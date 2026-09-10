<?php

use moca\postoffice\models\FormField;

/**
 * Pins the "submissions showed raw values" bug: the index and emails rendered `sm, lg` and
 * `1` where `Small, Large` and `Yes` were meant.
 */

function optionField(string $type): FormField
{
    $field = new FormField(['type' => $type, 'handle' => 'size', 'label' => 'Size']);
    $field->setOptions([
        ['label' => 'Small', 'value' => 'sm'],
        ['label' => 'Large', 'value' => 'lg'],
    ]);

    return $field;
}

it('renders an option label rather than its stored value', function() {
    expect(optionField('select')->formatValue('lg'))->toBe('Large')
        ->and(optionField('radio')->formatValue('sm'))->toBe('Small');
});

it('renders multiple checkbox labels', function() {
    expect(optionField('checkboxes')->formatValue(['sm', 'lg']))->toBe('Small, Large');
});

it('falls back to the stored value when the option is gone', function() {
    // A form edited after the submission arrived: the option no longer exists, so there is
    // no label. Showing the raw value beats showing a blank.
    expect(optionField('select')->formatValue('xl'))->toBe('xl');
});

it('renders consent as yes or no', function() {
    $consent = new FormField(['type' => 'consent', 'handle' => 'agree', 'label' => 'I agree']);

    expect($consent->formatValue('1'))->toBe('Yes')
        ->and($consent->formatValue(null))->toBe('No')
        ->and($consent->formatValue(''))->toBe('No');
});

it('leaves plain values alone', function() {
    $text = new FormField(['type' => 'text', 'handle' => 'name', 'label' => 'Name']);

    expect($text->formatValue('Jane'))->toBe('Jane')
        ->and($text->formatValue(null))->toBe('');
});

it('uses a different default message for required than for the type check', function() {
    // An empty required email used to report "must be a valid email address".
    $email = new FormField(['type' => 'email', 'handle' => 'email', 'label' => 'Email']);

    expect($email->getRequiredMessage())->toBe('Email is required.')
        ->and($email->getTypeErrorMessage())->toBe('Email must be a valid email address.');
});

it('lets a custom message override both', function() {
    $email = new FormField([
        'type' => 'email',
        'handle' => 'email',
        'label' => 'Email',
        'errorMessage' => 'We need a way to reach you.',
    ]);

    expect($email->getRequiredMessage())->toBe('We need a way to reach you.')
        ->and($email->getTypeErrorMessage())->toBe('We need a way to reach you.');
});
