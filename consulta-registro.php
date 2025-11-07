<?php
/*
Plugin Name: Consulta del Registro
Description: Formulario simplificado para consulta del registro de embarcaciones con sistema modal profesional
Version: 2.0
Author: Tramitfy
*/

// Asegurarse de que el archivo no sea accedido directamente
defined('ABSPATH') || exit;

// Cargar Stripe library ANTES de las funciones (IGUAL QUE HOJA DE ASIENTO)
require_once(get_template_directory() . '/vendor/autoload.php');

// Configuración de Stripe AL NIVEL GLOBAL (IGUAL QUE HOJA DE ASIENTO)
define('CONSULTA_STRIPE_MODE', 'live'); // 'test' o 'live'

define('CONSULTA_STRIPE_TEST_PUBLIC_KEY', 'pk_test_51SBOq2GXJ2PkUN8kmrKUUjCLbvY3v8sAsgr6rNtg8zHyUZjB6pFrB7Vz3Gm0l2Wm7y5xVoMap2NY8utwgdJOogNQ000qBYIX5V');
define('CONSULTA_STRIPE_TEST_SECRET_KEY', 'sk_test_51SBOq2GXJ2PkUN8kFlbLBQU3pd1kTVpWsSooQzdPMcqC8jKFSykeptf5XKOtbBzwMT4yjVHM0AbHUFoncbWIe4V600wkzJwpXC');

define('CONSULTA_STRIPE_LIVE_PUBLIC_KEY', 'pk_live_51QHhtNGXGHYLV5CXu3P7PrAFezBnDuf0JsZzb2AxjSsV0okn4y19VOMIjW0NUOLpaFdI3CCRhiC4fvNBDDbPhiW100KkF6Uo2x');
define('CONSULTA_STRIPE_LIVE_SECRET_KEY', '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');

define('CONSULTA_SERVICE_PRICE', 29.99);

// Seleccionar las claves según el modo (IGUAL QUE HOJA DE ASIENTO)
if (CONSULTA_STRIPE_MODE === 'test') {
    $consulta_stripe_public_key = CONSULTA_STRIPE_TEST_PUBLIC_KEY;
    $consulta_stripe_secret_key = CONSULTA_STRIPE_TEST_SECRET_KEY;
} else {
    $consulta_stripe_public_key = CONSULTA_STRIPE_LIVE_PUBLIC_KEY;
    $consulta_stripe_secret_key = CONSULTA_STRIPE_LIVE_SECRET_KEY;
}

// ============================================
// SISTEMA DE LOGS TRAMITFY
// ============================================

