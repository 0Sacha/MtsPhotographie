<?php
declare(strict_types=1);

require __DIR__ . '/_init.php';
$utilisateur = exiger_connexion();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifier_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'mon_mot_de_passe') {
        $requete = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
        $requete->execute([$utilisateur['id']]);
        $nouveau = (string) ($_POST['nouveau'] ?? '');

        if (!password_verify((string) ($_POST['actuel'] ?? ''), (string) $requete->fetchColumn())) {
            flash('erreur', 'Mot de passe actuel incorrect.');
        } elseif ($erreur = erreur_mot_de_passe($nouveau, (string) ($_POST['confirmation'] ?? ''))) {
            flash('erreur', $erreur);
        } else {
            db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($nouveau, PASSWORD_DEFAULT), $utilisateur['id']]);
            session_regenerate_id(true);
            flash('succes', 'Votre mot de passe a été modifié.');
        }
    } elseif ($action === 'ajouter') {
        $identifiant = strtolower(trim((string) ($_POST['identifiant'] ?? '')));
        $motDePasse = (string) ($_POST['mot_de_passe'] ?? '');
        $existe = db()->prepare('SELECT COUNT(*) FROM users WHERE username = ?');
        $existe->execute([$identifiant]);

        if ($erreur = erreur_identifiant($identifiant) ?? erreur_mot_de_passe($motDePasse, (string) ($_POST['confirmation'] ?? ''))) {
            flash('erreur', $erreur);
        } elseif ((int) $existe->fetchColumn()) {
            flash('erreur', "Le compte « $identifiant » existe déjà.");
        } else {
            db()->prepare('INSERT INTO users (username, password_hash, created_at) VALUES (?, ?, ?)')
                ->execute([$identifiant, password_hash($motDePasse, PASSWORD_DEFAULT), now()]);
            flash('succes', "Compte « $identifiant » créé. Transmettez-lui son mot de passe de vive voix.");
        }
    } elseif ($action === 'supprimer') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id === (int) $utilisateur['id']) {
            flash('erreur', 'Vous ne pouvez pas supprimer votre propre compte.');
        } else {
            db()->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
            flash('succes', 'Compte supprimé.');
        }
    }
    rediriger('comptes.php');
}

$comptes = db()->query('SELECT id, username, created_at, last_login FROM users ORDER BY username')->fetchAll();
$formatDate = fn (?string $date) => $date ? (new DateTimeImmutable($date))->format('d/m/Y à H:i') : 'jamais';

$section = 'comptes';
$titrePage = 'Comptes';
require __DIR__ . '/_haut.php';
?>
        <div class="admin-entete">
            <div>
                <p class="surtitre">Accès au dashboard</p>
                <h1>Comptes</h1>
            </div>
        </div>

        <div class="carte defilable">
            <table class="tableau">
                <thead>
                    <tr><th>Identifiant</th><th>Créé le</th><th>Dernière connexion</th><th></th></tr>
                </thead>
                <tbody>
<?php foreach ($comptes as $compte): ?>
                    <tr>
                        <td><strong><?= e($compte['username']) ?></strong><?= (int) $compte['id'] === (int) $utilisateur['id'] ? ' <span class="aide">(vous)</span>' : '' ?></td>
                        <td><?= e($formatDate($compte['created_at'])) ?></td>
                        <td><?= e($formatDate($compte['last_login'])) ?></td>
                        <td style="text-align:right">
<?php if ((int) $compte['id'] !== (int) $utilisateur['id']): ?>
                            <form method="post">
                                <?= champ_csrf() ?>
                                <input type="hidden" name="id" value="<?= (int) $compte['id'] ?>">
                                <button class="bouton bouton-danger" name="action" value="supprimer" data-confirmer><i class="fa-regular fa-trash-can"></i> Supprimer</button>
                            </form>
<?php endif; ?>
                        </td>
                    </tr>
<?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <section class="section grille-2">
            <form method="post" class="carte formulaire">
                <h2 class="surtitre">Changer mon mot de passe</h2>
                <?= champ_csrf() ?>
                <label>Mot de passe actuel
                    <input type="password" name="actuel" autocomplete="current-password" required>
                </label>
                <label>Nouveau mot de passe (10 caractères minimum)
                    <input type="password" name="nouveau" minlength="10" autocomplete="new-password" required>
                </label>
                <label>Confirmation
                    <input type="password" name="confirmation" minlength="10" autocomplete="new-password" required>
                </label>
                <div class="actions"><button class="bouton" name="action" value="mon_mot_de_passe">Modifier</button></div>
            </form>

            <form method="post" class="carte formulaire">
                <h2 class="surtitre">Ajouter un compte</h2>
                <?= champ_csrf() ?>
                <label>Identifiant
                    <input type="text" name="identifiant" pattern="[a-z0-9._\-]{3,30}" autocapitalize="none" autocomplete="off" required>
                </label>
                <label>Mot de passe (10 caractères minimum)
                    <input type="password" name="mot_de_passe" minlength="10" autocomplete="new-password" required>
                </label>
                <label>Confirmation
                    <input type="password" name="confirmation" minlength="10" autocomplete="new-password" required>
                </label>
                <div class="actions"><button class="bouton" name="action" value="ajouter"><i class="fa-solid fa-user-plus"></i> Ajouter</button></div>
            </form>
        </section>
<?php require __DIR__ . '/_bas.php';
