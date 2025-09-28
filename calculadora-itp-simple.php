<?php
/**
 * Calculadora ITP Náutica V2 - Diseño de Dos Columnas
 *
 * Shortcode: [calc_itp_v2]
 * Layout: Información en tiempo real (izquierda) + Formulario (derecha)
 */

function calc_itp_v2_shortcode() {
    // Datos esenciales en formato JSON
    $itp_rates = json_encode([
        "Andalucía" => 0.04, "Aragón" => 0.04, "Asturias" => 0.04, "Islas Baleares" => 0.04,
        "Canarias" => 0.055, "Cantabria" => 0.08, "Castilla-La Mancha" => 0.06, "Castilla y León" => 0.05,
        "Cataluña" => 0.05, "Comunidad Valenciana" => 0.08, "Extremadura" => 0.06, "Galicia" => 0.01,
        "Madrid" => 0.04, "Murcia" => 0.04, "Navarra" => 0.04, "País Vasco" => 0.04,
        "La Rioja" => 0.04, "Ceuta" => 0.02, "Melilla" => 0.04
    ]);

    $depreciation_rates = json_encode([
        ["years" => 0, "rate" => 100], ["years" => 1, "rate" => 84], ["years" => 2, "rate" => 67],
        ["years" => 3, "rate" => 56], ["years" => 4, "rate" => 47], ["years" => 5, "rate" => 39],
        ["years" => 6, "rate" => 34], ["years" => 7, "rate" => 28], ["years" => 8, "rate" => 24],
        ["years" => 9, "rate" => 19], ["years" => 10, "rate" => 17], ["years" => 11, "rate" => 13],
        ["years" => 12, "rate" => 12], ["years" => 13, "rate" => 11], ["years" => 14, "rate" => 10],
        ["years" => 15, "rate" => 10]
    ]);

    ob_start();
    ?>

    <style>
        :root {
            --primary: #016d86;
            --primary-dark: #015767;
            --primary-light: #e6f5f7;
            --secondary: #02F9D2;
            --secondary-dark: #02d9b8;
            --warning: #ff9900;
            --danger: #ff4444;
            --dark: #2c3e50;
            --light: #f8f9fa;
            --border: #e0e6ed;
            --shadow: 0 4px 20px rgba(0,0,0,0.08);
            --radius: 12px;
        }

        .itp-calc-v2 {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            min-height: 400px;
        }

        .main-title {
            text-align: center;
            color: var(--primary);
            font-size: 28px;
            font-weight: 700;
            margin: 0 0 20px 0;
            padding: 0;
            line-height: 1.2;
        }

        .itp-layout {
            display: grid;
            grid-template-columns: 1fr 2fr;
            min-height: 450px;
        }

        /* COLUMNA IZQUIERDA - INFORMACIÓN */
        .itp-info-panel {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 25px;
            position: relative;
            overflow: hidden;
        }

        .itp-info-panel::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 200px;
            height: 200px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
        }

        .itp-info-header {
            margin-bottom: 25px;
            position: relative;
            z-index: 2;
        }

        .itp-info-header h2 {
            font-size: 28px;
            font-weight: 700;
            margin: 0 0 10px 0;
            line-height: 1.2;
        }

        .itp-info-header p {
            opacity: 0.9;
            font-size: 16px;
            margin: 0;
        }

        .itp-preview-card {
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(10px);
            border-radius: var(--radius);
            padding: 30px;
            margin-bottom: 30px;
            border: 1px solid rgba(255,255,255,0.2);
            position: relative;
            z-index: 2;
        }

        .itp-preview-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }

        .itp-preview-title .icon {
            margin-right: 10px;
            font-size: 20px;
        }

        .itp-calculation-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .itp-calculation-row:last-child {
            border-bottom: none;
            font-weight: 600;
            font-size: 18px;
            padding-top: 20px;
            border-top: 2px solid rgba(255,255,255,0.3);
        }

        .itp-amount-display {
            font-size: 48px;
            font-weight: 800;
            color: var(--success);
            text-align: center;
            margin: 30px 0;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }

        .itp-benefits {
            position: relative;
            z-index: 2;
        }

        .itp-info-card {
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(10px);
            border-radius: var(--radius);
            padding: 25px;
            margin-bottom: 20px;
            border: 1px solid rgba(255,255,255,0.2);
            position: relative;
            z-index: 2;
        }

        .itp-feature-item {
            display: flex;
            align-items: center;
            margin-bottom: 12px;
            font-size: 14px;
        }

        .itp-feature-item .feature-icon {
            margin-right: 12px;
            font-size: 16px;
        }

        /* COLUMNA DERECHA - FORMULARIO */
        .itp-form-panel {
            padding: 25px;
            background: #fff;
        }

        .itp-form-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .itp-form-header h3 {
            color: var(--dark);
            font-size: 24px;
            font-weight: 700;
            margin: 0 0 10px 0;
        }

        .itp-form-header .subtitle {
            color: #666;
            font-size: 16px;
        }

        .itp-steps {
            margin-bottom: 30px;
        }

        .itp-step {
            display: none;
            animation: slideIn 0.3s ease-out;
        }

        .itp-step.active {
            display: block;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateX(20px); }
            to { opacity: 1; transform: translateX(0); }
        }

        .itp-step-title {
            font-size: 20px;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 25px;
            display: flex;
            align-items: center;
        }

        .itp-step-title .step-number {
            background: var(--primary);
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 14px;
            font-weight: 700;
        }

        .itp-vehicle-options {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 25px;
        }

        .itp-vehicle-option {
            border: 2px solid var(--border);
            border-radius: var(--radius);
            padding: 15px 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }

        .itp-vehicle-option:hover {
            border-color: var(--primary);
            box-shadow: 0 4px 12px rgba(0,102,204,0.15);
        }

        .itp-vehicle-option.selected {
            border-color: var(--primary);
            background: var(--primary-light);
        }

        .itp-vehicle-option input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }


        .itp-vehicle-option h4 {
            margin: 0 0 8px 0;
            font-size: 18px;
            font-weight: 600;
            color: var(--dark);
        }

        .itp-vehicle-option p {
            margin: 0;
            font-size: 12px;
            color: #666;
        }

        .itp-form-group {
            margin-bottom: 25px;
        }

        .itp-form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark);
            font-size: 14px;
        }

        .itp-form-control {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid var(--border);
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: white;
            box-sizing: border-box;
        }

        .itp-form-control[type="number"] {
            -moz-appearance: textfield;
        }

        .itp-form-control[type="number"]::-webkit-outer-spin-button,
        .itp-form-control[type="number"]::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        /* Mejorar el input de precio */
        #purchase-price {
            font-size: 18px;
            font-weight: 600;
            text-align: left;
            padding-left: 16px;
        }

        #purchase-price::placeholder {
            text-align: left;
            font-weight: normal;
        }

        .itp-form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(0,102,204,0.1);
        }

        .itp-form-control.error {
            border-color: var(--danger);
        }

        .itp-error-message {
            color: var(--danger);
            font-size: 12px;
            margin-top: 5px;
            display: none;
        }

        .itp-form-control.error + .itp-error-message {
            display: block;
        }

        .itp-hint {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }

        .itp-btn-group {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        .itp-btn {
            flex: 1;
            padding: 16px 24px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            text-decoration: none;
            display: inline-block;
        }

        .itp-btn-primary {
            background: var(--primary);
            color: white;
        }

        .itp-btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }

        .itp-btn-secondary {
            background: var(--light);
            color: var(--dark);
            border: 2px solid var(--border);
        }

        .itp-btn-secondary:hover {
            background: var(--border);
        }

        .itp-progress-dots {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 30px;
        }

        .itp-progress-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--border);
            transition: all 0.3s ease;
        }

        .itp-progress-dot.active {
            background: var(--primary);
        }

        .itp-progress-dot.completed {
            background: var(--success);
        }

        /* Estilos específicos para el paso 3 de email */
        #step-3 .itp-form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(1, 109, 134, 0.1);
        }

        /* RESPONSIVE */
        @media (max-width: 768px) {
            .itp-layout {
                grid-template-columns: 1fr;
            }

            .itp-info-panel {
                padding: 20px 15px;
            }

            .itp-form-panel {
                padding: 25px 15px;
            }

            .itp-vehicle-options {
                grid-template-columns: 1fr;
            }

            .itp-btn-group {
                flex-direction: column;
            }

        }
    </style>

    <h1 class="main-title">Calculadora de ITP para Embarcaciones y Motos de Agua</h1>

    <div class="itp-calc-v2">
        <div class="itp-layout">
            <!-- COLUMNA IZQUIERDA - INFORMACIÓN -->
            <div class="itp-info-panel">

                <div style="text-align: center; padding: 25px 20px;">
                    <h2 style="color: white; margin: 0 0 20px 0; font-size: 24px; font-weight: 700;">Calcula tu ITP</h2>
                    <p style="margin: 0; opacity: 0.95; font-size: 16px; line-height: 1.6;">Calcula de forma rápida y precisa el Impuesto de Transmisiones Patrimoniales (ITP) que debes pagar por la compra de tu embarcación o moto de agua. Nuestra calculadora te ayudará a conocer el importe exacto según la normativa vigente de cada comunidad autónoma.</p>
                </div>

                <!-- Datos seleccionados del vehículo -->
                <div id="left-panel-vehicle-info" style="background: rgba(255,255,255,0.1); padding: 20px; border-radius: 12px; margin: 20px; display: none;">
                    <div style="text-align: center;">
                        <h4 style="color: white; margin: 0 0 15px 0; font-size: 18px;">📋 Datos seleccionados</h4>
                        <div style="display: flex; flex-direction: column; gap: 8px; font-size: 14px;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span>Tipo:</span>
                                <strong id="left-vehicle-type">Embarcación</strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span>Fabricante:</span>
                                <strong id="left-manufacturer">-</strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span>Modelo:</span>
                                <strong id="left-model">-</strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span>Año:</span>
                                <strong id="left-year">-</strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span>Precio:</span>
                                <strong id="left-price">-</strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span>Comunidad:</span>
                                <strong id="left-region">-</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- COLUMNA DERECHA - FORMULARIO -->
            <div class="itp-form-panel">

                <div class="itp-progress-dots" style="margin-bottom: 25px;">
                    <div class="itp-progress-dot active"></div>
                    <div class="itp-progress-dot"></div>
                    <div class="itp-progress-dot"></div>
                </div>

                <form id="itp-form-v2">
                    <!-- PASO 1: TIPO DE VEHÍCULO -->
                    <div class="itp-step active" id="step-1">
                        <div class="itp-step-title">
                            <span class="step-number">1</span>
                            <span>Tipo de embarcación</span>
                        </div>

                        <div class="itp-vehicle-options">
                            <div class="itp-vehicle-option selected" data-vehicle="barco">
                                <input type="radio" name="vehicleType" value="barco" checked>
                                <h4>Embarcación</h4>
                                <p>Veleros, motoras, etc.</p>
                            </div>
                            <div class="itp-vehicle-option" data-vehicle="moto">
                                <input type="radio" name="vehicleType" value="moto">
                                <h4>Moto Acuática</h4>
                                <p>Jet ski, motos de agua</p>
                            </div>
                        </div>

                        <div class="itp-form-group">
                            <label for="manufacturer">Fabricante</label>
                            <select class="itp-form-control" id="manufacturer" name="manufacturer">
                                <option value="">Selecciona un fabricante</option>
                            </select>
                            <div class="itp-error-message">Por favor selecciona un fabricante</div>
                        </div>

                        <div class="itp-form-group">
                            <label for="model">Modelo</label>
                            <select class="itp-form-control" id="model" name="model">
                                <option value="">Primero selecciona fabricante</option>
                            </select>
                            <div class="itp-error-message">Por favor selecciona un modelo</div>
                        </div>

                        <div class="itp-form-group" style="margin-top: 25px;">
                            <div style="background: #f0f8fa; border: 1px solid #016d86; padding: 15px; border-radius: 8px;">
                                <label style="display: flex; align-items: center; margin: 0; cursor: pointer; font-size: 14px; font-weight: 600; color: #016d86;">
                                    <input type="checkbox" id="no-model-found" name="noModelFound" style="margin-right: 10px; transform: scale(1.2);">
                                    <span>✏️ Introducir fabricante y modelo manualmente</span>
                                </label>
                                <p style="margin: 8px 0 0 0; font-size: 12px; color: #555; line-height: 1.4;">
                                    Si tu embarcación no aparece en nuestras listas, activa esta opción para introducir los datos manualmente. <strong>El cálculo se realizará sobre el precio de compra completo (sin depreciación por antigüedad).</strong>
                                </p>
                            </div>
                        </div>

                        <div class="itp-btn-group">
                            <button type="button" class="itp-btn itp-btn-primary" onclick="nextStep(2)">Continuar</button>
                        </div>
                    </div>

                    <!-- PASO 2: DATOS ECONÓMICOS -->
                    <div class="itp-step" id="step-2">
                        <div class="itp-step-title">
                            <span class="step-number">2</span>
                            <span>Datos de la transacción</span>
                        </div>

                        <div class="itp-form-group">
                            <label for="purchase-price">Precio de compra (€)</label>
                            <input type="number" class="itp-form-control" id="purchase-price" name="purchasePrice" placeholder="Ej: 25000" step="100" min="100">
                            <div class="itp-hint">El precio acordado con el vendedor</div>
                            <div class="itp-error-message">Por favor introduce el precio de compra</div>
                        </div>

                        <div class="itp-form-group">
                            <label for="matriculation-date">Fecha de matriculación</label>
                            <input type="date" class="itp-form-control" id="matriculation-date" name="matriculationDate">
                            <div class="itp-hint">La antigüedad afecta al valor fiscal</div>
                            <div class="itp-error-message">Por favor selecciona la fecha</div>
                        </div>

                        <div class="itp-form-group">
                            <label for="region">Comunidad Autónoma</label>
                            <select class="itp-form-control" id="region" name="region">
                                <option value="">Selecciona tu comunidad</option>
                                <option value="Andalucía">Andalucía (4%)</option>
                                <option value="Aragón">Aragón (4%)</option>
                                <option value="Asturias">Asturias (4%)</option>
                                <option value="Islas Baleares">Islas Baleares (4%)</option>
                                <option value="Canarias">Canarias (5.5%)</option>
                                <option value="Cantabria">Cantabria (8%)</option>
                                <option value="Castilla-La Mancha">Castilla-La Mancha (6%)</option>
                                <option value="Castilla y León">Castilla y León (5%)</option>
                                <option value="Cataluña">Cataluña (5%)</option>
                                <option value="Comunidad Valenciana">Comunidad Valenciana (8%)</option>
                                <option value="Extremadura">Extremadura (6%)</option>
                                <option value="Galicia">Galicia (1%)</option>
                                <option value="Madrid">Madrid (4%)</option>
                                <option value="Murcia">Murcia (4%)</option>
                                <option value="Navarra">Navarra (4%)</option>
                                <option value="País Vasco">País Vasco (4%)</option>
                                <option value="La Rioja">La Rioja (4%)</option>
                                <option value="Ceuta">Ceuta (2%)</option>
                                <option value="Melilla">Melilla (4%)</option>
                            </select>
                            <div class="itp-error-message">Por favor selecciona una comunidad</div>
                        </div>

                        <div class="itp-form-group">
                            <div class="itp-privacy-check">
                                <input type="checkbox" id="privacy-terms" required>
                                <span>Acepto la <a href="https://tramitfy.es/politica-de-privacidad/" target="_blank">Política de Privacidad</a> y los <a href="https://tramitfy.es/terminos-y-condiciones-de-uso-2/" target="_blank">Términos y Condiciones</a></span>
                            </div>
                            <div class="itp-error-message" id="privacy-error">Debes aceptar las políticas para continuar</div>
                        </div>

                        <div class="itp-btn-group">
                            <button type="button" class="itp-btn itp-btn-secondary" onclick="prevStep(1)">Anterior</button>
                            <button type="button" class="itp-btn itp-btn-primary" onclick="calculateITP()">Calcular ITP</button>
                        </div>
                    </div>

                    <!-- PASO 3: SOLICITUD DE EMAIL -->
                    <div class="itp-step" id="step-3">
                        <div class="itp-step-title">
                            <span class="step-number">3</span>
                            <span>Recibe tu cálculo detallado</span>
                        </div>

                        <div style="text-align: center; padding: 20px 0;">
                            <div style="background: linear-gradient(135deg, var(--primary-light) 0%, #f0f8fa 100%); border-radius: var(--radius); padding: 25px; margin-bottom: 25px; border: 2px solid var(--secondary);">
                                <h3 style="color: var(--primary); margin: 0 0 15px 0; font-size: 22px; font-weight: 700;">🎯 ¡Tu cálculo está listo!</h3>
                                <p style="margin: 0; color: var(--dark); font-size: 16px; line-height: 1.6;">Hemos procesado todos los datos de tu <strong id="email-vehicle-type">embarcación</strong>.<br>Te enviamos el informe completo con toda la información necesaria.</p>
                            </div>

                            <div class="itp-form-group" style="text-align: left;">
                                <label for="email-input-step3" style="font-size: 16px; color: var(--dark);">📧 Tu dirección de email</label>
                                <input type="email" class="itp-form-control" id="email-input-step3" name="email" placeholder="ejemplo@correo.com" required style="font-size: 16px; padding: 16px;">
                                <div class="itp-hint">Te enviaremos el cálculo detallado del ITP y la documentación necesaria</div>
                                <div class="itp-error-message">Por favor introduce un email válido</div>
                            </div>

                            <div class="itp-btn-group">
                                <button type="button" class="itp-btn itp-btn-secondary" onclick="prevStep(2)">← Modificar datos</button>
                                <button type="button" class="itp-btn" id="submit-email-step3" onclick="submitEmailStep3()" style="background: var(--secondary); color: var(--dark); font-weight: 700;">
                                    📧 Enviar mi cálculo GRATIS
                                </button>
                            </div>

                            <p style="font-size: 12px; color: #666; text-align: center; margin-top: 20px; line-height: 1.4;">
                                Al proceder, confirmas haber aceptado nuestras políticas de privacidad y términos de uso en el paso anterior.
                            </p>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Variables globales
        const itpRates = <?php echo $itp_rates; ?>;
        const depreciationRates = <?php echo $depreciation_rates; ?>;
        let currentStep = 1;
        let currentVehicleType = 'barco';
        let calculationData = {};

        // Inicialización
        document.addEventListener('DOMContentLoaded', function() {
            loadManufacturers();
            setupEventListeners();
            updateRealTimeDisplay();
        });

        // Event Listeners
        function setupEventListeners() {
            // Selección de tipo de vehículo
            document.querySelectorAll('.itp-vehicle-option').forEach(option => {
                option.addEventListener('click', function() {
                    // Actualizar visualización
                    document.querySelectorAll('.itp-vehicle-option').forEach(opt => opt.classList.remove('selected'));
                    this.classList.add('selected');

                    // Actualizar radio button
                    const radio = this.querySelector('input[type="radio"]');
                    radio.checked = true;
                    currentVehicleType = radio.value;

                    // Limpiar modelos cuando cambie el tipo de vehículo
                    const modelSelect = document.getElementById('model');
                    modelSelect.innerHTML = '<option value="">Primero selecciona fabricante</option>';
                    modelSelect.disabled = true;

                    // Recargar fabricantes
                    loadManufacturers();
                    updateRealTimeDisplay();
                });
            });

            // Cambios en formulario
            document.getElementById('manufacturer').addEventListener('change', function() {
                // Limpiar el select de modelos antes de cargar nuevos
                const modelSelect = document.getElementById('model');
                modelSelect.innerHTML = '<option value="">Primero selecciona fabricante</option>';
                modelSelect.disabled = true;

                loadModels();
                updateRealTimeDisplay();
            });

            document.getElementById('model').addEventListener('change', updateRealTimeDisplay);
            document.getElementById('purchase-price').addEventListener('input', updateRealTimeDisplay);
            document.getElementById('matriculation-date').addEventListener('change', updateRealTimeDisplay);
            document.getElementById('region').addEventListener('change', updateRealTimeDisplay);

            // Checkbox "Introducir manualmente"
            document.getElementById('no-model-found').addEventListener('change', function() {
                const manufacturerContainer = document.querySelector('label[for="manufacturer"]').parentNode;
                const modelContainer = document.querySelector('label[for="model"]').parentNode;
                const manufacturerLabel = document.querySelector('label[for="manufacturer"]');
                const modelLabel = document.querySelector('label[for="model"]');
                const isChecked = this.checked;

                if (isChecked) {
                    // Convertir a inputs manuales
                    const currentManufacturer = document.getElementById('manufacturer').value;
                    const currentModel = document.getElementById('model').value;

                    // Cambiar labels
                    manufacturerLabel.textContent = 'Fabricante (manual)';
                    modelLabel.textContent = 'Modelo (manual)';

                    // Reemplazar manufacturer select con input
                    manufacturerContainer.innerHTML = `
                        <label for="manufacturer">Fabricante (manual)</label>
                        <input type="text" class="itp-form-control" id="manufacturer" name="manufacturer"
                               placeholder="Ej: Beneteau, Jeanneau, Sea Ray..."
                               value="${currentManufacturer}"
                               style="background: #f0f8fa; border: 2px solid #016d86;">
                        <div class="itp-hint">Introduce el fabricante de tu embarcación</div>
                        <div class="itp-error-message">Por favor introduce el fabricante</div>
                    `;

                    // Reemplazar model select con input
                    modelContainer.innerHTML = `
                        <label for="model">Modelo (manual)</label>
                        <input type="text" class="itp-form-control" id="model" name="model"
                               placeholder="Ej: Oceanis 46, First 35, Sundancer 280..."
                               value="${currentModel}"
                               style="background: #f0f8fa; border: 2px solid #016d86;">
                        <div class="itp-hint">Introduce el modelo específico</div>
                        <div class="itp-error-message">Por favor introduce el modelo</div>
                    `;

                    // Añadir listeners a los nuevos inputs
                    document.getElementById('manufacturer').addEventListener('input', updateRealTimeDisplay);
                    document.getElementById('model').addEventListener('input', updateRealTimeDisplay);

                } else {
                    // Restaurar selects originales
                    manufacturerContainer.innerHTML = `
                        <label for="manufacturer">Fabricante</label>
                        <select class="itp-form-control" id="manufacturer" name="manufacturer">
                            <option value="">Selecciona un fabricante</option>
                        </select>
                        <div class="itp-error-message">Por favor selecciona un fabricante</div>
                    `;

                    modelContainer.innerHTML = `
                        <label for="model">Modelo</label>
                        <select class="itp-form-control" id="model" name="model">
                            <option value="">Primero selecciona fabricante</option>
                        </select>
                        <div class="itp-error-message">Por favor selecciona un modelo</div>
                    `;

                    // Restaurar listeners originales
                    document.getElementById('manufacturer').addEventListener('change', function() {
                        const modelSelect = document.getElementById('model');
                        modelSelect.innerHTML = '<option value="">Primero selecciona fabricante</option>';
                        modelSelect.disabled = true;
                        loadModels();
                        updateRealTimeDisplay();
                    });

                    document.getElementById('model').addEventListener('change', updateRealTimeDisplay);

                    // Recargar fabricantes
                    loadManufacturers();
                }

                updateRealTimeDisplay();
            });
        }

        // Navegación entre pasos
        function nextStep(step) {
            if (validateCurrentStep()) {
                showStep(step);
            }
        }

        function prevStep(step) {
            showStep(step);
        }

        function showStep(step) {
            // Ocultar todos los pasos
            document.querySelectorAll('.itp-step').forEach(s => s.classList.remove('active'));

            // Mostrar paso actual
            document.getElementById(`step-${step}`).classList.add('active');

            // Actualizar puntos de progreso
            document.querySelectorAll('.itp-progress-dot').forEach((dot, index) => {
                dot.classList.remove('active', 'completed');
                if (index + 1 < step) {
                    dot.classList.add('completed');
                } else if (index + 1 === step) {
                    dot.classList.add('active');
                }
            });

            currentStep = step;
            updateRealTimeDisplay();
        }

        // Validaciones
        function validateCurrentStep() {
            if (currentStep === 1) {
                const manufacturer = document.getElementById('manufacturer').value;
                const model = document.getElementById('model').value;
                const noModelFound = document.getElementById('no-model-found').checked;

                if (noModelFound) {
                    // Validación para entrada manual - verificar que los campos de texto tengan contenido
                    if (!manufacturer || manufacturer.trim() === '') {
                        showError('manufacturer', 'Por favor introduce el fabricante');
                        return false;
                    }

                    if (!model || model.trim() === '') {
                        showError('model', 'Por favor introduce el modelo');
                        return false;
                    }
                } else {
                    // Validación normal para selects
                    if (!manufacturer) {
                        showError('manufacturer', 'Por favor selecciona un fabricante');
                        return false;
                    }

                    if (!model) {
                        showError('model', 'Por favor selecciona un modelo');
                        return false;
                    }
                }

                clearErrors(['manufacturer', 'model']);
                return true;
            }

            if (currentStep === 2) {
                const price = document.getElementById('purchase-price').value;
                const date = document.getElementById('matriculation-date').value;
                const region = document.getElementById('region').value;
                const privacyCheck = document.getElementById('privacy-terms').checked;

                if (!price || price <= 0) {
                    showError('purchase-price', 'Por favor introduce un precio válido');
                    return false;
                }

                if (!date) {
                    showError('matriculation-date', 'Por favor selecciona la fecha');
                    return false;
                }

                if (!region) {
                    showError('region', 'Por favor selecciona una comunidad');
                    return false;
                }

                if (!privacyCheck) {
                    document.getElementById('privacy-error').style.display = 'block';
                    return false;
                } else {
                    document.getElementById('privacy-error').style.display = 'none';
                }

                clearErrors(['purchase-price', 'matriculation-date', 'region']);
                return true;
            }

            return true;
        }

        function showError(fieldId, message) {
            const field = document.getElementById(fieldId);
            const errorMsg = field.parentNode.querySelector('.itp-error-message');

            field.classList.add('error');
            if (errorMsg) {
                errorMsg.textContent = message;
            }
        }

        function clearErrors(fieldIds) {
            fieldIds.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                field.classList.remove('error');
            });
        }

        // Carga de datos
        function loadManufacturers() {
            const manufacturerSelect = document.getElementById('manufacturer');
            manufacturerSelect.innerHTML = '<option value="">Cargando fabricantes...</option>';
            manufacturerSelect.disabled = true;

            // Determinar qué archivo CSV cargar según el tipo de vehículo
            const csvFile = (currentVehicleType === 'moto') ? 'MOTO.csv' : 'data.csv';

            // Ruta relativa que funciona independientemente del tema
            const themeUrl = "<?php echo get_template_directory_uri(); ?>";
            const csvUrl = `${themeUrl}/${csvFile}`;

            console.log(`Cargando datos desde: ${csvUrl}`);

            // Usar fetch para cargar los datos del CSV
            fetch(csvUrl)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`Error HTTP: ${response.status}`);
                    }
                    return response.text();
                })
                .then(csvText => {
                    console.log('CSV cargado correctamente');

                    // Parsear CSV y extraer fabricantes únicos
                    const lines = csvText.split('\n').filter(line => line.trim() !== '');
                    const manufacturers = new Set();

                    // Saltar la primera línea (headers) si existe
                    for (let i = 1; i < lines.length; i++) {
                        const columns = lines[i].split(',');
                        if (columns.length >= 2 && columns[0].trim()) {
                            manufacturers.add(columns[0].trim().replace(/"/g, ''));
                        }
                    }

                    // Convertir Set a Array y ordenar
                    const manufacturerList = Array.from(manufacturers).sort();

                    // Actualizar el select
                    manufacturerSelect.innerHTML = '<option value="">Selecciona un fabricante</option>';
                    manufacturerList.forEach(manufacturer => {
                        const option = document.createElement('option');
                        option.value = manufacturer;
                        option.textContent = manufacturer;
                        manufacturerSelect.appendChild(option);
                    });

                    manufacturerSelect.disabled = false;

                    // Guardar los datos completos para usar en loadModels
                    window.vehicleData = lines;

                    console.log(`${manufacturerList.length} fabricantes cargados`);
                })
                .catch(error => {
                    console.error('Error cargando fabricantes:', error);
                    manufacturerSelect.innerHTML = '<option value="">Error cargando datos</option>';
                    manufacturerSelect.disabled = false;
                });
        }

        function loadModels() {
            const manufacturer = document.getElementById('manufacturer').value;
            const modelSelect = document.getElementById('model');

            if (!manufacturer) {
                modelSelect.innerHTML = '<option value="">Primero selecciona fabricante</option>';
                modelSelect.disabled = true;
                return;
            }

            if (!window.vehicleData) {
                modelSelect.innerHTML = '<option value="">Error: datos no disponibles</option>';
                return;
            }

            modelSelect.innerHTML = '<option value="">Cargando modelos...</option>';
            modelSelect.disabled = true;

            // Buscar modelos del fabricante seleccionado
            const models = new Set();

            window.vehicleData.forEach((line, index) => {
                if (index === 0) return; // Saltar headers

                const columns = line.split(',');
                if (columns.length >= 2) {
                    const csvManufacturer = columns[0].trim().replace(/"/g, '');
                    const csvModel = columns[1].trim().replace(/"/g, '');

                    if (csvManufacturer === manufacturer && csvModel) {
                        models.add(csvModel);
                    }
                }
            });

            // Convertir Set a Array y ordenar
            const modelList = Array.from(models).sort();

            // Actualizar el select
            modelSelect.innerHTML = '<option value="">Selecciona un modelo</option>';
            modelList.forEach(model => {
                const option = document.createElement('option');
                option.value = model;
                option.textContent = model;
                modelSelect.appendChild(option);
            });

            modelSelect.disabled = false;

            console.log(`${modelList.length} modelos cargados para ${manufacturer}`);
        }

        // Actualización en tiempo real
        function updateRealTimeDisplay() {
            // Recoger datos del formulario
            const manufacturer = document.getElementById('manufacturer').value;
            const model = document.getElementById('model').value;
            const date = document.getElementById('matriculation-date').value;
            const price = document.getElementById('purchase-price').value;
            const region = document.getElementById('region').value;
            const noModelFound = document.getElementById('no-model-found').checked;

            // Actualizar panel izquierdo con resumen de datos
            const leftPanelInfo = document.getElementById('left-panel-vehicle-info');
            if (leftPanelInfo && (manufacturer || model || date || price || region || noModelFound)) {
                leftPanelInfo.style.display = 'block';

                // Actualizar cada campo
                document.getElementById('left-vehicle-type').textContent = currentVehicleType === 'barco' ? 'Embarcación' : 'Moto Acuática';

                if (noModelFound) {
                    document.getElementById('left-manufacturer').textContent = manufacturer || 'Introducir manualmente';
                    document.getElementById('left-model').textContent = model || 'Introducir manualmente';
                } else {
                    document.getElementById('left-manufacturer').textContent = manufacturer || '-';
                    document.getElementById('left-model').textContent = model || '-';
                }

                document.getElementById('left-year').textContent = date ? new Date(date).getFullYear() : '-';
                document.getElementById('left-price').textContent = price ? formatCurrency(parseFloat(price)) : '-';
                document.getElementById('left-region').textContent = region || '-';
            } else if (leftPanelInfo) {
                leftPanelInfo.style.display = 'none';
            }

            // Actualizar el tipo de vehículo en el paso de email
            if (document.getElementById('email-vehicle-type')) {
                document.getElementById('email-vehicle-type').textContent = currentVehicleType === 'barco' ? 'embarcación' : 'moto acuática';
            }
        }

        // Los cálculos se realizan internamente y se envían por email

        // Cálculo final
        function calculateITP() {
            if (validateCurrentStep()) {
                // Recopilar datos para el cálculo
                const noModelFound = document.getElementById('no-model-found').checked;
                calculationData = {
                    vehicleType: currentVehicleType,
                    manufacturer: document.getElementById('manufacturer').value,
                    model: document.getElementById('model').value,
                    purchasePrice: parseFloat(document.getElementById('purchase-price').value),
                    matriculationDate: document.getElementById('matriculation-date').value,
                    region: document.getElementById('region').value,
                    noModelFound: noModelFound
                };

                // Actualizar la información del vehículo en el paso 3
                updateRealTimeDisplay();

                showStep(3);
            }
        }

        // Enviar email desde el paso 3
        function submitEmailStep3() {
            const email = document.getElementById('email-input-step3').value;
            const submitBtn = document.getElementById('submit-email-step3');

            // Validar email
            if (!email) {
                showError('email-input-step3', 'Por favor introduce tu email');
                return;
            }

            if (!isValidEmail(email)) {
                showError('email-input-step3', 'Por favor introduce un email válido');
                return;
            }

            clearErrors(['email-input-step3']);

            // Deshabilitar botón y mostrar estado de carga
            submitBtn.disabled = true;
            submitBtn.innerHTML = '📧 Enviando...';
            submitBtn.style.background = '#cccccc';

            // Preparar datos para envio
            const formData = new FormData();
            formData.append('action', 'enviar_email_itp_v2');
            formData.append('email', email);
            formData.append('vehicleType', calculationData.vehicleType);
            formData.append('manufacturer', calculationData.manufacturer);
            formData.append('model', calculationData.model);
            formData.append('purchasePrice', calculationData.purchasePrice);
            formData.append('matriculationDate', calculationData.matriculationDate);
            formData.append('region', calculationData.region);
            formData.append('noModelFound', calculationData.noModelFound ? '1' : '0');
            formData.append('nonce', '<?php echo wp_create_nonce("itp_email_nonce"); ?>');

            // Enviar via AJAX
            fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ ¡Tu cálculo ha sido enviado correctamente!');

                    // Redireccionar
                    setTimeout(() => {
                        window.location.href = 'https://tramitfy.es/cambio-titularidad-embarcacion/';
                    }, 1500);
                } else {
                    alert('❌ Error al enviar el email: ' + (data.data || 'Error desconocido'));

                    // Rehabilitar botón
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '📧 Enviar mi cálculo GRATIS';
                    submitBtn.style.background = 'var(--secondary)';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('❌ Error de conexión. Intenta de nuevo.');

                // Rehabilitar botón
                submitBtn.disabled = false;
                submitBtn.innerHTML = '📧 Enviar mi cálculo GRATIS';
                submitBtn.style.background = 'var(--secondary)';
            });
        }

        // Validar formato de email
        function isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }

        // Utilidades
        function formatCurrency(amount) {
            return new Intl.NumberFormat('es-ES', {
                style: 'currency',
                currency: 'EUR',
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            }).format(amount);
        }

        // Formatear input de precio mientras el usuario escribe
        document.addEventListener('DOMContentLoaded', function() {
            const priceInput = document.getElementById('purchase-price');

            priceInput.addEventListener('input', function(e) {
                let value = e.target.value;

                // Eliminar caracteres no numéricos excepto puntos y comas
                value = value.replace(/[^0-9]/g, '');

                // Actualizar el valor del input
                e.target.value = value;
            });

            // Formatear al perder el foco
            priceInput.addEventListener('blur', function(e) {
                let value = parseInt(e.target.value);
                if (!isNaN(value) && value >= 100) {
                    // Mantener el valor sin formatear para cálculos
                    e.target.value = value;
                }
            });
        });
    </script>

    <?php
    return ob_get_clean();
}

