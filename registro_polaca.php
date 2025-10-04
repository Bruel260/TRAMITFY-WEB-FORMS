<?php
// Versión formulario polaca: 2.1.0-1759083869 - Mejoras táctiles + WhatsApp ocultado
// Asegurarse de que el archivo no sea accedido directamente
defined('ABSPATH') || exit;

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
    // Configuración de Stripe - movido dentro de la función para evitar conflictos con Elementor
    if (!defined('POLISH_REGISTRATION_STRIPE_MODE')) {
        define('POLISH_REGISTRATION_STRIPE_MODE', 'test'); // 'test' o 'live'
        define('POLISH_REGISTRATION_STRIPE_TEST_PUBLIC_KEY', 'STRIPE_PUBLIC_KEY_PLACEHOLDER');
        define('POLISH_REGISTRATION_STRIPE_TEST_SECRET_KEY', 'STRIPE_SECRET_KEY_PLACEHOLDER');
        define('POLISH_REGISTRATION_STRIPE_LIVE_PUBLIC_KEY', 'STRIPE_LIVE_PUBLIC_KEY_PLACEHOLDER');
        define('POLISH_REGISTRATION_STRIPE_LIVE_SECRET_KEY', 'STRIPE_LIVE_SECRET_KEY_PLACEHOLDER');
        define('POLISH_REGISTRATION_TRAMITFY_API_URL', 'https://46-202-128-35.sslip.io/api/herramientas/polaca/webhook');
    }

    // Seleccionar las claves según el modo
    $stripe_public_key = (POLISH_REGISTRATION_STRIPE_MODE === 'live') ? POLISH_REGISTRATION_STRIPE_LIVE_PUBLIC_KEY : POLISH_REGISTRATION_STRIPE_TEST_PUBLIC_KEY;

    // Version para forzar actualización de caché
    $version = '2.1.0-' . time();

    // Encolar los scripts y estilos necesarios
    wp_enqueue_style('polish-registration-form-style', get_template_directory_uri() . '/style.css', array(), $version);
    wp_enqueue_script('stripe', 'https://js.stripe.com/v3/', array(), null, false);
    wp_enqueue_script('signature-pad', 'https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js', array(), null, false);
    wp_enqueue_script('jspdf', 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js', array(), null, false);

    // Iniciar el buffering de salida
    ob_start();
    ?>

    <!-- Formulario Bandera Polaca v2.1.0 - <?php echo date('Y-m-d H:i:s'); ?> -->
    <!-- Mejoras: Firma táctil optimizada + WhatsApp Ninja ocultado -->

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
            grid-template-columns: 420px 1fr;
            align-items: stretch;
            min-height: fit-content;
        }

        /* SIDEBAR IZQUIERDO */
        .pr-sidebar {
            background: linear-gradient(180deg, rgb(var(--primary)) 0%, rgb(var(--primary-dark)) 100%);
            color: white;
            padding: 20px 20px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            min-height: 100%;
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

        /* Sidebar de beneficios/ventajas */
        .pr-benefits-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin: 15px 0;
        }

        .pr-benefit-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 12px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.15);
            transition: all 0.3s ease;
        }

        .pr-benefit-item:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: translateY(-2px);
        }

        .pr-benefit-icon {
            width: 32px;
            height: 32px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 14px;
            flex-shrink: 0;
        }

        .pr-benefit-content {
            flex: 1;
        }

        .pr-benefit-title {
            font-size: 13px;
            font-weight: 600;
            color: white;
            margin-bottom: 3px;
            line-height: 1.3;
        }

        .pr-benefit-desc {
            font-size: 11px;
            color: rgba(255, 255, 255, 0.85);
            line-height: 1.4;
        }

        .pr-confidence-badge {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.2), rgba(255, 255, 255, 0.1));
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 10px;
            padding: 15px;
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 20px;
        }

        .pr-confidence-badge i {
            font-size: 20px;
            color: #ffd700;
        }

        .pr-confidence-text {
            flex: 1;
        }

        .pr-confidence-text > div:first-child {
            font-size: 13px;
            font-weight: 600;
            color: white;
            margin-bottom: 2px;
        }

        .pr-confidence-subtitle {
            font-size: 11px;
            color: rgba(255, 255, 255, 0.8);
        }

        /* Widgets de reseñas */
        .pr-reviews-widget {
            margin-top: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 12px;
            border: 1px solid rgb(var(--neutral-200));
        }

        .pr-sidebar-reviews {
            margin-top: 20px;
            padding: 15px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, 0.15);
        }

        .pr-reviews-widget-summary {
            margin-top: 25px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 12px;
            border: 1px solid rgb(var(--neutral-200));
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
            min-height: 100%;
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

        /* Nuevos estilos para sidebar informativo */
        .pr-info-section {
            margin: 20px 0;
            padding: 15px;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 10px;
        }
        
        .pr-info-section h3 {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .pr-info-section h3 i {
            font-size: 16px;
            color: rgba(255, 255, 255, 0.9);
        }
        
        .pr-info-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .pr-info-list li {
            font-size: 12px;
            line-height: 1.6;
            margin-bottom: 8px;
            padding-left: 20px;
            position: relative;
            color: rgba(255, 255, 255, 0.85);
        }
        
        .pr-info-list li:before {
            content: "•";
            position: absolute;
            left: 8px;
            color: rgba(255, 255, 255, 0.5);
        }
        
        .pr-info-list li strong {
            color: rgba(255, 255, 255, 0.95);
        }
        
        .pr-info-section p {
            font-size: 13px;
            line-height: 1.5;
            color: rgba(255, 255, 255, 0.85);
            margin: 0;
        }
        
        /* Estilos para tracking en tiempo real */
        .pr-tracking-container {
            padding: 15px 0;
        }
        
        /* Botón de editar selección en sidebar */
        .pr-btn-edit-selection-sidebar {
            width: 100%;
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 12px;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin: 15px 0;
        }
        
        .pr-btn-edit-selection-sidebar:hover {
            background: rgba(255, 255, 255, 0.25);
            border-color: rgba(255, 255, 255, 0.5);
            transform: translateY(-1px);
        }
        
        .pr-btn-edit-selection-sidebar i {
            font-size: 14px;
        }
        
        /* Estilos para página de resumen compacta */
        .pr-summary-compact {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 25px;
            border: 1px solid #e9ecef;
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .pr-summary-left h4,
        .pr-summary-right h4 {
            margin: 0 0 15px 0;
            color: rgb(var(--primary));
            font-size: 16px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .pr-services-list {
            margin-bottom: 15px;
        }
        
        .pr-service-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 6px 0;
            font-size: 14px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .pr-service-item:last-child {
            border-bottom: none;
        }
        
        .pr-service-label {
            font-weight: 500;
            color: rgb(var(--neutral-800));
        }
        
        .pr-service-price {
            color: rgb(var(--primary));
            font-weight: 600;
        }
        
        .pr-summary-info {
            background: rgba(var(--primary), 0.05);
            padding: 12px;
            border-radius: 8px;
            font-size: 13px;
            color: rgb(var(--neutral-600));
            line-height: 1.4;
        }
        
        .pr-price-compact {
            margin-bottom: 15px;
        }
        
        .pr-price-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 4px 0;
            font-size: 13px;
        }
        
        .pr-price-row.base {
            font-weight: 600;
            color: rgb(var(--neutral-800));
            border-bottom: 1px solid #e9ecef;
            padding-bottom: 8px;
            margin-bottom: 8px;
        }
        
        .pr-price-row.additional {
            color: rgb(var(--neutral-600));
        }
        
        .pr-total-final {
            background: rgb(var(--primary));
            color: white;
            padding: 12px;
            border-radius: 8px;
            text-align: center;
            font-size: 24px;
            font-weight: 700;
        }
        
        /* Responsive para resumen compacto */
        @media (max-width: 768px) {
            .pr-summary-compact {
                grid-template-columns: 1fr;
                gap: 20px;
                padding: 20px;
            }
            
            .pr-total-final {
                font-size: 20px;
            }

            /* Beneficios responsive */
            .pr-benefit-item {
                padding: 10px;
                gap: 10px;
            }

            .pr-benefit-icon {
                width: 28px;
                height: 28px;
                font-size: 12px;
            }

            .pr-benefit-title {
                font-size: 12px;
            }

            .pr-benefit-desc {
                font-size: 10px;
            }

            .pr-confidence-badge {
                padding: 12px;
                gap: 10px;
            }

            .pr-confidence-badge i {
                font-size: 18px;
            }

            .pr-confidence-text > div:first-child {
                font-size: 12px;
            }

            .pr-confidence-subtitle {
                font-size: 10px;
            }

            /* Widgets de reseñas responsive */
            .pr-reviews-widget {
                margin-top: 20px;
                padding: 15px;
            }

            .pr-sidebar-reviews {
                margin-top: 15px;
                padding: 12px;
            }

            .pr-reviews-widget-summary {
                margin-top: 20px;
                padding: 15px;
            }
        }
        
        .pr-tracking-item {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 10px;
            border-left: 3px solid rgb(var(--primary));
        }
        
        .pr-tracking-label {
            font-size: 11px;
            text-transform: uppercase;
            opacity: 0.7;
            margin-bottom: 4px;
        }
        
        .pr-tracking-value {
            font-size: 14px;
            font-weight: 600;
        }
        
        .pr-tracking-price {
            font-size: 12px;
            color: rgb(var(--success));
            margin-top: 4px;
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

        /* ESTILOS MODERNOS PARA OPCIONES DINÁMICAS */
        .option-group {
            margin-top: 20px;
            padding: 20px;
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border-radius: 12px;
            border: 1px solid #e9ecef;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }

        .option-group:hover {
            box-shadow: 0 4px 15px rgba(1, 109, 134, 0.1);
            border-color: rgba(1, 109, 134, 0.2);
        }

        .option-group h4 {
            margin-top: 0;
            margin-bottom: 8px;
            color: var(--primary-color);
            font-weight: bold;
            font-size: 1.2em;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .option-description {
            margin-bottom: 15px;
            color: #666;
            font-size: 0.9em;
            line-height: 1.4;
            font-style: italic;
        }

        .option-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 12px;
        }

        .option-card {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 0;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .option-card:hover {
            border-color: var(--primary-color);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(1, 109, 134, 0.15);
        }

        .option-card label {
            display: block;
            cursor: pointer;
            margin: 0;
            padding: 0;
            height: 100%;
        }

        .option-card input[type="radio"],
        .option-card input[type="checkbox"] {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }

        .option-content {
            padding: 16px;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .option-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 8px;
        }

        .option-icon {
            font-size: 1.5em;
            margin-right: 8px;
            filter: grayscale(100%);
            transition: filter 0.3s ease;
        }

        .option-label {
            flex-grow: 1;
            font-weight: 600;
            color: #333;
            font-size: 1em;
        }

        .option-price {
            font-weight: bold;
            color: var(--primary-color);
            font-size: 0.9em;
            background: rgba(1, 109, 134, 0.1);
            padding: 4px 8px;
            border-radius: 12px;
            white-space: nowrap;
        }

        .option-description-text {
            color: #666;
            font-size: 0.85em;
            line-height: 1.3;
            margin-top: auto;
        }

        /* Estados seleccionados */
        .option-card:has(input:checked) {
            border-color: var(--primary-color);
            background: linear-gradient(135deg, #ffffff 0%, rgba(1, 109, 134, 0.05) 100%);
            box-shadow: 0 4px 15px rgba(1, 109, 134, 0.2);
        }

        .option-card:has(input:checked) .option-icon {
            filter: none;
            transform: scale(1.1);
        }

        .option-card:has(input:checked) .option-label {
            color: var(--primary-color);
        }

        .option-card:has(input:checked) .option-price {
            background: var(--primary-color);
            color: white;
        }

        .option-card:has(input:checked)::before {
            content: '✓';
            position: absolute;
            top: 8px;
            right: 8px;
            background: var(--primary-color);
            color: white;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
            z-index: 10;
        }

        /* Precios especiales */
        .option-price:contains('+') {
            color: #e74c3c;
        }

        .option-price:contains('Gratis') {
            color: #27ae60;
        }

        .option-price:contains('Incluido') {
            color: #27ae60;
            background: rgba(39, 174, 96, 0.1);
        }

        /* Responsive para opciones */
        @media (max-width: 768px) {
            .option-cards {
                grid-template-columns: 1fr;
            }
            
            .option-card {
                margin-bottom: 10px;
            }

            .option-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }

            .option-icon {
                align-self: center;
                margin-bottom: 4px;
            }
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

        .pr-section-header {
            margin: 30px 0 20px 0;
            padding-bottom: 15px;
            border-bottom: 2px solid rgb(var(--neutral-200));
        }

        .pr-section-header h4 {
            margin: 0 0 8px 0;
            color: rgb(var(--primary));
            font-size: 18px;
            font-weight: 600;
        }

        .pr-section-header p {
            margin: 0;
            color: rgb(var(--neutral-600));
            font-size: 14px;
        }

        /* Layout comprimido */
        .pr-compact-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin: 0;
        }

        .pr-compact-section h4 {
            margin: 0 0 10px 0;
            color: rgb(var(--primary));
            font-size: 16px;
            font-weight: 600;
            border-bottom: 2px solid rgb(var(--neutral-200));
            padding-bottom: 6px;
        }

        .pr-compact-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        /* Datos del barco integrados */
        .pr-boat-fields {
            margin: 15px 0 0 0;
            padding: 12px 0;
            border-top: 1px solid rgb(var(--neutral-200));
            transition: all 0.3s ease;
        }

        .pr-boat-fields h5 {
            margin: 0 0 10px 0;
            color: rgb(var(--primary));
            font-size: 14px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .pr-boat-fields h5::before {
            content: '\f6ec';
            font-family: 'Font Awesome 5 Free';
            font-weight: 900;
            color: rgb(var(--primary));
            font-size: 12px;
        }

        .pr-compact-uploads {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .pr-upload-compact {
            background: #f8f9fa;
            border: 2px dashed rgb(var(--neutral-300));
            border-radius: 8px;
            padding: 10px;
            text-align: center;
            transition: all 0.3s ease;
        }

        .pr-upload-compact:hover {
            border-color: rgb(var(--primary));
            background: rgba(var(--primary), 0.05);
        }

        .pr-upload-compact label {
            display: block;
            font-weight: 500;
            margin-bottom: 8px;
            color: rgb(var(--neutral-700));
            font-size: 13px;
        }

        .pr-upload-compact input[type="file"] {
            width: 100%;
            padding: 8px;
            border: none;
            background: transparent;
            font-size: 12px;
        }

        /* Sección de Autorización */
        .pr-authorization-section {
            margin-top: 15px;
            padding: 8px;
            background: #f8f9fa;
            border-radius: 6px;
            border: 1px solid rgb(var(--neutral-200));
        }

        .pr-authorization-section h4 {
            margin: 0 0 6px 0;
            color: rgb(var(--neutral-700));
            font-size: 13px;
            font-weight: 600;
        }

        .pr-authorization-box {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 6px;
            padding: 6px;
            background: white;
            border-radius: 4px;
            border: 1px solid rgb(var(--neutral-200));
        }

        .pr-authorization-text {
            display: flex;
            align-items: center;
            gap: 10px;
            flex: 1;
        }

        .pr-authorization-text i {
            color: rgb(var(--primary));
            font-size: 16px;
        }

        .pr-authorization-text span {
            font-weight: 500;
            color: rgb(var(--neutral-700));
            font-size: 14px;
        }

        .pr-signature-btn {
            white-space: nowrap;
            min-width: 80px;
            padding: 4px 8px;
            font-size: 12px;
        }

        .pr-signature-status {
            margin-top: 6px;
            padding: 6px 8px;
            background: #d4edda;
            color: #155724;
            border-radius: 4px;
            display: flex;
            align-items: center;
            gap: 4px;
            font-weight: 500;
            font-size: 12px;
        }

        .pr-signature-status i {
            color: #28a745;
        }

        /* Estilos específicos para la página de pago */
        #page-payment .pr-form-section {
            margin-bottom: 30px;
        }
        
        #page-payment .pr-form-group {
            margin-bottom: 20px;
        }
        
        #page-payment label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: rgb(var(--neutral-700));
        }
        
        /* Botón de pago único integrado */
        .pr-payment-single-action {
            margin-top: 40px;
            padding-top: 30px;
            border-top: 1px solid rgb(var(--neutral-200));
            display: flex;
            justify-content: center;
        }
        
        .pr-btn-payment-integrated {
            min-width: 250px;
            padding: 14px 28px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.2s ease;
            box-shadow: 0 2px 8px rgba(1, 109, 134, 0.2);
        }
        
        .pr-btn-payment-integrated:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(1, 109, 134, 0.3);
        }
        
        .pr-btn-payment-integrated:disabled {
            transform: none;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.1);
            opacity: 0.7;
        }

        /* Eliminar estilos del contenedor stripe anterior */
        .pr-stripe-section {
            display: none; /* Ya no se usa */
        }

        .pr-stripe-section h3 {
            margin: 0 0 10px 0;
            color: rgb(var(--primary));
            font-size: 24px;
            font-weight: 600;
        }

        /* Eliminar estilos de payment description */
        .pr-payment-description {
            display: none; /* Ya no se usa */
        }

        /* Eliminar estilos del h3 de stripe */
        .pr-stripe-section h3 {
            display: none; /* Ya no se usa */
        }

        /* Eliminar estilos del contenedor stripe */
        .pr-stripe-container {
            display: none; /* Ya no se usa */
        }

        .pr-stripe-field {
            background: white;
            border: 2px solid rgb(var(--neutral-300));
            border-radius: 8px;
            padding: 16px;
            transition: all 0.2s ease;
            min-height: 50px;
        }

        .pr-stripe-field:focus-within {
            border-color: rgb(var(--primary));
            box-shadow: 0 0 0 3px rgba(1, 109, 134, 0.1);
        }

        #payment-element-inline {
            /* Reset any Stripe default styles to match our form */
        }

        .pr-payment-error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
            display: none;
            font-size: 14px;
            border: 2px solid #f5c6cb;
            font-weight: 500;
        }

        /* Estilos eliminados - ya no se usan botones especiales de pago */

        /* Sidebar de pago */
        .pr-payment-breakdown {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 20px;
            margin: 15px 0;
            border: 1px solid rgba(255, 255, 255, 0.15);
            min-width: 320px;
        }

        .pr-payment-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            font-size: 15px;
        }

        .pr-payment-label {
            color: rgba(255, 255, 255, 0.9);
            font-weight: 500;
            flex: 1;
            margin-right: 20px;
            word-wrap: break-word;
        }

        .pr-payment-value {
            color: white;
            font-weight: 600;
            white-space: nowrap;
            min-width: 90px;
            text-align: right;
        }

        .pr-payment-separator {
            height: 1px;
            background: rgba(255, 255, 255, 0.2);
            margin: 10px 0;
        }

        .pr-payment-total {
            border-top: 2px solid rgba(255, 255, 255, 0.4);
            padding-top: 15px;
            margin-top: 15px;
            font-size: 17px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            padding: 15px;
            margin: 15px -5px 0 -5px;
        }

        .pr-payment-total .pr-payment-label {
            color: #ffd700;
            font-weight: 700;
            font-size: 16px;
        }

        .pr-payment-total .pr-payment-value {
            color: #ffd700;
            font-size: 20px;
            font-weight: 700;
            min-width: 100px;
        }

        /* Sistema de firma discreto */
        .pr-signature-section {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #f8f9fa;
            border: 2px solid rgb(var(--neutral-200));
            border-radius: 12px;
            padding: 20px;
            margin: 25px 0;
        }

        .pr-signature-info h4 {
            margin: 0 0 5px 0;
            color: rgb(var(--primary));
            font-size: 16px;
            font-weight: 600;
        }

        .pr-signature-info p {
            margin: 0;
            color: rgb(var(--neutral-600));
            font-size: 13px;
        }

        .pr-signature-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            background: rgb(var(--primary));
            color: white;
            border: none;
            border-radius: 10px;
            padding: 15px 25px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        .pr-signature-btn:hover {
            background: rgb(var(--primary-dark));
            transform: translateY(-2px);
        }

        .pr-signature-btn i {
            font-size: 18px;
        }

        .pr-signature-btn span {
            font-size: 14px;
            font-weight: 600;
        }

        .pr-signature-status {
            font-size: 11px;
            opacity: 0.8;
            background: rgba(255, 255, 255, 0.2);
            padding: 3px 8px;
            border-radius: 10px;
        }

        /* Overlay de firma */
        .pr-signature-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .pr-signature-content {
            background: white;
            border-radius: 12px;
            padding: 25px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .pr-signature-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid rgb(var(--neutral-200));
        }

        .pr-signature-header h4 {
            margin: 0;
            color: rgb(var(--primary));
            font-size: 18px;
        }

        .pr-close-signature {
            background: none;
            border: none;
            font-size: 20px;
            color: rgb(var(--neutral-500));
            cursor: pointer;
            padding: 5px;
            border-radius: 50%;
            width: 35px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .pr-close-signature:hover {
            background: rgb(var(--neutral-100));
            color: rgb(var(--neutral-700));
        }

        .pr-signature-pad-container {
            text-align: center;
            margin: 20px 0;
        }

        .pr-signature-pad-container canvas {
            border: 3px solid rgb(var(--primary));
            border-radius: 8px;
            width: 100%;
            max-width: 500px;
            height: 200px;
        }

        .pr-signature-guide {
            margin-top: 10px;
            color: rgb(var(--neutral-600));
            font-size: 13px;
        }

        /* Sidebar de progreso */
        .pr-progress-sections {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin: 0;
        }

        .pr-progress-section {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 8px;
            border: 1px solid rgba(255, 255, 255, 0.15);
        }

        .pr-progress-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }

        .pr-progress-icon {
            font-size: 16px;
            color: #ffd700;
        }

        .pr-progress-title {
            flex: 1;
            font-size: 14px;
            font-weight: 600;
            color: white;
        }

        .pr-progress-status {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.8);
            background: rgba(255, 255, 255, 0.1);
            padding: 3px 8px;
            border-radius: 10px;
        }

        .pr-progress-bar {
            background: rgba(255, 255, 255, 0.2);
            height: 6px;
            border-radius: 3px;
            margin-bottom: 12px;
            overflow: hidden;
        }

        .pr-progress-fill {
            background: #ffd700;
            height: 100%;
            border-radius: 3px;
            transition: width 0.3s ease;
            width: 0%;
        }

        .pr-progress-items {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .pr-progress-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            color: rgba(255, 255, 255, 0.9);
        }

        .pr-item-indicator {
            font-size: 8px;
            color: rgba(255, 255, 255, 0.5);
        }

        .pr-progress-item.completed .pr-item-indicator {
            color: #ffd700;
        }

        .pr-progress-action {
            margin-top: 10px;
        }

        .pr-signature-progress-btn {
            width: 100%;
            background: rgba(255, 255, 255, 0.15);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 8px;
            padding: 10px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .pr-signature-progress-btn:hover {
            background: rgba(255, 255, 255, 0.25);
            border-color: rgba(255, 255, 255, 0.5);
        }

        .pr-completion-summary {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
            text-align: center;
        }

        .pr-completion-text {
            font-size: 14px;
            font-weight: 600;
            color: white;
            margin-bottom: 8px;
        }

        .pr-completion-bar {
            background: rgba(255, 255, 255, 0.2);
            height: 8px;
            border-radius: 4px;
            overflow: hidden;
        }

        .pr-completion-fill {
            background: linear-gradient(90deg, #ffd700, #ffed4a);
            height: 100%;
            border-radius: 4px;
            transition: width 0.5s ease;
            width: 0%;
        }

        /* Página de firma */
        .pr-signature-intro {
            color: rgb(var(--neutral-600));
            font-size: 15px;
            margin-bottom: 30px;
            text-align: center;
        }

        .pr-signature-main-layout {
            max-width: 800px;
            margin: 0 auto;
        }

        .pr-signature-pad-section h4 {
            color: rgb(var(--primary));
            font-size: 18px;
            margin-bottom: 15px;
            text-align: center;
        }

        .pr-signature-instructions-main {
            background: #f8f9fa;
            border: 1px solid rgb(var(--neutral-200));
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            color: rgb(var(--neutral-700));
        }

        .pr-signature-instructions-main i {
            color: rgb(var(--primary));
            font-size: 16px;
        }

        .pr-signature-canvas-container {
            border: 3px solid rgb(var(--primary));
            border-radius: 12px;
            background: white;
            text-align: center;
            position: relative;
            margin-bottom: 20px;
        }

        .pr-signature-canvas-container canvas {
            display: block;
            margin: 0 auto;
            cursor: crosshair;
        }

        .pr-signature-guide {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: rgb(var(--neutral-400));
            font-size: 18px;
            pointer-events: none;
            opacity: 0.5;
        }

        .pr-signature-controls-main {
            display: flex;
            gap: 15px;
            justify-content: center;
        }

        /* Documento completo en sidebar */
        .pr-auth-document-full {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 15px;
            margin: 15px 0;
            font-size: 11px;
            line-height: 1.4;
            border: 1px solid rgba(255, 255, 255, 0.15);
            max-height: 500px;
            overflow-y: auto;
        }

        /* Documento compacto para sidebar de firma */
        #sidebar-signature .pr-auth-document-full {
            max-height: none;
            overflow-y: visible;
            margin: 10px 0 0 0;
            padding: 15px;
            font-size: 11px;
            line-height: 1.3;
            min-height: calc(50vh - 100px);
        }

        #sidebar-signature .pr-document-section {
            margin-bottom: 12px;
        }

        #sidebar-signature .pr-document-section h5 {
            font-size: 12px;
            margin: 0 0 6px 0;
            padding-bottom: 4px;
            border-bottom: 1px solid rgba(255, 215, 0, 0.3);
        }

        #sidebar-signature .pr-document-field {
            margin-bottom: 4px;
            padding: 2px 0;
        }

        #sidebar-signature .pr-field-label {
            min-width: 85px;
            font-size: 10px;
        }

        #sidebar-signature .pr-field-value {
            font-size: 10px;
            font-weight: 600;
        }

        #sidebar-signature .pr-authorization-list {
            margin: 8px 0;
            padding-left: 14px;
        }

        #sidebar-signature .pr-authorization-list li {
            margin-bottom: 4px;
            font-size: 10px;
            line-height: 1.3;
        }

        #sidebar-signature .pr-signature-area {
            margin-top: 10px;
            padding: 8px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 6px;
        }

        #sidebar-signature .pr-document-section p {
            margin: 6px 0;
            font-size: 10px;
            line-height: 1.3;
        }

        .pr-document-header {
            text-align: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        .pr-document-header h4 {
            margin: 0 0 5px 0;
            font-size: 13px;
            color: white;
            font-weight: 700;
        }

        .pr-document-reference {
            font-size: 10px;
            color: rgba(255, 255, 255, 0.7);
        }

        .pr-document-section {
            margin-bottom: 15px;
        }

        .pr-document-section h5 {
            margin: 0 0 8px 0;
            font-size: 12px;
            color: #ffd700;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .pr-document-field {
            display: flex;
            margin-bottom: 5px;
        }

        .pr-field-label {
            font-weight: 600;
            color: rgba(255, 255, 255, 0.9);
            min-width: 80px;
        }

        .pr-field-value {
            color: white;
            flex: 1;
        }

        .pr-authorization-list {
            margin: 8px 0;
            padding-left: 12px;
        }

        .pr-authorization-list li {
            margin-bottom: 4px;
            color: rgba(255, 255, 255, 0.9);
            font-size: 10px;
            line-height: 1.3;
        }

        .pr-document-footer {
            margin-top: 15px;
            padding-top: 10px;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
        }

        .pr-signature-line {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }

        .pr-signature-placeholder {
            font-style: italic;
            color: rgba(255, 255, 255, 0.6);
            font-size: 10px;
        }

        .pr-date-line {
            display: flex;
            justify-content: space-between;
        }

        .pr-signature-label, .pr-date-label {
            font-weight: 600;
            color: rgba(255, 255, 255, 0.9);
            font-size: 10px;
        }

        .pr-date-value {
            color: white;
            font-size: 10px;
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
            touch-action: none;
            -webkit-user-select: none;
            user-select: none;
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
            touch-action: manipulation;
            -webkit-tap-highlight-color: transparent;
        }

        .pr-expand-signature-btn:active {
            transform: scale(0.98);
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
            touch-action: manipulation;
            -webkit-tap-highlight-color: transparent;
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
            z-index: 9999 !important;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            overflow: hidden;
            overscroll-behavior: contain;
        }

        .pr-signature-modal.active {
            display: flex !important;
        }

        .pr-signature-modal-content {
            background: white;
            border-radius: 12px;
            padding: 20px;
            width: 100%;
            max-width: 800px;
            text-align: center;
            touch-action: pan-y;
            overscroll-behavior: contain;
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
            touch-action: none;
            -webkit-user-select: none;
            user-select: none;
            -webkit-tap-highlight-color: transparent;
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
            margin: 25px 0;
            padding: 18px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }

        .pr-checkbox-group label {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            cursor: pointer;
            font-size: 14px;
            line-height: 1.5;
            color: rgb(var(--neutral-700));
        }

        .pr-checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            margin-top: 2px;
            accent-color: rgb(var(--primary));
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

        .pr-form-navigation {
            display: flex;
            gap: 15px;
            justify-content: space-between;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid rgb(var(--neutral-200));
            clear: both;
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
            z-index: 9997;
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
            z-index: 9996;
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
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 8px;
                padding: 4px;
                margin-bottom: 12px;
            }

            .pr-nav-item {
                padding: 10px 8px;
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

            .pr-section-header {
                margin: 20px 0 15px 0;
                padding-bottom: 12px;
            }

            .pr-section-header h4 {
                font-size: 16px;
            }

            .pr-section-header p {
                font-size: 13px;
            }

            /* Layout comprimido responsive */
            .pr-compact-layout {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .pr-compact-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .pr-compact-section h4 {
                font-size: 15px;
            }

            .pr-upload-compact {
                padding: 12px;
            }

            .pr-authorization-section {
                padding: 12px;
                margin-top: 15px;
                max-height: none;
            }

            .pr-authorization-box {
                flex-direction: column;
                text-align: center;
                gap: 12px;
                padding: 12px;
            }

            .pr-authorization-text {
                justify-content: center;
            }

            .pr-authorization-text span {
                font-size: 13px;
            }

            .pr-signature-btn {
                padding: 10px 16px;
                min-width: auto;
                width: 100%;
                font-size: 13px;
            }

            .pr-signature-content {
                width: 95%;
                padding: 20px;
            }

            .pr-signature-pad-container canvas {
                height: 150px;
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
                touch-action: none;
            }

            .pr-signature-modal {
                padding: 10px;
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

            .pr-form-navigation {
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
                grid-template-columns: 1fr 1fr;
                gap: 6px;
                padding: 3px;
            }

            .pr-nav-item {
                padding: 10px 6px;
                font-size: 10px;
                gap: 3px;
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

            .pr-form-navigation {
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
                touch-action: none;
            }

            .pr-signature-modal-content {
                padding: 10px;
            }

            .pr-signature-modal h3 {
                font-size: 16px;
                margin-bottom: 10px;
            }

            .pr-signature-modal p {
                font-size: 12px;
                margin-bottom: 15px;
            }
        }

        /* ESTILOS PARA SELECCIÓN PROGRESIVA */
        
        /* Área de breadcrumb - ELIMINADO: funcionalidad integrada en sidebar tracking */
        /* 
        .pr-breadcrumb {
            background: linear-gradient(135deg, rgba(var(--primary), 0.05), rgba(var(--primary), 0.02));
            border: 2px solid rgba(var(--primary), 0.1);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
            animation: slideInDown 0.4s ease;
        }
        
        .pr-breadcrumb h3 {
            margin: 0 0 15px 0;
            color: rgb(var(--primary));
            font-size: 16px;
            font-weight: 600;
        }
        
        .pr-breadcrumb-items {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .pr-breadcrumb-item {
            background: rgb(var(--primary));
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            animation: slideInRight 0.3s ease;
        }
        
        
        .pr-breadcrumb-item .price {
            background: rgba(255, 255, 255, 0.2);
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 11px;
            margin-left: 5px;
        }
        */
        
        .pr-btn-edit-selection {
            background: #f8f9fa;
            border: 2px solid rgb(var(--neutral-300));
            color: rgb(var(--neutral-700));
            padding: 10px 16px;
            border-radius: 8px;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .pr-btn-edit-selection:hover {
            border-color: rgb(var(--primary));
            color: rgb(var(--primary));
            background: rgba(var(--primary), 0.02);
        }
        
        /* Área de selección progresiva */
        .pr-progressive-area {
            position: relative;
        }
        
        .pr-selection-step {
            display: none;
            animation: fadeInUp 0.4s ease;
        }
        
        .pr-selection-step.active {
            display: block;
        }
        
        .pr-selection-step h3 {
            margin: 0 0 8px 0;
            color: rgb(var(--neutral-900));
            font-size: 20px;
            font-weight: 600;
        }
        
        .pr-selection-step p {
            margin: 0 0 25px 0;
            color: rgb(var(--neutral-600));
            font-size: 14px;
        }
        
        /* Grid de opciones */
        .pr-options-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .pr-option-card {
            background: white;
            border: 3px solid rgb(var(--neutral-200));
            border-radius: 12px;
            padding: 25px;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            text-align: center;
            transform: translateY(0);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        }
        
        .pr-option-card:hover {
            border-color: rgb(var(--primary));
            background: rgba(var(--primary), 0.02);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(var(--primary), 0.15);
        }
        
        
        .pr-option-card.selected {
            border-color: rgb(var(--primary));
            background: rgba(var(--primary), 0.05);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(var(--primary), 0.2);
        }
        
        
        .pr-option-card.selected::after {
            content: '✓';
            position: absolute;
            top: -8px;
            right: -8px;
            background: rgb(var(--primary));
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
            box-shadow: 0 2px 8px rgba(var(--primary), 0.3);
        }
        
        
        .pr-option-title {
            font-size: 16px;
            font-weight: 600;
            color: rgb(var(--neutral-900));
            margin-bottom: 8px;
        }
        
        .pr-option-price {
            font-size: 20px;
            font-weight: 700;
            color: rgb(var(--primary));
            margin-bottom: 12px;
        }
        
        .pr-option-description {
            font-size: 13px;
            color: rgb(var(--neutral-600));
            line-height: 1.4;
        }
        
        /* Multi-select para servicios adicionales */
        .pr-multi-select .pr-option-card {
            border-width: 2px;
        }
        
        .pr-multi-select .pr-option-card.selected {
            background: rgba(var(--primary), 0.08);
        }
        
        .pr-skip-extras {
            text-align: center;
            margin-top: 20px;
        }
        
        .pr-btn-light {
            background: #f8f9fa;
            border: 2px solid rgb(var(--neutral-300));
            color: rgb(var(--neutral-700));
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .pr-btn-light:hover {
            border-color: rgb(var(--neutral-400));
            background: rgb(var(--neutral-100));
        }
        
        /* Estilos para el resumen final */
        .pr-summary-container {
            background: #f8f9fa;
            border: 2px solid rgb(var(--neutral-200));
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .pr-summary-section {
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid rgb(var(--neutral-200));
        }
        
        .pr-summary-section:last-of-type {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .pr-summary-section h4 {
            margin: 0 0 12px 0;
            font-size: 14px;
            font-weight: 600;
            color: rgb(var(--primary));
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .pr-summary-item {
            background: white;
            border: 1px solid rgb(var(--neutral-200));
            border-radius: 8px;
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .pr-summary-item-title {
            font-weight: 500;
            color: rgb(var(--neutral-900));
            font-size: 15px;
        }
        
        .pr-summary-item-price {
            font-weight: 600;
            color: rgb(var(--primary));
            font-size: 15px;
        }
        
        .pr-summary-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .pr-summary-total {
            background: rgb(var(--primary));
            color: white;
            border-radius: 8px;
            padding: 20px;
            margin-top: 25px;
        }
        
        .pr-total-line {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 18px;
            font-weight: 600;
        }
        
        .pr-total-amount {
            font-size: 24px;
            font-weight: 700;
        }
        
        .pr-summary-actions {
            margin-top: 25px;
            text-align: center;
        }
        
        /* Animaciones */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        /* Responsive para selección progresiva */
        @media (max-width: 768px) {
            .pr-options-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .pr-option-card {
                padding: 20px;
            }
            
            .pr-option-icon {
                font-size: 28px;
                margin-bottom: 12px;
            }
            
            .pr-option-title {
                font-size: 15px;
            }
            
            .pr-option-price {
                font-size: 18px;
            }
            
            /* Breadcrumb móvil - ELIMINADO
            .pr-breadcrumb-items {
                justify-content: center;
            }
            
            .pr-breadcrumb-item {
                font-size: 12px;
                padding: 6px 12px;
            }
            */
        }
        
        @media (max-width: 480px) {
            .pr-selection-step h3 {
                font-size: 18px;
            }
            
            .pr-selection-step p {
                font-size: 13px;
            }
            
            .pr-option-card {
                padding: 15px;
            }
            
            .pr-option-icon {
                font-size: 24px;
                margin-bottom: 10px;
            }
            
            .pr-option-title {
                font-size: 14px;
            }
            
            .pr-option-price {
                font-size: 16px;
            }
            
            .pr-option-description {
                font-size: 12px;
            }
        }
    </style>

    <?php if (current_user_can('administrator')): ?>
    <!-- Panel de auto-rellenado para administradores -->
    <div class="pr-admin-panel">
        <div class="pr-admin-panel-info">
            <div class="pr-admin-panel-title">PANEL ADMINISTRADOR AVANZADO</div>
            <div class="pr-admin-panel-subtitle">Auto-rellena el formulario completo con funcionalidades avanzadas del local</div>
        </div>
        <button type="button" id="admin-autofill-btn" class="pr-admin-autofill-btn">
Auto-rellenar Formulario Completo (Modo TEST)
        </button>
    </div>
    <?php endif; ?>

    <!-- Container principal -->
    <div class="pr-container">
        <!-- Sidebar izquierdo -->
        <div class="pr-sidebar">

            <!-- Contenido informativo inicial -->
            <div id="sidebar-initial" class="pr-sidebar-content">
                <div class="pr-headline">Registro Marítimo Polaco</div>
                <div class="pr-subheadline">Comienza seleccionando el tipo de trámite que necesitas realizar</div>

                <div class="pr-info-section">
                    <h3><i class="fas fa-anchor"></i> Tipos de trámite disponibles:</h3>
                    <ul class="pr-info-list">
                        <li><strong>Registro nuevo:</strong> Para embarcaciones sin bandera previa</li>
                        <li><strong>Cambio de titularidad:</strong> Para cambios de propietario</li>
                        <li><strong>Solicitud MMSI:</strong> Número de identificación marítima</li>
                    </ul>
                </div>


                <div class="pr-info-section">
                    <h3><i class="fas fa-clock"></i> Tiempo estimado:</h3>
                    <p>Entre 15 y 30 días hábiles desde la recepción de toda la documentación</p>
                </div>

                <div class="pr-trust-badges">
                    <div class="pr-badge">
                        <i class="fas fa-shield-alt"></i>
                        Proceso seguro
                    </div>
                    <div class="pr-badge">
                        <i class="fas fa-certificate"></i>
                        100% Oficial
                    </div>
                </div>

                <!-- Widget de reseñas en sidebar inicial -->
                <div class="pr-sidebar-reviews">
                    [trustindex data-widget-id=f4fbfd341d12439e0c86fae7fc2]
                </div>
            </div>

            <!-- Contenido de seguimiento de selección -->
            <div id="sidebar-tracking" class="pr-sidebar-content" style="display: none;">
                <div class="pr-headline">Tu selección</div>
                <div class="pr-tracking-container">
                    <!-- Se llena dinámicamente con JavaScript -->
                </div>
                
                <button type="button" class="pr-btn-edit-selection-sidebar" onclick="resetToStep(0)">
                    <i class="fas fa-edit"></i> Editar selección
                </button>
                
                <div class="pr-price-box" id="tracking-price-box" style="display: none;">
                    <div class="pr-price-label">Precio total</div>
                    <div class="pr-price-amount" id="tracking-price">€ 0.00</div>
                    <div class="pr-price-detail">IVA incluido</div>
                </div>

                <!-- Widget de reseñas en sidebar tracking -->
                <div class="pr-sidebar-reviews">
                    [trustindex data-widget-id=f4fbfd341d12439e0c86fae7fc2]
                </div>
            </div>

            <!-- Sidebar para página de pago con desglose de precio -->
            <div id="sidebar-payment" class="pr-sidebar-content" style="display: none;">
                <div class="pr-headline">Resumen de pago</div>
                <div class="pr-subheadline">Desglose detallado de costes</div>

                <div class="pr-payment-breakdown">
                    <div class="pr-payment-item">
                        <div class="pr-payment-label">Tipo de trámite</div>
                        <div class="pr-payment-value" id="payment-tramite-type">-</div>
                    </div>

                    <div class="pr-payment-item">
                        <div class="pr-payment-label">Precio base</div>
                        <div class="pr-payment-value" id="payment-base-price">€ 0.00</div>
                    </div>

                    <div class="pr-payment-item" id="payment-additional-section" style="display: none;">
                        <div class="pr-payment-label">Opciones adicionales</div>
                        <div class="pr-payment-value" id="payment-additional-amount">€ 0.00</div>
                    </div>

                    <div class="pr-payment-item">
                        <div class="pr-payment-label">Tasas oficiales</div>
                        <div class="pr-payment-value" id="payment-taxes">€ 0.00</div>
                    </div>

                    <div class="pr-payment-separator"></div>

                    <div class="pr-payment-item pr-payment-total">
                        <div class="pr-payment-label">Total a pagar</div>
                        <div class="pr-payment-value" id="payment-total">€ 0.00</div>
                    </div>
                </div>

                <!-- Widget de reseñas en sidebar payment -->
                <div class="pr-sidebar-reviews">
                    [trustindex data-widget-id=f4fbfd341d12439e0c86fae7fc2]
                </div>
            </div>

            <div id="sidebar-progress" class="pr-sidebar-content" style="display: none;">
                <div class="pr-progress-sections">
                    <div class="pr-progress-section" id="progress-personal">
                        <div class="pr-progress-header">
                            <i class="fas fa-user pr-progress-icon"></i>
                            <span class="pr-progress-title">Datos Personales</span>
                            <div class="pr-progress-status" id="status-personal">0/4</div>
                        </div>
                        <div class="pr-progress-bar">
                            <div class="pr-progress-fill" id="fill-personal"></div>
                        </div>
                        <div class="pr-progress-items">
                            <div class="pr-progress-item" id="item-name">
                                <i class="fas fa-circle pr-item-indicator"></i>
                                <span>Nombre completo</span>
                            </div>
                            <div class="pr-progress-item" id="item-dni">
                                <i class="fas fa-circle pr-item-indicator"></i>
                                <span>DNI / Pasaporte</span>
                            </div>
                            <div class="pr-progress-item" id="item-email">
                                <i class="fas fa-circle pr-item-indicator"></i>
                                <span>Email</span>
                            </div>
                            <div class="pr-progress-item" id="item-phone">
                                <i class="fas fa-circle pr-item-indicator"></i>
                                <span>Teléfono</span>
                            </div>
                        </div>
                    </div>

                    <div class="pr-progress-section" id="progress-documents">
                        <div class="pr-progress-header">
                            <i class="fas fa-file-upload pr-progress-icon"></i>
                            <span class="pr-progress-title">Documentación</span>
                            <div class="pr-progress-status" id="status-documents">0/2</div>
                        </div>
                        <div class="pr-progress-bar">
                            <div class="pr-progress-fill" id="fill-documents"></div>
                        </div>
                        <div class="pr-progress-items">
                            <div class="pr-progress-item" id="item-dni-doc">
                                <i class="fas fa-circle pr-item-indicator"></i>
                                <span>DNI / Pasaporte</span>
                            </div>
                            <div class="pr-progress-item" id="item-registro">
                                <i class="fas fa-circle pr-item-indicator"></i>
                                <span>Registro Marítimo</span>
                            </div>
                        </div>
                    </div>

                    <div class="pr-progress-section" id="progress-signature">
                        <div class="pr-progress-header">
                            <i class="fas fa-file-signature pr-progress-icon"></i>
                            <span class="pr-progress-title">Autorización</span>
                            <div class="pr-progress-status" id="status-signature">Pendiente</div>
                        </div>
                        <div class="pr-progress-action">
                            <button type="button" class="pr-signature-progress-btn" onclick="openSignaturePage()">
                                <i class="fas fa-file-signature"></i>
                                Firmar Documento
                            </button>
                        </div>
                    </div>
                </div>

                <div class="pr-completion-summary">
                    <div class="pr-completion-text">
                        <span id="completion-percentage">0%</span> completado
                    </div>
                    <div class="pr-completion-bar">
                        <div class="pr-completion-fill" id="total-completion"></div>
                    </div>
                </div>

                <!-- Widget de reseñas en sidebar progress -->
                <div class="pr-sidebar-reviews">
                    [trustindex data-widget-id=f4fbfd341d12439e0c86fae7fc2]
                </div>
            </div>

            <!-- Sidebar para página de resumen con ventajas -->
            <div id="sidebar-benefits" class="pr-sidebar-content" style="display: none;">
                <div class="pr-headline">¿Por qué elegir Tramitfy?</div>
                <div class="pr-subheadline">Ventajas de realizar tu trámite con nosotros</div>

                <div class="pr-benefits-list">
                    <div class="pr-benefit-item">
                        <div class="pr-benefit-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="pr-benefit-content">
                            <div class="pr-benefit-title">Tramitación Rápida</div>
                            <div class="pr-benefit-desc">Procesamos tu solicitud en 15-30 días hábiles</div>
                        </div>
                    </div>

                    <div class="pr-benefit-item">
                        <div class="pr-benefit-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <div class="pr-benefit-content">
                            <div class="pr-benefit-title">100% Seguro</div>
                            <div class="pr-benefit-desc">Datos protegidos y transacciones encriptadas</div>
                        </div>
                    </div>

                    <div class="pr-benefit-item">
                        <div class="pr-benefit-icon">
                            <i class="fas fa-award"></i>
                        </div>
                        <div class="pr-benefit-content">
                            <div class="pr-benefit-title">Experiencia Probada</div>
                            <div class="pr-benefit-desc">Miles de trámites exitosos realizados</div>
                        </div>
                    </div>

                    <div class="pr-benefit-item">
                        <div class="pr-benefit-icon">
                            <i class="fas fa-euro-sign"></i>
                        </div>
                        <div class="pr-benefit-content">
                            <div class="pr-benefit-title">Precio Transparente</div>
                            <div class="pr-benefit-desc">Sin costes ocultos ni sorpresas</div>
                        </div>
                    </div>
                </div>

                <!-- Widget de reseñas en sidebar -->
                <div class="pr-sidebar-reviews">
                    [trustindex data-widget-id=f4fbfd341d12439e0c86fae7fc2]
                </div>
            </div>

            <!-- Sidebar para firma digital con documento ampliado -->
            <div id="sidebar-signature" class="pr-sidebar-content" style="display: none;">
                <div class="pr-headline">Documento Completo</div>
                <div class="pr-subheadline">Autorización para Tramitación</div>

                <div class="pr-auth-document-full">
                    <div class="pr-document-header">
                        <h4>AUTORIZACIÓN PARA TRÁMITE MARÍTIMO</h4>
                        <div class="pr-document-reference">Ref: POL-REG-2024</div>
                    </div>

                    <div class="pr-document-content">
                        <div class="pr-document-section">
                            <h5>DATOS DEL SOLICITANTE</h5>
                            <div class="pr-document-field">
                                <span class="pr-field-label">Nombre completo:</span>
                                <span class="pr-field-value" id="doc-customer-name">Nombre pendiente</span>
                            </div>
                            <div class="pr-document-field">
                                <span class="pr-field-label">DNI / Pasaporte:</span>
                                <span class="pr-field-value" id="doc-customer-dni">DNI pendiente</span>
                            </div>
                            <div class="pr-document-field">
                                <span class="pr-field-label">Email de contacto:</span>
                                <span class="pr-field-value" id="doc-customer-email">Email pendiente</span>
                            </div>
                        </div>

                        <div class="pr-document-section">
                            <h5>AUTORIZACIÓN</h5>
                            <p>Por la presente, YO, <strong><span id="doc-auth-name">NOMBRE</span></strong>, con DNI <strong><span id="doc-auth-dni">DNI</span></strong>, autorizo expresamente a <strong>TRAMITFY S.L.</strong> para:</p>
                            
                            <ul class="pr-authorization-list">
                                <li>Realizar en mi nombre y representación el trámite de registro bajo bandera polaca</li>
                                <li>Presentar toda la documentación requerida ante las autoridades marítimas competentes</li>
                                <li>Recibir comunicaciones oficiales relacionadas con el proceso de registro</li>
                                <li>Gestionar los pagos de tasas oficiales y administrativas</li>
                                <li>Actuar como mi representante legal en todo el proceso de tramitación</li>
                            </ul>
                        </div>


                        <div class="pr-document-section">
                            <h5>FECHA Y FIRMA</h5>
                            <div class="pr-document-field">
                                <span class="pr-field-label">Fecha de solicitud:</span>
                                <span class="pr-field-value" id="doc-date"></span>
                            </div>
                            <div class="pr-signature-area">
                                <p><strong>FIRMA DEL SOLICITANTE:</strong></p>
                                <div class="pr-signature-box">
                                    <div class="pr-signature-placeholder">Firma aquí en el área principal</div>
                                </div>
                            </div>
                        </div>

                        <div class="pr-document-section">
                            <h5>RESPONSABILIDADES</h5>
                            <p>Declaro que toda la información proporcionada es veraz y completa. Me comprometo a proporcionar cualquier documentación adicional que sea requerida para completar el trámite.</p>
                        </div>

                        <div class="pr-document-footer">
                            <div class="pr-signature-line">
                                <div class="pr-signature-label">Firma del Solicitante:</div>
                                <div class="pr-signature-placeholder">Firma digital en el área principal</div>
                            </div>
                            <div class="pr-date-line">
                                <div class="pr-date-label">Fecha:</div>
                                <div class="pr-date-value" id="signature-date"></div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Área principal del formulario -->
        <div class="pr-form-area">

            <!-- Navegación entre páginas -->
            <div class="pr-navigation">
                <div class="pr-nav-item active" data-page="page-selection">
                    <i class="fas fa-list"></i>
                    Selección
                </div>
                <div class="pr-nav-item" data-page="page-summary">
                    <i class="fas fa-list-alt"></i>
                    Resumen
                </div>
                <div class="pr-nav-item" data-page="page-documents">
                    <i class="fas fa-user-edit"></i>
                    Datos y Documentación
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

                    <!-- ÁREA DE SELECCIÓN PROGRESIVA -->
                    <div id="progressive-selection" class="pr-progressive-area">
                        <!-- Paso 1: Selección de trámite inicial -->
                        <div id="step-tramite" class="pr-selection-step active">
                            <h3>Seleccione el tipo de trámite</h3>
                            <p>Elija el servicio que necesita para su embarcación:</p>
                            
                            <div class="pr-options-grid">
                                <div class="pr-option-card" data-selection="registro" data-step="tramite">
                                    <div class="pr-option-title">Registro Completo</div>
                                    <div class="pr-option-price">€ 429.99</div>
                                    <div class="pr-option-description">
                                        Registro completo de embarcación bajo bandera polaca
                                    </div>
                                </div>
                                
                                <div class="pr-option-card" data-selection="cambio_titularidad" data-step="tramite">
                                    <div class="pr-option-title">Cambio de Titularidad</div>
                                    <div class="pr-option-price">€ 429.99</div>
                                    <div class="pr-option-description">
                                        Transferencia de titularidad de embarcación registrada
                                    </div>
                                </div>
                                
                                <div class="pr-option-card" data-selection="mmsi" data-step="tramite">
                                    <div class="pr-option-title">Número MMSI</div>
                                    <div class="pr-option-price">€ 190.00</div>
                                    <div class="pr-option-description">
                                        Solicitud de número MMSI polaco para comunicaciones
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Paso 2: Tamaño de embarcación -->
                        <div id="step-boatsize" class="pr-selection-step">
                            <h3>Tamaño de su embarcación</h3>
                            <p>Seleccione la categoría que corresponde a su embarcación:</p>
                            
                            <div class="pr-options-grid">
                                <div class="pr-option-card" data-selection="size_0_7" data-step="boatsize" data-price="0">
                                    <div class="pr-option-title">0-7 metros</div>
                                    <div class="pr-option-price">+€ 0</div>
                                    <div class="pr-option-description">
                                        Embarcaciones pequeñas hasta 7 metros de eslora
                                    </div>
                                </div>
                                
                                <div class="pr-option-card" data-selection="size_7_12" data-step="boatsize" data-price="50">
                                    <div class="pr-option-title">7-12 metros</div>
                                    <div class="pr-option-price">+€ 50</div>
                                    <div class="pr-option-description">
                                        Embarcaciones medianas de 7 a 12 metros
                                    </div>
                                </div>
                                
                                <div class="pr-option-card" data-selection="size_12_24" data-step="boatsize" data-price="100">
                                    <div class="pr-option-title">12-24 metros</div>
                                    <div class="pr-option-price">+€ 100</div>
                                    <div class="pr-option-description">
                                        Embarcaciones grandes de 12 a 24 metros
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Paso 3: Opciones MMSI (solo para registro y cambio_titularidad) -->
                        <div id="step-mmsi" class="pr-selection-step">
                            <h3>Opciones MMSI</h3>
                            <p>¿Desea solicitar número MMSI? (Opcional):</p>
                            
                            <div class="pr-options-grid">
                                <div class="pr-option-card" data-selection="no_mmsi" data-step="mmsi" data-price="0">
                                    <div class="pr-option-title">Sin MMSI</div>
                                    <div class="pr-option-price">+€ 0</div>
                                    <div class="pr-option-description">
                                        No solicitar número MMSI
                                    </div>
                                </div>
                                
                                <div class="pr-option-card" data-selection="mmsi_licensed" data-step="mmsi" data-price="170">
                                    <div class="pr-option-title">MMSI Licensed</div>
                                    <div class="pr-option-price">+€ 170</div>
                                    <div class="pr-option-description">
                                        Número MMSI para embarcación con licencia
                                    </div>
                                </div>
                                
                                <div class="pr-option-card" data-selection="mmsi_unlicensed" data-step="mmsi" data-price="170">
                                    <div class="pr-option-title">MMSI Unlicensed</div>
                                    <div class="pr-option-price">+€ 170</div>
                                    <div class="pr-option-description">
                                        Número MMSI para embarcación sin licencia
                                    </div>
                                </div>
                                
                                <div class="pr-option-card" data-selection="mmsi_company" data-step="mmsi" data-price="170">
                                    <div class="pr-option-title">MMSI Company</div>
                                    <div class="pr-option-price">+€ 170</div>
                                    <div class="pr-option-description">
                                        Número MMSI para empresa
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Paso 4: Servicios adicionales -->
                        <div id="step-extras" class="pr-selection-step">
                            <h3>Servicios adicionales</h3>
                            <p>Seleccione los servicios extra que necesite (Opcional):</p>
                            
                            <div class="pr-options-grid pr-multi-select">
                                <div class="pr-option-card" data-selection="apostilla" data-step="extras" data-price="85">
                                    <div class="pr-option-title">Apostilla de La Haya</div>
                                    <div class="pr-option-price">+€ 85</div>
                                    <div class="pr-option-description">
                                        Apostilla oficial para validez internacional
                                    </div>
                                </div>
                                
                                <div class="pr-option-card" data-selection="extracto" data-step="extras" data-price="25">
                                    <div class="pr-option-title">Extracto del Registro</div>
                                    <div class="pr-option-price">+€ 25</div>
                                    <div class="pr-option-description">
                                        Extracto oficial del registro de la embarcación
                                    </div>
                                </div>
                                
                                <div class="pr-option-card" data-selection="bandera_fisica" data-step="extras" data-price="45">
                                    <div class="pr-option-title">Bandera Física</div>
                                    <div class="pr-option-price">+€ 45</div>
                                    <div class="pr-option-description">
                                        Bandera física polaca para la embarcación
                                    </div>
                                </div>
                            </div>
                            
                            <div class="pr-skip-extras">
                                <button type="button" class="pr-btn pr-btn-light" onclick="skipExtras()">
                                    Saltar servicios adicionales
                                </button>
                            </div>
                        </div>

                        <!-- Paso 5: Tipo de entrega -->
                        <div id="step-delivery" class="pr-selection-step">
                            <h3>Tipo de entrega</h3>
                            <p>Seleccione cómo desea recibir su documentación:</p>
                            
                            <div class="pr-options-grid">
                                <div class="pr-option-card" data-selection="delivery_standard" data-step="delivery" data-price="0">
                                    <div class="pr-option-title">Entrega Estándar</div>
                                    <div class="pr-option-price">+€ 0</div>
                                    <div class="pr-option-description">
                                        Envío por correo ordinario (15-20 días)
                                    </div>
                                </div>
                                
                                <div class="pr-option-card" data-selection="delivery_express" data-step="delivery" data-price="25">
                                    <div class="pr-option-title">Entrega Express</div>
                                    <div class="pr-option-price">+€ 25</div>
                                    <div class="pr-option-description">
                                        Envío express certificado (5-7 días)
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- BOTONES DE NAVEGACIÓN -->
                    <div class="pr-form-actions">
                        <button type="button" id="btn-back-step" class="pr-btn pr-btn-secondary" style="display: none;" onclick="goBackStep()">
                            <i class="fas fa-arrow-left"></i> Atrás
                        </button>
                        <button type="button" id="btn-continue" class="pr-btn pr-btn-primary" style="display: none;" onclick="showPage('page-summary')">
                            Continuar <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>

                </div>


                <!-- PÁGINA 3: Datos y Documentación -->
                <div id="page-documents" class="pr-form-page hidden">
                    <!-- Layout comprimido en grid -->
                    <div class="pr-compact-layout">
                        <!-- Columna izquierda: Datos personales y autorización -->
                        <div class="pr-compact-section">
                            <h4>Datos Personales</h4>
                            <div class="pr-compact-grid">
                                <div class="pr-input-group">
                                    <label for="customer_name">Nombre completo *</label>
                                    <input type="text" id="customer_name" name="customer_name" required>
                                </div>
                                <div class="pr-input-group">
                                    <label for="customer_dni">DNI / Pasaporte *</label>
                                    <input type="text" id="customer_dni" name="customer_dni" required>
                                </div>
                                <div class="pr-input-group">
                                    <label for="customer_email">Email *</label>
                                    <input type="email" id="customer_email" name="customer_email" required>
                                </div>
                                <div class="pr-input-group">
                                    <label for="customer_phone">Teléfono *</label>
                                    <input type="tel" id="customer_phone" name="customer_phone" required>
                                </div>
                            </div>

                            <!-- Datos del Barco (solo para registro) -->
                            <div class="pr-boat-fields" id="boat-data-section" style="display: none;">
                                <h5>Datos del Barco</h5>
                                <div class="pr-compact-grid">
                                    <div class="pr-input-group">
                                        <label for="boat_name">Nombre del barco *</label>
                                        <input type="text" id="boat_name" name="boat_name" placeholder="Ej: Mar Azul">
                                    </div>
                                    <div class="pr-input-group">
                                        <label for="boat_registration">Matrícula del barco *</label>
                                        <input type="text" id="boat_registration" name="boat_registration" placeholder="Ej: ES-MAD-12345">
                                    </div>
                                </div>
                            </div>

                            <!-- Sección de Autorización -->
                            <div class="pr-authorization-section">
                                <h4>Autorización Digital</h4>
                                <div class="pr-authorization-box">
                                    <div class="pr-authorization-text">
                                        <i class="fas fa-file-signature"></i>
                                        <span>Autorización para tramitación</span>
                                    </div>
                                    <button type="button" class="pr-btn pr-btn-primary pr-signature-btn" onclick="openSignaturePage()">
                                        <i class="fas fa-pen"></i> Firmar
                                    </button>
                                </div>
                                <div class="pr-signature-status" id="signature-status" style="display: none;">
                                    <i class="fas fa-check-circle"></i>
                                    <span>Documento firmado</span>
                                </div>
                            </div>

                        </div>

                        <!-- Columna derecha: Documentación -->
                        <div class="pr-compact-section">
                            <h4>Documentación</h4>
                            <div class="pr-compact-uploads">
                                <div class="pr-upload-compact">
                                    <label>DNI / Pasaporte *</label>
                                    <input type="file" name="dni_documento[]" accept=".pdf,.jpg,.jpeg,.png" multiple>
                                </div>
                                <div class="pr-upload-compact">
                                    <label>Registro Marítimo *</label>
                                    <input type="file" name="registro_maritimo[]" accept=".pdf,.jpg,.jpeg,.png" multiple>
                                </div>
                                <div class="pr-upload-compact">
                                    <label>Seguro de Embarcación</label>
                                    <input type="file" name="seguro_embarcacion[]" accept=".pdf,.jpg,.jpeg,.png" multiple>
                                </div>
                                <div class="pr-upload-compact">
                                    <label>Documentos Adicionales</label>
                                    <input type="file" name="documentos_adicionales[]" accept=".pdf,.jpg,.jpeg,.png" multiple>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Overlay de firma (oculto inicialmente) -->
                    <div class="pr-signature-overlay" id="signature-overlay" style="display: none;">
                        <div class="pr-signature-content">
                            <div class="pr-signature-header">
                                <h4>Firma Digital</h4>
                                <button type="button" class="pr-close-signature" onclick="closeSignatureMode()">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            
                            <div class="pr-signature-pad-container">
                                <canvas id="signature-pad" width="500" height="200"></canvas>
                                <div class="pr-signature-guide">Firma aquí con tu dedo o stylus</div>
                            </div>
                            
                            <div class="pr-signature-controls">
                                <button type="button" class="pr-btn pr-btn-secondary" onclick="clearSignature()">
                                    <i class="fas fa-eraser"></i> Borrar
                                </button>
                                <button type="button" class="pr-btn pr-btn-primary" id="confirm-signature-btn" onclick="confirmSignature()" disabled>
                                    <i class="fas fa-check"></i> Confirmar Firma
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="pr-form-navigation">
                        <button type="button" class="pr-btn pr-btn-secondary" onclick="showPage('page-summary')">
                            <i class="fas fa-arrow-left"></i> Atrás
                        </button>
                        <button type="button" class="pr-btn pr-btn-primary" onclick="showPage('page-payment')">
                            Continuar <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <!-- PÁGINA DE FIRMA -->
                <div id="page-signature" class="pr-form-page hidden">
                    <h3>Autorización Digital</h3>
                    <p class="pr-signature-intro">Revisa el documento de autorización y firma para completar tu trámite</p>

                    <div class="pr-signature-main-layout">
                        <div class="pr-signature-pad-section">
                            <h4>Área de Firma</h4>
                            <div class="pr-signature-instructions-main">
                                <i class="fas fa-info-circle"></i>
                                <span>Firma en el área designada usando tu dedo o stylus</span>
                            </div>
                            
                            <div class="pr-signature-canvas-container">
                                <canvas id="signature-pad-main" width="600" height="250"></canvas>
                                <div class="pr-signature-guide">Firma aquí</div>
                            </div>
                            
                            <div class="pr-signature-controls-main">
                                <button type="button" class="pr-btn pr-btn-secondary" onclick="clearSignatureMain()">
                                    <i class="fas fa-eraser"></i> Limpiar Firma
                                </button>
                                <button type="button" class="pr-btn pr-btn-primary" id="confirm-signature-main-btn" onclick="confirmSignatureMain()" disabled>
                                    <i class="fas fa-check"></i> Confirmar Firma
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="pr-form-navigation">
                        <button type="button" class="pr-btn pr-btn-secondary" onclick="showPage('page-documents')">
                            <i class="fas fa-arrow-left"></i> Volver a Documentos
                        </button>
                        <button type="button" class="pr-btn pr-btn-primary" id="signature-continue-btn" onclick="showPage('page-payment')" disabled>
                            Continuar <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <!-- PÁGINA 2: Resumen de servicios seleccionados -->
                <div id="page-summary" class="pr-form-page hidden">
                    <h3>Resumen de tu pedido</h3>
                    
                    <!-- Resumen compacto en una sola tarjeta -->
                    <div class="pr-summary-compact">
                        <div class="pr-summary-left">
                            <h4><i class="fas fa-ship"></i> Servicios incluidos</h4>
                            <div id="summary-tramite-details" class="pr-services-list">
                                <!-- Se llena dinámicamente -->
                            </div>
                            <div class="pr-summary-info">
                                <strong>Plazo:</strong> 15-30 días hábiles desde recepción completa de documentación
                            </div>
                        </div>
                        
                        <div class="pr-summary-right">
                            <h4><i class="fas fa-calculator"></i> Total</h4>
                            <div id="summary-price-breakdown" class="pr-price-compact">
                                <!-- Se llena dinámicamente -->
                            </div>
                            <div class="pr-total-final">
                                <span id="summary-final-price">€0.00</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="pr-form-navigation">
                        <button type="button" class="pr-btn pr-btn-secondary" onclick="showPage('page-selection')">
                            <i class="fas fa-arrow-left"></i> Volver a selección
                        </button>
                        <button type="button" class="pr-btn pr-btn-primary" onclick="showPage('page-documents')">
                            Continuar <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <!-- PÁGINA 5: Pago -->
                <div id="page-payment" class="pr-form-page hidden">
                    <!-- Campo de pago directo sin títulos -->
                    <div class="pr-form-section">
                        <div class="pr-form-group">
                            <label>Datos de la tarjeta</label>
                            <div id="payment-element-inline" class="pr-stripe-field"></div>
                            <div class="pr-payment-error" id="payment-error-inline"></div>
                        </div>
                    </div>

                    <!-- Términos como parte del formulario -->
                    <div class="pr-form-section">
                        <div class="pr-form-group">
                            <div class="pr-checkbox-group">
                                <label>
                                    <input type="checkbox" name="terms_accept" id="terms_accept" required>
                                    <span>Acepto los <a href="#" target="_blank">términos y condiciones</a> y la <a href="#" target="_blank">política de privacidad</a> *</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Botón de pago único integrado -->
                    <div class="pr-payment-single-action">
                        <button type="button" class="pr-btn pr-btn-primary pr-btn-payment-integrated" id="confirm-payment-inline-btn" onclick="console.log('🔘 BOTÓN INLINE CLICKEADO'); confirmPaymentInline()">
                            <i class="fas fa-lock"></i> Confirmar Pago
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

        <button type="button" class="pr-btn pr-btn-primary" id="confirm-payment-btn" style="width: 100%; margin-top: 20px;" onclick="console.log('🔘 BOTÓN MODAL CLICKEADO'); confirmPayment()">
            <i class="fas fa-lock"></i> Confirmar Pago
        </button>
    </div>

    <script>
        // TEST - Verificar ejecución JavaScript
        console.log('🚨 SCRIPT INICIADO - Polaca JS ejecutándose');
        
        // Error handler global para detectar problemas JavaScript  
        window.addEventListener('error', function(e) {
            console.error('🚨 ERROR JAVASCRIPT DETECTADO:', e.error);
            console.error('  Archivo:', e.filename);
            console.error('  Línea:', e.lineno);
            console.error('  Mensaje:', e.message);
            // alert('ERROR JS: ' + e.message + ' en línea ' + e.lineno); // Comentado para evitar popups
        });
        
        console.log('🚀 SCRIPT POLACO INICIADO');
        
        // Función alternativa de logging
        function logToServer(message) {
            console.log('📝 LOG:', message);
            
            // Método 1: Fetch tradicional
            fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                method: 'POST',
                body: new URLSearchParams({
                    action: 'log_polaca_debug',
                    message: message
                })
            }).then(response => {
                console.log('✅ Log enviado correctamente:', response.status);
            }).catch(error => {
                console.error('❌ Error enviando log:', error);
                
                // Método 2: Imagen pixel fallback
                const img = new Image();
                img.src = '<?php echo admin_url("admin-ajax.php"); ?>?action=log_polaca_debug&message=' + encodeURIComponent(message) + '&t=' + Date.now();
            });
        }
        
        // Variables globales
        let signaturePad, signaturePadFullscreen;
        let stripe, elements, paymentElement;
        let currentTramiteType = 'registro'; // Inicializar con registro por defecto
        let basePrice = 429.99; // Precio base de registro
        let additionalCosts = 0;
        let taxes = 45.00; // Tasas oficiales de registro
        let signatureConfirmed = false; // Estado de firma confirmada
        
        // Inicializar sistema progresivo por defecto
        let progressiveSelection = {
            tramite: { id: 'registro', title: 'Registro bajo bandera polaca' },
            boatSize: null,
            mmsi: null,
            extras: [],
            delivery: null
        };

        // Configuración completa de trámites polacos CON TODAS LAS OPCIONES Y SUBOPCIONES
        const tramitesConfig = {
            'registro': {
                title: 'Registro bajo Bandera Polaca',
                price: 429.99,
                taxes: 75.00,
                fees: 293.38,
                documents: [
                    { id: 'dni_propietario', label: 'Copia del DNI o pasaporte del propietario', example: 'dni-propietario', required: true },
                    { id: 'contrato_factura', label: 'Contrato de compraventa o factura de compra', example: 'factura-compra', required: true },
                    { id: 'ce_conformidad', label: 'Certificado CE de conformidad', example: 'certificado-ce', required: true },
                    { id: 'foto_placa_motor', label: 'Foto de la placa del motor', example: 'placa-motor', required: true }
                ],
                fields: [
                    { type: 'select', id: 'boat_port', label: 'Puerto de amarre preferido', required: true, options: [
                        { value: '', text: 'Selecciona un puerto' },
                        { value: 'Gdansk', text: 'Gdansk' },
                        { value: 'Gdynia', text: 'Gdynia' },
                        { value: 'Szczecin', text: 'Szczecin' },
                        { value: 'Swinoujscie', text: 'Świnoujście' }
                    ]}
                ],
                boatSizes: [
                    { id: 'size_0_7', label: 'Embarcación 0-7 metros', description: 'Gestión estándar para embarcaciones pequeñas', price: 0, icon: 'fas fa-anchor' },
                    { id: 'size_7_12', label: 'Embarcación 7-12 metros', description: 'Gestión para embarcaciones medianas', price: 50, icon: 'fas fa-sailboat' },
                    { id: 'size_12_24', label: 'Embarcación 12-24 metros', description: 'Gestión para embarcaciones grandes', price: 100, icon: 'fas fa-ship' }
                ],
                deliveryOptions: [
                    { id: 'standard', label: 'Entrega Estándar', description: '10-15 días laborables', price: 0, icon: 'fas fa-shipping-fast' },
                    { id: 'express', label: 'Entrega Express', description: '1-3 días laborables prioritarios', price: 180, icon: 'fas fa-bolt' }
                ],
                mmsiOptions: [
                    { id: 'no_mmsi', label: 'Sin MMSI', description: 'No incluir número MMSI en el registro', price: 0, icon: 'fas fa-times-circle' },
                    { id: 'mmsi_licensed', label: 'MMSI Licensed', description: 'Número MMSI para uso comercial con licencia', price: 170, icon: 'fas fa-radio' },
                    { id: 'mmsi_unlicensed', label: 'MMSI Unlicensed', description: 'Número MMSI para uso recreativo sin licencia comercial', price: 170, icon: 'fas fa-anchor' },
                    { id: 'mmsi_company', label: 'MMSI Company', description: 'Número MMSI para empresa o uso corporativo', price: 170, icon: 'fas fa-building' }
                ],
                extraServices: []
            },
            'cambio_titularidad': {
                title: 'Cambio de Titularidad - Bandera Polaca',
                price: 429.99,
                taxes: 50.00,
                fees: 314.04,
                documents: [
                    { id: 'dni_nuevo_propietario', label: 'Copia del DNI del nuevo propietario', example: 'dni-propietario', required: true },
                    { id: 'dni_anterior_propietario', label: 'Copia del DNI del anterior propietario', example: 'dni-propietario', required: true },
                    { id: 'contrato_compraventa', label: 'Contrato de compraventa', example: 'contrato-compraventa', required: true },
                    { id: 'registro_polaco_actual', label: 'Registro polaco actual de la embarcación', example: 'registro-polaco', required: true }
                ],
                fields: [],
                boatSizes: [
                    { id: 'size_0_7', label: 'Embarcación 0-7 metros', description: 'Cambio titularidad embarcaciones pequeñas', price: 0, icon: 'fas fa-anchor' },
                    { id: 'size_7_12', label: 'Embarcación 7-12 metros', description: 'Cambio titularidad embarcaciones medianas - Gestión especializada', price: 50, icon: 'fas fa-sailboat' },
                    { id: 'size_12_24', label: 'Embarcación 12-24 metros', description: 'Cambio titularidad embarcaciones grandes - Gestión completa', price: 100, icon: 'fas fa-ship' }
                ],
                mmsiOptions: [
                    { id: 'no_mmsi', label: 'Mantener MMSI actual', description: 'No realizar cambios en el número MMSI existente', price: 0, icon: 'fas fa-radio' },
                    { id: 'mmsi_licensed', label: 'MMSI Licensed', description: 'Transferir/Asignar MMSI para uso comercial', price: 170, icon: 'fas fa-satellite' },
                    { id: 'mmsi_unlicensed', label: 'MMSI Unlicensed', description: 'Transferir/Asignar MMSI para uso recreativo', price: 170, icon: 'fas fa-anchor' },
                    { id: 'mmsi_company', label: 'MMSI Company', description: 'Transferir/Asignar MMSI para empresa', price: 170, icon: 'fas fa-building' }
                ],
                extraServices: []
            },
            'mmsi': {
                title: 'Solicitud de Número MMSI Polaco',
                price: 190.00,
                taxes: 40.00,
                fees: 123.97,
                documents: [
                    { id: 'dni_propietario', label: 'Copia del DNI o pasaporte del propietario', example: 'dni-propietario', required: true },
                    { id: 'registro_polaco', label: 'Registro polaco de la embarcación', example: 'registro-polaco', required: true },
                    { id: 'certificado_radio', label: 'Certificado de radiooperador (si aplica)', example: 'certificado-radio', required: false },
                    { id: 'especificaciones_radio', label: 'Especificaciones del equipo de radio', example: 'radio-specs', required: false }
                ],
                fields: [
                    { type: 'text', id: 'radio_operator_cert', label: 'Número de certificado de radiooperador (opcional)', placeholder: 'Si posee certificado', required: false }
                ],
                mmsiTypes: [
                    { id: 'mmsi_licensed', label: 'MMSI Licensed', description: 'Número MMSI para uso comercial con licencia de operador', price: 0, icon: 'fas fa-satellite', baseService: true },
                    { id: 'mmsi_unlicensed', label: 'MMSI Unlicensed', description: 'Número MMSI para uso recreativo sin licencia comercial', price: 0, icon: 'fas fa-anchor', baseService: true },
                    { id: 'mmsi_company', label: 'MMSI Company', description: 'Número MMSI para empresa o flota comercial', price: 0, icon: 'fas fa-building', baseService: true }
                ],
                extraServices: []
            }
        };

        // Mantener compatibilidad con código existente
        const tramitePrices = {};
        const additionalOptionPrices = {
            'delivery_option': { 'express': 180 },
            'mmsi_option': { 'mmsi_licensed': 170, 'mmsi_unlicensed': 170, 'mmsi_company': 170 },
            'boat_size': { 'size_7_12': 50, 'size_12_24': 100 }
        };

        // Inicializar tramitePrices desde tramitesConfig para compatibilidad
        Object.keys(tramitesConfig).forEach(key => {
            tramitePrices[key] = {
                total: tramitesConfig[key].price,
                taxes: tramitesConfig[key].taxes
            };
        });

        // Variables globales adicionales para funcionalidades avanzadas
        let selectedTramite = '';
        let currentPrice = 0;

        // Funciones dinámicas integradas del formulario local

        /**
         * Función para configurar el formulario según el trámite seleccionado
         */
        function setupTramiteForm() {
            const config = tramitesConfig[selectedTramite];
            if (!config) return;

            // Actualizar precio base
            basePrice = config.price;
            currentPrice = basePrice;
            
            // Mostrar/ocultar campos específicos en datos básicos
            showSpecificFieldsInBasicData(selectedTramite);
            
            // Mostrar/ocultar sección de datos del barco (solo para registro)
            showBoatDataSection(selectedTramite);
            
            // Mostrar/ocultar secciones de opciones según el trámite
            showOptionsForTramite(selectedTramite);
            
            // Generar sección de documentos
            generateDocumentsSection(config.documents);
            
            // Actualizar detalles de precio
            updatePriceDetails(config);
            
            // Actualizar precio total después de generar opciones
            setTimeout(updateTotalPrice, 100);
        }

        /**
         * Mostrar campos específicos según el trámite en datos básicos
         */
        function showSpecificFieldsInBasicData(tramite) {
            // Ocultar todos los campos específicos primero
            const boatPortField = document.getElementById('boat-port-field');
            const radioCertField = document.getElementById('radio-cert-field');
            
            if (boatPortField) boatPortField.style.display = 'none';
            if (radioCertField) radioCertField.style.display = 'none';
            
            // Hacer los campos no requeridos por defecto
            const boatPortSelect = document.getElementById('boat_port');
            const radioCertInput = document.getElementById('radio_operator_cert');
            
            if (boatPortSelect) boatPortSelect.required = false;
            if (radioCertInput) radioCertInput.required = false;
            
            // Mostrar campos según el trámite
            if (tramite === 'registro' && boatPortField) {
                boatPortField.style.display = 'block';
                if (boatPortSelect) boatPortSelect.required = true;
            } else if (tramite === 'mmsi' && radioCertField) {
                radioCertField.style.display = 'block';
                // radio_operator_cert es opcional, no required
            }
        }

        /**
         * Mostrar/ocultar sección de datos del barco según el trámite
         */
        function showBoatDataSection(tramite) {
            const boatSection = document.getElementById('boat-data-section');
            if (!boatSection) return;

            // Mostrar solo para registro, ocultar para otros trámites
            if (tramite === 'registro') {
                boatSection.style.display = 'block';
                // Hacer campos requeridos
                const boatNameInput = document.getElementById('boat_name');
                const boatRegistrationInput = document.getElementById('boat_registration');
                if (boatNameInput) boatNameInput.required = true;
                if (boatRegistrationInput) boatRegistrationInput.required = true;
            } else {
                boatSection.style.display = 'none';
                // Remover requisitos y limpiar valores
                const boatNameInput = document.getElementById('boat_name');
                const boatRegistrationInput = document.getElementById('boat_registration');
                if (boatNameInput) {
                    boatNameInput.required = false;
                    boatNameInput.value = '';
                }
                if (boatRegistrationInput) {
                    boatRegistrationInput.required = false;
                    boatRegistrationInput.value = '';
                }
            }
        }

        /**
         * Mostrar opciones según el trámite seleccionado CON LÓGICA MODERNA
         */
        function showOptionsForTramite(tramite) {
            const config = tramitesConfig[tramite];
            if (!config) return;

            const optionsGroup = document.getElementById('group-tramite-options');
            if (!optionsGroup) return;

            // Limpiar contenido anterior
            optionsGroup.innerHTML = `
                <div class="group-header" onclick="toggleGroup('options')">
                    <h3><span class="group-icon">⚙️</span>Opciones del trámite</h3>
                    <span class="group-status"></span>
                </div>
                <div class="group-content" id="options-content">
                    <!-- Contenido dinámico se genera aquí -->
                </div>
            `;

            const optionsContent = document.getElementById('options-content');
            let hasOptions = false;

            // TAMAÑOS DE EMBARCACIÓN (si aplica)
            if (config.boatSizes && config.boatSizes.length > 0) {
                hasOptions = true;
                const boatSizesSection = createModernOptionSection(
                    'boat-sizes',
                    '🛥️ Tamaño de embarcación',
                    'Selecciona el tamaño de tu embarcación para el cálculo de tasas',
                    config.boatSizes,
                    'radio',
                    'boat_size'
                );
                optionsContent.appendChild(boatSizesSection);
            }

            // OPCIONES DE ENTREGA (solo para registro)
            if (config.deliveryOptions && config.deliveryOptions.length > 0) {
                hasOptions = true;
                const deliverySection = createModernOptionSection(
                    'delivery-options',
                    '📦 Opciones de entrega',
                    'Elige la velocidad de procesamiento y entrega',
                    config.deliveryOptions,
                    'radio',
                    'delivery_option'
                );
                optionsContent.appendChild(deliverySection);
            }

            // OPCIONES MMSI (si aplica)
            if (config.mmsiOptions && config.mmsiOptions.length > 0) {
                hasOptions = true;
                const mmsiSection = createModernOptionSection(
                    'mmsi-options',
                    'Servicios MMSI',
                    'Número de identificación para equipos de radio marítimos',
                    config.mmsiOptions,
                    'radio',
                    'mmsi_option'
                );
                optionsContent.appendChild(mmsiSection);
            }

            // TIPOS MMSI (solo para trámite MMSI)
            if (config.mmsiTypes && config.mmsiTypes.length > 0) {
                hasOptions = true;
                const mmsiTypesSection = createModernOptionSection(
                    'mmsi-types',
                    '🎯 Tipo de MMSI',
                    'Selecciona el tipo de uso para tu número MMSI',
                    config.mmsiTypes,
                    'radio',
                    'mmsi_type'
                );
                optionsContent.appendChild(mmsiTypesSection);
            }

            // SERVICIOS EXTRA (si aplica)
            if (config.extraServices && config.extraServices.length > 0) {
                hasOptions = true;
                const extraServicesSection = createModernOptionSection(
                    'extra-services',
                    '✨ Servicios adicionales',
                    'Servicios opcionales para complementar tu trámite',
                    config.extraServices,
                    'checkbox',
                    'extra_services'
                );
                optionsContent.appendChild(extraServicesSection);
            }

            // Mostrar/ocultar el grupo completo
            if (hasOptions) {
                optionsGroup.style.display = 'block';
                // Aplicar animación de entrada suave
                optionsGroup.style.opacity = '0';
                optionsGroup.style.transform = 'translateY(-20px)';
                
                setTimeout(() => {
                    optionsGroup.style.transition = 'all 0.5s ease-in-out';
                    optionsGroup.style.opacity = '1';
                    optionsGroup.style.transform = 'translateY(0)';
                }, 100);
            } else {
                optionsGroup.style.display = 'none';
            }
            
            // Agregar event listeners para actualizar precios
            addPriceUpdateListeners();
        }

        /**
         * Crear sección moderna de opciones con diseño profesional
         */
        function createModernOptionSection(sectionId, title, description, options, inputType, inputName) {
            const section = document.createElement('div');
            section.className = 'option-group';
            section.id = sectionId + '-section';
            
            section.innerHTML = `
                <h4>${title}</h4>
                <p class="option-description">${description}</p>
                <div class="option-cards">
                    ${options.map(option => `
                        <div class="option-card" data-option="${option.id}">
                            <label>
                                <input type="${inputType}" 
                                       name="${inputName}" 
                                       value="${option.id}" 
                                       data-price="${option.price || 0}">
                                <div class="option-content">
                                    <div class="option-header">
                                        <span class="option-icon">${option.icon || '⚙️'}</span>
                                        <span class="option-label">${option.label}</span>
                                        <span class="option-price">
                                            ${option.price > 0 ? `+${option.price}€` : (option.baseService ? 'Incluido' : 'Gratis')}
                                        </span>
                                    </div>
                                    <div class="option-description-text">${option.description}</div>
                                </div>
                            </label>
                        </div>
                    `).join('')}
                </div>
            `;

            return section;
        }

        /**
         * Agregar listeners para actualización de precios en tiempo real
         */
        function addPriceUpdateListeners() {
            const optionInputs = document.querySelectorAll('#group-tramite-options input[type="radio"], #group-tramite-options input[type="checkbox"]');
            optionInputs.forEach(input => {
                input.removeEventListener('change', updateTotalPrice);
                input.addEventListener('change', updateTotalPrice);
            });
        }

        /**
         * Actualizar precio total con todas las opciones MODERNAS
         */
        function updateTotalPrice() {
            const config = tramitesConfig[selectedTramite];
            if (!config) return;
            
            let totalPrice = config.price;
            let selectedOptions = [];
            
            // Sumar precios de radio buttons seleccionados (boat_size, delivery_option, mmsi_option, mmsi_type)
            const radioButtons = document.querySelectorAll('#group-tramite-options input[type="radio"]:checked');
            radioButtons.forEach(radio => {
                const price = parseFloat(radio.getAttribute('data-price')) || 0;
                const optionName = radio.getAttribute('name');
                const optionValue = radio.value;
                
                totalPrice += price;
                
                if (price > 0) {
                    selectedOptions.push({
                        type: 'radio',
                        name: optionName,
                        value: optionValue,
                        price: price,
                        label: radio.closest('.option-card').querySelector('.option-label').textContent
                    });
                }
            });
            
            // Sumar precios de checkboxes seleccionados (extra_services)
            const checkboxes = document.querySelectorAll('#group-tramite-options input[type="checkbox"]:checked');
            checkboxes.forEach(checkbox => {
                const price = parseFloat(checkbox.getAttribute('data-price')) || 0;
                const optionName = checkbox.getAttribute('name');
                const optionValue = checkbox.value;
                
                totalPrice += price;
                
                selectedOptions.push({
                    type: 'checkbox',
                    name: optionName,
                    value: optionValue,
                    price: price,
                    label: checkbox.closest('.option-card').querySelector('.option-label').textContent
                });
            });
            
            currentPrice = totalPrice;
            additionalCosts = totalPrice - config.price; // Para compatibilidad
            
            // Actualizar variables globales para compatibilidad
            basePrice = config.price;
            
            // Actualizar la visualización del precio
            updatePriceDisplay();
            
            // Actualizar resumen detallado si existe
            updateDetailedPriceSummary(selectedOptions);
            
            // Log para debugging
            console.log('💰 Precio actualizado:', {
                tramite: selectedTramite,
                precioBase: config.price,
                costosAdicionales: additionalCosts,
                precioTotal: currentPrice,
                opcionesSeleccionadas: selectedOptions
            });
        }

        /**
         * Actualizar resumen detallado de precios
         */
        function updateDetailedPriceSummary(selectedOptions) {
            // Buscar contenedor de resumen de precio
            const summaryContainer = document.getElementById('price-summary-details');
            if (!summaryContainer) return;
            
            const config = tramitesConfig[selectedTramite];
            let summaryHTML = `
                <div class="price-breakdown">
                    <div class="price-item base">
                        <span class="item-name">${config.title}</span>
                        <span class="item-price">${config.price.toFixed(2)}€</span>
                    </div>
            `;
            
            // Agregar opciones seleccionadas
            selectedOptions.forEach(option => {
                summaryHTML += `
                    <div class="price-item addon">
                        <span class="item-name">${option.label}</span>
                        <span class="item-price">+${option.price.toFixed(2)}€</span>
                    </div>
                `;
            });
            
            summaryHTML += `
                    <div class="price-item total">
                        <span class="item-name">Total</span>
                        <span class="item-price">${currentPrice.toFixed(2)}€</span>
                    </div>
                </div>
            `;
            
            summaryContainer.innerHTML = summaryHTML;
        }

        /**
         * Actualizar visualización del precio en la interfaz
         */
        function updatePriceDisplay() {
            const config = tramitesConfig[selectedTramite];
            if (!config) return;
            
            // Actualizar precio en la página de pago
            const priceElement = document.getElementById('final-price');
            if (priceElement) {
                priceElement.textContent = currentPrice.toFixed(2) + ' €';
            }
            
            // Actualizar botón de pago
            const payButton = document.getElementById('proceed-to-payment');
            if (payButton) {
                const priceSpan = payButton.querySelector('.btn-amount');
                if (priceSpan) {
                    priceSpan.textContent = currentPrice.toFixed(2) + ' €';
                }
            }
        }

        /**
         * Generar sección de documentos dinámicamente
         */
        function generateDocumentsSection(documents) {
            const container = document.getElementById('documents-upload-section');
            if (!container) return;
            
            container.innerHTML = '';
            
            documents.forEach((doc, index) => {
                const div = document.createElement('div');
                div.className = 'upload-item';
                
                const requiredText = doc.required ? '<span style="color: red;">*</span>' : '';
                
                div.innerHTML = `
                    <label for="upload-${doc.id}">
                        ${doc.label}${requiredText}
                    </label>
                    <input type="file" id="upload-${doc.id}" name="upload_${doc.id}" ${doc.required ? 'required' : ''}>
                    <a href="#" class="view-example" data-doc="${doc.example}">Ver ejemplo</a>
                `;
                container.appendChild(div);
            });
        }

        /**
         * Actualizar detalles del precio en página de pago
         */
        function updatePriceDetails(config) {
            updatePriceDisplay();
        }

        /**
         * Función para obtener descripción del trámite polaco
         */
        function get_polish_tramite_description(tramite_type) {
            const descriptions = {
                'registro': 'Registro bajo bandera polaca',
                'cambio_titularidad': 'Cambio de titularidad - bandera polaca',
                'mmsi': 'Solicitud de número MMSI polaco',
            };
            
            return descriptions[tramite_type] || 'Trámite marítimo polaco';
        }

        // Inicialización cuando el DOM esté listo
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🚀 DOM LOADED - Inicializando formulario polaco');
            
            // Log inicial al servidor
            logToServer('DOM LOADED - Iniciando formulario polaco');
            
            initializeForm();
            initializeSignature();
            initializeStripe();
        });

        function initializeForm() {
            console.log('📝 Inicializando formulario...');
            
            // Log al servidor
            logToServer('initializeForm() INICIADA');
            
            // Configurar event listeners para actualización en tiempo real del sidebar
            const formFields = ['customer_name', 'customer_dni', 'customer_email', 'customer_phone'];
            formFields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (field) {
                    field.addEventListener('input', () => {
                        if (document.getElementById('sidebar-progress').style.display === 'block') {
                            updateProgressSidebar();
                        }
                    });
                }
            });

            // Event listeners para archivos
            const fileFields = ['dni_documento[]', 'registro_maritimo[]'];
            fileFields.forEach(fieldName => {
                const field = document.querySelector(`input[name="${fieldName}"]`);
                if (field) {
                    field.addEventListener('change', () => {
                        if (document.getElementById('sidebar-progress').style.display === 'block') {
                            updateProgressSidebar();
                        }
                    });
                }
            });

            // Configurar selección de trámite (integrado con funcionalidades avanzadas)
            const tramiteOptions = document.querySelectorAll('.pr-tramite-option');
            tramiteOptions.forEach(option => {
                option.addEventListener('click', function() {
                    const radio = this.querySelector('input[type="radio"]');
                    radio.checked = true;

                    // Remover selección anterior
                    tramiteOptions.forEach(opt => opt.classList.remove('selected'));
                    this.classList.add('selected');

                    // Actualizar variables globales (mantener compatibilidad)
                    currentTramiteType = radio.value;
                    selectedTramite = radio.value; // Nueva variable para funcionalidades avanzadas
                    
                    // Configurar formulario con funcionalidades avanzadas del local
                    setupTramiteForm();
                    
                    // Mantener funcionalidades existentes
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
                    maxWidth: 3.5,
                    velocityFilterWeight: 0.7,
                    minDistance: 5,
                    throttle: 0
                });

                signaturePad.addEventListener('beginStroke', function() {
                    document.getElementById('confirm-signature-btn').disabled = false;
                });
            }

            // Configurar canvas de firma fullscreen con parámetros optimizados para móvil
            const canvasFullscreen = document.getElementById('signature-pad-fullscreen');
            if (canvasFullscreen) {
                signaturePadFullscreen = new SignaturePad(canvasFullscreen, {
                    backgroundColor: 'rgb(255, 255, 255)',
                    penColor: 'rgb(0, 0, 0)',
                    minWidth: 1.5,
                    maxWidth: 4.5,
                    velocityFilterWeight: 0.5,
                    minDistance: 3,
                    throttle: 0,
                    dotSize: 2
                });

                signaturePadFullscreen.addEventListener('beginStroke', function() {
                    document.getElementById('confirm-fullscreen-signature-btn').disabled = false;
                });

                // Prevenir scroll durante firma en canvas fullscreen
                canvasFullscreen.addEventListener('touchstart', function(e) {
                    e.preventDefault();
                }, { passive: false });

                canvasFullscreen.addEventListener('touchmove', function(e) {
                    e.preventDefault();
                }, { passive: false });
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
            console.log('💳 Inicializando Stripe...');
            
            // Log al servidor
            logToServer('initializeStripe() INICIADA - public_key: <?php echo substr($stripe_public_key, 0, 10); ?>...');
            
            try {
                stripe = Stripe('<?php echo $stripe_public_key; ?>');
                console.log('✅ Stripe inicializado correctamente');
                
                logToServer('Stripe inicializado correctamente');
                
            } catch(error) {
                console.error('❌ Error inicializando Stripe:', error);
                
                logToServer('ERROR inicializando Stripe: ' + error.message);
            }
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
            const navElement = document.querySelector(`[data-page="${pageId}"]`);
            if (navElement) {
                navElement.classList.add('active');
            }

            // Actualizar sidebar
            updateSidebar(pageId);

            // Acciones específicas por página
            if (pageId === 'page-documents') {
                updateAuthDocument();
                setTimeout(resizeCanvas, 100);
            } else if (pageId === 'page-summary') {
                updateSummaryPage();
                updatePaymentSummary(); // Esta función es para la página de resumen
            } else if (pageId === 'page-payment') {
                // No llamar updatePaymentSummary() aquí - es para page-summary
                if (progressiveSelection && progressiveSelection.tramite) {
                    updatePaymentSidebar();
                }
                // Inicializar Stripe inline cuando se carga la página
                setTimeout(() => {
                    initializePaymentElementInline();
                }, 1000);
            }
        }

        function updateSidebar(pageId) {
            // Ocultar todos los contenidos del sidebar
            document.getElementById('sidebar-initial').style.display = 'none';
            document.getElementById('sidebar-tracking').style.display = 'none';
            document.getElementById('sidebar-progress').style.display = 'none';
            document.getElementById('sidebar-benefits').style.display = 'none';
            document.getElementById('sidebar-signature').style.display = 'none';
            document.getElementById('sidebar-payment').style.display = 'none';

            if (pageId === 'page-selection') {
                // Si no hay selecciones, mostrar inicial, si hay, mostrar tracking
                if (!progressiveSelection.tramite) {
                    document.getElementById('sidebar-initial').style.display = 'block';
                } else {
                    document.getElementById('sidebar-tracking').style.display = 'block';
                }
            } else if (pageId === 'page-documents') {
                // Para documentos, mostrar sidebar de progreso como testigo
                document.getElementById('sidebar-progress').style.display = 'block';
                updateProgressSidebar();
            } else if (pageId === 'page-signature') {
                // Para página de firma, mostrar documento completo
                document.getElementById('sidebar-signature').style.display = 'block';
                updateSignatureDocument();
            } else if (pageId === 'page-summary') {
                // Para página de resumen, mostrar sidebar de beneficios/ventajas
                document.getElementById('sidebar-benefits').style.display = 'block';
            } else if (pageId === 'page-payment') {
                // Para pago, mostrar el sidebar de pago
                document.getElementById('sidebar-payment').style.display = 'block';
                updatePaymentSidebar();
            } else {
                // Para otras páginas, mantener el tracking si hay selecciones
                if (progressiveSelection.tramite) {
                    document.getElementById('sidebar-tracking').style.display = 'block';
                } else {
                    document.getElementById('sidebar-initial').style.display = 'block';
                }
            }
        }

        // Función para actualizar el sidebar de progreso
        function updateProgressSidebar() {
            // Datos personales
            const personalFields = [
                { id: 'customer_name', item: 'item-name' },
                { id: 'customer_dni', item: 'item-dni' },
                { id: 'customer_email', item: 'item-email' },
                { id: 'customer_phone', item: 'item-phone' }
            ];

            let personalCompleted = 0;
            personalFields.forEach(field => {
                const element = document.getElementById(field.id);
                const item = document.getElementById(field.item);
                if (element && element.value.trim()) {
                    personalCompleted++;
                    item.classList.add('completed');
                } else {
                    item.classList.remove('completed');
                }
            });

            // Documentación
            const docFields = [
                { name: 'dni_documento[]', item: 'item-dni-doc' },
                { name: 'registro_maritimo[]', item: 'item-registro' }
            ];

            let docsCompleted = 0;
            docFields.forEach(field => {
                const element = document.querySelector(`input[name="${field.name}"]`);
                const item = document.getElementById(field.item);
                if (element && element.files && element.files.length > 0) {
                    docsCompleted++;
                    item.classList.add('completed');
                } else {
                    item.classList.remove('completed');
                }
            });

            // Actualizar barras de progreso
            const personalPercent = (personalCompleted / 4) * 100;
            const docsPercent = (docsCompleted / 2) * 100;

            document.getElementById('fill-personal').style.width = personalPercent + '%';
            document.getElementById('fill-documents').style.width = docsPercent + '%';

            document.getElementById('status-personal').textContent = `${personalCompleted}/4`;
            document.getElementById('status-documents').textContent = `${docsCompleted}/2`;

            // Progreso total
            const totalCompleted = personalCompleted + docsCompleted;
            const totalFields = 6;
            const totalPercent = Math.round((totalCompleted / totalFields) * 100);

            document.getElementById('completion-percentage').textContent = totalPercent + '%';
            document.getElementById('total-completion').style.width = totalPercent + '%';
        }

        // Función para actualizar el documento de firma
        function updateSignatureDocument() {
            const customerName = document.getElementById('customer_name').value || 'Nombre pendiente';
            const customerDni = document.getElementById('customer_dni').value || 'DNI pendiente';
            const customerEmail = document.getElementById('customer_email').value || 'Email pendiente';

            // Actualizar campos del documento
            const docCustomerName = document.getElementById('doc-customer-name');
            const docCustomerDni = document.getElementById('doc-customer-dni');
            const docCustomerEmail = document.getElementById('doc-customer-email');
            const docAuthName = document.getElementById('doc-auth-name');
            const docAuthDni = document.getElementById('doc-auth-dni');
            
            if (docCustomerName) docCustomerName.textContent = customerName;
            if (docCustomerDni) docCustomerDni.textContent = customerDni;
            if (docCustomerEmail) docCustomerEmail.textContent = customerEmail;
            if (docAuthName) docAuthName.textContent = customerName.toUpperCase();
            if (docAuthDni) docAuthDni.textContent = customerDni;
            
            // Actualizar información de la embarcación
            // NOTA: Los datos de embarcación se insertan manualmente, no automáticamente
            // const boatName = document.getElementById('boat_name');
            // const boatRegistration = document.getElementById('boat_registration');
            // const boatPort = document.getElementById('boat_port');
            // 
            // const docBoatName = document.getElementById('doc-boat-name');
            // const docBoatRegistration = document.getElementById('doc-boat-registration');
            // const docBoatPort = document.getElementById('doc-boat-port');
            // 
            // if (docBoatName) {
            //     docBoatName.textContent = boatName && boatName.value ? boatName.value : 'Nombre pendiente';
            // }
            // if (docBoatRegistration) {
            //     docBoatRegistration.textContent = boatRegistration && boatRegistration.value ? boatRegistration.value : 'Matrícula pendiente';
            // }
            // if (docBoatPort) {
            //     docBoatPort.textContent = boatPort && boatPort.value ? boatPort.value : 'Puerto pendiente';
            // }
            
            // Actualizar tipo de trámite
            // NOTA: Sección EMBARCACIÓN eliminada completamente del documento
            // const docTramiteType = document.getElementById('doc-tramite-type');
            // if (docTramiteType && progressiveSelection.tramite) {
            //     docTramiteType.textContent = progressiveSelection.tramite.title || 'Registro bandera polaca';
            // }
            
            // Actualizar fecha
            const today = new Date().toLocaleDateString('es-ES');
            const signatureDate = document.getElementById('signature-date');
            const docDate = document.getElementById('doc-date');
            
            if (signatureDate) signatureDate.textContent = today;
            if (docDate) docDate.textContent = today;
        }

        // Función para abrir la página de firma
        function openSignaturePage() {
            showPage('page-signature');
            // Inicializar canvas de firma principal
            setTimeout(() => {
                initializeMainSignature();
            }, 100);
        }

        // Funciones para el pad de firma principal
        let signaturePadMain;

        function initializeMainSignature() {
            const canvas = document.getElementById('signature-pad-main');
            if (canvas) {
                signaturePadMain = new SignaturePad(canvas, {
                    backgroundColor: 'rgba(255, 255, 255, 0)',
                    penColor: 'rgb(0, 0, 0)',
                    minWidth: 1,
                    maxWidth: 3
                });

                signaturePadMain.addEventListener("beginStroke", () => {
                    document.getElementById('confirm-signature-main-btn').disabled = false;
                });
            }
        }

        function clearSignatureMain() {
            if (signaturePadMain) {
                signaturePadMain.clear();
                document.getElementById('confirm-signature-main-btn').disabled = true;
                document.getElementById('signature-continue-btn').disabled = true;
            }
        }

        function confirmSignatureMain() {
            if (signaturePadMain && !signaturePadMain.isEmpty()) {
                signatureConfirmed = true; // Marcar firma como confirmada
                
                // ✅ TRANSFERIR FIRMA AL SIGNATUREPAD PRINCIPAL
                if (signaturePad) {
                    const mainSignatureData = signaturePadMain.toDataURL();
                    const img = new Image();
                    img.onload = function() {
                        const canvas = document.getElementById('signature-pad');
                        const ctx = canvas.getContext('2d');
                        ctx.clearRect(0, 0, canvas.width, canvas.height);
                        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                        console.log('✅ Firma transferida de signaturePadMain a signaturePad');
                    };
                    img.src = mainSignatureData;
                }
                
                document.getElementById('confirm-signature-main-btn').disabled = true;
                document.getElementById('signature-continue-btn').disabled = false;
                
                // Actualizar estado en sidebar de progreso
                document.getElementById('status-signature').textContent = 'Firmado';
                document.getElementById('status-signature').style.background = 'rgba(0, 255, 0, 0.3)';
                
                // Mostrar estado de firma completada en la página principal
                document.getElementById('signature-status').style.display = 'flex';
                
                console.log('✅ Firma confirmada correctamente desde signaturePadMain');
            }
        }

        // Funciones para el sistema de firma discreto
        function activateSignatureMode() {
            // Actualizar datos del documento en el sidebar
            updateDocumentData();
            
            // Mostrar sidebar de firma
            document.getElementById('sidebar-signature').style.display = 'block';
            document.getElementById('sidebar-authorization').style.display = 'none';
            
            // Mostrar overlay de firma
            document.getElementById('signature-overlay').style.display = 'block';
            
            // Reinicializar canvas de firma
            setTimeout(resizeCanvas, 100);
        }

        function closeSignatureMode() {
            // Ocultar overlay
            document.getElementById('signature-overlay').style.display = 'none';
            
            // Volver al sidebar normal
            document.getElementById('sidebar-signature').style.display = 'none';
            document.getElementById('sidebar-authorization').style.display = 'block';
            
            // Limpiar firma si no está confirmada
            if (!document.getElementById('confirm-signature-btn').classList.contains('confirmed')) {
                clearSignature();
            }
        }

        function updateDocumentData() {
            // Llamar a la función unificada de actualización del documento
            updateSignatureDocument();
        }

        // Función de confirmar firma para el canvas principal (overlay)
        function confirmSignature() {
            if (signaturePad && !signaturePad.isEmpty()) {
                signatureConfirmed = true; // Marcar firma como confirmada
                document.getElementById('confirm-signature-btn').disabled = true;
                document.getElementById('confirm-signature-btn').classList.add('confirmed');
                
                // Actualizar estado del botón
                const statusElement = document.getElementById('signature-status');
                if (statusElement) {
                    statusElement.textContent = 'Firmado';
                    statusElement.style.background = 'rgba(0, 255, 0, 0.3)';
                }
                
                const btnElement = document.getElementById('activate-signature-btn');
                if (btnElement) {
                    btnElement.style.background = 'rgb(0, 150, 0)';
                }
                
                // Cerrar modo firma
                closeSignatureMode();
                
                console.log('✅ Firma confirmada correctamente desde signaturePad overlay');
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

            // Actualizar sidebar si existe el elemento antiguo (compatibilidad)
            const oldSidebarPrice = document.getElementById('sidebar-price');
            if (oldSidebarPrice) {
                oldSidebarPrice.textContent = `€ ${totalPrice.toFixed(2)}`;
            }
        }

        function updateAuthDocument() {
            const customerNameInput = document.getElementById('customer_name');
            const customerDniInput = document.getElementById('customer_dni');
            
            const customerName = (customerNameInput ? customerNameInput.value : '') || 'Nombre pendiente';
            const customerDni = (customerDniInput ? customerDniInput.value : '') || 'DNI pendiente';

            const docNameEl = document.getElementById('document-customer-name');
            const docDniEl = document.getElementById('document-customer-dni');
            const authNameEl = document.getElementById('auth-customer-name');
            const authDniEl = document.getElementById('auth-customer-dni');
            
            if (docNameEl) docNameEl.textContent = customerName;
            if (docDniEl) docDniEl.textContent = customerDni;
            if (authNameEl) authNameEl.textContent = customerName;
            if (authDniEl) authDniEl.textContent = customerDni;
        }

        function updatePaymentSummary() {
            if (!currentTramiteType) return;

            const tramiteDescriptions = {
                'registro': 'Registro bajo bandera polaca',
                'cambio_titularidad': 'Cambio de titularidad',
                'mmsi': 'Número MMSI polaco'
            };

            // Validar que los elementos existan antes de actualizarlos
            const tramiteTypeEl = document.getElementById('summary-tramite-type');
            const basePriceEl = document.getElementById('summary-base-price');
            const taxesEl = document.getElementById('summary-taxes');
            
            if (tramiteTypeEl) tramiteTypeEl.textContent = tramiteDescriptions[currentTramiteType];
            if (basePriceEl) basePriceEl.textContent = `€ ${basePrice.toFixed(2)}`;
            if (taxesEl) taxesEl.textContent = `€ ${taxes.toFixed(2)}`;

            const additionalCostsEl = document.getElementById('summary-additional-costs');
            const additionalAmountEl = document.getElementById('summary-additional-amount');
            
            if (additionalCosts > 0) {
                if (additionalCostsEl) additionalCostsEl.style.display = 'block';
                if (additionalAmountEl) additionalAmountEl.textContent = `€ ${additionalCosts.toFixed(2)}`;
            } else {
                if (additionalCostsEl) additionalCostsEl.style.display = 'none';
            }

            const totalPrice = basePrice + additionalCosts;
            const totalEl = document.getElementById('summary-total');
            if (totalEl) totalEl.textContent = `€ ${totalPrice.toFixed(2)}`;
        }

        function updatePaymentSidebar() {
            if (!progressiveSelection.tramite) return;

            // Mostrar servicios contratados detalladamente
            const paymentBreakdown = document.querySelector('.pr-payment-breakdown');
            let html = '';

            // Servicio principal
            const tramiteLabels = {
                'registro': 'Registro bandera polaca',
                'cambio_titularidad': 'Cambio de titularidad',
                'mmsi': 'Número MMSI polaco'
            };

            const basePrice = getBasePrice(progressiveSelection.tramite.id);
            html += `
                <div class="pr-payment-item">
                    <div class="pr-payment-label">${tramiteLabels[progressiveSelection.tramite.id]}</div>
                    <div class="pr-payment-value">€ ${basePrice.toFixed(2)}</div>
                </div>
            `;

            let additionalTotal = 0;

            // Tamaño del barco (si aplica y tiene coste)
            if (progressiveSelection.boatSize && progressiveSelection.boatSize.id !== 'none' && progressiveSelection.boatSize.price > 0) {
                const sizeLabels = {
                    'size_7_12': 'Suplemento embarcación 7-12m',
                    'size_12_24': 'Suplemento embarcación 12-24m'
                };
                html += `
                    <div class="pr-payment-item">
                        <div class="pr-payment-label">${sizeLabels[progressiveSelection.boatSize.id] || 'Suplemento tamaño'}</div>
                        <div class="pr-payment-value">€ ${progressiveSelection.boatSize.price.toFixed(2)}</div>
                    </div>
                `;
                additionalTotal += progressiveSelection.boatSize.price;
            }

            // MMSI (si aplica y tiene coste)
            if (progressiveSelection.mmsi && progressiveSelection.mmsi.id !== 'none' && progressiveSelection.mmsi.id !== 'no_mmsi' && progressiveSelection.mmsi.price > 0) {
                html += `
                    <div class="pr-payment-item">
                        <div class="pr-payment-label">Número MMSI</div>
                        <div class="pr-payment-value">€ ${progressiveSelection.mmsi.price.toFixed(2)}</div>
                    </div>
                `;
                additionalTotal += progressiveSelection.mmsi.price;
            }

            // Servicios extras
            if (progressiveSelection.extras && progressiveSelection.extras.length > 0) {
                const extrasLabels = {
                    'apostilla': 'Apostilla de la Haya',
                    'extracto': 'Extracto registral',
                    'bandera_fisica': 'Bandera física'
                };
                
                progressiveSelection.extras.forEach(extra => {
                    if (extra.price > 0) {
                        html += `
                            <div class="pr-payment-item">
                                <div class="pr-payment-label">${extrasLabels[extra.id] || extra.title}</div>
                                <div class="pr-payment-value">€ ${extra.price.toFixed(2)}</div>
                            </div>
                        `;
                        additionalTotal += extra.price;
                    }
                });
            }

            // Método de entrega (si tiene coste)
            if (progressiveSelection.delivery && progressiveSelection.delivery.price > 0) {
                html += `
                    <div class="pr-payment-item">
                        <div class="pr-payment-label">Entrega express (24-48h)</div>
                        <div class="pr-payment-value">€ ${progressiveSelection.delivery.price.toFixed(2)}</div>
                    </div>
                `;
                additionalTotal += progressiveSelection.delivery.price;
            }

            // Obtener tasas oficiales
            const tramiteConfig = tramitesConfig[progressiveSelection.tramite.id];
            const tasasOficiales = tramiteConfig ? tramiteConfig.taxes : 0;

            if (tasasOficiales > 0) {
                html += `
                    <div class="pr-payment-item">
                        <div class="pr-payment-label">Tasas oficiales</div>
                        <div class="pr-payment-value">€ ${tasasOficiales.toFixed(2)}</div>
                    </div>
                `;
            }

            // Separador y total
            html += '<div class="pr-payment-separator"></div>';

            const finalTotal = basePrice + additionalTotal + tasasOficiales;
            html += `
                <div class="pr-payment-item pr-payment-total">
                    <div class="pr-payment-label">Total a pagar</div>
                    <div class="pr-payment-value">€ ${finalTotal.toFixed(2)}</div>
                </div>
            `;

            paymentBreakdown.innerHTML = html;

            // Actualizar variables globales para Stripe
            window.basePrice = getBasePrice(progressiveSelection.tramite.id);
            window.additionalCosts = additionalTotal;
            window.taxes = tasasOficiales;
        }

        // Funciones de firma
        function openSignatureModal() {
            const modal = document.getElementById('signature-modal');
            modal.classList.add('active');
            modal.style.display = 'flex';

            // Prevenir scroll del body
            document.body.style.overflow = 'hidden';
            document.body.style.position = 'fixed';
            document.body.style.width = '100%';

            // Ocultar WhatsApp Ninja de forma agresiva (múltiples selectores)
            const whatsappSelectors = [
                '.wp-whatsapp-chat',
                '#whatsapp-chat-widget',
                '.whatsapp-button',
                '.wa-chat-box',
                '.wa-chat-button',
                '.wa-widget',
                '.wa-chat-bubble',
                '[id*="whatsapp"]',
                '[class*="whatsapp"]',
                '[class*="wa-"]',
                '.ctc-analytics',
                '#ctc_chat'
            ];

            whatsappSelectors.forEach(selector => {
                try {
                    const elements = document.querySelectorAll(selector);
                    elements.forEach(element => {
                        if (element && !element.closest('.pr-signature-modal')) {
                            element.style.setProperty('display', 'none', 'important');
                            element.style.setProperty('visibility', 'hidden', 'important');
                            element.style.setProperty('opacity', '0', 'important');
                            element.style.setProperty('z-index', '-1', 'important');
                            element.setAttribute('data-hidden-by-modal', 'true');
                        }
                    });
                } catch (e) {
                    console.log('Selector no válido:', selector);
                }
            });

            // Limpiar firma al abrir modal
            if (signaturePadFullscreen) {
                signaturePadFullscreen.clear();
                document.getElementById('confirm-fullscreen-signature-btn').disabled = true;
            }
        }

        function closeSignatureModal() {
            const modal = document.getElementById('signature-modal');
            modal.classList.remove('active');
            modal.style.display = 'none';

            // Restaurar scroll del body
            document.body.style.overflow = '';
            document.body.style.position = '';
            document.body.style.width = '';

            // Restaurar WhatsApp Ninja
            const hiddenElements = document.querySelectorAll('[data-hidden-by-modal="true"]');
            hiddenElements.forEach(element => {
                element.style.removeProperty('display');
                element.style.removeProperty('visibility');
                element.style.removeProperty('opacity');
                element.style.removeProperty('z-index');
                element.removeAttribute('data-hidden-by-modal');
            });
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


        function confirmFullscreenSignature() {
            if (signaturePadFullscreen && !signaturePadFullscreen.isEmpty()) {
                signatureConfirmed = true; // Marcar firma como confirmada
                // Transferir firma al canvas principal
                if (signaturePad) {
                    const fullscreenData = signaturePadFullscreen.toDataURL();
                    const img = new Image();
                    img.onload = function() {
                        const canvas = document.getElementById('signature-pad');
                        const ctx = canvas.getContext('2d');
                        ctx.clearRect(0, 0, canvas.width, canvas.height);
                        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                        console.log('✅ Firma transferida de signaturePadFullscreen a signaturePad');
                    };
                    img.src = fullscreenData;
                }

                closeSignatureModal();
                document.getElementById('confirm-signature-btn').disabled = false;
                console.log('✅ Firma confirmada correctamente desde signaturePadFullscreen');
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

            // Crear Payment Intent en el servidor
            const formData = new FormData();
            formData.append('action', 'create_polish_payment_intent');
            formData.append('amount', totalAmount);

            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Verificar que Stripe esté disponible
                    if (typeof stripe === 'undefined') {
                        console.error('❌ Stripe no está definido');
                        alert('Error: Stripe no se ha cargado correctamente. Por favor, recarga la página.');
                        return;
                    }
                    
                    // Inicializar elementos de Stripe con el client_secret
                    elements = stripe.elements({
                        clientSecret: data.data.client_secret
                    });
                    
                    paymentElement = elements.create('payment');
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

                    // Guardar client_secret para usarlo en confirmPayment
                    window.paymentClientSecret = data.data.client_secret;
                } else {
                    document.getElementById('payment-spinner').style.display = 'none';
                    showPaymentError('Error creando el pago: ' + data.data);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('payment-spinner').style.display = 'none';
                showPaymentError('Error de conexión. Por favor, inténtelo de nuevo.');
            });
        }

        function confirmPayment() {
            console.log('🎯 CONFIRM PAYMENT EJECUTADO (modal)');
            if (!validateForm()) return;

            const confirmButton = document.getElementById('confirm-payment-btn');
            confirmButton.disabled = true;
            confirmButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';

            if (!window.paymentClientSecret) {
                showPaymentError('Error: Payment Intent no inicializado');
                confirmButton.disabled = false;
                confirmButton.innerHTML = '<i class="fas fa-lock"></i> Confirmar Pago';
                return;
            }

            stripe.confirmPayment({
                elements: elements,
                confirmParams: {
                    return_url: window.location.href
                },
                redirect: 'if_required'
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

        function showPaymentErrorInline(message) {
            const errorElement = document.getElementById('payment-error-inline');
            errorElement.textContent = message;
            errorElement.style.display = 'block';
        }

        function confirmPaymentInline() {
            console.log('🎯 CONFIRM PAYMENT INLINE EJECUTADO (página)');
            
            // Log inicial al servidor
            logToServer('FUNCIÓN confirmPaymentInline() INICIADA');
            
            if (!validateForm()) {
                console.log('❌ Validación del formulario falló');
                logToServer('VALIDACIÓN FORMULARIO FALLÓ');
                return;
            }

            const confirmButton = document.getElementById('confirm-payment-inline-btn');
            if (!confirmButton) {
                console.error('Botón de confirmación no encontrado');
                return;
            }

            confirmButton.disabled = true;
            confirmButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';

            // Verificar que Stripe y elementos estén inicializados
            if (!stripe || !elements) {
                console.error('Stripe no está inicializado correctamente');
                showPaymentErrorInline('Error de configuración. Por favor, recargue la página.');
                confirmButton.disabled = false;
                confirmButton.innerHTML = '<i class="fas fa-lock"></i> Confirmar Pago';
                return;
            }

            if (!window.paymentClientSecret) {
                showPaymentErrorInline('Error: Payment Intent no inicializado. Por favor, recargue la página.');
                confirmButton.disabled = false;
                confirmButton.innerHTML = '<i class="fas fa-lock"></i> Confirmar Pago';
                return;
            }

            console.log('🔄 EJECUTANDO stripe.confirmPayment...');
            console.log('🔧 ENVIRONMENT CHECK:');
            console.log('   stripe object:', !!stripe);
            console.log('   elements object:', !!elements);
            console.log('   paymentClientSecret:', !!window.paymentClientSecret);
            console.log('   current URL:', window.location.href);
            console.log('   return URL:', window.location.origin + '/wp-admin/admin-ajax.php?action=handle_polish_registration_webhook&payment_success=true');
            
            // Log al servidor también
            logToServer(`INICIANDO stripe.confirmPayment - stripe:${!!stripe}, elements:${!!elements}, clientSecret:${!!window.paymentClientSecret}`);
            
            // Verificar que todos los objetos estén disponibles
            if (typeof stripe === 'undefined') {
                console.error('❌ Stripe no está definido');
                showPaymentErrorInline('Error: Stripe no se ha cargado correctamente. Por favor, recarga la página.');
                confirmButton.disabled = false;
                confirmButton.innerHTML = '<i class="fas fa-lock"></i> Confirmar Pago';
                return;
            }
            
            if (typeof elements === 'undefined' || !elements) {
                console.error('❌ Elements no está definido');
                showPaymentErrorInline('Error: Elementos de pago no están disponibles. Por favor, inténtelo de nuevo.');
                confirmButton.disabled = false;
                confirmButton.innerHTML = '<i class="fas fa-lock"></i> Confirmar Pago';
                return;
            }
            
            // Timeout de 15 segundos para detectar si Stripe se cuelga
            const timeoutId = setTimeout(() => {
                logToServer('TIMEOUT: stripe.confirmPayment tardó más de 15 segundos');
                console.error('⏰ TIMEOUT: stripe.confirmPayment se colgó después de 15 segundos');
                showPaymentErrorInline('El procesamiento del pago está tardando más de lo esperado. Por favor, inténtelo de nuevo.');
                confirmButton.disabled = false;
                confirmButton.innerHTML = '<i class="fas fa-lock"></i> Confirmar Pago';
            }, 15000);
            
            stripe.confirmPayment({
                elements: elements,
                confirmParams: {
                    return_url: window.location.origin + '/wp-admin/admin-ajax.php?action=handle_polish_registration_webhook&payment_success=true'
                },
                redirect: 'if_required'
            }).then(function(result) {
                clearTimeout(timeoutId); // Cancelar timeout
                logToServer('STRIPE .then() EJECUTADO - result recibido');
                console.log('🎉 STRIPE .then() EJECUTADO CORRECTAMENTE');
                console.log('📋 RESULTADO stripe.confirmPayment:', result);
                console.log('🔍 ANÁLISIS DETALLADO DEL RESULTADO:');
                console.log('   result existe:', !!result);
                console.log('   result.error:', result?.error);
                console.log('   result.paymentIntent:', result?.paymentIntent);
                console.log('   result.paymentIntent.status:', result?.paymentIntent?.status);
                
                // Log al servidor
                fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                    method: 'POST',
                    body: new URLSearchParams({
                        action: 'log_polaca_debug',
                        message: `STRIPE RESULT: status=${result?.paymentIntent?.status}, error=${!!result?.error}, paymentIntent=${!!result?.paymentIntent}`
                    })
                });
                
                if (result && result.error) {
                    console.error('🚨 ERROR STRIPE DETALLADO:', {
                        code: result.error.code,
                        type: result.error.type,
                        message: result.error.message,
                        decline_code: result.error.decline_code,
                        payment_intent: result.error.payment_intent
                    });
                    showPaymentErrorInline(result.error.message);
                    confirmButton.disabled = false;
                    confirmButton.innerHTML = '<i class="fas fa-lock"></i> Confirmar Pago';
                } else if (result && result.paymentIntent && result.paymentIntent.status === 'succeeded') {
                    console.log('✅ PAGO EXITOSO - processFormSubmission:', result.paymentIntent.id);
                    
                    // Log al servidor
                    fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                        method: 'POST',
                        body: new URLSearchParams({
                            action: 'log_polaca_debug',
                            message: `PAGO EXITOSO - Llamando processFormSubmission con ID: ${result.paymentIntent.id}`
                        })
                    });
                    
                    processFormSubmission(result.paymentIntent.id);
                } else {
                    console.warn('⚠️ RESULTADO INESPERADO - sin error ni paymentIntent exitoso:', result);
                }
            }).catch(function(error) {
                clearTimeout(timeoutId); // Cancelar timeout
                logToServer('STRIPE .catch() EJECUTADO - error recibido');
                console.log('⚠️ STRIPE .catch() EJECUTADO');
                console.error('🚨 ERROR CRÍTICO EN stripe.confirmPayment:', error);
                console.error('🔍 DETALLES DEL ERROR:', {
                    name: error?.name,
                    message: error?.message,
                    stack: error?.stack,
                    tipo: typeof error,
                    toString: error?.toString()
                });
                
                // Log detallado al servidor
                logToServer(`ERROR CRÍTICO stripe.confirmPayment: ${error?.name || 'Unknown'} - ${error?.message || error?.toString() || 'No message'}`);
                
                showPaymentErrorInline('Error en el procesamiento del pago: ' + (error?.message || 'Error desconocido'));
                confirmButton.disabled = false;
                confirmButton.innerHTML = '<i class="fas fa-lock"></i> Confirmar Pago';
            });
        }

        function calculateProgressiveTotal() {
            if (!progressiveSelection.tramite) return 0;
            
            let total = getBasePrice(progressiveSelection.tramite.id);
            
            // Añadir suplementos de tamaño
            if (progressiveSelection.boat_size && progressiveSelection.boat_size.supplement > 0) {
                total += progressiveSelection.boat_size.supplement;
            }
            
            // Añadir opciones MMSI
            if (progressiveSelection.mmsi_option && progressiveSelection.mmsi_option.price > 0) {
                total += progressiveSelection.mmsi_option.price;
            }
            
            // Añadir servicios extra
            if (progressiveSelection.extras) {
                Object.values(progressiveSelection.extras).forEach(extra => {
                    if (extra.selected && extra.price > 0) {
                        total += extra.price;
                    }
                });
            }
            
            // Añadir opciones de entrega
            if (progressiveSelection.delivery && progressiveSelection.delivery.price > 0) {
                total += progressiveSelection.delivery.price;
            }
            
            // Añadir tasas oficiales
            total += getTasasOficiales();
            
            return total;
        }

        function initializePaymentElementInline() {
            // Calcular el total correctamente
            let totalAmount = 0;
            
            // Usar progressiveSelection si está disponible
            if (progressiveSelection && progressiveSelection.tramite) {
                totalAmount = calculateProgressiveTotal();
            } else {
                // Fallback: intentar usar variables globales o crear un estado mínimo
                const currentBasePrice = window.basePrice || basePrice;
                const currentAdditionalCosts = window.additionalCosts || additionalCosts;
                const currentTaxes = window.taxes || taxes;
                
                // Si no hay nada, crear un trámite básico por defecto
                if (currentBasePrice === 0 && !progressiveSelection.tramite) {
                    console.log('Creando estado mínimo para pago...');
                    progressiveSelection.tramite = { id: 'registro', title: 'Registro bandera polaca' };
                    currentTramiteType = 'registro'; // Sincronizar con variable global
                    totalAmount = getBasePrice('registro') + getTasasOficiales('registro');
                    console.log('Estado mínimo creado. Total:', totalAmount);
                } else {
                    totalAmount = currentBasePrice + currentAdditionalCosts + currentTaxes;
                }
            }
            
            console.log('Total calculado para Stripe:', totalAmount);
            
            if (totalAmount <= 0) {
                console.error('El total debe ser mayor que 0. Total actual:', totalAmount);
                console.log('progressiveSelection:', progressiveSelection);
                console.log('basePrice:', basePrice, 'additionalCosts:', additionalCosts, 'taxes:', taxes);
                // Último recurso: usar precio mínimo
                totalAmount = getBasePrice('registro') + getTasasOficiales('registro');
                console.log('Usando precio mínimo como último recurso:', totalAmount);
                if (totalAmount <= 0) return;
            }

            if (!stripe) {
                console.error('Stripe no está inicializado');
                return;
            }

            // Verificar que el elemento existe y está vacío
            const mountElement = document.getElementById('payment-element-inline');
            if (!mountElement) {
                console.error('Elemento payment-element-inline no encontrado');
                return;
            }

            // Limpiar elemento si ya tiene contenido
            if (mountElement.innerHTML.trim() !== '') {
                mountElement.innerHTML = '';
            }

            // Mostrar spinner mientras creamos el Payment Intent
            mountElement.innerHTML = '<div style="text-align: center; padding: 20px;"><i class="fas fa-spinner fa-spin"></i> Cargando...</div>';

            // Crear Payment Intent en el servidor
            const formData = new FormData();
            formData.append('action', 'create_polish_payment_intent');
            formData.append('amount', totalAmount);

            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Limpiar spinner
                    mountElement.innerHTML = '';

                    // Verificar que Stripe esté disponible
                    if (typeof stripe === 'undefined') {
                        console.error('❌ Stripe no está definido en inline payment');
                        alert('Error: Stripe no se ha cargado correctamente. Por favor, recarga la página.');
                        return;
                    }

                    // Inicializar elementos de Stripe con el client_secret
                    elements = stripe.elements({
                        clientSecret: data.data.client_secret
                    });
                    
                    const paymentElementInline = elements.create('payment');
                    paymentElementInline.mount('#payment-element-inline');
                    console.log('Stripe Payment Element inline montado correctamente con total:', totalAmount);

                    // Manejar errores en tiempo real
                    paymentElementInline.on('change', function(event) {
                        const errorElement = document.getElementById('payment-error-inline');
                        if (event.error) {
                            errorElement.textContent = event.error.message;
                            errorElement.style.display = 'block';
                        } else {
                            errorElement.style.display = 'none';
                        }
                    });

                    paymentElementInline.on('ready', function() {
                        console.log('Stripe Payment Element inline está listo');
                    });

                    // Guardar client_secret para usarlo en confirmPaymentInline
                    window.paymentClientSecret = data.data.client_secret;
                } else {
                    mountElement.innerHTML = '<div style="color: red; text-align: center; padding: 20px;">Error cargando el pago</div>';
                    console.error('Error creando Payment Intent:', data.data);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                mountElement.innerHTML = '<div style="color: red; text-align: center; padding: 20px;">Error de conexión</div>';
            });
        }

        function validateForm() {
            console.log('🔍 VALIDANDO FORMULARIO:');
            
            // Validar que se haya seleccionado un trámite
            // Verificar tanto el sistema progresivo como el tradicional
            const hasTramiteSelected = progressiveSelection.tramite || currentTramiteType;
            console.log('  progressiveSelection.tramite:', progressiveSelection.tramite);
            console.log('  currentTramiteType:', currentTramiteType);
            console.log('  hasTramiteSelected:', hasTramiteSelected);
            
            if (!hasTramiteSelected) {
                console.log('❌ FALLÓ: No hay trámite seleccionado');
                alert('Por favor, seleccione un tipo de trámite.');
                showPage('page-selection');
                return false;
            }

            // Validar datos personales
            const requiredFields = ['customer_name', 'customer_dni', 'customer_email', 'customer_phone'];
            for (let field of requiredFields) {
                const element = document.getElementById(field);
                const value = element ? element.value.trim() : '';
                console.log(`  ${field}:`, value);
                if (!value) {
                    console.log(`❌ FALLÓ: Campo ${field} vacío`);
                    alert('Por favor, complete todos los campos obligatorios.');
                    showPage('page-personal');
                    return false;
                }
            }

            // Validar firma usando el estado de confirmación
            console.log('  signatureConfirmed:', signatureConfirmed);
            if (!signatureConfirmed) {
                console.log('❌ FALLÓ: Firma no confirmada');
                alert('Por favor, proporcione y confirme su firma digital.');
                showPage('page-documents');
                return false;
            }

            // Validar términos y condiciones
            const termsElement = document.getElementById('terms_accept');
            const termsChecked = termsElement ? termsElement.checked : false;
            console.log('  terms_accept:', termsChecked);
            if (!termsChecked) {
                console.log('❌ FALLÓ: Términos no aceptados');
                alert('Debe aceptar los términos y condiciones para continuar.');
                showPage('page-payment');
                return false;
            }

            console.log('✅ VALIDACIÓN EXITOSA');
            return true;
        }

        // Función para generar PDF de autorización
        async function generateAuthorizationPDF() {
            try {
                console.log('📄 Generando PDF de autorización profesional...');
                const { jsPDF } = window.jspdf;
                const doc = new jsPDF();
                
                // Datos del formulario
                const customerName = document.getElementById('customer_name').value;
                const customerDni = document.getElementById('customer_dni').value;
                const customerEmail = document.getElementById('customer_email').value;
                const customerPhone = document.getElementById('customer_phone').value;
                const tramiteType = progressiveSelection.tramite?.title || 'Registro bajo bandera polaca';
                const billingAddress = document.getElementById('billing_address')?.value || '';
                const billingCity = document.getElementById('billing_city')?.value || '';
                const billingPostalCode = document.getElementById('billing_postal_code')?.value || '';
                const billingProvince = document.getElementById('billing_province')?.value || '';
                
                const today = new Date();
                const todayFormatted = today.toLocaleDateString('es-ES', { 
                    year: 'numeric', 
                    month: 'long', 
                    day: 'numeric' 
                });
                const timeFormatted = today.toLocaleTimeString('es-ES', { 
                    hour: '2-digit', 
                    minute: '2-digit' 
                });
                
                // CABECERA CORPORATIVA
                doc.setFillColor(1, 109, 134); // Color #016d86
                doc.rect(0, 0, 210, 25, 'F');
                
                // Logo y nombre empresa
                doc.setTextColor(255, 255, 255);
                doc.setFontSize(20);
                doc.setFont(undefined, 'bold');
                doc.text('TRAMITFY', 20, 16);
                
                doc.setFontSize(10);
                doc.setFont(undefined, 'normal');
                doc.text('Servicios Marítimos Profesionales', 20, 21);
                
                // Número de documento
                doc.setTextColor(255, 255, 255);
                doc.setFontSize(8);
                const docNumber = `DOC-${Date.now()}`;
                doc.text(`Nº: ${docNumber}`, 150, 16);
                doc.text(`Fecha: ${todayFormatted} - ${timeFormatted}`, 150, 21);
                
                // TÍTULO PRINCIPAL
                doc.setTextColor(0, 0, 0);
                doc.setFontSize(18);
                doc.setFont(undefined, 'bold');
                doc.text('AUTORIZACIÓN DE REPRESENTACIÓN', 20, 40);
                doc.text('PARA TRÁMITES MARÍTIMOS', 20, 48);
                
                // Línea decorativa
                doc.setDrawColor(1, 109, 134);
                doc.setLineWidth(0.5);
                doc.line(20, 52, 190, 52);
                
                // DATOS DEL SOLICITANTE
                let yPos = 65;
                doc.setFontSize(14);
                doc.setFont(undefined, 'bold');
                doc.setTextColor(1, 109, 134);
                doc.text('I. DATOS DEL SOLICITANTE', 20, yPos);
                
                // Marco para datos del cliente
                doc.setDrawColor(200, 200, 200);
                doc.setLineWidth(0.3);
                doc.rect(20, yPos + 5, 170, 35);
                
                yPos += 15;
                doc.setFontSize(11);
                doc.setFont(undefined, 'normal');
                doc.setTextColor(0, 0, 0);
                
                doc.setFont(undefined, 'bold');
                doc.text('Nombre completo:', 25, yPos);
                doc.setFont(undefined, 'normal');
                doc.text(customerName, 65, yPos);
                
                yPos += 8;
                doc.setFont(undefined, 'bold');
                doc.text('DNI/NIE:', 25, yPos);
                doc.setFont(undefined, 'normal');
                doc.text(customerDni, 65, yPos);
                
                doc.setFont(undefined, 'bold');
                doc.text('Teléfono:', 120, yPos);
                doc.setFont(undefined, 'normal');
                doc.text(customerPhone, 145, yPos);
                
                yPos += 8;
                doc.setFont(undefined, 'bold');
                doc.text('Email:', 25, yPos);
                doc.setFont(undefined, 'normal');
                doc.text(customerEmail, 65, yPos);
                
                // Dirección si está disponible
                if (billingAddress) {
                    yPos += 8;
                    doc.setFont(undefined, 'bold');
                    doc.text('Dirección:', 25, yPos);
                    doc.setFont(undefined, 'normal');
                    const fullAddress = `${billingAddress}, ${billingPostalCode} ${billingCity}, ${billingProvince}`;
                    doc.text(fullAddress, 65, yPos);
                }
                
                // OBJETO DE LA AUTORIZACIÓN
                yPos += 20;
                doc.setFontSize(14);
                doc.setFont(undefined, 'bold');
                doc.setTextColor(1, 109, 134);
                doc.text('II. OBJETO DE LA AUTORIZACIÓN', 20, yPos);
                
                yPos += 10;
                doc.setFontSize(11);
                doc.setTextColor(0, 0, 0);
                doc.setFont(undefined, 'normal');
                
                const authText = [
                    'Por medio del presente documento, yo, ' + customerName + ', con DNI ' + customerDni + ',',
                    'autorizo expresamente a TRAMITFY, con domicilio social en España, para que en mi',
                    'nombre y representación realice las siguientes gestiones ante las autoridades competentes:',
                    '',
                    '• ' + tramiteType,
                    '• Presentación de documentación requerida',
                    '• Seguimiento del expediente administrativo',
                    '• Recepción de notificaciones oficiales',
                    '• Pago de tasas y aranceles correspondientes'
                ];
                
                authText.forEach(line => {
                    doc.text(line, 25, yPos);
                    yPos += 6;
                });
                
                // DECLARACIONES Y COMPROMISOS
                yPos += 10;
                doc.setFontSize(14);
                doc.setFont(undefined, 'bold');
                doc.setTextColor(1, 109, 134);
                doc.text('III. DECLARACIONES Y COMPROMISOS', 20, yPos);
                
                yPos += 10;
                doc.setFontSize(10);
                doc.setTextColor(0, 0, 0);
                doc.setFont(undefined, 'normal');
                
                const declarations = [
                    '1. Declaro que toda la información proporcionada es veraz y completa.',
                    '2. Autorizo el tratamiento de mis datos personales conforme al RGPD.',
                    '3. Me comprometo a facilitar la documentación adicional que sea requerida.',
                    '4. Acepto los honorarios profesionales acordados para este trámite.',
                    '5. Esta autorización tiene validez hasta la finalización del trámite.'
                ];
                
                declarations.forEach(declaration => {
                    doc.text(declaration, 25, yPos);
                    yPos += 6;
                });
                
                // FIRMA DIGITAL
                yPos += 15;
                doc.setFontSize(14);
                doc.setFont(undefined, 'bold');
                doc.setTextColor(1, 109, 134);
                doc.text('IV. FIRMA DIGITAL', 20, yPos);
                
                // Marco para firma
                doc.setDrawColor(200, 200, 200);
                doc.setLineWidth(0.3);
                doc.rect(20, yPos + 5, 170, 40);
                
                yPos += 15;
                doc.setFontSize(10);
                doc.setTextColor(100, 100, 100);
                doc.text('Firma digitalizada el ' + todayFormatted + ' a las ' + timeFormatted, 25, yPos);
                
                // Insertar firma si existe
                let signatureInserted = false;
                if (signaturePadMain && !signaturePadMain.isEmpty()) {
                    const signatureDataURL = signaturePadMain.toDataURL();
                    doc.addImage(signatureDataURL, 'PNG', 25, yPos + 5, 80, 25);
                    signatureInserted = true;
                } else if (signaturePad && !signaturePad.isEmpty()) {
                    const signatureDataURL = signaturePad.toDataURL();
                    doc.addImage(signatureDataURL, 'PNG', 25, yPos + 5, 80, 25);
                    signatureInserted = true;
                } else if (signaturePadFullscreen && !signaturePadFullscreen.isEmpty()) {
                    const signatureDataURL = signaturePadFullscreen.toDataURL();
                    doc.addImage(signatureDataURL, 'PNG', 25, yPos + 5, 80, 25);
                    signatureInserted = true;
                }
                
                if (!signatureInserted) {
                    doc.setTextColor(200, 0, 0);
                    doc.text('[FIRMA PENDIENTE]', 25, yPos + 15);
                }
                
                // Datos del firmante
                yPos += 35;
                doc.setFontSize(9);
                doc.setTextColor(0, 0, 0);
                doc.setFont(undefined, 'bold');
                doc.text('Firmado por:', 25, yPos);
                doc.setFont(undefined, 'normal');
                doc.text(customerName, 55, yPos);
                
                doc.setFont(undefined, 'bold');
                doc.text('DNI:', 120, yPos);
                doc.setFont(undefined, 'normal');
                doc.text(customerDni, 135, yPos);
                
                // PIE DE PÁGINA
                yPos = 280;
                doc.setDrawColor(1, 109, 134);
                doc.setLineWidth(0.3);
                doc.line(20, yPos, 190, yPos);
                
                yPos += 5;
                doc.setFontSize(8);
                doc.setTextColor(100, 100, 100);
                doc.text('TRAMITFY - Servicios Marítimos Profesionales', 20, yPos);
                doc.text('Este documento ha sido generado digitalmente', 20, yPos + 4);
                
                doc.text('Página 1 de 1', 150, yPos);
                doc.text(`Documento: ${docNumber}`, 150, yPos + 4);
                
                // Convertir a blob
                const pdfBlob = doc.output('blob');
                console.log('✅ PDF de autorización profesional generado:', pdfBlob.size, 'bytes');
                
                return pdfBlob;
            } catch (error) {
                console.error('❌ Error generando PDF profesional:', error);
                return null;
            }
        }

        async function processFormSubmission(paymentIntentId) {
            console.log('🚀 processFormSubmission INICIADO con paymentIntentId:', paymentIntentId);
            const formData = new FormData();
            const form = document.getElementById('polish-registration-form');

            // Debug: Verificar valores antes de enviar
            console.log('🔍 DATOS ANTES DE ENVIAR:');
            console.log('  currentTramiteType:', currentTramiteType);
            console.log('  basePrice:', basePrice);
            console.log('  additionalCosts:', additionalCosts);
            console.log('  taxes:', taxes);
            console.log('  finalAmount:', (basePrice + additionalCosts).toFixed(2));
            console.log('  paymentIntentId:', paymentIntentId);

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
            console.log('🖋️ VERIFICANDO TODAS LAS FIRMAS:');
            console.log('  signaturePad existe:', !!signaturePad);
            console.log('  signaturePad.isEmpty():', signaturePad ? signaturePad.isEmpty() : 'N/A');
            console.log('  signaturePadMain existe:', !!signaturePadMain);
            console.log('  signaturePadMain.isEmpty():', signaturePadMain ? signaturePadMain.isEmpty() : 'N/A');
            console.log('  signaturePadFullscreen existe:', !!signaturePadFullscreen);
            console.log('  signaturePadFullscreen.isEmpty():', signaturePadFullscreen ? signaturePadFullscreen.isEmpty() : 'N/A');
            console.log('  signatureConfirmed:', signatureConfirmed);
            
            let signatureDataURL = null;
            
            // Intentar obtener firma del signaturePad principal
            if (signaturePad && !signaturePad.isEmpty()) {
                signatureDataURL = signaturePad.toDataURL();
                console.log('✅ Usando firma de signaturePad principal');
            }
            // Si no hay en el principal, intentar con signaturePadMain
            else if (signaturePadMain && !signaturePadMain.isEmpty()) {
                signatureDataURL = signaturePadMain.toDataURL();
                console.log('✅ Usando firma de signaturePadMain como fallback');
            }
            // Si no hay en ninguno, intentar con signaturePadFullscreen
            else if (signaturePadFullscreen && !signaturePadFullscreen.isEmpty()) {
                signatureDataURL = signaturePadFullscreen.toDataURL();
                console.log('✅ Usando firma de signaturePadFullscreen como fallback');
            }
            
            if (signatureDataURL) {
                formData.append('signature', signatureDataURL);
                console.log('✅ Firma añadida al FormData:', signatureDataURL.substring(0, 50) + '...');
            } else {
                console.log('❌ No hay firma válida en ningún canvas');
            }

            // Generar y agregar PDF de autorización
            console.log('📄 Generando PDF de autorización...');
            try {
                const authPDF = await generateAuthorizationPDF();
                if (authPDF) {
                    formData.append('autorizacion_pdf', authPDF, 'autorizacion_firmada.pdf');
                    console.log('✅ PDF de autorización añadido al FormData:', authPDF.size, 'bytes');
                } else {
                    console.log('❌ No se pudo generar el PDF de autorización');
                }
            } catch (error) {
                console.error('❌ Error generando PDF de autorización:', error);
            }

            // Log antes del webhook
            fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                method: 'POST',
                body: new URLSearchParams({
                    action: 'log_polaca_debug',
                    message: 'WEBHOOK: Enviando datos al webhook...'
                })
            });

            // Enviar al webhook
            console.log('🚀 Enviando al webhook:', '<?php echo POLISH_REGISTRATION_TRAMITFY_API_URL; ?>');
            fetch('<?php echo POLISH_REGISTRATION_TRAMITFY_API_URL; ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('📡 Respuesta del webhook:', response.status, response.statusText);
                return response.json();
            })
            .then(data => {
                console.log('📥 RESPUESTA DEL WEBHOOK:', data);
                console.log('🔍 DEBUG: data.success =', data.success);
                console.log('🔍 DEBUG: data.tramiteId =', data.tramiteId);
                console.log('🔍 DEBUG: data.id =', data.id);
                
                if (data.success) {
                    console.log('✅ Webhook exitoso - enviando emails');
                    
                    // Log al servidor
                    fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                        method: 'POST',
                        body: new URLSearchParams({
                            action: 'log_polaca_debug',
                            message: `Webhook exitoso - tramiteId: ${data.tramiteId}, id: ${data.id}`
                        })
                    });
                    
                    console.log(`🎉 ¡Trámite enviado con éxito! ID: ${data.tramiteId}`);
                    
                    console.log('🚀 PUNTO CRÍTICO: A punto de llamar sendPolishRegistrationEmails');
                    
                    // Log crítico al servidor
                    fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                        method: 'POST',
                        body: new URLSearchParams({
                            action: 'log_polaca_debug',
                            message: 'PUNTO CRÍTICO: A punto de llamar sendPolishRegistrationEmails'
                        })
                    });
                    
                    // Enviar emails con wp_mail después del webhook exitoso
                    console.log('📧 PRE-CALL: A punto de llamar sendPolishRegistrationEmails');
                    console.log('📧 Parámetros:', { tramiteId: data.tramiteId, transferId: data.id });
                    
                    try {
                        sendPolishRegistrationEmails(data.tramiteId, data.id, function() {
                            console.log('📧 CALLBACK EJECUTADO: emails enviados');
                            // Callback: redirigir DESPUÉS de enviar emails CON DELAY
                            console.log('🔄 Redirigiendo después de enviar emails en 3 segundos...');
                            setTimeout(() => {
                                window.location.href = `https://46-202-128-35.sslip.io/seguimiento/${data.id}`;
                            }, 3000);
                        });
                        console.log('📧 POST-CALL: sendPolishRegistrationEmails ejecutada sin errores');
                    } catch(error) {
                        console.error('📧 ERROR llamando sendPolishRegistrationEmails:', error);
                        logToServer('ERROR en sendPolishRegistrationEmails: ' + error.message);
                    }
                } else {
                    console.error('❌ Error en webhook:', data.error);
                    alert('Error al procesar el trámite: ' + (data.error || 'Error desconocido'));
                }
            })
            .catch(error => {
                console.error('❌ ERROR EN WEBHOOK:', error);
                
                // Log error al servidor
                fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                    method: 'POST',
                    body: new URLSearchParams({
                        action: 'log_polaca_debug',
                        message: `ERROR WEBHOOK: ${error.toString()}`
                    })
                });
                
                alert('Error de conexión. Por favor, inténtelo de nuevo.');
            })
            .finally(() => {
                const confirmButton = document.getElementById('confirm-payment-btn');
                confirmButton.disabled = false;
                confirmButton.innerHTML = '<i class="fas fa-lock"></i> Confirmar Pago';
                closePaymentModal();
            });
        }

        // Función para enviar emails con wp_mail
        function sendPolishRegistrationEmails(tramiteId, transferId, callback) {
            console.log('🔔 sendPolishRegistrationEmails LLAMADA con:', tramiteId, transferId);
            console.log('🔔 INICIANDO PROCESO DE ENVÍO DE EMAILS...');
            console.log('🔔 Callback recibido:', typeof callback);
            
            // Log al servidor
            fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                method: 'POST',
                body: new URLSearchParams({
                    action: 'log_polaca_debug',
                    message: `sendPolishRegistrationEmails EJECUTADA con tramiteId=${tramiteId}, transferId=${transferId}`
                })
            });
            
            const formData = new FormData();
            console.log('📝 FormData creado');
            formData.append('action', 'send_polish_registration_emails');
            formData.append('tramite_id', tramiteId);
            formData.append('transfer_id', transferId);
            formData.append('customer_name', document.getElementById('customer_name').value);
            formData.append('customer_email', document.getElementById('customer_email').value);
            formData.append('customer_dni', document.getElementById('customer_dni').value);
            formData.append('customer_phone', document.getElementById('customer_phone').value);
            formData.append('nonce', '<?php echo wp_create_nonce("polish_registration_emails_nonce"); ?>');
            
            console.log('📨 Datos a enviar:');
            console.log('📨 customer_name:', document.getElementById('customer_name').value);
            console.log('📨 customer_email:', document.getElementById('customer_email').value);
            console.log('📨 customer_dni:', document.getElementById('customer_dni').value);
            console.log('📨 customer_phone:', document.getElementById('customer_phone').value);
            console.log('📨 URL destino:', '<?php echo admin_url("admin-ajax.php"); ?>');

            console.log('🚀 EJECUTANDO FETCH...');
            fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('📡 Response status:', response.status);
                console.log('📡 Response headers:', response.headers);
                console.log('📡 Response OK:', response.ok);
                return response.text(); // Cambiar a text() para ver respuesta cruda
            })
            .then(responseText => {
                console.log('📄 Raw response:', responseText);
                try {
                    const data = JSON.parse(responseText);
                    console.log('📋 Parsed data:', data);
                    if (data.success) {
                        console.log('✅ Emails enviados correctamente');
                    } else {
                        console.error('❌ Error enviando emails:', data.data);
                    }
                } catch (parseError) {
                    console.error('📄 Error parsing JSON:', parseError);
                    console.error('📄 Raw response was:', responseText);
                }
                
                // Ejecutar callback independientemente del resultado
                if (callback && typeof callback === 'function') {
                    console.log('🔄 Ejecutando callback...');
                    callback();
                }
            })
            .catch(error => {
                console.error('❌ Error en envío de emails:', error);
                
                // Ejecutar callback incluso si hay error
                if (callback && typeof callback === 'function') {
                    console.log('🔄 Ejecutando callback después de error...');
                    callback();
                }
            });
        }

        // ========================================
        // AUTO-RELLENADO AVANZADO PARA ADMINISTRADORES
        // ========================================
        <?php if (current_user_can('administrator')): ?>
        const adminAutofillBtn = document.getElementById('admin-autofill-btn');
        if (adminAutofillBtn) {
            adminAutofillBtn.addEventListener('click', async function() {
                console.log('Iniciando auto-rellenado avanzado del formulario polaco...');

                // PASO 1: Seleccionar trámite "registro"
                const registroOption = document.querySelector('.pr-tramite-option[data-tramite="registro"]');
                if (registroOption) {
                    registroOption.click();
                    console.log('✓ Trámite "registro" seleccionado');
                }

                // Esperar a que se configure el formulario
                await new Promise(resolve => setTimeout(resolve, 500));

                // PASO 2: Rellenar datos básicos
                const customerName = document.getElementById('customer_name');
                const customerDni = document.getElementById('customer_dni');
                const customerEmail = document.getElementById('customer_email');
                const customerPhone = document.getElementById('customer_phone');
                const boatPort = document.getElementById('boat_port');

                if (customerName) customerName.value = 'Joan Pinyol Test';
                if (customerDni) customerDni.value = '12345678Z';
                if (customerEmail) customerEmail.value = 'joanpinyol@hotmail.es';
                if (customerPhone) customerPhone.value = '682246937';
                if (boatPort) boatPort.value = 'Gdansk';

                console.log('✓ Datos básicos rellenados');

                await new Promise(resolve => setTimeout(resolve, 300));

                // PASO 3: Seleccionar opciones (si están visibles)
                // Tamaño de embarcación estándar (0-7m)
                const boatSizeStandard = document.querySelector('input[name="boat_size"][value="size_0_7"]');
                if (boatSizeStandard) {
                    boatSizeStandard.checked = true;
                    boatSizeStandard.dispatchEvent(new Event('change', { bubbles: true }));
                    console.log('✓ Tamaño embarcación 0-7m seleccionado');
                }

                // Entrega estándar (no express)
                const deliveryStandard = document.querySelector('input[name="delivery_option"][value="standard"]');
                if (deliveryStandard) {
                    deliveryStandard.checked = true;
                    deliveryStandard.dispatchEvent(new Event('change', { bubbles: true }));
                    console.log('✓ Entrega estándar seleccionada');
                }

                // Sin MMSI
                const mmsiNone = document.querySelector('input[name="mmsi_option"][value="no_mmsi"]');
                if (mmsiNone) {
                    mmsiNone.checked = true;
                    mmsiNone.dispatchEvent(new Event('change', { bubbles: true }));
                    console.log('✓ Sin MMSI seleccionado');
                }

                await new Promise(resolve => setTimeout(resolve, 500));

                // PASO 4: Navegar a documentos
                const navDocs = document.querySelector('.pr-nav-item[data-page="page-documents"]');
                if (navDocs) {
                    navDocs.click();
                    console.log('✓ Navegando a documentación...');
                }

                await new Promise(resolve => setTimeout(resolve, 500));

                // PASO 5: Remover required de documentos (no podemos auto-subir archivos)
                const fileInputs = document.querySelectorAll('input[type="file"][required]');
                fileInputs.forEach(input => {
                    input.removeAttribute('required');
                    console.log('✓ Required removido de:', input.id);
                });

                // PASO 6: Aceptar términos
                const termsAccept = document.querySelector('input[name="terms_accept"]');
                if (termsAccept) {
                    termsAccept.checked = true;
                    console.log('✓ Términos aceptados');
                }

                // PASO 7: Simular firma (mejorada)
                setTimeout(() => {
                    if (signaturePad) {
                        // Limpiar firma anterior
                        signaturePad.clear();
                        
                        // Dibujar una firma más realista
                        const canvas = document.getElementById('signature-pad');
                        const ctx = canvas.getContext('2d');
                        ctx.beginPath();
                        ctx.moveTo(50, 80);
                        ctx.bezierCurveTo(75, 60, 100, 60, 125, 80);
                        ctx.bezierCurveTo(150, 100, 175, 100, 200, 80);
                        ctx.bezierCurveTo(220, 60, 240, 60, 260, 80);
                        ctx.lineWidth = 2;
                        ctx.strokeStyle = '#000';
                        ctx.stroke();
                        console.log('✓ Firma simulada mejorada');
                    }
                }, 1000);

                await new Promise(resolve => setTimeout(resolve, 1200));

                // PASO 8: Navegar a pago
                const navPayment = document.querySelector('.pr-nav-item[data-page="page-payment"]');
                if (navPayment) {
                    navPayment.click();
                    console.log('✓ Navegando a página de pago...');
                }

                await new Promise(resolve => setTimeout(resolve, 500));

                // PASO 9: Aceptar términos de pago
                const termsAcceptPago = document.querySelector('input[name="terms_accept_pago"]');
                if (termsAcceptPago) {
                    termsAcceptPago.checked = true;
                    termsAcceptPago.dispatchEvent(new Event('change', { bubbles: true }));
                    console.log('✓ Términos de pago aceptados');
                }

                console.log('AUTO-RELLENADO AVANZADO COMPLETADO');
                console.log('El formulario está listo para proceder al pago');
                console.log('Usa la tarjeta de prueba Stripe: 4242 4242 4242 4242');

                alert('Formulario auto-rellenado con funcionalidades avanzadas!\n\n' +
                      'Todo configurado según tramitesConfig\n' +
                      'Precio calculado dinámicamente\n' +
                      'Documentos opcionales (required removido)\n' +
                      'Términos aceptados automáticamente\n' +
                      'Firma simulada\n\n' +
                      'Tarjeta de prueba Stripe:\n' +
                      '    • Número: 4242 4242 4242 4242\n' +
                      '    • Fecha: Cualquier fecha futura\n' +
                      '    • CVC: Cualquier 3 dígitos\n\n' +
                      'El formulario se enviará al webhook TRAMITFY');
            });
        }
        <?php endif; ?>

        // ===============================
        // LÓGICA DE SELECCIÓN PROGRESIVA
        // ===============================
        
        // Estado de la selección progresiva (ya inicializado arriba)
        // Actualizar con propiedades adicionales si no existen
        if (!progressiveSelection.currentStep) {
            progressiveSelection.currentStep = 0;
            progressiveSelection.totalPrice = 0;
        }
        
        // Configuración de pasos
        const progressiveSteps = [
            { id: 'tramite', name: 'Trámite', required: true },
            { id: 'boatsize', name: 'Tamaño', required: true, skipFor: ['mmsi'] },
            { id: 'mmsi', name: 'MMSI', required: false, skipFor: ['mmsi'] },
            { id: 'extras', name: 'Extras', required: false },
            { id: 'delivery', name: 'Entrega', required: true },
            { id: 'summary', name: 'Resumen', required: true }
        ];
        
        // Inicializar selección progresiva
        function initProgressiveSelection() {
            // Event listeners para las opciones
            document.addEventListener('click', function(e) {
                const card = e.target.closest('.pr-option-card');
                if (!card) return;
                
                const step = card.dataset.step;
                const selection = card.dataset.selection;
                const price = parseFloat(card.dataset.price || 0);
                
                handleOptionSelection(step, selection, price, card);
            });
            
            // Mostrar el primer paso
            showStep(0);
        }
        
        // Manejar selección de opción
        function handleOptionSelection(step, selection, price, cardElement) {
            if (step === 'extras') {
                // Para extras es multiselección
                handleExtraSelection(selection, price, cardElement);
            } else {
                // Para otros pasos es selección única
                handleSingleSelection(step, selection, price, cardElement);
            }
        }
        
        // Manejar selección única
        function handleSingleSelection(step, selection, price, cardElement) {
            // Limpiar selecciones previas en este paso
            const stepElement = cardElement.closest('.pr-selection-step');
            stepElement.querySelectorAll('.pr-option-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Marcar como seleccionado
            cardElement.classList.add('selected');
            
            // Actualizar estado
            progressiveSelection[step === 'boatsize' ? 'boatSize' : step] = {
                id: selection,
                price: price,
                title: cardElement.querySelector('.pr-option-title').textContent
            };
            
            // Si es selección de trámite, actualizar también currentTramiteType
            if (step === 'tramite') {
                currentTramiteType = selection;
                console.log('Trámite seleccionado:', selection, '- currentTramiteType actualizado');
            }
            
            // Para trámite MMSI, saltar pasos de tamaño y mmsi
            if (step === 'tramite' && selection === 'mmsi') {
                progressiveSelection.boatSize = { id: 'none', price: 0, title: 'N/A' };
                progressiveSelection.mmsi = { id: 'none', price: 0, title: 'N/A' };
            }
            
            // Actualizar sidebar tracking
            updateSidebarTracking();
            
            // Avanzar al siguiente paso después de un breve delay
            setTimeout(() => {
                goToNextStep();
            }, 500);
        }
        
        // Manejar selección de extras (multiselección)
        function handleExtraSelection(selection, price, cardElement) {
            const isSelected = cardElement.classList.contains('selected');
            
            if (isSelected) {
                // Deseleccionar
                cardElement.classList.remove('selected');
                progressiveSelection.extras = progressiveSelection.extras.filter(extra => extra.id !== selection);
            } else {
                // Seleccionar
                cardElement.classList.add('selected');
                progressiveSelection.extras.push({
                    id: selection,
                    price: price,
                    title: cardElement.querySelector('.pr-option-title').textContent
                });
            }
            
            // Actualizar sidebar tracking
            updateSidebarTracking();
            
            updateBreadcrumb();
            updateTotal();
            
            // Mostrar botón de continuar si no hay selecciones, o después de cada selección
            const btnContinue = document.getElementById('btn-continue');
            btnContinue.style.display = 'block';
        }
        
        // Ir al siguiente paso
        function goToNextStep() {
            const currentStepData = progressiveSteps[progressiveSelection.currentStep];
            let nextStepIndex = progressiveSelection.currentStep + 1;
            
            // Encontrar el próximo paso válido
            while (nextStepIndex < progressiveSteps.length) {
                const nextStep = progressiveSteps[nextStepIndex];
                
                // Verificar si este paso debe saltarse
                if (nextStep.skipFor && progressiveSelection.tramite && 
                    nextStep.skipFor.includes(progressiveSelection.tramite.id)) {
                    nextStepIndex++;
                    continue;
                }
                
                break;
            }
            
            if (nextStepIndex >= progressiveSteps.length) {
                // Hemos terminado todos los pasos
                finishSelection();
                return;
            }
            
            progressiveSelection.currentStep = nextStepIndex;
            showStep(nextStepIndex);
            updateBreadcrumb();
            updateTotal();
        }
        
        // Ir al paso anterior
        function goBackStep() {
            if (progressiveSelection.currentStep > 0) {
                let prevStepIndex = progressiveSelection.currentStep - 1;
                
                // Encontrar el paso anterior válido
                while (prevStepIndex >= 0) {
                    const prevStep = progressiveSteps[prevStepIndex];
                    
                    if (prevStep.skipFor && progressiveSelection.tramite && 
                        prevStep.skipFor.includes(progressiveSelection.tramite.id)) {
                        prevStepIndex--;
                        continue;
                    }
                    
                    break;
                }
                
                if (prevStepIndex >= 0) {
                    progressiveSelection.currentStep = prevStepIndex;
                    showStep(prevStepIndex);
                }
            }
        }
        
        // Mostrar paso específico
        function showStep(stepIndex) {
            // Ocultar todos los pasos
            document.querySelectorAll('.pr-selection-step').forEach(step => {
                step.classList.remove('active');
            });
            
            // Mostrar el paso actual
            const stepData = progressiveSteps[stepIndex];
            const stepElement = document.getElementById(`step-${stepData.id}`);
            if (stepElement) {
                stepElement.classList.add('active');
            }
            
            // Si es el paso de resumen, actualizarlo
            if (stepData.id === 'summary') {
                updateSummaryDisplay();
            }
            
            // Actualizar botones de navegación
            updateNavigationButtons();
        }
        
        // Actualizar botones de navegación
        function updateNavigationButtons() {
            const btnBack = document.getElementById('btn-back-step');
            const btnContinue = document.getElementById('btn-continue');
            
            // Botón atrás (mostrar si no estamos en el primer paso)
            if (progressiveSelection.currentStep > 0) {
                btnBack.style.display = 'block';
            } else {
                btnBack.style.display = 'none';
            }
            
            // Botón continuar (ocultar por defecto, se muestra cuando se hace selección)
            const currentStepData = progressiveSteps[progressiveSelection.currentStep];
            if (currentStepData.id === 'extras') {
                // Para extras, mostrar siempre
                btnContinue.style.display = 'block';
            } else if (currentStepData.id === 'summary') {
                // Para resumen, mostrar botón de continuar final
                btnContinue.style.display = 'block';
                btnContinue.textContent = 'Continuar a Datos Personales';
            } else {
                btnContinue.style.display = 'none';
            }
        }
        
        // Actualizar breadcrumb (ahora solo actualiza el tracking del sidebar)
        function updateBreadcrumb() {
            // La funcionalidad del breadcrumb ahora está integrada en updateSidebarTracking
            // Mantenemos esta función para compatibilidad con el código existente
            updateSidebarTracking();
        }
        
        // Actualizar total
        // Función para actualizar el sidebar con el tracking en tiempo real
        function updateSidebarTracking() {
            // Cambiar del sidebar inicial al de tracking cuando se hace la primera selección
            const initialSidebar = document.getElementById('sidebar-initial');
            const trackingSidebar = document.getElementById('sidebar-tracking');
            
            // Si es la primera vez que se selecciona algo, cambiar sidebars
            if (initialSidebar && initialSidebar.style.display !== 'none') {
                initialSidebar.style.display = 'none';
                trackingSidebar.style.display = 'block';
            }
            
            // Actualizar contenido del tracking
            const trackingContainer = document.querySelector('.pr-tracking-container');
            let trackingHTML = '';
            
            // Tipo de trámite
            if (progressiveSelection.tramite) {
                const tramiteLabels = {
                    'registro': 'Registro nuevo',
                    'cambio_titularidad': 'Cambio de titularidad',
                    'mmsi': 'Solicitud MMSI'
                };
                trackingHTML += `
                    <div class="pr-tracking-item">
                        <div class="pr-tracking-label">Tipo de trámite</div>
                        <div class="pr-tracking-value">${tramiteLabels[progressiveSelection.tramite.id] || progressiveSelection.tramite.title}</div>
                        ${progressiveSelection.tramite.price > 0 ? `<div class="pr-tracking-price">+€ ${progressiveSelection.tramite.price.toFixed(2)}</div>` : ''}
                    </div>
                `;
            }
            
            // Tamaño del barco (solo si aplica)
            if (progressiveSelection.boatSize && progressiveSelection.boatSize.id !== 'none') {
                const sizeLabels = {
                    'size_0_7': 'Hasta 7 metros',
                    'size_7_12': 'Entre 7 y 12 metros',
                    'size_12_24': 'Entre 12 y 24 metros'
                };
                trackingHTML += `
                    <div class="pr-tracking-item">
                        <div class="pr-tracking-label">Tamaño del barco</div>
                        <div class="pr-tracking-value">${sizeLabels[progressiveSelection.boatSize.id] || progressiveSelection.boatSize.title}</div>
                        ${progressiveSelection.boatSize.price > 0 ? `<div class="pr-tracking-price">+€ ${progressiveSelection.boatSize.price.toFixed(2)}</div>` : ''}
                    </div>
                `;
            }
            
            // MMSI
            if (progressiveSelection.mmsi && progressiveSelection.mmsi.id !== 'none' && progressiveSelection.mmsi.id !== 'no_mmsi') {
                const mmsiLabels = {
                    'mmsi_licensed': 'MMSI con licencia',
                    'mmsi_unlicensed': 'MMSI sin licencia',
                    'mmsi_company': 'MMSI empresa'
                };
                trackingHTML += `
                    <div class="pr-tracking-item">
                        <div class="pr-tracking-label">Opción MMSI</div>
                        <div class="pr-tracking-value">${mmsiLabels[progressiveSelection.mmsi.id] || progressiveSelection.mmsi.title}</div>
                        ${progressiveSelection.mmsi.price > 0 ? `<div class="pr-tracking-price">+€ ${progressiveSelection.mmsi.price.toFixed(2)}</div>` : ''}
                    </div>
                `;
            }
            
            // Servicios extras
            if (progressiveSelection.extras && progressiveSelection.extras.length > 0) {
                const extrasLabels = {
                    'apostilla': 'Apostilla de la Haya',
                    'extracto': 'Extracto registral',
                    'bandera_fisica': 'Bandera física'
                };
                
                trackingHTML += `
                    <div class="pr-tracking-item">
                        <div class="pr-tracking-label">Servicios adicionales</div>
                `;
                
                progressiveSelection.extras.forEach(extra => {
                    trackingHTML += `
                        <div class="pr-tracking-value">• ${extrasLabels[extra.id] || extra.title}
                            ${extra.price > 0 ? ` <span class="pr-tracking-price">+€ ${extra.price.toFixed(2)}</span>` : ''}
                        </div>
                    `;
                });
                
                trackingHTML += `</div>`;
            }
            
            // Opción de entrega
            if (progressiveSelection.delivery) {
                const deliveryLabels = {
                    'delivery_standard': 'Entrega estándar',
                    'delivery_express': 'Entrega express (24-48h)'
                };
                trackingHTML += `
                    <div class="pr-tracking-item">
                        <div class="pr-tracking-label">Método de entrega</div>
                        <div class="pr-tracking-value">${deliveryLabels[progressiveSelection.delivery.id] || progressiveSelection.delivery.title}</div>
                        ${progressiveSelection.delivery.price > 0 ? `<div class="pr-tracking-price">+€ ${progressiveSelection.delivery.price.toFixed(2)}</div>` : ''}
                    </div>
                `;
            }
            
            trackingContainer.innerHTML = trackingHTML;
            
            // Actualizar precio total
            updateTrackingPrice();
        }
        
        // Función para actualizar el precio en el sidebar de tracking
        function updateTrackingPrice() {
            let total = 0;
            
            // Obtener precio base del trámite
            if (progressiveSelection.tramite) {
                const prices = {
                    'registro': 429.99,
                    'cambio_titularidad': 429.99,
                    'mmsi': 190.00
                };
                total = prices[progressiveSelection.tramite.id] || 0;
            }
            
            // Sumar opciones adicionales
            if (progressiveSelection.boatSize) total += progressiveSelection.boatSize.price || 0;
            if (progressiveSelection.mmsi) total += progressiveSelection.mmsi.price || 0;
            if (progressiveSelection.delivery) total += progressiveSelection.delivery.price || 0;
            
            // Sumar extras
            if (progressiveSelection.extras) {
                progressiveSelection.extras.forEach(extra => {
                    total += extra.price || 0;
                });
            }
            
            // Actualizar precio en sidebar
            const priceBox = document.getElementById('tracking-price-box');
            const priceElement = document.getElementById('tracking-price');
            
            if (total > 0) {
                priceBox.style.display = 'block';
                priceElement.textContent = `€ ${total.toFixed(2)}`;
            }
        }
        
        // Función para actualizar la página de resumen (solo servicios seleccionados)
        function updateSummaryPage() {
            // Actualizar detalles del trámite
            updateSummaryTramiteDetails();
            
            // Actualizar resumen de precios
            updateSummaryPriceBreakdown();
        }
        
        function updateSummaryTramiteDetails() {
            const container = document.getElementById('summary-tramite-details');
            let html = '';
            
            if (progressiveSelection.tramite) {
                const tramiteLabels = {
                    'registro': 'Registro bandera polaca',
                    'cambio_titularidad': 'Cambio de titularidad',
                    'mmsi': 'Número MMSI polaco'
                };
                
                html += `
                    <div class="pr-service-item">
                        <span class="pr-service-label">${tramiteLabels[progressiveSelection.tramite.id] || progressiveSelection.tramite.title}</span>
                        <span class="pr-service-price">€ ${getBasePrice(progressiveSelection.tramite.id).toFixed(2)}</span>
                    </div>
                `;
            }
            
            // Tamaño del barco
            if (progressiveSelection.boatSize && progressiveSelection.boatSize.id !== 'none' && progressiveSelection.boatSize.price > 0) {
                const sizeLabels = {
                    'size_7_12': 'Suplemento 7-12m',
                    'size_12_24': 'Suplemento 12-24m'
                };
                html += `
                    <div class="pr-service-item">
                        <span class="pr-service-label">${sizeLabels[progressiveSelection.boatSize.id] || 'Suplemento tamaño'}</span>
                        <span class="pr-service-price">+ € ${progressiveSelection.boatSize.price.toFixed(2)}</span>
                    </div>
                `;
            }
            
            // MMSI
            if (progressiveSelection.mmsi && progressiveSelection.mmsi.id !== 'none' && progressiveSelection.mmsi.id !== 'no_mmsi' && progressiveSelection.mmsi.price > 0) {
                html += `
                    <div class="pr-service-item">
                        <span class="pr-service-label">Número MMSI</span>
                        <span class="pr-service-price">+ € ${progressiveSelection.mmsi.price.toFixed(2)}</span>
                    </div>
                `;
            }
            
            // Servicios extras
            if (progressiveSelection.extras && progressiveSelection.extras.length > 0) {
                const extrasLabels = {
                    'apostilla': 'Apostilla de la Haya',
                    'extracto': 'Extracto registral',
                    'bandera_fisica': 'Bandera física'
                };
                
                progressiveSelection.extras.forEach(extra => {
                    if (extra.price > 0) {
                        html += `
                            <div class="pr-service-item">
                                <span class="pr-service-label">${extrasLabels[extra.id] || extra.title}</span>
                                <span class="pr-service-price">+ € ${extra.price.toFixed(2)}</span>
                            </div>
                        `;
                    }
                });
            }
            
            // Método de entrega
            if (progressiveSelection.delivery && progressiveSelection.delivery.price > 0) {
                html += `
                    <div class="pr-service-item">
                        <span class="pr-service-label">Entrega express</span>
                        <span class="pr-service-price">+ € ${progressiveSelection.delivery.price.toFixed(2)}</span>
                    </div>
                `;
            }
            
            container.innerHTML = html;
        }
        
        function getBasePrice(tramiteId) {
            const prices = {
                'registro': 429.99,
                'cambio_titularidad': 429.99,
                'mmsi': 190.00
            };
            return prices[tramiteId] || 0;
        }
        
        function getTasasOficiales(tramiteId) {
            // Si no se especifica tramiteId, usar el actual
            if (!tramiteId && progressiveSelection && progressiveSelection.tramite) {
                tramiteId = progressiveSelection.tramite.id;
            }
            if (!tramiteId) tramiteId = 'registro'; // Fallback
            
            const tasas = {
                'registro': 45.00,
                'cambio_titularidad': 45.00,
                'mmsi': 25.00
            };
            return tasas[tramiteId] || 0;
        }
        
        
        function updateSummaryPriceBreakdown() {
            const finalPriceElement = document.getElementById('summary-final-price');
            let total = 0;
            
            // Calcular total sin mostrar desglose (ya está en la lista de servicios)
            if (progressiveSelection.tramite) {
                total += getBasePrice(progressiveSelection.tramite.id);
            }
            
            if (progressiveSelection.boatSize) total += progressiveSelection.boatSize.price || 0;
            if (progressiveSelection.mmsi) total += progressiveSelection.mmsi.price || 0;
            if (progressiveSelection.delivery) total += progressiveSelection.delivery.price || 0;
            
            if (progressiveSelection.extras) {
                progressiveSelection.extras.forEach(extra => {
                    total += extra.price || 0;
                });
            }
            
            finalPriceElement.textContent = `€ ${total.toFixed(2)}`;
            
            // Mostrar solo un resumen muy simple
            const container = document.getElementById('summary-price-breakdown');
            container.innerHTML = `
                <div class="pr-price-row">
                    <span>IVA incluido</span>
                    <span>Todo incluido</span>
                </div>
            `;
        }
        
        function updateTotal() {
            let total = 0;
            
            if (progressiveSelection.tramite) total += progressiveSelection.tramite.price;
            if (progressiveSelection.boatSize) total += progressiveSelection.boatSize.price;
            if (progressiveSelection.mmsi) total += progressiveSelection.mmsi.price;
            if (progressiveSelection.delivery) total += progressiveSelection.delivery.price;
            
            progressiveSelection.extras.forEach(extra => {
                total += extra.price;
            });
            
            progressiveSelection.totalPrice = total;
            
            // Actualizar precio en breadcrumb - ELIMINADO: funcionalidad integrada en sidebar
            // Ya no necesitamos actualizar elementos del breadcrumb eliminado
        }
        
        // Saltar extras
        function skipExtras() {
            progressiveSelection.extras = [];
            goToNextStep();
        }
        
        // Actualizar resumen
        function updateSummaryDisplay() {
            // Actualizar trámite principal
            if (progressiveSelection.tramite) {
                document.getElementById('summary-tramite').innerHTML = `
                    <div class="pr-summary-item-title">${progressiveSelection.tramite.title}</div>
                    <div class="pr-summary-item-price">€ ${progressiveSelection.tramite.price}</div>
                `;
            }
            
            // Actualizar tamaño embarcación
            const boatSection = document.getElementById('summary-boat-section');
            if (progressiveSelection.boatSize && progressiveSelection.boatSize.id !== 'none') {
                boatSection.style.display = 'block';
                document.getElementById('summary-boatsize').innerHTML = `
                    <div class="pr-summary-item-title">${progressiveSelection.boatSize.title}</div>
                    <div class="pr-summary-item-price">+€ ${progressiveSelection.boatSize.price}</div>
                `;
            } else {
                boatSection.style.display = 'none';
            }
            
            // Actualizar MMSI
            const mmsiSection = document.getElementById('summary-mmsi-section');
            if (progressiveSelection.mmsi && progressiveSelection.mmsi.id !== 'none' && progressiveSelection.mmsi.id !== 'no_mmsi') {
                mmsiSection.style.display = 'block';
                document.getElementById('summary-mmsi').innerHTML = `
                    <div class="pr-summary-item-title">${progressiveSelection.mmsi.title}</div>
                    <div class="pr-summary-item-price">+€ ${progressiveSelection.mmsi.price}</div>
                `;
            } else {
                mmsiSection.style.display = 'none';
            }
            
            // Actualizar servicios adicionales
            const extrasSection = document.getElementById('summary-extras-section');
            if (progressiveSelection.extras.length > 0) {
                extrasSection.style.display = 'block';
                const extrasHTML = progressiveSelection.extras.map(extra => `
                    <div class="pr-summary-item">
                        <div class="pr-summary-item-title">${extra.title}</div>
                        <div class="pr-summary-item-price">+€ ${extra.price}</div>
                    </div>
                `).join('');
                document.getElementById('summary-extras').innerHTML = extrasHTML;
            } else {
                extrasSection.style.display = 'none';
            }
            
            // Actualizar entrega
            if (progressiveSelection.delivery) {
                document.getElementById('summary-delivery').innerHTML = `
                    <div class="pr-summary-item-title">${progressiveSelection.delivery.title}</div>
                    <div class="pr-summary-item-price">+€ ${progressiveSelection.delivery.price}</div>
                `;
            }
            
            // Actualizar total
            document.getElementById('summary-total-price').textContent = `€ ${progressiveSelection.totalPrice.toFixed(2)}`;
        }
        
        // Resetear a un paso específico
        function resetToStep(stepIndex) {
            progressiveSelection.currentStep = stepIndex;
            
            // Limpiar selecciones posteriores
            if (stepIndex === 0) {
                progressiveSelection = {
                    tramite: null,
                    boatSize: null,
                    mmsi: null,
                    extras: [],
                    delivery: null,
                    currentStep: 0,
                    totalPrice: 0
                };
                
                // Resetear sidebar al inicial
                const initialSidebar = document.getElementById('sidebar-initial');
                const trackingSidebar = document.getElementById('sidebar-tracking');
                if (initialSidebar && trackingSidebar) {
                    initialSidebar.style.display = 'block';
                    trackingSidebar.style.display = 'none';
                }
            }
            
            showStep(stepIndex);
            updateBreadcrumb();
            updateTotal();
            
            // Limpiar selecciones visuales
            document.querySelectorAll('.pr-option-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Mostrar área de selección progresiva
            document.getElementById('progressive-selection').style.display = 'block';
        }
        
        // Finalizar selección
        function finishSelection() {
            // Actualizar el formulario tradicional con las selecciones
            updateTraditionalForm();
            
            // Actualizar breadcrumb final
            updateBreadcrumb();
            
            // Ir automáticamente a la página de resumen
            showPage('page-summary');
        }
        
        // Actualizar formulario tradicional con las selecciones
        function updateTraditionalForm() {
            if (progressiveSelection.tramite) {
                // Marcar el radio button correspondiente
                const tramiteRadio = document.querySelector(`input[name="tramite_type"][value="${progressiveSelection.tramite.id}"]`);
                if (tramiteRadio) {
                    tramiteRadio.checked = true;
                }
                
                // Actualizar el precio total (esto se integra con el sistema existente)
                currentTramiteType = progressiveSelection.tramite.id;
                updateTotalPrice();
            }
        }
        
        // Inicializar cuando el DOM esté listo
        document.addEventListener('DOMContentLoaded', function() {
            initProgressiveSelection();
        });

    </script>

    <?php
    return ob_get_clean();
}

// Registrar shortcode en init para evitar conflictos con Elementor
add_action('init', 'polish_registration_init');

function polish_registration_init() {
    add_shortcode('polish_registration_form', 'polish_registration_form_shortcode');
}

// Handlers AJAX para Stripe
add_action('wp_ajax_create_polish_payment_intent', 'create_polish_payment_intent');
add_action('wp_ajax_nopriv_create_polish_payment_intent', 'create_polish_payment_intent');

function create_polish_payment_intent() {
    // Headers para asegurar respuesta JSON
    header('Content-Type: application/json');
    
    try {
        // Definir constantes si no están definidas
        if (!defined('POLISH_REGISTRATION_STRIPE_MODE')) {
            define('POLISH_REGISTRATION_STRIPE_MODE', 'test'); // 'test' o 'live'
            define('POLISH_REGISTRATION_STRIPE_TEST_PUBLIC_KEY', 'STRIPE_PUBLIC_KEY_PLACEHOLDER');
            define('POLISH_REGISTRATION_STRIPE_TEST_SECRET_KEY', 'STRIPE_SECRET_KEY_PLACEHOLDER');
            define('POLISH_REGISTRATION_STRIPE_LIVE_PUBLIC_KEY', 'STRIPE_LIVE_PUBLIC_KEY_PLACEHOLDER');
            define('POLISH_REGISTRATION_STRIPE_LIVE_SECRET_KEY', 'STRIPE_LIVE_SECRET_KEY_PLACEHOLDER');
        }

        // Validación básica sin nonce por ahora para debug
        if (!isset($_POST['amount']) || empty($_POST['amount'])) {
            wp_send_json_error('Amount is required');
            return;
        }

        $amount = floatval($_POST['amount']);
        $currency = 'eur';
        
        // Validar amount
        if ($amount <= 0) {
            wp_send_json_error('Invalid amount: ' . $amount);
            return;
        }

        // Configurar Stripe - usar cURL más robusto
        $stripe_secret_key = (POLISH_REGISTRATION_STRIPE_MODE === 'live') ? 
            POLISH_REGISTRATION_STRIPE_LIVE_SECRET_KEY : 
            POLISH_REGISTRATION_STRIPE_TEST_SECRET_KEY;

        if (empty($stripe_secret_key)) {
            wp_send_json_error('Stripe configuration error');
            return;
        }

        // Usar cURL en lugar de file_get_contents para mejor control
        $curl = curl_init();
        
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.stripe.com/v1/payment_intents',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => http_build_query(array(
                'amount' => round($amount * 100),
                'currency' => $currency,
                'automatic_payment_methods[enabled]' => 'true',
                'metadata[source]' => 'polish_registration_form',
                'metadata[timestamp]' => time()
            )),
            CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer ' . $stripe_secret_key,
                'Content-Type: application/x-www-form-urlencoded'
            ),
        ));

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        
        if (curl_error($curl)) {
            curl_close($curl);
            wp_send_json_error('cURL error: ' . curl_error($curl));
            return;
        }
        
        curl_close($curl);

        if ($httpCode !== 200) {
            wp_send_json_error('Stripe API HTTP error: ' . $httpCode);
            return;
        }

        $payment_intent = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error('Invalid JSON response from Stripe');
            return;
        }

        if (isset($payment_intent['error'])) {
            wp_send_json_error('Stripe API error: ' . $payment_intent['error']['message']);
            return;
        }

        if (!isset($payment_intent['client_secret'])) {
            wp_send_json_error('Missing client_secret in Stripe response');
            return;
        }

        wp_send_json_success([
            'client_secret' => $payment_intent['client_secret']
        ]);

    } catch (Exception $e) {
        error_log('Polish Payment Intent Error: ' . $e->getMessage());
        wp_send_json_error('Server error: ' . $e->getMessage());
    } catch (Error $e) {
        error_log('Polish Payment Intent Fatal Error: ' . $e->getMessage());
        wp_send_json_error('Fatal error: ' . $e->getMessage());
    }
}

