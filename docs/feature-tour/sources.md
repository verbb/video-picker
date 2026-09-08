# Sources

A Source connects Video Picker to a video provider. Once set up, you can browse videos in the field explorer or resolve them by URL.

<img src="/_screenshots/feature-tour/sources.png" width="727" alt="Video Picker Sources index with connected providers." />

## Provider settings

Each provider is different. OAuth Sources need Client ID / Secret and a Connect handshake; credential Sources use API tokens you paste into the Source settings. Create a Source and follow the [provider docs](docs:providers/youtube) for your platform.

## Available Fields

On each Source you can limit which Video Picker fields are allowed to browse it (**Available Fields**). Leave empty to allow all fields. The field must still use a Source that is enabled and connected.

## Fetching videos

```twig
{# Get the source by its handle #}
{% set source = craft.videoPicker.getSourceByHandle('mySourceHandle') %}

{% for video in source.getVideos() %}
    ID: {{ video.id }}<br>
    Title: {{ video.title }}<br>
    Description: {{ video.description }}<br>
    Thumbnail: <img src="{{ video.getThumbnail() }}" alt="{{ video.title }}">
{% endfor %}
```

You can also use `craft.videoPicker.getVideoByUrl(url)`, which picks the right Source for the URL automatically:

```twig
{% set video = craft.videoPicker.getVideoByUrl('http://provider.com/video/4b6b2kk32b5h') %}

{% if video %}
    ID: {{ video.id }}<br>
    Title: {{ video.title }}<br>
    Description: {{ video.description }}<br>
    Thumbnail: <img src="{{ video.getThumbnail() }}" alt="{{ video.title }}">
{% endif %}
```

:::tip
Check out [Rendering Videos](docs:template-guides/rendering-videos) for more.
:::
