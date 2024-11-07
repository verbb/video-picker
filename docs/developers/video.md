# Video
Whenever you're dealing with a video in your template, you're actually working with a `Video` object.

## Attributes

Attribute | Description
--- | ---
`id` | The ID of the video.
`url` | The URL of the video.
`sourceHandle` | A handle identifying the video source, e.g., "YouTube" or "Vimeo".
`date` | The date the video was published or created (`DateTime` object).
`duration` | Duration of the video in seconds.
`plays` | The number of times the video has been played.
`authorName` | The name of the video's author.
`authorUrl` | URL to the author’s profile or channel.
`authorUsername` | Username of the author.
`thumbnails` | Array of thumbnail URLs for the video.
`title` | The title of the video.
`description` | The description of the video.
`private` | Whether the video is private.
`width` | Width of the video in pixels.
`height` | Height of the video in pixels.
`raw` | Array of raw data for any additional information provided by the API.

## Methods

Method | Description
--- | ---
`getSource()` | Returns the [Source](docs:developers/source) for the video.
`getFormattedDuration()` | Returns a human-readable format of the duration (e.g., `01:23:15`).
`getThumbnail($width = 600)` | Retrieves the URL for the closest available thumbnail to the specified width.
`getEmbedHtml($options = [])` | Returns the HTML embed code for the video, with optional customization parameters.
`getEmbedUrl($options = [])` | Retrieves the direct URL to embed the video with optional customization parameters.
