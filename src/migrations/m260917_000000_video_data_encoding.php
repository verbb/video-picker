<?php
namespace verbb\videopicker\migrations;

use craft\db\Migration;

class m260917_000000_video_data_encoding extends Migration
{
    // Public Methods
    // =========================================================================

    public function safeUp(): bool
    {
        $table = '{{%video_picker_videos}}';

        if ($this->db->tableExists($table) && !$this->db->columnExists($table, 'dataVersion')) {
            // Preserve legacy decoding until each snapshot is refreshed from its provider.
            $this->addColumn($table, 'dataVersion', $this->smallInteger()->notNull()->defaultValue(0));
        }

        return true;
    }
}
