<?php
/*
Plugin Name: Consulta del Registro
Description: Formulario simplificado para consulta del registro de embarcaciones con Stripe
Version: 1.0
Author: Tramitfy
*/

// Asegurarse de que el archivo no sea accedido directamente
defined('ABSPATH') || exit;

// ============================================
// SISTEMA DE LOGS TRAMITFY
// ============================================

// Función de logging
if (!function_exists('tramitfy_consulta_log')) {
    function tramitfy_consulta_log($message, $context = 'CONSULTA-FORM', $level = 'INFO') {
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

tramitfy_consulta_log('========== INICIO CARGA FORMULARIO CONSULTA REGISTRO ==========', 'INIT', 'INFO');

// Configuración Stripe para Consulta Registro
define('CONSULTA_STRIPE_MODE', 'test'); // 'test' o 'live'
define('CONSULTA_STRIPE_TEST_PUBLIC_KEY', 'YOUR_STRIPE_TEST_PUBLIC_KEY_HERE');
define('CONSULTA_STRIPE_TEST_SECRET_KEY', 'YOUR_STRIPE_TEST_SECRET_KEY_HERE');
define('CONSULTA_STRIPE_LIVE_PUBLIC_KEY', 'YOUR_STRIPE_LIVE_PUBLIC_KEY_HERE');
define('CONSULTA_STRIPE_LIVE_SECRET_KEY', 'YOUR_STRIPE_LIVE_SECRET_KEY_HERE');

// Asignar claves a variables globales
if (CONSULTA_STRIPE_MODE === 'test') {
    $consulta_stripe_public_key = CONSULTA_STRIPE_TEST_PUBLIC_KEY;
    $consulta_stripe_secret_key = CONSULTA_STRIPE_TEST_SECRET_KEY;
} else {
    $consulta_stripe_public_key = CONSULTA_STRIPE_LIVE_PUBLIC_KEY;
    $consulta_stripe_secret_key = CONSULTA_STRIPE_LIVE_SECRET_KEY;
}

// Función para autorellenar datos de administrador
function consulta_admin_autofill_data() {
    $admin_data = [];
    if (current_user_can('administrator')) {
        $admin_data = [
            'customer_email' => '',
            'boat_name' => '',
            'matricula' => ''
        ];
    }
    return $admin_data;
}

// Función para procesar el formulario
if (!function_exists('consulta_registro_procesar_formulario')) {
    function consulta_registro_procesar_formulario() {
        global $consulta_stripe_secret_key;
        
        try {
            // Log del inicio del procesamiento
            tramitfy_consulta_log('Iniciando procesamiento formulario', 'PROCESS', 'INFO');
            
            // Verificar que es una petición POST
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Método no permitido');
            }

            // Datos básicos
            $customer_email = sanitize_email($_POST['customer_email'] ?? '');
            $boat_name = sanitize_text_field($_POST['boat_name'] ?? '');
            $matricula = sanitize_text_field($_POST['matricula'] ?? '');
            
            // Datos opcionales para compatibilidad
            $customer_name = !empty($customer_email) ? explode('@', $customer_email)[0] : 'Cliente';
            $customer_dni = '';
            $customer_phone = '';

            // Validaciones básicas
            if (empty($customer_email) || empty($boat_name) || empty($matricula)) {
                throw new Exception('Email, nombre de embarcación y matrícula son obligatorios');
            }

            // Crear Payment Intent con Stripe
            $amount = 2999; // 29.99 EUR en centavos
            
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => 'https://api.stripe.com/v1/payment_intents',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $consulta_stripe_secret_key,
                    'Content-Type: application/x-www-form-urlencoded',
                ],
                CURLOPT_POSTFIELDS => http_build_query([
                    'amount' => $amount,
                    'currency' => 'eur',
                    'metadata' => [
                        'tramite_type' => 'consulta-registro',
                        'customer_name' => $customer_name,
                        'customer_email' => $customer_email,
                        'boat_name' => $boat_name,
                        'matricula' => $matricula
                    ]
                ])
            ]);

            $stripe_response = curl_exec($curl);
            $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);

            if ($http_code !== 200) {
                throw new Exception('Error al crear el pago: ' . $stripe_response);
            }

            $payment_intent = json_decode($stripe_response, true);
            
            if (!isset($payment_intent['client_secret'])) {
                throw new Exception('Error en la respuesta de Stripe');
            }

            // Enviar datos al webhook
            $webhook_data = [
                'tramite_type' => 'consulta-registro',
                'customer_name' => $customer_name,
                'customer_dni' => $customer_dni,
                'customer_email' => $customer_email,
                'customer_phone' => $customer_phone,
                'boat_name' => $boat_name,
                'matricula' => $matricula,
                'final_amount' => 29.99,
                'payment_intent_id' => $payment_intent['id']
            ];

            $webhook_curl = curl_init();
            curl_setopt_array($webhook_curl, [
                CURLOPT_URL => 'https://46-202-128-35.sslip.io/api/herramientas/consulta-registro/webhook',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS => json_encode($webhook_data)
            ]);

            $webhook_response = curl_exec($webhook_curl);
            $webhook_http_code = curl_getinfo($webhook_curl, CURLINFO_HTTP_CODE);
            curl_close($webhook_curl);

            tramitfy_consulta_log('Webhook response: ' . $webhook_response, 'WEBHOOK', 'INFO');

            // Retornar client_secret para el frontend
            wp_send_json_success([
                'client_secret' => $payment_intent['client_secret'],
                'amount' => $amount
            ]);

        } catch (Exception $e) {
            tramitfy_consulta_log('Error procesamiento: ' . $e->getMessage(), 'ERROR', 'ERROR');
            wp_send_json_error(['message' => $e->getMessage()]);
        }

        wp_die();
    }
}

