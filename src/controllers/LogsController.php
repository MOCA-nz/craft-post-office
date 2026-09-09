<?php

namespace moca\capture\controllers;

use Craft;
use craft\helpers\UrlHelper;
use craft\web\Controller;
use moca\capture\Plugin;
use yii\web\Response;

/**
 * The Logs screen.
 *
 * Capture's own events only: send failures, unreachable captchas, spam rejections. Craft's
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

    public function actionIndex(): Response
    {
        $forms = [];

        foreach (Plugin::getInstance()->forms->getAllForms() as $form) {
            $forms[$form->id] = $form;
        }

        return $this->renderTemplate('capture/logs/_index', [
            'rows' => Plugin::getInstance()->log->getQuery()->limit(500)->all(),
            'forms' => $forms,
        ]);
    }

    public function actionClear(): Response
    {
        $this->requirePostRequest();

        Plugin::getInstance()->log->clear();
        $this->setSuccessFlash(Craft::t('capture', 'Log cleared.'));

        return $this->redirect(UrlHelper::cpUrl('capture/logs'));
    }
}
