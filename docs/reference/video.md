# Video
A populated Video Picker field returns a `Video` object. You can also obtain one with `craft.videoPicker.getVideoByUrl()` or a source's `getVideoById()` method.

<span id="attributes"></span>

## Properties

::: reference
### `id`

**Type:** `string|null`

The video’s ID on the provider’s platform.
:::

::: reference
### `url`

**Type:** `string|null`

The video’s URL on the provider’s platform.
:::

::: reference
### `sourceHandle`

**Type:** `string|null`

The handle of the Video Picker source the video came from. `getSource()` uses it to load that source.
:::

::: reference
### `date`

**Type:** `DateTime|null`

The date the video was published or created, or `null` when the provider doesn’t supply one.
:::

::: reference
### `duration`

**Type:** `int|null`

The video’s length in seconds, or `null` when the provider doesn’t supply one.
:::

::: reference
### `duration8601`

**Type:** `string`

The video’s length in ISO 8601 format, such as `PT4M13S`, or an empty string when the duration is unknown. This calls `getDuration8601()`.
:::

::: reference
### `plays`

**Type:** `int|null`

The number of times the video has been played, when the provider reports it.
:::

::: reference
### `authorName`

**Type:** `string|null`

The name of the video’s author or channel.
:::

::: reference
### `authorUrl`

**Type:** `string|null`

The URL of the author’s profile or channel.
:::

::: reference
### `authorUsername`

**Type:** `string|null`

The author’s username on the provider’s platform.
:::

::: reference
### `thumbnails`

**Type:** `array`

A list of thumbnail images. Each item has a `url` and may include integer `width` and `height` values. Use `getThumbnail()` to pick one by width.
:::

::: reference
### `title`

**Type:** `string|null`

The video’s title.
:::

::: reference
### `description`

**Type:** `string|null`

The video’s description.
:::

::: reference
### `private`

**Type:** `bool`

Whether the provider marks the video as private.
:::

::: reference
### `width`

**Type:** `int|null`

The video’s width in pixels, when the provider reports it.
:::

::: reference
### `height`

**Type:** `int|null`

The video’s height in pixels, when the provider reports it.
:::

::: reference
### `raw`

**Type:** `array`

The full data the provider returned for the video, for details Video Picker doesn’t map to its own properties.
:::

## Methods

::: reference
### `getSource()`

**Returns:** `verbb\videopicker\base\SourceInterface|null`

Returns the [Source](docs:reference/source) the video came from, or `null` when that source no longer exists.
:::

::: reference
### `getFormattedDuration()`

**Returns:** `string`

Returns the video’s length for display, as `mm:ss` or `hh:mm:ss` when it’s an hour or longer. A duration of zero, which providers report for live streams, returns `∞`, and an unknown duration returns an empty string.
:::

::: reference
### `getDuration8601()`

**Returns:** `string`

Returns the video’s length in ISO 8601 format, such as `PT1H23M15S`, for use in structured data. An unknown duration returns an empty string.
:::

::: reference
### `getThumbnail()`

**Arguments:** `width` (`int`, optional, defaults to `600`)

**Returns:** `string|null`

Returns the URL of the thumbnail closest to the width you give, or the first thumbnail when none have a width. Returns `null` when the video has no thumbnails.
:::

::: reference
### `getEmbedHtml()`

**Arguments:** `options` (`array`, optional)

**Returns:** `string|null`

Returns the iframe HTML for the video, using the field’s embed defaults. `options` can include player settings such as `autoplay`, `muted`, `loop`, `controls` and `start`, iframe attributes such as `class` or `title`, and other query parameters. Returns `null` when the video’s source no longer exists, unless it’s a generic embed from a URL.
:::

::: reference
### `getEmbedUrl()`

**Arguments:** `options` (`array`, optional)

**Returns:** `string|null`

Returns the embed URL for the video, with the same options as `getEmbedHtml()`. Returns `null` when the video’s source no longer exists, unless it’s a generic embed from a URL.
:::
