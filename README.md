# PHOENIX

Plateforme de réédition d'actes d'état civil — Cameroun.

Conçue pour une **exécution locale** (décision D-011). Laravel 13 + MySQL
(D-051), interfaces en HTML et CSS écrits à la main, sans framework front-end
(D-010).

## Démarrage

Prérequis : PHP 8.4 (`pdo_mysql`, `mbstring`, `intl`, `gd`, `zip`),
Composer 2, et Docker — ou un MySQL 8.0.16+ / MariaDB 10.11+ local.

**MySQL est le seul moteur supporté, et l'application le fait respecter.** Elle
refuse de démarrer sur tout autre pilote : ses barrières anti-fraude sont
posées dans la base, et aucune ne survit à un changement de moteur. Sur SQLite
elle fonctionnerait en apparence, et accepterait une transition interdite sans
rien signaler (D-054).

```bash
cp .env.example .env
docker compose up -d              # MySQL + collecteur de courriels
composer install
php artisan key:generate          # APP_KEY — À SAUVEGARDER
php artisan phoenix:generate-index-key   # clé de recherche — À SAUVEGARDER
php artisan migrate --database=mysql_owner --force
php artisan db:seed
php artisan serve
```

Puis, dans deux terminaux séparés :

```bash
php artisan queue:work
php artisan schedule:work
```

| Page | Chemin |
|---|---|
| Accueil | `/` |
| Connexion / inscription | `/login`, `/register` |
| Tableau de bord | `/tableau-de-bord` — aiguille selon le rôle |
| Profil citoyen | `/mon-espace/profil` |
| Mes demandes | `/mon-espace/demandes` |
| Assistant de demande | `/mon-espace/demandes/{id}/etape/{1-4}` |
| File de l'officier | `/verification/file` |
| Vérification en 5 étapes | `/verification/demandes/{id}/etape/{1-5}` |
| Files du maire | `/signature/tableau-de-bord` |
| Revue d'un dossier | `/signature/dossiers/{id}` |
| Double authentification | `/double-authentification` |
| Portail administrateur | `/administration/comptes`, `/administration/journal` |
| État du service | `/sante` (JSON avec `Accept: application/json`) |
| Galerie de composants | `/dev/ui` — hors production uniquement |
| Courriels capturés | http://localhost:8025 |

Les comptes de démonstration sont affichés à la fin de `db:seed`.

## Mettre en service un compte officiel

Un officier ou un maire ne peut pas s'inscrire lui-même. Le parcours est :

1. Un administrateur crée le compte (`/administration/comptes/nouveau`). Il est
   créé **`pending`**, sans mot de passe choisi par l'administrateur.
2. Son titulaire reçoit un lien, définit son mot de passe, se connecte. Il
   n'accède alors **qu'à** la page de double authentification.
3. Il configure sa 2FA et la confirme.
4. L'administrateur peut alors activer le compte, avec un motif obligatoire
   qui part au journal d'audit.

L'étape 3 n'est pas contournable : la contrainte `users_official_2fa_check`
refuse en base tout compte officiel actif sans 2FA confirmée **ni secret posé**.

## Signer un acte

**Le maire doit reconfirmer son identité à chaque signature** (D-069). Un clic
dans une session déjà ouverte n'est pas une décision : la session officielle
dure trente minutes, et un navigateur laissé ouvert suffirait sinon à délivrer
des actes.

Deux moyens, et un seul est exigé :

| Moyen | Ce qu'il apporte | Quand il s'applique |
|---|---|---|
| **Appareil enrôlé** (D-070) — Face ID, Windows Hello, empreinte | la clé qui signe n'a jamais quitté l'appareil du maire ; le serveur ne peut pas signer à sa place | exige TLS (ou `localhost`) et un appareil enrôlé depuis la page Sécurité |
| **Code d'authentification** (D-069) | prouve la présence du titulaire du compte | toujours — c'est le repli si l'appareil est perdu ou le service servi sans TLS |

La biométrie **ne quitte jamais l'appareil** : elle y déverrouille une clé
privée qui n'en sort pas davantage. La table `signing_devices` ne contient
qu'une clé publique, jamais de gabarit biométrique.

Cinq codes erronés par quart d'heure bloquent la signature, et chaque échec
part au journal d'audit.

`php artisan db:seed` affiche les clefs TOTP des comptes de démonstration à
côté de leurs mots de passe : sans elles, le maire de démonstration ne peut pas
signer.

## Exercer les cas dégradés

Les adaptateurs externes sont factices (§9 du brief : aucune API réelle n'est
documentée). Ils sont **déterministes** et se déclenchent par préfixe du numéro
de pièce, de sorte que les états dégradés soient testables sans configuration :

| Préfixe | Cas simulé |
|---|---|
| `DEMO-NOMATCH` | pièce connue, nom différent |
| `DEMO-STOLEN` | pièce signalée volée |
| `DEMO-DOUBT` | nom proche sans être identique |
| `DEMO-DOWN`, `DEMO-TIMEOUT` | base de la police injoignable |

Pour le registre d'état civil, c'est le **nom** recherché qui déclenche :
`HOMONYME`, `INTROUVABLE`, `DETRUIT`, `PANNE`.

