# Changelog

## Unreleased

### Added
- User permission **Explore videos** (`videoPicker-explore`) for field explorer / URL resolve AJAX (separate from **Sources**).
- Add **Bunny Stream**, **Cloudflare Stream**, **Dailymotion**, **Mux**, **Sprout Video** and **Wistia** sources.
- Add field setting for optional input placeholder text when no video is selected.
- Add `CredentialsSource` and `OAuthSource` split to cater for different authentication methods of providers.
- Add source setting **Available Fields** to control what fields can browse the source.
- Expose provider/search/embed cache durations, hi-res embed image toggle, and embed allowed domains in plugin settings (advanced embed client arrays stay config-only).
- Add field selection policies: Allow URL Input, Allow Search, Public Videos Only, optional Videos Per Page override, min/max duration, and per-page Video Sort (no default source/collection — source order remains global).
- Add field embed intent defaults (Autoplay, Muted, Loop, Show Controls) applied per video provider; Twig options still override.
- Add YouTube source **Privacy Enhanced Mode** (`youtube-nocookie.com` embeds).
- Add **Video Cache Duration** (default 7 days, minimum 1 hour) for selected-video DB metadata with stale-while-revalidate and cache status (`ok` / `stale` / `unavailable`).
- Add **Embed Error Cache Duration** (default 60 seconds; `0` disables) so failed Twig embed crawls are not retried every request.

