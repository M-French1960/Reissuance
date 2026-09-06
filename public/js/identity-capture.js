/*
 * Capture et compression des pièces d'identité.
 *
 * Le seul JavaScript du projet, avec la résilience d'envoi. Il existe parce
 * que trois besoins reposent sur des API du navigateur et ne peuvent pas être
 * couverts par du HTML/CSS seul (D-010) :
 *
 *   1. la capture en direct — getUserMedia (§5.2 du brief) ;
 *   2. la compression avant envoi — canvas (D-008) ;
 *   3. le retour visuel pendant l'envoi.
 *
 * Écrit en amélioration progressive : sans JavaScript, le champ « fichier »
 * reste utilisable et le formulaire fonctionne. La compression disparaît
 * alors, mais la validation serveur, elle, ne dépend de rien.
 *
 * Aucune dépendance, aucun gestionnaire en ligne : la CSP du projet interdit
 * `unsafe-inline` et `unsafe-eval`.
 */
(function () {
  'use strict';

  var CONFIG = {
    maxDimension: 1600,
    targetBytes: 250 * 1024,
    // Qualités essayées dans l'ordre : on s'arrête dès qu'on tient la cible.
    qualities: [0.82, 0.7, 0.6, 0.5, 0.42],
    mime: 'image/jpeg'
  };

  /** Redimensionne puis compresse jusqu'à tenir sous la cible. */
  function compress(source) {
    return new Promise(function (resolve, reject) {
      var image = new Image();

      image.onerror = function () {
        reject(new Error("Cette image n'a pas pu être lue. Reprenez la photo."));
      };

      image.onload = function () {
        var ratio = Math.min(
          1,
          CONFIG.maxDimension / Math.max(image.naturalWidth, image.naturalHeight)
        );

        var canvas = document.createElement('canvas');
        canvas.width = Math.round(image.naturalWidth * ratio);
        canvas.height = Math.round(image.naturalHeight * ratio);
        canvas.getContext('2d').drawImage(image, 0, 0, canvas.width, canvas.height);

        var index = 0;

        function attempt() {
          canvas.toBlob(function (blob) {
            if (!blob) {
              reject(new Error("La compression a échoué. Reprenez la photo."));
              return;
            }

            // On accepte dès qu'on tient la cible, ou à la dernière qualité :
            // mieux vaut une image un peu lourde que pas d'image du tout.
            if (blob.size <= CONFIG.targetBytes || index >= CONFIG.qualities.length - 1) {
              resolve(blob);
              return;
            }

            index += 1;
            attempt();
          }, CONFIG.mime, CONFIG.qualities[index]);
        }

        attempt();
      };

      image.src = URL.createObjectURL(source);
    });
  }

  function formatSize(bytes) {
    return bytes < 1024 * 1024
      ? Math.round(bytes / 1024) + ' Ko'
      : (bytes / 1024 / 1024).toFixed(1) + ' Mo';
  }

  function setupBlock(block) {
    var input = block.querySelector('[data-capture-input]');
    var preview = block.querySelector('[data-capture-preview]');
    var feedback = block.querySelector('[data-capture-feedback]');
    var submit = block.querySelector('[data-capture-submit]');
    var form = block.querySelector('form');

    if (!input || !form) {
      return;
    }

    // Le champ n'est plus caché : sans JS, il reste le moyen d'envoyer.
    block.removeAttribute('data-capture-noscript');

    function say(message, tone) {
      if (!feedback) {
        return;
      }
      feedback.textContent = message;
      feedback.className = 'field__hint capture__feedback capture__feedback--' + (tone || 'neutral');
    }

    input.addEventListener('change', function () {
      var file = input.files && input.files[0];

      if (!file) {
        return;
      }

      if (submit) {
        submit.disabled = true;
      }
      say('Préparation de la photo…', 'neutral');

      compress(file).then(function (blob) {
        var compressed = new File([blob], 'piece.jpg', { type: CONFIG.mime });
        var transfer = new DataTransfer();
        transfer.items.add(compressed);
        input.files = transfer.files;

        if (preview) {
          preview.src = URL.createObjectURL(blob);
          preview.hidden = false;
        }

        say(
          'Photo prête : ' + formatSize(file.size) + ' réduits à ' + formatSize(blob.size) + '.',
          'success'
        );

        if (submit) {
          submit.disabled = false;
        }
      }).catch(function (error) {
        // On n'empêche pas l'envoi : le serveur validera. Mais on prévient.
        say(error.message + ' La photo sera envoyée telle quelle.', 'danger');

        if (submit) {
          submit.disabled = false;
        }
      });
    });

    // Retour visuel immédiat pendant l'envoi (§8.5), et protection contre le
    // double envoi d'une même photo sur réseau lent.
    form.addEventListener('submit', function () {
      if (submit) {
        submit.disabled = true;
        submit.textContent = 'Envoi en cours…';
      }
    });
  }

  function init() {
    document.querySelectorAll('[data-capture]').forEach(setupBlock);

    // Détection de perte de connexion : sur réseau contraint, une saisie
    // perdue sans explication est la pire des situations (§8.5).
    var banner = document.querySelector('[data-offline-banner]');

    if (banner) {
      var update = function () {
        banner.hidden = navigator.onLine;
      };
      window.addEventListener('online', update);
      window.addEventListener('offline', update);
      update();
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
