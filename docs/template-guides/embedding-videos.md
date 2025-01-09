# Embedding Videos
You can also use Video Picker to embed **any** video, thanks to our usage of the [embed/embed](https://github.com/oscarotero/Embed) package.

## URL
By providing the URL to a video or embeddable media, you'll be returned with the embeddable URL. This often differs from the URL of a video, which doesn't always allow being embedded in HTML.

```twig
{% set url = craft.videoPicker.getEmbedUrl('https://www.youtube.com/watch?v=jfKfPfyJRdk') %}

{# Example output #}
{# https://www.youtube.com/embed/jfKfPfyJRdk?feature=oembed #}
```

You can also pass a second argument with a list of query params to append to the URL.

```twig
{% set url = craft.videoPicker.getEmbedUrl('https://www.youtube.com/watch?v=jfKfPfyJRdk', { autoplay: 1, rel: 0, theme: 'dark' }) %}

{# Example output #}
{# https://www.youtube.com/embed/jfKfPfyJRdk?feature=oembed&autoplay=1&rel=0&theme=dark #}
```

## Embed HTML
Rather than just the URL to the embeddable media, you can opt to generate the HTML to render it.

```twig
{% set html = craft.videoPicker.getEmbedHtml('https://www.youtube.com/watch?v=jfKfPfyJRdk', { autoplay: 1 }) %}

{{ html | raw }}

{# Example output #}
{# <iframe src="https://www.youtube.com/embed/jfKfPfyJRdk?feature=oembed" width="200" height="150" title="lofi hip hop radio 📚 beats to relax/study to" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen autoplay="1"></iframe> #}
```

You can also pass a second argument with a list of HTML attributes to add to the `<iframe>` element.

## Embed Data
You can also grab the entire embed data to use in templates.

```twig
{% set embedData = craft.videoPicker.getEmbedData('https://www.youtube.com/watch?v=jfKfPfyJRdk') %}

{# Example output #}
{# [
    "title" => "lofi hip hop radio 📚 beats to relax/study to"
    "description" => "🎼 | Listen on Spotify, Apple music and more→ https://fanlink.tv/lofigirl-music 🎄 | New Christmas Radio is out now!→ https://www.youtube.com/watch?v=pfiCN..."
    "url" => "https://www.youtube.com/watch?v=jfKfPfyJRdk"
    "code" => "<iframe width="200" height="150" src="https://www.youtube.com/embed/jfKfPfyJRdk?feature=oembed" frameborder="0" allow="accelerometer; autoplay; clipboard-write; ▶"
    "authorName" => "Lofi Girl"
    "authorUrl" => "https://www.youtube.com/@LofiGirl"
    "providerName" => "YouTube"
    "providerUrl" => "https://www.youtube.com/"
    "icon" => "https://www.youtube.com/s/desktop/b5305900/img/logos/favicon_144x144.png"
    "favicon" => "https://www.youtube.com/s/desktop/b5305900/img/logos/favicon.ico"
    "publishedTime" => "2022-07-12T05:12:29-07:00"
] #}
```
