<?php

namespace moca\capture\controllers;

use Craft;
use craft\helpers\UrlHelper;
use craft\web\Controller;
use moca\capture\fields\FieldType;
use moca\capture\models\Form;
use moca\capture\models\FormField;
use moca\capture\models\Notification;
use moca\capture\Plugin;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * The Forms screens.
 *
 * Form definitions go into project config, so every write here requires admin changes to be
 * allowed. `requireAdmin(false)` checks the permission without forcing an elevated session,
 * matching how Craft's own settings controllers behave.
 */
class FormsController extends Controller
{
    /**
     * @inheritdoc
     */
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->requireAdmin(false);

        return true;
    }

    /**
     * Lists forms.
     */
    public function actionIndex(): Response
    {
        return $this->renderTemplate('capture/forms/_index', [
            'forms' => Plugin::getInstance()->forms->getAllForms(),
        ]);
    }

    /**
     * Shows the form edit screen.
     *
     * @throws NotFoundHttpException
     */
    public function actionEdit(?int $formId = null, ?Form $form = null): Response
    {
        // $form is only ever passed back by actionSave() on a validation failure, so the
        // user's unsaved input survives the round trip.
        if ($form === null) {
            if ($formId !== null) {
                $form = Plugin::getInstance()->forms->getFormById($formId);

                if ($form === null) {
                    throw new NotFoundHttpException('Form not found');
                }
            } else {
                $form = new Form();
            }
        }

        return $this->renderTemplate('capture/forms/_edit', [
            'form' => $form,
            'isNew' => $form->id === null,
            'fieldTypes' => $this->_fieldTypeOptions(),
            'title' => $form->id === null
                ? Craft::t('capture', 'Create a new form')
                : $form->name,
        ]);
    }

    /**
     * Saves a form.
     */
    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        $request = $this->request;
        $formId = $request->getBodyParam('formId');

        if ($formId) {
            $form = Plugin::getInstance()->forms->getFormById((int)$formId);

            if ($form === null) {
                throw new NotFoundHttpException('Form not found');
            }
        } else {
            $form = new Form();
        }

        $form->name = $request->getBodyParam('name', $form->name);
        $form->handle = $request->getBodyParam('handle', $form->handle);
        $form->fromName = $request->getBodyParam('fromName') ?: null;
        $form->fromEmail = $request->getBodyParam('fromEmail') ?: null;
        $form->replyToEmail = $request->getBodyParam('replyToEmail') ?: null;
        $form->successBehavior = $request->getBodyParam('successBehavior', Form::SUCCESS_REDIRECT);
        $form->redirectUrl = $request->getBodyParam('redirectUrl') ?: null;
        // Not `successMessage`: that is Craft's own flash-message param, which
        // setSuccessFlash() reads through getValidatedBodyParam() and expects to be hashed.
        $form->successMessage = $request->getBodyParam('formSuccessMessage') ?: null;
        $form->honeypotEnabled = (bool)$request->getBodyParam('honeypotEnabled');
        $form->recaptchaEnabled = (bool)$request->getBodyParam('recaptchaEnabled');
        $form->turnstileEnabled = (bool)$request->getBodyParam('turnstileEnabled');

        $form->setFields($this->_fieldsFromPost());
        $form->setNotifications($this->_notificationsFromPost($form));

        if (!Plugin::getInstance()->forms->saveForm($form)) {
            $this->setFailFlash(Craft::t('capture', 'Couldn’t save form.'));

            // Hand the populated model back to actionEdit() so nothing typed is lost.
            Craft::$app->getUrlManager()->setRouteParams(['form' => $form]);

            return null;
        }

        $this->setSuccessFlash(Craft::t('capture', 'Form saved.'));

        return $this->redirectToPostedUrl($form);
    }

    /**
     * Deletes a form.
     */
    public function actionDelete(): Response
    {
        $this->requirePostRequest();

        $formId = (int)$this->request->getRequiredBodyParam('id');
        $form = Plugin::getInstance()->forms->getFormById($formId);

        if ($form === null) {
            throw new NotFoundHttpException('Form not found');
        }

        Plugin::getInstance()->forms->deleteForm($form);

        if ($this->request->getAcceptsJson()) {
            return $this->asSuccess();
        }

        $this->setSuccessFlash(Craft::t('capture', 'Form deleted.'));

        return $this->redirect(UrlHelper::cpUrl('capture/forms'));
    }

    /**
     * Builds field models from the builder table's posted rows.
     *
     * @return FormField[]
     */
    private function _fieldsFromPost(): array
    {
        $rows = $this->request->getBodyParam('fields') ?: [];
        $fields = [];

        foreach ($rows as $uid => $row) {
            if (($row['label'] ?? '') === '' && ($row['handle'] ?? '') === '') {
                continue;
            }

            $field = new FormField([
                // Existing rows post under their UID. Rows added in the browser post under
                // a generated id (row1, row2...), so anything that isn't a UUID is new.
                'uid' => $this->_uidOrNull($uid),
                'type' => $row['type'] ?? 'text',
                'handle' => $row['handle'] ?? '',
                'label' => $row['label'] ?? '',
                'placeholder' => ($row['placeholder'] ?? '') ?: null,
                'required' => !empty($row['required']),
                'errorMessage' => ($row['errorMessage'] ?? '') ?: null,
                'includeInEmail' => !empty($row['includeInEmail']),
            ]);

            $field->setOptions($this->_parseOptions($row['options'] ?? ''));

            $fields[] = $field;
        }

        return $fields;
    }

    /**
     * Builds notification models from the posted rows.
     *
     * The autoresponder is posted separately from the repeatable recipient rows, because it
     * has no address of its own and cannot be deleted.
     *
     * @return Notification[]
     */
    private function _notificationsFromPost(Form $form): array
    {
        $rows = $this->request->getBodyParam('notifications') ?: [];
        $notifications = [];

        foreach ($rows as $uid => $row) {
            if (($row['recipientEmail'] ?? '') === '') {
                continue;
            }

            $notifications[] = new Notification([
                'uid' => $this->_uidOrNull($uid),
                'kind' => Notification::KIND_RECIPIENT,
                'recipientEmail' => $row['recipientEmail'],
                'subject' => ($row['subject'] ?? '') ?: null,
                'templatePath' => ($row['templatePath'] ?? '') ?: null,
                'enabled' => !empty($row['enabled']),
            ]);
        }

        // Carry the existing autoresponder's UID so it keeps its identity across saves.
        $existing = null;

        foreach ($form->getNotifications() as $notification) {
            if ($notification->getIsAutoresponder()) {
                $existing = $notification;
                break;
            }
        }

        $notifications[] = new Notification([
            'uid' => $existing?->uid,
            'kind' => Notification::KIND_AUTORESPONDER,
            'recipientEmail' => null,
            'subject' => $this->request->getBodyParam('autoresponderSubject') ?: null,
            'templatePath' => $this->request->getBodyParam('autoresponderTemplate') ?: null,
            'enabled' => (bool)$this->request->getBodyParam('autoresponderEnabled'),
        ]);

        return $notifications;
    }

    /**
     * Returns the posted row key if it is a UID, or null if the row is new.
     */
    private function _uidOrNull(int|string $key): ?string
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', (string)$key)
            ? (string)$key
            : null;
    }

    /**
     * Parses the options column: one option per line, either `Label` or `Label:value`.
     */
    private function _parseOptions(string $raw): array
    {
        $options = [];

        foreach (preg_split('/\R/', $raw) as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            if (str_contains($line, ':')) {
                [$label, $value] = array_map('trim', explode(':', $line, 2));
            } else {
                $label = $line;
                $value = $line;
            }

            $options[] = ['label' => $label, 'value' => $value];
        }

        return $options;
    }

    /**
     * The field types the builder offers.
     */
    private function _fieldTypeOptions(): array
    {
        return FieldType::options();
    }
}
