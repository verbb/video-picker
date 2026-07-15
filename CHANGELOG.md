# Changelog

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
