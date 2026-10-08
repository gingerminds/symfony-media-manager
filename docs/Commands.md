# Commands

| Command | |
|---|---|
| `gingerminds:media:cache:clear` | Clears the rendered image presets. |
| `gingerminds:media:directory:move` | Moves or renames a folder of the library. |
| `gingerminds:media:basket:purge` | Deletes the expired guest baskets. |
| `gingerminds:media:files:hash` | Computes the hash of the files without one. |
| `gingerminds:media:files:index` | Creates the rows of the files on the disk missing from the database. |
| `gingerminds:media:files:deduplicate` | Merges the files with the same content. |
| `gingerminds:media:files:relocate` | Moves the files outside the library root into it. |
| `gingerminds:media:files:orphans` | Lists, and deletes, the files used nowhere. |

`gingerminds:permissions:sync` (core) creates the permissions of the bundle.

## Image cache

```bash
php bin/console gingerminds:media:cache:clear                 # everything, after a confirmation
php bin/console gingerminds:media:cache:clear --force         # everything, no confirmation
php bin/console gingerminds:media:cache:clear --file=<id> --file=<id>
php bin/console gingerminds:media:cache:clear --media=<id>    # the file and the thumbnail of a media
```

## Folders

```bash
php bin/console gingerminds:media:directory:move <path> [<parent>] [--name=<new name>]
```

Moves `<path>` (relative to the library root) under `<parent>` (`""` for the root; its current
parent by default), renamed with `--name`. Same rules as the admin, without the
`library.max_directory_move` limit: use it for the big folders.

```bash
php bin/console gingerminds:media:directory:move news/2024 archives
php bin/console gingerminds:media:directory:move news/2024 --name="2024 news"
```

## Baskets

```bash
php bin/console gingerminds:media:basket:purge
```

Deletes the guest baskets past their `expires_at`. Run it every day from a cron.

## Maintenance

For the databases imported from Laravel and the files copied on the disk by hand. Every command
works in batches and can run on a big library; the ones changing data have a `--dry-run`.

### `files:hash [--force]`

Computes the sha256 of the files without one (imported rows), reading each file on its disk;
`--force` hashes every file again. The files missing on their disk are listed.

### `files:index [--path=<folder>] [--dry-run]`

Creates the rows of the files present on the library disk under `library.root` (or `--path`) but
missing from `files`, whatever their type; hidden folders (`.cache`...) are skipped. Type, size and
hash are computed as for an upload, the file name becomes the display name.

### `files:deduplicate [--dry-run]`

Groups the files by hash, keeps the oldest of each group and merges the others into it: every
reference points to the kept file, the duplicates are deleted (a physical file still used by another
row is kept). Prints a table of the merges. Run `files:hash` first.

### `files:relocate [--dry-run]`

Moves the files outside `library.root` (e.g. `uploads/2024/...` of a Laravel database) to the first
level of the root, on their own disk; `name-1.ext` when the name is taken. The paths are updated,
the presets purged, the rows sharing a physical file move together. The files missing on their
disk are listed and left as they are.

### `files:orphans [--delete] [--older-than=<days>]`

Lists the files used nowhere (see the [reference registry](FileLibrary.md#usages-and-references)),
with their size and date; `--older-than` keeps the ones created more than that many days ago.
`--delete` deletes them, the usages being checked again: a file used in the meantime is kept.

```bash
php bin/console gingerminds:media:files:orphans --older-than=90 --delete
```
