# Intégrations externes

> **Règle absolue du projet (§9) : on ne code jamais contre une API qu'on n'a
> pas documentée.** Ce fichier distingue systématiquement ce qui est *supposé*
> de ce qui est **À CONFIRMER**, avec la question exacte à poser au tiers.
> Rien de ce qui suit n'est une description d'une API réelle : je n'ai accès à
> aucune documentation de ces quatre systèmes.

- **Date :** 2026-09-06

---

## 1. Principe commun

Quatre contrats dans `app/Contracts/`, chacun avec deux implémentations :

| Contrat | Adaptateur factice | Squelette réel |
|---|---|---|
| `IdentityLookupProvider` | `FakeIdentityLookupProvider` | `PoliceIdentityLookupProvider` |
| `CivilRegistryProvider` | `FakeCivilRegistryProvider` | `NationalCivilRegistryProvider` |
| `SignatureProvider` | `FakeSignatureProvider` | `AccreditedSignatureProvider` *(squelette)* |
| `PaymentProvider` | `FakePaymentProvider` | *(prestataire non identifié)* |

**Le squelette réel lève une exception explicite** tant qu'il n'est pas
implémenté — jamais un retour muet, jamais une valeur par défaut optimiste.
Un adaptateur non implémenté qui renverrait « correspondance trouvée » serait
exactement le chemin par lequel un acte frauduleux sort du système.

Sélection par variable d'environnement, liaison dans un *service provider*.
Défaut en local : les adaptateurs factices.

### Réponse normalisée

Tout adaptateur renvoie un objet portant au minimum :

| Champ | Valeurs |
|---|---|
| `outcome` | `match` \| `no_match` \| `inconclusive` \| `unavailable` |
| `payload` | données utiles, sans champ superflu |
| `provider` | identifiant de l'adaptateur |
| `queried_at` | horodatage |
| `correlation_id` | pour rapprocher avec les journaux du tiers |

`unavailable` est un résultat de premier rang, pas une exception à rattraper
n'importe où. Le §9 l'exige : l'indisponibilité d'une base externe ne doit
jamais bloquer l'officier sans explication. Elle est enregistrée dans
`verification_steps.result` et l'écran propose une relance explicite.

### Règles transverses

- **Délai d'expiration strict** sur chaque appel, et il est court : l'officier
  attend devant son écran.
- **Aucune donnée d'identité dans les journaux applicatifs** (garde-fou n°6).
  On journalise le `correlation_id`, jamais le numéro de pièce.
- **Aucune décision automatique.** Un `match` ne vaut pas acceptation : il
  informe l'officier, qui décide. Le §4.3 l'impose.
- Toute réponse est persistée dans `verification_steps.payload` pour être
  reconstituable *a posteriori*.

---

## 2. DGSN — `IdentityLookupProvider`

*Délégation Générale à la Sûreté Nationale : l'administration qui délivre la
carte nationale d'identité, et l'acteur « GDNS » du diagramme de cas
d'utilisation (D-046).*

**Usage :** étape 2 de la vérification (§5.3) — contrôler le numéro de pièce.

**Ce que je suppose :** on soumet un numéro de pièce, éventuellement un nom, et
on obtient une confirmation d'existence avec le nom porté par la pièce. Le
prototype simulait exactement cela (`policeMatch: { nameOnId, match }`).

**Ce que je ne sais pas — À CONFIRMER :**

1. **Une interface machine existe-t-elle seulement ?** Si la vérification se
   fait aujourd'hui par téléphone, par courrier ou par consultation d'un
   terminal dédié, l'adaptateur ne modélise pas un appel synchrone mais **une
   réponse humaine différée**. Cela changerait le modèle de données et
   l'ergonomie de l'officier. *C'est la question la plus structurante des
   quatre intégrations.*
