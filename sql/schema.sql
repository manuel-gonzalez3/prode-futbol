-- ============================================================
-- Prode de Torneos de Fútbol — Esquema + datos de prueba (seed)
-- Entornos Web · UCEL 2026
--
-- Este archivo crea la base completa desde cero y le carga datos
-- de prueba para poder probar la aplicación enseguida.
--
-- Cómo ejecutarlo:
--   mysql -u root < sql/schema.sql
-- o importándolo desde phpMyAdmin.
--
-- Es re-ejecutable: cada vez que se corre borra y vuelve a crear todo.
-- ============================================================

-- Le avisamos a MySQL que este archivo está en UTF-8: sin esto, al importarlo por
-- consola los acentos de los datos de prueba (García, López) se guardan mal.
SET NAMES utf8mb4;

-- Se borra la base entera y se vuelve a crear: así no quedan tablas
-- viejas ni claves foráneas que impidan reconstruirla.
DROP DATABASE IF EXISTS prode;
CREATE DATABASE prode
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
USE prode;

-- ------------------------------------------------------------
-- Tablas
-- ------------------------------------------------------------

-- Personas que usan el sistema: participantes y administradores.
-- Hay tres roles, de menor a mayor poder:
--   participante    → juega: ve torneos y carga pronósticos
--   admin           → administra torneos y participantes; a los otros admins solo los ve
--   admin_principal → además es el único que crea, edita o deshabilita admins.
--                     Se define acá (seed / base de datos): desde la aplicación nadie puede darlo ni quitarlo.
CREATE TABLE usuario (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre        VARCHAR(60)  NOT NULL,
    apellido      VARCHAR(60)  NOT NULL,
    email         VARCHAR(120) NOT NULL,
    password      VARCHAR(255) NOT NULL,          -- hash de password_hash(), nunca la contraseña en texto plano
    rol           ENUM('participante','admin','admin_principal') NOT NULL DEFAULT 'participante',
    activo        TINYINT(1)   NOT NULL DEFAULT 1, -- 0 = deshabilitado, no puede loguearse
    creado_en     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_usuario_email (email)           -- no puede haber dos usuarios con el mismo email
) ENGINE=InnoDB;

-- Intentos de login fallidos. Se usan para bloquear un email que acumula
-- demasiados intentos en poco tiempo (5 cada 15 minutos, ver IntentoLogin.php).
-- Se guarda el email escrito aunque no exista ningún usuario con ese email:
-- así el bloqueo funciona igual y no revela quién está registrado.
CREATE TABLE intento_login (
    id     INT UNSIGNED NOT NULL AUTO_INCREMENT,
    email  VARCHAR(120) NOT NULL,
    ip     VARCHAR(45)  NOT NULL,                     -- desde dónde se intentó (45 = largo máximo de una IPv6)
    fecha  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_intento_email_fecha (email, fecha)        -- para contar rápido los intentos recientes de un email
) ENGINE=InnoDB;

-- Pedidos de recuperación de contraseña. El token viaja en el link del mail;
-- acá se guarda solo su hash (SHA-256) para que, si alguien lee la tabla,
-- no pueda usar los links. Vence a los 30 minutos y sirve una sola vez.
CREATE TABLE recuperacion_password (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id  INT UNSIGNED NOT NULL,
    token_hash  CHAR(64)     NOT NULL,                -- SHA-256 del token en hexadecimal
    expira_en   DATETIME     NOT NULL,
    usado       TINYINT(1)   NOT NULL DEFAULT 0,      -- 1 = ya se usó o quedó invalidado por uno más nuevo
    creado_en   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_recuperacion_token (token_hash),
    CONSTRAINT fk_recuperacion_usuario FOREIGN KEY (usuario_id) REFERENCES usuario (id)
) ENGINE=InnoDB;