if (!function_exists('tramitfy_consulta_log')) {
    function tramitfy_consulta_log($message, $context = 'CONSULTA-MODAL', $level = 'INFO') {
        $log_dir = get_template_directory() . '/logs';

        if (!is_dir($log_dir)) {
            @mkdir($log_dir, 0755, true);
        }

        $log_file = $log_dir . '/tramitfy-' . date('Y-m-d') . '.log';

        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';

        if (is_array($message) || is_object($message)) {
            $message = json_encode($message, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }

        $log_entry = sprintf(
            "[%s] [%s] [%s] [IP:%s] %s\n",
            $timestamp,
            $level,
            $context,
            $ip,
            $message
        );

        @file_put_contents($log_file, $log_entry, FILE_APPEND);

        if ($level === 'ERROR' || $level === 'CRITICAL') {
            error_log("TRAMITFY [$context] $level: $message");
        }
    }
}

// ============================================
// AUTO-RELLENADO ADMIN
// ============================================

function consulta_admin_autofill_data() {
    // Datos pre-rellenados por defecto para demostrar funcionalidad
    // Los usuarios pueden modificarlos o usarlos directamente
    $ejemplos_barcos = [
        [
            'customer_email' => 'carlos.martinez@gmail.com',
            'boat_name' => 'MAR AZUL',
            'matricula' => '3-BA-2-456'
        ],
        [
            'customer_email' => 'ana.rodriguez@hotmail.es',
            'boat_name' => 'GAVIOTA BLANCA',
            'matricula' => '2-MA-3-789'
        ],
        [
            'customer_email' => 'miguel.fernandez@yahoo.es',
            'boat_name' => 'ESTRELLA DEL MAR',
            'matricula' => '4-MU-1-234'
        ],
        [
            'customer_email' => 'laura.gonzalez@outlook.com',
            'boat_name' => 'BRISA MARINA',
            'matricula' => '6-PM-2-567'
        ],
        [
            'customer_email' => 'jose.lopez@icloud.com',
            'boat_name' => 'VIENTO DEL SUR',
            'matricula' => '1-CA-4-890'
        ]
    ];
    
    // Siempre devolver ejemplo (selección consistente basada en hora del día)
    $index = (int)(date('H') / 5) % count($ejemplos_barcos); // Cambia cada 5 horas
    return $ejemplos_barcos[$index];
}

// ============================================
// CREAR PAYMENT INTENT
// ============================================

if (!function_exists('consulta_create_payment_intent_handler')) {
    function consulta_create_payment_intent_handler() {
        global $consulta_stripe_secret_key;

        // Verificar que sea AJAX
        if (!wp_doing_ajax()) {
            wp_die('Access denied');
        }

        try {
            tramitfy_consulta_log('Iniciando creación Payment Intent', 'PAYMENT-INTENT', 'INFO');

            $customer_email = sanitize_email($_POST['customer_email'] ?? '');
            $boat_name = sanitize_text_field($_POST['boat_name'] ?? '');
            $matricula = sanitize_text_field($_POST['matricula'] ?? '');

            if (empty($customer_email) || empty($boat_name) || empty($matricula)) {
                throw new Exception('Todos los campos son obligatorios');
            }

            $amount = intval(CONSULTA_SERVICE_PRICE * 100); // Convertir a centavos

            \Stripe\Stripe::setApiKey($consulta_stripe_secret_key);

            $intent = \Stripe\PaymentIntent::create([
                'amount' => $amount,
                'currency' => 'eur',
                'metadata' => [
                    'tramite_type' => 'consulta-registro',
                    'customer_email' => $customer_email,
                    'boat_name' => $boat_name,
                    'matricula' => $matricula,
                    'final_amount' => CONSULTA_SERVICE_PRICE
                ]
            ]);

            tramitfy_consulta_log('Payment Intent creado: ' . $intent->id, 'PAYMENT-INTENT', 'INFO');

            wp_send_json_success([
                'client_secret' => $intent->client_secret,
                'amount' => CONSULTA_SERVICE_PRICE
            ]);

        } catch (Exception $e) {
            tramitfy_consulta_log('Error Payment Intent: ' . $e->getMessage(), 'PAYMENT-INTENT', 'ERROR');
            wp_send_json_error($e->getMessage());
        }
    }
}

add_action('wp_ajax_consulta_create_payment_intent', 'consulta_create_payment_intent_handler');
add_action('wp_ajax_nopriv_consulta_create_payment_intent', 'consulta_create_payment_intent_handler');

// ============================================
// CONFIRMAR PAGO
// ============================================

if (!function_exists('consulta_confirm_payment_handler')) {
    function consulta_confirm_payment_handler() {
        if (!wp_doing_ajax()) {
            wp_die('Access denied');
        }

        try {
            tramitfy_consulta_log('Iniciando confirmación de pago', 'CONFIRM-PAYMENT', 'INFO');

            $payment_intent_id = sanitize_text_field($_POST['payment_intent_id'] ?? '');
            $customer_email = sanitize_email($_POST['customer_email'] ?? '');
            $boat_name = sanitize_text_field($_POST['boat_name'] ?? '');
            $matricula = sanitize_text_field($_POST['matricula'] ?? '');

            if (empty($payment_intent_id) || empty($customer_email)) {
                throw new Exception('Datos de pago incompletos');
            }

            // Enviar webhook al sistema API
            $webhook_data = [
                'tramiteType' => 'consulta-registro',
                'customerName' => explode('@', $customer_email)[0],
                'customerEmail' => $customer_email,
                'customerDni' => '',
                'customerPhone' => '',
                'boatName' => $boat_name,
                'matricula' => $matricula,
                'finalAmount' => CONSULTA_SERVICE_PRICE,
                'paymentIntentId' => $payment_intent_id,
                'timestamp' => date('Y-m-d H:i:s'),
                'formType' => 'consulta-registro-modal'
            ];

            $webhook_url = 'https://tramitfy.org/api/herramientas/documentacion/webhook';

            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $webhook_url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($webhook_data),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Accept: application/json'
                ],
                CURLOPT_TIMEOUT => 30
            ]);

            $webhook_response = curl_exec($curl);
            $webhook_http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);

            tramitfy_consulta_log('Webhook response: ' . $webhook_response . ' HTTP: ' . $webhook_http_code, 'WEBHOOK', 'INFO');

            if ($webhook_http_code === 200) {
                wp_send_json_success([
                    'message' => 'Pago confirmado y trámite registrado correctamente',
                    'tramite_id' => $payment_intent_id
                ]);
            } else {
                throw new Exception('Error al registrar el trámite en el sistema');
            }

        } catch (Exception $e) {
            tramitfy_consulta_log('Error confirmación pago: ' . $e->getMessage(), 'CONFIRM-PAYMENT', 'ERROR');
            wp_send_json_error($e->getMessage());
        }
    }
}

