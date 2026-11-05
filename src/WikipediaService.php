<?php

declare(strict_types=1);

/**
 * Short album blurbs via Wikipedia / Wikidata (linked from MusicBrainz).
 */
final class WikipediaService
{
    private const USER_AGENT = 'iTrack/0.1 (personal music library; local)';
    private const PREFERRED_LANGS = ['fr', 'en'];

    /**
     * @param mixed $relations MusicBrainz url-rels list
     */
    public static function summaryFromRelations(mixed $relations): ?string
    {
        if (!is_array($relations)) {
            return null;
        }

        $wikipediaUrl = null;
        $wikidataUrl = null;

        foreach ($relations as $relation) {
            if (!is_array($relation)) {
                continue;
            }

            $type = isset($relation['type']) && is_string($relation['type'])
                ? strtolower($relation['type'])
                : '';
            $resource = '';
            if (isset($relation['url']) && is_array($relation['url'])
                && isset($relation['url']['resource']) && is_string($relation['url']['resource'])
            ) {
                $resource = trim($relation['url']['resource']);
            }

            if ($resource === '') {
                continue;
            }

            if ($type === 'wikipedia' && $wikipediaUrl === null) {
                $wikipediaUrl = $resource;
            } elseif ($type === 'wikidata' && $wikidataUrl === null) {
                $wikidataUrl = $resource;
            }
        }

        if ($wikipediaUrl !== null) {
            $summary = self::summaryFromWikipediaUrl($wikipediaUrl);
            if ($summary !== null) {
                return $summary;
            }
        }

        if ($wikidataUrl !== null) {
            return self::summaryFromWikidataUrl($wikidataUrl);
        }

        return null;
    }

    public static function summaryFromWikipediaUrl(string $url): ?string
    {
        $parsed = self::parseWikipediaUrl($url);
        if ($parsed === null) {
            return null;
        }

        return self::fetchPageSummary($parsed['lang'], $parsed['title']);
    }

    public static function summaryFromWikidataUrl(string $url): ?string
    {
        if (!preg_match('#wikidata\.org/wiki/(Q\d+)#i', $url, $match)) {
            return null;
        }

        $qid = $match[1];
        $apiUrl = 'https://www.wikidata.org/w/api.php?' . http_build_query([
            'action' => 'wbgetentities',
            'ids' => $qid,
            'props' => 'sitelinks',
            'format' => 'json',
        ]);

        $data = self::fetchJson($apiUrl);
        if ($data === null) {
            return null;
        }

        $sitelinks = $data['entities'][$qid]['sitelinks'] ?? null;
        if (!is_array($sitelinks)) {
            return null;
        }

        foreach (self::PREFERRED_LANGS as $lang) {
            $key = $lang . 'wiki';
            $title = isset($sitelinks[$key]['title']) && is_string($sitelinks[$key]['title'])
                ? $sitelinks[$key]['title']
                : '';
            if ($title === '') {
                continue;
            }

            $summary = self::fetchPageSummary($lang, $title);
            if ($summary !== null) {
                return $summary;
            }
        }

        return null;
    }

    /**
     * @return array{lang: string, title: string}|null
     */
    private static function parseWikipediaUrl(string $url): ?array
    {
        $parts = parse_url($url);
        if (!is_array($parts)) {
            return null;
        }

        $host = isset($parts['host']) && is_string($parts['host']) ? strtolower($parts['host']) : '';
        $path = isset($parts['path']) && is_string($parts['path']) ? $parts['path'] : '';

        if (!preg_match('#^([a-z]{2,3})\.wikipedia\.org$#', $host, $hostMatch)) {
            return null;
        }

        if (!preg_match('#^/wiki/(.+)$#', $path, $pathMatch)) {
            return null;
        }

        $title = rawurldecode(str_replace('_', ' ', $pathMatch[1]));
        $title = trim($title);
        if ($title === '') {
            return null;
        }

        return [
            'lang' => $hostMatch[1],
            'title' => $title,
        ];
    }

    private static function fetchPageSummary(string $lang, string $title): ?string
    {
        $lang = strtolower(preg_replace('/[^a-z]/', '', $lang) ?? '');
        if ($lang === '') {
            return null;
        }

        $url = sprintf(
            'https://%s.wikipedia.org/api/rest_v1/page/summary/%s',
            $lang,
            rawurlencode(str_replace(' ', '_', $title))
        );

        $data = self::fetchJson($url);
        if ($data === null) {
            return null;
        }

        $type = isset($data['type']) && is_string($data['type']) ? $data['type'] : '';
        if ($type === 'disambiguation' || $type === 'no-extract') {
            return null;
        }

        $extract = isset($data['extract']) && is_string($data['extract'])
            ? trim($data['extract'])
            : '';

        if ($extract === '') {
            return null;
        }

        // Keep the blurb short for the modal.
        if (mb_strlen($extract) > 420) {
            $cut = mb_substr($extract, 0, 420);
            $break = mb_strrpos($cut, ' ');
            $extract = trim(($break !== false ? mb_substr($cut, 0, $break) : $cut)) . '…';
        }

        return $extract;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function fetchJson(string $url): ?array
    {
        if (!function_exists('curl_init')) {
            return null;
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'User-Agent: ' . self::USER_AGENT,
            ],
        ]);

        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErrno = curl_errno($ch);

        if ($body === false || $curlErrno !== 0 || $status < 200 || $status >= 300) {
            return null;
        }

        if (!is_string($body) || $body === '') {
            return null;
        }

        $decoded = json_decode($body, true);
        return is_array($decoded) ? $decoded : null;
    }
}
