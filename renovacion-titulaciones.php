<?php
// Asegurarse de que el archivo no sea accedido directamente
defined('ABSPATH') || exit;

/**
 * Función principal para generar y mostrar el formulario de Renovación de Titulaciones en el frontend
 */
function renovacion_titulaciones_form_shortcode() {
    // Encolar los scripts y estilos necesarios
    wp_enqueue_style('renovacion-titulaciones-form-style', get_template_directory_uri() . '/style.css', array(), filemtime(get_template_directory() . '/style.css'));
    wp_enqueue_script('stripe', 'https://js.stripe.com/v3/', array(), null, false);
    wp_enqueue_script('signature-pad', 'https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js', array(), null, false);

    // Iniciar el buffering de salida
    ob_start();
    ?>
    <!-- Estilos personalizados para el formulario y el modal -->
    <style>
        /* (Se mantiene exactamente igual todos los estilos) */
        /* Estilos generales para el formulario */
        #renovacion-titulaciones-form {
            max-width: 1000px;
            margin: 40px auto;
            padding: 30px;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            background-color: #ffffff;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            position: relative;
            z-index: 1;
        }
        #renovacion-titulaciones-form label {
            font-weight: normal;
            display: block;
            margin-top: 15px;
            margin-bottom: 5px;
            color: #555555;
        }
        #renovacion-titulaciones-form input[type="text"],
        #renovacion-titulaciones-form input[type="tel"],
        #renovacion-titulaciones-form input[type="email"],
        #renovacion-titulaciones-form input[type="file"],
        #renovacion-titulaciones-form select {
            width: 100%;
            padding: 12px;
            border-radius: 5px;
            border: 1px solid #cccccc;
            font-size: 16px;
            background-color: #f9f9f9;
        }
        #renovacion-titulaciones-form .button {
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
        #renovacion-titulaciones-form .button:hover {
            background-color: #218838;
        }
        #renovacion-titulaciones-form .hidden {
            display: none;
        }
        /* Menú de navegación */
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
            top: 50%;
            right: -10px;
            transform: translateY(-50%);
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
            margin-top: 15px;
        }
        .button-container .button {
            flex: 1 1 auto;
            margin: 5px;
        }
        /* Página de selección de trámite */
        #page-tramite-type {
            text-align: center;
            padding: 20px;
        }
        #page-tramite-type h2 {
            font-size: 24px;
            color: #016d86;
            margin-bottom: 20px;
        }
        .tramite-options {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 20px;
        }
        .tramite-option {
            border: 2px solid #cccccc;
            border-radius: 10px;
            padding: 15px;
            cursor: pointer;
            width: 150px;
            text-align: center;
            transition: border-color 0.3s, transform 0.3s, background-color 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f7f7f7;
        }
        .tramite-option:hover {
            border-color: #016d86;
            transform: scale(1.05);
        }
        .tramite-option.selected {
            border-color: #28a745;
            background-color: #cce5ff;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        .tramite-text {
            font-size: 18px;
            font-weight: bold;
            color: #016d86;
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
        /* Botón de pago – Único y agrandado en la última página */
        #submit {
            background-color: #016d86;
            color: #ffffff;
            padding: 20px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 24px;
            transition: background-color 0.3s ease;
            width: 100%;
            max-width: 500px;
            margin: 40px auto 0;
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
        .StripeElement--invalid {
            border-color: #dc3545;
        }
        .StripeElement {
            background-color: #ffffff;
            padding: 12px;
            border: 1px solid #cccccc;
            border-radius: 4px;
            margin-bottom: 10px;
            width: 100%;
        }
        #card-errors {
            color: #dc3545;
            margin-top: 10px;
        }
        #payment-message {
            margin-top: 10px;
            font-size: 16px;
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
        /* Botones deshabilitados */
        .button[disabled],
        .button:disabled {
            background-color: #cccccc;
            cursor: not-allowed;
        }
        /* Checkbox y recuadro de precio */
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
        /* Mensajes de error */
        .error-message {
            color: #dc3545;
            margin-bottom: 20px;
            font-size: 16px;
            font-weight: bold;
        }
        .field-error {
            border-color: #dc3545 !important;
        }
        .coupon-valid {
            background-color: #d4edda !important;
            border-color: #28a745 !important;
        }
        .coupon-error {
            background-color: #f8d7da !important;
            border-color: #dc3545 !important;
        }
        .coupon-loading {
            background-color: #fff3cd !important;
            border-color: #ffeeba !important;
        }
        /* Modal Popup para ejemplos de documentos */
        #document-popup {
            display: none; /* oculto por defecto */
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }
        #document-popup .popup-content {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            max-width: 600px;
            width: 90%;
            position: relative;
        }
        #document-popup .close-popup {
            position: absolute;
            top: 10px;
            right: 15px;
            font-size: 28px;
            font-weight: bold;
            color: #aaa;
            cursor: pointer;
        }
        #document-popup .close-popup:hover {
            color: #000;
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

    <!-- Formulario principal -->
    <form id="renovacion-titulaciones-form" action="" method="POST" enctype="multipart/form-data">
        <!-- Mensajes de error -->
        <div id="error-messages"></div>

        <!-- Navegación del formulario (4 páginas) -->
        <div id="form-navigation">
            <a href="#" class="nav-link" data-page-id="page-tramite-type">Trámite</a>
            <a href="#" class="nav-link" data-page-id="page-personal-info">Datos</a>
            <a href="#" class="nav-link" data-page-id="page-documents">Documentación</a>
            <a href="#" class="nav-link" data-page-id="page-payment">Pago</a>
        </div>

        <!-- Overlay de carga -->
        <div id="loading-overlay">
            <div class="spinner"></div>
            <p>Procesando, por favor espera...</p>
        </div>

        <!-- Página 1: Selección del Trámite -->
        <div id="page-tramite-type" class="form-page">
            <h2>Elige tu titulación a renovar</h2>
            <div class="tramite-options">
                <div class="tramite-option" data-value="PNB">
                    <div class="tramite-text">P.N.B.</div>
                </div>
                <div class="tramite-option" data-value="PER">
                    <div class="tramite-text">P.E.R</div>
                </div>
                <div class="tramite-option" data-value="patron_de_yate">
                    <div class="tramite-text">PATRON DE YATE</div>
                </div>
                <div class="tramite-option" data-value="capitan_de_yate">
                    <div class="tramite-text">CAPITAN DE YATE</div>
                </div>
                <div class="tramite-option" data-value="moto_a_o_b">
                    <div class="tramite-text">MOTO A O B</div>
                </div>
            </div>
            <!-- Campo oculto para almacenar la opción seleccionada -->
            <input type="hidden" id="tramite_type" name="tramite_type" required>
            
        <!-- Aviso sobre titulaciones válidas para renovación (posición ajustada) -->
