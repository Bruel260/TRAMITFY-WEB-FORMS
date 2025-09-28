<?php
// Asegurarse de que el archivo no sea accedido directamente
defined('ABSPATH') || exit;

// Configuración de Stripe
define('STRIPE_MODE', 'live'); // 'test' o 'live'
define('STRIPE_TEST_PUBLIC_KEY', 'YOUR_STRIPE_TEST_PUBLIC_KEY_HERE');
define('STRIPE_TEST_SECRET_KEY', 'YOUR_STRIPE_TEST_SECRET_KEY_HERE');
define('STRIPE_LIVE_PUBLIC_KEY', 'YOUR_STRIPE_LIVE_PUBLIC_KEY_HERE');
define('STRIPE_LIVE_SECRET_KEY', 'YOUR_STRIPE_LIVE_SECRET_KEY_HERE');

// Seleccionar las claves según el modo
$stripe_public_key = (STRIPE_MODE === 'live') ? STRIPE_LIVE_PUBLIC_KEY : STRIPE_TEST_PUBLIC_KEY;
$stripe_secret_key = (STRIPE_MODE === 'live') ? STRIPE_LIVE_SECRET_KEY : STRIPE_TEST_SECRET_KEY;

// Precio del servicio (en euros)
define('SERVICE_PRICE', 95.00);

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
    $pdf->SetFont('Arial', '', 10);

    // Crear tabla con colores
    $pdf->SetFillColor($primary_color[0], $primary_color[1], $primary_color[2]);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(100, 8, utf8_decode('Descripción'), 1, 0, 'C', true);
    $pdf->Cell(40, 8, 'Cantidad', 1, 0, 'C', true);
    $pdf->Cell(50, 8, 'Precio', 1, 1, 'R', true);

    // Tipo de servicio
    $deregistration_type_text = ($deregistration_type === 'siniestro') ? 'Baja definitiva por siniestro' : 'Baja definitiva por exportación';
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetTextColor($text_color[0], $text_color[1], $text_color[2]);
    $pdf->Cell(100, 8, utf8_decode($deregistration_type_text), 1, 0, 'L');
    $pdf->Cell(40, 8, '1', 1, 0, 'C');
    $pdf->Cell(50, 8, number_format($base_price, 2).' EUR', 1, 1, 'R');

    // Descuento si aplica
    if ($discount_amount > 0) {
        $pdf->Cell(100, 8, utf8_decode('Descuento ('.$discount_percent.'%)'), 1, 0, 'L');
        $pdf->Cell(40, 8, '1', 1, 0, 'C');
        $pdf->Cell(50, 8, '-'.number_format($discount_amount, 2).' EUR', 1, 1, 'R');
    }

    $pdf->Ln(5);

    // Resumen financiero
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(140, 8, 'Base imponible:', 0, 0, 'R');
    $pdf->Cell(50, 8, number_format($price_before_vat, 2).' EUR', 1, 1, 'R');

    $pdf->Cell(140, 8, 'IVA (21%):', 0, 0, 'R');
    $pdf->Cell(50, 8, number_format($vat_amount, 2).' EUR', 1, 1, 'R');

    $pdf->Cell(140, 8, 'Tasas:', 0, 0, 'R');
    $pdf->Cell(50, 8, number_format($taxes, 2).' EUR', 1, 1, 'R');

    $pdf->SetFont('Arial', 'B', 12);
    $pdf->SetTextColor($primary_color[0], $primary_color[1], $primary_color[2]);
    $pdf->Cell(140, 10, 'TOTAL:', 0, 0, 'R');
    $pdf->Cell(50, 10, number_format($total_with_discount, 2).' EUR', 1, 1, 'R');

    $pdf->Ln(10);

    // Información adicional
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetTextColor($text_color[0], $text_color[1], $text_color[2]);
    $pdf->Cell(0, 6, utf8_decode('Forma de pago: Stripe (Tarjeta de crédito/débito)'), 0, 1, 'L');
    $pdf->Cell(0, 6, utf8_decode('Estado: Pagado'), 0, 1, 'L');

    if (!empty($workshop_data)) {
        $pdf->Cell(0, 6, utf8_decode('Datos del taller: '.$workshop_data), 0, 1, 'L');
    }

    $pdf->Ln(10);

    // Mensaje de pie
    $pdf->SetFont('Arial', 'I', 9);
    $pdf->Cell(0, 5, utf8_decode('Gracias por confiar en Tramitfy para sus trámites náuticos.'), 0, 1, 'C');
    $pdf->Cell(0, 5, utf8_decode('Para cualquier consulta, puede contactarnos en info@tramitfy.es'), 0, 1, 'C');

    // Guardar el PDF en el directorio de uploads
    $invoice_filename = 'factura_baja_embarcacion_' . date('Ymd_His') . '.pdf';
    $invoice_path = $upload_dir . '/' . $invoice_filename;
    $pdf->Output('F', $invoice_path);

    return $invoice_filename;
}

/**
 * Shortcode para el formulario de baja de embarcación
 */
