# API

Public endpoints of the bundle, under the API prefix of the core (`/api`), in JSON-LD. The lists
take the core list parameters: `page`, `itemsPerPage`, `sortBy`, `sort`, `filters[...]`,
`filters[search]`.

## Files

| Endpoint | |
|---|---|
| `GET /api/files/{id}` | The original file. |
| `GET /api/files/{id}/{preset}` | The image rendered with a preset. |

See [Images](Images.md): cache headers, errors, rate limit.

## Medias

`GET /api/media` and `GET /api/media/{id}`:

```json
{
    "@id": "/api/media/12",
    "@type": "Media",
    "id": 12,
    "code": "tractor-red",
    "name": "Red tractor",
    "file": "0199b0c4-7a7c-7c5e-9d3e-5b8f8a6c1d2e",
    "file_reference": "0199b0c4-7a7c-7c5e-9d3e-5b8f8a6c1d2e",
    "file_size": 52413,
    "file_type": "application/pdf",
    "thumbnail_reference": "0199b0c4-8b1d-7a2e-8c4f-2d6e9a1b3c5f",
    "thumbnail_size": 8120,
    "media_category_id": 3
}
```

- `file` and `file_reference` are the id of the file, always: build the URL with
  `/api/files/{file}` or `/api/files/{file}/{preset}`. For the preview of a media that is not an
  image, use `thumbnail_reference`.
- Filters: `filters[search]` (code, name), `filters[category]` or the Laravel name
  `filters[media_category_id]` (a category id, a list `[]=`, or `null` for the medias without
  category). The category filter is exact, without the subcategories.
- Sort: `sortBy=name|code|createdAt|category.name`.

## Media categories

| Endpoint | |
|---|---|
| `GET /api/media_categories` | Paginated (200 per page, 500 max), ordered by position: `id`, `code`, `name`, `position`, `parent_id`. |
| `GET /api/media_categories/{id}` | A category with its `children`. |
| `GET /api/media_categories/tree` | The root categories with their nested `children` (every level), not paginated. |

## Baskets

See [Baskets](Basket.md): `/api/baskets` and the `basket_token` of the login response.

## Cache

The media and category responses go through the core API response cache (`media`,
`media_category` tags), invalidated when an entity changes; a category change also invalidates the
medias. A project media override exposing more fields keeps this behaviour.
