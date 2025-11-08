<?php
// Asegurarse de que el archivo no sea accedido directamente
defined('ABSPATH') || exit;

// Cargar Stripe library ANTES de las funciones (IGUAL QUE RECUPERAR DOCUMENTACIÓN)
require_once(get_template_directory() . '/vendor/autoload.php');

// Configuración de Stripe AL NIVEL GLOBAL (IGUAL QUE RECUPERAR DOCUMENTACIÓN)
define('NAVIGATION_PERMIT_STRIPE_MODE', 'live'); // 'test' o 'live'

define('NAVIGATION_PERMIT_STRIPE_TEST_PUBLIC_KEY', 'pk_test_51SBOq2GXJ2PkUN8kmrKUUjCLbvY3v8sAsgr6rNtg8zHyUZjB6pFrB7Vz3Gm0l2Wm7y5xVoMap2NY8utwgdJOogNQ000qBYIX5V');
define('NAVIGATION_PERMIT_STRIPE_TEST_SECRET_KEY', 'sk_test_51SBOq2GXJ2PkUN8kFlbLBQU3pd1kTVpWsSooQzdPMcqC8jKFSykeptf5XKOtbBzwMT4yjVHM0AbHUFoncbWIe4V600wkzJwpXC');

define('NAVIGATION_PERMIT_STRIPE_LIVE_PUBLIC_KEY', 'pk_live_51QHhtNGXGHYLV5CXu3P7PrAFezBnDuf0JsZzb2AxjSsV0okn4y19VOMIjW0NUOLpaFdI3CCRhiC4fvNBDDbPhiW100KkF6Uo2x');
define('NAVIGATION_PERMIT_STRIPE_LIVE_SECRET_KEY', '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');

define('NAVIGATION_PERMIT_SERVICE_PRICE', 65.00);
define('NAVIGATION_PERMIT_TASA_CERTIFICADO', 15.00);
define('NAVIGATION_PERMIT_TASA_EMISION', 8.00);

// Seleccionar las claves según el modo (IGUAL QUE RECUPERAR DOCUMENTACIÓN)
if (NAVIGATION_PERMIT_STRIPE_MODE === 'test') {
    $stripe_public_key = NAVIGATION_PERMIT_STRIPE_TEST_PUBLIC_KEY;
    $stripe_secret_key = NAVIGATION_PERMIT_STRIPE_TEST_SECRET_KEY;
} else {
    $stripe_public_key = NAVIGATION_PERMIT_STRIPE_LIVE_PUBLIC_KEY;
    $stripe_secret_key = NAVIGATION_PERMIT_STRIPE_LIVE_SECRET_KEY;
}

/**
 * Shortcode para el formulario de renovación de permiso de navegación
 */
