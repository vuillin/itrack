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

        $query = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
        if ($query === '') {
            JsonResponse::error('Paramètre q obligatoire');
        }

        if (mb_strlen($query) > 200) {
            JsonResponse::error('Requête trop longue');
        }

        $result = $musicBrainz->searchAlbums($query);
        if (!$result->isOk()) {
            $result->respondAsJsonError();
        }

        JsonResponse::send([
            'query' => $query,
            'items' => $result->data,
        ]);
    };
}
