# Interfaces & Types

Each Video Picker field adds a `{fieldHandle}_Video` object type to Craft’s GraphQL schema, named from the field’s handle. [Queries](docs:graphql/queries) shows complete requests and responses.

## Field Value Types

### The `{fieldHandle}_Video` Type

The selected video in a Video Picker field. For a field with the handle `videoPickerField`, the type is `videoPickerField_Video`. All fields are nullable, and which ones have values depends on the provider and the stored video.

::: reference
#### `authorName`

**Type:** `String`

The name of the video’s author or channel.
:::

::: reference
#### `authorUrl`

**Type:** `String`

The URL of the author’s profile or channel.
:::

::: reference
#### `authorUsername`

**Type:** `String`

The author’s username on the provider’s platform.
:::

::: reference
#### `date`

**Type:** `DateTime`

The date the video was published or created.
:::

::: reference
#### `description`

**Type:** `String`

The video’s description.
:::

::: reference
#### `duration`

**Type:** `Int`

The video’s length in seconds.
:::

::: reference
#### `embedHtml`

**Type:** `String`

The iframe HTML for the video, using the field’s embed defaults.
:::

::: reference
#### `embedUrl`

**Type:** `String`

The embed URL for the video, using the field’s embed defaults.
:::

::: reference
#### `formattedDuration`

**Type:** `String`

The video’s length for display, as `mm:ss` or `hh:mm:ss`. A live stream reports `∞`.
:::

::: reference
#### `height`

**Type:** `Int`

The video’s height in pixels, when the provider reports it.
:::

::: reference
#### `id`

**Type:** `String`

The video’s ID on the provider’s platform.
:::

::: reference
#### `plays`

**Type:** `Float`

The number of times the video has been played, when the provider reports it. It’s a `Float` so counts above the 32-bit integer limit are returned correctly.
:::

::: reference
#### `private`

**Type:** `Boolean`

Whether the provider marks the video as private.
:::

::: reference
#### `sourceHandle`

**Type:** `String`

The handle of the Video Picker source the video came from.
:::

::: reference
#### `thumbnails`

**Type:** `ArrayType`

A list of thumbnail images as JSON, each with a `url` and optional `width` and `height`.
:::

::: reference
#### `title`

**Type:** `String`

The video’s title.
:::

::: reference
#### `url`

**Type:** `String`

The video’s URL on the provider’s platform.
:::

::: reference
#### `width`

**Type:** `Int`

The video’s width in pixels, when the provider reports it.
:::

## Scalars

### The `ArrayType` Scalar

`ArrayType` is a JSON scalar used by `thumbnails`. Request the field name on its own, without a nested selection, and your client receives the thumbnails as structured data.

```json
{
  "thumbnails": [
    { "url": "https://i.ytimg.com/vi/jfKfPfyJRdk/mqdefault_live.jpg", "width": 320, "height": 180 }
  ]
}
```
