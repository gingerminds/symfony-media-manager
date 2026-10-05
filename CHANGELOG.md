# Changelog

All notable changes to this bundle are documented in this file.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and this project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added

- `GingermindsMediaManagerBundle` skeleton (`gingerminds_media_manager` configuration: named
  storage disks, file library, image presets, files rate limit, basket switch, overridable
  `media` / `media_category` / `file` / `basket` resources) and its test application.
- `File` entity (overridable `BaseFile` mapped superclass, `resources.file.entity`), `files`
  table with the `gingerminds/laravel-media-manager` schema (UUID `guid` id, `disk`, `path`,
  `mime_type`, `original_name`, `size`) plus an indexed sha256 `hash`.
- Default `gingerminds_media_manager.storage.default` Flysystem storage (local,
  `var/storage/media`), replaced by a project storage of the same name; `DiskRegistry` resolves
  the `storage.disks` names (`UnknownDiskException`).
- `FileStorage::store()`: size and mime checks (`library.max_upload_size`,
  `library.allowed_mimes`), slugged name under `library.root`, "name-1.ext" suffix when the path
  is taken (on the disk or in `files`), sha256 hash; `delete()` and `readStream()`. No
  `replace()`, no `folder` option (`MEDIA_MANAGER_FOLDER`).
- `PathGuard`: relative paths only (no "." / ".." segments, backslashes or control characters),
  confined to `library.root`.
- `MimeTypeNormalizer` (Office types become `application/<extension>`, OOXML guesses checked
  against their zip content), as a service.

### Changed

- `storage.default_disk` and `library.root` are checked at runtime, so they accept env
  placeholders (`%env(FILE_LIBRARY_DISK)%`, `%env(FILE_LIBRARY_ROOT)%`).
