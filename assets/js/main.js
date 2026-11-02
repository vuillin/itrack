import { fetchAlbums } from './api/client.js';
import { ensureToastHost, toastError } from './components/toast.js';
import { setAlbums } from './state.js';
import { initNavigation } from './views/navigation.js';
import { initSearch, renderSearchResults } from './views/rechercher.js';

async function loadAlbums() {
    try {
        const albums = await fetchAlbums();
        setAlbums(Array.isArray(albums) ? albums : []);
        renderSearchResults();
    } catch (error) {
        console.error(error);
        toastError('Impossible de charger les albums');
    }
}

document.addEventListener('DOMContentLoaded', () => {
    ensureToastHost();
    initSearch();
    initNavigation();
    loadAlbums();
});
