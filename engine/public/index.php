<?php

require __DIR__ . '/../vendor/autoload.php';

use Engine\App\HomePage;

$widgetTree = (new HomePage())->build();

?><!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PHP Engine</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center">
    <?= $widgetTree->render() ?>
</body>
</html>
