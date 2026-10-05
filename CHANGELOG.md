# Changelog

## Unreleased

### Changed
- Require Verbb Base 3.0.20 or later so shared control-panel layouts use the current asset bundle namespace. ([verbb-base#3](https://github.com/verbb/verbb-base/issues/3))

## 2.1.3 - 2026-10-05

### Fixed
- Fixed source credential values being visible to users without credential-management permission.

## 2.1.2 - 2026-10-02

### Changed
- Updated the required version of `verbb/base` to 3.0.19.

### Fixed
- Fixed an error when upgrading sites with existing selected-video cache rows.

### Removed
- Removed the unused legacy `verbb\videopicker\assetbundles\VideoPickerAsset` compatibility wrapper.

## 2.1.1 - 2026-09-30

### Changed
- Keep OAuth callback redirects literal.
- Authorize OAuth connection management.
- Validate OAuth callback transactions.

## 2.1.0 - 2026-09-29

### Added
- Add **Bunny Stream**, **Cloudflare Stream**, **Dailymotion**, **Mux**, **Sprout Video** and **Wistia** sources.
- Add field settings for available sources, URL input, search, public-only videos, videos per page, minimum and maximum duration, video sort order and placeholder text.
- Add source setting **Available Fields** to control what fields can browse the source.
- Add field-level playback defaults for autoplay, mute, looping and controls, with per-video Twig options taking precedence.
- Add configurable provider, search, embed-error and selected-video cache durations, embed allowed domains and high-resolution embed images.
- Add YouTube **Privacy Enhanced Mode** for `youtube-nocookie.com` embeds.
- Add an optional provider icon, private-video indicator and provider link to selected-video previews.
- Add **Explore videos** (`videoPicker-explore`) permission for field explorer and URL lookup requests, separate from **Sources**.
- Add **Manage source credentials and connections** permission. Existing non-admin Source managers must be granted this permission to continue managing credentials and OAuth connections.

### Changed
- Route plugin settings through the plugin’s authorized settings controller.
- Rebuild the field input and explorer with [Plugin Kit](https://docs.verbb.io/plugin-kit/web/) web components, including responsive layouts and improved keyboard and screen-reader support.
- Revalidate expired selected-video metadata on read while retaining the last successful data when refreshes fail.
- Improve explorer performance and resilience with cached provider searches, complete collection pagination and isolated provider failures.
- Share cached videos by URL across compatible same-provider sources and rebind them to an allowed source when used by a field.
- Split credential and OAuth source authentication into `CredentialsSource` and `OAuthSource`; `SourceInterface` no longer requires `getOAuthProviderClass()`.
- Separate provider embed options from iframe attributes.
- Change GraphQL `plays` to `Float` to support play counts above the 32-bit integer limit.
- Require Embed 4 and update Auth, Plugin Kit and lodash to patched releases.
- Expand the field, provider, embed, Source API, GraphQL and migration documentation.

### Fixed
- Fixed a high-severity cross-site scripting vulnerability.
- Fixed a high-severity server-side request forgery vulnerability.
- Fixed two moderate-severity cross-site scripting vulnerabilities.
- Fixed a moderate-severity denial-of-service vulnerability.
- Fixed two moderate-severity information disclosure vulnerabilities.
- Fixed a moderate-severity credential disclosure vulnerability.
- Fixed a low-severity server-side request forgery vulnerability.
- Fixed a low-severity broken access control vulnerability.
- Fixed a low-severity excessive OAuth permissions vulnerability.
- Fixed OAuth callback validation and redirect handling.
- Fixed YouTube explorer searches reusing incompatible playlist pagination parameters. ([#6](https://github.com/verbb/video-picker/issues/6))
- Fixed literal emoji shortcodes changing when video metadata is cached.
- Fixed Refresh reusing stale video metadata for alias URLs or stopping before a later account that can access the video.
- Fixed cached video embeds becoming unavailable after changing a source handle.
- Fixed unlisted Vimeo embeds omitting their privacy hash.
- Fixed generic embeds dropping URL query parameters or named image properties, or rejecting valid public URLs when peer-IP statistics are unavailable.
- Fixed empty YouTube searches and playlists causing an API error.
- Fixed source credential changes reusing stale provider data.
- Fixed duplicate source names and handles not showing validation errors.
- Fixed deleting a source from its edit page not completing successfully.
- Fixed invalid video URLs silently clearing the selected video when saving content.
- Fixed GraphQL `ArrayType` values not returning their serialized arrays.
- Fixed element thumbnail `srcset` output containing an empty 2× candidate.

### Removed
- Removed the `raw` field from GraphQL video types. Remove it from GraphQL queries and persisted operations before upgrading.

### Fixed
- Fixed OAuth callback transaction validation.
- Fixed authorization for connecting and disconnecting OAuth sources.
- Fixed OAuth callback redirects being evaluated as Twig templates.

## 2.0.11 - 2026-09-13

### Changed
- Normalize plugin settings.

## 2.0.10 - 2026-07-15

### Added
- Add guides to docs.

## 2.0.9 - 2026-05-03

### Changed
- Bump `verbb/auth` to allow `firebase/php-jwt` 7.x.

## 2.0.8 - 2026-03-15

### Changed
- Refactor Video Picker field bootstrapping to DOM auto-mount.

## 2.0.7 - 2026-02-13

### Fixed
- Fix refreshing video’s not clearing local cache data.

## 2.0.6 - 2026-02-07

### Added
- Add “EU/UK Browsing” setting for Vimeo, due to region restrictions on browsing videos.

### Fixed
- Fix a redirect error when connecting to a source in the control panel.
- Fix an error when creating a new source.

## 2.0.5 - 2025-08-13

### Added
- Add “Show Explorer” and “Show Preview” field settings.
- Add `duration8601` to video data.
- Add the ability to use Video Picker Videos in element cards (thumbnails and URLs).

## 2.0.4 - 2025-07-18

### Changed
- Update English translations.

## 2.0.3 - 2025-05-20

### Fixed
- Fix local cache not working for multiple sources.
- Fix some errors when processing Vimeo videos.
- Fix some errors when processing YouTube videos.
- Fix Utility and Video Picker field icons.

## 2.0.2 - 2025-05-01

### Changed
- Fields can now enter video URLs even when no sources exist yet.

### Fixed
- Fix video URLs not being trimmed correctly for whitespace.
- Fix an error when viewing paginated videos while searching.

## 2.0.1 - 2025-03-04

### Changed
- Improve API performance by adding an extra level of local-caching of requests for videos and metadata.

### Fixed
- Fix error handling around accounts with no videos to browse.
- Fix YouTube throwing an error when no playlists exist for the user.
- Fix source cache not clearing when disconnecting.
- Fix a button layout issue on Craft 4.14+.

## 2.0.0 - 2025-01-14

### Changed
- Now requires Craft 5.0+.

## 1.0.7 - 2026-07-15

### Added
- Add guides to docs.

## 1.0.6 - 2026-05-03

### Changed
- Bump `verbb/auth` to allow `firebase/php-jwt` 7.x.

## 1.0.5 - 2025-08-12

### Added
- Add “Show Explorer” and “Show Preview” field settings.
- Add `duration8601` to video data.

## 1.0.4 - 2025-07-18

### Changed
- Update English translations.

## 1.0.3 - 2025-05-20

### Fixed
- Fix local cache not working for multiple sources.
- Fix some errors when processing Vimeo videos.
- Fix some errors when processing YouTube videos.

## 1.0.2 - 2025-05-01

### Changed
- Fields can now enter video URLs even when no sources exist yet.

### Fixed
- Fix video URLs not being trimmed correctly for whitespace.
- Fix an error when viewing paginated videos while searching.

## 1.0.1 - 2025-03-04

### Changed
- Improve API performance by adding an extra level of local-caching of requests for videos and metadata.

### Fixed
- Fix error handling around accounts with no videos to browse.
- Fix YouTube throwing an error when no playlists exist for the user.
- Fix source cache not clearing when disconnecting.
- Fix a button layout issue on Craft 4.14+.

## 1.0.0 - 2025-01-14

### Added
- Initial release
