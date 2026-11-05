<?php

declare(strict_types=1);

final class AlbumRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM albums ORDER BY sort_title COLLATE NOCASE ASC');
        $rows = $stmt->fetchAll();
        return array_map(fn(array $row): array => $this->hydrate($row), $rows);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM albums WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $this->hydrate($row);
    }

    public function findByMbid(string $mbid): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM albums WHERE mbid = :mbid');
        $stmt->execute([':mbid' => $mbid]);
        $row = $stmt->fetch();
        return $row === false ? null : $this->hydrate($row);
    }

    public function findByMbidForOther(string $mbid, int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM albums WHERE mbid = :mbid AND id != :id');
        $stmt->execute([':mbid' => $mbid, ':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $this->hydrate($row);
    }

    public function findByCover(string $cover): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM albums WHERE cover = :cover');
        $stmt->execute([':cover' => $cover]);
        $row = $stmt->fetch();
        return $row === false ? null : $this->hydrate($row);
    }

    public function findByCoverForOther(string $cover, int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM albums WHERE cover = :cover AND id != :id');
        $stmt->execute([':cover' => $cover, ':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $this->hydrate($row);
    }

    public static function generateSortTitle(string $title): string
    {
        $pattern = '/^(Le |La |Les |L\'|Un |Une |Des |The |A |An )/i';
        return trim(preg_replace($pattern, '', $title) ?? $title);
    }

    public static function normalizeCoverFilename(?string $cover): ?string
    {
        if ($cover === null) {
            return null;
        }

        $value = trim($cover);
        if ($value === '') {
            return null;
        }

        if (!str_ends_with(strtolower($value), '.webp')) {
            $value = preg_replace('/\.(png|jpe?g)$/i', '', $value) ?? $value;
            $value .= '.webp';
        }

        return $value;
    }

    /**
     * Create or refresh an album marked as listened, storing the full snapshot.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function markListened(array $data): array
    {
        $mbid = isset($data['mbid']) && is_string($data['mbid']) && trim($data['mbid']) !== ''
            ? trim($data['mbid'])
            : null;

        $existing = $mbid !== null ? $this->findByMbidRaw($mbid) : null;

        if ($existing !== null) {
            $payload = $this->mergeListenedPayload($existing, $data);
            $payload['id'] = (int) $existing['id'];
            $this->update($payload);
            $album = $this->findById((int) $existing['id']);
            if ($album === null) {
                throw new RuntimeException('Album introuvable après mise à jour');
            }
            return $album;
        }

        $id = $this->create($data);
        $album = $this->findById($id);
        if ($album === null) {
            throw new RuntimeException('Album introuvable après création');
        }
        return $album;
    }

    public function create(array $data): int
    {
        $sql = 'INSERT INTO albums (
            title, sort_title, artist, release_year, cover, mbid, genres, label,
            track_count, overview, primary_type, secondary_types, first_release_date,
            disambiguation, tracks_json, mb_rating, mb_rating_votes,
            is_favorite, vinyl, cd, cassette, boxset,
            last_listened_at, user_rating
        ) VALUES (
            :title, :sort_title, :artist, :release_year, :cover, :mbid, :genres, :label,
            :track_count, :overview, :primary_type, :secondary_types, :first_release_date,
            :disambiguation, :tracks_json, :mb_rating, :mb_rating_votes,
            :is_favorite, :vinyl, :cd, :cassette, :boxset,
            :last_listened_at, :user_rating
        )';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->bindPayload($data, includeId: false));
        return (int) $this->pdo->lastInsertId();
    }

    public function update(array $data): void
    {
        $sql = 'UPDATE albums SET
            title = :title,
            sort_title = :sort_title,
            artist = :artist,
            release_year = :release_year,
            cover = :cover,
            mbid = :mbid,
            genres = :genres,
            label = :label,
            track_count = :track_count,
            overview = :overview,
            primary_type = :primary_type,
            secondary_types = :secondary_types,
            first_release_date = :first_release_date,
            disambiguation = :disambiguation,
            tracks_json = :tracks_json,
            mb_rating = :mb_rating,
            mb_rating_votes = :mb_rating_votes,
            is_favorite = :is_favorite,
            vinyl = :vinyl,
            cd = :cd,
            cassette = :cassette,
            boxset = :boxset,
            last_listened_at = :last_listened_at,
            user_rating = :user_rating
        WHERE id = :id';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->bindPayload($data, includeId: true));
    }

    public function updateFavorite(int $id, int $isFavorite): void
    {
        $stmt = $this->pdo->prepare('UPDATE albums SET is_favorite = :is_favorite WHERE id = :id');
        $stmt->execute([
            ':id' => $id,
            ':is_favorite' => $isFavorite ? 1 : 0,
        ]);
    }

    /**
     * Remove from listens. Deletes the row if it has no physical formats,
     * otherwise only clears last_listened_at.
     *
     * @return array{deleted: bool, album: ?array<string, mixed>}
     */
    public function unlisten(int $id): array
    {
        $existing = $this->findById($id);
        if ($existing === null) {
            throw new RuntimeException('Album introuvable');
        }

        $owned = ((int) ($existing['vinyl'] ?? 0) === 1)
            || ((int) ($existing['cd'] ?? 0) === 1)
            || ((int) ($existing['cassette'] ?? 0) === 1)
            || ((int) ($existing['boxset'] ?? 0) === 1);

        if ($owned) {
            $stmt = $this->pdo->prepare('UPDATE albums SET last_listened_at = NULL WHERE id = :id');
            $stmt->execute([':id' => $id]);
            return [
                'deleted' => false,
                'album' => $this->findById($id),
            ];
        }

        $this->delete($id);
        return [
            'deleted' => true,
            'album' => null,
        ];
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM albums WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findByMbidRaw(string $mbid): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM albums WHERE mbid = :mbid');
        $stmt->execute([':mbid' => $mbid]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /**
     * @param array<string, mixed> $existing
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function mergeListenedPayload(array $existing, array $data): array
    {
        $payload = $existing;

        foreach ([
            'title', 'artist', 'release_year', 'cover', 'mbid', 'genres', 'label',
            'track_count', 'overview', 'primary_type', 'secondary_types',
            'first_release_date', 'disambiguation', 'tracks', 'tracks_json',
            'mb_rating', 'mb_rating_votes', 'last_listened_at', 'user_rating',
        ] as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }
            $value = $data[$field];
            if ($value === null || $value === '') {
                continue;
            }
            if (is_array($value) && $value === []) {
                continue;
            }
            $payload[$field] = $value;
        }

        // Preserve ownership / favorite flags from the existing row.
        foreach (['is_favorite', 'vinyl', 'cd', 'cassette', 'boxset'] as $flag) {
            $payload[$flag] = (int) ($existing[$flag] ?? 0);
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrate(array $row): array
    {
        $tracks = [];
        if (isset($row['tracks_json']) && is_string($row['tracks_json']) && $row['tracks_json'] !== '') {
            $decoded = json_decode($row['tracks_json'], true);
            if (is_array($decoded)) {
                $tracks = $decoded;
            }
        }
        $row['tracks'] = $tracks;

        $secondary = [];
        if (isset($row['secondary_types']) && is_string($row['secondary_types']) && $row['secondary_types'] !== '') {
            $decoded = json_decode($row['secondary_types'], true);
            if (is_array($decoded)) {
                $secondary = $decoded;
            }
        }
        $row['secondary_types'] = $secondary;

        if (
            (!isset($row['mb_rating']) || $row['mb_rating'] === null)
            && isset($row['rating']) && is_array($row['rating'])
        ) {
            // no-op: rating only exists on API payloads
        }

        if (isset($row['mb_rating']) && $row['mb_rating'] !== null) {
            $row['rating'] = [
                'value' => (float) $row['mb_rating'],
                'votes_count' => isset($row['mb_rating_votes']) ? (int) $row['mb_rating_votes'] : 0,
            ];
        }

        if ((!isset($row['description']) || $row['description'] === null)
            && isset($row['overview']) && is_string($row['overview']) && $row['overview'] !== ''
        ) {
            $row['description'] = $row['overview'];
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function bindPayload(array $data, bool $includeId): array
    {
        $title = (string) ($data['title'] ?? '');

        $secondary = $data['secondary_types'] ?? null;
        if (is_array($secondary)) {
            $secondary = json_encode(array_values($secondary), JSON_UNESCAPED_UNICODE);
        }

        $tracksJson = $data['tracks_json'] ?? null;
        if (isset($data['tracks']) && is_array($data['tracks'])) {
            $tracksJson = json_encode(array_values($data['tracks']), JSON_UNESCAPED_UNICODE);
        }

        $params = [
            ':title' => $title,
            ':sort_title' => self::generateSortTitle($title),
            ':artist' => $data['artist'] ?? null,
            ':release_year' => $data['release_year'] ?? null,
            ':cover' => $data['cover'] ?? null,
            ':mbid' => $data['mbid'] ?? null,
            ':genres' => $data['genres'] ?? null,
            ':label' => $data['label'] ?? null,
            ':track_count' => $data['track_count'] ?? null,
            ':overview' => $data['overview'] ?? null,
            ':primary_type' => $data['primary_type'] ?? null,
            ':secondary_types' => $secondary,
            ':first_release_date' => $data['first_release_date'] ?? null,
            ':disambiguation' => $data['disambiguation'] ?? null,
            ':tracks_json' => $tracksJson,
            ':mb_rating' => $data['mb_rating'] ?? null,
            ':mb_rating_votes' => $data['mb_rating_votes'] ?? null,
            ':is_favorite' => (int) ($data['is_favorite'] ?? 0),
            ':vinyl' => (int) ($data['vinyl'] ?? 0),
            ':cd' => (int) ($data['cd'] ?? 0),
            ':cassette' => (int) ($data['cassette'] ?? 0),
            ':boxset' => (int) ($data['boxset'] ?? 0),
            ':last_listened_at' => $data['last_listened_at'] ?? null,
            ':user_rating' => $data['user_rating'] ?? null,
        ];

        if ($includeId) {
            $params[':id'] = (int) $data['id'];
        }

        return $params;
    }
}
