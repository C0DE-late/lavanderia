# Lavandería y dietas — registro por área

Aplicación web ligera (PHP 8.3 + MySQL 8, sin librerías externas) para registrar, por área del hospital:

- **Ropa:** la ropa sucia y limpia que se recoge y entrega en cada recorrido.
- **Dietas:** cuántas dietas de cada tipo se pidieron y cuántas se entregaron en cada tiempo de comida.

Cada uno es un **módulo independiente** dentro de la misma app, con su propio menú, panel, reportes y catálogos. Los reportes se exportan a CSV para Excel.

## Módulos

- Al entrar, cada persona ve los módulos que tiene asignados y elige uno. Si tiene un solo módulo, entra directo a él.
- Dentro de un módulo, el menú lateral (en el celular, la barra inferior y el botón ☰) solo muestra las opciones de ese módulo. Desde el mismo menú se salta al otro módulo.
- El administrador asigna los módulos de cada usuario en **Usuarios** y entra siempre a todos.
- Cada módulo tiene **sus propias áreas**: las de lavandería (todas las que entregan ropa) y las de dietas (solo las que tienen pacientes).

| Carpeta | Contenido |
|---|---|
| `ropa/` | Módulo de lavandería: panel, registrar, mis registros, reporte, CSV, áreas. |
| `dietas/` | Módulo de dietas: panel, registrar, mis registros, reporte, CSV, áreas, tipos de dieta. |
| raíz | Ingreso, selector de módulos, usuarios y cambio de clave. |
| `app/` | Código compartido: base de datos, sesión, plantilla, permisos y la lógica de cada módulo (`ropa.php`, `dietas.php`). |

## Roles

| Rol | Puede |
|---|---|
| **Operario** | Registrar en los módulos que tenga asignados. Ver sus registros de los últimos 7 días y corregir los de hoy y ayer. |
| **Supervisor** | En sus módulos: ver los reportes con filtros, las novedades del día y las fotos; corregir o eliminar registros y descargar los CSV. |
| **Administrador** | Todo lo del supervisor, más gestionar las áreas de cada módulo, los tipos de dieta y los usuarios con sus módulos. |

## Panel de cada módulo

Cada módulo abre en su panel, que muestra cómo va el día (o el día que se elija):

- **Lavandería:** kg de ropa sucia y limpia, bolsas y, por cada recorrido y tipo de ropa, cuántas áreas van y cuáles faltan.
- **Dietas:** pedidas, entregadas, diferencia y cumplimiento; por cada tiempo de comida, cuántas áreas van y cuáles faltan.
- **Novedades** (supervisor y administrador): las observaciones del día del módulo, con enlace al registro.

## Datos de cada registro de ropa

Fecha · Recorrido (1, 2 o 3) · Ropa (sucia/limpia) · Área · Peso (kg) · Bolsas · Foto (opcional) · Responsable · Observaciones o novedades.

Una misma área no se puede registrar dos veces en el mismo recorrido, fecha y tipo de ropa. El formulario la marca como «Ya registrada».

## Datos de cada registro de dietas

Fecha · Tiempo de comida (desayuno, media mañana, almuerzo, cena, refrigerio nocturno) · Área · Por cada tipo de dieta: pedidas y entregadas · Foto (opcional) · Responsable · Observaciones.

- Un área se registra una sola vez por tiempo de comida y fecha.
- Si lo entregado no coincide con lo pedido, las observaciones son obligatorias.
- Los tipos de dieta (normal, blanda, líquida, hiposódica…) se editan en **Dietas → Tipos de dieta**. Los tiempos de comida se cambian en `config.php`.
- El **Reporte de dietas** muestra totales por tiempo de comida, por área y por tipo, con la diferencia y el % de cumplimiento, y descarga tres CSV: detalle, por área y por tipo.

## CSV para Excel

En **Reporte ropa** se descargan dos archivos con los filtros aplicados:

- **CSV detalle:** un renglón por registro.
- **CSV total por área:** kg de ropa sucia, kg de ropa limpia, kg total y bolsas por área, con una fila TOTAL.

Los archivos usan punto y coma (`;`), coma decimal y UTF-8, así que el Excel en español los abre con doble clic y con las tildes correctas.

## Instalación con Docker (servidor)

```bash
cp .env.ejemplo .env                 # y cambia las claves
docker compose up -d --build
docker compose exec app php instalar/instalar.php   # solo la primera vez
```

El instalador crea las tablas, 13 áreas de lavandería, 8 áreas de dietas y 12 tipos de dieta de ejemplo, y un usuario por rol con acceso a los dos módulos (`admin`, `supervisor`, `operario`). Las claves de esos usuarios se muestran **una sola vez** en la consola, así que cópialas en ese momento y cámbialas al entrar.

La app queda en `http://IP-DEL-SERVIDOR:8080`. El puerto se cambia con `PUERTO_APP` en `.env`. La base de datos y las fotos se guardan en los volúmenes `datos` y `fotos`, que sobreviven a reinicios y actualizaciones.

**Actualizar la app:** `docker compose up -d --build`. Los datos no se pierden. Si la versión nueva trae tablas o columnas nuevas, vuelve a ejecutar el instalador: solo crea lo que falta y no toca los datos ni las claves.

```bash
docker compose exec app php instalar/instalar.php
```

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
3. Abre `http://localhost/lavanderia/`.

## Configuración

`config.php` lee las variables de entorno `DB_HOST`, `DB_PUERTO`, `DB_NOMBRE`, `DB_USUARIO`, `DB_CLAVE`, `DIR_FOTOS`, `APP_NOMBRE` y `APP_INSTITUCION`. Si no existen, usa los valores de WAMP. Allí mismo se cambian los recorridos (`[1, 2, 3]`), los tiempos de comida y los límites de peso, bolsas y dietas.

## Seguridad

- Las claves se guardan cifradas con `password_hash`.
- Todos los formularios llevan protección CSRF y las consultas a la base son preparadas.
- Las fotos se reducen a 1280 px, se les quitan los metadatos y solo se entregan a usuarios con sesión.
- Los archivos de configuración y las carpetas `app/`, `instalar/` y `almacen/` están bloqueados desde la web.
- En los CSV, los textos que empiezan por `=`, `+`, `-` o `@` se neutralizan para que Excel no los ejecute como fórmula.
