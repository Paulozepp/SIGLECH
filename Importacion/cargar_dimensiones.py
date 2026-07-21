#!/usr/bin/env python3
"""
Cargador de Dimensiones (catálogos) - Lista de Espera SIGLECH
Puebla dim_establecimiento, dim_prestacion_cne_iq, dim_maestro_prestacion,
dim_causal_salida, dim_cie10, dim_prestacion_ges desde los CSV de
DIMENSIONES_LE/DIMENSIONES/. Requiere 001_dimensiones_lista_espera.sql ya
ejecutado (crea las tablas y puebla dim_tipo_lista con datos fijos).

Idempotente: usa TRUNCATE + INSERT en cada corrida (los catálogos son
pequeños, recargar completo es más simple y seguro que hacer diffs).
"""

import pandas as pd
import mysql.connector
from mysql.connector import Error
from pathlib import Path
import sys

BASE = Path(__file__).parent / "DIMENSIONES_LE" / "DIMENSIONES"


class ColoresTerminal:
    VERDE = '\033[92m'
    ROJO = '\033[91m'
    AMARILLO = '\033[93m'
    AZUL = '\033[94m'
    RESET = '\033[0m'
    BOLD = '\033[1m'


def log_exito(msg):
    print(f"{ColoresTerminal.VERDE}OK {msg}{ColoresTerminal.RESET}")


def log_error(msg):
    print(f"{ColoresTerminal.ROJO}ERROR {msg}{ColoresTerminal.RESET}")


def log_info(msg):
    print(f"{ColoresTerminal.AZUL}-- {msg}{ColoresTerminal.RESET}")


def leer_csv(nombre_archivo):
    ruta = BASE / nombre_archivo
    if not ruta.exists():
        raise FileNotFoundError(f"No se encontró {ruta}")
    return pd.read_csv(ruta, sep=';', encoding='utf-8-sig', dtype=str, low_memory=False)


def a_fecha(valor):
    """DD-MM-YYYY -> date, o None"""
    if valor is None or pd.isna(valor) or str(valor).strip() == '':
        return None
    dt = pd.to_datetime(valor, format='%d-%m-%Y', errors='coerce')
    return dt.date() if pd.notna(dt) else None


def a_int(valor):
    if valor is None or pd.isna(valor) or str(valor).strip() == '':
        return None
    try:
        return int(float(str(valor).replace(',', '.')))
    except (ValueError, TypeError):
        return None


def a_str(valor):
    if valor is None or pd.isna(valor):
        return None
    v = str(valor).strip()
    return v if v != '' else None


def cargar_establecimiento(cursor):
    log_info("Cargando dim_establecimiento (Mantenedor_ Establecimiento.csv)...")
    df = leer_csv("Mantenedor_ Establecimiento.csv")

    cursor.execute("TRUNCATE TABLE dim_establecimiento")

    sql = """
    INSERT INTO dim_establecimiento
    (id, codigo_antiguo, nombre, region_codigo, region_nombre, comuna_codigo,
     comuna_nombre, ss_codigo, ss_nombre, tipo_establecimiento, nivel_atencion,
     nivel_atencion_deis, dependencia_ejecucion, certificacion, hospital,
     especialidad, activo, vigente)
    VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
    """

    filas = []
    saltadas = 0
    for _, r in df.iterrows():
        id_ = a_int(r['Código Nuevo'])
        if id_ is None:
            saltadas += 1
            continue
        filas.append((
            id_, a_str(r['CODIGO']), a_str(r['NOMBRE']),
            a_int(r['Región Codigo']), a_str(r['Región Nombre']),
            a_int(r['COMUNA']), a_str(r['Nom. Comuna']),
            a_int(r['SS Codigo']), a_str(r['SS Nombre']),
            a_str(r['Tipo de Establecimiento']), a_str(r['Nivel de Atención']),
            a_str(r['Nivel de Atención DEIS']), a_str(r['Dependencia de Ejecución']),
            a_str(r['Certificación']), a_str(r['Hospital']), a_str(r['Especialidad']),
            a_str(r['ACTIVO']), a_str(r['Vigente']),
        ))

    cursor.executemany(sql, filas)
    log_exito(f"dim_establecimiento: {len(filas)} filas cargadas ({saltadas} saltadas sin id)")


