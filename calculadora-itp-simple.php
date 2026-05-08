<?php
/**
 * Calculadora ITP Náutica V2 - Diseño de Dos Columnas
 *
 * Shortcode: [calc_itp_v2]
 * Layout: Información en tiempo real (izquierda) + Formulario (derecha)
 */

/**
 * Carga datos desde archivo CSV - IGUAL QUE TBV2
 * NOTA: data.csv tiene header, MOTO.csv NO tiene header
 */
function calc_itp_cargar_datos_csv($tipo = 'barco') {
    $ruta_csv = get_template_directory() . '/' . ($tipo === 'moto' ? 'MOTO.csv' : 'data.csv');
    $data = [];

    // data.csv tiene header, MOTO.csv no
    $tiene_header = ($tipo !== 'moto');

    if (file_exists($ruta_csv) && ($handle = fopen($ruta_csv, 'r')) !== false) {
        $first_line = true;
        while (($row = fgetcsv($handle, 1000, ',')) !== false) {
            // Solo saltar primera línea si tiene header
            if ($first_line && $tiene_header) {
                $first_line = false;
                continue;
            }
            $first_line = false;

            if (count($row) >= 3) {
                $fabricante = trim($row[0]);
                $modelo = trim($row[1]);
                $precio = floatval(str_replace(',', '.', trim($row[2])));

                if (!empty($fabricante) && !empty($modelo)) {
                    $data[$fabricante][] = [
                        'modelo' => $modelo,
                        'precio' => $precio
                    ];
                }
            }
        }
        fclose($handle);
    }

    return $data;
}

