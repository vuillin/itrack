import { fetchMusicBrainzReleaseGroup } from '../api/client.js';
import { coverUrl } from '../utils/dom.js';

let overlay = null;
let dialog = null;
let bodyEl = null;
let titleEl = null;
let abortController = null;
let requestId = 0;
let lastFocus = null;

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
        cover_url: remote
            ? (seed.cover_url || '')
            : (seed.cover_url || coverUrl(seed.cover)),
        tracks,
    };
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
        <div class="album-modal" role="dialog" aria-modal="true" aria-labelledby="album-modal-title" tabindex="-1">
            <button type="button" class="album-modal__close" aria-label="Fermer">×</button>
            <div class="album-modal__body"></div>
        </div>
    `;

    dialog = overlay.querySelector('.album-modal');
    bodyEl = overlay.querySelector('.album-modal__body');
    const closeBtn = overlay.querySelector('.album-modal__close');

    overlay.addEventListener('click', (event) => {
        if (event.target === overlay) closeAlbumModal();
    });
    closeBtn.addEventListener('click', () => closeAlbumModal());

    document.body.appendChild(overlay);
}

function renderCover(album) {
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

    if (lastFocus && typeof lastFocus.focus === 'function') {
        lastFocus.focus();
    }
    lastFocus = null;
}

export async function openAlbumModal(seed, { remote = false } = {}) {
    ensureModal();

    const album = normalizeSeed(seed, { remote });
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
        renderAlbum(normalizeSeed({ ...album, ...detail }, { remote: true }));
    } catch (error) {
        if (error && error.name === 'AbortError') return;
        if (currentRequest !== requestId) return;

        const status = bodyEl.querySelector('.album-modal__status');
        if (status) {
            status.textContent = error instanceof Error
                ? error.message
                : 'Impossible de charger la fiche';
            status.classList.add('album-modal__status--error');
        }
    }
}
