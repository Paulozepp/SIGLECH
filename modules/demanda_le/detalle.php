<?php
/**
 * Ficha completa de un registro de demanda (CNE / IQ / PROC) - SIGLECH
 * Muestra los 68 campos de la Norma 118 (Lista de Espera SIGTE) agrupados por sección.
 */

require_once __DIR__ . '/../../auth/guard.php';
require_once __DIR__ . '/../../partials/layout.php';
require_once __DIR__ . '/_config.php';

$user = requiereLogin();
$pdo  = getConexionSiglech();

$tipo = strtolower((string)($_GET['tipo'] ?? ''));
$info = demandaLeTabla($tipo);
$id   = (int)($_GET['id'] ?? 0);

if ($info === null || $id <= 0) {
    http_response_code(404);
    layoutHeader('Registro no encontrado', $user, 'demanda_le');
    echo '<p class="text-slate-600 dark:text-slate-400">Registro no encontrado.</p>';
    layoutFooter();
    exit;
}

$tabla = $info['tabla'];
$stmt = $pdo->prepare("SELECT * FROM {$tabla} WHERE id = :id LIMIT 1");
$stmt->execute(['id' => $id]);
$registro = $stmt->fetch();

if ($registro === false) {
    http_response_code(404);
    layoutHeader('Registro no encontrado', $user, 'demanda_le');
    echo '<p class="text-slate-600 dark:text-slate-400">El registro solicitado no existe en ' . htmlspecialchars($tabla) . '.</p>';
    echo '<a href="/SIGLECH/index.php?tipo=' . htmlspecialchars($tipo) . '" class="text-brand-600 hover:underline">← Volver</a>';
    layoutFooter();
    exit;
}

// Secciones según estructura de la Norma 118 - Lista de Espera SIGTE
$secciones = [
    'Identificación del archivo' => [
        '_id' => 'ID interno del archivo fuente',
        'TIPO_ARCHIVO' => 'Tipo de archivo',
        'ARCHIVO_ID' => 'ID de archivo',
        'SERV_SALUD' => 'Servicio de salud',
    ],
    'Identificación del paciente' => [
        'RUN' => 'RUN', 'DV' => 'DV',
        'NOMBRES' => 'Nombres',
        'PRIMER_APELLIDO' => 'Primer apellido',
        'SEGUNDO_APELLIDO' => 'Segundo apellido',
        'FECHA_NAC' => 'Fecha de nacimiento',
        'SEXO' => 'Sexo',
        'EDAD' => 'Edad',
        'GRUPO_ETARIO' => 'Grupo etario',
        'PREVISION' => 'Previsión',
    ],
    'Prestación solicitada' => [
        'TIPO_PREST' => 'Tipo de prestación',
        'PRESTA_MIN' => 'Prestación MINSAL',
        'PRESTA_EST' => 'Prestación establecimiento',
        'PRESTA_EST_ESTANDAR' => 'Prestación estandarizada',
        'ESPECIALIDAD_ESTANDAR' => 'Especialidad estandarizada',
        'TIPO_DE_LISTA' => 'Tipo de lista',
        'PLANO' => 'Plano',
        'EXTREMIDAD' => 'Extremidad',
    ],
    'Fechas y tiempo de espera' => [
        'F_ENTRADA' => 'Fecha de entrada',
        'F_CITACION' => 'Fecha de citación',
        'F_SALIDA' => 'Fecha de salida',
        'F_DEFUNCION' => 'Fecha de defunción',
        'DIAS_ESPERA' => 'Días de espera',
    ],
    'Derivación (origen / destino)' => [
        'ESTAB_ORIG' => 'Establecimiento origen (código)',
        'NIVEL_ORIG' => 'Nivel origen',
        'NOMBRE_ORIG' => 'Nombre establecimiento origen',
        'ESTAB_DEST' => 'Establecimiento destino (código)',
        'NIVEL_DEST' => 'Nivel destino',
        'NOMBRE_DEST' => 'Nombre establecimiento destino',
        'SS_DEST' => 'Servicio de salud destino',
        'SS_NOMBRE_DEST' => 'Nombre servicio de salud destino',
        'RED' => 'Red',
        'PRAIS' => 'PRAIS',
    ],
    'Diagnóstico' => [
        'SOSPECHA_DIAG' => 'Sospecha diagnóstica',
        'CONFIR_DIAG' => 'Diagnóstico confirmado',
        'CIE10_HOMOLOGADO' => 'CIE-10 homologado',
        'CIE10_DESCRIPCION' => 'CIE-10 descripción',
    ],
    'Ubicación y contacto' => [
        'REGION' => 'Región',
        'COMUNA' => 'Comuna',
        'CIUDAD' => 'Ciudad',
        'COND_RURALIDAD' => 'Condición de ruralidad',
        'VIA_DIRECCION' => 'Vía',
        'NOM_CALLE' => 'Nombre calle',
        'NUM_DIRECCION' => 'Número',
        'RESTO_DIRECCION' => 'Resto dirección',
        'FONO_FIJO' => 'Teléfono fijo',
        'FONO_MOVIL' => 'Teléfono móvil',
        'EMAIL' => 'Email',
        'FUENTE_CONTACTO' => 'Fuente de contacto',
    ],
    'Profesionales' => [
        'RUN_PROF_SOL' => 'RUN profesional solicitante',
        'DV_PROF_SOL' => 'DV profesional solicitante',
        'RUN_PROF_RESOL' => 'RUN profesional resolutivo',
        'DV_PROF_RESOL' => 'DV profesional resolutivo',
    ],
    'Egreso y resultado' => [
        'ESTADO' => 'Estado',
        'ESTADO_GLOSA' => 'Glosa de estado',
        'RESULTADO' => 'Resultado',
        'TIPO_EGRESO' => 'Tipo de egreso',
        'C_SALIDA' => 'Causal de salida (código)',
        'N_CAUSAL' => 'N° causal',
        'E_OTOR_AT' => 'Establecimiento que otorga atención',
        'PRESTA_MIN_SALIDA' => 'Prestación MINSAL de salida',
        'POSTERGADO' => 'Postergado',
    ],
    'Identificadores externos' => [
        'ID_LOCAL' => 'ID local',
        'SIGTE_ID' => 'Folio SIGTE',
    ],
    'Metadatos de carga' => [
        'id' => 'ID interno BD',
        'fecha_carga' => 'Fecha de carga al sistema',
    ],
];