function boat_deregistration_form_shortcode() {
    global $stripe_public_key;

    // Encolar los scripts y estilos necesarios
    wp_enqueue_style('boat-deregistration-form-style', get_template_directory_uri() . '/style.css', array(), filemtime(get_template_directory() . '/style.css'));
    wp_enqueue_script('stripe', 'https://js.stripe.com/v3/', array(), null, false);
    wp_enqueue_script('signature-pad', 'https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js', array(), null, false);

    // Iniciar el buffering de salida
    ob_start();
    ?>

    <!-- Font Awesome para iconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        /* Variables de color */
        :root {
            --primary: 1, 109, 134;
            --primary-dark: 0, 86, 106;
            --primary-light: 0, 125, 156;

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
        }

        /* Reset y estilos globales */
        * {
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: rgb(var(--neutral-800));
        }

        /* Container principal - Grid de 2 columnas */
        .bd-container {
            max-width: 1400px;
            margin: 25px auto;
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
            display: grid;
            grid-template-columns: 380px 1fr;
            min-height: auto;
        }

        /* SIDEBAR IZQUIERDO */
        .bd-sidebar {
            background: linear-gradient(180deg, rgb(var(--primary)) 0%, rgb(var(--primary-dark)) 100%);
            color: white;
            padding: 20px 20px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            position: sticky;
            top: 0;
            height: 95vh;
            overflow-y: auto;
        }

        .bd-logo {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .bd-logo i {
            font-size: 28px;
        }

        .bd-headline {
            font-size: 17px;
            font-weight: 600;
            line-height: 1.3;
            margin-bottom: 4px;
        }

        .bd-subheadline {
            font-size: 13px;
            opacity: 0.92;
            line-height: 1.4;
        }

        /* Caja de precio destacada */
        .bd-price-box {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            padding: 12px;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.25);
            margin: 6px 0;
        }

        .bd-price-label {
            font-size: 11px;
            opacity: 0.85;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 6px;
        }

        .bd-price-amount {
            font-size: 38px;
            font-weight: 700;
            margin: 4px 0;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .bd-price-detail {
            font-size: 12px;
            opacity: 0.88;
        }

        /* Lista de beneficios */
        .bd-benefits {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin: 8px 0;
        }

        .bd-benefit {
            display: flex;
            align-items: start;
            gap: 8px;
            font-size: 12px;
            line-height: 1.4;
        }

        .bd-benefit i {
            font-size: 14px;
            color: rgb(var(--success));
            background: white;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            margin-top: 1px;
        }

        /* Trust badges */
        .bd-trust-badges {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: auto;
            padding-top: 10px;
        }

        .bd-badge {
            background: rgba(255, 255, 255, 0.18);
            padding: 5px 10px;
            border-radius: 16px;
            font-size: 10px;
            display: flex;
            align-items: center;
            gap: 4px;
            border: 1px solid rgba(255, 255, 255, 0.25);
            font-weight: 500;
        }

        .bd-badge i {
            font-size: 11px;
        }

        /* Sidebar de autorización */
        .bd-sidebar-auth-doc {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-top: 10px;
        }

        /* ÁREA PRINCIPAL DEL FORMULARIO */
        .bd-form-area {
            padding: 30px 40px;
            background: #fafbfc;
            overflow-y: auto;
        }

        .bd-form-header {
            margin-bottom: 15px;
        }

        .bd-form-title {
            font-size: 22px;
            font-weight: 700;
            color: rgb(var(--neutral-900));
            margin-bottom: 4px;
        }

        .bd-form-subtitle {
            font-size: 13px;
            color: rgb(var(--neutral-600));
        }

        /* Panel de auto-rellenado para administradores */
        .bd-admin-panel {
            background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
            color: white;
            padding: 10px 15px;
            border-radius: 10px;
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 12px rgba(14, 165, 233, 0.3);
        }

        .bd-admin-panel-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .bd-admin-panel-title {
            font-size: 12px;
            font-weight: 600;
            opacity: 0.95;
        }

        .bd-admin-panel-subtitle {
            font-size: 10px;
            opacity: 0.85;
        }

        .bd-admin-autofill-btn {
            padding: 8px 16px;
            background: white;
            color: #0ea5e9;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 12px;
            transition: all 0.2s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        }

        .bd-admin-autofill-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        /* Navegación modernizada */
        .bd-navigation {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            padding: 6px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .bd-nav-item {
            flex: 1;
            padding: 10px 16px;
            text-align: center;
            background: #f8f9fa;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            color: rgb(var(--neutral-700));
            font-weight: 500;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            border: 2px solid transparent;
        }

        .bd-nav-item i {
            font-size: 14px;
        }

        .bd-nav-item.active {
            background: linear-gradient(135deg, rgb(var(--primary)) 0%, rgb(var(--primary-dark)) 100%);
            color: white;
            border-color: rgb(var(--primary));
            box-shadow: 0 4px 12px rgba(var(--primary), 0.3);
        }

        .bd-nav-item:hover:not(.active) {
            background: #e9ecef;
            border-color: rgb(var(--primary-light));
        }

        /* Páginas del formulario */
        .bd-form-page {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        }

        .bd-form-page.hidden {
            display: none;
        }

        .bd-form-page h3 {
            font-size: 18px;
            font-weight: 600;
            color: rgb(var(--neutral-900));
            margin: 0 0 20px 0;
        }

        /* Inputs mejorados */
        .bd-input-group {
            margin-bottom: 18px;
        }

        .bd-input-group label {
            display: block;
            font-weight: 500;
            margin-bottom: 7px;
            color: rgb(var(--neutral-800));
            font-size: 14px;
        }

        .bd-input-group input[type="text"],
        .bd-input-group input[type="email"],
        .bd-input-group input[type="tel"],
        .bd-input-group input[type="file"],
        .bd-input-group select {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid rgb(var(--neutral-300));
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.2s ease;
            background: white;
        }

        .bd-input-group input:focus,
        .bd-input-group select:focus {
            outline: none;
            border-color: rgb(var(--primary));
            box-shadow: 0 0 0 3px rgba(var(--primary), 0.1);
        }

        /* Grid para inputs en 2 columnas */
        .bd-inputs-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 18px;
        }

        /* Upload section */
        .bd-upload-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }

        .bd-upload-item {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 12px;
            border: 2px dashed rgb(var(--neutral-300));
            transition: all 0.3s ease;
        }

        .bd-upload-item:hover {
            border-color: rgb(var(--primary));
            background: rgba(var(--primary), 0.02);
        }

        .bd-upload-item label {
            display: block;
            font-weight: 600;
            margin-bottom: 12px;
            color: rgb(var(--neutral-800));
            font-size: 15px;
        }

        .bd-upload-item input[type="file"] {
            width: 100%;
            padding: 6px;
            border: none;
            background: white;
            border-radius: 6px;
            font-size: 11px;
        }

        .bd-upload-item .view-example {
            display: inline-block;
            margin-top: 4px;
            color: rgb(var(--primary));
            text-decoration: none;
            font-size: 11px;
            font-weight: 500;
        }

        .bd-upload-item .view-example:hover {
            text-decoration: underline;
        }

        /* Área de firma */
        .bd-signature-area {
            margin: 20px 0;
            text-align: center;
        }

        .bd-signature-label {
            font-size: 14px;
            font-weight: 600;
            color: rgb(var(--neutral-700));
            margin-bottom: 12px;
        }

        .bd-signature-container {
            position: relative;
            display: inline-block;
        }

        #signature-pad {
            border: 3px solid rgb(var(--primary));
            border-radius: 8px;
            width: 100%;
            max-width: 400px;
            height: 180px;
            cursor: crosshair;
            background: white;
            box-shadow: 0 2px 8px rgba(var(--primary), 0.15);
        }

        .bd-signature-line {
            position: absolute;
            bottom: 40px;
            left: 50%;
            transform: translateX(-50%);
            width: 80%;
            height: 2px;
            background: rgba(var(--neutral-400), 0.5);
            pointer-events: none;
        }

        .bd-signature-text {
            position: absolute;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 12px;
            color: rgb(var(--neutral-500));
            pointer-events: none;
        }

        .bd-signature-controls {
            margin-top: 15px;
            display: flex;
            gap: 10px;
            justify-content: center;
        }

        .bd-signature-btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            font-size: 13px;
            transition: all 0.2s ease;
        }

        .bd-signature-btn.clear {
            background: #f8f9fa;
            color: rgb(var(--neutral-700));
            border: 1px solid rgb(var(--neutral-300));
        }

        .bd-signature-btn.clear:hover {
            background: #e9ecef;
        }

        .bd-signature-btn.mobile-expand {
            background: rgb(var(--primary));
            color: white;
            display: none;
        }

        /* Cupón de descuento */
        .bd-coupon-section {
            background: linear-gradient(135deg, #fef3cd 0%, #fff3cd 100%);
            border: 2px solid #fec107;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
        }

        .bd-coupon-title {
            font-size: 16px;
            font-weight: 600;
            color: #856404;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .bd-coupon-row {
            display: flex;
            gap: 10px;
            align-items: end;
        }

        .bd-coupon-input {
            flex: 1;
        }

        .bd-coupon-btn {
            padding: 12px 20px;
            background: #ffc107;
            color: #212529;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .bd-coupon-btn:hover {
            background: #e0a800;
        }

        .bd-coupon-message {
            margin-top: 10px;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
        }

        .bd-coupon-message.success {
            background: #d1edff;
            color: #0c5460;
            border: 1px solid #b3d7ff;
        }

        .bd-coupon-message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Dirección de facturación */
        .bd-billing-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            border: 2px solid rgb(var(--neutral-200));
        }

        .bd-billing-title {
            font-size: 16px;
            font-weight: 600;
            color: rgb(var(--neutral-800));
            margin-bottom: 15px;
        }

        .bd-checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 15px;
        }

        .bd-checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: rgb(var(--primary));
        }

        .bd-checkbox-group label {
            font-size: 14px;
            color: rgb(var(--neutral-700));
            margin: 0;
        }

        /* Botones de navegación */
        .bd-nav-buttons {
            display: flex;
            gap: 15px;
            justify-content: space-between;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid rgb(var(--neutral-200));
        }

        .bd-btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .bd-btn.primary {
            background: rgb(var(--primary));
            color: white;
        }

        .bd-btn.primary:hover {
            background: rgb(var(--primary-dark));
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(var(--primary), 0.3);
        }

        .bd-btn.secondary {
            background: #f8f9fa;
            color: rgb(var(--neutral-700));
            border: 2px solid rgb(var(--neutral-300));
        }

        .bd-btn.secondary:hover {
            background: #e9ecef;
            border-color: rgb(var(--neutral-400));
        }

        /* Modal de firma fullscreen */
        .bd-signature-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            z-index: 999999;
            justify-content: center;
            align-items: center;
        }

        .bd-signature-modal-content {
            background: white;
            border-radius: 12px;
            padding: 30px;
            width: 90%;
            max-width: 800px;
            max-height: 90%;
            overflow-y: auto;
            position: relative;
        }

        .bd-signature-modal-title {
            font-size: 20px;
            font-weight: 600;
            color: rgb(var(--neutral-900));
            margin-bottom: 20px;
            text-align: center;
        }

        #signature-pad-large {
            width: 100%;
            height: 300px;
            border: 3px solid rgb(var(--primary));
            border-radius: 8px;
            background: white;
            cursor: crosshair;
        }

        .bd-modal-controls {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 20px;
        }

        /* Modal de pago */
        .bd-payment-modal {
            display: none;
            position: fixed;
            top: 125px;
            left: 0;
            width: 100%;
            height: calc(100% - 125px);
            background: rgba(0, 0, 0, 0.8);
            z-index: 10000;
            justify-content: center;
            align-items: flex-start;
            padding: 20px;
            overflow-y: auto;
        }

        .bd-payment-modal-content {
            background: white;
            border-radius: 12px;
            padding: 25px;
            width: 100%;
            max-width: 500px;
            margin-top: 20px;
        }

        .bd-payment-title {
            font-size: 18px;
            font-weight: 600;
            color: rgb(var(--neutral-900));
            margin-bottom: 20px;
            text-align: center;
        }

        #payment-element {
            margin-bottom: 20px;
        }

        .bd-payment-button {
            width: 100%;
            padding: 15px;
            background: rgb(var(--primary));
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .bd-payment-button:hover {
            background: rgb(var(--primary-dark));
        }

        .bd-payment-button:disabled {
            background: rgb(var(--neutral-400));
            cursor: not-allowed;
        }

        .bd-loading-spinner {
            display: none;
            text-align: center;
            padding: 20px;
        }

        .bd-spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid rgb(var(--primary));
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            margin: 0 auto 10px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Resumen de compra */
        .bd-summary-box {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            border: 2px solid rgb(var(--neutral-200));
            margin: 20px 0;
        }

        .bd-summary-title {
            font-size: 16px;
            font-weight: 600;
            color: rgb(var(--neutral-800));
            margin-bottom: 15px;
        }

        .bd-summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .bd-summary-row.total {
            font-weight: 600;
            font-size: 16px;
            color: rgb(var(--primary));
            border-top: 2px solid rgb(var(--neutral-300));
            padding-top: 8px;
            margin-top: 10px;
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .bd-container {
                grid-template-columns: 1fr;
                margin: 10px;
            }

            .bd-sidebar {
                height: auto;
                position: static;
            }

            .bd-form-area {
                padding: 20px;
            }

            .bd-inputs-row {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .bd-upload-grid {
                grid-template-columns: 1fr;
            }

            .bd-nav-buttons {
                flex-direction: column;
            }

            .bd-coupon-row {
                flex-direction: column;
                gap: 10px;
            }

            .bd-signature-btn.mobile-expand {
                display: inline-block;
            }

            #signature-pad {
                display: none;
            }

            .bd-payment-modal {
                top: 0;
                height: 100%;
            }

            .bd-payment-modal-content {
                margin-top: 0;
            }
        }

        /* Ocultar WhatsApp Ninja durante firma */
        .bd-signature-modal.active ~ .wpfront-notification-bar,
        .bd-signature-modal.active ~ #wpfront-notification-bar,
        .bd-signature-modal.active ~ [class*="whatsapp"],
        .bd-signature-modal.active ~ [id*="whatsapp"] {
            display: none !important;
        }
    </style>

    <?php if (current_user_can('administrator')): ?>
    <!-- Panel de auto-rellenado para administradores -->
    <div class="bd-admin-panel">
        <div class="bd-admin-panel-info">
            <div class="bd-admin-panel-title">🔧 MODO ADMINISTRADOR</div>
            <div class="bd-admin-panel-subtitle">Rellena automáticamente todos los campos y llega hasta el resumen</div>
        </div>
        <button type="button" id="admin-autofill-btn" class="bd-admin-autofill-btn">
            ⚡ Auto-rellenar Formulario
        </button>
    </div>
    <?php endif; ?>

    <div class="bd-container">
        <!-- SIDEBAR IZQUIERDO -->
        <div class="bd-sidebar">
            <div class="bd-logo">
                <i class="fas fa-anchor"></i>
                Tramitfy
            </div>

            <!-- Contenido por defecto del sidebar -->
            <div id="sidebar-default">
                <div class="bd-headline">Baja de Embarcación</div>
                <div class="bd-subheadline">Gestiona la baja definitiva de tu embarcación de recreo de forma rápida y segura</div>

                <div class="bd-price-box">
                    <div class="bd-price-label">Precio Total</div>
                    <div class="bd-price-amount">95€</div>
                    <div class="bd-price-detail">Tasas y honorarios incluidos</div>
                </div>

                <div class="bd-benefits">
                    <div class="bd-benefit">
                        <i class="fas fa-check"></i>
                        <span>Tramitación completa ante las autoridades</span>
                    </div>
                    <div class="bd-benefit">
                        <i class="fas fa-check"></i>
                        <span>Documentación oficial de baja</span>
                    </div>
                    <div class="bd-benefit">
                        <i class="fas fa-check"></i>
                        <span>Gestión de tasas oficiales</span>
                    </div>
                    <div class="bd-benefit">
                        <i class="fas fa-check"></i>
                        <span>Seguimiento en tiempo real</span>
                    </div>
                    <div class="bd-benefit">
                        <i class="fas fa-check"></i>
                        <span>Soporte especializado</span>
                    </div>
                </div>

                <div class="bd-trust-badges">
                    <div class="bd-badge">
                        <i class="fas fa-shield-alt"></i>
                        Seguro
                    </div>
                    <div class="bd-badge">
                        <i class="fas fa-clock"></i>
                        Rápido
                    </div>
                    <div class="bd-badge">
                        <i class="fas fa-certificate"></i>
                        Oficial
                    </div>
                </div>
            </div>

            <!-- Contenido para página de documentación -->
            <div id="sidebar-authorization" style="display: none;">
                <div class="bd-headline">Documento de Autorización</div>
                <div class="bd-subheadline">Autorización para la tramitación de baja de embarcación</div>

                <div class="bd-sidebar-auth-doc">
                    <div style="background: rgba(255, 255, 255, 0.1); padding: 15px; border-radius: 8px; font-size: 13px; line-height: 1.5;">
                        <strong>AUTORIZACIÓN LEGAL</strong><br><br>
                        Yo, <span id="sidebar-customer-name">______</span>, con DNI <span id="sidebar-customer-dni">______</span>, autorizo expresamente a Tramitfy S.L. (CIF B55388557) para realizar en mi nombre todos los trámites necesarios para la baja definitiva de mi embarcación de recreo.
                        <br><br>
                        Esta autorización incluye la representación ante las autoridades marítimas competentes y la gestión de toda la documentación requerida.
                    </div>

                    <div style="background: rgba(255, 255, 255, 0.1); padding: 12px; border-radius: 8px; font-size: 12px; text-align: center;">
                        <i class="fas fa-signature" style="font-size: 16px; margin-bottom: 5px;"></i><br>
                        <strong>Firma Digital Requerida</strong><br>
                        Su firma digital tiene validez legal
                    </div>
                </div>
            </div>
        </div>

        <!-- ÁREA PRINCIPAL DEL FORMULARIO -->
        <div class="bd-form-area">
            <div class="bd-form-header">
                <h2 class="bd-form-title">Baja de Embarcación de Recreo</h2>
                <p class="bd-form-subtitle">Complete el formulario para iniciar el proceso de baja definitiva</p>
            </div>

            <!-- Navegación entre páginas -->
            <div class="bd-navigation">
                <a href="#" class="bd-nav-item active" data-page="page-personal">
                    <i class="fas fa-user"></i>
                    Datos Personales
                </a>
                <a href="#" class="bd-nav-item" data-page="page-documents">
                    <i class="fas fa-file-alt"></i>
                    Documentación
                </a>
                <a href="#" class="bd-nav-item" data-page="page-payment">
                    <i class="fas fa-credit-card"></i>
                    Pago
                </a>
            </div>

            <form id="boat-deregistration-form" method="post" enctype="multipart/form-data">
                <!-- PÁGINA 1: DATOS PERSONALES -->
                <div id="page-personal" class="bd-form-page">
                    <h3>Datos del Propietario</h3>

                    <div class="bd-inputs-row">
                        <div class="bd-input-group">
                            <label for="customer_name">Nombre Completo *</label>
                            <input type="text" id="customer_name" name="customer_name" required>
                        </div>
                        <div class="bd-input-group">
                            <label for="customer_dni">DNI/NIE *</label>
                            <input type="text" id="customer_dni" name="customer_dni" required>
                        </div>
                    </div>

                    <div class="bd-inputs-row">
                        <div class="bd-input-group">
                            <label for="customer_email">Email *</label>
                            <input type="email" id="customer_email" name="customer_email" required>
                        </div>
                        <div class="bd-input-group">
                            <label for="customer_phone">Teléfono *</label>
                            <input type="tel" id="customer_phone" name="customer_phone" required>
                        </div>
                    </div>

                    <div class="bd-input-group">
                        <label for="deregistration_type">Tipo de Baja *</label>
                        <select id="deregistration_type" name="deregistration_type" required>
                            <option value="">Seleccione una opción</option>
                            <option value="siniestro">Baja definitiva por siniestro</option>
                            <option value="exportacion">Baja definitiva por exportación</option>
                        </select>
                    </div>

                    <!-- Campo para Datos del Taller (solo si es siniestro) -->
                    <div id="workshop-data-section" class="bd-input-group" style="display: none;">
                        <label for="workshop_data">Datos del Taller</label>
                        <input type="text" id="workshop_data" name="workshop_data" placeholder="Nombre del taller o enlace de información">
                    </div>

                    <!-- Cupón de descuento -->
                    <div class="bd-coupon-section">
                        <div class="bd-coupon-title">
                            <i class="fas fa-tag"></i>
                            ¿Tienes un cupón de descuento?
                        </div>
                        <div class="bd-coupon-row">
                            <div class="bd-coupon-input">
                                <label for="coupon_code">Código de cupón</label>
                                <input type="text" id="coupon_code" name="coupon_code" placeholder="Introduce tu código">
                            </div>
                            <button type="button" id="apply-coupon-btn" class="bd-coupon-btn">Aplicar</button>
                        </div>
                        <div id="coupon-message" class="bd-coupon-message" style="display: none;"></div>
                    </div>

                    <!-- Dirección de facturación -->
                    <div class="bd-billing-section">
                        <div class="bd-billing-title">Dirección de Facturación</div>

                        <div class="bd-checkbox-group">
                            <input type="checkbox" id="same_address" name="same_address" checked>
                            <label for="same_address">Usar mis datos personales para la facturación</label>
                        </div>

                        <div id="billing-fields" style="display: none;">
                            <div class="bd-input-group">
                                <label for="billing_address">Dirección</label>
                                <input type="text" id="billing_address" name="billing_address">
                            </div>
                            <div class="bd-inputs-row">
                                <div class="bd-input-group">
                                    <label for="billing_postal_code">Código Postal</label>
                                    <input type="text" id="billing_postal_code" name="billing_postal_code">
                                </div>
                                <div class="bd-input-group">
                                    <label for="billing_city">Ciudad</label>
                                    <input type="text" id="billing_city" name="billing_city">
                                </div>
                            </div>
                            <div class="bd-input-group">
                                <label for="billing_province">Provincia</label>
                                <input type="text" id="billing_province" name="billing_province">
                            </div>
                        </div>
                    </div>

                    <div class="bd-nav-buttons">
                        <div></div>
                        <button type="button" class="bd-btn primary" onclick="showPage('page-documents')">
                            Continuar <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <!-- PÁGINA 2: DOCUMENTACIÓN -->
                <div id="page-documents" class="bd-form-page hidden">
                    <h3>Documentación Requerida</h3>

                    <div class="bd-upload-grid">
                        <div class="bd-upload-item">
                            <label for="dni_file">DNI/NIE del Propietario *</label>
                            <input type="file" id="dni_file" name="dni_file[]" accept=".jpg,.jpeg,.png,.pdf" multiple required>
                            <a href="https://tramitfy.es/wp-content/uploads/2024/12/ejemplo-dni.jpg" target="_blank" class="view-example">Ver ejemplo</a>
                        </div>

                        <div class="bd-upload-item">
                            <label for="registration_file">Registro Marítimo de la Embarcación *</label>
                            <input type="file" id="registration_file" name="registration_file[]" accept=".jpg,.jpeg,.png,.pdf" multiple required>
                            <a href="https://tramitfy.es/wp-content/uploads/2024/12/ejemplo-registro-maritimo.jpg" target="_blank" class="view-example">Ver ejemplo</a>
                        </div>
                    </div>

                    <!-- Área de firma -->
                    <div class="bd-signature-area">
                        <div class="bd-signature-label">Firma Digital *</div>
                        <div class="bd-signature-container">
                            <canvas id="signature-pad"></canvas>
                            <div class="bd-signature-line"></div>
                            <div class="bd-signature-text">FIRME AQUÍ</div>
                        </div>
                        <div class="bd-signature-controls">
                            <button type="button" class="bd-signature-btn clear" onclick="clearSignature()">
                                <i class="fas fa-eraser"></i> Borrar
                            </button>
                            <button type="button" class="bd-signature-btn mobile-expand" onclick="openSignatureModal()">
                                <i class="fas fa-expand"></i> Ampliar
                            </button>
                        </div>
                    </div>

                    <div class="bd-nav-buttons">
                        <button type="button" class="bd-btn secondary" onclick="showPage('page-personal')">
                            <i class="fas fa-arrow-left"></i> Anterior
                        </button>
                        <button type="button" class="bd-btn primary" onclick="showPage('page-payment')">
                            Continuar <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <!-- PÁGINA 3: PAGO -->
                <div id="page-payment" class="bd-form-page hidden">
                    <h3>Resumen y Pago</h3>

                    <div class="bd-summary-box">
                        <div class="bd-summary-title">Resumen de la Solicitud</div>
                        <div class="bd-summary-row">
                            <span>Servicio:</span>
                            <span id="summary-service">Baja de embarcación</span>
                        </div>
                        <div class="bd-summary-row">
                            <span>Precio base:</span>
                            <span>95,00 €</span>
                        </div>
                        <div class="bd-summary-row" id="discount-row" style="display: none;">
                            <span>Descuento:</span>
                            <span id="discount-amount">-0,00 €</span>
                        </div>
                        <div class="bd-summary-row total">
                            <span>Total a pagar:</span>
                            <span id="total-amount">95,00 €</span>
                        </div>
                    </div>

                    <div style="margin: 30px 0; padding: 20px; background: #f0f9ff; border: 2px solid #0ea5e9; border-radius: 10px; text-align: center;">
                        <p style="margin: 0; font-size: 14px; color: #0c5460;">
                            <i class="fas fa-info-circle" style="margin-right: 8px;"></i>
                            Al hacer clic en "Realizar Pago Seguro" se abrirá una ventana de pago seguro con Stripe
                        </p>
                    </div>

                    <div class="bd-nav-buttons">
                        <button type="button" class="bd-btn secondary" onclick="showPage('page-documents')">
                            <i class="fas fa-arrow-left"></i> Anterior
                        </button>
                        <button type="button" id="pay-button" class="bd-btn primary">
                            <i class="fas fa-lock"></i> Realizar Pago Seguro
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal de firma fullscreen -->
    <div id="signature-modal" class="bd-signature-modal">
        <div class="bd-signature-modal-content">
            <div class="bd-signature-modal-title">Firma Digital</div>
            <canvas id="signature-pad-large"></canvas>
            <div class="bd-modal-controls">
                <button type="button" class="bd-btn secondary" onclick="clearLargeSignature()">
                    <i class="fas fa-eraser"></i> Borrar
                </button>
                <button type="button" class="bd-btn primary" onclick="confirmSignature()">
                    <i class="fas fa-check"></i> Confirmar Firma
                </button>
            </div>
        </div>
    </div>

    <!-- Modal de pago -->
    <div id="payment-modal" class="bd-payment-modal">
        <div class="bd-payment-modal-content">
            <div class="bd-loading-spinner" id="loading-spinner">
                <div class="bd-spinner"></div>
                <p>Cargando pasarela de pago...</p>
            </div>

            <div id="payment-content" style="display: none;">
                <div class="bd-payment-title">Pago Seguro</div>
                <div id="payment-element"></div>
                <button id="submit-payment" class="bd-payment-button">
                    Confirmar Pago
                </button>
                <div id="payment-messages"></div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Variables globales
            let stripe = null;
            let elements = null;
            let signaturePad = null;
            let signaturePadLarge = null;
            let currentDiscount = 0;
            let currentTotal = 95.00;

            // Inicializar Stripe
            function initializeStripe() {
                stripe = Stripe('<?php echo $stripe_public_key; ?>');
            }

            // Inicializar signature pads
            function initializeSignaturePads() {
                // Signature pad principal (oculto en móvil)
                const canvas = document.getElementById('signature-pad');
                if (canvas) {
                    // Ajustar tamaño del canvas
                    function resizeCanvas() {
                        const ratio = Math.max(window.devicePixelRatio || 1, 1);
                        const rect = canvas.getBoundingClientRect();
                        canvas.width = rect.width * ratio;
                        canvas.height = rect.height * ratio;
                        canvas.getContext('2d').scale(ratio, ratio);
                        canvas.style.width = rect.width + 'px';
                        canvas.style.height = rect.height + 'px';
                    }

                    setTimeout(resizeCanvas, 100);
                    window.addEventListener('resize', resizeCanvas);

                    signaturePad = new SignaturePad(canvas, {
                        backgroundColor: 'rgb(255, 255, 255)',
                        penColor: 'rgb(0, 0, 0)',
                        minWidth: 0.8,
                        maxWidth: 3.5
                    });
                }

                // Signature pad grande para modal
                const canvasLarge = document.getElementById('signature-pad-large');
                if (canvasLarge) {
                    signaturePadLarge = new SignaturePad(canvasLarge, {
                        backgroundColor: 'rgb(255, 255, 255)',
                        penColor: 'rgb(0, 0, 0)',
                        minWidth: 1.0,
                        maxWidth: 4.0
                    });
                }
            }

            // Funciones de navegación
            window.showPage = function(pageId) {
                // Ocultar todas las páginas
                const pages = document.querySelectorAll('.bd-form-page');
                pages.forEach(page => page.classList.add('hidden'));

                // Mostrar la página seleccionada
                document.getElementById(pageId).classList.remove('hidden');

                // Actualizar navegación
                const navItems = document.querySelectorAll('.bd-nav-item');
                navItems.forEach(item => item.classList.remove('active'));
                document.querySelector(`[data-page="${pageId}"]`).classList.add('active');

                // Actualizar sidebar
                updateSidebar(pageId);

                // Redimensionar canvas si es necesario
                if (pageId === 'page-documents' && signaturePad) {
                    setTimeout(() => {
                        const canvas = document.getElementById('signature-pad');
                        const ratio = Math.max(window.devicePixelRatio || 1, 1);
                        const rect = canvas.getBoundingClientRect();
                        canvas.width = rect.width * ratio;
                        canvas.height = rect.height * ratio;
                        canvas.getContext('2d').scale(ratio, ratio);
                        signaturePad.clear();
                    }, 100);
                }
            };

            // Actualizar contenido del sidebar
            function updateSidebar(pageId) {
                const defaultSidebar = document.getElementById('sidebar-default');
                const authSidebar = document.getElementById('sidebar-authorization');

                if (pageId === 'page-documents') {
                    // Mostrar sidebar de autorización
                    defaultSidebar.style.display = 'none';
                    authSidebar.style.display = 'block';

                    // Actualizar datos dinámicos
                    const customerName = document.getElementById('customer_name').value || '______';
                    const customerDni = document.getElementById('customer_dni').value || '______';

                    document.getElementById('sidebar-customer-name').textContent = customerName;
                    document.getElementById('sidebar-customer-dni').textContent = customerDni;
                } else {
                    // Mostrar sidebar por defecto
                    defaultSidebar.style.display = 'block';
                    authSidebar.style.display = 'none';
                }
            }

            // Navegación con clicks
            document.querySelectorAll('.bd-nav-item').forEach(item => {
                item.addEventListener('click', function(e) {
                    e.preventDefault();
                    const pageId = this.getAttribute('data-page');
                    showPage(pageId);
                });
            });

            // Mostrar/ocultar datos del taller
            document.getElementById('deregistration_type').addEventListener('change', function() {
                const workshopSection = document.getElementById('workshop-data-section');
                const workshopInput = document.getElementById('workshop_data');

                if (this.value === 'siniestro') {
                    workshopSection.style.display = 'block';
                    workshopInput.required = true;
                } else {
                    workshopSection.style.display = 'none';
                    workshopInput.required = false;
                    workshopInput.value = '';
                }

                // Actualizar resumen
                updateSummary();
            });

            // Mostrar/ocultar dirección de facturación
            document.getElementById('same_address').addEventListener('change', function() {
                const billingFields = document.getElementById('billing-fields');
                billingFields.style.display = this.checked ? 'none' : 'block';
            });

            // Sistema de cupones
            document.getElementById('apply-coupon-btn').addEventListener('click', function() {
                const couponCode = document.getElementById('coupon_code').value.trim().toUpperCase();
                const messageDiv = document.getElementById('coupon-message');

                const validCoupons = {
                    'DESCUENTO10': 10,
                    'DESCUENTO20': 20,
                    'VERANO15': 15,
                    'BLACK50': 50
                };

                if (validCoupons[couponCode]) {
                    const discountPercent = validCoupons[couponCode];
                    currentDiscount = (95.00 * discountPercent) / 100;
                    currentTotal = 95.00 - currentDiscount;

                    messageDiv.className = 'bd-coupon-message success';
                    messageDiv.textContent = `¡Cupón aplicado! Descuento del ${discountPercent}% (${currentDiscount.toFixed(2)}€)`;
                    messageDiv.style.display = 'block';

                    // Actualizar resumen
                    updateSummary();
                } else {
                    messageDiv.className = 'bd-coupon-message error';
                    messageDiv.textContent = 'Cupón no válido o expirado';
                    messageDiv.style.display = 'block';
                }
            });

            // Actualizar resumen
            function updateSummary() {
                const serviceType = document.getElementById('deregistration_type').value;
                let serviceText = 'Baja de embarcación';

                if (serviceType === 'siniestro') {
                    serviceText = 'Baja por siniestro';
                } else if (serviceType === 'exportacion') {
                    serviceText = 'Baja por exportación';
                }

                document.getElementById('summary-service').textContent = serviceText;

                if (currentDiscount > 0) {
                    document.getElementById('discount-row').style.display = 'flex';
                    document.getElementById('discount-amount').textContent = `-${currentDiscount.toFixed(2)} €`;
                } else {
                    document.getElementById('discount-row').style.display = 'none';
                }

                document.getElementById('total-amount').textContent = `${currentTotal.toFixed(2)} €`;
            }

            // Funciones de firma
            window.clearSignature = function() {
                if (signaturePad) {
                    signaturePad.clear();
                }
            };

            window.openSignatureModal = function() {
                const modal = document.getElementById('signature-modal');
                modal.style.display = 'flex';
                modal.classList.add('active');

                // Redimensionar canvas grande
                setTimeout(() => {
                    const canvas = document.getElementById('signature-pad-large');
                    const ratio = Math.max(window.devicePixelRatio || 1, 1);
                    canvas.width = canvas.offsetWidth * ratio;
                    canvas.height = canvas.offsetHeight * ratio;
                    canvas.getContext('2d').scale(ratio, ratio);

                    if (signaturePadLarge) {
                        signaturePadLarge.clear();
                    }
                }, 100);
            };

            window.clearLargeSignature = function() {
                if (signaturePadLarge) {
                    signaturePadLarge.clear();
                }
            };

            window.confirmSignature = function() {
                if (signaturePadLarge && !signaturePadLarge.isEmpty()) {
                    // Transferir firma al canvas pequeño
                    if (signaturePad) {
                        const dataURL = signaturePadLarge.toDataURL();
                        const img = new Image();
                        img.onload = function() {
                            signaturePad.clear();
                            const ctx = signaturePad._ctx;
                            ctx.drawImage(img, 0, 0, signaturePad.canvas.width, signaturePad.canvas.height);
                        };
                        img.src = dataURL;
                    }

                    // Cerrar modal
                    const modal = document.getElementById('signature-modal');
                    modal.style.display = 'none';
                    modal.classList.remove('active');
                } else {
                    alert('Por favor, realice su firma antes de confirmar.');
                }
            };

            // Cerrar modal con click fuera
            document.getElementById('signature-modal').addEventListener('click', function(e) {
                if (e.target === this) {
                    this.style.display = 'none';
                    this.classList.remove('active');
                }
            });

            // Sistema de pago
            document.getElementById('pay-button').addEventListener('click', function() {
                // Validaciones básicas
                const requiredFields = ['customer_name', 'customer_dni', 'customer_email', 'customer_phone', 'deregistration_type'];
                let isValid = true;

                for (const fieldId of requiredFields) {
                    const field = document.getElementById(fieldId);
                    if (!field.value.trim()) {
                        alert(`Por favor, complete el campo: ${field.previousElementSibling.textContent}`);
                        showPage('page-personal');
                        field.focus();
                        return;
                    }
                }

                // Validar archivos
                const dniFile = document.getElementById('dni_file');
                const registrationFile = document.getElementById('registration_file');

                if (!dniFile.files.length) {
                    alert('Por favor, suba el archivo del DNI/NIE');
                    showPage('page-documents');
                    return;
                }

                if (!registrationFile.files.length) {
                    alert('Por favor, suba el archivo del Registro Marítimo');
                    showPage('page-documents');
                    return;
                }

                // Validar firma
                if (!signaturePad || signaturePad.isEmpty()) {
                    alert('Por favor, proporcione su firma digital');
                    showPage('page-documents');
                    return;
                }

                // Abrir modal de pago
                openPaymentModal();
            });

            async function openPaymentModal() {
                const modal = document.getElementById('payment-modal');
                const loadingSpinner = document.getElementById('loading-spinner');
                const paymentContent = document.getElementById('payment-content');

                modal.style.display = 'flex';
                loadingSpinner.style.display = 'block';
                paymentContent.style.display = 'none';

                try {
                    // Crear Payment Intent
                    const response = await fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: new URLSearchParams({
                            action: 'create_payment_intent_boat_deregistration',
                            amount: Math.round(currentTotal * 100), // Convertir a centavos
                            customer_email: document.getElementById('customer_email').value,
                            customer_name: document.getElementById('customer_name').value
                        })
                    });

                    const data = await response.json();

                    if (data.success) {
                        const clientSecret = data.client_secret;

                        // Configurar Stripe Elements
                        elements = stripe.elements({
                            clientSecret: clientSecret,
                            appearance: {
                                theme: 'stripe',
                                variables: {
                                    colorPrimary: '#016d86',
                                }
                            }
                        });

                        const paymentElement = elements.create('payment');
                        paymentElement.mount('#payment-element');

                        loadingSpinner.style.display = 'none';
                        paymentContent.style.display = 'block';

                        // Manejar envío del pago
                        document.getElementById('submit-payment').addEventListener('click', handlePaymentSubmit);

                    } else {
                        throw new Error(data.message || 'Error al crear el pago');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    alert('Error al inicializar el pago: ' + error.message);
                    modal.style.display = 'none';
                }
            }

            async function handlePaymentSubmit() {
                const submitButton = document.getElementById('submit-payment');
                const messagesDiv = document.getElementById('payment-messages');

                submitButton.disabled = true;
                submitButton.textContent = 'Procesando...';

                try {
                    const { error } = await stripe.confirmPayment({
                        elements,
                        confirmParams: {
                            return_url: window.location.href + '?payment=success'
                        },
                        redirect: 'if_required'
                    });

                    if (error) {
                        // Mostrar error
                        messagesDiv.innerHTML = `<div style="color: #e74c3c; margin-top: 10px;">${error.message}</div>`;
                        submitButton.disabled = false;
                        submitButton.textContent = 'Confirmar Pago';
                    } else {
                        // Pago exitoso, enviar formulario
                        await submitForm();
                    }
                } catch (error) {
                    console.error('Error en el pago:', error);
                    messagesDiv.innerHTML = `<div style="color: #e74c3c; margin-top: 10px;">Error inesperado. Inténtelo de nuevo.</div>`;
                    submitButton.disabled = false;
                    submitButton.textContent = 'Confirmar Pago';
                }
            }

            async function submitForm() {
                try {
                    const formData = new FormData();

                    // Datos del formulario
                    formData.append('customer_name', document.getElementById('customer_name').value);
                    formData.append('customer_dni', document.getElementById('customer_dni').value);
                    formData.append('customer_email', document.getElementById('customer_email').value);
                    formData.append('customer_phone', document.getElementById('customer_phone').value);
                    formData.append('deregistration_type', document.getElementById('deregistration_type').value);
                    formData.append('workshop_data', document.getElementById('workshop_data').value);
                    formData.append('coupon_code', document.getElementById('coupon_code').value);
                    formData.append('finalAmount', currentTotal);
                    formData.append('discountAmount', currentDiscount);

                    // Dirección de facturación
                    formData.append('same_address', document.getElementById('same_address').checked ? '1' : '0');
                    formData.append('billing_address', document.getElementById('billing_address').value);
                    formData.append('billing_city', document.getElementById('billing_city').value);
                    formData.append('billing_postal_code', document.getElementById('billing_postal_code').value);
                    formData.append('billing_province', document.getElementById('billing_province').value);

                    // Archivos
                    const dniFiles = document.getElementById('dni_file').files;
                    for (let i = 0; i < dniFiles.length; i++) {
                        formData.append('dni_file[]', dniFiles[i]);
                    }

                    const registrationFiles = document.getElementById('registration_file').files;
                    for (let i = 0; i < registrationFiles.length; i++) {
                        formData.append('registration_file[]', registrationFiles[i]);
                    }

                    // Firma
                    if (signaturePad && !signaturePad.isEmpty()) {
                        const signatureData = signaturePad.toDataURL();
                        formData.append('signature', signatureData);
                    }

                    // Enviar a la API de Tramitfy
                    const response = await fetch('https://46-202-128-35.sslip.io/api/herramientas/forms/baja-embarcacion', {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();

                    if (result.success) {
                        alert('¡Formulario enviado exitosamente! Se le enviará un email de confirmación.');
                        window.location.href = `https://46-202-128-35.sslip.io/seguimiento/${result.id}`;
                    } else {
                        throw new Error(result.message || 'Error al enviar el formulario');
                    }

                } catch (error) {
                    console.error('Error al enviar:', error);
                    alert('Error al enviar el formulario: ' + error.message);
                }
            }

            // Cerrar modal de pago
            document.getElementById('payment-modal').addEventListener('click', function(e) {
                if (e.target === this) {
                    this.style.display = 'none';
                }
            });

            <?php if (current_user_can('administrator')): ?>
            // Auto-rellenado para administradores
            document.getElementById('admin-autofill-btn').addEventListener('click', function() {
                alert('Iniciando auto-rellenado del formulario...');

                // Rellenar datos personales
                document.getElementById('customer_name').value = 'Juan Pérez Administrador';
                document.getElementById('customer_dni').value = '12345678Z';
                document.getElementById('customer_email').value = 'joanpinyol@hotmail.es';
                document.getElementById('customer_phone').value = '682246937';
                document.getElementById('deregistration_type').value = 'siniestro';
                document.getElementById('workshop_data').value = 'Taller Náutico Ejemplo S.L.';

                // Trigger eventos
                document.getElementById('deregistration_type').dispatchEvent(new Event('change'));

                // Simular firma después de un delay
                setTimeout(() => {
                    if (signaturePad) {
                        signaturePad.clear();
                        const ctx = signaturePad._ctx;
                        ctx.font = '24px cursive';
                        ctx.fillStyle = '#000000';
                        ctx.fillText('Juan Pérez', 50, 90);
                    }
                }, 500);

                // Navegar automáticamente
                setTimeout(() => {
                    showPage('page-documents');
                    setTimeout(() => {
                        showPage('page-payment');
                        alert('Formulario auto-rellenado. Los archivos deben subirse manualmente y el pago se procesa con Stripe.');
                    }, 1000);
                }, 1000);
            });
            <?php endif; ?>

            // Inicialización
            initializeStripe();
            setTimeout(initializeSignaturePads, 100);
            updateSummary();
        });
    </script>

    <?php
    return ob_get_clean();
}

