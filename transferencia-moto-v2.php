<?php
/**
 * TRAMITFY - TRANSFERENCIA MOTOS DE AGUA V2 
 * 
 * ✅ COMPLETAMENTE INDEPENDIENTE - Sin interferencias con otros formularios
 * ✅ Estética idéntica a TBV2 pero temática motos
 * ✅ Sistema de pagos Redsys propio
 * ✅ Endpoints API separados
 * 
 * @version 1.0.0 - MOTO EDITION
 * @author Claude Code 
 * @created 2025-11-16
 */

// ============================================
// 🔒 PROTECCIÓN Y CONFIGURACIÓN INICIAL
// ============================================

if (!defined('ABSPATH')) {
    exit('Acceso directo no permitido.');
}

// ============================================
// 🚤 TMV2 - DETECCIÓN DE PÁGINA AUTORIZADA
// ============================================

/**
 * Detecta si estamos en una página autorizada para cargar TMV2
 * COMPLETAMENTE INDEPENDIENTE de otros formularios
 */
function tmv2_is_page_authorized() {
    global $post;
    
    // 🚨 PROTECCIÓN AJAX: Solo acciones TMV2
    if (defined('DOING_AJAX') && DOING_AJAX) {
        $action = $_POST['action'] ?? $_GET['action'] ?? '';
        
        // 🔒 Admin bypass
        if (is_admin()) {
            return true;
        }
        
        // Solo acciones TMV2 específicas
        $tmv2_ajax_actions = [
            'tmv2_create_redsys_payment',
            'tmv2_store_files', 
            'tmv2_send_confirmation_emails',
            'tmv2_process_callback'
        ];
        
        return in_array($action, $tmv2_ajax_actions);
    }
    
    // Admin autorizado
    if (!defined('DOING_AJAX') && is_admin()) return true;
    
    // Verificar por URL - específico para motos
    $request_uri = $_SERVER['REQUEST_URI'] ?? '';
    $authorized_pages = [
        'moto-v2',
        'transferencia-moto-v2',
        'transferencia-propiedad-moto-v2',
        'testingfy-moto'
    ];
    
    foreach ($authorized_pages as $page) {
        if (strpos($request_uri, $page) !== false) {
            return true;
        }
    }
    
    // Verificar por shortcode
    if (is_object($post)) {
        if (strpos($post->post_content, '[transferencia_moto_v2') !== false) {
            return true;
        }
    }
    
    return false;
}

// ============================================
// 🛡️ PROTECCIÓN: Solo cargar si está autorizado
// ============================================

if (!tmv2_is_page_authorized()) {
    return; // Salir silenciosamente sin cargar nada
}

// ============================================
// ⚙️ CONFIGURACIÓN TMV2 INDEPENDIENTE
// ============================================

// Modo de operación
if (!defined('TMV2_REDSYS_MODE')) define('TMV2_REDSYS_MODE', 'test'); // test o live

// Configuración comercio Redsys
if (!defined('TMV2_REDSYS_MERCHANT_CODE')) define('TMV2_REDSYS_MERCHANT_CODE', '363391103');
if (!defined('TMV2_REDSYS_TERMINAL')) define('TMV2_REDSYS_TERMINAL', '1');
if (!defined('TMV2_REDSYS_CURRENCY')) define('TMV2_REDSYS_CURRENCY', '978'); // EUR

// Claves de cifrado (sanitizadas para Git)
if (!defined('TMV2_REDSYS_SECRET_KEY')) define('TMV2_REDSYS_SECRET_KEY', 'TMV2_XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX');
if (!defined('TMV2_REDSYS_SIGNATURE_VERSION')) define('TMV2_REDSYS_SIGNATURE_VERSION', 'HMAC_SHA256_V1');

// URLs Redsys
if (!defined('TMV2_REDSYS_URL_TEST')) define('TMV2_REDSYS_URL_TEST', 'https://sis-t.redsys.es:25443/sis/realizarPago');
if (!defined('TMV2_REDSYS_URL_LIVE')) define('TMV2_REDSYS_URL_LIVE', 'https://sis.redsys.es/sis/realizarPago');

// URLs TMV2 específicas (COMPLETAMENTE SEPARADAS)
if (!defined('TMV2_WEBHOOK_URL')) define('TMV2_WEBHOOK_URL', 'https://tramitfy.org/api/herramientas/motos-v2/webhook');
if (!defined('TMV2_REDSYS_URL_OK')) define('TMV2_REDSYS_URL_OK', 'https://tramitfy.es/moto-pago-realizado-con-exito/');
if (!defined('TMV2_REDSYS_URL_KO')) define('TMV2_REDSYS_URL_KO', 'https://tramitfy.es/transferencia-moto-v2/');
if (!defined('TMV2_REDSYS_URL_NOTIFICATION')) define('TMV2_REDSYS_URL_NOTIFICATION', 'https://tramitfy.org/api/herramientas/motos-v2/callback');

