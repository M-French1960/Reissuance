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

## Points explicitement **non** tranchés à ce stade

Ils sont listés ici pour éviter qu'une décision implicite ne s'installe.

| Sujet | Où il sera tranché |
|---|---|
| Mode de connexion Supabase (direct / pooler session / pooler transaction) | `docs/DATABASE.md`, après mesure réelle — jalon 1 |
| Stockage des pièces d'identité (Supabase Storage / Vercel Blob) | `docs/STORAGE.md` — jalon 1 |
| Version de Livewire (3 ou 4) et de Tailwind (3 ou 4) | À valider avec le commanditaire avant le jalon 1 |
| Plan Vercel (contrainte de fréquence des tâches planifiées) | Question bloquante — voir la réponse de cadrage |
| 2FA du citoyen (TOTP / SMS / aucun) | Jalon 2, après confirmation de la faisabilité SMS |
| Défense en profondeur RLS | Jalon 6, après évaluation du coût |
| Conservation du genre et des données parentales | Après avis juridique |
