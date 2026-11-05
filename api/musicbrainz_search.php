<?php

declare(strict_types=1);

/**
 * @return callable
 */
function musicbrainz_search_handler(): callable
{
    $musicBrainz = new MusicBrainzService();

    return static function () use ($musicBrainz): void {
        if (Request::method() !== 'GET') {
            JsonResponse::error('Méthode non autorisée', 405);
        }

        $title = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
        $artist = isset($_GET['artist']) && is_string($_GET['artist']) ? trim($_GET['artist']) : '';

        if ($title === '' && $artist === '') {
            JsonResponse::error('Indique un album et/ou un artiste');
        }

        if (mb_strlen($title) > 200 || mb_strlen($artist) > 200) {
            JsonResponse::error('Requête trop longue');
        }

        $type = isset($_GET['type']) && is_string($_GET['type'])
            ? $musicBrainz->normalizeType($_GET['type'])
            : MusicBrainzService::DEFAULT_TYPE;

        $result = $musicBrainz->searchAlbums($title, $artist, $type);
        if (!$result->isOk()) {
            $result->respondAsJsonError();
        }

        JsonResponse::send([
            'query' => $title,
            'artist' => $artist,
            'type' => $type,
            'items' => $result->data,
        ]);
    };
}
