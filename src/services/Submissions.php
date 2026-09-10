<?php

namespace moca\postoffice\services;

use craft\helpers\Db;
use moca\postoffice\elements\Submission;
use moca\postoffice\migrations\Install;
use yii\base\Component;

/**
 * Submission persistence.
 *
 * The element row is Craft's; this owns the plugin's side table.
 */
class Submissions extends Component
{
    /**
     * Writes the plugin's row for a submission element.
     *
     * Called from Submission::afterSave().
     */
    public function saveSubmissionRecord(Submission $submission, bool $isNew): void
    {
        $data = [
            'formId' => $submission->formId,
            'values' => json_encode($submission->getValues()),
            'ipAddress' => $submission->ipAddress,
            'userAgent' => $submission->userAgent,
        ];

        if ($isNew) {
            Db::insert(Install::TABLE_SUBMISSIONS, $data + ['id' => $submission->id]);

            return;
        }

        Db::update(Install::TABLE_SUBMISSIONS, $data, ['id' => $submission->id]);
    }
}
