<?php
/*
 * Point d'entrée commun : chargé en premier par toutes les pages PHP.
 */
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

date_default_timezone_set('Europe/Paris');
mb_internal_encoding('UTF-8');

$configFile = __DIR__ . '/config.php';
if (!is_file($configFile)) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Site non configuré : copiez includes/config.example.php en includes/config.php et renseignez-le.";
    exit;
}

$GLOBALS['APP_CONFIG'] = require $configFile;

if (!empty($GLOBALS['APP_CONFIG']['debug'])) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}

require __DIR__ . '/functions.php';
