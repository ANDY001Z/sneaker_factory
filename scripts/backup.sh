#!/usr/bin/env bash
set -e

# Cargar variables del .env si existe
if [ -f .env ]; then
  export $(grep -v '^#' .env | xargs)
fi

BACKUP_DIR="./backups"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
BACKUP_FILE="${BACKUP_DIR}/sneaker_db_${TIMESTAMP}.sql"

mkdir -p "$BACKUP_DIR"

docker exec -e PGPASSWORD="${DB_PASSWORD}" -t sneaker_db \
  pg_dump -U "${DB_USER}" -d "${DB_NAME}" > "$BACKUP_FILE"

echo "Backup guardado en: $BACKUP_FILE"

# Mantener únicamente las últimas 5 copias
MAX_RETENTION=5
BACKUP_COUNT=$(ls -1q ./backups/sneaker_db_*.sql 2>/dev/null | wc -l)
if [ "$BACKUP_COUNT" -gt "$MAX_RETENTION" ]; then
    ls -1t ./backups/sneaker_db_*.sql | tail -n +$((MAX_RETENTION + 1)) | xargs rm -f
    echo "Copias antiguas purgadas. Conservando las últimas $MAX_RETENTION."
fi