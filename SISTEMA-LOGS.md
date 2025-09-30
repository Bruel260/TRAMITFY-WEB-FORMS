# Sistema de Logs Tramitfy

## 📍 Ubicación de Logs

### Logs del Servidor (PHP)
**Ruta:** `/domains/tramitfy.es/public_html/wp-content/themes/xtra/logs/`

**Formato de archivo:** `tramitfy-YYYY-MM-DD.log`

**Ejemplo:** `tramitfy-2025-09-30.log`

### Logs del Cliente (JavaScript)
**Ubicación:** Consola F12 del navegador

**Storage:** `window.tramitfyLogs` (array en memoria)

---

## 🔧 Funciones PHP

### `tramitfy_log($message, $context, $level)`
Función principal de logging en PHP.

**Parámetros:**
- `$message` (string|array|object): Mensaje a registrar
- `$context` (string): Contexto/módulo (ej: 'SUBMIT', 'MOTO-FORM', 'CSV-LOAD')
- `$level` (string): Nivel de log ('INFO', 'DEBUG', 'ERROR', 'CRITICAL')

**Ejemplo:**
```php
tramitfy_log('Usuario enviado formulario', 'SUBMIT', 'INFO');
tramitfy_log(['name' => 'Juan', 'email' => 'test@test.com'], 'DATOS', 'DEBUG');
```

### `tramitfy_debug($message, $data)`
Atajo para logs de debug (solo si WP_DEBUG está activo).

**Ejemplo:**
```php
tramitfy_debug('Procesando archivo CSV', ['rows' => 150, 'file' => 'MOTO.csv']);
```

---

## 🌐 Funciones JavaScript (F12)

### Niveles de Log
- **DEBUG** 🔍 (gris) - Solo si `TRAMITFY_DEBUG = true`
- **INFO** ℹ️ (azul) - Información general
- **SUCCESS** ✅ (verde) - Operaciones exitosas
- **WARNING** ⚠️ (naranja) - Advertencias
- **ERROR** ❌ (rojo) - Errores
- **CRITICAL** 🔥 (rojo oscuro) - Errores críticos

### Funciones Disponibles

#### `logDebug(context, message, data)`
```javascript
logDebug('CSV-LOAD', 'Fabricantes cargados', { count: 25 });
```

#### `logInfo(context, message, data)`
```javascript
logInfo('FORM', 'Navegando a página 2');
```

#### `logSuccess(context, message, data)`
```javascript
logSuccess('PAYMENT', 'Pago procesado correctamente', { amount: 134.99 });
```

#### `logWarning(context, message, data)`
```javascript
logWarning('VALIDATION', 'Campo vacío detectado', { field: 'email' });
```

#### `logError(context, message, data)`
```javascript
logError('API', 'Error en request', { status: 500, endpoint: '/api/submit' });
```

#### `logCritical(context, message, data)`
```javascript
logCritical('STRIPE', 'Fallo completo de pago', { error: err });
```

### Medición de Performance

#### `perfStart(label)`
Inicia medición de tiempo.

```javascript
perfStart('form-submit');
```

#### `perfEnd(label)`
Finaliza medición y muestra duración.

```javascript
perfEnd('form-submit'); // Retorna: 245.67 (ms)
```

**Códigos de color automáticos:**
- Verde (< 100ms): Rápido ✅
- Naranja (100-500ms): Aceptable ⚠️
- Rojo (> 500ms): Lento ❌

### Exportar Logs

Desde la consola F12:

```javascript
exportTramitfyLogs()
```

Descarga un archivo JSON con todos los logs de la sesión.

---

## 📊 Contextos Principales

### PHP
- `INIT` - Inicialización del formulario
- `SUBMIT` - Envío del formulario
- `CSV-LOAD` - Carga de datos CSV
- `EMAIL` - Envío de emails
- `PDF` - Generación de PDFs
- `WEBHOOK` - Llamadas a API
- `DEBUG` - Información de debug

### JavaScript
- `INIT` - Inicialización
- `DOM` - Eventos DOM
- `MANUFACTURER` - Selección de fabricante
- `MODEL` - Selección de modelo
- `NO-ENCUENTRO` - Modo manual
- `PRECIO-FLOW` - Flujo de precio/ITP
- `SIDEBAR` - Actualización del sidebar
- `NAVIGATION` - Navegación entre páginas
- `VALIDATION` - Validaciones
- `PAYMENT` - Proceso de pago
- `PERF` - Mediciones de performance

---

## 🔍 Ejemplos de Uso

### Debugging de Carga de Modelos

**En consola F12, buscar:**
```
[MANUFACTURER] Fabricante seleccionado
[MANUFACTURER] modelos encontrados
```

### Revisar Performance

**En consola F12:**
```
[PERF] ⏱️
```

Muestra todas las mediciones de tiempo.

### Verificar Envío de Formulario

**En servidor (logs PHP):**
```bash
tail -f /domains/tramitfy.es/public_html/wp-content/themes/xtra/logs/tramitfy-$(date +%Y-%m-%d).log
```

**Buscar:**
```
[SUBMIT] INICIO SUBMIT FORMULARIO
[SUBMIT] Cliente: [nombre]
```

---

## 🛠️ Configuración

### Activar/Desactivar Debug JavaScript

En `transferencia-moto.php` línea ~5281:

```javascript
const TRAMITFY_DEBUG = true;  // Cambiar a false para desactivar logs DEBUG
```

### Activar Debug PHP

En `wp-config.php`:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

---

## 📝 Formato de Logs PHP

```
[YYYY-MM-DD HH:MM:SS] [LEVEL] [CONTEXT] [IP:xxx.xxx.xxx.xxx] Mensaje
```

**Ejemplo:**
```
[2025-09-30 14:32:15] [INFO] [SUBMIT] [IP:192.168.1.100] Usuario enviado formulario
[2025-09-30 14:32:16] [ERROR] [EMAIL] [IP:192.168.1.100] Error al enviar email: Connection timeout
```

---

## 🗑️ Limpieza de Logs

Los logs PHP se eliminan automáticamente después de **7 días**.

**Limpieza manual:**
```bash
rm /domains/tramitfy.es/public_html/wp-content/themes/xtra/logs/tramitfy-*.log
```

---

## 💡 Tips de Desarrollo

1. **Siempre revisar consola F12** al desarrollar nuevas funcionalidades
2. **Usar perfStart/perfEnd** para medir rendimiento de operaciones lentas
3. **Exportar logs antes de refrescar** la página si necesitas guardar información
4. **Revisar logs PHP** después de envíos de formulario para diagnosticar errores
5. **Buscar por contexto** usando Ctrl+F en consola: `[MANUFACTURER]`, `[PRECIO]`, etc.

---

## 🚨 Troubleshooting

### No aparecen logs en consola
- Verificar que `TRAMITFY_DEBUG = true`
- Abrir consola F12 ANTES de cargar la página
- Revisar filtros de consola (deben estar todos activos)

### No se crean archivos de log PHP
- Verificar permisos del directorio `/logs/`
- Comprobar que `WP_DEBUG` está activo
- Verificar espacio en disco del servidor

### Logs muy largos en consola
- Desactivar logs DEBUG: `TRAMITFY_DEBUG = false`
- Limpiar consola: `clear()` o Ctrl+L
- Filtrar por contexto específico en la barra de búsqueda

---

**Última actualización:** 2025-09-30
**Versión del formulario:** 1.8