-- Cada torneo (ej: "Liga Profesional 2026"): tiene un precio de entrada,
-- va acumulando el pozo con cada inscripción pagada y se reparte al final.
CREATE TABLE torneo (
    id             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    nombre         VARCHAR(120)  NOT NULL,
    descripcion    TEXT          NULL,
    precio_entrada DECIMAL(10,2) NOT NULL,           -- cuánto cuesta inscribirse
    pozo_acumulado DECIMAL(12,2) NOT NULL DEFAULT 0, -- suma de las entradas cobradas
    estado         ENUM('proximo','activo','finalizado') NOT NULL DEFAULT 'proximo',
    inicio         DATE          NOT NULL,
    fin            DATE          NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB;

-- Una "fecha" es una jornada del torneo (fecha 1, fecha 2, ...).
-- El campo 'cierre' marca hasta cuándo se pueden cargar predicciones.
CREATE TABLE fecha (
    id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    torneo_id INT UNSIGNED NOT NULL,
    numero    INT UNSIGNED NOT NULL,
    cierre    DATETIME     NOT NULL, -- límite para cargar predicciones (alimenta el countdown)
    estado    ENUM('abierta','cerrada','finalizada') NOT NULL DEFAULT 'abierta',
    PRIMARY KEY (id),
    UNIQUE KEY uq_fecha_torneo_numero (torneo_id, numero), -- no se repite el número de fecha dentro de un torneo
    CONSTRAINT fk_fecha_torneo FOREIGN KEY (torneo_id) REFERENCES torneo (id)
) ENGINE=InnoDB;

-- Catálogo de equipos que pueden jugar los partidos.
CREATE TABLE equipo (
    id     INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre VARCHAR(80)  NOT NULL,
    escudo VARCHAR(255) NULL,     -- ruta/URL del escudo (opcional)
    PRIMARY KEY (id),
    UNIQUE KEY uq_equipo_nombre (nombre)
) ENGINE=InnoDB;

-- Cada partido de una fecha. Los goles quedan en NULL hasta que el admin
-- carga el resultado; ahí el estado pasa a 'finalizado'.
CREATE TABLE partido (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    fecha_id            INT UNSIGNED NOT NULL,
    equipo_local_id     INT UNSIGNED NOT NULL,
    equipo_visitante_id INT UNSIGNED NOT NULL,
    inicio              DATETIME     NOT NULL, -- corte individual para predicciones de ese partido
    goles_local         INT UNSIGNED NULL,     -- NULL hasta que se carga el resultado
    goles_visitante     INT UNSIGNED NULL,
    estado              ENUM('programado','finalizado') NOT NULL DEFAULT 'programado',
    actualizado_en      DATETIME     NULL,     -- cuándo se cargó/corrigió el resultado por última vez
    PRIMARY KEY (id),
    CONSTRAINT fk_partido_fecha     FOREIGN KEY (fecha_id)            REFERENCES fecha (id),
    CONSTRAINT fk_partido_local     FOREIGN KEY (equipo_local_id)     REFERENCES equipo (id),
    CONSTRAINT fk_partido_visitante FOREIGN KEY (equipo_visitante_id) REFERENCES equipo (id),
    CONSTRAINT chk_partido_equipos  CHECK (equipo_local_id <> equipo_visitante_id) -- un equipo no juega contra sí mismo
) ENGINE=InnoDB;

-- Vincula un usuario con un torneo. El admin marca la entrada como 'pagado'
-- y eso habilita al participante a cargar predicciones.
CREATE TABLE inscripcion (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id  INT UNSIGNED NOT NULL,
    torneo_id   INT UNSIGNED NOT NULL,
    estado_pago ENUM('pendiente','pagado') NOT NULL DEFAULT 'pendiente',
    creado_en   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_inscripcion_usuario_torneo (usuario_id, torneo_id), -- una sola inscripción por usuario y torneo
    CONSTRAINT fk_inscripcion_usuario FOREIGN KEY (usuario_id) REFERENCES usuario (id),
    CONSTRAINT fk_inscripcion_torneo  FOREIGN KEY (torneo_id)  REFERENCES torneo (id)
) ENGINE=InnoDB;

-- El pronóstico de un usuario para un partido. 'puntos' se calcula
-- automáticamente cuando se carga el resultado (5/3/2/0, ver app/helpers/scoring.php).
CREATE TABLE prediccion (
    id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id           INT UNSIGNED NOT NULL,
    partido_id           INT UNSIGNED NOT NULL,
    goles_local_pred     INT UNSIGNED NOT NULL,
    goles_visitante_pred INT UNSIGNED NOT NULL,
    puntos               INT UNSIGNED NOT NULL DEFAULT 0, -- puntaje obtenido (0 hasta que hay resultado)
    creado_en            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_prediccion_usuario_partido (usuario_id, partido_id), -- un pronóstico por usuario y partido
    CONSTRAINT fk_prediccion_usuario FOREIGN KEY (usuario_id) REFERENCES usuario (id),
    CONSTRAINT fk_prediccion_partido FOREIGN KEY (partido_id) REFERENCES partido (id)
) ENGINE=InnoDB;

-- Reglas de reparto del pozo: qué porcentaje se lleva cada puesto.
-- La suma de porcentajes de un torneo debe dar 100 (se valida en la app).
CREATE TABLE distribucion_premio (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    torneo_id  INT UNSIGNED NOT NULL,
    posicion   INT UNSIGNED NOT NULL,  -- 1 = campeón, 2 = subcampeón, ...
    porcentaje DECIMAL(5,2) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_distribucion_torneo_posicion (torneo_id, posicion),
    CONSTRAINT fk_distribucion_torneo FOREIGN KEY (torneo_id) REFERENCES torneo (id)
) ENGINE=InnoDB;

-- Premios ya asignados al terminar el torneo: quién salió en cada puesto
-- y cuánta plata le tocó (según la distribución de arriba aplicada al pozo).
CREATE TABLE premio (
    id         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    torneo_id  INT UNSIGNED  NOT NULL,
    usuario_id INT UNSIGNED  NOT NULL,
    posicion   INT UNSIGNED  NOT NULL,
    monto      DECIMAL(12,2) NOT NULL,
    creado_en  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_premio_torneo_posicion (torneo_id, posicion),
    CONSTRAINT fk_premio_torneo  FOREIGN KEY (torneo_id)  REFERENCES torneo (id),
    CONSTRAINT fk_premio_usuario FOREIGN KEY (usuario_id) REFERENCES usuario (id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Seed: solo usuarios por ahora
--
-- Credenciales de prueba:
--   admin@demo.com  / admin123   (rol admin_principal)
--   carla@demo.com  / admin123   (rol admin)
--   ana@demo.com    / prode123
--   bruno@demo.com  / prode123
--
-- Los emails llevan ".com" porque el alta/edición de usuarios valida el formato
-- con filter_var(): un email como "admin@demo" no pasa esa validación al guardar.
--
-- Las contraseñas se guardan hasheadas: los valores de abajo se
-- generaron con password_hash('admin123', PASSWORD_DEFAULT), etc.
-- ------------------------------------------------------------

INSERT INTO usuario (id, nombre, apellido, email, password, rol) VALUES
(1, 'Admin',  'Principal',      'admin@demo.com', '$2y$10$54wXJqOUNMr5z2rZ.Rc/T.CKXT4ynit6hQKo.8S9BjivJ3t0qaPgW', 'admin_principal'),
(2, 'Ana',    'García',         'ana@demo.com',   '$2y$10$VyUbiW1r5cSv5ai13qmtAepHpGjAoVXKa/.xbaKQ/zkZy41TP5ksy', 'participante'),
(3, 'Bruno',  'López',          'bruno@demo.com', '$2y$10$bA6zxQJkgLz3iUP6OOZMiOhPWQPrMONeE0L7/yMzDRGLBvj6W1Lra', 'participante'),
(4, 'Carla',  'Méndez',         'carla@demo.com', '$2y$10$kZ9hn37WeNNcC77XmODa.ubUgh/r6HRklrGwhNTiOvI3JxPh6h7TO', 'admin');
