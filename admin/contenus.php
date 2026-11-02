<?php
declare(strict_types=1);

require __DIR__ . '/_init.php';
$utilisateur = exiger_connexion();

$definitions = setting_definitions();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifier_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'textes') {
        $groupe = (string) ($_POST['groupe'] ?? '');
        if (isset($definitions[$groupe])) {
            foreach ($definitions[$groupe]['champs'] as $cle => $champ) {
                $valeur = trim(str_replace("\r\n", "\n", (string) ($_POST[$cle] ?? '')));
                if ($champ['type'] === 'email' && $valeur !== '' && !filter_var($valeur, FILTER_VALIDATE_EMAIL)) {
                    flash('erreur', "« $valeur » n'est pas une adresse e-mail valide.");
                    rediriger('contenus.php#' . $groupe);
                }
                if ($champ['type'] === 'photo') {
                    $valeur = (string) ((int) $valeur ?: '');
                }
                save_setting($cle, mb_substr($valeur, 0, 5000));
            }
            flash('succes', 'Textes « ' . $definitions[$groupe]['label'] . ' » enregistrés.');
        }
        rediriger('contenus.php#' . $groupe);
    }

    // Services
    $id = (int) ($_POST['id'] ?? 0);
    $icones = service_icons();
    $service = [
        'icon'      => isset($icones[$_POST['icon'] ?? '']) ? $_POST['icon'] : 'camera',
        'title'     => mb_substr(trim((string) ($_POST['title'] ?? '')), 0, 80),
        'text'      => mb_substr(trim(str_replace("\r\n", "\n", (string) ($_POST['text'] ?? ''))), 0, 1000),
        'link_text' => mb_substr(trim((string) ($_POST['link_text'] ?? '')), 0, 60),
    ];

    if (in_array($action, ['service_ajouter', 'service_modifier'], true) && $service['title'] === '') {
        flash('erreur', 'Le titre du service est obligatoire.');
    } elseif ($action === 'service_ajouter') {
        $position = (int) db()->query('SELECT COALESCE(MAX(position), -1) + 1 FROM services')->fetchColumn();
        db()->prepare('INSERT INTO services (icon, title, text, link_text, position) VALUES (?, ?, ?, ?, ?)')
            ->execute([$service['icon'], $service['title'], $service['text'], $service['link_text'], $position]);
        flash('succes', 'Service « ' . $service['title'] . ' » ajouté.');
    } elseif ($action === 'service_modifier') {
        db()->prepare('UPDATE services SET icon = ?, title = ?, text = ?, link_text = ? WHERE id = ?')
            ->execute([$service['icon'], $service['title'], $service['text'], $service['link_text'], $id]);
        flash('succes', 'Service « ' . $service['title'] . ' » enregistré.');
    } elseif ($action === 'service_monter' || $action === 'service_descendre') {
        $ids = array_map('intval', array_column(services(), 'id'));
        $index = array_search($id, $ids, true);
        $voisin = $action === 'service_monter' ? $index - 1 : $index + 1;
        if ($index !== false && isset($ids[$voisin])) {
            [$ids[$index], $ids[$voisin]] = [$ids[$voisin], $ids[$index]];
            $requete = db()->prepare('UPDATE services SET position = ? WHERE id = ?');
            foreach ($ids as $position => $idService) {
                $requete->execute([$position, $idService]);
            }
        }
    } elseif ($action === 'service_supprimer') {
        db()->prepare('DELETE FROM services WHERE id = ?')->execute([$id]);
        flash('succes', 'Service supprimé.');
    }
    rediriger('contenus.php#services-liste');
}

$reglages = settings();
$services = services();
$photos = photos('toutes');

/** Champs communs d'un service (formulaire d'ajout et de modification) */
function champs_service(array $service): string
{
    $options = '';
    foreach (service_icons() as $icone => $libelle) {
        $options .= sprintf('<option value="%s"%s>%s</option>', e($icone), $icone === ($service['icon'] ?? '') ? ' selected' : '', e($libelle));
    }
    return '<div class="ligne-champs">'
        . '<label>Titre<input type="text" name="title" value="' . e($service['title'] ?? '') . '" maxlength="80" required></label>'
        . '<label>Icône<select name="icon">' . $options . '</select></label>'
        . '<label>Texte du lien (vide = pas de lien)<input type="text" name="link_text" value="' . e($service['link_text'] ?? 'SUR DEVIS →') . '" maxlength="60"></label>'
        . '</div>'
        . '<label>Description<textarea name="text" maxlength="1000">' . e($service['text'] ?? '') . '</textarea></label>';
}

