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

if (!defined('TTV2_REDSYS_MODE')) define('TTV2_REDSYS_MODE', 'live');
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
if (!defined('TTV2_REDSYS_URL_OK')) define('TTV2_REDSYS_URL_OK', 'https://tramitfy.es/pago-completado-tn/');
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
        'Ds_Merchant_ProductDescription' => 'Renovacion Titulacion Nautica',
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
        // Usar el OrderID recibido del frontend (ya padeado) o generar uno nuevo si no viene
        $order_id = !empty($_POST['orderId']) ? $_POST['orderId'] : str_pad(time(), 12, '0', STR_PAD_LEFT);
        if (strlen($order_id) > 12) { $order_id = substr($order_id, -12); }
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

        .ttv2-pricing-box {
            background: rgba(255,255,255,0.1);
            border-radius: 12px;
            padding: 20px;
            margin-top: 25px;
        }

        .ttv2-pricing-header h3 {
            margin: 0 0 15px 0;
            font-size: 16px;
            font-weight: 600;
        }

        .ttv2-price-main {
            font-size: 32px;
            font-weight: bold;
            text-align: center;
            margin-bottom: 15px;
            color: #ffffff;
        }

        .ttv2-price-description {
            border-top: 1px solid rgba(255,255,255,0.2);
            padding-top: 15px;
        }

        .ttv2-price-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .ttv2-price-note {
            font-size: 12px;
            opacity: 0.8;
            font-style: italic;
            margin-top: 10px;
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
            max-width: 700px;
            margin: 30px auto;
        }
        
        .ttv2-services-grid-2x2 {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            grid-template-rows: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 15px;
        }
        
        @media (max-width: 768px) {
            .ttv2-services-grid-2x2 {
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
            }
        }
        
        @media (max-width: 480px) {
            .ttv2-services-grid-2x2 {
                grid-template-columns: 1fr;
                grid-template-rows: auto;
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
            position: relative;
            display: flex;
            justify-content: space-between;
            align-items: center;
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

        /* Sistema de upload múltiple TBV2 style */
        .ttv2-document-upload {
            padding: 15px;
            background: #f8f9fa;
            border-top: 1px solid #e5e5e5;
        }
        
        .ttv2-upload-wrapper {
            position: relative;
        }
        
        .ttv2-upload-btn {
            width: 100%;
            padding: 12px 20px;
            background: rgb(var(--ttv2-primary));
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }
        
        .ttv2-upload-btn:hover {
            background: rgb(var(--ttv2-primary-dark));
            transform: translateY(-1px);
        }
        
        .ttv2-upload-btn.has-files {
            background: #ecfdf5;
            color: #10b981;
            border: 1px solid #10b981;
        }
        
        .ttv2-upload-btn .desktop-text {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .ttv2-upload-btn .mobile-text {
            display: none;
        }
        
        @media (max-width: 768px) {
            .ttv2-upload-btn .desktop-text {
                display: none;
            }
            .ttv2-upload-btn .mobile-text {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                font-size: 16px;
            }
        }
        
        .ttv2-file-input {
            display: none;
        }
        
        .ttv2-file-count {
            font-size: 12px;
            color: #6b7280;
            text-align: center;
            margin-top: 5px;
        }
        
        .ttv2-file-count.has-files {
            color: #10b981;
        }
        
        /* Preview de archivos múltiples */
        .ttv2-file-preview-container {
            margin-top: 10px;
            max-height: 150px;
            overflow-y: auto;
            border-radius: 6px;
        }
        
        .ttv2-file-preview-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 10px;
            margin-bottom: 4px;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 12px;
            transition: all 0.2s;
        }
        
        .ttv2-file-preview-item:hover {
            border-color: rgba(var(--ttv2-primary), 0.3);
            background: #fafbfc;
        }
        
        .ttv2-file-preview-info {
            display: flex;
            align-items: center;
            flex: 1;
            min-width: 0;
            gap: 8px;
        }
        
        .ttv2-file-preview-icon {
            font-size: 16px;
            color: #6b7280;
            flex-shrink: 0;
        }
        
        .ttv2-file-preview-details {
            flex: 1;
            min-width: 0;
        }
        
        .ttv2-file-preview-name {
            font-weight: 500;
            color: #374151;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: block;
        }
        
        .ttv2-file-preview-size {
            font-size: 11px;
            color: #9ca3af;
            margin-top: 2px;
        }
        
        .ttv2-file-remove-btn {
            background: #ef4444;
            border: none;
            border-radius: 4px;
            color: white;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            flex-shrink: 0;
        }
        
        .ttv2-file-remove-btn:hover {
            background: #dc2626;
            transform: scale(1.1);
        }
        
        @media (max-width: 768px) {
            .ttv2-file-preview-container {
                max-height: 120px;
            }
            
            .ttv2-file-preview-item {
                padding: 6px 8px;
                font-size: 11px;
            }
            
            .ttv2-file-preview-icon {
                font-size: 14px;
            }
            
            .ttv2-file-preview-size {
                font-size: 10px;
            }
            
            .ttv2-file-remove-btn {
                width: 20px;
                height: 20px;
                font-size: 12px;
            }
        }
        
        /* Ver ejemplo link */
        .ttv2-view-example {
            color: rgba(var(--ttv2-primary), 1);
            text-decoration: underline;
            font-size: 12px;
            cursor: pointer;
            font-weight: 500;
            padding: 2px 6px;
            background: rgba(var(--ttv2-primary), 0.08);
            border-radius: 4px;
            transition: all 0.2s;
        }
        
        .ttv2-view-example:hover {
            background: rgba(var(--ttv2-primary), 0.15);
            text-decoration: none;
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
        
        @media (max-width: 768px) {
            .ttv2-signature-area {
                flex-direction: column;
                gap: 10px;
                padding: 15px;
            }
            
            .ttv2-signature-area .ttv2-button {
                width: 100%;
                padding: 12px;
            }
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

        /* Modal de firma responsivo */
        .ttv2-signature-modal-responsive {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 99999;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .ttv2-signature-modal-content {
            background: white;
            border-radius: 16px;
            max-width: 800px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
        }
        
        .ttv2-signature-header {
            padding: 20px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            background: white;
            z-index: 10;
            border-radius: 16px 16px 0 0;
        }
        
        .ttv2-signature-title {
            margin: 0;
            font-size: 20px;
            color: #1f2937;
            font-weight: 600;
        }
        
        .ttv2-close-signature {
            font-size: 28px;
            color: #9ca3af;
            cursor: pointer;
            line-height: 1;
            padding: 0 5px;
            transition: color 0.2s;
        }
        
        .ttv2-close-signature:hover {
            color: #374151;
        }
        
        .ttv2-authorization-section {
            padding: 0 20px;
        }
        
        
        .ttv2-authorization-preview {
            background: #f9fafb;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 13px;
            line-height: 1.6;
            color: #374151;
            max-height: 200px;
            overflow-y: auto;
        }
        
        .ttv2-signature-canvas-container {
            padding: 20px;
            background: #fafafa;
        }
        
        .ttv2-signature-instruction {
            text-align: center;
            color: #6b7280;
            font-size: 14px;
            margin: 0 0 15px 0;
        }
        
        .ttv2-canvas-wrapper {
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            overflow: hidden;
            position: relative;
            width: 100%;
            height: 250px;
        }
        
        .ttv2-signature-canvas {
            width: 100%;
            height: 100%;
            cursor: crosshair;
            touch-action: none;
        }
        
        .ttv2-signature-actions {
            padding: 20px;
            display: flex;
            gap: 12px;
            justify-content: center;
            border-top: 1px solid #e5e7eb;
            background: #fafafa;
            border-radius: 0 0 16px 16px;
        }
        
        .ttv2-btn-clear,
        .ttv2-btn-save {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }
        
        .ttv2-btn-clear {
            background: #f3f4f6;
            color: #6b7280;
            border: 1px solid #e5e7eb;
        }
        
        .ttv2-btn-clear:hover {
            background: #e5e7eb;
            color: #374151;
        }
        
        .ttv2-btn-save {
            background: rgb(var(--ttv2-primary));
            color: white;
            flex: 1;
            max-width: 200px;
        }
        
        .ttv2-btn-save:hover {
            background: rgb(var(--ttv2-primary-dark));
            transform: translateY(-1px);
        }
        
        /* Responsive móvil para firma */
        @media (max-width: 768px) {
            .ttv2-signature-modal-responsive {
                padding: 0;
            }
            
            .ttv2-signature-modal-content {
                max-width: 100%;
                height: 100%;
                max-height: 100%;
                border-radius: 0;
                display: flex;
                flex-direction: column;
            }
            
            .ttv2-signature-header {
                padding: 15px;
                border-radius: 0;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                background: white;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            
            .ttv2-signature-title {
                font-size: 18px;
            }
            
            .ttv2-authorization-section {
                order: 1;
                margin-top: 60px;
                padding: 0 15px;
                flex-shrink: 0;
            }
            
            
            .ttv2-authorization-preview {
                font-size: 12px;
                max-height: 150px;
            }
            
            .ttv2-signature-canvas-container {
                order: 2;
                flex: 1;
                padding: 15px;
                display: flex;
                flex-direction: column;
                justify-content: center;
                background: white;
                min-height: 250px; /* Altura mínima para el canvas */
            }
            
            .ttv2-signature-instruction {
                font-size: 13px;
                margin-bottom: 10px;
            }
            
            .ttv2-canvas-wrapper {
                height: 200px;
                max-height: 40vh;
                border-radius: 8px;
            }
            
            .ttv2-signature-actions {
                order: 3;
                position: static;
                padding: 15px;
                background: white;
                border-top: 1px solid #e5e7eb;
                margin: 0 15px;
                border-radius: 0 0 8px 8px;
                gap: 10px;
            }
            
            .ttv2-btn-clear,
            .ttv2-btn-save {
                padding: 14px 20px;
                font-size: 14px;
            }
            
            .ttv2-btn-clear {
                min-width: 100px;
            }
            
            .ttv2-btn-save {
                flex: 1;
                max-width: none;
            }
            
            .ttv2-btn-clear span,
            .ttv2-btn-save span {
                display: none;
            }
            
            @media (min-width: 360px) {
                .ttv2-btn-clear span,
                .ttv2-btn-save span {
                    display: inline;
                }
            }
            
            /* Ocultar modales externos cuando firma está activa en móvil */
            body.ttv2-signature-modal-open {
                /* Selectores comunes de cookies */
                #cookie-banner,
                .cookie-notice,
                .cookie-consent,
                .cookie-popup,
                .cookies-banner,
                #cookies-banner,
                .gdpr-banner,
                .wpcc-banner,
                .moove_gdpr_cookie_modal,
                .cli-modal-backdrop,
                
                /* Selectores comunes de WhatsApp */
                #whatsapp-widget,
                .whatsapp-widget,
                .wa-widget,
                .wa-button,
                .whatsapp-button,
                .whatsapp-float,
                .wp-whatsapp,
                .joinchat,
                .cht-widget,
                .wa-chat-box,
                
                /* Otros modales comunes */
                .popup-overlay,
                .modal-backdrop,
                .newsletter-popup,
                .promo-banner,
                .gtranslate_wrapper,
                .translate-widget {
                    display: none !important;
                    visibility: hidden !important;
                    opacity: 0 !important;
                    pointer-events: none !important;
                    z-index: -1 !important;
                }
            }
        }
        
        @media (max-width: 360px) {
            .ttv2-canvas-wrapper {
                height: 150px;
            }
            
            .ttv2-signature-actions {
                padding: 12px;
            }
            
            .ttv2-btn-clear,
            .ttv2-btn-save {
                padding: 12px 16px;
                font-size: 13px;
            }
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
            
            .ttv2-pricing-box {
                padding: 15px;
                margin-top: 20px;
            }
            
            .ttv2-price-main {
                font-size: 28px;
            }
            
            /* Página de pago móvil optimizada */
            #ttv2-redsys-container {
                padding: 15px;
            }
            
            .ttv2-redsys-payment-info {
                padding: 18px;
            }
            
            #ttv2-submit-payment {
                padding: 14px;
                font-size: 16px;
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

            .ttv2-services-grid-2x2 {
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
        
        /* Modal de Advertencia Inicial */
        .ttv2-warning-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.85);
            z-index: 100000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            backdrop-filter: blur(5px);
        }
        
        .ttv2-warning-overlay.hidden {
            display: none;
        }
        
        .ttv2-warning-modal {
            background: white;
            border-radius: 20px;
            max-width: 600px;
            width: 100%;
            padding: 0;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.3s ease-out;
        }
        
        @keyframes slideUp {
            from {
                transform: translateY(30px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        .ttv2-warning-header {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            color: white;
            padding: 30px;
            border-radius: 20px 20px 0 0;
            text-align: center;
        }
        
        .ttv2-warning-header h2 {
            margin: 0;
            font-size: 24px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            color: white !important;
        }
        
        .ttv2-warning-header .warning-icon {
            background: rgba(255, 255, 255, 0.2);
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        
        .ttv2-warning-body {
            padding: 35px 30px;
        }
        
        .ttv2-warning-message {
            background: #fef2f2;
            border: 2px solid #fee2e2;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
        }
        
        .ttv2-warning-message p {
            margin: 0 0 15px 0;
            color: #7f1d1d;
            font-size: 15px;
            line-height: 1.6;
        }
        
        .ttv2-warning-message p:last-child {
            margin-bottom: 0;
        }
        
        .ttv2-warning-message strong {
            color: #991b1b;
            font-weight: 700;
        }
        
        .ttv2-valid-entities {
            background: #f0fdf4;
            border: 2px solid #bbf7d0;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
        }
        
        .ttv2-valid-entities h4 {
            margin: 0 0 12px 0;
            color: #14532d;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .ttv2-valid-entities ul {
            margin: 0;
            padding-left: 25px;
            color: #166534;
        }
        
        .ttv2-valid-entities li {
            margin: 8px 0;
            font-size: 14px;
        }
        
        .ttv2-warning-checkbox {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
        }
        
        .ttv2-warning-checkbox label {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            cursor: pointer;
            font-size: 14px;
            line-height: 1.5;
            color: #374151;
        }
        
        .ttv2-warning-checkbox input[type="checkbox"] {
            width: 20px;
            height: 20px;
            min-width: 20px;
            margin-top: 2px;
            accent-color: rgb(var(--ttv2-primary));
            cursor: pointer;
        }
        
        .ttv2-warning-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
        }
        
        .ttv2-btn-cancel {
            padding: 14px 30px;
            background: #f3f4f6;
            color: #6b7280;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .ttv2-btn-cancel:hover {
            background: #e5e7eb;
            color: #4b5563;
        }
        
        .ttv2-btn-confirm {
            padding: 14px 40px;
            background: rgb(var(--ttv2-primary));
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            opacity: 0.5;
            pointer-events: none;
        }
        
        .ttv2-btn-confirm.enabled {
            opacity: 1;
            pointer-events: auto;
        }
        
        .ttv2-btn-confirm.enabled:hover {
            background: rgb(var(--ttv2-primary-dark));
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(var(--ttv2-primary), 0.3);
        }
        
        /* Formulario bloqueado */
        .ttv2-container.blocked {
            filter: blur(3px);
            pointer-events: none;
            opacity: 0.6;
        }
        
        @media (max-width: 768px) {
            .ttv2-warning-modal {
                margin: 10px;
                max-width: calc(100% - 20px);
            }
            
            .ttv2-warning-header {
                padding: 25px 20px;
            }
            
            .ttv2-warning-header h2 {
                font-size: 20px;
            }
            
            .ttv2-warning-body {
                padding: 25px 20px;
            }
            
            .ttv2-warning-message p {
                font-size: 14px;
            }
            
            .ttv2-valid-entities li {
                font-size: 13px;
            }
            
            .ttv2-warning-actions {
                flex-direction: column;
            }
            
            .ttv2-btn-cancel,
            .ttv2-btn-confirm {
                width: 100%;
                padding: 16px;
            }
        }
        
        /* ======================================== */
        /* Eligibility Verification Page - Sobrio */
        /* ======================================== */
        .ttv2-eligibility-page {
            max-width: 900px;
            margin: 0 auto;
            padding: 40px 30px;
            line-height: 1.6;
        }
        
        .ttv2-eligibility-content > div {
            margin-bottom: 35px;
        }
        
        .ttv2-main-messages {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-bottom: 40px;
        }
        
        .ttv2-requirement h3,
        .ttv2-restriction h3,
        .ttv2-guidance h3 {
            font-size: 18px;
            color: #374151;
            margin-bottom: 15px;
            font-weight: 600;
        }
        
        .ttv2-requirement p,
        .ttv2-restriction p,
        .ttv2-guidance p {
            color: #4b5563;
            font-size: 15px;
            margin: 0;
            line-height: 1.7;
        }
        
        .ttv2-requirement {
            padding: 25px;
            background: #f8fafc;
            border-left: 4px solid #016d86;
            border-radius: 0 6px 6px 0;
        }
        
        .ttv2-restriction {
            padding: 25px;
            background: #fafafa;
            border-left: 4px solid #9ca3af;
            border-radius: 0 6px 6px 0;
        }
        
        .ttv2-guidance {
            padding: 25px;
            background: #f9fafb;
            border-left: 4px solid #d1d5db;
            border-radius: 0 6px 6px 0;
        }
        
        .ttv2-confirmation-section {
            background: #ffffff;
            border: 1px solid #d1d5db;
            padding: 25px;
            margin: 35px 0;
        }
        
        .ttv2-confirmation-label {
            display: flex;
            align-items: flex-start;
            cursor: pointer;
        }
        
        .ttv2-confirmation-label input[type="checkbox"] {
            width: 18px;
            height: 18px;
            margin-right: 15px;
            margin-top: 2px;
            cursor: pointer;
            flex-shrink: 0;
        }
        
        .ttv2-confirmation-text {
            color: #374151;
            font-size: 15px;
            line-height: 1.6;
        }
        
        .ttv2-navigation-buttons {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            margin-top: 40px;
        }
        
        .ttv2-btn-back,
        .ttv2-btn-continue {
            padding: 12px 24px;
            font-size: 15px;
            font-weight: 500;
            border: 1px solid #d1d5db;
            background: #ffffff;
            color: #374151;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .ttv2-btn-back:hover {
            background: #f3f4f6;
        }
        
        .ttv2-btn-continue {
            background: #016d86;
            color: white;
            border-color: #016d86;
        }
        
        .ttv2-btn-continue:hover:not(:disabled) {
            background: #015a70;
        }
        
        .ttv2-btn-continue:disabled {
            background: #d1d5db;
            color: #9ca3af;
            cursor: not-allowed;
            border-color: #d1d5db;
        }
        
        /* Mobile responsive */
        @media (max-width: 768px) {
            .ttv2-eligibility-page {
                padding: 30px 20px;
                max-width: 100%;
            }
            
            .ttv2-main-messages {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .ttv2-navigation-buttons {
                flex-direction: column;
            }
            
            .ttv2-btn-back,
            .ttv2-btn-continue {
                width: 100%;
                text-align: center;
            }
            
            .ttv2-requirement,
            .ttv2-restriction,
            .ttv2-guidance {
                padding: 20px;
            }
            
            .ttv2-confirmation-section {
                padding: 20px;
            }
        }
        
        /* Tablet responsive */
        @media (max-width: 1024px) and (min-width: 769px) {
            .ttv2-eligibility-page {
                max-width: 800px;
                padding: 35px 25px;
            }
        }
    </style>

    <div class="ttv2-container" id="ttv2-main-container">
        <!-- Sidebar Izquierdo -->
        <div class="ttv2-sidebar">
            <div style="padding: 0 0 20px 0; border-bottom: 1px solid rgba(255,255,255,0.1);">
                <h3 style="font-size: 20px; font-weight: 700; margin: 0 0 10px 0; line-height: 1.3;">
                    Renueva tu título náutico sin moverte de casa
                </h3>
                <p style="opacity: 0.95; font-size: 14px; margin: 0; line-height: 1.6;">
                    Renueva el PER, PNB, patrón o capitán de yate en 1 minuto y 100% online.
                </p>
            </div>

            <div class="ttv2-pricing-box" style="margin-top: 20px;">
                <div class="ttv2-pricing-header">
                    <h3 style="margin: 0 0 5px 0;">Renovación de Titulación Náutica</h3>
                </div>
                <div class="ttv2-pricing-content">
                    <div style="display: flex; align-items: baseline; margin: 10px 0;">
                        <span style="font-size: 24px; opacity: 0.8;">€</span>
                        <span class="ttv2-price-main" id="ttv2-sidebar-price" style="font-size: 48px; margin: 0 5px;">55</span>
                        <span style="font-size: 24px; opacity: 0.8;">,00</span>
                    </div>
                    
                    <div style="margin-top: 20px;">
                        <div style="display: flex; align-items: center; margin-bottom: 10px;">
                            <span style="color: #4ade80; margin-right: 8px;">✓</span>
                            <span style="font-size: 14px;">Se presenta a Capitanía Marítima en un plazo máximo de 24 h</span>
                        </div>
                        <div style="display: flex; align-items: center; margin-bottom: 10px;">
                            <span style="color: #4ade80; margin-right: 8px;">✓</span>
                            <span style="font-size: 14px;">IVA incluido</span>
                        </div>
                        <div style="display: flex; align-items: center; margin-bottom: 10px;">
                            <span style="color: #4ade80; margin-right: 8px;">✓</span>
                            <span style="font-size: 14px;">Tasas administrativas incluidas</span>
                        </div>
                        <div style="display: flex; align-items: center; margin-bottom: 10px;">
                            <span style="color: #4ade80; margin-right: 8px;">✓</span>
                            <span style="font-size: 14px;">Seguimiento personalizado</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Mini Progress Indicator (oculto) -->
            <div class="ttv2-progress" style="display: none;">
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

            <!-- Widget de Reseñas Trustindex -->
            <div class="ttv2-sidebar-footer" style="margin-top: auto;">
                <div id="ttv2-reviews-container"></div>
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
                <!-- PASO 0: Verificación de Elegibilidad -->
                <!-- PASO 1: Selector de Tipo de Servicio -->
                <div class="ttv2-step active" data-step="1">
                    <h2>Elige tu titulación a renovar</h2>
                    <p>Selecciona el tipo de titulación náutica que deseas renovar.</p>

                    <div class="ttv2-services-wrapper">
                        <div class="ttv2-services-grid-2x2">
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
                            
                            <div class="ttv2-service-box" data-service="capitan_de_yate" data-price="55">
                                <div class="ttv2-service-title">CAPITÁN DE YATE</div>
                                <div class="ttv2-service-description">Máxima titulación náutica recreativa</div>
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
                            <input type="text" id="ttv2-customer-name" name="customerName" required value="">
                            <small style="color: #666; font-size: 12px; display: block; margin-top: 5px;">Introduzca su nombre completo tal como aparece en su DNI</small>
                        </div>

                        <div class="ttv2-form-group">
                            <label for="ttv2-customer-dni">DNI/NIE <span class="required">*</span></label>
                            <input type="text" id="ttv2-customer-dni" name="customerDni" required value="">
                            <small style="color: #666; font-size: 12px; display: block; margin-top: 5px;">Documento Nacional de Identidad o NIE sin espacios ni guiones</small>
                        </div>
                    </div>

                    <div class="ttv2-form-row">
                        <div class="ttv2-form-group">
                            <label for="ttv2-customer-email">Correo Electrónico <span class="required">*</span></label>
                            <input type="email" id="ttv2-customer-email" name="customerEmail" required value="">
                            <small style="color: #666; font-size: 12px; display: block; margin-top: 5px;">Recibirá las notificaciones del trámite en este email</small>
                        </div>

                        <div class="ttv2-form-group">
                            <label for="ttv2-customer-phone">Teléfono <span class="required">*</span></label>
                            <input type="tel" id="ttv2-customer-phone" name="customerPhone" required value="">
                            <small style="color: #666; font-size: 12px; display: block; margin-top: 5px;">Teléfono móvil para contacto y notificaciones SMS</small>
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
                                <h3 class="ttv2-document-title">Copia del DNI por ambas caras</h3>
                                <a href="#" class="ttv2-example-link ttv2-view-example" data-doc="dni-comprador" onclick="event.stopPropagation();">Ver ejemplo</a>
                            </div>
                            <div class="ttv2-document-upload">
                                <div class="ttv2-upload-wrapper">
                                    <input type="file" id="ttv2-dni" class="ttv2-file-input" name="dniFile[]" multiple accept="image/*,application/pdf" onchange="ttv2HandleMultipleFiles(this, 'ttv2-dni')">
                                    <div class="ttv2-upload-btn" onclick="document.getElementById('ttv2-dni').click()" data-input="ttv2-dni">
                                        <span class="desktop-text"><i class="fa-solid fa-upload"></i> Seleccionar archivos</span>
                                        <span class="mobile-text"><i class="fa-solid fa-camera"></i></span>
                                    </div>
                                    <div class="ttv2-file-count" data-input="ttv2-dni">Sin archivos</div>
                                    <div class="ttv2-file-preview-container" data-input="ttv2-dni"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Certificado médico -->
                        <div class="ttv2-document-box">
                            <div class="ttv2-document-header">
                                <h3 class="ttv2-document-title">Certificado médico psicotécnico por ambas caras</h3>
                                <a href="#" class="ttv2-example-link ttv2-view-example" data-doc="certificado-medico-plantilla" onclick="event.stopPropagation();">Ver ejemplo</a>
                            </div>
                            <div class="ttv2-document-upload">
                                <div class="ttv2-upload-wrapper">
                                    <input type="file" id="ttv2-certificado" class="ttv2-file-input" name="certificadoFile[]" multiple accept="image/*,application/pdf" onchange="ttv2HandleMultipleFiles(this, 'ttv2-certificado')">
                                    <div class="ttv2-upload-btn" onclick="document.getElementById('ttv2-certificado').click()" data-input="ttv2-certificado">
                                        <span class="desktop-text"><i class="fa-solid fa-upload"></i> Seleccionar archivos</span>
                                        <span class="mobile-text"><i class="fa-solid fa-camera"></i></span>
                                    </div>
                                    <div class="ttv2-file-count" data-input="ttv2-certificado">Sin archivos</div>
                                    <div class="ttv2-file-preview-container" data-input="ttv2-certificado"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Documentación caducada -->
                        <div class="ttv2-document-box">
                            <div class="ttv2-document-header">
                                <h3 class="ttv2-document-title">Copia documentación caducada</h3>
                                <a href="#" class="ttv2-example-link ttv2-view-example" data-doc="QUE-TITULO-NECESITO" onclick="event.stopPropagation();">Ver ejemplo</a>
                            </div>
                            <div class="ttv2-document-upload">
                                <div class="ttv2-upload-wrapper">
                                    <input type="file" id="ttv2-titulacion" class="ttv2-file-input" name="titulacionFile[]" multiple accept="image/*,application/pdf" onchange="ttv2HandleMultipleFiles(this, 'ttv2-titulacion')">
                                    <div class="ttv2-upload-btn" onclick="document.getElementById('ttv2-titulacion').click()" data-input="ttv2-titulacion">
                                        <span class="desktop-text"><i class="fa-solid fa-upload"></i> Seleccionar archivos</span>
                                        <span class="mobile-text"><i class="fa-solid fa-camera"></i></span>
                                    </div>
                                    <div class="ttv2-file-count" data-input="ttv2-titulacion">Sin archivos</div>
                                    <div class="ttv2-file-preview-container" data-input="ttv2-titulacion"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Firma del documento -->
                        <div class="ttv2-document-box">
                            <div class="ttv2-document-header">
                                <h3 class="ttv2-document-title">Firma del documento de autorización</h3>
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
                    <h2 style="margin-bottom: 12px; color: #016d86; font-size: 24px; font-weight: 600;">Método de Pago</h2>
                    <p style="color: #666; margin-bottom: 25px; font-size: 15px; line-height: 1.6;">Pago seguro con TPV CaixaBank. Será redirigido a la pasarela segura para completar el pago.</p>

                    <!-- Redsys Container - Ancho completo -->
                    <div id="ttv2-redsys-container">
                        <!-- Redsys Payment Info -->
                        <div class="ttv2-redsys-payment-info" style="background: #f8f9ff; border: 1px solid #e0e7ff; border-radius: 12px; padding: 24px; margin-bottom: 25px;">
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

                        <!-- Terms and Conditions Checkbox -->
                        <div class="ttv2-terms-container ttv2-payment-terms" style="margin: 20px 0; padding: 16px; border: 1px solid #e5e7eb; border-radius: 8px; background-color: #f9fafb;">
                            <label style="display: flex; align-items: flex-start; gap: 10px; font-size: 14px; line-height: 1.5; cursor: pointer; color: #374151;">
                                <input type="checkbox" id="ttv2-terms-accept-pago" name="ttv2_terms_accept_pago" style="margin-top: 2px; width: 16px; height: 16px; accent-color: #016d86; cursor: pointer;">
                                <span style="font-size: 14px;">Acepto los <a href="https://tramitfy.es/terminos-y-condiciones-de-uso/" target="_blank" style="color: #016d86; text-decoration: none; font-weight: 500;">términos y condiciones de pago</a></span>
                            </label>
                        </div>

                        <!-- Payment Message -->
                        <div id="ttv2-payment-message" class="hidden" style="margin: 20px 0; padding: 15px; border-radius: 8px; text-align: center; font-weight: 500; display: none;"></div>

                        <!-- Payment Button -->
                        <button type="button" id="ttv2-submit-payment" class="ttv2-btn-primary" style="width: 100%; padding: 16px; font-size: 18px; font-weight: 600; background: linear-gradient(135deg, #016d86 0%, #015266 100%); color: white; border: none; border-radius: 8px; cursor: pointer; margin-top: 20px; transition: all 0.3s ease; box-shadow: 0 4px 6px -1px rgba(1, 109, 134, 0.3), 0 2px 4px -1px rgba(1, 109, 134, 0.2);" disabled>
                            <i class="fa-solid fa-credit-card"></i> Proceder al Pago
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

    <!-- Modal para firma optimizado móvil -->
    <div id="ttv2-signature-modal" class="ttv2-signature-modal-responsive" style="display:none;">
        <div class="ttv2-signature-modal-content">
            <!-- Header del modal -->
            <div class="ttv2-signature-header">
                <h3 class="ttv2-signature-title">Firma Digital</h3>
                <span class="ttv2-close-signature">&times;</span>
            </div>
            
            <!-- Documento preview (siempre visible) -->
            <div class="ttv2-authorization-section">
                <h4 style="margin: 15px 0 10px 0; color: #4b5563; font-size: 14px; font-weight: 600;">Documento de autorización</h4>
                <div id="ttv2-authorization-preview" class="ttv2-authorization-preview"></div>
            </div>
            
            <!-- Canvas de firma responsivo -->
            <div class="ttv2-signature-canvas-container">
                <p class="ttv2-signature-instruction">Firme con el dedo o ratón</p>
                <div class="ttv2-canvas-wrapper">
                    <canvas id="ttv2-signature-pad" class="ttv2-signature-canvas"></canvas>
                </div>
            </div>
            
            <!-- Botones de acción -->
            <div class="ttv2-signature-actions">
                <button type="button" class="ttv2-btn-clear" id="ttv2-clear-signature">
                    <i class="fa-solid fa-eraser"></i>
                    <span>Limpiar</span>
                </button>
                <button type="button" class="ttv2-btn-save" id="ttv2-save-signature">
                    <i class="fa-solid fa-check"></i>
                    <span>Guardar Firma</span>
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
        let ttv2Files = {
            'ttv2-dni': [],
            'ttv2-certificado': [],
            'ttv2-titulacion': []
        };

        // Textos descriptivos para servicios
        const ttv2ServiceDescriptions = {
            'PNB': {
                title: 'Patrón de Navegación Básica',
                info: 'Renovación de P.N.B. Necesitarás tu titulación actual y DNI.'
            },
            'PER': {
                title: 'Patrón de Embarcaciones de Recreo',
                info: 'Renovación de P.E.R. Gestión completa del trámite.'
            },
            'patron_de_yate': {
                title: 'Patrón de Yate',
                info: 'Renovación de Patrón de Yate. Incluye gestión completa.'
            },
            'capitan_de_yate': {
                title: 'Capitán de Yate',
                info: 'Renovación de Capitán de Yate. Máxima titulación recreativa.'
            },
        };

        document.addEventListener('DOMContentLoaded', function() {
            // Cargar widget de reseñas
            setTimeout(function() {
                ttv2LoadReviewsWidget();
            }, 1000);
            
            // Event listener para el checkbox de términos
            const termsCheckbox = document.getElementById('ttv2-terms-accept-pago');
            const submitButton = document.getElementById('ttv2-submit-payment');
            
            if (termsCheckbox && submitButton) {
                // Función para validar y actualizar estado del botón
                function updatePaymentButtonState() {
                    const errors = ttv2ValidateForPayment();
                    const isValid = errors.length === 0;
                    
                    submitButton.disabled = !isValid;
                    
                    if (isValid) {
                        submitButton.style.opacity = '1';
                        submitButton.style.cursor = 'pointer';
                        submitButton.innerHTML = '<i class="fa-solid fa-credit-card"></i> Proceder al Pago';
                    } else {
                        submitButton.style.opacity = '0.6';
                        submitButton.style.cursor = 'not-allowed';
                        const missingCount = errors.length;
                        submitButton.innerHTML = `<i class="fa-solid fa-exclamation-triangle"></i> Faltan ${missingCount} campo${missingCount > 1 ? 's' : ''}`;
                    }
                }
                
                // Actualizar inicialmente
                updatePaymentButtonState();
                
                // Listener para términos
                termsCheckbox.addEventListener('change', updatePaymentButtonState);
                
                // Listeners para campos del formulario
                document.getElementById('ttv2-customer-name')?.addEventListener('input', updatePaymentButtonState);
                document.getElementById('ttv2-customer-dni')?.addEventListener('input', updatePaymentButtonState);
                document.getElementById('ttv2-customer-email')?.addEventListener('input', updatePaymentButtonState);
                document.getElementById('ttv2-customer-phone')?.addEventListener('input', updatePaymentButtonState);
                
                // Listener para cambios de archivos (delegado para archivos dinámicos)
                document.addEventListener('ttv2-files-updated', updatePaymentButtonState);
                
                // Listener para firma (cuando se guarde)
                document.addEventListener('ttv2-signature-saved', updatePaymentButtonState);
                
                submitButton.addEventListener('click', function(e) {
                    e.preventDefault();
                    ttv2ProcessPayment();
                });
            }

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

            // Event listeners para navegación (libre - sin restricciones)
            document.querySelectorAll('.ttv2-nav-item').forEach(item => {
                item.addEventListener('click', function() {
                    const targetStep = parseInt(this.dataset.nav);
                    // Navegación libre - solo verificar que no estemos en DGMM (step 0)
                    if (ttv2CurrentStep > 0) {
                        ttv2GoToStep(targetStep);
                        console.log(`TTV2: Free navigation to step ${targetStep}`);
                    } else {
                        // Estamos en step 0 (DGMM), mostrar aviso
                        ttv2ShowAlert('⚠️ Verificación Requerida', 
                            'Debe aceptar la verificación de elegibilidad DGMM antes de continuar con el formulario.',
                            'warning');
                    }
                });
            });

            // Sistema de archivos múltiples inicializado por onchange en HTML

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
                    document.body.classList.remove('ttv2-signature-modal-open');
                    ttv2ShowExternalModals();
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
            
            // Hacer función global para que sea accesible desde ttv2GoToStep
            window.ttv2GenerateAuthorizationDocument = function() {
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
            
            // Función para ocultar modales externos agresivamente
            function ttv2HideExternalModals() {
                const modalSelectors = [
                    // Cookies
                    '#cookie-banner', '.cookie-notice', '.cookie-consent', '.cookie-popup',
                    '.cookies-banner', '#cookies-banner', '.gdpr-banner', '.wpcc-banner',
                    '.moove_gdpr_cookie_modal', '.cli-modal-backdrop', '.wp-cookies-ok',
                    '.cookie-law-info-bar', '.cookie-notice-hidden',
                    
                    // WhatsApp
                    '#whatsapp-widget', '.whatsapp-widget', '.wa-widget', '.wa-button',
                    '.whatsapp-button', '.whatsapp-float', '.wp-whatsapp', '.joinchat',
                    '.cht-widget', '.wa-chat-box', '.whatsapp-launcher', '.wa-widget-send-button',
                    
                    // Otros comunes
                    '.popup-overlay', '.modal-backdrop', '.newsletter-popup', '.promo-banner',
                    '.gtranslate_wrapper', '.translate-widget', '#google_translate_element',
                    '.crisp-client', '.intercom-launcher', '.tawk-widget',
                    '.facebook-messenger', '.messenger-widget'
                ];
                
                modalSelectors.forEach(selector => {
                    const elements = document.querySelectorAll(selector);
                    elements.forEach(element => {
                        if (element) {
                            element.style.display = 'none';
                            element.style.visibility = 'hidden';
                            element.style.opacity = '0';
                            element.style.pointerEvents = 'none';
                            element.style.zIndex = '-9999';
                            element.setAttribute('data-ttv2-hidden', 'true');
                        }
                    });
                });
                
                console.log('TTV2: External modals hidden for signature view');
            }
            
            // Función para restaurar modales externos
            function ttv2ShowExternalModals() {
                const hiddenElements = document.querySelectorAll('[data-ttv2-hidden="true"]');
                hiddenElements.forEach(element => {
                    element.style.display = '';
                    element.style.visibility = '';
                    element.style.opacity = '';
                    element.style.pointerEvents = '';
                    element.style.zIndex = '';
                    element.removeAttribute('data-ttv2-hidden');
                });
                
                console.log('TTV2: External modals restored');
            }
            
            // Función para mostrar alertas personalizadas
            function ttv2ShowAlert(title, message, type = 'info') {
                const alertTypes = {
                    'warning': { icon: '⚠️', color: '#f59e0b', bg: '#fef3c7' },
                    'error': { icon: '❌', color: '#dc2626', bg: '#fef2f2' },
                    'success': { icon: '✅', color: '#16a34a', bg: '#f0fdf4' },
                    'info': { icon: 'ℹ️', color: '#2563eb', bg: '#eff6ff' }
                };
                
                const alertConfig = alertTypes[type] || alertTypes['info'];
                
                // Generar ID único para el modal
                const alertId = 'ttv2-alert-' + Date.now();
                
                // Crear modal de alerta
                const alertModal = document.createElement('div');
                alertModal.id = alertId;
                alertModal.style.cssText = `
                    position: fixed;
                    top: 0; left: 0; right: 0; bottom: 0;
                    background: rgba(0,0,0,0.5);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    z-index: 10000;
                    animation: fadeIn 0.3s ease;
                `;
                
                const alertContent = document.createElement('div');
                alertContent.style.cssText = `
                    background: white;
                    border-radius: 12px;
                    padding: 25px;
                    max-width: 400px;
                    margin: 20px;
                    box-shadow: 0 10px 25px rgba(0,0,0,0.2);
                    text-align: center;
                    animation: slideIn 0.3s ease;
                `;
                
                const buttonId = alertId + '-btn';
                
                alertContent.innerHTML = `
                    <div style="color: ${alertConfig.color}; font-size: 48px; margin-bottom: 15px;">
                        ${alertConfig.icon}
                    </div>
                    <h3 style="margin: 0 0 15px 0; color: #374151; font-size: 18px; font-weight: 600;">
                        ${title}
                    </h3>
                    <p style="margin: 0 0 25px 0; color: #6b7280; line-height: 1.5; font-size: 14px;">
                        ${message}
                    </p>
                    <button id="${buttonId}" 
                            style="background: ${alertConfig.color}; color: white; border: none; 
                                   padding: 10px 24px; border-radius: 6px; cursor: pointer; 
                                   font-weight: 500; font-size: 14px; transition: all 0.2s;">
                        Entendido
                    </button>
                `;
                
                alertModal.appendChild(alertContent);
                document.body.appendChild(alertModal);
                
                // Agregar event listener al botón
                const closeButton = document.getElementById(buttonId);
                if (closeButton) {
                    closeButton.addEventListener('click', function() {
                        alertModal.remove();
                    });
                }
                
                // Click fuera del modal para cerrarlo
                alertModal.addEventListener('click', function(e) {
                    if (e.target === alertModal) {
                        alertModal.remove();
                    }
                });
                
                // Auto-remove after 8 seconds
                setTimeout(() => {
                    if (alertModal.parentNode) {
                        alertModal.remove();
                    }
                }, 8000);
            }
            
            // Función de validación movida fuera del bloque DOMContentLoaded
            
            // Inicializar SignaturePad cuando se abre el modal
            if (ttv2OpenSignatureBtn) {
                ttv2OpenSignatureBtn.addEventListener('click', function() {
                    ttv2GenerateAuthorizationDocument();
                    ttv2SignatureModal.style.display = 'flex';
                    document.body.classList.add('ttv2-signature-modal-open');
                    
                    // Forzar ocultación de modales específicos
                    ttv2HideExternalModals();
                    
                    // Esperar un momento para que el modal se renderice
                    setTimeout(() => {
                        if (!ttv2SignaturePad) {
                            const canvas = document.getElementById('ttv2-signature-pad');
                            const wrapper = canvas.parentElement;
                            
                            // Configuración responsiva del canvas
                            function setupCanvas() {
                                const rect = wrapper.getBoundingClientRect();
                                const ratio = Math.max(window.devicePixelRatio || 1, 1);
                                
                                // Ajustar tamaño del canvas al contenedor
                                canvas.width = rect.width * ratio;
                                canvas.height = rect.height * ratio;
                                canvas.style.width = rect.width + 'px';
                                canvas.style.height = rect.height + 'px';
                                
                                // Escalar contexto para alta resolución
                                const ctx = canvas.getContext('2d');
                                ctx.scale(ratio, ratio);
                                
                                return { width: rect.width, height: rect.height };
                            }
                            
                            const dimensions = setupCanvas();
                            
                            // Inicializar SignaturePad con configuración optimizada
                            ttv2SignaturePad = new SignaturePad(canvas, {
                                backgroundColor: 'rgb(255, 255, 255)',
                                penColor: 'rgb(0, 0, 0)',
                                minWidth: window.innerWidth < 768 ? 1.5 : 0.5,
                                maxWidth: window.innerWidth < 768 ? 3 : 2.5,
                                throttle: 16, // Mejor rendimiento en móvil
                                minDistance: window.innerWidth < 768 ? 3 : 5,
                                velocityFilterWeight: 0.7
                            });
                            
                            // Redimensionar canvas cuando cambia el tamaño de ventana
                            let resizeTimeout;
                            window.addEventListener('resize', function() {
                                clearTimeout(resizeTimeout);
                                resizeTimeout = setTimeout(() => {
                                    const signatureData = ttv2SignaturePad.toDataURL();
                                    setupCanvas();
                                    ttv2SignaturePad.fromDataURL(signatureData);
                                }, 250);
                            });
                        }
                        
                        // Restaurar firma si ya existe
                        if (ttv2SignatureData && ttv2SignaturePad) {
                            ttv2SignaturePad.fromDataURL(ttv2SignatureData);
                        }
                    }, 100);
                });
            }
            
            // Limpiar firma
            if (ttv2ClearSignatureBtn) {
                ttv2ClearSignatureBtn.addEventListener('click', function() {
                    if (ttv2SignaturePad) {
                        ttv2SignaturePad.clear();
                        // Vibrar en móvil para feedback táctil
                        if ('vibrate' in navigator && window.innerWidth < 768) {
                            navigator.vibrate(50);
                        }
                    }
                });
            }
            
            // Guardar firma
            if (ttv2SaveSignatureBtn) {
                ttv2SaveSignatureBtn.addEventListener('click', function() {
                    if (ttv2SignaturePad && !ttv2SignaturePad.isEmpty()) {
                        ttv2SignatureData = ttv2SignaturePad.toDataURL();
                        
                        // Hacer la firma accesible globalmente
                        window.ttv2SignatureData = ttv2SignatureData;
                        
                        // Mostrar estado de firma completada
                        const signatureStatus = document.getElementById('ttv2-signature-status');
                        if (signatureStatus) {
                            signatureStatus.style.display = 'block';
                        }
                        
                        // Actualizar botón de firma
                        const signBtn = document.getElementById('ttv2-open-signature-modal');
                        if (signBtn) {
                            signBtn.innerHTML = '<i class="fa-solid fa-check"></i> Firmado';
                            signBtn.style.background = '#ecfdf5';
                            signBtn.style.color = '#10b981';
                            signBtn.style.border = '1px solid #10b981';
                        }
                        
                        // Vibrar en móvil para feedback táctil
                        if ('vibrate' in navigator && window.innerWidth < 768) {
                            navigator.vibrate([50, 50, 100]);
                        }
                        
                        // Cerrar modal con animación
                        ttv2SignatureModal.style.display = 'none';
                        document.body.classList.remove('ttv2-signature-modal-open');
                        ttv2ShowExternalModals();
                        
                        // Guardar en el array de archivos para enviar con el formulario
                        ttv2Files['signature'] = ttv2SignatureData;
                        
                        // Disparar evento para actualizar botón de pago
                        document.dispatchEvent(new CustomEvent('ttv2-signature-saved'));
                    } else {
                        alert('Por favor, firme el documento antes de guardar.');
                        // Vibrar para error
                        if ('vibrate' in navigator && window.innerWidth < 768) {
                            navigator.vibrate([100, 50, 100]);
                        }
                    }
                });
            }
            
            // Cerrar modal de firma
            if (ttv2CloseSignatureBtn) {
                ttv2CloseSignatureBtn.addEventListener('click', function() {
                    ttv2SignatureModal.style.display = 'none';
                    document.body.classList.remove('ttv2-signature-modal-open');
                    ttv2ShowExternalModals();
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

            // Auto-fill deshabilitado
        });

        // Navegación entre pasos
        function ttv2NextStep() {
            ttv2GoToStep(ttv2CurrentStep + 1);
        }

        function ttv2PrevStep() {
            ttv2GoToStep(ttv2CurrentStep - 1);
        }

        function ttv2GoToStep(step) {
            console.log(`TTV2: Navigating from step ${ttv2CurrentStep} to step ${step}`);
            if (step < 1 || step > 4) return;

            // Ocultar paso actual
            const currentStepElement = document.querySelector(`.ttv2-step[data-step="${ttv2CurrentStep}"]`);
            if (currentStepElement) currentStepElement.classList.remove('active');
            
            // Actualizar navegación (solo si no es step 0 - DGMM)
            if (ttv2CurrentStep > 0) {
                const currentNavItem = document.querySelector(`.ttv2-nav-item[data-nav="${ttv2CurrentStep}"]`);
                if (currentNavItem) currentNavItem.classList.remove('active');
                const currentProgressStep = document.querySelector(`.ttv2-progress-step[data-step="${ttv2CurrentStep}"]`);
                if (currentProgressStep) currentProgressStep.classList.remove('active');
            }
            
            // Si el paso actual se completó (solo para steps > 0)
            if (step > ttv2CurrentStep && ttv2CurrentStep > 0) {
                const completedNavItem = document.querySelector(`.ttv2-nav-item[data-nav="${ttv2CurrentStep}"]`);
                if (completedNavItem) completedNavItem.classList.add('completed');
                const progressStep = document.querySelector(`.ttv2-progress-step[data-step="${ttv2CurrentStep}"]`);
                if (progressStep) progressStep.classList.add('completed');
            }

            // Mostrar nuevo paso
            ttv2CurrentStep = step;
            const newStepElement = document.querySelector(`.ttv2-step[data-step="${step}"]`);
            if (newStepElement) {
                newStepElement.classList.add('active');
                console.log(`TTV2: Step ${step} element found and activated`);
            } else {
                console.error(`TTV2: Step ${step} element NOT FOUND!`);
            }
            
            // Activar navegación (solo para steps > 0)
            if (step > 0) {
                const newNavItem = document.querySelector(`.ttv2-nav-item[data-nav="${step}"]`);
                if (newNavItem) newNavItem.classList.add('active');
                const newProgressStep = document.querySelector(`.ttv2-progress-step[data-step="${step}"]`);
                if (newProgressStep) newProgressStep.classList.add('active');
            }

            // Si es el paso 2, actualizar la titulación seleccionada
            if (step === 2) {
                ttv2UpdateSelectedTitle();
            }
            
            // Si es el paso 3, generar documento de autorización
            if (step === 3) {
                setTimeout(ttv2GenerateAuthorizationDocument, 100);
            }
            
            // Si es el último paso, actualizar precio en botón
            if (step === 4) {
                // El precio ya se actualiza con ttv2SelectedPrice
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
                'capitan_de_yate': 'Capitán de Yate'
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
                // Sin validaciones - permitir continuar sin documentos
                // Los documentos ahora son opcionales para pasar a la página de pago
            }

            return true;
        }

        // Actualizar sidebar
        function ttv2UpdateSidebar() {
            // Sidebar content removed - only reviews widget remains
            // No need to update service/titulacion/total elements
        }
        
        // Reviews widget removed - caused conflicts with DGMM verification
        // function ttv2LoadReviewsWidget() { ... } // REMOVED

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

        // Sistema de manejo de archivos múltiples con preview (TBV2 style)
        let ttv2UploadedFiles = {};
        
        function ttv2HandleMultipleFiles(input, inputId) {
            const files = Array.from(input.files);
            const countElement = document.querySelector(`[data-input="${inputId}"]`);
            const previewContainer = document.querySelector(`.ttv2-file-preview-container[data-input="${inputId}"]`);
            const uploadBtn = document.querySelector(`.ttv2-upload-btn[data-input="${inputId}"]`);
            
            // Inicializar array si no existe
            if (!ttv2UploadedFiles[inputId]) {
                ttv2UploadedFiles[inputId] = [];
            }
            
            // Agregar nuevos archivos
            ttv2UploadedFiles[inputId] = [...ttv2UploadedFiles[inputId], ...files];
            
            // Actualizar contador
            if (countElement) {
                const fileCount = ttv2UploadedFiles[inputId].length;
                if (fileCount === 0) {
                    countElement.textContent = 'Sin archivos';
                    countElement.classList.remove('has-files');
                    uploadBtn?.classList.remove('has-files');
                } else {
                    countElement.textContent = `${fileCount} archivo${fileCount > 1 ? 's' : ''} seleccionado${fileCount > 1 ? 's' : ''}`;
                    countElement.classList.add('has-files');
                    uploadBtn?.classList.add('has-files');
                }
            }
            
            // Actualizar preview
            ttv2RenderFilesList(inputId);
            
            // Actualizar ttv2Files para compatibilidad
            ttv2Files[inputId] = ttv2UploadedFiles[inputId];
            
            // Disparar evento para actualizar botón de pago
            document.dispatchEvent(new CustomEvent('ttv2-files-updated'));
        }
        
        function ttv2RenderFilesList(inputId) {
            const container = document.querySelector(`.ttv2-file-preview-container[data-input="${inputId}"]`);
            if (!container || !ttv2UploadedFiles[inputId]) return;
            
            const files = ttv2UploadedFiles[inputId];
            container.innerHTML = '';
            
            files.forEach((file, index) => {
                const previewItem = ttv2CreateFilePreviewItem(file, inputId, index);
                container.appendChild(previewItem);
            });
        }
        
        function ttv2CreateFilePreviewItem(file, inputId, index) {
            const item = document.createElement('div');
            item.className = 'ttv2-file-preview-item';
            
            const fileIcon = ttv2GetFileIcon(file.type);
            const fileSize = ttv2FormatFileSize(file.size);
            
            item.innerHTML = `
                <div class="ttv2-file-preview-info">
                    <i class="${fileIcon} ttv2-file-preview-icon"></i>
                    <div class="ttv2-file-preview-details">
                        <div class="ttv2-file-preview-name" title="${file.name}">${file.name}</div>
                        <div class="ttv2-file-preview-size">${fileSize}</div>
                    </div>
                </div>
                <button type="button" class="ttv2-file-remove-btn" onclick="ttv2RemoveFile('${inputId}', ${index})">
                    <i class="fa-solid fa-times"></i>
                </button>
            `;
            
            return item;
        }
        
        function ttv2RemoveFile(inputId, index) {
            if (!ttv2UploadedFiles[inputId]) return;
            
            // Eliminar archivo del array
            ttv2UploadedFiles[inputId].splice(index, 1);
            
            // Re-renderizar lista
            ttv2RenderFilesList(inputId);
            
            // Actualizar contador
            const countElement = document.querySelector(`.ttv2-file-count[data-input="${inputId}"]`);
            const uploadBtn = document.querySelector(`.ttv2-upload-btn[data-input="${inputId}"]`);
            const fileCount = ttv2UploadedFiles[inputId].length;
            
            if (countElement) {
                if (fileCount === 0) {
                    countElement.textContent = 'Sin archivos';
                    countElement.classList.remove('has-files');
                    uploadBtn?.classList.remove('has-files');
                } else {
                    countElement.textContent = `${fileCount} archivo${fileCount > 1 ? 's' : ''} seleccionado${fileCount > 1 ? 's' : ''}`;
                }
            }
            
            // Actualizar input file
            ttv2UpdateInputFiles(inputId);
            
            // Actualizar ttv2Files para compatibilidad
            ttv2Files[inputId] = ttv2UploadedFiles[inputId];
            
            // Disparar evento para actualizar botón de pago
            document.dispatchEvent(new CustomEvent('ttv2-files-updated'));
        }
        
        function ttv2UpdateInputFiles(inputId) {
            const input = document.getElementById(inputId);
            if (!input || !ttv2UploadedFiles[inputId]) return;
            
            // Crear DataTransfer para actualizar el input
            const dt = new DataTransfer();
            ttv2UploadedFiles[inputId].forEach(file => {
                dt.items.add(file);
            });
            
            input.files = dt.files;
        }
        
        function ttv2GetFileIcon(fileType) {
            if (fileType.startsWith('image/')) {
                return 'fa-solid fa-image';
            } else if (fileType === 'application/pdf') {
                return 'fa-solid fa-file-pdf';
            } else {
                return 'fa-solid fa-file';
            }
        }
        
        function ttv2FormatFileSize(bytes) {
            if (bytes === 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
        }

        // Función para comprimir imagen antes de convertir a base64
        async function ttv2CompressImage(file, maxWidth = 1920, maxHeight = 1920, quality = 0.8) {
            return new Promise((resolve, reject) => {
                // Si no es imagen, devolver sin comprimir
                if (!file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = () => resolve(reader.result);
                    reader.onerror = reject;
                    reader.readAsDataURL(file);
                    return;
                }
                
                const img = new Image();
                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d');
                
                img.onload = function() {
                    let width = img.width;
                    let height = img.height;
                    
                    // Calcular nuevo tamaño manteniendo proporción
                    if (width > maxWidth || height > maxHeight) {
                        const ratio = Math.min(maxWidth / width, maxHeight / height);
                        width = Math.round(width * ratio);
                        height = Math.round(height * ratio);
                    }
                    
                    canvas.width = width;
                    canvas.height = height;
                    
                    // Dibujar imagen redimensionada
                    ctx.drawImage(img, 0, 0, width, height);
                    
                    // Convertir a base64 con calidad reducida
                    const compressedBase64 = canvas.toDataURL(file.type || 'image/jpeg', quality);
                    
                    console.log(`TTV2: Imagen comprimida de ${file.size} bytes a ~${compressedBase64.length * 0.75} bytes`);
                    resolve(compressedBase64);
                };
                
                img.onerror = reject;
                
                // Leer archivo original
                const reader = new FileReader();
                reader.onload = (e) => {
                    img.src = e.target.result;
                };
                reader.onerror = reject;
                reader.readAsDataURL(file);
            });
        }
        
        // Función para convertir archivo a base64 (sin comprimir para PDFs)
        function ttv2FileToBase64(file) {
            return new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.onload = () => resolve(reader.result);
                reader.onerror = reject;
                reader.readAsDataURL(file);
            });
        }

        // Función para convertir todos los archivos a base64
        async function ttv2ConvertFilesToBase64() {
            const filesArray = [];
            
            // Procesar cada categoría de archivo
            const fileCategories = [
                'ttv2-dni',
                'ttv2-certificado',
                'ttv2-titulacion'
            ];
            
            for (const inputId of fileCategories) {
                // Usar ttv2UploadedFiles si existe, sino ttv2Files
                const filesToUse = ttv2UploadedFiles[inputId] || ttv2Files[inputId];
                
                if (filesToUse && filesToUse.length > 0) {
                    console.log(`TTV2: Procesando ${filesToUse.length} archivo(s) de ${inputId}`);
                    
                    for (const file of filesToUse) {
                        try {
                            // Usar compresión para imágenes, conversión normal para otros archivos
                            const base64 = file.type.startsWith('image/') 
                                ? await ttv2CompressImage(file, 1920, 1920, 0.8)
                                : await ttv2FileToBase64(file);
                            
                            // Usar el mismo formato que TBV2
                            filesArray.push({
                                name: file.name,
                                base64: base64,
                                size: file.size, // Tamaño original (para referencia)
                                compressedSize: base64.length * 0.75, // Tamaño aproximado comprimido
                                type: file.type,
                                category: inputId // Mantener la categoría
                            });
                            console.log(`TTV2: Archivo procesado: ${file.name} (${file.type.startsWith('image/') ? 'comprimido' : 'sin comprimir'})`);
                        } catch (error) {
                            console.error(`Error convirtiendo archivo ${file.name}:`, error);
                        }
                    }
                }
            }
            
            // Añadir firma digital si existe
            if (window.ttv2SignatureData) {
                filesArray.push({
                    name: 'firma_digital.png',
                    base64: window.ttv2SignatureData,
                    size: window.ttv2SignatureData.length,
                    type: 'image/png',
                    category: 'signature'
                });
                console.log('TTV2: Firma digital incluida');
            }
            
            console.log('TTV2: Total archivos convertidos:', filesArray.length);
            return filesArray;
        }

        // Procesar pago
        async function ttv2ProcessPayment() {
            // Validar datos completos antes del pago
            const validationErrors = ttv2ValidateForPayment();
            if (validationErrors.length > 0) {
                const errorMessage = validationErrors.length === 1 ? 
                    validationErrors[0] : 
                    `Por favor, complete los siguientes campos:\n\n• ${validationErrors.join('\n• ')}`;
                
                ttv2ShowAlert('📋 Datos Incompletos', errorMessage, 'warning');
                return;
            }

            // Mostrar modal
            document.getElementById('ttv2-payment-modal').classList.add('active');
            const submitBtn = document.getElementById('ttv2-submit-payment');
            if (submitBtn) submitBtn.disabled = true;

            try {
                // Verificar que tenemos datos
                if (!ttv2FormData.amount) {
                    ttv2FormData.amount = ttv2SelectedPrice || 55;
                }
                
                console.log('TTV2 - Procesando pago con datos:', ttv2FormData);
                
                // NUEVO: Convertir archivos a base64
                console.log('TTV2: Iniciando conversión de archivos a base64...');
                const filesArray = await ttv2ConvertFilesToBase64();
                console.log('TTV2: Archivos convertidos:', filesArray.length, 'archivos');
                
                // Generar OrderID aquí con precisión de 10 segundos para evitar discrepancias
                // Redondeamos a la decena más cercana para dar margen en dispositivos lentos
                const timestamp = Math.floor(Date.now() / 10000) * 10; // Timestamp redondeado a 10 segundos
                const generatedOrderId = '00' + timestamp.toString().padStart(10, '0');
                console.log('TTV2: OrderID generado para Redsys (redondeado):', generatedOrderId);
                
                // Preparar estructura temporal sin datos de formulario (se capturarán después)
                const baseCaptureData = {
                    orderId: generatedOrderId,
                    files: filesArray,
                    tramiteType: 'titulaciones-v2',
                    metadata: {
                        timestamp: Date.now(),
                        formId: 'ttv2'
                    }
                };
                
                // NUEVO: Capturar datos del DOM justo antes del pago (estado final)
                const customerName = document.getElementById('ttv2-customer-name')?.value?.trim() || '';
                const customerDni = document.getElementById('ttv2-customer-dni')?.value?.trim() || '';
                const customerEmail = document.getElementById('ttv2-customer-email')?.value?.trim() || '';
                const customerPhone = document.getElementById('ttv2-customer-phone')?.value?.trim() || '';
                
                console.log('TTV2: Capturando datos del DOM en estado final:', {
                    name: customerName,
                    dni: customerDni, 
                    email: customerEmail,
                    phone: customerPhone
                });
                
                // Completar datos de captura con información del formulario
                // GA4 tracking
                const gaMatch = document.cookie.match(/_ga=GA\d+\.\d+\.(.+)/);
                const gaClientId = gaMatch ? gaMatch[1] : '';
                const gclidVal = new URLSearchParams(window.location.search).get('gclid') || sessionStorage.getItem('gclid') || '';
                const gaSessionMatch = document.cookie.match(/_ga_[A-Z0-9]+=GS\d+\.\d+\.(.+?)(?:\.|$)/);
                const gaSessionIdVal = gaSessionMatch ? gaSessionMatch[1] : '';

                const captureData = {
                    ...baseCaptureData,
                    customerData: {
                        name: customerName,
                        dni: customerDni,
                        email: customerEmail,
                        phone: customerPhone,
                        address: '',
                        city: '',
                        postalCode: '',
                        province: ''
                    },
                    serviceData: {
                        tipoServicio: ttv2FormData.tipoServicio || 'PNB',
                        tipoTitulacion: ttv2FormData.tipoTitulacion || '',
                        numeroTitulo: ttv2FormData.numeroTitulo || '',
                        fechaExpedicion: ttv2FormData.fechaExpedicion || ''
                    },
                    pricing: {
                        amount: ttv2FormData.amount || 55,
                        basePrice: 55,
                        tasas: 35,
                        honorarios: 20
                    },
                    ga_client_id: gaClientId,
                    gclid: gclidVal,
                    ga_session_id: gaSessionIdVal
                };
                
                console.log('TTV2: Enviando al API temporal con datos completos...');
                
                // Enviar al API temporal de Tramitfy
                const captureResponse = await fetch('https://tramitfy.org/api/temporal/capture', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(captureData)
                });
                
                const captureResult = await captureResponse.json();
                console.log('TTV2: Respuesta del API temporal:', captureResult);
                
                if (!captureResult.success) {
                    throw new Error(captureResult.error || 'Error capturando datos temporales');
                }
                
                // Usar el OrderID que generamos antes para garantizar sincronización
                const orderId = generatedOrderId; // Usar el mismo que enviamos al temporal
                console.log('TTV2: OrderId para Redsys:', orderId);
                
                // Crear pago Redsys con el OrderId sincronizado
                const paymentData = new FormData();
                paymentData.append('action', 'ttv2_create_redsys_payment');
                paymentData.append('amount', ttv2FormData.amount);
                paymentData.append('orderId', orderId); // Usar el OrderId generado localmente
                
                const ajaxUrl = '<?php echo admin_url("admin-ajax.php"); ?>';
                const response = await fetch(ajaxUrl, {
                    method: 'POST',
                    body: paymentData
                });

                const result = await response.json();

                if (result.success) {
                    console.log('TTV2: Pago Redsys creado, redirigiendo...');
                    
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
                console.error('TTV2 Error:', error);
                alert('Error al procesar el pago: ' + error.message);
                document.getElementById('ttv2-payment-modal').classList.remove('active');
                const payButton = document.getElementById('ttv2-submit-payment');
                if (payButton) payButton.disabled = false;
            }
        }

        // Función para cargar widget de reseñas de forma segura
        function ttv2LoadReviewsWidget() {
            console.log('TTV2: Loading reviews widget...');
            
            const container = document.getElementById('ttv2-reviews-container');
            if (!container) {
                console.error('TTV2: Reviews container not found');
                return;
            }
            
            // Crear script de Trustindex
            const script = document.createElement('script');
            script.defer = true;
            script.async = true;
            script.src = 'https://cdn.trustindex.io/loader.js?f4fbfd341d12439e0c86fae7fc2';
            
            script.onload = function() {
                console.log('TTV2: Reviews widget loaded successfully');
            };
            
            script.onerror = function() {
                console.error('TTV2: Failed to load reviews widget');
            };
            
            // Agregar al container
            container.appendChild(script);
        }

        // Función para validar datos completos antes del pago (MOVIDA FUERA DE DOMContentLoaded)
        function ttv2ValidateForPayment() {
            const errors = [];
            
            // Validar servicio seleccionado
            if (!ttv2SelectedService) {
                errors.push('Debe seleccionar un tipo de servicio');
            }
            
            // Validar datos personales
            const name = document.getElementById('ttv2-customer-name')?.value?.trim();
            const dni = document.getElementById('ttv2-customer-dni')?.value?.trim();
            const email = document.getElementById('ttv2-customer-email')?.value?.trim();
            const phone = document.getElementById('ttv2-customer-phone')?.value?.trim();
            
            if (!name) errors.push('Debe ingresar su nombre completo');
            if (!dni) errors.push('Debe ingresar su DNI/NIE');
            if (!email) errors.push('Debe ingresar su email');
            if (!phone) errors.push('Debe ingresar su teléfono');
            
            // Validar documentación
            const dniFiles = ttv2UploadedFiles['ttv2-dni'] || ttv2Files['ttv2-dni'] || [];
            const certificadoFiles = ttv2UploadedFiles['ttv2-certificado'] || ttv2Files['ttv2-certificado'] || [];
            const titulacionFiles = ttv2UploadedFiles['ttv2-titulacion'] || ttv2Files['ttv2-titulacion'] || [];
            
            if (dniFiles.length === 0) {
                errors.push('Debe subir su DNI/NIE por ambas caras');
            }
            if (certificadoFiles.length === 0) {
                errors.push('Debe subir su certificado médico psicotécnico');
            }
            if (titulacionFiles.length === 0) {
                errors.push('Debe subir la copia de la documentación caducada');
            }
            
            // Validar firma
            if (!window.ttv2SignatureData) {
                errors.push('Debe firmar el documento de autorización');
            }
            
            // Validar términos y condiciones
            const termsCheckbox = document.getElementById('ttv2-terms-accept-pago');
            if (termsCheckbox && !termsCheckbox.checked) {
                errors.push('Debe aceptar los términos y condiciones');
            }
            
            return errors;
        }

    </script>

    <?php
    return ob_get_clean();
}

// Registrar shortcode
add_shortcode('titulaciones_v2_form', 'ttv2_form_shortcode');

/**
 * Handler AJAX para crear formulario de pago Redsys
 */
function ttv2_handle_create_redsys_payment() {
    // Limpiar cualquier output previo
    if (ob_get_level()) {
        ob_end_clean();
    }
    ob_start();
    
    try {
        // Usar OrderID del frontend (sincronizado con temporal capture)
        $orderId = sanitize_text_field($_POST['orderId'] ?? '');
        if (empty($orderId) || !preg_match('/^[0-9]{1,12}$/', $orderId)) {
            // Fallback: generar uno nuevo (no debería pasar)
            $orderId = str_pad(time(), 12, '0', STR_PAD_LEFT);
            if (strlen($orderId) > 12) { $orderId = substr($orderId, -12); }
            error_log("TTV2: WARNING - OrderID no recibido del frontend, generando nuevo: $orderId");
        }

        // Obtener monto del formulario
        $amount = floatval($_POST['amount'] ?? 55);
        $amount_cents = strval(intval($amount * 100));
        
        error_log("TTV2: Creando pago - OrderID: $orderId, Amount: {$amount}€");
        
        // Crear datos de pago para Redsys
        $payment_data = ttv2_redsys_create_payment_form([
            'order_id' => $orderId,
            'amount_cents' => $amount_cents,
            'description' => 'Titulacion Nautica'
        ]);
        
        // Limpiar buffer y enviar respuesta
        ob_end_clean();
        
        wp_send_json_success([
            'orderId' => $orderId,
            'paymentData' => $payment_data
        ]);
        
    } catch (Exception $e) {
        ob_end_clean();
        error_log("TTV2 Error: " . $e->getMessage());
        wp_send_json_error(['message' => $e->getMessage()]);
    }
    
    exit;
}


// Registrar handlers AJAX
add_action('wp_ajax_ttv2_create_redsys_payment', 'ttv2_handle_create_redsys_payment');
add_action('wp_ajax_nopriv_ttv2_create_redsys_payment', 'ttv2_handle_create_redsys_payment');
add_action('wp_ajax_ttv2_store_temporal', 'ttv2_store_temporal');
add_action('wp_ajax_nopriv_ttv2_store_temporal', 'ttv2_store_temporal');
add_action('wp_ajax_ttv2_send_confirmation_emails', 'ttv2_send_confirmation_emails_handler');
add_action('wp_ajax_nopriv_ttv2_send_confirmation_emails', 'ttv2_send_confirmation_emails_handler');

/**
 * Envía emails de confirmación para TTV2 usando wp_mail
 */
function ttv2_send_confirmation_emails($orderId, $paymentData, $formData) {
    error_log("TTV2 EMAILS - Iniciando para OrderID: $orderId");
    error_log("TTV2 EMAILS - paymentData recibido: " . print_r($paymentData, true));
    error_log("TTV2 EMAILS - formData recibido: " . print_r($formData, true));
    
    // Extraer datos del pago y formulario
    $finalAmount = floatval($paymentData['Ds_Amount']) / 100; // Redsys envía en centimos
    $authCode = $paymentData['Ds_AuthorisationCode'] ?? '';
    $tramiteId = 'TTV2-' . $orderId;
    
    // Datos del cliente desde formulario
    $customerEmail = $formData['customerEmail'] ?? $formData['personal']['customerEmail'] ?? 'cliente@example.com';
    $customerName = $formData['customerName'] ?? $formData['personal']['customerName'] ?? 'Cliente';
    $customerDni = $formData['customerDni'] ?? '';
    $tipoServicio = $formData['tipoServicio'] ?? 'Renovación de Titulación';
    
    error_log("TTV2 EMAILS - Datos extraídos: Email=$customerEmail, Nombre=$customerName, DNI=$customerDni, Servicio=$tipoServicio, Importe=$finalAmount");
    error_log("TTV2 EMAILS - DEBUG customerEmail: '" . ($formData['customerEmail'] ?? 'VACÍO') . "'");
    error_log("TTV2 EMAILS - DEBUG customerName: '" . ($formData['customerName'] ?? 'VACÍO') . "'");
    error_log("TTV2 EMAILS - DEBUG usando fallbacks: Email=" . ($customerEmail === 'cliente@example.com' ? 'SÍ' : 'NO') . ", Nombre=" . ($customerName === 'Cliente' ? 'SÍ' : 'NO'));
    
    // Headers para emails (formato compatible como TMV2)
    $headers = ['Content-Type: text/html; charset=UTF-8'];
    
    // === EMAIL AL CLIENTE (USANDO PATRÓN EXACTO TBV2) ===
    $to_customer = $customerEmail;  // Misma variable que TBV2 funciona
    $subject_customer = ' Confirmación de Renovación de Titulación - TRAMITFY';  // Mismo formato que TBV2
    
    $message_customer = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #016d86; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background: #f9f9f9; }
            .footer { padding: 15px; text-align: center; background: #eee; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🚢 TRAMITFY</h1>
                <h2>Renovación de Titulación Confirmada</h2>
            </div>
            <div class='content'>
                <p>Estimado/a <strong>$customerName</strong>,</p>
                
                <p>Su solicitud de renovación de titulación náutica ha sido recibida y confirmada correctamente.</p>
                
                <h3>📋 Resumen del Trámite:</h3>
                <ul>
                    <li><strong>Número de Trámite:</strong> $tramiteId</li>
                    <li><strong>Tipo de Servicio:</strong> $tipoServicio</li>
                    <li><strong>Importe Total:</strong> " . number_format($finalAmount, 2) . "€</li>
                    <li><strong>Código de Autorización:</strong> $authCode</li>
                </ul>
                
                <h3>📋 Próximos Pasos:</h3>
                <p>Procesaremos su documentación y gestionaremos la renovación ante la Capitanía Marítima correspondiente. Le mantendremos informado del estado de su trámite.</p>
                
                <h3>🔗 Enlaces Útiles:</h3>
                <p><a href='https://tramitfy.org/seguimiento/$tramiteId' style='color: #016d86; text-decoration: none;'>📱 Seguir mi trámite</a></p>
                
                <p>Gracias por confiar en TRAMITFY para sus trámites náuticos.</p>
            </div>
            <div class='footer'>
                <p>© 2024 TRAMITFY - Gestión de Trámites Náuticos<br>
                Web: tramitfy.org | Email: info@tramitfy.es</p>
            </div>
        </div>
    </body>
    </html>";
    
    // DEBUG CRÍTICO: Verificar destinatario del email del cliente (PATRÓN TBV2)
    error_log("TTV2 EMAILS - ENVIANDO CLIENTE - Destinatario: '$to_customer'");
    error_log("TTV2 EMAILS - ENVIANDO CLIENTE - Subject: '$subject_customer'");
    error_log("TTV2 EMAILS - ENVIANDO CLIENTE - Headers: " . print_r(['Content-Type: text/html; charset=UTF-8'], true));
    
    // Usar EXACTAMENTE el mismo formato que TBV2 (que SÍ funciona)
    $email_sent_customer = wp_mail($to_customer, $subject_customer, $message_customer, ['Content-Type: text/html; charset=UTF-8']);
    
    error_log("TTV2 EMAILS - RESULTADO CLIENTE - Email enviado a '$to_customer': " . ($email_sent_customer ? 'SUCCESS' : 'FAILED'));
    error_log("TTV2 EMAILS - wp_mail return: " . var_export($email_sent_customer, true));
    
    // === EMAIL AL ADMIN ===
    $admin_email = 'ipmgroup24@gmail.com';
    $subject_admin = "🎓 Nueva Renovación TTV2 - $tramiteId - " . number_format($finalAmount, 2) . "€";
    
    $message_admin = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #dc2626; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background: #f9f9f9; }
            .data { background: white; padding: 15px; margin: 10px 0; border-left: 4px solid #dc2626; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🎓 NUEVA RENOVACIÓN TTV2</h1>
                <h2>$tramiteId</h2>
            </div>
            <div class='content'>
                <div class='data'>
                    <h3>👤 Datos del Cliente:</h3>
                    <p><strong>Nombre:</strong> $customerName</p>
                    <p><strong>DNI:</strong> $customerDni</p>
                    <p><strong>Email:</strong> $customerEmail</p>
                </div>
                
                <div class='data'>
                    <h3>🎓 Datos del Servicio:</h3>
                    <p><strong>Tipo:</strong> $tipoServicio</p>
                    <p><strong>Importe:</strong> " . number_format($finalAmount, 2) . "€</p>
                    <p><strong>OrderID:</strong> $orderId</p>
                    <p><strong>Autorización:</strong> $authCode</p>
                </div>
                
                <div class='data'>
                    <h3>🔗 Enlaces Rápidos:</h3>
                    <p><a href='https://tramitfy.org/tramites/$tramiteId' style='color: #dc2626;'>📊 Ver en Dashboard</a></p>
                </div>
            </div>
        </div>
    </body>
    </html>";
    
    // Usar EXACTAMENTE el mismo formato que TMV2 (que funciona)  
    $email_sent_admin = wp_mail($admin_email, $subject_admin, $message_admin, ['Content-Type: text/html; charset=UTF-8']);
    error_log("TTV2 EMAILS - Email admin a $admin_email: " . ($email_sent_admin ? 'SUCCESS' : 'FAILED'));
    
    return [
        'client' => $email_sent_customer,
        'admin' => $email_sent_admin,
        'tramite_id' => $tramiteId
    ];
}

/**
 * Handler AJAX para envío de emails de confirmación TTV2
 */
function ttv2_send_confirmation_emails_handler() {
    error_log('TTV2 EMAILS HANDLER - Iniciado');
    
    // Verificar que es una petición AJAX válida
    if (!defined('DOING_AJAX') || !DOING_AJAX) {
        error_log('TTV2 EMAILS - No es petición AJAX');
        wp_die('Acceso directo no permitido');
        return;
    }
    
    try {
        error_log('TTV2 EMAILS - Datos recibidos: ' . print_r($_POST, true));
        
        $orderId = $_POST['orderId'] ?? '';
        $paymentDataJson = $_POST['paymentData'] ?? '{}';
        $formDataJson = $_POST['formData'] ?? '{}';
        
        // DEBUG: Datos RAW recibidos
        error_log('TTV2 EMAILS - formDataJson RAW: ' . $formDataJson);
        
        // Decodificar JSON
        $paymentData = json_decode($paymentDataJson, true);
        $formData = json_decode($formDataJson, true);
        
        // DEBUG: Verificar si json_decode falló
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('TTV2 EMAILS - ERROR JSON: ' . json_last_error_msg());
        }
        
        error_log('TTV2 EMAILS - OrderID: ' . $orderId);
        error_log('TTV2 EMAILS - PaymentData: ' . print_r($paymentData, true));
        error_log('TTV2 EMAILS - FormData: ' . print_r($formData, true));
        error_log('TTV2 EMAILS - FormData[customerEmail]: ' . ($formData['customerEmail'] ?? 'NO EXISTE'));
        
        // DEBUG: Forzar logging en archivo específico
        file_put_contents('/tmp/ttv2-debug.log', 
            "[" . date('Y-m-d H:i:s') . "] formDataJson: $formDataJson\n" .
            "[" . date('Y-m-d H:i:s') . "] formData decoded: " . print_r($formData, true) . "\n" .
            "[" . date('Y-m-d H:i:s') . "] customerEmail: " . ($formData['customerEmail'] ?? 'NO EXISTE') . "\n\n", 
            FILE_APPEND
        );
        
        if (empty($orderId)) {
            error_log('TTV2 EMAILS - OrderID vacío');
            wp_send_json_error('OrderID requerido');
            return;
        }
        
        // Enviar emails
        $emailResults = ttv2_send_confirmation_emails($orderId, $paymentData, $formData);
        
        error_log('TTV2 EMAILS - Resultados: ' . print_r($emailResults, true));
        
        // Responder éxito
        wp_send_json_success([
            'message' => 'Emails enviados correctamente',
            'client_email_sent' => $emailResults['client'],
            'admin_email_sent' => $emailResults['admin'],
            'tramite_id' => $emailResults['tramite_id']
        ]);
        
    } catch (Exception $e) {
        error_log('TTV2 EMAILS - Error: ' . $e->getMessage());
        wp_send_json_error('Error al enviar emails: ' . $e->getMessage());
    }
    
    wp_die();
}