# Journal de décisions — PHOENIX

Format : décision, date, alternatives écartées, justification.
Seules les décisions **arbitrées** figurent ici. Les points encore ouverts sont
signalés comme tels et ne doivent pas être traités comme tranchés.

---

## D-001 — Portage du prototype plutôt que reprise du code

- **Date :** 2026-09-05
- **Statut :** décidé
- **Décision :** le HTML/CSS existant est utilisé comme référence visuelle et
  comme source de vérité sur les champs métier. Aucun fichier n'est repris tel
  quel. Aucun `<script>` de mock ne survit au branchement d'un écran.
- **Alternatives écartées :**
  - *Reprendre les fichiers et y greffer Blade.* Écarté : les mesures de
    l'audit (0 media query sur 4 fichiers CSS sur 5, largeurs fixes jusqu'à
    1 320 px, 6 couples de contraste en échec AA, `innerHTML` par concaténation,
    11 `onclick` en ligne) rendent la dette plus coûteuse que la réécriture.
  - *Repartir de zéro en ignorant le prototype.* Écarté : `form.html` contient
    un travail métier réel (champs acte de naissance + parents) qui va au-delà
    du modèle de données du brief et qu'il serait coûteux de redécouvrir.
- **Justification :** voir `docs/AUDIT_FRONTEND.md`, §8.4 et §10.

---

## D-002 — Suppression des icônes SVG vendorisées

- **Date :** 2026-09-05
- **Statut :** décidé, application au jalon 1
- **Décision :** supprimer `brands/`, `regular/` et `solid/` (2 045 fichiers).
- **Justification :** vérifié par `grep` — aucun de ces fichiers n'est référencé
  par une page ou une feuille de style. Les icônes affichées viennent du CDN
  cdnjs. Ces répertoires représentent 99,4 % des fichiers versionnés sans
  contribuer au rendu.
- **Conséquence :** les icônes nécessaires seront intégrées en SVG inline ou en
  sprite local, ce qui supprime aussi la dépendance au CDN étranger (audit
  §6.5).

---

## D-003 — Le paiement sort du parcours citoyen jusqu'au jalon 7

- **Date :** 2026-09-05
- **Statut :** décidé
- **Décision :** l'étape 4 « Payment » de `form.html` n'est pas portée au
  jalon 3. Aucune valeur monétaire n'est codée.
- **Justification :** le brief place le paiement au jalon 7. Par ailleurs, **je
  ne peux pas confirmer que le montant de 20 000 CFA affiché par le prototype
  corresponde à un tarif réel**, ni qu'un tarif de réédition existe sous cette
  forme au Cameroun. Coder une valeur invérifiable dans un service public est
  exclu.
- **À confirmer :** tarif officiel, base réglementaire, modalités
  d'encaissement — reporté dans `docs/COMPLIANCE_OPEN_QUESTIONS.md`.

---

## D-004 — Aucune reprise des jeux de données du prototype

- **Date :** 2026-09-05
- **Statut :** décidé
- **Décision :** les noms, numéros de pièce, téléphones et e-mails présents dans
  les mocks ne sont repris dans aucun seeder, test ou capture d'écran. Les jeux
  de démonstration utiliseront des identités explicitement fictives (préfixe
  `DEMO-`, domaine `@example.test`).
- **Justification :** garde-fou n°1 du projet. Rien ne permet d'établir que ces
  valeurs sont inventées ; l'incertitude suffit à les écarter.

---

---

## D-005 — Hébergement sur les offres gratuites, usage non commercial assumé

- **Date :** 2026-09-05
- **Statut :** ⚠️ **CADUQUE depuis le 2026-09-06 — remplacée par D-011.** Conservée pour mémoire : elle redeviendrait applicable en cas de déploiement sur Vercel.
- **Décision :** Vercel plan Hobby et Supabase offre gratuite. Le commanditaire
  confirme que le projet relève d'un usage non commercial, ce qui le place hors
  du champ de la restriction Vercel citée ci-dessous.
- **Règle applicable, vérifiée le 2026-09-05 :** « Hobby teams are restricted to
  non-commercial personal use only. […] Commercial usage is defined as any
  Deployment that is used for the purpose of financial gain of anyone involved
  in any part of the production of the project, including a paid employee or
  consultant writing the code. »
- **Conséquence à réévaluer :** si le projet devait un jour encaisser des frais
  de citoyens (§5.2 du brief, jalon 7) ou être développé contre rémunération,
  **cette décision tombe** et un passage en Pro devient obligatoire. À revoir
  avant tout travail sur le paiement.

---

## D-006 — Architecture sans traitement asynchrone

- **Date :** 2026-09-05
- **Statut :** ⚠️ **ANNULÉE le 2026-09-06 par D-011.** En local, un worker de file et un ordonnanceur fonctionnent normalement : l'asynchrone redevient disponible. La contrainte n°1 ci-dessous (« une notification qui échoue ne doit jamais annuler une transition d'état ») est **maintenue** — elle est bonne en soi.
- **Décision :** aucun mécanisme de file d'attente asynchrone. `QUEUE_CONNECTION`
  reste `sync`. Les notifications partent pendant la requête. Le §3.1 du brief
  (endpoints `/internal/cron/dispatch` et `/internal/queue/drain`, drain par
  lots, verrou anti-concurrence) **n'est pas implémenté**.
- **Cause :** le plan Vercel Hobby limite les tâches planifiées à **une
  exécution par jour**, avec une précision de ± 59 minutes (Pro et Enterprise :
  une par minute). Un drain quotidien signifierait qu'un citoyen est notifié
  jusqu'à 24 h après le changement de statut de sa demande — inacceptable au
  regard de l'exigence n°2.
- **Alternatives écartées :**
  - *Drain quotidien.* Écarté : latence de notification inacceptable.
  - *Drain opportuniste en fin de requête.* Écarté : le conteneur peut être
    recyclé après l'envoi de la réponse ; exécution non garantie, donc
    notification silencieusement perdue.
- **Contraintes que cette décision impose au code :**
  1. **Une notification qui échoue ne doit jamais faire échouer ni annuler une
     transition d'état.** L'envoi est encapsulé, borné par un délai
     d'expiration strict, et son échec est journalisé puis rejoué.
  2. Aucun traitement long dans une requête citoyen ou officier. Budget de
     300 s côté Vercel, mais l'ergonomie impose bien moins.
  3. Le code ne doit rien présupposer d'une file d'attente : si l'asynchrone
     revient un jour, ce sera un ajout, pas un déblocage.

---

## D-007 — Emploi de l'unique tâche planifiée quotidienne

- **Date :** 2026-09-05
- **Statut :** ⚠️ **CADUQUE depuis le 2026-09-06 — remplacée par D-011.** Plus de limite d'une exécution par jour, plus de mise en pause Supabase à contourner.
- **Décision :** la seule exécution quotidienne autorisée sur Hobby est
  affectée, par ordre de priorité, à : (1) maintenir le projet Supabase éveillé,
  (2) rejouer les notifications en échec, (3) l'entretien courant.
- **Justification du point (1) :** l'offre gratuite Supabase **met le projet en
  pause après une semaine d'inactivité**. Sans ce ping, l'application peut être
  hors service au moment précis où quelqu'un vient la consulter après une
  interruption. C'est le risque opérationnel le plus probable du projet.
- **Correction technique par rapport au §3.1 du brief :** Vercel Cron émet une
  requête **`GET`**, pas `POST`, vers l'URL du déploiement **de production**
  uniquement, en UTC. L'endpoint sera donc un `GET` protégé par secret
  d'en-tête, idempotent.

---

## D-008 — La compression des images côté client est une contrainte, pas une optimisation

- **Date :** 2026-09-05
- **Statut :** **RÉVISÉE le 2026-09-06 par D-011.** La compression est **maintenue**, mais sa justification change : ce n'est plus une limite de capacité (le disque local n'est plus plafonné à 1 Go), c'est une exigence de performance sur réseau contraint (§8.5 du brief) et la condition d'un déploiement ultérieur. La cible de 250 Ko/image est conservée.
- **Décision :** compression et redimensionnement dans le navigateur **avant**
  tout envoi, cible ≤ 250 Ko par image. Politique de rétention et suppression
  effective implémentées dès le jalon 3, pas reportées au jalon 6.
