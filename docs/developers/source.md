# Source
A source returned by `craft.videoPicker.getSourceByHandle()` is a `Source` object. Use it when custom integration code needs provider capabilities or needs to fetch a provider video directly. Templates that render a field selection normally work with a [Video](docs:developers/video) instead.

## Finding Sources

Choose a lookup according to the information your template has:

- `craft.videoPicker.getAllSources()` returns all saved Sources.
- `craft.videoPicker.getAllEnabledSources()` excludes disabled Sources.
- `craft.videoPicker.getAllConfiguredSources()` returns Sources with their required settings; OAuth Sources may still need to reconnect.
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

The name of the source.
:::

::: reference
### `handle`

**Type:** `string|null`

The handle of the source.
:::

::: reference
### `enabled`

**Type:** `bool|null`

Whether the source is enabled or not.
:::

::: reference
### `primaryColor`

**Type:** `string|null`

The primary brand colour of the connected provider.
:::

::: reference
### `icon`

**Type:** `string|null`

The SVG icon of the source provider connected.
:::

::: reference
### `providerName`

**Type:** `string`

The name of the source provider connected.
:::



## Methods

::: reference
### `isConfigured()`

**Returns:** `bool`

Whether the source provider has been configured.
:::

::: reference
### `isConnected()`

**Returns:** `bool`

Whether the source is connected (OAuth token) or ready (credentials saved).
:::

::: reference
### `isUsable()`

**Returns:** `bool`

Whether the required settings and connection are ready for provider requests. This does not check `enabled`; check that property too when using a lookup that includes disabled Sources.
:::

::: reference
### `supportsConnection()`

**Returns:** `bool`

Whether this source type offers a connection action. OAuth sources authorise an account; credential sources verify their API settings.
:::

::: reference
### `supportsBrowse()`

**Returns:** `bool`

Whether the explorer can list collections.
:::

::: reference
### `supportsSearch()`

**Returns:** `bool`

Whether the explorer can search this provider.
:::

::: reference
### `getVideos(method, options = [], videosPerPage = null)`

**Returns:** `array`

Calls a provider browse method and returns a response array. Its `videos` key contains [Video](docs:developers/video) objects; `nextPage` contains the provider's pagination value when another page is available. An unavailable method can return an empty array.

`method` is a `string` matching a supported provider collection method. `options` may contain `id`, `q` or `nextPage`; unsupported keys are discarded. `videosPerPage` is an optional `int` from 1 to 50.

For a connected YouTube Source with the handle `youTube`, list the account's uploads:

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

If `result.nextPage` is present, pass that value as `nextPage` in a subsequent call using the same method and collection options. Treat it as a provider value, not a page number.
:::

::: reference
### `getVideoById(id)`

**Returns:** `verbb\videopicker\models\Video|null`

Fetches a [Video](docs:developers/video) object by its string ID from this source.
:::

::: reference
### `getEmbedHtml(videoId, options = [])`

**Returns:** `string`

Returns the HTML embed code for a string video ID. The options array may include provider intent such as `autoplay`, `muted`, `loop`, `controls` and `start`, iframe attributes, or additional query parameters.
:::

::: reference
### `getEmbedUrl(videoId, options = [])`

**Returns:** `string`

Returns the embed URL for a string video ID with optional provider intent and query parameters.
:::
