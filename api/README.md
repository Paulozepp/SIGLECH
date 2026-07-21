# SIGLECH API v1.0 📡

API RESTful para acceso programático a listas de espera y gestión de egresos en SIGLECH.

**Versión**: 1.0.0  
**Última Actualización**: 21 de Julio 2026  
**Responsable**: Paulo Rebolledo  

---

## 🔐 Autenticación

Todos los endpoints requieren **Bearer Token** en el header:

```http
Authorization: Bearer {token_api}
```

### Obtener Token

Contacta a Paulo Rebolledo para recibir un token. Cada cliente API tiene:
- **Nombre único**
- **Token secreto** (hash SHA256)
- **Permisos**: `lectura`, `escritura`, o `lectura,escritura`

**Cliente de Ejemplo** (para Henry Moraga):
```
Nombre: henry_moraga_data_sync
Permisos: lectura,escritura
```

---

## 🌐 Base URL

```
http://localhost/SIGLECH/api/v1/
```

En producción cambiar a HTTPS:
```
https://siglech.servidor.cl/api/v1/
```

---

## 📚 ENDPOINTS

### 1. Listar Vigentes

**GET** `/listas_espera/vigentes`

Obtiene todas las listas de espera vigentes con filtros opcionales.

#### Parámetros

| Parámetro | Tipo | Requerido | Descripción |
|-----------|------|-----------|-------------|
| tipo | string | No | CNE, IQ, PROC (default: todos) |
| especialidad | string | No | Buscar por especialidad (parcial) |
| establecimiento | string | No | Código o nombre establecimiento |
| prioridad | string | No | CRÍTICA, ALTA, MEDIA, BAJA |
| dias_espera_min | int | No | Mínimo días de espera |
| dias_espera_max | int | No | Máximo días de espera |
| pagina | int | No | Número de página (default: 1) |
| por_pagina | int | No | Registros por página (default: 100, máx: 1000) |
| ordenar_por | string | No | dias_espera, fecha_ingreso, prioridad |

#### Ejemplo de Petición

```bash
curl -X GET \
  "http://localhost/SIGLECH/api/v1/listas_espera/vigentes?tipo=CNE&especialidad=Cardiología&pagina=1&por_pagina=50" \
  -H "Authorization: Bearer tu_token_aqui"
```

#### Ejemplo de Respuesta (200 OK)

```json
{
  "success": true,
  "datos": [
    {
      "id": 12345,
      "folio": "SIGTE-2026-00012345",
      "tipo": "CNE",
      "run": "12345678-9",
      "paciente": "Pérez García, Juan",
      "especialidad": "Cardiología",
      "est_origen": "Hospital Puerto Varas",
      "est_destino": "Hospital Castro",
      "dias_espera": 45,
      "fecha_ingreso": "2026-06-06",
      "prioridad": "MEDIA",
      "estado": "VIGENTE",
      "cie10": "I10"
    }
  ],
  "paginacion": {
    "pagina": 1,
    "por_pagina": 50,
    "total": 37320,
    "paginas_totales": 747
  },
  "metadatos": {
    "timestamp": "2026-07-21T10:30:45+00:00",
    "cliente": "henry_moraga_data_sync"
  }
}
```

---

### 2. Estadísticas

**GET** `/listas_espera/estadisticas`

Obtiene KPIs y estadísticas de listas de espera vigentes.

#### Parámetros

| Parámetro | Tipo | Requerido | Descripción |
|-----------|------|-----------|-------------|
| tipo | string | No | CNE, IQ, PROC, todos (default: todos) |
| especialidad | string | No | Filtrar por especialidad |
| establecimiento | string | No | Filtrar por establecimiento |

#### Ejemplo de Petición

```bash
curl -X GET \
  "http://localhost/SIGLECH/api/v1/listas_espera/estadisticas?tipo=CNE" \
  -H "Authorization: Bearer tu_token_aqui"
```

#### Ejemplo de Respuesta (200 OK)

```json
{
  "success": true,
  "datos": {
    "CNE": {
      "nombre": "Consulta Nueva Especialidad",
      "vigentes": 37320,
      "promedio_dias_espera": 52,
      "maximo_dias_espera": 847,
      "minimo_dias_espera": 1,
      "por_urgencia": {
        "CRÍTICA (>180 días)": 3421,
        "ALTA (90-180 días)": 8934,
        "MEDIA (30-90 días)": 15643,
        "BAJA (<30 días)": 9322
      }
    },
    "IQ": { ... },
    "PROC": { ... }
  },
  "metadatos": {
    "total_vigentes": 72884,
    "tipos_incluidos": ["CNE", "IQ", "PROC"],
    "timestamp": "2026-07-21T10:30:45+00:00",
    "cliente": "henry_moraga_data_sync"
  }
}
```

---

## 📊 Códigos de Respuesta HTTP

