<?php

namespace moca\capture\services;

use moca\capture\elements\Submission;
use moca\capture\models\Form;
use yii\base\Component;

/**
 * Resolves `{{ fieldHandle }}` references against a submission.
 *
 * Deliberately not Twig. The strings this resolves come from form settings, but the values
 * substituted into them come from whoever filled the form in, and handing visitor-influenced
 * data to a template engine is how server-side template injection happens. This understands
 * exactly one thing, a field handle in double braces, and nothing else.
 *
 * `{{ values.email }}` is accepted as well as `{{ email }}`, because that is what people
 * reach for after reading Craft's own docs.
 */
class ValueTemplate extends Component
{
    /**
     * Field types whose value may decide where an email is sent.
     *
     * Fixed-option fields can only ever yield an address the site owner put in the options.
     * Email fields are free text, but sending to an address the visitor supplied is already
     * what the autoresponder does, so allowing it here is consistent rather than new risk.
     */
    public const RECIPIENT_TYPES = ['select', 'radio', 'checkboxes', 'email'];

    /**
     * Whether a string contains anything this resolver would act on.
     */
    public function isTemplated(?string $value): bool
    {
        return $value !== null && preg_match('/\{\{.+?\}\}/', $value) === 1;
    }

    /**
     * Resolves every reference in a string.
     *
     * @param string[]|null $allowedTypes Field types that may be referenced, or null for any.
     * @param string[] $errors Filled with the reason when a reference could not be resolved.
     */
    public function resolve(
        string $template,
        Submission $submission,
        ?array $allowedTypes = null,
        array &$errors = [],
    ): string {
        $form = $submission->getForm();

        if ($form === null) {
            $errors[] = 'The submission has no form.';

            return '';
        }

        $fields = $this->_fieldsByHandle($form);
        $values = $submission->getValues();

        return preg_replace_callback(
            '/\{\{\s*(?:values\.)?([a-zA-Z][a-zA-Z0-9_]*)\s*\}\}/',
            function(array $match) use ($fields, $values, $allowedTypes, &$errors): string {
                $handle = $match[1];

                if (!isset($fields[$handle])) {
                    $errors[] = "The form has no “{$handle}” field.";

                    return '';
                }

                $type = $fields[$handle]->type;

                if ($allowedTypes !== null && !in_array($type, $allowedTypes, true)) {
                    $errors[] = "“{$handle}” is a {$type} field, which is not allowed here.";

                    return '';
                }

                $value = $values[$handle] ?? null;

                if (is_array($value)) {
                    return implode(',', $value);
                }

                if ($value === null || $value === '') {
                    $errors[] = "“{$handle}” was left empty.";

                    return '';
                }

                return (string)$value;
            },
            $template,
        );
    }

    /**
     * @return array<string, \moca\capture\models\FormField>
     */
    private function _fieldsByHandle(Form $form): array
    {
        $byHandle = [];

        foreach ($form->getFields() as $field) {
            $byHandle[$field->handle] = $field;
        }

        return $byHandle;
    }
}
