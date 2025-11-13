<?php
/**
 * TBV2 TEMPORAL STORAGE SYSTEM
 * Sistema de almacenamiento temporal para datos y archivos antes del pago
 * 
 * Funcionalidades:
 * - Storage temporal con TTL de 2 horas
 * - Gestión de archivos en directorio temporal
 * - Limpieza automática de datos expirados
 * - Logs detallados para debugging
 */

if (!defined('ABSPATH')) {
    exit('Acceso directo no permitido');
}

// Configuración del sistema temporal
if (!defined('TBV2_TEMP_TTL')) define('TBV2_TEMP_TTL', 2 * HOUR_IN_SECONDS); // 2 horas
if (!defined('TBV2_TEMP_DIR')) define('TBV2_TEMP_DIR', wp_upload_dir()['basedir'] . '/temp-tbv2/');
if (!defined('TBV2_TEMP_URL')) define('TBV2_TEMP_URL', wp_upload_dir()['baseurl'] . '/temp-tbv2/');

class TBV2_Temporal_Storage {
    
    /**
     * Genera un ID único temporal
     */
    public static function generate_temp_id() {
        return 'TBV2-TEMP-' . time() . '-' . wp_generate_password(8, false);
    }
    
    /**
     * Crea directorio temporal para un ID específico
     */
    public static function create_temp_directory($temp_id) {
        $temp_path = TBV2_TEMP_DIR . $temp_id . '/';
        
        if (!file_exists($temp_path)) {
            if (wp_mkdir_p($temp_path)) {
                // Crear .htaccess para proteger el directorio
                $htaccess_content = "Order deny,allow\nDeny from all";
                file_put_contents($temp_path . '.htaccess', $htaccess_content);
                
                error_log("✅ TBV2 TEMPORAL: Directorio creado: {$temp_path}");
                return $temp_path;
            } else {
                error_log("❌ TBV2 TEMPORAL ERROR: No se pudo crear directorio: {$temp_path}");
                return false;
            }
        }
        
        return $temp_path;
    }
    
    /**
     * Guarda archivos en el storage temporal
     */
    public static function save_files($temp_id, $files_data) {
        $temp_path = self::create_temp_directory($temp_id);
        if (!$temp_path) {
            return false;
        }
        
        $saved_files = [];
        
        foreach ($files_data as $field_name => $files_info) {
            if (!isset($files_info['tmp_name']) || !is_array($files_info['tmp_name'])) {
                continue;
            }
            
            $field_files = [];
            
            for ($i = 0; $i < count($files_info['tmp_name']); $i++) {
                if (empty($files_info['tmp_name'][$i])) {
                    continue;
                }
                
                $original_name = sanitize_file_name($files_info['name'][$i]);
                $temp_filename = sprintf('%s_%03d_%s', $field_name, $i + 1, $original_name);
                $destination = $temp_path . $temp_filename;
                
                if (move_uploaded_file($files_info['tmp_name'][$i], $destination)) {
                    $field_files[] = [
                        'original_name' => $original_name,
                        'temp_filename' => $temp_filename,
                        'temp_path' => $destination,
                        'size' => filesize($destination),
                        'type' => $files_info['type'][$i]
                    ];
                    
                    error_log("📎 TBV2 TEMPORAL: Archivo guardado: {$temp_filename}");
                } else {
                    error_log("❌ TBV2 TEMPORAL ERROR: Fallo al mover archivo: {$original_name}");
                }
            }
            
            if (!empty($field_files)) {
                $saved_files[$field_name] = $field_files;
            }
        }
        
        return $saved_files;
    }
    
