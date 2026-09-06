# Architecture d'exécution locale

> Ce document remplace le §3 du brief (contraintes de plateforme Vercel), rendu
> caduc par la décision **D-011**.

- **Date :** 2026-09-06
- **Cible :** installation sur une machine, sans dépendance à un hébergeur.

---

## 1. Vue d'ensemble

```
┌──────────────────────────────────────────────────────────┐
│  Navigateur — HTML/CSS écrits à la main (D-010)          │
│  JS vanille : capture photo · compression · envoi         │
└───────────────────────────┬──────────────────────────────┘
                            │ HTTP (TLS en production locale)
┌───────────────────────────▼──────────────────────────────┐
│  Application Laravel                                      │
│  Blade · Policies · machine à états · adaptateurs factices│
└───┬───────────────────┬──────────────────┬───────────────┘
    │                   │                  │
┌───▼──────────┐  ┌─────▼──────────┐  ┌────▼─────────────┐
│ PostgreSQL   │  │ Disque local   │  │ Worker de file   │
│ (local)      │  │ storage/app/   │  │ + ordonnanceur   │
│ 2 rôles SQL  │  │   private/     │  │ (processus réels)│
└──────────────┘  └────────────────┘  └──────────────────┘
```

Trois différences majeures avec le §3 du brief, toutes des simplifications :

| Contrainte Vercel | En local |
|---|---|
| Système de fichiers non persistant | **Disque persistant** |
| Pas de worker ni d'ordonnanceur | **`queue:work` et `schedule:run` réels** |
| Caches figés à la construction de l'image | Caches gérés normalement |

---

## 2. Composants et versions

Versions vérifiées le 2026-09-06 sur Packagist et Docker Hub, pas de mémoire.

| Composant | Version | Note |
|---|---|---|
| Laravel | **13.30.1** (dernière stable) | exige PHP ^8.3 |
| PHP | **8.4** recommandé | extensions : `pdo_pgsql`, `intl`, `mbstring`, `gd` ou `imagick`, `zip` |
| PostgreSQL | **17** | image officielle `postgres:17` |
| Blade | inclus | seul élément de la couche vue (D-010) |
| Pest | 5.x si PHP 8.4, sinon 4.x | Pest 5 exige PHP ^8.4 |
| Larastan | 3.x | niveau 6 minimum |
| Laravel Pint | 1.x | |

⚠ **À confirmer :** PHP 8.4 (permet Pest 5) ou PHP 8.3 (impose Pest 4). Je pars
sur **8.4** sauf objection.

**Aucun outillage JavaScript.** Pas de Node, pas de `package.json`, pas d'étape
de construction : le CSS est écrit à la main et servi tel quel, le JavaScript
est constitué de quelques fichiers vanille (D-010).

---

## 3. Pile locale

### 3.1 Docker Compose — mode recommandé

Trois services : `app` (PHP + serveur web), `db` (PostgreSQL 17), `mail`
(collecteur de courriels local, pour ne jamais envoyer un courriel réel depuis
un poste de développement).

Volumes persistants pour les données PostgreSQL et pour `storage/app/private`.
Les deux survivent au redémarrage — c'est précisément ce que Vercel ne
permettait pas.

### 3.2 Sans Docker

PHP 8.4, PostgreSQL 17 et Composer installés nativement ; `php artisan serve`.
Fonctionne, mais la procédure d'installation est plus longue et dépend du
système. Docker Compose reste le chemin documenté et testé.

### 3.3 Les deux processus d'arrière-plan

C'est ce que Vercel interdisait et qui redevient possible :

```
php artisan queue:work --tries=3 --backoff=30
php artisan schedule:work
```

Ils tournent en continu à côté du serveur web. Le worker traite les
notifications ; l'ordonnanceur gère la purge de rétention et les relances.

**La contrainte de D-006 est maintenue** : l'échec d'une notification ne doit
jamais faire échouer ni annuler une transition d'état. La file rejoue, la
demande poursuit son cycle.

---

## 4. Configuration

| Réglage | Valeur locale | Justification |
|---|---|---|
| `APP_ENV` | `local` \| `production` | |
| `APP_DEBUG` | `true` en local, **`false` sinon** | |
| `DB_CONNECTION` | `pgsql` | connexion directe, aucune dépendance Supabase |
| `SESSION_DRIVER` | `database` | choix conservé volontairement — voir ci-dessous |
| `CACHE_STORE` | `database` | idem |
| `QUEUE_CONNECTION` | `database` | worker réel |
| `FILESYSTEM_DISK` | `private` | disque pointant sur `storage/app/private`, **hors de `public/`** |
| `MAIL_MAILER` | `smtp` vers le collecteur local | aucun courriel réel depuis un poste |
| `LOG_CHANNEL` | `daily` | |

**Pourquoi conserver `database` pour la session et le cache alors que `file`
redevient possible ?** Parce que c'est le seul choix qui reste valable si le
projet est un jour déployé, et qu'il ne coûte rien en local. Revenir à `file`
serait un gain nul contre une dette certaine.

