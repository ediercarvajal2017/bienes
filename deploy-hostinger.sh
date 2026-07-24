#!/bin/bash
# Script de despliegue de SIGEBI en Hostinger.
# Uso (conectado por SSH al servidor):
#   curl -o deploy.sh https://raw.githubusercontent.com/ediercarvajal2017/bienes/main/deploy-hostinger.sh
#   bash deploy.sh
#
# Sirve tanto para el primer despliegue como para actualizaciones posteriores
# (si detecta que ya existe el repo, hace git pull en lugar de clonar).

set -e

REPO_URL="https://github.com/ediercarvajal2017/bienes.git"

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
read -rp "Ruta completa del proyecto (la carpeta que CONTIENE a 'public/', sin barra final): " PROYECTO_DIR
mkdir -p "$PROYECTO_DIR"
cd "$PROYECTO_DIR"

# --- 3. Clonar o actualizar ---
if [ -d ".git" ]; then
    echo "Ya existe un repositorio aquí, actualizando (git pull)..."
    git pull origin main
else
    if [ "$(ls -A . 2>/dev/null)" ]; then
        echo "ERROR: '$PROYECTO_DIR' no está vacía y no es un repositorio git."
        echo "Vacíala primero o indica otra ruta, y vuelve a ejecutar este script."
        exit 1
    fi
    git clone "$REPO_URL" .
fi
echo ""

# --- 4. Composer ---
if ! command -v composer &> /dev/null; then
    echo "ERROR: 'composer' no se encontró en el PATH de esta sesión SSH."
    echo "Revisa en hPanel el nombre/ruta exacta de Composer para tu plan e instala manualmente."
    exit 1
fi
composer install --no-dev --optimize-autoloader
echo ""

# --- 5. Archivo .env ---
if [ ! -f ".env" ]; then
    echo "Configura la conexión a la base de datos MySQL (creada previamente en hPanel):"
    read -rp "  DB_HOST [localhost]: " DB_HOST
    DB_HOST=${DB_HOST:-localhost}
    read -rp "  DB_DATABASE: " DB_DATABASE
    read -rp "  DB_USERNAME: " DB_USERNAME
    read -rsp "  DB_PASSWORD: " DB_PASSWORD
    echo ""

    cat > .env <<EOF
APP_ENV=production
APP_DEBUG=0
APP_TIMEZONE=America/Bogota

DB_HOST=${DB_HOST}
DB_PORT=3306
DB_DATABASE=${DB_DATABASE}
DB_USERNAME=${DB_USERNAME}
DB_PASSWORD=${DB_PASSWORD}
EOF
    echo ".env creado."
else
    echo ".env ya existe, no se modifica (se conserva la configuración actual)."
fi
echo ""

# --- 6. Migraciones y datos base ---
$PHP_BIN database/migrate.php
$PHP_BIN database/seeders/seed.php
echo ""

# --- 7. Permisos de almacenamiento ---
chmod -R 755 storage

echo ""
echo "=== Despliegue completado ==="
echo "Si el seed imprimió arriba un usuario/contraseña de superusuario, guárdalos ahora: no se repiten."
echo "Verifica visitando tu subdominio, y confirma que HTTPS y el docroot en /public queden activos desde hPanel."
