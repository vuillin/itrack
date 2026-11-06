import { searchMusicBrainzAlbums } from '../api/client.js';
import { openAlbumModal } from '../components/album-modal.js';
import { getAlbums } from '../state.js';
import { albumCoverUrl, albumSearchScore, clearElement, hasBeenListened, isOwned } from '../utils/dom.js';

const MAX_LOCAL_RESULTS = 25;

let lastTitleQuery = '';
let lastArtistQuery = '';
let mbAbortController = null;
let mbRequestId = 0;

function getTypeSelect() {
    return document.getElementById('search-type');
}

function getSelectedType() {
    const select = getTypeSelect();
    if (!select || typeof select.value !== 'string' || !select.value) {
        return 'album';
    }
    return select.value;
}

function getSelectedOption() {
    const select = getTypeSelect();
    if (!select || select.selectedIndex < 0) return null;
    return select.options[select.selectedIndex] || null;
}

function getTitleInput() {
    return document.getElementById('search-input');
}

function getArtistInput() {
    return document.getElementById('search-artist');
}

function typeLabel() {
    const option = getSelectedOption();
    const label = option?.textContent?.trim();
    return label || 'Albums';
}

function titlePlaceholder() {
    const option = getSelectedOption();
    const placeholder = option?.dataset?.placeholder?.trim();
    return placeholder || 'Album';
}

function updateMbSectionTitle() {
    const title = document.getElementById('search-mb-title');
    if (title) {
        title.textContent = typeLabel();
    }
}

function updateTitlePlaceholder() {
    const input = getTitleInput();
    if (!input) return;

    const placeholder = titlePlaceholder();
    input.placeholder = placeholder;

    const labelText = document.querySelector('label[for="search-input"] .visually-hidden');
    if (labelText) {
        labelText.textContent = placeholder;
    }
}

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

function createAlbumCard(album, { remote = false, showActions = true } = {}) {
    const li = document.createElement('li');
    li.className = 'album-card album-card--action';
    li.tabIndex = 0;
    li.setAttribute('role', 'button');
    li.setAttribute('aria-label', `${album.title || 'Album'} — ${album.artist || 'Artiste inconnu'}`);

    const open = () => openAlbumModal(album, { remote, showActions });
    li.addEventListener('click', open);
    li.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            open();
        }
    });

    const imageUrl = remote ? album.cover_url : albumCoverUrl(album);
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

function syncResultsVisibility() {
    const results = document.getElementById('search-results');
    const listenedSection = document.getElementById('search-listened-section');
    const collectionSection = document.getElementById('search-collection-section');
    const mbSection = document.getElementById('search-mb-section');
    if (!results || !listenedSection || !collectionSection || !mbSection) return;

    const hasSomething = !listenedSection.hidden || !collectionSection.hidden || !mbSection.hidden;
    results.hidden = !hasSomething;
}

function renderLocalRow(sectionId, rowId, albums, { fromListens = false } = {}) {
    const section = document.getElementById(sectionId);
    const row = document.getElementById(rowId);
    if (!section || !row) return;

    clearElement(row);

    if (!albums.length) {
        section.hidden = true;
        syncResultsVisibility();
        return;
    }

    albums.forEach((album) => {
        row.appendChild(createAlbumCard(album, {
            remote: false,
            showActions: !fromListens,
        }));
    });
    section.hidden = false;
    syncResultsVisibility();
}

function renderLocalCollection(albums) {
    const listened = albums.filter((album) => hasBeenListened(album));
    const collection = albums.filter((album) => isOwned(album));

    renderLocalRow('search-listened-section', 'search-listened-row', listened, { fromListens: true });
    renderLocalRow('search-collection-section', 'search-collection-row', collection, { fromListens: false });
}

function setMbStatus(message, { error = false } = {}) {
    const statusEl = document.getElementById('search-mb-status');
    if (!statusEl) return;

    if (!message) {
        statusEl.hidden = true;
        statusEl.textContent = '';
        statusEl.classList.remove('search-row-status--error');
        return;
    }

    statusEl.hidden = false;
    statusEl.textContent = message;
    statusEl.classList.toggle('search-row-status--error', error);
}

