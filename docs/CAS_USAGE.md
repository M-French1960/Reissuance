# Cas d'utilisation — traçabilité

> **Version 2 du diagramme.** Trois divergences signalées sur la version 1 y
> sont réglées : le maire n'a plus les réglages système, l'administrateur
> apparaît comme acteur **Super Admin**, et l'acteur de signature est nommé
> **Docusign**. Ce document suit la version 2.

> Le diagramme de cas d'utilisation fourni est la **référence** du
> développement. Ce document trace chaque cas jusqu'au code, et nomme les
> points où le diagramme et le brief ne disent pas la même chose.
>
> ## ⚠️ Ce document ne fait pas foi — le test, si
>
> **`tests/Feature/UseCaseCoverageTest.php` porte la traçabilité applicable.**
> Ce document-ci est une note que j'entretiens, et une note que son auteur
> entretient ne prouve rien : celle-ci a déjà divergé, listant en §3 comme
> « à construire » trois cas que son propre §2 donnait pour faits (D-061).
>
> Le test attache chaque cas du diagramme à ses routes nommées et à l'acteur
> qui l'exerce. Si un cas devient inatteignable — route supprimée, renommée,
> fermée au mauvais rôle — la suite échoue. Éprouvé dans les deux sens.
>
> Il vérifie aussi que chaque route citée est **réellement exercée par un
> test** : c'est ainsi qu'on a découvert que `admin.users.reassign` ne l'était
> par aucun, alors que ses règles venaient d'être corrigées (D-058).
>
> Ce que je ne fais pas : trancher seul ces divergences. Le §4.2 du brief et le
> diagramme portent deux répartitions de pouvoir différentes, et une
> répartition de pouvoir n'est pas un détail d'implémentation.

---

## 1. Acteurs

| Diagramme | Dans le code | Remarque |
|---|---|---|
| Visitor | visiteur anonyme | — |
| Citizen | `UserRole::Citizen` | — |
| Civil Registry Officer | `UserRole::Officer` | — |
| Mayor | `UserRole::Mayor` | — |
| **Super Admin** | `UserRole::Admin` | ✅ présent au diagramme v2 |
| Payment API | `PaymentProvider` | jalon 7 |
| Docusign API | `SignatureProvider` → `DocusignSignatureProvider` | ✅ nommé — voir §4.6 |
| Civil Registry DB | `CivilRegistryProvider` | jalon 4 |
| GDNS | `IdentityLookupProvider` → `DgsnIdentityLookupProvider` | ✅ **tranché** — voir §4.4 |
| Facial Recognition AI | `FacialRecognitionProvider` | ✅ **construit** — voir §4.3 et `BIOMETRIE.md` |

---

## 2. Cas couverts

| Cas d'utilisation | Où | État |
|---|---|---|
| Authenticate *(inclus partout)* | `auth` + `active` + `two-factor` | ✅ |
| Create Account | Fortify, citoyens uniquement | ✅ |
| Make Request | assistant en 4 étapes | ✅ |
| Update Account Information | `citizen.profile.edit` | ✅ |
| Consult Notification | centre de notifications | ✅ jalon 6 |
| Track Request Status | `citizen.requests.show` + frise | ✅ |
| Download Certificate | `acts.document` | ✅ |
| Make Payment | `citizen.requests.payment` | ✅ jalon 7 |
| Search Registry | étape 4 de la vérification | ✅ |
| Manage Request | file + décision de l'officier | ✅ |
| Verify Identity | les 4 vérifications | ✅ |
| — Accept Request | T4 | ✅ |
| — Reject Request | T5 | ✅ |
| Escalate Case | T6 | ✅ |
| Review Escalated Case | tableau du maire | ✅ |
| Sign Certificate | T7 / T9 | ✅ |
| — Send Certificate to Officer | T8 / T11 | ✅ |
| Cancel Request | T13 / T14 | ✅ D-044 |
| Contact Officer | fil du dossier | ✅ D-047 |
| Manage Accounts *(Super Admin)* | `admin.users.*` | ✅ |
| Verify Identity → Facial Recognition API | `FacialRecognitionProvider` | ✅ D-045 |

---

## 3. Cas manquants — à construire

> Mis à jour le 2026-09-11. Cette section listait encore comme manquants trois
> cas que le §2 du **même document** donnait pour faits. Une documentation qui
> se contredit fait perdre plus de temps qu'elle n'en fait gagner.

| Cas d'utilisation | Constat | Difficulté |
|---|---|---|
| ~~Manage system settings~~ | **Fait** (D-060) — écran d'administration en **consultation seule** : le rendre modifiable permettrait de basculer un prestataire sur l'adaptateur factice, donc de faire délivrer des actes sans vérification réelle. | — |
| ~~Cancel Request~~ | **Fait** (D-044) — T13 et T14, avec les deux états terminaux correspondants. | — |
| ~~Contact Officer~~ | **Fait** (D-047) — fil d'échanges porté par le dossier. | — |
| ~~Through Orange Money / Through Mobile Money~~ | **Fait** — colonne `operator` (migration `2026_01_10_000100`), les deux opérateurs sont proposés au demandeur sur l'écran de paiement. | — |
| ~~Generate Certificate~~ | tranché : déjà couvert par T4 puis T8 (§4.2) | — |

