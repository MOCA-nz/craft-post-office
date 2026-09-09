<?php

namespace moca\capture\variables;

use Craft;
use craft\helpers\Template;
use craft\web\View;
use moca\capture\elements\Submission;
use moca\capture\models\Form;
use moca\capture\Plugin;
use moca\capture\services\Spam;
use Twig\Markup;

/**
 * `craft.capture` in Twig.
 */
class CaptureVariable
{
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
     * Returns how many submissions a form has received.
     */
    public function submissionCount(int $formId): int
    {
        return (int)Submission::find()->formId($formId)->count();
    }

    /**
     * Renders a form.
     *
     * Returns null rather than throwing when the handle is unknown, so a mistyped handle in
     * a template does not take the page down.
     */
    public function form(string $handle): ?Markup
    {
        $form = $this->getForm($handle);

        if ($form === null) {
            return null;
        }

        $view = Craft::$app->getView();

        // A failed submission is handed back by SubmitController through route params, so
        // the re-rendered form keeps what the visitor typed and shows the errors.
        $submission = Craft::$app->getUrlManager()->getRouteParams()['captureSubmission'] ?? null;

        $html = $view->renderTemplate('capture/_form', [
            'form' => $form,
            'captureSubmission' => $submission instanceof Submission && $submission->formId === $form->id
                ? $submission
                : null,
            'honeypotField' => Spam::HONEYPOT_FIELD,
            'timestampField' => Spam::TIMESTAMP_FIELD,
            'timestamp' => Plugin::getInstance()->spam->timestampValue(),
        ], View::TEMPLATE_MODE_SITE);

        return Template::raw($html);
    }
}