2. Quelle autorité expose ce service, et sous quelle convention ?
3. Protocole, authentification, format des identifiants ?
4. Quels types de pièces sont couverts — CNI seule, passeport, récépissé ?
5. La réponse comprend-elle une photographie ? Si oui, sa conservation est-elle
   autorisée, et pour combien de temps ?
6. Existe-t-il un environnement de test avec des identités fictives ?
7. Quel volume d'appels est autorisé, et à quel coût ?
8. Quel engagement de disponibilité ? Que fait le service en cas de panne
   prolongée ?

**Jeux de test de l'adaptateur factice :** correspondance exacte ·
correspondance avec écart d'orthographe sur le nom · aucune correspondance ·
pièce déclarée volée · service indisponible · dépassement du délai · réponse
malformée.

---

## 3. Base de l'état civil — `CivilRegistryProvider`

**Usage :** étape 4 (§5.3) — retrouver l'acte d'origine.

**Ce que je suppose :** on recherche par nom, date et lieu de naissance,
éventuellement par numéro d'acte, et on obtient zéro, un ou plusieurs actes.

**Ce que je ne sais pas — À CONFIRMER :**

1. **Les registres sont-ils numérisés ?** S'ils sont sur papier dans les
   centres, l'étape 4 n'est pas une recherche automatisée mais **la saisie par
   l'officier du résultat d'une consultation physique**. Le système doit alors
   enregistrer une déclaration d'agent, pas une réponse de service.
2. La recherche est-elle nationale ou limitée au centre d'enregistrement ?
3. Comment sont gérés les homonymes et les résultats multiples ?
4. Que retourne le service pour un acte détruit ou introuvable — cas qui est
   pourtant la raison d'être de la plateforme ?
5. Format et unicité du numéro d'acte d'origine ?
6. La réédition doit-elle être **inscrite en retour** dans le registre
   d'origine ? Si oui, c'est une écriture, pas une lecture, avec toutes les
   conséquences de sécurité que cela emporte.
7. Environnement de test ?

**Jeux de test :** acte unique trouvé · plusieurs homonymes · aucun résultat ·
acte marqué détruit · centre hors périmètre · service indisponible ·
dépassement du délai.

---

## 4. Signature électronique — `SignatureProvider`

**Usage :** jalon 5, transitions T7 et T9 (`STATE_MACHINE.md`).

**Ce que je supposais — et ce que la construction a démenti.** Le contrat
suppose qu'on soumet un document et qu'on obtient un document signé **dans le
même appel**. Docusign ne fonctionne pas ainsi : on crée une enveloppe, la
plateforme la traite, puis on récupère le document. Même avec un sceau
électronique — le seul mode sans intervention humaine — il faut créer,
attendre, télécharger. Et `ActIssuanceService::issue()` appelle `sign()` **à
l'intérieur d'une transaction de base de données** : y attendre un service
étranger tiendrait des verrous ouverts pendant tout le délai.

La suite exigera donc un **flux en deux temps** : la décision du maire demande
la signature et place la demande en attente ; un travail de file récupère
l'acte signé et achève la transition. C'est un changement de machine à états,
qui ne sera pas fait avant que la question 1 ci-dessous ait une réponse. Voir
D-066.

**Ce que je ne sais pas — et c'est bloquant pour le jalon 5 :**

1. **Un acte d'état civil signé électroniquement a-t-il valeur légale au
   Cameroun ?** Sans réponse, le jalon 5 produit un document sans valeur.
   Question reprise dans `docs/COMPLIANCE_OPEN_QUESTIONS.md`.
2. Quel prestataire est agréé, et par quelle autorité ?
3. Quel niveau de signature est exigé — simple, avancée, qualifiée ?
4. Qui signe juridiquement : le maire en tant que personne, ou la commune en
   tant qu'institution ? Cela détermine si le certificat est nominatif, et donc
   toute la gestion des clés.
5. Comment un tiers vérifie-t-il l'authenticité d'un acte présenté ?
6. Quelle durée de conservation de la preuve ? Que se passe-t-il à l'expiration
   du certificat — l'acte reste-t-il vérifiable ?
