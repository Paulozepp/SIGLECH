#!/usr/bin/env python3
"""
ETL: Lista de Espera SIGTE (BD siglech, staging) -> sicoch.interconsultas (BD
operacional real y activa - confirmado con fuente_dato='SIGTE' ya en uso).

IMPORTANTE - LEER ANTES DE EJECUTAR EN LA ESTACIÓN DE DESARROLLO DE LA OFICINA:
- sicoch.pacientes / especialidades / establecimientos están sub-poblados en
  el ambiente donde se escribió este script (501/5/1 filas) frente al universo
  real de SIGTE (cientos de miles de RUN, ~50 especialidades, ~2700
  establecimientos). En el ambiente de oficina, sicoch.pacientes tiene ~290.000
  filas reales: revisar de nuevo el % de RUN que ya existen antes de asumir
  cuántos pacientes nuevos se crearán.
- El mapeo TIPO_PREST/GES/complejidad -> ENUM tipo_lista (más abajo, función
  determinar_tipo_lista) es una PROPUESTA razonada a partir de los datos
  disponibles, no una regla oficial MINSAL. ECICEP y CASO_COMPLEJO no son
  derivables desde el export SIGTE y quedan fuera del mapeo automático.
- Por defecto corre en --dry-run (no escribe nada, solo reporta). Usar
  --ejecutar para aplicar cambios de verdad.
- sicoch.pacientes.run NO tiene UNIQUE constraint (puede haber duplicados
  existentes) - se resuelve con SELECT ... LIMIT 1, no con upsert por run.
- Idempotente por folio_sic (SIGTE_ID) - sicoch.interconsultas.folio_sic sí es
  UNIQUE, así que re-ejecutar el ETL es seguro (usa INSERT...ON DUPLICATE KEY).

Uso:
    python etl_lista_espera_a_sicoch.py                 # dry-run (default)
    python etl_lista_espera_a_sicoch.py --ejecutar       # aplica cambios
    python etl_lista_espera_a_sicoch.py --ejecutar --limite 500   # prueba acotada
"""

import argparse
import sys
import time
import mysql.connector
from mysql.connector import Error


class Colores:
    VERDE = '\033[92m'
    ROJO = '\033[91m'
    AMARILLO = '\033[93m'
    AZUL = '\033[94m'
    RESET = '\033[0m'
    BOLD = '\033[1m'


def log(msg, color=Colores.RESET):
    print(f"{color}{msg}{Colores.RESET}")


# ============================================================================
# Queries de origen (siglech) - una por tipo, cada una con los JOIN propios
# a los catálogos correctos (ver hallazgos: CNE/PROC -> dim_maestro_prestacion,
# IQ -> dim_prestacion_cne_iq; dim_prestacion_ges matchea bien CNE/IQ pero
# casi nada de PROC).
# ============================================================================

SQL_ORIGEN = {
    'CNE': """
        SELECT t.SIGTE_ID, t.RUN, t.DV, t.NOMBRES, t.PRIMER_APELLIDO, t.SEGUNDO_APELLIDO,
               t.FECHA_NAC, t.SEXO, t.PREVISION, t.CIUDAD AS COMUNA_NOMBRE, t.F_ENTRADA,
               t.ESPECIALIDAD_ESTANDAR, t.ESTAB_ORIG, t.ESTAB_DEST, t.NOMBRE_ORIG, t.NOMBRE_DEST,
               mp.tipo_especialidad, ges.tipo_le, ges.oncologica, NULL AS complejidad,
               'CNE' AS tipo_origen
        FROM demanda_cne t
        LEFT JOIN dim_maestro_prestacion mp ON t.PRESTA_MIN = mp.codigo
        LEFT JOIN dim_prestacion_ges ges ON t.PRESTA_MIN = ges.codigo
        WHERE t.ESTADO = 'VIGENTE' AND t.F_SALIDA IS NULL
    """,
    'IQ': """
        SELECT t.SIGTE_ID, t.RUN, t.DV, t.NOMBRES, t.PRIMER_APELLIDO, t.SEGUNDO_APELLIDO,
               t.FECHA_NAC, t.SEXO, t.PREVISION, t.CIUDAD AS COMUNA_NOMBRE, t.F_ENTRADA,
               t.ESPECIALIDAD_ESTANDAR, t.ESTAB_ORIG, t.ESTAB_DEST, t.NOMBRE_ORIG, t.NOMBRE_DEST,
               'Médica' AS tipo_especialidad, ges.tipo_le, ges.oncologica, pc.complejidad,
               'IQ' AS tipo_origen
        FROM demanda_iq t
        LEFT JOIN dim_prestacion_cne_iq pc ON t.PRESTA_MIN = pc.codigo_sigte
        LEFT JOIN dim_prestacion_ges ges ON t.PRESTA_MIN = ges.codigo
        WHERE t.ESTADO = 'VIGENTE' AND t.F_SALIDA IS NULL
    """,
    'PROC': """
        SELECT t.SIGTE_ID, t.RUN, t.DV, t.NOMBRES, t.PRIMER_APELLIDO, t.SEGUNDO_APELLIDO,
               t.FECHA_NAC, t.SEXO, t.PREVISION, t.CIUDAD AS COMUNA_NOMBRE, t.F_ENTRADA,
               t.ESPECIALIDAD_ESTANDAR, t.ESTAB_ORIG, t.ESTAB_DEST, t.NOMBRE_ORIG, t.NOMBRE_DEST,
               mp.tipo_especialidad, NULL AS tipo_le, 0 AS oncologica, NULL AS complejidad,
               'PROC' AS tipo_origen
        FROM demanda_proc t
        LEFT JOIN dim_maestro_prestacion mp ON t.PRESTA_MIN = mp.codigo
        WHERE t.ESTADO = 'VIGENTE' AND t.F_SALIDA IS NULL
    """,
}

