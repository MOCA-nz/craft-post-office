<?php

namespace moca\capture\migrations;

use craft\db\Migration;
use craft\db\Query;
use craft\db\Table;
use craft\helpers\Db;

/**
 * Creates every table the plugin owns.
 *
 * Definition tables (forms, formfields, notifications) are mirrored into project config by
 * the Forms service. Content tables (submissions, sentnotifications, logs) are database only.
 */
class Install extends Migration
{
    public const TABLE_FORMS = '{{%capture_forms}}';
    public const TABLE_FORMFIELDS = '{{%capture_formfields}}';
    public const TABLE_NOTIFICATIONS = '{{%capture_notifications}}';
    public const TABLE_SUBMISSIONS = '{{%capture_submissions}}';
    public const TABLE_SENTNOTIFICATIONS = '{{%capture_sentnotifications}}';
    public const TABLE_LOGS = '{{%capture_logs}}';

    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Idempotent throughout. An install that fails part-way leaves tables behind, and
        // without these guards the retry dies on "table already exists" rather than
        // finishing the job. Re-running indexes unguarded also walks MySQL's 64-key ceiling.
        $this->createTables();
        $this->createIndexes();
        $this->addForeignKeys();

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        // The elements rows belong to Craft, not to this plugin, so dropping the plugin's
        // tables would strand one row per submission in `elements` forever. Delete them
        // first, while capture_submissions still exists to identify which they are.
        if ($this->db->tableExists(self::TABLE_SUBMISSIONS)) {
            $submissionIds = (new Query())
                ->select(['id'])
                ->from([self::TABLE_SUBMISSIONS])
                ->column($this->db);

            if ($submissionIds !== []) {
                // searchindex has no foreign key to elements, so it needs deleting by hand.
                $this->delete(Table::SEARCHINDEX, ['elementId' => $submissionIds]);
                $this->delete(Table::ELEMENTS, ['id' => $submissionIds]);
            }
        }

        // Dropped in reverse dependency order so foreign keys never block a drop.
        $this->dropTableIfExists(self::TABLE_LOGS);
        $this->dropTableIfExists(self::TABLE_SENTNOTIFICATIONS);
        $this->dropTableIfExists(self::TABLE_SUBMISSIONS);
        $this->dropTableIfExists(self::TABLE_NOTIFICATIONS);
        $this->dropTableIfExists(self::TABLE_FORMFIELDS);
        $this->dropTableIfExists(self::TABLE_FORMS);

