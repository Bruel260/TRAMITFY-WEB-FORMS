<?php
// Asegurarse de que el archivo no sea accedido directamente
defined('ABSPATH') || exit;

/**
 * Función para generar factura como PDF
 */
function generate_invoice_pdf($customer_name, $customer_dni, $customer_email, $customer_phone, $deregistration_type, $workshop_data, $coupon_used, $upload_dir, $billing_address = '', $billing_city = '', $billing_postal_code = '', $billing_province = '') {
    // Cálculo de precios - basado en el mismo cálculo del JavaScript
    $base_price = 95.00;
    $taxes = 21.15;
    $fees = 60.00;
    $vat_rate = 0.21;
    
    // Aplicar descuento si hay cupón
    $discount_percent = 0;
    $discount_amount = 0;
    $valid_coupons = array(
        'DESCUENTO10' => 10,
        'DESCUENTO20' => 20,
        'VERANO15'    => 15,
        'BLACK50'     => 50,
    );
    
    if (!empty($coupon_used)) {
        $coupon_upper = strtoupper($coupon_used);
        if (isset($valid_coupons[$coupon_upper])) {
            $discount_percent = $valid_coupons[$coupon_upper];
            $discount_amount = ($base_price * $discount_percent) / 100;
        }
    }
    
    $total_with_discount = $base_price - $discount_amount;
    
    // Calcular honorarios e IVA
    $price_before_vat = ($total_with_discount - $taxes) / (1 + $vat_rate);
    $vat_amount = $price_before_vat * $vat_rate;
    
    // Crear una nueva instancia de FPDF para la factura
    require_once get_template_directory() . '/vendor/fpdf/fpdf.php';
    $pdf = new FPDF();
    $pdf->AddPage();
    
    // Definimos colores corporativos
    $primary_color = array(1, 109, 134); // #016d86
    $secondary_color = array(40, 167, 69); // #28a745
    $text_color = array(51, 51, 51); // #333333
    
    // Configurar fuentes
    $pdf->SetFont('Arial', 'B', 18);
    $pdf->SetTextColor($primary_color[0], $primary_color[1], $primary_color[2]);
    
    // Logo y encabezado
    // Si hay un logo disponible, descomentar y usar la ruta correcta
    // $pdf->Image('ruta_al_logo.png', 10, 10, 40);
    
    // Título de la factura
    $pdf->Cell(0, 15, utf8_decode('FACTURA'), 0, 1, 'R');
    
    // Número de factura y fecha
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 8, utf8_decode('Nº Factura: INV-'.date('Ymd').'-'.time()), 0, 1, 'R');
    $pdf->Cell(0, 8, 'Fecha: '.date('d/m/Y'), 0, 1, 'R');
    $pdf->Ln(10);
    
    // Datos de la empresa
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->SetTextColor($text_color[0], $text_color[1], $text_color[2]);
    $pdf->Cell(0, 8, utf8_decode('DATOS DE LA EMPRESA:'), 0, 1, 'L');
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 6, 'Tramitfy S.L.', 0, 1, 'L');
    $pdf->Cell(0, 6, 'CIF: B55388557', 0, 1, 'L');
    $pdf->Cell(0, 6, utf8_decode('Dirección: Paseo Castellana 194 puerta B, Madrid, España'), 0, 1, 'L');
    $pdf->Cell(0, 6, utf8_decode('Teléfono: +34 689 170 273'), 0, 1, 'L');
    $pdf->Cell(0, 6, 'Email: info@tramitfy.es', 0, 1, 'L');
    $pdf->Cell(0, 6, 'Web: www.tramitfy.es', 0, 1, 'L');
    $pdf->Ln(10);
    
    // Datos del cliente
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, 'DATOS DEL CLIENTE:', 0, 1, 'L');
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 6, 'Nombre: '.$customer_name, 0, 1, 'L');
    $pdf->Cell(0, 6, 'DNI: '.$customer_dni, 0, 1, 'L');
    $pdf->Cell(0, 6, utf8_decode('Teléfono: '.$customer_phone), 0, 1, 'L');
    $pdf->Cell(0, 6, 'Email: '.$customer_email, 0, 1, 'L');
    
    // Dirección de facturación
    if (!empty($billing_address)) {
        $pdf->Cell(0, 6, utf8_decode('Dirección: '.$billing_address), 0, 1, 'L');
        if (!empty($billing_postal_code) || !empty($billing_city)) {
            $location = '';
            if (!empty($billing_postal_code)) {
                $location .= $billing_postal_code;
            }
            if (!empty($billing_city)) {
                $location .= (!empty($location) ? ' ' : '') . $billing_city;
            }
            $pdf->Cell(0, 6, utf8_decode('Población: '.$location), 0, 1, 'L');
        }
        if (!empty($billing_province)) {
            $pdf->Cell(0, 6, utf8_decode('Provincia: '.$billing_province), 0, 1, 'L');
        }
    }
    $pdf->Ln(10);
    
    // Detalles del servicio
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, 'DETALLES DEL SERVICIO:', 0, 1, 'L');
    $pdf->Ln(2);
    
    // Crear tabla
    $pdf->SetFillColor(240, 240, 240);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(100, 8, utf8_decode('Descripción'), 1, 0, 'L', true);
    $pdf->Cell(40, 8, 'Cantidad', 1, 0, 'C', true);
    $pdf->Cell(50, 8, 'Precio', 1, 1, 'R', true);
    
    // Tipo de servicio
    $deregistration_type_text = ($deregistration_type === 'siniestro') ? 'Baja definitiva por siniestro' : 'Baja definitiva por exportación';
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(100, 8, utf8_decode($deregistration_type_text), 1, 0, 'L');
    $pdf->Cell(40, 8, '1', 1, 0, 'C');
    $pdf->Cell(50, 8, number_format($base_price, 2) . ' EUR', 1, 1, 'R');
    
    // Si hay descuento, mostrar línea de descuento
    if ($discount_percent > 0) {
        $pdf->SetTextColor(220, 53, 69); // Color rojo para el descuento #dc3545
        $pdf->Cell(100, 8, utf8_decode('Descuento cupón: '.$coupon_used.' ('.$discount_percent.'%)'), 1, 0, 'L');
        $pdf->Cell(40, 8, '1', 1, 0, 'C');
        $pdf->Cell(50, 8, '-'.number_format($discount_amount, 2) . ' EUR', 1, 1, 'R');
        $pdf->SetTextColor($text_color[0], $text_color[1], $text_color[2]);
    }
    
    // Desglose
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(100, 8, 'Tasas', 1, 0, 'L');
    $pdf->Cell(40, 8, '1', 1, 0, 'C');
    $pdf->Cell(50, 8, number_format($taxes, 2) . ' EUR', 1, 1, 'R');
    
    $pdf->Cell(100, 8, 'Honorarios', 1, 0, 'L');
    $pdf->Cell(40, 8, '1', 1, 0, 'C');
    $pdf->Cell(50, 8, number_format($price_before_vat - $taxes, 2) . ' EUR', 1, 1, 'R');
    
    // Subtotal, IVA y Total
    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'B', 10);
    
    $pdf->Cell(140, 8, 'Subtotal', 0, 0, 'R');
    $pdf->Cell(50, 8, number_format($price_before_vat, 2) . ' EUR', 0, 1, 'R');
    
    $pdf->Cell(140, 8, 'IVA (21%)', 0, 0, 'R');
    $pdf->Cell(50, 8, number_format($vat_amount, 2) . ' EUR', 0, 1, 'R');
    
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->SetTextColor($primary_color[0], $primary_color[1], $primary_color[2]);
    $pdf->Cell(140, 10, 'TOTAL', 0, 0, 'R');
    $pdf->Cell(50, 10, number_format($total_with_discount, 2) . ' EUR', 0, 1, 'R');
    
    // Pie de factura
    $pdf->SetTextColor($text_color[0], $text_color[1], $text_color[2]);
    $pdf->SetFont('Arial', '', 9);
    $pdf->Ln(15);
    $pdf->Cell(0, 6, utf8_decode('Método de pago: Tarjeta de crédito/débito'), 0, 1, 'L');
    $pdf->Cell(0, 6, utf8_decode('Esta factura sirve como comprobante de pago'), 0, 1, 'L');
    
    // Nota legal
    $pdf->Ln(15);
    $pdf->SetFont('Arial', 'I', 8);
    $pdf->MultiCell(0, 5, utf8_decode('Esta factura ha sido generada electrónicamente y es válida sin firma ni sello. Según el Real Decreto 1619/2012, de 30 de noviembre, por el que se aprueba el Reglamento por el que se regulan las obligaciones de facturación.'), 0, 'L');
    
    // Guardar el PDF
    $invoice_pdf_name = 'factura_' . time() . '.pdf';
    $invoice_pdf_path = $upload_dir['path'] . '/' . $invoice_pdf_name;
    $pdf->Output('F', $invoice_pdf_path);
    
    return $invoice_pdf_path;
}

