<?php
// Asegurarse de que el archivo no sea accedido directamente
defined('ABSPATH') || exit;

// Cargar Stripe library ANTES de las funciones (IGUAL QUE RECUPERAR DOCUMENTACIÓN)
require_once(get_template_directory() . '/vendor/autoload.php');

// Configuración de Stripe AL NIVEL GLOBAL (IGUAL QUE RECUPERAR DOCUMENTACIÓN)
define('HOJA_ASIENTO_STRIPE_MODE', 'test'); // 'test' o 'live'

define('HOJA_ASIENTO_STRIPE_TEST_PUBLIC_KEY', 'pk_test_YOUR_STRIPE_TEST_PUBLIC_KEY');
define('HOJA_ASIENTO_STRIPE_TEST_SECRET_KEY', 'sk_test_YOUR_STRIPE_TEST_SECRET_KEY');

define('HOJA_ASIENTO_STRIPE_LIVE_PUBLIC_KEY', 'pk_live_YOUR_STRIPE_LIVE_PUBLIC_KEY');
define('HOJA_ASIENTO_STRIPE_LIVE_SECRET_KEY', 'sk_live_YOUR_STRIPE_LIVE_SECRET_KEY');

define('HOJA_ASIENTO_PRECIO_BASE', 29.95);
define('HOJA_ASIENTO_API_URL', 'https://46-202-128-35.sslip.io/api/herramientas/hoja-asiento/webhook');

// Seleccionar las claves según el modo (IGUAL QUE RECUPERAR DOCUMENTACIÓN)
if (HOJA_ASIENTO_STRIPE_MODE === 'test') {
    $stripe_public_key = HOJA_ASIENTO_STRIPE_TEST_PUBLIC_KEY;
    $stripe_secret_key = HOJA_ASIENTO_STRIPE_TEST_SECRET_KEY;
} else {
    $stripe_public_key = HOJA_ASIENTO_STRIPE_LIVE_PUBLIC_KEY;
    $stripe_secret_key = HOJA_ASIENTO_STRIPE_LIVE_SECRET_KEY;
}

/**
 * Shortcode para el formulario de solicitud de permiso de navegación
 */
