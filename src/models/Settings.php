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

    /**
     * Short TTL for failed Twig embed crawls (seconds). Default 60.
     * Set to 0 to disable negative caching (retry every request).
     */
    public int $embedErrorCacheDuration = 60;

    /**
     * Selected-video DB cache TTL (seconds). Minimum 3600 (1 hour); default 7 days.
     * Expired rows revalidate on read (stale-while-revalidate).
     */
    public int $videoCacheDuration = 604800;

    public bool $resolveHiResEmbedImage = false;
    public array $embedClientConfig = [];
    public array $embedClientSettings = [];
    public array $embedHeaders = [];
    public array $embedDetectorsSettings = [];

    /** @var string[] Host allowlist for Twig embed helpers (empty = any public host). */
    public array $embedAllowedDomains = [];


    // Public Methods
    // =========================================================================

    public function setAttributes($values, $safeOnly = true): void
    {
        if (array_key_exists('embedAllowedDomains', $values)) {
            $values['embedAllowedDomains'] = self::normalizeDomainList($values['embedAllowedDomains']);
        }

        parent::setAttributes($values, $safeOnly);
    }

    /**
     * Editable-table rows for the CP settings form.
     *
     * @return array<int, array{domain: string}>
     */
    public function getEmbedAllowedDomainRows(): array
    {
        return array_map(
            static fn(string $domain): array => ['domain' => $domain],
            $this->embedAllowedDomains,
        );
    }

    public function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['pluginName'], 'trim'];
        $rules[] = [['pluginName', 'videosPerPage'], 'required'];
        $rules[] = [['videosPerPage'], 'number', 'integerOnly' => true, 'min' => 1, 'max' => 50];
        $rules[] = [[
            'providerCacheDuration',
            'providerSearchCacheDuration',
            'embedCacheDuration',
        ], 'number', 'integerOnly' => true, 'min' => 60];
        $rules[] = [['embedErrorCacheDuration'], 'number', 'integerOnly' => true, 'min' => 0];
        $rules[] = [['videoCacheDuration'], 'number', 'integerOnly' => true, 'min' => 3600];

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


    // Private Methods
    // =========================================================================

    /**
     * @return string[]
     */
    private static function normalizeDomainList(mixed $value): array
    {
        if ($value === null || $value === '' || $value === []) {
            return [];
        }

        if (is_string($value)) {
            $value = preg_split('/[\r\n,]+/', $value) ?: [];
        }

        if (!is_array($value)) {
            return [];
        }

        $domains = [];

        foreach ($value as $row) {
            if (is_string($row)) {
                $domain = trim($row);
            } elseif (is_array($row)) {
                $domain = trim((string)($row['domain'] ?? $row[0] ?? ''));
            } else {
                continue;
            }

            // CP/docs: host only — strip accidental scheme / www.
            $domain = preg_replace('#^https?://#i', '', $domain) ?? $domain;
            $domain = preg_replace('#^www\.#i', '', $domain) ?? $domain;
            $domain = strtolower(rtrim($domain, '/.'));

            if ($domain === '') {
                continue;
            }

            $domains[] = $domain;
        }

        return array_values(array_unique($domains));
    }
}