function calc_itp_v2_shortcode() {
    // Cargar datos CSV para ambos tipos de vehículo
    $datos_csv_barco = calc_itp_cargar_datos_csv('barco');
    $datos_csv_moto = calc_itp_cargar_datos_csv('moto');

    // Datos esenciales en formato JSON
    $itp_rates = json_encode([
        "Andalucía" => 0.04, "Aragón" => 0.04, "Asturias" => 0.04, "Islas Baleares" => 0.04,
        "Canarias" => 0.055, "Cantabria" => 0.08, "Castilla-La Mancha" => 0.06, "Castilla y León" => 0.05,
        "Cataluña" => 0.05, "Comunidad Valenciana" => 0.08, "Extremadura" => 0.06, "Galicia" => 0.01,
        "Madrid" => 0.04, "Murcia" => 0.04, "Navarra" => 0.04, "País Vasco" => 0.04,
        "La Rioja" => 0.04, "Ceuta" => 0.02, "Melilla" => 0.04
    ]);

    // Tabla oficial BOE 2024 - Columna "A motor y MN" (Motores y Motos Náuticas)
    $depreciation_rates = json_encode([
        ["years" => 1, "rate" => 100],  // Hasta 1 año
        ["years" => 2, "rate" => 85],   // Más de 1, hasta 2
        ["years" => 3, "rate" => 72],   // Más de 2, hasta 3
        ["years" => 4, "rate" => 61],   // Más de 3, hasta 4
        ["years" => 5, "rate" => 52],   // Más de 4, hasta 5
        ["years" => 6, "rate" => 44],   // Más de 5, hasta 6
        ["years" => 7, "rate" => 37],   // Más de 6, hasta 7
        ["years" => 8, "rate" => 32],   // Más de 7, hasta 8
        ["years" => 9, "rate" => 27],   // Más de 8, hasta 9
        ["years" => 10, "rate" => 23],  // Más de 9, hasta 10
        ["years" => 11, "rate" => 19],  // Más de 10, hasta 11
        ["years" => 12, "rate" => 16],  // Más de 11, hasta 12
        ["years" => 13, "rate" => 14],  // Más de 12, hasta 13
        ["years" => 14, "rate" => 12],  // Más de 13, hasta 14
        ["years" => 15, "rate" => 10]   // Más de 14 años
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

        // Datos CSV cargados desde PHP (igual que TBV2)
        const datosCsvBarco = <?php echo json_encode($datos_csv_barco); ?>;
        const datosCsvMoto = <?php echo json_encode($datos_csv_moto); ?>;

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

        // Carga de datos - USANDO DATOS PHP (igual que TBV2)
        function loadManufacturers() {
            const manufacturerSelect = document.getElementById('manufacturer');
            manufacturerSelect.innerHTML = '<option value="">Cargando fabricantes...</option>';
            manufacturerSelect.disabled = true;

            // Seleccionar datos según tipo de vehículo
            const datosCsv = (currentVehicleType === 'moto') ? datosCsvMoto : datosCsvBarco;

            console.log(`Cargando fabricantes para: ${currentVehicleType}`);

            // Obtener fabricantes del objeto PHP
            const manufacturerList = Object.keys(datosCsv).sort();

            // Actualizar el select
            manufacturerSelect.innerHTML = '<option value="">Selecciona un fabricante</option>';
            manufacturerList.forEach(manufacturer => {
                const option = document.createElement('option');
                option.value = manufacturer;
                const modelCount = datosCsv[manufacturer].length;
                option.textContent = `${manufacturer} (${modelCount} modelos)`;
                manufacturerSelect.appendChild(option);
            });

            manufacturerSelect.disabled = false;

            console.log(`${manufacturerList.length} fabricantes cargados`);
        }

        // Carga de modelos - USANDO DATOS PHP CON PRECIOS (igual que TBV2)
        function loadModels() {
            const manufacturer = document.getElementById('manufacturer').value;
            const modelSelect = document.getElementById('model');

            if (!manufacturer) {
                modelSelect.innerHTML = '<option value="">Primero selecciona fabricante</option>';
                modelSelect.disabled = true;
                return;
            }

            // Seleccionar datos según tipo de vehículo
            const datosCsv = (currentVehicleType === 'moto') ? datosCsvMoto : datosCsvBarco;

            if (!datosCsv[manufacturer]) {
                modelSelect.innerHTML = '<option value="">Error: fabricante no encontrado</option>';
                return;
            }

            modelSelect.innerHTML = '<option value="">Cargando modelos...</option>';
            modelSelect.disabled = true;

            // Obtener modelos del fabricante
            const models = datosCsv[manufacturer];

            // Ordenar modelos alfabéticamente
            models.sort((a, b) => a.modelo.localeCompare(b.modelo));

            // Actualizar el select CON PRECIO EN DATASET (igual que TBV2)
            modelSelect.innerHTML = '<option value="">Selecciona un modelo</option>';
            models.forEach(modelData => {
                const option = document.createElement('option');
                option.value = modelData.modelo;
                option.textContent = modelData.modelo;
                // ✅ GUARDAR PRECIO EN DATASET - CLAVE PARA EL CÁLCULO
                option.dataset.price = modelData.precio;
                modelSelect.appendChild(option);
            });

            modelSelect.disabled = false;

            console.log(`${models.length} modelos cargados para ${manufacturer} (con precios)`);
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

        // Cálculo final - CON PRECIO DEL CSV (igual que TBV2)
        function calculateITP() {
            if (validateCurrentStep()) {
                const noModelFound = document.getElementById('no-model-found').checked;
                const modelSelect = document.getElementById('model');
                const purchasePrice = parseFloat(document.getElementById('purchase-price').value);
                const matriculationDate = document.getElementById('matriculation-date').value;

                // ✅ OBTENER PRECIO DEL MODELO DEL CSV (igual que TBV2)
                let modelPrice = 0;
                if (!noModelFound && modelSelect && modelSelect.value) {
                    const selectedOption = modelSelect.options[modelSelect.selectedIndex];
                    modelPrice = parseFloat(selectedOption.dataset.price || 0);
                    console.log('💰 Precio del modelo CSV:', modelPrice.toFixed(2), '€');
                }

                // ✅ CALCULAR DEPRECIACIÓN (igual que TBV2)
                let depreciationRate = 100;
                if (!noModelFound && matriculationDate && modelPrice > 0) {
                    const matriculationDateObj = new Date(matriculationDate);
                    const today = new Date();
                    let yearsDifference = today.getFullYear() - matriculationDateObj.getFullYear();
                    const monthsDifference = today.getMonth() - matriculationDateObj.getMonth();

                    if (monthsDifference < 0 || (monthsDifference === 0 && today.getDate() < matriculationDateObj.getDate())) {
                        yearsDifference--;
                    }

                    // Buscar factor de depreciación en tabla BOE
                    for (const rate of depreciationRates) {
                        if (yearsDifference < rate.years) {
                            depreciationRate = rate.rate;
                            break;
                        }
                        depreciationRate = rate.rate; // Último valor para >14 años
                    }
                    console.log('📉 Depreciación aplicada:', depreciationRate + '%', '(', yearsDifference, 'años)');
                }

                // ✅ CALCULAR VALOR FISCAL (precio CSV con depreciación)
                const fiscalValue = modelPrice * (depreciationRate / 100);
                console.log('📊 Valor fiscal (precio CSV × depreciación):', fiscalValue.toFixed(2), '€');

                // ✅ BASE IMPONIBLE = MAX(precio compra, valor fiscal) - igual que TBV2
                const baseImponible = Math.max(purchasePrice, fiscalValue);
                console.log('📋 Base imponible (MAX):', baseImponible.toFixed(2), '€');

                // Recopilar datos para el cálculo
                calculationData = {
                    vehicleType: currentVehicleType,
                    manufacturer: document.getElementById('manufacturer').value,
                    model: document.getElementById('model').value,
                    purchasePrice: purchasePrice,
                    modelPrice: modelPrice, // ✅ NUEVO: Precio del CSV
                    fiscalValue: fiscalValue, // ✅ NUEVO: Valor fiscal calculado
                    depreciationRate: depreciationRate, // ✅ NUEVO: Tasa de depreciación
                    baseImponible: baseImponible, // ✅ NUEVO: Base imponible calculada
                    matriculationDate: matriculationDate,
                    region: document.getElementById('region').value,
                    noModelFound: noModelFound
                };

                console.log('✅ Datos de cálculo completos:', calculationData);

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

            // Preparar datos para envio - INCLUYE DATOS DEL CSV (igual que TBV2)
            const formData = new FormData();
            formData.append('action', 'enviar_email_itp_v2');
            formData.append('email', email);
            formData.append('vehicleType', calculationData.vehicleType);
            formData.append('manufacturer', calculationData.manufacturer);
            formData.append('model', calculationData.model);
            formData.append('purchasePrice', calculationData.purchasePrice);
            formData.append('modelPrice', calculationData.modelPrice || 0); // ✅ NUEVO: Precio CSV
            formData.append('fiscalValue', calculationData.fiscalValue || 0); // ✅ NUEVO: Valor fiscal
            formData.append('depreciationRate', calculationData.depreciationRate || 100); // ✅ NUEVO: Depreciación
            formData.append('baseImponible', calculationData.baseImponible || calculationData.purchasePrice); // ✅ NUEVO: Base imponible
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

    // ✅ NUEVOS DATOS DEL CSV (calculados en frontend igual que TBV2)
    $model_price = floatval($_POST['modelPrice'] ?? 0);
    $fiscal_value_from_js = floatval($_POST['fiscalValue'] ?? 0);
    $depreciation_rate_from_js = floatval($_POST['depreciationRate'] ?? 100);
    $base_imponible_from_js = floatval($_POST['baseImponible'] ?? $purchase_price);

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

    // Calcular antigüedad
    $matriculation_year = date('Y', strtotime($matriculation_date));
    $current_year = date('Y');
    $vehicle_age = $current_year - $matriculation_year;

    // ✅ USAR DATOS DEL FRONTEND SI ESTÁN DISPONIBLES (igual que TBV2)
    if ($model_price > 0 && !$no_model_found) {
        // Usar cálculos del frontend (basados en precio CSV)
        $depreciation_rate = $depreciation_rate_from_js;
        $fiscal_value = $fiscal_value_from_js;
        $base_imponible = $base_imponible_from_js;
        error_log("ITP CALC: Usando datos CSV - modelPrice: {$model_price}, fiscalValue: {$fiscal_value}, baseImponible: {$base_imponible}");
    } else {
        // Fallback: modo manual o sin precio CSV
        $depreciation_rate = 100;
        $fiscal_value = $purchase_price;
        $base_imponible = $purchase_price;
        error_log("ITP CALC: Modo manual - usando purchasePrice directamente: {$purchase_price}");
    }

    // Calcular ITP - BASE IMPONIBLE YA CALCULADA CORRECTAMENTE
    $itp_rate = isset($itp_rates[$region]) ? $itp_rates[$region] : 0.04;
    $itp_amount = $base_imponible * $itp_rate;

    error_log("ITP CALC FINAL: baseImponible={$base_imponible}, itpRate={$itp_rate}, itpAmount={$itp_amount}");

    // Formatear moneda
    $purchase_price_formatted = number_format($purchase_price, 0, ',', '.') . ' €';
    $model_price_formatted = number_format($model_price, 0, ',', '.') . ' €'; // ✅ NUEVO: Precio CSV
    $fiscal_value_formatted = number_format($fiscal_value, 0, ',', '.') . ' €';
    $base_imponible_formatted = number_format($base_imponible, 0, ',', '.') . ' €';
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

    $total_with_discount_clean = $itp_amount + $discounted_service_fee;
    $total_with_discount_clean_formatted = number_format($total_with_discount_clean, 2, ',', '.') . ' €';

    $message = "
    <!DOCTYPE html>
    <html lang='es'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <meta http-equiv='X-UA-Compatible' content='IE=edge'>
    </head>
    <body style='margin:0;padding:0;background:#f0f4f8;font-family:Arial,Helvetica,sans-serif;-webkit-text-size-adjust:100%;'>
    <table role='presentation' width='100%' cellpadding='0' cellspacing='0' style='background:#f0f4f8;'>
    <tr><td align='center' style='padding:24px 12px;'>
    <table role='presentation' width='560' cellpadding='0' cellspacing='0' style='max-width:560px;width:100%;background:#ffffff;border:1px solid #d9e2ec;'>

    <!-- CABECERA -->
    <tr>
      <td style='background:#016d86;padding:28px 32px;text-align:center;'>
        <div style='font-size:22px;font-weight:bold;color:#ffffff;letter-spacing:2px;'>TRAMITFY</div>
        <div style='font-size:12px;color:#b3dfe8;margin-top:4px;letter-spacing:1px;'>ESPECIALISTAS EN TRAMITACIÓN NÁUTICA</div>
      </td>
    </tr>

    <!-- INTRO -->
    <tr>
      <td style='padding:28px 32px 0 32px;'>
        <p style='margin:0 0 4px 0;font-size:13px;color:#6b7c93;'>Tu cálculo de ITP para la " . strtolower($vehicle_type_text) . ":</p>
        <p style='margin:0;font-size:19px;font-weight:bold;color:#1a2e44;'>$manufacturer $model &middot; $matriculation_year</p>
      </td>
    </tr>

    <!-- ITP RESULTADO -->
    <tr>
      <td style='padding:20px 32px;'>
        <table role='presentation' width='100%' cellpadding='0' cellspacing='0'>
          <tr>
            <td style='background:#016d86;padding:24px;text-align:center;'>
              <div style='font-size:11px;color:#b3dfe8;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;'>ITP estimado a pagar</div>
              <div style='font-size:40px;font-weight:bold;color:#ffffff;line-height:1;'>$itp_amount_formatted</div>
              <div style='font-size:12px;color:#b3dfe8;margin-top:8px;'>$region &middot; tipo " . ($itp_rate * 100) . "%</div>
            </td>
          </tr>
        </table>
      </td>
    </tr>

    <!-- VEHÍCULO + CÁLCULO -->
    <tr>
      <td style='padding:0 32px 24px 32px;'>
        <table role='presentation' width='100%' cellpadding='0' cellspacing='0'>
          <tr>
            <!-- Columna izquierda -->
            <td width='50%' style='vertical-align:top;padding-right:12px;'>
              <div style='font-size:11px;font-weight:bold;color:#016d86;text-transform:uppercase;letter-spacing:0.5px;border-bottom:2px solid #016d86;padding-bottom:6px;margin-bottom:10px;'>Tu $vehicle_type_text</div>
              <table role='presentation' width='100%' cellpadding='0' cellspacing='0' style='font-size:12px;'>
                <tr><td style='color:#6b7c93;padding:4px 0;'>Fabricante</td><td style='color:#1a2e44;font-weight:bold;text-align:right;'>$manufacturer</td></tr>
                <tr><td style='color:#6b7c93;padding:4px 0;border-top:1px solid #eef2f7;'>Modelo</td><td style='color:#1a2e44;font-weight:bold;text-align:right;border-top:1px solid #eef2f7;'>$model</td></tr>
                <tr><td style='color:#6b7c93;padding:4px 0;border-top:1px solid #eef2f7;'>Año</td><td style='color:#1a2e44;font-weight:bold;text-align:right;border-top:1px solid #eef2f7;'>$matriculation_year</td></tr>
                <tr><td style='color:#6b7c93;padding:4px 0;border-top:1px solid #eef2f7;'>Precio compra</td><td style='color:#1a2e44;font-weight:bold;text-align:right;border-top:1px solid #eef2f7;'>$purchase_price_formatted</td></tr>
                <tr><td style='color:#6b7c93;padding:4px 0;border-top:1px solid #eef2f7;'>Comunidad</td><td style='color:#1a2e44;font-weight:bold;text-align:right;border-top:1px solid #eef2f7;'>$region</td></tr>
              </table>
            </td>
            <!-- Columna derecha -->
            <td width='50%' style='vertical-align:top;padding-left:12px;'>
              <div style='font-size:11px;font-weight:bold;color:#016d86;text-transform:uppercase;letter-spacing:0.5px;border-bottom:2px solid #016d86;padding-bottom:6px;margin-bottom:10px;'>Cómo se calcula</div>
              <table role='presentation' width='100%' cellpadding='0' cellspacing='0' style='font-size:12px;'>
                " . ($model_price > 0 ? "
                <tr><td style='color:#27ae60;padding:4px 0;'>Valor BOE (CSV)</td><td style='color:#27ae60;font-weight:bold;text-align:right;'>$model_price_formatted</td></tr>
                " : "") . "
                <tr><td style='color:#6b7c93;padding:4px 0;border-top:1px solid #eef2f7;'>Antigüedad</td><td style='color:#1a2e44;font-weight:bold;text-align:right;border-top:1px solid #eef2f7;'>$vehicle_age años</td></tr>
                <tr><td style='color:#6b7c93;padding:4px 0;border-top:1px solid #eef2f7;'>Depreciación BOE</td><td style='color:#1a2e44;font-weight:bold;text-align:right;border-top:1px solid #eef2f7;'>" . ($no_model_found ? 'Sin aplicar' : "{$depreciation_rate}%") . "</td></tr>
                <tr><td style='color:#6b7c93;padding:4px 0;border-top:1px solid #eef2f7;'>Valor fiscal</td><td style='color:#1a2e44;font-weight:bold;text-align:right;border-top:1px solid #eef2f7;'>$fiscal_value_formatted</td></tr>
                <tr><td colspan='2' style='font-size:10px;color:#9aa8b8;padding:2px 0;font-style:italic;border-top:1px solid #eef2f7;'>" . ($model_price > 0 ? 'Valor modelo × depreciación' : 'Precio compra sin depreciación') . "</td></tr>
                <tr><td style='color:#016d86;font-weight:bold;padding:6px 0;border-top:2px solid #e2ecf0;'>Base imponible</td><td style='color:#016d86;font-weight:bold;font-size:14px;text-align:right;border-top:2px solid #e2ecf0;'>$base_imponible_formatted</td></tr>
                <tr><td colspan='2' style='font-size:10px;color:#9aa8b8;padding:2px 0;font-style:italic;'>Se usa el mayor entre compra y valor fiscal</td></tr>
                <tr><td style='color:#6b7c93;padding:4px 0;border-top:1px solid #eef2f7;'>Tipo ITP</td><td style='color:#1a2e44;font-weight:bold;text-align:right;border-top:1px solid #eef2f7;'>" . ($itp_rate * 100) . "%</td></tr>
              </table>
            </td>
          </tr>
        </table>
      </td>
    </tr>

    <!-- DESGLOSE PRECIO -->
    <tr>
      <td style='padding:0 32px 24px 32px;'>
        <div style='font-size:13px;font-weight:bold;color:#1a2e44;margin-bottom:12px;'>Precio completo de nuestro servicio:</div>
        <table role='presentation' width='100%' cellpadding='0' cellspacing='0' style='font-size:13px;border:1px solid #e2ecf0;'>
          <tr style='background:#f7fafc;'><td style='padding:9px 12px;color:#6b7c93;'>ITP (impuesto a Hacienda):</td><td style='padding:9px 12px;color:#1a2e44;font-weight:bold;text-align:right;'>$itp_amount_formatted</td></tr>
          <tr><td style='padding:9px 12px;color:#6b7c93;border-top:1px solid #e2ecf0;'>Gestión del ITP:</td><td style='padding:9px 12px;color:#27ae60;font-weight:bold;text-align:right;border-top:1px solid #e2ecf0;'>0,00 &#8364;</td></tr>
          <tr style='background:#f7fafc;'><td style='padding:9px 12px;color:#6b7c93;border-top:1px solid #e2ecf0;'>Cambio de titularidad (tasas + gestión + IVA):</td><td style='padding:9px 12px;color:#1a2e44;font-weight:bold;text-align:right;border-top:1px solid #e2ecf0;'>$service_fee_formatted</td></tr>
          <tr style='background:#016d86;'><td style='padding:11px 12px;color:#ffffff;font-weight:bold;font-size:14px;'>TOTAL</td><td style='padding:11px 12px;color:#ffffff;font-weight:bold;font-size:18px;text-align:right;'>$total_gestion_nosotros_formatted</td></tr>
        </table>
      </td>
    </tr>

    <!-- CTA -->
    <tr>
      <td style='padding:8px 32px 32px 32px;text-align:center;'>
        <p style='font-size:15px;font-weight:bold;color:#1a2e44;margin:0 0 16px 0;'>¿Quieres que nos encarguemos de todo el proceso?</p>
        <table role='presentation' cellpadding='0' cellspacing='0' style='margin:0 auto;'>
          <tr><td style='background:#016d86;padding:14px 32px;'>
            <a href='https://tramitfy.es/cambio-titularidad-embarcacion/' style='color:#ffffff;font-weight:bold;font-size:14px;text-decoration:none;letter-spacing:0.5px;display:block;'>CONTRATAR TRAMITACIÓN COMPLETA</a>
          </td></tr>
        </table>
        <p style='font-size:12px;color:#9aa8b8;margin:10px 0 0 0;'>Sin colas &middot; 100% online &middot; Provisional en 24h</p>
      </td>
    </tr>

    <!-- SEPARADOR -->
    <tr><td style='padding:0 32px;'><div style='height:1px;background:#e2ecf0;'></div></td></tr>

    <!-- CUPÓN -->
    <tr>
      <td style='padding:24px 32px;'>
        <table role='presentation' width='100%' cellpadding='0' cellspacing='0' style='border:2px dashed #016d86;background:#f0fafb;'>
          <tr><td style='padding:20px;text-align:center;'>
            <div style='font-size:11px;font-weight:bold;color:#016d86;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;'>Oferta especial para ti</div>
            <table role='presentation' cellpadding='0' cellspacing='0' style='margin:0 auto 12px auto;border:2px dashed #016d86;background:#ffffff;'>
              <tr><td style='padding:8px 24px;font-size:24px;font-weight:bold;color:#016d86;letter-spacing:3px;'>NAUTICA5</td></tr>
            </table>
            <div style='font-size:13px;color:#4a5568;margin-bottom:14px;'>5% de descuento en la tramitación</div>
            <table role='presentation' width='100%' cellpadding='0' cellspacing='0' style='max-width:280px;margin:0 auto;font-size:12px;'>
              <tr><td style='color:#6b7c93;padding:4px 0;'>ITP (impuesto):</td><td style='color:#1a2e44;font-weight:bold;text-align:right;padding:4px 0;'>$itp_amount_formatted</td></tr>
              <tr><td style='color:#6b7c93;padding:4px 0;border-top:1px solid #e2ecf0;'>Cambio titularidad normal:</td><td style='color:#999;text-decoration:line-through;text-align:right;padding:4px 0;border-top:1px solid #e2ecf0;'>$service_fee_formatted</td></tr>
              <tr><td style='color:#6b7c93;padding:4px 0;border-top:1px solid #e2ecf0;'>Con NAUTICA5:</td><td style='color:#27ae60;font-weight:bold;text-align:right;padding:4px 0;border-top:1px solid #e2ecf0;'>$discounted_service_fee_formatted</td></tr>
              <tr><td style='color:#27ae60;font-size:11px;padding:4px 0;border-top:1px solid #e2ecf0;'>Ahorro:</td><td style='color:#27ae60;font-weight:bold;font-size:11px;text-align:right;padding:4px 0;border-top:1px solid #e2ecf0;'>-$discount_amount_formatted</td></tr>
              <tr style='background:#016d86;'><td style='color:#ffffff;font-weight:bold;padding:8px 4px;font-size:13px;'>TOTAL CON CUPÓN:</td><td style='color:#ffffff;font-weight:bold;font-size:16px;text-align:right;padding:8px 4px;'>$total_with_discount_clean_formatted</td></tr>
            </table>
            <div style='font-size:11px;color:#9aa8b8;margin-top:10px;'>Introduce el código al contratar en el formulario</div>
          </td></tr>
        </table>
      </td>
    </tr>

    <!-- AVISO LEGAL -->
    <tr>
      <td style='padding:0 32px 24px 32px;'>
        <table role='presentation' width='100%' cellpadding='0' cellspacing='0' style='background:#fffbf0;border:1px solid #fcd34d;'>
          <tr><td style='padding:12px 16px;font-size:11px;color:#92400e;line-height:1.5;'>
            <strong>Nota:</strong> Este cálculo es orientativo basado en la tabla BOE y el precio declarado. El importe definitivo lo determina Hacienda. Si la base imponible fiscal supera el precio de compra, se aplicará la primera.
          </td></tr>
        </table>
      </td>
    </tr>

    <!-- FOOTER -->
    <tr>
      <td style='background:#1a2e44;padding:20px 32px;text-align:center;'>
        <div style='font-size:13px;font-weight:bold;color:#ffffff;letter-spacing:1px;margin-bottom:4px;'>TRAMITFY</div>
        <div style='font-size:11px;color:#6b8aaa;margin-bottom:12px;'>Especialistas en tramitación náutica &middot; España</div>
        <div style='font-size:11px;'>
          <a href='https://tramitfy.es' style='color:#b3dfe8;text-decoration:none;margin:0 8px;'>Web</a>
          <span style='color:#3d5a7a;'>|</span>
          <a href='https://tramitfy.es/politica-de-privacidad/' style='color:#b3dfe8;text-decoration:none;margin:0 8px;'>Privacidad</a>
          <span style='color:#3d5a7a;'>|</span>
          <a href='mailto:info@tramitfy.es' style='color:#b3dfe8;text-decoration:none;margin:0 8px;'>info@tramitfy.es</a>
        </div>
        <div style='font-size:10px;color:#3d5a7a;margin-top:10px;'>&#169; 2025 Tramitfy S.L. &middot; B55388557</div>
      </td>
    </tr>

    </table>
    </td></tr>
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
                    <tr style='background-color: #f0f9ff;'>
                        <td class='label' style='font-weight: bold; color: #016d86;'>Base imponible ITP:</td>
                        <td class='value' style='font-weight: bold; color: #016d86;'>$base_imponible_formatted</td>
                    </tr>
                    <tr>
                        <td colspan='2' style='font-size: 11px; font-style: italic; color: #666666; padding: 4px 0;'>(Se usa el mayor entre precio de compra y valor fiscal)</td>
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
            'modelPrice' => floatval($model_price), // ✅ NUEVO: Precio del CSV
            'region' => $region,
            'email' => $email,
            'itpAmount' => floatval($itp_amount),
            'fiscalValue' => floatval($fiscal_value),
            'baseImponible' => floatval($base_imponible), // ✅ NUEVO: Base imponible
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