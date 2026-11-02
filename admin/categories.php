<?php
declare(strict_types=1);

require __DIR__ . '/_init.php';
$utilisateur = exiger_connexion();

/** Identifiant d'URL unique pour une catégorie */
function slug_unique(string $nom, int $ignorerId = 0): string
{
    $base = slugify($nom);
    $slug = $base;
    $requete = db()->prepare('SELECT COUNT(*) FROM categories WHERE slug = ? AND id <> ?');
    for ($i = 2; ; $i++) {
        $requete->execute([$slug, $ignorerId]);
        if (!(int) $requete->fetchColumn()) {
            return $slug;
        }
        $slug = "$base-$i";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifier_csrf();
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);
    $nom = mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 60);

    if ($action === 'ajouter' || $action === 'modifier') {
        if ($nom === '') {
            flash('erreur', 'Le nom de la catégorie est obligatoire.');
        } elseif ($action === 'ajouter') {
            $position = (int) db()->query('SELECT COALESCE(MAX(position), -1) + 1 FROM categories')->fetchColumn();
            db()->prepare('INSERT INTO categories (slug, name, position) VALUES (?, ?, ?)')->execute([slug_unique($nom), $nom, $position]);
            flash('succes', "Catégorie « $nom » ajoutée.");
        } else {
            db()->prepare('UPDATE categories SET name = ?, slug = ? WHERE id = ?')->execute([$nom, slug_unique($nom, $id), $id]);
            flash('succes', "Catégorie « $nom » enregistrée.");
        }
    } elseif ($action === 'monter' || $action === 'descendre') {
        // Réécrit les positions 0, 1, 2… puis échange avec la voisine
        $ids = array_column(categories(), 'id');
        $index = array_search($id, array_map('intval', $ids), true);
        $voisin = $action === 'monter' ? $index - 1 : $index + 1;
        if ($index !== false && isset($ids[$voisin])) {
            [$ids[$index], $ids[$voisin]] = [$ids[$voisin], $ids[$index]];
            $requete = db()->prepare('UPDATE categories SET position = ? WHERE id = ?');
            foreach ($ids as $position => $idCategorie) {
                $requete->execute([$position, $idCategorie]);
            }
        }
    } elseif ($action === 'supprimer') {
        db()->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
        flash('succes', 'Catégorie supprimée. Ses photos sont maintenant « sans catégorie ».');
    }
    rediriger('categories.php');
}

$categories = db()->query('SELECT c.*, COUNT(p.id) AS nb FROM categories c LEFT JOIN photos p ON p.category_id = c.id
                           GROUP BY c.id ORDER BY c.position, c.name')->fetchAll();

$section = 'categories';
$titrePage = 'Catégories';
require __DIR__ . '/_haut.php';
?>
        <div class="admin-entete">
            <div>
                <p class="surtitre">Filtres du portfolio</p>
                <h1>Catégories</h1>
            </div>
        </div>
        <p class="aide" style="margin-bottom:24px">Les catégories deviennent les filtres du portfolio, dans cet ordre. Une catégorie sans photo visible n'apparaît pas sur le site.</p>

        <div class="liste-ordonnee">
<?php foreach ($categories as $i => $categorie): ?>
            <form method="post" class="carte element">
                <?= champ_csrf() ?>
                <input type="hidden" name="id" value="<?= (int) $categorie['id'] ?>">
                <label>Nom <span>(<?= (int) $categorie['nb'] ?> photo<?= $categorie['nb'] > 1 ? 's' : '' ?>)</span>
                    <input type="text" name="name" value="<?= e($categorie['name']) ?>" maxlength="60" required>
                </label>
                <div class="actions">
                    <button class="bouton" name="action" value="modifier">Enregistrer</button>
                    <div class="ordre">
                        <button class="bouton bouton-secondaire bouton-icone" name="action" value="monter" title="Monter"<?= $i === 0 ? ' disabled' : '' ?>><i class="fa-solid fa-arrow-up"></i></button>
                        <button class="bouton bouton-secondaire bouton-icone" name="action" value="descendre" title="Descendre"<?= $i === count($categories) - 1 ? ' disabled' : '' ?>><i class="fa-solid fa-arrow-down"></i></button>
                    </div>
                    <button class="bouton bouton-danger" name="action" value="supprimer" formnovalidate data-confirmer><i class="fa-regular fa-trash-can"></i> Supprimer</button>
                </div>
            </form>
<?php endforeach; ?>
        </div>

        <section class="section">
            <h2>Nouvelle catégorie</h2>
            <form method="post" class="carte element">
                <?= champ_csrf() ?>
                <label>Nom
                    <input type="text" name="name" maxlength="60" placeholder="Ex. : Lacs, Oiseaux, Hiver…" required>
                </label>
                <button class="bouton" name="action" value="ajouter"><i class="fa-solid fa-plus"></i> Ajouter</button>
            </form>
        </section>
<?php require __DIR__ . '/_bas.php';
