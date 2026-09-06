# Questions juridiques ouvertes

> **Avertissement.** Je ne suis pas juriste et je n'ai aucune source vérifiable
> sur le droit camerounais applicable à ces sujets. Ce document ne contient
> **aucune citation de texte de loi**, aucune référence à un article, aucune
> affirmation sur l'état du droit. Il contient uniquement des **questions à
> poser** à un conseil juridique et aux autorités compétentes.
>
> Conformément au §10 du brief : aucune hypothèse juridique n'est codée. Là où
> une réponse manque, le code s'arrête ou passe par un adaptateur factice
> explicitement marqué comme sans valeur juridique.

- **Date :** 2026-09-06
- **À adresser à :** un conseil juridique, l'autorité d'état civil, l'autorité
  de protection des données, l'autorité de police

---

## Bloc A — Valeur légale de l'acte signé électroniquement

**Bloque le jalon 5.** Sans réponse, la plateforme produit un document dont on
ignore s'il vaut quelque chose.

- A1. Un acte d'état civil signé électroniquement est-il reconnu au Cameroun ?
  Sous quelles conditions de forme ?
- A2. Existe-t-il un régime d'agrément des prestataires de signature
  électronique ? Quels prestataires sont agréés à ce jour ?
- A3. Quel niveau de signature est exigé pour un acte d'état civil — simple,
  avancée, qualifiée, ou une catégorie propre au droit camerounais ?
- A4. **Qui est juridiquement le signataire** : le maire en tant que personne
  physique, ou la commune en tant qu'institution ? *Cette réponse détermine si
  le certificat est nominatif, et donc toute la gestion des clés et la conduite
  à tenir lors d'un changement de maire.*
- A5. Un acte signé par un maire reste-t-il valable après la fin de son mandat ?
- A6. Comment un tiers — banque, employeur, administration — vérifie-t-il
  l'authenticité d'un acte présenté ? Un service de vérification publique
  doit-il être fourni par la plateforme ?
- A7. Une conservation sous forme papier reste-t-elle obligatoire en parallèle ?
- A8. Un horodatage qualifié est-il requis ?
- A9. Quelle est la durée de conservation légale de la preuve de signature, et
  que se passe-t-il lorsque le certificat expire ?

---

## Bloc B — Protection des données personnelles et biométrie

**Conditionne les jalons 3 et 6, et le paramétrage de la rétention.**

- B1. Quelle autorité est compétente en matière de protection des données
  personnelles au Cameroun, et une déclaration ou autorisation préalable de ce
  traitement est-elle requise ?
- B2. **Une photographie de visage prise en direct constitue-t-elle une donnée
  biométrique** au sens du droit applicable ? Si oui, quel régime particulier
  s'y attache ?
- B3. Quelle est la **durée de conservation autorisée** du selfie et de la photo
  de pièce d'identité ? *Cette réponse est directement paramétrable dans le
  système (`request_attachments.purge_after`) — je ne peux pas la deviner.*
- B4. La conservation reste-t-elle autorisée après délivrance de l'acte, et
  pour quelle finalité — preuve, contrôle, contentieux ?
- B5. Quelles mentions d'information doivent figurer avant la collecte ? Quel
  consentement doit être recueilli, et sous quelle forme prouvable ?
- B6. Le citoyen dispose-t-il d'un droit d'accès, de rectification et
  d'effacement ? **Comment le concilier avec le journal d'audit en ajout seul**,
  qui ne peut par construction ni être modifié ni être effacé ? *C'est une
  tension réelle entre deux exigences du brief, et je ne peux pas la trancher
  seul.*
- B7. Les données peuvent-elles être hébergées hors du territoire national ?
  *Réponse déterminante si le projet quitte un jour l'exécution locale.*
- B8. Le chiffrement des données au repos est-il une obligation, une
  recommandation, ou sans exigence particulière ?
- B9. Quelles obligations en cas de violation de données — notification, délai,
  destinataire ?
- B10. La collecte de l'identité, de la nationalité et de l'adresse des parents
  est-elle proportionnée à la finalité ? *Ces champs viennent du prototype et
  élargissent sensiblement l'assiette de données personnelles (audit §12.4).*

