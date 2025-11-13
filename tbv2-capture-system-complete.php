<?php
/**
 * TRAMITFY TBV2 - SISTEMA DE CAPTURA COMPLETO
 * Captura datos del formulario + archivos adjuntos + firma digital
 * 
 * @version 3.0.0 - CAPTURA COMPLETA
 * @author Claude Code
 * @created 2025-11-12
 * @updated 2025-11-12 - Archivos + Firma
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit('Acceso directo no permitido.');
}

// Función para logging específico del sistema de captura
function tbv2_capture_log($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] [CAPTURE] [$level] $message\n";
    file_put_contents('/tmp/tbv2_capture.log', $log_entry, FILE_APPEND | LOCK_EX);
    error_log("TBV2_CAPTURE: $message");
}

/**
 * PASO 3: CAPTURA COMPLETA DE DATOS + ARCHIVOS + FIRMA
 */
function tbv2_capture_form_data_complete() {
    try {
        tbv2_capture_log("=== INICIO CAPTURA COMPLETA ===", "SUCCESS");
        
        // Generar Order ID único
        $order_id = 'TBV2_' . time() . '_' . rand(1000, 9999);
        tbv2_capture_log("Order ID generado: $order_id", "INFO");
        
        // ========================================
        // 1. DATOS PERSONALES DEL FORMULARIO
        // ========================================
        $form_data = [
            'personal' => [
                'customerName' => sanitize_text_field($_POST['customer_name'] ?? ''),
                'customerEmail' => sanitize_email($_POST['customer_email'] ?? ''),
                'customerPhone' => sanitize_text_field($_POST['customer_phone'] ?? ''),
                'customerDni' => sanitize_text_field($_POST['customer_dni'] ?? ''),
                'companyName' => sanitize_text_field($_POST['company_name'] ?? '')
            ],
            'vehicle' => [
                'tipoEmbarcacion' => sanitize_text_field($_POST['tipo_embarcacion'] ?? ''),
                'eslora' => sanitize_text_field($_POST['eslora'] ?? ''),
                'motor' => sanitize_text_field($_POST['motor'] ?? ''),
                'matriculationDate' => sanitize_text_field($_POST['matriculation_date'] ?? ''),
                'purchasePrice' => floatval($_POST['purchase_price'] ?? 0),
                'region' => sanitize_text_field($_POST['region'] ?? '')
            ],
            'payment' => [
                'finalAmount' => floatval($_POST['final_amount'] ?? 0),
                'transferTax' => floatval($_POST['transfer_tax'] ?? 0),
                'extraFee' => floatval($_POST['extra_fee'] ?? 0),
                'paymentMethod' => sanitize_text_field($_POST['payment_method'] ?? 'card')
            ]
        ];
        
        tbv2_capture_log("Datos personales capturados: " . json_encode($form_data['personal']), "INFO");
        
        // ========================================
        // 2. PROCESAMIENTO DE ARCHIVOS ADJUNTOS
        // ========================================
        $attachments = [];
        $file_fields = [
            'upload_hoja_asiento' => 'hojaAsiento',
            'upload_dni_comprador' => 'dniComprador', 
            'upload_dni_vendedor' => 'dniVendedor',
            'upload_contrato_compraventa' => 'contratoCompraventa',
            'upload_modelo_620' => 'modelo620'
        ];
        
        foreach ($file_fields as $field_name => $attachment_key) {
            if (isset($_FILES[$field_name]) && !empty($_FILES[$field_name]['name'][0])) {
                $attachments[$attachment_key] = [];
                
                $files = $_FILES[$field_name];
                $file_count = count($files['name']);
                
                for ($i = 0; $i < $file_count; $i++) {
                    if ($files['error'][$i] === UPLOAD_ERR_OK) {
                        $file_content = file_get_contents($files['tmp_name'][$i]);
                        $mime_type = $files['type'][$i];
                        $file_name = sanitize_file_name($files['name'][$i]);
                        
                        // Convertir archivo a base64
                        $base64_data = base64_encode($file_content);
                        $data_uri = "data:$mime_type;base64,$base64_data";
                        
                        $attachments[$attachment_key][] = [
                            'name' => $file_name,
                            'data' => $data_uri,
                            'mimeType' => $mime_type,
                            'size' => $files['size'][$i]
                        ];
                        
                        tbv2_capture_log("Archivo capturado: $attachment_key - $file_name (" . $files['size'][$i] . " bytes)", "SUCCESS");
                    }
                }
            }
        }
        
        // ========================================
        // 3. CAPTURA DE FIRMA DIGITAL
        // ========================================
        $signature_data = null;
        if (isset($_POST['signature_data']) && !empty($_POST['signature_data'])) {
            $signature_base64 = $_POST['signature_data'];
            
            // Validar que sea una imagen válida en base64
            if (strpos($signature_base64, 'data:image/png;base64,') === 0) {
                $attachments['firma_autorizacion'] = [[
                    'name' => 'firma_digital_' . $order_id . '.png',
                    'data' => $signature_base64,
                    'mimeType' => 'image/png',
                    'size' => strlen(base64_decode(substr($signature_base64, strpos($signature_base64, ',') + 1)))
                ]];
                
                tbv2_capture_log("Firma digital capturada: " . strlen($signature_base64) . " caracteres", "SUCCESS");
            } else {
                tbv2_capture_log("Formato de firma inválido", "ERROR");
            }
        }
        
        // ========================================
        // 4. CREAR ESTRUCTURA COMPLETA DE DATOS
        // ========================================
        $complete_data = [
            'order_id' => $order_id,
            'timestamp' => date('Y-m-d H:i:s'),
            'form_data' => $form_data,
            'attachments' => $attachments,
            'status' => 'captured',
            'webhook_sent' => false
        ];
        
        // ========================================
        // 5. GUARDAR EN SISTEMA DE ARCHIVOS
        // ========================================
        $orders_dir = '/tmp/tbv2_orders';
        if (!file_exists($orders_dir)) {
            mkdir($orders_dir, 0755, true);
        }
        
        $order_file = $orders_dir . '/' . $order_id . '_complete.json';
        $json_result = file_put_contents($order_file, json_encode($complete_data, JSON_PRETTY_PRINT));
        
        if ($json_result === false) {
            tbv2_capture_log("ERROR: No se pudo guardar datos en $order_file", "ERROR");
            return false;
        }
        
        tbv2_capture_log("Datos completos guardados en: $order_file", "SUCCESS");
        tbv2_capture_log("Archivos adjuntos: " . count($attachments), "INFO");
        tbv2_capture_log("=== FIN CAPTURA COMPLETA ===", "SUCCESS");
        
        // Retornar order_id para uso en Redsys
        return $order_id;
        
    } catch (Exception $e) {
        tbv2_capture_log("EXCEPCIÓN en captura completa: " . $e->getMessage(), "ERROR");
        return false;
    }
}

