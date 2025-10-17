<?php
// Asegurarse de que el archivo no sea accedido directamente
defined('ABSPATH') || exit;

// Función para cargar datos desde archivos CSV según el tipo de vehículo
function cargar_datos_csv($tipo) {
    $archivo_csv = $tipo === 'Moto de Agua' ? 'MOTO.csv' : 'data.csv';
    $ruta_csv = get_template_directory() . '/' . $archivo_csv;
    $data = [];

    if (($handle = fopen($ruta_csv, 'r')) !== FALSE) {
        fgetcsv($handle, 1000, ',');
        while (($row = fgetcsv($handle, 1000, ',')) !== FALSE) {
            list($fabricante, $modelo, $precio) = $row;
            $data[$fabricante][] = [
                'modelo' => $modelo,
                'precio' => $precio
            ];
        }
        fclose($handle);
    }
    return $data;
}

// Función principal para generar y mostrar el formulario en el frontend
function custom_form_shortcode() {
    // Cargar datos de fabricantes para 'Barco' inicialmente
    $datos_fabricantes = cargar_datos_csv('Barco');

    // Obtener la ruta y versión del archivo CSS para encolarlo
    $style_path = get_template_directory() . '/style.css';
    $style_version = filemtime($style_path);

    // Encolar los scripts necesarios
    wp_enqueue_script('stripe', 'https://js.stripe.com/v3/', array(), null, false);
    wp_enqueue_script('signature-pad', 'https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js', array(), null, false);

    // Iniciar el buffering de salida
    ob_start();
    ?>

    <!-- Incluir el archivo CSS del tema -->
    <link rel="stylesheet" href="<?php echo get_template_directory_uri() . '/style.css?v=' . $style_version; ?>" type="text/css" />

    <!-- Estilos personalizados para el formulario -->
    <style>
    /* Estilos para el contenedor del formulario de pago */
.payment-form-container {
    background-color: #f9f9f9;
    padding: 20px;
    border-radius: 8px;
    border: 1px solid #e0e0e0;
    margin-top: 15px;
    margin-bottom: 15px;
}

/* Estilos para el Payment Element */
#payment-element {
    margin-top: 15px;
    margin-bottom: 15px;
}

/* Personalización de Stripe Elements */
.StripeElement {
    background-color: #ffffff;
    padding: 12px;
    border: 1px solid #cccccc;
    border-radius: 4px;
    margin-bottom: 10px;
    width: 100%;
}

.StripeElement--focus {
    border-color: #016d86;
}

.StripeElement--invalid {
    border-color: #dc3545;
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

    /* Estilos para el Payment Element */
#payment-element {
    margin-top: 15px;
    margin-bottom: 15px;
}

        /* Estilos generales para el formulario */
        #custom-form {
            max-width: 1200px;
            margin: 40px auto;
            padding: 30px;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            background-color: #ffffff;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        #custom-form h2 {
            margin-top: 0;
            color: #333333;
        }

        #custom-form label {
            font-weight: normal;
            display: block;
            margin-top: 15px;
            color: #555555;
        }

        #custom-form select,
        #custom-form input[type="text"],
        #custom-form input[type="date"],
        #custom-form input[type="number"],
        #custom-form input[type="tel"],
        #custom-form input[type="email"] {
            width: 100%;
            padding: 12px;
            margin-top: 8px;
            border-radius: 5px;
            border: 1px solid #cccccc;
            font-size: 16px;
            background-color: #f9f9f9;
        }

        #custom-form .button {
            background-color: #28a745;
            color: #ffffff;
            padding: 12px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 18px;
            transition: background-color 0.3s ease;
        }

        #custom-form .button:hover {
            background-color: #218838;
        }

        #custom-form .radio-group {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 20px;
            justify-content: center;
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

        .hidden {
            display: none;
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

        /* Estilos para la página de Precio */
        .price-details {
            margin-top: 20px;
            font-size: 16px;
            background-color: #fafafa;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
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

        .price-calculation {
            font-size: 18px;
            font-weight: bold;
            margin-top: 25px;
            color: #333333;
        }

        #info-banner {
            display: none;
            background-color: #016d86;
            color: #2e7d32;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
        }

        .additional-options {
            margin-top: 20px;
        }

        .additional-options label {
            display: block;
            margin-bottom: 10px;
            color: #555555;
        }

        .additional-options span {
            float: right;
            color: #333333;
        }

        .total-amount {
            font-size: 22px;
            font-weight: bold;
            margin-top: 25px;
            text-align: right;
            color: #333333;
        }

        /* Modificación para centrar los SVGs */
        .radio-group label {
            flex: 1 1 200px;
            max-width: 100%;
            min-width: 150px;
            height: auto;
            padding: 10px 15px;
            display: flex;
            flex-direction: column; /* Añadido */
            align-items: center;     /* Modificado */
            justify-content: center; /* Añadido */
            border: 2px solid #016d86;
            border-radius: 8px;
            margin: 10px;
            position: relative;
            cursor: pointer;
            transition: background-color 0.3s ease, border-color 0.3s ease;
            background-color: #ffffff;
            text-align: center;      /* Añadido */
        }

        .radio-group input[type="radio"] {
            position: absolute;
            top: 10px;
            left: 10px;
        }

        .radio-group svg {
            margin-left: 0;         /* Modificado */
            margin-bottom: 10px;    /* Añadido para separación */
            width: 48px;
            height: 48px;
        }

        /* Resaltar el contenedor seleccionado */
        .radio-group label.selected {
            background-color: #e9f7ff;
            border-color: #0056b3;
        }

        /* Estilos para el popup de información */
        #info-popup {
            display: none;
            background-color: #ffffff;
            border: 1px solid #016d86;
            border-radius: 8px;
            padding: 25px;
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
            margin-top: 25px;
            animation: fadeIn 0.5s;
        }
        
        /* Añadir esta regla para el título del popup */
#info-popup h2 {
    text-align: center;
    color: #016d86; /* Añadido */
}


        /* Animación para el popup */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Estilos para el elemento de la tarjeta */
        .stripe-input {
            border: 1px solid #cccccc;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 15px;
            background-color: #f9f9f9;
        }

        .stripe-input.StripeElement--focus {
            border-color: #28a745;
        }

        .stripe-input.StripeElement--invalid {
            border-color: #dc3545;
        }

        .stripe-label {
            font-weight: normal;
            margin-top: 15px;
            display: block;
            color: #555555;
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

        /* Estilos para los campos adicionales */
        .additional-input {
            margin-top: 10px;
        }

        .additional-input label {
            font-weight: normal;
            display: block;
            margin-top: 8px;
            color: #555555;
        }

        .additional-input input[type="text"] {
            width: 100%;
            padding: 12px;
            margin-top: 5px;
            border-radius: 5px;
            border: 1px solid #cccccc;
            font-size: 16px;
            background-color: #f9f9f9;
        }

        /* Estilos adicionales para la sección de documentos */
        .upload-section {
            margin-top: 20px;
        }
        .upload-item {
            margin-bottom: 15px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
        }
        .upload-item label {
            flex: 1 1 100%;
            font-weight: normal;
            color: #555555;
            margin-bottom: 5px;
        }
        .upload-item input[type="file"] {
            flex: 1 1 100%;
            margin-right: 10px;
            margin-bottom: 5px;
        }
        .upload-item .view-example,
        .upload-item .view-signed-document {
            flex: none;
            background-color: transparent;
            color: #007bff;
            text-decoration: underline;
            cursor: pointer;
            margin-bottom: 5px;
        }
        .upload-item .view-example:hover,
        .upload-item .view-signed-document:hover {
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

        /* Modificaciones para la responsividad de la firma */
        #signature-container {
            margin-top: 20px;
            text-align: center;
            width: 100%;
        }

        #signature-pad {
            border: 1px solid #ccc;
            width: 100%;
            max-width: 600px; /* Añadido para limitar el ancho máximo */
            height: 200px;
            box-sizing: border-box;
        }

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

            .price-details {
                padding: 15px;
            }

            .price-calculation {
                font-size: 16px;
            }

            .radio-group label {
                flex: 1 1 100%;
                margin: 5px 0;
            }

            .radio-group svg {
                width: 40px;
                height: 40px;
                margin-left: 0;
            }

            .upload-item {
                flex-direction: column;
                align-items: flex-start;
            }

            .upload-item input[type="file"],
            .upload-item .view-example {
                margin: 5px 0;
            }

            /* Modificación para la firma */
            #signature-pad {
                height: 150px; /* Ajuste de altura para pantallas más pequeñas */
            }
        }

        @media (max-width: 480px) {
            #custom-form {
                padding: 20px;
            }

            #form-navigation {
                padding: 10px;
            }

            #form-navigation a {
                padding: 8px;
            }

            .price-details {
                padding: 10px;
            }

            .price-calculation {
                font-size: 14px;
            }

            .radio-group svg {
                width: 32px;
                height: 32px;
                margin-left: 0;
            }

            .button {
                font-size: 16px;
                padding: 10px;
            }

            .total-amount {
                font-size: 18px;
            }

            /* Modificación para la firma */
            #signature-pad {
                height: 120px; /* Ajuste de altura para pantallas más pequeñas */
            }
        }
    </style>
    <!-- Formulario principal -->
    <form id="custom-form" action="" method="POST" enctype="multipart/form-data">
        <!-- Navegación del formulario -->
        <div id="form-navigation">
            <a href="#" class="nav-link" data-page-id="page-vehiculo">Vehículo</a>
            <a href="#" class="nav-link" data-page-id="page-precio">Precio</a>
            <a href="#" class="nav-link" data-page-id="page-pago">Pago</a>
            <a href="#" class="nav-link" data-page-id="page-documentos">Documentos</a>
        </div>

