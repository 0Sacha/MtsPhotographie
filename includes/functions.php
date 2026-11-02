<?php
/*
 * Fonctions communes au site et au dashboard.
 */
declare(strict_types=1);

/* ---------------------------------------------------------------
 * Configuration et base de données
 * ------------------------------------------------------------- */

function config(string $cle, mixed $defaut = null): mixed
{
    return $GLOBALS['APP_CONFIG'][$cle] ?? $defaut;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $c = config('db');
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $c['host'], $c['port'] ?? 3306, $c['name']);
        $pdo = new PDO($dsn, $c['user'], $c['password'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

/** Date et heure actuelles (heure de Paris), au format MySQL */
function now(string $modif = 'now'): string
{
    return (new DateTimeImmutable($modif))->format('Y-m-d H:i:s');
}

/** Échappe un texte pour l'afficher dans du HTML */
function e(mixed $texte): string
{
    return htmlspecialchars((string) $texte, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Page « Site en maintenance ».
 * Code HTTP 503 + Retry-After : Google comprend que c'est temporaire et ne retire pas le site des résultats.
 */
function page_maintenance(string $detail = ''): never
{
    // Nom et e-mail affichés si la base répond, sinon valeurs de secours
    try {
        $nom = setting('site_nom');
        $email = setting('contact_email');
    } catch (Throwable) {
        $nom = 'MTS Photographie';
        $email = '';
    }

    http_response_code(503);
    header('Retry-After: 3600');
    header('Content-Type: text/html; charset=utf-8');
    header('X-Robots-Tag: noindex');
    ?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Maintenance – <?= e($nom) ?></title>
    <style>
        body { display: grid; place-items: center; min-height: 100vh; margin: 0; padding: 24px; box-sizing: border-box; background: #FAFAF9; color: #0A0A0A; font-family: Arial, Helvetica, sans-serif; text-align: center; }
        .icone { display: inline-grid; place-items: center; width: 64px; height: 64px; margin-bottom: 24px; border-radius: 18px; background: #0A0A0A; }
        p.surtitre { margin: 0 0 12px; color: #8A8A8A; font-size: 13px; letter-spacing: 4px; text-transform: uppercase; }
        h1 { margin: 0 0 16px; font-size: clamp(32px, 7vw, 56px); font-weight: 400; }
        p { max-width: 460px; margin: 0 auto; color: #6A6A6A; font-size: 18px; line-height: 1.5; }
        a { color: #3F6B47; }
        pre { max-width: 90vw; margin-top: 24px; color: #B42318; text-align: left; white-space: pre-wrap; }
    </style>
</head>
<body>
    <main>
        <span class="icone" aria-hidden="true">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#FEFEFE" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"/><circle cx="12" cy="13" r="3"/></svg>
        </span>
        <p class="surtitre"><?= e($nom) ?></p>
        <h1>Site en maintenance</h1>
        <p>Le site se refait une beauté. Revenez dans quelques instants.</p>
<?php if ($email !== ''): ?>
        <p style="margin-top:16px;font-size:15px">Une question ? <a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></p>
<?php endif; ?>
<?php if ($detail !== ''): ?>
        <pre><?= e($detail) ?></pre>
<?php endif; ?>
    </main>
</body>
</html>
<?php
    exit;
}

/** Erreur technique (base inaccessible…) : journalise puis affiche la page de maintenance */
function maintenance(Throwable $erreur): never
{
    error_log('[MTS] ' . $erreur->getMessage());
    page_maintenance(config('debug') ? $erreur->getMessage() : '');
}

/** Le mode maintenance est-il activé depuis le dashboard ? */
function maintenance_active(): bool
{
    try {
        return setting('maintenance') === '1';
    } catch (Throwable) {
        return false;
    }
}

/** Un administrateur est-il connecté ? (sans ouvrir de session pour les simples visiteurs) */
function admin_connecte(): bool
{
    if (!isset($_COOKIE['mts_admin'])) {
        return false;
    }
    demarrer_session();
    $connecte = !empty($_SESSION['user_id']);
    session_write_close();
    return $connecte;
}

/* ---------------------------------------------------------------
 * Contenus modifiables (réglages)
 * ------------------------------------------------------------- */

/**
 * Liste des textes modifiables depuis le dashboard, regroupés par page.
 * La valeur « defaut » sert à l'installation et si le réglage est vide.
 */
function setting_definitions(): array
{
    return [
        'general' => ['label' => 'Général', 'champs' => [
            'site_nom'         => ['label' => 'Nom affiché dans les onglets', 'type' => 'text', 'defaut' => 'Hugo Martins'],
            'site_description' => ['label' => 'Description pour Google (accueil)', 'type' => 'textarea', 'defaut' => 'Hugo Martins, photographe nature et paysage : montagnes, forêts et faune sauvage. Portfolio, prestations et tirages.'],
        ]],
        'accueil' => ['label' => 'Accueil', 'champs' => [
            'hero_surtitre'  => ['label' => 'Surtitre', 'type' => 'text', 'defaut' => 'Photographie 2026'],
            'hero_prenom'    => ['label' => 'Prénom (en noir)', 'type' => 'text', 'defaut' => 'Hugo'],
            'hero_nom'       => ['label' => 'Nom (en gris)', 'type' => 'text', 'defaut' => 'Martins'],
            'hero_soustitre' => ['label' => 'Sous-titre', 'type' => 'text', 'defaut' => 'Photographe nature & paysage'],
            'hero_bouton'    => ['label' => 'Texte du bouton', 'type' => 'text', 'defaut' => 'Découvrir mon portfolio'],
        ]],
        'portfolio' => ['label' => 'Portfolio', 'champs' => [
            'portfolio_surtitre' => ['label' => 'Surtitre', 'type' => 'text', 'defaut' => 'GALERIE'],
            'portfolio_titre'    => ['label' => 'Titre', 'type' => 'text', 'defaut' => 'Portfolio Photo'],
            'portfolio_intro'    => ['label' => 'Introduction', 'type' => 'textarea', 'defaut' => "Montagnes, forêts et faune sauvage. Cliquez sur une photo pour l'afficher en grand."],
        ]],
        'services' => ['label' => 'Services', 'champs' => [
            'services_surtitre' => ['label' => 'Surtitre', 'type' => 'text', 'defaut' => 'CE QUE JE PROPOSE'],
            'services_titre'    => ['label' => 'Titre', 'type' => 'text', 'defaut' => 'Services'],
        ]],
        'apropos' => ['label' => 'À propos', 'champs' => [
            'about_surtitre'  => ['label' => 'Surtitre', 'type' => 'text', 'defaut' => 'QUI SUIS-JE'],
            'about_titre'     => ['label' => 'Titre', 'type' => 'text', 'defaut' => 'À propos'],
            'about_photo'     => ['label' => 'Photo', 'type' => 'photo', 'defaut' => ''],
            'about_soustitre' => ['label' => 'Sous-titre', 'type' => 'text', 'defaut' => 'La photo au coeur de la nature'],
            'about_texte'     => ['label' => 'Texte (laisser une ligne vide entre deux paragraphes)', 'type' => 'textarea', 'defaut' => "Photographe amateur passionné, je parcours les forêts, les montagnes et les sentiers pour saisir la lumière et les détails que l'on ne prend pas toujours le temps de regarder.\n\nAu fil des sorties, je me suis aussi tourné vers le portrait, avec la même envie : des images simples, naturelles et sincères."],
            'about_points'    => ['label' => 'Points forts (un par ligne)', 'type' => 'textarea', 'defaut' => "Nature et paysages\nPortraits en lumière naturelle\nÉvénements"],
        ]],
        'contact' => ['label' => 'Contact', 'champs' => [
            'contact_surtitre' => ['label' => 'Surtitre', 'type' => 'text', 'defaut' => 'UN PROJET ?'],
            'contact_titre'    => ['label' => 'Titre', 'type' => 'text', 'defaut' => 'Contact'],
            'contact_texte'    => ['label' => 'Texte', 'type' => 'textarea', 'defaut' => 'Une question, une demande de séance ou de tirage ? Écrivez-moi par e-mail, je vous réponds rapidement.'],
            'contact_email'    => ['label' => 'Adresse e-mail', 'type' => 'email', 'defaut' => 'contact@mtsphotographie.fr'],
        ]],
    ];
}

/** Tous les réglages : valeurs en base, complétées par les valeurs par défaut */
function settings(bool $recharger = false): array
{
    static $cache = null;
    if ($cache === null || $recharger) {
        $cache = [];
        foreach (setting_definitions() as $groupe) {
            foreach ($groupe['champs'] as $cle => $champ) {
                $cache[$cle] = $champ['defaut'];
            }
        }
        foreach (db()->query('SELECT name, value FROM settings') as $ligne) {
            if ($ligne['value'] !== '' || !isset($cache[$ligne['name']])) {
                $cache[$ligne['name']] = $ligne['value'];
            }
        }
    }
    return $cache;
}

function setting(string $cle): string
{
    return (string) (settings()[$cle] ?? '');
}

function save_setting(string $cle, string $valeur): void
{
    db()->prepare('INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)')
        ->execute([$cle, $valeur]);
}

/** Découpe un texte en paragraphes (séparés par une ligne vide) */
function paragraphes(string $texte): array
{
    $blocs = preg_split('/\R\s*\R/u', trim($texte)) ?: [];
    return array_values(array_filter(array_map('trim', $blocs), fn ($b) => $b !== ''));
}

/** Découpe un texte en lignes non vides */
function lignes(string $texte): array
{
    return array_values(array_filter(array_map('trim', preg_split('/\R/u', $texte) ?: []), fn ($l) => $l !== ''));
}

/* ---------------------------------------------------------------
 * Photos, catégories, services
 * ------------------------------------------------------------- */

function categories(): array
{
    return db()->query('SELECT * FROM categories ORDER BY position, name')->fetchAll();
}

/**
 * Photos triées par ordre d'affichage.
 * $filtre : 'visibles', 'diaporama', 'bandeau' ou 'toutes'
 */
function photos(string $filtre = 'visibles'): array
{
    $conditions = [
        'toutes'    => '1 = 1',
        'visibles'  => 'p.visible = 1',
        'diaporama' => 'p.visible = 1 AND p.in_slideshow = 1',
        'bandeau'   => 'p.visible = 1 AND p.in_band = 1',
    ];
    $sql = 'SELECT p.*, c.slug AS category_slug, c.name AS category_name
            FROM photos p LEFT JOIN categories c ON c.id = p.category_id
            WHERE ' . ($conditions[$filtre] ?? $conditions['visibles']) . '
            ORDER BY p.position, p.id';
    return db()->query($sql)->fetchAll();
}

function photo(int $id): ?array
{
    $requete = db()->prepare('SELECT * FROM photos WHERE id = ?');
    $requete->execute([$id]);
    return $requete->fetch() ?: null;
}

/**
 * Balise <img> d'une photo. $miniature : utiliser la version réduite.
 * width/height gardent les proportions réelles pour éviter que la page « saute » au chargement.
 */
function img_photo(array $photo, bool $miniature = false, string $attributs = '', string $prefixe = ''): string
{
    $source = $miniature ? $photo['thumb'] : $photo['file'];
    return sprintf(
        '<img width="%d" height="%d" src="%s" alt="%s"%s>',
        $photo['width'],
        $photo['height'],
        e($prefixe . $source),
        e($photo['title']),
        $attributs !== '' ? ' ' . $attributs : ''
    );
}

function services(): array
{
    return db()->query('SELECT * FROM services ORDER BY position, id')->fetchAll();
}

/** Icônes Font Awesome proposées pour les services */
function service_icons(): array
{
    return [
        'champagne-glasses' => 'Verres (événement)',
        'user'              => 'Personne',
        'tree'              => 'Arbre',
        'mountain-sun'      => 'Montagne',
        'leaf'              => 'Feuille',
        'paw'               => 'Empreinte (faune)',
        'image'             => 'Image',
        'camera'            => 'Appareil photo',
        'print'             => 'Impression',
        'gift'              => 'Cadeau',
        'heart'             => 'Cœur',
        'calendar'          => 'Calendrier',
        'star'              => 'Étoile',
    ];
}

/** Transforme un texte en identifiant d'URL : « Forêts & lacs » → « forets-lacs » */
function slugify(string $texte): string
{
    // Retire les accents sans dépendre de l'extension intl (pas toujours présente sur les hébergements)
    $texte = strtr(mb_strtolower($texte), [
        'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ã' => 'a', 'å' => 'a', 'æ' => 'ae',
        'ç' => 'c', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'î' => 'i', 'ï' => 'i', 'í' => 'i', 'ì' => 'i',
        'ñ' => 'n', 'ô' => 'o', 'ö' => 'o', 'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'œ' => 'oe', 'ø' => 'o',
        'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ú' => 'u', 'ÿ' => 'y', 'ý' => 'y',
    ]);
    $texte = preg_replace('/[^a-z0-9]+/', '-', $texte);
    return trim((string) $texte, '-') ?: 'categorie';
}

function format_octets(float $octets): string
{
    $unites = ['o', 'Ko', 'Mo', 'Go'];
    $i = 0;
    while ($octets >= 1024 && $i < count($unites) - 1) {
        $octets /= 1024;
        $i++;
    }
    return number_format($octets, $i === 0 ? 0 : 1, ',', ' ') . ' ' . $unites[$i];
}

/* ---------------------------------------------------------------
 * Traitement des images envoyées
 * ------------------------------------------------------------- */

const PHOTO_TAILLE_MAX = 2000;   // plus grand côté de la photo affichée, en pixels
const MINIATURE_TAILLE_MAX = 800;

function message_erreur_upload(int $code): string
{
    return match ($code) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'fichier trop lourd (limite du serveur : ' . ini_get('upload_max_filesize') . ')',
        UPLOAD_ERR_PARTIAL                        => 'envoi interrompu, réessayez',
        UPLOAD_ERR_NO_FILE                        => 'aucun fichier reçu',
        default                                   => 'erreur du serveur (code ' . $code . ')',
    };
}

/** Redimensionne une image pour que son plus grand côté ne dépasse pas $max */
function redimensionner(GdImage $source, int $max): GdImage
{
    $l = imagesx($source);
    $h = imagesy($source);
    $ratio = min(1, $max / max($l, $h));
    $nl = max(1, (int) round($l * $ratio));
    $nh = max(1, (int) round($h * $ratio));

    $cible = imagecreatetruecolor($nl, $nh);
    imagealphablending($cible, false);
    imagesavealpha($cible, true);
    imagecopyresampled($cible, $source, 0, 0, 0, 0, $nl, $nh, $l, $h);
    return $cible;
}

/** Remet droite une photo JPEG prise en mode portrait (orientation EXIF) */
function corriger_orientation(GdImage $image, string $chemin): GdImage
{
    if (!function_exists('exif_read_data')) {
        return $image;
    }
    $exif = @exif_read_data($chemin);
    $orientation = (int) ($exif['Orientation'] ?? 1);
    if (in_array($orientation, [2, 4, 5, 7], true)) {
        imageflip($image, IMG_FLIP_HORIZONTAL);
    }
    $angle = match ($orientation) {
        3, 4 => 180,
        5, 6 => -90,
        7, 8 => 90,
        default => 0,
    };
    if ($angle !== 0) {
        $tournee = imagerotate($image, $angle, 0);
        if ($tournee !== false) {
            return $tournee;
        }
    }
    return $image;
}

/**
 * Valide une image envoyée, la réencode en WebP (ce qui supprime tout contenu caché)
 * et crée sa miniature. Retourne les chemins et dimensions, ou lève une exception.
 */
function traiter_photo(array $fichier): array
{
    if (($fichier['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException(message_erreur_upload((int) ($fichier['error'] ?? UPLOAD_ERR_NO_FILE)));
    }
    if (!is_uploaded_file($fichier['tmp_name']) && PHP_SAPI !== 'cli') {
        throw new RuntimeException('fichier invalide');
    }
    $infos = @getimagesize($fichier['tmp_name']);
    if ($infos === false) {
        throw new RuntimeException("ce n'est pas une image");
    }
    [$largeur, $hauteur, $type] = $infos;
    if ($largeur * $hauteur > 60_000_000) {
        throw new RuntimeException('image trop grande (plus de 60 mégapixels)');
    }

    @ini_set('memory_limit', '512M');
    @set_time_limit(120);

    $image = match ($type) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($fichier['tmp_name']),
        IMAGETYPE_PNG  => @imagecreatefrompng($fichier['tmp_name']),
        IMAGETYPE_WEBP => @imagecreatefromwebp($fichier['tmp_name']),
        default        => throw new RuntimeException('format non pris en charge (JPEG, PNG ou WebP uniquement)'),
    };
    if ($image === false) {
        throw new RuntimeException('image illisible ou corrompue');
    }
    if ($type === IMAGETYPE_JPEG) {
        $image = corriger_orientation($image, $fichier['tmp_name']);
    }
    if (!imageistruecolor($image)) {
        imagepalettetotruecolor($image);
    }

    $dossier = APP_ROOT . '/uploads/photos';
    if (!is_dir($dossier) && !mkdir($dossier, 0755, true)) {
        throw new RuntimeException("impossible de créer le dossier uploads/photos");
    }

    $nom = date('Ymd') . '-' . bin2hex(random_bytes(6));
    $grande = redimensionner($image, PHOTO_TAILLE_MAX);
    $miniature = redimensionner($image, MINIATURE_TAILLE_MAX);

    if (!imagewebp($grande, "$dossier/$nom.webp", 82) || !imagewebp($miniature, "$dossier/$nom-mini.webp", 78)) {
        @unlink("$dossier/$nom.webp");
        throw new RuntimeException("échec de l'enregistrement (le serveur gère-t-il le WebP ?)");
    }

    return [
        'file'   => "uploads/photos/$nom.webp",
        'thumb'  => "uploads/photos/$nom-mini.webp",
        'width'  => imagesx($grande),
        'height' => imagesy($grande),
    ];
}

/** Titre par défaut à partir du nom de fichier : « coucher_de-soleil.jpg » → « Coucher de soleil » */
function titre_depuis_fichier(string $nomFichier): string
{
    $titre = trim(preg_replace('/[_\-]+/', ' ', pathinfo($nomFichier, PATHINFO_FILENAME)) ?? '');
    return mb_substr(mb_strtoupper(mb_substr($titre, 0, 1)) . mb_substr($titre, 1), 0, 120);
}

/** Supprime les fichiers d'une photo (uniquement ceux envoyés via le dashboard) */
function supprimer_fichiers_photo(array $photo): void
{
    foreach ([$photo['file'], $photo['thumb']] as $chemin) {
        if (str_starts_with($chemin, 'uploads/photos/') && !str_contains($chemin, '..')) {
            @unlink(APP_ROOT . '/' . $chemin);
        }
    }
}

/* ---------------------------------------------------------------
 * Sessions, connexion et sécurité
 * ------------------------------------------------------------- */

const SESSION_DUREE_INACTIVITE = 4 * 3600; // déconnexion après 4 h sans activité

function est_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

function demarrer_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name('mts_admin');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => est_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    if (isset($_SESSION['user_id'], $_SESSION['activite']) && time() - $_SESSION['activite'] > SESSION_DUREE_INACTIVITE) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['activite'] = time();
}

function utilisateur(): ?array
{
    static $utilisateur = false;
    if ($utilisateur === false) {
        $utilisateur = null;
        if (!empty($_SESSION['user_id'])) {
            $requete = db()->prepare('SELECT id, username, created_at, last_login FROM users WHERE id = ?');
            $requete->execute([$_SESSION['user_id']]);
            $utilisateur = $requete->fetch() ?: null;
        }
    }
    return $utilisateur;
}

function exiger_connexion(): array
{
    demarrer_session();
    $utilisateur = utilisateur();
    if ($utilisateur === null) {
        if (est_requete_json()) {
            json_reponse(['ok' => false, 'erreur' => 'Session expirée, reconnectez-vous.'], 401);
        }
        header('Location: login.php');
        exit;
    }
    return $utilisateur;
}

function jeton_csrf(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function champ_csrf(): string
{
    return '<input type="hidden" name="csrf" value="' . e(jeton_csrf()) . '">';
}

/** Bloque toute requête POST qui ne contient pas le bon jeton (protection CSRF) */
function verifier_csrf(): void
{
    $jeton = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF'] ?? '';
    if (!is_string($jeton) || !hash_equals(jeton_csrf(), $jeton)) {
        if (est_requete_json()) {
            json_reponse(['ok' => false, 'erreur' => 'Jeton de sécurité invalide, rechargez la page.'], 403);
        }
        http_response_code(403);
        exit('Jeton de sécurité invalide. Rechargez la page et réessayez.');
    }
}

function est_requete_json(): bool
{
    return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
        || str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json');
}

function json_reponse(array $donnees, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($donnees, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Corps JSON d'une requête fetch() */
function json_entree(): array
{
    $donnees = json_decode((string) file_get_contents('php://input'), true);
    return is_array($donnees) ? $donnees : [];
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flashs(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

function rediriger(string $url): never
{
    header('Location: ' . $url);
    exit;
}

/** Empreinte de l'adresse IP (jamais stockée en clair) */
function empreinte_ip(): string
{
    return hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . config('secret', ''));
}

const CONNEXION_ESSAIS_MAX = 5;
const CONNEXION_BLOCAGE_MINUTES = 15;

function connexion_bloquee(): bool
{
    $requete = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip_hash = ? AND attempted_at > ?');
    $requete->execute([empreinte_ip(), now('-' . CONNEXION_BLOCAGE_MINUTES . ' minutes')]);
    return (int) $requete->fetchColumn() >= CONNEXION_ESSAIS_MAX;
}

function noter_echec_connexion(): void
{
    db()->prepare('INSERT INTO login_attempts (ip_hash, attempted_at) VALUES (?, ?)')->execute([empreinte_ip(), now()]);
    db()->prepare('DELETE FROM login_attempts WHERE attempted_at < ?')->execute([now('-1 day')]);
}

/** Règles de mot de passe : 10 caractères minimum */
function erreur_mot_de_passe(string $motDePasse, string $confirmation): ?string
{
    if (mb_strlen($motDePasse) < 10) {
        return 'Le mot de passe doit faire au moins 10 caractères.';
    }
    if ($motDePasse !== $confirmation) {
        return 'Les deux mots de passe ne correspondent pas.';
    }
    return null;
}

function erreur_identifiant(string $identifiant): ?string
{
    if (!preg_match('/^[a-z0-9._-]{3,30}$/', $identifiant)) {
        return "L'identifiant doit faire 3 à 30 caractères : lettres minuscules, chiffres, point, tiret ou underscore.";
    }
    return null;
}
