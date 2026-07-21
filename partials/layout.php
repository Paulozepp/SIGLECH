<?php
/**
 * layout.php - Plantilla base de SIGLECH
 */

function layoutHeader($titulo = 'SIGLECH', $usuario = null, $seccion = null) {
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($titulo) ?> - SIGLECH</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --brand-50: #eff6ff;
            --brand-100: #dbeafe;
            --brand-200: #bfdbfe;
            --brand-300: #93c5fd;
            --brand-400: #60a5fa;
            --brand-500: #3b82f6;
            --brand-600: #2563eb;
            --brand-700: #1d4ed8;
            --brand-800: #1e40af;
            --brand-900: #1e3a8a;
        }

        @media (prefers-color-scheme: dark) {
            body {
                background-color: #0f172a;
            }
        }
    </style>
</head>
<body class="bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-slate-50">
    <!-- Navbar -->
    <nav class="bg-white dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700 sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 py-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-brand-600">🗂️ SIGLECH</h1>
                <span class="text-sm text-slate-600 dark:text-slate-400">v1.0</span>
            </div>

            <div class="flex items-center gap-4">
                <?php if ($usuario): ?>
                    <span class="text-sm text-slate-600 dark:text-slate-400">
                        👤 <?= htmlspecialchars($usuario['nombre'] ?? $usuario['usuario']) ?>
                    </span>
                    <a href="/SIGLECH//SIGLECH/auth/logout.php" class="text-sm text-slate-600 dark:text-slate-400 hover:text-brand-600">
                        Salir
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Menú de secciones -->
    <nav class="bg-white dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700">
        <div class="max-w-7xl mx-auto px-4 py-3 flex gap-6 overflow-x-auto">
            <a href="/SIGLECH/" class="px-4 py-2 text-sm font-medium <?= $seccion === 'dashboard' ? 'text-brand-600 border-b-2 border-brand-600' : 'text-slate-600 dark:text-slate-400' ?>">
                📊 Dashboard
            </a>
            <a href="/SIGLECH//SIGLECH/modules/listas_espera/" class="px-4 py-2 text-sm font-medium <?= $seccion === 'listas' ? 'text-brand-600 border-b-2 border-brand-600' : 'text-slate-600 dark:text-slate-400' ?>">
                📋 Listas
            </a>
            <a href="/SIGLECH//SIGLECH/modules/demanda_le/" class="px-4 py-2 text-sm font-medium <?= $seccion === 'demanda_le' ? 'text-brand-600 border-b-2 border-brand-600' : 'text-slate-600 dark:text-slate-400' ?>">
                📥 Demanda LE
            </a>
            <a href="/SIGLECH//SIGLECH/modules/reportes/" class="px-4 py-2 text-sm font-medium <?= $seccion === 'reportes' ? 'text-brand-600 border-b-2 border-brand-600' : 'text-slate-600 dark:text-slate-400' ?>">
                📈 Reportes
            </a>
            <a href="/SIGLECH//SIGLECH/modules/pacientes/" class="px-4 py-2 text-sm font-medium <?= $seccion === 'pacientes' ? 'text-brand-600 border-b-2 border-brand-600' : 'text-slate-600 dark:text-slate-400' ?>">
                👥 Pacientes
            </a>
        </div>
    </nav>

    <!-- Contenido Principal -->
    <main class="max-w-7xl mx-auto px-4 py-8">
    <?php
}

function layoutFooter() {
    ?>
    </main>

    <!-- Footer -->
    <footer class="bg-white dark:bg-slate-800 border-t border-slate-200 dark:border-slate-700 mt-12">
        <div class="max-w-7xl mx-auto px-4 py-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-8">
                <!-- Información del Sistema -->
                <div>
                    <h3 class="font-bold mb-3">Sistema</h3>
                    <ul class="text-sm text-slate-600 dark:text-slate-400 space-y-1">
                        <li>🗂️ SIGLECH v1.0</li>
                        <li>📅 2026</li>
                        <li>🔒 GNU GPL v2.0</li>
                    </ul>
                </div>

                <!-- Equipo -->
                <div>
                    <h3 class="font-bold mb-3">Equipo de Desarrollo</h3>
                    <ul class="text-sm text-slate-600 dark:text-slate-400 space-y-1">
                        <li>🩺 Dra. Estela Novoa - Clinical</li>
                        <li>💻 Ing. Paulo Rebolledo - Backend</li>
                        <li>📊 Ing. Henry Moraga - Data Science</li>
                    </ul>
                </div>

                <!-- Enlaces -->
                <div>
                    <h3 class="font-bold mb-3">Enlaces</h3>
                    <ul class="text-sm text-slate-600 dark:text-slate-400 space-y-1">
                        <li><a href="README.md" class="hover:text-brand-600">📖 README</a></li>
                        <li><a href="ARQUITECTURA_SIGLECH_API.md" class="hover:text-brand-600">🏗️ Arquitectura</a></li>
                        <li><a href="mailto:paulorebolledo@gmail.com" class="hover:text-brand-600">📧 Contacto</a></li>
                    </ul>
                </div>
            </div>

            <div class="border-t border-slate-200 dark:border-slate-700 pt-4 text-center text-sm text-slate-600 dark:text-slate-400">
                <p>© 2026 Servicio de Salud Chiloé - SIGLECH v1.0</p>
                <p>Sub Departamento de Gestión de Oferta y la Demanda</p>
            </div>
        </div>
    </footer>

</body>
</html>
    <?php
}
?>

