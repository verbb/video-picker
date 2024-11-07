# Rendering Videos
There are two main ways to render videos in your template. If you're using the Video Picker field, you'll be using that, but you can also render videos without the field.

## From Field
When you get the value of a Video Picker field in the context of an element, you'll be dealing with a [Video](docs:developers/video) object, or a `null` value.

For example, your Video Picker field might have the handle `video`, and you've attached it to an Entry.

```twig
{% if entry.video %}
    ID: {{ entry.video.id }}<br>
    Title: {{ entry.video.title }}<br>
    Description: {{ entry.video.description }}<br>
    Thumbnail: <img src="{{ entry.video.getThumbnail() }}" alt="{{ entry.video.title }}">
    Embed: {{ entry.video.getEmbedHtml({ width: 500, height: 300 }) | raw }}
{% endif %}
```

It's always a good idea to check if the field has a value first, before outputting anything about it.

## Without Field
You can also do much the same thing without a field, directly in your Twig templates. Supply a valid URL for one of your connected and enabled sources and call `craft.videoPicker.getVideoByUrl(url)`. This will return a [Video](docs:developers/video) object.

Note that this will **only** work for sources that are connected and enabled. For example, you can't put the URL of any video provider in `getVideoByUrl()`, as it has to be with a provider that Video Picker supports.

```twig
{% set video = craft.videoPicker.getVideoByUrl('https://www.youtube.com/watch?v=jfKfPfyJRdk') %}

{% if video %}
    ID: {{ video.id }}<br>
    Title: {{ video.title }}<br>
    Description: {{ video.description }}<br>
    Thumbnail: <img src="{{ video.getThumbnail() }}" alt="{{ video.title }}">
    Embed: {{ video.getEmbedHtml({ width: 500, height: 300 }) | raw }}
{% endif %}
```
