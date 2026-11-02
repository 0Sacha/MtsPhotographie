// Dashboard MTS Photographie

const jeton = document.querySelector('meta[name="csrf"]')?.content ?? '';

/* ---------- Notification ---------- */
const toast = document.querySelector('.toast');
let minuteurToast;

const notifier = (message, erreur = false) => {
    if (!toast) return;
    toast.textContent = message;
    toast.classList.toggle('erreur', erreur);
    toast.classList.add('visible');
    clearTimeout(minuteurToast);
    minuteurToast = setTimeout(() => toast.classList.remove('visible'), erreur ? 5000 : 2000);
};

/* ---------- Appels au serveur ---------- */
const api = async (url, donnees) => {
    const reponse = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF': jeton },
        body: JSON.stringify(donnees),
    });
    const resultat = await reponse.json().catch(() => ({ ok: false, erreur: `Erreur du serveur (${reponse.status})` }));
    if (!reponse.ok || !resultat.ok) {
        throw new Error(resultat.erreur || 'Erreur inconnue');
    }
    return resultat;
};

/* ---------- Boutons de suppression en deux temps (pas de fenêtre de confirmation) ---------- */
const demanderConfirmation = (bouton) => {
    if (bouton.classList.contains('confirmer')) {
        return true;
    }
    const texte = bouton.innerHTML;
    bouton.classList.add('confirmer');
    bouton.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> Confirmer ?';
    setTimeout(() => {
        bouton.classList.remove('confirmer');
        bouton.innerHTML = texte;
    }, 3500);
    return false;
};

// Formulaires classiques : <button data-confirmer> doit être cliqué deux fois
document.querySelectorAll('button[data-confirmer]').forEach((bouton) => {
    bouton.addEventListener('click', (e) => {
        if (!demanderConfirmation(bouton)) {
            e.preventDefault();
        }
    });
});

/* ---------- Envoi de photos ---------- */
const envoi = document.querySelector('#envoi');

if (envoi) {
    const champFichiers = envoi.querySelector('#fichiers');
    const zone = envoi.querySelector('.depot');
    const apercu = envoi.querySelector('.apercu-envoi');
    const boutonEnvoyer = envoi.querySelector('#envoyer');
    const progression = envoi.querySelector('#progression');
    let fichiers = [];

    const choisir = (liste) => {
        fichiers = [...liste].filter((f) => /^image\/(jpeg|png|webp)$/.test(f.type));
        apercu.replaceChildren(...fichiers.map((f) => {
            const img = document.createElement('img');
            img.src = URL.createObjectURL(f);
            img.alt = f.name;
            return img;
        }));
        boutonEnvoyer.disabled = fichiers.length === 0;
        progression.textContent = fichiers.length ? `${fichiers.length} photo${fichiers.length > 1 ? 's' : ''} prête${fichiers.length > 1 ? 's' : ''}` : '';
    };

    champFichiers.addEventListener('change', () => choisir(champFichiers.files));

    ['dragenter', 'dragover'].forEach((evenement) => zone.addEventListener(evenement, (e) => {
        e.preventDefault();
        zone.classList.add('survol');
    }));
    ['dragleave', 'drop'].forEach((evenement) => zone.addEventListener(evenement, () => zone.classList.remove('survol')));
    zone.addEventListener('drop', (e) => {
        e.preventDefault();
        choisir(e.dataTransfer.files);
    });

    // Une requête par photo : pas de limite de taille globale et une progression précise
    const envoyerUne = (fichier, numero) => new Promise((resoudre) => {
        const donnees = new FormData();
        donnees.append('photo', fichier);
        donnees.append('csrf', jeton);
        donnees.append('category_id', envoi.querySelector('[name="category_id"]').value);
        if (envoi.querySelector('[name="visible"]').checked) donnees.append('visible', '1');
        if (envoi.querySelector('[name="in_band"]').checked) donnees.append('in_band', '1');

        const requete = new XMLHttpRequest();
        requete.open('POST', envoi.dataset.url);
        requete.setRequestHeader('Accept', 'application/json');
        requete.upload.addEventListener('progress', (e) => {
            if (e.lengthComputable) {
                progression.textContent = `Photo ${numero} / ${fichiers.length} : ${Math.round((e.loaded / e.total) * 100)} %`;
            }
        });
        requete.addEventListener('load', () => {
            let resultat = {};
            try { resultat = JSON.parse(requete.responseText); } catch { /* réponse non JSON */ }
            resoudre(resultat.ok ? null : `${fichier.name} : ${resultat.erreur || `erreur ${requete.status}`}`);
        });
        requete.addEventListener('error', () => resoudre(`${fichier.name} : connexion interrompue`));
        requete.send(donnees);
    });

    boutonEnvoyer.addEventListener('click', async () => {
        boutonEnvoyer.disabled = true;
        const erreurs = [];
        for (const [i, fichier] of fichiers.entries()) {
            const erreur = await envoyerUne(fichier, i + 1);
            if (erreur) erreurs.push(erreur);
        }
        if (erreurs.length) {
            progression.textContent = '';
            notifier(`Échec : ${erreurs.join(' · ')}`, true);
            if (erreurs.length < fichiers.length) setTimeout(() => location.reload(), 4000);
            boutonEnvoyer.disabled = false;
        } else {
            progression.textContent = 'Terminé !';
            location.reload();
        }
    });
}

