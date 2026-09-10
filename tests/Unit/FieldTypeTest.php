<?php

use moca\postoffice\fields\FieldType;
use moca\postoffice\models\FormField;

it('knows which types carry options', function() {
    expect(FieldType::Select->hasOptions())->toBeTrue()
        ->and(FieldType::Radio->hasOptions())->toBeTrue()
        ->and(FieldType::Checkboxes->hasOptions())->toBeTrue()
        ->and(FieldType::Text->hasOptions())->toBeFalse()
        ->and(FieldType::Consent->hasOptions())->toBeFalse();
});

it('marks only checkboxes as multi-value', function() {
    expect(FieldType::Checkboxes->isMultiValue())->toBeTrue()
        ->and(FieldType::Radio->isMultiValue())->toBeFalse()
        ->and(FieldType::Select->isMultiValue())->toBeFalse();
});

it('maps types to html input types', function() {
    expect(FieldType::Email->inputType())->toBe('email')
        ->and(FieldType::Number->inputType())->toBe('number')
        ->and(FieldType::Tel->inputType())->toBe('tel')
        ->and(FieldType::Url->inputType())->toBe('url')
        ->and(FieldType::Hidden->inputType())->toBe('hidden')
        ->and(FieldType::Consent->inputType())->toBe('checkbox')
        ->and(FieldType::Textarea->inputType())->toBe('text');
});

it('only gives a validator to the types that imply one', function() {
    expect(FieldType::Email->validator())->toBe('email')
        ->and(FieldType::Url->validator())->toBe('url')
        ->and(FieldType::Number->validator())->toBe('number')
        ->and(FieldType::Text->validator())->toBeNull()
        ->and(FieldType::Textarea->validator())->toBeNull();
});

it('falls back to text for an unknown stored type', function() {
    $field = new FormField(['type' => 'something-removed-in-a-later-version']);

    expect($field->getFieldType())->toBe(FieldType::Text);
});
