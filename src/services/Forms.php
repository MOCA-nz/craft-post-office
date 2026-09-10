<?php

namespace moca\postoffice\services;

use Craft;
use craft\db\Query;
use craft\db\Table;
use craft\events\ConfigEvent;
use craft\helpers\ArrayHelper;
use craft\helpers\Db;
use craft\helpers\StringHelper;
use moca\postoffice\migrations\Install;
use moca\postoffice\models\Form;
use moca\postoffice\models\FormField;
use moca\postoffice\models\Notification;
use moca\postoffice\records\Form as FormRecord;
use moca\postoffice\records\FormField as FormFieldRecord;
use moca\postoffice\records\Notification as NotificationRecord;
use Throwable;
use yii\base\Component;

/**
 * Form definitions: forms, their fields, and their notification rows.
 *
 * All three are structure rather than content, so this service owns both the database rows
 * and their project config mirror. Writes go to project config and come back through the
 * change handlers, which is what makes a form built locally deploy to production.
 *
 * Reads come from the database, because project config is not a query engine.
 */
class Forms extends Component
{
    public const CONFIG_PATH = 'post-office.forms';

    /**
     * @var Form[]|null Memoized so a request that touches forms repeatedly hits the database once.
     */
    private ?array $_forms = null;

    // Project config
    // =========================================================================

    /**
     * Registers the project config change handlers.
     *
     * Called from Plugin::init() inside onInit(), so project config has finished booting.
     */
    public function registerProjectConfigHandlers(): void
    {
        Craft::$app->getProjectConfig()
            ->onAdd(self::CONFIG_PATH . '.{uid}', [$this, 'handleChangedForm'])
            ->onUpdate(self::CONFIG_PATH . '.{uid}', [$this, 'handleChangedForm'])
            ->onRemove(self::CONFIG_PATH . '.{uid}', [$this, 'handleDeletedForm']);
    }

    /**
     * Applies a form that was added or changed in project config.
     *
     * @throws Throwable
     */
    public function handleChangedForm(ConfigEvent $event): void
    {
        $uid = $event->tokenMatches[0];
        $data = $event->newValue;

        $transaction = Craft::$app->getDb()->beginTransaction();

        try {
            $record = FormRecord::findOne(['uid' => $uid]) ?? new FormRecord();
            $record->uid = $uid;
            $record->name = $data['name'];
            $record->handle = $data['handle'];
            $record->fromName = $data['fromName'] ?? null;
            $record->fromEmail = $data['fromEmail'] ?? null;
            $record->replyToEmail = $data['replyToEmail'] ?? null;
            $record->successBehavior = $data['successBehavior'] ?? Form::SUCCESS_REDIRECT;
            $record->redirectUrl = $data['redirectUrl'] ?? null;
            $record->successMessage = $data['successMessage'] ?? null;
            $record->honeypotEnabled = (bool)($data['honeypotEnabled'] ?? true);
            $record->recaptchaEnabled = (bool)($data['recaptchaEnabled'] ?? false);
            $record->turnstileEnabled = (bool)($data['turnstileEnabled'] ?? false);
            $record->sortOrder = $data['sortOrder'] ?? null;
            $record->save(false);

            $this->_applyFields((int)$record->id, $data['fields'] ?? []);
            $this->_applyNotifications((int)$record->id, $data['notifications'] ?? []);

            $transaction->commit();
        } catch (Throwable $e) {
            $transaction->rollBack();

            throw $e;
        }

        $this->_forms = null;
    }

    /**
     * Applies a form that was removed from project config.
     *
     * Fields, notifications and submissions cascade from the foreign keys.
     *
     * @throws Throwable
     */
    public function handleDeletedForm(ConfigEvent $event): void
    {
        $record = FormRecord::findOne(['uid' => $event->tokenMatches[0]]);

        if ($record === null) {
            return;
        }

        // Delete the submissions through the elements table, not through this table's own
        // foreign key. That key runs elements -> submissions, so deleting the form would
        // cascade the plugin rows away and strand their elements rows forever. Deleting the
        // elements rows instead cascades in the direction the key actually points.
        $submissionIds = (new Query())
            ->select(['id'])
            ->from([Install::TABLE_SUBMISSIONS])
            ->where(['formId' => $record->id])
            ->column();

        if ($submissionIds !== []) {
            // relations and elements_sites carry foreign keys to elements and clean
            // themselves up. searchindex and searchindexqueue do not, so deleting the
            // elements rows alone leaves index rows pointing at nothing.
            Db::delete(Table::SEARCHINDEX, ['elementId' => $submissionIds]);
            Db::deleteIfExists(Table::SEARCHINDEXQUEUE, ['elementId' => $submissionIds]);
            Db::delete(Table::ELEMENTS, ['id' => $submissionIds]);
        }

        Db::delete(Install::TABLE_FORMS, ['id' => $record->id]);

        $this->_forms = null;
    }