- **Cause :** l'offre gratuite Supabase plafonne le **stockage fichier à 1 Go**.
  Chaque demande produit deux images (selfie + pièce d'identité). Capacité
  totale du système selon la compression :

  | Compression | Poids/image | Poids/demande | Capacité totale |
  |---|---:|---:|---:|
  | Aucune (photo de smartphone) | ~3,5 Mo | 6,8 Mo | **~149 demandes** |
  | Légère | 800 Ko | 1,6 Mo | ~655 demandes |
  | **Cible retenue** | **250 Ko** | **0,5 Mo** | **~2 100 demandes** |
  | Forte + WebP | 120 Ko | 0,23 Mo | ~4 370 demandes |

  Sans compression, le système sature après moins de 150 demandes. Ce n'est pas
  une question de performance : c'est la capacité maximale du service.
- **Conséquence sur l'exigence n°3 :** le §8.5 du brief demandait déjà des
  miniatures dans les files de l'officier. Cela devient impératif pour une
  seconde raison — l'offre gratuite plafonne aussi le **trafic sortant à 5 Go
  par mois**, et chaque consultation d'une photo pleine résolution par un
  officier le consomme.

---

## D-009 — Confirmation du mode de connexion : pooler Supavisor en mode session

- **Date :** 2026-09-05
- **Statut :** ⚠️ **SANS OBJET depuis le 2026-09-06 — remplacée par D-011.** En local, connexion directe à PostgreSQL. Conservée : elle redeviendrait la recommandation en cas de bascule vers Supabase.
- **Décision provisoire :** pooler Supavisor en mode session
  (`aws-<région>.pooler.supabase.com:5432`).
- **Éléments nouveaux apportés par l'offre gratuite :** l'instance est en **CPU
  partagé avec 500 Mo de RAM**. Postgres crée un processus par connexion
  directe ; à ce niveau de mémoire, le nombre de connexions directes soutenables
  est faible. Cela renforce nettement le choix du pooler contre la connexion
  directe — laquelle est de toute façon **en IPv6 uniquement** sur l'offre
  gratuite.
- **Reste à mesurer avant de figer :** latence réelle depuis le Cameroun,
  comportement des deux modes sous migrations et transactions Eloquent réelles.


---

## D-010 — Interfaces en HTML et CSS écrits à la main : ni Tailwind, ni Livewire, ni Alpine

- **Date :** 2026-09-06
- **Statut :** décidé par le commanditaire
- **Décision :** la couche de présentation reste du **HTML et du CSS écrits à la
  main**, dans la continuité du prototype existant. Cela remplace le §2 du brief
  sur trois points : **pas de Tailwind CSS**, **pas de Livewire**, **pas
  d'Alpine.js**.
- **Ce qui est conservé :** Laravel, PostgreSQL/Supabase, et **Blade** comme
  moteur de gabarits. Blade produit du HTML ordinaire ; il apporte l'héritage de
  gabarit, les composants de vue et surtout **l'échappement par défaut de
  `{{ }}`**, qui est une exigence de sécurité (§4.5 du brief) et le correctif
  direct de la faille d'injection relevée dans l'audit (§6.2). Retirer Blade
  reviendrait à réintroduire cette faille.
- **Interprétation retenue, à corriger si elle est fausse :** « HTML/CSS pur »
  porte sur les **frameworks de présentation**, pas sur le moteur de gabarits ni
  sur le back-end.

### Conséquences favorables

Ce choix résout ou simplifie plusieurs points laissés ouverts :

1. **La CSP stricte devient atteignable.** Le §4.5 du brief exige une CSP sans
   `unsafe-inline`, et je signalais qu'Alpine évalue des expressions à
   l'exécution — tension que je ne pouvais pas promettre de résoudre. Sans
   Alpine ni Livewire, la question disparaît. La CSP peut être stricte dès le
   jalon 1, à condition de bannir tout attribut `onclick` en ligne : l'audit en
   compte 12 dans le prototype, aucun ne sera porté.
2. **Le budget JavaScript s'effondre.** Le §8.5 fixait < 100 Ko compressé. Sans
   framework, on vise **moins de 15 Ko**, uniquement du code écrit pour ce
   projet.
3. **Aucune étape de construction JavaScript.** Pas de Node dans
   `Dockerfile.vercel`, image plus petite, construction plus rapide, une
   dépendance de moins à auditer.
4. **Les parcours fonctionnent sans JavaScript.** Formulaires HTML classiques,
   soumission serveur, redirection. Sur terminal modeste en 3G — l'exigence n°3
   — c'est le mode de fonctionnement le plus robuste qui soit.

### Conséquences à assumer, et comment je les traite

| Point du brief | Ce que Livewire aurait fait | Ce que je fais à la place |
|---|---|---|
| §8.3 « tokens définis une fois, jamais de valeur en dur » | `tailwind.config.js` | **Propriétés personnalisées CSS** dans `:root` d'un unique `tokens.css`. L'exigence est tenue à l'identique : une seule source, aucune valeur en dur dans une vue. |
| §8.3 bibliothèque de composants | Composants Livewire | **Composants Blade sans état** (`<x-button>`, `<x-status-badge>`, `<x-field>`…). La galerie `/dev/ui` reste au programme et garde tout son sens. |
| §8.1 brouillon auto-sauvegardé | Sauvegarde réactive à la frappe | **Persistance à chaque étape validée** : POST → enregistrement du brouillon → redirection → GET. Plus robuste sur réseau instable qu'une sauvegarde continue, et sans perte en cas de coupure entre deux étapes. |
| §8.5 états de chargement (`wire:loading`) | Directive dédiée | Désactivation du bouton et indicateur au `submit`, en JavaScript vanille, en amélioration progressive. |
| §8.2 officier : avancement dans la file sans retour au tableau de bord | Navigation réactive | Liens « suivant » calculés côté serveur, portant le contexte de filtre. |

### Le JavaScript strictement nécessaire

Trois besoins reposent sur des API du navigateur et **ne peuvent pas être
couverts par du HTML/CSS seul**. Ils seront écrits en JavaScript vanille, sans
dépendance, et isolés dans des fichiers dédiés :

1. **Capture du selfie et de la pièce d'identité** — `getUserMedia`.
   C'est le §5.2 du brief (« capture en direct »).
2. **Compression et redimensionnement des images avant envoi** — `canvas`.
   **Ce n'est pas négociable** : la décision D-008 établit que sans compression,
   l'offre gratuite Supabase sature après environ 150 demandes au lieu de 2 100.
3. **Envoi direct vers le stockage objet** par URL pré-signée — le §3.3 interdit
   de faire transiter les images par le conteneur PHP.

Si le commanditaire souhaite **zéro JavaScript**, il faut le dire : cela
impliquerait de renoncer à la capture en direct et à la compression client,
donc de revoir D-008 et la capacité du service. Je ne le fais pas de ma propre
initiative.

### Ce que ce choix ne change pas

Aucune exigence de sécurité n'est allégée. Validation serveur systématique,
échappement Blade, CSRF sur toutes les mutations, Policies et machine à états
au niveau des données : identiques. L'absence de framework front-end ne rend
rien plus permissif — elle réduit seulement la surface à auditer.


---

## D-011 — Cible d'exécution : installation locale

- **Date :** 2026-09-06
- **Statut :** décidé par le commanditaire
- **Décision :** le projet est conçu pour **fonctionner en local**, sur une
  machine, sans dépendance à un hébergeur. Cela remplace le §3 du brief
  (contraintes de plateforme Vercel) et le choix Supabase du §2.
- **Ce que cela remplace :**

  | Élément du brief | Devient |
  |---|---|
  | Vercel, runtime conteneur, FrankenPHP | Serveur local (Docker Compose, ou PHP intégré en développement) |
  | `Dockerfile.vercel`, `Caddyfile`, `vercel.json` | **Non produits** |
  | PostgreSQL hébergé sur Supabase | **PostgreSQL local**, en connexion directe |
  | Stockage objet externe (Supabase Storage / Vercel Blob) | **Disque local**, hors racine web, servi uniquement par un contrôleur autorisé |
  | Endpoints `/internal/cron/*`, drain de file par cron | **Non produits** — `queue:work` et `schedule:run` réels |

- **Ce que cela rétablit, et qui était perdu :**
  1. **Le traitement asynchrone redevient possible.** D-006 tombe. Les
     notifications partent par une file réelle, sans bloquer la requête.
  2. **Un ordonnanceur réel.** Plus de limite d'une exécution par jour.
  3. **Plus aucun plafond de capacité artificiel.** Ni 1 Go de stockage, ni
     500 Mo de base, ni 5 Go de trafic, ni mise en pause après une semaine.
  4. **Plus de clause d'usage commercial** à surveiller.
  5. `config:cache` redevient utilisable sans piéger `APP_KEY`.

- **Ce que cela ne relâche pas — point de vigilance principal :**

  Le garde-fou n°5 du brief interdit « le stockage d'une pièce d'identité sur le
  système de fichiers du conteneur ni derrière une URL publique ». Il visait
  l'éphémérité du conteneur Vercel. En local, la première moitié perd son objet
  — le disque est persistant — mais **la seconde reste absolue** :

  - les fichiers sont écrits **hors de `public/`**, dans `storage/app/private/`,
    donc inatteignables par URL directe ;
  - toute lecture passe par un **contrôleur qui vérifie la Policy avant de
    servir l'octet**, et **journalise la consultation** (§4.4) ;
  - les noms de fichiers sont des identifiants opaques, jamais dérivés du nom
    ou du numéro de pièce du citoyen ;
  - une empreinte est enregistrée en base pour détecter toute altération.

  L'abstraction Laravel `Storage` est employée telle quelle : basculer plus tard
  vers S3 ou Supabase Storage ne demandera qu'un changement de disque dans la
  configuration, sans toucher au code applicatif.

- **Ce qui ne change pas :** D-010 (HTML/CSS écrits à la main, Blade conservé)
  reste intégralement applicable. Toutes les exigences de sécurité du §4 sont
  inchangées.

- **Décision de conception liée :** l'accès aux données passe exclusivement par
  Eloquent avec le pilote `pgsql`. Le code ne contient **aucune dépendance à
  Supabase**. Passer d'un PostgreSQL local à un PostgreSQL hébergé ne doit
  demander qu'un changement de chaîne de connexion dans `.env`.

---

## Points explicitement **non** tranchés à ce stade

Ils sont listés ici pour éviter qu'une décision implicite ne s'installe.

| Sujet | Où il sera tranché |
|---|---|
| ~~Mode de connexion Supabase~~ | **Sans objet — tranché en D-011** |
| ~~Stockage des pièces d'identité~~ | **Tranché en D-011 — disque local, hors racine web** |
| ~~Version de Livewire et de Tailwind~~ | **Sans objet — tranché en D-010** |
| ~~Version de Laravel et de PHP~~ | **Tranché : Laravel 13.30.1, PHP 8.4** |
| Zéro JavaScript, ou JavaScript vanille minimal | À confirmer — voir D-010 (aucun JS écrit à ce jour) |
| Pest et Larastan | **Toujours bloqués par le réseau — voir D-012.** Fortify s'est installé au jalon 2, mais pas ces deux-là. |
| ~~Plan Vercel~~ | **Sans objet — tranché en D-011** |
| 2FA du citoyen (TOTP / SMS / aucun) | **Toujours ouvert.** Le TOTP fonctionne pour tous les rôles ; il reste facultatif pour le citoyen faute de réponse sur la faisabilité SMS. |
| ~~Défense en profondeur RLS~~ | **Clos le 2026-09-11 par D-054 : MySQL est le seul moteur maintenu.** Trois compensations implémentées (D-053) ; risque résiduel en lecture **accepté et consigné** au §7.5 de `docs/RLS.md`. Reste à porter dans l'analyse de risque remise au maître d'ouvrage — ce qui n'est pas une tâche technique. |
| Conservation du genre et des données parentales | Après avis juridique |

---

## D-012 — Tests avec PHPUnit et sans Larastan au jalon 1

- **Date :** 2026-09-06
- **Statut :** contrainte d'environnement, **à lever dès que possible**
- **Décision :** la suite de tests utilise **PHPUnit 12** (fourni par Laravel)
  au lieu de Pest, et **Larastan n'est pas installé**.
- **Cause, honnêtement :** l'installation a échoué de façon reproductible dans
  l'environnement où le jalon a été construit — `Could not authenticate against
  github.com`, et les téléchargements d'archives GitHub renvoient 403 à travers
  le relais réseau. Cinq tentatives, en `--prefer-dist` puis `--prefer-source`,
  avec et sans l'API GitHub. **Ce n'est pas une limite du projet.**
- **Ce que cela ne change pas :** les tests portent exactement sur la même
  chose. Pest est une syntaxe posée sur PHPUnit, pas un pouvoir de test
  supplémentaire. Les 82 tests couvrent les mêmes refus.
- **À faire sur une machine au réseau libre :**

  ```
  composer require --dev -W pestphp/pest:^5.0 pestphp/pest-plugin-laravel:^5.0 larastan/larastan:^3.0
  ```

  Pest 5 exige PHPUnit 13 alors que le squelette Laravel 13 épingle PHPUnit
  `^12.5.12` : il faut assouplir cette contrainte, d'où le `-W`. Ajouter
  ensuite l'étape PHPStan niveau 6 à `.github/workflows/ci.yml`.
- **Alternative écartée :** faire semblant en écrivant des tests de style Pest
  qui ne s'exécutent pas. Un test qui ne tourne pas ne prouve rien.

---

## D-013 — Laravel Fortify pour l'authentification, sans ses extras

- **Date :** 2026-09-06
- **Statut :** décidé
- **Décision :** Fortify (v1.39) est la base d'authentification, avec des vues
  écrites à la main. Fonctionnalités activées : inscription, réinitialisation
  de mot de passe, changement de mot de passe, 2FA TOTP avec confirmation.
- **Pourquoi Fortify plutôt que Breeze :** le §4.1 du brief autorise les deux.
  Breeze installe un échafaudage Tailwind + Vite, incompatible avec D-010.
  Fortify est sans vues : il fournit les routes et la logique, l'interface
  reste entièrement la nôtre.
- **Écartés volontairement :**
  - **`passkeys`** — activé par défaut dans Fortify 1.39. Le §4.1 impose TOTP
    pour les rôles officiels ; ajouter une seconde méthode d'authentification
    avant que la première ne soit éprouvée élargit la surface d'attaque sans
    besoin établi.
  - **`updateProfileInformation`** — le profil citoyen a ses propres règles
    (jalon 3), et un compte officiel ne doit pas pouvoir modifier son propre
    rattachement.
  - **`emailVerification`** — à arbitrer au jalon 3 avec le parcours citoyen.
- **Hachage :** Argon2id (`config/hashing.php`), disponible via `sodium`.
  Vérifié : les mots de passe produits commencent par `$argon2id$`.

---

## D-014 — Statut « pending » : déblocage du parcours de création de compte

- **Date :** 2026-09-06
- **Statut :** décidé, correctif d'un blocage constaté

- **Le problème, découvert par les tests :** le parcours de création d'un
  compte officiel était **impossible à terminer**.

  1. `users_official_2fa_check` interdit un compte officiel **actif** sans 2FA
     confirmée → le compte devait donc être créé inactif.
  2. Mais `EnsureAccountIsActive` déconnecte tout compte inactif → son
     titulaire ne pouvait jamais se connecter.
  3. Donc il ne pouvait jamais configurer sa 2FA.
  4. Donc l'administrateur ne pouvait jamais l'activer.

  Aucune de ces quatre règles n'est fausse prise isolément. Leur composition
  fermait la boucle. C'est exactement le genre de défaut qu'un test de parcours
  attrape et qu'une relecture de code ne voit pas.

- **Décision :** ajout d'un quatrième statut, `pending`.

  | Statut | Peut se connecter | Accès |
  |---|---|---|
  | `pending` | oui | **uniquement** la configuration 2FA et le mot de passe |
  | `active` | oui | selon son rôle |
  | `suspended` | non | déconnexion immédiate, session invalidée |
  | `disabled` | non | idem |

  `EnsureAccountIsActive` confine `pending` à une liste fermée de routes. Le
  compte ne devient `active` que par une action explicite d'un administrateur,
  et seulement une fois la 2FA confirmée.

- **Alternative écartée :** assouplir la contrainte 2FA en base. Cela aurait
  ouvert une fenêtre pendant laquelle un compte officiel actif existe sans
  second facteur — précisément ce que le §4.1 interdit.

---

## D-015 — La vérification de mots de passe compromis échoue en mode ouvert

- **Date :** 2026-09-06
- **Statut :** constaté et contourné, **à connaître**
- **Le fait, vérifié dans le code de Laravel :**
  `Illuminate\Validation\NotPwnedVerifier::search()` intercepte l'exception
  réseau, retourne un corps vide, et la règle conclut que le mot de passe n'est
  pas compromis. **Hors ligne, `uncompromised()` laisse donc passer n'importe
  quel mot de passe**, sans le moindre avertissement.

  C'est le comportement du framework, pas un défaut de notre code. Mais sur une
  installation locale sans accès internet — le cas par défaut de ce projet
  depuis D-011 — cela signifie que la protection annoncée par le §4.1 n'existe
  pas.

- **Décision :** conserver `uncompromised()` (elle a de la valeur en ligne, et
  le brief l'exige) **et** ajouter `App\Rules\NotAWeakPassword`, un plancher
  local qui fonctionne hors ligne : termes du service et du contexte
  géographique, substitutions courantes (`p@ssw0rd` → `password`), répétitions
  et suites de caractères.
- **Ce que ce plancher n'est pas :** une liste de mots de passe compromis. Une
  vraie liste se compte en centaines de millions et n'a pas sa place dans le
  dépôt. Il attrape l'évident, rien de plus, et c'est ainsi qu'il faut le lire.
- **Réglable :** `PHOENIX_CHECK_COMPROMISED_PASSWORDS` désactive l'appel
  distant quand on sait qu'il est inutile.

---

## D-016 — Deux défauts silencieux rattrapés par les tests

- **Date :** 2026-09-06
- **Statut :** corrigés, consignés parce qu'ils sont instructifs

**1. La portée globale avait disparu sans erreur.** L'attribut
`#[ScopedBy(RequestVisibilityScope::class)]` reposait sur deux `use` que le
formateur a supprimés en les jugeant inutilisés. L'attribut pointait dès lors
vers des classes inexistantes dans l'espace de noms `App\Models` — et PHP
n'émet **aucune erreur** dans ce cas : l'attribut est simplement ignoré. Toutes
les requêtes remontaient l'intégralité des demandes, tous centres confondus.

Rattrapé par le test R13. Corrigé en écrivant les noms pleinement qualifiés
dans l'attribut, ce qui ne dépend plus d'aucun `use`.

**2. Les Policies n'étaient pas appelées.** Depuis Laravel 11, le contrôleur de
base n'inclut plus `AuthorizesRequests` : tout appel à `$this->authorize()`
lève une `Error` à l'exécution. Le portail administrateur ne tenait donc que
par son middleware de rôle, la couche Policy étant morte.

Ces deux défauts partagent un trait : **ils ne produisaient aucun message.**
C'est la raison d'être des tests de refus du §4.2 du brief — un test qui ne
prouve que le cas heureux n'aurait rien vu.

---

## D-017 — Ce que la suite de tests ne peut pas prouver sur la CSRF

- **Date :** 2026-09-06
- **Statut :** limite acceptée et documentée
- **Le fait :** `PreventRequestForgery::handle()` appelle `runningUnitTests()`
  et court-circuite entièrement le contrôle dès que la suite s'exécute. **Il
  est donc impossible de prouver le rejet d'une requête sans jeton depuis un
  test HTTP.**
- **Décision :** ne pas écrire de test qui prétendrait le contraire. Le test
  vérifie ce qui est réellement vérifiable et ce qui peut réellement casser :
  que le middleware figure dans le groupe `web`, et que les formulaires portent
  le jeton.
- **Justification :** un test vert qui ne teste rien est pire que pas de test —
  il fait croire qu'une protection est vérifiée.

---

## D-018 — L'envoi des pièces passe par l'application, pas par une URL pré-signée

- **Date :** 2026-09-06
- **Statut :** décidé, écart assumé au §3.3 du brief
- **Le §3.3 demandait :** « upload direct depuis le navigateur avec URL
  pré-signée (ne fais pas transiter les images par le conteneur PHP) ».
- **Décision :** l'envoi passe par l'application.
- **Justification :** cette exigence visait l'éphémérité du conteneur Vercel et
  supposait un stockage objet capable de signer des URL. Depuis **D-011**, le
  stockage est un disque local : **il n'existe aucune URL à pré-signer**.
  Maintenir l'exigence n'aurait aucun sens.
- **Ce qui est conservé de son intention :** l'abstraction `Storage` de Laravel
  est employée telle quelle. Basculer vers S3 ou Supabase Storage ne demandera
  qu'un changement de disque en configuration, et c'est alors seulement que
  l'URL pré-signée redeviendra pertinente.
- **Ce que cela ne relâche pas :** fichiers hors de `public/`, chemins opaques
  (UUID, jamais dérivés du nom ni du numéro de pièce), type MIME relu depuis le
  **contenu** et non depuis l'extension, empreinte SHA-256 vérifiée à chaque
  lecture, Policy vérifiée avant de servir le premier octet, consultation
  journalisée.

---

## D-019 — La CSP refusait mes propres styles en ligne, en silence

- **Date :** 2026-09-06
- **Statut :** corrigé
- **Le fait :** la CSP posée au jalon 2 comprend `style-src 'self'`, qui refuse
  tout attribut `style=` en ligne. Or les vues en contenaient quatorze. Ces
  styles n'étaient **pas appliqués** : les pages s'affichaient mal, sans
  qu'aucun test ne le signale et sans erreur visible côté serveur.
- **Comment il a été trouvé :** en exécutant l'application dans un navigateur
  et en lisant la console, pas par la suite de tests, qui était au vert.
- **Correction :** les quatorze attributs sont devenus des classes utilitaires
  dans `app.css`, ce qui satisfait aussi le §8.3 (aucune valeur de présentation
  en dur dans une vue). Un test refuse désormais tout retour d'un `style=`, et
  il a été éprouvé avec une violation délibérée.
- **Ce que cela rappelle :** une suite verte prouve ce qu'elle teste, pas que
  l'application fonctionne. Ouvrir les écrans reste nécessaire.

---

## D-020 — La compression navigateur, mesurée

- **Date :** 2026-09-06
- **Statut :** vérifié
- **Mesures réelles**, dans Chromium, avec le script du projet :

  | Image de test | Avant | Après | Réduction |
  |---|---:|---:|---:|
  | Photo réaliste (dégradés, formes) | 277 Ko | **35 Ko** | ÷ 7,9 |
  | Bruit aléatoire pur, 3000 × 2200 | 8,9 Mo | **552 Ko** | ÷ 16,5 |

  Le second cas est le pire absolu pour JPEG et n'atteint pas la cible de
  250 Ko : l'échelle de qualité s'arrête à 0,42 et le fichier part tel quel.
  C'est délibéré — mieux vaut une image un peu lourde que pas d'image. Aucune
  photographie réelle ne ressemble à du bruit aléatoire.
- **Repli :** sans JavaScript, le champ « fichier » reste utilisable et le
  formulaire fonctionne. La compression disparaît, la validation serveur non.

---

## D-021 — Les déclencheurs des adaptateurs factices ignorent la ponctuation

- **Date :** 2026-09-06
- **Statut :** corrigé
- **Le fait :** les cas de test des adaptateurs se déclenchent par préfixe du
  numéro de pièce. Je les avais écrits avec des tirets — `DEMO-DOWN` — alors
  que le numéro est stocké après passage par `BlindIndex::normalise()`, qui
  supprime tout ce qui n'est ni lettre ni chiffre. La valeur relue vaut
  `DEMODOWN` : **aucun déclencheur ne se serait jamais activé.**
- **Correction :** les préfixes sont écrits sans ponctuation, et l'adaptateur
  applique la **même** normalisation que le stockage. Un test vérifie que
  `DEMO-DOWN-1`, `demo down 1`, `DEMO.DOWN.1` et `DemoDown1` déclenchent tous
  le même cas.
- **Ce que cela rappelle :** une normalisation appliquée à un endroit doit
  l'être partout où la valeur est comparée. C'est la raison pour laquelle
  `BlindIndex::normalise()` est centralisée et testée — mais encore faut-il
  s'en servir.

---

## D-022 — Étendue de la barrière anti-fraude de l'étape 5

- **Date :** 2026-09-06
- **Statut :** décidé
- **Décision :** l'acceptation d'une demande (transition T4) exige que **les 5
  étapes de vérification aient un résultat enregistré**. Le rejet et
  l'escalade restent possibles à tout moment.
- **Pourquoi cette règle n'est pas dans le déclencheur PostgreSQL :** le
  déclencheur valide un couple `(statut source, statut cible)`. Cette règle-ci
  porte sur le **contenu** de `verification_steps`, pas sur les statuts. La
  poser en base demanderait au déclencheur de compter des lignes d'une autre
  table à chaque mise à jour — coûteux, et fragile si le cycle change.
  Elle est donc appliquée dans `DecisionController` **et testée** (R5, R5bis).
- **Asymétrie assumée :** rejeter sans avoir tout vérifié est autorisé. Un
  dossier manifestement irrecevable — pièce illisible, centre incompétent — ne
  doit pas obliger l'officier à dérouler cinq étapes pour rien. Le motif reste
  obligatoire, et il figure au journal.
- **`provider_unavailable` compte comme un résultat.** Une panne externe ne
  bloque donc pas l'officier (§9 du brief), mais elle laisse une trace : le
  journal montre exactement sur quoi la décision reposait.

---

## D-023 — Consultation d'un dossier par un collègue du même centre

- **Date :** 2026-09-06
- **Statut :** décidé — c'était le point ouvert du §5 de `PERMISSIONS.md`
- **Décision :** un officier peut **consulter** un dossier de son centre pris
  en charge par un collègue. La consultation est journalisée. La **décision**
  reste réservée à l'officier assigné.
- **Justification :** le cloisonnement total bloquerait le service dès qu'un
  agent est absent. Le journal rend l'élargissement d'accès contrôlable *a
  posteriori*, ce qui est le bon compromis entre le §4.2 et la réalité d'un
  service public.
- **Visible dans l'interface :** l'écran affiche « Lecture seule » et le nom de
  l'agent en charge, plutôt que de masquer silencieusement les boutons.

---

## D-024 — Générateur PDF écrit à la main, faute de pouvoir installer dompdf

- **Date :** 2026-09-07
- **Statut :** décidé sous contrainte, **à remplacer**
- **Décision :** `App\Support\Pdf\PdfDocument` produit les actes. C'est un
  générateur PDF minimal, texte seulement.
- **Cause :** `composer require dompdf/dompdf` échoue depuis l'environnement de
  construction, comme Pest et Larastan avant lui (voir D-012). Ce n'est pas une
  limite du projet.
- **Ce qu'il fait :** pages A4, Helvetica et Helvetica-Bold, filets, encodage
  WinAnsi (donc les accents français), échappement des caractères réservés,
  découpe des paragraphes, pagination automatique.
- **Ce qu'il ne fait pas :** images, tableaux, couleurs, polices embarquées,
  compression, retour à la ligne typographique. Il n'y a **aucune raison de
  l'étendre** : dès que le réseau le permet,

  ```
  composer require dompdf/dompdf
  ```

  et un gabarit Blade le remplacent avantageusement. Le seul point d'attention
  au remplacement : la mention obligatoire de D-025 doit rester la première
  chose écrite.
- **Vérification :** la sortie est relue par **pdftotext**, un outil
  indépendant, dans `ActDocumentTest`. Je ne me contente pas de supposer que
  le PDF est valide — l'alignement des libellés et les accents sont contrôlés.

---

## D-025 — Tout acte de démonstration porte la mention en clair

- **Date :** 2026-09-07
- **Statut :** décidé — **exigence de sécurité, pas précaution de développement**
- **Décision :** tant que l'adaptateur de signature est factice, chaque acte
  produit porte, **en toute première ligne**, la mention
  `DOCUMENT DE DEMONSTRATION - SANS VALEUR JURIDIQUE`, suivie de
  « Ce document ne peut etre presente a aucune administration. » Elle est
  répétée en fin de document et sur la preuve de signature.
- **Où elle est apposée :** dans `DocumentBuilder`, **pas** dans l'adaptateur
  de signature. Un document doit la porter même si quelqu'un contourne
  l'adaptateur.
- **Pourquoi :** la question A1 de `COMPLIANCE_OPEN_QUESTIONS.md` est ouverte —
  on ignore si un acte d'état civil signé électroniquement a valeur légale au
  Cameroun. Un document produit ici ne doit pouvoir être confondu avec un acte
  authentique par personne, y compris par un agent de bonne foi.
- **Vérifié :** un test relit le PDF avec pdftotext et exige que la mention
  soit la **première ligne non vide** du document.
- **Levée :** la mention ne disparaîtra que le jour où un prestataire agréé
  sera branché **et** la question A1 tranchée. Les deux, pas l'un des deux.

---

## D-026 — `@disabled` sur une balise de composant casse Blade

- **Date :** 2026-09-07
- **Statut :** corrigé, et l'écran cassé était en production depuis le jalon 3
- **Le fait :** `<x-button @disabled(! $complet)>` **ne compile pas**. Le
  compilateur de balises de composants ne traite pas les directives présentes
  dans la liste d'attributs, et produit du PHP déséquilibré. Le symptôme est
  une `ParseError` sur un `endif` situé des dizaines de lignes plus loin, ce
  qui n'oriente vers rien.

  Vérifié par réduction : `<button @disabled(...)>` (HTML) compile ;
  `<x-button @disabled(...)>` (composant) échoue.

- **Ce que cela a coûté :** deux vues étaient concernées, dont **l'écran
  d'envoi de la demande citoyenne**, cassé depuis le jalon 3. Aucun test ne le
  **rendait** : ils postaient directement vers la route. J'ai annoncé ce jalon
  comme fonctionnel alors que sa dernière page renvoyait une erreur 500.
- **Correction :** `x-button` accepte désormais une propriété `disabled`, et
  `@disabled` est appliqué à l'intérieur du composant, sur un `<button>` HTML,
  où il fonctionne.
- **Prévention :** deux tests. L'un compile **chacune des 45 vues** du projet.
  L'autre refuse toute directive dans une balise de composant, et a été éprouvé
  avec une violation délibérée.
- **La leçon, encore :** une suite verte prouve ce qu'elle teste. Poster vers
  une route ne rend pas la page.

---

## D-027 — La cinquième étape est la décision, pas son propre préalable

- **Date :** 2026-09-07
- **Statut :** corrigé, et le parcours officier était bloqué depuis le jalon 4
- **Le fait :** `VerificationWorkflow::isComplete()` exigeait un résultat
  enregistré pour les **cinq** étapes avant d'autoriser l'acceptation (T4). Or
  la cinquième étape s'appelle « Décision » et **aucun chemin HTTP ne
  l'enregistre** : `acknowledge` n'accepte que les étapes 1 et 3, l'étape 2
  vient de la base de la police, l'étape 4 du registre. La cinquième ne pouvait
  donc naître que de la décision elle-même, qu'elle bloquait.

  Conséquence : **aucune demande ne pouvait quitter `under_review` par
  l'interface.** Le bouton « Accepter » était rendu `disabled` en permanence,
  et le contrôleur aurait refusé de toute façon. Le parcours complet du service
  était interrompu depuis le jalon 4.

- **Pourquoi aucun test ne l'a vu :** tous les tests qui avaient besoin d'une
  demande acceptée appelaient `VerificationWorkflow::record()` **directement**,
  pour les cinq étapes. C'est légitime pour tester une règle — mais aucun test
  ne parcourait le chemin réel, et le chemin réel n'existait pas. Le test
  `r5bis_quatre_etapes_sur_cinq_ne_suffisent_pas` **passait au vert en
  constatant précisément le blocage**, qu'il prenait pour la règle.

- **Correction :** `VerificationWorkflow::VERIFICATION_STEPS` vaut `[1, 2, 3, 4]`.
  Ce sont ces quatre-là qui doivent porter un résultat avant une acceptation.
  La cinquième étape reste le cinquième **écran** — celui de la décision — et
  la décision est enregistrée dans `request_decisions`, pas dans
  `verification_steps`.

- **Ce que cela ne desserre pas :** la barrière anti-fraude est intacte. Aucune
  acceptation n'est possible sans que les quatre vérifications aient un
  résultat enregistré, nominatif et horodaté. L'ensemble des états atteignables
  ne s'élargit que du seul chemin que le brief prévoit.

- **Prévention :** `tests/Feature/EndToEnd/CompleteJourneyTest.php` parcourt le
  brouillon jusqu'à l'acte signé **par les routes seulement**, sans appeler un
  seul service applicatif, et affiche chaque écran avant de le poster. C'est ce
  test qui a révélé le blocage, à la première exécution.

- **La leçon :** un test qui met en place son état par les services teste une
  règle, pas un chemin. Il faut au moins un test qui ne connaisse que les URL.

---

## D-028 — Divergence à trancher sur la condition de T5

- **Date :** 2026-09-07
- **Statut :** **ouvert — signalé, non tranché de ma propre autorité**
- **Le fait :** `docs/STATE_MACHINE.md` donne à T5 (rejet par l'officier) la
  même condition qu'à T4 : « les 5 étapes ont un résultat enregistré ». Le code
  ne l'applique pas : il n'exige les vérifications que pour une **acceptation**.
  Un rejet ou une escalade ne demandent qu'un motif d'au moins dix caractères.
- **Pourquoi je ne tranche pas seul :** les deux lectures se défendent. Exiger
  les quatre vérifications avant un rejet garantit qu'aucun dossier n'est
  écarté sans avoir été instruit — mais impose quatre contrôles inutiles pour
  un motif comme « la demande relève d'un autre centre d'état civil », ce qui
  contredit le §8.2 du brief. Ne pas les exiger rend le rejet plus rapide que
  l'acceptation, ce qui est un déséquilibre à assumer explicitement.
- **En attendant :** le code reste tel quel, le motif est obligatoire, la
  contrainte `request_decisions_reason_required_check` l'impose en base, et
  toute décision est journalisée. La divergence est signalée ici et dans
  `STATE_MACHINE.md` plutôt que masquée par un alignement silencieux.

---

## D-029 — Le disque « local » de Laravel publiait une route sur le stockage privé

- **Date :** 2026-09-07
- **Statut :** corrigé
- **Le fait :** `config/filesystems.php` définit, dans le squelette Laravel, un
  disque `local` enraciné sur `storage_path('app/private')` **avec
  `'serve' => true`**. Cette clé publie une route `GET /storage/{path}` sur ce
  répertoire — celui-là même où vivent les pièces d'identité et les actes
  signés. C'est le répertoire que le garde-fou n°5 interdit d'exposer.
- **Ce qui ne fuyait pas :** la route exige une signature d'URL, parce que la
  clé `visibility` est absente du bloc et que Laravel retombe alors sur
  `private`. Vérifié en conditions réelles : `GET /storage/acts/…/acte.pdf`
  sans session répond **403**. Rien n'a fuité.
- **Ce qui était quand même faux, et c'est le point :** avec une URL signée, la
  route sert le fichier **sans consulter la Policy et sans écrire la ligne
  d'audit**. Elle contourne exactement le contrôleur écrit pour cela. Et le
  seul rempart restant était l'absence d'une clé — celle que porte le bloc
  `public` situé six lignes plus bas, d'où un copier-coller suffirait à
  ouvrir l'ensemble.
- **Correction :** le disque `local` est réenraciné sur `storage/app/local` et
  n'est plus servi. Rien dans l'application ne l'utilise — vérifié. Le repli du
  disque par défaut passe de `local` à `private` : une variable
  d'environnement absente ne doit pas ouvrir un accès.
- **Prévention :** `Security\ExposedRoutesTest` vérifie la **configuration**,
  pas des URL : aucun disque servi ne peut couvrir le stockage privé, aucune
  route `GET` hors liste blanche n'est joignable sans session. Éprouvé en
  réintroduisant délibérément le défaut : les deux tests le rattrapent, par
  deux chemins indépendants.
- **La leçon :** un défaut de configuration hérité d'un squelette ne se voit
  dans aucune revue de code applicatif. Il faut le chercher dans la table de
  routage.

---

## D-030 — Le filtre de compte actif manquait sur les routes de Fortify

- **Date :** 2026-09-07
- **Statut :** corrigé
- **Le fait :** `EnsureAccountIsActive` était posé sur le groupe de routes
  applicatives déclaré dans `routes/web.php`. Or Fortify enregistre ses propres
  routes authentifiées, qui n'y sont pas. **Un agent suspendu y gardait la
  main** : lecture de sa clé secrète 2FA, de son QR code et régénération de ses
  codes de secours, jusqu'à sa déconnexion. Constaté, pas déduit — trois routes
  répondaient encore `200` à un compte passé en `suspended`.
- **Pourquoi R14 ne l'a pas vu :** les tests de R14 vérifiaient la connexion et
  une session en cours **sur les routes applicatives**. Aucun ne sortait de ce
  périmètre.
- **Correction :** le filtre est posé sur le groupe `web` entier, dans
  `bootstrap/app.php`. Il est sans effet sur un visiteur anonyme et précède
  `auth`, l'utilisateur de session lui suffisant. Il est **retiré** du groupe
  de `routes/web.php` : l'y laisser le ferait s'exécuter deux fois et
  journaliser deux fois la révocation d'une session.
- **Prévention :** `Security\AccountLifecycleTest` teste quatre routes de
  Fortify **et** exige, structurellement, que *toute* route authentifiée porte
  le filtre. Ce second test a lui-même dû être corrigé : il lisait les groupes
  de middleware depuis le routeur, qui ne les connaît qu'une fois le noyau HTTP
  démarré — il passait donc au vert en ne regardant rien. Il les lit désormais
  depuis le noyau, et échoue bien quand on retire la correction.
- **La leçon :** un filtre posé sur « toutes les routes » ne couvre que les
  routes qu'on a écrites. Les paquets en ajoutent.

---

## D-031 — Une acceptation sous réserve exige un motif

- **Date :** 2026-09-07
- **Statut :** appliqué au jalon 6 — **durcissement, réversible sur demande**
- **Le fait constaté :** un test du jalon 6 a établi qu'un `no_match` de la
  base de la police n'empêchait pas l'acceptation, et que rien, au moment de la
  décision, ne distinguait cette acceptation d'une acceptation ordinaire :
  aucun avertissement, aucun motif exigé. C'est pourtant le chemin exact d'une
  fraude à la pièce volée — le cas `DEMOSTOLEN` de l'adaptateur factice.
- **Ce que je n'ai pas fait, et pourquoi :** interdire l'acceptation. Le §6
  d'`INTEGRATIONS.md` pose que l'officier reste responsable de son jugement, et
  une non-correspondance a des causes légitimes — une faute de frappe dans un
  numéro, un service qui répond mal, un registre lacunaire. Interdire ferait
  décider la machine à la place de l'agent, et pousserait à saisir de faux
  résultats pour débloquer un dossier.
- **Ce que je fais :** dès qu'une des quatre vérifications rend autre chose
  qu'une correspondance, **le motif devient obligatoire pour toute décision,
  acceptation comprise**. L'écran de l'officier annonce la réserve avant la
  saisie ; l'écran du maire l'affiche avant la signature, avec le motif.
- **Ce que cela change dans la machine à états :** rien. Aucune transition
  n'est ajoutée, aucune n'est retirée. Une seule condition est ajoutée, et elle
  ne fait que **restreindre** ce qui peut se produire en silence.
- **Coût pour l'agent :** nul dans le cas nominal — une acceptation sans
  réserve ne réclame toujours aucun motif, conformément au §8.2 du brief.
- **Réversibilité :** c'est un durcissement que j'ai décidé dans le cadre du
  jalon 6. S'il vous paraît trop contraignant, il tient dans une condition du
  contrôleur et deux blocs de vue.

---

## D-032 — Notifications : deux canaux, deux travaux, aucune emprise sur l'état

- **Date :** 2026-09-07
- **Statut :** appliqué au jalon 6
- **Ce qui est fait :** un centre de notifications dans l'application (canal
  `database`) et un courriel (canal `mail`), déclenchés depuis
  `RequestTransitionService` — **le seul point d'écriture du statut**. Aucun
  contrôleur ne peut donc oublier de prévenir : tout chemin qui change l'état
  notifie, y compris ceux qu'on écrira plus tard.
- **Trois précautions, dans cet ordre :**
  1. **Après le commit.** Une transition annulée ne notifie personne — vérifié
     par un test qui provoque une transition refusée.
  2. **Mise en file.** L'envoi réel a lieu dans le worker.
  3. **Enveloppée.** Même l'insertion en file est protégée : une base de file
     indisponible ne doit pas annuler une décision d'officier déjà journalisée
     et déjà appliquée. C'est ce que D-006 interdit. L'échec est journalisé
     sans donnée personnelle.
- **Mesuré, pas supposé.** Avec la file `database` réelle et **aucun serveur
  de courriel** : deux travaux sont créés, celui du canal `database` réussit,
  celui du canal `mail` échoue seul et part dans `failed_jobs`, la notification
  reste lisible dans l'application, et la demande reste dans son nouvel état.
  Les deux canaux sont des travaux distincts — un serveur de courriel mort ne
  coûte que le courriel. Reprise : `php artisan queue:retry all`.
- **Ce que les notifications ne portent pas :** le motif. Rédigé par un agent,
  il peut mentionner des éléments du dossier. Le demandeur le lit **connecté**,
  sur la page de sa demande ; il ne part pas par courriel, où il quitterait le
  système sans contrôle (garde-fou n°6). Un test compare le motif au contenu du
  courriel et à celui de la ligne de notification.
- **Qui est prévenu :** le demandeur à chaque changement d'état ; l'officier qui
  tient le dossier **en plus**, quand le maire le lui retourne — c'est une
  consigne de travail, pas une information.
- **Ce que je n'ai pas fait, à dessein :** prévenir tous les officiers d'un
  centre à chaque dépôt. Leur file est déjà l'outil de travail, et une
  notification par demande la noierait.

---

## D-033 — Le SMS manque, et c'est un vrai trou

- **Date :** 2026-09-07
- **Statut :** **ouvert — question posée, non tranchée**
- **Le fait :** les quatre intégrations prévues sont la police, l'état civil,
  la signature et le paiement. **Aucun fournisseur de SMS.** Or le §8.1 du brief
  vise des citoyens à faible littératie numérique sur réseau contraint : pour
  eux, le SMS est le canal qui arrive vraiment, et le courriel souvent pas.
- **Ce que cela veut dire concrètement :** un demandeur sans adresse
  électronique valide n'est prévenu **que** s'il revient se connecter. Le
  centre de notifications le sert alors correctement, mais il ne le tire pas.
- **Ce qu'il faudrait décider :** un fournisseur SMS existe-t-il, à quel coût
  par message, et qui le paie ? C'est une **cinquième intégration**, avec son
  contrat, son adaptateur factice et son mode dégradé — pas une option de
  configuration.
- **En attendant :** rien n'est inventé. `via()` n'ajoute le courriel que si une
  adresse existe ; le canal applicatif reste le seul dont l'arrivée est
  garantie, et c'est dit tel quel.

---

## D-034 — L'audit d'accessibilité se refait au navigateur, à chaque jalon

- **Date :** 2026-09-07
- **Statut :** appliqué au jalon 6
- **Ce qui est mesuré :** 17 écrans, dans Chromium à 390 × 844 px, avec
  `axe-core` 4.10.2 sur les jeux `wcag2a`, `wcag2aa`, `wcag21a`, `wcag21aa`,
  plus le parcours au clavier, le débordement horizontal et la taille de
  **chaque** cible tactile. Résultat après correction : **0 violation, 0
  débordement, 0 cible sous 44 px**. Détail dans `docs/ACCESSIBILITE.md`.
- **Ce que l'audit a trouvé :** le tableau de bord de l'administrateur
  renvoyait **500 depuis le jalon 2** ; les treize tableaux larges n'étaient pas
  défilables au clavier ; trois cibles tactiles étaient trop petites, dont une
  sous le minimum de 24 px de la WCAG 2.2 AA.
- **Pourquoi les tests ne l'avaient pas vu :** `ViewCompilationTest` prouve que
  les vues **compilent**. Rendre une vue est autre chose. `ScreensRenderTest`
  ouvre désormais chaque écran de chaque rôle, sur une base peuplée.
- **Reproduire l'audit :**
  ```
  php artisan serve --port=8231
  npm install playwright axe-core        # hors du dépôt
  node a11y.mjs                          # script d'audit, docs/ACCESSIBILITE.md 1
  ```
  Les scripts vivent hors du dépôt : ils dépendent de comptes de démonstration
  et d'un serveur local, pas du code livré.
- **Ce que cela ne prouve pas :** la conformité. Un outil automatique détecte
  de l'ordre du tiers des problèmes réels. L'essai avec un lecteur d'écran et
  avec des utilisateurs à faible littératie numérique reste à faire, et c'est
  écrit comme tel.

---

## D-035 — Le coût SQL se mesure deux fois, et la compression est une exigence

- **Date :** 2026-09-07
- **Statut :** appliqué au jalon 6
- **Ce qui est mesuré :** 515 demandes, 513 comptes, 1 711 entrées d'audit.
  Temps serveur **27 à 38 ms** sur les sept écrans de liste, jamais croissant
  avec le volume. CSS **6,4 Ko** compressé pour un budget de 50 Ko ; JS
  **2,9 Ko** pour 100 Ko. Détail dans `docs/PERFORMANCE.md`.
- **La méthode qui compte :** un test de N+1 ne vaut rien s'il mesure une seule
  fois. `QueryBudgetTest` rend chaque écran avec **peu** puis **beaucoup** de
  lignes et exige le **même** nombre de requêtes. C'est la seule définition
  utile : non pas « il y a beaucoup de requêtes », mais « il y en a une de plus
  par ligne ».
- **Ce que la mesure a corrigé :** la file de l'officier chargeait la relation
  `citizen` par anticipation alors que la vue ne l'ouvre jamais — une requête
  par page pour rien. Le nom affiché vient d'une colonne.
- **Un test vert pour de mauvaises raisons, encore :** mon premier jeu d'essai
  créait des dossiers non pris en charge. La relation susceptible de coûter une
  requête par ligne restait nulle, donc jamais interrogée. Corrigé, puis éprouvé
  en retirant le chargement anticipé : 7 requêtes pour 3 dossiers, 29 pour 30.
- **La compression n'est pas un réglage de confort :** le balisage est très
  répétitif et se comprime d'un facteur **24** sur la page des comptes —
  79,7 Ko bruts contre **3,3 Ko** compressés. Sans `gzip` ou `brotli` sur le
  serveur web, le §8.3 n'est pas tenu. `php artisan serve` ne compresse pas ;
  c'est écrit dans `ARCHITECTURE_LOCAL.md` comme point à vérifier au
  déploiement.
- **Ce que ces chiffres ne disent pas :** ils sont pris en local, sur cette
  machine, en mono-utilisateur. Ni un réseau 3G réel, ni un téléphone d'entrée
  de gamme, ni une montée en charge.

---

## D-036 — Une restauration se vérifie autrement qu'en comptant des lignes

- **Date :** 2026-09-07
- **Statut :** appliqué au jalon 6 — procédure **exécutée**, pas seulement écrite
- **Ce qui existe :** `scripts/sauvegarde.sh`, `scripts/restauration.sh` et la
  commande `php artisan phoenix:verifier-restauration`. Détail dans
  `docs/SAUVEGARDE.md`.
- **Le point de la commande de vérification :** une base restaurée peut rendre
  le bon nombre de lignes et rester inexploitable. Sans `APP_KEY`, les numéros
  de pièce sont illisibles. **Sans la clé d'index aveugle, la recherche par
  numéro ne lève aucune erreur : elle rend zéro résultat** — un officier en
  conclurait que la pièce est inconnue. Sans le stockage privé, chaque acte
  signé désigne un fichier absent.
- **Exécuté :** 515 demandes, 513 comptes, 1 715 entrées de journal, 1 acte
  signé, 1 pièce jointe restaurés dans une base d'essai. Inventaire identique,
  cinq contrôles au vert.
- **Éprouvé en provoquant chaque défaillance :** mauvaise `APP_KEY`, mauvaise
  clé d'index, stockage non restauré, fichier d'acte altéré d'un seul octet.
  Les quatre sont détectés, avec un message qui nomme la cause.
- **Ce que le script refuse de faire :** écraser la base en service sans une
  variable d'environnement explicite, et créer une base — `phoenix_owner` n'a
  pas `CREATEDB`, et c'est voulu ; le script indique la commande à faire
  exécuter par un administrateur.
- **Ce que je ne décide pas :** la périodicité des sauvegardes, leur lieu de
  conservation et leur durée de rétention. Les archives contiennent des données
  d'identité **et** les clés de déchiffrement : ce sont des décisions de
  service, liées au bloc B de `COMPLIANCE_OPEN_QUESTIONS.md`.

---

## D-037 — RLS : évalué en le construisant, recommandé, non adopté dans ce jalon

- **Date :** 2026-09-07
- **Statut :** **évaluation livrée — décision d'adoption à prendre par vous**
- **Ce qui a été fait :** le prototype a été construit, appliqué à une copie de
  la base et mesuré. Il est conservé dans `docs/prototypes/rls/`, en `.txt`
  pour qu'il ne s'exécute ni en migration ni en test. Analyse complète dans
  `docs/RLS.md`.
- **La protection est démontrée, pas supposée :** avec la portée globale
  entièrement contournée, un officier du centre A voit **2 demandes sur 2** sans
  politique et **1 sur 2** avec. Une écriture hors périmètre affecte 0 ligne.
  C'est exactement le défaut de D-013, où la portée globale avait disparu sans
  bruit parce que Pint avait retiré les `use`.
- **Le coût en performance est nul :** +0,2 ms de planification sur 515
  demandes, exécution dans le bruit de mesure.
- **Le coût réel est ailleurs :** politique naïve, **157 tests sur 340 en
  échec**. Politique affinée avec un contexte `system`, **340 sur 340** — mais
  ce second chiffre ne prouve rien, car les tests passent alors **en
  contournant** la politique. Des tests qui posent un vrai contexte restent à
  écrire.
- **Pourquoi je n'adopte pas maintenant :** avec un mutualiseur de connexions en
  mode transaction, une variable de session qui survit à la transaction devient
  une fuite **entre utilisateurs**. Mal posée, la politique **créerait** la
  fuite qu'elle doit empêcher. Cette question d'exploitation doit être tranchée
  avant, pas après.
- **Recommandation :** adopter, dans un jalon court et dédié, dont le contenu
  est la liste de `docs/RLS.md` §5 et le critère d'acceptation le test du §3.1.

---

## D-038 — L'argent est un entier d'unités mineures, jamais un flottant

- **Date :** 2026-09-10
- **Statut :** appliqué au jalon 7
- **Le fait :** `0.1 + 0.2` ne vaut pas `0.3` en binaire. Un service public qui
  encaisse ne peut pas dériver d'un centime par arrondi, ni justifier un écart
  de caisse par une représentation flottante.
- **Décision :** `App\Support\Money` porte un **entier** d'unités mineures, une
  devise ISO 4217 explicite et un nombre de décimales. La colonne est un
  `bigint`. La seule opération produisant une virgule est le rendu à l'écran.
- **Le franc CFA n'a pas de subdivision en usage :** `minor_unit` vaut 0, et
  1 000 F s'écrit `1000`.

---

## D-039 — Sans tarif configuré, la plateforme refuse d'encaisser

- **Date :** 2026-09-10
- **Statut :** appliqué au jalon 7
- **Le fait :** le tarif d'une réédition est une donnée **réglementaire**. Le
  prototype affichait 20 000 CFA, valeur que je n'ai jamais pu vérifier
  (D-003). La question 1 d'`INTEGRATIONS.md` §5 est toujours sans réponse.
- **Décision :** `PHOENIX_PAYMENT_AMOUNT_MINOR` n'a **aucune valeur par
  défaut**. Si l'encaissement est activé sans tarif, `Money::fromConfig()` lève
  une exception nommant la question ouverte, et **aucun encaissement n'est
  ouvert**. La plateforme refuse de servir plutôt que de facturer un chiffre
  inventé.
- **Pourquoi le refus plutôt qu'un défaut :** un défaut de 0 ferait croire à la
  gratuité ; un défaut non nul serait un tarif inventé. Le §10 du brief
  interdit de coder une hypothèse réglementaire.
- **Vérifié :** un test échoue si un montant à quatre chiffres accolé à une
  mention monétaire réapparaît dans `app/` ou dans les vues ; éprouvé en
  réintroduisant délibérément « 20000 F CFA ».

---

## D-040 — Le paiement a son propre cycle de vie, à côté de la demande

- **Date :** 2026-09-10
- **Statut :** appliqué au jalon 7
- **La question qu'il ne fallait pas trancher :** le paiement précède-t-il
  l'envoi de la demande, ou sa signature ? C'est la question 3
  d'`INTEGRATIONS.md` §5, **sans réponse**, et c'est une décision de service.
- **Décision :** le paiement n'est **pas** un état de la demande. Il a sa
  propre machine — `pending`, `authorised`, `settled`, `failed`, `expired`,
  `refunded` — gardée par son propre déclencheur PostgreSQL. Le moment où le
  paiement est exigé devient un **réglage** (`PHOENIX_PAYMENT_GATE`), dont la
  valeur par défaut est `none` : tant que rien n'est tranché, rien n'est
  encaissé.
- **Ce que cela évite :** figer dans la machine à états des demandes une
  réponse qui n'existe pas. Le jour où la question est tranchée, c'est une
  condition à vérifier, pas une architecture à refaire.
- **`authorised` n'est pas `settled` :** un ordre pris en compte par un
  opérateur ne vaut pas des fonds acquis. Seul `settled` vaut paiement, et un
  test le vérifie — c'est la confusion la plus coûteuse d'un encaissement
  mobile.
- **Idempotence :** les opérateurs de paiement mobile **rejouent** leurs
  rappels. Un rappel annonçant l'état déjà enregistré n'écrit ni transition ni
  seconde ligne d'audit, et une demande n'a qu'un encaissement vivant à la
  fois. Vérifié par un test qui rejoue le même événement quatre fois.
- **Le remboursement existe, la politique de remboursement non.** `refund()`
  rend l'opération possible et tracée ; elle n'est appelée par aucun
  automatisme. La question 4 d'`INTEGRATIONS.md` §5 reste ouverte.

---

## D-041 — Les deux placements de la barrière de paiement sont construits

- **Date :** 2026-09-10
- **Statut :** appliqué au jalon 7
- **La question :** le paiement précède-t-il l'envoi de la demande, ou sa
  signature ? C'est la question 3 d'`INTEGRATIONS.md` §5, **sans réponse**, et
  c'est une décision de service.
- **Décision :** ne pas la trancher, et construire **les deux**. Le placement
  est le réglage `PHOENIX_PAYMENT_GATE` — `none`, `before_submission` ou
  `before_signature` — et chacun est implémenté, testé, et vérifié au
  navigateur. Le défaut est `none`.
- **Le coût assumé :** un peu de travail en double. Le bénéfice : le jour où la
  question est tranchée, c'est une variable d'environnement, pas un jalon.
- **Le risque propre au fait d'avoir les deux**, et le test qui le couvre :
  qu'une barrière s'applique aux **deux** endroits et fasse payer deux fois. Un
  test vérifie explicitement que le placement non configuré ne bloque rien.
- **Un seul écran** sert les deux cas ; seul le moment où l'on y arrive change.
- **Une valeur inconnue échoue bruyamment.** Retomber silencieusement sur
  « aucun paiement » ferait d'une faute de frappe une gratuité générale ;
  retomber sur « paiement exigé » bloquerait le service.
- **Le rapprochement à la demande du citoyen :** un rappel d'opérateur peut se
  perdre. Sans le bouton « actualiser », un règlement effectivement acquitté
  resterait « en attente » indéfiniment et le demandeur n'aurait d'autre
  recours qu'un guichet.

---

## D-042 — Le reçu porte la même mention que l'acte, et pour la même raison

- **Date :** 2026-09-10
- **Statut :** appliqué au jalon 7
- **Décision :** tant que l'encaissement passe par l'adaptateur factice, le
  reçu porte en **première ligne** « RECU DE DEMONSTRATION — AUCUNE SOMME N'A
  ETE ENCAISSEE ». Vérifié en relisant le PDF avec `pdftotext`, comme pour
  l'acte (D-025).
- **La base réglementaire n'est imprimée que si elle est configurée.** On
  n'imprime pas un fondement juridique qu'on ne peut pas citer (§10 du brief).
  Deux tests : l'un exige son absence quand elle n'est pas renseignée, l'autre
  sa présence quand elle l'est.
- **Un défaut trouvé par ce test :** la colonne `provider` recevait le *choix
  d'adaptateur* (`fake`) et non le nom que l'adaptateur se donne
  (`fake-mobile-money`). La comparaison qui déclenche la mention ne
  correspondait donc jamais, et **le reçu n'aurait porté aucune mention**.
  C'est exactement le piège de D-021. Le nom rendu par l'adaptateur est
  désormais enregistré dès sa première réponse.

---

## D-043 — Le diagramme de cas d'utilisation devient la référence

- **Date :** 2026-09-10
- **Statut :** appliqué
- **Décision :** le diagramme de cas d'utilisation fourni sert désormais de
  référence au développement. La traçabilité cas par cas, l'état de chacun et
  les divergences avec le brief sont dans `docs/CAS_USAGE.md`.
- **Quatre divergences sont signalées, aucune n'est tranchée seul :**
  le diagramme confie les réglages système **au maire** et ne comporte aucun
  administrateur, ce qui défait la séparation du §4.2 ; « Generate
  Certificate » est placé chez l'officier alors que l'acte naît de la
  signature ; un acteur **Facial Recognition AI** apparaît, avec tout ce qu'un
  traitement biométrique implique ; et **GDNS** est un sigle dont je ne connais
  pas la signification et que je n'inventerai pas.
- **Ce qui est construit sans attendre :** « Cancel Request », le seul cas dont
  le manque était un trou avéré — la Policy `delete` existait depuis le jalon 3
  **sans aucune route pour l'appeler**, donc T12 n'a jamais été atteignable.

---

## D-044 — Annuler n'est pas rejeter, et n'est pas effacer

- **Date :** 2026-09-10
- **Statut :** appliqué
- **Deux transitions :** T13 (`draft` → `cancelled`) et T14
  (`pending` → `cancelled`), réservées au demandeur.
- **`cancelled` est un état distinct de `rejected`.** Un rejet est une décision
  de l'administration, une annulation un retrait du demandeur. Les confondre
  fausserait le journal et toute statistique de refus.
- **Ce n'est pas une suppression.** La demande reste en base avec sa trace :
  effacer, ce serait perdre le fait qu'une demande a existé, que c'est
  précisément ce qu'un audit anti-fraude doit pouvoir reconstituer.
- **La limite, et pourquoi :** l'annulation s'arrête à `pending`. Au-delà, un
  officier a pris le dossier et annuler jetterait son travail. Le demandeur
  passe par « Contact Officer ». Ce choix ne fait que restreindre ; l'élargir
  sera facile s'il le faut.
- **Frais déjà réglés :** on le **dit** au demandeur, on ne décide pas. La
  politique de remboursement est la question 4 d'`INTEGRATIONS.md` §5,
  toujours ouverte. Rembourser automatiquement serait inventer une règle ; se
  taire laisserait croire la somme perdue.
- **Deux défauts trouvés en l'implémentant :**
  1. Le **déclencheur PostgreSQL** listait les états terminaux en dur
     (`signed`, `rejected`). `cancelled` n'y figurait pas : une sortie
     d'annulation n'aurait été refusée que parce qu'elle manquait dans
     `allowed_transitions` — donc une ligne ajoutée par erreur dans cette table
     aurait suffi à faire repartir une demande annulée. La barrière doit tenir
     par elle-même.
  2. L'**écran de suivi** rangeait les statuts dans un tableau indexé par leur
     valeur. Ajouter un état l'a cassé — « Undefined array key » — sans qu'aucun
     outil ne prévienne. L'ordre vit désormais sur l'énumération, dans un
     `match` : un cas oublié y lève une erreur à la source, et un test parcourt
     tous les cas, donc couvrira aussi le prochain.
- **Ce que le test exhaustif de la machine à états a bien fait :** il a
  automatiquement énuméré les sept nouvelles paires interdites au départ de
  `cancelled`, sans que j'aie à les écrire. Un produit cartésien vaut mieux
  qu'une liste tenue à la main.

---

## D-045 — La biométrie est obligatoire, et la machine ne décide pas

- **Date :** 2026-09-10
- **Statut :** appliqué, **sur décision explicite** après signalement
- **Le contexte :** j'ai signalé que l'acteur *Facial Recognition AI* du
  diagramme était un traitement biométrique, et posé cinq questions
  d'encadrement. La réponse a été que la biométrie est obligatoire. Elle est
  donc construite.
- **Ce qui est fait :** à l'étape 3, un rapprochement automatique entre le
  selfie et la photographie de la pièce. **Obligatoire** — l'officier ne peut
  pas conclure sur l'étape tant qu'il n'a pas eu lieu.
- **Les quatre garde-fous, tenus par le code et par des tests :**
  1. **La machine rend un avis, pas une décision.** L'avis vit dans une table
     séparée ; l'étape porte la décision de l'officier, avec l'avis recopié
     pour qu'on sache s'il est passé outre. **Aucun seuil de rejet automatique
     n'existe nulle part.**
  2. **Une personne non reconnue n'est jamais bloquée.** `no_match`,
     `inconclusive` et `unavailable` sont des résultats enregistrés : la
     vérification est complète et l'officier peut accepter en motivant (D-031).
     Sans cette porte, un faux négatif serait une **exclusion administrative** —
     un acte d'état civil conditionne l'accès à presque tout.
  3. **Aucun gabarit biométrique n'est conservé** : l'issue et le score, rien
     d'autre. Un test refuse la présence des mots `embedding`, `template`,
     `descriptor`, `landmarks`, `encoding`, `image` et `base64`.
  4. **Le journal ne porte que l'issue** (garde-fou n°6).
- **Le demandeur est informé** avant de téléverser : que ses photographies
  seront comparées, que la comparaison ne décide pas, et qu'un échec ne vaut
  pas refus. Pas de case à cocher — un consentement qu'on ne peut pas refuser
  serait un faux consentement.
- **L'adaptateur réel lève une exception** plutôt que de simuler un « match ».
  Un avis fabriqué conduirait un officier à délivrer un acte en croyant qu'une
  machine a confirmé l'identité : c'est le mécanisme même d'une fraude réussie.
- **Ce que le code ne peut pas résoudre**, et qui bloque une mise en service
  réelle : fondement juridique, prestataire et taux d'erreur mesuré, voie de
  recours en cas de non-reconnaissance répétée, conservation des
  photographies, responsabilité en cas d'erreur. Voir `docs/BIOMETRIE.md` §4.

---

## D-046 — L'administrateur est conservé ; GDNS est la DGSN

- **Date :** 2026-09-10
- **Statut :** tranché par vous
- **L'administrateur reste.** Le diagramme n'en comportait pas et confiait les
  réglages au maire ; la séparation du §4.2 l'emporte. « Manage system
  settings » devient un cas de l'administrateur. Un maire qui créerait les
  comptes officiers **et** signerait les actes cumulerait les deux pouvoirs que
  la plateforme sépare.
- **« Generate Certificate » reste la préparation du dossier**, pas la
  production d'un document. L'acte naît de la signature du maire. Un document
  existant avant sa décision serait un acte en attente de tampon, et c'est
  précisément ce qui rend un raccourci possible.
- **GDNS = DGSN**, Délégation Générale à la Sûreté Nationale, qui délivre la
  carte nationale d'identité au Cameroun (vérifié en ligne : `dgsn.cm`). C'est
  l'acteur que `IdentityLookupProvider` modélisait déjà ; l'adaptateur réel
  s'appelle désormais `DgsnIdentityLookupProvider`.
- **Un piège écarté au passage :** la DGSN publie des frais pour la CNI. Ce
  n'est **pas** le tarif d'une réédition d'acte d'état civil, qui reste inconnu.
  Aucun montant n'est repris de l'un pour l'autre.

---

## D-047 — « Contact Officer » : un fil attaché au dossier, pas une messagerie

- **Date :** 2026-09-10
- **Statut :** appliqué
- **Décision :** le canal entre le demandeur et l'administration est un fil de
  messages **attaché à une demande**. On n'écrit pas à un agent, on écrit **au
  sujet d'un dossier**.
- **Pourquoi ce choix plutôt qu'une messagerie entre personnes :** n'importe
  quel officier du centre peut reprendre la conversation si celui qui suivait
  le dossier est absent — vérifié par un test. Une messagerie nominative aurait
  fait dépendre le service de la présence d'une personne.
- **L'administrateur en est exclu, et ce n'est pas un oubli.** Le §4.2 lui
  interdit le contenu des dossiers, et un échange sur un dossier **est** du
  contenu de dossier : lui ouvrir le fil rouvrirait par la fenêtre ce que la
  portée globale ferme. Refusé par la Policy **et** par une contrainte
  `CHECK` en base, qui rejette un message dont l'auteur est administrateur même
  en SQL direct.
- **Pas de pièces jointes.** Un canal de messages qui accepte des fichiers
  devient une seconde voie de dépôt de pièces d'identité, hors du contrôle de
  `IdentityDocumentStore`. Si le demandeur doit fournir une pièce, cela passe
  par le dossier.
- **Le journal dit qu'un message a été envoyé, jamais son contenu.** Un échange
  sur un dossier d'état civil contient des données personnelles (garde-fou
  n°6). Un test compare le texte du message au contenu de la ligne d'audit.