add_shortcode('boat_deregistration_form', 'boat_deregistration_form_shortcode');

// AJAX handler para crear Payment Intent
add_action('wp_ajax_create_payment_intent_boat_deregistration', 'create_payment_intent_boat_deregistration');
add_action('wp_ajax_nopriv_create_payment_intent_boat_deregistration', 'create_payment_intent_boat_deregistration');

function create_payment_intent_boat_deregistration() {
    global $stripe_secret_key;

    try {
        require_once get_template_directory() . '/vendor/stripe/init.php';

        \Stripe\Stripe::setApiKey($stripe_secret_key);

        $amount = intval($_POST['amount']); // En centavos
        $customer_email = sanitize_email($_POST['customer_email']);
        $customer_name = sanitize_text_field($_POST['customer_name']);

        $intent = \Stripe\PaymentIntent::create([
            'amount' => $amount,
            'currency' => 'eur',
            'description' => 'Baja de Embarcación de Recreo - ' . $customer_name,
            'receipt_email' => $customer_email,
            'metadata' => [
                'customer_name' => $customer_name,
                'service_type' => 'boat_deregistration'
            ]
        ]);

        wp_send_json_success([
            'client_secret' => $intent->client_secret,
            'payment_intent_id' => $intent->id
        ]);

    } catch (Exception $e) {
        wp_send_json_error(['message' => $e->getMessage()]);
    }
}

