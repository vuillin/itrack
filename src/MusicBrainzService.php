<?php

declare(strict_types=1);

final class MusicBrainzService
{
    private const BASE_URL = 'https://musicbrainz.org/ws/2/';
    private const USER_AGENT = 'iTrack/0.1 (personal music library; local)';
    private const DEFAULT_LIMIT = 10;
    private const FETCH_LIMIT = 30;
    private const MIN_SCORE = 80;
    public const DEFAULT_TYPE = 'album';

    /**
     * Search categories exposed by the UI / API.
     *
     * @var array<string, array{label: string, placeholder: string, primary: list<string>, secondary: string|null, exclude_secondary: list<string>}>
     */
    public const SEARCH_TYPES = [
        'album' => [
            'label' => 'Albums',
            'placeholder' => 'Album',
            'primary' => ['Album'],
            'secondary' => null,
            'exclude_secondary' => ['Compilation', 'Live', 'Remix', 'Soundtrack', 'DJ-mix', 'Interview', 'Demo', 'Mixtape/Street'],
        ],
        'ep' => [
            'label' => 'EP',
            'placeholder' => 'EP',
            'primary' => ['EP'],
            'secondary' => null,
            'exclude_secondary' => ['Compilation', 'Live', 'Remix', 'Soundtrack', 'DJ-mix', 'Interview', 'Demo', 'Mixtape/Street'],
        ],
        'single' => [
            'label' => 'Singles',
            'placeholder' => 'Single',
            'primary' => ['Single'],
            'secondary' => null,
            'exclude_secondary' => ['Compilation', 'Live', 'Remix', 'Soundtrack', 'DJ-mix', 'Interview', 'Demo', 'Mixtape/Street'],
        ],
        'live' => [
            'label' => 'Live',
            'placeholder' => 'Live',
            'primary' => ['Album', 'EP'],
            'secondary' => 'Live',
            'exclude_secondary' => [],
        ],
        'compilation' => [
            'label' => 'Compilations',
            'placeholder' => 'Compilation',
            'primary' => ['Album', 'EP'],
            'secondary' => 'Compilation',
            'exclude_secondary' => [],
        ],
        'remix' => [
            'label' => 'Remix',
            'placeholder' => 'Remix',
            'primary' => ['Album', 'EP', 'Single'],
            'secondary' => 'Remix',
            'exclude_secondary' => [],
        ],
        'soundtrack' => [
            'label' => 'Soundtracks',
            'placeholder' => 'Soundtrack',
            'primary' => ['Album', 'EP'],
            'secondary' => 'Soundtrack',
            'exclude_secondary' => [],
        ],
    ];

