# Rendering Videos

A populated Video Picker field returns a [Video](docs:developers/video) object. Use its details to build a video card, or follow [Embedding Videos](docs:template-guides/embedding-videos) to show a player.

## Render a Field Selection

This example assumes an entry field with the handle `featuredVideo`. Place it in the template that renders the entry. It shows a linked thumbnail and title, and a duration when the provider supplies one:

```twig
{% set video = entry.featuredVideo %}

{% if video and not video.hasErrors() %}
    {% set thumbnail = video.getThumbnail(640) %}

    <article class="video-card">
        <a href="{{ video.url }}">
            {% if thumbnail %}
                <img src="{{ thumbnail }}" alt="" loading="lazy">
            {% endif %}
            <h2>{{ video.title }}</h2>
        </a>

        {% if video.duration %}
            <p>Duration: {{ video.formattedDuration }}</p>
        {% endif %}
    </article>
{% endif %}
```

The image has an empty `alt` because the linked title names the same destination. A missing thumbnail leaves a text link, and an empty field or failed video lookup omits the card. Save an entry with a video and check the page, then test an entry with no selection.

Keep Twig's escaping enabled for titles, descriptions and URLs. The [Video reference](docs:developers/video) lists the available metadata and explains which values can be absent.

## Render a Supported URL

If your URL is stored in a Plain Text field instead of a Video Picker field, use [Rendering Videos from URLs](docs:template-guides/rendering-videos-from-urls). That guide covers both configured Sources and generic embed discovery.
