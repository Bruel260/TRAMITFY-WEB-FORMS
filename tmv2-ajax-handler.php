<?php
/**
 * TMV2 - AJAX HANDLERS ONLY
 * Solo funciones AJAX sin HTML/CSS/JS
 */

if (!defined('ABSPATH')) {
    exit('Acceso directo no permitido.');
}

// Solo cargar si es AJAX TMV2
if (!(defined('DOING_AJAX') && DOING_AJAX) || !in_array($_POST['action'] ?? '', ['tmv2_create_redsys_payment', 'tmv2_create_redsys_payment_generic', 'tmv2_store_files', 'tmv2_send_confirmation_emails'])) {
    return;
}

// Configuración mínima Redsys
if (!defined('TMV2_REDSYS_MODE')) define('TMV2_REDSYS_MODE', 'test');
if (!defined('TMV2_REDSYS_MERCHANT_CODE')) define('TMV2_REDSYS_MERCHANT_CODE', '363391103');
if (!defined('TMV2_REDSYS_TERMINAL')) define('TMV2_REDSYS_TERMINAL', '1');
if (!defined('TMV2_REDSYS_CURRENCY')) define('TMV2_REDSYS_CURRENCY', '978');
if (!defined('TMV2_REDSYS_SECRET_KEY')) define('TMV2_REDSYS_SECRET_KEY', 'sq7HjrUOBfKmC576ILgskD5srU870gJ7');
if (!defined('TMV2_REDSYS_SIGNATURE_VERSION')) define('TMV2_REDSYS_SIGNATURE_VERSION', 'HMAC_SHA256_V1');
if (!defined('TMV2_REDSYS_URL_TEST')) define('TMV2_REDSYS_URL_TEST', 'https://sis-t.redsys.es:25443/sis/realizarPago');
if (!defined('TMV2_REDSYS_URL_LIVE')) define('TMV2_REDSYS_URL_LIVE', 'https://sis.redsys.es/sis/realizarPago');

// SOLO LA FUNCIÓN AJAX CRÍTICA
function tmv2_handle_create_redsys_payment() {
    if (ob_get_level()) ob_end_clean();
    ob_start();
    
    // Headers JSON 
    header('Content-Type: application/json');
    
    try {
        // Datos mínimos para test
        $order_id = $_POST['orderData']['orderID'] ?? '123456789012';
        $amount = (int) ($_POST['orderData']['amount'] ?? 13499); // centimos
        
        $response = [
            'success' => true,
            'orderID' => $order_id,
            'amount' => $amount,
            'test' => 'TMV2 AJAX funcionando'
        ];
        
        echo json_encode($response);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    
    wp_die();
}

// Registrar acción
add_action('wp_ajax_tmv2_create_redsys_payment', 'tmv2_handle_create_redsys_payment');
add_action('wp_ajax_nopriv_tmv2_create_redsys_payment', 'tmv2_handle_create_redsys_payment');
?>