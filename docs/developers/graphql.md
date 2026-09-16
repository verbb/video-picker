# GraphQL
You can fetch Video Picker field content through Craft's GraphQL API. Add the entry section and Video Picker field to the schema used by your access token. Replace `blog_blog_Entry` and `videoPickerField` below with the generated entry type and field handle from your project.

```graphql
{
    entries(id: 1234) {
        ... on blog_blog_Entry {
            videoPickerField {
                url
                date
                formattedDuration
                plays
                thumbnails
                title
                embedUrl
            }
        }
    }
}
```

A populated field returns data in this shape:

```json
{
    "data": {
        "entries": [
            {
                "videoPickerField": {
                    "url": "https://youtu.be/jfKfPfyJRdk",
                    "date": "2022-07-12T22:12:29+10:00",
                    "formattedDuration": "∞",
                    "plays": 467338277,
                    "thumbnails": [
                        {
                            "url": "https://i.ytimg.com/vi/jfKfPfyJRdk/default_live.jpg",
                            "width": 120,
                            "height": 90
                        },
                        {
                            "url": "https://i.ytimg.com/vi/jfKfPfyJRdk/mqdefault_live.jpg",
                            "width": 320,
                            "height": 180
                        },
                        {
                            "url": "https://i.ytimg.com/vi/jfKfPfyJRdk/hqdefault_live.jpg",
                            "width": 480,
                            "height": 360
                        },
                        {
                            "url": "https://i.ytimg.com/vi/jfKfPfyJRdk/sddefault_live.jpg",
                            "width": 640,
                            "height": 480
                        },
                        {
                            "url": "https://i.ytimg.com/vi/jfKfPfyJRdk/maxresdefault_live.jpg",
                            "width": 1280,
                            "height": 720
                        }
                    ],
                    "title": "lofi hip hop radio 📚 beats to relax/study to",
                    "embedUrl": "https://www.youtube.com/embed/jfKfPfyJRdk?wmode=transparent"
                }
            }
        ]
    }
}
```

## The `{fieldHandle}_Video` Type

Each Video Picker field exposes an object type named from its handle, such as `videoPickerField_Video`. Select the following fields directly within that field’s selection. All fields are nullable; available metadata depends on the provider and stored video.

| Field | Type | Description |
| --- | --- | --- |
| `id` | `String` | The video identifier supplied by its provider. |
| `url` | `String` | The video’s source URL. |
| `sourceHandle` | `String` | The configured Video Picker source handle. |
| `date` | `DateTime` | The video’s publication date. |
| `duration` | `Int` | Duration in seconds. |
| `formattedDuration` | `String` | Duration formatted for display. |
| `plays` | `Float` | The provider’s play count, when available, including counts above the 32-bit integer limit. |
| `authorName` | `String` | The author’s display name. |
| `authorUrl` | `String` | The author’s URL. |
| `authorUsername` | `String` | The author’s username. |
| `thumbnails` | `ArrayType` | Thumbnail metadata as structured JSON, including URLs and dimensions. |
| `title` | `String` | The video title. |
| `description` | `String` | The video description. |
| `private` | `Boolean` | Whether the provider marks the video as private. |
| `width` | `Int` | Video width in pixels. |
| `height` | `Int` | Video height in pixels. |
| `raw` | `String` | Provider data encoded as a JSON string. |
| `embedHtml` | `String` | Embed HTML using the field’s embed defaults. |
| `embedUrl` | `String` | Embed URL using the field’s embed defaults. |

`thumbnails` is a JSON scalar, so request it without a nested selection. `raw` is a JSON-encoded string and must be decoded separately. Neither `embedHtml` nor `embedUrl` accepts arguments; configure embed defaults on the Video Picker field.
