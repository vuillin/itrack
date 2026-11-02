# iTrack API

Entry point: `api.php`

## Auth

- `GET` is public.
- `POST` / `PUT` / `PATCH` / `DELETE` require header `X-Itrack-Token` matching `ITRACK_API_TOKEN`.

## Albums

### `GET api.php`

Returns all albums as a JSON array.

### `POST api.php`

Create an album.

```json
{
  "title": "OK Computer",
  "artist": "Radiohead",
  "release_year": 1997,
  "cover": "ok_computer",
  "genres": "Rock, Alternative",
  "vinyl": 1,
  "cd": 0,
  "cassette": 0,
  "boxset": 0,
  "last_listened_at": "2026-03-01",
  "user_rating": 4.5
}
```

`title` is required. Cover filenames are normalized to `.webp`.

### `PUT api.php`

Update an album. Requires `id` and `title`.

### `PATCH api.php`

Toggle favorite.

```json
{
  "id": 1,
  "is_favorite": 1
}
```

### `DELETE api.php`

```json
{
  "id": 1
}
```
