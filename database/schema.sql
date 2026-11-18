-- ============================================================
-- PetKardex v2 - ERP Veterinario
-- Schema SQL Completo
-- Generado automáticamente por el agente de Base de Datos
-- Engine: MySQL 8.0+ | Charset: utf8mb4_unicode_ci
-- ============================================================

CREATE DATABASE IF NOT EXISTS petkardex
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE petkardex;

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET time_zone = '+00:00';

-- ============================================================
-- TABLA 1: roles
-- ============================================================
CREATE TABLE IF NOT EXISTS `roles` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nombre`      VARCHAR(100) NOT NULL,
    `slug`        VARCHAR(100) NOT NULL UNIQUE,
    `descripcion` TEXT,
    `permisos`    JSON,
    `activo`      TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_roles_slug` (`slug`),
    KEY `idx_roles_activo` (`activo`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Roles del sistema con permisos en JSON';

-- ============================================================
-- TABLA 2: usuarios
-- ============================================================
CREATE TABLE IF NOT EXISTS `usuarios` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `rol_id`        INT UNSIGNED NOT NULL,
    `nombre`        VARCHAR(100) NOT NULL,
    `apellido`      VARCHAR(100) NOT NULL,
    `email`         VARCHAR(191) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `telefono`      VARCHAR(30),
    `dni`           VARCHAR(20),
    `foto`          VARCHAR(255),
    `activo`        TINYINT(1) NOT NULL DEFAULT 1,
    `ultimo_login`  DATETIME,
    `token_reset`   VARCHAR(255),
    `token_expiry`  DATETIME,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_usuarios_email` (`email`),
    UNIQUE KEY `uk_usuarios_dni` (`dni`),
    KEY `idx_usuarios_rol` (`rol_id`),
    KEY `idx_usuarios_activo` (`activo`),
    CONSTRAINT `fk_usuarios_rol`
        FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Usuarios del sistema';

-- ============================================================
-- TABLA 3: propietarios
-- ============================================================
CREATE TABLE IF NOT EXISTS `propietarios` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `usuario_id`      INT UNSIGNED DEFAULT NULL,
    `nombre`          VARCHAR(100) NOT NULL,
    `apellido`        VARCHAR(100) NOT NULL,
    `email`           VARCHAR(191),
    `telefono`        VARCHAR(30),
    `celular`         VARCHAR(30),
    `direccion`       VARCHAR(255),
    `dni`             VARCHAR(30) NOT NULL,
    `fecha_nacimiento` DATE,
    `foto`            VARCHAR(255),
    `notas`           TEXT,
    `activo`          TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_propietarios_dni` (`dni`),
    KEY `idx_propietarios_usuario` (`usuario_id`),
    KEY `idx_propietarios_email` (`email`),
    KEY `idx_propietarios_activo` (`activo`),
    CONSTRAINT `fk_propietarios_usuario`
        FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Propietarios / dueños de mascotas';

-- ============================================================
-- TABLA 4: especies
-- ============================================================
CREATE TABLE IF NOT EXISTS `especies` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nombre`      VARCHAR(100) NOT NULL,
    `descripcion` TEXT,
    `activo`      TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_especies_nombre` (`nombre`),
    KEY `idx_especies_activo` (`activo`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Especies animales';

-- ============================================================
-- TABLA 5: razas
-- ============================================================
CREATE TABLE IF NOT EXISTS `razas` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `especie_id`  INT UNSIGNED NOT NULL,
    `nombre`      VARCHAR(100) NOT NULL,
    `descripcion` TEXT,
    `activo`      TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    KEY `idx_razas_especie` (`especie_id`),
    KEY `idx_razas_activo` (`activo`),
    CONSTRAINT `fk_razas_especie`
        FOREIGN KEY (`especie_id`) REFERENCES `especies` (`id`)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Razas por especie';

-- ============================================================
-- TABLA 6: pacientes
-- ============================================================
CREATE TABLE IF NOT EXISTS `pacientes` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `propietario_id`   INT UNSIGNED NOT NULL,
    `especie_id`       INT UNSIGNED NOT NULL,
    `raza_id`          INT UNSIGNED DEFAULT NULL,
    `nombre`           VARCHAR(100) NOT NULL,
    `fecha_nacimiento` DATE,
    `sexo`             ENUM('macho','hembra','desconocido') NOT NULL DEFAULT 'desconocido',
    `color`            VARCHAR(100),
    `peso_kg`          DECIMAL(6,3),
    `microchip`        VARCHAR(50),
    `foto`             VARCHAR(255),
    `qr_code`          VARCHAR(255) UNIQUE,
    `qr_imagen`        VARCHAR(255),
    `alertas_medicas`  TEXT,
    `sangre_tipo`      VARCHAR(10),
    `esterilizado`     TINYINT(1) NOT NULL DEFAULT 0,
    `activo`           TINYINT(1) NOT NULL DEFAULT 1,
    `fecha_registro`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_pacientes_qr_code` (`qr_code`),
    KEY `idx_pacientes_propietario` (`propietario_id`),
    KEY `idx_pacientes_especie` (`especie_id`),
    KEY `idx_pacientes_raza` (`raza_id`),
    KEY `idx_pacientes_activo` (`activo`),
    KEY `idx_pacientes_microchip` (`microchip`),
    CONSTRAINT `fk_pacientes_propietario`
        FOREIGN KEY (`propietario_id`) REFERENCES `propietarios` (`id`)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_pacientes_especie`
        FOREIGN KEY (`especie_id`) REFERENCES `especies` (`id`)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_pacientes_raza`
        FOREIGN KEY (`raza_id`) REFERENCES `razas` (`id`)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Pacientes (mascotas)';

-- ============================================================
-- TABLA 7: historial_clinico
-- ============================================================
CREATE TABLE IF NOT EXISTS `historial_clinico` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `paciente_id`      INT UNSIGNED NOT NULL,
    `veterinario_id`   INT UNSIGNED NOT NULL,
    `fecha_consulta`   DATETIME NOT NULL,
    `tipo_consulta`    ENUM('consulta','emergencia','control','vacunacion','cirugia','estetica') NOT NULL DEFAULT 'consulta',
    `motivo`           TEXT,
    `anamnesis`        TEXT,
    `examen_fisico`    JSON COMMENT 'temperatura, frecuencia_cardiaca, frecuencia_respiratoria, peso, condicion_corporal',
    `diagnostico`      TEXT,
    `diagnostico_cie`  VARCHAR(20) COMMENT 'Código CIE para diagnóstico',
    `tratamiento`      TEXT,
    `observaciones`    TEXT,
    `proximo_control`  DATE,
    `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_historial_paciente` (`paciente_id`),
    KEY `idx_historial_veterinario` (`veterinario_id`),
    KEY `idx_historial_fecha` (`fecha_consulta`),
    KEY `idx_historial_tipo` (`tipo_consulta`),
    CONSTRAINT `fk_historial_paciente`
        FOREIGN KEY (`paciente_id`) REFERENCES `pacientes` (`id`)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_historial_veterinario`
        FOREIGN KEY (`veterinario_id`) REFERENCES `usuarios` (`id`)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Historial clínico de consultas veterinarias';

-- ============================================================
-- TABLA 8: vacunas_catalogo
-- ============================================================
CREATE TABLE IF NOT EXISTS `vacunas_catalogo` (
    `id`                           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nombre`                       VARCHAR(150) NOT NULL,
    `laboratorio`                  VARCHAR(150),
    `enfermedad_prevenida`         VARCHAR(255),
    `dosis_ml`                     DECIMAL(5,2),
    `via_administracion`           VARCHAR(100),
    `intervalo_revacunacion_dias`  INT UNSIGNED,
    `activo`                       TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    KEY `idx_vacunas_activo` (`activo`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Catálogo de vacunas disponibles';

-- ============================================================
-- TABLA 9: vacunaciones
-- ============================================================
CREATE TABLE IF NOT EXISTS `vacunaciones` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `paciente_id`    INT UNSIGNED NOT NULL,
    `vacuna_id`      INT UNSIGNED NOT NULL,
    `veterinario_id` INT UNSIGNED NOT NULL,
    `fecha`          DATE NOT NULL,
    `lote`           VARCHAR(100),
    `proxima_dosis`  DATE,
    `notas`          TEXT,
    `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_vacunaciones_paciente` (`paciente_id`),
    KEY `idx_vacunaciones_vacuna` (`vacuna_id`),
    KEY `idx_vacunaciones_veterinario` (`veterinario_id`),
    KEY `idx_vacunaciones_fecha` (`fecha`),
    CONSTRAINT `fk_vacunaciones_paciente`
        FOREIGN KEY (`paciente_id`) REFERENCES `pacientes` (`id`)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_vacunaciones_vacuna`
        FOREIGN KEY (`vacuna_id`) REFERENCES `vacunas_catalogo` (`id`)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_vacunaciones_veterinario`
        FOREIGN KEY (`veterinario_id`) REFERENCES `usuarios` (`id`)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Registro de vacunaciones aplicadas';

-- ============================================================
-- TABLA 10: recetas
-- ============================================================
CREATE TABLE IF NOT EXISTS `recetas` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `historial_id`  INT UNSIGNED NOT NULL,
    `paciente_id`   INT UNSIGNED NOT NULL,
    `veterinario_id` INT UNSIGNED NOT NULL,
    `fecha`         DATE NOT NULL,
    `instrucciones` TEXT,
    `valida_hasta`  DATE,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_recetas_historial` (`historial_id`),
    KEY `idx_recetas_paciente` (`paciente_id`),
    KEY `idx_recetas_veterinario` (`veterinario_id`),
    CONSTRAINT `fk_recetas_historial`
        FOREIGN KEY (`historial_id`) REFERENCES `historial_clinico` (`id`)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_recetas_paciente`
        FOREIGN KEY (`paciente_id`) REFERENCES `pacientes` (`id`)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_recetas_veterinario`
        FOREIGN KEY (`veterinario_id`) REFERENCES `usuarios` (`id`)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Recetas médicas veterinarias';

-- ============================================================
-- TABLA 12: categorias_inventario (before productos/receta_items)
-- ============================================================
CREATE TABLE IF NOT EXISTS `categorias_inventario` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nombre`      VARCHAR(100) NOT NULL,
    `descripcion` TEXT,
    `tipo`        ENUM('medicamento','alimento','accesorio','consumible','servicio') NOT NULL,
    `activo`      TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    KEY `idx_categorias_tipo` (`tipo`),
    KEY `idx_categorias_activo` (`activo`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Categorías del inventario de productos';

-- ============================================================
-- TABLA 13: productos
-- ============================================================
CREATE TABLE IF NOT EXISTS `productos` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `categoria_id`     INT UNSIGNED NOT NULL,
    `codigo`           VARCHAR(50) NOT NULL,
    `nombre`           VARCHAR(200) NOT NULL,
    `descripcion`      TEXT,
    `presentacion`     VARCHAR(100),
    `unidad_medida`    VARCHAR(50),
    `precio_compra`    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `precio_venta`     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `stock_actual`     DECIMAL(10,3) NOT NULL DEFAULT 0.000,
    `stock_minimo`     DECIMAL(10,3) NOT NULL DEFAULT 0.000,
    `requiere_receta`  TINYINT(1) NOT NULL DEFAULT 0,
    `activo`           TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_productos_codigo` (`codigo`),
    KEY `idx_productos_categoria` (`categoria_id`),
    KEY `idx_productos_activo` (`activo`),
    KEY `idx_productos_stock` (`stock_actual`),
    CONSTRAINT `fk_productos_categoria`
        FOREIGN KEY (`categoria_id`) REFERENCES `categorias_inventario` (`id`)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Productos del inventario';

-- ============================================================
-- TABLA 11: receta_items (after productos)
-- ============================================================
CREATE TABLE IF NOT EXISTS `receta_items` (
    `id`                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `receta_id`            INT UNSIGNED NOT NULL,
    `producto_id`          INT UNSIGNED DEFAULT NULL,
    `medicamento_nombre`   VARCHAR(200) NOT NULL,
    `presentacion`         VARCHAR(100),
    `dosis`                VARCHAR(100),
    `frecuencia`           VARCHAR(100),
    `duracion`             VARCHAR(100),
    `cantidad_dispensada`  DECIMAL(10,2),
    `notas`                TEXT,
    PRIMARY KEY (`id`),
    KEY `idx_receta_items_receta` (`receta_id`),
    KEY `idx_receta_items_producto` (`producto_id`),
    CONSTRAINT `fk_receta_items_receta`
        FOREIGN KEY (`receta_id`) REFERENCES `recetas` (`id`)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_receta_items_producto`
        FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Ítems de cada receta médica';

-- ============================================================
-- TABLA 14: movimientos_inventario
-- ============================================================
CREATE TABLE IF NOT EXISTS `movimientos_inventario` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `producto_id`      INT UNSIGNED NOT NULL,
    `tipo`             ENUM('entrada','salida','ajuste','devolucion') NOT NULL,
    `cantidad`         DECIMAL(10,3) NOT NULL,
    `stock_anterior`   DECIMAL(10,3) NOT NULL,
    `stock_nuevo`      DECIMAL(10,3) NOT NULL,
    `referencia_tipo`  VARCHAR(50) COMMENT 'venta, receta, ajuste_manual, etc.',
    `referencia_id`    INT UNSIGNED,
    `motivo`           TEXT,
    `usuario_id`       INT UNSIGNED NOT NULL,
    `fecha`            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_movimientos_producto` (`producto_id`),
    KEY `idx_movimientos_usuario` (`usuario_id`),
    KEY `idx_movimientos_tipo` (`tipo`),
    KEY `idx_movimientos_fecha` (`fecha`),
    CONSTRAINT `fk_movimientos_producto`
        FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_movimientos_usuario`
        FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Movimientos de stock del inventario';

-- ============================================================
-- TABLA 15: citas
-- ============================================================
CREATE TABLE IF NOT EXISTS `citas` (
    `id`                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `paciente_id`          INT UNSIGNED NOT NULL,
    `propietario_id`       INT UNSIGNED NOT NULL,
    `veterinario_id`       INT UNSIGNED DEFAULT NULL,
    `recepcionista_id`     INT UNSIGNED DEFAULT NULL,
    `tipo_servicio`        ENUM('consulta','vacunacion','cirugia','estetica','control','emergencia','hospitalizacion') NOT NULL DEFAULT 'consulta',
    `fecha_hora`           DATETIME NOT NULL,
    `duracion_minutos`     INT NOT NULL DEFAULT 30,
    `estado`               ENUM('programada','confirmada','en_proceso','completada','cancelada','no_asistio') NOT NULL DEFAULT 'programada',
    `notas`                TEXT,
    `recordatorio_enviado` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at`           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_citas_paciente` (`paciente_id`),
    KEY `idx_citas_propietario` (`propietario_id`),
    KEY `idx_citas_veterinario` (`veterinario_id`),
    KEY `idx_citas_recepcionista` (`recepcionista_id`),
    KEY `idx_citas_fecha` (`fecha_hora`),
    KEY `idx_citas_estado` (`estado`),
    CONSTRAINT `fk_citas_paciente`
        FOREIGN KEY (`paciente_id`) REFERENCES `pacientes` (`id`)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_citas_propietario`
        FOREIGN KEY (`propietario_id`) REFERENCES `propietarios` (`id`)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_citas_veterinario`
        FOREIGN KEY (`veterinario_id`) REFERENCES `usuarios` (`id`)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT `fk_citas_recepcionista`
        FOREIGN KEY (`recepcionista_id`) REFERENCES `usuarios` (`id`)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Agenda de citas veterinarias';

-- ============================================================
-- TABLA 16: hospitalizaciones
-- ============================================================
CREATE TABLE IF NOT EXISTS `hospitalizaciones` (
    `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `paciente_id`        INT UNSIGNED NOT NULL,
    `veterinario_id`     INT UNSIGNED NOT NULL,
    `fecha_ingreso`      DATETIME NOT NULL,
    `fecha_egreso`       DATETIME DEFAULT NULL,
    `motivo_ingreso`     TEXT,
    `diagnostico_ingreso` TEXT,
    `estado`             ENUM('activo','alta','fallecido','referido') NOT NULL DEFAULT 'activo',
    `cama_numero`        VARCHAR(20),
    `created_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_hospitalizaciones_paciente` (`paciente_id`),
    KEY `idx_hospitalizaciones_veterinario` (`veterinario_id`),
    KEY `idx_hospitalizaciones_estado` (`estado`),
    KEY `idx_hospitalizaciones_ingreso` (`fecha_ingreso`),
    CONSTRAINT `fk_hospitalizaciones_paciente`
        FOREIGN KEY (`paciente_id`) REFERENCES `pacientes` (`id`)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_hospitalizaciones_veterinario`
        FOREIGN KEY (`veterinario_id`) REFERENCES `usuarios` (`id`)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Registro de hospitalizaciones';

-- ============================================================
-- TABLA 17: signos_vitales
-- ============================================================
CREATE TABLE IF NOT EXISTS `signos_vitales` (
    `id`                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `hospitalizacion_id`   INT UNSIGNED NOT NULL,
    `usuario_id`           INT UNSIGNED NOT NULL,
    `fecha_hora`           DATETIME NOT NULL,
    `temperatura`          DECIMAL(4,1),
    `frecuencia_cardiaca`  INT UNSIGNED,
    `frecuencia_respiratoria` INT UNSIGNED,
    `saturacion_oxigeno`   DECIMAL(4,1) DEFAULT NULL,
    `peso_actual`          DECIMAL(6,3) DEFAULT NULL,
    `glucosa`              INT DEFAULT NULL,
    `tension_arterial`     VARCHAR(20) DEFAULT NULL,
    `estado_conciencia`    ENUM('alerta','deprimido','estuporoso','comatoso') NOT NULL DEFAULT 'alerta',
    `observaciones`        TEXT,
    `created_at`           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_signos_hospitalizacion` (`hospitalizacion_id`),
    KEY `idx_signos_usuario` (`usuario_id`),
    KEY `idx_signos_fecha` (`fecha_hora`),
    CONSTRAINT `fk_signos_hospitalizacion`
        FOREIGN KEY (`hospitalizacion_id`) REFERENCES `hospitalizaciones` (`id`)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_signos_usuario`
        FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Signos vitales monitoreados durante hospitalización';

-- ============================================================
-- TABLA 18: ventas
-- ============================================================
CREATE TABLE IF NOT EXISTS `ventas` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `numero_venta`   VARCHAR(50) NOT NULL,
    `cliente_tipo`   ENUM('propietario','externo') NOT NULL DEFAULT 'propietario',
    `propietario_id` INT UNSIGNED DEFAULT NULL,
    `cliente_nombre` VARCHAR(200),
    `cajero_id`      INT UNSIGNED NOT NULL,
    `fecha`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `subtotal`       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `descuento`      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `impuesto`       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `total`          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `metodo_pago`    ENUM('efectivo','tarjeta','transferencia','mixto') NOT NULL DEFAULT 'efectivo',
    `estado`         ENUM('pendiente','pagada','anulada') NOT NULL DEFAULT 'pendiente',
    `notas`          TEXT,
    `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_ventas_numero` (`numero_venta`),
    KEY `idx_ventas_propietario` (`propietario_id`),
    KEY `idx_ventas_cajero` (`cajero_id`),
    KEY `idx_ventas_fecha` (`fecha`),
    KEY `idx_ventas_estado` (`estado`),
    CONSTRAINT `fk_ventas_propietario`
        FOREIGN KEY (`propietario_id`) REFERENCES `propietarios` (`id`)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT `fk_ventas_cajero`
        FOREIGN KEY (`cajero_id`) REFERENCES `usuarios` (`id`)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Ventas y facturación';

-- ============================================================
-- TABLA 19: venta_items
-- ============================================================
CREATE TABLE IF NOT EXISTS `venta_items` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `venta_id`         INT UNSIGNED NOT NULL,
    `producto_id`      INT UNSIGNED DEFAULT NULL,
    `descripcion`      VARCHAR(200) NOT NULL,
    `cantidad`         DECIMAL(10,3) NOT NULL DEFAULT 1.000,
    `precio_unitario`  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `descuento_item`   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `subtotal`         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    PRIMARY KEY (`id`),
    KEY `idx_venta_items_venta` (`venta_id`),
    KEY `idx_venta_items_producto` (`producto_id`),
    CONSTRAINT `fk_venta_items_venta`
        FOREIGN KEY (`venta_id`) REFERENCES `ventas` (`id`)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_venta_items_producto`
        FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Líneas de cada venta';

-- ============================================================
-- TABLA 20: agenda_estetica
-- ============================================================
CREATE TABLE IF NOT EXISTS `agenda_estetica` (
    `id`                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `cita_id`              INT UNSIGNED DEFAULT NULL,
    `paciente_id`          INT UNSIGNED NOT NULL,
    `groomer_id`           INT UNSIGNED NOT NULL,
    `fecha_hora`           DATETIME NOT NULL,
    `servicios`            JSON COMMENT 'Lista de servicios de estética solicitados',
    `observaciones_previas` TEXT,
    `alertas_medicas`      TEXT,
    `estado`               ENUM('programada','en_proceso','completada','cancelada') NOT NULL DEFAULT 'programada',
    `alertas_enviadas`     TINYINT(1) NOT NULL DEFAULT 0,
    `created_at`           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_agenda_estetica_cita` (`cita_id`),
    KEY `idx_agenda_estetica_paciente` (`paciente_id`),
    KEY `idx_agenda_estetica_groomer` (`groomer_id`),
    KEY `idx_agenda_estetica_fecha` (`fecha_hora`),
    KEY `idx_agenda_estetica_estado` (`estado`),
    CONSTRAINT `fk_agenda_estetica_cita`
        FOREIGN KEY (`cita_id`) REFERENCES `citas` (`id`)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT `fk_agenda_estetica_paciente`
        FOREIGN KEY (`paciente_id`) REFERENCES `pacientes` (`id`)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_agenda_estetica_groomer`
        FOREIGN KEY (`groomer_id`) REFERENCES `usuarios` (`id`)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Agenda de servicios de estética y grooming';

-- ============================================================
-- TABLA 21: alertas_internas
-- ============================================================
CREATE TABLE IF NOT EXISTS `alertas_internas` (
    `id`                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `origen_usuario_id`    INT UNSIGNED NOT NULL,
    `destino_usuario_id`   INT UNSIGNED DEFAULT NULL,
    `rol_destino`          VARCHAR(50) DEFAULT NULL,
    `paciente_id`          INT UNSIGNED DEFAULT NULL,
    `tipo`                 ENUM('medica','inventario','cita','emergencia','sistema') NOT NULL DEFAULT 'sistema',
    `titulo`               VARCHAR(200) NOT NULL,
    `mensaje`              TEXT NOT NULL,
    `prioridad`            ENUM('baja','media','alta','critica') NOT NULL DEFAULT 'media',
    `leida`                TINYINT(1) NOT NULL DEFAULT 0,
    `fecha_lectura`        DATETIME DEFAULT NULL,
    `created_at`           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_alertas_origen` (`origen_usuario_id`),
    KEY `idx_alertas_destino` (`destino_usuario_id`),
    KEY `idx_alertas_paciente` (`paciente_id`),
    KEY `idx_alertas_tipo` (`tipo`),
    KEY `idx_alertas_prioridad` (`prioridad`),
    KEY `idx_alertas_leida` (`leida`),
    CONSTRAINT `fk_alertas_origen`
        FOREIGN KEY (`origen_usuario_id`) REFERENCES `usuarios` (`id`)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_alertas_destino`
        FOREIGN KEY (`destino_usuario_id`) REFERENCES `usuarios` (`id`)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT `fk_alertas_paciente`
        FOREIGN KEY (`paciente_id`) REFERENCES `pacientes` (`id`)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Alertas internas entre usuarios del sistema';

-- ============================================================
-- TABLA 22: documentos_clinicos
-- ============================================================
CREATE TABLE IF NOT EXISTS `documentos_clinicos` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `paciente_id`    INT UNSIGNED NOT NULL,
    `usuario_id`     INT UNSIGNED NOT NULL,
    `tipo`           ENUM('certificado','informe','receta','alta','consentimiento','otro') NOT NULL DEFAULT 'otro',
    `titulo`         VARCHAR(200) NOT NULL,
    `contenido_html` LONGTEXT,
    `archivo_pdf`    VARCHAR(255) DEFAULT NULL,
    `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_documentos_paciente` (`paciente_id`),
    KEY `idx_documentos_usuario` (`usuario_id`),
    KEY `idx_documentos_tipo` (`tipo`),
    CONSTRAINT `fk_documentos_paciente`
        FOREIGN KEY (`paciente_id`) REFERENCES `pacientes` (`id`)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_documentos_usuario`
        FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Documentos clínicos generados (PDF, HTML)';

-- ============================================================
-- TABLA 23: configuracion_sistema
-- ============================================================
CREATE TABLE IF NOT EXISTS `configuracion_sistema` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `clave`       VARCHAR(100) NOT NULL,
    `valor`       TEXT,
    `descripcion` TEXT,
    `grupo`       VARCHAR(50),
    `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_config_clave` (`clave`),
    KEY `idx_config_grupo` (`grupo`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Configuración general del sistema';

-- ============================================================
-- TABLA 24: sesiones_qr
-- ============================================================
CREATE TABLE IF NOT EXISTS `sesiones_qr` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `paciente_id` INT UNSIGNED NOT NULL,
    `token`       VARCHAR(255) NOT NULL,
    `ip_origen`   VARCHAR(45),
    `user_agent`  TEXT,
    `creado_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `expira_at`   DATETIME NOT NULL,
    `usos`        INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_sesiones_qr_token` (`token`),
    KEY `idx_sesiones_qr_paciente` (`paciente_id`),
    KEY `idx_sesiones_qr_expira` (`expira_at`),
    CONSTRAINT `fk_sesiones_qr_paciente`
        FOREIGN KEY (`paciente_id`) REFERENCES `pacientes` (`id`)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Sesiones de acceso vía código QR de mascotas';

-- ============================================================
-- TABLA 25: audit_log
-- ============================================================
CREATE TABLE IF NOT EXISTS `audit_log` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `usuario_id`       INT UNSIGNED DEFAULT NULL,
    `accion`           VARCHAR(100) NOT NULL,
    `tabla_afectada`   VARCHAR(100) NOT NULL,
    `registro_id`      INT UNSIGNED DEFAULT NULL,
    `datos_anteriores` JSON DEFAULT NULL,
    `datos_nuevos`     JSON DEFAULT NULL,
    `ip`               VARCHAR(45),
    `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_audit_usuario` (`usuario_id`),
    KEY `idx_audit_tabla` (`tabla_afectada`),
    KEY `idx_audit_accion` (`accion`),
    KEY `idx_audit_fecha` (`created_at`),
    CONSTRAINT `fk_audit_usuario`
        FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Log de auditoría de todas las acciones del sistema';

-- ============================================================
-- INSERCIONES INICIALES — ROLES
-- ============================================================
INSERT INTO `roles` (`nombre`, `slug`, `descripcion`, `permisos`, `activo`) VALUES
(
    'Super Administrador', 'superadmin',
    'Acceso total al sistema sin restricciones',
    JSON_OBJECT(
        'dashboard', JSON_ARRAY('ver'),
        'usuarios', JSON_ARRAY('ver','crear','editar','eliminar'),
        'roles', JSON_ARRAY('ver','crear','editar','eliminar'),
        'pacientes', JSON_ARRAY('ver','crear','editar','eliminar'),
        'propietarios', JSON_ARRAY('ver','crear','editar','eliminar'),
        'historial', JSON_ARRAY('ver','crear','editar','eliminar'),
        'vacunaciones', JSON_ARRAY('ver','crear','editar','eliminar'),
        'recetas', JSON_ARRAY('ver','crear','editar','eliminar'),
        'inventario', JSON_ARRAY('ver','crear','editar','eliminar'),
        'ventas', JSON_ARRAY('ver','crear','editar','eliminar','anular'),
        'citas', JSON_ARRAY('ver','crear','editar','eliminar'),
        'hospitalizaciones', JSON_ARRAY('ver','crear','editar','eliminar'),
        'estetica', JSON_ARRAY('ver','crear','editar','eliminar'),
        'reportes', JSON_ARRAY('ver','exportar'),
        'configuracion', JSON_ARRAY('ver','editar'),
        'audit_log', JSON_ARRAY('ver')
    ),
    1
),
(
    'Veterinario', 'veterinario',
    'Médico veterinario con acceso a historial clínico, consultas y procedimientos',
    JSON_OBJECT(
        'dashboard', JSON_ARRAY('ver'),
        'pacientes', JSON_ARRAY('ver','crear','editar'),
        'propietarios', JSON_ARRAY('ver','crear','editar'),
        'historial', JSON_ARRAY('ver','crear','editar'),
        'vacunaciones', JSON_ARRAY('ver','crear','editar'),
        'recetas', JSON_ARRAY('ver','crear','editar'),
        'inventario', JSON_ARRAY('ver'),
        'ventas', JSON_ARRAY('ver'),
        'citas', JSON_ARRAY('ver','editar'),
        'hospitalizaciones', JSON_ARRAY('ver','crear','editar'),
        'reportes', JSON_ARRAY('ver'),
        'estetica', JSON_ARRAY('ver')
    ),
    1
),
(
    'Guardia / Seguridad', 'guardia',
    'Personal de seguridad con acceso limitado a registro de visitas y alertas',
    JSON_OBJECT(
        'dashboard', JSON_ARRAY('ver'),
        'pacientes', JSON_ARRAY('ver'),
        'propietarios', JSON_ARRAY('ver'),
        'citas', JSON_ARRAY('ver'),
        'alertas', JSON_ARRAY('ver')
    ),
    1
),
(
    'Recepcionista', 'recepcionista',
    'Gestión de citas, recepción de pacientes, cobros y atención al cliente',
    JSON_OBJECT(
        'dashboard', JSON_ARRAY('ver'),
        'pacientes', JSON_ARRAY('ver','crear','editar'),
        'propietarios', JSON_ARRAY('ver','crear','editar'),
        'historial', JSON_ARRAY('ver'),
        'vacunaciones', JSON_ARRAY('ver'),
        'recetas', JSON_ARRAY('ver'),
        'inventario', JSON_ARRAY('ver','editar'),
        'ventas', JSON_ARRAY('ver','crear'),
        'citas', JSON_ARRAY('ver','crear','editar','cancelar'),
        'estetica', JSON_ARRAY('ver','crear','editar')
    ),
    1
),
(
    'Groomer / Estética', 'groomer',
    'Personal de estética y grooming con acceso a agenda y fichas de mascotas',
    JSON_OBJECT(
        'dashboard', JSON_ARRAY('ver'),
        'pacientes', JSON_ARRAY('ver'),
        'propietarios', JSON_ARRAY('ver'),
        'estetica', JSON_ARRAY('ver','crear','editar'),
        'citas', JSON_ARRAY('ver'),
        'alertas', JSON_ARRAY('ver','crear')
    ),
    1
),
(
    'Propietario', 'propietario',
    'Dueño de mascotas con acceso al portal de propietarios',
    JSON_OBJECT(
        'mis_mascotas', JSON_ARRAY('ver'),
        'historial', JSON_ARRAY('ver'),
        'vacunaciones', JSON_ARRAY('ver'),
        'recetas', JSON_ARRAY('ver'),
        'citas', JSON_ARRAY('ver','solicitar'),
        'documentos', JSON_ARRAY('ver','descargar')
    ),
    1
),
(
    'Emergencias', 'emergencia',
    'Acceso de emergencia con permisos temporales expandidos para situaciones críticas',
    JSON_OBJECT(
        'dashboard', JSON_ARRAY('ver'),
        'pacientes', JSON_ARRAY('ver','crear','editar'),
        'propietarios', JSON_ARRAY('ver','crear'),
        'historial', JSON_ARRAY('ver','crear','editar'),
        'vacunaciones', JSON_ARRAY('ver','crear'),
        'recetas', JSON_ARRAY('ver','crear'),
        'hospitalizaciones', JSON_ARRAY('ver','crear','editar'),
        'inventario', JSON_ARRAY('ver'),
        'alertas', JSON_ARRAY('ver','crear')
    ),
    1
);

-- ============================================================
-- INSERCIONES INICIALES — ESPECIES
-- ============================================================
INSERT INTO `especies` (`nombre`, `descripcion`, `activo`) VALUES
('Perro',   'Canis lupus familiaris', 1),
('Gato',    'Felis catus', 1),
('Ave',     'Aves domésticas y exóticas', 1),
('Reptil',  'Reptiles domésticos: iguanas, tortugas, serpientes', 1),
('Roedor',  'Hamsters, ratas, ratones, cobayas', 1),
('Conejo',  'Oryctolagus cuniculus', 1),
('Pez',     'Peces de acuario y estanque', 1),
('Otro',    'Otras especies no especificadas', 1);

-- ============================================================
-- INSERCIONES INICIALES — VACUNAS CATÁLOGO
-- ============================================================
INSERT INTO `vacunas_catalogo`
    (`nombre`, `laboratorio`, `enfermedad_prevenida`, `dosis_ml`, `via_administracion`, `intervalo_revacunacion_dias`, `activo`)
VALUES
('Rabia',           'Zoetis',    'Rabia / Lyssavirus',                        1.00, 'Subcutánea',   365, 1),
('Moquillo',        'Nobivac',   'Moquillo canino (CDV)',                      1.00, 'Subcutánea',   365, 1),
('Parvovirus',      'Nobivac',   'Parvovirus canino (CPV)',                    1.00, 'Subcutánea',   365, 1),
('Hepatitis',       'Nobivac',   'Hepatitis infecciosa canina (CAV-1)',        1.00, 'Subcutánea',   365, 1),
('Leptospirosis',   'Zoetis',    'Leptospira spp.',                           1.00, 'Subcutánea',   365, 1),
('Bordetella',      'Nobivac',   'Tos de las perreras (Bordetella bronchiseptica)', 1.00, 'Intranasal', 365, 1),
('DHLPP',           'Duramune',  'Moquillo, Hepatitis, Leptospirosis, Parvovirus, Parainfluenza', 1.00, 'Subcutánea', 365, 1),
('Triple Felina',   'Purevax',   'Panleucopenia, Calicivirus, Herpesvirus felino', 1.00, 'Subcutánea', 365, 1);

-- ============================================================
-- INSERCIONES INICIALES — CONFIGURACIÓN DEL SISTEMA
-- ============================================================
INSERT INTO `configuracion_sistema` (`clave`, `valor`, `descripcion`, `grupo`) VALUES
('nombre_clinica',       'PetKardex Clínica Veterinaria',   'Nombre oficial de la clínica veterinaria',                           'general'),
('slogan_clinica',       'Cuidamos a tus mejores amigos',   'Slogan o lema de la clínica',                                        'general'),
('direccion',            'Av. Principal 123, Ciudad',       'Dirección física de la clínica',                                     'contacto'),
('telefono',             '+51 999 000 111',                 'Teléfono principal de contacto',                                     'contacto'),
('telefono_emergencias', '+51 999 000 112',                 'Teléfono de emergencias 24h',                                        'contacto'),
('email',                'info@petkardex.com',              'Correo electrónico principal',                                       'contacto'),
('email_notificaciones', 'notificaciones@petkardex.com',   'Correo usado para enviar notificaciones automáticas',                 'contacto'),
('logo',                 '/assets/img/logo.png',            'Ruta al logo de la clínica',                                        'apariencia'),
('favicon',              '/assets/img/favicon.ico',         'Ruta al favicon',                                                    'apariencia'),
('color_primario',       '#2E86AB',                         'Color primario del tema (HEX)',                                      'apariencia'),
('color_secundario',     '#A23B72',                         'Color secundario del tema (HEX)',                                    'apariencia'),
('moneda',               'PEN',                             'Código ISO 4217 de la moneda (PEN=Sol, USD=Dólar)',                   'finanzas'),
('moneda_simbolo',       'S/.',                             'Símbolo de la moneda local',                                        'finanzas'),
('impuesto_porcentaje',  '18',                              'Porcentaje de impuesto (IGV/IVA)',                                   'finanzas'),
('zona_horaria',         'America/Lima',                    'Zona horaria del sistema (PHP timezone)',                             'sistema'),
('idioma',               'es',                              'Idioma del sistema (código ISO 639-1)',                              'sistema'),
('formato_fecha',        'd/m/Y',                           'Formato de fecha para mostrar en la UI',                            'sistema'),
('formato_hora',         'H:i',                             'Formato de hora (24h o 12h)',                                        'sistema'),
('sesion_duracion',      '480',                             'Duración de la sesión de usuario en minutos',                       'seguridad'),
('intentos_login',       '5',                               'Intentos de login fallidos antes de bloquear la cuenta',            'seguridad'),
('bloqueo_minutos',      '15',                              'Minutos de bloqueo tras intentos fallidos',                         'seguridad'),
('qr_expiracion_horas',  '24',                              'Horas de validez de un enlace QR de mascota',                       'qr'),
('citas_anticipacion_dias', '30',                           'Días máximos de anticipación para agendar una cita',                'citas'),
('recordatorio_horas',   '24',                              'Horas antes de la cita para enviar recordatorio',                   'citas'),
('smtp_host',            'mailhog',                         'Servidor SMTP para envío de correos',                               'email'),
('smtp_port',            '1025',                            'Puerto SMTP',                                                        'email'),
('smtp_usuario',         '',                                'Usuario SMTP (vacío en desarrollo)',                                 'email'),
('smtp_password',        '',                                'Contraseña SMTP (vacío en desarrollo)',                              'email'),
('smtp_cifrado',         'none',                            'Cifrado SMTP: none, ssl, tls',                                      'email'),
('version_sistema',      '2.0.0',                           'Versión actual del sistema PetKardex',                              'sistema'),
('modo_mantenimiento',   '0',                               '1=modo mantenimiento activo, 0=sistema operativo',                  'sistema');

-- ============================================================
SET FOREIGN_KEY_CHECKS = 1;
-- ============================================================
-- FIN DEL SCHEMA — PetKardex v2
-- 25 tablas creadas
-- ============================================================
