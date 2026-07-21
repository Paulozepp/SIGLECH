#!/usr/bin/env python3
"""🧹 Limpia la tabla demanda_cne"""

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

    print("\n⚠️  ADVERTENCIA: Esto eliminará TODOS los datos de demanda_cne\n")
    respuesta = input("¿Estás seguro? (escribe 'SÍ' para confirmar): ").strip().upper()

    if respuesta == "SÍ":
        cursor.execute("DELETE FROM demanda_cne")
        conn.commit()
        print("\n✅ Tabla limpiada\n")
    else:
        print("\n❌ Cancelado\n")

    cursor.close()
    conn.close()

except Error as e:
    print(f"❌ Error: {e}")
