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
| Version de Laravel et de PHP (8.3 ou 8.4) | À valider avant le jalon 1 |
| Zéro JavaScript, ou JavaScript vanille minimal | À confirmer — voir D-010 |
| ~~Plan Vercel~~ | **Sans objet — tranché en D-011** |
| 2FA du citoyen (TOTP / SMS / aucun) | Jalon 2, après confirmation de la faisabilité SMS |
| Défense en profondeur RLS | Jalon 6, après évaluation du coût |
| Conservation du genre et des données parentales | Après avis juridique |
