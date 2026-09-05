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
- **Statut :** décidé par le commanditaire
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
- **Statut :** décidé
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
- **Statut :** décidé
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
- **Statut :** décidé
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
- **Statut :** recommandation confirmée, **reste à valider par mesure au jalon 1**
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

## Points explicitement **non** tranchés à ce stade

Ils sont listés ici pour éviter qu'une décision implicite ne s'installe.

| Sujet | Où il sera tranché |
|---|---|
| Mode de connexion Supabase — *recommandation arrêtée en D-009, à valider par mesure* | `docs/DATABASE.md` — jalon 1 |
| Stockage des pièces d'identité (Supabase Storage / Vercel Blob) | `docs/STORAGE.md` — jalon 1 |
| Version de Livewire (3 ou 4) et de Tailwind (3 ou 4) | À valider avec le commanditaire avant le jalon 1 |
| ~~Plan Vercel~~ | **Tranché en D-005** |
| 2FA du citoyen (TOTP / SMS / aucun) | Jalon 2, après confirmation de la faisabilité SMS |
| Défense en profondeur RLS | Jalon 6, après évaluation du coût |
| Conservation du genre et des données parentales | Après avis juridique |