PREVISION_TEXTO = {1: 'FONASA A', 2: 'FONASA B', 3: 'FONASA C', 4: 'FONASA D', 99: 'OTRO/DESCONOCIDO'}
SEXO_TEXTO = {1: 'M', 2: 'F'}


def determinar_tipo_lista(fila):
    """Propuesta de mapeo hacia sicoch.interconsultas.tipo_lista (14 valores).
    Ver cabecera del archivo: no es regla oficial MINSAL, validar con SDGA."""
    if fila['oncologica']:
        return 'ONCOLOGICO'

    tipo_origen = fila['tipo_origen']
    ges = (fila['tipo_le'] or '').strip().upper()
    es_ges = ges == 'GES'

    if tipo_origen == 'PROC':
        return 'APOYO_DX'

    if tipo_origen == 'CNE':
        es_odonto = (fila['tipo_especialidad'] or '').strip().lower().startswith('odont')
        if es_odonto:
            return 'CN_ODONTO_GES' if es_ges else 'CN_ODONTO_NO_GES'
        return 'CN_GES' if es_ges else 'CN_NO_GES'

    if tipo_origen == 'IQ':
        complejidad = (fila['complejidad'] or '').strip().lower()
        if 'mayor' in complejidad:
            return 'IQ_MAYOR_GES' if es_ges else 'IQ_MAYOR_NO_GES'
        if 'menor' in complejidad:
            return 'IQ_MENOR_GES' if es_ges else 'IQ_MENOR_NO_GES'
        return 'CIRUGIA_GES' if es_ges else 'CIRUGIA_NO_GES'

    return 'CN_NO_GES'  # fallback conservador, no debería alcanzarse


