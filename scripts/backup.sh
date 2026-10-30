#!/usr/bin/env bash

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