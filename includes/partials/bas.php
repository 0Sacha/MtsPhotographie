<?php
/*
 * Fin commune des pages publiques : pied de page (infos, contact, accès admin) puis fermeture de la page.
 * Utilise $adminConnecte, défini dans haut.php.
 */
$emailContact = setting('contact_email');
?>
            <footer class="pied">
                <p><strong><?= e(setting('site_nom')) ?></strong> · <?= e(setting('hero_soustitre')) ?></p>
<?php if ($emailContact !== ''): ?>
                <a href="mailto:<?= e($emailContact) ?>"><i class="fa-regular fa-envelope"></i><?= e($emailContact) ?></a>
<?php endif; ?>
                <p class="pied-droits">© <?= date('Y') ?> <?= e(setting('site_nom')) ?> · Photos protégées, reproduction interdite sans accord</p>
<?php if (!empty($adminConnecte)): ?>
                <a class="pied-admin connecte" href="admin/"><i class="fa-regular fa-chart-bar"></i>Dashboard</a>
<?php else: ?>
                <a class="pied-admin" href="admin/" rel="nofollow"><i class="fa-solid fa-lock"></i>Espace admin</a>
<?php endif; ?>
            </footer>
        </section>
    </main>
</body>

</html>
