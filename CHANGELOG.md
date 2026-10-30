# Changelog

All notable changes to this bundle are documented in this file.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and this project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added

- File events (`Gingerminds\MediaManagerBundle\Event`): `FileUploadingEvent` before a new content
  is stored (a listener refuses it with `FileUploadException::rejected()`, e.g. an antivirus),
  `FileStoredEvent`, `FileMovedEvent` (`previousPath`) and `FileDeletedEvent`, dispatched after the
  flush of the rows by `FileEventsListener` (Doctrine), whoever writes them.
- `FileNamerInterface` (default `SlugFileNamer`, `gingerminds_media_manager.file.namer`): the
  naming rule of the files and folders, used by `PathGuard`, which still refuses a name that is not
  a single segment.
- `FileResponseFactoryInterface`, the service the file controllers take: alias it to serve the
  files otherwise (X-Sendfile, bucket redirection).

- Alternative text of the files: nullable `alt` column on `BaseFile` (`FileInterface::getAlt()` /
  `setAlt()`, 255 characters max, blank saved as null), edited in the detail panel of the file
  library for the images, exposed as `alt` by the library endpoints and as `file_alt` by the media
  API. Merging duplicates gives the kept file the alt of a duplicate when it has none. Projects
  generate a migration adding the `alt` column to their `files` table.
- Form include slots of the core 1.7 form layout on the media and media category forms
  (`media_form_main|sidebar`, `media_category_form_main|sidebar`), e.g. for the fields another
  bundle or the project adds with a form type extension. Requires `gingerminds/symfony-core` ^1.7.

### Changed

- **BC break** — requires `gingerminds/symfony-core` ^1.10:
  - the bundle extends the core `AbstractResourceBundle`: the file, media and media category admin
    resources are registered on load (below the project `gingerminds_core.resources`), the basket
    is an overridable entity without admin, `resources.*.api: false` removes an entity from the
    API;
  - an entity overriding a bundle one inherits its `#[ApiResource]` (no copy to keep in sync);
  - `#[Cacheable]`, `#[CacheCascade]`, `#[Sortable]`, `#[Searchable]`, `#[EagerLoad]` and
    `#[Filter]` on the entities instead of the removed core interfaces;
  - `GET /api/media` and `GET /api/media_categories`: API Platform Doctrine provider and core list
    contract (`order[name]=asc`, `search=`, `category=`, `category[]=`, `category=null`); the
    Laravel `filters[media_category_id]` alias is removed with `MediaProvider`; the media list
    can no longer be sorted on `createdAt`;
  - media categories: core admin tree (`AbstractTreeCrudController`, `TreeRepositoryTrait`,
    `TreeNodeInterface`, `#[NoTreeCycle]`, `components/list/_tree.html.twig`);
    `MediaCategoryRepository::reorder()` / `findFlatTree()`, `MediaCategoryInterface::contains()`
    and the bundle `_tree.html.twig` partial are removed;
  - file, media and media category permissions checked by the core `ResourceVoter` (`FileVoter`,
    `MediaVoter` and `MediaCategoryVoter` removed): the file resource maps `DELETE` to `edit files`;
  - the controllers extend Symfony's `AbstractController` (no `CrudContext`), their routes are
    `#[Route]` attributes with the same names and paths; `#[IsGranted]` and `#[IsCsrfTokenValid]`
    replace the manual checks of the library writes and of the reordering;
  - `config/routes.php` only imports `FileLibraryActionController`.
- **BC break** Basket API: one processor per operation (`CreateBasketProcessor`,
  `AddMediasProcessor`, `RemoveMediaProcessor`, the API Platform remove processor for `DELETE`)
  instead of `BasketProcessor` and its `$action`; the payloads are input DTOs validated by API
  Platform (`CreateBasketInput`, `AddMediasInput`: a `422` with `violations`, also for an invalid
  `anonymous_token`, ignored until now); the owner is checked by the `security` of the operations
  (`BasketVoter`), an anonymous request on a user basket is a `401` (was `403`).
- **BC break** File library admin: rendered by the server in a Turbo frame (`gm-file-browser`)
  instead of the JSON endpoints and the `gm-file-browser` Stimulus controller (≈ 1 800 lines of
  JS). The state is in the query (`path`, filters, `sort`, `page`, `file` for the detail panel,
  `dialog` with `ids[]` / `paths[]`) and the page advances the URL. The dialogs (new folder, rename
  a folder, move, delete) are modals rendered by the server, moved to the body by `gm-file-dialog`
  (above the picker modal too), and closed back to the URL without `dialog`; the toolbar actions
  are enabled by the selection (`gm-file-selection`: move and delete by anything, rename by a single
  folder). The folder tree loads its subfolders when opened (`/admin/files/tree`) and only shows a
  caret on the folders that have some (`FileLibrary::hasDirectories()`). The writes are
  forms (CSRF token `gm-file-library` in `_token`, `return` URL, input DTOs mapped by
  `#[MapRequestPayload]`) coming back to the browser with flash messages: `POST /admin/files/directories`,
  `/directories/rename`, `/move` and `/delete` (files and folders together), `/{id}` (`name`,
  `alt`), `/merge`. Only `POST /admin/files/upload` answers JSON, one file a request with its
  progress (`gm-file-upload`). The picker modal loads the same frame (`gm-file-pick` keeps the
  picked files). Removed: the `browse`, `directories` and `show` JSON routes, the
  `directory_delete` / `directory_move` routes, `FileLibraryPresenter::browse()` / `directory()` /
  `breadcrumb()` / `usages()`,
  `FileLibraryController::ID_PLACEHOLDER` (now `FilePickerType::ID_PLACEHOLDER`) and the
  `assets/file_browser/` scripts (`helpers.js` and `modal.js` move to `assets/lib/`).
