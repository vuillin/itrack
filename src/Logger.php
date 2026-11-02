<?php

declare(strict_types=1);

final class Logger
{
    public static function error(string $message, ?Throwable $e = null): void
    {
        $dir = dirname(__DIR__) . '/logs';
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            error_log('[itrack] Impossible de créer le dossier logs/');
            return;
        }

        $line = '[' . date('Y-m-d H:i:s') . '] ' . $message;

        if ($e !== null) {
            $line .= ': ' . $e->getMessage()
                . ' in ' . $e->getFile() . ':' . $e->getLine();
        }

        $line .= PHP_EOL;

        $written = @file_put_contents($dir . '/app.log', $line, FILE_APPEND | LOCK_EX);
        if ($written === false) {
            error_log('[itrack] ' . rtrim($line));
        }
    }
}
