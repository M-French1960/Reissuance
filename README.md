# PHOENIX

Plateforme de réédition d'actes d'état civil — Cameroun.

Conçue pour une **exécution locale** (décision D-011). Laravel 13 + PostgreSQL,
interfaces en HTML et CSS écrits à la main, sans framework front-end (D-010).

## Démarrage

Prérequis : PHP 8.4 (`pdo_pgsql`, `mbstring`, `intl`, `gd`, `zip`),
Composer 2, et Docker — ou un PostgreSQL 16+ local.

```bash
cp .env.example .env
docker compose up -d              # PostgreSQL + collecteur de courriels
composer install
php artisan key:generate          # APP_KEY — À SAUVEGARDER
php artisan phoenix:generate-index-key   # clé de recherche — À SAUVEGARDER
php artisan migrate --database=pgsql_owner --force
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
| État du service | `/sante` (JSON avec `Accept: application/json`) |
| Galerie de composants | `/dev/ui` — hors production uniquement |
| Courriels capturés | http://localhost:8025 |

Les comptes de démonstration sont affichés à la fin de `db:seed`.

## Deux points à connaître avant de toucher au code

**L'application ne tourne jamais sous le propriétaire du schéma.** Les
migrations utilisent `phoenix_owner`, l'application `phoenix_app`, qui n'a ni
`UPDATE` ni `DELETE` sur `audit_logs`. C'est ce qui rend le journal d'audit
réellement inaltérable. Conséquence : `migrate:fresh` échoue avec le rôle
applicatif — c'est voulu, utiliser `--database=pgsql_owner`.

**`APP_KEY` et `PHOENIX_BLIND_INDEX_KEY` doivent être sauvegardées hors de la
machine.** La première chiffre les numéros de pièce ; les perdre les rend
illisibles définitivement. La seconde permet la recherche par numéro ; la
perdre casse la recherche sans perdre de données.

## Vérifications

```bash
./vendor/bin/phpunit    # 82 tests, sur un vrai PostgreSQL
./vendor/bin/pint       # formatage
```

Les tests tournent sur PostgreSQL et non sur SQLite : les barrières de sécurité
du projet sont des déclencheurs et des révocations de droits, que SQLite ne
saurait pas reproduire.

## Documentation

| Fichier | Contenu |
|---|---|
| `docs/DECISIONS.md` | Journal des décisions, y compris celles devenues caduques |
| `docs/AUDIT_FRONTEND.md` | Audit du prototype d'origine, mesures à l'appui |
| `docs/STATE_MACHINE.md` | Les 12 transitions et leur application en base |
| `docs/PERMISSIONS.md` | Matrice d'autorisation et tests de refus |
| `docs/DATA_MODEL.md` | Schéma, diagramme, index aveugle |
| `docs/INTEGRATIONS.md` | Les 4 dépendances externes et les questions à poser |
| `docs/COMPLIANCE_OPEN_QUESTIONS.md` | Questions juridiques ouvertes |
| `docs/ARCHITECTURE_LOCAL.md` | Pile locale, sécurité, sauvegarde |

`legacy-prototype/` conserve le prototype HTML d'origine comme référence
visuelle. Il n'est ni servi ni exécuté : voir l'audit avant d'y reprendre
quoi que ce soit.
