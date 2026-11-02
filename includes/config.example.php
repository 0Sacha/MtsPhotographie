<?php
/*
 * Configuration du site.
 * 1. Copier ce fichier en « config.php » (dans le même dossier)
 * 2. Renseigner les identifiants de la base MySQL donnés par le panneau WebStrator
 * 3. Remplacer « secret » par une longue chaîne aléatoire
 * Le fichier config.php ne doit jamais être commité (il est dans .gitignore).
 */
return [
    'db' => [
        'host'     => 'localhost',
        'port'     => 3306,
        'name'     => 'nom_de_la_base',
        'user'     => 'utilisateur',
        'password' => 'mot_de_passe',
    ],

    // Sert à anonymiser les statistiques : une longue chaîne aléatoire (au moins 32 caractères)
    'secret' => 'remplacer-par-une-longue-chaine-aleatoire',

    // Adresse publique du site, sans « / » final
    'url' => 'https://mts-photographie.wstr.fr',

    // Mettre à true uniquement pour déboguer (affiche les erreurs PHP)
    'debug' => false,
];
