# Custom Sources

A source connects Video Picker to a video platform, so editors can browse, search and select its videos. Create a custom source when your project needs a platform that Video Picker doesn’t include. These examples use a bootstrapped [Craft module](https://craftcms.com/docs/5.x/extend/module-guide.html) with the namespace `modules\sitemodule`.

## Create the Source Class

A source class extends one of three base classes. Extend `verbb\videopicker\base\OAuthSource` for a platform that connects with OAuth, `verbb\videopicker\base\CredentialsSource` for one that uses API keys, or `verbb\videopicker\base\Source` for one that needs neither. Both connected base classes use [Auth](https://github.com/verbb/auth) to store credentials and make requests.

This OAuth example goes in `modules/sitemodule/MySourceProvider.php`. Install the provider’s `league/oauth2-client` package first, replace `SomeProvider` with its provider class, create the referenced settings template, and change the API paths and response keys to match the platform’s API.

```php
namespace modules\sitemodule;

use Craft;
use verbb\videopicker\base\OAuthSource;
use verbb\videopicker\helpers\SourceSecurity;
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
        return Craft::$app->getView()->renderTemplate(
            'my-module/my-source/settings',
            SourceSecurity::settingsTemplateVariables($this),
        );
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

The outline shows Video Picker’s extension points rather than a finished provider. Add the provider’s OAuth scopes, error handling, pagination and response mapping before using it in production.

## Register the Source

Video Picker finds sources through the `registerSourceTypes` event. Register your class from your module or plugin’s `init()` method.

```php
use craft\events\RegisterComponentTypesEvent;
use modules\sitemodule\MySourceProvider;
use verbb\videopicker\services\Sources;
use yii\base\Event;

Event::on(Sources::class, Sources::EVENT_REGISTER_SOURCE_TYPES, function(RegisterComponentTypesEvent $event) {
    $event->types[] = MySourceProvider::class;
});
```

## Test the Source

Create a source of your new type, connect it and make it available to a test field. Check a collection, a search when supported, a valid URL and an unavailable video. Confirm the selected video saves and renders before giving editors access.

## Methods

These are the methods a source class commonly implements or overrides. [Source](docs:reference/source) describes the methods templates call on a saved source.

::: reference
### `displayName()`

**Returns:** `string`

Returns the provider name shown when choosing a source type. This is a static method.
:::

::: reference
### `getOAuthProviderClass()`

**Returns:** `string`

Returns the class name of the `league/oauth2-client` provider that handles the OAuth connection. This static method is required for classes extending `OAuthSource`.
:::

::: reference
### `getPrimaryColor()`

**Returns:** `string|null`

Returns the brand colour shown for the source in the control panel, or `null` for none.
:::

::: reference
### `getIcon()`

**Returns:** `string|null`

Returns the SVG icon shown for the source in the control panel, or `null` for none.
:::

::: reference
### `getSettingsHtml()`

**Returns:** `string|null`

Returns the settings form shown when editing a source of this type. Pass `SourceSecurity::settingsTemplateVariables($this)` to your template, so credentials are redacted for people without permission to manage them.
:::

::: reference
### `getEmbedUrlFormat()`

**Returns:** `string`

Returns the provider’s embed URL with an `{id}` placeholder, such as `https://provider.com/video/{id}`. Video Picker swaps in the video ID and adds any embed options as query parameters.
:::

::: reference
### `getVideoIdFromUrl()`

**Returns:** `string|null`

Returns the video ID from a provider URL, or `null` when the URL doesn’t belong to this provider. Video Picker uses it when editors paste a URL and for `craft.videoPicker.getVideoByUrl()`.
:::

::: reference
### `getVideoById()`

**Returns:** `verbb\videopicker\models\Video|null`

Returns a [Video](docs:reference/video) for a provider video ID, or `null` when the provider can’t find it.
:::

::: reference
### `getVideoEmbedOptions()`

**Returns:** `array`

Returns extra embed options worked out from a video’s metadata, such as Vimeo’s privacy hash for unlisted videos. Field and template options take priority over these.
:::

::: reference
### `supportsBrowse()`

**Returns:** `bool`

Returns whether editors can browse collections in the video explorer. `OAuthSource` returns `true`.
:::

::: reference
### `supportsSearch()`

**Returns:** `bool`

Returns whether editors can search the provider in the video explorer. `OAuthSource` returns `true`, and a search calls your `fetchVideosSearch()` method with a `q` option.
:::

::: reference
### `fetchExplorerSections()`

**Returns:** `array`

Returns the sections and collections shown in the video explorer, as `Section` models holding `Collection` models. Each collection’s `method` names the `fetchVideos[Method]()` method that loads its videos. This method is protected.
:::

::: reference
### `fetchVideos[Method]()`

**Returns:** `array`

Returns the videos for one collection, as an array with a `videos` list of Video models and an optional `nextPage` value when more are available. Name each method after a collection’s `method`, such as `fetchVideosUploads()`, and use `$this->getVideosPerPage()` for the page size. These methods are protected, and receive the `id`, `q` and `nextPage` options from the explorer.
:::
