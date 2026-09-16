<?php
namespace verbb\videopicker\models;

use verbb\videopicker\VideoPicker;
use verbb\videopicker\base\SourceInterface;
use verbb\videopicker\helpers\Videos;

use craft\base\Model;
use craft\helpers\ArrayHelper;
use craft\helpers\Json;
use craft\helpers\StringHelper;
use craft\helpers\Template;

use DateTime;

use Twig\Markup;

class Video extends Model
{
    // Properties
    // =========================================================================

    public ?string $id = null;
    public ?string $url = null;
    public ?string $sourceHandle = null;
    public ?DateTime $date = null;
    public ?int $duration = null;
    public ?int $plays = null;
    public ?string $authorName = null;
    public ?string $authorUrl = null;
    public ?string $authorUsername = null;
    public array $thumbnails = [];
    public ?string $title = null;
    public ?string $description = null;
    public bool $private = false;
    public ?int $width = null;
    public ?int $height = null;
    public array $raw = [];

    /**
     * Field-level embed intent defaults (autoplay/muted/loop/controls).
     * Merged under call-site options; not part of persisted video cache identity.
     */
    public array $embedDefaults = [];

    /**
     * Managed DB cache status for this snapshot (ok / stale / unavailable).
     * Runtime-only — not serialized into video_picker_videos.data.
     */
    public string $cacheStatus = 'ok';

    /** Last revalidation error when status is stale/unavailable (runtime-only). */
    public ?string $cacheError = null;

    private ?SourceInterface $_source = null;


    // Public Methods
    // =========================================================================

    public function fields(): array
    {
        $fields = parent::fields();
        // Keep field defaults / cache chrome off serialized video cache / GraphQL bag.
        unset($fields['embedDefaults'], $fields['cacheStatus'], $fields['cacheError']);

        return $fields;
    }

    public function getVideoData(): array
    {
        $video = $this->toArray([
            'id',
            'url',
            'sourceHandle',
            'date',
            'plays',
            'authorName',
            'authorUrl',
            'title',
            'description',
            'private',
        ]);

        $video['thumbnail'] = $this->getThumbnail();
        // CP preview: always autoplay+muted so the dialog feels live without sound blast.
        $video['embedHtml'] = $this->getEmbedHtml(['autoplay' => true, 'muted' => true]);
        $video['duration'] = $this->getFormattedDuration();
        $video['duration8601'] = $this->getDuration8601();
        $video['cacheStatus'] = $this->cacheStatus;
        $video['cacheError'] = $this->cacheError;

        // Selected-card chrome (P02) — provider identity when the source still exists.
        if ($source = $this->getSource()) {
            $video['providerName'] = $source->getProviderName();
            $video['providerHandle'] = $source->getProviderHandle();
            $video['providerColor'] = $source->getPrimaryColor();
            // Brand SVG for the compact title-row icon (CP field preview).
            $video['providerIcon'] = $source->getIcon();
        }

        return $video;
    }

    public function getFormattedDuration(): string
    {
        if ($this->duration === null) {
            return '';
        }

        $hours = intdiv($this->duration, 3600);
        $minutes = intdiv($this->duration % 3600, 60);
        $seconds = $this->duration % 60;

        // If under an hour, display as mm:ss
        if ($hours > 0) {
            return sprintf("%02d:%02d:%02d", $hours, $minutes, $seconds);
        }

        if ($minutes === 0 && $seconds === 0) {
            return '∞';
        }

        return sprintf("%02d:%02d", $minutes, $seconds);
    }

    public function getDuration8601(): string
    {
        if ($this->duration === null) {
            return '';
        }

        $hours = intdiv($this->duration, 3600);
        $minutes = intdiv($this->duration % 3600, 60);
        $seconds = $this->duration % 60;

        $iso8601 = 'PT';

        if ($hours > 0) {
            $iso8601 .= sprintf('%dH', $hours);
        }

        if ($minutes > 0) {
            $iso8601 .= sprintf('%dM', $minutes);
        }

        return $iso8601 . sprintf('%dS', $seconds);
    }

    public function getThumbnail(int $width = 600): ?string
    {
        if (!$this->thumbnails) {
            return null;
        }

        $closestThumbnail = null;
        $smallestDifference = PHP_INT_MAX;
        $firstUrl = null;

        // Find the thumbnail that most closely matches the width when dimensions exist.
        foreach ($this->thumbnails as $thumbnail) {
            $url = $thumbnail['url'] ?? null;

            if (!$url) {
                continue;
            }

            $firstUrl ??= $url;

            if (!isset($thumbnail['width'])) {
                continue;
            }

            $difference = abs((int)$thumbnail['width'] - $width);

            if ($difference < $smallestDifference) {
                $smallestDifference = $difference;
                $closestThumbnail = $url;
            }
        }

        return $closestThumbnail ?? $firstUrl;
    }

    public function getEmbedHtml(array $options = []): ?string
    {
        $source = $this->getSource();

        if (!$source) {
            return null;
        }

        return $source->getEmbedHtml($this->id, $this->_getEmbedOptions($source, $options));
    }

    public function getEmbedUrl(array $options = []): ?string
    {
        $source = $this->getSource();

        if (!$source) {
            return null;
        }

        return $source->getEmbedUrl($this->id, $this->_getEmbedOptions($source, $options));
    }

    public function getSource(): ?SourceInterface
    {
        if (!$this->_source && $this->sourceHandle) {
            $this->_source = VideoPicker::$plugin->getSources()->getSourceByHandle($this->sourceHandle);
        }

        return $this->_source;
    }

    public function serializeData(): string
    {
        // Handle emoji's in some values
        return StringHelper::emojiToShortcodes(Json::encode($this));
    }


    // Private Methods
    // =========================================================================

    private function _getEmbedOptions(SourceInterface $source, array $options): array
    {
        // Derive required provider metadata even for snapshots saved before the
        // option existed. Keep custom interface implementations without this hook usable.
        $providerOptions = method_exists($source, 'getVideoEmbedOptions') ? $source->getVideoEmbedOptions($this) : [];

        return array_merge($providerOptions, $this->embedDefaults, $options);
    }

}
