<?php
namespace verbb\videopicker\migrations;

use craft\db\Migration;
use craft\helpers\MigrationHelper;

use verbb\auth\Auth;

class Install extends Migration
{
    // Public Methods
    // =========================================================================

    public function safeUp(): bool
    {
        // Ensure that the Auth module kicks off setting up tables
        Auth::getInstance()->migrator->up();

        $this->createTables();
        $this->createIndexes();

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropForeignKeys();
        $this->dropTables();

        // Delete all tokens for this plugin
        Auth::getInstance()->getTokens()->deleteTokensByOwner('video-picker');

        return true;
    }

    public function createTables(): void
    {
        $this->archiveTableIfExists('{{%video_picker_sources}}');
        $this->createTable('{{%video_picker_sources}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string()->notNull(),
            'handle' => $this->string()->notNull(),
            'enabled' => $this->boolean(),
            'type' => $this->string()->notNull(),
            'settings' => $this->text(),
            'sortOrder' => $this->smallInteger()->unsigned(),
            'cache' => $this->text(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->archiveTableIfExists('{{%video_picker_videos}}');
        $this->createTable('{{%video_picker_videos}}', [
            'id' => $this->primaryKey(),
            'videoId' => $this->string()->notNull(),
            'videoUrl' => $this->string()->notNull(),
            'data' => $this->text(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);
    }

    public function createIndexes(): void
    {
        $this->createIndex(null, '{{%video_picker_sources}}', ['name'], true);
        $this->createIndex(null, '{{%video_picker_sources}}', ['handle'], true);

        $this->createIndex(null, '{{%video_picker_videos}}', ['videoId'], false);
        $this->createIndex(null, '{{%video_picker_videos}}', ['videoUrl'], false);
    }

    public function dropTables(): void
    {
        $this->dropTableIfExists('{{%video_picker_sources}}');
        $this->dropTableIfExists('{{%video_picker_videos}}');
    }

    public function dropForeignKeys(): void
    {
        if ($this->db->tableExists('{{%video_picker_sources}}')) {
            MigrationHelper::dropAllForeignKeysOnTable('{{%video_picker_sources}}', $this);
        }

        if ($this->db->tableExists('{{%video_picker_videos}}')) {
            MigrationHelper::dropAllForeignKeysOnTable('{{%video_picker_videos}}', $this);
        }
    }
}
