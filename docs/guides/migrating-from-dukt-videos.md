# Migrating from Dukt Videos
If your existing site has videos from [Dukt Videos](https://plugins.craftcms.com/videos?craft4), it can be migrated over to Video Picker.

## Field migration
The first step is to change your (Dukt) Video fields to Video Picker. Navigate to Settings > Fields in the Craft control panel, and select any of your Dukt Videos fields to edit. Simply switch the field type to "Video Picker".

After switching the type of your fields, you'll then need to create new Sources for your video provider. Navigate to Video Picker > Sources in the Craft control panel to setup the same sources you had in Dukt Videos (YouTube, Vimeo or both). Unfortunately, the two plugins' OAuth handling is incompatible, and cannot be automatically migrated over, so this will be a manual step to re-authorize access to these providers. Ensure that you connect to your appropriate source.

## Content migration
There's no need to migrate your content for this field change, as Dukt Videos and Video Picker use the same data format. So long as you've switched field types, and setup your sources, you should be ready to go with Video Picker.