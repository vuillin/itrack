import { getAlbums } from '../state.js';
import { albumCoverUrl, clearElement, hasBeenListened } from '../utils/dom.js';
import { openAlbumModal } from '../components/album-modal.js';

function createEmptyCover() {
    const placeholder = document.createElement('div');
    placeholder.className = 'album-card__cover album-card__cover--empty';
    placeholder.setAttribute('aria-hidden', 'true');

    const logo = document.createElement('img');
    logo.className = 'album-card__cover-logo';
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

    const open = () => openAlbumModal(album, { remote: false, showActions: false });
    li.addEventListener('click', open);
    li.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            open();
        }
    });

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

export function renderListenedAlbums() {
    const grid = document.getElementById('albums-listened-grid');
    if (!grid) return;

    clearElement(grid);

    const listened = getAlbums()
        .filter((album) => hasBeenListened(album))
        .sort((a, b) => {
            const dateA = String(a.last_listened_at || '');
            const dateB = String(b.last_listened_at || '');
            if (dateA !== dateB) return dateB.localeCompare(dateA);
            const titleA = String(a.sort_title || a.title || '');
            const titleB = String(b.sort_title || b.title || '');
            return titleA.localeCompare(titleB, 'fr', { sensitivity: 'base' });
        });

    if (!listened.length) {
        const empty = document.createElement('li');
        empty.className = 'albums-grid__empty';
        empty.textContent = 'Aucun album écouté pour le moment.';
        grid.appendChild(empty);
        return;
    }

    listened.forEach((album) => {
        grid.appendChild(createAlbumCard(album));
    });
}
