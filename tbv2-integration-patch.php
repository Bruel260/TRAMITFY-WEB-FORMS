<?php
/**
 * TBV2 INTEGRATION PATCH
 * Parche que integra el sistema temporal con el formulario existente
 * 
 * Este archivo modifica las funciones existentes para usar el nuevo sistema temporal
 */

if (!defined('ABSPATH')) {
    exit('Acceso directo no permitido');
}

// Incluir sistemas necesarios
require_once(__DIR__ . '/tbv2-temporal-storage.php');
require_once(__DIR__ . '/tbv2-redsys-callback.php');

/**
 * AJAX ENDPOINT: Crear pago Redsys con sistema temporal
 */
add_action('wp_ajax_tbv2_create_redsys_payment_temporal', 'tbv2_handle_create_redsys_payment_temporal');
add_action('wp_ajax_nopriv_tbv2_create_redsys_payment_temporal', 'tbv2_handle_create_redsys_payment_temporal');

function tbv2_handle_create_redsys_payment_temporal() {
    // Verificar nonce
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'tbv2_nonce')) {
        wp_send_json_error('Nonce inválido');
    }
    
    try {
        $temp_id = sanitize_text_field($_POST['temp_id'] ?? '');
        $order_id = sanitize_text_field($_POST['order_id'] ?? '');
        $amount = floatval($_POST['amount'] ?? 0);
        $description = sanitize_text_field($_POST['description'] ?? '');
        $customer_name = sanitize_text_field($_POST['customer_name'] ?? '');
        
        if (empty($temp_id) || empty($order_id) || $amount <= 0) {
            throw new Exception('Parámetros inválidos para el pago');
        }
        
        // Verificar que los datos temporales existen
        $temp_data = TBV2_Temporal_Storage::get_temp_data($temp_id);
        if (!$temp_data) {
            throw new Exception('Datos temporales no encontrados o expirados');
        }
        
        // Convertir amount a céntimos para Redsys
        $amount_cents = intval($amount * 100);
        
        // Preparar parámetros Redsys
        $params = [
            'Ds_Merchant_Amount' => $amount_cents,
            'Ds_Merchant_Order' => $order_id,
            'Ds_Merchant_MerchantCode' => TBV2_REDSYS_MERCHANT_CODE,
            'Ds_Merchant_Currency' => '978', // EUR
            'Ds_Merchant_TransactionType' => '0', // Autorización
            'Ds_Merchant_Terminal' => TBV2_REDSYS_TERMINAL,
            'Ds_Merchant_MerchantURL' => tbv2_get_callback_url('notification', $order_id),
            'Ds_Merchant_UrlOK' => tbv2_get_callback_url('success', $order_id) . '&temp_id=' . urlencode($temp_id),
            'Ds_Merchant_UrlKO' => tbv2_get_callback_url('error', $order_id) . '&temp_id=' . urlencode($temp_id),
            'Ds_Merchant_ProductDescription' => $description,
            'Ds_Merchant_Titular' => $customer_name,
            'Ds_Merchant_MerchantData' => base64_encode(json_encode([
                'temp_id' => $temp_id,
                'customer_name' => $customer_name,
                'amount' => $amount,
                'created_at' => current_time('mysql')
            ]))
        ];
        
        // Generar firma
        $signature = tbv2_redsys_generate_signature($params);
        $merchant_parameters = base64_encode(json_encode($params));
        
        // Determinar URL de Redsys
        $redsys_url = TBV2_REDSYS_URL_TEST; // Cambiar a TBV2_REDSYS_URL_LIVE para producción
        
        $response_data = [
            'redsys_url' => $redsys_url,
            'params' => [
                'Ds_SignatureVersion' => 'HMAC_SHA256_V1',
                'Ds_MerchantParameters' => $merchant_parameters,
                'Ds_Signature' => $signature
            ]
        ];
        
        error_log("💳 TBV2 TEMPORAL: Pago creado para temp_id: {$temp_id}, order_id: {$order_id}, amount: {$amount}€");
        
        wp_send_json_success($response_data);
        
    } catch (Exception $e) {
        error_log("❌ TBV2 TEMPORAL ERROR (create payment): " . $e->getMessage());
        wp_send_json_error($e->getMessage());
    }
}

/**
 * MODIFICACIÓN: Handler de callback de éxito mejorado
 */
