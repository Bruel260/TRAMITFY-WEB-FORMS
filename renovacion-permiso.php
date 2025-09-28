<?php
// Asegurarse de que el archivo no sea accedido directamente
defined('ABSPATH') || exit;

// Configuración de Stripe
define('STRIPE_MODE', 'live'); // 'test' o 'live'
define('STRIPE_TEST_PUBLIC_KEY', 'pk_test_REPLACE_WITH_YOUR_TEST_PUBLIC_KEY');
define('STRIPE_TEST_SECRET_KEY', 'sk_test_REPLACE_WITH_YOUR_TEST_SECRET_KEY');
define('STRIPE_LIVE_PUBLIC_KEY', 'pk_live_REPLACE_WITH_YOUR_LIVE_PUBLIC_KEY');
define('STRIPE_LIVE_SECRET_KEY', 'sk_live_REPLACE_WITH_YOUR_LIVE_SECRET_KEY');

// Seleccionar las claves según el modo
$stripe_public_key = (STRIPE_MODE === 'live') ? STRIPE_LIVE_PUBLIC_KEY : STRIPE_TEST_PUBLIC_KEY;
$stripe_secret_key = (STRIPE_MODE === 'live') ? STRIPE_LIVE_SECRET_KEY : STRIPE_TEST_SECRET_KEY;

// Precio del servicio (en euros)
define('SERVICE_PRICE', 65.00);

/**
 * Shortcode para el formulario de renovación de permiso de navegación
 */
