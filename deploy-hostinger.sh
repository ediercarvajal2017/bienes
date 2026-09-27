#!/bin/bash
# Script de despliegue de SIGEBI en Hostinger.
#
# Uso (conectado por SSH al servidor):
#   curl -o deploy.sh https://raw.githubusercontent.com/ediercarvajal2017/bienes/main/deploy-hostinger.sh
#   bash deploy.sh [ruta_del_proyecto] [version]
#
#   ruta_del_proyecto  carpeta que CONTIENE a 'public/' (si no se indica, la pregunta)
#   version            etiqueta de git a desplegar (ej. v1.4.0). Si no se indica, despliega
#                      lo último de la rama main.
#
# SIGEBI YA ESTÁ EN PRODUCCIÓN CON INFORMACIÓN REAL. En una actualización este script:
#   1. pone el sitio en mantenimiento (nadie escribe mientras se actualiza),
#   2. hace un respaldo COMPLETO y VERIFICADO de la base de datos,
#   3. guarda el conteo de filas de cada tabla,
#   4. actualiza código y dependencias, y aplica las migraciones,
#   5. compara el conteo de filas (si alguna tabla perdió filas, se detiene),
#   6. saca el sitio de mantenimiento.
# Si algo falla ANTES de tocar la base de datos, se cancela sin cambios. Si falla DESPUÉS,
# el sitio queda en mantenimiento y se muestran los comandos exactos para volver atrás.

set -euo pipefail

REPO_URL="https://github.com/ediercarvajal2017/bienes.git"
PROYECTO_DIR="${1:-}"
VERSION="${2:-}"

echo "=== Despliegue de SIGEBI ==="
echo ""

# --- 1. Detectar un PHP 8.3+ utilizable ---
PHP_BIN="php"
if ! $PHP_BIN -v 2>/dev/null | grep -qE "PHP 8\.[3-9]"; then
    ENCONTRADO=""
    for candidato in /opt/alt/php83/usr/bin/php /usr/bin/php8.3 /usr/local/bin/php8.3 /usr/local/php83/bin/php; do
        if [ -x "$candidato" ] && "$candidato" -v 2>/dev/null | grep -qE "PHP 8\.[3-9]"; then
            PHP_BIN="$candidato"
            ENCONTRADO="1"
            break
        fi
    done
    if [ -z "$ENCONTRADO" ]; then
        echo "No se encontró automáticamente un PHP 8.3+."
        read -rp "Indica la ruta completa al binario de PHP 8.3+ a usar: " PHP_BIN
    fi
fi
echo "Usando: $($PHP_BIN -v | head -1)"
echo ""

# --- 2. Carpeta del proyecto ---
if [ -z "$PROYECTO_DIR" ]; then
    read -rp "Ruta completa del proyecto (la carpeta que CONTIENE a 'public/', sin barra final): " PROYECTO_DIR
fi
mkdir -p "$PROYECTO_DIR"
cd "$PROYECTO_DIR"

if ! command -v composer &> /dev/null; then
    echo "ERROR: 'composer' no se encontró en el PATH de esta sesión SSH."
    echo "Revisa en hPanel el nombre/ruta exacta de Composer para tu plan e instala manualmente."
    exit 1
fi

# Lee una variable del .env (sin comillas), o devuelve vacío.
leer_env() {
    [ -f .env ] || { echo ""; return; }
    grep -E "^$1=" .env | tail -1 | cut -d= -f2- | sed -e 's/^["'\'']//' -e 's/["'\'']$//'
}

