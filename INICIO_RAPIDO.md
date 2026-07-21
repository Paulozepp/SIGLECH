# 🚀 SIGLECH - Inicio Rápido

**Fecha:** 2026-07-16  
**Versión:** 1.0 - Scaffolding Completado  
**Estado:** ✅ Listo para desarrollo  

---

## ⚡ 3 PASOS PARA EMPEZAR

### Paso 1: Instalar Base de Datos
```bash
mysql -u root < C:\xampp\htdocs\SIGLECH\database\schema.sql
```

### Paso 2: Acceder a SIGLECH
```
URL: http://localhost/SIGLECH
Usuario: admin
Contraseña: admin
```

### Paso 3: Verificar Conexión con SICOCH
En el dashboard, deberías ver "✓ Conectado a SICOCH"

---

## 📁 QUÉ SE HA CREADO

### Carpeta Principal: `C:\xampp\htdocs\SIGLECH\`

```
SIGLECH/
├── config.php                    # ⚙️ Configuración central
├── db.php                        # 🗄️ Conexión BD
├── index.php                     # 📊 Dashboard principal
│
├── auth/
│   └── guard.php                # 🔐 Autenticación
│
├── lib/
│   └── SICOCHClient.php          # 🔌 Cliente API SICOCH
│
├── database/
│   └── schema.sql               # 📋 Tablas SQL
│
├── modules/
│   ├── listas_espera/           # (EN DESARROLLO)
│   ├── reportes/                # (EN DESARROLLO)
│   ├── pacientes/               # (EN DESARROLLO)
│   └── integracion/             # (EN DESARROLLO)
│
├── partials/                     # (Plantillas HTML)
├── assets/                       # (CSS, JS, Imágenes)
├── logs/                         # (Logs de aplicación)
├── cache/                        # (Cache de reportes)
│
├── README.md                     # 📖 Documentación
├── ARQUITECTURA_SIGLECH_API.md   # 🏗️ Arquitectura detallada
└── RESUMEN_ARQUITECTURA.md       # 📊 Resumen ejecutivo
```

---

## 📊 ARCHIVOS CREADOS

| Archivo | Líneas | Estado | Propósito |
|---------|--------|--------|----------|
| `config.php` | 120 | ✅ | Configuración |
| `db.php` | 50 | ✅ | Conexión BD |
| `index.php` | 250 | ✅ | Dashboard |
| `auth/guard.php` | 200 | ✅ | Autenticación |
| `lib/SICOCHClient.php` | 180 | ✅ | API Consumer |
| `database/schema.sql` | 300 | ✅ | Tablas |
| `.gitignore` | 30 | ✅ | Git config |
| `README.md` | 150 | ✅ | Inicio rápido |
| `ARQUITECTURA_SIGLECH_API.md` | 500 | ✅ | Arquitectura |
| `RESUMEN_ARQUITECTURA.md` | 250 | ✅ | Resumen |

**Total: ~2030 líneas de código base**

---

## 🎯 ARQUITECTURA EN 30 SEGUNDOS

```
Usuario
   ↓
   HTTP GET /SIGLECH/
   ↓
   ╔═══════════════════════════════════════╗
   ║  SIGLECH (Sistema Independiente)      ║
   ║  ✓ Dashboard                          ║
   ║  ✓ Listas de Espera                   ║
   ║  ✓ Reportes + KPIs                    ║
   ║  ✓ Autenticación local                ║
   ╚═══════════════════════════════════════╝
   ↓
   REST API (SICOCHClient)
   ↓
   SICOCH (Sin modificar)
   ↓
   BD Compartida (pacientes, especialidades)
```

---

## 🔑 DATOS DE ACCESO INICIAL

| Campo | Valor |
|-------|-------|
| URL | http://localhost/SIGLECH |
| Usuario | admin |
| Contraseña | admin |
| Base de datos | sicoch_referencia |

⚠️ **CAMBIAR CONTRASEÑA EN PRIMER LOGIN**

---

## 🔌 API DE SICOCH (Consumida por SIGLECH)

SIGLECH **consume** estos endpoints de SICOCH:

| Endpoint | Método | Propósito |
|----------|--------|----------|
| `/api/pacientes?run=...` | GET | Obtener paciente |
| `/api/especialidades` | GET | Listar especialidades |
| `/api/establecimientos` | GET | Listar establecimientos |
| `/api/contactos` | POST | Registrar contacto |

**No se modifica SICOCH** - Solo lecturas.

---

## 📋 TABLAS DE BD EN SIGLECH

### Tablas Propias (8 tablas)
```
usuarios                          # Autenticación local
lista_espera_interconsultas      # Interconsultas
lista_espera_gestiones_contacto  # Contactos
lista_espera_fichas_egreso       # Fichas (Norma 118)
lista_espera_alertas             # Alertas automáticas
lista_espera_auditoria           # Auditoría
lista_espera_sincronizacion_log  # Log de sync
lista_espera_reportes_cache      # Cache de reportes
```

