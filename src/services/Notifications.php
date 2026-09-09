<?php

namespace moca\capture\services;

use Craft;
use craft\db\Query;
use craft\helpers\App;
use craft\helpers\Db;
use craft\web\View;
use DateTime;
use moca\capture\elements\Submission;
use moca\capture\migrations\Install;
use moca\capture\models\Form;
use moca\capture\models\Notification;
use moca\capture\Plugin;
use Throwable;
use yii\base\Component;

/**
 * Outgoing email, and the record of what was sent.
 *
 * One row is written to capture_sentnotifications per attempted send, successful or not, so
 * the Sent Notifications screen is a complete history rather than a success log.
 */
class Notifications extends Component
{
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';

    /**
     * Sends every enabled notification for a submission.
     *
     * Never throws: a mailer that is down must not lose the submission, which is already
     * saved by the time this runs. Failures are recorded and logged instead.
     */
    public function sendForSubmission(Submission $submission): void
    {
        $form = $submission->getForm();

        if ($form === null) {
            return;
        }

        foreach ($form->getNotifications() as $notification) {
            if (!$notification->enabled) {
                continue;
            }

            $recipient = $this->_resolveRecipient($notification, $submission, $form);

            if ($recipient === null) {
                // An autoresponder on a form with no email field, or with the field left
                // blank. Not an error worth failing over, but worth recording.
                Plugin::getInstance()->log->write(
                    Log::LEVEL_INFO,
                    'notification.skipped',
                    'No recipient could be resolved for the autoresponder.',
                    $form->id,
                    $submission->id,
                );

                continue;
            }

            $this->_send($notification, $submission, $form, $recipient);
        }
    }

    /**
     * Returns a query over sent notifications, newest first.
     */
    public function getSentQuery(): Query
    {
        return (new Query())
            ->from([Install::TABLE_SENTNOTIFICATIONS])
            ->orderBy(['dateCreated' => SORT_DESC]);
    }

    /**
     * Deletes sent-notification records older than the given number of days.
     *
     * @return int How many rows were removed.
     */
    public function pruneSent(int $days): int
    {
        if ($days <= 0) {
            return 0;
        }

        return Db::delete(Install::TABLE_SENTNOTIFICATIONS, [
            '<', 'dateCreated', Db::prepareDateForDb(new DateTime("-$days days")),
        ]);
    }

    /**
     * Renders the body of one notification.
     *
     * A notification with no template of its own falls back to the plugin's default, which
     * lists every field marked "include in email".
     *
     * @throws Throwable if the configured template does not exist or fails to render.
     */
    public function render(Notification $notification, Submission $submission, Form $form): string
    {
        $view = Craft::$app->getView();
        $template = $notification->templatePath ?: 'capture/_email';

        $variables = [
            'submission' => $submission,
            'form' => $form,
            'values' => $submission->getValues(),
            'rows' => $this->_emailRows($submission, $form),
        ];

        return $view->renderTemplate($template, $variables, View::TEMPLATE_MODE_SITE);
    }

