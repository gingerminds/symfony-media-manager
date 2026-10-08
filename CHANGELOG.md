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

- "File library" admin page (`/{admin}/files`, `file` core resource without CRUD routes, its
  controller set by `resources.file.controller`): folder tree, search and filters (type, sort,
  subfolders, unused, duplicates, creation dates), paginated grid with usage counts (`thumbnail`
  preset), detail panel (`card` preset preview, usages with their edit links, duplicates),
  uploads by button or drag & drop with progress (an existing content is reported, not stored
  again), new folder, rename, move and delete (used files are kept and listed with their
  usages; the move dialog shows the folder tree), merge of the duplicates. Folders stay listed
  whatever the filters, except while searching (files only, matched on their name, not their
  folders); usages and duplicates are collapsible sections of the detail panel.
- JSON endpoints of the library browser under `/{admin}/files` (`gingerminds_media_manager_file_*`
  routes; reads in `FileLibraryController`, writes in the `FileLibraryActionController` service): `browse`, `directories` (GET, POST, DELETE; each directory tells whether it has
  subdirectories), `{id}` (GET, PATCH), `upload`, `move`,
  `delete`, `merge`. Writes need the `gm-file-library` CSRF token in the `X-CSRF-Token` header;
  library errors are 422 with a translated message.
- `view files`: browse and pick files; `edit files`: every other action, deletions included
  (`delete files` is created by `gingerminds:permissions:sync` but not used).
- `LibraryStartPathProviderInterface`: folder the browser opens on (root by default), replaced by
  aliasing the interface.
- Admin assets through the core `admin_includes.head` slot: `gingerminds-media-manager` asset
  mapper path, `assets/styles/media-manager.scss` added to the sass-bundle roots (project theme
  and Bootstrap variables available), `gm-file-browser` Stimulus controller registered on the
  core app. Nothing to add to the project importmap: `RelativeImportCompiler` rewrites the relative
  imports of the bundle scripts (`gingerminds-media-manager/*.js`) to their versioned paths.
- Folders of the library selected with checkboxes, like the files but never together: the toolbar
  then offers the folder actions instead of the file ones. Rename (one folder), move
  (`FileLibrary::moveDirectories()`, the selected folders and their subfolders greyed out in the
  dialog) and delete (`rmdirs()`: the empty folders only, the others are kept and listed). A moved
  folder takes everything on the disk with it, the files missing from `files` too; the rows get
  their new paths (references keep working, they hold ids), the presets are purged and a folder
  that fails is moved back. The root cannot be moved, nor a folder into itself, nor onto an
  existing folder. `PATCH` / `DELETE /{admin}/files/directories` take `paths[]`. Up to
  `library.max_directory_move` files per move (1000 by default, 0: no limit), above that
  `gingerminds:media:directory:move <path> [<parent>] [--name=]` moves a folder without limit.
- `MediaSelectType`: one media (`MediaInterface`), several (`multiple`, a collection) or their ids
  (`as_id`), picked in the media picker; `categories` (codes) restricts the medias to these
  categories and their subcategories, one code locks the category filter; `per_page`. Widget: the
  picked medias as cards, removed one by one, reordered when several; `gm-media-select:change`
  event. Server side check of the existence and of the category of each media.
- Media picker modal (`GET /{admin}/medias/picker`, `view medias`), loaded once per page and shared
  by the fields: search (code, name), category tree (subcategories included), "Load more", "Select".
  Medias from `GET /{admin}/medias/search` (admin endpoint, the public API may be scoped by a
  project; overridable with `resources.media.controller`).
- Ordered collections of medias (port of `MediaCollectionSyncer`): `AbstractMediaLink` mapped
  superclass (`media` RESTRICT, `collection`, `position`) extended by a project link entity,
  `MediaCollectionSyncer::sync()` / `medias()` (string or backed enum collections), and the
  `collection` + `link_factory` options of `MediaSelectType` to edit one collection of the links.
- A media used through a Doctrine association (a link, a project relation) cannot be deleted
  (`MediaUsageCounter`).
- `MediaCategoryInterface` extends `TimestampableInterface`.
- `FilePickerType`: one file (`FileInterface`) or several (`multiple`, a collection), or their ids
  with `as_id` (JSON fields), picked in the library (no direct upload). Options: `accept` (mime
  types, exact or `type/*`: library filter, upload input, type filter of the modal limited to
  the matching types, server side check), `preview_preset`,
  `start_path`. Widget (form theme prepended): the picked files as cards, removed one by one,
  reordered by drag & drop when several; `gm-file-picker:change` event.
- Library picker modal (`GET /{admin}/files/picker`, `view files`), loaded once per page and shared
  by the fields: the library browser in picker mode (only the accepted types, uploads and new
  folders allowed, the other actions stay on the library page). A click picks a file, "Select"
  confirms; an uploaded file is picked, an already known content offers "Use this one".
- `browse` filters by mime types with `accept[]` (`MimeTypePatterns`).
- Library exceptions are translatable (`TranslatableExceptionInterface`, `error.*` keys).
- `Media` entity (overridable `BaseMedia` mapped superclass, `resources.media.entity`), `medias`
  table with the Laravel columns plus a required, unique and searchable `code`: `name` (nullable,
  the file name when left empty), `file_id`
  (required) and `thumbnail_id` (an image) as `RESTRICT` foreign keys to `files`,
  `media_category_id` (`SET NULL`). The legacy `file_name` / `mime_type` / `size` columns are not
  kept: they come from the file.
- Media admin CRUD (`/{admin}/medias`, `view|edit|delete medias`, "Medias" entry of the media
  library menu): list with preview (the file when it is an image, else the thumbnail), category
  filter and search; form with `FilePickerType` for the file and the thumbnail and the category
  tree; `?category_id=` preselects the category. Deleting a media keeps its files in the library.
- `GET /api/media` and `/api/media/{id}` (public, `MediaProvider`): `id`, `code`, `name`, `file` (always
  the file id, never its path), `file_reference`, `file_size`, `file_type`,
  `thumbnail_reference`, `thumbnail_size`, `media_category_id`; filtered by
  `filters[category]` or by the Laravel name `filters[media_category_id]`. Cached as `media`,
  invalidated by the media categories.
- `gingerminds:media:cache:clear --media=ID`: clears the presets of the file and the thumbnail of
  these medias.
- The files of a media are reported as used by the library (delete blocked, "Media" usage linked to
  its edit page) and follow a duplicates merge.

### Changed

- Requires `gingerminds/symfony-core` ^1.6 (`admin_includes.head`).
- `storage.default_disk` and `library.root` are checked at runtime, so they accept env
  placeholders (`%env(FILE_LIBRARY_DISK)%`, `%env(FILE_LIBRARY_ROOT)%`).
- A media category that still has medias cannot be deleted (Laravel set them to no category).
