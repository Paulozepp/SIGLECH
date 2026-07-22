<?php
/**
 * layout.php - Plantilla base de SIGLECH con Dark Mode
 */

function layoutHeader($titulo = 'SIGLECH', $usuario = null, $seccion = null) {
    ?>
<!DOCTYPE html>
<html lang="es" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($titulo) ?> - SIGLECH</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        'status-success': '#10b981',
                        'status-warning': '#f59e0b',
                        'status-error': '#ef4444',
                        'status-info': '#3b82f6'
                    }
                }
            }
        }
    </script>
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

        * {
            @apply transition-colors duration-200;
        }

        /* Gradientes */
        .gradient-brand {
            @apply bg-gradient-to-br from-blue-500 to-blue-600;
        }

        .gradient-success {
            @apply bg-gradient-to-br from-green-500 to-emerald-600;
        }

        .gradient-warning {
            @apply bg-gradient-to-br from-amber-500 to-orange-600;
        }

        .gradient-danger {
            @apply bg-gradient-to-br from-red-500 to-rose-600;
        }

        /* Cards mejoradas */
        .card {
            @apply bg-white dark:bg-slate-800 rounded-xl shadow-sm dark:shadow-lg border border-slate-200 dark:border-slate-700 hover:shadow-md dark:hover:shadow-xl transition-all;
        }

        .card-highlighted {
            @apply card border-l-4 border-l-blue-500;
        }

        /* Badges */
        .badge {
            @apply inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold;
        }

        .badge-success {
            @apply bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300;
        }

        .badge-warning {
            @apply bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300;
        }

        .badge-danger {
            @apply bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300;
        }

        .badge-info {
            @apply bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300;
        }

        /* Headers degradados */
        .section-header {
            @apply text-3xl font-bold mb-2 text-gray-900 dark:text-white;
        }

        .section-subtitle {
            @apply text-gray-600 dark:text-gray-400;
        }
    </style>
    <script src="/SIGLECH/assets/theme-toggle.js" defer></script>
