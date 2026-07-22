# Plantilla CSV - Demanda Listas de Espera

## 📋 Descripción

La plantilla CSV es un archivo de referencia que contiene todas las columnas requeridas para importar datos de demanda de listas de espera al sistema SIGLECH.

## 🎯 Carga Incremental

El sistema implementa **carga incremental automática**:

- ✅ **Solo inserta filas nuevas**: Identifica registros nuevos por la columna `_id`
- ✅ **Evita duplicados**: Filas con `_id` existente se omiten automáticamente
- ✅ **Reutilización de archivos**: Puedes subir el mismo archivo varias veces sin riesgo
- ✅ **Actualizaciones seguras**: Sube versiones actualizadas del archivo sin perder datos

## 📊 Columnas Disponibles (68 total)

### Identificación del Registro
- `_id` **[REQUERIDO]**: Identificador único del registro (no puede estar vacío)
- `TIPO_ARCHIVO`: Tipo de archivo (CNE, IQ, PROC)
- `ARCHIVO_ID`: Código del archivo fuente
- `SIGTE_ID`: Identificador del sistema SIGTE

### Datos del Paciente
- `RUN` **[REQUERIDO]**: Número de RUN (sin puntos ni guión)
- `DV`: Dígito verificador del RUN
- `NOMBRES`: Nombre del paciente
- `PRIMER_APELLIDO`: Primer apellido
- `SEGUNDO_APELLIDO`: Segundo apellido
- `FECHA_NAC`: Fecha de nacimiento (formato: DD-MM-YYYY)
- `SEXO`: Sexo del paciente (M/F)
- `EDAD`: Edad en años
- `GRUPO_ETARIO`: Grupo etario (ej: 45-49)

### Datos de Contacto
- `FONO_FIJO`: Teléfono fijo
- `FONO_MOVIL`: Teléfono móvil
- `EMAIL`: Correo electrónico

### Dirección
- `VIA_DIRECCION`: Tipo de vía (Calle, Avenida, etc.)
- `NOM_CALLE`: Nombre de la calle
- `NUM_DIRECCION`: Número de dirección
- `RESTO_DIRECCION`: Información adicional (depto, casa, etc.)
- `CIUDAD`: Ciudad
- `COMUNA`: Comuna
- `REGION`: Región
- `COND_RURALIDAD`: Condición de ruralidad (Urbana/Rural)
- `PRAIS`: Código PRAIS

### Información Clínica
- `SOSPECHA_DIAG`: Diagnóstico sospechado
- `CONFIR_DIAG`: Diagnóstico confirmado
- `CIE10_HOMOLOGADO`: Código CIE-10 homologado
- `CIE10_DESCRIPCION`: Descripción del código CIE-10
- `ESPECIALIDAD_ESTANDAR`: Especialidad estandarizada
- `PRESTA_EST_ESTANDAR`: Prestación estandarizada

### Prestaciones
- `TIPO_PREST`: Tipo de prestación (CNE, IQ, PROC)
- `PRESTA_MIN`: Prestación MINSAL
- `PRESTA_EST`: Prestación estandarizada
- `PLANO`: Plano (si aplica)
- `EXTREMIDAD`: Extremidad (si aplica)

### Establecimientos
- `ESTAB_ORIG`: Establecimiento de origen
- `ESTAB_DEST`: Establecimiento de destino
- `NIVEL_ORIG`: Nivel de origen (Primario, Secundario, etc.)
- `NOMBRE_ORIG`: Nombre del establecimiento de origen
- `NIVEL_DEST`: Nivel de destino
- `NOMBRE_DEST`: Nombre del establecimiento de destino
- `SS_DEST`: Servicio de Salud destino
- `SS_NOMBRE_DEST`: Nombre del Servicio de Salud destino
- `SERV_SALUD`: Servicio de Salud
- `RED`: Red asistencial

### Fechas y Estado
- `F_ENTRADA`: Fecha de entrada (formato: DD-MM-YYYY)
- `F_SALIDA`: Fecha de salida (formato: DD-MM-YYYY)
- `F_CITACION`: Fecha de citación (formato: DD-MM-YYYY)
- `F_DEFUNCION`: Fecha de defunción (formato: DD-MM-YYYY)
- `DIAS_ESPERA`: Días en espera
- `ESTADO`: Estado del registro (VIGENTE, EGRESADO, etc.)
- `TIPO_DE_LISTA`: Tipo de lista (CNE, IQ, PROC)

