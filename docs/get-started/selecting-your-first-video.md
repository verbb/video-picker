# Selecting Your First Video

You'll connect a video source, choose a video on an entry and embed it on the site. Start with a supported provider account and a video you are allowed to display.

Create a [Source](docs:feature-tour/sources), complete its provider setup and connect it. Create a Video Picker field called Featured Video with the handle `featuredVideo`, then add it to an entry type's field layout. Enable the source for that field using **Available Fields**. Editors need the **Explore videos** permission to browse and resolve videos.

Open an entry, choose the connected source in the explorer and select the test video. Save the entry after its preview appears. In the entry's Twig template, add:

```twig
{% if entry.featuredVideo %}
    {{ entry.featuredVideo.getEmbedHtml({ width: 640, height: 360 }) | raw }}
{% endif %}
```

Open the public page and play the video. Check that the provider permits embedding on your site's domain; a control-panel preview alone does not establish public playback permissions. Remove the selection and confirm the empty field produces no embed.

[Rendering Videos](docs:template-guides/rendering-videos) covers thumbnails, metadata and embed options once the basic field-to-page path works.