add_action('wp_ajax_handle_polish_registration_webhook', 'handle_polish_registration_webhook');
add_action('wp_ajax_nopriv_handle_polish_registration_webhook', 'handle_polish_registration_webhook');

function handle_polish_registration_webhook() {
    try {
        // Si viene de un redirect de Stripe con éxito
        if (isset($_GET['payment_success']) && $_GET['payment_success'] === 'true') {
            wp_redirect(home_url('/?tramite_success=polaca'));
            exit;
        }

        wp_send_json_success(['message' => 'Webhook handled successfully']);

    } catch (Exception $e) {
        error_log('Error in Polish registration webhook: ' . $e->getMessage());
        wp_send_json_error('Error processing webhook: ' . $e->getMessage());
    }
}

// AJAX handler para envío de emails con wp_mail
add_action('wp_ajax_send_polish_registration_emails', 'send_polish_registration_emails_handler');
add_action('wp_ajax_nopriv_send_polish_registration_emails', 'send_polish_registration_emails_handler');

// Test handler para verificar que AJAX funciona
add_action('wp_ajax_test_polish_emails', 'test_polish_emails_handler');
add_action('wp_ajax_nopriv_test_polish_emails', 'test_polish_emails_handler');

function test_polish_emails_handler() {
    error_log('🧪 TEST HANDLER EJECUTADO - AJAX FUNCIONA');
    wp_send_json_success('Test handler funciona correctamente');
}

