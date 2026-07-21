<?php
/**
 * SIGLECH - Instalador Simple (Alternativo)
 *
 * Si install.php falla, intenta este script
 * Acceder a: http://localhost/SIGLECH/setup-simple.php
 */

require_once __DIR__ . '/config.php';

$mensaje = '';
$exito = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // 1. Conectar sin BD
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';charset=' . DB_CHARSET,
            DB_USER,
            DB_PASS
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $mensaje .= "✅ Conectado a MySQL\n";

        // 2. Crear BD
        $pdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $mensaje .= "✅ Base de datos creada\n";

        // 3. Usar BD
        $pdo->exec("USE " . DB_NAME);
        $mensaje .= "✅ Base de datos seleccionada\n";

        // 4. Ejecutar cada tabla manualmente (más seguro)
        $tableSQL = [
            // Usuarios
            "CREATE TABLE IF NOT EXISTS usuarios (
                id INT AUTO_INCREMENT PRIMARY KEY,
                usuario VARCHAR(100) UNIQUE NOT NULL,
                nombre VARCHAR(200) NOT NULL,
                email VARCHAR(150),
                password_hash VARCHAR(255) NOT NULL,
                rol ENUM('admin', 'gestor', 'consultor', 'viewer') DEFAULT 'consultor',
                activo TINYINT DEFAULT 1,
                ultimo_acceso DATETIME,
                creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
                actualizado_en DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_usuario (usuario),
                INDEX idx_activo (activo)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            // Interconsultas
            "CREATE TABLE IF NOT EXISTS lista_espera_interconsultas (
                id INT AUTO_INCREMENT PRIMARY KEY,
                folio_sic VARCHAR(20) UNIQUE NOT NULL,
                paciente_id INT NOT NULL,
                especialidad_id INT,
                establecimiento_destino_id INT,
                tipo_lista VARCHAR(50) NOT NULL,
                prioridad ENUM('ALTA', 'MEDIA', 'BAJA') DEFAULT 'MEDIA',
                estado ENUM('PENDIENTE', 'EN_GESTION', 'CITADA', 'ATENDIDA', 'CERRADA') DEFAULT 'PENDIENTE',
                fecha_ingreso DATE,
                fuente_dato VARCHAR(50),
                hipotesis_dx TEXT,
                intentos_contacto INT DEFAULT 0,
                es_oncologico TINYINT DEFAULT 0,
                anualidad YEAR,
                creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
                actualizado_en DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_paciente (paciente_id),
                INDEX idx_estado (estado),
                INDEX idx_prioridad (prioridad),
                INDEX idx_fecha_ingreso (fecha_ingreso),
                INDEX idx_tipo_lista (tipo_lista),
                INDEX idx_especialidad (especialidad_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            // Gestiones de contacto
            "CREATE TABLE IF NOT EXISTS lista_espera_gestiones_contacto (
                id INT AUTO_INCREMENT PRIMARY KEY,
                interconsulta_id INT NOT NULL,
                resultado_contacto VARCHAR(100),
                fecha_gestion DATE,
                hora_gestion TIME,
                observaciones TEXT,
                usuario_id INT,
                creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (interconsulta_id) REFERENCES lista_espera_interconsultas(id),
                INDEX idx_interconsulta (interconsulta_id),
                INDEX idx_fecha_gestion (fecha_gestion)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            // Fichas de egreso
            "CREATE TABLE IF NOT EXISTS lista_espera_fichas_egreso (
                id INT AUTO_INCREMENT PRIMARY KEY,
                interconsulta_id INT NOT NULL,
                codigo_egreso VARCHAR(50),
                fecha_egreso DATE,
                observaciones TEXT,
                creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
                actualizado_en DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (interconsulta_id) REFERENCES lista_espera_interconsultas(id),
                INDEX idx_interconsulta (interconsulta_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            // Alertas
            "CREATE TABLE IF NOT EXISTS lista_espera_alertas (
                id INT AUTO_INCREMENT PRIMARY KEY,
                interconsulta_id INT NOT NULL,
                tipo_alerta VARCHAR(50),
                dias_espera INT,
                estado_alerta ENUM('ACTIVA', 'RESUELTA', 'IGNORADA') DEFAULT 'ACTIVA',
                fecha_alerta DATETIME DEFAULT CURRENT_TIMESTAMP,
                fecha_resolucion DATETIME,
                creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (interconsulta_id) REFERENCES lista_espera_interconsultas(id),
                INDEX idx_interconsulta (interconsulta_id),
                INDEX idx_estado (estado_alerta)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            // Auditoría
            "CREATE TABLE IF NOT EXISTS lista_espera_auditoria (
                id INT AUTO_INCREMENT PRIMARY KEY,
                usuario_id INT,
                tabla_afectada VARCHAR(100),
                registro_id INT,
                accion VARCHAR(50),
                datos_anteriores JSON,
                datos_nuevos JSON,
                timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
                ip_address VARCHAR(45),
                INDEX idx_usuario (usuario_id),
                INDEX idx_tabla (tabla_afectada),
                INDEX idx_timestamp (timestamp)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            // Log de sincronización
            "CREATE TABLE IF NOT EXISTS lista_espera_sincronizacion_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                origen VARCHAR(50),
                registros_nuevos INT DEFAULT 0,
                registros_actualizados INT DEFAULT 0,
                registros_eliminados INT DEFAULT 0,
                duracion_ms INT,
                estado VARCHAR(20),
                mensaje TEXT,
                timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_origen (origen),
                INDEX idx_timestamp (timestamp)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            // Cache de reportes
            "CREATE TABLE IF NOT EXISTS lista_espera_reportes_cache (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nombre_reporte VARCHAR(100),
                datos_json LONGTEXT,
                fecha_generacion DATETIME,
                fecha_expiracion DATETIME,
                INDEX idx_nombre (nombre_reporte),
                INDEX idx_expiracion (fecha_expiracion)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ];

        foreach ($tableSQL as $sql) {
            $pdo->exec($sql);
        }

        $mensaje .= "✅ Todas las tablas creadas\n";

        // 5. Insertar usuario admin
        $adminPassword = password_hash('admin', PASSWORD_ARGON2ID, [
            'memory_cost' => 2048,
            'time_cost' => 4,
            'threads' => 3,
        ]);

        $pdo->exec("INSERT IGNORE INTO usuarios (usuario, nombre, email, password_hash, rol)
                    VALUES ('admin', 'Administrador SIGLECH', 'admin@salud.cl', '$adminPassword', 'admin')");

        $mensaje .= "✅ Usuario admin creado\n";

        // 6. Verificar tablas
        $resultado = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '" . DB_NAME . "' AND TABLE_NAME LIKE 'lista_espera_%'");
        $tablaCount = $resultado->fetchColumn();

        $mensaje .= "✅ Tablas verificadas: $tablaCount tablas creadas\n";

        $exito = true;
        $mensaje .= "\n✅ ¡INSTALACIÓN COMPLETADA EXITOSAMENTE!\n";
        $mensaje .= "\n🔑 Datos de acceso:\n";
        $mensaje .= "   Usuario: admin\n";
        $mensaje .= "   Contraseña: admin\n";
        $mensaje .= "\n⚠️  IMPORTANTE: Cambia la contraseña en el primer login\n";

    } catch (Exception $e) {
        $exito = false;
        $mensaje = "❌ Error: " . htmlspecialchars($e->getMessage()) . "\n\n";
        $mensaje .= "Soluciones:\n";
        $mensaje .= "1. Verifica que MySQL está corriendo\n";
        $mensaje .= "2. Verifica usuario/contraseña en config.php\n";
        $mensaje .= "3. Intenta recargar la página\n";
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Alternativo - SIGLECH</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-slate-50 to-slate-100">
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="max-w-2xl w-full bg-white rounded-2xl shadow-xl p-8">

            <h1 class="text-4xl font-bold text-slate-900 mb-2">🗂️ SIGLECH Setup Alternativo</h1>
            <p class="text-slate-600 mb-8">Si el install.php falla, este es el plan B</p>

            <?php if ($exito): ?>
                <div class="bg-emerald-50 border-2 border-emerald-500 rounded-lg p-6 mb-6">
                    <h2 class="text-2xl font-bold text-emerald-900 mb-4">✅ ¡Éxito!</h2>
                    <div class="bg-slate-900 text-emerald-400 p-4 rounded-lg mb-4 text-sm font-mono whitespace-pre-wrap">
                        <?= htmlspecialchars($mensaje) ?>
                    </div>
                    <a href="/SIGLECH/index.php" class="block w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3 px-4 rounded-lg text-center transition">
                        Ir al Dashboard →
                    </a>
                </div>
            <?php else: ?>
                <div class="mb-6">
                    <p class="text-slate-600 mb-6">
                        Este script instala SIGLECH creando las tablas directamente (sin parser de SQL).
                        Es más lento pero más confiable que install.php.
                    </p>

                    <?php if (!empty($mensaje)): ?>
                        <div class="bg-red-50 border-2 border-red-500 rounded-lg p-6 mb-6">
                            <h3 class="text-xl font-bold text-red-900 mb-4">❌ Error</h3>
                            <div class="bg-slate-900 text-red-400 p-4 rounded-lg text-sm font-mono whitespace-pre-wrap">
                                <?= htmlspecialchars($mensaje) ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-4 px-6 rounded-lg transition text-lg">
                            🚀 Instalar Ahora
                        </button>
                    </form>
                </div>
            <?php endif; ?>

            <div class="mt-8 pt-8 border-t border-slate-200 text-sm text-slate-600">
                <p>Si esto también falla, intenta con <code class="bg-slate-100 px-2 py-1 rounded">install-siglech.bat</code></p>
            </div>

        </div>
    </div>
</body>
</html>

