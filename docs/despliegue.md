# Despliegue de SIGEBI a producción (Hostinger)

**Regla:** se sube SOLO el software. La información de la base de producción es la correcta
y no se carga, reemplaza ni corrige. Lo único que cambia en la base son las migraciones de
estructura pendientes (columnas y tablas nuevas, vacías), que el script muestra y pide
confirmar con "SI".

Versión 1.1.0 → migraciones pendientes en producción: `029` a `033`. Ensayadas sobre una
copia del volcado de producción: ninguna tabla perdió filas y las 24 tablas de datos
conservaron su contenido idéntico, fila por fila (la única que cambia es `schema_migrations`,
el registro de migraciones).

## 1. GitHub (desde el equipo de desarrollo)

```bash
git push -u origin preparacion-presentacion
git push origin v1.1.0
```

Abrir el Pull Request `preparacion-presentacion → main` en GitHub (queda el registro de todos
los cambios). **No unirlo todavía** si en Hostinger está activo el "Auto Deploy" de Git: ver el
paso 2.

## 2. Revisión previa en Hostinger (solo lectura)

- [ ] hPanel → Avanzado → **Git**: ¿está activo el "Auto Deploy"? Si lo está, **desactivarlo**
      antes de unir el Pull Request. Si no, al unir a `main` se publicaría el código nuevo sin
      respaldo, sin mantenimiento y sin las migraciones (el sitio fallaría).
- [ ] hPanel → Avanzado → **Configuración de PHP**: versión 8.3 o 8.4.
- [ ] Por SSH, en la carpeta del proyecto:
      ```bash
      php -v
      grep -E '^(APP_DEBUG|APP_URL|STORAGE_PATH|BACKUP_EMAIL)=' .env   # APP_DEBUG=0 y APP_URL con el dominio de los QR impresos
      git status --short --untracked-files=no                          # debe estar vacío
      php database/migrate.php --pendientes                            # debe listar 029 a 033
      ```
- [ ] Agregar al `.env` (si faltan): `APP_URL=https://<dominio exacto de los QR>` y
      `BACKUP_PASSWORD=<frase larga>` (guárdela también fuera del servidor). `APP_KEY` la genera el script.

## 3. Respaldo adicional en tu equipo

- [ ] hPanel → Bases de datos → phpMyAdmin → Exportar la base completa y descargarla.
- [ ] Descargar la carpeta de archivos subidos (`STORAGE_PATH/uploads`) o hacer una copia en el servidor.

## 4. Despliegue (horario de poca actividad)

1. Unir el Pull Request a `main` en GitHub (con el Auto Deploy desactivado).
2. Por SSH:
   ```bash
   cd <ruta del proyecto>
   bash deploy-hostinger.sh "$(pwd)" v1.1.0
   ```
   El script: pone el sitio en mantenimiento → respaldo verificado → conteo de filas →
   código v1.1.0 y dependencias → **lista las migraciones y pide escribir SI** → las aplica →
   compara el conteo de filas y prueba el sitio → quita el mantenimiento.
3. Si generó `APP_KEY`, la muestra una sola vez: **guárdela fuera del servidor**.

## 5. Verificación posterior

- [ ] Ingresar con un usuario de cada rol (todos deben iniciar sesión de nuevo una vez).
- [ ] Panel principal, Bienes, Asignar, Reintegrar, Bajas, Verificación física, Reportes.
- [ ] Escanear un QR ya impreso: abre la ficha del bien correcto.
- [ ] Descargar un reporte de cartera.
- [ ] `php database/diagnostico_integridad.php` (solo lectura) no muestra errores nuevos.
- [ ] Registro de errores del día (`STORAGE_PATH/logs/app-AAAA-MM-DD.log`) sin fallas.

## 6. Si algo falla

El script deja el sitio en mantenimiento y muestra los comandos exactos. Como las
migraciones son aditivas, normalmente basta con volver el código:

```bash
git checkout <commit anterior que muestra el script> && composer install --no-dev --optimize-autoloader
rm public/mantenimiento.flag
```

Restaurar la base con el respaldo previo **solo si fuera necesario** (el script muestra el
comando con `database/restaurar.php`).

## Fuera de este despliegue

Correcciones de datos del diagnóstico, mover al superusuario de institución y el entorno de
demostración requieren, cada uno, una autorización aparte.
