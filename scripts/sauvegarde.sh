#!/usr/bin/env bash
#
# Sauvegarde complète de PHOENIX.
#
# Trois éléments, indissociables :
#   1. la base de données ;
#   2. le stockage privé — pièces d'identité et actes signés ;
#   3. les deux clés — APP_KEY et PHOENIX_BLIND_INDEX_KEY.
#
# Une sauvegarde de la base SANS le stockage produit un système cohérent en
# apparence dont toutes les pièces d'identité manquent. Une sauvegarde des deux
# SANS les clés produit une base dont les numéros de pièce sont illisibles et
# dont la recherche par numéro ne fonctionne plus.
#
# Ce script écrit les clés dans l'archive. L'archive contient donc de quoi
# déchiffrer les données : elle se conserve chiffrée, hors de la machine, et
# avec les mêmes précautions que les données elles-mêmes.
#
# Usage : scripts/sauvegarde.sh [repertoire-de-destination]

set -euo pipefail

RACINE="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DESTINATION="${1:-${RACINE}/sauvegardes}"
HORODATAGE="$(date -u +%Y%m%dT%H%M%SZ)"
TRAVAIL="$(mktemp -d)"
trap 'rm -rf "${TRAVAIL}"' EXIT

cd "${RACINE}"

lire_env() {
  # Lit une variable de .env sans charger tout le fichier dans l'environnement.
  sed -n "s/^${1}=//p" .env | head -1 | sed 's/^"//;s/"$//'
}

BASE="$(lire_env DB_DATABASE)"
HOTE="$(lire_env DB_HOST)"
PORT="$(lire_env DB_PORT)"
PROPRIETAIRE="$(lire_env DB_OWNER_USERNAME)"
MOT_DE_PASSE="$(lire_env DB_OWNER_PASSWORD)"

if [[ -z "${BASE}" || -z "${PROPRIETAIRE}" ]]; then
  echo "Erreur : DB_DATABASE ou DB_OWNER_USERNAME est absent de .env." >&2
  exit 1
fi

echo "→ Base de données (${BASE})"
# --clean --if-exists : la restauration écrase proprement une base existante.
# Le format personnalisé permet une restauration parallèle et sélective.
PGPASSWORD="${MOT_DE_PASSE}" pg_dump \
  --host="${HOTE}" --port="${PORT}" --username="${PROPRIETAIRE}" \
  --format=custom --clean --if-exists \
  --file="${TRAVAIL}/base.dump" "${BASE}"

echo "→ Stockage privé"
if [[ -d storage/app/private ]]; then
  tar -czf "${TRAVAIL}/stockage-prive.tar.gz" -C storage/app private
else
  echo "  (aucun stockage privé — répertoire absent)"
  tar -czf "${TRAVAIL}/stockage-prive.tar.gz" -T /dev/null
fi

echo "→ Clés"
{
  echo "# Clés de PHOENIX — sauvegarde du ${HORODATAGE}"
  echo "# La perte d'APP_KEY rend illisibles les numéros de pièce chiffrés."
  echo "# La perte de la clé d'index rend impossible la recherche par numéro."
  echo "APP_KEY=$(lire_env APP_KEY)"
  echo "PHOENIX_BLIND_INDEX_KEY=$(lire_env PHOENIX_BLIND_INDEX_KEY)"
} > "${TRAVAIL}/cles.env"
chmod 600 "${TRAVAIL}/cles.env"

# Un inventaire pour vérifier, à la restauration, qu'on a bien tout remis.
echo "→ Inventaire"
PGPASSWORD="${MOT_DE_PASSE}" psql --host="${HOTE}" --port="${PORT}" \
  --username="${PROPRIETAIRE}" --dbname="${BASE}" --tuples-only --no-align \
  --command="SELECT 'demandes=' || count(*) FROM reissuance_requests
             UNION ALL SELECT 'comptes=' || count(*) FROM users
             UNION ALL SELECT 'journal=' || count(*) FROM audit_logs
             UNION ALL SELECT 'signatures=' || count(*) FROM document_signatures
             UNION ALL SELECT 'pieces=' || count(*) FROM request_attachments" \
  > "${TRAVAIL}/inventaire.txt"
echo "fichiers_stockage=$(find storage/app/private -type f 2>/dev/null | wc -l)" \
  >> "${TRAVAIL}/inventaire.txt"

mkdir -p "${DESTINATION}"
ARCHIVE="${DESTINATION}/phoenix-${HORODATAGE}.tar.gz"
tar -czf "${ARCHIVE}" -C "${TRAVAIL}" base.dump stockage-prive.tar.gz cles.env inventaire.txt
chmod 600 "${ARCHIVE}"

echo
echo "Sauvegarde écrite : ${ARCHIVE}"
echo "Taille : $(du -h "${ARCHIVE}" | cut -f1)"
cat "${TRAVAIL}/inventaire.txt" | sed 's/^/  /'
echo
echo "Cette archive contient les clés de déchiffrement."
echo "Conservez-la chiffrée et hors de cette machine."
