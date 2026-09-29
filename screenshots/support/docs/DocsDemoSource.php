<?php
/**
 * Docs-only Video Picker source for screenshot installs — connected without OAuth,
 * returns canned video metadata for URL resolve + explorer browse.
 */

namespace modules\videopickerdocs;

use verbb\videopicker\base\CredentialsSource;
use verbb\videopicker\models\Collection;
use verbb\videopicker\models\Section;
use verbb\videopicker\models\Video;

use Craft;

use craft\helpers\UrlHelper;

use DateTime;

class DocsDemoSource extends CredentialsSource
{
    public static string $providerHandle = 'docsDemo';

    public static function displayName(): string
    {
        return 'Demo Videos';
    }

    public function getPrimaryColor(): ?string
    {
        return '#2563EB';
    }

    public function getIcon(): ?string
    {
        return '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7L8 5z"/></svg>';
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function getSettingsHtml(): ?string
    {
        return '';
    }

    protected function fetchConnection(): bool
    {
        return true;
    }

    protected function pingCredentials(): void
    {
        // No remote API for docs demo.
    }

    public function getVideoIdFromUrl(string $url): ?string
    {
        if (preg_match('/(?:youtube\\.com\\/(?:watch\\?v=|embed\\/|shorts\\/)|youtu\\.be\\/)([\\w\\-]{11})/', $url, $matches)) {
            return $matches[1];
        }

        if (preg_match('/vimeo\\.com\\/(?:video\\/)?(\\d+)/', $url, $matches)) {
            return $matches[1];
        }

        return 'docsdemo01';
    }

    public function getVideoById(string $id): ?Video
    {
        return $this->buildDemoVideo($id);
    }

    protected function buildEmbedUrl(string $videoId, array $queryParams): string
    {
        return 'https://www.youtube.com/embed/' . rawurlencode($videoId);
    }

    protected function fetchExplorerSections(): array
    {
        return [
            new Section([
                'name' => 'Library',
                'collections' => [
                    new Collection([
                        'name' => 'Uploads',
                        'method' => 'uploads',
                        'icon' => 'video',
                    ]),
                ],
            ]),
        ];
    }

    protected function fetchVideosUploads(array $params = []): array
    {
        return [
            'videos' => [
                $this->buildDemoVideo('docsdemo01'),
                $this->buildDemoVideo('docsdemo02'),
                $this->buildDemoVideo('docsdemo03'),
                $this->buildDemoVideo('docsdemo04'),
            ],
            'nextPage' => null,
        ];
    }

    protected function fetchVideosSearch(array $params = []): array
    {
        return $this->fetchVideosUploads($params);
    }

    private function buildDemoVideo(string $id): Video
    {
        /** @var array<string, array<string, mixed>> $profiles */
        $profiles = [
            '1025521358' => [
                'title' => 'Journey of a Sea Turtle: Majestic Underwater Adventure',
                'url' => 'https://vimeo.com/1025521358',
                'duration' => 3286,
                'plays' => 13400,
                'authorName' => 'Russell Coight',
                'authorUrl' => 'https://vimeo.com/',
                'date' => new DateTime('2025-12-01'),
                'description' => 'Join us on a breathtaking underwater journey with a graceful sea turtle as it glides through vibrant coral reefs and the open ocean. Ideal for nature documentaries and conservation stories.',
                'thumbUrl' => $this->docsScreenshotAssetUrl('sea-turtle-thumb.jpg')
                    ?? $this->demoThumbnailUrl('vp-docs-sea-turtle'),
            ],
            'docsdemo01' => [
                'title' => 'Product walkthrough',
                'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'duration' => 186,
                'plays' => 1280,
                'authorName' => 'Verbb',
                'authorUrl' => 'https://verbb.io',
                'date' => new DateTime('2026-06-01'),
                'description' => 'Docs screenshot demo video.',
                'thumbSeed' => 'vp-docs-walkthrough',
            ],
            'docsdemo02' => [
                'title' => 'Getting started',
                'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'duration' => 186,
                'plays' => 1280,
                'authorName' => 'Verbb',
                'authorUrl' => 'https://verbb.io',
                'date' => new DateTime('2026-06-01'),
                'description' => 'Docs screenshot demo video.',
                'thumbSeed' => 'vp-docs-getting-started',
            ],
            'docsdemo03' => [
                'title' => 'Field settings tour',
                'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'duration' => 186,
                'plays' => 1280,
                'authorName' => 'Verbb',
                'authorUrl' => 'https://verbb.io',
                'date' => new DateTime('2026-06-01'),
                'description' => 'Docs screenshot demo video.',
                'thumbSeed' => 'vp-docs-field-tour',
            ],
            'docsdemo04' => [
                'title' => 'Embedding tips',
                'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'duration' => 186,
                'plays' => 1280,
                'authorName' => 'Verbb',
                'authorUrl' => 'https://verbb.io',
                'date' => new DateTime('2026-06-01'),
                'description' => 'Docs screenshot demo video.',
                'thumbSeed' => 'vp-docs-embedding',
            ],
            'dQw4w9WgXcQ' => [
                'title' => 'Product walkthrough',
                'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'duration' => 186,
                'plays' => 1280,
                'authorName' => 'Verbb',
                'authorUrl' => 'https://verbb.io',
                'date' => new DateTime('2026-06-01'),
                'description' => 'Docs screenshot demo video.',
                'thumbSeed' => 'vp-docs-walkthrough',
            ],
        ];

        $profile = $profiles[$id] ?? [
            'title' => 'Demo video',
            'url' => 'https://vimeo.com/1025521358',
            'duration' => 3286,
            'plays' => 13400,
            'authorName' => 'Russell Coight',
            'authorUrl' => 'https://vimeo.com/',
            'date' => new DateTime('2025-12-01'),
            'description' => 'Join us on a breathtaking underwater journey with a graceful sea turtle as it glides through vibrant coral reefs and the open ocean. Ideal for nature documentaries and conservation stories.',
            'thumbUrl' => $this->docsScreenshotAssetUrl('sea-turtle-thumb.jpg')
                ?? $this->demoThumbnailUrl('vp-docs-sea-turtle'),
        ];

        $video = new Video([
            'id' => $id,
            'url' => $profile['url'],
            'sourceHandle' => $this->handle ?: 'docsDemoVideos',
            'date' => $profile['date'],
            'duration' => $profile['duration'],
            'plays' => $profile['plays'],
            'authorName' => $profile['authorName'],
            'authorUrl' => $profile['authorUrl'],
            'title' => $profile['title'],
            'description' => $profile['description'],
            'private' => false,
            'width' => 1280,
            'height' => 720,
            'thumbnails' => [
                [
                    'url' => $profile['thumbUrl']
                        ?? $this->demoThumbnailUrl($profile['thumbSeed'] ?? 'vp-docs-demo'),
                    'width' => 640,
                    'height' => 360,
                ],
            ],
        ]);

        return $video;
    }

    private function demoThumbnailUrl(string $seed): string
    {
        return 'https://picsum.photos/seed/' . rawurlencode($seed) . '/640/360';
    }

    private function docsScreenshotAssetUrl(string $filename): ?string
    {
        $path = Craft::getAlias('@webroot') . '/video-picker-docs/' . $filename;

        if (!is_file($path)) {
            return null;
        }

        return UrlHelper::siteUrl('video-picker-docs/' . $filename);
    }
}
