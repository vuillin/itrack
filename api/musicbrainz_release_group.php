<?php

declare(strict_types=1);

/**
 * @return callable
 */
function musicbrainz_release_group_handler(): callable
{
    $musicBrainz = new MusicBrainzService();

    return static function () use ($musicBrainz): void {
        if (Request::method() !== 'GET') {
            JsonResponse::error('Méthode non autorisée', 405);
        }

        $mbid = isset($_GET['mb_rg']) && is_string($_GET['mb_rg'])
            ? trim($_GET['mb_rg'])
            : '';

        if ($mbid === '' || !$musicBrainz->isValidMbid($mbid)) {
            JsonResponse::error('MBID invalide', 400);
        }

        $result = $musicBrainz->getReleaseGroup($mbid);
        if (!$result->isOk()) {
            $result->respondAsJsonError();
        }

        JsonResponse::send($result->data);
    };
}
