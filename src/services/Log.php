<?php

namespace moca\capture\services;

use craft\db\Query;
use craft\helpers\Db;
use DateTime;
use moca\capture\migrations\Install;
use yii\base\Component;

/**
 * Capture's own event log.
 *
 * Deliberately not a view onto Craft's log files: this records only what the plugin does,
 * so the Logs screen stays small and every row is actionable.
 */
class Log extends Component
{
    public const LEVEL_INFO = 'info';
    public const LEVEL_WARNING = 'warning';
    public const LEVEL_ERROR = 'error';

    /**
     * Records an event.
     *
     * @param string $event Short machine-readable event name, e.g. 'notification.failed'.
     */
    public function write(
        string $level,
        string $event,
        string $message,
        ?int $formId = null,
        ?int $submissionId = null,
        array $context = [],
    ): void {
        Db::insert(Install::TABLE_LOGS, [
            'formId' => $formId,
            'submissionId' => $submissionId,
            'level' => $level,
            'event' => $event,
            'message' => $message,
            'context' => $context !== [] ? json_encode($context) : null,
        ]);
    }

    /**
     * Returns a query over the log, newest first.
     */
    public function getQuery(): Query
    {
        return (new Query())
            ->from([Install::TABLE_LOGS])
            ->orderBy(['dateCreated' => SORT_DESC]);
    }

    /**
     * Deletes entries older than the given number of days.
     *
     * @return int How many rows were removed.
     */
    public function prune(int $days): int
    {
        if ($days <= 0) {
            return 0;
        }

        return Db::delete(Install::TABLE_LOGS, [
            '<', 'dateCreated', Db::prepareDateForDb(new DateTime("-$days days")),
        ]);
    }

    /**
     * Empties the log.
     */
    public function clear(): void
    {
        Db::delete(Install::TABLE_LOGS);
    }
}