# =====================================================================================
# PRIMER DESPLIEGUE (carpeta vacía): clonar, crear .env, migrar y sembrar.
# =====================================================================================
if [ ! -d ".git" ]; then
    if [ "$(ls -A . 2>/dev/null)" ]; then
        echo "ERROR: '$PROYECTO_DIR' no está vacía y no es un repositorio git."
        echo "Vacíala primero o indica otra ruta, y vuelve a ejecutar este script."
        exit 1
    fi

    git clone "$REPO_URL" .
    if [ -n "$VERSION" ]; then
        git checkout "tags/$VERSION"
    fi
    composer install --no-dev --optimize-autoloader
    echo ""

    echo "Configura la conexión a la base de datos MySQL (creada previamente en hPanel):"
    read -rp "  DB_HOST [localhost]: " DB_HOST
    DB_HOST=${DB_HOST:-localhost}
    read -rp "  DB_DATABASE: " DB_DATABASE
    read -rp "  DB_USERNAME: " DB_USERNAME
    read -rsp "  DB_PASSWORD: " DB_PASSWORD
    echo ""
    read -rp "  URL pública del sitio, con https (ej. https://sigebi.midominio.com): " APP_URL
    read -rp "  Carpeta ABSOLUTA para archivos subidos, fuera de esta carpeta (ej. /home/usuario/storage_sigebi): " STORAGE_PATH
    read -rp "  Correo que recibe los respaldos diarios (opcional): " BACKUP_EMAIL
    BACKUP_PASSWORD=""
    if [ -n "$BACKUP_EMAIL" ]; then
        echo "  La copia por correo se cifra. Escriba una contraseña larga y GUÁRDELA fuera del servidor:"
        read -rsp "    BACKUP_PASSWORD: " BACKUP_PASSWORD
        echo ""
    fi
    echo "  Correo SMTP para 'olvidé mi contraseña' (hPanel > Correos). Deja vacío para configurarlo después."
    read -rp "    MAIL_HOST (ej. smtp.hostinger.com): " MAIL_HOST
    read -rp "    MAIL_USERNAME: " MAIL_USERNAME
    read -rsp "    MAIL_PASSWORD: " MAIL_PASSWORD
    echo ""

    APP_KEY="$($PHP_BIN -r 'echo base64_encode(random_bytes(32));')"

    cat > .env <<EOF
APP_ENV=production
APP_DEBUG=0
APP_TIMEZONE=America/Bogota
APP_URL=${APP_URL}
APP_KEY=${APP_KEY}

DB_HOST=${DB_HOST}
DB_PORT=3306
DB_DATABASE=${DB_DATABASE}
DB_USERNAME=${DB_USERNAME}
DB_PASSWORD=${DB_PASSWORD}

MAIL_HOST=${MAIL_HOST}
MAIL_PORT=587
MAIL_USERNAME=${MAIL_USERNAME}
MAIL_PASSWORD=${MAIL_PASSWORD}
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=${MAIL_USERNAME}
MAIL_FROM_NAME=SIGEBI

STORAGE_PATH=${STORAGE_PATH}
BACKUP_EMAIL=${BACKUP_EMAIL}
BACKUP_PASSWORD=${BACKUP_PASSWORD}
BACKUP_RETENCION_DIAS=14
EOF
    chmod 600 .env
    if [ -n "$STORAGE_PATH" ]; then
        mkdir -p "$STORAGE_PATH"/{uploads,logs,backups,archivo_auditoria,diagnosticos}
    fi
    echo ".env creado."
    echo "IMPORTANTE: guarde también FUERA del servidor (gestor de contraseñas) esta llave APP_KEY:"
    echo "  ${APP_KEY}"
    echo "Protege las claves de la verificación en dos pasos; si se pierde, cada usuario debe configurarla de nuevo."
    echo ""

    $PHP_BIN database/migrate.php
    $PHP_BIN database/seeders/seed.php
    chmod -R 755 storage

    echo ""
    echo "=== Primer despliegue completado ==="
    echo "Si el seed imprimió arriba un usuario/contraseña de superusuario, guárdalos ahora: no se repiten."
    echo "Confirma en hPanel que HTTPS y el docroot en /public queden activos."
    echo ""
    echo "Tareas programadas recomendadas (hPanel > Avanzado > Cron Jobs):"
    echo "  0 2 * * *  $PHP_BIN $PROYECTO_DIR/database/respaldo.php"
    echo "  0 3 * * *  $PHP_BIN $PROYECTO_DIR/database/purgar_papelera.php"
    echo "  0 4 1 * *  $PHP_BIN $PROYECTO_DIR/database/archivar_auditoria.php"
    exit 0
