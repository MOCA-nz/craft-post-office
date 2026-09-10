<?php

use craft\db\Query;
use craft\db\Table;
use moca\postoffice\elements\Submission;
use moca\postoffice\models\Form;
use moca\postoffice\models\FormField;
use moca\postoffice\Plugin;

function submissionForm(string $handle): Form
{
    $form = new Form([
        'name' => 'Submission Form',
        'handle' => $handle,
        'successBehavior' => Form::SUCCESS_AJAX,
    ]);

    $form->setFields([
        new FormField(['type' => 'text', 'handle' => 'fullName', 'label' => 'Full name', 'required' => true]),
        new FormField(['type' => 'email', 'handle' => 'email', 'label' => 'Email', 'required' => true]),
        new FormField(['type' => 'url', 'handle' => 'website', 'label' => 'Website']),
    ]);

    Plugin::getInstance()->forms->saveForm($form);

    // Read back through the plugin's own service instance: that is the one
    // Submission::getForm() consults, and it memoizes.
    return Plugin::getInstance()->forms->getFormByHandle($handle);
}

function submissionFor(Form $form, array $values): Submission
{
    $submission = new Submission();
    $submission->formId = $form->id;
    $submission->setValues($values);

    return $submission;
}

it('reports required fields under their own handle', function() {
    $form = submissionForm('reqCheck');
    $submission = submissionFor($form, []);

    expect($submission->validate())->toBeFalse()
        ->and($submission->getFieldErrors())->toHaveKeys(['fullName', 'email'])
        ->and($submission->getFieldErrors()['fullName'][0])->toBe('Full name is required.')
        ->and($submission->getFieldErrors()['email'][0])->toBe('Email is required.');
});

it('applies the type validator only to fields that have a value', function() {
    $form = submissionForm('typeCheck');

    // website is optional and empty: the url validator must not fire.
    $ok = submissionFor($form, ['fullName' => 'Jane', 'email' => 'jane@example.test', 'website' => '']);
    expect($ok->validate())->toBeTrue();

    $bad = submissionFor($form, ['fullName' => 'Jane', 'email' => 'jane@example.test', 'website' => 'not-a-url']);
    expect($bad->validate())->toBeFalse()
        ->and($bad->getFieldErrors())->toHaveKey('website')
        ->and($bad->getFieldErrors()['website'][0])->toBe('Website must be a valid URL.');
});

it('stores values and reads them back keyed by handle', function() {
    $form = submissionForm('storeCheck');
    $submission = submissionFor($form, ['fullName' => 'Jane', 'email' => 'jane@example.test']);

    expect(Craft::$app->getElements()->saveElement($submission))->toBeTrue();

    $fresh = Submission::find()->id($submission->id)->one();

    expect($fresh->getValues()['fullName'])->toBe('Jane')
        ->and($fresh->getForm()->handle)->toBe('storeCheck');
});

it('keeps values for fields removed from the form since submission', function() {
    $form = submissionForm('orphanValues');
    $submission = submissionFor($form, [
        'fullName' => 'Jane',
        'email' => 'jane@example.test',
        'goneAway' => 'still here',
    ]);
    Craft::$app->getElements()->saveElement($submission);

    $labels = array_column($submission->getDisplayValues(), 'label');
    $values = array_column($submission->getDisplayValues(), 'value');

    expect($labels)->toContain('goneAway')
        ->and($values)->toContain('still here');
});

it('leaves no orphaned element rows when a form is deleted', function() {
    // The foreign key runs elements -> submissions, so deleting the plugin's own rows used
    // to strand one elements row per submission forever.
    $form = submissionForm('cascadeCheck');

    foreach (['One', 'Two'] as $name) {
        $submission = submissionFor($form, ['fullName' => $name, 'email' => 'a@example.test']);
        Craft::$app->getElements()->saveElement($submission);
    }

    $countElements = fn() => (new Query())
        ->from([Table::ELEMENTS])
        ->where(['type' => Submission::class])
        ->count();

    $before = (int)$countElements();
    expect($before)->toBeGreaterThanOrEqual(2);

    Plugin::getInstance()->forms->deleteForm($form);

    expect((new Query())->from(['{{%postoffice_submissions}}'])->where(['formId' => $form->id])->count())->toEqual(0)
        ->and((int)$countElements())->toBe((int)$before - 2);
});