function hoja_asiento_form_shortcode() {
    global $stripe_public_key, $stripe_secret_key;

    // Si estamos en el editor de Elementor, devolver un placeholder
    if (defined('ELEMENTOR_VERSION') &&
        class_exists('\Elementor\Plugin') &&
        \Elementor\Plugin::$instance->editor &&
        \Elementor\Plugin::$instance->editor->is_edit_mode()) {
        return '<div style="padding: 20px; background: #f0f0f0; text-align: center;">
                    <h3>Formulario de Renovación de Hoja de Asiento</h3>
                    <p>El formulario se mostrará aquí en el frontend.</p>
                </div>';
    }
    
    // Encolar los scripts y estilos necesarios
    wp_enqueue_style('hoja-asiento-renewal-form-style', get_template_directory_uri() . '/style.css', array(), filemtime(get_template_directory() . '/style.css'));
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
            background: transparent !important;
        }

        /* Container principal - Grid de 2 columnas */
        .ha-container {
            max-width: 1400px;
            margin: 25px auto;
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
            display: grid;
            grid-template-columns: 380px 1fr;
            min-height: auto;
        }

        /* SIDEBAR IZQUIERDO */
        .ha-sidebar {
            background: linear-gradient(180deg, rgb(var(--primary)) 0%, rgb(var(--primary-dark)) 100%);
            color: white;
            padding: 20px 20px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            position: sticky;
            top: 0;
            height: 95vh;
            overflow-y: auto;
        }

        .ha-logo {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .ha-logo i {
            font-size: 28px;
        }

        .ha-headline {
            font-size: 17px;
            font-weight: 600;
            line-height: 1.3;
            margin-bottom: 4px;
        }

        .ha-subheadline {
            font-size: 13px;
            opacity: 0.92;
            line-height: 1.4;
        }

        /* Caja de precio destacada */
        .ha-price-box {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            padding: 12px;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.25);
            margin: 6px 0;
        }

        .ha-price-label {
            font-size: 11px;
            opacity: 0.85;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 6px;
        }

        .ha-price-amount {
            font-size: 38px;
            font-weight: 700;
            margin: 4px 0;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .ha-price-detail {
            font-size: 12px;
            opacity: 0.88;
        }

        /* Lista de beneficios */
        .ha-benefits {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin: 8px 0;
        }

        .ha-benefit {
            display: flex;
            align-items: start;
            gap: 8px;
            font-size: 12px;
            line-height: 1.4;
        }

        .ha-benefit i {
            font-size: 14px;
            color: rgb(var(--success));
            background: white;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            margin-top: 1px;
        }

        /* Trust badges */
        .ha-trust-badges {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: auto;
            padding-top: 10px;
        }

        .ha-badge {
            background: rgba(255, 255, 255, 0.18);
            padding: 5px 10px;
            border-radius: 16px;
            font-size: 10px;
            display: flex;
            align-items: center;
            gap: 4px;
            border: 1px solid rgba(255, 255, 255, 0.25);
            font-weight: 500;
        }

        .ha-badge i {
            font-size: 11px;
        }

        /* Sidebar de autorización */
        .ha-sidebar-auth-doc {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-top: 10px;
        }

        /* ÁREA PRINCIPAL DEL FORMULARIO */
        .ha-form-area {
            padding: 30px 40px;
            background: #fafbfc;
            overflow-y: auto;
        }

        .ha-form-header {
            margin-bottom: 15px;
        }

        .ha-form-title {
            font-size: 22px;
            font-weight: 700;
            color: rgb(var(--neutral-900));
            margin-bottom: 4px;
        }

        .ha-form-subtitle {
            font-size: 13px;
            color: rgb(var(--neutral-600));
        }

        /* Panel de auto-rellenado para administradores */
        .ha-admin-panel {
            background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
            color: white;
            padding: 10px 15px;
            border-radius: 10px;
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 12px rgba(14, 165, 233, 0.3);
        }

        .ha-admin-panel-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .ha-admin-panel-title {
            font-size: 12px;
            font-weight: 600;
            opacity: 0.95;
        }

        .ha-admin-panel-subtitle {
            font-size: 10px;
            opacity: 0.85;
        }

        .ha-admin-autofill-btn {
            padding: 8px 16px;
            background: white;
            color: #0ea5e9;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 12px;
            transition: all 0.2s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        }

        .ha-admin-autofill-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        /* Navegación modernizada */
        .ha-navigation {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            padding: 6px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .ha-nav-item {
            flex: 1;
            padding: 10px 16px;
            text-align: center;
            background: #f8f9fa;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            color: rgb(var(--neutral-700));
            font-weight: 500;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            border: 2px solid transparent;
        }

        .ha-nav-item i {
            font-size: 14px;
        }

        .ha-nav-item.active {
            background: linear-gradient(135deg, rgb(var(--primary)) 0%, rgb(var(--primary-dark)) 100%);
            color: white;
            border-color: rgb(var(--primary));
            box-shadow: 0 4px 12px rgba(var(--primary), 0.3);
        }

        .ha-nav-item:hover:not(.active) {
            background: #e9ecef;
            border-color: rgb(var(--primary-light));
        }

        /* Páginas del formulario */
        .ha-form-page {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        }

        .ha-form-page.hidden {
            display: none;
        }

        .ha-form-page h3 {
            font-size: 18px;
            font-weight: 600;
            color: rgb(var(--neutral-900));
            margin: 0 0 20px 0;
        }

        /* Inputs mejorados */
        .ha-input-group {
            margin-bottom: 18px;
        }

        .ha-input-group label {
            display: block;
            font-weight: 500;
            margin-bottom: 7px;
            color: rgb(var(--neutral-800));
            font-size: 14px;
        }

        .ha-input-group input[type="text"],
        .ha-input-group input[type="email"],
        .ha-input-group input[type="tel"],
        .ha-input-group input[type="file"],
        .ha-input-group select {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid rgb(var(--neutral-300));
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.2s ease;
            background: white;
        }

        .ha-input-group input:focus,
        .ha-input-group select:focus {
            outline: none;
            border-color: rgb(var(--primary));
            box-shadow: 0 0 0 3px rgba(var(--primary), 0.1);
        }

        /* Grid para inputs en 2 columnas */
        .ha-inputs-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 18px;
        }

        /* Upload section */
        .ha-upload-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }

        .ha-upload-item {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 12px;
            border: 2px dashed rgb(var(--neutral-300));
            transition: all 0.3s ease;
        }

        .ha-upload-item:hover {
            border-color: rgb(var(--primary));
            background: rgba(var(--primary), 0.02);
        }

        .ha-upload-item label {
            display: block;
            font-weight: 600;
            margin-bottom: 12px;
            color: rgb(var(--neutral-800));
            font-size: 15px;
        }

        .ha-upload-item input[type="file"] {
            width: 100%;
            padding: 6px;
            border: none;
            background: white;
            border-radius: 6px;
            font-size: 11px;
        }

        .ha-upload-item .view-example {
            display: inline-block;
            margin-top: 4px;
            color: rgb(var(--primary));
            text-decoration: none;
            font-size: 11px;
            font-weight: 500;
        }

        .ha-upload-item .view-example:hover {
            text-decoration: underline;
        }

        /* Layout 2 columnas para autorización */
        .ha-auth-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin: 20px 0;
        }

        .ha-auth-document {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 10px;
            font-size: 14px;
            line-height: 1.7;
            border: 2px solid rgb(var(--neutral-200));
        }

        .ha-auth-document h4 {
            font-size: 16px;
            font-weight: 700;
            color: rgb(var(--primary));
            margin-bottom: 15px;
        }

        .ha-auth-signature-area {
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .ha-signature-label {
            font-size: 14px;
            font-weight: 600;
            color: rgb(var(--neutral-700));
            margin-bottom: 12px;
            text-align: center;
        }

        /* Firma */
        .ha-signature-container {
            margin: 0;
            text-align: center;
            position: relative;
        }

        #signature-pad {
            border: 3px solid rgb(var(--primary));
            border-radius: 8px;
            width: 100%;
            height: 180px;
            cursor: crosshair;
            background: white;
            box-shadow: 0 2px 8px rgba(var(--primary), 0.15);
        }

        .ha-signature-clear {
            margin-top: 12px;
            padding: 10px 20px;
            background: rgb(var(--neutral-500));
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.2s ease;
        }

        .ha-signature-clear:hover {
            background: rgb(var(--neutral-600));
            transform: translateY(-1px);
        }

        .ha-zoom-btn {
            display: none;
            margin-top: 12px;
            padding: 10px 20px;
            background: linear-gradient(135deg, rgb(var(--primary)) 0%, rgb(var(--primary-dark)) 100%);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(var(--primary), 0.3);
        }

        .ha-zoom-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(var(--primary), 0.4);
        }

        /* Modal de firma avanzado */
        .ha-signature-modal {
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
            animation: fadeIn 0.3s ease;
        }

        .ha-signature-modal.active {
            display: flex;
        }

        .ha-signature-modal.active ~ * .wa__popup_chat_box,
        .ha-signature-modal.active ~ * #whatsapp-button,
        .ha-signature-modal.active ~ * .wa__btn_popup {
            display: none !important;
            visibility: hidden !important;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        .ha-modal-content {
            position: relative;
            width: 95%;
            height: 92%;
            max-width: 95%;
            max-height: 90vh;
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .ha-modal-header {
            background: linear-gradient(135deg, rgb(var(--primary)) 0%, rgb(var(--primary-dark)) 100%);
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid rgba(255, 255, 255, 0.2);
        }

        .ha-modal-header h3 {
            margin: 0;
            font-size: 22px;
            font-weight: 700;
        }

        .ha-modal-close {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }

        .ha-modal-close:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: rotate(90deg);
        }

        .ha-enhanced-signature-container {
            position: relative;
            flex: 1;
            width: 100%;
            background-color: white;
            overflow: hidden;
            touch-action: none;
        }

        #enhanced-signature-canvas {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            touch-action: none;
        }

        .ha-signature-guide {
            position: absolute;
            top: 50%;
            left: 10px;
            right: 10px;
            z-index: 1;
            pointer-events: none;
        }

        .ha-signature-line {
            height: 2px;
            background-color: rgb(var(--primary));
            opacity: 0.5;
        }

        .ha-signature-instruction {
            position: absolute;
            color: rgb(var(--primary));
            font-size: 20px;
            font-weight: bold;
            letter-spacing: 3px;
            opacity: 0.3;
            left: 50%;
            top: -15px;
            transform: translateX(-50%);
            text-align: center;
        }

        .ha-modal-footer {
            background: #f8f9fa;
            padding: 20px;
            border-top: 2px solid rgb(var(--neutral-200));
        }

        .ha-modal-instructions {
            text-align: center;
            color: rgb(var(--neutral-600));
            font-size: 14px;
            margin-bottom: 15px;
        }

        .ha-modal-button-container {
            display: flex;
            gap: 12px;
        }

        .ha-modal-clear-btn {
            flex: 1;
            padding: 14px 24px;
            background: rgb(var(--neutral-500));
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 15px;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .ha-modal-clear-btn:hover {
            background: rgb(var(--neutral-600));
            transform: translateY(-2px);
        }

        .ha-modal-accept-btn {
            flex: 2;
            padding: 14px 24px;
            background: linear-gradient(135deg, rgb(var(--success)) 0%, rgba(var(--success), 0.8) 100%);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 15px;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(var(--success), 0.3);
        }

        .ha-modal-accept-btn:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(var(--success), 0.4);
        }

        .ha-modal-accept-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Modal de pago */
        .ha-payment-modal {
            display: none;
            position: fixed;
            z-index: 999998;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(5px);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .ha-payment-modal.show {
            display: block;
            opacity: 1;
        }

        .ha-payment-modal-content {
            background-color: #fff;
            margin: 125px auto 5% auto;
            max-width: 600px;
            width: 90%;
            border-radius: 12px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            padding: 25px;
            position: relative;
            transform: translateY(-20px);
            opacity: 0;
            transition: all 0.4s ease;
        }

        .ha-payment-modal.show .ha-payment-modal-content {
            transform: translateY(0);
            opacity: 1;
        }

        .ha-close-payment-modal {
            position: absolute;
            top: 20px;
            right: 20px;
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.2s ease;
        }

        .ha-close-payment-modal:hover {
            color: #333;
            background-color: #f0f0f0;
        }

        #ha-stripe-container {
            margin: 0 auto;
            width: 100%;
            padding: 0;
        }

        #ha-stripe-loading {
            text-align: center;
            padding: 20px;
            margin-bottom: 15px;
        }

        .ha-stripe-spinner {
            display: inline-block;
            width: 40px;
            height: 40px;
            border: 4px solid rgba(var(--primary), 0.3);
            border-radius: 50%;
            border-top-color: rgb(var(--primary));
            animation: spin 1s linear infinite;
            margin-bottom: 15px;
        }


        .ha-confirm-payment-btn {
            width: 100%;
            padding: 16px 24px;
            background: linear-gradient(135deg, rgb(var(--primary)) 0%, rgb(var(--primary-dark)) 100%);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 4px 12px rgba(var(--primary), 0.3);
            margin-top: 20px;
        }

        .ha-confirm-payment-btn:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(var(--primary), 0.4);
        }

        .ha-confirm-payment-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        #ha-payment-message {
            margin: 15px 0;
            padding: 12px;
            border-radius: 8px;
            font-size: 14px;
            text-align: center;
        }

        #ha-payment-message.error {
            background: rgba(var(--error), 0.1);
            color: rgb(var(--error));
            border: 1px solid rgba(var(--error), 0.3);
        }

        #ha-payment-message.success {
            background: rgba(var(--success), 0.1);
            color: rgb(var(--success));
            border: 1px solid rgba(var(--success), 0.3);
        }

        #ha-payment-message.processing {
            background: rgba(var(--info), 0.1);
            color: rgb(var(--info));
            border: 1px solid rgba(var(--info), 0.3);
        }

        #ha-payment-message.hidden {
            display: none;
        }

        /* Términos y condiciones */
        .ha-terms {
            margin: 12px 0;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 3px solid rgb(var(--info));
        }

        .ha-terms label {
            display: flex;
            align-items: start;
            gap: 8px;
            cursor: pointer;
            font-size: 11px;
        }

        .ha-terms input[type="checkbox"] {
            margin-top: 2px;
            width: 16px;
            height: 16px;
            cursor: pointer;
        }

        .ha-terms a {
            color: rgb(var(--primary));
            text-decoration: none;
            font-weight: 500;
        }

        .ha-terms a:hover {
            text-decoration: underline;
        }

        /* Botones de navegación */
        .ha-button-group {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .ha-btn {
            flex: 1;
            padding: 14px 24px;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .ha-btn-prev {
            background: rgb(var(--neutral-300));
            color: rgb(var(--neutral-800));
        }

        .ha-btn-prev:hover {
            background: rgb(var(--neutral-400));
            transform: translateY(-2px);
        }

        .ha-btn-next, .ha-btn-submit {
            background: linear-gradient(135deg, rgb(var(--primary)) 0%, rgb(var(--primary-dark)) 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(var(--primary), 0.3);
        }

        .ha-btn-next:hover, .ha-btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(var(--primary), 0.4);
        }

        /* Precio y pago */
        .ha-price-summary {
            background: #f8f9fa;
            padding: 12px 15px;
            border-radius: 8px;
            margin: 12px 0;
            border: 2px solid rgb(var(--neutral-200));
        }

        .ha-price-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 6px;
            font-size: 13px;
        }

        .ha-price-row strong {
            color: rgb(var(--neutral-900));
        }

        .ha-price-total {
            margin-top: 8px;
            padding-top: 8px;
            border-top: 2px solid rgb(var(--neutral-300));
            font-size: 16px;
            font-weight: 700;
            color: rgb(var(--primary));
        }

        /* Payment element de Stripe */
        #payment-element {
            margin: 12px 0;
            padding: 12px;
            background: white;
            border-radius: 8px;
            border: 2px solid rgb(var(--neutral-200));
        }

        /* Cupón */
        .ha-coupon-container {
            margin: 12px 0;
        }

        .ha-coupon-input {
            display: flex;
            gap: 8px;
        }

        #coupon_code {
            flex: 1;
            padding: 8px 12px;
            border: 2px solid rgb(var(--neutral-300));
            border-radius: 8px;
            font-size: 13px;
        }

        .ha-coupon-message {
            margin-top: 6px;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 12px;
        }

        .ha-coupon-message.success {
            background: rgba(var(--success), 0.1);
            color: rgb(var(--success));
            border: 1px solid rgba(var(--success), 0.3);
        }

        .ha-coupon-message.error {
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

        .ha-loading-spinner {
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
            .ha-container {
                grid-template-columns: 1fr;
                margin: 20px;
            }

            .ha-sidebar {
                position: relative;
                height: auto;
            }

            .ha-form-area {
                padding: 25px 20px;
            }

            .ha-inputs-row {
                grid-template-columns: 1fr;
            }

            .ha-navigation {
                flex-wrap: wrap;
            }

            .ha-nav-item {
                flex: 1 1 calc(50% - 8px);
                min-width: 140px;
            }
        }

        /* File previews */
        .ha-file-preview-container {
            margin-top: 15px;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }

        .ha-file-preview-item {
            position: relative;
            width: 100px;
            height: 100px;
            border-radius: 8px;
            overflow: hidden;
            background: rgb(var(--neutral-100));
            border: 2px solid rgb(var(--neutral-200));
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            animation: fadeIn 0.3s ease;
        }

        .ha-file-preview-item:hover {
            border-color: rgb(var(--primary));
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(var(--primary), 0.15);
        }

        .ha-file-preview-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .ha-file-preview-item i {
            font-size: 32px;
            color: rgb(var(--neutral-400));
        }

        .ha-file-remove-btn {
            position: absolute;
            top: 4px;
            right: 4px;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background: rgba(220, 38, 38, 0.95);
            border: 2px solid white;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 11px;
            opacity: 0;
            transition: all 0.2s ease;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
        }

        .ha-file-preview-item:hover .ha-file-remove-btn {
            opacity: 1;
        }

        .ha-file-remove-btn:hover {
            background: rgba(185, 28, 28, 1);
            transform: scale(1.1);
        }

        .ha-file-name {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 6px 4px;
            background: linear-gradient(to top, rgba(0,0,0,0.8), transparent);
            color: white;
            font-size: 10px;
            text-align: center;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: scale(0.8);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        @keyframes fadeOut {
            from {
                opacity: 1;
                transform: scale(1);
            }
            to {
                opacity: 0;
                transform: scale(0.8);
            }
        }

        /* Hide default file input */
        .ha-upload-item input[type="file"] {
            opacity: 0;
            position: absolute;
            z-index: -1;
        }

        .ha-upload-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
            background: linear-gradient(135deg, rgb(var(--primary)) 0%, rgb(var(--primary-dark)) 100%);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(var(--primary), 0.2);
        }

        .ha-upload-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(var(--primary), 0.35);
        }

        .ha-upload-btn i {
            font-size: 16px;
        }

        @media (max-width: 768px) {
            .ha-container {
                margin: 10px;
                border-radius: 12px;
            }

            .ha-form-title {
                font-size: 22px;
            }

            .ha-upload-grid {
                grid-template-columns: 1fr;
            }

            .ha-file-preview-item {
                width: 85px;
                height: 85px;
            }

            .ha-file-remove-btn {
                opacity: 1;
            }

            .ha-auth-layout {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .ha-button-group {
                flex-direction: column;
            }

            #signature-pad {
                display: none;
            }

            .ha-signature-clear {
                display: none;
            }

            .ha-signature-container {
                margin: 25px 0 !important;
            }

            .ha-form-page {
                padding: 20px !important;
            }

            .ha-zoom-btn {
                display: block;
                width: 100%;
                padding: 16px 24px;
                font-size: 16px;
            }
        }
    </style>

    <!-- Container principal con layout de 2 columnas -->
    <div class="ha-container">
        
        <!-- SIDEBAR IZQUIERDO -->
        <div class="ha-sidebar">
            <!-- Contenido por defecto (Páginas 1, 2 y 4) -->
            <div id="sidebar-default">
                <div class="ha-logo">
                    <i class="fa-solid fa-ship"></i>
                    <span>Tramitfy</span>
                </div>

                <div>
                    <div class="ha-headline">
                        Solicitud de Hoja de Asiento
                    </div>
                    <div class="ha-subheadline">
                        Renueva tu permiso de navegación de forma rápida y segura. Gestión completa online sin desplazamientos.
                    </div>
                </div>

                <div class="ha-price-box">
                    <div class="ha-price-label">Precio Total</div>
                    <div class="ha-price-amount">29,95€</div>
                    <div class="ha-price-detail">IVA incluido · Pago único</div>
                </div>

                <div class="ha-benefits">
                    <div class="ha-benefit">
                        <i class="fa-solid fa-check"></i>
                        <span>Certificado de navegabilidad incluido</span>
                    </div>
                    <div class="ha-benefit">
                        <i class="fa-solid fa-check"></i>
                        <span>Emisión oficial del nuevo permiso</span>
                    </div>
                    <div class="ha-benefit">
                        <i class="fa-solid fa-check"></i>
                        <span>Gestión completa ante autoridades</span>
                    </div>
                    <div class="ha-benefit">
                        <i class="fa-solid fa-check"></i>
                        <span>Tramitación rápida en 5-7 días</span>
                    </div>
                    <div class="ha-benefit">
                        <i class="fa-solid fa-check"></i>
                        <span>Seguimiento online en tiempo real</span>
                    </div>
                </div>

                <div class="ha-trust-badges">
                    <div class="ha-badge">
                        <i class="fa-solid fa-shield-halved"></i>
                        <span>Pago seguro</span>
                    </div>
                    <div class="ha-badge">
                        <i class="fa-solid fa-lock"></i>
                        <span>Datos protegidos</span>
                    </div>
                    <div class="ha-badge">
                        <i class="fa-solid fa-headset"></i>
                        <span>Soporte 24/7</span>
                    </div>
                </div>
            </div>

            <!-- Contenido para página de autorización (Página 3) -->
            <div id="sidebar-authorization" style="display: none;">
                <div class="ha-logo">
                    <i class="fa-solid fa-file-signature"></i>
                    <span>Autorización</span>
                </div>

                <div class="ha-sidebar-auth-doc">
                    <h4 style="font-size: 18px; font-weight: 700; color: white; margin-bottom: 15px;">
                        DOCUMENTO DE AUTORIZACIÓN
                    </h4>

                    <div style="background: rgba(255,255,255,0.1); padding: 20px; border-radius: 10px; margin-bottom: 20px; backdrop-filter: blur(10px);">
                        <p style="font-size: 14px; line-height: 1.8; margin-bottom: 15px;">
                            Yo, <strong id="sidebar-auth-name" style="color: #fff; font-size: 16px;">[Nombre]</strong>, con DNI/NIE <strong id="sidebar-auth-dni" style="color: #fff;">[DNI]</strong>, autorizo a <strong>TRAMITFY</strong> para que, en mi nombre y representación, gestione ante las autoridades competentes la solicitud de mi permiso de navegación.
                        </p>
                        <p style="font-size: 14px; line-height: 1.8;">
                            Me comprometo a aportar toda la documentación necesaria y a abonar las tasas correspondientes.
                        </p>
                    </div>

                    <div style="background: rgba(255,255,255,0.15); padding: 15px; border-radius: 8px; border-left: 4px solid rgba(255,255,255,0.5);" class="sidebar-instruction">
                        <p style="font-size: 13px; line-height: 1.6; opacity: 0.95;">
                            <i class="fa-solid fa-info-circle" style="margin-right: 8px;"></i>
                            <span class="sidebar-desktop-text">Por favor, firme el documento en el área de la derecha para completar la autorización.</span>
                            <span class="sidebar-mobile-text" style="display: none;">Por favor, firme el documento en el área inferior para completar la autorización.</span>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- ÁREA PRINCIPAL DEL FORMULARIO -->
        <div class="ha-form-area">
            <form id="hoja-asiento-renewal-form" action="" method="POST" enctype="multipart/form-data">
                
                <div class="ha-form-header">
                    <h1 class="ha-form-title">Solicitud de Renovación</h1>
                    <p class="ha-form-subtitle">Complete el formulario para renovar su permiso de navegación</p>
                </div>

                <!-- Panel de auto-rellenado para administradores -->
                <?php if (current_user_can('administrator')): ?>
                <div class="ha-admin-panel">
                    <div class="ha-admin-panel-info">
                        <div class="ha-admin-panel-title">🔧 Modo Administrador</div>
                        <div class="ha-admin-panel-subtitle">Auto-relleno disponible para testing</div>
                    </div>
                    <button type="button" id="admin-autofill-btn" class="ha-admin-autofill-btn">
                        ⚡ Auto-rellenar
                    </button>
                </div>
                <?php endif; ?>

                <!-- Navegación del formulario -->
                <nav class="ha-navigation">
                    <a href="#" class="ha-nav-item active" data-page-id="page-personal-info">
                        <i class="fa-solid fa-user"></i>
                        <span>Datos Personales</span>
                    </a>
                    <a href="#" class="ha-nav-item" data-page-id="page-documents">
                        <i class="fa-solid fa-file-alt"></i>
                        <span>Documentación</span>
                    </a>
                    <a href="#" class="ha-nav-item" data-page-id="page-authorization">
                        <i class="fa-solid fa-signature"></i>
                        <span>Autorización</span>
                    </a>
                    <a href="#" class="ha-nav-item" data-page-id="page-payment">
                        <i class="fa-solid fa-credit-card"></i>
                        <span>Pago</span>
                    </a>
                </nav>

                <!-- Loading overlay -->
                <div id="loading-overlay">
                    <div class="ha-loading-spinner"></div>
                </div>

                <!-- PÁGINA 1: Datos Personales -->
                <div id="page-personal-info" class="ha-form-page">
                    <h3><i class="fa-solid fa-user"></i> Datos Personales</h3>

                    <div class="ha-inputs-row">
                        <div class="ha-input-group">
                            <label for="customer_name">Nombre y Apellidos *</label>
                            <input type="text" id="customer_name" name="customer_name" placeholder="Juan García López" required />
                        </div>

                        <div class="ha-input-group">
                            <label for="customer_dni">DNI/NIE *</label>
                            <input type="text" id="customer_dni" name="customer_dni" placeholder="12345678A" required />
                        </div>
                    </div>

                    <div class="ha-inputs-row">
                        <div class="ha-input-group">
                            <label for="customer_email">Correo Electrónico *</label>
                            <input type="email" id="customer_email" name="customer_email" placeholder="ejemplo@email.com" required />
                        </div>

                        <div class="ha-input-group">
                            <label for="customer_phone">Teléfono *</label>
                            <input type="tel" id="customer_phone" name="customer_phone" placeholder="600 123 456" required />
                        </div>
                    </div>

                    <div class="ha-button-group">
                        <button type="button" class="ha-btn ha-btn-next" data-next="page-documents">
                            Siguiente <i class="fa-solid fa-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <!-- PÁGINA 2: Documentación -->
                <div id="page-documents" class="ha-form-page hidden">
                    <h3><i class="fa-solid fa-file-alt"></i> Documentación Requerida</h3>

                    <p style="color: rgb(var(--neutral-600)); margin-bottom: 25px;">
                        Por favor, adjunte su DNI/NIE en formato PDF, JPG o PNG.
                    </p>

                    <div class="ha-upload-grid">
                        <div class="ha-upload-item">
                            <label for="upload-dni-propietario">
                                <i class="fa-solid fa-id-card"></i> DNI / NIE *
                            </label>
                            <input type="file" id="upload-dni-propietario" name="upload_dni_propietario[]" accept="image/*,.pdf" multiple>
                            <button type="button" class="ha-upload-btn" onclick="document.getElementById('upload-dni-propietario').click()">
                                <i class="fa-solid fa-cloud-arrow-up"></i> Seleccionar archivos
                            </button>
                            <div id="preview-dni-propietario" class="ha-file-preview-container"></div>
                            <a href="#" class="view-example" data-doc="dni" style="margin-top: 10px; display: inline-block;">Ver ejemplo</a>
                        </div>
                    </div>

                    <div class="ha-button-group">
                        <button type="button" class="ha-btn ha-btn-prev" data-prev="page-personal-info">
                            <i class="fa-solid fa-arrow-left"></i> Anterior
                        </button>
                        <button type="button" class="ha-btn ha-btn-next" data-next="page-authorization">
                            Siguiente <i class="fa-solid fa-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <!-- PÁGINA 3: Autorización y Firma -->
                <div id="page-authorization" class="ha-form-page hidden">
                    <h3><i class="fa-solid fa-signature"></i> Firme el Documento de Autorización</h3>

                    <p style="color: rgb(var(--neutral-600)); margin-bottom: 25px; text-align: center;" class="auth-instruction-text">
                        <span class="desktop-text">El documento de autorización se muestra en el panel izquierdo. Por favor, firme en el área inferior para completar la autorización.</span>
                        <span class="mobile-text" style="display: none;">El documento de autorización se muestra en el panel superior. Por favor, firme en el área inferior para completar la autorización.</span>
                    </p>

                    <div class="ha-signature-label" style="text-align: center; margin-bottom: 15px; font-size: 15px; font-weight: 600; color: rgb(var(--neutral-700));">
                        <i class="fa-solid fa-pen-to-square"></i> Firme aquí para autorizar
                    </div>

                    <div class="ha-signature-container" style="margin: 20px 0; text-align: center;">
                        <canvas id="signature-pad" width="800" height="200"></canvas>
                        <button type="button" class="ha-signature-clear" id="clear-signature">
                            <i class="fa-solid fa-eraser"></i> Limpiar Firma
                        </button>
                        <button type="button" class="ha-zoom-btn" id="zoom-signature">
                            <i class="fa-solid fa-search-plus"></i> Ampliar
                        </button>
                    </div>

                    <div class="ha-button-group">
                        <button type="button" class="ha-btn ha-btn-prev" data-prev="page-documents">
                            <i class="fa-solid fa-arrow-left"></i> Anterior
                        </button>
                        <button type="button" class="ha-btn ha-btn-next" data-next="page-payment">
                            Siguiente <i class="fa-solid fa-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <!-- PÁGINA 4: Pago -->
                <div id="page-payment" class="ha-form-page hidden">
                    <h3><i class="fa-solid fa-credit-card"></i> Información de Pago</h3>

                    <div class="ha-price-summary">
                        <div class="ha-price-row">
                            <span>Certificado de navegabilidad</span>
                            <span>7,61 €</span>
                        </div>
                        <div class="ha-price-row">
                            <span>Emisión de permiso</span>
                            <span>0,00 €</span>
                        </div>
                        <div class="ha-price-row">
                            <span>Honorarios profesionales</span>
                            <span>18,46 €</span>
                        </div>
                        <div class="ha-price-row">
                            <span>IVA (21%)</span>
                            <span>3,88 €</span>
                        </div>
                        <div class="ha-price-row ha-price-total">
                            <strong>Total a pagar</strong>
                            <strong id="final-amount">29,95 €</strong>
                        </div>
                    </div>

                    <div class="ha-coupon-container">
                        <label for="coupon_code">Código de descuento (opcional)</label>
                        <div class="ha-coupon-input">
                            <input type="text" id="coupon_code" name="coupon_code" placeholder="Ingresa tu código">
                        </div>
                        <div id="coupon-message" class="ha-coupon-message hidden"></div>
                    </div>

                    <div class="ha-terms">
                        <label>
                            <input type="checkbox" name="terms_accept" required>
                            <span>Acepto la <a href="https://tramitfy.es/politica-de-privacidad/" target="_blank">Política de Privacidad</a> y los <a href="https://tramitfy.es/terminos-y-condiciones-de-uso-2/" target="_blank">Términos y Condiciones</a> del servicio.</span>
                        </label>
                    </div>

                    <div class="ha-button-group">
                        <button type="button" class="ha-btn ha-btn-prev" data-prev="page-authorization">
                            <i class="fa-solid fa-arrow-left"></i> Anterior
                        </button>
                        <button type="button" class="ha-btn ha-btn-submit" id="show-payment-modal">
                            <i class="fa-solid fa-lock"></i> Realizar Pago Seguro
                        </button>
                    </div>
                </div>

            </form>
        </div>
    </div>

    <!-- Modal de pago -->
    <div id="ha-payment-modal" class="ha-payment-modal">
        <div class="ha-payment-modal-content">
            <span class="ha-close-payment-modal">&times;</span>

            <div id="ha-stripe-container">
                <!-- Spinner de carga mientras se inicializa -->
                <div id="ha-stripe-loading">
                    <div class="ha-stripe-spinner"></div>
                    <p>Cargando sistema de pago...</p>
                </div>

                <!-- Contenedor donde se montará el elemento de pago -->
                <div id="payment-element" class="payment-element-container"></div>

                <!-- Mensajes de estado del pago -->
                <div id="ha-payment-message" class="hidden"></div>
            </div>

            <button type="button" id="ha-confirm-payment-btn" class="ha-confirm-payment-btn">
                <i class="fa-solid fa-check-circle"></i> Confirmar Pago
            </button>
        </div>
    </div>

    <!-- Modal de firma avanzado -->
    <div id="signature-modal-advanced" class="ha-signature-modal">
        <div class="ha-modal-content">
            <div class="ha-modal-header">
                <h3><i class="fa-solid fa-pen-fancy"></i> Firma Digital</h3>
                <button class="ha-modal-close" id="close-modal">
                    <i class="fa-solid fa-times"></i>
                </button>
            </div>

            <div class="ha-enhanced-signature-container">
                <div class="ha-signature-guide">
                    <div class="ha-signature-line"></div>
                    <div class="ha-signature-instruction">FIRME AQUÍ</div>
                </div>
                <canvas id="enhanced-signature-canvas"></canvas>
            </div>

            <div class="ha-modal-footer">
                <p class="ha-modal-instructions">
                    <i class="fa-solid fa-hand-pointer"></i> Use el dedo para firmar en el área indicada
                </p>
                <div class="ha-modal-button-container">
                    <button class="ha-modal-clear-btn" id="modal-clear-btn">
                        <i class="fa-solid fa-eraser"></i> Borrar
                    </button>
                    <button class="ha-modal-accept-btn" id="modal-accept-btn" disabled>
                        <i class="fa-solid fa-check"></i> Confirmar firma
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
    (function() {
        'use strict';

        // Evitar ejecución en el editor de Elementor
        if (window.elementor || (typeof elementorFrontend !== 'undefined' && elementorFrontend.isEditMode && elementorFrontend.isEditMode())) {
            console.log('[Navigation Permit Form] Skipping initialization - Elementor editor detected');
            return;
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Variables globales
            let stripe, elements, clientSecret, signaturePad;
            let currentPrice = 29.95;
            const basePrice = 29.95;

            // Almacenamiento de archivos
            const fileStorage = {
                'upload-dni-propietario': []
            };

            // Sistema de múltiples archivos
            function initFileUpload(inputId, previewId) {
                const input = document.getElementById(inputId);
                const preview = document.getElementById(previewId);

                input.addEventListener('change', function(e) {
                    const files = Array.from(e.target.files);

                    files.forEach(file => {
                        // Agregar archivo al storage
                        fileStorage[inputId].push(file);

                        // Crear preview
                        const previewItem = document.createElement('div');
                        previewItem.className = 'ha-file-preview-item';
                        previewItem.dataset.fileName = file.name;

                        // Crear contenido según tipo de archivo
                        if (file.type.startsWith('image/')) {
                            const img = document.createElement('img');
                            const reader = new FileReader();
                            reader.onload = function(e) {
                                img.src = e.target.result;
                            };
                            reader.readAsDataURL(file);
                            previewItem.appendChild(img);
                        } else if (file.type === 'application/pdf') {
                            const icon = document.createElement('i');
                            icon.className = 'fa-solid fa-file-pdf';
                            icon.style.color = '#dc2626';
                            previewItem.appendChild(icon);
                        } else {
                            const icon = document.createElement('i');
                            icon.className = 'fa-solid fa-file';
                            previewItem.appendChild(icon);
                        }

                        // Nombre del archivo
                        const fileName = document.createElement('div');
                        fileName.className = 'ha-file-name';
                        fileName.textContent = file.name.length > 12 ? file.name.substring(0, 12) + '...' : file.name;
                        previewItem.appendChild(fileName);

                        // Botón de eliminar
                        const removeBtn = document.createElement('div');
                        removeBtn.className = 'ha-file-remove-btn';
                        removeBtn.innerHTML = '<i class="fa-solid fa-times"></i>';
                        removeBtn.onclick = function(e) {
                            e.stopPropagation();
                            removeFile(inputId, file.name, previewItem);
                        };
                        previewItem.appendChild(removeBtn);

                        preview.appendChild(previewItem);
                    });

                    // Limpiar el input para poder seleccionar los mismos archivos de nuevo
                    e.target.value = '';
                });
            }

            function removeFile(inputId, fileName, previewElement) {
                // Remover del storage
                fileStorage[inputId] = fileStorage[inputId].filter(f => f.name !== fileName);

                // Animar y eliminar preview
                previewElement.style.animation = 'fadeOut 0.2s ease';
                setTimeout(() => {
                    previewElement.remove();
                }, 200);
            }

            // Inicializar inputs de archivo
            initFileUpload('upload-dni-propietario', 'preview-dni-propietario');

            // Navegación entre páginas
            const formPages = document.querySelectorAll('.ha-form-page');
            const navItems = document.querySelectorAll('.ha-nav-item');
            let currentPageIndex = 0;

            function navigationPermitShowPage(pageId) {
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

                // Cambiar contenido del sidebar según la página
                const sidebarDefault = document.getElementById('sidebar-default');
                const sidebarAuthorization = document.getElementById('sidebar-authorization');

                if (pageId === 'page-authorization') {
                    sidebarDefault.style.display = 'none';
                    sidebarAuthorization.style.display = 'block';
                    generateAuthorizationDocument();

                    // Redimensionar canvas cuando se muestra la página
                    setTimeout(() => {
                        resizeCanvas();
                    }, 100);
                } else {
                    sidebarDefault.style.display = 'block';
                    sidebarAuthorization.style.display = 'none';
                }
            }

            // Event listeners para navegación
            document.querySelectorAll('.ha-btn-next').forEach(btn => {
                btn.addEventListener('click', function() {
                    if (navigationPermitValidateCurrentPage()) {
                        const nextPage = this.getAttribute('data-next');
                        navigationPermitShowPage(nextPage);
                    }
                });
            });

            document.querySelectorAll('.ha-btn-prev').forEach(btn => {
                btn.addEventListener('click', function() {
                    const prevPage = this.getAttribute('data-prev');
                    navigationPermitShowPage(prevPage);
                });
            });

            navItems.forEach(nav => {
                nav.addEventListener('click', function(e) {
                    e.preventDefault();
                    const pageId = this.getAttribute('data-page-id');
                    navigationPermitShowPage(pageId);
                });
            });

            // Validación de página actual
            function navigationPermitValidateCurrentPage() {
                const currentPage = document.querySelector('.ha-form-page:not(.hidden)');

                // Validación especial para página de documentos
                if (currentPage.id === 'page-documents') {
                    if (fileStorage['upload-dni-propietario'].length === 0) {
                        alert('Por favor, suba al menos un archivo de DNI/NIE.');
                        return false;
                    }
                    return true;
                }

                const requiredFields = currentPage.querySelectorAll('[required]');
                let isValid = true;

                requiredFields.forEach(field => {
                    // Saltar inputs de archivo porque ahora se validan con fileStorage
                    if (field.type === 'file') return;

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
                const name = document.getElementById('customer_name').value || '[Nombre]';
                const dni = document.getElementById('customer_dni').value || '[DNI]';

                // Actualizar sidebar
                document.getElementById('sidebar-auth-name').textContent = name;
                document.getElementById('sidebar-auth-dni').textContent = dni;
            }

            // Inicializar Stripe en el modal
            async function initializeStripe() {
                console.log('💳 Inicializando Stripe...');

                const loadingIndicator = document.getElementById('ha-stripe-loading');
                const stripeContainer = document.getElementById('payment-element');
                const paymentMessage = document.getElementById('ha-payment-message');

                // Mostrar loading
                if (loadingIndicator) loadingIndicator.style.display = 'flex';
                if (stripeContainer) stripeContainer.style.display = 'none';

                // Verificar que Stripe esté cargado
                if (typeof Stripe === 'undefined') {
                    console.error('❌ Stripe library no está cargada');
                    if (loadingIndicator) loadingIndicator.style.display = 'none';
                    if (paymentMessage) {
                        paymentMessage.textContent = 'Error: Sistema de pagos no disponible. Recarga la página.';
                        paymentMessage.className = 'error';
                        paymentMessage.style.display = 'block';
                    }
                    return false;
                }

                // Inicializar Stripe con la clave pública
                console.log('💳 Inicializando Stripe con clave pública...');
                const stripePublicKey = '<?php echo (HOJA_ASIENTO_STRIPE_MODE === "test") ? HOJA_ASIENTO_STRIPE_TEST_PUBLIC_KEY : HOJA_ASIENTO_STRIPE_LIVE_PUBLIC_KEY; ?>';
                console.log('💳 Usando clave:', stripePublicKey.substring(0, 15) + '...');
                console.log('💳 Modo:', '<?php echo HOJA_ASIENTO_STRIPE_MODE; ?>');
                stripe = Stripe(stripePublicKey);
                console.log('✅ Stripe object creado:', stripe);

                try {
                    console.log('💳 Creando Payment Intent...');
                    const totalAmountCents = Math.round(currentPrice * 100);

                    const response = await fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `action=create_payment_intent_hoja_asiento_renewal&amount=${totalAmountCents}`
                    });

                    if (!response.ok) {
                        throw new Error('Error en la conexión con el servidor');
                    }

                    const result = await response.json();
                    console.log('💳 Respuesta del servidor:', result);

                    if (result.error) throw new Error(result.error);
                    if (!result.clientSecret) throw new Error('No se recibió el client secret del servidor');

                    clientSecret = result.clientSecret;
                    console.log('💳 Client Secret recibido:', clientSecret.substring(0, 20) + '...');

                    if (!stripeContainer) {
                        throw new Error('Contenedor de Stripe no encontrado');
                    }

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
                        layout: { type: 'tabs', defaultCollapsed: false }
                    });

                    console.log('💳 Montando Stripe Elements en DOM...');
                    await paymentElement.mount('#payment-element');
                    console.log('✅ Stripe Elements montado correctamente');

                    // Ocultar loading y mostrar payment element
                    if (loadingIndicator) loadingIndicator.style.display = 'none';
                    if (stripeContainer) stripeContainer.style.display = 'block';

                    console.log('✅ Stripe inicializado completamente');
                    return true;

                } catch (error) {
                    console.error('❌ Error inicializando Stripe:', error);
                    console.error('❌ Error stack:', error.stack);

                    // Ocultar loading
                    if (loadingIndicator) loadingIndicator.style.display = 'none';

                    if (paymentMessage) {
                        paymentMessage.textContent = 'Error al cargar el sistema de pago: ' + error.message;
                        paymentMessage.className = 'error';
                        paymentMessage.style.display = 'block';
                    }

                    return false;
                }
            }

            // Inicializar firma con opciones mejoradas
            const canvas = document.getElementById('signature-pad');

            // Inicializar SignaturePad principal (para desktop)
            signaturePad = new SignaturePad(canvas, {
                minWidth: 0.5,
                maxWidth: 2.5,
                throttle: 0,
                velocityFilterWeight: 0.7,
                penColor: '#000000'
            });

            // Modal avanzado de firma
            const enhancedModal = document.getElementById('signature-modal-advanced');
            const enhancedCanvas = document.getElementById('enhanced-signature-canvas');
            let enhancedSignaturePad = null;
            let mainSignatureData = null;

            // Ajustar tamaño del canvas principal
            function resizeCanvas() {
                if (!canvas || canvas.offsetWidth === 0) return;

                const ratio = Math.max(window.devicePixelRatio || 1, 1);
                const width = canvas.offsetWidth;
                const height = canvas.offsetHeight;

                canvas.width = width * ratio;
                canvas.height = height * ratio;
                canvas.style.width = width + 'px';
                canvas.style.height = height + 'px';

                const context = canvas.getContext('2d');
                context.scale(ratio, ratio);

                // Restaurar firma si existe
                if (mainSignatureData && signaturePad) {
                    signaturePad.fromDataURL(mainSignatureData);
                }
            }

            // Redimensionar canvas del modal
            function resizeEnhancedCanvas() {
                const container = enhancedCanvas.parentElement;
                const rect = container.getBoundingClientRect();
                const ratio = window.devicePixelRatio || 1;

                enhancedCanvas.width = rect.width * ratio;
                enhancedCanvas.height = rect.height * ratio;
                enhancedCanvas.getContext('2d').scale(ratio, ratio);
            }

            // Inicializar SignaturePad del modal
            function initializeEnhancedSignaturePad() {
                if (enhancedSignaturePad) {
                    enhancedSignaturePad.off();
                }

                enhancedSignaturePad = new SignaturePad(enhancedCanvas, {
                    minWidth: 0.8,
                    maxWidth: 3.5,
                    throttle: 0,
                    velocityFilterWeight: 0.7,
                    penColor: '#000000'
                });

                enhancedSignaturePad.addEventListener('beginStroke', function() {
                    document.getElementById('modal-accept-btn').disabled = false;
                });
            }

            // Abrir modal avanzado
            function openEnhancedModal() {
                enhancedModal.classList.add('active');
                document.body.style.overflow = 'hidden';

                // Ocultar WhatsApp Ninja
                const waElements = document.querySelectorAll('.wa__popup_chat_box, #whatsapp-button, .wa__btn_popup, .wa__stt, [class*="wa__"], [id*="whatsapp"]');
                waElements.forEach(el => {
                    el.style.display = 'none';
                    el.style.visibility = 'hidden';
                });

                requestAnimationFrame(() => {
                    resizeEnhancedCanvas();
                    initializeEnhancedSignaturePad();

                    if (mainSignatureData) {
                        setTimeout(() => {
                            restoreSignatureToEnhancedCanvas();
                        }, 200);
                    }
                });
            }

            // Cerrar modal avanzado
            function closeEnhancedModal() {
                enhancedModal.style.opacity = '0';

                setTimeout(() => {
                    enhancedModal.classList.remove('active');
                    enhancedModal.style.opacity = '1';
                    document.body.style.overflow = '';

                    // Restaurar WhatsApp Ninja
                    const waElements = document.querySelectorAll('.wa__popup_chat_box, #whatsapp-button, .wa__btn_popup, .wa__stt, [class*="wa__"], [id*="whatsapp"]');
                    waElements.forEach(el => {
                        el.style.display = '';
                        el.style.visibility = '';
                    });
                }, 300);
            }

            // Restaurar firma en canvas del modal
            function restoreSignatureToEnhancedCanvas() {
                if (mainSignatureData && enhancedSignaturePad) {
                    enhancedSignaturePad.fromDataURL(mainSignatureData);
                    document.getElementById('modal-accept-btn').disabled = false;
                }
            }

            // Transferir firma del modal al canvas principal
            function transferSignatureToMain() {
                if (!enhancedSignaturePad.isEmpty()) {
                    mainSignatureData = enhancedSignaturePad.toDataURL();
                    signaturePad.fromDataURL(mainSignatureData);
                }
            }

            // Event listeners para modal
            document.getElementById('zoom-signature').addEventListener('click', openEnhancedModal);
            document.getElementById('close-modal').addEventListener('click', closeEnhancedModal);

            document.getElementById('modal-clear-btn').addEventListener('click', function() {
                if (enhancedSignaturePad) {
                    enhancedSignaturePad.clear();
                    document.getElementById('modal-accept-btn').disabled = true;
                }
            });

            document.getElementById('modal-accept-btn').addEventListener('click', function() {
                transferSignatureToMain();
                closeEnhancedModal();
            });

            // Cambiar texto según viewport
            function updateAuthText() {
                const desktopText = document.querySelector('.desktop-text');
                const mobileText = document.querySelector('.mobile-text');
                const sidebarDesktopText = document.querySelector('.sidebar-desktop-text');
                const sidebarMobileText = document.querySelector('.sidebar-mobile-text');

                if (window.innerWidth <= 1024) {
                    if (desktopText) desktopText.style.display = 'none';
                    if (mobileText) mobileText.style.display = 'inline';
                    if (sidebarDesktopText) sidebarDesktopText.style.display = 'none';
                    if (sidebarMobileText) sidebarMobileText.style.display = 'inline';
                } else {
                    if (desktopText) desktopText.style.display = 'inline';
                    if (mobileText) mobileText.style.display = 'none';
                    if (sidebarDesktopText) sidebarDesktopText.style.display = 'inline';
                    if (sidebarMobileText) sidebarMobileText.style.display = 'none';
                }
            }

            window.addEventListener('resize', function() {
                resizeCanvas();
                updateAuthText();
                if (enhancedModal.classList.contains('active')) {
                    resizeEnhancedCanvas();
                    if (enhancedSignaturePad && mainSignatureData) {
                        restoreSignatureToEnhancedCanvas();
                    }
                }
            });

            window.addEventListener('orientationchange', function() {
                setTimeout(() => {
                    if (enhancedModal.classList.contains('active')) {
                        resizeEnhancedCanvas();
                        if (enhancedSignaturePad && mainSignatureData) {
                            restoreSignatureToEnhancedCanvas();
                        }
                    }
                }, 300);
            });

            // Inicializar canvas en carga
            setTimeout(() => {
                resizeCanvas();
                updateAuthText();
            }, 100);

            document.getElementById('clear-signature').addEventListener('click', function() {
                signaturePad.clear();
                mainSignatureData = null;
            });

            // Abrir modal de pago
            document.getElementById('show-payment-modal').addEventListener('click', function() {
                // Validar términos y condiciones
                if (!document.querySelector('input[name="terms_accept"]').checked) {
                    alert('Debe aceptar la Política de Privacidad y los Términos y Condiciones.');
                    return;
                }

                // Validar firma
                if (signaturePad.isEmpty() && (!mainSignatureData || mainSignatureData === null)) {
                    alert('Por favor, firme el documento de autorización.');
                    navigationPermitShowPage('page-authorization');
                    return;
                }

                // Validar email
                const customerEmail = document.getElementById('customer_email').value.trim();
                if (!customerEmail) {
                    alert('Debe ingresar su correo electrónico en la sección de datos personales.');
                    navigationPermitShowPage('page-personal-info');
                    return;
                }

                // Mostrar el modal
                document.getElementById('ha-payment-modal').classList.add('show');

                // Inicializar Stripe si aún no se ha hecho
                if (!stripe || !elements) {
                    setTimeout(() => {
                        initializeStripe();
                    }, 300);
                }
            });

            // Cerrar modal de pago
            document.querySelector('.ha-close-payment-modal').addEventListener('click', function() {
                document.getElementById('ha-payment-modal').classList.remove('show');
            });

            document.getElementById('ha-payment-modal').addEventListener('click', function(event) {
                if (event.target === this) {
                    this.classList.remove('show');
                }
            });

            // Confirmar pago desde el modal
            document.getElementById('ha-confirm-payment-btn').addEventListener('click', async function() {
                const paymentMessage = document.getElementById('ha-payment-message');
                paymentMessage.className = 'hidden';
                paymentMessage.textContent = '';

                // Deshabilitar botón
                this.disabled = true;

                // Mostrar overlay de carga
                const loadingOverlay = document.getElementById('loading-overlay');
                loadingOverlay.classList.add('active');

                try {
                    // Verificar que Stripe esté inicializado
                    if (!stripe || !elements) {
                        throw new Error('El sistema de pago no está inicializado correctamente.');
                    }

                    paymentMessage.textContent = 'Procesando su pago...';
                    paymentMessage.className = 'processing';

                    // Confirmar pago con Stripe
                    const { error, paymentIntent } = await stripe.confirmPayment({
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

                    // Guardar payment intent ID
                    window.paymentIntentId = paymentIntent.id;

                    // Pago exitoso, enviar formulario
                    await submitFormData();

                } catch (error) {
                    console.error('Error:', error);
                    paymentMessage.textContent = 'Error al procesar el pago: ' + error.message;
                    paymentMessage.className = 'error';
                    loadingOverlay.classList.remove('active');
                    this.disabled = false;
                }
            });

            // Enviar datos del formulario
            async function submitFormData() {
                const form = document.getElementById('hoja-asiento-renewal-form');
                const formData = new FormData(form);

                // Añadir firma (priorizar mainSignatureData si existe)
                const signatureData = mainSignatureData || signaturePad.toDataURL();
                formData.append('signature', signatureData);

                // Añadir archivos desde fileStorage
                fileStorage['upload-dni-propietario'].forEach((file, index) => {
                    formData.append('upload_dni_propietario[]', file);
                });

                // Añadir datos adicionales
                formData.append('final_amount', currentPrice);
                formData.append('has_signature', 'true');
                formData.append('renewal_type', 'renovacion');
                formData.append('coupon_code', document.getElementById('coupon_code').value || '');
                formData.append('terms_accept', 'true');
                formData.append('payment_intent_id', paymentIntentId || '');
                formData.append('action', 'send_hoja_asiento_to_tramitfy');

                try {
                    // PASO 1: Enviar datos y crear trámite
                    console.log('📤 PASO 1: Enviando datos al servidor...');
                    const response = await fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();
                    console.log('📥 PASO 1 Respuesta:', result);

                    if (!result.success) {
                        throw new Error(result.error || 'Error al procesar el formulario');
                    }

                    console.log('✅ Datos guardados, tramiteId:', result.tramiteId);

                    // PASO 2: Esperar 2 segundos antes de enviar emails
                    console.log('⏳ Esperando 2 segundos antes de enviar emails...');
                    await new Promise(resolve => setTimeout(resolve, 2000));

                    // PASO 2: Enviar emails
                    console.log('📧 PASO 2: Enviando emails de confirmación...');
                    const submitButton = document.getElementById('ha-confirm-payment-btn');
                    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span>Enviando emails de confirmación...</span>';

                    const emailFormData = new FormData();
                    emailFormData.append('action', 'send_hoja_asiento_emails');
                    emailFormData.append('customerName', document.getElementById('customer_name').value);
                    emailFormData.append('customerEmail', document.getElementById('customer_email').value);
                    emailFormData.append('customerDni', document.getElementById('customer_dni').value);
                    emailFormData.append('customerPhone', document.getElementById('customer_phone').value);
                    emailFormData.append('finalAmount', currentPrice);
                    emailFormData.append('paymentIntentId', paymentIntentId || '');
                    emailFormData.append('tramiteId', result.tramiteId);
                    emailFormData.append('tramiteDbId', result.id);

                    const emailResponse = await fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                        method: 'POST',
                        body: emailFormData
                    });

                    const emailResult = await emailResponse.json();
                    console.log('📧 PASO 2 Respuesta:', emailResult);

                    if (!emailResult.success) {
                        console.warn('⚠️ Error al enviar emails:', emailResult.message);
                        // No bloquear el flujo si fallan los emails
                    } else {
                        console.log('✅ Emails enviados correctamente');
                    }

                    // Cerrar modal y redirigir
                    document.getElementById('ha-payment-modal').classList.remove('show');
                    alert(`✅ Formulario enviado con éxito. ID del trámite: ${result.tramiteId}`);
                    window.location.href = result.trackingUrl;

                } catch (error) {
                    console.error('❌ Error:', error);
                    const paymentMessage = document.getElementById('ha-payment-message');
                    paymentMessage.textContent = 'Error al enviar el formulario: ' + error.message;
                    paymentMessage.className = 'error';
                    document.getElementById('loading-overlay').classList.remove('active');
                    document.getElementById('ha-confirm-payment-btn').disabled = false;
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

                    // Marcar términos
                    document.querySelector('input[name="terms_accept"]').checked = true;

                    // Simular firma
                    setTimeout(() => {
                        const canvas = document.getElementById('signature-pad');
                        const ctx = canvas.getContext('2d');
                        ctx.font = '30px cursive';
                        ctx.fillStyle = '#000';
                        ctx.fillText('Admin Test', 50, 90);
                    }, 300);

                    alert('✅ Formulario auto-rellenado. Los archivos deben subirse manualmente.');
                });
            }
            <?php endif; ?>

            // Inicializar la primera página
            navigationPermitShowPage('page-personal-info');
        });
    })();
    </script>

    <?php
    return ob_get_clean();
}

