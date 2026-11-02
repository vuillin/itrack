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
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM albums WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function findByMbid(string $mbid): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM albums WHERE mbid = :mbid');
        $stmt->execute([':mbid' => $mbid]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function findByMbidForOther(string $mbid, int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM albums WHERE mbid = :mbid AND id != :id');
        $stmt->execute([':mbid' => $mbid, ':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function findByCover(string $cover): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM albums WHERE cover = :cover');
        $stmt->execute([':cover' => $cover]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function findByCoverForOther(string $cover, int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM albums WHERE cover = :cover AND id != :id');
        $stmt->execute([':cover' => $cover, ':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
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

    public function create(array $data): int
    {
        $sql = 'INSERT INTO albums (
            title, sort_title, artist, release_year, cover, mbid, genres, label,
            track_count, overview, is_favorite, vinyl, cd, cassette, boxset,
            last_listened_at, user_rating
        ) VALUES (
            :title, :sort_title, :artist, :release_year, :cover, :mbid, :genres, :label,
            :track_count, :overview, :is_favorite, :vinyl, :cd, :cassette, :boxset,
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

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM albums WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    private function bindPayload(array $data, bool $includeId): array
    {
        $title = (string) ($data['title'] ?? '');
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
