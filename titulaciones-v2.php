<?php
/**
 * TRAMITFY - TITULACIONES NÁUTICAS V2 CON REDSYS
 * 
 * Formulario rediseñado con selector de servicios tipo grid
 * Primera página: Selector de cuadrados para tipo de servicio
 * Segunda página: Datos personales y titulación
 * 
 * @version 2.0.0
 * @author Claude Code
 * @created 2025-11-18
 * @updated 2025-11-18
 */

if (!defined('ABSPATH')) {
    exit('Acceso directo no permitido.');
}

// =====================================================
// PROTECCIÓN TTV2 - DETECCIÓN DE PÁGINA AUTORIZADA
// =====================================================

function ttv2_is_authorized_page() {
    global $post;
    
    if (defined('DOING_AJAX') && DOING_AJAX) {
        $action = $_POST['action'] ?? $_GET['action'] ?? '';
        $ttv2_ajax_actions = [
            'ttv2_create_redsys_payment',
            'ttv2_store_temporal',
            'ttv2_send_confirmation_emails'
        ];
        
        if (!in_array($action, $ttv2_ajax_actions)) {
            return false;
        }
    }
    
    if (!defined('DOING_AJAX') && is_admin()) return true;
    
    $request_uri = $_SERVER['REQUEST_URI'] ?? '';
    $authorized_pages = [
        'titulaciones-v2',
        'renovacion-titulacion-nautica',
        'testingfy-moto'
    ];
    
    foreach ($authorized_pages as $page) {
        if (strpos($request_uri, $page) !== false) {
            return true;
        }
    }
    
    if (is_object($post)) {
        if (strpos($post->post_content, '[titulaciones_v2_form]') !== false) {
            return true;
        }
    }
    
    return false;
}

// =====================================================
// CONSTANTES DE CONFIGURACIÓN REDSYS TTV2
// =====================================================

if (!defined('TTV2_REDSYS_MODE')) define('TTV2_REDSYS_MODE', 'test');
if (!defined('TTV2_REDSYS_MERCHANT_CODE')) define('TTV2_REDSYS_MERCHANT_CODE', '363391103');
if (!defined('TTV2_REDSYS_TERMINAL')) define('TTV2_REDSYS_TERMINAL', '1');
if (!defined('TTV2_REDSYS_CURRENCY')) define('TTV2_REDSYS_CURRENCY', '978');

if (!defined('TTV2_REDSYS_SECRET_KEY')) {
    if (TTV2_REDSYS_MODE === 'test') {
        define('TTV2_REDSYS_SECRET_KEY', 'sq7HjrUOBfKmC576ILgskD5srU870gJ7');
    } else {
        define('TTV2_REDSYS_SECRET_KEY', 'ERDGGMADKbhFIngyRLnW6KrxEuKnjq9p');
    }
}

if (!defined('TTV2_REDSYS_SIGNATURE_VERSION')) define('TTV2_REDSYS_SIGNATURE_VERSION', 'HMAC_SHA256_V1');
if (!defined('TTV2_REDSYS_URL_TEST')) define('TTV2_REDSYS_URL_TEST', 'https://sis-t.redsys.es:25443/sis/realizarPago');
if (!defined('TTV2_REDSYS_URL_LIVE')) define('TTV2_REDSYS_URL_LIVE', 'https://sis.redsys.es/sis/realizarPago');
if (!defined('TTV2_REDSYS_URL_OK')) define('TTV2_REDSYS_URL_OK', 'https://tramitfy.es/pago-realizado-con-exito/');
if (!defined('TTV2_REDSYS_URL_KO')) define('TTV2_REDSYS_URL_KO', 'https://tramitfy.es/titulaciones-v2/');
if (!defined('TTV2_REDSYS_URL_NOTIFICATION')) define('TTV2_REDSYS_URL_NOTIFICATION', 'https://tramitfy.org/api/temporal/ttv2-confirm');
if (!defined('TTV2_WEBHOOK_URL')) define('TTV2_WEBHOOK_URL', 'https://tramitfy.org/api/herramientas/titulaciones-v2/webhook');

// Precios de servicios
if (!defined('TTV2_PRECIO_RENOVACION')) define('TTV2_PRECIO_RENOVACION', 65.00);
if (!defined('TTV2_PRECIO_DUPLICADO')) define('TTV2_PRECIO_DUPLICADO', 85.00);
if (!defined('TTV2_PRECIO_ACTUALIZACION')) define('TTV2_PRECIO_ACTUALIZACION', 45.00);
if (!defined('TTV2_PRECIO_AMPLIACION')) define('TTV2_PRECIO_AMPLIACION', 120.00);
if (!defined('TTV2_PRECIO_CANJE')) define('TTV2_PRECIO_CANJE', 95.00);

// =====================================================
// FUNCIONES CORE REDSYS TTV2
// =====================================================

function ttv2_redsys_generate_signature($data) {
    $password_decoded = base64_decode(TTV2_REDSYS_SECRET_KEY);
    $order_id = $data['Ds_Order'] ?? $data['Ds_Merchant_Order'] ?? '';
    
    $php_version = substr(phpversion(), 0, 1);
    
    if ($php_version >= "7") {
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
    }
    
    $string_to_sign = base64_encode(json_encode($data));
    $signature = hash_hmac('sha256', $string_to_sign, $encryption_key, true);
    
    return base64_encode($signature);
}

function ttv2_redsys_create_payment_form($order_data) {
    $redsys_url = (TTV2_REDSYS_MODE === 'test') ? TTV2_REDSYS_URL_TEST : TTV2_REDSYS_URL_LIVE;
    
    $params = [
        'Ds_Merchant_MerchantCode' => TTV2_REDSYS_MERCHANT_CODE,
        'Ds_Merchant_Terminal' => TTV2_REDSYS_TERMINAL,
        'Ds_Merchant_Order' => $order_data['order_id'],
        'Ds_Merchant_Amount' => $order_data['amount_cents'],
        'Ds_Merchant_Currency' => TTV2_REDSYS_CURRENCY,
        'Ds_Merchant_TransactionType' => '0',
        'Ds_Merchant_MerchantURL' => TTV2_REDSYS_URL_NOTIFICATION,
        'Ds_Merchant_UrlOK' => TTV2_REDSYS_URL_OK,
        'Ds_Merchant_UrlKO' => TTV2_REDSYS_URL_KO,
        'Ds_Merchant_MerchantName' => 'Tramitfy',
        'Ds_Merchant_ProductDescription' => 'Renovación Titulación Náutica',
        'Ds_Merchant_ConsumerLanguage' => '001'
    ];
    
    $signature = ttv2_redsys_generate_signature($params);
    $merchant_parameters = base64_encode(json_encode($params));
    
    return [
        'url' => $redsys_url,
        'Ds_MerchantParameters' => $merchant_parameters,
        'Ds_SignatureVersion' => TTV2_REDSYS_SIGNATURE_VERSION,
        'Ds_Signature' => $signature
    ];
}

// =====================================================
// REGISTRO DE ACCIONES AJAX
// =====================================================

if (ttv2_is_authorized_page()) {
    add_action('wp_ajax_ttv2_create_redsys_payment', 'ttv2_create_redsys_payment');
    add_action('wp_ajax_nopriv_ttv2_create_redsys_payment', 'ttv2_create_redsys_payment');
    add_action('wp_ajax_ttv2_store_temporal', 'ttv2_store_temporal');
    add_action('wp_ajax_nopriv_ttv2_store_temporal', 'ttv2_store_temporal');
}

// =====================================================
// FUNCIONES AJAX
// =====================================================

function ttv2_create_redsys_payment() {
    try {
        $order_id = str_pad(time(), 12, '0', STR_PAD_LEFT);
        $amount = floatval($_POST['amount'] ?? TTV2_PRECIO_RENOVACION);
        $amount_cents = strval(intval($amount * 100));
        
        $payment_data = ttv2_redsys_create_payment_form([
            'order_id' => $order_id,
            'amount_cents' => $amount_cents
        ]);
        
        wp_send_json_success([
            'orderId' => $order_id,
            'paymentData' => $payment_data
        ]);
        
    } catch (Exception $e) {
        wp_send_json_error(['message' => $e->getMessage()]);
    }
}

