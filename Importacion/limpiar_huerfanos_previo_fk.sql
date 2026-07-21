-- Limpieza de huérfanos ANTES de aplicar FK constraints en 004_fk_dimensiones.sql
-- Basado en verificación real contra los 447.546 registros ya cargados.

-- 1. CIE10_HOMOLOGADO='-' es un placeholder de "sin dato" del export SIGTE,
--    no un código CIE10 real (100% de los huérfanos en las 3 tablas es '-').
UPDATE demanda_cne  SET CIE10_HOMOLOGADO = NULL WHERE CIE10_HOMOLOGADO = '-';
UPDATE demanda_iq   SET CIE10_HOMOLOGADO = NULL WHERE CIE10_HOMOLOGADO = '-';
UPDATE demanda_proc SET CIE10_HOMOLOGADO = NULL WHERE CIE10_HOMOLOGADO = '-';

-- 2. Establecimiento 201396 (ESTAB_ORIG en 1 fila de CNE) no existe en el
--    mantenedor cargado - se agrega como registro mínimo en vez de perder
--    la trazabilidad del origen.
INSERT IGNORE INTO dim_establecimiento (id, nombre, activo, vigente)
VALUES (201396, 'SIN DATO EN MANTENEDOR - código 201396 (pendiente de completar)', 'SI', 'SI');

-- 3. 5 prestaciones reales usadas en CNE no estaban en Maestro_Prestacion.csv
--    (probablemente agregadas al catálogo SIGTE después del corte del export).
--    Se agregan con los datos disponibles en la propia fila de demanda para
--    no perder 178 registros de PRESTA_MIN.
INSERT IGNORE INTO dim_maestro_prestacion (codigo, prestacion, especialidad, tipo_especialidad)
SELECT DISTINCT PRESTA_MIN, PRESTA_EST_ESTANDAR, ESPECIALIDAD_ESTANDAR, 'Médica'
FROM demanda_cne
WHERE PRESTA_MIN IN ('09-016', '07-071', '07-070', '07-035', '07-050');

-- 4. E_OTOR_AT con formato corrupto (ej "133,375.00", dígitos de más/menos) -
--    son errores de exportación de SIGTE, no establecimientos reales.
--    Se limpian a NULL (campo ya es opcional: "quién atendió").
UPDATE demanda_cne t
LEFT JOIN dim_establecimiento d ON t.E_OTOR_AT = d.id
SET t.E_OTOR_AT = NULL
WHERE t.E_OTOR_AT IS NOT NULL AND d.id IS NULL;

UPDATE demanda_iq t
LEFT JOIN dim_establecimiento d ON t.E_OTOR_AT = d.id
SET t.E_OTOR_AT = NULL
WHERE t.E_OTOR_AT IS NOT NULL AND d.id IS NULL;

UPDATE demanda_proc t
LEFT JOIN dim_establecimiento d ON t.E_OTOR_AT = d.id
SET t.E_OTOR_AT = NULL
WHERE t.E_OTOR_AT IS NOT NULL AND d.id IS NULL;