class ResolverCatalogos:
    """Resuelve o crea paciente/especialidad/establecimiento en sicoch.
    Cachea especialidad/establecimiento en memoria (pocos valores únicos);
    paciente se resuelve por SELECT individual (universo grande, no cacheable)."""

    def __init__(self, cursor_sicoch, dry_run):
        self.cur = cursor_sicoch
        self.dry_run = dry_run
        self.cache_especialidad = {}
        self.cache_establecimiento = {}
        self.pacientes_creados = 0
        self.especialidades_creadas = 0
        self.establecimientos_creados = 0

    def resolver_paciente(self, fila):
        run = (fila['RUN'] or '').strip()
        if not run:
            return None

        self.cur.execute("SELECT id FROM pacientes WHERE run = %s LIMIT 1", (run,))
        r = self.cur.fetchone()
        if r:
            return r[0]

        if self.dry_run:
            self.pacientes_creados += 1
            return -1  # id ficticio para reporte en dry-run

        sexo = SEXO_TEXTO.get(int(fila['SEXO']) if fila['SEXO'] else None, None)
        prevision = PREVISION_TEXTO.get(int(fila['PREVISION']) if fila['PREVISION'] else None, None)

        self.cur.execute("""
            INSERT INTO pacientes
            (run, dv, primer_apellido, segundo_apellido, nombres, fecha_nacimiento,
             sexo, comuna, prevision, calidad_registro, n_fuentes)
            VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, 'BAJA', 1)
        """, (
            run, fila['DV'], fila['PRIMER_APELLIDO'], fila['SEGUNDO_APELLIDO'],
            fila['NOMBRES'], fila['FECHA_NAC'], sexo, fila['COMUNA_NOMBRE'], prevision,
        ))
        self.pacientes_creados += 1
        return self.cur.lastrowid

    def resolver_especialidad(self, nombre, tipo_especialidad):
        if not nombre:
            return None
        clave = nombre.strip().upper()
        if clave in self.cache_especialidad:
            return self.cache_especialidad[clave]

        self.cur.execute("SELECT id FROM especialidades WHERE UPPER(nombre) = %s LIMIT 1", (clave,))
        r = self.cur.fetchone()
        if r:
            self.cache_especialidad[clave] = r[0]
            return r[0]

        if self.dry_run:
            self.especialidades_creadas += 1
            self.cache_especialidad[clave] = -1
            return -1

        grupo = 'ODONTOLOGICA' if (tipo_especialidad or '').lower().startswith('odont') else 'MEDICA'
        codigo = 'SIGTE-' + clave[:15].replace(' ', '_')
        self.cur.execute(
            "INSERT INTO especialidades (codigo, nombre, grupo) VALUES (%s, %s, %s)",
            (codigo, nombre.strip()[:120], grupo)
        )
        self.especialidades_creadas += 1
        nuevo_id = self.cur.lastrowid
        self.cache_especialidad[clave] = nuevo_id
        return nuevo_id

    def resolver_establecimiento(self, codigo_siglech, nombre):
        if not codigo_siglech:
            return None
        if codigo_siglech in self.cache_establecimiento:
            return self.cache_establecimiento[codigo_siglech]

        codigo_sicoch = f"SIGTE-{codigo_siglech}"
        self.cur.execute("SELECT id FROM establecimientos WHERE codigo = %s LIMIT 1", (codigo_sicoch,))
        r = self.cur.fetchone()
        if r:
            self.cache_establecimiento[codigo_siglech] = r[0]
            return r[0]

        if self.dry_run:
            self.establecimientos_creados += 1
            self.cache_establecimiento[codigo_siglech] = -1
            return -1

        self.cur.execute(
            "INSERT INTO establecimientos (codigo, nombre, tipo) VALUES (%s, %s, 'OTRO')",
            (codigo_sicoch, (nombre or codigo_sicoch)[:150])
        )
        self.establecimientos_creados += 1
        nuevo_id = self.cur.lastrowid
        self.cache_establecimiento[codigo_siglech] = nuevo_id
        return nuevo_id


def procesar_tipo(tipo, cur_siglech, cur_sicoch, resolver, dry_run, limite):
    sql = SQL_ORIGEN[tipo]
    if limite:
        sql += f" LIMIT {int(limite)}"

    cur_siglech.execute(sql)
    columnas = [d[0] for d in cur_siglech.description]

    nuevas, actualizadas, saltadas = 0, 0, 0

    sql_upsert = """
        INSERT INTO interconsultas
        (folio_sic, paciente_id, especialidad_id, establecimiento_origen_id,
         establecimiento_destino_id, tipo_lista, fecha_ingreso, fuente_dato,
         es_oncologico, estado)
        VALUES (%s, %s, %s, %s, %s, %s, %s, 'SIGTE', %s, 'PENDIENTE')
        ON DUPLICATE KEY UPDATE
            especialidad_id = VALUES(especialidad_id),
            tipo_lista = VALUES(tipo_lista),
            es_oncologico = VALUES(es_oncologico)
    """

    for row in cur_siglech.fetchall():
        fila = dict(zip(columnas, row))

        paciente_id = resolver.resolver_paciente(fila)
        if paciente_id is None:
            saltadas += 1
            continue

        especialidad_id = resolver.resolver_especialidad(
            fila['ESPECIALIDAD_ESTANDAR'], fila['tipo_especialidad']
        )
        estab_origen_id = resolver.resolver_establecimiento(fila['ESTAB_ORIG'], fila['NOMBRE_ORIG'])
        estab_destino_id = resolver.resolver_establecimiento(fila['ESTAB_DEST'], fila['NOMBRE_DEST'])
        tipo_lista = determinar_tipo_lista(fila)
        folio = fila['SIGTE_ID'] or f"{tipo}-{fila['RUN']}-{fila['F_ENTRADA']}"

        if dry_run:
            nuevas += 1
            continue

        cur_sicoch.execute(sql_upsert, (
            folio, paciente_id, especialidad_id, estab_origen_id, estab_destino_id,
            tipo_lista, fila['F_ENTRADA'], 1 if fila['oncologica'] else 0,
        ))
        if cur_sicoch.rowcount == 1:
            nuevas += 1
        else:
            actualizadas += 1

    return nuevas, actualizadas, saltadas


