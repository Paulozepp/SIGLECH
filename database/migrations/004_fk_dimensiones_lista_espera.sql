-- ============================================================================
-- SIGLECH - FK reales entre demanda_cne/iq/proc y las dimensiones
-- Requiere (en este orden):
--   1. 001_dimensiones_lista_espera.sql  (crea dim_*)
--   2. Importacion/cargar_dimensiones.py  (puebla dim_* desde CSV)
--   3. Importacion/limpiar_huerfanos_previo_fk.sql  (limpia 447K filas ya
--      cargadas: CIE10='-' -> NULL, E_OTOR_AT con formato corrupto -> NULL,
--      agrega 1 establecimiento y 5 prestaciones faltantes al catálogo)
-- Verificado 0 huérfanos en las 21 relaciones tras los 3 pasos anteriores.
--
-- HALLAZGO IMPORTANTE (corrige el modelo mermaid original):
-- PRESTA_MIN en CNE hace match con dim_maestro_prestacion (99.9%), NO con
-- dim_prestacion_cne_iq como sugería el diagrama inicial. Solo IQ usa
-- dim_prestacion_cne_iq. PROC ya usaba dim_maestro_prestacion, confirmado.
--   CNE.PRESTA_MIN  -> dim_maestro_prestacion.codigo
--   IQ.PRESTA_MIN   -> dim_prestacion_cne_iq.codigo_sigte
--   PROC.PRESTA_MIN -> dim_maestro_prestacion.codigo
-- ============================================================================

-- ----------------------------------------------------------------------------
-- DEMANDA_CNE
-- ----------------------------------------------------------------------------
ALTER TABLE demanda_cne
    ADD CONSTRAINT fk_cne_estab_orig FOREIGN KEY (ESTAB_ORIG) REFERENCES dim_establecimiento(id) ON DELETE RESTRICT,
    ADD CONSTRAINT fk_cne_estab_dest FOREIGN KEY (ESTAB_DEST) REFERENCES dim_establecimiento(id) ON DELETE RESTRICT,
    ADD CONSTRAINT fk_cne_estab_otor FOREIGN KEY (E_OTOR_AT) REFERENCES dim_establecimiento(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_cne_causal FOREIGN KEY (C_SALIDA) REFERENCES dim_causal_salida(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_cne_tipo_lista FOREIGN KEY (TIPO_PREST) REFERENCES dim_tipo_lista(id) ON DELETE RESTRICT,
    ADD CONSTRAINT fk_cne_cie10 FOREIGN KEY (CIE10_HOMOLOGADO) REFERENCES dim_cie10(codigo) ON DELETE SET NULL,
    ADD CONSTRAINT fk_cne_prestacion FOREIGN KEY (PRESTA_MIN) REFERENCES dim_maestro_prestacion(codigo) ON DELETE RESTRICT,
    ADD INDEX idx_estab_orig (ESTAB_ORIG),
    ADD INDEX idx_estab_dest (ESTAB_DEST);

-- ----------------------------------------------------------------------------
-- DEMANDA_IQ
-- ----------------------------------------------------------------------------
ALTER TABLE demanda_iq
    ADD CONSTRAINT fk_iq_estab_orig FOREIGN KEY (ESTAB_ORIG) REFERENCES dim_establecimiento(id) ON DELETE RESTRICT,
    ADD CONSTRAINT fk_iq_estab_dest FOREIGN KEY (ESTAB_DEST) REFERENCES dim_establecimiento(id) ON DELETE RESTRICT,
    ADD CONSTRAINT fk_iq_estab_otor FOREIGN KEY (E_OTOR_AT) REFERENCES dim_establecimiento(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_iq_causal FOREIGN KEY (C_SALIDA) REFERENCES dim_causal_salida(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_iq_tipo_lista FOREIGN KEY (TIPO_PREST) REFERENCES dim_tipo_lista(id) ON DELETE RESTRICT,
    ADD CONSTRAINT fk_iq_cie10 FOREIGN KEY (CIE10_HOMOLOGADO) REFERENCES dim_cie10(codigo) ON DELETE SET NULL,
    ADD CONSTRAINT fk_iq_prestacion FOREIGN KEY (PRESTA_MIN) REFERENCES dim_prestacion_cne_iq(codigo_sigte) ON DELETE RESTRICT,
    ADD INDEX idx_estab_orig (ESTAB_ORIG),
    ADD INDEX idx_estab_dest (ESTAB_DEST);

-- ----------------------------------------------------------------------------
-- DEMANDA_PROC
-- ----------------------------------------------------------------------------
ALTER TABLE demanda_proc
    ADD CONSTRAINT fk_proc_estab_orig FOREIGN KEY (ESTAB_ORIG) REFERENCES dim_establecimiento(id) ON DELETE RESTRICT,
    ADD CONSTRAINT fk_proc_estab_dest FOREIGN KEY (ESTAB_DEST) REFERENCES dim_establecimiento(id) ON DELETE RESTRICT,
    ADD CONSTRAINT fk_proc_estab_otor FOREIGN KEY (E_OTOR_AT) REFERENCES dim_establecimiento(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_proc_causal FOREIGN KEY (C_SALIDA) REFERENCES dim_causal_salida(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_proc_tipo_lista FOREIGN KEY (TIPO_PREST) REFERENCES dim_tipo_lista(id) ON DELETE RESTRICT,
    ADD CONSTRAINT fk_proc_cie10 FOREIGN KEY (CIE10_HOMOLOGADO) REFERENCES dim_cie10(codigo) ON DELETE SET NULL,
    ADD CONSTRAINT fk_proc_prestacion FOREIGN KEY (PRESTA_MIN) REFERENCES dim_maestro_prestacion(codigo) ON DELETE RESTRICT,
    ADD INDEX idx_estab_orig (ESTAB_ORIG),
    ADD INDEX idx_estab_dest (ESTAB_DEST);

-- ----------------------------------------------------------------------------
-- VISTAS: consulta cruzada por RUN/especialidad/estado sin importar tipo,
-- y subconjunto de casos vigentes (para el ETL hacia lista_espera_interconsultas)
-- ----------------------------------------------------------------------------
CREATE OR REPLACE VIEW vw_lista_espera_raw_unificada AS
SELECT 'CNE' AS tipo_origen, t.* FROM demanda_cne t
UNION ALL
SELECT 'IQ' AS tipo_origen, t.* FROM demanda_iq t
UNION ALL
SELECT 'PROC' AS tipo_origen, t.* FROM demanda_proc t;

CREATE OR REPLACE VIEW vw_lista_espera_vigentes AS
SELECT * FROM vw_lista_espera_raw_unificada
WHERE ESTADO = 'VIGENTE' AND F_SALIDA IS NULL;
