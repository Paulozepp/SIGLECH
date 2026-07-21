<?php
/**
 * Clase para respuestas API estándar
 * Asegura formato JSON consistente en todos los endpoints
 */

class ApiResponse {
    private $success = true;
    private $datos = null;
    private $mensaje = null;
    private $error = null;
    private $paginacion = null;
    private $metadatos = [];

    /**
     * Constructor - inicia respuesta exitosa
     */
    public function __construct() {
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
    }

    /**
     * Establece datos de respuesta exitosa
     */
    public function setDatos($datos): self {
        $this->success = true;
        $this->datos = $datos;
        return $this;
    }

    /**
     * Establece mensaje de éxito
     */
    public function setMensaje(string $mensaje): self {
        $this->mensaje = $mensaje;
        return $this;
    }

    /**
     * Marca como error
     */
    public function setError(string $error, int $httpCode = 400): self {
        $this->success = false;
        $this->error = $error;
        http_response_code($httpCode);
        return $this;
    }

    /**
     * Establece información de paginación
     */
    public function setPaginacion(int $pagina, int $por_pagina, int $total): self {
        $this->paginacion = [
            'pagina' => $pagina,
            'por_pagina' => $por_pagina,
            'total' => $total,
            'paginas_totales' => ceil($total / $por_pagina)
        ];
        return $this;
    }

    /**
     * Agrega metadatos
     */
    public function agregarMetadato(string $clave, $valor): self {
        $this->metadatos[$clave] = $valor;
        return $this;
    }

    /**
     * Convierte a JSON y envía respuesta
     */
    public function enviar(): void {
        $respuesta = [
            'success' => $this->success
        ];

        if ($this->datos !== null) {
            $respuesta['datos'] = $this->datos;
        }

        if ($this->mensaje !== null) {
            $respuesta['mensaje'] = $this->mensaje;
        }

        if ($this->error !== null) {
            $respuesta['error'] = $this->error;
        }

        if ($this->paginacion !== null) {
            $respuesta['paginacion'] = $this->paginacion;
        }

        if (!empty($this->metadatos)) {
            $respuesta['metadatos'] = $this->metadatos;
        }

        echo json_encode($respuesta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Helper: respuesta exitosa simple
     */
    public static function exitoso($datos = null, string $mensaje = null): void {
        $resp = new self();
        if ($datos !== null) $resp->setDatos($datos);
        if ($mensaje !== null) $resp->setMensaje($mensaje);
        $resp->enviar();
    }

    /**
     * Helper: respuesta con error
     */
    public static function error(string $error, int $httpCode = 400): void {
        $resp = new self();
        $resp->setError($error, $httpCode)->enviar();
    }

    /**
     * Helper: no encontrado
     */
    public static function noEncontrado(): void {
        self::error('Recurso no encontrado', 404);
    }

    /**
     * Helper: no autorizado
     */
    public static function noAutorizado(): void {
        self::error('No autorizado', 401);
    }
}
?>
