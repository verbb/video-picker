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

    public bool $resolveHiResEmbedImage = false;
    public array $embedClientConfig = [];
    public array $embedClientSettings = [];
    public array $embedHeaders = [];
    public array $embedDetectorsSettings = [];
    public array $embedAllowedDomains = [];


    // Public Methods
    // =========================================================================

    public function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['pluginName', 'videosPerPage'], 'required'];
        $rules[] = [['videosPerPage'], 'number', 'integerOnly' => true, 'min' => 1];

        return $rules;
    }

    public function getEmbedClientConfig(): array
    {
        $defaults = [
            'min_image_width' => 16,
            'min_image_height' => 16,
        ];

        return array_replace_recursive($defaults, $this->embedClientConfig);
    }
}
