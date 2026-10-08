# Gingerminds Media Manager Bundle

Media library, file library and image processing bundle for Gingerminds Symfony admin panels,
built on [`gingerminds/symfony-core`](https://github.com/gingerminds/symfony-core) — the
Symfony 8 counterpart of `gingerminds/laravel-media-manager`:

- a **file library**: the single place files are uploaded to, browsed, organised in folders and
  picked from, without duplicates, with the usages of every file (a used file cannot be deleted);
- **medias** (a file, a thumbnail, a category) and a **media category** tree, administered in the
  core admin;
- form types to pick **files** (`FilePickerType`) and **medias** (`MediaSelectType`), ordered
  collections of medias;
- **image presets** rendered by Glide, public file URLs (`/api/files/{id}/{preset}`);
- a public API of the medias and categories, and download **baskets** (ZIP);
- named storage **disks** (Flysystem), maintenance commands for imported databases.

Requires PHP 8.4, Symfony 8.1, Doctrine ORM 3, API Platform 4.4, `league/flysystem-bundle`,
`league/glide` and `gingerminds/symfony-core` ^1.6.

## Installation

```bash
composer require gingerminds/symfony-media-manager
```

Then follow [Installation](docs/Installation.md) (bundle, routes, storage, database, permissions).

## Documentation

**Getting started**

- [Installation](docs/Installation.md)
- [Configuration](docs/Configuration.md) — disks, library, presets, baskets, overriding the entities.
- [Commands](docs/Commands.md) — image cache, folders, baskets, maintenance.
- [Coming from laravel-media-manager](docs/FromLaravel.md) — concept mapping, behaviour changes, importing a Laravel database.

**Files & medias**

- [File library](docs/FileLibrary.md) — uploads, folders, usages and references, duplicates, permissions.
- [Medias](docs/Medias.md) — medias and media categories.
- [Forms](docs/Forms.md) — `FilePickerType`, `MediaSelectType`, collections of medias.
- [Images](docs/Images.md) — Glide presets, URLs, cache.

**API**

- [API](docs/API.md) — files, medias, media categories.
- [Baskets](docs/Basket.md) — guest and user baskets, ZIP download, expiration.

## Development

```bash
make install       # composer install (Docker)
make assets        # importmap + sass of the test application
make qa            # phpstan, phpcs, phpunit
make serve         # test application on http://127.0.0.1:8000/admin
```

The test application (`tests/Application`) boots the core and the media manager bundles on SQLite,
with an in-memory storage, an `Article` (file and media fields, media links) and a `Page` (file ids
in a JSON field) exercising the references and the form types. `APP_ENV=test php
tests/Application/bin/console ...`; the `test_no_basket` environment disables the baskets.
