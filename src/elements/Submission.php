<?php

namespace moca\capture\elements;

use Craft;
use craft\base\Element;
use craft\elements\actions\Delete;
use craft\elements\actions\Restore;
use craft\elements\db\ElementQueryInterface;
use craft\elements\User;
use craft\helpers\Html;
use craft\helpers\UrlHelper;
use moca\capture\controllers\SubmissionsController;
use moca\capture\elements\db\SubmissionQuery;
use moca\capture\elements\exporters\SubmissionExport;
use moca\capture\models\Form;
use moca\capture\Plugin;
use moca\capture\queue\jobs\SendNotifications;
use yii\base\DynamicModel;

/**
 * A single form submission.
 *
 * Submissions are elements so the CP index, search, sorting, filtering, bulk actions, CSV
 * export and the trash all come from Craft rather than being rebuilt here.
 *
 * Submitted values are stored as JSON keyed by field handle rather than in per-field
 * columns. A contact form does not need per-field querying, and search indexing covers
 * finding submissions by their content.
 */
class Submission extends Element
{
    /**
     * @var int|null The form this submission belongs to.
     */
    public ?int $formId = null;

    /**
     * @var string|null The submitter's IP address at the time of submission.
     */
    public ?string $ipAddress = null;

    /**
     * @var string|null The submitter's user agent at the time of submission.
     */
    public ?string $userAgent = null;

    /**
     * @var array Submitted values, keyed by field handle.
     */
    private array $_values = [];

