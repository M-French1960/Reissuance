/*
 * Signature par appareil — WebAuthn (D-070).
 *
 * En JavaScript simple, sans dependance ni etape de compilation, comme le
 * reste du projet (D-010). Aucun code en ligne : la politique de securite du
 * contenu interdit `unsafe-inline`, et ce fichier est charge par `src`.
 *
 * CE QUE CE FICHIER NE FAIT PAS : aucune decision de securite. Il transporte
 * ce que l'appareil produit jusqu'au serveur, qui verifie. Un navigateur ne
 * prouve rien sur lui-meme.
 *
 * DEGRADATION VOLONTAIRE : si le navigateur ne connait pas WebAuthn, ou si la
 * page n'est pas servie en contexte securise, les boutons disparaissent et le
 * champ de code d'authentification reste. Une mairie ne doit pas cesser de
 * delivrer des actes parce qu'un navigateur est ancien.
 */
(function () {
  'use strict';

  var disponible =
    typeof window.PublicKeyCredential === 'function' &&
    window.isSecureContext === true &&
    typeof navigator.credentials === 'object';

  /* --- Encodage ---------------------------------------------------- */

  function versOctets(base64url) {
    var base64 = base64url.replace(/-/g, '+').replace(/_/g, '/');
    var brut = window.atob(base64 + '==='.slice((base64.length + 3) % 4));
    var octets = new Uint8Array(brut.length);
    for (var i = 0; i < brut.length; i++) {
      octets[i] = brut.charCodeAt(i);
    }
    return octets;
  }

  function versBase64Url(tampon) {
    var octets = new Uint8Array(tampon);
    var chaine = '';
    for (var i = 0; i < octets.length; i++) {
      chaine += String.fromCharCode(octets[i]);
    }
    return window.btoa(chaine).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
  }

  function decodeDescripteurs(liste) {
    return (liste || []).map(function (d) {
      return { type: d.type, id: versOctets(d.id), transports: d.transports };
    });
  }

  /* --- Dialogue avec le serveur ------------------------------------ */

  function options(url) {
    return fetch(url, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    }).then(function (reponse) {
      return reponse.json().then(function (corps) {
        if (!reponse.ok) {
          throw new Error(corps && corps.message ? corps.message : 'Demande refusée.');
        }
        return corps;
      });
    });
  }

  function annonce(zone, texte, erreur) {
    if (!zone) return;
    zone.textContent = texte;
    zone.className = erreur ? 'field__error' : 'u-note';
  }

  /* --- Enrôlement --------------------------------------------------- */

  function enrole(bouton) {
    var formulaire = document.getElementById('form-enrolement-appareil');
    var champ = document.getElementById('credential');
    var zone = document.getElementById('message-appareil');

    bouton.disabled = true;
    annonce(zone, 'Suivez les instructions de votre appareil…', false);

    options(bouton.dataset.optionsUrl)
      .then(function (opts) {
        return navigator.credentials.create({
          publicKey: {
            rp: opts.rp,
            user: {
              id: versOctets(opts.user.id),
              name: opts.user.name,
              displayName: opts.user.displayName,
            },
            challenge: versOctets(opts.challenge),
            pubKeyCredParams: opts.pubKeyCredParams,
            authenticatorSelection: opts.authenticatorSelection,
            attestation: opts.attestation,
            excludeCredentials: decodeDescripteurs(opts.excludeCredentials),
            timeout: opts.timeout,
          },
        });
      })
      .then(function (credential) {
        champ.value = JSON.stringify({
          id: credential.id,
          rawId: versBase64Url(credential.rawId),
          type: credential.type,
          response: {
            clientDataJSON: versBase64Url(credential.response.clientDataJSON),
            attestationObject: versBase64Url(credential.response.attestationObject),
          },
          clientExtensionResults: credential.getClientExtensionResults(),
        });
        formulaire.submit();
      })
      .catch(function (e) {
        bouton.disabled = false;
        annonce(zone, "L'appareil n'a pas pu être enrôlé : " + e.message, true);
      });
  }

  /* --- Signature ---------------------------------------------------- */

  function signe(bouton) {
    var formulaire = bouton.form;
    var champ = document.getElementById('device_assertion');
    var zone = document.getElementById('message-signature');

    bouton.disabled = true;
    annonce(zone, 'Confirmez sur votre appareil…', false);

    options(bouton.dataset.challengeUrl)
      .then(function (opts) {
        return navigator.credentials.get({
          publicKey: {
            challenge: versOctets(opts.challenge),
            rpId: opts.rpId,
            allowCredentials: decodeDescripteurs(opts.allowCredentials),
            userVerification: opts.userVerification,
            timeout: opts.timeout,
          },
        });
      })
      .then(function (credential) {
        champ.value = JSON.stringify({
          id: credential.id,
          rawId: versBase64Url(credential.rawId),
          type: credential.type,
          response: {
            clientDataJSON: versBase64Url(credential.response.clientDataJSON),
            authenticatorData: versBase64Url(credential.response.authenticatorData),
            signature: versBase64Url(credential.response.signature),
            userHandle: credential.response.userHandle
              ? versBase64Url(credential.response.userHandle)
              : null,
          },
          clientExtensionResults: credential.getClientExtensionResults(),
        });
        formulaire.action = bouton.dataset.signUrl;
        formulaire.submit();
      })
      .catch(function (e) {
        bouton.disabled = false;
        annonce(zone, "La signature par appareil a échoué : " + e.message + ' Vous pouvez utiliser votre code.', true);
      });
  }

  /* --- Mise en place ------------------------------------------------ */

  document.addEventListener('DOMContentLoaded', function () {
    var zones = document.querySelectorAll('[data-webauthn]');

    if (!disponible) {
      // On RETIRE plutot que de laisser un bouton qui echouera : proposer une
      // action impossible est pire que ne pas la proposer.
      Array.prototype.forEach.call(zones, function (z) {
        z.hidden = true;
      });
      return;
    }

    Array.prototype.forEach.call(zones, function (z) {
      z.hidden = false;
    });

    var enrolement = document.getElementById('bouton-enroler-appareil');
    if (enrolement) {
      enrolement.addEventListener('click', function (e) {
        e.preventDefault();
        enrole(enrolement);
      });
    }

    var signature = document.getElementById('bouton-signer-appareil');
    if (signature) {
      signature.addEventListener('click', function (e) {
        e.preventDefault();
        signe(signature);
      });
    }
  });
})();
