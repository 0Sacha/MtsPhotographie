<?php
declare(strict_types=1);

require __DIR__ . '/_init.php';
$utilisateur = exiger_connexion();

$periodes = [7 => '7 jours', 30 => '30 jours', 90 => '3 mois', 365 => '12 mois'];
$jours = (int) ($_GET['jours'] ?? 30);
if (!isset($periodes[$jours])) {
    $jours = 30;
}
$debut = (new DateTimeImmutable('today'))->modify('-' . ($jours - 1) . ' days');

/** Nombre de pages vues et de visiteurs depuis une date */
function totaux(string $depuis): array
{
    $requete = db()->prepare('SELECT COUNT(*) AS vues, COUNT(DISTINCT visitor, DATE(created_at)) AS visiteurs FROM page_views WHERE created_at >= ?');
    $requete->execute([$depuis]);
    return $requete->fetch();
}

$aujourdhui = totaux(now('today'));
$semaine = totaux(now('today -6 days'));
$periode = totaux($debut->format('Y-m-d H:i:s'));

$requete = db()->prepare('SELECT COUNT(*) FROM photo_views WHERE created_at >= ?');
$requete->execute([$debut->format('Y-m-d H:i:s')]);
$photosOuvertes = (int) $requete->fetchColumn();

// Visites par jour (les jours sans visite sont complétés à zéro)
$requete = db()->prepare('SELECT DATE(created_at) AS jour, COUNT(*) AS vues, COUNT(DISTINCT visitor) AS visiteurs
                          FROM page_views WHERE created_at >= ? GROUP BY DATE(created_at)');
$requete->execute([$debut->format('Y-m-d H:i:s')]);
$parJour = [];
foreach ($requete as $ligne) {
    $parJour[$ligne['jour']] = $ligne;
}
$serie = [];
for ($i = 0; $i < $jours; $i++) {
    $jour = $debut->modify("+$i days")->format('Y-m-d');
    $serie[] = ['jour' => $jour, 'vues' => (int) ($parJour[$jour]['vues'] ?? 0), 'visiteurs' => (int) ($parJour[$jour]['visiteurs'] ?? 0)];
}

/** Classement générique : [libellé => nombre] */
function classement(string $sql, string $depuis): array
{
    $requete = db()->prepare($sql);
    $requete->execute([$depuis]);
    return $requete->fetchAll(PDO::FETCH_KEY_PAIR);
}

$depuis = $debut->format('Y-m-d H:i:s');
$nomsPages = ['accueil' => 'Accueil', 'portfolio' => 'Portfolio', 'services' => 'Services', 'apropos' => 'À propos', 'contact' => 'Contact'];
$pages = classement('SELECT page, COUNT(*) n FROM page_views WHERE created_at >= ? GROUP BY page ORDER BY n DESC', $depuis);
$provenances = classement("SELECT IF(referrer = '', 'Accès direct', referrer) r, COUNT(*) n FROM page_views WHERE created_at >= ? GROUP BY r ORDER BY n DESC LIMIT 8", $depuis);
$appareils = classement('SELECT device, COUNT(*) n FROM page_views WHERE created_at >= ? GROUP BY device ORDER BY n DESC', $depuis);

$requete = db()->prepare('SELECT p.id, p.thumb, p.title, COUNT(*) AS vues FROM photo_views v JOIN photos p ON p.id = v.photo_id
                          WHERE v.created_at >= ? GROUP BY p.id, p.thumb, p.title ORDER BY vues DESC LIMIT 8');
$requete->execute([$depuis]);
$topPhotos = $requete->fetchAll();

// État du site
$nbPhotos = (int) db()->query('SELECT COUNT(*) FROM photos')->fetchColumn();
$nbVisibles = (int) db()->query('SELECT COUNT(*) FROM photos WHERE visible = 1')->fetchColumn();
$poidsPhotos = 0;
foreach (glob(APP_ROOT . '/uploads/photos/*.webp') ?: [] as $fichier) {
    $poidsPhotos += filesize($fichier);
}
foreach (glob(APP_ROOT . '/src/img/*/*.webp') ?: [] as $fichier) {
    $poidsPhotos += filesize($fichier);
}
$poidsBase = (int) db()->query('SELECT COALESCE(SUM(data_length + index_length), 0) FROM information_schema.TABLES WHERE table_schema = DATABASE()')->fetchColumn();
$espaceLibre = function_exists('disk_free_space') ? @disk_free_space(APP_ROOT) : false;
$versionMysql = (string) db()->query('SELECT VERSION()')->fetchColumn();

$etat = [
    ['Photos', "$nbPhotos dont $nbVisibles visibles", true],
    ['Poids des photos', format_octets($poidsPhotos), true],
    ['Taille de la base', format_octets($poidsBase), true],
    ['Espace disque libre', $espaceLibre !== false ? format_octets($espaceLibre) : 'non communiqué', true],
    ['PHP', PHP_VERSION, version_compare(PHP_VERSION, '8.1', '>=')],
    ['MySQL / MariaDB', $versionMysql, true],
    ['Conversion WebP', function_exists('imagewebp') ? 'disponible' : 'indisponible', function_exists('imagewebp')],
    ['Envoi maximal', ini_get('upload_max_filesize') . ' par photo', true],
    ['Dossier uploads', is_writable(APP_ROOT . '/uploads/photos') ? 'accessible en écriture' : 'non accessible en écriture', is_writable(APP_ROOT . '/uploads/photos')],
    ['HTTPS', est_https() ? 'actif' : 'inactif', est_https()],
];

/** Liste de barres horizontales */
function afficher_classement(array $lignes, array $noms = [], string $vide = 'Aucune donnée pour cette période.'): void
{
    if (!$lignes) {
        echo '<p class="vide">' . e($vide) . '</p>';
        return;
    }
    $max = max($lignes);
    echo '<ul class="classement">';
    foreach ($lignes as $libelle => $nombre) {
        printf(
            '<li><span>%s</span><span class="nombre">%s</span><span class="jauge"><span style="width:%.1f%%"></span></span></li>',
            e($noms[$libelle] ?? (string) $libelle),
            number_format((int) $nombre, 0, ',', ' '),
            $max > 0 ? $nombre / $max * 100 : 0
        );
    }
    echo '</ul>';
}

$section = 'tableau';
$titrePage = 'Tableau de bord';
require __DIR__ . '/_haut.php';

// Dimensions du graphique
$largeur = 800;
$hauteur = 220;
$marge = 28;
$maxVues = max(1, ...array_column($serie, 'vues'));
$pas = ($largeur - $marge) / count($serie);
$moisCourts = ['', 'janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
$formatJour = fn (string $jour) => (int) substr($jour, 8, 2) . ' ' . $moisCourts[(int) substr($jour, 5, 2)];
?>
        <div class="admin-entete">
            <div>
                <p class="surtitre">Bonjour <?= e($utilisateur['username']) ?></p>
                <h1>Tableau de bord</h1>
            </div>
            <form method="get" class="actions">
                <label class="sr-only" for="jours">Période</label>
                <select id="jours" name="jours" onchange="this.form.submit()">
<?php foreach ($periodes as $valeur => $libelle): ?>
                    <option value="<?= $valeur ?>"<?= $valeur === $jours ? ' selected' : '' ?>><?= e($libelle) ?></option>
<?php endforeach; ?>
                </select>
            </form>
        </div>

        <div class="grille-stats">
            <div class="carte stat">
                <span class="aide">Aujourd'hui</span>
                <span class="valeur"><?= (int) $aujourdhui['visiteurs'] ?></span>
                <span class="detail">visiteurs · <?= (int) $aujourdhui['vues'] ?> pages vues</span>
            </div>
            <div class="carte stat">
                <span class="aide">7 derniers jours</span>
                <span class="valeur"><?= (int) $semaine['visiteurs'] ?></span>
                <span class="detail">visiteurs · <?= (int) $semaine['vues'] ?> pages vues</span>
            </div>
            <div class="carte stat">
                <span class="aide"><?= e($periodes[$jours]) ?></span>
                <span class="valeur"><?= (int) $periode['visiteurs'] ?></span>
                <span class="detail">visiteurs · <?= (int) $periode['vues'] ?> pages vues</span>
            </div>
            <div class="carte stat">
                <span class="aide">Photos ouvertes (<?= e($periodes[$jours]) ?>)</span>
                <span class="valeur"><?= $photosOuvertes ?></span>
                <span class="detail">dans la visionneuse du portfolio</span>
            </div>
        </div>

        <section class="section">
            <h2>Pages vues par jour</h2>
            <div class="carte graphique">
                <svg viewBox="0 0 <?= $largeur ?> <?= $hauteur + 24 ?>" role="img" aria-label="Pages vues par jour sur <?= e($periodes[$jours]) ?>">
<?php foreach ([0, 0.5, 1] as $niveau):
    $y = $hauteur - $niveau * ($hauteur - 10); ?>
                    <line class="grille" x1="<?= $marge ?>" x2="<?= $largeur ?>" y1="<?= $y ?>" y2="<?= $y ?>"></line>
                    <text class="axe" x="0" y="<?= $y + 4 ?>"><?= (int) round($maxVues * $niveau) ?></text>
<?php endforeach; ?>
<?php foreach ($serie as $i => $point):
    $h = $point['vues'] / $maxVues * ($hauteur - 10);
    $x = $marge + $i * $pas; ?>
                    <rect class="barre" x="<?= round($x + $pas * 0.15, 1) ?>" y="<?= round($hauteur - $h, 1) ?>" width="<?= round(max(1, $pas * 0.7), 1) ?>" height="<?= round(max($h, $point['vues'] ? 2 : 0), 1) ?>" rx="2">
                        <title><?= e($formatJour($point['jour'])) ?> : <?= $point['vues'] ?> pages vues, <?= $point['visiteurs'] ?> visiteurs</title>
                    </rect>
<?php endforeach; ?>
<?php
$intervalle = max(1, (int) ceil(count($serie) / 8));
foreach ($serie as $i => $point):
    if ($i % $intervalle !== 0) {
        continue;
    } ?>
                    <text class="axe" x="<?= round($marge + $i * $pas + $pas / 2, 1) ?>" y="<?= $hauteur + 18 ?>" text-anchor="middle"><?= e($formatJour($point['jour'])) ?></text>
<?php endforeach; ?>
                </svg>
            </div>
        </section>

        <section class="section grille-2">
            <div class="carte">
                <h2 class="surtitre">Pages les plus vues</h2>
                <?php afficher_classement($pages, $nomsPages); ?>
            </div>
            <div class="carte">
                <h2 class="surtitre">Provenance</h2>
                <?php afficher_classement($provenances); ?>
            </div>
            <div class="carte">
                <h2 class="surtitre">Appareils</h2>
                <?php afficher_classement($appareils, ['mobile' => 'Mobile', 'ordinateur' => 'Ordinateur', 'tablette' => 'Tablette']); ?>
            </div>
            <div class="carte">
                <h2 class="surtitre">Photos les plus ouvertes</h2>
<?php if ($topPhotos): ?>
                <ul class="classement classement-photos">
<?php foreach ($topPhotos as $photo): ?>
                    <li><img src="../<?= e($photo['thumb']) ?>" alt=""><span><?= e($photo['title'] ?: 'Sans titre') ?></span><span class="nombre"><?= (int) $photo['vues'] ?></span></li>
<?php endforeach; ?>
                </ul>
<?php else: ?>
                <p class="vide">Aucune photo ouverte pour cette période.</p>
<?php endif; ?>
            </div>
        </section>

        <section class="section">
            <h2>État du site</h2>
            <div class="carte">
                <dl class="etat">
<?php foreach ($etat as [$libelle, $valeur, $ok]): ?>
                    <div><dt><?= e($libelle) ?></dt><dd class="<?= $ok ? '' : 'ko' ?>"><?= e($valeur) ?></dd></div>
<?php endforeach; ?>
                </dl>
            </div>
            <p class="aide" style="margin-top:12px">Les statistiques sont anonymes : pas de cookie, aucune adresse IP enregistrée, et vos propres visites ne sont pas comptées quand vous êtes connecté.</p>
        </section>
<?php require __DIR__ . '/_bas.php';
