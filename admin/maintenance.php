<?php
/*
 * Active ou désactive le mode maintenance (bouton de la barre latérale du dashboard).
 */
declare(strict_types=1);

require __DIR__ . '/_init.php';
$utilisateur = exiger_connexion();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifier_csrf();
    $actif = ($_POST['etat'] ?? '') === '1';
    save_setting('maintenance', $actif ? '1' : '0');
    flash('succes', $actif
        ? 'Mode maintenance activé : les visiteurs voient maintenant la page d\'attente.'
        : 'Le site est de nouveau en ligne pour tout le monde.');
}

// Retour sur la page d'où vient le clic (uniquement une page du dashboard)
$pages = ['index.php', 'photos.php', 'categories.php', 'contenus.php', 'comptes.php'];
$retour = (string) ($_POST['retour'] ?? '');
rediriger(in_array($retour, $pages, true) ? $retour : 'index.php');
