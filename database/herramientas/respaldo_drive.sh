#!/bin/bash
# =====================================================================================
# Respaldo diario de SIGEBI a Google Drive (vía rclone).
#
#  1. Base de datos: respaldo completo y verificado (database/respaldo.php), CIFRADO con
#     BACKUP_PASSWORD (AES-256, formato estándar de OpenSSL) y subido a
#     gdrive:sigebi-respaldos/base-de-datos. En Drive se conservan 60 días; en el
#     servidor, 14 (BACKUP_RETENCION_DIAS). BACKUP_EMAIL solo recibe alertas de fallo.
#  2. Archivos subidos (fotos, facturas, evidencias): copia INCREMENTAL al remoto cifrado
#     "sigebi-cifrado" (rclone crypt sobre gdrive:sigebi-respaldos/archivos). Solo sube
#     lo nuevo o modificado y nunca borra en Drive lo que se borre en el servidor.
#  3. Si algo falla: queda en el registro y se envía un correo de alerta a BACKUP_EMAIL.
#
# Esta es la PLANTILLA versionada. La copia que se ejecuta vive en
# ~/scripts/backup_sigebi.sh (fuera de public_html: ni la web ni un despliegue la tocan).
# Se programa en hPanel > Avanzado > Cron Jobs (este hosting no da crontab por SSH):
#     0 2 * * *  /bin/bash /home/u397951547/scripts/backup_sigebi.sh
#
# Para restaurar, ver docs/despliegue.md ("Respaldos en Google Drive").
# =====================================================================================
set -euo pipefail

PROYECTO="$HOME/domains/ediertech.com/public_html/bienes"
STORAGE="$HOME/storage_sigebi"
RCLONE="$HOME/bin/rclone"
REMOTO_BD="gdrive:sigebi-respaldos/base-de-datos"
REMOTO_ARCHIVOS="sigebi-cifrado:"
LOG="$STORAGE/logs/respaldo-drive.log"
FECHA="$(date +%Y%m%d_%H%M%S)"
PHP_BIN="php"

mkdir -p "$STORAGE/logs" "$STORAGE/backups"
log() { echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*" >> "$LOG"; }

leer_env() {
    { grep -E "^$1=" "$PROYECTO/.env" || true; } | tail -1 | cut -d= -f2- | sed -e 's/^["'\'']//' -e 's/["'\'']$//'
}

alertar() {
    local linea="$1"
    log "FALLO en la línea $linea"
    cd "$PROYECTO" && $PHP_BIN -r '
        require "vendor/autoload.php";
        App\Core\Env::cargar();
        $para = (string) getenv("BACKUP_EMAIL");
        if ($para === "") { exit(0); }
        App\Services\MailService::enviar($para, "Responsable de SIGEBI", "SIGEBI: el respaldo diario a Google Drive FALLÓ",
            "<p>El respaldo del " . date("Y-m-d H:i") . " falló en la línea " . $argv[1] . " del script.</p>"
            . "<p>Revise el registro <code>storage_sigebi/logs/respaldo-drive.log</code> en el servidor.</p>");
    ' "$linea" >> "$LOG" 2>&1 || true
}
trap 'alertar $LINENO' ERR

log "=== Inicio del respaldo $FECHA ==="
cd "$PROYECTO"

# --- 1. Base de datos ---
DUMP="$STORAGE/backups/sigebi_$FECHA.sql.gz"   # sigebi_*: respaldo.php borra los locales de más de 14 días
$PHP_BIN database/respaldo.php --salida="$DUMP" --sin-correo >> "$LOG" 2>&1
gzip -t "$DUMP"

CLAVE_RESPALDO="$(leer_env BACKUP_PASSWORD)"
if [ -z "$CLAVE_RESPALDO" ]; then
    log "Falta BACKUP_PASSWORD en el .env: no se sube una copia sin cifrar."
    false
fi
export CLAVE_RESPALDO
openssl enc -aes-256-cbc -pbkdf2 -iter 200000 -md sha256 -salt \
    -in "$DUMP" -out "$DUMP.enc" -pass env:CLAVE_RESPALDO
unset CLAVE_RESPALDO

"$RCLONE" copy "$DUMP.enc" "$REMOTO_BD" --log-file="$LOG" --log-level INFO
rm -f "$DUMP.enc"
"$RCLONE" delete "$REMOTO_BD" --min-age 60d --log-file="$LOG" --log-level INFO
log "Base de datos en Drive: $(basename "$DUMP").enc"

# --- 2. Archivos subidos (incremental y cifrado) ---
"$RCLONE" copy "$STORAGE/uploads" "$REMOTO_ARCHIVOS" \
    --exclude "_miniaturas/**" --log-file="$LOG" --log-level NOTICE --stats-one-line --stats 0
log "Archivos subidos sincronizados ($(find "$STORAGE/uploads" -type f -not -path '*/_miniaturas/*' | wc -l) en el servidor)."

log "=== Fin del respaldo $FECHA: OK ==="
