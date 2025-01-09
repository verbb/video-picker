# Available Variables
The following methods are available to call in your Twig templates:

### `craft.videoPicker.getAllSources()`
Returns a collection of [Source](docs:developers/source) objects.

### `craft.videoPicker.getAllEnabledSources()`
Returns a collection of enabled [Source](docs:developers/source) objects.

### `craft.videoPicker.getAllConfiguredSources()`
Returns a collection of configured [Source](docs:developers/source) objects.

### `craft.videoPicker.getSourceById(id)`
Returns a [Source](docs:developers/source) object by its ID.

### `craft.videoPicker.getSourceByHandle(handle)`
Returns a [Source](docs:developers/source) object by its handle.

### `craft.videoPicker.getVideoByUrl(videoUrl, clearCache = false)`
Returns a [Video](docs:developers/video) object for the provided URL. You can also opt to fetch live data with `clearCache = true`, but this will impact performance.

### `craft.videoPicker.getEmbedUrl(url, params)`
Returns the embed URL for any embeddable media. `params` can be used to add additional query string parameters to the URL.

### `craft.videoPicker.getEmbedHtml(url, params)`
Returns the embed iframe HTML for any embeddable media. `params` can be used to add additional attributes to the `<iframe>` element.

### `craft.videoPicker.getEmbedData(url)`
Returns the full embed data for any embeddable media.
