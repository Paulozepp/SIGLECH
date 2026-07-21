#!/usr/bin/env python3
"""
🚀 Cargador de Demanda PROC (Procedimientos) para SIGLECH
Carga archivo CSV en MySQL con chunking y optimizaciones
"""

import pandas as pd
import mysql.connector
from mysql.connector import Error
import sys
import argparse
from pathlib import Path
import time
from tqdm import tqdm

class ColoresTerminal:
    """Colores ANSI para terminal"""
    VERDE = '\033[92m'
    ROJO = '\033[91m'
    AMARILLO = '\033[93m'
    AZUL = '\033[94m'
    BLANCO = '\033[97m'
    MAGENTA = '\033[95m'
    RESET = '\033[0m'
    BOLD = '\033[1m'

class CargadorDemandaPROC:
    def __init__(self, host="localhost", user="root", password="", database="siglech"):
        self.host = host
        self.user = user
        self.password = password
        self.database = database
        self.conn = None
        self.cursor = None

    def log_exito(self, msg):
        print(f"{ColoresTerminal.VERDE}✅ {msg}{ColoresTerminal.RESET}")

    def log_error(self, msg):
        print(f"{ColoresTerminal.ROJO}❌ {msg}{ColoresTerminal.RESET}")

    def log_info(self, msg):
        print(f"{ColoresTerminal.AZUL}ℹ️  {msg}{ColoresTerminal.RESET}")

    def log_advertencia(self, msg):
        print(f"{ColoresTerminal.AMARILLO}⚠️  {msg}{ColoresTerminal.RESET}")

    def conectar(self):
        """Conecta a MySQL"""
        self.log_info(f"Conectando a {self.host}:{self.database}...")
        try:
            self.conn = mysql.connector.connect(
                host=self.host,
                user=self.user,
                password=self.password,
                database=self.database,
                autocommit=False
            )
            self.cursor = self.conn.cursor()
            self.log_exito(f"Conectado a BD {self.database}")
            return True
        except Error as e:
            self.log_error(f"Error conexión: {e}")
            return False

    def crear_tabla(self):
        """Crea tabla si no existe"""
        self.log_info("Verificando/creando tabla demanda_proc...")

        sql = """
        CREATE TABLE IF NOT EXISTS demanda_proc (
            id INT AUTO_INCREMENT PRIMARY KEY,
            _id VARCHAR(20),
            TIPO_ARCHIVO VARCHAR(10),
            ARCHIVO_ID VARCHAR(50),
            SERV_SALUD INT,
            RUN VARCHAR(15) NOT NULL,
            DV VARCHAR(1),
            NOMBRES VARCHAR(100),
            PRIMER_APELLIDO VARCHAR(100),
            SEGUNDO_APELLIDO VARCHAR(100),
            FECHA_NAC DATE,
            SEXO INT,
            PREVISION INT,
            TIPO_PREST INT,
            PRESTA_MIN VARCHAR(20),
            PLANO VARCHAR(50),
            EXTREMIDAD VARCHAR(50),
            PRESTA_EST VARCHAR(100),
            F_ENTRADA DATE,
            ESTAB_ORIG INT,
            ESTAB_DEST INT,
            F_SALIDA DATE,
            C_SALIDA INT,
            E_OTOR_AT INT,
            PRESTA_MIN_SALIDA VARCHAR(20),
            PRAIS VARCHAR(50),
            REGION INT,
            COMUNA INT,
            SOSPECHA_DIAG TEXT,
            CONFIR_DIAG TEXT,
            CIE10_HOMOLOGADO VARCHAR(10),
            CIE10_DESCRIPCION TEXT,
            CIUDAD VARCHAR(100),
            COND_RURALIDAD INT,
            VIA_DIRECCION VARCHAR(50),
            NOM_CALLE VARCHAR(150),
            NUM_DIRECCION VARCHAR(20),
            RESTO_DIRECCION VARCHAR(100),
            FONO_FIJO VARCHAR(20),
            FONO_MOVIL VARCHAR(20),
            EMAIL VARCHAR(100),
            F_CITACION DATE,
            RUN_PROF_SOL VARCHAR(15),
            DV_PROF_SOL VARCHAR(1),
            RUN_PROF_RESOL VARCHAR(15),
            DV_PROF_RESOL VARCHAR(1),
            ID_LOCAL VARCHAR(50),
            RESULTADO VARCHAR(50),
            SIGTE_ID VARCHAR(50),
            ESTADO_GLOSA VARCHAR(50),
            F_DEFUNCION DATE,
            ESTADO VARCHAR(50),
            DIAS_ESPERA INT,
            EDAD INT,
            GRUPO_ETARIO VARCHAR(20),
            N_CAUSAL INT,
            TIPO_EGRESO VARCHAR(50),
            PRESTA_EST_ESTANDAR VARCHAR(100),
            ESPECIALIDAD_ESTANDAR VARCHAR(100),
            TIPO_DE_LISTA VARCHAR(50),
            NIVEL_ORIG VARCHAR(50),
            NOMBRE_ORIG VARCHAR(150),
            NIVEL_DEST VARCHAR(50),
            NOMBRE_DEST VARCHAR(150),
            SS_DEST INT,
            SS_NOMBRE_DEST VARCHAR(150),
            RED VARCHAR(100),
            POSTERGADO VARCHAR(10),
            FUENTE_CONTACTO VARCHAR(100),
            fecha_carga TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_run (RUN),
            INDEX idx_especialidad (ESPECIALIDAD_ESTANDAR),
            INDEX idx_estado (ESTADO),
            INDEX idx_fecha (F_ENTRADA)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        """

        try:
            self.cursor.execute(sql)
            self.conn.commit()
            self.log_exito("Tabla demanda_proc lista")
            return True
        except Error as e:
            if "already exists" in str(e):
                self.log_advertencia("Tabla ya existe (continuando)")
                return True
            self.log_error(f"Error creando tabla: {e}")
            return False

    def contar_filas_csv(self, archivo):
        """Cuenta filas sin cargar todo en memoria"""
        self.log_info("Contando filas del CSV...")
        with open(archivo, 'r', encoding='utf-8', errors='ignore') as f:
            return sum(1 for _ in f) - 1  # -1 por header

    def cargar_por_chunks(self, archivo, chunk_size=2000):
        """Carga CSV en chunks con barra de progreso"""

        if not Path(archivo).exists():
            self.log_error(f"Archivo no encontrado: {archivo}")
            return False

        total_filas = self.contar_filas_csv(archivo)
        self.log_info(f"Total de filas: {total_filas:,}")

        print(f"\n{ColoresTerminal.BOLD}📂 Iniciando carga...{ColoresTerminal.RESET}\n")

        try:
            total_cargadas = 0
            tiempo_inicio = time.time()

            # Lee en chunks
            chunk_reader = pd.read_csv(
                archivo,
                chunksize=chunk_size,
                encoding='utf-8-sig',
                dtype={
                    'RUN': str,
                    'DV': str,
                    '_id': str,
                    'TIPO_ARCHIVO': str
                },
                low_memory=False
            )

            # Barra de progreso
            pbar = tqdm(
                total=total_filas,
                desc="Cargando",
                unit=" registros",
                ncols=80,
                colour="green"
            )

            columnas = [
                '_id', 'TIPO_ARCHIVO', 'ARCHIVO_ID', 'SERV_SALUD', 'RUN', 'DV', 'NOMBRES',
                'PRIMER_APELLIDO', 'SEGUNDO_APELLIDO', 'FECHA_NAC', 'SEXO', 'PREVISION',
                'TIPO_PREST', 'PRESTA_MIN', 'PLANO', 'EXTREMIDAD', 'PRESTA_EST', 'F_ENTRADA',
                'ESTAB_ORIG', 'ESTAB_DEST', 'F_SALIDA', 'C_SALIDA', 'E_OTOR_AT', 'PRESTA_MIN_SALIDA',
                'PRAIS', 'REGION', 'COMUNA', 'SOSPECHA_DIAG', 'CONFIR_DIAG', 'CIE10_HOMOLOGADO',
                'CIE10_DESCRIPCION', 'CIUDAD', 'COND_RURALIDAD', 'VIA_DIRECCION', 'NOM_CALLE',
                'NUM_DIRECCION', 'RESTO_DIRECCION', 'FONO_FIJO', 'FONO_MOVIL', 'EMAIL', 'F_CITACION',
                'RUN_PROF_SOL', 'DV_PROF_SOL', 'RUN_PROF_RESOL', 'DV_PROF_RESOL', 'ID_LOCAL', 'RESULTADO',
                'SIGTE_ID', 'ESTADO_GLOSA', 'F_DEFUNCION', 'ESTADO', 'DIAS_ESPERA', 'EDAD', 'GRUPO_ETARIO',
                'N_CAUSAL', 'TIPO_EGRESO', 'PRESTA_EST_ESTANDAR', 'ESPECIALIDAD_ESTANDAR',
                'TIPO_DE_LISTA', 'NIVEL_ORIG', 'NOMBRE_ORIG', 'NIVEL_DEST', 'NOMBRE_DEST', 'SS_DEST',
                'SS_NOMBRE_DEST', 'RED', 'POSTERGADO', 'FUENTE_CONTACTO'
            ]

            sql = """
            INSERT INTO demanda_proc (
                {cols}
            ) VALUES ({marcadores})
            """.format(cols=', '.join(columnas), marcadores=', '.join(['%s'] * len(columnas)))

            def convertir_valor(v):
                """NaN/NaT -> NULL, numpy -> nativos Python, Timestamp -> date"""
                if v is None or (not isinstance(v, str) and pd.isna(v)):
                    return None
                if isinstance(v, pd.Timestamp):
                    return v.date()
                if hasattr(v, 'item'):
                    return v.item()
                return v

            filas_saltadas = 0

            for chunk_num, chunk in enumerate(chunk_reader):
                # Convierte fechas (formato DD-MM-YYYY)
                fechas = ['FECHA_NAC', 'F_ENTRADA', 'F_SALIDA', 'F_CITACION', 'F_DEFUNCION']
                for col in fechas:
                    if col in chunk.columns:
                        chunk[col] = pd.to_datetime(
                            chunk[col],
                            format='%d-%m-%Y',
                            errors='coerce'
                        )

                # Prepara filas con valores limpios
                filas = [
                    tuple(convertir_valor(row[col]) for col in columnas)
                    for _, row in chunk.iterrows()
                ]

                # Inserta en sub-lotes para no exceder max_allowed_packet (1MB en XAMPP)
                filas_chunk = 0
                sub_lote = 250
                for i in range(0, len(filas), sub_lote):
                    lote = filas[i:i + sub_lote]
                    try:
                        self.cursor.executemany(sql, lote)
                        filas_chunk += len(lote)
                    except Error:
                        # Reconecta si la conexión murió y reintenta fila a fila
                        self.conn.ping(reconnect=True, attempts=3, delay=1)
                        self.cursor = self.conn.cursor()
                        for fila in lote:
                            try:
                                self.cursor.execute(sql, fila)
                                filas_chunk += 1
                            except Error as e:
                                filas_saltadas += 1
                                if filas_saltadas <= 20:
                                    self.log_advertencia(f"Fila saltada ({e})")

                # Commit por chunk
                self.conn.commit()
                total_cargadas += filas_chunk

                # Actualiza barra
                pbar.update(len(filas))

            if filas_saltadas:
                self.log_advertencia(f"Total filas saltadas: {filas_saltadas:,}")

            pbar.close()

            # Estadísticas finales
            tiempo_total = time.time() - tiempo_inicio

            print(f"\n{ColoresTerminal.BOLD}📊 ESTADÍSTICAS DE CARGA{ColoresTerminal.RESET}")
            print(f"  Total registros cargados: {ColoresTerminal.VERDE}{total_cargadas:,}{ColoresTerminal.RESET}")
            print(f"  Tiempo total: {ColoresTerminal.AZUL}{tiempo_total:.2f}s{ColoresTerminal.RESET}")
            print(f"  Velocidad: {ColoresTerminal.MAGENTA}{total_cargadas/tiempo_total:.0f} registros/seg{ColoresTerminal.RESET}\n")

            self.log_exito(f"¡Carga completada! {total_cargadas:,} registros en demanda_proc")
            return True

        except Exception as e:
            self.log_error(f"Error durante carga: {e}")
            self.conn.rollback()
            return False

    def mostrar_resumen(self):
        """Muestra estadísticas de la BD"""
        print(f"\n{ColoresTerminal.BOLD}📈 RESUMEN BD{ColoresTerminal.RESET}\n")

        # Total
        self.cursor.execute("SELECT COUNT(*) FROM demanda_proc")
        total = self.cursor.fetchone()[0]
        self.log_info(f"Total registros: {total:,}")

        # Por especialidad
        print(f"\n{ColoresTerminal.BOLD}Top 10 Especialidades:{ColoresTerminal.RESET}")
        self.cursor.execute("""
            SELECT ESPECIALIDAD_ESTANDAR, COUNT(*) as cantidad
            FROM demanda_proc
            GROUP BY ESPECIALIDAD_ESTANDAR
            ORDER BY cantidad DESC
            LIMIT 10
        """)

        for esp, cant in self.cursor.fetchall():
            print(f"  • {esp}: {cant:,}")

        # Por estado
        print(f"\n{ColoresTerminal.BOLD}Estados:{ColoresTerminal.RESET}")
        self.cursor.execute("""
            SELECT ESTADO, COUNT(*) as cantidad
            FROM demanda_proc
            GROUP BY ESTADO
        """)

        for estado, cant in self.cursor.fetchall():
            print(f"  • {estado}: {cant:,}")

    def cerrar(self):
        """Cierra conexión"""
        if self.cursor:
            self.cursor.close()
        if self.conn:
            self.conn.close()
            self.log_exito("Conexión cerrada")


