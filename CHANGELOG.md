# Changelog

## Unreleased

### Added
- Add field setting for optional input placeholder text when no video is selected.
- Add source setting **Available Fields** to control what fields can browse the source.
- Expose provider/search/embed cache durations, hi-res embed image toggle, and embed allowed domains in plugin settings (advanced embed client arrays stay config-only).
- Add field selection policies: Allow URL Input, Allow Search, Public Videos Only, optional Videos Per Page override, min/max duration, and per-page Video Sort (no default source/collection — source order remains global).

### Changed
- Field input rebuilt on [Plugin Kit](https://docs.verbb.io/plugin-kit/web/) (web components).

### Fixed
- Cached video rows are shared by URL across same-provider sources; field scoping gates on provider URL match and rebinds `sourceHandle` to an allowed source (no longer rejects when another YouTube/Vimeo source originally fetched the row).
- Explorer search no longer mutates collection options — leaving search or switching collections clears `q` / search pagination so YouTube playlist browse cannot send `q` + `pageToken` together (#6).
- YouTube search uses `cachedRequest` so `providerSearchCacheDuration` applies (was uncached via `request()`).
- Explorer get-videos options are whitelisted (`id`, `q`, `nextPage`); provider page size is forced after merge so `maxResults` / `per_page` cannot exceed Videos Per Page.
- Explorer and URL resolve only use sources that are configured and connected; the field warns when sources are allowed but not connected.
- Saving a source clears explorer section cache + provider application cache when provider settings/credentials change (not merely Available Fields); OAuth connect uses the same path.
- Field URL input debounces get-video and only requests once the value matches a YouTube/Vimeo URL pattern (or on blur/Enter); posted URL still updates every keystroke for save.
- Require `videoPicker-sources` for source CP actions; OAuth `connect`/`disconnect` require that permission + POST (only `callback` stays anonymous).
- Field video AJAX requires a valid `fieldId` so source Available Fields scoping cannot be bypassed.
- Enforce `embedAllowedDomains` (when set), block private/loopback hosts, cache generic embed crawls, and bound hi-res image fetches.
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
- Explorer / preview dialogs now response properly on tablet/mobile.
- Play control is a real button (explorer: always-visible disc + `P` on the focused card; field preview: full-thumb hit target with glyph on hover/focus only so the thumbnail stays clean).
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
