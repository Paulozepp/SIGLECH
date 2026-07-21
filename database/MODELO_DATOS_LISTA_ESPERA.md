# Modelo de Datos — Lista de Espera SIGLECH (CNE / IQ / PROC)

**Fecha**: 2026-07-20
**Estado**: Dimensiones + FK aplicadas y verificadas en el ambiente local. ETL a SICOCH diseñado y probado en dry-run, pendiente de ejecutar en la estación de desarrollo de la oficina (con la BD `sicoch` real de ~290.000 pacientes).

---

## 1. Arquitectura de 3 capas

```
┌─────────────────────────────────────────────────────────────┐
│ 1. STAGING / BODEGA HISTÓRICA (BD: siglech)                  │
│    demanda_cne / demanda_iq / demanda_proc (447.546 filas)   │
│    + dim_establecimiento / dim_prestacion_cne_iq /            │
│      dim_maestro_prestacion / dim_causal_salida / dim_cie10 / │
│      dim_tipo_lista / dim_prestacion_ges                      │
│    Carga: masiva inicial (Python, ya hecha) + incremental     │
│    (módulo PHP modules/demanda_le/, vía navegador)            │
└─────────────────────┬───────────────────────────────────────┘
                       │ ETL (etl_lista_espera_a_sicoch.py)
                       │ selecciona ESTADO='VIGENTE' (72.884 filas)
                       ▼
┌─────────────────────────────────────────────────────────────┐
│ 2. OPERACIONAL REAL (BD: sicoch)                              │
│    interconsultas (ya existía, 24 filas, fuente_dato='SIGTE') │
│    pacientes / especialidades / establecimientos              │
│    Es la tabla que usa el resto de la app SICOCH para         │
│    gestión clínica real.                                      │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│ 3. GESTIÓN LOCAL SIGLECH (BD: sicoch_referencia)               │
│    lista_espera_interconsultas (0 filas - vacía/vestigial),   │
│    gestiones_contacto, alertas, auditoría, sincronizacion_log │
│    NO es el destino del ETL (ver hallazgo #3 más abajo).      │
│    Se mantiene por ahora tal cual; su rol futuro (¿se usa,    │
│    se retira, se fusiona con sicoch.interconsultas?) queda    │
│    pendiente de decisión.                                     │
└─────────────────────────────────────────────────────────────┘
```

---

## 2. Hallazgos que corrigieron el diseño original

El modelo dimensional inicial (`Importacion/DIMENSIONES_LE/DIMENSIONES/08_Modelo_Relacional_LE.mermaid`,
hecho para Power BI) tenía algunas relaciones que **no coincidían con los datos reales** al
verificarlas contra las 447.546 filas ya cargadas. Quedan corregidas aquí:

| # | Se asumía | Se verificó (consulta real) | Corrección aplicada |
|---|---|---|---|
| 1 | `CNE.PRESTA_MIN` → `Prestaciones_CNE_IQ` (100% match, según el JSON de integridad original) | 0% match contra `dim_prestacion_cne_iq`; 99.93% match contra `dim_maestro_prestacion` | `CNE.PRESTA_MIN` FK → `dim_maestro_prestacion.codigo` (igual que PROC). Solo **IQ** usa `dim_prestacion_cne_iq` |
| 2 | `dim_prestacion_ges` (Tabla_Prestacion_Combinada) cubre las 3 listas | Match real: CNE 99.6%, IQ 100%, **PROC solo 0.07%** | El ETL asume PROC = `APOYO_DX` sin depender de GES/No-GES (el catálogo GES casi no cubre procedimientos) |
| 3 | La capa operacional de SIGLECH es `sicoch_referencia.lista_espera_interconsultas` (definida en `database/schema.sql`) | Esa tabla tiene **0 filas**. La tabla real y activa es `sicoch.interconsultas` (24 filas, ya con `fuente_dato='SIGTE'` en su ENUM) | El ETL apunta a `sicoch.interconsultas`, no a `sicoch_referencia.lista_espera_interconsultas` |
| 4 | `CIE10_HOMOLOGADO` tiene huérfanos por códigos raros | El 100% de los "huérfanos" era el valor literal `'-'` (placeholder de dato faltante en el export SIGTE) | Se limpió a `NULL` en las 3 tablas antes de aplicar el FK |
| 5 | Tipos de columna consistentes entre hechos y dimensiones | `TIPO_PREST`/`C_SALIDA` en `demanda_*` son `INT`, pero se habían diseñado `dim_tipo_lista.id`/`dim_causal_salida.id` como `TINYINT UNSIGNED` → MySQL rechaza el FK (errno 150) | Dimensiones ajustadas a `INT` para calzar con los datos ya cargados |

