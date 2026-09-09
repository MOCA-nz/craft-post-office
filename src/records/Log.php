<?php

namespace moca\capture\records;

use craft\db\ActiveRecord;
use moca\capture\migrations\Install;

/**
 * @property int $id
 * @property int|null $formId
 * @property int|null $submissionId
 * @property string $level
 * @property string $event
 * @property string $message
 * @property string|null $context
 * @property string $uid
 */
class Log extends ActiveRecord
{
    public static function tableName(): string
    {
        return Install::TABLE_LOGS;
    }
}