        return true;
    }

    /**
     * Creates the plugin's tables.
     */
    protected function createTables(): void
    {
        $this->createTableIfMissing(self::TABLE_FORMS, [
            'id' => $this->primaryKey(),
            'name' => $this->string()->notNull(),
            'handle' => $this->string()->notNull(),
            'fromName' => $this->string(),
            'fromEmail' => $this->string(),
            'replyToEmail' => $this->string(),
            'successBehavior' => $this->string(20)->notNull()->defaultValue('redirect'),
            'redirectUrl' => $this->string(),
            'successMessage' => $this->text(),
            'honeypotEnabled' => $this->boolean()->notNull()->defaultValue(true),
            'recaptchaEnabled' => $this->boolean()->notNull()->defaultValue(false),
            'turnstileEnabled' => $this->boolean()->notNull()->defaultValue(false),
            'sortOrder' => $this->smallInteger()->unsigned(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTableIfMissing(self::TABLE_FORMFIELDS, [
            'id' => $this->primaryKey(),
            'formId' => $this->integer()->notNull(),
            'type' => $this->string(40)->notNull(),
            'handle' => $this->string()->notNull(),
            'label' => $this->string()->notNull(),
            'placeholder' => $this->string(),
            'required' => $this->boolean()->notNull()->defaultValue(false),
            'errorMessage' => $this->string(),
            'includeInEmail' => $this->boolean()->notNull()->defaultValue(true),
            // JSON list of {label, value} pairs. Only used by select, radio and checkboxes.
            'options' => $this->text(),
            'sortOrder' => $this->smallInteger()->unsigned(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTableIfMissing(self::TABLE_NOTIFICATIONS, [
            'id' => $this->primaryKey(),
            'formId' => $this->integer()->notNull(),
            // 'recipient' rows carry a fixed address. The single 'autoresponder' row per
            // form resolves its address at send time from the submission's email field.
            'kind' => $this->string(20)->notNull()->defaultValue('recipient'),
            'recipientEmail' => $this->string(),
            'subject' => $this->string(),
            'templatePath' => $this->string(),
            'enabled' => $this->boolean()->notNull()->defaultValue(true),
            'sortOrder' => $this->smallInteger()->unsigned(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTableIfMissing(self::TABLE_SUBMISSIONS, [
            'id' => $this->integer()->notNull(),
            'formId' => $this->integer()->notNull(),
            // Submitted values, keyed by field handle.
            'values' => $this->text(),
            'ipAddress' => $this->string(45),
            'userAgent' => $this->text(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
            'PRIMARY KEY([[id]])',
        ]);

        $this->createTableIfMissing(self::TABLE_SENTNOTIFICATIONS, [
            'id' => $this->primaryKey(),
            'submissionId' => $this->integer()->notNull(),
            'notificationId' => $this->integer(),
            'recipientEmail' => $this->string()->notNull(),
            'subject' => $this->string(),
            'status' => $this->string(20)->notNull(),
            'error' => $this->text(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTableIfMissing(self::TABLE_LOGS, [
            'id' => $this->primaryKey(),
            'formId' => $this->integer(),
            'submissionId' => $this->integer(),
            'level' => $this->string(20)->notNull(),
            'event' => $this->string(60)->notNull(),
            'message' => $this->text()->notNull(),
            'context' => $this->text(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);
    }

    /**
     * Creates a table only when it is not already there.
     */
    protected function createTableIfMissing(string $table, array $columns): void
    {
        if ($this->db->tableExists($table)) {
            return;
        }

        $this->createTable($table, $columns);
    }

    /**
     * Adds a foreign key only when one is not already there for those columns.
     */
    protected function addForeignKeyIfMissing(
        string $table,
        array $columns,
        string $refTable,
        array $refColumns,
        ?string $delete,
    ): void {
        if (Db::findForeignKey($table, $columns) !== null) {
            return;
        }

        $this->addForeignKey(null, $table, $columns, $refTable, $refColumns, $delete, null);
    }

    /**
     * Creates indexes.
     */
    protected function createIndexes(): void
    {
        $this->createIndexIfMissing(self::TABLE_FORMS, ['handle'], true);

        $this->createIndexIfMissing(self::TABLE_FORMFIELDS, ['formId'], false);
        $this->createIndexIfMissing(self::TABLE_FORMFIELDS, ['formId', 'handle'], true);

        $this->createIndexIfMissing(self::TABLE_NOTIFICATIONS, ['formId'], false);

        $this->createIndexIfMissing(self::TABLE_SUBMISSIONS, ['formId'], false);

        $this->createIndexIfMissing(self::TABLE_SENTNOTIFICATIONS, ['submissionId'], false);
        $this->createIndexIfMissing(self::TABLE_SENTNOTIFICATIONS, ['notificationId'], false);

        $this->createIndexIfMissing(self::TABLE_LOGS, ['formId'], false);
        $this->createIndexIfMissing(self::TABLE_LOGS, ['submissionId'], false);
        $this->createIndexIfMissing(self::TABLE_LOGS, ['dateCreated'], false);
    }

    /**
     * Adds foreign keys.
     */
    protected function addForeignKeys(): void
    {
        $this->addForeignKeyIfMissing(self::TABLE_FORMFIELDS, ['formId'], self::TABLE_FORMS, ['id'], 'CASCADE');
        $this->addForeignKeyIfMissing(self::TABLE_NOTIFICATIONS, ['formId'], self::TABLE_FORMS, ['id'], 'CASCADE');

        // A submission is an element: deleting the element row deletes the submission row.
        $this->addForeignKeyIfMissing(self::TABLE_SUBMISSIONS, ['id'], Table::ELEMENTS, ['id'], 'CASCADE');
        $this->addForeignKeyIfMissing(self::TABLE_SUBMISSIONS, ['formId'], self::TABLE_FORMS, ['id'], 'CASCADE');

        $this->addForeignKeyIfMissing(self::TABLE_SENTNOTIFICATIONS, ['submissionId'], self::TABLE_SUBMISSIONS, ['id'], 'CASCADE');
        // Keep the sent record when its notification row is deleted: it is history.
        $this->addForeignKeyIfMissing(self::TABLE_SENTNOTIFICATIONS, ['notificationId'], self::TABLE_NOTIFICATIONS, ['id'], 'SET NULL');

        $this->addForeignKeyIfMissing(self::TABLE_LOGS, ['formId'], self::TABLE_FORMS, ['id'], 'SET NULL');
        $this->addForeignKeyIfMissing(self::TABLE_LOGS, ['submissionId'], self::TABLE_SUBMISSIONS, ['id'], 'SET NULL');
    }
}