function renderMusicBrainzAlbums(albums) {
    const section = document.getElementById('search-mb-section');
    const row = document.getElementById('search-mb-row');
    if (!section || !row) return;

    clearElement(row);

    if (!albums.length) {
        section.hidden = true;
        syncResultsVisibility();
        return;
    }

    albums.forEach((album) => {
        row.appendChild(createAlbumCard(album, { remote: true }));
    });
    section.hidden = false;
    syncResultsVisibility();
}

function hideMusicBrainzRow() {
    const section = document.getElementById('search-mb-section');
    const row = document.getElementById('search-mb-row');
    if (row) clearElement(row);
    if (section) section.hidden = true;
    setMbStatus('');
    syncResultsVisibility();
}

function renderLocalResults(titleQuery, artistQuery) {
    const titleNeedle = titleQuery.trim();
    const artistNeedle = artistQuery.trim();
    if (!titleNeedle && !artistNeedle) {
        renderLocalCollection([]);
        return;
    }

    const ranked = getAlbums()
        .map((album) => ({
            album,
            score: albumSearchScore(album, titleNeedle, artistNeedle),
        }))
        .filter((entry) => entry.score > 0)
        .sort((a, b) => {
            if (b.score !== a.score) return b.score - a.score;
            const titleA = String(a.album.sort_title || a.album.title || '');
            const titleB = String(b.album.sort_title || b.album.title || '');
            return titleA.localeCompare(titleB, 'fr', { sensitivity: 'base' });
        })
        .slice(0, MAX_LOCAL_RESULTS)
        .map((entry) => entry.album);

    renderLocalCollection(ranked);
}

async function renderMusicBrainzResults(titleQuery, artistQuery, type = getSelectedType()) {
    const titleNeedle = titleQuery.trim();
    const artistNeedle = artistQuery.trim();
    updateMbSectionTitle();

    if (!titleNeedle && !artistNeedle) {
        if (mbAbortController) {
            mbAbortController.abort();
            mbAbortController = null;
        }
        hideMusicBrainzRow();
        return;
    }

    if (mbAbortController) {
        mbAbortController.abort();
    }

    const requestId = ++mbRequestId;
    mbAbortController = new AbortController();

    const section = document.getElementById('search-mb-section');
    const row = document.getElementById('search-mb-row');
    if (section) section.hidden = false;
    if (row) clearElement(row);
    setMbStatus('Recherche MusicBrainz…');
    syncResultsVisibility();

    try {
        const payload = await searchMusicBrainzAlbums(titleNeedle, {
            artist: artistNeedle,
            type,
            signal: mbAbortController.signal,
        });
        if (requestId !== mbRequestId) return;

        const items = Array.isArray(payload.items) ? payload.items : [];
        setMbStatus('');
        renderMusicBrainzAlbums(items);
    } catch (error) {
        if (error && error.name === 'AbortError') return;
        if (requestId !== mbRequestId) return;

        hideMusicBrainzRow();
        const sectionEl = document.getElementById('search-mb-section');
        if (sectionEl) sectionEl.hidden = false;
        setMbStatus(
            error instanceof Error ? error.message : 'Erreur MusicBrainz',
            { error: true }
        );
        syncResultsVisibility();
    }
}

export function renderSearchResults(titleQuery = lastTitleQuery, artistQuery = lastArtistQuery) {
    lastTitleQuery = typeof titleQuery === 'string' ? titleQuery : '';
    lastArtistQuery = typeof artistQuery === 'string' ? artistQuery : '';
    const type = getSelectedType();
    renderLocalResults(lastTitleQuery, lastArtistQuery);
    renderMusicBrainzResults(lastTitleQuery, lastArtistQuery, type);
}

export function initSearch() {
    const form = document.getElementById('search-form');
    const titleInput = getTitleInput();
    const artistInput = getArtistInput();
    const typeSelect = getTypeSelect();
    if (!form || !titleInput) return;

    updateMbSectionTitle();
    updateTitlePlaceholder();

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        renderSearchResults(titleInput.value, artistInput ? artistInput.value : '');
    });

    if (typeSelect) {
        typeSelect.addEventListener('change', () => {
            updateMbSectionTitle();
            updateTitlePlaceholder();
            renderSearchResults(titleInput.value, artistInput ? artistInput.value : '');
        });
    }
}
