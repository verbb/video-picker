# Permissions

Video Picker's permissions separate the editors who choose videos in a field from the people who set up your video providers. An editor adding a video to an entry only needs to browse your sources, a site builder might decide which fields can use each source, and connecting a provider account stays with whoever holds its API keys. Set them in **Settings → Users → User Groups**, or on an individual user's **Permissions** tab.

**Access Video Picker** is listed under **General**, with Craft's other permissions for reaching each plugin's section. The others are listed under the **Video Picker** heading.

| Permission | What It Allows |
| --- | --- |
| Access Video Picker | Reach the Video Picker section of the control panel. |
| Explore videos | Use **Browse videos…** in a Video Picker field to browse and search an allowed source, and paste a video's URL into the field to look it up. |
| Sources | Open **Sources** to create, edit, reorder and delete [sources](docs:feature-tour/sources), and change their non-credential settings, such as **Available Fields**. |
| Manage source credentials and connections | Enter and change a source's API keys and other credentials, change its provider, connect or disconnect its account, and check its connection. |

**Explore videos** is for editors and works inside the field wherever they edit content, so it doesn't need **Access Video Picker**. **Sources** opens a screen in the plugin's section, so it needs **Access Video Picker** too.

**Manage source credentials and connections** is nested under **Sources** and only applies to the source screens. Without it, someone with **Sources** sees credentials redacted and can't add or change them, switch a source to a different provider, or add or change environment variable and alias references in a source's settings. They can still enable or disable a source, choose its **Available Fields** and delete it.

Admins have every permission, and Video Picker's settings are only available to admins. Keep **Manage source credentials and connections** for the people who manage your provider accounts, because it controls which account each source reads videos from.
