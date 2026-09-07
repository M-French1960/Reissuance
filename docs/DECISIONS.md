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
| Défense en profondeur RLS | Jalon 6, après évaluation du coût |
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
