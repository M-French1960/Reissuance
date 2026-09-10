# Cas d'utilisation — traçabilité

> Le diagramme de cas d'utilisation fourni devient la **référence** du
> développement. Ce document trace chaque cas jusqu'au code, et nomme les
> points où le diagramme et le brief ne disent pas la même chose.
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
| *(absent du diagramme)* | **`UserRole::Admin`** | ⚠ voir §4.1 |
| Payment API | `PaymentProvider` | jalon 7 |
| DoctSign API | `SignatureProvider` | jalon 5 |
| Civil Registry DB | `CivilRegistryProvider` | jalon 4 |
| GDNS | `IdentityLookupProvider` ? | ⚠ voir §4.4 |
| Facial Recognition AI | **rien** | ⚠ voir §4.3 |

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

---

## 3. Cas manquants — à construire

| Cas d'utilisation | Constat | Difficulté |
|---|---|---|
| **Cancel Request** | La Policy `delete` existe **mais aucune route ne l'appelle** : T12 est inatteignable. Et le diagramme rattache l'annulation au *suivi* d'une demande, donc à une demande **déjà envoyée** — ce que la machine à états ne prévoit pas du tout. | machine à états |
| **Contact Officer** | Rien. Aucun canal du demandeur vers l'agent. | modéré |
| **Through Orange Money / Through Mobile Money** | Un seul adaptateur factice générique. Le diagramme en fait deux spécialisations, donc un choix du demandeur. | faible |
| **Generate Certificate** *(officier)* | ⚠ voir §4.2 | à clarifier |
| **Manage system settings** *(maire)* | ⚠ voir §4.1 | à trancher |

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

**Je recommande la seconde lecture** et je n'ai rien changé. Si vous voulez la
première, dites-le : c'est faisable, mais je veux que la conséquence soit
écrite avant, pas découverte après.

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

Je suis parti de la lecture 1. **À confirmer.**

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

**Ce que je refuse de construire sans réponse** : un rejet automatique fondé
sur un score biométrique.

### 4.4 ⚠ GDNS — je ne sais pas ce que c'est

Le diagramme relie « Verify Identity » à un acteur **GDNS**. Je ne connais pas
ce sigle et **je n'en inventerai pas la signification**. S'il désigne la base
d'identité nationale, il correspond à l'`IdentityLookupProvider` déjà en place
et il n'y a rien à faire. Sinon, c'est une intégration de plus, avec son
contrat et son mode dégradé.

**Question à poser :** que désigne GDNS, et quelle interface expose-t-il ?

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

1. **Cancel Request** — combler un trou réel : T12 n'était pas branché.
2. **Contact Officer** — le canal manquant entre le demandeur et l'agent.
3. **Orange Money / Mobile Money** — deux adaptateurs au lieu d'un.
4. Le reste attend vos réponses aux §4.1 à §4.4.
