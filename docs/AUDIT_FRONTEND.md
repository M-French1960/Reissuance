# Audit du front-end existant — PHOENIX

> Jalon 0. Aucun code applicatif n'a été écrit. Ce document constate l'état réel du
> dépôt à la date de l'audit et sert de base à la décision de portage.

- **Date de l'audit :** 2026-09-05
- **Commit audité :** `fcf05f3` (« Officer »), branche `main`
- **Méthode :** lecture intégrale des 6 fichiers HTML et des 5 fichiers CSS,
  inventaire `find`, analyse croisée des identifiants JS/HTML, exécution réelle
  des pages dans Chromium 1194 via Playwright 1.56.1 (viewport 360 × 740 et
  390 × 844), calcul des rapports de contraste WCAG selon la formule de
  luminance relative.
- **Rien dans ce document n'est déduit de la mémoire du modèle** : chaque
  affirmation renvoie à un fichier, une ligne, ou une trace d'exécution.

---

## 1. Inventaire du dépôt

### 1.1 Vue d'ensemble

| Catégorie | Fichiers | Poids |
|---|---:|---|
| Pages HTML | 6 | 62,4 Ko |
| Feuilles CSS | 5 | 25,1 Ko |
| Image (logo) | 1 | 495 Ko (PNG 1024 × 1024) |
| Icônes SVG vendorisées | 2 045 | `brands/` 490, `regular/` 163, `solid/` 1 392 |
| Configuration | 1 | `.gitattributes` |

**Total suivi en versionnement : 2 058 fichiers, dont 2 045 (99,4 %) sont des
icônes SVG.**

### 1.2 Arborescence réelle (hors icônes)

```
.
├── .gitattributes
├── Applicant Dashboard.html      165 lignes
├── Login.html                    115 lignes
├── signin.html                   147 lignes
├── form.html                     618 lignes
├── officer-dashboard.html        241 lignes
├── officer-verification.html     531 lignes
├── style.css                     275 lignes
├── form.css                      525 lignes
├── officer.css                   485 lignes
├── login.css                     105 lignes
├── sign.css                      124 lignes
├── logo.png
├── brands/    (490 SVG)
├── regular/   (163 SVG)
└── solid/    (1 392 SVG)
```

### 1.3 Constats d'inventaire

- **Les 2 045 SVG ne sont référencés nulle part.** Vérifié :
  `grep -rn -E '(brands|regular|solid)/[a-z0-9-]+\.svg' *.html *.css` ne
  retourne aucune occurrence. Ce sont des icônes Font Awesome copiées en vrac.
  Les pages chargent Font Awesome **depuis le CDN cdnjs**, pas depuis ces
  fichiers. Ces répertoires sont du poids mort et doivent être supprimés du
  dépôt lors du portage.
- **Le nom de fichier `Applicant Dashboard.html` contient une espace.** Il est
  donc lié depuis `signin.html` sous la forme `Applicant Dashboard.html`, ce qui
  produit une URL non encodée. À proscrire.
- **`logo.png` pèse 495 Ko pour un affichage à 100 × 100 px.** Sur 3G, c'est à
  lui seul plus que le budget CSS complet du projet (< 50 Ko).
- L'historique Git ne compte que 6 commits, dont 3 aux messages non descriptifs
  (`DGAF`, `DSGAF`, `Officer`). Un fichier `screen.png` a été ajouté puis
  supprimé.
- **Aucun** : `package.json`, `composer.json`, `.gitignore`, `.env.example`,
  `README.md`, dossier `docs/`, configuration de CI, test.

---

## 2. Écrans réellement présents — et écart avec le brief

Le brief de cadrage annonce des fichiers qui **n'existent pas** dans le dépôt.
Vérifié sur l'ensemble de l'historique
(`git log --all --diff-filter=A --name-only`) : ils n'ont jamais existé.

| Écran annoncé au brief | État réel |
|---|---|
| `Applicant-Dashboard.html` | Existe sous le nom **`Applicant Dashboard.html`** (espace) |
| `officer-dashboard.html` | ✅ Présent |
| `officer-verification.html` | ✅ Présent |
| **`mayor-dashboard.html`** | ❌ **N'existe pas, n'a jamais existé** |
| **`mayor-request-review.html`** | ❌ **N'existe pas, n'a jamais existé** |
| Écrans administrateur | ❌ Absents (conforme au brief) |

En revanche, trois écrans **non annoncés** existent :

| Écran non annoncé | Rôle réel |
|---|---|
| `signin.html` | Formulaire d'**inscription** citoyen (malgré son nom) |
| `Login.html` | Tentative de formulaire de **connexion** (cassé, voir §5) |
| `form.html` | Assistant de demande en 4 étapes, **paiement inclus** |

