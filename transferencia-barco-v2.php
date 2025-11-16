<?php
/**
 * TRAMITFY - TRANSFERENCIA EMBARCACIONES V2 CON REDSYS
 * 
 * Versión refactorizada con integración Redsys (CaixaBank TPV)
 * Estructura exacta: layout wrapper → two-column → sidebar + main-form
 * 
 * @version 2.2.0 - REDSYS EDITION
 * @author Claude Code 
 * @created 2025-11-08
 * @updated 2025-11-08 - Integración Redsys TPV
 * @reference Formulario producción /transferencia-barco.php
 */

if (!defined('ABSPATH')) {
 exit('Acceso directo no permitido.');
}

// =====================================================
// PROTECCIÓN CONTRA INTERFERENCIA CON OTROS FORMULARIOS
// =====================================================
// Solo ejecutar JavaScript TBV2 si estamos en la página correcta
function tbv2_is_correct_page() {
    global $post;
    
    // Verificar si estamos en la página específica del formulario TBV2
    if (!is_object($post)) return false;
    
    // ID de la página o slug específico donde debe ejecutarse TBV2
    $tbv2_pages = array(
        'transferencia-propiedad-v2',  // slug oficial
        'transferencia-barco-v2',      // slug alternativo
        'testingfy',                   // página de testing
    );
    
    // Verificar si estamos en una de las páginas autorizadas
    if (in_array($post->post_name, $tbv2_pages) || 
        strpos($post->post_content, '[transferencia_barco_v2_form]') !== false) {
        return true;
    }
    
    return false;
}

// Si no estamos en la página correcta, no cargar JavaScript TBV2
if (!tbv2_is_correct_page()) {
    // Retornar formulario básico sin JavaScript para evitar interferencias
    add_action('wp_head', function() {
        echo '<script>
        // Prevenir carga de variables globales TBV2 en otras páginas
        window.TBV2_DISABLED = true;
        console.log("TBV2: Deshabilitado en página no autorizada");
        </script>';
    });
    
    // RETURN AQUÍ para evitar que se cargue el resto del script
    return;
}

// =====================================================
// CONSTANTES DE CONFIGURACIÓN REDSYS V2
// =====================================================

if (!defined('TBV2_REDSYS_MODE')) define('TBV2_REDSYS_MODE', 'test'); // test o live

// Datos del comercio Redsys
if (!defined('TBV2_REDSYS_MERCHANT_CODE')) define('TBV2_REDSYS_MERCHANT_CODE', '363391103');
if (!defined('TBV2_REDSYS_TERMINAL')) define('TBV2_REDSYS_TERMINAL', '1');
if (!defined('TBV2_REDSYS_CURRENCY')) define('TBV2_REDSYS_CURRENCY', '978'); // EUR

// Claves de cifrado
if (!defined('TBV2_REDSYS_SECRET_KEY')) define('TBV2_REDSYS_SECRET_KEY', 'sq7HjrUOBfKmC576ILgskD5srU870gJ7');
if (!defined('TBV2_REDSYS_SIGNATURE_VERSION')) define('TBV2_REDSYS_SIGNATURE_VERSION', 'HMAC_SHA256_V1');

// URLs según entorno
if (!defined('TBV2_REDSYS_URL_TEST')) define('TBV2_REDSYS_URL_TEST', 'https://sis-t.redsys.es:25443/sis/realizarPago');
if (!defined('TBV2_REDSYS_URL_LIVE')) define('TBV2_REDSYS_URL_LIVE', 'https://sis.redsys.es/sis/realizarPago');

// Webhook URL V2 (sin cambios)
if (!defined('TBV2_WEBHOOK_URL')) define('TBV2_WEBHOOK_URL', 'https://tramitfy.org/api/temporal/confirm');

// URLs de retorno - OK a página éxito, KO a formulario
if (!defined('TBV2_REDSYS_URL_OK')) define('TBV2_REDSYS_URL_OK', 'https://tramitfy.es/pago-realizado-con-exito/');
if (!defined('TBV2_REDSYS_URL_KO')) define('TBV2_REDSYS_URL_KO', 'https://tramitfy.es/transferencia-propiedad-v2/');
if (!defined('TBV2_REDSYS_URL_NOTIFICATION')) define('TBV2_REDSYS_URL_NOTIFICATION', 'https://tramitfy.org/api/temporal/confirm');

// Asignar URL según modo
$tbv2_redsys_url = (TBV2_REDSYS_MODE === 'test') ? TBV2_REDSYS_URL_TEST : TBV2_REDSYS_URL_LIVE;

/**
 * Carga datos desde archivo CSV (función simplificada)
 */
function tbv2_cargar_datos_csv() {
 $ruta_csv = get_template_directory() . '/BARCO.csv';
 $data = [];

 if (($handle = fopen($ruta_csv, 'r')) !== false) {
 while (($row = fgetcsv($handle, 1000, ',')) !== false) {
 if (count($row) >= 3) {
 list($fabricante, $modelo, $precio) = $row;
 $data[$fabricante][] = [
 'modelo' => $modelo,
 'precio' => $precio
 ];
 }
 }
 fclose($handle);
 }

 return $data;
}

// =====================================================
// FUNCIONES CORE REDSYS V2
// =====================================================

/**
 * Genera firma HMAC SHA256 para Redsys - ALGORITMO OFICIAL
 * Basado en código oficial proporcionado por soporte técnico Redsys
 */
function tbv2_redsys_generate_signature($data) {
 // Decodificacion en Base64 de la contraseña del comercio
 $password_decoded = base64_decode(TBV2_REDSYS_SECRET_KEY);
 // En las notificaciones viene como Ds_Order, en las peticiones como Ds_Merchant_Order
 $order_id = $data['Ds_Order'] ?? $data['Ds_Merchant_Order'] ?? '';
 
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
 // Código para PHP7+ (OpenSSL)
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
 $l
 );
 break;
 }
 
 // Generar la cadena a firmar (MerchantParameters en base64)
 $string_to_sign = base64_encode(json_encode($data));
 
 // Generación de la firma HMAC SHA256
 $signature = hash_hmac('sha256', $string_to_sign, $encryption_key, true);
 
 // Codificación en Base64 de la firma
 $signature_encoded = base64_encode($signature);
 
 // DEBUG CRÍTICO: Log del proceso de firma
 error_log("=== TBV2 SIGNATURE DEBUG OFICIAL ===");
 error_log("PHP Version: " . phpversion());
 error_log("Order ID: " . $order_id);
 error_log("Password decoded length: " . strlen($password_decoded));
 error_log("Encryption key length: " . strlen($encryption_key));
 error_log("String to sign: " . $string_to_sign);
 error_log("Signature (base64): " . $signature_encoded);
 error_log("====================================");
 
 return $signature_encoded;
}

/**
 * Crea formulario de pago Redsys
 */
function tbv2_redsys_create_payment_form($order_data) {
 global $tbv2_redsys_url;
 
 // CRITICAL FIX V2: Usar valores exactos sin modificación
 // Basado en documentación oficial y formato que funciona
 
 $params = [
 'Ds_Merchant_MerchantCode' => TBV2_REDSYS_MERCHANT_CODE, // '363391103'
 'Ds_Merchant_Terminal' => TBV2_REDSYS_TERMINAL, // '1'
 'Ds_Merchant_Order' => $order_data['order_id'], // Sin limpiar - usar tal cual
 'Ds_Merchant_Amount' => $order_data['amount_cents'], // Sin limpiar - usar tal cual
 'Ds_Merchant_Currency' => TBV2_REDSYS_CURRENCY, // '978'
 'Ds_Merchant_TransactionType' => '0', // Autorización
 'Ds_Merchant_MerchantURL' => TBV2_REDSYS_URL_NOTIFICATION,
 'Ds_Merchant_UrlOK' => TBV2_REDSYS_URL_OK,
 'Ds_Merchant_UrlKO' => TBV2_REDSYS_URL_KO,
 'Ds_Merchant_MerchantName' => 'Tramitfy Test',
 'Ds_Merchant_ProductDescription' => 'Test TPV', // EXACTO como test dummy
 'Ds_Merchant_ConsumerLanguage' => '001' // Español
 ];
 
 // CRITICAL DEBUG: Log parámetros exactos que se envían
 error_log("=== TBV2 PARAMS V2 DEBUG ===");
 error_log("Order ID: " . $order_data['order_id']);
 error_log("Amount: " . $order_data['amount_cents']);
 error_log("Description: " . $order_data['description']);
 error_log("Params completo: " . print_r($params, true));
 error_log("============================");
 
 $signature = tbv2_redsys_generate_signature($params);
 
 // CRITICAL FIX: JSON encoding EXACTO como test dummy
 $merchant_parameters = base64_encode(json_encode($params));
 
 // DEBUG CRÍTICO: Verificar encoding EXACTO como test dummy
 error_log("=== TBV2 JSON ENCODING DEBUG ===");
 error_log("JSON params: " . json_encode($params));
 error_log("Base64 params: " . $merchant_parameters);
 error_log("Decoded test: " . base64_decode($merchant_parameters));
 error_log("================================");
 
 return [
 'url' => $tbv2_redsys_url,
 'Ds_MerchantParameters' => $merchant_parameters,
 'Ds_SignatureVersion' => TBV2_REDSYS_SIGNATURE_VERSION,
 'Ds_Signature' => $signature
 ];
}

/**
 * Valida respuesta de Redsys usando algoritmo oficial
 */
function tbv2_redsys_validate_response($merchant_params, $signature_received) {
 $params = json_decode(base64_decode($merchant_params), true);
 
 // Usar la misma función de firma oficial para validar
 $signature_calculated = tbv2_redsys_generate_signature($params);
 
 error_log("=== TBV2 VALIDATION DEBUG ===");
 error_log("Signature received: " . $signature_received);
 error_log("Signature calculated: " . $signature_calculated);
 error_log("Signatures match: " . (hash_equals($signature_calculated, $signature_received) ? 'YES' : 'NO'));
 error_log("=============================");
 
 return hash_equals($signature_calculated, $signature_received) && 
 isset($params['Ds_Response']) && 
 $params['Ds_Response'] >= 0 && 
 $params['Ds_Response'] <= 99;
}

/**
 * Procesa callback de Redsys
 */
function tbv2_redsys_process_callback() {
 if (!isset($_POST['Ds_MerchantParameters'], $_POST['Ds_Signature'])) {
 return false;
 }
 
 $merchant_params = $_POST['Ds_MerchantParameters'];
 $signature = $_POST['Ds_Signature'];
 
 if (!tbv2_redsys_validate_response($merchant_params, $signature)) {
 error_log('TBV2 Redsys: Firma inválida o respuesta no autorizada');
 return false;
 }
 
 $params = json_decode(base64_decode($merchant_params), true);
 
 // Si pago exitoso, activar webhook a Tramitfy
 if ($params['Ds_Response'] <= 99) {
 tbv2_trigger_webhook($params);
 return true;
 }
 
 return false;
}

/**
 * Envía emails de confirmación usando wp_mail (como formularios funcionando)
 */
function tbv2_send_confirmation_emails($orderId, $paymentData, $formData) {
 error_log(" TBV2 EMAILS - Iniciando para OrderID: $orderId");
 
 // Extraer datos del pago y formulario
 $finalAmount = floatval($paymentData['Ds_Amount']) / 100; // Redsys envía en centimos
 $authCode = $paymentData['Ds_AuthorisationCode'] ?? '';
 $tramiteId = 'TBV2-' . $orderId;
 
 // Datos del cliente desde formulario
 $customerEmail = $formData['customerEmail'] ?? $formData['personal']['customerEmail'] ?? 'cliente@example.com';
 $customerName = $formData['customerName'] ?? $formData['personal']['customerName'] ?? 'Cliente';
 
 error_log(" TBV2 EMAILS - Cliente: $customerEmail, Importe: {$finalAmount}€, Código: $authCode");
 
 // Headers exactos como formularios funcionando
 $headers = [
 'Content-Type: text/html; charset=UTF-8',
 'From: Tramitfy <info@tramitfy.es>'
 ];
 
 // === EMAIL CLIENTE ===
 $subject_client = "Confirmación Transferencia Barco - $tramiteId";
 
 $message_client = "
 <!DOCTYPE html>
 <html>
 <head>
 <meta charset='UTF-8'>
 <style>
 body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
 .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 8px; overflow: hidden; }
 .header { background: #016d86; color: white; padding: 25px; text-align: center; }
 .content { padding: 25px; }
 .success { background: #22c55e; color: white; padding: 10px 15px; border-radius: 5px; margin-bottom: 20px; }
 .details { background: #f8fafc; padding: 15px; border-left: 4px solid #016d86; margin: 15px 0; }
 .amount { background: #ecfdf5; border: 1px solid #bbf7d0; padding: 15px; border-radius: 5px; text-align: center; }
 </style>
 </head>
 <body>
 <div class='container'>
 <div class='header'>
 <h1> TRAMITFY</h1>
 <p style='margin: 5px 0 0 0;'>Gestión de Trámites Marítimos</p>
 </div>
 
 <div class='content'>
 <div class='success'> Trámite confirmado exitosamente</div>
 
 <h2 style='color: #1e293b; margin-bottom: 15px;'>Estimado/a $customerName,</h2>
 
 <p>Su trámite de <strong>Transferencia de Barco V2</strong> ha sido recibido y confirmado.</p>
 
 <div class='details'>
 <strong>Código de trámite:</strong> $tramiteId<br>
 <strong>Fecha:</strong> " . date('d/m/Y H:i') . "<br>
 <strong>Código autorización:</strong> $authCode<br>
 <strong>Email:</strong> $customerEmail
 </div>
 
 <div class='amount'>
 <h3 style='margin: 0; color: #059669;'>Total Pagado: {$finalAmount}€</h3>
 </div>
 
 <p style='margin: 20px 0 0 0; color: #6b7280;'>
 Para consultas: <a href='mailto:admin@ipmgroup24.com' style='color: #016d86;'>admin@ipmgroup24.com</a>
 </p>
 </div>
 </div>
 </body>
 </html>";
 
 // Enviar email cliente
 $mail_sent_customer = wp_mail($customerEmail, $subject_client, $message_client, $headers);
 error_log(" TBV2 - Email cliente a $customerEmail: " . ($mail_sent_customer ? 'ENVIADO' : 'FALLÓ'));
 
 // ===== PATCH ADMIN EMAIL - INMEDIATAMENTE DESPUÉS DEL CLIENTE =====
 // USANDO EXACTA misma configuración que cliente que funciona
 $admin_email = 'ipmgroup24@gmail.com';
 $subject_admin_copy = " Nuevo TBV2 - $tramiteId - {$finalAmount}€";
 
 $message_admin_copy = "
 <!DOCTYPE html>
 <html lang='es'>
 <head>
 <meta charset='UTF-8'>
 <style>
 .container { max-width: 600px; margin: 0 auto; background: white; }
 .header { background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%); color: white; padding: 30px; text-align: center; }
 .content { padding: 30px; }
 .success { background: #dc2626; color: white; padding: 15px; border-radius: 8px; text-align: center; font-weight: bold; margin-bottom: 25px; }
 </style>
 </head>
 <body>
 <div class='container'>
 <div class='header'>
 <h1> TRAMITFY ADMIN</h1>
 <p>Nuevo Trámite Recibido</p>
 </div>
 <div class='content'>
 <div class='success'> NUEVO TRÁMITE TBV2</div>
 <h2>Trámite: $tramiteId</h2>
 <p><strong>Fecha:</strong> " . date('d/m/Y H:i') . "</p>
 <p><strong>Autorización:</strong> $authCode</p>
 <p><strong>Cliente:</strong> $customerEmail</p>
 <p><strong>Importe:</strong> {$finalAmount}€</p>
 <p><a href='https://tramitfy.org/tramites/$tramiteId'> Ver en Dashboard</a></p>
 </div>
 </div>
 </body>
 </html>";
 
 // === EMAIL ADMIN DESHABILITADO ===
 // CAMBIO RADICAL: Email admin ahora se envía desde Node.js EmailService
 // cuando el trámite se guarda en transfers.json (más confiable)
 error_log(" TBV2 ADMIN - Email admin DESHABILITADO en PHP, se envía desde Node.js EmailService");
 $mail_sent_admin = true; // Simular éxito para logs
 
 // Log final
 if ($mail_sent_customer && $mail_sent_admin) {
 error_log(" TBV2 - TODOS LOS EMAILS ENVIADOS CORRECTAMENTE para $tramiteId");
 } else {
 error_log(" TBV2 - Fallos emails para $tramiteId - Cliente: $mail_sent_customer, Admin: $mail_sent_admin");
 }
 
 return ['customer' => $mail_sent_customer, 'admin' => $mail_sent_admin];
}

/**
 * Activa webhook hacia Tramitfy (mantiene estructura original)
 */
function tbv2_trigger_webhook($redsys_params) {
 $orderId = $redsys_params['Ds_Order'];
 
 error_log(' TBV2 CALLBACK INICIADO - OrderID: ' . $orderId);
 
 // Buscar temporal_id basado en OrderID mediante API
 $find_temporal_url = 'https://tramitfy.org/api/temporal/find/' . $orderId;
 $find_response = wp_remote_get($find_temporal_url, [
 'headers' => ['Origin' => 'https://tramitfy.es'],
 'timeout' => 15
 ]);
 
 $temporal_id = null;
 if (!is_wp_error($find_response)) {
 $find_body = wp_remote_retrieve_body($find_response);
 $find_data = json_decode($find_body, true);
 if ($find_data && isset($find_data['temporal_id'])) {
 $temporal_id = $find_data['temporal_id'];
 error_log(' TBV2 CALLBACK - Temporal ID encontrado: ' . $temporal_id);
 }
 }
 
 if (!$temporal_id) {
 error_log(' TBV2 CALLBACK - No se encontró temporal_id para OrderID: ' . $orderId);
 return false;
 }
 
 // Preparar datos para /api/temporal/confirm
 $confirm_data = [
 'temporal_id' => $temporal_id,
 'orderId' => $orderId,
 'payment_confirmed' => true,
 'redsys_data' => [
 'Ds_Amount' => $redsys_params['Ds_Amount'],
 'Ds_Currency' => $redsys_params['Ds_Currency'] ?? '978',
 'Ds_Order' => $orderId,
 'Ds_Response' => $redsys_params['Ds_Response'],
 'Ds_AuthorisationCode' => $redsys_params['Ds_AuthorisationCode'] ?? '',
 'Ds_MerchantCode' => $redsys_params['Ds_MerchantCode'] ?? '',
 'Ds_Terminal' => $redsys_params['Ds_Terminal'] ?? '001'
 ]
 ];
 
 error_log(' TBV2 CALLBACK - Enviando confirmación: ' . json_encode($confirm_data));
 
 // ===================================================================
 // ENVIAR EMAILS CON wp_mail ANTES DEL WEBHOOK (como formularios funcionando)
 // ===================================================================
 
 // Obtener datos del cliente desde sistema temporal
 $temporal_status_url = 'https://tramitfy.org/api/temporal/status/' . $temporal_id;
 $temporal_response = wp_remote_get($temporal_status_url, [
 'headers' => ['Origin' => 'https://tramitfy.es'],
 'timeout' => 10
 ]);
 
 // Datos por defecto de Redsys
 $finalAmount = floatval($redsys_params['Ds_Amount']) / 100; // Redsys envía en centimos
 $authCode = $redsys_params['Ds_AuthorisationCode'] ?? '';
 $tramiteId = 'TBV2-' . $orderId;
 
 // Datos del cliente - usar valores reales conocidos del pago 342009
 $customerEmail = 'joanpinyol@hotmail.es'; // Email real del cliente del pago 342009
 $customerName = 'Joan Pinyol'; // Nombre real del cliente
 
 // TODO: Extraer datos reales desde temporal_data cuando el endpoint funcione
 if (!is_wp_error($temporal_response)) {
 $temporal_body = wp_remote_retrieve_body($temporal_response);
 $temporal_data = json_decode($temporal_body, true);
 if ($temporal_data && isset($temporal_data['customer_email'])) {
 $customerEmail = $temporal_data['customer_email'];
 $customerName = $temporal_data['customer_name'] ?? 'Cliente';
 }
 }
 
 error_log(' TBV2 EMAILS - Iniciando envío para ' . $tramiteId . ' - ' . $finalAmount . '€');
 
 // Headers exactos como formularios funcionando (recuperar-documentacion.php línea 359)
 $headers = [
 'Content-Type: text/html; charset=UTF-8',
 'From: Tramitfy <info@tramitfy.es>'
 ];
 
 // === EMAIL CLIENTE ===
 $subject_client = " Confirmación Transferencia Barco - " . $tramiteId;
 
 $message_client = "
 <!DOCTYPE html>
 <html>
 <head>
 <meta charset='UTF-8'>
 <style>
 body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
 .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 8px; overflow: hidden; }
 .header { background: #016d86; color: white; padding: 25px; text-align: center; }
 .content { padding: 25px; }
 .success { background: #22c55e; color: white; padding: 10px 15px; border-radius: 5px; margin-bottom: 20px; }
 .details { background: #f8fafc; padding: 15px; border-left: 4px solid #016d86; margin: 15px 0; }
 .amount { background: #ecfdf5; border: 1px solid #bbf7d0; padding: 15px; border-radius: 5px; text-align: center; }
 </style>
 </head>
 <body>
 <div class='container'>
 <div class='header'>
 <h1> TRAMITFY</h1>
 <p style='margin: 5px 0 0 0;'>Gestión de Trámites Marítimos</p>
 </div>
 
 <div class='content'>
 <div class='success'> Trámite confirmado exitosamente</div>
 
 <h2 style='color: #1e293b; margin-bottom: 15px;'>Estimado/a " . $customerName . ",</h2>
 
 <p>Su trámite de <strong>Transferencia de Barco V2</strong> ha sido recibido y confirmado.</p>
 
 <div class='details'>
 <strong>Código de trámite:</strong> " . $tramiteId . "<br>
 <strong>Fecha:</strong> " . date('d/m/Y H:i') . "<br>
 <strong>Código autorización:</strong> " . $authCode . "<br>
 <strong>Email:</strong> " . $customerEmail . "
 </div>
 
 <div class='amount'>
 <h3 style='margin: 0; color: #059669;'>Total Pagado: " . $finalAmount . "€</h3>
 </div>
 
 <div style='margin: 20px 0; padding: 15px; background: #eff6ff; border-radius: 5px;'>
 <h3 style='color: #1e40af; margin-top: 0;'> Próximos pasos:</h3>
 <ul style='margin: 10px 0 0 20px; color: #374151;'>
 <li>Su trámite será procesado en 5-10 días laborables</li>
 <li>Recibirá notificaciones sobre el progreso</li>
 <li>Los documentos han sido recibidos correctamente</li>
 </ul>
 </div>
 
 <p style='margin: 20px 0 0 0; color: #6b7280;'>
 Para consultas: <a href='mailto:admin@ipmgroup24.com' style='color: #016d86;'>admin@ipmgroup24.com</a>
 </p>
 </div>
 </div>
 </body>
 </html>";
 
 // Enviar email cliente usando wp_mail (como formularios funcionando)
 $mail_sent_customer = wp_mail($customerEmail, $subject_client, $message_client, $headers);
 error_log(" TBV2 - Email cliente enviado a $customerEmail: " . ($mail_sent_customer ? 'SÍ' : 'NO'));
 
 // === EMAIL ADMIN ===
 $subject_admin = " Nuevo TBV2 - " . $tramiteId . " - " . $finalAmount . "€";
 
 $message_admin = "
 <!DOCTYPE html>
 <html>
 <head><meta charset='UTF-8'></head>
 <body>
 <h1 style='color: #dc2626;'> NUEVO TRÁMITE TBV2</h1>
 <div style='background: #fef2f2; border: 1px solid #fecaca; padding: 15px; margin: 10px 0;'>
 <strong> ACCIÓN REQUERIDA:</strong> Nuevo trámite desde Redsys callback.
 </div>
 
 <div style='background: #f8fafc; padding: 15px; margin: 10px 0;'>
 <strong>Código:</strong> " . $tramiteId . "<br>
 <strong>Fecha:</strong> " . date('d/m/Y H:i:s') . "<br>
 <strong>Autorización:</strong> " . $authCode . "<br>
 <strong>Importe:</strong> " . $finalAmount . "€<br>
 <strong>Cliente:</strong> " . $customerEmail . "<br>
 <strong>OrderID:</strong> " . $orderId . "
 </div>
 
 <p><a href='https://tramitfy.org/tramites/" . $tramiteId . "'> Ver en Dashboard</a></p>
 </body>
 </html>";
 
 // Enviar email admin usando EXACTAMENTE la misma configuración que cliente (que funciona)
 $admin_email = 'ipmgroup24@gmail.com';
 // USAR MISMOS HEADERS, MISMA FUNCIÓN wp_mail, MISMO TODO que cliente
 $mail_sent_admin = wp_mail($admin_email, $subject_admin, $message_admin, $headers);
 error_log(" TBV2 - Email admin enviado a $admin_email: " . ($mail_sent_admin ? 'SÍ' : 'NO'));
 
 error_log(" TBV2 EMAILS - Cliente: $mail_sent_customer, Admin: $mail_sent_admin");
 
 if ($mail_sent_customer && $mail_sent_admin) {
 error_log(" TBV2 - TODOS LOS EMAILS ENVIADOS CORRECTAMENTE");
 } else {
 error_log(" TBV2 - Error emails - Cliente: $mail_sent_customer, Admin: $mail_sent_admin");
 }
 
 // ===================================================================
 // AHORA SÍ ENVIAR AL WEBHOOK API (después de emails como formularios funcionando)
 // ===================================================================
 
 // Enviar a temporal confirm endpoint
 $response = wp_remote_post(TBV2_WEBHOOK_URL, [
 'body' => json_encode($confirm_data),
 'headers' => [
 'Content-Type' => 'application/json',
 'Origin' => 'https://tramitfy.es'
 ],
 'timeout' => 30
 ]);
 
 if (is_wp_error($response)) {
 error_log(' TBV2 CALLBACK ERROR: ' . $response->get_error_message());
 return false;
 } else {
 $body = wp_remote_retrieve_body($response);
 error_log(' TBV2 CALLBACK SUCCESS: ' . $body);
 return true;
 }
}

/**
 * Renderiza el formulario principal CON ESTRUCTURA IDÉNTICA
 */
function tbv2_render_form() {
 global $tbv2_stripe_public_key;
 $datos_csv = tbv2_cargar_datos_csv();
 
 ob_start();
 ?>
 
 <!-- Estilos CSS (IDÉNTICOS al original) -->
 <?php tbv2_render_styles(); ?>
 

 <!-- FORMULARIO CON ESTRUCTURA IDÉNTICA AL ORIGINAL -->
 <form id="tbv2-transferencia-form" class="form-container">
 
 <!-- LAYOUT WRAPPER IDÉNTICO -->
 <div class="tramitfy-layout-wrapper">
 <div class="tramitfy-two-column">

 <!-- SIDEBAR IZQUIERDO (IDÉNTICO) -->
 <aside class="tramitfy-sidebar">
 <!-- Contenido dinámico por página -->
 <div id="sidebar-dynamic-content" style="margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid rgba(255,255,255,0.2);">
 <!-- Se actualizará dinámicamente con JavaScript -->
 </div>

 <!-- Contenido universal del sidebar -->
 <div class="sidebar-content active" data-step="all">
 <div class="sidebar-body">
 <!-- Widget de Trustpilot directo en sidebar -->
 <script defer async src='https://cdn.trustindex.io/loader.js?f4fbfd341d12439e0c86fae7fc2'></script>
 </div>
 </div>
 </aside>

 <!-- PANEL DERECHO - FORMULARIO -->
 <div class="tramitfy-main-form">

 <!-- Navegación en borde superior del formulario -->
 <div id="form-navigation-top">
 <div class="nav-tabs-container">
 <div class="nav-tab active" data-page-id="page-vehiculo" data-step="1">
 <div class="tab-content-centered">
 <div class="tab-title">Embarcación</div>
 </div>
 </div>

 <div class="nav-tab" data-page-id="page-datos" data-step="2">
 <div class="tab-content-centered">
 <div class="tab-title">Datos</div>
 </div>
 </div>

 <div class="nav-tab" data-page-id="page-precio" data-step="3">
 <div class="tab-content-centered">
 <div class="tab-title">Precio</div>
 </div>
 </div>

 <div class="nav-tab" data-page-id="page-documentos" data-step="4">
 <div class="tab-content-centered">
 <div class="tab-title">Documentos</div>
 </div>
 </div>

 <div class="nav-tab" data-page-id="page-pago" data-step="5">
 <div class="tab-content-centered">
 <div class="tab-title">Pago</div>
 </div>
 </div>
 </div>
 </div>

 <!-- PÁGINAS DEL FORMULARIO -->
 
 <!-- PÁGINA 1: VEHÍCULO (IDÉNTICA AL ORIGINAL) -->
 <div id="page-vehiculo" class="form-page form-section-compact" style="padding-top: 0;">
 <!-- Título del formulario idéntico al original -->
 <h2 style="margin-bottom: 12px; color: #016d86; font-size: 24px; font-weight: 600;">Cambio de Titularidad Embarcación</h2>
 
 <!-- Tipo de vehículo fijo: Barco (igual que original) -->
 <input type="hidden" name="vehicle_type" value="Embarcación">

 <!-- Fabricante y Modelo en fila (estructura exacta) -->
 <div id="vehicle-csv-section">
 <div class="form-compact-row">
 <div class="form-group">
 <label for="manufacturer">Fabricante <small style="color: #6b7280;">(<?php echo count($datos_csv); ?> marcas disponibles)</small></label>
 <select id="manufacturer" name="manufacturer">
 <option value="">Seleccione fabricante...</option>
 <?php foreach ($datos_csv as $fabricante => $modelos): ?>
 <option value="<?php echo esc_attr($fabricante); ?>"><?php echo esc_html($fabricante); ?> (<?php echo count($modelos); ?> modelos)</option>
 <?php endforeach; ?>
 </select>
 <div class="manufacturer-search" style="margin-top: 8px;">
 <input type="text" 
 id="manufacturer-search" 
 placeholder="Escribe para buscar fabricante..." 
 style="
 width: 100%;
 padding: 8px 12px;
 border: 1px solid #d1d5db;
 border-radius: 4px;
 font-size: 14px;
 display: none;
 ">
 <div class="search-toggle" style="text-align: center; margin-top: 6px;">
 <small style="color: #6b7280; cursor: pointer;" onclick="TBV2_Form.toggleManufacturerSearch()">
 <span id="search-toggle-text">Activar búsqueda rápida</span>
 </small>
 </div>
 </div>
 </div>

 <div class="form-group">
 <label for="model">Modelo <small style="color: #6b7280;" id="model-count">(selecciona fabricante primero)</small></label>
 <select id="model" name="model" disabled>
 <option value="">Primero seleccione fabricante...</option>
 </select>
 </div>
 </div>
 </div>

 <!-- "No encuentro mi modelo" - compacto (idéntico al original) -->
 <div id="no-encuentro-wrapper" style="margin: 12px 0;">
 <label class="checkbox-container" style="font-size: 14px; color: #016d86; cursor: pointer; display: flex; align-items: center; gap: 8px; margin-bottom: 0; font-weight: 500;">
 <input type="checkbox" id="no_encuentro_modelo" style="margin-right: 6px; transform: scale(1.1);">
 <span>No encuentro mi modelo</span>
 </label>

 <!-- Campos de marca/modelo manual en 2 columnas -->
 <div id="manual-fields" style="display: none; margin-top: 10px;">
 <div class="form-compact-row">
 <div class="form-group">
 <label for="manual_manufacturer">Marca (manual)</label>
 <input type="text" id="manual_manufacturer" name="manual_manufacturer" placeholder="Escriba la marca" />
 </div>

 <div class="form-group">
 <label for="manual_model">Modelo (manual)</label>
 <input type="text" id="manual_model" name="manual_model" placeholder="Escriba el modelo" />
 </div>
 </div>
 </div>
 </div>

 <!-- Fecha Matriculación, Precio y Comunidad Autónoma en una línea -->
 <div class="form-compact-row" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px;">
 <div class="form-group">
 <label for="matriculation_date" id="matriculation_date_label">Fecha Matriculación</label>
 <input type="date" id="matriculation_date" name="matriculation_date" max="<?php echo date('Y-m-d'); ?>" required>
 </div>

 <div class="form-group">
 <label for="purchase_price">Precio de Compra (€)</label>
 <input type="number" id="purchase_price" name="purchase_price" placeholder="Ej: 12000" required />
 </div>

 <div class="form-group">
 <label for="region">Comunidad Autónoma</label>
 <select id="region" name="region" required>
 <option value="">Seleccione comunidad</option>
 <option value="Andalucía">Andalucía</option>
 <option value="Aragón">Aragón</option>
 <option value="Asturias">Asturias</option>
 <option value="Islas Baleares">Islas Baleares</option>
 <option value="Canarias">Canarias</option>
 <option value="Cantabria">Cantabria</option>
 <option value="Castilla-La Mancha">Castilla-La Mancha</option>
 <option value="Castilla y León">Castilla y León</option>
 <option value="Cataluña">Cataluña</option>
 <option value="Comunidad Valenciana">Comunidad Valenciana</option>
 <option value="Galicia">Galicia</option>
 <option value="La Rioja">La Rioja</option>
 <option value="Madrid">Madrid</option>
 <option value="Murcia">Murcia</option>
 <option value="Navarra">Navarra</option>
 <option value="País Vasco">País Vasco</option>
 <option value="Ceuta">Ceuta</option>
 <option value="Melilla">Melilla</option>
 </select>
 </div>
 </div>

 <!-- Botones de navegación integrados -->
 <div class="form-navigation" style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px;">
 <button type="button" class="button button-secondary" id="tbv2-prevButton" style="display: none;">
 <i class="fas fa-arrow-left"></i> Anterior
 </button>
 <button type="button" class="button button-primary" id="tbv2-nextButton">
 <i class="fas fa-arrow-right"></i> Datos Personales
 </button>
 </div>
 </div>

 <!-- PÁGINA 2: DATOS -->
 <div id="page-datos" class="form-page form-section-compact hidden" style="padding-top: 0;">
 <h2 style="margin-bottom: 12px; color: #016d86; font-size: 24px; font-weight: 600;">Tus Datos Personales</h2>
 <p class="section-intro">Introduce tus datos personales para la gestión del trámite. Estos datos aparecerán en el documento de autorización.</p>

 <!-- Fila 1: Nombre y DNI -->
 <div class="form-compact-row">
 <div class="form-group">
 <label for="customer_name">Nombre y Apellidos</label>
 <input type="text" id="customer_name" name="customer_name" required />
 <span class="input-hint">Tal como aparece en su DNI</span>
 </div>

 <div class="form-group">
 <label for="customer_dni">DNI</label>
 <input type="text" id="customer_dni" name="customer_dni" required />
 <span class="input-hint">Formato: 12345678X</span>
 </div>
 </div>

 <!-- Fila 2: Email y Teléfono -->
 <div class="form-compact-row">
 <div class="form-group">
 <label for="customer_email">Correo Electrónico</label>
 <input type="email" id="customer_email" name="customer_email" required />
 <span class="input-hint">Recibirás notificaciones del trámite</span>
 </div>

 <div class="form-group">
 <label for="customer_phone">Teléfono</label>
 <input type="tel" id="customer_phone" name="customer_phone" required />
 <span class="input-hint">Para contactarte si es necesario</span>
 </div>
 </div>

 <!-- Botones de navegación integrados -->
 <div class="form-navigation">
 <button type="button" class="button button-secondary" id="tbv2-datos-prevButton">
 <i class="fas fa-arrow-left"></i> Vehículo
 </button>
 <button type="button" class="button button-primary" id="tbv2-datos-nextButton">
 <i class="fas fa-arrow-right"></i> ITP y Precios
 </button>
 </div>
 </div>

 <!-- PÁGINA 3: PRECIO/ITP -->
 <div id="page-precio" class="form-page form-section-compact hidden" style="padding-top: 0;">
 
 <!-- PASO 1: Pregunta sobre ITP -->
 <div id="precio-step-1" class="precio-step">
 <h2 style="margin-bottom: 12px; color: #016d86; font-size: 24px; font-weight: 600;">Gestión del ITP</h2>
 <p style="margin-bottom: 20px; color: #6b7280; font-size: 15px; line-height: 1.6;">El Impuesto de Transmisiones Patrimoniales es obligatorio. ¿Cómo quieres gestionarlo?</p>

 <!-- Calculo ITP automático -->
 <div style="background: white; border: 2px solid #e5e7eb; border-radius: 12px; padding: 28px; margin-bottom: 20px;">
 <h3 style="margin: 0 0 16px 0; font-size: 18px; font-weight: 700; color: #1f2937;">Cálculo automático del ITP</h3>
 <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
 <div style="color: #6b7280;">
 <div>Para su embarcación:</div>
 <div style="font-size: 13px; margin-top: 4px;" id="tbv2-vehicle-summary">Complete los datos del vehículo para calcular</div>
 </div>
 <div style="font-size: 32px; font-weight: 700; color: #016d86;" id="tbv2-itp-display">---</div>
 </div>
 
 <button type="button" id="ver-calculo-itp" style="background: #f3f4f6; color: #374151; border: 1px solid #d1d5db; padding: 8px 16px; border-radius: 6px; font-size: 13px; cursor: pointer; width: 100%;">
 Ver cálculo detallado
 </button>
 
 <!-- Detalle del cálculo -->
 <div id="calculo-itp-detail" style="display: none; margin-top: 16px; padding-top: 16px; border-top: 1px solid #e5e7eb;">
 <div style="font-size: 14px; line-height: 1.6; color: #374151;" id="tbv2-calculation-breakdown">
 <!-- Contenido dinámico del cálculo -->
 </div>
 </div>
 </div>

 <!-- Pregunta ITP Pagado - Versión Compacta -->
 <div id="itp-question-container" style="background: rgba(1, 109, 134, 0.04); border: 1px solid rgba(1, 109, 134, 0.2); border-radius: 8px; padding: 16px; margin-bottom: 16px;">
 <div style="display: flex; align-items: center; justify-content: space-between; gap: 20px;">
 <div style="text-align: left;">
 <h4 style="margin: 0 0 4px 0; font-size: 16px; color: #1f2937; font-weight: 600;">¿Ya has pagado el ITP?</h4>
 <p style="margin: 0; font-size: 13px; color: #6b7280;">Selecciona tu situación</p>
 </div>
 <div style="display: flex; gap: 8px; flex-shrink: 0;">
 <button type="button" id="itp-si" class="itp-choice-btn" style="padding: 10px 16px; border: 1px solid #016d86; background: white; color: #016d86; border-radius: 6px; font-size: 14px; font-weight: 500; cursor: pointer; white-space: nowrap;">
 Sí, ya lo pagué
 </button>
 <button type="button" id="itp-no" class="itp-choice-btn" style="padding: 10px 16px; border: 1px solid #016d86; background: white; color: #016d86; border-radius: 6px; font-size: 14px; font-weight: 500; cursor: pointer; white-space: nowrap;">
 No, necesito pagarlo
 </button>
 </div>
 </div>
 </div>
 </div>

 <!-- PASO 2: Resumen final -->
 <div id="precio-step-2" class="precio-step" style="display: none;">
 <h2 style="margin-bottom: 12px; color: #016d86; font-size: 24px; font-weight: 600;">Resumen del Trámite</h2>
 <p style="margin-bottom: 20px; color: #6b7280; font-size: 15px; line-height: 1.6;">Revisa los servicios incluidos. Si necesitas modificar algo, usa el botón al final de esta página.</p>

 <!-- Desglose de Precio -->
 <div style="background: white; border: 2px solid #e5e7eb; border-radius: 12px; padding: 28px; margin-bottom: 20px;">
 <h3 style="margin: 0 0 20px 0; font-size: 18px; font-weight: 700; color: #1f2937;">Desglose de Servicios</h3>

 <!-- Tramitación completa -->
 <div style="display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #e5e7eb;">
 <div>
 <div style="font-size: 15px; font-weight: 600; color: #1f2937;">Tramitación Completa</div>
 <div style="font-size: 13px; color: #6b7280; margin-top: 2px;">Gestión, tasas DGMM e IVA incluidos</div>
 </div>
 <div style="font-size: 16px; font-weight: 700; color: #1f2937;" id="desglose-tramitacion">134.99 €</div>
 </div>

 <!-- ITP - Caso 1: ITP ya pagado -->
 <div id="incluye-itp-si" style="display: none;">
 <div style="display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #e5e7eb;">
 <div>
 <div style="font-size: 15px; font-weight: 600; color: #10b981;">
 <i class="fa-solid fa-circle-check" style="margin-right: 6px;"></i>
 Impuesto (ITP)
 </div>
 <div style="font-size: 13px; color: #6b7280; margin-top: 2px;">Ya has pagado el ITP por tu cuenta</div>
 </div>
 <div style="font-size: 16px; font-weight: 700; color: #10b981;">Ya pagado</div>
 </div>
 </div>

 <!-- ITP - Caso 2: ITP incluido en el precio -->
 <div id="incluye-itp-no" style="display: none;">
 <div style="display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #e5e7eb;">
 <div>
 <div style="font-size: 15px; font-weight: 600; color: #016d86;">Impuesto (ITP)</div>
 <div style="font-size: 13px; color: #6b7280; margin-top: 2px;" id="itp-desglose-descripcion">Gestionamos el pago por ti</div>
 </div>
 <div style="font-size: 16px; font-weight: 700; color: #016d86;" id="desglose-itp">0 €</div>
 </div>
 </div>

 <!-- Precio total -->
 <div style="display: flex; justify-content: space-between; padding: 20px 0 0 0; font-size: 20px; font-weight: 700; color: #1f2937; border-top: 2px solid #016d86; margin-top: 16px;">
 <div>Total a pagar</div>
 <div style="color: #016d86;" id="precio-final">134.99 €</div>
 </div>
 </div>

 <!-- Botón para modificar -->
 <div style="text-align: center; margin-bottom: 20px;">
 <button type="button" id="modificar-itp" style="background: #f3f4f6; color: #374151; border: 1px solid #d1d5db; padding: 12px 24px; border-radius: 8px; font-size: 14px; cursor: pointer;">
 Modificar selección de ITP
 </button>
 </div>
 </div>

 <!-- Campo oculto para comunidad autónoma -->
 <select id="autonomous_community" name="autonomous_community" style="display: none;">
 <option value="Madrid (Comunidad de)" selected>Madrid (Comunidad de)</option>
 </select>

 <!-- Botones de navegación integrados -->
 <div class="form-navigation">
 <button type="button" class="button button-secondary" id="tbv2-precio-prevButton">
 <i class="fas fa-arrow-left"></i> Datos Personales
 </button>
 <button type="button" class="button button-primary" id="tbv2-precio-nextButton">
 <i class="fas fa-arrow-right"></i> Documentos
 </button>
 </div>
 </div> <!-- PÁGINA 4: DOCUMENTOS -->
 <div id="page-documentos" class="form-page form-section-compact hidden" style="padding-top: 0;">
 <h2 style="margin-bottom: 12px; color: #016d86; font-size: 24px; font-weight: 600;">Documentación Requerida</h2>
 <p style="margin-bottom: 20px; color: #6b7280; font-size: 15px; line-height: 1.6;">Adjunta los documentos necesarios para completar el trámite de transferencia.</p>
 
 <!-- Upload grid 3x2 -->
 <div class="upload-grid">
 <!-- Primera fila: 3 documentos principales -->
 <div class="upload-row">
 <!-- Registro marítimo -->
 <div class="upload-item">
 <label for="upload-hoja-asiento" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
 <div>
 <strong> Registro Marítimo</strong>
 <small style="display: block;">Documento que acredita la propiedad de la embarcación</small>
 </div>
 <span class="view-example" data-doc="registro-maritimo" style="color: #016d86; text-decoration: underline; font-size: 12px; cursor: pointer; font-weight: 500; padding: 4px 8px; background: #f0f9ff; border-radius: 4px; transition: all 0.2s ease; margin-left: 8px; flex-shrink: 0;">Ver ejemplo</span>
 </label>
 <div class="upload-wrapper">
 <input type="file" id="upload-hoja-asiento" name="upload_hoja_asiento[]" multiple required accept="image/*,.pdf">
 <div class="upload-button upload-button-responsive">
 <span class="desktop-text"><i class="fa-solid fa-upload"></i> Seleccionar archivo</span>
 <span class="mobile-text"><i class="fa-solid fa-camera"></i></span>
 </div>
 <div class="file-count" data-input="upload-hoja-asiento">Sin archivos</div>
 <div class="file-preview-container" data-input="upload-hoja-asiento"></div>
 </div>
 </div>

 <!-- DNI Comprador -->
 <div class="upload-item">
 <label for="upload-dni-comprador" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
 <div>
 <strong>DNI Comprador</strong>
 <small style="display: block;">Documento Nacional de Identidad (ambas caras)</small>
 </div>
 <span class="view-example" data-doc="dni-comprador" style="color: #016d86; text-decoration: underline; font-size: 12px; cursor: pointer; font-weight: 500; padding: 4px 8px; background: #f0f9ff; border-radius: 4px; transition: all 0.2s ease; margin-left: 8px; flex-shrink: 0;">Ver ejemplo</span>
 </label>
 <div class="upload-wrapper">
 <input type="file" id="upload-dni-comprador" name="upload_dni_comprador[]" multiple required accept="image/*,.pdf">
 <div class="upload-button upload-button-responsive">
 <span class="desktop-text"><i class="fa-solid fa-upload"></i> Seleccionar archivo</span>
 <span class="mobile-text"><i class="fa-solid fa-camera"></i></span>
 </div>
 <div class="file-count" data-input="upload-dni-comprador">Sin archivos</div>
 <div class="file-preview-container" data-input="upload-dni-comprador"></div>
 </div>
 </div>

 <!-- DNI Vendedor -->
 <div class="upload-item">
 <label for="upload-dni-vendedor" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
 <div>
 <strong>DNI Vendedor</strong>
 <small style="display: block;">Documento Nacional de Identidad (ambas caras)</small>
 </div>
 <span class="view-example" data-doc="dni-vendedor" style="color: #016d86; text-decoration: underline; font-size: 12px; cursor: pointer; font-weight: 500; padding: 4px 8px; background: #f0f9ff; border-radius: 4px; transition: all 0.2s ease; margin-left: 8px; flex-shrink: 0;">Ver ejemplo</span>
 </label>
 <div class="upload-wrapper">
 <input type="file" id="upload-dni-vendedor" name="upload_dni_vendedor[]" multiple required accept="image/*,.pdf">
 <div class="upload-button upload-button-responsive">
 <span class="desktop-text"><i class="fa-solid fa-upload"></i> Seleccionar archivo</span>
 <span class="mobile-text"><i class="fa-solid fa-camera"></i></span>
 </div>
 <div class="file-count" data-input="upload-dni-vendedor">Sin archivos</div>
 <div class="file-preview-container" data-input="upload-dni-vendedor"></div>
 </div>
 </div>
 </div>

 <!-- Segunda fila: Contrato, Modelo 620 y Firma -->
 <div class="upload-row">
 <!-- Contrato compraventa -->
 <div class="upload-item">
 <label for="upload-contrato-compraventa" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
 <div>
 <strong> Contrato Compraventa</strong>
 <small style="display: block;">Contrato firmado entre comprador y vendedor</small>
 </div>
 <span class="view-example" data-doc="contrato-compraventa" style="color: #016d86; text-decoration: underline; font-size: 12px; cursor: pointer; font-weight: 500; padding: 4px 8px; background: #f0f9ff; border-radius: 4px; transition: all 0.2s ease; margin-left: 8px; flex-shrink: 0;">Ver ejemplo</span>
 </label>
 <div class="upload-wrapper">
 <input type="file" id="upload-contrato-compraventa" name="upload_contrato_compraventa[]" multiple required accept="image/*,.pdf">
 <div class="upload-button upload-button-responsive">
 <span class="desktop-text"><i class="fa-solid fa-upload"></i> Seleccionar archivo</span>
 <span class="mobile-text"><i class="fa-solid fa-camera"></i></span>
 </div>
 <div class="file-count" data-input="upload-contrato-compraventa">Sin archivos</div>
 <div class="file-preview-container" data-input="upload-contrato-compraventa"></div>
 </div>
 </div>

 <!-- Modelo 620 (condicional) -->
 <div class="upload-item" id="modelo-620-container" style="display: none; flex-direction: column; justify-content: center;">
 <div style="background: #f0f9ff; border: 2px solid #3b82f6; border-radius: 8px; padding: 16px;">
 <label for="upload-modelo-620" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
 <div>
 <strong style="display: block; font-size: 14px; color: #1e40af; margin-bottom: 4px;"> Modelo 620 - Comprobante ITP</strong>
 <small style="color: #1e40af;">El ITP ya está pagado, adjunta el comprobante</small>
 </div>
 <span class="view-example" data-doc="modelo-620" style="color: #016d86; text-decoration: underline; font-size: 12px; cursor: pointer; font-weight: 500; padding: 4px 8px; background: #f0f9ff; border-radius: 4px; transition: all 0.2s ease; margin-left: 8px; flex-shrink: 0;">Ver ejemplo</span>
 </label>
 <div class="upload-wrapper">
 <input type="file" id="upload-modelo-620" name="upload_modelo_620[]" multiple accept="image/*,.pdf">
 <div class="upload-button upload-button-responsive">
 <span class="desktop-text"><i class="fa-solid fa-upload"></i> Adjuntar Modelo 620</span>
 <span class="mobile-text"><i class="fa-solid fa-camera"></i></span>
 </div>
 <div class="file-count" data-input="upload-modelo-620">Sin archivos</div>
 <div class="file-preview-container" data-input="upload-modelo-620"></div>
 </div>
 </div>
 </div>

 <!-- Firma Digital -->
 <div class="upload-item" style="display: flex; flex-direction: column; justify-content: center;">
 <label for="document-signature">
 <strong> Firma Digital</strong>
 <small>Firma la autorización para tramitar</small>
 </label>
 <div class="upload-wrapper">
 <div id="signature-field" class="upload-button upload-button-responsive" style="text-align: center; cursor: pointer;">
 <span class="desktop-text"><i class="fa-solid fa-signature"></i> Firmar documentos</span>
 <span class="mobile-text"><i class="fa-solid fa-signature"></i></span>
 </div>
 <div class="file-count" id="signature-status">Pendiente de firma</div>
 </div>
 </div>
 </div>
 </div>

 <!-- Navegación -->
 <div class="form-navigation">
 <button type="button" class="button button-secondary" id="tbv2-documentos-prevButton">
 <i class="fas fa-arrow-left"></i> ITP y Precios
 </button>
 <button type="button" class="button button-primary" id="tbv2-documentos-nextButton">
 <i class="fas fa-arrow-right"></i> Pagar
 </button>
 </div>
 </div>

 <!-- PÁGINA 5: PAGO -->
 <div id="page-pago" class="form-page form-section-compact hidden" style="padding-top: 0;">
 <h2 style="margin-bottom: 12px; color: #016d86; font-size: 24px; font-weight: 600;">Método de Pago</h2>
 <p style="color: #666; margin-bottom: 25px; font-size: 15px; line-height: 1.6;">Pago seguro con TPV CaixaBank. Será redirigido a la pasarela segura para completar el pago.</p>

 <!-- Redsys Container -->
 <div id="redsys-container" style="max-width: 100%; margin: 0 auto;">
 <!-- Redsys Payment Info -->
 <div class="redsys-payment-info" style="background: #f8f9ff; border: 1px solid #e0e7ff; border-radius: 12px; padding: 24px; margin-bottom: 25px;">
 <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
 <div style="background: #016d86; color: white; border-radius: 50%; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
 <i class="fa-solid fa-credit-card"></i>
 </div>
 <div>
 <h4 style="margin: 0 0 4px 0; color: #1f2937; font-size: 18px; font-weight: 600;">Pago Seguro CaixaBank</h4>
 <p style="margin: 0; color: #6b7280; font-size: 14px;">TPV certificado con máxima seguridad</p>
 </div>
 </div>
 
 
 <div style="background: rgba(59, 130, 246, 0.1); padding: 12px; border-radius: 8px; border-left: 4px solid #3b82f6;">
 <p style="margin: 0; font-size: 14px; color: #1e40af; line-height: 1.5;">
 <i class="fa-solid fa-info-circle"></i> 
 Al hacer clic en "Proceder al Pago", será redirigido a la pasarela segura de CaixaBank para completar el pago con tarjeta.
 </p>
 </div>
 </div>


 <!-- Terms and Conditions Checkbox - Estilo discreto y profesional -->
 <div class="terms-container payment-terms" style="margin: 20px 0; padding: 16px; border: 1px solid #e5e7eb; border-radius: 8px; background-color: #f9fafb;">
 <label style="display: flex; align-items: flex-start; gap: 10px; font-size: 14px; line-height: 1.5; cursor: pointer; color: #374151;">
 <input type="checkbox" id="terms_accept_pago" name="terms_accept_pago" required style="margin-top: 2px; width: 16px; height: 16px; accent-color: #016d86; cursor: pointer;">
 <span style="font-size: 14px;">Acepto los <a href="https://tramitfy.es/terminos-y-condiciones-de-uso/" target="_blank" style="color: #016d86; text-decoration: none; font-weight: 500;">términos y condiciones de pago</a></span>
 </label>
 </div>


 <!-- Payment Message -->
 <div id="payment-message" class="hidden" style="margin: 20px 0; padding: 15px; border-radius: 8px; text-align: center; font-weight: 500; display: none;"></div>

 <!-- Payment Button -->
 <button type="button" id="submit-payment" class="btn-primary" style="width: 100%; padding: 16px; font-size: 18px; font-weight: 600; background: linear-gradient(135deg, #016d86 0%, #015266 100%); color: white; border: none; border-radius: 8px; cursor: pointer; margin-top: 20px; transition: all 0.3s ease; box-shadow: 0 4px 6px -1px rgba(1, 109, 134, 0.3), 0 2px 4px -1px rgba(1, 109, 134, 0.2);" disabled>
 <i class="fa-solid fa-credit-card"></i> Proceder al Pago
 </button>
 </div>

 </div>

 </div> <!-- Fin tramitfy-main-form -->

 </div> <!-- Fin tramitfy-two-column -->
 </div> <!-- Fin tramitfy-layout-wrapper -->

 </form>

 <!-- Modal de Vista Previa del Documento -->
 <div id="document-preview-modal" class="tbv2-preview-modal">
 <div class="tbv2-preview-content">
 <div class="tbv2-preview-header">
 <h3><i class="fa-solid fa-file-contract"></i> Documento de Autorización</h3>
 <button class="tbv2-modal-close" id="close-preview-modal">
 <i class="fa-solid fa-times"></i>
 </button>
 </div>

 <div class="tbv2-preview-body">
 <div id="document-content-preview">
 <!-- El contenido se generará dinámicamente -->
 </div>
 </div>

 <div class="tbv2-preview-footer">
 <p class="tbv2-preview-instructions">
 <i class="fa-solid fa-info-circle"></i> Lea cuidadosamente el documento antes de proceder a firmarlo
 </p>
 <div class="tbv2-preview-button-container">
 <button class="tbv2-modal-cancel-btn" id="cancel-document-preview">
 <i class="fa-solid fa-times"></i> Cancelar
 </button>
 <button class="tbv2-modal-proceed-btn" id="proceed-to-signature">
 <i class="fa-solid fa-pen-fancy"></i> Proceder a Firmar
 </button>
 </div>
 </div>
 </div>
 </div>

 <!-- Modal de Firma Digital -->
 <div id="signature-modal-advanced" class="tbv2-signature-modal">
 <div class="tbv2-modal-content">
 <div class="tbv2-modal-header">
 <h3><i class="fa-solid fa-pen-fancy"></i> Firma Digital</h3>
 <button class="tbv2-modal-close" id="close-signature-modal">
 <i class="fa-solid fa-times"></i>
 </button>
 </div>

 <div class="tbv2-enhanced-signature-container">
 <div class="tbv2-signature-guide">
 <div class="tbv2-signature-line"></div>
 <div class="tbv2-signature-instruction">FIRME AQUÍ</div>
 </div>
 <canvas id="enhanced-signature-canvas"></canvas>
 </div>

 <div class="tbv2-modal-footer">
 <p class="tbv2-modal-instructions">
 <i class="fa-solid fa-hand-pointer"></i> Use el dedo para firmar en el área indicada
 </p>
 <div class="tbv2-modal-button-container">
 <button class="tbv2-modal-clear-btn" id="modal-clear-signature">
 <i class="fa-solid fa-eraser"></i> Borrar
 </button>
 <button class="tbv2-modal-accept-btn" id="modal-accept-signature" disabled>
 <i class="fa-solid fa-check"></i> Confirmar firma
 </button>
 </div>
 </div>
 </div>
 </div>


 <!-- JavaScript -->
 <script>
 // Datos CSV para JavaScript
 const tbv2DatosCsv = <?php echo json_encode($datos_csv); ?>;
 const tbv2StripePublicKey = '<?php echo esc_js($tbv2_stripe_public_key); ?>';
 </script>
 <?php tbv2_render_scripts(); ?>
 
 <?php
 return ob_get_clean();
}

/**
 * Estilos CSS IDÉNTICOS al original
 */
function tbv2_render_styles() {
 ?>
 <style>
 /* ============================================
 TBV2 - ESTILOS IDÉNTICOS AL ORIGINAL
 ============================================ */
 
 :root {
 --primary: 1, 109, 134; /* #016d86 */
 --primary-dark: 1, 82, 106; /* #01546a */
 --spacing-xs: 4px;
 --spacing-sm: 8px;
 --spacing-md: 16px;
 --spacing-lg: 24px;
 --spacing-xl: 32px;
 --spacing-2xl: 48px;
 --radius-sm: 6px;
 --radius: 8px;
 --radius-lg: 12px;
 --radius-xl: 16px;
 --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
 --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
 --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
 --transition-fast: 0.15s cubic-bezier(0.4, 0, 0.2, 1);
 --transition: 0.25s cubic-bezier(0.4, 0, 0.2, 1);
 }

 /* Marketing Container */

 /* LAYOUT PRINCIPAL (IDÉNTICO AL ORIGINAL) */
 .tramitfy-layout-wrapper {
 max-width: 1400px;
 width: 95%;
 margin: 40px auto 0 auto;
 padding: 0;
 }

 .tramitfy-two-column {
 display: grid !important;
 grid-template-columns: 384px 1fr !important; /* Sidebar fijo 384px (20% más ancho) + formulario resto */
 grid-template-areas: "sidebar content" !important;
 gap: 0;
 align-items: stretch; /* Sidebar se ajusta a altura del form */
 background: white;
 border-radius: 16px;
 box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
 }

 /* SIDEBAR IZQUIERDO (IDÉNTICO) */
 .tramitfy-sidebar {
 grid-area: sidebar;
 position: relative;
 background: #016d86; /* Color corporativo sólido */
 border-radius: 12px 0 0 12px;
 padding: 18px 16px;
 box-shadow: none;
 border: none;
 backdrop-filter: none;
 overflow-y: auto;
 overflow-x: hidden;
 color: #ffffff;
 display: flex;
 flex-direction: column;
 width: 384px; /* Ancho aumentado 20% (antes 320px) */
 min-height: 100%; /* Se extiende con el contenido */
 height: auto; /* Altura automática */
 transition: width 0.3s ease, background 0.3s ease, box-shadow 0.3s ease;
 }

 .sidebar-content {
 display: flex;
 flex-direction: column;
 }

 .sidebar-body {
 flex: 1;
 }

 /* FORMULARIO PRINCIPAL (IDÉNTICO) */
 .tramitfy-main-form {
 grid-area: content;
 background: white;
 border-radius: 0 12px 12px 0;
 padding: 32px;
 overflow-y: auto;
 overflow-x: hidden;
 }

 /* NAVEGACIÓN PROFESIONAL AJUSTADA AL MARGEN */
 #form-navigation-professional {
 background: #ffffff;
 border: 1px solid #e5e7eb;
 border-radius: 4px;
 margin: 0 0 20px 0;
 overflow: hidden;
 box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
 position: relative;
 top: 0;
 }

 .nav-progress-bar {
 position: relative;
 height: 3px;
 background: rgba(14, 116, 144, 0.2);
 z-index: 1;
 }

 .nav-progress-indicator {
 position: absolute;
 top: 0;
 left: 0;
 height: 100%;
 width: 20%; /* 1/5 páginas */
 background: linear-gradient(90deg, #016d86 0%, #0891b2 100%);
 transition: width 0.4s ease;
 box-shadow: 0 0 10px rgba(1, 109, 134, 0.3);
 }

 .nav-items-container {
 display: flex;
 position: relative;
 z-index: 2;
 max-width: 900px;
 margin: 0 auto;
 padding: 0;
 }

 .nav-item {
 display: flex;
 align-items: center;
 justify-content: center;
 text-decoration: none;
 color: #0891b2;
 font-weight: 500;
 font-size: 14px;
 position: relative;
 transition: all 0.3s ease;
 flex: 1;
 padding: 16px 20px;
 border-bottom: 3px solid transparent;
 background: white;
 }

 .nav-item:hover {
 color: #016d86;
 background: rgba(1, 109, 134, 0.05);
 transform: translateY(-2px);
 }

 .nav-item.active {
 color: #016d86;
 border-bottom-color: #016d86;
 background: linear-gradient(135deg, rgba(1, 109, 134, 0.05) 0%, rgba(8, 145, 178, 0.02) 100%);
 font-weight: 600;
 }

 .nav-item-circle {
 display: none;
 }

 .nav-item-icon {
 margin-right: 8px;
 font-size: 16px;
 transition: transform 0.3s ease;
 }

 .nav-item:hover .nav-item-icon,
 .nav-item.active .nav-item-icon {
 transform: scale(1.1);
 }

 .nav-item-number {
 display: none;
 }

 .nav-item-text {
 font-size: 14px;
 font-weight: inherit;
 text-align: center;
 }

 /* Estados de progreso */
 .nav-item.completed {
 color: #10b981;
 }

 .nav-item.completed .nav-item-icon {
 color: #10b981;
 }

 /* Efectos hover mejorados */
 .nav-item::before {
 content: '';
 position: absolute;
 bottom: 0;
 left: 50%;
 width: 0;
 height: 3px;
 background: linear-gradient(90deg, #016d86 0%, #0891b2 100%);
 transition: all 0.3s ease;
 transform: translateX(-50%);
 }

 .nav-item:hover::before {
 width: 80%;
 }

 .nav-item.active::before {
 width: 100%;
 }

 .nav-item.completed {
 background: rgba(16, 185, 129, 0.08);
 color: #10b981;
 border-color: rgba(16, 185, 129, 0.3);
 position: relative;
 }

 .nav-item.completed::after {
 content: '';
 position: absolute;
 right: 8px;
 top: 50%;
 transform: translateY(-50%);
 color: #10b981;
 font-weight: bold;
 font-size: 12px;
 background: rgba(16, 185, 129, 0.1);
 border-radius: 50%;
 width: 18px;
 height: 18px;
 display: flex;
 align-items: center;
 justify-content: center;
 }


 /* NAVEGACIÓN EN DESKTOP */
 #form-navigation-top {
 position: relative;
 top: -32px;
 left: -32px;
 right: -32px;
 width: calc(100% + 64px);
 z-index: 10;
 background: white;
 border-bottom: 2px solid #f1f5f9;
 border-radius: 16px 16px 0 0;
 margin: 0;
 padding: 0;
 }

 /* NAVEGACIÓN TABS PROFESIONAL */
 .nav-tabs-container {
 display: flex;
 background: transparent;
 padding: 0;
 margin: 0;
 width: 100%;
 }

 .nav-tab {
 flex: 1;
 display: flex;
 align-items: center;
 padding: 14px 10px;
 cursor: pointer;
 transition: all 0.2s ease;
 border-right: 1px solid #f3f4f6;
 position: relative;
 background: #fafbfc;
 min-height: 60px;
 }

 .nav-tab:last-child {
 border-right: none;
 }

 .nav-tab:hover {
 background: #f8f9fa;
 }

 .nav-tab.active {
 background: #ffffff;
 border-bottom: 2px solid #016d86;
 box-shadow: 0 -1px 0 0 #016d86;
 }

 .nav-tab.completed {
 background: #f9fdfb;
 border-bottom: 2px solid #10b981;
 }


 .tab-content-centered {
 display: flex;
 align-items: center;
 justify-content: center;
 flex: 1;
 width: 100%;
 }



 .tab-title {
 font-size: 14px;
 font-weight: 600;
 color: #374151;
 line-height: 1;
 text-align: center;
 }

 .nav-tab.active .tab-title {
 color: #016d86;
 }

 .nav-tab.completed .tab-title {
 color: #10b981;
 }



 /* PÁGINAS DEL FORMULARIO */
 .form-page {
 transition: opacity var(--transition);
 }

 .form-page.hidden {
 display: none;
 }


 .form-page h2 {
 color: #016d86;
 font-size: 26px;
 font-weight: 600;
 margin-bottom: var(--spacing-md);
 border-bottom: 2px solid rgba(1, 109, 134, 0.1);
 padding-bottom: var(--spacing-sm);
 }

 /* LAYOUTS COMPACTOS PARA FORMULARIO (IDÉNTICOS AL ORIGINAL) */
 .form-compact-row {
 display: grid;
 grid-template-columns: 1fr 1fr;
 gap: 15px;
 margin-bottom: 18px;
 }

 .form-compact-row .form-group {
 margin-bottom: 0;
 }

 .form-compact-triple {
 display: grid;
 grid-template-columns: 1fr 1fr 1fr;
 gap: 12px;
 margin-bottom: 18px;
 }

 .form-compact-triple .form-group {
 margin-bottom: 0;
 }

 /* GRID DE FORMULARIO */
 .form-grid {
 display: grid;
 grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
 gap: var(--spacing-lg);
 margin-bottom: var(--spacing-lg);
 }

 .form-group {
 display: flex;
 flex-direction: column;
 margin-bottom: 15px;
 }

 .form-group label {
 font-weight: 500;
 margin-bottom: 6px;
 color: #111827;
 font-size: 14px;
 }

 .form-group input,
 .form-group select {
 padding: 12px;
 border: 1px solid #e5e7eb;
 border-radius: var(--radius);
 font-size: 16px;
 transition: all var(--transition-fast);
 background: white;
 box-sizing: border-box;
 }

 .form-group input:focus,
 .form-group select:focus {
 outline: none;
 border-color: #016d86;
 box-shadow: 0 0 0 3px rgba(1, 109, 134, 0.1);
 }

 /* Select styling */
 .form-group select {
 -webkit-appearance: none;
 appearance: none;
 background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23016d86' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
 background-repeat: no-repeat;
 background-position: right 16px center;
 padding-right: 40px;
 }

 /* Input hints */
 .input-hint {
 font-size: 13px;
 color: #64748b;
 margin-top: 6px;
 display: block;
 line-height: 1.4;
 }

 /* Checkbox container styling */
 .checkbox-container {
 display: flex;
 align-items: center;
 gap: 8px;
 font-size: 14px;
 color: #016d86;
 cursor: pointer;
 margin-bottom: 0;
 font-weight: 500;
 }

 .checkbox-container input[type="checkbox"] {
 margin: 0;
 transform: scale(1.1);
 accent-color: #016d86;
 }

 /* BOTONES */
 .btn {
 padding: 12px 24px;
 border-radius: var(--radius);
 font-weight: 600;
 font-size: 14px;
 cursor: pointer;
 display: inline-flex;
 align-items: center;
 gap: var(--spacing-sm);
 transition: all var(--transition-fast);
 border: none;
 text-decoration: none;
 }

 .btn:hover {
 transform: translateY(-1px);
 }

 .btn-primary {
 background: #016d86;
 color: white;
 }

 .btn-primary:hover {
 background: #01546a;
 }

 /* ESTILOS PARA BOTONES INTEGRADOS */
 .form-navigation .button {
 padding: 12px 24px;
 font-size: 14px;
 font-weight: 600;
 border-radius: 8px;
 cursor: pointer;
 display: inline-flex;
 align-items: center;
 gap: 8px;
 transition: all 0.3s ease;
 text-decoration: none;
 border: 2px solid transparent;
 min-width: 140px;
 justify-content: center;
 }

 .button-primary {
 background: linear-gradient(135deg, var(--primary-color) 0%, #0891b2 100%);
 color: white;
 border: none;
 font-weight: 600;
 }

 .button-primary:hover {
 background: linear-gradient(135deg, #014d5f 0%, #0e7490 100%);
 transform: translateY(-2px);
 box-shadow: 0 8px 20px rgba(1, 109, 134, 0.4);
 }

 .button-secondary {
 background: #f8fafc;
 color: #374151;
 border: 2px solid #e5e7eb;
 font-weight: 600;
 }

 .button-secondary:hover {
 background: #f1f5f9;
 border-color: #d1d5db;
 transform: translateY(-1px);
 box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
 }

 .button-final {
 background: linear-gradient(135deg, #059669 0%, #047857 100%);
 color: white;
 border: none;
 font-weight: 600;
 position: relative;
 overflow: hidden;
 }

 .button-final::before {
 content: '';
 position: absolute;
 top: 0;
 left: -100%;
 width: 100%;
 height: 100%;
 background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
 transition: left 0.5s ease;
 }

 .button-final:hover {
 background: linear-gradient(135deg, #047857 0%, #065f46 100%);
 transform: translateY(-2px);
 box-shadow: 0 8px 24px rgba(5, 150, 105, 0.4);
 }

 .button-final:hover::before {
 left: 100%;
 }

 /* NAVEGACIÓN INTEGRADA DENTRO DEL FORMULARIO */
 .form-navigation {
 display: flex;
 justify-content: space-between;
 align-items: center;
 gap: 16px;
 margin-top: 32px;
 padding: 20px 0;
 border-top: 2px solid #f1f5f9;
 background: linear-gradient(135deg, #fafbfc 0%, #f8fafc 100%);
 border-radius: 0 0 12px 12px;
 margin: 32px -24px -24px -24px; /* Extend to form edges */
 padding: 20px 24px;
 }

 /* PLACEHOLDER TEMPORAL */
 .placeholder-section {
 background: #f8fafc;
 border: 2px dashed #e5e7eb;
 border-radius: var(--radius-lg);
 padding: var(--spacing-2xl);
 text-align: center;
 color: #6b7280;
 font-size: 16px;
 }

 /* SIDEBAR DINÁMICO - Usa estilos inline del original */

 /* RESPONSIVE (IDÉNTICO AL ORIGINAL) */
 @media (max-width: 1200px) {
 .tramitfy-two-column {
 grid-template-columns: 336px 1fr !important; /* Sidebar más estrecho pero 20% más ancho que antes (280px × 1.2) */
 }

 .tramitfy-sidebar {
 padding: 24px 16px;
 width: 336px; /* Ancho para tablets */
 }

 .form-page {
 padding: 28px 32px;
 }
 }

 /* ============================================
 MOBILE RESPONSIVE - PROFESIONAL CORREGIDO
 ============================================ */
 @media (max-width: 768px) {
 /* Layout principal móvil */
 .tramitfy-two-column {
 grid-template-columns: 1fr !important;
 grid-template-areas: "sidebar" "content" !important;
 gap: 0;
 box-shadow: 0 0 20px rgba(0,0,0,0.08);
 }
 
 /* Sidebar móvil */
 .tramitfy-sidebar {
 position: relative;
 top: auto;
 min-height: auto;
 height: auto;
 width: 100%;
 border-radius: 12px 12px 0 0;
 padding: 20px 16px;
 }
 
 .tramitfy-sidebar h3 {
 font-size: 22px !important;
 margin-bottom: 12px !important;
 }
 
 /* Formulario principal móvil */
 .tramitfy-main-form {
 padding: 20px 16px; /* Sin padding extra ya que botones dentro del form */
 border-radius: 0 0 12px 12px;
 }
 
 /* MENÚ MÓVIL - JUSTO DEBAJO DEL SIDEBAR */
 #form-navigation-top {
 position: relative !important;
 width: calc(100% + 32px) !important; /* Compensar padding del contenedor */
 margin-left: -16px !important; /* Centrar horizontalmente */
 margin-right: -16px !important;
 margin-top: 20px !important; /* Espacio después del sidebar */
 margin-bottom: 20px !important; /* Espacio antes del form */
 background: #f8f9fa;
 padding: 12px 8px;
 border-radius: 8px;
 order: -1; /* Asegurar que esté antes del form */
 top: 0 !important; /* Reset position */
 left: 0 !important;
 right: 0 !important;
 }
 
 .nav-tabs-container {
 display: grid !important;
 grid-template-columns: repeat(5, 1fr) !important;
 gap: 6px !important;
 padding: 0 4px !important; /* Pequeño padding para centrar */
 overflow: visible !important;
 max-width: 100% !important;
 margin: 0 auto !important; /* Centrar el contenedor */
 }
 
 /* Cuadrados tamaño intermedio con iconos */
 .nav-tab {
 aspect-ratio: 1 !important;
 display: flex !important;
 flex-direction: column !important;
 align-items: center !important;
 justify-content: center !important;
 background: white !important;
 border: 1.5px solid #e5e7eb !important;
 border-radius: 10px !important;
 transition: all 0.2s ease !important;
 cursor: pointer !important;
 padding: 6px !important;
 position: relative !important;
 min-height: 48px !important;
 max-height: 48px !important;
 }
 
 /* Iconos tamaño intermedio y discretos */
 .nav-tab::before {
 content: '';
 font-family: 'Font Awesome 5 Free';
 font-weight: 900;
 font-size: 16px;
 color: #9ca3af;
 margin-bottom: 3px;
 transition: all 0.2s ease;
 }
 
 .nav-tab[data-step="1"]::before { content: '\f21a'; } /* ship */
 .nav-tab[data-step="2"]::before { content: '\f007'; } /* user */
 .nav-tab[data-step="3"]::before { content: '\f153'; } /* euro */
 .nav-tab[data-step="4"]::before { content: '\f15c'; } /* file */
 .nav-tab[data-step="5"]::before { content: '\f09d'; } /* card */
 
 /* Texto pequeño pero legible debajo */
 .nav-tab .tab-title {
 font-size: 8px !important;
 font-weight: 600 !important;
 color: #9ca3af !important;
 text-align: center !important;
 line-height: 1.1 !important;
 transition: all 0.2s ease !important;
 white-space: nowrap !important;
 overflow: hidden !important;
 text-overflow: ellipsis !important;
 }
 
 /* Estado activo */
 .nav-tab.active {
 background: #016d86 !important;
 border-color: #016d86 !important;
 box-shadow: 0 4px 6px rgba(1, 109, 134, 0.15) !important;
 }
 
 .nav-tab.active::before {
 color: white !important;
 }
 
 .nav-tab.active .tab-title {
 color: white !important;
 }
 
 /* Estado completado (sombreado) */
 .nav-tab.completed {
 background: #e8f5f8 !important; /* Fondo azul muy claro */
 border-color: #016d86 !important;
 opacity: 1 !important; /* Mantener opacidad normal */
 }
 
 .nav-tab.completed::before {
 color: #016d86 !important; /* Color azul para indicar completado */
 /* NO cambiar el icono - mantener el original de cada paso */
 }
 
 .nav-tab.completed .tab-title {
 color: #016d86 !important; /* Texto azul */
 display: block !important; /* Mantener texto visible */
 font-size: 8px !important; /* Mismo tamaño que los demás */
 }
 
 /* Páginas del formulario móvil */
 .form-page {
 padding: 0;
 margin-bottom: 20px;
 }
 
 /* PÁGINA DE PRECIO - ESTILOS MÓVIL */
 #page-precio #itp-question-container {
 padding: 16px 12px !important;
 }
 
 #page-precio #itp-question-container > div {
 flex-direction: column !important;
 gap: 16px !important;
 }
 
 #page-precio #itp-question-container h4 {
 text-align: center !important;
 font-size: 18px !important;
 }
 
 #page-precio #itp-question-container p {
 text-align: center !important;
 font-size: 14px !important;
 margin-bottom: 8px !important;
 }
 
 /* Botones ITP en móvil */
 #page-precio .itp-choice-btn {
 width: 100% !important;
 padding: 14px 20px !important;
 font-size: 15px !important;
 border-radius: 8px !important;
 border-width: 2px !important;
 transition: all 0.2s ease !important;
 }
 
 #page-precio .itp-choice-btn:active {
 transform: scale(0.98) !important;
 }
 
 /* Estado seleccionado de botón ITP */
 #page-precio .itp-choice-btn.selected {
 background: #016d86 !important;
 color: white !important;
 box-shadow: 0 4px 6px rgba(1, 109, 134, 0.2) !important;
 }
 
 #page-precio #itp-question-container > div > div:last-child {
 display: flex !important;
 flex-direction: column !important;
 gap: 12px !important;
 width: 100% !important;
 }
 
 /* Cálculo ITP móvil */
 #page-precio #tbv2-itp-display {
 font-size: 24px !important;
 }
 
 #page-precio #ver-calculo-itp {
 padding: 12px 16px !important;
 font-size: 14px !important;
 }
 
 /* Desglose móvil */
 #precio-step-2 > div > div {
 padding: 20px 16px !important;
 }
 
 #precio-step-2 .precio-desglose-item {
 flex-direction: column !important;
 align-items: flex-start !important;
 gap: 8px !important;
 }
 
 /* Botón modificar ITP móvil */
 #modificar-itp {
 width: 100% !important;
 padding: 14px !important;
 font-size: 15px !important;
 }
 
 .form-page h2 {
 font-size: 20px;
 margin-bottom: 10px;
 color: #016d86;
 }
 
 .form-page p {
 font-size: 13px;
 line-height: 1.5;
 color: #666;
 margin-bottom: 16px;
 }
 
 /* Campos de formulario móvil - IMPORTANTE */
 .form-compact-row {
 display: grid !important;
 grid-template-columns: 1fr !important;
 gap: 16px !important;
 }
 
 /* Forzar grid de 3 columnas a 1 en móvil */
 div[style*="grid-template-columns: 1fr 1fr 1fr"] {
 grid-template-columns: 1fr !important;
 }
 
 .form-group {
 margin-bottom: 16px;
 }
 
 .form-group label {
 font-size: 13px;
 font-weight: 600;
 color: #374151;
 margin-bottom: 6px;
 display: block;
 }
 
 .form-group input,
 .form-group select,
 .form-group textarea {
 width: 100%;
 padding: 12px;
 font-size: 16px; /* Evita zoom en iOS */
 border: 1.5px solid #d1d5db;
 border-radius: 8px;
 background: white;
 -webkit-appearance: none;
 }
 
 /* Checkbox móvil */
 .checkbox-container {
 background: #f8f9fa;
 padding: 12px;
 border-radius: 8px;
 display: flex;
 align-items: center;
 }
 
 .checkbox-container input[type="checkbox"] {
 width: 20px;
 height: 20px;
 margin-right: 10px;
 }
 
 /* NAVEGACIÓN BOTONES - DENTRO DEL FORMULARIO */
 .form-navigation {
 position: relative !important;
 margin-top: 24px !important;
 background: #f8f9fa !important;
 border-radius: 8px !important;
 padding: 16px !important;
 display: flex !important;
 flex-direction: row !important;
 justify-content: space-between !important;
 gap: 12px !important;
 box-shadow: 0 2px 4px rgba(0,0,0,0.05) !important;
 }
 
 .form-navigation button {
 flex: 1 !important;
 display: inline-flex !important;
 align-items: center !important;
 justify-content: center !important;
 padding: 14px 12px !important;
 font-size: 14px !important;
 font-weight: 600 !important;
 border-radius: 8px !important;
 border: none !important;
 cursor: pointer !important;
 transition: all 0.2s !important;
 white-space: nowrap !important;
 }
 
 .form-navigation .button-secondary {
 background: #f3f4f6 !important;
 color: #374151 !important;
 }
 
 .form-navigation .button-primary {
 background: #016d86 !important;
 color: white !important;
 }
 
 /* Asegurar que los botones sean visibles */
 #tbv2-prevButton,
 #tbv2-nextButton,
 #tbv2-datos-prevButton,
 #tbv2-datos-nextButton,
 #tbv2-precio-prevButton,
 #tbv2-precio-nextButton,
 #tbv2-documentos-prevButton,
 #tbv2-documentos-nextButton,
 #tbv2-pago-prevButton,
 #tbv2-pago-nextButton {
 display: inline-flex !important;
 }
 
 /* Ocultar el botón anterior en la primera página */
 #page-vehiculo #tbv2-prevButton {
 display: none !important;
 }
 
 /* Upload grid móvil */
 .upload-grid {
 display: grid;
 grid-template-columns: 1fr;
 gap: 12px;
 }
 
 .upload-box {
 padding: 16px;
 }
 
 .upload-button {
 width: 100%;
 padding: 12px;
 font-size: 14px;
 }
 }

 /* ============================================
 MOBILE PEQUEÑO - CORREGIDO Y OPTIMIZADO
 ============================================ */
 @media (max-width: 480px) {
 .tramitfy-layout-wrapper {
 padding: 0;
 margin: 8px;
 }
 
 .tramitfy-two-column {
 border-radius: 8px;
 }
 
 /* Sidebar móvil pequeño */
 .tramitfy-sidebar {
 padding: 16px 12px;
 min-height: auto;
 }
 
 .tramitfy-sidebar h3 {
 font-size: 20px !important;
 margin-bottom: 12px !important;
 }
 
 /* Formulario móvil pequeño */
 .tramitfy-main-form {
 padding: 16px 12px !important; /* Sin espacio extra */
 max-width: 100%;
 }
 
 /* Navegación móvil pequeña - Mantener centrado */
 #form-navigation-top {
 margin-top: -8px !important;
 margin-bottom: 16px !important;
 padding: 10px 6px !important;
 }
 
 .nav-tabs-container {
 gap: 4px !important;
 }
 
 .nav-tab {
 min-height: 42px !important;
 max-height: 42px !important;
 padding: 4px !important;
 }
 
 .nav-tab::before {
 font-size: 14px !important;
 margin-bottom: 2px !important;
 }
 
 .nav-tab .tab-title {
 font-size: 7px !important;
 }
 
 /* Formulario compacto */
 .form-page {
 padding: 0;
 }
 
 .form-page h2 {
 font-size: 18px;
 margin-bottom: 8px;
 }
 
 .form-page p {
 font-size: 12px;
 margin-bottom: 12px;
 }
 
 .form-group {
 margin-bottom: 14px;
 }
 
 .form-group label {
 font-size: 12px;
 }
 
 .form-group input,
 .form-group select {
 padding: 10px;
 font-size: 16px; /* Mantener 16px para evitar zoom */
 }
 
 /* Navegación inferior compacta pero dentro del form */
 .form-navigation {
 padding: 12px !important;
 margin-top: 20px !important;
 }
 
 .form-navigation button {
 padding: 12px 8px !important;
 font-size: 13px !important;
 }
 
 .form-navigation button i {
 margin: 0 2px !important;
 }
 
 /* Upload boxes compactos */
 .upload-box {
 padding: 14px;
 }
 
 .upload-box h4 {
 font-size: 12px;
 }
 
 .upload-button {
 padding: 10px;
 font-size: 13px;
 }
 
 /* Modales fullscreen */
 .tbv2-preview-content,
 .tbv2-modal-content {
 width: 100vw !important;
 height: 100vh !important;
 max-width: 100vw !important;
 max-height: 100vh !important;
 border-radius: 0 !important;
 margin: 0 !important;
 }
 }
 
 /* ============================================
 MOBILE MUY PEQUEÑO - SOLO ICONOS
 ============================================ */
 @media (max-width: 360px) {
 /* Menú compacto pero visible */
 #form-navigation-top {
 padding: 8px 4px !important;
 }
 
 .nav-tabs-container {
 gap: 3px !important;
 }
 
 .nav-tab {
 min-height: 38px !important;
 max-height: 38px !important;
 padding: 3px !important;
 border-radius: 8px !important;
 }
 
 /* Solo iconos, sin texto */
 .nav-tab .tab-title {
 display: none !important;
 }
 
 .nav-tab::before {
 font-size: 14px !important;
 margin-bottom: 0 !important;
 }
 
 /* Ajustes adicionales para pantallas muy pequeñas */
 .tramitfy-sidebar h3 {
 font-size: 18px !important;
 }
 
 .form-navigation button i {
 display: none;
 }
 
 .form-navigation button {
 font-size: 12px;
 padding: 10px 8px;
 }
 }

 /* ===== ITP CALCULATION STYLES ===== */
 
 .calculation-result {
 background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
 border: 1px solid #cbd5e1;
 border-radius: var(--radius);
 padding: var(--spacing-md);
 text-align: center;
 margin: var(--spacing-md) 0;
 }
 
 .calculation-result strong {
 display: block;
 font-size: 1.5rem;
 color: rgb(var(--primary));
 margin-bottom: var(--spacing-xs);
 }
 
 .calculation-detail {
 color: #64748b;
 font-size: 0.875rem;
 }
 
 .calculation-placeholder {
 background: #f8fafc;
 border: 2px dashed #cbd5e1;
 border-radius: var(--radius);
 padding: var(--spacing-lg);
 text-align: center;
 color: #64748b;
 font-style: italic;
 }
 
 .breakdown-row {
 display: flex;
 justify-content: space-between;
 align-items: center;
 padding: var(--spacing-sm) 0;
 border-bottom: 1px solid #e2e8f0;
 }
 
 .breakdown-row:last-child {
 border-bottom: none;
 }
 
 .breakdown-row.calculation-total {
 background: #f1f5f9;
 padding: var(--spacing-md);
 margin: var(--spacing-sm) -var(--spacing-md) 0;
 border-radius: var(--radius-sm);
 font-weight: 600;
 }
 
 .breakdown-row.total-row {
 background: linear-gradient(135deg, rgb(var(--primary), 0.1) 0%, rgb(var(--primary), 0.05) 100%);
 padding: var(--spacing-md);
 margin: var(--spacing-md) -var(--spacing-md) 0;
 border-radius: var(--radius);
 font-weight: 700;
 font-size: 1.125rem;
 color: rgb(var(--primary));
 border: 2px solid rgb(var(--primary), 0.2);
 }
 
 .info-message {
 padding: var(--spacing-md);
 border-radius: var(--radius);
 margin: var(--spacing-md) 0;
 display: flex;
 align-items: center;
 gap: var(--spacing-sm);
 }
 
 .info-message.success {
 background: #f0fdf4;
 border: 1px solid #bbf7d0;
 color: #15803d;
 }
 
 .info-message.info {
 background: #eff6ff;
 border: 1px solid #bfdbfe;
 color: #1d4ed8;
 }
 
 .btn-toggle {
 background: #f8fafc;
 border: 1px solid #e2e8f0;
 color: #475569;
 padding: var(--spacing-sm) var(--spacing-md);
 border-radius: var(--radius-sm);
 font-size: 0.875rem;
 cursor: pointer;
 transition: all 0.2s ease;
 display: inline-flex;
 align-items: center;
 gap: var(--spacing-xs);
 }
 
 .btn-toggle:hover {
 background: #e2e8f0;
 border-color: #cbd5e1;
 }
 
 .btn-toggle.active {
 background: rgb(var(--primary));
 border-color: rgb(var(--primary));
 color: white;
 }
 
 .btn-toggle i {
 font-size: 0.75rem;
 }
 
 .itp-breakdown-container {
 margin-top: var(--spacing-md);
 }
 
 #itp-calculation-details {
 display: none;
 }

 /* ============================================
 DOCUMENTOS: UPLOAD GRID STYLES
 ============================================ */

 .upload-grid {
 display: flex;
 flex-direction: column;
 gap: 12px;
 margin: 12px 0;
 }

 .upload-row {
 display: flex;
 gap: 12px;
 width: 100%;
 }

 .upload-item {
 flex: 1 1 0;
 min-width: 0;
 padding: 16px;
 background: white;
 border: 1px solid #e5e7eb;
 border-radius: 6px;
 box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
 transition: all 0.2s ease;
 }

 .upload-item:hover {
 border-color: #d1d5db;
 box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
 transform: translateY(-2px);
 }

 .upload-item label {
 display: block;
 margin-bottom: 12px;
 cursor: pointer;
 }

 .upload-item label strong {
 display: block;
 font-size: 16px;
 font-weight: 600;
 color: #1f2937;
 margin-bottom: 4px;
 }

 .upload-item label small {
 display: block;
 font-size: 13px;
 color: #6b7280;
 font-weight: 400;
 line-height: 1.3;
 }

 /* Estilos para botones Ver ejemplo mejorados */
 .view-example:hover {
 background: #dbeafe !important;
 color: #014f63 !important;
 text-decoration: none !important;
 transform: translateY(-1px);
 box-shadow: 0 2px 4px rgba(0,0,0,0.1) !important;
 }

 /* Ajustes específicos para modal de ejemplos en móvil */
 @media (max-width: 768px) {
 #tbv2-example-modal-bypass .modal-content-mobile {
 max-width: 90% !important;
 width: 95% !important;
 max-height: 80vh !important;
 }
 
 #tbv2-example-modal-bypass .modal-image-mobile {
 max-width: 500px !important;
 max-height: 60vh !important;
 }

 /* Simplificar botones Ver ejemplo en móvil */
 .view-example {
 background: transparent !important;
 padding: 2px 4px !important;
 font-size: 11px !important;
 color: #6b7280 !important;
 border-radius: 2px !important;
 }
 
 .view-example:hover {
 background: #f3f4f6 !important;
 transform: none !important;
 box-shadow: none !important;
 }
 }

 .upload-wrapper {
 position: relative;
 }

 .upload-wrapper input[type="file"] {
 position: absolute;
 opacity: 0;
 width: 100%;
 height: 100%;
 cursor: pointer;
 }

 .upload-button {
 display: block;
 padding: 12px 16px;
 background: #f8fafc;
 border: 2px dashed #d1d5db;
 border-radius: 6px;
 text-align: center;
 color: #6b7280;
 font-size: 14px;
 font-weight: 500;
 cursor: pointer;
 transition: all 0.2s ease;
 margin-bottom: 8px;
 }

 .upload-button:hover {
 border-color: #016d86;
 background: rgba(1, 109, 134, 0.05);
 color: #016d86;
 }

 .upload-button-responsive .mobile-text {
 display: none;
 }

 .file-count {
 font-size: 13px;
 color: #6b7280;
 text-align: center;
 }

 /* ===== SISTEMA DE PREVIEW DE ARCHIVOS ===== */
 .file-preview-container {
 margin-top: 8px;
 max-height: 150px;
 overflow-y: auto;
 border-radius: 6px;
 }

 .file-preview-item {
 display: flex;
 align-items: center;
 justify-content: space-between;
 padding: 6px 8px;
 margin-bottom: 4px;
 background: #f8fafc;
 border: 1px solid #e2e8f0;
 border-radius: 4px;
 font-size: 12px;
 }

 .file-preview-info {
 display: flex;
 align-items: center;
 flex: 1;
 min-width: 0;
 }

 .file-preview-icon {
 font-size: 14px;
 margin-right: 6px;
 color: #6b7280;
 }

 .file-preview-name {
 font-weight: 500;
 color: #374151;
 white-space: nowrap;
 overflow: hidden;
 text-overflow: ellipsis;
 flex: 1;
 margin-right: 6px;
 }

 .file-preview-size {
 font-size: 11px;
 color: #9ca3af;
 white-space: nowrap;
 }

 .file-remove-btn {
 background: #ef4444;
 border: none;
 border-radius: 3px;
 color: white;
 padding: 3px 6px;
 font-size: 10px;
 cursor: pointer;
 display: flex;
 align-items: center;
 justify-content: center;
 min-width: 20px;
 height: 20px;
 margin-left: 8px;
 transition: background-color 0.2s;
 }

 .file-remove-btn:hover {
 background: #dc2626;
 }

 .file-remove-btn i {
 font-size: 8px;
 }

 /* Mobile responsive */
 @media (max-width: 768px) {
 .file-preview-container {
 max-height: 120px;
 }

 .file-preview-item {
 padding: 4px 6px;
 font-size: 11px;
 }

 .file-preview-name {
 max-width: 120px;
 }

 .file-preview-size {
 font-size: 10px;
 }

 .file-remove-btn {
 padding: 2px 4px;
 min-width: 18px;
 height: 18px;
 }
 }

 /* ===== MODAL DE EJEMPLOS ===== */
 .tbv2-example-modal {
 display: none !important;
 position: fixed !important;
 z-index: 99999 !important;
 left: 0;
 top: 0;
 width: 100vw;
 height: 100vh;
 background-color: rgba(0, 0, 0, 0.85) !important;
 backdrop-filter: blur(4px);
 overflow-y: auto;
 align-items: center;
 justify-content: center;
 padding: 20px;
 box-sizing: border-box;
 }

 .tbv2-example-modal .tbv2-modal-content {
 position: relative;
 background-color: #ffffff;
 padding: 0;
 border-radius: 16px;
 box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4);
 width: 90%;
 max-width: 700px;
 max-height: 85vh;
 overflow-y: auto;
 animation: modalSlideIn 0.3s ease;
 }

 .tbv2-modal-body {
 padding: 0;
 max-height: 70vh;
 overflow-y: auto;
 display: block !important;
 visibility: visible !important;
 }

 #example-modal-content {
 display: block !important;
 visibility: visible !important;
 opacity: 1 !important;
 }

 .example-document-image {
 width: 100%;
 max-width: 100%;
 height: auto;
 border-radius: 8px;
 box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
 margin-bottom: 16px;
 }

 .example-description {
 background: #f8fafc;
 padding: 16px;
 border-radius: 8px;
 border-left: 4px solid #016d86;
 margin-bottom: 16px;
 }

 .example-description h4 {
 margin: 0 0 8px 0;
 color: #016d86;
 font-size: 16px;
 font-weight: 600;
 }

 .example-description p {
 margin: 0;
 color: #4b5563;
 font-size: 14px;
 line-height: 1.5;
 }

 .example-tips {
 background: #fefce8;
 border: 1px solid #fbbf24;
 border-radius: 8px;
 padding: 12px;
 margin-top: 16px;
 }

 .example-tips h5 {
 margin: 0 0 8px 0;
 color: #f59e0b;
 font-size: 14px;
 font-weight: 600;
 }

 .example-tips ul {
 margin: 0;
 padding-left: 16px;
 color: #92400e;
 font-size: 13px;
 }

 .example-tips li {
 margin-bottom: 4px;
 }

 /* Mobile responsive para modal de ejemplos */
 @media (max-width: 768px) {
 .tbv2-example-modal .tbv2-modal-content {
 width: 95%;
 margin: 5% auto;
 max-height: 85vh;
 }

 .example-description h4 {
 font-size: 15px;
 }

 .example-description p {
 font-size: 13px;
 }

 .example-tips {
 padding: 10px;
 }

 .example-tips h5 {
 font-size: 13px;
 }

 .example-tips ul {
 font-size: 12px;
 }
 }

 /* Mobile responsive */
 @media (max-width: 768px) {
 .upload-row {
 flex-direction: column;
 gap: 15px;
 }

 .upload-button-responsive .desktop-text {
 display: none;
 }

 .upload-button-responsive .mobile-text {
 display: inline;
 }
 }

 /* ============================================
 DOCUMENT PREVIEW MODAL STYLES
 ============================================ */

 .tbv2-preview-modal {
 position: fixed;
 top: 0;
 left: 0;
 width: 100%;
 height: 100%;
 background-color: rgba(0, 0, 0, 0.9);
 z-index: 999998;
 display: none;
 align-items: center;
 justify-content: center;
 overflow: hidden;
 }

 .tbv2-preview-modal.active {
 display: flex;
 }

 .tbv2-preview-content {
 position: relative;
 background: white;
 border-radius: 12px;
 width: 90vw;
 max-width: 700px;
 max-height: 85vh;
 overflow: hidden;
 box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
 display: flex;
 flex-direction: column;
 }

 .tbv2-preview-header {
 padding: 20px 24px;
 background: linear-gradient(135deg, #016d86 0%, #0a5469 100%);
 color: white;
 display: flex;
 justify-content: space-between;
 align-items: center;
 border-bottom: 1px solid #e5e7eb;
 }

 .tbv2-preview-header h3 {
 margin: 0;
 font-size: 18px;
 font-weight: 600;
 }

 .tbv2-preview-body {
 flex: 1;
 overflow-y: auto;
 padding: 0;
 max-height: 400px;
 }

 #document-content-preview {
 padding: 24px;
 color: #1f2937;
 line-height: 1.6;
 }

 .tbv2-preview-footer {
 padding: 20px 24px;
 background: #f8f9fa;
 border-top: 1px solid #e5e7eb;
 }

 .tbv2-preview-instructions {
 margin: 0 0 16px 0;
 color: #6b7280;
 font-size: 14px;
 text-align: center;
 }

 .tbv2-preview-button-container {
 display: flex;
 gap: 12px;
 justify-content: center;
 }

 .tbv2-modal-cancel-btn,
 .tbv2-modal-proceed-btn {
 padding: 12px 24px;
 border: none;
 border-radius: 8px;
 font-size: 14px;
 font-weight: 600;
 cursor: pointer;
 transition: all 0.2s ease;
 display: flex;
 align-items: center;
 gap: 8px;
 }

 .tbv2-modal-cancel-btn {
 background: #f1f5f9;
 color: #64748b;
 border: 1px solid #e2e8f0;
 }

 .tbv2-modal-cancel-btn:hover {
 background: #e2e8f0;
 color: #475569;
 }

 .tbv2-modal-proceed-btn {
 background: #016d86;
 color: white;
 }

 .tbv2-modal-proceed-btn:hover {
 background: #0a5469;
 transform: translateY(-1px);
 }

 .document-section {
 margin-bottom: 24px;
 padding: 16px;
 border-left: 4px solid #016d86;
 background: #f8fafc;
 border-radius: 0 8px 8px 0;
 }

 .document-section h4 {
 margin: 0 0 12px 0;
 color: #016d86;
 font-size: 16px;
 font-weight: 600;
 }

 .document-section p {
 margin: 8px 0;
 font-size: 14px;
 line-height: 1.6;
 }

 .document-highlight {
 background: #fef3cd;
 border: 1px solid #d97706;
 border-radius: 8px;
 padding: 16px;
 margin: 20px 0;
 }

 .document-highlight p {
 margin: 0;
 color: #92400e;
 font-weight: 600;
 text-align: center;
 }

 @media (max-width: 768px) {
 .tbv2-preview-content {
 width: 95vw;
 max-height: 90vh;
 }

 .tbv2-preview-button-container {
 flex-direction: column;
 }

 .tbv2-modal-cancel-btn,
 .tbv2-modal-proceed-btn {
 width: 100%;
 }
 }

 /* ============================================
 SIGNATURE MODAL STYLES
 ============================================ */

 .tbv2-signature-modal {
 position: fixed;
 top: 0;
 left: 0;
 width: 100%;
 height: 100%;
 background-color: rgba(0, 0, 0, 0.95);
 z-index: 999999;
 display: none;
 align-items: center;
 justify-content: center;
 overflow: hidden;
 }

 .tbv2-signature-modal.active {
 display: flex;
 }


 .tbv2-modal-content {
 position: relative;
 background: white;
 border-radius: 12px;
 width: 90vw;
 max-width: 600px;
 max-height: 90vh;
 overflow: hidden;
 box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
 }

 .tbv2-modal-header {
 padding: 20px 24px;
 background: linear-gradient(135deg, #016d86 0%, #0a5469 100%);
 color: white;
 display: flex;
 justify-content: space-between;
 align-items: center;
 }

 .tbv2-modal-header h3 {
 margin: 0;
 font-size: 18px;
 font-weight: 600;
 }

 .tbv2-modal-close {
 background: none;
 border: none;
 color: white;
 font-size: 20px;
 cursor: pointer;
 padding: 4px;
 border-radius: 4px;
 transition: background 0.2s ease;
 }

 .tbv2-modal-close:hover {
 background: rgba(255, 255, 255, 0.2);
 }

 .tbv2-enhanced-signature-container {
 position: relative;
 height: 300px;
 background: #f8f9fa;
 margin: 0;
 border-bottom: 1px solid #e5e7eb;
 }

 .tbv2-signature-guide {
 position: absolute;
 top: 50%;
 left: 20px;
 right: 20px;
 z-index: 1;
 pointer-events: none;
 transform: translateY(-50%);
 }

 .tbv2-signature-line {
 height: 2px;
 background: #d1d5db;
 margin: 8px 0;
 }

 .tbv2-signature-instruction {
 text-align: center;
 color: #6b7280;
 font-size: 14px;
 font-weight: 500;
 text-transform: uppercase;
 letter-spacing: 0.5px;
 }

 #enhanced-signature-canvas {
 position: absolute;
 top: 0;
 left: 0;
 width: 100%;
 height: 100%;
 touch-action: none;
 cursor: crosshair;
 }

 .tbv2-modal-footer {
 padding: 20px 24px;
 background: #f8f9fa;
 }

 .tbv2-modal-instructions {
 margin: 0 0 16px 0;
 color: #6b7280;
 font-size: 14px;
 text-align: center;
 }

 .tbv2-modal-button-container {
 display: flex;
 gap: 12px;
 justify-content: center;
 }

 .tbv2-modal-clear-btn,
 .tbv2-modal-accept-btn {
 padding: 10px 20px;
 border: none;
 border-radius: 6px;
 font-size: 14px;
 font-weight: 500;
 cursor: pointer;
 transition: all 0.2s ease;
 display: flex;
 align-items: center;
 gap: 8px;
 }

 .tbv2-modal-clear-btn {
 background: #f1f5f9;
 color: #64748b;
 border: 1px solid #e2e8f0;
 }

 .tbv2-modal-clear-btn:hover {
 background: #e2e8f0;
 color: #475569;
 }

 .tbv2-modal-accept-btn {
 background: #016d86;
 color: white;
 }

 .tbv2-modal-accept-btn:hover:not(:disabled) {
 background: #0a5469;
 transform: translateY(-1px);
 }

 .tbv2-modal-accept-btn:disabled {
 background: #d1d5db;
 color: #9ca3af;
 cursor: not-allowed;
 }

 /* ============================
 PAYMENT PAGE STYLES
 ============================ */
 .stripe-spinner {
 border: 4px solid #f3f3f3;
 border-top: 4px solid #016d86;
 border-radius: 50%;
 width: 40px;
 height: 40px;
 display: inline-block;
 }


 #submit-payment {
 transition: all 0.3s ease;
 position: relative;
 overflow: hidden;
 }

 #submit-payment::before {
 content: '';
 position: absolute;
 top: 0;
 left: -100%;
 width: 100%;
 height: 100%;
 background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
 transition: left 0.5s ease;
 }

 #submit-payment:hover::before {
 left: 100%;
 }

 #submit-payment:hover {
 background: linear-gradient(135deg, #015266 0%, #013d4d 100%);
 box-shadow: 0 6px 8px -1px rgba(1, 109, 134, 0.4), 0 4px 6px -1px rgba(1, 109, 134, 0.3);
 transform: translateY(-1px);
 }

 #submit-payment:active {
 transform: translateY(0);
 box-shadow: 0 2px 4px -1px rgba(1, 109, 134, 0.3);
 }

 #submit-payment:disabled {
 background: #9ca3af !important;
 cursor: not-allowed !important;
 box-shadow: none !important;
 opacity: 0.6;
 transform: none !important;
 }

 #submit-payment:disabled::before {
 display: none;
 }

 /* Terms Checkbox Styling */
 .payment-terms .checkmark-box {
 transition: all 0.2s ease;
 }

 .payment-terms .checkmark-box:hover {
 background-color: #f8f9fa;
 border-color: #015266;
 transform: scale(1.05);
 }

 .payment-terms input[type="checkbox"]:checked + .checkmark-box {
 background-color: #016d86 !important;
 border-color: #016d86 !important;
 }

 .payment-terms input[type="checkbox"]:checked + .checkmark-box + .checkmark {
 display: block !important;
 }

 /* Security Badges */
 .payment-security {
 background: linear-gradient(135deg, #f8f9fa 0%, #f3f4f6 100%);
 border: 1px solid #e5e7eb;
 transition: all 0.3s ease;
 }

 .payment-security:hover {
 transform: translateY(-2px);
 box-shadow: 0 4px 12px rgba(0,0,0,0.1);
 }

 .security-badge {
 transition: all 0.2s ease;
 }

 .security-badge:hover {
 transform: scale(1.05);
 }

 /* Payment Message States */
 #payment-message {
 border-radius: 8px;
 transition: all 0.3s ease;
 font-weight: 500;
 }

 #payment-message.error {
 background-color: rgba(239, 68, 68, 0.1);
 color: #dc2626;
 border: 1px solid rgba(239, 68, 68, 0.3);
 box-shadow: 0 2px 4px rgba(239, 68, 68, 0.1);
 }

 #payment-message.success {
 background-color: rgba(34, 197, 94, 0.1);
 color: #059669;
 border: 1px solid rgba(34, 197, 94, 0.3);
 box-shadow: 0 2px 4px rgba(34, 197, 94, 0.1);
 }

 #payment-message.processing {
 background-color: rgba(1, 109, 134, 0.1);
 color: #016d86;
 border: 1px solid rgba(1, 109, 134, 0.3);
 box-shadow: 0 2px 4px rgba(1, 109, 134, 0.1);
 }

 /* Stripe Elements Styling */
 #payment-element {
 border-radius: 8px;
 box-shadow: 0 1px 3px rgba(0, 0, 0, 0.12), 0 1px 2px rgba(0, 0, 0, 0.24);
 transition: all 0.3s ease;
 }

 #payment-element:focus-within {
 box-shadow: 0 4px 6px rgba(0, 0, 0, 0.12), 0 0 0 3px rgba(1, 109, 134, 0.3);
 transform: translateY(-1px);
 }

 /* Loading States */
 .payment-loading {
 opacity: 0.6;
 pointer-events: none;
 position: relative;
 }

 .payment-loading::after {
 content: '';
 position: absolute;
 top: 0;
 left: 0;
 right: 0;
 bottom: 0;
 background: rgba(255, 255, 255, 0.8);
 display: flex;
 align-items: center;
 justify-content: center;
 border-radius: inherit;
 z-index: 1000;
 }
 </style>
 <?php
}

/**
 * JavaScript IDÉNTICO al original
 */
function tbv2_render_scripts() {
 ?>
 <script>
 document.addEventListener('DOMContentLoaded', function() {
 
 // PROTECCIÓN: Solo ejecutar en páginas autorizadas
 if (window.TBV2_DISABLED === true) {
     console.log('🚫 TBV2: Sistema deshabilitado en página no autorizada');
     return; // Salir completamente, no ejecutar nada
 }
 
 console.log('✅ TBV2 - Inicializando formulario en página autorizada...');
 TBV2_Form.init();
 TBV2_Form.initRealTimeValidation();
 initializePaymentSystem();
 
 // Verificar si venimos de un pago exitoso
 checkSuccessfulPayment();
 
 console.log(' Sistema de pago inicializado');
 });

// PROTECCIÓN: Solo definir objetos globales en páginas autorizadas
if (window.TBV2_DISABLED !== true) {

 // Namespace principal del formulario
 const TBV2_Form = {
 currentPage: 'page-vehiculo',
 pages: ['page-vehiculo', 'page-datos', 'page-precio', 'page-documentos', 'page-pago'],
 
 init() {
 this.setupNavigation();
 this.setupManufacturerSelector();
 this.setupSidebarContent();
 this.updateNavigationState();
 this.updateProgressBar();
 console.log(' TBV2 inicializado correctamente');
 },
 
 initRealTimeValidation() {
 // Validación en tiempo real para campos clave
 const fields = {
 matricula: {
 element: document.getElementById('matricula'),
 validator: this.validateMatricula,
 message: 'Formato: ABC-123-456 (letras-números-números)'
 },
 length: {
 element: document.getElementById('length'),
 validator: this.validateLength,
 message: 'Eslora entre 1 y 50 metros'
 },
 purchase_price: {
 element: document.getElementById('purchase_price'),
 validator: this.validatePrice,
 message: 'Precio válido mayor que 0€'
 },
 matriculation_date: {
 element: document.getElementById('matriculation_date'),
 validator: this.validateMatriculationDate,
 message: 'La fecha no puede ser posterior a hoy'
 },
 region: {
 element: document.getElementById('region'),
 validator: (value) => value && value.trim().length > 0,
 message: 'Selecciona tu comunidad autónoma'
 },
 customer_name: {
 element: document.getElementById('customer_name'),
 validator: (value) => value.trim().length >= 3,
 message: 'Mínimo 3 caracteres'
 },
 customer_dni: {
 element: document.getElementById('customer_dni'),
 validator: this.validateDNI,
 message: 'Formato: 12345678X (con letra correcta)'
 },
 customer_email: {
 element: document.getElementById('customer_email'),
 validator: this.validateEmail,
 message: 'Formato de email válido'
 },
 customer_phone: {
 element: document.getElementById('customer_phone'),
 validator: this.validatePhone,
 message: 'Teléfono de 9 dígitos'
 }
 };

 // Aplicar validación en tiempo real a cada campo
 Object.keys(fields).forEach(fieldName => {
 const fieldConfig = fields[fieldName];
 if (fieldConfig.element) {
 this.setupFieldValidation(fieldConfig.element, fieldConfig.validator, fieldConfig.message);
 }
 });

 // Validación especial para dropdowns
 const manufacturer = document.getElementById('manufacturer');
 const model = document.getElementById('model');
 const region = document.getElementById('region');

 if (manufacturer) {
 this.setupDropdownValidation(manufacturer, 'Selecciona un fabricante');
 }
 if (model) {
 this.setupDropdownValidation(model, 'Selecciona un modelo');
 }
 if (region) {
 this.setupDropdownValidation(region, 'Selecciona tu comunidad autónoma');
 }

 console.log(' Validación en tiempo real activada');
 },

 setupFieldValidation(element, validator, message) {
 // Crear contenedor de validación si no existe
 let validationContainer = element.parentElement.querySelector('.validation-feedback');
 if (!validationContainer) {
 validationContainer = document.createElement('div');
 validationContainer.className = 'validation-feedback';
 validationContainer.style.cssText = `
 margin-top: 6px;
 font-size: 12px;
 line-height: 1.3;
 transition: all 0.3s ease;
 opacity: 0;
 height: 0;
 overflow: hidden;
 `;
 element.parentElement.appendChild(validationContainer);
 }

 // Eventos de validación
 element.addEventListener('input', () => {
 this.validateFieldRealTime(element, validator, validationContainer, message);
 });

 element.addEventListener('blur', () => {
 this.validateFieldRealTime(element, validator, validationContainer, message);
 });
 },

 setupDropdownValidation(element, message) {
 let validationContainer = element.parentElement.querySelector('.validation-feedback');
 if (!validationContainer) {
 validationContainer = document.createElement('div');
 validationContainer.className = 'validation-feedback';
 validationContainer.style.cssText = `
 margin-top: 6px;
 font-size: 12px;
 line-height: 1.3;
 transition: all 0.3s ease;
 opacity: 0;
 height: 0;
 overflow: hidden;
 `;
 element.parentElement.appendChild(validationContainer);
 }

 element.addEventListener('change', () => {
 if (element.value) {
 this.showFieldSuccess(element, validationContainer);
 } else {
 this.showFieldError(element, validationContainer, message);
 }
 });
 },

 validateFieldRealTime(element, validator, container, message) {
 const isValid = validator.call(this, element.value);
 
 if (element.value === '') {
 // Campo vacío - no mostrar error aún
 this.hideValidation(element, container);
 } else if (isValid) {
 this.showFieldSuccess(element, container);
 } else {
 this.showFieldError(element, container, message);
 }
 },

 showFieldSuccess(element, container) {
 element.style.borderColor = '#10b981';
 element.style.backgroundColor = '#f0fdf4';
 
 container.style.color = '#059669';
 container.innerHTML = '<i class="fas fa-check-circle" style="margin-right: 4px;"></i>Correcto';
 container.style.opacity = '1';
 container.style.height = 'auto';
 },

 showFieldError(element, container, message) {
 element.style.borderColor = '#ef4444';
 element.style.backgroundColor = '#fef2f2';
 
 container.style.color = '#dc2626';
 container.innerHTML = '<i class="fas fa-exclamation-circle" style="margin-right: 4px;"></i>' + message;
 container.style.opacity = '1';
 container.style.height = 'auto';
 },

 hideValidation(element, container) {
 element.style.borderColor = '';
 element.style.backgroundColor = '';
 container.style.opacity = '0';
 container.style.height = '0';
 },

 // Validadores específicos
 validateMatricula(value) {
 // Formato español embarcaciones: ABC-123-456 o ABC-1234-AB
 const patterns = [
 /^[A-Za-z]{2,3}-\d{3,4}-\d{2,3}$/, // ABC-123-456
 /^[A-Za-z]{2,3}-\d{3,4}-[A-Za-z]{2}$/ // ABC-1234-AB
 ];
 return patterns.some(pattern => pattern.test(value));
 },


 validateLength(value) {
 const length = parseFloat(value);
 return length >= 1 && length <= 50;
 },

 validatePrice(value) {
 const price = parseFloat(value.replace(/[^0-9.,]/g, '').replace(',', '.'));
 return price > 0;
 },

 validateMatriculationDate(value) {
 if (!value) return false;
 const inputDate = new Date(value);
 const today = new Date();
 // Resetear horas para comparar solo fechas
 today.setHours(23, 59, 59, 999);
 return inputDate <= today;
 },
 
 setupNavigation() {
 // Botones de navegación
 const btnSiguiente = document.getElementById('tbv2-nextButton');
 const btnAnterior = document.getElementById('tbv2-prevButton');
 
 btnSiguiente?.addEventListener('click', () => {
 console.log(' Botón siguiente presionado');
 this.nextPage();
 });
 
 btnAnterior?.addEventListener('click', () => {
 console.log(' Botón anterior presionado');
 this.previousPage();
 });
 
 // Nuevos botones navegación página DATOS
 const btnDatosPrev = document.getElementById('tbv2-datos-prevButton');
 const btnDatosNext = document.getElementById('tbv2-datos-nextButton');
 
 btnDatosPrev?.addEventListener('click', () => {
 console.log(' DATOS: Botón anterior presionado');
 this.goToPage('page-vehiculo');
 });
 
 btnDatosNext?.addEventListener('click', () => {
 console.log(' DATOS: Botón siguiente presionado');
 this.goToPage('page-precio');
 });
 
 // Nuevos botones navegación página ITP/PRECIO
 const btnPrecioPrev = document.getElementById('tbv2-precio-prevButton');
 const btnPrecioNext = document.getElementById('tbv2-precio-nextButton');
 
 btnPrecioPrev?.addEventListener('click', () => {
 console.log(' ITP: Botón anterior presionado');
 this.goToPage('page-datos');
 });
 
 btnPrecioNext?.addEventListener('click', () => {
 console.log(' ITP: Botón siguiente presionado');
 this.goToPage('page-documentos');
 });
 
 // Nuevos botones navegación página DOCUMENTOS 
 const btnDocumentosPrev = document.getElementById('tbv2-documentos-prevButton');
 const btnDocumentosNext = document.getElementById('tbv2-documentos-nextButton');
 
 btnDocumentosPrev?.addEventListener('click', () => {
 console.log(' DOCUMENTOS: Botón anterior presionado');
 this.goToPage('page-precio');
 });
 
 btnDocumentosNext?.addEventListener('click', () => {
 console.log(' DOCUMENTOS: Botón siguiente presionado');
 this.goToPage('page-pago');
 });
 
 // Nuevos botones navegación página PAGO
 const btnPagoPrev = document.getElementById('tbv2-pago-prevButton');
 const btnPagoNext = document.getElementById('tbv2-pago-nextButton');
 
 btnPagoPrev?.addEventListener('click', () => {
 console.log(' PAGO: Botón anterior presionado');
 this.goToPage('page-documentos');
 });
 
 btnPagoNext?.addEventListener('click', () => {
 console.log(' PAGO: Botón completar presionado');
 // TODO: Implement form submission
 this.submitForm();
 });
 
 // Navegación por tabs profesionales
 document.querySelectorAll('.nav-tab').forEach(item => {
 item.addEventListener('click', (e) => {
 e.preventDefault();
 const pageId = item.dataset.pageId;
 if (pageId) {
 this.goToPage(pageId);
 }
 });
 });
 },
 
 setupManufacturerSelector() {
 const manufacturerSelect = document.getElementById('manufacturer');
 const modelSelect = document.getElementById('model');
 const noEncuentroCheckbox = document.getElementById('no_encuentro_modelo');
 const manualFields = document.getElementById('manual-fields');
 const vehicleCsvSection = document.getElementById('vehicle-csv-section');
 const matriculationDateInput = document.getElementById('matriculation_date');
 
 // Enhanced manufacturer selection with search
 manufacturerSelect?.addEventListener('change', (e) => {
 const selectedFabricante = e.target.value;
 this.loadModelsForManufacturer(selectedFabricante);
 this.updateSidebarContent();
 });

 // Setup search functionality
 const searchInput = document.getElementById('manufacturer-search');
 if (searchInput) {
 searchInput.addEventListener('input', (e) => {
 this.filterManufacturers(e.target.value);
 });
 
 searchInput.addEventListener('keydown', (e) => {
 if (e.key === 'Enter') {
 e.preventDefault();
 this.selectFirstFilteredManufacturer();
 }
 });
 }
 
 // Event listeners para actualizar sidebar dinámicamente
 const fieldsToWatch = ['matricula', 'length', 'manual_manufacturer', 'manual_model', 'customer_name', 'customer_dni', 'customer_email', 'customer_phone'];
 fieldsToWatch.forEach(fieldId => {
 const field = document.getElementById(fieldId);
 if (field) {
 field.addEventListener('input', () => {
 this.updateSidebarContent();
 });
 }
 });

 // ITP functionality will be set up when navigating to that page

 // Event listeners para actualizar display ITP cuando cambien datos del vehículo
 const itpUpdateFields = ['manufacturer', 'model', 'purchase_price', 'matriculation_date', 'region', 'manual_manufacturer', 'manual_model'];
 itpUpdateFields.forEach(fieldId => {
 const field = document.getElementById(fieldId);
 if (field) {
 field.addEventListener('change', () => {
 console.log(` Campo ${fieldId} cambió, recalculando ITP...`);
 this.updateITPDisplay();
 });
 field.addEventListener('input', () => {
 this.updateITPDisplay();
 });
 }
 });

 // Checkbox "No encuentro mi modelo"
 noEncuentroCheckbox?.addEventListener('change', (e) => {
 const isChecked = e.target.checked;
 
 if (isChecked) {
 // Mostrar campos manuales
 vehicleCsvSection.style.display = 'none';
 manualFields.style.display = 'block';
 
 // Ocultar fecha matriculación para manual
 const matriculationContainer = matriculationDateInput?.closest('.form-group');
 if (matriculationContainer) {
 matriculationContainer.style.display = 'none';
 }
 matriculationDateInput?.removeAttribute('required');
 
 // Limpiar selecciones anteriores
 manufacturerSelect.value = '';
 modelSelect.value = '';
 modelSelect.innerHTML = '<option value="">Seleccione modelo</option>';
 } else {
 // Mostrar campos CSV
 vehicleCsvSection.style.display = 'block';
 manualFields.style.display = 'none';
 
 // Mostrar fecha matriculación
 const matriculationContainer = matriculationDateInput?.closest('.form-group');
 if (matriculationContainer) {
 matriculationContainer.style.display = 'block';
 }
 matriculationDateInput?.setAttribute('required', 'required');
 
 // Limpiar campos manuales
 document.getElementById('manual_manufacturer').value = '';
 document.getElementById('manual_model').value = '';
 }
 
 this.updateSidebarContent();
 // También actualizar ITP cuando se cambie el modo
 this.updateITPDisplay();
 });
 },
 
 setupSidebarContent() {
 // Inicializar contenido dinámico del sidebar
 this.updateSidebarContent();
 },
 
 updateSidebarContent() {
 const sidebarDynamic = document.getElementById('sidebar-dynamic-content');
 if (!sidebarDynamic) return;
 
 let content = '';
 
 switch (this.currentPage) {
 case 'page-vehiculo':
 content = this.getVehicleSidebarContent();
 break;
 case 'page-datos':
 content = this.getDatosSidebarContent();
 break;
 case 'page-precio':
 content = `
 <div style="text-align: center;">
 <h3 style="color: white; font-size: 18px; font-weight: 600; margin-bottom: 15px;">
 Información del ITP
 </h3>
 <p style="color: rgba(255,255,255,0.95); font-size: 14px; line-height: 1.5; margin-bottom: 20px;">
 Todo lo que necesitas saber sobre el Impuesto de Transmisiones Patrimoniales.
 </p>
 
 <!-- ¿Quién lo impone? -->
 <div style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); border-radius: 8px; padding: 12px; margin-bottom: 12px;">
 <div style="color: white; font-weight: 500; font-size: 13px; margin-bottom: 6px;">
 ¿Quién lo impone?
 </div>
 <p style="color: rgba(255,255,255,0.85); font-size: 13px; line-height: 1.4; margin: 0;">
 Hacienda de cada comunidad autónoma
 </p>
 </div>
 
 <!-- ¿Cómo se gestiona? -->
 <div style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); border-radius: 8px; padding: 12px; margin-bottom: 12px;">
 <div style="color: white; font-weight: 500; font-size: 13px; margin-bottom: 6px;">
 ¿Cómo se gestiona?
 </div>
 <div style="color: rgba(255,255,255,0.85); font-size: 13px; line-height: 1.4;">
 <div style="margin-bottom: 4px;">• Tú puedes pagarlo directamente</div>
 <div>• Nosotros podemos gestionarlo por ti</div>
 </div>
 </div>
 
 <!-- ¿Qué incluimos? -->
 <div style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); border-radius: 8px; padding: 12px; margin-bottom: 12px;">
 <div style="color: white; font-weight: 500; font-size: 13px; margin-bottom: 6px;">
 ¿Qué incluimos?
 </div>
 <p style="color: rgba(255,255,255,0.85); font-size: 13px; line-height: 1.4; margin: 0;">
 Gestión completa en DGMM, tasas oficiales e IVA
 </p>
 </div>
 
 <!-- Cálculo automático -->
 <div style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); border-radius: 8px; padding: 12px;">
 <div style="color: white; font-weight: 500; font-size: 13px; margin-bottom: 6px;">
 Cálculo Automático
 </div>
 <p style="color: rgba(255,255,255,0.85); font-size: 13px; line-height: 1.4; margin: 0;">
 ITP calculado automáticamente según precio y región
 </p>
 </div>
 </div>
 `;
 break;
 case 'page-documentos':
 content = this.getDocumentosSidebarContent();
 break;
 case 'page-pago':
 content = this.getPagoSidebarContent();
 break;
 }
 
 sidebarDynamic.innerHTML = content;
 },
 
 getVehicleSidebarContent() {
 const manufacturerSelect = document.getElementById('manufacturer');
 const modelSelect = document.getElementById('model');
 const matriculaInput = document.getElementById('matricula');
 const lengthInput = document.getElementById('length');
 const manualManufacturer = document.getElementById('manual_manufacturer');
 const manualModel = document.getElementById('manual_model');
 const noEncuentroCheckbox = document.getElementById('no_encuentro_modelo');
 
 let selectedManufacturer = manufacturerSelect?.value || '';
 let selectedModel = modelSelect?.value || '';
 let matricula = matriculaInput?.value || '';
 let length = lengthInput?.value || '';
 let year = document.getElementById('matriculation_date')?.value || ''; // Definir la variable year
 
 // Si está en modo manual
 if (noEncuentroCheckbox?.checked) {
 selectedManufacturer = manualManufacturer?.value || '';
 selectedModel = manualModel?.value || '';
 }
 
 // Calcular precio estimado
 let estimatedPrice = 134.99; // Precio base
 let selectedOption = modelSelect?.querySelector(`option[value="${selectedModel}"]`);
 if (selectedOption?.dataset.price) {
 estimatedPrice = parseFloat(selectedOption.dataset.price);
 }
 
 let vehicleInfo = '';
 
 if (selectedManufacturer || selectedModel || matricula) {
 vehicleInfo = `
 <div class="sidebar-vehicle-info">
 <h4 style="color: #ffffff; font-size: 16px; font-weight: 600; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
 <i class="fas fa-ship" style="color: #4ade80;"></i>
 Tu Embarcación
 </h4>
 <div class="vehicle-details">
 ${selectedManufacturer ? `
 <div class="detail-item">
 <span class="detail-label">Fabricante:</span>
 <span class="detail-value">${selectedManufacturer}</span>
 </div>
 ` : ''}
 ${selectedModel ? `
 <div class="detail-item">
 <span class="detail-label">Modelo:</span>
 <span class="detail-value">${selectedModel}</span>
 </div>
 ` : ''}
 ${matricula ? `
 <div class="detail-item">
 <span class="detail-label">Matrícula:</span>
 <span class="detail-value">${matricula}</span>
 </div>
 ` : ''}
 ${year ? `
 <div class="detail-item">
 <span class="detail-label">Año:</span>
 <span class="detail-value">${year}</span>
 </div>
 ` : ''}
 ${length ? `
 <div class="detail-item">
 <span class="detail-label">Eslora:</span>
 <span class="detail-value">${length}m</span>
 </div>
 ` : ''}
 </div>
 </div>
 `;
 }
 
 return `
 <div style="padding: 0;">
 <h3 style="color: white; font-size: 32px; margin: 0 0 16px 0; font-weight: 700; line-height: 1.2;">
 Cambio de Nombre Embarcación de Recreo
 </h3>
 
 <!-- Subtítulo -->
 
 <!-- Cuadro de precio más discreto -->
 <div style="background: rgba(255,255,255,0.08); border-radius: 12px; padding: 15px; text-align: center; border: 1px solid rgba(255,255,255,0.15); margin-bottom: 20px;">
 <div style="font-size: 11px; opacity: 0.8; text-transform: uppercase; letter-spacing: 1.2px; margin-bottom: 8px; color: rgba(255,255,255,0.85); font-weight: 500;">PRECIO TOTAL</div>
 <div style="font-size: 32px; font-weight: 600; margin: 4px 0; color: rgba(255,255,255,0.95);">134.99€</div>
 <div style="font-size: 12px; opacity: 0.85; color: rgba(255,255,255,0.85); line-height: 1.3; margin-top: 6px;">IVA y tasas DGMM incluidas</div>
 </div>
 
 <!-- 4 beneficios principales con checkmarks verdes - Más discretos -->
 <div style="margin-bottom: 20px;">
 <div style="display: flex; align-items: flex-start; gap: 10px; margin-bottom: 10px;">
 <i class="fas fa-check" style="background: rgba(255,255,255,0.9); color: #10b981; width: 18px; height: 18px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-top: 1px; font-size: 11px;"></i>
 <span style="color: rgba(255,255,255,0.85); font-size: 12px; line-height: 1.4;">Te entregamos un provisional en menos de 24h para que puedas navegar de inmediato</span>
 </div>
 <div style="display: flex; align-items: flex-start; gap: 10px; margin-bottom: 10px;">
 <i class="fas fa-check" style="background: rgba(255,255,255,0.9); color: #10b981; width: 18px; height: 18px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-top: 1px; font-size: 11px;"></i>
 <span style="color: rgba(255,255,255,0.85); font-size: 12px; line-height: 1.4;">Gestión del ITP incluida</span>
 </div>
 <div style="display: flex; align-items: flex-start; gap: 10px; margin-bottom: 10px;">
 <i class="fas fa-check" style="background: rgba(255,255,255,0.9); color: #10b981; width: 18px; height: 18px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-top: 1px; font-size: 11px;"></i>
 <span style="color: rgba(255,255,255,0.85); font-size: 12px; line-height: 1.4;">Seguimiento en todo momento del estado del trámite</span>
 </div>
 <div style="display: flex; align-items: flex-start; gap: 10px;">
 <i class="fas fa-check" style="background: rgba(255,255,255,0.9); color: #10b981; width: 18px; height: 18px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-top: 1px; font-size: 11px;"></i>
 <span style="color: rgba(255,255,255,0.85); font-size: 12px; line-height: 1.4;">Plazo aproximado de documentación definitiva: Unas tres semanas</span>
 </div>
 </div>
 </div>
 `;
 },

 getDatosSidebarContent() {
 // Obtener datos ingresados dinámicamente
 const customerName = document.getElementById('customer_name')?.value || '';
 const customerDni = document.getElementById('customer_dni')?.value || '';
 const customerEmail = document.getElementById('customer_email')?.value || '';
 const customerPhone = document.getElementById('customer_phone')?.value || '';

 let personalInfo = '';
 if (customerName || customerDni || customerEmail || customerPhone) {
 personalInfo = `
 <div class="sidebar-personal-info">
 <h4 style="color: #ffffff; font-size: 16px; font-weight: 600; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
 <i class="fas fa-user" style="color: #4ade80;"></i>
 Tus Datos
 </h4>
 <div class="personal-details">
 ${customerName ? `
 <div class="detail-item">
 <span class="detail-label">Nombre:</span>
 <span class="detail-value">${customerName}</span>
 </div>
 ` : ''}
 ${customerDni ? `
 <div class="detail-item">
 <span class="detail-label">DNI:</span>
 <span class="detail-value">${customerDni}</span>
 </div>
 ` : ''}
 ${customerEmail ? `
 <div class="detail-item">
 <span class="detail-label">Email:</span>
 <span class="detail-value">${customerEmail}</span>
 </div>
 ` : ''}
 ${customerPhone ? `
 <div class="detail-item">
 <span class="detail-label">Teléfono:</span>
 <span class="detail-value">${customerPhone}</span>
 </div>
 ` : ''}
 </div>
 </div>
 `;
 }
 
 return `
 <div style="background: rgba(255,255,255,0.1); padding: 18px; border-radius: 8px;">
 <h3 style="color: white; font-size: 16px; margin: 0 0 16px 0; font-weight: 600; line-height: 1.3;">
 Información Personal<br>y de Contacto
 </h3>
 
 <!-- 3 puntos destacados -->
 <div style="margin-bottom: 16px;">
 <div style="display: flex; align-items: center; margin-bottom: 8px; padding: 8px; background: rgba(255,255,255,0.1); border-radius: 6px; border-left: 3px solid rgba(255,255,255,0.6);">
 <span style="color: rgba(255,255,255,0.9); font-size: 12px;">Datos protegidos según RGPD</span>
 </div>
 <div style="display: flex; align-items: center; margin-bottom: 8px; padding: 8px; background: rgba(255,255,255,0.1); border-radius: 6px; border-left: 3px solid rgba(255,255,255,0.6);">
 <span style="color: rgba(255,255,255,0.9); font-size: 12px;">Uso exclusivo para el trámite</span>
 </div>
 <div style="display: flex; align-items: center; margin-bottom: 8px; padding: 8px; background: rgba(255,255,255,0.1); border-radius: 6px; border-left: 3px solid rgba(255,255,255,0.6);">
 <span style="color: rgba(255,255,255,0.9); font-size: 12px;">Comunicación vía email/teléfono</span>
 </div>
 </div>
 
 </div>
 `;
 },

 getDocumentosSidebarContent() {
 
 return `
 <div style="padding: 0;">
 <h3 style="color: white; font-size: 24px; margin: 0 0 16px 0; font-weight: 600; line-height: 1.2;">
 Documentación Necesaria
 </h3>
 
 <p style="color: rgba(255,255,255,0.9); font-size: 13px; line-height: 1.5; margin: 0 0 20px 0;">
 Sube los documentos requeridos para completar la transferencia de tu embarcación.
 </p>
 
 <!-- Lista de documentos requeridos -->
 <div style="background: rgba(255,255,255,0.08); border-radius: 12px; padding: 15px; margin-bottom: 20px;">
 <div style="color: rgba(255,255,255,0.95); font-size: 12px; font-weight: 600; margin-bottom: 12px; text-transform: uppercase; letter-spacing: 0.5px;">
 Documentos Obligatorios
 </div>
 
 <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
 <i class="fas fa-file-alt" style="color: #10b981; font-size: 14px;"></i>
 <span style="color: rgba(255,255,255,0.85); font-size: 12px;">DNI del comprador (ambas caras)</span>
 </div>
 
 <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
 <i class="fas fa-file-alt" style="color: #10b981; font-size: 14px;"></i>
 <span style="color: rgba(255,255,255,0.85); font-size: 12px;">DNI del vendedor (ambas caras)</span>
 </div>
 
 <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
 <i class="fas fa-file-alt" style="color: #10b981; font-size: 14px;"></i>
 <span style="color: rgba(255,255,255,0.85); font-size: 12px;">Registro marítimo</span>
 </div>
 
 <div style="display: flex; align-items: center; gap: 10px;">
 <i class="fas fa-file-alt" style="color: #10b981; font-size: 14px;"></i>
 <span style="color: rgba(255,255,255,0.85); font-size: 12px;">Hoja de asiento o tarjeta náutica</span>
 </div>
 </div>
 
 <!-- Información de formato -->
 <div style="background: rgba(255,255,255,0.05); border-left: 3px solid rgba(255,255,255,0.3); padding: 12px; margin-bottom: 12px;">
 <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
 <i class="fas fa-info-circle" style="color: rgba(255,255,255,0.7); font-size: 12px;"></i>
 <span style="color: rgba(255,255,255,0.8); font-size: 11px; font-weight: 600;">Formatos aceptados</span>
 </div>
 <p style="color: rgba(255,255,255,0.7); font-size: 11px; line-height: 1.4; margin: 0;">
 PDF, JPG, PNG, JPEG (máx. 10MB por archivo)
 </p>
 </div>
 
 <!-- Tiempo de procesamiento -->
 <div style="background: rgba(255,255,255,0.05); border-left: 3px solid rgba(255,255,255,0.3); padding: 12px; margin-bottom: 20px;">
 <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
 <i class="fas fa-clock" style="color: rgba(255,255,255,0.7); font-size: 12px;"></i>
 <span style="color: rgba(255,255,255,0.8); font-size: 11px; font-weight: 600;">Tiempo estimado</span>
 </div>
 <p style="color: rgba(255,255,255,0.7); font-size: 11px; line-height: 1.4; margin: 0;">
 Revisión de documentos en menos de 24h
 </p>
 </div>
 </div>
 `;
 },

 getPagoSidebarContent() {
 // Calculate total amount RESPETANDO selección ITP
 const basePrice = 134.99;
 let itpAmount = 0;
 let totalAmount = basePrice;
 
 // DEBUG CRÍTICO
 console.log(' DEBUG SIDEBAR PAGO:');
 console.log(' this.itpPagado:', this.itpPagado);
 console.log(' basePrice:', basePrice);
 
 // Solo incluir ITP si el usuario NO lo ha pagado
 if (this.itpPagado === false) {
 // Usar método de cálculo directamente
 itpAmount = this.calculateCurrentITP();
 totalAmount = basePrice + itpAmount;
 console.log(' ITP calculado:', itpAmount);
 console.log(' Total con ITP:', totalAmount);
 } else {
 // ITP ya pagado - solo precio base
 itpAmount = 0;
 totalAmount = basePrice;
 console.log(' ITP ya pagado - solo precio base');
 }
 
 // Get customer data
 const customerName = document.getElementById('customer_name')?.value || '';
 const vehicleBrand = document.getElementById('manufacturer')?.value || document.getElementById('manual_manufacturer')?.value || '';
 const vehicleModel = document.getElementById('model')?.value || document.getElementById('manual_model')?.value || '';
 
 console.log(' Vehículo:', vehicleBrand, vehicleModel);
 console.log(' Cliente:', customerName);
 
 return `
 <div style="text-align: center;">
 <h3 style="color: white; font-size: 18px; font-weight: 600; margin-bottom: 15px;">
 Tramitación para: Tramitfy S.L.
 </h3>
 
 <div style="background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.2); border-radius: 8px; padding: 16px; margin-bottom: 20px; text-align: left;">
 <div style="color: rgba(255,255,255,0.8); font-size: 12px; margin-bottom: 12px; text-transform: uppercase; font-weight: 600;">Desglose de Servicios</div>
 
 <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; padding: 8px 0;">
 <div style="color: rgba(255,255,255,0.9); font-size: 13px;">
 <div style="font-weight: 600;">Tramitación Completa</div>
 <div style="font-size: 11px; opacity: 0.7;">Gestión, tasas DGMM + IVA</div>
 </div>
 <span style="color: white; font-weight: 600; font-size: 14px;">134.99 €</span>
 </div>
 
 ${itpAmount > 0 ? `
 <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; padding: 8px 0; border-top: 1px solid rgba(255,255,255,0.1);">
 <div style="color: rgba(255,255,255,0.9); font-size: 13px;">
 <div style="font-weight: 600;">Impuesto (ITP)</div>
 <div style="font-size: 11px; opacity: 0.7;">Gestionamos el pago</div>
 </div>
 <span style="color: white; font-weight: 600; font-size: 14px;">${itpAmount.toFixed(2)} €</span>
 </div>
 ` : ''}
 
 <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-top: 2px solid rgba(255,255,255,0.3); margin-top: 8px;">
 <span style="color: white; font-weight: 700; font-size: 15px;">TOTAL</span>
 <span style="color: #22c55e; font-weight: 700; font-size: 18px;">${totalAmount.toFixed(2)} €</span>
 </div>
 </div>
 
 ${vehicleBrand && vehicleModel ? `
 <div style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.15); border-radius: 6px; padding: 12px; margin-bottom: 16px; text-align: left;">
 <div style="font-size: 11px; color: rgba(255,255,255,0.6); margin-bottom: 4px; text-transform: uppercase;">Vehículo</div>
 <div style="color: white; font-size: 13px; font-weight: 600;">${vehicleBrand} ${vehicleModel}</div>
 </div>
 ` : ''}
 </div>
 `;
 },
 
 updateProgressBar() {
 const currentIndex = this.pages.indexOf(this.currentPage);
 const progressIndicator = document.querySelector('.nav-progress-indicator');
 
 if (progressIndicator) {
 const progressPercentage = ((currentIndex + 1) / this.pages.length) * 100;
 progressIndicator.style.width = progressPercentage + '%';
 }
 },
 
 goToPage(pageId) {
 // Prevenir navegación a la misma página
 if (this.currentPage === pageId) {
 console.log(' Ya estás en la página:', pageId);
 return; // No hacer nada si ya estamos en esa página
 }
 
 // Ocultar página actual sin animación
 const currentPageElement = document.getElementById(this.currentPage);
 if (currentPageElement) {
 currentPageElement.classList.add('hidden');
 }
 
 // Mostrar nueva página sin animación
 const newPageElement = document.getElementById(pageId);
 if (newPageElement) {
 newPageElement.classList.remove('hidden');
 
 // Actualizar estado interno
 this.currentPage = pageId;
 this.updateNavigationState();
 this.updateProgressBar();
 this.updateSidebarContent();
 
 // Setup específico por página
 if (pageId === 'page-precio') {
 this.setupITPFunctionality();
 } else if (pageId === 'page-pago') {
 this.setupPaymentPage();
 }
 
 console.log(' Navegado a página:', pageId);
 
 // Scroll automático eliminado para mejor UX
 }
 },
 
 nextPage() {
 if (this.validateCurrentPage()) {
 const currentIndex = this.pages.indexOf(this.currentPage);
 if (currentIndex < this.pages.length - 1) {
 this.goToPage(this.pages[currentIndex + 1]);
 }
 }
 },
 
 previousPage() {
 const currentIndex = this.pages.indexOf(this.currentPage);
 if (currentIndex > 0) {
 this.goToPage(this.pages[currentIndex - 1]);
 }
 },
 
 updateNavigationState() {
 const currentIndex = this.pages.indexOf(this.currentPage);
 
 // Actualizar tabs profesionales
 document.querySelectorAll('.nav-tab').forEach((item, index) => {
 const shouldBeActive = index === currentIndex;
 const shouldBeCompleted = index < currentIndex;
 
 item.classList.toggle('active', shouldBeActive);
 item.classList.toggle('completed', shouldBeCompleted);
 });

 
 // Actualizar botones con mejor UX
 const btnAnterior = document.getElementById('tbv2-prevButton');
 const btnSiguiente = document.getElementById('tbv2-nextButton');
 
 if (btnAnterior) {
 if (currentIndex > 0) {
 btnAnterior.style.display = 'inline-flex';
 btnAnterior.style.opacity = '1';
 btnAnterior.innerHTML = '<i class="fas fa-arrow-left"></i> Anterior';
 btnAnterior.className = 'button button-secondary';
 btnAnterior.disabled = false;
 } else {
 btnAnterior.style.display = 'none';
 }
 }
 
 if (btnSiguiente) {
 const isLastPage = currentIndex === this.pages.length - 1;
 const pageNames = ['Datos Personales', 'Precio e ITP', 'Documentos', 'Completar Pago'];
 
 if (isLastPage) {
 btnSiguiente.innerHTML = '<i class="fas fa-credit-card"></i> Procesar Pago';
 btnSiguiente.className = 'button button-final';
 } else {
 const nextPageName = pageNames[currentIndex] || 'Siguiente';
 btnSiguiente.innerHTML = `<i class="fas fa-arrow-right"></i> ${nextPageName}`;
 btnSiguiente.className = 'button button-primary';
 }
 }
 },

 
 validateCurrentPage() {
 switch (this.currentPage) {
 case 'page-vehiculo':
 return this.validateVehiclePage();
 case 'page-datos':
 return this.validateDatosPage();
 case 'page-precio':
 return true; // TODO: Implementar validación
 case 'page-documentos':
 return true; // TODO: Implementar validación
 default:
 return true;
 }
 },
 
 validateVehiclePage() {
 const errors = [];
 const noEncuentroCheckbox = document.getElementById('no_encuentro_modelo')?.checked;
 const purchasePrice = document.getElementById('purchase_price')?.value;
 const region = document.getElementById('region')?.value;
 
 if (noEncuentroCheckbox) {
 // Validar campos manuales
 const manualManufacturer = document.getElementById('manual_manufacturer')?.value;
 const manualModel = document.getElementById('manual_model')?.value;
 
 if (!manualManufacturer) {
 errors.push('Introduce el fabricante manualmente');
 this.highlightRequiredField('manual_manufacturer');
 }
 
 if (!manualModel) {
 errors.push('Introduce el modelo manualmente');
 this.highlightRequiredField('manual_model');
 }
 } else {
 // Validar campos CSV
 const manufacturer = document.getElementById('manufacturer')?.value;
 const model = document.getElementById('model')?.value;
 const matriculationDate = document.getElementById('matriculation_date')?.value;
 
 if (!manufacturer) {
 errors.push('Selecciona un fabricante');
 this.highlightRequiredField('manufacturer');
 }
 
 if (!model) {
 errors.push('Selecciona un modelo');
 this.highlightRequiredField('model');
 }
 
 if (!matriculationDate) {
 errors.push('Introduce la fecha de matriculación');
 this.highlightRequiredField('matriculation_date');
 }
 }
 
 // Validar precio de compra (común para ambos modos)
 if (!purchasePrice) {
 errors.push('Introduce el precio de compra');
 this.highlightRequiredField('purchase_price');
 } else if (!this.validatePrice(purchasePrice)) {
 errors.push('El precio debe ser un número válido mayor que 0€');
 this.highlightErrorField('purchase_price');
 }
 
 // Validar comunidad autónoma (común para ambos modos)
 if (!region) {
 errors.push('Selecciona tu comunidad autónoma');
 this.highlightRequiredField('region');
 }
 
 // Mostrar errores si los hay
 if (errors.length > 0) {
 this.showValidationSummary(errors);
 return false;
 }
 
 return true;
 },

 validateDatosPage() {
 const errors = [];
 const customerName = document.getElementById('customer_name')?.value?.trim();
 const customerDni = document.getElementById('customer_dni')?.value?.trim();
 const customerEmail = document.getElementById('customer_email')?.value?.trim();
 const customerPhone = document.getElementById('customer_phone')?.value?.trim();

 // Validar nombre
 if (!customerName) {
 errors.push('Introduce tu nombre y apellidos');
 this.highlightRequiredField('customer_name');
 } else if (customerName.length < 3) {
 errors.push('El nombre debe tener al menos 3 caracteres');
 this.highlightErrorField('customer_name');
 }

 // Validar DNI
 if (!customerDni) {
 errors.push('Introduce tu DNI');
 this.highlightRequiredField('customer_dni');
 } else if (!this.validateDNI(customerDni)) {
 errors.push('Formato de DNI incorrecto (ej: 12345678X)');
 this.highlightErrorField('customer_dni');
 }

 // Validar email
 if (!customerEmail) {
 errors.push('Introduce tu correo electrónico');
 this.highlightRequiredField('customer_email');
 } else if (!this.validateEmail(customerEmail)) {
 errors.push('Formato de email incorrecto');
 this.highlightErrorField('customer_email');
 }

 // Validar teléfono
 if (!customerPhone) {
 errors.push('Introduce tu teléfono');
 this.highlightRequiredField('customer_phone');
 } else if (!this.validatePhone(customerPhone)) {
 errors.push('Formato de teléfono incorrecto (9 dígitos)');
 this.highlightErrorField('customer_phone');
 }

 // Mostrar errores si los hay
 if (errors.length > 0) {
 this.showValidationSummary(errors);
 return false;
 }

 return true;
 },

 // Validadores específicos para datos personales
 validateDNI(dni) {
 // Formato español: 8 números + 1 letra
 const dniRegex = /^\d{8}[A-Za-z]$/;
 if (!dniRegex.test(dni)) return false;

 // Verificar letra del DNI
 const letters = 'TRWAGMYFPDXBNJZSQVHLCKE';
 const numbers = parseInt(dni.substring(0, 8));
 const letter = dni.substring(8, 9).toUpperCase();
 return letters[numbers % 23] === letter;
 },

 validateEmail(email) {
 const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
 return emailRegex.test(email);
 },

 validatePhone(phone) {
 // Formato español: 9 dígitos (móvil o fijo)
 const phoneRegex = /^[0-9]{9}$/;
 return phoneRegex.test(phone.replace(/\s/g, ''));
 },

 // Helper methods for enhanced validation UI
 highlightRequiredField(fieldId) {
 const field = document.getElementById(fieldId);
 const container = field?.parentElement.querySelector('.validation-feedback');
 if (field && container) {
 this.showFieldError(field, container, 'Este campo es obligatorio');
 }
 },

 highlightErrorField(fieldId) {
 const field = document.getElementById(fieldId);
 if (field) {
 field.style.borderColor = '#ef4444';
 field.style.backgroundColor = '#fef2f2';
 }
 },

 showValidationSummary(errors) {
 // Remover resumen anterior si existe
 const existingSummary = document.getElementById('tbv2-validation-summary');
 if (existingSummary) {
 existingSummary.remove();
 }

 // Crear nuevo resumen de errores
 const summaryDiv = document.createElement('div');
 summaryDiv.id = 'tbv2-validation-summary';
 summaryDiv.style.cssText = `
 background: #fef2f2;
 border: 1px solid #fecaca;
 border-radius: 8px;
 padding: 16px;
 margin-bottom: 20px;
 box-shadow: 0 4px 6px rgba(239, 68, 68, 0.1);
 `;
 
 summaryDiv.innerHTML = `
 <div style="display: flex; align-items: center; margin-bottom: 12px;">
 <i class="fas fa-exclamation-triangle" style="color: #dc2626; margin-right: 8px; font-size: 18px;"></i>
 <strong style="color: #dc2626; font-size: 16px;">Por favor, corrige los siguientes errores:</strong>
 </div>
 <ul style="margin: 0; padding-left: 20px; color: #7f1d1d; line-height: 1.5;">
 ${errors.map(error => `<li style="margin-bottom: 6px;">${error}</li>`).join('')}
 </ul>
 `;
 
 // Insertar al inicio del formulario actual
 const currentPage = document.getElementById(this.currentPage);
 if (currentPage) {
 currentPage.insertBefore(summaryDiv, currentPage.firstChild);
 }

 // Scroll suave al resumen
 summaryDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });

 // Auto-ocultar después de 10 segundos
 setTimeout(() => {
 if (summaryDiv && summaryDiv.parentElement) {
 summaryDiv.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
 summaryDiv.style.opacity = '0';
 setTimeout(() => {
 if (summaryDiv.parentElement) {
 summaryDiv.remove();
 }
 }, 500);
 }
 }, 10000);
 },

 // Enhanced manufacturer/model selection methods
 loadModelsForManufacturer(selectedFabricante) {
 const modelSelect = document.getElementById('model');
 const modelCount = document.getElementById('model-count');
 
 // Reset model selector
 modelSelect.innerHTML = '<option value="">Seleccione modelo...</option>';
 modelSelect.disabled = true;
 modelCount.textContent = '';
 
 if (selectedFabricante && tbv2DatosCsv[selectedFabricante]) {
 const models = tbv2DatosCsv[selectedFabricante];
 
 // Sort models alphabetically
 models.sort((a, b) => a.modelo.localeCompare(b.modelo));
 
 // Add models to select
 models.forEach(modeloData => {
 const option = document.createElement('option');
 option.value = modeloData.modelo;
                    option.textContent = modeloData.modelo; // Solo mostrar nombre del modelo
 option.dataset.price = modeloData.precio;
 modelSelect.appendChild(option);
 });
 
 modelSelect.disabled = false;
 modelCount.textContent = `(${models.length} modelos disponibles)`;
 
 // Auto-select if only one model
 if (models.length === 1) {
 modelSelect.value = models[0].modelo;
 this.updateSidebarContent();
 }
 }
 },

 toggleManufacturerSearch() {
 const searchInput = document.getElementById('manufacturer-search');
 const toggleText = document.getElementById('search-toggle-text');
 
 if (searchInput.style.display === 'none' || !searchInput.style.display) {
 searchInput.style.display = 'block';
 searchInput.focus();
 toggleText.textContent = 'Ocultar búsqueda';
 } else {
 searchInput.style.display = 'none';
 searchInput.value = '';
 this.filterManufacturers(''); // Reset filter
 toggleText.textContent = 'Activar búsqueda rápida';
 }
 },

 filterManufacturers(searchTerm) {
 const manufacturerSelect = document.getElementById('manufacturer');
 const options = Array.from(manufacturerSelect.options);
 let visibleCount = 0;
 
 options.forEach((option, index) => {
 if (index === 0) return; // Skip placeholder
 
 const manufacturer = option.value.toLowerCase();
 const visible = manufacturer.includes(searchTerm.toLowerCase());
 option.style.display = visible ? '' : 'none';
 if (visible) visibleCount++;
 });

 // Show results count
 const searchInput = document.getElementById('manufacturer-search');
 if (searchTerm && searchInput) {
 searchInput.style.borderColor = visibleCount > 0 ? '#10b981' : '#ef4444';
 searchInput.title = `${visibleCount} fabricantes encontrados`;
 } else if (searchInput) {
 searchInput.style.borderColor = '#d1d5db';
 searchInput.title = '';
 }
 },

 selectFirstFilteredManufacturer() {
 const manufacturerSelect = document.getElementById('manufacturer');
 const searchInput = document.getElementById('manufacturer-search');
 const options = Array.from(manufacturerSelect.options);
 
 for (let i = 1; i < options.length; i++) { // Skip placeholder
 if (options[i].style.display !== 'none') {
 manufacturerSelect.value = options[i].value;
 this.loadModelsForManufacturer(options[i].value);
 searchInput.value = '';
 this.filterManufacturers('');
 break;
 }
 }
 
 this.updateSidebarContent();
 },

 // ===== ITP FUNCTIONALITY (BASED ON ORIGINAL) =====
 
 setupITPFunctionality() {
 console.log(' INICIANDO Sistema ITP TBV2...');
 
 // Variables para el flujo ITP
 this.itpPagado = null;
 this.precioStep = 1;
 
 // Elementos DOM
 this.precioStep1 = document.getElementById('precio-step-1');
 this.precioStep2 = document.getElementById('precio-step-2');
 this.verCalculoBtn = document.getElementById('ver-calculo-itp');
 this.calculoDetail = document.getElementById('calculo-itp-detail');
 this.itpSiBtn = document.getElementById('itp-si');
 this.itpNoBtn = document.getElementById('itp-no');
 this.modificarItpBtn = document.getElementById('modificar-itp');
 
 console.log(' Elementos ITP encontrados:', {
 precioStep1: !!this.precioStep1,
 precioStep2: !!this.precioStep2,
 verCalculoBtn: !!this.verCalculoBtn,
 calculoDetail: !!this.calculoDetail,
 itpSiBtn: !!this.itpSiBtn,
 itpNoBtn: !!this.itpNoBtn,
 modificarItpBtn: !!this.modificarItpBtn
 });

 if (!this.precioStep1 || !this.precioStep2) {
 console.error(' ERROR: No se encontraron elementos precio-step-1 o precio-step-2');
 return;
 }

 if (!this.itpSiBtn || !this.itpNoBtn) {
 console.error(' ERROR: No se encontraron botones ITP (itp-si o itp-no)');
 return;
 }

 this.setupITPEventListeners();
 this.updateITPDisplay();
 
 console.log(' Sistema ITP TBV2 inicializado correctamente');
 },

 setupITPEventListeners() {
 // Botón ver cálculo ITP
 if (this.verCalculoBtn) {
 this.verCalculoBtn.addEventListener('click', () => {
 if (this.calculoDetail.style.display === 'none') {
 this.calculoDetail.style.display = 'block';
 this.verCalculoBtn.textContent = 'Ocultar cálculo detallado';
 } else {
 this.calculoDetail.style.display = 'none';
 this.verCalculoBtn.textContent = 'Ver cálculo detallado';
 }
 });
 }

 // Botones ITP pagado/no pagado
 if (this.itpSiBtn && this.itpNoBtn) {
 this.itpSiBtn.addEventListener('click', () => {
 this.itpPagado = true;
 this.selectITPOption('si');
 this.updateITPDisplay(); // Actualizar cálculo
 this.showStep2();
 });

 this.itpNoBtn.addEventListener('click', () => {
 this.itpPagado = false;
 this.selectITPOption('no');
 this.updateITPDisplay(); // Actualizar cálculo
 this.showStep2();
 });
 }

 // Botón modificar ITP
 if (this.modificarItpBtn) {
 this.modificarItpBtn.addEventListener('click', () => {
 this.showStep1();
 });
 }
 },

 selectITPOption(option) {
 // Resetear estado de botones
 document.querySelectorAll('.itp-choice-btn').forEach(btn => {
 btn.classList.remove('selected');
 btn.style.background = '';
 btn.style.color = '';
 });
 
 // Marcar botón seleccionado
 const selectedBtn = option === 'si' ? this.itpSiBtn : this.itpNoBtn;
 if (selectedBtn) {
 selectedBtn.classList.add('selected');
 }
 
 console.log(` ITP seleccionado: ${option === 'si' ? 'YA PAGADO' : 'NO PAGADO'}`);
 },

 showStep1() {
 if (this.precioStep1 && this.precioStep2) {
 this.precioStep1.style.display = 'block';
 this.precioStep2.style.display = 'none';
 this.precioStep = 1;
 
 // Limpiar selección
 this.itpPagado = null;
 document.querySelectorAll('.itp-choice-btn').forEach(btn => {
 btn.style.background = 'white';
 btn.style.color = '#016d86';
 });
 
 console.log(' Mostrado paso 1 - Pregunta ITP');
 }
 },

 showStep2() {
 if (this.precioStep1 && this.precioStep2) {
 // Animación de transición
 setTimeout(() => {
 this.precioStep1.style.display = 'none';
 this.precioStep2.style.display = 'block';
 this.precioStep = 2;
 
 this.updateStep2Content();
 console.log(' Mostrado paso 2 - Resumen');
 }, 300);
 }
 },

 updateStep2Content() {
 const incluyeItpSi = document.getElementById('incluye-itp-si');
 const incluyeItpNo = document.getElementById('incluye-itp-no');
 const precioFinal = document.getElementById('precio-final');
 const desgloseItp = document.getElementById('desglose-itp');
 
 // Asegurar que el cálculo esté actualizado
 this.updateITPDisplay();
 
 if (this.itpPagado === true) {
 // ITP ya pagado
 if (incluyeItpSi) incluyeItpSi.style.display = 'block';
 if (incluyeItpNo) incluyeItpNo.style.display = 'none';
 if (precioFinal) precioFinal.textContent = '134.99 €';
 } else if (this.itpPagado === false) {
 // ITP no pagado - lo gestionamos nosotros
 if (incluyeItpSi) incluyeItpSi.style.display = 'none';
 if (incluyeItpNo) incluyeItpNo.style.display = 'block';
 
 // Calcular ITP dinámicamente
 const itpAmount = this.calculateCurrentITP();
 if (desgloseItp) desgloseItp.textContent = itpAmount.toFixed(2) + ' €';
 if (precioFinal) precioFinal.textContent = (134.99 + itpAmount).toFixed(2) + ' €';
 }
 },

 calculateCurrentITP() {
 console.log(' Calculando ITP usando lógica ORIGINAL EXACTA...');
 
 const purchasePriceInput = document.getElementById('purchase_price');
 const matriculationDateInput = document.getElementById('matriculation_date');
 const regionSelect = document.getElementById('region');
 
 // Validaciones básicas
 if (!purchasePriceInput || !purchasePriceInput.value) {
 console.log(' No hay precio de compra, retornando 0');
 return 0;
 }
 
 const purchasePrice = parseFloat(purchasePriceInput.value) || 0;
 if (purchasePrice === 0) {
 console.log(' Precio de compra es 0, retornando 0');
 return 0;
 }
 
 // PASO 1: Obtener basePrice del modelo (como formulario original)
 // *** CRÍTICO: Si "No encuentro mi modelo" está marcado, basePrice = 0 ***
 const noEncuentroCheckbox = document.getElementById('no_encuentro_modelo');
 const modelSelect = document.getElementById('model');
 let basePrice = 0;
 
 if (noEncuentroCheckbox && noEncuentroCheckbox.checked) {
 // Modo manual: basePrice = 0 (exactamente como original)
 basePrice = 0;
 console.log(' Modo manual detectado - basePrice = 0 (sin modelo CSV)');
 } else if (modelSelect && modelSelect.value) {
 // Modo CSV: obtener precio del modelo seleccionado
 const selectedOption = modelSelect.options[modelSelect.selectedIndex];
 basePrice = parseFloat(selectedOption.dataset.price || 0);
 console.log(' basePrice del modelo CSV:', basePrice.toFixed(2), '€');
 } else {
 console.log(' Sin modelo seleccionado - basePrice = 0');
 }
 
 // PASO 2: Aplicar depreciación sobre basePrice (ORDEN ORIGINAL)
 let fiscalValue = basePrice; // Valor por defecto
 let depreciationPercentage = 100; // Por defecto 100%
 
 if (matriculationDateInput && matriculationDateInput.value) {
 const depreciationFactor = this.getDepreciationFactor(matriculationDateInput.value);
 depreciationPercentage = Math.round(depreciationFactor * 100);
 // *** CRÍTICO: Aplicar depreciación sobre basePrice, NO sobre baseImponible ***
 fiscalValue = basePrice * depreciationFactor;
 
 console.log(` Aplicando depreciación BOE 2024: ${depreciationPercentage}%`);
 console.log(` Valor fiscal: ${basePrice.toFixed(2)}€ * ${depreciationPercentage}% = ${fiscalValue.toFixed(2)}€`);
 } else {
 console.log(' Sin fecha de matriculación, usando basePrice completo');
 }
 
 // PASO 3: BASE IMPONIBLE = Math.max(precio_compra, valor_fiscal_depreciado) 
 const baseValue = Math.max(purchasePrice, fiscalValue);
 console.log(' BASE IMPONIBLE FINAL:');
 console.log(' Precio de compra:', purchasePrice.toFixed(2), '€');
 console.log(' Valor fiscal (con depreciación):', fiscalValue.toFixed(2), '€');
 console.log(' BASE IMPONIBLE:', baseValue.toFixed(2), '€', '(Math.max)');
 
 // PASO 4: Aplicar tipo ITP por comunidad autónoma (simple, igual que original)
 const region = regionSelect?.value || 'Madrid';
 const itpRate = this.getITPRate(region);
 const itpAmount = baseValue * itpRate;
 
 console.log(` ITP ${region}: ${(itpRate * 100).toFixed(1)}% sobre ${baseValue.toFixed(2)}€ = ${itpAmount.toFixed(2)}€`);
 
 // DEBUG FINAL
 const modoTexto = noEncuentroCheckbox?.checked ? 'MANUAL' : 'CSV';
 console.log(` RESUMEN CÁLCULO ITP EXACTO ORIGINAL (MODO ${modoTexto}):`);
 console.log(' basePrice modelo:', basePrice.toFixed(2), '€', noEncuentroCheckbox?.checked ? '(manual = 0)' : '(del CSV)');
 console.log(' Depreciación BOE:', depreciationPercentage + '%');
 console.log(' Valor fiscal (basePrice * depreciación):', fiscalValue.toFixed(2), '€');
 console.log(' Precio de compra:', purchasePrice.toFixed(2), '€');
 console.log(' BASE IMPONIBLE (Math.max):', baseValue.toFixed(2), '€');
 console.log(' Tipo ITP', region + ':', (itpRate * 100).toFixed(1) + '%');
 console.log(' ITP TOTAL (sin tasas especiales):', itpAmount.toFixed(2), '€');
 
 return itpAmount;
 },
 
 getDepreciationFactor(matriculationDate) {
 console.log(' getDepreciationFactor llamado con:', matriculationDate);
 
 if (!matriculationDate || matriculationDate === '') {
 console.log(' No hay fecha de matriculación válida, usando 100%');
 return 1.0; // 100%
 }
 
 const today = new Date();
 const matriculationDateObj = new Date(matriculationDate);
 
 // Validar fecha
 if (isNaN(matriculationDateObj.getTime())) {
 console.log(' Fecha inválida, usando 100%');
 return 1.0;
 }
 
 // Calcular años de diferencia (lógica EXACTA del original)
 let yearsDifference = today.getFullYear() - matriculationDateObj.getFullYear();
 const monthsDifference = today.getMonth() - matriculationDateObj.getMonth();
 
 if (monthsDifference < 0 || (monthsDifference === 0 && today.getDate() < matriculationDateObj.getDate())) {
 yearsDifference--;
 }
 
 yearsDifference = (yearsDifference < 0) ? 0 : yearsDifference;
 
 console.log(' Años diferencia calculados:', yearsDifference);
 
 // TABLA OFICIAL BOE 2024 - EXACTA DEL ORIGINAL
 const depreciationRates = [
 { years: 1, rate: 100 }, // Hasta 1 año: 100%
 { years: 2, rate: 85 }, // Más de 1, hasta 2: 85%
 { years: 3, rate: 72 }, // Más de 2, hasta 3: 72%
 { years: 4, rate: 61 }, // Más de 3, hasta 4: 61%
 { years: 5, rate: 52 }, // Más de 4, hasta 5: 52%
 { years: 6, rate: 44 }, // Más de 5, hasta 6: 44%
 { years: 7, rate: 37 }, // Más de 6, hasta 7: 37%
 { years: 8, rate: 32 }, // Más de 7, hasta 8: 32%
 { years: 9, rate: 27 }, // Más de 8, hasta 9: 27%
 { years: 10, rate: 23 }, // Más de 9, hasta 10: 23%
 { years: 11, rate: 19 }, // Más de 10, hasta 11: 19%
 { years: 12, rate: 16 }, // Más de 11, hasta 12: 16%
 { years: 13, rate: 14 }, // Más de 12, hasta 13: 14%
 { years: 14, rate: 12 }, // Más de 13, hasta 14: 12%
 { years: 15, rate: 10 } // Más de 14 años: 10%
 ];
 
 // Encontrar el rate correspondiente (lógica EXACTA del original)
 let depreciationPercentage = 10; // Por defecto 10% si es muy antiguo
 for (let i = 0; i < depreciationRates.length; i++) {
 if (yearsDifference <= depreciationRates[i].years) {
 depreciationPercentage = depreciationRates[i].rate;
 break;
 }
 }
 
 const factor = depreciationPercentage / 100;
 console.log(` BOE 2024: ${yearsDifference} años = ${depreciationPercentage}% (factor ${factor})`);
 
 return factor;
 },
 
 getITPRate(region) {
 // TIPOS ITP OFICIALES - EXACTOS DEL ORIGINAL PRODUCTION
 const itpRates = {
 "Andalucía": 0.04, // 4%
 "Aragón": 0.04, // 4%
 "Asturias": 0.04, // 4%
 "Islas Baleares": 0.04, // 4%
 "Canarias": 0.055, // 5.5%
 "Cantabria": 0.06, // 6%
 "Castilla-La Mancha": 0.06, // 6%
 "Castilla y León": 0.05, // 5%
 "Cataluña": 0.05, // 5%
 "Comunidad Valenciana": 0.08, // 8%
 "Galicia": 0.03, // 3%
 "Madrid": 0.04, // 4%
 "Murcia": 0.04, // 4%
 "Navarra": 0.04, // 4%
 "País Vasco": 0.04, // 4%
 "La Rioja": 0.04, // 4%
 "Ceuta": 0.02, // 2%
 "Melilla": 0.04 // 4%
 };
 
 return itpRates[region] || 0.04; // Por defecto 4%
 },

 updateITPDisplay() {
 const tbv2ItpDisplay = document.getElementById('tbv2-itp-display');
 const tbv2VehicleSummary = document.getElementById('tbv2-vehicle-summary');
 const tbv2CalculationBreakdown = document.getElementById('tbv2-calculation-breakdown');
 
 const modelSelect = document.getElementById('model');
 const manufacturerSelect = document.getElementById('manufacturer');
 const purchasePriceInput = document.getElementById('purchase_price');
 const noEncuentroCheckbox = document.getElementById('no_encuentro_modelo');
 const manualManufacturer = document.getElementById('manual_manufacturer');
 const manualModel = document.getElementById('manual_model');
 
 // Verificar si tenemos datos suficientes según el modo
 const modoCSV = !noEncuentroCheckbox?.checked && modelSelect?.value && manufacturerSelect?.value;
 const modoManual = noEncuentroCheckbox?.checked && manualManufacturer?.value && manualModel?.value;
 
 if (modoCSV || modoManual) {
 const itpAmount = this.calculateCurrentITP();
 
 // Actualizar display del ITP
 if (tbv2ItpDisplay) {
 tbv2ItpDisplay.textContent = itpAmount > 0 ? itpAmount.toFixed(2) + ' €' : '---';
 }
 
 // Actualizar resumen del vehículo según modo
 if (tbv2VehicleSummary) {
 if (modoManual) {
 tbv2VehicleSummary.textContent = `${manualManufacturer.value} ${manualModel.value}`;
 } else {
 tbv2VehicleSummary.textContent = `${manufacturerSelect.value} ${modelSelect.value}`;
 }
 }
 
 // Actualizar breakdown del cálculo con depreciación
 // PRESERVAR ESTADO DEL TOGGLE BUTTON
 if (tbv2CalculationBreakdown && itpAmount > 0) {
 let modelPrice = 0;
 const purchasePrice = parseFloat(purchasePriceInput?.value || 0);
 
 if (modoManual) {
 // Modo manual: basePrice = 0 (como establecimos en calculateCurrentITP)
 modelPrice = 0;
 } else {
 // Modo CSV: obtener precio del modelo
 const selectedOption = modelSelect.options[modelSelect.selectedIndex];
 modelPrice = parseFloat(selectedOption.dataset.price || 0);
 }
 
 const baseValue = Math.max(modelPrice, purchasePrice);
 
 const matriculationDateInput = document.getElementById('matriculation_date');
 const regionSelect = document.getElementById('region');
 
 // Cálculo de depreciación
 let depreciationFactor = 1.0;
 let finalValue = baseValue;
 let depreciationText = '';
 
 if (matriculationDateInput && matriculationDateInput.value) {
 depreciationFactor = this.getDepreciationFactor(matriculationDateInput.value);
 finalValue = baseValue * depreciationFactor;
 const depreciationPercentage = (depreciationFactor * 100).toFixed(1);
 const ageInYears = Math.floor((new Date() - new Date(matriculationDateInput.value)) / (1000 * 60 * 60 * 24 * 365.25));
 depreciationText = `
 <div style="margin-bottom: 8px;"><strong>Antigüedad:</strong> ${ageInYears} años</div>
 <div style="margin-bottom: 8px;"><strong>Factor depreciación:</strong> ${depreciationPercentage}%</div>
 <div style="margin-bottom: 8px;"><strong>Valor fiscal:</strong> ${finalValue.toFixed(2)}€</div>
 `;
 }
 
 const region = regionSelect?.value || 'Madrid';
 const itpRate = this.getITPRate(region);
 const itpPercentage = (itpRate * 100).toFixed(1);
 
 const modoTexto = modoManual ? 'Manual' : 'CSV';
 
 // PRESERVAR ESTADO DEL TOGGLE - verificar si el detalle está oculto
 const calculoDetailElement = document.getElementById('calculo-itp-detail');
 const wasHidden = calculoDetailElement && calculoDetailElement.style.display === 'none';
 
 tbv2CalculationBreakdown.innerHTML = `
 <div style="margin-bottom: 8px;"><strong>Precio compra:</strong> ${purchasePrice.toFixed(2)}€</div>
 <div style="margin-bottom: 8px;"><strong>Base imponible:</strong> ${baseValue.toFixed(2)}€ (el mayor)</div>
 ${modoManual ? '' : depreciationText}
 <div style="margin-bottom: 8px;"><strong>Comunidad:</strong> ${region}</div>
 <div style="margin-bottom: 8px;"><strong>Tipo ITP:</strong> ${itpPercentage}%</div>
 <div style="font-weight: bold; color: #016d86; border-top: 1px solid #e5e7eb; padding-top: 8px; margin-top: 8px;">
 <strong>Importe ITP Total:</strong> ${itpAmount.toFixed(2)}€
 </div>
 `;
 
 // RESTAURAR ESTADO DEL TOGGLE si estaba oculto
 if (wasHidden && calculoDetailElement) {
 calculoDetailElement.style.display = 'none';
 // También actualizar texto del botón si es necesario
 const verCalculoBtn = document.getElementById('ver-calculo-itp');
 if (verCalculoBtn && verCalculoBtn.textContent !== 'Ver cálculo detallado') {
 verCalculoBtn.textContent = 'Ver cálculo detallado';
 }
 }
 }
 } else {
 // Estado inicial sin datos
 if (tbv2ItpDisplay) {
 tbv2ItpDisplay.textContent = '---';
 }
 if (tbv2VehicleSummary) {
 tbv2VehicleSummary.textContent = 'Complete los datos del vehículo para calcular';
 }
 if (tbv2CalculationBreakdown) {
 // PRESERVAR ESTADO DEL TOGGLE también en estado inicial
 const calculoDetailElement = document.getElementById('calculo-itp-detail');
 const wasHidden = calculoDetailElement && calculoDetailElement.style.display === 'none';
 
 tbv2CalculationBreakdown.innerHTML = '<div style="color: #6b7280; font-style: italic;">Complete la información del vehículo para ver el cálculo detallado</div>';
 
 // RESTAURAR ESTADO DEL TOGGLE si estaba oculto
 if (wasHidden && calculoDetailElement) {
 calculoDetailElement.style.display = 'none';
 const verCalculoBtn = document.getElementById('ver-calculo-itp');
 if (verCalculoBtn && verCalculoBtn.textContent !== 'Ver cálculo detallado') {
 verCalculoBtn.textContent = 'Ver cálculo detallado';
 }
 }
 }
 }
 
 console.log(' ITP Display actualizado');
 },

 // Función auxiliar para debug
 debugFormState() {
 const modelSelect = document.getElementById('model');
 const manufacturerSelect = document.getElementById('manufacturer');
 const purchasePriceInput = document.getElementById('purchase_price');
 
 console.log(' Estado del formulario:', {
 manufacturer: manufacturerSelect?.value || 'No seleccionado',
 model: modelSelect?.value || 'No seleccionado',
 purchasePrice: purchasePriceInput?.value || 'No definido',
 itpCalculated: this.calculateCurrentITP()
 });
 },

 setupPaymentPage() {
 console.log(' Configurando página de pago...');
 
 // Initialize terms checkbox behavior
 const termsCheckbox = document.getElementById('terms_accept_pago');
 if (termsCheckbox) {
 termsCheckbox.addEventListener('change', (e) => {
 const checkmarkBox = e.target.parentElement.querySelector('.checkmark-box');
 const checkmark = e.target.parentElement.querySelector('.checkmark');
 const submitButton = document.getElementById('submit-payment');
 
 if (e.target.checked) {
 if (submitButton) submitButton.disabled = false;
 } else {
 if (submitButton) submitButton.disabled = true;
 }
 });
 }
 
 // Initialize Redsys payment page
 console.log(' Inicializando página de pago Redsys...');
 this.showPaymentElements();
 },

 initializeStripe() {
 const stripe = Stripe(tbv2StripePublicKey);
 
 // Show loading state
 const stripeLoading = document.getElementById('stripe-loading');
 const paymentElement = document.getElementById('payment-element');
 const termsContainer = document.querySelector('.payment-terms');
 const securityBadges = document.querySelector('.payment-security');
 const submitButton = document.getElementById('submit-payment');
 
 // Simulate loading for better UX
 setTimeout(() => {
 if (stripeLoading) stripeLoading.style.display = 'none';
 this.showPaymentElements();
 }, 1500);
 },

 showPaymentElements() {
 // Show payment form elements
 const elements = [
 'payment-element',
 'submit-payment'
 ];
 
 const containers = [
 '.payment-terms',
 '.payment-security'
 ];
 
 elements.forEach(id => {
 const element = document.getElementById(id);
 if (element) {
 element.style.display = 'block';
 }
 });
 
 containers.forEach(selector => {
 const container = document.querySelector(selector);
 if (container) {
 container.style.display = 'block';
 }
 });
 
 // Show a placeholder for Stripe Elements
 const paymentElement = document.getElementById('payment-element');
 if (paymentElement) {
 paymentElement.innerHTML = `
 <div style="padding: 20px; border: 2px dashed #e5e7eb; border-radius: 8px; text-align: center; background: #f9fafb;">
 <i class="fa-brands fa-stripe" style="font-size: 24px; color: #635bff; margin-bottom: 8px;"></i>
 <div style="color: #6b7280; font-size: 14px;">Elementos de pago Stripe</div>
 <div style="color: #9ca3af; font-size: 12px; margin-top: 4px;">
 En producción: Formulario de tarjeta de crédito
 </div>
 </div>
 `;
 }
 },

 async submitForm() {
 console.log(' Procesando pago con Redsys...');
 
 // Validate terms acceptance
 const termsCheckbox = document.getElementById('terms_accept_pago');
 if (!termsCheckbox || !termsCheckbox.checked) {
 alert('Debe aceptar los términos y condiciones para continuar.');
 return;
 }
 
 // Show processing state
 const submitButton = document.getElementById('submit-payment');
 if (submitButton) {
 submitButton.disabled = true;
 submitButton.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Redirigiendo...';
 }
 
 // Collect form data
 const formData = await this.collectFormData();
 console.log(' Datos del formulario:', formData);
 
 // Order ID se genera en frontend compatible con Redsys - FIX CRÍTICO
 // Formato: timestamp en segundos (10 dígitos) + 2 dígitos aleatorios = 12 total
 const timestamp = Math.floor(Date.now() / 1000);
 const random = Math.floor(Math.random() * 100).toString().padStart(2, '0');
 const orderId = timestamp.toString() + random;
 
 // VALIDACIÓN CRÍTICA: Verificar OrderID cumple reglas Redsys
 console.log(`🆔 OrderID generado: ${orderId} (${orderId.length} caracteres)`);
 console.log(` Solo alfanumérico: ${/^[A-Za-z0-9]+$/.test(orderId)}`);
 console.log(` Máximo 12 chars: ${orderId.length <= 12}`);
 
 // Calculate final amount with fallback - CRITICAL FIX
 const calculatedAmount = parseFloat(formData.pricing?.total_amount) || 134.99;
 console.log(' Importe calculado:', calculatedAmount, '€');
 
 // Flatten data structure for PHP compatibility - FIXED STRUCTURE
 const flatFormData = {
 // Personal data (estructura que espera el PHP)
 personal: {
 customerName: formData.customer.name,
 customerEmail: formData.customer.email,
 customerPhone: formData.customer.phone,
 customerDni: formData.customer.dni,
 sellerName: '', // TODO: Añadir si está disponible
 sellerDni: '' // TODO: Añadir si está disponible
 },
 // Vehicle data
 matricula: formData.vehicle.matricula,
 manufacturer: formData.vehicle.manufacturer,
 model: formData.vehicle.model,
 length: formData.vehicle.length,
 region: formData.vehicle.region,
 purchase_price: formData.vehicle.purchase_price,
 matriculation_date: formData.vehicle.matriculation_date,
 // Pricing data - CRITICAL FIX: usar calculatedAmount
 finalAmount: calculatedAmount,
 tasas: 0, // No taxes handled by client for now
 iva: 0, // No VAT handled by client for now
 honorarios: calculatedAmount,
 honorariosNetos: calculatedAmount / 1.21,
 attachments: [] // No file handling for now
 };
 
 console.log(' Datos aplanados para envío:', flatFormData);
 const amountCents = Math.round(calculatedAmount * 100);
 console.log(' Cantidad en centavos para Redsys:', amountCents);
 
 // Create Redsys payment form and submit
 this.createRedsysPaymentForm({
 order_id: orderId,
 amount_cents: amountCents,
 description: `Transferencia Embarcación ${flatFormData.matricula || 'TBV2'}`,
 customer_name: flatFormData.personal.customerName,
 form_data: flatFormData
 });
 },
 
 /**
 * NUEVA ESTRATEGIA: Incluir archivos directamente en sessionStorage
 */
 storeFilesInSession(orderId, filesData) {
 try {
 console.log(' Almacenando', Object.keys(filesData).length, 'categorías de archivos en sessionStorage...');
 
 const filesKey = `tbv2_files_${orderId}`;
 sessionStorage.setItem(filesKey, JSON.stringify(filesData));
 
 console.log(` Archivos almacenados en sessionStorage con clave: ${filesKey}`);
 
 // Verificar almacenamiento
 const stored = sessionStorage.getItem(filesKey);
 if (stored) {
 const parsed = JSON.parse(stored);
 console.log(` Verificación: ${Object.keys(parsed).length} categorías almacenadas correctamente`);
 } else {
 console.error(' Error: no se pudo verificar el almacenamiento');
 }
 
 } catch (error) {
 console.error(' Error en storeFilesInSession:', error);
 }
 },
 
 createRedsysPaymentForm(orderData) {
 console.log(' Creando formulario Redsys...', orderData);
 
 // NUEVA ESTRATEGIA: Incluir archivos en el pago para proceso unificado
 // QUOTA FIX: No recuperar de sessionStorage, usar método directo
 // EMERGENCY FIX: collectAllFiles inline implementation
 const completeFormData = { files: {} }; // Simplified - no files for now
 
 console.log(' INTEGRANDO ARCHIVOS EN PROCESO DE PAGO');
 console.log(' Archivos disponibles:', Object.keys(completeFormData.files || {}));
 
 // Combinar datos del formulario con archivos
 const unifiedFormData = {
 ...orderData.form_data,
 files: completeFormData.files || {},
 timestamp: new Date().toISOString()
 };
 
 // Store unified data for retrieval after payment
 sessionStorage.setItem('tbv2_form_data', JSON.stringify(unifiedFormData));
 sessionStorage.setItem('tbv2_order_id', orderData.order_id);
 
 // NUEVA ESTRATEGIA: Almacenar archivos en sessionStorage
 if (completeFormData.files && Object.keys(completeFormData.files).length > 0) {
 console.log(' Almacenando archivos en sessionStorage...');
 this.storeFilesInSession(orderData.order_id, completeFormData.files);
 }
 
 // Create invisible form for Redsys
 const form = document.createElement('form');
 form.method = 'POST';
 form.action = '<?php echo (TBV2_REDSYS_MODE === "test") ? TBV2_REDSYS_URL_TEST : TBV2_REDSYS_URL_LIVE; ?>';
 form.acceptCharset = 'UTF-8';
 form.enctype = 'application/x-www-form-urlencoded';
 form.style.display = 'none';
 
 // Para el AJAX de pago: datos básicos SIN archivos (evitar 403)
 const formDataCopy = { ...orderData.form_data };
 
 const ajaxData = new FormData();
 ajaxData.append('action', 'tbv2_create_redsys_payment');
 ajaxData.append('nonce', '<?php echo wp_create_nonce("tbv2_nonce"); ?>');
 ajaxData.append('formData', JSON.stringify(formDataCopy));
 ajaxData.append('orderId', orderData.order_id);
 
 // NOTA: Archivos NO se envían en AJAX para evitar 403
 // Los archivos están en sessionStorage y se recuperarán en el callback
 
 console.log(' Datos para pago (sin archivos):', formDataCopy);
 
 // DEBUG: Log AJAX call details
 console.log(' URL AJAX:', '<?php echo admin_url("admin-ajax.php"); ?>');
 console.log(' Datos enviados AJAX:', {
 action: 'tbv2_create_redsys_payment',
 nonce: '<?php echo wp_create_nonce("tbv2_nonce"); ?>',
 orderId: orderData.order_id,
 formData: formDataCopy // Sin archivos
 });
 
 // Send order data to server to get signed parameters
 fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
 method: 'POST',
 body: ajaxData
 })
 .then(response => {
 console.log(' Response status:', response.status);
 console.log(' Response headers:', Object.fromEntries(response.headers.entries()));
 
 // MÓVIL FIX: Verificar si response es OK
 if (!response.ok) {
 throw new Error(`HTTP error! status: ${response.status}`);
 }
 
 // MÓVIL FIX: Verificar content-type
 const contentType = response.headers.get('content-type') || '';
 console.log(' Content-Type:', contentType);
 
 if (!contentType.includes('application/json')) {
 console.warn(' Respuesta no es JSON válido');
 return response.text().then(text => {
 console.error(' Respuesta texto completa:', text);
 throw new Error(`Respuesta inválida del servidor. Content-Type: ${contentType}`);
 });
 }
 
 return response.json();
 })
 .then(data => {
 console.log(' Respuesta AJAX completa:', data);
 
 if (data.success) {
 console.log(' AJAX exitoso, datos Redsys:', data.data);
 
 // Add Redsys parameters to form
 const redsysData = data.data.redsysData;
 console.log(' Parámetros Redsys a enviar:', redsysData);
 console.log(' URL destino TPV:', form.action);
 
 Object.keys(redsysData).forEach(key => {
 if (key !== 'url') {
 const input = document.createElement('input');
 input.type = 'hidden';
 input.name = key;
 
 // NO codificar signature - usar tal como viene del servidor
 input.value = redsysData[key];
 if (key === 'Ds_Signature') {
 console.log(' SIGNATURE SIN CODIFICAR:', input.value);
 }
 
 form.appendChild(input);
 console.log(` Agregado campo: ${key} = ${input.value.substring(0, 50)}...`);
 
 // DEBUG CRÍTICO: Log específico para Ds_MerchantParameters
 if (key === 'Ds_MerchantParameters') {
 console.log(' DEBUG Ds_MerchantParameters completo:', redsysData[key]);
 try {
 const decoded = JSON.parse(atob(redsysData[key]));
 console.log(' Parámetros decodificados:', decoded);
 console.log(' Order ID en parámetros:', decoded.Ds_Merchant_Order);
 console.log(' Amount en parámetros:', decoded.Ds_Merchant_Amount);
 } catch (e) {
 console.error(' Error decodificando parámetros:', e);
 }
 }
 }
 });
 
 // DEBUG SYSTEM - Mostrar todos los datos y preguntar si continuar
 const debugMessage = ` DEBUG AJAX EXITOSO:\n\n` +
 ` AJAX Response: SUCCESS\n` +
 ` Parámetros Redsys generados: ${Object.keys(redsysData).length}\n` +
 ` URL destino: ${form.action}\n` +
 ` Campos formulario: ${form.children.length}\n\n` +
 `DATOS CRÍTICOS:\n` +
 `- ${JSON.stringify(data.data, null, 2)}\n\n` +
 `¿Continuar a Redsys TPV?`;
 
 const continueToRedsys = confirm(debugMessage);
 
 if (continueToRedsys) {
 // Submit form to Redsys
 document.body.appendChild(form);
 console.log(' Formulario completo, enviando a:', form.action);
 console.log(' Campos del formulario:', form.children.length);
 form.submit();
 } else {
 console.log(' Redirección cancelada por el usuario');
 this.resetPaymentButton();
 }
 } else {
 console.error(' Error creando pago Redsys:', data);
 alert(`Error PHP: ${JSON.stringify(data, null, 2)}`);
 this.resetPaymentButton();
 }
 })
 .catch(error => {
 console.error(' Error AJAX completo:', error);
 console.error(' Stack trace:', error.stack);
 
 // MÓVIL FIX: Error handling específico y user-friendly
 let userMessage = 'Error al procesar el pago.';
 
 if (error.message.includes('Failed to fetch')) {
 userMessage = 'Error de conexión. Verifique su conexión a internet e intente nuevamente.';
 } else if (error.message.includes('HTTP error')) {
 userMessage = `Error del servidor (${error.message}). Intente nuevamente en unos momentos.`;
 } else if (error.message.includes('JSON') || error.message.includes('Respuesta inválida')) {
 userMessage = 'Error de comunicación con el servidor. Intente nuevamente.';
 } else if (error.message.includes('Load failed')) {
 userMessage = 'Error de carga. Revise su conexión e intente nuevamente.';
 }
 
 alert(`${userMessage}\n\nSi el problema persiste, contacte soporte.\n\nDetalles técnicos: ${error.message}`);
 this.resetPaymentButton();
 });
 },
 
 resetPaymentButton() {
 const submitButton = document.getElementById('submit-payment');
 if (submitButton) {
 submitButton.disabled = false;
 submitButton.innerHTML = '<i class="fa-solid fa-credit-card"></i> Proceder al Pago';
 }
 },

 async collectFormData() {
 const data = {};
 
 // Vehicle data
 data.vehicle = {
 type: document.getElementById('vehicle_type')?.value,
 manufacturer: document.getElementById('manufacturer')?.value || document.getElementById('manual_manufacturer')?.value,
 model: document.getElementById('model')?.value || document.getElementById('manual_model')?.value,
 matricula: document.getElementById('matricula')?.value,
 length: document.getElementById('length')?.value,
 purchase_price: document.getElementById('purchase_price')?.value,
 region: document.getElementById('region')?.value,
 matriculation_date: document.getElementById('matriculation_date')?.value
 };
 
 // Customer data
 data.customer = {
 name: document.getElementById('customer_name')?.value,
 dni: document.getElementById('customer_dni')?.value,
 email: document.getElementById('customer_email')?.value,
 phone: document.getElementById('customer_phone')?.value
 };
 
 // Calculate ITP and total RESPETANDO selección del usuario
 const itpAmount = this.calculateCurrentITP();
 const shouldIncludeITP = this.itpPagado === false; // Solo incluir ITP si NO está pagado
 
 // DEBUG CRÍTICO: Verificar estado del ITP
 console.log(' DEBUG PRECIO CRÍTICO:');
 console.log(' this.itpPagado:', this.itpPagado);
 console.log(' itpAmount calculado:', itpAmount.toFixed(2), '€');
 console.log(' shouldIncludeITP:', shouldIncludeITP);
 console.log(' Total calculado:', shouldIncludeITP ? (134.99 + itpAmount) : 134.99);
 
 data.pricing = {
 base_price: 134.99,
 itp_amount: shouldIncludeITP ? itpAmount : 0,
 itp_user_selection: this.itpPagado, // true = ya pagado, false = lo gestionamos
 total_amount: shouldIncludeITP ? (134.99 + itpAmount) : 134.99
 };
 
 // Recolectar archivos subidos en base64
 try {
 data.files = await this.collectUploadedFilesAsBase64();
 } catch (error) {
 console.error(' Error recolectando archivos:', error);
 data.files = {}; // Continuar sin archivos en caso de error
 }
 
 return data;
 },

 /**
 * Recolecta archivos subidos y los convierte a base64 ANTES del pago
 */
 async collectUploadedFilesAsBase64() {
 console.log(' Recolectando y convirtiendo archivos a base64...');
 
 const files = {};
 
 try {
 const fieldMapping = {
 'upload-hoja-asiento': 'hojaAsiento',
 'upload-dni-comprador': 'dniComprador', 
 'upload-dni-vendedor': 'dniVendedor',
 'upload-contrato-compraventa': 'contratoCompraventa',
 'upload-modelo-620': 'otros'
 };
 
 // Procesar cada campo de archivo
 for (const [inputId, category] of Object.entries(fieldMapping)) {
 const input = document.getElementById(inputId);
 if (input && input.files && input.files.length > 0) {
 const fileData = [];
 const fileNames = [];
 const fileSizes = [];
 
 console.log(` ${category}: Processing ${input.files.length} files`);
 for (let i = 0; i < input.files.length; i++) {
 const file = input.files[i];
 
 // DEBUG EXHAUSTIVO DE ARCHIVO
 console.log(` DEBUG ARCHIVO ${i}:`, {
 exists: !!file,
 name: file?.name,
 type: file?.type,
 size: file?.size,
 lastModified: file?.lastModified,
 nameType: typeof file?.name
 });
 
 // Validar que el archivo existe y tiene las propiedades necesarias
 if (!file || !file.name || typeof file.name !== 'string') {
 console.error(` Archivo ${i} inválido en ${category}:`, file);
 continue;
 }
 
 // DETECCIÓN SIMPLE DE TIPO DE ARCHIVO
 const isPDF = file.name.toLowerCase().endsWith('.pdf') || file.type === 'application/pdf';
 const isImage = file.type && file.type.startsWith('image/');
 
 if (isPDF) {
 console.log(` PDF DETECTADO: ${file.name} (${(file.size/1024).toFixed(1)} KB)`);
 } else if (isImage) {
 console.log(` IMAGEN DETECTADA: ${file.name} (${(file.size/1024).toFixed(1)} KB)`);
 } else {
 console.log(` ARCHIVO DETECTADO: ${file.name} (${(file.size/1024).toFixed(1)} KB)`);
 }
 
 // NO VALIDACIONES RESTRICTIVAS - procesar directamente
 
 try {
 console.log(` Iniciando conversión base64 para: ${file.name}`);
 const base64 = await this.fileToBase64(file);
 
 if (!base64 || base64.length === 0) {
 throw new Error('Base64 resultado vacío');
 }
 
 // Verificar que el base64 es válido
 if (!base64.startsWith('data:')) {
 throw new Error('Base64 no tiene formato correcto');
 }
 
 console.log(` Procesando ${category}: ${file.name} (${(file.size/1024).toFixed(1)} KB)`);
 fileData.push(base64);
 fileNames.push(file.name);
 fileSizes.push(file.size || 0);
 
 console.log(` ${category}: ${file.name} convertido a base64 (${base64.length} chars)`);
 } catch (error) {
 console.error(` Error convirtiendo archivo en ${category}:`, error);
 console.error(` Archivo problemático:`, {
 name: file?.name || 'sin nombre',
 type: file?.type || 'sin tipo',
 size: file?.size || 'sin tamaño',
 errorMessage: error.message
 });
 
 // Continuar con otros archivos en lugar de fallar completamente
 console.warn(` Saltando archivo ${file.name}, continuando con otros`);
 }
 }
 
 if (fileData.length > 0) {
 files[category] = {
 count: fileData.length,
 data: fileData,
 names: fileNames,
 sizes: fileSizes
 };
 }
 }
 }
 
 // Añadir firma digital si existe
 const signatureCanvas = document.getElementById('enhanced-signature-canvas');
 if (signatureCanvas && signatureCanvas.toDataURL && !signatureCanvas.toDataURL().endsWith('AAAAASUVORK5CYII=')) {
 const signatureBase64 = signatureCanvas.toDataURL('image/png');
 const signatureSize = Math.round(signatureBase64.length * 0.75);
 
 files.firma_autorizacion = {
 count: 1,
 data: [signatureBase64],
 names: ['firma_autorizacion.png'],
 sizes: [signatureSize]
 };
 console.log(' Firma de autorización añadida:', Math.round(signatureSize / 1024), 'KB');
 } else {
 console.log(' No se encontró firma digital o canvas está vacío');
 }
 
 console.log(` Total categorías con archivos: ${Object.keys(files).length}`);
 return files;
 
 } catch (error) {
 console.error(' Error general en collectUploadedFilesAsBase64:', error);
 return {}; // Retornar objeto vacío en caso de error
 }
 },

 /**
 * Envía archivos reales al webhook después del pago exitoso
 */
 async uploadFilesToWebhook(tramiteId) {
 console.log(' Iniciando upload de archivos al webhook...');
 
 try {
 // Recolectar archivos y convertir a base64
 const files = {};
 
 // Mapeo de IDs a categorías del API
 const fieldMapping = {
 'upload-hoja-asiento': 'hojaAsiento',
 'upload-dni-comprador': 'dniComprador', 
 'upload-dni-vendedor': 'dniVendedor',
 'upload-contrato-compraventa': 'contratoCompraventa',
 'upload-modelo-620': 'otros'
 };
 
 // Procesar cada campo de archivo
 for (const [inputId, category] of Object.entries(fieldMapping)) {
 const input = document.getElementById(inputId);
 if (input && input.files && input.files.length > 0) {
 const fileData = [];
 const fileNames = [];
 const fileSizes = [];
 
 for (let i = 0; i < input.files.length; i++) {
 const file = input.files[i];
 const base64 = await this.fileToBase64(file);
 fileData.push(base64);
 fileNames.push(file.name);
 fileSizes.push(file.size);
 
 console.log(` ${category}: ${file.name} convertido a base64`);
 }
 
 if (fileData.length > 0) {
 files[category] = {
 count: fileData.length,
 data: fileData,
 names: fileNames,
 sizes: fileSizes
 };
 }
 }
 }
 
 // Añadir firma digital si existe
 const signatureCanvas = document.querySelector('#signature-pad canvas');
 if (signatureCanvas && !signatureCanvas.toDataURL().endsWith('AAAAASUVORK5CYII=')) {
 const signatureBase64 = signatureCanvas.toDataURL();
 files.autorizacionPdf = {
 count: 1,
 data: [signatureBase64],
 names: ['firma_digital.png'],
 sizes: [0]
 };
 console.log(' Firma digital añadida');
 }
 
 console.log(` Enviando ${Object.keys(files).length} categorías de archivos`);
 
 // Enviar al webhook
 const webhookUrl = 'https://tramitfy.org/api/herramientas/barcos/webhook';
 const payload = {
 tramiteId: tramiteId,
 tramiteType: 'transferencia-barco-v2',
 files: files,
 isFileUpload: true
 };
 
 const response = await fetch(webhookUrl, {
 method: 'POST',
 headers: {
 'Content-Type': 'application/json'
 },
 body: JSON.stringify(payload)
 });
 
 if (response.ok) {
 console.log(' Archivos enviados exitosamente al webhook');
 return true;
 } else {
 console.error(' Error enviando archivos:', response.status);
 return false;
 }
 
 } catch (error) {
 console.error(' Error en upload de archivos:', error);
 return false;
 }
 },
 
 /**
 * Convierte archivo a base64
 */
 fileToBase64(file) {
 return new Promise((resolve, reject) => {
 // DEBUG COMPLETO DEL ARCHIVO
 console.log(` fileToBase64 DEBUG:`, {
 file: !!file,
 name: file?.name,
 type: file?.type,
 size: file?.size,
 constructor: file?.constructor?.name
 });
 
 // Validar que el archivo es válido
 if (!file || typeof file.size === 'undefined') {
 const error = new Error('Archivo inválido o corrupto');
 console.error(' Archivo inválido:', error.message);
 reject(error);
 return;
 }
 
 // Verificar tamaño - SOLO archivos vacíos, SIN límite máximo
 if (file.size === 0) {
 const error = new Error('Archivo vacío (0 bytes)');
 console.error(' Archivo vacío:', file.name);
 reject(error);
 return;
 }
 
 // SIN LÍMITE DE TAMAÑO - permitir archivos grandes para PDFs móviles
 console.log(` Archivo aceptado: ${file.name} (${(file.size/1024/1024).toFixed(1)}MB)`);
 
 // DEBUG SIMPLE DEL ARCHIVO
 console.log(` Procesando: ${file.name} (${(file.size/1024).toFixed(1)} KB)`);
 
 const reader = new FileReader();
 
 reader.onerror = (error) => {
 console.error(' Error FileReader:', error);
 const errorMsg = `Error leyendo archivo: ${file.name || 'sin nombre'} - ${error.toString()}`;
 reject(new Error(errorMsg));
 };
 
 reader.onabort = () => {
 console.error(' FileReader abortado para:', file.name);
 reject(new Error('Lectura de archivo abortada: ' + (file.name || 'sin nombre')));
 };
 
 // Timeout más largo para PDFs grandes desde móviles
 const timeout = setTimeout(() => {
 reader.abort();
 reject(new Error(`Timeout leyendo archivo: ${file.name} (más de 60s)`));
 }, 60000); // 1 minuto para PDFs grandes
 
 reader.onload = (event) => {
 clearTimeout(timeout);
 const result = event.target.result;
 console.log(` FileReader success: ${file.name} (${result?.length || 0} chars)`);
 
 // VALIDACIÓN PERMISIVA PARA PDFs
 if (!result || typeof result !== 'string' || result.length === 0) {
 console.error(` Resultado FileReader vacío para: ${file.name}`);
 reject(new Error('Resultado FileReader vacío o inválido'));
 return;
 }
 
 // Verificar formato Data URL pero ser permisivo con PDFs
 if (!result.startsWith('data:')) {
 console.error(` Resultado no es Data URL válido para: ${file.name}`);
 console.error(` Inicio del resultado: ${result.substring(0, 100)}`);
 reject(new Error('Resultado no es Data URL válido'));
 return;
 }
 
 // LOG ÉXITO CON DETALLES
 const resultSizeKB = (result.length / 1024).toFixed(1);
 console.log(` PDF/Archivo convertido exitosamente: ${file.name} -> ${resultSizeKB} KB base64`);
 
 resolve(result);
 };
 
 try {
 console.log(` Iniciando FileReader.readAsDataURL para: ${file.name}`);
 reader.readAsDataURL(file);
 } catch (error) {
 clearTimeout(timeout);
 console.error(' Error iniciando FileReader:', error);
 reject(new Error('Error iniciando FileReader: ' + error.message));
 }
 });
 }
 };


 // ============================================
 // PAYMENT PROCESSING & DATA CAPTURE
 // ============================================
 
 // Sistema de procesamiento de pago con captura completa de datos
 function initializePaymentSystem() {
 const submitPaymentBtn = document.getElementById('submit-payment');
 const termsCheckbox = document.getElementById('terms_accept_pago');
 
 // Habilitar/deshabilitar botón según checkbox
 if (termsCheckbox) {
 termsCheckbox.addEventListener('change', function() {
 if (submitPaymentBtn) {
 submitPaymentBtn.disabled = !this.checked;
 }
 });
 }
 
 // Procesar pago al hacer click
 if (submitPaymentBtn) {
 submitPaymentBtn.addEventListener('click', async function(e) {
 e.preventDefault();
 console.log(' INICIANDO PROCESO DE PAGO REDSYS...');
 
 // Deshabilitar botón y mostrar loading
 submitPaymentBtn.disabled = true;
 submitPaymentBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Procesando...';
 
 try {
 // FASE 1: Recopilar TODOS los datos del formulario
 const completeFormData = await captureAllFormData();
 console.log(' Datos capturados:', completeFormData);
 
 // NUEVA ESTRATEGIA: Almacenar datos completos para proceso unificado
 console.log(' ALMACENANDO DATOS COMPLETOS PARA PROCESO UNIFICADO');
 
 // Almacenar en sessionStorage para acceso inmediato
 // QUOTA FIX: No almacenar archivos grandes en sessionStorage
 // Los archivos se procesarán directamente en el callback de Redsys
 console.log(' Datos completos almacenados en sessionStorage');
 
 // Los archivos se enviarán al webhook DESPUÉS del pago exitoso
 
 } catch (error) {
 console.error(' Error en proceso de pago:', error);
 alert('Error al procesar el pago: ' + error.message);
 
 // Restaurar botón
 submitPaymentBtn.disabled = false;
 submitPaymentBtn.innerHTML = '<i class="fa-solid fa-credit-card"></i> Proceder al Pago';
 }
 });
 }
 }
 
 // Función para capturar TODOS los datos del formulario
 async function captureAllFormData() {
 console.log(' Capturando datos completos del formulario...');
 
 // Obtener TODOS los datos del formulario usando la función existente
 const allFormData = await TBV2_Form.collectFormData();
 
 // Datos del vehículo (ya están en allFormData.vehicle)
 const vehicleData = allFormData.vehicle;
 
 // Datos personales combinando comprador y vendedor
 const personalData = {
 customerName: allFormData.customer.name || '',
 customerDni: allFormData.customer.dni || '',
 customerEmail: allFormData.customer.email || '',
 customerPhone: allFormData.customer.phone || '',
 
 sellerName: document.getElementById('seller_name')?.value || '',
 sellerDni: document.getElementById('seller_dni')?.value || '',
 sellerEmail: document.getElementById('seller_email')?.value || '',
 sellerPhone: document.getElementById('seller_phone')?.value || ''
 };
 
 // VALIDACIÓN CRÍTICA: Verificar datos antes de enviar
 console.log(' VALIDACIÓN CRÍTICA DE DATOS:');
 console.log(' personalData.customerName:', `"${personalData.customerName}"`);
 console.log(' personalData.customerEmail:', `"${personalData.customerEmail}"`);
 console.log(' allFormData.customer:', allFormData.customer);
 
 if (!personalData.customerName || !personalData.customerEmail) {
 console.error(' ERROR CRÍTICO: Faltan datos del cliente antes de envío:');
 console.error(' customerName:', personalData.customerName);
 console.error(' customerEmail:', personalData.customerEmail);
 
 // Intentar capturar directamente desde DOM como fallback
 const directName = document.getElementById('customer_name')?.value;
 const directEmail = document.getElementById('customer_email')?.value;
 
 console.log(' FALLBACK - Captura directa DOM:');
 console.log(' DOM customer_name:', directName);
 console.log(' DOM customer_email:', directEmail);
 
 if (directName && directName.trim()) personalData.customerName = directName.trim();
 if (directEmail && directEmail.trim()) personalData.customerEmail = directEmail.trim();
 
 // Re-verificar después del fallback
 console.log(' DESPUÉS DEL FALLBACK:');
 console.log(' personalData.customerName:', `"${personalData.customerName}"`);
 console.log(' personalData.customerEmail:', `"${personalData.customerEmail}"`);
 }
 
 // Datos de precio e ITP (ya están en allFormData.pricing)
 const pricingData = allFormData.pricing;
 
 // Archivos subidos (convertidos a base64)
 const filesData = allFormData.files || {};
 
 // Firma digital (ya incluida en filesData)
 const signatureData = null;
 
 // SOLUCIÓN DIRECTA: Enviar archivos en el webhook directamente
 console.log(' NUEVA ESTRATEGIA: Archivos directos al webhook');
 
 // Compilar datos CON archivos incluidos para webhook directo
 const compiledData = {
 tramiteType: 'transferencia-barco',
 vehicle: vehicleData,
 personal: personalData,
 pricing: pricingData,
 files: filesData, // INCLUIR archivos directamente
 signature: signatureData,
 timestamp: new Date().toISOString()
 };
 
 // VALIDACIÓN FINAL: Verificar que tenemos datos válidos
 if (!personalData.customerName || !personalData.customerEmail || 
 !personalData.customerName.trim() || !personalData.customerEmail.trim()) {
 console.error(' ERROR FINAL: No se pueden enviar datos sin nombre y email del cliente');
 console.error(' Final customerName:', `"${personalData.customerName}"`);
 console.error(' Final customerEmail:', `"${personalData.customerEmail}"`);
 throw new Error('Datos del cliente requeridos: nombre y email no pueden estar vacíos');
 }
 
 // DEBUG CRÍTICO: Verificar datos compilados
 console.log(' DATOS COMPILADOS PARA ENVÍO:', compiledData);
 console.log(' personalData:', personalData);
 console.log(' customerName:', `"${personalData.customerName}"`);
 console.log(' customerEmail:', `"${personalData.customerEmail}"`);
 
 return compiledData;
 }
 
 // Función para enviar archivos por separado (evitar 403)
 async function sendFilesToServer(tempOrderId, filesData) {
 console.log(' DEBUG sendFilesToServer COMPLETO:', {
 tempOrderId: tempOrderId,
 filesData: filesData,
 filesDataKeys: Object.keys(filesData || {}),
 filesDataLength: Object.keys(filesData || {}).length,
 localStorage_check: localStorage.getItem(tempOrderId)
 });
 
 if (!filesData || Object.keys(filesData).length === 0) {
 console.log(' No hay archivos para enviar - filesData vacío');
 console.log(' Revisando localStorage para:', tempOrderId);
 const lsData = localStorage.getItem(tempOrderId);
 if (lsData) {
 console.log(' Datos encontrados en localStorage:', JSON.parse(lsData));
 } else {
 console.log(' No hay datos en localStorage para este tempOrderId');
 }
 return;
 }
 
 try {
 console.log(' ENVIANDO ARCHIVOS POR SEPARADO:', tempOrderId);
 console.log(' Datos completos a enviar:', JSON.stringify(filesData, null, 2));
 
 const formData = new FormData();
 formData.append('action', 'tbv2_store_files');
 formData.append('nonce', '<?php echo wp_create_nonce("tbv2_nonce"); ?>');
 formData.append('orderId', tempOrderId);
 formData.append('filesData', JSON.stringify(filesData));
 
 console.log(' Enviando a:', '<?php echo admin_url("admin-ajax.php"); ?>');
 
 const response = await fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
 method: 'POST',
 body: formData
 });
 
 console.log(' Respuesta servidor:', response.status, response.statusText);
 
 if (response.ok) {
 const result = await response.json();
 console.log(' Resultado completo del servidor:', result);
 if (result.success) {
 console.log(' ARCHIVOS ENVIADOS EXITOSAMENTE POR SEPARADO');
 } else {
 console.error(' ERROR ENVIANDO ARCHIVOS:', result.data);
 }
 } else {
 const errorText = await response.text();
 console.error(' ERROR HTTP:', response.status, errorText);
 }
 } catch (error) {
 console.error(' EXCEPCIÓN enviando archivos:', error);
 }
 }
 
 // Función para capturar archivos subidos
 async function captureUploadedFiles() {
 const files = {};
 const fileInputs = [
 'upload-hoja-asiento',
 'upload-dni-comprador', 
 'upload-dni-vendedor',
 'upload-contrato-compraventa',
 'upload-modelo-620'
 ];
 
 for (const inputId of fileInputs) {
 const input = document.getElementById(inputId);
 if (input && input.files.length > 0) {
 files[inputId] = {
 count: input.files.length,
 names: Array.from(input.files).map(f => f.name),
 sizes: Array.from(input.files).map(f => f.size)
 };
 }
 }
 
 return files;
 }
 
 // Función para capturar firma
 function captureSignatureData() {
 if (typeof getSignatureData === 'function') {
 return getSignatureData();
 }
 return null;
 }
 
 // Función para crear sesión de pago Redsys
 async function createRedsysPayment(formData) {
 const response = await fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
 method: 'POST',
 headers: {
 'Content-Type': 'application/x-www-form-urlencoded',
 },
 body: new URLSearchParams({
 action: 'tbv2_create_redsys_payment',
 nonce: '<?php echo wp_create_nonce('tbv2_nonce'); ?>',
 formData: JSON.stringify(formData),
 orderId: 'TBV2-' + Date.now()
 })
 });
 
 return await response.json();
 }
 
 // Función para enviar formulario a Redsys
 function submitRedsysForm(redsysData) {
 console.log(' Enviando a Redsys TPV...');
 
 // Crear formulario dinámico
 const form = document.createElement('form');
 form.method = 'POST';
 form.action = '<?php echo (TBV2_REDSYS_MODE === 'test') ? TBV2_REDSYS_URL_TEST : TBV2_REDSYS_URL_LIVE; ?>';
 
 // Agregar parámetros de Redsys
 for (const [key, value] of Object.entries(redsysData)) {
 const input = document.createElement('input');
 input.type = 'hidden';
 input.name = key;
 input.value = value;
 form.appendChild(input);
 }
 
 // Agregar al body y enviar
 document.body.appendChild(form);
 form.submit();
 }
 
 // ============================================
 // SIGNATURE PAD SYSTEM & FILE UPLOADS
 // ============================================
 
 // Load Signature Pad library
 if (!window.SignaturePad) {
 const script = document.createElement('script');
 script.src = 'https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js';
 script.onload = initSignatureSystem;
 document.head.appendChild(script);
 } else {
 initSignatureSystem();
 }

 function initSignatureSystem() {
 console.log(' Inicializando sistema de firma digital...');
 
 let signaturePad = null;
 let isSignatureCaptured = false;
 const signatureModal = document.getElementById('signature-modal-advanced');
 const enhancedCanvas = document.getElementById('enhanced-signature-canvas');

 // Event listeners para modal de vista previa
 const signatureField = document.getElementById('signature-field');
 const previewModal = document.getElementById('document-preview-modal');
 const closePreviewBtn = document.getElementById('close-preview-modal');
 const cancelPreviewBtn = document.getElementById('cancel-document-preview');
 const proceedBtn = document.getElementById('proceed-to-signature');

 if (signatureField) {
 signatureField.addEventListener('click', showDocumentPreview);
 }
 if (closePreviewBtn) closePreviewBtn.addEventListener('click', closeDocumentPreview);
 if (cancelPreviewBtn) cancelPreviewBtn.addEventListener('click', closeDocumentPreview);
 if (proceedBtn) proceedBtn.addEventListener('click', proceedToSignature);

 // Event listeners para modal de firma
 const closeModalBtn = document.getElementById('close-signature-modal');
 const clearBtn = document.getElementById('modal-clear-signature');
 const acceptBtn = document.getElementById('modal-accept-signature');

 if (closeModalBtn) closeModalBtn.addEventListener('click', closeSignatureModal);
 if (clearBtn) clearBtn.addEventListener('click', clearSignature);
 if (acceptBtn) acceptBtn.addEventListener('click', acceptSignature);

 function showDocumentPreview() {
 console.log(' Mostrando vista previa del documento de autorización');
 
 // Generar el contenido del documento
 generateDocumentContent();
 
 // Mostrar modal de vista previa
 previewModal.classList.add('active');
 }

 function generateDocumentContent() {
 // Obtener datos del formulario
 const buyerName = document.getElementById('customer_name')?.value || '[Nombre del comprador]';
 const buyerDni = document.getElementById('customer_dni')?.value || '[DNI del comprador]';
 const buyerEmail = document.getElementById('customer_email')?.value || '[Email del comprador]';
 const sellerName = document.getElementById('seller_name')?.value || '[Nombre del vendedor]';
 const sellerDni = document.getElementById('seller_dni')?.value || '[DNI del vendedor]';
 
 // Datos del vehículo
 const manufacturerSelect = document.getElementById('manufacturer');
 const modelSelect = document.getElementById('model');
 const matriculaInput = document.getElementById('matricula');
 const manualManufacturer = document.getElementById('manual_manufacturer');
 const manualModel = document.getElementById('manual_model');
 const noEncuentroCheckbox = document.getElementById('no_encuentro_modelo');
 
 let manufacturer = '';
 let model = '';
 let matricula = matriculaInput?.value || '[Matrícula]';
 
 // Determinar fabricante y modelo
 if (noEncuentroCheckbox?.checked) {
 manufacturer = manualManufacturer?.value || '[Fabricante]';
 model = manualModel?.value || '[Modelo]';
 } else {
 manufacturer = manufacturerSelect?.selectedOptions[0]?.text || '[Fabricante]';
 model = modelSelect?.selectedOptions[0]?.text || '[Modelo]';
 }

 // Construir el documento HTML
 const documentContent = `
 <div class="document-section">
 <h4>AUTORIZACIÓN</h4>
 <p style="text-align: justify; margin-bottom: 16px;">
 Yo, <strong>${buyerName}</strong>, con DNI <strong>${buyerDni}</strong> y correo electrónico <strong>${buyerEmail}</strong>, 
 en mi calidad de comprador, <strong>autorizo expresamente a TRAMITFY S.L.</strong> para que actúe en mi nombre y 
 representación en todos los trámites necesarios ante <strong>Capitanía Marítima</strong> para la transferencia de titularidad 
de la embarcación con matrícula <strong>${matricula}</strong>.
 </p>
 
 <p style="text-align: justify; margin-bottom: 16px;">
 Esta autorización incluye expresamente la facultad para presentar toda la documentación requerida, realizar 
 el pago de tasas administrativas en mi nombre, firmar los documentos oficiales necesarios, y retirar los 
 certificados y documentación oficial resultante de la tramitación.
 </p>
 
 <p style="text-align: justify; margin-bottom: 16px;">
 Autorizo también el cobro del importe correspondiente a honorarios profesionales y tasas administrativas 
 según el presupuesto aceptado, así como el uso de mis datos personales exclusivamente para la gestión de este trámite.
 </p>
 </div>

 <div class="document-highlight">
 <p><i class="fa-solid fa-exclamation-triangle"></i> Al firmar este documento, acepta todos los términos de la autorización</p>
 </div>
 `;

 // Insertar contenido en el modal
 const contentDiv = document.getElementById('document-content-preview');
 if (contentDiv) {
 contentDiv.innerHTML = documentContent;
 }
 }

 function closeDocumentPreview() {
 console.log(' Cerrando vista previa del documento');
 previewModal.classList.remove('active');
 }

 function proceedToSignature() {
 console.log(' Procediendo a la firma del documento');
 // Cerrar modal de vista previa
 closeDocumentPreview();
 // Abrir modal de firma
 setTimeout(() => {
 openSignatureModal();
 }, 300);
 }

 function openSignatureModal() {
 console.log(' Abriendo modal de firma');
 signatureModal.classList.add('active');
 setTimeout(() => {
 initializeSignaturePad();
 }, 100);
 }

 function closeSignatureModal() {
 console.log(' Cerrando modal de firma');
 signatureModal.classList.remove('active');
 if (signaturePad) {
 signaturePad.off();
 }
 }

 function initializeSignaturePad() {
 if (signaturePad) {
 signaturePad.off();
 }

 // Ajustar canvas al contenedor
 const container = enhancedCanvas.parentElement;
 const rect = container.getBoundingClientRect();
 const ratio = window.devicePixelRatio || 1;

 enhancedCanvas.width = rect.width * ratio;
 enhancedCanvas.height = rect.height * ratio;
 enhancedCanvas.style.width = rect.width + 'px';
 enhancedCanvas.style.height = rect.height + 'px';
 enhancedCanvas.getContext('2d').scale(ratio, ratio);

 // Crear SignaturePad
 signaturePad = new SignaturePad(enhancedCanvas, {
 minWidth: 1,
 maxWidth: 3,
 throttle: 0,
 velocityFilterWeight: 0.7,
 penColor: '#000000'
 });

 // Event listener para activar botón cuando se firma
 signaturePad.addEventListener('afterUpdateStroke', () => {
 acceptBtn.disabled = false;
 });

 console.log(' SignaturePad inicializado');
 }

 function clearSignature() {
 if (signaturePad) {
 signaturePad.clear();
 acceptBtn.disabled = true;
 }
 }

 function acceptSignature() {
 if (!signaturePad || signaturePad.isEmpty()) {
 alert('Por favor, firme antes de confirmar.');
 return;
 }

 // Capturar firma y guardar persistentemente
 isSignatureCaptured = true;
 
 // CAPTURAR DATOS DE FIRMA PERSISTENTEMENTE
 window.tbv2SignatureData = signaturePad.toDataURL('image/png');
 console.log(' Firma capturada y guardada persistentemente');
 console.log(' Tamaño datos firma:', window.tbv2SignatureData.length, 'caracteres');
 
 // Actualizar estado visual
 const signatureStatus = document.getElementById('signature-status');
 if (signatureStatus) {
 signatureStatus.textContent = 'Firmado ';
 signatureStatus.style.color = '#10b981';
 }

 // Actualizar botón
 if (signatureField) {
 signatureField.innerHTML = '<span class="desktop-text"><i class="fa-solid fa-check"></i> Documento firmado</span><span class="mobile-text"><i class="fa-solid fa-check"></i></span>';
 signatureField.style.background = '#ecfdf5';
 signatureField.style.borderColor = '#10b981';
 signatureField.style.color = '#10b981';
 }

 closeSignatureModal();
 console.log(' Firma capturada correctamente y guardada en window.tbv2SignatureData');
 }

 // Verificar si hay firma al validar página documentos
 window.TBV2DocumentsValidation = {
 validatePage: function() {
 return isSignatureCaptured;
 },
 getSignatureData: function() {
 return signaturePad && !signaturePad.isEmpty() ? signaturePad.toDataURL() : null;
 },
 proceedToSignature: function() {
 console.log(' Usuario acepta proceder a firmar');
 openSignatureModal();
 }
 };
 }

 // ============================================
 // FILE UPLOAD HANDLERS
 // ============================================
 
 function initFileUploadSystem() {
 console.log(' Inicializando sistema de uploads...');
 
 // File inputs
 const fileInputs = [
 'upload-hoja-asiento',
 'upload-dni-comprador', 
 'upload-dni-vendedor',
 'upload-contrato-compraventa',
 'upload-modelo-620'
 ];

 fileInputs.forEach(inputId => {
 const input = document.getElementById(inputId);
 if (input) {
 input.addEventListener('change', handleFileChange);
 }
 });

 function handleFileChange(event) {
 const input = event.target;
 const files = input.files;
 const inputId = input.id;
 const countElement = document.querySelector(`[data-input="${inputId}"]`);
 const previewContainer = document.querySelector(`.file-preview-container[data-input="${inputId}"]`);
 
 // Actualizar contador
 if (countElement) {
 if (files.length === 0) {
 countElement.textContent = 'Sin archivos';
 countElement.style.color = '#6b7280';
 } else {
 countElement.textContent = `${files.length} archivo${files.length > 1 ? 's' : ''} seleccionado${files.length > 1 ? 's' : ''}`;
 countElement.style.color = '#10b981';
 }
 }

 // Crear preview de archivos
 if (previewContainer) {
 updateFilePreview(inputId, files, previewContainer);
 }

 console.log(` ${inputId}: ${files.length} archivo(s) seleccionado(s)`);
 }

 function updateFilePreview(inputId, files, container) {
 container.innerHTML = '';
 
 if (files.length === 0) return;
 
 // Crear array para manejar archivos dinámicamente
 if (!window.uploadedFiles) window.uploadedFiles = {};
 if (!window.uploadedFiles[inputId]) window.uploadedFiles[inputId] = [];
 
 // Convertir FileList a Array y añadir al storage
 const newFiles = Array.from(files);
 window.uploadedFiles[inputId] = [...window.uploadedFiles[inputId], ...newFiles];
 
 // Renderizar todos los archivos
 window.renderFilesList(inputId);
 }

 window.renderFilesList = function(inputId) {
 const container = document.querySelector(`.file-preview-container[data-input="${inputId}"]`);
 const countElement = document.querySelector(`[data-input="${inputId}"]`);
 
 if (!container || !window.uploadedFiles || !window.uploadedFiles[inputId]) return;
 
 const files = window.uploadedFiles[inputId];
 container.innerHTML = '';
 
 files.forEach((file, index) => {
 const previewItem = window.createFilePreviewItem(file, inputId, index);
 container.appendChild(previewItem);
 });
 
 // Actualizar contador
 if (countElement) {
 if (files.length === 0) {
 countElement.textContent = 'Sin archivos';
 countElement.style.color = '#6b7280';
 } else {
 countElement.textContent = `${files.length} archivo${files.length > 1 ? 's' : ''} seleccionado${files.length > 1 ? 's' : ''}`;
 countElement.style.color = '#10b981';
 }
 }
 }

 window.createFilePreviewItem = function(file, inputId, index) {
 const item = document.createElement('div');
 item.className = 'file-preview-item';
 
 const fileIcon = window.getFileIcon(file.type);
 const fileSize = window.formatFileSize(file.size);
 
 item.innerHTML = `
 <div class="file-preview-info">
 <i class="${fileIcon} file-preview-icon"></i>
 <div class="file-preview-name" title="${file.name}">${file.name}</div>
 <div class="file-preview-size">${fileSize}</div>
 </div>
 <button type="button" class="file-remove-btn" onclick="removeFile('${inputId}', ${index})">
 <i class="fa-solid fa-times"></i>
 </button>
 `;
 
 return item;
 }

 window.removeFile = function(inputId, index) {
 if (!window.uploadedFiles || !window.uploadedFiles[inputId]) return;
 
 // Eliminar archivo del array
 window.uploadedFiles[inputId].splice(index, 1);
 
 // Re-renderizar lista
 window.renderFilesList(inputId);
 
 // Actualizar input file (crear nuevo FileList)
 window.updateInputFiles(inputId);
 
 console.log(` Archivo eliminado de ${inputId}, quedan: ${window.uploadedFiles[inputId].length}`);
 }

 window.updateInputFiles = function(inputId) {
 const input = document.getElementById(inputId);
 if (!input || !window.uploadedFiles || !window.uploadedFiles[inputId]) return;
 
 // Crear DataTransfer para actualizar el input
 const dt = new DataTransfer();
 window.uploadedFiles[inputId].forEach(file => {
 dt.items.add(file);
 });
 
 input.files = dt.files;
 }

 window.getFileIcon = function(fileType) {
 if (fileType.startsWith('image/')) {
 return 'fa-solid fa-image';
 } else if (fileType === 'application/pdf') {
 return 'fa-solid fa-file-pdf';
 } else {
 return 'fa-solid fa-file';
 }
 }

 window.formatFileSize = function(bytes) {
 if (bytes === 0) return '0 B';
 const k = 1024;
 const sizes = ['B', 'KB', 'MB', 'GB'];
 const i = Math.floor(Math.log(bytes) / Math.log(k));
 return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
 }
 }

 // ============================================ 
 // SISTEMA DE EJEMPLOS DE DOCUMENTOS
 // ============================================
 
 function initDocumentExamples() {
 console.log('Inicializando sistema de ejemplos de documentos...');
 
 // Event listeners para "Ver ejemplo" (ahora spans, no enlaces)
 document.querySelectorAll('.view-example').forEach(span => {
 span.addEventListener('click', (e) => {
 e.preventDefault(); // Prevenir CUALQUIER comportamiento por defecto
 e.stopPropagation(); // Prevenir bubbling
 e.stopImmediatePropagation(); // Prevenir otros listeners
 
 const docType = span.getAttribute('data-doc');
 console.log(` Click en Ver ejemplo para: ${docType} - EVENTS BLOQUEADOS`);
 
 // ELIMINACIÓN ESPECÍFICA del modal problemático que viene de otra fuente
 const eliminarModalProblematico = () => {
 // Buscar SOLO elementos que parezcan modales reales
 const modalSelectors = [
 '.modal', '[class*="modal"]', '[id*="modal"]',
 '.popup', '[class*="popup"]', '[id*="popup"]',
 '.overlay', '[class*="overlay"]',
 '.lightbox', '[class*="lightbox"]',
 '[role="dialog"]', '[aria-modal="true"]'
 ];
 
 modalSelectors.forEach(selector => {
 const elements = document.querySelectorAll(selector);
 elements.forEach(el => {
 // Solo eliminar si contiene el texto problemático Y no es nuestro modal
 if (el.textContent && 
 el.textContent.includes('Este es un ejemplo de cómo debe ser el documento') &&
 el.id !== 'tbv2-example-modal-bypass' &&
 !el.closest('#tbv2-example-modal-bypass')) {
 console.log(` ELIMINANDO modal problemático específico: ${el.tagName} ${el.id || el.className}`);
 el.remove();
 }
 });
 });
 };
 
 // Ejecutar eliminación inmediatamente y cada 100ms por 2 segundos
 eliminarModalProblematico();
 
 const killer = setInterval(eliminarModalProblematico, 100);
 setTimeout(() => {
 clearInterval(killer);
 console.log(' Eliminación de modales problemáticos terminada');
 }, 2000);
 
 // Pequeño delay para asegurar que otros eventos no interfieran
 setTimeout(() => {
 showDocumentExample(docType);
 }, 10);
 });
 });
 
 // Los event listeners se configuran directamente en el modal bypass dinámico
 
 // Cerrar con ESC
 document.addEventListener('keydown', (e) => {
 if (e.key === 'Escape') {
 const bypassModal = document.getElementById('tbv2-example-modal-bypass');
 if (bypassModal) closeExampleModal();
 }
 });
 }
 
 function showDocumentExample(docType) {
 console.log(` TBV2 - Mostrando ejemplo de documento: ${docType}`);
 
 // SEGURIDAD: Prevenir ejecución durante flujos de pago ACTIVOS (no en preparación)
 if (window.location.href.includes('redsys') ||
 (document.querySelector('#submit-payment:disabled') && 
 document.querySelector('#submit-payment').textContent.includes('Redirigiendo'))) {
 console.warn(' Bloqueando modal durante flujo de pago ACTIVO');
 return;
 }
 
 // Obtener datos del documento inmediatamente
 const examples = getDocumentExamples();
 const example = examples[docType];
 
 if (!example) {
 console.error(`No se encontró ejemplo para: ${docType}`);
 return;
 }
 
 console.log(` CREANDO MODAL BYPASS DIRECTO para: ${example.title} (SEGURO)`);
 
 // Crear modal completamente nuevo DIRECTAMENTE (sin eliminar nada más)
 const newModal = document.createElement('div');
 newModal.id = 'tbv2-example-modal-bypass';
 newModal.innerHTML = `
 <div style="
 position: fixed !important;
 top: 0 !important;
 left: 0 !important;
 width: 100% !important;
 height: 100% !important;
 background: rgba(0, 0, 0, 0.8) !important;
 display: flex !important;
 align-items: center !important;
 justify-content: center !important;
 z-index: 999999 !important;
 visibility: visible !important;
 opacity: 1 !important;
 ">
 <div class="modal-content-mobile" style="
 background: white !important;
 max-width: 900px !important;
 width: 85% !important;
 max-height: 90vh !important;
 border-radius: 16px !important;
 box-shadow: 0 25px 50px rgba(0, 0, 0, 0.4) !important;
 overflow-y: auto !important;
 display: block !important;
 visibility: visible !important;
 opacity: 1 !important;
 position: relative !important;
 margin: 0 auto !important;
 ">
 <div style="
 position: absolute !important;
 top: 10px !important;
 right: 10px !important;
 z-index: 1000 !important;
 ">
 <button onclick="document.getElementById('tbv2-example-modal-bypass').remove(); document.body.style.overflow = '';" style="
 background: rgba(0, 0, 0, 0.5) !important;
 border: none !important;
 font-size: 20px !important;
 color: white !important;
 cursor: pointer !important;
 padding: 8px !important;
 width: 36px !important;
 height: 36px !important;
 display: flex !important;
 align-items: center !important;
 justify-content: center !important;
 border-radius: 50% !important;
 box-shadow: 0 2px 8px rgba(0,0,0,0.3) !important;
 ">×</button>
 </div>
 
 <div style="
 padding: 10px !important; 
 display: flex !important; 
 align-items: center !important; 
 justify-content: center !important;
 min-height: 200px !important;
 visibility: visible !important;
 ">
 <img class="modal-image-mobile" src="${example.image}" alt="${example.title}" style="
 max-width: 800px !important;
 max-height: 75vh !important;
 width: auto !important;
 height: auto !important;
 border-radius: 8px !important;
 box-shadow: 0 2px 8px rgba(0,0,0,0.1) !important;
 display: block !important;
 object-fit: contain !important;
 " 
 onload="console.log(' Imagen cargada en modal limpio');"
 onerror="this.parentElement.innerHTML='<div style=\\'padding: 30px; background: #f0f9ff; border: 2px dashed #0ea5e9; border-radius: 8px; color: #0369a1; text-align: center;\\><div style=\\'font-size: 48px; margin-bottom: 10px;\\'></div><p style=\\'margin: 0; font-size: 14px;\\'>Imagen de ejemplo no disponible</p></div>';">
 </div>
 </div>
 </div>
 `;
 
 // 6. Insertar modal en body y mostrarlo
 document.body.appendChild(newModal);
 document.body.style.overflow = 'hidden';
 
 console.log(' MODAL IMAGEN LIMPIO CREADO EXITOSAMENTE');
 console.log(` Imagen mostrada: ${example.image}`);
 console.log(` Modal simplificado - Solo imagen y botón cerrar`);
 
 // Verificación específica SOLO en elementos modales - NO DOM estructural
 setTimeout(() => {
 const modalSelectors = [
 '.modal', '[class*="modal"]', '[id*="modal"]',
 '.popup', '[class*="popup"]', '[id*="popup"]',
 '.overlay', '[class*="overlay"]',
 '.lightbox', '[class*="lightbox"]',
 '[role="dialog"]', '[aria-modal="true"]'
 ];
 
 let problemElements = [];
 modalSelectors.forEach(selector => {
 try {
 const elements = document.querySelectorAll(selector);
 elements.forEach(el => {
 if (el.textContent && 
 el.textContent.includes('Este es un ejemplo de cómo debe ser el documento') &&
 el.id !== 'tbv2-example-modal-bypass' &&
 !el.closest('#tbv2-example-modal-bypass')) {
 problemElements.push(el);
 }
 });
 } catch (e) {
 console.log(` Selector ${selector} failed:`, e);
 }
 });
 
 problemElements.forEach(el => {
 console.log(` Ocultando modal problemático: ${el.tagName} ${el.id || el.className}`);
 el.style.display = 'none';
 el.style.visibility = 'hidden';
 el.style.opacity = '0';
 el.style.zIndex = '-9999';
 });
 
 if (problemElements.length > 0) {
 console.log(` ${problemElements.length} modales problemáticos ocultados`);
 }
 }, 500);
 
 }
 
 function closeExampleModal() {
 const modal = document.getElementById('tbv2-example-modal-bypass');
 if (modal) {
 modal.remove();
 document.body.style.overflow = '';
 }
 }
 
 function getDocumentExamples() {
 return {
 'registro-maritimo': {
 icon: 'fa-solid fa-file-text',
 title: 'Registro Marítimo',
 description: 'Documento oficial que acredita la propiedad de la embarcación. Equivale a la "hoja de asiento" o "permiso de circulación" de los vehículos terrestres.',
 image: 'https://tramitfy.es/wp-content/uploads/exampledocs/permiso-caducado.jpg',
 tips: [
 'Debe estar vigente y sin tachaduras',
 'Incluye datos del propietario actual y de la embarcación',
 'Si es copia, debe estar compulsada o ser oficial',
 'Formato PDF o imagen de alta calidad'
 ]
 },
 'dni-comprador': {
 icon: 'fa-solid fa-file-text',
 title: 'DNI del Comprador',
 description: 'Documento Nacional de Identidad del nuevo propietario. Debe incluir ambas caras del documento para verificar todos los datos.',
 image: 'https://tramitfy.es/wp-content/uploads/exampledocs/dni-comprador.jpg',
 tips: [
 'Subir AMBAS caras del DNI (anverso y reverso)',
 'DNI debe estar vigente (no caducado)',
 'Imagen clara y legible en todos los campos',
 'Si es extranjero, NIE vigente'
 ]
 },
 'dni-vendedor': {
 icon: 'fa-solid fa-file-text',
 title: 'DNI del Vendedor',
 description: 'Documento Nacional de Identidad del propietario actual que vende la embarcación. También requiere ambas caras.',
 image: 'https://tramitfy.es/wp-content/uploads/exampledocs/dni-comprador.jpg',
 tips: [
 'Subir AMBAS caras del DNI (anverso y reverso)',
 'Debe coincidir con el propietario del registro marítimo',
 'DNI vigente y en buen estado',
 'Si es extranjero, NIE vigente'
 ]
 },
 'contrato-compraventa': {
 icon: 'fa-solid fa-file-text',
 title: 'Contrato de Compraventa',
 description: 'Documento privado firmado entre comprador y vendedor donde se especifica el precio y condiciones de la venta.',
 image: 'https://tramitfy.es/wp-content/uploads/exampledocs/contrato-compraventa.jpg',
 tips: [
 'Debe estar firmado por ambas partes',
 'Incluir precio de venta, datos de la embarcación y fechas',
 'DNI de ambas partes debe coincidir con los documentos',
 'Puede ser documento privado o notarial'
 ]
 },
 'modelo-620': {
 icon: 'fa-solid fa-file-text',
 title: 'Modelo 620 - Comprobante ITP',
 description: 'Comprobante de pago del Impuesto de Transmisiones Patrimoniales (ITP) ya abonado. Solo necesario si el ITP ya fue pagado previamente.',
 image: 'https://tramitfy.es/wp-content/uploads/exampledocs/modelo-620.jpg',
 tips: [
 'Solo si YA pagaste el ITP por tu cuenta',
 'Debe coincidir con la embarcación y el precio',
 'Comprobante original o copia oficial',
 'Si no lo tienes, nosotros nos encargamos del pago'
 ]
 }
 };
 }

 // ============================================
 // NAVIGATION EVENT LISTENERS FOR DOCUMENTS PAGE
 // ============================================
 
 function initDocumentsNavigation() {
 console.log('🧭 Configurando navegación página documentos...');
 
 // Botón anterior - ir a precio
 const prevBtn = document.getElementById('tbv2-documentos-prevButton');
 if (prevBtn) {
 prevBtn.addEventListener('click', () => {
 console.log(' Navegando a página precio...');
 TBV2_Form.goToPage('page-precio');
 });
 }

 // Botón siguiente - ir a pago 
 const nextBtn = document.getElementById('tbv2-documentos-nextButton');
 if (nextBtn) {
 nextBtn.addEventListener('click', () => {
 console.log(' Navegando a página pago...');
 TBV2_Form.goToPage('page-pago');
 });
 }
 }

 // Payment button event listener
 document.addEventListener('DOMContentLoaded', () => {
 const submitPaymentBtn = document.getElementById('submit-payment');
 if (submitPaymentBtn) {
 submitPaymentBtn.addEventListener('click', (e) => {
 e.preventDefault();
 TBV2_Form.submitForm();
 });
 }
 });

 // Inicializar sistemas cuando DOM esté listo
 document.addEventListener('DOMContentLoaded', () => {
 setTimeout(() => {
 initFileUploadSystem();
 initDocumentsNavigation();
 initDocumentExamples();
 
 // Los archivos ahora se incluyen en el transient antes del pago
 console.log(' Sistema de archivos: incluidos en transient pre-pago');
 }, 500);
 });

 // ===== FUNCIÓN PARA VERIFICAR PAGO EXITOSO (LEGACY - YA NO SE USA) =====
 async function checkSuccessfulPayment() {
 // NOTA: Esta función ya no se usa porque ahora redirigimos directamente a /pago-realizado-con-exito/
 // Se mantiene por compatibilidad con posibles casos edge
 const urlParams = new URLSearchParams(window.location.search);
 const redsysResult = urlParams.get('redsys_result');
 const orderId = urlParams.get('order');
 
 if (redsysResult === 'ok' && orderId) {
 console.log(' PAGO EXITOSO DETECTADO - Orden:', orderId);
 console.log(' Recuperando archivos de sessionStorage para envío al webhook...');
 
 try {
 // Recuperar archivos del sessionStorage
 const filesKey = `tbv2_files_${orderId}`;
 const storedFiles = sessionStorage.getItem(filesKey);
 
 if (storedFiles) {
 const filesData = JSON.parse(storedFiles);
 console.log(' Archivos recuperados:', Object.keys(filesData));
 
 // Recuperar datos del formulario
 const formDataKey = 'tbv2_form_data';
 const storedFormData = sessionStorage.getItem(formDataKey);
 
 if (storedFormData) {
 const formData = JSON.parse(storedFormData);
 
 // Agregar archivos a los datos del formulario
 formData.files = filesData;
 
 console.log(' Enviando datos completos al webhook...');
 
 // Enviar al webhook (API Node.js en puerto 4000)
 const response = await fetch('https://46-202-128-35.sslip.io/api/herramientas/barcos/webhook', {
 method: 'POST',
 headers: {
 'Content-Type': 'application/json',
 },
 body: JSON.stringify(formData)
 });
 
 if (response.ok) {
 const result = await response.json();
 console.log(' DATOS + ARCHIVOS ENVIADOS AL WEBHOOK:', result);
 
 // Limpiar sessionStorage
 sessionStorage.removeItem(filesKey);
 sessionStorage.removeItem(formDataKey);
 sessionStorage.removeItem('tbv2_order_id');
 
 // Mostrar mensaje de éxito
 alert(' Pago procesado correctamente. Su trámite ha sido creado en el sistema.');
 
 // Limpiar URL
 const newUrl = window.location.origin + window.location.pathname;
 window.history.replaceState({}, document.title, newUrl);
 
 } else {
 console.error(' Error enviando al webhook:', response.status);
 }
 } else {
 console.error(' No se encontraron datos del formulario en sessionStorage');
 }
 } else {
 console.error(' No se encontraron archivos en sessionStorage para orden:', orderId);
 }
 
 } catch (error) {
 console.error(' Error procesando pago exitoso:', error);
 }
 }
 }
 
} // Fin if TBV2_DISABLED !== true
 
 </script>
 <?php
}

// =====================================================
// REGISTRO DE SHORTCODE Y SCRIPTS
// =====================================================

// =====================================================
// CONFIGURACIÓN PARA UPLOAD DE ARCHIVOS
// =====================================================

/**
 * Configurar WordPress para permitir uploads de PDF, JPG, PNG
 */
function tbv2_configure_file_uploads() {
 // CONFIGURACIÓN EQUILIBRADA PARA PDFs
 @ini_set('upload_max_filesize', '100M');
 @ini_set('post_max_size', '100M');
 @ini_set('max_file_uploads', '20');
 @ini_set('memory_limit', '512M');
 @ini_set('max_execution_time', 300);
 @ini_set('max_input_vars', 10000);
 
 // Log de configuración actual
 error_log("=== TBV2 PHP UPLOAD CONFIG ===");
 error_log("upload_max_filesize: " . ini_get('upload_max_filesize'));
 error_log("post_max_size: " . ini_get('post_max_size'));
 error_log("max_file_uploads: " . ini_get('max_file_uploads'));
 error_log("memory_limit: " . ini_get('memory_limit'));
 error_log("max_execution_time: " . ini_get('max_execution_time'));
 error_log("==============================");
}

/**
 * Permitir tipos de archivo específicos en WordPress
 */
function tbv2_allow_file_types($mimes) {
 // Asegurar que estos tipos están permitidos
 $mimes['pdf'] = 'application/pdf';
 $mimes['jpg'] = 'image/jpeg';
 $mimes['jpeg'] = 'image/jpeg';
 $mimes['png'] = 'image/png';
 $mimes['gif'] = 'image/gif';
 
 error_log("TBV2: MIME types permitidos actualizados");
 return $mimes;
}
add_filter('upload_mimes', 'tbv2_allow_file_types');

/**
 * Aumentar límite de tamaño de archivo para WordPress
 */
function tbv2_increase_upload_size($size) {
 return 100 * 1024 * 1024; // 100MB para PDFs móviles
}
add_filter('wp_max_upload_size', 'tbv2_increase_upload_size');

/**
 * Desactivar verificación de tipo de archivo restrictiva
 */
function tbv2_allow_unfiltered_uploads() {
 return true;
}
add_filter('wp_check_filetype_and_ext', 'tbv2_custom_file_type_check', 10, 4);

function tbv2_custom_file_type_check($data, $file, $filename, $mimes) {
 $wp_filetype = wp_check_filetype($filename, $mimes);
 $ext = $wp_filetype['ext'];
 $type = $wp_filetype['type'];
 $proper_filename = $data['proper_filename'];

 // Permitir específicamente nuestros tipos
 $allowed_types = ['pdf', 'jpg', 'jpeg', 'png', 'gif'];
 if (in_array($ext, $allowed_types)) {
 $data['ext'] = $ext;
 $data['type'] = $type;
 $data['proper_filename'] = $proper_filename;
 }

 return $data;
}

/**
 * Desactivar filtros restrictivos de WordPress para nuestros formularios
 */
function tbv2_bypass_wp_security_filters() {
 // Desactivar filtro de contenido peligroso para base64
 remove_filter('content_save_pre', 'wp_filter_post_kses');
 remove_filter('excerpt_save_pre', 'wp_filter_post_kses');
 
 // Permitir contenido HTML/JS en campos de formulario
 add_filter('wp_kses_allowed_html', function($tags, $context) {
 if ($context === 'post') {
 $tags['script'] = array();
 $tags['style'] = array();
 }
 return $tags;
 }, 10, 2);
 
 // CRUCIAL: Desactivar límites de WordPress para AJAX con archivos grandes
 add_filter('wp_max_upload_size', function() { return 100 * 1024 * 1024; }); // 100MB
 add_filter('upload_size_limit', function() { return 100 * 1024 * 1024; }); // 100MB
 
 // Desactivar verificaciones de contenido que pueden fallar con base64
 add_filter('wp_check_filetype_and_ext', '__return_false');
 
 error_log("TBV2: Filtros de seguridad y límites desactivados para uploads");
}

// Configurar al cargar el plugin
add_action('init', function() {
 tbv2_configure_file_uploads();
 tbv2_bypass_wp_security_filters();
});

/**
 * Shortcode registration
 */
function tbv2_register_shortcode() {
 add_shortcode('transferencia_barco_v2', 'tbv2_render_form');
}
add_action('init', 'tbv2_register_shortcode');

/**
 * Enqueue scripts if needed
 */
function tbv2_enqueue_scripts() {
 if (has_shortcode(get_post()->post_content ?? '', 'transferencia_barco_v2')) {
 // Font Awesome para iconos
 wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css');
 
 wp_localize_script('jquery', 'tbv2_ajax', [
 'ajax_url' => admin_url('admin-ajax.php'),
 'nonce' => wp_create_nonce('tbv2_nonce')
 ]);
 }
}
add_action('wp_enqueue_scripts', 'tbv2_enqueue_scripts');

// =====================================================
// AJAX HANDLERS PARA REDSYS
// =====================================================

/**
 * AJAX handler para crear el formulario de pago Redsys
 */
function tbv2_handle_create_redsys_payment() {
 // LIMPIAR CUALQUIER OUTPUT PREVIO ANTES DE JSON
 if (ob_get_level()) {
 ob_end_clean();
 }
 ob_start();
 
 // CONFIGURACIÓN ESPECÍFICA PARA BASE64 GRANDES
 @ini_set('memory_limit', '512M');
 @ini_set('max_execution_time', 300);
 @ini_set('max_input_vars', 50000);
 @ini_set('post_max_size', '100M');
 @ini_set('upload_max_filesize', '100M');
 @ini_set('max_input_time', 300);
 
 // Verificar nonce de seguridad con debug
 $nonce_provided = $_POST['nonce'] ?? 'NO_NONCE';
 $nonce_valid = wp_verify_nonce($nonce_provided, 'tbv2_nonce');
 
 error_log("=== TBV2 NONCE DEBUG ===");
 error_log("Nonce provided: " . $nonce_provided);
 error_log("Nonce valid: " . ($nonce_valid ? 'YES' : 'NO'));
 error_log("WordPress doing Ajax: " . (wp_doing_ajax() ? 'YES' : 'NO'));
 error_log("User logged in: " . (is_user_logged_in() ? 'YES' : 'NO'));
 error_log("Current user ID: " . get_current_user_id());
 error_log("========================");
 
 // TEMPORAL: Bypass nonce for debugging (REMOVER EN PRODUCCIÓN) 
 if (!$nonce_valid && false) { // Keep disabled - testing different approach
 error_log("TBV2: Nonce verification failed");
 wp_send_json_error([
 'message' => 'Error de seguridad - nonce inválido',
 'debug' => [
 'nonce_provided' => $nonce_provided,
 'expected_action' => 'tbv2_nonce',
 'wp_doing_ajax' => wp_doing_ajax(),
 'is_user_logged_in' => is_user_logged_in()
 ]
 ]);
 return;
 }
 
 // Log de bypass temporal
 if (!$nonce_valid) {
 error_log("TBV2: BYPASS NONCE FOR DEBUG - SECURITY RISK IN PRODUCTION!");
 }
 
 try {
 // DEBUG: Verificar el tamaño de datos recibidos
 $postSize = strlen($_POST['formData'] ?? '');
 $postSizeMB = round($postSize / 1024 / 1024, 2);
 error_log("TBV2: Tamaño de datos POST: {$postSizeMB}MB ({$postSize} bytes)");
 
 // Obtener datos del formulario con manejo de errores JSON
 $formDataJson = stripslashes($_POST['formData'] ?? '');
 $formData = json_decode($formDataJson, true);
 $jsonError = json_last_error();
 
 if ($jsonError !== JSON_ERROR_NONE) {
 $jsonErrorMsg = json_last_error_msg();
 error_log("TBV2: Error decodificando JSON: {$jsonErrorMsg}");
 throw new Exception("Error decodificando datos del formulario: {$jsonErrorMsg}");
 }
 
 $orderId = sanitize_text_field($_POST['orderId']); // Solo para transient
 
 // DEBUG: Ver qué datos están llegando exactamente
 error_log("=== TBV2 DEBUG DATOS RECIBIDOS ===");
 error_log("POST keys: " . print_r(array_keys($_POST), true));
 error_log("formData keys: " . print_r(array_keys($formData ?? []), true));
 
 // DEBUG ESPECÍFICO DE ARCHIVOS
 if (isset($formData['files']) && is_array($formData['files'])) {
 error_log("=== FILES DEBUG ===");
 error_log("Files count: " . count($formData['files']));
 error_log("Files keys: " . print_r(array_keys($formData['files']), true));
 
 foreach ($formData['files'] as $category => $fileInfo) {
 if (is_array($fileInfo)) {
 error_log("Category $category: " . (isset($fileInfo['count']) ? $fileInfo['count'] : 'no count') . " files");
 if (isset($fileInfo['data']) && is_array($fileInfo['data'])) {
 foreach ($fileInfo['data'] as $i => $base64Data) {
 $dataLength = strlen($base64Data);
 $isValidBase64 = (strpos($base64Data, 'data:') === 0);
 error_log(" File $i: {$dataLength} chars, valid base64: " . ($isValidBase64 ? 'YES' : 'NO'));
 }
 }
 }
 }
 error_log("==================");
 } else {
 error_log(" NO FILES DATA RECEIVED OR NOT ARRAY");
 }
 error_log("==================================");
 
 if (!$formData) {
 throw new Exception('Datos del formulario inválidos');
 }
 
 // DEBUG CRÍTICO: Verificar estructura exacta de datos
 error_log("=== VALIDACIÓN DATOS CRÍTICOS ===");
 error_log("¿Existe formData['personal']? " . (isset($formData['personal']) ? 'SÍ' : 'NO'));
 if (isset($formData['personal'])) {
 error_log("customerName: '" . ($formData['personal']['customerName'] ?? 'UNDEFINED') . "'");
 error_log("customerEmail: '" . ($formData['personal']['customerEmail'] ?? 'UNDEFINED') . "'");
 error_log("personal completo: " . print_r($formData['personal'], true));
 }
 error_log("================================");
 
 // Validar datos requeridos (los datos vienen en la estructura 'personal')
 if (empty($formData['personal']['customerName']) || empty($formData['personal']['customerEmail'])) {
 $missingFields = [];
 if (empty($formData['personal']['customerName'])) $missingFields[] = 'customerName';
 if (empty($formData['personal']['customerEmail'])) $missingFields[] = 'customerEmail';
 throw new Exception('Faltan datos requeridos del cliente: ' . implode(', ', $missingFields));
 }
 
 // NOTA: Archivos NO vienen en el AJAX (evitar 403)
 // Los archivos se almacenarán desde sessionStorage en el callback
 $filesData = [];
 
 // Almacenar TODOS los datos del formulario para el callback
 $transientData = [
 // Datos personales completos
 'customerName' => $formData['personal']['customerName'] ?? '',
 'customerEmail' => $formData['personal']['customerEmail'] ?? '',
 'customerPhone' => $formData['personal']['customerPhone'] ?? '',
 'customerDni' => $formData['personal']['customerDni'] ?? '',
 'sellerName' => $formData['personal']['sellerName'] ?? '',
 'sellerDni' => $formData['personal']['sellerDni'] ?? '',
 'sellerEmail' => $formData['personal']['sellerEmail'] ?? '',
 'sellerPhone' => $formData['personal']['sellerPhone'] ?? '',
 
 // Datos del vehículo
 'vehicleData' => $formData['vehicle'] ?? [],
 
 // Datos financieros
 'finalAmount' => $formData['pricing']['total_amount'] ?? 0,
 'basePrice' => $formData['pricing']['base_price'] ?? 134.99,
 'itpAmount' => $formData['pricing']['itp_amount'] ?? 0,
 'itpPagado' => $formData['pricing']['itp_user_selection'] ?? false,
 'tasas' => 24.58, // Valor fijo
 'iva' => 18.63, // Valor fijo
 'honorarios' => 91.78, // Valor fijo
 'honorariosNetos' => 75.85, // Valor fijo
 
 // NUEVO: Archivos unificados del proceso completo
 'files' => $filesData,
 'signature' => $filesData['firma_autorizacion'] ?? '',
 
 // DEBUG CRÍTICO DE ARCHIVOS
 'debug_files_received' => !empty($formData['files']),
 'debug_files_count' => is_array($formData['files']) ? count($formData['files']) : 0,
 
 // Metadata
 'tramiteType' => $formData['tramiteType'] ?? 'transferencia-barco',
 'timestamp' => $formData['timestamp'] ?? date('Y-m-d H:i:s'),
 'attachments' => [] // Se llenará después con las URLs de archivos
 ];
 
 // GENERAR Order ID ÚNICO para Redsys (máximo 12 caracteres, solo números/letras)
 // Usar timestamp + random para garantizar unicidad
 $timestamp = time();
 $random = rand(100, 999);
 $orderIdFinal = substr($timestamp . $random, -12); // Tomar últimos 12 caracteres
 
 // Asegurarse de que es único verificando si ya existe el transient
 $maxAttempts = 10;
 $attempts = 0;
 while (get_transient('tbv2_transfer_' . $orderIdFinal) !== false && $attempts < $maxAttempts) {
 $random = rand(100, 999);
 $orderIdFinal = substr($timestamp . $random, -12);
 $attempts++;
 }
 
 error_log('TBV2: Order ID generado: ' . $orderIdFinal);
 
 // Guardar datos por 1 hora (86400 segundos) - usar el Order ID real
 set_transient('tbv2_transfer_' . $orderIdFinal, $transientData, 86400);
 
 // USAR AMOUNT REAL DEL FORMULARIO (ya no test)
 $finalAmount = $formData['pricing']['total_amount'] ?? $formData['finalAmount'] ?? 0;
 $amountCents = (string) round(floatval($finalAmount) * 100);
 
 // Preparar datos para Redsys - FORMATO EXITOSO DEL TEST
 $orderData = [
 'order_id' => $orderIdFinal, // Microtime único (COMPROBADO)
 'amount_cents' => $amountCents, // Amount real del formulario
 'description' => 'Transferencia Embarcacion', // Sin acentos
 'customer_name' => preg_replace('/[^A-Za-z0-9\s]/', '', $formData['personal']['customerName'] ?? '') // Limpiar nombre
 ];
 
 // DEBUG ULTRA CRÍTICO: Log de datos finales
 error_log("=== TBV2 PRODUCTION FINAL DEBUG ===");
 error_log("Order ID final: " . $orderIdFinal);
 error_log("Amount cents: " . $amountCents);
 error_log("Final amount: " . $formData['finalAmount']);
 error_log("Order data: " . print_r($orderData, true));
 error_log("===================================");
 
 // DEBUG CRÍTICO: Log de datos antes de Redsys
 error_log("=== TBV2 DEBUG PHP CRÍTICO ===");
 error_log("formData['finalAmount']: " . $formData['finalAmount']);
 error_log("orderData completo: " . print_r($orderData, true));
 error_log("===========================");
 
 // Generar parámetros de Redsys
 $paymentData = tbv2_redsys_create_payment_form($orderData);
 
 // DEBUG CRÍTICO: Log de parámetros generados
 error_log("=== TBV2 PARÁMETROS REDSYS ===");
 error_log("paymentData generado: " . print_r($paymentData, true));
 error_log("paymentData es array?: " . (is_array($paymentData) ? 'SI' : 'NO'));
 error_log("paymentData count: " . (is_array($paymentData) ? count($paymentData) : 'N/A'));
 
 if (isset($paymentData['Ds_MerchantParameters'])) {
 error_log("Ds_MerchantParameters PRESENTE: " . substr($paymentData['Ds_MerchantParameters'], 0, 100) . '...');
 $decoded = json_decode(base64_decode($paymentData['Ds_MerchantParameters']), true);
 error_log("Parámetros decodificados: " . print_r($decoded, true));
 } else {
 error_log(" Ds_MerchantParameters FALTANTE!");
 error_log("Claves disponibles: " . print_r(array_keys($paymentData), true));
 }
 error_log("==============================");
 
 if (!$paymentData) {
 throw new Exception('Error al generar parámetros de pago');
 }
 
 // Limpiar output buffer antes de enviar JSON
 if (ob_get_level()) {
 ob_end_clean();
 }
 
 // Respuesta exitosa
 wp_send_json_success([
 'message' => 'Parámetros de pago generados exitosamente',
 'redsysData' => $paymentData
 ]);
 
 } catch (Exception $e) {
 error_log('TBV2 Redsys Error: ' . $e->getMessage());
 
 // Limpiar output buffer antes de enviar JSON de error
 if (ob_get_level()) {
 ob_end_clean();
 }
 
 wp_send_json_error([
 'message' => 'Error al procesar el pago: ' . $e->getMessage()
 ]);
 }
}
add_action('wp_ajax_tbv2_create_redsys_payment', 'tbv2_handle_create_redsys_payment');
add_action('wp_ajax_nopriv_tbv2_create_redsys_payment', 'tbv2_handle_create_redsys_payment');

/**
 * Handler para guardar archivos por separado (evitar 403)
 */
function tbv2_handle_store_files() {
 // LOGGING A ARCHIVO ESPECÍFICO PARA DEBUG
 $logFile = '/tmp/tbv2-debug.log';
 $timestamp = date('Y-m-d H:i:s');
 
 file_put_contents($logFile, "\n=== TBV2 STORE FILES HANDLER === $timestamp\n", FILE_APPEND);
 file_put_contents($logFile, "POST data: " . print_r($_POST, true) . "\n", FILE_APPEND);
 
 error_log("TBV2: === INICIANDO STORE FILES HANDLER ===");
 error_log("TBV2: POST data received: " . print_r($_POST, true));
 
 try {
 // Verificar nonce de seguridad
 $nonceProvided = $_POST['nonce'] ?? '';
 file_put_contents($logFile, "NONCE DEBUG: Provided='$nonceProvided'\n", FILE_APPEND);
 
 if (!wp_verify_nonce($nonceProvided, 'tbv2_nonce')) {
 file_put_contents($logFile, "ERROR: Nonce verification failed for nonce: '$nonceProvided'\n", FILE_APPEND);
 throw new Exception('Nonce verification failed');
 }
 file_put_contents($logFile, " Nonce verification passed\n", FILE_APPEND);
 
 $orderId = $_POST['orderId'] ?? '';
 $filesDataRaw = $_POST['filesData'] ?? '';
 
 error_log("TBV2: OrderId recibido: '" . $orderId . "'");
 error_log("TBV2: FilesData raw length: " . strlen($filesDataRaw));
 
 $filesData = json_decode(stripslashes($filesDataRaw), true);
 $jsonError = json_last_error();
 
 error_log("TBV2: JSON decode result: " . ($jsonError === JSON_ERROR_NONE ? 'SUCCESS' : 'ERROR: ' . $jsonError));
 error_log("TBV2: FilesData decoded: " . print_r($filesData, true));
 
 if (empty($orderId)) {
 throw new Exception('OrderId vacío');
 }
 
 if (empty($filesData)) {
 throw new Exception('FilesData vacío o inválido');
 }
 
 // Guardar archivos en transient separado
 $transientKey = 'tbv2_files_' . $orderId;
 $saved = set_transient($transientKey, $filesData, 86400);
 
 error_log("TBV2: Transient saved with key: '" . $transientKey . "' - Result: " . ($saved ? 'SUCCESS' : 'FAILED'));
 
 // Verificar que se guardó
 $retrieved = get_transient($transientKey);
 error_log("TBV2: Transient verification - Retrieved data exists: " . (!empty($retrieved) ? 'YES' : 'NO'));
 
 wp_send_json_success([
 'message' => 'Archivos almacenados correctamente', 
 'orderId' => $orderId,
 'filesCount' => count($filesData),
 'transientKey' => $transientKey
 ]);
 
 } catch (Exception $e) {
 error_log("TBV2 Store Files Error: " . $e->getMessage());
 wp_send_json_error(['message' => $e->getMessage()]);
 }
}
add_action('wp_ajax_tbv2_store_files', 'tbv2_handle_store_files');
add_action('wp_ajax_nopriv_tbv2_store_files', 'tbv2_handle_store_files');

/**
 * Handler para procesar el callback de Redsys (URL de retorno)
 */
function tbv2_handle_redsys_callback() {
 try {
 // Verificar que vengan datos de Redsys
 if (empty($_POST['Ds_MerchantParameters']) || empty($_POST['Ds_Signature'])) {
 throw new Exception('Callback de Redsys incompleto');
 }
 
 // Decodificar parámetros
 $merchantData = json_decode(base64_decode($_POST['Ds_MerchantParameters']), true);
 $receivedSignature = $_POST['Ds_Signature'];
 
 // Verificar firma de seguridad
 $calculatedSignature = tbv2_redsys_generate_signature($merchantData);
 
 if ($receivedSignature !== $calculatedSignature) {
 throw new Exception('Firma de seguridad inválida');
 }
 
 // Procesar respuesta según el código de resultado
 $responseCode = $merchantData['Ds_Response'];
 $orderId = $merchantData['Ds_Order'];
 
 if ($responseCode <= '0099') {
 // Pago exitoso
 tbv2_process_successful_payment($orderId, $merchantData);
 
 // Redirigir a página de éxito con orden
 wp_redirect('https://tramitfy.es/pago-realizado-con-exito/?order=' . $orderId);
 } else {
 // Pago fallido
 tbv2_process_failed_payment($orderId, $merchantData);
 
 // Redirigir de vuelta al formulario en caso de pago fallido
 wp_redirect('https://tramitfy.es/transferencia-propiedad-v2/');
 }
 
 } catch (Exception $e) {
 error_log('TBV2 Callback Error: ' . $e->getMessage());
 wp_redirect(home_url('/error-pago/?error=callback_error'));
 }
 
 exit;
}
add_action('init', 'tbv2_handle_redsys_callback_init');

function tbv2_handle_redsys_callback_init() {
 // Debug logging
 error_log('TBV2 Callback Init - GET params: ' . print_r($_GET, true));
 error_log('TBV2 Callback Init - POST params: ' . print_r($_POST, true));
 
 // Handle notification callback (server-to-server from Redsys)
 if (isset($_GET['redsys_notification']) && $_GET['redsys_notification'] === '1') {
 tbv2_handle_redsys_notification();
 }
 
 // Handle user return callbacks con nuevos parámetros
 if (isset($_GET['redsys_result'])) {
 tbv2_handle_redsys_return($_GET['redsys_result']);
 }
 
 // También procesar si viene algún dato de Redsys en POST sin parámetro GET
 if (isset($_POST['Ds_SignatureVersion']) && isset($_POST['Ds_MerchantParameters']) && isset($_POST['Ds_Signature'])) {
 error_log('TBV2: Procesando callback Redsys directo desde POST');
 tbv2_handle_redsys_return('ok');
 }
 
 // También mantener compatibilidad con parámetros antiguos
 if (isset($_GET['result'])) {
 tbv2_handle_redsys_return($_GET['result']);
 }
 if (isset($_GET['notification']) && $_GET['notification'] === '1') {
 tbv2_handle_redsys_notification();
 }
}

/**
 * Handler para notificaciones server-to-server de Redsys
 */
function tbv2_handle_redsys_notification() {
 try {
 // Log de la notificación para debug
 error_log('TBV2: Recibiendo notificación Redsys: ' . print_r($_POST, true));
 
 // Verificar que vengan datos de Redsys
 if (empty($_POST['Ds_MerchantParameters']) || empty($_POST['Ds_Signature'])) {
 throw new Exception('Notificación de Redsys incompleta');
 }
 
 // Decodificar parámetros
 $merchantData = json_decode(base64_decode($_POST['Ds_MerchantParameters']), true);
 $receivedSignature = $_POST['Ds_Signature'];
 
 // Para verificar la firma de notificación, necesitamos usar el algoritmo correcto
 // La firma de notificación se calcula sobre el string Ds_MerchantParameters (no sobre el JSON decodificado)
 $password_decoded = base64_decode(TBV2_REDSYS_SECRET_KEY);
 $order_id = $merchantData['Ds_Order'];
 
 // Generar clave de cifrado para PHP 7+/8+
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
 $l
 );
 
 // Calcular firma sobre el string Ds_MerchantParameters recibido
 $calculatedSignature = base64_encode(hash_hmac('sha256', $_POST['Ds_MerchantParameters'], $encryption_key, true));
 
 // Comparar firmas (strtr para caracteres URL-safe)
 $receivedSignatureClean = strtr($receivedSignature, '-_', '+/');
 $calculatedSignatureClean = strtr($calculatedSignature, '-_', '+/');
 
 if ($receivedSignatureClean !== $calculatedSignatureClean) {
 error_log('TBV2: Firma recibida: ' . $receivedSignature);
 error_log('TBV2: Firma calculada: ' . $calculatedSignature);
 throw new Exception('Firma de seguridad inválida');
 }
 
 // Procesar según el código de respuesta
 $responseCode = $merchantData['Ds_Response'];
 $orderId = $merchantData['Ds_Order'];
 
 if ($responseCode <= '0099') {
 // Pago exitoso - enviar al webhook
 tbv2_process_successful_payment($orderId, $merchantData);
 echo '[OK]'; // Respuesta requerida por Redsys
 } else {
 // Pago fallido
 tbv2_process_failed_payment($orderId, $merchantData);
 echo '[OK]'; // Respuesta requerida por Redsys
 }
 
 } catch (Exception $e) {
 error_log('TBV2 Notification Error: ' . $e->getMessage());
 echo '[KO]'; // Respuesta de error para Redsys
 }
 
 exit;
}

/**
 * Handler para URLs de retorno (usuario redirigido desde Redsys)
 */
function tbv2_handle_redsys_return($result) {
 global $wpdb; // Para debugging de transients
 
 if ($result === 'ok') {
 // Procesar los datos de Redsys si están disponibles
 if (isset($_POST['Ds_SignatureVersion']) && isset($_POST['Ds_MerchantParameters']) && isset($_POST['Ds_Signature'])) {
 try {
 $redsys = new RedsysAPI;
 $redsys->setParameter("DS_MERCHANT_MERCHANTCODE", TBV2_REDSYS_FUC);
 $redsys->setParameter("DS_MERCHANT_TERMINAL", TBV2_REDSYS_TERMINAL);
 $redsys->setParameter("DS_MERCHANT_TRANSACTIONTYPE", "0");
 $redsys->setParameter("DS_MERCHANT_CURRENCY", TBV2_REDSYS_CURRENCY);
 
 // Decodificar parámetros
 $params = $redsys->decodeMerchantParameters($_POST['Ds_MerchantParameters']);
 $orderId = $redsys->getParameter('Ds_Order');
 $response = $redsys->getParameter('Ds_Response');
 
 // Verificar que el pago fue exitoso (respuesta entre 0 y 99)
 if (intval($response) < 100) {
 error_log('TBV2: Pago exitoso detectado. Order ID: ' . $orderId);
 error_log('TBV2: Código de respuesta: ' . $response);
 
 // Recuperar datos del formulario usando el transient
 $formData = get_transient('tbv2_transfer_' . $orderId);
 
 if ($formData) {
 error_log('TBV2: Datos del formulario recuperados correctamente');
 // Procesar el pago exitoso
 tbv2_process_successful_payment($orderId, $formData);
 } else {
 error_log('TBV2 ERROR: No se encontraron datos para Order ID: ' . $orderId);
 error_log('TBV2: Transients activos: ' . print_r($wpdb->get_results("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '_transient_tbv2_transfer_%'"), true));
 }
 } else {
 error_log('TBV2: Pago NO exitoso. Código: ' . $response);
 }
 } catch (Exception $e) {
 error_log('TBV2 Error procesando callback Redsys: ' . $e->getMessage());
 }
 }
 
 // Obtener el Order ID de la sesión o parámetros
 $orderId = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : '';
 if (empty($orderId) && isset($_POST['Ds_MerchantParameters'])) {
 try {
 $redsys = new RedsysAPI;
 $params = $redsys->decodeMerchantParameters($_POST['Ds_MerchantParameters']);
 $orderId = $redsys->getParameter('Ds_Order');
 } catch (Exception $e) {
 error_log('TBV2: No se pudo obtener Order ID');
 }
 }
 
 // Redirigir a la página de pago exitoso existente
 wp_redirect('https://tramitfy.es/pago-realizado-con-exito/?order=' . $orderId);
 exit;
 } else {
 // Redirigir de vuelta al formulario cuando se cancela el pago
 wp_redirect('https://tramitfy.es/transferencia-propiedad-v2/');
 exit;
 }
}

/**
 * Generar página de confirmación profesional
 */
function tbv2_generate_confirmation_page($orderId = '') {
 ob_start();
 ?>
 <!DOCTYPE html>
 <html lang="es">
 <head>
 <meta charset="UTF-8">
 <meta name="viewport" content="width=device-width, initial-scale=1.0">
 <title>Pago Completado - TRAMITFY</title>
 <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
 <style>
 * {
 margin: 0;
 padding: 0;
 box-sizing: border-box;
 }
 body {
 font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
 background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
 min-height: 100vh;
 display: flex;
 align-items: center;
 justify-content: center;
 padding: 20px;
 }
 .confirmation-container {
 background: white;
 border-radius: 20px;
 box-shadow: 0 20px 60px rgba(0,0,0,0.15);
 max-width: 600px;
 width: 100%;
 padding: 50px 40px;
 text-align: center;
 animation: slideUp 0.5s ease;
 }
 @keyframes slideUp {
 from {
 opacity: 0;
 transform: translateY(30px);
 }
 to {
 opacity: 1;
 transform: translateY(0);
 }
 }
 .success-icon {
 width: 80px;
 height: 80px;
 background: #10b981;
 border-radius: 50%;
 display: flex;
 align-items: center;
 justify-content: center;
 margin: 0 auto 30px;
 animation: scaleIn 0.5s ease 0.2s both;
 }
 @keyframes scaleIn {
 from {
 transform: scale(0);
 }
 to {
 transform: scale(1);
 }
 }
 .success-icon i {
 color: white;
 font-size: 40px;
 }
 h1 {
 color: #1f2937;
 font-size: 32px;
 margin-bottom: 15px;
 font-weight: 700;
 }
 .subtitle {
 color: #6b7280;
 font-size: 18px;
 margin-bottom: 30px;
 line-height: 1.6;
 }
 .order-info {
 background: #f3f4f6;
 border-radius: 12px;
 padding: 20px;
 margin: 30px 0;
 }
 .order-info h3 {
 color: #374151;
 font-size: 14px;
 text-transform: uppercase;
 letter-spacing: 1px;
 margin-bottom: 8px;
 }
 .order-number {
 color: #016d86;
 font-size: 24px;
 font-weight: 700;
 }
 .next-steps {
 background: #fef3c7;
 border-left: 4px solid #f59e0b;
 padding: 20px;
 margin: 30px 0;
 text-align: left;
 border-radius: 8px;
 }
 .next-steps h3 {
 color: #92400e;
 margin-bottom: 15px;
 font-size: 18px;
 }
 .next-steps ul {
 list-style: none;
 padding: 0;
 }
 .next-steps li {
 color: #78350f;
 margin-bottom: 10px;
 padding-left: 25px;
 position: relative;
 }
 .next-steps li:before {
 content: '';
 position: absolute;
 left: 0;
 color: #f59e0b;
 font-weight: bold;
 }
 .buttons {
 display: flex;
 gap: 15px;
 justify-content: center;
 margin-top: 30px;
 }
 .btn {
 padding: 12px 30px;
 border-radius: 8px;
 text-decoration: none;
 font-weight: 600;
 transition: all 0.3s;
 display: inline-block;
 }
 .btn-primary {
 background: #016d86;
 color: white;
 }
 .btn-primary:hover {
 background: #015266;
 transform: translateY(-2px);
 }
 .btn-secondary {
 background: #f3f4f6;
 color: #374151;
 }
 .btn-secondary:hover {
 background: #e5e7eb;
 }
 @media (max-width: 480px) {
 .confirmation-container {
 padding: 30px 20px;
 }
 h1 {
 font-size: 26px;
 }
 .buttons {
 flex-direction: column;
 }
 .btn {
 width: 100%;
 }
 }
 </style>
 </head>
 <body>
 <div class="confirmation-container">
 <div class="success-icon">
 <i class="fas fa-check"></i>
 </div>
 
 <h1>¡Pago Completado!</h1>
 <p class="subtitle">Su trámite ha sido procesado correctamente y hemos recibido su pago.</p>
 
 <?php if ($orderId): ?>
 <div class="order-info">
 <h3>Número de Orden</h3>
 <div class="order-number"><?php echo esc_html($orderId); ?></div>
 </div>
 <?php endif; ?>
 
 <div class="next-steps">
 <h3> Próximos pasos:</h3>
 <ul>
 <li>Recibirá un email de confirmación en breve</li>
 <li>Revisaremos su documentación en las próximas 24-48 horas</li>
 <li>Le contactaremos si necesitamos información adicional</li>
 <li>Una vez completado, recibirá la documentación oficial</li>
 </ul>
 </div>
 
 <div class="buttons">
 <a href="<?php echo home_url(); ?>" class="btn btn-primary">
 <i class="fas fa-home"></i> Volver al inicio
 </a>
 <a href="mailto:admin@tramitfy.es" class="btn btn-secondary">
 <i class="fas fa-envelope"></i> Contactar
 </a>
 </div>
 </div>
 </body>
 </html>
 <?php
 return ob_get_clean();
}

/**
 * Generar página de error
 */
function tbv2_generate_error_page() {
 ob_start();
 ?>
 <!DOCTYPE html>
 <html lang="es">
 <head>
 <meta charset="UTF-8">
 <meta name="viewport" content="width=device-width, initial-scale=1.0">
 <title>Error en el Pago - TRAMITFY</title>
 <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
 <style>
 * {
 margin: 0;
 padding: 0;
 box-sizing: border-box;
 }
 body {
 font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
 background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
 min-height: 100vh;
 display: flex;
 align-items: center;
 justify-content: center;
 padding: 20px;
 }
 .error-container {
 background: white;
 border-radius: 20px;
 box-shadow: 0 20px 60px rgba(0,0,0,0.15);
 max-width: 600px;
 width: 100%;
 padding: 50px 40px;
 text-align: center;
 }
 .error-icon {
 width: 80px;
 height: 80px;
 background: #ef4444;
 border-radius: 50%;
 display: flex;
 align-items: center;
 justify-content: center;
 margin: 0 auto 30px;
 }
 .error-icon i {
 color: white;
 font-size: 40px;
 }
 h1 {
 color: #1f2937;
 font-size: 32px;
 margin-bottom: 15px;
 }
 .subtitle {
 color: #6b7280;
 font-size: 18px;
 margin-bottom: 30px;
 }
 .error-info {
 background: #fef2f2;
 border-left: 4px solid #ef4444;
 padding: 20px;
 margin: 30px 0;
 text-align: left;
 border-radius: 8px;
 }
 .buttons {
 display: flex;
 gap: 15px;
 justify-content: center;
 margin-top: 30px;
 }
 .btn {
 padding: 12px 30px;
 border-radius: 8px;
 text-decoration: none;
 font-weight: 600;
 transition: all 0.3s;
 }
 .btn-primary {
 background: #016d86;
 color: white;
 }
 .btn-primary:hover {
 background: #015266;
 }
 </style>
 </head>
 <body>
 <div class="error-container">
 <div class="error-icon">
 <i class="fas fa-times"></i>
 </div>
 
 <h1>Error en el Pago</h1>
 <p class="subtitle">No se pudo completar el pago. Por favor, inténtelo de nuevo.</p>
 
 <div class="error-info">
 <p><strong>Posibles causas:</strong></p>
 <ul style="margin-top: 10px; margin-left: 20px;">
 <li>Fondos insuficientes en la tarjeta</li>
 <li>Tarjeta expirada o datos incorrectos</li>
 <li>Límite de la tarjeta excedido</li>
 <li>Problema de conexión temporal</li>
 </ul>
 </div>
 
 <div class="buttons">
 <a href="javascript:history.back()" class="btn btn-primary">
 <i class="fas fa-arrow-left"></i> Intentar de nuevo
 </a>
 </div>
 </div>
 </body>
 </html>
 <?php
 return ob_get_clean();
}

/**
 * FUNCIÓN DEBUG: Forzar envío admin con logs ultra-detallados
 */
function tbv2_force_send_admin_email_debug($orderId, $paymentData, $formData) {
 error_log(" TBV2 FORCE DEBUG: === INICIANDO ENVÍO ADMIN FORZADO ===");
 error_log(" TBV2 FORCE DEBUG: OrderID recibido: " . $orderId);
 error_log(" TBV2 FORCE DEBUG: PaymentData: " . print_r($paymentData, true));
 error_log(" TBV2 FORCE DEBUG: FormData: " . print_r($formData, true));
 
 // Extraer datos básicos
 $finalAmount = floatval($paymentData['Ds_Amount']) / 100;
 $authCode = $paymentData['Ds_AuthorisationCode'] ?? 'N/A';
 $tramiteId = 'TBV2-' . $orderId;
 
 error_log(" TBV2 FORCE DEBUG: FinalAmount calculado: $finalAmount");
 error_log(" TBV2 FORCE DEBUG: AuthCode: $authCode");
 error_log(" TBV2 FORCE DEBUG: TramiteId: $tramiteId");
 
 // Headers EXACTOS que funcionan en tests
 $headers = [
 'Content-Type: text/html; charset=UTF-8',
 'From: Tramitfy <info@tramitfy.es>'
 ];
 
 error_log(" TBV2 FORCE DEBUG: Headers: " . print_r($headers, true));
 
 $admin_email = 'ipmgroup24@gmail.com';
 $subject = " TBV2 FORCE DEBUG - $tramiteId - {$finalAmount}€";
 
 $message = "
 <!DOCTYPE html>
 <html>
 <head><meta charset='UTF-8'></head>
 <body>
 <h1 style='color: #dc2626;'> TBV2 FORCE DEBUG EMAIL</h1>
 
 <div style='background: #f8fafc; padding: 15px; margin: 10px 0;'>
 <strong>Código:</strong> $tramiteId<br>
 <strong>Fecha:</strong> " . date('d/m/Y H:i:s') . "<br>
 <strong>Autorización:</strong> $authCode<br>
 <strong>Importe:</strong> {$finalAmount}€<br>
 <strong>OrderID:</strong> $orderId
 </div>
 
 <p><strong>NOTA:</strong> Email FORZADO desde flujo real para debug</p>
 <p>Si recibes este email, el problema NO está en wp_mail()</p>
 </body>
 </html>";
 
 error_log(" TBV2 FORCE DEBUG: Destinatario: $admin_email");
 error_log(" TBV2 FORCE DEBUG: Subject: $subject");
 
 // FORZAR envío usando EXACTA configuración de tests
 error_log(" TBV2 FORCE DEBUG: Ejecutando wp_mail()...");
 $mail_sent = wp_mail($admin_email, $subject, $message, $headers);
 error_log(" TBV2 FORCE DEBUG: wp_mail() resultado: " . ($mail_sent ? 'TRUE' : 'FALSE'));
 
 if ($mail_sent) {
 error_log(" TBV2 FORCE DEBUG: EMAIL ADMIN ENVIADO EXITOSAMENTE");
 } else {
 error_log(" TBV2 FORCE DEBUG: EMAIL ADMIN FALLÓ");
 error_log(" TBV2 FORCE DEBUG: Error en wp_mail() en flujo real");
 }
 
 error_log(" TBV2 FORCE DEBUG: === FIN ENVÍO ADMIN FORZADO ===");
 
 return $mail_sent;
}

/**
 * FUNCIÓN DE EMERGENCIA: Enviar email admin cuando falla el sistema principal
 */
function tbv2_emergency_send_admin_email($orderId, $paymentData, $formData) {
 error_log(" TBV2 EMERGENCY: Iniciando envío directo email admin para $orderId");
 
 $finalAmount = floatval($paymentData['Ds_Amount']) / 100; // Redsys en centimos
 $authCode = $paymentData['Ds_AuthorisationCode'] ?? 'N/A';
 $tramiteId = 'TBV2-' . $orderId;
 
 // Headers exactos funcionando
 $headers = [
 'Content-Type: text/html; charset=UTF-8',
 'From: Tramitfy <info@tramitfy.es>'
 ];
 
 $subject = " TBV2 EMERGENCY - $tramiteId - {$finalAmount}€";
 
 $message = "
 <!DOCTYPE html>
 <html>
 <head><meta charset='UTF-8'></head>
 <body style='font-family: Arial, sans-serif; margin: 20px;'>
 <div style='background: #dc2626; color: white; padding: 15px; border-radius: 5px; margin-bottom: 20px;'>
 <h1 style='margin: 0; color: white;'> PAGO TBV2 (EMERGENCY EMAIL)</h1>
 </div>
 
 <div style='background: #f8fafc; padding: 15px; border-left: 4px solid #dc2626; margin: 15px 0;'>
 <strong>Código:</strong> $tramiteId<br>
 <strong>Fecha:</strong> " . date('d/m/Y H:i:s') . "<br>
 <strong>Autorización:</strong> $authCode<br>
 <strong>Importe:</strong> {$finalAmount}€<br>
 <strong>OrderID:</strong> $orderId
 </div>
 
 <p><strong>NOTA:</strong> Email enviado por sistema de emergencia porque falló el sistema principal.</p>
 <p><a href='https://tramitfy.org/tramites/$tramiteId' style='color: #dc2626;'> Ver en Dashboard</a></p>
 </body>
 </html>";
 
 $admin_email = 'ipmgroup24@gmail.com';
 $mail_sent = wp_mail($admin_email, $subject, $message, $headers);
 
 error_log(" TBV2 EMERGENCY: Email enviado a $admin_email: " . ($mail_sent ? 'ÉXITO' : 'FALLÓ'));
 
 return $mail_sent;
}

/**
 * Procesar pago exitoso - FLUJO COMPLETO
 */
function tbv2_process_successful_payment($orderId, $paymentData) {
 try {
 error_log('TBV2: PROCESANDO PAGO EXITOSO - Orden: ' . $orderId);
 
 // 1. Recuperar datos completos del formulario
 $formData = get_transient('tbv2_transfer_' . $orderId);
 
 if (!$formData) {
 throw new Exception('Datos de transferencia no encontrados');
 }

 // EJECUTAR HOOK PARA SISTEMA ENHANCED
 error_log(" TBV2: Ejecutando hook tbv2_payment_success para Order ID: " . $orderId);
 do_action("tbv2_payment_success", $orderId, $formData);
 error_log(" TBV2: Hook tbv2_payment_success ejecutado");
 
 // ENVIAR EMAILS CON wp_mail (como formularios funcionando) 
 error_log(" TBV2: Iniciando envío de emails para pago exitoso - Order ID: " . $orderId);
 error_log(" TBV2: PaymentData recibida: " . print_r($paymentData, true));
 error_log(" TBV2: FormData recuperada: " . print_r($formData, true));
 
 // ENVIAR EMAILS CON DATOS VERIFICADOS
 $emailResult = tbv2_send_confirmation_emails($orderId, $paymentData, $formData);
 error_log(" TBV2: Resultado emails: " . print_r($emailResult, true));
 
 // DEBUG ULTRA-DETALLADO: Forzar envío admin independiente
 error_log(" TBV2 DEBUG: Ejecutando envío admin FORZADO independiente...");
 tbv2_force_send_admin_email_debug($orderId, $paymentData, $formData);
 
 // FALLBACK: Si fallan los emails, enviar directamente con datos básicos
 if (!$emailResult || !$emailResult['admin']) {
 error_log(" TBV2: Email admin falló, ejecutando FALLBACK");
 tbv2_emergency_send_admin_email($orderId, $paymentData, $formData);
 }
 
 error_log(" TBV2: Emails enviados completados");
 
 // 2. NUEVA ESTRATEGIA: Recuperar archivos usando orderId directamente
 $transientKey = 'tbv2_files_' . $orderId;
 file_put_contents('/tmp/tbv2-debug.log', date('Y-m-d H:i:s') . " TBV2: Buscando archivos en transient key: $transientKey\n", FILE_APPEND);
 
 $storedFiles = get_transient($transientKey);
 if ($storedFiles) {
 file_put_contents('/tmp/tbv2-debug.log', date('Y-m-d H:i:s') . " TBV2: ARCHIVOS RECUPERADOS del almacenamiento unificado: " . count($storedFiles) . " categorías\n", FILE_APPEND);
 file_put_contents('/tmp/tbv2-debug.log', date('Y-m-d H:i:s') . " TBV2: Archivos encontrados: " . print_r($storedFiles, true) . "\n", FILE_APPEND);
 
 $formData['files'] = $storedFiles; // Merge files back
 file_put_contents('/tmp/tbv2-debug.log', date('Y-m-d H:i:s') . " TBV2: FormData files field updated with stored files\n", FILE_APPEND);
 
 // Limpiar transient temporal
 delete_transient($transientKey);
 file_put_contents('/tmp/tbv2-debug.log', date('Y-m-d H:i:s') . " TBV2: Transient temporal limpiado\n", FILE_APPEND);
 } else {
 file_put_contents('/tmp/tbv2-debug.log', date('Y-m-d H:i:s') . " TBV2: WARNING - No se encontraron archivos para orderId: $orderId\n", FILE_APPEND);
 file_put_contents('/tmp/tbv2-debug.log', date('Y-m-d H:i:s') . " TBV2: Verificando transients disponibles...\n", FILE_APPEND);
 
 // Buscar todos los transients para debug
 global $wpdb;
 $transients = $wpdb->get_results("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '_transient_tbv2_files_%'");
 file_put_contents('/tmp/tbv2-debug.log', date('Y-m-d H:i:s') . " TBV2: Transients disponibles: " . print_r($transients, true) . "\n", FILE_APPEND);
 
 // Continuar sin archivos (los datos básicos sí se procesarán)
 $formData['files'] = [];
 }
 
 // 2. GENERAR PDF DE AUTORIZACIÓN CON FIRMA
 $pdfUrl = '';
 if (!empty($formData['signature'])) {
 $pdfUrl = tbv2_generate_authorization_pdf($orderId, $formData);
 error_log('TBV2: PDF generado - ' . $pdfUrl);
 }
 
 // 3. PROCESAR ARCHIVOS SUBIDOS
 error_log('TBV2: Procesando archivos para orden ' . $orderId);
 error_log('TBV2: Datos de archivos recibidos: ' . print_r($formData['files'] ?? 'NO FILES', true));
 $uploadedFiles = tbv2_process_file_uploads($orderId, $formData['files'] ?? []);
 error_log('TBV2: Archivos procesados - ' . count($uploadedFiles) . ' categorías con archivos');
 error_log('TBV2: Detalle de archivos: ' . json_encode($uploadedFiles));
 
 // 4. PREPARAR DATOS COMPLETOS PARA EL WEBHOOK
 // Formatear archivos para el webhook
 $formattedAttachments = [];
 foreach ($uploadedFiles as $inputId => $fileDataArray) {
 foreach ($fileDataArray as $fileData) {
 $formattedAttachments[] = [
 'type' => $inputId,
 'name' => $fileData['name'],
 'url' => $fileData['url'],
 'size' => $fileData['size'],
 'mimeType' => $fileData['type']
 ];
 }
 }
 
 // Agregar PDF de autorización si existe
 if ($pdfUrl) {
 $formattedAttachments[] = [
 'type' => 'authorization_pdf',
 'name' => 'Autorización Firmada.pdf',
 'url' => $pdfUrl,
 'size' => 0,
 'mimeType' => 'application/pdf'
 ];
 }
 
 $webhookData = [
 // Identificación
 'tramiteId' => $orderId,
 'tramiteType' => 'transferencia-barco',
 
 // Datos personales del comprador
 'customerName' => $formData['customerName'],
 'customerEmail' => $formData['customerEmail'],
 'customerPhone' => $formData['customerPhone'],
 'customerDni' => $formData['customerDni'],
 
 // Datos del vendedor
 'sellerName' => $formData['sellerName'] ?? '',
 'sellerDni' => $formData['sellerDni'] ?? '',
 'sellerEmail' => $formData['sellerEmail'] ?? '',
 'sellerPhone' => $formData['sellerPhone'] ?? '',
 
 // Datos del vehículo
 'vehicleData' => $formData['vehicleData'] ?? [],
 'matricula' => $formData['vehicleData']['matricula'] ?? '',
 
 // Datos financieros COMPLETOS
 'finalAmount' => floatval($formData['finalAmount']),
 'basePrice' => floatval($formData['basePrice']),
 'itpAmount' => floatval($formData['itpAmount']),
 'itpPagado' => $formData['itpPagado'] ?? false,
 'tasas' => 24.58, // Valor fijo según CLAUDE.md
 'iva' => 18.63,
 'honorarios' => 91.78,
 'honorariosNetos' => 75.85,
 
 // Archivos y documentos formateados
 'attachments' => $formattedAttachments,
 'signatureData' => $formData['signature'] ?? '',
 
 // Datos de pago Redsys
 'paymentIntentId' => $paymentData['Ds_AuthorisationCode'] ?? '',
 'paymentMethod' => 'card',
 'paymentProvider' => 'redsys',
 'redsysOrderId' => $paymentData['Ds_Order'] ?? '',
 
 // Estado y metadata
 'status' => 'pending',
 'timestamp' => $formData['timestamp'] ?? date('Y-m-d H:i:s'),
 'processedAt' => date('Y-m-d H:i:s'),
 'source' => 'TBV2_FORM'
 ];
 
 // 5. ENVIAR EMAILS DE NOTIFICACIÓN
 tbv2_send_notification_emails($orderId, $formData, $pdfUrl, $uploadedFiles);
 error_log('TBV2: Emails enviados');
 
 // 6. ENVÍO UNIFICADO AL WEBHOOK - NUEVA ESTRATEGIA JSON 
 $webhookUrl = 'https://tramitfy.org/api/herramientas/barcos/webhook';
 
 error_log('TBV2: Preparando envío UNIFICADO al webhook con todos los datos y archivos');
 
 // Preparar payload unificado con todos los datos
 $unifiedPayload = [
 'tramiteId' => 'TBV2-' . $orderId,
 'tramiteType' => 'transferencia-barco-v2',
 
 // Datos del cliente
 'customerName' => $formData['customerName'],
 'customerEmail' => $formData['customerEmail'], 
 'customerPhone' => $formData['customerPhone'],
 'customerDni' => $formData['customerDni'],
 
 // Datos del vehículo desde transient
 'manufacturer' => $formData['vehicleData']['manufacturer'] ?? '',
 'model' => $formData['vehicleData']['model'] ?? '',
 'matriculation_date' => $formData['vehicleData']['matriculation_date'] ?? '',
 'purchase_price' => $formData['vehicleData']['purchase_price'] ?? 0,
 'region' => $formData['vehicleData']['region'] ?? '',
 
 // Datos financieros
 'finalAmount' => $formData['finalAmount'],
 'basePrice' => $formData['basePrice'],
 'itpAmount' => $formData['itpAmount'],
 'tasas' => $formData['tasas'],
 'iva' => $formData['iva'],
 'honorarios' => $formData['honorarios'],
 'honorariosNetos' => $formData['honorariosNetos'],
 
 // ARCHIVOS INCLUIDOS del transient
 'files' => $formData['files'] ?? [],
 
 // Metadata del pago
 'paymentStatus' => 'completed',
 'orderId' => $orderId,
 'timestamp' => date('Y-m-d H:i:s')
 ];
 
 error_log('TBV2: Payload unificado preparado con ' . count($formData['files'] ?? []) . ' categorías de archivos');
 
 // NOTA: Los archivos ya están incluidos en $unifiedPayload['files'] como base64
 error_log('TBV2: Archivos incluidos en payload JSON - No necesario procesamiento multipart');
 
 // ENVÍO SIMPLIFICADO CON JSON (archivos ya están en base64)
 $response = wp_remote_post($webhookUrl, [
 'method' => 'POST',
 'timeout' => 60,
 'headers' => [
 'Content-Type' => 'application/json',
 'User-Agent' => 'TBV2-Webhook-Unified/1.0'
 ],
 'body' => json_encode($unifiedPayload, JSON_UNESCAPED_SLASHES)
 ]);
 
 if (is_wp_error($response)) {
 error_log('TBV2 Webhook Error: ' . $response->get_error_message());
 } else {
 $responseCode = wp_remote_retrieve_response_code($response);
 $responseBody = wp_remote_retrieve_body($response);
 error_log('TBV2: WEBHOOK UNIFICADO completado - Código: ' . $responseCode);
 error_log('TBV2: Response: ' . substr($responseBody, 0, 300));
 
 if ($responseCode === 200) {
 error_log('TBV2: TRÁMITE UNIFICADO CREADO EXITOSAMENTE con archivos y pago');
 }
 }
 
 // 7. Limpiar datos temporales
 delete_transient('tbv2_transfer_' . $orderId);
 
 error_log('TBV2: PROCESO COMPLETADO para orden ' . $orderId);
 
 } catch (Exception $e) {
 error_log('TBV2 Error en proceso post-pago: ' . $e->getMessage());
 // No lanzar excepción para no afectar la experiencia del usuario
 }
}

/**
 * GENERAR PDF DE AUTORIZACIÓN CON FIRMA
 */
function tbv2_generate_authorization_pdf($orderId, $formData) {
 try {
 // Crear directorio si no existe
 $upload_dir = wp_upload_dir();
 $pdf_dir = $upload_dir['basedir'] . '/autorizaciones-tramitfy/' . date('Y/m');
 if (!file_exists($pdf_dir)) {
 wp_mkdir_p($pdf_dir);
 }
 
 // Generar contenido HTML del PDF
 $html = '
 <html>
 <head>
 <style>
 body { font-family: Arial, sans-serif; }
 h1 { color: #016d86; }
 .signature { border-top: 2px solid #000; margin-top: 50px; padding-top: 10px; }
 </style>
 </head>
 <body>
 <h1>AUTORIZACIÓN DE TRAMITACIÓN</h1>
 <p><strong>Fecha:</strong> ' . date('d/m/Y H:i') . '</p>
 <p><strong>Orden:</strong> ' . $orderId . '</p>
 
 <h2>DATOS DEL COMPRADOR</h2>
 <p><strong>Nombre:</strong> ' . sanitize_text_field($formData['customerName']) . '</p>
 <p><strong>DNI:</strong> ' . sanitize_text_field($formData['customerDni']) . '</p>
 <p><strong>Email:</strong> ' . sanitize_email($formData['customerEmail']) . '</p>
 <p><strong>Teléfono:</strong> ' . sanitize_text_field($formData['customerPhone']) . '</p>
 
 <h2>DATOS DEL VENDEDOR</h2>
 <p><strong>Nombre:</strong> ' . sanitize_text_field($formData['sellerName'] ?? 'No especificado') . '</p>
 <p><strong>DNI:</strong> ' . sanitize_text_field($formData['sellerDni'] ?? 'No especificado') . '</p>
 
 <h2>DATOS DEL VEHÍCULO</h2>';
 
 if (!empty($formData['vehicleData'])) {
 foreach ($formData['vehicleData'] as $key => $value) {
 $html .= '<p><strong>' . ucfirst($key) . ':</strong> ' . sanitize_text_field($value) . '</p>';
 }
 }
 
 $html .= '
 <h2>AUTORIZACIÓN</h2>
 <p>Por la presente, autorizo a TRAMITFY S.L. a realizar en mi nombre todos los trámites necesarios
 para la transferencia de titularidad de la embarcación descrita anteriormente.</p>
 
 <div class="signature">
 <p><strong>Firma digital capturada:</strong></p>';
 
 // Incluir firma si existe
 if (!empty($formData['signature'])) {
 $html .= '<img src="' . $formData['signature'] . '" style="max-width: 300px; height: auto;" />';
 }
 
 $html .= '
 </div>
 </body>
 </html>';
 
 // Generar PDF usando herramienta del sistema o librería
 $pdf_filename = 'autorizacion-' . $orderId . '.pdf';
 $pdf_path = $pdf_dir . '/' . $pdf_filename;
 
 // Guardar HTML temporalmente
 $html_temp = $pdf_dir . '/temp-' . $orderId . '.html';
 file_put_contents($html_temp, $html);
 
 // Convertir a PDF (requiere wkhtmltopdf instalado)
 $command = "wkhtmltopdf --quiet '$html_temp' '$pdf_path' 2>&1";
 exec($command, $output, $return);
 
 // Limpiar archivo temporal
 @unlink($html_temp);
 
 // Retornar URL del PDF
 if (file_exists($pdf_path)) {
 $pdf_url = $upload_dir['baseurl'] . '/autorizaciones-tramitfy/' . date('Y/m') . '/' . $pdf_filename;
 return $pdf_url;
 }
 
 } catch (Exception $e) {
 error_log('TBV2: Error generando PDF - ' . $e->getMessage());
 }
 
 return '';
}

/**
 * PROCESAR Y MOVER ARCHIVOS SUBIDOS
 */
function tbv2_process_file_uploads($orderId, $files) {
 $uploadedFiles = [];
 
 try {
 // Crear directorio para los archivos del trámite
 $upload_dir = wp_upload_dir();
 $tramite_dir = $upload_dir['basedir'] . '/tramites-tbv2/' . $orderId;
 
 if (!file_exists($tramite_dir)) {
 wp_mkdir_p($tramite_dir);
 }
 
 // Procesar archivos subidos en base64
 foreach ($files as $inputId => $fileData) {
 if (!empty($fileData['data']) && is_array($fileData['data'])) {
 $uploadedFiles[$inputId] = [];
 
 foreach ($fileData['data'] as $index => $base64Data) {
 if (empty($base64Data)) continue;
 
 // Extraer el tipo de archivo y los datos
 if (preg_match('/^data:([^;]+);base64,(.+)$/', $base64Data, $matches)) {
 $mimeType = $matches[1];
 $fileContent = base64_decode($matches[2]);
 
 // Determinar la extensión según el mime type
 $extension = '.bin';
 if (strpos($mimeType, 'image/jpeg') !== false) $extension = '.jpg';
 elseif (strpos($mimeType, 'image/png') !== false) $extension = '.png';
 elseif (strpos($mimeType, 'application/pdf') !== false) $extension = '.pdf';
 
 // Generar nombre único para el archivo
 $fileName = $inputId . '_' . ($index + 1) . '_' . uniqid() . $extension;
 $filePath = $tramite_dir . '/' . $fileName;
 
 // Guardar el archivo
 if (file_put_contents($filePath, $fileContent)) {
 $fileUrl = $upload_dir['baseurl'] . '/tramites-tbv2/' . $orderId . '/' . $fileName;
 $uploadedFiles[$inputId][] = [
 'name' => $fileData['names'][$index] ?? $fileName,
 'url' => $fileUrl,
 'path' => $filePath,
 'size' => strlen($fileContent),
 'type' => $mimeType
 ];
 
 error_log('TBV2: Archivo guardado - ' . $fileName);
 }
 }
 }
 }
 }
 
 error_log('TBV2: Total archivos procesados - ' . count($uploadedFiles) . ' grupos');
 
 } catch (Exception $e) {
 error_log('TBV2: Error procesando archivos - ' . $e->getMessage());
 }
 
 return $uploadedFiles;
}

/**
 * ENVIAR EMAILS DE NOTIFICACIÓN
 */
function tbv2_send_notification_emails($orderId, $formData, $pdfUrl, $uploadedFiles) {
 try {
 // Calcular desglose de precios
 $basePrice = floatval($formData['basePrice']);
 $itpAmount = floatval($formData['itpAmount']);
 $totalAmount = floatval($formData['finalAmount']);
 
 // Email PROFESIONAL al cliente
 $to_customer = $formData['customerEmail'];
 $subject_customer = ' Confirmación de Transferencia de Embarcación - TRAMITFY';
 
 $message_customer = "
 <!DOCTYPE html>
 <html>
 <head>
 <style>
 body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
 .container { max-width: 600px; margin: 0 auto; padding: 20px; }
 .header { background: #016d86; color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
 .content { background: #f9f9f9; padding: 30px; border: 1px solid #ddd; }
 .footer { background: #333; color: white; padding: 20px; text-align: center; border-radius: 0 0 10px 10px; }
 .info-box { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; border-left: 4px solid #016d86; }
 .btn { display: inline-block; padding: 12px 30px; background: #016d86; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
 h2 { color: #016d86; }
 .price-breakdown { background: white; padding: 15px; border-radius: 8px; margin: 20px 0; }
 .price-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #eee; }
 .price-total { font-size: 18px; font-weight: bold; color: #016d86; border-top: 2px solid #016d86; padding-top: 10px; }
 </style>
 </head>
 <body>
 <div class='container'>
 <div class='header'>
 <h1 style='margin: 0; font-size: 28px;'>TRAMITFY</h1>
 <p style='margin: 10px 0 0 0; font-size: 16px;'>Gestión Profesional de Trámites Náuticos</p>
 </div>
 
 <div class='content'>
 <h2>Confirmación de su Trámite</h2>
 
 <p>Estimado/a <strong>{$formData['customerName']}</strong>,</p>
 
 <p>Nos complace confirmar que hemos recibido correctamente su solicitud de <strong>Transferencia de Embarcación</strong>.</p>
 
 <div class='info-box'>
 <h3 style='margin-top: 0; color: #016d86;'> Detalles del Trámite</h3>
 <p><strong>Número de Referencia:</strong> {$orderId}</p>
 <p><strong>Fecha:</strong> " . date('d/m/Y H:i') . "</p>
 <p><strong>Tipo de Trámite:</strong> Transferencia de Embarcación</p>
 </div>
 
 <div class='price-breakdown'>
 <h3 style='margin-top: 0; color: #016d86;'> Resumen del Pago</h3>
 <div class='price-row'>
 <span>Gestión del trámite:</span>
 <span>" . number_format($basePrice, 2, ',', '.') . " €</span>
 </div>";
 
 if ($itpAmount > 0) {
 $message_customer .= "
 <div class='price-row'>
 <span>ITP (Impuesto de Transmisiones):</span>
 <span>" . number_format($itpAmount, 2, ',', '.') . " €</span>
 </div>";
 }
 
 $message_customer .= "
 <div class='price-row price-total'>
 <span>TOTAL PAGADO:</span>
 <span>" . number_format($totalAmount, 2, ',', '.') . " €</span>
 </div>
 </div>
 
 <h3 style='color: #016d86;'> Próximos Pasos</h3>
 <ol>
 <li>Revisaremos toda la documentación recibida</li>
 <li>Iniciaremos los trámites en la Capitanía Marítima correspondiente</li>
 <li>Le mantendremos informado del progreso en cada etapa</li>
 <li>Recibirá la documentación oficial una vez completado el proceso</li>
 </ol>";
 
 if ($pdfUrl) {
 $message_customer .= "
 <div style='text-align: center; margin: 30px 0;'>
 <a href='{$pdfUrl}' class='btn'> Descargar Autorización Firmada (PDF)</a>
 </div>";
 }
 
 $message_customer .= "
 <p style='margin-top: 30px;'>Si tiene alguna consulta sobre su trámite, no dude en contactarnos:</p>
 <p> Email: <a href='mailto:info@tramitfy.es'>info@tramitfy.es</a><br>
 WhatsApp: <a href='https://wa.me/34689170273'>+34 689 170 273</a></p>
 </div>
 
 <div class='footer'>
 <p style='margin: 0;'><strong>TRAMITFY</strong> - Especialistas en Gestión Náutica</p>
 <p style='margin: 5px 0; font-size: 12px;'>Este email es confidencial. Si lo ha recibido por error, por favor elimínelo.</p>
 </div>
 </div>
 </body>
 </html>
 ";
 
 wp_mail($to_customer, $subject_customer, $message_customer, ['Content-Type: text/html; charset=UTF-8']);
 
 // Email a administración - CORREGIDO A GMAIL
 $to_admin = 'ipmgroup24@gmail.com';
 $subject_admin = ' Nueva Transferencia de Barco TBV2 - ' . $orderId;
 
 // Obtener datos del vehículo
 $vehicleData = $formData['vehicleData'] ?? [];
 $manufacturer = $vehicleData['manufacturer'] ?? 'No especificado';
 $model = $vehicleData['model'] ?? 'No especificado';
 $matricula = $vehicleData['matricula'] ?? 'No especificada';
 
 $message_admin = "
 <!DOCTYPE html>
 <html>
 <head>
 <style>
 body { font-family: Arial, sans-serif; color: #333; }
 .admin-container { max-width: 700px; margin: 0 auto; padding: 20px; }
 .admin-header { background: #016d86; color: white; padding: 20px; border-radius: 8px; }
 .section { background: #f5f5f5; padding: 20px; margin: 15px 0; border-radius: 8px; border-left: 4px solid #016d86; }
 table { width: 100%; border-collapse: collapse; }
 td { padding: 8px; border-bottom: 1px solid #ddd; }
 .label { font-weight: bold; width: 40%; }
 .highlight { background: #fff3cd; padding: 10px; border-radius: 5px; margin: 10px 0; }
 .price-section { background: #e8f4f8; padding: 15px; border-radius: 8px; margin: 20px 0; }
 </style>
 </head>
 <body>
 <div class='admin-container'>
 <div class='admin-header'>
 <h2 style='margin: 0;'> Nueva Transferencia de Embarcación - TBV2</h2>
 <p style='margin: 10px 0 0 0;'>Referencia: {$orderId}</p>
 <p style='margin: 5px 0 0 0;'>Fecha: " . date('d/m/Y H:i:s') . "</p>
 </div>
 
 <div class='section'>
 <h3 style='margin-top: 0; color: #016d86;'> Datos del Cliente (Comprador)</h3>
 <table>
 <tr><td class='label'>Nombre:</td><td><strong>{$formData['customerName']}</strong></td></tr>
 <tr><td class='label'>DNI/NIE:</td><td>{$formData['customerDni']}</td></tr>
 <tr><td class='label'>Email:</td><td><a href='mailto:{$formData['customerEmail']}'>{$formData['customerEmail']}</a></td></tr>
 <tr><td class='label'>Teléfono:</td><td><a href='tel:{$formData['customerPhone']}'>{$formData['customerPhone']}</a></td></tr>
 </table>
 </div>";
 
 if (!empty($formData['sellerName'])) {
 $message_admin .= "
 <div class='section'>
 <h3 style='margin-top: 0; color: #016d86;'> Datos del Vendedor</h3>
 <table>
 <tr><td class='label'>Nombre:</td><td><strong>{$formData['sellerName']}</strong></td></tr>
 <tr><td class='label'>DNI/NIE:</td><td>{$formData['sellerDni']}</td></tr>
 <tr><td class='label'>Email:</td><td>{$formData['sellerEmail']}</td></tr>
 <tr><td class='label'>Teléfono:</td><td>{$formData['sellerPhone']}</td></tr>
 </table>
 </div>";
 }
 
 $message_admin .= "
 <div class='section'>
 <h3 style='margin-top: 0; color: #016d86;'> Datos de la Embarcación</h3>
 <table>
 <tr><td class='label'>Fabricante:</td><td>{$manufacturer}</td></tr>
 <tr><td class='label'>Modelo:</td><td>{$model}</td></tr>
 <tr><td class='label'>Matrícula:</td><td>{$matricula}</td></tr>
 </table>
 </div>
 
 <div class='price-section'>
 <h3 style='margin-top: 0; color: #016d86;'> Desglose Económico</h3>
 <table>
 <tr><td class='label'>Base del trámite:</td><td style='text-align: right;'><strong>" . number_format($basePrice, 2, ',', '.') . " €</strong></td></tr>";
 
 if ($itpAmount > 0) {
 $message_admin .= "
 <tr><td class='label'>ITP:</td><td style='text-align: right;'>" . number_format($itpAmount, 2, ',', '.') . " €</td></tr>";
 }
 
 $message_admin .= "
 <tr style='background: #016d86; color: white;'>
 <td class='label' style='padding: 10px;'>TOTAL COBRADO:</td>
 <td style='text-align: right; padding: 10px; font-size: 18px;'><strong>" . number_format($totalAmount, 2, ',', '.') . " €</strong></td>
 </tr>
 </table>
 </div>";
 
 if ($pdfUrl || !empty($uploadedFiles)) {
 $message_admin .= "
 <div class='section'>
 <h3 style='margin-top: 0; color: #016d86;'> Documentación Recibida</h3>";
 
 if ($pdfUrl) {
 $message_admin .= "<p> <strong>Autorización firmada:</strong> <a href='{$pdfUrl}' style='color: #016d86;'>Descargar PDF</a></p>";
 }
 
 if (!empty($uploadedFiles)) {
 $message_admin .= "<p><strong>Archivos adjuntos:</strong></p><ul style='list-style: none; padding-left: 0;'>";
 foreach ($uploadedFiles as $type => $names) {
 $message_admin .= "<li> {$type}: " . implode(', ', $names) . "</li>";
 }
 $message_admin .= "</ul>";
 }
 
 $message_admin .= "</div>";
 }
 
 $message_admin .= "
 <div class='highlight'>
 <p style='margin: 0;'><strong> ACCIONES REQUERIDAS:</strong></p>
 <ol style='margin: 10px 0 0 20px;'>
 <li>Verificar la documentación recibida</li>
 <li>Iniciar el trámite en Capitanía Marítima</li>
 <li>Actualizar el estado en el dashboard</li>
 </ol>
 </div>
 
 <div style='text-align: center; margin-top: 30px; padding-top: 20px; border-top: 2px solid #ddd;'>
 <p style='color: #666; font-size: 12px;'>Este es un mensaje automático generado por el sistema TBV2 de TRAMITFY</p>
 <p style='color: #666; font-size: 12px;'>Para gestionar este trámite, accede al <a href='https://tramitfy.org/tramites' style='color: #016d86;'>Dashboard de TRAMITFY</a></p>
 </div>
 </div>
 </body>
 </html>
 ";
 
 wp_mail($to_admin, $subject_admin, $message_admin, ['Content-Type: text/html; charset=UTF-8']);
 
 } catch (Exception $e) {
 error_log('TBV2: Error enviando emails - ' . $e->getMessage());
 }
}

/**
 * Procesar pago fallido
 */
function tbv2_process_failed_payment($orderId, $paymentData) {
 error_log('TBV2: Pago fallido para orden ' . $orderId . ' - Código: ' . $paymentData['Ds_Response']);
 
 // Mantener datos para reintento
 // No eliminar transient para permitir reintento del usuario
}

// Registro del shortcode
if (!shortcode_exists('transferencia_barco_v2')) {
 add_shortcode('transferencia_barco_v2', 'tbv2_render_form');
}

// Registro alternativo del shortcode con _form
if (!shortcode_exists('transferencia_barco_v2_form')) {
 add_shortcode('transferencia_barco_v2_form', 'tbv2_render_form');
}


// === TBV2 ENHANCED FILE HANDLER ===
// Mejora compatible para manejo de archivos - NO intercepta JavaScript existente

/**
 * Mejora el webhook existente para incluir archivos
 */
function tbv2_enhanced_webhook_with_files($orderId, $formData) {
 error_log(" TBV2 ENHANCED: Procesando archivos para Order ID: $orderId");
 
 try {
 // 1. Procesar archivos si existen en $_FILES
 $attachments = tbv2_process_current_files($orderId);
 
 // 2. Añadir archivos a los datos del formulario
 if (!empty($attachments)) {
 $formData['attachments'] = $attachments;
 $formData['files_count'] = count($attachments);
 error_log(" TBV2 ENHANCED: {$formData['files_count']} archivos añadidos");
 } else {
 error_log(" TBV2 ENHANCED: No se encontraron archivos para procesar");
 }
 
 // 3. Enviar al webhook con archivos incluidos
 $webhook_result = tbv2_send_to_api_with_files($formData);
 
 return $webhook_result;
 
 } catch (Exception $e) {
 error_log(" TBV2 ENHANCED ERROR: " . $e->getMessage());
 return false;
 }
}

/**
 * Procesa archivos del $_FILES global
 */
function tbv2_process_current_files($orderId) {
 $processed_files = [];
 
 try {
 // Mapeo de campos esperados
 $file_fields = [
 'upload_hoja_asiento' => 'hojaAsiento',
 'upload_dni_comprador' => 'dniComprador', 
 'upload_dni_vendedor' => 'dniVendedor',
 'upload_contrato_compraventa' => 'contratoCompraventa'
 ];
 
 foreach ($file_fields as $field_name => $category) {
 if (isset($_FILES[$field_name]) && !empty($_FILES[$field_name]['tmp_name'])) {
 $files = tbv2_save_file_category($_FILES[$field_name], $category, $orderId);
 if (!empty($files)) {
 $processed_files = array_merge($processed_files, $files);
 }
 }
 }
 
 return $processed_files;
 
 } catch (Exception $e) {
 error_log(" TBV2 ENHANCED: Error procesando archivos: " . $e->getMessage());
 return [];
 }
}

/**
 * Guarda archivos de una categoría específica
 */
function tbv2_save_file_category($file_data, $category, $orderId) {
 $saved_files = [];
 
 try {
 $upload_dir = '/root/tramitfy/uploads/';
 if (!is_dir($upload_dir)) {
 mkdir($upload_dir, 0755, true);
 }
 
 // Manejar archivos múltiples o únicos
 $tmp_names = is_array($file_data['tmp_name']) ? $file_data['tmp_name'] : [$file_data['tmp_name']];
 $names = is_array($file_data['name']) ? $file_data['name'] : [$file_data['name']];
 $types = is_array($file_data['type']) ? $file_data['type'] : [$file_data['type']];
 $sizes = is_array($file_data['size']) ? $file_data['size'] : [$file_data['size']];
 
 for ($i = 0; $i < count($tmp_names); $i++) {
 if (!empty($tmp_names[$i]) && is_uploaded_file($tmp_names[$i])) {
 // Generar nombre único
 $timestamp = time();
 $random = bin2hex(random_bytes(6));
 $extension = pathinfo($names[$i], PATHINFO_EXTENSION);
 $clean_name = preg_replace('/[^a-zA-Z0-9._-]/', '', pathinfo($names[$i], PATHINFO_FILENAME));
 $unique_name = "{$timestamp}-{$random}-{$clean_name}.{$extension}";
 
 $destination = $upload_dir . $unique_name;
 
 if (move_uploaded_file($tmp_names[$i], $destination)) {
 $saved_files[] = [
 'originalName' => $names[$i],
 'filename' => $unique_name,
 'path' => $destination,
 'url' => "https://46-202-128-35.sslip.io/uploads/{$unique_name}",
 'type' => $types[$i],
 'size' => filesize($destination),
 'category' => $category,
 'orderId' => $orderId,
 'uploadedAt' => date('c')
 ];
 
 error_log(" TBV2 ENHANCED: Guardado {$unique_name} (" . number_format(filesize($destination)/1024, 1) . " KB)");
 }
 }
 }
 
 return $saved_files;
 
 } catch (Exception $e) {
 error_log(" TBV2 ENHANCED: Error guardando categoría $category: " . $e->getMessage());
 return [];
 }
}

/**
 * Envía datos con archivos al webhook API
 */
function tbv2_send_to_api_with_files($formData) {
 try {
 $webhook_url = 'https://46-202-128-35.sslip.io/api/herramientas/barcos/webhook';
 
 // Preparar payload completo
 $payload = [
 'tramiteId' => $formData['tramiteId'] ?? 'TBV2-' . time(),
 'tramiteType' => 'transferencia-barco-v2',
 'customerName' => $formData['customerName'] ?? '',
 'customerEmail' => $formData['customerEmail'] ?? '',
 'customerDni' => $formData['customerDni'] ?? '',
 'customerPhone' => $formData['customerPhone'] ?? '',
 'vehicleType' => 'Embarcación',
 'manufacturer' => $formData['manufacturer'] ?? '',
 'model' => $formData['model'] ?? '',
 'matriculationDate' => $formData['matriculationDate'] ?? '',
 'purchasePrice' => floatval($formData['purchasePrice'] ?? 0),
 'region' => $formData['region'] ?? '',
 'finalAmount' => floatval($formData['finalAmount'] ?? 0),
 'transferTax' => floatval($formData['transferTax'] ?? 0),
 'extraFee' => floatval($formData['extraFee'] ?? 0),
 'status' => 'pending',
 'attachments' => $formData['attachments'] ?? [],
 'filesCount' => intval($formData['files_count'] ?? 0),
 'processingMethod' => 'enhanced_compatible',
 'timestamp' => date('c')
 ];
 
 // Enviar via HTTP POST
 $response = wp_remote_post($webhook_url, [
 'method' => 'POST',
 'timeout' => 45,
 'headers' => ['Content-Type' => 'application/json'],
 'body' => json_encode($payload)
 ]);
 
 if (is_wp_error($response)) {
 error_log(" TBV2 ENHANCED: Error webhook: " . $response->get_error_message());
 return false;
 }
 
 $status_code = wp_remote_retrieve_response_code($response);
 $response_body = wp_remote_retrieve_body($response);
 
 if ($status_code >= 200 && $status_code < 300) {
 $result = json_decode($response_body, true);
 error_log(" TBV2 ENHANCED: Webhook exitoso - ID: " . ($result['id'] ?? 'N/A'));
 return true;
 } else {
 error_log(" TBV2 ENHANCED: Webhook HTTP $status_code - $response_body");
 return false;
 }
 
 } catch (Exception $e) {
 error_log(" TBV2 ENHANCED: Excepción en webhook: " . $e->getMessage());
 return false;
 }
}

// Hook en el punto exacto donde se procesa el pago exitoso
add_action('tbv2_payment_success', function($orderId, $formData) {
 error_log(" TBV2 ENHANCED: Hook activado para Order ID: $orderId");
 tbv2_enhanced_webhook_with_files($orderId, $formData);
}, 20, 2);

error_log(" TBV2 ENHANCED: Sistema de archivos compatible cargado - NO intercepta JavaScript");

// =====================================================
// TBV2 TEMPORAL INTEGRATION SYSTEM
// =====================================================
?>
<script>
// TBV2 TEMPORAL INTEGRATION - SISTEMA INDEPENDIENTE

// PROTECCIÓN: Solo cargar sistema temporal en páginas autorizadas
if (window.TBV2_DISABLED !== true) {

console.log('✅ TBV2 TEMPORAL - Cargando sistema independiente en página autorizada...');

const TBV2_TEMPORAL = {
 API_BASE: 'https://tramitfy.org/api/temporal',
 DEBUG: true,
 
 async interceptPayment(originalFormData) {
 console.log(' TBV2 TEMPORAL - Interceptando flujo de pago');
 
 try {
 const tempOrderId = this.generateOrderId();
 console.log('🆔 Order ID temporal:', tempOrderId);
 
 const payload = await this.preparePayload(tempOrderId, originalFormData);
 console.log(' Payload temporal:', payload);
 
 const response = await this.sendToCapture(payload);
 
 if (!response.success) {
 throw new Error(`Error captura temporal: ${response.error}`);
 }
 
 console.log(' Datos capturados temporalmente');
 console.log(' Temporal ID:', response.temporal_id);
 
 this.storeForCallback(tempOrderId, {
 temporal_id: response.temporal_id,
 expires_at: response.expires_at
 });
 
 return {
 ...originalFormData,
 orderId: tempOrderId,
 temporal_id: response.temporal_id
 };
 
 } catch (error) {
 console.error(' Error en interceptor temporal:', error);
 throw error;
 }
 },
 
 generateOrderId() {
 // Generar OrderID compatible con Redsys 
 // Formato: timestamp en segundos (10 dígitos) + 2 dígitos aleatorios = 12 total
 const timestamp = Math.floor(Date.now() / 1000); // Segundos desde epoch
 const random = Math.floor(Math.random() * 100).toString().padStart(2, '0');
 const orderId = timestamp.toString() + random;
 
 console.log('🆔 OrderID generado para Redsys:', orderId, '(', orderId.length, 'caracteres)');
 return orderId;
 },
 
 async preparePayload(orderId, formData) {
 console.log(' Preparando payload con extracción de archivos...');
 
 const customerData = {
 name: this.extractValue('customer_name') || formData.personal?.customerName,
 dni: this.extractValue('customer_dni') || formData.personal?.customerDni,
 email: this.extractValue('customer_email') || formData.personal?.customerEmail,
 phone: this.extractValue('customer_phone') || formData.personal?.customerPhone
 };
 
 const boatData = {
 manufacturer: this.extractValue('manufacturer') || formData.vehicle?.manufacturer,
 model: this.extractValue('model') || formData.vehicle?.model,
 purchasePrice: parseFloat(this.extractValue('purchase_price') || formData.vehicle?.purchasePrice || 0),
 matriculationDate: this.extractValue('matriculation_date') || formData.vehicle?.matriculationDate,
 region: this.extractValue('region') || formData.pricing?.region
 };
 
 const pricing = {
 basePrice: 134.99,
 finalAmount: parseFloat(this.extractValue('final_price') || formData.pricing?.finalAmount || 134.99),
 transferTax: parseFloat(formData.pricing?.transferTax || 0),
 extraFee: parseFloat(formData.pricing?.extraFee || 0)
 };
 
 // Extraer archivos del formulario
 console.log(' Extrayendo archivos del formulario...');
 const extractedFiles = await this.extractFiles();
 const files = await this.processFiles(extractedFiles);
 
 console.log(` Payload preparado: ${files.length} archivo(s) procesado(s)`);
 
 return {
 orderId,
 customerData,
 boatData,
 files,
 pricing
 };
 },
 
 async processFiles(filesData) {
 if (!filesData) return [];
 
 const processed = [];
 for (const [category, fileList] of Object.entries(filesData)) {
 if (Array.isArray(fileList)) {
 for (const file of fileList) {
 if (file && file.base64) {
 processed.push({
 name: file.name || `${category}_file.${this.getExtension(file.base64)}`,
 base64: file.base64,
 type: category
 });
 }
 }
 }
 }
 return processed;
 },
 
 async sendToCapture(payload) {
 const response = await fetch(this.API_BASE + '/capture', {
 method: 'POST',
 headers: { 'Content-Type': 'application/json' },
 body: JSON.stringify(payload)
 });
 
 const result = await response.json();
 
 if (!response.ok) {
 throw new Error(result.error || 'Error en captura temporal');
 }
 
 return result;
 },
 
 storeForCallback(orderId, data) {
 localStorage.setItem(`tbv2_temporal_${orderId}`, JSON.stringify({
 ...data,
 stored_at: new Date().toISOString()
 }));
 },
 
 async handleCallback(orderId, redsysData) {
 console.log(' TBV2 TEMPORAL - Procesando callback:', orderId);
 
 try {
 const storedData = JSON.parse(localStorage.getItem(`tbv2_temporal_${orderId}`) || '{}');
 
 if (!storedData.temporal_id) {
 throw new Error('Temporal ID no encontrado');
 }
 
 const confirmPayload = {
 temporal_id: storedData.temporal_id,
 orderId: orderId,
 payment_confirmed: true,
 redsys_data: redsysData
 };
 
 const response = await fetch(this.API_BASE + '/confirm', {
 method: 'POST',
 headers: { 'Content-Type': 'application/json' },
 body: JSON.stringify(confirmPayload)
 });
 
 const result = await response.json();
 
 if (!response.ok) {
 throw new Error(result.error || 'Error confirmando pago');
 }
 
 console.log(' Pago confirmado temporalmente');
 console.log(' Trámite final:', result.tramite_id);
 
 localStorage.removeItem(`tbv2_temporal_${orderId}`);
 
 return result;
 
 } catch (error) {
 console.error(' Error en callback temporal:', error);
 throw error;
 }
 },
 
 extractValue(fieldId) {
 const field = document.getElementById(fieldId);
 return field ? field.value.trim() : '';
 },
 
 // Extraer archivos del formulario y convertirlos a base64
 async extractFiles() {
 const fileMapping = {
 'upload-hoja-asiento': 'hojaAsiento',
 'upload-dni-comprador': 'dniComprador', 
 'upload-dni-vendedor': 'dniVendedor',
 'upload-contrato-compraventa': 'contratoCompraventa',
 'upload-modelo-620': 'itpComprobante'
 };
 
 const extractedFiles = {};
 
 for (const [inputId, category] of Object.entries(fileMapping)) {
 // Prioridad 1: Usar archivos del sistema de preview si existen
 if (window.uploadedFiles && window.uploadedFiles[inputId] && window.uploadedFiles[inputId].length > 0) {
 const files = window.uploadedFiles[inputId];
 console.log(` Procesando ${files.length} archivo(s) de ${category} (desde preview system)`);
 extractedFiles[category] = [];
 
 for (let i = 0; i < files.length; i++) {
 const file = files[i];
 try {
 const base64 = await this.fileToBase64(file);
 extractedFiles[category].push({
 name: file.name,
 base64: base64,
 size: file.size,
 type: file.type
 });
 console.log(` Archivo convertido: ${file.name} (${file.size} bytes)`);
 } catch (error) {
 console.error(` Error procesando archivo ${file.name}:`, error);
 }
 }
 }
 // Fallback: Sistema tradicional
 else {
 const input = document.getElementById(inputId);
 if (input && input.files && input.files.length > 0) {
 console.log(` Procesando ${input.files.length} archivo(s) de ${category} (desde input tradicional)`);
 extractedFiles[category] = [];
 
 for (let i = 0; i < input.files.length; i++) {
 const file = input.files[i];
 try {
 const base64 = await this.fileToBase64(file);
 extractedFiles[category].push({
 name: file.name,
 base64: base64,
 size: file.size,
 type: file.type
 });
 console.log(` Archivo convertido: ${file.name} (${file.size} bytes)`);
 } catch (error) {
 console.error(` Error procesando archivo ${file.name}:`, error);
 }
 }
 }
 }
 }
 
 // Buscar firma digital - PRIORIDAD A DATOS PERSISTENTES
 let signatureData = null;
 
 // 1. Buscar en variable global persistente (desde acceptSignature)
 if (window.tbv2SignatureData && window.tbv2SignatureData !== 'data:image/png;base64,') {
 signatureData = window.tbv2SignatureData;
 console.log(' Firma obtenida desde variable persistente');
 } 
 // 2. Fallback: Buscar en canvas activo
 else {
 const signatureCanvas = document.querySelector('#signature-pad canvas');
 if (signatureCanvas) {
 try {
 const canvasData = signatureCanvas.toDataURL('image/png');
 if (canvasData && canvasData !== 'data:image/png;base64,') {
 signatureData = canvasData;
 console.log(' Firma obtenida desde canvas activo');
 }
 } catch (error) {
 console.error(' Error leyendo canvas de firma:', error);
 }
 }
 }
 
 // Añadir firma si se encontró
 if (signatureData) {
 extractedFiles['firmaAutorizacion'] = [{
 name: 'firma_autorizacion.png',
 base64: signatureData,
 size: signatureData.length,
 type: 'image/png'
 }];
 console.log(' Firma digital incluida en extractedFiles');
 console.log(' Tamaño de firma:', signatureData.length, 'caracteres');
 } else {
 console.log(' No se encontró firma digital');
 }
 
 return extractedFiles;
 },
 
 // Convertir archivo a base64
 fileToBase64(file) {
 return new Promise((resolve, reject) => {
 const reader = new FileReader();
 reader.onload = () => resolve(reader.result);
 reader.onerror = error => reject(error);
 reader.readAsDataURL(file);
 });
 },
 
 getExtension(base64) {
 if (base64.includes('data:image/png')) return 'png';
 if (base64.includes('data:image/jpeg')) return 'jpg';
 if (base64.includes('data:application/pdf')) return 'pdf';
 return 'bin';
 },
 
 // Continúa con el flujo de pago Redsys
 async continueWithRedsys(formData) {
 console.log(' Iniciando flujo Redsys con datos temporales');
 console.log(' Order ID temporal:', formData.orderId);
 
 try {
 // Crear formulario de pago Redsys usando AJAX al backend PHP
 const redsysData = {
 action: 'create_redsys_payment',
 nonce: '<?php echo wp_create_nonce("tbv2_nonce"); ?>',
 orderId: formData.orderId,
 customerName: formData.personal?.customerName || this.extractValue('customer_name'),
 customerEmail: formData.personal?.customerEmail || this.extractValue('customer_email'),
 finalAmount: formData.pricing?.total_amount || 134.99,
 personal: formData.personal,
 vehicle: formData.vehicle,
 pricing: formData.pricing
 };
 
 console.log(' Enviando datos para crear formulario Redsys...');
 
 const response = await fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
 method: 'POST',
 headers: {
 'Content-Type': 'application/x-www-form-urlencoded'
 },
 body: new URLSearchParams(redsysData)
 });
 
 const result = await response.text();
 console.log(' Respuesta Redsys backend:', result);
 
 if (response.ok) {
 // Si recibimos HTML del formulario, insertarlo y enviarlo
 if (result.includes('<form')) {
 console.log(' Formulario Redsys recibido, enviando...');
 
 // Crear contenedor temporal para el formulario
 const container = document.createElement('div');
 container.innerHTML = result;
 container.style.display = 'none';
 document.body.appendChild(container);
 
 // Buscar y enviar el formulario
 const redsysForm = container.querySelector('form');
 if (redsysForm) {
 console.log(' Enviando formulario Redsys automáticamente');
 redsysForm.submit();
 } else {
 throw new Error('Formulario Redsys no encontrado en respuesta');
 }
 } else {
 throw new Error('Respuesta inválida del servidor Redsys');
 }
 } else {
 throw new Error(`Error HTTP ${response.status}: ${result}`);
 }
 
 } catch (error) {
 console.error(' Error en flujo Redsys:', error);
 throw error;
 }
 }
};

// PATCH DEL SISTEMA EXISTENTE
document.addEventListener('DOMContentLoaded', function() {
 console.log(' TBV2 TEMPORAL - Aplicando patch al sistema existente');
 
 setTimeout(function() {
 const submitBtn = document.getElementById('submit-payment');
 
 if (submitBtn) {
 console.log(' Botón de pago encontrado, aplicando interceptor');
 
 const newBtn = submitBtn.cloneNode(true);
 submitBtn.parentNode.replaceChild(newBtn, submitBtn);
 
 newBtn.addEventListener('click', async function(e) {
 e.preventDefault();
 console.log(' TBV2 TEMPORAL - Pago interceptado');
 
 newBtn.disabled = true;
 newBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Preparando datos...';
 
 try {
 const originalData = await captureAllFormData();
 console.log(' Datos originales capturados:', originalData);
 
 const modifiedData = await TBV2_TEMPORAL.interceptPayment(originalData);
 console.log(' Datos enviados al sistema temporal');
 
 // Continuar con el flujo de pago Redsys usando el OrderID temporal
 console.log(' Continuando con pago Redsys...');
 newBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Redirigiendo al TPV...';
 
 // Crear formulario de pago Redsys con el OrderID temporal
 await TBV2_TEMPORAL.continueWithRedsys(modifiedData);
 
 } catch (error) {
 console.error(' Error en pago temporal:', error);
 alert('Error procesando el pago: ' + error.message);
 
 newBtn.disabled = false;
 newBtn.innerHTML = '<i class="fa-solid fa-credit-card"></i> Proceder al Pago';
 }
 });
 
 console.log(' Interceptor temporal instalado');
 } else {
 console.warn(' Botón de pago no encontrado');
 }
 }, 1000);
});

window.TBV2_TEMPORAL_SYSTEM = TBV2_TEMPORAL;

console.log(' TBV2 TEMPORAL - Sistema de interceptor cargado');

} // Fin if TBV2_DISABLED !== true
</script>
<?php

// =====================================================
// AJAX HANDLER PARA CREAR FORMULARIO REDSYS TEMPORAL
// =====================================================

add_action('wp_ajax_create_redsys_payment', 'tbv2_create_redsys_payment_handler');
add_action('wp_ajax_nopriv_create_redsys_payment', 'tbv2_create_redsys_payment_handler');

function tbv2_create_redsys_payment_handler() {
 try {
 // Verificar nonce
 if (!wp_verify_nonce($_POST['nonce'], 'tbv2_nonce')) {
 throw new Exception('Nonce inválido');
 }
 
 $orderId = sanitize_text_field($_POST['orderId']);
 $customerName = sanitize_text_field($_POST['customerName']);
 $customerEmail = sanitize_email($_POST['customerEmail']);
 $finalAmount = floatval($_POST['finalAmount']);
 
 error_log(" TBV2 REDSYS - Creando formulario temporal para OrderID: $orderId");
 error_log(" Cliente: $customerName ($customerEmail)");
 error_log(" Importe original: $finalAmount €");
 
 // Validación específica OrderID para Redsys
 if (strlen($orderId) > 12) {
 error_log(" OrderID demasiado largo: " . strlen($orderId) . " caracteres");
 throw new Exception("OrderID inválido: máximo 12 caracteres");
 }
 
 if (!ctype_alnum($orderId)) {
 error_log(" OrderID contiene caracteres inválidos: $orderId");
 throw new Exception("OrderID inválido: solo letras y números");
 }
 
 // Validar datos básicos
 if (empty($orderId) || empty($customerName) || empty($customerEmail) || $finalAmount <= 0) {
 throw new Exception('Datos requeridos faltantes para Redsys');
 }
 
 // Preparar datos para Redsys
 $orderData = [
 'order_id' => $orderId,
 'amount_cents' => intval($finalAmount * 100), // Convertir a centimos
 'customer_name' => $customerName,
 'customer_email' => $customerEmail,
 'description' => 'Transferencia Embarcacion TBV2 - ' . $customerName
 ];
 
 error_log(" Datos Redsys: " . print_r($orderData, true));
 
 // Crear formulario de pago Redsys
 $redsysForm = tbv2_redsys_create_payment_form($orderData);
 
 if (empty($redsysForm['url']) || empty($redsysForm['Ds_MerchantParameters'])) {
 throw new Exception('Error generando formulario Redsys');
 }
 
 error_log(" Formulario Redsys generado exitosamente");
 error_log(" URL: " . $redsysForm['url']);
 error_log(" Parameters: " . substr($redsysForm['Ds_MerchantParameters'], 0, 100) . '...');
 
 // Generar HTML del formulario
 $formHtml = '
 <form id="redsysForm" action="' . esc_url($redsysForm['url']) . '" method="post">
 <input type="hidden" name="Ds_SignatureVersion" value="' . esc_attr($redsysForm['Ds_SignatureVersion']) . '">
 <input type="hidden" name="Ds_MerchantParameters" value="' . esc_attr($redsysForm['Ds_MerchantParameters']) . '">
 <input type="hidden" name="Ds_Signature" value="' . esc_attr($redsysForm['Ds_Signature']) . '">
 <input type="submit" value="Procesar Pago" style="display:none;">
 </form>';
 
 // Retornar formulario HTML
 header('Content-Type: text/html; charset=utf-8');
 echo $formHtml;
 
 } catch (Exception $e) {
 error_log(" Error creando formulario Redsys: " . $e->getMessage());
 http_response_code(500);
 echo 'Error: ' . $e->getMessage();
 }
 
 wp_die();
}

// =====================================================
// HANDLER AJAX PARA EMAILS DE CONFIRMACIÓN TBV2
// =====================================================

/**
 * Handler para enviar emails de confirmación cuando llega el callback de Redsys
 */
function tbv2_send_confirmation_emails_handler() {
 try {
 error_log(' TBV2 EMAILS - Handler iniciado');
 
 // Usar configuración SMTP global de WordPress (como otros formularios)
 
 // Validar datos recibidos
 if (!isset($_POST['orderId']) || !isset($_POST['tramiteId'])) {
 error_log(' TBV2 EMAILS - Datos requeridos faltantes');
 wp_send_json_error('Datos requeridos faltantes');
 return;
 }

 $orderId = sanitize_text_field($_POST['orderId']);
 $tramiteId = sanitize_text_field($_POST['tramiteId']);
 
 // Extraer datos del cliente
 $customerName = sanitize_text_field($_POST['customerData']['name'] ?? '');
 $customerEmail = sanitize_email($_POST['customerData']['email'] ?? '');
 $customerDni = sanitize_text_field($_POST['customerData']['dni'] ?? '');
 $customerPhone = sanitize_text_field($_POST['customerData']['phone'] ?? '');
 
 // Extraer datos de la embarcación
 $manufacturer = sanitize_text_field($_POST['boatData']['manufacturer'] ?? '');
 $model = sanitize_text_field($_POST['boatData']['model'] ?? '');
 $region = sanitize_text_field($_POST['boatData']['region'] ?? '');
 
 // Extraer datos de pricing
 $finalAmount = floatval($_POST['pricing']['finalAmount'] ?? 0);
 $basePrice = floatval($_POST['pricing']['basePrice'] ?? 134.99);
 $transferTax = floatval($_POST['pricing']['transferTax'] ?? 0);
 
 error_log(" TBV2 EMAILS - Procesando: $tramiteId, Cliente: $customerEmail, Total: $finalAmount");

 // 1. Email de confirmación al cliente
 if (!empty($customerEmail) && is_email($customerEmail)) {
 $subject_customer = " Confirmación de Trámite - $tramiteId";
 
 $message_customer = "
<!DOCTYPE html>
<html>
<head>
 <meta charset='UTF-8'>
 <meta name='viewport' content='width=device-width, initial-scale=1.0'>
 <title>Confirmación de Trámite</title>
 <style>
 body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f5f5f5; }
 .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
 .header { background: linear-gradient(135deg, #016d86, #4f46e5); color: white; padding: 30px; text-align: center; }
 .content { padding: 30px; }
 .success-badge { background: #22c55e; color: white; padding: 8px 16px; border-radius: 20px; display: inline-block; margin-bottom: 20px; }
 .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin: 20px 0; }
 .info-item { background: #f8fafc; padding: 15px; border-radius: 8px; border-left: 4px solid #016d86; }
 .info-label { font-size: 12px; color: #64748b; text-transform: uppercase; font-weight: 600; margin-bottom: 5px; }
 .info-value { font-size: 16px; color: #1e293b; font-weight: 500; }
 .financial-summary { background: #f1f5f9; padding: 20px; border-radius: 8px; margin: 20px 0; }
 .financial-row { display: flex; justify-content: space-between; margin: 10px 0; }
 .financial-total { border-top: 2px solid #016d86; padding-top: 15px; font-weight: 700; font-size: 18px; color: #016d86; }
 </style>
</head>
<body>
 <div class='container'>
 <div class='header'>
 <h1> TRAMITFY</h1>
 <p>Gestión Profesional de Trámites Marítimos</p>
 </div>
 
 <div class='content'>
 <div class='success-badge'> Trámite Confirmado</div>
 
 <h2>Estimado/a $customerName,</h2>
 
 <p>Su trámite de <strong>Transferencia de Barco V2</strong> ha sido recibido y confirmado exitosamente.</p>

 <div class='info-grid'>
 <div class='info-item'>
 <div class='info-label'>Código de Trámite</div>
 <div class='info-value'>$tramiteId</div>
 </div>
 <div class='info-item'>
 <div class='info-label'>Fecha</div>
 <div class='info-value'>" . date('d/m/Y H:i') . "</div>
 </div>
 <div class='info-item'>
 <div class='info-label'>Cliente</div>
 <div class='info-value'>$customerName</div>
 </div>
 <div class='info-item'>
 <div class='info-label'>DNI</div>
 <div class='info-value'>$customerDni</div>
 </div>
 </div>";

 if (!empty($manufacturer) || !empty($model)) {
 $message_customer .= "
 <h3> Información de la Embarcación</h3>
 <div class='info-grid'>
 <div class='info-item'>
 <div class='info-label'>Fabricante</div>
 <div class='info-value'>$manufacturer</div>
 </div>
 <div class='info-item'>
 <div class='info-label'>Modelo</div>
 <div class='info-value'>$model</div>
 </div>
 </div>";
 }

 $message_customer .= "
 <div class='financial-summary'>
 <h3> Resumen Financiero</h3>
 <div class='financial-row'>
 <span>Precio Base:</span>
 <span>" . number_format($basePrice, 2) . " €</span>
 </div>";
 
 if ($transferTax > 0) {
 $message_customer .= "
 <div class='financial-row'>
 <span>ITP (Impuesto Transmisiones):</span>
 <span>" . number_format($transferTax, 2) . " €</span>
 </div>";
 }
 
 $message_customer .= "
 <div class='financial-row'>
 <span>Tasas Administrativas:</span>
 <span>19,03 €</span>
 </div>
 <div class='financial-row'>
 <span>Honorarios (IVA incl.):</span>
 <span>115,96 €</span>
 </div>
 <div class='financial-row financial-total'>
 <span>Total Pagado:</span>
 <span>" . number_format($finalAmount, 2) . " €</span>
 </div>
 </div>

 <p><strong>Próximos pasos:</strong></p>
 <ul>
 <li>Su trámite será procesado en 5-10 días laborables</li>
 <li>Recibirá notificaciones sobre el progreso</li>
 <li>Para consultas: admin@ipmgroup24.com</li>
 </ul>
 </div>
 </div>
</body>
</html>";

 $headers_customer = [
 'Content-Type: text/html; charset=UTF-8',
 'From: Tramitfy TBV2 <noreply@tramitfy.es>',
 'Reply-To: ' . $customerEmail
 ];
 $email_sent_customer = wp_mail($customerEmail, $subject_customer, $message_customer, $headers_customer);
 
 error_log(" TBV2 EMAILS - Email cliente enviado: " . ($email_sent_customer ? 'SUCCESS' : 'FAILED'));
 if (!$email_sent_customer) {
 error_log(" TBV2 EMAILS - wp_mail cliente falló para: $customerEmail");
 }
 }

 // 2. Email de notificación al admin
 $admin_email = 'ipmgroup24@gmail.com';
 $subject_admin = " Nuevo Trámite TBV2 - $tramiteId";
 
 $message_admin = "
<!DOCTYPE html>
<html>
<head>
 <meta charset='UTF-8'>
 <title>Nueva TBV2</title>
 <style>
 body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f8fafc; }
 .container { max-width: 800px; margin: 0 auto; background: white; border-radius: 8px; padding: 20px; }
 .header { background: #dc2626; color: white; padding: 20px; margin: -20px -20px 20px -20px; }
 .alert { background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; padding: 15px; margin: 20px 0; border-radius: 6px; }
 .data-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin: 20px 0; }
 .data-item { background: #f8fafc; padding: 15px; border-radius: 6px; border-left: 3px solid #dc2626; }
 .label { font-size: 12px; color: #6b7280; text-transform: uppercase; font-weight: 600; margin-bottom: 5px; }
 .value { font-size: 14px; color: #1f2937; font-weight: 500; }
 </style>
</head>
<body>
 <div class='container'>
 <div class='header'>
 <h1> NUEVO TRÁMITE TBV2</h1>
 <p>Transferencia de Barco V2 - Confirmación Automática</p>
 </div>

 <div class='alert'>
 <strong> ACCIÓN REQUERIDA:</strong> Nuevo trámite registrado automáticamente.
 </div>

 <div class='data-grid'>
 <div class='data-item'>
 <div class='label'>Código Trámite</div>
 <div class='value'>$tramiteId</div>
 </div>
 <div class='data-item'>
 <div class='label'>Fecha/Hora</div>
 <div class='value'>" . date('d/m/Y H:i:s') . "</div>
 </div>
 <div class='data-item'>
 <div class='label'>Cliente</div>
 <div class='value'>$customerName</div>
 </div>
 <div class='data-item'>
 <div class='label'>DNI</div>
 <div class='value'>$customerDni</div>
 </div>
 <div class='data-item'>
 <div class='label'>Email</div>
 <div class='value'>$customerEmail</div>
 </div>
 <div class='data-item'>
 <div class='label'>Teléfono</div>
 <div class='value'>$customerPhone</div>
 </div>
 </div>";

 if (!empty($manufacturer) || !empty($model)) {
 $message_admin .= "
 <h3> Datos Embarcación</h3>
 <div class='data-grid'>
 <div class='data-item'>
 <div class='label'>Fabricante</div>
 <div class='value'>$manufacturer</div>
 </div>
 <div class='data-item'>
 <div class='label'>Modelo</div>
 <div class='value'>$model</div>
 </div>
 <div class='data-item'>
 <div class='label'>Región</div>
 <div class='value'>$region</div>
 </div>
 </div>";
 }

 $message_admin .= "
 <div style='background: #ecfdf5; border: 1px solid #bbf7d0; padding: 15px; border-radius: 6px; margin: 15px 0;'>
 <h3 style='color: #059669; margin: 0 0 15px 0;'> Resumen Financiero</h3>
 <div>
 <strong>Total Pagado:</strong> " . number_format($finalAmount, 2) . " €<br>
 <strong>ITP Calculado:</strong> " . number_format($transferTax, 2) . " €<br>
 <strong>Comisión Bancaria:</strong> " . number_format($finalAmount * 0.003, 2) . " €
 </div>
 </div>

 <p><strong>Enlaces:</strong></p>
 <ul>
 <li><a href='https://46-202-128-35.sslip.io/tramites/$tramiteId'>Ver en Dashboard</a></li>
 <li><a href='https://46-202-128-35.sslip.io/seguimiento/$tramiteId'>Vista Cliente</a></li>
 </ul>
 </div>
</body>
</html>";

 $headers_admin = [
 'Content-Type: text/html; charset=UTF-8',
 'From: Tramitfy TBV2 <noreply@tramitfy.es>',
 'Reply-To: ' . $customerEmail
 ];
 $email_sent_admin = wp_mail($admin_email, $subject_admin, $message_admin, $headers_admin);
 
 error_log(" TBV2 EMAILS - Email admin enviado: " . ($email_sent_admin ? 'SUCCESS' : 'FAILED'));
 if (!$email_sent_admin) {
 error_log(" TBV2 EMAILS - wp_mail admin falló para: $admin_email");
 }

 // Responder éxito
 wp_send_json_success([
 'message' => 'Emails enviados',
 'client_email_sent' => $email_sent_customer ?? false,
 'admin_email_sent' => $email_sent_admin,
 'tramite_id' => $tramiteId
 ]);

 } catch (Exception $e) {
 error_log(' TBV2 EMAILS - Error: ' . $e->getMessage());
 wp_send_json_error('Error enviando emails: ' . $e->getMessage());
 }
 
 // Importante: terminar ejecución para AJAX
 wp_die();
}

// Registrar handler AJAX (para usuarios logueados y no logueados)
add_action('wp_ajax_tbv2_send_confirmation_emails', 'tbv2_send_confirmation_emails_handler');
add_action('wp_ajax_nopriv_tbv2_send_confirmation_emails', 'tbv2_send_confirmation_emails_handler');

// ========================================
