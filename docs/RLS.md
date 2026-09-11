# Sécurité au niveau des lignes — évaluation mesurée, puis rendue caduque

> ## ⚠️ Ce document ne décrit plus une option disponible
>
> **Depuis le passage à MySQL (D-051), la recommandation de ce document ne
> peut plus être suivie : MySQL n'a pas de sécurité au niveau des lignes.**
> Il n'existe aucun équivalent de `CREATE POLICY` ni de `ROW LEVEL SECURITY`.
> Ce n'est pas une différence de syntaxe à contourner, c'est une
> fonctionnalité absente.
>
> Le document est conservé pour trois raisons : les mesures du §3 restent
> vraies et instructives ; le défaut qu'il décrit au §1 est réel et n'a pas
> disparu ; et la recommandation redeviendrait applicable telle quelle si le
> projet revenait à PostgreSQL.
>
> **Ce qui reste à trancher aujourd'hui est au §7.**

---

> Jalon 6. D-011 avait reporté ce sujet ici, « après évaluation du coût ».
> Le prototype a été **construit et exécuté** : les chiffres ci-dessous sont
> mesurés, pas estimés. Le code est conservé dans `docs/prototypes/rls/`,
> et il est **spécifique à PostgreSQL**.

---

## 1. La question

L'application restreint déjà l'accès aux demandes à trois niveaux : la Policy,
le middleware de rôle, et une portée globale Eloquent
(`RequestVisibilityScope`) qui s'applique à **toute** requête sans qu'un appel
ait à y penser.

La question est de savoir si PostgreSQL doit refuser en plus, de son côté.

**Ce n'est pas une question théorique dans ce projet.** Au jalon 2, la portée
globale a **disparu silencieusement** : Pint a retiré les `use` en tête du
modèle, l'attribut `#[ScopedBy(...)]` pointait vers des classes inexistantes,
et **aucune erreur PHP n'a été levée**. Toutes les requêtes ont rendu toutes
les demandes, tous centres confondus, jusqu'à ce qu'un test de refus le voie
(D-013). C'est exactement le défaut qu'une politique en base rattrape.

---

## 2. Le prototype

Trois politiques sur `reissuance_requests` — lecture, insertion, mise à jour —
qui transcrivent `RequestVisibilityScope`. Le rôle applicatif étant **unique**
(`phoenix_app` pour tous les utilisateurs), elles s'appuient sur des variables
de session que l'application poserait à chaque requête.

Deux traits importants :

- **Le défaut est le refus.** Sans contexte posé, on ne voit **rien**. Une
  politique dont le cas par défaut ouvrirait l'accès serait pire qu'aucune
  politique.
- **Un contexte `system`** existe pour les travaux de fond — file d'attente,
  commandes, semences — qui n'ont aucun utilisateur authentifié.

---

## 3. Ce qui a été mesuré

### 3.1 La protection est réelle

Un test reproduit le défaut de D-013 : la portée globale entièrement
contournée (`withoutGlobalScopes()`), un officier du centre A, deux demandes en
base — une par centre.

| Configuration | Ce que l'officier du centre A voit |
|---|---|
| Sans politique | **2 sur 2** — la fuite |
| Avec politique | **1 sur 2** — contenue |

Une écriture hors périmètre est refusée de la même façon : `UPDATE` d'un
citoyen sur la demande d'un autre affecte **0 ligne**.

### 3.2 Le coût en performance est négligeable

Sur 515 demandes, six exécutions de la requête de la file :

| | Exécution | Planification |
|---|---:|---:|
| Sans politique | 0,47 à 0,76 ms | 0,47 à 0,71 ms |
| Avec politique | 0,37 à 0,43 ms | 0,67 à 0,76 ms |

Environ **+0,2 ms de planification**, l'exécution restant dans le bruit de
mesure. À cette échelle, la performance n'est pas un argument contre.

### 3.3 Le coût réel est ailleurs, et il est chiffré

Avec une politique naïve (`FOR ALL`, sans contexte `system`) :
**157 tests sur 340 en échec** — chaque insertion sans contexte est refusée.

Avec la politique affinée (lecture / insertion / mise à jour séparées, plus le
contexte `system`) et ce contexte posé dans le harnais de test :
**340 sur 340 au vert**.

> **Ce second chiffre ne prouve pas que RLS fonctionne.** Il prouve que la
> politique n'empêche pas la suite de tourner. Les tests passent parce qu'ils
> s'exécutent en `system`, c'est-à-dire **en contournant la politique**. Écrire
> des tests qui posent un vrai contexte est un travail à part entière, et c'est
> le seul qui prouverait quelque chose.

---

## 4. Ce que RLS protège, et ce qu'il ne protège pas

**Protège de :** un `where` oublié, une portée globale retirée par un
refactoring ou par un formateur de code, un contrôleur nouvellement écrit qui
oublie la Policy, une requête brute dans une commande.

**Ne protège pas de :** un attaquant capable d'exécuter du SQL arbitraire avec
le rôle applicatif — il poserait lui-même les variables de session. Le rôle
étant unique, RLS est une défense contre **les défauts du code**, pas contre un
attaquant au niveau base. C'est déjà beaucoup : les trois défauts de visibilité
rencontrés dans ce projet venaient tous du code.

---

## 5. Ce qu'il faudrait faire pour l'adopter

