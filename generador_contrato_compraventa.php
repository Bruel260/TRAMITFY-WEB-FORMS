<?php
/**
 * Generador Automático de Contratos de Compra-Venta Náutica
 *
 * Shortcode: [generador_contrato]
 * Layout: Vista previa del contrato (izquierda) + Formulario de datos (derecha)
 */

function generador_contrato_shortcode() {
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
            --success: #28a745;
            --dark: #2c3e50;
            --light: #f8f9fa;
            --border: #e0e6ed;
            --shadow: 0 4px 20px rgba(0,0,0,0.08);
            --radius: 12px;
        }

        .contrato-generator {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            min-height: 600px;
        }

        .main-title {
            text-align: center;
            color: var(--primary);
            font-size: 32px;
            font-weight: 700;
            margin: 0 0 20px 0;
            padding: 0;
            line-height: 1.2;
        }

        .contrato-layout {
            display: grid;
            grid-template-columns: 1.3fr 2.2fr;
            height: 75vh;
            min-height: 600px;
        }

        /* COLUMNA IZQUIERDA - VISTA PREVIA DEL CONTRATO */
        .contrato-preview-panel {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 20px;
            position: relative;
            overflow-y: auto;
            height: 100%;
        }

        .contrato-preview-panel::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 200px;
            height: 200px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
        }

        .contrato-document {
            background: white;
            color: var(--dark);
            border-radius: var(--radius);
            padding: 20px;
            margin: 10px 0;
            position: relative;
            z-index: 2;
            font-size: 13px;
            line-height: 1.4;
        }

        .contrato-header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid var(--primary);
            padding-bottom: 15px;
        }

        .contrato-title {
            font-size: 16px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 8px;
        }

        .contrato-section {
            margin-bottom: 15px;
        }

        .contrato-section h4 {
            color: var(--primary);
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 10px;
            border-left: 4px solid var(--secondary);
            padding-left: 10px;
        }

        .contrato-data {
            background: var(--light);
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 10px;
        }

        .contrato-data p {
            margin: 4px 0;
            font-size: 12px;
        }

        /* Secciones colapsables */
        .collapsible h4 {
            cursor: pointer;
            user-select: none;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
        }

        .collapsible h4:hover {
            background: rgba(0,0,0,0.05);
            padding: 5px 10px;
            border-radius: 4px;
            margin: -5px -10px;
        }

        .toggle-icon {
            font-size: 12px;
            transition: transform 0.3s ease;
            color: var(--secondary);
            font-weight: bold;
        }

        .collapsed {
            display: none !important;
        }

        .contrato-data {
            transition: all 0.3s ease;
        }

        .data-placeholder {
            color: #999;
            font-style: italic;
            background: #f9f9f9;
            padding: 2px 6px;
            border-radius: 4px;
            border: 1px dashed #ccc;
        }

        .clausulas-adicionales {
            background: #fff5f0;
            border: 2px solid var(--warning);
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }

        /* COLUMNA DERECHA - FORMULARIO */
        .contrato-form-panel {
            padding: 15px;
            background: #fff;
            overflow-y: auto;
            height: 100%;
        }

        .contrato-form-header {
            text-align: center;
            margin-bottom: 20px;
        }

        .contrato-form-header h3 {
            color: var(--dark);
            font-size: 24px;
            font-weight: 700;
            margin: 0 0 10px 0;
        }

        .form-steps {
            margin-bottom: 30px;
        }

        .form-step {
            display: none;
            animation: slideIn 0.3s ease-out;
        }

        .form-step.active {
            display: block;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateX(20px); }
            to { opacity: 1; transform: translateX(0); }
        }

        .step-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }

        .step-number {
            background: var(--primary);
            color: white;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 14px;
            font-weight: 700;
        }

        .vehicle-selector {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 20px;
        }

        .vehicle-option {
            border: 2px solid var(--border);
            border-radius: var(--radius);
            padding: 12px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }

        .vehicle-option:hover {
            border-color: var(--primary);
            box-shadow: 0 4px 12px rgba(0,102,204,0.15);
        }

        .vehicle-option.selected {
            border-color: var(--primary);
            background: var(--primary-light);
        }

        .vehicle-option input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .vehicle-option h4 {
            margin: 0 0 8px 0;
            font-size: 18px;
            font-weight: 600;
            color: var(--dark);
        }

        .form-group {
            margin-bottom: 12px;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: var(--dark);
            font-size: 13px;
        }

        .form-control {
            width: 100%;
            padding: 8px 10px;
            border: 2px solid var(--border);
            border-radius: 6px;
            font-size: 13px;
            transition: all 0.3s ease;
            background: white;
            box-sizing: border-box;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(0,102,204,0.1);
        }

        .form-control.error {
            border-color: var(--danger);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .upload-area {
            border: 2px dashed var(--border);
            border-radius: 6px;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 8px;
        }

        .upload-area:hover {
            border-color: var(--primary);
            background: var(--primary-light);
        }

        .upload-area.active {
            border-color: var(--success);
            background: #e8f5e8;
        }

        .clausulas-section {
            background: var(--light);
            border-radius: 6px;
            padding: 12px;
            margin-bottom: 12px;
            max-height: 250px;
            overflow-y: auto;
        }

        .clausula-option {
            display: flex;
            align-items: flex-start;
            margin-bottom: 10px;
            padding: 8px;
            border-radius: 4px;
            transition: background 0.3s ease;
        }

        .clausula-option small {
            font-size: 11px;
            line-height: 1.3;
        }

        .clausula-option:hover {
            background: white;
        }

        .clausula-option input[type="checkbox"] {
            margin-right: 12px;
            margin-top: 2px;
        }

        .clausula-option label {
            margin: 0;
            cursor: pointer;
            font-weight: normal;
        }

        .btn-group {
            display: flex;
            gap: 12px;
            margin-top: 20px;
        }

        .btn {
            flex: 1;
            padding: 12px 20px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: var(--light);
            color: var(--dark);
            border: 2px solid var(--border);
        }

        .btn-secondary:hover {
            background: var(--border);
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-success:hover {
            background: #218838;
        }

        .progress-dots {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-bottom: 20px;
        }

        .progress-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--border);
            transition: all 0.3s ease;
        }

        .progress-dot.active {
            background: var(--primary);
        }

        .progress-dot.completed {
            background: var(--success);
        }

        .error-message {
            color: var(--danger);
            font-size: 12px;
            margin-top: 5px;
            display: none;
        }

        .form-control.error + .error-message {
            display: block;
        }

        /* RESPONSIVE */
        @media (max-width: 1024px) {
            .contrato-layout {
                grid-template-columns: 1fr;
            }

            .contrato-preview-panel {
                max-height: 400px;
            }

            .contrato-form-panel {
                max-height: none;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .vehicle-selector {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <h1 class="main-title">🚤 Generador Automático de Contratos de Compra-Venta</h1>

    <div class="contrato-generator">
        <div class="contrato-layout">
            <!-- COLUMNA IZQUIERDA - VISTA PREVIA DEL CONTRATO -->
            <div class="contrato-preview-panel">
                <div class="contrato-document" id="contrato-preview">
                    <div class="contrato-header">
                        <h2 class="contrato-title">CONTRATO DE COMPRA-VENTA DE EMBARCACIÓN</h2>
                        <p style="margin: 0; font-size: 12px; color: #666;">Documento generado automáticamente</p>
                    </div>

                    <!-- Secciones colapsables -->
                    <div class="contrato-section collapsible" onclick="toggleSection('embarcacion')">
                        <h4>🚤 DATOS DE LA EMBARCACIÓN <span class="toggle-icon" id="toggle-embarcacion">▼</span></h4>
                        <div class="contrato-data" id="section-embarcacion">
                            <p><strong>Tipo de embarcación:</strong> <span id="prev-tipo-vehiculo" class="data-placeholder">[Seleccionar tipo]</span></p>
                            <p><strong>Marca:</strong> <span id="prev-marca" class="data-placeholder">[Introducir marca]</span></p>
                            <p><strong>Modelo:</strong> <span id="prev-modelo" class="data-placeholder">[Introducir modelo]</span></p>
                            <p><strong>Año de fabricación:</strong> <span id="prev-ano" class="data-placeholder">[Introducir año]</span></p>
                            <p><strong>Número de bastidor/casco:</strong> <span id="prev-bastidor" class="data-placeholder">[Introducir número]</span></p>
                            <p><strong>Matrícula/Folio:</strong> <span id="prev-matricula" class="data-placeholder">[Introducir matrícula]</span></p>
                            <p><strong>Precio de venta:</strong> <span id="prev-precio" class="data-placeholder">[Introducir precio]</span></p>
                            <div id="motor-info" style="display: none;">
                                <p><strong>Datos del motor:</strong> <span id="prev-motor" class="data-placeholder">[Datos del motor]</span></p>
                            </div>
                        </div>
                    </div>

                    <div class="contrato-section collapsible" onclick="toggleSection('vendedor')">
                        <h4>👤 PARTE VENDEDORA <span class="toggle-icon" id="toggle-vendedor">▶</span></h4>
                        <div class="contrato-data collapsed" id="section-vendedor">
                            <p><strong>Nombre completo:</strong> <span id="prev-vendedor-nombre" class="data-placeholder">[Nombre del vendedor]</span></p>
                            <p><strong>DNI/NIE:</strong> <span id="prev-vendedor-dni" class="data-placeholder">[DNI/NIE]</span></p>
                            <p><strong>Dirección:</strong> <span id="prev-vendedor-direccion" class="data-placeholder">[Dirección completa]</span></p>
                            <p><strong>Teléfono:</strong> <span id="prev-vendedor-telefono" class="data-placeholder">[Teléfono]</span></p>
                            <p><strong>Email:</strong> <span id="prev-vendedor-email" class="data-placeholder">[Email]</span></p>
                        </div>
                    </div>

                    <div class="contrato-section collapsible" onclick="toggleSection('comprador')">
                        <h4>👤 PARTE COMPRADORA <span class="toggle-icon" id="toggle-comprador">▶</span></h4>
                        <div class="contrato-data collapsed" id="section-comprador">
                            <p><strong>Nombre completo:</strong> <span id="prev-comprador-nombre" class="data-placeholder">[Nombre del comprador]</span></p>
                            <p><strong>DNI/NIE:</strong> <span id="prev-comprador-dni" class="data-placeholder">[DNI/NIE]</span></p>
                            <p><strong>Dirección:</strong> <span id="prev-comprador-direccion" class="data-placeholder">[Dirección completa]</span></p>
                            <p><strong>Teléfono:</strong> <span id="prev-comprador-telefono" class="data-placeholder">[Teléfono]</span></p>
                            <p><strong>Email:</strong> <span id="prev-comprador-email" class="data-placeholder">[Email]</span></p>
                        </div>
                    </div>

                    <div class="contrato-section collapsible" onclick="toggleSection('condiciones')">
                        <h4>📋 CONDICIONES DE LA VENTA <span class="toggle-icon" id="toggle-condiciones">▶</span></h4>
                        <div class="contrato-data collapsed" id="section-condiciones">
                            <p>Ambas partes acuerdan las siguientes condiciones para la transmisión de la propiedad de la embarcación descrita:</p>
                            <p><strong>Precio total acordado:</strong> <span id="prev-precio-texto" class="data-placeholder">[Precio en texto]</span></p>
                            <p><strong>Forma de pago:</strong> <span id="prev-forma-pago" class="data-placeholder">[Seleccionar forma de pago]</span></p>
                            <p><strong>Lugar de entrega:</strong> <span id="prev-lugar-entrega" class="data-placeholder">[Introducir lugar de entrega]</span></p>
                        </div>
                    </div>

                    <div id="clausulas-adicionales-preview" class="contrato-section collapsible" style="display: none;" onclick="toggleSection('clausulas')">
                        <h4>⚖️ CLÁUSULAS ADICIONALES <span class="toggle-icon" id="toggle-clausulas">▶</span></h4>
                        <div class="contrato-data collapsed" id="clausulas-list"></div>
                    </div>

                    <div class="contrato-section" style="margin-top: 20px; text-align: center;">
                        <p style="font-size: 12px; color: #666; margin-bottom: 15px;">
                            El contrato se generará al completar todos los pasos del formulario
                        </p>

                        <p style="font-size: 10px; color: #999; margin: 10px 0 0 0; font-style: italic;">
                            * Herramienta de apoyo. Se recomienda revisión legal profesional.
                        </p>
                    </div>
                </div>
            </div>

            <!-- COLUMNA DERECHA - FORMULARIO -->
            <div class="contrato-form-panel">

                <div class="progress-dots">
                    <div class="progress-dot active"></div>
                    <div class="progress-dot"></div>
                    <div class="progress-dot"></div>
                    <div class="progress-dot"></div>
                    <div class="progress-dot"></div>
                </div>

                <form id="contrato-form">
                    <!-- PASO 1: TIPO DE VEHÍCULO Y DATOS -->
                    <div class="form-step active" id="step-1">
                        <div class="step-title">
                            <span class="step-number">1</span>
                            <span>Datos de la embarcación</span>
                        </div>

                        <div class="vehicle-selector">
                            <div class="vehicle-option selected" data-vehicle="barco">
                                <input type="radio" name="tipoVehiculo" value="barco" checked>
                                <h4>🚤 Embarcación</h4>
                                <p>Veleros, motoras, etc.</p>
                            </div>
                            <div class="vehicle-option" data-vehicle="moto">
                                <input type="radio" name="tipoVehiculo" value="moto">
                                <h4>🏊 Moto Acuática</h4>
                                <p>Jet ski, motos de agua</p>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="marca">Marca *</label>
                                <input type="text" class="form-control" id="marca" name="marca" placeholder="Ej: Beneteau">
                                <div class="error-message">Por favor introduce la marca</div>
                            </div>
                            <div class="form-group">
                                <label for="modelo">Modelo *</label>
                                <input type="text" class="form-control" id="modelo" name="modelo" placeholder="Ej: Oceanis 40">
                                <div class="error-message">Por favor introduce el modelo</div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="ano">Año de fabricación</label>
                                <input type="number" class="form-control" id="ano" name="ano" placeholder="2020" min="1950" max="2024">
                                <small style="color: #666; font-size: 11px;">Año de construcción de la embarcación</small>
                            </div>
                            <div class="form-group">
                                <label for="bastidor">Número de casco/Bastidor</label>
                                <input type="text" class="form-control" id="bastidor" name="bastidor" placeholder="ABC123456789" maxlength="20">
                                <small style="color: #666; font-size: 11px;">Identificación única del casco</small>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="precio">Precio de venta (€) *</label>
                                <input type="number" class="form-control" id="precio" name="precio" placeholder="Ej: 45000" min="1">
                                <div class="error-message">Por favor introduce el precio</div>
                            </div>
                            <div class="form-group">
                                <label for="forma-pago">Forma de pago</label>
                                <select class="form-control" id="forma-pago" name="formaPago">
                                    <option value="al contado">Al contado</option>
                                    <option value="financiado">Financiado</option>
                                    <option value="a plazos">A plazos</option>
                                    <option value="transferencia bancaria">Transferencia bancaria</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="lugar-entrega">Lugar de entrega</label>
                            <input type="text" class="form-control" id="lugar-entrega" name="lugarEntrega" placeholder="Puerto Marina, Valencia">
                            <small style="color: #666; font-size: 11px;">Donde se realizará la entrega de la embarcación</small>
                        </div>

                        <div class="form-group">
                            <label for="motor">Datos del motor (recomendado)</label>
                            <textarea class="form-control" id="motor" name="motor" rows="3" placeholder="Ej: Volvo Penta D2-40, 40HP, año 2018, nº serie: 123456"></textarea>
                        </div>

                        <div class="btn-group">
                            <button type="button" class="btn btn-primary" onclick="nextStep(2)">Continuar →</button>
                        </div>
                    </div>

                    <!-- PASO 2: DATOS DEL VENDEDOR -->
                    <div class="form-step" id="step-2">
                        <div class="step-title">
                            <span class="step-number">2</span>
                            <span>Datos del vendedor</span>
                        </div>

                        <div class="form-group">
                            <label for="vendedor-nombre">Nombre completo *</label>
                            <input type="text" class="form-control" id="vendedor-nombre" name="vendedorNombre" placeholder="Nombre y apellidos completos">
                            <div class="error-message">Por favor introduce el nombre completo</div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="vendedor-dni">DNI/NIE *</label>
                                <input type="text" class="form-control" id="vendedor-dni" name="vendedorDni" placeholder="12345678A">
                                <div class="error-message">Por favor introduce el DNI/NIE</div>
                            </div>
                            <div class="form-group">
                                <label for="vendedor-telefono">Teléfono *</label>
                                <input type="tel" class="form-control" id="vendedor-telefono" name="vendedorTelefono" placeholder="600123456">
                                <div class="error-message">Por favor introduce el teléfono</div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="vendedor-direccion">Dirección completa *</label>
                            <textarea class="form-control" id="vendedor-direccion" name="vendedorDireccion" rows="2" placeholder="Calle, número, código postal, ciudad, provincia"></textarea>
                            <div class="error-message">Por favor introduce la dirección completa</div>
                        </div>

                        <div class="form-group">
                            <label for="vendedor-email">Email *</label>
                            <input type="email" class="form-control" id="vendedor-email" name="vendedorEmail" placeholder="ejemplo@correo.com">
                            <div class="error-message">Por favor introduce un email válido</div>
                        </div>

                        <div class="btn-group">
                            <button type="button" class="btn btn-secondary" onclick="prevStep(1)">← Anterior</button>
                            <button type="button" class="btn btn-primary" onclick="nextStep(3)">Continuar →</button>
                        </div>
                    </div>

                    <!-- PASO 3: DATOS DEL COMPRADOR -->
                    <div class="form-step" id="step-3">
                        <div class="step-title">
                            <span class="step-number">3</span>
                            <span>Datos del comprador</span>
                        </div>

                        <div class="form-group">
                            <label for="comprador-nombre">Nombre completo *</label>
                            <input type="text" class="form-control" id="comprador-nombre" name="compradorNombre" placeholder="Nombre y apellidos completos">
                            <div class="error-message">Por favor introduce el nombre completo</div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="comprador-dni">DNI/NIE *</label>
                                <input type="text" class="form-control" id="comprador-dni" name="compradorDni" placeholder="12345678A">
                                <div class="error-message">Por favor introduce el DNI/NIE</div>
                            </div>
                            <div class="form-group">
                                <label for="comprador-telefono">Teléfono *</label>
                                <input type="tel" class="form-control" id="comprador-telefono" name="compradorTelefono" placeholder="600123456">
                                <div class="error-message">Por favor introduce el teléfono</div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="comprador-direccion">Dirección completa *</label>
                            <textarea class="form-control" id="comprador-direccion" name="compradorDireccion" rows="2" placeholder="Calle, número, código postal, ciudad, provincia"></textarea>
                            <div class="error-message">Por favor introduce la dirección completa</div>
                        </div>

                        <div class="form-group">
                            <label for="comprador-email">Email *</label>
                            <input type="email" class="form-control" id="comprador-email" name="compradorEmail" placeholder="ejemplo@correo.com">
                            <div class="error-message">Por favor introduce un email válido</div>
                        </div>

                        <div class="btn-group">
                            <button type="button" class="btn btn-secondary" onclick="prevStep(2)">← Anterior</button>
                            <button type="button" class="btn btn-primary" onclick="nextStep(4)">Continuar →</button>
                        </div>
                    </div>

                    <!-- PASO 4: DOCUMENTACIÓN PDF -->
                    <div class="form-step" id="step-4">
                        <div class="step-title">
                            <span class="step-number">4</span>
                            <span>Documentación requerida</span>
                        </div>

                        <p style="color: #666; margin-bottom: 25px;">
                            Para completar el contrato, necesitamos la documentación de ambas partes en formato PDF:
                        </p>

                        <div class="form-group">
                            <label>📄 Documentación del vendedor *</label>
                            <div class="upload-area" onclick="document.getElementById('vendedor-pdf').click()">
                                <p style="margin: 0; font-size: 16px; color: var(--primary);">
                                    📁 Haz clic para subir PDF del vendedor
                                </p>
                                <p style="margin: 5px 0 0 0; font-size: 12px; color: #666;">
                                    DNI, NIE o documentación oficial
                                </p>
                                <input type="file" id="vendedor-pdf" accept=".pdf" style="display: none;">
                            </div>
                            <div id="vendedor-pdf-status" style="font-size: 14px; margin-top: 10px;"></div>
                        </div>

                        <div class="form-group">
                            <label>📄 Documentación del comprador *</label>
                            <div class="upload-area" onclick="document.getElementById('comprador-pdf').click()">
                                <p style="margin: 0; font-size: 16px; color: var(--primary);">
                                    📁 Haz clic para subir PDF del comprador
                                </p>
                                <p style="margin: 5px 0 0 0; font-size: 12px; color: #666;">
                                    DNI, NIE o documentación oficial
                                </p>
                                <input type="file" id="comprador-pdf" accept=".pdf" style="display: none;">
                            </div>
                            <div id="comprador-pdf-status" style="font-size: 14px; margin-top: 10px;"></div>
                        </div>

                        <div class="btn-group">
                            <button type="button" class="btn btn-secondary" onclick="prevStep(3)">← Anterior</button>
                            <button type="button" class="btn btn-primary" onclick="nextStep(5)">Continuar →</button>
                        </div>
                    </div>

                    <!-- PASO 5: CLÁUSULAS ADICIONALES -->
                    <div class="form-step" id="step-5">
                        <div class="step-title">
                            <span class="step-number">5</span>
                            <span>Cláusulas adicionales</span>
                        </div>

                        <p style="color: #666; margin-bottom: 25px;">
                            Selecciona las cláusulas adicionales que deseas incluir en el contrato:
                        </p>

                        <div class="clausulas-section">
                            <div class="clausula-option">
                                <input type="checkbox" id="clausula-documentacion" name="clausulas[]" value="documentacion">
                                <label for="clausula-documentacion">
                                    <strong>📋 Tramitación de documentación</strong><br>
                                    <small>Los gastos de tramitación y cambio de titularidad correrán a cuenta de: [especificar parte]</small>
                                </label>
                            </div>

                            <div class="clausula-option">
                                <input type="checkbox" id="clausula-inspeccion" name="clausulas[]" value="inspeccion">
                                <label for="clausula-inspeccion">
                                    <strong>🔍 Inspección previa</strong><br>
                                    <small>El comprador ha inspeccionado la embarcación y la acepta en las condiciones actuales</small>
                                </label>
                            </div>

                            <div class="clausula-option">
                                <input type="checkbox" id="clausula-itp" name="clausulas[]" value="itp">
                                <label for="clausula-itp">
                                    <strong>💰 Impuesto de Transmisiones Patrimoniales (ITP)</strong><br>
                                    <small>El pago del ITP será responsabilidad del comprador según la normativa vigente</small>
                                </label>
                            </div>

                            <div class="clausula-option">
                                <input type="checkbox" id="clausula-vicios" name="clausulas[]" value="vicios">
                                <label for="clausula-vicios">
                                    <strong>⚠️ Vicios ocultos</strong><br>
                                    <small>La venta se realiza sin garantía sobre vicios ocultos, siendo responsabilidad del comprador cualquier reparación posterior</small>
                                </label>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="clausulas-personalizadas">Cláusulas personalizadas (opcional)</label>
                            <textarea class="form-control" id="clausulas-personalizadas" name="clausulasPersonalizadas" rows="4" placeholder="Escriba aquí cualquier cláusula adicional específica..."></textarea>
                        </div>

                        <div class="form-group">
                            <div style="display: flex; align-items: flex-start; padding: 12px; background: var(--light); border-radius: 6px;">
                                <input type="checkbox" id="privacy-policy" name="privacyPolicy" required style="margin-right: 10px; margin-top: 2px;">
                                <label for="privacy-policy" style="margin: 0; font-size: 12px; line-height: 1.4; cursor: pointer;">
                                    Acepto la <a href="https://tramitfy.es/politica-de-privacidad/" target="_blank" style="color: var(--primary);">Política de Privacidad</a> y los <a href="https://tramitfy.es/terminos-y-condiciones-de-uso-2/" target="_blank" style="color: var(--primary);">Términos y Condiciones</a> de Tramitfy.es
                                </label>
                            </div>
                            <div class="error-message" id="privacy-error">Debes aceptar las políticas para continuar</div>
                        </div>

                        <div class="btn-group">
                            <button type="button" class="btn btn-secondary" onclick="prevStep(4)">← Anterior</button>
                            <button type="button" class="btn btn-success" onclick="generarContrato()">📄 Generar Contrato PDF</button>
                        </div>
                    </div>

                    <!-- PASO FINAL: ENVÍO -->
                    <div class="form-step" id="step-final">
                        <div class="step-title">
                            <span class="step-number">✓</span>
                            <span>Recibe tu contrato</span>
                        </div>

                        <div style="text-align: center; padding: 30px 0;">
                            <div style="background: linear-gradient(135deg, var(--primary-light) 0%, #f0f8fa 100%); border-radius: var(--radius); padding: 30px; margin-bottom: 30px; border: 2px solid var(--secondary);">
                                <h3 style="color: var(--primary); margin: 0 0 20px 0; font-size: 24px; font-weight: 700;">📄 ¡Contrato generado exitosamente!</h3>
                                <p style="margin: 0; color: var(--dark); font-size: 16px; line-height: 1.6;">
                                    Hemos procesado todos los datos y generado tu contrato de compra-venta personalizado.<br>
                                    Te lo enviamos junto con toda la información necesaria para la tramitación.
                                </p>
                            </div>

                            <div class="form-group" style="text-align: left;">
                                <label for="email-final" style="font-size: 16px; color: var(--dark);">📧 Email para recibir el contrato</label>
                                <input type="email" class="form-control" id="email-final" name="emailFinal" placeholder="ejemplo@correo.com" required style="font-size: 16px; padding: 16px;">
                                <div style="font-size: 12px; color: #666; margin-top: 8px;">
                                    Te enviaremos el contrato en PDF y toda la información sobre los siguientes pasos
                                </div>
                                <div class="error-message">Por favor introduce un email válido</div>
                            </div>

                            <div class="btn-group">
                                <button type="button" class="btn btn-secondary" onclick="prevStep(5)">← Modificar datos</button>
                                <button type="button" class="btn btn-success" id="btn-enviar-contrato" onclick="enviarContrato()">
                                    📧 Enviar mi contrato GRATIS
                                </button>
                            </div>

                            <p style="font-size: 12px; color: #666; text-align: center; margin-top: 25px; line-height: 1.4;">
                                Al generar el contrato, aceptas nuestras <a href="https://tramitfy.es/politica-de-privacidad/" target="_blank">políticas de privacidad</a>
                                y <a href="https://tramitfy.es/terminos-y-condiciones-de-uso-2/" target="_blank">términos de uso</a>.
                            </p>
                        </div>
                    </div>
                </form>

                <!-- Botón de datos de prueba para administrador -->
                <div style="text-align: center; padding: 20px; border-top: 1px solid var(--border); margin-top: 20px;">
                    <button
                        id="btn-test-data"
                        onclick="fillTestData()"
                        style="
                            background: #6c757d;
                            color: white;
                            border: none;
                            padding: 8px 16px;
                            border-radius: 4px;
                            font-size: 11px;
                            cursor: pointer;
                            transition: all 0.3s ease;
                        "
                        onmouseover="this.style.background='#5a6268'"
                        onmouseout="this.style.background='#6c757d'"
                    >
                        🔧 Rellenar datos de prueba (Admin)
                    </button>
                    <p style="font-size: 9px; color: #999; margin: 5px 0 0 0;">
                        Solo para testing - Rellena el paso actual automáticamente
                    </p>

                    <button
                        id="btn-test-ajax"
                        onclick="testAjax()"
                        style="
                            background: #dc3545;
                            color: white;
                            border: none;
                            padding: 6px 12px;
                            border-radius: 4px;
                            font-size: 10px;
                            cursor: pointer;
                            margin-top: 10px;
                            margin-right: 5px;
                        "
                    >
                        🔧 Test AJAX (Admin)
                    </button>

                    <button
                        id="btn-test-email"
                        onclick="testEmail()"
                        style="
                            background: #007bff;
                            color: white;
                            border: none;
                            padding: 6px 12px;
                            border-radius: 4px;
                            font-size: 10px;
                            cursor: pointer;
                            margin-top: 10px;
                            margin-right: 5px;
                        "
                    >
                        📧 Test Email (Admin)
                    </button>

                    <button
                        id="btn-test-pdf"
                        onclick="testPDF()"
                        style="
                            background: #28a745;
                            color: white;
                            border: none;
                            padding: 6px 12px;
                            border-radius: 4px;
                            font-size: 10px;
                            cursor: pointer;
                            margin-top: 10px;
                        "
                    >
                        📄 Test PDF (Admin)
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Variables globales
        let currentStep = 1;
        let contratoData = {};
        let uploadedFiles = {
            vendedor: null,
            comprador: null
        };

        // Datos de prueba para administrador
        const testData = {
            1: {
                tipoVehiculo: 'barco',
                marca: 'Beneteau',
                modelo: 'Oceanis 40',
                bastidor: 'BEN12345678',
                precio: '85000',
                motor: 'Volvo Penta D2-55, 55HP, año 2020, nº serie: VP987654'
            },
            2: {
                vendedorNombre: 'Juan García Martínez',
                vendedorDni: '12345678A',
                vendedorDireccion: 'Calle Marina, 15, 3º B, 28001 Madrid, Madrid',
                vendedorTelefono: '654321987',
                vendedorEmail: 'joanpinyol@hotmail.es'
            },
            3: {
                compradorNombre: 'María López Fernández',
                compradorDni: '87654321B',
                compradorDireccion: 'Avenida del Puerto, 42, 1º A, 08003 Barcelona, Barcelona',
                compradorTelefono: '687654321',
                compradorEmail: 'joanpinyol@hotmail.es'
            },
            5: {
                clausulas: ['inspeccion', 'itp'],
                clausulasPersonalizadas: 'El comprador se hace cargo del transporte de la embarcación desde el puerto actual.'
            }
        };

        // Función para rellenar TODOS los datos de prueba
        function fillTestData() {
            // Solo para administradores
            if (!confirm('¿Rellenar TODOS los datos de prueba? (Solo para administradores)')) {
                return;
            }

            // PASO 1: Datos de embarcación
            document.querySelectorAll('.vehicle-option').forEach(opt => opt.classList.remove('selected'));
            const barcoOption = document.querySelector('[data-vehicle="barco"]');
            if (barcoOption) {
                barcoOption.classList.add('selected');
                barcoOption.querySelector('input').checked = true;
                currentVehicleType = 'barco';
            }

            const fillField = (id, value) => {
                const field = document.getElementById(id);
                if (field) field.value = value;
            };

            fillField('marca', 'Beneteau');
            fillField('modelo', 'Oceanis 40');
            fillField('ano', '2020');
            fillField('bastidor', 'BEN12345678');
            fillField('precio', '85000');
            fillField('forma-pago', 'al contado');
            fillField('lugar-entrega', 'Puerto Marina Valencia');
            fillField('motor', 'Volvo Penta D2-55, 55HP, año 2020, nº serie: VP987654');

            // PASO 2: Datos del vendedor
            fillField('vendedor-nombre', 'Juan García Martínez');
            fillField('vendedor-dni', '12345678A');
            fillField('vendedor-direccion', 'Calle Marina, 15, 3º B, 28001 Madrid, Madrid');
            fillField('vendedor-telefono', '654321987');
            fillField('vendedor-email', 'ipmgroup24@gmail.com');

            // PASO 3: Datos del comprador
            fillField('comprador-nombre', 'Joan Pinyol');
            fillField('comprador-dni', '87654321B');
            fillField('comprador-direccion', 'Avenida del Puerto, 42, 1º A, 08003 Barcelona, Barcelona');
            fillField('comprador-telefono', '687654321');
            fillField('comprador-email', 'joanpinyol@hotmail.es');

            // PASO 4: Simular archivos PDF
            const vendedorArea = document.querySelector('[onclick*="vendedor-pdf"]');
            const compradorArea = document.querySelector('[onclick*="comprador-pdf"]');

            if (vendedorArea) {
                vendedorArea.classList.add('active');
                document.getElementById('vendedor-pdf-status').innerHTML = '✅ <strong>documento-vendedor.pdf</strong> (simulado)';
                document.getElementById('vendedor-pdf-status').style.color = 'var(--success)';
            }

            if (compradorArea) {
                compradorArea.classList.add('active');
                document.getElementById('comprador-pdf-status').innerHTML = '✅ <strong>documento-comprador.pdf</strong> (simulado)';
                document.getElementById('comprador-pdf-status').style.color = 'var(--success)';
            }

            // Simular archivos para validación
            uploadedFiles.vendedor = { name: 'documento-vendedor.pdf', size: 1024000 };
            uploadedFiles.comprador = { name: 'documento-comprador.pdf', size: 1024000 };

            // PASO 5: Cláusulas
            const checkClausula = (id) => {
                const checkbox = document.getElementById(id);
                if (checkbox) checkbox.checked = true;
            };

            checkClausula('clausula-inspeccion');
            checkClausula('clausula-itp');
            checkClausula('privacy-policy');

            fillField('clausulas-personalizadas', 'El comprador se hace cargo del transporte de la embarcación desde el puerto actual.');

            // Actualizar vista previa
            updatePreview();

            alert('✅ Todos los datos de prueba han sido rellenados correctamente');
        }

        // Función de test AJAX
        function testAjax() {
            console.log('Iniciando test AJAX...');

            const formData = new FormData();
            formData.append('action', 'tramitfy_test');
            formData.append('test', 'simple');

            fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Response status:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('AJAX Response:', data);
                if (data.success) {
                    alert('✅ AJAX Test EXITOSO! Revisa /wp-content/tramitfy-debug.log');
                } else {
                    alert('❌ AJAX Test falló: ' + JSON.stringify(data));
                }
            })
            .catch(error => {
                console.error('Error en test AJAX:', error);
                alert('❌ Error en test AJAX: ' + error.message);
            });
        }

        // Función de test Email
        function testEmail() {
            console.log('📧 Iniciando test de Email...');

            // Obtener email del formulario o usar uno por defecto
            let testEmailAddress = document.getElementById('vendedor-email')?.value ||
                                 document.getElementById('comprador-email')?.value ||
                                 'joanpinyol@hotmail.es';

            if (!testEmailAddress || !isValidEmail(testEmailAddress)) {
                testEmailAddress = prompt('Introduce un email válido para el test:', 'joanpinyol@hotmail.es');
                if (!testEmailAddress || !isValidEmail(testEmailAddress)) {
                    alert('❌ Email no válido');
                    return;
                }
            }

            const formData = new FormData();
            formData.append('action', 'tramitfy_test_email');
            formData.append('email', testEmailAddress);

            fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                console.log('Test Email response:', data);
                if (data.success) {
                    alert('📧 Test Email: ' + data.data.message +
                          '\nDestinatario: ' + testEmailAddress +
                          '\n\n💡 Revisa tu bandeja de entrada (y spam)\n💡 Revisa logs en /wp-content/tramitfy-debug.log');
                } else {
                    alert('❌ Error en test Email: ' + (data.data ? data.data.message : 'Error desconocido'));
                }
            })
            .catch(error => {
                console.error('Error en test Email:', error);
                alert('❌ Error en test Email: ' + error.message);
            });
        }

        // Función de test PDF
        function testPDF() {
            console.log('📄 Iniciando test de PDF...');

            if (!confirm('¿Generar PDF de prueba? Se rellenarán datos automáticamente.')) {
                return;
            }

            fillTestData(); // Rellenar datos de prueba

            const formData = new FormData();
            formData.append('action', 'tramitfy_test_pdf');

            // Recoger algunos datos básicos del formulario
            const testData = {
                marca: document.getElementById('marca')?.value || 'Beneteau',
                modelo: document.getElementById('modelo')?.value || 'Oceanis 40',
                ano: document.getElementById('ano')?.value || '2020',
                precio: document.getElementById('precio')?.value || '85000',
                vendedorNombre: document.getElementById('vendedor-nombre')?.value || 'Test Vendedor',
                compradorNombre: document.getElementById('comprador-nombre')?.value || 'Test Comprador'
            };

            formData.append('test_data', JSON.stringify(testData));

            fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                console.log('Test PDF response:', data);
                if (data.success) {
                    alert('📄 Test PDF exitoso!\n\n' +
                          data.data.message +
                          '\n\n💡 Revisa logs en /wp-content/tramitfy-debug.log');
                    if (data.data.pdf_url) {
                        window.open(data.data.pdf_url, '_blank');
                    }
                } else {
                    alert('❌ Error en test PDF: ' + (data.data ? data.data.message : 'Error desconocido'));
                }
            })
            .catch(error => {
                console.error('Error en test PDF:', error);
                alert('❌ Error en test PDF: ' + error.message);
            });
        }

        // Inicialización
        document.addEventListener('DOMContentLoaded', function() {
            setupEventListeners();
            updatePreview();

            // Añadir botón de datos de prueba para cada paso
            document.addEventListener('keydown', function(e) {
                if (e.ctrlKey && e.shiftKey && e.key === 'T') {
                    fillTestData();
                }
            });
        });

        // Event Listeners
        function setupEventListeners() {
            // Selección de tipo de vehículo
            document.querySelectorAll('.vehicle-option').forEach(option => {
                option.addEventListener('click', function() {
                    document.querySelectorAll('.vehicle-option').forEach(opt => opt.classList.remove('selected'));
                    this.classList.add('selected');
                    const radio = this.querySelector('input[type="radio"]');
                    radio.checked = true;
                    updatePreview();
                });
            });

            // Campos de formulario - actualización en tiempo real
            const formFields = [
                'marca', 'modelo', 'bastidor', 'precio', 'motor',
                'vendedor-nombre', 'vendedor-dni', 'vendedor-direccion', 'vendedor-telefono', 'vendedor-email',
                'comprador-nombre', 'comprador-dni', 'comprador-direccion', 'comprador-telefono', 'comprador-email',
                'clausulas-personalizadas'
            ];

            formFields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (field) {
                    field.addEventListener('input', updatePreview);
                    field.addEventListener('change', updatePreview);
                }
            });

            // Checkboxes de cláusulas
            document.querySelectorAll('input[name="clausulas[]"]').forEach(checkbox => {
                checkbox.addEventListener('change', updatePreview);
            });

            // Uploads de archivos
            setupFileUpload('vendedor-pdf', 'vendedor');
            setupFileUpload('comprador-pdf', 'comprador');
        }

        // Configuración de upload de archivos
        function setupFileUpload(inputId, type) {
            const input = document.getElementById(inputId);
            const status = document.getElementById(type + '-pdf-status');
            const uploadArea = input.parentElement;

            input.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    if (file.type !== 'application/pdf') {
                        alert('Por favor selecciona solo archivos PDF');
                        return;
                    }

                    if (file.size > 10 * 1024 * 1024) { // 10MB
                        alert('El archivo es demasiado grande. Máximo 10MB.');
                        return;
                    }

                    uploadedFiles[type] = file;
                    uploadArea.classList.add('active');
                    status.innerHTML = `✅ <strong>${file.name}</strong> (${(file.size/1024/1024).toFixed(1)} MB)`;
                    status.style.color = 'var(--success)';
                } else {
                    uploadedFiles[type] = null;
                    uploadArea.classList.remove('active');
                    status.innerHTML = '';
                }
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
            document.querySelectorAll('.form-step').forEach(s => s.classList.remove('active'));

            // Mostrar paso actual
            if (step === 'final') {
                document.getElementById('step-final').classList.add('active');
            } else {
                document.getElementById(`step-${step}`).classList.add('active');
            }

            // Actualizar puntos de progreso
            document.querySelectorAll('.progress-dot').forEach((dot, index) => {
                dot.classList.remove('active', 'completed');
                if (step === 'final') {
                    dot.classList.add('completed');
                } else if (index + 1 < step) {
                    dot.classList.add('completed');
                } else if (index + 1 === step) {
                    dot.classList.add('active');
                }
            });

            currentStep = step;
            autoExpandCurrentSection();
            updatePreview();
        }

        // Validaciones
        function validateCurrentStep() {
            const fieldsToValidate = getFieldsForStep(currentStep);
            let isValid = true;

            fieldsToValidate.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (field && !field.value.trim()) {
                    showError(field);
                    isValid = false;
                } else if (field) {
                    clearError(field);
                }
            });

            // Validaciones específicas
            if (currentStep === 4) {
                if (!uploadedFiles.vendedor) {
                    alert('Por favor sube la documentación del vendedor');
                    isValid = false;
                }
                if (!uploadedFiles.comprador) {
                    alert('Por favor sube la documentación del comprador');
                    isValid = false;
                }
            }

            if (currentStep === 5) {
                const privacyCheck = document.getElementById('privacy-policy');
                if (!privacyCheck.checked) {
                    document.getElementById('privacy-error').style.display = 'block';
                    isValid = false;
                } else {
                    document.getElementById('privacy-error').style.display = 'none';
                }
            }

            return isValid;
        }

        function getFieldsForStep(step) {
            const fieldsByStep = {
                1: ['marca', 'modelo', 'ano', 'bastidor', 'matricula', 'precio', 'forma-pago', 'lugar-entrega'],
                2: ['vendedor-nombre', 'vendedor-dni', 'vendedor-direccion', 'vendedor-telefono', 'vendedor-email'],
                3: ['comprador-nombre', 'comprador-dni', 'comprador-direccion', 'comprador-telefono', 'comprador-email']
            };
            return fieldsByStep[step] || [];
        }

        function showError(field) {
            field.classList.add('error');
        }

        function clearError(field) {
            field.classList.remove('error');
        }

        // Función para toggle de secciones
        function toggleSection(sectionName) {
            const section = document.getElementById('section-' + sectionName) || document.getElementById('clausulas-list');
            const icon = document.getElementById('toggle-' + sectionName);

            if (section && icon) {
                if (section.classList.contains('collapsed')) {
                    section.classList.remove('collapsed');
                    icon.textContent = '▼';
                } else {
                    section.classList.add('collapsed');
                    icon.textContent = '▶';
                }
            }

            // Si es cláusulas, ir al paso 5
            if (sectionName === 'clausulas' && currentStep !== 5) {
                showStep(5);
            }
        }

        // Auto-expandir sección según paso actual
        function autoExpandCurrentSection() {
            // Colapsar todas las secciones primero
            ['vendedor', 'comprador', 'condiciones', 'clausulas'].forEach(section => {
                const sectionEl = document.getElementById('section-' + section);
                const icon = document.getElementById('toggle-' + section);
                if (sectionEl && icon) {
                    sectionEl.classList.add('collapsed');
                    icon.textContent = '▶';
                }
            });

            // Expandir según el paso actual
            let sectionToExpand = '';
            if (currentStep === 2) sectionToExpand = 'vendedor';
            else if (currentStep === 3) sectionToExpand = 'comprador';
            else if (currentStep === 5) sectionToExpand = 'clausulas';

            if (sectionToExpand) {
                const section = document.getElementById('section-' + sectionToExpand);
                const icon = document.getElementById('toggle-' + sectionToExpand);
                if (section && icon) {
                    section.classList.remove('collapsed');
                    icon.textContent = '▼';
                }
            }
        }

        // Actualización de vista previa en tiempo real
        function updatePreview() {
            // Tipo de vehículo
            const tipoVehiculo = document.querySelector('input[name="tipoVehiculo"]:checked');
            document.getElementById('prev-tipo-vehiculo').textContent =
                tipoVehiculo?.value === 'moto' ? 'Moto Acuática' : 'Embarcación';

            // Datos básicos
            updatePreviewField('marca', 'prev-marca');
            updatePreviewField('modelo', 'prev-modelo');
            updatePreviewField('ano', 'prev-ano');
            updatePreviewField('bastidor', 'prev-bastidor');
            updatePreviewField('matricula', 'prev-matricula');
            updatePreviewField('forma-pago', 'prev-forma-pago');
            updatePreviewField('lugar-entrega', 'prev-lugar-entrega');
            updatePreviewField('motor', 'prev-motor');

            // Precio
            const precio = document.getElementById('precio').value;
            if (precio) {
                document.getElementById('prev-precio').textContent = formatCurrency(precio);
                document.getElementById('prev-precio-texto').textContent = formatCurrency(precio);
            }

            // Mostrar/ocultar info del motor
            const motorField = document.getElementById('motor').value;
            const motorInfo = document.getElementById('motor-info');
            if (motorField.trim()) {
                motorInfo.style.display = 'block';
            } else {
                motorInfo.style.display = 'none';
            }

            // Datos del vendedor
            updatePreviewField('vendedor-nombre', 'prev-vendedor-nombre');
            updatePreviewField('vendedor-dni', 'prev-vendedor-dni');
            updatePreviewField('vendedor-direccion', 'prev-vendedor-direccion');
            updatePreviewField('vendedor-telefono', 'prev-vendedor-telefono');
            updatePreviewField('vendedor-email', 'prev-vendedor-email');

            // Datos del comprador
            updatePreviewField('comprador-nombre', 'prev-comprador-nombre');
            updatePreviewField('comprador-dni', 'prev-comprador-dni');
            updatePreviewField('comprador-direccion', 'prev-comprador-direccion');
            updatePreviewField('comprador-telefono', 'prev-comprador-telefono');
            updatePreviewField('comprador-email', 'prev-comprador-email');

            // Cláusulas adicionales
            updateClausulasPreview();
        }

        function updatePreviewField(inputId, previewId) {
            const input = document.getElementById(inputId);
            const preview = document.getElementById(previewId);
            if (input && preview) {
                const value = input.value.trim();
                if (value) {
                    preview.textContent = value;
                    preview.classList.remove('data-placeholder');
                } else {
                    preview.classList.add('data-placeholder');
                }
            }
        }

        function updateClausulasPreview() {
            const clausulasSelected = [];
            const checkboxes = document.querySelectorAll('input[name="clausulas[]"]:checked');

            checkboxes.forEach(checkbox => {
                const label = checkbox.parentElement.querySelector('label strong').textContent;
                const description = checkbox.parentElement.querySelector('label small').textContent;
                clausulasSelected.push({ label, description });
            });

            const clausulasPersonalizadas = document.getElementById('clausulas-personalizadas').value.trim();

            const clausulasContainer = document.getElementById('clausulas-adicionales-preview');
            const clausulasList = document.getElementById('clausulas-list');

            if (clausulasSelected.length > 0 || clausulasPersonalizadas) {
                clausulasContainer.style.display = 'block';

                let html = '';
                clausulasSelected.forEach((clausula, index) => {
                    html += `<p><strong>${index + 1}. ${clausula.label.replace(/[^\w\s]/gi, '')}</strong></p>`;
                    html += `<p style="margin-left: 15px; font-size: 11px; color: #666;">${clausula.description}</p>`;
                });

                if (clausulasPersonalizadas) {
                    const nextNumber = clausulasSelected.length + 1;
                    html += `<p><strong>${nextNumber}. Cláusulas adicionales:</strong></p>`;
                    html += `<p style="margin-left: 15px; font-size: 11px; color: #666;">${clausulasPersonalizadas}</p>`;
                }

                clausulasList.innerHTML = html;
            } else {
                clausulasContainer.style.display = 'none';
            }
        }

        // Validar formato de email
        function isValidEmail(email) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        }

        // Generar contrato directamente
        function generarContrato() {
            console.log('🚀 === INICIANDO PROCESO DE GENERACIÓN DE CONTRATO ===');

            if (!validateCurrentStep()) {
                console.log('❌ Validación de paso falló');
                return;
            }

            console.log('✅ Paso validado correctamente');

            // Mostrar mensaje informativo
            const suggestedEmail = contratoData?.compradorEmail || contratoData?.vendedorEmail || '';
            console.log('📧 Email sugerido:', suggestedEmail);

            const userEmail = prompt('Introduce tu email para recibir el contrato:', suggestedEmail);
            console.log('📧 Email introducido por usuario:', userEmail);

            if (!userEmail || !isValidEmail(userEmail)) {
                console.log('❌ Email no válido:', userEmail);
                alert('Por favor introduce un email válido');
                return;
            }

            console.log('✅ Email validado correctamente');

            // Confirmar envío
            if (!confirm(`¿Confirmas que quieres generar el contrato y enviarlo a ${userEmail}?`)) {
                console.log('❌ Usuario canceló el envío');
                return;
            }

            console.log('✅ Usuario confirmó el envío');

            // Recopilar todos los datos
            console.log('📊 Recopilando todos los datos...');
            contratoData = gatherAllData();
            contratoData.userEmail = userEmail;
            console.log('📊 Datos recopilados:', contratoData);

            // Generar y enviar PDF
            console.log('🔄 Iniciando generación y envío del PDF...');
            generarYEnviarContratoPDF();
        }

        // Generar y enviar PDF del contrato
        function generarYEnviarContratoPDF() {
            console.log('🔄 INICIANDO GENERACIÓN DE CONTRATO PDF');
            console.log('📧 Email del usuario:', contratoData.userEmail);
            console.log('📊 Datos del contrato:', contratoData);

            const btnPDF = document.querySelector('.generate-status p');
            if (btnPDF) {
                btnPDF.innerHTML = '📄 Generando PDF...';
            }

            // Preparar datos para el PDF
            console.log('📤 Preparando FormData...');
            const formData = new FormData();
            formData.append('action', 'generar_y_enviar_contrato_pdf');
            formData.append('userEmail', contratoData.userEmail);

            // Añadir todos los datos del contrato
            Object.keys(contratoData).forEach(key => {
                if (key === 'clausulas') {
                    formData.append(key, JSON.stringify(contratoData[key]));
                } else {
                    formData.append(key, contratoData[key]);
                }
            });

            // Añadir archivos PDF si existen
            if (uploadedFiles.vendedor) {
                formData.append('vendedor_pdf', uploadedFiles.vendedor);
            }
            if (uploadedFiles.comprador) {
                formData.append('comprador_pdf', uploadedFiles.comprador);
            }

            console.log('🔐 Añadiendo nonce de seguridad...');
            formData.append('nonce', '<?php echo wp_create_nonce("contrato_pdf_nonce"); ?>');

            console.log('📤 Enviando petición AJAX...');
            console.log('🌐 URL destino:', '<?php echo admin_url("admin-ajax.php"); ?>');

            // Enviar petición para generar PDF
            fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('📥 Respuesta recibida - Status:', response.status, response.statusText);
                console.log('📄 Content-Type:', response.headers.get('content-type'));

                if (!response.ok) {
                    throw new Error(`HTTP Error: ${response.status} ${response.statusText}`);
                }

                return response.text(); // Cambiar a text() para debug
            })
            .then(responseText => {
                console.log('📋 Respuesta completa (raw):', responseText);

                let data;
                try {
                    data = JSON.parse(responseText);
                    console.log('✅ JSON parseado correctamente:', data);
                } catch (e) {
                    console.error('❌ Error al parsear JSON:', e);
                    console.error('📋 Contenido que no se pudo parsear:', responseText.substring(0, 500));
                    throw new Error('Respuesta del servidor no es JSON válido');
                }

                if (data.success) {
                    console.log('🎉 CONTRATO GENERADO EXITOSAMENTE');
                    alert('✅ ¡Contrato generado y enviado por email exitosamente!\n\n📧 Revisa tu bandeja de entrada (y spam) en unos minutos.\n\n📄 Recibirás un PDF profesional del contrato adjunto al email, listo para imprimir y firmar.');

                    // Redireccionar después de un momento
                    console.log('⏰ Programando redirección en 3 segundos...');
                    setTimeout(() => {
                        if (confirm('¿Quieres ir a nuestros servicios de tramitación?')) {
                            console.log('🔗 Redirigiendo a servicios...');
                            window.location.href = 'https://tramitfy.es/cambio-titularidad-embarcacion/';
                        }
                    }, 3000);
                } else {
                    console.error('❌ Error del servidor:', data);
                    console.error('📋 Datos de error:', data.data);
                    alert('❌ Error al generar el contrato:\n\n' + (data.data || 'Error desconocido') + '\n\n💡 Posibles causas:\n- Configuración de email del servidor\n- Email en spam/promociones\n- Problema temporal\n\n🔄 Intenta de nuevo en unos minutos.');
                }
            })
            .catch(error => {
                console.error('💥 ERROR CRÍTICO:', error);
                console.error('📋 Stack trace:', error.stack);
                alert('❌ Error al generar el PDF: ' + error.message + '\n\n🔍 Revisa la consola del navegador (F12) para más detalles.');

                // Restaurar estado
                if (btnPDF) {
                    btnPDF.innerHTML = '📄 Complete todos los datos para generar el contrato profesional';
                }
            });
        }

        function gatherAllData() {
            console.log('📊 === INICIANDO RECOPILACIÓN DE DATOS ===');

            const tipoVehiculo = document.querySelector('input[name="tipoVehiculo"]:checked')?.value;
            console.log('🚗 Tipo vehículo:', tipoVehiculo);

            // Función helper para obtener valor seguro
            const getValue = (id) => {
                const element = document.getElementById(id);
                const value = element?.value || '';
                console.log(`📝 ${id}: ${element ? `"${value}"` : 'ELEMENTO NO ENCONTRADO ❌'}`);
                return value;
            };

            const data = {
                // Datos del vehículo
                tipoVehiculo: tipoVehiculo,
                marca: getValue('marca'),
                modelo: getValue('modelo'),
                ano: getValue('ano'),
                bastidor: getValue('bastidor'),
                matricula: getValue('matricula'),
                precio: getValue('precio'),
                formaPago: getValue('forma-pago'),
                lugarEntrega: getValue('lugar-entrega'),
                motor: getValue('motor'),

                // Datos del vendedor
                vendedorNombre: getValue('vendedor-nombre'),
                vendedorDni: getValue('vendedor-dni'),
                vendedorDireccion: getValue('vendedor-direccion'),
                vendedorTelefono: getValue('vendedor-telefono'),
                vendedorEmail: getValue('vendedor-email'),

                // Datos del comprador
                compradorNombre: getValue('comprador-nombre'),
                compradorDni: getValue('comprador-dni'),
                compradorDireccion: getValue('comprador-direccion'),
                compradorTelefono: getValue('comprador-telefono'),
                compradorEmail: getValue('comprador-email'),

                // Cláusulas
                clausulas: Array.from(document.querySelectorAll('input[name="clausulas[]"]:checked')).map(cb => cb.value),
                clausulasPersonalizadas: getValue('clausulas-personalizadas')
            };

            console.log('📊 === DATOS RECOPILADOS EXITOSAMENTE ===');
            console.log('📋 Datos finales:', data);
            return data;
        }

        // Enviar contrato
        function enviarContrato() {
            const email = document.getElementById('email-final').value;
            const btnEnviar = document.getElementById('btn-enviar-contrato');

            if (!email || !isValidEmail(email)) {
                showError(document.getElementById('email-final'));
                return;
            }

            clearError(document.getElementById('email-final'));

            // Deshabilitar botón
            btnEnviar.disabled = true;
            btnEnviar.innerHTML = '📧 Enviando...';
            btnEnviar.style.background = '#cccccc';

            // Preparar datos para envío
            const formData = new FormData();
            formData.append('action', 'enviar_contrato_compraventa');
            formData.append('email', email);

            // Añadir datos del contrato
            Object.keys(contratoData).forEach(key => {
                if (key === 'clausulas') {
                    formData.append(key, JSON.stringify(contratoData[key]));
                } else {
                    formData.append(key, contratoData[key]);
                }
            });

            // Añadir archivos PDF
            if (uploadedFiles.vendedor) {
                formData.append('vendedor_pdf', uploadedFiles.vendedor);
            }
            if (uploadedFiles.comprador) {
                formData.append('comprador_pdf', uploadedFiles.comprador);
            }

            formData.append('nonce', '<?php echo wp_create_nonce("contrato_pdf_nonce"); ?>');

            // Enviar via AJAX
            fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ ¡Tu contrato ha sido generado y enviado correctamente!');

                    // Redireccionar
                    setTimeout(() => {
                        window.location.href = 'https://tramitfy.es/cambio-titularidad-embarcacion/';
                    }, 2000);
                } else {
                    alert('❌ Error al enviar el contrato: ' + (data.data || 'Error desconocido'));

                    // Rehabilitar botón
                    btnEnviar.disabled = false;
                    btnEnviar.innerHTML = '📧 Enviar mi contrato GRATIS';
                    btnEnviar.style.background = 'var(--success)';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('❌ Error de conexión. Intenta de nuevo.');

                // Rehabilitar botón
                btnEnviar.disabled = false;
                btnEnviar.innerHTML = '📧 Enviar mi contrato GRATIS';
                btnEnviar.style.background = 'var(--success)';
            });
        }

        // Utilidades
        function formatCurrency(amount) {
            return new Intl.NumberFormat('es-ES', {
                style: 'currency',
                currency: 'EUR',
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            }).format(parseFloat(amount) || 0);
        }

        function isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }

        // Formateo de números en el campo de precio
        document.addEventListener('DOMContentLoaded', function() {
            const precioInput = document.getElementById('precio');

            precioInput.addEventListener('input', function(e) {
                let value = e.target.value.replace(/[^0-9]/g, '');
                e.target.value = value;
            });
        });
    </script>

    <?php
    return ob_get_clean();
}