// Variable global para URL Redsys
global $tmv2_redsys_url;
$tmv2_redsys_url = (TMV2_REDSYS_MODE === 'test') ? TMV2_REDSYS_URL_TEST : TMV2_REDSYS_URL_LIVE;

// ============================================
// 💳 SISTEMA DE PAGOS REDSYS TMV2
// ============================================

/**
 * Genera firma HMAC SHA256 para Redsys (TMV2)
 */
function tmv2_redsys_generate_signature($data) {
    $json_data = json_encode($data);
    $params_base64 = base64_encode($json_data);
    
    // Usar clave de cifrado TMV2
    $key = base64_decode(TMV2_REDSYS_SECRET_KEY);
    $iv = substr($data['Ds_Merchant_Order'], 0, 8);
    
    // Generar clave específica
    $cipher = "aes-128-cbc";
    $encrypted_key = openssl_encrypt($iv, $cipher, $key, OPENSSL_RAW_DATA, str_repeat("\0", 16));
    
    // Calcular HMAC
    $signature = hash_hmac('sha256', $params_base64, $encrypted_key, true);
    
    return base64_encode($signature);
}

/**
 * Crea formulario de pago Redsys para TMV2
 */
function tmv2_redsys_create_payment_form($order_data) {
    global $tmv2_redsys_url;
    
    // Parámetros del comercio TMV2
    $params = [
        'Ds_Merchant_MerchantCode' => TMV2_REDSYS_MERCHANT_CODE,
        'Ds_Merchant_Terminal' => TMV2_REDSYS_TERMINAL,
        'Ds_Merchant_Order' => $order_data['order_id'],
        'Ds_Merchant_Amount' => $order_data['amount_cents'],
        'Ds_Merchant_Currency' => TMV2_REDSYS_CURRENCY,
        'Ds_Merchant_TransactionType' => '0', // Autorización
        'Ds_Merchant_MerchantURL' => TMV2_REDSYS_URL_NOTIFICATION,
        'Ds_Merchant_UrlOK' => TMV2_REDSYS_URL_OK,
        'Ds_Merchant_UrlKO' => TMV2_REDSYS_URL_KO,
        'Ds_Merchant_MerchantName' => 'Tramitfy - Motos de Agua',
        'Ds_Merchant_ProductDescription' => 'Transferencia Moto de Agua',
        'Ds_Merchant_ConsumerLanguage' => '001' // Español
    ];
    
    // DEBUG
    error_log("=== TMV2 PAYMENT PARAMS ===");
    error_log("Order ID: " . $order_data['order_id']);
    error_log("Amount: " . $order_data['amount_cents']);
    error_log("============================");
    
    $signature = tmv2_redsys_generate_signature($params);
    $merchant_parameters = base64_encode(json_encode($params));
    
    return [
        'url' => $tmv2_redsys_url,
        'Ds_MerchantParameters' => $merchant_parameters,
        'Ds_SignatureVersion' => TMV2_REDSYS_SIGNATURE_VERSION,
        'Ds_Signature' => $signature
    ];
}

/**
 * Valida respuesta de Redsys para TMV2
 */
function tmv2_redsys_validate_response($merchant_params, $signature_received) {
    $params = json_decode(base64_decode($merchant_params), true);
    
    $signature_calculated = tmv2_redsys_generate_signature($params);
    
    return hash_equals($signature_calculated, $signature_received);
}

/**
 * Procesa callback de Redsys para TMV2
 */
function tmv2_redsys_process_callback() {
    $merchant_params = $_POST['Ds_MerchantParameters'] ?? '';
    $signature = $_POST['Ds_Signature'] ?? '';
    
    if (!tmv2_redsys_validate_response($merchant_params, $signature)) {
        error_log("TMV2: Firma inválida en callback");
        return false;
    }
    
    $params = json_decode(base64_decode($merchant_params), true);
    
    // Verificar transacción exitosa
    if ($params['Ds_Response'] === '0000') {
        // Procesar pago exitoso
        tmv2_trigger_webhook($params);
        return true;
    }
    
    return false;
}

/**
 * Dispara webhook TMV2 tras pago exitoso
 */
