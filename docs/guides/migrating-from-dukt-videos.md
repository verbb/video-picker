# Migrating from Dukt Videos
If your existing site has videos from [Dukt Videos](https://plugins.craftcms.com/videos?craft4), it can be migrated over to Video Picker.

## Field migration
The first step is to change your (Dukt) Video fields to Video Picker. Edit any of your video fields in Craft's control panel in Settings > Fields. Simply switch the field type to Video Picker.

Once completed, you'll need to create new sources for your video provider. Unfortunately, the two plugins' OAuth handling is incompatible, and cannot be automatically migrated over. Ensure that you connect to your appropriate source.

## Content migration
There's no need to migrate your content for this field change, as Dukt Videos and Video Picker use the same data format. So long as you've switch field types, and setup your sources, you should be ready to go with Video Picker.