# Video
A populated Video Picker field returns a `Video` object. You can also obtain one with `craft.videoPicker.getVideoByUrl()` or a source's `getVideoById()` method.

<span id="attributes"></span>

## Properties

::: reference
### `id`

**Type:** `string|null`

The ID of the video.
:::

::: reference
### `url`

**Type:** `string|null`

The URL of the video.
:::

::: reference
### `sourceHandle`

**Type:** `string|null`

A handle identifying the video source, e.g. `youtube` or `dailymotion`.
:::

::: reference
### `date`

**Type:** `DateTime|null`

The date the video was published or created (`DateTime` object).
:::

::: reference
### `duration`

**Type:** `int|null`

Duration of the video in seconds.
:::

::: reference
### `duration8601`

**Type:** `string`

Duration of the video in ISO8601 format.
:::

::: reference
### `plays`

**Type:** `int|null`

The number of times the video has been played.
:::

::: reference
### `authorName`

**Type:** `string|null`

The name of the video's author.
:::

::: reference
### `authorUrl`

**Type:** `string|null`

URL to the author’s profile or channel.
:::

::: reference
### `authorUsername`

**Type:** `string|null`

Username of the author.
:::

::: reference
### `thumbnails`

**Type:** `array`

An array of thumbnail records. Each record contains a `url` and may include integer `width` and `height` values.
:::

::: reference
### `title`

**Type:** `string|null`

The title of the video.
:::

::: reference
### `description`

**Type:** `string|null`

The description of the video.
:::

::: reference
### `private`

**Type:** `bool`

Whether the video is private.
:::

::: reference
### `width`

**Type:** `int|null`

Width of the video in pixels.
:::

::: reference
### `height`

**Type:** `int|null`

Height of the video in pixels.
:::

::: reference
### `raw`

**Type:** `array`

Array of raw data for any additional information provided by the API.
:::


## Methods

::: reference
### `getSource()`

**Returns:** `verbb\videopicker\base\SourceInterface|null`

Returns the [Source](docs:developers/source) for the video.
:::

::: reference
### `getFormattedDuration()`

**Returns:** `string`

Returns a human-readable format of the duration (e.g., `01:23:15`).
:::

::: reference
### `getDuration8601()`

**Returns:** `string`

Returns the duration of the video in ISO8601 format.
:::

::: reference
### `getThumbnail($width = 600)`

**Returns:** `string|null`

Retrieves the URL for the closest available thumbnail to the specified width.
:::

::: reference
### `getEmbedHtml($options = [])`

**Returns:** `string|null`

Returns the HTML embed code for the video, with optional customisation parameters.
:::

::: reference
### `getEmbedUrl($options = [])`

**Returns:** `string|null`

Retrieves the direct URL to embed the video with optional customisation parameters.
:::
