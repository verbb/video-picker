<?php
namespace verbb\videopicker\records;

use craft\db\ActiveRecord;

class Video extends ActiveRecord
{
    // Public Methods
    // =========================================================================

    public static function tableName(): string
    {
        return '{{%video_picker_videos}}';
    }
}