### 3.1 Ce qui bloque, et qui n'est pas un écran

Quatre adaptateurs sur cinq sont des squelettes qui lèvent — DGSN, registre
national d'état civil, signature Docusign, reconnaissance faciale. **Aucun
n'est bloqué par du code** : ils attendent les réponses du §7 de
`docs/INTEGRATIONS.md`. Seul HR-Skills Pay est implémenté, et n'a jamais été
appelé contre le service réel.

---

## 4. Divergences entre le diagramme et le brief

### 4.1 ⚠ Le diagramme n'a pas d'administrateur, et donne les réglages au maire

Le diagramme confie **« Manage system settings » au maire** et ne comporte
aucun acteur administrateur.

Le §4.2 du brief pose l'inverse, et c'est le point sur lequel j'ai le plus
insisté depuis le jalon 2 : **l'administrateur gouverne les comptes et ne voit
aucun dossier ; le maire décide des dossiers et ne gouverne aucun compte.** Le
test R8 vérifie qu'un administrateur ne voit aucune demande, dans les sept
états. La séparation est écrite dans `PERMISSIONS.md` et appliquée par une
portée globale.

Les deux lectures s'opposent frontalement :

- **Suivre le diagramme** : supprimer le rôle administrateur, donner au maire
  la création de comptes et les réglages. Le maire cumulerait alors le pouvoir
  de créer les comptes officiers **et** celui de signer les actes — c'est-à-dire
  qu'il pourrait se créer un officier complice. C'est exactement le cumul que
  le §4.2 sépare.
- **Suivre le brief** : garder l'administrateur, et lire « Manage system
  settings » comme un cas porté par un acteur que le diagramme a omis.

**TRANCHÉ : l'administrateur est conservé.** La séparation du §4.2 tient. « Manage
system settings » devient donc un cas de **l'administrateur**, et non du maire :
un maire qui pourrait créer les comptes officiers et signer les actes cumulerait
les deux pouvoirs que la plateforme sépare.

### 4.2 ⚠ « Generate Certificate » du côté de l'officier

Aujourd'hui, l'acte est **produit au moment de la signature du maire**
(jalon 5) : il n'existe aucun document avant la décision de signer. Le
diagramme place « Generate Certificate » chez l'officier.

Deux lectures :

1. L'officier **prépare** le document que le maire signera ensuite. C'est déjà
   ce que fait l'option A retenue pour T8, et cela ne change rien au fond.
2. L'officier **produit** le certificat, le maire ne fait que l'apposer. Cela
   déplacerait la production du document avant la décision de signer — et
   créerait un document existant sans décision du maire, ce que le §4.3
   interdit.

**TRANCHÉ : lecture 1.** L'acte naît de la signature du maire, et « Generate
Certificate » est la préparation du dossier par l'officier — ce que fait déjà
la transition T4, puis l'option A de T8 lorsque le maire renvoie un dossier
prêt à signer.

C'est la seule lecture compatible avec le §4.3 : un document existant avant la
décision du maire serait un acte en attente de tampon, et non un acte que le
maire décide de délivrer. La différence n'est pas théorique — c'est elle qui
fait qu'aucun raccourci ne produit d'acte signé.

### 4.3 ⚠ Reconnaissance faciale — le point le plus lourd du diagramme

Le diagramme ajoute un acteur **Facial Recognition AI** relié à
« Verify Identity ». Ce n'est pas une intégration de plus : c'est un
**traitement biométrique**, et il soulève des questions auxquelles je n'ai pas
de réponse et que je ne peux pas inventer :

1. Quelle base légale autorise un traitement biométrique dans une démarche
   d'état civil au Cameroun ?
2. Le consentement du demandeur est-il requis, et un refus le prive-t-il de son
   acte ?
3. Quel taux de faux négatifs, et **que devient une personne que la machine ne
   reconnaît pas** ? Un acte d'état civil conditionne l'accès à presque tout :
   un faux négatif non rattrapable est une exclusion administrative.
4. Combien de temps le gabarit biométrique est-il conservé, et où ?
5. Qui répond d'une erreur — l'officier, le fournisseur, la commune ?

**Ce que je propose techniquement**, si la décision est prise : la
reconnaissance faciale devient un **cinquième résultat de vérification qui
assiste l'officier sans jamais décider à sa place**, exactement comme la base
de la police aujourd'hui. Un `no_match` n'interdit pas l'acceptation, il rend
le motif obligatoire (D-031). Un être humain reste responsable.