// Procesar el formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['customer_name'])) {

    // Obtener datos del formulario
    $customer_name = sanitize_text_field($_POST['customer_name']);
    $customer_dni = sanitize_text_field($_POST['customer_dni']);
    $customer_email = sanitize_email($_POST['customer_email']);
    $customer_phone = sanitize_text_field($_POST['customer_phone']);
    $deregistration_type = sanitize_text_field($_POST['deregistration_type']);
    $workshop_data = sanitize_text_field($_POST['workshop_data']);
    $coupon_used = sanitize_text_field($_POST['coupon_code']);

    // Dirección de facturación
    $same_address = isset($_POST['same_address']) && $_POST['same_address'] === '1';
    $billing_address = $same_address ? '' : sanitize_text_field($_POST['billing_address']);
    $billing_city = $same_address ? '' : sanitize_text_field($_POST['billing_city']);
    $billing_postal_code = $same_address ? '' : sanitize_text_field($_POST['billing_postal_code']);
    $billing_province = $same_address ? '' : sanitize_text_field($_POST['billing_province']);

    // Crear directorio de uploads
    $upload_dir = wp_upload_dir();
    $boat_deregistration_dir = $upload_dir['basedir'] . '/boat-deregistration-forms';
    if (!file_exists($boat_deregistration_dir)) {
        wp_mkdir_p($boat_deregistration_dir);
    }

    $customer_dir = $boat_deregistration_dir . '/' . sanitize_file_name($customer_name . '_' . $customer_dni . '_' . date('Ymd_His'));
    if (!file_exists($customer_dir)) {
        wp_mkdir_p($customer_dir);
    }

    // Procesar archivos subidos
    $uploaded_files = array();

    $file_fields = array(
        'dni_file' => 'DNI/NIE',
        'registration_file' => 'Registro Marítimo'
    );

    foreach ($file_fields as $field => $description) {
        if (isset($_FILES[$field]) && !empty($_FILES[$field]['name'][0])) {
            $files = $_FILES[$field];
            $uploaded_files[$field] = array();

            for ($i = 0; $i < count($files['name']); $i++) {
                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                    $filename = sanitize_file_name($files['name'][$i]);
                    $tmp_name = $files['tmp_name'][$i];
                    $destination = $customer_dir . '/' . $description . '_' . $filename;

                    if (move_uploaded_file($tmp_name, $destination)) {
                        $uploaded_files[$field][] = $destination;
                    }
                }
            }
        }
    }

    // Procesar firma digital
    if (isset($_POST['signature']) && !empty($_POST['signature'])) {
        $signature_data = $_POST['signature'];

        // Remover el prefijo data:image/png;base64,
        $signature_data = str_replace('data:image/png;base64,', '', $signature_data);
        $signature_data = str_replace(' ', '+', $signature_data);
        $signature_binary = base64_decode($signature_data);

        $signature_filename = $customer_dir . '/firma_digital.png';
        file_put_contents($signature_filename, $signature_binary);
        $uploaded_files['signature'] = $signature_filename;
    }

    // Generar factura en PDF
    $invoice_filename = generate_invoice_pdf(
        $customer_name,
        $customer_dni,
        $customer_email,
        $customer_phone,
        $deregistration_type,
        $workshop_data,
        $coupon_used,
        $customer_dir,
        $billing_address,
        $billing_city,
        $billing_postal_code,
        $billing_province
    );

    // Generar documento de autorización
    $authorization_filename = generate_authorization_pdf($customer_name, $customer_dni, $deregistration_type, $workshop_data, $customer_dir);

    // Enviar emails
    send_confirmation_emails($customer_name, $customer_dni, $customer_email, $customer_phone, $deregistration_type, $workshop_data, $coupon_used, $uploaded_files, $invoice_filename, $authorization_filename, $billing_address, $billing_city, $billing_postal_code, $billing_province);

    // Enviar datos a la API de Tramitfy
    $tramitfy_api_url = 'https://46-202-128-35.sslip.io/api/herramientas/forms/baja-embarcacion';

    $tramitfy_data = array(
        'customer_name' => $customer_name,
        'customer_dni' => $customer_dni,
        'customer_email' => $customer_email,
        'customer_phone' => $customer_phone,
        'deregistration_type' => $deregistration_type,
        'workshop_data' => $workshop_data,
        'coupon_used' => $coupon_used,
        'finalAmount' => floatval($_POST['finalAmount']),
        'discountAmount' => floatval($_POST['discountAmount']),
        'uploaded_files' => $uploaded_files,
        'invoice_filename' => $invoice_filename,
        'authorization_filename' => $authorization_filename,
        'timestamp' => date('Y-m-d H:i:s'),
        'billing_address' => $billing_address,
        'billing_city' => $billing_city,
        'billing_postal_code' => $billing_postal_code,
        'billing_province' => $billing_province
    );

    $response = wp_remote_post($tramitfy_api_url, array(
        'method' => 'POST',
        'timeout' => 45,
        'redirection' => 5,
        'httpversion' => '1.0',
        'blocking' => true,
        'headers' => array('Content-Type' => 'application/json'),
        'body' => json_encode($tramitfy_data),
        'cookies' => array()
    ));

    // Mostrar mensaje de éxito
    echo '<div style="max-width: 600px; margin: 40px auto; padding: 30px; background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%); border: 2px solid #28a745; border-radius: 15px; text-align: center; font-family: Arial, sans-serif;">';
    echo '<div style="font-size: 48px; color: #28a745; margin-bottom: 20px;"><i class="fas fa-check-circle"></i></div>';
    echo '<h2 style="color: #155724; margin-bottom: 15px; font-size: 24px;">¡Formulario Enviado Exitosamente!</h2>';
    echo '<p style="color: #155724; font-size: 16px; line-height: 1.6; margin-bottom: 25px;">Su solicitud de baja de embarcación ha sido recibida y procesada correctamente. En breve recibirá un email de confirmación con todos los detalles.</p>';
    echo '<div style="background: white; padding: 20px; border-radius: 10px; margin: 20px 0; border: 1px solid #c3e6cb;">';
    echo '<h3 style="color: #016d86; margin-bottom: 15px;">Próximos Pasos:</h3>';
    echo '<ul style="text-align: left; color: #155724; line-height: 1.8;">';
    echo '<li>Revisaremos su documentación</li>';
    echo '<li>Procesaremos la baja ante las autoridades competentes</li>';
    echo '<li>Le mantendremos informado del progreso</li>';
    echo '<li>Recibirá la documentación oficial una vez completado</li>';
    echo '</ul>';
    echo '</div>';
    echo '<p style="color: #155724; font-size: 14px; margin-top: 25px;"><strong>¿Preguntas?</strong> Contacte con nosotros en <a href="mailto:info@tramitfy.es" style="color: #016d86;">info@tramitfy.es</a></p>';
    echo '</div>';

    return;
}

