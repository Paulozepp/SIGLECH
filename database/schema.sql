-- ============================================================================
-- SIGLECH - Esquema de Base de Datos
-- Sistema Independiente de Gestión de Listas de Espera Chiloé v1.0
-- Base de datos compartida: sicoch_referencia
-- ============================================================================

-- ============================================================================
-- 1. TABLA: Usuarios (Autenticación Local de SIGLECH)
-- ============================================================================

CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario VARCHAR(100) UNIQUE NOT NULL,
    nombre VARCHAR(200) NOT NULL,
    email VARCHAR(150),
    password_hash VARCHAR(255) NOT NULL,
    rol ENUM('admin', 'gestor', 'consultor', 'viewer') DEFAULT 'consultor',
    activo TINYINT DEFAULT 1,
    ultimo_acceso DATETIME,
    creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_usuario (usuario),
    INDEX idx_activo (activo)
);

-- ============================================================================
-- 2. TABLA: Listas de Espera - Interconsultas
-- ============================================================================

CREATE TABLE IF NOT EXISTS lista_espera_interconsultas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    folio_sic VARCHAR(20) UNIQUE NOT NULL,
    paciente_id INT NOT NULL,                    -- Referencia a SICOCH.pacientes
    especialidad_id INT,                         -- Referencia a SICOCH.especialidades
    establecimiento_destino_id INT,              -- Referencia a SICOCH.establecimientos
    tipo_lista VARCHAR(50) NOT NULL,             -- Categoría: CN_GES, ONCOLOGICO, etc.
    prioridad ENUM('ALTA', 'MEDIA', 'BAJA') DEFAULT 'MEDIA',
    estado ENUM('PENDIENTE', 'EN_GESTION', 'CITADA', 'ATENDIDA', 'CERRADA') DEFAULT 'PENDIENTE',
    fecha_ingreso DATE,
    fuente_dato VARCHAR(50),                     -- MANUAL, HIS, EHR, etc.
    hipotesis_dx TEXT,
    intentos_contacto INT DEFAULT 0,
    es_oncologico TINYINT DEFAULT 0,
    anualidad YEAR,
    creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_paciente (paciente_id),
    INDEX idx_estado (estado),
    INDEX idx_prioridad (prioridad),
    INDEX idx_fecha_ingreso (fecha_ingreso),
    INDEX idx_tipo_lista (tipo_lista),
    INDEX idx_especialidad (especialidad_id)
);

-- ============================================================================
-- 3. TABLA: Gestiones de Contacto
-- ============================================================================

CREATE TABLE IF NOT EXISTS lista_espera_gestiones_contacto (
    id INT AUTO_INCREMENT PRIMARY KEY,
    interconsulta_id INT NOT NULL,
    resultado_contacto VARCHAR(100),             -- Contactado, Rechazo, No responde, etc.
    fecha_gestion DATE,
    hora_gestion TIME,
    observaciones TEXT,
    usuario_id INT,
    creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (interconsulta_id) REFERENCES lista_espera_interconsultas(id),
    INDEX idx_interconsulta (interconsulta_id),
    INDEX idx_fecha_gestion (fecha_gestion)
);

-- ============================================================================
-- 4. TABLA: Fichas de Egreso (Norma 118 MINSAL)
-- ============================================================================

CREATE TABLE IF NOT EXISTS lista_espera_fichas_egreso (
    id INT AUTO_INCREMENT PRIMARY KEY,
    interconsulta_id INT NOT NULL,
    codigo_egreso VARCHAR(50),                   -- 1, 0, X
    fecha_egreso DATE,
    observaciones TEXT,
    creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (interconsulta_id) REFERENCES lista_espera_interconsultas(id),
    INDEX idx_interconsulta (interconsulta_id)
);

-- ============================================================================
-- 5. TABLA: Sincronización (Auditoría de Sync)
-- ============================================================================

CREATE TABLE IF NOT EXISTS lista_espera_sincronizacion_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    origen VARCHAR(50),                         -- SICOCH, HIS, EHR
    registros_nuevos INT DEFAULT 0,
    registros_actualizados INT DEFAULT 0,
    registros_eliminados INT DEFAULT 0,
    duracion_ms INT,
    estado VARCHAR(20),                         -- OK, ERROR
    mensaje TEXT,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_origen (origen),
    INDEX idx_timestamp (timestamp)
);

