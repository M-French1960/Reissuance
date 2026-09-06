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
| `SignatureProvider` | `FakeSignatureProvider` | *(prestataire non identifié)* |
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

## 2. Base de la police — `IdentityLookupProvider`

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

**Ce que je suppose :** on soumet un document ; on obtient un document signé et
une preuve vérifiable.

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

**Ce que je fais en attendant :** `FakeSignatureProvider` produit un PDF et une
preuve **explicitement marquée comme non valable juridiquement**, en clair sur
le document lui-même. Aucun document produit par l'adaptateur factice ne doit
pouvoir être confondu avec un acte authentique. C'est une exigence de sécurité,
pas une précaution de développement.

`document_signatures.document_hash` (SHA-256) est enregistré dès maintenant :
il ne dépend d'aucun prestataire et servira quel que soit le choix final.

---

## 5. Paiement — `PaymentProvider`

**Hors périmètre jusqu'au jalon 7** (D-003). Le contrat est défini, la table
`payments` n'est pas créée, **aucun montant n'est codé**.

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
| Panne prolongée | Aucune acceptation possible sans les 5 étapes (T4) | La demande reste en `under_review` ; l'escalade au maire (T6) reste ouverte |

**Ce qu'aucune panne ne permet :** contourner une étape. T4 exige un résultat
enregistré pour les 5 étapes — `unavailable` **est** un résultat enregistré, ce
qui débloque la situation sans masquer le fait que la vérification n'a pas
abouti. L'officier reste responsable de sa décision, et le journal montre
exactement sur quoi elle reposait.

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
