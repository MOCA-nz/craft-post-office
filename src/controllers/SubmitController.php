<?php

namespace moca\postoffice\controllers;

use Craft;
use craft\web\Controller;
use moca\postoffice\elements\Submission;
use moca\postoffice\models\Form;
use moca\postoffice\Plugin;
use moca\postoffice\services\Log;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * The front-end submission endpoint.
 *
 * Serves both rendering modes: a normal POST that redirects, and a `fetch()` that gets JSON
 * back. The only difference is how the outcome is reported, so the build, spam, validate and
 * save path below is shared.
 */
class SubmitController extends Controller
{
    /**
     * @inheritdoc
     */
    public array|bool|int $allowAnonymous = true;

    /**
     * Accepts a submission.
     *
     * @throws NotFoundHttpException
     */
    public function actionIndex(): ?Response
    {
        $this->requirePostRequest();

        $plugin = Plugin::getInstance();
        $handle = (string)$this->request->getRequiredBodyParam('formHandle');
        $form = $plugin->forms->getFormByHandle($handle);

        if ($form === null) {
            throw new NotFoundHttpException("No form exists with the handle “{$handle}”.");
        }

        // Spam first: a rejected submission should never reach the database or the mailer.
        if (!$plugin->spam->check($form)) {
            $plugin->log->write(
                Log::LEVEL_WARNING,
                'spam.rejected',
                // Braced interpolation: a curly quote is a non-ASCII byte, and PHP accepts those in
                // identifiers, so "$form->handle”" parses the quote as part of the property name.
                "A submission to “{$form->handle}” was rejected as spam.",
                $form->id,
            );

            // Answer exactly as a success would. Telling a bot which check caught it is
            // free information for tuning against you.
            return $this->_success($form, new Submission());
        }

        $submission = new Submission();
        $submission->formId = $form->id;
        // The site the visitor was actually on. Without this every submission lands on the
        // primary site, and a multi-site install cannot tell them apart.
        $submission->siteId = Craft::$app->getSites()->getCurrentSite()->id;
        $submission->ipAddress = $this->request->getUserIP();
        $submission->userAgent = $this->request->getUserAgent();
        $submission->setValues($this->_valuesFromPost($form));

        if (!Craft::$app->getElements()->saveElement($submission)) {
            return $this->_failure($form, $submission);
        }

        return $this->_success($form, $submission);
    }

    /**
     * Reads posted values, keyed by field handle.
     *
     * Only handles the form actually declares are read, so an extra input in hand-written
     * markup cannot smuggle a value into storage.
     */
    private function _valuesFromPost(Form $form): array
    {
        $posted = $this->request->getBodyParam('fields') ?: [];
        $values = [];

        foreach ($form->getFields() as $field) {
            $value = $posted[$field->handle] ?? null;

            if ($field->getFieldType()->isMultiValue()) {
                $values[$field->handle] = array_values(array_filter((array)($value ?? [])));
                continue;
            }

            $values[$field->handle] = is_string($value) ? trim($value) : $value;
        }

        return $values;
    }

    /**
     * Reports a successful submission.
     */
    private function _success(Form $form, Submission $submission): Response
    {
        $message = $form->successMessage ?: Craft::t('post-office', 'Thanks, your message has been sent.');

        if ($this->request->getAcceptsJson()) {
            return $this->asJson([
                'success' => true,
                'message' => $message,
                'submissionId' => $submission->id,
            ]);
        }

        Craft::$app->getSession()->setNotice($message);

        if ($form->successBehavior === Form::SUCCESS_REDIRECT && $form->redirectUrl) {
            return $this->redirect($form->redirectUrl);
        }

        // No redirect configured: honour a `redirect` input if the template posted one,
        // otherwise return to the page the form was on.
        return $this->redirectToPostedUrl($submission);
    }

    /**
     * Reports a failed submission.
     */
    private function _failure(Form $form, Submission $submission): ?Response
    {
        $errors = $submission->getFieldErrors();

        if ($this->request->getAcceptsJson()) {
            return $this->asJson([
                'success' => false,
                'message' => Craft::t('post-office', 'Please check the form for errors.'),
                'errors' => $errors,
            ]);
        }

        Craft::$app->getSession()->setError(Craft::t('post-office', 'Please check the form for errors.'));

        // Hand the submission back so the template can re-render with the values the
        // visitor typed and the errors against each field.
        Craft::$app->getUrlManager()->setRouteParams([
            'postOfficeSubmission' => $submission,
        ]);

        return null;
    }
}
