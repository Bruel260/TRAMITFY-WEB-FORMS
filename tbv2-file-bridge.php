<?php
/**
 * TBV2 FILE BRIDGE API
 * 
 * Server-side bridge to retrieve files stored during form submission
 * Eliminates need for localStorage and cross-window DOM access
 * 
 * URL: https://tramitfy.es/wp-content/themes/xtra/tbv2-file-bridge.php
 */

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: https://tramitfy.es');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Accept, Content-Type, User-Agent');

// Función de logging
function log_bridge($message) {
    $timestamp = date('Y-m-d H:i:s');
    $log = "[$timestamp] TBV2_FILE_BRIDGE: $message\n";
    file_put_contents('/tmp/tbv2_file_bridge.log', $log, FILE_APPEND | LOCK_EX);
    error_log("TBV2_FILE_BRIDGE: $message");
}

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$orderId = $_GET['order_id'] ?? '';

log_bridge("=== BRIDGE API LLAMADO ===");
log_bridge("Order ID: $orderId");
log_bridge("Request Method: " . $_SERVER['REQUEST_METHOD']);

if (empty($orderId)) {
    log_bridge("ERROR: Order ID vacío");
    echo json_encode(['error' => 'Order ID requerido']);
    exit;
}

try {
    // Buscar archivos en directorio temporal
    $files_directory = "/tmp/tbv2_files_$orderId";
    $response = [
        'success' => true,
        'orderId' => $orderId,
        'files' => []
    ];
    
    log_bridge("Buscando archivos en: $files_directory");
    
    if (!is_dir($files_directory)) {
        log_bridge("Directorio no encontrado, creando respuesta vacía");
        echo json_encode($response);
        exit;
    }
    
    $files_list = scandir($files_directory);
    $files_list = array_filter($files_list, function($file) {
        return !in_array($file, ['.', '..']) && is_file("/tmp/tbv2_files_{$GLOBALS['orderId']}/$file");
    });
    
    log_bridge("Archivos encontrados: " . count($files_list));
    
    foreach ($files_list as $filename) {
        $filepath = "$files_directory/$filename";
        
        if (is_file($filepath)) {
            // Leer metadata del archivo (guardado como JSON)
            $metadata_file = "$filepath.meta";
            $file_data = null;
            
            if (file_exists($metadata_file)) {
                $metadata = json_decode(file_get_contents($metadata_file), true);
                if ($metadata) {
                    $file_data = [
                        'name' => $metadata['name'],
                        'content' => base64_encode(file_get_contents($filepath)),
                        'type' => $metadata['type'],
                        'field' => $metadata['field'],
                        'size' => $metadata['size']
                    ];
                    
                    log_bridge("Archivo procesado: {$metadata['name']} ({$metadata['size']} bytes)");
                }
            }
            
            // Fallback si no hay metadata
            if (!$file_data) {
                $file_data = [
                    'name' => basename($filename),
                    'content' => base64_encode(file_get_contents($filepath)),
                    'type' => mime_content_type($filepath) ?: 'application/octet-stream',
                    'field' => 'unknown',
                    'size' => filesize($filepath)
                ];
                
                log_bridge("Archivo sin metadata procesado: " . basename($filename));
            }
            
            $response['files'][] = $file_data;
        }
    }
    
    log_bridge("Total archivos preparados: " . count($response['files']));
    
    // Limpiar directorio temporal después del procesamiento
    if (count($response['files']) > 0) {
        log_bridge("Limpiando directorio temporal: $files_directory");
        $files_to_delete = glob("$files_directory/*");
        foreach($files_to_delete as $file) {
            if(is_file($file)) {
                unlink($file);
            }
        }
        rmdir($files_directory);
        log_bridge("Directorio temporal limpiado");
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    log_bridge("ERROR CRÍTICO: " . $e->getMessage());
    echo json_encode([
        'error' => 'Error del servidor: ' . $e->getMessage(),
        'orderId' => $orderId
    ]);
}
?>