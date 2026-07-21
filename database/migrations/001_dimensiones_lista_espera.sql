-- ============================================================================
-- SIGLECH - Dimensiones (catálogos) para Lista de Espera SIGTE
-- Fuente: Importacion/DIMENSIONES_LE/DIMENSIONES/*.csv
-- Integridad referencial verificada contra CNE/IQ/PROC: >99% match en todas
-- las relaciones, <0.2% huérfanos (ver 08_Integridad_Referencial_LE.json)
-- ============================================================================

-- ============================================================================
-- 1. DIM_ESTABLECIMIENTO
-- Fuente única (Mantenedor_ Establecimiento.csv, 2701 filas). Se usa 3 veces
-- por role-playing desde las tablas de hechos: ESTAB_ORIG, ESTAB_DEST, E_OTOR_AT
-- son todas FK a esta misma tabla (Establecimientos_Comuna.csv es la misma
-- fuente duplicada con una columna extra, no se carga aparte).
-- ============================================================================

CREATE TABLE IF NOT EXISTS dim_establecimiento (
    id INT PRIMARY KEY COMMENT 'Código Nuevo (6 dígitos) - usado en ESTAB_ORIG/ESTAB_DEST/E_OTOR_AT',
    codigo_antiguo VARCHAR(10) COMMENT 'CODIGO formato XX-XXX',
    nombre VARCHAR(200) NOT NULL,
    region_codigo TINYINT UNSIGNED,
    region_nombre VARCHAR(100),
    comuna_codigo INT,
    comuna_nombre VARCHAR(100),
    ss_codigo TINYINT UNSIGNED COMMENT '33 = Servicio de Salud Chiloé',
    ss_nombre VARCHAR(100),
    tipo_establecimiento VARCHAR(10),
    nivel_atencion VARCHAR(30) COMMENT 'Nivel de Atención SIGTE - usar esta, no nivel_atencion_deis',
    nivel_atencion_deis VARCHAR(30),
    dependencia_ejecucion VARCHAR(100),
    certificacion VARCHAR(50),
    hospital VARCHAR(5),
    especialidad VARCHAR(100),
    activo CHAR(2) DEFAULT 'SI',
    vigente CHAR(2) DEFAULT 'SI',
    creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_comuna (comuna_codigo),
    INDEX idx_ss (ss_codigo),
    INDEX idx_nivel (nivel_atencion),
    INDEX idx_vigente (vigente)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Maestro de establecimientos SSCH - role-playing (origen/destino/otorgante)';

-- ============================================================================
-- 2. DIM_TIPO_LISTA
-- Fuente: Tipo_Lista.csv (5 filas). Discrimina CNE / PROC / IQ.
-- ============================================================================

CREATE TABLE IF NOT EXISTS dim_tipo_lista (
    id TINYINT UNSIGNED PRIMARY KEY COMMENT '1 CNE, 2 Consulta Repetida, 3 PROC, 4 IQ, 5 IQ Compleja',
    descripcion VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Catálogo de tipos de prestación/lista';

-- ============================================================================
-- 3. DIM_PRESTACION_CNE_IQ
-- Fuente: Prestaciones_CNE_IQ.csv (1226 filas). Cubre PRESTA_MIN en CNE e IQ.
-- El campo "Código SIGTE" (formato XX-XX-XXX) es el que hace match real con
-- PRESTA_MIN; "CODIGO" (Propuesta de Código, sin guiones) es secundario.
-- ============================================================================

CREATE TABLE IF NOT EXISTS dim_prestacion_cne_iq (
    codigo_sigte VARCHAR(20) PRIMARY KEY COMMENT 'Formato XX-XX-XXX - hace match con PRESTA_MIN en CNE e IQ',
    codigo_propuesta VARCHAR(20) COMMENT 'CODIGO sin guiones (Propuesta de Código)',
    base_rem CHAR(2),
    tipo_prestacion TINYINT UNSIGNED COMMENT 'Ver dim_tipo_lista',
    nombre VARCHAR(300) NOT NULL,
    especialidad VARCHAR(150),
    complejidad VARCHAR(30),
    temporalidad VARCHAR(30),
    vigencia_entrada DATE,
    vigencia_salida DATE,
    FOREIGN KEY (tipo_prestacion) REFERENCES dim_tipo_lista(id) ON DELETE SET NULL,
    INDEX idx_especialidad (especialidad),
    INDEX idx_codigo_propuesta (codigo_propuesta)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Catálogo prestaciones CNE + IQ (Consulta Nueva Especialidad e Intervención Quirúrgica)';

-- ============================================================================
-- 4. DIM_MAESTRO_PRESTACION
-- Fuente: Maestro_Prestacion.csv (1947 filas). Cubre PRESTA_MIN en PROC,
-- que NO está cubierto por Prestaciones_CNE_IQ (0.16% match confirmado en
-- integridad referencial - por eso PROC usa este catálogo distinto).
-- ============================================================================

CREATE TABLE IF NOT EXISTS dim_maestro_prestacion (
    codigo VARCHAR(20) PRIMARY KEY COMMENT 'Formato XX-XXX o XX-XX-XXX - hace match con PRESTA_MIN en PROC',
    prestacion VARCHAR(300) NOT NULL,
    cod_tipo_lista TINYINT UNSIGNED COMMENT 'Ver dim_tipo_lista',
    tipo_lista_nombre VARCHAR(100),
    vigencia_entrada DATE,
    vigencia_salida DATE,
    nombre_tipo_procedimiento VARCHAR(100),
    especialidad VARCHAR(150),
    edad_min_anio TINYINT UNSIGNED,
    edad_max_anio TINYINT UNSIGNED,
    tipo_especialidad VARCHAR(30) COMMENT 'Odontológica / Médica',
    FOREIGN KEY (cod_tipo_lista) REFERENCES dim_tipo_lista(id) ON DELETE SET NULL,
    INDEX idx_especialidad (especialidad)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Catálogo maestro de prestaciones/procedimientos (usado por PROC)';

-- ============================================================================
-- 5. DIM_CAUSAL_SALIDA
-- Fuente: Causales_Salidas.csv (22 filas). Vacío en C_SALIDA = caso VIGENTE.
-- ============================================================================

CREATE TABLE IF NOT EXISTS dim_causal_salida (
    id TINYINT UNSIGNED PRIMARY KEY COMMENT 'Columna Causales, 0-21',
    nombre VARCHAR(200) NOT NULL,
    tipo_causal VARCHAR(30) COMMENT 'Médica / Administrativas',
    tipo_causal_2 VARCHAR(30),
    excluir_numerador TINYINT(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Catálogo de causales de salida/egreso de lista de espera';

-- ============================================================================
-- 6. DIM_CIE10
-- Fuente: CIE10.csv (14226 filas). FK opcional (CIE10_HOMOLOGADO es derivada).
-- ============================================================================

CREATE TABLE IF NOT EXISTS dim_cie10 (
    codigo VARCHAR(10) PRIMARY KEY,
    descripcion VARCHAR(300) NOT NULL,
    INDEX idx_descripcion (descripcion(100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Catálogo CIE-10 (diagnósticos)';

-- ============================================================================
-- 7. DIM_PRESTACION_GES
-- Fuente: Tabla_Prestacion_Combinada.csv (1298 filas). Catálogo de APOYO para
-- reglas GES/No-GES y rangos etarios; NO tiene FK obligatoria desde las tablas
-- de hechos (se usa para validación/priorización, no para integridad de carga).
-- ============================================================================

CREATE TABLE IF NOT EXISTS dim_prestacion_ges (
    codigo VARCHAR(20) PRIMARY KEY COMMENT 'Propuesta de Código',
    tipo_le VARCHAR(20) COMMENT 'GES / NO GES',
    glosa VARCHAR(300),
    especialidad VARCHAR(150),
    tipo_especialidad VARCHAR(30),
    complejidad VARCHAR(30),
    oncologica TINYINT(1) DEFAULT 0,
    edad_min_anio TINYINT UNSIGNED,
    edad_max_anio TINYINT UNSIGNED,
    edad_min_dias INT,
    edad_max_dias INT,
    INDEX idx_tipo_le (tipo_le),
    INDEX idx_especialidad (especialidad),
    INDEX idx_oncologica (oncologica)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Catálogo combinado de prestaciones con clasificación GES/No-GES y reglas etarias';

-- ============================================================================
-- DATOS FIJOS: dim_tipo_lista (5 filas, se cargan aquí directo, no vía CSV)
-- ============================================================================

INSERT IGNORE INTO dim_tipo_lista (id, descripcion) VALUES
(1, 'Consulta Nueva'),
(2, 'Consulta Repetida'),
(3, 'Procedimientos'),
(4, 'Intervención Quirúrgica'),
(5, 'Intervención Quirúrgica Compleja');
