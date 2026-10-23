
-- =====================================================
-- LA CUPONERA SV
-- Esquema inicial de la base de datos
-- MySQL / MariaDB
-- =====================================================

CREATE TABLE usuarios (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario VARCHAR(50) NOT NULL UNIQUE,
    correo VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    rol ENUM('cliente', 'empresa', 'admin') NOT NULL,
    estado ENUM('activo', 'inactivo', 'bloqueado')
        NOT NULL DEFAULT 'activo',
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE clientes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id BIGINT UNSIGNED NOT NULL UNIQUE,
    nombre VARCHAR(100) NOT NULL,
    apellidos VARCHAR(100) NOT NULL,
    dui VARCHAR(10) NOT NULL UNIQUE,
    fecha_nacimiento DATE NOT NULL,

    CONSTRAINT fk_clientes_usuario
        FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE empresas (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id BIGINT UNSIGNED NOT NULL UNIQUE,
    nombre VARCHAR(150) NOT NULL,
    nit VARCHAR(20) NOT NULL UNIQUE,
    direccion VARCHAR(255) NOT NULL,
    telefono VARCHAR(20) NOT NULL,
    estado_aprobacion ENUM(
        'pendiente', 'aprobada', 'rechazada'
    ) NOT NULL DEFAULT 'pendiente',
    comision_porcentaje DECIMAL(5,2) NULL,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT chk_empresas_comision
        CHECK (
            comision_porcentaje IS NULL
            OR comision_porcentaje BETWEEN 0 AND 100
        ),

    CONSTRAINT fk_empresas_usuario
        FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE ofertas (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id BIGINT UNSIGNED NOT NULL,
    titulo VARCHAR(180) NOT NULL,
    descripcion TEXT NOT NULL,
    precio_regular DECIMAL(10,2) NOT NULL,
    precio_oferta DECIMAL(10,2) NOT NULL,
    fecha_inicio DATETIME NOT NULL,
    fecha_fin DATETIME NOT NULL,
    fecha_limite_canje DATETIME NOT NULL,
    cantidad_maxima INT UNSIGNED NULL,
    estado ENUM(
        'borrador', 'publicada', 'pausada', 'finalizada'
    ) NOT NULL DEFAULT 'borrador',
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT chk_ofertas_precio
        CHECK (
            precio_regular > 0
            AND precio_oferta > 0
            AND precio_oferta < precio_regular
        ),

    CONSTRAINT chk_ofertas_fechas
        CHECK (
            fecha_inicio < fecha_fin
            AND fecha_limite_canje >= fecha_inicio
        ),

    CONSTRAINT fk_ofertas_empresa
        FOREIGN KEY (empresa_id)
        REFERENCES empresas(id)
        ON DELETE RESTRICT,

    INDEX idx_ofertas_estado_fechas (
        estado, fecha_inicio, fecha_fin
    ),
    INDEX idx_ofertas_empresa (empresa_id)
) ENGINE=InnoDB;

CREATE TABLE compras (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cliente_id BIGINT UNSIGNED NOT NULL,
    fecha TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    total DECIMAL(10,2) NOT NULL,
    estado ENUM(
        'pendiente', 'completada', 'fallida', 'cancelada'
    ) NOT NULL DEFAULT 'pendiente',
    referencia_pago VARCHAR(100) NULL UNIQUE,

    CONSTRAINT chk_compras_total
        CHECK (total >= 0),

    CONSTRAINT fk_compras_cliente
        FOREIGN KEY (cliente_id)
        REFERENCES clientes(id)
        ON DELETE RESTRICT,

    INDEX idx_compras_cliente_fecha (cliente_id, fecha)
) ENGINE=InnoDB;

CREATE TABLE cupones (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    compra_id BIGINT UNSIGNED NOT NULL,
    oferta_id BIGINT UNSIGNED NOT NULL,
    codigo_unico VARCHAR(64) NOT NULL UNIQUE,
    precio_pagado DECIMAL(10,2) NOT NULL,
    comision_aplicada DECIMAL(5,2) NOT NULL,
    estado ENUM(
        'disponible', 'canjeado', 'vencido', 'cancelado'
    ) NOT NULL DEFAULT 'disponible',
    fecha_canje DATETIME NULL,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT chk_cupones_precio
        CHECK (precio_pagado >= 0),

    CONSTRAINT chk_cupones_comision
        CHECK (comision_aplicada BETWEEN 0 AND 100),

    CONSTRAINT fk_cupones_compra
        FOREIGN KEY (compra_id)
        REFERENCES compras(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_cupones_oferta
        FOREIGN KEY (oferta_id)
        REFERENCES ofertas(id)
        ON DELETE RESTRICT,

    INDEX idx_cupones_compra (compra_id),
    INDEX idx_cupones_oferta (oferta_id),
    INDEX idx_cupones_estado (estado)
) ENGINE=InnoDB;

CREATE TABLE facturas (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    compra_id BIGINT UNSIGNED NOT NULL UNIQUE,
    numero_factura VARCHAR(30) NOT NULL UNIQUE,
    fecha_emision TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    total DECIMAL(10,2) NOT NULL,

    CONSTRAINT chk_facturas_total
        CHECK (total >= 0),

    CONSTRAINT fk_facturas_compra
        FOREIGN KEY (compra_id)
        REFERENCES compras(id)
        ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE tokens_recuperacion (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    vence_en DATETIME NOT NULL,
    usado_en DATETIME NULL,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_tokens_usuario
        FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id)
        ON DELETE CASCADE,

    INDEX idx_tokens_usuario (usuario_id),
    INDEX idx_tokens_vencimiento (vence_en)
) ENGINE=InnoDB;
