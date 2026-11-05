<?php

declare(strict_types=1);

final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $dbFile = dirname(__DIR__) . '/albums.sqlite';
        $schemaFile = dirname(__DIR__) . '/schema.sql';

        try {
            self::$pdo = new PDO('sqlite:' . $dbFile);
            self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            if (file_exists($schemaFile)) {
                $schema = file_get_contents($schemaFile);
                if ($schema !== false && $schema !== '') {
                    self::$pdo->exec($schema);
                }
            }

            self::migrate(self::$pdo);
        } catch (PDOException $e) {
            throw new RuntimeException('Erreur de connexion à la base de données.', 0, $e);
        }

        return self::$pdo;
    }

    private static function migrate(PDO $pdo): void
    {
        $existing = [];
        $stmt = $pdo->query('PRAGMA table_info(albums)');
        if ($stmt !== false) {
            foreach ($stmt->fetchAll() as $column) {
                if (isset($column['name']) && is_string($column['name'])) {
                    $existing[$column['name']] = true;
                }
            }
        }

        $additions = [
            'primary_type' => 'TEXT',
            'secondary_types' => 'TEXT',
            'first_release_date' => 'TEXT',
            'disambiguation' => 'TEXT',
            'tracks_json' => 'TEXT',
            'mb_rating' => 'REAL',
            'mb_rating_votes' => 'INTEGER',
        ];

        foreach ($additions as $name => $type) {
            if (!isset($existing[$name])) {
                $pdo->exec('ALTER TABLE albums ADD COLUMN ' . $name . ' ' . $type);
            }
        }
    }
}