// =====================================================
// CONFIGURACIÓN REDSYS - BAJA EMBARCACIÓN
// =====================================================
if (!defined('BAJA_REDSYS_MODE')) define('BAJA_REDSYS_MODE', 'live');
if (!defined('BAJA_REDSYS_MERCHANT_CODE')) define('BAJA_REDSYS_MERCHANT_CODE', '363391103');
if (!defined('BAJA_REDSYS_TERMINAL')) define('BAJA_REDSYS_TERMINAL', '1');
if (!defined('BAJA_REDSYS_CURRENCY')) define('BAJA_REDSYS_CURRENCY', '978');

if (!defined('BAJA_REDSYS_SECRET_KEY')) {
    if (BAJA_REDSYS_MODE === 'test') {
        define('BAJA_REDSYS_SECRET_KEY', 'sq7HjrUOBfKmC576ILgskD5srU870gJ7');
    } else {
        define('BAJA_REDSYS_SECRET_KEY', 'ERDGGMADKbhFIngyRLnW6KrxEuKnjq9p');
    }
}

if (!defined('BAJA_REDSYS_SIGNATURE_VERSION')) define('BAJA_REDSYS_SIGNATURE_VERSION', 'HMAC_SHA256_V1');
if (!defined('BAJA_REDSYS_URL_TEST')) define('BAJA_REDSYS_URL_TEST', 'https://sis-t.redsys.es:25443/sis/realizarPago');
if (!defined('BAJA_REDSYS_URL_LIVE')) define('BAJA_REDSYS_URL_LIVE', 'https://sis.redsys.es/sis/realizarPago');
if (!defined('BAJA_REDSYS_URL_OK')) define('BAJA_REDSYS_URL_OK', 'https://tramitfy.es/pago-completado-baja/');
if (!defined('BAJA_REDSYS_URL_KO')) define('BAJA_REDSYS_URL_KO', 'https://tramitfy.es/baja-embarcacion/');
if (!defined('BAJA_REDSYS_URL_NOTIFICATION')) define('BAJA_REDSYS_URL_NOTIFICATION', 'https://tramitfy.org/api/temporal/confirm');
if (!defined('BAJA_SERVICE_PRICE')) define('BAJA_SERVICE_PRICE', 95.00);

function baja_redsys_generate_signature($data) {
    $password_decoded = base64_decode(BAJA_REDSYS_SECRET_KEY);
    $order_id = $data['Ds_Order'] ?? $data['Ds_Merchant_Order'] ?? '';
    $l = ceil(strlen($order_id) / 8) * 8;
    $padded_order_id = $order_id . str_repeat("\0", $l - strlen($order_id));
    $encryption_key = substr(
        openssl_encrypt($padded_order_id, 'des-ede3-cbc', $password_decoded, OPENSSL_RAW_DATA, "\0\0\0\0\0\0\0\0"),
        0, $l
    );
    $string_to_sign = base64_encode(json_encode($data));
    $signature = hash_hmac('sha256', $string_to_sign, $encryption_key, true);
    return base64_encode($signature);
}

function baja_redsys_create_payment_form($order_data) {
    $redsys_url = (BAJA_REDSYS_MODE === 'test') ? BAJA_REDSYS_URL_TEST : BAJA_REDSYS_URL_LIVE;
    $params = [
        'Ds_Merchant_MerchantCode' => BAJA_REDSYS_MERCHANT_CODE,
        'Ds_Merchant_Terminal' => BAJA_REDSYS_TERMINAL,
        'Ds_Merchant_Order' => $order_data['order_id'],
        'Ds_Merchant_Amount' => $order_data['amount_cents'],
        'Ds_Merchant_Currency' => BAJA_REDSYS_CURRENCY,
        'Ds_Merchant_TransactionType' => '0',
        'Ds_Merchant_MerchantURL' => BAJA_REDSYS_URL_NOTIFICATION,
        'Ds_Merchant_UrlOK' => BAJA_REDSYS_URL_OK,
        'Ds_Merchant_UrlKO' => BAJA_REDSYS_URL_KO,
        'Ds_Merchant_MerchantName' => 'Tramitfy',
        'Ds_Merchant_ProductDescription' => 'Baja Embarcacion',
        'Ds_Merchant_ConsumerLanguage' => '001'
    ];
    $signature = baja_redsys_generate_signature($params);
    $merchant_parameters = base64_encode(json_encode($params));
    return [
        'url' => $redsys_url,
        'Ds_MerchantParameters' => $merchant_parameters,
        'Ds_SignatureVersion' => BAJA_REDSYS_SIGNATURE_VERSION,
        'Ds_Signature' => $signature
    ];
}

function baja_create_redsys_payment() {
    try {
        $order_id = !empty($_POST['orderId']) ? $_POST['orderId'] : str_pad(time(), 12, '0', STR_PAD_LEFT);
        if (strlen($order_id) > 12) { $order_id = substr($order_id, -12); }
        $amount = floatval($_POST['amount'] ?? BAJA_SERVICE_PRICE);
        // Aplicar descuento cupón desde temporal (fuente de verdad)
        $jsAppliedDiscount = floatval($_POST['couponDiscount'] ?? 0);
        $rawAmount = $amount + $jsAppliedDiscount;
        $apiCouponUrl = 'https://tramitfy.org/api/temporal/coupon-for-order?orderId=' . urlencode($order_id);
        $apiResponse = @file_get_contents($apiCouponUrl);
        if ($apiResponse) {
            $couponData = json_decode($apiResponse, true);
            $serverDiscount = floatval($couponData['couponDiscount'] ?? 0);
            if ($serverDiscount > 0) {
                $amount = max(0, $rawAmount - $serverDiscount);
                error_log("BAJA REDSYS - Cupón: " . $couponData['couponCode'] . " raw=" . $rawAmount . " -" . $serverDiscount . "€ → final=" . $amount . "€");
            } else {
                $amount = $rawAmount;
            }
        }
        $amount_cents = strval(intval($amount * 100));

        $payment_data = baja_redsys_create_payment_form([
            'order_id' => $order_id,
            'amount_cents' => $amount_cents
        ]);

        wp_send_json_success([
            'orderId' => $order_id,
            'paymentData' => $payment_data
        ]);

    } catch (Exception $e) {
        wp_send_json_error(['message' => $e->getMessage()]);
    }
}

/**
 * Función principal para generar y mostrar el formulario en el frontend
 */
