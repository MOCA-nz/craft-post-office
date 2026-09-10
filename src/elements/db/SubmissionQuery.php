<?php

namespace moca\postoffice\elements\db;

use craft\elements\db\ElementQuery;
use craft\helpers\Db;

/**
 * Element query for submissions.
 */
class SubmissionQuery extends ElementQuery
{
    /**
     * @var mixed Narrows results to submissions made through a given form.
     */
    public mixed $formId = null;

    /**
     * Narrows results to submissions made through a given form.
     */
    public function formId(mixed $value): static
    {
        $this->formId = $value;

        return $this;
    }

    /**
     * @inheritdoc
     */
    protected function beforePrepare(): bool
    {
        $this->joinElementTable('postoffice_submissions');

        // addSelect() rather than select(): additive, so other extensions contributing
        // columns are not clobbered.
        $this->query->addSelect([
            'postoffice_submissions.formId',
            'postoffice_submissions.values',
            'postoffice_submissions.ipAddress',
            'postoffice_submissions.userAgent',
        ]);

        if ($this->formId !== null) {
            $this->subQuery->andWhere(Db::parseParam('postoffice_submissions.formId', $this->formId));
        }

        return parent::beforePrepare();
    }
}
