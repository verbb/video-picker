# Videos

Video Picker provides a field for picking and embedding videos — paste a URL, or browse a connected Source.

![Video Picker field with URL input and selected video preview](/_screenshots/feature-tour/videos.png)

## Field settings

Configure these on the field under **Settings → Fields**:

- **Allow URL Input** — editors can paste a video URL.
- **Allow Search** — show search in the explorer.
- **Public Videos Only** — hide private videos in the explorer (where the provider supports it).
- **Videos Per Page** — optional override of the plugin default for explorer pagination.
- **Min / Max Duration** — limit which videos can be selected.
- **Video Sort** — sort order when browsing.
- **Placeholder** — empty-state text for the URL input.
- **Show Explorer / Show Preview / Show Provider Icon** — control chrome on the field.
- **Embed defaults** — Autoplay, Muted, Loop, and Show Controls applied when you embed the selected video. Twig can still override these per call (see [Rendering Videos](docs:template-guides/rendering-videos)).

Sources must be **enabled**, **connected**, and allow this field under the Source’s **Available Fields** setting (see [Sources](docs:feature-tour/sources)).

## Explorer

Browse folders and videos from a connected Source. Select the Source you want (assuming you’ve created and connected it), then pick a video.

![Video explorer browsing a connected Vimeo source](/_screenshots/feature-tour/explorer.png)

Once selected, you’ll see a preview in the field. You can go ahead and [render the video](docs:template-guides/rendering-videos).

## Direct URL

You don’t have to use the explorer — paste a direct URL and Video Picker will look up the video, fetch data, and show a preview. URLs only work for Sources that are connected and enabled, and whose provider matches the URL.
