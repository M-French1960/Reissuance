/**
 * Tiroir de la barre latérale, sur petit écran.
 *
 * MÊME RÈGLE QU'À L'ACCUEIL (D-078) : sans ce fichier, la barre latérale
 * reste affichée, en pleine largeur au-dessus du contenu. Le tiroir est une
 * amélioration, jamais une condition d'accès au menu. C'est l'attribut
 * data-js posé ci-dessous qui autorise la feuille de style à escamoter la
 * barre et à montrer le bouton.
 */
document.documentElement.dataset.js = '';

document.addEventListener('DOMContentLoaded', function () {
    var barre = document.querySelector('[data-shell-side]');
    var bouton = document.querySelector('[data-shell-toggle]');
    var voile = document.querySelector('[data-shell-scrim]');

    if (!barre || !bouton) {
        return;
    }

    function etat(ouvert) {
        barre.classList.toggle('is-open', ouvert);

        if (voile) {
            voile.classList.toggle('is-open', ouvert);
        }

        bouton.setAttribute('aria-expanded', ouvert ? 'true' : 'false');
        bouton.setAttribute('aria-label', ouvert ? bouton.dataset.labelClose : bouton.dataset.labelOpen);
    }

    bouton.addEventListener('click', function () {
        etat(!barre.classList.contains('is-open'));
    });

    if (voile) {
        voile.addEventListener('click', function () {
            etat(false);
        });
    }

    // Une destination choisie referme le tiroir : sinon le lecteur arrive sur
    // la page demandee avec le menu toujours par-dessus.
    barre.querySelectorAll('a').forEach(function (lien) {
        lien.addEventListener('click', function () {
            etat(false);
        });
    });

    document.addEventListener('keydown', function (evenement) {
        if (evenement.key === 'Escape' && barre.classList.contains('is-open')) {
            etat(false);
            bouton.focus();
        }
    });
});
