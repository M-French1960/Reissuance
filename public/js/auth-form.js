/**
 * Trois aides sur les formulaires d'authentification.
 *
 * AUCUNE N'EST NECESSAIRE. Sans ce fichier le formulaire s'envoie, le serveur
 * valide, et les messages d'erreur sont les memes. Le bouton d'affichage du
 * mot de passe ne s'ajoute que si le script tourne, l'alerte de verrouillage
 * majuscules reste muette, et la liste des regles reste une liste ordinaire.
 *
 * LA LISTE DES REGLES NE VALIDE RIEN. Elle reflete la politique reelle
 * (App\Rules\PasswordPolicy) pour eviter a quelqu'un de decouvrir au moment de
 * l'envoi que son mot de passe est refuse. C'est le serveur qui decide, et lui
 * seul : une regle supplementaire y vit (NotAWeakPassword) que le navigateur
 * ne peut pas verifier.
 */
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-password]').forEach(function (bloc) {
        var champ = bloc.querySelector('input');

        if (!champ) {
            return;
        }

        montrerOuCacher(bloc, champ);
        alerterMajuscules(bloc, champ);
        suivreLesRegles(bloc, champ);
    });
});

/** Le bouton n'existe que si ce code tourne : sinon il ne ferait rien. */
function montrerOuCacher(bloc, champ) {
    var bouton = document.createElement('button');
    bouton.type = 'button';
    bouton.className = 'auth__password-toggle';
    bouton.textContent = bloc.dataset.labelShow;
    bouton.setAttribute('aria-controls', champ.id);
    bouton.setAttribute('aria-pressed', 'false');

    bouton.addEventListener('click', function () {
        var visible = champ.type === 'text';
        champ.type = visible ? 'password' : 'text';
        bouton.textContent = visible ? bloc.dataset.labelShow : bloc.dataset.labelHide;
        bouton.setAttribute('aria-pressed', visible ? 'false' : 'true');
        champ.focus();
    });

    bloc.appendChild(bouton);
}

/**
 * Le verrouillage des majuscules explique une bonne part des echecs de
 * connexion : le champ est masque, donc personne ne voit le probleme.
 */
function alerterMajuscules(bloc, champ) {
    var alerte = bloc.parentElement.querySelector('[data-caps]');

    if (!alerte) {
        return;
    }

    function verifier(evenement) {
        if (typeof evenement.getModifierState !== 'function') {
            return;
        }

        alerte.classList.toggle('is-on', evenement.getModifierState('CapsLock'));
    }

    champ.addEventListener('keyup', verifier);
    champ.addEventListener('keydown', verifier);
    champ.addEventListener('blur', function () {
        alerte.classList.remove('is-on');
    });
}

/** Les regles s'allument au fur et a mesure de la frappe. */
function suivreLesRegles(bloc, champ) {
    var liste = bloc.parentElement.querySelector('[data-rules]');

    if (!liste) {
        return;
    }

    var controles = {
        length: function (valeur) { return valeur.length >= 12; },
        mixed: function (valeur) { return /[a-z]/.test(valeur) && /[A-Z]/.test(valeur); },
        number: function (valeur) { return /\d/.test(valeur); },
        symbol: function (valeur) { return /[^\p{L}\p{N}]/u.test(valeur); },
    };

    champ.addEventListener('input', function () {
        liste.querySelectorAll('[data-rule]').forEach(function (element) {
            var controle = controles[element.dataset.rule];
            element.classList.toggle('is-met', !!controle && controle(champ.value));
        });
    });
}
