<?php
/**
 * Docs-only connected Vimeo source for screenshot installs — explorer browse
 * with Library/Folders sidebar and canned nature-style demo clips.
 *
 * Thumbnails are bundled JPGs in web/video-picker-docs/explorer/ (copied from
 * docs/.screenshots/video-picker/assets/explorer/). They are fixed per video id,
 * not fetched at capture time, so titles and imagery stay paired across runs.
 */

namespace modules\videopickerdocs;

use verbb\videopicker\base\CredentialsSource;
use verbb\auth\helpers\Provider as ProviderHelper;
use verbb\videopicker\models\Collection;
use verbb\videopicker\models\Section;
use verbb\videopicker\models\Video;

use Craft;

use craft\helpers\UrlHelper;

use DateTime;

class DocsVimeoSource extends CredentialsSource
{
    public static string $providerHandle = 'vimeo';

    public static function displayName(): string
    {
        return 'Vimeo';
    }

    public function getPrimaryColor(): ?string
    {
        return ProviderHelper::getPrimaryColor('vimeo');
    }

    public function getIcon(): ?string
    {
        return ProviderHelper::getIcon('vimeo');
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function getSettingsHtml(): ?string
    {
        return '';
    }

    public function supportsSearch(): bool
    {
        return true;
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
        if (preg_match('/vimeo\\.com\\/(?:video\\/)?(\\d+)/', $url, $matches)) {
            return $matches[1];
        }

        return 'vp-lavender-farm';
    }

    public function getVideoById(string $id): ?Video
    {
        return $this->buildExplorerVideo($id);
    }

    protected function buildEmbedUrl(string $videoId, array $queryParams): string
    {
        return 'https://player.vimeo.com/video/' . rawurlencode($videoId);
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
                        'icon' => 'video-camera',
                    ]),
                    new Collection([
                        'name' => 'Likes',
                        'method' => 'likes',
                        'icon' => 'thumb-up',
                    ]),
                ],
            ]),
            new Section([
                'name' => 'Folders',
                'collections' => [
                    new Collection([
                        'name' => 'Nature',
                        'method' => 'folder',
                        'options' => ['id' => 'nature'],
                        'icon' => 'folder',
                    ]),
                    new Collection([
                        'name' => 'Wedding Portfolio',
                        'method' => 'folder',
                        'options' => ['id' => 'wedding'],
                        'icon' => 'folder',
                    ]),
                ],
            ]),
        ];
    }

    protected function fetchVideosUploads(array $params = []): array
    {
        return $this->explorerVideoPage();
    }

    protected function fetchVideosLikes(array $params = []): array
    {
        return $this->explorerVideoPage(array_slice($this->explorerVideoIds(), 0, 3));
    }

    protected function fetchVideosFolder(array $params = []): array
    {
        return $this->explorerVideoPage();
    }

    protected function fetchVideosSearch(array $params = []): array
    {
        return $this->explorerVideoPage();
    }

    /** @return list<string> */
    private function explorerVideoIds(): array
    {
        return [
            'vp-lavender-farm',
            'vp-surf-beaches',
            'vp-rock-fishing',
            'vp-bird-watching',
            'vp-books',
            'vp-moon-ocean',
        ];
    }

    /** @param list<string>|null $ids */
    private function explorerVideoPage(?array $ids = null): array
    {
        $ids ??= $this->explorerVideoIds();

        return [
            'videos' => array_map(fn(string $id) => $this->buildExplorerVideo($id), $ids),
            'nextPage' => null,
        ];
    }

    private function buildExplorerVideo(string $id): Video
    {
        /** @var array<string, array<string, mixed>> $profiles */
        $profiles = [
            'vp-lavender-farm' => [
                'title' => 'Lavender farm',
                'duration' => 653,
                'thumb' => 'lavender-farm.jpg',
            ],
            'vp-surf-beaches' => [
                'title' => 'Top central coast surf beaches',
                'duration' => 2595,
                'thumb' => 'surf-beaches.jpg',
            ],
            'vp-rock-fishing' => [
                'title' => 'Rock fishing',
                'duration' => 826,
                'thumb' => 'rock-fishing.jpg',
            ],
            'vp-bird-watching' => [
                'title' => 'How to get started bird watching',
                'duration' => 1931,
                'thumb' => 'bird-watching.jpg',
            ],
            'vp-books' => [
                'title' => 'Deep-dive into books',
                'duration' => 1175,
                'thumb' => 'books.jpg',
            ],
            'vp-moon-ocean' => [
                'title' => 'Moon and the ocean',
                'duration' => 332,
                'thumb' => 'moon-ocean.jpg',
            ],
            '1025521358' => [
                'title' => 'Journey of a Sea Turtle: Majestic Underwater Adventure',
                'duration' => 3286,
                'thumb' => '../sea-turtle-thumb.jpg',
            ],
        ];

        $profile = $profiles[$id] ?? $profiles['vp-lavender-farm'];
        $thumbPath = (string)$profile['thumb'];
        $thumbUrl = str_starts_with($thumbPath, '../')
            ? $this->docsScreenshotAssetUrl(ltrim($thumbPath, './'))
            : $this->docsScreenshotAssetUrl('explorer/' . $thumbPath);

        return new Video([
            'id' => $id,
            'url' => is_numeric($id) ? 'https://vimeo.com/' . $id : 'https://vimeo.com/docs/' . rawurlencode($id),
            'sourceHandle' => $this->handle ?: 'vimeo',
            'date' => new DateTime('2025-10-15'),
            'duration' => $profile['duration'],
            'plays' => 4200,
            'authorName' => 'Russell Coight',
            'authorUrl' => 'https://vimeo.com/',
            'title' => $profile['title'],
            'description' => 'Docs screenshot demo clip.',
            'private' => false,
            'width' => 1280,
            'height' => 720,
            'thumbnails' => [
                [
                    'url' => $thumbUrl ?? '',
                    'width' => 640,
                    'height' => 360,
                ],
            ],
        ]);
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
