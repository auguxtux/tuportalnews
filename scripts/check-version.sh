#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BASE_URL="${1:-https://develop.erun.es}"

cd "$ROOT_DIR"

echo "[1/6] Sintaxis PHP"
find . -type f -name '*.php' \
    -not -path './vendor/*' \
    -not -path './uploads/*' \
    -not -path './cache/*' \
    -not -path './logs/*' \
    -not -path './backups/*' \
    -print0 | xargs -0 -n1 php -l >/dev/null

echo "[2/6] Sintaxis JavaScript"
find assets/js -type f -name '*.js' \
    -not -path 'assets/vendor/*' \
    -print0 | xargs -0 -n1 node --check

echo "[3/6] Destinos de rutas"
php -r '
require "includes/routes.php";
foreach ($rutas_front as $ruta => $archivo) {
    if (!is_file($archivo)) {
        fwrite(STDERR, "Destino inexistente: {$ruta} -> {$archivo}\n");
        exit(1);
    }
}
echo "Rutas verificadas: " . count($rutas_front) . PHP_EOL;
'

echo "[4/6] Dependencias Composer"
composer validate --no-check-publish >/dev/null
composer audit --locked --no-interaction

echo "[5/6] Formato del diff"
git diff --check

echo "[6/6] Respuestas HTTP"
check_http() {
    local path="$1"
    local expected="$2"
    local status

    status="$(curl -sS --max-time 15 -o /dev/null -w '%{http_code}' "${BASE_URL}${path}")"
    if [[ "$status" != "$expected" ]]; then
        echo "Respuesta inesperada: ${path} devolvió ${status}; se esperaba ${expected}" >&2
        return 1
    fi

    echo "${path}: ${status}"
}

check_http '/' '200'
check_http '/login' '200'
check_http '/recuperar_password' '200'
check_http '/categoria' '200'
check_http '/tiempo' '200'
check_http '/pobreza' '200'
check_http '/nasa' '200'
check_http '/sitemap.xml' '200'
check_http '/.env' '403'
check_http '/includes/config.php' '403'
check_http '/logs/error.log' '403'
check_http '/.git/HEAD' '403'

echo "Comprobación de versión completada correctamente."
