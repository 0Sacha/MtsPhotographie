# MTS Photographie

Site de Hugo Martins, photographe nature et paysage, avec un dashboard pour gérer les photos et les textes sans toucher au code.

- **Site public** : accueil (diaporama et bandeau), portfolio filtrable avec visionneuse, services, à propos, contact.
- **Dashboard** (`/admin`) : statistiques de visite, envoi et organisation des photos, catégories, textes, comptes.

Technologies : PHP 8.1+, MySQL / MariaDB, HTML, SCSS et JavaScript, sans framework ni dépendance à installer.

## Structure

```
index.php, portfolio.php, services.php, about.php, contact.php   pages publiques
admin/          dashboard (connexion obligatoire)
api/track.php   statistiques de visite anonymes
includes/       configuration, connexion à la base, fonctions, en-tête commun
sql/schema.sql  structure de la base
install.php     installation (à supprimer du serveur après usage)
uploads/photos/ photos envoyées depuis le dashboard (hors git)
src/            CSS, JavaScript et photos d'origine
```

## Mise en ligne sur WebStrator

1. **Base de données** : dans le panneau, créer une base MySQL (connexion « localhost uniquement ») et noter l'hôte, le port, le nom de la base, l'utilisateur et le mot de passe.
2. **PHP** : choisir PHP 8.1 ou plus récent.
3. **Configuration** : copier `includes/config.example.php` en `includes/config.php` et le remplir (identifiants de la base, longue chaîne aléatoire pour `secret`, adresse du site). Ce fichier n'est jamais commité.
4. **Envoi des fichiers** par FTP à la racine du site : tout le projet sauf `.git/` et `.idea/`.
5. **Installation** : ouvrir `https://mts-photographie.wstr.fr/install.php`, vérifier que tous les voyants sont verts, choisir les mots de passe des comptes et valider. Le contenu actuel du site (photos, textes, services) est importé automatiquement.
6. **Supprimer `install.php` du serveur**, puis se connecter sur `/admin`.

Pour les mises à jour suivantes, renvoyer seulement les fichiers modifiés par FTP, sans écraser `includes/config.php` ni le dossier `uploads/photos/`.

## Utilisation du dashboard

| Page | Rôle |
|---|---|
| Tableau de bord | Visiteurs et pages vues, graphique par jour, pages les plus vues, provenance, appareils, photos les plus ouvertes, état du serveur |
| Photos | Envoi par glisser-déposer (converties en WebP, redressées et redimensionnées automatiquement), titre, catégorie, visibilité, diaporama et bandeau de l'accueil, ordre, suppression |
| Catégories | Filtres du portfolio : ajout, renommage, ordre, suppression |
| Contenus | Textes de chaque page et cartes de services |
| Comptes | Ajout et suppression de comptes, changement de mot de passe |
| Maintenance (barre latérale) | Met le site en pause : les visiteurs voient une page d'attente (code 503), les administrateurs connectés voient le site normalement |

Les statistiques sont anonymes : pas de cookie, aucune adresse IP enregistrée, robots et administrateurs connectés non comptés, données effacées après 13 mois.

## Sécurité

- Mots de passe chiffrés (`password_hash`), 10 caractères minimum.
- Blocage de 15 minutes après 5 essais de connexion ratés.
- Jeton CSRF sur toutes les actions et requêtes préparées partout.
- Photos réencodées à l'envoi : un fichier piégé ne passe pas.
- `includes/`, `sql/` et l'exécution de scripts dans `uploads/` sont bloqués par les fichiers `.htaccess`.

## Développement en local

```bash
cp includes/config.example.php includes/config.php   # puis le remplir
php -S localhost:8000                                # serveur de développement PHP
sass --watch src/css/style.scss src/css/style.css    # compilation des styles du site
```

Les styles du dashboard sont dans `admin/assets/admin.css` (CSS simple, sans compilation).
