# Baskets

A basket collects medias to download them at once as a ZIP. Guests and users have baskets: a guest
basket is identified by its token only, a user basket belongs to its owner.

## Endpoints

| Endpoint | |
|---|---|
| `POST /api/baskets` | Creates a basket, see below. 201. |
| `GET /api/baskets/{token}` | The basket and its medias. |
| `DELETE /api/baskets/{token}` | Deletes the basket. 204. |
| `POST /api/baskets/{token}/medias` | Adds medias: `{"media_ids": [1, 2, 3]}`, no duplicates. 422 for an unknown id. |
| `DELETE /api/baskets/{token}/medias/{mediaId}` | Removes a media, returns the basket. |
| `GET /api/baskets/{token}/download` | The ZIP of the files, then the basket is deleted. |

```json
{
    "@id": "/api/baskets/d7f2c3e4-6766-4736-94a2-322bf0ff1ee9",
    "@type": "Basket",
    "token": "d7f2c3e4-6766-4736-94a2-322bf0ff1ee9",
    "expires_at": "2026-11-07T10:26:37+01:00",
    "medias": [{ "id": 12, "code": "tractor-red", "name": "Red tractor", "file": "0199b0c4-...", "...": "..." }]
}
```

The medias have the fields of [`/api/media`](API.md#medias).

## Guests and users

- **Without authentication**, `POST /api/baskets` creates a guest basket.
- **Authenticated** (bearer token of the core API), it creates the basket of the user, replacing
  the previous one: a user has one basket. Send `{"anonymous_token": "<guest token>"}` to take over
  the guest basket of the visitor, according to `basket.claim_strategy`:
  - `merge` (default): the guest medias are added to the user basket;
  - `replace`: the user basket gets the guest medias only;
  - `ignore`: the guest medias are dropped.

  The guest basket is deleted in every case.
- The **login** response of the core API (`POST /api/login`) contains the `basket_token` of the
  user, the basket being created when missing: no extra request after login.

## Access

- A guest basket is open to whoever has its token (a random UUID): do not leak it.
- A user basket is open to its owner only: 403 for anyone else, guests included.
- An unknown or expired basket is a 404.

The rules are in `BasketVoter` (`BASKET_VIEW`, `BASKET_MODIFY`, `BASKET_DOWNLOAD`,
`BASKET_DELETE`): decorate or replace it to change them.

## Download

`GET /api/baskets/{token}/download` returns `basket.zip`: the file of each media, read on its own
disk, under its original name (`photo (2).png` for a second `photo.png`). The basket is then
**deleted**: downloading consumes it. 422 when the basket is empty or when none of its files exists.

## Expiration

A guest basket expires `basket.ttl` days (30 by default, 0: never) after its last change; a user
basket never expires (`expires_at` is null). An expired basket is a 404; delete them from a cron:

```bash
php bin/console gingerminds:media:basket:purge
```

## Data

`baskets` (`token` unique, `owner_id` to the core users, deleted with the user, `expires_at`,
timestamps) and `basket_media` (deleted with the basket or the media). A media in a basket can be
deleted: it leaves the basket. Overridable through `resources.basket.entity` (extending
`BaseBasket`, in `Gingerminds\MediaManagerBundle\Basket\Entity`).

## Disabling

```yaml
gingerminds_media_manager:
    basket:
        enabled: false
```

removes the baskets entirely: no Doctrine mapping (no tables), no API resource or route, no voter,
no `basket_token` in the login response.
