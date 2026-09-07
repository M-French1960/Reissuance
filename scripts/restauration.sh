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

export PGPASSWORD="${MOT_DE_PASSE}"

# Creer une base est un acte d'administration, pas le travail de ce script :
# le role proprietaire de PHOENIX n'a deliberement pas le droit CREATEDB. On
# tente, et si le droit manque on dit exactement quoi executer.
echo "→ Base ${CIBLE}"
if psql --host="${HOTE}" --port="${PORT}" --username="${PROPRIETAIRE}" \
     --dbname="${CIBLE}" --command='SELECT 1' >/dev/null 2>&1; then
  echo "  existe déjà — son contenu sera remplacé"
else
  if ! psql --host="${HOTE}" --port="${PORT}" --username="${PROPRIETAIRE}" --dbname=postgres \
       --command="CREATE DATABASE \"${CIBLE}\" OWNER \"${PROPRIETAIRE}\"" >/dev/null 2>&1; then
    echo >&2
    echo "Erreur : la base ${CIBLE} n'existe pas et ${PROPRIETAIRE} ne peut pas la créer." >&2
    echo "Faites-la créer par un administrateur de la grappe :" >&2
    echo "  CREATE DATABASE \"${CIBLE}\" OWNER \"${PROPRIETAIRE}\";" >&2
    exit 1
  fi
  echo "  créée"
fi

echo "→ Restauration de la base"
# --exit-on-error : une restauration partielle silencieuse est pire que rien.
pg_restore --host="${HOTE}" --port="${PORT}" --username="${PROPRIETAIRE}" \
  --dbname="${CIBLE}" --no-owner --exit-on-error "${TRAVAIL}/base.dump"

if [[ "${AVEC_STOCKAGE}" == "--avec-stockage" ]]; then
  echo "→ Restauration du stockage privé (écrase l'existant)"
  rm -rf storage/app/private
  tar -xzf "${TRAVAIL}/stockage-prive.tar.gz" -C storage/app
else
  echo "→ Stockage privé NON restauré (ajoutez --avec-stockage)"
  echo "  Contenu de l'archive :"
  tar -tzf "${TRAVAIL}/stockage-prive.tar.gz" | head -20 | sed 's/^/    /'
fi

echo "→ Inventaire obtenu"
psql --host="${HOTE}" --port="${PORT}" --username="${PROPRIETAIRE}" --dbname="${CIBLE}" \
  --tuples-only --no-align \
  --command="SELECT 'demandes=' || count(*) FROM reissuance_requests
             UNION ALL SELECT 'comptes=' || count(*) FROM users
             UNION ALL SELECT 'journal=' || count(*) FROM audit_logs
             UNION ALL SELECT 'signatures=' || count(*) FROM document_signatures
             UNION ALL SELECT 'pieces=' || count(*) FROM request_attachments" \
  | sed 's/^/    /'

echo
echo "Les clés de l'archive sont dans ${TRAVAIL}/cles.env — répertoire effacé à la sortie."
echo "Sans APP_KEY, les numéros de pièce restaurés sont illisibles."
echo "Sans PHOENIX_BLIND_INDEX_KEY, la recherche par numéro ne fonctionne pas."
echo
echo "Vérification obligatoire après restauration :"
echo "  php artisan phoenix:verifier-restauration --database=<connexion>"
