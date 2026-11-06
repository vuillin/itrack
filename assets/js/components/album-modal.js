import {
    fetchMusicBrainzReleaseGroup,
    markAlbumListened,
    unlistenAlbum,
    uploadAlbumCover,
} from '../api/client.js';
import { removeAlbum, upsertAlbum } from '../state.js';
import { albumCoverUrl } from '../utils/dom.js';
import { toastError, toastSuccess } from './toast.js';

let overlay = null;
let dialog = null;
let bodyEl = null;
let titleEl = null;
let actionsEl = null;
let listenBtn = null;
let abortController = null;
let requestId = 0;
let lastFocus = null;
let currentAlbum = null;
let detailReady = false;
let listenBusy = false;
let manageListens = false;
let unlistenStep = 0;
let unlistenBusy = false;
let coverUploadBusy = false;

const EDIT_COVER_ICON = `
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="22" height="22" fill="currentColor" aria-hidden="true">
  <path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zm17.71-10.04a1.003 1.003 0 0 0 0-1.42l-2.5-2.5a1.003 1.003 0 0 0-1.42 0l-1.83 1.83 3.75 3.75 1.999-1.66z"/>
</svg>
`;

function yearFromDate(value) {
    if (typeof value !== 'string' || value === '') return '';
    const match = value.match(/^(\d{4})/);
    return match ? match[1] : '';
}

function normalizeSeed(seed = {}, { remote = false } = {}) {
    const genres = (Array.isArray(seed.genres)
        ? seed.genres.filter((g) => typeof g === 'string' && g.trim())
        : typeof seed.genres === 'string'
            ? seed.genres.split(',').map((g) => g.trim()).filter(Boolean)
            : []
    ).slice(0, 3);

    const secondary = Array.isArray(seed.secondary_types)
        ? seed.secondary_types.filter((t) => typeof t === 'string' && t.trim())
        : [];

    const tracks = Array.isArray(seed.tracks)
        ? seed.tracks
            .filter((track) => track && typeof track.title === 'string' && track.title.trim())
            .map((track) => ({
                disc: Number(track.disc) > 0 ? Number(track.disc) : 1,
                number: track.number != null ? String(track.number) : '',
                title: track.title.trim(),
                length_ms: Number.isFinite(Number(track.length_ms)) ? Number(track.length_ms) : null,
            }))
        : [];

    return {
        id: seed.id != null ? Number(seed.id) : null,
        mbid: typeof seed.mbid === 'string' ? seed.mbid : '',
        title: seed.title || 'Sans titre',
        artist: seed.artist || 'Artiste inconnu',
        primary_type: seed.primary_type || '',
        secondary_types: secondary,
        first_release_date: seed.first_release_date || null,
        release_year: seed.release_year || null,
        disambiguation: seed.disambiguation || null,
        genres,
        rating: seed.rating && typeof seed.rating === 'object' ? seed.rating : null,
        description: seed.description || seed.annotation || seed.overview || null,
        cover: typeof seed.cover === 'string' ? seed.cover : '',
        cover_v: seed.cover_v != null ? Number(seed.cover_v) : null,
        cover_url: albumCoverUrl(seed),
        tracks,
        last_listened_at: seed.last_listened_at || null,
    };
}

function todayDate() {
    return new Date().toISOString().slice(0, 10);
}

function syncListenButton() {
    if (!listenBtn) return;
    const canListen = Boolean(currentAlbum?.title) && detailReady && !listenBusy;
    listenBtn.disabled = !canListen;
    listenBtn.classList.toggle('is-busy', listenBusy);
}

function buildListenPayload(album) {
    const year = yearFromDate(album.first_release_date) || album.release_year || null;
    return {
        title: album.title,
        artist: album.artist,
        mbid: album.mbid || null,
        release_year: year ? Number(year) : null,
        first_release_date: album.first_release_date,
        primary_type: album.primary_type || null,
        secondary_types: album.secondary_types || [],
        disambiguation: album.disambiguation || null,
        genres: album.genres || [],
        description: album.description || null,
        overview: album.description || null,
        tracks: album.tracks || [],
        rating: album.rating || null,
        last_listened_at: todayDate(),
    };
}

