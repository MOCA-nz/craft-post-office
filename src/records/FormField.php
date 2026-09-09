<?php

namespace moca\capture\records;

use craft\db\ActiveRecord;
use moca\capture\migrations\Install;

/**
 * @property int $id
 * @property int $formId
 * @property string $type
 * @property string $handle
 * @property string $label
 * @property string|null $placeholder
 * @property bool $required
 * @property string|null $errorMessage
 * @property bool $includeInEmail
 * @property string|null $options
 * @property int|null $sortOrder
 * @property string $uid
 */
class FormField extends ActiveRecord
{
    public static function tableName(): string
    {
        return Install::TABLE_FORMFIELDS;
    }
}
