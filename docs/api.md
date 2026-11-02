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

### `GET api.php?mb_search=1&q=...`

Search album/EP release-groups on MusicBrainz.

```json
{
  "query": "radiohead",
  "items": [
    {
      "mbid": "b1392450-e666-3926-a536-22c65f834433",
      "title": "OK Computer",
      "artist": "Radiohead",
      "primary_type": "Album",
      "first_release_date": "1997-05-21",
      "cover_url": "https://coverartarchive.org/release-group/b1392450-e666-3926-a536-22c65f834433/front-250"
    }
  ]
}
```