def cargar_prestacion_cne_iq(cursor):
    log_info("Cargando dim_prestacion_cne_iq (Prestaciones_CNE_IQ.csv)...")
    df = leer_csv("Prestaciones_CNE_IQ.csv")

    cursor.execute("TRUNCATE TABLE dim_prestacion_cne_iq")

    sql = """
    INSERT INTO dim_prestacion_cne_iq
    (codigo_sigte, codigo_propuesta, base_rem, tipo_prestacion, nombre,
     especialidad, complejidad, temporalidad, vigencia_entrada, vigencia_salida)
    VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
    """

    filas = []
    saltadas = 0
    for _, r in df.iterrows():
        codigo_sigte = a_str(r['Código SIGTE'])
        if codigo_sigte is None:
            saltadas += 1
            continue
        filas.append((
            codigo_sigte, a_str(r['CODIGO']), a_str(r['Base REM']),
            a_int(r['Tipo de Prestación']), a_str(r['NOMBRE']),
            a_str(r['Especialidad']), a_str(r['Complejidad Nueva']),
            a_str(r['Temporabilidad']), a_fecha(r['Vigencia Entrada']),
            a_fecha(r['Vigencia Salida']),
        ))

    cursor.executemany(sql, filas)
    log_exito(f"dim_prestacion_cne_iq: {len(filas)} filas cargadas ({saltadas} saltadas)")


def cargar_maestro_prestacion(cursor):
    log_info("Cargando dim_maestro_prestacion (Maestro_Prestacion.csv)...")
    df = leer_csv("Maestro_Prestacion.csv")

    cursor.execute("TRUNCATE TABLE dim_maestro_prestacion")

    sql = """
    INSERT INTO dim_maestro_prestacion
    (codigo, prestacion, cod_tipo_lista, tipo_lista_nombre, vigencia_entrada,
     vigencia_salida, nombre_tipo_procedimiento, especialidad, edad_min_anio,
     edad_max_anio, tipo_especialidad)
    VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
    """

    filas = []
    saltadas = 0
    vistos = set()
    for _, r in df.iterrows():
        codigo = a_str(r['Código'])
        if codigo is None or codigo in vistos:
            saltadas += 1
            continue
        vistos.add(codigo)
        filas.append((
            codigo, a_str(r['Prestación']), a_int(r['COD']),
            a_str(r['Tipo de lista']), a_fecha(r['Vigencia Entrada']),
            a_fecha(r['Vigencia Salida']), a_str(r['Nombre Tipo de Procedimiento']),
            a_str(r['Especialidad']), a_int(r['Edad_Min año']),
            a_int(r['Edad_Max año']), a_str(r['tipo de Especialidad']),
        ))

    cursor.executemany(sql, filas)
    log_exito(f"dim_maestro_prestacion: {len(filas)} filas cargadas ({saltadas} saltadas/duplicadas)")


def cargar_causal_salida(cursor):
    log_info("Cargando dim_causal_salida (Causales_Salidas.csv)...")
    df = leer_csv("Causales_Salidas.csv")

    cursor.execute("TRUNCATE TABLE dim_causal_salida")

    sql = """
    INSERT INTO dim_causal_salida (id, nombre, tipo_causal, tipo_causal_2, excluir_numerador)
    VALUES (%s, %s, %s, %s, %s)
    """

    filas = []
    for _, r in df.iterrows():
        filas.append((
            a_int(r['Causales']), a_str(r['Nombre de Causal']),
            a_str(r['Tipo de Causal']), a_str(r['Tipos de Causal 2']),
            a_int(r['Excluir del Numerador']),
        ))

    cursor.executemany(sql, filas)
    log_exito(f"dim_causal_salida: {len(filas)} filas cargadas")


