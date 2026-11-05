const API_URL = 'api.php';

function apiToken() {
    return (window.__ITRACK__ && window.__ITRACK__.apiToken) || '';
}

function writeHeaders() {
    return {
        'Content-Type': 'application/json',
        'X-Itrack-Token': apiToken(),
    };
}

export async function fetchAlbums() {
    const response = await fetch(API_URL);
    if (!response.ok) {
        throw new Error('Impossible de charger les albums');
    }
    return response.json();
}

export async function searchMusicBrainzAlbums(query, { artist = '', type = 'album', signal } = {}) {
    const params = new URLSearchParams({
        mb_search: '1',
        type,
    });
    if (query) params.set('q', query);
    if (artist) params.set('artist', artist);

    const response = await fetch(`${API_URL}?${params.toString()}`, { signal });
    if (!response.ok) {
        let message = 'Impossible de chercher sur MusicBrainz';
        try {
            const body = await response.json();
            if (body && typeof body.error === 'string' && body.error) {
                message = body.error;
            }
        } catch {
            // keep default message
        }
        throw new Error(message);
    }
    return response.json();
}

export async function createAlbum(data) {
    const response = await fetch(API_URL, {
        method: 'POST',
        headers: writeHeaders(),
        body: JSON.stringify(data),
    });
    return response;
}

export async function updateAlbum(data) {
    const response = await fetch(API_URL, {
        method: 'PUT',
        headers: writeHeaders(),
        body: JSON.stringify(data),
    });
    return response;
}

export async function updateFavorite(id, isFavorite) {
    const response = await fetch(API_URL, {
        method: 'PATCH',
        headers: writeHeaders(),
        body: JSON.stringify({ id, is_favorite: isFavorite }),
    });
    return response;
}

export async function deleteAlbum(id) {
    const response = await fetch(API_URL, {
        method: 'DELETE',
        headers: writeHeaders(),
        body: JSON.stringify({ id }),
    });
    return response;
}