</head>
<body class="bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-50">
    <!-- Navbar Mejorada -->
    <nav class="bg-white dark:bg-slate-800/80 backdrop-blur-sm border-b border-slate-200 dark:border-slate-700 sticky top-0 z-40 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 py-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg">
                    <span class="text-xl">🗂️</span>
                </div>
                <div>
                    <h1 class="text-2xl font-bold bg-gradient-to-r from-blue-600 to-cyan-500 bg-clip-text text-transparent">SIGLECH</h1>
                    <span class="text-xs text-slate-500 dark:text-slate-400">v1.0</span>
                </div>
            </div>

            <div class="flex items-center gap-6">
                <?php if ($usuario): ?>
                    <div class="flex items-center gap-3">
                        <div class="text-right">
                            <p class="text-sm font-medium text-slate-900 dark:text-white">
                                <?= htmlspecialchars($usuario['nombre'] ?? $usuario['usuario']) ?>
                            </p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                <?= htmlspecialchars($usuario['rol'] ?? 'Usuario') ?>
                            </p>
                        </div>
                        <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-cyan-500 rounded-full flex items-center justify-center">
                            <span class="text-white font-bold">👤</span>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Theme Toggle -->
                <button id="theme-toggle" class="p-2 rounded-lg bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200">
                    🌙
                </button>

                <?php if ($usuario): ?>
                    <a href="/SIGLECH/auth/logout.php" class="px-4 py-2 rounded-lg bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300 hover:bg-red-200 dark:hover:bg-red-900/50 text-sm font-medium">
                        Salir
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Menú de Secciones Mejorado -->
    <nav class="bg-white dark:bg-slate-800/50 backdrop-blur-sm border-b border-slate-200 dark:border-slate-700 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 flex gap-2 overflow-x-auto">
            <a href="/SIGLECH/" class="px-4 py-3 text-sm font-medium whitespace-nowrap border-b-2 transition-colors <?= $seccion === 'dashboard' ? 'text-blue-600 dark:text-blue-400 border-blue-600 dark:border-blue-400' : 'text-slate-600 dark:text-slate-400 border-transparent hover:text-slate-900 dark:hover:text-slate-200' ?>">
                📊 Dashboard
            </a>
            <a href="/SIGLECH/modules/listas_espera/" class="px-4 py-3 text-sm font-medium whitespace-nowrap border-b-2 transition-colors <?= $seccion === 'listas' ? 'text-blue-600 dark:text-blue-400 border-blue-600 dark:border-blue-400' : 'text-slate-600 dark:text-slate-400 border-transparent hover:text-slate-900 dark:hover:text-slate-200' ?>">
                📋 Listas
            </a>
            <a href="/SIGLECH/modules/demanda_le/" class="px-4 py-3 text-sm font-medium whitespace-nowrap border-b-2 transition-colors <?= $seccion === 'demanda_le' ? 'text-blue-600 dark:text-blue-400 border-blue-600 dark:border-blue-400' : 'text-slate-600 dark:text-slate-400 border-transparent hover:text-slate-900 dark:hover:text-slate-200' ?>">
                📥 Demanda LE
            </a>
            <a href="/SIGLECH/modules/reportes/" class="px-4 py-3 text-sm font-medium whitespace-nowrap border-b-2 transition-colors <?= $seccion === 'reportes' ? 'text-blue-600 dark:text-blue-400 border-blue-600 dark:border-blue-400' : 'text-slate-600 dark:text-slate-400 border-transparent hover:text-slate-900 dark:hover:text-slate-200' ?>">
                📈 Reportes
            </a>
            <a href="/SIGLECH/modules/pacientes/" class="px-4 py-3 text-sm font-medium whitespace-nowrap border-b-2 transition-colors <?= $seccion === 'pacientes' ? 'text-blue-600 dark:text-blue-400 border-blue-600 dark:border-blue-400' : 'text-slate-600 dark:text-slate-400 border-transparent hover:text-slate-900 dark:hover:text-slate-200' ?>">
                👥 Pacientes
            </a>
            <a href="/SIGLECH/modules/integracion_python/" class="px-4 py-3 text-sm font-medium whitespace-nowrap border-b-2 transition-colors <?= $seccion === 'integracion_python' ? 'text-blue-600 dark:text-blue-400 border-blue-600 dark:border-blue-400' : 'text-slate-600 dark:text-slate-400 border-transparent hover:text-slate-900 dark:hover:text-slate-200' ?>">
                🐍 Integración Python
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

    <!-- Footer Mejorado -->
    <footer class="bg-gradient-to-b from-white to-slate-50 dark:from-slate-800/50 dark:to-slate-950 border-t border-slate-200 dark:border-slate-700 mt-16">
        <div class="max-w-7xl mx-auto px-4 py-12">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8 mb-8">
                <!-- Logo y Descripción -->
                <div>
                    <div class="flex items-center gap-2 mb-3">
                        <div class="p-2 bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg">
                            <span class="text-lg">🗂️</span>
                        </div>
                        <div>
                            <h3 class="font-bold text-slate-900 dark:text-white">SIGLECH</h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400">v1.0</p>
                        </div>
                    </div>
                    <p class="text-sm text-slate-600 dark:text-slate-400">
                        Gestión de Listas de Espera Chiloé
                    </p>
                </div>

                <!-- Sistema -->
                <div>
                    <h3 class="font-bold mb-3 text-slate-900 dark:text-white">Sistema</h3>
                    <ul class="text-sm text-slate-600 dark:text-slate-400 space-y-2">
                        <li class="flex items-center gap-2">
                            <span>🔐</span> GNU GPL v2.0
                        </li>
                        <li class="flex items-center gap-2">
                            <span>📅</span> 2026
                        </li>
                        <li class="flex items-center gap-2">
                            <span>⚡</span> API REST
                        </li>
                    </ul>
                </div>

                <!-- Equipo -->
                <div>
                    <h3 class="font-bold mb-3 text-slate-900 dark:text-white">Equipo</h3>
                    <ul class="text-sm text-slate-600 dark:text-slate-400 space-y-2">
                        <li class="flex items-center gap-2">
                            <span>🩺</span> Dra. Estela Novoa
                        </li>
                        <li class="flex items-center gap-2">
                            <span>💻</span> Ing. Paulo Rebolledo
                        </li>
                        <li class="flex items-center gap-2">
                            <span>📊</span> Ing. Henry Moraga
                        </li>
                    </ul>
                </div>

                <!-- Enlaces -->
                <div>
                    <h3 class="font-bold mb-3 text-slate-900 dark:text-white">Enlaces</h3>
                    <ul class="text-sm space-y-2">
                        <li><a href="#" class="text-blue-600 dark:text-blue-400 hover:underline">📖 Documentación</a></li>
                        <li><a href="#" class="text-blue-600 dark:text-blue-400 hover:underline">🏗️ API Reference</a></li>
                        <li><a href="mailto:paulorebolledo@gmail.com" class="text-blue-600 dark:text-blue-400 hover:underline">📧 Soporte</a></li>
                    </ul>
                </div>
            </div>

            <div class="border-t border-slate-200 dark:border-slate-700 pt-6">
                <div class="flex flex-col md:flex-row items-center justify-between gap-4">
                    <div class="text-center md:text-left text-sm text-slate-600 dark:text-slate-400">
                        <p>© 2026 Servicio de Salud Chiloé</p>
                        <p>Sub Departamento de Gestión de Oferta y la Demanda</p>
                    </div>
                    <div class="flex items-center gap-4 text-xs text-slate-500 dark:text-slate-500">
                        <a href="#" class="hover:text-slate-700 dark:hover:text-slate-300">Privacidad</a>
                        <span>•</span>
                        <a href="#" class="hover:text-slate-700 dark:hover:text-slate-300">Términos</a>
                        <span>•</span>
                        <a href="#" class="hover:text-slate-700 dark:hover:text-slate-300">Estado</a>
                    </div>
                </div>
            </div>
        </div>
    </footer>

</body>
</html>
    <?php
}
?>

