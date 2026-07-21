#!/usr/bin/env python3
"""📊 Estadísticas de la BD - demanda_cne"""

import mysql.connector
from mysql.connector import Error

try:
    conn = mysql.connector.connect(
        host="localhost",
        user="root",
        password="",
        database="siglech"
    )
    cursor = conn.cursor()

    print("\n" + "="*60)
    print("📊 ESTADÍSTICAS - TABLA demanda_cne")
    print("="*60 + "\n")

    # Total
    cursor.execute("SELECT COUNT(*) FROM demanda_cne")
    total = cursor.fetchone()[0]
    print(f"✅ Total registros: {total:,}\n")

    if total == 0:
        print("⚠️  La tabla está vacía. Ejecuta el cargador primero.")
        cursor.close()
        conn.close()
        exit(0)

    # Especialidades
    print("="*60)
    print("🏥 TOP 15 ESPECIALIDADES")
    print("="*60)
    cursor.execute("""
        SELECT ESPECIALIDAD_ESTANDAR, COUNT(*) as cantidad
        FROM demanda_cne
        WHERE ESPECIALIDAD_ESTANDAR IS NOT NULL
        GROUP BY ESPECIALIDAD_ESTANDAR
        ORDER BY cantidad DESC
        LIMIT 15
    """)

    total_esp = 0
    for i, (esp, cant) in enumerate(cursor.fetchall(), 1):
        total_esp += cant
        pct = (cant / total) * 100
        barra = "█" * int(pct / 2)
        print(f"{i:2}. {esp:40} {cant:6} ({pct:5.1f}%) {barra}")

    # Estados
    print("\n" + "="*60)
    print("📌 ESTADOS")
    print("="*60)
    cursor.execute("""
        SELECT ESTADO, COUNT(*) as cantidad
        FROM demanda_cne
        WHERE ESTADO IS NOT NULL
        GROUP BY ESTADO
        ORDER BY cantidad DESC
    """)

    for estado, cant in cursor.fetchall():
        pct = (cant / total) * 100
        print(f"  {estado:30} {cant:6} ({pct:5.1f}%)")

    # Ciudades
    print("\n" + "="*60)
    print("🏘️  TOP 10 CIUDADES")
    print("="*60)
    cursor.execute("""
        SELECT CIUDAD, COUNT(*) as cantidad
        FROM demanda_cne
        WHERE CIUDAD IS NOT NULL
        GROUP BY CIUDAD
        ORDER BY cantidad DESC
        LIMIT 10
    """)

    for i, (ciudad, cant) in enumerate(cursor.fetchall(), 1):
        print(f"{i:2}. {ciudad:35} {cant:6}")

    # Redes
    print("\n" + "="*60)
    print("🌐 REDES DE SALUD")
    print("="*60)
    cursor.execute("""
        SELECT RED, COUNT(*) as cantidad
        FROM demanda_cne
        WHERE RED IS NOT NULL
        GROUP BY RED
        ORDER BY cantidad DESC
    """)

    for red, cant in cursor.fetchall():
        pct = (cant / total) * 100
        print(f"  {red:40} {cant:6} ({pct:5.1f}%)")

    # Fechas
    print("\n" + "="*60)
    print("🕒 RANGO DE FECHAS (F_ENTRADA)")
    print("="*60)
    cursor.execute("SELECT MIN(F_ENTRADA), MAX(F_ENTRADA) FROM demanda_cne")
    fecha_min, fecha_max = cursor.fetchone()
    if fecha_min:
        print(f"  Desde: {fecha_min}")
        print(f"  Hasta: {fecha_max}")

    # Estadísticas numéricas
    print("\n" + "="*60)
    print("📊 ESTADÍSTICAS NUMÉRICAS")
    print("="*60)
    cursor.execute("""
        SELECT
            AVG(EDAD) as edad_promedio,
            MIN(EDAD) as edad_min,
            MAX(EDAD) as edad_max,
            AVG(DIAS_ESPERA) as dias_promedio,
            MAX(DIAS_ESPERA) as dias_max
        FROM demanda_cne
    """)

    edad_prom, edad_min, edad_max, dias_prom, dias_max = cursor.fetchone()

    if edad_prom:
        print(f"  Edad promedio: {edad_prom:.1f} años")
        print(f"  Edad rango: {int(edad_min) if edad_min else 'N/A'} - {int(edad_max) if edad_max else 'N/A'}")
    if dias_prom:
        print(f"  Días espera promedio: {dias_prom:.0f} días")
        print(f"  Días espera máximo: {dias_max}")

    # Tasas de ocupación
    print("\n" + "="*60)
    print("🎯 TASAS DE COMPLETITUD")
    print("="*60)
    cursor.execute("""
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN RUN IS NOT NULL AND RUN != '' THEN 1 ELSE 0 END) as con_run,
            SUM(CASE WHEN EMAIL IS NOT NULL AND EMAIL != '' THEN 1 ELSE 0 END) as con_email,
            SUM(CASE WHEN FONO_MOVIL IS NOT NULL AND FONO_MOVIL != '' THEN 1 ELSE 0 END) as con_telefono
        FROM demanda_cne
    """)

    total_reg, con_run, con_email, con_telefono = cursor.fetchone()

    if total_reg > 0:
        print(f"  RUN válido: {(con_run/total_reg*100):5.1f}% ({con_run:,}/{total_reg:,})")
        print(f"  Email: {(con_email/total_reg*100):5.1f}% ({con_email:,}/{total_reg:,})")
        print(f"  Teléfono: {(con_telefono/total_reg*100):5.1f}% ({con_telefono:,}/{total_reg:,})")

    print("\n✅ Consulta completada\n")

    cursor.close()
    conn.close()

except Error as e:
    print(f"❌ Error: {e}")
    print("\n💡 Asegúrate que:")
    print("  1. MySQL está corriendo")
    print("  2. Base de datos 'siglech' existe")
    print("  3. Has cargado datos con load_demanda_cne.py")
