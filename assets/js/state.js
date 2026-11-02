let allAlbums = [];

export function getAlbums() {
    return allAlbums;
}

export function setAlbums(albums) {
    allAlbums = albums;
}

export function invalidateAlbums() {
    allAlbums = [];
}