---

## Bloc C — Accès aux bases de la police et de l'état civil

**Bloque le jalon 4 dans sa forme réelle ; les adaptateurs factices permettent
d'avancer sans réponse.**

- C1. À quelles conditions légales une plateforme peut-elle interroger la base
  d'identité de la police ? Une convention est-elle nécessaire, et avec qui ?
- C2. Idem pour la base de l'état civil.
- C3. Un officier d'état civil est-il **habilité** à consulter la base de la
  police, ou cette consultation doit-elle passer par un agent de police ?
  *Cette réponse peut modifier le parcours du §5.3.*
- C4. Une traçabilité particulière de ces consultations est-elle imposée par le
  tiers, au-delà de notre propre journal ?
- C5. Le citoyen doit-il être informé que son numéro de pièce est vérifié
  auprès de la police ?
- C6. Quelles restrictions s'appliquent à la conservation des réponses
  obtenues ? *Notre modèle les conserve dans `verification_steps.payload` —
  cette conservation doit être validée.*

---

## Bloc D — Compétence, procédure et frais

- D1. Quel texte encadre la procédure de réédition d'un acte de naissance perdu
  ou détérioré ? La procédure numérique doit-elle reproduire exactement la
  procédure papier ?
- D2. **Le maire est-il l'autorité signataire compétente**, ou l'officier
  d'état civil peut-il signer seul ? *Toute l'architecture anti-fraude du §4.3
  repose sur la double intervention officier puis maire — si le droit dit
  autre chose, la machine à états change.*
- D3. Quelle commune est compétente : celle de naissance, celle de résidence,
  ou indifféremment ?
- D4. Une pièce d'identité est-elle exigée, et laquelle ? Que fait-on d'un
  demandeur sans pièce d'identité — cas qui n'est pas marginal pour une
  personne ayant perdu son acte de naissance ?
- D5. Un tiers peut-il demander un acte pour autrui — parent, tuteur,
  mandataire ? *Le système actuel ne le prévoit pas.*
- D6. Comment traite-t-on la demande concernant un mineur ?
- D7. Quel est le tarif officiel, sur quelle base réglementaire, et qui
  encaisse ? *Le prototype affichait 20 000 CFA, valeur non vérifiable qui n'est
  reprise nulle part (D-003).*
- D8. Un délai légal de traitement est-il opposable ? *Le §8.1 prévoit
  d'afficher au citoyen un délai indicatif — je ne mettrai aucun chiffre tant
  qu'il n'est pas fondé.*
- D9. Quelles voies de recours en cas de rejet, et doivent-elles apparaître
  dans l'interface ?

---

## Bloc E — Accessibilité et langues

- E1. Une norme d'accessibilité est-elle imposée aux services publics
  numériques ? *À défaut, je vise WCAG 2.1 AA, conformément au §8.4 du brief.*
- E2. Le bilinguisme français / anglais est-il une obligation légale pour un
  service public en ligne ? *L'i18n est prévue dès le départ, mais l'ampleur de
  l'effort de traduction dépend de la réponse.*
- E3. Des mentions légales obligatoires doivent-elles figurer sur l'acte produit
  ou dans l'interface ?

---

## Ce que je fais en attendant les réponses

| Sujet | Comportement actuel |
|---|---|
| Signature | `FakeSignatureProvider`, document **portant en clair la mention qu'il est sans valeur juridique** |
| Rétention des images | Champ `purge_after` présent, **aucune durée par défaut codée** ; la purge est écrite mais non planifiée tant que B3 n'a pas de réponse |
| Tarif | Aucun montant nulle part |
| Délai indicatif au citoyen | Aucun chiffre affiché |
| Bases externes | Adaptateurs factices, aucune forme d'API présupposée |
| Consentement | Champ `consent_given_at` présent ; **le texte du consentement n'est pas rédigé** — il relève de B5 |

Aucun de ces choix n'est un contournement : ce sont des emplacements réservés,
visibles, qui échouent bruyamment plutôt que de produire silencieusement
quelque chose de faux.
