<?php

namespace moca\capture\records;

use craft\db\ActiveRecord;
use moca\capture\migrations\Install;

/**
 * @property int $id
 * @property int $formId
 */
class Submission extends ActiveRecord
{
    public static function tableName(): string
    {
        return Install::TABLE_SUBMISSIONS;
    }
}
