import { searchMusicBrainzAlbums } from '../api/client.js';
import { getAlbums } from '../state.js';
import { albumSearchScore, clearElement, coverUrl } from '../utils/dom.js';

const MAX_LOCAL_RESULTS = 25;

let lastQuery = '';
let mbAbortController = null;
let mbRequestId = 0;

function createEmptyCover() {
    const placeholder = document.createElement('div');
    placeholder.className = 'album-card__cover album-card__cover--empty';
    placeholder.setAttribute('aria-hidden', 'true');
    return placeholder;
}

function createAlbumCard(album, { remote = false } = {}) {
    const li = document.createElement('li');
    li.className = 'album-card';

    const imageUrl = remote ? album.cover_url : coverUrl(album.cover);
    if (imageUrl) {
        const img = document.createElement('img');
        img.className = 'album-card__cover';
        img.src = imageUrl;
        img.alt = `Pochette de ${album.title || 'album'}`;
        img.loading = 'lazy';
        if (remote) {
            img.referrerPolicy = 'no-referrer';
        }
        img.addEventListener('error', () => {
            img.replaceWith(createEmptyCover());
        });
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
    const collectionSection = document.getElementById('search-collection-section');
    const mbSection = document.getElementById('search-mb-section');
    if (!results || !collectionSection || !mbSection) return;

    const hasSomething = !collectionSection.hidden || !mbSection.hidden;
    results.hidden = !hasSomething;
}

function renderLocalCollection(albums) {
    const section = document.getElementById('search-collection-section');
    const row = document.getElementById('search-collection-row');
    if (!section || !row) return;

    clearElement(row);

    if (!albums.length) {
        section.hidden = true;
        syncResultsVisibility();
        return;
    }

    albums.forEach((album) => {
        row.appendChild(createAlbumCard(album, { remote: false }));
    });
    section.hidden = false;
    syncResultsVisibility();
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

function renderLocalResults(query) {
    const needle = query.trim();
    if (!needle) {
        renderLocalCollection([]);
        return;
    }

    const ranked = getAlbums()
        .map((album) => ({ album, score: albumSearchScore(album, needle) }))
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

async function renderMusicBrainzResults(query) {
    const needle = query.trim();
    if (!needle) {
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
        const payload = await searchMusicBrainzAlbums(needle, {
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

export function renderSearchResults(query = lastQuery) {
    lastQuery = typeof query === 'string' ? query : '';
    renderLocalResults(lastQuery);
    renderMusicBrainzResults(lastQuery);
}

export function initSearch() {
    const form = document.getElementById('search-form');
    const input = document.getElementById('search-input');
    if (!form || !input) return;

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        renderSearchResults(input.value);
    });
}
