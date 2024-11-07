# Source Provider
You can register your own Source Provider to add support for other video platforms, or even extend an existing Source Provider.

```php
namespace modules\sitemodule;

use craft\events\RegisterComponentTypesEvent;
use modules\sitemodule\MySourceProvider;
use verbb\videopicker\services\Sources;
use yii\base\Event;

Event::on(Sources::class, Sources::EVENT_REGISTER_SOURCE_TYPES, function(RegisterComponentTypesEvent $event) {
    $event->types[] = MySourceProvider::class;
});
```

## Example
Create the following class to house your Source Provider logic.

```php
namespace modules\sitemodule;

use Craft;
use Throwable;
use verbb\videopicker\VideoPicker;
use verbb\videopicker\base\OAuthSource;
use verbb\videopicker\helpers\Videos;
use verbb\videopicker\models\Collection;
use verbb\videopicker\models\Section;
use verbb\videopicker\models\Video;

use DateTime;

use League\OAuth2\Client\Provider\SomeProvider;

class MySourceProvider extends OAuthSource
{
    // Static Methods
    // =========================================================================

    public static function displayName(): string
    {
        return 'My Source Provider';
    }

    public static function getOAuthProviderClass(): string
    {
        return SomeProvider::class;
    }


    // Properties
    // =========================================================================

    public static string $providerHandle = 'mySourceProvider';


    // Public Methods
    // =========================================================================

    public function getPrimaryColor(): ?string
    {
        return '#000000';
    }

    public function getIcon(): ?string
    {
        return '<svg>...</svg>';
    }

    public function getSettingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('my-module/my-source/settings', [
            'source' => $this,
        ]);
    }

    public function getEmbedUrlFormat(): string
    {
        return 'https://provider.com/video/{id}';
    }

    public function getVideoIdFromUrl(string $url): ?string
    {
        $pattern = '/(?:https?:\/\/)?(?:www\.)?provider\.com\/(\d+)/';
        
        if (preg_match($pattern, $url, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public function getVideoById(string $id): ?Video
    {
        $data = $this->request('GET', 'videos/' . $id);

        if ($data) {
            return $this->_parseVideo($data);
        }

        return null;
    }


    // Protected Methods
    // =========================================================================

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
                ],
            ]),
        ];
    }

    protected function fetchVideosUploads(array $params = []): array
    {
        // Construct your request according to the API
        $response = $this->request('GET', 'videos', [
            'query' => $params,
        ]);

        $videos = [];

        foreach ($response as $videoData) {
            $videos[] = $this->_parseVideo($videoData);
        }

        return [
            'videos' => $videos,
        ];
    }


    // Private Methods
    // =========================================================================

    private function _parseVideo(array $data): Video
    {
        $video = new Video();
        $video->raw = $data;
        $video->authorName = $data['user']['name'];
        $video->authorUrl = $data['user']['link'];
        $video->date = new DateTime($data['created_time']);
        $video->description = $data['description'];
        $video->sourceHandle = $this->handle;
        $video->id = $data['id'];
        $video->plays = $data['plays'] ?? 0;
        $video->title = $data['title'];
        $video->url = $data['url'];
        $video->width = $data['width'];
        $video->height = $data['height'];
        $video->duration = $data['duration'];
        $video->private = $data['private'];

        foreach (($data['pictures'] ?? []) as $picture) {
            $video->thumbnails[] = [
                'url' => $picture['url'],
                'width' => $picture['width'],
                'height' => $picture['height'],
            ];
        }

        return $video;
    }
}
```

This is the minimum amount of implementation required for a typical source provider.

Video Picker source providers are built around the [Auth](https://github.com/verbb/auth) which in turn is built around [league/oauth2-client](https://github.com/thephpleague/oauth2-client). You can see that the `getOAuthProviderClass()` must return a `League\OAuth2\Client\Provider\AbstractProvider` class.