def cargar_cie10(cursor):
    log_info("Cargando dim_cie10 (CIE10.csv)...")
    df = leer_csv("CIE10.csv")

    cursor.execute("TRUNCATE TABLE dim_cie10")

    sql = "INSERT INTO dim_cie10 (codigo, descripcion) VALUES (%s, %s)"

    filas = []
    saltadas = 0
    vistos = set()
    for _, r in df.iterrows():
        codigo = a_str(r['CODIGO'])
        if codigo is None or codigo in vistos:
            saltadas += 1
            continue
        vistos.add(codigo)
        filas.append((codigo, a_str(r['DESCRIPCION']) or ''))

    # CIE10 es grande (14K filas); inserta en lotes
    for i in range(0, len(filas), 2000):
        cursor.executemany(sql, filas[i:i + 2000])

    log_exito(f"dim_cie10: {len(filas)} filas cargadas ({saltadas} saltadas/duplicadas)")


def cargar_prestacion_ges(cursor):
    log_info("Cargando dim_prestacion_ges (Tabla_Prestacion_Combinada.csv)...")
    df = leer_csv("Tabla_Prestacion_Combinada.csv")

    cursor.execute("TRUNCATE TABLE dim_prestacion_ges")

    sql = """
    INSERT INTO dim_prestacion_ges
    (codigo, tipo_le, glosa, especialidad, tipo_especialidad, complejidad,
     oncologica, edad_min_anio, edad_max_anio, edad_min_dias, edad_max_dias)
    VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
    """

    def es_oncologica(texto):
        if not texto:
            return 0
        t = texto.upper()
        return 1 if 'ONCOL' in t and 'NO ONCOL' not in t else 0

    filas = []
    saltadas = 0
    vistos = set()
    for _, r in df.iterrows():
        codigo = a_str(r['Propuesta de Código'])
        if codigo is None or codigo in vistos:
            saltadas += 1
            continue
        vistos.add(codigo)
        onco_texto = a_str(r['Oncológicas'])
        filas.append((
            codigo, a_str(r['Tipo de LE']), a_str(r['Glosa']),
            a_str(r['Especialidad']), a_str(r['tipo de Especialidad']),
            a_str(r['Complejidad Nueva']), es_oncologica(onco_texto),
            a_int(r['Edad_Min año']), a_int(r['Edad_Max año']),
            a_int(r['Edad_Min días']), a_int(r['Edad_Max días']),
        ))

    cursor.executemany(sql, filas)
    log_exito(f"dim_prestacion_ges: {len(filas)} filas cargadas ({saltadas} saltadas/duplicadas)")


def main():
    print(f"\n{ColoresTerminal.BOLD}Cargador de Dimensiones - Lista de Espera SIGLECH{ColoresTerminal.RESET}\n")

    try:
        conn = mysql.connector.connect(
            host="localhost", user="root", password="", database="siglech", autocommit=False
        )
    except Error as e:
        log_error(f"No se pudo conectar a BD siglech: {e}")
        sys.exit(1)

    cursor = conn.cursor()

    # Desactiva chequeo de FK mientras se truncan/recargan catálogos referenciados
    # entre sí (dim_prestacion_cne_iq/dim_maestro_prestacion -> dim_tipo_lista)
    cursor.execute("SET FOREIGN_KEY_CHECKS=0")

    pasos = [
        cargar_establecimiento,
        cargar_prestacion_cne_iq,
        cargar_maestro_prestacion,
        cargar_causal_salida,
        cargar_cie10,
        cargar_prestacion_ges,
    ]

    try:
        for paso in pasos:
            paso(cursor)
        cursor.execute("SET FOREIGN_KEY_CHECKS=1")
        conn.commit()
        log_exito("\nTodas las dimensiones cargadas y confirmadas (commit OK)")
    except Exception as e:
        conn.rollback()
        log_error(f"Fallo durante la carga, rollback aplicado: {e}")
        raise
    finally:
        cursor.close()
        conn.close()


if __name__ == "__main__":
    main()
