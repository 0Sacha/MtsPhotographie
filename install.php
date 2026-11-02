<?php
/*
 * Installation : crée les tables, importe le contenu actuel du site et les comptes.
 * À supprimer du serveur une fois l'installation terminée.
 */
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

demarrer_session();

$erreurs = [];
$termine = false;

try {
    $pdo = db();
} catch (Throwable $e) {
    $erreurs[] = 'Connexion à la base impossible : vérifiez includes/config.php. (' . $e->getMessage() . ')';
    $pdo = null;
}

/** L'installation est-elle déjà faite (au moins un compte existe) ? */
function deja_installe(PDO $pdo): bool
{
    try {
        return (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0;
    } catch (Throwable) {
        return false;
    }
}

$comptes = [
    ['identifiant' => 'hugo', 'mdp' => '', 'confirmation' => ''],
    ['identifiant' => 'sacha', 'mdp' => '', 'confirmation' => ''],
];

if ($pdo && deja_installe($pdo)) {
    $erreurs[] = "Le site est déjà installé. Supprimez le fichier install.php du serveur.";
} elseif ($pdo && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verifier_csrf();

    foreach ($comptes as $i => &$compte) {
        $compte['identifiant'] = strtolower(trim((string) ($_POST['identifiant'][$i] ?? '')));
        $compte['mdp'] = (string) ($_POST['mdp'][$i] ?? '');
        $compte['confirmation'] = (string) ($_POST['confirmation'][$i] ?? '');
        if ($compte['identifiant'] === '' && $i > 0) {
            continue; // le second compte est facultatif
        }
        $erreur = erreur_identifiant($compte['identifiant']) ?? erreur_mot_de_passe($compte['mdp'], $compte['confirmation']);
        if ($erreur) {
            $erreurs[] = 'Compte « ' . ($compte['identifiant'] ?: 'sans nom') . ' » : ' . $erreur;
        }
    }
    unset($compte);
    if ($comptes[0]['identifiant'] === $comptes[1]['identifiant']) {
        $erreurs[] = 'Les deux comptes doivent avoir des identifiants différents.';
    }

    if (!$erreurs) {
        try {
            // 1. Tables
            $sql = (string) file_get_contents(__DIR__ . '/sql/schema.sql');
            $sql = preg_replace('/^\s*--.*$/m', '', $sql);
            foreach (array_filter(array_map('trim', explode(';', (string) $sql))) as $instruction) {
                $pdo->exec($instruction);
            }

            $pdo->beginTransaction();

            // 2. Catégories
            $categories = ['montagne' => 'Montagnes', 'faune' => 'Faune', 'foret' => 'Forêts'];
            $idsCategories = [];
            $position = 0;
            $requete = $pdo->prepare('INSERT INTO categories (slug, name, position) VALUES (?, ?, ?)');
            foreach ($categories as $slug => $nom) {
                $requete->execute([$slug, $nom, $position++]);
                $idsCategories[$slug] = (int) $pdo->lastInsertId();
            }

            // 3. Photos actuelles du site (numéro, catégorie, titre, dans le diaporama)
            $photos = [
                [2, 'montagne', 'Sommets enneigés', true],
                [5, 'faune', 'Cerf dans la prairie', true],
                [3, 'foret', 'Torrent en pose longue', true],
                [1, 'montagne', 'Aiguilles en noir et blanc', false],
                [8, 'faune', 'Brame dans la brume', true],
                [10, 'foret', 'Lisière de forêt', true],
                [6, 'faune', 'Chocard dans la neige', false],
                [4, 'montagne', 'Crêtes au soleil', false],
                [9, 'faune', 'Sanglier en lisière', false],
                [7, 'faune', 'Chocard en vol', false],
            ];
            $requete = $pdo->prepare('INSERT INTO photos (file, thumb, width, height, title, category_id, position, visible, in_slideshow, in_band, created_at)
                                      VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, 1, ?)');
            $idPhotoAPropos = null;
            foreach ($photos as $position => [$numero, $categorie, $titre, $diaporama]) {
                $fichier = "src/img/NatureIMG/natureIMG_$numero.webp";
                $taille = @getimagesize(__DIR__ . '/' . $fichier);
                if ($taille === false) {
                    continue; // photo absente du serveur : on l'ignore
                }
                $requete->execute([$fichier, $fichier, $taille[0], $taille[1], $titre, $idsCategories[$categorie], $position, (int) $diaporama, now()]);
                if ($numero === 1) {
                    $idPhotoAPropos = (int) $pdo->lastInsertId();
                }
            }

            // 4. Services
            $services = [
                ['champagne-glasses', 'Événement', 'Anniversaire, mariage, fête de famille ou événement associatif : je capture les moments forts de votre journée.'],
                ['user', 'Portrait', 'Une séance photo en extérieur, à la lumière naturelle, pour des portraits simples et authentiques.'],
                ['tree', 'Nature & paysage', 'Des photos de paysages, de faune et de flore pour illustrer vos projets, sites ou publications.'],
                ['image', 'Tirages', 'Une photo du portfolio vous plaît ? Elle peut être imprimée dans le format de votre choix.'],
            ];
            $requete = $pdo->prepare('INSERT INTO services (icon, title, text, position) VALUES (?, ?, ?, ?)');
            foreach ($services as $position => [$icone, $titre, $texte]) {
                $requete->execute([$icone, $titre, $texte, $position]);
            }

            // 5. Textes (valeurs actuelles du site)
            foreach (setting_definitions() as $groupe) {
                foreach ($groupe['champs'] as $cle => $champ) {
                    save_setting($cle, $cle === 'about_photo' ? (string) $idPhotoAPropos : $champ['defaut']);
                }
            }

            // 6. Comptes
            $requete = $pdo->prepare('INSERT INTO users (username, password_hash, created_at) VALUES (?, ?, ?)');
            foreach ($comptes as $compte) {
                if ($compte['identifiant'] !== '') {
                    $requete->execute([$compte['identifiant'], password_hash($compte['mdp'], PASSWORD_DEFAULT), now()]);
                }
            }

            $pdo->commit();

            if (!is_dir(__DIR__ . '/uploads/photos')) {
                @mkdir(__DIR__ . '/uploads/photos', 0755, true);
            }
            $termine = true;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $erreurs[] = "Erreur pendant l'installation : " . $e->getMessage();
        }
    }
}

// Vérifications de l'hébergement
$verifications = [
    ['PHP 8.1 ou plus', version_compare(PHP_VERSION, '8.1', '>='), PHP_VERSION],
    ['Extension PDO MySQL', extension_loaded('pdo_mysql'), ''],
    ['Extension GD (images)', extension_loaded('gd'), ''],
    ['Conversion WebP', function_exists('imagewebp'), ''],
    ['Dossier uploads/ accessible en écriture', is_writable(__DIR__ . '/uploads') || is_writable(__DIR__ . '/uploads/photos'), ''],
    ['Taille maximale d\'envoi', true, ini_get('upload_max_filesize') . ' par fichier, ' . ini_get('post_max_size') . ' par envoi'],
];
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Installation – MTS Photographie</title>
    <link rel="stylesheet" href="admin/assets/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.3.1/css/all.min.css">
</head>
<body class="page-connexion">
    <main class="carte carte-connexion carte-large">
        <h1>Installation</h1>

        <?php foreach ($erreurs as $erreur): ?>
            <p class="alerte alerte-erreur"><?= e($erreur) ?></p>
        <?php endforeach; ?>

        <?php if ($termine): ?>
            <p class="alerte alerte-succes">Installation terminée : les tables sont créées, le contenu actuel du site est importé et les comptes sont prêts.</p>
            <p><strong>Important :</strong> supprimez maintenant le fichier <code>install.php</code> du serveur.</p>
            <a class="bouton" href="admin/login.php">Aller au dashboard</a>
        <?php elseif ($pdo && !deja_installe($pdo)): ?>
            <ul class="verifications">
                <?php foreach ($verifications as [$libelle, $ok, $detail]): ?>
                    <li class="<?= $ok ? 'ok' : 'ko' ?>">
                        <i class="fa-solid <?= $ok ? 'fa-circle-check' : 'fa-circle-xmark' ?>"></i>
                        <?= e($libelle) ?><?= $detail ? ' : ' . e($detail) : '' ?>
                    </li>
                <?php endforeach; ?>
            </ul>

            <form method="post" class="formulaire">
                <?= champ_csrf() ?>
                <p class="aide">Chaque compte choisit son mot de passe (10 caractères minimum). Le second compte est facultatif.</p>
                <?php foreach ($comptes as $i => $compte): ?>
                    <fieldset>
                        <legend>Compte <?= $i + 1 ?></legend>
                        <label>Identifiant
                            <input type="text" name="identifiant[]" value="<?= e($compte['identifiant']) ?>" autocomplete="off" <?= $i === 0 ? 'required' : '' ?>>
                        </label>
                        <label>Mot de passe
                            <input type="password" name="mdp[]" minlength="10" autocomplete="new-password" <?= $i === 0 ? 'required' : '' ?>>
                        </label>
                        <label>Confirmation
                            <input type="password" name="confirmation[]" minlength="10" autocomplete="new-password" <?= $i === 0 ? 'required' : '' ?>>
                        </label>
                    </fieldset>
                <?php endforeach; ?>
                <button type="submit" class="bouton">Installer</button>
            </form>
        <?php endif; ?>
    </main>
</body>
</html>
