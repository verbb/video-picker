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
