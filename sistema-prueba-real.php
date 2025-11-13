<?php
/**
 * TRAMITFY - SISTEMA DE PRUEBA REAL
 * 
 * ✅ TPV REDSYS REAL (Caixa)
 * ✅ ENVÍO REAL A VPS
 * ✅ METODOLOGÍA: "Retención hasta Confirmación TPV"
 * 
 * @version 1.1.0 - SISTEMA REAL CON TPV CAIXA
 * @author Claude Code  
 * @created 2025-11-13
 */

// Acceso solo via WordPress
if (!defined('ABSPATH')) {
    exit('Acceso directo no permitido.');
}

// =====================================================
// CONFIGURACIÓN REAL DEL SISTEMA
// =====================================================

// Configuración VPS Real
define('REAL_VPS_URL', 'https://46-202-128-35.sslip.io/api/herramientas/barcos/webhook');
define("REAL_TTL", 86400); // 2 horas para expiración temporal

// Configuración Redsys REAL (idéntica al V3 funcional)
define('REAL_REDSYS_MERCHANT', '363391103');
define('REAL_REDSYS_TERMINAL', '1');
define('REAL_REDSYS_SECRET', 'sq7HjrUOBfKmC576ILgskD5srU870gJ7');
define('REAL_REDSYS_CURRENCY', '978'); // EUR
define('REAL_REDSYS_URL_TEST', 'https://sis-t.redsys.es:25443/sis/realizarPago');
define('REAL_REDSYS_URL_LIVE', 'https://sis.redsys.es/sis/realizarPago');
define('REAL_REDSYS_MODE', 'test'); // test o live

// URLs callback (crear archivo callback separado)
define('REAL_REDSYS_URL_OK', 'https://tramitfy.es/callback-prueba-real.php?result=ok');
define('REAL_REDSYS_URL_KO', 'https://tramitfy.es/callback-prueba-real.php?result=ko'); 
define('REAL_REDSYS_URL_NOTIFICATION', 'https://tramitfy.es/callback-prueba-real.php?notification=1');

/**
 * ✅ LOGGING REAL
 */
function real_log($message, $context = 'REAL-SYSTEM', $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] [$level] [$context] $message\n";
    file_put_contents('/tmp/tramitfy-sistema-real.log', $log_entry, FILE_APPEND);
}

/**
 * ✅ GENERACIÓN DE ID ÚNICO (COMPATIBLE CON ORDER_ID REDSYS)
 */
function generar_order_id_real() {
    // Formato Redsys: 4 dígitos + 8 dígitos (total 12 caracteres)
    // Usar timestamp + microsegundos para garantizar unicidad
    $microtime = microtime(true);
    $timestamp_part = substr(str_replace('.', '', $microtime), -8);
    
    // Asegurar que tengamos exactamente 8 dígitos
    $order_id = str_pad($timestamp_part, 8, '0', STR_PAD_LEFT);
    
    // Validar que sea numérico y tenga 8 dígitos
    if (!ctype_digit($order_id) || strlen($order_id) !== 8) {
        // Fallback: usar timestamp actual
        $order_id = str_pad(substr(time(), -8), 8, '0', STR_PAD_LEFT);
    }
    
    real_log("Order ID generado: $order_id (formato: 8 dígitos)", 'ORDER-GEN', 'SUCCESS');
    return $order_id;
}

/**
 * ✅ ALGORITMO FIRMA REDSYS REAL (COPIADO EXACTO DEL V3 FUNCIONAL)
 */
