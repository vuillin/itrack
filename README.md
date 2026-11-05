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
| `cache/covers/` | Cached MusicBrainz cover art (local, not committed) |
| `docs/api.md` | API reference |
| `logs/` | App error log (`app.log`) |

## Views

- **Accueil** — stats collection / favoris / écoutés
- **Collection** — albums physiques (vinyl, CD, cassette, boxset)
- **Favoris** — albums marqués favoris
- **Écoutés** — albums avec `last_listened_at`

## Covers

Filenames in the DB, files on disk in `assets/images/covers/`.  
Store as `.webp`. In the DB, keep just the filename (`ok_computer.webp`).

## API

See [docs/api.md](docs/api.md).
