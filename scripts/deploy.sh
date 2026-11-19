#!/usr/bin/env bash
# Despliegue reproducible de Sneaker Factory. NO borra volumenes (los datos se conservan).
set -euo pipefail
cd "$(dirname "$0")/.."

echo "=================================================="
echo "  Despliegue - Sneaker Factory (DAW)"
echo "=================================================="

# 1. Prerrequisitos
for cmd in docker openssl curl; do
  command -v "$cmd" >/dev/null 2>&1 || { echo "[ERROR] Falta '$cmd'."; exit 1; }
done
docker compose version >/dev/null 2>&1 || { echo "[ERROR] Falta el plugin 'docker compose'."; exit 1; }
docker info >/dev/null 2>&1 || { echo "[ERROR] El demonio de Docker no responde (permisos o servicio parado)."; exit 1; }

# 2. .env desde la plantilla si no existe
if [ ! -f .env ]; then
  echo "[INFO] Creando .env desde .env.example..."
  cp .env.example .env
  sed -i 's/^DB_PASSWORD=.*/DB_PASSWORD=sneaker_pass/' .env   # credencial de laboratorio (no secreta)
fi

# 3. Carpetas de trabajo
mkdir -p nginx/certs logs/nginx backups

# 4. Puertos libres (solo si la pila aun no esta levantada)
if [ -z "$(docker compose ps -q 2>/dev/null)" ] && command -v ss >/dev/null 2>&1; then
  for p in 8081 8443; do
    if ss -ltn | grep -q ":$p "; then echo "[ERROR] El puerto $p ya esta en uso."; exit 1; fi
  done
fi

# 5. Certificado de prueba (con SAN). No se sube al repositorio.
if [ ! -f nginx/certs/server.crt ] || [ ! -f nginx/certs/server.key ]; then
  echo "[INFO] Generando certificado TLS de prueba..."
  MSYS_NO_PATHCONV=1 openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
    -keyout nginx/certs/server.key -out nginx/certs/server.crt \
    -subj "/CN=sneaker.local" \
    -addext "subjectAltName=DNS:sneaker.local,DNS:localhost,IP:127.0.0.1"
  chmod 600 nginx/certs/server.key || true
fi

# 6. Construir y levantar (sin 'down -v')
echo "[INFO] Construyendo y levantando servicios..."
docker compose up -d --build

# 7. Esperar a que los 3 servicios esten healthy (maximo ~2 min)
wait_healthy() {
  local n="$1" s
  for _ in $(seq 1 40); do
    s=$(docker inspect -f '{{if .State.Health}}{{.State.Health.Status}}{{else}}none{{end}}' "$n" 2>/dev/null || echo missing)
    [ "$s" = "healthy" ] && { echo "[OK] $n healthy"; return 0; }
    sleep 3
  done
  echo "[ERROR] $n no llego a healthy. Revisa: docker compose logs $n"; return 1
}
wait_healthy sneaker_db
wait_healthy sneaker_backend
wait_healthy sneaker_web

# 8. Verificacion
echo "[INFO] Estado:"
docker compose ps
echo "[INFO] Comprobaciones:"
./scripts/check.sh
echo "[INFO] Generando backup..."
./scripts/backup.sh

echo "=================================================="
echo " [EXITO] Despliegue completado."
echo " HTTPS:       https://localhost:8443  (o https://sneaker.local:8443 con entrada en hosts)"
echo " HTTP (301):  http://localhost:8081"
echo " Healthcheck: https://localhost:8443/health.php"
echo " Backups:     ./backups/   Logs Nginx: ./logs/nginx/"
echo "=================================================="