La migration ne suffit pas. Il faut aussi :

1. **Un middleware** qui pose les quatre variables de session à chaque requête
   authentifiée — rôle, identifiant, centre, commune.
2. **Un contexte `system`** posé explicitement par le worker de file, les
   commandes Artisan et les semences.
3. **Des tests qui posent un vrai contexte**, sans quoi la politique n'est
   jamais exercée (§3.3).
4. **Une contrainte d'exploitation documentée et tenue :** avec un mutualiseur
   de connexions en mode transaction (pgbouncer), une variable de session qui
   survit à la transaction devient une fuite **entre utilisateurs**. Il faudrait
   alors poser le contexte avec `SET LOCAL` à l'intérieur de chaque transaction.
   C'est le risque le plus sérieux de l'adoption : mal fait, RLS **crée** la
   fuite qu'il devait empêcher.
5. **Étendre aux autres tables** — `request_attachments`, `verification_steps`,
   `request_decisions`, `document_signatures` — sans quoi la protection ne
   couvre que la table des demandes.

---

## 6. Recommandation

**Adopter, mais pas à la légère, et pas dans ce jalon.**

Pour : le §4.3 du brief place l'anti-fraude au premier rang, ce projet a déjà
subi exactement le défaut que RLS rattrape, la protection est démontrée et le
coût en performance est nul.

Contre une adoption immédiate : le point 4 du §5. Une politique posée sans que
la question du mutualiseur de connexions soit tranchée transformerait une
défense en une fuite entre utilisateurs. Et une politique dont les tests ne
posent pas de vrai contexte n'est pas testée — elle est seulement présente.

**Ce que je propose :** un jalon dédié, court, dont le contenu est la liste du
§5 dans l'ordre, avec le test du §3.1 comme critère d'acceptation.

**La décision vous revient** : elle engage l'exploitation, pas seulement le
code.

> **Cette recommandation est suspendue depuis D-051.** Elle supposait
> PostgreSQL. Voir le §7.

---

## 7. Après le passage à MySQL — ce qui reste, et ce qui manque

### 7.1 Ce que MySQL n'a pas

Aucune sécurité au niveau des lignes. Les contournements que l'on rencontre
en cherchant un équivalent n'en sont pas :

| Contournement proposé | Pourquoi il ne remplace pas RLS |
|---|---|
| Une vue par rôle, avec `WHERE` | L'application garde `SELECT` sur la table, donc peut la lire directement. Une vue ne restreint que qui passe par elle. |
| Une vue `SQL SECURITY DEFINER` + retrait du droit sur la table | Possible sur la lecture, mais les écritures à travers une vue sont très limitées, et tout le code Eloquent devrait viser des vues. Le coût dépasse celui du portage de l'application. |
| Un compte MySQL par utilisateur | Ingérable : il faudrait créer et supprimer un compte serveur à chaque agent, et le mutualiseur de connexions perdrait son intérêt. |
| Des déclencheurs qui refusent les écritures hors périmètre | Couvre l'écriture, **jamais la lecture** — or la fuite de D-013 était une fuite de lecture. |

Aucune de ces pistes ne reproduit la propriété qui faisait l'intérêt de RLS :
**une requête écrite sans y penser ne rend que les lignes autorisées.**

### 7.2 Ce qui subsiste, et qui n'est pas rien

Les barrières posées en base **restent en place et sont vérifiées** :

- le journal d'audit en ajout seul, par des droits table par table (D-051) ;
- le référentiel des transitions en lecture seule ;
- les déclencheurs de machine à états, que le compte applicatif ne peut pas
  supprimer (D-052) ;
- les contraintes `CHECK`, qui rendent les états incohérents impossibles ;
- l'exigence d'une ligne d'audit dans la **même transaction** que toute
  transition.

Ce que ces barrières couvrent : la **fraude par écriture** — produire un acte
signé sans décision, altérer une trace, forcer un état. C'est le §4.3 du
brief, et c'est l'essentiel.

Ce qu'elles ne couvrent pas : la **fuite par lecture** entre centres ou
communes, qui reste tenue par la seule portée globale Eloquent — précisément
celle qui avait disparu sans bruit au jalon 2 (D-013).

### 7.3 Ce que je propose à la place

Le trou n'est pas comblé, il est déplacé. Trois pistes, par ordre de coût
croissant, **aucune n'étant équivalente à RLS** :

1. **Un test de non-régression dédié à la portée globale**, qui échoue si
   `RequestVisibilityScope` cesse d'être appliquée — le défaut de D-013 aurait
   été vu immédiatement. Coût faible, à faire dans tous les cas.
2. **Une vérification au démarrage** : l'application refuse de démarrer si la
   portée n'est pas enregistrée sur le modèle. Transforme un défaut silencieux
   en panne bruyante.
3. **Un contrôle de périmètre centralisé** à la frontière des dépôts, qui
   n'est plus contournable en écrivant `withoutGlobalScopes()`.

**Question ouverte, qui vous revient :** si la fuite de lecture entre communes
est jugée aussi grave que la fraude par écriture, alors le choix de MySQL a un
coût de sécurité qu'il faut assumer explicitement, ou compenser par les trois
points ci-dessus. Je ne peux pas trancher cela à votre place : c'est une
question de risque acceptable, pas de technique.