**Lección**: verificar integridad referencial contra la BD real (no solo contra el JSON de
análisis de la muestra) antes de aplicar cualquier FK sobre datos ya en producción.

---

## 3. Orden de instalación / migración

```bash
# 1. Dimensiones (schema)
mysql -u root siglech < database/migrations/001_dimensiones_lista_espera.sql

# 2. Poblar dimensiones desde los CSV
python Importacion/cargar_dimensiones.py

# 3. Limpiar huérfanos conocidos en demanda_cne/iq/proc (solo la primera vez,
#    ya ejecutado en este ambiente)
mysql -u root siglech < Importacion/limpiar_huerfanos_previo_fk.sql

# 4. Aplicar FK + vistas
mysql -u root siglech < database/migrations/004_fk_dimensiones_lista_espera.sql

# (003_unique_id_carga_incremental.sql ya estaba aplicado desde la sesión
#  anterior - UNIQUE KEY sobre _id en las 3 tablas, usado por cargar.php)
```

Verificación de huérfanos (debe dar 0 en las 21 filas) antes del paso 4:
`Importacion/verificar_huerfanos.sql`.

---

## 4. Tablas de dimensión (BD `siglech`)

| Tabla | Filas | Fuente CSV | PK |
|---|---|---|---|
| `dim_establecimiento` | 2.700 | Mantenedor_ Establecimiento.csv | `id` (Código Nuevo, 6 díg.) |
| `dim_prestacion_cne_iq` | 1.226 | Prestaciones_CNE_IQ.csv | `codigo_sigte` (usado solo por IQ) |
| `dim_maestro_prestacion` | 1.936 | Maestro_Prestacion.csv (+5 agregadas manualmente) | `codigo` (usado por CNE y PROC) |
| `dim_causal_salida` | 22 | Causales_Salidas.csv | `id` |
| `dim_tipo_lista` | 5 | fijo en el SQL (no viene de CSV) | `id` |
| `dim_cie10` | 14.226 | CIE10.csv | `codigo` |
| `dim_prestacion_ges` | 1.298 | Tabla_Prestacion_Combinada.csv | `codigo` (catálogo de apoyo, sin FK obligatoria) |

## 5. Tablas de hechos (BD `siglech`)

| Tabla | Filas | Vigentes (ESTADO='VIGENTE') |
|---|---|---|
| `demanda_cne` | 245.815 | 37.320 |
| `demanda_iq` | 31.690 | 6.207 |
| `demanda_proc` | 170.041 | 29.384 |
| **Total** | **447.546** | **72.884 (`vw_lista_espera_vigentes`)** |

Vistas: `vw_lista_espera_raw_unificada` (UNION ALL de las 3), `vw_lista_espera_vigentes`
(filtra `ESTADO='VIGENTE' AND F_SALIDA IS NULL`).

FK por tabla (7 cada una): `ESTAB_ORIG`/`ESTAB_DEST`/`E_OTOR_AT` → `dim_establecimiento`,
`C_SALIDA` → `dim_causal_salida`, `TIPO_PREST` → `dim_tipo_lista`, `CIE10_HOMOLOGADO` →
`dim_cie10`, `PRESTA_MIN` → `dim_maestro_prestacion` (CNE/PROC) o `dim_prestacion_cne_iq` (IQ).

---

## 6. ETL hacia la capa operacional (`Importacion/etl_lista_espera_a_sicoch.py`)

Toma `vw_lista_espera_vigentes` (72.884 filas en este ambiente) y hace upsert en
`sicoch.interconsultas`, resolviendo o creando sobre la marcha:

