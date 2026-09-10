<?php

namespace moca\postoffice\records;

use craft\db\ActiveRecord;
use moca\postoffice\migrations\Install;

/**
 * @property int $id
 * @property int $formId
 * @property string|null $values
 * @property string|null $ipAddress
 * @property string|null $userAgent
 * @property string $uid
 */
class Submission extends ActiveRecord
{
    public static function tableName(): string
    {
        return Install::TABLE_SUBMISSIONS;
    }
}
