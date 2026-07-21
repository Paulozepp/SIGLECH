# 🚀 Cargador de Demanda CNE en VSCode

## ⚡ Setup Rápido (2 minutos)

### 1️⃣ Instala dependencias

Abre Terminal en VSCode (`Ctrl + ``) y ejecuta:

```powershell
pip install -r requirements.txt
```

### 2️⃣ Verifica conexión MySQL

```powershell
python -c "import mysql.connector; conn = mysql.connector.connect(host='localhost', user='root', password='', database='siglech'); print('✅ OK'); conn.close()"
```

### 3️⃣ ¡Listo! Ya puedes cargar datos

---

## 📝 Cómo usar desde VSCode

### Opción A: Terminal (Más rápido)

**Terminal → Run Task** (`Ctrl + Shift + D`):

```
📥 Cargar Demanda CNE (Chunking)     ← Principal
🔍 Analizar CSV (10K filas)           ← Exploración
📊 Ver Estadísticas BD                ← Verificar carga
🧹 Limpiar tabla demanda_cne          ← Reset
🔗 Conexión MySQL                     ← Test
```

### Opción B: Debugger (Con breakpoints)

**Run and Debug** (`Ctrl + Shift + D` → Select debug config):

```
▶️ Ejecutar: Cargar CNE          ← Ejecución normal
🐛 Debug: Cargar CNE             ← Con debugger
📊 Analizar CSV                   ← Script de análisis
```

### Opción C: Code Runner

Click derecho en archivo .py → `Run Code`

---

## 🎯 Flujo típico

```
1. 🔍 Analizar CSV              (entender datos)
   Terminal → Run Task → "🔍 Analizar CSV"
   
2. 🔗 Verificar conexión        (test MySQL)
   Terminal → Run Task → "🔗 Conexión MySQL"
   
3. 🧹 Limpiar BD (opcional)     (si ya hay datos)
   Terminal → Run Task → "🧹 Limpiar"
   
4. 📥 Cargar datos              (main task)
   Terminal → Run Task → "📥 Cargar Demanda CNE"
   
5. 📊 Ver estadísticas          (verificar)
   Terminal → Run Task → "📊 Ver Estadísticas BD"
```

---

## ⚙️ Ajustar tamaño de chunks

Si tienes problemas de memoria, reduce el tamaño en `launch.json`:

```json
"args": [
    "--chunk-size",
    "1000"  ← Cambiar aquí (por defecto: 2000)
]
```

---

## 📊 Estructura de archivos

```
Importacion/
├── .vscode/
│   ├── tasks.json              ← Tasks configurados
│   ├── launch.json             ← Config debug
│   └── settings.json           ← Configuración editor
├── load_demanda_cne.py         ← 🔴 PRINCIPAL
├── analizar_csv.py             ← Análisis sin cargar
├── estadisticas_bd.py          ← Ver datos en BD
├── limpiar_bd.py               ← Reset BD
├── requirements.txt            ← Dependencias
└── README_VSCODE.md            ← Este archivo
```

---

## 🐛 Troubleshooting

### Error: "No module named mysql.connector"

```powershell
pip install mysql-connector-python
```

### Error: "No module named pandas"

```powershell
pip install pandas tqdm
```

### Error: "Connection refused"

1. Verifica que MySQL esté corriendo (XAMPP)
2. Check credenciales en `load_demanda_cne.py` (línea 210)
3. Verifica que base datos `siglech` exista

### Error: "Timeout during load"

Reduce `--chunk-size` en `launch.json` (ej: 1000 en lugar de 2000)

---

## 💡 Tips

- **Usar streaming output**: Abre `Output` panel (Ctrl + Shift + U)
- **Ver logs**: Terminal muestra todo con colores
- **Pausar en breakpoint**: F5 para debug paso a paso
- **Cancelar carga**: Ctrl + C en terminal

---

## 📈 Rendimiento esperado

- CSV: 245,816 registros
- Chunk size: 2,000
- Tiempo: ~5-10 minutos
- Velocidad: 400-600 registros/seg

---

## 🔗 Recursos

- [MySQL Connector Python](https://dev.mysql.com/doc/connector-python/en/)
- [Pandas Documentation](https://pandas.pydata.org/docs/)
- [VSCode Tasks](https://code.visualstudio.com/docs/editor/tasks)

---

**¿Necesitas ayuda?** Revisa los logs en la terminal. Cada mensaje tiene emoji para identificar:
- ✅ Éxito
- ❌ Error
- ⚠️ Advertencia
- ℹ️ Información