---

## 5. Sécurité en local — ce qui ne change pas

Le passage en local n'allège **aucune** exigence du §4. Les mêmes Policies, la
même machine à états appliquée en base, le même journal en ajout seul.

### 5.1 Deux rôles PostgreSQL distincts

C'est le point d'architecture le plus important de cette section.

| Rôle | Usage | Droits |
|---|---|---|
| `phoenix_owner` | migrations uniquement | propriétaire du schéma |
| `phoenix_app` | **l'application** | `SELECT/INSERT/UPDATE/DELETE`, **sauf `UPDATE` et `DELETE` sur `audit_logs`** |

L'application ne tourne **jamais** sous le rôle propriétaire. C'est ce qui rend
le journal d'audit réellement inaltérable (§4.4) plutôt que simplement
« interdit par convention ».

**Conséquence à connaître :** `php artisan migrate:fresh` échoue avec le rôle
applicatif. En développement, la réinitialisation passe par une commande
dédiée qui bascule sur `phoenix_owner`. C'est une friction volontaire.

### 5.2 Stockage des pièces d'identité

Le garde-fou n°5 interdisait le stockage sur le système de fichiers du
conteneur et derrière une URL publique. En local, la première moitié perd son
objet ; **la seconde reste absolue** :

- écriture dans `storage/app/private/`, **jamais** dans `public/` ;
- aucun lien symbolique depuis `public/` vers ce répertoire ;
- toute lecture passe par un contrôleur qui **vérifie la Policy avant de servir
  le premier octet** et **écrit une ligne d'audit** ;
- noms de fichiers opaques, jamais dérivés du nom ni du numéro de pièce ;
- empreinte SHA-256 en base pour détecter toute altération ;
- validation stricte du type MIME et de la taille **côté serveur**, la
  validation navigateur n'étant que de l'ergonomie.

L'abstraction `Storage` de Laravel est utilisée telle quelle : basculer vers S3
ou Supabase Storage ne demanderait qu'un changement de disque en configuration.

### 5.3 En-têtes de sécurité

La CSP stricte **sans `unsafe-inline` ni `unsafe-eval`** devient réellement
atteignable, puisque D-010 a supprimé Livewire et Alpine. Condition : aucun
attribut `onclick` en ligne — le prototype en compte 12, aucun n'est porté.

HSTS n'a de sens qu'en TLS ; il est conditionné à `APP_ENV=production`.

### 5.4 Ce que le local ne protège pas

À dire clairement : une installation locale n'est pas plus sûre par nature.
Elle déplace la responsabilité vers la machine hôte — chiffrement du disque,
comptes du système, sauvegardes. Ces points sortent du périmètre applicatif
mais doivent figurer dans la documentation d'exploitation avant tout usage réel.

---

## 6. Installation

```
1. Copier .env.example vers .env
2. docker compose up -d
3. composer install
4. php artisan key:generate          # APP_KEY — à sauvegarder
5. php artisan phoenix:init-db-roles # crée phoenix_owner et phoenix_app
6. php artisan migrate --force       # sous phoenix_owner
7. php artisan db:seed               # référentiels + comptes de démonstration
8. php artisan serve
9. Dans deux terminaux : queue:work et schedule:work
```

**`APP_KEY` doit être sauvegardée.** Elle chiffre le numéro de pièce
(`DATA_MODEL.md` §3). Sa perte rend les numéros déjà enregistrés illisibles —
définitivement. La clé HMAC de l'index aveugle appelle la même précaution.

---

## 7. Sauvegarde et reprise

Sujet que Vercel masquait et qui devient entièrement de notre ressort.

| Élément | À sauvegarder | Perte si absent |
|---|---|---|
| Base PostgreSQL | `pg_dump` régulier | tout |
| `storage/app/private/` | copie du volume | **toutes les pièces d'identité** |
| `APP_KEY` | hors de la machine | numéros de pièce illisibles |
| Clé HMAC | hors de la machine | recherche par numéro impossible |

Une sauvegarde de la base **sans** le répertoire de stockage produit un système
cohérent en apparence dont toutes les pièces d'identité manquent. Les deux se
sauvegardent ensemble.

Procédure de restauration à écrire et **à tester** au jalon 6. Une sauvegarde
jamais restaurée n'est pas une sauvegarde.

---

## 8. Ce qui n'est pas produit

Par application de D-011, et pour éviter que du code mort ne laisse croire à
une cible qui n'existe plus :

- `Dockerfile.vercel`, `Caddyfile`, `vercel.json`
- Les endpoints `/internal/cron/dispatch` et `/internal/queue/drain`
- Toute dépendance à Supabase, à son SDK ou à son outillage de migration

Si le déploiement revenait à l'ordre du jour, `docs/DECISIONS.md` conserve les
décisions D-005 à D-009 avec leurs mesures et leurs pièges — notamment le fait
que `config:cache` ne doit pas être figé dans l'image, et que Vercel Cron émet
un `GET`.
