/**
 * Menu de la page d'accueil sur petit écran.
 *
 * C'est TOUT ce que ce fichier fait. La maquette livrée portait aussi un
 * sélecteur de langue « visuel uniquement » et un formulaire de suivi qui
 * validait un numéro puis n'allait nulle part ; ni l'un ni l'autre n'a été
 * porté. Le changement de langue passe par le vrai composant du service, qui
 * écrit en session et sur le compte ; le suivi d'une demande n'est pas public.
 *
 * Amélioration progressive, et dans le bon sens. La première version repliait
 * le menu par défaut et laissait au bouton le soin de le rouvrir : sans
 * JavaScript, les quatre liens de section étaient donc inatteignables sur
 * téléphone. Le repli appartient maintenant au script, et à lui seul : c'est
 * l'attribut data-js posé ci-dessous qui autorise la feuille de style à
 * replier la liste et à montrer le bouton.
 */

/* Posé tout de suite, hors de DOMContentLoaded : la feuille de style en a
   besoin au premier rendu, sans quoi le menu s'ouvrirait puis se fermerait
   sous les yeux du visiteur. */
document.documentElement.dataset.js = '';

document.addEventListener('DOMContentLoaded', function () {
    var bouton = document.querySelector('[data-home-menu-toggle]');
    var menu = document.querySelector('[data-home-menu]');

    if (!bouton || !menu) {
        return;
    }

    function etat(ouvert) {
        menu.classList.toggle('is-open', ouvert);
        bouton.setAttribute('aria-expanded', ouvert ? 'true' : 'false');
        bouton.setAttribute('aria-label', ouvert ? bouton.dataset.labelClose : bouton.dataset.labelOpen);
    }

    bouton.addEventListener('click', function () {
        etat(!menu.classList.contains('is-open'));
    });

    // Une ancre replie le menu : sans cela, le lecteur atterrit sur la section
    // demandee avec la liste toujours ouverte par-dessus.
    menu.querySelectorAll('a').forEach(function (lien) {
        lien.addEventListener('click', function () {
            etat(false);
        });
    });

    // Echap referme, comme partout ailleurs.
    document.addEventListener('keydown', function (evenement) {
        if (evenement.key === 'Escape' && menu.classList.contains('is-open')) {
            etat(false);
            bouton.focus();
        }
    });
});