7. Un horodatage qualifié est-il requis ?
8. **Où les données peuvent-elles être traitées ?** Ajoutée en D-066 : faire
   transiter un acte d'état civil par un prestataire étranger revient à faire
   traiter des données d'identité hors du pays. Question absente de cette liste
   jusque-là, alors qu'elle conditionne le choix même du prestataire.

**Ce que je fais en attendant — implémenté au jalon 5 :**
`FakeSignatureProvider` scelle l'empreinte du document par HMAC et déclare
`legallyBinding: false`. `DocumentBuilder` appose la mention
`DOCUMENT DE DEMONSTRATION - SANS VALEUR JURIDIQUE` en **première ligne** de
l'acte, la répète en fin de document et sur la preuve.

La mention est apposée par le constructeur du document et **non** par
l'adaptateur : elle doit figurer même si quelqu'un contourne celui-ci. Un test
relit le PDF avec `pdftotext` et exige qu'elle soit la première ligne non vide.

Voir D-025.

`document_signatures.document_hash` (SHA-256) est enregistré dès maintenant :
il ne dépend d'aucun prestataire et servira quel que soit le choix final.

**Le client Docusign, lui, est construit** (D-066) : authentification JWT
Grant, lecture du compte et de son domaine d'hébergement, liste des sceaux,
création d'enveloppe, statut, téléchargement. Contrat relevé dans le code
publié par Docusign, vérifié contre un **serveur simulé** — aucun appel n'a été
fait contre le service réel. `DocusignSignatureProvider::sign()` lève, et lève
**avant tout appel** : aucune enveloppe n'est créée en passant.

Deux questions ci-dessus deviennent vérifiables sans devenir tranchées.
`DocusignClient::account()` rend le domaine d'hébergement du compte, donc la
région où les données seraient traitées (question 8). Et le flux
JWT Grant agit **au nom d'un utilisateur** : c'est lui qui apparaît comme
expéditeur de l'enveloppe — ce qui donne à la question 4 une conséquence
technique immédiate, la configuration `PHOENIX_DOCUSIGN_USER_ID`.

Une exigence contractuelle s'ajoute à la liste : **le compte doit disposer d'un
sceau électronique provisionné**. Il se souscrit auprès de Docusign, il ne se
crée pas par l'API.

---

## 5. Paiement — `PaymentProvider`

**Construit au jalon 7 — mais rien n'est encaissé.** Le contrat, les
adaptateurs, la table `payments`, l'écran du demandeur et le reçu existent.
**Aucun montant n'est codé** : le tarif est une donnée de configuration sans
valeur par défaut, et son absence fait *refuser* l'encaissement (D-039).

Le placement de la barrière — avant l'envoi, ou avant la signature — est un
**réglage**, et les deux sont implémentés et testés (D-041). Le défaut est
`none` : tant que les questions ci-dessous n'ont pas de réponse, la plateforme
n'encaisse rien.

**Ce que l'adaptateur factice fait :** il simule. Il ne représente aucun
opérateur réel et n'encaisse rien. Le reçu qu'il produit porte en première
ligne « RECU DE DEMONSTRATION — AUCUNE SOMME N'A ETE ENCAISSEE ».

**Ce que l'adaptateur réel fait :** il lève une exception nommant les six
questions. Un encaissement qui « marche » sans encaisser est la pire
défaillance possible ici — le citoyen croit avoir payé.

### 5.1 Les deux opérateurs

Le diagramme fait de « Make Payment » deux spécialisations : **Pay Through
Orange Money** et **Pay Through Mobile Money**. Le demandeur choisit, le choix
est conservé avec l'encaissement, et il figure au reçu — un rapprochement
comptable se fait par opérateur.