> **Conséquence de planification :** le parcours maire est à créer intégralement,
> au même titre que le parcours administrateur. Le jalon 5 ne dispose d'aucune
> maquette de départ. Il faut le prévoir dans le budget du jalon 5, ou produire
> des maquettes en amont.

---

## 3. Parcours réellement implémentés

```
signin.html  ──(submit OK)──▶  Applicant Dashboard.html  ──▶  form.html
 (inscription)                  (tableau de bord citoyen)      (demande 4 étapes)
     ▲                                                              │
     │                                                              ✗ impasse
 Login.html ──(submit)──✗ ÉCHEC JS, aucune navigation                 (aucune suite)

officer-dashboard.html ──▶ officer-verification.html?id=…
   (file de 4 demandes)      (5 étapes, 2 demandes connues sur 4)
                                      │
                                      ✗ décision affichée dans un alert(),
                                        jamais persistée
```

**Aucune passerelle entre le parcours citoyen et le parcours officier.** Les deux
îlots partagent le nom « PHOENIX » et rien d'autre : pas de jeu de données
commun, pas de vocabulaire de statut commun, pas de format d'identifiant commun.

---

## 4. Données en dur (mocks) — emplacement exact

Toutes les données sont des littéraux JavaScript inclus dans les pages. Aucun
appel réseau, aucun stockage autre que `localStorage`.

| Fichier | Lignes | Contenu | À supprimer au portage |
|---|---|---|---|
| `officer-dashboard.html` | 111-148 | Tableau `requests` : 4 demandes (référence, nom, type, centre, statut, date) | Oui — remplacé par une requête Eloquent paginée |
| `officer-verification.html` | 305-362 | Tableau `requests` : 2 demandes enrichies (identité complète, n° de pièce, téléphone, e-mail, URL de photos, `policeMatch`, `civilRecord`) | Oui — remplacé par les adaptateurs `FakeIdentityLookupProvider` / `FakeCivilRegistryProvider` |
| `officer-verification.html` | 319, 320, 348, 349 | `selfieImage` / `idImage` pointant vers `https://placehold.co` | Oui — remplacé par des URL signées de courte durée |
| `form.html` | 249-257 | Liste des centres d'état civil en dur dans un `<select>` (« Yaounde 1 » … « Yaounde 7 ») | Oui — table `civil_status_centers` |
| `officer-dashboard.html` | 65-74 | Même liste, **libellés et valeurs différents** (`yaounde-1`, « Yaoundé I ») | Oui — même table |
| `form.html` | 320, 350, 359, 614 | Montant « 20 000 CFA » codé en dur dans le texte et le libellé du bouton | Oui — voir §5.6 |
| `Applicant Dashboard.html` | 122-163 | Lecture/écriture de `userName`, `userEmail`, `userGender`, `userPassword` en `localStorage` | Oui — session Laravel |
| `signin.html` | 137-141 | **Écriture du mot de passe en clair dans `localStorage`** | Oui — voir §6.1 |

### 4.1 Incohérence de référentiel entre les deux mocks officier

`officer-dashboard.html` filtre sur `req.center` avec les valeurs du `<select>`
(`yaounde-1` … `yaounde-7`), mais les 4 demandes portent les valeurs
`yaounde-centre`, `yaounde-2`, `douala-centre`, `Yaounde 1`. **Trois demandes sur
quatre sont donc invisibles dès qu'on filtre par centre**, et « Douala Centre »
apparaît dans une liste censée ne contenir que Yaoundé.

C'est le symptôme d'un manque à combler impérativement au jalon 1 : **un
référentiel unique des centres et des communes, en base, avec clé étrangère.**

### 4.2 Noms figurant dans les mocks

Les mocks contiennent des noms de personnes (« Jean Dupont », « Marie Ngoa »,
« Paul MOHAMED », « Grace Kamga », « A. Mbarga »), des numéros de pièce
(`123456789`, `987654321`), des téléphones et des e-mails. **Je n'ai aucun moyen
de savoir s'ils sont inventés ou repris de personnes réelles.** Le garde-fou n°1
du projet interdit toute donnée réelle de citoyen dans le dépôt. Ces valeurs
seront intégralement remplacées par des jeux de démonstration explicitement
fictifs (préfixe `DEMO-`, domaine `@example.test`) et ne seront jamais reprises
telles quelles.

---

## 5. Défauts bloquants constatés — avec preuve d'exécution

Chaque défaut ci-dessous a été **reproduit dans Chromium**, pas seulement déduit
de la lecture du code.

