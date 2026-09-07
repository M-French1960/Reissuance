# Sauvegarde et restauration

> Jalon 6. La procédure décrite ici a été **exécutée**, y compris la
> restauration. Les modes de défaillance listés au §4 ont été **provoqués** pour
> vérifier qu'ils sont détectés.

---

## 1. Ce qu'il faut sauvegarder — les trois, ensemble

| Élément | Perte si absent |
|---|---|
| Base PostgreSQL | tout |
| `storage/app/private/` | **toutes les pièces d'identité et tous les actes signés** |
| `APP_KEY` | les numéros de pièce deviennent illisibles |
| `PHOENIX_BLIND_INDEX_KEY` | la recherche par numéro ne trouve plus rien |

Ces éléments sont **indissociables** :

- Base **sans** stockage → un système cohérent en apparence dont chaque
  téléchargement d'acte échoue.
- Base **et** stockage **sans** les clés → des numéros de pièce chiffrés que
  personne ne peut lire.
- **Sans la clé d'index**, la recherche par numéro ne lève aucune erreur : elle
  rend zéro résultat. Un officier en conclurait que la pièce est inconnue.
  C'est le mode de défaillance le plus dangereux, parce qu'il est silencieux.

---

## 2. Sauvegarder

```
scripts/sauvegarde.sh [répertoire-de-destination]
```

Produit une archive unique contenant le vidage de la base (format personnalisé),
le stockage privé, les deux clés et un **inventaire** — le nombre de lignes par
table au moment de la sauvegarde, pour comparer à la restauration.

> **L'archive contient les clés de déchiffrement.** Elle vaut les données
> elles-mêmes : conservée chiffrée, hors de la machine, avec les mêmes
> précautions d'accès.

---

## 3. Restaurer

```
scripts/restauration.sh ARCHIVE BASE_CIBLE [--avec-stockage]
```

Le script **refuse** d'écraser la base en service sans `PHOENIX_RESTAURATION_FORCEE=oui` :
un essai de restauration ne doit pas pouvoir détruire la production par
inadvertance.

Il ne crée pas la base si le rôle propriétaire n'en a pas le droit — c'est
volontaire, `phoenix_owner` n'a pas `CREATEDB`. Le script indique alors la
commande exacte à faire exécuter par un administrateur.

`--avec-stockage` restaure aussi `storage/app/private`, **en écrasant
l'existant**. À réserver à une vraie reprise après incident.

---

## 4. Vérifier — compter des lignes ne prouve rien

```
php artisan phoenix:verifier-restauration --db=phoenix_essai
```

Cinq contrôles, dont quatre vont au-delà du dénombrement :

| Contrôle | Ce qu'il prouve |
|---|---|
| Lignes présentes | la base n'est pas vide |
| Un numéro de pièce se déchiffre | `APP_KEY` est la bonne |
| La recherche par numéro trouve | `PHOENIX_BLIND_INDEX_KEY` est la bonne |
| Les actes existent et correspondent à leur empreinte | le stockage a bien été restauré, et intact |
| Le déclencheur d'état est présent | la machine à états est encore protégée en base |

### Résultat de l'exécution réelle

Restauration de 515 demandes, 513 comptes, 1 715 entrées de journal, 1 acte
signé et 1 pièce jointe dans une base d'essai : **inventaire identique**, et les
cinq contrôles au vert.

### Les défaillances ont été provoquées, pas supposées

| Défaillance provoquée | Détectée |
|---|---|
| Mauvaise `APP_KEY` | oui — `The MAC is invalid` |
| Mauvaise clé d'index aveugle | oui — l'empreinte recalculée ne correspond pas |
| Stockage privé non restauré | oui — « 1 acte désigne un fichier absent » |
| Fichier d'acte altéré d'un octet | oui — ne correspond plus à son empreinte |

Le troisième cas est celui que produit une sauvegarde de la base seule. C'est
précisément celui qu'on ne voit pas sans ce contrôle.

---

## 5. Ce qui reste à décider

- **La périodicité.** Aucune n'est fixée : elle dépend de la perte de données
  acceptable, qui est une décision de service, pas d'ingénierie.
- **Où les archives sont conservées**, et pour combien de temps. Elles
  contiennent des données d'identité et les clés : leur conservation relève des
  mêmes règles que la base (voir `COMPLIANCE_OPEN_QUESTIONS.md`, bloc B).
- **Qui exécute l'essai de restauration, et à quelle fréquence.** Une sauvegarde
  jamais restaurée n'est pas une sauvegarde ; une restauration essayée une seule
  fois, au jalon 6, ne le reste pas longtemps.
