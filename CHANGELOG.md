# Changelog

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
