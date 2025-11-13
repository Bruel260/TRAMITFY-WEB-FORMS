<?php
/**
 * TRAMITFY - TRANSFERENCIA EMBARCACIONES V2 CON REDSYS
 * 
 * ✅ VERSIÓN CORREGIDA - WORDPRESS NATIVO
 * ✅ Sin acceso directo fuera de WordPress
 * ✅ Variables locales en lugar de defines globales  
 * ✅ URLs de callback WordPress nativas
 * ✅ Integración perfecta con React webhook
 * ✅ Sistema de pago TPV Redsys funcional
 * 
 * @version 2.3.0 - WORDPRESS NATIVE FINAL
 * @author Claude Code  
 * @created 2025-11-13
 * @updated 2025-11-13 - Versión final corregida
 */

// ✅ SOLO PERMITIR ACCESO VÍA WORDPRESS
if (!defined('ABSPATH')) {
    exit('Acceso directo no permitido.');
}

// =====================================================
// CONFIGURACIÓN Y FUNCIONES BASE
// =====================================================

/**
 * ✅ Configuración del formulario TBV2
 * Variables locales en lugar de defines globales
 */
function tbv2_get_config() {
    return [
        'redsys_mode' => 'test', // test o live
        'merchant_code' => '363391103',
        'terminal' => '1',
        'currency' => '978', // EUR
        'secret_key' => 'sq7HjrUOBfKmC576ILgskD5srU870gJ7',
        'signature_version' => 'HMAC_SHA256_V1',
        'url_test' => 'https://sis-t.redsys.es:25443/sis/realizarPago',
        'url_live' => 'https://sis.redsys.es/sis/realizarPago',
        'webhook_url' => 'https://tramitfy.org/api/herramientas/barcos/webhook'
    ];
}

/**
 * ✅ Sistema de logging simplificado
 */
if (!function_exists('tbv2_log')) {
    function tbv2_log($message, $level = 'INFO') {
        error_log("TBV2_[$level]: $message");
    }
}

/**
 * ✅ URLs DE CALLBACK WORDPRESS NATIVAS
 */
function tbv2_get_callback_url($type, $orderId = null) {
    $base_url = home_url('/');
    
    switch ($type) {
        case 'ok':
            return add_query_arg([
                'tbv2_callback' => 'success',
                'order_id' => $orderId
            ], $base_url);
        case 'ko':
            return add_query_arg([
                'tbv2_callback' => 'error', 
                'order_id' => $orderId
            ], $base_url);
        case 'notification':
            return add_query_arg([
                'tbv2_callback' => 'notification'
            ], $base_url);
        default:
            return $base_url;
    }
}

/**
 * ✅ Obtener URL de TPV según entorno
 */
function tbv2_get_redsys_url() {
    $config = tbv2_get_config();
    return ($config['redsys_mode'] === 'test') ? $config['url_test'] : $config['url_live'];
}

/**
 * ✅ Cargar datos CSV de embarcaciones
 */
function tbv2_cargar_datos_csv() {
    $ruta_csv = get_template_directory() . '/BARCO.csv';
    $data = [];
    
    if (!file_exists($ruta_csv)) {
        tbv2_log('CSV de embarcaciones no encontrado: ' . $ruta_csv, 'WARNING');
        return $data;
    }
    
    if (($handle = fopen($ruta_csv, 'r')) !== FALSE) {
        while (($row = fgetcsv($handle)) !== FALSE) {
            if (isset($row[0]) && isset($row[1])) {
                $data[] = [
                    'manufacturer' => trim($row[0]),
                    'model' => trim($row[1]),
                    'engine_power' => isset($row[2]) ? trim($row[2]) : '',
                    'year' => isset($row[3]) ? trim($row[3]) : ''
                ];
            }
        }
        fclose($handle);
    }
    
    return $data;
}

// =====================================================
// FUNCIONES REDSYS
// =====================================================

/**
 * ✅ GENERACIÓN DE FIRMA REDSYS
 */
function tbv2_redsys_generate_signature($data) {
    $config = tbv2_get_config();
    
    // Decodificar clave en Base64
    $password_decoded = base64_decode($config['secret_key']);
    
    // Obtener order ID para cifrado
    $order_id = $data['Ds_Merchant_Order'];
    
    // Crear clave de cifrado usando Order ID
    $encryption_key = substr(hash_hmac('sha256', $order_id, $password_decoded, true), 0, 24);
    
    // Crear string a firmar
    $merchant_parameters_b64 = base64_encode(json_encode($data));
    $string_to_sign = $merchant_parameters_b64;
    
    // Generar firma HMAC
    $signature = hash_hmac('sha256', $string_to_sign, $encryption_key, true);
    $signature_encoded = base64_encode($signature);
    
    return [
        'Ds_MerchantParameters' => $merchant_parameters_b64,
        'Ds_Signature' => $signature_encoded,
        'Ds_SignatureVersion' => $config['signature_version']
    ];
}