function tmv2_trigger_webhook($redsys_params) {
    // Recuperar datos del sessionStorage (como TBV2)
    $customer_data = get_transient('tmv2_customer_' . $redsys_params['Ds_Order']);
    $vehicle_data = get_transient('tmv2_vehicle_' . $redsys_params['Ds_Order']);
    $files_data = get_transient('tmv2_files_' . $redsys_params['Ds_Order']);
    
    if (!$customer_data || !$vehicle_data) {
        error_log("TMV2: Datos no encontrados para order " . $redsys_params['Ds_Order']);
        return;
    }
    
    // Preparar payload para webhook TMV2
    $webhook_data = [
        'tramiteType' => 'transferencia-moto',
        'paymentIntentId' => $redsys_params['Ds_Order'],
        'finalAmount' => number_format($redsys_params['Ds_Amount'] / 100, 2),
        'customerName' => $customer_data['buyerName'],
        'customerDni' => $customer_data['buyerDni'],
        'customerEmail' => $customer_data['buyerEmail'],
        'customerPhone' => $customer_data['buyerPhone'],
        'vehicleData' => $vehicle_data,
        'sellerData' => [
            'name' => $customer_data['sellerName'],
            'dni' => $customer_data['sellerDni']
        ],
        'attachments' => $files_data ?? [],
        'status' => 'pending',
        'redsysData' => $redsys_params
    ];
    
    // Enviar a webhook TMV2
    wp_remote_post(TMV2_WEBHOOK_URL, [
        'headers' => ['Content-Type' => 'application/json'],
        'body' => json_encode($webhook_data),
        'timeout' => 45
    ]);
    
    // Limpiar datos temporales
    delete_transient('tmv2_customer_' . $redsys_params['Ds_Order']);
    delete_transient('tmv2_vehicle_' . $redsys_params['Ds_Order']);
    delete_transient('tmv2_files_' . $redsys_params['Ds_Order']);
}

// ============================================
// 📨 ACCIONES AJAX TMV2
// ============================================

/**
 * AJAX: Crear sesión de pago TMV2
 */
function tmv2_ajax_create_payment() {
    // Verificar nonce
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'tmv2_nonce')) {
        wp_die('Acceso denegado');
    }
    
    // Generar OrderID único
    $order_id = substr(time() . rand(1000, 9999), -12);
    
    // Calcular precio (base + ITP estimado)
    $base_price = 89.00;
    $purchase_price = floatval($_POST['purchasePrice'] ?? 0);
    $itp_estimated = $purchase_price * 0.04; // 4% estimado
    $total_amount = $base_price + $itp_estimated;
    $amount_cents = round($total_amount * 100); // En céntimos
    
    // Guardar datos en transients temporales
    $customer_data = [
        'buyerName' => sanitize_text_field($_POST['buyerName'] ?? ''),
        'buyerDni' => sanitize_text_field($_POST['buyerDni'] ?? ''),
        'buyerEmail' => sanitize_email($_POST['buyerEmail'] ?? ''),
        'buyerPhone' => sanitize_text_field($_POST['buyerPhone'] ?? ''),
        'sellerName' => sanitize_text_field($_POST['sellerName'] ?? ''),
        'sellerDni' => sanitize_text_field($_POST['sellerDni'] ?? '')
    ];
    
    $vehicle_data = [
        'manufacturer' => sanitize_text_field($_POST['manufacturer'] ?? ''),
        'model' => sanitize_text_field($_POST['model'] ?? ''),
        'year' => sanitize_text_field($_POST['year'] ?? ''),
        'matricula' => sanitize_text_field($_POST['matricula'] ?? ''),
        'purchasePrice' => $purchase_price
    ];
    
    // Guardar con expiración de 2 horas
    set_transient('tmv2_customer_' . $order_id, $customer_data, 2 * HOUR_IN_SECONDS);
    set_transient('tmv2_vehicle_' . $order_id, $vehicle_data, 2 * HOUR_IN_SECONDS);
    
    // Crear formulario de pago
    $payment_form = tmv2_redsys_create_payment_form([
        'order_id' => $order_id,
        'amount_cents' => $amount_cents,
        'description' => 'Transferencia Moto ' . $vehicle_data['manufacturer'] . ' ' . $vehicle_data['model']
    ]);
    
    wp_send_json_success([
        'order_id' => $order_id,
        'payment_form' => $payment_form,
        'total_amount' => number_format($total_amount, 2)
    ]);
}

/**
 * AJAX: Procesar archivos TMV2
 */
