<?php
/**
 * Test Rápido - Integración Python
 * Simula envío de datos de prueba a la API
 */

session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../auth/guard.php';
require_once __DIR__ . '/../../partials/layout.php';

// Verificar sesión
$user = requiereLogin();

$test_ejecutado = false;
$resultado_test = null;
$error_msg = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ejecutar_test'])) {
    $token = '552821b77ba50f33fe49c3046f6dea7a';
    $base_url = 'http://10.8.154.240/SIGLECH';
    $endpoint = '/api/v1/importar/json';

    // Datos de prueba
    $payload = [
        'tipo' => 'CNE',
        'datos' => [
            [
                '_id' => 'PRUEBA-HENRY-' . date('YmdHis') . '-01',
                'run' => '12345678-9',
                'primer_apellido' => 'Test',
                'segundo_apellido' => 'Automático',
                'nombres' => 'Registro',
                'estab_orig' => '1000',
                'estab_dest' => '1100',
                'especialidad' => 'Cardiología',
                'prestacion' => 'Consulta',
                'fecha_ingreso' => date('Y-m-d'),
                'fecha_salida' => null,
                'estado' => 'VIGENTE',
                'dias_espera' => rand(10, 90),
                'cie10' => 'I10'
            ]
        ]
    ];

    // Hacer request CURL
    $ch = curl_init($base_url . $endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    $test_ejecutado = true;

    if ($curl_error) {
        $error_msg = "Error CURL: $curl_error";
    } else {
        $resultado_test = json_decode($response, true);
    }
}