fi

# =====================================================================================
# ACTUALIZACIÓN de un sitio en producción.
# =====================================================================================

# --- Comprobaciones previas (no cambian nada) ---
if [ ! -f .env ]; then
    echo "ERROR: no existe .env en $PROYECTO_DIR. No se puede actualizar sin la configuración."
    exit 1
fi

if [ "$(leer_env APP_DEBUG)" = "1" ]; then
    echo "ADVERTENCIA: APP_DEBUG=1 en el .env de producción (muestra detalles técnicos de los errores)."
    read -rp "¿Continuar de todos modos? (s/N): " RESP
    [ "$RESP" = "s" ] || exit 1
fi

if [ -z "$(leer_env APP_URL)" ]; then
    echo "ADVERTENCIA: falta APP_URL en el .env (el enlace de 'olvidé mi contraseña' se arma con el"
    echo "dominio que envía el navegador). Agrégalo, ej.: APP_URL=https://sigebi.midominio.com"
fi

# APP_KEY cifra las claves de la verificación en dos pasos. Si falta, se genera y se AGREGA
# al final del .env (no se toca nada más). Sin ella, la verificación en dos pasos no está
# disponible y no se exige a nadie.
if [ -z "$(leer_env APP_KEY)" ]; then
    NUEVA_APP_KEY="$($PHP_BIN -r 'echo base64_encode(random_bytes(32));')"
    printf '\n# Llave de cifrado (verificación en dos pasos). Respaldarla FUERA del servidor.\nAPP_KEY=%s\n' "$NUEVA_APP_KEY" >> .env
    echo "AVISO: se generó APP_KEY y se agregó al .env. Guárdela también FUERA del servidor:"
    echo "  ${NUEVA_APP_KEY}"
    echo "Desde ahora la verificación en dos pasos queda disponible (ver la política por rol en"
    echo "Administración > Verificación en dos pasos)."
fi

if [ -n "$(leer_env BACKUP_EMAIL)" ] && [ -z "$(leer_env BACKUP_PASSWORD)" ]; then
    echo "ADVERTENCIA: hay BACKUP_EMAIL pero falta BACKUP_PASSWORD en el .env: el respaldo diario"
    echo "ya NO se enviará por correo (iría sin cifrar). Agréguelo para mantener la copia externa."
fi

if [ -n "$(git status --porcelain --untracked-files=no)" ]; then
    echo "ERROR: hay archivos del repositorio modificados a mano en el servidor:"
    git status --short --untracked-files=no
    echo "Revísalos (git diff) antes de actualizar; este script no los sobrescribe."
    exit 1
fi

STORAGE_DIR="$(leer_env STORAGE_PATH)"
STORAGE_DIR="${STORAGE_DIR:-$PROYECTO_DIR/storage}"
BACKUP_DIR="$STORAGE_DIR/backups"
mkdir -p "$BACKUP_DIR" "$STORAGE_DIR/logs" "$STORAGE_DIR/diagnosticos"

