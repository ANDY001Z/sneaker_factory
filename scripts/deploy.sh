#!/bin/bash
set -e

echo "=================================================="
echo "  Despliegue Automático - Sneaker Factory (DAW)   "
echo "=================================================="

# 1. Comprobar que Docker está instalado
if ! command -v docker &> /dev/null; then
    echo "[ERROR] Docker no está instalado en este sistema."
    exit 1
fi

# 2. Generar el archivo .env a partir de la plantilla si no existe
if [ ! -f .env ]; then
    echo "[INFO] No se encontró el archivo .env real."
    echo "[INFO] Generando .env automáticamente desde .env.example..."
    cp .env.example .env
    # Reemplaza cualquier contraseña por defecto en DB_PASSWORD por la contraseña de laboratorio
    sed -i 's/DB_PASSWORD=.*/DB_PASSWORD=sneaker_pass/' .env
fi

# 3. Generar certificados SSL/TLS autofirmados; no se suben al repositorio por seguridad de modo que se ejecutaran al inicializar el script.
mkdir -p nginx/certs
if [ ! -f nginx/certs/server.crt ] || [ ! -f nginx/certs/server.key ]; then
    echo "[INFO] No se encontraron certificados SSL."
    echo "[INFO] Generando certificados SSL/TLS autofirmados..."
    openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
      -keyout nginx/certs/server.key \
      -out nginx/certs/server.crt \
      -subj "//CN=localhost"
fi

# 4. Reconstruir y levantar la pila de contenedores
echo "[INFO] Reconstruyendo y levantando servicios en segundo plano..."
docker compose down -v
docker compose up -d --build

# 5. Esperar a la estabilización del entorno
echo "[INFO] Esperando 5 segundos para verificar el estado de los servicios..."
sleep 5

# 6. Ejecutar la copia de seguridad inicial de comprobación
if [ -f ./scripts/backup.sh ]; then
    echo "[INFO] Generando copia de seguridad inicial de comprobación..."
    chmod +x ./scripts/backup.sh
    ./scripts/backup.sh
fi

# 7. Mostrar el estado final de los contenedores
echo "[INFO] Estado actual de la infraestructura:"
docker compose ps

echo "=================================================="
echo " [ÉXITO] Despliegue completado y operativo."
echo " Acceso HTTP (Redirige 301): http://localhost:8081"
echo " Acceso HTTPS Seguro:       https://localhost:8443"
echo " Healthcheck API:           http://localhost:8081/health.php"
echo " Copias de seguridad:       ./backups/"
echo "=================================================="