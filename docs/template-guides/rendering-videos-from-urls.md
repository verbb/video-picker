# Rendering Videos from URLs

Use this workflow when a trusted video URL is stored outside a Video Picker field, such as in a Plain Text field with the handle `videoUrl`. Place the relevant example below in the entry template.

## Resolve a Video Object

For a URL recognised by a configured Source, `getVideoByUrl()` returns a [Video](docs:developers/video) object. Enable and connect the matching Source before using this example:

```twig
{% set video = entry.videoUrl
    ? craft.videoPicker.getVideoByUrl(entry.videoUrl)
    : null %}

{% if video and not video.hasErrors() %}
    <h2>{{ video.title }}</h2>
    {{ video.getEmbedHtml({ width: 640, height: 360, loading: 'lazy' }) | raw }}
{% elseif entry.videoUrl %}
    <p>The video is unavailable.</p>
{% endif %}
```

A supported URL shows the video title and player. An unresolved URL shows the fallback message. Use this approach when you also need the provider metadata described in [Rendering Videos](docs:template-guides/rendering-videos).

Pass `true` as the second argument to `getVideoByUrl()` only when you need to clear the cached result. Fetching live provider data on every page request adds latency and can consume API quota.

## Resolve Generic Embed Data

For an embeddable URL that does not use a configured Source, use the generic embed helpers. They fetch remote metadata on your server. Set an [embed host allowlist](docs:get-started/configuration#restrict-embed-hosts-in-production) to the public providers your site accepts before passing editor-supplied URLs to these helpers. This setting applies to generic discovery, not Source-based lookups.

Check `getEmbedData()` before generating the player:

```twig
{% set videoUrl = entry.videoUrl %}
{% set embed = videoUrl ? craft.videoPicker.getEmbedData(videoUrl) : null %}

{% if embed and embed.error is not defined and embed.code is defined and embed.code %}
    {{ craft.videoPicker.getEmbedHtml(videoUrl, {
        width: 640,
        height: 360,
        loading: 'lazy'
    }) | raw }}
{% elseif videoUrl %}
    <p>The video is unavailable.</p>
{% endif %}
```

`getEmbedData()` returns an array containing `error` when discovery fails, including rejected or unsupported URLs. Successful discovery can also lack embeddable `code`. The check above handles both cases without exposing the error details to visitors.

Do not use a truthy `getEmbedHtml()` or `getEmbedUrl()` result as the failure check: those helpers can produce a blank iframe or a `data:` URL when discovery fails. After the metadata check succeeds, use `getEmbedHtml()` for the complete markup or `getEmbedUrl()` if you need to build the iframe yourself. Apply `raw` only to embed markup from the providers you trust.

Test a supported URL, an empty value and a URL outside your allowlist. The supported video should play; the rejected URL should show the fallback without an iframe. [Cache](docs:feature-tour/cache) explains how to refresh previously discovered metadata.
