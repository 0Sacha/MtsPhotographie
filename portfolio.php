<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

try {
    $photos = photos('visibles');
    // Seules les catégories qui contiennent au moins une photo visible apparaissent dans les filtres
    $slugsUtilises = array_unique(array_filter(array_column($photos, 'category_slug')));
    $filtres = array_values(array_filter(categories(), fn ($c) => in_array($c['slug'], $slugsUtilises, true)));
    $titre = 'Portfolio – ' . setting('site_nom');
} catch (Throwable $e) {
    maintenance($e);
}

$page = 'portfolio';
require __DIR__ . '/includes/partials/haut.php';
?>
        <section>
            <div class="mainContent">
                <div class="portfolio">
                    <p class="surtitre"><?= e(setting('portfolio_surtitre')) ?></p>
                    <h1><?= e(setting('portfolio_titre')) ?></h1>
<?php foreach (paragraphes(setting('portfolio_intro')) as $paragraphe): ?>
                    <p><?= nl2br(e($paragraphe)) ?></p>
<?php endforeach; ?>
                </div>
<?php if (count($filtres) > 1): ?>
                <div class="filtres">
                    <button class="actif" data-filtre="tout">Tout <span></span></button>
<?php foreach ($filtres as $categorie): ?>
                    <button data-filtre="<?= e($categorie['slug']) ?>"><?= e($categorie['name']) ?> <span></span></button>
<?php endforeach; ?>
                </div>
<?php endif; ?>
            </div>
            <div class="photo">
<?php foreach ($photos as $photo): ?>
                <figure class="apparition" data-categorie="<?= e($photo['category_slug'] ?? '') ?>" data-id="<?= (int) $photo['id'] ?>" tabindex="0">
                    <?= img_photo($photo, false, 'loading="lazy"') ?>

                    <figcaption><?php if ($photo['category_name']): ?><span><?= e($photo['category_name']) ?></span><?php endif; ?><?= e($photo['title']) ?></figcaption>
                </figure>
<?php endforeach; ?>
            </div>
            <dialog class="lightbox" aria-label="Visionneuse">
                <p class="compteur"></p>
                <figure>
                    <img src="" alt="">
                    <figcaption></figcaption>
                </figure>
                <button class="fermer" aria-label="Fermer"><i class="fa-solid fa-xmark"></i></button>
                <button class="precedent" aria-label="Photo précédente"><i class="fa-solid fa-arrow-left"></i></button>
                <button class="suivant" aria-label="Photo suivante"><i class="fa-solid fa-arrow-right"></i></button>
            </dialog>
        </section>
<?php require __DIR__ . '/includes/partials/bas.php';