- **BC break** Media picker: the modal (`GET /admin/medias/picker`) is a Turbo frame rendered by the
  server (search, category filter restricted to the allowed categories, pagination instead of
  "Load more"); the `GET /admin/medias/search` JSON endpoint and `media_picker_controller.js` are
  removed. The file and media modals share one Stimulus controller, `gm-pick`
  (`pick_controller.js`, `gm-pick:pick` with `detail.items`), replacing `gm-file-pick` and
  `gm-media-picker`: a project without Flex updates its `controllers.json`.
- **BC break** The bundle is a Symfony UX package (`assets/package.json`, `symfony-ux` keyword):
  Flex adds its Stimulus controllers to the project `assets/controllers.json` (lazy, same `gm-*`
  names) and AssetMapper resolves their relative imports. `RelativeImportCompiler` and the inline
  registration of the controllers in `templates/admin/_head.html.twig` are removed (the head only
  links the stylesheet). A project without Flex adds the controllers itself (Installation.md).
- **BC break** The files keep the mime type guessed from their content: `MimeTypeNormalizer`
  (Office documents stored as `application/docx`, `application/xlsx`... and served with these
  unknown `Content-Type`) becomes `MimeTypeGuesser`, which only checks an OOXML guess against its
  zip content. `library.allowed_mimes` takes the real types (its default too), the `document` type
  filter matches them. `gingerminds:media:files:mime-types [--dry-run]` replaces the Laravel types
  of an imported database.
- **BC break** `GET /api/baskets/{token}/download` keeps the basket (no side effect on a `GET`, a
  download can be retried): `basket.delete_after_download: true` deletes it once downloaded, as the
  Laravel media manager did.
- The basket ZIP is streamed (`maennchen/zipstream-php`): no temporary copy of each file nor
  temporary archive, the download starts at once. `BasketArchiver::archive()` becomes `entries()`
  (the files found on their disk, by entry name) and `write()`; `ZipArchiveException` is removed.
- **BC break** `FileStorage::delete()` takes a list of files: the rows are deleted first, in one
  flush, then the physical files no other row points to (and their presets), a failure on the disk
  being logged. A failed flush (a reference added meanwhile) no longer leaves rows without file.
  `FileLibrary::delete()` and `mergeDuplicates()` delete in one go.
- `AssociationFinder` (`gingerminds_media_manager.doctrine.association_finder`): the owning
  associations to an entity, read once from the Doctrine mapping, shared by
  `DoctrineAssociationReferenceSource` and `MediaUsageCounter` (both take it as argument).
- **BC break** Orphans filter of the library and `files:orphans`: computed by the database, a
  `NOT EXISTS` per Doctrine association, instead of a `NOT IN` of every referenced id loaded in PHP
  (and every file row for `files:orphans`). `FileReferenceSourceInterface::usedIds()` and
  `FileReferenceRegistry::usedIds()` are replaced by `excludeReferenced(QueryBuilder $qb, string $alias)`;
  `JsonFieldFileReferenceSource` still reads its field in PHP (a `LIKE` on JSON is not portable).
  `FileRepository::paginateLibrary()` takes that restriction as a closure.
- **BC break** `FileResponseFactory`: `file(Request, FileInterface)` and `preset(Request, FileInterface,
  preset, parameters, render)` instead of `create(Request, disk, path, ...)`. The cache headers come
  from the row: `ETag` the content hash (the id and the dates without hash; for a preset, with its
  definition), `Last-Modified` the dates, the type and the size of the file: a 304 reads nothing on
  the disk and renders no preset, a 200 only opens the file (404 when it is missing), instead of
  four storage calls. The ETags change once (they were `md5(path . lastModified)`).
  `ImageProcessor::parameters()` gives the definition of a preset.
- Basket API: the `token` links no longer name the bundle `Basket` class (`fromClass`), so an
  entity overriding it keeps working operations.
- `GET /api/files/{id}` and `/{preset}`: the file is read by API Platform (`$data` of
  `ShowFileController` / `ShowFilePresetController`, no repository), a non UUID id is a 404 of the
  routing.
- `gingerminds:media:basket:purge` is a daily periodic task of the `default` schedule when
  `symfony/scheduler` is installed (`messenger:consume scheduler_default`), through the
  `scheduler.task` tag (no `#[AsPeriodicTask]`: it breaks the autoconfiguration without the
  component); a cron still works.
