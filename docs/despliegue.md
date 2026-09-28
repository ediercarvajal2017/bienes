# Despliegue de MIA a producción (Hostinger)

**Regla:** se sube SOLO el software. La información de la base de producción es la correcta
y no se carga, reemplaza ni corrige. Lo único que puede cambiar en la base son las migraciones
de estructura pendientes (columnas y tablas nuevas), que el script muestra y pide confirmar.

- Sitio: https://bienes.ediertech.com
- Carpeta: `/home/u397951547/domains/ediertech.com/public_html/bienes`
- Archivos subidos: `/home/u397951547/storage_sigebi` (fuera de la carpeta del sitio)
- Acceso SSH: alias `sigebi-hostinger` (clave en `~/.ssh/hostinger_sigebi`)

## El "Auto Deploy" de Hostinger está APAGADO (desde 2026-09-27)

Hostinger estaba conectado a GitHub y publicaba solo cada cambio de `main`: sin
mantenimiento, sin respaldo, **sin migraciones**, instalando las dependencias de desarrollo y
borrando los archivos no versionados de la carpeta. Se apagó quitando a la aplicación
Hostinger el acceso al repositorio `bienes` (GitHub → Settings → Applications → Hostinger →
Configure → Repository access). **No volver a activarlo** ni pulsar "Redistribuir" en
hPanel → Git.

Por eso las actualizaciones llegan a producción **solo** cuando se ejecuta el script de
despliegue, que trae de GitHub la versión indicada.

## Forma rápida (uso diario): `./publicar.sh`

Se trabaja en la rama **`desarrollo`**. Con los cambios en commits, desde Git Bash:

```bash
./publicar.sh "Qué cambia en esta versión"            # pruebas rápidas + versión + push + despliegue
./publicar.sh "…" --completo                          # además, las 301 pruebas de navegador
./publicar.sh "…" --con-migraciones                   # solo tras autorizar las migraciones que mostró
```

Hace los pasos de abajo en uno solo. Si la versión trae migraciones se detiene sin tocar nada y
las lista. La rama `desarrollo` se une a `main` de vez en cuando (Pull Request), solo como registro.

## Flujo de una actualización

```
cambio → pruebas → etiqueta vX.Y.Z en GitHub → script por SSH → unir el Pull Request a main
```

### 1. Pruebas en local

```bash
composer analyse      # PHPStan nivel 7: 0 errores
composer test         # PHPUnit
npm test              # Playwright (base desechable sigebi_test)
```

### 2. Versión en GitHub

Subir `'version'` en `config/app.php`, hacer commit en una rama y:

```bash
git tag -a vX.Y.Z -m "Versión X.Y.Z"
git push origin <rama> vX.Y.Z
```

Abrir el Pull Request `<rama> → main`, **sin unirlo todavía** (se une en el paso 5).

### 3. Revisión previa (solo lectura)

```bash
ssh sigebi-hostinger
cd /home/u397951547/domains/ediertech.com/public_html/bienes
git status --short --untracked-files=no     # debe estar vacío
php database/migrate.php --pendientes       # lista lo que se aplicará (no ejecuta nada)
```

Si hay migraciones pendientes, revisarlas antes con el responsable del sistema. Conviene un
horario de poca actividad: el menor uso registrado es de 3:00 a 7:00 p. m. y los fines de semana.

### 4. Despliegue

El `deploy-hostinger.sh` de la carpeta del sitio es el de la versión **instalada**. Se usa el
de la versión NUEVA, extraído a la carpeta personal, con la ruta **absoluta** del proyecto
(si el primer parámetro es otra cosa, p. ej. "SI", el script lo toma como carpeta y prepara
una instalación nueva allí):

```bash
cd /home/u397951547/domains/ediertech.com/public_html/bienes
git fetch --tags origin                                    # solo descarga; no cambia el sitio
git show vX.Y.Z:deploy-hostinger.sh > ~/deploy-vX.Y.Z.sh
bash ~/deploy-vX.Y.Z.sh /home/u397951547/domains/ediertech.com/public_html/bienes vX.Y.Z
```

El script: mantenimiento → código y dependencias sin las de desarrollo (la base aún no se
toca) → respaldo verificado → conteo de filas → **si hay migraciones, las lista y pide
escribir SI** → las aplica → compara el conteo de filas y prueba el sitio → quita el
mantenimiento. Si algo falla antes de migrar, vuelve solo a la versión anterior.

### 5. Unir el Pull Request a `main`

Para que `main` sea siempre igual a lo publicado. Con el Auto Deploy apagado, unirlo no
publica nada.

### 6. Verificación posterior

- [ ] Ingresar con un usuario de cada rol.
- [ ] Panel principal, Bienes (con fotos), Asignar, Reintegrar, Bajas, Verificación física, Reportes.
- [ ] Escanear un QR ya impreso: abre la ficha del bien correcto.
- [ ] `php database/diagnostico_integridad.php` (solo lectura) no muestra hallazgos nuevos.
- [ ] Registro de errores del día (`storage_sigebi/logs/app-AAAA-MM-DD.log`) sin fallas nuevas.

