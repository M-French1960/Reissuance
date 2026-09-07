# Sécurité au niveau des lignes — évaluation mesurée

> Jalon 6. D-011 avait reporté ce sujet ici, « après évaluation du coût ».
> Le prototype a été **construit et exécuté** : les chiffres ci-dessous sont
> mesurés, pas estimés. Le code est conservé dans `docs/prototypes/rls/`.

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