- **Ce que le test a révélé sur la portée globale :** le maire ne peut pas
  écrire sur une demande `pending`, non pas parce que la Policy le refuse, mais
  parce que sa portée globale l'empêche de la voir — la liaison de modèle échoue
  avant, et il obtient 404. La Policy reste écrite en termes de commune, comme
  seconde barrière si la portée changeait un jour.

---

## D-048 — Docusign nommé ne répond pas à la question A1

- **Date :** 2026-09-10
- **Statut :** appliqué, question A1 **toujours ouverte**
- **Le fait :** le diagramme v2 nomme **Docusign**. L'API eSignature REST
  existe et est documentée — enveloppes, documents, destinataires, suivi de
  statut (`developers.docusign.com`). L'adaptateur réel s'appelle désormais
  `DocusignSignatureProvider`.
- **Ce que cela règle :** on sait quelle interface implémenter. Ce n'est plus
  une hypothèse de contrat.
- **Ce que cela ne règle pas, et qu'il ne faut pas confondre :**
  1. **La valeur juridique.** Une signature techniquement valide n'est pas un
     acte qui fait foi. Question A1, toujours sans réponse. La mention « SANS
     VALEUR JURIDIQUE » reste (D-025).
  2. **La résidence des données.** Prestataire commercial étranger, données
     d'identité : décision explicite requise sur le lieu de traitement.
  3. **Le compte signataire.** La commune ou le maire nominativement ? Un
     changement de maire invalide-t-il les actes antérieurs ?
