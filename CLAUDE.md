# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

`gingerminds/symfony-media-manager`: a Symfony 8.1 bundle (PHP 8.4, Doctrine ORM 3, API Platform 4.4, Flysystem, Glide) that adds a media library, file library and image presets to admin panels built on `gingerminds/symfony-core` (^1.5). It is a step-by-step port of `gingerminds/laravel-media-manager`. Each step is recorded in `CHANGELOG.md` under `[Unreleased]`, so update it with every functional change.

## Project overview

Don't put useless comments. Comments needs to be small and quick to read not a 3-4 lines text.

If i use a skill or  put a message that explain a bug or a functionnality it's not necessary to put it in the code except if it's a really particular things that is not understandable by the code itself.

## Commands

Everything runs in the CI Docker image (`php-8.5-fpm-node-22-composer-2`, with gd/imagick/zip) through the Makefile. `var/` directories are Docker volumes, so do not reuse host-generated caches or the dart-sass binary.

```bash
make install         # composer install
make assets          # importmap:install + sass:build for the test app (needed before phpunit)
make qa              # phpstan + phpcs + phpunit (what CI checks, together with rector/php-cs-fixer dry-runs)
make phpunit         # runs `assets` first
make phpstan         # level 8, covers src/, config/, tests/Application/
make phpcs           # PSR-12 safety net (line length 180, warning only)
make fix-codestyle   # rector then php-cs-fixer (@Symfony + risky); formatting is owned by php-cs-fixer
```

Single test / suite (same Docker invocation as the Makefile's `PHP` variable):

```bash
docker run --rm -v $PWD:/app -v gingerminds-media-manager-var:/app/var -v gingerminds-media-manager-app-var:/app/tests/Application/var \
  -w /app --platform linux/amd64 registry.gitlab.gingerminds.fr/gm/docker-images/php-8.5-fpm-node-22-composer-2 \
  ./vendor/bin/phpunit --filter BundleBootTest        # or --testsuite unit|functional
```

Test app console: `APP_ENV=test php tests/Application/bin/console ...`. Browse it with `make serve`.

## Architecture

- **Bundle class** (`src/GingermindsMediaManagerBundle.php`) is an `AbstractBundle` with alias `gingerminds_media_manager`. It imports `config/definition.php` (config tree) and `config/services.php`, then exposes each config value as a `gingerminds_media_manager.*` container parameter. A new config option needs three edits: the tree, the parameter in `loadExtension()`, and `ConfigurationTest`/`BundleBootTest`.
- **Config files are PHP, not YAML.** `config/services.php` is meant to import per-concern files under `config/services/`. `config/routes.php` holds the bundle's own routes. The Media/MediaCategory CRUD routes come from the core's `gingerminds_crud` loader instead.
- **Configuration concepts** (`config/definition.php`):
  - `storage.disks` maps disk names to Flysystem storage service ids. Every file row stores its disk name, so declare Laravel disk names (e.g. `public`) when migrating a database.
  - `library` is the single place files are uploaded to, browsed and picked from. There is no direct upload elsewhere.
  - `images.presets` are Glide parameter sets served at `GET /api/files/{id}/{preset}`. Preset names are URL segments.
  - `basket.enabled` must really disable the feature: entities, API operations and login enrichment.
  - `resources.{media,media_category,file,basket}` declares overridable entity/controller/form classes, registered as `gingerminds_core` resources. Project entities extend the bundle's `Base*` classes.
- Translation domain: `GingermindsMediaManager` (`GingermindsMediaManagerBundle::TRANSLATION_DOMAIN`).
- The bundle has no dependency on `gingerminds/symfony-multisite`. Integration points are exposed through interfaces.
- `src/Maker/skeleton/*` is excluded from phpstan, phpcs, php-cs-fixer and rector.

## Tests

- `tests/Application` is a MicroKernel app that boots the core and this bundle on SQLite, with an in-memory Flysystem adapter (nothing is written to disk). Its Doctrine mapping only covers `tests/Application/Entity`. Bundle entities must be mapped by the bundle itself.
- `tests/bootstrap.php` sets `GINGERMINDS_VAR_DIR=var/phpunit`, then wipes the cache and recreates the SQLite schema from metadata on every run. There are no migrations in tests.
- DAMA DoctrineTestBundle wraps each test in a transaction that is rolled back.
- Functional tests extend `Tests\Functional\ApiTestCase`, which provides an `api()` JSON-LD helper and `assertStatus()`. They create data through `Fixtures` (users, roles, permissions, API tokens from the core). Log into the admin with `$client->loginUser($user, 'admin')`. Call the API with a bearer token from `Fixtures::token()`.
- PHPUnit 12 with `failOnRisky`/`failOnWarning`, and deprecations are displayed. Keep runs deprecation-free.