// Logger simple para debug de JavaScript
add_action('wp_ajax_log_polaca_debug', 'log_polaca_debug_handler');
add_action('wp_ajax_nopriv_log_polaca_debug', 'log_polaca_debug_handler');

function log_polaca_debug_handler() {
    $message = sanitize_text_field($_POST['message']);
    file_put_contents('/root/polaca-js-debug.log', date('Y-m-d H:i:s') . " - " . $message . "\n", FILE_APPEND);
    wp_send_json_success('Log guardado');
}

// Sistema de logs específico para emails polaca
function log_polaca_email($message) {
    $timestamp = date('Y-m-d H:i:s');
    $full_message = "POLACA-EMAIL [{$timestamp}] {$message}";
    error_log($full_message); // Solo usar error_log por ahora
}

// Configuración SMTP para wp_mail
function configure_smtp_for_polish_registration($phpmailer) {
    log_polaca_email('🔧 INICIANDO CONFIGURACIÓN SMTP...');
    
    $phpmailer->isSMTP();
    $phpmailer->Host = 'smtp.gmail.com';
    $phpmailer->SMTPAuth = true;
    $phpmailer->Username = 'info@tramitfy.es';
    $phpmailer->Password = '2Dn~uPK&z';
    $phpmailer->SMTPSecure = 'ssl';
    $phpmailer->Port = 465;
    $phpmailer->From = 'info@tramitfy.es';
    $phpmailer->FromName = 'Tramitfy';
    $phpmailer->SMTPDebug = 2; // Debug SMTP
    $phpmailer->Debugoutput = function($str, $level) {
        log_polaca_email("SMTP DEBUG: {$str}");
    };
    
    log_polaca_email('✅ SMTP CONFIGURADO - Host: smtp.gmail.com, Usuario: info@tramitfy.es, Puerto: 587');
}

