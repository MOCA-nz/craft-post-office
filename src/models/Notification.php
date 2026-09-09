<?php

namespace moca\capture\models;

use craft\base\Model;

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
    public ?string $templatePath = null;
    public bool $enabled = true;
    public ?int $sortOrder = null;
    public ?string $uid = null;

    public function getIsAutoresponder(): bool
    {
        return $this->kind === self::KIND_AUTORESPONDER;
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        return array_merge(parent::defineRules(), [
            [['kind'], 'in', 'range' => [self::KIND_RECIPIENT, self::KIND_AUTORESPONDER]],
            [['templatePath'], 'string', 'max' => 255],
            [['recipientEmail'], 'email'],
            [
                ['recipientEmail'],
                'required',
                'when' => fn(self $model) => $model->kind === self::KIND_RECIPIENT,
            ],
        ]);
    }
}
