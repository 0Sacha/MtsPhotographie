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
            </nav>
        </div>
