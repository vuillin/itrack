<?php

declare(strict_types=1);

/**
 * @return callable
 */
function albums_handlers(PDO $pdo): callable
{
    $repo = new AlbumRepository($pdo);

    return static function () use ($repo): void {
        $method = Request::method();

        try {
            if ($method === 'GET') {
                JsonResponse::send($repo->findAll());
            }

            if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
                Request::requireWriteToken();
            }

            $data = Request::jsonBody();

            if ($method === 'POST') {
                if (empty($data['title']) || !is_string($data['title'])) {
                    JsonResponse::error('Le titre est obligatoire');
                }

                $formats = Input::readFormatFlags($data);
                $cover = AlbumRepository::normalizeCoverFilename(
                    isset($data['cover']) ? (string) $data['cover'] : null
                );

                if ($cover !== null && $repo->findByCover($cover) !== null) {
                    JsonResponse::error('Cette pochette est déjà utilisée', 409);
                }

                $mbid = isset($data['mbid']) && is_string($data['mbid']) && $data['mbid'] !== ''
                    ? trim($data['mbid'])
                    : null;

                if ($mbid !== null && $repo->findByMbid($mbid) !== null) {
                    JsonResponse::error('Un album avec cet ID MusicBrainz existe déjà', 409);
                }

                $payload = array_merge($data, $formats);
                $payload['cover'] = $cover;
                $payload['mbid'] = $mbid;
                $payload['title'] = trim((string) $data['title']);

                $repo->create($payload);
                JsonResponse::send(['success' => 'Album ajouté']);
            }

            if ($method === 'PATCH') {
                $id = Input::requirePositiveInt($data['id'] ?? null, 'id');

                if (!array_key_exists('is_favorite', $data)) {
                    JsonResponse::error('is_favorite est obligatoire');
                }

                $isFavorite = Input::requireFlag($data['is_favorite'], 'is_favorite');

                if ($repo->findById($id) === null) {
                    JsonResponse::error('Album introuvable', 404);
                }

                $repo->updateFavorite($id, $isFavorite);
                JsonResponse::send(['success' => 'Favori mis à jour']);
            }

            if ($method === 'PUT') {
                $id = Input::requirePositiveInt($data['id'] ?? null, 'id');

                if (empty($data['title']) || !is_string($data['title'])) {
                    JsonResponse::error('Le titre est obligatoire pour modifier');
                }

                $existing = $repo->findById($id);
                if ($existing === null) {
                    JsonResponse::error('Album introuvable', 404);
                }

                $formats = Input::readFormatFlags($data);
                $payload = array_merge($existing, $data);
                $payload['id'] = $id;
                $payload = array_merge($payload, $formats);
                $payload['title'] = trim((string) $data['title']);

                if (array_key_exists('mbid', $data) && $data['mbid'] !== '' && $data['mbid'] !== null) {
                    $mbid = trim((string) $data['mbid']);
                    if ($repo->findByMbidForOther($mbid, $id) !== null) {
                        JsonResponse::error('Un album avec cet ID MusicBrainz existe déjà', 409);
                    }
                    $payload['mbid'] = $mbid;
                }

                $cover = AlbumRepository::normalizeCoverFilename(
                    isset($payload['cover']) ? (string) $payload['cover'] : null
                );

                if ($cover !== null && $repo->findByCoverForOther($cover, $id) !== null) {
                    JsonResponse::error('Cette pochette est déjà utilisée', 409);
                }

                $payload['cover'] = $cover;

                $repo->update($payload);
                JsonResponse::send(['success' => 'Album modifié']);
            }

            if ($method === 'DELETE') {
                $id = Input::requirePositiveInt($data['id'] ?? null, 'id');

                if ($repo->findById($id) === null) {
                    JsonResponse::error('Album introuvable', 404);
                }

                $repo->delete($id);
                JsonResponse::send(['success' => 'Album supprimé']);
            }

            JsonResponse::error('Méthode non autorisée', 405);
        } catch (JsonResponseException $e) {
            throw $e;
        } catch (Throwable $e) {
            Logger::error('API albums ' . $method, $e);
            JsonResponse::error('Erreur serveur', 500);
        }
    };
}
