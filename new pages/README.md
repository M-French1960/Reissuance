# PHOENIX — prototype front-end

Prototype HTML/CSS/JS de la plateforme de réédition des actes d'état civil.
Aucun serveur requis : ouvrez `index.html` dans un navigateur.

## Structure
- `assets/phoenix.css` : charte commune (tokens, verre dépoli, composants, responsive)
- `assets/phoenix.js` : barre latérale et barre supérieure par rôle, utilitaires, graphiques SVG, dialogues
- `assets/demo-data.js` : données d'exemple de l'espace demandeur
- Les 7 premières pages (accueil, inscription, connexion, formulaire, dashboards, vérification) sont autonomes ;
  les nouvelles s'appuient sur `assets/`.

## Pages
Public : index, track, verify, help, 404, session-expired, maintenance, terms, privacy, accessibility (provisoires)
Authentification : signup, login, forgot-password, officer-login
Demandeur : applicant-dashboard, requests, request-detail, request-complement, form, payments, notifications, profile
Officier : officer-dashboard, officer-verification, officer-reports, profile?role=officer
Maire : mayor-dashboard, mayor-sign, mayor-signed, profile?role=mayor
Administrateur : admin-dashboard, admin-agents, admin-centers, admin-payments, admin-audit, admin-settings

## Pour la démo
- Suivi public : PHX-2026-004218 + 4218
- Vérification d'acte : V7K2-94XA-QM3D (authentique), R2PL-00ZT-8KWE (révoqué)
- Connexion agent : identifiant commençant par « maire » → espace maire, « admin » → administration, sinon officier
- Mot de passe oublié : tout code à 6 chiffres sauf 000000
- Détail de demande : ?id=PHX-2026-004218 (complément), PHX-2026-003540 (en cours), PHX-2026-001107 (disponible), PHX-2026-000388 (rejetée)

## Avant la production
Chaque appel serveur est marqué `// TODO` avec la route Laravel suggérée.
Montants, délais, horaires, règles de remboursement et modèle officiel de la copie sont des valeurs d'exemple à confirmer.
La caméra et le scan de QR code exigent HTTPS (ou localhost).
