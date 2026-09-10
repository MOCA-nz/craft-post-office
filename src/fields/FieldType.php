<?php

namespace moca\postoffice\fields;

use Craft;

/**
 * The field types a form can be built from.
 *
 * These are the plugin's own types, not Craft field types. Each case carries everything the
 * three consumers need: the builder's type dropdown, front-end rendering, and validation.
 */
enum FieldType: string
{
    case Text = 'text';
    case Email = 'email';
    case Number = 'number';
    case Tel = 'tel';
    case Url = 'url';
    case Textarea = 'textarea';
    case Select = 'select';
    case Radio = 'radio';
    case Checkboxes = 'checkboxes';
    case Consent = 'consent';
    case Hidden = 'hidden';

    /**
     * The label shown in the builder's type dropdown.
     */
    public function label(): string
    {
        return match ($this) {
            self::Text => Craft::t('post-office', 'Text'),
            self::Email => Craft::t('post-office', 'Email'),
            self::Number => Craft::t('post-office', 'Number'),
            self::Tel => Craft::t('post-office', 'Phone'),
            self::Url => Craft::t('post-office', 'URL'),
            self::Textarea => Craft::t('post-office', 'Textarea'),
            self::Select => Craft::t('post-office', 'Dropdown'),
            self::Radio => Craft::t('post-office', 'Radio buttons'),
            self::Checkboxes => Craft::t('post-office', 'Checkboxes'),
            self::Consent => Craft::t('post-office', 'Consent'),
            self::Hidden => Craft::t('post-office', 'Hidden'),
        };
    }

    /**
     * Whether the type carries a list of options.
     */
    public function hasOptions(): bool
    {
        return match ($this) {
            self::Select, self::Radio, self::Checkboxes => true,
            default => false,
        };
    }

    /**
     * Whether the submitted value is a list rather than a scalar.
     */
    public function isMultiValue(): bool
    {
        return $this === self::Checkboxes;
    }

    /**
     * Whether the type renders as a `<textarea>` rather than an `<input>`.
     */
    public function isTextarea(): bool
    {
        return $this === self::Textarea;
    }

    /**
     * Whether a placeholder is meaningful for this type.
     */
    public function supportsPlaceholder(): bool
    {
        return match ($this) {
            self::Select, self::Radio, self::Checkboxes, self::Consent, self::Hidden => false,
            default => true,
        };
    }

    /**
     * The HTML input type, for the types that render as an `<input>`.
     */
    public function inputType(): string
    {
        return match ($this) {
            self::Email => 'email',
            self::Number => 'number',
            self::Tel => 'tel',
            self::Url => 'url',
            self::Hidden => 'hidden',
            self::Consent => 'checkbox',
            default => 'text',
        };
    }

    /**
     * The Yii validator this type implies, beyond the required check.
     *
     * The builder deliberately exposes only a required toggle: everything else here follows
     * from the type itself, so there is nothing extra to configure per field.
     */
    public function validator(): ?string
    {
        return match ($this) {
            self::Email => 'email',
            self::Url => 'url',
            self::Number => 'number',
            default => null,
        };
    }

    /**
     * The default message shown when this type's implied validator fails.
     */
    public function defaultErrorMessage(string $label): string
    {
        return match ($this) {
            self::Email => Craft::t('post-office', '{label} must be a valid email address.', ['label' => $label]),
            self::Url => Craft::t('post-office', '{label} must be a valid URL.', ['label' => $label]),
            self::Number => Craft::t('post-office', '{label} must be a number.', ['label' => $label]),
            default => Craft::t('post-office', '{label} is required.', ['label' => $label]),
        };
    }

    /**
     * Every type, as `{label, value}` pairs for a select field.
     */
    public static function options(): array
    {
        return array_map(
            static fn(self $case) => ['label' => $case->label(), 'value' => $case->value],
            self::cases(),
        );
    }
}