function navigation_permit_renewal_form_shortcode() {
    global $stripe_public_key;
    
    // Encolar los scripts y estilos necesarios
    wp_enqueue_style('navigation-permit-renewal-form-style', get_template_directory_uri() . '/style.css', array(), filemtime(get_template_directory() . '/style.css'));
    wp_enqueue_script('stripe', 'https://js.stripe.com/v3/', array(), null, false);
    wp_enqueue_script('signature-pad', 'https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js', array(), null, false);

    // Iniciar el buffering de salida
    ob_start();
    ?>

    <!-- Font Awesome para iconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        /* Variables de color */
        :root {
            --primary: 1, 109, 134;
            --primary-dark: 0, 86, 106;
            --primary-light: 0, 125, 156;
            
            --neutral-50: 248, 249, 250;
            --neutral-100: 241, 243, 244;
            --neutral-200: 233, 236, 239;
            --neutral-300: 222, 226, 230;
            --neutral-400: 206, 212, 218;
            --neutral-500: 173, 181, 189;
            --neutral-600: 108, 117, 125;
            --neutral-700: 73, 80, 87;
            --neutral-800: 52, 58, 64;
            --neutral-900: 33, 37, 41;
            
            --success: 40, 167, 69;
            --warning: 243, 156, 18;
            --error: 231, 76, 60;
            --info: 0, 123, 255;
        }

        /* Reset y estilos globales */
        * {
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: rgb(var(--neutral-800));
        }

        /* Container principal - Grid de 2 columnas */
        .npn-container {
            max-width: 1400px;
            margin: 40px auto;
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
            display: grid;
            grid-template-columns: 420px 1fr;
            min-height: 800px;
        }

        /* SIDEBAR IZQUIERDO */
        .npn-sidebar {
            background: linear-gradient(180deg, rgb(var(--primary)) 0%, rgb(var(--primary-dark)) 100%);
            color: white;
            padding: 35px 28px;
            display: flex;
            flex-direction: column;
            gap: 20px;
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
        }

        .npn-logo {
            font-size: 26px;
            font-weight: 700;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .npn-logo i {
            font-size: 36px;
        }

        .npn-headline {
            font-size: 20px;
            font-weight: 600;
            line-height: 1.4;
            margin-bottom: 10px;
        }

        .npn-subheadline {
            font-size: 15px;
            opacity: 0.92;
            line-height: 1.6;
        }

        /* Caja de precio destacada */
        .npn-price-box {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border-radius: 14px;
            padding: 20px;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.25);
            margin: 10px 0;
        }

        .npn-price-label {
            font-size: 13px;
            opacity: 0.85;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            margin-bottom: 10px;
        }

        .npn-price-amount {
            font-size: 48px;
            font-weight: 700;
            margin: 8px 0;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .npn-price-detail {
            font-size: 14px;
            opacity: 0.88;
        }

        /* Lista de beneficios */
        .npn-benefits {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin: 15px 0;
        }

        .npn-benefit {
            display: flex;
            align-items: start;
            gap: 12px;
            font-size: 14px;
            line-height: 1.5;
        }

        .npn-benefit i {
            font-size: 18px;
            color: rgb(var(--success));
            background: white;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            margin-top: 2px;
        }

        /* Trust badges */
        .npn-trust-badges {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: auto;
            padding-top: 20px;
        }

        .npn-badge {
            background: rgba(255, 255, 255, 0.18);
            padding: 7px 14px;
            border-radius: 20px;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
            border: 1px solid rgba(255, 255, 255, 0.25);
            font-weight: 500;
        }

        .npn-badge i {
            font-size: 14px;
        }

        /* ÁREA PRINCIPAL DEL FORMULARIO */
        .npn-form-area {
            padding: 35px 45px;
            background: #fafbfc;
            overflow-y: auto;
            max-height: 100vh;
        }

        .npn-form-header {
            margin-bottom: 25px;
        }

        .npn-form-title {
            font-size: 28px;
            font-weight: 700;
            color: rgb(var(--neutral-900));
            margin-bottom: 8px;
        }

        .npn-form-subtitle {
            font-size: 16px;
            color: rgb(var(--neutral-600));
        }

        /* Panel de auto-rellenado para administradores */
        .npn-admin-panel {
            background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
            color: white;
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 12px rgba(14, 165, 233, 0.3);
        }

        .npn-admin-panel-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .npn-admin-panel-title {
            font-size: 14px;
            font-weight: 600;
            opacity: 0.95;
        }

        .npn-admin-panel-subtitle {
            font-size: 12px;
            opacity: 0.85;
        }

        .npn-admin-autofill-btn {
            padding: 10px 20px;
            background: white;
            color: #0ea5e9;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.2s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        }

        .npn-admin-autofill-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        /* Navegación modernizada */
        .npn-navigation {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
            padding: 8px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .npn-nav-item {
            flex: 1;
            padding: 14px 20px;
            text-align: center;
            background: #f8f9fa;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            color: rgb(var(--neutral-700));
            font-weight: 500;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border: 2px solid transparent;
        }

        .npn-nav-item i {
            font-size: 16px;
        }

        .npn-nav-item.active {
            background: linear-gradient(135deg, rgb(var(--primary)) 0%, rgb(var(--primary-dark)) 100%);
            color: white;
            border-color: rgb(var(--primary));
            box-shadow: 0 4px 12px rgba(var(--primary), 0.3);
        }

        .npn-nav-item:hover:not(.active) {
            background: #e9ecef;
            border-color: rgb(var(--primary-light));
        }

        /* Páginas del formulario */
        .npn-form-page {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        }

        .npn-form-page.hidden {
            display: none;
        }

        .npn-form-page h3 {
            font-size: 20px;
            font-weight: 600;
            color: rgb(var(--neutral-900));
            margin: 0 0 20px 0;
        }

        /* Inputs mejorados */
        .npn-input-group {
            margin-bottom: 20px;
        }

        .npn-input-group label {
            display: block;
            font-weight: 500;
            margin-bottom: 8px;
            color: rgb(var(--neutral-800));
            font-size: 14px;
        }

        .npn-input-group input[type="text"],
        .npn-input-group input[type="email"],
        .npn-input-group input[type="tel"],
        .npn-input-group input[type="file"],
        .npn-input-group select {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid rgb(var(--neutral-300));
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.2s ease;
            background: white;
        }

        .npn-input-group input:focus,
        .npn-input-group select:focus {
            outline: none;
            border-color: rgb(var(--primary));
            box-shadow: 0 0 0 3px rgba(var(--primary), 0.1);
        }

        /* Grid para inputs en 2 columnas */
        .npn-inputs-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        /* Upload section */
        .npn-upload-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }

        .npn-upload-item {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            border: 2px dashed rgb(var(--neutral-300));
            transition: all 0.3s ease;
        }

        .npn-upload-item:hover {
            border-color: rgb(var(--primary));
            background: rgba(var(--primary), 0.02);
        }

        .npn-upload-item label {
            display: block;
            font-weight: 600;
            margin-bottom: 10px;
            color: rgb(var(--neutral-800));
        }

        .npn-upload-item input[type="file"] {
            width: 100%;
            padding: 10px;
            border: none;
            background: white;
            border-radius: 6px;
        }

        .npn-upload-item .view-example {
            display: inline-block;
            margin-top: 8px;
            color: rgb(var(--primary));
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
        }

        .npn-upload-item .view-example:hover {
            text-decoration: underline;
        }

        /* Firma */
        .npn-signature-container {
            margin: 30px 0;
            text-align: center;
        }

        #signature-pad {
            border: 3px solid rgb(var(--primary));
            border-radius: 10px;
            width: 100%;
            max-width: 600px;
            height: 200px;
            cursor: crosshair;
            background: white;
            box-shadow: 0 2px 8px rgba(var(--primary), 0.15);
            margin: 0 auto;
        }

        .npn-signature-clear {
            margin-top: 15px;
            padding: 10px 24px;
            background: rgb(var(--neutral-500));
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .npn-signature-clear:hover {
            background: rgb(var(--neutral-600));
            transform: translateY(-1px);
        }

        /* Términos y condiciones */
        .npn-terms {
            margin: 25px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid rgb(var(--info));
        }

        .npn-terms label {
            display: flex;
            align-items: start;
            gap: 10px;
            cursor: pointer;
            font-size: 14px;
        }

        .npn-terms input[type="checkbox"] {
            margin-top: 3px;
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .npn-terms a {
            color: rgb(var(--primary));
            text-decoration: none;
            font-weight: 500;
        }

        .npn-terms a:hover {
            text-decoration: underline;
        }

        /* Botones de navegación */
        .npn-button-group {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        .npn-btn {
            flex: 1;
            padding: 14px 28px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .npn-btn-prev {
            background: rgb(var(--neutral-300));
            color: rgb(var(--neutral-800));
        }

        .npn-btn-prev:hover {
            background: rgb(var(--neutral-400));
            transform: translateY(-2px);
        }

        .npn-btn-next, .npn-btn-submit {
            background: linear-gradient(135deg, rgb(var(--primary)) 0%, rgb(var(--primary-dark)) 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(var(--primary), 0.3);
        }

        .npn-btn-next:hover, .npn-btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(var(--primary), 0.4);
        }

        /* Precio y pago */
        .npn-price-summary {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 12px;
            margin: 20px 0;
            border: 2px solid rgb(var(--neutral-200));
        }

        .npn-price-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            font-size: 15px;
        }

        .npn-price-row strong {
            color: rgb(var(--neutral-900));
        }

        .npn-price-total {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 2px solid rgb(var(--neutral-300));
            font-size: 20px;
            font-weight: 700;
            color: rgb(var(--primary));
        }

        /* Payment element de Stripe */
        #payment-element {
            margin: 25px 0;
            padding: 20px;
            background: white;
            border-radius: 10px;
            border: 2px solid rgb(var(--neutral-200));
        }

        /* Cupón */
        .npn-coupon-container {
            margin: 20px 0;
        }

        .npn-coupon-input {
            display: flex;
            gap: 10px;
        }

        #coupon_code {
            flex: 1;
            padding: 12px 16px;
            border: 2px solid rgb(var(--neutral-300));
            border-radius: 8px;
            font-size: 15px;
        }

        .npn-coupon-message {
            margin-top: 10px;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 14px;
        }

        .npn-coupon-message.success {
            background: rgba(var(--success), 0.1);
            color: rgb(var(--success));
            border: 1px solid rgba(var(--success), 0.3);
        }

        .npn-coupon-message.error {
            background: rgba(var(--error), 0.1);
            color: rgb(var(--error));
            border: 1px solid rgba(var(--error), 0.3);
        }

        /* Loading overlay */
        #loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.95);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }

        #loading-overlay.active {
            display: flex;
        }

        .npn-loading-spinner {
            width: 60px;
            height: 60px;
            border: 5px solid rgb(var(--neutral-300));
            border-top-color: rgb(var(--primary));
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .npn-container {
                grid-template-columns: 1fr;
                margin: 20px;
            }

            .npn-sidebar {
                position: relative;
                height: auto;
            }

            .npn-form-area {
                padding: 25px 20px;
            }

            .npn-inputs-row {
                grid-template-columns: 1fr;
            }

            .npn-navigation {
                flex-wrap: wrap;
            }

            .npn-nav-item {
                flex: 1 1 calc(50% - 8px);
                min-width: 140px;
            }
        }

        @media (max-width: 768px) {
            .npn-container {
                margin: 10px;
                border-radius: 12px;
            }

            .npn-form-title {
                font-size: 22px;
            }

            .npn-upload-grid {
                grid-template-columns: 1fr;
            }

            .npn-button-group {
                flex-direction: column;
            }

            #signature-pad {
                height: 150px;
            }
        }
    </style>

    <!-- Container principal con layout de 2 columnas -->
    <div class="npn-container">
        
        <!-- SIDEBAR IZQUIERDO -->
        <div class="npn-sidebar">
            <div class="npn-logo">
                <i class="fa-solid fa-ship"></i>
                <span>Tramitfy</span>
            </div>

            <div>
                <div class="npn-headline">
                    Renovación Permiso de Navegación
                </div>
                <div class="npn-subheadline">
                    Renueva tu permiso de navegación de forma rápida y segura. Gestión completa online sin desplazamientos.
                </div>
            </div>

            <div class="npn-price-box">
                <div class="npn-price-label">Precio Total</div>
                <div class="npn-price-amount">65€</div>
                <div class="npn-price-detail">IVA incluido · Pago único</div>
            </div>

            <div class="npn-benefits">
                <div class="npn-benefit">
                    <i class="fa-solid fa-check"></i>
                    <span>Certificado de navegabilidad incluido</span>
                </div>
                <div class="npn-benefit">
                    <i class="fa-solid fa-check"></i>
                    <span>Emisión oficial del nuevo permiso</span>
                </div>
                <div class="npn-benefit">
                    <i class="fa-solid fa-check"></i>
                    <span>Gestión completa ante autoridades</span>
                </div>
                <div class="npn-benefit">
                    <i class="fa-solid fa-check"></i>
                    <span>Tramitación rápida en 5-7 días</span>
                </div>
                <div class="npn-benefit">
                    <i class="fa-solid fa-check"></i>
                    <span>Seguimiento online en tiempo real</span>
                </div>
            </div>

            <div class="npn-trust-badges">
                <div class="npn-badge">
                    <i class="fa-solid fa-shield-halved"></i>
                    <span>Pago seguro</span>
                </div>
                <div class="npn-badge">
                    <i class="fa-solid fa-lock"></i>
                    <span>Datos protegidos</span>
                </div>
                <div class="npn-badge">
                    <i class="fa-solid fa-headset"></i>
                    <span>Soporte 24/7</span>
                </div>
            </div>
        </div>

        <!-- ÁREA PRINCIPAL DEL FORMULARIO -->
        <div class="npn-form-area">
            <form id="navigation-permit-renewal-form" action="" method="POST" enctype="multipart/form-data">
                
                <div class="npn-form-header">
                    <h1 class="npn-form-title">Solicitud de Renovación</h1>
                    <p class="npn-form-subtitle">Complete el formulario para renovar su permiso de navegación</p>
                </div>

                <!-- Panel de auto-rellenado para administradores -->
                <?php if (current_user_can('administrator')): ?>
                <div class="npn-admin-panel">
                    <div class="npn-admin-panel-info">
                        <div class="npn-admin-panel-title">🔧 Modo Administrador</div>
                        <div class="npn-admin-panel-subtitle">Auto-relleno disponible para testing</div>
                    </div>
                    <button type="button" id="admin-autofill-btn" class="npn-admin-autofill-btn">
                        ⚡ Auto-rellenar
                    </button>
                </div>
                <?php endif; ?>

                <!-- Navegación del formulario -->
                <nav class="npn-navigation">
                    <a href="#" class="npn-nav-item active" data-page-id="page-personal-info">
                        <i class="fa-solid fa-user"></i>
                        <span>Datos Personales</span>
                    </a>
                    <a href="#" class="npn-nav-item" data-page-id="page-documents">
                        <i class="fa-solid fa-file-alt"></i>
                        <span>Documentación</span>
                    </a>
                    <a href="#" class="npn-nav-item" data-page-id="page-payment">
                        <i class="fa-solid fa-credit-card"></i>
                        <span>Pago</span>
                    </a>
                </nav>

                <!-- Loading overlay -->
                <div id="loading-overlay">
                    <div class="npn-loading-spinner"></div>
                </div>

                <!-- PÁGINA 1: Datos Personales -->
                <div id="page-personal-info" class="npn-form-page">
                    <h3><i class="fa-solid fa-user"></i> Datos Personales</h3>

                    <div class="npn-inputs-row">
                        <div class="npn-input-group">
                            <label for="customer_name">Nombre y Apellidos *</label>
                            <input type="text" id="customer_name" name="customer_name" placeholder="Juan García López" required />
                        </div>

                        <div class="npn-input-group">
                            <label for="customer_dni">DNI/NIE *</label>
                            <input type="text" id="customer_dni" name="customer_dni" placeholder="12345678A" required />
                        </div>
                    </div>

                    <div class="npn-inputs-row">
                        <div class="npn-input-group">
                            <label for="customer_email">Correo Electrónico *</label>
                            <input type="email" id="customer_email" name="customer_email" placeholder="ejemplo@email.com" required />
                        </div>

                        <div class="npn-input-group">
                            <label for="customer_phone">Teléfono *</label>
                            <input type="tel" id="customer_phone" name="customer_phone" placeholder="600 123 456" required />
                        </div>
                    </div>

                    <div class="npn-input-group">
                        <label for="renewal_type">Tipo de Renovación *</label>
                        <select id="renewal_type" name="renewal_type" required>
                            <option value="">Seleccione una opción</option>
                            <option value="caducidad">Renovación por caducidad</option>
                            <option value="perdida">Renovación por pérdida/extravío</option>
                        </select>
                    </div>

                    <div class="npn-button-group">
                        <button type="button" class="npn-btn npn-btn-next" data-next="page-documents">
                            Siguiente <i class="fa-solid fa-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <!-- PÁGINA 2: Documentación -->
                <div id="page-documents" class="npn-form-page hidden">
                    <h3><i class="fa-solid fa-file-alt"></i> Documentación Requerida</h3>

                    <p style="color: rgb(var(--neutral-600)); margin-bottom: 20px;">
                        Por favor, adjunte los siguientes documentos en formato PDF, JPG o PNG.
                    </p>

                    <div class="npn-upload-grid">
                        <div class="npn-upload-item">
                            <label for="upload-dni-propietario">
                                <i class="fa-solid fa-id-card"></i> DNI del Propietario *
                            </label>
                            <input type="file" id="upload-dni-propietario" name="upload_dni_propietario" accept="image/*,.pdf" required>
                            <a href="#" class="view-example" data-doc="dni">Ver ejemplo</a>
                        </div>

                        <div class="npn-upload-item">
                            <label for="upload-hoja-asiento">
                                <i class="fa-solid fa-file-lines"></i> Registro Marítimo *
                            </label>
                            <input type="file" id="upload-hoja-asiento" name="upload_hoja_asiento" accept="image/*,.pdf" required>
                            <a href="#" class="view-example" data-doc="registro">Ver ejemplo</a>
                        </div>

                        <div class="npn-upload-item" id="permiso-caducado-section">
                            <label for="upload-permiso-caducado">
                                <i class="fa-solid fa-file-circle-xmark"></i> Permiso a Renovar *
                            </label>
                            <input type="file" id="upload-permiso-caducado" name="upload_permiso_caducado" accept="image/*,.pdf" required>
                            <a href="#" class="view-example" data-doc="permiso">Ver ejemplo</a>
                        </div>
                    </div>

                    <h3 style="margin-top: 30px;"><i class="fa-solid fa-signature"></i> Autorización y Firma</h3>
                    
                    <div id="authorization-document" style="background:#f8f9fa; padding:20px; border-radius:8px; margin:20px 0; font-size:14px; line-height:1.6;">
                        <p><strong>AUTORIZACIÓN PARA TRAMITACIÓN</strong></p>
                        <p>Mediante la presente, autorizo a TRAMITFY para que, en mi nombre y representación, gestione ante las autoridades competentes la renovación de mi permiso de navegación, comprometiéndome a aportar toda la documentación necesaria y a abonar las tasas correspondientes.</p>
                    </div>

                    <div class="npn-signature-container">
                        <canvas id="signature-pad" width="600" height="200"></canvas>
                        <button type="button" class="npn-signature-clear" id="clear-signature">
                            <i class="fa-solid fa-eraser"></i> Limpiar Firma
                        </button>
                    </div>

                    <div class="npn-terms">
                        <label>
                            <input type="checkbox" name="terms_accept" required>
                            <span>Acepto los <a href="https://tramitfy.es/terminos-y-condiciones-de-uso/" target="_blank">términos y condiciones</a> del servicio.</span>
                        </label>
                    </div>

                    <div class="npn-button-group">
                        <button type="button" class="npn-btn npn-btn-prev" data-prev="page-personal-info">
                            <i class="fa-solid fa-arrow-left"></i> Anterior
                        </button>
                        <button type="button" class="npn-btn npn-btn-next" data-next="page-payment">
                            Siguiente <i class="fa-solid fa-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <!-- PÁGINA 3: Pago -->
                <div id="page-payment" class="npn-form-page hidden">
                    <h3><i class="fa-solid fa-credit-card"></i> Información de Pago</h3>

                    <div class="npn-price-summary">
                        <div class="npn-price-row">
                            <span>Certificado de navegabilidad</span>
                            <span>15,00 €</span>
                        </div>
                        <div class="npn-price-row">
                            <span>Emisión de permiso</span>
                            <span>8,00 €</span>
                        </div>
                        <div class="npn-price-row">
                            <span>Honorarios profesionales</span>
                            <span>34,69 €</span>
                        </div>
                        <div class="npn-price-row">
                            <span>IVA (21%)</span>
                            <span>7,28 €</span>
                        </div>
                        <div class="npn-price-row npn-price-total">
                            <strong>Total a pagar</strong>
                            <strong id="final-amount">65,00 €</strong>
                        </div>
                    </div>

                    <div class="npn-coupon-container">
                        <label for="coupon_code">Código de descuento (opcional)</label>
                        <div class="npn-coupon-input">
                            <input type="text" id="coupon_code" name="coupon_code" placeholder="Ingresa tu código">
                        </div>
                        <div id="coupon-message" class="npn-coupon-message hidden"></div>
                    </div>

                    <div id="payment-element"></div>

                    <div class="npn-terms">
                        <label>
                            <input type="checkbox" name="terms_accept_pago" required>
                            <span>Acepto los <a href="https://tramitfy.es/terminos-y-condiciones-de-uso/" target="_blank">términos de pago</a> y autorizo el cargo.</span>
                        </label>
                    </div>

                    <div class="npn-button-group">
                        <button type="button" class="npn-btn npn-btn-prev" data-prev="page-documents">
                            <i class="fa-solid fa-arrow-left"></i> Anterior
                        </button>
                        <button type="submit" class="npn-btn npn-btn-submit" id="submit">
                            <i class="fa-solid fa-lock"></i> Pagar 65,00 €
                        </button>
                    </div>
                </div>

            </form>
        </div>
    </div>

    <script>
    (function() {
        'use strict';

        document.addEventListener('DOMContentLoaded', function() {
            // Variables globales
            let stripe, elements, clientSecret, signaturePad;
            let currentPrice = 65.00;
            const basePrice = 65.00;

            // Navegación entre páginas
            const formPages = document.querySelectorAll('.npn-form-page');
            const navItems = document.querySelectorAll('.npn-nav-item');
            let currentPageIndex = 0;

            function showPage(pageId) {
                formPages.forEach((page, index) => {
                    if (page.id === pageId) {
                        page.classList.remove('hidden');
                        currentPageIndex = index;
                    } else {
                        page.classList.add('hidden');
                    }
                });

                navItems.forEach((nav, index) => {
                    nav.classList.toggle('active', index === currentPageIndex);
                });

                // Inicializar Stripe en página de pago
                if (pageId === 'page-payment' && !stripe) {
                    initializeStripe();
                }

                // Generar documento de autorización
                if (pageId === 'page-documents') {
                    generateAuthorizationDocument();
                }

                // Manejar visibilidad del campo "permiso caducado"
                const renewalType = document.getElementById('renewal_type').value;
                const permisoCaducadoSection = document.getElementById('permiso-caducado-section');
                if (renewalType === 'perdida') {
                    permisoCaducadoSection.style.display = 'none';
                    document.getElementById('upload-permiso-caducado').required = false;
                } else {
                    permisoCaducadoSection.style.display = 'block';
                    document.getElementById('upload-permiso-caducado').required = true;
                }
            }

            // Event listeners para navegación
            document.querySelectorAll('.npn-btn-next').forEach(btn => {
                btn.addEventListener('click', function() {
                    if (validateCurrentPage()) {
                        const nextPage = this.getAttribute('data-next');
                        showPage(nextPage);
                    }
                });
            });

            document.querySelectorAll('.npn-btn-prev').forEach(btn => {
                btn.addEventListener('click', function() {
                    const prevPage = this.getAttribute('data-prev');
                    showPage(prevPage);
                });
            });

            navItems.forEach(nav => {
                nav.addEventListener('click', function(e) {
                    e.preventDefault();
                    const pageId = this.getAttribute('data-page-id');
                    showPage(pageId);
                });
            });

            // Validación de página actual
            function validateCurrentPage() {
                const currentPage = document.querySelector('.npn-form-page:not(.hidden)');
                const requiredFields = currentPage.querySelectorAll('[required]');
                let isValid = true;

                requiredFields.forEach(field => {
                    if (!field.value || (field.type === 'checkbox' && !field.checked)) {
                        field.style.borderColor = 'rgb(var(--error))';
                        isValid = false;
                    } else {
                        field.style.borderColor = '';
                    }
                });

                if (!isValid) {
                    alert('Por favor, complete todos los campos obligatorios.');
                }

                return isValid;
            }

            // Generar documento de autorización
            function generateAuthorizationDocument() {
                const authDoc = document.getElementById('authorization-document');
                const name = document.getElementById('customer_name').value || '[Nombre]';
                const dni = document.getElementById('customer_dni').value || '[DNI]';
                const renewalType = document.getElementById('renewal_type');
                const renewalText = renewalType.options[renewalType.selectedIndex].text;

                authDoc.innerHTML = `
                    <p><strong>AUTORIZACIÓN PARA TRAMITACIÓN</strong></p>
                    <p>Yo, <strong>${name}</strong>, con DNI <strong>${dni}</strong>, autorizo a TRAMITFY para que, en mi nombre y representación, gestione ante las autoridades competentes la renovación de mi permiso de navegación por: <strong>${renewalText}</strong>.</p>
                    <p>Me comprometo a aportar toda la documentación necesaria y a abonar las tasas correspondientes.</p>
                `;
            }

            // Inicializar Stripe
            async function initializeStripe() {
                const totalAmountCents = Math.round(currentPrice * 100);
                
                stripe = Stripe('<?php echo $stripe_public_key; ?>');

                try {
                    const response = await fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `action=create_payment_intent_navigation_permit_renewal&amount=${totalAmountCents}`
                    });

                    const result = await response.json();
                    
                    if (result.error) {
                        throw new Error(result.error);
                    }

                    clientSecret = result.clientSecret;

                    const appearance = {
                        theme: 'stripe',
                        variables: {
                            colorPrimary: '#016d86',
                            colorBackground: '#ffffff',
                            colorText: '#333333',
                            borderRadius: '8px'
                        }
                    };

                    elements = stripe.elements({ appearance, clientSecret });
                    const paymentElement = elements.create('payment', {
                        paymentMethodOrder: ['card']
                    });
                    paymentElement.mount('#payment-element');

                } catch (error) {
                    console.error('Error initializing Stripe:', error);
                    alert('Error al cargar el sistema de pago. Por favor, recargue la página.');
                }
            }

            // Inicializar firma
            signaturePad = new SignaturePad(document.getElementById('signature-pad'));

            document.getElementById('clear-signature').addEventListener('click', function() {
                signaturePad.clear();
            });

            // Manejar envío del formulario
            const form = document.getElementById('navigation-permit-renewal-form');
            form.addEventListener('submit', async function(e) {
                e.preventDefault();

                // Validar términos de pago
                if (!document.querySelector('input[name="terms_accept_pago"]').checked) {
                    alert('Debe aceptar los términos y condiciones de pago.');
                    return;
                }

                // Validar firma
                if (signaturePad.isEmpty()) {
                    alert('Por favor, firme el documento de autorización.');
                    showPage('page-documents');
                    return;
                }

                // Mostrar overlay de carga
                const loadingOverlay = document.getElementById('loading-overlay');
                loadingOverlay.classList.add('active');

                try {
                    // Confirmar pago con Stripe
                    const { error } = await stripe.confirmPayment({
                        elements,
                        confirmParams: {
                            payment_method_data: {
                                billing_details: {
                                    name: document.getElementById('customer_name').value,
                                    email: document.getElementById('customer_email').value,
                                    phone: document.getElementById('customer_phone').value
                                }
                            },
                            return_url: window.location.href
                        },
                        redirect: 'if_required'
                    });

                    if (error) {
                        throw new Error(error.message);
                    }

                    // Pago exitoso, enviar formulario
                    await submitFormData();

                } catch (error) {
                    console.error('Error:', error);
                    alert('Error al procesar el pago: ' + error.message);
                    loadingOverlay.classList.remove('active');
                }
            });

            // Enviar datos del formulario
            async function submitFormData() {
                const formData = new FormData(form);
                
                // Añadir firma
                formData.append('signature', signaturePad.toDataURL());
                
                // Añadir datos adicionales
                formData.append('finalAmount', currentPrice);
                formData.append('hasSignature', 'true');
                formData.append('renewalType', document.getElementById('renewal_type').value);
                formData.append('couponCode', document.getElementById('coupon_code').value || '');
                formData.append('termsAccept', 'true');

                try {
                    const response = await fetch('https://46-202-128-35.sslip.io/api/herramientas/permiso-navegacion/webhook', {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();

                    if (result.success) {
                        alert(`✅ Formulario enviado con éxito. ID del trámite: ${result.tramiteId}`);
                        window.location.href = `https://46-202-128-35.sslip.io/seguimiento/${result.id}`;
                    } else {
                        throw new Error(result.error || 'Error al procesar el formulario');
                    }

                } catch (error) {
                    console.error('Error:', error);
                    alert('Error al enviar el formulario: ' + error.message);
                    document.getElementById('loading-overlay').classList.remove('active');
                }
            }

            // Auto-rellenado para administradores
            <?php if (current_user_can('administrator')): ?>
            const autofillBtn = document.getElementById('admin-autofill-btn');
            if (autofillBtn) {
                autofillBtn.addEventListener('click', function() {
                    // Rellenar datos personales
                    document.getElementById('customer_name').value = 'Admin Test';
                    document.getElementById('customer_dni').value = '12345678Z';
                    document.getElementById('customer_email').value = 'joanpinyol@hotmail.es';
                    document.getElementById('customer_phone').value = '682246937';
                    document.getElementById('renewal_type').value = 'caducidad';

                    // Marcar términos
                    document.querySelector('input[name="terms_accept"]').checked = true;
                    document.querySelector('input[name="terms_accept_pago"]').checked = true;

                    // Simular firma
                    setTimeout(() => {
                        const canvas = document.getElementById('signature-pad');
                        const ctx = canvas.getContext('2d');
                        ctx.font = '30px cursive';
                        ctx.fillStyle = '#000';
                        ctx.fillText('Admin Test', 50, 100);
                    }, 300);

                    alert('✅ Formulario auto-rellenado. Los archivos deben subirse manualmente.');
                });
            }
            <?php endif; ?>

            // Inicializar la primera página
            showPage('page-personal-info');
        });
    })();
    </script>

    <?php
    return ob_get_clean();
}

// Registrar el shortcode
add_shortcode('navigation_permit_renewal_form', 'navigation_permit_renewal_form_shortcode');

// AJAX handler para crear Payment Intent
add_action('wp_ajax_create_payment_intent_navigation_permit_renewal', 'create_payment_intent_navigation_permit_renewal');
add_action('wp_ajax_nopriv_create_payment_intent_navigation_permit_renewal', 'create_payment_intent_navigation_permit_renewal');

function create_payment_intent_navigation_permit_renewal() {
    global $stripe_secret_key;
    
    require_once get_template_directory() . '/vendor/autoload.php';
    
    \Stripe\Stripe::setApiKey($stripe_secret_key);
    
    $amount = isset($_POST['amount']) ? intval($_POST['amount']) : 6500;
    
    try {
        $paymentIntent = \Stripe\PaymentIntent::create([
            'amount' => $amount,
            'currency' => 'eur',
            'automatic_payment_methods' => ['enabled' => true],
            'description' => 'Renovación Permiso de Navegación',
        ]);
        
        wp_send_json([
            'clientSecret' => $paymentIntent->client_secret
        ]);
    } catch (Exception $e) {
        wp_send_json(['error' => $e->getMessage()], 500);
    }
}
?>