async function handleListenClick() {
    if (!currentAlbum || !detailReady || listenBusy) return;

    listenBusy = true;
    syncListenButton();

    try {
        const result = await markAlbumListened(buildListenPayload(currentAlbum));
        if (result?.album) {
            upsertAlbum(result.album);
            document.dispatchEvent(new CustomEvent('itrack:albums-changed'));
        }
        toastSuccess('Ajouté à tes écoutes');
        closeAlbumModal();
    } catch (error) {
        toastError(error instanceof Error ? error.message : 'Impossible d’enregistrer l’écoute');
    } finally {
        listenBusy = false;
        syncListenButton();
    }
}

function renderUnlistenButton() {
    if (!manageListens || !currentAlbum?.id) return null;

    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'album-modal__unlisten';
    btn.disabled = unlistenBusy;

    if (unlistenStep === 0) {
        btn.textContent = 'Retirer des écoutes';
    } else {
        btn.classList.add('album-modal__unlisten--confirm');
        btn.textContent = 'Confirmer le retrait';
    }

    btn.addEventListener('click', () => {
        handleUnlistenClick();
    });

    return btn;
}

async function handleUnlistenClick() {
    if (!manageListens || !currentAlbum?.id || unlistenBusy) return;

    if (unlistenStep === 0) {
        unlistenStep = 1;
        renderAlbum(currentAlbum);
        return;
    }

    unlistenBusy = true;
    renderAlbum(currentAlbum);

    try {
        const result = await unlistenAlbum(currentAlbum.id);
        if (result?.deleted) {
            removeAlbum(currentAlbum.id);
        } else if (result?.album) {
            upsertAlbum(result.album);
        } else {
            removeAlbum(currentAlbum.id);
        }
        document.dispatchEvent(new CustomEvent('itrack:albums-changed'));
        toastSuccess('Retiré des écoutes');
        closeAlbumModal();
    } catch (error) {
        toastError(error instanceof Error ? error.message : 'Impossible de retirer des écoutes');
        unlistenStep = 0;
    } finally {
        unlistenBusy = false;
        if (!overlay?.hidden) {
            renderAlbum(currentAlbum);
        }
    }
}

function formatTrackLength(lengthMs) {
    const totalSeconds = Math.round(Number(lengthMs) / 1000);
    if (!Number.isFinite(totalSeconds) || totalSeconds <= 0) return '';
    const minutes = Math.floor(totalSeconds / 60);
    const seconds = String(totalSeconds % 60).padStart(2, '0');
    return `${minutes}:${seconds}`;
}

function renderTracklist(tracks) {
    if (!tracks.length) return null;

    const section = document.createElement('section');
    section.className = 'album-modal__tracks';
    section.setAttribute('aria-label', 'Pistes');

    const heading = document.createElement('h3');
    heading.className = 'album-modal__tracks-title';
    heading.textContent = 'Pistes';
    section.appendChild(heading);

    const list = document.createElement('ol');
    list.className = 'album-modal__track-list';

    let lastDisc = null;
    const multiDisc = tracks.some((track) => track.disc > 1);

    tracks.forEach((track) => {
        if (multiDisc && track.disc !== lastDisc) {
            const disc = document.createElement('li');
            disc.className = 'album-modal__disc';
            disc.textContent = `Disque ${track.disc}`;
            list.appendChild(disc);
            lastDisc = track.disc;
        }

        const item = document.createElement('li');
        item.className = 'album-modal__track';

        const number = document.createElement('span');
        number.className = 'album-modal__track-number';
        number.textContent = track.number || '';
        item.appendChild(number);

        const title = document.createElement('span');
        title.className = 'album-modal__track-title';
        title.textContent = track.title;
        item.appendChild(title);

        const length = formatTrackLength(track.length_ms);
        if (length) {
            const duration = document.createElement('span');
            duration.className = 'album-modal__track-length';
            duration.textContent = length;
            item.appendChild(duration);
        }

        list.appendChild(item);
    });

    section.appendChild(list);
    return section;
}

