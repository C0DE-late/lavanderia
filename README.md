# Lavandería — registro de ropa por área

Aplicación web ligera (PHP 8.3 + MySQL 8, sin librerías externas) para registrar la ropa sucia y limpia que se recoge y entrega por área en cada recorrido. El reporte se exporta a CSV para Excel.

## Roles

| Rol | Puede |
|---|---|
| **Operario** | Registrar recorridos (peso por área, bolsas, foto y observaciones). Ver sus registros de los últimos 7 días y corregir los de hoy y ayer. |
| **Supervisor** | Ver el reporte con filtros, el peso total y el total por área, ver las fotos, corregir o eliminar registros y descargar los CSV. |
| **Administrador** | Todo lo del supervisor, más gestionar las áreas y los usuarios. |

## Datos de cada registro

Fecha · Recorrido (1, 2 o 3) · Ropa (sucia/limpia) · Área · Peso (kg) · Bolsas · Foto (opcional) · Responsable · Observaciones o novedades.

Una misma área no se puede registrar dos veces en el mismo recorrido, fecha y tipo de ropa. El formulario la marca como «Ya registrada».

## CSV para Excel

En **Reporte** se descargan dos archivos con los filtros aplicados:

- **CSV detalle:** un renglón por registro.
- **CSV total por área:** kg de ropa sucia, kg de ropa limpia, kg total y bolsas por área, con una fila TOTAL.

Los archivos usan punto y coma (`;`), coma decimal y UTF-8, así que el Excel en español los abre con doble clic y con las tildes correctas.

## Instalación con Docker (servidor)

```bash
cp .env.ejemplo .env                 # y cambia las claves
docker compose up -d --build
docker compose exec app php instalar/instalar.php   # solo la primera vez
```

El instalador crea las tablas, 13 áreas de ejemplo y un usuario por rol (`admin`, `supervisor`, `operario`). Las claves de esos usuarios se muestran **una sola vez** en la consola, así que cópialas en ese momento y cámbialas al entrar.

La app queda en `http://IP-DEL-SERVIDOR:8080`. El puerto se cambia con `PUERTO_APP` en `.env`. La base de datos y las fotos se guardan en los volúmenes `datos` y `fotos`, que sobreviven a reinicios y actualizaciones.

**Actualizar la app:** `docker compose up -d --build`. Los datos no se pierden.

**Respaldo:**

```bash
docker compose exec db sh -c 'mysqldump -u root -p"$MYSQL_ROOT_PASSWORD" lavanderia' > respaldo.sql
docker run --rm -v lavanderia_fotos:/f -v "$PWD":/b alpine tar czf /b/fotos.tgz -C /f .
```

(El nombre del volumen lleva como prefijo el nombre de la carpeta del proyecto; verifícalo con `docker volume ls`.)

### Cámara en el celular

El botón 📷 usa la cámara del celular mediante el selector de archivos del navegador, así que funciona también con `http`. Aun así, si la app va a salir a internet, conviene publicarla con **HTTPS** detrás del proxy del servidor (Nginx, Traefik, Caddy, etc.).

## Instalación local (WAMP)

1. Copia la carpeta en `C:\wamp64\www\lavanderia`.
2. Ejecuta `C:\wamp64\bin\php\php8.3.28\php.exe instalar\instalar.php`.
3. Abre `http://localhost/lavanderia`.

## Configuración

`config.php` lee las variables de entorno `DB_HOST`, `DB_PUERTO`, `DB_NOMBRE`, `DB_USUARIO`, `DB_CLAVE`, `DIR_FOTOS`, `APP_NOMBRE` y `APP_INSTITUCION`. Si no existen, usa los valores de WAMP. Allí mismo se cambian los recorridos (`[1, 2, 3]`) y los límites de peso y de bolsas.

## Seguridad

- Las claves se guardan cifradas con `password_hash`.
- Todos los formularios llevan protección CSRF y las consultas a la base son preparadas.
- Las fotos se reducen a 1280 px, se les quitan los metadatos y solo se entregan a usuarios con sesión.
- Los archivos de configuración y las carpetas `app/`, `instalar/` y `almacen/` están bloqueados desde la web.
- En los CSV, los textos que empiezan por `=`, `+`, `-` o `@` se neutralizan para que Excel no los ejecute como fórmula.
