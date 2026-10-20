#!/usr/bin/env bash
# Regenera el manual de TuPlanner: base de ejemplo nueva (docs/manual/generar/datos.php), capturas reales y el PDF.
#   bash docs/manual/generar/generar.sh
# Variables opcionales: PUERTO (8240), DB (tuplanner_manual_prueba), DB_USER (tuplanner), DB_PASS (prueba),
#                       SOLO_PDF=1 (no toma capturas, solo vuelve a armar el PDF con las que ya hay).
# Necesita: PostgreSQL, PHP, Python 3 con playwright y pypdf, y Chromium de Playwright (PLAYWRIGHT_BROWSERS_PATH).
set -euo pipefail
AQUI=$(cd "$(dirname "$0")" && pwd)
MANUAL=$(cd "$AQUI/.." && pwd)
RAIZ=$(cd "$MANUAL/../.." && pwd)
PUERTO=${PUERTO:-8240}
export TUPLANNER_DB_NAME=${DB:-tuplanner_manual_prueba} TUPLANNER_DB_USER=${DB_USER:-tuplanner} TUPLANNER_DB_PASS=${DB_PASS:-prueba}
export TUPLANNER_URL="http://127.0.0.1:$PUERTO" TZ=America/Guayaquil PGTZ=America/Guayaquil
export PLAYWRIGHT_BROWSERS_PATH=${PLAYWRIGHT_BROWSERS_PATH:-/opt/pw-browsers}
cd "$RAIZ"
if [ "${SOLO_PDF:-0}" != "1" ]; then
  case "$TUPLANNER_DB_NAME" in *prueba*|*manual*) ;; *) echo "Por seguridad la base debe llamarse *manual* o *prueba*"; exit 1;; esac
  TMPD=$(mktemp -d)
  export TUPLANNER_UPLOADS="$TMPD/uploads" TUPLANNER_BORRADOS="$TMPD/borrados"
  echo "== 1. Base de ejemplo nueva ($TUPLANNER_DB_NAME)"
  export PGPASSWORD=$TUPLANNER_DB_PASS
  psql -q -h 127.0.0.1 -U "$TUPLANNER_DB_USER" -d postgres -c "DROP DATABASE IF EXISTS $TUPLANNER_DB_NAME" -c "CREATE DATABASE $TUPLANNER_DB_NAME"
  psql -q -h 127.0.0.1 -U "$TUPLANNER_DB_USER" -d "$TUPLANNER_DB_NAME" -v ON_ERROR_STOP=1 -f db/schema.sql > /dev/null 2>&1
  for m in db/migraciones/*.sql; do
    psql -q -h 127.0.0.1 -U "$TUPLANNER_DB_USER" -d "$TUPLANNER_DB_NAME" -v ON_ERROR_STOP=1 -f "$m" > /dev/null 2>&1 || { echo "ERROR en $m"; exit 1; }
  done
  php "$AQUI/datos.php" > "$TMPD/ids.json"
  echo "== 2. Programa en http://127.0.0.1:$PUERTO"
  php -S "127.0.0.1:$PUERTO" -t public tests/router.php > "$TMPD/servidor.log" 2>&1 &
  SERVIDOR=$!
  trap 'kill $SERVIDOR 2>/dev/null || true; rm -rf "$TMPD"' EXIT
  for _ in $(seq 1 30); do curl -s -o /dev/null "http://127.0.0.1:$PUERTO/?r=login" && break; sleep 0.3; done
  echo "== 3. Capturas"
  rm -f "$MANUAL"/capturas/*.png
  python3 "$AQUI/capturas.py" --base "http://127.0.0.1:$PUERTO" --salida "$MANUAL/capturas" --ids "$TMPD/ids.json"
  if grep -qi "PHP Fatal\|PHP Warning\|Deprecated\|Notice" "$TMPD/servidor.log"; then echo "Errores de PHP durante las capturas:"; grep -i "PHP Fatal\|PHP Warning\|Deprecated\|Notice" "$TMPD/servidor.log" | cut -c1-300; fi
fi
echo "== 4. PDF del manual"
python3 "$AQUI/pdf.py" "$MANUAL/manual.html" "$RAIZ/docs/Manual_TuPlanner.pdf" "Manual de TuPlanner"
pdfinfo "$RAIZ/docs/Manual_TuPlanner.pdf" 2>/dev/null | grep -E "Pages" || true
