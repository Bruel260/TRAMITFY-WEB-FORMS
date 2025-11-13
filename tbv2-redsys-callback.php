<?php
/**
 * TBV2 REDSYS CALLBACK SYSTEM
 * Maneja callbacks de Redsys y transfiere datos temporales al webhook definitivo
 * 
 * Funcionalidades:
 * - Validación de pagos Redsys
 * - Transferencia temporal → definitivo
 * - Manejo de errores y rollbacks
 * - Logs detallados
 */

if (!defined('ABSPATH')) {
    exit('Acceso directo no permitido');
}

// Incluir sistema temporal
require_once(__DIR__ . '/tbv2-temporal-storage.php');

class TBV2_Redsys_Callback {
    
    /**
     * Procesa callback de éxito de Redsys
     */
    public static function handle_success_callback($temp_id, $payment_data) {
        error_log("🔄 TBV2 CALLBACK: Iniciando procesamiento para temp_id: {$temp_id}");
        
        try {
            // Validar que el temp_id existe y no ha expirado
            $temp_data = TBV2_Temporal_Storage::get_temp_data($temp_id);
            if (!$temp_data) {
                throw new Exception("Datos temporales no encontrados o expirados: {$temp_id}");
            }
            
            // Validar datos de pago
            if (!self::validate_payment_data($payment_data)) {
                throw new Exception("Datos de pago inválidos");
            }
            
            // Preparar datos de pago para el webhook
            $processed_payment_data = [
                'payment_method' => 'redsys',
                'transaction_id' => $payment_data['Ds_Order'] ?? null,
                'authorization_code' => $payment_data['Ds_AuthorisationCode'] ?? null,
                'response_code' => $payment_data['Ds_Response'] ?? null,
                'amount_paid' => floatval($payment_data['Ds_Amount'] ?? 0) / 100, // Redsys envía céntimos
                'currency' => $payment_data['Ds_Currency'] ?? '978', // EUR
                'transaction_type' => $payment_data['Ds_TransactionType'] ?? null,
                'merchant_code' => $payment_data['Ds_MerchantCode'] ?? null,
                'terminal' => $payment_data['Ds_Terminal'] ?? null,
                'processed_at' => current_time('mysql'),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ];
            
            error_log("💳 TBV2 CALLBACK: Pago validado - Amount: {$processed_payment_data['amount_paid']}€, Auth: {$processed_payment_data['authorization_code']}");
            
            // Transferir al webhook definitivo
            $webhook_result = TBV2_Temporal_Storage::transfer_to_webhook($temp_id, $processed_payment_data);
            
            if ($webhook_result && $webhook_result['success']) {
                error_log("✅ TBV2 CALLBACK: Transferencia completada exitosamente para {$temp_id}");
                
                // Log del trámite creado
                $tramite_id = str_replace('TEMP-', '', $temp_id);
                error_log("📋 TBV2 CALLBACK: Trámite creado con ID: {$tramite_id}");
                
                return [
                    'success' => true,
                    'temp_id' => $temp_id,
                    'tramite_id' => $tramite_id,
                    'payment_data' => $processed_payment_data,
                    'webhook_response' => $webhook_result['response'] ?? null
                ];
                
            } else {
                throw new Exception("Error en webhook: " . ($webhook_result['error'] ?? 'Respuesta inválida'));
            }
            
        } catch (Exception $e) {
            error_log("❌ TBV2 CALLBACK ERROR: " . $e->getMessage());
            
            // En caso de error, mantener los datos temporales para retry manual
            self::mark_temp_data_for_retry($temp_id, $e->getMessage(), $payment_data);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'temp_id' => $temp_id,
                'payment_data' => $payment_data ?? null
            ];
        }
    }
    
    /**
     * Procesa callback de error de Redsys
     */
    public static function handle_error_callback($temp_id, $payment_data) {
        error_log("❌ TBV2 CALLBACK ERROR: Pago fallido para temp_id: {$temp_id}");
        
        $error_info = [
            'temp_id' => $temp_id,
            'error_code' => $payment_data['Ds_Response'] ?? 'unknown',
            'error_description' => self::get_redsys_error_description($payment_data['Ds_Response'] ?? null),
            'order_id' => $payment_data['Ds_Order'] ?? null,
            'amount' => floatval($payment_data['Ds_Amount'] ?? 0) / 100,
            'failed_at' => current_time('mysql'),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ];
        
        // Marcar datos temporales con información de error
        self::mark_temp_data_failed($temp_id, $error_info);
        
        error_log("💸 TBV2 CALLBACK: Pago fallido - Code: {$error_info['error_code']}, Amount: {$error_info['amount']}€");
        
        return [
            'success' => false,
            'error' => $error_info['error_description'],
            'error_code' => $error_info['error_code'],
            'temp_id' => $temp_id
        ];
    }
    
    /**
     * Valida que los datos de pago son correctos
     */
    private static function validate_payment_data($payment_data) {
        // Verificar campos obligatorios
        $required_fields = ['Ds_Order', 'Ds_Response', 'Ds_Amount'];
        
        foreach ($required_fields as $field) {
            if (!isset($payment_data[$field]) || empty($payment_data[$field])) {
                error_log("❌ TBV2 CALLBACK: Campo requerido faltante: {$field}");
                return false;
            }
        }
        
        // Verificar que la respuesta indica éxito (0-99 es éxito en Redsys)
        $response_code = intval($payment_data['Ds_Response']);
        if ($response_code < 0 || $response_code > 99) {
            error_log("❌ TBV2 CALLBACK: Código de respuesta indica fallo: {$response_code}");
            return false;
        }
        
        // Verificar que el amount es positivo
        $amount = floatval($payment_data['Ds_Amount'] ?? 0);
        if ($amount <= 0) {
            error_log("❌ TBV2 CALLBACK: Amount inválido: {$amount}");
            return false;
        }
        
        return true;
    }
    
    /**
     * Marca datos temporales para retry manual en caso de error
     */
    private static function mark_temp_data_for_retry($temp_id, $error_message, $payment_data) {
        $retry_data = [
            'temp_id' => $temp_id,
            'error' => $error_message,
            'payment_data' => $payment_data,
            'failed_at' => current_time('mysql'),
            'retry_count' => 0,
            'status' => 'pending_retry'
        ];
        
        // Guardar en transient específico para retries (TTL más largo: 24 horas)
        set_transient("tbv2_retry_{$temp_id}", $retry_data, 24 * HOUR_IN_SECONDS);
        
        error_log("🔄 TBV2 CALLBACK: Datos marcados para retry: {$temp_id}");
    }
    
    /**
     * Marca datos temporales como fallidos definitivamente
     */
    private static function mark_temp_data_failed($temp_id, $error_info) {
        $failed_data = [
            'temp_id' => $temp_id,
            'error_info' => $error_info,
            'failed_at' => current_time('mysql'),
            'status' => 'payment_failed'
        ];
        
        // Guardar en transient de fallos (TTL: 7 días para auditoría)
        set_transient("tbv2_failed_{$temp_id}", $failed_data, 7 * DAY_IN_SECONDS);
        
        error_log("💀 TBV2 CALLBACK: Datos marcados como fallidos: {$temp_id}");
    }
    
    /**
     * Obtiene descripción de error de Redsys
     */
    private static function get_redsys_error_description($error_code) {
        $error_descriptions = [
            '0180' => 'Tarjeta ajena al servicio',
            '0184' => 'Error en la autenticación del titular',
            '0190' => 'Denegación del emisor sin especificar motivo',
            '0191' => 'Fecha de caducidad errónea',
            '0202' => 'Tarjeta en excepción transitoria o bajo sospecha de fraude',
            '0904' => 'Comercio no registrado en FUC',
            '0909' => 'Error de sistema',
            '0912' => 'Emisor no disponible',
            '9912' => 'Emisor no disponible'
        ];
        
        return $error_descriptions[$error_code] ?? 'Error desconocido en el pago';
    }
    
    /**
     * Procesa retry manual de datos temporales
     */
    public static function retry_failed_transfer($temp_id) {
        $retry_data = get_transient("tbv2_retry_{$temp_id}");
        
        if (!$retry_data) {
            return [
                'success' => false,
                'error' => 'Datos de retry no encontrados o expirados'
            ];
        }
        
        // Incrementar contador de reintentos
        $retry_data['retry_count']++;
        $retry_data['last_retry_at'] = current_time('mysql');
        
        if ($retry_data['retry_count'] > 3) {
            delete_transient("tbv2_retry_{$temp_id}");
            return [
                'success' => false,
                'error' => 'Máximo número de reintentos alcanzado'
            ];
        }
        
        // Intentar transferencia nuevamente
        $result = self::handle_success_callback($temp_id, $retry_data['payment_data']);
        
        if ($result['success']) {
            // Limpiar datos de retry tras éxito
            delete_transient("tbv2_retry_{$temp_id}");
            error_log("✅ TBV2 RETRY: Transferencia exitosa en intento #{$retry_data['retry_count']} para {$temp_id}");
        } else {
            // Actualizar datos de retry
            set_transient("tbv2_retry_{$temp_id}", $retry_data, 24 * HOUR_IN_SECONDS);
            error_log("❌ TBV2 RETRY: Fallo en intento #{$retry_data['retry_count']} para {$temp_id}");
        }
        
        return $result;
    }
    
    /**
     * Obtiene estadísticas del sistema temporal
     */
    public static function get_temp_stats() {
        global $wpdb;
        
        // Contar transients activos
        $active_temps = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_tbv2_data_%'"
        );
        
        // Contar datos de retry
        $pending_retries = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_tbv2_retry_%'"
        );
        
        // Contar fallos
        $failed_payments = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_tbv2_failed_%'"
        );
        
        return [
            'active_temporals' => intval($active_temps),
            'pending_retries' => intval($pending_retries),
            'failed_payments' => intval($failed_payments),
            'temp_directory_size' => self::get_temp_directory_size(),
            'last_cleanup' => wp_next_scheduled('tbv2_cleanup_temp_data')
        ];
    }
    
    /**
     * Calcula el tamaño del directorio temporal
     */
    private static function get_temp_directory_size() {
        if (!is_dir(TBV2_TEMP_DIR)) {
            return 0;
        }
        
        $size = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(TBV2_TEMP_DIR, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }
        
        return $size;
    }
}

/**
 * AJAX ENDPOINTS para manejo de callbacks
 */

// Retry manual de transferencias fallidas
add_action('wp_ajax_tbv2_retry_transfer', 'tbv2_handle_retry_transfer');

function tbv2_handle_retry_transfer() {
    if (!current_user_can('manage_options')) {
        wp_die('Sin permisos');
    }
    
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'tbv2_admin_nonce')) {
        wp_die('Nonce inválido');
    }
    
    $temp_id = sanitize_text_field($_POST['temp_id'] ?? '');
    
    if (empty($temp_id)) {
        wp_send_json_error('temp_id requerido');
    }
    
    $result = TBV2_Redsys_Callback::retry_failed_transfer($temp_id);
    
    if ($result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result);
    }
}

// Obtener estadísticas del sistema temporal  
add_action('wp_ajax_tbv2_get_temp_stats', 'tbv2_handle_get_temp_stats');

function tbv2_handle_get_temp_stats() {
    if (!current_user_can('manage_options')) {
        wp_die('Sin permisos');
    }
    
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'tbv2_admin_nonce')) {
        wp_die('Nonce inválido');
    }
    
    $stats = TBV2_Redsys_Callback::get_temp_stats();
    wp_send_json_success($stats);
}