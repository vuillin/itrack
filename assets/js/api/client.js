const API_URL = 'api.php';

function writeHeaders() {
    return {
        'Content-Type': 'application/json',
    };
}

export async function fetchAlbums() {
    const response = await fetch(API_URL);
    if (!response.ok) {
        throw new Error('Impossible de charger les albums');
    }
    return response.json();
}

async function readApiError(response, fallback) {
    try {
        const body = await response.json();
        if (body && typeof body.error === 'string' && body.error) {
            return body.error;
        }
    } catch {
        // keep fallback
    }
    return fallback;
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
        throw new Error(await readApiError(response, 'Impossible de chercher sur MusicBrainz'));
    }
    return response.json();
}

export async function fetchMusicBrainzReleaseGroup(mbid, { signal } = {}) {
    const params = new URLSearchParams({ mb_rg: mbid });
    const response = await fetch(`${API_URL}?${params.toString()}`, { signal });
    if (!response.ok) {
        throw new Error(await readApiError(response, 'Impossible de charger la fiche'));
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

export async function markAlbumListened(data) {
    const response = await fetch(API_URL, {
        method: 'POST',
        headers: writeHeaders(),
        body: JSON.stringify(data),
    });
    if (!response.ok) {
        throw new Error(await readApiError(response, 'Impossible d’enregistrer l’écoute'));
    }
    return response.json();
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

export async function unlistenAlbum(id) {
    const response = await fetch(API_URL, {
        method: 'PATCH',
        headers: writeHeaders(),
        body: JSON.stringify({ id, unlisten: true }),
    });
    if (!response.ok) {
        throw new Error(await readApiError(response, 'Impossible de retirer des écoutes'));
    }
    return response.json();
}

export async function deleteAlbum(id) {
    const response = await fetch(API_URL, {
        method: 'DELETE',
        headers: writeHeaders(),
        body: JSON.stringify({ id }),
    });
    return response;
}
