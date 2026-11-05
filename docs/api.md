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

## MusicBrainz search

### `GET api.php?mb_search=1&q=...&artist=...&type=album`

Search release-groups on MusicBrainz.

- `q` — album title (optional if `artist` is set)
- `artist` — artist name (optional if `q` is set)
- `type` (optional, default `album`): `album` | `ep` | `single` | `live` | `compilation` | `remix` | `soundtrack`

```json
{
  "query": "OK Computer",
  "artist": "Radiohead",
  "type": "album",
  "items": [
    {
      "mbid": "b1392450-e666-3926-a536-22c65f834433",
      "title": "OK Computer",
      "artist": "Radiohead",
      "primary_type": "Album",
      "first_release_date": "1997-05-21",
      "cover_url": "api.php?mb_cover=b1392450-e666-3926-a536-22c65f834433"
    }
  ]
}
```

### `GET api.php?mb_cover={mbid}`

Proxy + cache local for Cover Art Archive front thumbnails (`cache/covers/`).  
Returns the image bytes, or `404` when no cover exists.
