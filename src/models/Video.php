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

    private ?SourceInterface $_source = null;


    // Public Methods
    // =========================================================================

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
        $video['embedHtml'] = $this->getEmbedHtml(['autoplay' => true]);
        $video['duration'] = $this->getFormattedDuration();

        return $video;
    }

    public function getFormattedDuration(): string
    {
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

    public function getThumbnail(int $width = 600): ?string
    {
        $closestThumbnail = null;
        $smallestDifference = PHP_INT_MAX;

        // Find the thumbnail that most closely matches the width
        foreach ($this->thumbnails as $thumbnail) {
            $difference = abs($thumbnail['width'] - $width);

            if ($difference < $smallestDifference) {
                $smallestDifference = $difference;
                $closestThumbnail = $thumbnail['url'];
            }
        }

        return $closestThumbnail;
    }

    public function getEmbedHtml(array $options = []): ?string
    {
        $source = $this->getSource();

        if (!$source) {
            return null;
        }

        return $source->getEmbedHtml($this->id, $options);
    }

    public function getEmbedUrl(array $options = []): ?string
    {
        $source = $this->getSource();

        if (!$source) {
            return null;
        }

        return $source->getEmbedUrl($this->id, $options);
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
    
}