function tbv2_handle_redsys_success_temporal($order_id, $temp_id = null) {
    error_log("✅ TBV2 TEMPORAL SUCCESS: Procesando éxito para order_id: {$order_id}, temp_id: {$temp_id}");
    
    try {
        // Si no viene temp_id en parámetros, intentar extraer del merchant_data
        if (!$temp_id) {
            $merchant_data = $_GET['Ds_MerchantData'] ?? '';
            if (!empty($merchant_data)) {
                $decoded_data = json_decode(base64_decode($merchant_data), true);
                $temp_id = $decoded_data['temp_id'] ?? null;
            }
        }
        
        if (!$temp_id) {
            throw new Exception('No se pudo determinar temp_id para el callback de éxito');
        }
        
        // Recuperar datos del pago desde el callback
        $payment_data = [
            'Ds_Order' => $order_id,
            'Ds_Response' => $_GET['Ds_Response'] ?? '0000',
            'Ds_Amount' => $_GET['Ds_Amount'] ?? '0',
            'Ds_AuthorisationCode' => $_GET['Ds_AuthorisationCode'] ?? '',
            'Ds_TransactionType' => $_GET['Ds_TransactionType'] ?? '0',
            'Ds_MerchantCode' => $_GET['Ds_MerchantCode'] ?? '',
            'Ds_Terminal' => $_GET['Ds_Terminal'] ?? '',
            'Ds_Currency' => $_GET['Ds_Currency'] ?? '978'
        ];
        
        // Procesar con el sistema temporal
        $result = TBV2_Redsys_Callback::handle_success_callback($temp_id, $payment_data);
        
        if ($result['success']) {
            error_log("✅ TBV2 TEMPORAL: Transferencia completada exitosamente - Trámite: {$result['tramite_id']}");
            
            // Redirigir a página de éxito con información del trámite
            $success_url = add_query_arg([
                'tbv2_result' => 'success',
                'tramite_id' => $result['tramite_id'],
                'temp_id' => $temp_id,
                'amount' => $result['payment_data']['amount_paid']
            ], get_permalink());
            
            wp_redirect($success_url);
            exit;
            
        } else {
            throw new Exception($result['error']);
        }
        
    } catch (Exception $e) {
        error_log("❌ TBV2 TEMPORAL ERROR (success callback): " . $e->getMessage());
        
        // Redirigir a página de error
        $error_url = add_query_arg([
            'tbv2_result' => 'error',
            'error_message' => urlencode($e->getMessage()),
            'temp_id' => $temp_id ?? 'unknown'
        ], get_permalink());
        
        wp_redirect($error_url);
        exit;
    }
}

/**
 * MODIFICACIÓN: Handler de callback de error mejorado
 */
function tbv2_handle_redsys_error_temporal($order_id, $temp_id = null) {
    error_log("❌ TBV2 TEMPORAL ERROR: Procesando error para order_id: {$order_id}, temp_id: {$temp_id}");
    
    try {
        // Si no viene temp_id, intentar extraer del merchant_data
        if (!$temp_id) {
            $merchant_data = $_GET['Ds_MerchantData'] ?? '';
            if (!empty($merchant_data)) {
                $decoded_data = json_decode(base64_decode($merchant_data), true);
                $temp_id = $decoded_data['temp_id'] ?? null;
            }
        }
        
        if (!$temp_id) {
            error_log("⚠️ TBV2 TEMPORAL WARNING: No se pudo determinar temp_id para callback de error");
        }
        
        // Recuperar datos del error
        $payment_data = [
            'Ds_Order' => $order_id,
            'Ds_Response' => $_GET['Ds_Response'] ?? '9999',
            'Ds_Amount' => $_GET['Ds_Amount'] ?? '0',
            'Ds_ErrorCode' => $_GET['Ds_ErrorCode'] ?? ''
        ];
        
        // Si tenemos temp_id, procesar el error con el sistema temporal
        if ($temp_id) {
            $result = TBV2_Redsys_Callback::handle_error_callback($temp_id, $payment_data);
            error_log("💸 TBV2 TEMPORAL: Error procesado - {$result['error']} (Code: {$result['error_code']})");
        }
        
        // Redirigir a página de error con información
        $error_url = add_query_arg([
            'tbv2_result' => 'payment_failed',
            'error_code' => $payment_data['Ds_Response'],
            'temp_id' => $temp_id ?? 'unknown',
            'order_id' => $order_id
        ], get_permalink());
        
        wp_redirect($error_url);
        exit;
        
    } catch (Exception $e) {
        error_log("❌ TBV2 TEMPORAL EXCEPTION (error callback): " . $e->getMessage());
        
        // Fallback: redirigir a error genérico
        $fallback_url = add_query_arg([
            'tbv2_result' => 'system_error',
            'error_message' => urlencode('Error interno del sistema'),
            'temp_id' => $temp_id ?? 'unknown'
        ], get_permalink());
        
        wp_redirect($fallback_url);
        exit;
    }
}

