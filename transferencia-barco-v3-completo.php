<?php
/**
 * TRAMITFY - TRANSFERENCIA BARCO V3 COMPLETO
 * 
 * ✅ ESTRUCTURA VISUAL IDÉNTICA AL ORIGINAL
 * ✅ Sidebar específico por página
 * ✅ Menú móvil responsive
 * ✅ SISTEMA COMPLETO CAPTURA DE DATOS
 * ✅ Preparado para sincronización TPV
 * 
 * @version 3.0.1 - COMPLETO CON CAPTURA DE DATOS
 * @author Claude Code  
 * @created 2025-11-13
 * @reference transferencia-barco.php
 */

// Acceso solo via WordPress
if (!defined('ABSPATH')) {
    exit('Acceso directo no permitido.');
}

/**
 * ✅ FUNCIÓN PRINCIPAL DEL SHORTCODE V3 COMPLETO
 */
function transferencia_barco_v3_completo_shortcode() {
    // Encolar scripts necesarios
    wp_enqueue_script('signature-pad', 'https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js', array(), null, false);
    
    // Iniciar buffering
    ob_start();
    ?>
    
    <!-- CSS COMPLETO IDÉNTICO + MEJORAS -->
    <style>
        /* RESET GLOBAL */
        * { box-sizing: border-box; }
        html, body { margin: 0 !important; padding: 0 !important; }
        
        /* Variables de color */
        :root {
            --primary: 1, 109, 134;
            --primary-bg: 236, 247, 255;
            --secondary: 0, 123, 255;
            --neutral: 70, 80, 95;
            --success: 40, 167, 69;
            --warning: 243, 156, 18;
            --error: 231, 76, 60;
            --spacing-xs: 0.25rem;
            --spacing-sm: 0.5rem;
            --spacing-md: 1rem;
            --spacing-lg: 1.5rem;
            --spacing-xl: 2rem;
            --radius-sm: 0.25rem;
            --radius-md: 0.375rem;
            --radius-lg: 0.5rem;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.1);
            --shadow-md: 0 4px 6px rgba(0,0,0,0.1), 0 1px 3px rgba(0,0,0,0.08);
            --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05);
        }

        /* FORMULARIO PRINCIPAL */
        #transferencia-form-v3 {
            max-width: 100%;
            width: 100%;
            margin: 20px auto 0 auto;
            padding: 0;
            font-family: 'Roboto', 'Helvetica Neue', Helvetica, Arial, sans-serif;
            background: transparent;
        }

        /* LAYOUT DE DOS COLUMNAS IDÉNTICO */
        .tramitfy-layout-wrapper {
            width: 100%;
            margin: 0 auto 0 auto; /* Sin margen inferior */
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
            overflow-y: auto;
        }

        .tramitfy-two-column {
            display: grid !important;
            grid-template-columns: auto 1fr !important;
            grid-template-areas: "sidebar content" !important;
            gap: 0;
            align-items: stretch;
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }

        /* SIDEBAR IZQUIERDO IDÉNTICO */
        .tramitfy-sidebar {
            grid-area: sidebar;
            position: relative;
            background: #016d86;
            border-radius: 12px 0 0 12px;
            padding: 18px 16px;
            overflow-y: auto;
            overflow-x: hidden;
            color: #ffffff;
            display: flex;
            flex-direction: column;
            width: 320px;
            transition: width 0.3s ease, background 0.3s ease, box-shadow 0.3s ease;
        }

        .sidebar-content {
            display: flex;
            flex-direction: column;
            animation: fadeInUp 0.4s ease-out;
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
        }

        .sidebar-content.active {
            display: flex;
        }

        .sidebar-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 12px;
            padding-bottom: 10px;
            border-bottom: 2px solid rgba(255, 255, 255, 0.15);
            flex-shrink: 0;
        }

        .sidebar-icon {
            width: 38px;
            height: 38px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: white;
        }

        .sidebar-title {
            color: #ffffff;
            font-size: 15px;
            font-weight: 700;
            margin: 0;
            line-height: 1.2;
        }

        .sidebar-subtitle {
            color: rgba(255, 255, 255, 0.85);
            font-size: 11px;
            line-height: 1.2;
        }

        .sidebar-body {
            margin-bottom: 12px;
            flex: 1;
            min-height: 0;
        }

        .sidebar-info-box {
            background: rgba(255, 255, 255, 0.12);
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            border-left: 3px solid rgba(255, 255, 255, 0.4);
        }

        .sidebar-info-box p {
            margin: 0 0 4px 0;
            color: rgba(255, 255, 255, 0.95);
            font-size: 12px;
            line-height: 1.4;
        }

        .sidebar-info-box p:last-child {
            margin-bottom: 0;
        }

        .sidebar-info-box strong {
            color: #ffffff;
            font-weight: 600;
        }

        /* PANEL PRINCIPAL DERECHO */
        .tramitfy-main-form {
            grid-area: content;
            padding: 40px 48px;
            background: white;
            overflow-y: auto;
            position: relative;
            border-radius: 0 12px 12px 0;
        }

        .tramitfy-main-form h2 {
            font-size: 32px;
            font-weight: 700;
            color: #1a202c;
            margin-bottom: 12px;
        }

        .tramitfy-main-form .subtitle {
            color: #666;
            margin-bottom: 32px;
            font-size: 15px;
            line-height: 1.6;
        }

        /* NAVEGACIÓN SUPERIOR IDÉNTICA */
        #form-navigation {
            margin-bottom: 32px;
        }

        .nav-progress-bar {
            height: 4px;
            background: #e5e7eb;
            border-radius: 2px;
            margin-bottom: 20px;
            overflow: hidden;
        }

        .nav-progress-indicator {
            height: 100%;
            background: #016d86;
            border-radius: 2px;
            width: 20%;
            transition: width 0.3s ease;
        }

        .nav-items-container {
            display: flex;
            gap: 0;
            background: white;
            border-radius: 50px;
            padding: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            border: 2px solid #e5e7eb;
            justify-content: space-between;
        }

        .nav-item {
            padding: 12px 16px;
            border-radius: 50px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
            font-size: 13px;
            color: #6b7280;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            flex: 1;
            justify-content: center;
            white-space: nowrap;
        }

        .nav-item-circle {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #f3f4f6;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            flex-shrink: 0;
        }

        .nav-item-icon {
            font-size: 14px;
            color: #6b7280;
        }

        .nav-item-number {
            position: absolute;
            bottom: -2px;
            right: -2px;
            background: #6b7280;
            color: white;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: 700;
        }

        .nav-item-text {
            display: inline;
            font-weight: 600;
        }

        .nav-item.active {
            background: #016d86;
            color: white;
            box-shadow: 0 2px 8px rgba(1, 109, 134, 0.3);
        }

        .nav-item.active .nav-item-circle {
            background: rgba(255,255,255,0.2);
        }

        .nav-item.active .nav-item-icon {
            color: white;
        }

        .nav-item.active .nav-item-number {
            background: white;
            color: #016d86;
        }

        .nav-item.active .nav-item-text {
            color: white;
        }

        .nav-item.completed {
            background: #10b981;
            color: white;
        }

        .nav-item.completed .nav-item-circle {
            background: rgba(255,255,255,0.2);
        }

        .nav-item.completed .nav-item-icon {
            color: white;
        }

        .nav-item.completed .nav-item-number {
            background: white;
            color: #10b981;
        }

        /* PÁGINAS DEL FORMULARIO */
        .form-page {
            display: none;
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.3s ease;
        }

        .form-page.active {
            display: block;
            opacity: 1;
            transform: translateY(0);
        }

        /* GRUPOS DE FORMULARIO */
        .form-group {
            margin-bottom: 24px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s ease;
            background: white;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #016d86;
            box-shadow: 0 0 0 3px rgba(1, 109, 134, 0.1);
        }

        /* LAYOUTS COMPACTOS */
        .form-compact-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 20px;
        }

        .form-compact-triple {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 12px;
            margin-bottom: 20px;
        }

        /* BOTONES */
        .button {
            padding: 14px 24px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s ease;
            border: none;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .button-primary {
            background: #016d86;
            color: white;
        }

        .button-primary:hover {
            background: #014d5f;
            transform: translateY(-1px);
        }

        .button-secondary {
            background: #f8f9fa;
            color: #374151;
            border: 2px solid #e5e7eb;
        }

        .button-secondary:hover {
            background: #e5e7eb;
        }

        /* CONTENEDOR DE BOTONES */
        .button-container {
            display: flex;
            gap: 16px;
            justify-content: space-between;
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid #e5e7eb;
        }

        /* ITP INFO BOX */
        .itp-info-box {
            background: rgba(1, 109, 134, 0.08);
            border: 2px solid #016d86;
            border-radius: 12px;
            padding: 28px;
            margin-bottom: 20px;
        }

        .itp-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }

        .itp-title {
            font-size: 20px;
            font-weight: 700;
            color: #016d86;
        }

        .itp-subtitle {
            font-size: 14px;
            color: #016d86;
            margin-top: 6px;
        }

        .itp-amount {
            font-size: 32px;
            font-weight: 700;
            color: #016d86;
            background: white;
            padding: 8px 16px;
            border-radius: 8px;
            border: 2px solid #016d86;
        }

        /* UPLOAD DE ARCHIVOS */
        .upload-grid {
            display: grid;
            gap: 20px;
            margin-bottom: 32px;
        }

        .upload-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .upload-item {
            background: white;
            border: 2px dashed #d1d5db;
            border-radius: 12px;
            padding: 24px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
        }

        .upload-item:hover {
            border-color: #016d86;
            background: rgba(1, 109, 134, 0.02);
            transform: translateY(-2px);
        }

        .upload-item.uploaded {
            border-color: #10b981;
            background: rgba(16, 185, 129, 0.05);
        }

        .upload-item input[type="file"] {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }

        /* FIRMA DIGITAL */
        #signature-pad {
            border: 2px solid #016d86;
            width: 100%;
            max-width: 600px;
            height: 200px;
            border-radius: 8px;
            background: white;
            cursor: crosshair;
        }

        /* RESPONSIVO - MENÚ MÓVIL */
        @media (max-width: 1024px) {
            .tramitfy-two-column {
                grid-template-columns: 1fr !important;
                grid-template-areas: "sidebar" "content" !important;
            }
            
            .tramitfy-sidebar {
                position: relative;
                width: 100% !important;
                height: auto !important;
                min-height: auto !important;
                border-radius: 12px 12px 0 0;
                padding: 20px;
            }
            
            .tramitfy-main-form {
                padding: 24px;
                border-radius: 0 0 12px 12px;
            }
        }

        @media (max-width: 768px) {
            /* NAVEGACIÓN MÓVIL */
            .nav-items-container {
                flex-wrap: wrap;
                border-radius: 16px;
                padding: 6px;
                gap: 4px;
            }
            
            .nav-item {
                padding: 8px 12px;
                font-size: 11px;
                flex: 1;
                min-width: calc(50% - 2px);
                border-radius: 12px;
            }
            
            .nav-item-circle {
                width: 24px;
                height: 24px;
                display: none; /* Ocultar círculos en móvil */
            }
            
            .nav-item-text {
                font-size: 11px !important;
            }

            /* FORMULARIOS COMPACTOS */
            .form-compact-row,
            .form-compact-triple {
                grid-template-columns: 1fr;
                gap: 16px;
            }
            
            .upload-row {
                grid-template-columns: 1fr;
            }
            
            .button-container {
                flex-direction: column-reverse;
                gap: 12px;
            }
            
            .button {
                width: 100%;
            }

            /* SIDEBAR MÓVIL */
            .sidebar-info-box {
                padding: 8px;
                font-size: 11px;
            }

            .tramitfy-main-form h2 {
                font-size: 24px;
            }
        }

        /* TESTING STYLES */
        .test-info {
            background: linear-gradient(135deg, #10b981, #016d86);
            color: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            text-align: center;
        }

        .test-info h3 {
            margin: 0 0 8px 0;
            font-size: 16px;
        }

        .test-info p {
            margin: 0;
            opacity: 0.9;
            font-size: 13px;
        }

        /* DATA PERSISTENCE INDICATOR */
        .data-status {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: rgba(1, 109, 134, 0.9);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            z-index: 1000;
            display: none;
        }

        .data-status.saving {
            background: rgba(243, 156, 18, 0.9);
        }

        .data-status.saved {
            background: rgba(40, 167, 69, 0.9);
        }

        /* ANIMACIONES */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.7;
            }
        }

        .saving-indicator {
            animation: pulse 1s infinite;
        }
    </style>

    <!-- HTML PRINCIPAL - ESTRUCTURA COMPLETA -->
    <form id="transferencia-form-v3" method="POST" enctype="multipart/form-data">
        
        <div class="tramitfy-layout-wrapper">
            <div class="tramitfy-two-column">

                <!-- SIDEBAR IZQUIERDO -->
                <aside class="tramitfy-sidebar">
                    
                    <!-- Contenido dinámico por página -->
                    <div id="sidebar-dynamic-content" style="margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid rgba(255,255,255,0.2);">
                        <!-- Se actualizará dinámicamente con JavaScript -->
                    </div>

                    <!-- Contenido universal del sidebar -->
                    <div class="sidebar-content active" data-step="all">
                        <div class="sidebar-body">
                            <!-- Widget de reseñas -->
                            <div style="background: rgba(255,255,255,0.05); padding: 16px; border-radius: 8px; margin-bottom: 16px; text-align: center;">
                                <div style="font-size: 20px; margin-bottom: 8px;">⭐⭐⭐⭐⭐</div>
                                <div style="color: white; font-weight: 600; font-size: 14px;">Más de 1000+ clientes</div>
                                <div style="color: rgba(255,255,255,0.8); font-size: 12px;">Transferencias exitosas</div>
                            </div>

                            <!-- Info boxes -->
                            <div class="sidebar-info-box">
                                <p><strong>💰 Precio Incluye:</strong></p>
                                <p>• Gestión completa del trámite</p>
                                <p>• Documentación oficial</p>
                                <p>• Asesoramiento legal especializado</p>
                                <p>• Seguimiento hasta finalización</p>
                            </div>

                            <div class="sidebar-info-box">
                                <p><strong>⏱️ Tiempo de Gestión:</strong></p>
                                <p>• Presentación en menos de 24h</p>
                                <p>• Provisional en menos de 24h</p>
                                <p>• Seguimiento tiempo real</p>
                            </div>

                            <div class="sidebar-info-box">
                                <p><strong>🛡️ Garantía Total:</strong></p>
                                <p>• Sin letra pequeña</p>
                                <p>• Sin costes ocultos</p>
                                <p>• 100% satisfacción garantizada</p>
                            </div>
                        </div>
                    </div>

                </aside>

                <!-- PANEL PRINCIPAL DERECHO -->
                <div class="tramitfy-main-form">
                    
                    <!-- NAVEGACIÓN SUPERIOR -->
                    <div id="form-navigation">
                        <div class="nav-progress-bar">
                            <div class="nav-progress-indicator"></div>
                        </div>
                        <div class="nav-items-container">
                            <a href="#" class="nav-item active" data-page-id="page-vehiculo">
                                <div class="nav-item-circle">
                                    <div class="nav-item-icon">🚢</div>
                                    <div class="nav-item-number">1</div>
                                </div>
                                <span class="nav-item-text">Vehículo</span>
                            </a>

                            <a href="#" class="nav-item" data-page-id="page-datos">
                                <div class="nav-item-circle">
                                    <div class="nav-item-icon">👤</div>
                                    <div class="nav-item-number">2</div>
                                </div>
                                <span class="nav-item-text">Datos</span>
                            </a>

                            <a href="#" class="nav-item" data-page-id="page-documentos">
                                <div class="nav-item-circle">
                                    <div class="nav-item-icon">📁</div>
                                    <div class="nav-item-number">3</div>
                                </div>
                                <span class="nav-item-text">Documentos</span>
                            </a>

                            <a href="#" class="nav-item" data-page-id="page-pago">
                                <div class="nav-item-circle">
                                    <div class="nav-item-icon">💳</div>
                                    <div class="nav-item-number">4</div>
                                </div>
                                <span class="nav-item-text">Pago</span>
                            </a>
                        </div>
                    </div>

                    <!-- PÁGINA 1: VEHÍCULO -->
                    <div id="page-vehiculo" class="form-page active">
                        <h2 style="margin-bottom: 12px; color: #016d86; font-size: 22px; font-weight: 600;">Cambio Titularidad Embarcación</h2>
                        <p class="subtitle">Realiza el cambio de titularidad de tu embarcación online, sin desplazamientos ni esperas en Capitanía.</p>

                        <!-- Tipo fijo -->
                        <input type="hidden" name="vehicle_type" value="Embarcación">

                        <!-- Datos del vehículo -->
                        <h3 style="color: #016d86; margin: 32px 0 16px 0;">🚢 Datos de la Embarcación</h3>
                        <div class="form-compact-triple">
                            <div class="form-group">
                                <label for="matricula">Matrícula *</label>
                                <input type="text" id="matricula" name="matricula" required>
                            </div>
                            <div class="form-group">
                                <label for="manufacturer">Fabricante *</label>
                                <input type="text" id="manufacturer" name="manufacturer" required>
                            </div>
                            <div class="form-group">
                                <label for="model">Modelo *</label>
                                <input type="text" id="model" name="model" required>
                            </div>
                        </div>

                        <div class="form-compact-row">
                            <div class="form-group">
                                <label for="año">Año de Fabricación</label>
                                <input type="number" id="año" name="año" min="1900" max="2024">
                            </div>
                            <div class="form-group">
                                <label for="valor_vehiculo">Valor del Vehículo (€) *</label>
                                <input type="number" id="valor_vehiculo" name="valor_vehiculo" required step="0.01">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="comunidad_autonoma">Comunidad Autónoma *</label>
                            <select id="comunidad_autonoma" name="comunidad_autonoma" required>
                                <option value="">Seleccione...</option>
                                <option value="ANDALUCÍA">Andalucía</option>
                                <option value="ARAGÓN">Aragón</option>
                                <option value="ASTURIAS">Asturias</option>
                                <option value="CANTABRIA">Cantabria</option>
                                <option value="CASTILLA_LA_MANCHA">Castilla-La Mancha</option>
                                <option value="CASTILLA_Y_LEON">Castilla y León</option>
                                <option value="CATALUÑA">Cataluña</option>
                                <option value="COMUNIDAD_VALENCIANA">Comunidad Valenciana</option>
                                <option value="EXTREMADURA">Extremadura</option>
                                <option value="GALICIA">Galicia</option>
                                <option value="ISLAS_BALEARES">Islas Baleares</option>
                                <option value="ISLAS_CANARIAS">Islas Canarias</option>
                                <option value="LA_RIOJA">La Rioja</option>
                                <option value="MADRID">Madrid</option>
                                <option value="MURCIA">Murcia</option>
                                <option value="NAVARRA">Navarra</option>
                                <option value="PAIS_VASCO">País Vasco</option>
                            </select>
                        </div>
                    </div>

                    <!-- PÁGINA 2: DATOS -->
                    <div id="page-datos" class="form-page">
                        <h2>Datos de las Partes</h2>
                        <p class="subtitle">Complete la información del comprador y vendedor.</p>

                        <!-- Datos del Comprador -->
                        <h3 style="color: #016d86; margin-bottom: 16px;">👤 Datos del Comprador</h3>
                        <div class="form-compact-row">
                            <div class="form-group">
                                <label for="nombre_comprador">Nombre *</label>
                                <input type="text" id="nombre_comprador" name="nombre_comprador" required>
                            </div>
                            <div class="form-group">
                                <label for="apellidos_comprador">Apellidos *</label>
                                <input type="text" id="apellidos_comprador" name="apellidos_comprador" required>
                            </div>
                        </div>

                        <div class="form-compact-row">
                            <div class="form-group">
                                <label for="dni_comprador">DNI *</label>
                                <input type="text" id="dni_comprador" name="dni_comprador" required>
                            </div>
                            <div class="form-group">
                                <label for="telefono_comprador">Teléfono *</label>
                                <input type="tel" id="telefono_comprador" name="telefono_comprador" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="email_comprador">Email *</label>
                            <input type="email" id="email_comprador" name="email_comprador" required>
                        </div>

                        <!-- Datos del Vendedor -->
                        <h3 style="color: #016d86; margin: 32px 0 16px 0;">👥 Datos del Vendedor</h3>
                        <div class="form-compact-row">
                            <div class="form-group">
                                <label for="nombre_vendedor">Nombre *</label>
                                <input type="text" id="nombre_vendedor" name="nombre_vendedor" required>
                            </div>
                            <div class="form-group">
                                <label for="apellidos_vendedor">Apellidos *</label>
                                <input type="text" id="apellidos_vendedor" name="apellidos_vendedor" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="dni_vendedor">DNI *</label>
                            <input type="text" id="dni_vendedor" name="dni_vendedor" required style="max-width: 300px;">
                        </div>
                    </div>

                    <!-- PÁGINA 3: DOCUMENTOS -->
                    <div id="page-documentos" class="form-page">
                        <h2>Documentos y Firma</h2>
                        <p class="subtitle">Suba los documentos necesarios y firme digitalmente.</p>

                        <!-- Upload de documentos -->
                        <h3 style="color: #016d86; margin-bottom: 16px;">📁 Documentos Requeridos</h3>
                        <div class="upload-grid">
                            <div class="upload-row">
                                <div class="upload-item" onclick="document.getElementById('file-dni-comprador').click()">
                                    <div style="font-size: 32px; margin-bottom: 12px; color: #016d86;">📄</div>
                                    <div style="font-weight: 600; margin-bottom: 8px;">DNI Comprador</div>
                                    <div style="font-size: 12px; color: #666; margin-bottom: 8px;">PDF, JPG o PNG</div>
                                    <input type="file" id="file-dni-comprador" name="dni_comprador" accept=".pdf,.jpg,.png" style="display: none;">
                                    <div id="status-dni-comprador" style="font-size: 11px; color: #666;">Sin archivo</div>
                                </div>
                                <div class="upload-item" onclick="document.getElementById('file-dni-vendedor').click()">
                                    <div style="font-size: 32px; margin-bottom: 12px; color: #016d86;">📄</div>
                                    <div style="font-weight: 600; margin-bottom: 8px;">DNI Vendedor</div>
                                    <div style="font-size: 12px; color: #666; margin-bottom: 8px;">PDF, JPG o PNG</div>
                                    <input type="file" id="file-dni-vendedor" name="dni_vendedor" accept=".pdf,.jpg,.png" style="display: none;">
                                    <div id="status-dni-vendedor" style="font-size: 11px; color: #666;">Sin archivo</div>
                                </div>
                            </div>

                            <div class="upload-row">
                                <div class="upload-item" onclick="document.getElementById('file-registro-maritimo').click()">
                                    <div style="font-size: 32px; margin-bottom: 12px; color: #016d86;">⚓</div>
                                    <div style="font-weight: 600; margin-bottom: 8px;">Registro Marítimo</div>
                                    <div style="font-size: 12px; color: #666; margin-bottom: 8px;">Documentación del barco</div>
                                    <input type="file" id="file-registro-maritimo" name="registro_maritimo" accept=".pdf,.jpg,.png" style="display: none;">
                                    <div id="status-registro-maritimo" style="font-size: 11px; color: #666;">Sin archivo</div>
                                </div>
                                <div class="upload-item" onclick="document.getElementById('file-contrato').click()">
                                    <div style="font-size: 32px; margin-bottom: 12px; color: #016d86;">📋</div>
                                    <div style="font-weight: 600; margin-bottom: 8px;">Contrato Compraventa</div>
                                    <div style="font-size: 12px; color: #666; margin-bottom: 8px;">Si ya existe</div>
                                    <input type="file" id="file-contrato" name="contrato" accept=".pdf,.jpg,.png" style="display: none;">
                                    <div id="status-contrato" style="font-size: 11px; color: #666;">Opcional</div>
                                </div>
                            </div>
                        </div>

                        <!-- Firma digital -->
                        <h3 style="color: #016d86; margin: 32px 0 16px 0;">✍️ Firma Digital</h3>
                        <div style="text-align: center; margin: 32px 0;">
                            <canvas id="signature-pad" width="600" height="200"></canvas>
                            <br>
                            <button type="button" id="clear-signature" style="margin-top: 16px; background: #f5f5f5; color: #666; border: 2px solid #ddd; padding: 10px 20px; border-radius: 6px; cursor: pointer;">
                                🗑️ Limpiar Firma
                            </button>
                        </div>
                    </div>

                    <!-- PÁGINA 4: PAGO -->
                    <div id="page-pago" class="form-page">
                        <h2>Resumen y Pago</h2>
                        <p class="subtitle">Revise todos los datos y complete el pago.</p>

                        <!-- ITP Info Box -->
                        <div class="itp-info-box">
                            <div class="itp-header">
                                <div>
                                    <div class="itp-title">Impuesto (ITP)</div>
                                    <div class="itp-subtitle">Obligatorio ante Hacienda</div>
                                </div>
                                <div class="itp-amount" id="itp-amount-display">0 €</div>
                            </div>
                            <div style="border-top: 1px solid #016d86; padding-top: 16px; margin-top: 16px;">
                                <button type="button" style="background: #016d86; color: white; border: none; padding: 10px 20px; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; width: 100%;">
                                    🧮 Ver cómo se calcula el ITP
                                </button>
                            </div>
                        </div>

                        <!-- Resumen del pedido -->
                        <div style="background: #f8f9fa; padding: 24px; border-radius: 12px; margin-bottom: 24px;">
                            <h3 style="color: #016d86; margin-bottom: 16px;">📋 Resumen del Pedido</h3>
                            <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                <span>Gestión Transferencia:</span>
                                <span style="font-weight: 600;">174.99 €</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                <span>ITP (Impuesto):</span>
                                <span style="font-weight: 600;" id="itp-summary">0 €</span>
                            </div>
                            <hr style="margin: 16px 0; border: 1px solid #e5e7eb;">
                            <div style="display: flex; justify-content: space-between; font-size: 18px; font-weight: 700; color: #016d86;">
                                <span>Total:</span>
                                <span id="total-summary">174.99 €</span>
                            </div>
                        </div>

                        <!-- Resumen de datos -->
                        <div id="data-summary" style="background: #f8f9fa; padding: 20px; border-radius: 12px; margin-bottom: 24px;">
                            <h4 style="color: #016d86; margin-bottom: 12px;">📝 Resumen de Datos</h4>
                            <div id="summary-content">
                                <!-- Se llenará dinámicamente -->
                            </div>
                        </div>

                        <div style="text-align: center;">
                            <button type="button" id="btn-procesar-pago" class="button button-primary" style="padding: 16px 32px; font-size: 16px;">
                                💳 Procesar Pago con TPV
                            </button>
                        </div>
                    </div>

                    <!-- BOTONES DE NAVEGACIÓN -->
                    <div class="button-container">
                        <button type="button" id="btn-anterior" class="button button-secondary" style="display: none;">
                            ← Anterior
                        </button>
                        <button type="button" id="btn-siguiente" class="button button-primary">
                            Siguiente →
                        </button>
                    </div>

                </div>

            </div>
        </div>

        <!-- INDICADOR DE ESTADO -->
        <div id="data-status" class="data-status">💾 Guardando datos...</div>

    </form>

    <!-- JAVASCRIPT COMPLETO CON SISTEMA DE CAPTURA DE DATOS -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log('🚀 TBV3 COMPLETO - Sistema de captura de datos inicializado');
        
        // =====================================================
        // VARIABLES GLOBALES Y CONFIGURACIÓN
        // =====================================================
        
        let currentPage = 0;
        const pages = [
            { id: 'page-vehiculo', name: 'Vehículo' },
            { id: 'page-datos', name: 'Datos' },
            { id: 'page-documentos', name: 'Documentos' },
            { id: 'page-pago', name: 'Pago' }
        ];
        
        // Sistema de almacenamiento de datos
        const formData = {
            vehiculo: {},
            comprador: {},
            vendedor: {},
            archivos: {},
            firma: null,
            itp: { amount: 0, porcentaje: 0 }
        };
        
        // Referencias DOM
        const navItems = document.querySelectorAll('.nav-item');
        const formPages = document.querySelectorAll('.form-page');
        const btnAnterior = document.getElementById('btn-anterior');
        const btnSiguiente = document.getElementById('btn-siguiente');
        const progressIndicator = document.querySelector('.nav-progress-indicator');
        const sidebarDynamic = document.getElementById('sidebar-dynamic-content');
        const dataStatus = document.getElementById('data-status');
        
        // =====================================================
        // FUNCIONES DE NAVEGACIÓN
        // =====================================================
        
        function showPage(pageIndex) {
            if (pageIndex < 0 || pageIndex >= pages.length) return;
            
            // Ocultar todas las páginas
            formPages.forEach(page => page.classList.remove('active'));
            
            // Mostrar página actual
            const currentPageElement = document.getElementById(pages[pageIndex].id);
            if (currentPageElement) {
                currentPageElement.classList.add('active');
            }
            
            // Actualizar navegación
            updateNavigation(pageIndex);
            
            // Actualizar botones
            updateButtons(pageIndex);
            
            // Actualizar sidebar dinámico
            updateSidebarContent(pageIndex);
            
            // Actualizar barra de progreso
            updateProgress(pageIndex);
            
            currentPage = pageIndex;
            console.log('📄 Página cambiada:', pages[pageIndex].name);
        }
        
        function updateNavigation(pageIndex) {
            navItems.forEach((item, index) => {
                item.classList.remove('active', 'completed');
                if (index === pageIndex) {
                    item.classList.add('active');
                } else if (index < pageIndex) {
                    item.classList.add('completed');
                }
            });
        }
        
        function updateButtons(pageIndex) {
            btnAnterior.style.display = pageIndex === 0 ? 'none' : 'inline-flex';
            btnSiguiente.textContent = pageIndex === pages.length - 1 ? 'Procesar Pago' : 'Siguiente →';
        }
        
        function updateProgress(pageIndex) {
            const progress = ((pageIndex + 1) / pages.length) * 100;
            progressIndicator.style.width = progress + '%';
        }
        
        // =====================================================
        // SIDEBAR DINÁMICO POR PÁGINA
        // =====================================================
        
        function updateSidebarContent(pageIndex) {
            const pageId = pages[pageIndex].id;
            let contenido = '';
            
            switch(pageId) {
                case 'page-vehiculo':
                    contenido = `
                        <div style="background: rgba(255,255,255,0.1); padding: 18px; border-radius: 8px;">
                            <h3 style="color: white; font-size: 16px; margin: 0 0 16px 0; font-weight: 600; line-height: 1.3;">
                                Cambio Titularidad<br>Embarcación
                            </h3>
                            
                            <div style="margin-bottom: 16px;">
                                <div style="display: flex; align-items: center; margin-bottom: 8px; padding: 8px; background: rgba(255,255,255,0.1); border-radius: 6px; border-left: 3px solid rgba(255,255,255,0.6);">
                                    <span style="color: rgba(255,255,255,0.9); font-size: 12px;">Presentamos tu solicitud en menos de 24h</span>
                                </div>
                                <div style="display: flex; align-items: center; margin-bottom: 8px; padding: 8px; background: rgba(255,255,255,0.1); border-radius: 6px; border-left: 3px solid rgba(255,255,255,0.6);">
                                    <span style="color: rgba(255,255,255,0.9); font-size: 12px;">Envío de provisional en menos de 24h</span>
                                </div>
                                <div style="display: flex; align-items: center; margin-bottom: 8px; padding: 8px; background: rgba(255,255,255,0.1); border-radius: 6px; border-left: 3px solid rgba(255,255,255,0.6);">
                                    <span style="color: rgba(255,255,255,0.9); font-size: 12px;">Seguimiento estado del trámite en tiempo real</span>
                                </div>
                            </div>
                            
                            <div style="background: rgba(255,255,255,0.1); padding: 12px; border-radius: 6px;">
                                <div style="color: rgba(255,255,255,0.9); font-size: 12px; text-align: center;">
                                    <strong style="color: white;">Tiempo estimado total:</strong><br>
                                    15-30 días laborables
                                </div>
                            </div>
                        </div>
                    `;
                    break;
                    
                case 'page-datos':
                    contenido = `
                        <div style="background: rgba(255,255,255,0.1); padding: 16px; border-radius: 8px;">
                            <h4 style="color: white; font-size: 15px; margin: 0 0 12px 0; font-weight: 600;">
                                Datos Personales
                            </h4>
                            <div style="margin-bottom: 12px;">
                                <div style="color: rgba(255,255,255,0.9); font-size: 12px; margin-bottom: 8px; padding: 8px; background: rgba(255,255,255,0.05); border-radius: 4px;">
                                    <strong style="color: white;">📋 Información requerida:</strong><br>
                                    • DNI/NIE de comprador y vendedor<br>
                                    • Datos de contacto actualizados<br>
                                    • Email para notificaciones
                                </div>
                                <div style="color: rgba(255,255,255,0.8); font-size: 11px; text-align: center; padding: 8px; background: rgba(255,255,255,0.05); border-radius: 4px;">
                                    ✅ Datos seguros y encriptados
                                </div>
                            </div>
                        </div>
                    `;
                    break;
                    
                case 'page-documentos':
                    contenido = `
                        <div style="background: rgba(255,255,255,0.1); padding: 16px; border-radius: 8px;">
                            <h4 style="color: white; font-size: 15px; margin: 0 0 12px 0; font-weight: 600;">
                                Documentos y Firma
                            </h4>
                            <div style="margin-bottom: 12px;">
                                <div style="color: rgba(255,255,255,0.9); font-size: 12px; margin-bottom: 8px;">
                                    <strong style="color: white; display: block; margin-bottom: 4px;">📁 Documentos obligatorios:</strong>
                                    • DNI comprador (ambas caras)<br>
                                    • DNI vendedor (ambas caras)<br>
                                    • Registro marítimo vigente
                                </div>
                                <div style="color: rgba(255,255,255,0.9); font-size: 12px; margin-bottom: 8px;">
                                    <strong style="color: white; display: block; margin-bottom: 4px;">✍️ Firma digital:</strong>
                                    Autorización para gestión del trámite
                                </div>
                                <div style="color: rgba(255,255,255,0.8); font-size: 11px; text-align: center; padding: 8px; background: rgba(255,255,255,0.05); border-radius: 4px;">
                                    📱 Compatible con móviles y tablets
                                </div>
                            </div>
                        </div>
                    `;
                    break;
                    
                case 'page-pago':
                    contenido = `
                        <div style="background: rgba(255,255,255,0.1); padding: 16px; border-radius: 8px;">
                            <h4 style="color: white; font-size: 15px; margin: 0 0 12px 0; font-weight: 600;">
                                Pago Seguro
                            </h4>
                            <div style="margin-bottom: 12px;">
                                <div style="color: rgba(255,255,255,0.9); font-size: 12px; margin-bottom: 8px;">
                                    <strong style="color: white; display: block; margin-bottom: 4px;">💳 Métodos de pago:</strong>
                                    • Tarjeta de crédito/débito<br>
                                    • TPV Seguro (Redsys)<br>
                                    • Encriptación bancaria
                                </div>
                                <div style="color: rgba(255,255,255,0.9); font-size: 12px; margin-bottom: 8px;">
                                    <strong style="color: white; display: block; margin-bottom: 4px;">🛡️ Garantía:</strong>
                                    • Pago 100% seguro<br>
                                    • Certificado SSL<br>
                                    • Sin almacenar datos bancarios
                                </div>
                                <div style="color: rgba(255,255,255,0.8); font-size: 11px; text-align: center; padding: 8px; background: rgba(255,255,255,0.05); border-radius: 4px;">
                                    ⚡ Inicio del trámite inmediato tras el pago
                                </div>
                            </div>
                        </div>
                    `;
                    break;
            }
            
            sidebarDynamic.innerHTML = contenido;
        }
        
        // =====================================================
        // SISTEMA DE CAPTURA Y PERSISTENCIA DE DATOS
        // =====================================================
        
        function saveFormData() {
            showDataStatus('saving');
            
            // Capturar datos del vehículo
            formData.vehiculo = {
                matricula: document.getElementById('matricula')?.value || '',
                manufacturer: document.getElementById('manufacturer')?.value || '',
                model: document.getElementById('model')?.value || '',
                año: document.getElementById('año')?.value || '',
                valor_vehiculo: document.getElementById('valor_vehiculo')?.value || '',
                comunidad_autonoma: document.getElementById('comunidad_autonoma')?.value || ''
            };
            
            // Capturar datos del comprador
            formData.comprador = {
                nombre: document.getElementById('nombre_comprador')?.value || '',
                apellidos: document.getElementById('apellidos_comprador')?.value || '',
                dni: document.getElementById('dni_comprador')?.value || '',
                telefono: document.getElementById('telefono_comprador')?.value || '',
                email: document.getElementById('email_comprador')?.value || ''
            };
            
            // Capturar datos del vendedor
            formData.vendedor = {
                nombre: document.getElementById('nombre_vendedor')?.value || '',
                apellidos: document.getElementById('apellidos_vendedor')?.value || '',
                dni: document.getElementById('dni_vendedor')?.value || ''
            };
            
            // Capturar información de archivos
            formData.archivos = {
                dni_comprador: document.getElementById('file-dni-comprador')?.files[0] ? 'Subido' : 'No subido',
                dni_vendedor: document.getElementById('file-dni-vendedor')?.files[0] ? 'Subido' : 'No subido',
                registro_maritimo: document.getElementById('file-registro-maritimo')?.files[0] ? 'Subido' : 'No subido',
                contrato: document.getElementById('file-contrato')?.files[0] ? 'Subido' : 'No subido'
            };
            
            // Capturar firma
            if (signaturePad && !signaturePad.isEmpty()) {
                formData.firma = signaturePad.toDataURL();
            }
            
            // Guardar en localStorage para recuperación post-TPV
            try {
                localStorage.setItem('tbv3_form_data', JSON.stringify(formData));
                localStorage.setItem('tbv3_timestamp', Date.now().toString());
                console.log('💾 Datos guardados en localStorage');
                showDataStatus('saved');
            } catch (e) {
                console.error('❌ Error guardando datos:', e);
                showDataStatus('error');
            }
        }
        
        function loadFormData() {
            try {
                const savedData = localStorage.getItem('tbv3_form_data');
                if (savedData) {
                    const parsedData = JSON.parse(savedData);
                    
                    // Restaurar datos del vehículo
                    Object.keys(parsedData.vehiculo).forEach(key => {
                        const input = document.getElementById(key);
                        if (input) input.value = parsedData.vehiculo[key];
                    });
                    
                    // Restaurar datos del comprador
                    Object.keys(parsedData.comprador).forEach(key => {
                        const input = document.getElementById(key + '_comprador');
                        if (input) input.value = parsedData.comprador[key];
                    });
                    
                    // Restaurar datos del vendedor
                    Object.keys(parsedData.vendedor).forEach(key => {
                        const input = document.getElementById(key + '_vendedor');
                        if (input) input.value = parsedData.vendedor[key];
                    });
                    
                    console.log('📥 Datos restaurados desde localStorage');
                }
            } catch (e) {
                console.error('❌ Error cargando datos:', e);
            }
        }
        
        function showDataStatus(status) {
            const statusMap = {
                saving: { text: '💾 Guardando datos...', class: 'saving' },
                saved: { text: '✅ Datos guardados', class: 'saved' },
                error: { text: '❌ Error guardando', class: 'error' }
            };
            
            const config = statusMap[status];
            if (config) {
                dataStatus.textContent = config.text;
                dataStatus.className = 'data-status ' + config.class;
                dataStatus.style.display = 'block';
                
                setTimeout(() => {
                    dataStatus.style.display = 'none';
                }, 2000);
            }
        }
        
        // =====================================================
        // SISTEMA DE ARCHIVOS
        // =====================================================
        
        function setupFileUploads() {
            const fileInputs = [
                'file-dni-comprador',
                'file-dni-vendedor', 
                'file-registro-maritimo',
                'file-contrato'
            ];
            
            fileInputs.forEach(inputId => {
                const input = document.getElementById(inputId);
                const statusId = 'status-' + inputId.replace('file-', '');
                const statusElement = document.getElementById(statusId);
                const uploadItem = input?.closest('.upload-item');
                
                if (input) {
                    input.addEventListener('change', function(e) {
                        const file = e.target.files[0];
                        if (file) {
                            // Validar archivo
                            const allowedTypes = ['image/jpeg', 'image/png', 'application/pdf'];
                            const maxSize = 10 * 1024 * 1024; // 10MB
                            
                            if (!allowedTypes.includes(file.type)) {
                                alert('Tipo de archivo no permitido. Use JPG, PNG o PDF.');
                                input.value = '';
                                return;
                            }
                            
                            if (file.size > maxSize) {
                                alert('Archivo muy grande. Máximo 10MB.');
                                input.value = '';
                                return;
                            }
                            
                            // Archivo válido
                            if (statusElement) {
                                statusElement.textContent = '✅ ' + file.name.substring(0, 20);
                                statusElement.style.color = '#10b981';
                            }
                            
                            if (uploadItem) {
                                uploadItem.classList.add('uploaded');
                            }
                            
                            console.log('📎 Archivo subido:', file.name);
                            saveFormData(); // Guardar cambios
                        }
                    });
                }
            });
        }
        
        // =====================================================
        // SISTEMA DE FIRMA DIGITAL
        // =====================================================
        
        let signaturePad;
        
        function setupSignaturePad() {
            const canvas = document.getElementById('signature-pad');
            const clearBtn = document.getElementById('clear-signature');
            
            if (canvas) {
                signaturePad = new SignaturePad(canvas, {
                    backgroundColor: 'white',
                    penColor: '#016d86',
                    minWidth: 1,
                    maxWidth: 3
                });
                
                // Eventos de firma
                signaturePad.addEventListener('endStroke', () => {
                    console.log('✍️ Firma actualizada');
                    saveFormData();
                });
                
                if (clearBtn) {
                    clearBtn.addEventListener('click', () => {
                        signaturePad.clear();
                        console.log('🗑️ Firma limpiada');
                        saveFormData();
                    });
                }
                
                // Responsive signature pad
                function resizeCanvas() {
                    const ratio = Math.max(window.devicePixelRatio || 1, 1);
                    canvas.width = canvas.offsetWidth * ratio;
                    canvas.height = canvas.offsetHeight * ratio;
                    canvas.getContext("2d").scale(ratio, ratio);
                    signaturePad.clear(); // Limpiar después de redimensionar
                }
                
                window.addEventListener('resize', resizeCanvas);
                resizeCanvas();
            }
        }
        
        // =====================================================
        // CÁLCULOS ITP
        // =====================================================
        
        function calculateITP() {
            const valorVehiculo = parseFloat(document.getElementById('valor_vehiculo')?.value) || 0;
            const comunidad = document.getElementById('comunidad_autonoma')?.value || '';
            
            let porcentaje = 0.04; // 4% por defecto
            
            // Porcentajes por comunidad autónoma
            const itpRates = {
                'ISLAS_CANARIAS': 0,
                'MADRID': 0.04,
                'PAIS_VASCO': 0.04,
                'CATALUÑA': 0.04,
                'ANDALUCÍA': 0.04,
                'ARAGÓN': 0.04
            };
            
            if (itpRates[comunidad] !== undefined) {
                porcentaje = itpRates[comunidad];
            }
            
            const itpAmount = valorVehiculo * porcentaje;
            const basePrice = 174.99;
            const total = basePrice + itpAmount;
            
            // Actualizar displays
            const itpDisplay = document.getElementById('itp-amount-display');
            const itpSummary = document.getElementById('itp-summary');
            const totalSummary = document.getElementById('total-summary');
            
            if (itpDisplay) itpDisplay.textContent = itpAmount.toFixed(2) + ' €';
            if (itpSummary) itpSummary.textContent = itpAmount.toFixed(2) + ' €';
            if (totalSummary) totalSummary.textContent = total.toFixed(2) + ' €';
            
            // Guardar en formData
            formData.itp = {
                amount: itpAmount,
                porcentaje: porcentaje,
                comunidad: comunidad,
                total: total
            };
            
            console.log('🧮 ITP calculado:', itpAmount.toFixed(2) + '€');
        }
        
        // =====================================================
        // RESUMEN DE DATOS
        // =====================================================
        
        function updateDataSummary() {
            const summaryContent = document.getElementById('summary-content');
            if (!summaryContent) return;
            
            const vehiculo = formData.vehiculo;
            const comprador = formData.comprador;
            const archivos = formData.archivos;
            
            summaryContent.innerHTML = `
                <div style="margin-bottom: 16px;">
                    <strong style="color: #016d86;">🚢 Embarcación:</strong><br>
                    <small>${vehiculo.manufacturer} ${vehiculo.model} - ${vehiculo.matricula}</small>
                </div>
                <div style="margin-bottom: 16px;">
                    <strong style="color: #016d86;">👤 Comprador:</strong><br>
                    <small>${comprador.nombre} ${comprador.apellidos} - ${comprador.dni}</small>
                </div>
                <div style="margin-bottom: 16px;">
                    <strong style="color: #016d86;">📁 Documentos:</strong><br>
                    <small>
                        DNI Comprador: ${archivos.dni_comprador}<br>
                        DNI Vendedor: ${archivos.dni_vendedor}<br>
                        Registro: ${archivos.registro_maritimo}<br>
                        Firma: ${formData.firma ? 'Firmado' : 'Sin firmar'}
                    </small>
                </div>
            `;
        }
        
        // =====================================================
        // EVENT LISTENERS
        // =====================================================
        
        // Navegación
        navItems.forEach((item, index) => {
            item.addEventListener('click', (e) => {
                e.preventDefault();
                if (index <= currentPage + 1) {
                    saveFormData();
                    showPage(index);
                }
            });
        });
        
        btnSiguiente.addEventListener('click', () => {
            if (currentPage < pages.length - 1) {
                saveFormData();
                showPage(currentPage + 1);
            } else {
                // Último paso: preparar para TPV
                handleFinalSubmit();
            }
        });
        
        btnAnterior.addEventListener('click', () => {
            if (currentPage > 0) {
                showPage(currentPage - 1);
            }
        });
        
        // Auto-guardado en cambios de inputs
        document.addEventListener('input', (e) => {
            if (e.target.matches('input, select, textarea')) {
                saveFormData();
                
                // Recalcular ITP si es necesario
                if (e.target.id === 'valor_vehiculo' || e.target.id === 'comunidad_autonoma') {
                    calculateITP();
                }
            }
        });
        
        // =====================================================
        // PREPARACIÓN PARA TPV
        // =====================================================
        
        function handleFinalSubmit() {
            console.log('💳 Preparando datos para TPV...');
            
            // Actualizar resumen final
            saveFormData();
            updateDataSummary();
            
            // Validar datos críticos
            const validation = validateFormData();
            if (!validation.valid) {
                alert('⚠️ Faltan datos obligatorios:\n' + validation.errors.join('\n'));
                return;
            }
            
            // Preparar datos para TPV
            const tpvData = prepareTPVData();
            
            // Guardar datos finales para recuperación post-pago
            localStorage.setItem('tbv3_final_data', JSON.stringify(tpvData));
            localStorage.setItem('tbv3_ready_for_tpv', 'true');
            
            alert('🧪 V3 TESTING: Datos preparados para TPV\n\n' +
                  'Total: ' + formData.itp.total.toFixed(2) + '€\n' +
                  'Datos guardados para sincronización post-pago\n\n' +
                  '⏳ Próximo paso: Integrar TPV Redsys real');
            
            console.log('✅ Datos TPV preparados:', tpvData);
        }
        
        function validateFormData() {
            const errors = [];
            
            // Validar vehículo
            if (!formData.vehiculo.matricula) errors.push('- Matrícula del vehículo');
            if (!formData.vehiculo.manufacturer) errors.push('- Fabricante');
            if (!formData.vehiculo.valor_vehiculo) errors.push('- Valor del vehículo');
            if (!formData.vehiculo.comunidad_autonoma) errors.push('- Comunidad autónoma');
            
            // Validar comprador
            if (!formData.comprador.nombre) errors.push('- Nombre del comprador');
            if (!formData.comprador.dni) errors.push('- DNI del comprador');
            if (!formData.comprador.email) errors.push('- Email del comprador');
            
            // Validar vendedor
            if (!formData.vendedor.nombre) errors.push('- Nombre del vendedor');
            if (!formData.vendedor.dni) errors.push('- DNI del vendedor');
            
            return {
                valid: errors.length === 0,
                errors: errors
            };
        }
        
        function prepareTPVData() {
            return {
                // Datos para webhook API
                tramiteType: 'transferencia-barcos',
                vehicleType: 'Barco',
                customerName: formData.comprador.nombre + ' ' + formData.comprador.apellidos,
                customerDni: formData.comprador.dni,
                customerEmail: formData.comprador.email,
                customerPhone: formData.comprador.telefono,
                sellerName: formData.vendedor.nombre + ' ' + formData.vendedor.apellidos,
                sellerDni: formData.vendedor.dni,
                vehicleData: formData.vehiculo,
                itpData: formData.itp,
                pricing: {
                    finalAmount: formData.itp.total,
                    tasas: 52.60,
                    honorariosBrutos: formData.itp.total - 52.60,
                    honorariosNetos: (formData.itp.total - 52.60) / 1.21,
                    iva: (formData.itp.total - 52.60) - ((formData.itp.total - 52.60) / 1.21)
                },
                attachments: formData.archivos,
                signature: formData.firma,
                metadata: {
                    source: 'transferencia-barco-v3',
                    version: '3.0.1',
                    timestamp: new Date().toISOString(),
                    user_agent: navigator.userAgent
                }
            };
        }
        
        // =====================================================
        // INICIALIZACIÓN
        // =====================================================
        
        function initialize() {
            console.log('🔧 Inicializando TBV3 completo...');
            
            // Cargar datos guardados
            loadFormData();
            
            // Configurar componentes
            setupFileUploads();
            setupSignaturePad();
            
            // Calcular ITP inicial
            calculateITP();
            
            // Mostrar primera página
            showPage(0);
            
            console.log('✅ TBV3 inicializado correctamente');
            console.log('📋 Funcionalidades activas:');
            console.log('  ✅ Navegación multi-página');
            console.log('  ✅ Sidebar dinámico por página');
            console.log('  ✅ Sistema de captura de datos');
            console.log('  ✅ Upload de archivos con validación');
            console.log('  ✅ Firma digital responsive');
            console.log('  ✅ Cálculo ITP automático');
            console.log('  ✅ Persistencia localStorage');
            console.log('  ✅ Preparación para TPV');
            console.log('⏳ Listo para integrar TPV Redsys');
        }
        
        // Inicializar al cargar el DOM
        initialize();
    });
    </script>

    <?php
    return ob_get_clean();
}

// ✅ REGISTRO DEL SHORTCODE V3 COMPLETO
function tbv3_completo_register_shortcode() {
    add_shortcode('transferencia_barco_v3_completo', 'transferencia_barco_v3_completo_shortcode');
}
add_action('init', 'tbv3_completo_register_shortcode');

// Log de carga exitosa
if (function_exists('error_log')) {
    error_log('TBV3 COMPLETO: Sistema completo de captura de datos cargado correctamente');
}