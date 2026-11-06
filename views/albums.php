<section id="view-albums" class="view-section hidden">
    <h1 class="page-title">Albums</h1>

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

    <div id="albums-listened-root" class="albums-listened-root">
        <ul id="albums-listened-grid" class="albums-grid" aria-label="Albums écoutés"></ul>
    </div>
</section>
