<?php
declare(strict_types=1);

require __DIR__ . '/_init.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifier_csrf();
    $_SESSION = [];
    $parametres = session_get_cookie_params();
    setcookie(session_name(), '', time() - 3600, $parametres['path'], $parametres['domain'], $parametres['secure'], $parametres['httponly']);
    session_destroy();
}

rediriger('login.php');