/**
 * RECUPERAR DATOS COMPLETOS CAPTURADOS
 */
function tbv2_get_captured_data_complete($order_id) {
    try {
        $order_file = '/tmp/tbv2_orders/' . $order_id . '_complete.json';
        
        if (!file_exists($order_file)) {
            tbv2_capture_log("Archivo no encontrado: $order_file", "ERROR");
            return null;
        }
        
        $file_content = file_get_contents($order_file);
        if ($file_content === false) {
            tbv2_capture_log("Error leyendo archivo: $order_file", "ERROR");
            return null;
        }
        
        $data = json_decode($file_content, true);
        if ($data === null) {
            tbv2_capture_log("Error decodificando JSON: $order_file", "ERROR");
            return null;
        }
        
        tbv2_capture_log("Datos recuperados correctamente para: $order_id", "SUCCESS");
        return $data;
        
    } catch (Exception $e) {
        tbv2_capture_log("EXCEPCIÓN recuperando datos: " . $e->getMessage(), "ERROR");
        return null;
    }
}

/**
 * HANDLER AJAX PARA CAPTURA COMPLETA
 */
function tbv2_ajax_capture_complete() {
    // Verificar nonce de seguridad
    if (!wp_verify_nonce($_POST['tbv2_nonce'] ?? '', 'tbv2_capture_action')) {
        tbv2_capture_log("Nonce inválido en captura completa", "ERROR");
        wp_die('Error de seguridad');
    }
    
    $order_id = tbv2_capture_form_data_complete();
    
    if ($order_id) {
        wp_send_json_success([
            'order_id' => $order_id,
            'message' => 'Datos capturados completamente'
        ]);
    } else {
        wp_send_json_error([
            'message' => 'Error en captura de datos'
        ]);
    }
}

// Registrar handler AJAX
add_action('wp_ajax_tbv2_capture_complete', 'tbv2_ajax_capture_complete');
add_action('wp_ajax_nopriv_tbv2_capture_complete', 'tbv2_ajax_capture_complete');

tbv2_capture_log("Sistema de captura completo inicializado", "SUCCESS");
?>