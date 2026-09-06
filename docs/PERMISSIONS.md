# Matrice d'autorisation

> Chaque ligne de ce document devient une Policy Laravel **et un test de
> refus**. Un test qui ne prouve que le cas autorisé ne prouve rien (§4.2).

- **Date :** 2026-09-06

---

## 1. Principes

1. **Refus par défaut.** Aucune ressource n'est accessible sans une règle
   explicite. Les Gates sont fermés par défaut, pas ouverts.
2. **Le rattachement fait partie de l'autorisation.** Être officier ne suffit
   pas : il faut être officier **du centre concerné**. Être maire ne suffit
   pas : il faut être maire **de la commune concernée**.
3. **Portée systématique en base.** Chaque modèle sensible porte un *global
   scope* Eloquent qui restreint au périmètre de l'utilisateur. Un `where`
   oublié dans une requête ne doit pas pouvoir faire fuir une donnée hors
   périmètre (§3.2).
4. **Séparation gouvernance / données personnelles.** L'administrateur gère les
   comptes ; il n'accède pas au contenu des dossiers d'identité. Ce n'est pas
   une préférence, c'est une exigence (§4.2).
5. **Toute lecture d'une donnée d'identité sensible est journalisée** : qui,
   quel dossier, quand (§4.4).

---

## 2. Rôles

| Rôle | Créé par | Rattachement | 2FA |
|---|---|---|---|
| `citizen` | auto-inscription | — | recommandé, à trancher |
| `officer` | **administrateur uniquement** | un centre d'état civil | **obligatoire** |
| `mayor` | **administrateur uniquement** | une commune | **obligatoire** |
| `admin` | amorçage initial, puis administrateur | — | **obligatoire** |

**Aucun compte officiel ne peut s'auto-inscrire** (§4.1). La route
d'inscription n'accepte que le rôle `citizen`, et le rôle n'est jamais lu
depuis la requête HTTP.

---

## 3. Matrice principale

Légende — ✅ autorisé · ❌ refusé · 🔶 partiel, voir note

### 3.1 Demandes de réédition

| Action | Citoyen | Officier | Maire | Admin |
|---|---|---|---|---|
| Créer un brouillon | ✅ pour lui-même | ❌ | ❌ | ❌ |
| Voir un brouillon | ✅ **le sien seul** | ❌ | ❌ | ❌ |
| Envoyer une demande | ✅ la sienne | ❌ | ❌ | ❌ |
| Lister ses demandes | ✅ les siennes | ❌ | ❌ | ❌ |
| Lister la file du centre | ❌ | ✅ **son centre**, hors `draft` | ❌ | ❌ |
| Lister la file de la commune | ❌ | ❌ | ✅ **sa commune**, états `awaiting_signature` et `escalated` **seulement** | ❌ |
| Voir le détail d'une demande | ✅ la sienne | ✅ son centre | 🔶 sa commune, aux 2 états ci-dessus | ❌ |
| Prendre en charge (T3) | ❌ | ✅ son centre | ❌ | ❌ |
| Décider accepter/rejeter/escalader | ❌ | ✅ celui qui a pris en charge | ❌ | ❌ |
| Signer (T7, T9) | ❌ | ❌ | ✅ sa commune | ❌ |
| Retourner à l'officier (T8, T11) | ❌ | ❌ | ✅ sa commune | ❌ |
| Supprimer un brouillon (T12) | ✅ le sien | ❌ | ❌ | ❌ |
| Supprimer une demande envoyée | ❌ | ❌ | ❌ | ❌ |
| Consulter les compteurs agrégés | ❌ | ✅ son centre | ✅ sa commune | 🔶 **volumes seuls, sans identité** |

**Note maire :** un maire ne voit **pas** une demande de sa commune en état
`pending` ou `under_review`. Le §4.2 le dit explicitement. Conséquence
concrète : sa file ne peut pas être une simple liste filtrable côté interface,
c'est une portée appliquée en base.

### 3.2 Données d'identité — selfies, pièces, numéro de pièce

C'est la section la plus restrictive du système.

| Action | Citoyen | Officier | Maire | Admin |
|---|---|---|---|---|
| Téléverser selfie / pièce | ✅ sur sa demande en `draft` | ❌ | ❌ | ❌ |
| **Voir** selfie / pièce | ✅ les siens | ✅ demandes de son centre, **journalisé** | ✅ demandes de sa commune aux 2 états, **journalisé** | ❌ **jamais** |
| Voir le numéro de pièce en clair | ✅ le sien | ✅ son centre, **journalisé** | ✅ sa commune, **journalisé** | ❌ **jamais** |
| Remplacer une pièce | ✅ tant que `draft` | ❌ | ❌ | ❌ |
| Supprimer une pièce | ❌ (hors purge de rétention) | ❌ | ❌ | ❌ |

**Aucune URL directe.** Les fichiers vivent hors de `public/` (D-011). Toute
lecture passe par un contrôleur qui vérifie la Policy **avant** de servir le
premier octet, et écrit une ligne d'audit.