function boat_deregistration_form_shortcode() {
    // Encolar los scripts y estilos necesarios
    wp_enqueue_style('boat-deregistration-form-style', get_template_directory_uri() . '/style.css', array(), filemtime(get_template_directory() . '/style.css'));
    wp_enqueue_script('signature-pad', 'https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js', array(), null, false);

    // Iniciar el buffering de salida
    ob_start();
    ?>

    <!-- Estilos personalizados para el formulario -->
    <style>
        /* Estilos generales para el formulario */
        #boat-deregistration-form {
            max-width: 1000px;
            margin: 40px auto;
            padding: 30px;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            background-color: #ffffff;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        #boat-deregistration-form label {
            font-weight: normal;
            display: block;
            margin-top: 15px;
            margin-bottom: 5px;
            color: #555555;
        }

        #boat-deregistration-form input[type="text"],
        #boat-deregistration-form input[type="tel"],
        #boat-deregistration-form input[type="email"],
        #boat-deregistration-form input[type="file"],
        #boat-deregistration-form select {
            width: 100%;
            padding: 12px;
            margin-top: 0px;
            border-radius: 5px;
            border: 1px solid #cccccc;
            font-size: 16px;
            background-color: #f9f9f9;
        }

        #boat-deregistration-form .button {
            background-color: #28a745;
            color: #ffffff;
            padding: 12px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 18px;
            transition: background-color 0.3s ease;
            margin-top: 20px;
        }

        #boat-deregistration-form .button:hover {
            background-color: #218838;
        }

        #boat-deregistration-form .hidden {
            display: none;
        }

        /* Estilos para el menú de navegación */
        #form-navigation {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            margin-bottom: 30px;
            align-items: center;
            background-color: #f1f1f1;
            padding: 15px;
            border-radius: 8px;
        }

        #form-navigation a {
            color: #016d86;
            text-decoration: none;
            font-weight: bold;
            position: relative;
            padding: 8px 15px;
            transition: color 0.3s ease;
        }

        #form-navigation a.active {
            color: #016d86;
            text-decoration: underline;
        }

        #form-navigation a:not(:last-child)::after {
            content: '➔';
            position: absolute;
            right: -20px;
            font-size: 16px;
            color: #016d86;
        }

        #form-navigation a:hover {
            color: #016d86;
        }

        .button-container {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            margin-top: 30px;
        }

        .button-container .button {
            flex: 1 1 auto;
            margin: 5px;
        }

        /* Estilos para la sección de documentos */
        .upload-section {
            margin-top: 20px;
        }

        .upload-item {
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
        }

        .upload-item label {
            flex: 0 0 30%;
            font-weight: normal;
            color: #555555;
            margin-bottom: 5px;
        }

        .upload-item input[type="file"] {
            flex: 1;
            margin-bottom: 5px;
        }

        .upload-item .view-example {
            flex: 0 0 auto;
            margin-left: 10px;
            background-color: transparent;
            color: #007bff;
            text-decoration: underline;
            cursor: pointer;
            margin-bottom: 5px;
        }

        .upload-item .view-example:hover {
            color: #0056b3;
        }

        /* Popup para ejemplos de documentos */
        #document-popup {
            display: none;
            position: fixed;
            z-index: 1001;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.5);
        }

        #document-popup .popup-content {
            background-color: #fff;
            margin: 5% auto;
            padding: 20px;
            width: 90%;
            max-width: 600px;
            border-radius: 8px;
            position: relative;
        }

        #document-popup .close-popup {
            color: #aaa;
            position: absolute;
            top: 10px;
            right: 25px;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        #document-popup .close-popup:hover {
            color: black;
        }

        #document-popup h3 {
            margin-top: 0;
            color: #333333;
        }

        #document-popup img {
            width: 100%;
            border-radius: 8px;
        }

        /* Estilos para la firma */
        #signature-container {
            margin-top: 20px;
            text-align: center;
            width: 100%;
        }

        #signature-pad {
            border: 1px solid #ccc;
            width: 100%;
            max-width: 600px;
            height: 200px;
            box-sizing: border-box;
        }

        /* Mejora de la firma */
        #signature-instructions {
            font-size: 14px;
            color: #555;
            margin-bottom: 10px;
            text-align: center;
        }

        /* Estilos para el elemento de pago */
        #payment-element {
            margin-top: 15px;
            margin-bottom: 15px;
            background-color: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
        }

        /* Estilos para el botón de pago */
        #submit {
            background-color: #016d86;
            color: #ffffff;
            padding: 15px 25px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 20px;
            transition: background-color 0.3s ease;
            width: 100%;
            max-width: 300px;
            margin: 20px auto 0;
            display: block;
        }

        #submit:hover {
            background-color: #014f63;
        }

        /* Mensajes de éxito y error */
        #payment-message {
            margin-top: 15px;
            font-size: 16px;
            text-align: center;
        }

        #payment-message.success {
            color: #28a745;
        }

        #payment-message.error {
            color: #dc3545;
        }

        /* Estilos para mensajes de error de pago */
        .StripeElement--invalid {
            border-color: #dc3545;
        }

        /* Personalización elementos de pago */
        .StripeElement {
            background-color: #ffffff;
            padding: 12px;
            border: 1px solid #cccccc;
            border-radius: 4px;
            margin-bottom: 10px;
            width: 100%;
        }

        /* Mensajes de éxito y error */
        #card-errors {
            color: #dc3545;
            margin-top: 10px;
        }

        #payment-message {
            margin-top: 10px;
            font-size: 16px;
        }

        #payment-message.success {
            color: #28a745;
        }

        #payment-message.error {
            color: #dc3545;
        }

        /* Overlay de carga */
        #loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255,255,255,0.9);
            display: none;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        #loading-overlay .spinner {
            border: 8px solid #f3f3f3;
            border-top: 8px solid #007bff;
            border-radius: 50%;
            width: 70px;
            height: 70px;
            animation: spin 1.5s linear infinite;
        }

        #loading-overlay p {
            margin-top: 25px;
            font-size: 20px;
            color: #007bff;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Estilos adicionales para los botones */
        .button[disabled],
        .button:disabled {
            background-color: #cccccc;
            cursor: not-allowed;
        }

        /* Estilos para el checkbox de términos y condiciones */
        .terms-container {
            margin-top: 25px;
            text-align: left;
        }

        .terms-container label {
            font-weight: normal;
            color: #555555;
        }

        .terms-container a {
            color: #007bff;
            text-decoration: none;
        }

        .terms-container a:hover {
            text-decoration: underline;
        }

        /* Estilos para el recuadro de precio */
        .price-details {
            margin-top: 20px;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
            background-color: #fafafa;
        }

        .price-details p {
            font-size: 18px;
            font-weight: bold;
            margin: 0;
            color: #333333;
        }

        .price-details ul {
            list-style-type: none;
            padding: 0;
            margin: 15px 0;
        }

        .price-details ul li {
            margin-bottom: 8px;
            color: #555555;
        }

        .error-message {
            color: #dc3545;
            margin-bottom: 20px;
            font-size: 16px;
            font-weight: bold;
        }

        .field-error {
            border-color: #dc3545 !important;
        }

        /* [NUEVO - CUPÓN] Clases para el campo del cupón */
        .coupon-valid {
            background-color: #d4edda !important; /* verde claro */
            border-color: #28a745 !important;
        }
        .coupon-error {
            background-color: #f8d7da !important; /* rojo claro */
            border-color: #dc3545 !important;
        }
        .coupon-loading {
            background-color: #fff3cd !important; /* amarillo claro */
            border-color: #ffeeba !important;
        }
        /* [/NUEVO - CUPÓN] */

        /* Responsividad */
        @media (max-width: 768px) {
            #form-navigation {
                flex-direction: column;
                align-items: flex-start;
            }

            #form-navigation a {
                margin-bottom: 10px;
            }

            .button-container {
                flex-direction: column;
                align-items: stretch;
            }

            .button-container .button {
                width: 100%;
                margin: 5px 0;
            }

            #signature-pad {
                height: 150px;
            }

            .upload-item {
                flex-direction: column;
                align-items: flex-start;
            }

            .upload-item label, .upload-item input[type="file"], .upload-item .view-example {
                flex: 1 1 100%;
                margin-bottom: 5px;
            }

            .upload-item .view-example {
                margin-left: 0;
            }
        }

        @media (max-width: 480px) {
            #boat-deregistration-form {
                padding: 20px;
            }

            #form-navigation {
                padding: 10px;
            }

            .button {
                font-size: 16px;
                padding: 10px;
            }

            #signature-pad {
                height: 120px;
            }
        }
    </style>

    <!-- Formulario principal -->
    <form id="boat-deregistration-form" action="" method="POST" enctype="multipart/form-data">
        <!-- Mensajes de error -->
        <div id="error-messages"></div>

        <!-- Navegación del formulario -->
        <div id="form-navigation">
            <a href="#" class="nav-link" data-page-id="page-personal-info">Datos</a>
            <a href="#" class="nav-link" data-page-id="page-documents">Documentación</a>
            <a href="#" class="nav-link" data-page-id="page-payment">Pago</a>
        </div>

        <!-- Overlay de carga -->
        <div id="loading-overlay">
            <div class="spinner"></div>
            <p>Procesando, por favor espera...</p>
        </div>

        <!-- Página de Datos Personales -->
        <div id="page-personal-info" class="form-page">
            <label for="customer_name">Nombre y Apellidos:</label>
            <input type="text" id="customer_name" name="customer_name" placeholder="Ingresa tu nombre y apellidos" required />

            <label for="customer_dni">DNI:</label>
            <input type="text" id="customer_dni" name="customer_dni" placeholder="Ingresa tu DNI" required />

            <label for="customer_email">Correo Electrónico:</label>
            <input type="email" id="customer_email" name="customer_email" placeholder="Ingresa tu correo electrónico" required />

            <label for="customer_phone">Teléfono:</label>
            <input type="tel" id="customer_phone" name="customer_phone" placeholder="Ingresa tu teléfono" required />

            <!-- Selector de tipo de baja -->
            <label for="deregistration_type">Tipo de Baja:</label>
            <select id="deregistration_type" name="deregistration_type" required>
                <option value="">Seleccione una opción</option>
                <option value="siniestro">Baja definitiva por siniestro</option>
                <option value="exportacion">Baja definitiva por exportación</option>
            </select>

            <!-- Campo para Datos del Taller (solo si es siniestro) -->
            <div id="workshop-data-section" style="display: none;">
                <label for="workshop_data">Nombre del Taller o enlace de Google:</label>
                <input type="text" id="workshop_data" name="workshop_data" placeholder="Ingresa el nombre del taller o enlace" />
            </div>
            
            <!-- Campos de dirección de facturación -->
            <div id="billing-address-section">
                <h3 style="margin-top: 25px; color: #016d86;">Dirección de Facturación</h3>
                
                <div style="margin-bottom: 15px;">
                    <label style="display: inline-flex; align-items: center; cursor: pointer; margin-top: 0;">
                        <input type="checkbox" id="same_address" name="same_address" style="margin-right: 10px;"> 
                        Usar datos personales para la facturación
                    </label>
                </div>
                
                <div id="billing-fields">
                    <label for="billing_address">Dirección:</label>
                    <input type="text" id="billing_address" name="billing_address" placeholder="Calle, número, piso, puerta" required />
                    
                    <label for="billing_city">Población:</label>
                    <input type="text" id="billing_city" name="billing_city" placeholder="Ciudad o población" required />
                    
                    <label for="billing_postal_code">Código Postal:</label>
                    <input type="text" id="billing_postal_code" name="billing_postal_code" placeholder="Código postal" required />
                    
                    <label for="billing_province">Provincia:</label>
                    <input type="text" id="billing_province" name="billing_province" placeholder="Provincia" required />
                </div>
            </div>
        </div>

        <!-- Página de Documentación -->
        <div id="page-documents" class="form-page hidden">
            <h3>Adjuntar Documentación</h3>
            <p>Por favor, sube los siguientes documentos. Puedes ver un ejemplo haciendo clic en "Ver ejemplo"...</p>
            <div class="upload-section">
                <div class="upload-item">
                    <label for="upload-dni-propietario">Copia del DNI del propietario</label>
                    <input type="file" id="upload-dni-propietario" name="upload_dni_propietario" required>
                    <a href="#" class="view-example" data-doc="dni-propietario">Ver ejemplo</a>
                </div>
                <div class="upload-item">
                    <label for="upload-hoja-asiento">Copia del Registro marítmo</label>
                    <input type="file" id="upload-hoja-asiento" name="upload_hoja_asiento" required>
                    <a href="#" class="view-example" data-doc="hoja-asiento">Ver ejemplo</a>
                </div>
            </div>

            <h3>Autorización</h3>
            <div class="document-sign-section">
                <p>Por favor, lee el siguiente documento y firma en el espacio proporcionado.</p>
                <div id="authorization-document" style="background-color:#f9f9f9; padding:20px; border:1px solid #e0e0e0;">
                    <!-- Se generará dinámicamente -->
                </div>
                <div id="signature-container" style="margin-top:20px; text-align:center;">
                    <canvas id="signature-pad" width="500" height="200" style="border:1px solid #ccc;"></canvas>
                </div>
                <button type="button" class="button" id="clear-signature">Limpiar Firma</button>
            </div>

            <div class="terms-container">
                <label>
                    <input type="checkbox" name="terms_accept" required> 
                    Acepto los <a href="https://tramitfy.es/terminos-y-condiciones-de-uso/" target="_blank">términos y condiciones</a>.
                </label>
            </div>

            <div class="button-container">
                <button type="button" class="button" id="prevButton">Anterior</button>
                <button type="button" class="button" id="nextButton">Siguiente</button>
            </div>
        </div>

        <!-- Página de Pago -->
        <div id="page-payment" class="form-page hidden">
            <h2 style="text-align: center; color: #016d86;">Información de Pago</h2>
            <div class="price-details">
                <p><strong>Baja de embarcación de recreo:</strong> <span style="float:right;">95.00 €</span></p>
                <p><strong>Incluye:</strong></p>
                <ul>
                    <li>Tasas y Honorarios - 81.15 €</li>
                    <li>IVA (21%) - 12.60 €</li>
                </ul>

                <!-- [NUEVO - CUPÓN] Añadimos visualización de descuento y total con descuento -->
                <p id="discount-line" style="display:none;">
                    <strong>Descuento:</strong>
                    <span style="float:right;" id="discount-amount"></span>
                </p>
                <p><strong>Total a pagar:</strong>
                    <span style="float:right;" id="final-amount">95.00 €</span>
                </p>
                <!-- [/NUEVO - CUPÓN] -->
            </div>

            <!-- Cupón BAJA -->
            <div style="margin:16px 0;padding:14px;border:1px solid #e5e7eb;border-radius:8px;background:#f9fafb;">
                <label style="display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:8px;">Código de descuento (opcional)</label>
                <div style="display:flex;gap:8px;align-items:center;">
                    <input type="text" id="baja-coupon-input" placeholder="Introduce tu código" maxlength="30"
                           style="flex:1;padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;text-transform:uppercase;">
                    <button type="button" id="baja-coupon-btn"
                            style="padding:8px 16px;background:#016d86;color:white;border:none;border-radius:6px;font-size:14px;cursor:pointer;white-space:nowrap;">
                        Aplicar
                    </button>
                </div>
                <div id="baja-coupon-msg" style="display:none;font-size:13px;margin-top:6px;"></div>
            </div>

            <div id="payment-form">
                <div id="payment-message" class="hidden"></div>
                <div class="terms-container">
                    <label>
                        <input type="checkbox" name="terms_accept_pago" required>
                        Acepto los <a href="https://tramitfy.es/terminos-y-condiciones-de-uso/" target="_blank">términos y condiciones de pago</a>.
                    </label>
                </div>
                <button id="submit" class="button">Pagar con CaixaBank</button>
                <p style="text-align:center; margin-top:10px; font-size:13px; color:#666;">Serás redirigido a la pasarela de pago segura de CaixaBank.</p>
            </div>

            <!-- Cupón hidden inputs -->
            <input type="hidden" id="baja-coupon-code" name="coupon_code" value="">
            <input type="hidden" id="baja-coupon-discount" name="coupon_discount" value="0">

            <!-- Formulario oculto para Redsys -->
            <form id="baja-redsys-form" action="" method="POST" style="display:none;">
                <input type="hidden" name="Ds_SignatureVersion" id="baja-Ds_SignatureVersion">
                <input type="hidden" name="Ds_MerchantParameters" id="baja-Ds_MerchantParameters">
                <input type="hidden" name="Ds_Signature" id="baja-Ds_Signature">
            </form>
        </div>

        <div class="button-container" id="main-button-container">
            <button type="button" class="button" id="prevButtonMain">Anterior</button>
            <button type="button" class="button" id="nextButtonMain">Siguiente</button>
        </div>
        
        <!-- Botón para prueba de factura (solo visible para administradores) -->
        <?php if (current_user_can('administrator')): ?>
        <div style="margin-top: 30px; padding: 15px; background-color: #f8f9fa; border: 1px dashed #6c757d; border-radius: 5px;">
            <h4 style="color: #6c757d;">Herramientas de prueba (Solo administradores)</h4>
            <button id="test-invoice-btn" type="button" class="button" style="background-color: #6c757d;">Generar factura de prueba</button>
            <span id="test-invoice-result" style="margin-left: 10px; display: none; font-style: italic;"></span>
        </div>
        <?php endif; ?>
    </form>

    <!-- Popup para ejemplos de documentos -->
    <div id="document-popup">
        <div class="popup-content">
            <span class="close-popup">&times;</span>
            <h3>Ejemplo de documento</h3>
            <img id="document-example-image" src="" alt="Ejemplo de documento">
        </div>
    </div>

    <!-- JavaScript para manejar la lógica del formulario -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Variables de pago (Redsys)

            // Manejo de precio base y descuento
            let basePrice = 95.00;         // Precio base
            let currentPrice = basePrice;  // Precio actual (puede bajar con cupón)
            let discountApplied = 0;       // %
            let discountAmount = 0;        // €
            // Componentes del precio
            const taxes = 21.15;           // Tasas fijas
            const fees = 60.00;            // Honorarios
            const vatRate = 0.21;          // IVA 21%

            // Helper: Convertir File a base64
            function fileToBase64(file) {
                return new Promise((resolve, reject) => {
                    const reader = new FileReader();
                    reader.onload = () => resolve(reader.result);
                    reader.onerror = reject;
                    reader.readAsDataURL(file);
                });
            }
            let bajaIsSubmitting = false;

            // Navegación entre páginas
            const formPages = document.querySelectorAll('.form-page');
            const navLinks = document.querySelectorAll('.nav-link');
            let currentPage = 0;

            function updateForm() {
                formPages.forEach((page, index) => {
                    page.classList.toggle('hidden', index !== currentPage);
                });
                navLinks.forEach((link, index) => {
                    link.classList.toggle('active', index === currentPage);
                });

                // Manejar botones
                if (formPages[currentPage].id === 'page-documents') {
                    document.getElementById('main-button-container').style.display = 'none';
                    document.querySelector('#page-documents .button-container').style.display = 'flex';
                } else {
                    document.getElementById('main-button-container').style.display = 'flex';
                    if (document.querySelector('#page-documents .button-container')) {
                        document.querySelector('#page-documents .button-container').style.display = 'none';
                    }
                }
                // Hide 'Previous' button on first page and last page (payment)
                document.getElementById('prevButtonMain').style.display = (currentPage === 0 || currentPage === formPages.length - 1) ? 'none' : 'inline-block';

                const nextButton = document.getElementById('nextButtonMain');
                if (currentPage === formPages.length - 1) {
                    nextButton.style.display = 'none';
                } else {
                    nextButton.textContent = 'Siguiente';
                    nextButton.style.display = 'inline-block';
                }

                // Setup payment button on payment page
                if (formPages[currentPage].id === 'page-payment') {
                    handlePayment();
                }

                // Generar documento en la página de Documentos
                if (formPages[currentPage].id === 'page-documents') {
                    generateAuthorizationDocument();
                }

                // Mostrar/ocultar taller segun tipo de baja
                const deregistrationType = document.getElementById('deregistration_type').value;
                const workshopSection = document.getElementById('workshop-data-section');
                const workshopInput = document.getElementById('workshop_data');
                if (deregistrationType === 'siniestro') {
                    workshopSection.style.display = 'block';
                    workshopInput.required = true;
                } else {
                    workshopSection.style.display = 'none';
                    workshopInput.required = false;
                }
            }

            function generateAuthorizationDocument() {
                const authorizationDiv = document.getElementById('authorization-document');
                const customerName = document.getElementById('customer_name').value.trim();
                const customerDNI = document.getElementById('customer_dni').value.trim();
                const deregTypeText = document.getElementById('deregistration_type').selectedOptions[0].text;
                const workshopData = document.getElementById('workshop_data').value.trim();

                let workshopText = '';
                if (workshopData) {
                    workshopText = `, en el taller: ${workshopData}`;
                }

                let authorizationHTML = `
                    <p>Yo, <strong>${customerName}</strong>, con DNI <strong>${customerDNI}</strong>, autorizo a Tramitfy S.L. (CIF B55388557) a realizar en mi nombre los trámites necesarios para la ${deregTypeText}${workshopText}.</p>
                    <p>Firmo a continuación en señal de conformidad.</p>
                `;
                authorizationDiv.innerHTML = authorizationHTML;
            }

            function handlePayment() {
                const submitButton = document.getElementById('submit');
                if (submitButton._redsysBound) return;
                submitButton._redsysBound = true;

                submitButton.addEventListener('click', async (e) => {
                    e.preventDefault();

                    if (!document.querySelector('input[name="terms_accept_pago"]').checked) {
                        alert('Debe aceptar los términos y condiciones de pago para continuar.');
                        return;
                    }

                    if (bajaIsSubmitting) {
                        console.warn('⚠️ Envío ya en proceso');
                        return;
                    }
                    bajaIsSubmitting = true;

                    submitButton.disabled = true;
                    submitButton.textContent = 'Procesando...';
                    document.getElementById('loading-overlay').style.display = 'flex';

                    try {
                        console.log('🔄 BAJA: Iniciando flujo Redsys temporal...');

                        // 1. Generar OrderID
                        const timestamp = Math.floor(Date.now() / 10000) * 10;
                        const generatedOrderId = '00' + timestamp.toString().padStart(10, '0');
                        console.log('BAJA: OrderID generado:', generatedOrderId);

                        // 2. Recopilar archivos en base64
                        const filesArray = [];
                        const form = document.getElementById('boat-deregistration-form');
                        const fileInputs = form.querySelectorAll('input[type="file"]');
                        for (const input of fileInputs) {
                            if (input.files) {
                                for (const file of input.files) {
                                    const base64 = await fileToBase64(file);
                                    filesArray.push({
                                        fieldName: input.name || input.id,
                                        fileName: file.name,
                                        mimeType: file.type,
                                        data: base64
                                    });
                                }
                            }
                        }

                        // Añadir firma
                        const signaturePad = window.signaturePad;
                        if (signaturePad && !signaturePad.isEmpty()) {
                            filesArray.push({
                                fieldName: 'firma',
                                fileName: 'firma.png',
                                mimeType: 'image/png',
                                data: signaturePad.toDataURL()
                            });
                        }

                        console.log('BAJA: Archivos preparados:', filesArray.length);

                        // 3. Enviar datos al API temporal
                        const captureData = {
                            orderId: generatedOrderId,
                            tramiteType: 'baja-embarcacion',
                            files: filesArray,
                            customerData: {
                                name: document.getElementById('customer_name').value.trim(),
                                dni: document.getElementById('customer_dni').value.trim(),
                                email: document.getElementById('customer_email').value.trim(),
                                phone: document.getElementById('customer_phone').value.trim()
                            },
                            serviceData: {
                                deregistrationType: document.getElementById('deregistration_type')?.value || '',
                                workshopData: document.getElementById('workshop_data')?.value || ''
                            },
                            couponCode: document.getElementById('baja-coupon-code')?.value || '',
                            couponDiscount: parseFloat(document.getElementById('baja-coupon-discount')?.value || 0),
                            pricing: {
                                amount: currentPrice,
                                basePrice: <?php echo BAJA_SERVICE_PRICE; ?>,
                                taxes: 21.15,
                                fees: 60.00
                            },
                            metadata: {
                                timestamp: Date.now(),
                                formId: 'baja'
                            },
                            ga_client_id: (function() { var m = document.cookie.match(/_ga=GA\d+\.\d+\.(.+)/); return m ? m[1] : ''; })(),
                            gclid: new URLSearchParams(window.location.search).get('gclid') || sessionStorage.getItem('gclid') || '',
                            ga_session_id: (function() { var c = document.cookie.match(/_ga_[A-Z0-9]+=GS\d+\.\d+\.(.+?)(?:\.|$)/); return c ? c[1] : ''; })()
                        };

                        const captureResponse = await fetch('https://tramitfy.org/api/temporal/capture', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(captureData)
                        });

                        const captureResult = await captureResponse.json();
                        console.log('BAJA: Respuesta temporal:', captureResult);

                        if (!captureResult.success) {
                            throw new Error(captureResult.error || 'Error capturando datos temporales');
                        }

                        // 4. Crear pago Redsys
                        const paymentData = new FormData();
                        paymentData.append('action', 'baja_create_redsys_payment');
                        paymentData.append('amount', currentPrice);
                        paymentData.append('orderId', generatedOrderId);
                        paymentData.append('couponDiscount', document.getElementById('baja-coupon-discount')?.value || '0');

                        const ajaxUrl = '<?php echo admin_url("admin-ajax.php"); ?>';
                        const response = await fetch(ajaxUrl, {
                            method: 'POST',
                            body: paymentData
                        });

                        const result = await response.json();

                        if (result.success) {
                            console.log('BAJA: Pago Redsys creado, redirigiendo a pasarela...');

                            const redsysForm = document.getElementById('baja-redsys-form');
                            redsysForm.action = result.data.paymentData.url;
                            document.getElementById('baja-Ds_SignatureVersion').value = result.data.paymentData.Ds_SignatureVersion;
                            document.getElementById('baja-Ds_MerchantParameters').value = result.data.paymentData.Ds_MerchantParameters;
                            document.getElementById('baja-Ds_Signature').value = result.data.paymentData.Ds_Signature;

                            setTimeout(() => {
                                redsysForm.submit();
                            }, 1500);
                        } else {
                            throw new Error(result.data?.message || 'Error al crear el pago');
                        }

                    } catch (error) {
                        console.error('BAJA Error:', error);
                        document.getElementById('payment-message').textContent = 'Error al procesar el pago: ' + error.message;
                        document.getElementById('payment-message').classList.add('error');
                        document.getElementById('payment-message').classList.remove('hidden');
                        submitButton.disabled = false;
                        submitButton.textContent = 'Pagar con CaixaBank';
                        document.getElementById('loading-overlay').style.display = 'none';
                        bajaIsSubmitting = false;
                    }
                });
            }

            document.getElementById('nextButtonMain').addEventListener('click', () => {
                if (!validateCurrentPage()) return;
                currentPage++;
                updateForm();
            });

            document.getElementById('prevButtonMain').addEventListener('click', () => {
                currentPage--;
                updateForm();
            });

            const prevButton = document.getElementById('prevButton');
            const nextButton = document.getElementById('nextButton');
            if (prevButton && nextButton) {
                prevButton.addEventListener('click', () => {
                    currentPage--;
                    updateForm();
                });
                nextButton.addEventListener('click', () => {
                    if (!validateCurrentPage()) return;
                    currentPage++;
                    updateForm();
                });
            }

            navLinks.forEach(link => {
                link.addEventListener('click', (e) => {
                    e.preventDefault();
                    const pageId = link.getAttribute('data-page-id');
                    const pageIndex = Array.from(formPages).findIndex(page => page.id === pageId);
                    if (pageIndex !== -1) {
                        currentPage = pageIndex;
                        updateForm();
                    }
                });
            });

            updateForm();
            window.signaturePad = new SignaturePad(document.getElementById('signature-pad'));

            document.getElementById('clear-signature').addEventListener('click', function() {
                window.signaturePad.clear();
            });

            function validateCurrentPage() {
                let valid = true;
                const currentForm = formPages[currentPage];
                const requiredFields = currentForm.querySelectorAll('input[required], select[required]');
                const errorMessages = [];
                requiredFields.forEach(field => {
                    if (!field.value || (field.type === 'checkbox' && !field.checked)) {
                        valid = false;
                        field.classList.add('field-error');
                        const labelText = field.previousElementSibling ? field.previousElementSibling.textContent : field.name;
                        errorMessages.push(`El campo "${labelText}" es obligatorio.`);
                    } else {
                        field.classList.remove('field-error');
                    }
                });

                if (!valid) {
                    const errorDiv = document.getElementById('error-messages');
                    errorDiv.innerHTML = '';
                    errorMessages.forEach(msg => {
                        const p = document.createElement('p');
                        p.textContent = msg;
                        p.classList.add('error-message');
                        errorDiv.appendChild(p);
                    });
                } else {
                    document.getElementById('error-messages').innerHTML = '';
                }
                return valid;
            }

            // Manejo del popup para ejemplos
            const popup = document.getElementById('document-popup');
            const closePopup = document.querySelector('.close-popup');
            const exampleImage = document.getElementById('document-example-image');

            document.querySelectorAll('.view-example').forEach(link => {
                link.addEventListener('click', function(event) {
                    event.preventDefault();
                    const docType = this.getAttribute('data-doc');
                    exampleImage.src = '/wp-content/uploads/exampledocs/' + docType + '.jpg';
                    popup.style.display = 'block';
                });
            });

            closePopup.addEventListener('click', () => {
                popup.style.display = 'none';
            });
            window.addEventListener('click', function(event) {
                if (event.target == popup) {
                    popup.style.display = 'none';
                }
            });

            // Al cambiar tipo de baja
            document.getElementById('deregistration_type').addEventListener('change', updateForm);
            
            // Gestionar el checkbox de misma dirección para la facturación
            document.getElementById('same_address').addEventListener('change', function() {
                const billingFields = document.getElementById('billing-fields');
                const billingInputs = billingFields.querySelectorAll('input');
                
                if (this.checked) {
                    // Ocultar campos de dirección de facturación
                    billingFields.style.display = 'none';
                    // Hacer los campos no requeridos
                    billingInputs.forEach(input => {
                        input.required = false;
                    });
                } else {
                    // Mostrar campos de dirección de facturación
                    billingFields.style.display = 'block';
                    // Hacer los campos requeridos de nuevo
                    billingInputs.forEach(input => {
                        input.required = true;
                    });
                }
            });

            // Cupón BAJA - nuevo sistema
            function updatePriceWithCoupon_baja(discount) {
                currentPrice = Math.max(0, basePrice - discount);
                const finalAmountSpan = document.getElementById('final-amount');
                if (finalAmountSpan) finalAmountSpan.textContent = currentPrice.toFixed(2) + ' €';
            }

            (function() {
                const btn = document.getElementById('baja-coupon-btn');
                const input = document.getElementById('baja-coupon-input');
                const msg = document.getElementById('baja-coupon-msg');
                const hiddenCode = document.getElementById('baja-coupon-code');
                const hiddenDiscount = document.getElementById('baja-coupon-discount');
                if (!btn) return;
                btn.addEventListener('click', async function() {
                    const code = (input?.value || '').trim().toUpperCase();
                    if (!code) return;
                    btn.disabled = true; btn.textContent = '...';
                    msg.style.display = 'none';
                    try {
                        const r = await fetch('https://tramitfy.org/api/coupons/validate', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ code })
                        });
                        const data = await r.json();
                        if (data.valid) {
                            hiddenCode.value = data.code;
                            hiddenDiscount.value = data.clientDiscount;
                            updatePriceWithCoupon_baja(data.clientDiscount);
                            msg.style.cssText = 'display:block;color:#16a34a;font-weight:600;';
                            msg.textContent = '✓ ' + data.message;
                            btn.textContent = '✓'; btn.style.background = '#16a34a';
                            input.disabled = true; btn.disabled = true;
                        } else {
                            msg.style.cssText = 'display:block;color:#dc2626;';
                            msg.textContent = '✗ ' + (data.error || 'Cupón no válido');
                            btn.disabled = false; btn.textContent = 'Aplicar';
                        }
                    } catch(e) {
                        msg.style.cssText = 'display:block;color:#dc2626;';
                        msg.textContent = 'Error de conexión.';
                        btn.disabled = false; btn.textContent = 'Aplicar';
                    }
                });
            })();
            
            // Código para el botón de prueba de factura
            document.getElementById('test-invoice-btn')?.addEventListener('click', async function() {
                const resultSpan = document.getElementById('test-invoice-result');
                resultSpan.textContent = 'Generando factura de prueba...';
                resultSpan.style.display = 'inline';
                
                try {
                    // Recoger datos del formulario para la prueba
                    const testData = {
                        customer_name: document.getElementById('customer_name').value || 'Cliente de Prueba',
                        customer_dni: document.getElementById('customer_dni').value || '12345678Z',
                        customer_email: document.getElementById('customer_email').value || 'prueba@ejemplo.com',
                        customer_phone: document.getElementById('customer_phone').value || '600123456',
                        deregistration_type: document.getElementById('deregistration_type').value || 'siniestro',
                        workshop_data: document.getElementById('workshop_data').value || '',
                        coupon_code: document.getElementById('coupon_code').value || '',
                        same_address: document.getElementById('same_address').checked,
                        billing_address: document.getElementById('billing_address').value || 'Calle Ejemplo, 123',
                        billing_city: document.getElementById('billing_city').value || 'Madrid',
                        billing_postal_code: document.getElementById('billing_postal_code').value || '28001',
                        billing_province: document.getElementById('billing_province').value || 'Madrid'
                    };
                    
                    const response = await fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `action=test_invoice_boat_deregistration&data=${encodeURIComponent(JSON.stringify(testData))}`
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        resultSpan.textContent = 'Factura generada. Abriendo...';
                        resultSpan.style.color = '#28a745';
                        
                        // Abrir la factura en una nueva ventana
                        window.open(result.data.pdf_url, '_blank');
                    } else {
                        throw new Error(result.data?.message || 'Error desconocido');
                    }
                } catch (error) {
                    console.error('Error al generar la factura de prueba:', error);
                    resultSpan.textContent = 'Error: ' + error.message;
                    resultSpan.style.color = '#dc3545';
                }
            });
        });
    </script>

    <?php
    return ob_get_clean();
}
add_shortcode('boat_deregistration_form', 'boat_deregistration_form_shortcode');

