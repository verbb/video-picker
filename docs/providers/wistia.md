# Wistia
Follow these steps to configure Wistia for Video Picker.

## Connecting to Wistia

### Connect to the Wistia API
1. Go to your <a href="https://wistia.com" target="_blank">Wistia</a> account.
1. In the top-right profile dropdown, select **Settings**.
1. Navigate to **API Home**.
1. Click the **New Token** button.
1. Give the token a **Nickname** and select at least **Read all data** for **Permissions**.
1. Copy the **Access Token** and paste it into the field in Video Picker.
1. Save the source, then click **Connect** to verify the credentials.

### Explorer and URLs

After connecting, open a Video Picker field and browse **All Medias** or a project, or search for a known video. Select a result, save the entry and check its player.

You can paste URLs from `*.wistia.com/medias/…` or `fast.wistia.net/embed/iframe/…`. The preview uses the project's name for the owner, falling back to the account name, and displays descriptions as plain text.