Le seeder `DemoProviderCasesSeeder` crée une demande par cas. La ponctuation
n'a pas d'importance : les déclencheurs sont normalisés comme le stockage.

## Les actes produits n'ont aucune valeur juridique

Le prestataire de signature configuré est un **adaptateur factice**. Chaque
acte délivré porte, en première ligne :

```
DOCUMENT DE DEMONSTRATION - SANS VALEUR JURIDIQUE
Ce document ne peut etre presente a aucune administration.
```

Cette mention ne disparaîtra que le jour où un prestataire agréé sera branché
**et** la valeur légale d'un acte d'état civil signé électroniquement au
Cameroun confirmée. Voir le bloc A de `docs/COMPLIANCE_OPEN_QUESTIONS.md` et la
décision D-025.

Le PDF est produit par **dompdf**, à partir de gabarits Blade rangés sous
`resources/views/documents/` (D-067). Le moteur est bridé en un seul endroit,
`App\Support\Pdf\HtmlToPdf` : aucune ressource distante, aucun PHP embarqué,
aucun JavaScript. Ces brides comptent — le contenu d'un acte vient d'un dossier
citoyen, et un moteur de rendu HTML sans bride irait chercher ce qu'on lui
indique, depuis l'intérieur du réseau de la mairie.

## Trois points à connaître avant de toucher au code

**L'application ne tourne jamais sous le propriétaire du schéma.** Les
migrations utilisent `phoenix_owner`, l'application `phoenix_app`, qui n'a ni
`UPDATE` ni `DELETE` sur `audit_logs`. C'est ce qui rend le journal d'audit
réellement inaltérable. Conséquence : `migrate:fresh` échoue avec le compte
applicatif — c'est voulu, utiliser `--database=mysql_owner`.

**Après toute migration qui crée une table, lancer `php artisan phoenix:droits`.**
MySQL n'accorde aucun droit sur une table nouvelle et n'a pas d'équivalent
d'`ALTER DEFAULT PRIVILEGES`. Les droits sont posés **table par table**, jamais
sur la base entière : sur MySQL les deux portées s'additionnent, et un droit de
base rendrait le journal d'audit modifiable sans qu'aucune révocation par table
ne puisse le reprendre (D-051). Un test le vérifie.

**`APP_KEY` et `PHOENIX_BLIND_INDEX_KEY` doivent être sauvegardées hors de la
machine.** La première chiffre les numéros de pièce ; les perdre les rend
illisibles définitivement. La seconde permet la recherche par numéro ; la
perdre casse la recherche sans perdre de données.

**La vérification des mots de passe compromis ne fonctionne pas hors ligne.**
La règle `uncompromised()` de Laravel interroge un service distant et, s'il est
injoignable, **laisse passer** — sans avertissement. Le plancher réel en local
est `App\Rules\NotAWeakPassword`, qui attrape l'évident et rien de plus. Voir
la décision D-015.

## Vérifications

```bash
./vendor/bin/phpunit    # 746 tests, sur un vrai MySQL
./vendor/bin/pint       # formatage
```

Les tests tournent sur MySQL et non sur SQLite : les barrières de sécurité du
projet sont des déclencheurs et des droits accordés table par table, que SQLite
ne saurait pas reproduire.

La base d'essai est migrée par `tests/bootstrap.php`, avant le premier test :
depuis `setUp()` la migration arriverait trop tard, la transaction du trait
`DatabaseTransactions` étant déjà ouverte sous un compte encore sans droits. Ce
fichier **vide la base qu'il vise** et refuse donc de s'exécuter si `APP_ENV`
n'est pas `testing` ou si le nom de la base ne se termine pas par `_test`.

## Documentation

| Fichier | Contenu |
|---|---|
| `docs/DECISIONS.md` | Journal des décisions, y compris celles devenues caduques |
| `docs/AUDIT_FRONTEND.md` | Audit du prototype d'origine, mesures à l'appui |
| `docs/STATE_MACHINE.md` | Les 12 transitions et leur application en base |
| `docs/PERMISSIONS.md` | Matrice d'autorisation et tests de refus |
| `docs/DATA_MODEL.md` | Schéma, diagramme, index aveugle |
| `docs/INTEGRATIONS.md` | Les 5 dépendances externes et les questions à poser |
| `docs/COMPLIANCE_OPEN_QUESTIONS.md` | Questions juridiques ouvertes |
| `docs/ARCHITECTURE_LOCAL.md` | Pile locale, sécurité, sauvegarde |
| `docs/CAS_USAGE.md` | Les cas du diagramme, et ce qui en sort |
| `docs/ACCESSIBILITE.md` | Audit d'accessibilité et ce qu'il ne couvre pas |
| `docs/PERFORMANCE.md` | Budgets du §8.5, mesurés |
| `docs/SAUVEGARDE.md` | Sauvegarde et restauration, éprouvées |
| `docs/RLS.md` | Sécurité au niveau ligne : l'arbitrage et le risque accepté |
| `docs/BIOMETRIE.md` | Reconnaissance faciale : ce qui est supposé |

`legacy-prototype/` conserve le prototype HTML d'origine comme référence
visuelle. Il n'est ni servi ni exécuté : voir l'audit avant d'y reprendre
quoi que ce soit.
