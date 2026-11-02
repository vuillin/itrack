<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Icon.php';
?>
<aside class="app-sidebar" id="app-sidebar" aria-label="Navigation principale">
    <a href="#" id="logo-home" class="site-logo-link" aria-label="Retour à l'accueil">
        <img src="assets/images/assets/logo-itrack.png" alt="iTrack" class="site-logo">
    </a>

    <nav class="main-nav" aria-label="Principal">
        <ul>
            <li>
                <a href="#" class="nav-btn" data-target="view-rechercher">
                    <span class="nav-btn__icon"><?= Icon::render('search') ?></span>
                    <span class="nav-btn__label">Rechercher</span>
                </a>
            </li>
            <li>
                <a href="#" class="nav-btn active" data-target="view-accueil">
                    <span class="nav-btn__icon"><?= Icon::render('home') ?></span>
                    <span class="nav-btn__label">Accueil</span>
                </a>
            </li>
        </ul>
    </nav>

    <nav class="sidebar-section" aria-label="Mes écoutes">
        <h2 class="sidebar-section__title">Mes écoutes</h2>
        <ul>
            <li>
                <a href="#" class="nav-btn" data-target="view-albums">
                    <span class="nav-btn__icon"><?= Icon::render('album') ?></span>
                    <span class="nav-btn__label">Albums</span>
                </a>
            </li>
        </ul>
    </nav>

    <nav class="sidebar-section" aria-label="Ma collection">
        <h2 class="sidebar-section__title">Ma collection</h2>
        <ul>
            <li>
                <a href="#" class="nav-btn" data-target="view-collection">
                    <span class="nav-btn__icon"><?= Icon::render('collection') ?></span>
                    <span class="nav-btn__label">Collection</span>
                </a>
            </li>
        </ul>
    </nav>
</aside>
