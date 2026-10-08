# Images

Images are resized on demand by [Glide](https://glide.thephpleague.com/), from **presets**
declared in the configuration: the front never sends free sizes.

```yaml
gingerminds_media_manager:
    images:
        driver: imagick            # or gd
        default_format: webp       # format of the presets without their own "fm"
        presets:                   # replaces the default presets
            thumbnail: { w: 150, h: 150, fit: crop, q: 80 }
            card: { w: 400, h: 300, fit: contain, q: 85 }
            hero: { w: 1280, h: 720, fit: crop, q: 90, fm: jpg }
            banner: { w: 1920, h: 600, fit: crop, sharp: 5 }   # any Glide parameter
```

The default presets are `micro` (25×25), `thumbnail` (150×150), `card` (400×300) and `hero`
(1280×720). The admin uses `thumbnail` (grid, lists, pickers) and `card` (details): keep them, or
the admin falls back to the original files.

## URLs

| URL | |
|---|---|
| `GET /api/files/{id}` | The original file, inline, under its original name. |
| `GET /api/files/{id}/{preset}` | The image rendered with the preset. |

- Public, with a one day browser cache revalidated by `ETag` / `Last-Modified` (304).
- 404 for an unknown file or preset, 400 for a preset of a file that is not an image; an SVG is
  returned as is.
- `files_rate_limit` requests per minute and per IP (600 by default, 0: no limit).
- `{preset}` is documented in the OpenAPI as the list of the configured presets.

In Twig:

```twig
<img src="{{ gm_file_url(media.preview, 'card') }}" alt="">
<a href="{{ gm_file_url(media.file) }}">{{ media }}</a>
{{ gm_file_url(file, 'hero', true) }} {# absolute URL #}
```

`gm_file_url()` returns null without a file, so an optional relation can be passed as is.

## Cache

A rendered preset is stored on the disk of its file, under `images.cache_prefix` (`.cache`), then
served from there. It is purged when its file is deleted, renamed or moved (its folder too).

```bash
php bin/console gingerminds:media:cache:clear --file=<id> --media=<id>   # some files / medias
php bin/console gingerminds:media:cache:clear --force                     # everything
```

Clear the cache after changing a preset.

## Drivers

`imagick` by default. `gd` needs a GD built with WebP support for the default `webp` format, or set
`default_format` (or the `fm` of each preset) to a format your GD supports.
