<?php
/**
 * Descarga plantilla CSV para carga de demanda LE
 */

require_once __DIR__ . '/../../auth/guard.php';
require_once __DIR__ . '/_config.php';

$user = requiereLogin();

// Tipo de lista (parámetro)
$tipo = strtolower($_GET['tipo'] ?? 'cne');
$info = demandaLeTabla($tipo);

if ($info === null) {
    http_response_code(400);
    die('Tipo de lista inválido');
}

// Generar CSV con encabezado y ejemplos
$csv_content = '';

// Encabezado
$csv_content .= implode(',', DEMANDA_LE_COLUMNAS) . "\n";

// Ejemplo 1
$ejemplo1 = generarEjemplo($tipo, 'EJ001');
$csv_content .= implode(',', array_map('escaparCsv', $ejemplo1)) . "\n";

// Ejemplo 2
$ejemplo2 = generarEjemplo($tipo, 'EJ002');
$csv_content .= implode(',', array_map('escaparCsv', $ejemplo2)) . "\n";

// Enviar como descarga
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="plantilla_' . htmlspecialchars($tipo) . '_' . date('Y-m-d') . '.csv"');
header('Pragma: no-cache');
header('Expires: 0');

// BOM UTF-8
echo "\xEF\xBB\xBF";

echo $csv_content;
exit;

/**
 * Escapa valores CSV
 */
function escaparCsv($valor) {
    if ($valor === null) {
        return '';
    }
    $valor = (string)$valor;
    if (strpos($valor, ',') !== false || strpos($valor, '"') !== false || strpos($valor, "\n") !== false) {
        return '"' . str_replace('"', '""', $valor) . '"';
    }
    return $valor;
}

/**
 * Genera una fila de ejemplo
 */
function generarEjemplo($tipo, $id_base) {
    $ejemplo = [];

    foreach (DEMANDA_LE_COLUMNAS as $col) {
        $valor = generarValorEjemplo($col, $tipo, $id_base);
        $ejemplo[] = $valor;
    }

    return $ejemplo;
}

/**
 * Genera un valor de ejemplo para cada columna
 */
function generarValorEjemplo($col, $tipo, $id_base) {
    switch ($col) {
        case '_id':
            return $id_base;
        case 'TIPO_ARCHIVO':
            return strtoupper($tipo);
        case 'ARCHIVO_ID':
            return 'ARK-' . substr($id_base, 2);
        case 'SERV_SALUD':
            return 'Servicio de Salud Chiloé';
        case 'RUN':
            return '12345678';
        case 'DV':
            return '9';
        case 'NOMBRES':
            return 'Juan Carlos';
        case 'PRIMER_APELLIDO':
            return 'Rodríguez';
        case 'SEGUNDO_APELLIDO':
            return 'García';
        case 'FECHA_NAC':
            return '15-03-1980';
        case 'SEXO':
            return 'M';
        case 'PREVISION':
            return 'FONASA';
        case 'TIPO_PREST':
            return 'CNE';
        case 'PRESTA_MIN':
            return 'Cardiología';
        case 'PLANO':
            return '';
        case 'EXTREMIDAD':
            return '';
        case 'PRESTA_EST':
            return 'Cardiología';
        case 'F_ENTRADA':
            return '10-07-2026';
        case 'ESTAB_ORIG':
            return 'Centro de Salud Castro';
        case 'ESTAB_DEST':
            return 'Hospital Regional Chiloé';
        case 'F_SALIDA':
            return '';
        case 'C_SALIDA':
            return '';
        case 'E_OTOR_AT':
            return '';
        case 'PRESTA_MIN_SALIDA':
            return '';
        case 'PRAIS':
            return '16';
        case 'REGION':
            return 'Los Lagos';
        case 'COMUNA':
            return 'Castro';
        case 'SOSPECHA_DIAG':
            return 'Hipertensión Arterial';
        case 'CONFIR_DIAG':
            return 'Hipertensión Arterial Esencial';
        case 'CIE10_HOMOLOGADO':
            return 'I10';
        case 'CIE10_DESCRIPCION':
            return 'Hipertensión esencial (primaria)';
        case 'CIUDAD':
            return 'Castro';
        case 'COND_RURALIDAD':
            return 'Urbana';
        case 'VIA_DIRECCION':
            return 'Calle';
        case 'NOM_CALLE':
            return 'O\'Higgins';
        case 'NUM_DIRECCION':
            return '450';
        case 'RESTO_DIRECCION':
            return 'Departamento 5';
        case 'FONO_FIJO':
            return '(65) 2631234';
        case 'FONO_MOVIL':
            return '+56912345678';
        case 'EMAIL':
            return 'juan.rodriguez@email.com';
        case 'F_CITACION':
            return '25-07-2026';
        case 'RUN_PROF_SOL':
            return '98765432';
        case 'DV_PROF_SOL':
            return '1';
        case 'RUN_PROF_RESOL':
            return '87654321';
        case 'DV_PROF_RESOL':
            return '2';
        case 'ID_LOCAL':
            return 'LOC-001';
        case 'RESULTADO':
            return 'Aceptado';
        case 'SIGTE_ID':
            return 'SIGTE-' . substr($id_base, 2);
        case 'ESTADO_GLOSA':
            return 'No';
        case 'F_DEFUNCION':
            return '';
        case 'ESTADO':
            return 'VIGENTE';
        case 'DIAS_ESPERA':
            return '14';
        case 'EDAD':
            return '46';
        case 'GRUPO_ETARIO':
            return '45-49';
        case 'N_CAUSAL':
            return '1';
        case 'TIPO_EGRESO':
            return 'Atendido';
        case 'PRESTA_EST_ESTANDAR':
            return 'Cardiología';
        case 'ESPECIALIDAD_ESTANDAR':
            return 'Cardiología';
        case 'TIPO_DE_LISTA':
            return strtoupper($tipo);
        case 'NIVEL_ORIG':
            return 'Primario';
        case 'NOMBRE_ORIG':
            return 'Centro de Salud Castro';
        case 'NIVEL_DEST':
            return 'Secundario';
        case 'NOMBRE_DEST':
            return 'Hospital Regional Chiloé';
        case 'SS_DEST':
            return 'Servicio de Salud Chiloé';
        case 'SS_NOMBRE_DEST':
            return 'Servicio de Salud Chiloé';
        case 'RED':
            return 'Red Asistencial Chiloé';
        case 'POSTERGADO':
            return 'No';
        case 'FUENTE_CONTACTO':
            return 'Llamada telefónica';
        default:
            return '';
    }
}
?>