// ==========================================
// FUNCIÓN 1: Enviar formulario a TRAMITFY (SIN EMAILS)
// ==========================================
function send_hoja_asiento_to_tramitfy() {
    error_log('=== HOJA DE ASIENTO SEND TO TRAMITFY: INICIO ===');
    error_log('🔍 POST Data: ' . print_r($_POST, true));
    error_log('🔍 FILES Data: ' . print_r($_FILES, true));

    try {

        $uploadDir = wp_upload_dir();
        $baseUploadPath = $uploadDir['basedir'] . '/tramitfy-permiso-navegacion/';

        if (!file_exists($baseUploadPath)) {
            mkdir($baseUploadPath, 0755, true);
        }

        $timestamp = time();

        // Preparar datos del formulario (con isset para evitar errores)
        $formData = array(
            'customerName' => isset($_POST['customer_name']) ? sanitize_text_field($_POST['customer_name']) : '',
            'customerDni' => isset($_POST['customer_dni']) ? sanitize_text_field($_POST['customer_dni']) : '',
            'customerEmail' => isset($_POST['customer_email']) ? sanitize_email($_POST['customer_email']) : '',
            'customerPhone' => isset($_POST['customer_phone']) ? sanitize_text_field($_POST['customer_phone']) : '',
            'finalAmount' => isset($_POST['final_amount']) ? floatval($_POST['final_amount']) : 29.95,
            'paymentIntentId' => isset($_POST['payment_intent_id']) ? sanitize_text_field($_POST['payment_intent_id']) : '',
            'hasSignature' => isset($_POST['has_signature']) ? sanitize_text_field($_POST['has_signature']) : '',
            'couponCode' => isset($_POST['coupon_code']) ? sanitize_text_field($_POST['coupon_code']) : '',
            'termsAccept' => isset($_POST['terms_accept']) ? sanitize_text_field($_POST['terms_accept']) : ''
        );

        error_log('✅ Datos preparados: ' . json_encode($formData));

        // Guardar firma si existe
        $signaturePath = null;
        if (isset($_POST['signature']) && !empty($_POST['signature'])) {
            error_log("🔍 Firma detectada en POST, procesando...");
            $signatureData = $_POST['signature'];
            error_log("🔍 Firma original length: " . strlen($signatureData));
            $signatureData = str_replace('data:image/png;base64,', '', $signatureData);
            $signatureData = str_replace(' ', '+', $signatureData);
            $signatureDecoded = base64_decode($signatureData);
            error_log("🔍 Firma decoded length: " . strlen($signatureDecoded));

            $signatureFilename = $timestamp . '-signature.png';
            $signaturePath = $baseUploadPath . $signatureFilename;
            file_put_contents($signaturePath, $signatureDecoded);
            error_log("✅ Firma guardada: $signaturePath (exists: " . (file_exists($signaturePath) ? 'YES' : 'NO') . ")");
        } else {
            error_log("❌ NO se detectó firma en POST");
        }

        // Generar PDF de autorización con FPDF
        require_once get_template_directory() . '/vendor/fpdf/fpdf.php';
        $pdf = new FPDF();
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 14);

        // Título y fecha
        $pdf->Cell(0, 10, utf8_decode('AUTORIZACIÓN DE REPRESENTACIÓN'), 0, 1, 'C');
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 6, 'Fecha: ' . date('d/m/Y'), 0, 1, 'R');
        $pdf->Ln(6);

        // Información de la autorización
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 10, utf8_decode('DATOS DEL AUTORIZANTE'), 0, 1, 'L');
        $pdf->SetFont('Arial', '', 11);

        $pdf->Cell(40, 8, 'Nombre completo:', 0, 0);
        $pdf->Cell(0, 8, utf8_decode($formData['customerName']), 0, 1);

        $pdf->Cell(40, 8, 'DNI/NIE:', 0, 0);
        $pdf->Cell(0, 8, $formData['customerDni'], 0, 1);
        $pdf->Ln(5);

        // Texto de la autorización
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 10, utf8_decode('AUTORIZACIÓN'), 0, 1, 'L');
        $pdf->SetFont('Arial', '', 11);

        $renewalTypes = array(
            'renovacion' => 'solicitud estándar',
            'perdida' => 'solicitud por pérdida',
            'deterioro' => 'solicitud por deterioro',
            'robo' => 'solicitud por robo'
        );
        $renewalTypeText = isset($renewalTypes[$formData['renewalType']]) ? $renewalTypes[$formData['renewalType']] : 'solicitud';

        $customerName = $formData['customerName'];
        $customerDni = $formData['customerDni'];

        $texto = "Por la presente, yo $customerName, con DNI/NIE $customerDni, AUTORIZO a Tramitfy S.L. con CIF B55388557 a actuar como mi representante legal para la tramitación y gestión del procedimiento de $renewalTypeText de permiso de navegación ante las autoridades competentes.";
        $pdf->MultiCell(0, 6, utf8_decode($texto), 0, 'J');
        $pdf->Ln(3);

        $texto2 = "Doy conformidad para que Tramitfy S.L. pueda presentar y recoger cuanta documentación sea necesaria, subsanar defectos, pagar tasas y realizar cuantas actuaciones sean precisas para la correcta finalización del procedimiento.";
        $pdf->MultiCell(0, 6, utf8_decode($texto2), 0, 'J');
        $pdf->Ln(10);

        // Firma
        error_log("🔍 Verificando firma para PDF - signaturePath: " . ($signaturePath ?: 'NULL'));
        if ($signaturePath && file_exists($signaturePath)) {
            error_log("✅ Insertando firma en PDF desde: $signaturePath");
            $pdf->Cell(0, 8, utf8_decode('Firma del autorizante:'), 0, 1);
            $pdf->Image($signaturePath, 30, $pdf->GetY(), 50, 30);
            $pdf->Ln(35);
        } else {
            error_log("❌ NO se insertó firma en PDF (path: " . ($signaturePath ?: 'NULL') . ", exists: " . (file_exists($signaturePath ?: '') ? 'YES' : 'NO') . ")");
        }

        // Pie de página legal
        $pdf->SetFont('Arial', 'I', 8);
        $pdf->MultiCell(0, 4, utf8_decode('En cumplimiento del Reglamento (UE) 2016/679 de Protección de Datos, le informamos que sus datos personales serán tratados por Tramitfy S.L. con la finalidad de gestionar su solicitud. Puede ejercer sus derechos de acceso, rectificación, supresión y portabilidad dirigiéndose a info@tramitfy.es'), 0, 'J');

        $authorizationPdfName = 'autorizacion_' . $timestamp . '.pdf';
        $authorizationPdfPath = $baseUploadPath . $authorizationPdfName;
        $pdf->Output('F', $authorizationPdfPath);

        error_log("✅ PDF de autorización generado: $authorizationPdfPath");

        // Procesar archivos adjuntos usando wp_handle_upload
        add_filter('upload_mimes', function($mimes) {
            $mimes['pdf'] = 'application/pdf';
            $mimes['jpg|jpeg'] = 'image/jpeg';
            $mimes['png'] = 'image/png';
            return $mimes;
        });

        $uploadedFiles = array();
        error_log("=== HOJA DE ASIENTO: Procesando archivos ===");

        if (!empty($_FILES)) {
            foreach ($_FILES as $fieldName => $file) {
                if (is_array($file['name'])) {
                    $file_count = count($file['name']);
                    for ($i = 0; $i < $file_count; $i++) {
                        if ($file['error'][$i] === UPLOAD_ERR_OK) {
                            $file_array = array(
                                'name'     => $file['name'][$i],
                                'type'     => $file['type'][$i],
                                'tmp_name' => $file['tmp_name'][$i],
                                'error'    => $file['error'][$i],
                                'size'     => $file['size'][$i]
                            );
                            $uploaded_file = wp_handle_upload($file_array, ['test_form' => false]);

                            if (isset($uploaded_file['file'])) {
                                $uploadedFiles[] = array(
                                    'fieldname' => $fieldName,
                                    'path' => $uploaded_file['file'],
                                    'name' => $file['name'][$i],
                                    'type' => $file['type'][$i]
                                );
                                error_log("✅ Archivo agregado: {$file['name'][$i]}");
                            } else {
                                error_log("❌ wp_handle_upload falló: " . (isset($uploaded_file['error']) ? $uploaded_file['error'] : 'sin error'));
                            }
                        }
                    }
                } else {
                    if ($file['error'] === UPLOAD_ERR_OK) {
                        $uploaded_file = wp_handle_upload($file, ['test_form' => false]);

                        if (isset($uploaded_file['file'])) {
                            $uploadedFiles[] = array(
                                'fieldname' => $fieldName,
                                'path' => $uploaded_file['file'],
                                'name' => $file['name'],
                                'type' => $file['type']
                            );
                            error_log("✅ Archivo agregado: {$file['name']}");
                        } else {
                            error_log("❌ wp_handle_upload falló: " . (isset($uploaded_file['error']) ? $uploaded_file['error'] : 'sin error'));
                        }
                    }
                }
            }
        }

        // Enviar al webhook de Node.js usando CURLFile
        $webhookUrl = 'https://46-202-128-35.sslip.io/api/herramientas/permiso-navegacion/webhook';

        // Preparar datos como strings
        $form_data = array();
        foreach ($formData as $key => $value) {
            $form_data[$key] = (string)$value;
        }

        // Agregar PDF de autorización
        if (file_exists($authorizationPdfPath)) {
            $form_data['autorizacion_pdf'] = new CURLFile($authorizationPdfPath, 'application/pdf', $authorizationPdfName);
            error_log("✅ PDF autorización agregado: $authorizationPdfName");
        }

        // Agregar firma
        if ($signaturePath && file_exists($signaturePath)) {
            $form_data['firma'] = new CURLFile($signaturePath, 'image/png', basename($signaturePath));
            error_log("✅ Firma agregada");
        }

        // Agregar archivos adjuntos
        foreach ($uploadedFiles as $file) {
            if (file_exists($file['path'])) {
                // Usar nombre del campo para categorización
                if (strpos($file['fieldname'], 'permiso') !== false || strpos($file['fieldname'], 'documento') !== false) {
                    $form_data['permiso_caducado'] = new CURLFile($file['path'], $file['type'], $file['name']);
                    error_log("✅ Permiso caducado agregado: {$file['name']}");
                } else {
                    $form_data[$file['fieldname']] = new CURLFile($file['path'], $file['type'], $file['name']);
                    error_log("✅ Archivo agregado ({$file['fieldname']}): {$file['name']}");
                }
            }
        }

        // Usar CURL con CURLFile
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $webhookUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $form_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        error_log("📡 CURL Response Code: $httpCode");
        error_log("📡 CURL Response Body: $response");
        if ($curlError) {
            error_log("❌ CURL Error: $curlError");
        }

        $responseBody = json_decode($response, true);

        if (!$responseBody || !isset($responseBody['success']) || !$responseBody['success']) {
            error_log('❌ Error: Respuesta del webhook no válida');
            wp_send_json(['success' => false, 'error' => 'Error al procesar el formulario'], 500);
            return;
        }

        // Obtener datos del webhook
        $tramiteId = $responseBody['tramiteId'];
        $tramiteDbId = $responseBody['id'];
        $trackingUrl = "https://46-202-128-35.sslip.io/seguimiento/{$tramiteDbId}";
        $dashboardUrl = "https://46-202-128-35.sslip.io/tramites/{$tramiteDbId}";

        error_log("✅ Trámite creado: $tramiteId (DB ID: $tramiteDbId)");

        // DEVOLVER RESPUESTA (LOS EMAILS SE ENVÍAN EN FUNCIÓN SEPARADA)
        error_log("📤 Devolviendo respuesta al frontend con tramiteId: $tramiteId");
        wp_send_json([
            'success' => true,
            'tramiteId' => $tramiteId,
            'id' => $tramiteDbId,
            'trackingUrl' => $trackingUrl,
            'dashboardUrl' => $dashboardUrl
        ]);

    } catch (Exception $e) {
        error_log('❌ Error in send_hoja_asiento_to_tramitfy: ' . $e->getMessage());
        error_log('❌ Stack trace: ' . $e->getTraceAsString());
        wp_send_json(['success' => false, 'error' => $e->getMessage()], 500);
    }
}

