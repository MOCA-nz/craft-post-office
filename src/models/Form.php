<?php

namespace moca\postoffice\models;

use Craft;
use craft\base\Model;
use craft\behaviors\EnvAttributeParserBehavior;
use craft\validators\HandleValidator;
use craft\validators\UniqueValidator;
use moca\postoffice\records\Form as FormRecord;

/**
 * A form definition.
 *
 * Structure, not content: forms are mirrored into project config so a form built locally
 * deploys to production.
 */
class Form extends Model
{
    public const SUCCESS_REDIRECT = 'redirect';
    public const SUCCESS_AJAX = 'ajax';

    public ?int $id = null;
    public string $name = '';
    public string $handle = '';
    public ?string $fromName = null;
    public ?string $fromEmail = null;
    public ?string $replyToEmail = null;
    public string $successBehavior = self::SUCCESS_REDIRECT;
    public ?string $redirectUrl = null;
    public ?string $successMessage = null;
    public bool $honeypotEnabled = true;
    public bool $recaptchaEnabled = false;
    public bool $turnstileEnabled = false;
    public ?int $sortOrder = null;
    public ?string $uid = null;

    /**
     * @var FormField[]
     */
    private array $_fields = [];

    /**
     * @var Notification[]
     */
    private array $_notifications = [];

    /**
     * @inheritdoc
     */
    public function behaviors(): array
    {
        return [
            'parser' => [
                'class' => EnvAttributeParserBehavior::class,
                'attributes' => ['fromName', 'fromEmail', 'replyToEmail'],
            ],
        ];
    }

    /**
     * @return FormField[]
     */
    public function getFields(): array
    {
        return $this->_fields;
    }

    /**
     * @param FormField[] $fields
     */
    public function setFields(array $fields): void
    {
        $this->_fields = $fields;
    }

    /**
     * @return Notification[]
     */
    public function getNotifications(): array
    {
        return $this->_notifications;
    }

    /**
     * @param Notification[] $notifications
     */
    public function setNotifications(array $notifications): void
    {
        $this->_notifications = $notifications;
    }

    /**
     * Returns the first email-type field on the form.
     *
     * This is what the autoresponder notification sends to.
     */
    public function getEmailField(): ?FormField
    {
        foreach ($this->_fields as $field) {
            if ($field->type === 'email') {
                return $field;
            }
        }

        return null;
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        return array_merge(parent::defineRules(), [
            [['name', 'handle'], 'required'],
            [['name', 'handle'], 'string', 'max' => 255],
            [['handle'], HandleValidator::class],
            [['handle'], UniqueValidator::class, 'targetClass' => FormRecord::class],
            [['fromEmail', 'replyToEmail'], 'email'],
            [['successBehavior'], 'in', 'range' => [self::SUCCESS_REDIRECT, self::SUCCESS_AJAX]],
            [
                ['redirectUrl'],
                'required',
                'when' => fn(self $model) => $model->successBehavior === self::SUCCESS_REDIRECT,
            ],
        ]);
    }

    /**
     * @inheritdoc
     *
     * Field validation runs here rather than as a rule on `fields`. That property is backed
     * by a getter and setter, so it is not one of the model's attributes, and a rule naming
     * it is only run for some validation scenarios. Silently skipping it would put the
     * duplicate-handle check back on the database's unique index, which fails as a 500.
     */
    public function afterValidate(): void
    {
        $this->validateFields();

        parent::afterValidate();
    }

    /**
     * Validates the form's fields as a set.
     *
     * Uniqueness cannot be checked one field at a time: when a form is saved, the clashing
     * sibling is not in the database yet, so a per-record validator sees nothing wrong and
     * the save reaches the (formId, handle) unique index and dies with an integrity error
     * instead of a message an editor can act on.
     */
    public function validateFields(): void
    {
        $seen = [];

        foreach ($this->_fields as $i => $field) {
            $position = $i + 1;

            if (!$field->validate()) {
                foreach ($field->getFirstErrors() as $message) {
                    $this->addError('fields', Craft::t('post-office', 'Field {position}: {message}', [
                        'position' => $position,
                        'message' => $message,
                    ]));
                }

                continue;
            }

            if (isset($seen[$field->handle])) {
                $this->addError('fields', Craft::t('post-office', 'More than one field uses the handle “{handle}”. Handles must be unique within a form.', [
                    'handle' => $field->handle,
                ]));

                continue;
            }

            $seen[$field->handle] = true;
        }
    }
}