### Changed
- GraphQL `plays` uses `Float` to support video play counts above the 32-bit integer limit.
- Require Embed 4 and update Plugin Kit and lodash dependencies to their patched releases.
- Field input rebuilt on [Plugin Kit](https://docs.verbb.io/plugin-kit/web/) (web components).
- `SourceInterface` no longer requires `getOAuthProviderClass()` — OAuth is confined to `OAuthSource`.
- Source embeds split intent/query params from iframe attributes (no longer merge query options onto the iframe tag).
- Selected-video field preview can show the provider brand icon on the thumbnail via **Show Provider Icon** (off by default), plus a private lock beside the title when needed and an explicit open-on-provider action.
- Selected-video DB rows are no longer indefinite: expired snapshots revalidate on read and keep the last good data if refresh fails.
- Clarify field and provider setup, URL embed error handling, Source responses and migration paths in the documentation, and document the complete GraphQL video type.
- Align documentation filenames with page titles and update internal links.

### Fixed
- Resolve and refresh videos through later allowed accounts when an earlier account cannot access them.
- Allow clearing browse-only fields when the preview is hidden or unavailable.
- Resolve custom provider URLs when committing the field input.
- Preserve cached video embeds when changing a source handle.
- Keep the selected video preview responsive when URL lookups overlap or a video is chosen from the explorer.
- Preserve the current collection and search when refreshing the explorer, and clear results when its sources are no longer available.
- Preserve query parameters when extracting generic embed URLs from HTML.
- Handle empty YouTube searches and playlists without an API error.
- Load all pages of provider folders, playlists and collections in the explorer.
- Show validation errors for duplicate source names and handles.
- Ignore obsolete explorer responses after changing sources, collections or searches, or closing the dialog.
- Preserve video cache expiry and refresh errors when saving content without fetching new metadata.
- Preserve named image properties in generic embed data.
- Fix browsing additional pages of Mux videos.
- Fix deleting a source from its edit page.
- Preserve an empty Available Fields selection when saving a source.
- Reject invalid video URLs when saving content instead of silently clearing the selected video.
- Source setup links use kebab-cased provider slugs so multiword provider guides resolve correctly.
- Fixed a high-severity cross-site scripting vulnerability.
- Fixed a high-severity server-side request forgery vulnerability.
- Fixed a low-severity broken access control vulnerability.
- Fixed a moderate-severity information disclosure vulnerability.
- Generic Twig embed helpers no longer reject valid public URLs because Guzzle omitted peer-IP statistics in streaming mode.
- Cached video rows are shared by URL across same-provider sources; field scoping gates on provider URL match and rebinds `sourceHandle` to an allowed source (no longer rejects when another source for the same provider originally fetched the row).
- Explorer search no longer mutates collection options — leaving search or switching collections clears `q` / search pagination so YouTube playlist browse cannot send `q` + `pageToken` together (#6).
- YouTube search uses `cachedRequest` so `providerSearchCacheDuration` applies (was uncached via `request()`).
- Explorer get-videos options are whitelisted (`id`, `q`, `nextPage`); provider page size is forced after merge so `maxResults` / `per_page` cannot exceed Videos Per Page.
- Explorer and URL resolve only use sources that are configured and connected; the field warns when sources are allowed but not connected.
- Saving a source clears explorer section cache + provider application cache when provider settings/credentials change (not merely Available Fields); OAuth connect uses the same path.
- Field URL input debounces get-video and only requests once the value matches a supported provider URL pattern (or on blur/Enter); posted URL still updates every keystroke for save.
- Require `videoPicker-sources` for source CP actions; OAuth `connect`/`disconnect` require that permission + POST (only `callback` stays anonymous).
- Field video AJAX requires a valid `fieldId` so source Available Fields scoping cannot be bypassed.
- Enforce `embedAllowedDomains` (when set), block private/loopback hosts, validate every generic-embed redirect hop, cache generic embed crawls, and bound hi-res image fetches.
- Strip authorization and cookie headers when a generic embed redirect crosses origins.
- Provider `cachedRequest` uses bounded TTLs, account/config-aware keys, and treats empty responses as cacheable.
- Field `normalizeValue` memoizes video URL lookups per request.
- Explorer cold-open hydrates collections for one source at a time (others use DB cache until selected).
- Unique index on `video_picker_videos.videoUrl` (migration dedupes existing rows).
- Provider video errors return JSON errors instead of a partial 200 payload.
- Source delete removes OAuth tokens and tagged application cache.
- Vimeo responses no longer request/store `download` / `review_link` / `files` in `raw`.
- GraphQL `ArrayType` serialization returns `toArray()` results correctly.
- Element thumb `srcset` no longer emits an empty 2× candidate.
- Explorer video cards are keyboard-operable (listbox/option, arrow keys, Enter/Space).
- Explorer / preview dialogs now respond properly on tablet/mobile.
- Play control is a real button (explorer + field: glyph on hover/focus; explorer also `P` on the focused card; field: full-thumb hit target). Coarse pointers keep a faint explorer rest glyph.
- Preview dialog exposes an accessible name (video title, or “Video preview”) even with `without-header`.
- URL `pk-input` picks up the Craft field label (host id matches `label[for]`; shadow textbox gets `aria-label`) instead of using the placeholder as its accessible name.
- Loading and error states announce to assistive tech (`aria-busy` on the field; `role="alert"` / `role="status"` in the explorer).
- Selected-video preview stacks thumb above meta in narrow field columns (container query) and on Craft mobile.
- Explorer search uses an explicit `aria-label` (not placeholder-only).
- Explorer collection links expose `aria-current` for the active collection.
- Selection state is programmatic (`aria-selected` + “Selected” in the option name).
- Thumbnails use real `<img alt>` (or `role="img"`) instead of CSS-only backgrounds.
- Private videos announce “Private” via the option label (lock icon stays decorative).
- Narrow URL rows move Browse out of the input overlay so the editable area isn’t crushed.
- Refresh / Remove (and play) hit targets enlarge on coarse pointers.
- Load More / pagination announce video counts via a polite live region.
- Thumb play / selection transitions respect `prefers-reduced-motion`.
- Title and author links announce that they open in a new tab.
- Field preview title is a styled `div` (no stray `h3` in the form outline).
- Keyboard users can commit a selected card with Enter (parity with double-click); Select button documents the path.

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
