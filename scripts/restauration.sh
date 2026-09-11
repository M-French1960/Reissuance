#!/usr/bin/env bash
#
# Restauration de PHOENIX depuis une archive produite par sauvegarde.sh.
#
# « Une sauvegarde jamais restaurée n'est pas une sauvegarde. » Ce script est
# fait pour être exécuté pour de bon, périodiquement, dans une base d'essai.
#
# Il refuse d'écraser la base de production sans y être poussé : passez le nom
# de la base cible explicitement.
#
# Usage : scripts/restauration.sh ARCHIVE BASE_CIBLE [--avec-stockage]
#
#   --avec-stockage  restaure aussi storage/app/private. À NE PAS utiliser sur
#                    une machine en service : cela écrase les fichiers actuels.

set -euo pipefail

ARCHIVE="${1:?Usage : restauration.sh ARCHIVE BASE_CIBLE [--avec-stockage]}"
CIBLE="${2:?Nommez la base cible explicitement.}"
AVEC_STOCKAGE="${3:-}"

RACINE="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TRAVAIL="$(mktemp -d)"
trap 'rm -rf "${TRAVAIL}"' EXIT

cd "${RACINE}"

lire_env() { sed -n "s/^${1}=//p" .env | head -1 | sed 's/^"//;s/"$//'; }

HOTE="$(lire_env DB_HOST)"
PORT="$(lire_env DB_PORT)"
SOCKET="$(lire_env DB_SOCKET)"
PROPRIETAIRE="$(lire_env DB_OWNER_USERNAME)"
MOT_DE_PASSE="$(lire_env DB_OWNER_PASSWORD)"
BASE_EN_SERVICE="$(lire_env DB_DATABASE)"

if [[ "${CIBLE}" == "${BASE_EN_SERVICE}" && "${PHOENIX_RESTAURATION_FORCEE:-}" != "oui" ]]; then
  echo "Refus : ${CIBLE} est la base en service." >&2
  echo "Pour une restauration réelle après incident :" >&2
  echo "  PHOENIX_RESTAURATION_FORCEE=oui scripts/restauration.sh ..." >&2
  exit 1
fi

echo "→ Ouverture de l'archive"
tar -xzf "${ARCHIVE}" -C "${TRAVAIL}"

echo "→ Inventaire attendu"
sed 's/^/    /' "${TRAVAIL}/inventaire.txt"

# Le mot de passe passe par un fichier en 0600, jamais par la ligne de
# commande : `ps` est lisible par tout le monde.
IDENTIFIANTS="${TRAVAIL}/client.cnf"
touch "${IDENTIFIANTS}"
chmod 600 "${IDENTIFIANTS}"
cat > "${IDENTIFIANTS}" <<CNF
[client]
host=${HOTE}
port=${PORT}
user=${PROPRIETAIRE}
password=${MOT_DE_PASSE}
CNF
if [[ -n "${SOCKET}" ]]; then
  echo "socket=${SOCKET}" >> "${IDENTIFIANTS}"
fi

# Creer une base est un acte d'administration, pas le travail de ce script :
# le compte proprietaire de PHOENIX n'a deliberement aucun droit hors de la
# base du projet. On tente, et si le droit manque on dit exactement quoi
# executer.
echo "→ Base ${CIBLE}"
if mysql --defaults-extra-file="${IDENTIFIANTS}" --database="${CIBLE}" \
     --execute='SELECT 1' >/dev/null 2>&1; then
  echo "  existe déjà — son contenu sera remplacé"
else
  if ! mysql --defaults-extra-file="${IDENTIFIANTS}" --execute="
         CREATE DATABASE \`${CIBLE}\`
         CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci" >/dev/null 2>&1; then
    echo >&2
    echo "Erreur : la base ${CIBLE} n'existe pas et ${PROPRIETAIRE} ne peut pas la créer." >&2
    echo "Faites-la créer par un administrateur du serveur :" >&2
    echo "  CREATE DATABASE \`${CIBLE}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" >&2
    echo "  GRANT ALL PRIVILEGES ON \`${CIBLE}\`.* TO '${PROPRIETAIRE}'@'%' WITH GRANT OPTION;" >&2
    exit 1
  fi
  echo "  créée"
fi

echo "→ Restauration de la base"
# L'interclassement compte : la recherche par nom est insensible a la casse
# PARCE QUE les colonnes sont en utf8mb4_unicode_ci. Une base d'accueil creee
# avec un autre interclassement rendrait la recherche silencieusement
# incomplete (D-051).
mysql --defaults-extra-file="${IDENTIFIANTS}" --database="${CIBLE}" \
  < "${TRAVAIL}/base.sql"

if [[ "${AVEC_STOCKAGE}" == "--avec-stockage" ]]; then
  echo "→ Restauration du stockage privé (écrase l'existant)"
  rm -rf storage/app/private
  tar -xzf "${TRAVAIL}/stockage-prive.tar.gz" -C storage/app
else
  echo "→ Stockage privé NON restauré (ajoutez --avec-stockage)"
  echo "  Contenu de l'archive :"
  tar -tzf "${TRAVAIL}/stockage-prive.tar.gz" | head -20 | sed 's/^/    /'
fi

# Les droits du compte applicatif NE SONT PAS dans la sauvegarde : MySQL les
# range dans la base systeme `mysql`, pas dans celle du projet. Sans cette
# etape, la base restauree a ses tables et ses declencheurs mais l'application
# ne peut rien y lire — ou, si quelqu'un « repare » en accordant les droits sur
# la base entiere, le journal d'audit y redevient modifiable (D-051).
echo "→ Droits du compte applicatif"
php artisan phoenix:droits --database="${CIBLE}" | sed 's/^/    /'

echo "→ Inventaire obtenu"
mysql --defaults-extra-file="${IDENTIFIANTS}" --database="${CIBLE}" \
  --skip-column-names --batch \
  --execute="SELECT CONCAT('demandes=', count(*)) FROM reissuance_requests
             UNION ALL SELECT CONCAT('comptes=', count(*)) FROM users
             UNION ALL SELECT CONCAT('journal=', count(*)) FROM audit_logs
             UNION ALL SELECT CONCAT('signatures=', count(*)) FROM document_signatures
             UNION ALL SELECT CONCAT('pieces=', count(*)) FROM request_attachments" \
  | sed 's/^/    /'

echo
echo "Les clés de l'archive sont dans ${TRAVAIL}/cles.env — répertoire effacé à la sortie."
echo "Sans APP_KEY, les numéros de pièce restaurés sont illisibles."
echo "Sans PHOENIX_BLIND_INDEX_KEY, la recherche par numéro ne fonctionne pas."
echo
echo "Vérification obligatoire après restauration :"
echo "  php artisan phoenix:verifier-restauration --database=<connexion>"