> **Hypothèse signalée :** au Cameroun, « Mobile Money » désigne couramment le
> service de MTN, par opposition à Orange Money. C'est ainsi que je l'ai lu.
> Si un troisième opérateur est visé, `App\Enums\PaymentOperator` est l'unique
> endroit à changer.

**Ce qui n'est pas encodé :** les préfixes de numéro de chaque opérateur. Les
deviner reviendrait à inventer une règle métier, et un numéro mal classé ferait
échouer un règlement sans que personne comprenne pourquoi. C'est l'opérateur
qui reconnaît ses propres numéros.

### 5.2 HR-Skills Pay — l'agrégateur

**Nommé et implémenté** (D-050). Contrat relevé dans la documentation publiée
par le prestataire sur `hrskills-pay.com`.

| | |
|---|---|
| Base | `https://api.hrskills-pay.com` — bac à sable : chemins préfixés `/sandbox` |
| Authentification | `POST /v1/auth/transaction-token` — `Authorization: Bearer <clé A>`, corps `{"api_secret": "<clé B>"}` → jeton valable 45 min |
| Encaissement | `POST /api/v1/payin/mobile-money` — en-têtes `Authorization`, `X-Transaction-Token`, `Idempotency-Key` ; corps `{operator, country, phone_number, amount, currency}` |
| Rapprochement | `GET /v1/payments/{reference}` |
| Rappel | `X-Hub-Signature: sha256=<HMAC du corps brut>`, `X-Webhook-Event`, événements `payment.succeeded` / `payment.failed` |
| Opérateurs | `ORANGE`, `MTN` |
| Devise / pays | `XAF` / `CM` — montant **entier**, ce qui correspond à `Money` |

> **⚠ AUCUN APPEL N'A ÉTÉ FAIT CONTRE LE SERVICE RÉEL.** Sans identifiants,
> l'adaptateur n'est vérifié que contre un serveur simulé. Ces tests prouvent
> que **notre code suit la documentation**, pas que **le prestataire suit sa
> propre documentation**. La reprise contre le bac à sable, avec de vraies
> clés, reste à faire.

**Ce que l'implémentation refuse de faire :**

- **Croire un rappel sur parole.** Un rappel signé prouve l'origine, pas la
  fraîcheur du contenu. On y lit une référence, puis on **re-interroge** le
  prestataire. Un rappel qui annonce « payé » alors que l'API dit « en
  attente » ne paie rien — et c'est testé.
- **Accepter un rappel sans secret configuré.** Le mode ouvert serait ici une
  porte vers des actes non payés.
- **Traiter un statut inconnu comme un paiement.** Tout ce qui n'est pas
  explicitement un succès vaut « en attente ».
- **Recopier la réponse du prestataire en base.** Liste blanche de champs :
  `status`, `reference`, `net_amount`, `fee`, `amount`, `currency`,
  `operator`. Un numéro de téléphone rendu dans la réponse n'entre pas.
- **Bricoler un remboursement.** Aucun remboursement d'encaissement n'est
  documenté ; un décaissement (`/api/v1/payout/mobile-money`) est une
  opération distincte, sans lien comptable avec l'encaissement d'origine.
  L'adaptateur lève.

**Deux questions nouvelles, ouvertes :**

1. **Qui supporte les frais ?** La réponse porte un `net_amount` inférieur au
   montant payé. Si le tarif est un montant réglementaire, la commune
   perçoit-elle *tarif moins frais*, ou le demandeur doit-il payer *tarif plus
   frais* ? Cela change le montant affiché au citoyen.
2. **Deux noms de domaine apparaissent** dans la documentation du prestataire :
   `api.hrskills-pay.com` (avec trait d'union, utilisé par tous les exemples de
   code) et `api.hrskillspay.com` (sans). La base est donc une donnée de
   configuration, pas une constante.

### 5.3 ⚠ Ce qui reste ouvert malgré l'agrégateur