**L'administrateur est exclu sans exception.** Y compris de la page de détail
d'une demande. C'est le point le plus contre-intuitif de la matrice et le plus
important : un compte administrateur compromis ne doit pas donner accès aux
pièces d'identité de la population.

### 3.3 Comptes et gouvernance

| Action | Citoyen | Officier | Maire | Admin |
|---|---|---|---|---|
| Créer un compte officier / maire | ❌ | ❌ | ❌ | ✅ |
| Activer / désactiver / suspendre | ❌ | ❌ | ❌ | ✅ |
| Réinitialiser un mot de passe officiel | ❌ | ❌ | ❌ | ✅ **déclenche un lien, ne choisit jamais le mot de passe** |
| Réaffecter un officier à un autre centre | ❌ | ❌ | ❌ | ✅ |
| Réattribuer une demande à un autre officier | ❌ | ❌ | ❌ | ✅ **sans voir le contenu du dossier** |
| Modifier son propre profil | ✅ | ✅ | ✅ | ✅ |
| Voir le profil complet d'un citoyen | ✅ le sien | 🔶 dans le cadre d'une demande de son centre | 🔶 idem, sa commune | ❌ |
| Supprimer un compte | ❌ | ❌ | ❌ | ❌ **désactivation seulement** |

**Aucune suppression de compte.** Un compte supprimé rendrait orphelines des
lignes d'audit et des décisions signées. La désactivation est la seule
opération, et elle est réversible.

**La réattribution d'une demande** est la seule action d'un administrateur qui
touche une demande. Elle ne change pas l'état et ne donne accès à aucun contenu :
l'écran ne montre que la référence, le centre et l'officier assigné.

### 3.4 Journal d'audit

| Action | Citoyen | Officier | Maire | Admin |
|---|---|---|---|---|
| Consulter le journal | ❌ | 🔶 les entrées de **ses propres** actions | 🔶 idem | ✅ **métadonnées seules** |
| Modifier une entrée | ❌ | ❌ | ❌ | ❌ **impossible techniquement** |
| Supprimer une entrée | ❌ | ❌ | ❌ | ❌ **impossible techniquement** |
| Exporter le journal | ❌ | ❌ | ❌ | ✅ métadonnées |

**« Métadonnées seules » signifie :** l'administrateur voit *qui a consulté quel
dossier et quand*. Il ne voit pas *ce que contenait* le dossier. Il peut donc
détecter un officier qui consulte des dossiers anormalement nombreux, sans
accéder lui-même aux données. C'est exactement le point d'équilibre visé par le
§4.2.

L'impossibilité de modifier ou supprimer est appliquée par **révocation des
droits `UPDATE` et `DELETE` sur `audit_logs`** pour le rôle applicatif
PostgreSQL, pas par une Policy. Une Policy se contourne par un bug ; une
révocation de droit, non.

---

## 4. Tests de refus exigés

Pour chaque cellule ❌ de ce document, un test qui prouve le refus. Au
minimum :

| # | Scénario | Attendu |
|---|---|---|
| R1 | Citoyen A demande le détail d'une demande du citoyen B | 403 |
| R2 | Citoyen A demande le selfie du citoyen B par URL directe | 403, **et rien n'est servi** |
| R3 | Officier du centre A ouvre une demande du centre B | 403 |
| R4 | Officier prend en charge une demande déjà prise par un collègue | refus |
| R5 | Officier tente d'accepter sans les 5 étapes renseignées | refus |
| R6 | Maire ouvre une demande de sa commune en état `pending` | 403 |
| R7 | Maire ouvre une demande en `awaiting_signature` d'une autre commune | 403 |
| R8 | **Admin ouvre le détail d'une demande** | 403 |
| R9 | **Admin demande un selfie ou un numéro de pièce** | 403 |
| R10 | Admin tente de signer une demande | 403 |
| R11 | Inscription en forçant `role=officer` dans la requête | compte créé en `citizen` |
| R12 | `UPDATE` puis `DELETE` sur `audit_logs` en SQL avec le rôle applicatif | erreur PostgreSQL |
| R13 | Requête Eloquent sans `where` explicite sur les demandes, exécutée en tant qu'officier | ne retourne **que** son centre (portée globale) |
| R14 | Utilisateur désactivé tentant de se connecter | refus, et session existante invalidée |
| R15 | Officier consultant une pièce d'identité | accès accordé **et** ligne d'audit écrite |

R13 est le test le plus important du lot : il vérifie que la barrière tient
**même en cas d'oubli du développeur**, ce qui est le scénario réel.

---

## 5. Point à trancher

⚠ **Un officier peut-il consulter une demande de son centre prise en charge par
un collègue ?**

- **Lecture seule autorisée** — utile pour l'entraide et la continuité de
  service, mais élargit l'accès aux pièces d'identité.
- **Refus total** — cloisonnement maximal, mais bloque dès qu'un agent est
  absent.

Je recommande la **lecture seule autorisée, systématiquement journalisée**, la
décision restant réservée à l'officier assigné. Le journal rend l'élargissement
d'accès contrôlable *a posteriori*, ce qui me paraît le bon compromis entre le
§4.2 et la réalité d'un service public.
