-- Esquema de la base de datos de Lavandería y Dietas.
-- Solo crea lo que no existe: se puede volver a ejecutar en una instalación en uso
-- para agregar las tablas nuevas sin tocar los datos.

CREATE TABLE IF NOT EXISTS usuarios (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre      VARCHAR(120) NOT NULL,
    usuario     VARCHAR(40)  NOT NULL UNIQUE,
    clave_hash  VARCHAR(255) NOT NULL,
    rol         ENUM('operario', 'supervisor', 'admin') NOT NULL DEFAULT 'operario',
    activo      TINYINT(1)   NOT NULL DEFAULT 1,
    creado_en   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS areas (
    id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre  VARCHAR(100) NOT NULL UNIQUE,
    orden   INT          NOT NULL DEFAULT 0,
    activa  TINYINT(1)   NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- Lavandería: un renglón por área, recorrido, fecha y tipo de ropa.
CREATE TABLE IF NOT EXISTS registros (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fecha            DATE                 NOT NULL,
    recorrido        TINYINT UNSIGNED     NOT NULL,
    tipo             ENUM('sucia', 'limpia') NOT NULL,
    area_id          INT UNSIGNED         NOT NULL,
    peso_kg          DECIMAL(6,2)         NOT NULL,
    bolsas           SMALLINT UNSIGNED    NOT NULL DEFAULT 0,
    foto             VARCHAR(60)          NULL,
    observaciones    VARCHAR(500)         NOT NULL DEFAULT '',
    usuario_id       INT UNSIGNED         NOT NULL,
    actualizado_por  INT UNSIGNED         NULL,
    creado_en        DATETIME             NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en   DATETIME             NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_registro (fecha, recorrido, tipo, area_id),
    KEY ix_usuario_fecha (usuario_id, fecha),
    CONSTRAINT fk_registros_area    FOREIGN KEY (area_id)         REFERENCES areas (id),
    CONSTRAINT fk_registros_usuario FOREIGN KEY (usuario_id)      REFERENCES usuarios (id),
    CONSTRAINT fk_registros_editor  FOREIGN KEY (actualizado_por) REFERENCES usuarios (id)
) ENGINE=InnoDB;

-- Dietas: catálogo de tipos (normal, blanda, líquida...).
CREATE TABLE IF NOT EXISTS tipos_dieta (
    id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre  VARCHAR(100) NOT NULL UNIQUE,
    orden   INT          NOT NULL DEFAULT 0,
    activa  TINYINT(1)   NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- Dietas: una entrega por área, tiempo de comida y fecha.
CREATE TABLE IF NOT EXISTS entregas_dieta (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fecha            DATE          NOT NULL,
    tiempo           VARCHAR(20)   NOT NULL,
    area_id          INT UNSIGNED  NOT NULL,
    foto             VARCHAR(60)   NULL,
    observaciones    VARCHAR(500)  NOT NULL DEFAULT '',
    usuario_id       INT UNSIGNED  NOT NULL,
    actualizado_por  INT UNSIGNED  NULL,
    creado_en        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en   DATETIME      NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_entrega (fecha, tiempo, area_id),
    KEY ix_usuario_fecha (usuario_id, fecha),
    CONSTRAINT fk_entregas_area    FOREIGN KEY (area_id)         REFERENCES areas (id),
    CONSTRAINT fk_entregas_usuario FOREIGN KEY (usuario_id)      REFERENCES usuarios (id),
    CONSTRAINT fk_entregas_editor  FOREIGN KEY (actualizado_por) REFERENCES usuarios (id)
) ENGINE=InnoDB;

-- Dietas: cantidades pedidas y entregadas de cada tipo en una entrega.
CREATE TABLE IF NOT EXISTS entregas_dieta_detalle (
    entrega_id     INT UNSIGNED      NOT NULL,
    tipo_dieta_id  INT UNSIGNED      NOT NULL,
    pedidas        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    entregadas     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (entrega_id, tipo_dieta_id),
    CONSTRAINT fk_detalle_entrega FOREIGN KEY (entrega_id)    REFERENCES entregas_dieta (id) ON DELETE CASCADE,
    CONSTRAINT fk_detalle_tipo    FOREIGN KEY (tipo_dieta_id) REFERENCES tipos_dieta (id)
) ENGINE=InnoDB;