- **Paciente** (`sicoch.pacientes`): busca por RUN; si no existe, crea registro mínimo
  (`calidad_registro='BAJA'`, `n_fuentes=1`). *`pacientes.run` no tiene UNIQUE constraint*,
  se resuelve con `SELECT ... LIMIT 1`, no con upsert.
- **Especialidad** (`sicoch.especialidades`): busca por nombre exacto; si no existe, crea
  con código `SIGTE-<nombre>` y grupo `MEDICA`/`ODONTOLOGICA` según corresponda.
- **Establecimiento** (`sicoch.establecimientos`): busca por código `SIGTE-<id>`; si no
  existe, lo crea con tipo `OTRO`.
- **`tipo_lista`** (ENUM de 14 valores en `sicoch.interconsultas`): mapeo propuesto, **no es
  regla oficial MINSAL, falta validar con SDGA/equipo clínico**:
  - Oncológico (`dim_prestacion_ges.oncologica=1`) → `ONCOLOGICO` (prioridad sobre todo)
  - PROC → siempre `APOYO_DX`
  - CNE → `CN_ODONTO_GES`/`CN_ODONTO_NO_GES` (si odontológica) o `CN_GES`/`CN_NO_GES`
  - IQ → `IQ_MAYOR_GES`/`IQ_MAYOR_NO_GES`/`IQ_MENOR_GES`/`IQ_MENOR_NO_GES` según
    `dim_prestacion_cne_iq.complejidad` (Cirugía Mayor/Menor), fallback `CIRUGIA_GES`/`CIRUGIA_NO_GES`
  - **`ECICEP` y `CASO_COMPLEJO` no son derivables desde el export SIGTE** — quedan fuera
    del mapeo automático, se gestionan manualmente en SICOCH después de la carga.
- Idempotente por `folio_sic = SIGTE_ID` (sí tiene UNIQUE en `sicoch.interconsultas`).

### Cómo ejecutarlo mañana en la oficina

```bash
# 1. Primero, SIEMPRE en dry-run para ver qué haría (no escribe nada):
python Importacion/etl_lista_espera_a_sicoch.py

# 2. Revisar los contadores reportados (nuevas/actualizadas/pacientes creados/etc.)
#    En este ambiente de prueba (501 pacientes) casi todo sale "paciente nuevo".
#    Con la BD real (~290.000 pacientes) el % de match debería ser mucho más alto -
#    si sigue saliendo casi todo "nuevo" en la oficina, hay que investigar antes
#    de aplicar (podría indicar problema de formato de RUN, mayúsculas, etc.)

# 3. Prueba acotada antes de ir a producción completa:
python Importacion/etl_lista_espera_a_sicoch.py --ejecutar --limite 500

# 4. Ejecución completa:
python Importacion/etl_lista_espera_a_sicoch.py --ejecutar
```

**Antes de ejecutar en la oficina**: revisar con el equipo clínico/SDGA la tabla de mapeo
de `tipo_lista` de la sección anterior — es una propuesta razonada a partir de los datos
disponibles, no una clasificación oficial confirmada.

---

## 7. Pendientes / próximos pasos

- [ ] Ejecutar el ETL en dry-run contra la BD real de la oficina (~290K pacientes) y
      revisar el % de pacientes/especialidades/establecimientos que ya existen vs. nuevos
- [ ] Validar con SDGA/equipo clínico el mapeo de `tipo_lista` (especialmente IQ Mayor/Menor
      y el fallback `CIRUGIA_GES`/`CIRUGIA_NO_GES`)
- [ ] Decidir el rol de `sicoch_referencia.lista_espera_interconsultas` (vacía) - ¿se retira,
      se usa para otra cosa, se fusiona con `sicoch.interconsultas`?
- [ ] Definir estrategia para `ECICEP` y `CASO_COMPLEJO` (¿se detectan por otra vía, se
      marcan manualmente después de la carga inicial?)
- [ ] Re-ejecutar `cargar_dimensiones.py` cada vez que se actualicen los CSV de
      DIMENSIONES_LE (nuevos establecimientos, prestaciones, etc.) - es idempotente
      (TRUNCATE + INSERT)
- [ ] Considerar agregar un cron/tarea periódica que corra el ETL automáticamente tras
      cada carga incremental vía `modules/demanda_le/cargar.php`