/**
 * MODIFICACIÓN: Handler de notificación server-to-server
 */
function tbv2_handle_redsys_notification_temporal() {
    error_log("🔔 TBV2 TEMPORAL NOTIFICATION: Recibida notificación server-to-server");
    
    try {
        // Validar datos de la notificación
        $merchant_params = $_POST['Ds_MerchantParameters'] ?? '';
        $signature = $_POST['Ds_Signature'] ?? '';
        
        if (empty($merchant_params) || empty($signature)) {
            throw new Exception('Parámetros de notificación faltantes');
        }
        
        // Validar firma
        if (!tbv2_redsys_validate_response($merchant_params, $signature)) {
            throw new Exception('Firma de notificación inválida');
        }
        
        // Decodificar parámetros
        $params = json_decode(base64_decode($merchant_params), true);
        
        $order_id = $params['Ds_Order'] ?? '';
        $response_code = intval($params['Ds_Response'] ?? 9999);
        
        // Extraer temp_id del merchant_data si existe
        $temp_id = null;
        if (!empty($params['Ds_MerchantData'])) {
            $merchant_data = json_decode(base64_decode($params['Ds_MerchantData']), true);
            $temp_id = $merchant_data['temp_id'] ?? null;
        }
        
        error_log("🔔 TBV2 NOTIFICATION: order_id: {$order_id}, response: {$response_code}, temp_id: " . ($temp_id ?? 'N/A'));
        
        if ($response_code <= 99) {
            // Pago exitoso
            if ($temp_id) {
                $result = TBV2_Redsys_Callback::handle_success_callback($temp_id, $params);
                
                if ($result['success']) {
                    error_log("✅ TBV2 NOTIFICATION: Procesamiento exitoso para {$temp_id}");
                    echo '[OK]';
                } else {
                    error_log("❌ TBV2 NOTIFICATION: Error procesando {$temp_id}: {$result['error']}");
                    echo '[KO]';
                }
            } else {
                error_log("⚠️ TBV2 NOTIFICATION: Pago exitoso sin temp_id, usando método legacy");
                // Fallback al método original si no hay temp_id
                tbv2_process_successful_payment($order_id, $params);
                echo '[OK]';
            }
        } else {
            // Pago fallido
            if ($temp_id) {
                $result = TBV2_Redsys_Callback::handle_error_callback($temp_id, $params);
                error_log("💸 TBV2 NOTIFICATION: Pago fallido procesado para {$temp_id}");
            } else {
                error_log("💸 TBV2 NOTIFICATION: Pago fallido sin temp_id");
            }
            echo '[OK]';
        }
        
    } catch (Exception $e) {
        error_log("❌ TBV2 NOTIFICATION ERROR: " . $e->getMessage());
        echo '[KO]';
    }
    
    exit;
}

/**
 * JavaScript para integrar el nuevo sistema en el formulario
 */
