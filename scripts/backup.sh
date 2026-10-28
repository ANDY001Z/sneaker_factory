#!/bin/bash

# 1. Crear directorio de backups si no existe
BACKUP_DIR="./backups"
mkdir -p $BACKUP_DIR

# 2. Generar nombre de archivo con marca de tiempo
TIMESTAMP=$(date +"%Y-%m-%d_%H-%M-%S")
BACKUP_FILE="$BACKUP_DIR/sneaker_db_$TIMESTAMP.sql"

# 3. Cargar variables del .env si existe
if [ -f .env ]; then
  export $(cat .env | grep -v '^#' | xargs)
fi

echo "Generando copia de seguridad de PostgreSQL..."

# 4. Ejecutar pg_dump dentro del contenedor de Docker
docker exec -t sneaker_db pg_dump -U ${DB_USER:-postgres} -d ${DB_NAME:-sneaker_db} > $BACKUP_FILE

if [ $? -eq 0 ]; then
  echo "✅ Backup completado con éxito en: $BACKUP_FILE"
else
  echo "❌ Error al generar la copia de seguridad."
fi