# Source
A source returned by `craft.videoPicker.getSourceByHandle()` is a `Source` object. Use it when custom integration code needs provider capabilities or needs to fetch a provider video directly. Templates that render a field selection normally work with a [Video](docs:reference/video) instead.

## Finding Sources

Choose a lookup according to the information your template has:

- `craft.videoPicker.getAllSources()` returns all saved Sources.
- `craft.videoPicker.getAllEnabledSources()` excludes disabled Sources.
- `craft.videoPicker.getAllConfiguredSources()` returns Sources with their required settings, although OAuth Sources may still need to reconnect.
- `craft.videoPicker.getSourceById(id)` accepts an integer database ID.
- `craft.videoPicker.getSourceByHandle(handle)` accepts the configured string handle.

The collection methods return arrays of Source objects. For example, list enabled providers that are ready to use:

```twig
<ul>
    {% for source in craft.videoPicker.getAllEnabledSources() %}
        {% if source.isUsable() %}
            <li>{{ source.name }}</li>
        {% endif %}
    {% endfor %}
</ul>
```

<span id="attributes"></span>

## Properties

::: reference
### `name`

**Type:** `string|null`

The source’s name, as entered in the control panel.
:::

::: reference
### `handle`

**Type:** `string|null`

The source’s handle, used to fetch it with `craft.videoPicker.getSourceByHandle()`.
:::

::: reference
### `enabled`

**Type:** `bool|null`

Whether the source is enabled. Lookups such as `getAllSources()` include disabled sources, so check this before using one.
:::

::: reference
### `primaryColor`

**Type:** `string|null`

The provider’s brand colour as a hex value, or `null` when the provider doesn’t define one. This calls `getPrimaryColor()`.
:::

::: reference
### `icon`

**Type:** `string|null`

The provider’s SVG icon markup, or `null` when the provider doesn’t define one. This calls `getIcon()`.
:::

::: reference
### `providerName`

**Type:** `string`

The name of the platform this source connects to, such as `YouTube` or `Vimeo`. This calls `getProviderName()`.
:::

## Methods

::: reference
### `isConfigured()`

**Returns:** `bool`

Returns whether the source has the settings its provider needs, such as a client ID and secret.
:::

::: reference
### `isConnected()`

**Returns:** `bool`

Returns whether the source is connected. An OAuth source needs a saved token, and a credentials source needs its API settings to have been verified.
:::

::: reference
### `isUsable()`

**Returns:** `bool`

Returns whether the source is configured and, when it needs a connection, connected. This doesn’t check `enabled`, so check that too when a lookup can return disabled sources.
:::

::: reference
### `supportsConnection()`

**Returns:** `bool`

Returns whether this type of source has a connection step. OAuth sources authorise an account, and credentials sources verify their API settings.
:::

::: reference
### `supportsBrowse()`

**Returns:** `bool`

Returns whether editors can browse the provider’s collections in the video explorer.
:::

::: reference
### `supportsSearch()`

**Returns:** `bool`

Returns whether editors can search the provider in the video explorer.
:::

::: reference
### `getVideos()`

**Arguments:** `method` (`string`), `options` (`array`, optional), `videosPerPage` (`int|null`, optional)

**Returns:** `array`

Returns one page of videos from a provider collection, as an array whose `videos` key holds [Video](docs:reference/video) objects and whose `nextPage` key holds the provider’s pagination value when another page is available. The array is empty when the provider doesn’t support the method.

`method` names a collection the provider supports, such as `uploads`. `options` can contain `id`, `q` or `nextPage`, and other keys are ignored. `videosPerPage` sets the page size, from 1 to 50.


For a connected YouTube source with the handle `youTube`, list the account’s uploads:

```twig
{% set source = craft.videoPicker.getSourceByHandle('youTube') %}

{% set result = source and source.enabled and source.isUsable()
    ? source.getVideos('uploads', {}, 12)
    : {} %}

<ul>
    {% for video in result.videos ?? [] %}
        <li><a href="{{ video.url }}">{{ video.title }}</a></li>
    {% endfor %}
</ul>
```

If `result.nextPage` is present, pass that value as `nextPage` in another call with the same method and collection options. Treat it as a value from the provider, not a page number.
:::

::: reference
### `getVideoById()`

**Arguments:** `id` (`string`)

**Returns:** `verbb\videopicker\models\Video|null`

Returns the [Video](docs:reference/video) with this provider ID, or `null` when the provider can’t find it.
:::

::: reference
### `getVideoByUrl()`

**Arguments:** `url` (`string`)

**Returns:** `verbb\videopicker\models\Video|null`

Returns the [Video](docs:reference/video) for a provider URL, or `null` when the URL doesn’t belong to this provider. When the provider request fails, the returned video has the URL and an error on its `url` attribute.
:::

::: reference
### `getEmbedHtml()`

**Arguments:** `videoId` (`string`), `options` (`array`, optional)

**Returns:** `string`

Returns the iframe HTML for a video ID. `options` can include player settings such as `autoplay`, `muted`, `loop`, `controls` and `start`, iframe attributes such as `class` or `title`, and any other query parameters for the embed URL.
:::

::: reference
### `getEmbedUrl()`

**Arguments:** `videoId` (`string`), `options` (`array`, optional)

**Returns:** `string`

Returns the embed URL for a video ID, with the same player settings and query parameters as `getEmbedHtml()`.
:::

::: reference
### `getVideoEmbedOptions()`

**Arguments:** `video` ([Video](docs:reference/video))

**Returns:** `array`

Returns embed options worked out from a video’s metadata, such as Vimeo’s privacy hash for unlisted videos. A video’s own embed methods apply these automatically, beneath the field’s defaults and any options you pass.
:::