Un agrégateur expose **une** interface pour les deux opérateurs — c'est ce que
montre le diagramme, avec un seul acteur « Payment API ». C'est aussi la forme
qu'a `PaymentProvider`.

L'agrégateur est nommé et branché, mais **le tarif ne l'est toujours pas**
(question 1). `PHOENIX_PAYMENT_AMOUNT_MINOR` reste vide, et sans lui
l'encaissement refuse de s'ouvrir — ce qui est le comportement voulu (D-039).

Restent également ouvertes : qui encaisse (question 2), le sort d'un paiement
après rejet (question 4), et les obligations de reçu (question 6).

**À CONFIRMER avant toute ligne de code :**

1. Quel est le tarif officiel d'une réédition, et sur quelle base
   réglementaire ? Le prototype affichait 20 000 CFA — **valeur que je ne peux
   pas vérifier** et qui ne sera pas reprise.
2. Qui encaisse : la commune, le Trésor, un tiers ?
3. Le paiement précède-t-il la demande, ou la signature ?
4. Que devient un paiement lorsque la demande est rejetée ? Le remboursement
   est-il prévu ? C'est la question qui pèse le plus sur le modèle de données.
5. Mobile money — quels opérateurs, quel agrégateur, quelle interface ?
6. Quelles obligations de reçu et de comptabilité ?

**Rappel de D-005 :** si la plateforme encaisse des frais et qu'elle est un jour
déployée sur Vercel, la clause d'usage non commercial du plan gratuit ne
s'applique plus.

---

## 6. Comportement en cas de panne — vue d'ensemble

| Situation | Comportement | Écran de l'officier |
|---|---|---|
| Service indisponible | `unavailable` enregistré dans l'étape | Message explicite, bouton « Réessayer », possibilité de poursuivre les autres étapes |
| Délai dépassé | idem `unavailable` | idem |
| Réponse malformée | `inconclusive`, réponse brute conservée | Invitation à relancer, puis à escalader |
| Panne prolongée | Aucune acceptation possible sans les 4 vérifications (T4) | La demande reste en `under_review` ; l'escalade au maire (T6) reste ouverte |

**Ce qu'aucune panne ne permet :** contourner une étape. T4 exige un résultat
enregistré pour les 4 vérifications (D-027) — `unavailable` **est** un résultat
enregistré, ce qui débloque la situation sans masquer le fait que la
vérification n'a pas abouti. L'officier reste responsable de sa décision, et le journal montre
exactement sur quoi elle reposait.

**Ce qu'une panne — ou un refus — ne permet plus :** accepter en silence. Dès
qu'une des quatre vérifications rend autre chose qu'une correspondance
(`no_match`, `inconclusive`, `provider_unavailable`), **le motif devient
obligatoire pour toute décision, acceptation comprise**, et l'écran du maire
affiche la réserve avant qu'il ne signe. Le pouvoir de décision de l'officier
est intact ; ce qui disparaît, c'est la possibilité de l'exercer sans le dire.
Voir D-031.

---

## 7. Synthèse des questions à poser

Par ordre d'urgence — les deux premières conditionnent la conception, pas
seulement l'implémentation.

| # | Destinataire | Question |
|---|---|---|
| 1 | Autorité de police | Une interface machine de vérification existe-t-elle, ou la vérification est-elle humaine et différée ? |
| 2 | Autorité d'état civil | Les registres sont-ils numérisés et interrogeables, ou la recherche est-elle physique ? |
| 3 | Conseil juridique | Un acte d'état civil signé électroniquement a-t-il valeur légale ? |
| 4 | Autorité compétente | Quel prestataire de signature est agréé, et à quel niveau ? |
| 5 | Les deux autorités | À quelles conditions légales l'accès à ces bases est-il ouvert ? |
| 6 | Autorité compétente | Quel est le tarif officiel, et qui encaisse ? |

**Tant que 1 et 2 sont sans réponse, je poursuis avec les adaptateurs factices**
et je ne présuppose aucune forme d'API.