### Resultados y Seguimiento
- `RESULTADO`: Resultado de la gestión
- `TIPO_EGRESO`: Tipo de egreso
- `C_SALIDA`: Causal de salida
- `ESTADO_GLOSA`: Estado de glosa (Sí/No)
- `E_OTOR_AT`: Estado de otorgamiento de atención
- `PRESTA_MIN_SALIDA`: Prestación MINSAL de salida

### Información Profesional
- `RUN_PROF_SOL`: RUN del profesional solicitante
- `DV_PROF_SOL`: DV del profesional solicitante
- `RUN_PROF_RESOL`: RUN del profesional resolutivo
- `DV_PROF_RESOL`: DV del profesional resolutivo

### Otros Datos
- `PREVISION`: Previsión del paciente (FONASA, ISAPRE, etc.)
- `ID_LOCAL`: Identificador local
- `N_CAUSAL`: Número de causal
- `POSTERGADO`: Está postergado (Sí/No)
- `FUENTE_CONTACTO`: Fuente de contacto

## 📝 Formato de Archivo

### Requisitos
- **Formato**: CSV (Comma-Separated Values)
- **Separador**: Coma (`,`) o Punto y coma (`;`)
- **Codificación**: UTF-8
- **Encabezado**: Primera fila con nombres de columnas
- **Fechas**: Formato DD-MM-YYYY
- **RUN**: Solo números (sin puntos ni guión)

### Ejemplo de Fila
```
EJ001,CNE,ARK-001,Servicio de Salud Chiloé,12345678,9,Juan Carlos,Rodríguez,García,15-03-1980,M,FONASA,CNE,...
```

## 🔄 Proceso de Carga

1. **Descargar plantilla**: Selecciona el tipo (CNE, IQ, PROC)
2. **Completar datos**: Llena las filas con datos reales
3. **Subir archivo**: Sube el CSV a través de la interfaz
4. **Verificación automática**:
   - Se detectan columnas automáticamente
   - Se validan tipos de datos
   - Se omiten duplicados (registros con `_id` existente)
   - Se reportan errores de validación

## ⚠️ Consideraciones Importantes

### Columna `_id`
- Es el **identificador único** del registro
- No puede estar vacía
- Dos filas con el mismo `_id` se consideran duplicadas
- Solo la primera versión del registro se insertará

### Tipos de Datos
- **Fechas**: Deben estar en formato DD-MM-YYYY
- **RUN**: Solo números (se valida automáticamente)
- **Números**: Sin separadores de miles
- **Texto**: Sin caracteres especiales problemáticos

### Validaciones Automáticas
```
✓ Columnas requeridas presentes
✓ Formato de RUN válido
✓ Fechas en formato correcto
✓ Valores dentro de rangos permitidos
✗ Se omiten duplicados sin error
```

## 📊 Respuesta del Sistema

Después de subir un archivo, verás:

```
✅ Carga completada

Filas leídas:     100
Nuevas insertadas: 85
Ya existentes:     15 (omitidas)
Errores:           0
```

- **Filas leídas**: Total de registros en el archivo
- **Nuevas insertadas**: Registros con `_id` nuevos
- **Ya existentes**: Registros con `_id` duplicado (ignorados)
- **Errores**: Registros con problemas de validación

## 🚀 Mejores Prácticas

1. **Versionado**: Mantén versiones del archivo con fecha
   - `demanda_cne_2026-07-21.csv`
   - `demanda_cne_2026-07-25.csv`

2. **Backup**: Guarda copias antes de subir
3. **Validación**: Revisa fechas y RUNs antes de cargar
4. **Incremento**: Agrega datos nuevos sin recargar todo
5. **Reintento**: Si hay errores, corrige y sube de nuevo

## ✅ Checklist Antes de Cargar

- [ ] Archivo en formato CSV
- [ ] Codificación UTF-8
- [ ] Encabezado correcto
- [ ] Columna `_id` sin duplicados internos
- [ ] Fechas en formato DD-MM-YYYY
- [ ] RUN sin puntos ni guiones
- [ ] Especialidades válidas
- [ ] Establecimientos válidos

## 📞 Soporte

Si encuentras problemas:
1. Revisa el mensaje de error específico
2. Valida el formato de las fechas
3. Verifica que RUNs sean válidos
4. Asegúrate que `_id` no esté vacía
5. Contacta al administrador si persiste
