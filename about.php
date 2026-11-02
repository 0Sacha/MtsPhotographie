<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

try {
    $photoAPropos = setting('about_photo') !== '' ? photo((int) setting('about_photo')) : null;
    $titre = setting('about_titre') . ' – ' . setting('site_nom');
} catch (Throwable $e) {
    maintenance($e);
}

$page = 'apropos';
require __DIR__ . '/includes/partials/haut.php';
?>
        <section>
            <div class="mainContent">
                <div class="portfolio">
                    <p class="surtitre"><?= e(setting('about_surtitre')) ?></p>
                    <h1><?= e(setting('about_titre')) ?></h1>
                </div>
                <div class="about">
<?php if ($photoAPropos): ?>
                    <?= img_photo($photoAPropos) ?>

<?php endif; ?>
                    <div class="texte">
                        <h2><?= e(setting('about_soustitre')) ?></h2>
<?php foreach (paragraphes(setting('about_texte')) as $paragraphe): ?>
                        <p><?= nl2br(e($paragraphe)) ?></p>
<?php endforeach; ?>
<?php if ($points = lignes(setting('about_points'))): ?>
                        <ul>
<?php foreach ($points as $point): ?>
                            <li><i class="fa-solid fa-check"></i><?= e($point) ?></li>
<?php endforeach; ?>
                        </ul>
<?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
<?php require __DIR__ . '/includes/partials/bas.php';
