<?php
/**
 * SICOCHClient.php
 * Cliente REST para consumir datos de SICOCH
 *
 * Uso:
 * $sicoch = new SICOCHClient(SICOCH_API_BASE_URL, SICOCH_API_KEY);
 * $paciente = $sicoch->obtenerPacientePorRun('10557670-9');
 */

class SICOCHClient {
    private $base_url;
    private $api_key;
    private $timeout;
    private $last_error;

    public function __construct(string $base_url, string $api_key, int $timeout = 5) {
        $this->base_url = rtrim($base_url, '/');
        $this->api_key = $api_key;
        $this->timeout = $timeout;
        $this->last_error = null;
    }

    /**
     * Obtiene paciente por RUN
     */
    public function obtenerPacientePorRun(string $run): ?array {
        $run = urlencode($run);
        return $this->get("/api/pacientes?run={$run}");
    }

    /**
     * Obtiene paciente por ID
     */
    public function obtenerPaciente(int $id): ?array {
        return $this->get("/api/pacientes/{$id}");
    }

    /**
     * Busca pacientes por apellido
     */
    public function buscarPacientes(string $query): array {
        $query = urlencode($query);
        $result = $this->get("/api/pacientes?q={$query}");
        return is_array($result) ? $result : [];
    }

    /**
     * Obtiene todas las especialidades
     */
    public function obtenerEspecialidades(): array {
        $result = $this->get("/api/especialidades");
        return is_array($result) ? $result : [];
    }

    /**
     * Obtiene todos los establecimientos
     */
    public function obtenerEstablecimientos(): array {
        $result = $this->get("/api/establecimientos");
        return is_array($result) ? $result : [];
    }

    /**
     * Obtiene interconsultas de un paciente
     */
    public function obtenerInterconsultasPaciente(int $paciente_id): array {
        $result = $this->get("/api/interconsultas?paciente_id={$paciente_id}");
        return is_array($result) ? $result : [];
    }

    /**
     * Registra intento de contacto (POST a SICOCH)
     */
    public function registrarContacto(int $interconsulta_id, array $datos): ?array {
        return $this->post("/api/contactos", [
            'interconsulta_id' => $interconsulta_id,
            'resultado' => $datos['resultado'] ?? '',
            'observaciones' => $datos['observaciones'] ?? '',
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Request GET con autenticación
     */
    private function get(string $endpoint): ?array {
        $url = $this->base_url . $endpoint;

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => $this->buildHeaders(),
                'timeout' => $this->timeout,
                'ignore_errors' => true,
            ]
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            $this->last_error = "Connection failed: GET {$endpoint}";
            error_log("[SICOCH_API] {$this->last_error}");
            return null;
        }

        return json_decode($response, true);
    }

    /**
     * Request POST con autenticación
     */
    private function post(string $endpoint, array $data): ?array {
        $url = $this->base_url . $endpoint;
        $payload = json_encode($data, JSON_UNESCAPED_UNICODE);

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => array_merge(
                    $this->buildHeaders(),
                    ['Content-Length: ' . strlen($payload)]
                ),
                'content' => $payload,
                'timeout' => $this->timeout,
                'ignore_errors' => true,
            ]
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            $this->last_error = "Connection failed: POST {$endpoint}";
            error_log("[SICOCH_API] {$this->last_error}");
            return null;
        }

        return json_decode($response, true);
    }

    /**
     * Construye headers de autenticación
     */
    private function buildHeaders(): array {
        return [
            'Authorization: Bearer ' . $this->api_key,
            'Content-Type: application/json',
            'User-Agent: SIGLECH/1.0',
            'X-Requested-With: XMLHttpRequest',
        ];
    }

    /**
     * Obtiene último error
     */
    public function getError(): ?string {
        return $this->last_error;
    }

    /**
     * Verifica conexión con SICOCH
     */
    public function testConexion(): bool {
        $result = $this->get("/api/health.php");
        return $result !== null && isset($result['status']) && $result['status'] === 'ok';
    }
}