    // Reading
    // =========================================================================

    /**
     * @return Form[]
     */
    public function getAllForms(): array
    {
        if ($this->_forms !== null) {
            return $this->_forms;
        }

        $rows = $this->_createQuery()
            ->orderBy(['sortOrder' => SORT_ASC, 'name' => SORT_ASC])
            ->all();

        $forms = [];

        foreach ($rows as $row) {
            $form = new Form($row);
            $form->setFields($this->getFieldsByFormId((int)$form->id));
            $form->setNotifications($this->getNotificationsByFormId((int)$form->id));
            $forms[] = $form;
        }

        return $this->_forms = $forms;
    }

    public function getFormById(int $id): ?Form
    {
        return ArrayHelper::firstWhere($this->getAllForms(), 'id', $id);
    }

    public function getFormByHandle(string $handle): ?Form
    {
        return ArrayHelper::firstWhere($this->getAllForms(), 'handle', $handle);
    }

    public function getFormByUid(string $uid): ?Form
    {
        return ArrayHelper::firstWhere($this->getAllForms(), 'uid', $uid);
    }

    /**
     * @return FormField[]
     */
    public function getFieldsByFormId(int $formId): array
    {
        $rows = (new Query())
            ->select(['id', 'formId', 'type', 'handle', 'label', 'placeholder', 'required', 'errorMessage', 'includeInEmail', 'options', 'sortOrder', 'uid'])
            ->from([Install::TABLE_FORMFIELDS])
            ->where(['formId' => $formId])
            ->orderBy(['sortOrder' => SORT_ASC])
            ->all();

        return array_map(static function(array $row) {
            $options = $row['options'];
            unset($row['options']);
            $field = new FormField($row);
            $field->setOptions($options);

            return $field;
        }, $rows);
    }

    /**
     * @return Notification[]
     */
    public function getNotificationsByFormId(int $formId): array
    {
        $rows = (new Query())
            ->select(['id', 'formId', 'kind', 'recipientEmail', 'subject', 'templatePath', 'enabled', 'sortOrder', 'uid'])
            ->from([Install::TABLE_NOTIFICATIONS])
            ->where(['formId' => $formId])
            // The autoresponder sorts last so the builder can render it as a fixed row
            // beneath the editable ones.
            ->orderBy(['kind' => SORT_ASC, 'sortOrder' => SORT_ASC])
            ->all();

        return array_map(static fn(array $row) => new Notification($row), $rows);
    }

    // Writing
    // =========================================================================

    /**
     * Saves a form to project config.
     *
     * The database rows are written by handleChangedForm() when the config change is applied.
     */
    public function saveForm(Form $form, bool $runValidation = true): bool
    {
        $isNew = $form->id === null;

        if ($runValidation && !$form->validate()) {
            return false;
        }

        if ($form->uid === null) {
            $form->uid = $isNew ? StringHelper::UUID() : Db::uidById(Install::TABLE_FORMS, $form->id);
        }

        if ($isNew && $form->sortOrder === null) {
            $form->sortOrder = (new Query())->from([Install::TABLE_FORMS])->count() + 1;
        }

        $this->_ensureAutoresponder($form);

        Craft::$app->getProjectConfig()->set(
            self::CONFIG_PATH . '.' . $form->uid,
            $this->getConfig($form),
            "Save the “{$form->handle}” form",
        );

        if ($isNew) {
            $form->id = Db::idByUid(Install::TABLE_FORMS, $form->uid);
        }

        return true;
    }

    /**
     * Deletes a form.
     */
    public function deleteForm(Form $form): bool
    {
        if ($form->uid === null) {
            return false;
        }

        Craft::$app->getProjectConfig()->remove(
            self::CONFIG_PATH . '.' . $form->uid,
            "Delete the “{$form->handle}” form",
        );

        return true;
    }

