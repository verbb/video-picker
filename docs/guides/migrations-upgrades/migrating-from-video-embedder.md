# Migrating from Video Embedder

Move a Video Embedder field to Video Picker while preserving its saved video URLs. The example below keeps a field handle of `featuredVideo` throughout the migration, then uses it to render a Video Picker player.

Video Embedder 1 runs on Craft 3, while Video Picker 2 requires Craft 5. You cannot install both versions on the same Craft site and switch the field directly. Use a Plain Text field to carry the URL through the Craft upgrade, then convert that field to Video Picker.

## Prepare a Migration Copy

Work on a copy of the site with a database backup and the existing Video Embedder plugin still installed. Record the field handles and the providers used by existing entries. Include entries from each site and entry type, along with empty fields, drafts and videos with restricted access.

Video Embedder stores a video URL as text. Confirm that your sample entries contain those URLs before changing fields; custom storage or content transformations need their own conversion.

Keep this copy separate from the live site until the field values, Craft upgrade and templates have been checked. Record the steps that worked so you can repeat them during deployment.

## Field Migration

On Craft 3, open **Settings → Fields**, edit the existing video field and change its type to **Plain Text**. Keep the same handle and field layout placement; do not delete the field or create a replacement. Use no character limit and a text-capable database column.

Save the field, then reopen several entries. You should see the original video URL as editable text. Save one entry and reopen it to confirm the value is retained. If a value changes or disappears, restore the migration copy from its backup and investigate before converting more fields.

Repeat for the remaining Video Embedder fields. Remove calls to `craft.videoEmbedder` from templates and custom code before uninstalling the plugin. While the field is Plain Text, use `entry.featuredVideo` as a URL string; Video Picker object methods are available only after the final conversion.

## Upgrade Craft

After all fields and code no longer depend on Video Embedder, uninstall it on the migration copy. Follow Craft's [Craft 3 to 4 upgrade guide](https://craftcms.com/docs/4.x/upgrade.html), then its [Craft 4 to 5 upgrade guide](https://craftcms.com/docs/5.x/upgrade.html). Check the retained URLs after each stage and resolve the requirements of the site's other plugins before continuing.

Once the site runs Craft 5, follow Video Picker's [Installation & Setup](docs:get-started/installation-setup). Create a Source for each provider used in the saved URLs and confirm that it can resolve a sample video. Video Picker needs these connections even though Video Embedder did not.

Change each retained Plain Text field to **Video Picker**, keeping its handle and field layout placement. Make the matching Sources available to the field, and grant editors **Explore videos**. Start with one field and check its saved selections before converting the others.

## Content Migration

For ordinary URL values, the Plain Text field preserves the content needed by Video Picker; no separate URL-to-object conversion is required. Video Picker resolves the stored URL through a matching Source when reading the field.

Open the sample entries again. Check that titles and previews match the original videos, save an entry and reopen it. An unresolved video usually needs a matching Source, a renewed connection or access to the provider account that owns it. Keep the backup until every provider and field has passed this check.

## Update Templates

After the final field conversion, replace Video Embedder calls with the selected Video Picker object's embed method. In the template that renders the entry:

::: code-group
```twig [Video Embedder 1 on Craft 3]
{% if entry.featuredVideo %}
    {{ craft.videoEmbedder.getEmbedHtml(entry.featuredVideo) | raw }}
{% endif %}
```

```twig [Video Picker 2 on Craft 5]
{% set video = entry.featuredVideo %}

{% if video and not video.hasErrors() %}
    {{ video.getEmbedHtml({ width: 640, height: 360, loading: 'lazy' }) | raw }}
{% elseif video %}
    <p>The video is unavailable.</p>
{% endif %}
```
:::

The field now returns a Video object instead of a URL string. Check pages with a valid video, an empty field and an unavailable video. [Embedding Videos](docs:template-guides/embedding-videos) covers playback options and missing markup; [Rendering Videos from URLs](docs:template-guides/rendering-videos-from-urls) covers URLs that you deliberately keep outside a Video Picker field.