def main():
    parser = argparse.ArgumentParser(description="ETL Lista de Espera SIGTE -> sicoch.interconsultas")
    parser.add_argument('--ejecutar', action='store_true', help='Aplica cambios reales (default: dry-run)')
    parser.add_argument('--limite', type=int, default=None, help='Límite de filas por tipo (para pruebas)')
    parser.add_argument('--tipos', default='CNE,IQ,PROC', help='Tipos a procesar, separados por coma')
    args = parser.parse_args()

    dry_run = not args.ejecutar
    tipos = [t.strip().upper() for t in args.tipos.split(',')]

    log(f"\n{'MODO DRY-RUN (no se escribe nada)' if dry_run else 'MODO EJECUCIÓN REAL'}",
        Colores.AMARILLO if dry_run else Colores.ROJO)
    log("ETL Lista de Espera SIGTE -> sicoch.interconsultas\n", Colores.BOLD)

    try:
        conn_siglech = mysql.connector.connect(host="localhost", user="root", password="", database="siglech")
        conn_sicoch = mysql.connector.connect(host="localhost", user="root", password="", database="sicoch", autocommit=False)
    except Error as e:
        log(f"Error de conexión: {e}", Colores.ROJO)
        sys.exit(1)

    cur_siglech = conn_siglech.cursor()
    cur_sicoch = conn_sicoch.cursor()
    resolver = ResolverCatalogos(cur_sicoch, dry_run)

    inicio = time.time()
    totales = {'nuevas': 0, 'actualizadas': 0, 'saltadas': 0}

    try:
        for tipo in tipos:
            log(f"Procesando {tipo}...", Colores.AZUL)
            n, a, s = procesar_tipo(tipo, cur_siglech, cur_sicoch, resolver, dry_run, args.limite)
            totales['nuevas'] += n
            totales['actualizadas'] += a
            totales['saltadas'] += s
            log(f"  {tipo}: {n} nuevas, {a} actualizadas, {s} saltadas (sin RUN)", Colores.VERDE)

        if not dry_run:
            conn_sicoch.commit()

            # Log de sincronización (tabla ya existe en sicoch_referencia)
            conn_ref = mysql.connector.connect(host="localhost", user="root", password="", database="sicoch_referencia")
            cur_ref = conn_ref.cursor()
            cur_ref.execute("""
                INSERT INTO lista_espera_sincronizacion_log
                (origen, registros_nuevos, registros_actualizados, duracion_ms, estado, mensaje)
                VALUES ('SIGTE', %s, %s, %s, 'OK', %s)
            """, (
                totales['nuevas'], totales['actualizadas'],
                int((time.time() - inicio) * 1000),
                f"tipos={','.join(tipos)} saltadas={totales['saltadas']}",
            ))
            conn_ref.commit()
            cur_ref.close()
            conn_ref.close()
        else:
            conn_sicoch.rollback()

    except Exception as e:
        conn_sicoch.rollback()
        log(f"Error durante el ETL, rollback aplicado: {e}", Colores.ROJO)
        raise
    finally:
        cur_siglech.close()
        cur_sicoch.close()
        conn_siglech.close()
        conn_sicoch.close()

    duracion = time.time() - inicio
    log(f"\n{'='*60}", Colores.BOLD)
    log(f"Interconsultas nuevas:      {totales['nuevas']:,}", Colores.VERDE)
    log(f"Interconsultas actualizadas:{totales['actualizadas']:,}", Colores.VERDE)
    log(f"Saltadas (sin RUN):         {totales['saltadas']:,}", Colores.AMARILLO)
    log(f"Pacientes creados:          {resolver.pacientes_creados:,}", Colores.AZUL)
    log(f"Especialidades creadas:     {resolver.especialidades_creadas:,}", Colores.AZUL)
    log(f"Establecimientos creados:   {resolver.establecimientos_creados:,}", Colores.AZUL)
    log(f"Duración: {duracion:.2f}s")
    if dry_run:
        log("\nEsto fue un DRY-RUN. Nada se escribió. Usar --ejecutar para aplicar.", Colores.AMARILLO)


if __name__ == "__main__":
    main()
