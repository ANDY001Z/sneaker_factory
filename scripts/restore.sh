#!/bin/bash

# 1. Validar que se pasa el archivo como argumento
if [ -z "$1" ]; then
  echo "Uso: ./scripts/restore.sh <ruta_al_archivo_backup.sql>"
  exit 1
fi

BACKUP_FILE=$1

if [ ! -f "$BACKUP_FILE" ]; then
  echo "❌ El archivo $BACKUP_FILE no existe."
  exit 1
fi

# 2. Cargar variables del .env si existe
if [ -f .env ]; then
  export $(cat .env | grep -v '^#' | xargs)
fi

echo "Restaurando la base de datos desde $BACKUP_FILE..."

# 3. Inyectar el SQL de vuelta al contenedor de PostgreSQL
cat $BACKUP_FILE | docker exec -i sneaker_db psql -U ${DB_USER:-postgres} -d ${DB_NAME:-sneaker_db}

if [ $? -eq 0 ]; then
  echo "✅ Base de datos restaurada correctamente."
else
  echo "❌ Error durante la restauración."
fi