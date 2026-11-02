import { initNavigation } from './views/navigation.js';
import { ensureToastHost } from './components/toast.js';

document.addEventListener('DOMContentLoaded', () => {
    ensureToastHost();
    initNavigation();
});
