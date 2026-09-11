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
SOCKET="$(lire_env DB_SOCKET)"
PROPRIETAIRE="$(lire_env DB_OWNER_USERNAME)"
MOT_DE_PASSE="$(lire_env DB_OWNER_PASSWORD)"

if [[ -z "${BASE}" || -z "${PROPRIETAIRE}" ]]; then
  echo "Erreur : DB_DATABASE ou DB_OWNER_USERNAME est absent de .env." >&2
  exit 1
fi

# Le mot de passe n'est JAMAIS passé en argument : la ligne de commande d'un
# processus est lisible par tout le monde dans `ps`. Un fichier temporaire en
# 0600, effacé par le trap, est le mécanisme prévu par MySQL pour cela.
IDENTIFIANTS="${TRAVAIL}/client.cnf"
# Les droits AVANT l'écriture : entre un `cat >` et un `chmod` il existe un
# instant où le mot de passe est lisible par tous.
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

echo "→ Base de données (${BASE})"
# --single-transaction : instantané cohérent sans verrouiller les écritures.
# --routines --events : sans elles, la sauvegarde laisserait derrière elle les
#   objets qui ne sont pas des tables.
# --triggers est actif par défaut : les déclencheurs de machine à états, qui
#   sont la barrière anti-fraude, partent donc avec le reste.
#
# CE QUI N'EST PAS DANS CE FICHIER : les droits du compte applicatif. Sur
# MySQL ils vivent dans la base système `mysql`, pas dans la base du projet —
# contrairement à PostgreSQL, où pg_dump les emportait. Une base restaurée
# depuis ce fichier a donc ses tables et ses déclencheurs, mais AUCUN droit
# pour phoenix_app : le journal d'audit y serait modifiable par le compte
# applicatif s'il recevait des droits trop larges. La restauration relance
# `php artisan phoenix:droits`, et un contrôle le vérifie (D-051).
mysqldump --defaults-extra-file="${IDENTIFIANTS}" \
  --single-transaction --routines --events \
  --add-drop-table --add-drop-trigger \
  --result-file="${TRAVAIL}/base.sql" "${BASE}"

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
mysql --defaults-extra-file="${IDENTIFIANTS}" --database="${BASE}" \
  --skip-column-names --batch \
  --execute="SELECT CONCAT('demandes=', count(*)) FROM reissuance_requests
             UNION ALL SELECT CONCAT('comptes=', count(*)) FROM users
             UNION ALL SELECT CONCAT('journal=', count(*)) FROM audit_logs
             UNION ALL SELECT CONCAT('signatures=', count(*)) FROM document_signatures
             UNION ALL SELECT CONCAT('pieces=', count(*)) FROM request_attachments" \
  > "${TRAVAIL}/inventaire.txt"
echo "fichiers_stockage=$(find storage/app/private -type f 2>/dev/null | wc -l)" \
  >> "${TRAVAIL}/inventaire.txt"

mkdir -p "${DESTINATION}"
ARCHIVE="${DESTINATION}/phoenix-${HORODATAGE}.tar.gz"
tar -czf "${ARCHIVE}" -C "${TRAVAIL}" base.sql stockage-prive.tar.gz cles.env inventaire.txt
chmod 600 "${ARCHIVE}"

echo
echo "Sauvegarde écrite : ${ARCHIVE}"
echo "Taille : $(du -h "${ARCHIVE}" | cut -f1)"
cat "${TRAVAIL}/inventaire.txt" | sed 's/^/  /'
echo
echo "Cette archive contient les clés de déchiffrement."
echo "Conservez-la chiffrée et hors de cette machine."
