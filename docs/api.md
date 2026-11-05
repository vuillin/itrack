# iTrack API

Entry point: `api.php`

## Auth

None — local personal app. All methods are open.

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

If `last_listened_at` is set, the album is upserted by `mbid` (create or refresh) and the full snapshot is stored (`overview`, `tracks`, genres, rating MB, etc.). Response includes the saved `album`.

### `PUT api.php`

Update an album. Requires `id` and `title`.

### `PATCH api.php`

Toggle favorite:

```json
{
  "id": 1,
  "is_favorite": 1
}
```

Or remove from listens (`unlisten: true`). If the album has no physical formats it is deleted; otherwise only `last_listened_at` is cleared.

```json
{
  "id": 1,
  "unlisten": true
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

### `GET api.php?mb_rg={mbid}`

Lookup a release-group by MBID (detail sheet).

```json
{
  "mbid": "b1392450-e666-3926-a536-22c65f834433",
  "title": "OK Computer",
  "artist": "Radiohead",
  "primary_type": "Album",
  "secondary_types": [],
  "first_release_date": "1997-05-21",
  "disambiguation": null,
  "genres": ["alternative rock", "art rock", "rock"],
  "rating": { "value": 4.5, "votes_count": 12 },
  "annotation": null,
  "description": "OK Computer is the third studio album by the English rock band Radiohead…",
  "cover_url": "api.php?mb_cover=b1392450-e666-3926-a536-22c65f834433",
  "tracks": [
    { "disc": 1, "number": "1", "title": "Airbag", "length_ms": 284000 }
  ]
}
```

`description` comes from Wikipedia/Wikidata (via MusicBrainz url-rels), falling back to the MB annotation.  
Tracklist comes from a representative official release of the release-group (extra MusicBrainz call).

### `GET api.php?mb_cover={mbid}`

Proxy + cache local for Cover Art Archive front thumbnails (`cache/covers/`).  
Returns the image bytes, or `404` when no cover exists.