// Registrar el shortcode
add_shortcode('calc_itp_v2', 'calc_itp_v2_shortcode');

// Función AJAX para el envío de email
function enviar_email_itp_v2() {
    // Verificar nonce de seguridad
    if (!wp_verify_nonce($_POST['nonce'], 'itp_email_nonce')) {
        wp_send_json_error('Token de seguridad inválido');
        return;
    }

    // Validar y sanitizar datos
    $email = sanitize_email($_POST['email']);
    $vehicle_type = sanitize_text_field($_POST['vehicleType']);
    $manufacturer = sanitize_text_field($_POST['manufacturer']);
    $model = sanitize_text_field($_POST['model']);
    $purchase_price = floatval($_POST['purchasePrice']);
    $matriculation_date = sanitize_text_field($_POST['matriculationDate']);
    $region = sanitize_text_field($_POST['region']);
    $no_model_found = isset($_POST['noModelFound']) && $_POST['noModelFound'] === '1';

    if (!is_email($email)) {
        wp_send_json_error('Email inválido');
        return;
    }

    // Calcular ITP
    $itp_rates = [
        "Andalucía" => 0.04, "Aragón" => 0.04, "Asturias" => 0.04, "Islas Baleares" => 0.04,
        "Canarias" => 0.055, "Cantabria" => 0.08, "Castilla-La Mancha" => 0.06, "Castilla y León" => 0.05,
        "Cataluña" => 0.05, "Comunidad Valenciana" => 0.08, "Extremadura" => 0.06, "Galicia" => 0.01,
        "Madrid" => 0.04, "Murcia" => 0.04, "Navarra" => 0.04, "País Vasco" => 0.04,
        "La Rioja" => 0.04, "Ceuta" => 0.02, "Melilla" => 0.04
    ];

    $depreciation_rates = [
        ["years" => 0, "rate" => 100], ["years" => 1, "rate" => 84], ["years" => 2, "rate" => 67],
        ["years" => 3, "rate" => 56], ["years" => 4, "rate" => 47], ["years" => 5, "rate" => 39],
        ["years" => 6, "rate" => 34], ["years" => 7, "rate" => 28], ["years" => 8, "rate" => 24],
        ["years" => 9, "rate" => 19], ["years" => 10, "rate" => 17], ["years" => 11, "rate" => 13],
        ["years" => 12, "rate" => 12], ["years" => 13, "rate" => 11], ["years" => 14, "rate" => 14],
        ["years" => 15, "rate" => 10]
    ];

    // Calcular antigüedad y depreciación
    $matriculation_year = date('Y', strtotime($matriculation_date));
    $current_year = date('Y');
    $vehicle_age = $current_year - $matriculation_year;

    if ($no_model_found) {
        // Sin modelo específico: no aplicar depreciación
        $depreciation_rate = 100;
        $fiscal_value = $purchase_price; // Precio completo sin depreciación
    } else {
        // Con modelo específico: aplicar depreciación por antigüedad
        $depreciation_rate = 100;
        foreach ($depreciation_rates as $rate_data) {
            if ($vehicle_age >= $rate_data['years']) {
                $depreciation_rate = $rate_data['rate'];
            }
        }
        $fiscal_value = $purchase_price * ($depreciation_rate / 100);
    }

    // Calcular ITP
    $itp_rate = isset($itp_rates[$region]) ? $itp_rates[$region] : 0.04;
    $itp_amount = $fiscal_value * $itp_rate;

    // Formatear moneda
    $purchase_price_formatted = number_format($purchase_price, 0, ',', '.') . ' €';
    $fiscal_value_formatted = number_format($fiscal_value, 0, ',', '.') . ' €';
    $itp_amount_formatted = number_format($itp_amount, 0, ',', '.') . ' €';

    // Calcular precio total del servicio
    $service_fee = 134.95; // Precio actualizado
    $total_cost = $itp_amount + $service_fee;
    $total_cost_formatted = number_format($total_cost, 2, ',', '.') . ' €';
    $service_fee_formatted = number_format($service_fee, 2, ',', '.') . ' €';

    // Desglose como en el formulario
    $tasas_gestion = 114.87;
    $iva_servicio = 20.12;
    $comision_bancaria = $itp_amount * 0.015; // 1.5% del ITP

    // Formatear desglose
    $tasas_gestion_formatted = number_format($tasas_gestion, 2, ',', '.') . ' €';
    $iva_servicio_formatted = number_format($iva_servicio, 2, ',', '.') . ' €';
    $comision_bancaria_formatted = number_format($comision_bancaria, 2, ',', '.') . ' €';

    // Calcular precio con descuento - SOLO sobre la gestión (134,99€), NO sobre el ITP
    $discount_amount = $service_fee * 0.05; // 5% de descuento solo sobre la gestión
    $discounted_service_fee = $service_fee - $discount_amount;
    $total_with_discount = $itp_amount + $discounted_service_fee + $comision_bancaria; // ITP + gestión con descuento + comisión
    $total_with_discount_formatted = number_format($total_with_discount, 2, ',', '.') . ' €';
    $discount_amount_formatted = number_format($discount_amount, 2, ',', '.') . ' €';
    $discounted_service_fee_formatted = number_format($discounted_service_fee, 2, ',', '.') . ' €';

    // Preparar contenido del email
    $vehicle_type_text = ($vehicle_type === 'barco') ? 'Embarcación' : 'Moto Acuática';

    // Calcular suma para comparativa (ITP + 134.95)
    $total_gestion_nosotros = $itp_amount + $service_fee;
    $total_gestion_nosotros_formatted = number_format($total_gestion_nosotros, 2, ',', '.') . ' €';

    // Calcular suma para DIY (ITP + Tasas Capitanía)
    $tasas_capitania = 19.03;
    $total_diy = $itp_amount + $tasas_capitania;
    $total_diy_formatted = number_format($total_diy, 2, ',', '.') . ' €';
    $tasas_capitania_formatted = number_format($tasas_capitania, 2, ',', '.') . ' €';

    $subject = "Tu cálculo de ITP - $vehicle_type_text $manufacturer $model";

    $message = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <!--[if !mso]><!-->
        <meta http-equiv='X-UA-Compatible' content='IE=edge'>
        <!--<![endif]-->
        <style>
            body {
                margin: 0;
                padding: 0;
                font-family: Arial, Helvetica, sans-serif;
                line-height: 1.5;
                color: #333333;
                background-color: #f4f4f4;
            }
            table {
                border-spacing: 0;
                mso-table-lspace: 0pt;
                mso-table-rspace: 0pt;
            }
            td {
                padding: 0;
            }
            img {
                border: 0;
            }
            .email-wrapper {
                width: 100%;
                table-layout: fixed;
                background-color: #f4f4f4;
                padding: 20px 0;
            }
            .email-container {
                max-width: 550px;
                margin: 0 auto;
                background-color: #ffffff;
                border: 1px solid #dddddd;
            }
            .header {
                background-color: #016d86;
                padding: 25px 20px;
                text-align: center;
            }
            .header h1 {
                color: #ffffff;
                font-size: 24px;
                font-weight: bold;
                margin: 0;
                padding: 0;
            }
            .header p {
                color: #ffffff;
                font-size: 13px;
                margin: 5px 0 0 0;
                padding: 0;
            }
            .content {
                padding: 25px 20px;
                background-color: #ffffff;
            }

            /* Cupón discreto */
            .discount-box {
                background-color: #f0f8fa;
                border: 1px solid #b3e5f4;
                padding: 15px;
                margin: 20px 0;
                text-align: center;
            }
            .discount-title {
                color: #016d86;
                font-size: 14px;
                font-weight: bold;
                margin: 0 0 8px 0;
            }
            .discount-code {
                background-color: #ffffff;
                color: #016d86;
                display: inline-block;
                padding: 8px 20px;
                font-size: 18px;
                font-weight: bold;
                letter-spacing: 1px;
                border: 1px dashed #016d86;
                margin: 5px 0;
            }
            .discount-text {
                color: #555555;
                font-size: 12px;
                margin: 5px 0 0 0;
            }

            /* Tabla de datos compacta */
            .data-table {
                width: 100%;
                margin: 15px 0;
            }
            .data-table td {
                padding: 6px 8px;
                border-bottom: 1px solid #eeeeee;
                font-size: 13px;
            }
            .data-table .label {
                color: #666666;
                width: 40%;
            }
            .data-table .value {
                color: #333333;
                font-weight: bold;
            }
            .section-title {
                color: #016d86;
                font-size: 15px;
                font-weight: bold;
                margin: 20px 0 10px 0;
                padding-bottom: 5px;
                border-bottom: 1px solid #dddddd;
            }

            /* Resultado del cálculo */
            .result-box {
                background-color: #016d86;
                color: #ffffff;
                padding: 15px;
                margin: 20px 0;
                text-align: center;
            }
            .result-label {
                font-size: 12px;
                margin: 0;
                padding: 0;
            }
            .result-amount {
                font-size: 24px;
                font-weight: bold;
                margin: 5px 0;
                padding: 0;
            }

            /* Lista de servicios */
            .services-list {
                margin: 15px 0;
                padding: 0;
            }
            .service-item {
                color: #555555;
                font-size: 13px;
                padding: 4px 0;
                margin: 0;
            }

            /* Botón CTA */
            .cta-section {
                text-align: center;
                margin: 25px 0;
            }
            .cta-button {
                display: inline-block;
                background-color: #016d86;
                color: #ffffff !important;
                padding: 12px 30px;
                text-decoration: none;
                font-weight: bold;
                font-size: 14px;
            }
            .cta-hint {
                font-size: 11px;
                color: #666666;
                margin: 8px 0 0 0;
            }

            /* Footer */
            .footer {
                background-color: #f4f4f4;
                padding: 20px;
                text-align: center;
                border-top: 1px solid #dddddd;
            }
            .footer-text {
                font-size: 11px;
                color: #666666;
                line-height: 1.5;
                margin: 0;
                padding: 0;
            }
            .footer-links {
                margin: 10px 0 0 0;
            }
            .footer-links a {
                color: #016d86;
                text-decoration: none;
                font-size: 11px;
                margin: 0 5px;
            }
            .warning-box {
                background-color: #fff3cd;
                border: 1px solid #ffc107;
                padding: 10px;
                margin: 15px 0;
                font-size: 11px;
                color: #666666;
            }
        </style>
    </head>
    <body>
        <table class='email-wrapper' role='presentation'>
            <tr>
                <td align='center'>
                    <table class='email-container' role='presentation'>
                        <!-- Header -->
                        <tr>
                            <td class='header'>
                                <h1>TRAMITFY</h1>
                                <p>Cálculo de ITP para embarcaciones</p>
                            </td>
                        </tr>

                        <!-- Contenido -->
                        <tr>
                            <td class='content'>
                                <!-- Título -->
                                <h2 style='color: #016d86; font-size: 18px; margin: 0 0 15px 0;'>Cálculo completado</h2>

                                <!-- Datos comprimidos en dos columnas -->
                                <table role='presentation' width='100%' style='border-spacing: 10px; margin: 15px 0;'>
                                    <tr>
                                        <!-- Columna izquierda: Datos del vehículo -->
                                        <td style='width: 48%; vertical-align: top;'>
                                            <h3 style='color: #016d86; font-size: 14px; font-weight: bold; margin: 0 0 10px 0; padding-bottom: 5px; border-bottom: 1px solid #dddddd;'>Datos del vehículo</h3>
                                            <table style='width: 100%; font-size: 12px;' role='presentation'>
                                                <tr>
                                                    <td style='color: #666666; padding: 4px 0; width: 45%;'>Tipo:</td>
                                                    <td style='color: #333333; font-weight: bold; padding: 4px 0;'>$vehicle_type_text</td>
                                                </tr>
                                                <tr>
                                                    <td style='color: #666666; padding: 4px 0;'>Fabricante:</td>
                                                    <td style='color: #333333; font-weight: bold; padding: 4px 0;'>$manufacturer</td>
                                                </tr>
                                                <tr>
                                                    <td style='color: #666666; padding: 4px 0;'>Modelo:</td>
                                                    <td style='color: #333333; font-weight: bold; padding: 4px 0;'>$model</td>
                                                </tr>
                                                <tr>
                                                    <td style='color: #666666; padding: 4px 0;'>Año:</td>
                                                    <td style='color: #333333; font-weight: bold; padding: 4px 0;'>$matriculation_year</td>
                                                </tr>
                                                <tr>
                                                    <td style='color: #666666; padding: 4px 0;'>Precio:</td>
                                                    <td style='color: #333333; font-weight: bold; padding: 4px 0;'>$purchase_price_formatted</td>
                                                </tr>
                                                <tr>
                                                    <td style='color: #666666; padding: 4px 0;'>Comunidad:</td>
                                                    <td style='color: #333333; font-weight: bold; padding: 4px 0;'>$region</td>
                                                </tr>
                                            </table>
                                        </td>

                                        <!-- Columna derecha: Desglose del cálculo -->
                                        <td style='width: 48%; vertical-align: top;'>
                                            <h3 style='color: #016d86; font-size: 14px; font-weight: bold; margin: 0 0 10px 0; padding-bottom: 5px; border-bottom: 1px solid #dddddd;'>Desglose del cálculo</h3>
                                            <table style='width: 100%; font-size: 12px;' role='presentation'>
                                                <tr>
                                                    <td style='color: #666666; padding: 4px 0; width: 50%;'>Antigüedad:</td>
                                                    <td style='color: #333333; font-weight: bold; padding: 4px 0;'>$vehicle_age años</td>
                                                </tr>
                                                <tr>
                                                    <td style='color: #666666; padding: 4px 0;'>Depreciación:</td>
                                                    <td style='color: #333333; font-weight: bold; padding: 4px 0;'>" . ($no_model_found ? 'Sin aplicar (manual)' : "{$depreciation_rate}%") . "</td>
                                                </tr>
                                                <tr>
                                                    <td style='color: #666666; padding: 4px 0;'>Valor fiscal:</td>
                                                    <td style='color: #333333; font-weight: bold; padding: 4px 0;'>$fiscal_value_formatted</td>
                                                </tr>
                                                <tr>
                                                    <td style='color: #666666; padding: 4px 0;'>Tipo ITP:</td>
                                                    <td style='color: #333333; font-weight: bold; padding: 4px 0;'>" . ($itp_rate * 100) . "%</td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                </table>

                                <!-- Resultado -->
                                <table class='result-box' role='presentation' width='100%'>
                                    <tr>
                                        <td>
                                            <p class='result-label'>ITP A PAGAR</p>
                                            <p class='result-amount'>$itp_amount_formatted</p>
                                        </td>
                                    </tr>
                                </table>

                                <!-- Comparativa de precios -->
                                <div style='margin: 25px 0; background-color: #f8f9fa; border: 1px solid #e9ecef; padding: 20px;'>
                                    <h4 style='color: #016d86; font-size: 16px; margin: 0 0 15px 0; text-align: center; font-weight: bold;'>
                                        Comparativa: Hacerlo tú vs. Gestión completa
                                    </h4>
                                    <table role='presentation' width='100%' style='border-spacing: 10px;'>
                                        <tr>
                                            <!-- Opción DIY -->
                                            <td style='width: 48%; vertical-align: top;'>
                                                <div style='background-color: #fff; border: 2px solid #dc3545; padding: 15px; text-align: center; border-radius: 8px;'>
                                                    <h5 style='color: #dc3545; margin: 0 0 8px 0; font-size: 14px; font-weight: bold;'>Si lo haces TÚ</h5>
                                                    <p style='font-size: 20px; font-weight: bold; color: #dc3545; margin: 8px 0;'>$total_diy_formatted</p>
                                                    <p style='font-size: 11px; color: #666; margin: 5px 0; line-height: 1.3;'>+ Tu tiempo<br>+ Citas presenciales<br>+ Riesgo de errores<br>+ Estrés del papeleo<br>+ Presentar impuestos hacienda<br>+ Tasas de capitanía</p>
                                                </div>
                                            </td>
                                            <!-- Separador -->
                                            <td style='width: 4%; text-align: center; vertical-align: middle;'>
                                                <span style='color: #016d86; font-size: 18px; font-weight: bold;'>VS</span>
                                            </td>
                                            <!-- Opción gestión completa -->
                                            <td style='width: 48%; vertical-align: top;'>
                                                <div style='background-color: #fff; border: 2px solid #27ae60; padding: 15px; text-align: center; border-radius: 8px; position: relative;'>
                                                    <div style='background-color: #27ae60; color: white; font-size: 10px; padding: 3px 8px; position: absolute; top: -8px; right: 10px; border-radius: 10px;'>RECOMENDADO</div>
                                                    <h5 style='color: #27ae60; margin: 0 0 8px 0; font-size: 14px; font-weight: bold;'>Si lo gestionamos nosotros</h5>
                                                    <p style='font-size: 20px; font-weight: bold; color: #27ae60; margin: 8px 0;'>$total_gestion_nosotros_formatted</p>
                                                    <p style='font-size: 11px; color: #666; margin: 5px 0; line-height: 1.3;'>✓ Sin colas ni esperas<br>✓ Sin errores ni rechazos<br>✓ Gestión 100% online<br>✓ Provisional en menos de 24h</p>
                                                </div>
                                            </td>
                                        </tr>
                                    </table>
                                </div>

                                <!-- Precio completo del servicio -->
                                <h3 class='section-title'>Precio completo de nuestro servicio</h3>
                                <table class='data-table' role='presentation'>
                                    <tr>
                                        <td class='label'>ITP (impuesto):</td>
                                        <td class='value'>$itp_amount_formatted</td>
                                    </tr>
                                    <tr>
                                        <td class='label'>Gestión del ITP:</td>
                                        <td class='value'>0€</td>
                                    </tr>
                                    <tr>
                                        <td class='label'>Cambio de Titularidad:</td>
                                        <td class='value'>$service_fee_formatted</td>
                                    </tr>
                                    <tr>
                                        <td class='label' style='padding-left: 15px; font-size: 12px; color: #666;'>• Tasas capitanía marítima + Gestión:</td>
                                        <td class='value' style='font-size: 12px; color: #666;'>$tasas_gestion_formatted</td>
                                    </tr>
                                    <tr>
                                        <td class='label' style='padding-left: 15px; font-size: 12px; color: #666;'>• IVA:</td>
                                        <td class='value' style='font-size: 12px; color: #666;'>$iva_servicio_formatted</td>
                                    </tr>
                                    <tr style='background: #f0f8fa; font-weight: bold;'>
                                        <td class='label'>TOTAL:</td>
                                        <td class='value'>$total_gestion_nosotros_formatted</td>
                                    </tr>
                                </table>

                                <!-- Pregunta punto de dolor -->
                                <div style='text-align: center; margin: 20px 0 15px 0;'>
                                    <p style='color: #016d86; font-size: 16px; font-weight: bold; margin: 0; line-height: 1.4;'>
                                        <strong>¿Quieres evitar el lío de papeleo, formularios infinitos y citas presenciales que retrasan tu cambio de titularidad?</strong>
                                    </p>
                                </div>

                                <!-- CTA -->
                                <div class='cta-section'>
                                    <a href='https://tramitfy.es/cambio-titularidad-embarcacion/' class='cta-button'>
                                        CONTRATAR TRAMITACIÓN COMPLETA
                                    </a>
                                    <p style='font-size: 12px; color: #666; margin: 10px 0 0 0;'>Nosotros nos encargamos de todo el proceso</p>
                                </div>

                                <!-- Aviso -->
                                <table class='warning-box' role='presentation' width='100%'>
                                    <tr>
                                        <td>
                                            <em>Al trabajar solo online reducimos costes fijos, y eso nos permite ofrecerte la misma gestión al precio más competitivo.</em>
                                        </td>
                                    </tr>
                                </table>

                                <!-- Oferta especial al final -->
                                <table class='discount-box' role='presentation' width='100%' style='margin-top: 25px;'>
                                    <tr>
                                        <td style='text-align: center;'>
                                            <p class='discount-title'>🎁 OFERTA ESPECIAL</p>
                                            <div class='discount-code' style='font-size: 22px; margin: 10px 0;'>NAUTICA5</div>
                                            <p class='discount-text'>5% de descuento en tramitación</p>
                                            <table role='presentation' width='100%' style='margin-top: 15px;'>
                                                <tr>
                                                    <td class='label' style='text-align: left; padding: 5px 8px;'>ITP (impuesto):</td>
                                                    <td class='value' style='text-align: right; padding: 5px 8px;'>$itp_amount_formatted</td>
                                                </tr>
                                                <tr>
                                                    <td class='label' style='text-align: left; padding: 5px 8px;'>Cambio de Titularidad normal:</td>
                                                    <td class='value' style='text-align: right; padding: 5px 8px; text-decoration: line-through; color: #999;'>$service_fee_formatted</td>
                                                </tr>
                                                <tr>
                                                    <td class='label' style='text-align: left; padding: 5px 8px;'>Cambio de Titularidad con NAUTICA5:</td>
                                                    <td class='value' style='text-align: right; padding: 5px 8px; color: #16a085;'>$discounted_service_fee_formatted</td>
                                                </tr>
                                                <tr>
                                                    <td class='label' style='text-align: left; padding: 5px 8px;'><small>Ahorro:</small></td>
                                                    <td class='value' style='text-align: right; padding: 5px 8px; color: #27ae60;'><small>-$discount_amount_formatted</small></td>
                                                </tr>
                                                <tr style='background: #e8f5e8; font-weight: bold; border-top: 2px solid #27ae60;'>
                                                    <td class='label' style='text-align: left; padding: 8px; color: #27ae60;'>TOTAL CON CUPÓN:</td>
                                                    <td class='value' style='text-align: right; padding: 8px; color: #27ae60; font-size: 16px;'>$total_with_discount_formatted</td>
                                                </tr>
                                            </table>
                                            <p style='font-size: 11px; color: #666; margin: 10px 0 0 0;'>
                                                Aplicar cupón al contratar en nuestro formulario
                                            </p>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>

                        <!-- Footer -->
                        <tr>
                            <td class='footer'>
                                <p class='footer-text'>
                                    © 2024 Tramitfy - Especialistas en tramitación náutica
                                </p>
                                <div class='footer-links'>
                                    <a href='https://tramitfy.es'>Web</a> |
                                    <a href='https://tramitfy.es/politica-de-privacidad/'>Privacidad</a> |
                                    <a href='mailto:info@tramitfy.es'>Contacto</a>
                                </div>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>
    ";

    // Headers para email HTML
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: Tramitfy <noreply@tramitfy.es>',
        'Reply-To: info@tramitfy.es'
    );

    // Enviar email al cliente
    $sent = wp_mail($email, $subject, $message, $headers);

    // Calcular desglose detallado como en el formulario
    $tasas_gestion_admin = 114.87;
    $iva_admin = 20.12;
    $discount_ratio = 0.05; // 5% de descuento NAUTICA5
    $discounted_service_admin = $service_fee * (1 - $discount_ratio);
    $total_completo_admin = $itp_amount + $discounted_service_admin + $comision_bancaria;

    // Formatear para mostrar en email admin
    $tasas_gestion_admin_formatted = number_format($tasas_gestion_admin, 2, ',', '.') . ' €';
    $iva_admin_formatted = number_format($iva_admin, 2, ',', '.') . ' €';
    $discounted_service_admin_formatted = number_format($discounted_service_admin, 2, ',', '.') . ' €';
    $total_completo_admin_formatted = number_format($total_completo_admin, 2, ',', '.') . ' €';

    // Enviar notificación al equipo de Tramitfy
    if ($sent) {
        $admin_subject = "Nueva consulta ITP - $vehicle_type_text $manufacturer $model";

        $admin_message = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; }
                .container { max-width: 600px; margin: 20px auto; padding: 20px; background: #f5f5f5; }
                h2 { color: #016d86; border-bottom: 2px solid #016d86; padding-bottom: 10px; }
                .data-table { width: 100%; margin: 15px 0; background: white; padding: 15px; border-radius: 5px; }
                .data-table td { padding: 8px; border-bottom: 1px solid #eee; }
                .label { font-weight: bold; color: #555; width: 50%; }
                .value { color: #333; text-align: right; }
                .highlight { background: #016d86; color: white; padding: 15px; text-align: center; font-size: 18px; font-weight: bold; margin: 20px 0; border-radius: 5px; }
                .pricing-section { background: #e6f5f7; padding: 15px; border-radius: 5px; margin: 20px 0; }
                .discount-info { background: #ffeb3b; padding: 10px; margin: 15px 0; border-radius: 5px; }
                .total-section { background: #27ae60; color: white; padding: 15px; text-align: center; font-size: 20px; font-weight: bold; border-radius: 5px; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <h2>🚤 Nueva consulta de cálculo ITP</h2>

                <p><strong>Email del cliente:</strong> $email</p>
                <p><strong>Fecha y hora:</strong> " . date('d/m/Y H:i:s') . "</p>

                <h3>📋 Datos del vehículo:</h3>
                <table class='data-table'>
                    <tr>
                        <td class='label'>Tipo:</td>
                        <td class='value'>$vehicle_type_text</td>
                    </tr>
                    <tr>
                        <td class='label'>Fabricante:</td>
                        <td class='value'>$manufacturer</td>
                    </tr>
                    <tr>
                        <td class='label'>Modelo:</td>
                        <td class='value'>$model</td>
                    </tr>
                    <tr>
                        <td class='label'>Año de matriculación:</td>
                        <td class='value'>$matriculation_year</td>
                    </tr>
                    <tr>
                        <td class='label'>Precio de compra:</td>
                        <td class='value'>$purchase_price_formatted</td>
                    </tr>
                    <tr>
                        <td class='label'>Comunidad Autónoma:</td>
                        <td class='value'>$region</td>
                    </tr>
                </table>

                <h3>📊 Cálculo del ITP:</h3>
                <table class='data-table'>
                    <tr>
                        <td class='label'>Antigüedad del vehículo:</td>
                        <td class='value'>$vehicle_age años</td>
                    </tr>
                    <tr>
                        <td class='label'>Factor de depreciación:</td>
                        <td class='value'>" . ($no_model_found ? 'Sin aplicar (datos manuales)' : "{$depreciation_rate}%") . "</td>
                    </tr>
                    <tr>
                        <td class='label'>Valor fiscal resultante:</td>
                        <td class='value'>$fiscal_value_formatted</td>
                    </tr>
                    <tr>
                        <td class='label'>Tipo ITP en $region:</td>
                        <td class='value'>" . ($itp_rate * 100) . "%</td>
                    </tr>
                </table>

                <div class='highlight'>
                    💰 ITP A PAGAR: $itp_amount_formatted
                </div>

                <h3>💼 Desglose de nuestro servicio:</h3>
                <div class='pricing-section'>
                    <table class='data-table' style='background: white;'>
                        <tr>
                            <td class='label'>ITP (impuesto):</td>
                            <td class='value'><strong>$itp_amount_formatted</strong></td>
                        </tr>
                        <tr style='background: #f9f9f9;'>
                            <td class='label' colspan='2' style='text-align: center; color: #016d86; font-weight: bold;'>DESGLOSE GESTIÓN TRAMITFY:</td>
                        </tr>
                        <tr>
                            <td class='label'>• Tasas oficiales:</td>
                            <td class='value'>$base_tasas_formatted</td>
                        </tr>
                        <tr>
                            <td class='label'>• Honorarios (con desc. 5%):</td>
                            <td class='value'>$discounted_honorarios_formatted</td>
                        </tr>
                        <tr>
                            <td class='label'>• IVA (21%):</td>
                            <td class='value'>$iva_formatted</td>
                        </tr>
                        <tr style='background: #e6f5f7; font-weight: bold;'>
                            <td class='label'>SUBTOTAL GESTIÓN:</td>
                            <td class='value'>$total_gestion_formatted</td>
                        </tr>
                    </table>
                </div>

                <div class='discount-info'>
                    <p><strong>🎁 Cupón aplicable:</strong> NAUTICA5</p>
                    <p><strong>📉 Descuento:</strong> 5% sobre honorarios (ya aplicado en el cálculo)</p>
                    <p><strong>💰 Ahorro:</strong> " . number_format(($base_honorarios - $discounted_honorarios) + (($base_honorarios - $discounted_honorarios) * 0.21), 2, ',', '.') . " €</p>
                </div>

                <div class='total-section'>
                    🎯 PRECIO TOTAL CON CUPÓN: $total_completo_formatted
                    <br><small style='font-size: 14px; opacity: 0.9;'>(ITP + Gestión completa)</small>
                </div>

                <h3>✅ Servicios incluidos en la gestión:</h3>
                <ul style='background: white; padding: 15px; border-radius: 5px;'>
                    <li>Liquidación y pago del ITP en nombre del cliente</li>
                    <li>Revisión completa de documentación</li>
                    <li>Tramitación del cambio de titularidad</li>
                    <li>Gestión con Capitanía Marítima y Hacienda</li>
                    <li>Envío de documentación oficial a domicilio</li>
                    <li>Asesoramiento personalizado durante todo el proceso</li>
                </ul>

                <p style='text-align: center; margin-top: 30px; padding: 10px; background: white; border-radius: 5px; font-size: 12px; color: #666;'>
                    📧 Email automático generado por la calculadora ITP de Tramitfy<br>
                    🔗 <a href='https://tramitfy.es/cambio-titularidad-embarcacion/'>Enlace al formulario de contratación</a>
                </p>
            </div>
        </body>
        </html>
        ";

        $admin_headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: Tramitfy Calculadora <noreply@tramitfy.es>',
            'Reply-To: ' . $email
        );

        // Enviar a ipmgroup24@gmail.com
        wp_mail('ipmgroup24@gmail.com', $admin_subject, $admin_message, $admin_headers);

        $webhook_data = array(
            'vehicleType' => $vehicle_type,
            'manufacturer' => $manufacturer,
            'model' => $model,
            'purchasePrice' => floatval($purchase_price),
            'region' => $region,
            'email' => $email,
            'itpAmount' => floatval($itp_amount),
            'fiscalValue' => floatval($fiscal_value),
            'noModelFound' => $no_model_found,
            'depreciationRate' => $depreciation_rate
        );

        wp_remote_post('https://46-202-128-35.sslip.io/api/herramientas/itp/webhook', array(
            'timeout' => 5,
            'headers' => array('Content-Type' => 'application/json'),
            'body' => json_encode($webhook_data),
            'blocking' => false
        ));

        wp_send_json_success(['message' => 'Email enviado correctamente']);
    } else {
        wp_send_json_error('Error al enviar el email');
    }
}

add_action('wp_ajax_enviar_email_itp_v2', 'enviar_email_itp_v2');
add_action('wp_ajax_nopriv_enviar_email_itp_v2', 'enviar_email_itp_v2');
?>