### 5.1 `Login.html` — la connexion est impossible

Le fichier est une copie de `signin.html` dont on a supprimé les champs
« Email » et « Genre » **dans le HTML uniquement**. Le script conserve :

```js
const emailError = document.getElementById('email-error');   // → null
…
emailError.textContent = '';                                  // → TypeError
```

Trace d'exécution (clic sur « Sign Up » après saisie) :

```
PAGEERROR: Cannot set properties of null (setting 'textContent')
URL apres submit: Login.html          ← aucune navigation
```

De plus, en cas de succès la page redirigerait vers `dashboard.html`, **qui
n'existe pas** (`signin.html` redirige, lui, vers `Applicant Dashboard.html`).

Accessoirement : le `<title>`, le `<h1>` et le bouton affichent tous « Sign
Up », et le lien du bas dit « Don't have an account? **Log in** » en pointant
vers la page d'inscription. La page de connexion se présente donc à
l'utilisateur comme une page d'inscription.

### 5.2 `form.html` — la capture photo n'existe pas

Le script référence sept éléments d'une modale caméra qui **n'est présente nulle
part dans le HTML** :

```
#camera-modal  #camera-modal-title  #camera-video  #camera-canvas
#capture-btn   #retake-btn          #close-camera-btn
```

Conséquence en chaîne, vérifiée : à la ligne 505,
`closeCameraBtn.addEventListener(...)` lève `TypeError` et **interrompt
l'exécution du reste du script**. Tout ce qui suit n'est jamais enregistré :

| Ligne | Écouteur | État |
|---|---|---|
| 394-448 | navigation entre étapes | enregistré, fonctionne |
| 461, 467 | ouverture caméra | enregistré, **lève une exception au clic** |
| 505 | fermeture caméra | ✗ **exception ici** |
| 507 | capture | ✗ jamais enregistré |
| 551 | reprise de photo | ✗ jamais enregistré |
| 585 | **soumission du paiement** | ✗ jamais enregistré |

Trace : `PAGEERROR: Cannot read properties of null (reading 'addEventListener')`,
puis clic sur « Pay 20 000 CFA » → `dialogues declenches: 0`, message d'erreur
resté vide. **Le bouton de paiement est totalement inerte.**

### 5.3 `form.html` — aucun contrôle de progression

Test : depuis l'étape 1 vierge, trois clics sur « Continuer » suffisent à
atteindre l'écran de paiement.

```
panneau visible: step-4-panel
selfie files: 0
id-doc files: 0
```

Aucun champ n'est requis pour avancer, ni le nom, ni l'e-mail, ni le selfie, ni
la pièce d'identité — alors même que les deux `<input type="file">` portent
l'attribut `required` (inopérant : ils sont hors de tout `<form>`).

### 5.4 `Applicant Dashboard.html` — deux scripts en conflit, et un contenu inapproprié

- Deux blocs `<script>` se disputent le même élément `#welcome-text`.
- Le second référence `#user-info` et `#signout-btn`, **absents du HTML** →
  deux `PAGEERROR` vérifiés. **La déconnexion est impossible.**
- La barre latérale du tableau de bord **citoyen** affiche « PHOENIX — Officer
  Portal » et quatre liens vers les écrans **officier** : le citoyen se voit
  proposer la navigation d'un agent. Copier-coller non nettoyé.
- Le HTML est malformé : `<i class="…"></div> Welcome!` — une `</div>`
  fermante à l'intérieur d'un `<h1>`.
- L'icône du bandeau d'accueil est
  `<i class="fa-solid fa-hand-middle-finger">` — **un doigt d'honneur**, sur
  l'écran d'accueil d'un service public. À supprimer sans délai.
- Deux cartes pointent vers `users.html` et `messages.html`, **inexistantes**.
- Le badge de notifications affiche « 99+ » en dur.

### 5.5 `officer-verification.html` — le contrôle anti-fraude n'existe pas

C'est le défaut le plus grave au regard de l'exigence n°1.

1. **Les 5 étapes ne sont pas séquencées.** `goToStep(5)` est atteignable
   depuis l'étape 4 sans avoir lancé la vérification police (étape 2) ni la
   recherche à l'état civil (étape 4). Rien ne vérifie qu'une étape antérieure
   a produit un résultat.
2. **Aucune étape n'est persistée.** Une vérification interrompue est perdue :
   tout l'état vit dans la variable `currentRequest` en mémoire.
3. **La décision n'est pas enregistrée.** `submitDecision()` se termine par un
   `alert()` puis une redirection. Un commentaire dans le code le dit :
   `// In a real app, send to Supabase`.
