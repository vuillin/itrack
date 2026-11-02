<?php

declare(strict_types=1);

final class MusicBrainzFetchResult
{
    public const ERROR_NETWORK = 'network';
    public const ERROR_RATE_LIMITED = 'rate_limited';
    public const ERROR_INVALID_RESPONSE = 'invalid_response';
    public const ERROR_INVALID_QUERY = 'invalid_query';

    private function __construct(
        public readonly ?array $data,
        public readonly ?string $error,
    ) {
    }

    public static function ok(array $data): self
    {
        return new self($data, null);
    }

    public static function fail(string $error): self
    {
        return new self(null, $error);
    }

    public function isOk(): bool
    {
        return $this->error === null;
    }

    public function respondAsJsonError(): never
    {
        $map = [
            self::ERROR_NETWORK => ['MusicBrainz indisponible', 502],
            self::ERROR_RATE_LIMITED => ['MusicBrainz temporairement saturé', 503],
            self::ERROR_INVALID_RESPONSE => ['Réponse MusicBrainz invalide', 502],
            self::ERROR_INVALID_QUERY => ['Requête de recherche invalide', 400],
        ];

        [$message, $status] = $map[$this->error] ?? ['Erreur MusicBrainz', 502];
        JsonResponse::error($message, $status);
    }
}
