<?php
namespace verbb\videopicker\sources;

use verbb\videopicker\VideoPicker;
use verbb\videopicker\base\OAuthSource;
use verbb\videopicker\helpers\Videos;
use verbb\videopicker\models\Collection;
use verbb\videopicker\models\Section;
use verbb\videopicker\models\Video;

use craft\helpers\App;
use craft\helpers\ArrayHelper;

use DateTime;
use DateInterval;
use Throwable;

use verbb\auth\Auth;
use verbb\auth\providers\Google as GoogleProvider;

class YouTube extends OAuthSource
{
    // Static Methods
    // =========================================================================

    public static function getOAuthProviderClass(): string
    {
        return GoogleProvider::class;
    }


    // Properties
    // =========================================================================

    public static string $providerHandle = 'youTube';

    public ?string $proxyRedirect = null;


    // Public Methods
    // =========================================================================

    public function getProxyRedirect(): ?bool
    {
        return App::parseBooleanEnv($this->proxyRedirect);
    }

    public function getRedirectUri(): ?string
    {
        $uri = parent::getRedirectUri();

        // Allow a proxy to our server to forward on the request - just for local dev ease
        if ($this->getProxyRedirect()) {
            return "https://proxy.verbb.io?return=$uri";
        }

        return $uri;
    }

    public function getDefaultScopes(): array
    {
        return [
            'https://www.googleapis.com/auth/userinfo.profile',
            'https://www.googleapis.com/auth/userinfo.email',
            'https://www.googleapis.com/auth/youtube',
            'https://www.googleapis.com/auth/youtube.readonly',
        ];
    }

    public function getAuthorizationUrlOptions(): array
    {
        $options = parent::getAuthorizationUrlOptions();
        $options['access_type'] = 'offline';
        $options['prompt'] = 'consent';
        
        return $options;
    }

    public function getEmbedUrlFormat(): string
    {
        return 'https://www.youtube.com/embed/{id}?wmode=transparent';
    }

