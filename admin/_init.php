<?php
/*
 * Chargé en premier par toutes les pages du dashboard.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';

// Le dashboard ne doit jamais apparaître dans Google ni être affiché dans une autre page
header('X-Robots-Tag: noindex, nofollow');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

set_exception_handler(function (Throwable $erreur): void {
    error_log('[MTS] admin : ' . $erreur->getMessage());
    if (est_requete_json()) {
        json_reponse(['ok' => false, 'erreur' => 'Erreur du serveur' . (config('debug') ? ' : ' . $erreur->getMessage() : '.')], 500);
    }
    maintenance($erreur);
});

demarrer_session();
