# Sources
A Source allows you to connect to a video provider to fetch Videos from various platforms. Once a Source is set up, you can retrieve video data, including titles, descriptions, thumbnails, and playback details.

## Provider Settings
Each provider will be different, but almost all require OAuth authentication. Create a Source and follow the documentation for the provider to get your Client ID/Secret credentials. Once configured, connect to the provider, going through the OAuth handshake to retrieve a token.

## Fetching Videos
To fetch a video from a source, you'll need to provide a URL for the video, and use `source.getVideoByUrl(url)`.

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

You can also use the more convenient `craft.videoPicker.getVideoByUrl(url)` which does the same thing, but automatically determins which source the video is for. This can be more helpful when you don't know which source a video belongs to in advance.

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
Check out our guide on [Rendering Videos](docs:template-guides/rendering-videos) for more.
:::
