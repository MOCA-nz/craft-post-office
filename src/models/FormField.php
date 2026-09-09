<?php

namespace moca\capture\models;

use Craft;
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
     * Renders a stored value for a human to read.
     *
     * Values are stored raw, which is what a template or an export wants. This is the other
     * side of that: an option's label rather than its stored value, and Yes/No rather than 1,
     * for the CP detail screen and notification emails.
     */
    public function formatValue(mixed $value): string
    {
        if ($this->getFieldType() === FieldType::Consent) {
            return $value
                ? Craft::t('capture', 'Yes')
                : Craft::t('capture', 'No');
        }

        if (!$this->hasOptions()) {
            return is_array($value) ? implode(', ', $value) : (string)$value;
        }

        $labels = [];

        foreach ($this->getOptions() as $option) {
            $labels[(string)($option['value'] ?? '')] = (string)($option['label'] ?? '');
        }

        $selected = is_array($value) ? $value : ($value === null || $value === '' ? [] : [$value]);

        // An option removed from the form since submission has no label left, so fall back
        // to the stored value rather than showing a blank.
        return implode(', ', array_map(
            static fn($v) => $labels[(string)$v] ?? (string)$v,
            $selected,
        ));
    }

    /**
     * The message to show when this field is required but was left empty.
     */
    public function getRequiredMessage(): string
    {
        return $this->errorMessage ?: Craft::t('capture', '{label} is required.', ['label' => $this->label]);
    }

    /**
     * The message to show when this field has a value that its type rejects.
     *
     * The builder only lets an editor set one message per field, so a custom message covers
     * both this and the required case. Only the defaults differ, because "must be a valid
     * email address" is the wrong thing to say about a field that is simply blank.
     */
    public function getTypeErrorMessage(): string
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