/**
 * ✅ CREAR FORMULARIO DE PAGO REDSYS
 */
function tbv2_redsys_create_payment_form($order_data) {
    $config = tbv2_get_config();
    
    // Parámetros básicos para Redsys
    $params = [
        'Ds_Merchant_Amount' => $order_data['amount_cents'],
        'Ds_Merchant_Order' => $order_data['order_id'],
        'Ds_Merchant_MerchantCode' => $config['merchant_code'],
        'Ds_Merchant_Currency' => $config['currency'],
        'Ds_Merchant_TransactionType' => '0', // Autorización
        'Ds_Merchant_Terminal' => $config['terminal'],
        'Ds_Merchant_MerchantURL' => tbv2_get_callback_url('notification'),
        'Ds_Merchant_UrlOK' => tbv2_get_callback_url('ok', $order_data['order_id']),
        'Ds_Merchant_UrlKO' => tbv2_get_callback_url('ko', $order_data['order_id']),
        'Ds_Merchant_MerchantName' => 'Tramitfy',
        'Ds_Merchant_ProductDescription' => $order_data['description'],
        'Ds_Merchant_ConsumerLanguage' => '001' // Español
    ];
    
    // Generar firma
    $signature_data = tbv2_redsys_generate_signature($params);
    
    return [
        'url' => tbv2_get_redsys_url(),
        'params' => $signature_data
    ];
}

/**
 * ✅ VALIDAR RESPUESTA DE REDSYS
 */
function tbv2_redsys_validate_response($merchant_params, $signature_received) {
    $config = tbv2_get_config();
    $params = json_decode(base64_decode($merchant_params), true);
    
    if (!$params || !isset($params['Ds_Order'])) {
        return false;
    }
    
    // Recrear firma con los mismos parámetros
    $signature_calculated = tbv2_redsys_generate_signature($params)['Ds_Signature'];
    
    return hash_equals($signature_calculated, $signature_received);
}

/**
 * ✅ WEBHOOK A TRAMITFY API  
 * Mantiene compatibilidad 100% con React
 */