4. **La décision « Accepter » est le choix par défaut du `<select>`** et ne
   demande aucun motif. Un clic accidentel sur « Submit decision » vaut
   acceptation.
5. Le tableau de bord propose « Verify » pour les 4 demandes, mais la page de
   vérification n'en connaît que 2. Vérifié : `?id=PHX-00000003` →
   `DIALOG: Request not found.` puis redirection. **Impasse.**

### 5.6 Le paiement est présent alors qu'il est hors périmètre

`form.html` implémente une étape 4 « Payment » avec MTN MoMo / Orange Money et
un montant de **20 000 CFA en dur**. Or le brief place le paiement au jalon 7.

**Je ne peux pas confirmer que 20 000 CFA soit le tarif réel d'une réédition
d'acte de naissance au Cameroun, ni qu'un tel tarif existe sous cette forme.**
Ce chiffre doit être traité comme une valeur inventée par le prototype jusqu'à
confirmation par une source officielle. Il ne sera repris dans aucun code.

### 5.7 Chemin d'image absolu Windows

`Login.html:11` et `signin.html:11` :

```html
<img src="H:\WEBSITES\PROJECT\Reissuance\logo.png">
```

Trace : `net::ERR_UNKNOWN_URL_SCHEME`. **Le logo est cassé sur les deux pages
d'authentification, pour tout le monde, partout.**

---

## 6. Ce que le prototype préfigure de dangereux

Ces points ne sont pas des bugs du prototype : ce sont des habitudes à ne
surtout pas porter.

### 6.1 Mot de passe stocké en clair côté navigateur

`signin.html:141` :

```js
localStorage.setItem('userPassword', password);
```

Le commentaire juste au-dessus le reconnaît (« Don't store real passwords like
this in production »). Politique de mot de passe actuelle : **4 caractères
minimum**, aucune vérification de fuite, aucun hachage.

### 6.2 Injection HTML dans le poste de travail de l'officier

`officer-dashboard.html:170` construit chaque ligne du tableau par concaténation
de chaînes dans `innerHTML` :

```js
tr.innerHTML = "<td>" + req.applicantName + "</td>" + …
```

Branché sur des données réelles, **un nom de demandeur contenant du balisage
s'exécute dans la session de l'officier** : XSS stocké, dans l'écran même où se
prennent les décisions d'authentification. C'est exactement le chemin d'attaque
qui transforme une faille en acte frauduleux. En Blade, `{{ }}` échappe par
défaut — c'est une des raisons pour lesquelles la réécriture est préférable à la
reprise.

### 6.3 Autorisation inexistante et non préfigurée

Aucune page ne vérifie quoi que ce soit. On ouvre `officer-dashboard.html`
directement par son URL. La notion de « centre de rattachement de l'officier »
n'existe nulle part : le filtre par centre est un simple `<select>` qui laisse
voir **tous** les centres, y compris Douala. Le cloisonnement exigé
(§4.2 du brief) devra être construit intégralement.

### 6.4 Données d'identité manipulées sans précaution

Numéros de pièce, dates de naissance, téléphones et e-mails circulent en clair
dans des littéraux JS, et les photos sont des URL publiques
(`placehold.co`). Aucune journalisation de consultation n'est esquissée.

### 6.5 Dépendance à un CDN étranger

Les quatre pages qui utilisent des icônes chargent Font Awesome depuis
`cdnjs.cloudflare.com` — trois feuilles distinctes sur `Applicant
Dashboard.html`. Pour un service public camerounais consulté en 3G, c'est à la
fois une dépendance de disponibilité, une fuite de métadonnées de navigation
vers un tiers, et une pénalité de performance. Les icônes seront intégrées en
SVG inline ou en sprite local.

*(Les erreurs `ERR_CONNECTION_RESET` observées sur ces requêtes proviennent du
bac à sable réseau de l'audit, pas d'un défaut du dépôt. Le constat de
dépendance, lui, est établi par lecture du code.)*

---

## 7. Champs de formulaire existants

### 7.1 `signin.html` — inscription citoyen

| Champ | `id` | Type | Validation actuelle |
|---|---|---|---|
| Username | `username` | `text` | non vide |
| Email | `email` | `email` | non vide + regex `^[^\s@]+@[^\s@]+\.[^\s@]+$` |
| Password | `password` | `password` | non vide, **≥ 4 caractères** |
| Gender | `gender` | radio (male/female/other) | une option requise |

`novalidate` désactive la validation native ; toute la validation est en
JavaScript, donc **contournable en désactivant JS**. Aucune confirmation de mot
de passe, aucun nom de famille, aucune date de naissance, aucun consentement.