// ==========================================
// FUNCIÓN 2: Enviar EMAILS (separada del envío de datos)
// ==========================================
function send_hoja_asiento_emails() {
    error_log('=== HOJA DE ASIENTO SEND EMAILS: INICIO ===');
    error_log('🔍 POST Data for emails: ' . print_r($_POST, true));

    try {
        // Obtener datos del POST
        $customerName = isset($_POST['customerName']) ? sanitize_text_field($_POST['customerName']) : '';
        $customerEmail = isset($_POST['customerEmail']) ? sanitize_email($_POST['customerEmail']) : '';
        $customerDni = isset($_POST['customerDni']) ? sanitize_text_field($_POST['customerDni']) : '';
        $customerPhone = isset($_POST['customerPhone']) ? sanitize_text_field($_POST['customerPhone']) : '';
        $finalAmount = isset($_POST['finalAmount']) ? floatval($_POST['finalAmount']) : 29.95;
        $paymentIntentId = isset($_POST['paymentIntentId']) ? sanitize_text_field($_POST['paymentIntentId']) : '';
        $tramiteId = isset($_POST['tramiteId']) ? sanitize_text_field($_POST['tramiteId']) : '';
        $tramiteDbId = isset($_POST['tramiteDbId']) ? sanitize_text_field($_POST['tramiteDbId']) : '';

        if (!$tramiteId || !$tramiteDbId) {
            error_log('❌ Error: tramiteId o tramiteDbId no proporcionados');
            wp_send_json_error(['message' => 'tramiteId o tramiteDbId requeridos'], 400);
            return;
        }

        error_log("✅ Datos recibidos para tramiteId: $tramiteId");

        $trackingUrl = "https://46-202-128-35.sslip.io/seguimiento/{$tramiteDbId}";
        $dashboardUrl = "https://46-202-128-35.sslip.io/tramites/{$tramiteDbId}";

        // Calcular contabilidad
        $certificado = 7.61;
        $emision = 0.00;
        $totalTasas = $certificado + $emision;
        $honorariosBrutos = $finalAmount - $totalTasas;
        $honorariosNetos = round($honorariosBrutos / 1.21, 2);
        $iva = round($honorariosBrutos - $honorariosNetos, 2);

        // Texto del tipo de solicitud
        $renewalTypes = array(
            'renovacion' => 'Renovación estándar',
            'perdida' => 'Renovación por pérdida',
            'deterioro' => 'Renovación por deterioro',
            'robo' => 'Renovación por robo'
        );
        $renewalTypeText = isset($renewalTypes[$renewalType]) ? $renewalTypes[$renewalType] : 'Renovación estándar';

        error_log("💰 Contabilidad calculada - Total: $finalAmount€, Honorarios netos: $honorariosNetos€");

        // ============================================
        // EMAIL AL CLIENTE
        // ============================================
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: Tramitfy <info@tramitfy.es>'
        );

        error_log("📧 Preparando email al cliente: $customerEmail");

        $customerSubject = '✓ Solicitud Recibida - Solicitud de Hoja de Asiento';
        $customerMessage = "
        <!DOCTYPE html>
        <html>
        <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        </head>
        <body style='margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif; background-color: #f4f7fa;'>
        <table width='100%' cellpadding='0' cellspacing='0' style='background-color: #f4f7fa; padding: 40px 20px;'>
            <tr>
                <td align='center'>
                    <!-- Email Content Container -->
                    <table width='600' cellpadding='0' cellspacing='0' style='background-color: #ffffff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); overflow: hidden;'>

                        <!-- Header Gradient -->
                        <tr>
                            <td style='background: linear-gradient(135deg, rgb(1, 109, 134) 0%, rgb(0, 86, 106) 100%); padding: 45px 40px; text-align: center;'>
                                <h1 style='margin: 0 0 12px 0; color: #ffffff; font-size: 28px; font-weight: 700; letter-spacing: -0.5px;'>
                                    ✓ Solicitud Recibida
                                </h1>
                                <p style='margin: 0 0 20px 0; color: rgba(255,255,255,0.95); font-size: 16px;'>
                                    Renovación de Hoja de Asiento
                                </p>
                                <div style='background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); padding: 14px 24px; border-radius: 8px; display: inline-block;'>
                                    <p style='margin: 0; color: #ffffff; font-size: 14px; font-weight: 600;'>
                                        Número de trámite
                                    </p>
                                    <p style='margin: 6px 0 0 0; color: #ffffff; font-size: 22px; font-weight: 700; letter-spacing: 0.5px;'>
                                        {$tramiteId}
                                    </p>
                                </div>
                            </td>
                        </tr>

                        <!-- Body Content -->
                        <tr>
                            <td style='padding: 45px 40px;'>

                                <p style='margin: 0 0 24px 0; color: #2c3e50; font-size: 16px; line-height: 1.6;'>
                                    Estimado/a <strong>{$customerName}</strong>,
                                </p>

                                <p style='margin: 0 0 28px 0; color: #546e7a; font-size: 15px; line-height: 1.7;'>
                                    Hemos recibido correctamente su solicitud de solicitud de permiso de navegación. Nuestro equipo revisará su documentación y comenzará con la tramitación a la mayor brevedad posible.
                                </p>

                                <!-- Status Box -->
                                <table width='100%' cellpadding='0' cellspacing='0' style='background: linear-gradient(to right, #e3f2fd, #f0f7ff); border-radius: 10px; border-left: 4px solid rgb(1, 109, 134); margin: 32px 0;'>
                                    <tr>
                                        <td style='padding: 24px 28px;'>
                                            <table width='100%' cellpadding='8' cellspacing='0'>
                                                <tr>
                                                    <td style='color: #546e7a; font-size: 14px; font-weight: 600;'>
                                                        Estado actual:
                                                    </td>
                                                    <td align='right'>
                                                        <span style='background-color: #fff3e0; color: #e65100; padding: 6px 14px; border-radius: 20px; font-size: 13px; font-weight: 600;'>
                                                            Pendiente
                                                        </span>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td style='color: #546e7a; font-size: 14px; font-weight: 600;'>
                                                        Fecha de solicitud:
                                                    </td>
                                                    <td align='right' style='color: #2c3e50; font-size: 14px; font-weight: 600;'>
                                                        " . date('d/m/Y H:i') . "
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                </table>

                                <p style='margin: 32px 0 24px 0; color: #546e7a; font-size: 15px; line-height: 1.7;'>
                                    Puede consultar el estado de su trámite en cualquier momento desde el siguiente enlace:
                                </p>

                                <!-- CTA Button -->
                                <table width='100%' cellpadding='0' cellspacing='0' style='margin: 32px 0;'>
                                    <tr>
                                        <td align='center'>
                                            <a href='{$trackingUrl}' style='display: inline-block; background: linear-gradient(135deg, rgb(1, 109, 134) 0%, rgb(0, 86, 106) 100%); color: #ffffff; text-decoration: none; padding: 16px 40px; border-radius: 8px; font-size: 15px; font-weight: 600; box-shadow: 0 4px 12px rgba(1, 109, 134, 0.3);'>
                                                🔍 Ver Estado del Trámite
                                            </a>
                                        </td>
                                    </tr>
                                </table>

                                <p style='margin: 32px 0 0 0; color: #546e7a; font-size: 14px; line-height: 1.7;'>
                                    Le mantendremos informado del progreso de su solicitud.
                                </p>

                                <p style='margin: 32px 0 0 0; color: #2c3e50; font-size: 15px;'>
                                    Atentamente,<br>
                                    <strong style='color: rgb(1, 109, 134);'>Equipo Tramitfy</strong>
                                </p>

                            </td>
                        </tr>

                        <!-- Footer -->
                        <tr>
                            <td style='background-color: #f8f9fa; padding: 32px 40px; border-top: 1px solid #e0e0e0;'>
                                <p style='margin: 0 0 8px 0; color: #78909c; font-size: 13px; text-align: center; line-height: 1.5;'>
                                    <strong style='color: #546e7a;'>Tramitfy</strong><br>
                                    info@tramitfy.es | +34 689 170 273
                                </p>
                                <p style='margin: 8px 0 0 0; color: #90a4ae; font-size: 12px; text-align: center;'>
                                    Paseo Castellana 194 puerta B, Madrid, España
                                </p>
                            </td>
                        </tr>

                    </table>
                </td>
            </tr>
        </table>
        </body>
        </html>
        ";

        $mail_sent_customer = wp_mail($customerEmail, $customerSubject, $customerMessage, $headers);
        error_log("📧 Email cliente enviado: " . ($mail_sent_customer ? 'SÍ ✅' : 'NO ❌'));

        // ============================================
        // EMAIL AL ADMIN
        // ============================================
        error_log("📧 Preparando email al admin: ipmgroup24@gmail.com");

        $adminEmail = 'ipmgroup24@gmail.com';
        $adminSubject = '🔔 Nueva Solicitud - ' . $tramiteId . ' - Hoja de Asiento Navegación';
        $adminMessage = "
        <!DOCTYPE html>
        <html>
        <head>
        <meta charset='UTF-8'>
        </head>
        <body style='margin: 0; padding: 20px; font-family: Arial, sans-serif; background-color: #f5f5f5;'>
        <div style='max-width: 700px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);'>

            <div style='background: linear-gradient(135deg, #d32f2f 0%, #b71c1c 100%); padding: 25px 30px; color: white;'>
                <h2 style='margin: 0; font-size: 22px; font-weight: 600;'>🔔 NUEVA SOLICITUD</h2>
                <p style='margin: 6px 0 0; font-size: 14px; opacity: 0.95;'>Solicitud de Hoja de Asiento</p>
                <p style='margin: 10px 0 0; font-size: 16px; font-weight: 700; background: rgba(255,255,255,0.2); padding: 8px 12px; border-radius: 4px; display: inline-block;'>📋 {$tramiteId}</p>
            </div>

            <div style='padding: 30px;'>

                <div style='margin-bottom: 25px; background-color: #e3f2fd; padding: 16px 20px; border-radius: 6px; text-align: center;'>
                    <a href='{$dashboardUrl}' style='display: inline-block; background: linear-gradient(135deg, #1976d2 0%, #1565c0 100%); color: white; padding: 10px 24px; text-decoration: none; border-radius: 5px; font-weight: 600; font-size: 14px; box-shadow: 0 3px 8px rgba(25,118,210,0.3);'>
                        🔍 Ver Detalle Completo del Trámite
                    </a>
                </div>

                <div style='margin-bottom: 25px;'>
                    <h3 style='margin: 0 0 15px; color: #d32f2f; font-size: 16px; border-bottom: 2px solid #d32f2f; padding-bottom: 8px;'>👤 DATOS DEL CLIENTE</h3>
                    <table width='100%' cellpadding='6' cellspacing='0' style='font-size: 14px;'>
                        <tr>
                            <td style='color: #666; width: 35%;'>Nombre completo:</td>
                            <td style='color: #333; font-weight: 600;'>{$customerName}</td>
                        </tr>
                        <tr>
                            <td style='color: #666;'>DNI/NIE:</td>
                            <td style='color: #333; font-weight: 600;'>{$customerDni}</td>
                        </tr>
                        <tr>
                            <td style='color: #666;'>Email:</td>
                            <td style='color: #0066cc; font-weight: 600;'>{$customerEmail}</td>
                        </tr>
                        <tr>
                            <td style='color: #666;'>Teléfono:</td>
                            <td style='color: #333; font-weight: 600;'>{$customerPhone}</td>
                        </tr>
                        <tr>
                            <td style='color: #666;'>Tipo solicitud:</td>
                            <td style='color: #333; font-weight: 600;'>{$renewalTypeText}</td>
                        </tr>
                    </table>
                </div>

                <div style='margin-bottom: 25px; background-color: #fff8e1; padding: 18px; border-radius: 6px; border-left: 4px solid #ffa000;'>
                    <h3 style='margin: 0 0 15px; color: #f57f17; font-size: 16px;'>💰 CONTABILIDAD</h3>
                    <table width='100%' cellpadding='6' cellspacing='0' style='font-size: 14px;'>
                        <tr>
                            <td style='color: #666;'>Precio total cobrado:</td>
                            <td align='right' style='color: #333; font-weight: 700; font-size: 16px;'>" . number_format($finalAmount, 2) . " €</td>
                        </tr>
                        <tr style='border-top: 1px solid #ffe082;'>
                            <td colspan='2' style='padding-top: 12px; padding-bottom: 6px; color: #888; font-size: 13px; font-weight: 600;'>DESGLOSE:</td>
                        </tr>
                        <tr>
                            <td style='color: #666; padding-left: 15px;'>Certificado navegabilidad:</td>
                            <td align='right' style='color: #666;'>7.61 €</td>
                        </tr>
                        <tr>
                            <td style='color: #666; padding-left: 15px;'>Emisión permiso:</td>
                            <td align='right' style='color: #666;'>0.00 €</td>
                        </tr>
                        <tr>
                            <td style='color: #666; padding-left: 15px; border-bottom: 1px solid #ffe082; padding-bottom: 8px;'>Total tasas:</td>
                            <td align='right' style='color: #666; border-bottom: 1px solid #ffe082; padding-bottom: 8px;'>- " . number_format($totalTasas, 2) . " €</td>
                        </tr>
                        <tr>
                            <td style='color: #f57f17; font-weight: 700; padding-top: 8px;'>Honorarios brutos (con IVA):</td>
                            <td align='right' style='color: #f57f17; font-weight: 700; font-size: 16px; padding-top: 8px;'>" . number_format($honorariosBrutos, 2) . " €</td>
                        </tr>
                        <tr>
                            <td style='color: #666; padding-left: 15px; font-size: 13px;'>IVA (21%):</td>
                            <td align='right' style='color: #666; font-size: 13px;'>- " . number_format($iva, 2) . " €</td>
                        </tr>
                        <tr style='background-color: #fff3cd;'>
                            <td style='color: #d84315; font-weight: 700; padding: 8px; padding-left: 15px;'>Honorarios netos (sin IVA):</td>
                            <td align='right' style='color: #d84315; font-weight: 700; font-size: 17px; padding: 8px;'>" . number_format($honorariosNetos, 2) . " €</td>
                        </tr>
                    </table>
                </div>

                <div style='margin-bottom: 25px;'>
                    <h3 style='margin: 0 0 15px; color: #333; font-size: 16px;'>💳 PAGO STRIPE</h3>
                    <table width='100%' cellpadding='5' cellspacing='0' style='font-size: 13px; background-color: #f9f9f9; padding: 12px; border-radius: 4px;'>
                        <tr>
                            <td style='color: #666;'>Payment Intent ID:</td>
                            <td style='color: #333; font-family: monospace; font-size: 12px;'>{$paymentIntentId}</td>
                        </tr>
                        <tr>
                            <td style='color: #666;'>Modo Stripe:</td>
                            <td style='color: #333; font-weight: 600;'>" . HOJA_ASIENTO_STRIPE_MODE . "</td>
                        </tr>
                    </table>
                </div>

                <div style='margin-bottom: 25px;'>
                    <h3 style='margin: 0 0 15px; color: #333; font-size: 16px;'>📎 DOCUMENTOS</h3>
                    <p style='font-size: 13px; color: #666;'>Los documentos están guardados en el dashboard</p>
                </div>

                <div style='text-align: center; margin-top: 30px;'>
                    <a href='https://46-202-128-35.sslip.io' style='display: inline-block; background: linear-gradient(135deg, #0066cc 0%, #004a99 100%); color: white; padding: 14px 32px; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 15px; box-shadow: 0 4px 10px rgba(0,102,204,0.3);'>
                        🖥 Ver en Dashboard TRAMITFY
                    </a>
                </div>

            </div>

            <div style='background-color: #f5f5f5; padding: 20px; text-align: center; border-top: 1px solid #e0e0e0;'>
                <p style='margin: 0; color: #999; font-size: 12px;'>
                    Email automático generado por TRAMITFY<br>
                    Fecha: " . date('d/m/Y H:i:s') . "
                </p>
            </div>

        </div>
        </body>
        </html>
        ";

        $mail_sent_admin = wp_mail($adminEmail, $adminSubject, $adminMessage, $headers);
        error_log("📧 Email admin enviado: " . ($mail_sent_admin ? 'SÍ ✅' : 'NO ❌'));

        // Responder con éxito
        if ($mail_sent_customer && $mail_sent_admin) {
            error_log("✅ EMAILS ENVIADOS CORRECTAMENTE - Cliente: $customerEmail, Admin: $adminEmail");
            wp_send_json_success([
                'message' => 'Emails enviados correctamente',
                'tramiteId' => $tramiteId
            ]);
        } else {
            error_log("❌ ERROR AL ENVIAR EMAILS - Cliente: " . ($mail_sent_customer ? 'OK' : 'FAIL') . ", Admin: " . ($mail_sent_admin ? 'OK' : 'FAIL'));
            wp_send_json_error([
                'message' => 'Error al enviar emails',
                'customer' => $mail_sent_customer,
                'admin' => $mail_sent_admin
            ]);
        }

    } catch (Exception $e) {
        error_log('❌ Error in send_hoja_asiento_emails: ' . $e->getMessage());
        error_log('❌ Stack trace: ' . $e->getTraceAsString());
        wp_send_json_error(['message' => $e->getMessage()], 500);
    }
}

