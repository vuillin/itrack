<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Icon.php';
?>
<section id="view-rechercher" class="view-section hidden">
    <h1 class="page-title">Rechercher</h1>

    <form id="search-form" class="search-bar" role="search" action="#" method="get">
        <label class="search-bar__label" for="search-input">
            <span class="search-bar__icon" aria-hidden="true"><?= Icon::render('search') ?></span>
            <span class="visually-hidden">Rechercher</span>
        </label>
        <input
            id="search-input"
            class="search-bar__input"
            type="search"
            name="q"
            placeholder="iTrack"
            autocomplete="off"
            spellcheck="false"
        >
    </form>

    <div id="search-results" class="search-results" hidden>
        <section
            id="search-collection-section"
            class="albums-row-section"
            aria-labelledby="search-collection-title"
            hidden
        >
            <h2 id="search-collection-title" class="albums-row__title">Ma collection</h2>
            <ul id="search-collection-row" class="albums-row" aria-label="Albums de ma collection"></ul>
        </section>

        <section
            id="search-mb-section"
            class="albums-row-section"
            aria-labelledby="search-mb-title"
            hidden
        >
            <h2 id="search-mb-title" class="albums-row__title">Albums</h2>
            <p id="search-mb-status" class="search-row-status" hidden></p>
            <ul id="search-mb-row" class="albums-row" aria-label="Albums MusicBrainz"></ul>
        </section>
    </div>
</section>
