<?php

declare(strict_types=1);

/**
 * @return callable
 */
function album_cover_upload_handler(PDO $pdo): callable
{
    $repo = new AlbumRepository($pdo);
    $covers = CoverArtService::fromProjectRoot();

    return static function () use ($repo, $covers): void {
        if (Request::method() !== 'POST') {
            JsonResponse::error('Méthode non autorisée', 405);
        }

        $id = Input::requirePositiveInt($_POST['id'] ?? null, 'id');
        $album = $repo->findById($id);
        if ($album === null) {
            JsonResponse::error('Album introuvable', 404);
        }

        if (!isset($_FILES['cover']) || !is_array($_FILES['cover'])) {
            JsonResponse::error('Fichier pochette manquant');
        }

        $previousCover = isset($album['cover']) && is_string($album['cover']) && $album['cover'] !== ''
            ? $album['cover']
            : null;

        try {
            $filename = $covers->storeUploadedLibraryCover($_FILES['cover'], 'album-' . $id);
        } catch (InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        } catch (Throwable $e) {
            Logger::error('Upload pochette', $e);
            JsonResponse::error('Impossible d\'enregistrer la pochette', 500);
        }

        if ($repo->findByCoverForOther($filename, $id) !== null) {
            $covers->deleteLibraryCover($filename);
            JsonResponse::error('Cette pochette est déjà utilisée', 409);
        }

        $repo->updateCover($id, $filename);

        if (
            $previousCover !== null
            && $previousCover !== $filename
            && $repo->findByCover($previousCover) === null
        ) {
            $covers->deleteLibraryCover($previousCover);
        }

        JsonResponse::send([
            'success' => 'Pochette mise à jour',
            'album' => $repo->findById($id),
        ]);
    };
}