?>
<?php
layoutHeader('Test Integración Python', $user, 'integracion_python');
?>
        <div class="mb-8">
            <h1 class="text-3xl font-bold mb-2 text-gray-900 dark:text-white">🧪 Test de Integración Python</h1>
            <p class="text-slate-600 dark:text-gray-400">Prueba rápida de conectividad y envío de datos</p>
        </div>

        <!-- Panel de Test -->
        <div class="bg-white dark:bg-slate-800/50 backdrop-blur border border-slate-200 dark:border-slate-700 rounded-lg p-6 mb-8">
            <h2 class="text-xl font-bold mb-4 text-gray-900 dark:text-white">📤 Ejecutar Test</h2>

            <form method="POST" class="mb-6">
                <p class="text-slate-700 dark:text-gray-400 mb-4">
                    Esta prueba enviará 1 registro de prueba al endpoint de importación.
                </p>

                <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-500/30 rounded p-4 mb-4">
                    <p class="text-blue-700 dark:text-blue-200 text-sm">
                        <strong>Token:</strong> 552821b77ba50f33fe49c3046f6dea7a<br>
                        <strong>Endpoint:</strong> POST /api/v1/importar/json<br>
                        <strong>Tipo:</strong> CNE<br>
                        <strong>Registros:</strong> 1
                    </p>
                </div>

                <button type="submit" name="ejecutar_test" value="1"
                    class="bg-blue-600 hover:bg-blue-700 dark:bg-blue-700 dark:hover:bg-blue-600 text-white font-bold py-2 px-6 rounded">
                    ▶️ Ejecutar Test
                </button>
            </form>

            <?php if (!empty($error_msg)): ?>
            <div class="bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-500/50 rounded-lg p-4 mb-6">
                <p class="text-red-700 dark:text-red-200">❌ Error: <?php echo htmlspecialchars($error_msg); ?></p>
            </div>
            <?php endif; ?>

            <?php if ($test_ejecutado && !empty($resultado_test)): ?>
            <div class="bg-slate-100 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-lg p-4">
                <h3 class="font-bold mb-3 text-gray-900 dark:text-white">📊 Resultado del Test</h3>

                <?php if ($resultado_test['success']): ?>
                <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-500/30 rounded p-4 mb-4">
                    <p class="text-green-700 dark:text-green-200 font-bold mb-2">✅ Test Exitoso</p>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div class="bg-slate-200 dark:bg-slate-700 rounded p-3">
                        <div class="text-slate-600 dark:text-gray-400 text-sm">Importación ID</div>
                        <div class="font-mono text-green-600 dark:text-green-300 break-all">
                            <?php echo htmlspecialchars($resultado_test['datos']['importacion_id']); ?>
                        </div>
                    </div>
                    <div class="bg-slate-200 dark:bg-slate-700 rounded p-3">
                        <div class="text-slate-600 dark:text-gray-400 text-sm">Tipo</div>
                        <div class="font-bold text-blue-600 dark:text-blue-300">
                            <?php echo htmlspecialchars($resultado_test['datos']['tipo']); ?>
                        </div>
                    </div>
                    <div class="bg-slate-200 dark:bg-slate-700 rounded p-3">
                        <div class="text-slate-600 dark:text-gray-400 text-sm">Registros Exitosos</div>
                        <div class="font-bold text-green-600 dark:text-green-300 text-xl">
                            <?php echo $resultado_test['datos']['registros_exitosos']; ?>/<?php echo $resultado_test['datos']['total_registros']; ?>
                        </div>
                    </div>
                    <div class="bg-slate-200 dark:bg-slate-700 rounded p-3">
                        <div class="text-slate-600 dark:text-gray-400 text-sm">Tasa de Éxito</div>
                        <div class="font-bold text-green-600 dark:text-green-300">
                            <?php echo htmlspecialchars($resultado_test['datos']['tasa_exito']); ?>
                        </div>
                    </div>
                </div>

                <div class="bg-slate-200 dark:bg-slate-900 rounded p-3 mb-4">
                    <p class="text-slate-600 dark:text-gray-400 text-sm mb-2">Mensaje:</p>
                    <p class="text-slate-800 dark:text-gray-200 font-mono text-sm">
                        <?php echo htmlspecialchars($resultado_test['mensaje']); ?>
                    </p>
                </div>
                <?php else: ?>
                <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-500/30 rounded p-4">
                    <p class="text-red-700 dark:text-red-200 font-bold mb-2">❌ Test Falló</p>
                    <p class="text-red-600 dark:text-red-100 text-sm">
                        <?php echo htmlspecialchars($resultado_test['error'] ?? 'Error desconocido'); ?>
                    </p>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Instrucciones -->
        <div class="bg-white dark:bg-slate-800/50 backdrop-blur border border-slate-200 dark:border-slate-700 rounded-lg p-6">
            <h2 class="text-xl font-bold mb-4 text-gray-900 dark:text-white">📚 Instrucciones para Python</h2>

            <div class="space-y-4">
                <div>
                    <h3 class="font-semibold text-blue-600 dark:text-blue-300 mb-2">1. Instalación</h3>
                    <div class="bg-slate-100 dark:bg-slate-900 rounded p-3 font-mono text-sm text-slate-700 dark:text-gray-300">
                        pip install requests python-dotenv
                    </div>
                </div>

                <div>
                    <h3 class="font-semibold text-blue-600 dark:text-blue-300 mb-2">2. Script Básico</h3>
                    <div class="bg-slate-100 dark:bg-slate-900 rounded p-3 font-mono text-sm text-slate-700 dark:text-gray-300 overflow-x-auto">
<pre>import requests
import json

token = "552821b77ba50f33fe49c3046f6dea7a"
url = "http://10.8.154.240/SIGLECH/api/v1/importar/json"

data = {
    "tipo": "CNE",
    "datos": [
        {
            "_id": "PRUEBA-HENRY-01",
            "run": "12345678-9",
            "primer_apellido": "Test",
            "segundo_apellido": "Auto",
            "nombres": "Registro",
            "estab_orig": "1000",
            "estab_dest": "1100",
            "especialidad": "Cardiología",
            "prestacion": "Consulta",
            "fecha_ingreso": "2026-07-21",
            "estado": "VIGENTE",
            "dias_espera": 45,
            "cie10": "I10"
        }
    ]
}

headers = {
    "Authorization": f"Bearer {token}",
    "Content-Type": "application/json"
}

response = requests.post(url, json=data, headers=headers)
print(response.json())</pre>
                    </div>
                </div>

                <div>
                    <h3 class="font-semibold text-blue-600 dark:text-blue-300 mb-2">3. Verificar Datos</h3>
                    <p class="text-slate-700 dark:text-gray-400 text-sm">
                        Accede a: <a href="/SIGLECH/modules/integracion_python/" class="text-blue-600 dark:text-blue-300 hover:underline">
                            /SIGLECH/modules/integracion_python/
                        </a>
                        para ver los datos importados en el dashboard.
                    </p>
                </div>
            </div>
        </div>

<?php layoutFooter(); ?>