function tmv2_ajax_store_files() {
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'tmv2_nonce')) {
        wp_die('Acceso denegado');
    }
    
    $order_id = sanitize_text_field($_POST['order_id'] ?? '');
    $files_data = [];
    
    // Procesar archivos subidos
    if (!empty($_FILES)) {
        foreach ($_FILES as $field_name => $files) {
            if (is_array($files['name'])) {
                // Múltiples archivos
                for ($i = 0; $i < count($files['name']); $i++) {
                    if ($files['error'][$i] === UPLOAD_ERR_OK) {
                        $file_data = [
                            'name' => $files['name'][$i],
                            'type' => $files['type'][$i],
                            'size' => $files['size'][$i],
                            'content' => base64_encode(file_get_contents($files['tmp_name'][$i]))
                        ];
                        $files_data[$field_name][] = $file_data;
                    }
                }
            }
        }
    }
    
    // Guardar archivos en transient
    set_transient('tmv2_files_' . $order_id, $files_data, 2 * HOUR_IN_SECONDS);
    
    wp_send_json_success(['message' => 'Archivos guardados']);
}

// Registrar acciones AJAX TMV2
if (function_exists('add_action')) {
    add_action('wp_ajax_tmv2_create_redsys_payment', 'tmv2_ajax_create_payment');
    add_action('wp_ajax_nopriv_tmv2_create_redsys_payment', 'tmv2_ajax_create_payment');
    
    add_action('wp_ajax_tmv2_store_files', 'tmv2_ajax_store_files');
    add_action('wp_ajax_nopriv_tmv2_store_files', 'tmv2_ajax_store_files');
}

// ============================================
// 🎨 FUNCIÓN DE ESTILOS TMV2
// ============================================

function tmv2_render_styles() {
    ?>
    <style>
    /* ============================================
    TMV2 - ESTILOS IDÉNTICOS A TBV2 (TEMÁTICA MOTOS)
    ============================================ */
    
    :root {
        --tmv2-primary: 1, 109, 134; /* #016d86 - Azul tramitfy */
        --tmv2-primary-dark: 1, 82, 106; /* #01546a */
        --tmv2-moto-accent: 255, 140, 0; /* #ff8c00 - Naranja motos */
        --tmv2-spacing-xs: 4px;
        --tmv2-spacing-sm: 8px;
        --tmv2-spacing-md: 16px;
        --tmv2-spacing-lg: 24px;
        --tmv2-spacing-xl: 32px;
        --tmv2-spacing-2xl: 48px;
        --tmv2-radius-sm: 6px;
        --tmv2-radius-md: 8px;
        --tmv2-radius-lg: 12px;
    }

    /* Container principal TMV2 */
    .tmv2-layout-wrapper {
        max-width: 1400px;
        margin: 0 auto;
        padding: 20px;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }

    .tmv2-two-column {
        display: grid;
        grid-template-columns: 300px 1fr;
        gap: 30px;
        min-height: 80vh;
        align-items: start;
    }

    /* Sidebar TMV2 */
    .tmv2-sidebar {
        background: linear-gradient(135deg, 
            rgb(var(--tmv2-primary)) 0%, 
            rgb(var(--tmv2-primary-dark)) 100%);
        border-radius: var(--tmv2-radius-lg);
        padding: var(--tmv2-spacing-lg);
        color: white;
        position: sticky;
        top: 20px;
        min-height: 400px;
    }

    .tmv2-sidebar h3 {
        color: white;
        margin: 0 0 var(--tmv2-spacing-md) 0;
        font-size: 18px;
        font-weight: 600;
    }

    /* Formulario principal TMV2 */
    .tmv2-main-form {
        background: white;
        border-radius: var(--tmv2-radius-lg);
        box-shadow: 0 4px 6px rgba(0,0,0,0.07);
        overflow: hidden;
    }

    /* Navegación superior TMV2 */
    .tmv2-nav-tabs-container {
        display: flex;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
    }

    .tmv2-nav-tab {
        flex: 1;
        padding: 15px 20px;
        text-align: center;
        cursor: pointer;
        border-bottom: 3px solid transparent;
        transition: all 0.3s ease;
        font-weight: 500;
        color: #64748b;
    }

    .tmv2-nav-tab.active {
        color: rgb(var(--tmv2-primary));
        border-bottom-color: rgb(var(--tmv2-primary));
        background: white;
    }

    .tmv2-nav-tab:hover:not(.active) {
        background: #f1f5f9;
        color: rgb(var(--tmv2-primary-dark));
    }

    /* Contenido de pasos TMV2 */
    .tmv2-form-step {
        padding: var(--tmv2-spacing-xl);
        display: none;
    }

    .tmv2-form-step.active {
        display: block;
    }

    .tmv2-form-step h2 {
        color: rgb(var(--tmv2-primary));
        margin: 0 0 var(--tmv2-spacing-lg) 0;
        font-size: 24px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: var(--tmv2-spacing-sm);
    }

    /* Campos de formulario TMV2 */
    .tmv2-form-group {
        margin-bottom: var(--tmv2-spacing-lg);
    }

    .tmv2-form-group label {
        display: block;
        margin-bottom: var(--tmv2-spacing-sm);
        font-weight: 500;
        color: #374151;
    }

    .tmv2-form-group input,
    .tmv2-form-group select,
    .tmv2-form-group textarea {
        width: 100%;
        padding: 12px 16px;
        border: 1px solid #d1d5db;
        border-radius: var(--tmv2-radius-sm);
        font-size: 16px;
        transition: border-color 0.3s ease, box-shadow 0.3s ease;
    }

    .tmv2-form-group input:focus,
    .tmv2-form-group select:focus,
    .tmv2-form-group textarea:focus {
        outline: none;
        border-color: rgb(var(--tmv2-primary));
        box-shadow: 0 0 0 3px rgba(var(--tmv2-primary), 0.1);
    }

    /* Botones TMV2 */
    .tmv2-btn {
        padding: 12px 24px;
        border-radius: var(--tmv2-radius-sm);
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s ease;
        border: none;
        font-size: 16px;
    }

    .tmv2-btn-primary {
        background: rgb(var(--tmv2-primary));
        color: white;
    }

    .tmv2-btn-primary:hover {
        background: rgb(var(--tmv2-primary-dark));
    }

    .tmv2-btn-secondary {
        background: #6b7280;
        color: white;
    }

    .tmv2-btn-secondary:hover {
        background: #4b5563;
    }

    /* Responsive TMV2 */
    @media (max-width: 768px) {
        .tmv2-two-column {
            grid-template-columns: 1fr;
            gap: 20px;
        }
        
        .tmv2-sidebar {
            position: relative;
            top: 0;
        }

        .tmv2-nav-tabs-container {
            flex-wrap: wrap;
        }

        .tmv2-nav-tab {
            flex: 1 1 50%;
            min-width: 120px;
        }
    }
    </style>
    <?php
}

