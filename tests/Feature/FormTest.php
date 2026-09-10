<?php

use moca\postoffice\models\Form;
use moca\postoffice\models\FormField;
use moca\postoffice\models\Notification;
use moca\postoffice\Plugin;

function makeForm(string $handle = 'testForm', array $fields = []): Form
{
    $form = new Form([
        'name' => 'Test Form',
        'handle' => $handle,
        'successBehavior' => Form::SUCCESS_AJAX,
    ]);

    $form->setFields($fields ?: [
        new FormField(['type' => 'text', 'handle' => 'fullName', 'label' => 'Full name', 'required' => true]),
        new FormField(['type' => 'email', 'handle' => 'email', 'label' => 'Email']),
    ]);

    return $form;
}

it('round-trips a form through project config into the database', function() {
    $forms = Plugin::getInstance()->forms;

    expect($forms->saveForm(makeForm('roundTrip')))->toBeTrue();

    // Read back through a fresh service, so this asserts the project config handler wrote
    // the rows rather than that the model held on to them.
    $fresh = (new \moca\postoffice\services\Forms())->getFormByHandle('roundTrip');

    expect($fresh)->not->toBeNull()
        ->and($fresh->name)->toBe('Test Form')
        ->and($fresh->getFields())->toHaveCount(2)
        ->and($fresh->getFields()[0]->handle)->toBe('fullName')
        ->and($fresh->getFields()[0]->required)->toBeTrue();
});

it('creates exactly one autoresponder, disabled, on first save', function() {
    $forms = Plugin::getInstance()->forms;
    $forms->saveForm(makeForm('autoOnce'));

    $fresh = (new \moca\postoffice\services\Forms())->getFormByHandle('autoOnce');
    $autoresponders = array_filter($fresh->getNotifications(), fn($n) => $n->getIsAutoresponder());

    expect($autoresponders)->toHaveCount(1)
        ->and(reset($autoresponders)->enabled)->toBeFalse();
});

it('does not duplicate rows when a form is saved twice', function() {
    $forms = Plugin::getInstance()->forms;
    $forms->saveForm(makeForm('savedTwice'));

    $again = (new \moca\postoffice\services\Forms())->getFormByHandle('savedTwice');
    $again->name = 'Renamed';
    Plugin::getInstance()->forms->saveForm($again);

    $fresh = (new \moca\postoffice\services\Forms())->getFormByHandle('savedTwice');

    expect($fresh->name)->toBe('Renamed')
        ->and($fresh->getFields())->toHaveCount(2)
        ->and($fresh->getNotifications())->toHaveCount(1);
});

it('rejects two fields sharing a handle instead of hitting the unique index', function() {
    // Previously this reached the (formId, handle) index and threw an integrity error.
    $form = makeForm('dupeHandles', [
        new FormField(['type' => 'text', 'handle' => 'same', 'label' => 'One']),
        new FormField(['type' => 'text', 'handle' => 'same', 'label' => 'Two']),
    ]);

    expect(Plugin::getInstance()->forms->saveForm($form))->toBeFalse()
        ->and($form->getErrors('fields'))->not->toBeEmpty()
        ->and(implode(' ', $form->getErrors('fields')))->toContain('same');
});

it('requires a redirect url only when redirecting', function() {
    $redirecting = makeForm('needsUrl');
    $redirecting->successBehavior = Form::SUCCESS_REDIRECT;
    $redirecting->redirectUrl = null;

    expect($redirecting->validate())->toBeFalse()
        ->and($redirecting->getErrors('redirectUrl'))->not->toBeEmpty();

    $ajax = makeForm('needsNoUrl');
    $ajax->successBehavior = Form::SUCCESS_AJAX;

    expect($ajax->validate())->toBeTrue();
});

it('finds the first email field, which is what the autoresponder replies to', function() {
    $form = makeForm('emailField');

    expect($form->getEmailField()?->handle)->toBe('email');

    $noEmail = makeForm('noEmailField', [
        new FormField(['type' => 'text', 'handle' => 'name', 'label' => 'Name']),
    ]);

    expect($noEmail->getEmailField())->toBeNull();
});
