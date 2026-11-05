<?php

declare(strict_types=1);

/**
 * @return callable
 */
function musicbrainz_cover_handler(): callable
{
    $covers = CoverArtService::fromProjectRoot();

    return static function () use ($covers): void {
        if (Request::method() !== 'GET') {
            JsonResponse::error('Méthode non autorisée', 405);
        }

        $mbid = isset($_GET['mb_cover']) && is_string($_GET['mb_cover'])
            ? trim($_GET['mb_cover'])
            : '';

        if ($mbid === '') {
            JsonResponse::error('Paramètre mb_cover obligatoire');
        }

        try {
            $covers->respond($mbid);
        } catch (Throwable $e) {
            Logger::error('Échec proxy pochette MusicBrainz', $e);
            JsonResponse::error('Pochette indisponible', 502);
        }
    };
}
