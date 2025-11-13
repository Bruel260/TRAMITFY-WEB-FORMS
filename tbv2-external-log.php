<?php
/**
 * SISTEMA DE LOGGING EXTERNO TBV2
 * Accesible vía: https://tramitfy.es/wp-content/themes/xtra/tbv2-external-log.php?action=show_logs
 */

// Permitir acceso directo para este archivo de logs
if (!defined('ABSPATH')) {
    // Permitir acceso directo sin restricciones para logs
    define('WP_DEBUG', false);
}

// Función para mostrar logs
function tbv2_show_external_logs() {
    $log_file = '/tmp/tbv2_external.log';
    
    if (!file_exists($log_file)) {
        return "<h2>📋 No hay logs disponibles todavía.</h2><p>Ejecuta alguna acción en TBV2 para generar logs.</p>";
    }
    
    $logs = file_get_contents($log_file);
    $lines = explode("\n", array_reverse(explode("\n", $logs))); // Más recientes primero
    $lines = array_slice($lines, 0, 100); // Solo últimas 100 líneas
    
    $html = "
    <!DOCTYPE html>
    <html>
    <head>
        <title>🔍 TBV2 External Logs</title>
        <meta charset='utf-8'>
        <style>
            body { 
                font-family: 'Courier New', monospace; 
                background: #1a1a1a; 
                color: #00ff00; 
                padding: 20px; 
                margin: 0;
            }
            h1 { 
                color: #00ffff; 
                border-bottom: 2px solid #00ffff; 
                padding-bottom: 10px;
            }
            .controls { 
                margin: 20px 0; 
                padding: 15px; 
                background: #2a2a2a; 
                border: 1px solid #555;
                border-radius: 5px;
            }
            .controls a { 
                color: #ff6666; 
                text-decoration: none; 
                margin-right: 20px;
                padding: 5px 10px;
                border: 1px solid #ff6666;
                border-radius: 3px;
            }
            .controls a:hover { 
                background: #ff6666; 
                color: #000;
            }
            .log { 
                margin: 3px 0; 
                padding: 2px 5px;
                border-left: 3px solid #333;
                word-wrap: break-word;
            }
            .error { color: #ff4444; border-left-color: #ff4444; background: #2a1111; }
            .success { color: #44ff44; border-left-color: #44ff44; background: #112a11; }
            .warning { color: #ffaa00; border-left-color: #ffaa00; background: #2a2211; }
            .info { color: #44aaff; border-left-color: #44aaff; background: #111a2a; }
            .timestamp { color: #888; }
            .level { font-weight: bold; }
            pre { 
                background: #000; 
                padding: 15px; 
                border-radius: 5px; 
                border: 1px solid #333;
                max-height: 600px; 
                overflow-y: auto;
            }
        </style>
        <script>
            // Auto-refresh cada 3 segundos
            setTimeout(() => location.reload(), 3000);
        </script>
    </head>
    <body>
        <h1>🔍 TBV2 EXTERNAL LOGS - TIEMPO REAL</h1>
        
        <div class='controls'>
            <strong>⏱️ Auto-refresh:</strong> cada 3 segundos | 
            <strong>📅 Última actualización:</strong> " . date('Y-m-d H:i:s') . " | 
            <a href='?action=show_logs&clear=1'>🗑️ Limpiar Logs</a>
            <a href='?action=show_logs'>🔄 Actualizar</a>
        </div>
        
        <pre>";
    
    // Procesar y colorear cada línea
    foreach ($lines as $line) {
        if (empty(trim($line))) continue;
        
        $class = 'info';
        if (strpos($line, '[ERROR]') !== false) $class = 'error';
        if (strpos($line, '[SUCCESS]') !== false) $class = 'success';  
        if (strpos($line, '[WARNING]') !== false) $class = 'warning';
        
        // Destacar timestamps y niveles
        $line = preg_replace('/(\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\])/', '<span class="timestamp">$1</span>', htmlspecialchars($line));
        $line = preg_replace('/(\[ERROR\]|\[SUCCESS\]|\[WARNING\]|\[INFO\])/', '<span class="level">$1</span>', $line);
        
        $html .= "<div class='log $class'>$line</div>";
    }
    
    $html .= "</pre>
        
        <div class='controls'>
            <strong>📊 Total líneas:</strong> " . count($lines) . " | 
            <strong>📁 Archivo:</strong> /tmp/tbv2_external.log
        </div>
        
    </body>
    </html>";
    
    return $html;
}

// Función para limpiar logs
function tbv2_clear_external_logs() {
    $log_file = '/tmp/tbv2_external.log';
    if (file_exists($log_file)) {
        unlink($log_file);
    }
    
    // Crear log inicial
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] [SUCCESS] LOGS LIMPIADOS - Sistema reiniciado\n", FILE_APPEND | LOCK_EX);
}

// Manejo de requests
if (isset($_GET['action']) && $_GET['action'] === 'show_logs') {
    if (isset($_GET['clear'])) {
        tbv2_clear_external_logs();
        header('Location: ?action=show_logs');
        exit;
    }
    
    echo tbv2_show_external_logs();
    exit;
} else {
    // Si se accede sin parámetros, mostrar instrucciones
    echo "
    <h2>TBV2 External Log System</h2>
    <p><a href='?action=show_logs'>📋 Ver Logs en Tiempo Real</a></p>
    <p>Sistema de logging externo para debugging TBV2 TPV</p>
    ";
    exit;
}
?>