function tbv2_add_temporal_integration_js() {
    // Solo en páginas que contienen el formulario TBV2
    global $post;
    if (!$post || strpos($post->post_content, '[transferencia_barco_v2_form]') === false) {
        return;
    }
    
    // Incluir el JavaScript de integración
    echo '<script type="text/javascript">';
    echo file_get_contents(__DIR__ . '/tbv2-temporal-integration.js');
    echo '</script>';
    
    // Configuración JavaScript adicional
    echo '<script type="text/javascript">
    // Configurar TBV2 para usar sistema temporal
    document.addEventListener("DOMContentLoaded", function() {
        // Interceptar el botón de proceder al pago
        const proceedButton = document.querySelector(".tbv2-modal-proceed-btn, #proceed-to-signature");
        
        if (proceedButton) {
            // Remover eventos existentes y añadir el nuevo handler
            proceedButton.removeAttribute("onclick");
            
            proceedButton.addEventListener("click", function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                console.log("🚀 TBV2 TEMPORAL: Botón interceptado, usando sistema temporal");
                
                // Usar el nuevo sistema temporal
                if (window.TBV2TemporalSystem) {
                    window.TBV2TemporalSystem.processWithTemporal();
                } else {
                    console.error("❌ TBV2 TEMPORAL: Sistema temporal no cargado");
                    alert("Error: Sistema temporal no disponible");
                }
            });
            
            console.log("✅ TBV2 TEMPORAL: Botón de pago interceptado exitosamente");
        }
        
        // Mostrar mensajes de resultado si existen
        const urlParams = new URLSearchParams(window.location.search);
        const result = urlParams.get("tbv2_result");
        
        if (result) {
            setTimeout(() => {
                tbv2_show_result_message(result, urlParams);
            }, 1000);
        }
    });
    
    function tbv2_show_result_message(result, params) {
        let message = "";
        let type = "info";
        
        switch(result) {
            case "success":
                type = "success";
                const tramiteId = params.get("tramite_id");
                const amount = params.get("amount");
                message = `
                    <div class="tbv2-success-message">
                        <h4>✅ ¡Pago completado exitosamente!</h4>
                        <p><strong>Trámite creado:</strong> ${tramiteId}</p>
                        <p><strong>Importe:</strong> ${amount}€</p>
                        <p>Recibirá un email de confirmación en breve.</p>
                    </div>
                `;
                break;
                
            case "payment_failed":
                type = "error";
                const errorCode = params.get("error_code");
                message = `
                    <div class="tbv2-error-message">
                        <h4>❌ Error en el pago</h4>
                        <p>El pago no pudo ser procesado.</p>
                        <p><strong>Código de error:</strong> ${errorCode}</p>
                        <p>Sus datos se han guardado de forma segura. Puede intentar el pago nuevamente.</p>
                    </div>
                `;
                break;
                
            case "error":
            case "system_error":
                type = "error";
                const errorMessage = params.get("error_message");
                message = `
                    <div class="tbv2-error-message">
                        <h4>⚠️ Error del sistema</h4>
                        <p>${decodeURIComponent(errorMessage || "Ha ocurrido un error inesperado")}</p>
                        <p>Por favor, contacte con soporte si el problema persiste.</p>
                    </div>
                `;
                break;
        }
        
        if (message && window.TBV2TemporalSystem) {
            window.TBV2TemporalSystem.showAlert(message, type);
            
            // Limpiar URL
            const cleanUrl = window.location.pathname;
            window.history.replaceState({}, document.title, cleanUrl);
        }
    }
    </script>';
}

add_action('wp_footer', 'tbv2_add_temporal_integration_js');

/**
 * Hook para actualizar las URLs de callback con el sistema temporal
 */
function tbv2_update_callback_urls_for_temporal($type, $orderId = null) {
    $base_url = get_permalink();
    
    switch($type) {
        case 'success':
            return add_query_arg(['tbv2_callback' => 'success', 'order_id' => $orderId], $base_url);
            
        case 'error':
            return add_query_arg(['tbv2_callback' => 'error', 'order_id' => $orderId], $base_url);
            
        case 'notification':
            return add_query_arg(['tbv2_callback' => 'notification'], $base_url);
            
        default:
            return $base_url;
    }
}

/**
 * Handler para procesar callbacks en las URLs
 */
function tbv2_process_callback_urls() {
    if (!isset($_GET['tbv2_callback'])) {
        return;
    }
    
    $callback_type = sanitize_text_field($_GET['tbv2_callback']);
    $order_id = sanitize_text_field($_GET['order_id'] ?? '');
    $temp_id = sanitize_text_field($_GET['temp_id'] ?? '');
    
    switch($callback_type) {
        case 'success':
            tbv2_handle_redsys_success_temporal($order_id, $temp_id);
            break;
            
        case 'error':
            tbv2_handle_redsys_error_temporal($order_id, $temp_id);
            break;
            
        case 'notification':
            tbv2_handle_redsys_notification_temporal();
            break;
    }
}

add_action('template_redirect', 'tbv2_process_callback_urls');

error_log("✅ TBV2 TEMPORAL INTEGRATION: Parche de integración cargado exitosamente");