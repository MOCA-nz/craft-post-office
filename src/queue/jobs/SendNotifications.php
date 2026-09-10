<?php

namespace moca\postoffice\queue\jobs;

use Craft;
use craft\queue\BaseJob;
use moca\postoffice\elements\Submission;
use moca\postoffice\Plugin;

/**
 * Sends a submission's notifications.
 *
 * Queued rather than sent inline, so a slow or unreachable mail host delays nothing the
 * visitor is waiting on. The submission is already saved by the time this is pushed, so a
 * failure here costs an email, never the enquiry itself.
 */
class SendNotifications extends BaseJob
{
    /**
     * @var int The submission to send notifications for.
     */
    public int $submissionId;

    /**
     * @inheritdoc
     */
    public function execute($queue): void
    {
        $submission = Submission::find()
            ->id($this->submissionId)
            ->status(null)
            ->one();

        // Deleted between being queued and being run. Nothing to do, and not an error.
        if (!$submission instanceof Submission) {
            return;
        }

        Plugin::getInstance()->notifications->sendForSubmission($submission);
    }

    /**
     * @inheritdoc
     */
    protected function defaultDescription(): ?string
    {
        return Craft::t('post-office', 'Sending Post Office notifications');
    }
}
