<?php
/**
 * SCRIPT DE UPLOAD DIRECTO - BYPASS COMPLETO DE WORDPRESS
 * ======================================================
 * 
 * Este script evita completamente el sistema de WordPress
 * y sus posibles restricciones de seguridad/permisos.
 */

// Headers CORS y JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Configuración de errores
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Logging
$logFile = '/tmp/tbv2-direct-upload.log';
$timestamp = date('Y-m-d H:i:s');

function logMessage($message) {
    global $logFile, $timestamp;
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

logMessage("=== TBV2 DIRECT UPLOAD START ===");

try {
    // Verificar método POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }
    
    // Leer datos JSON del body
    $input = file_get_contents('php://input');
    logMessage("Raw input length: " . strlen($input));
    
    $data = json_decode($input, true);
    $jsonError = json_last_error();
    
    if ($jsonError !== JSON_ERROR_NONE) {
        throw new Exception('JSON inválido: ' . json_last_error_msg());
    }
    
    // Extraer datos
    $orderId = $data['orderId'] ?? '';
    $filesDataRaw = $data['filesData'] ?? '';
    $formDataRaw = $data['formData'] ?? '';
    
    logMessage("OrderId: $orderId");
    logMessage("FilesData length: " . strlen($filesDataRaw));
    
    if (empty($orderId)) {
        throw new Exception('OrderId vacío');
    }
    
    if (empty($filesDataRaw)) {
        throw new Exception('FilesData vacío');
    }
    
    // Decodificar archivos
    $filesData = json_decode($filesDataRaw, true);
    $formData = json_decode($formDataRaw, true);
    
    if (empty($filesData)) {
        throw new Exception('FilesData inválido tras decodificación');
    }
    
    // SIMULAR TRANSIENT CON ARCHIVO TEMPORAL
    $tempFile = '/tmp/tbv2_files_' . $orderId . '.json';
    
    $dataToStore = [
        'orderId' => $orderId,
        'filesData' => $filesData,
        'formData' => $formData,
        'timestamp' => time()
    ];
    
    $stored = file_put_contents($tempFile, json_encode($dataToStore, JSON_UNESCAPED_SLASHES));
    
    logMessage("Stored to temp file: $tempFile (size: $stored bytes)");
    
    if ($stored === false) {
        throw new Exception('Error escribiendo archivo temporal');
    }
    
    // Verificar que se puede leer
    $verification = file_get_contents($tempFile);
    if (!$verification) {
        throw new Exception('Error verificando archivo temporal');
    }
    
    logMessage("Verification successful - temp file readable");
    
    // Respuesta exitosa
    $response = [
        'success' => true,
        'message' => 'Archivos almacenados correctamente vía upload directo',
        'orderId' => $orderId,
        'filesCount' => count($filesData),
        'tempFile' => $tempFile,
        'timestamp' => $timestamp
    ];
    
    logMessage("SUCCESS: " . json_encode($response));
    
    echo json_encode($response);
    
} catch (Exception $e) {
    logMessage("ERROR: " . $e->getMessage());
    logMessage("Stack trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'timestamp' => $timestamp
    ]);
}

logMessage("=== TBV2 DIRECT UPLOAD END ===");
?>