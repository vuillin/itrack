export function clearElement(el) {
    while (el.firstChild) {
        el.removeChild(el.firstChild);
    }
}

function normalizeSearchText(value) {
    return String(value || '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .trim();
}

function fieldMatchScore(value, needle, { exact = 100, prefix = 80, includes = 50 } = {}) {
    if (!needle) return 0;
    if (value === needle) return exact;
    if (value.startsWith(needle)) return prefix;
    if (value.includes(needle)) return includes;
    return 0;
}

/**
 * Higher score = closer match. 0 = no match.
 * Pass album title query and/or artist query.
 */
export function albumSearchScore(album, titleQuery = '', artistQuery = '') {
    const titleNeedle = normalizeSearchText(titleQuery);
    const artistNeedle = normalizeSearchText(artistQuery);
    if (!titleNeedle && !artistNeedle) {
        return 0;
    }

    const title = normalizeSearchText(album?.title);
    const artist = normalizeSearchText(album?.artist);
    let score = 0;

    if (titleNeedle) {
        const titleScore = fieldMatchScore(title, titleNeedle);
        if (titleScore === 0) {
            return 0;
        }
        score += titleScore;
    }

    if (artistNeedle) {
        const artistScore = fieldMatchScore(artist, artistNeedle);
        if (artistScore === 0) {
            return 0;
        }
        score += artistScore;
    }

    return score;
}

export function setEmptyListMessage(listEl, message) {
    clearElement(listEl);
    const li = document.createElement('li');
    li.className = 'albums-grid__empty';
    li.textContent = message;
    listEl.appendChild(li);
}

export function coverUrl(cover) {
    return cover ? `assets/images/covers/${cover}` : '';
}

export function formatFlags(album) {
    const labels = [];
    if (album.vinyl == 1) labels.push('Vinyl');
    if (album.cd == 1) labels.push('CD');
    if (album.cassette == 1) labels.push('Cassette');
    if (album.boxset == 1) labels.push('Boxset');
    return labels;
}

export function isOwned(album) {
    return album.vinyl == 1 || album.cd == 1 || album.cassette == 1 || album.boxset == 1;
}

export function hasBeenListened(album) {
    return Boolean(album.last_listened_at);
}
