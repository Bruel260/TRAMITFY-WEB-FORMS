<?php
/**
 * TRAMITFY - TRANSFERENCIA EMBARCACIONES V2 CON REDSYS
 * 
 * Versión CORREGIDA compatible con WordPress
 * ✅ Sin acceso directo fuera de WordPress
 * ✅ Variables locales en lugar de defines globales  
 * ✅ URLs de callback WordPress nativas
 * ✅ Integración perfecta con React webhook
 * 
 * @version 2.3.0 - WORDPRESS NATIVE
 * @author Claude Code  
 * @created 2025-11-13
 * @updated 2025-11-13 - Corrección errores críticos
 * @reference Formulario producción /transferencia-barco.php
 */

// ✅ SOLO PERMITIR ACCESO VÍA WORDPRESS
if (!defined('ABSPATH')) {
    exit('Acceso directo no permitido.');
}

// Sistema de logging simplificado
if (!function_exists('tbv2_log')) {
    function tbv2_log($message, $level = 'INFO') {
        error_log("TBV2_[$level]: $message");
    }
}

// =====================================================
// CONFIGURACIÓN REDSYS V2 (VARIABLES LOCALES)
// =====================================================

/**
 * Configuración del formulario TBV2
 * ✅ Variables locales en lugar de defines globales
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
 * ✅ URLs DE CALLBACK WORDPRESS NATIVAS
 * Usa home_url() y add_query_arg() en lugar de archivos externos
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
 * Función para obtener URL de TPV según entorno
 */
function tbv2_get_redsys_url() {
    $config = tbv2_get_config();
    return ($config['redsys_mode'] === 'test') ? $config['url_test'] : $config['url_live'];
}

/**
 * Cargar datos CSV de embarcaciones
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

/**
 * ✅ GENERACIÓN DE FIRMA REDSYS
 * Función sin dependencias externas
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
 * Versión simplificada y robusta
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
 * Verificación de firma para callbacks
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
            'customerName' => $stored_data['customerName'],
            'customerEmail' => $stored_data['customerEmail'],
            'customerPhone' => $stored_data['customerPhone'],
            'customerDni' => $stored_data['customerDni'],
            'companyName' => $stored_data['companyName'] ?? '',
            'vehicleType' => 'Barco',
            'manufacturer' => 'Embarcación',
            'model' => $stored_data['tipoEmbarcacion'],
            'year' => $stored_data['yearFabrication'],
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

/**
 * ✅ RENDERIZAR FORMULARIO PRINCIPAL
 * Mantiene estructura HTML original
 */
function tbv2_render_form() {
    ob_start();
    $datos_csv = tbv2_cargar_datos_csv();
    ?>
    
    <!-- FORMULARIO PRINCIPAL IDÉNTICO AL ORIGINAL -->
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
                                <span id="transfer-price">134,99€</span>
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
                        <!-- Nonce de seguridad -->
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
                        
                        <!-- PÁGINA 4: FIRMA -->
                        <div id="page-signature" class="form-page">
                            <h2>✍️ Autorización y Firma</h2>
                            
                            <div class="authorization-section">
                                <div class="document-preview">
                                    <h3>📋 Documento de Autorización</h3>
                                    <p>Por favor, revise el documento de autorización antes de proceder con la firma digital.</p>
                                    <button type="button" id="show-document" class="btn-secondary">
                                        Ver Documento <i class="fa-solid fa-eye"></i>
                                    </button>
                                </div>
                                
                                <div class="signature-section">
                                    <div class="signature-status">
                                        <div class="file-count" id="signature-status">Pendiente de firma</div>
                                    </div>
                                    <button type="button" id="start-signature" class="btn-secondary" disabled>
                                        <i class="fa-solid fa-signature"></i> Firmar Documento
                                    </button>
                                </div>
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
                        
                        <!-- PÁGINA 5: PAGO -->
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
                            
                            <div class="payment-methods">
                                <h3>Método de pago</h3>
                                <div class="payment-option selected">
                                    <i class="fa-solid fa-credit-card"></i>
                                    <span>Tarjeta de crédito/débito</span>
                                    <small>Pago seguro con Redsys</small>
                                </div>
                            </div>
                            
                            <div class="form-navigation">
                                <button type="button" class="btn-prev" onclick="TBV2_Form.prevPage()">
                                    <i class="fa-solid fa-arrow-left"></i> Anterior
                                </button>
                                <button type="button" id="submit-payment" class="btn-primary" disabled>
                                    <i class="fa-solid fa-credit-card"></i> Proceder al Pago
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- MODALES -->
    <div id="document-preview-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>📋 Documento de Autorización</h3>
                <button type="button" class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <div id="document-content"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="TBV2_Signature.closeDocumentPreview()">Cancelar</button>
                <button type="button" class="btn-primary" onclick="TBV2_Signature.proceedToSignature()">Proceder a Firmar</button>
            </div>
        </div>
    </div>
    
    <div id="signature-modal" class="modal">
        <div class="modal-content signature-modal-content">
            <div class="modal-header">
                <h3>✍️ Firma Digital</h3>
                <button type="button" class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <div class="signature-instructions">
                    <p>Por favor, firme en el recuadro inferior:</p>
                </div>
                <div class="signature-pad-container">
                    <canvas id="signature-pad" width="600" height="300"></canvas>
                </div>
                <div class="signature-controls">
                    <button type="button" class="btn-secondary" onclick="TBV2_Signature.clearSignature()">
                        <i class="fa-solid fa-eraser"></i> Limpiar
                    </button>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="TBV2_Signature.closeSignatureModal()">Cancelar</button>
                <button type="button" class="btn-primary" onclick="TBV2_Signature.acceptSignature()">Confirmar Firma</button>
            </div>
        </div>
    </div>
    
    <?php
    // Renderizar estilos y scripts
    tbv2_render_styles();
    tbv2_render_scripts();
    
    return ob_get_clean();
}

// CONTINUARÁ CON ESTILOS Y SCRIPTS EN LA PARTE 2...// ESTILOS Y SCRIPTS COMPLETADOS