### 7.2 `Login.html` — connexion

Deux champs (`username`, `password`). Validation inopérante (§5.1).

### 7.3 `form.html` — demande de réédition

**Aucun de ces 17 champs n'est validé, ni côté client ni côté serveur.** Aucun
n'est dans un élément `<form>`.

| Étape | Champ | `id` | Type | Validation |
|---|---|---|---|---|
| 1 | Full Name | `applicant-name` | text | ✗ |
| 1 | Email Address | `applicant-email` | email | ✗ |
| 1 | Phone Number | `applicant-phone` | tel | ✗ (placeholder `+237 6XX XXX XXX`) |
| 1 | Selfie | `selfie-file` | file (`accept="image/*"`, `required` inopérant) | ✗ |
| 1 | Pièce d'identité | `id-doc-file` | file (idem) | ✗ |
| 2 | Full Name at Birth | `full-name-birth` | text | ✗ |
| 2 | Date of Birth | `dob` | date | ✗ |
| 2 | Year of Registration | `year-reg` | text | ✗ (devrait être numérique borné) |
| 2 | Place of Birth | `place-of-birth` | text | ✗ |
| 2 | Father's Full Name | `father-name` | text | ✗ |
| 2 | Mother's Full Name | `mother-name` | text | ✗ |
| 2 | Father's Nationality | `father-nationality` | text | ✗ (saisie libre → devrait être une liste) |
| 2 | Mother's Nationality | `mother-nationality` | text | ✗ (idem) |
| 2 | Parents' Address | `parents-address` | text | ✗ |
| 2 | Civil Status Center | `civil-center` | select | ✗ (option vide acceptée) |
| 2 | Original Certificate Number | `cert-number` | text | ✗ (optionnel) |
| 4 | Payment Method | `payment-method` | radio | ✗ (contrôle écrit mais jamais activé) |
| 4 | Mobile Number | `payment-phone` | tel | ✗ (idem) |

**Champs présents dans le prototype mais absents du modèle de données du brief
(§6)** : nom à la naissance, année d'enregistrement, lieu de naissance, identité
et nationalité des deux parents, adresse des parents, numéro de certificat
d'origine. Ce sont précisément les champs qui permettent de **retrouver l'acte
d'origine** à l'étape 4 de l'officier. Le modèle de données devra les intégrer —
c'est un apport réel du prototype.

**Champs exigés par le brief mais absents du prototype** : choix explicite de la
commune (distinct du centre), motif de la demande (perte / détérioration),
nombre d'exemplaires, consentement au traitement des données biométriques.

### 7.4 `officer-verification.html` — décision

| Champ | `id` | Type | Validation |
|---|---|---|---|
| Action | `decisionAction` | select (accepted/rejected/escalated) | ✗ défaut = `accepted` |
| Reason | `decisionReason` | textarea | requis **uniquement** si rejet/escalade |
| Internal notes | `decisionNotes` | textarea | optionnel |
| Recherche par nom | `searchByName` | text | ✗ |
| Recherche par n° pièce | `searchById` | text | ✗ |

---

## 8. Évaluation du CSS existant

### 8.1 Métriques mesurées

| Fichier | Lignes | `@media` | Couleurs hex | Rôle |
|---|---:|---:|---:|---|
| `style.css` | 275 | **0** | 21 (13 distinctes) | Tableau de bord citoyen |
| `form.css` | 525 | **0** | 58 (27 distinctes) | Assistant de demande |
| `officer.css` | 485 | 5 | 52 (26 distinctes) | Portail officier |
| `login.css` | 105 | **0** | 13 (10 distinctes) | Connexion |
| `sign.css` | 124 | **0** | 15 (10 distinctes) | Inscription |

**56 couleurs hexadécimales distinctes, zéro variable CSS, zéro token.** Le
violet de marque apparaît sous au moins quatre formes non interchangeables :
`#4a148c`, `#6a1b9a`, `#7b1fa2`, `rgb(91, 15, 177)`.

`login.css` et `sign.css` sont **quasi identiques** : 21 lignes de différence sur
105, dont une seule règle de fond (`#fdf3ff` vs `#ffffff`). Duplication pure.

`style.css:47` contient une déclaration invalide : `background-color: ;`.

### 8.2 Le mobile-first n'existe pas

`style.css` fixe des largeurs absolues :

```css
.p    { width: 1320px; }
.bod  { width: 1100px; }
.navbar { width: 1120px; }
.hey  { width: 1100px; height: 900px; }
#stats { width: 950px; }
```

Mesure sur viewport 360 px :

