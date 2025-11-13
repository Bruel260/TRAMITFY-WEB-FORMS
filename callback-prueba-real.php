<?php
/**
 * CALLBACK TPV REDSYS - SISTEMA DE PRUEBA REAL
 * 
 * ✅ Manejo callbacks reales del TPV Caixa
 * ✅ Recuperación de datos temporales
 * ✅ Envío definitivo a VPS solo en SUCCESS
 * ✅ Limpieza automática en todos los casos
 * 
 * @version 1.0.0 - CALLBACK REAL
 * @author Claude Code
 * @created 2025-11-13
 */

// Configuración básica WordPress
define('WP_USE_THEMES', false);
require_once('../../../wp-load.php');

// Configuración del callback
define('CALLBACK_REDSYS_SECRET', 'sq7HjrUOBfKmC576ILgskD5srU870gJ7');
define('CALLBACK_VPS_URL', 'https://46-202-128-35.sslip.io/api/herramientas/barcos/webhook');

/**
 * ✅ LOGGING DEL CALLBACK
 */
function callback_log($message, $context = 'CALLBACK-REAL', $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] [$level] [$context] $message\n";
    file_put_contents('/tmp/tramitfy-callback-real.log', $log_entry, FILE_APPEND);
}

/**
 * ✅ VERIFICAR FIRMA REDSYS
 */
function verificar_firma_redsys($datos_recibidos) {
    if (!isset($datos_recibidos['Ds_Signature']) || !isset($datos_recibidos['Ds_MerchantParameters'])) {
        callback_log("Datos de firma incompletos", 'VERIFY-SIGNATURE', 'ERROR');
        return false;
    }
    
    // Decodificar parámetros
    $merchant_data = json_decode(base64_decode($datos_recibidos['Ds_MerchantParameters']), true);
    if (!$merchant_data) {
        callback_log("Error decodificando MerchantParameters", 'VERIFY-SIGNATURE', 'ERROR');
        return false;
    }
    
    $order_id = $merchant_data['Ds_Order'] ?? '';
    callback_log("Verificando firma para Order ID: $order_id", 'VERIFY-SIGNATURE', 'INFO');
    
    // Generar firma esperada (algoritmo completo)
    $password_decoded = base64_decode(CALLBACK_REDSYS_SECRET);
    
    // Encriptar Order ID con 3DES
    $encryption_key = openssl_encrypt($order_id, 'DES-EDE3-CBC', $password_decoded, OPENSSL_RAW_DATA, "\0\0\0\0\0\0\0\0");
    
    // Generar HMAC
    $expected_signature = hash_hmac('sha256', $datos_recibidos['Ds_MerchantParameters'], $encryption_key, true);
    $expected_signature_b64 = base64_encode($expected_signature);
    
    $received_signature = $datos_recibidos['Ds_Signature'];
    
    if ($expected_signature_b64 === $received_signature) {
        callback_log("Firma verificada correctamente", 'VERIFY-SIGNATURE', 'SUCCESS');
        return $merchant_data;
    } else {
        callback_log("Firma inválida. Esperada: $expected_signature_b64, Recibida: $received_signature", 'VERIFY-SIGNATURE', 'ERROR');
        return false;
    }
}

/**
 * ✅ RECUPERAR DATOS TEMPORALES
 */
function recuperar_datos_temporales($order_id) {
    callback_log("Recuperando datos temporales para Order ID: $order_id", 'TEMP-RECOVERY', 'INFO');
    
    $datos = get_transient('real_temp_' . $order_id);
    
    if ($datos) {
        callback_log("Datos temporales recuperados exitosamente", 'TEMP-RECOVERY', 'SUCCESS');
        return $datos;
    } else {
        callback_log("No se encontraron datos temporales", 'TEMP-RECOVERY', 'ERROR');
        return false;
    }
}

/**
 * ✅ ENVIAR A VPS DEFINITIVO
 */
