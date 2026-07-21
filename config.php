<?php
/**
 * config.php - SIGLECH
 * Configuración de la aplicación
 *
 * ⚠️ NO compartir en producción - usar .env
 */

// Zona horaria
date_default_timezone_set('America/Santiago');

// BASE DE DATOS (Compartida con SICOCH)
define('DB_HOST', 'localhost');
define('DB_NAME', 'sicoch_referencia');  // BD compartida
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// BD de Demanda de Listas de Espera (CNE / IQ / PROC)
define('DB_NAME_SIGLECH', 'siglech');

// API DE SICOCH (Consumida por SIGLECH)
define('SICOCH_API_BASE_URL', 'http://localhost/SICOCH');
define('SICOCH_API_KEY', 'siglech_api_key_2026');  // Generar segura en producción
define('SICOCH_API_TIMEOUT', 5);  // segundos

// CONFIGURACIÓN DE SIGLECH
define('SIGLECH_NAME', 'SIGLECH - Gestión de Listas de Espera Chiloé');
define('SIGLECH_VERSION', '1.0.0');
define('SIGLECH_DEBUG', true);  // false en producción
define('SIGLECH_LOG_PATH', __DIR__ . '/logs');

// SESIÓN
define('SESSION_TIMEOUT', 3600);  // 1 hora
define('SESSION_SECURE', false);  // true en HTTPS
define('SESSION_HTTPONLY', true);

// APLICACIÓN
define('APP_URL', 'http://localhost/SIGLECH');
define('APP_ENVIRONMENT', 'development');  // development, staging, production

// SINCRONIZACIÓN
define('SYNC_INTERVAL', 4 * 60 * 60);  // 4 horas en segundos
define('SYNC_AUTO_ENABLED', true);

// CATEGORÍAS DE LISTAS DE ESPERA
define('CATEGORIAS_LISTAS', [
    'NO_GES' => [
        'label' => 'Interconsultas No GES',
        'icono' => '📋',
        'descripcion' => 'Consultas sin garantía GES',
        'tipos' => ['CN_NO_GES', 'CIRUGIA_NO_GES', 'IQ_MENOR_NO_GES', 'APOYO_DX'],
        'prefijo_folio' => 'ING',
    ],
    'GES' => [
        'label' => 'Interconsultas GES',
        'icono' => '🩺',
        'descripcion' => 'Prestaciones con garantía GES/AUGE',
        'tipos' => ['CN_GES', 'CN_ODONTO_GES', 'CIRUGIA_GES', 'IQ_MAYOR_GES'],
        'prefijo_folio' => 'IGE',
    ],
    'ONCOLOGICO' => [
        'label' => 'Paciente Oncológico',
        'icono' => '🎗️',
        'descripcion' => 'Casos oncológicos en seguimiento',
        'tipos' => ['ONCOLOGICO'],
        'prefijo_folio' => 'ONC',
    ],
    'ECICEP' => [
        'label' => 'Paciente ECICEP',
        'icono' => '🫀',
        'descripcion' => 'Estrategia de Cuidado Integral Centrado en Personas',
        'tipos' => ['ECICEP'],
        'prefijo_folio' => 'ECI',
    ],
    'CASO_COMPLEJO' => [
        'label' => 'Paciente Casos Complejos',
        'icono' => '🧩',
        'descripcion' => 'Casos clínicos complejos que requieren gestión coordinada',
        'tipos' => ['CASO_COMPLEJO'],
        'prefijo_folio' => 'CPX',
    ],
    'MACRORED' => [
        'label' => 'Atención Macrored',
        'icono' => '🌐',
        'descripcion' => 'Pacientes en atención coordinada entre establecimientos',
        'tipos' => ['MACRORED'],
        'prefijo_folio' => 'MRD',
    ],
    'HOSPITAL_DIGITAL' => [
        'label' => 'Hospital Digital',
        'icono' => '💻',
        'descripcion' => 'Consultas y seguimientos a través de hospital digital',
        'tipos' => ['HOSPITAL_DIGITAL'],
        'prefijo_folio' => 'HDG',
    ],
    'TRASLADOS_COORDINADOS' => [
        'label' => 'Traslados Coordinados',
        'icono' => '🚑',
        'descripcion' => 'Gestión de traslados coordinados entre establecimientos',
        'tipos' => ['TRASLADOS_COORDINADOS'],
        'prefijo_folio' => 'TRC',
    ],
    'INTERCONSULTAS_QUIRURGICAS' => [
        'label' => 'Interconsultas Quirúrgicas',
        'icono' => '🔪',
        'descripcion' => 'Evaluaciones preoperatorias para procedimientos quirúrgicos',
        'tipos' => ['INTERCONSULTAS_QUIRURGICAS'],
        'prefijo_folio' => 'IQX',
    ],
    'INTERCONSULTAS_PROCEDIMIENTOS' => [
        'label' => 'Interconsultas Procedimientos',
        'icono' => '🩹',
        'descripcion' => 'Procedimientos diagnósticos, terapéuticos e intervención',
        'tipos' => ['INTERCONSULTAS_PROCEDIMIENTOS'],
        'prefijo_folio' => 'IPD',
    ],
]);

// ESTADOS DE INTERCONSULTAS
define('ESTADOS_INTERCONSULTA', [
    'PENDIENTE' => 'Pendiente',
    'EN_GESTION' => 'En Gestión',
    'CITADA' => 'Citada',
    'ATENDIDA' => 'Atendida',
    'CERRADA' => 'Cerrada',
]);

// PRIORIDADES
define('PRIORIDADES', [
    'ALTA' => 'Alta',
    'MEDIA' => 'Media',
    'BAJA' => 'Baja',
]);

// ROLES DE USUARIO
define('ROLES_USUARIO', [
    'admin' => 'Administrador',
    'gestor' => 'Gestor de Listas',
    'consultor' => 'Consultor',
    'viewer' => 'Visualización',
]);

// UMBRALES DE ALERTAS
define('ALERTA_DIAS_ESPERA', 60);  // Alertar después de 60 días
define('ALERTA_INTENTOS_CONTACTO', 3);  // Alertar si no hay contacto en 3 intentos

// PAGINACIÓN
define('REGISTROS_POR_PAGINA', 50);
define('LIMITE_BUSQUEDA', 200);

// CACHE
define('CACHE_ENABLED', true);
define('CACHE_DURATION', 3600);  // 1 hora
define('CACHE_DIR', __DIR__ . '/cache');

// ERROR HANDLING
error_reporting(E_ALL);
ini_set('display_errors', SIGLECH_DEBUG ? 1 : 0);
ini_set('log_errors', 1);
ini_set('error_log', SIGLECH_LOG_PATH . '/errors.log');