| Page | `scrollWidth` | Débordement |
|---|---:|---|
| `Applicant Dashboard.html` | **1 354 px** | **× 3,76** |
| `form.html` | **447 px** | × 1,24 |
| `officer-dashboard.html` | 360 px | aucun |
| `officer-verification.html` | 360 px | aucun |
| `signin.html` / `Login.html` | 360 px | aucun |

Aggravant : **`form.html`, `Login.html` et `signin.html` n'ont pas de balise
`<meta name="viewport">`.** Un navigateur mobile réel les rendra donc dans une
fenêtre virtuelle d'environ 980 px puis dézoomera — texte illisible. Le résultat
mesuré ci-dessus est meilleur que la réalité terrain.

Les hauteurs sont figées elles aussi (`height: 1000px`, `height: 900px`), ce qui
tronque le contenu dès qu'il grandit.

`officer.css` est le seul fichier responsive, avec 5 points de rupture
(1100 / 900 / 800 / 600 px) — **desktop-first**, donc l'inverse de la stratégie
retenue, mais la structure de grille y est saine.

### 8.3 Accessibilité — contrastes mesurés

Calcul WCAG 2.1 (luminance relative). Seuil AA texte normal : 4,5:1.

| Couple | Ratio | Verdict |
|---|---:|---|
| `#9e9eaf` sur `#f5f5f7` — étape inactive du stepper | **2,42:1** | **Échec** |
| `#667eea` sur blanc — lien « View users → », bouton `.camera-btn`, `.submit-btn` | **3,66:1** | **Échec** (texte normal) |
| `#807b92` sur blanc — `.hint` (11 px) | **4,06:1** | **Échec** |
| `#ffffff` sur `#e53935` — badge de notification | **4,23:1** | **Échec** |
| `#777083` sur `#fdf3ff` — pied de page | **4,39:1** | **Échec** |
| `#64748b` sur `#f4f7fb` — texte secondaire officier | **4,43:1** | **Échec (limite)** |
| `#64748b` sur blanc | 4,76:1 | OK |
| `#666273` sur `#f5f5f7` | 5,42:1 | OK |
| `#b91c1c` sur `#fee2e2` — badge escaladé | 5,30:1 | OK |
| `#92400e` sur `#fef3c7` — badge en attente | 6,37:1 | OK |
| `#065f46` sur `#ecfdf5` — badge vérifié | 7,29:1 | OK |
| `#ffffff` sur `#6a1b9a` — bouton primaire | 9,39:1 | OK |
| `#ffffff` sur `#4a148c` — barre latérale | 11,86:1 | OK |

**Six couples sur treize échouent au niveau AA.** Le fait que les boutons
d'action principaux du citoyen (`.submit-btn`, `.camera-btn` en `#667eea`)
soient parmi les échecs est significatif.

Autres écarts relevés :

- **Aucun style de focus sur les boutons ni sur les liens.** `grep focus *.css`
  ne retourne que 4 occurrences, toutes sur `input:focus`. Aucun
  `:focus-visible`. La navigation au clavier est donc invisible — échec WCAG
  2.4.7.
- Les six pages déclarent `lang="en"` alors que l'interface cible est le
  français. Aucun dispositif d'internationalisation.
