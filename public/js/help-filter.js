/**
 * Filtre les questions de l'aide (D-097).
 *
 * Enrichissement progressif, et rien de plus. Le champ de recherche est LIVRE
 * MASQUE et n'apparait que si ce fichier s'execute : sans lui, un champ inerte
 * ferait taper l'utilisateur dans le vide, ce qui est pire que pas de champ du
 * tout. Les cinq questions, elles, restent toutes lisibles quoi qu'il arrive.
 *
 * Aucun style en ligne : `style-src 'self'` les refuserait en silence. On
 * bascule l'attribut `hidden`, que le navigateur comprend seul.
 */
document.addEventListener('DOMContentLoaded', function () {
    var champ = document.querySelector('[data-help-filter]');
    var enveloppe = document.querySelector('[data-help-filter-wrap]');

    if (!champ || !enveloppe) {
        return;
    }

    var questions = Array.prototype.slice.call(document.querySelectorAll('[data-help-item]'));
    var vide = document.querySelector('[data-help-empty]');

    enveloppe.hidden = false;

    champ.addEventListener('input', function () {
        // Sans accents ni casse : « delai » doit trouver « délai ». Un usager
        // qui tape depuis un telephone n'accentue pas toujours.
        var recherche = normaliser(champ.value);
        var trouvees = 0;

        questions.forEach(function (question) {
            var correspond = recherche === '' || normaliser(question.textContent).indexOf(recherche) !== -1;

            question.hidden = !correspond;

            if (correspond) {
                trouvees++;
            }
        });

        if (vide) {
            vide.hidden = trouvees !== 0;
        }
    });

    function normaliser(texte) {
        return texte
            .toLowerCase()
            .normalize('NFD')
            .replace(/[̀-ͯ]/g, '');
    }
});