function real_generate_redsys_signature($data, $secret = null) {
    if ($secret === null) {
        $secret = REAL_REDSYS_SECRET;
    }
    
    // Decodificacion en Base64 de la contraseña del comercio
    $password_decoded = base64_decode($secret);
    $order_id = $data['Ds_Merchant_Order'];
    
    real_log("Generando firma para Order ID: $order_id", 'SIGNATURE-GEN', 'INFO');
    
    // Deteccion de la version de PHP del servidor
    $php_version = substr(phpversion(), 0, 1);
    
    // Generacion de la clave de cifrado según versión PHP
    switch ($php_version) {
        case "5":
            // Código para PHP5 (mcrypt - deprecated)
            $bytes = array(0,0,0,0,0,0,0,0);
            $iv = implode(array_map("chr", $bytes));
            $encryption_key = mcrypt_encrypt(MCRYPT_3DES, $password_decoded, $order_id, MCRYPT_MODE_CBC, $iv);
            break;
            
        case "7":
        case "8": // Añadido soporte para PHP 8
        default:
            // Código para PHP7+ (OpenSSL) - IDÉNTICO AL V3
            $l = ceil(strlen($order_id) / 8) * 8;
            $padded_order_id = $order_id . str_repeat("\0", $l - strlen($order_id));
            $encryption_key = substr(
                openssl_encrypt(
                    $padded_order_id, 
                    'des-ede3-cbc', 
                    $password_decoded, 
                    OPENSSL_RAW_DATA, 
                    "\0\0\0\0\0\0\0\0"
                ), 
                0, 
                strlen($order_id)
            );
            break;
    }
    
    // Codificar datos merchant en Base64
    $merchant_data = base64_encode(json_encode($data));
    
    // Generar HMAC SHA256
    $signature = hash_hmac('sha256', $merchant_data, $encryption_key, true);
    
    // Codificar firma en Base64
    $signature_b64 = base64_encode($signature);
    
    real_log("Firma generada exitosamente", 'SIGNATURE-OK', 'SUCCESS');
    
    // IMPORTANTE: Esta función solo devuelve la firma, no el array completo
    return $signature_b64;
}

/**
 * ✅ PREPARAR PAGO REDSYS (IDÉNTICO AL V3)
 */
function real_prepare_redsys_payment($form_data, $total_amount) {
    // Generar order_id idéntico al V2/V3 funcional
    $microtime = microtime(true);
    $order_id = substr(str_replace('.', '', $microtime), -8);
    
    $amount_cents = intval($total_amount * 100);
    
    real_log("Order ID generado: $order_id, Amount: $amount_cents céntimos", 'REDSYS-ORDER', 'INFO');
    
    $parameters = [
        'Ds_Merchant_Amount' => $amount_cents,
        'Ds_Merchant_Order' => $order_id,
        'Ds_Merchant_MerchantCode' => REAL_REDSYS_MERCHANT,
        'Ds_Merchant_Currency' => REAL_REDSYS_CURRENCY,
        'Ds_Merchant_TransactionType' => '0',
        'Ds_Merchant_Terminal' => REAL_REDSYS_TERMINAL,
        'Ds_Merchant_MerchantURL' => REAL_REDSYS_URL_NOTIFICATION,
        'Ds_Merchant_UrlOK' => REAL_REDSYS_URL_OK,
        'Ds_Merchant_UrlKO' => REAL_REDSYS_URL_KO,
        'Ds_Merchant_ProductDescription' => 'Sistema Prueba Real',
        'Ds_Merchant_ConsumerLanguage' => '001', // Español
        'Ds_Merchant_MerchantName' => 'Tramitfy'
    ];
    
    real_log("Parámetros Redsys: " . json_encode($parameters), 'REDSYS-PARAMS', 'INFO');
    
    $merchant_parameters = base64_encode(json_encode($parameters));
    $signature = real_generate_redsys_signature($parameters, REAL_REDSYS_SECRET);
    
    $action_url = (REAL_REDSYS_MODE === 'live') ? REAL_REDSYS_URL_LIVE : REAL_REDSYS_URL_TEST;
    
    return [
        'Ds_SignatureVersion' => 'HMAC_SHA256_V1',
        'Ds_MerchantParameters' => $merchant_parameters,
        'Ds_Signature' => $signature,
        'action_url' => $action_url,
        'order_id' => $order_id
    ];
}

/**
 * ✅ GUARDADO TEMPORAL REAL
 */
function guardar_temporal_real($order_id, $datos_completos) {
    real_log("Guardando datos temporales - Order ID: $order_id", 'TEMPORAL-SAVE', 'INFO');
    
    $guardado = set_transient('real_temp_' . $order_id, $datos_completos, REAL_TTL);
    
    if ($guardado) {
        real_log("Datos temporales guardados exitosamente - Order ID: $order_id", 'TEMPORAL-OK', 'SUCCESS');
        return true;
    } else {
        real_log("Error guardando datos temporales - Order ID: $order_id", 'TEMPORAL-ERROR', 'ERROR');
        return false;
    }
}

/**
 * ✅ RECUPERAR TEMPORAL REAL
 */
function recuperar_temporal_real($order_id) {
    real_log("Recuperando datos temporales - Order ID: $order_id", 'TEMPORAL-GET', 'INFO');
    
    $datos = get_transient('real_temp_' . $order_id);
    
    if ($datos) {
        real_log("Datos temporales recuperados exitosamente - Order ID: $order_id", 'TEMPORAL-FOUND', 'SUCCESS');
        return $datos;
    } else {
        real_log("No se encontraron datos temporales - Order ID: $order_id", 'TEMPORAL-NOT-FOUND', 'WARNING');
        return false;
    }
}

