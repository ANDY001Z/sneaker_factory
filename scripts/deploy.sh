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
    # Asigna la contraseña de pruebas de laboratorio por defecto
    sed -i 's/cambiar_por_contraseña_segura/sneaker_pass/' .env
fi

# 3. Reconstruir y levantar la pila de contenedores
echo "[INFO] Reconstruyendo y levantando servicios en segundo plano..."
docker compose down -v
docker compose up -d --build

# 4. Esperar a la estabilización del entorno
echo "[INFO] Esperando 5 segundos para verificar el estado de los servicios..."
sleep 5

# 5. Mostrar el estado final de los contenedores
echo "[INFO] Estado actual de la infraestructura:"
docker compose ps

echo "=================================================="
echo " [ÉXITO] Despliegue completado y operativo."
echo " Acceso HTTP (Redirige 301): http://localhost:8081"
echo " Acceso HTTPS Seguro:       https://localhost:8443"
echo " Healthcheck API:           http://localhost:8081/health.php"
echo "=================================================="