function typeLine(album) {
    const parts = [];
    if (album.primary_type) parts.push(album.primary_type);
    album.secondary_types.forEach((type) => {
        if (!parts.includes(type)) parts.push(type);
    });
    return parts.join(' · ');
}

function metaLine(album) {
    const parts = [];
    const type = typeLine(album);
    if (type) parts.push(type);

    const year = yearFromDate(album.first_release_date) || String(album.release_year || '');
    if (year) parts.push(year);

    return parts.join(' · ');
}

function renderStars(rating) {
    const value = Number(rating?.value) || 0;
    if (value <= 0) return null;

    const wrap = document.createElement('div');
    wrap.className = 'album-modal__rating';
    wrap.setAttribute('role', 'img');
    wrap.setAttribute('aria-label', `${value} sur 5`);

    for (let i = 1; i <= 5; i += 1) {
        const star = document.createElement('span');
        star.className = 'album-modal__star';
        star.setAttribute('aria-hidden', 'true');
        star.textContent = '★';

        const filled = value - (i - 1);
        if (filled >= 0.75) {
            star.classList.add('album-modal__star--full');
        } else if (filled >= 0.25) {
            star.classList.add('album-modal__star--half');
        }

        wrap.appendChild(star);
    }

    return wrap;
}

function ensureModal() {
    if (overlay && document.body.contains(overlay)) {
        return;
    }

    overlay = document.createElement('div');
    overlay.className = 'album-modal-overlay';
    overlay.hidden = true;
    overlay.innerHTML = `
        <div class="album-modal-stack">
            <div class="album-modal__actions" aria-label="Actions">
                <button type="button" class="album-modal__action" data-action="listen" aria-label="Ajouter à mes écoutes">
                    <span class="album-modal__action-icon" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="28" height="28" fill="currentColor"><path d="M3.68,19.22q-0.98-0.99-0.98-2.39t0.98-2.39t2.4-0.99q0.57,0 1.07,0.18t0.91,0.53V5.8l11.29-2V14.82q0,1.41-0.99,2.39t-2.4,0.99t-2.39-0.99t-0.98-2.39t0.98-2.39t2.4-0.99q0.57,0 1.07,0.18t0.91,0.53V7.94L9.45,9.51v7.31q0,1.41-0.99,2.39T6.07,20.2T3.68,19.22Z"/></svg>
                    </span>
                    <span class="album-modal__action-label">Écoutes</span>
                </button>
                <button type="button" class="album-modal__action" data-action="collection" aria-label="Ajouter à ma collection">
                    <span class="album-modal__action-icon" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="28" height="28" fill="currentColor"><path d="M11.02,20.32H5.39q-0.71,0-1.21-0.5t-0.5-1.21V5.39q0-0.71 0.5-1.21t1.21-0.5h5.63V20.32Zm-1.4-1.4V5.08H5.39q-0.13,0-0.22,0.09T5.08,5.39V18.61q0,0.13 0.09,0.22t0.22,0.09H9.62Zm3.36-7.9V3.68h5.63q0.71,0 1.21,0.5t0.5,1.21v5.63H12.98Zm1.4-1.4h4.54V5.39q0-0.13-0.09-0.22T18.61,5.08H14.38V9.62Zm-1.4,10.7V12.98h7.34v5.63q0,0.71-0.5,1.21t-1.21,0.5H12.98Zm1.4-1.4h4.23q0.13,0 0.22-0.09t0.09-0.22V14.38H14.38v4.54ZM9.62,12Zm4.76-2.38Zm0,4.76Z"/></svg>
                    </span>
                    <span class="album-modal__action-label">Collection</span>
                </button>
            </div>
            <div class="album-modal" role="dialog" aria-modal="true" aria-labelledby="album-modal-title" tabindex="-1">
                <button type="button" class="album-modal__close" aria-label="Fermer">×</button>
                <div class="album-modal__body"></div>
            </div>
        </div>
    `;

    dialog = overlay.querySelector('.album-modal');
    bodyEl = overlay.querySelector('.album-modal__body');
    actionsEl = overlay.querySelector('.album-modal__actions');
    listenBtn = overlay.querySelector('[data-action="listen"]');
    const closeBtn = overlay.querySelector('.album-modal__close');

    overlay.addEventListener('click', (event) => {
        if (event.target === overlay) closeAlbumModal();
    });
    closeBtn.addEventListener('click', () => closeAlbumModal());
    if (listenBtn) {
        listenBtn.addEventListener('click', () => {
            handleListenClick();
        });
    }

    document.body.appendChild(overlay);
}

