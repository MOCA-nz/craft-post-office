<?php

namespace moca\capture\controllers;

use craft\web\Controller;
use moca\capture\elements\Submission;
use moca\capture\Plugin;
use yii\web\Response;

/**
 * The Sent Notifications screen.
 *
 * A log of what went out and when, with a link back to the submission that triggered it. The
 * submission itself is where the content lives, so it is not duplicated here.
 */
class NotificationsController extends Controller
{
    /**
     * @inheritdoc
     */
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->requirePermission(SubmissionsController::PERMISSION_VIEW_SUBMISSIONS);

        return true;
    }

    /**
     * Lists sent notifications.
     */
    public function actionIndex(): Response
    {
        $rows = Plugin::getInstance()->notifications->getSentQuery()->limit(200)->all();

        // One query for every submission referenced, rather than one per row.
        $submissionIds = array_unique(array_column($rows, 'submissionId'));
        $submissions = $submissionIds !== []
            ? Submission::find()->id($submissionIds)->indexBy('id')->all()
            : [];

        return $this->renderTemplate('capture/notifications/_index', [
            'rows' => $rows,
            'submissions' => $submissions,
        ]);
    }
}
