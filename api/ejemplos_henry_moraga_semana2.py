#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
SIGLECH API - Ejemplos de Importación (Semana 2)
Script Python para importar datos vía API REST

Nuevas funcionalidades:
- POST /importar/json - Carga datos desde JSON
- GET /importar/estado - Consultar progreso

Instalación:
    pip install requests pandas

Uso:
    python3 ejemplos_henry_moraga_semana2.py
"""

import requests
import json
import pandas as pd
import time
from datetime import datetime
from typing import Dict, List, Any

BASE_URL = "http://localhost/SIGLECH/api/v1"
TOKEN = "siglech_henry_moraga_b3811accb58744198920e10932be47a5"

HEADERS = {
    "Authorization": f"Bearer {TOKEN}",
    "Content-Type": "application/json"
}


class SIGLECHClient:
    def __init__(self, base_url: str, token: str):
        self.base_url = base_url
        self.headers = {
            "Authorization": f"Bearer {token}",
            "Content-Type": "application/json"
        }

    def importar_json(self, tipo: str, datos: List[Dict]) -> Dict[str, Any]:
        """
        Importa datos desde JSON

        Args:
            tipo: 'CNE', 'IQ' o 'PROC'
            datos: Lista de diccionarios con registros

        Returns:
            Dict con ID de importación y estadísticas
        """
        payload = {
            "tipo": tipo,
            "datos": datos
        }

        try:
            response = requests.post(
                f"{self.base_url}/importar/json",
                json=payload,
                headers=self.headers,
                timeout=30
            )

            if response.status_code == 202:
                return response.json()
            else:
                return {
                    "success": False,
                    "error": f"HTTP {response.status_code}: {response.text}"
                }
        except requests.exceptions.RequestException as e:
            return {
                "success": False,
                "error": f"Error de conexión: {str(e)}"
            }

    def consultar_estado_importacion(self, importacion_id: str, incluir_errores: bool = False) -> Dict[str, Any]:
        """
        Consulta el estado de una importación

        Args:
            importacion_id: ID retornado por importar_json
            incluir_errores: Si incluir detalles de errores

        Returns:
            Dict con estado y estadísticas
        """
        params = {
            "importacion_id": importacion_id,
            "incluir_errores": "true" if incluir_errores else "false"
        }

        try:
            response = requests.get(
                f"{self.base_url}/importar/estado",
                params=params,
                headers=self.headers,
                timeout=10
            )

            return response.json()
        except requests.exceptions.RequestException as e:
            return {
                "success": False,
                "error": f"Error de conexión: {str(e)}"
            }

    def monitorear_importacion(self, importacion_id: str, intervalo: int = 5, timeout: int = 300):
        """
        Monitorea una importación hasta que termine

        Args:
            importacion_id: ID de la importación
            intervalo: Segundos entre consultas
            timeout: Máximo segundos a esperar
        """
        inicio = time.time()

        while True:
            elapsed = time.time() - inicio

            if elapsed > timeout:
                print(f"⏱️ Timeout después de {timeout}s")
                break

            respuesta = self.consultar_estado_importacion(importacion_id)

            if not respuesta.get("success"):
                print(f"❌ Error: {respuesta.get('error')}")
                break

            datos = respuesta.get("datos", {})
            estado = datos.get("estado", "desconocido")
            progreso = datos.get("progreso_porcentaje", 0)

            registros = datos.get("registros", {})
            exitosos = registros.get("exitosos", 0)
            fallidos = registros.get("fallidos", 0)
            total = registros.get("total", 0)

            # Mostrar progreso
            barra = "█" * (progreso // 10) + "░" * (10 - (progreso // 10))
            print(f"\r[{barra}] {progreso}% | {exitosos}/{total} exitosos | {fallidos} errores | Estado: {estado}", end="")

            if estado == "completado" or estado == "error":
                print("\n")
                return respuesta

            time.sleep(intervalo)

        return None


# ============================================================================
# EJEMPLOS DE USO
# ============================================================================

def ejemplo_1_importar_cne_simple():
    """Ejemplo 1: Importar pocos registros CNE"""
    print("\n" + "="*70)
    print("EJEMPLO 1: Importar 3 registros CNE")
    print("="*70)

    client = SIGLECHClient(BASE_URL, TOKEN)

    # Datos a importar
    datos = [
        {
            "_id": "sigte-cne-test-001",
            "run": "12345678-9",
            "primer_apellido": "Pérez",
            "segundo_apellido": "García",
            "nombres": "Juan",
            "estab_orig": "1000",
            "estab_dest": "1100",
            "especialidad": "Cardiología",
            "prestacion": "Consulta",
            "fecha_ingreso": "2026-07-01",
            "estado": "VIGENTE",
            "dias_espera": 20,
            "cie10": "I10"
        },
        {
            "_id": "sigte-cne-test-002",
            "run": "87654321-2",
            "primer_apellido": "López",
            "segundo_apellido": "Martínez",
            "nombres": "María",
            "estab_orig": "1000",
            "estab_dest": "1100",
            "especialidad": "Pediatría",
            "prestacion": "Consulta",
            "fecha_ingreso": "2026-06-15",
            "estado": "VIGENTE",
            "dias_espera": 36,
            "cie10": "Z00"
        },
        {
            "_id": "sigte-cne-test-003",
            "run": "11111111-1",
            "primer_apellido": "Rodríguez",
            "segundo_apellido": "Fernández",
            "nombres": "Carlos",
            "estab_orig": "1000",
            "estab_dest": "1100",
            "especialidad": "Neurología",
            "prestacion": "Consulta",
            "fecha_ingreso": "2026-05-20",
            "estado": "VIGENTE",
            "dias_espera": 62,
            "cie10": "G89"
        }
    ]

    print(f"\n📤 Enviando {len(datos)} registros...")
    respuesta = client.importar_json("CNE", datos)

    if respuesta.get("success"):
        print(f"\n✅ Importación iniciada!")
        print(f"   ID: {respuesta['datos']['importacion_id']}")
        print(f"   Registros a procesar: {respuesta['datos']['total_registros']}")
        print(f"   Exitosos: {respuesta['datos']['registros_exitosos']}")
        print(f"   Fallidos: {respuesta['datos']['registros_fallidos']}")

        # Monitorear hasta completar
        print(f"\n⏳ Monitoreando...")
        resultado_final = client.monitorear_importacion(respuesta['datos']['importacion_id'])

        if resultado_final and resultado_final.get("success"):
            datos_finales = resultado_final['datos']
            print(f"\n✅ Importación completada!")
            print(f"   Total: {datos_finales['registros']['total']}")
            print(f"   Exitosos: {datos_finales['registros']['exitosos']}")
            print(f"   Fallidos: {datos_finales['registros']['fallidos']}")
            print(f"   Tasa de éxito: {datos_finales['registros']['tasa_exito']}%")
    else:
        print(f"❌ Error: {respuesta.get('error')}")


def ejemplo_2_importar_datos_desde_csv():
    """Ejemplo 2: Leer CSV local y importar"""
    print("\n" + "="*70)
    print("EJEMPLO 2: Leer CSV local y importar via API")
    print("="*70)

    try:
        import pandas as pd

        # Simulación: crear CSV de prueba
        datos_ejemplo = [
            ["sigte-cne-csv-001", "12345678-9", "Pérez", "García", "Juan", "1000", "1100", "Cardiología", "Consulta", "2026-07-01", "VIGENTE", 20, "I10"],
            ["sigte-cne-csv-002", "87654321-2", "López", "Martínez", "María", "1000", "1100", "Pediatría", "Consulta", "2026-06-15", "VIGENTE", 36, "Z00"],
            ["sigte-cne-csv-003", "11111111-1", "Rodríguez", "Fernández", "Carlos", "1000", "1100", "Neurología", "Consulta", "2026-05-20", "VIGENTE", 62, "G89"],
        ]

        columnas = ["_id", "run", "primer_apellido", "segundo_apellido", "nombres",
                   "estab_orig", "estab_dest", "especialidad", "prestacion",
                   "fecha_ingreso", "estado", "dias_espera", "cie10"]

        df = pd.DataFrame(datos_ejemplo, columns=columnas)

        # Guardar CSV
        archivo_csv = f"importar_test_{datetime.now().strftime('%Y%m%d_%H%M%S')}.csv"
        df.to_csv(archivo_csv, index=False)
        print(f"✅ CSV creado: {archivo_csv}")

        # Leer y convertir a lista de dicts
        df_leido = pd.read_csv(archivo_csv)
        datos = df_leido.to_dict('records')

        print(f"📊 {len(datos)} registros leídos del CSV")

        # Importar via API
        client = SIGLECHClient(BASE_URL, TOKEN)
        print(f"\n📤 Enviando a API...")

        respuesta = client.importar_json("CNE", datos)

        if respuesta.get("success"):
            print(f"✅ Importación iniciada: {respuesta['datos']['importacion_id']}")
        else:
            print(f"❌ Error: {respuesta.get('error')}")

    except ImportError:
        print("⚠️ Se requiere pandas para este ejemplo")
        print("   Ejecutar: pip install pandas")


def ejemplo_3_importar_lote_grande():
    """Ejemplo 3: Importar lote grande de datos"""
    print("\n" + "="*70)
    print("EJEMPLO 3: Importar lote grande (1000 registros)")
    print("="*70)

    client = SIGLECHClient(BASE_URL, TOKEN)

    # Generar datos de prueba
    especialidades = ["Cardiología", "Pediatría", "Neurología", "Oftalmología", "Traumatología"]
    datos = []

    for i in range(1000):
        run_base = 10000000 + i
        datos.append({
            "_id": f"sigte-cne-batch-{i+1:05d}",
            "run": f"{run_base}-{i % 10}",
            "primer_apellido": f"Apellido{i}",
            "segundo_apellido": f"Segundo{i}",
            "nombres": f"Nombre{i}",
            "estab_orig": "1000",
            "estab_dest": "1100",
            "especialidad": especialidades[i % len(especialidades)],
            "prestacion": "Consulta",
            "fecha_ingreso": f"2026-{(i % 6) + 1:02d}-{(i % 28) + 1:02d}",
            "estado": "VIGENTE",
            "dias_espera": (i % 200) + 1,
            "cie10": "I10" if i % 2 == 0 else "Z00"
        })

    print(f"\n📤 Enviando {len(datos)} registros...")
    respuesta = client.importar_json("CNE", datos)

    if respuesta.get("success"):
        importacion_id = respuesta['datos']['importacion_id']
        print(f"✅ Lote iniciado: {importacion_id}")
        print(f"\n⏳ Monitoreando...")

        resultado = client.monitorear_importacion(importacion_id)

        if resultado and resultado.get("success"):
            datos_finales = resultado['datos']
            duracion = datos_finales['timestamps'].get('duracion', 'N/A')
            print(f"\n✅ Completado en {duracion}")
            print(f"   Tasa de éxito: {datos_finales['registros']['tasa_exito']}%")


# ============================================================================
# MAIN
# ============================================================================

if __name__ == "__main__":
    print("🚀 SIGLECH API v1.1 - Ejemplos de Importación (Semana 2)")
    print(f"Base URL: {BASE_URL}")
    print(f"Cliente: henry_moraga_data_sync")

    try:
        # Ejecutar ejemplos
        ejemplo_1_importar_cne_simple()
        # ejemplo_2_importar_datos_desde_csv()
        # ejemplo_3_importar_lote_grande()

    except Exception as e:
        print(f"\n❌ Error: {e}")
        import traceback
        traceback.print_exc()

    print("\n" + "="*70)
    print("✅ Ejemplos completados")
    print("="*70)