function tbv2_trigger_webhook($redsys_params) {
    $config = tbv2_get_config();
    $order_id = $redsys_params['Ds_Order'];
    
    // Recuperar datos almacenados del formulario
    $stored_data = get_transient('tbv2_form_data_' . $order_id);
    $stored_files = get_transient('tbv2_files_' . $order_id);
    
    if ($stored_data) {
        // Preparar datos para el webhook (formato idéntico al original)
        $webhook_data = [
            'tramiteType' => 'transferencia-barcos',
            'customerName' => $stored_data['customer_name'],
            'customerEmail' => $stored_data['customer_email'],
            'customerPhone' => $stored_data['customer_phone'],
            'customerDni' => $stored_data['customer_dni'],
            'companyName' => $stored_data['company_name'] ?? '',
            'vehicleType' => 'Barco',
            'manufacturer' => 'Embarcación',
            'model' => $stored_data['tipo_embarcacion'],
            'year' => $stored_data['year_fabrication'],
            'registration' => $stored_data['matricula'],
            'finalAmount' => floatval($redsys_params['Ds_Amount']) / 100,
            'itpAmount' => floatval($stored_data['itpAmount']),
            'tasas' => 52.60,
            'paymentMethod' => 'redsys_tpv',
            'paymentIntentId' => $order_id,
            'signature' => $stored_data['signature'] ?? '',
            'attachments' => $stored_files ?? []
        ];
        
        // Enviar al webhook
        $response = wp_remote_post($config['webhook_url'], [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode($webhook_data),
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            tbv2_log('Error enviando webhook: ' . $response->get_error_message(), 'ERROR');
            return false;
        }
        
        tbv2_log('Webhook enviado exitosamente a Tramitfy', 'SUCCESS');
        return true;
    }
    
    tbv2_log('No se encontraron datos del formulario para order: ' . $order_id, 'WARNING');
    return false;
}

// =====================================================
// CALLBACK HANDLERS WORDPRESS NATIVOS
// =====================================================

/**
 * ✅ HANDLER PRINCIPAL DE CALLBACKS
 */
function tbv2_handle_wordpress_callback() {
    if (!isset($_GET['tbv2_callback'])) {
        return;
    }
    
    $callback_type = sanitize_text_field($_GET['tbv2_callback']);
    
    switch ($callback_type) {
        case 'success':
            tbv2_handle_success_callback();
            break;
            
        case 'error':
            tbv2_handle_error_callback();
            break;
            
        case 'notification':
            tbv2_handle_notification_callback();
            break;
    }
}

function tbv2_handle_success_callback() {
    $order_id = sanitize_text_field($_GET['order_id'] ?? '');
    
    if ($order_id) {
        // Procesar pago exitoso
        $stored_data = get_transient('tbv2_form_data_' . $order_id);
        if ($stored_data) {
            // Trigger webhook
            tbv2_trigger_webhook(['Ds_Order' => $order_id, 'Ds_Amount' => $stored_data['finalAmount'] * 100]);
        }
    }
    
    // Redirigir a página de confirmación
    wp_redirect(home_url('/confirmacion-pago/?success=true&order=' . $order_id));
    exit;
}

function tbv2_handle_error_callback() {
    $order_id = sanitize_text_field($_GET['order_id'] ?? '');
    
    tbv2_log("Pago fallido para order: $order_id", 'WARNING');
    
    wp_redirect(home_url('/error-pago/?error=payment_failed&order=' . $order_id));
    exit;
}

function tbv2_handle_notification_callback() {
    // Callback server-to-server de Redsys
    if (isset($_POST['Ds_MerchantParameters'], $_POST['Ds_Signature'])) {
        $merchant_params = $_POST['Ds_MerchantParameters'];
        $signature = $_POST['Ds_Signature'];
        
        if (tbv2_redsys_validate_response($merchant_params, $signature)) {
            $params = json_decode(base64_decode($merchant_params), true);
            
            if ($params['Ds_Response'] <= 99) {
                // Pago exitoso
                tbv2_trigger_webhook($params);
                echo '[OK]';
            } else {
                echo '[KO]';
            }
        } else {
            echo '[KO]';
        }
    }
    exit;
}

// =====================================================
// AJAX HANDLERS SEGUROS
// =====================================================

/**
 * ✅ AJAX HANDLER: Crear pago Redsys
 */
function tbv2_handle_create_redsys_payment() {
    if (!wp_verify_nonce($_POST['nonce'], 'tbv2_nonce')) {
        wp_send_json_error('Error de seguridad');
        return;
    }
    
    try {
        $form_data_json = sanitize_text_field($_POST['formData']);
        $form_data = json_decode($form_data_json, true);
        
        if (!$form_data) {
            wp_send_json_error('Datos del formulario inválidos');
            return;
        }
        
        // Generar Order ID único
        $order_id = 'TBV2' . date('ymdHis') . rand(100, 999);
        $final_amount = floatval($form_data['finalAmount']);
        $amount_cents = round($final_amount * 100);
        
        // Almacenar datos del formulario para el callback
        set_transient('tbv2_form_data_' . $order_id, $form_data, 2 * HOUR_IN_SECONDS);
        
        // Preparar datos para Redsys
        $order_data = [
            'order_id' => $order_id,
            'amount_cents' => $amount_cents,
            'description' => 'Transferencia Embarcación - ' . ($form_data['tipo_embarcacion'] ?? 'Barco')
        ];
        
        // Crear formulario de pago
        $payment_data = tbv2_redsys_create_payment_form($order_data);
        
        tbv2_log("Pago creado exitosamente - Order: $order_id, Amount: $final_amount€", 'SUCCESS');
        
        wp_send_json_success([
            'message' => 'Parámetros de pago generados exitosamente',
            'redsysData' => $payment_data
        ]);
        
    } catch (Exception $e) {
        tbv2_log('Error creando pago Redsys: ' . $e->getMessage(), 'ERROR');
        wp_send_json_error('Error al procesar el pago: ' . $e->getMessage());
    }
}

/**
 * ✅ AJAX HANDLER: Almacenar archivos
 */
function tbv2_store_files_dual() {
    if (!wp_verify_nonce($_POST['nonce'], 'tbv2_nonce')) {
        wp_send_json_error('Error de seguridad');
        return;
    }
    
    try {
        $order_id = sanitize_text_field($_POST['order_id']);
        $files_data_json = sanitize_text_field($_POST['files_data']);
        
        if (empty($order_id) || empty($files_data_json)) {
            wp_send_json_error('Datos faltantes');
            return;
        }
        
        $files_data = json_decode($files_data_json, true);
        if (!$files_data) {
            wp_send_json_error('Formato de archivos inválido');
            return;
        }
        
        // Almacenar en transient por 2 horas
        $transient_key = 'tbv2_files_' . $order_id;
        $stored = set_transient($transient_key, $files_data, 2 * HOUR_IN_SECONDS);
        
        if ($stored) {
            tbv2_log("Archivos almacenados exitosamente para order: $order_id", 'SUCCESS');
            
            wp_send_json_success([
                'message' => 'Archivos almacenados correctamente',
                'transient_key' => $transient_key
            ]);
        } else {
            wp_send_json_error('Error almacenando archivos');
        }
        
    } catch (Exception $e) {
        tbv2_log('Error almacenando archivos: ' . $e->getMessage(), 'ERROR');
        wp_send_json_error('Excepción: ' . $e->getMessage());
    }
}

// =====================================================
// RENDERIZADO DEL FORMULARIO
// =====================================================

/**
 * ✅ RENDERIZAR FORMULARIO PRINCIPAL
 */
function tbv2_render_form() {
    ob_start();
    $datos_csv = tbv2_cargar_datos_csv();
    ?>
    
    <!-- ESTILOS CSS INTEGRADOS -->
    <style>
    :root {
        --primary-color: #016d86;
        --primary-light: #0891b2;
        --success-color: #059669;
        --error-color: #dc2626;
        --gray-50: #f8fafc;
        --gray-100: #f1f5f9;
        --gray-300: #cbd5e1;
        --gray-500: #64748b;
        --gray-700: #334155;
        --spacing-xs: 8px;
        --spacing-sm: 12px;
        --spacing-md: 16px;
        --spacing-lg: 24px;
        --spacing-xl: 32px;
        --border-radius: 8px;
        --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    }

    .tbv2-form-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: var(--spacing-lg);
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        line-height: 1.6;
        color: var(--gray-700);
    }

    .layout-wrapper {
        background: white;
        border-radius: 12px;
        box-shadow: var(--shadow-lg);
        overflow: hidden;
    }

    .two-column-layout {
        display: grid;
        grid-template-columns: 350px 1fr;
        min-height: 600px;
    }

    @media (max-width: 768px) {
        .two-column-layout {
            grid-template-columns: 1fr;
        }
        .sidebar {
            order: 2;
        }
    }

    .sidebar {
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
        color: white;
        padding: var(--spacing-xl);
    }

    .main-form {
        padding: var(--spacing-xl);
        background: var(--gray-50);
    }

    .form-page {
        display: none;
    }

    .form-page.active {
        display: block;
    }

    .form-page h2 {
        margin: 0 0 var(--spacing-xl) 0;
        color: var(--primary-color);
        font-size: 1.75em;
        font-weight: 600;
    }

    .form-group {
        margin-bottom: var(--spacing-lg);
    }

    .form-group label {
        display: block;
        margin-bottom: var(--spacing-xs);
        font-weight: 500;
        color: var(--gray-700);
    }

    .form-group input,
    .form-group select {
        width: 100%;
        padding: var(--spacing-md);
        border: 1px solid var(--gray-300);
        border-radius: var(--border-radius);
        font-size: 1em;
        background: white;
    }

    .form-group input:focus,
    .form-group select:focus {
        outline: none;
        border-color: var(--primary-color);
    }

    .form-group small {
        display: block;
        margin-top: var(--spacing-xs);
        color: var(--gray-500);
        font-size: 0.875em;
    }

    .file-upload-group {
        margin-bottom: var(--spacing-lg);
        padding: var(--spacing-lg);
        background: white;
        border: 2px dashed var(--gray-300);
        border-radius: var(--border-radius);
    }

    .file-count {
        margin-top: var(--spacing-xs);
        font-size: 0.875em;
        color: var(--gray-500);
        font-weight: 500;
    }

    .btn-primary,
    .btn-secondary,
    .btn-next,
    .btn-prev {
        padding: var(--spacing-md) var(--spacing-xl);
        border: none;
        border-radius: var(--border-radius);
        font-size: 1em;
        font-weight: 600;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: var(--spacing-xs);
    }

    .btn-primary {
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
        color: white;
        box-shadow: var(--shadow-md);
    }

    .btn-primary:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }

    .btn-secondary {
        background: white;
        color: var(--primary-color);
        border: 1px solid var(--primary-color);
    }

    .btn-next {
        background: linear-gradient(135deg, var(--success-color) 0%, #047857 100%);
        color: white;
    }

    .btn-prev {
        background: var(--gray-100);
        color: var(--gray-700);
    }

    .form-navigation {
        display: flex;
        gap: var(--spacing-md);
        margin-top: var(--spacing-xl);
        padding-top: var(--spacing-lg);
        border-top: 1px solid var(--gray-300);
    }

    .form-navigation .btn-next {
        margin-left: auto;
    }

    .price-summary {
        background: rgba(255, 255, 255, 0.1);
        border-radius: var(--border-radius);
        padding: var(--spacing-lg);
        margin-bottom: var(--spacing-lg);
    }

    .price-item {
        display: flex;
        justify-content: space-between;
        margin-bottom: var(--spacing-sm);
    }

    .price-total {
        display: flex;
        justify-content: space-between;
        font-weight: 600;
        font-size: 1.1em;
        padding-top: var(--spacing-sm);
        border-top: 1px solid rgba(255, 255, 255, 0.3);
        margin-top: var(--spacing-sm);
    }

    .payment-summary {
        background: white;
        padding: var(--spacing-lg);
        border-radius: var(--border-radius);
        box-shadow: var(--shadow-sm);
        margin-bottom: var(--spacing-lg);
    }

    .summary-line {
        display: flex;
        justify-content: space-between;
        padding: var(--spacing-sm) 0;
        border-bottom: 1px solid var(--gray-100);
    }

    .summary-total {
        display: flex;
        justify-content: space-between;
        padding: var(--spacing-md) 0 0 0;
        font-weight: 600;
        font-size: 1.1em;
        color: var(--primary-color);
    }

    .payment-option {
        padding: var(--spacing-md);
        border: 2px solid var(--gray-300);
        border-radius: var(--border-radius);
        background: white;
        margin-bottom: var(--spacing-md);
    }

    .payment-option.selected {
        border-color: var(--primary-color);
        background: rgba(1, 109, 134, 0.05);
    }
    </style>

    <!-- FORMULARIO PRINCIPAL -->
    <div id="tramitfy-app-container" class="tbv2-form-container">
        <div class="layout-wrapper">
            <div class="two-column-layout">
                <!-- SIDEBAR -->
                <div class="sidebar">
                    <div class="sidebar-content">
                        <h2>🚢 Transferencia de Embarcación</h2>
                        <div class="price-summary">
                            <div class="price-item">
                                <span>Tramitación</span>
                                <span>134,99€</span>
                            </div>
                            <div class="price-item">
                                <span>ITP <span id="itp-percentage">(4%)</span></span>
                                <span id="itp-amount">0,00€</span>
                            </div>
                            <div class="price-total">
                                <span>Total</span>
                                <span id="total-amount">134,99€</span>
                            </div>
                        </div>
                        
                        <div class="benefits">
                            <h3>✅ Incluye</h3>
                            <ul>
                                <li>📋 Gestión completa del trámite</li>
                                <li>📄 Documentación oficial</li>
                                <li>⚡ Procesamiento express</li>
                                <li>🔔 Notificaciones por email</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <!-- FORMULARIO PRINCIPAL -->
                <div class="main-form">
                    <form id="tbv2-form" method="post">
                        <?php wp_nonce_field('tbv2_nonce', 'tbv2_nonce'); ?>
                        
                        <!-- PÁGINA 1: DATOS PERSONALES -->
                        <div id="page-personal" class="form-page active">
                            <h2>👤 Datos del Comprador</h2>
                            
                            <div class="form-group">
                                <label for="customer_name">Nombre completo *</label>
                                <input type="text" id="customer_name" name="customer_name" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="customer_dni">DNI/NIE *</label>
                                <input type="text" id="customer_dni" name="customer_dni" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="customer_email">Email *</label>
                                <input type="email" id="customer_email" name="customer_email" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="customer_phone">Teléfono *</label>
                                <input type="tel" id="customer_phone" name="customer_phone" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="company_name">Empresa (opcional)</label>
                                <input type="text" id="company_name" name="company_name">
                            </div>
                            
                            <div class="form-navigation">
                                <button type="button" class="btn-next" onclick="TBV2_Form.nextPage()">
                                    Siguiente <i class="fa-solid fa-arrow-right"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- PÁGINA 2: DATOS DEL VEHÍCULO -->
                        <div id="page-vehicle" class="form-page">
                            <h2>🚢 Datos de la Embarcación</h2>
                            
                            <div class="form-group">
                                <label for="tipo_embarcacion">Tipo de embarcación *</label>
                                <select id="tipo_embarcacion" name="tipo_embarcacion" required>
                                    <option value="">Seleccionar tipo...</option>
                                    <option value="Barco de motor">Barco de motor</option>
                                    <option value="Velero">Velero</option>
                                    <option value="Zodiac">Zodiac</option>
                                    <option value="Jet ski">Jet ski</option>
                                    <option value="Otro">Otro</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="matricula">Matrícula *</label>
                                <input type="text" id="matricula" name="matricula" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="year_fabrication">Año de fabricación</label>
                                <input type="number" id="year_fabrication" name="year_fabrication" min="1950" max="2025">
                            </div>
                            
                            <div class="form-group">
                                <label for="valor_transmision">Precio de compra (€) *</label>
                                <input type="number" id="valor_transmision" name="valor_transmision" required min="1" step="0.01">
                                <small>Para calcular el ITP correspondiente</small>
                            </div>
                            
                            <div class="form-navigation">
                                <button type="button" class="btn-prev" onclick="TBV2_Form.prevPage()">
                                    <i class="fa-solid fa-arrow-left"></i> Anterior
                                </button>
                                <button type="button" class="btn-next" onclick="TBV2_Form.nextPage()">
                                    Siguiente <i class="fa-solid fa-arrow-right"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- PÁGINA 3: DOCUMENTOS -->
                        <div id="page-documents" class="form-page">
                            <h2>📄 Documentos Requeridos</h2>
                            
                            <div class="file-upload-group">
                                <label>DNI del comprador (ambas caras) *</label>
                                <input type="file" id="file_dni_comprador" multiple accept=".pdf,.jpg,.jpeg,.png" required>
                                <div class="file-count" id="dni-comprador-count">0 archivos</div>
                            </div>
                            
                            <div class="file-upload-group">
                                <label>Permiso de circulación actual *</label>
                                <input type="file" id="file_permiso_circulacion" multiple accept=".pdf,.jpg,.jpeg,.png" required>
                                <div class="file-count" id="permiso-count">0 archivos</div>
                            </div>
                            
                            <div class="file-upload-group">
                                <label>Documentos adicionales</label>
                                <input type="file" id="file_documentos_adicionales" multiple accept=".pdf,.jpg,.jpeg,.png">
                                <div class="file-count" id="adicionales-count">0 archivos</div>
                                <small>Contrato compraventa, certificado de navegabilidad, etc.</small>
                            </div>
                            
                            <div class="form-navigation">
                                <button type="button" class="btn-prev" onclick="TBV2_Form.prevPage()">
                                    <i class="fa-solid fa-arrow-left"></i> Anterior
                                </button>
                                <button type="button" class="btn-next" onclick="TBV2_Form.nextPage()">
                                    Siguiente <i class="fa-solid fa-arrow-right"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- PÁGINA 4: PAGO -->
                        <div id="page-payment" class="form-page">
                            <h2>💳 Pago Seguro</h2>
                            
                            <div class="payment-summary">
                                <h3>Resumen del pedido</h3>
                                <div class="summary-line">
                                    <span>Transferencia de embarcación</span>
                                    <span>134,99€</span>
                                </div>
                                <div class="summary-line">
                                    <span>ITP <span id="final-itp-percentage"></span></span>
                                    <span id="final-itp-amount">0,00€</span>
                                </div>
                                <div class="summary-total">
                                    <span>Total a pagar</span>
                                    <span id="final-total-amount">134,99€</span>
                                </div>
                            </div>
                            
                            <div class="payment-option selected">
                                <i class="fa-solid fa-credit-card"></i>
                                <span>Tarjeta de crédito/débito</span>
                                <small>Pago seguro con Redsys</small>
                            </div>
                            
                            <div class="form-navigation">
                                <button type="button" class="btn-prev" onclick="TBV2_Form.prevPage()">
                                    <i class="fa-solid fa-arrow-left"></i> Anterior
                                </button>
                                <button type="button" id="submit-payment" class="btn-primary">
                                    <i class="fa-solid fa-credit-card"></i> Proceder al Pago
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- JAVASCRIPT INTEGRADO -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log('🚀 TBV2 - Inicializando formulario corregido...');
        
        // OBJETO PRINCIPAL DEL FORMULARIO
        window.TBV2_Form = {
            currentPage: 0,
            pages: ['personal', 'vehicle', 'documents', 'payment'],
            
            init: function() {
                console.log('📋 Inicializando TBV2 Form...');
                this.setupEventListeners();
                this.updateNavigation();
                TBV2_ITP.init();
                TBV2_FileUpload.init();
                TBV2_Payment.init();
            },
            
            setupEventListeners: function() {
                <?php if (current_user_can('administrator')): ?>
                this.autoFillAdminData();
                <?php endif; ?>
                
                document.getElementById('valor_transmision').addEventListener('input', () => {
                    TBV2_ITP.calculateITP();
                });
            },
            
            autoFillAdminData: function() {
                const testData = {
                    customer_name: 'Test Usuario',
                    customer_dni: '12345678A',
                    customer_email: 'admin@tramitfy.es',
                    customer_phone: '666777888',
                    tipo_embarcacion: 'Barco de motor',
                    matricula: 'TEST-123',
                    year_fabrication: '2020',
                    valor_transmision: '25000'
                };
                
                Object.entries(testData).forEach(([field, value]) => {
                    const element = document.getElementById(field);
                    if (element) {
                        element.value = value;
                        if (field === 'valor_transmision') {
                            TBV2_ITP.calculateITP();
                        }
                    }
                });
                
                console.log('🔧 Datos de admin pre-rellenados');
            },
            
            nextPage: function() {
                if (!this.validateCurrentPage()) return;
                
                if (this.currentPage < this.pages.length - 1) {
                    this.currentPage++;
                    this.showPage(this.currentPage);
                    this.updateNavigation();
                }
            },
            
            prevPage: function() {
                if (this.currentPage > 0) {
                    this.currentPage--;
                    this.showPage(this.currentPage);
                    this.updateNavigation();
                }
            },
            
            showPage: function(pageIndex) {
                document.querySelectorAll('.form-page').forEach(page => {
                    page.classList.remove('active');
                });
                
                const currentPage = document.getElementById('page-' + this.pages[pageIndex]);
                if (currentPage) {
                    currentPage.classList.add('active');
                }
            },
            
            updateNavigation: function() {
                if (this.pages[this.currentPage] === 'payment') {
                    this.updatePaymentSummary();
                }
            },
            
            validateCurrentPage: function() {
                const pageId = 'page-' + this.pages[this.currentPage];
                const currentPage = document.getElementById(pageId);
                const requiredFields = currentPage.querySelectorAll('[required]');
                
                let isValid = true;
                
                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        field.focus();
                        field.style.borderColor = 'var(--error-color)';
                        isValid = false;
                        return false;
                    } else {
                        field.style.borderColor = 'var(--gray-300)';
                    }
                });
                
                if (this.pages[this.currentPage] === 'documents') {
                    if (!TBV2_FileUpload.validateRequiredFiles()) {
                        isValid = false;
                    }
                }
                
                return isValid;
            },
            
            updatePaymentSummary: function() {
                const finalItp = TBV2_ITP.getCurrentITP();
                const transferPrice = 134.99;
                const total = transferPrice + finalItp;
                
                document.getElementById('final-itp-percentage').textContent = `(${TBV2_ITP.getCurrentPercentage()}%)`;
                document.getElementById('final-itp-amount').textContent = finalItp.toFixed(2) + '€';
                document.getElementById('final-total-amount').textContent = total.toFixed(2) + '€';
            },
            
            collectFormData: function() {
                const formData = new FormData(document.getElementById('tbv2-form'));
                const data = Object.fromEntries(formData.entries());
                
                data.itpAmount = TBV2_ITP.getCurrentITP();
                data.itpPercentage = TBV2_ITP.getCurrentPercentage();
                data.finalAmount = (134.99 + TBV2_ITP.getCurrentITP()).toFixed(2);
                data.files = TBV2_FileUpload.getAllFiles();
                
                return data;
            }
        };
        
        // SISTEMA ITP
        window.TBV2_ITP = {
            init: function() {
                console.log('💰 Inicializando sistema ITP...');
            },
            
            calculateITP: function() {
                const valorTransmision = parseFloat(document.getElementById('valor_transmision').value) || 0;
                const year = parseInt(document.getElementById('year_fabrication').value) || new Date().getFullYear();
                
                let percentage = 4;
                
                const currentYear = new Date().getFullYear();
                const age = currentYear - year;
                
                if (age > 10) {
                    percentage = Math.max(percentage - Math.floor(age / 5), 2);
                }
                
                const itpAmount = (valorTransmision * percentage) / 100;
                const total = 134.99 + itpAmount;
                
                document.getElementById('itp-percentage').textContent = `(${percentage}%)`;
                document.getElementById('itp-amount').textContent = itpAmount.toFixed(2) + '€';
                document.getElementById('total-amount').textContent = total.toFixed(2) + '€';
                
                return { percentage, amount: itpAmount };
            },
            
            getCurrentITP: function() {
                return parseFloat(document.getElementById('itp-amount').textContent.replace('€', '')) || 0;
            },
            
            getCurrentPercentage: function() {
                const text = document.getElementById('itp-percentage').textContent;
                return parseInt(text.match(/\d+/)[0]) || 4;
            }
        };
        
        // SISTEMA DE ARCHIVOS
        window.TBV2_FileUpload = {
            files: {},
            
            init: function() {
                console.log('📁 Inicializando sistema de archivos...');
                this.setupFileInputs();
            },
            
            setupFileInputs: function() {
                const fileInputs = [
                    { id: 'file_dni_comprador', counter: 'dni-comprador-count', category: 'dni_comprador' },
                    { id: 'file_permiso_circulacion', counter: 'permiso-count', category: 'permiso_circulacion' },
                    { id: 'file_documentos_adicionales', counter: 'adicionales-count', category: 'documentos_adicionales' }
                ];
                
                fileInputs.forEach(({id, counter, category}) => {
                    const input = document.getElementById(id);
                    const counterEl = document.getElementById(counter);
                    
                    if (input && counterEl) {
                        input.addEventListener('change', (e) => {
                            this.handleFileSelection(e, category, counterEl);
                        });
                    }
                });
            },
            
            handleFileSelection: function(event, category, counterElement) {
                const files = Array.from(event.target.files);
                this.files[category] = files;
                
                counterElement.textContent = files.length + ' archivo' + (files.length !== 1 ? 's' : '');
                counterElement.style.color = files.length > 0 ? 'var(--success-color)' : 'var(--gray-500)';
                
                console.log(`📎 ${category}: ${files.length} archivos seleccionados`);
            },
            
            validateRequiredFiles: function() {
                const required = ['dni_comprador', 'permiso_circulacion'];
                
                for (const category of required) {
                    if (!this.files[category] || this.files[category].length === 0) {
                        alert(`Por favor, seleccione archivos para: ${category.replace('_', ' ')}`);
                        return false;
                    }
                }
                
                return true;
            },
            
            getAllFiles: function() {
                return this.files;
            }
        };
        
        // SISTEMA DE PAGO
        window.TBV2_Payment = {
            init: function() {
                console.log('💳 Inicializando sistema de pago...');
                
                document.getElementById('submit-payment').addEventListener('click', () => {
                    this.processPayment();
                });
            },
            
            processPayment: function() {
                console.log('🔄 Iniciando proceso de pago...');
                
                const formData = TBV2_Form.collectFormData();
                
                this.showLoadingState();
                
                this.storeFiles(formData).then(() => {
                    return this.createRedsysPayment(formData);
                }).then((redsysData) => {
                    this.redirectToRedsys(redsysData);
                }).catch((error) => {
                    console.error('❌ Error en el proceso de pago:', error);
                    this.hideLoadingState();
                    alert('Error al procesar el pago. Por favor, inténtelo de nuevo.');
                });
            },
            
            storeFiles: function(formData) {
                return new Promise((resolve, reject) => {
                    const filesData = new FormData();
                    filesData.append('action', 'tbv2_store_files_dual');
                    filesData.append('nonce', document.querySelector('[name="tbv2_nonce"]').value);
                    filesData.append('order_id', 'temp_' + Date.now());
                    
                    const fileCategories = TBV2_FileUpload.getAllFiles();
                    const processedFiles = {};
                    
                    Object.keys(fileCategories).forEach(category => {
                        processedFiles[category] = [];
                    });
                    
                    filesData.append('files_data', JSON.stringify(processedFiles));
                    
                    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                        method: 'POST',
                        body: filesData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            console.log('✅ Archivos almacenados correctamente');
                            resolve(data);
                        } else {
                            reject(data.data || 'Error almacenando archivos');
                        }
                    })
                    .catch(reject);
                });
            },
            
            createRedsysPayment: function(formData) {
                const paymentData = new FormData();
                paymentData.append('action', 'tbv2_create_redsys_payment');
                paymentData.append('nonce', document.querySelector('[name="tbv2_nonce"]').value);
                paymentData.append('formData', JSON.stringify(formData));
                
                return fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    body: paymentData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        return data.data;
                    } else {
                        throw new Error(data.data || 'Error creando el pago');
                    }
                });
            },
            
            redirectToRedsys: function(redsysData) {
                console.log('🏦 Redirigiendo a Redsys TPV...');
                
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = redsysData.redsysData.url;
                form.style.display = 'none';
                
                Object.keys(redsysData.redsysData.params).forEach(key => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = key;
                    input.value = redsysData.redsysData.params[key];
                    form.appendChild(input);
                });
                
                document.body.appendChild(form);
                form.submit();
            },
            
            showLoadingState: function() {
                const button = document.getElementById('submit-payment');
                button.disabled = true;
                button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Procesando pago...';
            },
            
            hideLoadingState: function() {
                const button = document.getElementById('submit-payment');
                button.disabled = false;
                button.innerHTML = '<i class="fa-solid fa-credit-card"></i> Proceder al Pago';
            }
        };
        
        // Inicializar formulario
        TBV2_Form.init();
    });
    </script>
    
    <?php
    return ob_get_clean();
}

