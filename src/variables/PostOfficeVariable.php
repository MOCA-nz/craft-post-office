<?php

namespace moca\postoffice\variables;

use Craft;
use craft\helpers\Template;
use craft\web\View;
use moca\postoffice\elements\db\SubmissionQuery;
use moca\postoffice\elements\Submission;
use moca\postoffice\models\Form;
use moca\postoffice\Plugin;
use moca\postoffice\services\Spam;
use Twig\Markup;

/**
 * `craft.postOffice` in Twig.
 */
class PostOfficeVariable
{
    /**
     * The submit button's class when the template doesn't ask for another one. The plugin's
     * default styles hang off this, so replacing it also opts out of them.
     */
    public const DEFAULT_SUBMIT_CLASS = 'post-office-submit';

    /**
     * Returns a form definition by handle.
     */
    public function getForm(string $handle): ?Form
    {
        return Plugin::getInstance()->forms->getFormByHandle($handle);
    }

    /**
     * @return Form[]
     */
    public function getForms(): array
    {
        return Plugin::getInstance()->forms->getAllForms();
    }

    /**
     * Returns a submission query.
     *
     * Craft's `craft.query()` is a generic database query builder and takes no element type,
     * so without this there is no way to query submissions from a template at all.
     *
     *     {% for submission in craft.postOffice.submissions({ formId: form.id, limit: 5 }).all() %}
     */
    public function submissions(array $criteria = []): SubmissionQuery
    {
        /** @var SubmissionQuery $query */
        $query = Submission::find();

        if ($criteria !== []) {
            Craft::configure($query, $criteria);
        }

        return $query;
    }

    /**
     * Returns how many submissions a form has received.
     */
    public function submissionCount(int $formId, ?int $siteId = null): int
    {
        $query = Submission::find()->formId($formId);

        // Across every site by default: a count on a form's own screen means "how many have
        // come in", not "how many on the site I happen to be viewing".
        $query->siteId($siteId ?? '*');

        return (int)$query->count();
    }

    /**
     * Renders a form.
     *
     * Returns null rather than throwing when the handle is unknown, so a mistyped handle in
     * a template does not take the page down.
     *
     * The submit button is the one part of the markup a project usually needs to change
     * without overriding the whole template, so it takes its label, class and id from here:
     *
     *     {{ craft.postOffice.form('contact', {
     *         submitText: 'Send enquiry',
     *         submitClass: 'btn btn--primary',
     *         submitId: 'enquiry-submit',
     *     }) }}
     *
     * A class given here replaces the default rather than joining it, which also takes the
     * button out of the plugin's own styles: they are written against the default class, so
     * nothing has to be un-styled.
     *
     * @param array{submitText?: string, submitClass?: string, submitId?: string} $options
     */
    public function form(string $handle, array $options = []): ?Markup
    {
        $form = $this->getForm($handle);

        if ($form === null) {
            return null;
        }

        $view = Craft::$app->getView();

        // A failed submission is handed back by SubmitController through route params, so
        // the re-rendered form keeps what the visitor typed and shows the errors.
        $submission = Craft::$app->getUrlManager()->getRouteParams()['postOfficeSubmission'] ?? null;

        $html = $view->renderTemplate('post-office/_form', [
            'form' => $form,
            'postOfficeSubmission' => $submission instanceof Submission && $submission->formId === $form->id
                ? $submission
                : null,
            'honeypotField' => Spam::HONEYPOT_FIELD,
            'timestampField' => Spam::TIMESTAMP_FIELD,
            'timestamp' => Plugin::getInstance()->spam->timestampValue(),
            'submitText' => (string)($options['submitText'] ?? '') ?: Craft::t('post-office', 'Send'),
            'submitClass' => (string)($options['submitClass'] ?? '') ?: self::DEFAULT_SUBMIT_CLASS,
            'submitId' => (string)($options['submitId'] ?? '') ?: null,
        ], View::TEMPLATE_MODE_SITE);

        return Template::raw($html);
    }
}
