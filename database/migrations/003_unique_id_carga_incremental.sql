-- 003_unique_id_carga_incremental.sql
-- Agrega UNIQUE KEY sobre _id en demanda_cne/iq/proc para permitir
-- cargas incrementales de CSV (INSERT ... ON DUPLICATE KEY UPDATE)
-- sin duplicar filas ya importadas.

ALTER TABLE demanda_cne  ADD UNIQUE KEY uq_demanda_cne_id  (_id);
ALTER TABLE demanda_iq   ADD UNIQUE KEY uq_demanda_iq_id   (_id);
ALTER TABLE demanda_proc ADD UNIQUE KEY uq_demanda_proc_id (_id);