- Les badges de statut ne portent **que** la couleur comme information (échec
  WCAG 1.4.1) et affichent la valeur technique brute (`awaiting_signature`
  n'existe pas encore, mais `escalated` et `pending` sont affichés tels quels).
- `officer-verification.html` compte **11 gestionnaires `onclick` en ligne** et
  `officer-dashboard.html` 1 — incompatibles avec la CSP sans `unsafe-inline`
  exigée au §4.5.
- Les erreurs et confirmations passent par `alert()` : non stylable, non
  traduisible, non lisible par lecteur d'écran de façon fiable, et bloquant.

### 8.4 Verdict sur le CSS

**Le CSS existant n'est pas réutilisable comme base d'un design system.**
Motifs :

1. Zéro token, 56 couleurs concurrentes — l'inverse de l'exigence §8.3.
2. Deux fichiers sur cinq seulement sont utilisables au-delà de 1 024 px de
   large ; `style.css` est structurellement incompatible avec le mobile-first.
3. Un tiers des couples de contraste échoue en AA.
4. Aucun état de focus.

**Ce qui est en revanche récupérable, et doit l'être :**

- **La palette de marque**, une fois réduite à une échelle cohérente à partir de
  `#4a148c` (le violet dominant, contraste 11,86:1 sur blanc — excellent).
- **La sémantique des badges de statut** de `officer.css` (ambre / vert / rouge /
  gris), qui passe déjà AA : elle devient la base des tokens sémantiques.
- **La grille du portail officier** (`.layout` / `.sidebar` / `.main`,
  `.profile-grid`, `.images-grid`) : structure saine, à réécrire proprement en
  CSS moderne (grille, `clamp()`, requêtes de conteneur si utile).
- **L'ossature du stepper** de `form.css` (cercles + libellés + lignes), à
  reconstruire avec les états accessibles.

---

## 9. Écarts entre le prototype et la spécification §5

| Exigence du brief | État du prototype | Écart |
|---|---|---|
| **Administrateur** — création de comptes officier/maire, activation, suspension, réinitialisation, réaffectation | Néant | **Total** |
| **Citoyen** — inscription / connexion | `signin.html` OK, `Login.html` cassé | Connexion à refaire |
| **Citoyen** — profil et mise à jour | Néant | **Total** |
| **Citoyen** — confirmation du profil avant demande | Absent (`form.html` ressaisit tout) | Total |
| **Citoyen** — choix du centre | `<select>` en dur, 7 options Yaoundé | Référentiel à créer |
| **Citoyen** — selfie et pièce en direct | UI présente, **modale absente, code mort** | Fonctionnalité à écrire |
| **Citoyen** — suivi de statut / frise chronologique | Néant (2 cartes de compteur à 0) | **Total** |
| **Citoyen** — téléchargement du document final | Néant | **Total** |
| **Citoyen** — paiement | Présent (hors périmètre, montant inventé) | À **retirer** du jalon 3 |
| **Officier** — file filtrable | Présente, filtre par centre cassé (§4.1) | À refaire sur données réelles |
| **Officier** — 5 étapes | UI présente, **non séquencée, non persistée** | Machine à états à écrire |
| **Officier** — vérification police | Simulée par un objet en dur | Contrat `IdentityLookupProvider` à définir |
| **Officier** — recherche état civil | Simulée par un objet en dur | Contrat `CivilRegistryProvider` à définir |
| **Officier** — comparaison selfie / pièce | Deux `<img>` côte à côte | Ni zoom, ni miniature, ni chargement différé |
| **Officier** — décision avec motif | Présente, motif requis en JS seulement | À rendre opposable côté données |
| **Maire** — double file | **Aucun écran** | **Total** |
| **Maire** — signature électronique | **Aucun écran** | **Total** |
| **Maire** — traitement des escalades | **Aucun écran** | **Total** |
| **Journal d'audit** | Néant | **Total** |
| **Notifications** | Badge « 99+ » en dur | Total |

### 9.1 Vocabulaire de statut : trois référentiels incompatibles

| Source | Statuts |
|---|---|
| Brief §7 (cible) | `pending`, `under_review`, `awaiting_signature`, `signed`, `escalated`, `rejected` |
| `officer-dashboard.html` | `pending`, `verified`, `escalated`, `rejected`, `completed` |
| `officer.css` (badges) | `pending`, `verified`, `escalated`, `rejected` — **pas de `completed`** |

Le statut `completed` est produit par le mock mais **n'a aucune classe CSS** : le
badge s'affiche sans style. Symptôme du problème de fond : le vocabulaire de
statut n'est défini nulle part de façon unique. Il devra l'être **une seule
fois**, dans une énumération PHP adossée à une contrainte en base
(`docs/STATE_MACHINE.md`), et l'interface n'affichera jamais la valeur technique
mais son libellé traduit (« En attente de signature du maire »).

---

## 10. Verdict par écran

| Écran | Verdict | Justification |
|---|---|---|
| `signin.html` | **À refaire** | Base Fortify/Breeze. Le HTML sert de référence visuelle uniquement. Politique de mot de passe et champs à revoir entièrement. |
| `Login.html` | **À refaire** | Cassé, mal intitulé, redirige vers une page inexistante. Rien à sauver. |
| `Applicant Dashboard.html` | **À refaire** | Largeurs fixes 1320 px, deux scripts en conflit, navigation officier sur un écran citoyen, icône obscène, liens morts. La structure « bandeau + cartes + frise en 4 étapes » est une bonne idée à reprendre — le code, non. |
| `form.html` | **À porter, en profondeur** | **Le seul écran dont le contenu métier a une vraie valeur** : le découpage en 4 étapes et surtout les champs « acte de naissance + parents » sont pertinents et vont au-delà du brief. Le code JS est à jeter (capture morte, aucune validation, aucun gating). Devient un formulaire multi-étapes rendu côté serveur, une vue par étape (l'étape paiement est retirée jusqu'au jalon 7). |
| `officer-dashboard.html` | **À porter** | Meilleure qualité du dépôt : responsive, structure de table saine, badges de statut accessibles. À rebrancher sur Eloquent paginé, à filtrer par centre de l'officier côté serveur, et à débarrasser de `innerHTML`. |
| `officer-verification.html` | **À porter, avec réécriture de la logique** | La maquette des 5 étapes est bonne et sert de référence d'ergonomie. Toute la logique (séquencement, persistance, décision) est à écrire. Les 11 `onclick` en ligne sautent. |
| Écrans **maire** | **À créer** | Inexistants. |
| Écrans **administrateur** | **À créer** | Inexistants. |

**Synthèse : 0 écran réutilisable tel quel, 3 à porter, 2 à refaire, 3+ à
créer.**

---

## 11. Stratégie de portage retenue

Conforme au §1 du brief : le HTML existant sert de **structure de départ et de
référence visuelle**, jamais de code copié.

> **Révisée le 2026-09-06 par la décision D-010** : les interfaces restent en
> HTML et CSS écrits à la main. Ni Tailwind, ni Livewire, ni Alpine. Blade est
> conservé comme moteur de gabarits, pour l'héritage de vues et surtout pour
> l'échappement par défaut de `{{ }}`, qui corrige la faille d'injection du §6.2.

1. **Extraire d'abord les tokens** dans un unique `tokens.css`, sous forme de
   **propriétés personnalisées CSS** déclarées sur `:root`, à partir de la
   palette réduite du prototype, **avec les contrastes corrigés** — les 6
   couples en échec ne sont pas reportés. Aucune valeur en dur dans une vue.
2. **Construire la bibliothèque de composants Blade sans état**
   (`<x-button>`, `<x-field>`, `<x-status-badge>`, `<x-request-card>`,
   `<x-empty-state>`…) et la galerie `/dev/ui` avant de porter le premier
   écran, afin qu'aucun écran ne réintroduise de valeur en dur.
3. **Porter écran par écran**, en découpant chaque page en vues et composants :
   - `form.html` → une vue par étape, chaque étape validée côté serveur et
     persistée comme brouillon avant redirection vers la suivante
   - `officer-dashboard.html` → vue de file (filtres, tri, pagination serveur)
     + composants `<x-status-badge>` et `<x-request-row>`
   - `officer-verification.html` → une vue par étape de vérification, chacune
     persistant son résultat dans `verification_steps` avant de passer à la
     suivante
   - Les 12 attributs `onclick` en ligne du prototype ne sont **pas** portés :
     ils sont incompatibles avec la CSP stricte du §4.5, désormais atteignable
4. **Aucun `<script>` de mock ne survit** au branchement d'un écran. Le
   `localStorage` disparaît intégralement au profit de la session Laravel. Le
   seul JavaScript conservé est du code vanille, sans dépendance, limité aux
   trois besoins qui reposent sur une API du navigateur : capture photo
   (`getUserMedia`), compression avant envoi (`canvas` — imposée par D-008) et
   envoi direct vers le stockage objet par URL pré-signée.
5. **Supprimer du dépôt** : `brands/`, `regular/`, `solid/` (2 045 fichiers non
   référencés), et recompresser `logo.png` (495 Ko → cible < 30 Ko en WebP/PNG
   optimisé, avec les tailles nécessaires).
6. **Renommer** `Applicant Dashboard.html` : aucun nom de fichier avec espace.

---

## 12. Points portés à l'arbitrage

Ces points sortent du constat et relèvent d'une décision qui n'est pas la mienne.
Ils sont repris dans la liste des questions bloquantes du jalon 0.

1. **Le parcours maire n'a aucune maquette.** Le jalon 5 part de zéro.
2. **Le tarif « 20 000 CFA » est invérifiable** en l'état ; aucune valeur
   monétaire ne sera codée avant confirmation officielle.
3. **Le prototype collecte le genre à l'inscription** sans usage identifié.
   Collecte de donnée personnelle sans finalité : à supprimer, sauf si l'acte
   d'état civil l'exige — auquel cas la donnée appartient au dossier, pas au
   compte.
4. **Le prototype collecte l'identité et la nationalité des deux parents.**
   C'est utile à la recherche de l'acte d'origine, mais cela élargit
   sensiblement l'assiette de données personnelles. À valider au regard des
   obligations de protection des données (voir
   `docs/COMPLIANCE_OPEN_QUESTIONS.md`).
5. **Le référentiel géographique est à établir** : le prototype mélange 7
   arrondissements de Yaoundé et un « Douala Centre ». Le périmètre réel du
   déploiement initial doit être défini avant les migrations.