    /**
     * Resolves who a notification goes to.
     *
     * A recipient row carries a fixed address. The autoresponder has none: it resolves the
     * submission's own email value at send time, which is the whole point of it.
     */
    private function _resolveRecipient(Notification $notification, Submission $submission, Form $form): ?string
    {
        if (!$notification->getIsAutoresponder()) {
            $configured = (string)$notification->recipientEmail;
            $valueTemplate = Plugin::getInstance()->valueTemplate;

            if ($valueTemplate->isTemplated($configured)) {
                return $this->_resolveTemplatedRecipient($notification, $submission, $form);
            }

            $address = App::parseEnv($configured);

            return $address !== '' ? $address : null;
        }

        $emailField = $form->getEmailField();

        if ($emailField === null) {
            return null;
        }

        $value = $submission->getValues()[$emailField->handle] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Resolves a recipient that is driven by a form field.
     *
     * Only the field types in ValueTemplate::RECIPIENT_TYPES may decide this, and whatever
     * they resolve to still has to look like an address. A failure here is recorded against
     * the submission rather than thrown, because the enquiry is already saved and the person
     * who needs to know is whoever is reading Sent Notifications.
     */
    private function _resolveTemplatedRecipient(Notification $notification, Submission $submission, Form $form): ?string
    {
        $template = (string)$notification->recipientEmail;
        $errors = [];
        $resolved = Plugin::getInstance()->valueTemplate->resolve(
            $template,
            $submission,
            ValueTemplate::RECIPIENT_TYPES,
            $errors,
        );

        $addresses = array_values(array_filter(array_map('trim', explode(',', $resolved))));

        foreach ($addresses as $address) {
            if (!filter_var($address, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "“{$address}” is not a valid email address.";
            }
        }

        // Only worth saying when nothing above already explains why.
        if ($addresses === [] && $errors === []) {
            $errors[] = 'The recipient resolved to nothing.';
        }

        if ($errors !== []) {
            $this->_record(
                $notification,
                $submission,
                $template,
                Craft::t('capture', 'Unresolved recipient'),
                self::STATUS_FAILED,
                implode(' ', $errors),
            );

            Plugin::getInstance()->log->write(
                Log::LEVEL_ERROR,
                'recipient.unresolved',
                "A templated recipient could not be resolved: " . implode(' ', $errors),
                $form->id,
                $submission->id,
                ['template' => $template],
            );

            return null;
        }

        return implode(',', $addresses);
    }

    /**
     * Resolves a subject that may reference form fields.
     *
     * Any field may drive a subject: unlike a recipient, a subject cannot send mail
     * somewhere unintended, so there is nothing to restrict.
     */
    private function _resolveSubject(Notification $notification, Submission $submission, Form $form): string
    {
        $subject = $notification->getSubject($form->name);
        $valueTemplate = Plugin::getInstance()->valueTemplate;

        if (!$valueTemplate->isTemplated($subject)) {
            return $subject;
        }

        $errors = [];
        $resolved = trim($valueTemplate->resolve($subject, $submission, null, $errors));

        // A subject that resolved to nothing is worse than a generic one.
        return $resolved !== '' ? $resolved : $notification->getDefaultSubject($form->name);
    }

    /**
     * Renders and sends one notification, recording the outcome either way.
     */
    private function _send(Notification $notification, Submission $submission, Form $form, string $recipient): void
    {
        $subject = $this->_resolveSubject($notification, $submission, $form);

        try {
            $body = $this->render($notification, $submission, $form);

            $message = Craft::$app->getMailer()->compose()
                ->setTo(str_contains($recipient, ',') ? explode(',', $recipient) : $recipient)
                ->setSubject($subject)
                ->setHtmlBody($body);

            if ($from = App::parseEnv($form->fromEmail)) {
                $message->setFrom($form->fromName ? [$from => App::parseEnv($form->fromName)] : $from);
            }

            if ($replyTo = $this->_replyTo($submission, $form)) {
                $message->setReplyTo($replyTo);
            }

            if (!$message->send()) {
                throw new \RuntimeException('The mailer refused to send the message.');
            }

            $this->_record($notification, $submission, $recipient, $subject, self::STATUS_SENT);
        } catch (Throwable $e) {
            $this->_record($notification, $submission, $recipient, $subject, self::STATUS_FAILED, $e->getMessage());

            Plugin::getInstance()->log->write(
                Log::LEVEL_ERROR,
                'notification.failed',
                "Sending to $recipient failed: {$e->getMessage()}",
                $form->id,
                $submission->id,
                ['template' => $notification->templatePath, 'exception' => $e::class],
            );
        }
    }

    /**
     * The Reply-To address: the form's override, else the submitter's own email.
     */
    private function _replyTo(Submission $submission, Form $form): ?string
    {
        if ($configured = App::parseEnv($form->replyToEmail)) {
            return $configured;
        }

        $emailField = $form->getEmailField();

        if ($emailField === null) {
            return null;
        }

        $value = $submission->getValues()[$emailField->handle] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * The label/value pairs the default email template renders.
     *
     * Honours each field's "include in email" switch, which is the only place that setting
     * has any effect.
     */
    private function _emailRows(Submission $submission, Form $form): array
    {
        $values = $submission->getValues();
        $rows = [];

        foreach ($form->getFields() as $field) {
            if (!$field->includeInEmail) {
                continue;
            }

            $value = $values[$field->handle] ?? null;

            $rows[] = [
                'label' => $field->label,
                'value' => $field->formatValue($value),
            ];
        }

        return $rows;
    }

    private function _record(
        Notification $notification,
        Submission $submission,
        string $recipient,
        string $subject,
        string $status,
        ?string $error = null,
    ): void {
        Db::insert(Install::TABLE_SENTNOTIFICATIONS, [
            'submissionId' => $submission->id,
            'notificationId' => $notification->id,
            'recipientEmail' => $recipient,
            'subject' => $subject,
            'status' => $status,
            'error' => $error,
        ]);
    }
}