/* ---------- Grille des photos ---------- */
const grille = document.querySelector('#grille');

if (grille) {
    const url = grille.dataset.url;

    const renumeroter = () => {
        grille.querySelectorAll('.photo-carte .numero').forEach((numero, i) => { numero.textContent = i + 1; });
    };

    const enregistrerOrdre = async () => {
        renumeroter();
        try {
            await api(`${url}?action=ordre`, { ids: [...grille.children].map((carte) => Number(carte.dataset.id)) });
            notifier('Ordre enregistré');
        } catch (erreur) {
            notifier(erreur.message, true);
        }
    };

    // Modification d'un titre, d'une catégorie ou d'un interrupteur
    grille.addEventListener('change', async (e) => {
        const champ = e.target.dataset.champ;
        if (!champ) return;
        const carte = e.target.closest('.photo-carte');
        const valeur = e.target.type === 'checkbox' ? e.target.checked : e.target.value;
        try {
            await api(`${url}?action=modifier`, { id: Number(carte.dataset.id), champ, valeur });
            if (champ === 'visible') carte.classList.toggle('masquee', !valeur);
            e.target.classList.add('enregistre');
            setTimeout(() => e.target.classList.remove('enregistre'), 1200);
            notifier('Enregistré');
        } catch (erreur) {
            notifier(erreur.message, true);
            if (e.target.type === 'checkbox') e.target.checked = !valeur;
        }
    });

    // Entrée dans le champ titre : enregistre sans recharger
    grille.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && e.target.dataset.champ === 'title') {
            e.preventDefault();
            e.target.blur();
        }
    });

    grille.addEventListener('click', async (e) => {
        const carte = e.target.closest('.photo-carte');
        if (!carte) return;

        // Flèches : déplacer d'une place
        const deplacer = e.target.closest('[data-deplacer]');
        if (deplacer) {
            const sens = Number(deplacer.dataset.deplacer);
            const voisine = sens < 0 ? carte.previousElementSibling : carte.nextElementSibling;
            if (voisine) {
                voisine.insertAdjacentElement(sens < 0 ? 'beforebegin' : 'afterend', carte);
                enregistrerOrdre();
            }
            return;
        }

        // Suppression en deux clics
        const supprimer = e.target.closest('[data-supprimer]');
        if (supprimer && demanderConfirmation(supprimer)) {
            try {
                await api(`${url}?action=supprimer`, { id: Number(carte.dataset.id) });
                carte.remove();
                renumeroter();
                notifier('Photo supprimée');
            } catch (erreur) {
                notifier(erreur.message, true);
            }
        }
    });

    // Glisser-déposer pour réordonner
    let deplacee = null;

    grille.addEventListener('dragstart', (e) => {
        deplacee = e.target.closest('.photo-carte');
        if (!deplacee || e.target.closest('input, select, button')) {
            e.preventDefault();
            return;
        }
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', deplacee.dataset.id);
        requestAnimationFrame(() => deplacee.classList.add('glisse'));
    });

    grille.addEventListener('dragover', (e) => {
        if (!deplacee) return;
        e.preventDefault();
        const cible = e.target.closest('.photo-carte');
        if (!cible || cible === deplacee) return;
        const rect = cible.getBoundingClientRect();
        // Moitié droite de la carte survolée : on place la photo après, sinon avant
        const apres = e.clientX > rect.left + rect.width / 2;
        cible.insertAdjacentElement(apres ? 'afterend' : 'beforebegin', deplacee);
    });

    grille.addEventListener('drop', (e) => e.preventDefault());

    grille.addEventListener('dragend', () => {
        if (!deplacee) return;
        deplacee.classList.remove('glisse');
        deplacee = null;
        enregistrerOrdre();
    });
}