- **L'adaptateur lève une exception** nommant ces trois points. Un adaptateur
  qui rendrait un document « signé » sans les avoir tranchés serait le chemin
  par lequel un acte frauduleux sort du système.

---

## D-049 — Deux opérateurs, un agrégateur qui reste à nommer

- **Date :** 2026-09-10
- **Statut :** opérateurs **appliqués** ; agrégateur **ouvert**
- **Ce qui est fait :** « Pay Through Orange Money » et « Pay Through Mobile
  Money » du diagramme sont deux valeurs d'une énumération
  `App\Enums\PaymentOperator`. Le demandeur choisit à l'écran, le choix est
  conservé avec l'encaissement, il figure au reçu, et un test compte les
  règlements **par opérateur** — c'est la raison d'être de la colonne : sans
  elle, on ne saurait pas quel opérateur doit quelle somme.
- **Hypothèse signalée :** « Mobile Money » lu comme le service de MTN, par
  opposition à Orange Money. Un seul fichier à changer si c'est autre chose.
- **Ce qui n'est pas encodé :** les préfixes de numéro par opérateur. Les
  deviner serait inventer une règle métier ; un numéro mal classé ferait
  échouer un règlement sans explication. L'opérateur reconnaît ses numéros.
- **Refus silencieux évités :** un règlement sans opérateur est refusé, un
  opérateur inconnu aussi — par la validation **et** par une contrainte `CHECK`
  qui rejette une valeur hors liste même en SQL direct.