case "$STORAGE_DIR" in
    "$PROYECTO_DIR"/*)
        echo "AVISO: los archivos subidos están dentro de la carpeta del proyecto ($STORAGE_DIR)."
        echo "       Si usas 'Auto Deploy' de Git en Hostinger, pueden borrarse. Recomendado: STORAGE_PATH fuera."
        ;;
esac

MARCA="$(date +%Y%m%d-%H%M%S)"
RESPALDO="$BACKUP_DIR/pre-deploy-$MARCA.sql.gz"
CONTEO="$STORAGE_DIR/diagnosticos/conteo-pre-deploy-$MARCA.json"
COMMIT_ANTERIOR="$(git rev-parse HEAD)"
MANTENIMIENTO="$PROYECTO_DIR/public/mantenimiento.flag"

# Si algo falla antes de tocar la base, se sale del mantenimiento sin cambios.
BASE_TOCADA=0
al_fallar() {
    echo ""
    if [ "$BASE_TOCADA" = "0" ]; then
        echo "*** El despliegue se canceló ANTES de modificar la base de datos. ***"
        git checkout -q "$COMMIT_ANTERIOR" 2>/dev/null || true
        composer install --no-dev --optimize-autoloader -q 2>/dev/null || true
        rm -f "$MANTENIMIENTO"
        echo "El sitio sigue con la versión anterior ($COMMIT_ANTERIOR) y vuelve a estar disponible."
    else
        echo "*** EL DESPLIEGUE FALLÓ DESPUÉS DE EMPEZAR A MIGRAR LA BASE DE DATOS. ***"
        echo "El sitio QUEDA EN MANTENIMIENTO para que nadie escriba en una base a medio actualizar."
        echo ""
        echo "Opciones:"
        echo "  a) Revisar el error de arriba, corregir y volver a ejecutar este script."
        echo "  b) Volver a la versión anterior (código y base de datos):"
        echo "       cd $PROYECTO_DIR"
        echo "       git checkout $COMMIT_ANTERIOR && composer install --no-dev --optimize-autoloader"
        echo "       $PHP_BIN database/restaurar.php $RESPALDO --base=$(leer_env DB_DATABASE) --reemplazar"
        echo "       rm $MANTENIMIENTO"
        echo "Respaldo previo al despliegue: $RESPALDO"
    fi
}
trap al_fallar ERR

# --- 1. Mantenimiento ---
touch "$MANTENIMIENTO"
echo "Sitio en modo mantenimiento."

# --- 2. Respaldo completo y verificado ---
echo "Respaldando la base de datos..."
$PHP_BIN database/respaldo.php --salida="$RESPALDO" --sin-correo --sin-limpieza
gzip -t "$RESPALDO"
echo "Respaldo verificado: $RESPALDO"

# --- 3. Conteo de filas previo ---
$PHP_BIN database/herramientas/humo.php --guardar="$CONTEO"

# --- 4. Código y dependencias ---
git fetch --tags origin
if [ -n "$VERSION" ]; then
    git checkout -q "tags/$VERSION"
else
    git checkout -q main
    git pull --ff-only origin main
fi
composer install --no-dev --optimize-autoloader
echo "Código actualizado: $COMMIT_ANTERIOR -> $(git rev-parse HEAD)"

# --- 5. Migraciones (a partir de aquí la base puede cambiar) ---
BASE_TOCADA=1
$PHP_BIN database/migrate.php
$PHP_BIN database/seeders/seed.php

# --- 6. Prueba de humo: ninguna tabla perdió filas y el sitio responde ---
APP_URL_ENV="$(leer_env APP_URL)"
rm -f "$MANTENIMIENTO"   # necesario para que la URL responda 200
if [ -n "$APP_URL_ENV" ]; then
    $PHP_BIN database/herramientas/humo.php --comparar="$CONTEO" --url="$APP_URL_ENV" || { touch "$MANTENIMIENTO"; false; }
else
    $PHP_BIN database/herramientas/humo.php --comparar="$CONTEO" || { touch "$MANTENIMIENTO"; false; }
fi

trap - ERR
echo ""
echo "=== Actualización completada ==="
echo "Versión anterior: $COMMIT_ANTERIOR"
echo "Versión actual:   $(git rev-parse HEAD)"
echo "Respaldo previo:  $RESPALDO   (guárdalo al menos hasta confirmar que todo funciona)"
