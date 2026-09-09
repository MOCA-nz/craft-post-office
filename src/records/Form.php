<?php

namespace moca\capture\records;

use craft\db\ActiveRecord;
use moca\capture\migrations\Install;

/**
 * @property int $id
 * @property string $name
 * @property string $handle
 */
class Form extends ActiveRecord
{
    public static function tableName(): string
    {
        return Install::TABLE_FORMS;
    }
}