- **L'agrégateur, en revanche, n'est pas nommé.** La demande m'est parvenue
  sous le nom **« HR-SKILLS »**. Je ne l'ai identifié ni parmi les compétences
  disponibles, ni comme prestataire de paiement camerounais par une recherche
  en ligne. **Je n'ai donc rien écrit sous ce nom** : inventer un contrat
  d'API revient à écrire du code qui ne s'exécutera jamais, et à donner
  l'illusion que l'intégration avance.
- **Ce que coûtera la réponse :** une classe derrière `PaymentProvider` et une
  ligne de configuration. Le cycle de vie, l'idempotence, le reçu et le choix
  d'opérateur ne bougeront pas — c'est précisément ce que D-040 cherchait à
  garantir.

---

## D-050 — HR-Skills Pay branché, contre un serveur simulé

- **Date :** 2026-09-10
- **Statut :** implémenté ; **reprise contre le bac à sable à faire**
- **Comment le contrat a été obtenu :** le site est une application
  JavaScript ; `/docs`, `/api` et `/developers` rendent tous la même coquille
  de 491 octets. Le contrat a donc été relevé dans le **paquet JavaScript
  public** du prestataire, qui embarque sa propre documentation — exemples
  `curl`, Python, PHP et JavaScript compris. C'est leur documentation, pas une
  déduction.
- **Ce qui est implémenté :** jeton de transaction (45 min, mis en cache),
  encaissement mobile money avec la clé d'idempotence de PHOENIX reprise
  telle quelle, rapprochement par référence, et un rappel signé.
- **CE QUI N'A PAS ÉTÉ FAIT, et qu'il ne faut pas confondre :** aucun appel
  contre le service réel. Sans identifiants, l'adaptateur n'est vérifié que
  contre un serveur simulé. **Les tests prouvent que notre code suit la
  documentation, pas que le prestataire suit sa propre documentation.**
- **Cinq refus délibérés :**
  1. Un statut inconnu vaut « en attente », jamais « payé » : se tromper dans
     ce sens ferait délivrer un acte contre un encaissement inexistant.
  2. Un rappel n'est jamais cru sur parole. La signature prouve l'origine, pas
     la fraîcheur : on re-interroge le prestataire. Un rappel signé annonçant
     « payé » alors que l'API dit « en attente » ne paie rien — testé.
  3. Sans secret de rappel configuré, **tout** rappel est refusé.
  4. La réponse n'est pas recopiée en base : liste blanche de champs, pour
     qu'un numéro de téléphone rendu par le prestataire n'y entre pas.
  5. Le remboursement lève. Aucun n'est documenté, et un décaissement est une
     opération distincte : les confondre reviendrait à virer des fonds sans
     lien comptable avec l'encaissement d'origine.
