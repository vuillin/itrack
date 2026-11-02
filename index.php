<?php

declare(strict_types=1);

require_once __DIR__ . '/src/Env.php';
loadEnv(__DIR__ . '/.env');

$apiToken = getenv('ITRACK_API_TOKEN') ?: '';
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>iTrack</title>
    <link rel="icon" href="assets/images/assets/itrack.ico?v=3" type="image/x-icon">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .hidden {
            display: none !important;
        }
    </style>
</head>

<body>
    <?php include 'components/sidebar.php'; ?>

    <div class="app-container">
        <main class="main-content">
            <?php include 'views/rechercher.php'; ?>
            <?php include 'views/accueil.php'; ?>
            <?php include 'views/nouveautes.php'; ?>
            <?php include 'views/albums.php'; ?>
            <?php include 'views/collection.php'; ?>
        </main>
    </div>

    <script>
        window.__ITRACK__ = {
            apiToken: <?= json_encode($apiToken, JSON_UNESCAPED_UNICODE) ?>
        };
    </script>
    <script type="module" src="assets/js/main.js"></script>
</body>

</html>
