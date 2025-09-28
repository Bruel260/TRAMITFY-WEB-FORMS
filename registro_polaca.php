<?php
/**
 * Formulario de Registro de Bandera Polaca
 * Para WordPress - Shortcode: [polish_registration_form]
 */

// Asegurarse de que el archivo no sea accedido directamente
defined('ABSPATH') || exit;

// Configuración de Stripe
define('STRIPE_MODE', 'test'); // test o live

define('STRIPE_TEST_PUBLIC_KEY', 'pk_test_REPLACE_WITH_YOUR_TEST_PUBLIC_KEY');
define('STRIPE_TEST_SECRET_KEY', 'sk_test_REPLACE_WITH_YOUR_TEST_SECRET_KEY');

define('STRIPE_LIVE_PUBLIC_KEY', 'pk_live_REPLACE_WITH_YOUR_LIVE_PUBLIC_KEY');
define('STRIPE_LIVE_SECRET_KEY', 'sk_live_REPLACE_WITH_YOUR_LIVE_SECRET_KEY');

if (STRIPE_MODE === 'test') {
    $stripe_public_key = STRIPE_TEST_PUBLIC_KEY;
    $stripe_secret_key = STRIPE_TEST_SECRET_KEY;
} else {
    $stripe_public_key = STRIPE_LIVE_PUBLIC_KEY;
    $stripe_secret_key = STRIPE_LIVE_SECRET_KEY;
}

// Configuración del webhook TRAMITFY
define('TRAMITFY_API_URL', 'https://46-202-128-35.sslip.io/api/herramientas/polaca/webhook');

