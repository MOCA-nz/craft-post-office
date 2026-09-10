<?php

namespace moca\postoffice\controllers;

use craft\web\Controller;
use moca\postoffice\elements\Submission;
use moca\postoffice\Plugin;
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
     * How many rows a page shows.
     */
    public const PAGE_SIZE = 100;

    /**
     * Lists sent notifications.
     */
    public function actionIndex(int $page = 1): Response
    {
        $page = max(1, $page);
        $query = Plugin::getInstance()->notifications->getSentQuery();

        // Counted before paging, so the screen can say how many there are rather than
        // silently showing the first N and stopping.
        $total = (int)$query->count();

        $rows = $query
            ->offset(($page - 1) * self::PAGE_SIZE)
            ->limit(self::PAGE_SIZE)
            ->all();

        // One query for every submission referenced, rather than one per row.
        $submissionIds = array_unique(array_column($rows, 'submissionId'));
        $submissions = $submissionIds !== []
            ? Submission::find()->id($submissionIds)->indexBy('id')->all()
            : [];

        return $this->renderTemplate('post-office/notifications/_index', [
            'rows' => $rows,
            'submissions' => $submissions,
            'page' => $page,
            'total' => $total,
            'totalPages' => max(1, (int)ceil($total / self::PAGE_SIZE)),
        ]);
    }
}
