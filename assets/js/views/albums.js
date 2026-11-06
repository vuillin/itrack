import { getAlbums } from '../state.js';
import { albumCoverUrl, clearElement, hasBeenListened } from '../utils/dom.js';
import { openAlbumModal } from '../components/album-modal.js';

/** @type {'default' | 'artist' | 'year'} */
let currentSort = 'default';
/** @type {{ artist: 'asc' | 'desc', year: 'asc' | 'desc' }} */
const sortDirs = {
    artist: 'asc',
    year: 'desc',
};
let sortInitialized = false;

function compareText(a, b) {
    return String(a || '').localeCompare(String(b || ''), 'fr', { sensitivity: 'base' });
}

function albumYear(album) {
    const direct = Number(album?.release_year);
    if (Number.isFinite(direct) && direct > 0) {
        return direct;
    }
    const date = typeof album?.first_release_date === 'string' ? album.first_release_date : '';
    const match = date.match(/^(\d{4})/);
    return match ? Number(match[1]) : null;
}

function albumArtistLabel(album) {
    const artist = typeof album?.artist === 'string' ? album.artist.trim() : '';
    return artist || 'Artiste inconnu';
}

function bindAlbumOpen(el, album) {
    const open = () => openAlbumModal(album, { remote: false, showActions: false });
    el.addEventListener('click', open);
    el.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            open();
        }
    });
}

function createEmptyCover(className = 'album-card__cover album-card__cover--empty') {
    const placeholder = document.createElement('div');
    placeholder.className = className;
    placeholder.setAttribute('aria-hidden', 'true');

    const logo = document.createElement('img');
    logo.className = className.includes('album-row')
        ? 'album-row__cover-logo'
        : 'album-card__cover-logo';
    logo.src = 'assets/images/assets/itrack.ico';
    logo.alt = '';
    logo.draggable = false;
    placeholder.appendChild(logo);

    return placeholder;
}

function createAlbumCard(album) {
    const li = document.createElement('li');
    li.className = 'album-card album-card--action';
    li.tabIndex = 0;
    li.setAttribute('role', 'button');
    li.setAttribute('aria-label', `${album.title || 'Album'} — ${album.artist || 'Artiste inconnu'}`);
    bindAlbumOpen(li, album);

    const imageUrl = albumCoverUrl(album);
    if (imageUrl) {
        const img = document.createElement('img');
        img.className = 'album-card__cover';
        img.src = imageUrl;
        img.alt = `Pochette de ${album.title || 'album'}`;
        img.loading = 'lazy';
        img.draggable = false;
        img.addEventListener('error', () => {
            img.replaceWith(createEmptyCover());
        }, { once: true });
        li.appendChild(img);
    } else {
        li.appendChild(createEmptyCover());
    }

    const title = document.createElement('h3');
    title.className = 'album-card__title';
    title.textContent = album.title || 'Sans titre';
    li.appendChild(title);

    const artist = document.createElement('p');
    artist.className = 'album-card__artist';
    artist.textContent = album.artist || 'Artiste inconnu';
    li.appendChild(artist);

    return li;
}

function createAlbumRow(album) {
    const li = document.createElement('li');
    li.className = 'album-row';
    li.tabIndex = 0;
    li.setAttribute('role', 'button');
    li.setAttribute('aria-label', `${album.title || 'Album'} — ${album.artist || 'Artiste inconnu'}`);
    bindAlbumOpen(li, album);

    const imageUrl = albumCoverUrl(album);
    if (imageUrl) {
        const img = document.createElement('img');
        img.className = 'album-row__cover';
        img.src = imageUrl;
        img.alt = '';
        img.loading = 'lazy';
        img.draggable = false;
        img.addEventListener('error', () => {
            img.replaceWith(createEmptyCover('album-row__cover album-row__cover--empty'));
        }, { once: true });
        li.appendChild(img);
    } else {
        li.appendChild(createEmptyCover('album-row__cover album-row__cover--empty'));
    }

    const title = document.createElement('span');
    title.className = 'album-row__title';
    title.textContent = album.title || 'Sans titre';
    li.appendChild(title);

    return li;
}

function createAlbumGrid(albums, ariaLabel) {
    const grid = document.createElement('ul');
    grid.className = 'albums-grid';
    if (ariaLabel) {
        grid.setAttribute('aria-label', ariaLabel);
    }
    albums.forEach((album) => {
        grid.appendChild(createAlbumCard(album));
    });
    return grid;
}

function createAlbumList(albums, ariaLabel) {
    const list = document.createElement('ul');
    list.className = 'albums-list';
    if (ariaLabel) {
        list.setAttribute('aria-label', ariaLabel);
    }
    albums.forEach((album) => {
        list.appendChild(createAlbumRow(album));
    });
    return list;
}

function createGroup(title, albums) {
    const section = document.createElement('section');
    section.className = 'albums-group';

    const heading = document.createElement('h2');
    heading.className = 'albums-group__title';
    heading.textContent = title;
    section.appendChild(heading);
    section.appendChild(createAlbumList(albums, title));

    return section;
}

function sortByListenDate(albums) {
    return [...albums].sort((a, b) => {
        const dateA = String(a.last_listened_at || '');
        const dateB = String(b.last_listened_at || '');
        if (dateA !== dateB) return dateB.localeCompare(dateA);
        return compareText(a.sort_title || a.title, b.sort_title || b.title);
    });
}