function ttv2_store_temporal() {
    try {
        $temporal_data = [
            'orderId' => $_POST['orderId'] ?? '',
            'timestamp' => current_time('mysql'),
            'customerData' => [
                'name' => sanitize_text_field($_POST['customerName'] ?? ''),
                'dni' => sanitize_text_field($_POST['customerDni'] ?? ''),
                'email' => sanitize_email($_POST['customerEmail'] ?? ''),
                'phone' => sanitize_text_field($_POST['customerPhone'] ?? ''),
                'address' => '',
                'city' => '',
                'postalCode' => '',
                'province' => sanitize_text_field($_POST['customerProvince'] ?? '')
            ],
            'titulacionData' => [
                'tipo' => sanitize_text_field($_POST['tipoTitulacion'] ?? ''),
                'servicio' => sanitize_text_field($_POST['tipoServicio'] ?? ''),
                'numeroTitulo' => sanitize_text_field($_POST['numeroTitulo'] ?? ''),
                'fechaExpedicion' => sanitize_text_field($_POST['fechaExpedicion'] ?? ''),
                'lugarExpedicion' => sanitize_text_field($_POST['lugarExpedicion'] ?? '')
            ],
            'pricing' => [
                'amount' => floatval($_POST['amount'] ?? 65.00)
            ]
        ];
        
        $response = wp_remote_post('https://tramitfy.org/api/temporal/capture', [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode($temporal_data),
            'timeout' => 30
        ]);
        
        if (!is_wp_error($response)) {
            wp_send_json_success(['message' => 'Datos almacenados temporalmente']);
        } else {
            throw new Exception('Error al almacenar datos temporales');
        }
        
    } catch (Exception $e) {
        wp_send_json_error(['message' => $e->getMessage()]);
    }
}

// =====================================================
// SHORTCODE DEL FORMULARIO
// =====================================================