    /**
     * Guarda datos temporales completos
     */
    public static function save_temp_data($customer_data, $vehicle_data, $pricing_data, $files_data = [], $signature_data = null) {
        $temp_id = self::generate_temp_id();
        $expires_at = time() + TBV2_TEMP_TTL;
        
        // Procesar archivos si existen
        $saved_files = [];
        if (!empty($files_data)) {
            $saved_files = self::save_files($temp_id, $files_data);
            if ($saved_files === false) {
                error_log("❌ TBV2 TEMPORAL ERROR: Fallo al guardar archivos para {$temp_id}");
                return false;
            }
        }
        
        // Procesar firma digital
        $signature_file = null;
        if (!empty($signature_data)) {
            $signature_file = self::save_signature($temp_id, $signature_data);
        }
        
        // Preparar datos completos
        $complete_data = [
            'temp_id' => $temp_id,
            'created_at' => current_time('mysql'),
            'expires_at' => date('Y-m-d H:i:s', $expires_at),
            'customer_data' => $customer_data,
            'vehicle_data' => $vehicle_data,
            'pricing_data' => $pricing_data,
            'files' => $saved_files,
            'signature' => $signature_file,
            'status' => 'temporal',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ];
        
        // Guardar en transients WordPress
        $data_saved = set_transient("tbv2_data_{$temp_id}", $complete_data, TBV2_TEMP_TTL);
        $meta_saved = set_transient("tbv2_meta_{$temp_id}", [
            'temp_id' => $temp_id,
            'created_at' => time(),
            'expires_at' => $expires_at,
            'files_count' => count($saved_files),
            'has_signature' => !empty($signature_file)
        ], TBV2_TEMP_TTL);
        
        if ($data_saved && $meta_saved) {
            error_log("✅ TBV2 TEMPORAL: Datos guardados exitosamente: {$temp_id}");
            error_log("📊 TBV2 TEMPORAL: Archivos: " . count($saved_files) . " campos, Firma: " . ($signature_file ? 'SÍ' : 'NO'));
            
            return [
                'success' => true,
                'temp_id' => $temp_id,
                'expires_at' => date('c', $expires_at),
                'files_count' => count($saved_files),
                'has_signature' => !empty($signature_file)
            ];
        } else {
            error_log("❌ TBV2 TEMPORAL ERROR: Fallo al guardar transients para {$temp_id}");
            self::cleanup_temp_directory($temp_id);
            return false;
        }
    }
    
    /**
     * Guarda firma digital en formato PNG
     */
    public static function save_signature($temp_id, $signature_data) {
        if (empty($signature_data) || strpos($signature_data, 'data:image/png;base64,') !== 0) {
            return null;
        }
        
        $temp_path = self::create_temp_directory($temp_id);
        if (!$temp_path) {
            return null;
        }
        
        // Decodificar base64
        $image_data = base64_decode(str_replace('data:image/png;base64,', '', $signature_data));
        if ($image_data === false) {
            error_log("❌ TBV2 TEMPORAL ERROR: Fallo al decodificar firma digital");
            return null;
        }
        
        $signature_filename = 'firma_digital.png';
        $signature_path = $temp_path . $signature_filename;
        
        if (file_put_contents($signature_path, $image_data)) {
            error_log("✍️ TBV2 TEMPORAL: Firma digital guardada: {$signature_filename}");
            return [
                'filename' => $signature_filename,
                'path' => $signature_path,
                'size' => filesize($signature_path)
            ];
        } else {
            error_log("❌ TBV2 TEMPORAL ERROR: Fallo al guardar firma digital");
            return null;
        }
    }
    
    /**
     * Recupera datos temporales
     */
    public static function get_temp_data($temp_id) {
        $data = get_transient("tbv2_data_{$temp_id}");
        $meta = get_transient("tbv2_meta_{$temp_id}");
        
        if ($data === false) {
            error_log("❌ TBV2 TEMPORAL: Datos no encontrados o expirados: {$temp_id}");
            return null;
        }
        
        // Verificar que los archivos aún existen
        if (!empty($data['files'])) {
            foreach ($data['files'] as $field_name => $files) {
                foreach ($files as $file_info) {
                    if (!file_exists($file_info['temp_path'])) {
                        error_log("⚠️ TBV2 TEMPORAL WARNING: Archivo faltante: {$file_info['temp_filename']}");
                    }
                }
            }
        }
        
        error_log("✅ TBV2 TEMPORAL: Datos recuperados exitosamente: {$temp_id}");
        return $data;
    }
    
