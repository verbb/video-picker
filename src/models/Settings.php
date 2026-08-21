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

    /** Application-cache TTL (seconds) for provider API responses. */
    public int $providerCacheDuration = 3600;

    /** Shorter TTL for search / high-cardinality provider requests. */
    public int $providerSearchCacheDuration = 900;

    /** Application-cache TTL for Twig generic embed crawls. */
    public int $embedCacheDuration = 3600;

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
        $rules[] = [['videosPerPage'], 'number', 'integerOnly' => true, 'min' => 1, 'max' => 50];
        $rules[] = [[
            'providerCacheDuration',
            'providerSearchCacheDuration',
            'embedCacheDuration',
        ], 'number', 'integerOnly' => true, 'min' => 60];

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
