<?php
/**
 * _config.php - Módulo Demanda LE (CNE / IQ / PROC)
 * Definiciones compartidas entre /SIGLECH/index.php y cargar.php
 */

const DEMANDA_LE_TABLAS = [
    'cne' => [
        'tabla'  => 'demanda_cne',
        'label'  => 'CNE',
        'nombre' => 'Consulta Nueva Especialidad',
        'icono'  => '🩺',
    ],
    'iq' => [
        'tabla'  => 'demanda_iq',
        'label'  => 'IQ',
        'nombre' => 'Intervención Quirúrgica',
        'icono'  => '🔪',
    ],
    'proc' => [
        'tabla'  => 'demanda_proc',
        'label'  => 'PROC',
        'nombre' => 'Procedimientos',
        'icono'  => '🩹',
    ],
];

// Orden exacto de columnas del CSV fuente (68 columnas, sin id/fecha_carga)
const DEMANDA_LE_COLUMNAS = [
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
    'SS_NOMBRE_DEST', 'RED', 'POSTERGADO', 'FUENTE_CONTACTO',
];

const DEMANDA_LE_COLUMNAS_FECHA = ['FECHA_NAC', 'F_ENTRADA', 'F_SALIDA', 'F_CITACION', 'F_DEFUNCION'];

/**
 * Valida el código de tipo (cne/iq/proc) y retorna su config, o null si inválido
 */
function demandaLeTabla(string $tipo): ?array {
    return DEMANDA_LE_TABLAS[$tipo] ?? null;
}

/**
 * Convierte un valor crudo de CSV al tipo esperado por MySQL (NULL, fecha Y-m-d, o string)
 */
function demandaLeConvertirValor(string $col, ?string $val): ?string {
    if ($val === null) {
        return null;
    }
    $val = trim($val);
    if ($val === '') {
        return null;
    }
    if (in_array($col, DEMANDA_LE_COLUMNAS_FECHA, true)) {
        $dt = DateTime::createFromFormat('d-m-Y', $val);
        return $dt !== false ? $dt->format('Y-m-d') : null;
    }
    return $val;
}