// Registrar el shortcode
add_shortcode('generador_contrato', 'generador_contrato_shortcode');

// Función AJAX para el envío del contrato
function enviar_contrato_compraventa() {
    // Verificar nonce de seguridad
    if (!wp_verify_nonce($_POST['nonce'], 'contrato_email_nonce')) {
        wp_send_json_error('Token de seguridad inválido');
        return;
    }

    // Validar y sanitizar datos
    $email = sanitize_email($_POST['email']);
    if (!is_email($email)) {
        wp_send_json_error('Email inválido');
        return;
    }

    // Recopilar datos del contrato
    $contrato_data = [
        'tipoVehiculo' => sanitize_text_field($_POST['tipoVehiculo']),
        'marca' => sanitize_text_field($_POST['marca']),
        'modelo' => sanitize_text_field($_POST['modelo']),
        'ano' => sanitize_text_field($_POST['ano']),
        'bastidor' => sanitize_text_field($_POST['bastidor']),
        'matricula' => sanitize_text_field($_POST['matricula']),
        'precio' => floatval($_POST['precio']),
        'formaPago' => sanitize_text_field($_POST['formaPago']),
        'lugarEntrega' => sanitize_text_field($_POST['lugarEntrega']),
        'motor' => sanitize_textarea_field($_POST['motor']),
        'vendedorNombre' => sanitize_text_field($_POST['vendedorNombre']),
        'vendedorDni' => sanitize_text_field($_POST['vendedorDni']),
        'vendedorDireccion' => sanitize_textarea_field($_POST['vendedorDireccion']),
        'vendedorTelefono' => sanitize_text_field($_POST['vendedorTelefono']),
        'vendedorEmail' => sanitize_email($_POST['vendedorEmail']),
        'compradorNombre' => sanitize_text_field($_POST['compradorNombre']),
        'compradorDni' => sanitize_text_field($_POST['compradorDni']),
        'compradorDireccion' => sanitize_textarea_field($_POST['compradorDireccion']),
        'compradorTelefono' => sanitize_text_field($_POST['compradorTelefono']),
        'compradorEmail' => sanitize_email($_POST['compradorEmail']),
        'clausulas' => json_decode(stripslashes($_POST['clausulas']), true),
        'clausulasPersonalizadas' => sanitize_textarea_field($_POST['clausulasPersonalizadas'])
    ];

    // Formatear datos para el email
    $vehicle_type_text = ($contrato_data['tipoVehiculo'] === 'moto') ? 'Moto Acuática' : 'Embarcación';
    $precio_formatted = number_format($contrato_data['precio'], 0, ',', '.') . ' €';

    $subject = "Tu Contrato de Compra-Venta - $vehicle_type_text {$contrato_data['marca']} {$contrato_data['modelo']}";

    // Generar cláusulas para el email
    $clausulas_text = '';
    if (!empty($contrato_data['clausulas'])) {
        $clausulas_descriptions = [
            'documentacion' => 'Los gastos de tramitación y cambio de titularidad según lo acordado entre las partes',
            'inspeccion' => 'El comprador ha inspeccionado la embarcación y la acepta en las condiciones actuales',
            'itp' => 'El pago del ITP será responsabilidad del comprador según la normativa vigente',
            'vicios' => 'La venta se realiza sin garantía sobre vicios ocultos'
        ];

        foreach ($contrato_data['clausulas'] as $clausula) {
            if (isset($clausulas_descriptions[$clausula])) {
                $clausulas_text .= "<li>✅ " . $clausulas_descriptions[$clausula] . "</li>";
            }
        }
    }

    if (!empty($contrato_data['clausulasPersonalizadas'])) {
        $clausulas_text .= "<li>📝 " . nl2br($contrato_data['clausulasPersonalizadas']) . "</li>";
    }

    $motor_info = '';
    if (!empty($contrato_data['motor'])) {
        $motor_info = "
        <div class='data-row'><strong>Datos del motor:</strong> <span>{$contrato_data['motor']}</span></div>";
    }

    $clausulas_section = '';
    if ($clausulas_text) {
        $clausulas_section = "
        <div style='background: #fff5f0; border: 2px solid #ff9900; border-radius: 8px; padding: 20px; margin: 20px 0;'>
            <h3>⚖️ Cláusulas adicionales incluidas:</h3>
            <ul style='margin: 10px 0; padding-left: 20px;'>
                $clausulas_text
            </ul>
        </div>";
    }

    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .header { background: #016d86; color: white; padding: 25px; text-align: center; }
            .content { padding: 25px; }
            .contract-box { background: #f0f8fa; border: 2px solid #02F9D2; border-radius: 12px; padding: 25px; margin: 20px 0; }
            .section { margin: 20px 0; }
            .section h3 { color: #016d86; border-left: 4px solid #02F9D2; padding-left: 12px; }
            .data-row { display: flex; justify-content: space-between; margin: 8px 0; padding: 8px 0; border-bottom: 1px solid #eee; }
            .highlight { font-size: 20px; font-weight: bold; color: #016d86; text-align: center; margin: 20px 0; }
            .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #666; }
        </style>
    </head>
    <body>
        <div class='header'>
            <h1>🚤 Contrato de Compra-Venta</h1>
            <p>Generado automáticamente por Tramitfy</p>
        </div>

        <div class='content'>
            <div class='contract-box'>
                <div class='highlight'>
                    📋 Contrato para: $vehicle_type_text {$contrato_data['marca']} {$contrato_data['modelo']}
                </div>

                <div class='section'>
                    <h3>🚤 Datos de la embarcación</h3>
                    <div class='data-row'><strong>Tipo:</strong> <span>$vehicle_type_text</span></div>
                    <div class='data-row'><strong>Marca:</strong> <span>{$contrato_data['marca']}</span></div>
                    <div class='data-row'><strong>Modelo:</strong> <span>{$contrato_data['modelo']}</span></div>
                    <div class='data-row'><strong>Número de bastidor/casco:</strong> <span>{$contrato_data['bastidor']}</span></div>
                    <div class='data-row'><strong>Precio de venta:</strong> <span>$precio_formatted</span></div>
                    $motor_info
                </div>

                <div class='section'>
                    <h3>👤 Datos del vendedor</h3>
                    <div class='data-row'><strong>Nombre:</strong> <span>{$contrato_data['vendedorNombre']}</span></div>
                    <div class='data-row'><strong>DNI/NIE:</strong> <span>{$contrato_data['vendedorDni']}</span></div>
                    <div class='data-row'><strong>Dirección:</strong> <span>{$contrato_data['vendedorDireccion']}</span></div>
                    <div class='data-row'><strong>Teléfono:</strong> <span>{$contrato_data['vendedorTelefono']}</span></div>
                    <div class='data-row'><strong>Email:</strong> <span>{$contrato_data['vendedorEmail']}</span></div>
                </div>

                <div class='section'>
                    <h3>👤 Datos del comprador</h3>
                    <div class='data-row'><strong>Nombre:</strong> <span>{$contrato_data['compradorNombre']}</span></div>
                    <div class='data-row'><strong>DNI/NIE:</strong> <span>{$contrato_data['compradorDni']}</span></div>
                    <div class='data-row'><strong>Dirección:</strong> <span>{$contrato_data['compradorDireccion']}</span></div>
                    <div class='data-row'><strong>Teléfono:</strong> <span>{$contrato_data['compradorTelefono']}</span></div>
                    <div class='data-row'><strong>Email:</strong> <span>{$contrato_data['compradorEmail']}</span></div>
                </div>
            </div>

            $clausulas_section

            <h3>📋 Próximos pasos:</h3>
            <p>Ya tienes tu contrato de compra-venta personalizado. Te recomendamos:</p>
            <ul>
                <li>✅ Revisar todos los datos cuidadosamente</li>
                <li>✅ Imprimir el contrato en duplicado</li>
                <li>✅ Firmar en presencia de ambas partes</li>
                <li>✅ Tramitar el cambio de titularidad</li>
            </ul>

            <p style='text-align: center; margin: 30px 0;'>
                <a href='https://tramitfy.es/cambio-titularidad-embarcacion/' style='background: #02F9D2; color: #016d86; padding: 15px 30px; text-decoration: none; border-radius: 8px; font-weight: bold; display: inline-block;'>
                    🚀 Necesitas ayuda con la tramitación?
                </a>
            </p>
        </div>

        <div class='footer'>
            <p>© 2024 Tramitfy - Especialistas en tramitación náutica</p>
            <p>Este contrato ha sido generado automáticamente. Consulta con un profesional si tienes dudas.</p>
        </div>
    </body>
    </html>
    ";

    // Headers para email texto plano
    $headers = array(
        'Content-Type: text/plain; charset=UTF-8',
        'From: Tramitfy <noreply@tramitfy.es>',
        'Reply-To: info@tramitfy.es'
    );

    // Convertir contenido HTML a texto plano
    $text_message = strip_tags($message);
    $text_message = html_entity_decode($text_message, ENT_QUOTES, 'UTF-8');
    $text_message = preg_replace('/\s+/', ' ', $text_message);
    $text_message = trim($text_message);

    // Enviar email como texto plano
    $sent = wp_mail($email, $subject, $text_message, $headers);

    if ($sent) {
        wp_send_json_success(['message' => 'Contrato enviado correctamente']);
    } else {
        wp_send_json_error('Error al enviar el contrato');
    }
}

add_action('wp_ajax_enviar_contrato_compraventa', 'enviar_contrato_compraventa');
add_action('wp_ajax_nopriv_enviar_contrato_compraventa', 'enviar_contrato_compraventa');

// Función AJAX para generar y enviar PDF del contrato
function generar_y_enviar_contrato_pdf() {
    // AUTOLOG COMPLETO - Inicio de función
    // Forzar log en archivo personalizado si debug.log no funciona
    $custom_log = ABSPATH . 'wp-content/tramitfy-debug.log';
    $log_entry = date('Y-m-d H:i:s') . " === TRAMITFY AUTOLOG START ===\n";
    file_put_contents($custom_log, $log_entry, FILE_APPEND | LOCK_EX);

    error_log("=== TRAMITFY AUTOLOG START ===");
    error_log("Tramitfy: Función iniciada - generar_y_enviar_contrato_pdf()");
    error_log("Tramitfy: POST recibido: " . print_r($_POST, true));

    // También escribir en nuestro log personalizado
    $log_entry = date('Y-m-d H:i:s') . " Tramitfy: Función iniciada - generar_y_enviar_contrato_pdf()\n";
    file_put_contents($custom_log, $log_entry, FILE_APPEND | LOCK_EX);

    // Catch global para errores fatales
    try {
        error_log("Tramitfy: Iniciando TRY block principal...");

    // Verificar nonce de seguridad
    error_log("Tramitfy: Verificando nonce...");
    if (!wp_verify_nonce($_POST['nonce'], 'contrato_pdf_nonce')) {
        error_log("Tramitfy: ERROR - Nonce inválido");
        wp_send_json_error('Token de seguridad inválido');
        return;
    }
    error_log("Tramitfy: Nonce válido ✓");

    // Validar email
    $email = sanitize_email($_POST['userEmail']);
    error_log("Tramitfy: Email original: " . $_POST['userEmail']);
    error_log("Tramitfy: Email sanitizado: " . $email);

    if (!is_email($email)) {
        error_log("Tramitfy: ERROR - Email inválido: " . $email);
        wp_send_json_error('Email inválido');
        return;
    }
    error_log("Tramitfy: Email válido ✓");

    // Recopilar datos del contrato
    error_log("Tramitfy: Recopilando datos del contrato...");
    $contrato_data = [
        'tipoVehiculo' => sanitize_text_field($_POST['tipoVehiculo']),
        'marca' => sanitize_text_field($_POST['marca']),
        'modelo' => sanitize_text_field($_POST['modelo']),
        'ano' => sanitize_text_field($_POST['ano']),
        'bastidor' => sanitize_text_field($_POST['bastidor']),
        'matricula' => sanitize_text_field($_POST['matricula']),
        'precio' => floatval($_POST['precio']),
        'formaPago' => sanitize_text_field($_POST['formaPago']),
        'lugarEntrega' => sanitize_text_field($_POST['lugarEntrega']),
        'motor' => sanitize_textarea_field($_POST['motor']),
        'vendedorNombre' => sanitize_text_field($_POST['vendedorNombre']),
        'vendedorDni' => sanitize_text_field($_POST['vendedorDni']),
        'vendedorDireccion' => sanitize_textarea_field($_POST['vendedorDireccion']),
        'vendedorTelefono' => sanitize_text_field($_POST['vendedorTelefono']),
        'vendedorEmail' => sanitize_email($_POST['vendedorEmail']),
        'compradorNombre' => sanitize_text_field($_POST['compradorNombre']),
        'compradorDni' => sanitize_text_field($_POST['compradorDni']),
        'compradorDireccion' => sanitize_textarea_field($_POST['compradorDireccion']),
        'compradorTelefono' => sanitize_text_field($_POST['compradorTelefono']),
        'compradorEmail' => sanitize_email($_POST['compradorEmail']),
        'clausulas' => json_decode(stripslashes($_POST['clausulas']), true),
        'clausulasPersonalizadas' => sanitize_textarea_field($_POST['clausulasPersonalizadas'])
    ];
    error_log("Tramitfy: Datos del contrato recopilados ✓");
    error_log("Tramitfy: - Embarcación: {$contrato_data['marca']} {$contrato_data['modelo']} (" . ($contrato_data['ano'] ? $contrato_data['ano'] : 'sin año') . ")");
    error_log("Tramitfy: - Forma de pago: " . ($contrato_data['formaPago'] ? $contrato_data['formaPago'] : 'no especificada'));
    error_log("Tramitfy: - Lugar entrega: " . ($contrato_data['lugarEntrega'] ? $contrato_data['lugarEntrega'] : 'no especificado'));

    // Formatear datos
    error_log("Tramitfy: Formateando datos...");
    $vehicle_type_text = ($contrato_data['tipoVehiculo'] === 'moto') ? 'MOTO ACUÁTICA' : 'EMBARCACIÓN';
    $precio_formatted = number_format($contrato_data['precio'], 0, ',', '.') . ' €';
    $fecha_actual = date('d/m/Y');

    // Generar cláusulas para el PDF
    $clausulas_text = '';
    if (!empty($contrato_data['clausulas'])) {
        $clausulas_descriptions = [
            'documentacion' => 'Los gastos de tramitación y cambio de titularidad correrán según lo acordado entre las partes.',
            'inspeccion' => 'El comprador declara haber inspeccionado la embarcación y la acepta en las condiciones actuales.',
            'itp' => 'El pago del Impuesto de Transmisiones Patrimoniales (ITP) será responsabilidad del comprador según la normativa vigente.',
            'vicios' => 'La venta se realiza sin garantía sobre vicios ocultos, siendo responsabilidad del comprador cualquier reparación posterior a la firma de este contrato.'
        ];

        $counter = 1;
        foreach ($contrato_data['clausulas'] as $clausula) {
            if (isset($clausulas_descriptions[$clausula])) {
                $clausulas_text .= $counter . '. ' . $clausulas_descriptions[$clausula] . "\n\n";
                $counter++;
            }
        }
    }

    if (!empty($contrato_data['clausulasPersonalizadas'])) {
        $clausulas_text .= $counter . '. ' . $contrato_data['clausulasPersonalizadas'] . "\n\n";
    }

    $motor_section = '';
    if (!empty($contrato_data['motor'])) {
        $motor_section = "DATOS DEL MOTOR: " . $contrato_data['motor'] . "\n";
    }

    // Preparar textos con lógica condicional
    $forma_pago_texto = !empty($contrato_data['formaPago']) ? $contrato_data['formaPago'] : "según lo acordado entre las partes";
    $lugar_entrega_texto = !empty($contrato_data['lugarEntrega']) ? "en " . $contrato_data['lugarEntrega'] : "en el lugar donde actualmente se encuentra";

    // Contenido del PDF profesional
    $pdf_content = "


                        CONTRATO DE COMPRA-VENTA DE {$vehicle_type_text}


En _________________, a {$fecha_actual}

REUNIDOS

De una parte:
D./Dña. {$contrato_data['vendedorNombre']}, mayor de edad, con DNI/NIE número {$contrato_data['vendedorDni']},
con domicilio en {$contrato_data['vendedorDireccion']}, teléfono {$contrato_data['vendedorTelefono']},
email {$contrato_data['vendedorEmail']}, en calidad de VENDEDOR.

De otra parte:
D./Dña. {$contrato_data['compradorNombre']}, mayor de edad, con DNI/NIE número {$contrato_data['compradorDni']},
con domicilio en {$contrato_data['compradorDireccion']}, teléfono {$contrato_data['compradorTelefono']},
email {$contrato_data['compradorEmail']}, en calidad de COMPRADOR.

Ambas partes se reconocen mutuamente la capacidad legal necesaria para contratar y obligarse, y

                                        EXPONEN

PRIMERO.- Que el VENDEDOR es propietario legítimo de la embarcación que a continuación se describe:

    • Tipo de embarcación: {$vehicle_type_text}
    • Marca: {$contrato_data['marca']}
    • Modelo: {$contrato_data['modelo']}" .
    (!empty($contrato_data['ano']) ? "
    • Año de fabricación: {$contrato_data['ano']}" : "") . "
    • Número de bastidor/casco: {$contrato_data['bastidor']}" .
    (!empty($contrato_data['matricula']) ? "
    • Matrícula/Folio: {$contrato_data['matricula']}" : "") . "

{$motor_section}

SEGUNDO.- Que el VENDEDOR tiene la libre disposición de dicha embarcación, encontrándose libre
de cargas, gravámenes, embargos o cualquier limitación de dominio.

TERCEIRO.- Que ambas partes han convenido la transmisión de la propiedad de la citada embarcación
en las condiciones que se establecen en las siguientes:

                                        ESTIPULACIONES

PRIMERA.- OBJETO DEL CONTRATO
El VENDEDOR transmite al COMPRADOR, que acepta, la plena propiedad de la embarcación descrita
en la exposición primera.

SEGUNDA.- PRECIO
El precio pactado por la transmisión de la embarcación asciende a la cantidad de {$precio_formatted}
({$precio_formatted}).

TERCERA.- FORMA DE PAGO
El pago del precio se efectuará {$forma_pago_texto}.

CUARTA.- ENTREGA
La entrega de la embarcación se efectuará {$lugar_entrega_texto},
junto con toda la documentación necesaria para la tramitación del cambio de titularidad.

QUINTA.- GASTOS
Los gastos derivados de la formalización del presente contrato y del cambio de titularidad
serán por cuenta de quien corresponda según la legislación vigente, salvo pacto en contrario.

{$clausulas_text}

SEXTA.- LEGISLACIÓN APLICABLE
El presente contrato se rige por la legislación española vigente en materia de compraventa
y navegación marítima.

En prueba de conformidad, ambas partes firman el presente contrato por duplicado y a un solo
efecto, en el lugar y fecha al principio indicados.


        EL VENDEDOR                                    EL COMPRADOR




________________________                        ________________________
{$contrato_data['vendedorNombre']}                            {$contrato_data['compradorNombre']}
DNI: {$contrato_data['vendedorDni']}                                     DNI: {$contrato_data['compradorDni']}




━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Documento generado automáticamente por Tramitfy.es - Especialistas en tramitación náutica
Este contrato es una herramienta de apoyo. Se recomienda revisión legal profesional.
";

    // TEST DE CONFIGURACIÓN DE EMAIL
    error_log("Tramitfy: === INICIANDO TESTS DE EMAIL ===");

    // Verificar configuración básica de WordPress
    $admin_email = get_option('admin_email');
    error_log("Tramitfy: Admin email configurado: " . $admin_email);

    // Verificar si mail() de PHP funciona
    $php_mail_test = function_exists('mail');
    error_log("Tramitfy: Función mail() de PHP disponible: " . ($php_mail_test ? 'SÍ' : 'NO'));

    // TEST BÁSICO DE EMAIL
    error_log("Tramitfy: === TEST BÁSICO DE EMAIL ===");
    $test_email_sent = wp_mail($email, "Test de Email - Tramitfy", "Este es un email de prueba para verificar que wp_mail funciona correctamente.", array('Content-Type: text/html; charset=UTF-8'));
    error_log("Tramitfy: Test email result: " . ($test_email_sent ? 'SUCCESS' : 'FAILED'));

    // Verificar configuración SMTP y servidor
    error_log("Tramitfy: === CONFIGURACIÓN DE EMAIL ===");
    error_log("Tramitfy: Servidor: " . $_SERVER['HTTP_HOST']);
    error_log("Tramitfy: PHP sendmail_path: " . ini_get('sendmail_path'));
    error_log("Tramitfy: SMTP definido: " . (defined('SMTP_HOST') ? 'SÍ (' . SMTP_HOST . ')' : 'NO'));

    // Plugin SMTP activo
    $smtp_plugins = array('wp-mail-smtp/wp_mail_smtp.php', 'easy-wp-smtp/easy-wp-smtp.php', 'post-smtp/postman-smtp.php');
    $active_smtp_plugin = 'Ninguno';
    foreach ($smtp_plugins as $plugin) {
        if (is_plugin_active($plugin)) {
            $active_smtp_plugin = $plugin;
            break;
        }
    }
    error_log("Tramitfy: Plugin SMTP activo: " . $active_smtp_plugin);

    global $phpmailer;
    if (isset($phpmailer)) {
        error_log("Tramitfy: PHPMailer disponible: SÍ");
        error_log("Tramitfy: Mail method: " . (isset($phpmailer->Mailer) ? $phpmailer->Mailer : 'No definido'));
    } else {
        error_log("Tramitfy: PHPMailer disponible: NO");
    }

    // Usar directamente wp_mail
    error_log("Tramitfy: Usando wp_mail para PDF");

    // Inicializar variables para el PDF
    $file_path = '';

    try {
        error_log("Tramitfy: === INICIANDO GENERACIÓN DE PDF CON FPDF ===");

        // Usar FPDF que ya está disponible en WordPress
        require_once get_template_directory() . '/vendor/fpdf/fpdf.php';
        error_log("Tramitfy: FPDF incluido correctamente");

        // Validar datos requeridos
        if (empty($contrato_data['vendedorNombre']) || empty($contrato_data['compradorNombre'])) {
            throw new Exception("Faltan datos requeridos del contrato");
        }

        // Crear nueva instancia de FPDF
        $pdf = new FPDF('P', 'mm', 'A4');
        $pdf->AddPage();
        error_log("Tramitfy: Instancia FPDF creada y página añadida");

        // Log detallado de los datos del contrato
        error_log("Tramitfy: Datos del contrato para PDF:");
        error_log("Tramitfy: - Marca: " . $contrato_data['marca']);
        error_log("Tramitfy: - Modelo: " . $contrato_data['modelo']);
        error_log("Tramitfy: - Vendedor: " . $contrato_data['vendedorNombre']);
        error_log("Tramitfy: - Comprador: " . $contrato_data['compradorNombre']);

        // Definir colores corporativos
        $primary_color = array(1, 109, 134); // #016d86
        $text_color = array(51, 51, 51); // #333333

        // ENCABEZADO
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->SetTextColor($primary_color[0], $primary_color[1], $primary_color[2]);
        $pdf->Cell(0, 12, utf8_decode('CONTRATO DE COMPRAVENTA'), 0, 1, 'C');
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->Cell(0, 10, utf8_decode('DE EMBARCACIÓN DE RECREO'), 0, 1, 'C');
        $pdf->Ln(8);

        $pdf->SetFont('Arial', '', 10);
        $pdf->SetTextColor($text_color[0], $text_color[1], $text_color[2]);
        $pdf->Cell(0, 6, utf8_decode('En _________________, a ' . $fecha_actual), 0, 1, 'C');
        $pdf->Ln(8);

        // COMPARECIENTES
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->SetTextColor($primary_color[0], $primary_color[1], $primary_color[2]);
        $pdf->Cell(0, 8, 'COMPARECIENTES', 0, 1, 'L');
        $pdf->Ln(3);

        $pdf->SetFont('Arial', '', 10);
        $pdf->SetTextColor($text_color[0], $text_color[1], $text_color[2]);
        $texto_comparecientes = 'De una parte, Don/Doña ' . $contrato_data['vendedorNombre'] . ', mayor de edad, con documento nacional de identidad número ' . $contrato_data['vendedorDni'] . ', con domicilio en ' . $contrato_data['vendedorDireccion'] . ', teléfono ' . $contrato_data['vendedorTelefono'] . ' y correo electrónico ' . $contrato_data['vendedorEmail'] . ', quien en adelante se denominará EL VENDEDOR.

Y de otra parte, Don/Doña ' . $contrato_data['compradorNombre'] . ', mayor de edad, con documento nacional de identidad número ' . $contrato_data['compradorDni'] . ', con domicilio en ' . $contrato_data['compradorDireccion'] . ', teléfono ' . $contrato_data['compradorTelefono'] . ' y correo electrónico ' . $contrato_data['compradorEmail'] . ', quien en adelante se denominará EL COMPRADOR.

Ambas partes se reconocen mutuamente la capacidad jurídica necesaria para otorgar el presente contrato y, al efecto,';

        $pdf->MultiCell(0, 5, utf8_decode($texto_comparecientes), 0, 'J');
        $pdf->Ln(5);

        // EXPONEN
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->SetTextColor($primary_color[0], $primary_color[1], $primary_color[2]);
        $pdf->Cell(0, 8, 'EXPONEN', 0, 1, 'L');
        $pdf->Ln(3);

        $pdf->SetFont('Arial', '', 10);
        $pdf->SetTextColor($text_color[0], $text_color[1], $text_color[2]);

        // Construir descripción técnica completa
        $descripcion_tecnica = '';
        if (!empty($contrato_data['eslora'])) $descripcion_tecnica .= 'eslora ' . $contrato_data['eslora'] . ' metros, ';
        if (!empty($contrato_data['manga'])) $descripcion_tecnica .= 'manga ' . $contrato_data['manga'] . ' metros, ';
        if (!empty($contrato_data['puntal'])) $descripcion_tecnica .= 'puntal ' . $contrato_data['puntal'] . ' metros, ';
        if (!empty($contrato_data['materialCasco'])) $descripcion_tecnica .= 'casco de ' . strtolower($contrato_data['materialCasco']) . ', ';
        if (!empty($contrato_data['colorCasco'])) $descripcion_tecnica .= 'color ' . strtolower($contrato_data['colorCasco']) . ', ';
        if (!empty($contrato_data['tipoMotor'])) $descripcion_tecnica .= 'motor ' . strtolower($contrato_data['tipoMotor']) . ' ';
        if (!empty($contrato_data['potencia'])) $descripcion_tecnica .= 'de ' . $contrato_data['potencia'] . ' CV, ';
        if (!empty($contrato_data['combustible'])) $descripcion_tecnica .= 'combustible ' . strtolower($contrato_data['combustible']) . ', ';
        if (!empty($contrato_data['numeroMotores'])) $descripcion_tecnica .= $contrato_data['numeroMotores'] . ' motor(es), ';
        if (!empty($contrato_data['horasMotor'])) $descripcion_tecnica .= 'con ' . $contrato_data['horasMotor'] . ' horas de motor';

        // Limpiar última coma
        $descripcion_tecnica = rtrim($descripcion_tecnica, ', ');

        $matricula_texto = !empty($contrato_data['matricula']) ?
            ', con matrícula/folio número ' . $contrato_data['matricula'] :
            ', pendiente de asignación de matrícula';

        $texto_exponen = 'PRIMERO.- Que EL VENDEDOR es propietario legítimo de una embarcación de recreo de las siguientes características técnicas: marca ' . $contrato_data['marca'] . ', modelo ' . $contrato_data['modelo'];

        if (!empty($contrato_data['ano'])) {
            $texto_exponen .= ', año de fabricación ' . $contrato_data['ano'];
        }

        if (!empty($contrato_data['bastidor'])) {
            $texto_exponen .= ', número de bastidor/casco ' . $contrato_data['bastidor'];
        }

        $texto_exponen .= $matricula_texto;

        if (!empty($descripcion_tecnica)) {
            $texto_exponen .= ', ' . $descripcion_tecnica;
        }

        if (!empty($contrato_data['estadoConservacion'])) {
            $texto_exponen .= ', en estado de conservación ' . strtolower($contrato_data['estadoConservacion']);
        }

        $texto_exponen .= '.

SEGUNDO.- Que dicha embarcación se encuentra libre de cargas, gravámenes, embargos o cualquier limitación de dominio, y que EL VENDEDOR dispone de plena capacidad para su enajenación.

TERCERO.- Que EL COMPRADOR conoce perfectamente la embarcación objeto de la presente compraventa, habiéndola examinado a su entera satisfacción, y manifiesta su conformidad tanto con su estado de conservación como con sus características técnicas.

CUARTO.- Que ambas partes tienen interés en formalizar el presente contrato de compraventa en los términos y condiciones que se establecen en las siguientes';

        $pdf->MultiCell(0, 5, utf8_decode($texto_exponen), 0, 'J');
        $pdf->Ln(5);

        // Verificar si hay nueva página necesaria
        if ($pdf->GetY() > 240) {
            $pdf->AddPage();
        }

        // ESTIPULACIONES
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->SetTextColor($primary_color[0], $primary_color[1], $primary_color[2]);
        $pdf->Cell(0, 8, 'ESTIPULACIONES', 0, 1, 'L');
        $pdf->Ln(3);

        $pdf->SetFont('Arial', '', 10);
        $pdf->SetTextColor($text_color[0], $text_color[1], $text_color[2]);

        // Construir forma de pago dinámicamente
        $forma_pago_texto = !empty($contrato_data['formaPago']) ? $contrato_data['formaPago'] : 'al contado';

        $clausulas = 'PRIMERA.- OBJETO Y PRECIO: EL VENDEDOR transmite en pleno dominio a EL COMPRADOR la embarcación descrita en los antecedentes, por el precio de ' . $precio_formatted . ' (EUROS), que EL COMPRADOR pagará ' . $forma_pago_texto . '.

SEGUNDA.- ESTADO DE LA EMBARCACIÓN: EL VENDEDOR garantiza que la embarcación se encuentra en perfecto estado de navegabilidad y cumple con toda la normativa vigente aplicable. EL COMPRADOR declara conocer el estado actual de la embarcación tras haberla examinado detenidamente.

TERCERA.- DOCUMENTACIÓN: EL VENDEDOR se compromete a entregar toda la documentación de la embarcación en regla, incluyendo certificado de navegabilidad, seguro, ITV náutica si procede, y cuanta documentación sea necesaria para el uso y disfrute de la embarcación.

CUARTA.- TRANSMISIÓN DE RIESGOS: La transmisión de riesgos se producirá en el momento de la entrega efectiva de la embarcación, momento en que EL COMPRADOR asumirá todos los riesgos inherentes a la misma.

QUINTA.- CARGAS Y GRAVÁMENES: EL VENDEDOR garantiza que la embarcación se encuentra libre de cargas, gravámenes, embargos o cualquier limitación de dominio, respondiendo de la existencia de vicios ocultos del derecho de propiedad.

SEXTA.- OBLIGACIONES FISCALES: Cada parte asumirá las obligaciones fiscales que le correspondan según la legislación vigente. EL COMPRADOR se hace cargo del Impuesto de Transmisiones Patrimoniales y demás tributos aplicables.

SÉPTIMA.- CAMBIO DE TITULARIDAD: Ambas partes se comprometen a realizar todas las gestiones necesarias para el cambio de titularidad ante las autoridades competentes en el plazo máximo de treinta días desde la firma del presente contrato.

OCTAVA.- ENTREGA: La entrega de la embarcación se realizará en ' . (!empty($contrato_data['lugarEntrega']) ? $contrato_data['lugarEntrega'] : 'el lugar donde actualmente se encuentra') . ', corriendo por cuenta de EL COMPRADOR los gastos de traslado si los hubiere.';

        $pdf->MultiCell(0, 5, utf8_decode($clausulas), 0, 'J');

        // Verificar si necesita nueva página para más cláusulas
        if ($pdf->GetY() > 220) {
            $pdf->AddPage();
        }

        $pdf->Ln(5);

        $clausulas_adicionales = 'NOVENA.- SANEAMIENTO POR EVICCIÓN Y VICIOS OCULTOS: EL VENDEDOR responde del saneamiento por evicción y vicios ocultos en los términos establecidos en el Código Civil, con las limitaciones y exclusiones permitidas por la ley.

DÉCIMA.- RESOLUCIÓN: El incumplimiento de cualquiera de las obligaciones contraídas por las partes dará derecho a la parte cumplidora a exigir el cumplimiento o la resolución del contrato, con indemnización de daños y perjuicios en ambos casos.

UNDÉCIMA.- COMPETENCIA JUDICIAL: Para cuantas cuestiones puedan derivarse de la interpretación o cumplimiento del presente contrato, ambas partes se someten expresamente a la jurisdicción de los Juzgados y Tribunales del domicilio del demandado.

DUODÉCIMA.- PROTECCIÓN DE DATOS: Las partes se informan mutuamente que los datos personales facilitados serán tratados conforme a la normativa de protección de datos vigente, únicamente para el cumplimiento del presente contrato.

Y en prueba de conformidad con cuanto antecede, firman el presente contrato por duplicado y a un solo efecto en el lugar y fecha indicados en el encabezamiento.';

        $pdf->MultiCell(0, 5, utf8_decode($clausulas_adicionales), 0, 'J');
        $pdf->Ln(15);

        // Verificar si necesita nueva página para firmas
        if ($pdf->GetY() > 220) {
            $pdf->AddPage();
            $pdf->Ln(10);
        }

        // FIRMAS
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->SetTextColor($text_color[0], $text_color[1], $text_color[2]);
        $pdf->Cell(0, 8, 'FIRMAS', 0, 1, 'C');
        $pdf->Ln(10);

        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(95, 6, utf8_decode('EL VENDEDOR'), 0, 0, 'C');
        $pdf->Cell(95, 6, utf8_decode('EL COMPRADOR'), 0, 1, 'C');
        $pdf->Ln(25);

        // Líneas para firmas
        $pdf->Line(30, $pdf->GetY(), 90, $pdf->GetY());
        $pdf->Line(125, $pdf->GetY(), 185, $pdf->GetY());
        $pdf->Ln(8);

        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(95, 4, utf8_decode('Fdo.: ' . $contrato_data['vendedorNombre']), 0, 0, 'C');
        $pdf->Cell(95, 4, utf8_decode('Fdo.: ' . $contrato_data['compradorNombre']), 0, 1, 'C');
        $pdf->Cell(95, 4, utf8_decode('DNI: ' . $contrato_data['vendedorDni']), 0, 0, 'C');
        $pdf->Cell(95, 4, utf8_decode('DNI: ' . $contrato_data['compradorDni']), 0, 1, 'C');

        // Información de contacto
        $pdf->Ln(3);
        $pdf->SetFont('Arial', '', 8);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->Cell(95, 3, utf8_decode('Telf: ' . $contrato_data['vendedorTelefono']), 0, 0, 'C');
        $pdf->Cell(95, 3, utf8_decode('Telf: ' . $contrato_data['compradorTelefono']), 0, 1, 'C');
        $pdf->Cell(95, 3, utf8_decode($contrato_data['vendedorEmail']), 0, 0, 'C');
        $pdf->Cell(95, 3, utf8_decode($contrato_data['compradorEmail']), 0, 1, 'C');

        // Posicionar al final de la página
        $pdf->SetY(270);

        // PIE DE PÁGINA
        $pdf->SetFont('Arial', '', 8);
        $pdf->SetTextColor(128, 128, 128);
        $pdf->Cell(0, 4, utf8_decode('Este documento ha sido generado electrónicamente por Tramitfy.es el ' . $fecha_actual), 0, 1, 'C');
        $pdf->Cell(0, 4, utf8_decode('Herramienta de apoyo para la formalización - Se recomienda asesoramiento legal profesional'), 0, 1, 'C');

        $pdf->SetFont('Arial', '', 7);
        $pdf->SetTextColor(150, 150, 150);
        $pdf->Cell(0, 3, utf8_decode('Tramitfy.es - Especialistas en trámites náuticos | info@tramitfy.es'), 0, 1, 'C');

        error_log("Tramitfy: Contenido del PDF creado correctamente");

        // Crear archivo PDF temporal
        error_log("Tramitfy: === GUARDANDO ARCHIVO PDF ===");
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['path'];
        $filename = 'contrato-compraventa-' . sanitize_file_name($contrato_data['marca'] . '-' . $contrato_data['modelo']) . '-' . time() . '.pdf';
        $file_path = $temp_dir . '/' . $filename;
        error_log("Tramitfy: Ruta de destino: " . $file_path);
        error_log("Tramitfy: Directorio de destino existe: " . (is_dir($temp_dir) ? 'SÍ' : 'NO'));
        error_log("Tramitfy: Directorio escribible: " . (is_writable($temp_dir) ? 'SÍ' : 'NO'));

        // Generar PDF y guardarlo
        error_log("Tramitfy: Generando contenido del PDF...");
        $pdf_content = $pdf->Output('S');
        error_log("Tramitfy: Contenido PDF generado - Tamaño: " . strlen($pdf_content) . " bytes");

        error_log("Tramitfy: Escribiendo archivo PDF...");
        $write_result = file_put_contents($file_path, $pdf_content);
        error_log("Tramitfy: file_put_contents result: " . ($write_result ? $write_result . ' bytes escritos' : 'FALLÓ'));

        if (!$write_result || !file_exists($file_path)) {
            throw new Exception("No se pudo crear el archivo PDF");
        }

        $file_size = filesize($file_path);
        error_log("Tramitfy: ✅ PDF creado exitosamente - Tamaño: " . $file_size . " bytes");

    } catch (Exception $e) {
        error_log("Tramitfy: ERROR al generar PDF: " . $e->getMessage());
        wp_send_json_error('Error al generar el PDF: ' . $e->getMessage());
        return;
    }

    // Preparar email con el contrato como adjunto
    $subject = "Tu Contrato de Compra-Venta - {$vehicle_type_text} {$contrato_data['marca']} {$contrato_data['modelo']}";

    // Headers para email HTML atractivo
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: Tramitfy <noreply@tramitfy.es>',
        'Reply-To: info@tramitfy.es'
    );

    // INTENTAR ENVÍO DE EMAIL
    error_log("Tramitfy: === INICIANDO ENVÍO DE EMAIL ===");
    error_log("Tramitfy: Enviando email HTML atractivo");
    error_log("Tramitfy: Destinatario: " . $email);
    error_log("Tramitfy: Asunto: " . $subject);

    // Crear email HTML profesional y formal
    $email_html = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Documentación Legal - Contrato de Compra-Venta de Embarcación</title>
    </head>
    <body style='margin: 0; padding: 0; font-family: Georgia, Times, serif; background-color: #f8f9fa; line-height: 1.6;'>
        <div style='max-width: 800px; margin: 0 auto; background-color: #ffffff; border: 1px solid #dee2e6;'>

            <!-- Header Profesional -->
            <div style='background-color: #1a365d; padding: 25px 30px; border-bottom: 3px solid #2c5282;'>
                <h1 style='color: #ffffff; margin: 0; font-size: 24px; font-weight: normal; text-align: center; letter-spacing: 1px;'>
                    CONTRATO DE COMPRA-VENTA DE EMBARCACIÓN
                </h1>
                <p style='color: #cbd5e0; margin: 8px 0 0 0; font-size: 14px; text-align: center; font-style: italic;'>
                    Documentación legal generada por Tramitfy.es
                </p>
            </div>

            <!-- Identificación del Documento -->
            <div style='padding: 25px 30px; background-color: #f7fafc; border-bottom: 1px solid #e2e8f0;'>
                <h2 style='color: #2d3748; margin: 0 0 15px 0; font-size: 18px; font-weight: bold; text-transform: uppercase;'>
                    Identificación de la Operación Jurídica
                </h2>
                <p style='color: #4a5568; margin: 0; font-size: 15px;'>
                    Se adjunta a la presente comunicación el contrato de compra-venta de embarcación de recreo
                    formalizado entre las partes que se detallan a continuación, elaborado conforme a la normativa
                    vigente en materia de transmisión de embarcaciones de recreo.
                </p>
            </div>

            <!-- Datos Técnicos de la Embarcación -->
            <div style='padding: 25px 30px;'>
                <h3 style='color: #2d3748; margin: 0 0 20px 0; font-size: 16px; font-weight: bold; border-bottom: 2px solid #4299e1; padding-bottom: 8px;'>
                    I. IDENTIFICACIÓN DE LA EMBARCACIÓN
                </h3>

                <table style='width: 100%; border-collapse: collapse; font-size: 14px;'>
                    <tr style='border-bottom: 1px solid #e2e8f0;'>
                        <td style='padding: 8px 0; font-weight: bold; color: #4a5568; width: 30%;'>Marca:</td>
                        <td style='padding: 8px 0; color: #2d3748;'>{$contrato_data['marca']}</td>
                    </tr>
                    <tr style='border-bottom: 1px solid #e2e8f0;'>
                        <td style='padding: 8px 0; font-weight: bold; color: #4a5568;'>Modelo:</td>
                        <td style='padding: 8px 0; color: #2d3748;'>{$contrato_data['modelo']}</td>
                    </tr>
                    " . (!empty($contrato_data['ano']) ? "
                    <tr style='border-bottom: 1px solid #e2e8f0;'>
                        <td style='padding: 8px 0; font-weight: bold; color: #4a5568;'>Año de fabricación:</td>
                        <td style='padding: 8px 0; color: #2d3748;'>{$contrato_data['ano']}</td>
                    </tr>" : "") . "
                    " . (!empty($contrato_data['bastidor']) ? "
                    <tr style='border-bottom: 1px solid #e2e8f0;'>
                        <td style='padding: 8px 0; font-weight: bold; color: #4a5568;'>Número de bastidor/casco:</td>
                        <td style='padding: 8px 0; color: #2d3748;'>{$contrato_data['bastidor']}</td>
                    </tr>" : "") . "
                    <tr style='border-bottom: 1px solid #e2e8f0;'>
                        <td style='padding: 8px 0; font-weight: bold; color: #4a5568;'>Matrícula/Folio:</td>
                        <td style='padding: 8px 0; color: #2d3748;'>{$contrato_data['matricula']}</td>
                    </tr>
                </table>
            </div>

            <!-- Partes Contratantes -->
            <div style='padding: 25px 30px; background-color: #f7fafc;'>
                <h3 style='color: #2d3748; margin: 0 0 20px 0; font-size: 16px; font-weight: bold; border-bottom: 2px solid #4299e1; padding-bottom: 8px;'>
                    II. PARTES CONTRATANTES
                </h3>

                <div style='display: flex; flex-wrap: wrap; gap: 30px;'>
                    <div style='flex: 1; min-width: 300px; background: white; padding: 20px; border-left: 4px solid #ed8936;'>
                        <h4 style='color: #c05621; margin: 0 0 12px 0; font-size: 14px; font-weight: bold; text-transform: uppercase;'>
                            Parte Vendedora
                        </h4>
                        <table style='width: 100%; font-size: 14px;'>
                            <tr><td style='padding: 4px 0; font-weight: bold; color: #4a5568; width: 35%;'>Nombre:</td><td style='padding: 4px 0; color: #2d3748;'>{$contrato_data['vendedorNombre']}</td></tr>
                            <tr><td style='padding: 4px 0; font-weight: bold; color: #4a5568;'>DNI/NIE:</td><td style='padding: 4px 0; color: #2d3748;'>{$contrato_data['vendedorDni']}</td></tr>
                            <tr><td style='padding: 4px 0; font-weight: bold; color: #4a5568;'>Correo:</td><td style='padding: 4px 0; color: #2d3748;'>{$contrato_data['vendedorEmail']}</td></tr>
                        </table>
                    </div>

                    <div style='flex: 1; min-width: 300px; background: white; padding: 20px; border-left: 4px solid #9f7aea;'>
                        <h4 style='color: #6b46c1; margin: 0 0 12px 0; font-size: 14px; font-weight: bold; text-transform: uppercase;'>
                            Parte Compradora
                        </h4>
                        <table style='width: 100%; font-size: 14px;'>
                            <tr><td style='padding: 4px 0; font-weight: bold; color: #4a5568; width: 35%;'>Nombre:</td><td style='padding: 4px 0; color: #2d3748;'>{$contrato_data['compradorNombre']}</td></tr>
                            <tr><td style='padding: 4px 0; font-weight: bold; color: #4a5568;'>DNI/NIE:</td><td style='padding: 4px 0; color: #2d3748;'>{$contrato_data['compradorDni']}</td></tr>
                            <tr><td style='padding: 4px 0; font-weight: bold; color: #4a5568;'>Correo:</td><td style='padding: 4px 0; color: #2d3748;'>{$contrato_data['compradorEmail']}</td></tr>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Condiciones Económicas -->
            <div style='padding: 25px 30px;'>
                <h3 style='color: #2d3748; margin: 0 0 20px 0; font-size: 16px; font-weight: bold; border-bottom: 2px solid #4299e1; padding-bottom: 8px;'>
                    III. CONDICIONES ECONÓMICAS Y DE ENTREGA
                </h3>

                <div style='background: #f0fff4; border-left: 4px solid #48bb78; padding: 20px; margin-bottom: 15px;'>
                    <table style='width: 100%; font-size: 14px;'>
                        <tr>
                            <td style='padding: 8px 0; font-weight: bold; color: #2f855a; width: 30%;'>Precio de transmisión:</td>
                            <td style='padding: 8px 0; color: #2d3748; font-size: 18px; font-weight: bold;'>{$precio_formatted}</td>
                        </tr>
                        " . (!empty($contrato_data['formaPago']) ? "
                        <tr>
                            <td style='padding: 8px 0; font-weight: bold; color: #2f855a;'>Modalidad de pago:</td>
                            <td style='padding: 8px 0; color: #2d3748;'>{$contrato_data['formaPago']}</td>
                        </tr>" : "") . "
                        " . (!empty($contrato_data['lugarEntrega']) ? "
                        <tr>
                            <td style='padding: 8px 0; font-weight: bold; color: #2f855a;'>Lugar de entrega:</td>
                            <td style='padding: 8px 0; color: #2d3748;'>{$contrato_data['lugarEntrega']}</td>
                        </tr>" : "") . "
                    </table>
                </div>
            </div>

            <!-- Documentación Adjunta -->
            <div style='background: #e6f3ff; border: 1px solid #4299e1; padding: 20px; margin: 20px 30px; border-radius: 4px;'>
                <h3 style='color: #2b6cb0; margin: 0 0 12px 0; font-size: 16px; font-weight: bold;'>DOCUMENTACIÓN LEGAL ADJUNTA</h3>
                <p style='color: #2c5282; margin: 0; font-size: 15px; line-height: 1.5;'>
                    <strong>Contrato de Compra-Venta en formato PDF</strong><br>
                    El documento adjunto contiene el contrato completo con todas las cláusulas legales necesarias
                    para la transmisión de la embarcación. Debe ser firmado por ambas partes en presencia de
                    testigos o ante notario según corresponda.
                </p>
                <p style='color: #2a69ac; margin: 10px 0 0 0; font-size: 13px; font-style: italic;'>
                    Se recomienda conservar una copia del presente email como justificante de la transacción.
                </p>
            </div>

            <!-- Advertencias Legales -->
            <div style='padding: 25px 30px; background-color: #fffbeb; border-top: 1px solid #f59e0b;'>
                <h3 style='color: #92400e; margin: 0 0 15px 0; font-size: 14px; font-weight: bold; text-transform: uppercase;'>
                    Obligaciones Posteriores a la Firma
                </h3>
                <ul style='color: #78350f; margin: 0; padding-left: 20px; font-size: 13px; line-height: 1.4;'>
                    <li style='margin-bottom: 6px;'>Cambio de titularidad ante la Capitanía Marítima correspondiente</li>
                    <li style='margin-bottom: 6px;'>Liquidación del Impuesto de Transmisiones Patrimoniales</li>
                    <li style='margin-bottom: 6px;'>Actualización del seguro de la embarcación</li>
                    <li style='margin-bottom: 6px;'>Comunicación del cambio al puerto base o marina</li>
                </ul>
            </div>

            <!-- Footer Profesional -->
            <div style='background-color: #1a365d; padding: 20px 30px; text-align: center;'>
                <p style='color: #cbd5e0; margin: 0; font-size: 13px;'>
                    © 2024 Tramitfy.es - Servicios Jurídicos Náuticos<br>
                    Correo: info@tramitfy.es | Web: <a href='https://tramitfy.es' style='color: #90cdf4; text-decoration: none;'>tramitfy.es</a>
                </p>
                <p style='color: #a0aec0; margin: 10px 0 0 0; font-size: 11px; font-style: italic;'>
                    Documento generado automáticamente. Conserve este correo como justificante de la operación.
                </p>
            </div>
        </div>
    </body>
    </html>
    ";

    // Verificar que el archivo existe antes de adjuntarlo
    if (!file_exists($file_path)) {
        error_log("Tramitfy: ERROR CRÍTICO - El archivo PDF no existe en el momento del envío: " . $file_path);
        wp_send_json_error('Error crítico: el archivo PDF no se encontró');
        return;
    }

    $file_size_check = filesize($file_path);
    error_log("Tramitfy: Verificación final - Archivo existe: SÍ, Tamaño: " . $file_size_check . " bytes");

    // Adjuntar el PDF al email
    $attachments = array($file_path);
    error_log("Tramitfy: Adjuntos preparados: " . print_r($attachments, true));
    error_log("Tramitfy: Verificando archivo adjunto - Existe: " . (file_exists($file_path) ? 'SÍ' : 'NO'));
    error_log("Tramitfy: Ruta completa del adjunto: " . $file_path);

    // Envío de email HTML con PDF adjunto
    error_log("Tramitfy: Enviando email HTML con PDF adjunto...");
    error_log("Tramitfy: Headers: " . print_r($headers, true));

    // Hook para capturar errores de PHPMailer
    add_action('wp_mail_failed', function($wp_error) {
        error_log("Tramitfy: wp_mail FAILED - " . $wp_error->get_error_message());
    });

    $sent = wp_mail($email, $subject, $email_html, $headers, $attachments);
    error_log("Tramitfy: Email con PDF result: " . ($sent ? 'SUCCESS' : 'FAILED'));

    if (!$sent) {
        global $phpmailer;
        if (isset($phpmailer) && !empty($phpmailer->ErrorInfo)) {
            error_log("Tramitfy: PHPMailer Error: " . $phpmailer->ErrorInfo);
        }

        // Intentar envío sin adjunto como fallback
        error_log("Tramitfy: Reintentando envío sin adjunto...");
        $sent_fallback = wp_mail($email, $subject, $email_html, $headers);
        error_log("Tramitfy: Email fallback result: " . ($sent_fallback ? 'SUCCESS' : 'FAILED'));

        if ($sent_fallback) {
            $sent = true; // Marcar como enviado si el fallback funcionó
            error_log("Tramitfy: Email enviado exitosamente sin adjunto (fallback)");
        }
    }

    // Limpiar archivo temporal después del envío
    if (file_exists($file_path)) {
        unlink($file_path);
    }

    // RESULTADO FINAL
    error_log("Tramitfy: === RESULTADO FINAL ===");

    if ($sent) {
        // Log de éxito completo
        error_log("Tramitfy: ✅ EMAIL ENVIADO EXITOSAMENTE");
        error_log("Tramitfy: Destinatario: " . $email);
        error_log("Tramitfy: Archivo adjunto: " . ($file_path ? 'SÍ' : 'NO'));
        error_log("Tramitfy: === TRAMITFY AUTOLOG END - SUCCESS ===");

        // Log personalizado
        $log_entry = date('Y-m-d H:i:s') . " ✅ EMAIL ENVIADO EXITOSAMENTE a: " . $email . "\n";
        file_put_contents($custom_log, $log_entry, FILE_APPEND | LOCK_EX);

        // ENVIAR EMAIL DE NOTIFICACIÓN INTERNA
        $notification_email = 'ipmgroup24@gmail.com';
        $notification_subject = 'Nuevo Contrato de Compra-Venta Generado - Tramitfy';

        $notification_html = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Notificación Interna - Nuevo Contrato</title>
        </head>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f8f9fa;'>
                <div style='background-color: #1a365d; color: white; padding: 20px; text-align: center;'>
                    <h1 style='margin: 0; font-size: 20px;'>NUEVO CONTRATO GENERADO</h1>
                    <p style='margin: 5px 0 0 0; font-size: 14px;'>Sistema Tramitfy - Notificación Interna</p>
                </div>

                <div style='background-color: white; padding: 25px; margin-top: 0;'>
                    <h2 style='color: #2d3748; margin-top: 0;'>Detalles del Trámite</h2>

                    <table style='width: 100%; border-collapse: collapse; margin-bottom: 20px;'>
                        <tr style='border-bottom: 1px solid #e2e8f0;'>
                            <td style='padding: 8px 0; font-weight: bold; width: 30%;'>Fecha y hora:</td>
                            <td style='padding: 8px 0;'>" . date('d/m/Y H:i:s') . "</td>
                        </tr>
                        <tr style='border-bottom: 1px solid #e2e8f0;'>
                            <td style='padding: 8px 0; font-weight: bold;'>Email destinatario:</td>
                            <td style='padding: 8px 0;'>{$email}</td>
                        </tr>
                    </table>

                    <h3 style='color: #4299e1; margin-bottom: 15px;'>Datos de la Embarcación</h3>
                    <table style='width: 100%; border-collapse: collapse; margin-bottom: 20px; background-color: #f7fafc;'>
                        <tr><td style='padding: 8px; border: 1px solid #e2e8f0; font-weight: bold;'>Marca:</td><td style='padding: 8px; border: 1px solid #e2e8f0;'>{$contrato_data['marca']}</td></tr>
                        <tr><td style='padding: 8px; border: 1px solid #e2e8f0; font-weight: bold;'>Modelo:</td><td style='padding: 8px; border: 1px solid #e2e8f0;'>{$contrato_data['modelo']}</td></tr>
                        " . (!empty($contrato_data['ano']) ? "<tr><td style='padding: 8px; border: 1px solid #e2e8f0; font-weight: bold;'>Año:</td><td style='padding: 8px; border: 1px solid #e2e8f0;'>{$contrato_data['ano']}</td></tr>" : "") . "
                        <tr><td style='padding: 8px; border: 1px solid #e2e8f0; font-weight: bold;'>Matrícula:</td><td style='padding: 8px; border: 1px solid #e2e8f0;'>{$contrato_data['matricula']}</td></tr>
                        <tr><td style='padding: 8px; border: 1px solid #e2e8f0; font-weight: bold;'>Precio:</td><td style='padding: 8px; border: 1px solid #e2e8f0;'>{$precio_formatted}</td></tr>
                    </table>

                    <h3 style='color: #48bb78; margin-bottom: 15px;'>Partes Involucradas</h3>
                    <div style='display: flex; gap: 20px; margin-bottom: 20px;'>
                        <div style='flex: 1; background-color: #fff5f5; padding: 15px; border-left: 4px solid #f56565;'>
                            <h4 style='margin: 0 0 10px 0; color: #c53030;'>VENDEDOR</h4>
                            <p style='margin: 5px 0;'><strong>Nombre:</strong> {$contrato_data['vendedorNombre']}</p>
                            <p style='margin: 5px 0;'><strong>DNI:</strong> {$contrato_data['vendedorDni']}</p>
                            <p style='margin: 5px 0;'><strong>Email:</strong> {$contrato_data['vendedorEmail']}</p>
                            <p style='margin: 5px 0;'><strong>Teléfono:</strong> {$contrato_data['vendedorTelefono']}</p>
                        </div>

                        <div style='flex: 1; background-color: #f0fff4; padding: 15px; border-left: 4px solid #48bb78;'>
                            <h4 style='margin: 0 0 10px 0; color: #38a169;'>COMPRADOR</h4>
                            <p style='margin: 5px 0;'><strong>Nombre:</strong> {$contrato_data['compradorNombre']}</p>
                            <p style='margin: 5px 0;'><strong>DNI:</strong> {$contrato_data['compradorDni']}</p>
                            <p style='margin: 5px 0;'><strong>Email:</strong> {$contrato_data['compradorEmail']}</p>
                            <p style='margin: 5px 0;'><strong>Teléfono:</strong> {$contrato_data['compradorTelefono']}</p>
                        </div>
                    </div>

                    " . (!empty($contrato_data['formaPago']) || !empty($contrato_data['lugarEntrega']) ? "
                    <h3 style='color: #805ad5; margin-bottom: 15px;'>Condiciones Adicionales</h3>
                    <table style='width: 100%; border-collapse: collapse; background-color: #faf5ff;'>
                        " . (!empty($contrato_data['formaPago']) ? "<tr><td style='padding: 8px; border: 1px solid #e2e8f0; font-weight: bold;'>Forma de pago:</td><td style='padding: 8px; border: 1px solid #e2e8f0;'>{$contrato_data['formaPago']}</td></tr>" : "") . "
                        " . (!empty($contrato_data['lugarEntrega']) ? "<tr><td style='padding: 8px; border: 1px solid #e2e8f0; font-weight: bold;'>Lugar de entrega:</td><td style='padding: 8px; border: 1px solid #e2e8f0;'>{$contrato_data['lugarEntrega']}</td></tr>" : "") . "
                    </table>
                    " : "") . "

                    <div style='background-color: #e6fffa; border: 1px solid #81e6d9; padding: 15px; margin-top: 20px; border-radius: 4px;'>
                        <p style='margin: 0; color: #234e52; font-weight: bold;'>✓ Contrato PDF generado y enviado correctamente</p>
                        <p style='margin: 5px 0 0 0; color: #285e61; font-size: 14px;'>El cliente ha recibido su documentación legal completa.</p>
                    </div>
                </div>

                <div style='background-color: #2d3748; color: #a0aec0; padding: 15px; text-align: center; font-size: 12px;'>
                    <p style='margin: 0;'>Sistema de Notificaciones Tramitfy | " . date('Y') . "</p>
                </div>
            </div>
        </body>
        </html>
        ";

        $notification_headers = array('Content-Type: text/html; charset=UTF-8');

        // Intentar enviar notificación interna
        error_log("Tramitfy: Enviando notificación interna a: " . $notification_email);
        $notification_sent = wp_mail($notification_email, $notification_subject, $notification_html, $notification_headers);
        error_log("Tramitfy: Notificación interna result: " . ($notification_sent ? 'SUCCESS' : 'FAILED'));

        $file_url = $upload_dir['url'] . '/' . $filename;

        $webhook_data = array(
            'tipoVehiculo' => $contrato_data['tipoVehiculo'],
            'marca' => $contrato_data['marca'],
            'modelo' => $contrato_data['modelo'],
            'precio' => $contrato_data['precio'],
            'vendedorNombre' => $contrato_data['vendedorNombre'],
            'vendedorEmail' => $contrato_data['vendedorEmail'],
            'vendedorDni' => $contrato_data['vendedorDni'],
            'compradorNombre' => $contrato_data['compradorNombre'],
            'compradorEmail' => $contrato_data['compradorEmail'],
            'compradorDni' => $contrato_data['compradorDni'],
            'contractPdfUrl' => $file_url
        );

        wp_remote_post('https://46-202-128-35.sslip.io/api/herramientas/contract/webhook', array(
            'timeout' => 5,
            'headers' => array('Content-Type' => 'application/json'),
            'body' => json_encode($webhook_data),
            'blocking' => false
        ));

        error_log("Tramitfy: ====== ENVIANDO RESPUESTA ÉXITO AL JAVASCRIPT ======");
        wp_send_json_success(['message' => 'Contrato generado y enviado por email correctamente']);
    } else {
        // Log detallado del error final
        error_log("Tramitfy: ❌ FALLO EN ENVÍO DE EMAIL");
        error_log("Tramitfy: Todos los métodos fallaron");

        global $phpmailer;
        $error_info = 'Sin información específica';
        if (isset($phpmailer) && !empty($phpmailer->ErrorInfo)) {
            $error_info = $phpmailer->ErrorInfo;
        }

        error_log("Tramitfy: PHPMailer final error: " . $error_info);

        // Verificar configuración del servidor
        $smtp_configured = defined('SMTP_HOST') || get_option('smtp_host');
        error_log("Tramitfy: SMTP configurado: " . ($smtp_configured ? 'SÍ' : 'NO'));

        error_log("Tramitfy: === TRAMITFY AUTOLOG END - ERROR ===");

        // Log personalizado del error
        $log_entry = date('Y-m-d H:i:s') . " ❌ FALLO EMAIL: " . $error_info . "\n";
        file_put_contents($custom_log, $log_entry, FILE_APPEND | LOCK_EX);

        error_log("Tramitfy: ====== ENVIANDO RESPUESTA ERROR AL JAVASCRIPT ======");
        wp_send_json_error('Error al enviar el contrato por email. Problema de configuración del servidor de correo.');
    }

    } catch (Exception $e) {
        error_log("Tramitfy: ❌❌❌ ERROR FATAL CAPTURADO: " . $e->getMessage());
        error_log("Tramitfy: Stack trace: " . $e->getTraceAsString());

        $log_entry = date('Y-m-d H:i:s') . " ❌ ERROR FATAL: " . $e->getMessage() . "\n";
        file_put_contents($custom_log, $log_entry, FILE_APPEND | LOCK_EX);

        error_log("Tramitfy: ====== ENVIANDO RESPUESTA ERROR FATAL AL JAVASCRIPT ======");
        wp_send_json_error('Error fatal del servidor: ' . $e->getMessage());
    } catch (Error $e) {
        error_log("Tramitfy: ❌❌❌ PHP ERROR CAPTURADO: " . $e->getMessage());
        error_log("Tramitfy: Stack trace: " . $e->getTraceAsString());

        $log_entry = date('Y-m-d H:i:s') . " ❌ PHP ERROR: " . $e->getMessage() . "\n";
        file_put_contents($custom_log, $log_entry, FILE_APPEND | LOCK_EX);

        error_log("Tramitfy: ====== ENVIANDO RESPUESTA ERROR PHP AL JAVASCRIPT ======");
        wp_send_json_error('Error de PHP: ' . $e->getMessage());
    }

    // Log final de la función
    error_log("Tramitfy: === FUNCIÓN generar_y_enviar_contrato_pdf() TERMINADA ===");
}

add_action('wp_ajax_generar_y_enviar_contrato_pdf', 'generar_y_enviar_contrato_pdf');
add_action('wp_ajax_nopriv_generar_y_enviar_contrato_pdf', 'generar_y_enviar_contrato_pdf');

// Test simple para verificar que el archivo se carga
add_action('wp_loaded', function() {
    $test_log = ABSPATH . 'wp-content/tramitfy-debug.log';
    $log_entry = date('Y-m-d H:i:s') . " TRAMITFY: Archivo PHP cargado correctamente\n";
    file_put_contents($test_log, $log_entry, FILE_APPEND | LOCK_EX);
});

// Función de test AJAX simple
function tramitfy_test_ajax() {
    $test_log = ABSPATH . 'wp-content/tramitfy-debug.log';
    $log_entry = date('Y-m-d H:i:s') . " TRAMITFY: Función AJAX de test ejecutada\n";
    file_put_contents($test_log, $log_entry, FILE_APPEND | LOCK_EX);

    wp_send_json_success(['message' => 'Test AJAX funcionando']);
}

add_action('wp_ajax_tramitfy_test', 'tramitfy_test_ajax');
add_action('wp_ajax_nopriv_tramitfy_test', 'tramitfy_test_ajax');

// Función de test de email
function tramitfy_test_email() {
    $test_email = isset($_POST['email']) ? sanitize_email($_POST['email']) : 'joanpinyol@hotmail.es';

    $test_log = ABSPATH . 'wp-content/tramitfy-debug.log';
    $log_entry = date('Y-m-d H:i:s') . " TRAMITFY: Test email solicitado para: " . $test_email . "\n";
    file_put_contents($test_log, $log_entry, FILE_APPEND | LOCK_EX);

    $subject = "Test de Email - Tramitfy " . date('H:i:s');
    $message = "
    <h2>Test de Email - Tramitfy</h2>
    <p>Este es un email de prueba enviado el " . date('d/m/Y H:i:s') . "</p>
    <p>Si recibiste este email, la configuración básica funciona correctamente.</p>
    <hr>
    <p><small>Enviado desde: " . get_site_url() . "</small></p>
    ";

    $headers = array('Content-Type: text/html; charset=UTF-8');
    $sent = wp_mail($test_email, $subject, $message, $headers);

    $log_entry = date('Y-m-d H:i:s') . " TRAMITFY: Test email result: " . ($sent ? 'SUCCESS' : 'FAILED') . "\n";
    file_put_contents($test_log, $log_entry, FILE_APPEND | LOCK_EX);

    wp_send_json_success(['message' => 'Test email ' . ($sent ? 'enviado correctamente' : 'falló'), 'sent' => $sent]);
}

add_action('wp_ajax_tramitfy_test_email', 'tramitfy_test_email');
add_action('wp_ajax_nopriv_tramitfy_test_email', 'tramitfy_test_email');

// Función de test de PDF
function tramitfy_test_pdf() {
    $test_log = ABSPATH . 'wp-content/tramitfy-debug.log';
    $log_entry = date('Y-m-d H:i:s') . " TRAMITFY: === PDF TEST START ===\n";
    file_put_contents($test_log, $log_entry, FILE_APPEND | LOCK_EX);

    try {
        // Datos de prueba
        $test_data = json_decode(stripslashes($_POST['test_data']), true);
        $log_entry = date('Y-m-d H:i:s') . " TRAMITFY: Test data recibido: " . print_r($test_data, true) . "\n";
        file_put_contents($test_log, $log_entry, FILE_APPEND | LOCK_EX);

        // Simular datos del contrato
        $contrato_data = [
            'marca' => $test_data['marca'] ?? 'Beneteau',
            'modelo' => $test_data['modelo'] ?? 'Oceanis 40',
            'ano' => $test_data['ano'] ?? '2020',
            'bastidor' => 'TEST123456',
            'matricula' => 'TEST-001',
            'precio' => $test_data['precio'] ?? '85000',
            'formaPago' => 'Transferencia bancaria',
            'lugarEntrega' => 'Puerto Marina Test',
            'motor' => 'Motor de prueba 40HP',
            'vendedorNombre' => $test_data['vendedorNombre'] ?? 'Vendedor Test',
            'vendedorDni' => '12345678A',
            'vendedorDireccion' => 'Dirección Vendedor Test',
            'vendedorTelefono' => '600000000',
            'vendedorEmail' => 'joanpinyol@hotmail.es',
            'compradorNombre' => $test_data['compradorNombre'] ?? 'Comprador Test',
            'compradorDni' => '87654321B',
            'compradorDireccion' => 'Dirección Comprador Test',
            'compradorTelefono' => '600111111',
            'compradorEmail' => 'joanpinyol@hotmail.es',
        ];

        $log_entry = date('Y-m-d H:i:s') . " TRAMITFY: Iniciando generación de PDF...\n";
        file_put_contents($test_log, $log_entry, FILE_APPEND | LOCK_EX);

        // USAR FPDF con múltiples fallbacks
        $fpdf_found = false;
        $fpdf_paths = [
            dirname(__FILE__) . '/fpdf/fpdf.php',
            get_template_directory() . '/vendor/fpdf/fpdf.php',
            ABSPATH . 'wp-content/fpdf/fpdf.php'
        ];

        foreach ($fpdf_paths as $fpdf_path) {
            if (file_exists($fpdf_path)) {
                require_once $fpdf_path;
                $fpdf_found = true;
                $log_entry = date('Y-m-d H:i:s') . " TRAMITFY: FPDF cargado desde: " . $fpdf_path . "\n";
                file_put_contents($test_log, $log_entry, FILE_APPEND | LOCK_EX);
                break;
            }
        }

        if (!$fpdf_found) {
            throw new Exception("FPDF library not found");
        }

        // Crear instancia de FPDF
        $pdf = new FPDF('P', 'mm', 'A4');
        $pdf->AddPage();
        $log_entry = date('Y-m-d H:i:s') . " TRAMITFY: Instancia FPDF creada\n";
        file_put_contents($test_log, $log_entry, FILE_APPEND | LOCK_EX);

        // Contenido simple de prueba
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(0, 10, utf8_decode('CONTRATO DE PRUEBA - TRAMITFY'), 0, 1, 'C');
        $pdf->Ln(10);

        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(0, 8, utf8_decode('Embarcación: ' . $contrato_data['marca'] . ' ' . $contrato_data['modelo']), 0, 1);
        $pdf->Cell(0, 8, utf8_decode('Año: ' . $contrato_data['ano']), 0, 1);
        $pdf->Cell(0, 8, utf8_decode('Precio: ' . number_format($contrato_data['precio'], 0, ',', '.') . ' EUR'), 0, 1);
        $pdf->Ln(10);

        $pdf->Cell(0, 8, utf8_decode('Vendedor: ' . $contrato_data['vendedorNombre']), 0, 1);
        $pdf->Cell(0, 8, utf8_decode('Comprador: ' . $contrato_data['compradorNombre']), 0, 1);
        $pdf->Ln(10);

        $pdf->SetFont('Arial', 'I', 10);
        $pdf->Cell(0, 8, utf8_decode('PDF generado en: ' . date('d/m/Y H:i:s')), 0, 1, 'C');

        // Guardar PDF
        $upload_dir = wp_upload_dir();
        $file_name = 'test_contrato_' . time() . '.pdf';
        $file_path = $upload_dir['path'] . '/' . $file_name;
        $file_url = $upload_dir['url'] . '/' . $file_name;

        $pdf->Output('F', $file_path);

        // Verificar que se creó correctamente
        if (file_exists($file_path)) {
            $file_size = filesize($file_path);
            $log_entry = date('Y-m-d H:i:s') . " TRAMITFY: ✅ PDF creado exitosamente - Tamaño: " . $file_size . " bytes\n";
            file_put_contents($test_log, $log_entry, FILE_APPEND | LOCK_EX);

            $log_entry = date('Y-m-d H:i:s') . " TRAMITFY: === PDF TEST SUCCESS ===\n";
            file_put_contents($test_log, $log_entry, FILE_APPEND | LOCK_EX);

            wp_send_json_success([
                'message' => 'PDF generado correctamente - Tamaño: ' . $file_size . ' bytes',
                'pdf_url' => $file_url,
                'file_path' => $file_path
            ]);
        } else {
            throw new Exception("PDF no se pudo crear");
        }

    } catch (Exception $e) {
        $log_entry = date('Y-m-d H:i:s') . " TRAMITFY: ❌ ERROR PDF: " . $e->getMessage() . "\n";
        file_put_contents($test_log, $log_entry, FILE_APPEND | LOCK_EX);

        $log_entry = date('Y-m-d H:i:s') . " TRAMITFY: === PDF TEST FAILED ===\n";
        file_put_contents($test_log, $log_entry, FILE_APPEND | LOCK_EX);

        wp_send_json_error(['message' => 'Error al generar PDF: ' . $e->getMessage()]);
    }
}

add_action('wp_ajax_tramitfy_test_pdf', 'tramitfy_test_pdf');
add_action('wp_ajax_nopriv_tramitfy_test_pdf', 'tramitfy_test_pdf');
?>