function sortWithinArtist(albums) {
    return [...albums].sort((a, b) => {
        const yearA = albumYear(a);
        const yearB = albumYear(b);
        if (yearA != null && yearB != null && yearA !== yearB) {
            return yearB - yearA;
        }
        if (yearA != null && yearB == null) return -1;
        if (yearA == null && yearB != null) return 1;
        return compareText(a.sort_title || a.title, b.sort_title || b.title);
    });
}

function sortWithinYear(albums) {
    return [...albums].sort((a, b) => {
        const artistCmp = compareText(albumArtistLabel(a), albumArtistLabel(b));
        if (artistCmp !== 0) return artistCmp;
        return compareText(a.sort_title || a.title, b.sort_title || b.title);
    });
}

function groupByArtist(albums) {
    /** @type {Map<string, { label: string, albums: typeof albums }>} */
    const groups = new Map();

    albums.forEach((album) => {
        const label = albumArtistLabel(album);
        const key = label.toLocaleLowerCase('fr');
        const existing = groups.get(key);
        if (existing) {
            existing.albums.push(album);
            return;
        }
        groups.set(key, { label, albums: [album] });
    });

    const ordered = [...groups.values()]
        .sort((a, b) => compareText(a.label, b.label));
    if (sortDirs.artist === 'desc') {
        ordered.reverse();
    }

    return ordered.map((group) => ({
        title: group.label,
        albums: sortWithinArtist(group.albums),
    }));
}

function groupByYear(albums) {
    /** @type {Map<number | 'unknown', typeof albums>} */
    const groups = new Map();

    albums.forEach((album) => {
        const year = albumYear(album);
        const key = year == null ? 'unknown' : year;
        const existing = groups.get(key);
        if (existing) {
            existing.push(album);
            return;
        }
        groups.set(key, [album]);
    });

    const dir = sortDirs.year === 'desc' ? 1 : -1;
    return [...groups.entries()]
        .sort(([a], [b]) => {
            if (a === 'unknown') return 1;
            if (b === 'unknown') return -1;
            return (b - a) * dir;
        })
        .map(([key, items]) => ({
            title: key === 'unknown' ? 'Sans année' : String(key),
            albums: sortWithinYear(items),
        }));
}

function sortDirLabel(sort, dir) {
    if (sort === 'artist') {
        return dir === 'asc' ? 'A à Z' : 'Z à A';
    }
    return dir === 'desc' ? 'récent → ancien' : 'ancien → récent';
}

function syncSortButtons() {
    const toolbar = document.getElementById('listens-sort');
    if (!toolbar) return;

    toolbar.querySelectorAll('[data-sort]').forEach((button) => {
        const sort = button.getAttribute('data-sort');
        const active = sort === currentSort;
        button.classList.toggle('is-active', active);
        button.setAttribute('aria-pressed', active ? 'true' : 'false');

        if (sort !== 'artist' && sort !== 'year') return;

        const dir = sortDirs[sort];
        const arrow = button.querySelector('[data-sort-dir]');
        if (arrow) {
            arrow.textContent = dir === 'asc' ? '↑' : '↓';
        }
        const name = sort === 'artist' ? 'Artiste' : 'Année';
        button.setAttribute('aria-label', `${name}, ${sortDirLabel(sort, dir)}`);
    });
}

function initListensSort() {
    if (sortInitialized) return;
    const toolbar = document.getElementById('listens-sort');
    if (!toolbar) return;

    sortInitialized = true;
    toolbar.addEventListener('click', (event) => {
        const button = event.target instanceof Element
            ? event.target.closest('[data-sort]')
            : null;
        if (!(button instanceof HTMLElement)) return;

        const next = button.getAttribute('data-sort');
        if (next !== 'default' && next !== 'artist' && next !== 'year') return;

        if (next === currentSort) {
            if (next === 'artist' || next === 'year') {
                sortDirs[next] = sortDirs[next] === 'asc' ? 'desc' : 'asc';
                syncSortButtons();
                renderListenedAlbums();
            }
            return;
        }

        currentSort = next;
        syncSortButtons();
        renderListenedAlbums();
    });
}

export function renderListenedAlbums() {
    const root = document.getElementById('albums-listened-root');
    if (!root) return;

    initListensSort();
    syncSortButtons();
    clearElement(root);

    const listened = getAlbums().filter((album) => hasBeenListened(album));

    if (!listened.length) {
        const grid = document.createElement('ul');
        grid.className = 'albums-grid';
        grid.setAttribute('aria-label', 'Albums écoutés');
        const empty = document.createElement('li');
        empty.className = 'albums-grid__empty';
        empty.textContent = 'Aucun album écouté pour le moment.';
        grid.appendChild(empty);
        root.appendChild(grid);
        return;
    }

    if (currentSort === 'default') {
        root.appendChild(createAlbumGrid(sortByListenDate(listened), 'Albums écoutés'));
        return;
    }

    const groups = currentSort === 'artist'
        ? groupByArtist(listened)
        : groupByYear(listened);

    groups.forEach((group) => {
        root.appendChild(createGroup(group.title, group.albums));
    });
}
