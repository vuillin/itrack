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

            $data = Request::jsonBody();

            if ($method === 'POST') {
                if (empty($data['title']) || !is_string($data['title'])) {
                    JsonResponse::error('Le titre est obligatoire');
                }

                $formats = Input::readFormatFlags($data);
                $cover = AlbumRepository::normalizeCoverFilename(
                    isset($data['cover']) ? (string) $data['cover'] : null
                );

                $mbid = isset($data['mbid']) && is_string($data['mbid']) && $data['mbid'] !== ''
                    ? trim($data['mbid'])
                    : null;

                $isListen = array_key_exists('last_listened_at', $data)
                    && is_string($data['last_listened_at'])
                    && trim($data['last_listened_at']) !== '';

                if ($isListen) {
                    $payload = array_merge($data, $formats);
                    $payload['mbid'] = $mbid;
                    $payload['title'] = trim((string) $data['title']);
                    $payload['last_listened_at'] = trim((string) $data['last_listened_at']);

                    if ($mbid !== null) {
                        $coverService = CoverArtService::fromProjectRoot();
                        if ($cover !== null && !$coverService->libraryCoverExists($cover)) {
                            $cover = null;
                        }
                        if ($cover === null) {
                            $existing = $repo->findByMbid($mbid);
                            $existingCover = is_array($existing) && isset($existing['cover']) && is_string($existing['cover'])
                                ? AlbumRepository::normalizeCoverFilename($existing['cover'])
                                : null;
                            if ($existingCover !== null && $coverService->libraryCoverExists($existingCover)) {
                                $cover = $existingCover;
                            } else {
                                $cover = $coverService->persistLibraryCover($mbid);
                            }
                        }
                    }
                    $payload['cover'] = $cover;

                    if (isset($data['overview']) && is_string($data['overview'])) {
                        $payload['overview'] = trim($data['overview']);
                    } elseif (isset($data['description']) && is_string($data['description'])) {
                        $payload['overview'] = trim($data['description']);
                    }

                    if (isset($data['genres']) && is_array($data['genres'])) {
                        $payload['genres'] = implode(', ', array_values(array_filter(
                            array_map(static fn(mixed $g): string => is_string($g) ? trim($g) : '', $data['genres']),
                            static fn(string $g): bool => $g !== ''
                        )));
                    }

                    if (isset($data['rating']) && is_array($data['rating'])) {
                        if (isset($data['rating']['value']) && is_numeric($data['rating']['value'])) {
                            $payload['mb_rating'] = (float) $data['rating']['value'];
                        }
                        if (isset($data['rating']['votes_count']) && is_numeric($data['rating']['votes_count'])) {
                            $payload['mb_rating_votes'] = (int) $data['rating']['votes_count'];
                        }
                    }

                    if (isset($data['tracks']) && is_array($data['tracks'])) {
                        $payload['tracks'] = $data['tracks'];
                        $payload['track_count'] = count($data['tracks']);
                    }

                    $album = $repo->markListened($payload);
                    JsonResponse::send([
                        'success' => 'Écoute enregistrée',
                        'album' => $album,
                    ]);
                }

                if ($cover !== null && $repo->findByCover($cover) !== null) {
                    JsonResponse::error('Cette pochette est déjà utilisée', 409);
                }

                if ($mbid !== null && $repo->findByMbid($mbid) !== null) {
                    JsonResponse::error('Un album avec cet ID MusicBrainz existe déjà', 409);
                }

                $payload = array_merge($data, $formats);
                $payload['cover'] = $cover;
                $payload['mbid'] = $mbid;
                $payload['title'] = trim((string) $data['title']);

                $id = $repo->create($payload);
                $album = $repo->findById($id);
                JsonResponse::send([
                    'success' => 'Album ajouté',
                    'album' => $album,
                ]);
            }

            if ($method === 'PATCH') {
                $id = Input::requirePositiveInt($data['id'] ?? null, 'id');

                if ($repo->findById($id) === null) {
                    JsonResponse::error('Album introuvable', 404);
                }

                if (!empty($data['unlisten'])) {
                    $result = $repo->unlisten($id);
                    if (
                        !empty($result['deleted'])
                        && isset($result['deleted_cover'])
                        && is_string($result['deleted_cover'])
                        && $result['deleted_cover'] !== ''
                    ) {
                        CoverArtService::fromProjectRoot()->deleteLibraryCover($result['deleted_cover']);
                    }
                    JsonResponse::send([
                        'success' => 'Retiré des écoutes',
                        'deleted' => $result['deleted'],
                        'album' => $result['album'],
                    ]);
                }

                if (!array_key_exists('is_favorite', $data)) {
                    JsonResponse::error('is_favorite ou unlisten est obligatoire');
                }

                $isFavorite = Input::requireFlag($data['is_favorite'], 'is_favorite');
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
