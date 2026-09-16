# Source Provider
Register a Source Provider when your project needs a video platform that Video Picker does not include. Start with a bootstrapped [Craft module](https://craftcms.com/docs/5.x/extend/module-guide.html) using the namespace `modules\sitemodule`.

Create `MySourceProvider.php` beside `Module.php` for the provider class. Add the following imports at the top of `Module.php`, then put the listener inside `Module::init()` after `parent::init()`. This registration snippet is partial module code:

```php

use craft\events\RegisterComponentTypesEvent;
use modules\sitemodule\MySourceProvider;
use verbb\videopicker\services\Sources;
use yii\base\Event;

Event::on(Sources::class, Sources::EVENT_REGISTER_SOURCE_TYPES, function(RegisterComponentTypesEvent $event) {
    $event->types[] = MySourceProvider::class;
});
```

## OAuth Source Outline

Install the provider-specific `league/oauth2-client` package before extending `OAuthSource`. Replace `SomeProvider` with that package's `AbstractProvider` implementation, create the referenced settings template, and map the example API paths and response keys to the provider's documented contract.

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
        return null;
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

This outline shows the Video Picker extension points; it is not a complete provider implementation. Add the provider's OAuth scopes, error handling, pagination and response mapping before registering it in production.

Video Picker source providers extend `Source`, `CredentialsSource` (API keys), or
`OAuthSource` (OAuth Connect). Credential sources use the
[Auth](https://github.com/verbb/auth) credentials trait; OAuth sources use
`league/oauth2-client` via Auth.

After implementing the provider, create a Source of its type, connect it and make it available to a test field. Verify a collection, a search when supported, a valid URL and an unavailable video. Confirm the selected video saves and renders before giving editors access.
