# Configuration

```bash
php bin/console config:dump-reference gingerminds_media_manager
```

Every option is optional. The defaults:

```yaml
gingerminds_media_manager:
    storage:
        default_disk: default            # disk of the new files
        disks:                           # disk name => Flysystem storage service id
            default: gingerminds_media_manager.storage.default
    library:
        root: library                    # directory of the library on the disks
        max_upload_size: 51200           # KB
        allowed_mimes: [image/jpeg, image/png, image/gif, image/webp, image/svg+xml,
                        application/pdf, application/zip, application/docx, application/xlsx, application/pptx]
        max_directory_move: 1000         # files of a folder moved from the admin, 0: no limit
        per_page: 48                     # files per page of the library browser
    images:
        driver: imagick                  # imagick | gd
        default_format: webp             # jpg | pjpg | png | gif | webp | avif
        cache_prefix: .cache             # rendered presets, on the disk of each file
        presets:
            micro: { w: 25, h: 25, fit: crop, q: 70 }
            thumbnail: { w: 150, h: 150, fit: crop, q: 80 }
            card: { w: 400, h: 300, fit: contain, q: 85 }
            hero: { w: 1280, h: 720, fit: crop, q: 90 }
    files_rate_limit: 600                # requests per minute and per IP on /api/files/*, 0: no limit
    basket:
        enabled: true
        claim_strategy: merge            # merge | replace | ignore
        ttl: 30                          # days of a guest basket, 0: forever
    resources:
        media: { entity: ~, controller: ~, form: ~ }
        media_category: { entity: ~, controller: ~, form: ~ }
        file: { entity: ~, controller: ~ }
        basket: { entity: ~ }
```

## Storage

- `disks` maps **disk names** to Flysystem storage service ids. Every file row stores the name of
  its disk (`files.disk`) and is always read on that disk: files of several disks can live side by
  side. Declare the disk names of an imported Laravel database (e.g. `public`) to keep its rows
  valid.
- `default_disk` is the disk of the uploads, and the disk the library browses (folders exist on it
  only).
- The bundle declares `gingerminds_media_manager.storage.default` (local, `var/storage/media`); a
  project storage with the same name replaces it.

## Library

- `root` is relative (no leading slash, no `..`): every upload goes under it, the library never
  reads outside. Files outside it (imported databases) are moved in by
  [`files:relocate`](Commands.md#maintenance).
- `allowed_mimes` are checked on upload, **after normalization**: the content is guessed (never the
  extension), Office documents become `application/<extension>` (`application/docx`,
  `application/xlsx`...) once their zip content is checked.
- `max_upload_size` is in KB: keep it below the PHP `upload_max_filesize` / `post_max_size` and
  the web server limit.

## Environment variables

`storage.default_disk`, `library.root` and `library.max_upload_size` are read at runtime, so they
accept placeholders:

```yaml
gingerminds_media_manager:
    storage:
        default_disk: '%env(FILE_LIBRARY_DISK)%'
    library:
        root: '%env(FILE_LIBRARY_ROOT)%'
        max_upload_size: '%env(int:FILE_LIBRARY_MAX_UPLOAD_SIZE)%'
```

`files_rate_limit`, `basket` and `resources` are read when the container is built: no placeholder.

## Images

- `presets` replaces the default presets entirely when set. A preset name is a URL segment
  (lowercase letters, digits, `_` and `-`). Besides `w`, `h`, `fit`, `q` and `fm`, any
  [Glide parameter](https://glide.thephpleague.com/2.0/api/quick-reference/) is passed through.
- `driver: gd` needs a GD built with WebP support for the default `webp` format (or set
  `default_format`). See [Images](Images.md).

## Overriding the entities

`resources.file|media|media_category|basket.entity` replaces a bundle entity by a project one,
extending the bundle mapped superclass. The bundle class is then removed from the Doctrine mapping
and from the API resources: the project class declares its table, its repository and, when the
bundle class has one, its `#[ApiResource]` (copy it from the bundle class).

```php
use Gingerminds\MediaManagerBundle\Entity\Media\BaseMedia;
use Gingerminds\MediaManagerBundle\Repository\Media\MediaRepository;

#[ApiResource(/* copied from Gingerminds\MediaManagerBundle\Entity\Media\Media */)]
#[ORM\Entity(repositoryClass: MediaRepository::class)]
#[ORM\Table(name: 'medias')]
class Media extends BaseMedia
{
    #[ORM\Column(length: 255, nullable: true)]
    #[Groups([self::GROUP_LIST, self::GROUP_READ])]
    private ?string $credits = null;
}
```

```yaml
gingerminds_media_manager:
    resources:
        media:
            entity: App\Entity\Media
```

The relations of the bundle point to the interfaces (`FileInterface`, `MediaInterface`...),
resolved to the configured entity.

A project `File` must also redeclare the hash index, Doctrine ignoring the indexes of a mapped
superclass:

```php
#[ORM\Entity(repositoryClass: FileRepository::class)]
#[ORM\Table(name: 'files')]
#[ORM\Index(name: 'files_hash_index', columns: ['hash'])]
class File extends BaseFile
{
}
```

`controller` and `form` replace the admin controller and form type of `media` and
`media_category`; `file.controller` replaces the reads of the library (see
[File library](FileLibrary.md#extending)). The media controller also serves the media picker
(`picker`, `search`).
