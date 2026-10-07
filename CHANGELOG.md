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
- `GET /api/files/{id}` (original file, inline with its original name) and
  `GET /api/files/{id}/{preset}` (Glide preset, `ImageProcessor`), public, same URLs as
  `gingerminds/laravel-media-manager`: streamed from the disk of the file, one day browser cache
  revalidated with ETag (same value as Laravel) / Last-Modified (304), 404 for an unknown file or
  preset, 400 for a non image. An SVG is returned as is by the presets (500 under Laravel).
- Presets rendered and cached on the disk of each file (`images.driver`, `images.cache_prefix`,
  per preset `fm` over `images.default_format`), purged when the file is deleted.
- `gingerminds_media_manager_files` rate limiter (`files_rate_limit` requests per minute, 0: no
  limit) on the file endpoints, through the core API rate limiting.
- `{preset}` documented in OpenAPI as the list of the configured presets.
- `gm_file_url(file, preset = null, absolute = false)` Twig function.
- `gingerminds:media:cache:clear [--file=<id>]... [--force]` command (`media-manager:cache:clear`).
- `MediaCategory` entity (overridable `BaseMediaCategory`, `resources.media_category`),
  `media_categories` table with the Laravel schema (`code`, `name`, `parent_id` set to null when
  the parent is deleted, `position`) plus a unique `code`. A category cannot be placed in itself
  or one of its descendants.
- Media categories admin (`media-categories`, `view|edit|delete media_categories` permissions):
  drag & drop tree ordered by position, "add a child" (`new?parent_id=`), parent select indented
  by depth. A new or moved category goes to the end of its level. A category with children cannot
  be deleted.
- `POST /{admin}/media-categories/reorder` (`gingerminds_media_manager_media_category_reorder`,
  CSRF): orders one level, the ids of other levels are ignored.
- "Media library" admin menu section.
- Public `GET /api/media_categories` (200 per page, 500 max, ordered by position) and
  `GET /api/media_categories/{id}` (with `children`), with `parent_id`, cached
  (`media_category`, invalidates `media`).
- Public `GET /api/media_categories/tree`: the root categories with their nested `children`
  (every level, ordered by position), not paginated, loaded by a single query.

- `FileLibrary`, the file library under `library.root` (paths relative to it): `directories()`
  (read on the disk), `files(LibraryQuery)` (read in `files`, paginated by `library.per_page`:
  search, type, creation dates, orphans, duplicates, sort, recursive), `mkdir()`, `rmdir()`
  (empty directories only), `upload()` (a content already in the library is not stored again: the
  existing file is returned as a duplicate), `rename()` / `move()` (same id, the physical file
  follows, presets purged, moved back when the save fails), `delete()` (used files are kept and
  returned with their usages, `deleteFile()` throws `FileInUseException`), `mergeDuplicates()`.
- File reference registry (`FileReferenceRegistry`): `usages()` of several files at once,
  `isUsed()`, `usedIds()`, `replace()` (through the entities, so the API cache is invalidated).
  Sources implement `FileReferenceSourceInterface` (autoconfigured): every Doctrine association
  to a file is found in the mapping, `JsonFieldFileReferenceSource` covers ids stored in a JSON
  field. Usages are resolved by `FileUsageResolverInterface` services (autoconfigured, by
  priority), by default to the `gingerminds_core` resource of the owner and its edit URL.
- `FileStorage::delete()` keeps the physical file while another row still points to it.

### Changed

- `storage.default_disk` and `library.root` are checked at runtime, so they accept env
  placeholders (`%env(FILE_LIBRARY_DISK)%`, `%env(FILE_LIBRARY_ROOT)%`).