<div style="background-color: #ffeaea; border: 1px solid #e74c3c; color: #c0392b; border-radius: 5px; padding: 10px; margin-top: 30px; margin-bottom: 5px; text-align: center; font-size: 14px; font-weight: 600;">
    Nota: Solo se podrán renovar mediante este formulario las titulaciones expedidas por la Dirección General de la Marina Mercante. No siendo válidas para su renovación las expedidas por administraciones autonómicas.
</div>
        </div>

        <!-- Página 2: Datos Personales -->
        <div id="page-personal-info" class="form-page hidden">
            <label for="customer_name">Nombre y Apellidos:</label>
            <input type="text" id="customer_name" name="customer_name" placeholder="Ingresa tu nombre y apellidos" required>
            <label for="customer_dni">DNI:</label>
            <input type="text" id="customer_dni" name="customer_dni" placeholder="Ingresa tu DNI" required>
            <label for="customer_email">Correo Electrónico:</label>
            <input type="email" id="customer_email" name="customer_email" placeholder="Ingresa tu correo electrónico" required>
            <label for="customer_phone">Teléfono:</label>
            <input type="tel" id="customer_phone" name="customer_phone" placeholder="Ingresa tu teléfono" required>
        </div>

        <!-- Página 3: Documentación -->
        <div id="page-documents" class="form-page hidden">
            <h3>Adjuntar Documentación</h3>
            <p>Sube los siguientes documentos. Haz clic en "Ver ejemplo" para visualizar el documento de referencia.</p>
            <div class="upload-section">
                <div class="upload-item">
                    <label for="upload-dni">Copia del DNI por ambas caras</label>
                    <input type="file" id="upload-dni" name="upload_dni" required>
                    <a href="#" class="view-example" data-doc="dni-comprador">Ver ejemplo</a>
                </div>
                <div class="upload-item">
                    <label for="upload-medical-report">Certificado médico psicotécnico por ambas caras</label>
                    <input type="file" id="upload-medical-report" name="upload_medical_report" required>
                    <!-- Se actualiza el data-doc para usar el nuevo archivo -->
                    <a href="#" class="view-example" data-doc="certificado-medico-plantilla">Ver ejemplo</a>
                </div>
                <div class="upload-item">
                    <label for="upload-expired-doc">Copia documentación caducada</label>
                    <input type="file" id="upload-expired-doc" name="upload_expired_doc" required>
                    <a href="#" class="view-example" data-doc="QUE-TITULO-NECESITO">Ver ejemplo</a>
                </div>
            </div>
            <!-- Contenedor para el documento de autorización -->
            <div id="authorization-document" style="background-color:#f9f9f9; padding:20px; border:1px solid #e0e0e0; margin-top:20px;"></div>
            <h3>Firma</h3>
            <div id="signature-container">
                <canvas id="signature-pad" width="500" height="200" style="border:1px solid #ccc;"></canvas>
            </div>
            <button type="button" class="button" id="clear-signature">Limpiar Firma</button>
            <div class="terms-container">
                <label>
                    <input type="checkbox" name="terms_accept" required>
                    Acepto los <a href="https://tramitfy.es/terminos-y-condiciones-de-uso/" target="_blank">términos y condiciones</a>.
                </label>
            </div>
        </div>

        <!-- Página 4: Pago -->
        <div id="page-payment" class="form-page hidden">
            <h2 style="text-align: center; color: #016d86;">Información de Pago</h2>
            <div class="price-details">
                <p><strong>Renovación de titulaciones:</strong> <span style="float:right;" id="total-field">55,00 €</span></p>
                <p><strong>Incluye:</strong></p>
                <ul>
                    <li id="tasas_honorarios-field">Tasas + Honorarios: 45,08 €</li>
                    <li id="iva-field">IVA: 9,92 €</li>
                </ul>
                <p id="discount-line" style="display:none;">
                    <strong>Descuento:</strong>
                    <span style="float:right;" id="discount-amount"></span>
                </p>
                <p><strong>Total a pagar:</strong>
                   <span style="float:right;" id="final-amount">55,00 €</span>
                </p>
            </div>
            <div class="coupon-container" style="margin-top: 20px;">
                <label for="coupon_code">Cupón de descuento (opcional):</label>
                <input type="text" id="coupon_code" name="coupon_code" placeholder="Ingresa tu cupón">
                <p id="coupon-message" class="hidden" style="margin-top:10px;"></p>
            </div>
            <div id="payment-form">
                <div id="payment-element"></div>
                <div id="payment-message" class="hidden"></div>
                <div class="terms-container">
                    <label>
                        <input type="checkbox" name="terms_accept_pago" required>
                        Acepto los <a href="https://tramitfy.es/terminos-y-condiciones-de-uso/" target="_blank">términos y condiciones de pago</a>.
                    </label>
                </div>
                <!-- Únicamente se muestra el botón de Pagar (agrandado) -->
                <button id="submit" class="button">Pagar</button>
            </div>
        </div>

        <!-- Botones de navegación principales -->
        <div class="button-container" id="main-button-container">
            <button type="button" class="button" id="prevButtonMain">Anterior</button>
            <button type="button" class="button" id="nextButtonMain">Siguiente</button>
        </div>
    </form>

    <!-- Modal Popup para ejemplos de documentos -->
    <div id="document-popup" style="display:none;">
        <div class="popup-content">
            <span class="close-popup">&times;</span>
            <h3>Ejemplo de documento</h3>
            <img id="document-example-image" src="" alt="Ejemplo de documento">
        </div>
    </div>

    <!-- JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Variables para Stripe y precios
            let stripe, elements, clientSecret;
            const fixedTasas = 7.78;
            const basePrice = 55.00; // Nuevo precio base sin descuento
            // currentPrice se actualizará si hay cupón aplicado
            let currentPrice = basePrice;
            let discountApplied = 0, discountAmount = 0, couponTimeout = null;
            let currentPage = 0;
            const formPages = document.querySelectorAll('.form-page');
            const navLinks = document.querySelectorAll('.nav-link');

            // Función para actualizar el desglose de Tasas, Honorarios e IVA
            function updateBreakdown() {
                let remaining = currentPrice - fixedTasas;
                let iva = remaining * 0.21;
                let honorarios = remaining - iva;
                let tasasHonorarios = fixedTasas + honorarios;
                document.getElementById('tasas_honorarios-field').textContent = 'Tasas + Honorarios: ' + tasasHonorarios.toFixed(2) + ' €';
                document.getElementById('iva-field').textContent = 'IVA: ' + iva.toFixed(2) + ' €';
            }

            async function initializeStripe(customAmount = null) {
                const amountToCharge = (customAmount !== null) ? customAmount : currentPrice;
                const totalAmountCents = Math.round(amountToCharge * 100);
                stripe = Stripe('<?php echo 'YOUR_STRIPE_LIVE_PUBLIC_KEY_HERE'; ?>');
                const response = await fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=create_payment_intent_renovacion_titulaciones&amount=${totalAmountCents}`
                });
                const result = await response.json();
                if(result.error) throw new Error(result.error);
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
                        '.Label': { color: '#555555', fontSize: '14px', marginBottom: '4px' },
                        '.Input': { padding: '12px', border: '1px solid #cccccc', borderRadius: '4px' },
                        '.Input:focus': { borderColor: '#016d86' },
                        '.Input--invalid': { borderColor: '#dc3545' },
                    }
                };
                elements = stripe.elements({ appearance, clientSecret });
                const paymentElementOptions = { paymentMethodOrder: ['card'] };
                const paymentElement = elements.create('payment', paymentElementOptions);
                paymentElement.mount('#payment-element');
            }

            function updateForm() {
                formPages.forEach((page, index) => page.classList.toggle('hidden', index !== currentPage));
                navLinks.forEach((link, index) => link.classList.toggle('active', index === currentPage));
                if(formPages[currentPage].id === 'page-payment') {
                    document.getElementById('main-button-container').style.display = 'none';
                } else {
                    document.getElementById('main-button-container').style.display = 'flex';
                }
                document.getElementById('prevButtonMain').style.display = currentPage === 0 ? 'none' : 'inline-block';
                const nextButton = document.getElementById('nextButtonMain');
                if(currentPage === formPages.length - 1) {
                    nextButton.style.display = 'none';
                } else {
                    nextButton.textContent = 'Siguiente';
                    nextButton.style.display = 'inline-block';
                }
                if(formPages[currentPage].id === 'page-payment' && !stripe) {
                    initializeStripe().catch(error => alert('Error al inicializar el pago: ' + error.message));
                    handlePayment();
                }
                if(formPages[currentPage].id === 'page-documents') {
                    generateAuthorizationDocument();
                }
            }

            function generateAuthorizationDocument() {
                const authDiv = document.getElementById('authorization-document');
                const customerName = document.getElementById('customer_name').value.trim();
                const customerDNI = document.getElementById('customer_dni').value.trim();
                const tramiteTypeTextElement = document.querySelector('.tramite-option.selected .tramite-text');
                const tramiteTypeText = tramiteTypeTextElement ? tramiteTypeTextElement.innerText : '';
                authDiv.innerHTML = `
                    <p>Yo, <strong>${customerName}</strong>, con DNI <strong>${customerDNI}</strong>, autorizo a Tramitfy S.L. (CIF B55388557) a realizar en mi nombre los trámites necesarios para la renovación de mis titulaciones correspondientes al trámite: <strong>${tramiteTypeText}</strong>.</p>
                    <p>Firmo a continuación en señal de conformidad.</p>
                `;
            }

            function handlePayment() {
                const submitButton = document.getElementById('submit');
                submitButton.addEventListener('click', async (e) => {
                    e.preventDefault();
                    if(!document.querySelector('input[name="terms_accept_pago"]').checked) {
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
                        if(error) throw new Error(error.message);
                        document.getElementById('payment-message').textContent = 'Pago realizado con éxito.';
                        document.getElementById('payment-message').classList.add('success');
                        document.getElementById('payment-message').classList.remove('hidden');
                        handleFinalSubmission();
                    } catch (error) {
                        document.getElementById('payment-message').textContent = 'Error al procesar el pago: ' + error.message;
                        document.getElementById('payment-message').classList.add('error');
                        document.getElementById('payment-message').classList.remove('hidden');
                        submitButton.disabled = false;
                        document.getElementById('loading-overlay').style.display = 'none';
                    }
                });
            }

            // Al enviar el formulario se añade el valor final actualizado (sin el símbolo) en 'final_amount'
            function handleFinalSubmission() {
                if(window.signaturePad && window.signaturePad.isEmpty()) {
                    alert('Por favor, firme antes de enviar el formulario.');
                    document.getElementById('loading-overlay').style.display = 'none';
                    return;
                }
                let formData = new FormData(document.getElementById('renovacion-titulaciones-form'));
                formData.append('action', 'submit_form_renovacion_titulaciones');
                formData.append('signature', window.signaturePad.toDataURL());
                formData.append('coupon_used', document.getElementById('coupon_code').value.trim());
                // Extraer el importe final del elemento (quitar el símbolo € y espacios)
                let finalAmountText = document.getElementById('final-amount').textContent;
                let finalAmountNumeric = parseFloat(finalAmountText.replace('€','').trim());
                formData.append('final_amount', finalAmountNumeric);
                // Se eliminó la referencia a capitania
                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    document.getElementById('loading-overlay').style.display = 'none';
                    if(data.success) {
                        alert('Formulario enviado con éxito.');
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

            // Eventos de navegación
            document.getElementById('nextButtonMain').addEventListener('click', () => { if (!validateCurrentPage()) return; currentPage++; updateForm(); });
            document.getElementById('prevButtonMain').addEventListener('click', () => { currentPage--; updateForm(); });
            const prevButton = document.getElementById('prevButton');
            const nextButton = document.getElementById('nextButton');
            if(prevButton && nextButton) {
                prevButton.addEventListener('click', () => { currentPage--; updateForm(); });
                nextButton.addEventListener('click', () => { if(!validateCurrentPage()) return; currentPage++; updateForm(); });
            }
            navLinks.forEach(link => {
                link.addEventListener('click', (e) => {
                    e.preventDefault();
                    const pageId = link.getAttribute('data-page-id');
                    const pageIndex = Array.from(formPages).findIndex(page => page.id === pageId);
                    if(pageIndex !== -1) { currentPage = pageIndex; updateForm(); }
                });
            });
            function validateCurrentPage() {
                let valid = true;
                const currentForm = formPages[currentPage];
                const requiredFields = currentForm.querySelectorAll('input[required], select[required]');
                const errorMessages = [];
                requiredFields.forEach(field => {
                    if(!field.value || (field.type === 'checkbox' && !field.checked)) {
                        valid = false;
                        field.classList.add('field-error');
                        const labelText = field.previousElementSibling ? field.previousElementSibling.textContent : field.name;
                        errorMessages.push(`El campo "${labelText}" es obligatorio.`);
                    } else {
                        field.classList.remove('field-error');
                    }
                });
                const errorDiv = document.getElementById('error-messages');
                errorDiv.innerHTML = '';
                if(!valid) {
                    errorMessages.forEach(msg => {
                        const p = document.createElement('p');
                        p.textContent = msg;
                        p.classList.add('error-message');
                        errorDiv.appendChild(p);
                    });
                }
                return valid;
            }

            // Lógica del popup modal para "Ver ejemplo"
            const popup = document.getElementById('document-popup');
            const closePopup = document.querySelector('.close-popup');
            const exampleImage = document.getElementById('document-example-image');
            document.querySelectorAll('.view-example').forEach(function(link) {
                link.addEventListener('click', function(event) {
                    event.preventDefault();
                    const docType = this.getAttribute('data-doc');
                    var baseURL = "<?php echo esc_url(home_url('/')); ?>";
                    exampleImage.src = baseURL + "wp-content/uploads/exampledocs/" + docType + ".jpg";
                    popup.style.display = 'flex';
                });
            });
            closePopup.addEventListener('click', function() {
                popup.style.display = 'none';
            });
            window.addEventListener('click', function(event) {
                if(event.target == popup) {
                    popup.style.display = 'none';
                }
            });

            // Selección visual de trámite
            document.querySelectorAll('.tramite-option').forEach(function(option) {
                option.addEventListener('click', function() {
                    document.querySelectorAll('.tramite-option').forEach(function(opt) { opt.classList.remove('selected'); });
                    this.classList.add('selected');
                    document.getElementById('tramite_type').value = this.getAttribute('data-value');
                    generateAuthorizationDocument();
                });
            });

            updateForm();
            // Actualizamos el desglose inicial
            updateBreakdown();
            window.signaturePad = new SignaturePad(document.getElementById('signature-pad'));
            document.getElementById('clear-signature').addEventListener('click', function() {
                window.signaturePad.clear();
            });

            // Lógica para el cupón
            const couponInput = document.getElementById('coupon_code');
            const couponMessage = document.getElementById('coupon-message');
            const discountLine = document.getElementById('discount-line');
            const discountSpan = document.getElementById('discount-amount');
            const finalAmountSpan = document.getElementById('final-amount');
            couponInput.addEventListener('input', () => {
                if(couponTimeout) clearTimeout(couponTimeout);
                if(couponInput.value.trim() === '') { resetCoupon(); return; }
                couponInput.classList.remove('coupon-error', 'coupon-valid');
                couponInput.classList.add('coupon-loading');
                couponMessage.classList.remove('success', 'error-message', 'hidden');
                couponMessage.textContent = 'Verificando cupón...';
                couponTimeout = setTimeout(() => { validateCouponCode(couponInput.value.trim()); }, 1000);
            });
            function resetCoupon() {
                couponInput.classList.remove('coupon-error', 'coupon-valid', 'coupon-loading');
                couponMessage.textContent = '';
                couponMessage.classList.add('hidden');
                discountLine.style.display = 'none';
                discountSpan.textContent = '';
                finalAmountSpan.textContent = basePrice.toFixed(2) + ' €';
                currentPrice = basePrice;
                updateBreakdown();
                if(stripe) {
                    stripe = null;
                    document.getElementById('payment-element').innerHTML = '';
                    initializeStripe(basePrice).catch(error => console.error(error));
                }
            }
            async function validateCouponCode(code) {
                try {
                    const response = await fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `action=validate_coupon_code_renovacion_titulaciones&coupon=${encodeURIComponent(code)}`
                    });
                    const result = await response.json();
                    if(couponInput.value.trim() !== code) return;
                    if(result.success) {
                        discountApplied = result.data.discount_percent;
                        // Calcular el importe final aplicando el descuento:
                        currentPrice = basePrice * (1 - discountApplied / 100);
                        // Calcular el descuento en euros
                        discountAmount = basePrice - currentPrice;
                        couponMessage.textContent = 'Cupón aplicado correctamente.';
                        couponMessage.classList.remove('hidden', 'error-message');
                        couponMessage.classList.add('success');
                        couponInput.classList.remove('coupon-loading', 'coupon-error');
                        couponInput.classList.add('coupon-valid');
                        discountLine.style.display = 'block';
                        discountSpan.textContent = '- ' + discountAmount.toFixed(2) + ' €';
                        finalAmountSpan.textContent = currentPrice.toFixed(2) + ' €';
                        updateBreakdown();
                        if(stripe) {
                            stripe = null;
                            document.getElementById('payment-element').innerHTML = '';
                        }
                        await initializeStripe(currentPrice);
                    } else {
                        couponMessage.textContent = 'Cupón inválido o expirado.';
                        couponMessage.classList.remove('hidden','success');
                        couponMessage.classList.add('error-message');
                        couponInput.classList.remove('coupon-loading', 'coupon-valid');
                        couponInput.classList.add('coupon-error');
                        discountLine.style.display = 'none';
                        currentPrice = basePrice;
                        finalAmountSpan.textContent = basePrice.toFixed(2) + ' €';
                        updateBreakdown();
                        if(stripe) {
                            stripe = null;
                            document.getElementById('payment-element').innerHTML = '';
                        }
                        await initializeStripe(basePrice);
                    }
                } catch (error) {
                    console.error('Error al validar el cupón:', error);
                    couponMessage.textContent = 'Error al validar el cupón.';
                    couponMessage.classList.remove('hidden','success');
                    couponMessage.classList.add('error-message');
                    couponInput.classList.remove('coupon-loading', 'coupon-valid');
                    couponInput.classList.add('coupon-error');
                    discountLine.style.display = 'none';
                }
            }
        });
    </script>

    <?php
    return ob_get_clean();
}
add_shortcode('renovacion_titulaciones_form', 'renovacion_titulaciones_form_shortcode');

/**
 * Endpoint para crear el Payment Intent para Renovación de Titulaciones
 */
add_action('wp_ajax_create_payment_intent_renovacion_titulaciones', 'create_payment_intent_renovacion_titulaciones');
add_action('wp_ajax_nopriv_create_payment_intent_renovacion_titulaciones', 'create_payment_intent_renovacion_titulaciones');
function create_payment_intent_renovacion_titulaciones() {
    require_once __DIR__ . '/vendor/stripe/stripe-php/init.php';
    \Stripe\Stripe::setApiKey('YOUR_STRIPE_LIVE_SECRET_KEY_HERE');
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
 * Endpoint para validar el cupón en Renovación de Titulaciones
 */
add_action('wp_ajax_validate_coupon_code_renovacion_titulaciones', 'validate_coupon_code_renovacion_titulaciones');
add_action('wp_ajax_nopriv_validate_coupon_code_renovacion_titulaciones', 'validate_coupon_code_renovacion_titulaciones');
function validate_coupon_code_renovacion_titulaciones() {
    $valid_coupons = array(
        'DESCUENTO10' => 10,
        'DESCUENTO20' => 20,
        'VERANO15'    => 15,
        'BLACK50'     => 50,
    );
    $coupon = isset($_POST['coupon']) ? sanitize_text_field($_POST['coupon']) : '';
    $coupon_upper = strtoupper($coupon);
    if(isset($valid_coupons[$coupon_upper])) {
        $discount_percent = $valid_coupons[$coupon_upper];
        wp_send_json_success(['discount_percent' => $discount_percent]);
    } else {
        wp_send_json_error('Cupón inválido o expirado');
    }
    wp_die();
}

/**
 * Función para manejar el envío final del formulario de Renovación de Titulaciones,
 * incluyendo la inserción en la base de datos (Google Drive & Google Sheets)
 */
add_action('wp_ajax_submit_form_renovacion_titulaciones', 'submit_form_renovacion_titulaciones');
add_action('wp_ajax_nopriv_submit_form_renovacion_titulaciones', 'submit_form_renovacion_titulaciones');
function submit_form_renovacion_titulaciones() {
    // Generar identificador único para Renovación de Titulaciones: TMA-RENOV-YYYYMMDD-######
    $prefix = 'TMA-RENOV';
    $counter_option = 'tma_renov_counter';
    $current_cnt = get_option($counter_option, 0);
    $current_cnt++;
    update_option($counter_option, $current_cnt);
    $date_part = date('Ymd');
    $secuencial = str_pad($current_cnt, 6, '0', STR_PAD_LEFT);
    $unique_id = $prefix . '-' . $date_part . '-' . $secuencial;

    $tramite_type    = sanitize_text_field($_POST['tramite_type']);
    $customer_name   = sanitize_text_field($_POST['customer_name']);
    $customer_dni    = sanitize_text_field($_POST['customer_dni']);
    $customer_email  = sanitize_email($_POST['customer_email']);
    $customer_phone  = sanitize_text_field($_POST['customer_phone']);
    $coupon_used     = isset($_POST['coupon_used']) ? sanitize_text_field($_POST['coupon_used']) : '';
    $signature       = $_POST['signature'];
    $signature_data  = str_replace('data:image/png;base64,', '', $signature);
    $signature_data  = base64_decode($signature_data);
    $upload_dir      = wp_upload_dir();
    $signature_image_name = 'signature_' . time() . '.png';
    $signature_image_path = $upload_dir['path'] . '/' . $signature_image_name;
    file_put_contents($signature_image_path, $signature_data);

    require_once get_template_directory() . '/vendor/fpdf/fpdf.php';
    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 10, 'Fecha: ' . date('d/m/Y'), 0, 0, 'R');
    $pdf->Ln(10);
    $pdf->Cell(0, 10, utf8_decode('Autorización para Renovación de Titulaciones'), 0, 1, 'C');
    $pdf->Ln(10);
    $texto = "Yo, $customer_name, con DNI $customer_dni, autorizo a Tramitfy S.L. (CIF B55388557) a realizar en mi nombre los trámites necesarios para la renovación de mis titulaciones correspondientes al trámite: $tramite_type.";
    $pdf->MultiCell(0, 10, utf8_decode($texto), 0, 'J');
    $pdf->Ln(10);
    $pdf->Cell(0, 10, utf8_decode('Firma:'), 0, 1);
    $pdf->Image($signature_image_path, null, null, 50, 30);
    $authorization_pdf_name = 'autorizacion_' . time() . '.pdf';
    $authorization_pdf_path = $upload_dir['path'] . '/' . $authorization_pdf_name;
    $pdf->Output('F', $authorization_pdf_path);
    unlink($signature_image_path);

    $attachments = [$authorization_pdf_path];
    foreach($_FILES as $key => $file) {
        if($file['error'] === UPLOAD_ERR_OK) {
            $uploaded_file = wp_handle_upload($file, ['test_form' => false]);
            if(isset($uploaded_file['file'])) {
                $attachments[] = $uploaded_file['file'];
            }
        }
    }

    // --- Envío de emails ---
    // Email al administrador
    $clientData = "Nombre: $customer_name\nDNI: $customer_dni\nEmail: $customer_email\nTeléfono: $customer_phone";
    $message_admin = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9; border: 1px solid #e0e0e0; border-radius: 10px; }
            .header { text-align: center; margin-bottom: 20px; }
            .header img { max-width: 200px; height: auto; margin-bottom: 10px; }
            .content { padding: 20px; background-color: #ffffff; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
            .footer { margin-top: 30px; padding: 10px 20px; background-color: #016d86; color: #ffffff; text-align: left; font-size: 12px; border-radius: 8px; }
            .details-table { width: 100%; border-collapse: collapse; }
            .details-table th, .details-table td { text-align: left; padding: 8px; border-bottom: 1px solid #dddddd; }
            .details-table th { background-color: #f2f2f2; }
            a { color: #FFFFFF; text-decoration: none; }
            a:hover { text-decoration: underline; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <img src="https://www.tramitfy.es/wp-content/uploads/LOGO.png" alt="Tramitfy Logo">
                <h2 style="color: #016d86;">Nuevo Formulario de Renovación de Titulaciones</h2>
            </div>
            <div class="content">
                <p>Se ha recibido un nuevo formulario con los siguientes detalles:</p>
                <table class="details-table">
                    <tr>
                        <th>Identificador:</th>
                        <td>' . htmlspecialchars($unique_id) . '</td>
                    </tr>
                    <tr>
                        <th>Trámite:</th>
                        <td>' . htmlspecialchars($tramite_type) . '</td>
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
                        <th>Cupón utilizado:</th>
                        <td>' . ($coupon_used ? htmlspecialchars($coupon_used) : "N/A") . '</td>
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
    $subject_admin = 'Nuevo formulario de renovación de titulaciones';
    wp_mail($admin_email, $subject_admin, $message_admin, $headers, $attachments);

    // Email al cliente (mejorado)
    // Cálculo:
    // 1. Se obtiene el importe final X (con descuento aplicado) enviado desde el cliente.
    // 2. Sobre X se restan las tasas fijas (7,78 €) y a ese resultado se le aplica el IVA del 21%.
    //    IVA = (X - 7,78) * 0.21
    //    Honorarios = (X - 7,78) - IVA
    // 3. El descuento en euros se calcula como: Descuento = 55,00 - X (si se aplicó cupón)
    $tasas = 7.78;
    $basePrice = 55.00;
    $finalAmount = isset($_POST['final_amount']) ? floatval($_POST['final_amount']) : $basePrice;
    $iva = ($finalAmount - $tasas) * 0.21;
    $honorarios = ($finalAmount - $tasas) - $iva;
    $descuento = $coupon_used ? ($basePrice - $finalAmount) : 0.00;
    // En este correo se mantiene el mismo cálculo, aunque en el recuadro se muestran Tasas+Honorarios e IVA por separado.
    $contableData = "IMPORTE TOTAL: " . number_format($finalAmount, 2, ',', '.') . " €; TASAS: " . number_format($tasas, 2, ',', '.') .
        " €; DESCUENTO: " . number_format($descuento, 2, ',', '.') . " €; IVA: " . number_format($iva, 2, ',', '.') .
        " €; HONORARIOS: " . number_format($honorarios, 2, ',', '.') . " €; CUPÓN USADO: " . ($coupon_used ? $coupon_used : "N/A");
    // Datos del cliente para el email
    $clientData = "Nombre: $customer_name\nDNI: $customer_dni\nEmail: $customer_email\nTeléfono: $customer_phone";
    $message_client = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9; border: 1px solid #e0e0e0; border-radius: 10px; }
            .header { text-align: center; margin-bottom: 20px; }
            .header img { max-width: 200px; height: auto; margin-bottom: 10px; }
            .content { padding: 20px; background-color: #ffffff; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
            .footer { margin-top: 30px; padding: 10px 20px; background-color: #016d86; color: #ffffff; text-align: left; font-size: 12px; border-radius: 8px; }
            h2 { color: #016d86; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <img src="https://www.tramitfy.es/wp-content/uploads/LOGO.png" alt="Tramitfy Logo">
                <h2>Confirmación de Renovación de Titulaciones</h2>
            </div>
            <div class="content">
                <p>Estimado/a <strong>' . htmlspecialchars($customer_name) . '</strong>,</p>
                <p>Hemos recibido su solicitud para la renovación de sus titulaciones. Su formulario ha sido procesado exitosamente y se ha generado el siguiente identificador único:</p>
                <p style="text-align:center; font-size:20px; font-weight:bold;">' . htmlspecialchars($unique_id) . '</p>
                <p><strong>Datos contables:</strong><br>
                   ' . $contableData . '
                </p>
                <p>En breve recibirá la documentación completa en su correo electrónico.</p>
                <p>Le agradecemos su confianza y quedamos a su disposición para cualquier consulta.</p>
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
    $subject_client = 'Confirmación de Renovación de Titulaciones';
    wp_mail($customer_email, $subject_client, $message_client, $headers_client);

    /*******************************************************
     * Inserción en la base de datos (Google Drive & Google Sheets)
     *******************************************************/
    // Se asume que el importe final viene en POST; de lo contrario se usa 55,00 €
    $finalAmount = isset($_POST['final_amount']) ? floatval($_POST['final_amount']) : $basePrice;

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
        // Insertar en columnas: Trámite ID, CLIENT DATA, BOAT DATA, CONTABLE DATA, VISITORS, LINKED DOCUMENTS, EXTRACT DATA, CAPITANÍA
        $clientData = "Nombre: $customer_name\nDNI: $customer_dni\nEmail: $customer_email\nTeléfono: $customer_phone";
        $tasas = 7.78;
        $basePrice = 55.00;
        $iva = ($finalAmount - $tasas) * 0.21;
        $honorarios = ($finalAmount - $tasas) - $iva;
        $descuento = $coupon_used ? ($basePrice - $finalAmount) : 0.00;
        $contableData = "IMPORTE TOTAL: " . number_format($finalAmount, 2, ',', '.') . " €; TASAS: " . number_format($tasas, 2, ',', '.') .
            " €; DESCUENTO: " . number_format($descuento, 2, ',', '.') . " €; IVA: " . number_format($iva, 2, ',', '.') .
            " €; HONORARIOS: " . number_format($honorarios, 2, ',', '.') . " €; CUPÓN USADO: " . ($coupon_used ? $coupon_used : "N/A");
        $rowValuesDatabase = [
            $unique_id,
            $clientData,
            "Renovación de Titulaciones",
            $contableData,
            "",
            implode("\n", $uploadedDriveLinks),
            "",
            ""  // Se eliminó la referencia a Capitanía Marítima
        ];
        $rangeDatabase = 'DATABASE!A1';
        $paramsDatabase = ['valueInputOption' => 'USER_ENTERED'];
        $sheetsService->spreadsheets_values->append($spreadsheetId, $rangeDatabase, new Google_Service_Sheets_ValueRange(['values' => [$rowValuesDatabase]]), $paramsDatabase);

        // --- Hoja "OrganizedData" ---
        // Se crea un arreglo de al menos 21 columnas (índices 0 a 20)
        $organizedRow = array_fill(0, 21, '');
        $organizedRow[0] = $unique_id;                   // ID Trámite
        $organizedRow[1] = $customer_name;               // Nombre
        $organizedRow[2] = $customer_dni;                // DNI
        $organizedRow[3] = $customer_email;              // Email
        $organizedRow[4] = $customer_phone;              // Teléfono
        $organizedRow[5] = "Renovación de Titulaciones";   // Tipo de Titulación
        $organizedRow[6] = "";                          // Se eliminó la referencia a Capitanía Marítima
        // Columnas 7 a 10 se dejan vacías
        $organizedRow[11] = ($coupon_used ? $coupon_used : "N/A"); // Cupón Aplicado
        // Columnas 12 y 13 vacías
        // La columna O (índice 14) refleja el importe final actualizado (con descuento)
        $organizedRow[14] = $finalAmount;
        $organizedRow[15] = "";                          // ITP (no aplica)
        $organizedRow[16] = $tasas;                       // Tasas
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
