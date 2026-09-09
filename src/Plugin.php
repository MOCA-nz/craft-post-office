<?php

namespace moca\capture;

use Craft;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterTemplateRootsEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\helpers\UrlHelper;
use craft\services\Elements;
use craft\services\UserPermissions;
use craft\web\UrlManager;
use craft\web\View;
use craft\web\twig\variables\CraftVariable;
use moca\capture\controllers\SubmissionsController;
use moca\capture\elements\Submission;
use moca\capture\models\Settings;
use moca\capture\services\Forms;
use moca\capture\services\Log;
use moca\capture\services\Notifications;
use moca\capture\services\Spam;
use moca\capture\services\Submissions;
use moca\capture\variables\CaptureVariable;
use yii\base\Event;

/**
 * Capture: a contact form builder.
 *
 * Form definitions (forms, their fields and their notification rows) are structure and live
 * in project config, so a form built locally deploys to production. Submissions, sent
 * notifications and logs are content and live only in the database.
 *
 * @property-read Forms $forms
 * @property-read Submissions $submissions
 * @property-read Notifications $notifications
 * @property-read Spam $spam
 * @property-read Log $log
 * @method Settings getSettings()
 */
class Plugin extends BasePlugin
{
    public string $schemaVersion = '1.0.0';

    public bool $hasCpSettings = true;

    public bool $hasCpSection = true;

    /**
     * @inheritdoc
     */
    public static function config(): array
    {
        return [
            'components' => [
                'forms' => ['class' => Forms::class],
                'submissions' => ['class' => Submissions::class],
                'notifications' => ['class' => Notifications::class],
                'spam' => ['class' => Spam::class],
                'log' => ['class' => Log::class],
            ],
        ];
    }

    public function init(): void
    {
        parent::init();

        // Element type registration must never be gated by request context. Gate it behind
        // getIsCpRequest() and the type vanishes from console and queue requests, at which
        // point garbage collection silently stops purging trashed submissions.
        $this->_registerElementTypes();

        $this->_registerVariable();
        $this->_registerPermissions();
        $this->_registerSiteTemplateRoot();

        Craft::$app->onInit(function() {
            $this->_registerProjectConfigHandlers();
        });

        if (Craft::$app->getRequest()->getIsCpRequest()) {
            $this->_registerCpUrlRules();
        }
    }

    /**
     * @inheritdoc
     */
    public function getCpNavItem(): ?array
    {
        $item = parent::getCpNavItem();

        if ($item === null) {
            return null;
        }

        $item['subnav'] = [
            'forms' => [
                'label' => Craft::t('capture', 'Forms'),
                'url' => 'capture/forms',
            ],
            'submissions' => [
                'label' => Craft::t('capture', 'Submissions'),
                'url' => 'capture/submissions',
            ],
            'sent-notifications' => [
                'label' => Craft::t('capture', 'Sent Notifications'),
                'url' => 'capture/sent-notifications',
            ],
            'logs' => [
                'label' => Craft::t('capture', 'Logs'),
                'url' => 'capture/logs',
            ],
        ];

        return $item;
    }

    /**
     * @inheritdoc
     */
    public function getSettingsResponse(): mixed
    {
        return Craft::$app->getResponse()->redirect(UrlHelper::cpUrl('capture/settings'));
    }

    /**
     * @inheritdoc
     */
    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }

    private function _registerElementTypes(): void
    {
        Event::on(
            Elements::class,
            Elements::EVENT_REGISTER_ELEMENT_TYPES,
            function(RegisterComponentTypesEvent $event) {
                $event->types[] = Submission::class;
            }
        );
    }

    private function _registerPermissions(): void
    {
        Event::on(
            UserPermissions::class,
            UserPermissions::EVENT_REGISTER_PERMISSIONS,
            function(RegisterUserPermissionsEvent $event) {
                $event->permissions[] = [
                    'heading' => Craft::t('capture', 'Capture'),
                    'permissions' => [
                        SubmissionsController::PERMISSION_VIEW_SUBMISSIONS => [
                            'label' => Craft::t('capture', 'View submissions'),
                        ],
                    ],
                ];
            }
        );
    }

    /**
     * Makes the plugin's front-end templates addressable as `capture/*`.
     *
     * Craft registers a plugin's templates for the control panel automatically, but not for
     * the site, so the front-end form templates need this. A project can override any of
     * them by creating the same path under its own `templates/capture/` directory.
     */
    private function _registerSiteTemplateRoot(): void
    {
        Event::on(
            View::class,
            View::EVENT_REGISTER_SITE_TEMPLATE_ROOTS,
            function(RegisterTemplateRootsEvent $event) {
                $event->roots['capture'] = __DIR__ . '/templates/site';
            }
        );
    }

    private function _registerVariable(): void
    {
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function(Event $event) {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('capture', CaptureVariable::class);
            }
        );
    }

    private function _registerProjectConfigHandlers(): void
    {
        $this->forms->registerProjectConfigHandlers();
    }

    private function _registerCpUrlRules(): void
    {
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                $event->rules['capture'] = 'capture/forms/index';
                $event->rules['capture/forms'] = 'capture/forms/index';
                $event->rules['capture/forms/new'] = 'capture/forms/edit';
                $event->rules['capture/forms/<formId:\d+>'] = 'capture/forms/edit';
                $event->rules['capture/submissions'] = 'capture/submissions/index';
                $event->rules['capture/submissions/<submissionId:\d+>'] = 'capture/submissions/view';
                $event->rules['capture/sent-notifications'] = 'capture/notifications/index';
                $event->rules['capture/logs'] = 'capture/logs/index';
                $event->rules['capture/sent-notifications/<page:\d+>'] = 'capture/notifications/index';
                $event->rules['capture/settings'] = 'capture/settings/index';
            }
        );
    }
}
