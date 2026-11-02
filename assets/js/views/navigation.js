let currentView = 'view-accueil';

const VIEW_TO_HASH = {
    'view-rechercher': 'rechercher',
    'view-accueil': 'accueil',
    'view-nouveautes': 'nouveautes',
    'view-albums': 'albums',
    'view-collection': 'collection',
};

const HASH_TO_VIEW = {
    rechercher: 'view-rechercher',
    accueil: 'view-accueil',
    nouveautes: 'view-nouveautes',
    albums: 'view-albums',
    collection: 'view-collection',
};

function viewFromHash() {
    const hash = window.location.hash.replace(/^#/, '');
    return HASH_TO_VIEW[hash] || 'view-accueil';
}

export function initNavigation({ onViewChange } = {}) {
    const navButtons = document.querySelectorAll('.nav-btn');
    const viewSections = document.querySelectorAll('.view-section');

    function syncActiveNav(targetId) {
        navButtons.forEach((btn) => {
            btn.classList.toggle('active', btn.getAttribute('data-target') === targetId);
        });
    }

    function switchView(targetId) {
        viewSections.forEach((section) => section.classList.add('hidden'));

        const targetSection = document.getElementById(targetId);
        if (targetSection) {
            targetSection.classList.remove('hidden');
        }

        currentView = targetId;

        if (typeof onViewChange === 'function') {
            onViewChange(targetId);
        }
    }

    function applyView(targetId) {
        const viewId = document.getElementById(targetId) ? targetId : 'view-accueil';
        syncActiveNav(viewId);
        switchView(viewId);
    }

    function goTo(targetId) {
        const hash = VIEW_TO_HASH[targetId] || 'accueil';
        const next = `#${hash}`;
        if (window.location.hash === next) {
            applyView(targetId);
        } else {
            window.location.hash = hash;
        }
    }

    navButtons.forEach((btn) => {
        btn.addEventListener('click', (event) => {
            event.preventDefault();
            const target = btn.getAttribute('data-target');
            if (!target) {
                return;
            }
            goTo(target);
        });
    });

    const logoHome = document.getElementById('logo-home');
    if (logoHome) {
        logoHome.addEventListener('click', (event) => {
            event.preventDefault();
            goTo('view-accueil');
        });
    }

    window.addEventListener('hashchange', () => {
        applyView(viewFromHash());
    });

    const initialView = viewFromHash();
    if (!window.location.hash) {
        history.replaceState(null, '', `#${VIEW_TO_HASH[initialView]}`);
    }
    applyView(initialView);

    return { switchView: goTo };
}
