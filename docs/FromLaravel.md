# Coming from `gingerminds/laravel-media-manager`

The bundle keeps the Laravel package features (medias, media categories, files and Glide presets,
media select, media collections, baskets) and its database schema, with the YANMAR file library
plan built in: files are uploaded to a single library, never twice, their usages are known and a
used file cannot be deleted. This page maps each concept; see also the core
[Coming from laravel-core](https://github.com/gingerminds/symfony-core/blob/master/docs/FromLaravel.md).

## Concepts

| Laravel package | Symfony bundle | Notes |
|---|---|---|
| `LaravelMediaManagerServiceProvider`, `...AuthServiceProvider` | `GingermindsMediaManagerBundle` + `config/services/*.php` | `gingerminds_media_manager.*` service ids. |
| `config/gingerminds-media-manager.php` | `gingerminds_media_manager:` config | See the table below. |
| `ResourceResolver` (`resources.media\|media_category`) | `resources.media\|media_category\|file\|basket` (`entity`, `controller`, `form`) | Registered as core resources, see [Configuration](Configuration.md#overriding-the-entities). |
| `Media`, `MediaCategory`, `File`, `Basket` models | `BaseMedia`, `BaseMediaCategory`, `BaseFile`, `BaseBasket` mapped superclasses + entities | Overridable. |
| `MediaController`, `MediaCategoryController`, Blade views | Core CRUD controllers, Twig templates | Media library menu section. |
| Policies (`MediaPolicy`, `MediaCategoryPolicy`, `BasketPolicy`) | Voters (`MediaVoter`, `MediaCategoryVoter`, `FileVoter`, `BasketVoter`) | Same permission names, plus `view\|edit files`. |
| `FileUploadService` (`upload`, `replace`, `delete`) | `FileLibrary`, `FileStorage` | No `replace()`: a new file is uploaded to the library and picked. |
| `<x-...::form.inputs.file>` (upload field) | [`FilePickerType`](Forms.md#filepickertype) | Picks in the library, no direct upload. |
| `<x-...::form.inputs.media-select>` + `medias.search` | [`MediaSelectType`](Forms.md#mediaselecttype) + `GET /admin/medias/search` | `category-codes` → `categories`, `per-page` → `per_page`. |
| `MediaCollectionSyncer` (pivot `collection`, `sort_order`) | `AbstractMediaLink` + `MediaCollectionSyncer` + `collection` option | A link entity per pivot table, see [Forms](Forms.md#collections-of-medias). |
| `HasBasket` trait (polymorphic owner) | `Basket::$owner` (core user) | |
| `BasketLoginResponseEnricher` | Same name, on the core `LoginResponseEnricherInterface` | |
| `ImageProcessor`, `GlideCacheService` | `ImageProcessor` | |
| `MediaNormalizer` | `MediaApiFieldsTrait` | `file` is always the file id. |
| `media-manager:cache:clear [--media=]` | `gingerminds:media:cache:clear [--media=] [--file=]` | |
| Migrations shipped by the package | `doctrine:migrations:diff` in the project | |

### Configuration

| Laravel | Symfony |
|---|---|
| `disk` (`MEDIA_MANAGER_DISK`) | `storage.default_disk` + `storage.disks` |
| `folder` (`MEDIA_MANAGER_FOLDER`, `uploads`) | `library.root` (`library`) |
| `max_file_size`, `max_thumbnail_size` (KB) | `library.max_upload_size` (KB), one limit |
| — | `library.allowed_mimes` |
| `files_rate_limit` | `files_rate_limit` |
| `default_format`, `presets` | `images.default_format`, `images.presets` |
| `basket.enabled`, `basket.claim_strategy` | `basket.enabled`, `basket.claim_strategy` |
| `basket.owner_models`, `basket.storage_disk` | — (core user; each file on its own disk) |
| — | `basket.ttl`, `images.driver`, `images.cache_prefix`, `library.per_page`, `library.max_directory_move` |

## Behaviour changes

- **No direct upload**: files are uploaded to the library and picked; uploading a known content
  returns the existing file.
- **Deleting**: a media keeps its files (Laravel deleted them); a used file, a used media, a
  category with subcategories or medias cannot be deleted (Laravel cascaded or set to null).
- **API**: `file` is always the file id (Laravel sent the path of a non-image file); `code` and
  `media_category_id` are new fields; `filters[media_category_id]` still works.
- **Media**: `code` is new, required and unique; the name defaults to the file name.
- **Media select**: a category includes its subcategories; no language hint (project specific).
- **Media categories**: `code` is unique.
- **Presets**: an SVG is returned as is (500 under Laravel), 400 for a non image.
- **Baskets**: a guest basket expires (`basket.ttl`), the ZIP keeps the original file names, and
  `basket.enabled: false` removes the tables and routes too (Laravel only disabled the policy and
  the login enrichment).
- The Laravel `create()` of the media controller did not pass the category to its view: fixed
  (`?category_id=`).

## Schema

Same tables and columns, with:

| Table | Changes |
|---|---|
| `files` | `hash` (sha256, indexed) added. |
| `media_categories` | `code` unique. |
| `medias` | `code` added (unique, required); `file_id` required; `file_id`, `thumbnail_id` in `RESTRICT` (Laravel: cascade). The legacy `file_name`, `mime_type`, `size` columns were already dropped by the Laravel migrations. |
| `baskets` | `owner_type` / `owner_id` (polymorphic) become `owner_id` to `users` (`CASCADE`). |
| `basket_media` | Unchanged. |
| Media links | One table per link entity (`collection`, `position`), like the Laravel pivots (`sort_order` → `position`). |

## Importing a Laravel database

1. **Disks**: declare the Laravel disk names (`public`...) in `storage.disks`, pointing to storages
   holding the Laravel files, so that every `files.disk` is known.
2. **Clean** before the schema migration:
   - media categories with the same `code`: rename or merge them;
   - medias without `file_id`: delete them or attach a file;
   - give every media a `code` (e.g. `UPDATE medias SET code = CONCAT('media-', id)`);
   - baskets: keep the rows whose `owner_type` is the user class as `owner_id`, delete the others.
3. **Migrate** the schema (`doctrine:migrations:diff`, review, `migrate`), then
   `gingerminds:permissions:sync`.
4. **Files**, in this order:

```bash
php bin/console gingerminds:media:files:relocate --dry-run   # then without --dry-run: uploads/... into the library
php bin/console gingerminds:media:files:hash                 # hashes of the imported rows
php bin/console gingerminds:media:files:deduplicate --dry-run
php bin/console gingerminds:media:files:deduplicate          # merges the duplicates, references updated
php bin/console gingerminds:media:files:orphans              # review the unused files, then --delete
```

5. **Presets**: the Laravel Glide cache is not reused, the presets are rendered again on demand.

After the import, the "Duplicates" filter of the library shows the duplicates left, and the library
detects the usages of every file.