<!-- Overlay de carga -->
<div id="loading-overlay">
    <div class="spinner"></div>
    <p>Procesando, por favor espera...</p>
</div>

<!-- PILOTO DE AVISOS (banner superior) -->
<div id="alert-message" style="display:none; background-color:#fffae6; border:1px solid #ffe58a; padding:15px; border-radius:5px; margin-bottom:20px;">
    <p id="alert-message-text" style="margin:0; color:#666;"></p>
</div>


        <!-- Página de Información del Vehículo -->
        <div id="page-vehiculo" class="form-page">
            <h2>Información del Vehículo</h2>
            <!-- Selección de tipo de vehículo -->
            <div class="radio-group">
                <label>
                    <input type="radio" name="vehicle_type" value="Barco" required checked>
                    <!-- SVG para Barco -->
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 581.11 434.98"><polygon fill="#035966" points="581.11 306.95 0 306.95 0 400.91 39.27 434.98 541.84 434.98 581.11 400.91 581.11 306.95"/><polygon fill="#035966" points="272.28 0 272.45 279.37 514.65 279.29 272.28 0"/><polygon fill="#035966" points="244.82 73.77 244.7 279.37 66.46 279.31 244.82 73.77"/></svg>
                </label>
                <label>
                    <input type="radio" name="vehicle_type" value="Moto de Agua">
                    <!-- SVG para Moto de Agua -->
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 581.11 340.03"><polygon fill="#035966" points="581.11 212.01 0 212.01 0 305.96 39.27 340.03 541.84 340.03 581.11 305.96 581.11 212.01"/><path fill="#035966" d="M306.59,118.58h-94.34c-5.79,0-10.64,4.03-11.91,9.43l-7.74,24.02h-40.73l-35.68-29.65.03-.04c-2.29-1.9-5.06-2.83-7.82-2.83h0s-77.38,0-77.38,0c-6.75,0-12.23,5.48-12.23,12.24,0,2.78.93,5.35,2.49,7.4l31.45,45.27h271.29l-17.43-65.84Z"/><polygon fill="#035966" points="562.32 184.42 330.15 19.53 302.65 0 323.59 79.09 236.75 56.21 231.66 75.55 329.47 101.33 351.47 184.42 562.32 184.42"/></svg>
                </label>
            </div>

            <!-- Selección de fabricante -->
            <label for="manufacturer">Fabricante:</label>
            <select id="manufacturer" name="manufacturer" required>
                <option value="">Seleccione un fabricante</option>
                <?php foreach (array_keys($datos_fabricantes) as $fabricante): ?>
                    <option value="<?php echo esc_attr($fabricante); ?>"><?php echo esc_html($fabricante); ?></option>
                <?php endforeach; ?>
            </select>

            <!-- Selección de modelo -->
            <label for="model">Modelo:</label>
            <select id="model" name="model" required>
                <option value="">Seleccione un modelo</option>
            </select>

            <!-- Precio de compra -->
            <label for="purchase_price">Precio de Compra (€):</label>
            <input type="number" id="purchase_price" name="purchase_price" placeholder="Ingresa el precio de compra" required />

            <!-- Fecha de matriculación -->
            <label for="matriculation_date">Fecha de Matriculación:</label>
            <input type="date" id="matriculation_date" name="matriculation_date" max="<?php echo date('Y-m-d'); ?>" required>

            <!-- Comunidad Autónoma -->
            <label for="region">Comunidad Autónoma:</label>
            <select id="region" name="region" required>
                <option value="">Seleccione una comunidad autónoma</option>
                <option value="Andalucía">Andalucía</option>
                <option value="Aragón">Aragón</option>
                <option value="Asturias">Asturias</option>
                <option value="Islas Baleares">Islas Baleares</option>
                <option value="Canarias">Canarias</option>
                <option value="Cantabria">Cantabria</option>
                <option value="Castilla-La Mancha">Castilla-La Mancha</option>
                <option value="Castilla y León">Castilla y León</option>
                <option value="Cataluña">Cataluña</option>
                <option value="Comunidad Valenciana">Comunidad Valenciana</option>
                <option value="Extremadura">Extremadura</option>
                <option value="Galicia">Galicia</option>
                <option value="Madrid">Madrid</option>
                <option value="Murcia">Murcia</option>
                <option value="Navarra">Navarra</option>
                <option value="País Vasco">País Vasco</option>
                <option value="La Rioja">La Rioja</option>
                <option value="Ceuta">Ceuta</option>
                <option value="Melilla">Melilla</option>
            </select>

            <!-- Aceptación de términos -->
            <div class="terms-container">
                <label>
                    <input type="checkbox" name="terms_accept_vehicle" required> Acepto los <a href="https://tramitfy.es/terminos-y-condiciones-de-uso/" target="_blank">términos y condiciones</a>.
                </label>
            </div>
        </div>

        <!-- Página de Detalle de Precios -->
        <div id="page-precio" class="form-page hidden">
            <h2>Detalle de Precios</h2>
            <!-- Detalles de precios -->
            <div class="price-details">
<p><strong>Cambio de nombre:</strong> <span id="cambio_nombre_price" style="float:right;">94.99 €</span></p>
                <p><strong>Incluye:</strong></p>
<ul>
    <li>Tasas capitanía marítima - 19.03 €</li>
    <li>Gestión - 60 €</li>
    <li>IVA - 15.96 €</li>
    <li>Comisión bancaria (1.5% del ITP) - <span id="extra_fee_includes_display">0 €</span></li>
    <li>Envío vía e-mail - Gratuito</li>
