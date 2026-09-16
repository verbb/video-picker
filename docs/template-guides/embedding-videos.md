# Embedding Videos

Use a selected Video Picker field to add a player to an entry page. The example below assumes a field with the handle `featuredVideo`; place it in the template that renders the entry.

```twig
{% set video = entry.featuredVideo %}
{% set embedHtml = video and not video.hasErrors()
    ? video.getEmbedHtml({
        width: 640,
        height: 360,
        title: video.title,
        loading: 'lazy'
    })
    : null %}

{% if embedHtml %}
    {{ embedHtml | raw }}
{% elseif video %}
    <p>The video is unavailable.</p>
{% endif %}
```

A selected video produces a player. An empty field produces no output, while a video that cannot produce embed markup shows the fallback message. Apply `raw` only to the markup returned by `getEmbedHtml()`; continue to escape titles, descriptions and other content.

## Set Playback Options

The field's **Autoplay**, **Muted**, **Loop** and **Show Controls** settings provide defaults. Pass an option to `getEmbedHtml()` when a particular page needs different behaviour. For example, add `autoplay: false` and `controls: true` to the options above for a player visitors start themselves.

Video Picker translates playback options for the selected provider. Options such as `width`, `height`, `class`, `title` and `loading` become iframe attributes. Provider support and browser playback restrictions still apply, so check the actual player after changing an option.

Test a saved selection, an empty field and an unavailable video. Check the player's size and controls on the rendered page; use your site's CSS to adapt its dimensions to smaller screens.

## Embed a Stored URL

For a URL stored outside a Video Picker field, follow [Rendering Videos from URLs](docs:template-guides/rendering-videos-from-urls). It explains how to resolve the URL, restrict generic embed hosts and check for discovery errors before rendering a player.
