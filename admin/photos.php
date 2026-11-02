<?php
declare(strict_types=1);

require __DIR__ . '/_init.php';
$utilisateur = exiger_connexion();

$action = $_GET['action'] ?? '';

/* ---------- Envoi d'une photo (une requête par photo, appelée par admin.js) ---------- */
if ($action === 'envoyer' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_FILES['photo']) && empty($_POST)) {
        // Le serveur a ignoré l'envoi car il dépasse post_max_size : PHP reçoit une requête vide
        json_reponse(['ok' => false, 'erreur' => 'fichier trop lourd (limite du serveur : ' . ini_get('post_max_size') . ')'], 413);
    }
    verifier_csrf();
    if (empty($_FILES['photo'])) {
        json_reponse(['ok' => false, 'erreur' => 'aucun fichier reçu'], 400);
    }
    try {
        $infos = traiter_photo($_FILES['photo']);
    } catch (RuntimeException $e) {
        json_reponse(['ok' => false, 'erreur' => $e->getMessage()], 422);
    }

    $categorie = (int) ($_POST['category_id'] ?? 0);
    $existe = db()->prepare('SELECT id FROM categories WHERE id = ?');
    $existe->execute([$categorie]);

    // Les nouvelles photos arrivent en tête du portfolio
    db()->exec('UPDATE photos SET position = position + 1');
    db()->prepare('INSERT INTO photos (file, thumb, width, height, title, category_id, position, visible, in_slideshow, in_band, created_at)
                   VALUES (?, ?, ?, ?, ?, ?, 0, ?, 0, ?, ?)')
        ->execute([
            $infos['file'], $infos['thumb'], $infos['width'], $infos['height'],
            titre_depuis_fichier((string) $_FILES['photo']['name']),
            $existe->fetchColumn() ?: null,
            !empty($_POST['visible']) ? 1 : 0,
            !empty($_POST['in_band']) ? 1 : 0,
            now(),
        ]);
    json_reponse(['ok' => true, 'id' => (int) db()->lastInsertId()]);
}

/* ---------- Modifications instantanées (JSON) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifier_csrf();
    $donnees = json_entree();

    switch ($action) {
        case 'modifier':
            $id = (int) ($donnees['id'] ?? 0);
            $champ = (string) ($donnees['champ'] ?? '');
            $valeur = $donnees['valeur'] ?? null;
            $photo = photo($id) ?? json_reponse(['ok' => false, 'erreur' => 'Photo introuvable.'], 404);

            if ($champ === 'title') {
                $valeur = mb_substr(trim((string) $valeur), 0, 120);
            } elseif ($champ === 'category_id') {
                $valeur = (int) $valeur ?: null;
                if ($valeur !== null) {
                    $existe = db()->prepare('SELECT id FROM categories WHERE id = ?');
                    $existe->execute([$valeur]);
                    $valeur = $existe->fetchColumn() ? $valeur : null;
                }
            } elseif (in_array($champ, ['visible', 'in_slideshow', 'in_band'], true)) {
                $valeur = $valeur ? 1 : 0;
            } else {
                json_reponse(['ok' => false, 'erreur' => 'Champ inconnu.'], 400);
            }
            // $champ est vérifié ci-dessus : il ne peut contenir qu'un nom de colonne autorisé
            db()->prepare("UPDATE photos SET $champ = ? WHERE id = ?")->execute([$valeur, $photo['id']]);
            json_reponse(['ok' => true]);

        case 'ordre':
            $ids = array_values(array_unique(array_map('intval', (array) ($donnees['ids'] ?? []))));
            $requete = db()->prepare('UPDATE photos SET position = ? WHERE id = ?');
            db()->beginTransaction();
            foreach ($ids as $position => $id) {
                $requete->execute([$position, $id]);
            }
            db()->commit();
            json_reponse(['ok' => true]);

        case 'supprimer':
            $photo = photo((int) ($donnees['id'] ?? 0)) ?? json_reponse(['ok' => false, 'erreur' => 'Photo introuvable.'], 404);
            db()->prepare('DELETE FROM photos WHERE id = ?')->execute([$photo['id']]);
            supprimer_fichiers_photo($photo);
            if (setting('about_photo') === (string) $photo['id']) {
                save_setting('about_photo', '');
            }
            json_reponse(['ok' => true]);
    }
    json_reponse(['ok' => false, 'erreur' => 'Action inconnue.'], 400);
}

/* ---------- Affichage ---------- */
$photos = photos('toutes');
$categories = categories();

$requete = db()->prepare('SELECT photo_id, COUNT(*) FROM photo_views WHERE created_at >= ? GROUP BY photo_id');
$requete->execute([now('-30 days')]);
$vues = $requete->fetchAll(PDO::FETCH_KEY_PAIR);

