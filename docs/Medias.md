# Medias

A **media** is a file of the library published as a resource: a code, a name, the file, an optional
thumbnail and a category. Medias are listed by the API and picked by the
[`MediaSelectType`](Forms.md#mediaselecttype) fields.

## Media

`Media` (`medias` table, overridable through `resources.media.entity`):

| Field | Column | Notes |
|---|---|---|
| `code` | `code` | Required, unique, searchable. |
| `name` | `name` | Optional: the name of the file when left empty. |
| `file` | `file_id` | Required, `RESTRICT`. |
| `thumbnail` | `thumbnail_id` | Optional, an image, `RESTRICT`: shown instead of a file that is not an image. |
| `category` | `media_category_id` | Optional, set to null when the category is deleted. |

`getPreview()` is the file when it is an image, else the thumbnail.

### Admin

`/admin/medias` ("Medias" in the media library menu), `view|edit|delete medias`:

- list with the preview, the category and the file, searched on the code and the name, filtered by
  category, sorted;
- form: code, name, the file and the thumbnail picked in the library, the category in the tree;
  `/admin/medias/new?category_id=` preselects the category.

### Deleting

- Deleting a media **keeps its files** in the library.
- A **used media cannot be deleted**: any Doctrine association to `MediaInterface` (a
  [media link](Forms.md#collections-of-medias), a project relation) counts, the admin shows the
  number of usages. Baskets do not count: a media in a basket can be deleted.
- Declare your own relations to `MediaInterface` with `onDelete: 'RESTRICT'`.

## Media categories

`MediaCategory` (`media_categories` table, overridable through `resources.media_category.entity`):
a unique `code`, a `name`, a `parent` and a `position` among its siblings.

`/admin/media-categories`, `view|edit|delete media_categories`:

- the tree, ordered by drag & drop (`POST /admin/media-categories/reorder`, one level at a time);
- "Add a child", the parent picked in the indented tree (a category cannot go into itself or one
  of its descendants); a new or moved category goes to the end of its level;
- a category with **subcategories** or **medias** cannot be deleted: move them first.

`MediaCategoryRepository::findTree()` loads the whole tree in one query, `findFlatTree()` returns
it depth first with the depth of each category, `findIdsWithDescendants()` the ids of categories
and of all their descendants.

## API and cache

`GET /api/media`, `GET /api/media_categories` and `GET /api/media_categories/tree`: see [API](API.md).

The responses are cached by the core API cache: `media` and `media_category` tags, a change of a
category invalidates the medias.
