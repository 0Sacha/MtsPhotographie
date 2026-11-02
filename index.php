<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

try {
    $diaporama = photos('diaporama');
    $bandeau = photos('bandeau');
    $titre = setting('site_nom') . ' – ' . setting('hero_soustitre');
    $description = setting('site_description');
} catch (Throwable $e) {
    maintenance($e);
}

$page = 'accueil';
require __DIR__ . '/includes/partials/haut.php';
?>
        <section>
            <div class="mainContent hero">
                <div class="accroche">
                    <div class="informations">
                        <p><i class="fa-solid fa-camera"></i><?= e(setting('hero_surtitre')) ?></p>
                        <h1><?= e(setting('hero_prenom')) ?> <span><?= e(setting('hero_nom')) ?></span></h1>
                        <div class="devise">
                            <p><?= e(setting('hero_soustitre')) ?></p>
                        </div>
                    </div>
                    <a href="portfolio.php" class="discover">
                        <p><?= e(setting('hero_bouton')) ?></p>
                        <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                    </a>
                </div>
<?php if ($diaporama): ?>
                <div class="diaporama">
<?php foreach ($diaporama as $i => $photo): ?>
                    <?= img_photo($photo, false, $i === 0 ? 'class="visible"' : '') ?>

<?php endforeach; ?>
                    <div class="progression">
<?php foreach ($diaporama as $i => $photo): ?>
                        <span<?= $i === 0 ? ' class="actif"' : '' ?>></span>
<?php endforeach; ?>
                    </div>
                    <div class="legende"><span></span><p><?= e($diaporama[0]['title']) ?></p></div>
                </div>
<?php endif; ?>
            </div>
<?php if ($bandeau): ?>
            <a href="portfolio.php" class="defilement" aria-label="Voir le portfolio">
                <div class="piste">
<?php // La liste est répétée deux fois pour que le défilement boucle sans coupure
foreach ([$bandeau, $bandeau] as $liste): foreach ($liste as $photo): ?>
                    <img width="<?= (int) $photo['width'] ?>" height="<?= (int) $photo['height'] ?>" src="<?= e($photo['thumb']) ?>" alt="" loading="lazy">
<?php endforeach; endforeach; ?>
                </div>
            </a>
<?php endif; ?>
<?php require __DIR__ . '/includes/partials/bas.php';
