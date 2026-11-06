<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Icon.php';
require_once dirname(__DIR__) . '/src/MusicBrainzService.php';
?>
<section id="view-rechercher" class="view-section hidden">
    <h1 class="page-title">Rechercher</h1>

    <form id="search-form" class="search-toolbar" role="search">
        <div class="search-bar search-bar--title">
            <label class="search-bar__label" for="search-input">
                <span class="search-bar__icon" aria-hidden="true"><?= Icon::render('search') ?></span>
                <span class="visually-hidden"><?= htmlspecialchars(MusicBrainzService::SEARCH_TYPES[MusicBrainzService::DEFAULT_TYPE]['placeholder'], ENT_QUOTES, 'UTF-8') ?></span>
            </label>
            <input
                id="search-input"
                class="search-bar__input"
                type="search"
                placeholder="<?= htmlspecialchars(MusicBrainzService::SEARCH_TYPES[MusicBrainzService::DEFAULT_TYPE]['placeholder'], ENT_QUOTES, 'UTF-8') ?>"
                autocomplete="off"
                spellcheck="false"
            >
        </div>

        <div class="search-bar search-bar--artist">
            <label class="search-bar__label" for="search-artist">
                <span class="visually-hidden">Artiste</span>
            </label>
            <input
                id="search-artist"
                class="search-bar__input"
                type="search"
                placeholder="Artiste"
                autocomplete="off"
                spellcheck="false"
            >
        </div>

        <label class="search-type" for="search-type">
            <span class="visually-hidden">Catégorie</span>
            <select id="search-type" class="search-type__select">
                <?php foreach (MusicBrainzService::SEARCH_TYPES as $value => $config): ?>
                    <option
                        value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>"
                        data-placeholder="<?= htmlspecialchars($config['placeholder'], ENT_QUOTES, 'UTF-8') ?>"
                        <?= $value === MusicBrainzService::DEFAULT_TYPE ? ' selected' : '' ?>
                    >
                        <?= htmlspecialchars($config['label'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <button type="submit" class="search-submit">Rechercher</button>
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
            <ul id="search-mb-row" class="albums-row" aria-label="Résultats MusicBrainz"></ul>
        </section>

        <section
            id="search-listened-section"
            class="albums-row-section"
            aria-labelledby="search-listened-title"
            hidden
        >
            <h2 id="search-listened-title" class="albums-row__title">Mes écoutes</h2>
            <ul id="search-listened-row" class="albums-row" aria-label="Albums de mes écoutes"></ul>
        </section>
    </div>
</section>