function detalleValor($v): string {
    if ($v === null || $v === '') {
        return '—';
    }
    return htmlspecialchars((string)$v);
}

layoutHeader('Ficha ' . $info['label'] . ' - ' . ($registro['RUN'] ?? ''), $user, 'demanda_le');
?>

<div class="mb-6">
    <a href="/SIGLECH/index.php?tipo=<?= htmlspecialchars($tipo) ?>" class="text-sm text-brand-600 hover:underline">← Volver a <?= htmlspecialchars($info['label']) ?></a>
</div>

<div class="mb-8">
    <h2 class="text-3xl font-bold text-slate-900 dark:text-white mb-2">
        <?= $info['icono'] ?> Ficha completa — <?= htmlspecialchars(trim(($registro['NOMBRES'] ?? '') . ' ' . ($registro['PRIMER_APELLIDO'] ?? '') . ' ' . ($registro['SEGUNDO_APELLIDO'] ?? ''))) ?>
    </h2>
    <p class="text-slate-600 dark:text-slate-400">
        RUN <?= htmlspecialchars(($registro['RUN'] ?? '') . '-' . ($registro['DV'] ?? '')) ?>
        · <?= htmlspecialchars($info['nombre']) ?>
        · Todos los campos según Norma 118 (Lista de Espera SIGTE)
    </p>
</div>

<div class="space-y-6">
    <?php foreach ($secciones as $tituloSeccion => $campos): ?>
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-lg border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="px-6 py-3 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/50">
            <h3 class="font-bold text-slate-900 dark:text-white"><?= htmlspecialchars($tituloSeccion) ?></h3>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-4 p-6">
            <?php foreach ($campos as $col => $label): ?>
            <div>
                <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400"><?= htmlspecialchars($label) ?></p>
                <p class="text-sm text-slate-900 dark:text-white break-words"><?= detalleValor($registro[$col] ?? null) ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php layoutFooter(); ?>

