# Installation

Install and configure [`gingerminds/symfony-core`](https://github.com/gingerminds/symfony-core)
first (^1.6): this bundle registers its resources, menu, permissions, admin assets and login
enrichment into it.

## 1. Require the bundle

```bash
composer require gingerminds/symfony-media-manager
```

Register it after the core and the Flysystem bundle (Flex does it for you) in `config/bundles.php`:

```php
return [
    // ...
    League\FlysystemBundle\FlysystemBundle::class => ['all' => true],
    Gingerminds\CoreBundle\GingermindsCoreBundle::class => ['all' => true],
    Gingerminds\MediaManagerBundle\GingermindsMediaManagerBundle::class => ['all' => true],
];
```

## 2. Routes

```yaml
# config/routes/gingerminds_media_manager.yaml — write endpoints of the file library
gingerminds_media_manager:
    resource: '@GingermindsMediaManagerBundle/config/routes.php'
```

The routes of the resources (`/admin/medias` and its picker, `/admin/media-categories` and its
reordering, the `/admin/files` pages) come from the core `gingerminds_crud` loader, the API routes (`/api/files`, `/api/media`,
`/api/media_categories`, `/api/baskets`) from API Platform: nothing to add.

## 3. Storage

Without configuration, the files go to the `gingerminds_media_manager.storage.default` Flysystem
storage: local, `var/storage/media`, disk name `default`. To store them elsewhere, declare a
storage with that name (it replaces the bundle one) or map your own storages to disk names:

```yaml
# config/packages/flysystem.yaml
flysystem:
    storages:
        media.storage:
            local:
                directory: '%kernel.project_dir%/var/storage/media'

# config/packages/gingerminds_media_manager.yaml
gingerminds_media_manager:
    storage:
        default_disk: default
        disks:
            default: media.storage
```

Every file row keeps the name of its disk: see [Configuration](Configuration.md#storage), and
[Coming from Laravel](FromLaravel.md) to keep the disks of an imported database.

Keep the storage **private** (out of `public/`): the files are served by `GET /api/files/{id}`,
with the headers that keep an uploaded SVG from running scripts.

### Locks

Uploads, renames and moves lock the file names through the `framework.lock` store (`symfony/lock`,
required by the bundle). Without configuration it is local to the server (`semaphore`, else
`flock`), which is enough for one server, however many PHP workers. With several servers or
containers sharing the database and the storage, use a shared store:

```yaml
# config/packages/lock.yaml (Flex recipe of symfony/lock)
framework:
    lock: '%env(LOCK_DSN)%'   # e.g. LOCK_DSN=redis://redis:6379
```

## 4. Database

The bundle maps its entities itself (`files`, `media_categories`, `medias`, and `baskets` /
`basket_media` when the baskets are enabled). Generate and run the migration in the project:

```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

## 5. Permissions

```bash
php bin/console gingerminds:permissions:sync
```

creates `view|edit|delete files`, `view|edit|delete medias` and `view|edit|delete
media_categories`; give them to your roles. See [File library](FileLibrary.md#permissions).

## 6. Admin assets

The core `admin_includes.head` slot loads the bundle stylesheet (compiled with the core one by the
sass bundle, with your theme and the Bootstrap variables).

The bundle is a Symfony UX package (`assets/package.json`): on `composer require`, Flex adds its
Stimulus controllers to `assets/controllers.json` (`gm-file-picker`, `gm-media-select`... loaded
lazily by the core admin Stimulus app) and `sortablejs` to the importmap. Without Flex, add them
yourself:

```json
{
    "controllers": {
        "@gingerminds/symfony-media-manager": {
            "file-dialog": {"enabled": true, "fetch": "lazy"},
            "file-filters": {"enabled": true, "fetch": "lazy"},
            "file-picker": {"enabled": true, "fetch": "lazy"},
            "file-selection": {"enabled": true, "fetch": "lazy"},
            "file-upload": {"enabled": true, "fetch": "lazy"},
            "media-select": {"enabled": true, "fetch": "lazy"},
            "pick": {"enabled": true, "fetch": "lazy"}
        }
    }
}
```

## 7. Cron

With the baskets enabled, purge the expired guest baskets every day: with `symfony/scheduler`, it
is a task of the `default` schedule (`messenger:consume scheduler_default`), else a cron:

```cron
0 3 * * * php bin/console gingerminds:media:basket:purge
```

## Check

- `/admin/files`: the file library, upload a file;
- `/admin/medias`: create a media with that file;
- `GET /api/media`, then `GET /api/files/{id}/thumbnail` with the `file` of a media.
