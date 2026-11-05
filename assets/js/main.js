import { fetchAlbums } from './api/client.js';
import { ensureToastHost, toastError } from './components/toast.js';
import { setAlbums } from './state.js';
import { renderListenedAlbums } from './views/albums.js';
import { initNavigation } from './views/navigation.js';
import { initSearch, renderSearchResults } from './views/rechercher.js';

async function loadAlbums() {
    try {
        const albums = await fetchAlbums();
        setAlbums(Array.isArray(albums) ? albums : []);
        renderSearchResults();
        renderListenedAlbums();
    } catch (error) {
        console.error(error);
        toastError('Impossible de charger les albums');
    }
}

document.addEventListener('itrack:albums-changed', () => {
    renderSearchResults();
    renderListenedAlbums();
});

document.addEventListener('DOMContentLoaded', () => {
    ensureToastHost();
    initSearch();
    initNavigation({
        onViewChange: (viewId) => {
            if (viewId === 'view-albums') {
                renderListenedAlbums();
            }
        },
    });
    loadAlbums();
});
