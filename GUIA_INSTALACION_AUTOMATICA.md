# 🚀 Guía de Instalación Automática - SIGLECH

**Versión:** 1.0  
**Fecha:** 2026-07-16  
**Estado:** ✅ Listos 4 métodos de instalación

---

## 📋 Resumen de Métodos

Tienes **4 formas** de instalar SIGLECH. Elige la que mejor se adapte a tu sistema:

| Método | SO | Dificultad | Recomendado |
|--------|----|-----------| ------------|
| **install.php** | Windows/Linux/Mac | ⭐ Muy fácil | ✅ Sí |
| **setup.php** | Windows/Linux/Mac | ⭐⭐ Fácil | ✅ Mejor |
| **install-siglech.bat** | Solo Windows | ⭐⭐ Fácil | Sí |
| **install-siglech.sh** | Linux/Mac | ⭐⭐⭐ Normal | Sí |

---

## 🎯 Método 1: install.php (RECOMENDADO - MÁS FÁCIL)

### ¿Cómo usar?

1. **Abre en tu navegador:**
   ```
   http://localhost/SIGLECH/install.php
   ```

2. **Verás la página de instalación**

3. **Click en el botón: "🚀 Instalar SIGLECH Ahora"**

4. **¡Listo!** Te mostrará el resultado

### Ventajas
✅ No requiere terminal  
✅ Interfaz visual  
✅ Funciona en Windows, Linux, Mac  
✅ Rápido y seguro  

### Desventajas
❌ Requiere que el servidor web esté corriendo  

---

## 🎯 Método 2: setup.php (RECOMENDADO - MÁS AVANZADO)

### ¿Cómo usar?

1. **Abre en tu navegador:**
   ```
   http://localhost/SIGLECH/setup.php
   ```

2. **Sigue los 4 pasos:**
   - Paso 1: Verificar Requisitos
   - Paso 2: Probar Conexión a BD
   - Paso 3: Ejecutar Instalación
   - Paso 4: Finalización

3. **¡Listo!** Accede a SIGLECH

### Ventajas
✅ Interfaz interactiva y detallada  
✅ Verifica todos los requisitos  
✅ Muestra el estado de cada paso  
✅ Más información de diagnóstico  
✅ Funciona en Windows, Linux, Mac  

### Desventajas
❌ Requiere que el servidor web esté corriendo  

---

## 🎯 Método 3: install-siglech.bat (SOLO WINDOWS)

### ¿Cómo usar?

**Opción A: Double-click**
1. Abre el Explorador de Archivos
2. Navega a: `C:\xampp\htdocs\SIGLECH\`
3. **Double-click en: `install-siglech.bat`**
4. Se abrirá una terminal automáticamente
5. Verás el progreso
6. ¡Listo!

**Opción B: Desde CMD**
```cmd
cd C:\xampp\htdocs\SIGLECH
install-siglech.bat
```

### Ventajas
✅ No requiere servidor web corriendo  
✅ Double-click directo  
✅ Muy rápido  
✅ Muestra todo en la terminal  

### Desventajas
❌ Solo Windows  
❌ Requiere MySQL en el PATH (generalmente está)  

### Solución de Problemas

**"MySQL no está en el PATH"**
```
Soluciones:
1. Asegúrate que XAMPP está instalado
2. MySQL debe estar en C:\xampp\mysql\bin
3. O ejecuta desde: C:\xampp\mysql\bin\mysql.exe
```

---

## 🎯 Método 4: install-siglech.sh (LINUX/MAC)

### ¿Cómo usar?

```bash
# 1. Navega a la carpeta
cd /path/to/SIGLECH

# 2. Dale permisos de ejecución
chmod +x install-siglech.sh

