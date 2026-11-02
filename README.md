# MTS Photographie

Site vitrine statique d'un photographe amateur : nature, paysages et portraits.

## Pages

| Fichier          | Contenu                                                        |
|------------------|----------------------------------------------------------------|
| `index.html`     | Accueil : accroche, diaporama et bandeau de photos défilant    |
| `portfolio.html` | Galerie filtrable (Nature / Portraits) avec visionneuse        |
| `services.html`  | Prestations proposées (sur devis)                              |
| `about.html`     | Présentation du photographe                                    |
| `contact.html`   | Coordonnées                                                    |

## Structure

```
src/
├── css/
│   ├── reset.css     # reset CSS (intégré à la compilation)
│   ├── style.scss    # source des styles
│   └── style.css     # fichier compilé, chargé par les pages
├── js/main.js        # diaporama, filtres, visionneuse, animations
└── img/
    ├── icon/         # icônes SVG de la barre de navigation
    ├── NatureIMG/    # photos nature (WebP)
    └── PortraitIMG/  # portraits (WebP)
```

Dépendance externe : [Font Awesome](https://fontawesome.com/) 7.3.1 (version gratuite, via cdnjs).

## Développement

Aucune étape de build hormis la compilation Sass :

```bash
sass src/css/style.scss src/css/style.css
# ou en continu
sass --watch src/css/style.scss src/css/style.css
```

Ouvrir ensuite `index.html` dans un navigateur.

## Ajouter une photo

1. Exporter l'image en **WebP** dans `src/img/NatureIMG/` ou `src/img/PortraitIMG/`.
2. Dans `portfolio.html`, ajouter un bloc `<figure>` avec `data-categorie="nature"` ou `"portrait"`,
   en renseignant `width` et `height` (dimensions réelles de l'image) et un `alt` descriptif.
3. Les compteurs des filtres se mettent à jour automatiquement.
