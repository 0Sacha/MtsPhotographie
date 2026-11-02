// Statistiques de visite (anonymes, sans cookie) : voir api/track.php
const suivre = (donnees) => {
    const corps = JSON.stringify(donnees);
    try {
        if (navigator.sendBeacon) {
            navigator.sendBeacon('api/track.php', new Blob([corps], { type: 'application/json' }));
        } else {
            fetch('api/track.php', { method: 'POST', body: corps, keepalive: true, headers: { 'Content-Type': 'application/json' } });
        }
    } catch {
        // Les statistiques ne doivent jamais bloquer le site
    }
};

if (document.body.dataset.page) {
    suivre({ type: 'page', page: document.body.dataset.page, ref: document.referrer });
}

// Apparition des éléments au défilement
const observateur = new IntersectionObserver((entrees) => {
    entrees.forEach((entree) => {
        if (entree.isIntersecting) {
            entree.target.classList.add('visible');
            observateur.unobserve(entree.target);
        }
    });
}, { threshold: 0.1 });

document.querySelectorAll('.apparition').forEach((element) => observateur.observe(element));

// Barre de navigation mobile : se cache en descendant, réapparaît en remontant
const sidebar = document.querySelector('#sidebar');
const mobile = window.matchMedia('(max-width: 900px)');

if (sidebar) {
    let dernierePosition = Math.max(0, window.scrollY);
    let enAttente = false;

    const majNavigation = () => {
        enAttente = false;
        const position = Math.max(0, window.scrollY);
        const ecart = position - dernierePosition;

        if (!mobile.matches) {
            sidebar.classList.remove('defile', 'cache');
            dernierePosition = position;
            return;
        }

        sidebar.classList.toggle('defile', position > 8);

        // Petit seuil pour éviter que la barre clignote sur les micro-défilements
        if (Math.abs(ecart) > 6) {
            sidebar.classList.toggle('cache', ecart > 0 && position > sidebar.offsetHeight);
            dernierePosition = position;
        }
    };

    window.addEventListener('scroll', () => {
        if (!enAttente) {
            enAttente = true;
            requestAnimationFrame(majNavigation);
        }
    }, { passive: true });

    mobile.addEventListener('change', majNavigation);
}

// Diaporama de l'accueil
const diaporama = document.querySelector('.diaporama');

if (diaporama) {
    const images = diaporama.querySelectorAll('img');
    const points = diaporama.querySelectorAll('.progression span');
    const legende = diaporama.querySelector('.legende p');
    let index = 0;

    setInterval(() => {
        images[index].classList.remove('visible');
        points[index].classList.remove('actif');
        index = (index + 1) % images.length;
        images[index].classList.add('visible');
        points[index].classList.add('actif');
        legende.textContent = images[index].alt;
    }, 5000);
}

// Filtres du portfolio
const filtres = document.querySelectorAll('.filtres button');
const figures = document.querySelectorAll('.photo figure');

filtres.forEach((bouton) => {
    const filtre = bouton.dataset.filtre;

    // Compteur calculé à partir des photos présentes dans la page
    bouton.querySelector('span').textContent = filtre === 'tout'
        ? figures.length
        : [...figures].filter((figure) => figure.dataset.categorie === filtre).length;

    bouton.addEventListener('click', () => {
        filtres.forEach((autre) => autre.classList.toggle('actif', autre === bouton));
        figures.forEach((figure) => {
            figure.hidden = filtre !== 'tout' && figure.dataset.categorie !== filtre;
        });
    });
});

// Visionneuse plein écran
const lightbox = document.querySelector('.lightbox');

if (lightbox) {
    const image = lightbox.querySelector('img');
    const legende = lightbox.querySelector('figcaption');
    const compteur = lightbox.querySelector('.compteur');
    let liste = [];
    let position = 0;
    const photosVues = new Set();

    const afficher = (nouvellePosition) => {
        position = (nouvellePosition + liste.length) % liste.length;
        const source = liste[position].querySelector('img');
        image.src = source.src;
        image.alt = source.alt;
        legende.textContent = source.alt;
        compteur.textContent = `${position + 1} / ${liste.length}`;

        // Chaque photo n'est comptée qu'une fois par visite de la page
        const id = liste[position].dataset.id;
        if (id && !photosVues.has(id)) {
            photosVues.add(id);
            suivre({ type: 'photo', id: Number(id) });
        }
    };

    const ouvrir = (figure) => {
        liste = [...figures].filter((autre) => !autre.hidden);
        afficher(liste.indexOf(figure));
        lightbox.showModal();
    };

    figures.forEach((figure) => {
        figure.addEventListener('click', () => ouvrir(figure));
        figure.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                ouvrir(figure);
            }
        });
    });

    lightbox.querySelector('.fermer').addEventListener('click', () => lightbox.close());
    lightbox.querySelector('.precedent').addEventListener('click', () => afficher(position - 1));
    lightbox.querySelector('.suivant').addEventListener('click', () => afficher(position + 1));

    // Fermeture au clic en dehors de la photo
    lightbox.addEventListener('click', (e) => {
        if (e.target === lightbox) lightbox.close();
    });

    lightbox.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowLeft') afficher(position - 1);
        if (e.key === 'ArrowRight') afficher(position + 1);
    });

    // Balayage sur mobile
    let departX = 0;
    lightbox.addEventListener('touchstart', (e) => {
        departX = e.touches[0].clientX;
    });
    lightbox.addEventListener('touchend', (e) => {
        const distance = e.changedTouches[0].clientX - departX;
        if (Math.abs(distance) > 50) afficher(position + (distance < 0 ? 1 : -1));
    });
}
