# Selecting Video Sources

Find a Source by its configured handle when a custom template must resolve a known provider video outside a field. Check that the Source is usable before making the provider request:

```twig
{% set source = craft.videoPicker.getSourceByHandle('youTube') %}
{% set video = source and source.enabled and source.isUsable() ? source.getVideoById('jfKfPfyJRdk') : null %}

{% if video and not video.hasErrors() %}
    <h2>{{ video.title }}</h2>
    {{ video.getEmbedHtml({ width: 640, height: 360 }) | raw }}
{% endif %}
```

The provider video ID format differs by Source. Use a saved Video Picker field or `getVideoByUrl()` when you have a full URL instead.

## Choose a Source Collection

For a template that lets visitors choose a provider, start with `craft.videoPicker.getAllEnabledSources()` and show only Sources for which `isUsable()` is true. A Source with saved settings may still need its OAuth connection renewed.

The [Source reference](docs:developers/source#finding-sources) explains the other lookup methods. Use Source names and handles for presentation; keep credentials and raw settings out of public output.
