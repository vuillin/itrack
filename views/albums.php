<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Icon.php';
?>
<section id="view-albums" class="view-section hidden">
    <h1 class="page-title">Albums</h1>

    <div class="listens-toolbar">
        <div class="search-bar listens-search">
            <label class="search-bar__label" for="listens-search-input">
                <span class="search-bar__icon" aria-hidden="true"><?= Icon::render('search') ?></span>
                <span class="visually-hidden">Rechercher dans mes écoutes</span>
            </label>
            <input
                id="listens-search-input"
                class="search-bar__input"
                type="search"
                placeholder="Rechercher"
                autocomplete="off"
                spellcheck="false"
            >
        </div>

        <div
            id="listens-sort"
            class="listens-sort"
            role="toolbar"
            aria-label="Trier les écoutes"
        >
            <button
                type="button"
                class="listens-sort__btn is-active"
                data-sort="default"
                aria-pressed="true"
            >
                Par défaut
            </button>
            <button
                type="button"
                class="listens-sort__btn"
                data-sort="artist"
                aria-pressed="false"
                aria-label="Artiste, A à Z"
            >
                Artiste
                <span class="listens-sort__dir" data-sort-dir aria-hidden="true">↑</span>
            </button>
            <button
                type="button"
                class="listens-sort__btn"
                data-sort="year"
                aria-pressed="false"
                aria-label="Année, récent → ancien"
            >
                Année
                <span class="listens-sort__dir" data-sort-dir aria-hidden="true">↓</span>
            </button>
        </div>
    </div>

    <div id="albums-listened-root" class="albums-listened-root">
        <ul id="albums-listened-grid" class="albums-grid" aria-label="Albums écoutés"></ul>
    </div>
</section>
