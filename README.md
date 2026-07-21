# 🗂️ SIGLECH v1.0

**Gestión de Listas de Espera Chiloé**

Sistema independiente que se comunica con SICOCH vía API REST para gestionar listas de espera del Servicio de Salud Chiloé.

---

## 🎯 Características

✅ **Sistema Independiente** - Funciona sin SICOCH  
✅ **Comunicación vía API** - REST API a SICOCH  
✅ **BD Compartida** - Tabla pacientes sincronizada  
✅ **10 Categorías** - No GES, GES, Oncológico, ECICEP, etc.  
✅ **Reportes Avanzados** - KPIs, alertas, dashboards  
✅ **Norma 118 MINSAL** - Fichas de egreso completas  
✅ **Autenticación Local** - Usuarios y roles  
✅ **Auditoría Completa** - Trazabilidad de acciones  

---

## 📁 Estructura

```
SIGLECH/
├── config.php                 # Configuración
├── db.php                     # Conexión BD
├── index.php                  # Dashboard principal
├── auth/                      # Autenticación
├── lib/                       # Clases principales
│   └── SICOCHClient.php      # Cliente API de SICOCH
├── modules/                   # Funcionalidades
│   ├── listas_espera/
│   ├── reportes/
│   ├── pacientes/
│   └── integracion/
├── database/
│   └── schema.sql            # Tablas de SIGLECH
└── partials/                 # Plantillas HTML
```

---

## 🚀 Instalación Rápida

### 1. Crear Base de Datos
```bash
mysql -u root < database/schema.sql
```

### 2. Crear Usuario Admin
Usuario: `admin`  
Contraseña: `admin` (cambiar en primer login)

### 3. Configurar SICOCH
Editar `config.php`:
```php
define('SICOCH_API_BASE_URL', 'http://localhost/SICOCH');
define('SICOCH_API_KEY', 'tu_api_key_aqui');
```

### 4. Acceder
```
http://localhost/SIGLECH
```

---

## 🔌 Comunicación SICOCH ↔ SIGLECH

### SICOCHClient.php
Cliente REST que consume datos de SICOCH:

```php
$sicoch = new SICOCHClient(SICOCH_API_BASE_URL, SICOCH_API_KEY);

// Obtener paciente
$paciente = $sicoch->obtenerPacientePorRun('10557670-9');

// Obtener especialidades
$especialidades = $sicoch->obtenerEspecialidades();

// Registrar contacto
$sicoch->registrarContacto($interconsulta_id, [
    'resultado' => 'CONTACTADO',
    'observaciones' => 'Paciente confirmó cita',
]);
```

### Endpoints que SIGLECH Usa
- `GET /api/pacientes` - Pacientes desde SICOCH
- `GET /api/especialidades` - Especialidades
- `GET /api/establecimientos` - Establecimientos
- `POST /api/contactos` - Registrar contacto

---

## 📊 Tablas de SIGLECH

### Tablas Propias (Independientes)
- `usuarios` - Autenticación local
- `lista_espera_interconsultas` - Interconsultas
- `lista_espera_gestiones_contacto` - Gestiones de contacto
- `lista_espera_fichas_egreso` - Fichas de egreso
- `lista_espera_alertas` - Alertas automáticas
- `lista_espera_auditoria` - Auditoría

### Tablas Compartidas (BD sicoch_referencia)
- `pacientes` - Sincronizadas desde SICOCH
- `especialidades` - Referencia
- `establecimientos` - Referencia

---

## 🔐 Seguridad

✅ Autenticación local (usuarios + roles)  
✅ API Key para SICOCH  
✅ CSRF tokens  
✅ Prepared statements  
✅ Auditoría de acciones  
✅ Logs separados  

---

## 📈 Timeline Implementación

| Semana | Fase |
|--------|------|
| 1 | Setup + DB + Auth |
| 2 | Dashboard + Listas básicas |
| 3 | Gestión avanzada + Fichas |
| 4 | Reportes + KPIs |
| 5 | Sincronización + Webhooks |
| 6 | Testing + Deploy |

---

## 📞 Contacto

**Autor:** Paulo Rebolledo  
**Email:** paulorebolledo@gmail.com  
**Institución:** Servicio de Salud Chiloé  

---

## 📚 Documentación

- `ARQUITECTURA_SIGLECH_API.md` - Arquitectura detallada
- `API_ENDPOINTS.md` - Endpoints REST (próximamente)
- `MANUAL_USUARIO.md` - Manual de usuario (próximamente)
- `INSTALACION.md` - Guía de instalación (próximamente)

---

## ⚙️ Próximos Pasos

1. ✅ Ejecutar `database/schema.sql`
2. ✅ Configurar `config.php`
3. ✅ Acceder a `http://localhost/SIGLECH`
4. ✅ Login con `admin/admin`
5. ✅ Verificar conexión con SICOCH

---

**Versión:** 1.0  
**Fecha:** 2026-07-16  
**Estado:** Scaffolding completado  
**Próximo:** Implementar módulos funcionales
