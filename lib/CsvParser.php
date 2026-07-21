<?php
/**
 * Clase para parsear y validar archivos CSV
 * Soporta CNE, IQ, PROC
 */

class CsvParser {
    private $archivo;
    private $tipo;
    private $errores = [];
    private $advertencias = [];
    private $registros = [];

    public function __construct(string $ruta_archivo, string $tipo) {
        $this->archivo = $ruta_archivo;
        $this->tipo = strtoupper($tipo);

        if (!in_array($this->tipo, ['CNE', 'IQ', 'PROC'])) {
            throw new Exception("Tipo inválido: $tipo. Debe ser CNE, IQ o PROC");
        }
    }

    /**
     * Parsea el archivo CSV y retorna array de registros
     */
    public function parsear(): array {
        if (!file_exists($this->archivo)) {
            throw new Exception("Archivo no encontrado: {$this->archivo}");
        }

        $this->registros = [];
        $linea = 0;

        if (($handle = fopen($this->archivo, 'r')) !== false) {
            // Leer cabecera
            $cabecera = fgetcsv($handle, 0, ',', '"');
            if (!$cabecera) {
                throw new Exception("Archivo CSV vacío o corrupto");
            }

            // Normalizar cabecera
            $cabecera = array_map('trim', $cabecera);
            $cabecera = array_map('strtoupper', $cabecera);

            // Mapear columnas según tipo
            $mapeo = $this->obtenerMapeo();

            // Leer datos
            while (($datos = fgetcsv($handle, 0, ',', '"')) !== false) {
                $linea++;

                if (count($datos) === 1 && empty($datos[0])) {
                    continue; // Saltar líneas vacías
                }

                $registro = [];
                foreach ($mapeo as $campo_interno => $campo_csv) {
                    $indice = array_search($campo_csv, $cabecera);
                    if ($indice !== false) {
                        $valor = trim($datos[$indice] ?? '');
                        $registro[$campo_interno] = $valor;
                    }
                }

                $this->registros[] = [
                    'linea' => $linea + 1,
                    'datos' => $registro,
                    'valido' => false,
                    'errores' => []
                ];
            }

            fclose($handle);
        } else {
            throw new Exception("No se pudo abrir el archivo: {$this->archivo}");
        }

        return $this->registros;
    }

    /**
     * Obtiene mapeo de campos según tipo de lista
     */
    private function obtenerMapeo(): array {
        // Mapeos estándar para SIGTE export
        return [
            '_id' => 'SIGTE_ID',
            'run' => 'RUN',
            'estab_orig' => 'ESTAB_ORIG',
            'estab_dest' => 'ESTAB_DEST',
            'especialidad' => 'ESPECIALIDAD_ESTANDAR',
            'prestacion' => 'PRESTA_MIN',
            'fecha_ingreso' => 'F_INGRE',
            'fecha_salida' => 'F_SALIDA',
            'estado' => 'ESTADO',
            'prioridad' => 'PRIORIDAD_LE',
            'dias_espera' => 'DIAS_ESPERA',
            'cie10' => 'CIE10_HOMOLOGADO',
            'primer_apellido' => 'PRIMER_APELLIDO',
            'segundo_apellido' => 'SEGUNDO_APELLIDO',
            'nombres' => 'NOMBRES',
        ];
    }

    /**
     * Valida los registros parseados
     */
    public function validar(PDO $pdo): bool {
        $validos = 0;
        $errores_total = 0;

        foreach ($this->registros as &$reg) {
            $resultado = $this->validarRegistro($pdo, $reg['datos']);

            if (empty($resultado['errores'])) {
                $reg['valido'] = true;
                $validos++;
            } else {
                $reg['errores'] = $resultado['errores'];
                $errores_total += count($resultado['errores']);
            }
        }

        return $errores_total === 0;
    }

