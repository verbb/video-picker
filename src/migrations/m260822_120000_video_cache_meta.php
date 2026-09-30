<?php
namespace verbb\videopicker\migrations;

use craft\db\Migration;
use craft\db\Query;
use craft\helpers\DateTimeHelper;
use craft\helpers\Db;

use DateInterval;
use DateTime;

/**
 * Managed selected-video cache metadata (A03): fetchedAt, expiresAt, status, lastError.
 */
class m260822_120000_video_cache_meta extends Migration
{
    // Public Methods
    // =========================================================================

    public function safeUp(): bool
    {
        $table = '{{%video_picker_videos}}';

        if (!$this->db->tableExists($table)) {
            return true;
        }

        if (!$this->db->columnExists($table, 'fetchedAt')) {
            $this->addColumn($table, 'fetchedAt', $this->dateTime()->null()->after('data'));
        }

        if (!$this->db->columnExists($table, 'expiresAt')) {
            $this->addColumn($table, 'expiresAt', $this->dateTime()->null()->after('fetchedAt'));
        }

        if (!$this->db->columnExists($table, 'status')) {
            $this->addColumn($table, 'status', $this->string(32)->notNull()->defaultValue('ok')->after('expiresAt'));
        }

        if (!$this->db->columnExists($table, 'lastError')) {
            $this->addColumn($table, 'lastError', $this->text()->null()->after('status'));
        }

        // MySQL keeps schema changes when a later migration statement fails, so retries
        // must accept indexes created by the first attempt.
        $this->createIndexIfMissing($table, ['expiresAt']);
        $this->createIndexIfMissing($table, ['status']);

        // Treat existing rows as freshly fetched for one default TTL window (7 days).
        $ttl = new DateInterval('P7D');
        $rows = (new Query())
            ->select(['id', 'dateUpdated'])
            ->from($table)
            ->where(['fetchedAt' => null])
            ->all();

        foreach ($rows as $row) {
            $fetched = DateTimeHelper::toDateTime($row['dateUpdated']) ?: new DateTime();
            $expires = (clone $fetched)->add($ttl);

            $this->update($table, [
                'fetchedAt' => Db::prepareDateForDb($fetched),
                'expiresAt' => Db::prepareDateForDb($expires),
                'status' => 'ok',
                'lastError' => null,
            ], ['id' => $row['id']], [], false);
        }

        return true;
    }

    public function safeDown(): bool
    {
        echo "m260822_120000_video_cache_meta cannot be reverted.\n";

        return false;
    }
}