    /**
     * Transfiere datos temporales al webhook definitivo
     */
    public static function transfer_to_webhook($temp_id, $payment_data = []) {
        $temp_data = self::get_temp_data($temp_id);
        if (!$temp_data) {
            return false;
        }
        
        try {
            // Preparar datos para webhook
            $webhook_data = [
                'tramiteId' => str_replace('TEMP-', '', $temp_id), // Convertir a ID definitivo
                'tramiteType' => 'transferencia-barco-v2',
                'customerName' => $temp_data['customer_data']['name'] ?? '',
                'customerEmail' => $temp_data['customer_data']['email'] ?? '',
                'customerDni' => $temp_data['customer_data']['dni'] ?? '',
                'customerPhone' => $temp_data['customer_data']['phone'] ?? '',
                'vehicleType' => 'Embarcación',
                'manufacturer' => $temp_data['vehicle_data']['manufacturer'] ?? '',
                'model' => $temp_data['vehicle_data']['model'] ?? '',
                'matriculationDate' => $temp_data['vehicle_data']['matriculation_date'] ?? '',
                'purchasePrice' => $temp_data['vehicle_data']['purchase_price'] ?? 0,
                'region' => $temp_data['vehicle_data']['region'] ?? '',
                'finalAmount' => $temp_data['pricing_data']['final_amount'] ?? 0,
                'transferTax' => $temp_data['pricing_data']['transfer_tax'] ?? 0,
                'extraFee' => $temp_data['pricing_data']['extra_fee'] ?? 0,
                'status' => 'pending',
                'payment_data' => $payment_data,
                'temp_metadata' => [
                    'original_temp_id' => $temp_id,
                    'created_at' => $temp_data['created_at'],
                    'ip_address' => $temp_data['ip_address']
                ]
            ];
            
            // Preparar archivos para multipart upload
            $files_to_upload = [];
            if (!empty($temp_data['files'])) {
                foreach ($temp_data['files'] as $field_name => $files) {
                    foreach ($files as $file_info) {
                        if (file_exists($file_info['temp_path'])) {
                            $files_to_upload[] = [
                                'field_name' => $field_name,
                                'file_path' => $file_info['temp_path'],
                                'original_name' => $file_info['original_name'],
                                'mime_type' => $file_info['type']
                            ];
                        }
                    }
                }
            }
            
            // Añadir firma si existe
            if (!empty($temp_data['signature']) && file_exists($temp_data['signature']['path'])) {
                $files_to_upload[] = [
                    'field_name' => 'authorization_pdf',
                    'file_path' => $temp_data['signature']['path'],
                    'original_name' => 'firma_digital.png',
                    'mime_type' => 'image/png'
                ];
            }
            
            // Enviar al webhook con archivos
            $webhook_response = self::send_webhook_with_files(
                'https://46-202-128-35.sslip.io/api/herramientas/barcos/webhook',
                $webhook_data,
                $files_to_upload
            );
            
            if ($webhook_response['success']) {
                // Limpiar datos temporales tras éxito
                self::cleanup_temp_data($temp_id);
                error_log("✅ TBV2 TEMPORAL: Transferencia exitosa al webhook: {$temp_id}");
                return $webhook_response;
            } else {
                error_log("❌ TBV2 TEMPORAL ERROR: Fallo en webhook: " . $webhook_response['error']);
                return false;
            }
            
        } catch (Exception $e) {
            error_log("❌ TBV2 TEMPORAL EXCEPTION: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Envía webhook con archivos multipart
     */
    private static function send_webhook_with_files($webhook_url, $data, $files) {
        // Crear boundary único para multipart
        $boundary = wp_generate_password(16, false);
        
        $body = '';
        
        // Añadir datos JSON
        foreach ($data as $key => $value) {
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"{$key}\"\r\n\r\n";
            $body .= is_array($value) ? json_encode($value) : $value;
            $body .= "\r\n";
        }
        
        // Añadir archivos
        foreach ($files as $file) {
            $file_content = file_get_contents($file['file_path']);
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"{$file['field_name']}\"; filename=\"{$file['original_name']}\"\r\n";
            $body .= "Content-Type: {$file['mime_type']}\r\n\r\n";
            $body .= $file_content;
            $body .= "\r\n";
        }
        
        $body .= "--{$boundary}--\r\n";
        
        // Enviar request
        $response = wp_remote_post($webhook_url, [
            'method' => 'POST',
            'timeout' => 60,
            'headers' => [
                'Content-Type' => "multipart/form-data; boundary={$boundary}",
            ],
            'body' => $body
        ]);
        
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => $response->get_error_message()
            ];
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code >= 200 && $response_code < 300) {
            return [
                'success' => true,
                'response' => json_decode($response_body, true),
                'status_code' => $response_code
            ];
        } else {
            return [
                'success' => false,
                'error' => "HTTP {$response_code}: {$response_body}",
                'status_code' => $response_code
            ];
        }
    }
    
    /**
     * Limpia datos temporales específicos
     */
    public static function cleanup_temp_data($temp_id) {
        // Eliminar transients
        delete_transient("tbv2_data_{$temp_id}");
        delete_transient("tbv2_meta_{$temp_id}");
        
        // Eliminar directorio de archivos
        self::cleanup_temp_directory($temp_id);
        
        error_log("🗑️ TBV2 TEMPORAL: Limpieza completada: {$temp_id}");
    }
    
    /**
     * Elimina directorio temporal y todos sus archivos
     */
    public static function cleanup_temp_directory($temp_id) {
        $temp_path = TBV2_TEMP_DIR . $temp_id . '/';
        
        if (is_dir($temp_path)) {
            $files = glob($temp_path . '*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($temp_path);
            error_log("📁 TBV2 TEMPORAL: Directorio eliminado: {$temp_path}");
        }
    }
    
    /**
     * Limpieza automática de datos expirados
     */
    public static function cleanup_expired_data() {
        global $wpdb;
        
        // Buscar transients expirados
        $expired_transients = $wpdb->get_results(
            "SELECT option_name FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_timeout_tbv2_%' 
             AND option_value < " . time()
        );
        
        $cleaned_count = 0;
        foreach ($expired_transients as $transient) {
            $temp_id = str_replace(['_transient_timeout_tbv2_data_', '_transient_timeout_tbv2_meta_'], '', $transient->option_name);
            
            if (strpos($temp_id, 'TBV2-TEMP-') === 0) {
                self::cleanup_temp_data($temp_id);
                $cleaned_count++;
            }
        }
        
        // Limpiar directorios huérfanos
        if (is_dir(TBV2_TEMP_DIR)) {
            $dirs = glob(TBV2_TEMP_DIR . 'TBV2-TEMP-*', GLOB_ONLYDIR);
            foreach ($dirs as $dir) {
                $temp_id = basename($dir);
                $data = get_transient("tbv2_data_{$temp_id}");
                
                // Si no existe transient, limpiar directorio
                if ($data === false) {
                    self::cleanup_temp_directory($temp_id);
                    $cleaned_count++;
                }
            }
        }
        
        if ($cleaned_count > 0) {
            error_log("🧹 TBV2 TEMPORAL: Limpieza automática completada. {$cleaned_count} elementos eliminados");
        }
        
        return $cleaned_count;
    }
}

// Hook para limpieza automática cada 6 horas
add_action('tbv2_cleanup_temp_data', ['TBV2_Temporal_Storage', 'cleanup_expired_data']);

if (!wp_next_scheduled('tbv2_cleanup_temp_data')) {
    wp_schedule_event(time(), 'twicedaily', 'tbv2_cleanup_temp_data');
}

/**
 * AJAX ENDPOINT: Guardar datos temporales
 */
add_action('wp_ajax_tbv2_prepare_data', 'tbv2_handle_prepare_data');
add_action('wp_ajax_nopriv_tbv2_prepare_data', 'tbv2_handle_prepare_data');

function tbv2_handle_prepare_data() {
    // Verificar nonce de seguridad
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'tbv2_nonce')) {
        wp_die('Nonce inválido');
    }
    
