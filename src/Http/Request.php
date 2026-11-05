<?php

declare(strict_types=1);

final class Request
{
    private static ?string $testMethod = null;
    private static ?string $testBody = null;

    public static function setTestMethod(string $method): void
    {
        self::$testMethod = strtoupper($method);
    }

    public static function setTestBody(string $body): void
    {
        self::$testBody = $body;
    }

    public static function resetTestState(): void
    {
        self::$testMethod = null;
        self::$testBody = null;
    }

    public static function method(): string
    {
        if (self::$testMethod !== null) {
            return self::$testMethod;
        }

        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    /**
     * @return array<string, mixed>
     */
    public static function jsonBody(): array
    {
        $raw = self::$testBody;
        if ($raw === null) {
            $raw = file_get_contents('php://input');
        }

        if ($raw === false || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    public static function header(string $name): ?string
    {
        $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        if (isset($_SERVER[$serverKey]) && is_string($_SERVER[$serverKey])) {
            return $_SERVER[$serverKey];
        }

        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            foreach ($headers as $key => $value) {
                if (strcasecmp((string) $key, $name) === 0) {
                    return (string) $value;
                }
            }
        }

        return null;
    }

}
