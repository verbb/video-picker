<?php
namespace verbb\videopicker\migrations;

use craft\db\Migration;
use craft\db\Query;

/**
 * Deduplicate video_picker_videos by videoUrl and add a unique index (VP-M01).
 */
class m260822_000000_unique_video_url extends Migration
{
    public function safeUp(): bool
    {
        $table = '{{%video_picker_videos}}';

        if (!$this->db->tableExists($table)) {
            return true;
        }

        // Keep the newest row per videoUrl; drop older duplicates.
        $duplicates = (new Query())
            ->select(['videoUrl'])
            ->from($table)
            ->groupBy(['videoUrl'])
            ->having('COUNT(*) > 1')
            ->column();

        foreach ($duplicates as $videoUrl) {
            $ids = (new Query())
                ->select(['id'])
                ->from($table)
                ->where(['videoUrl' => $videoUrl])
                ->orderBy(['dateUpdated' => SORT_DESC, 'id' => SORT_DESC])
                ->column();

            array_shift($ids);

            if ($ids) {
                $this->delete($table, ['id' => $ids]);
            }
        }

        $this->dropIndexIfExists($table, ['videoUrl'], false);
        $this->createIndex(null, $table, ['videoUrl'], true);

        return true;
    }

    public function safeDown(): bool
    {
        echo "m260822_000000_unique_video_url cannot be reverted.\n";

        return false;
    }
}
