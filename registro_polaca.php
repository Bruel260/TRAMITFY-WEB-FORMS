<?php
// Asegurarse de que el archivo no sea accedido directamente
defined('ABSPATH') || exit;

// Configuración de Stripe
define('STRIPE_MODE', 'test'); // 'test' o 'live'
define('STRIPE_TEST_PUBLIC_KEY', 'YOUR_STRIPE_TEST_PUBLIC_KEY_HERE');
define('STRIPE_TEST_SECRET_KEY', 'YOUR_STRIPE_TEST_SECRET_KEY_HERE');
define('STRIPE_LIVE_PUBLIC_KEY', 'YOUR_STRIPE_LIVE_PUBLIC_KEY_HERE');
define('STRIPE_LIVE_SECRET_KEY', 'YOUR_STRIPE_LIVE_SECRET_KEY_HERE');

// Seleccionar las claves según el modo
$stripe_public_key = (STRIPE_MODE === 'live') ? STRIPE_LIVE_PUBLIC_KEY : STRIPE_TEST_PUBLIC_KEY;
$stripe_secret_key = (STRIPE_MODE === 'live') ? STRIPE_LIVE_SECRET_KEY : STRIPE_TEST_SECRET_KEY;

// Configuración del webhook TRAMITFY
define('TRAMITFY_API_URL', 'https://46-202-128-35.sslip.io/api/herramientas/polaca/webhook');

/**
 * Función para calcular costos adicionales basados en las opciones seleccionadas
 */
function calculate_additional_costs($tramite_type, $extra_data) {
    $additional_cost = 0;

    // Precios de las opciones por tipo de trámite
    $option_prices = array(
        'registro' => array(
            'delivery_option' => array('express' => 180),
            'mmsi_option' => array('mmsi_licensed' => 170, 'mmsi_unlicensed' => 170, 'mmsi_company' => 170),
            'extra_services' => array()
        ),
        'cambio_titularidad' => array(
            'boat_size' => array('size_7_12' => 50, 'size_12_24' => 100),
            'mmsi_option' => array('mmsi_licensed' => 170, 'mmsi_unlicensed' => 170, 'mmsi_company' => 170),
            'extra_services' => array()
        ),
        'mmsi' => array()
    );

    if (!isset($option_prices[$tramite_type])) {
        return 0;
    }

    $prices = $option_prices[$tramite_type];

    // Calcular costos de opciones simples
    foreach ($prices as $option_type => $option_values) {
        if ($option_type === 'extra_services') continue; // Se maneja por separado

        if (isset($extra_data[$option_type]) && isset($option_values[$extra_data[$option_type]])) {
            $additional_cost += $option_values[$extra_data[$option_type]];
        }
    }

    // Calcular costos de servicios extra (array)
    if (isset($extra_data['extra_services']) && is_array($extra_data['extra_services'])) {
        foreach ($extra_data['extra_services'] as $service) {
            if (isset($prices['extra_services'][$service])) {
                $additional_cost += $prices['extra_services'][$service];
            }
        }
    }

    return $additional_cost;
}

/**
 * Función para obtener precios según el tipo de trámite polaco
 */
function get_polish_tramite_prices() {
    return array(
        'registro' => array(
            'total' => 429.99,
            'taxes' => 75.00,
            'fees' => 293.38, // Calculado para que con IVA sume el total
        ),
        'cambio_titularidad' => array(
            'total' => 429.99,
            'taxes' => 50.00,
            'fees' => 314.04, // Calculado para que con IVA sume el total
        ),
        'mmsi' => array(
            'total' => 190.00,
            'taxes' => 40.00,
            'fees' => 123.97, // Calculado para que con IVA sume el total
        ),
    );
}

/**
 * Función para obtener la descripción del trámite polaco
 */
function get_polish_tramite_description($tramite_type) {
    $descriptions = array(
        'registro' => 'Registro bajo bandera polaca',
        'cambio_titularidad' => 'Cambio de titularidad - bandera polaca',
        'mmsi' => 'Solicitud de número MMSI polaco',
    );

    return isset($descriptions[$tramite_type]) ? $descriptions[$tramite_type] : 'Trámite marítimo polaco';
}

/**
 * Función para generar factura como PDF según el trámite
 */
