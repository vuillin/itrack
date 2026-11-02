export function clearElement(el) {
    while (el.firstChild) {
        el.removeChild(el.firstChild);
    }
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
