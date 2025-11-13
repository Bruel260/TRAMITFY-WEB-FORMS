<?php
/**
 * TBV3 CALLBACK - Manejador de respuestas Redsys TPV
 * 
 * ✅ Maneja respuestas OK, KO y notificaciones del TPV
 * ✅ Recupera datos desde transients y envía a webhook VPS
 * ✅ Sistema idéntico al V2 pero para V3 TPV
 * 
 * @version 3.2.0 - TPV REDSYS CALLBACK
 * @author Claude Code  
 * @created 2025-11-13
 */

// Configurar para logs
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Función de logging
function tbv3_callback_log($message, $context = 'TBV3-CALLBACK', $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    
    if (is_array($message) || is_object($message)) {
        $message = json_encode($message, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    
    $log_entry = sprintf(
        "[%s] [%s] [%s] [IP:%s] %s\n",
        $timestamp,
        $level,
        $context,
        $ip,
        $message
    );
    
    // Log múltiple para garantizar visibilidad
    file_put_contents('/tmp/tramitfy-tbv3-callback.log', $log_entry, FILE_APPEND);
    file_put_contents('/tmp/tramitfy-debug.log', $log_entry, FILE_APPEND);
    error_log("TBV3-CALLBACK [$context] $level: $message");
}

tbv3_callback_log('=== INICIO CALLBACK TBV3 ===', 'CALLBACK-START', 'INFO');
tbv3_callback_log('GET: ' . json_encode($_GET), 'GET-DATA', 'INFO');
tbv3_callback_log('POST: ' . json_encode($_POST), 'POST-DATA', 'INFO');

// Configuración (mismas que en V3 TPV)
define('TBV3_REDSYS_SECRET', 'sq7HjrUOBfKmC576ILgskD5srU870gJ7');
define('TBV3_WEBHOOK_URL', 'https://46-202-128-35.sslip.io/api/herramientas/barcos/webhook');

/**
 * ✅ FUNCIÓN PARA GENERAR FIRMA REDSYS
 */
function tbv3_callback_generate_redsys_signature($parameters, $secret) {
    $order_id = $parameters['Ds_Order'] ?? $parameters['Ds_Merchant_Order'];
    
    // Derivar clave usando order
    $derived_key = hash_hmac('sha256', $order_id, base64_decode($secret), true);
    
    // Codificar parámetros
    $merchant_data = base64_encode(json_encode($parameters));
    
    // Generar firma
    $signature = hash_hmac('sha256', $merchant_data, $derived_key, true);
    return base64_encode($signature);
}

/**
 * ✅ VALIDAR RESPUESTA REDSYS
 */
function tbv3_callback_validate_response($merchant_params, $signature_received) {
    $params = json_decode(base64_decode($merchant_params), true);
    if (!$params) {
        tbv3_callback_log('Error decodificando parámetros', 'VALIDATION-ERROR', 'ERROR');
        return false;
    }
    
    $calculated_signature = tbv3_callback_generate_redsys_signature($params, TBV3_REDSYS_SECRET);
    
    tbv3_callback_log("Firma calculada: $calculated_signature", 'SIGNATURE-CALC', 'DEBUG');
    tbv3_callback_log("Firma recibida: $signature_received", 'SIGNATURE-RECV', 'DEBUG');
    
    return hash_equals($calculated_signature, $signature_received);
}

/**
 * ✅ ENVIAR A WEBHOOK VPS
 */
function tbv3_callback_send_to_webhook($complete_data) {
    tbv3_callback_log('Enviando datos a webhook VPS', 'WEBHOOK-SEND', 'INFO');
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => TBV3_WEBHOOK_URL,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($complete_data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'User-Agent: TBV3-Callback/1.0'
        ]
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($curl_error) {
        tbv3_callback_log("Error cURL: $curl_error", 'WEBHOOK-ERROR', 'ERROR');
        return false;
    }
    
    if ($http_code >= 200 && $http_code < 300) {
        tbv3_callback_log("Webhook enviado exitosamente - HTTP $http_code", 'WEBHOOK-SUCCESS', 'SUCCESS');
        tbv3_callback_log("Respuesta: $response", 'WEBHOOK-RESPONSE', 'INFO');
        return true;
    } else {
        tbv3_callback_log("Error HTTP $http_code: $response", 'WEBHOOK-FAIL', 'ERROR');
        return false;
    }
}

/**
 * ✅ BUSCAR TRÁMITE POR ORDER ID
 */
function tbv3_callback_find_tramite_by_order($order_id) {
    // Buscar en transients con patrón tbv3_payment_*
    global $wpdb;
    
    if (!$wpdb) {
        tbv3_callback_log('WordPress DB no disponible en callback', 'DB-ERROR', 'ERROR');
        return null;
    }
    
    $transients = $wpdb->get_results(
        "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE '_transient_tbv3_payment_%'"
    );
    
    foreach ($transients as $transient) {
        $data = maybe_unserialize($transient->option_value);
        if ($data && isset($data['order_id']) && $data['order_id'] === $order_id) {
            $tramite_id = str_replace(['_transient_tbv3_payment_', '_tbv3_payment_'], '', $transient->option_name);
            tbv3_callback_log("Trámite encontrado: $tramite_id para order: $order_id", 'TRAMITE-FOUND', 'SUCCESS');
            return $tramite_id;
        }
    }
    
    tbv3_callback_log("Trámite NO encontrado para order: $order_id", 'TRAMITE-NOT-FOUND', 'WARNING');
    return null;
}

// =====================================================
// PROCESAMIENTO PRINCIPAL
// =====================================================

try {
    
    // Detectar tipo de callback
    if (isset($_GET['result'])) {
        
        // ===== CALLBACK OK/KO =====
        $result = $_GET['result'];
        tbv3_callback_log("Callback resultado: $result", 'CALLBACK-TYPE', 'INFO');
        
        if ($result === 'ok') {
            
            // Pago exitoso - redirigir a página de confirmación
            $order_id = $_GET['Ds_Order'] ?? 'unknown';
            tbv3_callback_log("Pago exitoso - Order: $order_id", 'PAYMENT-OK', 'SUCCESS');
            
            // Buscar trámite
            $tramite_id = tbv3_callback_find_tramite_by_order($order_id);
            
            if ($tramite_id) {
                $success_url = "https://tramitfy.es/confirmacion-pago/?success=true&tramite=" . urlencode($tramite_id);
            } else {
                $success_url = "https://tramitfy.es/confirmacion-pago/?success=partial&order=" . urlencode($order_id);
            }
            
            header("Location: $success_url");
            exit;
            
        } else if ($result === 'ko') {
            
            // Pago fallido
            $order_id = $_GET['Ds_Order'] ?? 'unknown';
            tbv3_callback_log("Pago fallido - Order: $order_id", 'PAYMENT-KO', 'WARNING');
            
            header("Location: https://tramitfy.es/error-pago/?error=payment_failed&order=" . urlencode($order_id));
            exit;
        }
        
    } else if (isset($_GET['notification']) && $_GET['notification'] == '1') {
        
        // ===== NOTIFICACIÓN REDSYS =====
        tbv3_callback_log('Procesando notificación Redsys', 'NOTIFICATION', 'INFO');
        
        if (!isset($_POST['Ds_MerchantParameters'], $_POST['Ds_Signature'])) {
            tbv3_callback_log('Parámetros faltantes en notificación', 'NOTIFICATION-ERROR', 'ERROR');
            http_response_code(400);
            echo "ERROR: Parámetros faltantes";
            exit;
        }
        
        $merchant_params = $_POST['Ds_MerchantParameters'];
        $signature = $_POST['Ds_Signature'];
        
        // Validar firma
        if (!tbv3_callback_validate_response($merchant_params, $signature)) {
            tbv3_callback_log('Firma inválida en notificación', 'NOTIFICATION-INVALID', 'ERROR');
            http_response_code(400);
            echo "ERROR: Firma inválida";
            exit;
        }
        
        // Decodificar parámetros
        $params = json_decode(base64_decode($merchant_params), true);
        $order_id = $params['Ds_Order'];
        $response_code = $params['Ds_Response'];
        
        tbv3_callback_log("Notificación - Order: $order_id, Response: $response_code", 'NOTIFICATION-DATA', 'INFO');
        
        if (intval($response_code) >= 0 && intval($response_code) <= 99) {
            
            // Pago exitoso en notificación
            tbv3_callback_log("Pago confirmado en notificación - Order: $order_id", 'PAYMENT-CONFIRMED', 'SUCCESS');
            
            // Buscar trámite
            $tramite_id = tbv3_callback_find_tramite_by_order($order_id);
            
            if ($tramite_id) {
                
                // Recuperar datos completos
                $complete_data = get_transient('tbv3_payment_' . $tramite_id);
                
                if ($complete_data) {
                    
                    // Añadir datos de pago
                    $complete_data['payment_data'] = [
                        'order_id' => $order_id,
                        'amount' => $params['Ds_Amount'] ?? '',
                        'response' => $response_code,
                        'payment_method' => 'redsys',
                        'payment_date' => date('Y-m-d H:i:s'),
                        'status' => 'completed',
                        'redsys_params' => $params
                    ];
                    
                    tbv3_callback_log("Datos completos preparados para webhook", 'DATA-PREPARED', 'INFO');
                    
                    // Enviar a webhook VPS
                    $webhook_sent = tbv3_callback_send_to_webhook($complete_data);
                    
                    if ($webhook_sent) {
                        // Limpiar transient
                        delete_transient('tbv3_payment_' . $tramite_id);
                        tbv3_callback_log("Proceso completado exitosamente - Trámite: $tramite_id", 'PROCESS-COMPLETED', 'SUCCESS');
                        
                        echo "OK"; // Respuesta requerida por Redsys
                        exit;
                        
                    } else {
                        tbv3_callback_log("Error enviando webhook para trámite $tramite_id", 'WEBHOOK-FAILED', 'ERROR');
                        http_response_code(500);
                        echo "ERROR: Webhook failed";
                        exit;
                    }
                    
                } else {
                    tbv3_callback_log("Datos del trámite no encontrados: $tramite_id", 'DATA-NOT-FOUND', 'ERROR');
                    http_response_code(404);
                    echo "ERROR: Data not found";
                    exit;
                }
                
            } else {
                tbv3_callback_log("Trámite no encontrado para order: $order_id", 'TRAMITE-NOT-FOUND', 'ERROR');
                http_response_code(404);
                echo "ERROR: Tramite not found";
                exit;
            }
            
        } else {
            // Pago fallido en notificación
            tbv3_callback_log("Pago fallido en notificación - Order: $order_id, Code: $response_code", 'PAYMENT-FAILED-NOTIFICATION', 'WARNING');
            echo "OK"; // Respuesta requerida por Redsys
            exit;
        }
        
    } else {
        // Callback no reconocido
        tbv3_callback_log('Tipo de callback no reconocido', 'UNKNOWN-CALLBACK', 'WARNING');
        tbv3_callback_log('URL completa: ' . $_SERVER['REQUEST_URI'], 'UNKNOWN-URL', 'INFO');
        
        http_response_code(400);
        echo "ERROR: Callback type not recognized";
        exit;
    }

} catch (Exception $e) {
    tbv3_callback_log('Exception en callback: ' . $e->getMessage(), 'CALLBACK-EXCEPTION', 'CRITICAL');
    tbv3_callback_log('Stack trace: ' . $e->getTraceAsString(), 'CALLBACK-TRACE', 'DEBUG');
    
    http_response_code(500);
    echo "ERROR: Internal server error";
    exit;
}

tbv3_callback_log('=== FIN CALLBACK TBV3 ===', 'CALLBACK-END', 'INFO');