    /**
     * Builds the project config payload for a form.
     *
     * Keyed by UID throughout: project config never carries database IDs.
     */
    public function getConfig(Form $form): array
    {
        $config = [
            'name' => $form->name,
            'handle' => $form->handle,
            'fromName' => $form->fromName,
            'fromEmail' => $form->fromEmail,
            'replyToEmail' => $form->replyToEmail,
            'successBehavior' => $form->successBehavior,
            'redirectUrl' => $form->redirectUrl,
            'successMessage' => $form->successMessage,
            'honeypotEnabled' => $form->honeypotEnabled,
            'recaptchaEnabled' => $form->recaptchaEnabled,
            'turnstileEnabled' => $form->turnstileEnabled,
            'sortOrder' => $form->sortOrder,
            'fields' => [],
            'notifications' => [],
        ];

        foreach ($form->getFields() as $i => $field) {
            $config['fields'][$field->uid ?? StringHelper::UUID()] = [
                'type' => $field->type,
                'handle' => $field->handle,
                'label' => $field->label,
                'placeholder' => $field->placeholder,
                'required' => $field->required,
                'errorMessage' => $field->errorMessage,
                'includeInEmail' => $field->includeInEmail,
                'options' => $field->getOptions(),
                'sortOrder' => $i + 1,
            ];
        }

        foreach ($form->getNotifications() as $i => $notification) {
            $config['notifications'][$notification->uid ?? StringHelper::UUID()] = [
                'kind' => $notification->kind,
                'recipientEmail' => $notification->recipientEmail,
                'subject' => $notification->subject,
                'templatePath' => $notification->templatePath,
                'enabled' => $notification->enabled,
                'sortOrder' => $i + 1,
            ];
        }

        return $config;
    }

    // Private
    // =========================================================================

    /**
     * Guarantees the form carries exactly one autoresponder row.
     *
     * It is created on first save, never deleted, and only ever toggled.
     */
    private function _ensureAutoresponder(Form $form): void
    {
        $notifications = $form->getNotifications();

        foreach ($notifications as $notification) {
            if ($notification->getIsAutoresponder()) {
                return;
            }
        }

        $autoresponder = new Notification([
            'kind' => Notification::KIND_AUTORESPONDER,
            'enabled' => false,
            'uid' => StringHelper::UUID(),
        ]);

        $notifications[] = $autoresponder;
        $form->setNotifications($notifications);
    }

    /**
     * Syncs a form's field rows to match project config.
     */
    private function _applyFields(int $formId, array $fields): void
    {
        foreach ($fields as $uid => $data) {
            $record = FormFieldRecord::findOne(['uid' => $uid]) ?? new FormFieldRecord();
            $record->uid = $uid;
            $record->formId = $formId;
            $record->type = $data['type'];
            $record->handle = $data['handle'];
            $record->label = $data['label'];
            $record->placeholder = $data['placeholder'] ?? null;
            $record->required = (bool)($data['required'] ?? false);
            $record->errorMessage = $data['errorMessage'] ?? null;
            $record->includeInEmail = (bool)($data['includeInEmail'] ?? true);
            $record->options = !empty($data['options']) ? json_encode($data['options']) : null;
            $record->sortOrder = $data['sortOrder'] ?? null;
            $record->save(false);
        }

        // Anything no longer in config has been deleted from the form.
        Db::delete(Install::TABLE_FORMFIELDS, [
            'and',
            ['formId' => $formId],
            ['not', ['uid' => array_keys($fields)]],
        ]);
    }

    /**
     * Syncs a form's notification rows to match project config.
     */
    private function _applyNotifications(int $formId, array $notifications): void
    {
        foreach ($notifications as $uid => $data) {
            $record = NotificationRecord::findOne(['uid' => $uid]) ?? new NotificationRecord();
            $record->uid = $uid;
            $record->formId = $formId;
            $record->kind = $data['kind'] ?? Notification::KIND_RECIPIENT;
            $record->recipientEmail = $data['recipientEmail'] ?? null;
            $record->subject = $data['subject'] ?? null;
            $record->templatePath = $data['templatePath'] ?? null;
            $record->enabled = (bool)($data['enabled'] ?? true);
            $record->sortOrder = $data['sortOrder'] ?? null;
            $record->save(false);
        }

        Db::delete(Install::TABLE_NOTIFICATIONS, [
            'and',
            ['formId' => $formId],
            ['not', ['uid' => array_keys($notifications)]],
        ]);
    }

    private function _createQuery(): Query
    {
        return (new Query())
            ->select([
                'id',
                'name',
                'handle',
                'fromName',
                'fromEmail',
                'replyToEmail',
                'successBehavior',
                'redirectUrl',
                'successMessage',
                'honeypotEnabled',
                'recaptchaEnabled',
                'turnstileEnabled',
                'sortOrder',
                'uid',
            ])
            ->from([Install::TABLE_FORMS]);
    }
}