function renderCoverMedia(album) {
    if (album.cover_url) {
        const img = document.createElement('img');
        img.className = 'album-modal__cover';
        img.src = album.cover_url;
        img.alt = `Pochette de ${album.title}`;
        img.addEventListener('error', () => {
            img.replaceWith(renderEmptyCover());
        }, { once: true });
        return img;
    }
    return renderEmptyCover();
}

function renderEmptyCover() {
    const placeholder = document.createElement('div');
    placeholder.className = 'album-modal__cover album-modal__cover--empty';
    placeholder.setAttribute('aria-hidden', 'true');

    const logo = document.createElement('img');
    logo.className = 'album-modal__cover-logo';
    logo.src = 'assets/images/assets/itrack.ico';
    logo.alt = '';
    logo.draggable = false;
    placeholder.appendChild(logo);

    return placeholder;
}

function renderCover(album) {
    const media = renderCoverMedia(album);
    if (!manageListens || !album.id) {
        return media;
    }

    const wrap = document.createElement('div');
    wrap.className = 'album-modal__cover-wrap';
    if (coverUploadBusy) {
        wrap.classList.add('is-busy');
    }

    wrap.appendChild(media);

    const edit = document.createElement('button');
    edit.type = 'button';
    edit.className = 'album-modal__cover-edit';
    edit.setAttribute('aria-label', 'Remplacer la pochette');
    edit.disabled = coverUploadBusy;
    edit.innerHTML = EDIT_COVER_ICON;

    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/*';
    input.className = 'album-modal__cover-input';
    input.hidden = true;

    edit.addEventListener('click', () => {
        if (!coverUploadBusy) input.click();
    });

    input.addEventListener('change', () => {
        const file = input.files && input.files[0];
        if (!file) return;
        // Defer so we leave Safari's change handler before touching the DOM / FormData.
        queueMicrotask(() => {
            handleCoverUpload(file);
        });
    });

    wrap.appendChild(edit);
    wrap.appendChild(input);
    return wrap;
}

async function handleCoverUpload(file) {
    if (!manageListens || !currentAlbum?.id || coverUploadBusy) return;

    coverUploadBusy = true;
    renderAlbum(currentAlbum);

    try {
        const result = await uploadAlbumCover(currentAlbum.id, file);
        if (!result?.album) {
            throw new Error('Réponse invalide');
        }
        currentAlbum = normalizeSeed({ ...currentAlbum, ...result.album }, { remote: false });
        upsertAlbum(result.album);
        document.dispatchEvent(new CustomEvent('itrack:albums-changed'));
        toastSuccess('Pochette mise à jour');
    } catch (error) {
        const message = error instanceof Error ? error.message : '';
        // Safari DOMException noise — never surface the raw English pattern error.
        if (/did not match the expected pattern/i.test(message)) {
            toastError('Impossible de mettre à jour la pochette');
        } else {
            toastError(message || 'Impossible de mettre à jour la pochette');
        }
    } finally {
        coverUploadBusy = false;
        if (!overlay?.hidden && currentAlbum) {
            renderAlbum(currentAlbum);
        }
    }
}

function renderAlbum(album, { loading = false } = {}) {
    ensureModal();
    bodyEl.replaceChildren();

    const content = document.createElement('div');
    content.className = 'album-modal__content';

    const layout = document.createElement('div');
    layout.className = 'album-modal__layout';

    layout.appendChild(renderCover(album));

    const info = document.createElement('div');
    info.className = 'album-modal__info';

    titleEl = document.createElement('h2');
    titleEl.id = 'album-modal-title';
    titleEl.className = 'album-modal__title';
    titleEl.textContent = album.title;
    info.appendChild(titleEl);

    const artist = document.createElement('p');
    artist.className = 'album-modal__artist';
    artist.textContent = album.artist;
    info.appendChild(artist);

    const meta = metaLine(album);
    if (meta) {
        const metaEl = document.createElement('p');
        metaEl.className = 'album-modal__meta';
        metaEl.textContent = meta;
        info.appendChild(metaEl);
    }

    const stars = renderStars(album.rating);
    if (stars) {
        info.appendChild(stars);
    }

    if (album.disambiguation) {
        const note = document.createElement('p');
        note.className = 'album-modal__note';
        note.textContent = album.disambiguation;
        info.appendChild(note);
    }

    if (album.genres.length) {
        const genres = document.createElement('ul');
        genres.className = 'album-modal__genres';
        genres.setAttribute('aria-label', 'Genres');
        album.genres.forEach((name) => {
            const item = document.createElement('li');
            item.className = 'album-modal__genre';
            item.textContent = name;
            genres.appendChild(item);
        });
        info.appendChild(genres);
    }

    if (loading) {
        const status = document.createElement('p');
        status.className = 'album-modal__status';
        status.textContent = 'Chargement…';
        info.appendChild(status);
    }

    layout.appendChild(info);
    content.appendChild(layout);

    if (album.description) {
        const description = document.createElement('p');
        description.className = 'album-modal__description';
        description.textContent = album.description;
        content.appendChild(description);
    }

    const tracklist = renderTracklist(album.tracks);
    if (tracklist) {
        content.appendChild(tracklist);
    }

    const unlistenBtn = renderUnlistenButton();
    if (unlistenBtn) {
        content.appendChild(unlistenBtn);
    }

    bodyEl.appendChild(content);
}

function onKeydown(event) {
    if (event.key === 'Escape') {
        event.preventDefault();
        closeAlbumModal();
    }
}

export function closeAlbumModal() {
    if (!overlay || overlay.hidden) return;

    if (abortController) {
        abortController.abort();
        abortController = null;
    }

    overlay.hidden = true;
    document.removeEventListener('keydown', onKeydown);
    document.body.classList.remove('album-modal-open');
    manageListens = false;
    unlistenStep = 0;
    unlistenBusy = false;
    coverUploadBusy = false;
    currentAlbum = null;

    if (lastFocus && typeof lastFocus.focus === 'function') {
        lastFocus.focus();
    }
    lastFocus = null;
}

export async function openAlbumModal(seed, { remote = false, showActions = true } = {}) {
    ensureModal();

    const album = normalizeSeed(seed, { remote });
    currentAlbum = album;
    detailReady = !(Boolean(album.mbid) && remote);
    listenBusy = false;
    manageListens = !showActions;
    unlistenStep = 0;
    unlistenBusy = false;
    coverUploadBusy = false;
    if (actionsEl) {
        actionsEl.hidden = !showActions;
    }
    syncListenButton();

    lastFocus = document.activeElement;
    overlay.hidden = false;
    document.body.classList.add('album-modal-open');
    document.addEventListener('keydown', onKeydown);
    dialog.focus();

    const shouldFetch = Boolean(album.mbid) && remote;
    renderAlbum(album, { loading: shouldFetch });

    if (!shouldFetch) return;

    if (abortController) {
        abortController.abort();
    }

    const currentRequest = ++requestId;
    abortController = new AbortController();

    try {
        const detail = await fetchMusicBrainzReleaseGroup(album.mbid, {
            signal: abortController.signal,
        });
        if (currentRequest !== requestId) return;
        currentAlbum = normalizeSeed({ ...album, ...detail }, { remote: true });
        detailReady = true;
        syncListenButton();
        renderAlbum(currentAlbum);
    } catch (error) {
        if (error && error.name === 'AbortError') return;
        if (currentRequest !== requestId) return;

        // Allow saving the seed data even if enrichment failed.
        detailReady = true;
        syncListenButton();

        const status = bodyEl.querySelector('.album-modal__status');
        if (status) {
            status.textContent = error instanceof Error
                ? error.message
                : 'Impossible de charger la fiche';
            status.classList.add('album-modal__status--error');
        }
    }
}
