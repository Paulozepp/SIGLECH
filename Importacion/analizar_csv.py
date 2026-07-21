#!/usr/bin/env python3
"""📊 Analiza CSV sin cargar en BD"""

import pandas as pd
from pathlib import Path

archivo = r"C:\xampp\htdocs\SIGLECH\Importacion\Lista de espera Julio 2026\TIPO DE LE\CNE\CSV\Demanda_Total_CNE.csv"

print("\n" + "="*60)
print("📊 ANÁLISIS DEL CSV - DEMANDA CNE")
print("="*60 + "\n")

print("🔍 Leyendo primeras 10,000 filas...")
df = pd.read_csv(archivo, nrows=10000, dtype={'RUN': str})

print(f"\n✅ Filas totales (CSV): ~245,816")
print(f"✅ Columnas: {len(df.columns)}")
print(f"✅ Tamaño archivo: ~350 MB\n")

print("="*60)
print("🏥 TOP 15 ESPECIALIDADES")
print("="*60)
esp = df['ESPECIALIDAD_ESTANDAR'].value_counts().head(15)
for i, (esp_name, count) in enumerate(esp.items(), 1):
    pct = (count / len(df)) * 100
    barra = "█" * int(pct / 2)
    print(f"{i:2}. {esp_name:40} {count:6} ({pct:5.1f}%) {barra}")

print(f"\n✅ Total especialidades únicas: {df['ESPECIALIDAD_ESTANDAR'].nunique()}")

print("\n" + "="*60)
print("📌 ESTADOS")
print("="*60)
for estado, count in df['ESTADO'].value_counts().items():
    pct = (count / len(df)) * 100
    print(f"  • {estado:30} {count:6} ({pct:5.1f}%)")

print("\n" + "="*60)
print("🏘️  TOP 10 CIUDADES/MUNICIPIOS")
print("="*60)
for i, (ciudad, count) in enumerate(df['CIUDAD'].value_counts().head(10).items(), 1):
    print(f"{i:2}. {ciudad:35} {count:6}")

print("\n" + "="*60)
print("🕒 RANGO DE FECHAS")
print("="*60)
df['F_ENTRADA'] = pd.to_datetime(df['F_ENTRADA'], format='%d-%m-%Y', errors='coerce')
print(f"  Desde: {df['F_ENTRADA'].min().strftime('%d-%m-%Y')}")
print(f"  Hasta: {df['F_ENTRADA'].max().strftime('%d-%m-%Y')}")

print("\n" + "="*60)
print("📊 ESTADÍSTICAS NUMÉRICAS")
print("="*60)
print(f"  Edad promedio: {df['EDAD'].mean():.1f} años")
print(f"  Edad mín/máx: {df['EDAD'].min()}/{df['EDAD'].max()}")
print(f"  Días espera promedio: {df['DIAS_ESPERA'].mean():.0f} días")
print(f"  Días espera máx: {df['DIAS_ESPERA'].max()}")

print("\n" + "="*60)
print("🧊 VALORES VACÍOS (muestra)")
print("="*60)
vacios = df.isnull().sum()
vacios_sort = vacios[vacios > 0].sort_values(ascending=False).head(10)
for col, count in vacios_sort.items():
    pct = (count / len(df)) * 100
    print(f"  {col:30} {count:6} ({pct:5.1f}%)")

print("\n✅ Análisis completado\n")
