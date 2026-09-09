<?php

namespace moca\capture\models;

use craft\base\Model;
use craft\validators\HandleValidator;
use moca\capture\fields\FieldType;

/**
 * One field on a form.
 *
 * These are the plugin's own field definitions, not Craft field types: each carries the
 * per-field email and validation settings the builder exposes.
 */
class FormField extends Model
{
    public ?int $id = null;
    public ?int $formId = null;
    public string $type = 'text';
    public string $handle = '';
    public string $label = '';
    public ?string $placeholder = null;
    public bool $required = false;
    public ?string $errorMessage = null;
    public bool $includeInEmail = true;
    public ?int $sortOrder = null;
    public ?string $uid = null;

    /**
     * @var array List of ['label' => string, 'value' => string]. Only used by select,
     *            radio and checkboxes.
     */
    private array $_options = [];

    public function getOptions(): array
    {
        return $this->_options;
    }

    public function setOptions(array|string|null $options): void
    {
        if (is_string($options)) {
            $options = json_decode($options, true) ?: [];
        }

        $this->_options = $options ?? [];
    }

    /**
     * The field's type as an enum case.
     */
    public function getFieldType(): FieldType
    {
        return FieldType::tryFrom($this->type) ?? FieldType::Text;
    }

    /**
     * Whether this field type carries a list of options.
     */
    public function hasOptions(): bool
    {
        return $this->getFieldType()->hasOptions();
    }

    /**
     * The message to show when this field fails validation.
     *
     * The builder only lets an editor set one message per field, so it covers both the
     * required check and the type's implied check.
     */
    public function getErrorMessage(): string
    {
        return $this->errorMessage ?: $this->getFieldType()->defaultErrorMessage($this->label);
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        return array_merge(parent::defineRules(), [
            [['type', 'handle', 'label'], 'required'],
            [['type'], 'in', 'range' => array_column(FieldType::cases(), 'value')],
            [['handle'], HandleValidator::class],
            [['label', 'placeholder', 'errorMessage'], 'string', 'max' => 255],
        ]);
    }
}
