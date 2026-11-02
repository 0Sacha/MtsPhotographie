<?php
/*
 * Début commun des pages publiques : <head>, barre de navigation.
 * Variables attendues : $page (identifiant de la page), $titre (titre de l'onglet), $description (facultatif)
 */
$navigation = [
    'accueil'   => ['index.php', 'Accueil', 'fa-house'],
    'portfolio' => ['portfolio.php', 'Portfolio', 'fa-images'],
    'services'  => ['services.php', 'Services', 'fa-camera'],
    'apropos'   => ['about.php', 'À propos', 'fa-user'],
    'contact'   => ['contact.php', 'Contact', 'fa-envelope'],
];
$urlSite = rtrim((string) config('url', ''), '/');

// Mode maintenance : les visiteurs voient la page d'attente, les administrateurs connectés voient le site
$enMaintenance = maintenance_active();
$adminConnecte = admin_connecte();
if ($enMaintenance && !$adminConnecte) {
    page_maintenance();
}
?>
<!doctype html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <link rel="stylesheet" href="src/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.3.1/css/all.min.css">
    <title><?= e($titre) ?></title>
<?php if (!empty($description)): ?>
    <meta name="description" content="<?= e($description) ?>">
<?php endif; ?>
<?php if ($urlSite !== ''): ?>
    <link rel="canonical" href="<?= e($urlSite . '/' . ($page === 'accueil' ? '' : $navigation[$page][0])) ?>">
<?php endif; ?>
    <script>document.documentElement.classList.add('js');</script>
    <script src="src/js/main.js" defer></script>
</head>

<body data-page="<?= e($page) ?>">
<?php if ($enMaintenance): ?>
    <div role="status" style="position:fixed;left:50%;bottom:calc(16px + env(safe-area-inset-bottom));z-index:100;transform:translateX(-50%);display:flex;align-items:center;gap:12px;width:max-content;max-width:calc(100vw - 32px);box-sizing:border-box;padding:10px 12px 10px 18px;border-radius:18px;background:#B42318;color:#FEFEFE;font-size:14px;line-height:1.3;box-shadow:0 8px 24px rgba(10,10,10,.2)">
        <i class="fa-solid fa-screwdriver-wrench"></i><span>Maintenance active : les visiteurs voient la page d'attente</span>
        <a href="admin/" style="flex-shrink:0;padding:6px 12px;border-radius:9999px;background:#FEFEFE;color:#B42318;text-decoration:none">Dashboard</a>
    </div>
<?php endif; ?>
    <main>
        <div id="sidebar">
            <nav>
                <div class="top">
                    <ul>
<?php foreach ($navigation as $cle => [$lien, $libelle, $icone]): ?>
<?php if ($cle === $page): ?>
                        <li><a href="<?= e($lien) ?>" aria-label="<?= e($libelle) ?>" aria-current="page"><i class="fa-regular <?= e($icone) ?>"></i></a></li>
<?php else: ?>
                        <li><a href="<?= e($lien) ?>" aria-label="<?= e($libelle) ?>"><i class="fa-regular <?= e($icone) ?>"></i></a></li>
<?php endif; ?>
<?php endforeach; ?>
                    </ul>
                </div>
<?php if ($adminConnecte): ?>
                <a class="acces-admin connecte" href="admin/" aria-label="Dashboard" title="Dashboard"><i class="fa-regular fa-chart-bar"></i></a>
<?php else: ?>
                <a class="acces-admin" href="admin/" aria-label="Connexion à l'administration" title="Administration" rel="nofollow"><i class="fa-solid fa-lock"></i></a>
<?php endif; ?>
            </nav>
        </div>