# 3. Ejecuta
./install-siglech.sh
```

### Ventajas
✅ No requiere servidor web corriendo  
✅ Automatizado completamente  
✅ Script shell nativo  
✅ Muestra progreso en color  

### Desventajas
❌ Solo Linux/Mac  
❌ Requiere MySQL instalado en el sistema  

### Solución de Problemas

**"MySQL no está instalado"**

En Ubuntu/Debian:
```bash
sudo apt-get install mysql-server mysql-client
```

En macOS (Homebrew):
```bash
brew install mysql
brew services start mysql
```

---

## 🔍 Comparación Técnica

### install.php
```
Método:         PHP + PDO directo
Requiere:       Servidor web + MySQL corriendo
Seguridad:      ✅ Muy seguro (por PDO)
Plataforma:     ✅ Windows, Linux, Mac
Configuración:  ✅ Automática desde config.php
Velocidad:      ✅ Rápido
Interfaz:       🎨 Web moderna
```

### setup.php
```
Método:         PHP + PDO + Interfaz paso a paso
Requiere:       Servidor web + MySQL corriendo
Seguridad:      ✅ Muy seguro (por PDO)
Plataforma:     ✅ Windows, Linux, Mac
Configuración:  ✅ Automática desde config.php
Velocidad:      ✅ Rápido
Interfaz:       🎨 Web avanzada con verificaciones
```

### install-siglech.bat
```
Método:         CMD + mysql.exe
Requiere:       MySQL en PATH (XAMPP)
Seguridad:      ✅ Seguro (redirección de archivo)
Plataforma:     ⚠️ Solo Windows
Configuración:  ✅ Desde config.php
Velocidad:      ✅✅ Muy rápido
Interfaz:       💻 Terminal
```

### install-siglech.sh
```
Método:         Bash + mysql CLI
Requiere:       MySQL instalado en sistema
Seguridad:      ✅ Seguro (redirección de archivo)
Plataforma:     ⚠️ Solo Linux/Mac
Configuración:  ✅ Desde config.php
Velocidad:      ✅✅ Muy rápido
Interfaz:       💻 Terminal con colores
```

---

## ✅ Checklist Post-Instalación

Después de instalar con cualquier método, verifica:

- [ ] Puedes acceder a http://localhost/SIGLECH
- [ ] Logo muestra "✓ Conectado a SICOCH"
- [ ] KPI cards muestran números (0 es normal)
- [ ] Puedes hacer login (admin/admin)
- [ ] Base de datos tiene 8 tablas
- [ ] No hay errores en logs/errors.log

---

## 📝 Logs y Debug

### Ver errores
```bash
tail -f C:\xampp\htdocs\SIGLECH\logs\errors.log
```

### Ver registro de sincronización
```bash
tail -f C:\xampp\htdocs\SIGLECH\logs\sync.log
```

### Ver API logs
```bash
tail -f C:\xampp\htdocs\SIGLECH\logs\api.log
```

---

## 🆘 Solución de Problemas

### "Error: No se pudo conectar a MySQL"
```
1. Asegúrate que XAMPP está corriendo
2. Verifica que MySQL está habilitado en XAMPP
3. Verifica credenciales en config.php
4. Intenta: mysql -u root (sin contraseña)
```

### "Archivo schema.sql no encontrado"
```
Verifica que existe:
C:\xampp\htdocs\SIGLECH\database\schema.sql
```

### "Permiso denegado"
```
En Linux/Mac:
chmod +x install-siglech.sh
chmod -R 755 SIGLECH/

En Windows:
Click derecho → Propiedades → Seguridad
```

### "La instalación ya existe"
```
install.php y setup.php detectan esto automáticamente.
Opción: Reinstalar (borra datos) o acceder al dashboard.
```

---

## 🎓 Recomendación Final

**Para usuarios nuevos:**
→ Usar **install.php** (más simple)

**Para usuarios avanzados:**
→ Usar **setup.php** (más control)

**Para desarrolladores (Windows):**
→ Usar **install-siglech.bat** (más rápido)

**Para desarrolladores (Linux/Mac):**
→ Usar **install-siglech.sh** (nativo)

---

## 📞 Soporte

Si tienes problemas:

1. Intenta con otro método
2. Lee los logs en `logs/`
3. Verifica que MySQL está corriendo
4. Verifica permisos de carpetas
5. Contacta: paulorebolledo@gmail.com

---

**Versión:** 1.0  
**Fecha:** 2026-07-16  
**Estado:** ✅ Todos los métodos listos  
**Próxima:** Usar cualquiera de los 4 métodos
