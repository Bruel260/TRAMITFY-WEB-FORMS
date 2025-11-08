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
define('CONSULTA_STRIPE_MODE', 'test'); // 'test' o 'live' - CAMBIADO A TEST

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
    // DISABLED - Sin prefill para admin, todos usan placeholders grises
    return [];
}

function get_demo_data_for_placeholders() {
    // Datos para placeholders visuales (aparecen en gris)
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
    
    $index = (int)(date('H') / 5) % count($ejemplos_barcos);
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
            $terms_accept = sanitize_text_field($_POST['terms_accept'] ?? '');

            if (empty($payment_intent_id) || empty($customer_email)) {
                throw new Exception('Datos de pago incompletos');
            }

            if ($terms_accept !== 'true') {
                throw new Exception('Debe aceptar los términos y condiciones');
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
                'formType' => 'consulta-registro-modal',
                'termsAccept' => $terms_accept
            ];

            $webhook_url = 'https://tramitfy.org/api/herramientas/consulta-registro/webhook';

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
// ENVIAR EMAILS DE CONFIRMACIÓN
// ============================================

if (!function_exists('send_consulta_emails')) {
    function send_consulta_emails() {
        try {
            tramitfy_consulta_log('Iniciando envío de emails de confirmación', 'EMAILS', 'INFO');
            
            // Obtener datos del POST
            $customerName = sanitize_text_field($_POST['customerName'] ?? '');
            $customerEmail = sanitize_email($_POST['customerEmail'] ?? '');
            $boatName = sanitize_text_field($_POST['boatName'] ?? '');
            $matricula = sanitize_text_field($_POST['matricula'] ?? '');
            $finalAmount = floatval($_POST['finalAmount'] ?? CONSULTA_SERVICE_PRICE);
            $paymentIntentId = sanitize_text_field($_POST['paymentIntentId'] ?? '');
            $tramiteId = sanitize_text_field($_POST['tramiteId'] ?? '');
            
            if (!$tramiteId || !$customerEmail) {
                throw new Exception('Datos incompletos para envío de emails');
            }
            
            // Configurar headers de email
            $headers = array(
                'Content-Type: text/html; charset=UTF-8',
                'From: Tramitfy <info@tramitfy.es>'
            );
            
            // ============================================
            // EMAIL AL CLIENTE
            // ============================================
            
            $customerSubject = '✓ Consulta del Registro Recibida - ' . $tramiteId;
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
                        <table width='600' cellpadding='0' cellspacing='0' style='background-color: #ffffff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); overflow: hidden;'>
                            <tr>
                                <td style='background: linear-gradient(135deg, rgb(1, 109, 134) 0%, rgb(0, 86, 106) 100%); padding: 45px 40px; text-align: center;'>
                                    <div style='margin: 0 0 12px 0; color: #ffffff; font-size: 28px; font-weight: 700;'>
                                        ✓ Consulta Recibida
                                    </div>
                                    <p style='margin: 0; color: rgba(255,255,255,0.95); font-size: 16px;'>
                                        Consulta del Registro Marítimo
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <td style='padding: 40px;'>
                                    <p style='margin: 0 0 20px 0; color: #333; font-size: 16px;'>
                                        Estimado/a <strong>{$customerName}</strong>,
                                    </p>
                                    <p style='margin: 0 0 25px 0; color: #555; font-size: 15px; line-height: 1.6;'>
                                        Hemos recibido correctamente su solicitud de consulta del registro marítimo. 
                                        Nuestro equipo procesará la información y le enviaremos los resultados por email.
                                    </p>
                                    <table cellpadding='0' cellspacing='0' style='width: 100%; background: #f8f9fa; border-radius: 8px; margin: 25px 0;'>
                                        <tr>
                                            <td style='padding: 20px;'>
                                                <h3 style='margin: 0 0 15px 0; color: #016d86; font-size: 18px;'>Detalles de la Consulta</h3>
                                                <table cellpadding='8' cellspacing='0' style='width: 100%;'>
                                                    <tr>
                                                        <td style='color: #666; width: 120px;'>ID Trámite:</td>
                                                        <td style='color: #333; font-weight: 600;'>{$tramiteId}</td>
                                                    </tr>
                                                    <tr>
                                                        <td style='color: #666;'>Embarcación:</td>
                                                        <td style='color: #333; font-weight: 600;'>{$boatName}</td>
                                                    </tr>
                                                    <tr>
                                                        <td style='color: #666;'>Matrícula:</td>
                                                        <td style='color: #333; font-weight: 600;'>{$matricula}</td>
                                                    </tr>
                                                    <tr>
                                                        <td style='color: #666;'>Precio:</td>
                                                        <td style='color: #016d86; font-weight: 700; font-size: 16px;'>{$finalAmount}€</td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                    </table>
                                    <div style='background: #e3f2fd; border-left: 4px solid #016d86; padding: 15px; margin: 25px 0;'>
                                        <p style='margin: 0; color: #0d47a1; font-size: 14px;'>
                                            <strong>¿Qué recibirá?</strong><br>
                                            • Datos técnicos oficiales de la embarcación<br>
                                            • Información del registro marítimo<br>
                                            • Estado actual del registro<br>
                                            • Documentación oficial disponible
                                        </p>
                                    </div>
                                    <p style='margin: 25px 0 0 0; color: #666; font-size: 14px; text-align: center;'>
                                        Si tiene alguna duda, puede contactarnos en <strong>info@tramitfy.es</strong>
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
            tramitfy_consulta_log('Email cliente enviado: ' . ($mail_sent_customer ? 'SÍ' : 'NO'), 'EMAILS', 'INFO');
            
            // ============================================
            // EMAIL AL ADMIN
            // ============================================
            
            $adminEmail = 'ipmgroup24@gmail.com';
            $adminSubject = '🔔 Nueva Consulta Registro - ' . $tramiteId . ' - ' . $boatName;
            $adminMessage = "
            <!DOCTYPE html>
            <html>
            <head><meta charset='UTF-8'></head>
            <body style='margin: 0; padding: 20px; font-family: Arial, sans-serif; background-color: #f5f5f5;'>
            <div style='max-width: 700px; margin: 0 auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);'>
                <div style='background: #016d86; color: white; padding: 20px; text-align: center;'>
                    <h2 style='margin: 0; font-size: 24px;'>🔍 Nueva Consulta del Registro</h2>
                    <p style='margin: 10px 0 0 0; opacity: 0.9;'>ID: {$tramiteId}</p>
                </div>
                <div style='padding: 30px;'>
                    <h3 style='color: #016d86; margin: 0 0 20px 0;'>Datos del Cliente</h3>
                    <table cellpadding='8' cellspacing='0' style='width: 100%; margin-bottom: 30px;'>
                        <tr>
                            <td style='color: #666; width: 120px;'>Nombre:</td>
                            <td style='color: #333; font-weight: 600;'>{$customerName}</td>
                        </tr>
                        <tr>
                            <td style='color: #666;'>Email:</td>
                            <td style='color: #0066cc; font-weight: 600;'>{$customerEmail}</td>
                        </tr>
                    </table>
                    
                    <h3 style='color: #016d86; margin: 0 0 20px 0;'>Datos de la Embarcación</h3>
                    <table cellpadding='8' cellspacing='0' style='width: 100%; margin-bottom: 30px;'>
                        <tr>
                            <td style='color: #666; width: 120px;'>Nombre:</td>
                            <td style='color: #333; font-weight: 600;'>{$boatName}</td>
                        </tr>
                        <tr>
                            <td style='color: #666;'>Matrícula:</td>
                            <td style='color: #333; font-weight: 600;'>{$matricula}</td>
                        </tr>
                        <tr>
                            <td style='color: #666;'>Precio:</td>
                            <td style='color: #28a745; font-weight: 700; font-size: 16px;'>{$finalAmount}€</td>
                        </tr>
                        <tr>
                            <td style='color: #666;'>Payment ID:</td>
                            <td style='color: #666; font-family: monospace; font-size: 12px;'>{$paymentIntentId}</td>
                        </tr>
                    </table>
                    
                    <div style='background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                        <p style='margin: 0; color: #856404; font-weight: 600;'>
                            ⏰ Acción Requerida: Procesar consulta del registro de embarcación
                        </p>
                    </div>
                </div>
                <div style='background: #f8f9fa; padding: 20px; text-align: center; border-top: 1px solid #e0e0e0;'>
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
            tramitfy_consulta_log('Email admin enviado: ' . ($mail_sent_admin ? 'SÍ' : 'NO'), 'EMAILS', 'INFO');
            
            if ($mail_sent_customer && $mail_sent_admin) {
                wp_send_json_success([
                    'message' => 'Emails enviados correctamente',
                    'tramiteId' => $tramiteId
                ]);
            } else {
                wp_send_json_error([
                    'message' => 'Error al enviar algunos emails',
                    'customer' => $mail_sent_customer,
                    'admin' => $mail_sent_admin
                ]);
            }
            
        } catch (Exception $e) {
            tramitfy_consulta_log('Error enviando emails: ' . $e->getMessage(), 'EMAILS', 'ERROR');
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
}

add_action('wp_ajax_send_consulta_emails', 'send_consulta_emails');
add_action('wp_ajax_nopriv_send_consulta_emails', 'send_consulta_emails');

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
        $demo_data = get_demo_data_for_placeholders();
        
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
            padding: 30px 25px;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            text-align: center;
            gap: 25px;
        }

        /* Header del Sidebar */
        .sidebar-header {
            text-align: center;
        }

        .sidebar-header h3 {
            margin: 0 0 15px 0;
            color: #ffffff;
            font-size: 18px;
            font-weight: 700;
            line-height: 1.3;
        }

        .sidebar-description {
            color: rgba(255, 255, 255, 0.95);
            font-size: 14px;
            font-weight: 400;
            line-height: 1.5;
            margin: 0;
        }

        .sidebar-description strong {
            color: white;
            font-weight: 600;
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

        /* Sección de Reseñas - Cerca del precio */
        .sidebar-reviews {
            margin-top: 20px;
            margin-bottom: auto;
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
            padding: 16px 28px;
            border-radius: var(--radius-lg);
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: var(--transition-normal);
            margin-top: 25px;
            box-shadow: var(--shadow-md);
            position: relative;
            overflow: hidden;
        }

        .primary-button:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .primary-button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
            filter: grayscale(0.3);
        }

        .primary-button.terms-pending {
            opacity: 0.7;
            filter: grayscale(0.2);
        }

        .primary-button.terms-accepted {
            opacity: 1;
            filter: none;
            transition: all 0.3s ease;
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

        /* Términos y condiciones - Diseño minimalista y discreto */
        .consulta-terms {
            margin: 15px 0 10px 0;
            padding: 12px 0;
            border-top: 1px solid rgba(var(--neutral-300), 0.4);
            border-bottom: 1px solid rgba(var(--neutral-300), 0.4);
            background: transparent;
        }

        .consulta-terms label {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            font-size: 13px;
            line-height: 1.4;
            color: rgb(var(--neutral-600));
            font-weight: 400;
            transition: color var(--transition-fast);
        }

        .consulta-terms label:hover {
            color: rgb(var(--neutral-700));
        }

        .consulta-terms input[type="checkbox"] {
            width: 16px;
            height: 16px;
            cursor: pointer;
            accent-color: rgb(var(--primary));
            border-radius: 3px;
            flex-shrink: 0;
            transition: var(--transition-fast);
        }

        .consulta-terms input[type="checkbox"]:checked {
            transform: scale(1.02);
        }

        .consulta-terms a {
            color: rgb(var(--primary));
            text-decoration: underline;
            text-decoration-color: rgba(var(--primary), 0.3);
            font-weight: 500;
            transition: var(--transition-fast);
        }

        .consulta-terms a:hover {
            text-decoration-color: rgb(var(--primary));
            color: rgb(var(--primary-light));
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
                padding: 25px 20px;
                gap: 20px;
            }

            .sidebar-header h3 {
                font-size: 16px;
                margin-bottom: 12px;
            }

            .sidebar-description {
                font-size: 13px;
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


            /* Términos responsive */
            .consulta-terms {
                margin: 12px 0 8px 0;
                padding: 10px 0;
            }

            .consulta-terms label {
                font-size: 12px;
                gap: 8px;
            }

            .consulta-terms input[type="checkbox"] {
                width: 15px;
                height: 15px;
            }

            .primary-button {
                margin-top: 20px;
                padding: 15px 24px;
                font-size: 15px;
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
                    <!-- Descripción del Servicio -->
                    <div class="sidebar-header">
                        <h3>Consulta del Registro Marítimo</h3>
                        <p class="sidebar-description">
                            Obtén información oficial de <strong>cualquier embarcación</strong> solo con su nombre y matrícula.
                        </p>
                    </div>

                    <!-- Precio -->
                    <div class="sidebar-price-highlight">
                        <div class="sidebar-price-amount"><?php echo CONSULTA_SERVICE_PRICE; ?>€</div>
                        <div class="price-value-text">Información oficial completa</div>
                    </div>

                    <!-- Reseñas - Justo debajo del precio -->
                    <div class="sidebar-reviews">
                        <script defer async src='https://cdn.trustindex.io/loader.js?f4fbfd341d12439e0c86fae7fc2'></script>
                    </div>
                </aside>

                <!-- Panel Principal del Formulario -->
                <main class="tramitfy-main-form">
                    <div class="form-header">
                        <h1 class="form-title">Consulta del Registro</h1>
                    </div>

                    <form id="consultaRegistroForm">
                        <div class="form-group">
                            <label class="form-label" for="customer_email">Tu email *</label>
                            <input type="email" class="form-input demo-placeholder" id="customer_email" name="customer_email"
                                   value="" 
                                   placeholder="<?php echo esc_attr($demo_data['customer_email']); ?>" required>
                        </div>

                        <div class="form-compact-row">
                            <div class="form-group">
                                <label class="form-label" for="boat_name">Nombre embarcación *</label>
                                <input type="text" class="form-input demo-placeholder" id="boat_name" name="boat_name"
                                       value="" 
                                       placeholder="<?php echo esc_attr($demo_data['boat_name']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="matricula">Matrícula *</label>
                                <input type="text" class="form-input demo-placeholder" id="matricula" name="matricula"
                                       value="" 
                                       placeholder="<?php echo esc_attr($demo_data['matricula']); ?>" required>
                            </div>
                        </div>

                        <!-- Términos y condiciones -->
                        <div class="consulta-terms">
                            <label>
                                <input type="checkbox" name="terms_accept" id="terms_accept" required />
                                He leído y acepto la <a href="#" target="_blank">Política de Privacidad</a> y los <a href="#" target="_blank">Términos y Condiciones</a>.
                            </label>
                        </div>

                        <button type="button" id="show-payment-modal" class="primary-button enhanced-cta">
                            <span class="button-icon">🚀</span>
                            <span class="button-text">Obtener Información Ahora</span>
                            <span class="button-price"><?php echo CONSULTA_SERVICE_PRICE; ?>€</span>
                        </button>
                        
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
            const termsCheckbox = document.getElementById('terms_accept');

            // Función para mostrar mensajes
            function showMessage(text, type = 'info') {
                paymentMessageDiv.textContent = text;
                paymentMessageDiv.className = type;
            }

            // Función para actualizar estado visual del botón según términos
            function updateButtonState() {
                if (termsCheckbox.checked) {
                    showPaymentModalBtn.classList.remove('terms-pending');
                    showPaymentModalBtn.classList.add('terms-accepted');
                } else {
                    showPaymentModalBtn.classList.add('terms-pending');
                    showPaymentModalBtn.classList.remove('terms-accepted');
                }
            }

            // Event listener para el checkbox de términos
            termsCheckbox.addEventListener('change', updateButtonState);

            // Inicializar estado del botón
            updateButtonState();


            // Validar formulario
            function validateForm() {
                const email = document.getElementById('customer_email').value.trim();
                const boatName = document.getElementById('boat_name').value.trim();
                const matricula = document.getElementById('matricula').value.trim();
                const termsAccept = document.getElementById('terms_accept').checked;

                // Limpiar valores demo antes de validar
                const demoInputs = document.querySelectorAll('.demo-placeholder');
                demoInputs.forEach(function(input) {
                    if (input.getAttribute('data-is-demo') === 'true') {
                        input.value = '';
                    }
                });

                // Validar campos obligatorios
                if (!email || !boatName || !matricula) {
                    alert('Por favor, complete todos los campos obligatorios.');
                    return false;
                }

                // Validar formato email
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(email)) {
                    alert('Por favor, introduzca un email válido.');
                    return false;
                }

                // Validar términos y condiciones
                if (!termsAccept) {
                    alert('Debe aceptar la Política de Privacidad y los Términos y Condiciones para continuar.');
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
                    // PASO 1: Confirmar pago y guardar datos
                    confirmPaymentBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando pago...';
                    showMessage('Procesando pago y guardando datos...', 'processing');
                    
                    const formData = new FormData();
                    formData.append('action', 'consulta_confirm_payment');
                    formData.append('payment_intent_id', paymentIntentId);
                    formData.append('customer_email', document.getElementById('customer_email').value);
                    formData.append('boat_name', document.getElementById('boat_name').value);
                    formData.append('matricula', document.getElementById('matricula').value);
                    formData.append('terms_accept', 'true');

                    const response = await fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();

                    if (result.success) {
                        console.log('✅ Datos guardados correctamente');
                        
                        // PASO 2: Enviar emails de confirmación
                        confirmPaymentBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando confirmaciones...';
                        showMessage('Enviando emails de confirmación...', 'processing');
                        
                        await new Promise(resolve => setTimeout(resolve, 1000)); // Pequeña pausa
                        
                        const emailFormData = new FormData();
                        emailFormData.append('action', 'send_consulta_emails');
                        emailFormData.append('customerName', document.getElementById('customer_email').value.split('@')[0]);
                        emailFormData.append('customerEmail', document.getElementById('customer_email').value);
                        emailFormData.append('boatName', document.getElementById('boat_name').value);
                        emailFormData.append('matricula', document.getElementById('matricula').value);
                        emailFormData.append('finalAmount', '<?php echo CONSULTA_SERVICE_PRICE; ?>');
                        emailFormData.append('paymentIntentId', paymentIntentId);
                        emailFormData.append('tramiteId', 'CR-' + Date.now());

                        const emailResponse = await fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                            method: 'POST',
                            body: emailFormData,
                            signal: AbortSignal.timeout(15000) // 15 segundos timeout para emails
                        });

                        const emailResult = await emailResponse.json();
                        
                        if (!emailResult.success) {
                            console.warn('⚠️ Error al enviar emails:', emailResult.message);
                            // No bloquear el flujo si fallan los emails
                        } else {
                            console.log('✅ Emails enviados correctamente');
                        }
                        
                        // Mostrar éxito final
                        showMessage('Consulta registrada correctamente. Recibirá confirmación por email.', 'success');
                        
                        setTimeout(function() {
                            paymentModal.classList.remove('show');
                            document.getElementById('consultaRegistroForm').innerHTML = 
                                '<div style="text-align: center; padding: 30px; background: #d4edda; border-radius: 8px; color: #155724;">' +
                                '<h3>✅ Consulta Registrada</h3>' +
                                '<p><strong>Recibirá los resultados por email.</strong></p>' +
                                '<p style="margin-top: 15px; font-size: 14px;">También hemos enviado una confirmación a su email.</p>' +
                                '</div>';
                        }, 2000);
                        
                    } else {
                        throw new Error(result.data || 'Error al confirmar el pago');
                    }

                } catch (error) {
                    console.error('Error:', error);
                    showMessage('Error: ' + error.message, 'error');
                    confirmPaymentBtn.disabled = false;
                    confirmPaymentBtn.innerHTML = 'Confirmar Pago';
                }
            }

            // Sistema de placeholders demo visuales
            document.addEventListener('DOMContentLoaded', function() {
                const demoInputs = document.querySelectorAll('.demo-placeholder');
                
                demoInputs.forEach(function(input) {
                    // TODOS los campos empiezan con placeholder gris (sin excepciones admin)
                    if (!input.value.trim()) {
                        const demoValue = input.getAttribute('placeholder');
                        input.value = demoValue;
                        input.style.color = '#9CA3AF'; // Color gris
                        input.setAttribute('data-is-demo', 'true');
                    }

                    // Al hacer focus, borrar si es demo
                    input.addEventListener('focus', function() {
                        if (this.getAttribute('data-is-demo') === 'true') {
                            this.value = '';
                            this.style.color = '#374151'; // Color normal
                            this.setAttribute('data-is-demo', 'false');
                        }
                    });

                    // Al perder focus, si está vacío, restaurar demo
                    input.addEventListener('blur', function() {
                        if (!this.value.trim()) {
                            const demoValue = this.getAttribute('placeholder');
                            this.value = demoValue;
                            this.style.color = '#9CA3AF'; // Color gris
                            this.setAttribute('data-is-demo', 'true');
                        }
                    });

                    // Al enviar formulario, limpiar demos
                    const form = input.closest('form');
                    if (form) {
                        form.addEventListener('submit', function() {
                            demoInputs.forEach(function(demoInput) {
                                if (demoInput.getAttribute('data-is-demo') === 'true') {
                                    demoInput.value = '';
                                }
                            });
                        });
                    }
                });
            });
        });
        </script>

        <?php
        return ob_get_clean();
    }
}

// Registrar shortcode
add_shortcode('consulta_registro_form', 'consulta_registro_form_shortcode');