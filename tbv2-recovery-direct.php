<?php
/**
 * SCRIPT DE RECOVERY DIRECTO - RECUPERAR ARCHIVOS DE UPLOAD DIRECTO
 * =================================================================
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

$logFile = '/tmp/tbv2-recovery-direct.log';
$timestamp = date('Y-m-d H:i:s');

function logMessage($message) {
    global $logFile, $timestamp;
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

logMessage("=== TBV2 DIRECT RECOVERY START ===");

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }
    
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('JSON inválido');
    }
    
    $orderId = $data['orderId'] ?? '';
    $action = $data['action'] ?? '';
    
    if (empty($orderId) || $action !== 'retrieve') {
        throw new Exception('Parámetros inválidos');
    }
    
    logMessage("Retrieving data for OrderId: $orderId");
    
    // Buscar archivo temporal
    $tempFile = '/tmp/tbv2_files_' . $orderId . '.json';
    
    if (!file_exists($tempFile)) {
        logMessage("Temp file not found: $tempFile");
        throw new Exception('Archivo temporal no encontrado');
    }
    
    $fileContent = file_get_contents($tempFile);
    if ($fileContent === false) {
        throw new Exception('Error leyendo archivo temporal');
    }
    
    $storedData = json_decode($fileContent, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Datos corruptos en archivo temporal');
    }
    
    logMessage("Successfully retrieved data from temp file");
    
    // Verificar que los datos son válidos
    if (!isset($storedData['filesData']) || !isset($storedData['orderId'])) {
        throw new Exception('Estructura de datos inválida');
    }
    
    $response = [
        'success' => true,
        'data' => $storedData,
        'source' => 'direct upload temp file',
        'timestamp' => $timestamp
    ];
    
    logMessage("SUCCESS: Data recovered for OrderId: $orderId");
    
    echo json_encode($response);
    
} catch (Exception $e) {
    logMessage("ERROR: " . $e->getMessage());
    
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'timestamp' => $timestamp
    ]);
}

logMessage("=== TBV2 DIRECT RECOVERY END ===");
?>