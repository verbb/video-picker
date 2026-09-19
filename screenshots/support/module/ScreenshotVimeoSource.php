<?php
namespace modules\videopickerscreenshots;

use craft\helpers\UrlHelper;
use DateTime;
use verbb\videopicker\base\Source;
use verbb\videopicker\models\Collection;
use verbb\videopicker\models\Section;
use verbb\videopicker\models\Video;

class ScreenshotVimeoSource extends Source
{
    public static string $providerHandle = 'vimeo';

    public static function displayName(): string
    {
        return 'Vimeo';
    }

    public static function getOAuthProviderClass(): string
    {
        return '';
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function isConnected(): bool
    {
        return true;
    }

    public function getSettingsHtml(): ?string
    {
        return '';
    }

    public function getVideoIdFromUrl(string $url): ?string
    {
        if (preg_match('/vimeo\\.com\\/(?:video\\/)?([\\w-]+)/', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public function getVideoById(string $id): ?Video
    {
        return $this->_makeVideo($id);
    }

    public function getEmbedUrlFormat(): string
    {
        return 'https://player.vimeo.com/video/{id}';
    }

    protected function fetchExplorerSections(): array
    {
        return [
            new Section([
                'name' => 'Library',
                'collections' => [
                    new Collection(['name' => 'Uploads', 'method' => 'uploads', 'icon' => 'video-camera']),
                    new Collection(['name' => 'Likes', 'method' => 'likes', 'icon' => 'thumb-up']),
                ],
            ]),
            new Section([
                'name' => 'Folders',
                'collections' => [
                    new Collection(['name' => 'Nature', 'method' => 'folder', 'options' => ['id' => 'nature'], 'icon' => 'folder']),
                    new Collection(['name' => 'Wedding Portfolio', 'method' => 'folder', 'options' => ['id' => 'wedding'], 'icon' => 'folder']),
                ],
            ]),
        ];
    }

    protected function fetchVideosUploads(array $params = []): array
    {
        return $this->_videoPage();
    }

    protected function fetchVideosLikes(array $params = []): array
    {
        return $this->_videoPage(array_slice($this->_videoIds(), 0, 3));
    }

    protected function fetchVideosFolder(array $params = []): array
    {
        return $this->_videoPage();
    }

    protected function fetchVideosSearch(array $params = []): array
    {
        return $this->_videoPage();
    }

    private function _videoIds(): array
    {
        return ['lavender-farm', 'surf-beaches', 'rock-fishing', 'bird-watching', 'books', 'moon-ocean'];
    }

    private function _videoPage(?array $ids = null): array
    {
        return [
            'videos' => array_map(fn(string $id) => $this->_makeVideo($id), $ids ?? $this->_videoIds()),
            'nextPage' => null,
        ];
    }

    private function _makeVideo(string $id): Video
    {
        $profiles = [
            'lavender-farm' => ['Lavender farm', 653, 'lavender-farm.jpg'],
            'surf-beaches' => ['Top central coast surf beaches', 2595, 'surf-beaches.jpg'],
            'rock-fishing' => ['Rock fishing', 826, 'rock-fishing.jpg'],
            'bird-watching' => ['How to get started bird watching', 1931, 'bird-watching.jpg'],
            'books' => ['Deep-dive into books', 1175, 'books.jpg'],
            'moon-ocean' => ['Moon and the ocean', 332, 'moon-ocean.jpg'],
            '1025521358' => ['Journey of a Sea Turtle', 3286, 'sea-turtle-thumb.jpg'],
        ];
        [$title, $duration, $thumbnail] = $profiles[$id] ?? $profiles['lavender-farm'];

        return new Video([
            'id' => $id,
            'url' => 'https://vimeo.com/' . $id,
            'sourceHandle' => $this->handle ?: 'vimeo',
            'date' => new DateTime('2025-10-15'),
            'duration' => $duration,
            'plays' => 4200,
            'authorName' => 'Verbb',
            'authorUrl' => 'https://verbb.io',
            'title' => $title,
            'description' => 'A representative video from the connected Vimeo library.',
            'private' => false,
            'width' => 1280,
            'height' => 720,
            'thumbnails' => [[
                'url' => UrlHelper::siteUrl('video-picker-screenshots/' . ($id === '1025521358' ? '' : 'explorer/') . $thumbnail),
                'width' => 640,
                'height' => 360,
            ]],
        ]);
    }
}
