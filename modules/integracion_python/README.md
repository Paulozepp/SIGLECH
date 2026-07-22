# 🐍 Integración Python - SIGLECH

Monitor y documentación de la integración entre el Navegador de Paciente (Python) y SIGLECH.

## 📋 Contenido

- [Acceso](#acceso)
- [Estadísticas](#estadísticas)
- [Datos de Prueba](#datos-de-prueba)
- [API Remota](#api-remota)
- [Configuración](#configuración)
- [Troubleshooting](#troubleshooting)

---

## Acceso

**URL:** `http://10.8.154.240/SIGLECH/modules/integracion_python/`

Requiere inicio de sesión en SIGLECH.

---

## Estadísticas

### Dashboard Principal

La pestaña muestra en tiempo real:

| Métrica | Descripción |
|---------|-------------|
| **Total Importaciones** | Cantidad de cargas desde Python |
| **Completadas** | Importaciones finalizadas exitosamente |
| **En Progreso** | Importaciones en proceso |
| **Con Error** | Importaciones que fallaron |

### Registros de Prueba

Monitorea registros marcados con `_id` que contenga `PRUEBA-HENRY`:

```
PRUEBA-HENRY-20260721-01
PRUEBA-HENRY-20260721-02
PRUEBA-HENRY-20260721-03
```

Estos aparecen en una sección especial con:
- Cantidad de registros por tipo (CNE/IQ/PROC)
- Fecha de última carga

---

## Datos de Prueba

### Formato JSON

Envía datos con estructura:

```json
{
  "tipo": "CNE",
  "datos": [
    {
      "_id": "PRUEBA-HENRY-20260721-01",
      "run": "12345678-9",
      "primer_apellido": "Pérez",
      "segundo_apellido": "García",
      "nombres": "Juan",
      "estab_orig": "1000",
      "estab_dest": "1100",
      "especialidad": "Cardiología",
      "prestacion": "Consulta",
      "fecha_ingreso": "2026-06-06",
      "fecha_salida": null,
      "estado": "VIGENTE",
      "dias_espera": 45,
      "cie10": "I10"
    }
  ]
}
```

### Campos Requeridos

- `_id`: Identificador único (usa prefijo PRUEBA-HENRY para testing)
- `run`: RUT del paciente (formato: XX.XXX.XXX-X o XXXXXXXX-X)
- `primer_apellido`: Primer apellido
- `segundo_apellido`: Segundo apellido
- `nombres`: Nombres
- `estab_orig`: Código establecimiento origen
- `estab_dest`: Código establecimiento destino
- `especialidad`: Nombre especialidad
- `prestacion`: Tipo prestación
- `fecha_ingreso`: Fecha en formato YYYY-MM-DD
- `estado`: VIGENTE o EGRESADO
- `dias_espera`: Número de días esperando
- `cie10`: Código CIE-10

### Campos Opcionales

- `fecha_salida`: Fecha de salida (null si VIGENTE)
- `prioridad`: Se calcula automáticamente de dias_espera

---

## API Remota

### Endpoint Importación

```bash
POST http://10.8.154.240/SIGLECH/api/v1/importar/json

Header:
  Authorization: Bearer 552821b77ba50f33fe49c3046f6dea7a
  Content-Type: application/json

Body: JSON con estructura anterior
```

### Respuesta Exitosa (HTTP 202)

```json
{
  "success": true,
  "datos": {
    "importacion_id": "IMP-2026-07-21-6a5fd869bdd9c",
    "tipo": "CNE",
    "total_registros": 1,
    "registros_exitosos": 1,
    "registros_fallidos": 0,
    "tasa_exito": "100%"
  }
}
```

### Obtener Estadísticas (JSON)

```bash
GET http://10.8.154.240/SIGLECH/modules/integracion_python/api.php
```

Retorna:
- Estadísticas globales
- Estadísticas por tipo (CNE/IQ/PROC)
- Últimas 5 importaciones
- Registros de prueba

---

## Configuración

### Token Bearer

```
Token: 552821b77ba50f33fe49c3046f6dea7a
Permisos: lectura, escritura
Estado: ACTIVO
```

Para crear nuevos tokens, ejecutar en la BD:

```php
// En C:\xampp\htdocs\SIGLECH\api\v1\crear-token-test.php
// Retorna token nuevo + hash SHA256
```

### Base de Datos

Tablas de destino según tipo:
- `CNE` → `demanda_cne`
- `IQ` → `demanda_iq`
- `PROC` → `demanda_proc`

Tabla de auditoría:
- `importaciones` (registro principal)
- `importacion_detalles` (detalle por registro)

---

## Troubleshooting

### Error 401: Token Requerido

**Problema:** El Authorization header no está siendo enviado

**Solución:**
```bash
# Verificar que usas:
curl -H "Authorization: Bearer 552821b77ba50f33fe49c3046f6dea7a" \
  -X POST \
  -H "Content-Type: application/json" \
  -d @datos.json \
  http://10.8.154.240/SIGLECH/api/v1/importar/json
```

### Error 400: JSON Inválido

**Problema:** El JSON enviado tiene formato incorrecto

**Solución:**
- Validar JSON en: https://jsonlint.com/
- Confirmar que `tipo` sea uno de: CNE, IQ, PROC
- Confirmar que `datos` sea un array no vacío

### Error 500: Unknown Column

**Problema:** Una de las columnas no existe en la tabla

**Verificación:**
- Nombre correcto: `F_ENTRADA` (no F_INGRE)
- No incluir `PRIORIDAD_LE` (se calcula automáticamente)
- Usar nombres exactos de campos

### Importación Tarda Mucho

La importación es asincrónica. Verificar estado:

```bash
# Con el importacion_id retornado:
curl -H "Authorization: Bearer {token}" \
  "http://10.8.154.240/SIGLECH/api/v1/importar/estado?importacion_id=IMP-2026-07-21-..."
```

---

## Integración Python

### Usar siglech_client.py

```python
from siglech_client import SiglechClient

cliente = SiglechClient(
    base_url="http://10.8.154.240/SIGLECH",
    token="552821b77ba50f33fe49c3046f6dea7a"
)

# Importar datos
resultado = cliente.importar_json(tipo='CNE', datos=[
    {
        "_id": "PRUEBA-HENRY-20260721-01",
        "run": "12345678-9",
        # ... resto de campos
    }
])

print(resultado['importacion_id'])
```

### Script Standalone

```python
import requests
import json

token = "552821b77ba50f33fe49c3046f6dea7a"
url = "http://10.8.154.240/SIGLECH/api/v1/importar/json"

headers = {
    "Authorization": f"Bearer {token}",
    "Content-Type": "application/json"
}

payload = {
    "tipo": "CNE",
    "datos": [...]
}

response = requests.post(url, json=payload, headers=headers)
print(response.json())
```

---

## Monitoreo

### Dashboard Web

Acceder a: `http://10.8.154.240/SIGLECH/modules/integracion_python/`

Muestra:
- Estadísticas en tiempo real
- Últimas importaciones
- Registros de prueba
- Instrucciones

### API JSON

Consumir: `http://10.8.154.240/SIGLECH/modules/integracion_python/api.php`

Útil para dashboards, monitores, alertas automáticas.

---

## Historial de Cambios

| Fecha | Cambio | Status |
|-------|--------|--------|
| 2026-07-21 | Fix: F_INGRE → F_ENTRADA | ✅ |
| 2026-07-21 | Fix: Remover PRIORIDAD_LE | ✅ |
| 2026-07-21 | Crear módulo integracion_python | ✅ |

---

**Contacto:** Para soporte técnico, consultar logs en:
- API: `/logs/api-*.log`
- Sistema: `phpinfo()` → Logs

Last updated: 2026-07-21
