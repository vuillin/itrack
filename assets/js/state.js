let allAlbums = [];

export function getAlbums() {
    return allAlbums;
}

export function setAlbums(albums) {
    allAlbums = Array.isArray(albums) ? albums : [];
}

export function upsertAlbum(album) {
    if (!album || typeof album !== 'object') return;

    const index = allAlbums.findIndex((entry) => {
        if (album.id != null && entry.id != null && Number(entry.id) === Number(album.id)) {
            return true;
        }
        return Boolean(album.mbid) && entry.mbid === album.mbid;
    });

    if (index >= 0) {
        allAlbums[index] = { ...allAlbums[index], ...album };
    } else {
        allAlbums.push(album);
    }
}

export function removeAlbum(id) {
    const target = Number(id);
    if (!Number.isFinite(target) || target <= 0) return;
    allAlbums = allAlbums.filter((entry) => Number(entry.id) !== target);
}

export function invalidateAlbums() {
    allAlbums = [];
}