| Código | Significado | Ejemplo |
|--------|-------------|---------|
| **200** | OK - Solicitud exitosa | Datos retornados correctamente |
| **202** | Aceptado - Procesamiento en progreso | Importación iniciada |
| **400** | Solicitud inválida | Parámetros incorrectos |
| **401** | No autorizado | Token faltante o inválido |
| **403** | Permiso denegado | Token sin permisos para operación |
| **404** | No encontrado | Recurso no existe |
| **405** | Método no permitido | Usar POST en lugar de GET |
| **429** | Demasiadas peticiones | Rate limit excedido |
| **500** | Error del servidor | Error en base de datos |

---

## 🔄 PRÓXIMOS ENDPOINTS (Semana 2-3)

### Importación de Datos

```bash
POST /importar/csv         # Carga CSV incremental
POST /importar/json        # Carga desde scripts Python
GET  /importar/estado/{id} # Consultar progreso
```

### Gestión de Egresos

```bash
POST /egresos/crear       # Crear egreso
GET  /egresos/listar      # Listar egresos
PUT  /egresos/{id}        # Actualizar egreso
```

### Exportación de Datos

```bash
GET /exportar/cne         # Descargar CNE como CSV
GET /exportar/iq          # Descargar IQ como CSV
GET /exportar/proc        # Descargar PROC como CSV
GET /exportar/egresos     # Descargar egresos como CSV
```

---

## 🔒 Rate Limiting

**Límite**: 100 peticiones por minuto por cliente

Si se excede:
```json
{
  "success": false,
  "error": "Rate limit exceeded",
  "retry_after": 60
}
```

---

## 📋 Formatos de Respuesta

Todas las respuestas siguen este formato estándar:

```json
{
  "success": boolean,           // true si exitoso, false si error
  "datos": any,                 // Array u objeto con datos (si éxito)
  "error": string,              // Mensaje de error (si falla)
  "mensaje": string,            // Mensaje adicional (opcional)
  "paginacion": {               // (solo en endpoints paginados)
    "pagina": int,
    "por_pagina": int,
    "total": int,
    "paginas_totales": int
  },
  "metadatos": {                // Información adicional
    "timestamp": "2026-07-21T10:30:45+00:00",
    "cliente": "nombre_cliente"
  }
}
```

---

## 🐍 Ejemplo: Script Python para Henry Moraga

```python
import requests
import json
from datetime import datetime

BASE_URL = "http://localhost/SIGLECH/api/v1"
TOKEN = "tu_token_aqui"

headers = {
    "Authorization": f"Bearer {TOKEN}",
    "Content-Type": "application/json"
}

# 1. Obtener vigentes de Cardiología
response = requests.get(
    f"{BASE_URL}/listas_espera/vigentes",
    params={
        "tipo": "CNE",
        "especialidad": "Cardiología",
        "pagina": 1,
        "por_pagina": 100
    },
    headers=headers
)

if response.status_code == 200:
    datos = response.json()
    print(f"Total vigentes: {datos['paginacion']['total']}")
    for registro in datos['datos']:
        print(f"  - {registro['paciente']} ({registro['dias_espera']} días)")
else:
    print(f"Error: {response.status_code}")
    print(response.json())

# 2. Obtener estadísticas
response = requests.get(
    f"{BASE_URL}/listas_espera/estadisticas",
    params={"tipo": "CNE"},
    headers=headers
)

if response.status_code == 200:
    datos = response.json()
    cne = datos['datos']['CNE']
    print(f"CNE: {cne['vigentes']} vigentes, promedio {cne['promedio_dias_espera']} días")
else:
    print(f"Error: {response.json()}")
```

---

## 🛠️ Solución de Problemas

### Token Inválido
```json
{
  "success": false,
  "error": "Token inválido o inactivo"
}
```
**Solución**: Verificar que el token sea correcto y esté activo. Contactar a Paulo Rebolledo.

### Permiso Denegado
```json
{
  "success": false,
  "error": "Permiso denegado",
  "requerido": "escritura",
  "actual": "lectura"
}
```
**Solución**: El cliente solo tiene permisos de lectura. Contactar a Paulo Rebolledo para elevar permisos.

### Rate Limit Excedido
```json
{
  "success": false,
  "error": "Rate limit exceeded",
  "retry_after": 60
}
```
**Solución**: Esperar 60 segundos antes de reintentar.

---

## 📞 Contacto y Soporte

**Paulo Rebolledo** (Desarrollo)  
📧 paulorebolledo@gmail.com  
📞 +56 9 XXXX XXXX  

**Henry Moraga** (Data Science)  
📧 henry.moraga@example.com  

**Dra. Estela Novoa** (Clinical)  
📧 estela.novoa@example.com  

---

## 📜 Changelog

### v1.0.0 (21 Julio 2026)
- ✅ Endpoints de lectura: vigentes, estadísticas
- ✅ Autenticación por token Bearer
- ✅ Auditoría de accesos API
- ⏳ Endpoints de importación (Semana 2)
- ⏳ Endpoints de egresos (Semana 3)
- ⏳ Endpoints de exportación (Semana 3)

---

**Documentación oficial**: Última actualización: 21 de Julio 2026
