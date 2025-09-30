<?php
// FUNCIÓN SIMPLIFICADA TEMPORAL - Solo emails y webhook
add_action('wp_ajax_submit_moto_form_tpm_SIMPLE', 'tpm_submit_form_simple');
add_action('wp_ajax_nopriv_submit_moto_form_tpm_SIMPLE', 'tpm_submit_form_simple');
function tpm_submit_form_simple() {
    // Recoger datos básicos
    $customer_name = sanitize_text_field($_POST['customer_name']);
    $customer_dni = sanitize_text_field($_POST['customer_dni']);
    $customer_email = sanitize_email($_POST['customer_email']);
    $customer_phone = sanitize_text_field($_POST['customer_phone']);
    $vehicle_type = sanitize_text_field($_POST['vehicle_type']);
    $manufacturer = sanitize_text_field($_POST['manufacturer']);
    $model = sanitize_text_field($_POST['model']);
    $purchase_price = floatval($_POST['purchase_price']);
    $region = sanitize_text_field($_POST['region']);
    $final_amount = floatval($_POST['final_amount']);
    $current_transfer_tax = floatval($_POST['current_transfer_tax']);
    $tasas_hidden = floatval($_POST['tasas_hidden']);
    $iva_hidden = floatval($_POST['iva_hidden']);
    $honorarios_hidden = floatval($_POST['honorarios_hidden']);
    
    // Generar ID de trámite
    $prefix = 'TMA-TRANS';
    $counter_option = 'tma_trans_counter';
    $current_cnt = get_option($counter_option, 0);
    $current_cnt++;
    update_option($counter_option, $current_cnt);
    $date_part = date('Ymd');
    $secuencial = str_pad($current_cnt, 6, '0', STR_PAD_LEFT);
    $tramite_id = $prefix . '-' . $date_part . '-' . $secuencial;
    
    // Email al cliente
    $headers = ['Content-Type: text/html; charset=UTF-8', 'From: info@tramitfy.es'];
    $subject_customer = 'Confirmación de pago recibido - Tramitfy';
    $message_customer = "<html><body><p>Hola <strong>$customer_name</strong>,</p><p>Hemos recibido tu pago. Número de trámite: <strong>$tramite_id</strong></p><p>Gracias por confiar en Tramitfy.</p></body></html>";
    wp_mail($customer_email, $subject_customer, $message_customer, $headers);
    
    // Email al admin
    $admin_email = 'ipmgroup24@gmail.com';
    $subject_admin = "Nuevo trámite - $tramite_id";
    $message_admin = "<html><body><h2>Nuevo Trámite</h2><p>ID: $tramite_id</p><p>Cliente: $customer_name ($customer_email)</p><p>Vehículo: $manufacturer $model</p><p>Total: " . number_format($final_amount, 2) . " €</p></body></html>";
    wp_mail($admin_email, $subject_admin, $message_admin, $headers);
    
    // Llamar al webhook de la API
    $webhook_url = 'https://46-202-128-35.sslip.io/api/herramientas/motos/webhook';
    $webhook_data = array(
        'tramiteId' => $tramite_id,
        'tramiteType' => 'Transferencia Moto',
        'customerName' => $customer_name,
        'customerDni' => $customer_dni,
        'customerEmail' => $customer_email,
        'customerPhone' => $customer_phone,
        'vehicleType' => $vehicle_type,
        'manufacturer' => $manufacturer,
        'model' => $model,
        'purchasePrice' => $purchase_price,
        'region' => $region,
        'finalAmount' => $final_amount,
        'transferTax' => $current_transfer_tax,
        'tasas' => $tasas_hidden,
        'iva' => $iva_hidden,
        'honorarios' => $honorarios_hidden,
        'status' => 'pending'
    );
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $webhook_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($webhook_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_exec($ch);
    curl_close($ch);
    
    // Responder con éxito
    wp_send_json_success('Formulario procesado correctamente.');
    wp_die();
}
