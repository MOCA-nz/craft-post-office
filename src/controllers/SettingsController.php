<?php

namespace moca\postoffice\controllers;

use Craft;
use craft\helpers\UrlHelper;
use craft\web\Controller;
use moca\postoffice\models\Settings;
use moca\postoffice\Plugin;
use yii\web\Response;

/**
 * The plugin settings screen.
 *
 * Lives inside the plugin's own CP section rather than on Settings > Plugins, so the four
 * subnav items and this screen are one place. Craft's plugin settings link redirects here.
 */
class SettingsController extends Controller
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

    public function actionIndex(): Response
    {
        /** @var Settings $settings */
        $settings = Plugin::getInstance()->getSettings();

        return $this->renderTemplate('post-office/settings/_index', [
            'settings' => $settings,
            'readOnly' => !Craft::$app->getConfig()->getGeneral()->allowAdminChanges,
        ]);
    }

    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        $plugin = Plugin::getInstance();
        /** @var Settings $settings */
        $settings = $plugin->getSettings();

        // Load the model first and update properties, rather than passing the body params
        // straight to savePluginSettings(): only submitted keys would persist and anything
        // else on the model would be silently dropped.
        $settings->recaptchaEnabled = (bool)$this->request->getBodyParam('recaptchaEnabled');
        $settings->turnstileEnabled = (bool)$this->request->getBodyParam('turnstileEnabled');
        $settings->recaptchaSiteKey = (string)$this->request->getBodyParam('recaptchaSiteKey', '');
        $settings->recaptchaSecretKey = (string)$this->request->getBodyParam('recaptchaSecretKey', '');
        $settings->turnstileSiteKey = (string)$this->request->getBodyParam('turnstileSiteKey', '');
        $settings->turnstileSecretKey = (string)$this->request->getBodyParam('turnstileSecretKey', '');
        $settings->historyRetentionDays = (int)$this->request->getBodyParam('historyRetentionDays', 90);

        if (!Craft::$app->getPlugins()->savePluginSettings($plugin, $settings->toArray())) {
            $this->setFailFlash(Craft::t('post-office', 'Couldn’t save settings.'));
            Craft::$app->getUrlManager()->setRouteParams(['settings' => $settings]);

            return null;
        }

        $this->setSuccessFlash(Craft::t('post-office', 'Settings saved.'));

        return $this->redirect(UrlHelper::cpUrl('post-office/settings'));
    }
}