/**
 * Endpoint para crear pago Redsys
 */
add_action('wp_ajax_baja_create_redsys_payment', 'baja_create_redsys_payment');
add_action('wp_ajax_nopriv_baja_create_redsys_payment', 'baja_create_redsys_payment');


/**
 * Endpoint para generar una factura de prueba
 */
add_action('wp_ajax_test_invoice_boat_deregistration', 'test_invoice_boat_deregistration');

function test_invoice_boat_deregistration() {
    // Verificar si el usuario es administrador
    if (!current_user_can('administrator')) {
        wp_send_json_error(['message' => 'Permiso denegado']);
        return;
    }
    
    $data = json_decode(stripslashes($_POST['data']), true);
    
    if (empty($data)) {
        wp_send_json_error(['message' => 'Datos no válidos']);
        return;
    }
    
    // Asignar valores de prueba
    $customer_name = sanitize_text_field($data['customer_name']);
    $customer_dni = sanitize_text_field($data['customer_dni']);
    $customer_email = sanitize_email($data['customer_email']);
    $customer_phone = sanitize_text_field($data['customer_phone']);
    $deregistration_type = sanitize_text_field($data['deregistration_type']);
    $workshop_data = sanitize_text_field($data['workshop_data']);
    $coupon_used = sanitize_text_field($data['coupon_code']);
    
    $upload_dir = wp_upload_dir();
    
    // Verificar si se usa la misma dirección personal
    $same_address = isset($data['same_address']) ? (bool)$data['same_address'] : false;
    
    if ($same_address) {
        // Si se marcó la casilla, utilizar el nombre como dirección de facturación (simplificado)
        $billing_address = $customer_name;
        $billing_city = '';
        $billing_postal_code = '';
        $billing_province = '';
    } else {
        // Obtener datos de dirección para la factura
        $billing_address = isset($data['billing_address']) ? sanitize_text_field($data['billing_address']) : '';
        $billing_city = isset($data['billing_city']) ? sanitize_text_field($data['billing_city']) : '';
        $billing_postal_code = isset($data['billing_postal_code']) ? sanitize_text_field($data['billing_postal_code']) : '';
        $billing_province = isset($data['billing_province']) ? sanitize_text_field($data['billing_province']) : '';
    }
    
    // Generar la factura usando la función existente
    $invoice_pdf_path = generate_invoice_pdf(
        $customer_name, 
        $customer_dni, 
        $customer_email, 
        $customer_phone, 
        $deregistration_type, 
        $workshop_data, 
        $coupon_used, 
        $upload_dir,
        $billing_address,
        $billing_city,
        $billing_postal_code,
        $billing_province
    );
    
    // Obtener la URL del archivo
    $invoice_pdf_url = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $invoice_pdf_path);
    
    wp_send_json_success([
        'message' => 'Factura generada correctamente',
        'pdf_url' => $invoice_pdf_url
    ]);
}