// Función para crear Payment Intent de Stripe - IGUAL QUE RECUPERAR DOCUMENTACIÓN
function create_payment_intent_hoja_asiento_renewal() {
    // Configurar Stripe dentro de la función (IGUAL QUE RECUPERAR DOCUMENTACIÓN)
    if (HOJA_ASIENTO_STRIPE_MODE === 'test') {
        $stripe_secret_key = HOJA_ASIENTO_STRIPE_TEST_SECRET_KEY;
    } else {
        $stripe_secret_key = HOJA_ASIENTO_STRIPE_LIVE_SECRET_KEY;
    }

    header('Content-Type: application/json');

    require_once get_template_directory() . '/vendor/autoload.php';

    try {
        error_log('=== NAVIGATION PERMIT PAYMENT INTENT ===');
        error_log('STRIPE MODE: ' . HOJA_ASIENTO_STRIPE_MODE);
        error_log('Using Stripe key starting with: ' . substr($stripe_secret_key, 0, 25));

        \Stripe\Stripe::setApiKey($stripe_secret_key);

        $currentKey = \Stripe\Stripe::getApiKey();
        error_log('Stripe API Key confirmed: ' . substr($currentKey, 0, 25));

        $amount = HOJA_ASIENTO_SERVICE_PRICE * 100; // 29.95 EUR = 6500 cents

        $paymentIntent = \Stripe\PaymentIntent::create([
            'amount' => $amount,
            'currency' => 'eur',
            'automatic_payment_methods' => [
                'enabled' => true,
            ],
            'description' => 'Solicitud de Hoja de Asiento',
            'metadata' => [
                'service' => 'Hoja de Asiento',
                'source' => 'tramitfy_web',
                'form' => 'renovacion_permiso',
                'mode' => HOJA_ASIENTO_STRIPE_MODE
            ]
        ]);

        error_log('Payment Intent created: ' . $paymentIntent->id);

        echo json_encode([
            'clientSecret' => $paymentIntent->client_secret,
            'debug' => [
                'mode' => HOJA_ASIENTO_STRIPE_MODE,
                'keyUsed' => substr($stripe_secret_key, 0, 25) . '...',
                'keyConfirmed' => substr($currentKey, 0, 25) . '...',
                'paymentIntentId' => $paymentIntent->id
            ]
        ]);
    } catch (Exception $e) {
        error_log('Error creating payment intent: ' . $e->getMessage());
        echo json_encode(['error' => $e->getMessage()]);
    }

    wp_die();
}

// Registrar shortcode y handlers AJAX al nivel global (IGUAL QUE RECUPERAR DOCUMENTACIÓN)
add_shortcode('hoja_asiento_form', 'hoja_asiento_form_shortcode');

add_action('wp_ajax_create_payment_intent_hoja_asiento_renewal', 'create_payment_intent_hoja_asiento_renewal');
add_action('wp_ajax_nopriv_create_payment_intent_hoja_asiento_renewal', 'create_payment_intent_hoja_asiento_renewal');

add_action('wp_ajax_send_hoja_asiento_to_tramitfy', 'send_hoja_asiento_to_tramitfy');
add_action('wp_ajax_nopriv_send_hoja_asiento_to_tramitfy', 'send_hoja_asiento_to_tramitfy');

add_action('wp_ajax_send_hoja_asiento_emails', 'send_hoja_asiento_emails');
add_action('wp_ajax_nopriv_send_hoja_asiento_emails', 'send_hoja_asiento_emails');
