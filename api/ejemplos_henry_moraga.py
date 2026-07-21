#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
SIGLECH API - Script de Ejemplo para Henry Moraga
Script Python para acceder a listas de espera via API REST

Instalación de dependencias:
    pip install requests pandas

Uso:
    python3 ejemplos_henry_moraga.py
"""

import requests
import json
import pandas as pd
from datetime import datetime
from typing import Dict, List, Any

# ============================================================================
# CONFIGURACIÓN
# ============================================================================

BASE_URL = "http://localhost/SIGLECH/api/v1"
TOKEN = "siglech_henry_moraga_b3811accb58744198920e10932be47a5"

# Headers con autenticación
HEADERS = {
    "Authorization": f"Bearer {TOKEN}",
    "Content-Type": "application/json"
}

# ============================================================================
# CLASE: Cliente API SIGLECH
# ============================================================================

class SIGLECHClient:
    def __init__(self, base_url: str, token: str):
        self.base_url = base_url
        self.headers = {
            "Authorization": f"Bearer {token}",
            "Content-Type": "application/json"
        }

    def obtener_vigentes(self, tipo: str = None, especialidad: str = None,
                        pagina: int = 1, por_pagina: int = 100) -> Dict[str, Any]:
        """
        Obtiene lista de espera vigentes con filtros opcionales

        Args:
            tipo: 'CNE', 'IQ', 'PROC' o None para todas
            especialidad: Nombre de especialidad (búsqueda parcial)
            pagina: Número de página
            por_pagina: Registros por página

        Returns:
            Dict con datos y paginación
        """
        params = {
            "pagina": pagina,
            "por_pagina": por_pagina
        }

        if tipo:
            params["tipo"] = tipo
        if especialidad:
            params["especialidad"] = especialidad

        try:
            response = requests.get(
                f"{self.base_url}/listas_espera/vigentes",
                params=params,
                headers=self.headers,
                timeout=10
            )
            response.raise_for_status()
            return response.json()
        except requests.exceptions.RequestException as e:
            print(f"❌ Error en obtener_vigentes: {e}")
            return {"success": False, "error": str(e)}

    def obtener_estadisticas(self, tipo: str = None) -> Dict[str, Any]:
        """
        Obtiene estadísticas de listas de espera

        Args:
            tipo: 'CNE', 'IQ', 'PROC' o None para todas

        Returns:
            Dict con KPIs por tipo de lista
        """
        params = {}
        if tipo:
            params["tipo"] = tipo

        try:
            response = requests.get(
                f"{self.base_url}/listas_espera/estadisticas",
                params=params,
                headers=self.headers,
                timeout=10
            )
            response.raise_for_status()
            return response.json()
        except requests.exceptions.RequestException as e:
            print(f"❌ Error en obtener_estadisticas: {e}")
            return {"success": False, "error": str(e)}


# ============================================================================
# EJEMPLOS DE USO
# ============================================================================

def ejemplo_1_vigentes_cne():
    """Ejemplo 1: Obtener CNE vigentes"""
    print("\n" + "="*70)
    print("EJEMPLO 1: Obtener vigentes de CNE (Cardiología)")
    print("="*70)

    client = SIGLECHClient(BASE_URL, TOKEN)

    respuesta = client.obtener_vigentes(
        tipo="CNE",
        especialidad="Cardiología",
        pagina=1,
        por_pagina=10
    )

    if respuesta.get("success"):
        print(f"\n✅ Conexión exitosa!")
        print(f"📊 Total vigentes: {respuesta['paginacion']['total']}")
        print(f"📄 Página: {respuesta['paginacion']['pagina']} de {respuesta['paginacion']['paginas_totales']}")
        print(f"\nPrimeros 10 registros:")
        print("-" * 70)

        for reg in respuesta['datos'][:10]:
            print(f"  {reg['run']:15} | {reg['paciente']:30} | {reg['dias_espera']:3}d | {reg['prioridad']}")
    else:
        print(f"❌ Error: {respuesta.get('error')}")


def ejemplo_2_estadisticas():
    """Ejemplo 2: Obtener estadísticas generales"""
    print("\n" + "="*70)
    print("EJEMPLO 2: Estadísticas generales de listas de espera")
    print("="*70)

    client = SIGLECHClient(BASE_URL, TOKEN)

    respuesta = client.obtener_estadisticas(tipo="CNE")

    if respuesta.get("success"):
        print(f"\n✅ Estadísticas obtenidas!")

        for tipo, datos in respuesta['datos'].items():
            print(f"\n{tipo}: {datos['nombre']}")
            print(f"  Vigentes: {datos['vigentes']}")
            print(f"  Promedio espera: {datos['promedio_dias_espera']} días")
            print(f"  Máximo espera: {datos['maximo_dias_espera']} días")
            print(f"  Por urgencia:")
            for urgencia, cantidad in datos['por_urgencia'].items():
                print(f"    {urgencia}: {cantidad}")
    else:
        print(f"❌ Error: {respuesta.get('error')}")


def ejemplo_3_exportar_a_csv():
    """Ejemplo 3: Obtener datos y guardar en CSV"""
    print("\n" + "="*70)
    print("EJEMPLO 3: Exportar a CSV (primeros 1000 registros)")
    print("="*70)

    client = SIGLECHClient(BASE_URL, TOKEN)

    todas_filas = []
    pagina = 1
    por_pagina = 100

    print("\n📥 Descargando datos...")

    while True:
        respuesta = client.obtener_vigentes(
            tipo="CNE",
            pagina=pagina,
            por_pagina=por_pagina
        )

        if not respuesta.get("success"):
            print(f"❌ Error: {respuesta.get('error')}")
            break

        datos = respuesta.get('datos', [])
        if not datos:
            break

        todas_filas.extend(datos)
        paginacion = respuesta.get('paginacion', {})
        total = paginacion.get('total', 0)

        print(f"  Descargados: {len(todas_filas)} / {total}")

        if pagina >= paginacion.get('paginas_totales', 1):
            break

        pagina += 1
        if len(todas_filas) >= 1000:
            break

    # Convertir a DataFrame y guardar
    if todas_filas:
        df = pd.DataFrame(todas_filas)

        # Seleccionar columnas principales
        columnas = ['folio', 'run', 'paciente', 'especialidad', 'est_origen',
                   'est_destino', 'dias_espera', 'fecha_ingreso', 'prioridad', 'estado']
        df_export = df[[col for col in columnas if col in df.columns]]

        archivo = f"siglech_cne_{datetime.now().strftime('%Y%m%d_%H%M%S')}.csv"
        df_export.to_csv(archivo, index=False, encoding='utf-8-sig')

        print(f"\n✅ Archivo guardado: {archivo}")
        print(f"   Registros: {len(df_export)}")
        print(f"\nPrimeras filas:")
        print(df_export.head(10))
    else:
        print("❌ No se obtuvieron datos")


def ejemplo_4_procesar_datos():
    """Ejemplo 4: Análisis de datos"""
    print("\n" + "="*70)
    print("EJEMPLO 4: Análisis de datos con Python")
    print("="*70)

    client = SIGLECHClient(BASE_URL, TOKEN)

    respuesta = client.obtener_vigentes(tipo="CNE", pagina=1, por_pagina=500)

    if respuesta.get("success"):
        datos = respuesta['datos']
        df = pd.DataFrame(datos)

        print(f"\n✅ {len(df)} registros CNE obtenidos")

        print(f"\n📊 Análisis:")
        print(f"  Días promedio: {df['dias_espera'].mean():.1f}")
        print(f"  Días máximo: {df['dias_espera'].max()}")
        print(f"  Días mínimo: {df['dias_espera'].min()}")

        print(f"\n🏥 Top 10 Especialidades:")
        top_esp = df['especialidad'].value_counts().head(10)
        for esp, count in top_esp.items():
            print(f"  {esp}: {count}")

        print(f"\n⚠️ Pacientes en espera crítica (>180 días):")
        criticos = df[df['dias_espera'] > 180]
        print(f"  Total: {len(criticos)}")
        if len(criticos) > 0:
            print(f"\n  Top 5 más antiguos:")
            top_criticos = criticos.nlargest(5, 'dias_espera')[['run', 'paciente', 'dias_espera', 'especialidad']]
            for idx, row in top_criticos.iterrows():
                print(f"    {row['run']:15} | {row['paciente']:30} | {row['dias_espera']:4}d | {row['especialidad']}")
    else:
        print(f"❌ Error: {respuesta.get('error')}")


# ============================================================================
# MAIN
# ============================================================================

if __name__ == "__main__":
    print("🚀 SIGLECH API - Script de Prueba para Henry Moraga")
    print(f"Base URL: {BASE_URL}")
    print(f"Cliente: henry_moraga_data_sync")

    # Ejecutar ejemplos
    try:
        ejemplo_1_vigentes_cne()
        ejemplo_2_estadisticas()

        # Los siguientes ejemplos requieren pandas
        try:
            import pandas
            ejemplo_3_exportar_a_csv()
            ejemplo_4_procesar_datos()
        except ImportError:
            print("\n⚠️ Para ejecutar ejemplos 3 y 4, instalar pandas:")
            print("   pip install pandas")

    except Exception as e:
        print(f"\n❌ Error: {e}")
        import traceback
        traceback.print_exc()

    print("\n" + "="*70)
    print("✅ Ejemplos completados")
    print("="*70)
