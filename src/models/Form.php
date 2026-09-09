<?php

namespace moca\capture\models;

use craft\base\Model;
use craft\behaviors\EnvAttributeParserBehavior;
use craft\validators\HandleValidator;
use craft\validators\UniqueValidator;
use moca\capture\records\Form as FormRecord;

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
}