- **Un défaut trouvé en écrivant les tests :** un rappel signé annonçant un
  état **antérieur** à celui déjà enregistré faisait remonter une
  `DomainException` et répondre **500** sur une route publique — donc réessayer
  le prestataire en boucle. Le rapprochement ne recule plus et ne casse plus :
  une transition non atteignable est journalisée et ignorée.
- **La seule route CSRF-exemptée du système**, et elle l'est explicitement :
  un serveur tiers ne peut pas porter de jeton. Elle est gardée par la
  signature HMAC de son corps brut, vérifiée avant toute lecture.
- **Deux questions nouvelles :** qui supporte les frais — la réponse porte un
  `net_amount` inférieur au montant payé, ce qui change le montant à afficher
  au citoyen ; et deux noms de domaine apparaissent dans leur documentation
  (`api.hrskills-pay.com` et `api.hrskillspay.com`), d'où une base en
  configuration.

---

## D-051 — Passage à MySQL : ce qui change, et ce qui aurait cassé en silence

- **Date :** 2026-09-11
- **Statut :** implémenté ; 485 tests au vert sur MariaDB 10.11 / MySQL 8.4
- **Origine :** demande explicite de votre part de faire passer la base en
  MySQL. Ce n'est pas un simple changement de pilote : quatre garanties de
  sécurité du système reposaient sur des mécanismes que MySQL n'a pas.

### Ce qui a été vérifié AVANT d'écrire du code

Trois propriétés, prouvées directement en SQL sur le serveur avant tout
portage, parce que s'en remettre à la documentation aurait été insuffisant :

1. les contraintes `CHECK` sont bien appliquées (elles étaient ignorées avant
   MariaDB 10.2 / MySQL 8.0.16) ;
2. un déclencheur `BEFORE UPDATE` peut lire une **autre** table et interrompre
   l'écriture par `SIGNAL SQLSTATE '45000'` ;
3. le journal d'audit ne peut être rendu inaltérable **que** par des droits
   accordés table par table.

### Les droits MySQL s'ADDITIONNENT — la différence la plus lourde

Sur PostgreSQL chaque objet porte ses droits : accorder largement puis
révoquer sur `audit_logs` fonctionnait. Sur MySQL, un
`GRANT ... ON phoenix.*` suivi d'un `REVOKE ... ON phoenix.audit_logs` **ne
révoque rien** — la révocation est acceptée, et le droit de base subsiste.

Conséquence : le journal d'audit redeviendrait modifiable dès que quelqu'un
accorderait un droit sur la base « pour dépanner », sans aucun message
d'erreur. Toute la logique de droits est donc centralisée dans
`ApplicationPrivileges`, table par table, et deux tests l'entourent : l'un
vérifie qu'aucune table n'a été oubliée, l'autre qu'aucun droit à l'échelle
de la base n'existe.

### Il n'y a pas d'`ALTER DEFAULT PRIVILEGES`

Une table créée par une migration ultérieure ne reçoit **aucun** droit. D'où
la migration de rafraîchissement et la commande `php artisan phoenix:droits`,
à relancer après toute migration créant une table — et le test qui échoue si
on l'oublie.

### `information_schema` est filtrée par les droits du lecteur

Deux contrôles se sont cassés **en silence**, ce qui est la pire forme :

- La page de santé annonçait le déclencheur de machine à états **ABSENT** sur
  une base parfaitement saine : le compte applicatif n'a pas le droit
  `TRIGGER`, donc il ne voit pas les déclencheurs. Lui accorder ce droit
  aurait réglé l'affichage **et** ouvert la faille : `TRIGGER` permet
  `DROP TRIGGER`, c'est-à-dire laisser l'application supprimer la seule
  barrière qui refuse une transition interdite. Une vue en
  `SQL SECURITY DEFINER` expose les noms sans accorder ce pouvoir (D-052).
- Le test « aucune table oubliée » ne pouvait **structurellement pas** échouer :
  un compte ne voit pas dans `information_schema.TABLES` une table sur
  laquelle il n'a aucun droit. Il listait donc toujours zéro oubli. La liste
  des tables est désormais lue sous le compte propriétaire.

### `SHOW GRANTS` couvre toutes les bases

Le lecteur de droits des tests confondait `phoenix` et `phoenix_test` : un
droit laxiste en développement aurait pu décider du résultat d'un test, et
l'ordre des lignes décidait lequel l'emportait. Le filtre sur la base
courante est maintenant explicite.

### `ilike` n'existe pas — la recherche était cassée

Trois écrans de recherche (journal d'audit, comptes, file d'attente) ont été
portés vers `like`. Sur MySQL, `like` est insensible à la casse **parce que**
les colonnes sont en `utf8mb4_unicode_ci` : la correction dépend désormais de
l'interclassement, qui est une propriété de schéma modifiable sans erreur.
Un test l'ancre, et il échoue bien si l'on passe une colonne en
interclassement binaire — vérifié.

### La sauvegarde ne contient pas les droits

MySQL range les droits dans la base système `mysql`, pas dans celle du
projet : `mysqldump` ne les emporte pas, là où `pg_dump` le faisait. Une base
restaurée a ses tables et ses déclencheurs, et aucun droit. La restauration
relance donc `phoenix:droits`. Cycle complet exécuté pour de bon :
inventaire identique, puis les garanties elles-mêmes revérifiées sur la base
restaurée — journal en ajout seul, référentiel en lecture seule, déclencheur
insupprimable, et `draft -> signed` refusé.

### Autres différences rencontrées

| Sujet | PostgreSQL | MySQL |
|---|---|---|
| Corps du déclencheur | fonction séparée, réutilisable | écrit dans le déclencheur ; pas de `CREATE OR REPLACE TRIGGER` |
| Erreur levée | `RAISE EXCEPTION` | `SIGNAL SQLSTATE '45000'` |
| Longueur d'identifiant | 63 caractères | **64** — un index généré en faisait 68, nommé explicitement |
| Erreur après un échec dans une transaction | transaction empoisonnée | l'instruction échoue, la transaction continue |
| `DROP CONSTRAINT IF EXISTS` | oui | non (MariaDB seulement) — passage par `information_schema` |

### Ce qui devient sans objet

**D-037 (RLS) ne peut pas être adopté tel quel : MySQL n'a pas de sécurité
au niveau des lignes.** Le prototype conservé dans `docs/prototypes/rls/`
reste valable pour PostgreSQL uniquement. La défense en profondeur qu'il
apportait doit être repensée — `docs/RLS.md` détaille les options et
aucune n'est équivalente.

---

## D-052 — Le compte applicatif ne doit pas voir les déclencheurs, mais la page de santé doit

- **Date :** 2026-09-11
- **Statut :** implémenté
- **Le problème :** la page de santé doit pouvoir affirmer que le déclencheur
  de machine à états est en place. Sur MySQL, `information_schema.TRIGGERS`
  est filtrée par les droits du lecteur : sans le droit `TRIGGER` sur la
  table, le compte applicatif ne voit rien. La page annonçait donc **ABSENT**
  sur une base saine — et un voyant rouge permanent est un voyant qu'on
  apprend à ignorer.
- **La solution écartée :** accorder `TRIGGER` au compte applicatif. Ce droit
  permet `DROP TRIGGER`. L'application pourrait supprimer la seule barrière
  qui refuse une transition interdite : le contraire exact de ce que le
  déclencheur protège.
- **La solution retenue :** une vue `phoenix_guards` en
  `SQL SECURITY DEFINER`, évaluée avec les droits du compte de migration, qui
  n'expose que le nom du déclencheur et sa table. Le compte applicatif y gagne
  une lecture et aucun pouvoir.
- **Vérifié, pas supposé :** le compte applicatif lit bien la vue et se voit
  toujours refuser `DROP TRIGGER` avec l'erreur 1142 — dans la base de
  développement comme dans une base restaurée depuis une sauvegarde. Un test
  le tient.
- **Effet de bord utile :** la commande de vérification de restauration passe
  par la même vue, et cesse donc de crier au loup.

---

## D-053 — Trois compensations pour la fuite en lecture que MySQL ne ferme plus

- **Date :** 2026-09-11
- **Statut :** implémenté ; 493 tests au vert. **Un arbitrage reste ouvert
  (§7.4 de `docs/RLS.md`).**
- **Origine :** D-051 a rendu D-037 inapplicable — MySQL n'a pas de sécurité au
  niveau des lignes. La fraude **par écriture** reste couverte en base
  (déclencheurs, contraintes, journal en ajout seul) ; la fuite **par lecture**
  entre centres et communes est redevenue tenue par la seule portée globale
  Eloquent, c'est-à-dire précisément celle qui avait disparu sans bruit au
  jalon 2 (D-013).

### Ce qui a été fait

1. **La portée est testée pour les quatre rôles, sans `where` explicite.** Le
   maire manquait — le rôle dont la clause est la plus fine (commune **et**
   deux états seulement). R6 et R7 le couvraient par la Policy, ce qui n'est
   pas la même barrière.
2. **L'application refuse de démarrer si la portée n'est pas enregistrée.**
   Un test protège là où des tests tournent ; en production il n'en tourne
   aucun.
3. **Un seul point de contournement dans `app/`, et il est audité.** Les deux
   contrôleurs qui doivent charger une demande hors portée pour pouvoir
   l'autoriser passent par `ReissuanceRequest::loadForAuthorization()`, et un
   test structurel interdit l'idiome brut ailleurs.

### Une chose que j'ai affirmée puis vérifiée fausse

En écrivant le garde-fou n°2, j'ai d'abord repris tel quel le constat de
D-013 : « une classe inexistante dans `#[ScopedBy(...)]` ne lève aucune erreur
PHP ». **C'est faux sur Laravel 13.30**, qui lève désormais une
`InvalidArgumentException` — vérifié en reproduisant le défaut. Le garde-fou ne
couvre donc pas la forme exacte de D-013, mais les deux formes restées
silencieuses : l'attribut **supprimé**, et l'attribut remplacé par une autre
portée valide. Le commentaire et le message d'erreur ont été corrigés en
conséquence.

### Une chose que j'ai crue nécessaire et qui ne l'est pas

Le `tearDown` de `VisibilityScopeGuardTest` remet la portée en place. Je
pensais que son absence contaminerait toute la suite. **Non :**
`DatabaseServiceProvider::boot()` appelle `Model::clearBootedModels()` à chaque
démarrage d'application, donc à chaque test. Vérifié en le retirant : la suite
reste verte. Il est conservé par précaution, et le commentaire dit maintenant
qu'il est une précaution et non une nécessité.

### Ce que cela ne fait pas

Ces trois points protègent **la barrière** ; ils ne la remplacent pas. La
portée reste le point unique de défaillance en lecture : sa disparition devient
bruyante et son contournement visible, ce qui est très différent d'impossible.
Et quiconque dispose des identifiants du compte applicatif peut lire toutes les
demandes en SQL direct — c'était déjà vrai sans RLS, RLS l'aurait fermé.

Le tableau comparatif et les deux voies possibles sont au §7.4 de
`docs/RLS.md`. **La décision vous revient.**

---

## D-054 — MySQL est le seul moteur, et l'application le fait respecter

- **Date :** 2026-09-11
- **Statut :** implémenté ; 500 tests au vert
- **Origine :** votre arbitrage — « maintiens uniquement MySQL pour la base de
  données ». C'est la seconde voie du §7.4 de `docs/RLS.md` : accepter le
  risque résiduel en lecture et le consigner, plutôt que revenir à PostgreSQL
  pour la seule raison de RLS.

### Pourquoi un moteur unique n'est pas qu'une question de préférence

Toutes les barrières anti-fraude de PHOENIX sont posées **dans la base** :
déclencheurs de machine à états, contraintes CHECK, exigence d'une ligne
d'audit dans la même transaction, droits accordés table par table qui rendent
le journal d'audit inaltérable. **Aucune ne survit à un changement de moteur.**

Sur SQLite, l'application démarrerait, les écrans fonctionneraient, et une
transition interdite serait acceptée sans que rien ne le signale. C'est la
pire des pannes : silencieuse, et du côté de la permissivité. Un
`DB_CONNECTION=sqlite` posé par erreur — ou hérité d'un `.env` d'exemple —
suffisait.

### Ce que j'ai cru faire, et ce qu'il fallait faire

J'ai d'abord retiré `sqlite`, `mariadb`, `pgsql`, `pgsql_owner` et `sqlsrv` de
`config/database.php`, en pensant que cela les supprimait. **Vérifié : non.**
Laravel fusionne sa configuration de base avec celle de l'application, et
`connections` figure dans ses options fusionnables
(`LoadConfiguration::mergeableOptions`). Les cinq connexions revenaient
intégralement, le fichier du projet vide ou non — `config("database.connections")`
les listait encore toutes.

Un fichier trimé donnait donc une **fausse impression de fermeture**. C'est
exactement la classe de défaut que ce projet passe son temps à traquer.

### Ce qui est en place

`App\Support\Security\DatabaseEngineGuard`, appelé depuis
`AppServiceProvider::register()` — dans `register()` et non `boot()`, pour
qu'une connexion étrangère disparaisse avant que quoi que ce soit puisse
l'utiliser. Trois actions :

1. **élaguer** les connexions que le cadre réinjecte ;
2. **refuser** une connexion par défaut hors du projet ;
3. **refuser** toute connexion dont le pilote n'est pas `mysql`.

Éprouvé pour de bon : `DB_CONNECTION=sqlite` et `DB_CONNECTION=pgsql` font
refuser le démarrage avec un message qui nomme la variable à corriger ; changer
le pilote d'une connexion du projet le fait refuser aussi. Et les deux parties
sont porteuses : sans l'élagage, la troisième vérification attrape la connexion
réinjectée et refuse tout — moins commode, toujours sûr.

`config/queue.php` repliait sur `sqlite` pour les lots et les tâches échouées ;
corrigé.

### Ce qui est accepté, en toutes lettres

1. La portée globale Eloquent reste le **point unique de défaillance en
   lecture**. Sa disparition est bruyante et son contournement visible
   (D-053), pas impossible.
2. **Quiconque dispose des identifiants du compte applicatif peut lire toutes
   les demandes en SQL direct.** RLS l'aurait fermé. Leur protection est la
   seule chose qui tienne sur ce point.

La fraude **par écriture** reste, elle, refusée par la base elle-même.

**Ce qui n'est pas technique et reste à faire :** ces deux points ont leur
place dans l'analyse de risque remise au maître d'ouvrage. Je ne peux pas
l'écrire — elle engage une responsabilité, pas un choix d'implémentation.

---

## D-055 — Un bandeau qui affirmait une chose fausse, et le dossier bloqué qu'il cachait

- **Date :** 2026-09-11
- **Statut :** bandeau corrigé et testé ; **le blocage sous-jacent reste à
  trancher (voir plus bas)**
- **Comment il a été trouvé :** en regardant une capture d'écran. Pas un test.

### Ce que l'écran disait

Sur l'écran de vérification, dès que l'officier ne pouvait pas décider, le
bandeau affichait :

> Lecture seule — Ce dossier est pris en charge par **un autre agent**.

Le repli `{{ $demande->assignedOfficer?->name ?? 'un autre agent' }}` traitait
« personne n'a pris ce dossier » comme « quelqu'un d'autre l'a pris ». Pour un
agent devant un dossier libre, l'effet concret est un cul-de-sac : il croit que
le dossier ne lui revient pas et passe au suivant, alors qu'il lui suffisait de
le prendre en charge — action que le bandeau ne proposait pas.

**Pourquoi les tests ne l'ont pas vu :** ils vérifiaient que l'état lecture
seule *bloque la décision*, ce qui était vrai et reste vrai. Aucun ne
vérifiait que le message *dise la vérité*.

### Ce qui est corrigé

