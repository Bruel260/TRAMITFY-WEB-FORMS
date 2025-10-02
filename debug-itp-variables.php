<?php
/**
 * DEBUG ESPECÍFICO PARA VARIABLES ITP
 * Sistema centralizado de debug para detectar problemas de transmisión
 */

// Función de debug específica para ITP
function debug_itp_variables($context = 'UNKNOWN') {
    $timestamp = date('Y-m-d H:i:s');
    $debug_file = '/tmp/tramitfy-itp-debug.log';
    
    $debug_content = "\n=== DEBUG ITP VARIABLES - $context ===\n";
    $debug_content .= "Timestamp: $timestamp\n";
    $debug_content .= "Context: $context\n\n";
    
    // 1. VARIABLES RAW $_POST
    $debug_content .= "🔍 RAW \$_POST ITP VARIABLES:\n";
    $debug_content .= "   itp_paid: [" . ($_POST['itp_paid'] ?? 'NOT_SET') . "]\n";
    $debug_content .= "   itp_management_option: [" . ($_POST['itp_management_option'] ?? 'NOT_SET') . "]\n";
    $debug_content .= "   itp_payment_method: [" . ($_POST['itp_payment_method'] ?? 'NOT_SET') . "]\n";
    $debug_content .= "   itp_amount: [" . ($_POST['itp_amount'] ?? 'NOT_SET') . "]\n";
    $debug_content .= "   itp_commission: [" . ($_POST['itp_commission'] ?? 'NOT_SET') . "]\n";
    $debug_content .= "   itp_total_amount: [" . ($_POST['itp_total_amount'] ?? 'NOT_SET') . "]\n\n";
    
    // 2. VARIABLES ECONÓMICAS
    $debug_content .= "💰 VARIABLES ECONÓMICAS:\n";
    $debug_content .= "   final_amount: [" . ($_POST['final_amount'] ?? 'NOT_SET') . "]\n";
    $debug_content .= "   current_transfer_tax: [" . ($_POST['current_transfer_tax'] ?? 'NOT_SET') . "]\n";
    $debug_content .= "   current_extra_fee: [" . ($_POST['current_extra_fee'] ?? 'NOT_SET') . "]\n";
    $debug_content .= "   tasas_hidden: [" . ($_POST['tasas_hidden'] ?? 'NOT_SET') . "]\n";
    $debug_content .= "   iva_hidden: [" . ($_POST['iva_hidden'] ?? 'NOT_SET') . "]\n";
    $debug_content .= "   honorarios_hidden: [" . ($_POST['honorarios_hidden'] ?? 'NOT_SET') . "]\n\n";
    
    // 3. TODAS LAS KEYS DE $_POST (para detectar nombres incorrectos)
    $debug_content .= "🗂️ TODAS LAS KEYS DE \$_POST:\n";
    if (!empty($_POST)) {
        foreach (array_keys($_POST) as $key) {
            if (strpos($key, 'itp') !== false || strpos($key, 'transfer') !== false || strpos($key, 'amount') !== false) {
                $debug_content .= "   ★ $key: [" . $_POST[$key] . "]\n";
            } else {
                $debug_content .= "     $key\n";
            }
        }
    } else {
        $debug_content .= "   (EMPTY \$_POST)\n";
    }
    
    $debug_content .= "\n" . str_repeat("=", 60) . "\n";
    
    // Escribir al archivo de log
    file_put_contents($debug_file, $debug_content, FILE_APPEND);
    
    // También escribir al error_log de WordPress
    error_log("🔍 ITP DEBUG - Ver detalles en: $debug_file");
}

// Función para debug de form_data antes de enviar al webhook
function debug_webhook_data($form_data, $context = 'WEBHOOK') {
    $timestamp = date('Y-m-d H:i:s');
    $debug_file = '/tmp/tramitfy-webhook-debug.log';
    
    $debug_content = "\n=== DEBUG WEBHOOK DATA - $context ===\n";
    $debug_content .= "Timestamp: $timestamp\n\n";
    
    $debug_content .= "🚀 DATOS ENVIADOS AL WEBHOOK:\n";
    foreach ($form_data as $key => $value) {
        if (is_object($value) && get_class($value) === 'CURLFile') {
            $debug_content .= "   $key: [FILE: " . $value->getFilename() . "]\n";
        } else {
            $debug_content .= "   $key: [$value]\n";
        }
    }
    
    $debug_content .= "\n" . str_repeat("=", 60) . "\n";
    
    file_put_contents($debug_file, $debug_content, FILE_APPEND);
    error_log("🚀 WEBHOOK DEBUG - Ver detalles en: $debug_file");
}

// Función para debug de condiciones de email
function debug_email_conditions($itp_gestion, $itp_metodo_pago, $itp_amount, $context = 'EMAIL') {
    $timestamp = date('Y-m-d H:i:s');
    $debug_file = '/tmp/tramitfy-email-debug.log';
    
    $debug_content = "\n=== DEBUG EMAIL CONDITIONS - $context ===\n";
    $debug_content .= "Timestamp: $timestamp\n\n";
    
    $debug_content .= "📧 CONDICIONES PARA EMAIL BANCARIO:\n";
    $debug_content .= "   itp_gestion: [$itp_gestion]\n";
    $debug_content .= "   itp_metodo_pago: [$itp_metodo_pago]\n";
    $debug_content .= "   itp_amount: [$itp_amount]\n\n";
    
    // Evaluar condiciones paso a paso
    $condition1 = ($itp_gestion === 'gestionan-ustedes' && $itp_metodo_pago === 'transferencia');
    $condition2 = ($itp_amount > 0 && $itp_metodo_pago === 'transferencia');
    $final_condition = $condition1 || $condition2;
    
    $debug_content .= "🔍 EVALUACIÓN DE CONDICIONES:\n";
    $debug_content .= "   Condición 1 (gestion=gestionan-ustedes Y metodo=transferencia): " . ($condition1 ? 'TRUE' : 'FALSE') . "\n";
    $debug_content .= "   Condición 2 (itp_amount>0 Y metodo=transferencia): " . ($condition2 ? 'TRUE' : 'FALSE') . "\n";
    $debug_content .= "   RESULTADO FINAL (OR): " . ($final_condition ? 'TRUE - MOSTRAR DATOS BANCARIOS' : 'FALSE - NO MOSTRAR') . "\n";
    
    $debug_content .= "\n" . str_repeat("=", 60) . "\n";
    
    file_put_contents($debug_file, $debug_content, FILE_APPEND);
    error_log("📧 EMAIL DEBUG - Ver detalles en: $debug_file");
    
    return $final_condition;
}

/**
 * INSTRUCCIONES DE USO:
 * 
 * 1. En tpm_submit_form(), después de procesar $_POST:
 *    debug_itp_variables('TPM_SUBMIT_FORM');
 * 
 * 2. Antes de enviar al webhook:
 *    debug_webhook_data($form_data, 'BEFORE_WEBHOOK');
 * 
 * 3. Para debug de email:
 *    $show_bank_data = debug_email_conditions($itp_gestion, $itp_metodo_pago, $itp_amount, 'EMAIL_CLIENT');
 * 
 * 4. Ver logs:
 *    tail -f /tmp/tramitfy-itp-debug.log
 *    tail -f /tmp/tramitfy-webhook-debug.log  
 *    tail -f /tmp/tramitfy-email-debug.log
 */
?>