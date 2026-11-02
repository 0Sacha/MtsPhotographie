<?php
declare(strict_types=1);

require __DIR__ . '/_init.php';

if (utilisateur()) {
    rediriger('index.php');
}

$erreur = null;
$identifiant = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifier_csrf();
    $identifiant = strtolower(trim((string) ($_POST['identifiant'] ?? '')));
    $motDePasse = (string) ($_POST['mot_de_passe'] ?? '');

    if (connexion_bloquee()) {
        $erreur = 'Trop de tentatives. Réessayez dans ' . CONNEXION_BLOCAGE_MINUTES . ' minutes.';
    } else {
        $requete = db()->prepare('SELECT id, password_hash FROM users WHERE username = ?');
        $requete->execute([$identifiant]);
        $compte = $requete->fetch();

        if ($compte && password_verify($motDePasse, $compte['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $compte['id'];
            unset($_SESSION['csrf']);

            if (password_needs_rehash($compte['password_hash'], PASSWORD_DEFAULT)) {
                db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                    ->execute([password_hash($motDePasse, PASSWORD_DEFAULT), $compte['id']]);
            }
            db()->prepare('UPDATE users SET last_login = ? WHERE id = ?')->execute([now(), $compte['id']]);
            db()->prepare('DELETE FROM login_attempts WHERE ip_hash = ?')->execute([empreinte_ip()]);
            rediriger('index.php');
        }

        noter_echec_connexion();
        usleep(400_000); // ralentit les tentatives automatiques
        $erreur = 'Identifiant ou mot de passe incorrect.';
    }
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <title>Connexion – Dashboard</title>
    <link rel="stylesheet" href="assets/admin.css">
</head>
<body class="page-connexion">
    <main class="carte carte-connexion">
        <div>
            <p class="surtitre">Dashboard</p>
            <h1>Connexion</h1>
        </div>
<?php if ($erreur): ?>
        <p class="alerte alerte-erreur"><?= e($erreur) ?></p>
<?php endif; ?>
        <form method="post" class="formulaire">
            <?= champ_csrf() ?>
            <label>Identifiant
                <input type="text" name="identifiant" value="<?= e($identifiant) ?>" autocomplete="username" autocapitalize="none" required autofocus>
            </label>
            <label>Mot de passe
                <input type="password" name="mot_de_passe" autocomplete="current-password" required>
            </label>
            <button type="submit" class="bouton">Se connecter</button>
        </form>
        <a class="aide" href="../index.php">← Retour au site</a>
    </main>
</body>
</html>