/**
 * Función para manejar el envío final del formulario
 */
add_action('wp_ajax_submit_form_boat_deregistration', 'submit_form_boat_deregistration');
add_action('wp_ajax_nopriv_submit_form_boat_deregistration', 'submit_form_boat_deregistration');

function submit_form_boat_deregistration() {
    // Validar y procesar los datos enviados
    $customer_name = sanitize_text_field($_POST['customer_name']);
    $customer_dni = sanitize_text_field($_POST['customer_dni']);
    $customer_email = sanitize_email($_POST['customer_email']);
    $customer_phone = sanitize_text_field($_POST['customer_phone']);
    $deregistration_type = sanitize_text_field($_POST['deregistration_type']);
    $workshop_data = sanitize_text_field($_POST['workshop_data']);

    // [NUEVO - CUPÓN] Capturamos el cupón utilizado
    $coupon_used = isset($_POST['coupon_used']) ? sanitize_text_field($_POST['coupon_used']) : '';

    $signature = $_POST['signature'];

    // Procesar la firma
    $signature_data = str_replace('data:image/png;base64,', '', $signature);
    $signature_data = base64_decode($signature_data);

    $upload_dir = wp_upload_dir();
    $signature_image_name = 'signature_' . time() . '.png';
    $signature_image_path = $upload_dir['path'] . '/' . $signature_image_name;
    file_put_contents($signature_image_path, $signature_data);

    // Generar PDF de autorización
    require_once get_template_directory() . '/vendor/fpdf/fpdf.php';
    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', '', 12);

    // Agregar fecha en esquina superior
    $pdf->Cell(0, 10, 'Fecha: ' . date('d/m/Y'), 0, 0, 'R');
    $pdf->Ln(10);

    $pdf->Cell(0, 10, utf8_decode('Autorización para Baja de Embarcación de Recreo'), 0, 1, 'C');
    $pdf->Ln(10);

    $deregistration_type_text = ($deregistration_type === 'siniestro') ? 'siniestro' : 'exportación';
    $texto = "Yo, $customer_name, con DNI $customer_dni, autorizo a Tramitfy S.L. (CIF B55388557) a realizar en mi nombre los trámites necesarios para la baja definitiva por $deregistration_type_text.";
    if ($workshop_data) {
        $texto .= " En el taller: $workshop_data.";
    }
    $pdf->MultiCell(0, 10, utf8_decode($texto), 0, 'J');
    $pdf->Ln(10);

    $pdf->Cell(0, 10, utf8_decode('Firma:'), 0, 1);
    $pdf->Image($signature_image_path, null, null, 50, 30);

    $authorization_pdf_name = 'autorizacion_' . time() . '.pdf';
    $authorization_pdf_path = $upload_dir['path'] . '/' . $authorization_pdf_name;
    $pdf->Output('F', $authorization_pdf_path);
    
    // Generar PDF de factura con dirección de facturación
    $same_address = isset($_POST['same_address']);
    
    if ($same_address) {
        // Si se marcó la casilla, utilizar el nombre como dirección de facturación (simplificado)
        $billing_address = $customer_name;
        $billing_city = '';
        $billing_postal_code = '';
        $billing_province = '';
    } else {
        // Usar los campos de dirección de facturación proporcionados
        $billing_address = sanitize_text_field($_POST['billing_address']);
        $billing_city = sanitize_text_field($_POST['billing_city']);
        $billing_postal_code = sanitize_text_field($_POST['billing_postal_code']);
        $billing_province = sanitize_text_field($_POST['billing_province']);
    }
    
    $invoice_pdf_path = generate_invoice_pdf(
        $customer_name, 
        $customer_dni, 
        $customer_email, 
        $customer_phone, 
        $deregistration_type, 
        $workshop_data, 
        $coupon_used, 
        $upload_dir,
        $billing_address,
        $billing_city,
        $billing_postal_code,
        $billing_province
    );

    unlink($signature_image_path);

    // Procesar archivos subidos
    $attachments = [$authorization_pdf_path, $invoice_pdf_path];
    foreach ($_FILES as $key => $file) {
        if ($file['error'] === UPLOAD_ERR_OK) {
            $uploaded_file = wp_handle_upload($file, ['test_form' => false]);
            if (isset($uploaded_file['file'])) {
                $attachments[] = $uploaded_file['file'];
            }
        }
    }

    // Email del administrador para recibir notificaciones
    $admin_email = 'ipmgroup24@gmail.com';
    $subject_admin = 'Nuevo formulario de baja de embarcación de recreo enviado';

    $deregistration_type_text_2 = ($deregistration_type === 'siniestro') ? 'Baja definitiva por siniestro' : 'Baja definitiva por exportación';

    // [NUEVO - CUPÓN] Agregamos la fila del cupón al correo del admin
    $message_admin = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body {
                font-family: Arial, sans-serif;
                line-height: 1.6;
                color: #333333;
            }
            .container {
                max-width: 600px;
                margin: 0 auto;
                padding: 20px;
                background-color: #f9f9f9;
                border: 1px solid #e0e0e0;
                border-radius: 10px;
            }
            .header {
                text-align: center;
                margin-bottom: 20px;
            }
            .header img {
                max-width: 200px;
                height: auto;
                margin-bottom: 10px;
            }
            .content {
                padding: 20px;
                background-color: #ffffff;
                border-radius: 8px;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            }
            .footer {
                margin-top: 30px;
                padding: 10px 20px;
                background-color: #016d86;
                color: #ffffff;
                text-align: left;
                font-size: 12px;
                border-radius: 8px;
            }
            a {
                color: #FFFFFF;
                text-decoration: none;
            }
            a:hover {
                text-decoration: underline;
            }
            .details-table {
                width: 100%;
                border-collapse: collapse;
            }
            .details-table th, .details-table td {
                text-align: left;
                padding: 8px;
                border-bottom: 1px solid #dddddd;
            }
            .details-table th {
                background-color: #f2f2f2;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <img src="https://www.tramitfy.es/wp-content/uploads/LOGO.png" alt="Tramitfy Logo">
                <h2 style="color: #016d86;">Nuevo Formulario de Baja de Embarcación de Recreo Enviado</h2>
            </div>
            <div class="content">
                <p>Se ha recibido un nuevo formulario con los siguientes detalles:</p>
                <h3>Datos del Cliente:</h3>
                <table class="details-table">
                    <tr>
                        <th>Nombre:</th>
                        <td>' . htmlspecialchars($customer_name) . '</td>
                    </tr>
                    <tr>
                        <th>DNI:</th>
                        <td>' . htmlspecialchars($customer_dni) . '</td>
                    </tr>
                    <tr>
                        <th>Email:</th>
                        <td>' . htmlspecialchars($customer_email) . '</td>
                    </tr>
                    <tr>
                        <th>Teléfono:</th>
                        <td>' . htmlspecialchars($customer_phone) . '</td>
                    </tr>
                    <tr>
                        <th>Tipo de Baja:</th>
                        <td>' . htmlspecialchars($deregistration_type_text_2) . '</td>
                    </tr>
                    <!-- [NUEVO - CUPÓN] Mostramos el cupón usado -->
                    <tr>
                        <th>Cupón utilizado:</th>
                        <td>' . htmlspecialchars($coupon_used) . '</td>
                    </tr>
                </table>
                
                <h3>Dirección de Facturación:</h3>
                <table class="details-table">
                    <tr>
                        <th>Dirección:</th>
                        <td>' . htmlspecialchars($billing_address) . '</td>
                    </tr>
                    <tr>
                        <th>Población:</th>
                        <td>' . htmlspecialchars($billing_city) . '</td>
                    </tr>
                    <tr>
                        <th>Código Postal:</th>
                        <td>' . htmlspecialchars($billing_postal_code) . '</td>
                    </tr>
                    <tr>
                        <th>Provincia:</th>
                        <td>' . htmlspecialchars($billing_province) . '</td>
                    </tr>
                </table>';

    if ($workshop_data) {
        $message_admin .= '
                <h3>Datos del Taller:</h3>
                <p>' . htmlspecialchars($workshop_data) . '</p>';
    }

    $message_admin .= '
                <p>Se adjuntan los siguientes documentos:</p>
                <ul>
                    <li>Autorización firmada por el cliente</li>
                    <li>Factura generada</li>
                    <li>Documentos proporcionados por el cliente</li>
                </ul>
            </div>
            <div class="footer">
                <p><strong>Tramitfy S.L.</strong><br>
                Correo: <a href="mailto:info@tramitfy.es">info@tramitfy.es</a><br>
                Teléfono: <a href="tel:+34689170273">+34 689 170 273</a><br>
                Dirección: Paseo Castellana 194 puerta B, Madrid, España<br>
                Web: <a href="https://www.tramitfy.es">www.tramitfy.es</a></p>
            </div>
        </div>
    </body>
    </html>';

    $headers = [];
    $headers[] = 'Content-Type: text/html; charset=UTF-8';
    $headers[] = 'From: info@tramitfy.es';

    wp_mail($admin_email, $subject_admin, $message_admin, $headers, $attachments);

    // Correo al cliente
    $subject_client = 'Confirmación de su solicitud de baja de embarcación de recreo';
    $message_client = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body {
                font-family: Arial, sans-serif;
                line-height: 1.6;
                color: #333333;
            }
            .container {
                max-width: 600px;
                margin: 0 auto;
                padding: 20px;
                background-color: #f9f9f9;
                border: 1px solid #e0e0e0;
                border-radius: 10px;
            }
            .header {
                text-align: center;
                margin-bottom: 20px;
            }
            .header img {
                max-width: 200px;
                height: auto;
                margin-bottom: 10px;
            }
            .content {
                padding: 20px;
                background-color: #ffffff;
                border-radius: 8px;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            }
            .footer {
                margin-top: 30px;
                padding: 10px 20px;
                background-color: #016d86;
                color: #ffffff;
                text-align: left;
                font-size: 12px;
                border-radius: 8px;
            }
            a {
                color: #FFFFFF;
                text-decoration: none;
            }
            a:hover {
                text-decoration: underline;
            }
            .invoice-box {
                margin-top: 20px;
                padding: 15px;
                border: 1px solid #28a745;
                background-color: #d4edda;
                border-radius: 5px;
                color: #155724;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <img src="https://www.tramitfy.es/wp-content/uploads/LOGO.png" alt="Tramitfy Logo">
                <h2 style="color: #016d86;">Confirmación de su solicitud de baja de embarcación de recreo</h2>
            </div>
            <div class="content">
                <p>Estimado/a ' . htmlspecialchars($customer_name) . ',</p>
                <p>Hemos recibido su solicitud para la ' . htmlspecialchars($deregistration_type_text_2) . '.</p>
                <p>Le facilitaremos la documentación por correo electrónico tan pronto la recibamos.</p>
                
                <div class="invoice-box">
                    <p><strong>Factura adjunta</strong></p>
                    <p>Se adjunta a este correo la factura correspondiente a su pago. Por favor, conserve este documento para sus registros.</p>
                </div>
                
                <p>Gracias por confiar en nosotros.</p>
                <p>Atentamente,<br>El equipo de Tramitfy</p>
            </div>
            <div class="footer">
                <p><strong>Tramitfy S.L.</strong><br>
                Correo: <a href="mailto:info@tramitfy.es">info@tramitfy.es</a><br>
                Teléfono: <a href="tel:+34689170273">+34 689 170 273</a><br>
                Dirección: Paseo Castellana 194 puerta B, Madrid, España<br>
                Web: <a href="https://www.tramitfy.es">www.tramitfy.es</a></p>
            </div>
        </div>
    </body>
    </html>';

    $headers_client = [];
    $headers_client[] = 'Content-Type: text/html; charset=UTF-8';
    $headers_client[] = 'From: info@tramitfy.es';

    wp_mail($customer_email, $subject_client, $message_client, $headers_client);

    // [NUEVO - INTEGRACIÓN TRAMITFY] Enviar datos también a la app React
    send_to_tramitfy_app(
        $customer_name,
        $customer_dni,
        $customer_email,
        $customer_phone,
        $deregistration_type,
        $workshop_data,
        $coupon_used,
        $signature,
        $same_address,
        $billing_address,
        $billing_city,
        $billing_postal_code,
        $billing_province,
        $attachments
    );

    wp_send_json_success('Formulario procesado correctamente.');
    wp_die();
}

/**
 * [NUEVO - INTEGRACIÓN TRAMITFY] Función para enviar datos a la app React de Tramitfy
 */
function send_to_tramitfy_app($customer_name, $customer_dni, $customer_email, $customer_phone,
                              $deregistration_type, $workshop_data, $coupon_used, $signature,
                              $same_address, $billing_address, $billing_city, $billing_postal_code,
                              $billing_province, $attachments) {

    // URL del endpoint de la API de Tramitfy
    // Webhook para sincronizar con React Dashboard
    $tramitfy_api_url = 'https://tramitfy.org/api/herramientas/baja/webhook';

    // Preparar los datos en el formato que espera Tramitfy
    $tramitfy_data = array(
        'customer_name' => $customer_name,
        'customer_dni' => $customer_dni,
        'customer_email' => $customer_email,
        'customer_phone' => $customer_phone,
        'deregistration_type' => $deregistration_type,
        'workshop_data' => $workshop_data,
        'coupon_used' => $coupon_used,
        'signature' => $signature,
        'same_address' => $same_address ? 'true' : 'false',
        'billing_address' => $billing_address,
        'billing_city' => $billing_city,
        'billing_postal_code' => $billing_postal_code,
        'billing_province' => $billing_province,
        'payment_completed' => 'true', // Stripe payment was successful
        // Crear referencias a los documentos (sin enviar archivos grandes)
        'document_references' => json_encode(array(
            'authorization_pdf' => 'Generated authorization PDF',
            'invoice_pdf' => 'Generated invoice PDF',
            'uploaded_files' => array_map(function($path) {
                return basename($path);
            }, $attachments)
        ))
    );

    // Configurar la solicitud HTTP
    $args = array(
        'method' => 'POST',
        'timeout' => 30,
        'redirection' => 5,
        'httpversion' => '1.0',
        'blocking' => true,
        'headers' => array(
            'Content-Type' => 'application/x-www-form-urlencoded',
            'User-Agent' => 'WordPress/Tramitfy-PHP-Form'
        ),
        'body' => $tramitfy_data
    );

    // Enviar la solicitud
    $response = wp_remote_post($tramitfy_api_url, $args);

    // Verificar si hubo error en la solicitud
    if (is_wp_error($response)) {
        error_log('Error enviando datos a Tramitfy: ' . $response->get_error_message());
        return false;
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);

    // Log del resultado
    if ($response_code == 200) {
        $result = json_decode($response_body, true);
        if ($result && isset($result['success']) && $result['success']) {
            error_log('Datos enviados exitosamente a Tramitfy. Procedure ID: ' . $result['procedureId']);
            return true;
        } else {
            error_log('Tramitfy respondió con error: ' . $response_body);
            return false;
        }
    } else {
        error_log('Error HTTP al enviar a Tramitfy. Código: ' . $response_code . ' Respuesta: ' . $response_body);
        return false;
    }
}
?>
