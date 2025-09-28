<?php
// Asegurarse de que el archivo no sea accedido directamente
defined('ABSPATH') || exit;

/**
 * Función principal para generar y mostrar el formulario en el frontend
 */
function navigation_permit_renewal_form_shortcode() {
    // Encolar los scripts y estilos necesarios
    wp_enqueue_style('navigation-permit-renewal-form-style', get_template_directory_uri() . '/style.css', array(), filemtime(get_template_directory() . '/style.css'));
    wp_enqueue_script('stripe', 'https://js.stripe.com/v3/', array(), null, false);
    wp_enqueue_script('signature-pad', 'https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js', array(), null, false);

    // Iniciar el buffering de salida
    ob_start();
    ?>

    <!-- Estilos personalizados para el formulario -->
    <style>
        /* [CAMBIO 1] Añadido margen superior para evitar que tape el menú */
        body {
            padding-top: 0 !important; /* Evitar conflictos con padding del body */
        }
        
        /* Estilos generales para el formulario */
        #navigation-permit-renewal-form {
            max-width: 1000px;
            margin: 120px auto 40px auto; /* [CAMBIO 2] Margen superior aumentado */
            padding: 30px;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            background-color: #ffffff;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            position: relative; /* [CAMBIO 3] Añadido para control de z-index */
            z-index: 1; /* [CAMBIO 4] Z-index bajo para no interferir con el menú */
        }

        #navigation-permit-renewal-form label {
            font-weight: normal;
            display: block;
            margin-top: 15px;
            margin-bottom: 5px;
            color: #555555;
        }

        #navigation-permit-renewal-form input[type="text"],
        #navigation-permit-renewal-form input[type="tel"],
        #navigation-permit-renewal-form input[type="email"],
        #navigation-permit-renewal-form input[type="file"],
        #navigation-permit-renewal-form select {
            width: 100%;
            padding: 12px;
            margin-top: 0px;
            border-radius: 5px;
            border: 1px solid #cccccc;
            font-size: 16px;
            background-color: #f9f9f9;
        }

        #navigation-permit-renewal-form .button {
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

        #navigation-permit-renewal-form .button:hover {
            background-color: #218838;
        }

        #navigation-permit-renewal-form .hidden {
            display: none;
        }

        /* Estilos para el menú de navegación */
        /* [CAMBIO 5] Cambiado el nombre para evitar conflictos */
        #permit-form-navigation {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            margin-bottom: 30px;
            align-items: center;
            background-color: #f1f1f1;
            padding: 15px;
            border-radius: 8px;
        }

        #permit-form-navigation a {
            color: #016d86;
            text-decoration: none;
            font-weight: bold;
            position: relative;
            padding: 8px 15px;
            transition: color 0.3s ease;
        }

        #permit-form-navigation a.active {
            color: #016d86;
            text-decoration: underline;
        }

        #permit-form-navigation a:not(:last-child)::after {
            content: '➔';
            position: absolute;
            top: 50%;
            right: -10px;
            transform: translateY(-50%);
            font-size: 16px;
            color: #016d86;
        }

        #permit-form-navigation a:hover {
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
        /* [CAMBIO 6] Z-index reducido */
        #document-popup {
            display: none;
            position: fixed;
            z-index: 500; /* Reducido de 1001 */
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

        /* Estilos para mensajes de error de Stripe Elements */
        .StripeElement--invalid {
            border-color: #dc3545;
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
        /* [CAMBIO 7] Z-index reducido */
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
            z-index: 500; /* Reducido de 1000 */
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

        /* Estilos para mensajes de error */
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

        /* Responsividad para teléfonos (ancho máximo de 480px) */
        /* [CAMBIO 8] Ajustes en media queries */
        @media (max-width: 480px) {
            #navigation-permit-renewal-form {
                margin: 80px auto 20px auto; /* Margen superior para móvil */
                padding: 15px;
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

            .upload-item label,
            .upload-item input[type="file"],
            .upload-item .view-example {
                flex: 1 1 100%;
                margin-bottom: 5px;
            }

            .upload-item .view-example {
                margin-left: 0;
            }

            #permit-form-navigation {
                padding: 10px;
                font-size: 14px;
            }

            .button {
                font-size: 16px;
                padding: 10px;
            }

            #signature-pad {
                height: 120px;
            }
        }

        /* Media Query para tablet (ancho entre 481px y 768px) */
        @media (min-width: 481px) and (max-width: 768px) {
            #navigation-permit-renewal-form {
                max-width: 90%;
                margin: 100px auto 30px auto;
                padding: 25px;
            }
            #permit-form-navigation {
                padding: 12px;
            }
            .button {
                font-size: 17px;
                padding: 12px;
            }
            #signature-pad {
                height: 180px;
            }
            .upload-item {
                flex-direction: row;
                align-items: center;
            }
        }
    </style>

    <!-- Formulario principal -->
    <form id="navigation-permit-renewal-form" action="" method="POST" enctype="multipart/form-data">

        <!-- Panel de Auto-rellenado para Administradores -->
        <?php if (current_user_can('administrator')): ?>
        <div class="admin-autofill-panel" style="background: #f0f9ff; border: 2px solid #0ea5e9; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <p style="margin: 0 0 10px 0; font-size: 14px; font-weight: bold; color: #0ea5e9;">
                🔧 MODO ADMINISTRADOR
            </p>
            <button type="button" id="admin-autofill-btn" class="btn-primary" style="padding: 10px 20px; background: #0ea5e9; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: bold;">
                ⚡ Auto-rellenar Formulario (Solo Datos)
            </button>
            <p style="margin: 8px 0 0 0; font-size: 12px; color: #64748b;">
                Rellena automáticamente todos los campos y llega hasta el resumen. Stripe se maneja independientemente.
            </p>
        </div>
        <?php endif; ?>

        <!-- Mensajes de error -->
        <div id="error-messages"></div>

        <!-- [CAMBIO 9] ID cambiado para evitar conflictos -->
        <!-- Navegación del formulario -->
        <div id="permit-form-navigation">
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

            <label for="renewal_type">Tipo de Renovación:</label>
            <select id="renewal_type" name="renewal_type" required>
                <option value="">Seleccione una opción</option>
                <option value="caducidad">Renovación por caducidad</option>
                <option value="perdida">Renovación por pérdida</option>
            </select>
        </div>

        <!-- Página de Documentación -->
        <div id="page-documents" class="form-page hidden">
            <h3>Adjuntar Documentación</h3>
            <p>Por favor, sube los siguientes documentos. Puedes ver un ejemplo haciendo clic en "Ver ejemplo"...</p>
            <div class="upload-section">
                <div class="upload-item">
                    <label for="upload-dni-propietario">DNI del propietario</label>
                    <input type="file" id="upload-dni-propietario" name="upload_dni_propietario" required>
                    <a href="#" class="view-example" data-doc="dni-comprador">Ver ejemplo</a>
                </div>
                <div class="upload-item">
                    <label for="upload-hoja-asiento">Registro marítimo</label>
                    <input type="file" id="upload-hoja-asiento" name="upload_hoja_asiento" required>
                    <a href="#" class="view-example" data-doc="hoja-asiento">Ver ejemplo</a>
                </div>
                <div class="upload-item" id="permiso-caducado-section">
                    <label for="upload-permiso-caducado">Permiso de navegación que va a caducar</label>
                    <input type="file" id="upload-permiso-caducado" name="upload_permiso_caducado" required>
                    <a href="#" class="view-example" data-doc="permiso-caducado">Ver ejemplo</a>
                </div>
            </div>

            <h3>Autorización</h3>
            <div class="document-sign-section">
                <p>Por favor, lee el siguiente documento y firma...</p>
                <div id="authorization-document" style="background-color:#f9f9f9; padding:20px; border:1px solid #e0e0e0;">
                    <!-- Documento dinámico -->
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
                <p><strong>Renovación permiso de navegación:</strong> <span style="float:right;">65,00 €</span></p>
                <p><strong>Incluye:</strong></p>
                <ul>
                    <li>Tasas y honorarios - 57,61 €</li>
                    <li>IVA (21%) - 7,38 €</li>
                </ul>

                <!-- [NUEVO - CUPÓN] Campo de cupón y total con descuento -->
                <p id="discount-line" style="display:none;">
                    <strong>Descuento:</strong>
                    <span style="float:right;" id="discount-amount"></span>
                </p>
                <p><strong>Total a pagar:</strong> 
                   <span style="float:right;" id="final-amount">65.00 €</span>
                </p>
                <!-- [/NUEVO - CUPÓN] -->
            </div>

            <!-- [NUEVO - CUPÓN] Añadimos el input y mensaje para el cupón -->
            <div class="coupon-container" style="margin-top: 20px;">
                <label for="coupon_code">Cupón de descuento (opcional):</label>
                <input type="text" id="coupon_code" name="coupon_code" placeholder="Ingresa tu cupón" />
                <p id="coupon-message" class="hidden" style="margin-top:10px;"></p>
            </div>
            <!-- [/NUEVO - CUPÓN] -->

            <div id="payment-form">
                <div id="payment-element"></div>
                <div id="payment-message" class="hidden"></div>
                <div class="terms-container">
                    <label>
                        <input type="checkbox" name="terms_accept_pago" required> 
                        Acepto los <a href="https://tramitfy.es/terminos-y-condiciones-de-uso/" target="_blank">términos y condiciones de pago</a>.
                    </label>
                </div>
                <button id="submit" class="button">Pagar</button>
            </div>
        </div>

        <div class="button-container" id="main-button-container">
            <button type="button" class="button" id="prevButtonMain" style="display: none;">Anterior</button>
            <button type="button" class="button" id="nextButtonMain">Siguiente</button>
        </div>
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
        // [CAMBIO 10] Envolver todo en un IIFE para evitar conflictos globales
        (function() {
            document.addEventListener('DOMContentLoaded', function() {
                // Variables para Stripe
                let stripe;
                let elements;
                let clientSecret;

                // [NUEVO - CUPÓN] Variables de precio para manejar el cupón
                let basePrice = 65.00;  // Precio base (65€)
                let currentPrice = basePrice;
                let discountApplied = 0;  // % de descuento
                let discountAmount = 0;   // Euros descontados
                let couponTimeout = null; // Debounce

                async function initializeStripe(customAmount = null) {
                    // Si hay descuento, customAmount vendrá con el precio final
                    const amountToCharge = (customAmount !== null) ? customAmount : currentPrice;
                    const totalAmountCents = Math.round(amountToCharge * 100);

                    // Clave pública (pk_...)
                    stripe = Stripe('<?php echo 'YOUR_STRIPE_LIVE_PUBLIC_KEY_HERE'; ?>');

                    // Crear Payment Intent en el servidor
                    const response = await fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `action=create_payment_intent_navigation_permit_renewal&amount=${totalAmountCents}`
                    });

                    const result = await response.json();

                    if (result.error) {
                        throw new Error(result.error);
                    }

                    clientSecret = result.clientSecret;

                    const appearance = {
                        theme: 'flat',
                        variables: {
                            colorPrimary: '#016d86',
                            colorBackground: '#ffffff',
                            colorText: '#333333',
                            colorDanger: '#dc3545',
                            fontFamily: 'Arial, sans-serif',
                            spacingUnit: '4px',
                            borderRadius: '4px',
                        },
                        rules: {
                            '.Label': {
                                color: '#555555',
                                fontSize: '14px',
                                marginBottom: '4px',
                            },
                            '.Input': {
                                padding: '12px',
                                border: '1px solid #cccccc',
                                borderRadius: '4px',
                            },
                            '.Input:focus': {
                                borderColor: '#016d86',
                            },
                            '.Input--invalid': {
                                borderColor: '#dc3545',
                            },
                        }
                    };

                    elements = stripe.elements({ appearance, clientSecret });

                    const paymentElementOptions = {
                        paymentMethodOrder: ['card'],
                    };

                    const paymentElement = elements.create('payment', paymentElementOptions);
                    paymentElement.mount('#payment-element');
                }

                // Manejo de páginas
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

                    if (formPages[currentPage].id === 'page-documents') {
                        document.getElementById('main-button-container').style.display = 'none';
                        document.querySelector('#page-documents .button-container').style.display = 'flex';
                    } else {
                        document.getElementById('main-button-container').style.display = 'flex';
                        if (document.querySelector('#page-documents .button-container')) {
                            document.querySelector('#page-documents .button-container').style.display = 'none';
                        }
                    }

                    // En la página inicial, no mostramos el botón Anterior
                    // En la página de pago, no mostramos el botón Anterior (aunque no sea la página inicial)
                    document.getElementById('prevButtonMain').style.display =
                        (currentPage === 0 || formPages[currentPage].id === 'page-payment') ? 'none' : 'inline-block';

                    const nextButton = document.getElementById('nextButtonMain');
                    if (currentPage === formPages.length - 1) {
                        nextButton.style.display = 'none';
                    } else {
                        nextButton.textContent = 'Siguiente';
                        nextButton.style.display = 'inline-block';
                    }

                    // Inicializar Stripe en la página de pago
                    if (formPages[currentPage].id === 'page-payment' && !stripe) {
                        initializeStripe().catch(error => {
                            alert('Error al inicializar el pago: ' + error.message);
                        });
                        handlePayment();
                    }

                    // Generar el documento de autorización
                    if (formPages[currentPage].id === 'page-documents') {
                        generateAuthorizationDocument();
                    }

                    // Mostrar/ocultar "permiso-caducado"
                    const renewalType = document.getElementById('renewal_type').value;
                    const permisoCaducadoSection = document.getElementById('permiso-caducado-section');
                    if (renewalType === 'perdida') {
                        permisoCaducadoSection.style.display = 'none';
                        document.getElementById('upload-permiso-caducado').required = false;
                    } else {
                        permisoCaducadoSection.style.display = 'flex';
                        document.getElementById('upload-permiso-caducado').required = true;
                    }
                }

                function generateAuthorizationDocument() {
                    const authorizationDiv = document.getElementById('authorization-document');
                    const customerName = document.getElementById('customer_name').value.trim();
                    const customerDNI = document.getElementById('customer_dni').value.trim();
                    const renewalTypeText = document.getElementById('renewal_type').selectedOptions[0].text;

                    let authorizationHTML = `
                        <p>Yo, <strong>${customerName}</strong>, con DNI <strong>${customerDNI}</strong>, autorizo a Tramitfy S.L. (CIF B55388557) a realizar en mi nombre los trámites necesarios para la renovación de mi permiso de navegación por: <strong>${renewalTypeText}</strong>.</p>
                        <p>Firmo a continuación en señal de conformidad.</p>
                    `;
                    authorizationDiv.innerHTML = authorizationHTML;
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
                        document.getElementById('loading-overlay').style.display = 'flex';

                        try {
                            const { error } = await stripe.confirmPayment({
                                elements,
                                confirmParams: {
                                    payment_method_data: {
                                        billing_details: {
                                            name: document.getElementById('customer_name').value,
                                            email: document.getElementById('customer_email').value,
                                            phone: document.getElementById('customer_phone').value
                                        }
                                    },
                                    return_url: window.location.href
                                },
                                redirect: 'if_required'
                            });

                            if (error) {
                                throw new Error(error.message);
                            } else {
                                document.getElementById('payment-message').textContent = 'Pago realizado con éxito.';
                                document.getElementById('payment-message').classList.add('success');
                                document.getElementById('payment-message').classList.remove('hidden');
                                handleFinalSubmission();
                            }
                        } catch (error) {
                            document.getElementById('payment-message').textContent = 'Error al procesar el pago: ' + error.message;
                            document.getElementById('payment-message').classList.add('error');
                            document.getElementById('payment-message').classList.remove('hidden');
                            submitButton.disabled = false;
                            document.getElementById('loading-overlay').style.display = 'none';
                        }
                    });
                }

                function handleFinalSubmission() {
                    if (signaturePad && signaturePad.isEmpty()) {
                        alert('Por favor, firme antes de enviar el formulario.');
                        document.getElementById('loading-overlay').style.display = 'none';
                        return;
                    }

                    let formData = new FormData(document.getElementById('navigation-permit-renewal-form'));
                    formData.append('action', 'submit_form_navigation_permit_renewal');

                    // Añadir la firma
                    formData.append('signature', signaturePad.toDataURL());

                    // [NUEVO - CUPÓN] Añadir el cupón utilizado (aunque esté vacío)
                    formData.append('coupon_used', document.getElementById('coupon_code').value.trim());

                    // Extraer el importe final del elemento (quitar el símbolo € y espacios)
                    let finalAmountText = document.getElementById('final-amount').textContent;
                    let finalAmountNumeric = parseFloat(finalAmountText.replace('€','').trim());
                    formData.append('finalAmount', finalAmountNumeric);
                    formData.append('hasSignature', 'true');
                    formData.append('renewalType', document.getElementById('renewal_type').value);
                    formData.append('couponCode', document.getElementById('coupon_code')?.value || '');
                    formData.append('termsAccept', 'true');

                    // Enviar a API Tramitfy
                    fetch('https://46-202-128-35.sslip.io/api/herramientas/permiso-navegacion/webhook', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        document.getElementById('loading-overlay').style.display = 'none';
                        if (data.success) {
                            alert(`Formulario enviado con éxito. ID del trámite: ${data.tramiteId}`);
                            window.location.href = `https://46-202-128-35.sslip.io/seguimiento/${data.id}`;
                        } else {
                            alert('Error al enviar el formulario: ' + (data.error || 'Error desconocido'));
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        document.getElementById('loading-overlay').style.display = 'none';
                        alert('Hubo un error al enviar el formulario.');
                    });
                }

                document.getElementById('nextButtonMain').addEventListener('click', () => {
                    if (!validateCurrentPage()) {
                        return;
                    }
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
                        if (!validateCurrentPage()) {
                            return;
                        }
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

                // [CAMBIO 11] Evitar conflictos con el evento window.click
                popup.addEventListener('click', function(event) {
                    if (event.target === popup) {
                        popup.style.display = 'none';
                    }
                });

                // Inicializar la firma y la primera vista
                updateForm();
                let signaturePad = new SignaturePad(document.getElementById('signature-pad'));

                document.getElementById('clear-signature').addEventListener('click', function() {
                    signaturePad.clear();
                });

                // [NUEVO - CUPÓN] Lógica para el cupón
                const couponInput = document.getElementById('coupon_code');
                const couponMessage = document.getElementById('coupon-message');
                const discountLine = document.getElementById('discount-line');
                const discountSpan = document.getElementById('discount-amount');
                const finalAmountSpan = document.getElementById('final-amount');

                couponInput.addEventListener('input', () => {
                    if (couponTimeout) {
                        clearTimeout(couponTimeout);
                    }
                    if (couponInput.value.trim() === '') {
                        resetCoupon();
                        return;
                    }

                    couponInput.classList.remove('coupon-error', 'coupon-valid');
                    couponInput.classList.add('coupon-loading');
                    couponMessage.classList.remove('success', 'error-message', 'hidden');
                    couponMessage.textContent = 'Verificando cupón...';

                    couponTimeout = setTimeout(() => {
                        validateCouponCode(couponInput.value.trim());
                    }, 1000);
                });

                function resetCoupon() {
                    couponInput.classList.remove('coupon-error', 'coupon-valid', 'coupon-loading');
                    couponMessage.textContent = '';
                    couponMessage.classList.add('hidden');
                    discountLine.style.display = 'none';
                    discountSpan.textContent = '';
                    currentPrice = basePrice;
                    finalAmountSpan.textContent = basePrice.toFixed(2) + ' €';

                    if (stripe) {
                        stripe = null;
                        document.getElementById('payment-element').innerHTML = '';
                        initializeStripe(basePrice).catch(error => {
                            console.error(error);
                        });
                    }
                }

                async function validateCouponCode(code) {
                    try {
                        const response = await fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            // [NUEVO - CUPÓN] Llamamos a un endpoint para validar cupones
                            body: `action=validate_coupon_code_navigation_permit_renewal&coupon=${encodeURIComponent(code)}`
                        });
                        const result = await response.json();

                        if (couponInput.value.trim() !== code) return;

                        if (result.success) {
                            discountApplied = result.data.discount_percent;
                            discountAmount = (basePrice * discountApplied) / 100;
                            currentPrice = basePrice - discountAmount;

                            couponMessage.textContent = 'Cupón aplicado correctamente.';
                            couponMessage.classList.remove('hidden', 'error-message');
                            couponMessage.classList.add('success');

                            couponInput.classList.remove('coupon-loading', 'coupon-error');
                            couponInput.classList.add('coupon-valid');

                            discountLine.style.display = 'block';
                            discountSpan.textContent = '- ' + discountAmount.toFixed(2) + ' €';
                            finalAmountSpan.textContent = currentPrice.toFixed(2) + ' €';

                            if (stripe) {
                                stripe = null;
                                document.getElementById('payment-element').innerHTML = '';
                            }
                            await initializeStripe(currentPrice);
                        } else {
                            couponMessage.textContent = 'Cupón inválido o expirado.';
                            couponMessage.classList.remove('hidden', 'success');
                            couponMessage.classList.add('error-message');

                            couponInput.classList.remove('coupon-loading', 'coupon-valid');
                            couponInput.classList.add('coupon-error');

                            discountLine.style.display = 'none';
                            discountSpan.textContent = '';
                            currentPrice = basePrice;
                            finalAmountSpan.textContent = basePrice.toFixed(2) + ' €';

                            if (stripe) {
                                stripe = null;
                                document.getElementById('payment-element').innerHTML = '';
                            }
                            await initializeStripe(basePrice);
                        }
                    } catch (error) {
                        console.error('Error al validar el cupón:', error);

                        couponMessage.textContent = 'Error al validar el cupón.';
                        couponMessage.classList.remove('hidden', 'success');
                        couponMessage.classList.add('error-message');

                        couponInput.classList.remove('coupon-loading', 'coupon-valid');
                        couponInput.classList.add('coupon-error');

                        discountLine.style.display = 'none';
                        discountSpan.textContent = '';
                        currentPrice = basePrice;
                        finalAmountSpan.textContent = basePrice.toFixed(2) + ' €';

                        if (stripe) {
                            stripe = null;
                            document.getElementById('payment-element').innerHTML = '';
                        }
                        await initializeStripe(basePrice);
                    }
                }
                // [/NUEVO - CUPÓN]
            });

            // [AUTO-RELLENADO PARA ADMINISTRADORES]
            <?php if (current_user_can('administrator')): ?>
            const adminAutofillBtn = document.getElementById('admin-autofill-btn');
            if (adminAutofillBtn) {
                adminAutofillBtn.addEventListener('click', function() {
                    alert('Iniciando auto-rellenado del formulario...');

                    // PASO 1: Rellenar datos personales
                    document.getElementById('customer_name').value = 'Admin Test';
                    document.getElementById('customer_dni').value = '12345678Z';
                    document.getElementById('customer_email').value = 'joanpinyol@hotmail.es';
                    document.getElementById('customer_phone').value = '682246937';
                    document.getElementById('renewal_type').value = 'caducidad';

                    // Simular firma en el canvas (automática)
                    setTimeout(() => {
                        const canvas = document.getElementById('signature-pad');
                        if (canvas) {
                            const ctx = canvas.getContext('2d');
                            ctx.font = '30px cursive';
                            ctx.fillText('Admin Test', 50, 100);
                        }
                    }, 500);

                    // Marcar checkbox de términos
                    const termsCheckbox = document.querySelector('input[name="terms_accept"]');
                    if (termsCheckbox) {
                        termsCheckbox.checked = true;
                    }

                    // Navegar automáticamente al siguiente paso
                    setTimeout(() => {
                        // Cambiar a la página de documentación
                        showPage('page-documents');

                        // Después de 1 segundo, navegar a pago
                        setTimeout(() => {
                            showPage('page-payment');
                            alert('Formulario auto-rellenado. Los archivos deben subirse manualmente y el pago se procesa con Stripe.');
                        }, 1000);
                    }, 1000);
                });
            }
            <?php endif; ?>
            // [/AUTO-RELLENADO]
        })(); // [CAMBIO 10] Fin del IIFE
    </script>

    <?php
    return ob_get_clean();
}
add_shortcode('navigation_permit_renewal_form', 'navigation_permit_renewal_form_shortcode');

