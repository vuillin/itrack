<?php

declare(strict_types=1);

final class MusicBrainzService
{
    private const BASE_URL = 'https://musicbrainz.org/ws/2/';
    private const COVER_ART_FRONT = 'https://coverartarchive.org/release-group/%s/front-250';
    private const USER_AGENT = 'iTrack/0.1 (personal music library; local)';
    private const DEFAULT_LIMIT = 25;

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

        // Keep the Lucene query simple: user text + album/EP primary type.
        $lucene = sprintf(
            '(%s) AND (primarytype:album OR primarytype:ep)',
            $this->escapeLucene($query)
        );

        $url = self::BASE_URL . 'release-group?' . http_build_query([
            'query' => $lucene,
            'fmt' => 'json',
            'limit' => $limit,
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

            $mapped = $this->mapReleaseGroup($row);
            if ($mapped !== null) {
                $items[] = $mapped;
            }
        }

        return MusicBrainzFetchResult::ok($items);
    }

    private function escapeLucene(string $value): string
    {
        // Escape Lucene special characters so free-text search stays safe.
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
    private function mapReleaseGroup(array $row): ?array
    {
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

        $date = isset($row['first-release-date']) && is_string($row['first-release-date'])
            ? trim($row['first-release-date'])
            : null;

        return [
            'mbid' => $mbid,
            'title' => $title,
            'artist' => $this->extractArtistCredit($row),
            'primary_type' => $primaryType,
            'first_release_date' => $date !== '' ? $date : null,
            'cover_url' => sprintf(self::COVER_ART_FRONT, rawurlencode($mbid)),
        ];
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