function navigation_permit_renewal_form_shortcode() {
    global $stripe_public_key, $stripe_secret_key;

    // Si estamos en el editor de Elementor, devolver un placeholder
    if (defined('ELEMENTOR_VERSION') &&
        class_exists('\Elementor\Plugin') &&
        \Elementor\Plugin::$instance->editor &&
        \Elementor\Plugin::$instance->editor->is_edit_mode()) {
        return '<div style="padding: 20px; background: #f0f0f0; text-align: center;">
                    <h3>Formulario de Renovación de Permiso de Navegación</h3>
                    <p>El formulario se mostrará aquí en el frontend.</p>
                </div>';
    }
    
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
            margin: 25px auto 40px auto;
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
            display: grid;
            grid-template-columns: 380px 1fr;
            min-height: fit-content;
            align-items: stretch;
        }

        /* SIDEBAR IZQUIERDO */
        .npn-sidebar {
            background: linear-gradient(180deg, rgb(var(--primary)) 0%, rgb(var(--primary-dark)) 100%);
            color: white;
            padding: 30px 25px 40px 25px;
            display: flex;
            flex-direction: column;
            gap: 25px;
            min-height: 100%;
            position: relative;
        }

        .npn-logo {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .npn-logo i {
            font-size: 28px;
        }

        .npn-headline {
            font-size: 24px;
            font-weight: 600;
            line-height: 1.3;
            margin-bottom: 8px;
        }

        .npn-subheadline {
            font-size: 13px;
            opacity: 0.92;
            line-height: 1.4;
            margin-bottom: 10px;
        }

        /* Caja de precio destacada */
        .npn-price-box {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            padding: 15px;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.25);
            margin: 15px 0 20px 0;
        }

        .npn-price-label {
            font-size: 11px;
            opacity: 0.85;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 6px;
        }

        .npn-price-amount {
            font-size: 38px;
            font-weight: 700;
            margin: 4px 0;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .npn-price-detail {
            font-size: 12px;
            opacity: 0.88;
        }

        /* Lista de beneficios */
        .npn-benefits {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin: 15px 0 20px 0;
        }

        .npn-reviews-widget {
            margin-top: 25px;
            padding: 25px 15px 20px 15px;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(5px);
        }

        .npn-benefit {
            display: flex;
            align-items: start;
            gap: 8px;
            font-size: 12px;
            line-height: 1.4;
        }

        .npn-benefit i {
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
        .npn-trust-badges {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: auto;
            padding-top: 30px;
            border-top: 1px solid rgba(255, 255, 255, 0.15);
        }

        .npn-badge {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            padding: 8px 12px;
            border-radius: 20px;
            font-size: 10px;
            display: flex;
            align-items: center;
            gap: 6px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .npn-badge i {
            font-size: 11px;
        }
        
        .npn-badge:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        /* Sidebar de autorización */
        .npn-sidebar-auth-doc {
            display: flex;
            flex-direction: column;
            gap: 20px;
            margin-top: 20px;
            padding-top: 15px;
        }
        
        /* Estética moderna para parte inferior */
        .npn-container::after {
            content: '';
            position: absolute;
            bottom: -20px;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 4px;
            background: linear-gradient(90deg, rgb(var(--primary)) 0%, rgb(var(--primary-light)) 100%);
            border-radius: 2px;
            opacity: 0.6;
        }
        
        /* Mejorar navegación para que se vea más moderna */
        .npn-navigation {
            background: linear-gradient(135deg, white 0%, #f8f9fa 100%);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        /* ÁREA PRINCIPAL DEL FORMULARIO */
        .npn-form-area {
            padding: 30px 40px 50px 40px;
            background: linear-gradient(135deg, #fafbfc 0%, #f8f9fa 100%);
            overflow-y: auto;
            min-height: 100%;
            display: flex;
            flex-direction: column;
            position: relative;
        }

        .npn-form-header {
            margin-bottom: 15px;
        }

        .npn-form-title {
            font-size: 12px;
            font-weight: 400;
            color: rgb(var(--neutral-500));
            margin-bottom: 4px;
        }

        .npn-form-subtitle {
            font-size: 13px;
            color: rgb(var(--neutral-600));
        }

        /* Panel de auto-rellenado para administradores */
        .npn-admin-panel {
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

        .npn-admin-panel-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .npn-admin-panel-title {
            font-size: 12px;
            font-weight: 600;
            opacity: 0.95;
        }

        .npn-admin-panel-subtitle {
            font-size: 10px;
            opacity: 0.85;
        }

        .npn-admin-autofill-btn {
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

        .npn-admin-autofill-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        /* Navegación modernizada */
        .npn-navigation {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            padding: 6px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .npn-nav-item {
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

        .npn-nav-item i {
            font-size: 14px;
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
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        }

        .npn-form-page.hidden {
            display: none;
        }

        .npn-form-page h3 {
            font-size: 18px;
            font-weight: 600;
            color: rgb(var(--neutral-900));
            margin: 0 0 20px 0;
        }

        /* Inputs mejorados */
        .npn-input-group {
            margin-bottom: 18px;
        }

        .npn-input-group label {
            display: block;
            font-weight: 500;
            margin-bottom: 7px;
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
            margin-bottom: 18px;
        }

        /* Upload section */
        .npn-upload-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }

        .npn-upload-item {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 12px;
            border: 2px dashed rgb(var(--neutral-300));
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            min-height: 230px;
        }

        .npn-upload-item:hover {
            border-color: rgb(var(--primary));
            background: rgba(var(--primary), 0.02);
        }

        .npn-upload-item label {
            display: block;
            font-weight: 600;
            margin-bottom: 15px;
            color: rgb(var(--neutral-800));
            font-size: 15px;
            flex-grow: 0;
            min-height: 65px;
        }

        .npn-upload-item input[type="file"] {
            width: 100%;
            padding: 6px;
            border: none;
            background: white;
            border-radius: 6px;
            font-size: 11px;
        }

        .npn-upload-item .view-example {
            display: inline-block;
            margin-top: auto;
            color: rgb(var(--primary));
            text-decoration: none;
            font-size: 11px;
            font-weight: 500;
            padding-top: 10px;
        }

        .npn-upload-item .view-example:hover {
            text-decoration: underline;
        }

        /* Layout 2 columnas para autorización */
        .npn-auth-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin: 20px 0;
        }

        .npn-auth-document {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 10px;
            font-size: 14px;
            line-height: 1.7;
            border: 2px solid rgb(var(--neutral-200));
        }

        .npn-auth-document h4 {
            font-size: 16px;
            font-weight: 700;
            color: rgb(var(--primary));
            margin-bottom: 15px;
        }

        .npn-auth-signature-area {
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .npn-signature-label {
            font-size: 14px;
            font-weight: 600;
            color: rgb(var(--neutral-700));
            margin-bottom: 12px;
            text-align: center;
        }

        /* Firma */
        .npn-signature-container {
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

        .npn-signature-clear {
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

        .npn-signature-clear:hover {
            background: rgb(var(--neutral-600));
            transform: translateY(-1px);
        }

        .npn-zoom-btn {
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

        .npn-zoom-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(var(--primary), 0.4);
        }

        /* Modal de firma avanzado */
        .npn-signature-modal {
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

        .npn-signature-modal.active {
            display: flex;
        }

        .npn-signature-modal.active ~ * .wa__popup_chat_box,
        .npn-signature-modal.active ~ * #whatsapp-button,
        .npn-signature-modal.active ~ * .wa__btn_popup {
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

        .npn-modal-content {
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

        .npn-modal-header {
            background: linear-gradient(135deg, rgb(var(--primary)) 0%, rgb(var(--primary-dark)) 100%);
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid rgba(255, 255, 255, 0.2);
        }

        .npn-modal-header h3 {
            margin: 0;
            font-size: 22px;
            font-weight: 700;
        }

        .npn-modal-close {
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

        .npn-modal-close:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: rotate(90deg);
        }

        .npn-enhanced-signature-container {
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

        .npn-signature-guide {
            position: absolute;
            top: 50%;
            left: 10px;
            right: 10px;
            z-index: 1;
            pointer-events: none;
        }

        .npn-signature-line {
            height: 2px;
            background-color: rgb(var(--primary));
            opacity: 0.5;
        }

        .npn-signature-instruction {
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

        .npn-modal-footer {
            background: #f8f9fa;
            padding: 20px;
            border-top: 2px solid rgb(var(--neutral-200));
        }

        .npn-modal-instructions {
            text-align: center;
            color: rgb(var(--neutral-600));
            font-size: 14px;
            margin-bottom: 15px;
        }

        .npn-modal-button-container {
            display: flex;
            gap: 12px;
        }

        .npn-modal-clear-btn {
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

        .npn-modal-clear-btn:hover {
            background: rgb(var(--neutral-600));
            transform: translateY(-2px);
        }

        .npn-modal-accept-btn {
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

        .npn-modal-accept-btn:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(var(--success), 0.4);
        }

        .npn-modal-accept-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Modal de pago */
        .npn-payment-modal {
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

        .npn-payment-modal.show {
            display: block;
            opacity: 1;
        }

        .npn-payment-modal-content {
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

        .npn-payment-modal.show .npn-payment-modal-content {
            transform: translateY(0);
            opacity: 1;
        }

        .npn-close-payment-modal {
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

        .npn-close-payment-modal:hover {
            color: #333;
            background-color: #f0f0f0;
        }

        #npn-stripe-container {
            margin: 0 auto;
            width: 100%;
            padding: 0;
        }

        #npn-stripe-loading {
            text-align: center;
            padding: 20px;
            margin-bottom: 15px;
        }

        .npn-stripe-spinner {
            display: inline-block;
            width: 40px;
            height: 40px;
            border: 4px solid rgba(var(--primary), 0.3);
            border-radius: 50%;
            border-top-color: rgb(var(--primary));
            animation: spin 1s linear infinite;
            margin-bottom: 15px;
        }


        .npn-confirm-payment-btn {
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

        .npn-confirm-payment-btn:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(var(--primary), 0.4);
        }

        .npn-confirm-payment-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        #npn-payment-message {
            margin: 15px 0;
            padding: 12px;
            border-radius: 8px;
            font-size: 14px;
            text-align: center;
        }

        #npn-payment-message.error {
            background: rgba(var(--error), 0.1);
            color: rgb(var(--error));
            border: 1px solid rgba(var(--error), 0.3);
        }

        #npn-payment-message.success {
            background: rgba(var(--success), 0.1);
            color: rgb(var(--success));
            border: 1px solid rgba(var(--success), 0.3);
        }

        #npn-payment-message.processing {
            background: rgba(var(--info), 0.1);
            color: rgb(var(--info));
            border: 1px solid rgba(var(--info), 0.3);
        }

        #npn-payment-message.hidden {
            display: none;
        }

        /* Términos y condiciones */
        .npn-terms {
            margin: 12px 0;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 3px solid rgb(var(--info));
        }

        .npn-terms label {
            display: flex;
            align-items: start;
            gap: 8px;
            cursor: pointer;
            font-size: 11px;
        }

        .npn-terms input[type="checkbox"] {
            margin-top: 2px;
            width: 16px;
            height: 16px;
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
            gap: 10px;
            margin-top: 15px;
        }

        .npn-btn {
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
            padding: 12px 15px;
            border-radius: 8px;
            margin: 12px 0;
            border: 2px solid rgb(var(--neutral-200));
        }

        .npn-price-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 6px;
            font-size: 13px;
        }

        .npn-price-row strong {
            color: rgb(var(--neutral-900));
        }

        .npn-price-total {
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
        .npn-coupon-container {
            margin: 12px 0;
        }

        .npn-coupon-input {
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

        .npn-coupon-message {
            margin-top: 6px;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 12px;
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

        /* File previews */
        .npn-file-preview-container {
            margin-top: 15px;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }

        .npn-file-preview-item {
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

        .npn-file-preview-item:hover {
            border-color: rgb(var(--primary));
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(var(--primary), 0.15);
        }

        .npn-file-preview-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .npn-file-preview-item i {
            font-size: 32px;
            color: rgb(var(--neutral-400));
        }

        .npn-file-remove-btn {
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

        .npn-file-preview-item:hover .npn-file-remove-btn {
            opacity: 1;
        }

        .npn-file-remove-btn:hover {
            background: rgba(185, 28, 28, 1);
            transform: scale(1.1);
        }

        .npn-file-name {
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
        .npn-upload-item input[type="file"] {
            opacity: 0;
            position: absolute;
            z-index: -1;
        }

        .npn-upload-btn {
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

        .npn-upload-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(var(--primary), 0.35);
        }
        
        /* Área de botones en uploads */
        .npn-upload-actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: auto;
            padding-top: 15px;
        }

        .npn-upload-btn i {
            font-size: 16px;
        }

        @media (max-width: 768px) {
            .npn-container {
                margin: 10px;
                border-radius: 12px;
            }

            .npn-form-title {
                font-size: 11px;
            }

            .npn-upload-grid {
                grid-template-columns: 1fr;
            }

            .npn-file-preview-item {
                width: 85px;
                height: 85px;
            }

            .npn-file-remove-btn {
                opacity: 1;
            }

            .npn-auth-layout {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .npn-button-group {
                flex-direction: column;
            }

            #signature-pad {
                display: none;
            }

            .npn-signature-clear {
                display: none;
            }

            .npn-signature-container {
                margin: 25px 0 !important;
            }

            .npn-form-page {
                padding: 20px !important;
            }

            .npn-zoom-btn {
                display: block;
                width: 100%;
                padding: 16px 24px;
                font-size: 16px;
            }
        }
    </style>

    <!-- Container principal con layout de 2 columnas -->
    <div class="npn-container">
        
        <!-- SIDEBAR IZQUIERDO -->
        <div class="npn-sidebar">
            <!-- Contenido por defecto (Páginas 1, 2 y 4) -->
            <div id="sidebar-default">

                <div>
                    <div class="npn-headline">
                        <h1>Renovar Permiso de Navegación</h1>
                    </div>
                    <div class="npn-subheadline">
                        Renueva tu permiso de navegación de forma rápida y segura. Gestión completa online sin desplazamientos.
                    </div>
                </div>

                <div class="npn-price-box">
                    <div class="npn-price-label">Precio Total</div>
                    <div class="npn-price-amount">65€</div>
                    <div class="npn-price-detail">IVA y tasas Capitanía marítima incluidas</div>
                </div>

                <div class="npn-benefits">
                    <div class="npn-benefit">
                        <i class="fa-solid fa-check"></i>
                        <span>Presentamos tu solicitud en menos de 24h desde que la recibimos</span>
                    </div>
                    <div class="npn-benefit">
                        <i class="fa-solid fa-check"></i>
                        <span>Envío de provisional en menos de 24h</span>
                    </div>
                    <div class="npn-benefit">
                        <i class="fa-solid fa-check"></i>
                        <span>Consulta el estado del trámite vía whatsapp</span>
                    </div>
                </div>

                <!-- Widget de reseñas TrustIndex -->
                <div class="npn-reviews-widget">
                    [trustindex data-widget-id=f4fbfd341d12439e0c86fae7fc2]
                </div>

            </div>

            <!-- Contenido para página de autorización (Página 3) -->
            <div id="sidebar-authorization" style="display: none;">
                <div class="npn-logo">
                    <i class="fa-solid fa-file-signature"></i>
                    <span>Autorización</span>
                </div>

                <div class="npn-sidebar-auth-doc">
                    <h4 style="font-size: 18px; font-weight: 700; color: white; margin-bottom: 15px;">
                        DOCUMENTO DE AUTORIZACIÓN
                    </h4>

                    <div style="background: rgba(255,255,255,0.1); padding: 20px; border-radius: 10px; margin-bottom: 20px; backdrop-filter: blur(10px);">
                        <p style="font-size: 14px; line-height: 1.8; margin-bottom: 15px;">
                            Yo, <strong id="sidebar-auth-name" style="color: #fff; font-size: 16px;">[Nombre]</strong>, con DNI/NIE <strong id="sidebar-auth-dni" style="color: #fff;">[DNI]</strong>, autorizo a <strong>TRAMITFY</strong> para que, en mi nombre y representación, gestione ante las autoridades competentes la renovación de mi permiso de navegación.
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
        <div class="npn-form-area">
            <form id="navigation-permit-renewal-form" action="" method="POST" enctype="multipart/form-data">
                
                <div class="npn-form-header">
                    <h2 class="npn-form-title">Solicitud de Renovación</h2>
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
                    <a href="#" class="npn-nav-item" data-page-id="page-authorization">
                        <i class="fa-solid fa-signature"></i>
                        <span>Autorización</span>
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


                    <div class="npn-button-group">
                        <button type="button" class="npn-btn npn-btn-next" data-next="page-documents">
                            Siguiente <i class="fa-solid fa-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <!-- PÁGINA 2: Documentación -->
                <div id="page-documents" class="npn-form-page hidden">
                    <h3><i class="fa-solid fa-file-alt"></i> Documentación Requerida</h3>

                    <p style="color: rgb(var(--neutral-600)); margin-bottom: 25px;">
                        Por favor, adjunte los siguientes documentos en formato PDF, JPG o PNG.
                    </p>

                    <div class="npn-upload-grid">
                        <div class="npn-upload-item">
                            <label for="upload-dni-propietario">
                                <i class="fa-solid fa-id-card"></i> DNI del Propietario *
                                <small style="display: block; color: #016d86; font-weight: 500; margin-top: 4px;">(ambas caras)</small>
                            </label>
                            <input type="file" id="upload-dni-propietario" name="upload_dni_propietario[]" accept="image/*,.pdf" multiple>
                            <div id="preview-dni-propietario" class="npn-file-preview-container"></div>
                            
                            <div class="npn-upload-actions">
                                <button type="button" class="npn-upload-btn" onclick="document.getElementById('upload-dni-propietario').click()">
                                    <i class="fa-solid fa-cloud-arrow-up"></i> Seleccionar archivos
                                </button>
                                <a href="#" class="view-example" data-doc="dni">Ver ejemplo</a>
                            </div>
                        </div>

                        <div class="npn-upload-item">
                            <label for="upload-documento-barco">
                                <i class="fa-solid fa-file-lines"></i> Documento de la Embarcación *<br>
                                <small style="font-weight: normal; font-size: 12px; opacity: 0.8; display: block; margin-top: 5px;">
                                    Suba <strong>uno o más</strong> de los siguientes: Registro Marítimo (Hoja de Asiento) <strong>O</strong> Permiso de Navegación a Renovar
                                </small>
                            </label>
                            <input type="file" id="upload-documento-barco" name="upload_documento_barco[]" accept="image/*,.pdf" multiple>
                            <div id="preview-documento-barco" class="npn-file-preview-container"></div>
                            
                            <div class="npn-upload-actions">
                                <button type="button" class="npn-upload-btn" onclick="document.getElementById('upload-documento-barco').click()">
                                    <i class="fa-solid fa-cloud-arrow-up"></i> Seleccionar archivos
                                </button>
                                <a href="#" class="view-example" data-doc="registro">Ver ejemplo</a>
                            </div>
                        </div>
                    </div>

                    <div class="npn-button-group">
                        <button type="button" class="npn-btn npn-btn-prev" data-prev="page-personal-info">
                            <i class="fa-solid fa-arrow-left"></i> Anterior
                        </button>
                        <button type="button" class="npn-btn npn-btn-next" data-next="page-authorization">
                            Siguiente <i class="fa-solid fa-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <!-- PÁGINA 3: Autorización y Firma -->
                <div id="page-authorization" class="npn-form-page hidden">
                    <h3><i class="fa-solid fa-signature"></i> Firme el Documento de Autorización</h3>

                    <p style="color: rgb(var(--neutral-600)); margin-bottom: 25px; text-align: center;" class="auth-instruction-text">
                        <span class="desktop-text">El documento de autorización se muestra en el panel izquierdo. Por favor, firme en el área inferior para completar la autorización.</span>
                        <span class="mobile-text" style="display: none;">El documento de autorización se muestra en el panel superior. Por favor, firme en el área inferior para completar la autorización.</span>
                    </p>

                    <div class="npn-signature-label" style="text-align: center; margin-bottom: 15px; font-size: 15px; font-weight: 600; color: rgb(var(--neutral-700));">
                        <i class="fa-solid fa-pen-to-square"></i> Firme aquí para autorizar
                    </div>

                    <div class="npn-signature-container" style="margin: 20px 0; text-align: center;">
                        <canvas id="signature-pad" width="800" height="200"></canvas>
                        <button type="button" class="npn-signature-clear" id="clear-signature">
                            <i class="fa-solid fa-eraser"></i> Limpiar Firma
                        </button>
                        <button type="button" class="npn-zoom-btn" id="zoom-signature">
                            <i class="fa-solid fa-search-plus"></i> Ampliar
                        </button>
                    </div>

                    <div class="npn-button-group">
                        <button type="button" class="npn-btn npn-btn-prev" data-prev="page-documents">
                            <i class="fa-solid fa-arrow-left"></i> Anterior
                        </button>
                        <button type="button" class="npn-btn npn-btn-next" data-next="page-payment">
                            Siguiente <i class="fa-solid fa-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <!-- PÁGINA 4: Pago -->
                <div id="page-payment" class="npn-form-page hidden">
                    <h3><i class="fa-solid fa-credit-card"></i> Información de Pago</h3>

                    <div class="npn-price-summary">
                        <div class="npn-price-row">
                            <span>Gestión Tramitfy + Tasas DGMM</span>
                            <span>55,35 €</span>
                        </div>
                        <div class="npn-price-row">
                            <span>IVA (21%)</span>
                            <span>9,65 €</span>
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

                    <div class="npn-terms">
                        <label>
                            <input type="checkbox" name="terms_accept" required>
                            <span>Acepto la <a href="https://tramitfy.es/politica-de-privacidad/" target="_blank">Política de Privacidad</a> y los <a href="https://tramitfy.es/terminos-y-condiciones-de-uso-2/" target="_blank">Términos y Condiciones</a> del servicio.</span>
                        </label>
                    </div>

                    <div class="npn-button-group">
                        <button type="button" class="npn-btn npn-btn-prev" data-prev="page-authorization">
                            <i class="fa-solid fa-arrow-left"></i> Anterior
                        </button>
                        <button type="button" class="npn-btn npn-btn-submit" id="show-payment-modal">
                            <i class="fa-solid fa-lock"></i> Realizar Pago Seguro
                        </button>
                    </div>
                </div>

            </form>
        </div>
    </div>

    <!-- Modal de pago -->
    <div id="npn-payment-modal" class="npn-payment-modal">
        <div class="npn-payment-modal-content">
            <span class="npn-close-payment-modal">&times;</span>

            <div id="npn-stripe-container">
                <!-- Spinner de carga mientras se inicializa -->
                <div id="npn-stripe-loading">
                    <div class="npn-stripe-spinner"></div>
                    <p>Cargando sistema de pago...</p>
                </div>

                <!-- Contenedor donde se montará el elemento de pago -->
                <div id="payment-element" class="payment-element-container"></div>

                <!-- Mensajes de estado del pago -->
                <div id="npn-payment-message" class="hidden"></div>
            </div>

            <button type="button" id="npn-confirm-payment-btn" class="npn-confirm-payment-btn">
                <i class="fa-solid fa-check-circle"></i> Confirmar Pago
            </button>
        </div>
    </div>

    <!-- Modal de firma avanzado -->
    <div id="signature-modal-advanced" class="npn-signature-modal">
        <div class="npn-modal-content">
            <div class="npn-modal-header">
                <h3><i class="fa-solid fa-pen-fancy"></i> Firma Digital</h3>
                <button class="npn-modal-close" id="close-modal">
                    <i class="fa-solid fa-times"></i>
                </button>
            </div>

            <div class="npn-enhanced-signature-container">
                <div class="npn-signature-guide">
                    <div class="npn-signature-line"></div>
                    <div class="npn-signature-instruction">FIRME AQUÍ</div>
                </div>
                <canvas id="enhanced-signature-canvas"></canvas>
            </div>

            <div class="npn-modal-footer">
                <p class="npn-modal-instructions">
                    <i class="fa-solid fa-hand-pointer"></i> Use el dedo para firmar en el área indicada
                </p>
                <div class="npn-modal-button-container">
                    <button class="npn-modal-clear-btn" id="modal-clear-btn">
                        <i class="fa-solid fa-eraser"></i> Borrar
                    </button>
                    <button class="npn-modal-accept-btn" id="modal-accept-btn" disabled>
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
            
            // 🛡️ PROTECCIÓN ANTI-DUPLICADOS
            let isSubmitting = false;
            let submitController = null;
            let currentPrice = <?php echo NAVIGATION_PERMIT_SERVICE_PRICE; ?>;
            const basePrice = <?php echo NAVIGATION_PERMIT_SERVICE_PRICE; ?>;

            // Almacenamiento de archivos
            const fileStorage = {
                'upload-dni-propietario': [],
                'upload-documento-barco': []
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
                        previewItem.className = 'npn-file-preview-item';
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
                        fileName.className = 'npn-file-name';
                        fileName.textContent = file.name.length > 12 ? file.name.substring(0, 12) + '...' : file.name;
                        previewItem.appendChild(fileName);

                        // Botón de eliminar
                        const removeBtn = document.createElement('div');
                        removeBtn.className = 'npn-file-remove-btn';
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
            initFileUpload('upload-documento-barco', 'preview-documento-barco');

            // Navegación entre páginas
            const formPages = document.querySelectorAll('.npn-form-page');
            const navItems = document.querySelectorAll('.npn-nav-item');
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
            document.querySelectorAll('.npn-btn-next').forEach(btn => {
                btn.addEventListener('click', function() {
                    if (navigationPermitValidateCurrentPage()) {
                        const nextPage = this.getAttribute('data-next');
                        navigationPermitShowPage(nextPage);
                    }
                });
            });

            document.querySelectorAll('.npn-btn-prev').forEach(btn => {
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
                const currentPage = document.querySelector('.npn-form-page:not(.hidden)');

                // Validación especial para página de documentos
                if (currentPage.id === 'page-documents') {
                    if (fileStorage['upload-dni-propietario'].length === 0) {
                        alert('Por favor, suba al menos un archivo de DNI del Propietario.');
                        return false;
                    }
                    if (fileStorage['upload-documento-barco'].length === 0) {
                        alert('Por favor, suba al menos un documento de la embarcación (Registro Marítimo o Permiso de Navegación).');
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

                const loadingIndicator = document.getElementById('npn-stripe-loading');
                const stripeContainer = document.getElementById('payment-element');
                const paymentMessage = document.getElementById('npn-payment-message');

                // Limpiar elementos anteriores
                if (stripeContainer) stripeContainer.innerHTML = '';
                if (paymentMessage) {
                    paymentMessage.className = 'hidden';
                    paymentMessage.textContent = '';
                }

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
                const stripePublicKey = '<?php echo (NAVIGATION_PERMIT_STRIPE_MODE === "test") ? NAVIGATION_PERMIT_STRIPE_TEST_PUBLIC_KEY : NAVIGATION_PERMIT_STRIPE_LIVE_PUBLIC_KEY; ?>';
                console.log('💳 Usando clave:', stripePublicKey.substring(0, 15) + '...');
                console.log('💳 Modo:', '<?php echo NAVIGATION_PERMIT_STRIPE_MODE; ?>');
                stripe = Stripe(stripePublicKey);
                console.log('✅ Stripe object creado:', stripe);

                try {
                    console.log('💳 Creando Payment Intent...');
                    const totalAmountCents = Math.round(currentPrice * 100);

                    const response = await fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `action=create_payment_intent_navigation_permit_renewal&amount=${totalAmountCents}`
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
                document.getElementById('npn-payment-modal').classList.add('show');

                // SIEMPRE reinicializar Stripe para obtener un nuevo Payment Intent
                console.log('🔄 Reinicializando Stripe para nuevo intento de pago...');
                setTimeout(() => {
                    initializeStripe();
                }, 300);
            });

            // Cerrar modal de pago
            document.querySelector('.npn-close-payment-modal').addEventListener('click', function() {
                document.getElementById('npn-payment-modal').classList.remove('show');
            });

            document.getElementById('npn-payment-modal').addEventListener('click', function(event) {
                if (event.target === this) {
                    this.classList.remove('show');
                }
            });

            // Confirmar pago desde el modal
            document.getElementById('npn-confirm-payment-btn').addEventListener('click', async function() {
                // 🛡️ PROTECCIÓN ANTI-DUPLICADOS - Verificar si ya está procesando
                if (isSubmitting) {
                    console.warn('⚠️ Envío ya en proceso, ignorando clic adicional');
                    return;
                }
                
                // Marcar como procesando inmediatamente
                isSubmitting = true;
                
                // Cancelar cualquier envío anterior si existe
                if (submitController) {
                    submitController.abort();
                    console.log('🚫 Cancelando envío anterior');
                }
                
                // Crear nuevo controller para este envío
                submitController = new AbortController();
                
                const paymentMessage = document.getElementById('npn-payment-message');
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
                const form = document.getElementById('navigation-permit-renewal-form');
                // Crear FormData MANUAL para evitar archivos duplicados del HTML
                const formData = new FormData();

                // Añadir todos los campos del formulario manualmente
                formData.append('customer_name', document.getElementById('customer_name').value);
                formData.append('customer_dni', document.getElementById('customer_dni').value);
                formData.append('customer_email', document.getElementById('customer_email').value);
                formData.append('customer_phone', document.getElementById('customer_phone').value);

                // Añadir firma (priorizar mainSignatureData si existe)
                const signatureData = mainSignatureData || signaturePad.toDataURL();
                formData.append('signature', signatureData);

                // Añadir archivos desde fileStorage
                console.log('🔍 FileStorage al enviar:', {
                    'upload-dni-propietario': fileStorage['upload-dni-propietario'].length,
                    'upload-documento-barco': fileStorage['upload-documento-barco'].length
                });
                
                fileStorage['upload-dni-propietario'].forEach((file, index) => {
                    console.log(`📎 Añadiendo DNI archivo ${index}:`, file.name, file.size);
                    formData.append('upload_dni_propietario[]', file);
                });
                fileStorage['upload-documento-barco'].forEach((file, index) => {
                    console.log(`📎 Añadiendo documento archivo ${index}:`, file.name, file.size);
                    formData.append('upload_documento_barco[]', file);
                });

                // Añadir datos adicionales
                formData.append('final_amount', currentPrice);
                formData.append('has_signature', 'true');
                formData.append('renewal_type', 'renovacion');
                formData.append('coupon_code', document.getElementById('coupon_code').value || '');
                formData.append('terms_accept', 'true');
                formData.append('payment_intent_id', paymentIntentId || '');
                formData.append('action', 'send_navigation_permit_to_tramitfy');

                // Ya no necesitamos deshabilitar inputs porque creamos FormData manual

                // DEBUG: Mostrar todos los datos que se van a enviar
                console.log('🔍 DEBUGGING FormData antes del envío:');
                console.log('📊 FileStorage state:', fileStorage);
                for (let [key, value] of formData.entries()) {
                    if (value instanceof File) {
                        console.log(`📎 ${key}: [FILE] ${value.name} (${value.size} bytes, type: ${value.type})`);
                    } else {
                        console.log(`📝 ${key}: ${value}`);
                    }
                }

                try {
                    // PASO 1: Enviar datos y crear trámite
                    console.log('📤 PASO 1: Enviando datos al servidor...');
                    const response = await fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                        method: 'POST',
                        body: formData,
                        signal: submitController.signal // Usar controller para cancelación
                    });

                    console.log('🌐 Response status:', response.status);
                    console.log('🌐 Response ok:', response.ok);
                    console.log('🌐 Response headers:', Array.from(response.headers.entries()));
                    
                    const responseText = await response.text();
                    console.log('📜 Raw response:', responseText);
                    
                    let result;
                    try {
                        result = JSON.parse(responseText);
                        console.log('📥 PASO 1 Respuesta (parsed):', result);
                    } catch (parseError) {
                        console.error('❌ Error parsing JSON response:', parseError);
                        console.log('📜 Response was:', responseText);
                        throw new Error('Respuesta del servidor no válida');
                    }

                    if (!result.success) {
                        throw new Error(result.error || 'Error al procesar el formulario');
                    }

                    console.log('✅ Datos guardados, tramiteId:', result.tramiteId);

                    // PASO 2: Esperar 2 segundos antes de enviar emails
                    console.log('⏳ Esperando 2 segundos antes de enviar emails...');
                    await new Promise(resolve => setTimeout(resolve, 2000));

                    // PASO 2: Enviar emails
                    console.log('📧 PASO 2: Enviando emails de confirmación...');
                    const submitButton = document.getElementById('npn-confirm-payment-btn');
                    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span>Enviando emails de confirmación...</span>';

                    const emailFormData = new FormData();
                    emailFormData.append('action', 'send_navigation_permit_emails');
                    emailFormData.append('customerName', document.getElementById('customer_name').value);
                    emailFormData.append('customerEmail', document.getElementById('customer_email').value);
                    emailFormData.append('customerDni', document.getElementById('customer_dni').value);
                    emailFormData.append('customerPhone', document.getElementById('customer_phone').value);
                    emailFormData.append('renewalType', 'renovacion');
                    emailFormData.append('finalAmount', currentPrice);
                    emailFormData.append('paymentIntentId', paymentIntentId || '');
                    emailFormData.append('tramiteId', result.tramiteId);
                    emailFormData.append('tramiteDbId', result.id);

                    const emailResponse = await fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                        method: 'POST',
                        body: emailFormData,
                        signal: submitController.signal // Usar controller para cancelación
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
                    document.getElementById('npn-payment-modal').classList.remove('show');
                    alert(`✅ Formulario enviado con éxito. ID del trámite: ${result.tramiteId}`);
                    
                    // 🛡️ NO resetear isSubmitting aquí - el usuario va a ser redirigido
                    window.location.href = result.trackingUrl;

                } catch (error) {
                    console.error('❌ Error:', error);
                    
                    // 🛡️ Resetear flag de protección en caso de error
                    isSubmitting = false;
                    
                    const paymentMessage = document.getElementById('npn-payment-message');
                    
                    // Mensaje específico para timeouts
                    if (error.name === 'TimeoutError' || error.message.includes('timeout')) {
                        paymentMessage.textContent = 'El envío está tomando más tiempo de lo esperado. Por favor, intente de nuevo en unos minutos.';
                    } else if (error.name === 'AbortError') {
                        paymentMessage.textContent = 'El envío fue cancelado. Por favor, intente de nuevo.';
                        console.log('🚫 Envío cancelado por el usuario o timeout');
                    } else {
                        paymentMessage.textContent = 'Error al enviar el formulario: ' + error.message;
                    }
                    
                    paymentMessage.className = 'error';
                    document.getElementById('loading-overlay').classList.remove('active');
                    const submitBtn = document.getElementById('npn-confirm-payment-btn');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fa-solid fa-check-circle"></i> Confirmar Pago';
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

    <!-- Modal para mostrar ejemplos de documentos -->
    <div id="document-popup" style="display: none; position: fixed; z-index: 999999; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.8); backdrop-filter: blur(5px);">
        <div style="position: relative; background-color: #fff; margin: 5% auto; padding: 0; width: 90%; max-width: 800px; border-radius: 12px; box-shadow: 0 20px 40px rgba(0,0,0,0.3); animation: slideIn 0.3s ease;">
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 20px 25px; border-bottom: 1px solid #e0e6ed; background: linear-gradient(135deg, #1976d2 0%, #1565c0 100%); color: white; border-radius: 12px 12px 0 0;">
                <h3 style="margin: 0; font-size: 20px; font-weight: 600;">
                    <i class="fa-solid fa-image" style="margin-right: 10px;"></i>
                    Ejemplo de Documento
                </h3>
                <span class="close-popup" style="color: rgba(255,255,255,0.8); font-size: 28px; font-weight: bold; cursor: pointer; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; border-radius: 50%; transition: all 0.2s ease;">&times;</span>
            </div>
            <div style="padding: 25px; text-align: center;">
                <img id="document-example-image" src="" alt="Ejemplo de documento" style="max-width: 100%; height: auto; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);" />
                <div style="margin-top: 20px; padding: 15px; background-color: #e3f2fd; border-radius: 8px; border-left: 4px solid #1976d2;">
                    <p style="margin: 0; color: #1976d2; font-weight: 600; font-size: 14px;">
                        <i class="fa-solid fa-info-circle" style="margin-right: 8px;"></i>
                        Este es un ejemplo de cómo debe ser el documento que necesitas subir
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Sistema de ejemplos de documentos
    document.addEventListener('DOMContentLoaded', function() {
        const popup = document.getElementById('document-popup');
        const closePopup = document.querySelector('.close-popup');
        const exampleImage = document.getElementById('document-example-image');

        // Manejar clicks en "Ver ejemplo"
        document.querySelectorAll('.view-example').forEach(link => {
            link.addEventListener('click', function(event) {
                event.preventDefault();
                const docType = this.getAttribute('data-doc');
                
                // Configurar imagen según el tipo de documento
                if (docType === 'dni') {
                    exampleImage.src = '/wp-content/uploads/exampledocs/dni-comprador.jpg';
                } else if (docType === 'registro') {
                    exampleImage.src = '/wp-content/uploads/exampledocs/permiso-caducado.jpg';
                } else {
                    // Fallback para otros tipos de documento
                    const baseUrl = '<?php echo get_template_directory_uri(); ?>/assets/examples/';
                    exampleImage.src = baseUrl + docType + '.jpg';
                }
                
                // Mostrar modal
                popup.style.display = 'block';
                document.body.style.overflow = 'hidden';
                
                // Animación de entrada
                setTimeout(() => {
                    popup.style.opacity = '1';
                }, 10);
            });
        });

        // Cerrar modal
        function closeModal() {
            popup.style.opacity = '0';
            document.body.style.overflow = 'auto';
            setTimeout(() => {
                popup.style.display = 'none';
            }, 300);
        }

        closePopup.addEventListener('click', closeModal);
        
        // Cerrar al hacer click fuera del modal
        popup.addEventListener('click', function(event) {
            if (event.target === popup) {
                closeModal();
            }
        });

        // Cerrar con ESC
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && popup.style.display === 'block') {
                closeModal();
            }
        });
    });
    </script>

    <style>
    #document-popup {
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    
    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(-50px) scale(0.9);
        }
        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }
    
    .close-popup:hover {
        background-color: rgba(255,255,255,0.2) !important;
        transform: scale(1.1);
    }
    
    .view-example {
        color: #1976d2 !important;
        text-decoration: none !important;
        font-weight: 500 !important;
        cursor: pointer !important;
        transition: color 0.2s ease !important;
    }
    
    .view-example:hover {
        color: #0d47a1 !important;
        text-decoration: underline !important;
    }
    </style>

    <?php
    return ob_get_clean();
}

// ==========================================
// FUNCIÓN 1: Enviar formulario a TRAMITFY (SIN EMAILS)
// ==========================================
function send_navigation_permit_to_tramitfy() {
    error_log('=== PERMISO NAVEGACIÓN SEND TO TRAMITFY: INICIO ===');
    error_log('📊 Límites del servidor:');
    error_log('   - upload_max_filesize: ' . ini_get('upload_max_filesize'));
    error_log('   - post_max_size: ' . ini_get('post_max_size'));
    error_log('   - max_file_uploads: ' . ini_get('max_file_uploads'));
    error_log('   - memory_limit: ' . ini_get('memory_limit'));
    
    // DEBUG ADICIONAL: Información de la request HTTP
    error_log('🌐 REQUEST INFO:');
    error_log('   - Content-Type: ' . (isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : 'NO_SET'));
    error_log('   - Content-Length: ' . (isset($_SERVER['CONTENT_LENGTH']) ? $_SERVER['CONTENT_LENGTH'] . ' bytes' : 'NO_SET'));
    error_log('   - Request Method: ' . (isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'NO_SET'));
    error_log('   - HTTP User Agent: ' . (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'NO_SET'));
    
    // DEBUG: Conteos de campos recibidos
    error_log('📊 DATOS RECIBIDOS:');
    error_log('   - POST fields count: ' . count($_POST));
    error_log('   - FILES fields count: ' . count($_FILES));
    error_log('   - Total $_FILES keys: ' . implode(', ', array_keys($_FILES)));
    
    // DEBUG: Estado específico de archivos esperados
    $expected_files = ['upload_dni_propietario', 'upload_documento_barco'];
    foreach ($expected_files as $field) {
        if (isset($_FILES[$field])) {
            error_log("✅ Campo $field está presente");
            if (is_array($_FILES[$field]['name'])) {
                error_log("   - Es array con " . count($_FILES[$field]['name']) . " archivos");
                for ($i = 0; $i < count($_FILES[$field]['name']); $i++) {
                    if (!empty($_FILES[$field]['name'][$i])) {
                        error_log("   - Archivo $i: {$_FILES[$field]['name'][$i]} (" . $_FILES[$field]['size'][$i] . " bytes, error: {$_FILES[$field]['error'][$i]})");
                    }
                }
            } else {
                error_log("   - Archivo único: {$_FILES[$field]['name']} (" . $_FILES[$field]['size'] . " bytes, error: {$_FILES[$field]['error']})");
            }
        } else {
            error_log("❌ Campo $field NO está presente en \$_FILES");
        }
    }
    
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
            'renewalType' => isset($_POST['renewal_type']) ? sanitize_text_field($_POST['renewal_type']) : 'renovacion',
            'finalAmount' => isset($_POST['final_amount']) ? floatval($_POST['final_amount']) : 65.00,
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
            'renovacion' => 'renovación estándar',
            'perdida' => 'renovación por pérdida',
            'deterioro' => 'renovación por deterioro',
            'robo' => 'renovación por robo'
        );
        $renewalTypeText = isset($renewalTypes[$formData['renewalType']]) ? $renewalTypes[$formData['renewalType']] : 'renovación';

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
        error_log("=== PERMISO NAVEGACIÓN: Procesando archivos ===");

        if (!empty($_FILES)) {
            foreach ($_FILES as $fieldName => $file) {
                if (is_array($file['name'])) {
                    $file_count = count($file['name']);
                    for ($i = 0; $i < $file_count; $i++) {
                        error_log("🔍 Procesando archivo {$i}: {$file['name'][$i]} - Error: {$file['error'][$i]} - Tamaño: " . ($file['size'][$i] ?? 'unknown'));
                        
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
                                throw new Exception("Error al procesar archivo {$file['name'][$i]}: " . (isset($uploaded_file['error']) ? $uploaded_file['error'] : 'Error desconocido'));
                            }
                        } else {
                            // Manejar errores específicos de upload
                            $error_message = '';
                            switch ($file['error'][$i]) {
                                case UPLOAD_ERR_INI_SIZE:
                                case UPLOAD_ERR_FORM_SIZE:
                                    $error_message = "El archivo {$file['name'][$i]} es demasiado grande. Tamaño máximo permitido: " . ini_get('upload_max_filesize');
                                    break;
                                case UPLOAD_ERR_PARTIAL:
                                    $error_message = "El archivo {$file['name'][$i]} se subió parcialmente";
                                    break;
                                case UPLOAD_ERR_NO_FILE:
                                    $error_message = "No se seleccionó archivo para {$fieldName}";
                                    break;
                                case UPLOAD_ERR_NO_TMP_DIR:
                                    $error_message = "Error del servidor: falta directorio temporal";
                                    break;
                                case UPLOAD_ERR_CANT_WRITE:
                                    $error_message = "Error del servidor: no se puede escribir archivo";
                                    break;
                                default:
                                    $error_message = "Error desconocido al subir {$file['name'][$i]} (código: {$file['error'][$i]})";
                            }
                            error_log("❌ Error de upload: " . $error_message);
                            throw new Exception($error_message);
                        }
                    }
                } else {
                    error_log("🔍 Procesando archivo único: {$file['name']} - Error: {$file['error']} - Tamaño: " . ($file['size'] ?? 'unknown'));
                    
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
                            throw new Exception("Error al procesar archivo {$file['name']}: " . (isset($uploaded_file['error']) ? $uploaded_file['error'] : 'Error desconocido'));
                        }
                    } else {
                        // Manejar errores específicos de upload para archivo único
                        $error_message = '';
                        switch ($file['error']) {
                            case UPLOAD_ERR_INI_SIZE:
                            case UPLOAD_ERR_FORM_SIZE:
                                $error_message = "El archivo {$file['name']} es demasiado grande. Tamaño máximo permitido: " . ini_get('upload_max_filesize');
                                break;
                            case UPLOAD_ERR_PARTIAL:
                                $error_message = "El archivo {$file['name']} se subió parcialmente";
                                break;
                            case UPLOAD_ERR_NO_FILE:
                                $error_message = "No se seleccionó archivo para {$fieldName}";
                                break;
                            case UPLOAD_ERR_NO_TMP_DIR:
                                $error_message = "Error del servidor: falta directorio temporal";
                                break;
                            case UPLOAD_ERR_CANT_WRITE:
                                $error_message = "Error del servidor: no se puede escribir archivo";
                                break;
                            default:
                                $error_message = "Error desconocido al subir {$file['name']} (código: {$file['error']})";
                        }
                        error_log("❌ Error de upload: " . $error_message);
                        throw new Exception($error_message);
                    }
                }
            }
        }

        // Enviar al webhook de Node.js usando CURLFile
        $webhookUrl = 'https://tramitfy.org/api/herramientas/permiso-navegacion/webhook';

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
        $trackingUrl = "https://tramitfy.es/pago-realizado-con-exito/";
        $dashboardUrl = "https://tramitfy.org/tramites/{$tramiteDbId}";

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
        error_log('❌ Error in send_navigation_permit_to_tramitfy: ' . $e->getMessage());
        error_log('❌ Stack trace: ' . $e->getTraceAsString());
        wp_send_json(['success' => false, 'error' => $e->getMessage()], 500);
    }
}

// ==========================================
// FUNCIÓN 2: Enviar EMAILS (separada del envío de datos)
// ==========================================
function send_navigation_permit_emails() {
    error_log('=== PERMISO NAVEGACIÓN SEND EMAILS: INICIO ===');
    error_log('🔍 POST Data for emails: ' . print_r($_POST, true));

    try {
        // Obtener datos del POST
        $customerName = isset($_POST['customerName']) ? sanitize_text_field($_POST['customerName']) : '';
        $customerEmail = isset($_POST['customerEmail']) ? sanitize_email($_POST['customerEmail']) : '';
        $customerDni = isset($_POST['customerDni']) ? sanitize_text_field($_POST['customerDni']) : '';
        $customerPhone = isset($_POST['customerPhone']) ? sanitize_text_field($_POST['customerPhone']) : '';
        $renewalType = isset($_POST['renewalType']) ? sanitize_text_field($_POST['renewalType']) : 'renovacion';
        $finalAmount = isset($_POST['finalAmount']) ? floatval($_POST['finalAmount']) : 65.00;
        $paymentIntentId = isset($_POST['paymentIntentId']) ? sanitize_text_field($_POST['paymentIntentId']) : '';
        $tramiteId = isset($_POST['tramiteId']) ? sanitize_text_field($_POST['tramiteId']) : '';
        $tramiteDbId = isset($_POST['tramiteDbId']) ? sanitize_text_field($_POST['tramiteDbId']) : '';

        if (!$tramiteId || !$tramiteDbId) {
            error_log('❌ Error: tramiteId o tramiteDbId no proporcionados');
            wp_send_json_error(['message' => 'tramiteId o tramiteDbId requeridos'], 400);
            return;
        }

        error_log("✅ Datos recibidos para tramiteId: $tramiteId");

        $trackingUrl = "https://tramitfy.es/pago-realizado-con-exito/";
        $dashboardUrl = "https://tramitfy.org/tramites/{$tramiteDbId}";

        // Calcular contabilidad
        $certificado = 15.00;
        $emision = 8.00;
        $totalTasas = $certificado + $emision;
        $honorariosBrutos = $finalAmount - $totalTasas;
        $honorariosNetos = round($honorariosBrutos / 1.21, 2);
        $iva = round($honorariosBrutos - $honorariosNetos, 2);

        // Texto del tipo de renovación
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

        $customerSubject = '✓ Solicitud Recibida - Renovar Permiso de Navegación';
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
                                <div style='margin: 0 0 12px 0; color: #ffffff; font-size: 28px; font-weight: 700; letter-spacing: -0.5px;'>
                                    ✓ Solicitud Recibida
                                </div>
                                <p style='margin: 0 0 20px 0; color: rgba(255,255,255,0.95); font-size: 16px;'>
                                    Renovación de Permiso de Navegación
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
                                    Hemos recibido correctamente su solicitud de renovación de permiso de navegación. Nuestro equipo revisará su documentación <strong>(incluido DNI por ambas caras)</strong> y comenzará con la tramitación a la mayor brevedad posible.
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
                                    Le mantendremos informado del progreso de su solicitud por email y le contactaremos si necesitamos documentación adicional.
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
        $adminSubject = '🔔 Nueva Solicitud - ' . $tramiteId . ' - Renovación Permiso Navegación';
        $adminMessage = "
        <!DOCTYPE html>
        <html>
        <head>
        <meta charset='UTF-8'>
        </head>
        <body style='margin: 0; padding: 20px; font-family: Arial, sans-serif; background-color: #f5f5f5;'>
        <div style='max-width: 700px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);'>

            <div style='background: linear-gradient(135deg, #d32f2f 0%, #b71c1c 100%); padding: 25px 30px; color: white;'>
                <div style='margin: 0; font-size: 22px; font-weight: 600;'>🔔 NUEVA SOLICITUD</div>
                <p style='margin: 6px 0 0; font-size: 14px; opacity: 0.95;'>Renovar Permiso de Navegación</p>
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
                            <td style='color: #666;'>Tipo renovación:</td>
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
                            <td align='right' style='color: #666;'>15.00 €</td>
                        </tr>
                        <tr>
                            <td style='color: #666; padding-left: 15px;'>Emisión permiso:</td>
                            <td align='right' style='color: #666;'>8.00 €</td>
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
                    <h3 style='margin: 0 0 15px; color: #333; font-size: 16px;'>PAGO STRIPE</h3>
                    <table width='100%' cellpadding='5' cellspacing='0' style='font-size: 13px; background-color: #f9f9f9; padding: 12px; border-radius: 4px;'>
                        <tr>
                            <td style='color: #666;'>Payment Intent ID:</td>
                            <td style='color: #333; font-family: monospace; font-size: 12px;'>{$paymentIntentId}</td>
                        </tr>
                        <tr>
                            <td style='color: #666;'>Modo Stripe:</td>
                            <td style='color: #333; font-weight: 600;'>" . NAVIGATION_PERMIT_STRIPE_MODE . "</td>
                        </tr>
                    </table>
                </div>

                <div style='margin-bottom: 25px;'>
                    <h3 style='margin: 0 0 15px; color: #333; font-size: 16px;'>📎 DOCUMENTOS</h3>
                    <p style='font-size: 13px; color: #666;'>Los documentos están guardados en el dashboard (DNI por ambas caras incluido)</p>
                </div>

                <div style='text-align: center; margin-top: 30px;'>
                    <a href='https://tramitfy.org' style='display: inline-block; background: linear-gradient(135deg, #0066cc 0%, #004a99 100%); color: white; padding: 14px 32px; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 15px; box-shadow: 0 4px 10px rgba(0,102,204,0.3);'>
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
        error_log('❌ Error in send_navigation_permit_emails: ' . $e->getMessage());
        error_log('❌ Stack trace: ' . $e->getTraceAsString());
        wp_send_json_error(['message' => $e->getMessage()], 500);
    }
}

// Función para crear Payment Intent de Stripe - IGUAL QUE RECUPERAR DOCUMENTACIÓN
function create_payment_intent_navigation_permit_renewal() {
    // Configurar Stripe dentro de la función (IGUAL QUE RECUPERAR DOCUMENTACIÓN)
    if (NAVIGATION_PERMIT_STRIPE_MODE === 'test') {
        $stripe_secret_key = NAVIGATION_PERMIT_STRIPE_TEST_SECRET_KEY;
    } else {
        $stripe_secret_key = NAVIGATION_PERMIT_STRIPE_LIVE_SECRET_KEY;
    }

    header('Content-Type: application/json');

    require_once get_template_directory() . '/vendor/autoload.php';

    try {
        error_log('=== NAVIGATION PERMIT PAYMENT INTENT ===');
        error_log('STRIPE MODE: ' . NAVIGATION_PERMIT_STRIPE_MODE);
        error_log('Using Stripe key starting with: ' . substr($stripe_secret_key, 0, 25));

        \Stripe\Stripe::setApiKey($stripe_secret_key);

        $currentKey = \Stripe\Stripe::getApiKey();
        error_log('Stripe API Key confirmed: ' . substr($currentKey, 0, 25));

        $amount = NAVIGATION_PERMIT_SERVICE_PRICE * 100; // 65.00 EUR = 6500 cents

        $paymentIntent = \Stripe\PaymentIntent::create([
            'amount' => $amount,
            'currency' => 'eur',
            'automatic_payment_methods' => [
                'enabled' => true,
            ],
            'description' => 'Renovar Permiso de Navegación',
            'metadata' => [
                'service' => 'Permiso Navegación',
                'source' => 'tramitfy_web',
                'form' => 'renovacion_permiso',
                'mode' => NAVIGATION_PERMIT_STRIPE_MODE
            ]
        ]);

        error_log('Payment Intent created: ' . $paymentIntent->id);

        echo json_encode([
            'clientSecret' => $paymentIntent->client_secret,
            'debug' => [
                'mode' => NAVIGATION_PERMIT_STRIPE_MODE,
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
add_shortcode('navigation_permit_renewal_form', 'navigation_permit_renewal_form_shortcode');

add_action('wp_ajax_create_payment_intent_navigation_permit_renewal', 'create_payment_intent_navigation_permit_renewal');
add_action('wp_ajax_nopriv_create_payment_intent_navigation_permit_renewal', 'create_payment_intent_navigation_permit_renewal');

add_action('wp_ajax_send_navigation_permit_to_tramitfy', 'send_navigation_permit_to_tramitfy');
add_action('wp_ajax_nopriv_send_navigation_permit_to_tramitfy', 'send_navigation_permit_to_tramitfy');

add_action('wp_ajax_send_navigation_permit_emails', 'send_navigation_permit_emails');
add_action('wp_ajax_nopriv_send_navigation_permit_emails', 'send_navigation_permit_emails');
