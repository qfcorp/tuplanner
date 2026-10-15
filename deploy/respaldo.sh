#!/usr/bin/env bash
# Respaldo diario de TuPlanner: base de datos + archivos subidos (logos, fotos, comprobantes, firmas). Guarda 14 dias.
# Cron (todos los dias a las 3:25 de la manana):
#   25 3 * * * APP=/var/www/tuplanner /var/www/tuplanner/deploy/respaldo.sh >> /var/log/tuplanner-respaldo.log 2>&1
set -euo pipefail
umask 077   # solo el dueno del servidor puede leer los respaldos

APP="${APP:-/var/www/tuplanner}"
DESTINO="${DESTINO:-/var/backups/tuplanner}"
DIAS="${DIAS:-14}"

if [ ! -f "$APP/config/.env" ]; then
  echo "$(date '+%F %T') ERROR: no existe $APP/config/.env"
  exit 1
fi
# Lee los datos de conexion de config/.env
set -a
# shellcheck disable=SC1090
. <(grep -E '^TUPLANNER_(DB_HOST|DB_NAME|DB_USER|DB_PASS|UPLOADS)=' "$APP/config/.env")
set +a
export PGPASSWORD="${TUPLANNER_DB_PASS:-}"
UPLOADS="${TUPLANNER_UPLOADS:-$APP/uploads}"

FECHA=$(date +%Y-%m-%d_%H%M)
mkdir -p "$DESTINO"
chmod 700 "$DESTINO"

pg_dump -h "${TUPLANNER_DB_HOST:-127.0.0.1}" -U "${TUPLANNER_DB_USER:-tuplanner}" -Fc "${TUPLANNER_DB_NAME:-tuplanner}" > "$DESTINO/base_$FECHA.dump"
if [ -d "$UPLOADS" ]; then
  tar -czf "$DESTINO/archivos_$FECHA.tar.gz" -C "$(dirname "$UPLOADS")" "$(basename "$UPLOADS")"
fi

# Verifica que el respaldo se pueda leer
pg_restore --list "$DESTINO/base_$FECHA.dump" > /dev/null

# Borra respaldos de mas de $DIAS dias
find "$DESTINO" -name 'base_*.dump' -mtime +"$DIAS" -delete
find "$DESTINO" -name 'archivos_*.tar.gz' -mtime +"$DIAS" -delete

echo "$(date '+%F %T') respaldo OK: base $(du -h "$DESTINO/base_$FECHA.dump" | cut -f1)$([ -f "$DESTINO/archivos_$FECHA.tar.gz" ] && echo ", archivos $(du -h "$DESTINO/archivos_$FECHA.tar.gz" | cut -f1)")"