</ul>

                <div class="price-calculation">
                    <label for="transfer_tax"><strong>Impuesto de transmisiones:</strong></label>
                    <span id="transfer_tax_display">0 €</span> <a href="#" id="info-link">+info</a>
                </div>
            </div>

            <!-- Popup de información con cálculos de ITP -->
            <div id="info-popup">
                <h2 style="text-align:center; color:#016d86;">Detalle del cálculo del ITP</h2>
                <p>El <strong>Impuesto sobre Transmisiones Patrimoniales (ITP)</strong> es un tributo que el comprador debe abonar a Hacienda en los cambios de titularidad de un vehículo entre particulares.</p>
                <div style="background-color:#f1f1f1; padding:15px; border-radius:8px;">
                    <p><strong>Valor fiscal base:</strong> <span id="base_value_display">0 €</span></p>
                    <p><strong>Antigüedad del vehículo:</strong> <span id="vehicle_age_display">0 años</span></p>
                    <p><strong>Porcentaje de depreciación aplicado:</strong> <span id="depreciation_percentage_display">0 %</span></p>
                    <p><strong>Valor fiscal con depreciación:</strong> <span id="fiscal_value_display">0 €</span></p>
                    <p><strong>Precio de compra declarado:</strong> <span id="purchase_price_display">0 €</span></p>
                    <p><strong>Base imponible (mayor valor):</strong> <span id="tax_base_display">0 €</span></p>
                    <p><strong>Tipo impositivo aplicado:</strong> <span id="tax_rate_display">0 %</span></p>
                    <hr>
                    <p style="font-size:18px;"><strong>ITP a pagar:</strong> <span id="calculated_itp_display">0 €</span></p>
                </div>
            </div>

            <!-- Opciones adicionales -->
            <div class="additional-options">
                <label><input type="checkbox" class="extra-option" data-price="40" value="Cambio de nombre"> Cambiar de nombre la embarcación <span>40 €</span></label>
                <!-- Input para 'Cambio de nombre' -->
                <div class="additional-input" id="nombre-input" style="display: none;">
                    <input type="text" id="nuevo_nombre" name="nuevo_nombre" placeholder="Ingrese el nuevo nombre de la embarcación" />
                </div>

                <label><input type="checkbox" class="extra-option" data-price="40" value="Cambio de puerto base"> Cambio de puerto base <span>40 €</span></label>
                <!-- Input para 'Cambio de puerto base' -->
                <div class="additional-input" id="puerto-input" style="display: none;">
                    <input type="text" id="nuevo_puerto" name="nuevo_puerto" placeholder="Ingrese el nuevo puerto" />
                </div>
            </div>

            <!-- Total -->
            <div class="total-amount">
                <strong>Total:</strong> <span id="total_display">94.99 €</span>
            </div>
        </div>

        <!-- Página de Información de Pago -->
        <div id="page-pago" class="form-page hidden">
            <h2>Información de Pago</h2>
            <!-- Campos de información del cliente -->
            <label for="customer_name">Nombre y Apellidos:</label>
            <input type="text" id="customer_name" name="customer_name" required />

            <label for="customer_dni">DNI:</label>
            <input type="text" id="customer_dni" name="customer_dni" required />

            <label for="customer_email">Correo Electrónico:</label>
            <input type="email" id="customer_email" name="customer_email" required />

            <label for="customer_phone">Teléfono:</label>
            <input type="tel" id="customer_phone" name="customer_phone" required />

            <!-- Formulario de pago con Stripe -->
            <div id="payment-form" class="payment-form-container">
<!-- Elemento de pago -->
<div id="payment-element"><!-- Payment Element se renderizará aquí --></div>
<!-- Mostrar mensajes de pago -->
<div id="payment-message" class="hidden"></div>


                <!-- Aceptación de términos de pago -->
                <div class="terms-container">
                    <label>
                        <input type="checkbox" name="terms_accept_pago" required> Acepto los <a href="https://tramitfy.es/terminos-y-condiciones-de-uso/" target="_blank">términos y condiciones de pago</a>.
                    </label>
                </div>

                <button id="submit" class="button">Pagar</button>
                <div id="payment-message" class="hidden"></div>
            </div>
        </div>

<!-- Página de Subida de Documentos -->
<div id="page-documentos" class="form-page hidden">
    <h2>Adjuntar Documentación</h2>
    <p>Por favor, sube los siguientes documentos. Puedes ver un ejemplo haciendo clic en "Ver ejemplo" junto a cada uno.</p>
    <div class="upload-section">
<div class="upload-item">
    <label id="label-hoja-asiento" for="upload-hoja-asiento">Copia de la hoja de asiento</label>
    <input type="file" id="upload-hoja-asiento" name="upload_hoja_asiento" required>
    <a href="#" class="view-example" id="view-example-hoja-asiento" data-doc="hoja-asiento">Ver ejemplo</a>
