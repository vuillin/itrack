<?php

declare(strict_types=1);
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
            <?php include 'views/albums.php'; ?>
            <?php include 'views/collection.php'; ?>
        </main>
    </div>

    <script type="module" src="assets/js/main.js"></script>
</body>

</html>
