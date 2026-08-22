# Migrating from Dukt Videos
If your existing site has videos from [Dukt Videos](https://plugins.craftcms.com/videos?craft4), it can be migrated over to Video Picker.

## Field migration
The first step is to change your (Dukt) Video fields to Video Picker. Navigate to Settings > Fields in the Craft control panel, and select any of your Dukt Videos fields to edit. Simply switch the field type to "Video Picker".

After switching the type of your fields, you'll then need to create new Sources for your video providers. Navigate to Video Picker > Sources in the Craft control panel to set up the same OAuth sources you had in Dukt Videos, plus any credential-based sources you need. Unfortunately, the two plugins' OAuth handling is incompatible and cannot be migrated automatically, so re-authorize OAuth sources manually. Credential sources use API tokens from the provider dashboard.

## Content migration
There's no need to migrate your content for this field change, as Dukt Videos and Video Picker use the same data format. So long as you've switched field types, and setup your sources, you should be ready to go with Video Picker.
