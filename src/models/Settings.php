<?php
namespace verbb\videopicker\models;

use craft\base\Model;

class Settings extends Model
{
    // Properties
    // =========================================================================

    public string $pluginName = 'Video Picker';
    public bool $hasCpSection = true;
    public int $videosPerPage = 12;
    

    // Public Methods
    // =========================================================================

    public function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['pluginName', 'videosPerPage'], 'required'];
        $rules[] = [['videosPerPage'], 'number', 'integerOnly' => true, 'min' => 1];

        return $rules;
    }
}
