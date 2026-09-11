# Performance — mesures

> **Reprises le 2026-09-11 sur MySQL** (D-056). Toutes les valeurs sont
> **mesurées** sur l'application en fonctionnement, avec une base peuplée.
> Aucune n'est estimée.

---

## 1. Conditions de mesure

| Élément | Valeur |
|---|---|
| Demandes en base | **515** |
| Comptes | **513** |
| Entrées du journal d'audit | **1 697** |
| Étapes de vérification | **674** |
| Fenêtre | 390 × 844 px |
| Navigateur | Chromium, piloté par Playwright |
| Serveur | `php artisan serve`, **MariaDB 10.11 locale** |

Les conditions reproduisent celles de la mesure PostgreSQL du jalon 6 (515
demandes, 513 comptes) pour que la comparaison ait un sens. Chaque écran est
chargé **trois fois**, la médiane est retenue : un à-coup isolé ne doit pas
devenir un chiffre publié.

> **Un chiffre que j'ai failli publier faux.** Mon premier harnais mesurait le
> chargement complet avec `waitUntil: 'networkidle'`, qui attend 500 ms de
> silence réseau **par construction**. Toutes les pages sortaient à ~550 ms,
> un plateau suspect qui n'était que ce délai. Le temps de chargement est
> désormais lu dans l'API Navigation Timing de la page
> (`loadEventEnd - startTime`), qui mesure la page et non l'attente de
> l'outil.

Le jeu de volume est produit par `database/seeders/VolumeSeeder.php`, avec des
données **entièrement synthétiques** (garde-fou n°1). Un écran mesuré sur trois
lignes ne mesure rien.

---

## 2. Ressources statiques — budgets du §8.5

| Ressource | Brut | Gzip | Budget | Marge |
|---|---:|---:|---:|---|
| `css/tokens.css` | 4 058 o | 1 730 o | — | — |
| `css/app.css` | 20 791 o | 5 182 o | — | — |
| **CSS total** | **24 849 o** | **6 912 o** | 50 000 o | **86 %** |
| `js/identity-capture.js` | 7 675 o | 2 871 o | — | — |
| **JS total** | **7 675 o** | **2 871 o** | 100 000 o | **97 %** |

Aucune étape de compilation, aucune dépendance tierce chargée par le
navigateur : c'est ce que valait la décision D-010 (HTML et CSS écrits à la
main).

---

## 3. Temps de réponse et poids des pages

Base peuplée, quatre requêtes réseau par page (HTML + 2 CSS + 1 JS) :

| Écran | HTML brut | Serveur | Chargement complet | Serveur sur PostgreSQL |
|---|---:|---:|---:|---:|
| Tableau de bord citoyen | 5 435 o | 28 ms | 88 ms | 28 ms |
| Mes demandes | 7 964 o | 30 ms | 52 ms | 31 ms |
| Tableau de bord officier | 3 384 o | 25 ms | 52 ms | 38 ms |
| File de traitement | 33 959 o | 41 ms | 120 ms | 32 ms |
| File de signature | 12 069 o | 32 ms | 53 ms | 36 ms |
| Comptes | 79 663 o | 32 ms | 80 ms | 27 ms |
| Journal d'audit | 32 320 o | 29 ms | 63 ms | 28 ms |

**Le temps serveur ne dépasse jamais 41 ms** et ne varie pas avec le volume :
toutes les listes paginent, aucune ne parcourt la table entière.

**Le changement de moteur ne se voit pas.** La dernière colonne reprend les
temps serveur relevés sur PostgreSQL au jalon 6, dans les mêmes conditions.
L'écart le plus large est de 9 ms sur la file de traitement — du même ordre que
la dispersion entre deux passages sur le même moteur. Il serait malhonnête d'en
tirer qu'un moteur est plus rapide que l'autre : à cette échelle de volume, ni
l'un ni l'autre n'est le facteur limitant.

---

## 4. La compression n'est pas une option, c'est une exigence

Le poids brut est trompeur. Ce balisage est très répétitif, donc il se comprime
très bien :

| Écran | Brut | Gzip | Facteur |
|---|---:|---:|---:|
| Comptes | 79 663 o | **3 524 o** | **×23** |
| Journal d'audit | 32 320 o | **2 394 o** | ×14 |
| File de traitement | 33 959 o | **2 984 o** | ×11 |

**Conséquence pour le déploiement :** sans compression activée sur le serveur
web, la page des comptes coûte 79 Ko au lieu de 3,3 Ko. Sur le réseau contraint
visé au §8.3, c'est la différence entre une page instantanée et une page qu'on
attend. `php artisan serve` **ne compresse pas** : c'est acceptable en
développement, jamais en service.

À vérifier au déploiement : `gzip` ou `brotli` activé sur `text/html`,
`text/css` et `application/javascript`.

---

## 5. Coût SQL par écran — et sa stabilité

Ce qui compte n'est pas « combien de requêtes », mais « le nombre croît-il avec
le nombre de lignes ». `tests/Feature/QueryBudgetTest.php` mesure **deux fois**,
avec peu puis beaucoup de lignes, et compare.

| Écran | Requêtes | Croît avec le volume ? |
|---|---:|---|
| File de traitement (officier) | 5 | non |
| File de signature (maire) | 5 | non |
| Mes demandes (citoyen) | 4 | non |
| Comptes (administrateur) | 5 | non |
| Journal d'audit | 4 | non |

**Aucun de ces compteurs n'a bougé au passage à MySQL** — ce qui était attendu,
le nombre de requêtes étant une propriété du code et non du moteur. Relevés
avec `PHOENIX_SHOW_QUERIES=1 ./vendor/bin/phpunit tests/Feature/QueryBudgetTest.php`.

> Un relevé bricolé à côté du harnais de test m'avait donné 3 requêtes pour la
> file de signature et pour les comptes, au lieu de 5. La différence venait de
> mon propre jeu d'essai — un administrateur fraîchement créé, sans les lignes
> de session que charge une vraie visite — et non de l'application. Les
> chiffres publiés sont ceux du harnais, qui passe par la pile HTTP complète.

### Ce que la mesure a corrigé

La file de l'officier chargeait **deux** relations par anticipation :
`assignedOfficer`, que la vue affiche, et `citizen`, **que la vue n'ouvre
jamais**. Une requête par page pour rien. Le nom affiché vient de la colonne
`full_name_at_birth`, pas de la relation. La file est passée de 6 à 5 requêtes.

### Un test qui passait pour de mauvaises raisons

Mon premier jeu d'essai créait des dossiers **non pris en charge**. La vue
affiche le nom de l'officier assigné ; avec un dossier libre ce nom est nul,
Eloquent n'interroge rien, et la relation qui pourrait coûter une requête par
ligne n'était jamais exercée. Le test était vert sans rien vérifier.

Corrigé, puis éprouvé en retirant le chargement anticipé : **7 requêtes pour 3
dossiers, 29 pour 30**. Le test échoue, comme il doit.

---

## 6. Ce qui n'est pas mesuré ici

- **Un vrai réseau 3G.** Les chiffres ci-dessus sont en local. Le temps réseau
  dominera le temps serveur dans toutes les conditions visées au §8.3.
- **Un vrai appareil modeste.** Chromium sur cette machine n'est pas un
  téléphone d'entrée de gamme.
- **La montée en charge simultanée.** Aucun test de charge n'a été fait ; ces
  mesures sont mono-utilisateur.
- **Le téléversement d'une photographie** sur réseau lent, mesuré au jalon 3
  (compression navigateur : 277 Ko → 35 Ko sur une photographie réaliste).