### Tablas Compartidas (Referencia)
```
pacientes        ← Sincronizada desde SICOCH
especialidades   ← Referencia
establecimientos ← Referencia
```

---

## ✨ LO QUE FUNCIONA HOY

✅ Dashboard principal con KPIs  
✅ Autenticación de usuarios  
✅ Conexión a BD compartida  
✅ Cliente REST de SICOCH  
✅ Estructura modular lista  
✅ Documentación completa  

---

## 🔲 PRÓXIMAS FASES (6 Semanas)

| Semana | Módulo | Hito |
|--------|--------|------|
| 1 | Listas | Crear/editar/listar interconsultas |
| 2 | Reportes | Dashboard de reportes + KPIs |
| 3 | Egreso | Fichas de egreso (Norma 118) |
| 4 | Reportes | Reportes avanzados + Alertas |
| 5 | Integración | Sincronización automática |
| 6 | Testing | QA + Deploy producción |

---

## 🛠️ TECNOLOGÍA

```
Backend:
├─ PHP 8.0+
├─ MySQL/MariaDB (compartida)
├─ PDO (prepared statements)
└─ REST API (SICOCHClient)

Frontend:
├─ HTML5
├─ CSS3 (Tailwind)
├─ JavaScript vanilla
└─ Chart.js (reportes)

Herramientas:
├─ Git
├─ Composer (opcional)
├─ MySQL Workbench
└─ VS Code/PhpStorm
```

---

## 📚 DOCUMENTACIÓN

### En SIGLECH:
1. **README.md** - Introducción
2. **ARQUITECTURA_SIGLECH_API.md** - Detallado
3. **RESUMEN_ARQUITECTURA.md** - Resumen ejecutivo
4. **INICIO_RAPIDO.md** - Este archivo

### En SICOCH:
- **ARQUITECTURA_SIGLECH_API.md** - Duplicado (referencia)

---

## ⚙️ CONFIGURACIÓN BÁSICA

### config.php
```php
// Base de datos (compartida con SICOCH)
define('DB_HOST', 'localhost');
define('DB_NAME', 'sicoch_referencia');
define('DB_USER', 'root');
define('DB_PASS', '');

// API de SICOCH
define('SICOCH_API_BASE_URL', 'http://localhost/SICOCH');
define('SICOCH_API_KEY', 'siglech_api_key_2026');

// Categorías de listas (10 categorías)
define('CATEGORIAS_LISTAS', [ ... ]);
```

---

## ✅ CHECKLIST DE VERIFICACIÓN

Después de instalar, verifica:

- [ ] Carpeta `C:\xampp\htdocs\SIGLECH\` existe
- [ ] Puedes acceder a `http://localhost/SIGLECH`
- [ ] Logo de SIGLECH muestra "✓ Conectado a SICOCH"
- [ ] Puedes hacer login con admin/admin
- [ ] KPI cards muestran números (0 es normal)
- [ ] Puedes navegar el dashboard sin errores
- [ ] Base de datos tiene 8 tablas de SIGLECH
- [ ] Archivo `config.php` está accesible
- [ ] No hay errores en `logs/errors.log`

---

## 🆘 SOLUCIÓN DE PROBLEMAS

### "No conectado a SICOCH"
✓ Verificar que SICOCH esté corriendo  
✓ Revisar `SICOCH_API_BASE_URL` en config.php  
✓ Revisar `SICOCH_API_KEY`  

### "Error de base de datos"
✓ Ejecutar schema.sql nuevamente  
✓ Verificar usuario/contraseña en config.php  
✓ Verificar que BD `sicoch_referencia` existe  

### "Error en auth"
✓ Limpiar cookies del navegador  
✓ Verificar que tabla `usuarios` tiene datos  
✓ Revisar logs en `logs/errors.log`  

---

## 📞 SOPORTE

**Autor:** Paulo Rebolledo  
**Email:** paulorebolledo@gmail.com  
**Institución:** Servicio de Salud Chiloé  

---

## 🎓 PRÓXIMOS PASOS

1. ✅ Leer este archivo
2. ✅ Ejecutar schema.sql
3. ✅ Acceder a http://localhost/SIGLECH
4. ✅ Cambiar contraseña de admin
5. ✅ Revisar ARQUITECTURA_SIGLECH_API.md
6. ✅ Comenzar desarrollo de módulos

---

**Estado:** ✅ Listo  
**Versión:** 1.0 - Scaffolding  
**Próxima Fase:** Módulo de Listas de Espera  

🚀 **¡SIGLECH listo para desarrollo!**
