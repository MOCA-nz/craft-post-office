<?php

namespace moca\capture\records;

use craft\db\ActiveRecord;
use moca\capture\migrations\Install;

/**
 * @property int $id
 * @property string $name
 * @property string $handle
 * @property string|null $fromName
 * @property string|null $fromEmail
 * @property string|null $replyToEmail
 * @property string $successBehavior
 * @property string|null $redirectUrl
 * @property string|null $successMessage
 * @property bool $honeypotEnabled
 * @property bool $recaptchaEnabled
 * @property bool $turnstileEnabled
 * @property int|null $sortOrder
 * @property string $uid
 */
class Form extends ActiveRecord
{
    public static function tableName(): string
    {
        return Install::TABLE_FORMS;
    }
}
