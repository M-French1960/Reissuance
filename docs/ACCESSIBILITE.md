# Accessibilité — audit mesuré

> Jalon 6. Toutes les valeurs de ce document ont été **mesurées**, dans un
> navigateur réel, sur l'application en fonctionnement. Aucune n'est estimée.

---

## 1. Méthode

- **Navigateur :** Chromium, piloté par Playwright.
- **Fenêtre :** 390 × 844 px — le format le plus contraignant du parc visé
  (§8.1 du brief : téléphone, réseau et appareil modestes).
- **Outil :** `axe-core` 4.10.2, jeux de règles `wcag2a`, `wcag2aa`,
  `wcag21a`, `wcag21aa`.
- **Périmètre :** **17 écrans**, atteints avec les comptes qui les voient
  réellement — trois écrans anonymes, puis les parcours citoyen, officier,
  maire et administrateur.
- **En plus d'axe :** parcours au clavier seul, visibilité du focus,
  débordement horizontal, et mesure de **chaque** cible tactile.

Ce que cette méthode ne couvre pas : l'essai par une personne qui utilise
réellement un lecteur d'écran. Un outil automatique détecte de l'ordre du tiers
des problèmes d'accessibilité. **Ce document n'est pas une preuve de
conformité.**

---

## 2. Résultat après correction

| Mesure | Valeur |
|---|---|
| Écrans audités | 17 |
| Violations axe (WCAG 2.1 A + AA) | **0** |
| Débordement horizontal à 390 px | **0 px** sur les 17 écrans |
| Cibles tactiles sous 44 px | **0** |
| Éléments invisibles atteints au clavier | **0** |

---

## 3. Ce que l'audit a trouvé, et qui est corrigé

### 3.1 Le tableau de bord de l'administrateur renvoyait 500

**Depuis le jalon 2.** La vue reconvertissait une énumération déjà convertie :
`UserRole::from($row->role)` sur un modèle `User`, dont le cast a déjà rendu un
`UserRole`. L'écran d'accueil de l'administrateur, immédiatement après sa
connexion, était une page d'erreur.

Aucun test ne l'avait vu : `ViewCompilationTest` prouve que les 47 vues
**compilent**, ce qui n'est pas les **rendre**. `ScreensRenderTest` ouvre
désormais chaque écran, pour chaque rôle, sur une base qui contient de vraies
lignes — un tableau vide ne lève aucune des erreurs qu'on cherche.

### 3.2 Les tableaux larges n'étaient pas défilables au clavier

`.table-wrap` porte `overflow-x: auto` sans `tabindex` : une personne au
clavier ne pouvait pas faire défiler un tableau plus large que l'écran, donc
n'en atteignait pas la fin. Signalé « serious » par axe sur deux écrans, règle
WCAG 2.1.1.

Les **13** enveloppes portent maintenant `tabindex="0"`, `role="group"` et un
`aria-label` repris de la légende du tableau. `role="group"` plutôt que
`region` : nommer la zone sans ajouter treize points de repère à la liste d'un
lecteur d'écran. Un style de focus visible accompagne le changement.

### 3.3 Trois cibles tactiles trop petites

Mesurées, pas estimées :

| Élément | Avant | Après |
|---|---|---|
| « Mot de passe oublié ? » | 152 × **16** px | 44 px de haut |
| « Créer un compte citoyen » | 167 × **38** px | 44 px de haut |
| `<summary>` « Changer le statut » (×12) | 114 × **43** px | 44 px de haut |

Le premier échouait même le minimum de **24 px** de la WCAG 2.2 AA (SC 2.5.8) ;
les deux autres ne manquaient que la cible de 44 px que le projet s'est donnée.

---

## 4. Ce que l'audit a vérifié et trouvé correct

- **Lien d'évitement** — premier arrêt de tabulation de chaque page, et il
  déplace bien le focus sur `#contenu`.
- **Ordre de tabulation** — 33 arrêts sur la file de traitement, aucun élément
  invisible atteint, aucun piège au clavier.
- **Focus visible** — présent sur tous les éléments focalisables.
- **Langue du document** — déclarée sur `<html>`.
- **Contrastes** — mesurés au jalon 1 et inchangés depuis ; aucune violation
  `color-contrast` sur les 17 écrans.

### Une fausse alerte, notée pour mémoire

Ma première sonde a signalé un champ de date « sans contour de focus ». C'était
un artefact de la sonde : un `<input type="date"` consomme **trois** appuis sur
Tab (jour, mois, année), tous avec un contour visible ; au quatrième, le focus
quitte le champ, et `document.activeElement` le désigne encore une fraction de
seconde alors que `:focus-visible` est déjà faux. Rien à corriger. C'est écrit
ici plutôt que présenté comme une correction.

---

## 5. Non-régression

`tests/Feature/AccessibilityTest.php` empêche le retour de ce qui a été trouvé :
toute zone défilante atteignable au clavier et nommée, jeton de cible tactile à
44 px, langue déclarée, lien d'évitement pointant sur une cible existante.

`tests/Feature/ScreensRenderTest.php` ouvre chaque écran de chaque rôle.

Ces tests ne remplacent pas l'audit : **ils gardent ses résultats**. L'audit
lui-même est à refaire au navigateur à chaque jalon — la commande est dans
`docs/DECISIONS.md`, D-034.

---

## 6. Ce qui reste à faire

- **Essai avec un lecteur d'écran réel** (NVDA, VoiceOver, TalkBack). Non fait.
  C'est le seul moyen de vérifier ce qu'un outil automatique ne voit pas :
  l'ordre de lecture réel, la pertinence des annonces, la charge cognitive.
- **Essai avec des utilisateurs à faible littératie numérique**, qui est la
  cible du §8.1. Aucun outil ne le remplace.