// Cargar Stripe library
require_once(__DIR__ . '/vendor/autoload.php');

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
    $primary_color = array(1, 109, 134); // var(--primary-color)
    $secondary_color = array(40, 167, 69); // var(--secondary-color)
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
    $pdf->Ln(2);
    
    // Crear tabla
    $pdf->SetFillColor(240, 240, 240);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(100, 8, utf8_decode('Descripción'), 1, 0, 'L', true);
    $pdf->Cell(40, 8, 'Cantidad', 1, 0, 'C', true);
    $pdf->Cell(50, 8, 'Precio', 1, 1, 'R', true);
    
    // Tipo de servicio
    $service_description = get_polish_tramite_description($tramite_type);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(100, 8, utf8_decode($service_description), 1, 0, 'L');
    $pdf->Cell(40, 8, '1', 1, 0, 'C');
    $pdf->Cell(50, 8, number_format($base_price, 2) . ' EUR', 1, 1, 'R');
    
    // Mostrar costos adicionales si existen
    if ($additional_costs > 0) {
        $pdf->Cell(100, 8, utf8_decode('Opciones adicionales'), 1, 0, 'L');
        $pdf->Cell(40, 8, '1', 1, 0, 'C');
        $pdf->Cell(50, 8, number_format($additional_costs, 2) . ' EUR', 1, 1, 'R');
    }
    
    // Subtotal antes del descuento
    $subtotal_before_discount = $base_price + $additional_costs;
    
    // Si hay descuento, mostrar línea de descuento
    if ($discount_percent > 0) {
        $pdf->SetTextColor(1, 109, 134); // Color TRAMITFY para el descuento var(--primary-color)
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
    $invoice_pdf_name = 'factura_polaca_' . time() . '.pdf';
    $invoice_pdf_path = $upload_dir['path'] . '/' . $invoice_pdf_name;
    $pdf->Output('F', $invoice_pdf_path);
    
    return $invoice_pdf_path;
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
 * Función principal para generar y mostrar el formulario en el frontend
 */
function polish_registration_form_shortcode() {
    // Encolar los scripts y estilos necesarios
    wp_enqueue_style('polish-registration-form-style', get_template_directory_uri() . '/style.css', array(), filemtime(get_template_directory() . '/style.css'));
    wp_enqueue_script('stripe', 'https://js.stripe.com/v3/', array(), null, false);
    wp_enqueue_script('signature-pad', 'https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js', array(), null, false);

    // Iniciar el buffering de salida
    ob_start();
    ?>

    <!-- Estilos personalizados para el formulario -->
    <style>
        :root {
            --primary-color: #016d86;
            --secondary-color: #02F9D2;
        }
        
        /* Estilos generales para el formulario */
        #polish-registration-form {
            max-width: 1000px;
            margin: 40px auto;
            padding: 30px;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            background-color: #ffffff;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        /* Estilos para la portada */
        .portada-section {
            text-align: center;
            padding: 25px 20px;
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border-radius: 10px;
            border: 2px solid #d1ecf1;
            margin-bottom: 20px;
        }

        .portada-section h1 {
            color: var(--primary-color);
            font-size: 2.2em;
            margin-bottom: 15px;
            font-weight: bold;
        }

        .portada-section p {
            font-size: 1.1em;
            color: #666;
            margin-bottom: 25px;
            line-height: 1.5;
        }

        .tramites-grid {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-top: 20px;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
        }

        .tramite-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border: 2px solid #d1ecf1;
            border-radius: 8px;
            padding: 15px 20px;
            display: flex;
            align-items: center;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .tramite-card:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 15px rgba(1, 109, 134, 0.2);
            border-color: var(--primary-color);
        }

        .tramite-card:focus {
            outline: 2px solid var(--primary-color);
            outline-offset: 2px;
        }

        .tramite-card.selected {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-color) 100%);
            color: white;
            border-color: var(--primary-color);
        }

        .tramite-icon {
            font-size: 2.2em;
            margin-right: 20px;
            color: var(--primary-color);
            flex-shrink: 0;
            width: 60px;
            text-align: center;
        }

        .tramite-card.selected .tramite-icon {
            color: white;
        }

        .tramite-content {
            flex-grow: 1;
            text-align: left;
        }

        .tramite-title {
            font-size: 1.2em;
            font-weight: bold;
            margin-bottom: 5px;
            color: var(--primary-color);
        }

        .tramite-card.selected .tramite-title {
            color: white;
        }

        .tramite-description {
            font-size: 0.9em;
            line-height: 1.4;
            color: #666;
            margin-bottom: 8px;
        }

        .tramite-card.selected .tramite-description {
            color: #f8f9fa;
        }

        .tramite-price {
            font-size: 1.5em;
            font-weight: bold;
            color: var(--primary-color);
            margin-left: 20px;
            flex-shrink: 0;
            min-width: 100px;
            text-align: right;
        }

        .tramite-card.selected .tramite-price {
            color: white;
        }

        .option-group {
            margin-top: 20px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }

        .option-group h4 {
            margin-top: 0;
            margin-bottom: 15px;
            color: var(--primary-color);
            font-weight: bold;
        }

        .option-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 10px;
        }

        .option-card {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 6px;
            padding: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }

        .option-card:hover {
            border-color: var(--primary-color);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(1, 109, 134, 0.1);
        }

        .option-card label {
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
            margin: 0;
            font-weight: normal;
        }

        .option-card input[type="radio"],
        .option-card input[type="checkbox"] {
            margin-right: 10px;
            transform: scale(1.2);
        }

        .option-card input[type="radio"]:checked,
        .option-card input[type="checkbox"]:checked {
            accent-color: var(--primary-color);
        }

        .option-card:has(input:checked) {
            border-color: var(--primary-color);
            background-color: #ffffff;
        }

        .option-label {
            flex-grow: 1;
            margin-right: 10px;
        }

        .option-price {
            font-weight: bold;
            color: var(--secondary-color);
            font-size: 0.9em;
        }

        .option-price:contains('+') {
            color: #dc3545;
        }


        @media (max-width: 768px) {
            .option-cards {
                grid-template-columns: 1fr;
            }
            
            .option-card {
                padding: 10px;
            }
            
        }

        .continue-button {
            background-color: var(--secondary-color);
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 6px;
            font-size: 1.1em;
            font-weight: bold;
            cursor: pointer;
            margin-top: 20px;
            display: none;
            transition: background-color 0.3s ease;
        }

        .continue-button:hover {
            background-color: var(--secondary-color);
        }

        .continue-button.show {
            display: inline-block;
        }

        .back-to-home-button {
            background-color: #6c757d;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            font-size: 0.9em;
            cursor: pointer;
            transition: background-color 0.3s ease;
            margin-right: 20px;
        }

        .back-to-home-button:hover {
            background-color: #5a6268;
        }

        .polish-flag {
            display: inline-block;
            margin-right: 10px;
        }

        /* Estilos para grupos progresivos */
        .progressive-group {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }

        .progressive-group.completed {
            background: #f8f9fa;
            border-color: #c3e6cb;
        }

        .progressive-group.collapsed .group-content {
            display: none;
        }

        .group-header {
            padding: 15px 20px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(1, 109, 134, 0.05);
            border-radius: 8px 8px 0 0;
            transition: background-color 0.3s ease;
        }

        .group-header:hover {
            background: rgba(1, 109, 134, 0.1);
        }

        .progressive-group.completed .group-header {
            background: rgba(40, 167, 69, 0.1);
        }

        .group-header h3 {
            margin: 0;
            color: var(--primary-color);
            font-size: 1.1em;
            display: flex;
            align-items: center;
        }

        .progressive-group.completed .group-header h3 {
            color: var(--secondary-color);
        }

        .group-icon {
            margin-right: 10px;
            font-size: 1.2em;
        }

        .group-status {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background-color: #e9ecef;
            border: 2px solid #dee2e6;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0;
            transition: all 0.3s ease;
        }

        .progressive-group.completed .group-status {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }

        .progressive-group.in-progress .group-status {
            background-color: #fff3cd;
            border-color: #ffeaa7;
            animation: pulse 1.5s ease-in-out infinite;
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.6; }
            100% { opacity: 1; }
        }

        .progressive-group.collapsed .group-status {
            transform: rotate(180deg);
        }

        .group-content {
            padding: 20px;
            border-top: 1px solid #dee2e6;
        }

        .progressive-group.completed .group-content {
            background: rgba(40, 167, 69, 0.05);
        }

        /* Estilos para campos con errores */
        .field-error {
            border: 2px solid #dc3545 !important;
            background-color: #f8d7da !important;
        }

        /* Estilos para documentos opcionales */
        .upload-item label span {
            font-weight: normal;
        }

        /* Animaciones para transiciones suaves */
        .progressive-group {
            opacity: 1;
            transform: translateY(0);
            transition: all 0.5s ease;
        }

        .progressive-group[style*="display: none"] {
            opacity: 0;
            transform: translateY(-20px);
        }

        /* Mejorar visualización del signature pad */
        #signature-container {
            border: 2px dashed #ccc;
            border-radius: 8px;
            padding: 10px;
            background: #fafafa;
        }

        #signature-pad {
            border: 1px solid #ddd;
            border-radius: 4px;
            background: white;
        }

        /* Estilos para indicadores de estado */
        .group-status {
            min-width: 30px;
            text-align: center;
        }

        /* Hover effects mejorados */
        .group-header:hover {
            transform: translateX(2px);
        }

        /* Resto de estilos heredados de baja.php */
        #polish-registration-form label {
            font-weight: normal;
            display: block;
            margin-top: 15px;
            margin-bottom: 5px;
            color: #666;
        }

        #polish-registration-form input[type="text"],
        #polish-registration-form input[type="tel"],
        #polish-registration-form input[type="email"],
        #polish-registration-form input[type="file"],
        #polish-registration-form select,
        #polish-registration-form textarea {
            width: 100%;
            padding: 12px;
            margin-top: 0px;
            border-radius: 5px;
            border: 1px solid #cccccc;
            font-size: 16px;
            background-color: #f9f9f9;
            box-sizing: border-box;
        }

        #polish-registration-form input[type="text"]:focus,
        #polish-registration-form input[type="tel"]:focus,
        #polish-registration-form input[type="email"]:focus,
        #polish-registration-form input[type="file"]:focus,
        #polish-registration-form select:focus,
        #polish-registration-form textarea:focus {
            outline: 2px solid var(--primary-color);
            outline-offset: 2px;
            border-color: var(--primary-color);
        }

        #polish-registration-form textarea {
            min-height: 100px;
            resize: vertical;
        }

        #polish-registration-form .button {
            background-color: var(--secondary-color);
            color: #ffffff;
            padding: 12px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 18px;
            transition: background-color 0.3s ease;
            margin-top: 20px;
        }

        #polish-registration-form .button:hover {
            background-color: var(--secondary-color);
        }

        #polish-registration-form .button:focus {
            outline: 2px solid var(--primary-color);
            outline-offset: 2px;
        }

        #polish-registration-form .hidden {
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
            color: var(--primary-color);
            text-decoration: none;
            font-weight: bold;
            position: relative;
            padding: 8px 15px;
            transition: color 0.3s ease;
        }

        #form-navigation a.active {
            color: var(--primary-color);
            text-decoration: underline;
        }

        #form-navigation a:focus {
            outline: 2px solid var(--primary-color);
            outline-offset: 2px;
        }

        #form-navigation a:not(:last-child)::after {
            content: '→';
            position: absolute;
            right: -20px;
            font-size: 16px;
            color: var(--primary-color);
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
            color: #666;
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
            background-color: var(--primary-color);
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
            background-color: #b02a37;
        }

        /* Mensajes de éxito y error */
        #payment-message {
            margin-top: 15px;
            font-size: 16px;
            text-align: center;
        }

        #payment-message.success {
            color: var(--secondary-color);
        }

        #payment-message.error {
            color: var(--primary-color);
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
            border-top: 8px solid var(--primary-color);
            border-radius: 50%;
            width: 70px;
            height: 70px;
            animation: spin 1.5s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
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
            color: #333;
        }

        .price-details ul {
            list-style-type: none;
            padding: 0;
            margin: 15px 0;
        }

        .price-details ul li {
            margin-bottom: 8px;
            color: #666;
        }

        .error-message {
            color: var(--primary-color);
            margin-bottom: 20px;
            font-size: 16px;
            font-weight: bold;
        }

        .field-error {
            border-color: var(--primary-color) !important;
        }


        /* Responsividad */
        @media (max-width: 768px) {
            .tramites-grid {
                grid-template-columns: 1fr;
            }
            
            #form-navigation {
                flex-direction: column;
                align-items: flex-start;
            }

            .button-container {
                flex-direction: column;
                align-items: stretch;
            }

            .upload-item {
                flex-direction: column;
                align-items: flex-start;
            }
        }

        @media (max-width: 480px) {
            #polish-registration-form {
                padding: 20px;
            }

            .portada-section h1 {
                font-size: 1.8em;
            }

            .tramite-card {
                padding: 12px 15px;
                flex-direction: column;
                text-align: center;
            }

            .tramite-icon {
                margin-right: 0;
                margin-bottom: 10px;
                width: auto;
            }

            .tramite-content {
                text-align: center;
                margin-bottom: 10px;
            }

            .tramite-price {
                margin-left: 0;
                text-align: center;
                min-width: auto;
            }
        }

        /* Submit button styling */
        #submit {
            background-color: var(--primary-color);
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
            background-color: var(--primary-color);
        }

        #submit:focus {
            outline: 2px solid var(--secondary-color);
            outline-offset: 2px;
        }

        /* Sistema moderno de indicadores de campo */
        .field-container {
            position: relative;
            margin-bottom: 20px;
        }

        .field-indicator {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
            opacity: 0;
            transition: all 0.3s ease;
            z-index: 10;
        }

        .field-indicator.show {
            opacity: 1;
        }

        .field-indicator.error {
            background-color: #dc3545;
            color: white;
        }

        .field-indicator.warning {
            background-color: #ffc107;
            color: #333;
        }

        .field-indicator.success {
            background-color: var(--secondary-color);
            color: white;
        }

        /* Estilos para campos con estados */
        .field-error {
            border: 2px solid #dc3545 !important;
            background-color: #fff5f5 !important;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25) !important;
        }

        .field-warning {
            border: 2px solid #ffc107 !important;
            background-color: #fffbf0 !important;
            box-shadow: 0 0 0 0.2rem rgba(255, 193, 7, 0.25) !important;
        }

        .field-success {
            border: 2px solid var(--secondary-color) !important;
            background-color: #f0fff4 !important;
            box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25) !important;
        }

        /* Tooltip moderno para errores */
        .field-tooltip {
            position: absolute;
            bottom: -35px;
            left: 0;
            right: 0;
            background: #dc3545;
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 13px;
            opacity: 0;
            transform: translateY(-10px);
            transition: all 0.3s ease;
            z-index: 100;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .field-tooltip.warning {
            background: #ffc107;
            color: #333;
        }

        .field-tooltip.show {
            opacity: 1;
            transform: translateY(0);
        }

        .field-tooltip::before {
            content: '';
            position: absolute;
            top: -6px;
            left: 20px;
            width: 0;
            height: 0;
            border-left: 6px solid transparent;
            border-right: 6px solid transparent;
            border-bottom: 6px solid #dc3545;
        }

        .field-tooltip.warning::before {
            border-bottom-color: #ffc107;
        }

        /* Animación de shake para campos con error */
        @keyframes fieldShake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-3px); }
            20%, 40%, 60%, 80% { transform: translateX(3px); }
        }

        .field-shake {
            animation: fieldShake 0.5s ease-in-out;
        }
        
        @keyframes slideInFromTop {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes slideOutToTop {
            from {
                opacity: 1;
                transform: translateY(0);
            }
            to {
                opacity: 0;
                transform: translateY(-20px);
            }
        }

        /* Ajustes específicos para diferentes tipos de campo */
        .field-container select {
            padding-right: 45px;
        }

        .field-container input[type="file"] + .field-indicator {
            top: 40%;
        }

        .field-container textarea + .field-indicator {
            top: 20px;
            transform: none;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .field-tooltip {
                font-size: 12px;
                padding: 6px 10px;
            }
        }

        /* Estilos para la página de pago profesional */
        .payment-header {
            text-align: center;
            margin-bottom: 30px;
            padding: 20px 0;
        }

        .payment-header h2 {
            color: var(--primary-color);
            font-size: 2.2em;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .payment-subtitle {
            color: #666;
            font-size: 1.1em;
            margin: 0;
            font-weight: 400;
        }

        .order-summary {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid #e8ecef;
            margin-bottom: 30px;
            overflow: hidden;
        }

        .summary-header {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            padding: 20px 25px;
            border-bottom: 1px solid #e8ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .summary-header h3 {
            margin: 0;
            color: var(--primary-color);
            font-size: 1.3em;
            font-weight: 600;
        }

        .order-number {
            font-size: 0.9em;
            color: #666;
            font-weight: 500;
        }

        .order-items {
            padding: 25px;
        }

        .order-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .order-item:last-child {
            border-bottom: none;
        }

        .item-details h4 {
            margin: 0 0 5px 0;
            color: #333;
            font-size: 1.1em;
            font-weight: 500;
        }

        .item-details p {
            margin: 0;
            color: #666;
            font-size: 0.9em;
        }

        .item-price {
            font-weight: 600;
            font-size: 1.1em;
            color: var(--primary-color);
        }


        .order-total {
            background: #f8f9fa;
            padding: 20px 25px;
            border-top: 1px solid #e8ecef;
        }

        .total-line {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            font-size: 1em;
        }

        .total-line.final {
            border-top: 2px solid #e8ecef;
            padding-top: 15px;
            margin-top: 10px;
            font-size: 1.2em;
            font-weight: 700;
            color: var(--primary-color);
        }


        .payment-actions {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid #e8ecef;
            padding: 25px;
            text-align: center;
        }

        .custom-checkbox {
            display: inline-flex !important;
            align-items: center !important;
            cursor: pointer;
            line-height: 1.5;
            padding: 15px;
            gap: 12px;
            position: relative;
            z-index: 1;
        }
        
        .terms-container {
            display: flex;
            justify-content: center;
            width: 100%;
            margin-bottom: 20px;
        }

        .custom-checkbox input[type="checkbox"] {
            display: none !important;
            opacity: 0 !important;
            position: absolute !important;
            left: -9999px !important;
            width: 0 !important;
            height: 0 !important;
        }

        .checkmark {
            width: 22px;
            height: 22px;
            border: 2px solid #ddd;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            flex-shrink: 0;
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            cursor: pointer;
            pointer-events: auto;
        }

        .custom-checkbox input[type="checkbox"]:checked + .checkmark {
            background: var(--primary-color);
            border-color: var(--primary-color);
        }

        .custom-checkbox input[type="checkbox"]:checked + .checkmark::after {
            content: '✓';
            color: white;
            font-size: 12px;
            font-weight: bold;
        }

        .terms-text {
            font-size: 0.95em;
            color: #555;
            line-height: 1.4;
            margin: 0;
            text-align: left;
        }

        .terms-text a {
            color: var(--primary-color);
            text-decoration: none;
        }

        .terms-text a:hover {
            text-decoration: underline;
        }

        .proceed-payment-btn {
            width: 100%;
            padding: 18px 28px;
            background: linear-gradient(135deg, var(--primary-color) 0%, #024d5e 100%);
            color: white;
            border: none;
            border-radius: 16px;
            font-size: 1.2em;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 0 8px 25px rgba(1, 109, 134, 0.4), 0 0 0 1px rgba(255,255,255,0.1) inset;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            position: relative;
            overflow: hidden;
        }

        .proceed-payment-btn:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 12px 35px rgba(1, 109, 134, 0.6), 0 0 0 1px rgba(255,255,255,0.2) inset;
            background: linear-gradient(135deg, #027d99 0%, #013b47 100%);
        }

        .proceed-payment-btn:active {
            transform: translateY(-1px) scale(1.01);
            transition: all 0.15s ease;
        }

        .proceed-payment-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .proceed-payment-btn:hover::before {
            left: 100%;
        }

        .btn-icon {
            font-size: 1.3em;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3));
        }

        .btn-amount {
            background: rgba(255,255,255,0.15);
            padding: 8px 16px;
            border-radius: 12px;
            font-weight: 800;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.1);
        }

        .security-info {
            text-align: center;
            margin-top: 20px;
        }

        .security-badges {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-bottom: 10px;
            flex-wrap: wrap;
        }

        .badge {
            background: #f8f9fa;
            border: 1px solid #e8ecef;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            color: #555;
        }

        .security-text {
            font-size: 0.85em;
            color: #666;
            margin: 0;
        }

        /* Estilos para el modal de pago */
        .payment-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 10000;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 110px 20px 20px 20px;
        }

        .modal-backdrop {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.75);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }

        .modal-container {
            background: white;
            border-radius: 24px;
            box-shadow: 0 30px 80px rgba(0,0,0,0.5);
            max-width: 540px;
            width: 100%;
            max-height: 85vh;
            min-height: 500px;
            position: relative;
            z-index: 1;
            overflow: hidden;
            animation: modalSlideIn 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            border: 1px solid rgba(255, 255, 255, 0.3);
            backdrop-filter: blur(20px);
            display: flex;
            flex-direction: column;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .modal-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, #024d5e 100%);
            color: white;
            padding: 20px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 1.3em;
            font-weight: 600;
        }

        .modal-close {
            background: none;
            border: none;
            color: white;
            font-size: 1.4em;
            cursor: pointer;
            padding: 8px;
            border-radius: 50%;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .modal-close:hover {
            background: rgba(255,255,255,0.25);
            transform: scale(1.15);
        }

        .modal-body {
            padding: 25px;
            flex: 1;
            overflow-y: auto;
            min-height: 0;
        }

        .payment-summary {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .summary-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .item-name {
            font-weight: 500;
            color: #333;
        }

        .item-amount {
            font-weight: 700;
            font-size: 1.1em;
            color: var(--primary-color);
        }

        .payment-form-container {
            margin: 20px 0;
            min-height: 120px;
        }

        #payment-element {
            min-height: 80px;
            padding: 15px;
            border: 1px solid #e8ecef;
            border-radius: 12px;
            background: #fafbfc;
        }

        .payment-message {
            margin-top: 15px;
            padding: 12px;
            border-radius: 8px;
            font-size: 0.9em;
            text-align: center;
        }

        .payment-message.success {
            background: #f8f9fa;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .payment-message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .security-footer {
            text-align: center;
            margin-top: 20px;
        }

        .security-footer p {
            margin: 0;
            font-size: 0.9em;
            color: #666;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .security-icon {
            font-size: 1.1em;
        }

        .modal-footer {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 25px;
            display: flex !important;
            border-top: 1px solid #e8ecef;
            border-radius: 0 0 24px 24px;
            backdrop-filter: blur(10px);
            justify-content: center;
            align-items: center;
            flex-shrink: 0;
            min-height: 90px;
        }


        .pay-now-btn {
            width: 100%;
            max-width: none;
            padding: 18px 24px;
            background: linear-gradient(135deg, var(--primary-color) 0%, #024d5e 100%);
            color: white;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 700;
            display: flex !important;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 0 6px 20px rgba(1, 109, 134, 0.3);
            text-transform: uppercase;
            letter-spacing: 0.3px;
            font-size: 16px;
            position: relative;
            overflow: hidden;
            min-height: 58px;
            margin: 0;
        }

        .pay-now-btn:hover {
            transform: translateY(-2px) scale(1.02);
            box-shadow: 0 10px 30px rgba(1, 109, 134, 0.5);
            background: linear-gradient(135deg, #027d99 0%, #013b47 100%);
        }

        .pay-now-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .pay-now-btn:hover::before {
            left: 100%;
        }

        .pay-now-btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        .btn-loader {
            width: 16px;
            height: 16px;
        }

        .spinner-small {
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top: 2px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        /* Responsive para desktop */
        @media (min-width: 1024px) {
            .modal-container {
                max-width: 600px;
                min-height: 600px;
            }
            
            .modal-body {
                padding: 30px;
            }
            
            .payment-summary {
                padding: 20px;
                margin-bottom: 25px;
            }
            
            .payment-form-container {
                margin: 25px 0;
                min-height: 140px;
            }
            
            #payment-element {
                min-height: 100px;
                padding: 20px;
            }
            
            .modal-footer {
                padding: 30px;
                min-height: 100px;
            }
            
            .pay-now-btn {
                padding: 20px 30px;
                min-height: 60px;
                font-size: 17px;
            }
        }

        /* Responsive para móvil */
        @media (max-width: 768px) {
            .payment-modal {
                padding: 8px;
                align-items: flex-start;
                padding-top: 15px;
            }

            .modal-container {
                max-height: 95vh;
                max-width: 95vw;
                margin: 0;
                min-height: auto;
            }
            
            .modal-body {
                padding: 20px;
            }
            
            .payment-form-container {
                min-height: 100px;
            }
            
            #payment-element {
                min-height: 60px;
                padding: 12px;
            }

            .modal-header {
                padding: 15px 18px;
            }

            .modal-header h3 {
                font-size: 1.1em;
            }

            .modal-close {
                width: 32px;
                height: 32px;
                font-size: 1.2em;
            }

            .modal-body {
                padding: 15px 18px;
            }

            .modal-footer {
                padding: 12px 18px;
                flex-direction: column;
                gap: 10px;
            }

            .cancel-btn, .pay-now-btn {
                flex: none;
                padding: 14px 16px;
                font-size: 14px;
            }

            .security-badges {
                gap: 6px;
                flex-wrap: wrap;
                justify-content: center;
            }

            .badge {
                font-size: 0.75em;
                padding: 3px 6px;
                min-width: auto;
            }

            .payment-summary {
                font-size: 14px;
                padding: 12px;
            }

            .summary-item {
                padding: 6px 0;
            }

            #payment-element {
                font-size: 16px;
            }

            .custom-checkbox {
                margin-bottom: 12px;
            }

            .checkmark {
                width: 20px;
                height: 20px;
            }

            .proceed-payment-btn {
                flex-direction: column;
                gap: 8px;
                padding: 16px;
            }
        }
    </style>

    <!-- Formulario principal -->
    <form id="polish-registration-form" action="" method="POST" enctype="multipart/form-data">

        <?php if (current_user_can('administrator')): ?>
        <!-- PANEL DE AUTO-RELLENADO (Solo para administradores) -->
        <div class="admin-autofill-panel" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: 3px solid #5a67d8; padding: 20px; border-radius: 12px; margin-bottom: 30px; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                <span style="font-size: 28px;">⚡</span>
                <div>
                    <div style="color: white; font-weight: bold; font-size: 18px;">Panel de Administrador</div>
                    <div style="color: rgba(255,255,255,0.9); font-size: 13px;">Auto-rellena el formulario para pruebas rápidas</div>
                </div>
            </div>
            <button type="button" id="admin-autofill-btn" style="width: 100%; padding: 14px 24px; background: white; color: #667eea; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; font-size: 15px; transition: all 0.3s; box-shadow: 0 2px 8px rgba(0,0,0,0.15);" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.2)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 8px rgba(0,0,0,0.15)';">
                🚀 Auto-rellenar Formulario (Modo TEST)
            </button>
        </div>
        <?php endif; ?>

        <!-- Overlay de carga -->
        <div id="loading-overlay">
            <div class="spinner"></div>
            <p>Procesando, por favor espera...</p>
        </div>

        <!-- Portada - Selección de trámite -->
        <div id="page-portada" class="form-page portada-section">
            <div style="font-weight: bold; font-size: 2em; margin: 0.67em 0; color: var(--primary-color);">Registro bajo Bandera Polaca</div>
            <p>Selecciona el trámite que necesitas realizar para tu embarcación:</p>
            
            <div class="tramites-grid">
                <div class="tramite-card" data-tramite="registro">
                    <div class="tramite-icon"></div>
                    <div class="tramite-content">
                        <div class="tramite-title">Registro Completo</div>
                        <div class="tramite-description">
                            Registro completo de tu embarcación bajo bandera polaca. Incluye documentación oficial para embarcaciones hasta 24 metros.
                        </div>
                    </div>
                    <div class="tramite-price">429,99 €</div>
                </div>

                <div class="tramite-card" data-tramite="cambio_titularidad">
                    <div class="tramite-icon"></div>
                    <div class="tramite-content">
                        <div class="tramite-title">Cambio de Titularidad</div>
                        <div class="tramite-description">
                            Tramitación del cambio de propietario de embarcación ya registrada bajo bandera polaca. Gestión completa de documentación.
                        </div>
                    </div>
                    <div class="tramite-price">429,99 €</div>
                </div>

                <div class="tramite-card" data-tramite="mmsi">
                    <div class="tramite-icon"></div>
                    <div class="tramite-content">
                        <div class="tramite-title">Número MMSI Polaco</div>
                        <div class="tramite-description">
                            Solicitud del número MMSI polaco para equipos de radio VHF/DSC. Identificación única para comunicaciones marítimas.
                        </div>
                    </div>
                    <div class="tramite-price">190,00 €</div>
                </div>
            </div>

            <button type="button" class="continue-button" id="continue-from-portada">Continuar con el trámite seleccionado</button>
        </div>

        <!-- Navegación del formulario (oculta en portada) -->
        <div id="form-navigation" class="hidden">
            <button type="button" class="back-to-home-button" id="back-to-home">← Volver a Portada</button>
            <a href="#" class="nav-link" data-page-id="page-personal-info">Datos</a>
            <a href="#" class="nav-link" data-page-id="page-documents">Documentación</a>
            <a href="#" class="nav-link" data-page-id="page-payment">Pago</a>
        </div>

        <!-- Campo oculto para almacenar el trámite seleccionado -->
        <input type="hidden" id="selected_tramite" name="selected_tramite" value="" />

        <!-- Página de Datos Personales -->
        <div id="page-personal-info" class="form-page hidden">
            <div id="tramite-selected-title" style="color: var(--primary-color); text-align: center; margin-bottom: 30px; font-weight: bold; font-size: 1.5em;"></div>
            
            <!-- Grupo 1: Datos Básicos -->
            <div class="progressive-group" id="group-basic-data" data-group="basic" style="display: none;">
                <div class="group-header" onclick="toggleGroup('basic')">
                    <h3>Datos Básicos</h3>
                    <span class="group-status"></span>
                </div>
                <div class="group-content">
                    <label for="customer_name">Nombre y Apellidos:</label>
                    <input type="text" id="customer_name" name="customer_name" placeholder="Ingresa tu nombre y apellidos" required />

                    <label for="customer_dni">DNI o Pasaporte:</label>
                    <input type="text" id="customer_dni" name="customer_dni" placeholder="Ingresa tu DNI o pasaporte" required />

                    <label for="customer_email">Correo Electrónico:</label>
                    <input type="email" id="customer_email" name="customer_email" placeholder="Ingresa tu correo electrónico" required />

                    <label for="customer_phone">Teléfono:</label>
                    <input type="tel" id="customer_phone" name="customer_phone" placeholder="Ingresa tu teléfono" required />

                    <!-- Puerto de amarre (solo para registro) -->
                    <div id="boat-port-field" style="display: none;">
                        <label for="boat_port">Puerto de matrícula:</label>
                        <select id="boat_port" name="boat_port" required>
                            <option value="">Selecciona un puerto</option>
                            <option value="Gdansk">Gdansk</option>
                            <option value="Gdynia">Gdynia</option>
                            <option value="Szczecin">Szczecin</option>
                            <option value="Swinoujscie">Świnoujście</option>
                        </select>
                    </div>

                    <!-- Certificado radiooperador (solo para MMSI) -->
                    <div id="radio-cert-field" style="display: none;">
                        <label for="radio_operator_cert">Número de certificado de radiooperador (opcional):</label>
                        <input type="text" id="radio_operator_cert" name="radio_operator_cert" placeholder="Si posee certificado" />
                    </div>
                </div>
            </div>


            <!-- Grupo 3: Opciones del trámite -->
            <div class="progressive-group" id="group-tramite-options" data-group="options" style="display: none;">
                <div class="group-header" onclick="toggleGroup('options')">
                    <h3>Opciones del trámite</h3>
                    <span class="group-status"></span>
                </div>
                <div class="group-content">
                    <!-- Tamaños de embarcación (solo registro) -->
                    <div id="boat-sizes-section" style="display: none;">
                        <h4>Tamaño de embarcación</h4>
                        <div class="option-cards">
                            <div class="option-card">
                                <label>
                                    <input type="radio" name="boat_size" value="size_0_7" data-price="0" />
                                    <span class="option-label">0 a 7 metros</span>
                                    <span class="option-price">Incluido</span>
                                </label>
                            </div>
                            <div class="option-card">
                                <label>
                                    <input type="radio" name="boat_size" value="size_7_12" data-price="50" />
                                    <span class="option-label">7.1 a 12 metros</span>
                                    <span class="option-price">+50€</span>
                                </label>
                            </div>
                            <div class="option-card">
                                <label>
                                    <input type="radio" name="boat_size" value="size_12_24" data-price="100" />
                                    <span class="option-label">12.1 a 24 metros</span>
                                    <span class="option-price">+100€</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Opciones de entrega (solo registro) -->
                    <div id="delivery-options-section" style="display: none;">
                        <h4>Opciones de entrega</h4>
                        <div class="option-cards">
                            <div class="option-card">
                                <label>
                                    <input type="radio" name="delivery_option" value="standard" data-price="0" />
                                    <span class="option-label">Estándar 10-15 días</span>
                                    <span class="option-price">Incluido</span>
                                </label>
                            </div>
                            <div class="option-card">
                                <label>
                                    <input type="radio" name="delivery_option" value="express" data-price="180" />
                                    <span class="option-label">Express 1-3 días laborables</span>
                                    <span class="option-price">+180€</span>
                                </label>
                            </div>
                        </div>
                    </div>



                    <!-- Servicios opcionales MMSI -->
                    <div id="mmsi-options-section" style="display: none;">
                        <h4>Servicios opcionales MMSI</h4>
                        <div class="option-cards">
                            <div class="option-card">
                                <label>
                                    <input type="radio" name="mmsi_option" value="no_mmsi" data-price="0" />
                                    <span class="option-label">Sin MMSI</span>
                                    <span class="option-price">Incluido</span>
                                </label>
                            </div>
                            <div class="option-card">
                                <label>
                                    <input type="radio" name="mmsi_option" value="mmsi_licensed" data-price="170" />
                                    <span class="option-label">MMSI (propietario con licencia de operador)</span>
                                    <span class="option-price">+170€</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Servicios adicionales -->
                </div>
            </div>
            
            <!-- Grupo 4: Campos de dirección de facturación -->
            <div class="progressive-group" id="group-billing" data-group="billing" style="display: none;">
                <div class="group-header" onclick="toggleGroup('billing')">
                    <h3>Dirección de Facturación</h3>
                    <span class="group-status"></span>
                </div>
                <div class="group-content">
                    <div id="billing-address-section">
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
            </div>
        </div>

        <!-- Página de Documentación -->
        <div id="page-documents" class="form-page hidden">
            <!-- Grupo 5: Documentos -->
            <div class="progressive-group" id="group-documents" data-group="documents" style="display: none;">
                <div class="group-header" onclick="toggleGroup('documents')">
                    <h3>Adjuntar Documentación</h3>
                    <span class="group-status"></span>
                </div>
                <div class="group-content">
                    <div class="upload-section" id="documents-upload-section">
                        <!-- Se llenarán dinámicamente según el trámite seleccionado -->
                    </div>
                </div>
            </div>

            <!-- Grupo 6: Autorización -->
            <div class="progressive-group" id="group-authorization" data-group="authorization" style="display: none;">
                <div class="group-header" onclick="toggleGroup('authorization')">
                    <h3>Autorización y Firma</h3>
                    <span class="group-status"></span>
                </div>
                <div class="group-content">
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

                    <div class="terms-container" style="margin-top: 20px;">
                        <label>
                            <input type="checkbox" name="terms_accept" required> 
                            Acepto los <a href="https://tramitfy.es/terminos-y-condiciones-de-uso/" target="_blank">términos y condiciones</a>.
                        </label>
                    </div>
                </div>
            </div>

            <div class="button-container">
                <button type="button" class="button" id="prevButton">Anterior</button>
                <button type="button" class="button" id="nextButton">Siguiente</button>
            </div>
        </div>

        <!-- Página de Pago -->
        <div id="page-payment" class="form-page hidden">
            <div class="payment-header">
                <div style="font-weight: bold; font-size: 1.5em; margin: 0.83em 0; color: var(--primary-color);">Resumen de Compra</div>
                <p class="payment-subtitle">Revisa los detalles de tu pedido antes de proceder al pago</p>
            </div>

            <!-- Resumen de la orden -->
            <div class="order-summary">
                <div class="summary-header">
                    <h3>Detalle del Pedido</h3>
                    <span class="order-number">Orden #<span id="order-number"></span></span>
                </div>
                
                <div class="order-items" id="payment-price-details">
                    <!-- Se llenará dinámicamente según el trámite seleccionado -->
                </div>


            </div>

            <!-- Botones de acción -->
            <div class="payment-actions">
                <div class="terms-container">
                    <label class="custom-checkbox">
                        <input type="checkbox" name="terms_accept_pago" required>
                        <span class="checkmark"></span>
                        <span class="terms-text">
                            Acepto los <a href="https://tramitfy.es/terminos-y-condiciones-de-uso/" target="_blank">términos y condiciones</a> y la <a href="https://tramitfy.es/politica-de-privacidad/" target="_blank">política de privacidad</a>
                        </span>
                    </label>
                </div>
                
                <button id="proceed-to-payment" class="proceed-payment-btn">
                    <span class="btn-icon"></span>
                    <span class="btn-text">Proceder al pago</span>
                    <span class="btn-amount" id="btn-total-amount">0,00 €</span>
                </button>
                
            </div>
        </div>

        <div class="button-container" id="main-button-container" style="display: none;">
            <button type="button" class="button" id="prevButtonMain">Anterior</button>
            <button type="button" class="button" id="nextButtonMain">Siguiente</button>
        </div>
    </form>

    <!-- Modal de Pago Moderno -->
    <div id="payment-modal" class="payment-modal hidden">
        <div class="modal-backdrop"></div>
        <div class="modal-container">
            <div class="modal-header">
                <h3>Finalizar Pago</h3>
                <button type="button" class="modal-close" id="close-payment-modal">
                    <span>✕</span>
                </button>
            </div>
            
            <div class="modal-body">
                <!-- Resumen mini del pedido -->
                <div class="payment-summary">
                    <div class="summary-item">
                        <span class="item-name" id="modal-service-name">Servicio</span>
                        <span class="item-amount" id="modal-total-amount">0,00 €</span>
                    </div>
                </div>

                <!-- Elemento de pago de Stripe -->
                <div class="payment-form-container">
                    <div id="payment-element"></div>
                    <div id="payment-message" class="payment-message hidden"></div>
                </div>

                <!-- Información de seguridad -->
                <div class="security-footer">
                    <p>
                        <span class="security-icon"></span>
                        Pago seguro procesado por Stripe. Tus datos están protegidos.
                    </p>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" id="submit" class="pay-now-btn" style="display: flex !important; visibility: visible !important; opacity: 1 !important; width: 100%;">
                    <span class="btn-loader hidden">
                        <span class="spinner-small"></span>
                    </span>
                    <span class="btn-text">Proceder al pago</span>
                </button>
            </div>
        </div>
    </div>

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
            // Variables globales
            let stripe;
            let elements;
            let clientSecret;
            let selectedTramite = '';
            let currentPage = 0;
            let basePrice = 0;
            let currentPrice = 0;

            // Configuración de trámites polacos
            const tramitesConfig = {
                'registro': {
                    title: 'Registro bajo Bandera Polaca',
                    price: 429.99,
                    taxes: 75.00,
                    fees: 293.38,
                    documents: [
                        { id: 'dni_propietario', label: 'Copia del DNI o pasaporte del propietario', example: 'dni-propietario' },
                        { id: 'contrato_factura', label: 'Contrato de compraventa o factura de compra', example: 'factura-compra' },
                        { id: 'ce_conformidad', label: 'Certificado CE de conformidad', example: 'certificado-ce' },
                        { id: 'foto_placa_motor', label: 'Foto de la placa del motor', example: 'placa-motor' }
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
                        { id: 'size_0_7', label: '0 a 7 metros', price: 0 },
                        { id: 'size_7_12', label: '7.1 a 12 metros', price: 50 },
                        { id: 'size_12_24', label: '12.1 a 24 metros', price: 100 }
                    ],
                    deliveryOptions: [
                        { id: 'standard', label: 'Estándar 10-15 días', price: 0 },
                        { id: 'express', label: 'Express 1-3 días laborables', price: 180 }
                    ],
                    mmsiOptions: [
                        { id: 'no_mmsi', label: 'Sin MMSI', price: 0 },
                        { id: 'mmsi_licensed', label: 'MMSI (propietario con licencia de operador)', price: 170 }
                    ],
                    extraServices: []
                },
                'cambio_titularidad': {
                    title: 'Cambio de Titularidad - Bandera Polaca',
                    price: 429.99,
                    taxes: 50.00,
                    fees: 314.04,
                    documents: [
                        { id: 'dni_nuevo_propietario', label: 'Copia del DNI del nuevo propietario', example: 'dni-propietario' },
                        { id: 'dni_anterior_propietario', label: 'Copia del DNI del anterior propietario', example: 'dni-propietario' },
                        { id: 'contrato_compraventa', label: 'Contrato de compraventa', example: 'contrato-compraventa' },
                        { id: 'registro_polaco_actual', label: 'Registro polaco actual de la embarcación', example: 'registro-polaco' }
                    ],
                    fields: [],
                    boatSizes: [
                        { id: 'size_0_7', label: '0 a 7 metros', price: 0 },
                        { id: 'size_7_12', label: '7.1 a 12 metros', price: 50 },
                        { id: 'size_12_24', label: '12.1 a 24 metros', price: 100 }
                    ],
                    mmsiOptions: [
                        { id: 'no_mmsi', label: 'Sin MMSI', price: 0 },
                        { id: 'mmsi_licensed', label: 'MMSI (propietario con licencia de operador)', price: 170 }
                    ],
                    extraServices: []
                },
                'mmsi': {
                    title: 'Solicitud de Número MMSI Polaco',
                    price: 190.00,
                    taxes: 40.00,
                    fees: 123.97,
                    documents: [
                        { id: 'dni_propietario', label: 'Copia del DNI o pasaporte del propietario', example: 'dni-propietario' },
                        { id: 'registro_polaco', label: 'Registro polaco de la embarcación', example: 'registro-polaco' },
                        { id: 'certificado_radio', label: 'Certificado de radiooperador (si aplica)', example: 'certificado-radio' },
                        { id: 'especificaciones_radio', label: 'Especificaciones del equipo de radio', example: 'radio-specs' }
                    ],
                    fields: [
                        { type: 'text', id: 'radio_operator_cert', label: 'Número de certificado de radiooperador (opcional)', placeholder: 'Si posee certificado', required: false }
                    ]
                }
            };

            // Páginas del formulario
            const formPages = document.querySelectorAll('.form-page');
            const navLinks = document.querySelectorAll('.nav-link');

            // Manejo de selección de trámite en la portada
            document.querySelectorAll('.tramite-card').forEach(card => {
                card.addEventListener('click', function() {
                    // Remover selección anterior
                    document.querySelectorAll('.tramite-card').forEach(c => c.classList.remove('selected'));
                    
                    // Seleccionar tarjeta actual
                    this.classList.add('selected');
                    selectedTramite = this.dataset.tramite;
                    document.getElementById('selected_tramite').value = selectedTramite;
                    
                    // Mostrar botón continuar
                    document.getElementById('continue-from-portada').classList.add('show');
                });
            });

            // Continuar desde portada
            document.getElementById('continue-from-portada').addEventListener('click', function() {
                if (!selectedTramite) {
                    alert('Por favor, selecciona un trámite antes de continuar.');
                    return;
                }
                
                // Configurar el formulario según el trámite seleccionado
                setupTramiteForm();
                
                // Ir a la primera página del formulario
                currentPage = 1; // Página de datos personales
                updateForm();
            });

            // Volver a la portada
            document.getElementById('back-to-home').addEventListener('click', function() {
                // Limpiar selección de trámite
                selectedTramite = null;
                document.getElementById('selected_tramite').value = '';
                
                // Limpiar formulario
                document.getElementById('polish-registration-form').reset();
                
                // Limpiar selección de tarjetas
                document.querySelectorAll('.tramite-card').forEach(card => {
                    card.classList.remove('selected');
                });
                
                // Ocultar botón continuar
                document.getElementById('continue-from-portada').classList.remove('show');
                
                // Volver a la portada
                currentPage = 0;
                updateForm();
                
                // Limpiar título
                document.getElementById('tramite-selected-title').textContent = '';
                
                // Reiniciar precio
                currentPrice = 0;
                basePrice = 0;
            });

            function setupTramiteForm() {
                const config = tramitesConfig[selectedTramite];
                if (!config) return;

                // Actualizar título
                document.getElementById('tramite-selected-title').textContent = config.title;
                
                // Configurar precio base
                basePrice = config.price;
                currentPrice = basePrice;
                
                
                // Mostrar/ocultar campos específicos en datos básicos
                showSpecificFieldsInBasicData(selectedTramite);
                
                // Mostrar/ocultar secciones de opciones según el trámite
                showOptionsForTramite(selectedTramite);
                
                // Generar sección de documentos
                generateDocumentsSection(config.documents);
                
                // Actualizar detalles de precio
                updatePriceDetails(config);
                
                // Actualizar precio total después de generar opciones
                setTimeout(updateTotalPrice, 100);
                
                // Verificar la completitud inicial de todos los grupos y asegurar orden
                setTimeout(() => {
                    ensureGroupOrder();
                    checkAllGroupsCompletion();
                }, 200);
            }

            function showSpecificFieldsInBasicData(tramite) {
                // Ocultar todos los campos específicos primero
                document.getElementById('boat-port-field').style.display = 'none';
                document.getElementById('radio-cert-field').style.display = 'none';
                
                // Hacer los campos no requeridos por defecto
                document.getElementById('boat_port').required = false;
                document.getElementById('radio_operator_cert').required = false;
                
                // Mostrar campos según el trámite
                if (tramite === 'registro') {
                    document.getElementById('boat-port-field').style.display = 'block';
                    document.getElementById('boat_port').required = true;
                } else if (tramite === 'mmsi') {
                    document.getElementById('radio-cert-field').style.display = 'block';
                    // radio_operator_cert es opcional, no required
                }
            }

            function showOptionsForTramite(tramite) {
                // Mostrar/ocultar el grupo de opciones
                const optionsGroup = document.getElementById('group-tramite-options');
                
                // Ocultar todas las secciones primero
                const sections = [
                    'boat-sizes-section',
                    'delivery-options-section', 
                    'mmsi-options-section'
                ];
                
                sections.forEach(sectionId => {
                    const section = document.getElementById(sectionId);
                    if (section) section.style.display = 'none';
                });
                
                // Mostrar secciones según el trámite
                if (tramite === 'registro') {
                    optionsGroup.style.display = 'block';
                    document.getElementById('boat-sizes-section').style.display = 'block';
                    document.getElementById('delivery-options-section').style.display = 'block';
                    document.getElementById('mmsi-options-section').style.display = 'block';
                } else if (tramite === 'cambio_titularidad') {
                    optionsGroup.style.display = 'block';
                    document.getElementById('boat-sizes-section').style.display = 'block';
                    document.getElementById('mmsi-options-section').style.display = 'block';
                } else {
                    optionsGroup.style.display = 'none';
                }
                
                // Agregar event listeners para actualizar precios
                addPriceUpdateListeners();
            }

            function addPriceUpdateListeners() {
                // Agregar listeners a todos los inputs de opciones
                const optionInputs = document.querySelectorAll('#group-tramite-options input[type="radio"], #group-tramite-options input[type="checkbox"]');
                optionInputs.forEach(input => {
                    input.removeEventListener('change', updateTotalPrice); // Remover listeners existentes
                    input.addEventListener('change', updateTotalPrice);
                    
                    // También agregar listener para verificar completitud del grupo
                    input.removeEventListener('change', checkOptionsGroupCompletion);
                    input.addEventListener('change', checkOptionsGroupCompletion);
                });
            }

            function checkOptionsGroupCompletion() {
                const optionsGroup = document.getElementById('group-tramite-options');
                if (optionsGroup) {
                    checkGroupCompletion(optionsGroup);
                }
            }

            function checkAllGroupsCompletion() {
                const allGroups = document.querySelectorAll('.progressive-group');
                allGroups.forEach(group => {
                    // Solo verificar grupos que estén visibles
                    const computedStyle = window.getComputedStyle(group);
                    if (computedStyle.display !== 'none' && group.style.display !== 'none') {
                        checkGroupCompletion(group);
                    }
                });
            }


            // Funciones obsoletas removidas - ahora usamos HTML estático con control de visibilidad

            function updateTotalPrice() {
                const config = tramitesConfig[selectedTramite];
                if (!config) return;
                
                let totalPrice = config.price;
                
                // Sumar precios de radio buttons seleccionados en el grupo de opciones
                const radioButtons = document.querySelectorAll('#group-tramite-options input[type="radio"]:checked');
                radioButtons.forEach(radio => {
                    const price = parseFloat(radio.getAttribute('data-price')) || 0;
                    totalPrice += price;
                });
                
                // Sumar precios de checkboxes seleccionados en el grupo de opciones
                const checkboxes = document.querySelectorAll('#group-tramite-options input[type="checkbox"]:checked');
                checkboxes.forEach(checkbox => {
                    const price = parseFloat(checkbox.getAttribute('data-price')) || 0;
                    totalPrice += price;
                });
                
                
                currentPrice = totalPrice;
                
                // Actualizar la visualización del precio
                updatePriceDisplay();
            }

            function updatePriceDisplay() {
                const config = tramitesConfig[selectedTramite];
                if (!config) return;
                
                // Generar número de orden único
                if (!document.getElementById('order-number').textContent) {
                    const orderNumber = 'PL' + Date.now().toString().slice(-6);
                    document.getElementById('order-number').textContent = orderNumber;
                }
                
                const container = document.getElementById('payment-price-details');
                if (!container) return;
                
                let basePrice = config.price;
                let additionalCosts = 0;
                let orderItemsHTML = '';
                let itemCount = 0;
                
                // Servicio principal con descripción más detallada
                const serviceDescriptions = {
                    'registro': 'Registro completo bajo bandera polaca - Incluye documentación oficial, gestión completa de trámites y certificado de registro',
                    'cambio_titularidad': 'Cambio de propietario para embarcación registrada - Gestión completa de documentación y tramitación oficial',
                    'mmsi': 'Solicitud de número MMSI polaco - Identificación única para comunicaciones marítimas y equipos de radio'
                };
                
                orderItemsHTML += `
                    <div class="order-item">
                        <div class="item-details">
                            <h4>${config.title}</h4>
                            <p>${serviceDescriptions[selectedTramite] || 'Tramitación completa del servicio'}</p>
                        </div>
                        <div class="item-price">${basePrice.toFixed(2)} €</div>
                    </div>
                `;
                itemCount++;
                
                // Mostrar costos adicionales de radio buttons con descripciones mejoradas
                const radioButtons = document.querySelectorAll('#group-tramite-options input[type="radio"]:checked');
                radioButtons.forEach(radio => {
                    const price = parseFloat(radio.getAttribute('data-price')) || 0;
                    if (price > 0) {
                        const label = radio.parentElement.querySelector('.option-label').textContent;
                        additionalCosts += price;
                        
                        // Descripciones más detalladas para servicios adicionales
                        let description = 'Servicio adicional';
                        if (label.includes('Express')) description = 'Entrega prioritaria en 1-3 días laborables';
                        else if (label.includes('MMSI')) description = 'Número de identificación para equipos de radio marítimos';
                        
                        orderItemsHTML += `
                            <div class="order-item">
                                <div class="item-details">
                                    <h4>${label}</h4>
                                    <p>${description}</p>
                                </div>
                                <div class="item-price">+${price.toFixed(2)} €</div>
                            </div>
                        `;
                        itemCount++;
                    }
                });
                
                // Mostrar costos adicionales de checkboxes con descripciones mejoradas
                const checkboxes = document.querySelectorAll('#group-tramite-options input[type="checkbox"]:checked');
                checkboxes.forEach(checkbox => {
                    const price = parseFloat(checkbox.getAttribute('data-price')) || 0;
                    if (price > 0) {
                        const label = checkbox.parentElement.querySelector('.option-label').textContent;
                        additionalCosts += price;
                        
                        // Descripciones más detalladas para servicios extra
                        let description = 'Servicio adicional';
                        if (label.includes('Apostilla')) description = 'Legalización oficial de documentos para uso internacional';
                        else if (label.includes('Extracto')) description = 'Certificado oficial del registro marítimo polaco';
                        else if (label.includes('Bandera')) description = 'Bandera oficial polaca 65x40 cm con ojales metálicos';
                        
                        orderItemsHTML += `
                            <div class="order-item">
                                <div class="item-details">
                                    <h4>${label}</h4>
                                    <p>${description}</p>
                                </div>
                                <div class="item-price">+${price.toFixed(2)} €</div>
                            </div>
                        `;
                        itemCount++;
                    }
                });
                
                container.innerHTML = orderItemsHTML;
                
                // Actualizar botón con el precio total
                document.getElementById('btn-total-amount').textContent = currentPrice.toFixed(2) + ' €';
                
                // Actualizar contador de items si existe
                const itemCountElement = document.getElementById('item-count');
                if (itemCountElement) {
                    itemCountElement.textContent = itemCount + (itemCount === 1 ? ' artículo' : ' artículos');
                }
            }

            function generateDocumentsSection(documents) {
                const container = document.getElementById('documents-upload-section');
                container.innerHTML = '';
                
                documents.forEach((doc, index) => {
                    const div = document.createElement('div');
                    div.className = 'upload-item';
                    
                    // Determinar qué documentos son obligatorios según el trámite
                    const isRequired = doc.id === 'dni_propietario' || 
                                     doc.id === 'dni_nuevo_propietario' || 
                                     doc.id === 'contrato_factura' || 
                                     doc.id === 'ce_conformidad' || 
                                     doc.id === 'foto_placa_motor' || 
                                     doc.id === 'contrato_compraventa' || 
                                     doc.id === 'registro_polaco_actual' ||
                                     !doc.label.includes('opcional');
                    const requiredText = isRequired ? '<span style="color: red;">*</span>' : '';
                    
                    div.innerHTML = `
                        <label for="upload-${doc.id}">
                            ${doc.label}${requiredText}
                        </label>
                        <input type="file" id="upload-${doc.id}" name="upload_${doc.id}" ${isRequired ? 'required' : ''}>
                        <a href="#" class="view-example" data-doc="${doc.example}">Ver ejemplo</a>
                    `;
                    container.appendChild(div);
                });
                
                // Agregar event listeners para detectar cambios en archivos
                const documentGroup = document.getElementById('group-documents');
                if (documentGroup) {
                    const fileInputs = container.querySelectorAll('input[type="file"]');
                    fileInputs.forEach(input => {
                        input.addEventListener('change', () => checkGroupCompletion(documentGroup));
                    });
                }
            }

            function updatePriceDetails(config) {
                updatePriceDisplay();
            }

            function updateForm() {
                // Ocultar/mostrar páginas
                formPages.forEach((page, index) => {
                    if (index === 0) { // Portada
                        page.classList.toggle('hidden', currentPage !== 0);
                    } else {
                        page.classList.toggle('hidden', index !== currentPage);
                    }
                });

                // Mostrar automáticamente el primer grupo de cada página
                if (currentPage === 1) { // Página de datos personales
                    setTimeout(() => {
                        const basicGroup = document.getElementById('group-basic-data');
                        if (basicGroup && basicGroup.style.display === 'none') {
                            basicGroup.style.display = 'block';
                        }
                        // Asegurar orden correcto y verificar completitud
                        ensureGroupOrder();
                        checkAllGroupsCompletion();
                    }, 100);
                } else if (currentPage === 2) { // Página de documentos
                    setTimeout(() => {
                        const docsGroup = document.getElementById('group-documents');
                        if (docsGroup && docsGroup.style.display === 'none') {
                            docsGroup.style.display = 'block';
                        }
                        // Asegurar orden correcto y verificar completitud
                        ensureGroupOrder();
                        checkAllGroupsCompletion();
                    }, 100);
                }

                // Mostrar/ocultar navegación
                const navigation = document.getElementById('form-navigation');
                navigation.classList.toggle('hidden', currentPage === 0);

                // Actualizar enlaces de navegación
                navLinks.forEach((link, index) => {
                    link.classList.toggle('active', index === currentPage - 1);
                });

                // Manejar botones de navegación
                const mainButtonContainer = document.getElementById('main-button-container');
                if (currentPage === 0) {
                    mainButtonContainer.style.display = 'none';
                } else if (currentPage === 2) { // Página de documentos
                    mainButtonContainer.style.display = 'none';
                    document.querySelector('#page-documents .button-container').style.display = 'flex';
                } else if (currentPage === 3) { // Página de pago
                    mainButtonContainer.style.display = 'none';
                } else {
                    mainButtonContainer.style.display = 'flex';
                    if (document.querySelector('#page-documents .button-container')) {
                        document.querySelector('#page-documents .button-container').style.display = 'none';
                    }
                }

                // Botón anterior
                const prevButtonMain = document.getElementById('prevButtonMain');
                if (prevButtonMain) {
                    prevButtonMain.style.display = (currentPage <= 1) ? 'none' : 'inline-block';
                }

                // Botón siguiente
                const nextButtonMain = document.getElementById('nextButtonMain');
                if (nextButtonMain) {
                    nextButtonMain.style.display = (currentPage >= 3) ? 'none' : 'inline-block';
                }

                // Inicializar Stripe en la página de pago
                if (currentPage === 3 && !stripe) {
                    initializeStripe().catch(error => {
                        alert('Error al inicializar el pago: ' + error.message);
                    });
                    handlePayment();
                }

                // Generar documento de autorización en la página de documentos
                if (currentPage === 2) {
                    generateAuthorizationDocument();
                    // Inicializar signature pad cuando se muestra la página de documentos
                    setTimeout(initializeSignaturePad, 100);
                }

                // Inicializar grupos progresivos cuando se muestra una página
                if (currentPage === 1) {
                    initializeProgressiveGroups('page-personal-info');
                } else if (currentPage === 2) {
                    initializeProgressiveGroups('page-documents');
                }
            }

            function generateAuthorizationDocument() {
                const authorizationDiv = document.getElementById('authorization-document');
                const customerName = document.getElementById('customer_name').value.trim();
                const customerDNI = document.getElementById('customer_dni').value.trim();
                const tramiteTitle = tramitesConfig[selectedTramite].title;

                let authorizationHTML = `
                    <p>Yo, <strong>${customerName}</strong>, con DNI/Pasaporte <strong>${customerDNI}</strong>, autorizo a Tramitfy S.L. (CIF B55388557) a realizar en mi nombre los trámites necesarios para: <strong>${tramiteTitle}</strong>.</p>
                    <p>Declaro que toda la información y documentación proporcionada es veraz y completa.</p>
                    <p>Firmo a continuación en señal de conformidad.</p>
                `;
                authorizationDiv.innerHTML = authorizationHTML;
            }

            // Inicializar Stripe
            async function initializeStripe(customAmount = null) {
                const amountToCharge = (customAmount !== null) ? customAmount : currentPrice;
                const totalAmountCents = Math.round(amountToCharge * 100);

                stripe = Stripe('<?php echo $stripe_public_key; ?>');

                const response = await fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=create_payment_intent_polish_registration&amount=${totalAmountCents}`
                });
                const result = await response.json();

                if (result.error) {
                    throw new Error(result.error);
                }

                clientSecret = result.clientSecret;

                const appearance = {
                    theme: 'flat',
                    variables: {
                        colorPrimary: 'var(--primary-color)',
                        colorBackground: '#ffffff',
                        colorText: '#333',
                        colorDanger: 'var(--primary-color)',
                        fontFamily: 'Arial, sans-serif',
                        spacingUnit: '4px',
                        borderRadius: '4px',
                    }
                };

                elements = stripe.elements({ appearance, clientSecret });
                const paymentElementOptions = {
                    paymentMethodOrder: ['card'],
                };
                const paymentElement = elements.create('payment', paymentElementOptions);
                paymentElement.mount('#payment-element');
            }

            function handlePayment() {
                const submitButton = document.getElementById('submit');
                submitButton.addEventListener('click', async (e) => {
                    e.preventDefault();

                    if (!document.querySelector('input[name="terms_accept_pago"]').checked) {
                        alert('Debe aceptar los términos y condiciones de pago para continuar.');
                        return;
                    }

                    submitButton.disabled = true;
                    const loadingOverlay = document.getElementById('loading-overlay');
                    if (loadingOverlay) {
                        loadingOverlay.style.display = 'flex';
                    }

                    try {
                        // Verificar que todos los datos requeridos estén disponibles
                        const customerName = document.getElementById('customer_name');
                        const customerEmail = document.getElementById('customer_email');
                        const customerPhone = document.getElementById('customer_phone');
                        
                        if (!customerName?.value || !customerEmail?.value || !customerPhone?.value) {
                            throw new Error('Faltan datos del cliente. Por favor, complete todos los campos requeridos.');
                        }
                        
                        const { error } = await stripe.confirmPayment({
                            elements,
                            confirmParams: {
                                payment_method_data: {
                                    billing_details: {
                                        name: customerName.value,
                                        email: customerEmail.value,
                                        phone: customerPhone.value
                                    }
                                },
                                return_url: window.location.href
                            },
                            redirect: 'if_required'
                        });

                        if (error) {
                            throw new Error(error.message);
                        } else {
                            const paymentMessage = document.getElementById('payment-message');
                            if (paymentMessage) {
                                paymentMessage.textContent = 'Pago realizado con éxito.';
                                paymentMessage.classList.add('success');
                                paymentMessage.classList.remove('hidden');
                            }
                            handleFinalSubmission();
                        }
                    } catch (error) {
                        const paymentMessage = document.getElementById('payment-message');
                        if (paymentMessage) {
                            paymentMessage.textContent = 'Error al procesar el pago: ' + error.message;
                            paymentMessage.classList.add('error');
                            paymentMessage.classList.remove('hidden');
                        }
                        submitButton.disabled = false;
                        const loadingOverlay = document.getElementById('loading-overlay');
                        if (loadingOverlay) loadingOverlay.style.display = 'none';
                    }
                });
            }

            function handleFinalSubmission() {
                const signaturePad = window.signaturePad;
                if (!signaturePad || signaturePad.isEmpty()) {
                    alert('Por favor, firme antes de enviar el formulario.');
                    const loadingOverlay = document.getElementById('loading-overlay');
                    if (loadingOverlay) loadingOverlay.style.display = 'none';
                    
                    // Expandir el grupo de autorización para que el usuario pueda firmar
                    const authGroup = document.getElementById('group-authorization');
                    if (authGroup) {
                        authGroup.classList.remove('collapsed');
                        authGroup.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                    return;
                }

                let formData = new FormData(document.getElementById('polish-registration-form'));
                formData.append('action', 'submit_form_polish_registration');
                formData.append('signature', signaturePad.toDataURL());

                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    const loadingOverlay = document.getElementById('loading-overlay');
                    if (loadingOverlay) loadingOverlay.style.display = 'none';
                    
                    if (data.success) {
                        alert('Formulario enviado con éxito.');
                        window.location.href = '<?php echo site_url('/pago-realizado-con-exito'); ?>';
                    } else {
                        alert('Error al enviar el formulario: ' + (data.message || 'Error desconocido'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    const loadingOverlay = document.getElementById('loading-overlay');
                    if (loadingOverlay) loadingOverlay.style.display = 'none';
                    alert('Hubo un error al enviar el formulario.');
                });
            }

            // Eventos de navegación
            document.getElementById('nextButtonMain')?.addEventListener('click', () => {
                if (!handleProgressiveNavigation()) return;
                currentPage++;
                updateForm();
            });

            document.getElementById('prevButtonMain')?.addEventListener('click', () => {
                currentPage--;
                updateForm();
            });

            document.getElementById('prevButton')?.addEventListener('click', () => {
                currentPage--;
                updateForm();
            });

            document.getElementById('nextButton')?.addEventListener('click', () => {
                if (!handleProgressiveNavigation()) return;
                currentPage++;
                updateForm();
            });

            // Navegación por enlaces del menú superior
            navLinks.forEach(link => {
                link.addEventListener('click', (e) => {
                    e.preventDefault();
                    const pageId = link.getAttribute('data-page-id');
                    const pageIndex = Array.from(formPages).findIndex(page => page.id === pageId);
                    if (pageIndex !== -1) {
                        // Permitir navegación libre por el menú superior
                        currentPage = pageIndex;
                        updateForm();
                        
                        // Mostrar todos los grupos de la página visitada
                        if (pageIndex === 1) {
                            showAllGroupsInPage('page-personal-info');
                        } else if (pageIndex === 2) {
                            showAllGroupsInPage('page-documents');
                        }
                    }
                });
            });

            // Función para manejar navegación progresiva de grupos
            function handleProgressiveNavigation() {
                const currentPageElement = formPages[currentPage];
                const progressiveGroups = currentPageElement.querySelectorAll('.progressive-group');
                
                if (progressiveGroups.length === 0) {
                    // Si no hay grupos progresivos, usar validación estándar
                    return validateCurrentPage();
                }
                
                // Definir el orden esperado de grupos por página
                const pageGroupOrders = {
                    1: ['group-basic-data', 'group-tramite-options', 'group-billing'], // Página datos personales
                    2: ['group-documents', 'group-authorization'] // Página documentos
                };
                
                const expectedOrder = pageGroupOrders[currentPage] || [];
                const hiddenGroups = [];
                
                // Identificar grupos que deberían estar visibles pero están ocultos
                expectedOrder.forEach(groupId => {
                    const group = document.getElementById(groupId);
                    if (group && group.style.display === 'none') {
                        // Verificar si el grupo debería estar visible según el trámite seleccionado
                        if (shouldGroupBeVisible(groupId)) {
                            hiddenGroups.push(group);
                        }
                    }
                });
                
                // Si hay grupos ocultos, mostrar el primero y detener navegación
                if (hiddenGroups.length > 0) {
                    const nextGroupToShow = hiddenGroups[0];
                    showGroupSequentially(nextGroupToShow);
                    
                    // Mostrar mensaje informativo sin ser intrusivo
                    showProgressiveMessage(`Mostrando sección: ${nextGroupToShow.querySelector('h3').textContent}`);
                    
                    return false; // Prevenir navegación
                }
                
                // Si todos los grupos están visibles, verificar que estén completados
                return validateCurrentPage();
            }
            
            // Función para mostrar grupos secuencialmente con animación suave
            function showGroupSequentially(group) {
                if (!group || group.style.display !== 'none') return;
                
                // Mostrar el grupo con animación suave
                group.style.display = 'block';
                group.style.opacity = '0';
                group.style.transform = 'translateY(-20px)';
                
                // Forzar re-flow para la animación
                group.offsetHeight;
                
                // Aplicar transición
                group.style.transition = 'all 0.5s ease-in-out';
                group.style.opacity = '1';
                group.style.transform = 'translateY(0)';
                
                // Scroll suave hacia el grupo recién mostrado
                setTimeout(() => {
                    const rect = group.getBoundingClientRect();
                    const isVisible = rect.top >= 0 && rect.bottom <= window.innerHeight;
                    
                    if (!isVisible) {
                        group.scrollIntoView({ 
                            behavior: 'smooth', 
                            block: 'center',
                            inline: 'nearest'
                        });
                    }
                    
                    // Verificar completitud del grupo recién mostrado
                    checkGroupCompletion(group);
                }, 100);
                
                // Limpiar estilos de transición después de la animación
                setTimeout(() => {
                    group.style.transition = '';
                }, 500);
            }
            
            // Función para mostrar mensajes de progreso no intrusivos
            function showProgressiveMessage(message) {
                // Buscar contenedor de mensajes existente o crearlo
                let messageContainer = document.getElementById('progressive-message');
                if (!messageContainer) {
                    messageContainer = document.createElement('div');
                    messageContainer.id = 'progressive-message';
                    messageContainer.style.cssText = `
                        position: fixed;
                        top: 20px;
                        right: 20px;
                        background: var(--primary-color);
                        color: white;
                        padding: 12px 20px;
                        border-radius: 6px;
                        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                        z-index: 1001;
                        font-size: 14px;
                        opacity: 0;
                        transform: translateX(100%);
                        transition: all 0.3s ease-in-out;
                        max-width: 300px;
                    `;
                    document.body.appendChild(messageContainer);
                }
                
                // Actualizar mensaje y mostrar
                messageContainer.textContent = message;
                messageContainer.style.opacity = '1';
                messageContainer.style.transform = 'translateX(0)';
                
                // Ocultar después de 3 segundos
                setTimeout(() => {
                    messageContainer.style.opacity = '0';
                    messageContainer.style.transform = 'translateX(100%)';
                }, 3000);
            }
            
            // Función para determinar si un grupo debería estar visible según el trámite seleccionado
            function shouldGroupBeVisible(groupId) {
                // Siempre visibles
                const alwaysVisible = ['group-basic-data', 'group-documents', 'group-authorization', 'group-billing'];
                if (alwaysVisible.includes(groupId)) {
                    return true;
                }
                
                
                // Grupo de opciones del trámite
                if (groupId === 'group-tramite-options') {
                    return selectedTramite === 'registro' || selectedTramite === 'cambio_titularidad';
                }
                
                return true; // Por defecto, considerar visible
            }

            // Sistema moderno de validación con indicadores visuales en campos
            function validateCurrentPage() {
                let valid = true;
                const currentPageElement = formPages[currentPage];
                
                // Limpiar todos los estados de error previos
                clearAllFieldStates(currentPageElement);
                
                // Verificar si la página actual tiene grupos progresivos
                const progressiveGroups = currentPageElement.querySelectorAll('.progressive-group');
                
                if (progressiveGroups.length > 0) {
                    // Validación para páginas con grupos progresivos
                    const visibleGroups = Array.from(progressiveGroups).filter(group => 
                        group.style.display !== 'none'
                    );
                    
                    visibleGroups.forEach(group => {
                        if (!validateGroup(group)) {
                            valid = false;
                        }
                    });
                } else {
                    // Validación tradicional para páginas sin grupos progresivos
                    const requiredFields = currentPageElement.querySelectorAll('input[required], select[required], textarea[required]');
                    
                    requiredFields.forEach(field => {
                        if (!validateField(field)) {
                            valid = false;
                        }
                    });
                }
                
                // Si hay errores, enfocar el primer campo con error
                if (!valid) {
                    const firstErrorField = currentPageElement.querySelector('.field-error');
                    if (firstErrorField) {
                        setTimeout(() => {
                            firstErrorField.scrollIntoView({ 
                                behavior: 'smooth', 
                                block: 'center' 
                            });
                            firstErrorField.focus();
                        }, 100);
                    }
                }
                
                return valid;
            }
            
            // Función para validar un grupo específico
            function validateGroup(group) {
                let groupValid = true;
                
                // Verificación especial por tipo de grupo
                if (group.dataset.group === 'billing') {
                    const sameAddressCheckbox = document.getElementById('same_address');
                    if (sameAddressCheckbox && sameAddressCheckbox.checked) {
                        return true; // Saltar validación de facturación si usa mismos datos
                    }
                    
                    const requiredFields = group.querySelectorAll('input[required]');
                    requiredFields.forEach(field => {
                        if (!validateField(field)) {
                            groupValid = false;
                        }
                    });
                } else if (group.dataset.group === 'documents') {
                    // Como todos los documentos son opcionales, este grupo siempre es válido
                    // Solo removemos estados de error si los hubiera
                    const fileInputs = group.querySelectorAll('input[type="file"]');
                    fileInputs.forEach(field => {
                        clearFieldState(field);
                    });
                } else if (group.dataset.group === 'authorization') {
                    // Validar firma y términos
                    const termsCheckbox = group.querySelector('input[name="terms_accept"]');
                    const signaturePad = window.signaturePad;
                    
                    if (termsCheckbox && !termsCheckbox.checked) {
                        setFieldState(termsCheckbox, 'error', 'Debe aceptar los términos y condiciones');
                        groupValid = false;
                    }
                    
                    if (!signaturePad || signaturePad.isEmpty()) {
                        showCustomError('signature-pad', 'Debe proporcionar su firma');
                        groupValid = false;
                    }
                } else {
                    // Validación general para otros grupos
                    const requiredFields = group.querySelectorAll('input[required], select[required], textarea[required]');
                    requiredFields.forEach(field => {
                        if (!validateField(field)) {
                            groupValid = false;
                        }
                    });
                }
                
                if (!groupValid) {
                    // Expandir el grupo que tiene errores
                    group.classList.remove('collapsed');
                }
                
                return groupValid;
            }
            
            // Función para validar un campo individual
            function validateField(field) {
                if (!field) return true;
                
                let isValid = true;
                let errorMessage = '';
                
                if (field.type === 'file') {
                    if (field.required && (!field.files || field.files.length === 0)) {
                        isValid = false;
                        errorMessage = 'Este documento es obligatorio';
                    }
                } else if (field.type === 'checkbox') {
                    if (field.required && !field.checked) {
                        isValid = false;
                        errorMessage = 'Debe marcar esta casilla';
                    }
                } else if (field.type === 'email') {
                    if (!field.value.trim()) {
                        if (field.required) {
                            isValid = false;
                            errorMessage = 'Este campo es obligatorio';
                        }
                    } else if (!isValidEmail(field.value.trim())) {
                        isValid = false;
                        errorMessage = 'Formato de email inválido';
                    }
                } else {
                    if (field.required && !field.value.trim()) {
                        isValid = false;
                        errorMessage = 'Este campo es obligatorio';
                    }
                }
                
                // Aplicar estado visual al campo
                if (isValid) {
                    setFieldState(field, 'success');
                } else {
                    setFieldState(field, 'error', errorMessage);
                    // Añadir animación de shake
                    field.classList.add('field-shake');
                    setTimeout(() => field.classList.remove('field-shake'), 500);
                }
                
                return isValid;
            }
            
            // Función para establecer el estado visual de un campo
            function setFieldState(field, state, message = '') {
                if (!field) return;
                
                // Limpiar estados previos
                clearFieldState(field);
                
                // Crear contenedor si no existe
                let container = field.closest('.field-container');
                if (!container) {
                    container = document.createElement('div');
                    container.className = 'field-container';
                    field.parentNode.insertBefore(container, field);
                    container.appendChild(field);
                }
                
                // Aplicar estado al campo
                field.classList.add(`field-${state}`);
                
                // Crear y mostrar indicador
                const indicator = createFieldIndicator(state);
                container.appendChild(indicator);
                
                // Crear y mostrar tooltip si hay mensaje
                if (message && state !== 'success') {
                    const tooltip = createFieldTooltip(message, state);
                    container.appendChild(tooltip);
                    
                    // Mostrar tooltip temporalmente
                    setTimeout(() => tooltip.classList.add('show'), 100);
                    setTimeout(() => tooltip.classList.remove('show'), 4000);
                }
            }
            
            // Función para limpiar el estado de un campo
            function clearFieldState(field) {
                if (!field) return;
                
                field.classList.remove('field-error', 'field-warning', 'field-success', 'field-shake');
                
                const container = field.closest('.field-container');
                if (container) {
                    // Remover indicadores y tooltips previos
                    const indicators = container.querySelectorAll('.field-indicator');
                    const tooltips = container.querySelectorAll('.field-tooltip');
                    
                    indicators.forEach(indicator => indicator.remove());
                    tooltips.forEach(tooltip => tooltip.remove());
                }
            }
            
            // Función para limpiar todos los estados de campo en una página
            function clearAllFieldStates(pageElement) {
                const allFields = pageElement.querySelectorAll('input, select, textarea');
                allFields.forEach(field => clearFieldState(field));
            }
            
            // Función para crear indicador visual de campo
            function createFieldIndicator(state) {
                const indicator = document.createElement('div');
                indicator.className = `field-indicator ${state} show`;
                
                const icons = {
                    error: '✕',
                    warning: '!',
                    success: '✓'
                };
                
                indicator.textContent = icons[state] || '';
                return indicator;
            }
            
            // Función para crear tooltip de campo
            function createFieldTooltip(message, state = 'error') {
                const tooltip = document.createElement('div');
                tooltip.className = `field-tooltip ${state}`;
                tooltip.textContent = message;
                return tooltip;
            }
            
            // Función para mostrar error personalizado (ej: firma)
            function showCustomError(elementId, message) {
                const element = document.getElementById(elementId);
                if (!element) return;
                
                // Agregar borde de error temporal
                element.style.border = '2px solid #dc3545';
                element.style.boxShadow = '0 0 0 0.2rem rgba(220, 53, 69, 0.25)';
                
                // Crear mensaje temporal
                let errorMsg = element.parentNode.querySelector('.custom-error-msg');
                if (!errorMsg) {
                    errorMsg = document.createElement('div');
                    errorMsg.className = 'custom-error-msg';
                    errorMsg.style.cssText = `
                        color: #dc3545;
                        font-size: 13px;
                        margin-top: 5px;
                        font-weight: 500;
                    `;
                    element.parentNode.appendChild(errorMsg);
                }
                
                errorMsg.textContent = message;
                
                // Limpiar después de unos segundos
                setTimeout(() => {
                    element.style.border = '';
                    element.style.boxShadow = '';
                    if (errorMsg) errorMsg.remove();
                }, 4000);
            }
            
            // Función helper para validar email
            function isValidEmail(email) {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                return emailRegex.test(email);
            }

            // Inicializar signature pad (solo cuando sea necesario)
            function initializeSignaturePad() {
                if (!window.signaturePad && document.getElementById('signature-pad')) {
                    window.signaturePad = new SignaturePad(document.getElementById('signature-pad'));
                    
                    // Agregar event listener para detectar cambios en la firma
                    const authGroup = document.getElementById('group-authorization');
                    if (authGroup) {
                        window.signaturePad.addEventListener('endStroke', () => {
                            checkGroupCompletion(authGroup);
                        });
                    }
                }
            }

            document.getElementById('clear-signature')?.addEventListener('click', function() {
                if (window.signaturePad) {
                    window.signaturePad.clear();
                    // Verificar el estado del grupo después de limpiar
                    const authGroup = document.getElementById('group-authorization');
                    if (authGroup) {
                        checkGroupCompletion(authGroup);
                    }
                }
            });

            // Manejo del popup para ejemplos de documentos
            const popup = document.getElementById('document-popup');
            const closePopup = document.querySelector('.close-popup');
            const exampleImage = document.getElementById('document-example-image');

            document.addEventListener('click', function(event) {
                if (event.target.classList.contains('view-example')) {
                    event.preventDefault();
                    const docType = event.target.getAttribute('data-doc');
                    exampleImage.src = '/wp-content/uploads/exampledocs/' + docType + '.jpg';
                    popup.style.display = 'block';
                }
            });

            closePopup?.addEventListener('click', () => {
                popup.style.display = 'none';
            });

            window.addEventListener('click', function(event) {
                if (event.target == popup) {
                    popup.style.display = 'none';
                }
            });

            // Gestionar checkbox de misma dirección
            document.getElementById('same_address')?.addEventListener('change', function() {
                const billingFields = document.getElementById('billing-fields');
                const billingInputs = billingFields.querySelectorAll('input');
                
                if (this.checked) {
                    billingFields.style.display = 'none';
                    billingInputs.forEach(input => {
                        input.required = false;
                    });
                } else {
                    billingFields.style.display = 'block';
                    billingInputs.forEach(input => {
                        input.required = true;
                    });
                }
            });


            // Funciones para grupos progresivos
            function initializeProgressiveGroups(pageId) {
                const page = document.getElementById(pageId);
                if (!page) return;

                const groups = page.querySelectorAll('.progressive-group');
                let hasVisitedBefore = false;
                
                // Verificar si la página ya ha sido visitada (tiene grupos completados)
                groups.forEach(group => {
                    if (group.classList.contains('completed')) {
                        hasVisitedBefore = true;
                    }
                });
                
                groups.forEach((group, index) => {
                    if (hasVisitedBefore) {
                        // Si ya se visitó la página, mostrar todos los grupos pero collapsed los completados
                        group.style.display = 'block';
                        if (group.classList.contains('completed')) {
                            group.classList.add('collapsed');
                        }
                    } else {
                        // Primera visita: comportamiento progresivo normal
                        if (index === 0) {
                            group.style.display = 'block';
                            group.classList.remove('completed', 'collapsed');
                        } else {
                            group.style.display = 'none';
                        }
                    }

                    // Agregar event listeners para detectar cambios en los campos
                    const inputs = group.querySelectorAll('input, select, textarea');
                    inputs.forEach(input => {
                        // Remover listeners existentes para evitar duplicados
                        input.removeEventListener('input', () => checkGroupCompletion(group));
                        input.removeEventListener('change', () => checkGroupCompletion(group));
                        
                        // Agregar nuevos listeners
                        input.addEventListener('input', () => checkGroupCompletion(group));
                        input.addEventListener('change', () => checkGroupCompletion(group));
                    });
                });
            }
            
            function showAllGroupsInPage(pageId) {
                const page = document.getElementById(pageId);
                if (!page) return;
                
                const groups = page.querySelectorAll('.progressive-group');
                groups.forEach(group => {
                    group.style.display = 'block';
                    // Mantener el estado collapsed solo para grupos completados
                    if (group.classList.contains('completed')) {
                        group.classList.add('collapsed');
                    } else {
                        group.classList.remove('collapsed');
                    }
                });
            }

            function toggleGroup(groupName) {
                const group = document.querySelector(`[data-group="${groupName}"]`);
                if (!group) return;

                // Permitir expandir/colapsar cualquier grupo (completado o no)
                group.classList.toggle('collapsed');
                
                // Si el grupo está siendo expandido y no está completado, 
                // mantenerlo visible para que el usuario pueda trabajar en él
                if (!group.classList.contains('collapsed') && !group.classList.contains('completed')) {
                    // Asegurarse de que todos los grupos anteriores estén visibles también
                    const allGroups = group.parentElement.querySelectorAll('.progressive-group');
                    const currentIndex = Array.from(allGroups).indexOf(group);
                    
                    for (let i = 0; i <= currentIndex; i++) {
                        allGroups[i].style.display = 'block';
                    }
                }
            }

            function checkGroupCompletion(group) {
                // Prevenir saltos automáticos guardando la posición actual del scroll
                const currentScrollPosition = window.pageYOffset;
                
                const requiredInputs = group.querySelectorAll('input[required], select[required], textarea[required]');
                let allFilled = true;
                let hasProgress = false;

                requiredInputs.forEach(input => {
                    if (input.type === 'checkbox') {
                        if (!input.checked) allFilled = false;
                        else hasProgress = true;
                    } else if (input.type === 'file') {
                        if (!input.files || input.files.length === 0) {
                            allFilled = false;
                        } else {
                            hasProgress = true;
                        }
                    } else if (!input.value.trim()) {
                        allFilled = false;
                    } else {
                        hasProgress = true;
                    }
                });

                // Verificación especial para grupos específicos
                if (group.dataset.group === 'billing') {
                    const sameAddressCheckbox = document.getElementById('same_address');
                    if (sameAddressCheckbox && sameAddressCheckbox.checked) {
                        allFilled = true;
                        hasProgress = true;
                    }
                } else if (group.dataset.group === 'documents') {
                    // Para documentos, verificar que TODOS los documentos requeridos estén subidos
                    const requiredInputs = group.querySelectorAll('input[type="file"][required]');
                    const allRequiredUploaded = Array.from(requiredInputs).every(input => 
                        input.files && input.files.length > 0
                    );
                    
                    // Debug para documentos
                    console.log(`Documentos requeridos: ${requiredInputs.length}`);
                    requiredInputs.forEach(input => {
                        console.log(`- ${input.id}: ${input.files && input.files.length > 0 ? 'Subido' : 'No subido'}`);
                    });
                    
                    if (requiredInputs.length > 0) {
                        allFilled = allRequiredUploaded;
                        // Verificar si hay al menos un archivo subido para mostrar progreso
                        const anyFileUploaded = Array.from(group.querySelectorAll('input[type="file"]')).some(input => 
                            input.files && input.files.length > 0
                        );
                        hasProgress = anyFileUploaded;
                    } else {
                        // No hay campos requeridos, considerar completado
                        allFilled = true;
                        const anyFileUploaded = Array.from(group.querySelectorAll('input[type="file"]')).some(input => 
                            input.files && input.files.length > 0
                        );
                        hasProgress = anyFileUploaded;
                    }
                } else if (group.dataset.group === 'options') {
                    // Para el grupo de opciones, verificar que las opciones visibles tengan selecciones
                    // Primero verificar si el grupo entero está visible
                    if (group.style.display === 'none') {
                        // Si el grupo está oculto, está completado automáticamente
                        allFilled = true;
                        hasProgress = false;
                    } else {
                        const visibleSections = group.querySelectorAll('div[id$="-section"]:not([style*="display: none"])');
                        let optionsFilled = true;
                        let optionsProgress = false;
                        
                        visibleSections.forEach(section => {
                            const radioButtons = section.querySelectorAll('input[type="radio"]');
                            const checkboxes = section.querySelectorAll('input[type="checkbox"]');
                            
                            if (radioButtons.length > 0) {
                                const hasSelection = Array.from(radioButtons).some(radio => radio.checked);
                                if (!hasSelection) optionsFilled = false;
                                else optionsProgress = true;
                            }
                            
                            // Los checkboxes son siempre opcionales
                            if (checkboxes.length > 0) {
                                const hasSelection = Array.from(checkboxes).some(checkbox => checkbox.checked);
                                if (hasSelection) optionsProgress = true;
                            }
                        });
                        
                        // Si no hay secciones visibles, el grupo está completado
                        if (visibleSections.length === 0) {
                            allFilled = true;
                            hasProgress = false;
                        } else {
                            allFilled = optionsFilled;
                            hasProgress = optionsProgress;
                        }
                    }
                } else if (group.dataset.group === 'authorization') {
                    // Para autorización, verificar firma y términos
                    const termsCheckbox = group.querySelector('input[name="terms_accept"]');
                    const signaturePad = window.signaturePad;
                    
                    const termsAccepted = termsCheckbox && termsCheckbox.checked;
                    const signaturePresent = signaturePad && !signaturePad.isEmpty();
                    
                    allFilled = termsAccepted && signaturePresent;
                    hasProgress = termsAccepted || signaturePresent;
                }

                // Debug: Log del estado del grupo
                console.log(`Grupo ${group.id}: allFilled=${allFilled}, hasProgress=${hasProgress}`);
                
                // Actualizar estado visual del grupo
                const statusIcon = group.querySelector('.group-status');
                if (!statusIcon) return;
                
                if (allFilled && !group.classList.contains('completed')) {
                    // Marcar grupo como completado
                    group.classList.add('completed');
                    group.classList.remove('in-progress');
                    
                    // Colapsar el grupo completado después de un breve delay
                    setTimeout(() => {
                        if (group.classList.contains('completed')) {
                            group.classList.add('collapsed');
                            // Verificar orden antes de mostrar siguiente grupo
                            ensureGroupOrder();
                            showNextGroup(group);
                        }
                    }, 800);
                } else if (!allFilled && group.classList.contains('completed')) {
                    // Si ya no está completo, desmarcar
                    group.classList.remove('completed', 'collapsed');
                    if (hasProgress) {
                        group.classList.add('in-progress');
                    } else {
                        group.classList.remove('in-progress');
                    }
                    hideNextGroups(group);
                    // Asegurar orden después de ocultar grupos siguientes
                    ensureGroupOrder();
                } else if (!allFilled && hasProgress && !group.classList.contains('completed')) {
                    // Mostrar progreso parcial
                    group.classList.add('in-progress');
                } else if (!allFilled && !hasProgress && !group.classList.contains('completed')) {
                    // Estado inicial o sin progreso
                    group.classList.remove('in-progress');
                }
                
                // Restaurar posición del scroll para evitar saltos automáticos problemáticos
                if (Math.abs(window.pageYOffset - currentScrollPosition) > 100) {
                    window.scrollTo(0, currentScrollPosition);
                }
            }

            function showNextGroup(currentGroup) {
                const allGroups = currentGroup.parentElement.querySelectorAll('.progressive-group');
                const currentIndex = Array.from(allGroups).indexOf(currentGroup);
                const nextGroup = allGroups[currentIndex + 1];

                if (nextGroup) {
                    // Asegurar que solo se muestre el siguiente grupo en orden
                    const nextGroupId = nextGroup.id;
                    const expectedOrder = ['group-basic-data', 'group-tramite-options', 'group-billing', 'group-documents', 'group-authorization'];
                    
                    // Verificar que el grupo siguiente está en el orden esperado
                    if (expectedOrder.includes(nextGroupId)) {
                        nextGroup.style.display = 'block';
                        // Scroll más inteligente - solo si el grupo no está ya visible
                        setTimeout(() => {
                            const rect = nextGroup.getBoundingClientRect();
                            const windowHeight = window.innerHeight;
                            const isVisible = rect.top >= 0 && rect.bottom <= windowHeight;
                            
                            if (!isVisible) {
                                nextGroup.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            }
                        }, 300);
                    }
                }
            }

            function hideNextGroups(currentGroup) {
                const allGroups = currentGroup.parentElement.querySelectorAll('.progressive-group');
                const currentIndex = Array.from(allGroups).indexOf(currentGroup);
                
                // Ocultar todos los grupos posteriores al actual
                for (let i = currentIndex + 1; i < allGroups.length; i++) {
                    allGroups[i].style.display = 'none';
                    allGroups[i].classList.remove('completed', 'collapsed', 'in-progress');
                }
            }

            // Función para asegurar el orden correcto de los grupos
            function ensureGroupOrder() {
                const expectedOrder = ['group-basic-data', 'group-tramite-options', 'group-billing', 'group-documents', 'group-authorization'];
                const currentPage = document.querySelector('.form-page:not(.hidden)');
                if (!currentPage) return;
                
                const allGroups = currentPage.querySelectorAll('.progressive-group');
                let lastCompletedIndex = -1;
                
                // Encontrar el último grupo completado
                allGroups.forEach((group, index) => {
                    if (group.classList.contains('completed')) {
                        lastCompletedIndex = index;
                    }
                });
                
                // Asegurar progresión lógica: mostrar solo hasta el siguiente grupo después del último completado
                allGroups.forEach((group, index) => {
                    if (index === 0) {
                        // El primer grupo siempre debe estar visible
                        group.style.display = 'block';
                    } else if (index <= lastCompletedIndex + 1) {
                        // Mostrar grupos hasta el siguiente después del último completado
                        group.style.display = 'block';
                    } else {
                        // Ocultar grupos posteriores
                        group.style.display = 'none';
                        group.classList.remove('completed', 'collapsed', 'in-progress');
                    }
                });
                
                // Log para debug
                console.log(`Último grupo completado: ${lastCompletedIndex}, Grupos visibles: ${lastCompletedIndex + 2}`);
            }

            // Redefinir la generación de campos específicos con soporte para grupos progresivos

            // Mejorar el manejo del checkbox "same_address" y otros eventos
            document.addEventListener('change', function(e) {
                if (e.target.id === 'same_address') {
                    const billingFields = document.getElementById('billing-fields');
                    const billingGroup = document.getElementById('group-billing');
                    
                    if (e.target.checked) {
                        billingFields.style.display = 'none';
                        // Limpiar los campos de facturación ya que no son necesarios
                        billingFields.querySelectorAll('input').forEach(input => {
                            input.removeAttribute('required');
                            input.value = '';
                        });
                    } else {
                        billingFields.style.display = 'block';
                        // Restaurar required en los campos de facturación
                        billingFields.querySelectorAll('input').forEach(input => {
                            input.setAttribute('required', 'required');
                        });
                    }
                    
                    // Verificar el estado del grupo después del cambio
                    if (billingGroup) {
                        checkGroupCompletion(billingGroup);
                    }
                }
                
                // Manejar cambios en checkbox de términos
                if (e.target.name === 'terms_accept') {
                    const authGroup = document.getElementById('group-authorization');
                    if (authGroup) {
                        checkGroupCompletion(authGroup);
                    }
                }
            });

            // Funciones globales para uso en onClick HTML
            window.toggleGroup = toggleGroup;
            
            // Función de debugging para desarrollo (puede removerse en producción)
            window.debugFormState = function() {
                console.log('=== ESTADO DEL FORMULARIO ===');
                console.log('Página actual:', currentPage);
                console.log('Trámite seleccionado:', selectedTramite);
                console.log('Precio actual:', currentPrice);
                
                const groups = document.querySelectorAll('.progressive-group');
                groups.forEach(group => {
                    const groupName = group.dataset.group;
                    const isVisible = group.style.display !== 'none';
                    const isCompleted = group.classList.contains('completed');
                    const isCollapsed = group.classList.contains('collapsed');
                    console.log(`Grupo ${groupName}:`, { isVisible, isCompleted, isCollapsed });
                });
                
                if (window.signaturePad) {
                    console.log('Firma presente:', !window.signaturePad.isEmpty());
                }
            };
            
            // Lógica del modal de pago moderno
            document.getElementById('proceed-to-payment')?.addEventListener('click', function() {
                console.log('Botón de pago clickeado');

                // 1. Validar que se haya seleccionado un trámite
                if (!selectedTramite) {
                    alert('Por favor, selecciona primero un tipo de trámite para continuar.');
                    return;
                }

                // 2. Validar datos básicos requeridos
                const basicDataValidation = validateBasicData();
                if (!basicDataValidation.isValid) {
                    const missingFieldsList = basicDataValidation.missingFields.join('\n• ');
                    alert(`Por favor, completa los siguientes campos requeridos:\n\n• ${missingFieldsList}`);
                    return;
                }

                // 3. Validar términos y condiciones (checkbox único)
                const termsCheckbox = document.querySelector('input[name="terms_accept_pago"]');
                console.log('Checkbox de términos encontrado:', termsCheckbox);
                console.log('Checkbox marcado:', termsCheckbox ? termsCheckbox.checked : 'no existe');

                if (!termsCheckbox || !termsCheckbox.checked) {
                    // Mostrar alerta simple y efectiva
                    alert('Por favor, acepta los términos y condiciones y la política de privacidad para continuar.');

                    // Efecto visual simple en el checkmark
                    const checkmark = document.querySelector('.checkmark');
                    if (checkmark) {
                        const originalBorder = checkmark.style.borderColor;
                        checkmark.style.borderColor = '#dc3545';
                        checkmark.style.borderWidth = '3px';

                        // Restaurar después de 3 segundos
                        setTimeout(() => {
                            checkmark.style.borderColor = '#ddd';
                            checkmark.style.borderWidth = '2px';
                        }, 3000);
                    }
                    return;
                }

                console.log('Todas las validaciones pasadas. Intentando abrir modal...');
                openPaymentModal();
            });
            
            
            // Event listener para limpiar errores del checkbox cuando se selecciona
            document.querySelector('input[name="terms_accept_pago"]')?.addEventListener('change', function() {
                if (this.checked) {
                    const checkmark = document.querySelector('.checkmark');
                    if (checkmark) {
                        // Limpiar estilos de error
                        checkmark.style.borderColor = '#ddd';
                        checkmark.style.borderWidth = '2px';
                    }
                }
            });
            
            // Event listeners para cerrar modal
            document.getElementById('close-payment-modal')?.addEventListener('click', closePaymentModal);
            
            // Cerrar modal haciendo clic en el backdrop
            document.querySelector('.modal-backdrop')?.addEventListener('click', closePaymentModal);
            
            // Función para validar datos básicos requeridos
            function validateBasicData() {
                const requiredFields = [
                    { id: 'customer_name', name: 'Nombre y apellidos' },
                    { id: 'customer_dni', name: 'DNI o pasaporte' },
                    { id: 'customer_email', name: 'Correo electrónico' },
                    { id: 'customer_phone', name: 'Teléfono' },
                    { id: 'boat_port', name: 'Puerto de amarre' }
                ];

                const missingFields = [];

                requiredFields.forEach(field => {
                    const element = document.getElementById(field.id);
                    if (!element || !element.value.trim()) {
                        missingFields.push(field.name);
                    }
                });

                return {
                    isValid: missingFields.length === 0,
                    missingFields: missingFields
                };
            }

            // Función para abrir el modal de pago
            function openPaymentModal() {
                console.log('openPaymentModal() llamada');
                const modal = document.getElementById('payment-modal');
                const config = tramitesConfig[selectedTramite];

                console.log('Modal encontrado:', modal);
                console.log('selectedTramite:', selectedTramite);
                console.log('Config:', config);
                console.log('currentPrice:', currentPrice);

                if (!modal) {
                    console.error('Modal no encontrado');
                    return;
                }

                if (!config) {
                    console.error('Config no encontrada para tramite:', selectedTramite);
                    alert('Por favor, selecciona primero un tipo de trámite para continuar.');
                    return;
                }

                if (currentPrice <= 0) {
                    console.error('Precio no válido:', currentPrice);
                    alert('Error al calcular el precio. Por favor, selecciona nuevamente el trámite.');
                    return;
                }

                // Actualizar información del modal
                document.getElementById('modal-service-name').textContent = config.title;
                document.getElementById('modal-total-amount').textContent = currentPrice.toFixed(2) + ' €';

                console.log('Removiendo clase hidden del modal...');
                // Mostrar modal con animación
                modal.classList.remove('hidden');
                document.body.style.overflow = 'hidden'; // Prevenir scroll del fondo
                console.log('Modal debería estar visible ahora');
                
                // Inicializar Stripe en el modal si no está ya inicializado
                if (!stripe) {
                    initializeStripe().then(() => {
                        console.log('Stripe inicializado en modal');
                    }).catch(error => {
                        console.error('Error al inicializar Stripe:', error);
                        showPaymentError('Error al cargar el sistema de pago. Por favor, recarga la página.');
                    });
                }
            }
            
            // Función para cerrar el modal de pago
            function closePaymentModal() {
                const modal = document.getElementById('payment-modal');
                if (!modal) return;
                
                modal.classList.add('hidden');
                document.body.style.overflow = ''; // Restaurar scroll
                
                // Limpiar mensajes de error
                const paymentMessage = document.getElementById('payment-message');
                if (paymentMessage) {
                    paymentMessage.classList.add('hidden');
                    paymentMessage.textContent = '';
                }
            }
            
            // Función para mostrar errores de pago en el modal
            function showPaymentError(message) {
                const paymentMessage = document.getElementById('payment-message');
                if (paymentMessage) {
                    paymentMessage.textContent = message;
                    paymentMessage.classList.remove('hidden', 'success');
                    paymentMessage.classList.add('error');
                }
            }
            
            // Función para mostrar éxito de pago en el modal
            function showPaymentSuccess(message) {
                const paymentMessage = document.getElementById('payment-message');
                if (paymentMessage) {
                    paymentMessage.textContent = message;
                    paymentMessage.classList.remove('hidden', 'error');
                    paymentMessage.classList.add('success');
                }
            }
            
            
            // Función helper para calcular costos adicionales
            function calculateAdditionalCosts() {
                let additionalCosts = 0;
                
                // Radio buttons
                const radioButtons = document.querySelectorAll('#group-tramite-options input[type="radio"]:checked');
                radioButtons.forEach(radio => {
                    additionalCosts += parseFloat(radio.getAttribute('data-price')) || 0;
                });
                
                // Checkboxes
                const checkboxes = document.querySelectorAll('#group-tramite-options input[type="checkbox"]:checked');
                checkboxes.forEach(checkbox => {
                    additionalCosts += parseFloat(checkbox.getAttribute('data-price')) || 0;
                });
                
                return additionalCosts;
            }

            // Mensaje de bienvenida para debugging
            console.log('Formulario de registro polaco inicializado. Use debugFormState() para ver el estado.');

            // Inicializar formulario
            updateForm();

            // ========================================
            // AUTO-RELLENADO PARA ADMINISTRADORES
            // ========================================
            <?php if (current_user_can('administrator')): ?>
            const adminAutofillBtn = document.getElementById('admin-autofill-btn');
            if (adminAutofillBtn) {
                adminAutofillBtn.addEventListener('click', async function() {
                    console.log('🚀 Iniciando auto-rellenado del formulario polaco...');

                    // PASO 1: Seleccionar trámite "registro"
                    const registroCard = document.querySelector('.tramite-card[data-tramite="registro"]');
                    if (registroCard) {
                        registroCard.click();
                        console.log('✓ Trámite "registro" seleccionado');
                    }

                    // Esperar a que se active el botón de continuar
                    await new Promise(resolve => setTimeout(resolve, 300));

                    const continueBtn = document.getElementById('continue-from-portada');
                    if (continueBtn) {
                        continueBtn.click();
                        console.log('✓ Continuando desde portada...');
                    }

                    // Esperar a que se muestren los campos
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

                    // Marcar grupo como completo
                    const basicGroup = document.getElementById('group-basic-data');
                    if (basicGroup) {
                        basicGroup.classList.add('completed');
                        const nextGroupBtn = basicGroup.querySelector('.group-next-btn');
                        if (nextGroupBtn) {
                            await new Promise(resolve => setTimeout(resolve, 300));
                            nextGroupBtn.click();
                            console.log('✓ Avanzando al siguiente grupo...');
                        }
                    }

                    await new Promise(resolve => setTimeout(resolve, 500));

                    // PASO 3: Rellenar datos de embarcación
                    const boatName = document.getElementById('boat_name');
                    const boatLength = document.getElementById('boat_length');
                    const boatHullNumber = document.getElementById('boat_hull_number');

                    if (boatName) boatName.value = 'SEA SPIRIT';
                    if (boatLength) boatLength.value = '8.5';
                    if (boatHullNumber) boatHullNumber.value = 'ESP-123-AB-2020';

                    console.log('✓ Datos de embarcación rellenados');

                    // Marcar grupo como completo
                    const boatGroup = document.getElementById('group-boat-data');
                    if (boatGroup) {
                        boatGroup.classList.add('completed');
                        const nextGroupBtn = boatGroup.querySelector('.group-next-btn');
                        if (nextGroupBtn) {
                            await new Promise(resolve => setTimeout(resolve, 300));
                            nextGroupBtn.click();
                            console.log('✓ Avanzando al siguiente grupo...');
                        }
                    }

                    await new Promise(resolve => setTimeout(resolve, 500));

                    // PASO 4: Rellenar datos del vendedor anterior
                    const prevOwnerName = document.getElementById('previous_owner_name');
                    const prevOwnerAddress = document.getElementById('previous_owner_address');
                    const saleDate = document.getElementById('sale_date');
                    const salePrice = document.getElementById('sale_price');

                    if (prevOwnerName) prevOwnerName.value = 'Pedro García';
                    if (prevOwnerAddress) prevOwnerAddress.value = 'Calle Mayor 123, Madrid';
                    if (saleDate) saleDate.value = '2024-01-15';
                    if (salePrice) salePrice.value = '35000';

                    console.log('✓ Datos del vendedor rellenados');

                    // Marcar grupo como completo y avanzar a opciones
                    const sellerGroup = document.getElementById('group-seller-data');
                    if (sellerGroup) {
                        sellerGroup.classList.add('completed');
                        const nextGroupBtn = sellerGroup.querySelector('.group-next-btn');
                        if (nextGroupBtn) {
                            await new Promise(resolve => setTimeout(resolve, 300));
                            nextGroupBtn.click();
                            console.log('✓ Avanzando a opciones...');
                        }
                    }

                    await new Promise(resolve => setTimeout(resolve, 500));

                    // PASO 5: Seleccionar opción de entrega normal (no express)
                    const deliveryNormal = document.querySelector('input[name="delivery_option"][value="normal"]');
                    if (deliveryNormal) {
                        deliveryNormal.checked = true;
                        deliveryNormal.dispatchEvent(new Event('change', { bubbles: true }));
                        console.log('✓ Opción de entrega normal seleccionada');
                    }

                    // PASO 6: No seleccionar MMSI (opcional)
                    const mmsiNone = document.querySelector('input[name="mmsi_option"][value="none"]');
                    if (mmsiNone) {
                        mmsiNone.checked = true;
                        mmsiNone.dispatchEvent(new Event('change', { bubbles: true }));
                        console.log('✓ Sin MMSI seleccionado');
                    }

                    await new Promise(resolve => setTimeout(resolve, 300));

                    // Marcar grupo de opciones como completo
                    const optionsGroup = document.getElementById('group-tramite-options');
                    if (optionsGroup) {
                        optionsGroup.classList.add('completed');
                        const continueBtn = document.getElementById('continue-to-documents');
                        if (continueBtn) {
                            await new Promise(resolve => setTimeout(resolve, 300));
                            continueBtn.click();
                            console.log('✓ Avanzando a documentación...');
                        }
                    }

                    await new Promise(resolve => setTimeout(resolve, 500));

                    // PASO 7: Remover required de documentos y avanzar
                    // (no podemos auto-subir archivos por seguridad del navegador)
                    const fileInputs = document.querySelectorAll('input[type="file"][required]');
                    fileInputs.forEach(input => {
                        input.removeAttribute('required');
                        console.log('✓ Required removido de:', input.id);
                    });

                    // Marcar grupo de documentos como completo para poder avanzar
                    const docsGroup = document.getElementById('group-documents');
                    if (docsGroup) {
                        docsGroup.classList.add('completed');
                    }

                    // Hacer clic en "Siguiente" para avanzar
                    const nextBtn = document.getElementById('nextButton');
                    if (nextBtn) {
                        nextBtn.click();
                        console.log('✓ Avanzando desde documentación...');
                    }

                    await new Promise(resolve => setTimeout(resolve, 500));

                    // PASO 8: Aceptar términos y firmar
                    const termsAccept = document.querySelector('input[name="terms_accept"]');
                    if (termsAccept) {
                        termsAccept.checked = true;
                        console.log('✓ Términos aceptados');
                    }

                    // Marcar grupo de autorización como completo
                    const authGroup = document.getElementById('group-authorization');
                    if (authGroup) {
                        authGroup.classList.add('completed');
                    }

                    await new Promise(resolve => setTimeout(resolve, 300));

                    // Inicializar SignaturePad si existe
                    const signaturePad = document.getElementById('signature-pad');
                    if (signaturePad && typeof SignaturePad !== 'undefined') {
                        if (!window.signaturePadInstance) {
                            window.signaturePadInstance = new SignaturePad(signaturePad);
                        }
                        // Dibujar una firma simple
                        const ctx = signaturePad.getContext('2d');
                        ctx.beginPath();
                        ctx.moveTo(50, 80);
                        ctx.bezierCurveTo(75, 60, 100, 60, 125, 80);
                        ctx.bezierCurveTo(150, 100, 175, 100, 200, 80);
                        ctx.lineWidth = 2;
                        ctx.strokeStyle = '#000';
                        ctx.stroke();
                        console.log('✓ Firma simulada');
                    }

                    await new Promise(resolve => setTimeout(resolve, 500));

                    // PASO 9: Avanzar a la página de pago
                    const nextBtnFromAuth = document.getElementById('nextButton');
                    if (nextBtnFromAuth) {
                        nextBtnFromAuth.click();
                        console.log('✓ Avanzando a página de pago...');
                    }

                    await new Promise(resolve => setTimeout(resolve, 500));

                    // Dirección de facturación
                    const sameAddress = document.getElementById('same_address');
                    if (sameAddress) {
                        sameAddress.checked = true;
                        sameAddress.dispatchEvent(new Event('change', { bubbles: true }));
                        console.log('✓ Misma dirección de facturación');
                    }

                    await new Promise(resolve => setTimeout(resolve, 300));

                    // Aceptar términos de pago
                    const termsAcceptPago = document.querySelector('input[name="terms_accept_pago"]');
                    if (termsAcceptPago) {
                        termsAcceptPago.checked = true;
                        termsAcceptPago.dispatchEvent(new Event('change', { bubbles: true }));
                        console.log('✓ Términos de pago aceptados');
                    }

                    console.log('✅ FORMULARIO AUTO-RELLENADO COMPLETADO');
                    console.log('📋 Ahora puedes revisar el resumen y proceder al pago manualmente');
                    console.log('💳 Usa la tarjeta de prueba: 4242 4242 4242 4242');

                    alert('✅ Formulario auto-rellenado!\n\n' +
                          '📋 Revisa el resumen y haz clic en "Proceder al Pago"\n' +
                          '💳 Tarjeta de prueba Stripe:\n' +
                          '    • Número: 4242 4242 4242 4242\n' +
                          '    • Fecha: Cualquier fecha futura\n' +
                          '    • CVC: Cualquier 3 dígitos\n' +
                          '    • Código postal: Cualquiera\n\n' +
                          '🚀 El formulario se enviará al webhook TRAMITFY');
                });
            }
            <?php endif; ?>
        });
    </script>

    <?php
    return ob_get_clean();
}
add_shortcode('polish_registration_form', 'polish_registration_form_shortcode');

/**
 * Endpoint para crear el Payment Intent para registro polaco
 */
add_action('wp_ajax_create_payment_intent_polish_registration', 'create_payment_intent_polish_registration');
add_action('wp_ajax_nopriv_create_payment_intent_polish_registration', 'create_payment_intent_polish_registration');

function create_payment_intent_polish_registration() {
    global $stripe_secret_key;

    header('Content-Type: application/json');

    try {
        error_log('=== REGISTRO POLACA PAYMENT INTENT ===');
        error_log('STRIPE MODE: ' . STRIPE_MODE);
        error_log('Using Stripe key starting with: ' . substr($stripe_secret_key, 0, 25));

        \Stripe\Stripe::setApiKey($stripe_secret_key);

        $currentKey = \Stripe\Stripe::getApiKey();
        error_log('Stripe API Key confirmed: ' . substr($currentKey, 0, 25));

        $amount = isset($_POST['amount']) ? intval($_POST['amount']) : 0;
        error_log('Amount to charge: ' . $amount . ' cents');

        $paymentIntent = \Stripe\PaymentIntent::create([
            'amount' => $amount,
            'currency' => 'eur',
            'payment_method_types' => ['card'],
            'metadata' => [
                'form_type' => 'registro_polaca',
                'stripe_mode' => STRIPE_MODE
            ]
        ]);

        error_log('Payment Intent created: ' . $paymentIntent->id);

        echo json_encode([
            'clientSecret' => $paymentIntent->client_secret,
        ]);
    } catch (Exception $e) {
        error_log('ERROR creating payment intent: ' . $e->getMessage());
        echo json_encode([
            'error' => $e->getMessage(),
        ]);
    }

    wp_die();
}

/**
 * Endpoint para validar el cupón para registro polaco
 */
add_action('wp_ajax_validate_coupon_code_polish_registration', 'validate_coupon_code_polish_registration');
add_action('wp_ajax_nopriv_validate_coupon_code_polish_registration', 'validate_coupon_code_polish_registration');

function validate_coupon_code_polish_registration() {
    $valid_coupons = array(
        'DESCUENTO10' => 10,
        'DESCUENTO20' => 20,
        'VERANO15'    => 15,
        'BLACK50'     => 50,
        'POLACA10'    => 10,
    );

    $coupon = isset($_POST['coupon']) ? sanitize_text_field($_POST['coupon']) : '';
    $coupon_upper = strtoupper($coupon);

    if (isset($valid_coupons[$coupon_upper])) {
        $discount_percent = $valid_coupons[$coupon_upper];
        wp_send_json_success(['discount_percent' => $discount_percent]);
    } else {
        wp_send_json_error('Cupón inválido');
    }
    wp_die();
}

/**
 * Función para manejar el envío final del formulario de registro polaco
 */
add_action('wp_ajax_submit_form_polish_registration', 'submit_form_polish_registration');
add_action('wp_ajax_nopriv_submit_form_polish_registration', 'submit_form_polish_registration');

function submit_form_polish_registration() {
    // Validar y procesar los datos enviados
    $customer_name = sanitize_text_field($_POST['customer_name']);
    $customer_dni = sanitize_text_field($_POST['customer_dni']);
    $customer_email = sanitize_email($_POST['customer_email']);
    $customer_phone = sanitize_text_field($_POST['customer_phone']);
    $selected_tramite = sanitize_text_field($_POST['selected_tramite']);
    $coupon_used = isset($_POST['coupon_used']) ? sanitize_text_field($_POST['coupon_used']) : '';
    $signature = $_POST['signature'];

    // Recoger datos específicos del trámite
    $tramite_data = array();
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'boat_') === 0 || strpos($key, 'previous_') === 0 || strpos($key, 'radio_') === 0 || strpos($key, 'sale_') === 0 || strpos($key, 'mmsi_') === 0) {
            $tramite_data[$key] = sanitize_text_field($value);
        }
    }

    // Recoger nuevas opciones
    $options_data = array();
    $options_data['boat_size'] = isset($_POST['boat_size']) ? sanitize_text_field($_POST['boat_size']) : '';
    $options_data['delivery_option'] = isset($_POST['delivery_option']) ? sanitize_text_field($_POST['delivery_option']) : '';
    $options_data['mmsi_option'] = isset($_POST['mmsi_option']) ? sanitize_text_field($_POST['mmsi_option']) : '';
    
    // Servicios extra como array
    $extra_services = array();
    if (isset($_POST['extra_services']) && is_array($_POST['extra_services'])) {
        foreach ($_POST['extra_services'] as $service) {
            $extra_services[] = sanitize_text_field($service);
        }
    }
    $options_data['extra_services'] = $extra_services;

    // Procesar la firma
    $signature_data = str_replace('data:image/png;base64,', '', $signature);
    $signature_data = base64_decode($signature_data);

    $upload_dir = wp_upload_dir();
    $signature_image_name = 'signature_polaca_' . time() . '.png';
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

    $tramite_title = get_polish_tramite_description($selected_tramite);
    $pdf->Cell(0, 10, utf8_decode('Autorización para ' . $tramite_title), 0, 1, 'C');
    $pdf->Ln(10);

    $texto = "Yo, $customer_name, con DNI/Pasaporte $customer_dni, autorizo a Tramitfy S.L. (CIF B55388557) a realizar en mi nombre los trámites necesarios para: $tramite_title.";
    $pdf->MultiCell(0, 10, utf8_decode($texto), 0, 'J');
    $pdf->Ln(10);

    $pdf->Cell(0, 10, utf8_decode('Firma:'), 0, 1);
    $pdf->Image($signature_image_path, null, null, 50, 30);

    $authorization_pdf_name = 'autorizacion_polaca_' . time() . '.pdf';
    $authorization_pdf_path = $upload_dir['path'] . '/' . $authorization_pdf_name;
    $pdf->Output('F', $authorization_pdf_path);
    
    // Generar PDF de factura
    $same_address = isset($_POST['same_address']);
    
    if ($same_address) {
        $billing_address = $customer_name;
        $billing_city = '';
        $billing_postal_code = '';
        $billing_province = '';
    } else {
        $billing_address = sanitize_text_field($_POST['billing_address']);
        $billing_city = sanitize_text_field($_POST['billing_city']);
        $billing_postal_code = sanitize_text_field($_POST['billing_postal_code']);
        $billing_province = sanitize_text_field($_POST['billing_province']);
    }
    
    $invoice_pdf_path = generate_polish_invoice_pdf(
        $customer_name, 
        $customer_dni, 
        $customer_email, 
        $customer_phone, 
        $selected_tramite, 
        array_merge($tramite_data, $options_data), 
        '', 
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

    $admin_email = get_option('admin_email');
    $subject_admin = 'Nuevo formulario de registro polaco enviado';
    $tramite_description = get_polish_tramite_description($selected_tramite);

    // Correo al administrador
    $message_admin = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body {
                font-family: Arial, sans-serif;
                line-height: 1.6;
                color: #333;
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
                background-color: var(--primary-color);
                color: #ffffff;
                text-align: left;
                font-size: 12px;
                border-radius: 8px;
            }
            a {
                color: #FFFFFF;
                text-decoration: none;
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
                <div style="color: var(--primary-color); font-weight: bold; font-size: 1.5em; margin: 0.83em 0;">Nuevo Formulario de Registro Polaco Enviado</div>
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
                        <th>DNI/Pasaporte:</th>
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
                        <th>Tipo de Trámite:</th>
                        <td>' . htmlspecialchars($tramite_description) . '</td>
                    </tr>
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

    if (!empty($tramite_data)) {
        $message_admin .= '
                <h3>Datos Específicos del Trámite:</h3>
                <table class="details-table">';
        foreach ($tramite_data as $key => $value) {
            $message_admin .= '
                    <tr>
                        <th>' . htmlspecialchars($key) . ':</th>
                        <td>' . htmlspecialchars($value) . '</td>
                    </tr>';
        }
        $message_admin .= '</table>';
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
    $subject_client = 'Confirmación de su solicitud de registro bajo bandera polaca';
    $message_client = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body {
                font-family: Arial, sans-serif;
                line-height: 1.6;
                color: #333;
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
                background-color: var(--primary-color);
                color: #ffffff;
                text-align: left;
                font-size: 12px;
                border-radius: 8px;
            }
            a {
                color: #FFFFFF;
                text-decoration: none;
            }
            .invoice-box {
                margin-top: 20px;
                padding: 15px;
                border: 1px solid var(--secondary-color);
                background-color: #f8f9fa;
                border-radius: 5px;
                color: #155724;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <img src="https://www.tramitfy.es/wp-content/uploads/LOGO.png" alt="Tramitfy Logo">
                <div style="color: var(--primary-color); font-weight: bold; font-size: 1.5em; margin: 0.83em 0;">Confirmación de su solicitud de registro polaco</div>
            </div>
            <div class="content">
                <p>Estimado/a ' . htmlspecialchars($customer_name) . ',</p>
                <p>Hemos recibido su solicitud para: <strong>' . htmlspecialchars($tramite_description) . '</strong>.</p>
                <p>Procesaremos su documentación y le enviaremos el registro provisional en un plazo de 10-15 días laborables.</p>
                
                <div class="invoice-box">
                    <p><strong>Factura adjunta</strong></p>
                    <p>Se adjunta a este correo la factura correspondiente a su pago. Por favor, conserve este documento para sus registros.</p>
                </div>
                
                <p>Gracias por confiar en nosotros para el registro de su embarcación bajo bandera polaca.</p>
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

    // NO enviamos emails desde PHP - los enviará la API Node.js

    /*******************************************************/
    /*** TRAMITFY API WEBHOOK - Enviar datos al sistema ***/
    /*******************************************************/

    // Preparar datos del trámite
    $prices = get_polish_tramite_prices();
    $current_price = $prices[$selected_tramite];
    $final_amount = $current_price['total'];

    // Aplicar descuento si hay cupón
    if (!empty($coupon_used)) {
        $valid_coupons = [
            'DESCUENTO10' => 10, 'DESCUENTO20' => 20, 'VERANO15' => 15,
            'BLACK50' => 50, 'POLACA10' => 10
        ];
        $coupon_upper = strtoupper($coupon_used);
        if (isset($valid_coupons[$coupon_upper])) {
            $discount_percent = $valid_coupons[$coupon_upper];
            $discount_amount = ($final_amount * $discount_percent) / 100;
            $final_amount -= $discount_amount;
        }
    }

    // Calcular honorarios e IVA
    $iva_amount = $final_amount - $current_price['taxes'] - $current_price['fees'];

    // Preparar archivos para webhook
    $curl_files = [];

    // Agregar PDFs generados
    if (file_exists($authorization_pdf_path)) {
        $curl_files['autorizacion_pdf'] = new CURLFile($authorization_pdf_path, 'application/pdf', basename($authorization_pdf_path));
    }
    if (file_exists($invoice_pdf_path)) {
        $curl_files['factura_pdf'] = new CURLFile($invoice_pdf_path, 'application/pdf', basename($invoice_pdf_path));
    }

    // Agregar archivos subidos por el usuario
    $file_counter = 0;
    foreach ($_FILES as $key => $file) {
        if ($file['error'] === UPLOAD_ERR_OK && isset($file['tmp_name'])) {
            $curl_files["uploaded_file_$file_counter"] = new CURLFile(
                $file['tmp_name'],
                $file['type'],
                $file['name']
            );
            $file_counter++;
        }
    }

    // Preparar datos estructurados para el webhook
    $webhook_data = array_merge([
        'customerName' => $customer_name,
        'customerDNI' => $customer_dni,
        'customerEmail' => $customer_email,
        'customerPhone' => $customer_phone,
        'selected_tramite' => $selected_tramite,
        'tramite_description' => get_polish_tramite_description($selected_tramite),
        'couponUsed' => $coupon_used,
        'totalPrice' => $final_amount,
        'baseprice' => $current_price['total'],
        'taxes' => $current_price['taxes'],
        'fees' => $current_price['fees'],
        'iva' => $iva_amount,
        'hasSignature' => 'true',
        'paymentIntentId' => isset($_POST['paymentIntentId']) ? sanitize_text_field($_POST['paymentIntentId']) : '',
        'billing_address' => $billing_address,
        'billing_city' => $billing_city,
        'billing_postal_code' => $billing_postal_code,
        'billing_province' => $billing_province
    ], $tramite_data, $options_data);

    // Convertir extra_services array a string para curl
    if (isset($webhook_data['extra_services']) && is_array($webhook_data['extra_services'])) {
        $webhook_data['extra_services'] = json_encode($webhook_data['extra_services']);
    }

    // Enviar al webhook
    $ch = curl_init(TRAMITFY_API_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, array_merge($webhook_data, $curl_files));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: multipart/form-data']);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Limpiar archivos temporales
    if (file_exists($authorization_pdf_path)) unlink($authorization_pdf_path);
    if (file_exists($invoice_pdf_path)) unlink($invoice_pdf_path);

    if ($http_code === 200) {
        $response_data = json_decode($response, true);
        error_log('Webhook exitoso: ' . print_r($response_data, true));
        wp_send_json_success([
            'message' => 'Formulario procesado correctamente.',
            'tramiteId' => $response_data['tramiteId'] ?? '',
            'id' => $response_data['id'] ?? ''
        ]);
    } else {
        error_log('Error en webhook: ' . $response . ' (HTTP ' . $http_code . ')');
        wp_send_json_error('Error al procesar el formulario. Por favor, contacte con soporte.');
    }

    wp_die();
}