/**
 * Función para generar el documento de autorización como PDF
 */
function generate_authorization_pdf($customer_name, $customer_dni, $deregistration_type, $workshop_data, $upload_dir) {
    require_once get_template_directory() . '/vendor/fpdf/fpdf.php';
    $pdf = new FPDF();
    $pdf->AddPage();

    // Colores corporativos
    $primary_color = array(1, 109, 134);
    $text_color = array(51, 51, 51);

    // Encabezado
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->SetTextColor($primary_color[0], $primary_color[1], $primary_color[2]);
    $pdf->Cell(0, 10, utf8_decode('Autorización para Baja de Embarcación de Recreo'), 0, 1, 'C');
    $pdf->Ln(10);

    $deregistration_type_text = ($deregistration_type === 'siniestro') ? 'siniestro' : 'exportación';
    $texto = "Yo, $customer_name, con DNI $customer_dni, autorizo a Tramitfy S.L. (CIF B55388557) a realizar en mi nombre los trámites necesarios para la baja definitiva por $deregistration_type_text.";
    if ($workshop_data) {
        $texto .= " En el taller: $workshop_data.";
    }

    $pdf->SetFont('Arial', '', 11);
    $pdf->SetTextColor($text_color[0], $text_color[1], $text_color[2]);
    $pdf->MultiCell(0, 6, utf8_decode($texto), 0, 'J');

    $pdf->Ln(10);
    $pdf->Cell(0, 6, 'Fecha: ' . date('d/m/Y'), 0, 1, 'L');
    $pdf->Ln(20);
    $pdf->Cell(0, 6, 'Firma: ____________________', 0, 1, 'R');

    // Guardar el PDF
    $auth_filename = 'autorizacion_baja_embarcacion_' . date('Ymd_His') . '.pdf';
    $auth_path = $upload_dir . '/' . $auth_filename;
    $pdf->Output('F', $auth_path);

    return $auth_filename;
}