function ttv2_form_shortcode() {
    if (defined('ELEMENTOR_VERSION') && 
        class_exists('\Elementor\Plugin') && 
        \Elementor\Plugin::$instance->editor && 
        \Elementor\Plugin::$instance->editor->is_edit_mode()) {
        return '<div style="padding: 20px; background: #f0f0f0; text-align: center;">
                    <h3>Formulario Titulaciones V2</h3>
                    <p>El formulario se mostrará aquí en el frontend.</p>
                </div>';
    }
    
    ob_start();
    ?>
    
    <!-- Cargar SignaturePad -->
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>
    
    <style>
        :root {
            --ttv2-primary: 1, 109, 134;
            --ttv2-primary-dark: 0, 86, 106;
            --ttv2-primary-light: 0, 125, 156;
            --ttv2-success: 40, 167, 69;
            --ttv2-error: 231, 76, 60;
            --ttv2-warning: 243, 156, 18;
        }

        /* Container principal - Grid 2 columnas */
        .ttv2-container {
            max-width: 1400px;
            margin: 25px auto 40px auto;
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
            display: grid;
            grid-template-columns: 380px 1fr;
            min-height: 600px;
        }

        /* Sidebar izquierdo */
        .ttv2-sidebar {
            background: linear-gradient(180deg, rgb(var(--ttv2-primary)) 0%, rgb(var(--ttv2-primary-dark)) 100%);
            color: white;
            padding: 30px 25px;
            display: flex;
            flex-direction: column;
            gap: 25px;
        }

        .ttv2-sidebar h2 {
            color: white !important;
            background: none !important;
            font-size: 22px;
            margin: 0;
        }

        .ttv2-sidebar h3 {
            color: white !important;
            background: none !important;
            font-size: 16px;
            margin: 0 0 10px 0;
        }

        .ttv2-progress {
            margin-top: 20px;
        }

        .ttv2-progress-step {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            opacity: 0.6;
            transition: opacity 0.3s;
        }

        .ttv2-progress-step.active {
            opacity: 1;
        }

        .ttv2-progress-step.completed {
            opacity: 1;
        }

        .ttv2-progress-step .step-number {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            border: 2px solid white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-weight: bold;
            background: transparent;
        }

        .ttv2-progress-step.completed .step-number {
            background: white;
            color: rgb(var(--ttv2-primary));
        }

        .ttv2-progress-step.active .step-number {
            background: rgba(255,255,255,0.2);
        }

        /* Contenido principal */
        .ttv2-main-content {
            padding: 40px;
            position: relative;
            overflow: visible;
        }

        .ttv2-navigation {
            display: flex;
            justify-content: stretch;
            align-items: center;
            margin: -40px -40px 30px -40px;
            padding: 0;
            background: #fafafa;
            border-bottom: 1px solid #e0e0e0;
            overflow: hidden;
        }

        .ttv2-nav-item {
            flex: 1;
            text-align: center;
            padding: 15px 10px;
            cursor: pointer;
            color: #999;
            transition: all 0.2s;
            position: relative;
            background: transparent;
            border: none;
            font-size: 13px;
            font-weight: 400;
        }
        
        .ttv2-nav-item span {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .ttv2-nav-item .step-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: transparent;
            border: 1px solid #ddd;
            color: #999;
            font-size: 11px;
            font-weight: 500;
            transition: all 0.2s;
        }

        .ttv2-nav-item.active {
            color: #333;
            background: transparent;
            font-weight: 500;
            border-bottom: 2px solid rgb(var(--ttv2-primary));
        }
        
        .ttv2-nav-item.active .step-number {
            background: rgb(var(--ttv2-primary));
            border-color: rgb(var(--ttv2-primary));
            color: white;
        }

        .ttv2-nav-item.completed {
            color: #666;
        }
        
        .ttv2-nav-item.completed .step-number {
            background: transparent;
            border-color: rgb(var(--ttv2-success));
            color: rgb(var(--ttv2-success));
        }

        .ttv2-nav-item:not(:last-child)::before {
            display: none;
        }

        /* Pasos del formulario */
        .ttv2-step {
            display: none;
        }

        .ttv2-step.active {
            display: block;
            animation: fadeIn 0.3s;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* PÁGINA 1: Selector de servicios en grid 3-2 */
        .ttv2-services-wrapper {
            max-width: 900px;
            margin: 30px auto;
        }
        
        .ttv2-services-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .ttv2-services-row-2 {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            max-width: calc(66.66% - 5px);
            margin: 0 auto;
        }
        
        @media (max-width: 768px) {
            .ttv2-services-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .ttv2-services-row-2 {
                max-width: 100%;
            }
        }
        
        @media (max-width: 480px) {
            .ttv2-services-grid,
            .ttv2-services-row-2 {
                grid-template-columns: 1fr;
            }
        }

        .ttv2-service-box {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 25px 15px;
            cursor: pointer;
            transition: all 0.2s;
            background: white;
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            min-height: 140px;
        }

        .ttv2-service-box:hover {
            border-color: rgb(var(--ttv2-primary));
            background: rgba(var(--ttv2-primary), 0.05);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .ttv2-service-box.selected {
            border-color: rgb(var(--ttv2-primary));
            background: rgba(var(--ttv2-primary), 0.08);
            border-width: 2px;
            box-shadow: 0 0 0 2px rgba(var(--ttv2-primary), 0.2);
        }

        /* Check/tick removido por solicitud */
        .ttv2-service-box.selected::after {
            display: none;
        }

        .ttv2-service-icon {
            display: none; /* Ocultar iconos por solicitud */
        }

        .ttv2-service-title {
            font-size: 20px;
            font-weight: 700;
            color: #333;
            margin: 0 0 10px 0;
            line-height: 1.2;
        }

        .ttv2-service-description {
            font-size: 15px;
            color: #666;
            margin: 0;
            line-height: 1.4;
        }

        .ttv2-service-price {
            display: none; /* Ocultar precios por solicitud */
        }

        /* Campos del formulario */
        .ttv2-form-group {
            margin-bottom: 20px;
        }

        .ttv2-form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }

        .ttv2-form-group label .required {
            color: rgb(var(--ttv2-error));
        }

        .ttv2-form-group input,
        .ttv2-form-group select,
        .ttv2-form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }

        .ttv2-form-group input:focus,
        .ttv2-form-group select:focus,
        .ttv2-form-group textarea:focus {
            outline: none;
            border-color: rgb(var(--ttv2-primary));
            box-shadow: 0 0 0 3px rgba(var(--ttv2-primary), 0.1);
        }

        .ttv2-form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        /* Selector de titulación */
        .ttv2-titulacion-selector {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }

        .ttv2-titulacion-option {
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            cursor: pointer;
            text-align: center;
            transition: all 0.3s;
            background: white;
        }

        .ttv2-titulacion-option:hover {
            border-color: rgb(var(--ttv2-primary));
            background: rgba(var(--ttv2-primary), 0.02);
        }

        .ttv2-titulacion-option.selected {
            border-color: rgb(var(--ttv2-primary));
            background: rgba(var(--ttv2-primary), 0.08);
            font-weight: 600;
        }

        .ttv2-titulacion-code {
            font-size: 20px;
            font-weight: bold;
            color: rgb(var(--ttv2-primary));
            margin-bottom: 5px;
        }

        .ttv2-titulacion-name {
            font-size: 12px;
            color: #666;
        }

        /* Botones */
        .ttv2-button {
            background: rgb(var(--ttv2-primary));
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-block;
        }

        .ttv2-button:hover {
            background: rgb(var(--ttv2-primary-dark));
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .ttv2-button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        .ttv2-button-secondary {
            background: #f0f0f0;
            color: #333;
        }

        .ttv2-button-secondary:hover {
            background: #e0e0e0;
        }

        .ttv2-buttons-group {
            display: flex;
            gap: 10px;
            margin-top: 30px;
            justify-content: space-between;
        }

        /* Upload de archivos */
        .ttv2-documents-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-top: 30px;
        }

        @media (max-width: 768px) {
            .ttv2-documents-grid {
                grid-template-columns: 1fr;
            }
        }

        .ttv2-document-box {
            background: white;
            border: 1px solid #e5e5e5;
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s;
        }

        .ttv2-document-box:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            border-color: rgba(var(--ttv2-primary), 0.3);
        }

        .ttv2-document-header {
            padding: 10px 15px;
            background: linear-gradient(135deg, rgba(var(--ttv2-primary), 0.03) 0%, rgba(var(--ttv2-primary), 0.01) 100%);
            border-bottom: 1px solid #e5e5e5;
        }

        .ttv2-document-title {
            font-size: 14px;
            font-weight: 600;
            color: #333;
            margin: 0;
            line-height: 1.4;
        }

        .ttv2-upload-area {
            border: 1px solid #e5e5e5;
            border-radius: 0;
            padding: 12px 15px;
            cursor: pointer;
            transition: all 0.3s;
            background: #fafafa;
            position: relative;
            min-height: 40px;
            display: flex;
            flex-direction: row;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
        }

        .ttv2-upload-area:hover {
            background: #f5f5f5;
            border-color: rgba(var(--ttv2-primary), 0.5);
        }

        .ttv2-upload-area.dragover {
            border-color: rgb(var(--ttv2-primary));
            background: rgba(var(--ttv2-primary), 0.05);
        }

        .ttv2-upload-content {
            display: flex;
            align-items: center;
            gap: 8px;
            flex: 1;
        }

        .ttv2-signature-area {
            padding: 12px 15px;
            background: #fafafa;
            min-height: 40px;
            display: flex;
            flex-direction: row;
            justify-content: space-between;
            align-items: center;
            position: relative;
        }


        .ttv2-upload-text {
            font-size: 13px;
            color: #666;
            margin: 2px 0;
            line-height: 1.3;
        }

        .ttv2-upload-formats {
            font-size: 11px;
            color: #999;
            margin-top: 5px;
        }

        .ttv2-file-preview {
            margin-top: 3px;
            padding: 0 !important;
        }

        .ttv2-file-item {
            padding: 8px 12px;
            background: white;
            border: 1px solid #e5e5e5;
            border-radius: 4px;
            margin: 4px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.2s;
            font-size: 12px;
        }

        .ttv2-file-item:hover {
            border-color: rgb(var(--ttv2-primary));
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .ttv2-file-info {
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 1;
        }

        .ttv2-file-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, rgba(var(--ttv2-primary), 0.1) 0%, rgba(var(--ttv2-primary), 0.05) 100%);
            position: relative;
        }

        .ttv2-file-icon::before {
            content: '';
            width: 16px;
            height: 20px;
            border: 2px solid rgb(var(--ttv2-primary));
            border-radius: 2px;
            position: relative;
        }

        .ttv2-file-icon::after {
            content: '';
            position: absolute;
            width: 6px;
            height: 6px;
            border-top: 2px solid rgb(var(--ttv2-primary));
            border-right: 2px solid rgb(var(--ttv2-primary));
            top: 10px;
            right: 11px;
        }

        .ttv2-file-remove {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: #fee;
            color: #d33;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 18px;
            line-height: 1;
            transition: all 0.2s;
        }

        .ttv2-file-remove:hover {
            background: #fdd;
            transform: scale(1.1);
        }

        /* Resumen final */
        .ttv2-summary {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin: 20px 0;
        }

        .ttv2-summary-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e0e0e0;
        }

        .ttv2-summary-row:last-child {
            border-bottom: none;
            font-weight: bold;
            font-size: 18px;
            color: rgb(var(--ttv2-primary));
            padding-top: 15px;
        }

        /* Modal de pago */
        .ttv2-payment-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            z-index: 99999;
            justify-content: center;
            align-items: center;
        }

        .ttv2-payment-modal.active {
            display: flex;
        }

        .ttv2-payment-content {
            background: white;
            padding: 40px;
            border-radius: 16px;
            max-width: 500px;
            width: 90%;
            text-align: center;
        }

        /* Botón Ver ejemplo */
        .ttv2-example-link {
            display: inline-block;
            color: rgb(var(--ttv2-primary));
            text-decoration: none;
            font-size: 11px;
            font-weight: 500;
            transition: all 0.2s;
            padding: 6px 10px;
            border-radius: 4px;
            white-space: nowrap;
        }

        .ttv2-example-link:hover {
            background: rgba(var(--ttv2-primary), 0.08);
            text-decoration: none;
        }

        /* Modal para ejemplos de documentos */
        .ttv2-document-popup {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 99999;
            align-items: center;
            justify-content: center;
        }

        .ttv2-document-popup .ttv2-popup-content {
            background-color: #fff;
            padding: 30px;
            border-radius: 12px;
            max-width: 600px;
            width: 90%;
            position: relative;
            max-height: 90vh;
            overflow-y: auto;
        }

        .ttv2-document-popup .ttv2-close-popup {
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 32px;
            font-weight: 300;
            color: #999;
            cursor: pointer;
            line-height: 1;
            transition: color 0.2s;
        }

        .ttv2-document-popup .ttv2-close-popup:hover {
            color: #333;
        }

        .ttv2-document-popup h3 {
            margin: 0 0 20px 0;
            color: #333;
            font-size: 20px;
        }

        .ttv2-document-popup img {
            width: 100%;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .ttv2-document-popup .ttv2-popup-description {
            margin-top: 15px;
            color: #666;
            font-size: 14px;
            line-height: 1.6;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .ttv2-container {
                grid-template-columns: 1fr;
            }

            .ttv2-sidebar {
                padding: 20px;
            }
            
            .ttv2-navigation {
                margin: -20px -20px 20px -20px;
            }
            
            .ttv2-nav-item {
                font-size: 11px;
                padding: 12px 5px;
            }
            
            .ttv2-nav-item .step-number {
                width: 18px;
                height: 18px;
                font-size: 10px;
            }
            
            .ttv2-main-content {
                padding: 20px;
            }

            .ttv2-progress {
                display: flex;
                justify-content: space-around;
                margin-top: 10px;
            }

            .ttv2-progress-step {
                flex-direction: column;
                margin-bottom: 0;
            }

            .ttv2-progress-step .step-text {
                display: none;
            }

            .ttv2-main-content {
                padding: 20px;
            }

            .ttv2-services-grid {
                grid-template-columns: 1fr;
            }

            .ttv2-form-row {
                grid-template-columns: 1fr;
            }

            .ttv2-titulacion-selector {
                grid-template-columns: 1fr 1fr;
            }
        }

        /* Loading spinner */
        .ttv2-spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid rgb(var(--ttv2-primary));
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Alertas */
        .ttv2-alert {
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .ttv2-alert-info {
            background: rgba(var(--ttv2-primary), 0.1);
            border-left: 4px solid rgb(var(--ttv2-primary));
            color: rgb(var(--ttv2-primary-dark));
        }

        .ttv2-alert-warning {
            background: rgba(var(--ttv2-warning), 0.1);
            border-left: 4px solid rgb(var(--ttv2-warning));
            color: #856404;
        }
    </style>

    <div class="ttv2-container">
        <!-- Sidebar Izquierdo -->
        <div class="ttv2-sidebar">
            <div class="ttv2-logo">
                <h2>⚓ TRAMITFY</h2>
                <p style="opacity: 0.9; font-size: 14px;">Gestión de Titulaciones Náuticas</p>
            </div>

            <div class="ttv2-progress">
                <div class="ttv2-progress-step active" data-step="1">
                    <div class="step-number">1</div>
                    <div class="step-text">Tipo de Servicio</div>
                </div>
                <div class="ttv2-progress-step" data-step="2">
                    <div class="step-number">2</div>
                    <div class="step-text">Datos Personales</div>
                </div>
                <div class="ttv2-progress-step" data-step="3">
                    <div class="step-number">3</div>
                    <div class="step-text">Documentación</div>
                </div>
                <div class="ttv2-progress-step" data-step="4">
                    <div class="step-number">4</div>
                    <div class="step-text">Pago</div>
                </div>
            </div>

            <div class="ttv2-price-summary" style="margin-top: auto; padding: 20px; background: rgba(255,255,255,0.1); border-radius: 12px;">
                <h3>Resumen del trámite</h3>
                <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                    <span>Servicio:</span>
                    <span id="ttv2-sidebar-service">-</span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 14px; opacity: 0.9;">
                    <span>Titulación:</span>
                    <span id="ttv2-sidebar-titulacion">-</span>
                </div>
                <!-- Precio oculto por solicitud -->
                <div style="display: none;">
                    <span>Total:</span>
                    <span id="ttv2-sidebar-total">0.00€</span>
                </div>
            </div>

            <div class="ttv2-contact" style="padding: 15px; background: rgba(255,255,255,0.1); border-radius: 12px;">
                <p style="margin: 0; font-size: 13px; opacity: 0.9;">¿Necesitas ayuda?</p>
                <p style="margin: 5px 0 0 0; font-weight: bold;">📞 900 123 456</p>
                <p style="margin: 5px 0 0 0; font-size: 13px;">info@tramitfy.es</p>
            </div>
        </div>

        <!-- Contenido Principal -->
        <div class="ttv2-main-content">
            <!-- Navegación Superior -->
            <div class="ttv2-navigation">
                <div class="ttv2-nav-item active" data-nav="1">
                    <span><span class="step-number">1</span> Servicio</span>
                </div>
                <div class="ttv2-nav-item" data-nav="2">
                    <span><span class="step-number">2</span> Datos</span>
                </div>
                <div class="ttv2-nav-item" data-nav="3">
                    <span><span class="step-number">3</span> Documentos</span>
                </div>
                <div class="ttv2-nav-item" data-nav="4">
                    <span><span class="step-number">4</span> Pago</span>
                </div>
            </div>

            <!-- Formulario -->
            <form id="ttv2-form">
                <!-- PASO 1: Selector de Tipo de Servicio -->
                <div class="ttv2-step active" data-step="1">
                    <h2>Elige tu titulación a renovar</h2>
                    <p>Selecciona el tipo de titulación náutica que deseas renovar.</p>

                    <div class="ttv2-services-wrapper">
                        <div class="ttv2-services-grid">
                            <div class="ttv2-service-box" data-service="PNB" data-price="55">
                                <div class="ttv2-service-title">P.N.B.</div>
                                <div class="ttv2-service-description">Patrón de Navegación Básica</div>
                            </div>

                            <div class="ttv2-service-box" data-service="PER" data-price="55">
                                <div class="ttv2-service-title">P.E.R</div>
                                <div class="ttv2-service-description">Patrón de Embarcaciones de Recreo</div>
                            </div>

                            <div class="ttv2-service-box" data-service="patron_de_yate" data-price="55">
                                <div class="ttv2-service-title">PATRÓN DE YATE</div>
                                <div class="ttv2-service-description">Titulación para yates hasta 24 metros</div>
                            </div>
                        </div>
                        
                        <div class="ttv2-services-row-2">
                            <div class="ttv2-service-box" data-service="capitan_de_yate" data-price="55">
                                <div class="ttv2-service-title">CAPITÁN DE YATE</div>
                                <div class="ttv2-service-description">Máxima titulación náutica recreativa</div>
                            </div>

                            <div class="ttv2-service-box" data-service="moto_a_o_b" data-price="55">
                                <div class="ttv2-service-title">MOTO A O B</div>
                                <div class="ttv2-service-description">Licencia de navegación para motos de agua</div>
                            </div>
                        </div>
                    </div>

                    <div class="ttv2-alert ttv2-alert-info" style="display: none; margin-top: 25px;" id="ttv2-service-info">
                        <span id="ttv2-service-info-text"></span>
                    </div>

                    <div class="ttv2-buttons-group">
                        <div></div>
                        <button type="button" class="ttv2-button" onclick="ttv2NextStep()" disabled id="ttv2-continue-1">
                            Continuar →
                        </button>
                    </div>
                </div>

                <!-- PASO 2: Datos Personales -->
                <div class="ttv2-step" data-step="2">
                    <h2>Datos Personales</h2>
                    <p>Complete sus datos personales para continuar con el trámite de renovación.</p>

                    <div class="ttv2-alert ttv2-alert-info" style="margin-bottom: 30px;">
                        <span>Titulación seleccionada: <strong id="ttv2-selected-title-name"></strong></span>
                    </div>

                    <div class="ttv2-form-row">
                        <div class="ttv2-form-group">
                            <label for="ttv2-customer-name">Nombre y Apellidos <span class="required">*</span></label>
                            <input type="text" id="ttv2-customer-name" name="customerName" required placeholder="Ej: Juan García López">
                        </div>

                        <div class="ttv2-form-group">
                            <label for="ttv2-customer-dni">DNI/NIE <span class="required">*</span></label>
                            <input type="text" id="ttv2-customer-dni" name="customerDni" required placeholder="Ej: 12345678A">
                        </div>
                    </div>

                    <div class="ttv2-form-row">
                        <div class="ttv2-form-group">
                            <label for="ttv2-customer-email">Correo Electrónico <span class="required">*</span></label>
                            <input type="email" id="ttv2-customer-email" name="customerEmail" required placeholder="Ej: email@ejemplo.com">
                        </div>

                        <div class="ttv2-form-group">
                            <label for="ttv2-customer-phone">Teléfono <span class="required">*</span></label>
                            <input type="tel" id="ttv2-customer-phone" name="customerPhone" required placeholder="Ej: 600123456">
                        </div>
                    </div>

                    <div class="ttv2-buttons-group">
                        <button type="button" class="ttv2-button ttv2-button-secondary" onclick="ttv2PrevStep()">
                            ← Anterior
                        </button>
                        <button type="button" class="ttv2-button" onclick="ttv2NextStep()">
                            Continuar →
                        </button>
                    </div>
                </div>

                <!-- PASO 3: Documentación -->
                <div class="ttv2-step" data-step="3">
                    <h2>Documentación Requerida</h2>
                    <p>Por favor, suba la documentación necesaria para completar su trámite de titulación náutica.</p>

                    <div class="ttv2-documents-grid">
                        <!-- DNI -->
                        <div class="ttv2-document-box">
                            <div class="ttv2-document-header">
                                <h3 class="ttv2-document-title">Copia del DNI por ambas caras <span class="required">*</span></h3>
                            </div>
                            <div class="ttv2-upload-area" onclick="document.getElementById('ttv2-dni').click()">
                                <div class="ttv2-upload-content">
                                    <span style="font-size: 13px; color: #666;">Seleccionar archivo</span>
                                    <span style="font-size: 11px; color: #999;">• JPG, PNG, PDF</span>
                                </div>
                                <a href="#" class="ttv2-example-link" data-doc="dni-comprador" onclick="event.stopPropagation();" style="margin: 0;">Ver ejemplo</a>
                                <input type="file" id="ttv2-dni" name="dniFile" multiple accept="image/*,application/pdf" style="display:none;" required>
                            </div>
                            <div id="ttv2-dni-preview" class="ttv2-file-preview"></div>
                        </div>

                        <!-- Certificado médico -->
                        <div class="ttv2-document-box">
                            <div class="ttv2-document-header">
                                <h3 class="ttv2-document-title">Certificado médico psicotécnico por ambas caras <span class="required">*</span></h3>
                            </div>
                            <div class="ttv2-upload-area" onclick="document.getElementById('ttv2-certificado').click()">
                                <div class="ttv2-upload-content">
                                    <span style="font-size: 13px; color: #666;">Seleccionar archivo</span>
                                    <span style="font-size: 11px; color: #999;">• JPG, PNG, PDF</span>
                                </div>
                                <a href="#" class="ttv2-example-link" data-doc="certificado-medico-plantilla" onclick="event.stopPropagation();" style="margin: 0;">Ver ejemplo</a>
                                <input type="file" id="ttv2-certificado" name="certificadoFile" multiple accept="image/*,application/pdf" style="display:none;" required>
                            </div>
                            <div id="ttv2-certificado-preview" class="ttv2-file-preview"></div>
                        </div>

                        <!-- Documentación caducada -->
                        <div class="ttv2-document-box">
                            <div class="ttv2-document-header">
                                <h3 class="ttv2-document-title">Copia documentación caducada <span class="required">*</span></h3>
                            </div>
                            <div class="ttv2-upload-area" onclick="document.getElementById('ttv2-titulacion').click()">
                                <div class="ttv2-upload-content">
                                    <span style="font-size: 13px; color: #666;">Seleccionar archivo</span>
                                    <span style="font-size: 11px; color: #999;">• JPG, PNG, PDF</span>
                                </div>
                                <a href="#" class="ttv2-example-link" data-doc="QUE-TITULO-NECESITO" onclick="event.stopPropagation();" style="margin: 0;">Ver ejemplo</a>
                                <input type="file" id="ttv2-titulacion" name="titulacionFile" multiple accept="image/*,application/pdf" style="display:none;" required>
                            </div>
                            <div id="ttv2-titulacion-preview" class="ttv2-file-preview"></div>
                        </div>

                        <!-- Firma del documento -->
                        <div class="ttv2-document-box">
                            <div class="ttv2-document-header">
                                <h3 class="ttv2-document-title">Firma del documento de autorización <span class="required">*</span></h3>
                            </div>
                            <div class="ttv2-signature-area">
                                <div id="ttv2-authorization-document" style="display: none;"></div>
                                <div class="ttv2-upload-content">
                                    <span style="font-size: 13px; color: #666;">Documento de autorización</span>
                                </div>
                                <button type="button" class="ttv2-button" id="ttv2-open-signature-modal" style="padding: 8px 20px; font-size: 12px; margin: 0;">
                                    Firmar
                                </button>
                                <div id="ttv2-signature-status" style="position: absolute; bottom: -20px; left: 15px; display: none;">
                                    <span style="color: rgb(var(--ttv2-primary)); font-weight: 500; font-size: 11px;">✓ Firmado</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="ttv2-alert ttv2-alert-info" style="margin-top: 30px;">
                        <span>Asegúrese de que los documentos sean legibles y estén completos antes de continuar.</span>
                    </div>

                    <div class="ttv2-buttons-group" style="margin-top: 40px;">
                        <button type="button" class="ttv2-button ttv2-button-secondary" onclick="ttv2PrevStep()">
                            ← Anterior
                        </button>
                        <button type="button" class="ttv2-button" onclick="ttv2NextStep()">
                            Continuar →
                        </button>
                    </div>
                </div>

                <!-- PASO 4: Revisión y Pago -->
                <div class="ttv2-step" data-step="4">
                    <h2 style="margin-bottom: 25px; color: #333;">Confirmación y Pago</h2>
                    
                    <div class="ttv2-payment-container" style="display: grid; grid-template-columns: 1.2fr 380px; gap: 30px;">
                        <!-- Columna izquierda: Resumen -->
                        <div class="ttv2-payment-summary">
                            <div class="ttv2-summary-section" style="background: white; border: 1px solid #e5e5e5; border-radius: 8px; padding: 25px; margin-bottom: 20px;">
                                <h3 style="font-size: 18px; margin: 0 0 20px 0; color: rgb(var(--ttv2-primary));">Datos Personales</h3>
                                <div class="ttv2-summary-grid" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
                                    <div>
                                        <label style="font-size: 12px; color: #999; display: block; margin-bottom: 4px;">Nombre completo</label>
                                        <p style="margin: 0; font-size: 14px; color: #333;" id="ttv2-summary-name">-</p>
                                    </div>
                                    <div>
                                        <label style="font-size: 12px; color: #999; display: block; margin-bottom: 4px;">DNI/NIE</label>
                                        <p style="margin: 0; font-size: 14px; color: #333;" id="ttv2-summary-dni">-</p>
                                    </div>
                                    <div>
                                        <label style="font-size: 12px; color: #999; display: block; margin-bottom: 4px;">Email</label>
                                        <p style="margin: 0; font-size: 14px; color: #333;" id="ttv2-summary-email">-</p>
                                    </div>
                                    <div>
                                        <label style="font-size: 12px; color: #999; display: block; margin-bottom: 4px;">Teléfono</label>
                                        <p style="margin: 0; font-size: 14px; color: #333;" id="ttv2-summary-phone">-</p>
                                    </div>
                                </div>
                            </div>

                            <div class="ttv2-summary-section" style="background: white; border: 1px solid #e5e5e5; border-radius: 8px; padding: 25px; margin-bottom: 20px;">
                                <h3 style="font-size: 18px; margin: 0 0 20px 0; color: rgb(var(--ttv2-primary));">Detalles del Trámite</h3>
                                <div style="space-y: 12px;">
                                    <div style="padding: 12px 0; border-bottom: 1px solid #f0f0f0;">
                                        <label style="font-size: 12px; color: #999; display: block; margin-bottom: 4px;">Tipo de servicio</label>
                                        <p style="margin: 0; font-size: 15px; color: #333; font-weight: 500;" id="ttv2-summary-service">-</p>
                                    </div>
                                    <div style="padding: 12px 0;">
                                        <label style="font-size: 12px; color: #999; display: block; margin-bottom: 4px;">Titulación</label>
                                        <p style="margin: 0; font-size: 14px; color: #333;" id="ttv2-summary-titulacion">-</p>
                                    </div>
                                </div>
                            </div>

                            <div class="ttv2-summary-section" style="background: white; border: 1px solid #e5e5e5; border-radius: 8px; padding: 25px;">
                                <h3 style="font-size: 18px; margin: 0 0 20px 0; color: rgb(var(--ttv2-primary));">Documentación</h3>
                                <div class="ttv2-docs-status" style="display: flex; align-items: center; gap: 10px; padding: 15px; background: #f0fff4; border: 1px solid #d4edda; border-radius: 6px;">
                                    <span style="color: #28a745; font-size: 20px;">✓</span>
                                    <div>
                                        <p style="margin: 0; font-size: 14px; color: #333; font-weight: 500;">Documentación completa</p>
                                        <p style="margin: 0; font-size: 13px; color: #666;" id="ttv2-summary-docs">0 archivos adjuntos</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Columna derecha: Precio y pago -->
                        <div class="ttv2-payment-box">
                            <div style="background: white; border: 1px solid #e5e5e5; border-radius: 8px; padding: 25px; position: sticky; top: 20px;">
                                <h3 style="font-size: 18px; margin: 0 0 25px 0; color: #333;">Resumen del Pago</h3>
                                
                                <!-- Precio -->
                                <div style="padding: 20px 0; border-top: 1px solid #f0f0f0; border-bottom: 1px solid #f0f0f0;">
                                    <div style="display: flex; justify-content: space-between; align-items: center;">
                                        <span style="font-size: 14px; color: #666;">Gestión del trámite</span>
                                        <span style="font-size: 14px; color: #333;" id="ttv2-price-base">-</span>
                                    </div>
                                </div>

                                <div style="padding: 20px 0; border-bottom: 2px solid #f0f0f0;">
                                    <div style="display: flex; justify-content: space-between; align-items: center;">
                                        <span style="font-size: 16px; font-weight: 600; color: #333;">Total a pagar</span>
                                        <span style="font-size: 20px; font-weight: 600; color: rgb(var(--ttv2-primary));" id="ttv2-summary-total">0.00€</span>
                                    </div>
                                </div>

                                <!-- Términos -->
                                <div style="margin: 20px 0;">
                                    <label style="display: flex; align-items: flex-start; cursor: pointer; font-size: 13px;">
                                        <input type="checkbox" id="ttv2-terms" required style="width: auto; margin-right: 8px; margin-top: 2px;">
                                        <span style="color: #666; line-height: 1.5;">Acepto los <a href="#" style="color: rgb(var(--ttv2-primary));">términos y condiciones</a> y autorizo el tratamiento de mis datos.</span>
                                    </label>
                                </div>

                                <!-- Info de seguridad -->
                                <div style="display: flex; align-items: center; gap: 10px; padding: 12px; background: #f0f8ff; border: 1px solid #d1e7ff; border-radius: 6px; margin-bottom: 20px;">
                                    <span style="font-size: 16px;">🔒</span>
                                    <span style="font-size: 12px; color: #666;">Pago 100% seguro con Redsys</span>
                                </div>

                                <!-- Botón de pago -->
                                <button type="button" class="ttv2-button" onclick="ttv2ProcessPayment()" id="ttv2-pay-button" style="width: 100%; padding: 14px; font-size: 16px; background: linear-gradient(135deg, rgb(var(--ttv2-primary)) 0%, rgb(var(--ttv2-primary-dark)) 100%);">
                                    Proceder al Pago Seguro →
                                </button>

                                <!-- Métodos de pago aceptados -->
                                <div style="text-align: center; margin-top: 15px;">
                                    <p style="font-size: 11px; color: #999; margin-bottom: 8px;">Métodos de pago aceptados</p>
                                    <div style="display: flex; justify-content: center; gap: 15px; opacity: 0.6; filter: grayscale(100%);">
                                        <img src="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iMjQiIHZpZXdCb3g9IjAgMCA0MCAyNCIgZmlsbD0ibm9uZSI+PHJlY3Qgd2lkdGg9IjQwIiBoZWlnaHQ9IjI0IiByeD0iNCIgZmlsbD0iIzAwNTBBMCIvPjwvc3ZnPg==" alt="Visa" style="height: 24px;">
                                        <img src="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iMjQiIHZpZXdCb3g9IjAgMCA0MCAyNCIgZmlsbD0ibm9uZSI+PHJlY3Qgd2lkdGg9IjQwIiBoZWlnaHQ9IjI0IiByeD0iNCIgZmlsbD0iI0VCMDAxQiIvPjwvc3ZnPg==" alt="Mastercard" style="height: 24px;">
                                        <img src="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iMjQiIHZpZXdCb3g9IjAgMCA0MCAyNCIgZmlsbD0ibm9uZSI+PHJlY3Qgd2lkdGg9IjQwIiBoZWlnaHQ9IjI0IiByeD0iNCIgZmlsbD0iIzAwNjZDQyIvPjwvc3ZnPg==" alt="Maestro" style="height: 24px;">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="ttv2-buttons-group" style="margin-top: 30px;">
                        <button type="button" class="ttv2-button ttv2-button-secondary" onclick="ttv2PrevStep()">
                            ← Volver al paso anterior
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal de Pago -->
    <div class="ttv2-payment-modal" id="ttv2-payment-modal">
        <div class="ttv2-payment-content">
            <h3>Procesando su pago...</h3>
            <div class="ttv2-spinner"></div>
            <p>Por favor, espere mientras le redirigimos al TPV seguro.</p>
            <p style="font-size: 14px; opacity: 0.7;">No cierre ni actualice esta página.</p>
        </div>
    </div>

    <!-- Formulario oculto para Redsys -->
    <form id="ttv2-redsys-form" action="" method="POST" style="display:none;">
        <input type="hidden" name="Ds_SignatureVersion" id="ttv2-Ds_SignatureVersion">
        <input type="hidden" name="Ds_MerchantParameters" id="ttv2-Ds_MerchantParameters">
        <input type="hidden" name="Ds_Signature" id="ttv2-Ds_Signature">
    </form>

    <!-- Modal para ejemplos de documentos -->
    <div id="ttv2-document-popup" class="ttv2-document-popup" style="display:none;">
        <div class="ttv2-popup-content">
            <span class="ttv2-close-popup">&times;</span>
            <h3>Ejemplo de documento</h3>
            <img id="ttv2-document-example-image" src="" alt="Ejemplo de documento">
            <div class="ttv2-popup-description" id="ttv2-document-description"></div>
        </div>
    </div>

    <!-- Modal para firma -->
    <div id="ttv2-signature-modal" class="ttv2-document-popup" style="display:none;">
        <div class="ttv2-popup-content" style="max-width: 700px;">
            <span class="ttv2-close-signature" style="position: absolute; top: 15px; right: 20px; font-size: 32px; cursor: pointer; color: #999;">&times;</span>
            <h3 style="margin-bottom: 20px;">Firme el documento de autorización</h3>
            
            <div style="background: #f5f5f5; padding: 15px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; line-height: 1.6;">
                <div id="ttv2-authorization-preview"></div>
            </div>
            
            <div style="text-align: center; margin: 20px 0;">
                <p style="font-size: 14px; color: #666; margin-bottom: 10px;">Firme en el recuadro inferior usando el ratón o el dedo</p>
                <canvas id="ttv2-signature-pad" width="600" height="200" style="border: 2px solid #ddd; border-radius: 6px; background: white; touch-action: none;"></canvas>
            </div>
            
            <div style="display: flex; gap: 10px; justify-content: center; margin-top: 20px;">
                <button type="button" class="ttv2-button ttv2-button-secondary" id="ttv2-clear-signature">
                    Limpiar Firma
                </button>
                <button type="button" class="ttv2-button" id="ttv2-save-signature">
                    Guardar Firma
                </button>
            </div>
        </div>
    </div>

    <script>
        // Variables globales TTV2
        let ttv2CurrentStep = 1;
        let ttv2SelectedService = null;
        let ttv2SelectedPrice = 0;
        let ttv2SelectedTitulacion = null;
        let ttv2FormData = {};
        let ttv2Files = {};

        // Textos descriptivos para servicios
        const ttv2ServiceDescriptions = {
            'PNB': {
                title: 'Patrón de Navegación Básica',
                info: 'Renovación de P.N.B. Necesitarás tu titulación actual y DNI. Proceso rápido en 24-48h.'
            },
            'PER': {
                title: 'Patrón de Embarcaciones de Recreo',
                info: 'Renovación de P.E.R. Gestión completa del trámite. Proceso en 24-48h.'
            },
            'patron_de_yate': {
                title: 'Patrón de Yate',
                info: 'Renovación de Patrón de Yate. Incluye gestión completa. Proceso en 3-5 días.'
            },
            'capitan_de_yate': {
                title: 'Capitán de Yate',
                info: 'Renovación de Capitán de Yate. Máxima titulación recreativa. Proceso en 3-5 días.'
            },
            'moto_a_o_b': {
                title: 'Licencia Motos de Agua',
                info: 'Renovación de licencia para motos de agua. Proceso rápido en 24h.'
            }
        };

        // Inicialización
        document.addEventListener('DOMContentLoaded', function() {
            // Event listeners para service boxes
            document.querySelectorAll('.ttv2-service-box').forEach(box => {
                box.addEventListener('click', function() {
                    document.querySelectorAll('.ttv2-service-box').forEach(b => b.classList.remove('selected'));
                    this.classList.add('selected');
                    ttv2SelectedService = this.dataset.service;
                    ttv2SelectedPrice = parseFloat(this.dataset.price);
                    
                    // Mostrar información adicional
                    const info = ttv2ServiceDescriptions[ttv2SelectedService];
                    if (info) {
                        document.getElementById('ttv2-service-info').style.display = 'flex';
                        document.getElementById('ttv2-service-info-text').textContent = info.info;
                    }
                    
                    // Actualizar titulación seleccionada
                    ttv2UpdateSelectedTitle();
                    
                    // Habilitar botón continuar
                    document.getElementById('ttv2-continue-1').disabled = false;
                    
                    // Actualizar sidebar
                    ttv2UpdateSidebar();
                });
            });

            // Event listeners para opciones de titulación
            document.querySelectorAll('.ttv2-titulacion-option').forEach(option => {
                option.addEventListener('click', function() {
                    document.querySelectorAll('.ttv2-titulacion-option').forEach(o => o.classList.remove('selected'));
                    this.classList.add('selected');
                    ttv2SelectedTitulacion = this.dataset.titulacion;
                    ttv2UpdateSidebar();
                });
            });

            // Event listeners para navegación
            document.querySelectorAll('.ttv2-nav-item').forEach(item => {
                item.addEventListener('click', function() {
                    const targetStep = parseInt(this.dataset.nav);
                    if (targetStep < ttv2CurrentStep || ttv2ValidateCurrentStep()) {
                        ttv2GoToStep(targetStep);
                    }
                });
            });

            // File uploads
            ['ttv2-dni', 'ttv2-titulacion', 'ttv2-certificado'].forEach(inputId => {
                const input = document.getElementById(inputId);
                if (input) {
                    input.addEventListener('change', function(e) {
                        ttv2HandleFileSelect(e, inputId);
                    });
                }
            });

            // Modal para ejemplos de documentos
            const ttv2Popup = document.getElementById('ttv2-document-popup');
            const ttv2ClosePopup = document.querySelector('.ttv2-close-popup');
            const ttv2ExampleImage = document.getElementById('ttv2-document-example-image');
            const ttv2DocDescription = document.getElementById('ttv2-document-description');
            
            // Configurar descripciones para cada tipo de documento
            const ttv2DocDescriptions = {
                'dni-comprador': 'Copia del DNI por ambas caras. Asegúrese de que sea legible y esté actualizado.',
                'certificado-medico-plantilla': 'Certificado médico psicotécnico por ambas caras, emitido por un centro autorizado.',
                'QUE-TITULO-NECESITO': 'Copia de la documentación caducada que desea renovar. Debe estar completa y legible.'
            };
            
            // Event listeners para links de ejemplo
            document.querySelectorAll('.ttv2-example-link').forEach(function(link) {
                link.addEventListener('click', function(event) {
                    event.preventDefault();
                    const docType = this.getAttribute('data-doc');
                    const baseURL = '<?php echo esc_url(home_url('/')); ?>';
                    
                    ttv2ExampleImage.src = baseURL + 'wp-content/uploads/exampledocs/' + docType + '.jpg';
                    ttv2DocDescription.textContent = ttv2DocDescriptions[docType] || '';
                    ttv2Popup.style.display = 'flex';
                });
            });
            
            // Cerrar modal
            if (ttv2ClosePopup) {
                ttv2ClosePopup.addEventListener('click', function() {
                    ttv2Popup.style.display = 'none';
                });
            }
            
            // Cerrar al hacer click fuera del modal
            window.addEventListener('click', function(event) {
                if (event.target === ttv2Popup) {
                    ttv2Popup.style.display = 'none';
                }
                if (event.target === ttv2SignatureModal) {
                    ttv2SignatureModal.style.display = 'none';
                }
            });

            // Sistema de firma
            let ttv2SignaturePad = null;
            let ttv2SignatureData = null;
            const ttv2SignatureModal = document.getElementById('ttv2-signature-modal');
            const ttv2OpenSignatureBtn = document.getElementById('ttv2-open-signature-modal');
            const ttv2ClearSignatureBtn = document.getElementById('ttv2-clear-signature');
            const ttv2SaveSignatureBtn = document.getElementById('ttv2-save-signature');
            const ttv2CloseSignatureBtn = document.querySelector('.ttv2-close-signature');
            
            // Función para generar documento de autorización
            function ttv2GenerateAuthorizationDocument() {
                const authDiv = document.getElementById('ttv2-authorization-document');
                const authPreview = document.getElementById('ttv2-authorization-preview');
                const customerName = document.getElementById('ttv2-customer-name').value || '[Nombre del titular]';
                const customerDNI = document.getElementById('ttv2-customer-dni').value || '[DNI]';
                const selectedService = document.querySelector('.ttv2-service-box.selected');
                const serviceText = selectedService ? 
                    selectedService.querySelector('.ttv2-service-title').textContent + ' - ' + 
                    selectedService.querySelector('.ttv2-service-description').textContent : 
                    '[Servicio seleccionado]';
                
                const authContent = `
                    <p>Yo, <strong>${customerName}</strong>, con DNI <strong>${customerDNI}</strong>, 
                    autorizo a Tramitfy S.L. (CIF B55388557) a realizar en mi nombre los trámites necesarios 
                    para la gestión de mi titulación náutica correspondiente al servicio: 
                    <strong>${serviceText}</strong>.</p>
                    <p>Declaro que todos los datos proporcionados son veraces y que los documentos adjuntos 
                    son copias fieles de los originales.</p>
                    <p>Firmo a continuación en señal de conformidad.</p>
                    <p style="font-size: 12px; color: #666; margin-top: 20px;">
                    Fecha: ${new Date().toLocaleDateString('es-ES', { day: 'numeric', month: 'long', year: 'numeric' })}
                    </p>
                `;
                
                if (authDiv) authDiv.innerHTML = authContent;
                if (authPreview) authPreview.innerHTML = authContent;
            }
            
            // Inicializar SignaturePad cuando se abre el modal
            if (ttv2OpenSignatureBtn) {
                ttv2OpenSignatureBtn.addEventListener('click', function() {
                    ttv2GenerateAuthorizationDocument();
                    ttv2SignatureModal.style.display = 'flex';
                    
                    if (!ttv2SignaturePad) {
                        const canvas = document.getElementById('ttv2-signature-pad');
                        ttv2SignaturePad = new SignaturePad(canvas, {
                            backgroundColor: 'rgb(255, 255, 255)',
                            penColor: 'rgb(0, 0, 0)'
                        });
                        
                        // Ajustar canvas para alta resolución
                        function resizeCanvas() {
                            const ratio = Math.max(window.devicePixelRatio || 1, 1);
                            canvas.width = canvas.offsetWidth * ratio;
                            canvas.height = canvas.offsetHeight * ratio;
                            canvas.getContext("2d").scale(ratio, ratio);
                            ttv2SignaturePad.clear();
                        }
                        
                        window.addEventListener("resize", resizeCanvas);
                        resizeCanvas();
                    }
                    
                    // Restaurar firma si ya existe
                    if (ttv2SignatureData) {
                        ttv2SignaturePad.fromDataURL(ttv2SignatureData);
                    }
                });
            }
            
            // Limpiar firma
            if (ttv2ClearSignatureBtn) {
                ttv2ClearSignatureBtn.addEventListener('click', function() {
                    if (ttv2SignaturePad) {
                        ttv2SignaturePad.clear();
                    }
                });
            }
            
            // Guardar firma
            if (ttv2SaveSignatureBtn) {
                ttv2SaveSignatureBtn.addEventListener('click', function() {
                    if (ttv2SignaturePad && !ttv2SignaturePad.isEmpty()) {
                        ttv2SignatureData = ttv2SignaturePad.toDataURL();
                        
                        // Mostrar estado de firma completada
                        document.getElementById('ttv2-signature-status').style.display = 'block';
                        
                        // Cerrar modal
                        ttv2SignatureModal.style.display = 'none';
                        
                        // Guardar en el array de archivos para enviar con el formulario
                        ttv2Files['signature'] = ttv2SignatureData;
                    } else {
                        alert('Por favor, firme el documento antes de guardar.');
                    }
                });
            }
            
            // Cerrar modal de firma
            if (ttv2CloseSignatureBtn) {
                ttv2CloseSignatureBtn.addEventListener('click', function() {
                    ttv2SignatureModal.style.display = 'none';
                });
            }
            
            // Actualizar documento cuando cambian los datos
            document.getElementById('ttv2-customer-name')?.addEventListener('input', ttv2GenerateAuthorizationDocument);
            document.getElementById('ttv2-customer-dni')?.addEventListener('input', ttv2GenerateAuthorizationDocument);

            // Drag and drop
            document.querySelectorAll('.ttv2-upload-area').forEach(area => {
                area.addEventListener('dragover', function(e) {
                    e.preventDefault();
                    this.classList.add('dragover');
                });
                
                area.addEventListener('dragleave', function(e) {
                    e.preventDefault();
                    this.classList.remove('dragover');
                });
                
                area.addEventListener('drop', function(e) {
                    e.preventDefault();
                    this.classList.remove('dragover');
                    const inputId = this.onclick.toString().match(/getElementById\('([^']+)'\)/)[1];
                    const input = document.getElementById(inputId);
                    if (input && e.dataTransfer.files.length > 0) {
                        input.files = e.dataTransfer.files;
                        ttv2HandleFileSelect({ target: input }, inputId);
                    }
                });
            });

            // Auto-fill para administradores
            <?php if (current_user_can('administrator')): ?>
            ttv2AutoFillAdmin();
            <?php endif; ?>
        });

        // Navegación entre pasos
        function ttv2NextStep() {
            if (ttv2ValidateCurrentStep()) {
                ttv2GoToStep(ttv2CurrentStep + 1);
            }
        }

        function ttv2PrevStep() {
            ttv2GoToStep(ttv2CurrentStep - 1);
        }

        function ttv2GoToStep(step) {
            if (step < 1 || step > 4) return;

            // Ocultar paso actual
            document.querySelector(`.ttv2-step[data-step="${ttv2CurrentStep}"]`).classList.remove('active');
            
            // Actualizar navegación
            document.querySelector(`.ttv2-nav-item[data-nav="${ttv2CurrentStep}"]`).classList.remove('active');
            document.querySelector(`.ttv2-progress-step[data-step="${ttv2CurrentStep}"]`).classList.remove('active');
            
            // Si el paso actual se completó
            if (step > ttv2CurrentStep) {
                document.querySelector(`.ttv2-nav-item[data-nav="${ttv2CurrentStep}"]`).classList.add('completed');
                document.querySelector(`.ttv2-progress-step[data-step="${ttv2CurrentStep}"]`).classList.add('completed');
            }

            // Mostrar nuevo paso
            ttv2CurrentStep = step;
            document.querySelector(`.ttv2-step[data-step="${step}"]`).classList.add('active');
            document.querySelector(`.ttv2-nav-item[data-nav="${step}"]`).classList.add('active');
            document.querySelector(`.ttv2-progress-step[data-step="${step}"]`).classList.add('active');

            // Si es el paso 2, actualizar la titulación seleccionada
            if (step === 2) {
                ttv2UpdateSelectedTitle();
            }
            
            // Si es el paso 3, generar documento de autorización
            if (step === 3) {
                setTimeout(ttv2GenerateAuthorizationDocument, 100);
            }
            
            // Si es el último paso, actualizar resumen
            if (step === 4) {
                ttv2UpdateSummary();
            }

            // Scroll al inicio
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
        
        // Actualizar información de titulación seleccionada en paso 2
        function ttv2UpdateSelectedTitle() {
            const titleMap = {
                'PNB': 'P.N.B. - Patrón de Navegación Básica',
                'PER': 'P.E.R - Patrón de Embarcaciones de Recreo',
                'patron_de_yate': 'Patrón de Yate',
                'capitan_de_yate': 'Capitán de Yate',
                'moto_a_o_b': 'Moto A o B - Licencia de motos de agua'
            };
            
            const titleElement = document.getElementById('ttv2-selected-title-name');
            if (titleElement && ttv2SelectedService && titleMap[ttv2SelectedService]) {
                titleElement.textContent = titleMap[ttv2SelectedService];
                ttv2SelectedTitulacion = ttv2SelectedService;
            }
        }

        // Validación de pasos
        function ttv2ValidateCurrentStep() {
            const step = ttv2CurrentStep;

            if (step === 1) {
                // Validar servicio seleccionado
                if (!ttv2SelectedService) {
                    alert('Por favor, seleccione un tipo de servicio.');
                    return false;
                }
                ttv2FormData.tipoServicio = ttv2SelectedService;
                ttv2FormData.amount = ttv2SelectedPrice;
            }

            if (step === 2) {
                // Validar datos personales
                const name = document.getElementById('ttv2-customer-name').value.trim();
                const dni = document.getElementById('ttv2-customer-dni').value.trim();
                const email = document.getElementById('ttv2-customer-email').value.trim();
                const phone = document.getElementById('ttv2-customer-phone').value.trim();

                if (!name || !dni || !email || !phone) {
                    alert('Por favor, complete todos los campos obligatorios.');
                    return false;
                }

                // Validar formato DNI/NIE
                const dniRegex = /^[0-9]{8}[A-Z]$|^[XYZ][0-9]{7}[A-Z]$/;
                if (!dniRegex.test(dni.toUpperCase())) {
                    alert('Por favor, introduzca un DNI/NIE válido.');
                    return false;
                }

                // Validar formato email
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(email)) {
                    alert('Por favor, introduzca un email válido.');
                    return false;
                }

                // Validar teléfono
                const phoneRegex = /^[6789]\d{8}$/;
                if (!phoneRegex.test(phone.replace(/\s/g, ''))) {
                    alert('Por favor, introduzca un teléfono válido.');
                    return false;
                }

                // Validar titulación seleccionada
                if (!ttv2SelectedTitulacion) {
                    alert('Por favor, seleccione su tipo de titulación.');
                    return false;
                }

                // Guardar datos
                ttv2FormData.customerName = name;
                ttv2FormData.customerDni = dni.toUpperCase();
                ttv2FormData.customerEmail = email;
                ttv2FormData.customerPhone = phone;
                ttv2FormData.tipoTitulacion = ttv2SelectedService; // Usar el servicio seleccionado como titulación
            }

            if (step === 3) {
                // Validar documentación mínima
                if (!ttv2Files['ttv2-dni'] || ttv2Files['ttv2-dni'].length === 0) {
                    alert('Por favor, suba su DNI por ambas caras.');
                    return false;
                }
                if (!ttv2Files['ttv2-certificado'] || ttv2Files['ttv2-certificado'].length === 0) {
                    alert('Por favor, suba el certificado médico psicotécnico.');
                    return false;
                }
                if (!ttv2Files['ttv2-titulacion'] || ttv2Files['ttv2-titulacion'].length === 0) {
                    alert('Por favor, suba la copia de documentación caducada.');
                    return false;
                }
                
                // Validar firma
                if (!ttv2Files['signature'] || ttv2Files['signature'] === null) {
                    alert('Por favor, firme el documento de autorización.');
                    return false;
                }
            }

            return true;
        }

        // Actualizar sidebar
        function ttv2UpdateSidebar() {
            if (ttv2SelectedService) {
                const serviceText = ttv2ServiceDescriptions[ttv2SelectedService]?.title || ttv2SelectedService;
                document.getElementById('ttv2-sidebar-service').textContent = serviceText;
                document.getElementById('ttv2-sidebar-total').textContent = ttv2SelectedPrice.toFixed(2) + '€';
            }
            
            if (ttv2SelectedTitulacion) {
                document.getElementById('ttv2-sidebar-titulacion').textContent = ttv2SelectedTitulacion;
            }
        }

        // Actualizar resumen final
        function ttv2UpdateSummary() {
            document.getElementById('ttv2-summary-name').textContent = ttv2FormData.customerName || '-';
            document.getElementById('ttv2-summary-dni').textContent = ttv2FormData.customerDni || '-';
            document.getElementById('ttv2-summary-email').textContent = ttv2FormData.customerEmail || '-';
            document.getElementById('ttv2-summary-phone').textContent = ttv2FormData.customerPhone || '-';
            
            const serviceText = ttv2ServiceDescriptions[ttv2SelectedService]?.title || ttv2SelectedService;
            document.getElementById('ttv2-summary-service').textContent = serviceText;
            document.getElementById('ttv2-summary-titulacion').textContent = ttv2SelectedTitulacion || '-';
            
            // Contar archivos
            let totalFiles = 0;
            Object.keys(ttv2Files).forEach(key => {
                if (ttv2Files[key] && ttv2Files[key].length > 0) {
                    totalFiles += ttv2Files[key].length;
                }
            });
            document.getElementById('ttv2-summary-docs').textContent = totalFiles + ' archivo(s) adjuntos';
            
            // Actualizar precios
            document.getElementById('ttv2-summary-total').textContent = ttv2SelectedPrice.toFixed(2) + '€';
            const priceBase = document.getElementById('ttv2-price-base');
            if (priceBase) {
                priceBase.textContent = ttv2SelectedPrice.toFixed(2) + '€';
            }
        }

        // Manejo de archivos
        function ttv2RemoveFile(inputId, index) {
            ttv2Files[inputId].splice(index, 1);
            ttv2HandleFileSelect({ target: { files: ttv2Files[inputId] } }, inputId);
        }
        
        function ttv2HandleFileSelect(event, inputId) {
            const files = event.target.files;
            ttv2Files[inputId] = Array.from(files);
            
            // Mostrar preview
            const previewId = inputId + '-preview';
            const previewContainer = document.getElementById(previewId);
            if (previewContainer) {
                previewContainer.innerHTML = '';
                ttv2Files[inputId].forEach((file, index) => {
                    const fileDiv = document.createElement('div');
                    fileDiv.className = 'ttv2-file-item';
                    
                    const fileExt = file.name.split('.').pop().toUpperCase();
                    const isImage = ['JPG', 'JPEG', 'PNG'].includes(fileExt);
                    const isPDF = fileExt === 'PDF';
                    
                    fileDiv.innerHTML = `
                        <div class="ttv2-file-info">
                            <div class="ttv2-file-icon"></div>
                            <div>
                                <div style="font-size: 14px; color: #333; font-weight: 500;">${file.name}</div>
                                <div style="font-size: 12px; color: #999;">${(file.size / 1024).toFixed(1)} KB</div>
                            </div>
                        </div>
                        <span class="ttv2-file-remove" onclick="ttv2RemoveFile('${inputId}', ${index})">×</span>
                    `;
                    previewContainer.appendChild(fileDiv);
                });
            }
        }

        // Procesar pago
        async function ttv2ProcessPayment() {
            // Validar términos
            if (!document.getElementById('ttv2-terms').checked) {
                alert('Por favor, acepte los términos y condiciones.');
                return;
            }

            // Mostrar modal
            document.getElementById('ttv2-payment-modal').classList.add('active');
            document.getElementById('ttv2-pay-button').disabled = true;

            try {
                // 1. Almacenar datos temporalmente
                const temporalData = new FormData();
                temporalData.append('action', 'ttv2_store_temporal');
                temporalData.append('orderId', '');
                temporalData.append('customerName', ttv2FormData.customerName);
                temporalData.append('customerDni', ttv2FormData.customerDni);
                temporalData.append('customerEmail', ttv2FormData.customerEmail);
                temporalData.append('customerPhone', ttv2FormData.customerPhone);
                temporalData.append('customerAddress', '');
                temporalData.append('customerCity', '');
                temporalData.append('customerPostalCode', '');
                temporalData.append('tipoTitulacion', ttv2FormData.tipoTitulacion);
                temporalData.append('tipoServicio', ttv2FormData.tipoServicio);
                temporalData.append('numeroTitulo', '');
                temporalData.append('fechaExpedicion', '');
                temporalData.append('amount', ttv2FormData.amount);

                // 2. Crear pago Redsys
                const paymentData = new FormData();
                paymentData.append('action', 'ttv2_create_redsys_payment');
                paymentData.append('amount', ttv2FormData.amount);

                const response = await fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    body: paymentData
                });

                const result = await response.json();

                if (result.success) {
                    // Actualizar datos temporales con OrderId
                    temporalData.set('orderId', result.data.orderId);
                    await fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                        method: 'POST',
                        body: temporalData
                    });

                    // Preparar formulario Redsys
                    const form = document.getElementById('ttv2-redsys-form');
                    form.action = result.data.paymentData.url;
                    document.getElementById('ttv2-Ds_SignatureVersion').value = result.data.paymentData.Ds_SignatureVersion;
                    document.getElementById('ttv2-Ds_MerchantParameters').value = result.data.paymentData.Ds_MerchantParameters;
                    document.getElementById('ttv2-Ds_Signature').value = result.data.paymentData.Ds_Signature;

                    // Enviar a Redsys
                    setTimeout(() => {
                        form.submit();
                    }, 1500);
                } else {
                    throw new Error(result.data.message || 'Error al crear el pago');
                }

            } catch (error) {
                console.error('Error:', error);
                alert('Error al procesar el pago: ' + error.message);
                document.getElementById('ttv2-payment-modal').classList.remove('active');
                document.getElementById('ttv2-pay-button').disabled = false;
            }
        }

        // Auto-fill para administradores
        function ttv2AutoFillAdmin() {
            // Seleccionar primer servicio
            document.querySelector('.ttv2-service-box').click();
            
            // Datos personales
            document.getElementById('ttv2-customer-name').value = 'Admin Test TTV2';
            document.getElementById('ttv2-customer-dni').value = '12345678Z';
            document.getElementById('ttv2-customer-email').value = 'admin@tramitfy.es';
            document.getElementById('ttv2-customer-phone').value = '600000000';
        }
    </script>

    <?php
    return ob_get_clean();
}

// Registrar shortcode
add_shortcode('titulaciones_v2_form', 'ttv2_form_shortcode');