    try {
        // Extraer datos del POST
        $customer_data = [
            'name' => sanitize_text_field($_POST['customer_name'] ?? ''),
            'email' => sanitize_email($_POST['customer_email'] ?? ''),
            'dni' => sanitize_text_field($_POST['customer_dni'] ?? ''),
            'phone' => sanitize_text_field($_POST['customer_phone'] ?? ''),
            'company' => sanitize_text_field($_POST['company_name'] ?? '')
        ];
        
        $vehicle_data = [
            'tipo_embarcacion' => sanitize_text_field($_POST['tipo_embarcacion'] ?? ''),
            'eslora' => sanitize_text_field($_POST['eslora'] ?? ''),
            'motor' => sanitize_text_field($_POST['motor'] ?? ''),
            'manufacturer' => sanitize_text_field($_POST['manufacturer'] ?? ''),
            'model' => sanitize_text_field($_POST['model'] ?? ''),
            'matricula' => sanitize_text_field($_POST['matricula'] ?? ''),
            'matriculation_date' => sanitize_text_field($_POST['matriculation_date'] ?? ''),
            'purchase_price' => floatval($_POST['purchase_price'] ?? 0),
            'region' => sanitize_text_field($_POST['region'] ?? '')
        ];
        
        $pricing_data = [
            'final_amount' => floatval($_POST['final_amount'] ?? 0),
            'transfer_tax' => floatval($_POST['transfer_tax'] ?? 0),
            'extra_fee' => floatval($_POST['extra_fee'] ?? 0),
            'payment_method' => 'redsys'
        ];
        
        // Procesar archivos subidos
        $files_data = [];
        foreach ($_FILES as $field_name => $file_info) {
            if (!empty($file_info['tmp_name']) && is_array($file_info['tmp_name'])) {
                $files_data[$field_name] = $file_info;
            }
        }
        
        // Procesar firma digital
        $signature_data = $_POST['signature_data'] ?? null;
        
        // Guardar datos temporales
        $result = TBV2_Temporal_Storage::save_temp_data(
            $customer_data,
            $vehicle_data,
            $pricing_data,
            $files_data,
            $signature_data
        );
        
        if ($result) {
            wp_send_json_success([
                'temp_id' => $result['temp_id'],
                'expires_at' => $result['expires_at'],
                'files_count' => $result['files_count'],
                'has_signature' => $result['has_signature']
            ]);
        } else {
            wp_send_json_error('Error al guardar datos temporales');
        }
        
    } catch (Exception $e) {
        error_log("❌ TBV2 PREPARE DATA ERROR: " . $e->getMessage());
        wp_send_json_error('Error interno del servidor');
    }
}