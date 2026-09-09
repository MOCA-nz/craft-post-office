<?php

namespace moca\capture\migrations;

use craft\db\Migration;

/**
 * Adds a per-notification subject line.
 *
 * Subjects were hardcoded in the Notifications service, so an editor who wanted a different
 * one had to override the whole email template, and even then could not change the subject.
 */
class m260909_120000_notification_subject extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        if (!$this->db->columnExists(Install::TABLE_NOTIFICATIONS, 'subject')) {
            $this->addColumn(
                Install::TABLE_NOTIFICATIONS,
                'subject',
                $this->string()->after('recipientEmail'),
            );
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        if ($this->db->columnExists(Install::TABLE_NOTIFICATIONS, 'subject')) {
            $this->dropColumn(Install::TABLE_NOTIFICATIONS, 'subject');
        }

        return true;
    }
}