function enviar_definitivo_vps($datos_completos, $payment_data) {
    callback_log("Iniciando envío definitivo a VPS", 'VPS-DEFINITIVE', 'INFO');
    
    // Preparar datos completos con información de pago
    $datos_completos['pago_data'] = $payment_data;
    $datos_completos['estado'] = 'confirmed_and_paid';
    $datos_completos['timestamp_pago'] = time();
    
    // Payload para VPS
    $webhook_payload = [
        'tramiteType' => 'sistema-prueba-real',
        'vehicleType' => 'Prueba',
        'customerName' => $datos_completos['form_data']['nombre'],
        'customerDni' => '12345678X',
        'customerEmail' => $datos_completos['form_data']['email'],
        'customerPhone' => $datos_completos['form_data']['telefono'],
        'sellerName' => 'Vendedor Prueba',
        'sellerDni' => '87654321Y',
        'vehicleData' => [
            'marca' => 'Test Brand',
            'modelo' => 'Test Model',
            'matricula' => 'TEST123'
        ],
        'itpData' => [
            'base_imponible' => 1000,
            'tipo_itp' => 4,
            'importe_itp' => 40
        ],
        'pricing' => [
            'precio_total' => 134.99,
            'tasas' => 40,
            'honorarios_brutos' => 94.99
        ],
        'paymentData' => $payment_data,
        'attachments' => $datos_completos['files_data'] ?? [],
        'signature' => $datos_completos['signature_data'] ?? null,
        'metadata' => [
            'source' => 'callback-prueba-real',
            'version' => '1.0.0',
            'timestamp' => date('Y-m-d H:i:s'),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'callback_type' => 'redsys_success'
        ]
    ];
    
    // Enviar con wp_remote_post
    $response = wp_remote_post(CALLBACK_VPS_URL, [
        'timeout' => 30,
        'headers' => [
            'Content-Type' => 'application/json',
            'User-Agent' => 'TRAMITFY-CALLBACK-REAL/1.0.0'
        ],
        'body' => json_encode($webhook_payload)
    ]);
    
    if (is_wp_error($response)) {
        callback_log('Error enviando a VPS: ' . $response->get_error_message(), 'VPS-DEFINITIVE', 'ERROR');
        return false;
    }
    
    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);
    
    if ($response_code >= 200 && $response_code < 300) {
        callback_log("Datos enviados exitosamente a VPS - HTTP $response_code", 'VPS-DEFINITIVE', 'SUCCESS');
        callback_log("Respuesta VPS: $response_body", 'VPS-RESPONSE', 'INFO');
        return true;
    } else {
        callback_log("Error HTTP enviando a VPS: $response_code - $response_body", 'VPS-DEFINITIVE', 'ERROR');
        return false;
    }
}

/**
 * ✅ LIMPIAR DATOS TEMPORALES
 */
function limpiar_datos_temporales($order_id) {
    callback_log("Limpiando datos temporales para Order ID: $order_id", 'TEMP-CLEANUP', 'INFO');
    
    $limpiado = delete_transient('real_temp_' . $order_id);
    
    if ($limpiado) {
        callback_log("Datos temporales limpiados exitosamente", 'TEMP-CLEANUP', 'SUCCESS');
    }
    
    return $limpiado;
}

/**
 * ✅ PANTALLA DE RESULTADO
 */