/**
 * ✅ LIMPIAR TEMPORAL REAL
 */
function limpiar_temporal_real($order_id) {
    real_log("Limpiando datos temporales - Order ID: $order_id", 'TEMPORAL-CLEAN', 'INFO');
    
    $limpiado = delete_transient('real_temp_' . $order_id);
    
    if ($limpiado) {
        real_log("Datos temporales limpiados - Order ID: $order_id", 'TEMPORAL-CLEANED', 'SUCCESS');
    }
    
    return $limpiado;
}

/**
 * ✅ ENVÍO REAL AL VPS (COPIADO DEL V3 FUNCIONAL)
 */
function enviar_a_vps_real($datos_completos) {
    real_log("Iniciando envío REAL a VPS", 'VPS-SEND', 'INFO');
    
    // Preparar payload idéntico al V3 funcional
    $webhook_payload = [
        'tramiteType' => 'sistema-prueba-real',
        'vehicleType' => 'Prueba',
        'customerName' => $datos_completos['form_data']['nombre'],
        'customerDni' => '12345678X', // Datos de prueba
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
        'paymentData' => $datos_completos['pago_data'] ?? null,
        'attachments' => $datos_completos['files_data'] ?? [],
        'signature' => $datos_completos['signature_data'] ?? null,
        'metadata' => [
            'source' => 'sistema-prueba-real',
            'version' => '1.1.0',
            'timestamp' => date('Y-m-d H:i:s'),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? ''
        ]
    ];
    
    // Usar wp_remote_post como el V3 funcional
    $response = wp_remote_post(REAL_VPS_URL, [
        'timeout' => 30,
        'headers' => [
            'Content-Type' => 'application/json',
            'User-Agent' => 'TRAMITFY-REAL/1.1.0'
        ],
        'body' => json_encode($webhook_payload)
    ]);
    
    if (is_wp_error($response)) {
        real_log('Error enviando a VPS REAL: ' . $response->get_error_message(), 'VPS-ERROR', 'ERROR');
        return false;
    }
    
    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);
    
    if ($response_code >= 200 && $response_code < 300) {
        real_log("Datos enviados exitosamente a VPS REAL - HTTP $response_code", 'VPS-OK', 'SUCCESS');
        real_log("Respuesta VPS: $response_body", 'VPS-RESPONSE', 'INFO');
        return true;
    } else {
        real_log("Error HTTP enviando a VPS REAL: $response_code - $response_body", 'VPS-FAIL', 'ERROR');
        return false;
    }
}

/**
 * ✅ PROCESAMIENTO FORMULARIO REAL
 */
function procesar_formulario_real() {
    if (!isset($_POST['proceso_real'])) {
        return;
    }
    
    real_log("=== INICIO PROCESAMIENTO SISTEMA REAL ===", 'PROCESO-START', 'INFO');
    
    // 1. Generar Order ID único compatible con Redsys
    $order_id = generar_order_id_real();
    real_log("Order ID generado: $order_id", 'ID-GEN', 'SUCCESS');
    
    // 2. Capturar datos del formulario
    $form_data = [
        'nombre' => sanitize_text_field($_POST['nombre'] ?? ''),
        'email' => sanitize_email($_POST['email'] ?? ''),
        'telefono' => sanitize_text_field($_POST['telefono'] ?? ''),
        'mensaje' => sanitize_textarea_field($_POST['mensaje'] ?? '')
    ];
    
    // 3. Procesar archivos (real)
    $files_data = [];
    if (!empty($_FILES['documentos'])) {
        // Procesamiento real de archivos (simplificado para prueba)
        $files_data = ['archivo_real.pdf' => 'procesado'];
        real_log("Archivos procesados: " . count($_FILES['documentos']['name']), 'FILES-OK', 'SUCCESS');
    }
    
    // 4. Procesar firma
    $signature_data = null;
    if (!empty($_POST['signature_data'])) {
        $signature_data = $_POST['signature_data']; // base64
        real_log("Firma digital capturada", 'SIGNATURE-OK', 'SUCCESS');
    }
    
    // 5. Preparar datos completos
    $datos_completos = [
        'order_id' => $order_id,
        'timestamp_captura' => time(),
        'form_data' => $form_data,
        'files_data' => $files_data,
        'signature_data' => $signature_data,
        'estado' => 'captured_temporal'
    ];
    
    // 6. Guardar en almacén temporal
    $guardado_temporal = guardar_temporal_real($order_id, $datos_completos);
    
    if (!$guardado_temporal) {
        wp_die('Error guardando datos temporales');
    }
    
    // 7. Preparar redirección REAL al TPV Redsys usando función idéntica al V3
    real_log("Preparando redirección REAL al TPV Redsys", 'TPV-REDIRECT', 'INFO');
    
    // Usar función idéntica al V3 funcional
    $total_amount = 134.99; // €134.99
    $payment_config = real_prepare_redsys_payment($form_data, $total_amount);
    
    real_log("Configuración pago preparada: " . json_encode($payment_config), 'PAYMENT-CONFIG', 'INFO');
    
    // Mostrar formulario de redirección automática al TPV REAL
    mostrar_redireccion_tpv_real($payment_config);
    exit;
}