Trois cas, trois messages, et le test qui va avec
(`VerificationBannerTest`, éprouvé en rétablissant l'ancien bandeau) :

| Situation | Message |
|---|---|
| Dossier libre et prenable | « Dossier à prendre en charge » + le bouton |
| Dossier tenu par un collègue | « Lecture seule — pris en charge par *[nom]* » |
| En examen, sans agent affecté | « Dossier sans agent affecté » + renvoi à l'administrateur |

Au passage, la vue pointait vers `officer.claim`, une route qui n'existe pas —
le nom réel est `officer.verification.claim`. Elle aurait levé dès qu'on aurait
atteint la branche. Ce sont les nouveaux tests qui l'ont attrapé.

### Le vrai problème, qui n'est pas le message — **arbitrage attendu**

En cherchant la cause, j'ai vérifié le cycle complet des affectations :

- `claim` exige l'état **en attente** ET aucun agent affecté ;
- `decide` exige l'état **en cours d'examen** ET l'affectation à soi-même ;
- `assigned_officer_id` n'est **jamais remis à zéro**.

Conséquence, reproduite : **un dossier en cours d'examen dont l'agent affecté
ne peut plus agir n'est repris par personne.** Aucun collègue du même centre ne
peut ni le décider ni le prendre en charge. Il n'existe aucun chemin de reprise.

Cet état est atteignable par une action ordinaire de l'administrateur —
suspendre un agent, ou le rattacher à un autre centre. La demande d'un citoyen
devient alors définitivement intraitable, sans qu'aucune alerte ne le signale.

J'ai aussi relevé que la Policy `decide` répond **oui** pour un agent suspendu :
c'est le middleware `EnsureAccountIsActive` qui le bloque à la porte HTTP, pas
la Policy. Cela fonctionne, mais la Policy n'est pas la barrière qu'on croit
lire.

**Je n'ai pas construit de mécanisme de reprise, et c'est délibéré.** « Qui peut
reprendre un dossier tenu par un autre agent » est une question anti-fraude, pas
une question d'ergonomie : un chemin de reprise mal posé permet à un agent de
récupérer les dossiers d'un collègue. Trois options, à trancher :

1. **L'administrateur libère** le dossier depuis la gestion des comptes —
   tracé, réversible, et cohérent avec son rôle actuel.
2. **Libération automatique** quand l'agent affecté n'est plus actif ou n'est
   plus rattaché au centre.
3. **Reprise entre pairs du même centre**, avec journalisation obligatoire —
   le plus souple, et le plus exposé.

Mon avis : l'option 1, complétée par une alerte listant les dossiers orphelins.
Mais c'est une règle de gestion, pas un choix technique.

---

## D-056 — Les mesures de performance reprises sur MySQL

- **Date :** 2026-09-11
- **Statut :** fait ; `docs/PERFORMANCE.md` à jour
- **Pourquoi :** D-051 avait laissé publiées des durées relevées sur
  PostgreSQL. Des chiffres qui ne décrivent plus le système sont pires que pas
  de chiffres.
- **Conditions reproduites à l'identique** — 515 demandes, 513 comptes — pour
  que la comparaison veuille dire quelque chose. Trois chargements par écran,
  médiane retenue.
- **Résultat : le changement de moteur ne se voit pas.** Temps serveur 25 à
  41 ms contre 27 à 38 ms sur PostgreSQL ; l'écart le plus large est de 9 ms,
  du même ordre que la dispersion entre deux passages sur le même moteur. Il
  serait malhonnête d'en conclure qu'un moteur est plus rapide que l'autre.
- **Les compteurs SQL n'ont pas bougé** (5, 5, 4, 5, 4), ce qui était attendu :
  c'est une propriété du code, pas du moteur.

### Deux chiffres que j'ai failli publier faux

1. **Le chargement complet.** Mon premier harnais attendait `networkidle`, qui
   attend 500 ms de silence réseau **par construction**. Toutes les pages
   sortaient à ~550 ms — un plateau suspect qui n'était que ce délai. Remplacé
   par la lecture de `loadEventEnd - startTime` dans la page.
2. **Le coût SQL.** Un relevé bricolé hors du harnais de test donnait 3
   requêtes au lieu de 5 pour deux écrans. La différence venait de mon jeu
   d'essai — un administrateur fraîchement créé, sans les lignes de session
   d'une vraie visite. Les chiffres publiés sont ceux du harnais, qui passe par
   la pile HTTP complète.

---

## D-057 — Libérer une affectation bloquée, sans donner les dossiers à l'administrateur

- **Date :** 2026-09-11
- **Statut :** implémenté ; 515 tests au vert
- **Origine :** votre arbitrage sur D-055 — **option 1**, l'administrateur
  libère le dossier depuis la gestion des comptes.

### Le défaut corrigé

`claim` exigeait l'état **en attente** et aucun agent affecté ; `decide` exige
l'état **en cours d'examen** et l'affectation à soi-même ; et
`assigned_officer_id` n'était jamais remis à zéro. Un dossier dont l'agent
affecté ne pouvait plus agir — suspendu, désactivé, ou rattaché à un autre
centre — n'était donc repris par personne. **La demande d'un citoyen devenait
définitivement intraitable, sans aucune alerte**, à la suite d'une action
ordinaire de l'administrateur.

### Libérer ne suffisait pas

Effacer l'affectation seule aurait laissé le dossier dans l'état « en cours
d'examen », que `claim` refusait : on aurait remplacé une impasse par une
autre. `claim` accepte désormais ce second état, à condition qu'aucun agent ne
soit affecté.

**Aucune transition d'état n'a été ajoutée pour autant.** Un dossier déjà en
cours d'examen le reste, et `under_review → under_review` n'existe pas dans la
machine à états — l'y ajouter pour la commodité d'un contrôleur aurait affaibli
la seule barrière qui refuse une transition interdite. Le contrôleur ne
transitionne que depuis « en attente », et trace la reprise par une ligne
d'audit dédiée (`request.assignment_resumed`).

### La tension à résoudre : l'administrateur ne voit aucun dossier

C'est la règle la plus contre-intuitive de la matrice, et la plus importante :
`RequestVisibilityScope` renvoie `whereRaw('1 = 0')` pour l'administrateur. Il
gouverne les comptes, pas les dossiers d'identité (§4.2 du brief).

Or libérer une affectation suppose de savoir **qui tient quel dossier**.

**La distinction retenue :** l'écran ne montre pas des demandes mais des
**affectations**. Une référence, un centre, un agent, une date. La sélection
est verrouillée sur `ReissuanceRequest::ADMINISTRATION_COLUMNS`, qui ne porte
ni nom de naissance, ni date de naissance, ni filiation, ni pièce jointe. Deux
tests le vérifient : l'un colonne par colonne sur la liste blanche, l'autre en
cherchant des données d'identité dans le HTML rendu.

C'est un second contournement de portée dans `app/`, et il est audité comme le
premier : `ScopeBypassTest` liste désormais les **deux** méthodes autorisées et
échoue si une troisième apparaît.

### Un 404 qui disait la vérité

La liaison automatique de modèle résolvait `{reissuanceRequest}` **à travers la
portée globale** — donc introuvable pour l'administrateur, donc 404 sur une
action qui lui est pourtant réservée. Ce n'était pas un bogue de la portée mais
sa cohérence. L'identifiant est passé en clair et résolu dans le contrôleur par
la méthode auditée.

### Ce que libérer fait, et ne fait pas

Efface l'affectation, rien d'autre : ni l'état de la demande, ni les étapes de
vérification déjà franchies. Le dossier redevient prenable par un agent du
centre, qui reprend où le précédent s'est arrêté. L'opération est tracée
(`request.assignment_released`), avec le nom de l'agent dessaisi.

**Le rôle est le seul critère d'autorisation, délibérément.** Un officier ne
peut ni se défaire d'un dossier ni reprendre celui d'un collègue sans passer
par l'administration : rendre le choix de l'agent négociable entre pairs est
exactement le levier d'une fraude.

### Vérifié pour de bon

La boucle complète a été exécutée au navigateur : dossier pris en charge, agent
suspendu, alerte affichée, libération par l'administrateur, reprise par un
collègue du centre, et l'alerte disparaît. L'état du dossier n'a pas bougé, et
les deux lignes d'audit sont en base.

### Ce qui reste ouvert

La Policy `decide` répond **oui** pour un agent suspendu : c'est le middleware
`EnsureAccountIsActive` qui le bloque à la porte HTTP, pas la Policy. Cela
fonctionne, mais la Policy n'est pas la barrière qu'on croit lire en la
relisant. Je ne l'ai pas modifiée dans ce jalon : y ajouter un contrôle de
statut de compte touche **toutes** les Policies, et mérite d'être fait d'un
seul tenant plutôt qu'au coup par coup.

---

## D-058 — Six défauts trouvés par une relecture, et ce qu'ils ont en commun

- **Date :** 2026-09-11
- **Statut :** les six corrigés ; 542 tests au vert
- **Origine :** une relecture systématique du diff complet de la branche. Les
  six ont été **reproduits** avant toute correction — un rapport n'est pas une
  preuve.

### Ce qu'ils ont en commun

Aucun n'était visible depuis la suite de tests, et pour trois raisons
différentes qui valent d'être nommées :

1. **Un test qui simule ne rend rien.** Tous les tests de notification
   utilisent `Notification::fake()`, qui intercepte l'envoi **avant** le
   rendu. Ils prouvaient qu'une notification part, jamais qu'elle se fabrique.
2. **Un chemin d'échec n'est pas un chemin testé.** Le paiement était éprouvé
   sur le succès et sur le refus de l'opérateur, jamais sur l'échec de la
   *prise de contact*.
3. **Un affichage faux n'échoue pas.** La frise rendait « terminé » des étapes
   qui n'avaient pas eu lieu : rien ne casse, et personne ne le voit.

### 1. Toute annulation tuait sa notification *(grave)*

`RequestStatusChanged` construit titre et corps par un `match` sur l'état
d'arrivée. `cancelled`, ajouté en D-044, n'y avait pas d'entrée : chaque
annulation levait une `UnhandledMatchError` **dans le worker**. La transition
passait, la demande était bien annulée, et le citoyen n'était **jamais**
prévenu — la tâche échouait hors de la requête HTTP, en silence.

`NotificationRenderingTest` parcourt désormais l'énumération entière et **rend**
chaque notification. Un état ajouté demain sans entrée échouera ici.

### 2. Un paiement pouvait devenir définitivement impayable *(grave)*

La ligne de paiement est validée en base **avant** l'appel à l'opérateur — il
le faut, la clé d'idempotence doit exister avant d'être envoyée. Si l'appel
échouait ensuite (numéro invalide, clés absentes, jeton refusé), la ligne
restait « en attente » sans `provider_reference` ; `initiate()` la rendait
telle quelle **sans jamais rappeler l'opérateur**, et `reconcile()` s'arrêtait
faute de référence. **Le citoyen ne pouvait plus jamais payer sa demande.**

La reprise se fait avec **la même clé d'idempotence** : en générer une nouvelle
ouvrirait un second ordre chez l'opérateur pour un seul acte — c'est-à-dire un
risque de double prélèvement. Le montant repris est celui de la ligne, pas
celui de la configuration : un changement de tarif entre deux tentatives ne
doit pas suivre le demandeur en cours de route.

### 3. La frise annonçait des étapes qui n'avaient pas eu lieu

`signed`, `rejected` et `cancelled` partagent le rang 4 — le parcours s'arrête
là, qu'il aboutisse ou non. Ce rang servait à décider quels jalons étaient
« terminés ». Un **brouillon annulé** affichait donc « Demande envoyée ✓ »,
« Vérification par l'officier ✓ » et « Décision du maire ✓ » : trois étapes
qui n'ont jamais eu lieu. Un refus de l'officier affichait la décision du maire
comme rendue.

Annoncer à un demandeur que son dossier a été instruit alors qu'il ne l'a pas
été n'est pas un défaut d'affichage. Le point d'arrêt se lit désormais dans le
**journal d'audit**, seule trace de ce qui s'est réellement passé.

### 4. Le rattachement pouvait rendre 500

`UserPolicy::reassign` admettait l'**administrateur** — `isOfficial()` n'exclut
que le citoyen. Or `users_role_scope_check` exige qu'un administrateur n'ait ni
centre ni commune. Et `commune_id` était `nullable` : un formulaire soumis sans
commune pour un maire partait à NULL. Dans les deux cas, `QueryException` non
rattrapée, donc **500**.

La contrainte faisait son travail ; c'est la validation qui manquait. La Policy
nomme maintenant les deux rôles qui ont un rattachement, plutôt que de s'en
remettre à une négation.

### 5. La page de santé dessinait la carte du système

`/sante` est publique — une sonde de supervision n'a pas de session. Mais elle
rendait à un visiteur anonyme la version du serveur de base, le nom du compte
applicatif, l'hôte (le message brut d'une exception PDO y passait tel quel) et
**l'état des droits du journal d'audit**.

Deux publics, deux réponses : l'anonyme reçoit le verdict, l'administrateur
authentifié reçoit le détail. Le code HTTP ne change pas — 200 ou 503, ce dont
une sonde a besoin.

### 6. Le script de restauration recommandait une option inexistante

Il imprimait `--database=`, la commande déclare `--db=`. L'étape intitulée
« Vérification obligatoire après restauration » s'arrêtait sur une erreur
d'option inconnue. Le cycle complet a été réexécuté, et la commande
recommandée fonctionne maintenant telle quelle.

---

## D-059 — Le statut du compte devient une barrière d'autorisation

- **Date :** 2026-09-11
- **Statut :** implémenté ; 569 tests au vert
- **Origine :** jalon A. Deux défauts relevés séparément — `decide()` répondait
  **oui** pour un officier suspendu, `reassign()` admettait l'administrateur —
  n'étaient pas deux bogues distincts mais **deux symptômes du même trou** :
  aucune des vingt-trois capacités des Policies ne regardait le statut du
  compte.

### Ce qui tenait la ligne jusqu'ici, et pourquoi ça ne suffisait pas

En pratique rien ne passait : `EnsureAccountIsActive` déconnecte un compte
suspendu à la porte HTTP. S'en remettre à lui a deux défauts, et le second est
le plus sérieux :

1. **Les Policies sont aussi consultées hors requête HTTP** — file d'attente,
   commandes Artisan, semences — où aucun middleware ne tourne.
2. **Une Policy qu'on relit ne disait pas ce qu'elle applique.** Un développeur
   qui lit `decide()` y voit trois conditions et en conclut, raisonnablement,
   qu'un agent suspendu est refusé par la Policy. Il ne l'était pas. **Croire
   lire une barrière là où elle n'est pas est exactement ce qui produit la
   faille suivante** — et c'est comme cela que `reassign()` est passé.

### La règle, posée une fois

`AccountStatusGate`, un `Gate::before` : **un compte qui n'est pas actif n'est
autorisé à rien.** Rien d'autre ne change ; les Policies gardent leur logique
métier.

**Pourquoi pas dans chaque capacité :** répéter `$user->isActive()` vingt-trois
fois, c'est vingt-trois occasions de l'oublier — et la vingt-quatrième
capacité, écrite dans six mois, ne l'aura pas.

**Pourquoi les Policies le disent quand même :** leur en-tête indique désormais
que le contrôle est ailleurs, et où. Une barrière invisible là où on la cherche
est le problème qu'on vient de corriger ; il ne s'agissait pas de le déplacer.

### Ce que cela n'empêche pas

Un compte en attente de configuration atteint toujours son écran de double
authentification — vérifié : il n'est gardé par aucune Policy. C'est la seule
chose qu'il doit pouvoir faire. Et le statut de la **cible** reste traité dans
`UserPolicy` : réactiver un compte suspendu est précisément le travail de
l'administrateur.

### Vérifié

`AccountStatusAuthorizationTest` parcourt la matrice entière — 4 rôles × 3
statuts inactifs × toutes les capacités, **lues par réflexion sur les Policies
elles-mêmes**, si bien qu'une capacité ajoutée demain est couverte sans qu'on
pense à ce fichier. 27 tests, 286 assertions.