function generate_polish_invoice_pdf($customer_name, $customer_dni, $customer_email, $customer_phone, $tramite_type, $extra_data, $coupon_used, $upload_dir, $billing_address = '', $billing_city = '', $billing_postal_code = '', $billing_province = '') {

    // Definir precios según el trámite
    $prices = get_polish_tramite_prices();
    $current_price = $prices[$tramite_type];

    $base_price = $current_price['total'];

    // Calcular costos adicionales basados en las opciones seleccionadas
    $additional_costs = calculate_additional_costs($tramite_type, $extra_data);
    $total_base_price = $base_price + $additional_costs;

    $taxes = $current_price['taxes'];
    $fees = $current_price['fees'];
    $vat_rate = 0.21;

    $total_with_discount = $total_base_price;
    $discount_percent = 0;
    $discount_amount = 0;

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
    $service_description = get_polish_tramite_description($tramite_type);
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetTextColor($text_color[0], $text_color[1], $text_color[2]);
    $pdf->Cell(100, 8, utf8_decode($service_description), 1, 0, 'L');
    $pdf->Cell(40, 8, '1', 1, 0, 'C');
    $pdf->Cell(50, 8, number_format($base_price, 2).' EUR', 1, 1, 'R');

    // Mostrar costos adicionales si existen
    if ($additional_costs > 0) {
        $pdf->Cell(100, 8, utf8_decode('Opciones adicionales'), 1, 0, 'L');
        $pdf->Cell(40, 8, '1', 1, 0, 'C');
        $pdf->Cell(50, 8, number_format($additional_costs, 2).' EUR', 1, 1, 'R');
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

    $pdf->Ln(10);

    // Mensaje de pie
    $pdf->SetFont('Arial', 'I', 9);
    $pdf->Cell(0, 5, utf8_decode('Gracias por confiar en Tramitfy para sus trámites náuticos.'), 0, 1, 'C');
    $pdf->Cell(0, 5, utf8_decode('Para cualquier consulta, puede contactarnos en info@tramitfy.es'), 0, 1, 'C');

    // Guardar el PDF en el directorio de uploads
    $invoice_filename = 'factura_polaca_' . date('Ymd_His') . '.pdf';
    $invoice_path = $upload_dir . '/' . $invoice_filename;
    $pdf->Output('F', $invoice_path);

    return $invoice_filename;
}

/**
 * Shortcode para el formulario de registro de bandera polaca
 */
function polish_registration_form_shortcode() {
    global $stripe_public_key;

    // Encolar los scripts y estilos necesarios
    wp_enqueue_style('polish-registration-form-style', get_template_directory_uri() . '/style.css', array(), filemtime(get_template_directory() . '/style.css'));
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
        .pr-container {
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
        .pr-sidebar {
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

        .pr-logo {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .pr-logo i {
            font-size: 28px;
        }

        .pr-headline {
            font-size: 17px;
            font-weight: 600;
            line-height: 1.3;
            margin-bottom: 4px;
        }

        .pr-subheadline {
            font-size: 13px;
            opacity: 0.92;
            line-height: 1.4;
        }

        /* Caja de precio destacada */
        .pr-price-box {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            padding: 12px;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.25);
            margin: 6px 0;
        }

        .pr-price-label {
            font-size: 11px;
            opacity: 0.85;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 6px;
        }

        .pr-price-amount {
            font-size: 38px;
            font-weight: 700;
            margin: 4px 0;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .pr-price-detail {
            font-size: 12px;
            opacity: 0.88;
        }

        /* Lista de beneficios */
        .pr-benefits {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin: 8px 0;
        }

        .pr-benefit {
            display: flex;
            align-items: start;
            gap: 8px;
            font-size: 12px;
            line-height: 1.4;
        }

        .pr-benefit i {
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
        .pr-trust-badges {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: auto;
            padding-top: 10px;
        }

        .pr-badge {
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

        .pr-badge i {
            font-size: 11px;
        }

        /* Sidebar de autorización */
        .pr-sidebar-auth-doc {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-top: 10px;
        }

        /* ÁREA PRINCIPAL DEL FORMULARIO */
        .pr-form-area {
            padding: 30px 40px;
            background: #fafbfc;
            overflow-y: auto;
        }

        .pr-form-header {
            margin-bottom: 15px;
        }

        .pr-form-title {
            font-size: 22px;
            font-weight: 700;
            color: rgb(var(--neutral-900));
            margin-bottom: 4px;
        }

        .pr-form-subtitle {
            font-size: 13px;
            color: rgb(var(--neutral-600));
        }

        /* Panel de auto-rellenado para administradores */
        .pr-admin-panel {
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

        .pr-admin-panel-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .pr-admin-panel-title {
            font-size: 12px;
            font-weight: 600;
            opacity: 0.95;
        }

        .pr-admin-panel-subtitle {
            font-size: 10px;
            opacity: 0.85;
        }

        .pr-admin-autofill-btn {
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

        .pr-admin-autofill-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        /* Navegación modernizada */
        .pr-navigation {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            padding: 6px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .pr-nav-item {
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

        .pr-nav-item i {
            font-size: 14px;
        }

        .pr-nav-item.active {
            background: linear-gradient(135deg, rgb(var(--primary)) 0%, rgb(var(--primary-dark)) 100%);
            color: white;
            border-color: rgb(var(--primary));
            box-shadow: 0 4px 12px rgba(var(--primary), 0.3);
        }

        .pr-nav-item:hover:not(.active) {
            background: #e9ecef;
            border-color: rgb(var(--primary-light));
        }

        /* Páginas del formulario */
        .pr-form-page {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        }

        .pr-form-page.hidden {
            display: none;
        }

        .pr-form-page h3 {
            font-size: 18px;
            font-weight: 600;
            color: rgb(var(--neutral-900));
            margin: 0 0 20px 0;
        }

        /* Inputs mejorados */
        .pr-input-group {
            margin-bottom: 18px;
        }

        .pr-input-group label {
            display: block;
            font-weight: 500;
            margin-bottom: 7px;
            color: rgb(var(--neutral-800));
            font-size: 14px;
        }

        .pr-input-group input[type="text"],
        .pr-input-group input[type="email"],
        .pr-input-group input[type="tel"],
        .pr-input-group input[type="file"],
        .pr-input-group select {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid rgb(var(--neutral-300));
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.2s ease;
            background: white;
        }

        .pr-input-group input:focus,
        .pr-input-group select:focus {
            outline: none;
            border-color: rgb(var(--primary));
            box-shadow: 0 0 0 3px rgba(var(--primary), 0.1);
        }

        /* Radio buttons para selección de trámite */
        .pr-tramite-selector {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }

        .pr-tramite-option {
            background: #f8f9fa;
            border: 3px solid rgb(var(--neutral-300));
            border-radius: 12px;
            padding: 25px;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }

        .pr-tramite-option:hover {
            border-color: rgb(var(--primary));
            background: rgba(var(--primary), 0.02);
        }

        .pr-tramite-option.selected {
            border-color: rgb(var(--primary));
            background: rgba(var(--primary), 0.05);
        }

        .pr-tramite-option input[type="radio"] {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }

        .pr-tramite-title {
            font-size: 16px;
            font-weight: 600;
            color: rgb(var(--neutral-900));
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .pr-tramite-price {
            font-size: 28px;
            font-weight: 700;
            color: rgb(var(--primary));
            margin: 10px 0;
        }

        .pr-tramite-description {
            font-size: 13px;
            color: rgb(var(--neutral-600));
            line-height: 1.4;
            margin-bottom: 15px;
        }

        .pr-tramite-details {
            font-size: 12px;
            color: rgb(var(--neutral-500));
        }

        /* Grid para inputs en 2 columnas */
        .pr-inputs-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 18px;
        }

        /* Upload section */
        .pr-upload-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }

        .pr-upload-item {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 12px;
            border: 2px dashed rgb(var(--neutral-300));
            transition: all 0.3s ease;
        }

        .pr-upload-item:hover {
            border-color: rgb(var(--primary));
            background: rgba(var(--primary), 0.02);
        }

        .pr-upload-item label {
            display: block;
            font-weight: 600;
            margin-bottom: 12px;
            color: rgb(var(--neutral-800));
            font-size: 15px;
        }

        .pr-upload-item input[type="file"] {
            width: 100%;
            padding: 6px;
            border: none;
            background: white;
            border-radius: 6px;
            font-size: 11px;
        }

        .pr-upload-item .view-example {
            display: inline-block;
            margin-top: 4px;
            color: rgb(var(--primary));
            text-decoration: none;
            font-size: 11px;
            font-weight: 500;
        }

        .pr-upload-item .view-example:hover {
            text-decoration: underline;
        }

        /* Layout 2 columnas para autorización */
        .pr-auth-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin: 20px 0;
        }

        .pr-auth-document {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 10px;
            font-size: 14px;
            line-height: 1.7;
            border: 2px solid rgb(var(--neutral-200));
        }

        .pr-auth-document h4 {
            font-size: 16px;
            font-weight: 700;
            color: rgb(var(--primary));
            margin-bottom: 15px;
        }

        .pr-auth-signature-area {
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .pr-signature-label {
            font-size: 14px;
            font-weight: 600;
            color: rgb(var(--neutral-700));
            margin-bottom: 12px;
            text-align: center;
        }

        /* Firma */
        .pr-signature-container {
            margin: 0;
            text-align: center;
            position: relative;
        }

        #signature-pad {
            border: 3px solid rgb(var(--primary));
            border-radius: 8px;
            width: 100%;
            height: 180px;
            cursor: crosshair;
            background: white;
            box-shadow: 0 2px 8px rgba(var(--primary), 0.15);
        }

        /* Botón ampliar para móvil */
        .pr-expand-signature-btn {
            display: none;
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, rgb(var(--primary)) 0%, rgb(var(--primary-dark)) 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }

        .pr-signature-controls {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin-top: 12px;
        }

        .pr-signature-controls button {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            font-size: 13px;
            transition: all 0.2s ease;
        }

        .pr-clear-signature {
            background: #dc3545;
            color: white;
        }

        .pr-clear-signature:hover {
            background: #c82333;
        }

        .pr-confirm-signature {
            background: rgb(var(--primary));
            color: white;
        }

        .pr-confirm-signature:hover {
            background: rgb(var(--primary-dark));
        }

        .pr-confirm-signature:disabled {
            background: #6c757d;
            cursor: not-allowed;
        }

        /* Modal de firma fullscreen */
        .pr-signature-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            z-index: 999999;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .pr-signature-modal-content {
            background: white;
            border-radius: 12px;
            padding: 20px;
            width: 100%;
            max-width: 800px;
            text-align: center;
        }

        .pr-signature-modal h3 {
            margin: 0 0 20px 0;
            color: rgb(var(--neutral-900));
        }

        #signature-pad-fullscreen {
            border: 3px solid rgb(var(--primary));
            border-radius: 8px;
            width: 100%;
            height: 400px;
            cursor: crosshair;
            background: white;
            margin-bottom: 20px;
        }

        .pr-signature-guide {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            pointer-events: none;
            color: rgb(var(--neutral-400));
            font-size: 14px;
            text-align: center;
        }

        .pr-signature-guide::before {
            content: '';
            position: absolute;
            top: -2px;
            left: 50%;
            transform: translateX(-50%);
            width: 200px;
            height: 1px;
            background: rgb(var(--neutral-400));
        }

        /* Opciones adicionales */
        .pr-additional-options {
            margin: 25px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
            border: 2px solid rgb(var(--neutral-200));
        }

        .pr-additional-options h4 {
            font-size: 16px;
            font-weight: 600;
            color: rgb(var(--neutral-900));
            margin-bottom: 15px;
        }

        .pr-checkbox-group {
            margin-bottom: 15px;
        }

        .pr-checkbox-group label {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            font-size: 14px;
            color: rgb(var(--neutral-700));
        }

        .pr-checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
        }

        /* Resumen de pago */
        .pr-payment-summary {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 12px;
            border: 2px solid rgb(var(--neutral-200));
            margin: 20px 0;
        }

        .pr-payment-summary h4 {
            font-size: 18px;
            font-weight: 600;
            color: rgb(var(--neutral-900));
            margin-bottom: 15px;
        }

        .pr-summary-line {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .pr-summary-line.total {
            font-size: 18px;
            font-weight: 700;
            color: rgb(var(--primary));
            border-top: 2px solid rgb(var(--neutral-300));
            padding-top: 12px;
            margin-top: 12px;
        }

        /* Botones */
        .pr-btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .pr-btn-primary {
            background: linear-gradient(135deg, rgb(var(--primary)) 0%, rgb(var(--primary-dark)) 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(var(--primary), 0.3);
        }

        .pr-btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(var(--primary), 0.4);
        }

        .pr-btn-secondary {
            background: #6c757d;
            color: white;
        }

        .pr-btn-secondary:hover {
            background: #5a6268;
        }

        .pr-form-actions {
            display: flex;
            gap: 15px;
            justify-content: space-between;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid rgb(var(--neutral-200));
        }

        /* Modal de pago */
        .pr-payment-modal {
            position: fixed;
            top: 125px;
            left: 50%;
            transform: translateX(-50%);
            width: 90%;
            max-width: 500px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            z-index: 9999;
            display: none;
            padding: 25px;
        }

        .pr-payment-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 9998;
            display: none;
        }

        .pr-payment-modal h3 {
            margin: 0 0 20px 0;
            text-align: center;
            color: rgb(var(--neutral-900));
        }

        #payment-element {
            margin-bottom: 20px;
        }

        .pr-payment-spinner {
            display: none;
            text-align: center;
            padding: 20px;
        }

        .pr-payment-error {
            color: #dc3545;
            text-align: center;
            margin-top: 10px;
            display: none;
        }

        .pr-close-modal {
            position: absolute;
            top: 10px;
            right: 15px;
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: rgb(var(--neutral-600));
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .pr-container {
                grid-template-columns: 1fr;
                margin: 0;
                border-radius: 0;
            }

            .pr-sidebar {
                position: static;
                height: auto;
                padding: 15px;
                gap: 8px;
            }

            .pr-logo {
                font-size: 18px;
                margin-bottom: 2px;
            }

            .pr-headline {
                font-size: 15px;
                margin-bottom: 2px;
            }

            .pr-subheadline {
                font-size: 12px;
            }

            .pr-price-box {
                padding: 10px;
                margin: 4px 0;
            }

            .pr-price-amount {
                font-size: 30px;
            }

            .pr-benefit {
                font-size: 11px;
            }

            .pr-benefit i {
                width: 18px;
                height: 18px;
                font-size: 12px;
            }

            .pr-form-area {
                padding: 15px;
            }

            .pr-form-header {
                margin-bottom: 12px;
            }

            .pr-form-title {
                font-size: 18px;
            }

            .pr-form-subtitle {
                font-size: 12px;
            }

            .pr-admin-panel {
                flex-direction: column;
                gap: 10px;
                padding: 12px;
                margin-bottom: 10px;
            }

            .pr-admin-autofill-btn {
                width: 100%;
            }

            .pr-navigation {
                flex-wrap: nowrap;
                overflow-x: auto;
                gap: 6px;
                padding: 4px;
                margin-bottom: 12px;
                -webkit-overflow-scrolling: touch;
            }

            .pr-nav-item {
                flex: 0 0 auto;
                min-width: 100px;
                padding: 8px 12px;
                font-size: 11px;
                white-space: nowrap;
            }

            .pr-nav-item i {
                font-size: 12px;
            }

            .pr-form-page {
                padding: 20px 15px;
            }

            .pr-form-page h3 {
                font-size: 16px;
                margin-bottom: 15px;
            }

            .pr-inputs-row {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .pr-input-group {
                margin-bottom: 15px;
            }

            .pr-input-group label {
                font-size: 13px;
                margin-bottom: 6px;
            }

            .pr-input-group input,
            .pr-input-group select {
                padding: 10px 14px;
                font-size: 14px;
            }

            .pr-auth-layout {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .pr-auth-document {
                padding: 15px;
                font-size: 13px;
            }

            .pr-auth-document h4 {
                font-size: 14px;
            }

            .pr-tramite-selector {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .pr-tramite-option {
                padding: 20px;
            }

            .pr-tramite-title {
                font-size: 15px;
            }

            .pr-tramite-price {
                font-size: 24px;
            }

            .pr-tramite-description {
                font-size: 12px;
            }

            .pr-tramite-details {
                font-size: 11px;
            }

            .pr-upload-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .pr-upload-item {
                padding: 15px;
            }

            .pr-additional-options {
                padding: 15px;
                margin: 20px 0;
            }

            .pr-additional-options h4 {
                font-size: 14px;
            }

            .pr-checkbox-group {
                margin-bottom: 12px;
            }

            .pr-checkbox-group label {
                font-size: 13px;
            }

            .pr-payment-summary {
                padding: 15px;
            }

            .pr-payment-summary h4 {
                font-size: 16px;
            }

            .pr-summary-line {
                font-size: 13px;
            }

            .pr-summary-line.total {
                font-size: 16px;
            }

            /* Mostrar botón ampliar en móvil */
            .pr-expand-signature-btn {
                display: block;
            }

            #signature-pad {
                display: none;
            }

            .pr-signature-controls {
                display: none;
            }

            .pr-signature-modal-content {
                padding: 15px;
                border-radius: 0;
                width: 100%;
                height: 100%;
                max-width: 100%;
            }

            #signature-pad-fullscreen {
                height: 300px;
                margin-bottom: 15px;
            }

            .pr-payment-modal {
                top: 20px;
                width: calc(100% - 20px);
                padding: 15px;
                max-height: calc(100vh - 40px);
                overflow-y: auto;
            }

            .pr-form-actions {
                gap: 10px;
                margin-top: 20px;
                padding-top: 15px;
            }

            .pr-btn {
                padding: 12px 20px;
                font-size: 13px;
            }
        }

        @media (max-width: 480px) {
            .pr-form-area {
                padding: 12px;
            }

            .pr-form-page {
                padding: 15px 10px;
            }

            .pr-form-title {
                font-size: 16px;
            }

            .pr-navigation {
                gap: 4px;
                padding: 3px;
            }

            .pr-nav-item {
                min-width: 85px;
                padding: 8px 10px;
                font-size: 10px;
                gap: 4px;
            }

            .pr-tramite-option {
                padding: 15px;
            }

            .pr-tramite-title {
                font-size: 14px;
            }

            .pr-tramite-price {
                font-size: 22px;
            }

            .pr-upload-item {
                padding: 12px;
            }

            .pr-payment-summary {
                padding: 12px;
            }

            .pr-form-actions {
                flex-direction: column;
            }

            .pr-btn {
                width: 100%;
                justify-content: center;
            }

            .pr-btn-secondary {
                order: 2;
            }

            .pr-btn-primary {
                order: 1;
            }

            .pr-admin-panel-title {
                font-size: 11px;
            }

            .pr-admin-panel-subtitle {
                font-size: 9px;
            }

            #signature-pad-fullscreen {
                height: 250px;
            }
        }
    </style>

    <?php if (current_user_can('administrator')): ?>
    <!-- Panel de auto-rellenado para administradores -->
    <div class="pr-admin-panel">
        <div class="pr-admin-panel-info">
            <div class="pr-admin-panel-title">🔧 MODO ADMINISTRADOR</div>
            <div class="pr-admin-panel-subtitle">Rellena automáticamente todos los campos y llega hasta el resumen</div>
        </div>
        <button type="button" id="admin-autofill-btn" class="pr-admin-autofill-btn">
            ⚡ Auto-rellenar Formulario
        </button>
    </div>
    <?php endif; ?>

    <!-- Container principal -->
    <div class="pr-container">
        <!-- Sidebar izquierdo -->
        <div class="pr-sidebar">
            <!-- Logo y título -->
            <div class="pr-logo">
                <i class="fas fa-ship"></i>
                Tramitfy
            </div>

            <!-- Contenido dinámico del sidebar -->
            <div id="sidebar-selection" class="pr-sidebar-content">
                <div class="pr-headline">Registro Bandera Polaca</div>
                <div class="pr-subheadline">Selecciona el tipo de trámite que necesitas y completa el proceso paso a paso.</div>

                <div class="pr-benefits">
                    <div class="pr-benefit">
                        <i class="fas fa-check"></i>
                        <span>Proceso 100% online</span>
                    </div>
                    <div class="pr-benefit">
                        <i class="fas fa-check"></i>
                        <span>Documentación digital</span>
                    </div>
                    <div class="pr-benefit">
                        <i class="fas fa-check"></i>
                        <span>Seguimiento en tiempo real</span>
                    </div>
                    <div class="pr-benefit">
                        <i class="fas fa-check"></i>
                        <span>Asesoramiento especializado</span>
                    </div>
                </div>

                <div class="pr-trust-badges">
                    <div class="pr-badge">
                        <i class="fas fa-shield-alt"></i>
                        Seguro
                    </div>
                    <div class="pr-badge">
                        <i class="fas fa-clock"></i>
                        Rápido
                    </div>
                    <div class="pr-badge">
                        <i class="fas fa-certificate"></i>
                        Oficial
                    </div>
                </div>
            </div>

            <div id="sidebar-default" class="pr-sidebar-content" style="display: none;">
                <div class="pr-headline">Registro Bandera Polaca</div>
                <div class="pr-subheadline">Tramita tu registro bajo bandera polaca de forma rápida y segura.</div>

                <div class="pr-price-box">
                    <div class="pr-price-label">Precio total</div>
                    <div class="pr-price-amount" id="sidebar-price">€ 429.99</div>
                    <div class="pr-price-detail">IVA incluido</div>
                </div>

                <div class="pr-benefits">
                    <div class="pr-benefit">
                        <i class="fas fa-check"></i>
                        <span>Registro oficial completo</span>
                    </div>
                    <div class="pr-benefit">
                        <i class="fas fa-check"></i>
                        <span>Gestión de documentación</span>
                    </div>
                    <div class="pr-benefit">
                        <i class="fas fa-check"></i>
                        <span>Asesoramiento legal</span>
                    </div>
                    <div class="pr-benefit">
                        <i class="fas fa-check"></i>
                        <span>Soporte especializado</span>
                    </div>
                </div>

                <div class="pr-trust-badges">
                    <div class="pr-badge">
                        <i class="fas fa-shield-alt"></i>
                        Seguro
                    </div>
                    <div class="pr-badge">
                        <i class="fas fa-clock"></i>
                        Rápido
                    </div>
                    <div class="pr-badge">
                        <i class="fas fa-certificate"></i>
                        Oficial
                    </div>
                </div>
            </div>

            <div id="sidebar-authorization" class="pr-sidebar-content" style="display: none;">
                <div class="pr-headline">Autorización y Firma</div>
                <div class="pr-subheadline">Documento de autorización para el trámite de registro.</div>

                <div class="pr-sidebar-auth-doc">
                    <div style="background: rgba(255, 255, 255, 0.15); padding: 15px; border-radius: 10px; font-size: 13px; line-height: 1.5;">
                        <strong>Autorización para:</strong><br>
                        <span id="auth-customer-name">Nombre del cliente</span><br>
                        <strong>DNI:</strong> <span id="auth-customer-dni">DNI del cliente</span><br><br>
                        <em>Autorizo a Tramitfy para realizar el trámite de registro bajo bandera polaca en mi nombre.</em>
                    </div>
                </div>

                <div class="pr-trust-badges">
                    <div class="pr-badge">
                        <i class="fas fa-file-signature"></i>
                        Firma digital
                    </div>
                    <div class="pr-badge">
                        <i class="fas fa-lock"></i>
                        Protegido
                    </div>
                </div>
            </div>
        </div>

        <!-- Área principal del formulario -->
        <div class="pr-form-area">
            <div class="pr-form-header">
                <h1 class="pr-form-title">Registro Bandera Polaca</h1>
                <p class="pr-form-subtitle">Complete el formulario para iniciar su trámite de registro marítimo</p>
            </div>

            <!-- Navegación entre páginas -->
            <div class="pr-navigation">
                <div class="pr-nav-item active" data-page="page-selection">
                    <i class="fas fa-list"></i>
                    Selección
                </div>
                <div class="pr-nav-item" data-page="page-personal">
                    <i class="fas fa-user"></i>
                    Datos Personales
                </div>
                <div class="pr-nav-item" data-page="page-documents">
                    <i class="fas fa-file-upload"></i>
                    Documentación
                </div>
                <div class="pr-nav-item" data-page="page-payment">
                    <i class="fas fa-credit-card"></i>
                    Pago
                </div>
            </div>

            <!-- Formulario -->
            <form id="polish-registration-form" enctype="multipart/form-data">
                <!-- PÁGINA 1: Selección de trámite -->
                <div id="page-selection" class="pr-form-page">
                    <h3>Seleccione el tipo de trámite</h3>
                    <p>Elija el servicio que necesita para su embarcación:</p>

                    <div class="pr-tramite-selector">
                        <div class="pr-tramite-option" data-tramite="registro">
                            <input type="radio" name="tramite_type" value="registro" id="tramite-registro">
                            <label for="tramite-registro">
                                <div class="pr-tramite-title">
                                    <i class="fas fa-ship"></i>
                                    Registro Completo
                                </div>
                                <div class="pr-tramite-price">€ 429.99</div>
                                <div class="pr-tramite-description">
                                    Registro completo de embarcación bajo bandera polaca. Incluye toda la documentación oficial y gestión completa del proceso.
                                </div>
                                <div class="pr-tramite-details">
                                    <strong>Incluye:</strong> Tasas oficiales (€75), gestión completa, documentación oficial
                                </div>
                            </label>
                        </div>

                        <div class="pr-tramite-option" data-tramite="cambio_titularidad">
                            <input type="radio" name="tramite_type" value="cambio_titularidad" id="tramite-cambio">
                            <label for="tramite-cambio">
                                <div class="pr-tramite-title">
                                    <i class="fas fa-exchange-alt"></i>
                                    Cambio de Titularidad
                                </div>
                                <div class="pr-tramite-price">€ 429.99</div>
                                <div class="pr-tramite-description">
                                    Transferencia de titularidad de embarcación ya registrada bajo bandera polaca.
                                </div>
                                <div class="pr-tramite-details">
                                    <strong>Incluye:</strong> Tasas oficiales (€50), gestión de transferencia, nueva documentación
                                </div>
                            </label>
                        </div>

                        <div class="pr-tramite-option" data-tramite="mmsi">
                            <input type="radio" name="tramite_type" value="mmsi" id="tramite-mmsi">
                            <label for="tramite-mmsi">
                                <div class="pr-tramite-title">
                                    <i class="fas fa-satellite"></i>
                                    Número MMSI
                                </div>
                                <div class="pr-tramite-price">€ 190.00</div>
                                <div class="pr-tramite-description">
                                    Solicitud de número MMSI (Maritime Mobile Service Identity) polaco para comunicaciones marítimas.
                                </div>
                                <div class="pr-tramite-details">
                                    <strong>Incluye:</strong> Tasas oficiales (€40), gestión MMSI, certificado oficial
                                </div>
                            </label>
                        </div>
                    </div>

                    <div class="pr-form-actions">
                        <div></div>
                        <button type="button" class="pr-btn pr-btn-primary" onclick="showPage('page-personal')">
                            Continuar <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <!-- PÁGINA 2: Datos personales -->
                <div id="page-personal" class="pr-form-page hidden">
                    <h3>Datos Personales</h3>

                    <div class="pr-inputs-row">
                        <div class="pr-input-group">
                            <label for="customer_name">Nombre completo *</label>
                            <input type="text" id="customer_name" name="customer_name" required>
                        </div>
                        <div class="pr-input-group">
                            <label for="customer_dni">DNI / Pasaporte *</label>
                            <input type="text" id="customer_dni" name="customer_dni" required>
                        </div>
                    </div>

                    <div class="pr-inputs-row">
                        <div class="pr-input-group">
                            <label for="customer_email">Email *</label>
                            <input type="email" id="customer_email" name="customer_email" required>
                        </div>
                        <div class="pr-input-group">
                            <label for="customer_phone">Teléfono *</label>
                            <input type="tel" id="customer_phone" name="customer_phone" required>
                        </div>
                    </div>

                    <!-- Opciones adicionales según el tipo de trámite -->
                    <div id="additional-options-registro" class="pr-additional-options" style="display: none;">
                        <h4>Opciones adicionales - Registro</h4>

                        <div class="pr-checkbox-group">
                            <label>
                                <input type="checkbox" name="delivery_option" value="express">
                                <span>Delivery Express (+€180) - Entrega urgente de documentación</span>
                            </label>
                        </div>

                        <div class="pr-checkbox-group">
                            <label>
                                <input type="checkbox" name="mmsi_option" value="mmsi_licensed">
                                <span>MMSI Licensed (+€170) - Número MMSI para uso comercial</span>
                            </label>
                        </div>

                        <div class="pr-checkbox-group">
                            <label>
                                <input type="checkbox" name="mmsi_option" value="mmsi_unlicensed">
                                <span>MMSI Unlicensed (+€170) - Número MMSI para uso recreativo</span>
                            </label>
                        </div>

                        <div class="pr-checkbox-group">
                            <label>
                                <input type="checkbox" name="mmsi_option" value="mmsi_company">
                                <span>MMSI Company (+€170) - Número MMSI para empresa</span>
                            </label>
                        </div>
                    </div>

                    <div id="additional-options-cambio" class="pr-additional-options" style="display: none;">
                        <h4>Opciones adicionales - Cambio Titularidad</h4>

                        <div class="pr-checkbox-group">
                            <label>
                                <input type="checkbox" name="boat_size" value="size_7_12">
                                <span>Embarcación 7-12m (+€50) - Gestión para embarcaciones medianas</span>
                            </label>
                        </div>

                        <div class="pr-checkbox-group">
                            <label>
                                <input type="checkbox" name="boat_size" value="size_12_24">
                                <span>Embarcación 12-24m (+€100) - Gestión para embarcaciones grandes</span>
                            </label>
                        </div>

                        <div class="pr-checkbox-group">
                            <label>
                                <input type="checkbox" name="mmsi_option" value="mmsi_licensed">
                                <span>MMSI Licensed (+€170) - Número MMSI para uso comercial</span>
                            </label>
                        </div>

                        <div class="pr-checkbox-group">
                            <label>
                                <input type="checkbox" name="mmsi_option" value="mmsi_unlicensed">
                                <span>MMSI Unlicensed (+€170) - Número MMSI para uso recreativo</span>
                            </label>
                        </div>

                        <div class="pr-checkbox-group">
                            <label>
                                <input type="checkbox" name="mmsi_option" value="mmsi_company">
                                <span>MMSI Company (+€170) - Número MMSI para empresa</span>
                            </label>
                        </div>
                    </div>

                    <div class="pr-form-actions">
                        <button type="button" class="pr-btn pr-btn-secondary" onclick="showPage('page-selection')">
                            <i class="fas fa-arrow-left"></i> Atrás
                        </button>
                        <button type="button" class="pr-btn pr-btn-primary" onclick="showPage('page-documents')">
                            Continuar <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <!-- PÁGINA 3: Documentación -->
                <div id="page-documents" class="pr-form-page hidden">
                    <h3>Documentación Requerida</h3>

                    <div class="pr-upload-grid">
                        <div class="pr-upload-item">
                            <label>DNI / Pasaporte *</label>
                            <input type="file" name="dni_documento[]" accept=".pdf,.jpg,.jpeg,.png" multiple>
                            <a href="#" class="view-example">Ver ejemplo</a>
                        </div>

                        <div class="pr-upload-item">
                            <label>Registro Marítimo *</label>
                            <input type="file" name="registro_maritimo[]" accept=".pdf,.jpg,.jpeg,.png" multiple>
                            <a href="#" class="view-example">Ver ejemplo</a>
                        </div>

                        <div class="pr-upload-item">
                            <label>Seguro de Embarcación</label>
                            <input type="file" name="seguro_embarcacion[]" accept=".pdf,.jpg,.jpeg,.png" multiple>
                            <a href="#" class="view-example">Ver ejemplo</a>
                        </div>

                        <div class="pr-upload-item">
                            <label>Documentos Adicionales</label>
                            <input type="file" name="documentos_adicionales[]" accept=".pdf,.jpg,.jpeg,.png" multiple>
                            <a href="#" class="view-example">Ver ejemplo</a>
                        </div>
                    </div>

                    <!-- Autorización y firma -->
                    <div class="pr-auth-layout">
                        <div class="pr-auth-document">
                            <h4>Autorización para Trámite</h4>
                            <p>Al firmar este documento, autorizo expresamente a <strong>Tramitfy S.L.</strong> para:</p>
                            <ul>
                                <li>Realizar en mi nombre el trámite de registro bajo bandera polaca</li>
                                <li>Presentar la documentación ante las autoridades competentes</li>
                                <li>Recibir comunicaciones oficiales relacionadas con el trámite</li>
                                <li>Gestionar los pagos de tasas oficiales requeridas</li>
                            </ul>
                            <p><strong>Solicitante:</strong> <span id="document-customer-name">Nombre pendiente</span></p>
                            <p><strong>DNI:</strong> <span id="document-customer-dni">DNI pendiente</span></p>
                        </div>

                        <div class="pr-auth-signature-area">
                            <div class="pr-signature-label">Firma Digital *</div>

                            <!-- Botón ampliar para móvil -->
                            <button type="button" class="pr-expand-signature-btn" onclick="openSignatureModal()">
                                <i class="fas fa-expand"></i> Ampliar para Firmar
                            </button>

                            <!-- Canvas de firma (oculto en móvil) -->
                            <div class="pr-signature-container">
                                <canvas id="signature-pad" width="400" height="180"></canvas>
                                <div class="pr-signature-guide">
                                    FIRME AQUÍ
                                </div>
                            </div>

                            <div class="pr-signature-controls">
                                <button type="button" class="pr-clear-signature" onclick="clearSignature()">
                                    <i class="fas fa-eraser"></i> Borrar
                                </button>
                                <button type="button" class="pr-confirm-signature" id="confirm-signature-btn" onclick="confirmSignature()" disabled>
                                    <i class="fas fa-check"></i> Confirmar
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="pr-form-actions">
                        <button type="button" class="pr-btn pr-btn-secondary" onclick="showPage('page-personal')">
                            <i class="fas fa-arrow-left"></i> Atrás
                        </button>
                        <button type="button" class="pr-btn pr-btn-primary" onclick="showPage('page-payment')">
                            Continuar <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <!-- PÁGINA 4: Pago -->
                <div id="page-payment" class="pr-form-page hidden">
                    <h3>Resumen y Pago</h3>

                    <!-- Resumen del pedido -->
                    <div class="pr-payment-summary">
                        <h4>Resumen de su trámite</h4>
                        <div class="pr-summary-line">
                            <span>Tipo de trámite:</span>
                            <span id="summary-tramite-type">-</span>
                        </div>
                        <div class="pr-summary-line">
                            <span>Precio base:</span>
                            <span id="summary-base-price">-</span>
                        </div>
                        <div id="summary-additional-costs" style="display: none;">
                            <div class="pr-summary-line">
                                <span>Opciones adicionales:</span>
                                <span id="summary-additional-amount">-</span>
                            </div>
                        </div>
                        <div class="pr-summary-line">
                            <span>Tasas oficiales:</span>
                            <span id="summary-taxes">-</span>
                        </div>
                        <div class="pr-summary-line total">
                            <span>Total a pagar:</span>
                            <span id="summary-total">-</span>
                        </div>
                    </div>

                    <!-- Dirección de facturación -->
                    <h4>Dirección de Facturación (Opcional)</h4>
                    <div class="pr-inputs-row">
                        <div class="pr-input-group">
                            <label for="billing_address">Dirección</label>
                            <input type="text" id="billing_address" name="billing_address">
                        </div>
                        <div class="pr-input-group">
                            <label for="billing_city">Ciudad</label>
                            <input type="text" id="billing_city" name="billing_city">
                        </div>
                    </div>

                    <div class="pr-inputs-row">
                        <div class="pr-input-group">
                            <label for="billing_postal_code">Código Postal</label>
                            <input type="text" id="billing_postal_code" name="billing_postal_code">
                        </div>
                        <div class="pr-input-group">
                            <label for="billing_province">Provincia</label>
                            <input type="text" id="billing_province" name="billing_province">
                        </div>
                    </div>

                    <!-- Términos y condiciones -->
                    <div class="pr-checkbox-group" style="margin: 25px 0;">
                        <label>
                            <input type="checkbox" name="terms_accept" id="terms_accept" required>
                            <span>Acepto los <a href="#" target="_blank">términos y condiciones</a> y la <a href="#" target="_blank">política de privacidad</a> *</span>
                        </label>
                    </div>

                    <div class="pr-form-actions">
                        <button type="button" class="pr-btn pr-btn-secondary" onclick="showPage('page-documents')">
                            <i class="fas fa-arrow-left"></i> Atrás
                        </button>
                        <button type="button" class="pr-btn pr-btn-primary" onclick="openPaymentModal()">
                            <i class="fas fa-credit-card"></i> Realizar Pago Seguro
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal de firma fullscreen -->
    <div class="pr-signature-modal" id="signature-modal">
        <div class="pr-signature-modal-content">
            <button class="pr-close-modal" onclick="closeSignatureModal()">&times;</button>
            <h3>Firma Digital</h3>
            <p>Por favor, firme en el área designada usando su dedo o un stylus</p>

            <div style="position: relative;">
                <canvas id="signature-pad-fullscreen" width="700" height="400"></canvas>
                <div class="pr-signature-guide">
                    FIRME AQUÍ
                </div>
            </div>

            <div class="pr-signature-controls">
                <button type="button" class="pr-clear-signature" onclick="clearFullscreenSignature()">
                    <i class="fas fa-eraser"></i> Borrar
                </button>
                <button type="button" class="pr-confirm-signature" id="confirm-fullscreen-signature-btn" onclick="confirmFullscreenSignature()" disabled>
                    <i class="fas fa-check"></i> Confirmar Firma
                </button>
            </div>
        </div>
    </div>

    <!-- Modal de pago -->
    <div class="pr-payment-modal-overlay" id="payment-modal-overlay"></div>
    <div class="pr-payment-modal" id="payment-modal">
        <button class="pr-close-modal" onclick="closePaymentModal()">&times;</button>
        <h3>Pago Seguro</h3>

        <div class="pr-payment-spinner" id="payment-spinner">
            <i class="fas fa-spinner fa-spin"></i> Cargando...
        </div>

        <div id="payment-element"></div>
        <div class="pr-payment-error" id="payment-error"></div>

        <button type="button" class="pr-btn pr-btn-primary" id="confirm-payment-btn" style="width: 100%; margin-top: 20px;" onclick="confirmPayment()">
            <i class="fas fa-lock"></i> Confirmar Pago
        </button>
    </div>

    <script>
        // Variables globales
        let signaturePad, signaturePadFullscreen;
        let stripe, elements, paymentElement;
        let currentTramiteType = '';
        let basePrice = 0;
        let additionalCosts = 0;
        let taxes = 0;

        // Precios de los trámites
        const tramitePrices = {
            'registro': { total: 429.99, taxes: 75.00 },
            'cambio_titularidad': { total: 429.99, taxes: 50.00 },
            'mmsi': { total: 190.00, taxes: 40.00 }
        };

        // Precios de opciones adicionales
        const additionalOptionPrices = {
            'delivery_option': { 'express': 180 },
            'mmsi_option': { 'mmsi_licensed': 170, 'mmsi_unlicensed': 170, 'mmsi_company': 170 },
            'boat_size': { 'size_7_12': 50, 'size_12_24': 100 }
        };

        // Inicialización cuando el DOM esté listo
        document.addEventListener('DOMContentLoaded', function() {
            initializeForm();
            initializeSignature();
            initializeStripe();
        });

        function initializeForm() {
            // Configurar selección de trámite
            const tramiteOptions = document.querySelectorAll('.pr-tramite-option');
            tramiteOptions.forEach(option => {
                option.addEventListener('click', function() {
                    const radio = this.querySelector('input[type="radio"]');
                    radio.checked = true;

                    // Remover selección anterior
                    tramiteOptions.forEach(opt => opt.classList.remove('selected'));
                    this.classList.add('selected');

                    // Actualizar variables globales
                    currentTramiteType = radio.value;
                    updatePricing();
                    showAdditionalOptions();
                });
            });

            // Configurar navegación
            const navItems = document.querySelectorAll('.pr-nav-item');
            navItems.forEach(item => {
                item.addEventListener('click', function() {
                    const targetPage = this.getAttribute('data-page');
                    showPage(targetPage);
                });
            });

            // Configurar opciones adicionales
            const additionalCheckboxes = document.querySelectorAll('.pr-additional-options input[type="checkbox"]');
            additionalCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', updatePricing);
            });

            // Configurar cambios en datos personales
            document.getElementById('customer_name').addEventListener('input', updateAuthDocument);
            document.getElementById('customer_dni').addEventListener('input', updateAuthDocument);
        }

        function initializeSignature() {
            // Configurar canvas de firma principal
            const canvas = document.getElementById('signature-pad');
            if (canvas) {
                signaturePad = new SignaturePad(canvas, {
                    backgroundColor: 'rgb(255, 255, 255)',
                    penColor: 'rgb(0, 0, 0)',
                    minWidth: 0.8,
                    maxWidth: 3.5
                });

                signaturePad.addEventListener('beginStroke', function() {
                    document.getElementById('confirm-signature-btn').disabled = false;
                });
            }

            // Configurar canvas de firma fullscreen
            const canvasFullscreen = document.getElementById('signature-pad-fullscreen');
            if (canvasFullscreen) {
                signaturePadFullscreen = new SignaturePad(canvasFullscreen, {
                    backgroundColor: 'rgb(255, 255, 255)',
                    penColor: 'rgb(0, 0, 0)',
                    minWidth: 1.0,
                    maxWidth: 4.0
                });

                signaturePadFullscreen.addEventListener('beginStroke', function() {
                    document.getElementById('confirm-fullscreen-signature-btn').disabled = false;
                });
            }

            // Redimensionar canvas cuando sea necesario
            setTimeout(resizeCanvas, 100);
        }

        function resizeCanvas() {
            if (signaturePad) {
                const canvas = document.getElementById('signature-pad');
                const rect = canvas.getBoundingClientRect();
                const devicePixelRatio = window.devicePixelRatio || 1;

                canvas.width = rect.width * devicePixelRatio;
                canvas.height = rect.height * devicePixelRatio;

                const ctx = canvas.getContext('2d');
                ctx.scale(devicePixelRatio, devicePixelRatio);

                signaturePad.clear();
            }
        }

        function initializeStripe() {
            stripe = Stripe('<?php echo $stripe_public_key; ?>');
        }

        function showPage(pageId) {
            // Ocultar todas las páginas
            const pages = document.querySelectorAll('.pr-form-page');
            pages.forEach(page => page.classList.add('hidden'));

            // Mostrar página seleccionada
            document.getElementById(pageId).classList.remove('hidden');

            // Actualizar navegación
            const navItems = document.querySelectorAll('.pr-nav-item');
            navItems.forEach(item => item.classList.remove('active'));
            document.querySelector(`[data-page="${pageId}"]`).classList.add('active');

            // Actualizar sidebar
            updateSidebar(pageId);

            // Acciones específicas por página
            if (pageId === 'page-documents') {
                updateAuthDocument();
                setTimeout(resizeCanvas, 100);
            } else if (pageId === 'page-payment') {
                updatePaymentSummary();
            }
        }

        function updateSidebar(pageId) {
            // Ocultar todos los contenidos del sidebar
            document.getElementById('sidebar-selection').style.display = 'none';
            document.getElementById('sidebar-default').style.display = 'none';
            document.getElementById('sidebar-authorization').style.display = 'none';

            if (pageId === 'page-selection') {
                document.getElementById('sidebar-selection').style.display = 'block';
            } else if (pageId === 'page-documents') {
                document.getElementById('sidebar-authorization').style.display = 'block';
            } else {
                document.getElementById('sidebar-default').style.display = 'block';
            }
        }

        function showAdditionalOptions() {
            // Ocultar todas las opciones adicionales
            document.getElementById('additional-options-registro').style.display = 'none';
            document.getElementById('additional-options-cambio').style.display = 'none';

            // Mostrar opciones según el tipo de trámite
            if (currentTramiteType === 'registro') {
                document.getElementById('additional-options-registro').style.display = 'block';
            } else if (currentTramiteType === 'cambio_titularidad') {
                document.getElementById('additional-options-cambio').style.display = 'block';
            }
        }

        function updatePricing() {
            if (!currentTramiteType || !tramitePrices[currentTramiteType]) return;

            basePrice = tramitePrices[currentTramiteType].total;
            taxes = tramitePrices[currentTramiteType].taxes;
            additionalCosts = 0;

            // Calcular costos adicionales
            const additionalCheckboxes = document.querySelectorAll('.pr-additional-options input[type="checkbox"]:checked');
            additionalCheckboxes.forEach(checkbox => {
                const optionType = checkbox.name;
                const optionValue = checkbox.value;

                if (additionalOptionPrices[optionType] && additionalOptionPrices[optionType][optionValue]) {
                    additionalCosts += additionalOptionPrices[optionType][optionValue];
                }
            });

            const totalPrice = basePrice + additionalCosts;

            // Actualizar sidebar
            document.getElementById('sidebar-price').textContent = `€ ${totalPrice.toFixed(2)}`;
        }

        function updateAuthDocument() {
            const customerName = document.getElementById('customer_name').value || 'Nombre pendiente';
            const customerDni = document.getElementById('customer_dni').value || 'DNI pendiente';

            document.getElementById('document-customer-name').textContent = customerName;
            document.getElementById('document-customer-dni').textContent = customerDni;
            document.getElementById('auth-customer-name').textContent = customerName;
            document.getElementById('auth-customer-dni').textContent = customerDni;
        }

        function updatePaymentSummary() {
            if (!currentTramiteType) return;

            const tramiteDescriptions = {
                'registro': 'Registro bajo bandera polaca',
                'cambio_titularidad': 'Cambio de titularidad',
                'mmsi': 'Número MMSI polaco'
            };

            document.getElementById('summary-tramite-type').textContent = tramiteDescriptions[currentTramiteType];
            document.getElementById('summary-base-price').textContent = `€ ${basePrice.toFixed(2)}`;
            document.getElementById('summary-taxes').textContent = `€ ${taxes.toFixed(2)}`;

            if (additionalCosts > 0) {
                document.getElementById('summary-additional-costs').style.display = 'block';
                document.getElementById('summary-additional-amount').textContent = `€ ${additionalCosts.toFixed(2)}`;
            } else {
                document.getElementById('summary-additional-costs').style.display = 'none';
            }

            const totalPrice = basePrice + additionalCosts;
            document.getElementById('summary-total').textContent = `€ ${totalPrice.toFixed(2)}`;
        }

        // Funciones de firma
        function openSignatureModal() {
            document.getElementById('signature-modal').style.display = 'flex';
            // Ocultar WhatsApp si existe
            const whatsapp = document.querySelector('.wp-whatsapp-chat');
            if (whatsapp) whatsapp.style.display = 'none';
        }

        function closeSignatureModal() {
            document.getElementById('signature-modal').style.display = 'none';
            // Mostrar WhatsApp si existe
            const whatsapp = document.querySelector('.wp-whatsapp-chat');
            if (whatsapp) whatsapp.style.display = 'block';
        }

        function clearSignature() {
            if (signaturePad) {
                signaturePad.clear();
                document.getElementById('confirm-signature-btn').disabled = true;
            }
        }

        function clearFullscreenSignature() {
            if (signaturePadFullscreen) {
                signaturePadFullscreen.clear();
                document.getElementById('confirm-fullscreen-signature-btn').disabled = true;
            }
        }

        function confirmSignature() {
            if (signaturePad && !signaturePad.isEmpty()) {
                alert('Firma confirmada correctamente.');
            }
        }

        function confirmFullscreenSignature() {
            if (signaturePadFullscreen && !signaturePadFullscreen.isEmpty()) {
                // Transferir firma al canvas principal
                if (signaturePad) {
                    const fullscreenData = signaturePadFullscreen.toDataURL();
                    const img = new Image();
                    img.onload = function() {
                        const canvas = document.getElementById('signature-pad');
                        const ctx = canvas.getContext('2d');
                        ctx.clearRect(0, 0, canvas.width, canvas.height);
                        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                    };
                    img.src = fullscreenData;
                }

                closeSignatureModal();
                document.getElementById('confirm-signature-btn').disabled = false;
                alert('Firma confirmada correctamente.');
            }
        }

        // Funciones de pago
        function openPaymentModal() {
            // Validar formulario antes de abrir el modal
            if (!validateForm()) return;

            document.getElementById('payment-modal-overlay').style.display = 'block';
            document.getElementById('payment-modal').style.display = 'block';

            if (!paymentElement) {
                initializePaymentElement();
            }
        }

        function closePaymentModal() {
            document.getElementById('payment-modal-overlay').style.display = 'none';
            document.getElementById('payment-modal').style.display = 'none';
        }

        function initializePaymentElement() {
            const totalAmount = basePrice + additionalCosts;

            document.getElementById('payment-spinner').style.display = 'block';

            elements = stripe.elements();
            paymentElement = elements.create('payment', {
                amount: Math.round(totalAmount * 100), // Stripe usa centavos
                currency: 'eur',
                paymentMethodCreation: 'manual'
            });

            paymentElement.mount('#payment-element');

            paymentElement.on('ready', function() {
                document.getElementById('payment-spinner').style.display = 'none';
            });

            paymentElement.on('change', function(event) {
                const errorElement = document.getElementById('payment-error');
                if (event.error) {
                    errorElement.textContent = event.error.message;
                    errorElement.style.display = 'block';
                } else {
                    errorElement.style.display = 'none';
                }
            });
        }

        function confirmPayment() {
            if (!validateForm()) return;

            const confirmButton = document.getElementById('confirm-payment-btn');
            confirmButton.disabled = true;
            confirmButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';

            const totalAmount = basePrice + additionalCosts;

            stripe.createPaymentMethod({
                elements: elements
            }).then(function(result) {
                if (result.error) {
                    showPaymentError(result.error.message);
                    confirmButton.disabled = false;
                    confirmButton.innerHTML = '<i class="fas fa-lock"></i> Confirmar Pago';
                } else {
                    // Crear Payment Intent
                    return stripe.confirmPayment({
                        elements: elements,
                        confirmParams: {
                            return_url: window.location.href
                        },
                        redirect: 'if_required'
                    });
                }
            }).then(function(result) {
                if (result.error) {
                    showPaymentError(result.error.message);
                    confirmButton.disabled = false;
                    confirmButton.innerHTML = '<i class="fas fa-lock"></i> Confirmar Pago';
                } else {
                    // Pago exitoso, enviar formulario
                    processFormSubmission(result.paymentIntent.id);
                }
            });
        }

        function showPaymentError(message) {
            const errorElement = document.getElementById('payment-error');
            errorElement.textContent = message;
            errorElement.style.display = 'block';
        }

        function validateForm() {
            // Validar que se haya seleccionado un trámite
            if (!currentTramiteType) {
                alert('Por favor, seleccione un tipo de trámite.');
                showPage('page-selection');
                return false;
            }

            // Validar datos personales
            const requiredFields = ['customer_name', 'customer_dni', 'customer_email', 'customer_phone'];
            for (let field of requiredFields) {
                if (!document.getElementById(field).value.trim()) {
                    alert('Por favor, complete todos los campos obligatorios.');
                    showPage('page-personal');
                    return false;
                }
            }

            // Validar firma
            if (!signaturePad || signaturePad.isEmpty()) {
                alert('Por favor, proporcione su firma digital.');
                showPage('page-documents');
                return false;
            }

            // Validar términos y condiciones
            if (!document.getElementById('terms_accept').checked) {
                alert('Debe aceptar los términos y condiciones para continuar.');
                showPage('page-payment');
                return false;
            }

            return true;
        }

        function processFormSubmission(paymentIntentId) {
            const formData = new FormData();
            const form = document.getElementById('polish-registration-form');

            // Agregar datos básicos
            formData.append('tramite_type', currentTramiteType);
            formData.append('paymentIntentId', paymentIntentId);
            formData.append('finalAmount', (basePrice + additionalCosts).toFixed(2));

            // Agregar campos del formulario
            const inputs = form.querySelectorAll('input[type="text"], input[type="email"], input[type="tel"], select');
            inputs.forEach(input => {
                if (input.value.trim()) {
                    formData.append(input.name, input.value);
                }
            });

            // Agregar opciones adicionales
            const checkboxes = form.querySelectorAll('input[type="checkbox"]:checked');
            checkboxes.forEach(checkbox => {
                if (checkbox.name !== 'terms_accept') {
                    formData.append(`option_${checkbox.name}`, checkbox.value);
                }
            });

            // Agregar archivos
            const fileInputs = form.querySelectorAll('input[type="file"]');
            fileInputs.forEach(input => {
                for (let file of input.files) {
                    formData.append(input.name, file);
                }
            });

            // Agregar firma
            if (signaturePad && !signaturePad.isEmpty()) {
                const signatureDataURL = signaturePad.toDataURL();
                formData.append('signature', signatureDataURL);
            }

            // Enviar al webhook
            fetch('<?php echo TRAMITFY_API_URL; ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(`¡Trámite enviado con éxito! ID: ${data.tramiteId}`);
                    window.location.href = `https://46-202-128-35.sslip.io/seguimiento/${data.id}`;
                } else {
                    alert('Error al procesar el trámite: ' + (data.error || 'Error desconocido'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error de conexión. Por favor, inténtelo de nuevo.');
            })
            .finally(() => {
                const confirmButton = document.getElementById('confirm-payment-btn');
                confirmButton.disabled = false;
                confirmButton.innerHTML = '<i class="fas fa-lock"></i> Confirmar Pago';
                closePaymentModal();
            });
        }

        <?php if (current_user_can('administrator')): ?>
        // Auto-rellenado para administradores
        document.getElementById('admin-autofill-btn').addEventListener('click', function() {
            alert('Iniciando auto-rellenado del formulario...');

            // Seleccionar primer trámite
            const firstTramite = document.querySelector('.pr-tramite-option[data-tramite="registro"]');
            if (firstTramite) {
                firstTramite.click();
            }

            // Rellenar datos personales
            document.getElementById('customer_name').value = 'Admin Test';
            document.getElementById('customer_dni').value = '12345678Z';
            document.getElementById('customer_email').value = 'joanpinyol@hotmail.es';
            document.getElementById('customer_phone').value = '682246937';

            // Simular firma
            setTimeout(() => {
                if (signaturePad) {
                    const canvas = document.getElementById('signature-pad');
                    const ctx = canvas.getContext('2d');
                    ctx.font = '30px cursive';
                    ctx.fillText('Admin Test', 50, 100);
                    document.getElementById('confirm-signature-btn').disabled = false;
                }
            }, 500);

            // Marcar términos y condiciones
            setTimeout(() => {
                document.getElementById('terms_accept').checked = true;
            }, 800);

            // Navegar automáticamente
            setTimeout(() => {
                showPage('page-personal');
                setTimeout(() => {
                    showPage('page-documents');
                    setTimeout(() => {
                        showPage('page-payment');
                        alert('Formulario auto-rellenado. Los archivos deben subirse manualmente y el pago se procesa con Stripe.');
                    }, 1000);
                }, 1000);
            }, 1000);
        });
        <?php endif; ?>
    </script>

    <?php
    return ob_get_clean();
}

// Registrar el shortcode
add_shortcode('polish_registration_form', 'polish_registration_form_shortcode');

?>