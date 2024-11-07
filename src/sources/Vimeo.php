<?php
namespace verbb\videopicker\sources;

use verbb\videopicker\VideoPicker;
use verbb\videopicker\base\OAuthSource;
use verbb\videopicker\helpers\Videos;
use verbb\videopicker\models\Collection;
use verbb\videopicker\models\Section;
use verbb\videopicker\models\Video;

use craft\helpers\ArrayHelper;

use DateTime;
use Throwable;

use verbb\auth\Auth;
use verbb\auth\providers\Vimeo as VimeoProvider;

class Vimeo extends OAuthSource
{
    // Static Methods
    // =========================================================================

    public static function getOAuthProviderClass(): string
    {
        return VimeoProvider::class;
    }

    
    // Properties
    // =========================================================================

    public static string $providerHandle = 'vimeo';


    // Public Methods
    // =========================================================================

    public function getDefaultScopes(): array
    {
        return [
            'public',
            'private',
        ];
    }

    public function getEmbedUrlFormat(): string
    {
        return 'https://player.vimeo.com/video/{id}';
    }

    public function getVideoIdFromUrl(string $url): ?string
    {
        $pattern = '/(?:https?:\/\/)?(?:www\.)?vimeo\.com\/(?:channels\/[\w]+\/|groups\/[\w]+\/videos\/|album\/\d+\/video\/|video\/|)(\d+)/';
        
        if (preg_match($pattern, $url, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public function getVideoById(string $id): ?Video
    {
        $data = $this->request('GET', 'videos/' . $id, [
            'query' => [
                'fields' => 'created_time,description,duration,height,link,name,pictures,pictures,privacy,stats,uri,user,width,download,review_link,files'
            ],
        ]);

        if ($data) {
            return $this->_parseVideo($data);
        }

        return null;
    }


    // Protected Methods
    // =========================================================================

    protected function fetchExplorerSections(): array
    {
        $sections = [];

        // Library
        $sections[] = new Section([
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
                    'icon' => 'thumb-up'
                ]),
            ]
        ]);

        // Folders
        $collections = [];

        foreach ($this->_getCollectionsFolders() as $folder) {
            $collections[] = new Collection([
                'name' => $folder['title'],
                'method' => 'folder',
                'options' => ['id' => $folder['id']],
                'icon' => 'folder',
            ]);
        }

        if ($collections) {
            $sections[] = new Section([
                'name' => 'Folders',
                'collections' => $collections,
            ]);
        }

        // Albums
        $collections = [];

        foreach ($this->_getCollectionsAlbums() as $album) {
            $collections[] = new Collection([
                'name' => $album['title'],
                'method' => 'album',
                'options' => ['id' => $album['id']],
                'icon' => 'layout'
            ]);
        }

        if ($collections) {
            $sections[] = new Section([
                'name' => 'Showcases',
                'collections' => $collections,
            ]);
        }

        // Channels
        $collections = [];

        foreach ($this->_getCollectionsChannels() as $channel) {
            $collections[] = new Collection([
                'name' => $channel['title'],
                'method' => 'channel',
                'options' => ['id' => $channel['id']],
            ]);
        }

        if ($collections) {
            $sections[] = new Section([
                'name' => 'Channels',
                'collections' => $collections,
            ]);
        }

        return $sections;
    }

    protected function fetchVideosAlbum(array $params = []): array
    {
        $albumId = ArrayHelper::remove($params, 'id');

        return $this->_performVideosRequest('me/albums/' . $albumId . '/videos', $params);
    }

    protected function fetchVideosFolder(array $params = []): array
    {
        $folderId = ArrayHelper::remove($params, 'id');

        return $this->_performVideosRequest('me/folders/' . $folderId . '/videos', $params);
    }

    protected function fetchVideosChannel(array $params = []): array
    {
        $channelId = ArrayHelper::remove($params, 'id');

        return $this->_performVideosRequest('channels/' . $channelId . '/videos', $params);
    }

    protected function fetchVideosLikes(array $params = []): array
    {
        return $this->_performVideosRequest('me/likes', $params);
    }

    protected function fetchVideosSearch(array $params = []): array
    {
        $params['query'] = ArrayHelper::remove($params, 'q');

        return $this->_performVideosRequest('videos', $params);
    }

