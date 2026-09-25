/**
 * Confirmation avant d'enregistrer une decision.
 *
 * POURQUOI. Enregistrer une decision est irreversible : elle transmet l'acte
 * au maire, ou elle refuse la demande d'etat civil de quelqu'un et lui en
 * notifie le motif. Un clic de trop ne se rattrape pas.
 *
 * FILET, PAS BARRIERE. Sans ce fichier le formulaire s'envoie normalement et
 * les controles du serveur sont exactement les memes — motif obligatoire,
 * acceptation refusee tant qu'un controle manque, Policy, machine a etats.
 * La confirmation ne protege de rien d'autre que du geste maladroit, et elle
 * ne remplace aucun controle.
 *
 * Le dialogue reprend LA decision choisie, en toutes lettres : un « Etes-vous
 * sur ? » qui ne dit pas de quoi ne fait que rajouter un clic.
 */
document.addEventListener('DOMContentLoaded', function () {
    var formulaire = document.querySelector('[data-decision-form]');
    var dialogue = document.querySelector('[data-decision-dialog]');

    // Sans <dialog>, on ne simule rien : le formulaire part directement.
    if (!formulaire || !dialogue || typeof dialogue.showModal !== 'function') {
        return;
    }

    var resume = dialogue.querySelector('[data-decision-summary]');
    var confirmer = dialogue.querySelector('[data-decision-confirm]');
    var annuler = dialogue.querySelector('[data-decision-cancel]');
    var confirme = false;

    formulaire.addEventListener('submit', function (evenement) {
        if (confirme) {
            return;
        }

        var choix = formulaire.querySelector('input[name="decision"]:checked');

        // Rien de coche : on laisse le navigateur signaler le champ requis.
        if (!choix) {
            return;
        }

        evenement.preventDefault();

        var etiquette = formulaire.querySelector('label[for="' + choix.id + '"]');
        resume.textContent = etiquette ? etiquette.firstChild.textContent.trim() : choix.value;
        dialogue.showModal();
    });

    confirmer.addEventListener('click', function () {
        confirme = true;
        dialogue.close();
        formulaire.requestSubmit();
    });

    annuler.addEventListener('click', function () {
        dialogue.close();
    });
});
