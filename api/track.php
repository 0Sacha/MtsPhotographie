<?php
/*
 * Statistiques de visite, appelées par main.js.
 * Respect de la vie privée : pas de cookie, pas d'adresse IP stockée.
 * Le « visiteur » est une empreinte anonyme qui change chaque jour (impossible de suivre quelqu'un dans le temps).
 */
declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$navigateur = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');

// On ignore les robots (Google, aperçus de liens…)
if ($navigateur === '' || preg_match('/bot|crawl|spider|slurp|preview|headless|lighthouse|facebookexternalhit/i', $navigateur)) {
    http_response_code(204);
    exit;
}

// On ne compte pas les visites des administrateurs connectés
if (isset($_COOKIE['mts_admin'])) {
    demarrer_session();
    $estAdmin = !empty($_SESSION['user_id']);
    session_write_close();
    if ($estAdmin) {
        http_response_code(204);
        exit;
    }
}

$donnees = json_decode((string) file_get_contents('php://input', false, null, 0, 2048), true);
if (!is_array($donnees)) {
    http_response_code(400);
    exit;
}

try {
    $type = $donnees['type'] ?? '';

    if ($type === 'page') {
        $pages = ['accueil', 'portfolio', 'services', 'apropos', 'contact'];
        $page = (string) ($donnees['page'] ?? '');
        if (!in_array($page, $pages, true)) {
            http_response_code(400);
            exit;
        }

        // Provenance : uniquement le nom du site d'origine (ex. « google.com »), jamais l'adresse complète
        $provenance = strtolower((string) parse_url((string) ($donnees['ref'] ?? ''), PHP_URL_HOST));
        $provenance = preg_replace('/^(www\.|m\.|l\.)/', '', $provenance) ?? '';
        $hoteSite = preg_replace('/:\d+$/', '', strtolower((string) ($_SERVER['HTTP_HOST'] ?? '')));
        if ($provenance === $hoteSite || $provenance === preg_replace('/^www\./', '', $hoteSite)) {
            $provenance = ''; // navigation interne au site
        }

        $appareil = match (true) {
            (bool) preg_match('/iPad|Tablet|PlayBook|Silk|(Android(?!.*Mobile))/i', $navigateur) => 'tablette',
            (bool) preg_match('/Mobi|iPhone|iPod|Android/i', $navigateur)                       => 'mobile',
            default                                                                              => 'ordinateur',
        };

        $visiteur = substr(hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . $navigateur . '|' . date('Y-m-d') . '|' . config('secret', '')), 0, 16);

        db()->prepare('INSERT INTO page_views (page, referrer, device, visitor, created_at) VALUES (?, ?, ?, ?, ?)')
            ->execute([$page, mb_substr($provenance, 0, 100), $appareil, $visiteur, now()]);
    } elseif ($type === 'photo') {
        db()->prepare('INSERT INTO photo_views (photo_id, created_at) SELECT id, ? FROM photos WHERE id = ? AND visible = 1')
            ->execute([now(), (int) ($donnees['id'] ?? 0)]);
    } else {
        http_response_code(400);
        exit;
    }

    // De temps en temps, on supprime les statistiques de plus de 13 mois
    if (random_int(1, 200) === 1) {
        db()->prepare('DELETE FROM page_views WHERE created_at < ?')->execute([now('-13 months')]);
        db()->prepare('DELETE FROM photo_views WHERE created_at < ?')->execute([now('-13 months')]);
    }
} catch (Throwable $e) {
    error_log('[MTS] track : ' . $e->getMessage());
    http_response_code(500);
    exit;
}

http_response_code(204);