$section = 'contenus';
$titrePage = 'Contenus';
require __DIR__ . '/_haut.php';
?>
        <div class="admin-entete">
            <div>
                <p class="surtitre">Textes du site</p>
                <h1>Contenus</h1>
            </div>
            <nav class="actions" aria-label="Aller à une section">
<?php foreach ($definitions as $cle => $groupe): ?>
                <a class="bouton bouton-secondaire" href="#<?= e($cle) ?>"><?= e($groupe['label']) ?></a>
<?php endforeach; ?>
                <a class="bouton bouton-secondaire" href="#services-liste">Cartes de services</a>
            </nav>
        </div>

<?php foreach ($definitions as $cle => $groupe): ?>
        <section class="section" id="<?= e($cle) ?>">
            <h2><?= e($groupe['label']) ?></h2>
            <form method="post" class="carte formulaire">
                <?= champ_csrf() ?>
                <input type="hidden" name="action" value="textes">
                <input type="hidden" name="groupe" value="<?= e($cle) ?>">
<?php foreach ($groupe['champs'] as $nom => $champ): ?>
                <label><?= e($champ['label']) ?>
<?php if ($champ['type'] === 'textarea'): ?>
                    <textarea name="<?= e($nom) ?>"><?= e($reglages[$nom]) ?></textarea>
<?php elseif ($champ['type'] === 'photo'): ?>
                    <select name="<?= e($nom) ?>">
                        <option value="">Aucune photo</option>
<?php foreach ($photos as $photo): ?>
                        <option value="<?= (int) $photo['id'] ?>"<?= (string) $photo['id'] === $reglages[$nom] ? ' selected' : '' ?>><?= e($photo['title'] ?: 'Photo n°' . $photo['id']) ?></option>
<?php endforeach; ?>
                    </select>
<?php else: ?>
                    <input type="<?= $champ['type'] === 'email' ? 'email' : 'text' ?>" name="<?= e($nom) ?>" value="<?= e($reglages[$nom]) ?>">
<?php endif; ?>
                </label>
<?php endforeach; ?>
                <div class="actions">
                    <button type="submit" class="bouton">Enregistrer</button>
                </div>
            </form>
        </section>
<?php endforeach; ?>

        <section class="section" id="services-liste">
            <h2>Cartes de services</h2>
            <div class="liste-ordonnee">
<?php foreach ($services as $i => $service): ?>
                <form method="post" class="carte formulaire">
                    <?= champ_csrf() ?>
                    <input type="hidden" name="id" value="<?= (int) $service['id'] ?>">
                    <?= champs_service($service) ?>
                    <div class="actions">
                        <button class="bouton" name="action" value="service_modifier">Enregistrer</button>
                        <div class="ordre">
                            <button class="bouton bouton-secondaire bouton-icone" name="action" value="service_monter" title="Monter" formnovalidate<?= $i === 0 ? ' disabled' : '' ?>><i class="fa-solid fa-arrow-up"></i></button>
                            <button class="bouton bouton-secondaire bouton-icone" name="action" value="service_descendre" title="Descendre" formnovalidate<?= $i === count($services) - 1 ? ' disabled' : '' ?>><i class="fa-solid fa-arrow-down"></i></button>
                        </div>
                        <button class="bouton bouton-danger" name="action" value="service_supprimer" formnovalidate data-confirmer><i class="fa-regular fa-trash-can"></i> Supprimer</button>
                    </div>
                </form>
<?php endforeach; ?>
            </div>

            <h2 style="margin:32px 0 16px">Nouveau service</h2>
            <form method="post" class="carte formulaire">
                <?= champ_csrf() ?>
                <?= champs_service(['icon' => 'camera']) ?>
                <div class="actions">
                    <button class="bouton" name="action" value="service_ajouter"><i class="fa-solid fa-plus"></i> Ajouter</button>
                </div>
            </form>
        </section>
<?php require __DIR__ . '/_bas.php';