// =====================================================
// REGISTRO DE SHORTCODE Y HOOKS
// =====================================================

/**
 * ✅ REGISTRO ÚNICO DE SHORTCODE
 */
if (!function_exists('tbv2_register_shortcode_safe')) {
    function tbv2_register_shortcode_safe() {
        if (!shortcode_exists('transferencia_barco_v2')) {
            add_shortcode('transferencia_barco_v2', 'tbv2_render_form');
        }
    }
    add_action('init', 'tbv2_register_shortcode_safe');
}

/**
 * ✅ ENQUEUE SCRIPTS SEGUROS
 */
if (!function_exists('tbv2_enqueue_scripts_safe')) {
    function tbv2_enqueue_scripts_safe() {
        if (is_admin()) return;
        
        global $post;
        if (isset($post->post_content) && has_shortcode($post->post_content, 'transferencia_barco_v2')) {
            wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css');
        }
    }
    add_action('wp_enqueue_scripts', 'tbv2_enqueue_scripts_safe');
}

// ✅ AJAX HOOKS SEGUROS
add_action('wp_ajax_tbv2_create_redsys_payment', 'tbv2_handle_create_redsys_payment');
add_action('wp_ajax_nopriv_tbv2_create_redsys_payment', 'tbv2_handle_create_redsys_payment');

add_action('wp_ajax_tbv2_store_files_dual', 'tbv2_store_files_dual');
add_action('wp_ajax_nopriv_tbv2_store_files_dual', 'tbv2_store_files_dual');

// ✅ CALLBACK HANDLER
add_action('template_redirect', 'tbv2_handle_wordpress_callback');

tbv2_log("TBV2 CORREGIDO FINAL cargado exitosamente - Versión WordPress nativa completa", 'SUCCESS');
?>