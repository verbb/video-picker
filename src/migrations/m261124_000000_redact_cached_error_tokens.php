<?php
namespace verbb\videopicker\migrations;

use craft\db\Migration;
use craft\db\Query;

class m261124_000000_redact_cached_error_tokens extends Migration
{
    // Public Methods
    // =========================================================================

    public function safeUp(): bool
    {
        $table = '{{%video_picker_videos}}';

        if (!$this->db->tableExists($table) || !$this->db->columnExists($table, 'lastError')) {
            return true;
        }

        // Failed Vimeo requests stored provider error messages containing the request URL and its access token.
        $rows = (new Query())
            ->select(['id', 'lastError'])
            ->from($table)
            ->where(['like', 'lastError', 'access_token='])
            ->all($this->db);

        foreach ($rows as $row) {
            $lastError = preg_replace('/(access_token=)[^&\s`\'"]+/i', '$1REDACTED', (string)$row['lastError']);

            if ($lastError !== $row['lastError']) {
                $this->update($table, ['lastError' => $lastError], ['id' => $row['id']], [], false);
            }
        }

        return true;
    }

    public function safeDown(): bool
    {
        echo "m261124_000000_redact_cached_error_tokens cannot be reverted.\n";

        return false;
    }
}
