<?php

namespace moca\capture\elements\exporters;

use Craft;
use craft\base\ElementExporter;
use craft\elements\db\ElementQueryInterface;
use moca\capture\elements\Submission;
use moca\capture\models\Form;

/**
 * Exports submissions with a column per form field.
 *
 * Craft's built-in "Raw data" exporter emits the values column verbatim, so a spreadsheet
 * receives one cell of JSON. This expands the fields into real columns, which is the only
 * shape that is any use in a spreadsheet.
 *
 * Every form in the result set contributes its columns, so exporting "All submissions"
 * across several forms produces the union, with blanks where a form has no such field.
 */
class SubmissionExport extends ElementExporter
{
    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('capture', 'Submissions');
    }

    /**
     * @inheritdoc
     */
    public function export(ElementQueryInterface $query): mixed
    {
        /** @var Submission[] $submissions */
        $submissions = $query->all();

        // Build the column set first, so every row has the same keys in the same order even
        // when the result spans forms with different fields.
        $forms = [];
        $columns = [];

        foreach ($submissions as $submission) {
            $form = $submission->getForm();

            if ($form === null || isset($forms[$form->id])) {
                continue;
            }

            $forms[$form->id] = $form;

            foreach ($form->getFields() as $field) {
                $columns[$field->handle] = $field->label;
            }
        }

        $rows = [];

        foreach ($submissions as $submission) {
            $row = [
                Craft::t('capture', 'ID') => $submission->id,
                Craft::t('capture', 'Form') => $submission->getForm()->name ?? '',
                Craft::t('capture', 'Date Submitted') => $submission->dateCreated?->format('Y-m-d H:i:s'),
                Craft::t('capture', 'IP address') => $submission->ipAddress,
            ];

            $fields = $this->_fieldsByHandle($submission->getForm());
            $values = $submission->getValues();

            foreach ($columns as $handle => $label) {
                // A field the submission's own form does not have: blank, not missing, so
                // the column count stays constant.
                $row[$label] = isset($fields[$handle])
                    ? $fields[$handle]->formatValue($values[$handle] ?? null)
                    : '';
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @inheritdoc
     */
    public static function isFormattable(): bool
    {
        return true;
    }

    /**
     * @return array<string, \moca\capture\models\FormField>
     */
    private function _fieldsByHandle(?Form $form): array
    {
        if ($form === null) {
            return [];
        }

        $byHandle = [];

        foreach ($form->getFields() as $field) {
            $byHandle[$field->handle] = $field;
        }

        return $byHandle;
    }
}
