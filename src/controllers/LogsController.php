<?php

namespace moca\postoffice\controllers;

use Craft;
use craft\helpers\UrlHelper;
use craft\web\Controller;
use moca\postoffice\Plugin;
use yii\web\Response;

/**
 * The Logs screen.
 *
 * Post Office's own events only: send failures, unreachable captchas, spam rejections. Craft's
 * log files are a separate concern and are not surfaced here.
 */
class LogsController extends Controller
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

    public function actionIndex(int $page = 1): Response
    {
        $page = max(1, $page);
        $forms = [];

        foreach (Plugin::getInstance()->forms->getAllForms() as $form) {
            $forms[$form->id] = $form;
        }

        $query = Plugin::getInstance()->log->getQuery();
        $total = (int)$query->count();

        return $this->renderTemplate('post-office/logs/_index', [
            'rows' => $query->offset(($page - 1) * self::PAGE_SIZE)->limit(self::PAGE_SIZE)->all(),
            'forms' => $forms,
            'page' => $page,
            'total' => $total,
            'totalPages' => max(1, (int)ceil($total / self::PAGE_SIZE)),
        ]);
    }

    public function actionClear(): Response
    {
        $this->requirePostRequest();

        Plugin::getInstance()->log->clear();
        $this->setSuccessFlash(Craft::t('post-office', 'Log cleared.'));

        return $this->redirect(UrlHelper::cpUrl('post-office/logs'));
    }
}
