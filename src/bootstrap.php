<?php

declare(strict_types=1);

require_once __DIR__ . '/Env.php';
require_once __DIR__ . '/Database.php';

loadEnv(dirname(__DIR__) . '/.env');

$pdo = Database::connection();
