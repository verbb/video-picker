<?php
namespace verbb\videopicker\records;

use craft\db\ActiveRecord;

class Source extends ActiveRecord
{
    // Public Methods
    // =========================================================================

    public static function tableName(): string
    {
        return '{{%video_picker_sources}}';
    }
}
