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

    public bool $euRestricted = false;


    // Public Methods
    // =========================================================================

    public function supportsSearch(): bool
    {
        return !$this->euRestricted;
    }

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

    public function getVideoEmbedOptions(Video $video): array
    {
        $url = $video->url ?? '';
        parse_str(parse_url($url, PHP_URL_QUERY) ?: '', $query);
        $hash = $query['h'] ?? null;

        if (!$hash && preg_match('~^/' . preg_quote($video->id ?? '', '~') . '/([a-zA-Z0-9]+)(?:/|$)~', parse_url($url, PHP_URL_PATH) ?: '', $matches)) {
            $hash = $matches[1];
        }

        return is_string($hash) && preg_match('/^[a-zA-Z0-9]+$/', $hash) ? ['h' => $hash] : [];
    }

    protected function mapEmbedQueryParams(string $videoId, array $intent): array
    {
        $params = [];

        if (array_key_exists('autoplay', $intent)) {
            $params['autoplay'] = (int)$this->isEmbedTruthy($intent['autoplay']);
        }

        if (array_key_exists('muted', $intent) || array_key_exists('mute', $intent)) {
            $params['muted'] = (int)$this->isEmbedTruthy($intent['muted'] ?? $intent['mute'] ?? null);
        }

        if (array_key_exists('loop', $intent)) {
            $params['loop'] = (int)$this->isEmbedTruthy($intent['loop']);
        }

        if (array_key_exists('controls', $intent)) {
            $params['controls'] = (int)$this->isEmbedTruthy($intent['controls']);
        }

        if (isset($intent['start']) && $intent['start'] !== '') {
            $params['start'] = (int)$intent['start'];
        }

        return $params;
    }

    protected function buildEmbedUrl(string $videoId, array $queryParams): string
    {
        // Vimeo's timecode belongs in the fragment, after any privacy/query options.
        $start = $queryParams['start'] ?? null;
        unset($queryParams['start']);
        $url = parent::buildEmbedUrl($videoId, $queryParams);

        return $start !== null ? $url . '#t=' . (int)$start . 's' : $url;
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
        $data = $this->cachedRequest('GET', 'videos/' . $id, [
            'query' => [
                // Omit download/review_link/files — unused and sensitive when stored in Video::$raw.
                'fields' => 'created_time,description,duration,height,link,name,pictures,privacy,stats,uri,user,width',
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

        // Channels - check if they are disabled for some users (UK/EU)
        // https://help.vimeo.com/hc/en-us/articles/30298226209169-Changes-to-Vimeo-com-in-the-EU-and-UK
        if (!$this->euRestricted) {
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
        $query['fields'] = 'created_time,description,duration,height,link,name,pictures,privacy,stats,uri,user,width';

        $data = $this->cachedRequest('GET', $uri, [
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
        // Never persist download/review/file URLs even if an older API response included them.
        unset($data['download'], $data['review_link'], $data['files']);

        $video = new Video();
        $video->raw = $data;
        $video->authorName = $data['user']['name'] ?? null;;
        $video->authorUrl = $data['user']['link'] ?? null;;
        $video->date = new DateTime($data['created_time'] ?? '');
        $video->description = $data['description'] ?? null;;
        $video->sourceHandle = $this->handle;
        $video->id = (int)substr($data['uri'], strlen('/videos/'));
        $video->plays = $data['stats']['plays'] ?? 0;
        $video->title = $data['name'] ?? null;;
        $video->url = $data['link'] ?? null;;
        $video->width = $data['width'] ?? null;;
        $video->height = $data['height'] ?? null;;
        $video->duration = $data['duration'] ?? null;;

        if (in_array(($data['privacy']['view'] ?? ''), ['nobody', 'contacts', 'password', 'users', 'disable'])) {
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

        $items = $this->_getCollectionItems('me/folders', $query);
        $collections = [];

        foreach ($items as $data) {
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

        $items = $this->_getCollectionItems('me/albums', $query);
        $collections = [];

        foreach ($items as $data) {
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

        $items = $this->_getCollectionItems('me/channels', $query);
        $collections = [];

        foreach ($items as $data) {
            $collections[] = [
                'id' => substr($data['uri'], strpos($data['uri'], '/channels/') + \strlen('/channels/')),
                'url' => $data['uri'],
                'title' => $data['name'],
                'totalVideos' => $data['data']['stats']['videos'] ?? 0,
            ];
        }

        return $collections;
    }

    private function _getCollectionItems(string $uri, array $query): array
    {
        $items = [];
        $query['per_page'] = 100;

        do {
            $response = $this->cachedRequest('GET', $uri, ['query' => $query]);
            array_push($items, ...($response['data'] ?? []));
            $query['page']++;
        } while (!empty($response['paging']['next']));

        return $items;
    }

    private function _queryFromParams(array $params = []): array
    {
        $page = ArrayHelper::remove($params, 'nextPage') ?? 1;

        // Drop pagination / page-size keys so callers cannot override the clamp (D02).
        ArrayHelper::remove($params, 'per_page');
        ArrayHelper::remove($params, 'page');
        ArrayHelper::remove($params, 'maxResults');
        ArrayHelper::remove($params, 'pageToken');

        return array_merge($params, [
            'full_response' => 1,
            'page' => $page,
            'per_page' => $this->getVideosPerPage(),
        ]);
    }
}