// ============================================
// 🏗️ FUNCIÓN PRINCIPAL DE RENDERIZADO TMV2
// ============================================

function tmv2_render_form() {
    // Protección adicional
    if (!tmv2_is_page_authorized()) {
        return '<!-- TMV2: No autorizado -->';
    }

    ob_start();
    ?>
    
    <!-- Estilos TMV2 -->
    <?php tmv2_render_styles(); ?>

    <!-- FORMULARIO TMV2 PRINCIPAL -->
    <form id="tmv2-transferencia-form" class="tmv2-form-container">
        
        <!-- LAYOUT WRAPPER TMV2 -->
        <div class="tmv2-layout-wrapper">
            <div class="tmv2-two-column">

                <!-- SIDEBAR IZQUIERDO TMV2 -->
                <aside class="tmv2-sidebar">
                    <div id="tmv2-sidebar-dynamic-content">
                        <!-- Contenido dinámico por paso -->
                    </div>

                    <!-- Contenido universal del sidebar -->
                    <div class="tmv2-sidebar-content">
                        <h3>🚤 Transferencia Motos de Agua</h3>
                        <p>Gestiona la transferencia de propiedad de tu moto de agua de forma rápida y segura.</p>
                        
                        <!-- Widget Trustpilot -->
                        <div style="margin-top: 20px;">
                            <script defer async src='https://cdn.trustindex.io/loader.js?f4fbfd341d12439e0c86fae7fc2'></script>
                        </div>
                    </div>
                </aside>

                <!-- PANEL DERECHO - FORMULARIO -->
                <div class="tmv2-main-form">

                    <!-- Navegación superior -->
                    <div class="tmv2-nav-tabs-container">
                        <div class="tmv2-nav-tab active" data-step="1">
                            <span>🚤 Datos Moto</span>
                        </div>
                        <div class="tmv2-nav-tab" data-step="2">
                            <span>👥 Propietarios</span>
                        </div>
                        <div class="tmv2-nav-tab" data-step="3">
                            <span>📄 Documentos</span>
                        </div>
                        <div class="tmv2-nav-tab" data-step="4">
                            <span>💳 Pago</span>
                        </div>
                    </div>

                    <!-- PASO 1: DATOS DE LA MOTO -->
                    <div class="tmv2-form-step active" data-step="1">
                        <h2>🚤 Datos de la Moto de Agua</h2>
                        
                        <div class="tmv2-form-group">
                            <label for="tmv2-manufacturer">Fabricante *</label>
                            <select id="tmv2-manufacturer" name="manufacturer" required>
                                <option value="">Selecciona fabricante</option>
                                <option value="Sea-Doo">Sea-Doo</option>
                                <option value="Yamaha">Yamaha</option>
                                <option value="Kawasaki">Kawasaki</option>
                                <option value="Honda">Honda</option>
                                <option value="Polaris">Polaris</option>
                                <option value="Otro">Otro</option>
                            </select>
                        </div>

                        <div class="tmv2-form-group">
                            <label for="tmv2-model">Modelo *</label>
                            <input type="text" id="tmv2-model" name="model" placeholder="ej. GTI 130" required>
                        </div>

                        <div class="tmv2-form-group">
                            <label for="tmv2-year">Año de fabricación *</label>
                            <select id="tmv2-year" name="year" required>
                                <option value="">Selecciona año</option>
                                <?php
                                for ($year = date('Y'); $year >= 1990; $year--) {
                                    echo "<option value='$year'>$year</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div class="tmv2-form-group">
                            <label for="tmv2-matricula">Matrícula *</label>
                            <input type="text" id="tmv2-matricula" name="matricula" placeholder="ej. 1234ABC" required>
                        </div>

                        <div class="tmv2-form-group">
                            <label for="tmv2-purchase-price">Precio de compraventa (€) *</label>
                            <input type="number" id="tmv2-purchase-price" name="purchasePrice" placeholder="15000" step="0.01" required>
                        </div>

                        <div style="text-align: right; margin-top: 30px;">
                            <button type="button" class="tmv2-btn tmv2-btn-primary" onclick="tmv2_nextStep(2)">
                                Siguiente: Propietarios →
                            </button>
                        </div>
                    </div>

                    <!-- PASO 2: DATOS DE PROPIETARIOS -->
                    <div class="tmv2-form-step" data-step="2">
                        <h2>👥 Datos de Propietarios</h2>
                        
                        <h3 style="color: #059669; margin: 30px 0 20px 0;">Comprador (Nuevo propietario)</h3>
                        
                        <div class="tmv2-form-group">
                            <label for="tmv2-buyer-name">Nombre completo *</label>
                            <input type="text" id="tmv2-buyer-name" name="buyerName" required>
                        </div>

                        <div class="tmv2-form-group">
                            <label for="tmv2-buyer-dni">DNI/NIE *</label>
                            <input type="text" id="tmv2-buyer-dni" name="buyerDni" placeholder="12345678A" required>
                        </div>

                        <div class="tmv2-form-group">
                            <label for="tmv2-buyer-email">Email *</label>
                            <input type="email" id="tmv2-buyer-email" name="buyerEmail" required>
                        </div>

                        <div class="tmv2-form-group">
                            <label for="tmv2-buyer-phone">Teléfono *</label>
                            <input type="tel" id="tmv2-buyer-phone" name="buyerPhone" placeholder="600123456" required>
                        </div>

                        <h3 style="color: #dc2626; margin: 30px 0 20px 0;">Vendedor (Propietario actual)</h3>
                        
                        <div class="tmv2-form-group">
                            <label for="tmv2-seller-name">Nombre completo *</label>
                            <input type="text" id="tmv2-seller-name" name="sellerName" required>
                        </div>

                        <div class="tmv2-form-group">
                            <label for="tmv2-seller-dni">DNI/NIE *</label>
                            <input type="text" id="tmv2-seller-dni" name="sellerDni" placeholder="87654321B" required>
                        </div>

                        <div style="display: flex; gap: 15px; margin-top: 30px;">
                            <button type="button" class="tmv2-btn tmv2-btn-secondary" onclick="tmv2_prevStep(1)">
                                ← Anterior
                            </button>
                            <button type="button" class="tmv2-btn tmv2-btn-primary" onclick="tmv2_nextStep(3)">
                                Siguiente: Documentos →
                            </button>
                        </div>
                    </div>

                    <!-- PASO 3: DOCUMENTOS -->
                    <div class="tmv2-form-step" data-step="3">
                        <h2>📄 Documentos Requeridos</h2>
                        
                        <div class="tmv2-form-group">
                            <label for="tmv2-dni-comprador">DNI/NIE del Comprador (ambas caras) *</label>
                            <input type="file" id="tmv2-dni-comprador" name="dniComprador[]" multiple accept=".pdf,.jpg,.jpeg,.png" required>
                            <small style="color: #6b7280;">PDF o imágenes. Máximo 10MB por archivo.</small>
                        </div>

                        <div class="tmv2-form-group">
                            <label for="tmv2-dni-vendedor">DNI/NIE del Vendedor (ambas caras) *</label>
                            <input type="file" id="tmv2-dni-vendedor" name="dniVendedor[]" multiple accept=".pdf,.jpg,.jpeg,.png" required>
                            <small style="color: #6b7280;">PDF o imágenes. Máximo 10MB por archivo.</small>
                        </div>

                        <div class="tmv2-form-group">
                            <label for="tmv2-permiso-circulacion">Permiso de Circulación *</label>
                            <input type="file" id="tmv2-permiso-circulacion" name="permisoCirculacion[]" multiple accept=".pdf,.jpg,.jpeg,.png" required>
                            <small style="color: #6b7280;">Documento original de la moto de agua.</small>
                        </div>

                        <div class="tmv2-form-group">
                            <label for="tmv2-contrato">Contrato de Compraventa</label>
                            <input type="file" id="tmv2-contrato" name="contratoCompraventa[]" multiple accept=".pdf,.jpg,.jpeg,.png">
                            <small style="color: #6b7280;">Opcional. Si no lo tienes, nosotros te ayudamos a generarlo.</small>
                        </div>

                        <div style="display: flex; gap: 15px; margin-top: 30px;">
                            <button type="button" class="tmv2-btn tmv2-btn-secondary" onclick="tmv2_prevStep(2)">
                                ← Anterior
                            </button>
                            <button type="button" class="tmv2-btn tmv2-btn-primary" onclick="tmv2_nextStep(4)">
                                Siguiente: Pago →
                            </button>
                        </div>
                    </div>

                    <!-- PASO 4: PAGO -->
                    <div class="tmv2-form-step" data-step="4">
                        <h2>💳 Resumen y Pago</h2>
                        
                        <div style="background: #f8fafc; padding: 20px; border-radius: 8px; margin: 20px 0;">
                            <h3 style="margin: 0 0 15px 0; color: rgb(var(--tmv2-primary));">Resumen del Trámite</h3>
                            <div id="tmv2-summary">
                                <!-- Se llenará dinámicamente -->
                            </div>
                        </div>

                        <div style="background: #fef3c7; padding: 15px; border-radius: 8px; margin: 20px 0;">
                            <p style="margin: 0; color: #92400e;">
                                <strong>⚠️ Importante:</strong> El pago se realizará mediante TPV seguro Redsys. 
                                Todos los datos están protegidos con cifrado SSL.
                            </p>
                        </div>

                        <div style="display: flex; gap: 15px; margin-top: 30px;">
                            <button type="button" class="tmv2-btn tmv2-btn-secondary" onclick="tmv2_prevStep(3)">
                                ← Anterior
                            </button>
                            <button type="button" class="tmv2-btn tmv2-btn-primary" onclick="tmv2_processPayment()" style="background: #059669;">
                                💳 Pagar y Procesar Trámite
                            </button>
                        </div>
                    </div>

                </div> <!-- .tmv2-main-form -->

            </div> <!-- .tmv2-two-column -->
        </div> <!-- .tmv2-layout-wrapper -->

    </form>

    <!-- JAVASCRIPT TMV2 -->
    <script>
    // ============================================
    // TMV2 - SISTEMA JAVASCRIPT INDEPENDIENTE
    // ============================================

    const TMV2_System = {
        currentStep: 1,
        formData: {},

        init() {
            console.log('TMV2 System initialized');
            this.updateSummary();
        },

        updateSummary() {
            // Actualizar resumen dinámicamente
            const summaryEl = document.getElementById('tmv2-summary');
            if (summaryEl) {
                summaryEl.innerHTML = `
                    <div style="display: flex; justify-content: between; margin-bottom: 10px;">
                        <span>Transferencia Moto de Agua:</span>
                        <strong>89.00€</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                        <span>ITP (calculado automáticamente):</span>
                        <strong>Variable</strong>
                    </div>
                    <hr>
                    <div style="display: flex; justify-content: space-between; font-size: 18px; font-weight: bold; color: rgb(var(--tmv2-primary));">
                        <span>Total estimado:</span>
                        <span>89.00€ + ITP</span>
                    </div>
                `;
            }
        }
    };

    // Funciones de navegación TMV2
    function tmv2_nextStep(step) {
        // Validar paso actual
        if (!tmv2_validateStep(TMV2_System.currentStep)) {
            return;
        }

        // Cambiar a siguiente paso
        document.querySelectorAll('.tmv2-form-step').forEach(el => el.classList.remove('active'));
        document.querySelectorAll('.tmv2-nav-tab').forEach(el => el.classList.remove('active'));

        document.querySelector(`.tmv2-form-step[data-step="${step}"]`).classList.add('active');
        document.querySelector(`.tmv2-nav-tab[data-step="${step}"]`).classList.add('active');

        TMV2_System.currentStep = step;
        TMV2_System.updateSummary();

        // Scroll to top
        document.querySelector('.tmv2-main-form').scrollIntoView({ behavior: 'smooth' });
    }

    function tmv2_prevStep(step) {
        tmv2_nextStep(step);
    }

    function tmv2_validateStep(step) {
        const stepEl = document.querySelector(`.tmv2-form-step[data-step="${step}"]`);
        const requiredInputs = stepEl.querySelectorAll('input[required], select[required]');
        
        for (let input of requiredInputs) {
            if (!input.value.trim()) {
                alert(`Por favor, completa el campo: ${input.previousElementSibling.textContent}`);
                input.focus();
                return false;
            }
        }
        
        return true;
    }

    function tmv2_processPayment() {
        if (!tmv2_validateStep(4)) return;

        const submitBtn = document.querySelector('button[onclick="tmv2_processPayment()"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '⏳ Procesando...';
        }

        // Recopilar todos los datos del formulario
        const formData = tmv2_collectFormData();
        
        // Subir archivos primero
        tmv2_uploadFiles(formData)
            .then(() => {
                // Crear sesión de pago
                return tmv2_createPaymentSession(formData);
            })
            .then(response => {
                // Redireccionar a Redsys
                tmv2_redirectToRedsys(response.data.payment_form);
            })
            .catch(error => {
                console.error('Error procesando pago:', error);
                alert('Error procesando el pago. Por favor, inténtalo de nuevo.');
                
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '💳 Pagar y Procesar Trámite';
                }
            });
    }

    function tmv2_collectFormData() {
        return {
            // Datos de la moto
            manufacturer: document.getElementById('tmv2-manufacturer').value,
            model: document.getElementById('tmv2-model').value,
            year: document.getElementById('tmv2-year').value,
            matricula: document.getElementById('tmv2-matricula').value,
            purchasePrice: document.getElementById('tmv2-purchase-price').value,
            
            // Datos del comprador
            buyerName: document.getElementById('tmv2-buyer-name').value,
            buyerDni: document.getElementById('tmv2-buyer-dni').value,
            buyerEmail: document.getElementById('tmv2-buyer-email').value,
            buyerPhone: document.getElementById('tmv2-buyer-phone').value,
            
            // Datos del vendedor
            sellerName: document.getElementById('tmv2-seller-name').value,
            sellerDni: document.getElementById('tmv2-seller-dni').value
        };
    }

    function tmv2_uploadFiles(formData) {
        return new Promise((resolve, reject) => {
            const uploadFormData = new FormData();
            uploadFormData.append('action', 'tmv2_store_files');
            uploadFormData.append('nonce', '<?php echo wp_create_nonce("tmv2_nonce"); ?>');
            uploadFormData.append('order_id', 'temp_' + Date.now());
            
            // Agregar archivos
            ['tmv2-dni-comprador', 'tmv2-dni-vendedor', 'tmv2-permiso-circulacion', 'tmv2-contrato'].forEach(fieldId => {
                const fileInput = document.getElementById(fieldId);
                if (fileInput && fileInput.files) {
                    Array.from(fileInput.files).forEach(file => {
                        uploadFormData.append(fieldId + '[]', file);
                    });
                }
            });
            
            fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                method: 'POST',
                body: uploadFormData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    resolve(data);
                } else {
                    reject(new Error('Error subiendo archivos'));
                }
            })
            .catch(reject);
        });
    }

    function tmv2_createPaymentSession(formData) {
        return new Promise((resolve, reject) => {
            const paymentData = new FormData();
            paymentData.append('action', 'tmv2_create_redsys_payment');
            paymentData.append('nonce', '<?php echo wp_create_nonce("tmv2_nonce"); ?>');
            
            // Agregar todos los datos del formulario
            Object.keys(formData).forEach(key => {
                paymentData.append(key, formData[key]);
            });
            
            fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                method: 'POST',
                body: paymentData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    resolve(data);
                } else {
                    reject(new Error('Error creando sesión de pago'));
                }
            })
            .catch(reject);
        });
    }

    function tmv2_redirectToRedsys(paymentForm) {
        // Crear formulario dinámico para redirección
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = paymentForm.url;
        
        // Agregar campos ocultos
        Object.keys(paymentForm).forEach(key => {
            if (key !== 'url') {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = paymentForm[key];
                form.appendChild(input);
            }
        });
        
        // Agregar al DOM y enviar
        document.body.appendChild(form);
        form.submit();
    }

    // Inicializar cuando el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => TMV2_System.init());
    } else {
        TMV2_System.init();
    }
    </script>

    <?php
    return ob_get_clean();
}

// ============================================
// 📝 REGISTRO DE SHORTCODE TMV2
// ============================================

// Registrar shortcode solo si las funciones de WordPress están disponibles
if (function_exists('add_shortcode') && !shortcode_exists('transferencia_moto_v2')) {
    add_shortcode('transferencia_moto_v2', 'tmv2_render_form');
    add_shortcode('transferencia_moto_v2_form', 'tmv2_render_form');
}

// ============================================
// 🔚 FIN TMV2 - SISTEMA INDEPENDIENTE
// ============================================