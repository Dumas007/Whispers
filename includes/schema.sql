SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS ajustes (
  clave VARCHAR(60) PRIMARY KEY,
  valor TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS negocios (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  nombre         VARCHAR(120) NOT NULL,
  rubro          VARCHAR(60) NULL,
  logo           VARCHAR(120) NULL,
  plan           ENUM('basico','profesional','premium') NOT NULL DEFAULT 'basico',
  activo         TINYINT(1) NOT NULL DEFAULT 1,
  alerta_whatsapp VARCHAR(255) NULL,
  alerta_email   VARCHAR(800) NULL,
  umbral_alerta  DECIMAL(2,1) NOT NULL DEFAULT 3.0,
  umbral_resena  DECIMAL(2,1) NOT NULL DEFAULT 4.0,
  color_principal VARCHAR(7) NULL,
  color_acento   VARCHAR(7) NULL,
  colores_panel  TINYINT(1) NOT NULL DEFAULT 0,
  preguntar_personal TINYINT(1) NOT NULL DEFAULT 0,
  resumen_semanal TINYINT(1) NOT NULL DEFAULT 1,
  proximo_cobro  DATE NULL,
  notas          TEXT NULL,
  creado_en      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sucursales (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  negocio_id        INT NOT NULL,
  nombre            VARCHAR(120) NOT NULL,
  direccion         VARCHAR(200) NULL,
  google_review_url VARCHAR(300) NULL,
  activa            TINYINT(1) NOT NULL DEFAULT 1,
  creado_en         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_suc_neg FOREIGN KEY (negocio_id) REFERENCES negocios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mesas (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  negocio_id  INT NOT NULL,
  sucursal_id INT NOT NULL,
  numero      INT NOT NULL,
  nombre      VARCHAR(60) NULL,
  token       VARCHAR(32) NOT NULL UNIQUE,
  activa      TINYINT(1) NOT NULL DEFAULT 1,
  creado_en   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_mesa (sucursal_id, numero),
  CONSTRAINT fk_mesa_neg FOREIGN KEY (negocio_id)  REFERENCES negocios(id)   ON DELETE CASCADE,
  CONSTRAINT fk_mesa_suc FOREIGN KEY (sucursal_id) REFERENCES sucursales(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS usuarios (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  negocio_id    INT NULL,
  sucursal_id   INT NULL,
  nombre        VARCHAR(100) NOT NULL,
  email         VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  rol           ENUM('super','admin','gerente') NOT NULL DEFAULT 'gerente',
  activo        TINYINT(1) NOT NULL DEFAULT 1,
  ultimo_acceso DATETIME NULL,
  creado_en     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_usr_neg FOREIGN KEY (negocio_id)  REFERENCES negocios(id)   ON DELETE CASCADE,
  CONSTRAINT fk_usr_suc FOREIGN KEY (sucursal_id) REFERENCES sucursales(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS preguntas (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  negocio_id INT NOT NULL,
  texto      VARCHAR(150) NOT NULL,
  texto_en   VARCHAR(150) NULL,
  especial   TINYINT(1) NOT NULL DEFAULT 0,
  hasta      DATE NULL,
  orden      INT NOT NULL DEFAULT 0,
  activa     TINYINT(1) NOT NULL DEFAULT 1,
  creado_en  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_preg_neg FOREIGN KEY (negocio_id) REFERENCES negocios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS encuestas (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  negocio_id     INT NOT NULL,
  sucursal_id    INT NOT NULL,
  mesa_id        INT NOT NULL,
  promedio       DECIMAL(3,2) NOT NULL,
  recomendaria   TINYINT(1) NULL,
  comentario     TEXT NULL,
  nombre_cliente VARCHAR(80) NULL,
  telefono       VARCHAR(20) NULL,
  revisada       TINYINT(1) NOT NULL DEFAULT 0,
  nota_interna   TEXT NULL,
  ip             VARCHAR(45) NULL,
  personal_id    INT NULL,
  idioma         CHAR(2) NOT NULL DEFAULT 'es',
  creado_en      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_neg_fecha (negocio_id, creado_en),
  INDEX idx_mesa (mesa_id),
  CONSTRAINT fk_enc_neg  FOREIGN KEY (negocio_id)  REFERENCES negocios(id)   ON DELETE CASCADE,
  CONSTRAINT fk_enc_suc  FOREIGN KEY (sucursal_id) REFERENCES sucursales(id) ON DELETE CASCADE,
  CONSTRAINT fk_enc_mesa FOREIGN KEY (mesa_id)     REFERENCES mesas(id)      ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS respuestas (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  encuesta_id  INT NOT NULL,
  pregunta_id  INT NOT NULL,
  calificacion TINYINT NOT NULL,
  INDEX idx_preg (pregunta_id),
  CONSTRAINT fk_resp_enc  FOREIGN KEY (encuesta_id) REFERENCES encuestas(id) ON DELETE CASCADE,
  CONSTRAINT fk_resp_preg FOREIGN KEY (pregunta_id) REFERENCES preguntas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS analisis_ia (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  negocio_id        INT NOT NULL,
  sucursal_id       INT NULL,
  desde             DATE NOT NULL,
  hasta             DATE NOT NULL,
  total_comentarios INT NOT NULL DEFAULT 0,
  resultado         MEDIUMTEXT NOT NULL,
  modelo            VARCHAR(60) NULL,
  creado_por        INT NULL,
  creado_en         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ia_neg (negocio_id, creado_en),
  CONSTRAINT fk_ia_neg FOREIGN KEY (negocio_id) REFERENCES negocios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS alertas_log (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  negocio_id  INT NOT NULL,
  encuesta_id INT NULL,
  canal       VARCHAR(20) NOT NULL,
  destino     VARCHAR(150) NOT NULL,
  ok          TINYINT(1) NOT NULL DEFAULT 0,
  detalle     TEXT NULL,
  creado_en   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_log_neg (negocio_id, creado_en),
  CONSTRAINT fk_log_neg FOREIGN KEY (negocio_id) REFERENCES negocios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