- `gm_file_url()`: `MediaManagerExtension` is a Twig runtime (`#[AsTwigFunction]`), built on its
  first call.
- `PATCH /admin/files/{id}` updates the sent fields only (`name` and/or `alt`): the action
  `FileLibraryActionController::rename()` is now `update()` and the route
  `gingerminds_media_manager_file_rename` is now `gingerminds_media_manager_file_update`.
- CI: PHPUnit coverage (Xdebug, Clover report) sent to SonarQube, which now runs after the tests;
  line coverage shown on the merge requests.
- File library: the `from` / `to` creation date filters are whole days of the core
  `display_timezone`, compared as UTC bounds (`FileLibraryController` takes the core
  `DisplayTimezone` service). Requires the core `DisplayTimezone` (dates stored in UTC).

### Removed

- **BC break** The `gingerminds_media_manager.storage.disks`, `.basket.enabled` and `.files_rate_limit`
  container parameters (read by nothing: the configuration keys stay) and
  `FileReferenceRegistry::isUsed()` (no caller: `usages()`).

### Fixed

- Two simultaneous uploads of one content give one file: the hash is looked up and the file
  stored under a lock of the content (`PathLocker::content()`), and hashed once
  (`FileStorage::store()` takes the known hash).
- An upload, a file move or a new directory into a directory being moved waits for the end of the
  move: it was left in the old directory and deleted with it (row without physical file).
  `PathLocker` locks the moved directories exclusively and shares the directories written into
  (and their parents); `FileLibrary::mkdir()` is locked too.
- No Doctrine deprecation on the order of the media category children (`\SortDirection` instead
  of `'ASC'`).
- File library and media picker modals: centered, the media picker as wide as the library one.
  Opened over another modal (a form in a modal), they go above it with their backdrop instead of
  showing it through.

### Security

- Every response serving a file content (`GET /api/files/{id}`, `GET /api/files/{id}/{preset}`,
  304 included, basket ZIP) sends `X-Content-Type-Options: nosniff` and
  `Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; sandbox`
  (`FileResponseHeaders`): an uploaded SVG opened directly no longer runs its scripts in the
  application origin, it still displays in an `<img>`. A PDF keeps `nosniff` only, the browser PDF
  viewers refusing a sandboxed document. `ImageProcessor::process()` rejects an SVG (`image/svg`
  included) instead of handing it to Glide.
- Uploads, renames, moves, `files:relocate` and directory moves choose their free path ("name-1.ext"...),
  write it and save the rows under a `symfony/lock` lock (`PathLocker`, keyed on the disk, the
  directory and the name without its "-N" suffixes): two simultaneous uploads of `photo.jpg` no
  longer write the same physical file. Requires `symfony/lock` (store of `framework.lock`).

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
- `MediaCategoryChoiceType`: a media category picked in the indented tree, `exclude` removes a
  category and its descendants (category parent, media category).
- Baskets of medias (`src/Basket`, overridable `BaseBasket` / `resources.basket.entity`): `baskets`
  (`token` UUID, `owner_id` to the core user, `expires_at`) and `basket_media` (cascade on both
  sides). API as in Laravel: `POST /baskets` (a guest basket; for a user, a new basket replacing
  the previous one, `anonymous_token` claims a guest basket with `basket.claim_strategy`),
  `GET` / `DELETE /baskets/{token}`, `POST /baskets/{token}/medias` (`media_ids`, no duplicates),
  `DELETE /baskets/{token}/medias/{mediaId}`, `GET /baskets/{token}/download` (ZIP of the files,
  each read on its own disk under its original name, then the basket is deleted; 422 when there is
  nothing to download). A guest basket is open to whoever has its token, a user basket to its owner
  only (`BasketVoter`). The API login response gets the `basket_token` of the user.
- `basket.ttl` (30 days by default): a guest basket expires that long after its last change, a user
  basket never; `gingerminds:media:basket:purge` deletes the expired ones (cron).
- `basket.enabled: false` removes the baskets entirely: no Doctrine mapping (no tables), no API
  resource, no voter nor login enrichment.
- A media in a basket can still be deleted (`MediaUsageCounter` ignores the baskets).
- Maintenance commands, for imported databases and files copied by hand (`src/File/Maintenance`):
  - `gingerminds:media:files:hash [--force]`: sha256 of the files without one, read on their disk;
  - `gingerminds:media:files:index [--path=] [--dry-run]`: rows for the files of the library disk
    missing from `files`, whatever their type (hidden directories excluded);
  - `gingerminds:media:files:deduplicate [--dry-run]`: the files with the same hash merged into the
    oldest one (references updated), report table;
  - `gingerminds:media:files:relocate [--dry-run]`: the files outside `library.root` (every disk)
    moved to its first level, "name-1.ext" when taken, presets purged, rows sharing a file together;
  - `gingerminds:media:files:orphans [--delete] [--older-than=DAYS]`: the files used nowhere.
- Documentation: `README.md` and `docs/` (installation, configuration, file library, medias, forms,
  images, API, baskets, commands, coming from Laravel with the import of a Laravel database).
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
