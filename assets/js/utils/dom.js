export function clearElement(el) {
    while (el.firstChild) {
        el.removeChild(el.firstChild);
    }
}

export function normalizeSearchText(value) {
    return String(value || '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .trim();
}

/**
 * Higher score = closer match. 0 = no match.
 */
export function albumSearchScore(album, query) {
    const needle = normalizeSearchText(query);
    if (!needle) {
        return 0;
    }

    const title = normalizeSearchText(album?.title);
    const artist = normalizeSearchText(album?.artist);
    let score = 0;

    if (title === needle) score += 100;
    else if (title.startsWith(needle)) score += 80;
    else if (title.includes(needle)) score += 50;

    if (artist === needle) score += 40;
    else if (artist.startsWith(needle)) score += 30;
    else if (artist.includes(needle)) score += 20;

    if (score === 0) {
        const tokens = needle.split(/\s+/).filter(Boolean);
        if (tokens.length > 1) {
            const haystack = `${title} ${artist}`;
            const allMatch = tokens.every((token) => haystack.includes(token));
            if (allMatch) {
                score = 15;
            }
        }
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
