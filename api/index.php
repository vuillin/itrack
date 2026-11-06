<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/AlbumRepository.php';
require_once dirname(__DIR__) . '/src/MusicBrainzFetchResult.php';
require_once dirname(__DIR__) . '/src/MusicBrainzService.php';
require_once dirname(__DIR__) . '/src/CoverArtService.php';
require_once dirname(__DIR__) . '/src/WikipediaService.php';
require_once dirname(__DIR__) . '/src/Logger.php';
require_once dirname(__DIR__) . '/src/Http/JsonResponseException.php';
require_once dirname(__DIR__) . '/src/Http/JsonResponse.php';
require_once dirname(__DIR__) . '/src/Http/Request.php';
require_once dirname(__DIR__) . '/src/Http/Input.php';
require_once __DIR__ . '/albums.php';
require_once __DIR__ . '/album_cover_upload.php';
require_once __DIR__ . '/musicbrainz_search.php';
require_once __DIR__ . '/musicbrainz_release_group.php';
require_once __DIR__ . '/musicbrainz_cover.php';

if (isset($_GET['album_cover'])) {
    $handle = album_cover_upload_handler($pdo);
    $handle();
    exit;
}

if (isset($_GET['mb_cover'])) {
    $handle = musicbrainz_cover_handler();
    $handle();
    exit;
}

if (isset($_GET['mb_rg'])) {
    $handle = musicbrainz_release_group_handler();
    $handle();
    exit;
}

if (isset($_GET['mb_search'])) {
    $handle = musicbrainz_search_handler();
    $handle();
    exit;
}

$handle = albums_handlers($pdo);
$handle();
