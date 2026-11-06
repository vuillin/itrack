## Setup

```bash
php -S localhost:8000
```

Open http://localhost:8000

On first run, `albums.sqlite` is created automatically from `schema.sql`.

## Project layout

| Path | Role |
|------|------|
| `index.php` | App shell |
| `api.php` | JSON API entry |
| `schema.sql` | Database schema |
| `src/` | PHP (repository, HTTP helpers) |
| `api/` | API route handlers |
| `assets/js/` | Frontend ES modules |
| `assets/css/` | Styles |
| `components/` | PHP partials |
| `views/` | Page sections |
| `albums.sqlite` | Your library (local, not committed) |
| `cache/covers/` | Temporary CAA cache for search previews (local, not committed) |
| `assets/images/covers/` | Permanent library covers (local, not committed) |
| `docs/api.md` | API reference |
| `logs/` | App error log (`app.log`) |

## Views

- **Accueil** — stats collection / favoris / écoutés
- **Collection** — albums physiques (vinyl, CD, cassette, boxset)
- **Favoris** — albums marqués favoris
- **Écoutés** — albums avec `last_listened_at`

## Covers

- **Search / preview** — proxied via `api.php?mb_cover={mbid}` (`cache/covers/`).
- **Library (écoutes / collection)** — downloaded once into `assets/images/covers/`, filename stored in `albums.cover` (e.g. `{mbid}.jpg`).

## API

See [docs/api.md](docs/api.md).
