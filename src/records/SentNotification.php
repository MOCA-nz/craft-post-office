<?php

namespace moca\capture\records;

use craft\db\ActiveRecord;
use moca\capture\migrations\Install;

/**
 * @property int $id
 * @property int $submissionId
 * @property int|null $notificationId
 * @property string $recipientEmail
 * @property string|null $subject
 * @property string $status
 * @property string|null $error
 * @property string $uid
 */
class SentNotification extends ActiveRecord
{
    public static function tableName(): string
    {
        return Install::TABLE_SENTNOTIFICATIONS;
    }
}