-- ============================================================================
-- 6. TABLA: Cache de Reportes
-- ============================================================================

CREATE TABLE IF NOT EXISTS lista_espera_reportes_cache (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre_reporte VARCHAR(100),
    datos_json LONGTEXT,                        -- Datos serializados
    fecha_generacion DATETIME,
    fecha_expiracion DATETIME,
    INDEX idx_nombre (nombre_reporte),
    INDEX idx_expiracion (fecha_expiracion)
);

-- ============================================================================
-- 7. TABLA: Alertas de Espera
-- ============================================================================

CREATE TABLE IF NOT EXISTS lista_espera_alertas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    interconsulta_id INT NOT NULL,
    tipo_alerta VARCHAR(50),                    -- ESPERA_EXCESIVA, SIN_CONTACTO, etc.
    dias_espera INT,
    estado_alerta ENUM('ACTIVA', 'RESUELTA', 'IGNORADA') DEFAULT 'ACTIVA',
    fecha_alerta DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_resolucion DATETIME,
    creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (interconsulta_id) REFERENCES lista_espera_interconsultas(id),
    INDEX idx_interconsulta (interconsulta_id),
    INDEX idx_estado (estado_alerta)
);

-- ============================================================================
-- 8. TABLA: Auditoría de Acciones
-- ============================================================================

CREATE TABLE IF NOT EXISTS lista_espera_auditoria (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT,
    tabla_afectada VARCHAR(100),
    registro_id INT,
    accion VARCHAR(50),                         -- INSERT, UPDATE, DELETE
    datos_anteriores JSON,
    datos_nuevos JSON,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    INDEX idx_usuario (usuario_id),
    INDEX idx_tabla (tabla_afectada),
    INDEX idx_timestamp (timestamp)
);

-- ============================================================================
-- DATOS INICIALES
-- ============================================================================

-- Usuario admin por defecto (cambiar en producción)
INSERT IGNORE INTO usuarios (usuario, nombre, email, password_hash, rol)
VALUES (
    'admin',
    'Administrador SIGLECH',
    'admin@salud.cl',
    '$argon2id$v=19$m=2048,t=4,p=3$a29xR0I0RVlHQmZkU1B1Yg$5Jn2q1J0R1C5vV8p2L9d9q3x7z0w8e1r4t6y9u2i5o',
    'admin'
);

-- ============================================================================
-- VISTAS ÚTILES
-- ============================================================================

-- Vista: Interconsultas con datos del paciente
CREATE OR REPLACE VIEW vw_lista_espera_completa AS
SELECT
    li.id,
    li.folio_sic,
    li.paciente_id,
    li.tipo_lista,
    li.prioridad,
    li.estado,
    li.fecha_ingreso,
    DATEDIFF(CURDATE(), li.fecha_ingreso) AS dias_espera,
    li.hipotesis_dx,
    li.intentos_contacto,
    li.creado_en,
    li.actualizado_en
FROM lista_espera_interconsultas li
ORDER BY li.fecha_ingreso ASC;

-- Vista: Estadísticas por categoría
CREATE OR REPLACE VIEW vw_estadisticas_categorias AS
SELECT
    tipo_lista,
    COUNT(*) AS total,
    SUM(CASE WHEN estado = 'PENDIENTE' THEN 1 ELSE 0 END) AS pendientes,
    SUM(CASE WHEN estado = 'EN_GESTION' THEN 1 ELSE 0 END) AS en_gestion,
    SUM(CASE WHEN estado = 'ATENDIDA' THEN 1 ELSE 0 END) AS atendidas,
    ROUND(AVG(DATEDIFF(CURDATE(), fecha_ingreso))) AS dias_promedio
FROM lista_espera_interconsultas
GROUP BY tipo_lista;

-- ============================================================================
-- INDICES ADICIONALES PARA PERFORMANCE
-- ============================================================================

CREATE INDEX idx_lei_folio ON lista_espera_interconsultas(folio_sic);
CREATE INDEX idx_lei_estado_fecha ON lista_espera_interconsultas(estado, fecha_ingreso);
CREATE INDEX idx_lei_prioridad_estado ON lista_espera_interconsultas(prioridad, estado);
