# Migrating from Video Embedder
If your existing site uses [Video Embedder](https://github.com/verbb/craft-videoembedder), you can use Video Picker as a replacement.

## Field migration
The first step is to change your (Video Embedder) Video fields to Video Picker. Navigate to Settings > Fields in the Craft control panel, and select any of your Video Embedder Videos fields to edit. Simply switch the field type to "Video Picker".

After switching the type of your fields, you'll then need to create new Sources for your video provider. While Video Embedder didn't require a source configured for your videos, Video Picker does, in order to get accurate metadata on your videos.

Navigate to Video Picker > Sources in the Craft control panel to setup the sources for your YouTube or Vimeo videos (or both)>

## Content migration
There's no need to migrate your content for this field change, as Video Embedder Videos and Video Picker use the same data format. So long as you've switched field types, and setup your sources, you should be ready to go with Video Picker.

## Update templates
If you've used `getEmbedUrl` in your templates for Video Picker to get embed content, you can quickly swap that out with Video Picker. Take a look at our [Embedding Videos](docs:template-guides/embedding-videos) page.

```twig
{# Video Embedder #}
{% set embed = craft.videoEmbedder.getEmbedUrl('https://www.youtube.com/watch?v=jfKfPfyJRdk') %}

{# Video Picker #}
{% set embed = craft.videoPicker.getEmbedUrl('https://www.youtube.com/watch?v=jfKfPfyJRdk') %}
```

:::tip
We're happy to gift anyone a **free** license for any site they have with Video Embedder installed, to ease the migration to Craft 4 or 5 with Video Picker. Just [get in touch](https://verbb.io/contact) with a screenshot of your current site with Video Embedder installed as proof of upgrade.
:::