    /**
     * Search release-groups by album title and/or artist and category.
     *
     * @return MusicBrainzFetchResult list of items on success
     */
    public function searchAlbums(
        string $titleQuery,
        string $artistQuery = '',
        string $type = self::DEFAULT_TYPE,
        int $limit = self::DEFAULT_LIMIT
    ): MusicBrainzFetchResult {
        $titleQuery = trim($titleQuery);
        $artistQuery = trim($artistQuery);
        if ($titleQuery === '' && $artistQuery === '') {
            return MusicBrainzFetchResult::fail(MusicBrainzFetchResult::ERROR_INVALID_QUERY);
        }

        $type = $this->normalizeType($type);
        $limit = max(1, min(100, $limit));

        $url = self::BASE_URL . 'release-group?' . http_build_query([
            'query' => $this->buildLuceneQuery($titleQuery, $artistQuery, $type),
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

            $mapped = $this->mapReleaseGroup($row, $titleQuery, $artistQuery, $type);
            if ($mapped !== null) {
                $items[] = $mapped;
            }

            if (count($items) >= $limit) {
                break;
            }
        }

        return MusicBrainzFetchResult::ok($items);
    }

    public function normalizeType(string $type): string
    {
        $type = strtolower(trim($type));
        return array_key_exists($type, self::SEARCH_TYPES) ? $type : self::DEFAULT_TYPE;
    }

    /**
     * @return array{label: string, placeholder: string, primary: list<string>, secondary: string|null, exclude_secondary: list<string>}
     */
    private function typeConfig(string $type): array
    {
        return self::SEARCH_TYPES[$this->normalizeType($type)];
    }

    private function buildLuceneQuery(string $titleQuery, string $artistQuery, string $type): string
    {
        $config = $this->typeConfig($type);
        $parts = [];

        if ($titleQuery !== '') {
            $titlePhrase = $this->quotePhrase($titleQuery);
            $titleEscaped = $this->escapeLucene($titleQuery);
            $parts[] = sprintf('(releasegroup:%s^4 OR releasegroup:(%s))', $titlePhrase, $titleEscaped);
        }

        if ($artistQuery !== '') {
            $artistPhrase = $this->quotePhrase($artistQuery);
            $artistEscaped = $this->escapeLucene($artistQuery);
            $parts[] = sprintf('(artist:%s^4 OR artist:(%s))', $artistPhrase, $artistEscaped);
        }

        if (is_array($config['primary']) && $config['primary'] !== []) {
            $primaryClauses = array_map(
                static fn(string $primary): string => 'primarytype:' . strtolower($primary),
                $config['primary']
            );
            $parts[] = '(' . implode(' OR ', $primaryClauses) . ')';
        }

        if (is_string($config['secondary']) && $config['secondary'] !== '') {
            $parts[] = 'secondarytype:' . strtolower($config['secondary']);
        }

        foreach ($config['exclude_secondary'] as $excluded) {
            $parts[] = 'NOT secondarytype:' . strtolower($excluded);
        }

        // Compilations often use Various Artists — keep them for that category only.
        if ($type !== 'compilation') {
            $parts[] = 'NOT artist:"Various Artists"';
        }

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
    private function mapReleaseGroup(array $row, string $titleQuery, string $artistQuery, string $type): ?array
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

        $config = $this->typeConfig($type);
        if (is_array($config['primary']) && $config['primary'] !== [] && !in_array($primaryType, $config['primary'], true)) {
            return null;
        }

        $secondaryTypes = [];
        $rawSecondary = $row['secondary-types'] ?? [];
        if (is_array($rawSecondary)) {
            foreach ($rawSecondary as $secondaryType) {
                if (is_string($secondaryType) && $secondaryType !== '') {
                    $secondaryTypes[] = $secondaryType;
                }
            }
        }

        if (is_string($config['secondary']) && $config['secondary'] !== '') {
            if (!in_array($config['secondary'], $secondaryTypes, true)) {
                return null;
            }
        }

        foreach ($config['exclude_secondary'] as $excluded) {
            if (in_array($excluded, $secondaryTypes, true)) {
                return null;
            }
        }

        $artist = $this->extractArtistCredit($row);
        if ($type !== 'compilation' && $this->isVariousArtist($artist)) {
            return null;
        }

        if (!$this->matchesQuery($title, $artist, $titleQuery, $artistQuery)) {
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
            'cover_url' => CoverArtService::proxyUrl($mbid),
        ];
    }

    private function matchesQuery(string $title, string $artist, string $titleQuery, string $artistQuery): bool
    {
        $normalizedTitle = $this->normalize($title);
        $normalizedArtist = $this->normalize($artist);

        if ($titleQuery !== '' && !$this->valueMatchesNeedle($normalizedTitle, $this->normalize($titleQuery))) {
            return false;
        }

        if ($artistQuery !== '' && !$this->valueMatchesNeedle($normalizedArtist, $this->normalize($artistQuery))) {
            return false;
        }

        return $titleQuery !== '' || $artistQuery !== '';
    }

    private function valueMatchesNeedle(string $value, string $needle): bool
    {
        if ($needle === '') {
            return true;
        }

        if (str_contains($value, $needle)) {
            return true;
        }

        $tokens = preg_split('/\s+/', $needle) ?: [];
        $tokens = array_values(array_filter($tokens, static fn(string $token): bool => $token !== ''));
        if ($tokens === []) {
            return false;
        }

        foreach ($tokens as $token) {
            if (!str_contains($value, $token)) {
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
