<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/AlbumRepository.php';
require_once dirname(__DIR__) . '/src/MusicBrainzFetchResult.php';
require_once dirname(__DIR__) . '/src/MusicBrainzService.php';
require_once dirname(__DIR__) . '/src/Logger.php';
require_once dirname(__DIR__) . '/src/Http/JsonResponseException.php';
require_once dirname(__DIR__) . '/src/Http/JsonResponse.php';
require_once dirname(__DIR__) . '/src/Http/Request.php';
require_once dirname(__DIR__) . '/src/Http/Input.php';
require_once __DIR__ . '/albums.php';
require_once __DIR__ . '/musicbrainz_search.php';

if (isset($_GET['mb_search'])) {
    $handle = musicbrainz_search_handler();
    $handle();
    exit;
}

$handle = albums_handlers($pdo);
$handle();
