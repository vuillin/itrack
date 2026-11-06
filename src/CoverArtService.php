<?php

declare(strict_types=1);

final class CoverArtService
{
    private const USER_AGENT = 'iTrack/0.1 (personal music library; local)';
    private const CAA_FRONT = 'https://coverartarchive.org/release-group/%s/front-250';
    private const NEGATIVE_TTL_SECONDS = 86400;
    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    public function __construct(
        private string $cacheDir,
        private string $libraryDir,
    ) {
    }

    public static function fromProjectRoot(?string $projectRoot = null): self
    {
        $root = $projectRoot ?? dirname(__DIR__);
        return new self(
            $root . '/cache/covers',
            $root . '/assets/images/covers',
        );
    }

    public static function isValidMbid(string $mbid): bool
    {
        return (bool) preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $mbid
        );
    }

    public static function proxyUrl(string $mbid): string
    {
        return 'api.php?mb_cover=' . rawurlencode($mbid);
    }

    /**
     * Stream a cached (or freshly fetched) cover to the client (search / preview).
     */
    public function respond(string $mbid): void
    {
        $mbid = strtolower(trim($mbid));
        if (!self::isValidMbid($mbid)) {
            http_response_code(400);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'MBID invalide';
            exit;
        }

        $path = $this->ensureCacheFile($mbid);
        if ($path === null) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            header('Cache-Control: public, max-age=3600');
            echo 'Pochette introuvable';
            exit;
        }

        $mime = $this->detectMime($path) ?? 'image/jpeg';
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string) filesize($path));
        header('Cache-Control: public, max-age=604800');
        readfile($path);
        exit;
    }

    /**
     * Download (or reuse cache) into the library folder and return the DB filename.
     */
    public function persistLibraryCover(string $mbid): ?string
    {
        $mbid = strtolower(trim($mbid));
        if (!self::isValidMbid($mbid)) {
            return null;
        }

        $existing = $this->findLibraryCoverFilename($mbid);
        if ($existing !== null) {
            return $existing;
        }

        $sourcePath = $this->ensureCacheFile($mbid);
        if ($sourcePath === null) {
            return null;
        }

        $this->ensureDir($this->libraryDir);

        $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
        if ($extension === '' || !in_array($extension, self::IMAGE_EXTENSIONS, true)) {
            $extension = 'jpg';
        }

        $filename = $mbid . '.' . $extension;
        $destination = $this->libraryPath($filename);
        if (!@copy($sourcePath, $destination)) {
            Logger::error('Impossible de copier la pochette library ' . $mbid);
            return null;
        }

        return $filename;
    }

    public function libraryCoverExists(?string $filename): bool
    {
        if ($filename === null) {
            return false;
        }

        $filename = basename(trim($filename));
        if ($filename === '' || str_contains($filename, '..')) {
            return false;
        }

        $path = $this->libraryPath($filename);
        return is_file($path) && filesize($path) > 0;
    }

    public function deleteLibraryCover(?string $filename): void
    {
        if ($filename === null) {
            return;
        }

        $filename = basename(trim($filename));
        if ($filename === '' || str_contains($filename, '..')) {
            return;
        }

        $path = $this->libraryPath($filename);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    private function findLibraryCoverFilename(string $mbid): ?string
    {
        foreach (self::IMAGE_EXTENSIONS as $extension) {
            $filename = $mbid . '.' . $extension;
            $path = $this->libraryPath($filename);
            if (is_file($path) && filesize($path) > 0) {
                return $filename;
            }
        }

        return null;
    }

    private function ensureCacheFile(string $mbid): ?string
    {
        $this->ensureDir($this->cacheDir);

        foreach (self::IMAGE_EXTENSIONS as $extension) {
            $imagePath = $this->cachePath($mbid, $extension);
            if (is_file($imagePath) && filesize($imagePath) > 0) {
                return $imagePath;
            }
        }

        $missingPath = $this->missingPath($mbid);
        if (is_file($missingPath)) {
            $age = time() - (int) filemtime($missingPath);
            if ($age < self::NEGATIVE_TTL_SECONDS) {
                return null;
            }
            @unlink($missingPath);
        }

        $binary = $this->fetchCoverBinary($mbid);
        if ($binary === null) {
            @file_put_contents($missingPath, (string) time());
            return null;
        }

        $targetPath = $this->cachePath($mbid, $binary['extension']);
        $written = @file_put_contents($targetPath, $binary['body']);
        if ($written === false) {
            Logger::error('Impossible d\'écrire le cache pochette ' . $mbid);
            return null;
        }

        return $targetPath;
    }

    /**
     * @return array{body: string, extension: string}|null
     */
    private function fetchCoverBinary(string $mbid): ?array
    {
        if (!function_exists('curl_init')) {
            return null;
        }

        $url = sprintf(self::CAA_FRONT, rawurlencode($mbid));
        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_USERAGENT => self::USER_AGENT,
            CURLOPT_HTTPHEADER => [
                'Accept: image/avif,image/webp,image/apng,image/*,*/*;q=0.8',
            ],
        ]);

        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $curlErrno = curl_errno($ch);

        if ($body === false || $curlErrno !== 0 || $status < 200 || $status >= 300) {
            return null;
        }

        if (!is_string($body) || $body === '') {
            return null;
        }

        // CAA may return JSON/HTML error bodies with a 200 in edge cases.
        if (str_starts_with(ltrim($body), '{') || str_starts_with(ltrim($body), '<')) {
            return null;
        }

        $extension = $this->extensionFromMime($contentType) ?? 'jpg';

        return [
            'body' => $body,
            'extension' => $extension,
        ];
    }

    private function ensureDir(string $dir): void
    {
        if (is_dir($dir)) {
            return;
        }

        if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Impossible de créer le dossier ' . $dir);
        }
    }

    private function cachePath(string $mbid, string $extension = 'jpg'): string
    {
        return $this->cacheDir . '/' . $mbid . '.' . $extension;
    }

    private function libraryPath(string $filename): string
    {
        return $this->libraryDir . '/' . $filename;
    }

    private function missingPath(string $mbid): string
    {
        return $this->cacheDir . '/' . $mbid . '.missing';
    }

    private function extensionFromMime(string $contentType): ?string
    {
        $mime = strtolower(trim(explode(';', $contentType)[0] ?? ''));
        return match ($mime) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => null,
        };
    }

    private function detectMime(string $path): ?string
    {
        if (function_exists('mime_content_type')) {
            $mime = mime_content_type($path);
            if (is_string($mime) && str_starts_with($mime, 'image/')) {
                return $mime;
            }
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => null,
        };
    }
}
