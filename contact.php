<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

try {
    $email = setting('contact_email');
    $titre = setting('contact_titre') . ' – ' . setting('site_nom');
} catch (Throwable $e) {
    maintenance($e);
}

$page = 'contact';
require __DIR__ . '/includes/partials/haut.php';
?>
        <section>
            <div class="mainContent">
                <div class="portfolio">
                    <p class="surtitre"><?= e(setting('contact_surtitre')) ?></p>
                    <h1><?= e(setting('contact_titre')) ?></h1>
                </div>
                <div class="contact">
                    <div class="infos">
<?php foreach (paragraphes(setting('contact_texte')) as $paragraphe): ?>
                        <p><?= nl2br(e($paragraphe)) ?></p>
<?php endforeach; ?>
<?php if ($email !== ''): ?>
                        <a href="mailto:<?= e($email) ?>"><i class="fa-solid fa-envelope"></i><?= e($email) ?></a>
<?php endif; ?>
                    </div>
                </div>
            </div>
<?php require __DIR__ . '/includes/partials/bas.php';
