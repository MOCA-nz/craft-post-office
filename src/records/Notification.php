<?php

namespace moca\capture\records;

use craft\db\ActiveRecord;
use moca\capture\migrations\Install;

/**
 * @property int $id
 * @property int $formId
 */
class Notification extends ActiveRecord
{
    public static function tableName(): string
    {
        return Install::TABLE_NOTIFICATIONS;
    }
}
