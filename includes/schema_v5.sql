CREATE TABLE IF NOT EXISTS personal (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  negocio_id  INT NOT NULL,
  sucursal_id INT NULL,
  nombre      VARCHAR(60) NOT NULL,
  puesto      VARCHAR(40) NULL,
  foto        VARCHAR(120) NULL,
  activo      TINYINT(1) NOT NULL DEFAULT 1,
  creado_en   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_pers_neg (negocio_id),
  CONSTRAINT fk_pers_neg FOREIGN KEY (negocio_id) REFERENCES negocios(id) ON DELETE CASCADE,
  CONSTRAINT fk_pers_suc FOREIGN KEY (sucursal_id) REFERENCES sucursales(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cupones (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  negocio_id    INT NOT NULL,
  tipo          ENUM('premio','disculpa') NOT NULL,
  activo        TINYINT(1) NOT NULL DEFAULT 0,
  titulo        VARCHAR(80) NOT NULL,
  condiciones   VARCHAR(255) NULL,
  mensaje       TEXT NULL,
  vigencia_dias INT NOT NULL DEFAULT 30,
  prefijo       VARCHAR(6) NOT NULL DEFAULT 'WH',
  envio_auto    TINYINT(1) NOT NULL DEFAULT 0,
  UNIQUE KEY uq_cupon_tipo (negocio_id, tipo),
  CONSTRAINT fk_cup_neg FOREIGN KEY (negocio_id) REFERENCES negocios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cupones_emitidos (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  negocio_id    INT NOT NULL,
  tipo          ENUM('premio','disculpa') NOT NULL,
  encuesta_id   INT NULL,
  codigo        VARCHAR(20) NOT NULL UNIQUE,
  titulo        VARCHAR(80) NOT NULL,
  condiciones   VARCHAR(255) NULL,
  creado_en     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  vence_en      DATE NULL,
  canjeado_en   DATETIME NULL,
  canjeado_por  INT NULL,
  INDEX idx_ce_neg (negocio_id, creado_en),
  INDEX idx_ce_enc (encuesta_id),
  CONSTRAINT fk_ce_neg FOREIGN KEY (negocio_id) REFERENCES negocios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
;

CREATE TABLE IF NOT EXISTS negocio_funciones (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  negocio_id   INT NOT NULL,
  funcion      VARCHAR(30) NOT NULL,
  hasta        DATE NULL,
  otorgado_por INT NULL,
  creado_en    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_neg_func (negocio_id, funcion),
  CONSTRAINT fk_nf_neg FOREIGN KEY (negocio_id) REFERENCES negocios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci