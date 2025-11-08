<?php
/*
Plugin Name: Consulta del Registro - Modal System
Description: Formulario simplificado para consulta del registro de embarcaciones con modal de pago (igual que hoja-asiento)
Version: 2.0
Author: Tramitfy
*/

// Asegurarse de que el archivo no sea accedido directamente
defined('ABSPATH') || exit;

// Cargar Stripe library ANTES de las funciones (IGUAL QUE HOJA DE ASIENTO)
require_once(get_template_directory() . '/vendor/autoload.php');

// Configuración de Stripe AL NIVEL GLOBAL (IGUAL QUE HOJA DE ASIENTO)
define('CONSULTA_STRIPE_MODE', 'live'); // 'test' o 'live'

define('CONSULTA_STRIPE_TEST_PUBLIC_KEY', 'YOUR_STRIPE_TEST_PUBLIC_KEY_HERE');
define('CONSULTA_STRIPE_TEST_SECRET_KEY', 'YOUR_STRIPE_TEST_SECRET_KEY_HERE');

define('CONSULTA_STRIPE_LIVE_PUBLIC_KEY', 'YOUR_STRIPE_LIVE_PUBLIC_KEY_HERE');
define('CONSULTA_STRIPE_LIVE_SECRET_KEY', 'YOUR_STRIPE_LIVE_SECRET_KEY_HERE');

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
    $admin_data = [];
    if (current_user_can('administrator')) {
        $admin_data = [
            'customer_email' => 'admin@tramitfy.es',
            'boat_name' => 'Barco de Prueba Consulta',
            'matricula' => 'CONS-123'
        ];
    }
    return $admin_data;
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

if (!function_exists('consulta_registro_modal_shortcode')) {
    function consulta_registro_modal_shortcode($atts) {
        global $consulta_stripe_public_key;
        
        $admin_data = consulta_admin_autofill_data();
        
        ob_start();
        ?>
        <style>
        /* Estilos del formulario principal */
        .consulta-form-container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }

        .consulta-form-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }

        .consulta-form-header h2 {
            color: #016d86;
            margin: 0 0 10px 0;
            font-size: 1.8rem;
        }

        .consulta-form-header p {
            color: #666;
            margin: 0;
            font-size: 1.1rem;
        }

        .consulta-price-display {
            background: linear-gradient(135deg, #016d86 0%, #024d61 100%);
            color: white;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 25px;
            font-size: 1.2rem;
            font-weight: bold;
        }

        .consulta-form-group {
            margin-bottom: 20px;
        }

        .consulta-form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
        }

        .consulta-form-group input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }

        .consulta-form-group input:focus {
            outline: none;
            border-color: #016d86;
            box-shadow: 0 0 0 3px rgba(1, 109, 134, 0.1);
        }

        .consulta-btn {
            background: linear-gradient(135deg, #016d86 0%, #024d61 100%);
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
        }

        .consulta-btn:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(1, 109, 134, 0.3);
        }

        .consulta-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        /* Modal de pago - COPIADO DE HOJA-ASIENTO */
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
        </style>

        <div class="consulta-form-container">
            <div class="consulta-form-header">
                <h2>🔍 Consulta del Registro</h2>
                <p>Consulta los datos oficiales de tu embarcación</p>
            </div>

            <div class="consulta-price-display">
                💰 Precio del servicio: <?php echo CONSULTA_SERVICE_PRICE; ?>€
            </div>

            <form id="consulta-registro-form">
                <div class="consulta-form-group">
                    <label for="customer_email">📧 Email de contacto *</label>
                    <input type="email" id="customer_email" name="customer_email" 
                           value="<?php echo esc_attr($admin_data['customer_email'] ?? ''); ?>" required>
                </div>

                <div class="consulta-form-group">
                    <label for="boat_name">🚢 Nombre de la embarcación *</label>
                    <input type="text" id="boat_name" name="boat_name" 
                           value="<?php echo esc_attr($admin_data['boat_name'] ?? ''); ?>" required>
                </div>

                <div class="consulta-form-group">
                    <label for="matricula">🏷️ Matrícula/Folio *</label>
                    <input type="text" id="matricula" name="matricula" 
                           value="<?php echo esc_attr($admin_data['matricula'] ?? ''); ?>" 
                           placeholder="Ej: 1234-ABC, FR-123456" required>
                </div>

                <button type="button" id="show-payment-modal" class="consulta-btn">
                    💳 Proceder al Pago (<?php echo CONSULTA_SERVICE_PRICE; ?>€)
                </button>
            </form>
        </div>

        <!-- Modal de pago -->
        <div id="consulta-payment-modal" class="consulta-payment-modal">
            <div class="consulta-payment-modal-content">
                <span class="consulta-close-payment-modal">&times;</span>
                
                <h3 style="text-align: center; color: #016d86; margin-bottom: 20px;">
                    💳 Pago Seguro - <?php echo CONSULTA_SERVICE_PRICE; ?>€
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
                    <i class="fa-solid fa-check-circle"></i> Confirmar Pago
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
                        showMessage('✅ Pago completado. Su consulta ha sido registrada correctamente.', 'success');
                        setTimeout(function() {
                            paymentModal.classList.remove('show');
                            document.getElementById('consulta-registro-form').innerHTML = 
                                '<div style="text-align: center; padding: 30px; background: #d4edda; border-radius: 8px; color: #155724;">' +
                                '<h3>✅ Consulta Registrada</h3>' +
                                '<p>Su consulta ha sido procesada correctamente. Recibirá los resultados por email.</p>' +
                                '<p><strong>ID del trámite:</strong> ' + result.data.tramite_id + '</p>' +
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
add_shortcode('consulta_registro_modal', 'consulta_registro_modal_shortcode');

?>