// El resto del código PHP permanece igual...
/**
 * Endpoint para crear el Payment Intent
 */
add_action('wp_ajax_create_payment_intent_navigation_permit_renewal', 'create_payment_intent_navigation_permit_renewal');
add_action('wp_ajax_nopriv_create_payment_intent_navigation_permit_renewal', 'create_payment_intent_navigation_permit_renewal');

function create_payment_intent_navigation_permit_renewal() {
    // Incluir la librería de Stripe
    require_once __DIR__ . '/vendor/stripe/stripe-php/init.php';

    \Stripe\Stripe::setApiKey('YOUR_STRIPE_LIVE_SECRET_KEY_HERE'); // Reemplaza con tu clave secreta de Stripe

    $amount = isset($_POST['amount']) ? intval($_POST['amount']) : 0;

    try {
        $paymentIntent = \Stripe\PaymentIntent::create([
            'amount' => $amount,
            'currency' => 'eur',
            'payment_method_types' => ['card'], // Solo aceptar pagos con tarjeta
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

/**
 * [NUEVO - CUPÓN] Endpoint para validar el cupón
 */
add_action('wp_ajax_validate_coupon_code_navigation_permit_renewal', 'validate_coupon_code_navigation_permit_renewal');
add_action('wp_ajax_nopriv_validate_coupon_code_navigation_permit_renewal', 'validate_coupon_code_navigation_permit_renewal');

function validate_coupon_code_navigation_permit_renewal() {
    // Lista de cupones válidos
    $valid_coupons = array(
        'DESCUENTO10' => 10,
        'DESCUENTO20' => 20,
        'VERANO15'    => 15,
        'BLACK50'     => 50,
    );

    $coupon = isset($_POST['coupon']) ? sanitize_text_field($_POST['coupon']) : '';
    $coupon_upper = strtoupper($coupon);

    if (isset($valid_coupons[$coupon_upper])) {
        $discount_percent = $valid_coupons[$coupon_upper];
        wp_send_json_success(['discount_percent' => $discount_percent]);
    } else {
        wp_send_json_error('Cupón inválido o expirado');
    }
    wp_die();
}
/* [/NUEVO - CUPÓN] */

/**
 * Función para manejar el envío final del formulario
 */
add_action('wp_ajax_submit_form_navigation_permit_renewal', 'submit_form_navigation_permit_renewal');
add_action('wp_ajax_nopriv_submit_form_navigation_permit_renewal', 'submit_form_navigation_permit_renewal');

function submit_form_navigation_permit_renewal() {
    // Generar identificador único para Renovación de Permisos: TMA-RENOV-PERM-YYYYMMDD-######
    $prefix = 'TMA-RENOV-PERM';
    $counter_option = 'tma_renov_perm_counter';
    $current_cnt = get_option($counter_option, 0);
    $current_cnt++;
    update_option($counter_option, $current_cnt);
    $date_part = date('Ymd');
    $secuencial = str_pad($current_cnt, 6, '0', STR_PAD_LEFT);
    $unique_id = $prefix . '-' . $date_part . '-' . $secuencial;

    // Validar y procesar los datos enviados
    $customer_name = sanitize_text_field($_POST['customer_name']);
    $customer_dni = sanitize_text_field($_POST['customer_dni']);
    $customer_email = sanitize_email($_POST['customer_email']);
    $customer_phone = sanitize_text_field($_POST['customer_phone']);
    $renewal_type = sanitize_text_field($_POST['renewal_type']);

    // [NUEVO - CUPÓN] Recoger el cupón usado
    $coupon_used = isset($_POST['coupon_used']) ? sanitize_text_field($_POST['coupon_used']) : '';

    // Obtener el importe final (con descuento aplicado, si existe)
    $basePrice = 65.00;
    $finalAmount = isset($_POST['final_amount']) ? floatval($_POST['final_amount']) : $basePrice;

    $signature = $_POST['signature'];

    // Procesar la firma
    $signature_data = str_replace('data:image/png;base64,', '', $signature);
    $signature_data = base64_decode($signature_data);

    $upload_dir = wp_upload_dir();
    $signature_image_name = 'signature_' . time() . '.png';
    $signature_image_path = $upload_dir['path'] . '/' . $signature_image_name;
    file_put_contents($signature_image_path, $signature_data);

    // Generar el PDF de autorización con datos del cliente
    require_once get_template_directory() . '/vendor/fpdf/fpdf.php';
    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', '', 12);

    // Agregar la fecha
    $pdf->Cell(0, 10, 'Fecha: ' . date('d/m/Y'), 0, 0, 'R');
    $pdf->Ln(10);

    $pdf->Cell(0, 10, utf8_decode('Autorización para Renovación de Permiso de Navegación'), 0, 1, 'C');
    $pdf->Ln(10);
    $renewal_type_text = $renewal_type === 'caducidad' ? 'caducidad' : 'pérdida';
    $texto = "Yo, $customer_name, con DNI $customer_dni, autorizo a Tramitfy S.L. (CIF B55388557) a realizar en mi nombre los trámites necesarios para la renovación de mi permiso de navegación por $renewal_type_text.";
    $pdf->MultiCell(0, 10, utf8_decode($texto), 0, 'J');
    $pdf->Ln(10);

    $pdf->Cell(0, 10, utf8_decode('Firma:'), 0, 1);
    $pdf->Image($signature_image_path, null, null, 50, 30);

    $authorization_pdf_name = 'autorizacion_' . time() . '.pdf';
    $authorization_pdf_path = $upload_dir['path'] . '/' . $authorization_pdf_name;
    $pdf->Output('F', $authorization_pdf_path);

    // Eliminar imagen de firma temporal
    unlink($signature_image_path);

    // Procesar archivos subidos y añadirlos a los adjuntos
    $attachments = [$authorization_pdf_path];

    foreach ($_FILES as $key => $file) {
        if ($file['error'] === UPLOAD_ERR_OK) {
            $uploaded_file = wp_handle_upload($file, ['test_form' => false]);
            if (isset($uploaded_file['file'])) {
                $attachments[] = $uploaded_file['file'];
            }
        }
    }

    // Construir el mensaje para el administrador
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
                <h2 style="color: #016d86;">Nuevo Formulario de Renovación de Permiso de Navegación</h2>
            </div>
            <div class="content">
                <p>Se ha recibido un nuevo formulario de renovación de permiso de navegación con los siguientes detalles:</p>
                <table class="details-table">
                    <tr>
                        <th>Identificador:</th>
                        <td>' . htmlspecialchars($unique_id) . '</td>
                    </tr>
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
                        <th>Tipo de renovación:</th>
                        <td>' . htmlspecialchars($renewal_type) . '</td>
                    </tr>
                    <!-- [NUEVO - CUPÓN] Mostrar cupón utilizado en el correo -->
                    <tr>
                        <th>Cupón utilizado:</th>
                        <td>' . htmlspecialchars($coupon_used) . '</td>
                    </tr>
                </table>
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

    $headers = [];
    $headers[] = 'Content-Type: text/html; charset=UTF-8';
    $headers[] = 'From: info@tramitfy.es';

    $admin_email = get_option('admin_email');
    $subject_admin = 'Nuevo formulario de renovación de permiso de navegación';
    wp_mail($admin_email, $subject_admin, $message_admin, $headers, $attachments);

    // Cálculos para los datos financieros
    $fixedTasas = 16.56;
    $remaining = $finalAmount - $fixedTasas;
    $iva = $remaining * 0.21;
    $honorarios = $remaining - $iva;
    $descuento = $coupon_used ? ($basePrice - $finalAmount) : 0.00;
    $contableData = "IMPORTE TOTAL: " . number_format($finalAmount, 2, ',', '.') . " €; TASAS: " . number_format($fixedTasas, 2, ',', '.') .
        " €; DESCUENTO: " . number_format($descuento, 2, ',', '.') . " €; IVA: " . number_format($iva, 2, ',', '.') .
        " €; HONORARIOS: " . number_format($honorarios, 2, ',', '.') . " €; CUPÓN USADO: " . ($coupon_used ? $coupon_used : "N/A");

    // Correo al cliente con ID de trámite e información financiera
    $subject_client = 'Confirmación de su renovación de permiso de navegación';
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
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <img src="https://www.tramitfy.es/wp-content/uploads/LOGO.png" alt="Tramitfy Logo">
                <h2 style="color: #016d86;">Confirmación de su renovación de permiso de navegación</h2>
            </div>
            <div class="content">
                <p>Estimado/a <strong>' . htmlspecialchars($customer_name) . '</strong>,</p>
                <p>Hemos recibido su solicitud para la renovación de su permiso de navegación. Su formulario ha sido procesado exitosamente y se ha generado el siguiente identificador único:</p>
                <p style="text-align:center; font-size:20px; font-weight:bold;">' . htmlspecialchars($unique_id) . '</p>
                <p><strong>Datos contables:</strong><br>
                   ' . $contableData . '
                </p>
                <p>Le facilitaremos la documentación por correo electrónico tan pronto la recibamos.</p>
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

    /*******************************************************
     * Inserción en la base de datos (Google Drive & Google Sheets)
     *******************************************************/
    require_once __DIR__ . '/vendor/autoload.php';
    $googleCredentialsPath = __DIR__ . '/credentials.json';
    $client = new Google_Client();
    $client->setAuthConfig($googleCredentialsPath);
    $client->addScope(Google_Service_Drive::DRIVE_FILE);
    $client->addScope(Google_Service_Sheets::SPREADSHEETS);
    $driveService = new Google_Service_Drive($client);

    // Obtener o crear la carpeta en Drive para el mes actual
    $parentFolderId = '1vxHdQImalnDVI7aTaE0cGIX7m-7pl7sr';
    $yearMonth = date('Y-m');
    try {
        $query = sprintf(
            "name = '%s' and '%s' in parents and mimeType = 'application/vnd.google-apps.folder' and trashed=false",
            $yearMonth,
            $parentFolderId
        );
        $responseDrive = $driveService->files->listFiles([
            'q' => $query,
            'spaces' => 'drive',
            'fields' => 'files(id, name)'
        ]);
        if (count($responseDrive->files) > 0) {
            $folderId = $responseDrive->files[0]->id;
        } else {
            $folderMetadata = new Google_Service_Drive_DriveFile([
                'name' => $yearMonth,
                'mimeType' => 'application/vnd.google-apps.folder',
                'parents' => [$parentFolderId]
            ]);
            $createdFolder = $driveService->files->create($folderMetadata, ['fields' => 'id']);
            $folderId = $createdFolder->id;
        }
    } catch (Exception $e) {
        $folderId = null;
    }

    // Subir los archivos adjuntos a la carpeta de Drive y obtener los enlaces
    $uploadedDriveLinks = [];
    if ($folderId && !empty($attachments)) {
        foreach ($attachments as $filePath) {
            if (!file_exists($filePath)) {
                continue;
            }
            $fileName = basename($filePath);
            $driveFile = new Google_Service_Drive_DriveFile([
                'name' => $fileName,
                'parents' => [$folderId]
            ]);
            try {
                $fileContent = file_get_contents($filePath);
                $createdFile = $driveService->files->create($driveFile, [
                    'data' => $fileContent,
                    'mimeType' => mime_content_type($filePath),
                    'uploadType' => 'multipart',
                    'fields' => 'id, webViewLink'
                ]);
                $permission = new Google_Service_Drive_Permission();
                $permission->setType('anyone');
                $permission->setRole('reader');
                $driveService->permissions->create($createdFile->id, $permission);
                $uploadedDriveLinks[] = $createdFile->webViewLink;
            } catch (Exception $e) {
                // Opcional: manejo de error en la subida
            }
        }
    }

    // Inserción en Google Sheets
    try {
        $sheetsClient = new Google_Client();
        $sheetsClient->setAuthConfig($googleCredentialsPath);
        $sheetsClient->addScope(Google_Service_Sheets::SPREADSHEETS);
        $sheetsService = new Google_Service_Sheets($sheetsClient);
        $spreadsheetId = '1APFnwJ3yBfxt1M4JJcfPLOQkdIF27OXAzubW1Bx9ZbA';

        // --- Hoja "DATABASE" ---
        $clientData = "Nombre: $customer_name\nDNI: $customer_dni\nEmail: $customer_email\nTeléfono: $customer_phone";
        $boatData = "Renovación Permiso de Navegación\nTipo: $renewal_type";

        $rowValuesDatabase = [
            $unique_id,
            $clientData,
            $boatData,
            $contableData,
            "",
            implode("\n", $uploadedDriveLinks),
            "",
            ""
        ];
        $rangeDatabase = 'DATABASE!A1';
        $paramsDatabase = ['valueInputOption' => 'USER_ENTERED'];
        $sheetsService->spreadsheets_values->append($spreadsheetId, $rangeDatabase, new Google_Service_Sheets_ValueRange(['values' => [$rowValuesDatabase]]), $paramsDatabase);

        // --- Hoja "OrganizedData" ---
        $organizedRow = array_fill(0, 21, '');
        $organizedRow[0] = $unique_id;                   // ID Trámite
        $organizedRow[1] = $customer_name;               // Nombre
        $organizedRow[2] = $customer_dni;                // DNI
        $organizedRow[3] = $customer_email;              // Email
        $organizedRow[4] = $customer_phone;              // Teléfono
        $organizedRow[5] = "Renovación Permiso de Navegación"; // Tipo de Trámite
        $organizedRow[6] = "";                           // Campo libre
        $organizedRow[7] = $renewal_type;                // Tipo de renovación
        // Columnas 8 a 10 se dejan vacías
        $organizedRow[11] = ($coupon_used ? $coupon_used : "N/A"); // Cupón Aplicado
        // Columnas 12 y 13 vacías
        $organizedRow[14] = $finalAmount;                 // Importe final
        $organizedRow[15] = "";                           // ITP (no aplica)
        $organizedRow[16] = $fixedTasas;                  // Tasas
        $organizedRow[17] = $iva;                         // IVA
        $organizedRow[18] = $honorarios;                  // Honorarios
        // A partir de la columna T (índice 19) se agregan los documentos
        $docIndex = 19;
        foreach ($uploadedDriveLinks as $docLink) {
            $organizedRow[$docIndex] = $docLink;
            $docIndex++;
        }
        $rangeOrganized = 'OrganizedData!A1';
        $paramsOrganized = ['valueInputOption' => 'USER_ENTERED'];
        $sheetsService->spreadsheets_values->append($spreadsheetId, $rangeOrganized, new Google_Service_Sheets_ValueRange(['values' => [$organizedRow]]), $paramsOrganized);
    } catch (Exception $e) {
        // Opcional: registrar el error en el log
    }
    /*******************************************************/

    wp_send_json_success('Formulario procesado correctamente.');
    wp_die();
}
?>