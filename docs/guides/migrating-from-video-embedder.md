# Migrating from Video Embedder
If your existing site uses [Video Embedder](https://github.com/verbb/craft-videoembedder), you can use Video Picker as a replacement.

## Update templates
While Video Embedder doesn't have a field for picking videos, it does use Twig tags to embed videos. Fortunately, these are widely compatible with Video Picker. Take a look at our [Embedding Videos](docs:template-guides/embedding-videos) page.

```twig
{# Video Embedder #}
{% set embed = craft.videoEmbedder.getEmbedUrl('https://www.youtube.com/watch?v=jfKfPfyJRdk') %}

{# Video Picker #}
{% set embed = craft.videoPicker.getEmbedUrl('https://www.youtube.com/watch?v=jfKfPfyJRdk') %}
```

:::tip
We're happy to gift anyone a **free** license for any site they have with Video Embedder installed, to ease the migration to Craft 4 or 5 with Video Picker. Just [get in touch](https://verbb.io/contact) with a screenshot of your current site with Video Embedder installed as proof of upgrade.
:::