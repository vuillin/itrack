<?php

declare(strict_types=1);

final class Input
{
    public static function requirePositiveInt(mixed $value, string $field): int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }
        if (is_string($value) && ctype_digit($value)) {
            $int = (int) $value;
            if ($int > 0) {
                return $int;
            }
        }
        JsonResponse::error("{$field} invalide");
    }

    public static function requireFlag(mixed $value, string $field): int
    {
        if ($value === true || $value === 1 || $value === '1') {
            return 1;
        }
        if ($value === false || $value === 0 || $value === '0') {
            return 0;
        }
        JsonResponse::error("{$field} doit être 0 ou 1");
    }

    /**
     * @param array<string, mixed> $data
     * @return array{vinyl: int, cd: int, cassette: int, boxset: int}
     */
    public static function readFormatFlags(array $data): array
    {
        $formats = [];
        foreach (['vinyl', 'cd', 'cassette', 'boxset'] as $field) {
            if (!array_key_exists($field, $data)) {
                $formats[$field] = 0;
                continue;
            }
            $formats[$field] = self::requireFlag($data[$field], $field);
        }
        return $formats;
    }
}