**TRANCHÉ : la biométrie est obligatoire.** Elle est construite, et le
rapprochement est exigé avant que l'officier ne puisse conclure sur l'étape 3.

Les garde-fous tiennent dans le code, pas dans une intention : la machine rend
un **avis** et ne décide pas ; une personne qu'elle ne reconnaît pas n'est
**jamais bloquée** ; aucun gabarit biométrique n'est conservé ; le journal ne
porte que l'issue. Le détail, les tests qui les protègent et les cinq questions
qui restent ouvertes pour une mise en service réelle sont dans
`docs/BIOMETRIE.md`.

**Ce qui n'existe nulle part dans le code** : un seuil au-delà duquel une
demande serait automatiquement refusée.

### 4.4 ⚠ GDNS — je ne sais pas ce que c'est

Le diagramme relie « Verify Identity » à un acteur **GDNS**. Je ne connais pas
ce sigle et **je n'en inventerai pas la signification**. S'il désigne la base
d'identité nationale, il correspond à l'`IdentityLookupProvider` déjà en place
et il n'y a rien à faire. Sinon, c'est une intégration de plus, avec son
contrat et son mode dégradé.

**TRANCHÉ : GDNS = DGSN**, la Délégation Générale à la Sûreté Nationale —
l'administration camerounaise qui délivre la carte nationale d'identité
(vérifié : `https://www.dgsn.cm/`). C'est exactement l'acteur que
`IdentityLookupProvider` modélisait déjà sous le nom de « base de la police ».
L'adaptateur réel s'appelle désormais `DgsnIdentityLookupProvider`.

**Attention à ne pas confondre deux tarifs :** la DGSN annonce des frais pour
la **CNI**. Ce n'est pas le tarif d'une **réédition d'acte d'état civil**, qui
reste inconnu (question 1 d'`INTEGRATIONS.md` §5). Aucun montant n'est repris
de l'un pour l'autre.

### 4.6 Docusign — le produit est nommé, la question juridique reste

Le diagramme v2 nomme **Docusign**. L'API eSignature REST existe et est
documentée : elle travaille par « enveloppes » contenant documents et
destinataires (`developers.docusign.com`). L'adaptateur réel s'appelle
désormais `DocusignSignatureProvider`.

**Ce que le nom du produit ne règle pas**, et qu'il ne faut pas confondre :

1. **La valeur juridique.** Qu'une signature soit techniquement valide ne dit
   pas qu'un acte d'état civil camerounais signé ainsi **fait foi**. C'est la
   question A1, toujours ouverte. Tant qu'elle l'est, la mention « SANS VALEUR
   JURIDIQUE » reste sur tout acte produit (D-025).
2. **La résidence des données.** Docusign est un service commercial étranger.
   Y faire transiter un acte d'état civil appelle une décision explicite sur le
   lieu de traitement et sur ce que le prestataire conserve.
3. **Le compte signataire.** La commune, ou le maire nominativement ? Un
   changement de maire invalide-t-il les actes antérieurs ? La réponse
   détermine le modèle d'habilitation.

L'adaptateur **lève une exception** nommant ces trois points plutôt que de
rendre un document « signé ».

---

### 4.5 Annuler une demande déjà envoyée

Le diagramme rattache « Cancel Request » au **suivi** d'une demande, donc à une
demande envoyée. La machine à états n'a aucune sortie de ce genre : `T12` ne
supprime qu'un brouillon, et il n'est même pas branché.

Cela ouvre trois questions :

1. Jusqu'où peut-on annuler ? Une fois qu'un officier a pris le dossier, une
   annulation jette son travail.
2. Un acte déjà signé ne peut évidemment plus être annulé — `signed` est
   terminal et le restera.
3. **Et les frais déjà réglés ?** C'est la question 4 d'`INTEGRATIONS.md` §5,
   toujours ouverte.

**Ce que j'implémente**, en le disant : l'annulation est possible tant que
**personne n'a pris le dossier en charge** — donc en `draft` et en `pending`.
Au-delà, le demandeur passe par « Contact Officer », qui est précisément
l'autre cas du diagramme. Ce choix ne fait que restreindre ; il sera facile de
l'élargir si vous le décidez.

---

## 5. Ordre de travail retenu

1. ~~**Cancel Request**~~ — fait (D-044).
2. ~~**Facial Recognition**~~ — fait (D-045, `BIOMETRIE.md`).
3. ~~**GDNS**~~ — identifié : DGSN.
4. ~~**Contact Officer**~~ — fait (D-047).
5. ~~**Orange Money / Mobile Money**~~ — fait, colonne `operator`.
6. ~~**Manage system settings**~~ — fait (D-060), en consultation seule.

> **Tous les cas d'utilisation du diagramme sont couverts.** Ce qui reste n'est
> plus un écran à construire : quatre adaptateurs sur cinq sont des squelettes,
> et aucun n'est bloqué par du code. Voir §3.1 et le §7 de
> `docs/INTEGRATIONS.md`.
