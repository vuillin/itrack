<?php

declare(strict_types=1);

final class Icon
{
    public static function render(string $name): string
    {
        $path = dirname(__DIR__) . '/assets/icons/' . $name . '.svg';
        if (!is_file($path)) {
            return '';
        }

        $svg = file_get_contents($path);
        return $svg === false ? '' : $svg;
    }
}