add_action('wp_ajax_consulta_confirm_payment', 'consulta_confirm_payment_handler');
add_action('wp_ajax_nopriv_consulta_confirm_payment', 'consulta_confirm_payment_handler');

// ============================================
// SHORTCODE PRINCIPAL
// ============================================

if (!function_exists('consulta_registro_form_shortcode')) {
    function consulta_registro_form_shortcode($atts) {
        global $consulta_stripe_public_key;
        
        // Si estamos en el editor de Elementor, devolver placeholder
        if (defined('ELEMENTOR_VERSION') &&
            class_exists('\Elementor\Plugin') &&
            \Elementor\Plugin::$instance->editor &&
            \Elementor\Plugin::$instance->editor->is_edit_mode()) {
            return '<div style="padding: 20px; background: #f0f0f0; text-align: center;">
                        <h3>🔍 Formulario Consulta del Registro</h3>
                        <p>El formulario se mostrará aquí en el frontend.</p>
                    </div>';
        }
        
        $admin_data = consulta_admin_autofill_data();
        
        ob_start();
        ?>
        <style>
        /* Variables CSS como otros formularios */
        :root {
            --primary: 1, 109, 134;
            --primary-light: 8, 145, 178;
            --secondary: 248, 249, 250;
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
            --spacing-xs: 0.25rem;
            --spacing-sm: 0.5rem;
            --spacing-md: 1rem;
            --spacing-lg: 1.5rem;
            --spacing-xl: 2rem;
            --spacing-xxl: 2.5rem;
            --radius-sm: 0.25rem;
            --radius-md: 0.375rem;
            --radius-lg: 0.5rem;
            --radius-xl: 0.75rem;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.1);
            --shadow-md: 0 4px 6px rgba(0,0,0,0.1), 0 1px 3px rgba(0,0,0,0.08);
            --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05);
            --transition-fast: 150ms ease-in-out;
            --transition-normal: 250ms ease-in-out;
            --z-10: 10;
            --z-20: 20;
            --z-30: 30;
            --z-40: 40;
            --z-50: 50;
        }

        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        /* Layout de 2 columnas igual que otros formularios */
        .tramitfy-two-column {
            display: grid !important;
            grid-template-columns: 300px 1fr !important;
            grid-template-areas: "sidebar content" !important;
            gap: 0;
            align-items: stretch;
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            margin: 15px auto;
            max-width: 900px;
            min-height: 400px;
        }

        /* Panel Lateral Izquierdo */
        .tramitfy-sidebar {
            grid-area: sidebar;
            background: #016d86;
            border-radius: 12px 0 0 12px;
            padding: 20px 20px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
        }

        .sidebar-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        .sidebar-icon {
            width: 42px;
            height: 42px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .sidebar-title h3 {
            margin: 0 0 5px 0;
            color: #ffffff;
            font-size: 18px;
            font-weight: 700;
            line-height: 1.2;
        }

        .sidebar-title p {
            margin: 0;
            color: rgba(255, 255, 255, 0.85);
            font-size: 12px;
            line-height: 1.2;
        }

        .sidebar-content {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
        }

        .sidebar-price-highlight {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            margin-bottom: 20px;
        }

        .sidebar-price-label {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 8px;
        }

        .sidebar-price-amount {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .sidebar-price-includes {
            font-size: 12px;
            opacity: 0.85;
            line-height: 1.5;
        }

        .price-badge {
            background: rgba(255, 255, 255, 0.3);
            color: white;
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 12px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            display: inline-block;
        }

        .price-value-text {
            font-size: 13px;
            opacity: 0.9;
            margin-top: 8px;
            font-weight: 500;
        }

        .sidebar-info-box {
            background: rgba(255, 255, 255, 0.12);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .sidebar-info-box h4 {
            margin: 0 0 8px 0;
            color: #ffffff;
            font-size: 14px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .sidebar-info-box p {
            margin: 0 0 4px 0;
            color: rgba(255, 255, 255, 0.95);
            font-size: 12px;
            line-height: 1.4;
        }

        .sidebar-info-box p:last-child {
            margin-bottom: 0;
        }

        .sidebar-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(255, 255, 255, 0.2);
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            color: white;
            margin-top: 15px;
        }

        /* Panel Principal del Formulario */
        .tramitfy-main-form {
            grid-area: content;
            padding: 30px 40px;
            background: #ffffff;
            border-radius: 0 12px 12px 0;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .form-header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid rgba(var(--neutral-200), 0.5);
        }

        .form-title {
            font-size: 28px;
            font-weight: 700;
            color: rgb(var(--neutral-800));
            margin-bottom: 8px;
        }

        .form-subtitle {
            font-size: 14px;
            color: #059669;
            font-weight: 500;
            background: rgba(5, 150, 105, 0.1);
            padding: 8px 16px;
            border-radius: 20px;
            border: 1px solid rgba(5, 150, 105, 0.2);
            display: inline-block;
            margin-top: 5px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-label {
            display: block;
            font-weight: 600;
            color: rgb(var(--neutral-700));
            margin-bottom: 8px;
            font-size: 14px;
        }

        .form-input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid rgba(var(--neutral-300), 0.8);
            border-radius: var(--radius-lg);
            font-size: 16px;
            transition: var(--transition-normal);
            background: white;
            color: rgb(var(--neutral-800));
        }

        .form-input:focus {
            outline: none;
            border-color: rgb(var(--primary));
            box-shadow: 0 0 0 3px rgba(var(--primary), 0.1);
        }

        .form-input::placeholder {
            color: rgb(var(--neutral-500));
        }

        .form-compact-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 18px;
        }

        .form-compact-row .form-group {
            margin-bottom: 0;
        }

        .primary-button {
            background: linear-gradient(135deg, rgb(var(--primary)), rgb(var(--primary-light)));
            color: white;
            border: none;
            padding: 14px 24px;
            border-radius: var(--radius-lg);
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: var(--transition-normal);
            margin-top: 20px;
            box-shadow: var(--shadow-md);
        }

        .primary-button:hover:not(:disabled) {
            transform: translateY(-1px);
            box-shadow: var(--shadow-lg);
        }

        .primary-button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        /* Estilos mejorados para conversión */
        .enhanced-cta {
            display: flex !important;
            align-items: center;
            justify-content: space-between;
            padding: 16px 24px !important;
            font-size: 17px !important;
            position: relative;
            overflow: hidden;
        }

        .enhanced-cta::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.6s;
        }

        .enhanced-cta:hover::before {
            left: 100%;
        }

        .button-icon {
            font-size: 16px;
            margin-right: 8px;
        }

        .button-text {
            flex: 1;
            text-align: center;
            font-weight: 600;
        }

        .button-price {
            background: rgba(255, 255, 255, 0.2);
            padding: 4px 12px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 16px;
        }

        .trust-indicators {
            margin-top: 20px;
            text-align: left;
        }

        .trust-item {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
            font-size: 13px;
            color: rgb(var(--neutral-600));
        }

        .trust-item span {
            margin-left: 8px;
            font-weight: 500;
        }

        .prefill-notice {
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.2);
            border-radius: 8px;
            padding: 10px 15px;
            margin-top: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .prefill-icon {
            font-size: 14px;
        }

        .prefill-text {
            font-size: 13px;
            color: #1e40af;
            font-weight: 500;
        }

        /* Modal de pago - IGUAL QUE HOJA-ASIENTO */
        .consulta-payment-modal {
            display: none;
            position: fixed;
            z-index: 999999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(5px);
        }

        .consulta-payment-modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .consulta-payment-modal-content {
            background-color: #fff;
            margin: 15% auto;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            transform: scale(0.7);
            opacity: 0;
            transition: all 0.3s ease;
        }

        .consulta-payment-modal.show .consulta-payment-modal-content {
            transform: scale(1);
            opacity: 1;
        }

        .consulta-close-payment-modal {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            line-height: 1;
            margin: -10px -10px 20px 20px;
            padding: 10px;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .consulta-close-payment-modal:hover {
            background-color: #f0f0f0;
            color: #000;
        }

        #payment-element {
            margin: 20px 0;
            padding: 20px;
            border: 1px solid #e1e5e9;
            border-radius: 8px;
            background: #fafafa;
        }

        .consulta-confirm-payment-btn {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            margin-top: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .consulta-confirm-payment-btn:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(40, 167, 69, 0.4);
        }

        .consulta-confirm-payment-btn:disabled {
            background: #6c757d;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        #consulta-payment-message {
            margin-top: 15px;
            padding: 15px;
            border-radius: 8px;
            font-weight: 500;
        }

        #consulta-payment-message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        #consulta-payment-message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        #consulta-payment-message.processing {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        #consulta-payment-message.hidden {
            display: none;
        }

        .consulta-stripe-spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #016d86;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            margin: 0 auto 15px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        #consulta-stripe-loading {
            text-align: center;
            padding: 20px;
        }

        /* Responsive - Tablets y móviles */
        @media (max-width: 768px) {
            .tramitfy-two-column {
                grid-template-columns: 1fr !important;
                grid-template-areas: "sidebar" "content" !important;
                gap: 0;
                margin: 10px;
            }

            .tramitfy-sidebar {
                border-radius: 12px 12px 0 0;
                min-width: auto;
                width: auto;
                min-height: auto;
                padding: 20px;
            }

            .tramitfy-main-form {
                border-radius: 0 0 12px 12px;
                padding: 30px 20px;
                min-height: auto;
            }

            .form-compact-row {
                grid-template-columns: 1fr;
            }

            .form-title {
                font-size: 24px;
            }

            .sidebar-price-amount {
                font-size: 28px;
            }
        }

        @media (max-width: 480px) {
            .tramitfy-two-column {
                border-radius: 8px;
                margin: 5px;
            }

            .tramitfy-sidebar {
                padding: 15px;
            }

            .tramitfy-main-form {
                padding: 20px 15px;
            }

            .sidebar-price-amount {
                font-size: 24px;
            }
        }
        </style>

        <div class="tramitfy-layout-wrapper">
            <div class="tramitfy-two-column">
                <!-- Panel Lateral Izquierdo -->
                <aside class="tramitfy-sidebar">
                    <div class="sidebar-price-highlight">
                        <div class="price-badge">⚡ Respuesta inmediata</div>
                        <div class="sidebar-price-amount"><?php echo CONSULTA_SERVICE_PRICE; ?>€</div>
                        <div class="price-value-text">Información oficial completa</div>
                    </div>

                    <!-- Widget de Trustpilot -->
                    <script defer async src='https://cdn.trustindex.io/loader.js?f4fbfd341d12439e0c86fae7fc2'></script>
                </aside>

                <!-- Panel Principal del Formulario -->
                <main class="tramitfy-main-form">
                    <div class="form-header">
                        <h1 class="form-title">Consulta del Registro</h1>
                        <p class="form-subtitle">✅ Información oficial en minutos • 🔒 Pago 100% seguro</p>
                        <div class="prefill-notice">
                            <span class="prefill-icon">💡</span>
                            <span class="prefill-text">Datos de ejemplo incluidos - Puedes modificarlos por los tuyos</span>
                        </div>
                    </div>

                    <form id="consultaRegistroForm">
                        <div class="form-group">
                            <label class="form-label" for="customer_email">Tu email *</label>
                            <input type="email" class="form-input" id="customer_email" name="customer_email"
                                   value="<?php echo esc_attr($admin_data['customer_email'] ?? ''); ?>" 
                                   placeholder="carlos.martinez@gmail.com" required>
                        </div>

                        <div class="form-compact-row">
                            <div class="form-group">
                                <label class="form-label" for="boat_name">Nombre embarcación *</label>
                                <input type="text" class="form-input" id="boat_name" name="boat_name"
                                       value="<?php echo esc_attr($admin_data['boat_name'] ?? ''); ?>" 
                                       placeholder="ESTRELLA DEL MAR" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="matricula">Matrícula *</label>
                                <input type="text" class="form-input" id="matricula" name="matricula"
                                       value="<?php echo esc_attr($admin_data['matricula'] ?? ''); ?>" 
                                       placeholder="3-BA-2-456" required>
                            </div>
                        </div>

                        <button type="button" id="show-payment-modal" class="primary-button enhanced-cta">
                            <span class="button-icon">🚀</span>
                            <span class="button-text">Obtener Información Ahora</span>
                            <span class="button-price"><?php echo CONSULTA_SERVICE_PRICE; ?>€</span>
                        </button>
                        
                        <div class="trust-indicators">
                            <div class="trust-item">🔒 <span>Pago 100% seguro con Stripe</span></div>
                            <div class="trust-item">⚡ <span>Información en menos de 5 minutos</span></div>
                            <div class="trust-item">✅ <span>Datos oficiales del registro marítimo</span></div>
                        </div>
                    </form>
                </main>
            </div>
        </div>

        <!-- Modal de pago -->
        <div id="consulta-payment-modal" class="consulta-payment-modal">
            <div class="consulta-payment-modal-content">
                <span class="consulta-close-payment-modal">&times;</span>
                
                <h3 style="text-align: center; color: #016d86; margin-bottom: 20px;">
                    Pago - <?php echo CONSULTA_SERVICE_PRICE; ?>€
                </h3>

                <div id="consulta-stripe-container">
                    <!-- Spinner de carga mientras se inicializa -->
                    <div id="consulta-stripe-loading">
                        <div class="consulta-stripe-spinner"></div>
                        <p>Cargando sistema de pago...</p>
                    </div>

                    <!-- Contenedor donde se montará el elemento de pago -->
                    <div id="payment-element" class="payment-element-container" style="display: none;"></div>

                    <!-- Mensajes de estado del pago -->
                    <div id="consulta-payment-message" class="hidden"></div>
                </div>

                <button type="button" id="consulta-confirm-payment-btn" class="consulta-confirm-payment-btn" disabled>
                    Confirmar Pago
                </button>
            </div>
        </div>

        <script src="https://js.stripe.com/v3/"></script>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const stripe = Stripe('<?php echo $consulta_stripe_public_key; ?>');
            let elements;
            let paymentElement;
            let currentClientSecret = null;

            const showPaymentModalBtn = document.getElementById('show-payment-modal');
            const paymentModal = document.getElementById('consulta-payment-modal');
            const closePaymentModalBtn = document.querySelector('.consulta-close-payment-modal');
            const confirmPaymentBtn = document.getElementById('consulta-confirm-payment-btn');
            const paymentMessageDiv = document.getElementById('consulta-payment-message');
            const stripeLoading = document.getElementById('consulta-stripe-loading');
            const paymentElementDiv = document.getElementById('payment-element');

            // Función para mostrar mensajes
            function showMessage(text, type = 'info') {
                paymentMessageDiv.textContent = text;
                paymentMessageDiv.className = type;
            }

            // Validar formulario
            function validateForm() {
                const email = document.getElementById('customer_email').value.trim();
                const boatName = document.getElementById('boat_name').value.trim();
                const matricula = document.getElementById('matricula').value.trim();

                if (!email || !boatName || !matricula) {
                    alert('Por favor, complete todos los campos obligatorios.');
                    return false;
                }

                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(email)) {
                    alert('Por favor, introduzca un email válido.');
                    return false;
                }

                return true;
            }

            // Mostrar modal y crear Payment Intent
            showPaymentModalBtn.addEventListener('click', function() {
                if (!validateForm()) return;

                paymentModal.classList.add('show');
                createPaymentIntent();
            });

            // Cerrar modal
            closePaymentModalBtn.addEventListener('click', function() {
                paymentModal.classList.remove('show');
            });

            // Crear Payment Intent
            async function createPaymentIntent() {
                try {
                    stripeLoading.style.display = 'block';
                    paymentElementDiv.style.display = 'none';
                    confirmPaymentBtn.disabled = true;

                    const formData = new FormData();
                    formData.append('action', 'consulta_create_payment_intent');
                    formData.append('customer_email', document.getElementById('customer_email').value);
                    formData.append('boat_name', document.getElementById('boat_name').value);
                    formData.append('matricula', document.getElementById('matricula').value);

                    const response = await fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();

                    if (result.success) {
                        currentClientSecret = result.data.client_secret;
                        await initializeStripeElements(currentClientSecret);
                    } else {
                        throw new Error(result.data || 'Error al crear el pago');
                    }

                } catch (error) {
                    console.error('Error:', error);
                    showMessage('Error: ' + error.message, 'error');
                    stripeLoading.style.display = 'none';
                }
            }

            // Inicializar Stripe Elements
            async function initializeStripeElements(clientSecret) {
                try {
                    elements = stripe.elements({
                        clientSecret: clientSecret,
                        appearance: {
                            theme: 'stripe',
                            variables: {
                                colorPrimary: '#016d86'
                            }
                        }
                    });

                    paymentElement = elements.create('payment');
                    paymentElement.mount('#payment-element');

                    paymentElement.on('ready', function() {
                        stripeLoading.style.display = 'none';
                        paymentElementDiv.style.display = 'block';
                        confirmPaymentBtn.disabled = false;
                    });

                    paymentElement.on('change', function(event) {
                        if (event.error) {
                            showMessage(event.error.message, 'error');
                        } else {
                            showMessage('', 'hidden');
                        }
                    });

                } catch (error) {
                    console.error('Error inicializando Stripe:', error);
                    showMessage('Error inicializando el sistema de pago', 'error');
                }
            }

            // Confirmar pago
            confirmPaymentBtn.addEventListener('click', async function() {
                if (!currentClientSecret) return;

                confirmPaymentBtn.disabled = true;
                showMessage('Procesando pago...', 'processing');

                try {
                    const {error, paymentIntent} = await stripe.confirmPayment({
                        elements,
                        confirmParams: {
                            return_url: window.location.href
                        },
                        redirect: 'if_required'
                    });

                    if (error) {
                        throw new Error(error.message);
                    }

                    if (paymentIntent && paymentIntent.status === 'succeeded') {
                        await confirmPaymentWithServer(paymentIntent.id);
                    }

                } catch (error) {
                    console.error('Error:', error);
                    showMessage('Error: ' + error.message, 'error');
                    confirmPaymentBtn.disabled = false;
                }
            });

            // Confirmar pago con servidor
            async function confirmPaymentWithServer(paymentIntentId) {
                try {
                    const formData = new FormData();
                    formData.append('action', 'consulta_confirm_payment');
                    formData.append('payment_intent_id', paymentIntentId);
                    formData.append('customer_email', document.getElementById('customer_email').value);
                    formData.append('boat_name', document.getElementById('boat_name').value);
                    formData.append('matricula', document.getElementById('matricula').value);

                    const response = await fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();

                    if (result.success) {
                        showMessage('Pago completado. Consulta registrada correctamente.', 'success');
                        setTimeout(function() {
                            paymentModal.classList.remove('show');
                            document.getElementById('consultaRegistroForm').innerHTML = 
                                '<div style="text-align: center; padding: 30px; background: #d4edda; border-radius: 8px; color: #155724;">' +
                                '<h3>Consulta Registrada</h3>' +
                                '<p>Recibirá los resultados por email en 24-48h.</p>' +
                                '</div>';
                        }, 2000);
                    } else {
                        throw new Error(result.data || 'Error al confirmar el pago');
                    }

                } catch (error) {
                    console.error('Error:', error);
                    showMessage('Error: ' + error.message, 'error');
                    confirmPaymentBtn.disabled = false;
                }
            }
        });
        </script>

        <?php
        return ob_get_clean();
    }
}

// Registrar shortcode
add_shortcode('consulta_registro_form', 'consulta_registro_form_shortcode');