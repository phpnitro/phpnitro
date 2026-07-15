<?php

require __DIR__ . '/../vendor/autoload.php';

use Engine\App\HomePage;

session_start();

$screen = new HomePage();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_action'])) {
    $screen->handle($_POST['_action']);
    header('Location: ' . $_SERVER['REQUEST_URI'], true, 303);
    exit;
}

$widgetTree = $screen->build();

?><!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PHP Engine</title>
    <link rel="stylesheet" href="tailwind.css">
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center">
    <?= $widgetTree->render() ?>
</body>
</html>
