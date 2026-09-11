#!/usr/bin/env bash
# Demarre MySQL/MariaDB pour le developpement local, de facon idempotente.
#
# Le conteneur de developpement n'a pas de gestionnaire de services : le
# serveur doit etre relance a la main apres chaque redemarrage. Ce script
# evite de retaper la ligne complete et, surtout, evite de se tromper de
# socket — une erreur qui se manifeste plus tard par un « Connection refused »
# difficile a rattacher a sa cause.
set -euo pipefail

DONNEES="${MYSQL_DATADIR:-/tmp/mysqldata}"
EXECUTION="${MYSQL_RUNDIR:-/tmp/mysqlrun}"
SOCKET="${EXECUTION}/mysql.sock"

if mysqladmin --socket="${SOCKET}" -u root ping >/dev/null 2>&1; then
  echo "Deja demarre : ${SOCKET}"
  exit 0
fi

mkdir -p /run/mysqld "${EXECUTION}" "${DONNEES}"
chown -R mysql:mysql /run/mysqld "${EXECUTION}" "${DONNEES}"

if [ ! -d "${DONNEES}/mysql" ]; then
  echo "Initialisation du repertoire de donnees…"
  mariadb-install-db --user=mysql --datadir="${DONNEES}" >/dev/null
fi

nohup mariadbd \
  --user=mysql \
  --datadir="${DONNEES}" \
  --socket="${SOCKET}" \
  --port=3306 \
  --bind-address=127.0.0.1 \
  --pid-file="${EXECUTION}/mysqld.pid" \
  > "${EXECUTION}/err.log" 2>&1 &

for _ in $(seq 1 30); do
  if mysqladmin --socket="${SOCKET}" -u root ping >/dev/null 2>&1; then
    echo "Demarre : ${SOCKET}"
    exit 0
  fi
  sleep 1
done

echo "Echec du demarrage ; voir ${EXECUTION}/err.log" >&2
exit 1
