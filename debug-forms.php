<?php
/**
 * Sistema de Debug para Formularios TRAMITFY
 * Archivo: debug-forms.php
 *
 * Este archivo crea funciones de debugging que se pueden usar
 * en los formularios para identificar errores críticos
 */

// Solo ejecutar si WordPress está cargado
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Función de debug que escribe a un archivo log
 */
function tramitfy_debug($message, $file = '', $line = '') {
    $log_file = '/tmp/tramitfy-debug.log';
    $timestamp = date('Y-m-d H:i:s');
    $location = '';

    if ($file) {
        $location = " [File: " . basename($file);
        if ($line) {
            $location .= ", Line: $line";
        }
        $location .= "]";
    }

    $log_message = "[$timestamp]$location $message\n";

    // Escribir al archivo
    error_log($log_message, 3, $log_file);

    // También al error_log normal
    error_log("TRAMITFY_DEBUG: $message");
}

/**
 * Verificar sintaxis PHP de un archivo
 */
function tramitfy_check_syntax($file_path) {
    $output = [];
    $return_var = 0;

    // Verificar sintaxis con PHP
    exec("php -l $file_path 2>&1", $output, $return_var);

    $result = [
        'valid' => ($return_var === 0),
        'output' => implode("\n", $output),
        'file' => basename($file_path)
    ];

    tramitfy_debug("Syntax check for " . basename($file_path) . ": " . ($result['valid'] ? 'OK' : 'ERROR'));

    if (!$result['valid']) {
        tramitfy_debug("Syntax error details: " . $result['output']);
    }

    return $result;
}

/**
 * Debug hook para detectar errores fatales
 */
function tramitfy_shutdown_handler() {
    $error = error_get_last();

    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        tramitfy_debug("FATAL ERROR DETECTED!");
        tramitfy_debug("Type: " . $error['type']);
        tramitfy_debug("Message: " . $error['message']);
        tramitfy_debug("File: " . $error['file']);
        tramitfy_debug("Line: " . $error['line']);

        // Escribir a un archivo específico de errores críticos
        $critical_log = '/tmp/tramitfy-critical.log';
        $log_entry = date('Y-m-d H:i:s') . " - CRITICAL ERROR\n";
        $log_entry .= "File: " . $error['file'] . "\n";
        $log_entry .= "Line: " . $error['line'] . "\n";
        $log_entry .= "Message: " . $error['message'] . "\n";
        $log_entry .= "----------------------------------------\n";

        error_log($log_entry, 3, $critical_log);
    }
}

// Registrar el shutdown handler
register_shutdown_function('tramitfy_shutdown_handler');

// Log inicial
tramitfy_debug("Debug system initialized", __FILE__, __LINE__);