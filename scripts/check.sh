#!/usr/bin/env bash
# Comprobacion : web, healthcheck, BD y recursos. Sale con 1 si esq falla algo
set -u
cd "$(dirname "$0")/.."
[ -f .env ] && { set -a; . ./.env; set +a; }
URL="${1:-https://localhost:8443}"
fail=0

code=$(curl -ks -o /dev/null -w '%{http_code}' "$URL/" || echo 000)
echo "web (/)            : $code";            [ "$code" = 200 ] || fail=1
code=$(curl -ks -o /dev/null -w '%{http_code}' "$URL/health.php" || echo 000)
echo "health.php         : $code";            [ "$code" = 200 ] || fail=1
code=$(curl -ks -o /dev/null -w '%{http_code}' "$URL/productos" || echo 000)
echo "API /productos     : $code";            [ "$code" = 200 ] || fail=1

if docker exec sneaker_db pg_isready -U "${DB_USER:-sneaker_user}" -d "${DB_NAME:-sneaker_db}" >/dev/null 2>&1; then
  echo "base de datos      : OK"
else
  echo "base de datos      : FALLO"; fail=1
fi

echo "--- recursos ---"
docker stats --no-stream --format 'CPU {{.Name}}: {{.CPUPerc}}  MEM: {{.MemPerc}}' 2>/dev/null || true
df -h / | tail -1

[ "$fail" = 0 ] && echo "RESULTADO: OK" || echo "RESULTADO: HAY FALLOS"
exit $fail