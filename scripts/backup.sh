#!/usr/bin/env bash
# Backup consistente (pg_dump) + configuracion. Registra fecha, resultado y ruta. Retencion: 5.
set -euo pipefail
cd "$(dirname "$0")/.."          # funciona tambien desde cron
set -a; . ./.env; set +a

DIR=backups
TS=$(date +%Y%m%d_%H%M%S)
OUT="$DIR/sneaker_db_$TS.sql"
CFG="$DIR/sneaker_config_$TS.tar.gz"
LOG="$DIR/backup.log"
MAX=5
mkdir -p "$DIR"

if docker exec -e PGPASSWORD="$DB_PASSWORD" sneaker_db pg_dump -U "$DB_USER" -d "$DB_NAME" --no-owner > "$OUT" && [ -s "$OUT" ]; then
  tar czf "$CFG" compose.yml .env.example nginx/default.conf backend/Dockerfile scripts data
  echo "$(date -Is) OK    $OUT ($(du -h "$OUT" | cut -f1)) + $CFG" | tee -a "$LOG"
else
  rm -f "$OUT"
  echo "$(date -Is) FALLO pg_dump" | tee -a "$LOG"
  exit 1
fi

# Retencion: ultimas $MAX copias
ls -1t "$DIR"/sneaker_db_*.sql        2>/dev/null | tail -n +$((MAX+1)) | xargs -r rm -f
ls -1t "$DIR"/sneaker_config_*.tar.gz 2>/dev/null | tail -n +$((MAX+1)) | xargs -r rm -f