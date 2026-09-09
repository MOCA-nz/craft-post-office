<?php

namespace moca\capture\records;

use craft\db\ActiveRecord;
use moca\capture\migrations\Install;

/**
 * @property int $id
 * @property string $level
 */
class Log extends ActiveRecord
{
    public static function tableName(): string
    {
        return Install::TABLE_LOGS;
    }
}
