# Rendering Videos from URLs

Use this workflow when a trusted video URL is stored outside a Video Picker field, such as in a Plain Text field with the handle `videoUrl`. Place the relevant example below in the entry template.

## Resolve a Video Object

For a URL recognised by a configured Source, `getVideoByUrl()` returns a [Video](docs:reference/video) object. Enable and connect the matching Source before using this example:

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

Check the metadata rather than the result of `getEmbedHtml()`. When discovery fails it returns an empty string, but when a provider supplies a widget instead of an iframe it wraps that markup in a sandboxed iframe, so a non-empty result doesn't prove a working player. Apply `raw` only to embed markup from the providers you trust.

## Building Your Own Iframe

To write the iframe markup yourself, for example to wrap it in your own responsive container, use `craft.videoPicker.getEmbedUrl(url, params)` to get the player URL on its own. It takes the video URL and an optional hash of query parameters, which are added to the player URL as they are, so use the provider's own parameter names. It returns `null` when discovery fails or the provider's player isn't an iframe, so check the result before outputting it:

```twig
{% set videoUrl = entry.videoUrl %}
{% set playerUrl = videoUrl ? craft.videoPicker.getEmbedUrl(videoUrl, { rel: 0 }) : null %}

{% if playerUrl %}
    <div class="video-wrapper">
        <iframe src="{{ playerUrl }}" title="{{ entry.title }}" loading="lazy" allowfullscreen></iframe>
    </div>
{% elseif videoUrl %}
    <p>The video is unavailable.</p>
{% endif %}
```

For a YouTube URL, this outputs the YouTube player URL with `rel=0` added. Unlike `getEmbedHtml()`, the parameters aren't iframe attributes, so set the size and other attributes on your own `<iframe>` tag. The same embed host allowlist applies, because the URL comes from the same discovery.

Test a supported URL, an empty value and a URL outside your allowlist. The supported video should play, and the rejected URL should show the fallback without an iframe. [Cache](docs:feature-tour/cache) explains how to refresh previously discovered metadata.
