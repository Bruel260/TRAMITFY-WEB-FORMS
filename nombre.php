<?php
// Asegurarse de que el archivo no sea accedido directamente
defined('ABSPATH') || exit;

/**
 * Función principal para generar y mostrar el formulario en el frontend
 */
function name_change_form_shortcode() {
    // Encolar los scripts y estilos necesarios
    wp_enqueue_style('name-change-form-style', get_template_directory_uri() . '/style.css', array(), filemtime(get_template_directory() . '/style.css'));
    wp_enqueue_script('stripe', 'https://js.stripe.com/v3/', array(), null, false);
    wp_enqueue_script('signature-pad', 'https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js', array(), null, false);

    // Iniciar el buffering de salida
    ob_start();
    ?>

    <!-- Estilos personalizados para el formulario -->
    <style>
        /* Estilos generales para el formulario */
        #name-change-form {
            max-width: 1000px;
            margin: 40px auto;
            padding: 30px;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            background-color: #ffffff;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        #name-change-form label {
            font-weight: normal;
            display: block;
            margin-top: 15px;
            margin-bottom: 5px;
            color: #555555;
        }

        #name-change-form input[type="text"],
        #name-change-form input[type="tel"],
        #name-change-form input[type="email"],
        #name-change-form input[type="file"] {
            width: 100%;
            padding: 12px;
            margin-top: 0px;
            border-radius: 5px;
            border: 1px solid #cccccc;
            font-size: 16px;
            background-color: #f9f9f9;
        }

        #name-change-form .button {
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

        #name-change-form .button:hover {
            background-color: #218838;
        }

        #name-change-form .hidden {
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

        /* [NUEVO - CUPÓN] Clases CSS para el campo del cupón */
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
            #name-change-form {
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
    <form id="name-change-form" action="" method="POST" enctype="multipart/form-data">
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
            <!-- Campos de información del cliente -->
            <label for="customer_name">Nombre y Apellidos:</label>
            <input type="text" id="customer_name" name="customer_name" placeholder="Ingresa tu nombre y apellidos" required />

            <label for="customer_dni">DNI:</label>
            <input type="text" id="customer_dni" name="customer_dni" placeholder="Ingresa tu DNI" required />

            <label for="customer_email">Correo Electrónico:</label>
            <input type="email" id="customer_email" name="customer_email" placeholder="Ingresa tu correo electrónico" required />

            <label for="customer_phone">Teléfono:</label>
            <input type="tel" id="customer_phone" name="customer_phone" placeholder="Ingresa tu teléfono" required />

            <!-- Nuevo Nombre -->
            <label for="new_name">Nuevo Nombre:</label>
            <input type="text" id="new_name" name="new_name" placeholder="Ingrese el nuevo nombre de la embarcación" required />
        </div>

        <!-- Página de Documentación -->
        <div id="page-documents" class="form-page hidden">
            <!-- Sección de Documentos -->
            <h3>Adjuntar Documentación</h3>
            <p>Por favor, sube los siguientes documentos. Puedes ver un ejemplo haciendo clic en "Ver ejemplo" junto a cada uno.</p>
            <div class="upload-section">
                <div class="upload-item">
                    <label for="upload-hoja-asiento">Copia del Registro marítimo</label>
                    <input type="file" id="upload-hoja-asiento" name="upload_hoja_asiento" required>
                    <a href="#" class="view-example" data-doc="hoja-asiento">Ver ejemplo</a>
                </div>
                <div class="upload-item">
                    <label for="upload-dni-comprador">Copia del DNI</label>
                    <input type="file" id="upload-dni-comprador" name="upload_dni_comprador" required>
                    <a href="#" class="view-example" data-doc="dni-comprador">Ver ejemplo</a>
                </div>
                <!-- Añadir más documentos si es necesario -->
            </div>

            <!-- Sección para generar y firmar el documento -->
            <h3>Autorización</h3>
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

            <!-- Aceptación de términos -->
            <div class="terms-container">
                <label>
                    <input type="checkbox" name="terms_accept" required> Acepto los <a href="https://tramitfy.es/terminos-y-condiciones-de-uso/" target="_blank">términos y condiciones</a>.
                </label>
            </div>

            <!-- Botones de navegación del formulario -->
            <div class="button-container">
                <button type="button" class="button" id="prevButton">Anterior</button>
                <button type="button" class="button" id="nextButton">Siguiente</button>
            </div>
        </div>

        <!-- Página de Pago -->
        <div id="page-payment" class="form-page hidden">
            <h2 style="text-align: center; color: #016d86;">Información de Pago</h2>

            <!-- Detalle de Precio -->
            <div class="price-details">
                <p><strong>Cambio de nombre:</strong> <span style="float:right;" id="base-amount">65.00 €</span></p>
                <p><strong>Incluye:</strong></p>
                <ul>
                    <li>Tasas - 19.03 €</li>
                    <li>Honorarios - 38.00 €</li>
                    <li>IVA (21%) - 7.98 €</li>
                </ul>
                <!-- [NUEVO - CUPÓN] Línea de descuento y total final -->
                <p id="discount-line" style="display:none;">
                    <strong>Descuento:</strong>
                    <span style="float:right;" id="discount-amount"></span>
                </p>
                <!-- [/NUEVO - CUPÓN] -->
                <p><strong>Total a pagar:</strong> <span style="float:right;" id="final-amount">65.00 €</span></p>
            </div>

            <!-- [NUEVO - CUPÓN] Campo para ingresar cupón y mensaje -->
            <div class="coupon-container" style="margin-top: 20px;">
                <label for="coupon_code">Cupón de descuento (opcional):</label>
                <input type="text" id="coupon_code" name="coupon_code" placeholder="Ingresa tu cupón" />
                <p id="coupon-message" class="hidden" style="margin-top:10px;"></p>
            </div>
            <!-- [/NUEVO - CUPÓN] -->

            <!-- Formulario de pago con Stripe -->
            <div id="payment-form" style="margin-top: 20px;">
                <div id="payment-element"><!-- Elemento de pago se renderizará aquí --></div>

                <div id="payment-message" class="hidden"></div>

                <div class="terms-container">
                    <label>
                        <input type="checkbox" name="terms_accept_pago" required> Acepto los <a href="https://tramitfy.es/terminos-y-condiciones-de-uso/" target="_blank">términos y condiciones de pago</a>.
                    </label>
                </div>

                <button id="submit" class="button">Pagar</button>
            </div>
        </div>

        <!-- Botones de navegación del formulario (para otras páginas) -->
        <div class="button-container" id="main-button-container">
            <button type="button" class="button" id="prevButtonMain">Anterior</button>
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
        document.addEventListener('DOMContentLoaded', function() {
            // Variables para Stripe
            let stripe;
            let elements;
            let clientSecret;

            // [NUEVO - CUPÓN] Variables para el precio y cupón
            let basePrice = 65.00;         // Precio base
            let currentPrice = basePrice;  // Precio actual (ajustado si aplica cupón)
            let couponTimeout = null;      // Para debounce

            /**
             * Función para inicializar Stripe
             * @param {number} customAmount - Monto personalizado en caso de descuento
             */
            async function initializeStripe(customAmount = null) {
                const amountToCharge = (customAmount !== null) ? customAmount : currentPrice;
                const totalAmountCents = Math.round(amountToCharge * 100);

                // [IMPORTANTE] Aquí usamos la CLAVE PÚBLICA (pk_...) en el frontend
                stripe = Stripe('<?php echo 'YOUR_STRIPE_LIVE_PUBLIC_KEY_HERE'; // <--- PUBLIC KEY ?>');

                // Crear Payment Intent en el servidor
                const response = await fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=create_payment_intent_name_change&amount=${totalAmountCents}`
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

            // Navegación del formulario entre páginas
            const formPages = document.querySelectorAll('.form-page');
            const navLinks = document.querySelectorAll('.nav-link');
            let currentPageIndex = 0;

            function updateForm() {
                formPages.forEach((page, index) => {
                    page.classList.toggle('hidden', index !== currentPageIndex);
                });
                navLinks.forEach((link, index) => {
                    link.classList.toggle('active', index === currentPageIndex);
                });

                // Mostrar u ocultar los botones según la página actual
                if (formPages[currentPageIndex].id === 'page-documents') {
                    document.getElementById('main-button-container').style.display = 'none';
                    document.querySelector('#page-documents .button-container').style.display = 'flex';
                } else {
                    document.getElementById('main-button-container').style.display = 'flex';
                    if (document.querySelector('#page-documents .button-container')) {
                        document.querySelector('#page-documents .button-container').style.display = 'none';
                    }
                }

                // Ocultar botón "Anterior" en la primera página
                document.getElementById('prevButtonMain').style.display = currentPageIndex === 0 ? 'none' : 'inline-block';

                // Ajustar texto del botón "Siguiente"
                const nextButton = document.getElementById('nextButtonMain');
                if (currentPageIndex === formPages.length - 1) {
                    nextButton.style.display = 'none';
                } else {
                    nextButton.textContent = 'Siguiente';
                    nextButton.style.display = 'inline-block';
                }

                // Inicializar Stripe en la página de pago
                if (formPages[currentPageIndex].id === 'page-payment' && !stripe) {
                    initializeStripe().catch(error => {
                        alert('Error al inicializar el pago: ' + error.message);
                    });
                    handlePayment();
                }

                // Generar el documento de autorización en la página de documentos
                if (formPages[currentPageIndex].id === 'page-documents') {
                    generateAuthorizationDocument();
                }
            }

            // Función para generar el documento de autorización
            function generateAuthorizationDocument() {
                const authorizationDiv = document.getElementById('authorization-document');
                const customerName = document.getElementById('customer_name').value.trim();
                const customerDNI = document.getElementById('customer_dni').value.trim();
                const newName = document.getElementById('new_name').value.trim();

                let authorizationHTML = `
                    <p>Yo, <strong>${customerName}</strong>, con DNI <strong>${customerDNI}</strong>, autorizo a Tramitfy S.L. (CIF B55388557) a realizar en mi nombre los trámites necesarios para el cambio de nombre a: <strong>${newName}</strong>.</p>
                    <p>Firmo a continuación en señal de conformidad.</p>
                `;

                authorizationDiv.innerHTML = authorizationHTML;
            }

            // Manejar el pago
            function handlePayment() {
                const submitButton = document.getElementById('submit');

                submitButton.addEventListener('click', async (e) => {
                    e.preventDefault();

                    // Verificar checkbox de términos y condiciones
                    if (!document.querySelector('input[name="terms_accept_pago"]').checked) {
                        alert('Debe aceptar los términos y condiciones de pago para continuar.');
                        return;
                    }

                    // Deshabilitar botón de pago para evitar múltiples clics
                    submitButton.disabled = true;

                    // Mostrar overlay de carga
                    document.getElementById('loading-overlay').style.display = 'flex';

                    try {
                        // Confirmar el pago
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
                            // Pago completado
                            document.getElementById('payment-message').textContent = 'Pago realizado con éxito.';
                            document.getElementById('payment-message').classList.add('success');
                            document.getElementById('payment-message').classList.remove('hidden');

                            // Enviar el formulario al servidor
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

            // Manejar el envío final del formulario
            function handleFinalSubmission() {
                // Validar que la firma no esté vacía
                if (signaturePad && signaturePad.isEmpty()) {
                    alert('Por favor, firme antes de enviar el formulario.');
                    document.getElementById('loading-overlay').style.display = 'none';
                    return;
                }

                // Crear objeto FormData
                let formData = new FormData(document.getElementById('name-change-form'));
                // Añadir acción para AJAX
                formData.append('action', 'submit_form_name_change');

                // Añadir datos adicionales
                formData.append('signature', signaturePad.toDataURL());

                // [NUEVO - CUPÓN] Enviar el cupón que haya sido utilizado (aunque esté vacío)
                formData.append('coupon_used', document.getElementById('coupon_code').value.trim());
                // [/NUEVO - CUPÓN]

                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    // Ocultar overlay de carga
                    document.getElementById('loading-overlay').style.display = 'none';
                    if (data.success) {
                        alert('Formulario enviado con éxito.');
                        // Redirigir o mostrar mensaje de éxito
                        window.location.href = '<?php echo site_url('/pago-realizado-con-exito'); ?>';
                    } else {
                        alert('Error al enviar el formulario: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('loading-overlay').style.display = 'none';
                    alert('Hubo un error al enviar el formulario.');
                });
            }

            // Botones de navegación para páginas distintas a "Documentación"
            document.getElementById('nextButtonMain').addEventListener('click', () => {
                if (!validateCurrentPage()) {
                    return;
                }
                currentPageIndex++;
                updateForm();
            });

            document.getElementById('prevButtonMain').addEventListener('click', () => {
                currentPageIndex--;
                updateForm();
            });

            // Botones de navegación específicos para la página "Documentación"
            const prevButton = document.getElementById('prevButton');
            const nextButton = document.getElementById('nextButton');

            if (prevButton && nextButton) {
                prevButton.addEventListener('click', () => {
                    currentPageIndex--;
                    updateForm();
                });

                nextButton.addEventListener('click', () => {
                    if (!validateCurrentPage()) {
                        return;
                    }
                    currentPageIndex++;
                    updateForm();
                });
            }

            // Permitir navegación entre páginas desde el menú
            navLinks.forEach(link => {
                link.addEventListener('click', (e) => {
                    e.preventDefault();
                    const pageId = link.getAttribute('data-page-id');
                    const pageIndex = Array.from(formPages).findIndex(page => page.id === pageId);
                    if (pageIndex !== -1) {
                        currentPageIndex = pageIndex;
                        updateForm();
                    }
                });
            });

            // Inicializar formulario y firma
            updateForm();
            let signaturePad = new SignaturePad(document.getElementById('signature-pad'));

            // Limpiar la firma
            document.getElementById('clear-signature').addEventListener('click', function() {
                signaturePad.clear();
            });

            // Validar campos de la página actual
            function validateCurrentPage() {
                let valid = true;
                const currentForm = formPages[currentPageIndex];
                const requiredFields = currentForm.querySelectorAll('input[required], select[required]');
                const errorMessages = [];
                requiredFields.forEach(field => {
                    if (!field.value || (field.type === 'checkbox' && !field.checked)) {
                        valid = false;
                        field.classList.add('field-error');
                        // Buscar el label anterior para mostrar el nombre del campo
                        const label = (field.previousElementSibling && field.previousElementSibling.tagName.toLowerCase() === 'label')
                            ? field.previousElementSibling.textContent
                            : field.name;
                        errorMessages.push(`El campo "${label}" es obligatorio.`);
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

            // [NUEVO - CUPÓN] Lógica para validar el cupón con debounce
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
                    validateCouponCodeXXX(couponInput.value.trim());
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

            async function validateCouponCodeXXX(code) {
                try {
                    const response = await fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        // [NUEVO - CUPÓN] Usamos action=validate_coupon_code_XXX
                        body: `action=validate_coupon_code_XXX&coupon=${encodeURIComponent(code)}`
                    });
                    const result = await response.json();

                    // Si el usuario borró el campo mientras esperábamos, no hacemos nada
                    if (couponInput.value.trim() !== code) return;

                    if (result.success) {
                        const discountPercent = result.data.discount_percent; 
                        const discountAmount = (basePrice * discountPercent) / 100;
                        currentPrice = basePrice - discountAmount;

                        couponMessage.textContent = 'Cupón aplicado correctamente';
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
                        couponMessage.textContent = 'Cupón inválido';
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
    </script>

    <?php
    return ob_get_clean();
}
add_shortcode('name_change_form', 'name_change_form_shortcode');

/**
 * Endpoint para crear el Payment Intent
 */
add_action('wp_ajax_create_payment_intent_name_change', 'create_payment_intent_name_change');
add_action('wp_ajax_nopriv_create_payment_intent_name_change', 'create_payment_intent_name_change');

function create_payment_intent_name_change() {
    // Incluir la librería de Stripe
    require_once __DIR__ . '/vendor/stripe/stripe-php/init.php';

    // [IMPORTANTE] Aquí usas la CLAVE SECRETA (sk_...) en el servidor
    \Stripe\Stripe::setApiKey('YOUR_STRIPE_LIVE_SECRET_KEY_HERE'); // <--- SECRET KEY

    $amount = isset($_POST['amount']) ? intval($_POST['amount']) : 0;

    try {
        $paymentIntent = \Stripe\PaymentIntent::create([
            'amount' => $amount,
            'currency' => 'eur',
            'payment_method_types' => ['card'],
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
add_action('wp_ajax_validate_coupon_code_XXX', 'validate_coupon_code_XXX');
add_action('wp_ajax_nopriv_validate_coupon_code_XXX', 'validate_coupon_code_XXX');

function validate_coupon_code_XXX() {
    // Array de cupones válidos
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
        wp_send_json_error('Cupón inválido');
    }
    wp_die();
}
/* [/NUEVO - CUPÓN] */

/**
 * Función para manejar el envío final del formulario
 */
add_action('wp_ajax_submit_form_name_change', 'submit_form_name_change');
add_action('wp_ajax_nopriv_submit_form_name_change', 'submit_form_name_change');

function submit_form_name_change() {
    // Validar y procesar los datos enviados
    $customer_name = sanitize_text_field($_POST['customer_name']);
    $customer_dni = sanitize_text_field($_POST['customer_dni']);
    $customer_email = sanitize_email($_POST['customer_email']);
    $customer_phone = sanitize_text_field($_POST['customer_phone']);
    $new_name = sanitize_text_field($_POST['new_name']);

    // [NUEVO - CUPÓN] Recogemos el cupón
    $coupon_used = isset($_POST['coupon_used']) ? sanitize_text_field($_POST['coupon_used']) : '';
    // [/NUEVO - CUPÓN]

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

    // Agregar la fecha en la esquina superior derecha
    $pdf->Cell(0, 10, 'Fecha: ' . date('d/m/Y'), 0, 0, 'R');
    $pdf->Ln(10);

    $pdf->Cell(0, 10, utf8_decode('Autorización para Cambio de Nombre'), 0, 1, 'C');
    $pdf->Ln(10);
    $texto = "Yo, $customer_name, con DNI $customer_dni, autorizo a Tramitfy S.L. (CIF B55388557) a realizar en mi nombre los trámites necesarios para el cambio de nombre a: $new_name.";
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

    foreach ($_FILES as $file) {
        if ($file['error'] === UPLOAD_ERR_OK) {
            $uploaded_file = wp_handle_upload($file, ['test_form' => false]);
            if (isset($uploaded_file['file'])) {
                $attachments[] = $uploaded_file['file'];
            }
        }
    }

    // Enviar correo al administrador con todos los datos y archivos adjuntos
    $admin_email = get_option('admin_email');
    $subject_admin = 'Nuevo formulario de cambio de nombre enviado';

    // Construir el mensaje para el administrador con formato
    $message_admin = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            /* Estilos del correo electrónico */
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
                <h2 style="color: #016d86;">Nuevo Formulario de Cambio de Nombre Enviado</h2>
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
                    <!-- [NUEVO - CUPÓN] Agregamos la fila del cupón al correo admin -->
                    <tr>
                        <th>Cupón utilizado:</th>
                        <td>' . htmlspecialchars($coupon_used) . '</td>
                    </tr>
                    <!-- [/NUEVO - CUPÓN] -->
                </table>
                <h3>Nuevo Nombre Solicitado:</h3>
                <p>' . htmlspecialchars($new_name) . '</p>
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

    wp_mail($admin_email, $subject_admin, $message_admin, $headers, $attachments);

    // Enviar correo al cliente sin adjunto
    $subject_client = 'Confirmación de su solicitud de cambio de nombre';

    $message_client = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            /* Estilos del correo electrónico */
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
                <h2 style="color: #016d86;">Confirmación de su solicitud de cambio de nombre</h2>
            </div>
            <div class="content">
                <p>Estimado/a ' . htmlspecialchars($customer_name) . ',</p>
                <p>Hemos recibido su solicitud para cambiar el nombre a: <strong>' . htmlspecialchars($new_name) . '</strong>.</p>
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

    // Encabezados para el correo al cliente
    $headers_client = [];
    $headers_client[] = 'Content-Type: text/html; charset=UTF-8';
    $headers_client[] = 'From: info@tramitfy.es';

    wp_mail($customer_email, $subject_client, $message_client, $headers_client);

    wp_send_json_success('Formulario procesado correctamente.');
    wp_die();
}
?>