def main():
    parser = argparse.ArgumentParser(description="Cargador de Demanda PROC")
    parser.add_argument("--chunk-size", type=int, default=2000, help="Tamaño de chunks (default: 2000)")
    parser.add_argument("--host", default="localhost", help="Host MySQL")
    parser.add_argument("--user", default="root", help="Usuario MySQL")
    parser.add_argument("--password", default="", help="Password MySQL")
    parser.add_argument("--database", default="siglech", help="BD MySQL")

    args = parser.parse_args()

    archivo_csv = r"C:\xampp\htdocs\SIGLECH\Importacion\Lista de espera Julio 2026\TIPO DE LE\PROC\CSV\Demanda_Total_PROC.csv"

    print(f"\n{ColoresTerminal.BOLD}{ColoresTerminal.MAGENTA}")
    print("╔════════════════════════════════════════╗")
    print("║  🚀 CARGADOR DE DEMANDA PROC - SIGLECH   ║")
    print("╚════════════════════════════════════════╝")
    print(f"{ColoresTerminal.RESET}\n")

    cargador = CargadorDemandaPROC(
        host=args.host,
        user=args.user,
        password=args.password,
        database=args.database
    )

    if cargador.conectar():
        if cargador.crear_tabla():
            if cargador.cargar_por_chunks(archivo_csv, chunk_size=args.chunk_size):
                cargador.mostrar_resumen()
        cargador.cerrar()
    else:
        print(f"\n{ColoresTerminal.ROJO}No se pudo conectar a BD. Verifica credenciales.{ColoresTerminal.RESET}")
        sys.exit(1)


if __name__ == "__main__":
    main()
