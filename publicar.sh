#!/bin/bash
# =====================================================================================
# Publica en producción lo que está en la rama "desarrollo", en un solo paso:
#   1. pruebas rápidas (PHPStan + PHPUnit; con --completo también Playwright)
#   2. nueva versión (sube el último número: 1.1.6 -> 1.1.7) con su etiqueta
#   3. push de la rama y la etiqueta a GitHub
#   4. despliegue en Hostinger con deploy-hostinger.sh (mantenimiento, respaldo verificado,
#      conteo de filas, prueba del sitio; si algo falla antes de migrar, vuelve atrás solo)
#   5. resumen corto del resultado
#
# Si la versión trae MIGRACIONES (cambios en la estructura de la base), se detiene sin tocar
# nada y las muestra: se vuelve a ejecutar con --con-migraciones solo después de autorizarlas.
#
# Uso (Git Bash, en la carpeta del proyecto, con los cambios ya en commits):
#     ./publicar.sh "Qué cambia en esta versión"
#     ./publicar.sh "…" --completo           (incluye las pruebas de navegador, ~6 min)
#     ./publicar.sh "…" --con-migraciones    (aplica las migraciones mostradas antes)
# =====================================================================================
set -euo pipefail
cd "$(dirname "$0")"

MENSAJE="${1:-}"
COMPLETO=0; MIGRAR=0
for arg in "${@:2}"; do
    case "$arg" in
        --completo) COMPLETO=1 ;;
        --con-migraciones) MIGRAR=1 ;;
        *) echo "Opción desconocida: $arg"; exit 1 ;;
    esac
done
[ -n "$MENSAJE" ] || { echo "Uso: ./publicar.sh \"Qué cambia\" [--completo] [--con-migraciones]"; exit 1; }

PHP="${PHP_BIN:-/c/xampp/php/php.exe}"
SERVIDOR="sigebi-hostinger"
RUTA="/home/u397951547/domains/ediertech.com/public_html/bienes"
export GIT_AUTHOR_NAME="ediercarvajal2017" GIT_AUTHOR_EMAIL="ediercarvajal@gmail.com"
export GIT_COMMITTER_NAME="$GIT_AUTHOR_NAME" GIT_COMMITTER_EMAIL="$GIT_AUTHOR_EMAIL"

paso() { echo ""; echo "▶ $*"; }

# --- 0. Comprobaciones ---
[ "$(git branch --show-current)" = "desarrollo" ] || { echo "Hay que estar en la rama 'desarrollo'."; exit 1; }
[ -z "$(git status --porcelain --untracked-files=no)" ] || { echo "Hay cambios sin commit:"; git status --short --untracked-files=no; exit 1; }

# --- 1. Pruebas ---
paso "Pruebas"
"$PHP" vendor/bin/phpstan analyse --memory-limit=1G --no-progress --error-format=raw > /dev/null || { echo "PHPStan encontró errores: no se publica."; "$PHP" vendor/bin/phpstan analyse --memory-limit=1G --no-progress --error-format=raw | head -20; exit 1; }
echo "  PHPStan: sin errores"
"$PHP" vendor/bin/phpunit > /tmp/phpunit-publicar.txt 2>&1 || { tail -20 /tmp/phpunit-publicar.txt; echo "PHPUnit falló: no se publica."; exit 1; }
echo "  PHPUnit: $(grep -oE 'OK \([^)]*\)' /tmp/phpunit-publicar.txt)"
if [ "$COMPLETO" = 1 ]; then
    PW_CANAL=msedge npx playwright test --reporter=dot > /tmp/pw-publicar.txt 2>&1 || { tail -30 /tmp/pw-publicar.txt; echo "Playwright falló: no se publica."; exit 1; }
    echo "  Playwright: $(grep -oE '[0-9]+ passed' /tmp/pw-publicar.txt)"
fi

# --- 2. Migraciones pendientes en producción ---
paso "Migraciones"
# Migraciones de esta versión que la base de producción todavía no tiene (solo lectura).
LOCALES=$(ls database/migrations/*.sql | xargs -n1 basename | sort)
APLICADAS=$(ssh "$SERVIDOR" "cd $RUTA && php" <<'PHP' | tr -d '\r' | sort
<?php
require 'vendor/autoload.php';
App\Core\Env::cargar();
$c = require 'config/database.php';
$p = new PDO("mysql:host={$c['host']};port={$c['port']};dbname={$c['database']}", $c['username'], $c['password']);
echo implode("\n", $p->query('SELECT migracion FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN)), "\n";
PHP
)
[ -n "$APLICADAS" ] || { echo "  No se pudo leer el estado de la base de producción: no se publica."; exit 1; }
NUEVAS=$(comm -23 <(echo "$LOCALES") <(echo "$APLICADAS") || true)
if [ -n "$NUEVAS" ]; then
    echo "  Esta versión trae migraciones que cambiarán la estructura de la base:"
    echo "$NUEVAS" | sed 's/^/    - /'
    if [ "$MIGRAR" != 1 ]; then
        echo "  No se publicó nada. Revíselas y, si las autoriza, ejecute de nuevo con --con-migraciones."
        exit 2
    fi
else
    echo "  Ninguna (la base no cambia)"
fi

# --- 3. Versión y etiqueta ---
paso "Versión"
ACTUAL=$(grep -oE "'version' => '[0-9]+\.[0-9]+\.[0-9]+'" config/app.php | grep -oE "[0-9]+\.[0-9]+\.[0-9]+")
NUEVA="${ACTUAL%.*}.$(( ${ACTUAL##*.} + 1 ))"
sed -i "s/'version' => '$ACTUAL'/'version' => '$NUEVA'/" config/app.php
git add config/app.php
git commit -q -m "Versión $NUEVA: $MENSAJE" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
git tag -a "v$NUEVA" -m "Versión $NUEVA: $MENSAJE"
echo "  $ACTUAL -> $NUEVA"

# --- 4. GitHub ---
paso "GitHub"
git push -q origin desarrollo "v$NUEVA"
echo "  Rama 'desarrollo' y etiqueta v$NUEVA subidas"

# --- 5. Producción ---
paso "Producción"
RESPUESTA="/dev/null"; [ "$MIGRAR" = 1 ] && RESPUESTA="SI"
SALIDA=$(ssh "$SERVIDOR" "cd $RUTA && git fetch -q --tags origin && git show v$NUEVA:deploy-hostinger.sh > ~/deploy-v$NUEVA.sh && chmod 700 ~/deploy-v$NUEVA.sh && if [ '$RESPUESTA' = SI ]; then echo SI | bash ~/deploy-v$NUEVA.sh $RUTA v$NUEVA; else bash ~/deploy-v$NUEVA.sh $RUTA v$NUEVA < /dev/null; fi; rm -f ~/deploy-v$NUEVA.sh" 2>&1) || true
echo "$SALIDA" | grep -E "Respaldo verificado|Ejecutando|HTTP |Prueba de humo|Actualización completada|FALL|ERROR|cancel|perdió" | sed 's/^/  /'
if ! echo "$SALIDA" | grep -q "=== Actualización completada ==="; then
    echo ""
    echo "✖ El despliegue NO terminó. Últimas líneas:"
    echo "$SALIDA" | tail -25
    exit 1
fi

echo ""
echo "✔ Versión $NUEVA publicada en https://bienes.ediertech.com"