$section = 'photos';
$titrePage = 'Photos';
require __DIR__ . '/_haut.php';

/** Liste déroulante des catégories */
function select_categories(array $categories, ?int $selection, string $attributs): string
{
    $html = '<select ' . $attributs . '><option value="">Sans catégorie</option>';
    foreach ($categories as $categorie) {
        $html .= sprintf('<option value="%d"%s>%s</option>', $categorie['id'], (int) $categorie['id'] === $selection ? ' selected' : '', e($categorie['name']));
    }
    return $html . '</select>';
}
?>
        <div class="admin-entete">
            <div>
                <p class="surtitre"><?= count($photos) ?> photo<?= count($photos) > 1 ? 's' : '' ?></p>
                <h1>Photos</h1>
            </div>
        </div>

        <section class="carte formulaire" id="envoi" data-url="photos.php?action=envoyer">
            <label class="depot" for="fichiers">
                <i class="fa-solid fa-cloud-arrow-up"></i>
                <strong>Glissez vos photos ici ou cliquez pour les choisir</strong>
                <span class="aide">JPEG, PNG ou WebP · converties automatiquement en WebP optimisé · <?= e(ini_get('upload_max_filesize')) ?> max par photo</span>
                <input type="file" id="fichiers" accept="image/jpeg,image/png,image/webp" multiple>
            </label>
            <div class="apercu-envoi"></div>
            <div class="ligne-champs">
                <label>Catégorie des nouvelles photos
                    <?= select_categories($categories, null, 'name="category_id"') ?>
                </label>
            </div>
            <div class="actions">
                <label class="interrupteur"><input type="checkbox" name="visible" checked> Visible sur le site</label>
                <label class="interrupteur"><input type="checkbox" name="in_band" checked> Dans le bandeau de l'accueil</label>
            </div>
            <div class="actions">
                <button type="button" class="bouton" id="envoyer" disabled><i class="fa-solid fa-upload"></i> Envoyer</button>
                <span class="aide" id="progression"></span>
            </div>
        </section>

        <section class="section">
            <h2>Ordre d'affichage</h2>
            <p class="aide" style="margin-bottom:16px">Glissez-déposez les photos (ou utilisez les flèches) pour changer l'ordre du portfolio. Les modifications sont enregistrées automatiquement.</p>
<?php if (!$photos): ?>
            <p class="carte vide">Aucune photo pour l'instant.</p>
<?php endif; ?>
            <div class="photos-grille" id="grille" data-url="photos.php">
<?php foreach ($photos as $i => $photo): ?>
                <article class="photo-carte<?= $photo['visible'] ? '' : ' masquee' ?>" data-id="<?= (int) $photo['id'] ?>" draggable="true">
                    <div class="vignette">
                        <img src="../<?= e($photo['thumb']) ?>" alt="" loading="lazy" draggable="false">
                        <span class="numero"><?= $i + 1 ?></span>
                        <span class="vues" title="Ouvertures dans la visionneuse sur 30 jours"><i class="fa-regular fa-eye"></i> <?= (int) ($vues[$photo['id']] ?? 0) ?></span>
                    </div>
                    <div class="corps">
                        <label>Titre
                            <input type="text" value="<?= e($photo['title']) ?>" maxlength="120" data-champ="title">
                        </label>
                        <label>Catégorie
                            <?= select_categories($categories, $photo['category_id'] !== null ? (int) $photo['category_id'] : null, 'data-champ="category_id"') ?>
                        </label>
                        <div class="interrupteurs">
                            <label class="interrupteur"><input type="checkbox" data-champ="visible"<?= $photo['visible'] ? ' checked' : '' ?>> Visible</label>
                            <label class="interrupteur"><input type="checkbox" data-champ="in_slideshow"<?= $photo['in_slideshow'] ? ' checked' : '' ?>> Diaporama</label>
                            <label class="interrupteur"><input type="checkbox" data-champ="in_band"<?= $photo['in_band'] ? ' checked' : '' ?>> Bandeau</label>
                        </div>
                        <div class="bas">
                            <div class="ordre">
                                <button type="button" class="bouton bouton-secondaire bouton-icone" data-deplacer="-1" title="Avancer"><i class="fa-solid fa-arrow-left"></i></button>
                                <button type="button" class="bouton bouton-secondaire bouton-icone" data-deplacer="1" title="Reculer"><i class="fa-solid fa-arrow-right"></i></button>
                            </div>
                            <button type="button" class="bouton bouton-danger" data-supprimer><i class="fa-regular fa-trash-can"></i> Supprimer</button>
                        </div>
                    </div>
                </article>
<?php endforeach; ?>
            </div>
        </section>
<?php require __DIR__ . '/_bas.php';
