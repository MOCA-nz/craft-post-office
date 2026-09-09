<?php

namespace moca\capture\records;

use craft\db\ActiveRecord;
use moca\capture\migrations\Install;

/**
 * @property int $id
 * @property int $formId
 * @property string $kind
 * @property string|null $recipientEmail
 * @property string|null $subject
 * @property string|null $templatePath
 * @property bool $enabled
 * @property int|null $sortOrder
 * @property string $uid
 */
class Notification extends ActiveRecord
{
    public static function tableName(): string
    {
        return Install::TABLE_NOTIFICATIONS;
    }
}