    private ?Form $_form = null;

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('capture', 'Submission');
    }

    /**
     * @inheritdoc
     */
    public static function pluralDisplayName(): string
    {
        return Craft::t('capture', 'Submissions');
    }

    /**
     * @inheritdoc
     */
    public static function refHandle(): ?string
    {
        return 'submission';
    }

    /**
     * @inheritdoc
     */
    public static function hasTitles(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public static function hasContent(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public static function hasStatuses(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     *
     * A submission exists on exactly the site it was submitted from. It is not content that
     * gets translated or propagated: it is a record of one event on one site, so copying it
     * to other sites would invent submissions nobody made.
     */
    public static function isLocalized(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     *
     * Only the submission's own site, for the same reason.
     */
    public function getSupportedSites(): array
    {
        return [$this->siteId ?? Craft::$app->getSites()->getPrimarySite()->id];
    }

    /**
     * @inheritdoc
     *
     * @return SubmissionQuery
     */
    public static function find(): ElementQueryInterface
    {
        return new SubmissionQuery(static::class);
    }

    /**
     * Returns the submitted values, keyed by field handle.
     */
    public function getValues(): array
    {
        return $this->_values;
    }

    /**
     * Sets the submitted values.
     *
     * @param array|string|null $values An array, or the JSON string as stored.
     */
    public function setValues(array|string|null $values): void
    {
        if (is_string($values)) {
            $values = json_decode($values, true) ?: [];
        }

        $this->_values = $values ?? [];
    }

    /**
     * Returns the form this submission was made through.
     */
    public function getForm(): ?Form
    {
        if ($this->_form !== null) {
            return $this->_form;
        }

        if ($this->formId === null) {
            return null;
        }

        return $this->_form = Plugin::getInstance()->forms->getFormById($this->formId);
    }

    /**
     * @inheritdoc
     *
     * Validation rules come from the form, not from this class: each form has a different
     * set of fields, so the rules are built per instance.
     */
    protected function defineRules(): array
    {
        return array_merge(parent::defineRules(), [
            [['formId'], 'required'],
        ]);
    }

    /**
     * @inheritdoc
     *
     * Field validation runs here rather than as a rule on `values`. `values` is backed by a
     * getter and setter, so it is not one of the element's attributes, and a rule naming it
     * is skipped depending on the validation scenario: it fired on saveElement() but not on
     * a bare validate(). afterValidate() runs on every path.
     */
    public function afterValidate(): void
    {
        $this->validateValues();

        parent::afterValidate();
    }

    /**
     * Validates the submitted values against the form's field definitions.
     *
     * Errors are added under the field's own handle rather than under `values`, so the JSON
     * response can be keyed by handle and a template can look up one field's error directly.
     */
    public function validateValues(): void
    {
        $form = $this->getForm();

        if ($form === null) {
            return;
        }

        foreach ($form->getFields() as $field) {
            $type = $field->getFieldType();
            $value = $this->_values[$field->handle] ?? null;
            $isEmpty = $value === null || $value === '' || $value === [];

            if ($field->required && $isEmpty) {
                $this->addError($field->handle, $field->getRequiredMessage());
                continue;
            }

            // Only the required check applies to an empty optional field: running the
            // type's validator on '' would reject every blank optional field.
            if ($isEmpty) {
                continue;
            }

            $validator = $type->validator();

            if ($validator === null) {
                continue;
            }

            $model = DynamicModel::validateData([$field->handle => $value], [
                [[$field->handle], $validator],
            ]);

            if ($model->hasErrors()) {
                $this->addError($field->handle, $field->getTypeErrorMessage());
            }
        }
    }

    /**
     * Returns validation errors keyed by field handle.
     *
     * This is the shape the JSON response and the front-end templates both consume.
     */
    public function getFieldErrors(): array
    {
        $errors = [];

        foreach ($this->getForm()?->getFields() ?? [] as $field) {
            if ($this->hasErrors($field->handle)) {
                $errors[$field->handle] = $this->getErrors($field->handle);
            }
        }

        return $errors;
    }

    /**
     * @inheritdoc
     */
    public function getUiLabel(): string
    {
        // Submissions have no title, so label them by their first email or text value,
        // falling back to the id. This is what shows in the index and in chips.
        foreach ($this->_values as $value) {
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return Craft::t('capture', 'Submission {id}', ['id' => $this->id]);
    }

    /**
     * @inheritdoc
     *
     * One source per form, built from the forms table.
     */
    protected static function defineSources(string $context = null): array
    {
        $sources = [
            [
                'key' => '*',
                'label' => Craft::t('capture', 'All submissions'),
                'defaultSort' => ['dateCreated', 'desc'],
            ],
        ];

        foreach (Plugin::getInstance()->forms->getAllForms() as $form) {
            $sources[] = [
                'key' => "form:$form->handle",
                'label' => $form->name,
                'criteria' => ['formId' => $form->id],
                'defaultSort' => ['dateCreated', 'desc'],
            ];
        }

        return $sources;
    }

    /**
     * @inheritdoc
     *
     * Without this the index offers no bulk actions at all: canDelete() decides whether a
     * delete is permitted, but the action still has to be registered to appear.
     */
    protected static function defineActions(string $source): array
    {
        // Config arrays and class strings, not instantiated actions: the element index
        // filters this list by re-creating each entry, and an already-built instance falls
        // through that filter, which silently drops Restore from the trash view.
        return [
            [
                'type' => Delete::class,
                'confirmationMessage' => Craft::t('capture', 'Are you sure you want to delete the selected submissions?'),
                'successMessage' => Craft::t('capture', 'Submissions deleted.'),
            ],
            Restore::class,
        ];
    }

    /**
     * @inheritdoc
     *
     * The plugin's own exporter goes first so it is the default choice: Craft's raw exporter
     * writes the values column as a single cell of JSON, which is no use in a spreadsheet.
     */
    protected static function defineExporters(string $source): array
    {
        $exporters = parent::defineExporters($source);
        array_unshift($exporters, SubmissionExport::class);

        return $exporters;
    }

    /**
     * @inheritdoc
     */
    protected static function defineTableAttributes(): array
    {
        // No column for the submission's own label: Craft always renders that as the first
        // column, so adding one here just repeats it.
        return [
            'id' => ['label' => Craft::t('capture', 'ID')],
            'form' => ['label' => Craft::t('capture', 'Form')],
            'site' => ['label' => Craft::t('capture', 'Site')],
            'ipAddress' => ['label' => Craft::t('capture', 'IP address')],
            'dateCreated' => ['label' => Craft::t('capture', 'Date')],
        ];
    }

    /**
     * @inheritdoc
     */
    protected static function defineDefaultTableAttributes(string $source): array
    {
        return ['form', 'dateCreated'];
    }

    /**
     * @inheritdoc
     */
    protected static function defineSortOptions(): array
    {
        return [
            'dateCreated' => Craft::t('capture', 'Date submitted'),
            'id' => Craft::t('capture', 'ID'),
        ];
    }

    /**
     * @inheritdoc
     */
    protected static function defineSearchableAttributes(): array
    {
        return ['values'];
    }

    /**
     * @inheritdoc
     */
    protected function attributeHtml(string $attribute): string
    {
        return match ($attribute) {
            'form' => Html::encode($this->getForm()->name ?? ''),
            'site' => Html::encode($this->getSite()->name),
            'ipAddress' => Html::encode($this->ipAddress ?? ''),
            default => parent::attributeHtml($attribute),
        };
    }

    /**
     * Returns the submission as label/value pairs, in the form's field order.
     *
     * Fields deleted from the form since submission still have stored values, so those are
     * appended under their raw handle rather than silently dropped.
     *
     * @return array<int, array{label: string, value: string}>
     */
    public function getDisplayValues(): array
    {
        $form = $this->getForm();
        $rows = [];
        $seen = [];

        foreach ($form?->getFields() ?? [] as $field) {
            $seen[$field->handle] = true;
            $rows[] = [
                'label' => $field->label,
                'value' => $field->formatValue($this->_values[$field->handle] ?? null),
            ];
        }

        foreach ($this->_values as $handle => $value) {
            if (!isset($seen[$handle])) {
                $rows[] = ['label' => $handle, 'value' => $this->_stringify($value)];
            }
        }

        return $rows;
    }

    private function _stringify(mixed $value): string
    {
        if (is_array($value)) {
            return implode(', ', $value);
        }

        return (string)$value;
    }

    /**
     * @inheritdoc
     *
     * Without this the element index renders the label as plain text: Craft only links a
     * row through to its edit URL for elements the user is allowed to view.
     */
    public function canView(User $user): bool
    {
        return $user->can(SubmissionsController::PERMISSION_VIEW_SUBMISSIONS);
    }

    /**
     * @inheritdoc
     *
     * Submissions are a record of what someone sent, and the plugin offers no screen that
     * edits one: the detail screen is read-only and there is no field layout to edit.
     *
     * This still has to return true, because Craft's Restore action checks canSave() before
     * it will bring an element back from the trash. Returning false here reads as "immutable"
     * but actually means "deletions are permanent", which is the worse failure for a form
     * that collects enquiries.
     */
    public function canSave(User $user): bool
    {
        return $user->can(SubmissionsController::PERMISSION_VIEW_SUBMISSIONS);
    }

    /**
     * @inheritdoc
     */
    public function canDelete(User $user): bool
    {
        return $user->can(SubmissionsController::PERMISSION_VIEW_SUBMISSIONS);
    }

    /**
     * @inheritdoc
     */
    public function canDuplicate(User $user): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function getCpEditUrl(): ?string
    {
        return UrlHelper::cpUrl("capture/submissions/$this->id");
    }

    /**
     * @inheritdoc
     */
    public function getSearchKeywords(string $attribute): string
    {
        // Values live in a JSON column, so they are invisible to search unless flattened
        // into keywords here.
        if ($attribute === 'values') {
            return implode(' ', array_map(
                static fn($value) => is_array($value) ? implode(' ', $value) : (string)$value,
                $this->_values,
            ));
        }

        return parent::getSearchKeywords($attribute);
    }

    /**
     * @inheritdoc
     */
    public function afterSave(bool $isNew): void
    {
        if (!$this->propagating) {
            Plugin::getInstance()->submissions->saveSubmissionRecord($this, $isNew);

            // Notifications fire on insert only: re-saving a submission, or restoring one
            // from the trash, must never re-send the emails.
            //
            // Queued rather than sent here, so the visitor's request does not wait on the
            // mail host. The submission is committed either way.
            if ($isNew) {
                Craft::$app->getQueue()->push(new SendNotifications([
                    'submissionId' => $this->id,
                ]));
            }
        }

        parent::afterSave($isNew);
    }
}
