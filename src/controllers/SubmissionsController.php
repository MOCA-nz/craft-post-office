<?php

namespace moca\postoffice\controllers;

use craft\web\Controller;
use moca\postoffice\elements\Submission;
use moca\postoffice\Plugin;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * The Submissions screens.
 *
 * Submissions are content rather than structure, so unlike the Forms screens these are gated
 * on a plugin permission, not on admin-changes.
 */
class SubmissionsController extends Controller
{
    /**
     * The permission this screen is gated on. Referenced by the registration in Plugin, so
     * the handle cannot drift between where it is granted and where it is checked.
     */
    public const PERMISSION_VIEW_SUBMISSIONS = 'post-office:view-submissions';

    /**
     * @inheritdoc
     */
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->requirePermission(self::PERMISSION_VIEW_SUBMISSIONS);

        return true;
    }

    /**
     * The submissions element index.
     *
     * Everything on this screen (sources, columns, search, sort, export, trash) comes from
     * Craft's element index, driven by the Submission element's own definitions.
     */
    public function actionIndex(): Response
    {
        return $this->renderTemplate('post-office/submissions/_index');
    }

    /**
     * A single submission.
     *
     * @throws NotFoundHttpException
     */
    public function actionView(int $submissionId): Response
    {
        $submission = Submission::find()->id($submissionId)->one();

        if (!$submission instanceof Submission) {
            throw new NotFoundHttpException('Submission not found');
        }

        return $this->renderTemplate('post-office/submissions/_view', [
            'submission' => $submission,
            'form' => $submission->getForm(),
            'sent' => Plugin::getInstance()->notifications
                ->getSentQuery()
                ->where(['submissionId' => $submission->id])
                ->all(),
        ]);
    }
}
