<?php
/*
 * En-tête commun du dashboard. Variables attendues : $section, $titrePage, $utilisateur
 */
$menu = [
    'tableau'    => ['index.php', 'Tableau de bord', 'fa-chart-line'],
    'photos'     => ['photos.php', 'Photos', 'fa-images'],
    'categories' => ['categories.php', 'Catégories', 'fa-tags'],
    'contenus'   => ['contenus.php', 'Contenus', 'fa-pen-to-square'],
    'comptes'    => ['comptes.php', 'Comptes', 'fa-users'],
];
$enMaintenance = maintenance_active();
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf" content="<?= e(jeton_csrf()) ?>">
    <title><?= e($titrePage) ?> – Dashboard</title>
    <link rel="stylesheet" href="assets/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.3.1/css/all.min.css">
    <script src="assets/admin.js" defer></script>
</head>
<body class="admin">
    <header class="admin-barre">
        <a class="admin-marque" href="index.php"><?= e(setting('site_nom')) ?><span>Dashboard</span></a>
        <nav class="admin-menu" aria-label="Menu du dashboard">
<?php foreach ($menu as $cle => [$lien, $libelle, $icone]): ?>
            <a href="<?= $lien ?>"<?= $cle === $section ? ' aria-current="page"' : '' ?> title="<?= e($libelle) ?>"><i class="fa-solid <?= $icone ?>"></i><span><?= e($libelle) ?></span></a>
<?php endforeach; ?>
        </nav>
        <div class="admin-pied">
            <p class="admin-utilisateur">Connecté : <strong><?= e($utilisateur['username']) ?></strong></p>
            <form method="post" action="maintenance.php">
                <?= champ_csrf() ?>
                <input type="hidden" name="retour" value="<?= e($menu[$section][0] ?? 'index.php') ?>">
                <input type="hidden" name="etat" value="<?= $enMaintenance ? '0' : '1' ?>">
                <button type="submit" class="bascule-maintenance<?= $enMaintenance ? ' actif' : '' ?>"
                        title="<?= $enMaintenance ? 'Remettre le site en ligne' : 'Mettre le site en maintenance' ?>"
                        aria-pressed="<?= $enMaintenance ? 'true' : 'false' ?>"<?= $enMaintenance ? '' : ' data-confirmer' ?>>
                    <i class="fa-solid fa-screwdriver-wrench"></i><span>Maintenance</span><span class="mini-interrupteur" aria-hidden="true"></span>
                </button>
            </form>
            <a href="../index.php" target="_blank" rel="noopener" title="Voir le site"><i class="fa-solid fa-arrow-up-right-from-square"></i><span>Voir le site</span></a>
            <form method="post" action="logout.php">
                <?= champ_csrf() ?>
                <button type="submit" title="Déconnexion"><i class="fa-solid fa-right-from-bracket"></i><span>Déconnexion</span></button>
            </form>
        </div>
    </header>
    <main class="admin-contenu">
<?php if ($enMaintenance): ?>
        <p class="alerte alerte-maintenance"><i class="fa-solid fa-screwdriver-wrench"></i> Le site est en maintenance : les visiteurs voient une page d'attente. Vous seuls, connectés, voyez le site normalement.</p>
<?php endif; ?>
<?php foreach (flashs() as $message): ?>
        <p class="alerte alerte-<?= e($message['type']) ?>"><?= e($message['message']) ?></p>
<?php endforeach; ?>