/**
 * Función para enviar emails de confirmación
 */
function send_confirmation_emails($customer_name, $customer_dni, $customer_email, $customer_phone, $deregistration_type, $workshop_data, $coupon_used, $uploaded_files, $invoice_filename, $authorization_filename, $billing_address, $billing_city, $billing_postal_code, $billing_province) {

    // Email al cliente
    $subject_client = 'Confirmación de solicitud de baja de embarcación - Tramitfy';

    $deregistration_type_text = ($deregistration_type === 'siniestro') ? 'Baja definitiva por siniestro' : 'Baja definitiva por exportación';

    $message_client = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Confirmación de solicitud</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">

    <div style="background: linear-gradient(135deg, #016d86 0%, #014d61 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
        <h1 style="margin: 0; font-size: 28px;">¡Solicitud Recibida!</h1>
        <p style="margin: 10px 0 0; font-size: 16px; opacity: 0.9;">Su trámite está en proceso</p>
    </div>

    <div style="background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; border: 1px solid #e9ecef;">

        <h2 style="color: #016d86; margin-bottom: 20px;">Estimado/a ' . $customer_name . ',</h2>

        <p>Hemos recibido correctamente su solicitud de <strong>' . $deregistration_type_text . '</strong>.</p>

        <div style="background: white; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #016d86;">
            <h3 style="color: #016d86; margin-top: 0;">Datos de la solicitud:</h3>
            <ul style="list-style: none; padding: 0;">
                <li style="margin-bottom: 8px;"><strong>Nombre:</strong> ' . $customer_name . '</li>
                <li style="margin-bottom: 8px;"><strong>DNI:</strong> ' . $customer_dni . '</li>
                <li style="margin-bottom: 8px;"><strong>Email:</strong> ' . $customer_email . '</li>
                <li style="margin-bottom: 8px;"><strong>Teléfono:</strong> ' . $customer_phone . '</li>
                <li style="margin-bottom: 8px;"><strong>Tipo de baja:</strong> ' . $deregistration_type_text . '</li>';

    if (!empty($workshop_data)) {
        $message_client .= '<li style="margin-bottom: 8px;"><strong>Datos del taller:</strong> ' . $workshop_data . '</li>';
    }

    if (!empty($coupon_used)) {
        $message_client .= '<li style="margin-bottom: 8px;"><strong>Cupón utilizado:</strong> ' . $coupon_used . '</li>';
    }

    $message_client .= '
            </ul>
        </div>

        <div style="background: #e3f2fd; padding: 20px; border-radius: 8px; margin: 20px 0;">
            <h3 style="color: #0277bd; margin-top: 0; margin-bottom: 15px;">¿Qué sucede ahora?</h3>
            <ol style="color: #0277bd; padding-left: 20px;">
                <li style="margin-bottom: 10px;">Revisaremos toda su documentación</li>
                <li style="margin-bottom: 10px;">Iniciaremos los trámites ante las autoridades competentes</li>
                <li style="margin-bottom: 10px;">Le mantendremos informado del progreso</li>
                <li style="margin-bottom: 10px;">Recibirá la documentación oficial una vez completado</li>
            </ol>
        </div>

        <div style="text-align: center; margin: 30px 0;">
            <p style="color: #666; margin-bottom: 15px;">Para cualquier consulta, no dude en contactarnos:</p>
            <div style="background: #016d86; color: white; padding: 15px; border-radius: 8px; display: inline-block;">
                <p style="margin: 0;"><strong>📧 info@tramitfy.es</strong></p>
                <p style="margin: 5px 0 0;"><strong>📞 +34 689 170 273</strong></p>
            </div>
        </div>

        <hr style="border: none; border-top: 1px solid #dee2e6; margin: 30px 0;">

        <div style="text-align: center; color: #6c757d; font-size: 14px;">
            <p style="margin: 0;">Tramitfy S.L. - CIF: B55388557</p>
            <p style="margin: 5px 0 0;">Paseo de la Castellana 194 puerta B, Madrid</p>
            <p style="margin: 5px 0 0;"><a href="https://tramitfy.es" style="color: #016d86; text-decoration: none;">www.tramitfy.es</a></p>
        </div>

    </div>

</body>
</html>';

    // Enviar email al cliente
    $headers_client = array('Content-Type: text/html; charset=UTF-8');
    wp_mail($customer_email, $subject_client, $message_client, $headers_client);

    // Email al administrador
    $admin_email = get_option('admin_email');
    $subject_admin = 'Nuevo formulario de baja de embarcación de recreo enviado';

    $deregistration_type_text_2 = ($deregistration_type === 'siniestro') ? 'Baja definitiva por siniestro' : 'Baja definitiva por exportación';

    // [NUEVO - CUPÓN] Agregamos la fila del cupón al correo del admin
    $message_admin = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Nuevo formulario de baja de embarcación</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 700px; margin: 0 auto; padding: 20px;">

    <div style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; padding: 25px; text-align: center; border-radius: 10px 10px 0 0;">
        <h1 style="margin: 0; font-size: 24px;">🚨 Nuevo Formulario de Baja de Embarcación</h1>
        <p style="margin: 10px 0 0; font-size: 14px; opacity: 0.9;">Recibido el ' . date('d/m/Y H:i:s') . '</p>
    </div>

    <div style="background: #f8f9fa; padding: 25px; border-radius: 0 0 10px 10px; border: 1px solid #e9ecef;">

        <h2 style="color: #dc3545; margin-bottom: 20px;">Detalles del Cliente:</h2>

        <table style="width: 100%; border-collapse: collapse; margin-bottom: 25px;">
            <tr style="background: #e9ecef;">
                <td style="padding: 12px; border: 1px solid #dee2e6; font-weight: bold;">Nombre:</td>
                <td style="padding: 12px; border: 1px solid #dee2e6;">' . $customer_name . '</td>
            </tr>
            <tr>
                <td style="padding: 12px; border: 1px solid #dee2e6; font-weight: bold;">DNI:</td>
                <td style="padding: 12px; border: 1px solid #dee2e6;">' . $customer_dni . '</td>
            </tr>
            <tr style="background: #e9ecef;">
                <td style="padding: 12px; border: 1px solid #dee2e6; font-weight: bold;">Email:</td>
                <td style="padding: 12px; border: 1px solid #dee2e6;"><a href="mailto:' . $customer_email . '">' . $customer_email . '</a></td>
            </tr>
            <tr>
                <td style="padding: 12px; border: 1px solid #dee2e6; font-weight: bold;">Teléfono:</td>
                <td style="padding: 12px; border: 1px solid #dee2e6;"><a href="tel:' . $customer_phone . '">' . $customer_phone . '</a></td>
            </tr>
            <tr style="background: #e9ecef;">
                <td style="padding: 12px; border: 1px solid #dee2e6; font-weight: bold;">Tipo de baja:</td>
                <td style="padding: 12px; border: 1px solid #dee2e6;">' . $deregistration_type_text_2 . '</td>
            </tr>';

    if (!empty($workshop_data)) {
        $message_admin .= '
            <tr>
                <td style="padding: 12px; border: 1px solid #dee2e6; font-weight: bold;">Datos del taller:</td>
                <td style="padding: 12px; border: 1px solid #dee2e6;">' . $workshop_data . '</td>
            </tr>';
    }

    // [NUEVO - CUPÓN] Agregamos la fila del cupón
    if (!empty($coupon_used)) {
        $message_admin .= '
            <tr style="background: #fff3cd;">
                <td style="padding: 12px; border: 1px solid #dee2e6; font-weight: bold;">🎟️ Cupón usado:</td>
                <td style="padding: 12px; border: 1px solid #dee2e6; color: #856404; font-weight: bold;">' . $coupon_used . '</td>
            </tr>';
    }

    $message_admin .= '
        </table>';

    // Dirección de facturación si es diferente
    if (!empty($billing_address)) {
        $message_admin .= '
        <h3 style="color: #dc3545; margin-bottom: 15px;">Dirección de Facturación:</h3>
        <div style="background: white; padding: 15px; border-radius: 8px; margin-bottom: 25px; border: 1px solid #dee2e6;">
            <p style="margin: 0;"><strong>Dirección:</strong> ' . $billing_address . '</p>';

        if (!empty($billing_postal_code) || !empty($billing_city)) {
            $location = '';
            if (!empty($billing_postal_code)) {
                $location .= $billing_postal_code;
            }
            if (!empty($billing_city)) {
                $location .= (!empty($location) ? ' ' : '') . $billing_city;
            }
            $message_admin .= '<p style="margin: 5px 0 0;"><strong>Población:</strong> ' . $location . '</p>';
        }

        if (!empty($billing_province)) {
            $message_admin .= '<p style="margin: 5px 0 0;"><strong>Provincia:</strong> ' . $billing_province . '</p>';
        }

        $message_admin .= '</div>';
    }

    $message_admin .= '
        <h3 style="color: #dc3545; margin-bottom: 15px;">Archivos Adjuntos:</h3>
        <ul style="background: white; padding: 20px; border-radius: 8px; border: 1px solid #dee2e6;">';

    foreach ($uploaded_files as $field => $files) {
        if (!empty($files)) {
            $field_names = array(
                'dni_file' => 'DNI/NIE',
                'registration_file' => 'Registro Marítimo',
                'signature' => 'Firma Digital'
            );

            $field_name = isset($field_names[$field]) ? $field_names[$field] : $field;

            if (is_array($files)) {
                foreach ($files as $file) {
                    $message_admin .= '<li><strong>' . $field_name . ':</strong> ' . basename($file) . '</li>';
                }
            } else {
                $message_admin .= '<li><strong>' . $field_name . ':</strong> ' . basename($files) . '</li>';
            }
        }
    }

    $message_admin .= '</ul>

        <div style="background: #d4edda; padding: 20px; border-radius: 8px; margin: 25px 0; border: 1px solid #c3e6cb;">
            <h3 style="color: #155724; margin-top: 0; margin-bottom: 15px;">📋 Próximos pasos:</h3>
            <ol style="color: #155724; margin: 0; padding-left: 20px;">
                <li>Revisar toda la documentación adjunta</li>
                <li>Verificar los datos del cliente</li>
                <li>Iniciar los trámites correspondientes ante las autoridades</li>
                <li>Mantener informado al cliente del progreso</li>
            </ol>
        </div>

        <div style="text-align: center; margin: 25px 0;">
            <p style="color: #666; margin-bottom: 10px;">Los archivos están guardados en el servidor para su revisión</p>
            <div style="background: #dc3545; color: white; padding: 15px; border-radius: 8px; display: inline-block;">
                <p style="margin: 0;"><strong>📁 Carpeta del cliente en el servidor</strong></p>
            </div>
        </div>

        <hr style="border: none; border-top: 1px solid #dee2e6; margin: 25px 0;">

        <div style="text-align: center; color: #6c757d; font-size: 12px;">
            <p style="margin: 0;">Este email se generó automáticamente desde el formulario de baja de embarcación</p>
            <p style="margin: 5px 0 0;">Tramitfy - Sistema de gestión de trámites náuticos</p>
        </div>

    </div>

</body>
</html>';

    // Enviar email al administrador
    $headers_admin = array('Content-Type: text/html; charset=UTF-8');
    wp_mail($admin_email, $subject_admin, $message_admin, $headers_admin);
}
?>