## Si algo falla

El script deja el sitio en mantenimiento y muestra los comandos exactos. Como las
migraciones son aditivas, normalmente basta con volver el código:

```bash
git checkout <commit anterior que muestra el script> && composer install --no-dev --optimize-autoloader
rm public/mantenimiento.flag
```

Restaurar la base con el respaldo previo **solo si fuera necesario** (el script muestra el
comando con `database/restaurar.php`). Los respaldos quedan en `storage_sigebi/backups/`.

## Respaldos en Google Drive

Todos los días a las 2:00 a. m. (hPanel > Avanzado > Cron Jobs) se ejecuta
`~/scripts/backup_sigebi.sh` (plantilla: `database/herramientas/respaldo_drive.sh`):

- **Base de datos:** respaldo completo y verificado, cifrado con `BACKUP_PASSWORD` y subido a
  `Mi unidad/sigebi-respaldos/base-de-datos/` (se conservan 60 días en Drive y 14 en el servidor).
- **Fotos y documentos:** copia incremental cifrada en `Mi unidad/sigebi-respaldos/archivos/`
  (remoto `sigebi-cifrado` de rclone; los nombres se ven, el contenido va cifrado).
- **Si falla:** correo de alerta a `BACKUP_EMAIL` y registro en `storage_sigebi/logs/respaldo-drive.log`.

La contraseña de los respaldos (`BACKUP_PASSWORD`) está en el `.env` del servidor y en
`Documentos/SIGEBI_produccion/SECRETOS_SIGEBI_NO_COMPARTIR.txt`. **Sin ella no se pueden abrir.**

Restaurar la base desde Drive:

```bash
~/bin/rclone copy gdrive:sigebi-respaldos/base-de-datos/<archivo>.sql.gz.enc ~/restaurar/
openssl enc -d -aes-256-cbc -pbkdf2 -iter 200000 -md sha256     -in ~/restaurar/<archivo>.sql.gz.enc -out ~/restaurar/<archivo>.sql.gz   # pide BACKUP_PASSWORD
php database/restaurar.php ~/restaurar/<archivo>.sql.gz --base=<base> --reemplazar
```

Recuperar los archivos: `~/bin/rclone copy sigebi-cifrado: ~/restaurar/uploads` (rclone los
descifra solo; en otro equipo hay que crear el remoto `sigebi-cifrado` con la misma contraseña).

## Resumen diario de errores

`database/herramientas/resumen_errores.php` revisa los registros del día anterior en
`storage_sigebi/logs/`:

- errores (páginas 500) y avisos de PHP, agrupados por mensaje;
- avisos de la política de contenido (CSP, `csp-AAAA-MM-DD.log`);
- si el respaldo nocturno terminó bien.

Si encuentra algo, envía un correo a `BACKUP_EMAIL`. Si todo está limpio, no envía nada.

Se programa en hPanel > Avanzado > Cron Jobs:

```
30 6 * * *  cd /home/u397951547/domains/ediertech.com/public_html/bienes && php database/herramientas/resumen_errores.php
```

Para probarlo a mano: `php database/herramientas/resumen_errores.php --fecha=AAAA-MM-DD --sin-correo`
(solo lo muestra) o con `--siempre` (lo envía aunque no haya nada).

**CSP obligatoria:** hoy la política va en modo "solo reportar". Cuando el resumen lleve
unos 7 días sin avisos de CSP reales, se cambia en `App\Helpers\PoliticaContenido`
`Content-Security-Policy-Report-Only` por `Content-Security-Policy`.

## Historial

| Fecha | Versión | Migraciones | Notas |
|---|---|---|---|
| 2026-09-27 | 1.1.2 | 029–033 | Seguridad, 2FA opcional, ciclos de vida. Las 24 tablas de datos quedaron idénticas fila por fila |
| 2026-09-27 | 1.1.3 | — | Fotos: se liberan sesión y conexión antes de enviar; reintento de conexión |
| 2026-09-27 | 1.1.4 | — | Auditoría con todas las acciones en español; demostración local (`demo.bat`) y guion de presentación |
| 2026-09-27 | 1.1.5 | — | Respaldo diario a Google Drive (plantilla); scripts de corrección de datos R05 y R06 (`database/correcciones/`) |
| 2026-09-27 | 1.1.6 | — | Script de regularización R12. Aplicadas en producción: R06 (9 asignaciones cerradas), R05 (superusuario a la institución técnica) y R12 (sillas 200033042-178). R04 confirmado como autorizado; R07, R16 y R18 se dejan por decisión del responsable |

## Fuera del despliegue

Correcciones de datos del diagnóstico, mover al superusuario de institución y el entorno de
demostración requieren, cada uno, una autorización aparte.
