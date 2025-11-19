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
        // Usar el OrderID recibido del frontend (ya padeado) o generar uno nuevo si no viene
        $order_id = !empty($_POST['orderId']) ? $_POST['orderId'] : str_pad(time(), 12, '0', STR_PAD_LEFT);
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
        
        .ttv2-toggle-document {
            width: 100%;
            padding: 12px;
            background: #f3f4f6;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            transition: all 0.2s;
            margin: 15px 0;
            color: #4b5563;
            font-size: 14px;
        }
        
        .ttv2-toggle-document:hover {
            background: #e5e7eb;
        }
        
        .ttv2-toggle-document.active i {
            transform: rotate(180deg);
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
                margin-top: 60px;
                padding: 0 15px;
            }
            
            .ttv2-toggle-document {
                margin: 10px 0;
                font-size: 13px;
                padding: 10px;
            }
            
            .ttv2-authorization-preview {
                font-size: 12px;
                max-height: 150px;
            }
            
            .ttv2-signature-canvas-container {
                flex: 1;
                padding: 15px;
                display: flex;
                flex-direction: column;
                justify-content: center;
                background: white;
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
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                padding: 15px;
                background: white;
                border-top: 1px solid #e5e7eb;
                box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
                border-radius: 0;
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
                <!-- PASO 0: Verificación de Elegibilidad -->
                <div class="ttv2-step active" data-step="0" id="ttv2-dgmm-warning-step">
                    <div class="ttv2-eligibility-page">
                        <div class="ttv2-eligibility-content">
                            <div class="ttv2-main-messages">
                                <div class="ttv2-requirement">
                                    <h3>Solo Titulaciones DGMM</h3>
                                    <p>Este servicio está disponible <strong>únicamente</strong> para titulaciones expedidas por la <strong>Dirección General de la Marina Mercante (DGMM)</strong> o entidades estatales dependientes del Ministerio de Transportes.</p>
                                </div>
                                
                                <div class="ttv2-restriction">
                                    <h3>Titulaciones Autonómicas No Válidas</h3>
                                    <p><strong>No podemos tramitar</strong> la renovación de titulaciones expedidas por administraciones autonómicas como Cataluña, País Vasco, Islas Baleares, Comunidad Valenciana, Canarias o cualquier otra comunidad autónoma.</p>
                                </div>
                            </div>
                            
                            <div class="ttv2-guidance">
                                <h3>¿Cómo verificar el organismo emisor?</h3>
                                <p>Revise el sello o logo en su titulación. Si aparece el escudo de una comunidad autónoma o referencias a organismos autonómicos, no podemos procesar su renovación.</p>
                            </div>
                            
                            <div class="ttv2-confirmation-section">
                                <label class="ttv2-confirmation-label">
                                    <input type="checkbox" id="ttv2-dgmm-confirm" name="dgmm_confirm" required>
                                    <span class="ttv2-confirmation-text">
                                        Confirmo que mi titulación náutica fue expedida por la Dirección General de la Marina Mercante (DGMM) y entiendo que las titulaciones autonómicas no pueden tramitarse a través de este servicio.
                                    </span>
                                </label>
                            </div>
                            
                            <div class="ttv2-navigation-buttons">
                                <button type="button" class="ttv2-btn-back" onclick="window.location.href='https://tramitfy.es'">
                                    Volver al Inicio
                                </button>
                                <button type="button" class="ttv2-btn-continue" id="ttv2-dgmm-continue" disabled>
                                    Continuar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- PASO 1: Selector de Tipo de Servicio -->
                <div class="ttv2-step" data-step="1">
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
            
            <!-- Documento preview (colapsable en móvil) -->
            <div class="ttv2-authorization-section">
                <button type="button" class="ttv2-toggle-document" onclick="toggleAuthDocument()">
                    <span>Ver documento de autorización</span>
                    <i class="fa-solid fa-chevron-down"></i>
                </button>
                <div id="ttv2-authorization-preview" class="ttv2-authorization-preview" style="display: none;"></div>
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
            // ============================================
            // DGMM WARNING PAGE HANDLING
            // ============================================
            const dgmmCheckbox = document.getElementById('ttv2-dgmm-confirm');
            const dgmmContinueBtn = document.getElementById('ttv2-dgmm-continue');
            
            // Handle DGMM checkbox
            if (dgmmCheckbox && dgmmContinueBtn) {
                dgmmCheckbox.addEventListener('change', function() {
                    dgmmContinueBtn.disabled = !this.checked;
                });
                
                // Handle continue button click
                dgmmContinueBtn.addEventListener('click', function() {
                    if (dgmmCheckbox.checked) {
                        // Mark as confirmed
                        sessionStorage.setItem('ttv2_dgmm_confirmed', 'true');
                        
                        // Hide warning step
                        document.getElementById('ttv2-dgmm-warning-step').classList.remove('active');
                        
                        // Show step 1 (service selection)
                        document.querySelector('.ttv2-step[data-step="1"]').classList.add('active');
                        
                        // Update progress bar to show step 1 as active
                        document.querySelectorAll('.ttv2-progress-step').forEach(step => {
                            step.classList.remove('active');
                        });
                        document.querySelector('.ttv2-progress-step[data-step="1"]').classList.add('active');
                    }
                });
            }
            
            // Check if already confirmed in this session
            const isDGMMConfirmed = sessionStorage.getItem('ttv2_dgmm_confirmed');
            if (isDGMMConfirmed === 'true') {
                // Hide warning step and show step 1 directly
                const warningStep = document.getElementById('ttv2-dgmm-warning-step');
                if (warningStep) {
                    warningStep.classList.remove('active');
                }
                document.querySelector('.ttv2-step[data-step="1"]').classList.add('active');
                document.querySelector('.ttv2-progress-step[data-step="1"]').classList.add('active');
            }
            
            // Event listener para el checkbox de términos
            const termsCheckbox = document.getElementById('ttv2-terms-accept-pago');
            const submitButton = document.getElementById('ttv2-submit-payment');
            
            if (termsCheckbox && submitButton) {
                termsCheckbox.addEventListener('change', function() {
                    submitButton.disabled = !this.checked;
                });
                
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

            // Event listeners para navegación
            document.querySelectorAll('.ttv2-nav-item').forEach(item => {
                item.addEventListener('click', function() {
                    const targetStep = parseInt(this.dataset.nav);
                    if (targetStep < ttv2CurrentStep || ttv2ValidateCurrentStep()) {
                        ttv2GoToStep(targetStep);
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
            
            // Función para toggle del documento
            window.toggleAuthDocument = function() {
                const preview = document.getElementById('ttv2-authorization-preview');
                const button = document.querySelector('.ttv2-toggle-document');
                
                if (preview.style.display === 'none') {
                    preview.style.display = 'block';
                    button.classList.add('active');
                } else {
                    preview.style.display = 'none';
                    button.classList.remove('active');
                }
            }
            
            // Inicializar SignaturePad cuando se abre el modal
            if (ttv2OpenSignatureBtn) {
                ttv2OpenSignatureBtn.addEventListener('click', function() {
                    ttv2GenerateAuthorizationDocument();
                    ttv2SignatureModal.style.display = 'flex';
                    
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
                        
                        // Guardar en el array de archivos para enviar con el formulario
                        ttv2Files['signature'] = ttv2SignatureData;
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
                // Sin validaciones - permitir continuar sin documentos
                // Los documentos ahora son opcionales para pasar a la página de pago
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

        // Función para convertir archivo a base64
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
                            const base64 = await ttv2FileToBase64(file);
                            // Usar el mismo formato que TBV2
                            filesArray.push({
                                name: file.name,
                                base64: base64,
                                size: file.size,
                                type: file.type,
                                category: inputId // Mantener la categoría
                            });
                            console.log(`TTV2: Archivo convertido: ${file.name}`);
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
            // Validar términos
            const termsCheckbox = document.getElementById('ttv2-terms-accept-pago');
            if (!termsCheckbox || !termsCheckbox.checked) {
                alert('Por favor, acepte los términos y condiciones.');
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
                
                // Generar OrderID aquí (igual que PHP) para que coincida con Redsys
                const timestamp = Math.floor(Date.now() / 1000); // Timestamp en segundos como PHP time()
                const generatedOrderId = timestamp.toString().padStart(12, '0');
                console.log('TTV2: OrderID generado para Redsys:', generatedOrderId);
                
                // Preparar datos completos para API temporal
                const captureData = {
                    orderId: generatedOrderId, // Usar el mismo OrderID que usará Redsys
                    customerData: {
                        name: ttv2FormData.customerName || '',
                        dni: ttv2FormData.customerDni || '',
                        email: ttv2FormData.customerEmail || '',
                        phone: ttv2FormData.customerPhone || '',
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
                    files: filesArray,  // Ahora es un array como TBV2
                    tramiteType: 'titulaciones-v2'
                };
                
                console.log('TTV2: Enviando al API temporal...');
                
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
        // Generar OrderID único
        $orderId = str_pad(time(), 12, '0', STR_PAD_LEFT);
        
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