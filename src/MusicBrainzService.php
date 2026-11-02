<?php

declare(strict_types=1);

final class MusicBrainzService
{
    private const BASE_URL = 'https://musicbrainz.org/ws/2/';
    private const COVER_ART_FRONT = 'https://coverartarchive.org/release-group/%s/front-250';
    private const USER_AGENT = 'iTrack/0.1 (personal music library; local)';
    private const DEFAULT_LIMIT = 10;
    private const FETCH_LIMIT = 30;
    private const MIN_SCORE = 80;

    /** @var list<string> */
    private const BLOCKED_SECONDARY_TYPES = [
        'Compilation',
        'Live',
        'Remix',
        'Soundtrack',
        'DJ-mix',
        'Interview',
        'Demo',
        'Mixtape/Street',
        'Spokenword',
        'Audiobook',
    ];

    /**
     * Search album/EP release-groups by free text.
     *
     * @return MusicBrainzFetchResult list of items on success
     */
    public function searchAlbums(string $query, int $limit = self::DEFAULT_LIMIT): MusicBrainzFetchResult
    {
        $query = trim($query);
        if ($query === '') {
            return MusicBrainzFetchResult::fail(MusicBrainzFetchResult::ERROR_INVALID_QUERY);
        }

        $limit = max(1, min(100, $limit));

        $url = self::BASE_URL . 'release-group?' . http_build_query([
            'query' => $this->buildLuceneQuery($query),
            'fmt' => 'json',
            'limit' => self::FETCH_LIMIT,
        ]);

        $fetch = $this->fetchJson($url);
        if (!$fetch->isOk()) {
            return $fetch;
        }

        $rows = $fetch->data['release-groups'] ?? null;
        if (!is_array($rows)) {
            return MusicBrainzFetchResult::fail(MusicBrainzFetchResult::ERROR_INVALID_RESPONSE);
        }

        $items = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $mapped = $this->mapReleaseGroup($row, $query);
            if ($mapped !== null) {
                $items[] = $mapped;
            }

            if (count($items) >= $limit) {
                break;
            }
        }

