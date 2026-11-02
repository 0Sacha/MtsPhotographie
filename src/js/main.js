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

    const afficher = (nouvellePosition) => {
        position = (nouvellePosition + liste.length) % liste.length;
        const source = liste[position].querySelector('img');
        image.src = source.src;
        image.alt = source.alt;
        legende.textContent = source.alt;
        compteur.textContent = `${position + 1} / ${liste.length}`;
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