    public function getVideoIdFromUrl(string $url): ?string
    {
        $pattern = '/(?:https?:\/\/)?(?:www\.)?(?:youtube\.com\/(?:watch\?v=|embed\/|v\/|shorts\/|.+\?v=)|youtu\.be\/)([\w\-]{11})/';
        
        if (preg_match($pattern, $url, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public function getVideoById(string $id): ?Video
    {
        $items = $this->request('GET', 'youtube/v3/videos', [
            'query' => [
                'part' => 'snippet,statistics,contentDetails,status',
                'id' => $id,
            ],
        ]);

        $data = $items['items'][0] ?? [];

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

        // Playlists
        $collections = [];

        foreach ($this->_getCollectionsPlaylists() as $playlist) {
            $collections[] = new Collection([
                'name' => $playlist['title'],
                'method' => 'playlist',
                'options' => ['id' => $playlist['id']],
                'icon' => 'list',
            ]);
        }

        if ($collections) {
            $sections[] = new Section([
                'name' => 'Playlists',
                'collections' => $collections,
            ]);
        }

        return $sections;
    }

    protected function fetchVideosPlaylist(array $params = []): array
    {
        $params['part'] = 'id,snippet';
        $params['playlistId'] = ArrayHelper::remove($params, 'id');

        $response = $this->request('GET', 'youtube/v3/playlistItems', [
            'query' => $this->_queryFromParams($params),
        ]);

        $videoIds = [];

        foreach (($response['items'] ?? []) as $item) {
            $videoIds[] = $item['snippet']['resourceId']['videoId'];
        }

        return $this->_getVideosResponse($response, $videoIds);
    }

    protected function fetchVideosLikes(array $params = []): array
    {
        $params['part'] = 'snippet,statistics,contentDetails,status';
        $params['myRating'] = 'like';

        $response = $this->request('GET', 'youtube/v3/videos', [
            'query' => $this->_queryFromParams($params),
        ]);

        $videos = [];

        foreach (($response['items'] ?? []) as $videoData) {
            $videos[] = $this->_parseVideo($videoData);
        }

        return [
            'videos' => $videos,
            'nextPage' => $response['nextPageToken'] ?? null,
        ];
    }

    protected function fetchVideosSearch(array $params = []): array
    {
        $params['part'] = 'id';
        $params['type'] = 'video';

        $response = $this->request('GET', 'youtube/v3/search', [
            'query' => $this->_queryFromParams($params),
        ]);

        $videoIds = [];

        foreach (($response['items'] ?? []) as $item) {
            $videoIds[] = $item['id']['videoId'];
        }

        return $this->_getVideosResponse($response, $videoIds);
    }

    protected function fetchVideosUploads(array $params = []): array
    {
        $uploadsPlaylistId = $this->_getSpecialPlaylistId('uploads');

        if (!$uploadsPlaylistId) {
            return [];
        }

        $params['part'] = 'id,snippet';
        $params['playlistId'] = $uploadsPlaylistId;

        $response = $this->request('GET', 'youtube/v3/playlistItems', [
            'query' => $this->_queryFromParams($params),
        ]);

        $videoIds = [];

        foreach (($response['items'] ?? []) as $item) {
            $videoIds[] = $item['snippet']['resourceId']['videoId'];
        }

        return $this->_getVideosResponse($response, $videoIds);
    }


    // Private Methods
    // =========================================================================

    private function _getVideosResponse(array $response, array $videoIds): array
    {
        $videos = [];

        $videosResponse = $this->request('GET', 'youtube/v3/videos', [
            'query' => [
                'part' => 'snippet,statistics,contentDetails,status',
                'id' => implode(',', $videoIds),
            ],
        ]);

        foreach (($videosResponse['items'] ?? []) as $videoData) {
            $videos[] = $this->_parseVideo($videoData);
        }

        return [
            'videos' => $videos,
            'nextPage' => $response['nextPageToken'] ?? null,
        ];
    }

    private function _parseVideo(array $data): Video
    {
        $video = new Video();
        $video->raw = $data;
        $video->authorName = $data['snippet']['channelTitle'];
        $video->authorUrl = 'http://youtube.com/channel/' . $data['snippet']['channelId'];
        $video->date = new DateTime($data['snippet']['publishedAt']);
        $video->description = $data['snippet']['description'];
        $video->sourceHandle = $this->handle;
        $video->id = $data['id'];
        $video->plays = $data['statistics']['viewCount'];
        $video->title = $data['snippet']['title'];
        $video->url = 'https://youtu.be/' . $video->id;

        $interval = new DateInterval($data['contentDetails']['duration']);
        $video->duration = (new DateTime('@0'))->add($interval)->getTimestamp();

        if (!empty($data['status']['privacyStatus']) && $data['status']['privacyStatus'] === 'private') {
            $video->private = true;
        }

        foreach (($data['snippet']['thumbnails'] ?? []) as $picture) {
            $video->thumbnails[] = [
                'url' => $picture['url'],
                'width' => $picture['width'],
                'height' => $picture['height'],
            ];
        }

        return $video;
    }

    private function _getCollectionsPlaylists(array $params = []): array
    {
        $data = $this->request('GET', 'youtube/v3/playlists', [
            'query' => [
                'part' => 'snippet',
                'mine' => 'true',
                'maxResults' => 50,
            ],
        ]);

        $collections = [];

        foreach (($data['items'] ?? []) as $item) {
            $collection = [];
            $collection['id'] = $item['id'];
            $collection['title'] = $item['snippet']['title'];
            $collection['totalVideos'] = 0;
            $collection['url'] = 'title';

            $collections[] = $collection;
        }

        return $collections;
    }

    private function _queryFromParams(array $params = []): array
    {
        $page = ArrayHelper::remove($params, 'nextPage') ?? null;

        return array_merge([
            'maxResults' => $this->getVideosPerPage(),
            'pageToken' => $page,
        ], $params);
    }

    private function _getSpecialPlaylistId(string $type)
    {
        $specialPlaylists = $this->_getSpecialPlaylists();

        if (isset($specialPlaylists[$type])) {
            return $specialPlaylists[$type];
        }

        return null;
    }

    private function _getSpecialPlaylists(): array
    {
        $channelsResponse = $this->request('GET', 'youtube/v3/channels', [
            'query' => [
                'part' => 'contentDetails',
                'mine' => 'true',
            ],
        ]);

        if (isset($channelsResponse['items'][0])) {
            $channel = $channelsResponse['items'][0];

            return $channel['contentDetails']['relatedPlaylists'];
        }

        return [];
    }

}