        return MusicBrainzFetchResult::ok($items);
    }

    private function buildLuceneQuery(string $query): string
    {
        $phrase = $this->quotePhrase($query);
        $escaped = $this->escapeLucene($query);

        $parts = [
            sprintf('(releasegroup:%s^4 OR artist:%s^3 OR releasegroup:(%s) OR artist:(%s))', $phrase, $phrase, $escaped, $escaped),
            '(primarytype:album OR primarytype:ep)',
            'NOT secondarytype:compilation',
            'NOT secondarytype:live',
            'NOT secondarytype:remix',
            'NOT secondarytype:soundtrack',
            'NOT secondarytype:dj-mix',
            'NOT secondarytype:interview',
            'NOT secondarytype:demo',
            'NOT artist:"Various Artists"',
        ];

        return implode(' AND ', $parts);
    }

    private function quotePhrase(string $value): string
    {
        $clean = str_replace('"', '', $value);
        return '"' . $this->escapeLucene($clean) . '"';
    }

    private function escapeLucene(string $value): string
    {
        return preg_replace('/([+\-&|!(){}\[\]^"~*?:\\\\\/])/', '\\\\$1', $value) ?? $value;
    }

    /**
     * @param array<string, mixed> $row
     * @return array{
     *   mbid: string,
     *   title: string,
     *   artist: string,
     *   primary_type: string,
     *   first_release_date: string|null,
     *   cover_url: string
     * }|null
     */
    private function mapReleaseGroup(array $row, string $query): ?array
    {
        $score = isset($row['score']) ? (int) $row['score'] : 0;
        if ($score < self::MIN_SCORE) {
            return null;
        }

        $mbid = isset($row['id']) && is_string($row['id']) ? trim($row['id']) : '';
        $title = isset($row['title']) && is_string($row['title']) ? trim($row['title']) : '';
        $primaryType = isset($row['primary-type']) && is_string($row['primary-type'])
            ? $row['primary-type']
            : '';

        if ($mbid === '' || $title === '') {
            return null;
        }

        if (!in_array($primaryType, ['Album', 'EP'], true)) {
            return null;
        }

        $secondaryTypes = $row['secondary-types'] ?? [];
        if (is_array($secondaryTypes)) {
            foreach ($secondaryTypes as $secondaryType) {
                if (is_string($secondaryType) && in_array($secondaryType, self::BLOCKED_SECONDARY_TYPES, true)) {
                    return null;
                }
            }
        }

        $artist = $this->extractArtistCredit($row);
        if ($this->isVariousArtist($artist)) {
            return null;
        }

        if (!$this->matchesQuery($title, $artist, $query)) {
            return null;
        }

        $date = isset($row['first-release-date']) && is_string($row['first-release-date'])
            ? trim($row['first-release-date'])
            : null;

        return [
            'mbid' => $mbid,
            'title' => $title,
            'artist' => $artist,
            'primary_type' => $primaryType,
            'first_release_date' => $date !== '' ? $date : null,
            'cover_url' => sprintf(self::COVER_ART_FRONT, rawurlencode($mbid)),
        ];
    }

    private function matchesQuery(string $title, string $artist, string $query): bool
    {
        $needle = $this->normalize($query);
        if ($needle === '') {
            return false;
        }

        $normalizedTitle = $this->normalize($title);
        $normalizedArtist = $this->normalize($artist);

        if (
            $normalizedTitle === $needle
            || $normalizedArtist === $needle
            || str_starts_with($normalizedTitle, $needle)
            || str_starts_with($normalizedArtist, $needle)
            || str_contains($normalizedTitle, $needle)
            || str_contains($normalizedArtist, $needle)
        ) {
            return true;
        }

        $tokens = preg_split('/\s+/', $needle) ?: [];
        $tokens = array_values(array_filter($tokens, static fn(string $token): bool => $token !== ''));
        if ($tokens === []) {
            return false;
        }

        $haystack = $normalizedTitle . ' ' . $normalizedArtist;
        foreach ($tokens as $token) {
            if (!str_contains($haystack, $token)) {
                return false;
            }
        }

        return true;
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        if (class_exists('Normalizer')) {
            $normalized = \Normalizer::normalize($value, \Normalizer::FORM_D);
            if (is_string($normalized)) {
                $value = preg_replace('/\p{Mn}/u', '', $normalized) ?? $value;
            }
        }

        return $value;
    }

    private function isVariousArtist(string $artist): bool
    {
        $normalized = $this->normalize($artist);
        return $normalized === 'various artists'
            || $normalized === 'various'
            || $normalized === 'v.a.'
            || $normalized === 'va';
    }

    /**
     * @param array<string, mixed> $row
     */
    private function extractArtistCredit(array $row): string
    {
        $credits = $row['artist-credit'] ?? null;
        if (!is_array($credits) || $credits === []) {
            return 'Artiste inconnu';
        }

        $parts = [];
        foreach ($credits as $credit) {
            if (!is_array($credit)) {
                continue;
            }

            $name = '';
            if (isset($credit['name']) && is_string($credit['name'])) {
                $name = $credit['name'];
            } elseif (isset($credit['artist']['name']) && is_string($credit['artist']['name'])) {
                $name = $credit['artist']['name'];
            }

            $join = isset($credit['joinphrase']) && is_string($credit['joinphrase'])
                ? $credit['joinphrase']
                : '';

            $parts[] = $name . $join;
        }

        $artist = trim(implode('', $parts));
        return $artist !== '' ? $artist : 'Artiste inconnu';
    }

    private function fetchJson(string $url): MusicBrainzFetchResult
    {
        if (!function_exists('curl_init')) {
            return MusicBrainzFetchResult::fail(MusicBrainzFetchResult::ERROR_NETWORK);
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return MusicBrainzFetchResult::fail(MusicBrainzFetchResult::ERROR_NETWORK);
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'User-Agent: ' . self::USER_AGENT,
            ],
        ]);

        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErrno = curl_errno($ch);

        if ($body === false || $curlErrno !== 0) {
            return MusicBrainzFetchResult::fail(MusicBrainzFetchResult::ERROR_NETWORK);
        }

        if ($status === 503) {
            return MusicBrainzFetchResult::fail(MusicBrainzFetchResult::ERROR_RATE_LIMITED);
        }

        if ($status < 200 || $status >= 300) {
            return MusicBrainzFetchResult::fail(MusicBrainzFetchResult::ERROR_NETWORK);
        }

        if (!is_string($body) || $body === '') {
            return MusicBrainzFetchResult::fail(MusicBrainzFetchResult::ERROR_INVALID_RESPONSE);
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            return MusicBrainzFetchResult::fail(MusicBrainzFetchResult::ERROR_INVALID_RESPONSE);
        }

        if (isset($decoded['error'])) {
            return MusicBrainzFetchResult::fail(MusicBrainzFetchResult::ERROR_RATE_LIMITED);
        }

        return MusicBrainzFetchResult::ok($decoded);
    }
}
