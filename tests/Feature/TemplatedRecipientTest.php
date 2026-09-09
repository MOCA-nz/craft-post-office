<?php

use moca\capture\elements\Submission;
use moca\capture\models\Form;
use moca\capture\models\FormField;
use moca\capture\Plugin;
use moca\capture\services\ValueTemplate;

function agentForm(string $handle): Form
{
    $form = new Form(['name' => 'Agent Form', 'handle' => $handle, 'successBehavior' => Form::SUCCESS_AJAX]);

    $agent = new FormField(['type' => 'select', 'handle' => 'agent', 'label' => 'Agent']);
    $agent->setOptions([
        ['label' => 'Jane', 'value' => 'jane@example.test'],
        ['label' => 'Bob', 'value' => 'bob@example.test'],
    ]);

    $form->setFields([
        new FormField(['type' => 'text', 'handle' => 'fullName', 'label' => 'Full name']),
        new FormField(['type' => 'email', 'handle' => 'email', 'label' => 'Email']),
        $agent,
    ]);

    Plugin::getInstance()->forms->saveForm($form);

    return Plugin::getInstance()->forms->getFormByHandle($handle);
}

function submissionWith(Form $form, array $values): Submission
{
    $s = new Submission();
    $s->formId = $form->id;
    $s->setValues($values);

    return $s;
}

it('resolves a field reference from the submission', function() {
    $form = agentForm('agentResolve');
    $submission = submissionWith($form, ['agent' => 'jane@example.test']);
    $errors = [];

    $resolved = Plugin::getInstance()->valueTemplate
        ->resolve('{{ agent }}', $submission, ValueTemplate::RECIPIENT_TYPES, $errors);

    expect($resolved)->toBe('jane@example.test')->and($errors)->toBeEmpty();
});

it('accepts the values. prefix people reach for', function() {
    $form = agentForm('agentPrefix');
    $submission = submissionWith($form, ['agent' => 'bob@example.test']);
    $errors = [];

    expect(Plugin::getInstance()->valueTemplate->resolve('{{ values.agent }}', $submission, null, $errors))
        ->toBe('bob@example.test');
});

it('refuses a field type that may not decide a recipient', function() {
    // fullName is free text: allowing it would let a visitor type any address and have the
    // site mail it.
    $form = agentForm('agentRefuse');
    $submission = submissionWith($form, ['fullName' => 'attacker@example.test']);
    $errors = [];

    $resolved = Plugin::getInstance()->valueTemplate
        ->resolve('{{ fullName }}', $submission, ValueTemplate::RECIPIENT_TYPES, $errors);

    expect($resolved)->toBe('')
        ->and(implode(' ', $errors))->toContain('not allowed here');
});

it('allows any field type when nothing is restricted, as subjects do', function() {
    $form = agentForm('subjectAny');
    $submission = submissionWith($form, ['fullName' => 'Jane Smith']);
    $errors = [];

    expect(Plugin::getInstance()->valueTemplate->resolve('Enquiry from {{ fullName }}', $submission, null, $errors))
        ->toBe('Enquiry from Jane Smith')
        ->and($errors)->toBeEmpty();
});

it('reports an unknown handle rather than resolving to nothing silently', function() {
    $form = agentForm('agentUnknown');
    $submission = submissionWith($form, []);
    $errors = [];

    Plugin::getInstance()->valueTemplate->resolve('{{ nope }}', $submission, null, $errors);

    expect(implode(' ', $errors))->toContain('has no');
});

it('reports an empty value', function() {
    $form = agentForm('agentEmpty');
    $submission = submissionWith($form, ['agent' => '']);
    $errors = [];

    Plugin::getInstance()->valueTemplate->resolve('{{ agent }}', $submission, null, $errors);

    expect(implode(' ', $errors))->toContain('left empty');
});

it('does not execute twig', function() {
    // The strings come from settings but the values come from visitors. Anything that looks
    // like Twig beyond a bare handle must be left alone, not evaluated.
    $form = agentForm('noTwig');
    $submission = submissionWith($form, ['fullName' => 'Jane']);
    $errors = [];

    $resolved = Plugin::getInstance()->valueTemplate
        ->resolve('{{ 7 * 7 }}', $submission, null, $errors);

    expect($resolved)->toBe('{{ 7 * 7 }}');
});