function send_polish_registration_emails_handler() {
    // Log directo a archivo que sabemos funciona
    file_put_contents('/root/polaca-debug.log', date('Y-m-d H:i:s') . " - HANDLER EJECUTADO\n", FILE_APPEND);
    
    // Log inmediato para verificar que se ejecuta
    error_log('🇵🇱 HANDLER LLAMADO - ' . date('Y-m-d H:i:s'));
    log_polaca_email('🇵🇱 INICIANDO HANDLER DE EMAILS POLACA');
    log_polaca_email('📨 POST DATA: ' . print_r($_POST, true));
    
    // Usar wp_mail() nativo de WordPress sin configuración SMTP manual
    log_polaca_email('📧 USANDO wp_mail() NATIVO DE WORDPRESS');
    
    // Verificar nonce de seguridad - TEMPORAL: DESHABILITADO PARA DEBUG
    log_polaca_email('⚠️ NONCE VERIFICATION TEMPORALMENTE DESHABILITADO PARA DEBUG');
    // if (!wp_verify_nonce($_POST['nonce'], 'polish_registration_emails_nonce')) {
    //     log_polaca_email('❌ NONCE VERIFICATION FAILED');
    //     wp_send_json_error('Nonce verification failed');
    //     return;
    // }
    
    log_polaca_email('✅ NONCE VERIFICADO - Procesando emails');

    $tramite_id = sanitize_text_field($_POST['tramite_id']);
    $transfer_id = sanitize_text_field($_POST['transfer_id']);
    $customer_name = sanitize_text_field($_POST['customer_name']);
    $customer_email = sanitize_email($_POST['customer_email']);
    $customer_dni = sanitize_text_field($_POST['customer_dni']);
    $customer_phone = sanitize_text_field($_POST['customer_phone']);

    log_polaca_email("📋 DATOS PROCESADOS - Trámite: {$tramite_id}, Cliente: {$customer_name}, Email: {$customer_email}");

    if (!$customer_email || !is_email($customer_email)) {
        log_polaca_email("❌ EMAIL INVÁLIDO: {$customer_email}");
        wp_send_json_error('Email de cliente no válido');
        return;
    }

    log_polaca_email("✅ EMAIL VÁLIDO - Procediendo con envío");

    try {
        $tracking_url = "https://46-202-128-35.sslip.io/seguimiento/{$transfer_id}";
        log_polaca_email("🔗 TRACKING URL: {$tracking_url}");
        
        // Email al cliente
        $subject_client = "Confirmación - Registro bajo Bandera Polaca #{$tramite_id}";
        $message_client = "
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Confirmación Registro Bandera Polaca</title>
        </head>
        <body style='margin: 0; padding: 20px; font-family: \"Segoe UI\", Tahoma, Geneva, Verdana, sans-serif; background-color: #f8fafc; line-height: 1.6;'>
            <div style='max-width: 650px; margin: 0 auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);'>
                
                <!-- Header -->
                <div style='background: linear-gradient(135deg, #016d86 0%, #0891b2 100%); padding: 40px 30px; text-align: center;'>
                    <h1 style='color: white; margin: 0; font-size: 24px; font-weight: 600;'>🇵🇱 Registro Bandera Polaca</h1>
                    <p style='color: #bfdbfe; margin: 8px 0 0 0; font-size: 16px;'>Confirmación de Solicitud</p>
                </div>
                
                <!-- Content -->
                <div style='padding: 40px 30px;'>
                    <p style='margin: 0 0 25px 0; font-size: 16px; color: #374151;'>Estimado/a <strong>{$customer_name}</strong>,</p>
                    
                    <p style='margin: 0 0 30px 0; font-size: 16px; color: #374151;'>Hemos recibido correctamente su solicitud de <strong>Registro bajo Bandera Polaca</strong> y está siendo procesada por nuestro equipo especializado.</p>
                    
                    <!-- Información del Trámite -->
                    <div style='background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 8px; padding: 25px; margin: 30px 0;'>
                        <h3 style='margin: 0 0 20px 0; color: #016d86; font-size: 18px; font-weight: 600;'>📋 Información del Trámite</h3>
                        <table style='width: 100%; border-collapse: collapse;'>
                            <tr>
                                <td style='padding: 8px 0; color: #6b7280; font-weight: 500; width: 40%;'>Número de trámite:</td>
                                <td style='padding: 8px 0; color: #111827; font-weight: 600;'>{$tramite_id}</td>
                            </tr>
                            <tr>
                                <td style='padding: 8px 0; color: #6b7280; font-weight: 500;'>Estado:</td>
                                <td style='padding: 8px 0; color: #059669; font-weight: 600;'>✓ Pendiente de revisión</td>
                            </tr>
                            <tr>
                                <td style='padding: 8px 0; color: #6b7280; font-weight: 500;'>Fecha de solicitud:</td>
                                <td style='padding: 8px 0; color: #111827; font-weight: 600;'>" . date('d/m/Y H:i') . "</td>
                            </tr>
                            <tr>
                                <td style='padding: 8px 0; color: #6b7280; font-weight: 500;'>Tipo de trámite:</td>
                                <td style='padding: 8px 0; color: #111827; font-weight: 600;'>Registro bajo Bandera Polaca</td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- Datos del Cliente -->
                    <div style='background: #fefefe; border: 1px solid #e5e7eb; border-radius: 8px; padding: 25px; margin: 30px 0;'>
                        <h3 style='margin: 0 0 20px 0; color: #016d86; font-size: 18px; font-weight: 600;'>👤 Datos del Solicitante</h3>
                        <table style='width: 100%; border-collapse: collapse;'>
                            <tr>
                                <td style='padding: 8px 0; color: #6b7280; font-weight: 500; width: 30%;'>Nombre:</td>
                                <td style='padding: 8px 0; color: #111827; font-weight: 600;'>{$customer_name}</td>
                            </tr>
                            <tr>
                                <td style='padding: 8px 0; color: #6b7280; font-weight: 500;'>DNI/NIE:</td>
                                <td style='padding: 8px 0; color: #111827; font-weight: 600;'>{$customer_dni}</td>
                            </tr>
                            <tr>
                                <td style='padding: 8px 0; color: #6b7280; font-weight: 500;'>Email:</td>
                                <td style='padding: 8px 0; color: #111827; font-weight: 600;'>{$customer_email}</td>
                            </tr>
                            <tr>
                                <td style='padding: 8px 0; color: #6b7280; font-weight: 500;'>Teléfono:</td>
                                <td style='padding: 8px 0; color: #111827; font-weight: 600;'>{$customer_phone}</td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- Seguimiento -->
                    <div style='background: #eff6ff; border: 1px solid #3b82f6; border-radius: 8px; padding: 25px; margin: 30px 0; text-align: center;'>
                        <h3 style='margin: 0 0 15px 0; color: #1e40af; font-size: 18px; font-weight: 600;'>🔍 Seguimiento del Trámite</h3>
                        <p style='margin: 0 0 20px 0; color: #1e40af; font-size: 14px;'>Consulte el estado actualizado de su solicitud en cualquier momento:</p>
                        <a href='{$tracking_url}' style='display: inline-block; padding: 14px 28px; background: #016d86; color: white; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 16px;'>Ver Estado del Trámite</a>
                    </div>
                    
                    <!-- Próximos pasos -->
                    <div style='background: #fefce8; border: 1px solid #facc15; border-radius: 8px; padding: 25px; margin: 30px 0;'>
                        <h3 style='margin: 0 0 15px 0; color: #a16207; font-size: 18px; font-weight: 600;'>📋 Próximos Pasos</h3>
                        <ul style='margin: 0; padding-left: 20px; color: #a16207;'>
                            <li style='margin-bottom: 10px;'>Nuestro equipo revisará su solicitud en un plazo máximo de <strong>48 horas laborables</strong></li>
                            <li style='margin-bottom: 10px;'>Le contactaremos si necesitamos documentación adicional o aclaraciones</li>
                            <li style='margin-bottom: 10px;'>Una vez aprobada, procederemos con el registro ante las autoridades polacas</li>
                            <li>Recibirá confirmación una vez completado el proceso</li>
                        </ul>
                    </div>
                    
                    <p style='margin: 30px 0 0 0; font-size: 16px; color: #374151;'>Gracias por confiar en <strong>Tramitfy</strong> para la gestión de sus trámites marítimos.</p>
                    
                    <p style='margin: 20px 0 0 0; font-size: 14px; color: #6b7280;'>
                        <strong>Equipo Tramitfy</strong><br>
                        📧 info@tramitfy.es<br>
                        🌐 <a href='https://tramitfy.es' style='color: #016d86;'>tramitfy.es</a>
                    </p>
                </div>
                
                <!-- Footer -->
                <div style='background: #f9fafb; padding: 25px 30px; text-align: center; border-top: 1px solid #e5e7eb;'>
                    <p style='margin: 0; font-size: 12px; color: #9ca3af; line-height: 1.5;'>
                        <strong>Tramitfy</strong> - Especialistas en Trámites Marítimos<br>
                        Este email ha sido enviado en respuesta a su solicitud de registro bajo bandera polaca.<br>
                        Para cualquier consulta, responda a este email o visite nuestra web.
                    </p>
                </div>
            </div>
        </body>
        </html>";

        $headers_client = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: Tramitfy <info@tramitfy.es>',
            'Reply-To: info@tramitfy.es'
        );

        // Enviar email al cliente
        log_polaca_email("📧 PREPARANDO EMAIL AL CLIENTE");
        log_polaca_email("📧 Destinatario: {$customer_email}");
        log_polaca_email("📧 Asunto: {$subject_client}");
        log_polaca_email("📧 Headers: " . print_r($headers_client, true));
        
        log_polaca_email("📧 EJECUTANDO wp_mail() para cliente...");
        
        // Capturar errores de wp_mail
        global $wp_mail_errors;
        $wp_mail_errors = [];
        add_action('wp_mail_failed', function($wp_error) {
            global $wp_mail_errors;
            $wp_mail_errors[] = $wp_error->get_error_message();
        });
        
        $client_sent = wp_mail($customer_email, $subject_client, $message_client, $headers_client);
        log_polaca_email("📧 RESULTADO EMAIL CLIENTE: " . ($client_sent ? 'ÉXITO ✅' : 'FALLO ❌'));
        
        if (!$client_sent && !empty($wp_mail_errors)) {
            log_polaca_email("📧 ERRORES CLIENTE: " . implode(', ', $wp_mail_errors));
        }

        // Email al admin
        $subject_admin = "🇵🇱 Nuevo Registro Bandera Polaca - {$tramite_id}";
        $message_admin = "
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Nuevo Registro Bandera Polaca</title>
        </head>
        <body style='margin: 0; padding: 20px; font-family: \"Segoe UI\", Tahoma, Geneva, Verdana, sans-serif; background-color: #f8fafc; line-height: 1.6;'>
            <div style='max-width: 700px; margin: 0 auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);'>
                
                <!-- Header Admin -->
                <div style='background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%); padding: 30px; text-align: center;'>
                    <h1 style='color: white; margin: 0; font-size: 24px; font-weight: 600;'>🇵🇱 Nuevo Registro Bandera Polaca</h1>
                    <p style='color: #fecaca; margin: 8px 0 0 0; font-size: 16px;'>Notificación para Administración</p>
                </div>
                
                <!-- Content Admin -->
                <div style='padding: 30px;'>
                    <div style='background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 20px; margin-bottom: 25px;'>
                        <h3 style='margin: 0 0 15px 0; color: #dc2626; font-size: 18px; font-weight: 600;'>⚡ Acción Requerida</h3>
                        <p style='margin: 0; color: #7f1d1d; font-size: 16px;'>Nueva solicitud de registro bajo bandera polaca recibida y procesada. Revisar y gestionar el trámite.</p>
                    </div>
                    
                    <!-- Información del Trámite Admin -->
                    <div style='background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 8px; padding: 25px; margin: 25px 0;'>
                        <h3 style='margin: 0 0 20px 0; color: #374151; font-size: 18px; font-weight: 600;'>📋 Información del Trámite</h3>
                        <table style='width: 100%; border-collapse: collapse;'>
                            <tr>
                                <td style='padding: 8px 0; color: #6b7280; font-weight: 500; width: 30%;'>Número de trámite:</td>
                                <td style='padding: 8px 0; color: #111827; font-weight: 600;'>{$tramite_id}</td>
                            </tr>
                            <tr>
                                <td style='padding: 8px 0; color: #6b7280; font-weight: 500;'>Transfer ID:</td>
                                <td style='padding: 8px 0; color: #111827; font-weight: 600;'>{$transfer_id}</td>
                            </tr>
                            <tr>
                                <td style='padding: 8px 0; color: #6b7280; font-weight: 500;'>Estado:</td>
                                <td style='padding: 8px 0; color: #059669; font-weight: 600;'>✓ Pendiente de revisión</td>
                            </tr>
                            <tr>
                                <td style='padding: 8px 0; color: #6b7280; font-weight: 500;'>Fecha de solicitud:</td>
                                <td style='padding: 8px 0; color: #111827; font-weight: 600;'>" . date('d/m/Y H:i') . "</td>
                            </tr>
                            <tr>
                                <td style='padding: 8px 0; color: #6b7280; font-weight: 500;'>Tipo de trámite:</td>
                                <td style='padding: 8px 0; color: #111827; font-weight: 600;'>Registro bajo Bandera Polaca</td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- Datos del Cliente Admin -->
                    <div style='background: #fefefe; border: 1px solid #e5e7eb; border-radius: 8px; padding: 25px; margin: 25px 0;'>
                        <h3 style='margin: 0 0 20px 0; color: #374151; font-size: 18px; font-weight: 600;'>👤 Datos del Cliente</h3>
                        <table style='width: 100%; border-collapse: collapse;'>
                            <tr>
                                <td style='padding: 8px 0; color: #6b7280; font-weight: 500; width: 25%;'>Nombre completo:</td>
                                <td style='padding: 8px 0; color: #111827; font-weight: 600;'>{$customer_name}</td>
                            </tr>
                            <tr>
                                <td style='padding: 8px 0; color: #6b7280; font-weight: 500;'>DNI/NIE:</td>
                                <td style='padding: 8px 0; color: #111827; font-weight: 600;'>{$customer_dni}</td>
                            </tr>
                            <tr>
                                <td style='padding: 8px 0; color: #6b7280; font-weight: 500;'>Email de contacto:</td>
                                <td style='padding: 8px 0; color: #111827; font-weight: 600;'><a href='mailto:{$customer_email}' style='color: #016d86;'>{$customer_email}</a></td>
                            </tr>
                            <tr>
                                <td style='padding: 8px 0; color: #6b7280; font-weight: 500;'>Teléfono:</td>
                                <td style='padding: 8px 0; color: #111827; font-weight: 600;'><a href='tel:{$customer_phone}' style='color: #016d86;'>{$customer_phone}</a></td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- Acciones Admin -->
                    <div style='background: #eff6ff; border: 1px solid #3b82f6; border-radius: 8px; padding: 25px; margin: 25px 0;'>
                        <h3 style='margin: 0 0 20px 0; color: #1e40af; font-size: 18px; font-weight: 600;'>🔧 Acciones de Gestión</h3>
                        <div style='text-align: center;'>
                            <p style='margin: 0 0 20px 0; color: #1e40af; font-size: 14px;'>Accede a las herramientas de gestión del trámite:</p>
                            <div style='margin: 15px 0;'>
                                <a href='https://46-202-128-35.sslip.io/tramites/{$transfer_id}' style='display: inline-block; padding: 12px 24px; background: #dc2626; color: white; text-decoration: none; border-radius: 6px; font-weight: 600; margin: 5px;'>🔧 Gestionar en Dashboard</a>
                                <a href='{$tracking_url}' style='display: inline-block; padding: 12px 24px; background: #016d86; color: white; text-decoration: none; border-radius: 6px; font-weight: 600; margin: 5px;'>🔍 Ver Seguimiento Público</a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recordatorio Admin -->
                    <div style='background: #fefce8; border: 1px solid #facc15; border-radius: 8px; padding: 20px; margin: 25px 0;'>
                        <h3 style='margin: 0 0 10px 0; color: #a16207; font-size: 16px; font-weight: 600;'>⏰ Recordatorio</h3>
                        <p style='margin: 0; color: #a16207; font-size: 14px;'>
                            <strong>Tiempo de respuesta comprometido:</strong> 48 horas laborables<br>
                            Revisar documentación y contactar al cliente si faltan datos.
                        </p>
                    </div>
                </div>
                
                <!-- Footer Admin -->
                <div style='background: #f9fafb; padding: 20px; text-align: center; border-top: 1px solid #e5e7eb;'>
                    <p style='margin: 0; font-size: 12px; color: #6b7280;'>
                        <strong>Tramitfy Admin Panel</strong> - Email automático generado por el sistema<br>
                        Este trámite requiere revisión y seguimiento por parte del equipo de administración.
                    </p>
                </div>
            </div>
        </body>
        </html>";

        $headers_admin = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: Tramitfy <info@tramitfy.es>',
            'Reply-To: info@tramitfy.es'
        );

        // Enviar email al admin
        log_polaca_email("📧 PREPARANDO EMAIL AL ADMIN");
        log_polaca_email("📧 Destinatario: admin@ipmgroup24.com");
        log_polaca_email("📧 Asunto: {$subject_admin}");
        
        log_polaca_email("📧 EJECUTANDO wp_mail() para admin...");
        
        // Resetear errores para el admin
        $wp_mail_errors = [];
        
        $admin_sent = wp_mail('admin@ipmgroup24.com', $subject_admin, $message_admin, $headers_admin);
        log_polaca_email("📧 RESULTADO EMAIL ADMIN: " . ($admin_sent ? 'ÉXITO ✅' : 'FALLO ❌'));
        
        if (!$admin_sent && !empty($wp_mail_errors)) {
            log_polaca_email("📧 ERRORES ADMIN: " . implode(', ', $wp_mail_errors));
        }

        // Resultado final
        log_polaca_email("🏁 RESUMEN FINAL:");
        log_polaca_email("📧 Cliente ({$customer_email}): " . ($client_sent ? 'ENVIADO ✅' : 'FALLO ❌'));
        log_polaca_email("📧 Admin (admin@ipmgroup24.com): " . ($admin_sent ? 'ENVIADO ✅' : 'FALLO ❌'));

        if ($client_sent && $admin_sent) {
            log_polaca_email("🎉 PROCESO COMPLETADO - TODOS LOS EMAILS ENVIADOS");
            wp_send_json_success('Emails enviados correctamente');
        } else {
            log_polaca_email("⚠️ PROCESO CON ERRORES - Revisar logs SMTP arriba");
            $error_details = "Error enviando emails: ";
            if (!$client_sent) {
                $error_details .= "Cliente FALLO";
                if (!empty($wp_mail_errors)) {
                    $error_details .= " (" . implode(', ', $wp_mail_errors) . ")";
                }
                $error_details .= ", ";
            }
            if (!$admin_sent) {
                $error_details .= "Admin FALLO";
                if (!empty($wp_mail_errors)) {
                    $error_details .= " (" . implode(', ', $wp_mail_errors) . ")";
                }
                $error_details .= ", ";
            }
            $error_details = rtrim($error_details, ', ');
            wp_send_json_error($error_details);
        }

    } catch (Exception $e) {
        log_polaca_email("💥 EXCEPCIÓN CAPTURADA: " . $e->getMessage());
        log_polaca_email("📍 ARCHIVO: " . $e->getFile() . " LÍNEA: " . $e->getLine());
        log_polaca_email("📚 STACK TRACE: " . $e->getTraceAsString());
        wp_send_json_error('Error interno: ' . $e->getMessage());
    }
}