Éprouvé en retirant la règle : **14 tests sur 27 échouent**, sur les quatre
rôles. Un test de contrepartie vérifie qu'un compte actif conserve toutes ses
capacités — sans lui, la règle serait satisfaite en refusant tout le monde.

Vérifié aussi au navigateur, dans les deux contextes : un officier suspendu
pendant sa session est redirigé vers la connexion, et `can('decide')` répond
**non** hors requête HTTP, là où aucun middleware ne tourne.

---

## D-060 — « Manage system settings » : consultable, non modifiable, incapable de fuir un secret

- **Date :** 2026-09-11
- **Statut :** implémenté ; 581 tests au vert. **Dernier cas d'utilisation du
  diagramme — la couverture est complète.**
- **Arbitrage appliqué :** lecture pour tous les réglages, écriture pour aucun.

### Pourquoi la lecture seule n'est pas de la timidité

Rendre ces réglages modifiables depuis un navigateur ouvrirait un vecteur de
fraude direct : un administrateur pourrait **basculer un prestataire sur
l'adaptateur factice** et faire délivrer des actes sans vérification réelle, ou
changer le tarif d'un service public depuis une page web. Ils restent donc des
variables d'environnement, sous le contrôle de qui déploie.

Un test structurel le tient : `admin.settings.*` ne doit comporter **aucune
route autre que GET**. Ce n'est pas une convention qu'on peut oublier.

### Ce que l'écran apporte malgré tout, et ce n'est pas rien

Avant lui, **rien dans l'application ne permettait de voir qu'elle ne vérifie
rien de réel.** Quatre adaptateurs sur cinq sont des squelettes ; une
démonstration prise pour un service réel est le risque d'exploitation le plus
concret de ce projet à ce stade. L'écran l'annonce en haut, en rouge, et nomme
les intégrations concernées.

Il signale aussi deux incohérences d'exploitation qui seraient sinon
silencieuses : un encaissement activé sans tarif (la plateforme refusera de
servir), et un tarif affiché sans base légale citable.

### Le risque propre à cet écran, et comment il est fermé

Un écran de réglages affiche la configuration, et cette configuration contient
des **secrets** : clé d'index aveugle, clés de l'agrégateur de paiement, secret
de signature des rappels. C'est typiquement l'endroit où un secret finit par
s'afficher « pour déboguer », puis y reste.

**La protection ne tient pas à un masquage à l'affichage.**
`SystemSetting::secret()` consomme la valeur et ne retient que « configuré » ou
« non configuré » : **la valeur n'entre jamais dans l'objet**. Il n'y a donc
rien à masquer dans le gabarit, parce qu'il n'y a rien à fuir.

Vérifié avec des valeurs reconnaissables (`SECRET-…-NE-DOIT-PAS-FUIR`) : aucune
n'apparaît dans le HTML, ni même dans les objets sérialisés que la vue reçoit.
Éprouvé en affichant délibérément un secret « pour déboguer » : deux tests
tombent.

### Deux erreurs dans mes propres tests, corrigées

1. Une assertion cherchait `n'ont` dans du texte non échappé du gabarit — elle
   ne mesurait rien d'utile, et a été remplacée.
2. Le refus pour un visiteur anonyme était vérifié **après** trois `actingAs()`
   dans la même méthode. Or `actingAs()` persiste jusqu'à la fin du test : je ne
   mesurais que le dernier rôle connecté, et j'obtenais 403 là où un invité
   reçoit une redirection. L'invité a désormais son propre test — confirmé au
   navigateur : 302 vers la connexion, comme les autres routes d'administration.

### Ce qui reste à trancher, et qui n'est pas technique

Le tarif et sa base légale restent vides : questions 1 et 6 du §7 de
`docs/INTEGRATIONS.md`. L'écran les affiche comme « non défini », ce qui est
l'état réel.

---

## D-061 — Le diagramme cesse d'être un document et devient une contrainte

- **Date :** 2026-09-11
- **Statut :** implémenté ; 649 tests, 0 échec
- **Origine :** votre consigne — « va toujours sur la base du diagramme
  fourni ».

### Ce qui n'allait pas dans ma façon de suivre le diagramme

Je venais d'annoncer « tous les cas d'utilisation sont couverts » **sur la foi
de `docs/CAS_USAGE.md`** — un tableau que j'entretiens moi-même. Ce n'est pas
une preuve, et ce document avait déjà divergé une fois : son §3 listait comme
« à construire » trois cas que son propre §2 donnait pour faits.

Une note de synthèse dérive. Un test, non.

### Ce qui est en place

`tests/Feature/UseCaseCoverageTest.php` encode le diagramme : chaque cas, son
acteur, ses routes nommées. Trois vérifications :

1. **Chaque cas a ses routes** — une route supprimée ou renommée fait échouer
   le cas correspondant, nommément.
2. **Chaque écran s'ouvre pour l'acteur du diagramme** — un écran fermé au
   mauvais rôle fait échouer le cas.
3. **Chaque route citée est réellement exercée par un test** — sans quoi le
   saut des cas « en écriture » serait une façon élégante de ne rien tester.

Éprouvé dans les deux sens : en supprimant la route des réglages, et en
fermant un écran à son propre acteur. Les deux font tomber la suite.

### Ce que ce test a immédiatement trouvé

**`admin.users.reassign` n'était exercée par aucun test** — alors que je venais
d'en corriger la validation et la Policy en D-058. J'avais vérifié mon
affirmation par un `grep`, qui comptait… ce fichier de traçabilité lui-même.
Corriger sans test, c'est corriger jusqu'à la prochaine fois. Sept tests
couvrent désormais le rattachement, dont les deux cas qui rendaient 500.

### Ce qui existe hors diagramme, et qui est maintenant déclaré

Le diagramme est la référence : ce qui n'y figure pas doit être **déclaré**,
pas glissé en douce. Six routes sont dans ce cas, et le test tombe si une
septième apparaît sans être documentée :

| Hors diagramme | Pourquoi |
|---|---|
| `admin.assignments.*` | Ne vient pas d'un besoin exprimé mais d'une impasse constatée (D-057) |
| `admin.audit.index` | Sert le §4.4 du brief, pas un cas du diagramme |
| `health` | Exploitation |
| `dev.ui` | Galerie de composants, hors production |
| `webhooks.hrskills` | Rappel signé de l'agrégateur |

### Ce que ce test ne prouve pas

Qu'un cas soit atteignable ne dit rien de sa **justesse**. Le comportement de
chaque cas reste vérifié par son propre fichier. Celui-ci ne couvre que la
traçabilité du diagramme au code — et c'est déjà ce qui manquait.

---

## D-062 — Le diagramme v2 relu à la source : trois écarts, dont un de ma main

- **Date :** 2026-09-11
- **Statut :** écarts corrigés ou déclarés ; 656 tests, 0 échec
- **Origine :** vous avez repartagé le diagramme. Jusque-là, `UseCaseCoverageTest`
  encodait ma **transcription** du diagramme, pas le diagramme. Confronté à la
  source, il manquait trois choses.

### 1. « Manage system settings » n'existe pas au diagramme v2 — et je l'ai construit

Le Super Admin n'y porte **qu'un seul cas : « Manage Accounts »**. Il n'y a pas
de cas « Manage system settings », ni chez lui ni chez le maire.

J'ai pourtant construit cet écran (D-060) en annonçant qu'il achevait la
couverture du diagramme. **Cette croyance venait de ma propre analyse de la
version 1** — où le cas existait, confié au maire — et non de la version 2, qui
est la référence. C'est exactement la dérive contre laquelle `UseCaseCoverageTest`
a été écrit, et elle est passée par moi, le jour même.

L'écran est **conservé** : consultation seule, aucune route d'écriture, aucun
secret affiché, et il signale que la plateforme tourne sur des adaptateurs
factices — ce que rien d'autre ne fait. Mais il est désormais **déclaré hors
diagramme**, et son maintien vous revient.

### 2. Deux cas du diagramme n'étaient pas tracés

**« Pay Through Orange Money »** et **« Pay Through Mobile Money »** sont deux
spécialisations de « Make Payment » au diagramme. Mon test ne traçait que le cas
parent. Les deux sont implémentés et couverts — vérifié, `PaymentOperatorTest`
les exerce l'un et l'autre — mais ils ne figuraient pas dans la traçabilité.

### 3. « Generate Certificate » : j'avais tranché ce qui ne m'appartenait pas

Le diagramme place ce cas chez l'**officier**. Le code n'a aucune route
correspondante : l'acte n'est fabriqué que par `ActIssuanceService`, appelé
depuis le contrôleur du **maire**.

`docs/CAS_USAGE.md` §4.2 portait « **TRANCHÉ** : lecture 1 », de ma main. Ma
lecture reste défendable — un document existant avant la décision du maire
serait un acte en attente de tampon — mais **qui rédige l'acte est une question
de responsabilité du contenu, pas d'implémentation.** Elle est rouverte, et
désormais déclarée dans `UseCaseCoverageTest::CAS_SANS_ROUTE` avec sa
justification, de sorte qu'elle ne puisse plus se perdre dans un document.

### Ce qui change dans le test

- Les cas portent les **noms exacts du diagramme** : « Escalate Request » et
  non « Escalate Case », « Make Reissuance Request » et non « Make Request ».
  Une traduction approximative fait perdre la trace.
- « Accept Request » et « Reject Request » sont deux cas distincts, comme au
  diagramme, et non un « Accept / Reject » de ma composition.
- Une liste **`CAS_SANS_ROUTE`** rend visibles les cas du diagramme sans
  implémentation. Un test échoue si elle change sans qu'on l'ait voulu : un cas
  non construit ne doit pas pouvoir sortir de la discussion.

### La leçon, et elle est pour moi

Un test qui encode une transcription ne vaut pas mieux que la transcription.
`UseCaseCoverageTest` prouvait la cohérence entre mon souvenir du diagramme et
le code — pas entre le diagramme et le code. **La source doit être relue, pas
mémorisée.**

---

## D-063 — Le diagramme ne dit pas que qui peut faire quoi : il dit aussi qui ne le peut pas

- **Date :** 2026-09-11
- **Statut :** implémenté ; 686 tests, 0 échec
- **Origine :** poursuite de D-062. Je n'avais confronté au diagramme que les
  **cas**. Il porte deux autres informations, et aucune n'était vérifiée.

### 1. « Include Authenticate » sur tous les cas

Toutes les ellipses du diagramme portent une flèche `«Include»` vers
`Authenticate`, sauf une : **« Create Citizen Account »**, qui part du Visitor —
on ne peut pas exiger d'être connecté pour créer son compte.

La propriété tenait, mais au fait que personne ne s'était trompé. Elle est
désormais applicable : une route de cas déclarée hors du groupe authentifié
fait échouer la suite. Éprouvé en sortant le centre de notifications du
groupe — le test le nomme.

### 2. Les liens acteur → cas, **dans les deux sens**

En ne traçant PAS de lien, le diagramme dit aussi qui ne peut pas. C'est le
cœur de la séparation des pouvoirs : le maire ne gouverne pas les comptes,
l'administrateur ne voit pas les dossiers, l'officier ne signe pas.

Le test précédent vérifiait que l'acteur lié arrive à l'écran. Celui-ci
vérifie que **les trois autres sont refusés**, cas par cas.

**Un refus par 404 compte, et vaut mieux qu'un 403.** Les écrans qui portent
une demande en paramètre passent par la portée globale : hors périmètre, la
demande n'existe pas pour cet acteur, et il obtient 404 avant qu'aucune Policy
ne s'exprime. C'est le raisonnement déjà retenu pour le téléchargement d'un
acte — un 403 confirmerait que le dossier existe.

### Une sonde que j'ai d'abord mal choisie

Ma première tentative pour éprouver ce test a ouvert la gestion des comptes au
maire **dans la Policy** : le test est resté vert. Ma deuxième l'a ouverte
**dans le middleware** : vert encore.

Ce n'était pas une faiblesse du test mais une propriété du système : l'écran
est gardé **deux fois**, indépendamment. Il a fallu ouvrir les deux gardes pour
que le maire passe — et là, le test tombe en nommant le cas et le rôle.

J'aurais pu conclure « le test ne détecte rien » après la première sonde. La
bonne conclusion était : *pourquoi ne détecte-t-il rien ?*

### Un écart assumé, et déclaré

Le diagramme lie **« Consult Notification » au seul Citizen**. Le code l'ouvre
aux quatre rôles, délibérément : quand le maire renvoie un dossier (T8/T11),
l'officier qui le tient doit l'apprendre autrement qu'en rafraîchissant sa
file. Suivre le diagramme à la lettre rendrait la consigne du maire invisible.

L'écart est porté par un test qui tombe si le centre de notifications cesse
d'être commun — pour qu'on en reparle plutôt que de le subir.

---

## D-064 — « Generate Certificate » : l'officier rédige, le maire signe ce qu'il a lu

- **Date :** 2026-09-11
- **Statut :** implémenté ; 694 tests, 0 échec
- **Arbitrage appliqué :** **lecture 2** — l'officier produit un projet d'acte
  que le maire signe ensuite. C'est votre décision sur la divergence rouverte
  en D-062.

### Ce que cette lecture déplace, et ce qu'elle ouvre

Elle transfère la **responsabilité du contenu** de l'acte à l'officier, et
laisse au maire celle de la **délivrance**. C'est cohérent avec le diagramme,
qui place « Generate Certificate » chez l'officier.

Mais elle ouvre une faille que je devais fermer en la construisant : **si
l'officier rédige en amont, il peut modifier le dossier après que le maire a lu
le projet.** Le maire signerait alors autre chose que ce qu'il a vu — un acte
dont le contenu lui aurait échappé. C'est exactement ce que le §4.3 du brief
interdit, et c'était mon objection à la lecture 2.

### Comment elle est fermée

Une **empreinte du contenu**, relevée à la rédaction et recalculée à la
signature. Si elle a bougé, la signature est refusée, et le refus annule la
transition avec le reste de la transaction : aucun acte n'est produit.

**L'empreinte porte sur le contenu, pas sur le PDF.** Le projet porte un
bandeau « PROJET » que l'acte final n'a pas : les deux fichiers diffèrent
forcément, et comparer leurs octets ne dirait rien. C'est le contenu qui doit
être stable, pas la mise en page.

Un test lit le corps du document généré et vérifie que **chaque champ imprimé
est couvert par l'empreinte** : un champ ajouté à l'acte sans être ajouté à
l'empreinte deviendrait modifiable après lecture du maire, sans que rien ne le
signale.

### Ce qui garantit qu'un projet n'est jamais pris pour un acte

- Table **distincte** de `document_signatures` — un projet n'a ni signataire,
  ni preuve, ni valeur.
- Bandeau **« PROJET D'ACTE — NON SIGNÉ — SANS VALEUR »** en tête, avant tout
  le reste, et répété en pied.
- Aucun bloc « Signé par » : à sa place, le nom de l'officier qui l'a rédigé.
- Le citoyen n'y a **aucun accès**.

Le §4.3 tient donc malgré le déplacement : aucun document ayant valeur d'acte
n'existe avant la décision du maire.

### La traçabilité que la lecture 2 rend nécessaire

`document_signatures.draft_id` relie l'acte au projet dont il est issu. La
responsabilité du contenu remonte ainsi **nominativement** à l'officier qui l'a
rédigé — ce que la lecture 1 n'avait pas à faire, puisque le maire composait.

### Douze tests sont tombés, et c'était juste

Leurs fixtures poussaient une demande jusqu'à `awaiting_signature` par un appel
direct au service de transition, sans passer par la décision de l'officier :
elles sautaient une étape devenue réelle. Elles appellent désormais le **même
service** que le contrôleur — fabriquer un `ActDraft` à la main aurait
contourné l'empreinte, c'est-à-dire la seule chose qui rend cette lecture sûre.

Le jeu de démonstration avait le même trou : **aucun dossier n'y était
signable**. Corrigé.

### Le projet est rédigé sur les deux chemins

Acceptation (T4) **et** escalade (T6), parce que les deux peuvent mener à une
signature. N'en couvrir qu'un rendrait la signature impossible après une
escalade — T9.

---
