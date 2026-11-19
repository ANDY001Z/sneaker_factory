#!/usr/bin/env bash
# Uso: ./scripts/restore.sh <backup.sql> [base_destino]
# Por defecto restaura en una BD AISLADA (sneaker_restore_test), sin tocar produccion.
set -euo pipefail
cd "$(dirname "$0")/.."
[ $# -ge 1 ] || { echo "Uso: $0 <backup.sql> [base_destino]"; exit 1; }
FILE="$1"
[ -f "$FILE" ] || { echo "No existe $FILE"; exit 1; }
set -a; . ./.env; set +a
TARGET="${2:-sneaker_restore_test}"

psqlx() { docker exec -i -e PGPASSWORD="$DB_PASSWORD" sneaker_db psql -U "$DB_USER" "$@"; }

if [ "$TARGET" = "$DB_NAME" ]; then
  echo "ATENCION: vas a SOBRESCRIBIR la base de PRODUCCION ($DB_NAME)."
  read -r -p "Escribe RESTAURAR para continuar: " ans
  [ "$ans" = "RESTAURAR" ] || { echo "Cancelado."; exit 1; }
  psqlx -d "$TARGET" -v ON_ERROR_STOP=1 -c "DROP SCHEMA public CASCADE; CREATE SCHEMA public;"
else
  echo "[INFO] Preparando base aislada '$TARGET'..."
  psqlx -d "$DB_NAME" -c "DROP DATABASE IF EXISTS \"$TARGET\";"
  psqlx -d "$DB_NAME" -c "CREATE DATABASE \"$TARGET\";"
fi

echo "[INFO] Restaurando $FILE en '$TARGET'..."
psqlx -d "$TARGET" -v ON_ERROR_STOP=1 < "$FILE" >/dev/null
echo "[OK] Restauracion completada en '$TARGET'."
echo "Comprueba: docker exec -e PGPASSWORD=\$DB_PASSWORD sneaker_db psql -U $DB_USER -d $TARGET -c 'SELECT * FROM orders;'"
