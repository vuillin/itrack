<?php

declare(strict_types=1);

final class JsonResponse
{
    private static bool $testMode = false;

    public static function enableTestMode(bool $enabled = true): void
    {
        self::$testMode = $enabled;
    }

    public static function send(mixed $data, int $status = 200): void
    {
        if (!self::$testMode) {
            http_response_code($status);
        }

        if (self::$testMode) {
            throw new JsonResponseException($data, $status);
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function error(string $message, int $status = 400): void
    {
        self::send(['error' => $message], $status);
    }
}