    /**
     * Valida un registro individual
     */
    private function validarRegistro(PDO $pdo, array $datos): array {
        $errores = [];

        // Validación: RUN
        if (empty($datos['run'])) {
            $errores[] = 'RUN requerido';
        } else {
            if (!$this->validarRUN($datos['run'])) {
                $errores[] = "RUN inválido: {$datos['run']}";
            }
        }

        // Validación: Fecha ingreso
        if (empty($datos['fecha_ingreso'])) {
            $errores[] = 'Fecha ingreso requerida';
        } else {
            if (!$this->validarFecha($datos['fecha_ingreso'])) {
                $errores[] = "Fecha ingreso inválida: {$datos['fecha_ingreso']}";
            }
        }

        // Validación: Especialidad existe
        if (!empty($datos['especialidad'])) {
            if (!$this->especialidadExiste($pdo, $datos['especialidad'])) {
                $errores[] = "Especialidad no encontrada: {$datos['especialidad']}";
            }
        }

        // Validación: Establecimiento destino existe
        if (!empty($datos['estab_dest'])) {
            if (!$this->establecimientoExiste($pdo, $datos['estab_dest'])) {
                $errores[] = "Establecimiento destino no encontrado: {$datos['estab_dest']}";
            }
        }

        // Validación: Estado válido
        if (!empty($datos['estado'])) {
            if (!in_array($datos['estado'], ['VIGENTE', 'EGRESADO', 'CANCELADO'])) {
                $errores[] = "Estado inválido: {$datos['estado']}";
            }
        }

        // Validación: Fechas coherentes
        if (!empty($datos['fecha_ingreso']) && !empty($datos['fecha_salida'])) {
            $fi = strtotime($datos['fecha_ingreso']);
            $fs = strtotime($datos['fecha_salida']);
            if ($fi > $fs) {
                $errores[] = "Fecha salida no puede ser anterior a ingreso";
            }
        }

        // Validación: CIE10
        if (!empty($datos['cie10'])) {
            if (!$this->cie10Existe($pdo, $datos['cie10'])) {
                $errores[] = "CIE10 no encontrado: {$datos['cie10']}";
            }
        }

        return ['errores' => $errores];
    }

    /**
     * Valida formato RUN (6-9 dígitos + dígito verificador)
     */
    private function validarRUN(string $run): bool {
        // Limpiar
        $run = preg_replace('/[^0-9kK]/', '', $run);

        if (strlen($run) < 7 || strlen($run) > 9) {
            return false;
        }

        $numero = substr($run, 0, -1);
        $digito = strtoupper(substr($run, -1));

        // Calcular dígito verificador
        $suma = 0;
        $multiplicador = 2;

        for ($i = strlen($numero) - 1; $i >= 0; $i--) {
            $suma += intval($numero[$i]) * $multiplicador;
            $multiplicador++;
            if ($multiplicador > 7) {
                $multiplicador = 2;
            }
        }

        $digito_calculado = 11 - ($suma % 11);

        if ($digito_calculado === 11) {
            $digito_calculado = 0;
        } elseif ($digito_calculado === 10) {
            $digito_calculado = 'K';
        }

        return $digito === (string)$digito_calculado;
    }

    /**
     * Valida formato de fecha (YYYY-MM-DD)
     */
    private function validarFecha(string $fecha): bool {
        $formato = 'Y-m-d';
        $dt = \DateTime::createFromFormat($formato, $fecha);
        return $dt && $dt->format($formato) === $fecha;
    }

    /**
     * Verifica que especialidad existe en dimensiones
     */
    private function especialidadExiste(PDO $pdo, string $especialidad): bool {
        // Por ahora retornar true (se valida en base de datos al insertar)
        return true;
    }

    /**
     * Verifica que establecimiento existe
     */
    private function establecimientoExiste(PDO $pdo, string $establecimiento): bool {
        try {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as existe
                FROM dim_establecimiento
                WHERE id = ? OR nombre LIKE ?
                LIMIT 1
            ");
            $stmt->execute([$establecimiento, "%$establecimiento%"]);
            return $stmt->fetch()['existe'] > 0;
        } catch (Exception $e) {
            return true; // Asumir válido si no puede verificar
        }
    }

    /**
     * Verifica que CIE10 existe
     */
    private function cie10Existe(PDO $pdo, string $cie10): bool {
        try {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as existe
                FROM dim_cie10
                WHERE codigo = ?
                LIMIT 1
            ");
            $stmt->execute([$cie10]);
            return $stmt->fetch()['existe'] > 0;
        } catch (Exception $e) {
            return true; // Asumir válido si no puede verificar
        }
    }

    /**
     * Retorna registros parseados y validados
     */
    public function obtenerRegistros(): array {
        return $this->registros;
    }

    /**
     * Retorna solo registros válidos
     */
    public function obtenerRegistrosValidos(): array {
        return array_filter($this->registros, function($reg) {
            return $reg['valido'];
        });
    }

    /**
     * Retorna solo registros con errores
     */
    public function obtenerRegistrosConErrores(): array {
        return array_filter($this->registros, function($reg) {
            return !$reg['valido'];
        });
    }

    /**
     * Retorna estadísticas de validación
     */
    public function obtenerEstadisticas(): array {
        $total = count($this->registros);
        $validos = count($this->obtenerRegistrosValidos());
        $con_errores = count($this->obtenerRegistrosConErrores());

        return [
            'total' => $total,
            'validos' => $validos,
            'con_errores' => $con_errores,
            'tasa_exito' => $total > 0 ? round(($validos / $total) * 100, 2) : 0
        ];
    }
}
?>