// Registrar el handler AJAX
add_action('wp_ajax_consulta_registro_procesar', 'consulta_registro_procesar_formulario');
add_action('wp_ajax_nopriv_consulta_registro_procesar', 'consulta_registro_procesar_formulario');

// Función principal del shortcode
if (!function_exists('consulta_registro_form_shortcode')) {
    function consulta_registro_form_shortcode($atts) {
        global $consulta_stripe_public_key;
        
        ob_start();
        
        $admin_data = consulta_admin_autofill_data();
        ?>

        <style>
        .tramitfy-consulta-container {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 0;
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            margin: 20px auto;
            max-width: 900px;
            min-height: 500px;
            overflow: hidden;
        }

        .tramitfy-consulta-sidebar {
            background: #016d86;
            padding: 30px 25px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
        }

        .tramitfy-consulta-main {
            padding: 40px 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .consulta-form-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e5e7eb;
        }

        .consulta-form-title {
            font-size: 28px;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 8px;
        }

        .consulta-form-subtitle {
            font-size: 16px;
            color: #6b7280;
        }

        .consulta-form-group {
            margin-bottom: 20px;
        }

        .consulta-form-label {
            display: block;
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .consulta-form-input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #d1d5db;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s ease;
            box-sizing: border-box;
        }

        .consulta-form-input:focus {
            outline: none;
            border-color: #016d86;
            box-shadow: 0 0 0 3px rgba(1, 109, 134, 0.1);
        }

        .consulta-form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 18px;
        }

        .consulta-btn {
            background: linear-gradient(135deg, #016d86, #0891b2);
            color: white;
            border: none;
            padding: 14px 24px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: all 0.3s ease;
            margin-top: 20px;
        }

        .consulta-btn:hover:not(:disabled) {
            transform: translateY(-1px);
            box-shadow: 0 10px 25px rgba(1, 109, 134, 0.3);
        }

        .consulta-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .consulta-stripe-container {
            display: none;
            margin-top: 20px;
            padding: 20px;
            background: #f9fafb;
            border-radius: 8px;
            border: 2px dashed #d1d5db;
        }

        .consulta-loading-spinner {
            display: inline-block;
            width: 18px;
            height: 18px;
            border: 2px solid #ffffff;
            border-radius: 50%;
            border-top-color: transparent;
            animation: spin 1s ease-in-out infinite;
            margin-right: 8px;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .consulta-success-message {
            background: #d1fae5;
            border: 1px solid #a7f3d0;
            color: #065f46;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
            display: none;
        }

        .consulta-error-message {
            background: #fee2e2;
            border: 1px solid #fecaca;
            color: #dc2626;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
            display: none;
        }

        @media (max-width: 768px) {
            .tramitfy-consulta-container {
                grid-template-columns: 1fr;
                margin: 10px;
            }
            
            .tramitfy-consulta-sidebar {
                padding: 20px;
            }
            
            .tramitfy-consulta-main {
                padding: 30px 20px;
            }
            
            .consulta-form-row {
                grid-template-columns: 1fr;
            }
            
            .consulta-form-title {
                font-size: 24px;
            }
        }
        </style>

        <div class="tramitfy-consulta-container">
            <!-- Sidebar -->
            <div class="tramitfy-consulta-sidebar">
                <div style="background: rgba(255,255,255,0.15); padding: 25px; border-radius: 12px; margin-bottom: 30px;">
                    <div style="color: rgba(255,255,255,0.9); font-size: 14px; margin-bottom: 8px;">Precio del servicio</div>
                    <div style="color: white; font-size: 36px; font-weight: 800; margin-bottom: 8px;">29,99€</div>
                    <div style="color: rgba(255,255,255,0.8); font-size: 12px; margin-bottom: 15px;">Todo incluido</div>
                    <div style="background: rgba(255,255,255,0.2); padding: 8px 16px; border-radius: 20px; font-size: 11px; color: white; display: inline-block;">✓ Respuesta 24-48h</div>
                </div>

                <!-- Widget de Trustpilot -->
                <script defer async src='https://cdn.trustindex.io/loader.js?f4fbfd341d12439e0c86fae7fc2'></script>
            </div>

            <!-- Formulario Principal -->
            <div class="tramitfy-consulta-main">
                <div class="consulta-form-header">
                    <h1 class="consulta-form-title">Consulta del Registro Marítimo</h1>
                    <p class="consulta-form-subtitle">Obtén información oficial de tu embarcación</p>
                </div>

                <form id="consultaRegistroForm">
                    <div class="consulta-form-group">
                        <label class="consulta-form-label" for="customer_email">Email *</label>
                        <input type="email" class="consulta-form-input" id="customer_email" name="customer_email"
                               value="<?php echo esc_attr($admin_data['customer_email'] ?? ''); ?>" 
                               placeholder="tu@email.com" required>
                    </div>

                    <div class="consulta-form-row">
                        <div class="consulta-form-group">
                            <label class="consulta-form-label" for="boat_name">Nombre de la embarcación *</label>
                            <input type="text" class="consulta-form-input" id="boat_name" name="boat_name"
                                   value="<?php echo esc_attr($admin_data['boat_name'] ?? ''); ?>" 
                                   placeholder="Ej: MAR AZUL" required>
                        </div>
                        <div class="consulta-form-group">
                            <label class="consulta-form-label" for="matricula">Matrícula *</label>
                            <input type="text" class="consulta-form-input" id="matricula" name="matricula"
                                   value="<?php echo esc_attr($admin_data['matricula'] ?? ''); ?>" 
                                   placeholder="Ej: 2-ABC-1-23" required>
                        </div>
                    </div>

                    <button type="submit" class="consulta-btn" id="submitBtn">
                        Solicitar Consulta - 29,99€
                    </button>

                    <div class="consulta-stripe-container" id="stripeContainer">
                        <h4 style="margin-bottom: 15px; color: #374151;">Pago seguro</h4>
                        <div id="card-element"></div>
                        <button type="button" class="consulta-btn" id="payBtn" style="margin-top: 15px;">
                            <span class="consulta-loading-spinner" id="paySpinner" style="display: none;"></span>
                            Pagar 29,99€
                        </button>
                    </div>

                    <div class="consulta-success-message" id="successMessage"></div>
                    <div class="consulta-error-message" id="errorMessage"></div>
                </form>
            </div>
        </div>

        <script src="https://js.stripe.com/v3/"></script>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const stripe = Stripe('<?php echo esc_js($consulta_stripe_public_key); ?>');
            const elements = stripe.elements();
            let cardElement = null;
            let clientSecret = null;

            const form = document.getElementById('consultaRegistroForm');
            const submitBtn = document.getElementById('submitBtn');
            const stripeContainer = document.getElementById('stripeContainer');
            const payBtn = document.getElementById('payBtn');
            const paySpinner = document.getElementById('paySpinner');
            const successMessage = document.getElementById('successMessage');
            const errorMessage = document.getElementById('errorMessage');

            function showError(message) {
                errorMessage.textContent = message;
                errorMessage.style.display = 'block';
                successMessage.style.display = 'none';
            }

            function showSuccess(message) {
                successMessage.textContent = message;
                successMessage.style.display = 'block';
                errorMessage.style.display = 'none';
            }

            function hideMessages() {
                errorMessage.style.display = 'none';
                successMessage.style.display = 'none';
            }

            // Submit del formulario inicial
            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                hideMessages();

                const formData = new FormData(form);
                formData.append('action', 'consulta_registro_procesar');

                try {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="consulta-loading-spinner"></span>Validando datos...';

                    const response = await fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();

                    if (!result.success) {
                        throw new Error(result.data?.message || 'Error al procesar la solicitud');
                    }

                    clientSecret = result.data.client_secret;

                    showSuccess('Datos validados correctamente. Procede al pago para completar tu consulta.');
                    
                    stripeContainer.style.display = 'block';
                    submitBtn.style.display = 'none';

                    if (!cardElement) {
                        cardElement = elements.create('card', {
                            style: {
                                base: {
                                    fontSize: '16px',
                                    color: '#374151',
                                    fontFamily: '"Inter", -apple-system, BlinkMacSystemFont, sans-serif'
                                }
                            }
                        });
                        cardElement.mount('#card-element');
                    }

                } catch (error) {
                    showError(error.message);
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = 'Solicitar Consulta - 29,99€';
                }
            });

            // Procesar pago
            if (payBtn) {
                payBtn.addEventListener('click', async function() {
                    if (!clientSecret) {
                        showError('Error: No se pudo inicializar el pago');
                        return;
                    }

                    try {
                        payBtn.disabled = true;
                        paySpinner.style.display = 'inline-block';
                        document.getElementById('payBtnText').textContent = 'Procesando pago...';
                        hideMessages();

                        const { error, paymentIntent } = await stripe.confirmCardPayment(clientSecret, {
                            payment_method: {
                                card: cardElement,
                                billing_details: {
                                    email: document.getElementById('customer_email').value,
                                }
                            }
                        });

                        if (error) {
                            throw new Error(error.message);
                        }

                        if (paymentIntent.status === 'succeeded') {
                            showSuccess('¡Pago completado! Recibirás la consulta en 24-48h por email.');
                            stripeContainer.style.display = 'none';
                        }

                    } catch (error) {
                        showError('Error en el pago: ' + error.message);
                    } finally {
                        payBtn.disabled = false;
                        paySpinner.style.display = 'none';
                        // Texto del botón se restaura automáticamente
                    }
                });
            }
        });
        </script>

        <?php
        return ob_get_clean();
    }
}

// Registrar shortcode
add_shortcode('consulta_registro_form', 'consulta_registro_form_shortcode');

tramitfy_consulta_log('========== FIN CARGA FORMULARIO CONSULTA REGISTRO ==========', 'INIT', 'INFO');