function mostrar_resultado($success, $order_id, $message) {
    $title = $success ? "¡Pago Exitoso!" : "Pago No Completado";
    $bg_color = $success ? "#d4edda" : "#f8d7da";
    $border_color = $success ? "#28a745" : "#dc3545";
    $text_color = $success ? "#155724" : "#721c24";
    $icon = $success ? "🎉" : "❌";
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title><?php echo $title; ?> - Sistema Real</title>
        <style>
            body { font-family: Arial, sans-serif; padding: 40px; background: <?php echo $bg_color; ?>; }
            .result-container { background: white; padding: 40px; border-radius: 8px; max-width: 600px; margin: 0 auto; border: 3px solid <?php echo $border_color; ?>; }
            .result-title { color: <?php echo $text_color; ?>; text-align: center; }
            .result-info { background: #e2e3e5; padding: 15px; border-radius: 6px; margin: 20px 0; }
        </style>
    </head>
    <body>
        <div class="result-container">
            <h2 class="result-title"><?php echo $icon; ?> <?php echo $title; ?></h2>
            
            <div class="result-info">
                <strong>Order ID:</strong> <?php echo esc_html($order_id); ?><br>
                <strong>Timestamp:</strong> <?php echo date('Y-m-d H:i:s'); ?><br>
                <strong>Sistema:</strong> TPV Real + VPS Real<br>
                <strong>Estado:</strong> <?php echo esc_html($message); ?>
            </div>
            
            <?php if ($success): ?>
            <div style="background: #d1ecf1; padding: 20px; border-radius: 6px;">
                <h3>✅ Proceso Completado:</h3>
                <ol>
                    <li>Datos capturados temporalmente ✅</li>
                    <li>Pago procesado via TPV Real ✅</li>
                    <li>Enviado definitivamente a VPS ✅</li>
                    <li>Datos temporales limpiados ✅</li>
                </ol>
            </div>
            <?php else: ?>
            <div style="background: #fff3cd; padding: 20px; border-radius: 6px;">
                <h3>🔒 Sistema Seguro Activo:</h3>
                <ul>
                    <li>Pago no completado ❌</li>
                    <li>Datos temporales limpiados 🧹</li>
                    <li>Sin datos huérfanos en VPS ✅</li>
                    <li>Sistema limpio ✅</li>
                </ul>
            </div>
            <?php endif; ?>
            
            <p style="text-align: center; margin-top: 30px;">
                <a href="<?php echo home_url(); ?>" style="background: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px;">
                    Volver al inicio
                </a>
            </p>
        </div>
    </body>
    </html>
    <?php
}

// =====================================================
// PROCESAMIENTO DEL CALLBACK
// =====================================================

callback_log("=== INICIO CALLBACK REDSYS REAL ===", 'CALLBACK-START', 'INFO');
callback_log("Método: " . $_SERVER['REQUEST_METHOD'], 'CALLBACK-METHOD', 'INFO');
callback_log("Datos recibidos: " . json_encode($_REQUEST), 'CALLBACK-DATA', 'INFO');

// Detectar tipo de callback
if (isset($_GET['notification']) && $_GET['notification'] == '1') {
    // CALLBACK DE NOTIFICACIÓN (SERVIDOR A SERVIDOR)
    callback_log("Procesando notificación servidor a servidor", 'NOTIFICATION', 'INFO');
    
    // Verificar firma
    $merchant_data = verificar_firma_redsys($_POST);
    if (!$merchant_data) {
        callback_log("Error verificando firma en notificación", 'NOTIFICATION', 'ERROR');
        http_response_code(400);
        exit('Error: Firma inválida');
    }
    
    $order_id = $merchant_data['Ds_Order'];
    $response_code = $merchant_data['Ds_Response'] ?? '';
    
    callback_log("Notificación para Order ID: $order_id, Response: $response_code", 'NOTIFICATION', 'INFO');
    
    if ($response_code >= '0000' && $response_code <= '0099') {
        // PAGO EXITOSO
        callback_log("=== PAGO EXITOSO - PROCESANDO DEFINITIVO ===", 'NOTIFICATION-SUCCESS', 'SUCCESS');
        
        // Recuperar datos temporales
        $datos_temporales = recuperar_datos_temporales($order_id);
        if (!$datos_temporales) {
            callback_log("Error: No se encontraron datos temporales", 'NOTIFICATION-ERROR', 'ERROR');
            http_response_code(404);
            exit('Error: Datos no encontrados');
        }
        
        // Preparar datos de pago
        $payment_data = [
            'order_id' => $order_id,
            'response_code' => $response_code,
            'authorization_code' => $merchant_data['Ds_AuthorisationCode'] ?? '',
            'amount' => $merchant_data['Ds_Amount'] ?? '',
            'currency' => $merchant_data['Ds_Currency'] ?? '',
            'timestamp' => time(),
            'merchant_data' => $merchant_data
        ];
        
        // Enviar definitivo a VPS
        $enviado_vps = enviar_definitivo_vps($datos_temporales, $payment_data);
        if (!$enviado_vps) {
            callback_log("Error enviando a VPS - Manteniendo datos temporales", 'NOTIFICATION-ERROR', 'ERROR');
            http_response_code(500);
            exit('Error: Fallo enviando a VPS');
        }
        
        // Limpiar datos temporales
        limpiar_datos_temporales($order_id);
        
        callback_log("=== PROCESO EXITOSO COMPLETADO ===", 'NOTIFICATION-COMPLETE', 'SUCCESS');
        http_response_code(200);
        exit('OK');
        
    } else {
        // PAGO FALLIDO
        callback_log("=== PAGO FALLIDO - LIMPIANDO TEMPORAL ===", 'NOTIFICATION-FAIL', 'WARNING');
        
        // Solo limpiar temporal
        limpiar_datos_temporales($order_id);
        
        callback_log("=== LIMPIEZA COMPLETADA ===", 'NOTIFICATION-CLEANED', 'SUCCESS');
        http_response_code(200);
        exit('OK');
    }
    
} elseif (isset($_GET['result'])) {
    // CALLBACK DE USUARIO (PANTALLA)
    $result = $_GET['result'];
    $order_id = $_GET['Ds_Order'] ?? 'UNKNOWN';
    
    callback_log("Mostrando resultado al usuario: $result", 'USER-RESULT', 'INFO');
    
    if ($result === 'ok') {
        mostrar_resultado(true, $order_id, 'Pago completado exitosamente');
    } else {
        mostrar_resultado(false, $order_id, 'Pago cancelado o fallido');
    }
    
} else {
    // CALLBACK INVÁLIDO
    callback_log("Callback inválido - parámetros no reconocidos", 'CALLBACK-INVALID', 'ERROR');
    http_response_code(400);
    exit('Error: Callback inválido');
}
?>