/**
 * ✅ REDIRECCIÓN AUTOMÁTICA AL TPV REAL
 */
function mostrar_redireccion_tpv_real($payment_config) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Redirigiendo al TPV - Sistema Real</title>
        <style>
            body { font-family: Arial, sans-serif; padding: 40px; background: #f0f8ff; text-align: center; }
            .redirect-container { background: white; padding: 40px; border-radius: 8px; max-width: 500px; margin: 0 auto; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
            .spinner { border: 4px solid #f3f3f3; border-top: 4px solid #007bff; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 20px auto; }
            @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        </style>
    </head>
    <body>
        <div class="redirect-container">
            <h2>🏦 Redirigiendo al TPV Real (Caixa)</h2>
            <div class="spinner"></div>
            <p><strong>Order ID:</strong> <?php echo esc_html($payment_config['order_id']); ?></p>
            <p>Conectando con Redsys...</p>
            <p><small>Si no se redirige automáticamente, haz click en "Continuar"</small></p>
            
            <form id="tpv-form" method="POST" action="<?php echo esc_url($payment_config['action_url']); ?>">
                <input type="hidden" name="Ds_SignatureVersion" value="<?php echo esc_attr($payment_config['Ds_SignatureVersion']); ?>">
                <input type="hidden" name="Ds_MerchantParameters" value="<?php echo esc_attr($payment_config['Ds_MerchantParameters']); ?>">
                <input type="hidden" name="Ds_Signature" value="<?php echo esc_attr($payment_config['Ds_Signature']); ?>">
                
                <button type="submit" style="background: #007bff; color: white; padding: 12px 24px; border: none; border-radius: 6px; cursor: pointer;">
                    Continuar al Pago
                </button>
            </form>
        </div>
        
        <script>
            console.log('🔄 Preparando redirección al TPV Real...');
            console.log('Order ID:', '<?php echo esc_js($payment_config['order_id']); ?>');
            console.log('Action URL:', '<?php echo esc_js($payment_config['action_url']); ?>');
            
            // Auto-envío tras 3 segundos
            setTimeout(function() {
                console.log('🚀 Redirigiendo al TPV Real...');
                document.getElementById('tpv-form').submit();
            }, 3000);
        </script>
    </body>
    </html>
    <?php
}

/**
 * ✅ FUNCIÓN PRINCIPAL DEL SHORTCODE REAL
 */
function sistema_prueba_real_shortcode() {
    // Procesar formulario si es POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        procesar_formulario_real();
        return;
    }
    
    // Mostrar formulario
    ob_start();
    ?>
    <div id="sistema-prueba-real">
        <style>
            #sistema-prueba-real { max-width: 800px; margin: 20px auto; font-family: Arial, sans-serif; }
            .form-container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
            .form-group { margin-bottom: 20px; }
            .form-group label { display: block; font-weight: bold; margin-bottom: 8px; color: #333; }
            .form-group input, .form-group textarea { width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 6px; font-size: 14px; }
            .form-group input:focus, .form-group textarea:focus { border-color: #007bff; outline: none; }
            .signature-container { border: 2px dashed #ddd; border-radius: 6px; padding: 20px; text-align: center; margin: 20px 0; }
            #signature-pad { border: 2px solid #007bff; border-radius: 6px; }
            .btn-primary { background: #28a745; color: white; padding: 15px 30px; border: none; border-radius: 6px; font-size: 16px; cursor: pointer; }
            .btn-secondary { background: #6c757d; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; margin-top: 10px; }
            .info-box { background: #d1ecf1; padding: 20px; border-radius: 6px; margin-bottom: 20px; border-left: 4px solid #007bff; }
            .warning-box { background: #fff3cd; padding: 20px; border-radius: 6px; margin-bottom: 20px; border-left: 4px solid #ffc107; }
        </style>
        
        <div class="form-container">
            <h2>🏦 Sistema de Prueba REAL</h2>
            
            <div class="info-box">
                <h3>🎯 SISTEMA REAL ACTIVO:</h3>
                <ul>
                    <li>✅ <strong>TPV REAL:</strong> Redirección a Redsys/Caixa</li>
                    <li>✅ <strong>VPS REAL:</strong> Envío a https://46-202-128-35.sslip.io</li>
                    <li>⏳ <strong>Temporal:</strong> WordPress transients (2h TTL)</li>
                    <li>💾 <strong>Definitivo:</strong> Solo tras confirmación TPV</li>
                </ul>
            </div>
            
            <div class="warning-box">
                <h3>⚠️ IMPORTANTE:</h3>
                <p>Este sistema usa el <strong>TPV REAL de test</strong>. Los datos se envían al <strong>VPS real</strong> tras confirmación de pago.</p>
                <p><strong>Datos de tarjeta test:</strong> 4548812049400004 | 12/20 | 123</p>
            </div>
            
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="nombre">Nombre Completo *</label>
                    <input type="text" id="nombre" name="nombre" required value="Usuario Prueba Real">
                </div>
                
                <div class="form-group">
                    <label for="email">Email *</label>
                    <input type="email" id="email" name="email" required value="test-real@tramitfy.com">
                </div>
                
                <div class="form-group">
                    <label for="telefono">Teléfono *</label>
                    <input type="tel" id="telefono" name="telefono" required value="123456789">
                </div>
                
                <div class="form-group">
                    <label for="documentos">Documentos de Prueba</label>
                    <input type="file" id="documentos" name="documentos[]" multiple accept=".pdf,.jpg,.png">
                    <small>Archivos de prueba (PDF, JPG, PNG)</small>
                </div>
                
                <div class="form-group">
                    <label for="mensaje">Mensaje</label>
                    <textarea id="mensaje" name="mensaje" rows="4" placeholder="Testing sistema real...">Probando metodología segura con TPV real y envío VPS real</textarea>
                </div>
                
                <div class="signature-container">
                    <h3>✍️ Firma Digital de Autorización</h3>
                    <p>Firma en el recuadro para autorizar el proceso:</p>
                    <canvas id="signature-pad" width="600" height="200"></canvas>
                    <br>
                    <button type="button" id="clear-signature" class="btn-secondary">Limpiar Firma</button>
                    <input type="hidden" id="signature_data" name="signature_data">
                </div>
                
                <div style="text-align: center; margin-top: 30px;">
                    <button type="submit" name="proceso_real" class="btn-primary">
                        🏦 Procesar con TPV REAL (€134.99)
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>
    <script>
        // Configurar firma digital
        const canvas = document.getElementById('signature-pad');
        const clearBtn = document.getElementById('clear-signature');
        const signatureInput = document.getElementById('signature_data');
        
        const signaturePad = new SignaturePad(canvas, {
            backgroundColor: 'rgb(255, 255, 255)',
            penColor: 'rgb(0, 0, 0)'
        });
        
        signaturePad.addEventListener('endStroke', () => {
            if (!signaturePad.isEmpty()) {
                signatureInput.value = signaturePad.toDataURL();
                console.log('✍️ Firma capturada para sistema real');
            }
        });
        
        clearBtn.addEventListener('click', () => {
            signaturePad.clear();
            signatureInput.value = '';
            console.log('🧹 Firma limpiada');
        });
        
        // Validación antes de envío
        document.querySelector('form').addEventListener('submit', function(e) {
            if (signaturePad.isEmpty()) {
                alert('⚠️ Por favor, firma para autorizar el proceso real');
                e.preventDefault();
                return;
            }
            
            console.log('🏦 Enviando al TPV REAL...');
            this.querySelector('button[type="submit"]').innerHTML = '⏳ Procesando...';
            this.querySelector('button[type="submit"]').disabled = true;
        });
        
        console.log('🏦 Sistema de prueba REAL inicializado');
    </script>
    <?php
    
    return ob_get_clean();
}

/**
 * ✅ REGISTRAR SHORTCODE REAL
 */
function registrar_sistema_prueba_real() {
    add_shortcode('sistema_prueba_real', 'sistema_prueba_real_shortcode');
}

add_action('init', 'registrar_sistema_prueba_real');

/**
 * ✅ HOOKS DE WORDPRESS
 */
add_action('init', function() {
    if (isset($_POST['proceso_real'])) {
        // Procesar en el hook init para evitar problemas de headers
    }
});

?>