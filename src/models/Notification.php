<?php

namespace moca\postoffice\models;

use Craft;
use craft\base\Model;
use moca\postoffice\Plugin;

/**
 * One outgoing email rule on a form.
 *
 * A 'recipient' row carries a fixed address. The single 'autoresponder' row per form has no
 * stored address: it resolves one at send time from the submission's email field. That row
 * is created automatically, cannot be deleted, and is only ever toggled on or off.
 */
class Notification extends Model
{
    public const KIND_RECIPIENT = 'recipient';
    public const KIND_AUTORESPONDER = 'autoresponder';

    public ?int $id = null;
    public ?int $formId = null;
    public string $kind = self::KIND_RECIPIENT;
    public ?string $recipientEmail = null;
    public ?string $subject = null;
    public ?string $templatePath = null;
    public bool $enabled = true;
    public ?int $sortOrder = null;
    public ?string $uid = null;

    public function getIsAutoresponder(): bool
    {
        return $this->kind === self::KIND_AUTORESPONDER;
    }

    /**
     * The subject line for this notification.
     *
     * Falls back to a sensible default rather than requiring one, so an existing form keeps
     * working and a new one does not need the field filled in to send.
     */
    public function getSubject(string $formName): string
    {
        if ($this->subject) {
            return $this->subject;
        }

        return $this->getDefaultSubject($formName);
    }

    /**
     * The subject used when none is set, or when a templated one resolves to nothing.
     */
    public function getDefaultSubject(string $formName): string
    {
        return $this->getIsAutoresponder()
            ? Craft::t('post-office', 'Thanks for getting in touch')
            : Craft::t('post-office', 'New {form} submission', ['form' => $formName]);
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        return array_merge(parent::defineRules(), [
            [['kind'], 'in', 'range' => [self::KIND_RECIPIENT, self::KIND_AUTORESPONDER]],
            [['templatePath', 'subject'], 'string', 'max' => 255],
            [
                ['recipientEmail'],
                'email',
                // A templated recipient resolves per submission, so it cannot be validated
                // as an address here. The resolved value is checked at send time instead.
                'when' => fn(self $model) => !Plugin::getInstance()->valueTemplate->isTemplated($model->recipientEmail),
            ],
            [
                ['recipientEmail'],
                'required',
                'when' => fn(self $model) => $model->kind === self::KIND_RECIPIENT,
            ],
        ]);
    }
}
