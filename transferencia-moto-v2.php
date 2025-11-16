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

    /* ============================================
    LAYOUT PRINCIPAL IDÉNTICO A TBV2 (TEMÁTICA MOTOS)
    ============================================ */
    
    /* Container principal (idéntico a TBV2) */
    .tramitfy-layout-wrapper {
        max-width: 1400px;
        width: 95%;
        margin: 40px auto 0 auto;
        padding: 0;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }

    .tramitfy-two-column {
        display: grid !important;
        grid-template-columns: 384px 1fr !important; /* Sidebar fijo 384px + formulario resto */
        grid-template-areas: "sidebar content" !important;
        gap: 0;
        align-items: stretch;
        background: white;
        border-radius: 16px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
    }

    /* SIDEBAR IZQUIERDO (idéntico a TBV2 con temática motos) */
    .tramitfy-sidebar {
        grid-area: sidebar;
        position: relative;
        background: #016d86; /* Color corporativo tramitfy */
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
        width: 384px;
        min-height: 100%;
        height: auto;
        transition: width 0.3s ease, background 0.3s ease, box-shadow 0.3s ease;
    }

    .sidebar-content {
        display: flex;
        flex-direction: column;
    }

    .sidebar-body {
        flex: 1;
    }

    /* FORMULARIO PRINCIPAL (idéntico a TBV2) */
    .tramitfy-main-form {
        grid-area: content;
        background: white;
        border-radius: 0 12px 12px 0;
        padding: 32px;
        overflow-y: auto;
        overflow-x: hidden;
    }

    /* ============================================
    NAVEGACIÓN IDÉNTICA A TBV2 (TEMÁTICA MOTOS)
    ============================================ */
    
    /* Navegación principal superior (idéntica a TBV2) */
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

    /* ============================================
    PÁGINAS Y FORMULARIOS IDÉNTICOS A TBV2
    ============================================ */
    
    /* Páginas del formulario (idénticas a TBV2) */
    .form-page {
        transition: opacity 0.3s ease;
    }

    .form-page.hidden {
        display: none;
    }

    .form-page h2 {
        color: #016d86;
        font-size: 26px;
        font-weight: 600;
        margin-bottom: 20px;
        border-bottom: 2px solid rgba(1, 109, 134, 0.1);
        padding-bottom: 8px;
    }

    /* LAYOUTS COMPACTOS PARA FORMULARIO (idénticos a TBV2) */
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

    /* Campos de formulario (idénticos a TBV2) */
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
    .form-group select,
    .form-group textarea {
        padding: 12px;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        font-size: 16px;
        transition: all 0.2s ease;
        background: white;
        box-sizing: border-box;
    }

    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: #016d86;
        box-shadow: 0 0 0 3px rgba(1, 109, 134, 0.1);
    }

    /* Select styling (idéntico a TBV2) */
    .form-group select {
        -webkit-appearance: none;
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23016d86' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 16px center;
        padding-right: 40px;
    }

    /* Input hints (idéntico a TBV2) */
    .input-hint {
        font-size: 13px;
        color: #64748b;
        margin-top: 6px;
        display: block;
        line-height: 1.4;
    }

    /* ============================================
    BOTONES IDÉNTICOS A TBV2
    ============================================ */
    
    .btn {
        padding: 12px 24px;
        border-radius: 6px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s ease;
        border: none;
        font-size: 16px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        gap: 8px;
    }

    .btn-primary {
        background: #016d86;
        color: white;
    }

    .btn-primary:hover {
        background: #01546a;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(1, 109, 134, 0.3);
    }

    .btn-secondary {
        background: #6b7280;
        color: white;
    }

    .btn-secondary:hover {
        background: #4b5563;
    }

    /* ============================================
    RESPONSIVE IDÉNTICO A TBV2
    ============================================ */
    
    @media (max-width: 768px) {
        .tramitfy-layout-wrapper {
            margin: 20px auto 0 auto;
            width: 98%;
        }
        
        .tramitfy-two-column {
            grid-template-columns: 1fr !important;
            grid-template-areas: "sidebar" "content" !important;
            border-radius: 8px;
        }
        
        .tramitfy-sidebar {
            width: 100% !important;
            border-radius: 8px 8px 0 0;
            position: relative;
            top: 0;
        }

        .tramitfy-main-form {
            border-radius: 0 0 8px 8px;
            padding: 20px;
        }

        .nav-tabs-container {
            flex-wrap: wrap;
        }

        .nav-tab {
            flex: 1 1 50%;
            min-width: 120px;
            padding: 12px 8px;
        }

        .form-compact-row,
        .form-compact-triple {
            grid-template-columns: 1fr;
            gap: 15px;
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

    <!-- TMV2 - FORMULARIO PRINCIPAL IDÉNTICO A TBV2 -->
    <form id="tmv2-transferencia-form" class="form-container">
        
        <!-- LAYOUT WRAPPER IDÉNTICO A TBV2 -->
        <div class="tramitfy-layout-wrapper">
            <div class="tramitfy-two-column">

                <!-- SIDEBAR IZQUIERDO IDÉNTICO A TBV2 -->
                <aside class="tramitfy-sidebar">
                    <div class="sidebar-content">
                        <div class="sidebar-body">
                            <div id="tmv2-sidebar-dynamic-content">
                                <!-- Contenido dinámico por paso idéntico a TBV2 -->
                            </div>
                                
                            <!-- Widget Trustpilot -->
                            <div style="margin-top: 20px;">
                                <script defer async src='https://cdn.trustindex.io/loader.js?f4fbfd341d12439e0c86fae7fc2'></script>
                            </div>
                        </div>
                    </div>
                </aside>

                <!-- PANEL DERECHO - FORMULARIO IDÉNTICO A TBV2 -->
                <div class="tramitfy-main-form">

                    <!-- Navegación superior idéntica a TBV2 -->
                    <div id="form-navigation-top">
                        <div class="nav-tabs-container">
                            <div class="nav-tab active" data-step="1">
                                <div class="tab-content-centered">
                                    <span class="tab-title">🚤 Datos Moto</span>
                                </div>
                            </div>
                            <div class="nav-tab" data-step="2">
                                <div class="tab-content-centered">
                                    <span class="tab-title">👥 Propietarios</span>
                                </div>
                            </div>
                            <div class="nav-tab" data-step="3">
                                <div class="tab-content-centered">
                                    <span class="tab-title">📄 Documentos</span>
                                </div>
                            </div>
                            <div class="nav-tab" data-step="4">
                                <div class="tab-content-centered">
                                    <span class="tab-title">💳 Pago</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- PASO 1: DATOS DE LA MOTO (estructura idéntica a TBV2) -->
                    <div class="form-page active" data-step="1">
                        <h2>🚤 Datos de la Moto de Agua</h2>
                        
                        <div class="form-compact-row">
                            <div class="form-group">
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

                            <div class="form-group">
                                <label for="tmv2-model">Modelo *</label>
                                <input type="text" id="tmv2-model" name="model" placeholder="ej. GTI 130" required>
                            </div>
                        </div>

                        <div class="form-compact-row">
                            <div class="form-group">
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

                            <div class="form-group">
                                <label for="tmv2-matricula">Matrícula *</label>
                                <input type="text" id="tmv2-matricula" name="matricula" placeholder="ej. 1234ABC" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="tmv2-purchase-price">Precio de compraventa (€) *</label>
                            <input type="number" id="tmv2-purchase-price" name="purchasePrice" placeholder="15000" step="0.01" required>
                        </div>

                        <div style="text-align: right; margin-top: 30px;">
                            <button type="button" class="btn btn-primary" onclick="tmv2_nextStep(2)">
                                Siguiente: Propietarios →
                            </button>
                        </div>
                    </div>

                    <!-- PASO 2: DATOS DE PROPIETARIOS (estructura idéntica a TBV2) -->
                    <div class="form-page hidden" data-step="2">
                        <h2>👥 Datos de Propietarios</h2>
                        
                        <h3 style="color: #059669; margin: 30px 0 20px 0;">Comprador (Nuevo propietario)</h3>
                        
                        <div class="form-compact-row">
                            <div class="form-group">
                                <label for="tmv2-buyer-name">Nombre completo *</label>
                                <input type="text" id="tmv2-buyer-name" name="buyerName" required>
                            </div>

                            <div class="form-group">
                                <label for="tmv2-buyer-dni">DNI/NIE *</label>
                                <input type="text" id="tmv2-buyer-dni" name="buyerDni" placeholder="12345678A" required>
                            </div>
                        </div>

                        <div class="form-compact-row">
                            <div class="form-group">
                                <label for="tmv2-buyer-email">Email *</label>
                                <input type="email" id="tmv2-buyer-email" name="buyerEmail" required>
                            </div>

                            <div class="form-group">
                                <label for="tmv2-buyer-phone">Teléfono *</label>
                                <input type="tel" id="tmv2-buyer-phone" name="buyerPhone" placeholder="600123456" required>
                            </div>
                        </div>

                        <h3 style="color: #dc2626; margin: 30px 0 20px 0;">Vendedor (Propietario actual)</h3>
                        
                        <div class="form-compact-row">
                            <div class="form-group">
                                <label for="tmv2-seller-name">Nombre completo *</label>
                                <input type="text" id="tmv2-seller-name" name="sellerName" required>
                            </div>

                            <div class="form-group">
                                <label for="tmv2-seller-dni">DNI/NIE *</label>
                                <input type="text" id="tmv2-seller-dni" name="sellerDni" placeholder="87654321B" required>
                            </div>
                        </div>

                        <div style="display: flex; gap: 15px; margin-top: 30px;">
                            <button type="button" class="btn btn-secondary" onclick="tmv2_prevStep(1)">
                                ← Anterior
                            </button>
                            <button type="button" class="btn btn-primary" onclick="tmv2_nextStep(3)">
                                Siguiente: Documentos →
                            </button>
                        </div>
                    </div>

                    <!-- PASO 3: DOCUMENTOS (estructura idéntica a TBV2) -->
                    <div class="form-page hidden" data-step="3">
                        <h2>📄 Documentos Requeridos</h2>
                        
                        <!-- Upload grid profesional idéntico a TBV2 -->
                        <div class="upload-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin: 20px 0;">
                            
                            <!-- DNI Comprador -->
                            <div class="upload-item" style="background: white; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px;">
                                <label for="tmv2-dni-comprador" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                                    <div>
                                        <strong>🆔 DNI Comprador</strong>
                                        <small style="display: block; color: #6b7280;">Documento Nacional de Identidad (ambas caras)</small>
                                    </div>
                                    <span class="view-example" data-doc="dni-comprador" style="color: #016d86; text-decoration: underline; font-size: 12px; cursor: pointer; font-weight: 500; padding: 4px 8px; background: #f0f9ff; border-radius: 4px;">Ver ejemplo</span>
                                </label>
                                <div class="upload-wrapper">
                                    <input type="file" id="tmv2-dni-comprador" name="dniComprador[]" multiple accept=".pdf,.jpg,.jpeg,.png" required style="display: none;">
                                    <div class="upload-button upload-button-responsive" style="background: #f8fafc; border: 2px dashed #d1d5db; border-radius: 8px; padding: 20px; text-align: center; cursor: pointer; transition: all 0.2s ease;" onclick="document.getElementById('tmv2-dni-comprador').click();">
                                        <span class="desktop-text"><i class="fa-solid fa-upload"></i> Seleccionar archivos</span>
                                        <span class="mobile-text"><i class="fa-solid fa-camera"></i> Foto/Archivo</span>
                                    </div>
                                    <div class="file-count" data-input="tmv2-dni-comprador" style="margin-top: 8px; font-size: 14px; color: #6b7280;">Sin archivos</div>
                                    <div class="file-preview-container" data-input="tmv2-dni-comprador" style="margin-top: 12px;"></div>
                                </div>
                            </div>

                            <!-- DNI Vendedor -->
                            <div class="upload-item" style="background: white; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px;">
                                <label for="tmv2-dni-vendedor" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                                    <div>
                                        <strong>🆔 DNI Vendedor</strong>
                                        <small style="display: block; color: #6b7280;">Documento Nacional de Identidad (ambas caras)</small>
                                    </div>
                                    <span class="view-example" data-doc="dni-vendedor" style="color: #016d86; text-decoration: underline; font-size: 12px; cursor: pointer; font-weight: 500; padding: 4px 8px; background: #f0f9ff; border-radius: 4px;">Ver ejemplo</span>
                                </label>
                                <div class="upload-wrapper">
                                    <input type="file" id="tmv2-dni-vendedor" name="dniVendedor[]" multiple accept=".pdf,.jpg,.jpeg,.png" required style="display: none;">
                                    <div class="upload-button upload-button-responsive" style="background: #f8fafc; border: 2px dashed #d1d5db; border-radius: 8px; padding: 20px; text-align: center; cursor: pointer; transition: all 0.2s ease;" onclick="document.getElementById('tmv2-dni-vendedor').click();">
                                        <span class="desktop-text"><i class="fa-solid fa-upload"></i> Seleccionar archivos</span>
                                        <span class="mobile-text"><i class="fa-solid fa-camera"></i> Foto/Archivo</span>
                                    </div>
                                    <div class="file-count" data-input="tmv2-dni-vendedor" style="margin-top: 8px; font-size: 14px; color: #6b7280;">Sin archivos</div>
                                    <div class="file-preview-container" data-input="tmv2-dni-vendedor" style="margin-top: 12px;"></div>
                                </div>
                            </div>

                            <!-- Permiso Circulación -->
                            <div class="upload-item" style="background: white; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px;">
                                <label for="tmv2-permiso-circulacion" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                                    <div>
                                        <strong>📄 Permiso Circulación</strong>
                                        <small style="display: block; color: #6b7280;">Documento original de la moto de agua</small>
                                    </div>
                                    <span class="view-example" data-doc="permiso-circulacion" style="color: #016d86; text-decoration: underline; font-size: 12px; cursor: pointer; font-weight: 500; padding: 4px 8px; background: #f0f9ff; border-radius: 4px;">Ver ejemplo</span>
                                </label>
                                <div class="upload-wrapper">
                                    <input type="file" id="tmv2-permiso-circulacion" name="permisoCirculacion[]" multiple accept=".pdf,.jpg,.jpeg,.png" required style="display: none;">
                                    <div class="upload-button upload-button-responsive" style="background: #f8fafc; border: 2px dashed #d1d5db; border-radius: 8px; padding: 20px; text-align: center; cursor: pointer; transition: all 0.2s ease;" onclick="document.getElementById('tmv2-permiso-circulacion').click();">
                                        <span class="desktop-text"><i class="fa-solid fa-upload"></i> Seleccionar archivos</span>
                                        <span class="mobile-text"><i class="fa-solid fa-camera"></i> Foto/Archivo</span>
                                    </div>
                                    <div class="file-count" data-input="tmv2-permiso-circulacion" style="margin-top: 8px; font-size: 14px; color: #6b7280;">Sin archivos</div>
                                    <div class="file-preview-container" data-input="tmv2-permiso-circulacion" style="margin-top: 12px;"></div>
                                </div>
                            </div>

                            <!-- Contrato Compraventa -->
                            <div class="upload-item" style="background: white; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px;">
                                <label for="tmv2-contrato" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                                    <div>
                                        <strong>📋 Contrato Compraventa</strong>
                                        <small style="display: block; color: #6b7280;">Opcional. Si no lo tienes, te ayudamos a generarlo</small>
                                    </div>
                                    <span class="view-example" data-doc="contrato-compraventa" style="color: #016d86; text-decoration: underline; font-size: 12px; cursor: pointer; font-weight: 500; padding: 4px 8px; background: #f0f9ff; border-radius: 4px;">Ver ejemplo</span>
                                </label>
                                <div class="upload-wrapper">
                                    <input type="file" id="tmv2-contrato" name="contratoCompraventa[]" multiple accept=".pdf,.jpg,.jpeg,.png" style="display: none;">
                                    <div class="upload-button upload-button-responsive" style="background: #f8fafc; border: 2px dashed #d1d5db; border-radius: 8px; padding: 20px; text-align: center; cursor: pointer; transition: all 0.2s ease;" onclick="document.getElementById('tmv2-contrato').click();">
                                        <span class="desktop-text"><i class="fa-solid fa-upload"></i> Seleccionar archivos</span>
                                        <span class="mobile-text"><i class="fa-solid fa-camera"></i> Foto/Archivo</span>
                                    </div>
                                    <div class="file-count" data-input="tmv2-contrato" style="margin-top: 8px; font-size: 14px; color: #6b7280;">Sin archivos</div>
                                    <div class="file-preview-container" data-input="tmv2-contrato" style="margin-top: 12px;"></div>
                                </div>
                            </div>

                            <!-- Firma Digital -->
                            <div class="upload-item" style="background: white; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px;">
                                <label for="tmv2-signature" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                                    <div>
                                        <strong>✍️ Firma Digital</strong>
                                        <small style="display: block; color: #6b7280;">Firma la autorización para tramitar</small>
                                    </div>
                                </label>
                                <div class="upload-wrapper">
                                    <div id="tmv2-signature-field" class="upload-button upload-button-responsive" style="background: #f0f9ff; border: 2px dashed #016d86; border-radius: 8px; padding: 20px; text-align: center; cursor: pointer; transition: all 0.2s ease;">
                                        <span class="desktop-text"><i class="fa-solid fa-signature"></i> Firmar documentos</span>
                                        <span class="mobile-text"><i class="fa-solid fa-signature"></i> Firmar</span>
                                    </div>
                                    <div class="file-count" id="tmv2-signature-status" style="margin-top: 8px; font-size: 14px; color: #6b7280;">Pendiente de firma</div>
                                </div>
                            </div>

                        </div>

                        <div style="display: flex; gap: 15px; margin-top: 30px;">
                            <button type="button" class="btn btn-secondary" onclick="tmv2_prevStep(2)">
                                ← Anterior
                            </button>
                            <button type="button" class="btn btn-primary" onclick="tmv2_nextStep(4)">
                                Siguiente: Pago →
                            </button>
                        </div>
                    </div>

                    <!-- PASO 4: PAGO (estructura idéntica a TBV2) -->
                    <div class="form-page hidden" data-step="4">
                        <h2>💳 Resumen y Pago</h2>
                        
                        <div style="background: #f8fafc; padding: 20px; border-radius: 8px; margin: 20px 0;">
                            <h3 style="margin: 0 0 15px 0; color: #016d86;">Resumen del Trámite</h3>
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
                            <button type="button" class="btn btn-secondary" onclick="tmv2_prevStep(3)">
                                ← Anterior
                            </button>
                            <button type="button" class="btn btn-primary" onclick="tmv2_processPayment()" style="background: #059669;">
                                💳 Pagar y Procesar Trámite
                            </button>
                        </div>
                    </div>

                </div> <!-- .tramitfy-main-form -->

            </div> <!-- .tramitfy-two-column -->
        </div> <!-- .tramitfy-layout-wrapper -->

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
            this.setupSidebarContent();
            this.updateSummary();
        },

        setupSidebarContent() {
            this.updateSidebarForStep(this.currentStep);
        },

        updateSidebarForStep(step) {
            const sidebarContent = document.getElementById('tmv2-sidebar-dynamic-content');
            if (!sidebarContent) return;

            switch(step) {
                case 1:
                    sidebarContent.innerHTML = this.getVehiculoSidebarContent();
                    break;
                case 2:
                    sidebarContent.innerHTML = this.getDatosSidebarContent();
                    break;
                case 3:
                    sidebarContent.innerHTML = this.getDocumentosSidebarContent();
                    break;
                case 4:
                    sidebarContent.innerHTML = this.getPagoSidebarContent();
                    break;
            }
        },

        getVehiculoSidebarContent() {
            return `
            <div style="padding: 0;">
                <h3 style="color: white; font-size: 32px; margin: 0 0 16px 0; font-weight: 700; line-height: 1.2;">
                    Cambio de Nombre Moto de Agua
                </h3>
                
                <!-- Cuadro de precio más discreto -->
                <div style="background: rgba(255,255,255,0.08); border-radius: 12px; padding: 15px; text-align: center; border: 1px solid rgba(255,255,255,0.15); margin-bottom: 20px;">
                    <div style="font-size: 11px; opacity: 0.8; text-transform: uppercase; letter-spacing: 1.2px; margin-bottom: 8px; color: rgba(255,255,255,0.85); font-weight: 500;">PRECIO TOTAL</div>
                    <div style="font-size: 32px; font-weight: 600; margin: 4px 0; color: rgba(255,255,255,0.95);">89.00€</div>
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
            const customerName = document.getElementById('buyerName')?.value || '';
            const customerDni = document.getElementById('buyerDni')?.value || '';
            const customerEmail = document.getElementById('buyerEmail')?.value || '';
            const customerPhone = document.getElementById('buyerPhone')?.value || '';
    
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
                ${personalInfo}
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
                    Sube los documentos requeridos para completar la transferencia de tu moto de agua.
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
                        <span style="color: rgba(255,255,255,0.85); font-size: 12px;">Permiso de circulación</span>
                    </div>
                    
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-file-alt" style="color: #10b981; font-size: 14px;"></i>
                        <span style="color: rgba(255,255,255,0.85); font-size: 12px;">Contrato de compraventa (opcional)</span>
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
                <div style="background: rgba(255,255,255,0.05); border-left: 3px solid rgba(255,255,255,0.3); padding: 12px;">
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
            const basePrice = 89.00;
            const totalAmount = basePrice;
            
            // Get customer data
            const customerName = document.getElementById('buyerName')?.value || '';
            const vehicleBrand = document.getElementById('manufacturer')?.value || '';
            const vehicleModel = document.getElementById('model')?.value || '';
            
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
                        <span style="color: white; font-weight: 600; font-size: 14px;">${basePrice.toFixed(2)} €</span>
                    </div>
                    
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

        updateSummary() {
            // Actualizar resumen dinámicamente
            const summaryEl = document.getElementById('tmv2-summary');
            if (summaryEl) {
                summaryEl.innerHTML = `
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                        <span>Transferencia Moto de Agua:</span>
                        <strong>89.00€</strong>
                    </div>
                    <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 15px 0;">
                    <div style="display: flex; justify-content: space-between; font-size: 18px; font-weight: bold; color: #016d86;">
                        <span>Total:</span>
                        <span>89.00€</span>
                    </div>
                `;
            }
        }
    };

    // Funciones de navegación TMV2 (idénticas a TBV2)
    function tmv2_nextStep(step) {
        // Validar paso actual
        if (!tmv2_validateStep(TMV2_System.currentStep)) {
            return;
        }

        // Cambiar a siguiente paso (usar clases de TBV2)
        document.querySelectorAll('.form-page').forEach(el => {
            el.classList.remove('active');
            el.classList.add('hidden');
        });
        document.querySelectorAll('.nav-tab').forEach(el => el.classList.remove('active'));

        const targetPage = document.querySelector(`.form-page[data-step="${step}"]`);
        const targetTab = document.querySelector(`.nav-tab[data-step="${step}"]`);
        
        if (targetPage && targetTab) {
            targetPage.classList.add('active');
            targetPage.classList.remove('hidden');
            targetTab.classList.add('active');
        }

        TMV2_System.currentStep = step;
        TMV2_System.updateSummary();

        // Scroll to top (usar clase de TBV2)
        document.querySelector('.tramitfy-main-form').scrollIntoView({ behavior: 'smooth' });
    }

    function tmv2_prevStep(step) {
        tmv2_nextStep(step);
    }

    function tmv2_validateStep(step) {
        const stepEl = document.querySelector(`.form-page[data-step="${step}"]`);
        if (!stepEl) return true;
        
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

        // Preparar datos del formulario para Redsys
        const formData = new FormData(document.getElementById('tmv2-transferencia-form'));
        
        // Mostrar loading
        const payButton = event.target;
        const originalText = payButton.innerHTML;
        payButton.innerHTML = '🔄 Procesando pago...';
        payButton.disabled = true;

        // Crear payment con Redsys
        fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'tmv2_create_redsys_payment',
                nonce: '<?php echo wp_create_nonce("tmv2_payment_nonce"); ?>',
                manufacturer: formData.get('manufacturer'),
                model: formData.get('model'),
                year: formData.get('year'),
                matricula: formData.get('matricula'),
                purchasePrice: formData.get('purchasePrice'),
                buyerName: formData.get('buyerName'),
                buyerDni: formData.get('buyerDni'),
                buyerEmail: formData.get('buyerEmail'),
                buyerPhone: formData.get('buyerPhone'),
                sellerName: formData.get('sellerName'),
                sellerDni: formData.get('sellerDni')
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Redireccionar a Redsys
                document.body.innerHTML = data.data.redsys_form;
                setTimeout(() => {
                    document.getElementById('tmv2-redsys-form').submit();
                }, 500);
            } else {
                alert('Error al procesar el pago: ' + (data.data || 'Error desconocido'));
                payButton.innerHTML = originalText;
                payButton.disabled = false;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error de conexión al procesar el pago');
            payButton.innerHTML = originalText;
            payButton.disabled = false;
        });
    }

    // ============================================
    // TMV2 - SISTEMA DE ARCHIVOS MÚLTIPLES (IDÉNTICO A TBV2)
    // ============================================

    // Sistema de file handling idéntico a TBV2
    const TMV2_FileHandler = {
        init() {
            this.setupFileInputs();
            this.setupExampleLinks();
            this.setupSignatureField();
        },

        setupFileInputs() {
            const fileInputs = document.querySelectorAll('input[type="file"]');
            fileInputs.forEach(input => {
                input.addEventListener('change', (e) => this.handleFileSelection(e));
            });
        },

        setupExampleLinks() {
            const exampleLinks = document.querySelectorAll('.view-example');
            exampleLinks.forEach(link => {
                link.addEventListener('click', (e) => {
                    e.preventDefault();
                    const docType = e.target.getAttribute('data-doc');
                    this.showExampleModal(docType);
                });
            });
        },

        setupSignatureField() {
            const signatureField = document.getElementById('tmv2-signature-field');
            if (signatureField) {
                signatureField.addEventListener('click', () => this.openSignatureModal());
            }
        },

        handleFileSelection(event) {
            const input = event.target;
            const files = input.files;
            const inputId = input.id;
            const countElement = document.querySelector(`[data-input="${inputId}"]`);
            const previewContainer = document.querySelector(`[data-input="${inputId}"].file-preview-container`);

            if (countElement) {
                if (files.length === 0) {
                    countElement.textContent = 'Sin archivos';
                    countElement.style.color = '#6b7280';
                } else if (files.length === 1) {
                    countElement.textContent = `1 archivo seleccionado`;
                    countElement.style.color = '#059669';
                } else {
                    countElement.textContent = `${files.length} archivos seleccionados`;
                    countElement.style.color = '#059669';
                }
            }

            // Preview simple de archivos
            if (previewContainer && files.length > 0) {
                previewContainer.innerHTML = '';
                Array.from(files).slice(0, 3).forEach((file, index) => {
                    const preview = document.createElement('div');
                    preview.style.cssText = `
                        display: inline-flex; 
                        align-items: center; 
                        background: #f0f9ff; 
                        padding: 8px 12px; 
                        border-radius: 6px; 
                        margin: 4px 4px 0 0; 
                        font-size: 12px;
                        border: 1px solid #e0f2fe;
                    `;
                    
                    const icon = file.type.includes('pdf') ? '📄' : '🖼️';
                    const name = file.name.length > 20 ? file.name.substring(0, 20) + '...' : file.name;
                    preview.innerHTML = `${icon} ${name}`;
                    previewContainer.appendChild(preview);
                });

                if (files.length > 3) {
                    const moreDiv = document.createElement('div');
                    moreDiv.style.cssText = `
                        display: inline-flex; 
                        align-items: center; 
                        color: #6b7280; 
                        font-size: 12px; 
                        margin: 4px 0 0 4px;
                    `;
                    moreDiv.textContent = `+${files.length - 3} más`;
                    previewContainer.appendChild(moreDiv);
                }
            }
        },

        showExampleModal(docType) {
            // Sistema de modales de ejemplo idéntico a TBV2
            const modalId = 'tmv2-example-modal';
            let modal = document.getElementById(modalId);
            
            if (!modal) {
                modal = document.createElement('div');
                modal.id = modalId;
                modal.style.cssText = `
                    position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
                    background: rgba(0,0,0,0.8); z-index: 10000; display: none;
                    justify-content: center; align-items: center; padding: 20px;
                `;
                modal.innerHTML = `
                    <div style="background: white; border-radius: 12px; max-width: 600px; width: 100%; max-height: 90vh; overflow-y: auto; position: relative;">
                        <div style="padding: 24px; border-bottom: 1px solid #e5e7eb;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <h3 style="margin: 0; color: #016d86; font-size: 20px;">Ejemplo de Documento</h3>
                                <button onclick="document.getElementById('${modalId}').style.display='none'" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #6b7280;">&times;</button>
                            </div>
                        </div>
                        <div id="${modalId}-content" style="padding: 24px;">
                            <!-- Contenido dinámico -->
                        </div>
                    </div>
                `;
                document.body.appendChild(modal);
            }

            // Contenido específico por tipo de documento
            const content = this.getExampleContent(docType);
            document.getElementById(`${modalId}-content`).innerHTML = content;
            modal.style.display = 'flex';
        },

        getExampleContent(docType) {
            const examples = {
                'dni-comprador': `
                    <div style="text-align: center;">
                        <div style="background: #f0f9ff; padding: 20px; border-radius: 8px; margin-bottom: 16px;">
                            <h4 style="margin: 0 0 12px 0; color: #016d86;">DNI/NIE del Comprador</h4>
                            <p style="margin: 0; color: #374151; line-height: 1.5;">
                                📸 Foto clara de <strong>ambas caras</strong> del documento<br>
                                ✅ Sin brillos ni sombras<br>
                                ✅ Todos los datos legibles
                            </p>
                        </div>
                        <img src="https://tramitfy.es/wp-content/uploads/exampledocs/dni-placeholder.jpg" alt="Ejemplo DNI" style="max-width: 100%; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);" onerror="this.style.display='none'">
                    </div>
                `,
                'dni-vendedor': `
                    <div style="text-align: center;">
                        <div style="background: #f0f9ff; padding: 20px; border-radius: 8px; margin-bottom: 16px;">
                            <h4 style="margin: 0 0 12px 0; color: #016d86;">DNI/NIE del Vendedor</h4>
                            <p style="margin: 0; color: #374151; line-height: 1.5;">
                                📸 Foto clara de <strong>ambas caras</strong> del documento<br>
                                ✅ Sin brillos ni sombras<br>
                                ✅ Todos los datos legibles
                            </p>
                        </div>
                        <img src="https://tramitfy.es/wp-content/uploads/exampledocs/dni-placeholder.jpg" alt="Ejemplo DNI" style="max-width: 100%; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);" onerror="this.style.display='none'">
                    </div>
                `,
                'permiso-circulacion': `
                    <div style="text-align: center;">
                        <div style="background: #f0f9ff; padding: 20px; border-radius: 8px; margin-bottom: 16px;">
                            <h4 style="margin: 0 0 12px 0; color: #016d86;">Permiso de Circulación</h4>
                            <p style="margin: 0; color: #374151; line-height: 1.5;">
                                📄 Documento original de la moto de agua<br>
                                ✅ Con datos del propietario actual<br>
                                ✅ Matrícula clara y legible
                            </p>
                        </div>
                        <img src="https://tramitfy.es/wp-content/uploads/exampledocs/permiso-moto-placeholder.jpg" alt="Ejemplo Permiso" style="max-width: 100%; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);" onerror="this.style.display='none'">
                    </div>
                `,
                'contrato-compraventa': `
                    <div style="text-align: center;">
                        <div style="background: #f0f9ff; padding: 20px; border-radius: 8px; margin-bottom: 16px;">
                            <h4 style="margin: 0 0 12px 0; color: #016d86;">Contrato de Compraventa</h4>
                            <p style="margin: 0; color: #374151; line-height: 1.5;">
                                📋 Contrato firmado entre comprador y vendedor<br>
                                ✅ Con datos de ambas partes<br>
                                ✅ Precio y fecha de venta<br>
                                <small style="color: #6b7280;">Si no tienes, nosotros te ayudamos a generarlo</small>
                            </p>
                        </div>
                        <img src="https://tramitfy.es/wp-content/uploads/exampledocs/contrato-placeholder.jpg" alt="Ejemplo Contrato" style="max-width: 100%; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);" onerror="this.style.display='none'">
                    </div>
                `
            };
            return examples[docType] || '<p>Ejemplo no disponible</p>';
        },

        openSignatureModal() {
            alert('🚧 Sistema de firma digital en desarrollo.\n\nPróximamente disponible.');
        }
    };

    // Inicializar sistemas cuando el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            TMV2_System.init();
            TMV2_FileHandler.init();
        });
    } else {
        TMV2_System.init();
        TMV2_FileHandler.init();
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
// 🚀 FUNCIONES AJAX TMV2 - SISTEMA DE PAGOS
// ============================================

/**
 * AJAX: Crear pago Redsys para TMV2
 */
function tmv2_create_redsys_payment() {
    // Verificar nonce
    if (!wp_verify_nonce($_POST['nonce'], 'tmv2_payment_nonce')) {
        wp_die('Error de seguridad');
    }
    
    // Protección adicional
    if (!tmv2_is_page_authorized()) {
        wp_send_json_error('No autorizado para TMV2');
        return;
    }

    try {
        // Recibir datos del formulario
        $manufacturer = sanitize_text_field($_POST['manufacturer']);
        $model = sanitize_text_field($_POST['model']);
        $year = sanitize_text_field($_POST['year']);
        $matricula = sanitize_text_field($_POST['matricula']);
        $purchasePrice = floatval($_POST['purchasePrice']);
        $buyerName = sanitize_text_field($_POST['buyerName']);
        $buyerDni = sanitize_text_field($_POST['buyerDni']);
        $buyerEmail = sanitize_email($_POST['buyerEmail']);
        $buyerPhone = sanitize_text_field($_POST['buyerPhone']);
        $sellerName = sanitize_text_field($_POST['sellerName']);
        $sellerDni = sanitize_text_field($_POST['sellerDni']);

        // Validaciones básicas
        if (empty($manufacturer) || empty($buyerEmail) || empty($buyerName)) {
            wp_send_json_error('Faltan campos obligatorios');
            return;
        }

        // Calcular precio final TMV2 (fijo 89.00€)
        $finalAmount = 89.00;
        $finalAmountCents = $finalAmount * 100; // Redsys trabaja en centimos

        // Generar OrderID único (12 dígitos)
        $orderId = substr(time() . rand(100, 999), 0, 12);

        // Preparar datos para almacenamiento
        $transferData = [
            'orderId' => $orderId,
            'tramiteType' => 'transferencia-moto-v2',
            'moto' => [
                'manufacturer' => $manufacturer,
                'model' => $model,
                'year' => $year,
                'matricula' => $matricula,
                'purchasePrice' => $purchasePrice
            ],
            'customer' => [
                'name' => $buyerName,
                'dni' => $buyerDni,
                'email' => $buyerEmail,
                'phone' => $buyerPhone
            ],
            'seller' => [
                'name' => $sellerName,
                'dni' => $sellerDni
            ],
            'payment' => [
                'amount' => $finalAmount,
                'currency' => 'EUR'
            ],
            'timestamp' => time()
        ];

        // Guardar en sessionStorage + WordPress transient (método híbrido como TBV2)
        $transient_key = 'tmv2_transfer_' . $orderId;
        set_transient($transient_key, $transferData, 7200); // 2 horas

        // Preparar parámetros Redsys
        $redsysParams = [
            'Ds_Merchant_Amount' => $finalAmountCents,
            'Ds_Merchant_Order' => $orderId,
            'Ds_Merchant_MerchantCode' => TMV2_REDSYS_MERCHANT_CODE,
            'Ds_Merchant_Currency' => TMV2_REDSYS_CURRENCY,
            'Ds_Merchant_TransactionType' => '0',
            'Ds_Merchant_Terminal' => TMV2_REDSYS_TERMINAL,
            'Ds_Merchant_MerchantURL' => TMV2_REDSYS_URL_NOTIFICATION,
            'Ds_Merchant_UrlOK' => TMV2_REDSYS_URL_OK,
            'Ds_Merchant_UrlKO' => TMV2_REDSYS_URL_KO
        ];

        // Codificar parámetros
        $merchantParameters = base64_encode(json_encode($redsysParams));

        // Generar firma (simplificada para este ejemplo)
        $signature = base64_encode(hash_hmac('sha256', $merchantParameters, TMV2_REDSYS_SECRET_KEY, true));

        // URL Redsys según modo
        global $tmv2_redsys_url;
        
        // Crear formulario Redsys
        $redsysForm = '
        <form id="tmv2-redsys-form" action="' . $tmv2_redsys_url . '" method="POST" style="display:none;">
            <input type="hidden" name="Ds_SignatureVersion" value="' . TMV2_REDSYS_SIGNATURE_VERSION . '">
            <input type="hidden" name="Ds_MerchantParameters" value="' . $merchantParameters . '">
            <input type="hidden" name="Ds_Signature" value="' . $signature . '">
        </form>
        <div style="text-align: center; padding: 40px; font-family: -apple-system, sans-serif;">
            <h3>🔄 Redirigiendo al pago seguro...</h3>
            <p>No cierres esta ventana</p>
            <div style="margin: 20px 0;">
                <div style="width: 40px; height: 40px; border: 4px solid #f3f3f3; border-top: 4px solid #016d86; border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto;"></div>
            </div>
        </div>
        <style>
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        </style>';

        wp_send_json_success(['redsys_form' => $redsysForm]);

    } catch (Exception $e) {
        error_log("TMV2 PAYMENT ERROR: " . $e->getMessage());
        wp_send_json_error('Error interno del servidor');
    }
}

/**
 * AJAX: Callback Redsys para TMV2
 */
function tmv2_redsys_callback() {
    // Este endpoint será llamado por Redsys tras el pago
    error_log('🚤 TMV2 CALLBACK - Iniciado desde Redsys');
    
    // Obtener parámetros de Redsys (normalmente enviados por POST)
    $redsys_params = $_POST;
    
    if (isset($redsys_params['Ds_Order'])) {
        $orderId = $redsys_params['Ds_Order'];
        
        // Recuperar datos del transient
        $transient_key = 'tmv2_transfer_' . $orderId;
        $transferData = get_transient($transient_key);
        
        if ($transferData) {
            // Preparar datos para webhook API
            $webhookData = [
                'tramiteType' => 'transferencia-moto-v2',
                'customerName' => $transferData['customer']['name'],
                'customerDni' => $transferData['customer']['dni'],
                'customerEmail' => $transferData['customer']['email'],
                'customerPhone' => $transferData['customer']['phone'],
                'finalAmount' => $transferData['payment']['amount'],
                'vehicleData' => $transferData['moto'],
                'sellerData' => $transferData['seller'],
                'paymentIntentId' => $orderId,
                'redsys_data' => $redsys_params
            ];
            
            // Enviar a webhook API
            $webhook_response = wp_remote_post(TMV2_WEBHOOK_URL, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Origin' => 'https://tramitfy.es'
                ],
                'body' => json_encode($webhookData),
                'timeout' => 30
            ]);
            
            // Limpiar transient
            delete_transient($transient_key);
            
            error_log('🚤 TMV2 CALLBACK - Webhook enviado para OrderID: ' . $orderId);
        } else {
            error_log('🚤 TMV2 CALLBACK - No se encontraron datos para OrderID: ' . $orderId);
        }
    }
    
    // Responder a Redsys
    echo 'OK';
    exit;
}

// Registrar funciones AJAX
if (tmv2_is_page_authorized()) {
    add_action('wp_ajax_tmv2_create_redsys_payment', 'tmv2_create_redsys_payment');
    add_action('wp_ajax_nopriv_tmv2_create_redsys_payment', 'tmv2_create_redsys_payment');
    
    add_action('wp_ajax_tmv2_redsys_callback', 'tmv2_redsys_callback');
    add_action('wp_ajax_nopriv_tmv2_redsys_callback', 'tmv2_redsys_callback');
}

// ============================================
// 🔚 FIN TMV2 - SISTEMA INDEPENDIENTE
// ============================================