</div>

        <div class="upload-item">
            <label for="upload-dni-comprador">DNI del comprador</label>
            <input type="file" id="upload-dni-comprador" name="upload_dni_comprador" required>
            <a href="#" class="view-example" data-doc="dni-comprador">Ver ejemplo</a>
        </div>
        <div class="upload-item">
            <label for="upload-dni-vendedor">DNI del vendedor</label>
            <input type="file" id="upload-dni-vendedor" name="upload_dni_vendedor" required>
            <a href="#" class="view-example" data-doc="dni-comprador">Ver ejemplo</a>
        </div>
        <div class="upload-item">
            <label for="upload-contrato-compraventa">Copia del contrato de compraventa</label>
            <input type="file" id="upload-contrato-compraventa" name="upload_contrato_compraventa" required>
            <a href="#" class="view-example" data-doc="contrato-compraventa">Ver ejemplo</a>
        </div>
    </div>

            <!-- Sección para generar y firmar el documento -->
            <h2>Autorización para Transferencia</h2>
            <div class="document-sign-section">
                <p>Por favor, lee el siguiente documento y firma en el espacio proporcionado.</p>
                <div id="authorization-document" style="background-color:#f9f9f9; padding:20px; border-radius:8px; border:1px solid #e0e0e0;">
                    <!-- El documento de autorización se generará dinámicamente aquí -->
                </div>
                <div id="signature-container" style="margin-top:20px; text-align:center;">
                    <canvas id="signature-pad" width="500" height="200" style="border:1px solid #ccc;"></canvas>
                </div>
                <button type="button" class="button" id="clear-signature">Limpiar Firma</button>
            </div>
        </div>

        <!-- Botones de navegación del formulario -->
        <div class="button-container">
            <button type="button" class="button" id="prevButton">Anterior</button>
            <button type="button" class="button" id="nextButton">Siguiente</button>
        </div>
    </form>

    <!-- Estilos adicionales para la sección de documentos -->
    <style>
        .upload-section {
            margin-top: 20px;
        }
        .upload-item {
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }
        .upload-item label {
            flex: 1;
            font-weight: normal;
            color: #555555;
        }
        .upload-item input[type="file"] {
            flex: 2;
            margin-right: 10px;
        }
        .upload-item .view-example,
        .upload-item .view-signed-document {
            flex: none;
            background-color: transparent;
            color: #007bff;
            text-decoration: underline;
            cursor: pointer;
        }
        .upload-item .view-example:hover,
        .upload-item .view-signed-document:hover {
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
    </style>

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
            // Variables para cálculos de ITP y depreciación
            const itpRates = {
                "Andalucía": 0.04,
                "Aragón": 0.04,
                "Asturias": 0.04,
                "Islas Baleares": 0.04,
                "Canarias": 0.055,
                "Cantabria": 0.08,
                "Castilla-La Mancha": 0.06,
                "Castilla y León": 0.05,
                "Cataluña": 0.05,
                "Comunidad Valenciana": 0.06,
                "Extremadura": 0.06,
                "Galicia": 0.03,
                "Madrid": 0.04,
                "Murcia": 0.04,
                "Navarra": 0.04,
                "País Vasco": 0.04,
                "La Rioja": 0.04,
                "Ceuta": 0.02,
                "Melilla": 0.04
            };

            const depreciationRates = [
                { years: 0, rate: 100 },
                { years: 1, rate: 84 },
                { years: 2, rate: 67 },
                { years: 3, rate: 56 },
                { years: 4, rate: 47 },
                { years: 5, rate: 39 },
                { years: 6, rate: 34 },
                { years: 7, rate: 28 },
                { years: 8, rate: 24 },
                { years: 9, rate: 19 },
                { years: 10, rate: 17 },
                { years: 11, rate: 13 },
                { years: 12, rate: 12 },
                { years: 13, rate: 11 },
                { years: 14, rate: 10 },
                { years: 15, rate: 10 }
            ];

            // Elementos del DOM
            let basePrice = 0;
            const purchasePriceInput = document.getElementById('purchase_price');
            const regionSelect = document.getElementById('region');
            const transferTaxDisplay = document.getElementById('transfer_tax_display');
            const totalDisplay = document.getElementById('total_display');
            const extraOptions = document.querySelectorAll('.extra-option');
            const manufacturerSelect = document.getElementById('manufacturer');
            const modelSelect = document.getElementById('model');
            const extraFeeIncludesDisplay = document.getElementById('extra_fee_includes_display');
const cambioNombrePriceDisplay = document.getElementById('cambio_nombre_price');

            const infoPopup = document.getElementById('info-popup');
            const matriculationDateInput = document.getElementById('matriculation_date');

            // Elementos para mostrar en el popup
            const baseValueDisplay = document.getElementById('base_value_display');
            const depreciationPercentageDisplay = document.getElementById('depreciation_percentage_display');
            const fiscalValueDisplay = document.getElementById('fiscal_value_display');
            const calculatedItpDisplay = document.getElementById('calculated_itp_display');
            const vehicleAgeDisplay = document.getElementById('vehicle_age_display');
            const purchasePriceDisplay = document.getElementById('purchase_price_display');
            const taxBaseDisplay = document.getElementById('tax_base_display');
            const taxRateDisplay = document.getElementById('tax_rate_display');

          let currentTransferTax = 0;
let currentExtraFee = 0;


// Variables para Stripe
let stripe;
let elements;
let paymentCompleted = false;
let purchaseDetails = {};

// Función para inicializar Stripe con Payment Element
async function initializeStripe() {
    stripe = Stripe('YOUR_STRIPE_LIVE_PUBLIC_KEY_HERE'); // Reemplaza con tu clave pública

    // Obtener el monto total en céntimos
    const totalAmount = parseFloat(document.getElementById('total_display').textContent.replace('€', '').trim());
    const totalAmountCents = Math.round(totalAmount * 100);

    // Crear Payment Intent en el servidor
    const response = await fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=create_payment_intent&amount=${totalAmountCents}`
    });

    const result = await response.json();

    if (result.error) {
        alert('Error al crear el Payment Intent: ' + result.error);
        return;
    }

    const appearance = {
        theme: 'flat',
        variables: {
            colorPrimary: '#016d86',
            colorBackground: '#ffffff',
            colorText: '#333333',
            fontFamily: 'Arial, sans-serif',
            spacingUnit: '4px',
            borderRadius: '4px',
        },
        rules: {
            '.Input': {
                padding: '12px',
                border: '1px solid #cccccc',
                borderRadius: '4px',
                backgroundColor: '#ffffff',
            },
            '.Input:focus': {
                borderColor: '#016d86',
            },
            '.Label': {
                fontSize: '14px',
                marginBottom: '4px',
                color: '#555555',
            },
        }
    };

    elements = stripe.elements({ appearance, clientSecret: result.clientSecret });

    const paymentElementOptions = {
        layout: 'tabs',
    };

    const paymentElement = elements.create('payment', paymentElementOptions);
    paymentElement.mount('#payment-element');
}

            // Función para calcular el porcentaje de depreciación basado en los años
            function calculateDepreciationPercentage(years) {
                for (let i = 0; i < depreciationRates.length; i++) {
                    if (years <= depreciationRates[i].years) {
                        return depreciationRates[i].rate;
                    }
                }
                return 10;
            }

            // Función para calcular el valor fiscal del vehículo
            function calculateFiscalValue() {
                const matriculationDate = new Date(matriculationDateInput.value);
                const today = new Date();
                let yearsDifference = today.getFullYear() - matriculationDate.getFullYear();

                const monthsDifference = today.getMonth() - matriculationDate.getMonth();
                if (monthsDifference < 0 || (monthsDifference === 0 && today.getDate() < matriculationDate.getDate())) {
                    yearsDifference--;
                }

                yearsDifference = yearsDifference < 0 ? 0 : yearsDifference;

                const depreciationPercentage = calculateDepreciationPercentage(yearsDifference);
                const fiscalValue = basePrice * (depreciationPercentage / 100);

                return { fiscalValue, depreciationPercentage, yearsDifference };
            }

            // Función para calcular el Impuesto de Transmisiones Patrimoniales (ITP)
            function calculateTransferTax() {
                const purchasePrice = parseFloat(purchasePriceInput.value) || 0;
                const { fiscalValue, depreciationPercentage, yearsDifference } = calculateFiscalValue();
                const region = regionSelect.value;
                const rate = itpRates[region] || 0;
                const baseValue = Math.max(purchasePrice, fiscalValue);
                const itp = baseValue * rate;
                const extraFee = itp * 0.015;


                // Actualizar valores en el popup
                baseValueDisplay.textContent = `${basePrice.toFixed(2)} €`;
                depreciationPercentageDisplay.textContent = `${depreciationPercentage} %`;
                fiscalValueDisplay.textContent = `${fiscalValue.toFixed(2)} €`;
                vehicleAgeDisplay.textContent = `${yearsDifference} años`;
                purchasePriceDisplay.textContent = `${purchasePrice.toFixed(2)} €`;
                taxBaseDisplay.textContent = `${baseValue.toFixed(2)} €`;
                taxRateDisplay.textContent = `${(rate * 100).toFixed(2)} %`;
                calculatedItpDisplay.textContent = `${itp.toFixed(2)} €`;

                return { itp, extraFee };
            }
            
            // Función para actualizar la visibilidad de las opciones adicionales
function updateAdditionalOptionsVisibility() {
    const vehicleType = document.querySelector('input[name="vehicle_type"]:checked').value;
    const additionalOptionsDiv = document.querySelector('.additional-options');

    if (vehicleType === 'Moto de Agua') {
        additionalOptionsDiv.style.display = 'none';
        // Desmarcar todas las opciones extras si están seleccionadas
        extraOptions.forEach(option => {
            option.checked = false;
        });
        updateTotal(); // Actualizar el total después de desmarcar las opciones
    } else {
        additionalOptionsDiv.style.display = 'block';
    }
}

// Función para actualizar los labels y atributos en la sección de documentos
function updateDocumentLabels() {
    const vehicleType = document.querySelector('input[name="vehicle_type"]:checked').value;
    const labelHojaAsiento = document.getElementById('label-hoja-asiento');
    const inputHojaAsiento = document.getElementById('upload-hoja-asiento');
    const viewExampleLink = document.getElementById('view-example-hoja-asiento');

    if (vehicleType === 'Moto de Agua') {
        labelHojaAsiento.textContent = 'Tarjeta de la moto';
        inputHojaAsiento.name = 'upload_tarjeta_moto';
        viewExampleLink.setAttribute('data-doc', 'tarjeta-moto');
    } else {
        labelHojaAsiento.textContent = 'Copia de la hoja de asiento';
        inputHojaAsiento.name = 'upload_hoja_asiento';
        viewExampleLink.setAttribute('data-doc', 'hoja-asiento');
    }
}


            // Función para actualizar la visualización del ITP
function updateTransferTaxDisplay() {
    const { itp, extraFee } = calculateTransferTax();
    currentTransferTax = itp;
    currentExtraFee = extraFee;

    transferTaxDisplay.textContent = `${currentTransferTax.toFixed(2)} €`;
    extraFeeIncludesDisplay.textContent = `${currentExtraFee.toFixed(2)} €`;
}


function updateTotal() {
    let total = 94.99;
    extraOptions.forEach(option => {
        if (option.checked) {
            total += parseFloat(option.dataset.price);
        }
    });
    total += currentTransferTax;
    total += currentExtraFee;
    totalDisplay.textContent = `${total.toFixed(2)} €`;

    // Actualizar el precio junto a "Cambio de nombre"
    const cambioNombreTotal = 94.99 + currentExtraFee;
    cambioNombrePriceDisplay.textContent = `${cambioNombreTotal.toFixed(2)} €`;
}


            // Función que se ejecuta cuando cambian los inputs relevantes
function onInputChange() {
    if (purchasePriceInput.value && matriculationDateInput.value && regionSelect.value && basePrice > 0) {
        updateTransferTaxDisplay();
        updateTotal();
    }
}


            // Prevenir puntos y comas en el campo de precio de compra
            purchasePriceInput.addEventListener('input', function() {
                // Eliminar puntos y comas
                this.value = this.value.replace(/[.,]/g, '');
                onInputChange();
            });

            matriculationDateInput.addEventListener('change', onInputChange);
            regionSelect.addEventListener('change', onInputChange);

            extraOptions.forEach(option => option.addEventListener('change', function() {
                updateTotal();
                updateAdditionalInputs();
            }));

document.getElementById('info-link').addEventListener('click', function(e) {
    e.preventDefault();
    const computedStyle = window.getComputedStyle(infoPopup);
    if (computedStyle.display === "none") {
        infoPopup.style.display = "block";
    } else {
        infoPopup.style.display = "none";
    }
});

            // Función para cargar fabricantes según el tipo de vehículo
            function populateManufacturers() {
                const vehicleType = document.querySelector('input[name="vehicle_type"]:checked').value;
                const csvFile = vehicleType === 'Moto de Agua' ? 'MOTO.csv' : 'data.csv';
                fetch('<?php echo get_template_directory_uri(); ?>/' + csvFile)
                    .then(response => response.text())
                    .then(data => {
                        const manufacturers = {};
                        const rows = data.split('\n').slice(1);
                        rows.forEach(row => {
                            const [fabricante, modelo, precio] = row.split(',');
                            if (!manufacturers[fabricante]) {
                                manufacturers[fabricante] = [];
                            }
                            manufacturers[fabricante].push({ modelo, precio });
                        });

                        manufacturerSelect.innerHTML = '<option value="">Seleccione un fabricante</option>';
                        Object.keys(manufacturers).forEach(fabricante => {
                            const option = document.createElement('option');
                            option.value = fabricante;
                            option.textContent = fabricante;
                            manufacturerSelect.appendChild(option);
                        });
                    });
            }

            // Escuchar cambios en el tipo de vehículo
            document.querySelectorAll('input[name="vehicle_type"]').forEach(input => {
                input.addEventListener('change', () => {
                    populateManufacturers();
                    modelSelect.innerHTML = '<option value="">Seleccione un modelo</option>';
                    basePrice = 0;
                    onInputChange();
                    updateVehicleSelection();
                });
            });

            // Llenar modelos y precios cuando se selecciona un fabricante
            manufacturerSelect.addEventListener('change', function() {
                const selectedFabricante = this.value;
                modelSelect.innerHTML = '<option value="">Seleccione un modelo</option>';
                basePrice = 0;
                onInputChange();

                if (selectedFabricante) {
                    const csvFile = document.querySelector('input[name="vehicle_type"]:checked').value === 'Moto de Agua' ? 'MOTO.csv' : 'data.csv';
                    fetch('<?php echo get_template_directory_uri(); ?>/' + csvFile)
                        .then(response => response.text())
                        .then(data => {
                            const rows = data.split('\n').slice(1);
                            rows.forEach(row => {
                                const [fabricante, modelo, precio] = row.split(',');
                                if (fabricante === selectedFabricante) {
                                    const option = document.createElement('option');
                                    option.value = modelo;
                                    option.textContent = modelo;
                                    option.dataset.price = precio;
                                    modelSelect.appendChild(option);
                                }
                            });
                        });
                }
            });

            // Actualizar el precio base cuando se selecciona un modelo
            modelSelect.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                basePrice = selectedOption ? parseFloat(selectedOption.dataset.price) : 0;
                onInputChange();
            });

            // Cargar fabricantes inicialmente
            populateManufacturers();

            // Navegación del formulario entre páginas
            const formPages = document.querySelectorAll('.form-page');
            const navLinks = document.querySelectorAll('.nav-link');
            let currentPage = 0;

// Función para manejar el pago
async function handlePayment() {
    const paymentForm = document.getElementById('payment-form');
    const paymentMessage = document.getElementById('payment-message');

    document.getElementById('submit').addEventListener('click', async (e) => {
        e.preventDefault();

        // Validar campos del cliente
        const customerName = document.getElementById('customer_name').value.trim();
        const customerEmail = document.getElementById('customer_email').value.trim();
        const customerPhone = document.getElementById('customer_phone').value.trim();
        const customerDNI = document.getElementById('customer_dni').value.trim();

        if (!customerName || !customerEmail || !customerPhone || !customerDNI) {
            alert('Por favor, completa todos los campos requeridos.');
            return;
        }

        // Verificar checkbox de términos y condiciones
        if (!document.querySelector('input[name="terms_accept_pago"]').checked) {
            alert('Debe aceptar los términos y condiciones de pago para continuar.');
            return;
        }

        // Deshabilitar botón de pago para evitar múltiples clics
        document.getElementById('submit').disabled = true;

        // Mostrar overlay de carga
        document.getElementById('loading-overlay').style.display = 'flex';

        try {
            const { error } = await stripe.confirmPayment({
                elements,
                confirmParams: {
                    payment_method_data: {
                        billing_details: {
                            name: customerName,
                            email: customerEmail,
                            phone: customerPhone,
                        },
                    },
                    // Puedes agregar return_url si deseas redirigir después del pago
                },
                redirect: 'if_required', // Evitar redirecciones automáticas
            });

            if (error) {
                // Mostrar mensaje de error al cliente
                paymentMessage.textContent = error.message;
                paymentMessage.classList.add('error');
                paymentMessage.classList.remove('hidden');
            } else {
                // Pago completado con éxito
                paymentMessage.textContent = 'Pago realizado con éxito.';
                paymentMessage.classList.add('success');
                paymentMessage.classList.remove('hidden');

                // Marcar pago como completado
                paymentCompleted = true;

                // Guardar detalles de la compra
                const totalAmount = parseFloat(document.getElementById('total_display').textContent.replace('€', '').trim());

                purchaseDetails = {
                    totalAmount: totalAmount.toFixed(2),
                    options: Array.from(extraOptions).filter(opt => opt.checked).map(opt => opt.value),
                    transferTax: currentTransferTax.toFixed(2),
                    customerName: customerName,
                    customerEmail: customerEmail,
                    customerPhone: customerPhone,
                    customerDNI: customerDNI,
                    nuevoNombre: document.getElementById('nuevo_nombre').value.trim(),
                    nuevoPuerto: document.getElementById('nuevo_puerto').value.trim()
                };

                // Enviar correos
                sendEmails();

                // Proceder a la siguiente página
                currentPage++;
                updateForm();
            }
        } catch (err) {
            console.error(err);
            alert('Ocurrió un error al procesar el pago.');
        } finally {
            document.getElementById('submit').disabled = false;
            document.getElementById('loading-overlay').style.display = 'none';
        }
    });
}



            // Función para obtener el índice de página por ID
            function getPageIndexById(pageId) {
                for (let i = 0; i < formPages.length; i++) {
                    if (formPages[i].id === pageId) {
                        return i;
                    }
                }
                return -1;
            }

            // Función para actualizar la visualización del formulario
            function updateForm() {
                formPages.forEach((page, index) => {
                    page.classList.toggle('hidden', index !== currentPage);
                });
                navLinks.forEach(link => {
                    const pageId = link.getAttribute('data-page-id');
                    const pageIndex = getPageIndexById(pageId);
                    link.classList.toggle('active', pageIndex === currentPage);
                });

                // Ocultar botón "Anterior" en la primera página
                document.getElementById('prevButton').style.display = currentPage === 0 ? 'none' : 'inline-block';

                // Ajustar texto del botón "Siguiente"/"Enviar"
                const nextButton = document.getElementById('nextButton');
                if (currentPage === formPages.length - 1) {
                    nextButton.textContent = 'Enviar';
                } else {
                    nextButton.textContent = 'Siguiente';
                }

                // Deshabilitar inputs en la página de documentos si el pago no está completado
                if (formPages[currentPage].id === 'page-documentos') {
                    updateDocumentLabels();
                    const uploadInputs = document.querySelectorAll('#page-documentos input[type="file"]');
                    if (!paymentCompleted) {
                        uploadInputs.forEach(input => {
                            input.disabled = true;
                        });
                        // Mostrar mensaje al usuario
                        if (!document.getElementById('payment-required-message')) {
                            const message = document.createElement('p');
                            message.id = 'payment-required-message';
                            message.style.color = 'red';
                            message.textContent = 'Debe completar el pago para poder subir y ver los documentos necesarios.';
                            const pageDocumentos = document.getElementById('page-documentos');
                            pageDocumentos.insertBefore(message, pageDocumentos.firstChild);
                        }
                    } else {
                        uploadInputs.forEach(input => {
                            input.disabled = false;
                        });
                        const message = document.getElementById('payment-required-message');
                        if (message) {
                            message.remove();
                        }
                        // Generar el documento de autorización
                        generateAuthorizationDocument();
                        // Inicializar la firma
                        initializeSignaturePad();
                    }
                }

                // Actualizar la visibilidad del checkbox de términos y condiciones
                updateTermsCheckbox();

                // Inicializar Stripe en la página de pago
                if (formPages[currentPage].id === 'page-pago' && !stripe) {
                    initializeStripe();
                    handlePayment();
                }
            }

            // Función para mostrar u ocultar el checkbox de términos y condiciones
            function updateTermsCheckbox() {
                const termsContainers = document.querySelectorAll('.terms-container');
                termsContainers.forEach(container => {
                    container.style.display = 'none';
                });

                if (formPages[currentPage].id === 'page-vehiculo') {
                    document.querySelector('input[name="terms_accept_vehicle"]').checked = false;
                    termsContainers[0].style.display = 'block';
                } else if (formPages[currentPage].id === 'page-pago') {
                    document.querySelector('input[name="terms_accept_pago"]').checked = false;
                    termsContainers[1].style.display = 'block';
                }
            }

            // Función para manejar el envío final del formulario
function handleFinalSubmission() {
    // 1. Validar firma
    if (signaturePad && signaturePad.isEmpty()) {
        alert('Por favor, firme el documento de autorización antes de enviar el formulario.');
        document.getElementById('loading-overlay').style.display = 'none';
        return;
    }

    // 2. REFERENCIAS AL BANNER
    const alertMessage = document.getElementById('alert-message');
    const alertMessageText = document.getElementById('alert-message-text');

    // 3. MOSTRAMOS BANNER Y TEXTO "Enviando..."
    alertMessage.style.display = 'block';
    alertMessageText.textContent = 'Enviando el formulario...';

    // 4. Continuar con la lógica de envío (FormData, fetch, etc.)
    const formData = new FormData(document.getElementById('transferencia-form'));
    formData.append('action', 'submit_form_XXX');
    if (signaturePad) {
        formData.append('signature', signaturePad.toDataURL());
    }

    // Overlay
    document.getElementById('loading-overlay').style.display = 'flex';

    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Mensaje de éxito
            alertMessageText.textContent = '¡Formulario enviado con éxito! Redirigiendo...';

            // Redirigir
            window.location.href = 'https://tramitfy.es/pago-realizado-con-exito/';
        } else {
            // Error devuelto por el servidor
            alertMessageText.textContent = 'Error al enviar el formulario: ' + data.message;
            document.getElementById('loading-overlay').style.display = 'none';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alertMessageText.textContent = 'Hubo un error al enviar el formulario.';
        document.getElementById('loading-overlay').style.display = 'none';
    });
}


            // Llamar a la función de envío final al hacer clic en "Enviar" en la última página
            document.getElementById('nextButton').addEventListener('click', () => {
                const isLastPage = currentPage === formPages.length - 1;

                if (!isLastPage) {
                    currentPage++;
                    updateForm();
                } else {
                    // Enviar el formulario
                    handleFinalSubmission();
                }
            });

            navLinks.forEach(link => {
                link.addEventListener('click', (e) => {
                    e.preventDefault();
                    const pageId = link.getAttribute('data-page-id');
                    const pageIndex = getPageIndexById(pageId);
                    if (pageIndex !== -1) {
                        currentPage = pageIndex;
                        updateForm();
                    }
                });
            });

            document.getElementById('prevButton').addEventListener('click', () => {
                if (currentPage > 0) currentPage--;
                updateForm();
            });

            // Función para resaltar el contenedor seleccionado
            function updateVehicleSelection() {
                document.querySelectorAll('.radio-group label').forEach(label => {
                    label.classList.remove('selected');
                });
                const selectedRadio = document.querySelector('input[name="vehicle_type"]:checked');
                if (selectedRadio) {
                    selectedRadio.parentElement.classList.add('selected');
                }
            }

// Inicializar formulario y resaltar selección inicial
updateForm();
updateVehicleSelection();
updateAdditionalOptionsVisibility(); // Añadido
updateDocumentLabels(); // Añadido para el siguiente paso


// Escuchar cambios en el tipo de vehículo
const vehicleTypeRadios = document.querySelectorAll('input[name="vehicle_type"]');
vehicleTypeRadios.forEach(radio => {
    radio.addEventListener('change', () => {
        updateVehicleSelection();
        updateAdditionalOptionsVisibility(); // Añadido
        updateDocumentLabels(); // Añadido para el siguiente paso
    });
});


            // Variables para la firma digital
            let signaturePad;
            const signatureCanvas = document.getElementById('signature-pad');
            const clearSignatureButton = document.getElementById('clear-signature');

            // Inicializar Signature Pad
            function initializeSignaturePad() {
                if (!signaturePad) {
                    signaturePad = new SignaturePad(signatureCanvas);
                }
            }

            // Limpiar la firma
            clearSignatureButton.addEventListener('click', function() {
                if (signaturePad) {
                    signaturePad.clear();
                }
            });

            // Función para generar el documento de autorización
            function generateAuthorizationDocument() {
                const authorizationDiv = document.getElementById('authorization-document');
                const customerName = document.getElementById('customer_name').value.trim();
                const customerDNI = document.getElementById('customer_dni').value.trim();
                const vehicleType = document.querySelector('input[name="vehicle_type"]:checked').value;
                const manufacturer = document.getElementById('manufacturer').value;
                const model = document.getElementById('model').value;
                const matriculationDate = document.getElementById('matriculation_date').value;

                // Obtener valores de los campos adicionales
                const nuevoNombre = document.getElementById('nuevo_nombre').value.trim();
                const nuevoPuerto = document.getElementById('nuevo_puerto').value.trim();

                let authorizationHTML = `
                    <p>Yo, <strong>${customerName}</strong>, con DNI <strong>${customerDNI}</strong>, autorizo a TRAMITFY S.L. (CIF B55388557) a realizar en mi nombre la transferencia de propiedad del siguiente vehículo:</p>
                    <ul>
                        <li><strong>Tipo de Vehículo:</strong> ${vehicleType}</li>
                        <li><strong>Fabricante:</strong> ${manufacturer}</li>
                        <li><strong>Modelo:</strong> ${model}</li>
                        <li><strong>Fecha de Matriculación:</strong> ${matriculationDate}</li>
                `;

                if (nuevoNombre) {
                    authorizationHTML += `<li><strong>Nuevo Nombre:</strong> ${nuevoNombre}</li>`;
                }

                if (nuevoPuerto) {
                    authorizationHTML += `<li><strong>Nuevo Puerto:</strong> ${nuevoPuerto}</li>`;
                }

                authorizationHTML += `</ul><p>Firmo a continuación en señal de conformidad.</p>`;

                authorizationDiv.innerHTML = authorizationHTML;
            }

// Función para enviar correos
function sendEmails() {
    const formData = new FormData();
    formData.append('action', 'send_emails');
    formData.append('customer_email', purchaseDetails.customerEmail);
    formData.append('customer_name', purchaseDetails.customerName);
    formData.append('customer_dni', purchaseDetails.customerDNI);
    formData.append('customer_phone', purchaseDetails.customerPhone); // Línea añadida
    formData.append('service_details', purchaseDetails.options.join(', '));
    formData.append('payment_amount', purchaseDetails.totalAmount);
    formData.append('nuevo_nombre', purchaseDetails.nuevoNombre);
    formData.append('nuevo_puerto', purchaseDetails.nuevoPuerto);

    // Depuración: Verificar que el teléfono se está agregando
    console.log('Enviando teléfono:', formData.get('customer_phone'));

    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('Correos enviados exitosamente.');
        } else {
            console.log('Error al enviar los correos.');
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}

            // Manejar el popup de ejemplo de documentos
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

            // Función para mostrar u ocultar los campos adicionales
            function updateAdditionalInputs() {
                const cambioNombreCheckbox = document.querySelector('input[value="Cambio de nombre"]');
                const cambioPuertoCheckbox = document.querySelector('input[value="Cambio de puerto base"]');
                const nombreInputDiv = document.getElementById('nombre-input');
                const puertoInputDiv = document.getElementById('puerto-input');

                if (cambioNombreCheckbox.checked) {
                    nombreInputDiv.style.display = 'block';
                } else {
                    nombreInputDiv.style.display = 'none';
                }

                if (cambioPuertoCheckbox.checked) {
                    puertoInputDiv.style.display = 'block';
                } else {
                    puertoInputDiv.style.display = 'none';
                }
            }

            // Llamar a la función al cargar la página
            updateAdditionalInputs();

            // Añadir eventos a los checkboxes
            extraOptions.forEach(option => {
                option.addEventListener('change', function() {
                    updateAdditionalInputs();
                    updateTotal();
                });
            });

        });
    </script>

    <?php
    return ob_get_clean();
}
add_shortcode('custom_form', 'custom_form_shortcode');

// Endpoint para crear el Payment Intent
add_action('wp_ajax_create_payment_intent', 'create_payment_intent');
add_action('wp_ajax_nopriv_create_payment_intent', 'create_payment_intent');

function create_payment_intent() {
    // Incluir la librería de Stripe
    require_once __DIR__ . '/vendor/stripe/stripe-php/init.php';

    \Stripe\Stripe::setApiKey('YOUR_STRIPE_LIVE_SECRET_KEY_HERE'); // Reemplaza con tu clave secreta

    $amount = isset($_POST['amount']) ? intval($_POST['amount']) : 0;

    try {
        $paymentIntent = \Stripe\PaymentIntent::create([
            'amount' => $amount,
            'currency' => 'eur',
            'payment_method_types' => ['card'], // Aceptar solo pagos con tarjeta
        ]);

        echo json_encode([
            'clientSecret' => $paymentIntent->client_secret,
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'error' => $e->getMessage(),
        ]);
    }

    wp_die();
}


// Función para enviar correos tras el pago
add_action('wp_ajax_send_emails', 'send_emails');
add_action('wp_ajax_nopriv_send_emails', 'send_emails');

function send_emails() {
    // Obtener y sanitizar los datos enviados por POST
    $customer_email = sanitize_email($_POST['customer_email']);
    $admin_email = get_option('admin_email');
    $customer_name = sanitize_text_field($_POST['customer_name']);
    $customer_dni = sanitize_text_field($_POST['customer_dni']);
    $customer_phone = sanitize_text_field($_POST['customer_phone']);
    $payment_amount = sanitize_text_field($_POST['payment_amount']);
    $nuevo_nombre = sanitize_text_field($_POST['nuevo_nombre']);
    $nuevo_puerto = sanitize_text_field($_POST['nuevo_puerto']);

    // Obtener las opciones extras seleccionadas
    $opciones_extras = isset($_POST['service_details']) ? explode(', ', sanitize_text_field($_POST['service_details'])) : [];

    // Establecer encabezados personalizados
    $headers = [];
    $headers[] = 'Content-Type: text/html; charset=UTF-8';
    $headers[] = 'From: info@tramitfy.es';

    // Construir el mensaje para el cliente
    $subject_customer = 'Confirmación de pago recibido';
    $message_customer = '<!DOCTYPE html>
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
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <img src="https://www.tramitfy.es/wp-content/uploads/LOGO.png" alt="Tramitfy Logo">
                <h2 style="color: #016d86;">Confirmación de Pago Recibido</h2>
            </div>
            <div class="content">
                <p>Estimado <strong>' . htmlspecialchars($customer_name) . '</strong>,</p>
                <p>Gracias por su confianza en nuestros servicios. Le confirmamos que hemos recibido su pago correctamente y enviado automáticamente su trámite a la administración pública competente.</p>
                <h3>Detalles de su trámite:</h3>
                <ul>
                    <li><strong>Servicio contratado:</strong> Transferencia de propiedad</li>';

    if (!empty($opciones_extras)) {
        $message_customer .= '<li><strong>Opciones adicionales contratadas:</strong> ' . htmlspecialchars(implode(', ', $opciones_extras)) . '</li>';
    }

    $message_customer .= '
                    <li><strong>Importe pagado:</strong> ' . htmlspecialchars($payment_amount) . ' € (incluye ITP, tasas y honorarios)</li>';

    if (!empty($nuevo_nombre)) {
        $message_customer .= '<li><strong>Nuevo Nombre:</strong> ' . htmlspecialchars($nuevo_nombre) . '</li>';
    }
    if (!empty($nuevo_puerto)) {
        $message_customer .= '<li><strong>Nuevo Puerto:</strong> ' . htmlspecialchars($nuevo_puerto) . '</li>';
    }

    $message_customer .= '
                </ul>
                <p>Le facilitaremos la documentación por correo electrónico tan pronto la recibamos.</p>
                <p>Atentamente,<br>El equipo de <strong>Tramitfy</strong></p>
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

    // Enviar el correo al cliente
    wp_mail($customer_email, $subject_customer, $message_customer, $headers);

    // Construir el mensaje para el administrador
    $subject_admin = 'Nuevo pago recibido';
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
            .content {
                padding: 20px;
                background-color: #ffffff;
                border-radius: 8px;
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
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h2 style="color: #016d86;">Nuevo Pago Recibido</h2>
            </div>
            <div class="content">
                <p>Se ha recibido un nuevo pago con los siguientes detalles:</p>
                <h3>Datos del cliente:</h3>
                <ul>
                    <li><strong>Nombre completo:</strong> ' . htmlspecialchars($customer_name) . '</li>
                    <li><strong>DNI:</strong> ' . htmlspecialchars($customer_dni) . '</li>
                    <li><strong>Email:</strong> ' . htmlspecialchars($customer_email) . '</li>
                    <li><strong>Teléfono:</strong> ' . htmlspecialchars($customer_phone) . '</li>
                </ul>
                <h3>Detalles del trámite:</h3>
                <ul>
                    <li><strong>Servicio contratado:</strong> Transferencia de propiedad</li>';

    if (!empty($opciones_extras)) {
        $message_admin .= '<li><strong>Opciones adicionales contratadas:</strong> ' . htmlspecialchars(implode(', ', $opciones_extras)) . '</li>';
    }

    $message_admin .= '
                    <li><strong>Importe pagado:</strong> ' . htmlspecialchars($payment_amount) . ' € (incluye ITP, tasas y honorarios)</li>';

    if (!empty($nuevo_nombre)) {
        $message_admin .= '<li><strong>Nuevo Nombre:</strong> ' . htmlspecialchars($nuevo_nombre) . '</li>';
    }
    if (!empty($nuevo_puerto)) {
        $message_admin .= '<li><strong>Nuevo Puerto:</strong> ' . htmlspecialchars($nuevo_puerto) . '</li>';
    }

    $message_admin .= '
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

    // Enviar el correo al administrador
    wp_mail($admin_email, $subject_admin, $message_admin, $headers);

    wp_send_json_success('Correos enviados.');
    wp_die();
}
// Función para manejar el envío final del formulario
add_action('wp_ajax_submit_form', 'submit_form');
add_action('wp_ajax_nopriv_submit_form', 'submit_form');

function submit_form() {
    // Validar y procesar los datos enviados
    $customer_name = sanitize_text_field($_POST['customer_name']);
    $customer_dni = sanitize_text_field($_POST['customer_dni']);
    $customer_email = sanitize_email($_POST['customer_email']);
    $customer_phone = sanitize_text_field($_POST['customer_phone']);
    $vehicle_type = sanitize_text_field($_POST['vehicle_type']);
    $manufacturer = sanitize_text_field($_POST['manufacturer']);
    $model = sanitize_text_field($_POST['model']);
    $matriculation_date = sanitize_text_field($_POST['matriculation_date']);
    $purchase_price = floatval($_POST['purchase_price']);
    $region = sanitize_text_field($_POST['region']);

    // Procesar los campos adicionales
    $nuevo_nombre = isset($_POST['nuevo_nombre']) ? sanitize_text_field($_POST['nuevo_nombre']) : '';
    $nuevo_puerto = isset($_POST['nuevo_puerto']) ? sanitize_text_field($_POST['nuevo_puerto']) : '';

    // Obtener las opciones extras
    $opciones_extras = isset($_POST['extra-option']) ? $_POST['extra-option'] : [];

    $signature = $_POST['signature'];

    // Procesar la firma
    $signature_data = str_replace('data:image/png;base64,', '', $signature);
    $signature_data = str_replace(' ', '+', $signature_data);
    $signature_data = base64_decode($signature_data);

    $upload_dir = wp_upload_dir();
    $signature_image_name = 'signature_' . time() . '.png';
    $signature_image_path = $upload_dir['path'] . '/' . $signature_image_name;
    file_put_contents($signature_image_path, $signature_data);

    // Cargar el precio base desde el CSV correspondiente
    $csv_file = $vehicle_type === 'Moto de Agua' ? 'MOTO.csv' : 'data.csv';
    $csv_path = get_template_directory() . '/' . $csv_file;

    $base_price = 0;

    if (($handle = fopen($csv_path, 'r')) !== FALSE) {
        fgetcsv($handle, 1000, ','); // Saltar la primera línea si es el encabezado
        while (($row = fgetcsv($handle, 1000, ',')) !== FALSE) {
            list($csv_manufacturer, $csv_model, $csv_price) = $row;
            if ($csv_manufacturer === $manufacturer && $csv_model === $model) {
                $base_price = floatval($csv_price);
                break;
            }
        }
        fclose($handle);
    }

    // Si no se encuentra el precio base, enviar error
    if ($base_price === 0) {
        wp_send_json_error('No se pudo encontrar el precio base del vehículo seleccionado.');
        wp_die();
    }

    // Cálculo del ITP
    $itp_rates = [
        "Andalucía" => 0.04,
        "Aragón" => 0.04,
        "Asturias" => 0.04,
        "Islas Baleares" => 0.04,
        "Canarias" => 0.055,
        "Cantabria" => 0.08,
        "Castilla-La Mancha" => 0.06,
        "Castilla y León" => 0.05,
        "Cataluña" => 0.05,
        "Comunidad Valenciana" => 0.06,
        "Extremadura" => 0.06,
        "Galicia" => 0.03,
        "Madrid" => 0.04,
        "Murcia" => 0.04,
        "Navarra" => 0.04,
        "País Vasco" => 0.04,
        "La Rioja" => 0.04,
        "Ceuta" => 0.02,
        "Melilla" => 0.04
    ];

    $depreciation_rates = [
        ['years' => 0, 'rate' => 100],
        ['years' => 1, 'rate' => 84],
        ['years' => 2, 'rate' => 67],
        ['years' => 3, 'rate' => 56],
        ['years' => 4, 'rate' => 47],
        ['years' => 5, 'rate' => 39],
        ['years' => 6, 'rate' => 34],
        ['years' => 7, 'rate' => 28],
        ['years' => 8, 'rate' => 24],
        ['years' => 9, 'rate' => 19],
        ['years' => 10, 'rate' => 17],
        ['years' => 11, 'rate' => 13],
        ['years' => 12, 'rate' => 12],
        ['years' => 13, 'rate' => 11],
        ['years' => 14, 'rate' => 10],
        ['years' => 15, 'rate' => 10]
    ];

    // Calcular la antigüedad del vehículo
    $matriculation_date_time = strtotime($matriculation_date);
    $current_date_time = time();
    $years_difference = date('Y', $current_date_time) - date('Y', $matriculation_date_time);
    if (date('md', $current_date_time) < date('md', $matriculation_date_time)) {
        $years_difference--;
    }
    $years_difference = max(0, $years_difference);

    // Obtener el porcentaje de depreciación
    $depreciation_percentage = 10; // Valor mínimo por defecto
    foreach ($depreciation_rates as $rate) {
        if ($years_difference <= $rate['years']) {
            $depreciation_percentage = $rate['rate'];
            break;
        }
    }

    // Calcular el valor fiscal con depreciación
    $fiscal_value = $base_price * ($depreciation_percentage / 100);

    // Base imponible para el ITP (mayor entre precio de compra y valor fiscal)
    $tax_base = max($purchase_price, $fiscal_value);

    // Obtener el tipo impositivo según la región
    $tax_rate = isset($itp_rates[$region]) ? $itp_rates[$region] : 0;

    // Calcular el ITP
    $calculated_itp = $tax_base * $tax_rate;

    // Generar el PDF de autorización
    require_once get_template_directory() . '/vendor/fpdf/fpdf.php';
    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', '', 12);

// Agregar la fecha en la esquina superior derecha
$pdf->Cell(0, 10, 'Fecha: ' . date('d/m/Y'), 0, 0, 'R');
$pdf->Ln(10);

    $pdf->Cell(0, 10, utf8_decode('Autorización para Transferencia de Propiedad'), 0, 1, 'C');
    $pdf->Ln(10);
    $texto = "Yo, $customer_name, con DNI $customer_dni, autorizo a TRAMITFY S.L. (CIF B55388557) a realizar en mi nombre la transferencia de propiedad del siguiente vehículo:";
    $pdf->MultiCell(0, 10, utf8_decode($texto), 0, 'J');
    $pdf->Ln(5);

    $pdf->Cell(0, 10, utf8_decode('Datos del Vehículo:'), 0, 1);
    $pdf->Cell(0, 10, utf8_decode('Tipo de Vehículo: ' . $vehicle_type), 0, 1);
    $pdf->Cell(0, 10, utf8_decode('Fabricante: ' . $manufacturer), 0, 1);
    $pdf->Cell(0, 10, utf8_decode('Modelo: ' . $model), 0, 1);
    $pdf->Cell(0, 10, utf8_decode('Fecha de Matriculación: ' . $matriculation_date), 0, 1);

    if (!empty($nuevo_nombre)) {
        $pdf->Cell(0, 10, utf8_decode('Nuevo Nombre: ' . $nuevo_nombre), 0, 1);
    }

    if (!empty($nuevo_puerto)) {
        $pdf->Cell(0, 10, utf8_decode('Nuevo Puerto: ' . $nuevo_puerto), 0, 1);
    }

    $pdf->Ln(10);

    $pdf->Cell(0, 10, utf8_decode('Firma:'), 0, 1);
    $pdf->Image($signature_image_path, $pdf->GetX(), $pdf->GetY(), 50, 30);

    $authorization_pdf_name = 'autorizacion_' . time() . '.pdf';
    $authorization_pdf_path = $upload_dir['path'] . '/' . $authorization_pdf_name;
    $pdf->Output('F', $authorization_pdf_path);

    // Eliminar imagen de firma temporal
    unlink($signature_image_path);

    // Enviar correo al administrador con todos los datos y archivos adjuntos
    $admin_email = get_option('admin_email');
    $subject_admin = 'Nuevo formulario enviado';

    // Construir el mensaje para el administrador con estilo
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
            .highlight {
                background-color: #e9f7ff;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <img src="https://www.tramitfy.es/wp-content/uploads/LOGO.png" alt="Tramitfy Logo">
                <h2 style="color: #016d86;">Nuevo Formulario Enviado</h2>
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
                </table>
                <h3>Datos del Vehículo:</h3>
                <table class="details-table">
                    <tr>
                        <th>Tipo de Vehículo:</th>
                        <td>' . htmlspecialchars($vehicle_type) . '</td>
                    </tr>
                    <tr>
                        <th>Fabricante:</th>
                        <td>' . htmlspecialchars($manufacturer) . '</td>
                    </tr>
                    <tr>
                        <th>Modelo:</th>
                        <td>' . htmlspecialchars($model) . '</td>
                    </tr>
                    <tr>
                        <th>Fecha de Matriculación:</th>
                        <td>' . htmlspecialchars($matriculation_date) . '</td>
                    </tr>
                    <tr>
                        <th>Precio de Compra:</th>
                        <td>' . number_format($purchase_price, 2, ',', '.') . ' €</td>
                    </tr>
                    <tr>
                        <th>Comunidad Autónoma:</th>
                        <td>' . htmlspecialchars($region) . '</td>
                    </tr>
                    <tr class="highlight">
                        <th>ITP Calculado:</th>
                        <td>' . number_format($calculated_itp, 2, ',', '.') . ' €</td>
                    </tr>';
    if (!empty($nuevo_nombre)) {
        $message_admin .= '
                    <tr>
                        <th>Nuevo Nombre:</th>
                        <td>' . htmlspecialchars($nuevo_nombre) . '</td>
                    </tr>';
    }
    if (!empty($nuevo_puerto)) {
        $message_admin .= '
                    <tr>
                        <th>Nuevo Puerto:</th>
                        <td>' . htmlspecialchars($nuevo_puerto) . '</td>
                    </tr>';
    }

    $message_admin .= '
                </table>';

    if (!empty($opciones_extras)) {
        $message_admin .= '
                <h3>Opciones Extras Seleccionadas:</h3>
                <ul>';
        foreach ($opciones_extras as $opcion) {
            $message_admin .= '<li>' . htmlspecialchars($opcion) . '</li>';
        }
        $message_admin .= '</ul>';
    }

    $message_admin .= '
                <p>Se adjuntan los documentos proporcionados por el cliente.</p>
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

    // Establecer encabezados personalizados
    $headers = [];
    $headers[] = 'Content-Type: text/html; charset=UTF-8';
    $headers[] = 'From: info@tramitfy.es';

    $attachments = [$authorization_pdf_path];

// Procesar archivos subidos y añadirlos a los adjuntos
$upload_fields = ['upload_hoja_asiento', 'upload_tarjeta_moto', 'upload_dni_comprador', 'upload_dni_vendedor', 'upload_contrato_compraventa'];
foreach ($upload_fields as $field_name) {
    if (isset($_FILES[$field_name]) && $_FILES[$field_name]['error'] === UPLOAD_ERR_OK) {
        $uploaded_file = wp_handle_upload($_FILES[$field_name], ['test_form' => false]);
        if (isset($uploaded_file['file'])) {
            $attachments[] = $uploaded_file['file'];
        }
    }
}


    wp_mail($admin_email, $subject_admin, $message_admin, $headers, $attachments);

    wp_send_json_success('Formulario procesado correctamente.');
    wp_die();
}
