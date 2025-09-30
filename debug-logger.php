<?php
/**
 * Debug Logger para WordPress
 * Este archivo captura y muestra errores PHP
 */

// Habilitar reporte de errores
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('log_errors', 1);

// Crear directorio de logs si no existe
$log_dir = __DIR__ . '/debug-logs';
if (!file_exists($log_dir)) {
    mkdir($log_dir, 0777, true);
}

// Configurar archivo de log
$log_file = $log_dir . '/wordpress-debug-' . date('Y-m-d') . '.log';
ini_set('error_log', $log_file);

// Función para escribir en el log
function debug_log_write($message, $type = 'INFO') {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] [$type] $message" . PHP_EOL;
    file_put_contents($log_file, $log_message, FILE_APPEND);
}

// Manejador de errores personalizado
function custom_error_handler($errno, $errstr, $errfile, $errline) {
    $error_type = '';
    switch ($errno) {
        case E_ERROR:
        case E_CORE_ERROR:
        case E_COMPILE_ERROR:
        case E_USER_ERROR:
            $error_type = 'FATAL';
            break;
        case E_WARNING:
        case E_CORE_WARNING:
        case E_COMPILE_WARNING:
        case E_USER_WARNING:
            $error_type = 'WARNING';
            break;
        case E_NOTICE:
        case E_USER_NOTICE:
            $error_type = 'NOTICE';
            break;
        case E_STRICT:
            $error_type = 'STRICT';
            break;
        case E_DEPRECATED:
        case E_USER_DEPRECATED:
            $error_type = 'DEPRECATED';
            break;
        default:
            $error_type = 'UNKNOWN';
            break;
    }

    $message = "PHP $error_type: $errstr in $errfile on line $errline";
    debug_log_write($message, $error_type);

    // Para errores fatales, también mostrar en pantalla
    if (in_array($errno, [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        echo "<div style='background: #ff0000; color: white; padding: 10px; margin: 10px; border-radius: 5px;'>";
        echo "<strong>ERROR CRÍTICO:</strong><br>";
        echo htmlspecialchars($message);
        echo "</div>";
    }

    return true; // No ejecutar el manejador de errores interno de PHP
}

// Registrar el manejador
set_error_handler("custom_error_handler");

// Función para capturar errores fatales
function shutdown_handler() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
        $message = "FATAL ERROR: {$error['message']} in {$error['file']} on line {$error['line']}";
        debug_log_write($message, 'FATAL');
    }
}
register_shutdown_function('shutdown_handler');

// Verificar cada archivo PHP del tema
function check_php_files() {
    $results = [];
    $theme_dir = __DIR__;

    // Lista de archivos a verificar
    $files_to_check = [
        'hoja-asiento.php',
        'baja.php',
        'transferencia-barco.php',
        'transferencia-moto.php',
        'recuperar-documentacion.php',
        'renovacion-permiso.php'
    ];

    foreach ($files_to_check as $file) {
        $filepath = $theme_dir . '/' . $file;
        if (file_exists($filepath)) {
            // Verificar sintaxis PHP
            $output = [];
            $return_code = 0;
            exec("php -l $filepath 2>&1", $output, $return_code);

            $results[$file] = [
                'exists' => true,
                'syntax_valid' => ($return_code === 0),
                'message' => implode("\n", $output),
                'size' => filesize($filepath),
                'modified' => date('Y-m-d H:i:s', filemtime($filepath))
            ];

            // Si hay error de sintaxis, logearlo
            if ($return_code !== 0) {
                debug_log_write("SYNTAX ERROR in $file: " . implode(" ", $output), 'ERROR');
            }
        } else {
            $results[$file] = [
                'exists' => false,
                'message' => 'File not found'
            ];
        }
    }

    return $results;
}

// Si se accede directamente, mostrar el estado
if (!defined('ABSPATH')) {
    echo "<!DOCTYPE html>";
    echo "<html><head><title>WordPress Debug Status</title>";
    echo "<style>
        body { font-family: monospace; margin: 20px; background: #f0f0f0; }
        .container { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; }
        .file-status { margin: 10px 0; padding: 10px; border-left: 4px solid #ccc; background: #fafafa; }
        .file-status.error { border-left-color: #ff0000; background: #ffeeee; }
        .file-status.success { border-left-color: #00ff00; background: #eeffee; }
        .log-content { background: #333; color: #0f0; padding: 10px; border-radius: 5px; overflow-x: auto; }
        pre { margin: 0; }
    </style></head><body>";

    echo "<div class='container'>";
    echo "<h1>🔍 WordPress Theme Debug Status</h1>";
    echo "<p>Generated: " . date('Y-m-d H:i:s') . "</p>";

    // Verificar archivos
    echo "<h2>📁 PHP Files Status:</h2>";
    $file_results = check_php_files();

    foreach ($file_results as $filename => $status) {
        $class = $status['syntax_valid'] ?? false ? 'success' : 'error';
        echo "<div class='file-status $class'>";
        echo "<strong>$filename</strong><br>";
        if ($status['exists']) {
            echo "Size: " . number_format($status['size']) . " bytes<br>";
            echo "Modified: {$status['modified']}<br>";
            echo "Syntax: " . ($status['syntax_valid'] ? '✅ VALID' : '❌ ERROR') . "<br>";
            if (!$status['syntax_valid']) {
                echo "<pre>" . htmlspecialchars($status['message']) . "</pre>";
            }
        } else {
            echo "❌ File not found";
        }
        echo "</div>";
    }

    // Mostrar últimas líneas del log
    echo "<h2>📋 Recent Log Entries:</h2>";
    if (file_exists($log_file)) {
        $log_lines = file($log_file);
        $recent_lines = array_slice($log_lines, -50); // Últimas 50 líneas
        echo "<div class='log-content'>";
        echo "<pre>" . htmlspecialchars(implode("", $recent_lines)) . "</pre>";
        echo "</div>";
    } else {
        echo "<p>No log file found yet.</p>";
    }

    echo "<h2>🔧 PHP Configuration:</h2>";
    echo "<div class='file-status'>";
    echo "PHP Version: " . phpversion() . "<br>";
    echo "Memory Limit: " . ini_get('memory_limit') . "<br>";
    echo "Max Execution Time: " . ini_get('max_execution_time') . "<br>";
    echo "Error Reporting: " . error_reporting() . "<br>";
    echo "</div>";

    echo "</div></body></html>";
}
?>