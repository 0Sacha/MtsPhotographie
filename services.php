<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

try {
    $services = services();
    $titre = 'Services – ' . setting('site_nom');
} catch (Throwable $e) {
    maintenance($e);
}

$page = 'services';
require __DIR__ . '/includes/partials/haut.php';
?>
        <section>
            <div class="mainContent">
                <div class="portfolio">
                    <p class="surtitre"><?= e(setting('services_surtitre')) ?></p>
                    <h1><?= e(setting('services_titre')) ?></h1>
                </div>
                <div class="cards">
<?php foreach ($services as $service): ?>
                    <div class="card">
                        <i class="fa-solid fa-<?= e($service['icon']) ?>"></i>
                        <h2><?= e($service['title']) ?></h2>
                        <p><?= nl2br(e($service['text'])) ?></p>
<?php if ($service['link_text'] !== ''): ?>
                        <a class="tarif" href="contact.php"><?= e($service['link_text']) ?></a>
<?php endif; ?>
                    </div>
<?php endforeach; ?>
                </div>
            </div>
<?php require __DIR__ . '/includes/partials/bas.php';
