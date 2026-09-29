# Migrating from Dukt Videos

Move single-video fields from Dukt Videos to Video Picker and reconnect the accounts that supply their videos. The migration keeps the original field handles and saved URLs, then checks the resulting Video Picker selections and templates.

Dukt Videos 2 targets Craft 3 and Dukt Videos 3 targets Craft 4. Video Picker 2 requires Craft 5. For those starting versions, carry the URL through the Craft upgrade in a Plain Text field so the upgrade does not depend on Dukt Videos remaining installed.

## Prepare a Migration Copy

Back up the database and work on a copy of the site with Dukt Videos still installed. Record the video field handles, connected provider accounts and several sample entries for each field. Include empty selections, restricted videos, drafts and entries from each site.

These Dukt Videos fields store a single video URL. Verify your saved values before starting; customised storage needs a separate conversion. Keep the database backup and record each successful step for the eventual deployment.

## Field Migration

Open **Settings → Fields** and change one existing Dukt Videos field to **Plain Text**, retaining its handle and field layout placement. Use no character limit and a text-capable database column. Do not delete the field or replace it with a new one.

Open your sample entries and confirm that the original video URL appears as text. Save one entry and reopen it before converting the other fields. If any value is lost or changed, restore the migration copy from the backup and investigate before continuing.

Update templates and custom code that depend on Dukt Videos before uninstalling it. During this intermediate stage, the field returns a URL string, so templates must not call video object methods on it.

## Upgrade and Connect Video Picker

Once all fields and code are independent of Dukt Videos, uninstall it on the migration copy. If you start on Craft 3, follow the [Craft 3 to 4 upgrade guide](https://craftcms.com/docs/4.x/upgrade.html) first. Then follow the [Craft 4 to 5 upgrade guide](https://craftcms.com/docs/5.x/upgrade.html), checking the retained URLs after each stage.

On Craft 5, install Video Picker using [Installation & Setup](docs:get-started/installation-setup). Create a Source for each provider used by existing content. OAuth connections do not transfer between plugins: authorise each Source with the account that owns or can access the videos.

Change one retained Plain Text field to **Video Picker**, keeping the same handle. Make its Sources available under **Available Fields** and grant editors **Explore videos**. Confirm the field resolves existing videos before converting the remaining fields.

## Content Migration

The stored URL supplies the value Video Picker needs; there is no separate content conversion for ordinary single-video values. On each sample entry, check the video's title and preview, save the entry and reopen it. Verify every provider and field before removing the migration backup.

## Check the Templates

Use the converted field's Video object in the entry template. For a field with the handle `featuredVideo`:

```twig
{% set video = entry.featuredVideo %}

{% if video and not video.hasErrors() %}
    <h2>{{ video.title }}</h2>
    {{ video.getEmbedHtml({ width: 640, height: 360, loading: 'lazy' }) | raw }}
{% elseif video %}
    <p>The video is unavailable.</p>
{% endif %}
```

Check a valid selection, an empty field and a video the connected account cannot access. Review all templates and custom code for remaining Dukt Videos calls; matching field handles alone do not guarantee that the APIs are interchangeable. [Rendering Videos](docs:template-guides/rendering-videos) covers metadata and [Embedding Videos](docs:template-guides/embedding-videos) covers player options.