    protected function fetchVideosUploads(array $params = []): array
    {
        return $this->_performVideosRequest('me/videos', $params);
    }


    // Private Methods
    // =========================================================================

    private function _performVideosRequest(string $uri, array $params = []): array
    {
        $query = $this->_queryFromParams($params);
        $query['fields'] = 'created_time,description,duration,height,link,name,pictures,pictures,privacy,stats,uri,user,width,download,review_link,files';

        $data = $this->request('GET', $uri, [
            'query' => $query,
        ]);

        $videos = [];

        foreach (($data['data'] ?? []) as $videoData) {
            $videos[] = $this->_parseVideo($videoData);
        }

        $nextPage = null;

        if (isset($data['paging']['next']) && $data['paging']['next']) {
            $nextPage = $query['page'] + 1;
        }

        return [
            'videos' => $videos,
            'nextPage' => $nextPage,
        ];
    }

    private function _parseVideo(array $data): Video
    {
        $video = new Video();
        $video->raw = $data;
        $video->authorName = $data['user']['name'];
        $video->authorUrl = $data['user']['link'];
        $video->date = new DateTime($data['created_time']);
        $video->description = $data['description'];
        $video->sourceHandle = $this->handle;
        $video->id = (int)substr($data['uri'], strlen('/videos/'));
        $video->plays = $data['stats']['plays'] ?? 0;
        $video->title = $data['name'];
        $video->url = $data['link'];
        $video->width = $data['width'];
        $video->height = $data['height'];
        $video->duration = $data['duration'];

        if (in_array($data['privacy']['view'], ['nobody', 'contacts', 'password', 'users', 'disable'])) {
            $video->private = true;
        }

        foreach (($data['pictures']['sizes'] ?? []) as $picture) {
            $video->thumbnails[] = [
                'url' => $picture['link'],
                'width' => $picture['width'],
                'height' => $picture['height'],
            ];
        }

        return $video;
    }

    private function _getCollectionsFolders(array $params = []): array
    {
        $query = $this->_queryFromParams($params);
        $query['fields'] = 'name,uri';

        $data = $this->request('GET', 'me/folders', [
            'query' => $query,
        ]);

        $collections = [];

        foreach (($data['data'] ?? []) as $data) {
            $collections[] = [
                'id' => substr($data['uri'], strpos($data['uri'], '/projects/') + \strlen('/projects/')),
                'url' => $data['uri'],
                'title' => $data['name'],
                'totalVideos' => $data['metadata']['connections']['videos']['total'] ?? 0,
            ];
        }

        return $collections;
    }

    private function _getCollectionsAlbums(array $params = []): array
    {
        $query = $this->_queryFromParams($params);
        $query['fields'] = 'name,uri,stats';

        $data = $this->request('GET', 'me/albums', [
            'query' => $query,
        ]);

        $collections = [];

        foreach (($data['data'] ?? []) as $data) {
            $collections[] = [
                'id' => substr($data['uri'], strpos($data['uri'], '/albums/') + \strlen('/albums/')),
                'url' => $data['uri'],
                'title' => $data['name'],
                'totalVideos' => $data['data']['stats']['videos'] ?? 0,
            ];
        }

        return $collections;
    }

    private function _getCollectionsChannels(array $params = []): array
    {
        $query = $this->_queryFromParams($params);
        $query['fields'] = 'name,uri';

        $data = $this->request('GET', 'me/channels', [
            'query' => $query,
        ]);

        $collections = [];

        foreach (($data['data'] ?? []) as $data) {
            $collections[] = [
                'id' => substr($data['uri'], strpos($data['uri'], '/channels/') + \strlen('/channels/')),
                'url' => $data['uri'],
                'title' => $data['name'],
                'totalVideos' => $data['data']['stats']['videos'] ?? 0,
            ];
        }

        return $collections;
    }

    private function _queryFromParams(array $params = []): array
    {
        $page = ArrayHelper::remove($params, 'nextPage') ?? 1;

        return array_merge([
            'full_response' => 1,
            'page' => $page,
            'per_page' => $this->getVideosPerPage(),
        ], $params);
    }
}
