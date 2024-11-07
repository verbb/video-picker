# Source
Whenever you're dealing with an source in your template, you're actually working with a `Source` object.

## Attributes

Attribute | Description
--- | ---
`name` | The name of the source.
`handle` | The handle of the source.
`enabled` | Whether the source is enabled or not.
`primaryColor` | The primary brand color of the provider connected.
`icon` | The SVG icon of the source provider connected.
`providerName` | The name of the source provider connected.


## Methods

Method | Description
--- | ---
`isConfigured()` | Whether the source provider has been configured.
`isConnected()` | Whether the source provider has been connected and has a token.
`getToken()` | The access token for a source provider.
`getVideos($criteria = [])` | Retrieves a list of [Video](docs:developers/video) objects based on specified criteria.
`getVideoById($id)` | Fetches a [Video](docs:developers/video) object by its ID from this source.
`getEmbedHtml($videoId, $options = [])` | Returns the HTML embed code for a video, identified by its ID, with optional parameters for customization.
`getEmbedUrl($videoId, $options = [])` | Retrieves the direct URL to embed a video, identified by its ID, with optional parameters for customization.
