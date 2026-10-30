# File library

The library is the **single place files enter the application**: they are uploaded there, organised
in folders, then picked by the form fields ([`FilePickerType`](Forms.md#filepickertype)). There is
no direct upload in the other forms, so a file can be reused, its usages are known and it is never
stored twice.

A file is a row of `files` (`File`: UUID id, `disk`, `path`, `mime_type`, `original_name`, `size`,
sha256 `hash`) and a physical file on its disk, under `library.root`.

## Admin page

`/admin/files` ("File library" in the media library menu):

- folder tree, search (on the file name only, the folders are hidden while searching), filters
  (type, creation dates, subfolders, unused, duplicates) and sort;
- a grid of files with their usage count, a detail panel (preview, usages linking to their edit
  page, duplicates);
- uploads by button or drag & drop: a content already in the library is not stored again, the
  existing file is reported;
- files: rename, move, delete, merge the duplicates;
- images: an alternative text (`alt`), edited in the detail panel, one per file whatever the
  locale (a resource needing another one per usage declares its own field); merging duplicates
  keeps it, or takes the one of a duplicate when the kept file has none;
- folders, selected with their checkbox (files or folders, never both): new, rename, move,
  delete (empty folders only).

## Uploads

- type checked on the content (`library.allowed_mimes`, after normalization), size checked
  (`library.max_upload_size`);
- the file name is slugged (`Rapport final.PDF` gives `rapport-final.pdf`), `-1`, `-2`... when the
  name is taken; the original name is kept as the display name;
- choosing the free name, writing the file and saving its row happen under a `symfony/lock` lock
  (renames, moves and `files:relocate` too): two simultaneous uploads of `photo.jpg` give
  `photo.jpg` and `photo-1.jpg`, never one file for two rows. Several rows may still share a path
  (imported Laravel uploads), so `(disk, path)` is not unique: the physical file is deleted with
  its last row;
- a directory being moved is locked: an upload, a move or a new directory into it (or below)
  waits for the end of the move instead of being left behind and deleted with the old directory;
- the content is hashed: uploading a known content returns the existing file instead, also when
  two uploads of that content run at the same time (locked by hash).

## Usages and references

The **file reference registry** knows where every file is used:

- every **Doctrine association** to `FileInterface` is found in the mapping, nothing to declare
  (a `ManyToOne`, a `ManyToMany`, the files of a media...);
- file ids stored in a **JSON field** (content blocks...) are declared as a source:

```yaml
# config/services.yaml
services:
    app.page_blocks_file_references:
        class: Gingerminds\MediaManagerBundle\File\Reference\JsonFieldFileReferenceSource
        arguments:
            $entityClass: App\Entity\Page
            $field: content
```

Other storages implement `FileReferenceSourceInterface` (autoconfigured).

A JSON field has no database integrity: the ids are searched as strings (reliable for UUIDs) with
`LIKE`, which does not work on a Postgres `jsonb` column (use `json` or `text`).

A usage is shown as the label of its owner and a link to its edit page, found through its
`gingerminds_core` resource. For an owner without admin resource, add a resolver
(autoconfigured, by priority, the first non null wins):

```php
final class PageUsageResolver implements FileUsageResolverInterface
{
    public function resolve(FileReference $reference): ?FileUsage
    {
        return $reference->owner instanceof Page
            ? new FileUsage('Page', $reference->owner->title, '/pages/' . $reference->owner->getId())
            : null;
    }
}
```

### Deleting

A **used file cannot be deleted**: the admin keeps it and lists its usages,
`FileLibrary::deleteFile()` throws `FileInUseException`. The presets of a deleted file are purged;
its physical file is kept while another row still points to it.

Never put `onDelete: CASCADE` on a relation to `files`: deleting a file must not delete the
entities using it. Use `RESTRICT` (or the default) for your own relations, as the bundle does.

### Duplicates

Two rows with the same hash are duplicates (imported databases: uploads never create one).
"Keep this file and merge its duplicates" points every reference to the kept file
(`FileLibrary::mergeDuplicates()`, through the entities: the API cache is invalidated), then
deletes the duplicates. [`files:deduplicate`](Commands.md#maintenance) does it for the whole
library.

## Folders

Folders exist on the default disk only. Moving or renaming a folder takes everything with it (the
files missing from `files` too), updates the paths of the rows and purges their presets; the
references keep working, they hold ids. A folder that fails is moved back. The root cannot be
moved, nor a folder into itself or onto an existing folder.

The admin moves up to `library.max_directory_move` files at once (1000 by default); beyond, use
[`gingerminds:media:directory:move`](Commands.md#folders).

A form field or a project pointing at a folder by its name (`start_path`,
`LibraryStartPathProviderInterface`) does not follow it.

## Permissions

| Permission | Allows |
|---|---|
| `view files` | browse the library, see the details and usages, pick files in a form |
| `edit files` | everything else: upload, folders, rename, move, delete, merge |

Deleting needs `edit files` (`resources.file.permissions`): there is no `delete files` permission.

## Extending

- **Opening folder**: the browser opens on the root. Alias `LibraryStartPathProviderInterface` to
  your service to open elsewhere (e.g. the folder of the current site):

```yaml
services:
    Gingerminds\MediaManagerBundle\File\LibraryStartPathProviderInterface: '@App\Media\SiteStartPathProvider'
```

- **Reads** (page, picker, folder tree): `resources.file.controller`, extending
  `FileLibraryController` (`browser()` gives the template variables).
- **Writes** (upload, folders, rename, move, delete, merge): decorate the
  `gingerminds_media_manager.controller.admin.file_library_action` service
  (`FileLibraryActionController`).
- **Services**: `FileLibrary` (library operations), `FileStorage` (store, read, delete on the
  disks), `FileReferenceRegistry` (usages, `isUsed()`, `usedIds()`, `replace()`), `DiskRegistry`.
- In Twig, `gm_file_url(file, preset = null, absolute = false)` gives the URL of a file or of a
  preset.

The library is rendered by the server in a Turbo frame (`gm-file-browser`): its state is in the
query (`path`, the filters, `page`, `file` for the detail panel, `dialog` with the selected
`ids[]` / `paths[]`, shown in a modal), and the page advances the URL (back button, shareable
links). The toolbar actions are enabled by the selection: move and delete by any file or folder,
rename by a single folder. The folder tree only lets open the folders that have subfolders, loaded
when opened. The writes are
forms posted with the `gm-file-library` CSRF token and a `return` URL (the browser), a library
error coming back as a flash message. Only the upload answers JSON: one file a request, posted by
the `gm-file-upload` Stimulus controller with its progress.
