# Gingerminds Media Manager Bundle

Media library, file library and image processing bundle for Gingerminds Symfony admin panels,
built on [`gingerminds/symfony-core`](https://github.com/gingerminds/symfony-core) — the
Symfony 8 counterpart of `gingerminds/laravel-media-manager`.

Requires PHP 8.4, Symfony 8.1, Doctrine ORM 3, API Platform 4.4, `league/flysystem-bundle`,
`league/glide` and `gingerminds/symfony-core` ^1.6.

> Work in progress: the port of `gingerminds/laravel-media-manager` is done step by step,
> see [CHANGELOG](CHANGELOG.md).

## Development

```bash
make install     # composer install (Docker)
make assets      # importmap + sass of the test application
make qa          # phpstan, phpcs, phpunit
```

The test application (`tests/Application`) boots the core and the media manager bundles on SQLite,
with an in-memory storage: `APP_ENV=test php tests/Application/bin/console ...`.
