<?php
/**
 * TRAMITFY - TRANSFERENCIA BARCO V3
 * 
 * ✅ ESTRUCTURA VISUAL IDÉNTICA AL ORIGINAL
 * ✅ Empezamos desde cero con diseño funcional
 * ✅ Layout dos columnas exacto
 * ✅ Posteriormente: integración TPV paso a paso
 * 
 * @version 3.0.0 - NUEVO DESDE CERO
 * @author Claude Code  
 * @created 2025-11-13
 * @reference transferencia-barco.php (estructura visual)
 */

// Acceso solo via WordPress
if (!defined('ABSPATH')) {
    exit('Acceso directo no permitido.');
}

/**
 * ✅ FUNCIÓN PRINCIPAL DEL SHORTCODE
 * Estructura idéntica al original
 */
function transferencia_barco_v3_shortcode() {
    // Encolar scripts necesarios
    wp_enqueue_script('signature-pad', 'https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js', array(), null, false);
    
    // Iniciar buffering
    ob_start();
    ?>
    
    <!-- CSS IDÉNTICO AL ORIGINAL -->
    <style>
        /* RESET GLOBAL PARA ELIMINAR ESPACIOS EN BLANCO */
        * {
            box-sizing: border-box;
        }
        
        html, body {
            margin: 0 !important;
            padding: 0 !important;
            box-sizing: border-box !important;
        }
        
        /* Variables de color - Esquema formal verdoso/azul-gris */
        :root {
            --primary: 1, 109, 134; /* Color #016d86 - Verde/azul corporativo principal */
            --primary-dark: 0, 86, 106;
            --primary-light: 0, 125, 156;
            --primary-bg: 236, 247, 255;
            
            --secondary: 0, 123, 255; /* Azul #007bff */
            --secondary-dark: 0, 105, 217;
            --secondary-light: 50, 145, 255;
            --secondary-bg: 235, 245, 253;
            
            --neutral: 70, 80, 95;
            --neutral-dark: 44, 62, 80;
            --neutral-medium: 127, 140, 141;
            --neutral-light: 189, 195, 199;
            
            --success: 40, 167, 69;
            --warning: 243, 156, 18;
            --error: 231, 76, 60;
            --info: 0, 123, 255;
            
            --spacing-xs: 0.25rem;
            --spacing-sm: 0.5rem;
            --spacing-md: 1rem;
            --spacing-lg: 1.5rem;
            --spacing-xl: 2rem;
            
            --radius-sm: 0.25rem;
            --radius-md: 0.375rem;
            --radius-lg: 0.5rem;
            --radius-xl: 0.75rem;
            
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
            border: none;
            border-radius: 16px;
            font-family: 'Roboto', 'Helvetica Neue', Helvetica, Arial, sans-serif;
            background-color: transparent;
            box-shadow: none;
        }

        /* LAYOUT DE DOS COLUMNAS - IDÉNTICO */
        .tramitfy-layout-wrapper {
            width: 100%;
            margin: 0 auto;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
            overflow-y: auto;
        }

        .tramitfy-two-column {
            display: grid;
            grid-template-columns: 380px 1fr;
            min-height: 100vh;
            position: relative;
        }

        /* SIDEBAR IZQUIERDO */
        .tramitfy-sidebar {
            background: linear-gradient(180deg, #016d86 0%, #014d5f 100%);
            padding: 32px 24px;
            color: white;
            overflow-y: auto;
            position: sticky;
            top: 0;
            height: 100vh;
            box-shadow: 4px 0 20px rgba(0,0,0,0.15);
        }

        .sidebar-content {
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .sidebar-header h3 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 8px;
            color: white;
        }

        .sidebar-header p {
            font-size: 14px;
            color: rgba(255,255,255,0.8);
            margin-bottom: 24px;
        }

        /* PANEL PRINCIPAL DERECHO */
        .tramitfy-main {
            padding: 40px 48px;
            background: white;
            overflow-y: auto;
            position: relative;
        }

        .tramitfy-main h2 {
            font-size: 32px;
            font-weight: 700;
            color: #1a202c;
            margin-bottom: 12px;
            text-align: left;
        }

        .tramitfy-main .subtitle {
            color: #666;
            margin-bottom: 32px;
            font-size: 15px;
            line-height: 1.6;
        }

        /* PÁGINAS/PASOS DEL FORMULARIO */
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

        /* MENÚ DE NAVEGACIÓN SUPERIOR */
        .nav-progress-bar {
            display: flex;
            justify-content: center;
            padding: 20px 0;
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            position: sticky;
            top: 0;
            z-index: 10;
            margin-bottom: 24px;
        }

        .navigation-menu {
            display: flex;
            gap: 0;
            background: white;
            border-radius: 50px;
            padding: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            border: 2px solid #e5e7eb;
        }

        .nav-item {
            padding: 12px 24px;
            border-radius: 50px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
            font-size: 14px;
            color: #6b7280;
            display: flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }

        .nav-item.active {
            background: #016d86;
            color: white;
            box-shadow: 0 2px 8px rgba(1, 109, 134, 0.3);
        }

        .nav-item.completed {
            background: #10b981;
            color: white;
        }

        .nav-item:hover:not(.active) {
            background: #f3f4f6;
            color: #374151;
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

        /* RESPONSIVO */
        @media (max-width: 1024px) {
            .tramitfy-two-column {
                grid-template-columns: 1fr;
            }
            
            .tramitfy-sidebar {
                position: relative;
                height: auto;
                min-height: 200px;
            }
            
            .tramitfy-main {
                padding: 24px;
            }
        }

        @media (max-width: 768px) {
            .form-compact-row,
            .form-compact-triple {
                grid-template-columns: 1fr;
                gap: 16px;
            }
            
            .upload-row {
                grid-template-columns: 1fr;
            }
            
            .navigation-menu {
                flex-wrap: wrap;
                border-radius: 16px;
            }
            
            .nav-item {
                padding: 8px 16px;
                font-size: 12px;
            }
        }

        /* TESTING STYLES */
        .test-info {
            background: linear-gradient(135deg, #10b981, #016d86);
            color: white;
            padding: 24px;
            border-radius: 12px;
            margin-bottom: 24px;
            text-align: center;
        }

        .test-info h3 {
            margin: 0 0 12px 0;
            font-size: 20px;
        }

        .test-info p {
            margin: 0;
            opacity: 0.9;
            font-size: 14px;
        }
    </style>

    <!-- HTML PRINCIPAL - ESTRUCTURA IDÉNTICA -->
    <form id="transferencia-form-v3" method="POST" enctype="multipart/form-data">
        
        <div class="tramitfy-layout-wrapper">
            <div class="tramitfy-two-column">

                <!-- SIDEBAR IZQUIERDO -->
                <aside class="tramitfy-sidebar">
                    <div class="sidebar-content">
                        <div class="sidebar-header">
                            <h3>🚢 Transferencia Embarcación V3</h3>
                            <p>Estructura visual idéntica al original</p>
                        </div>

                        <!-- Contenido dinámico del sidebar -->
                        <div id="sidebar-dynamic-content" style="margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid rgba(255,255,255,0.2);">
                            <div style="background: rgba(255,255,255,0.1); padding: 16px; border-radius: 8px;">
                                <div style="font-weight: 600; margin-bottom: 8px;">✅ Paso Actual: Datos Básicos</div>
                                <div style="font-size: 13px; opacity: 0.8;">Complete la información del vehículo</div>
                            </div>
                        </div>

                        <!-- Widget de confianza -->
                        <div style="background: rgba(255,255,255,0.05); padding: 20px; border-radius: 12px; margin-bottom: 24px;">
                            <div style="text-align: center; margin-bottom: 16px;">
                                <div style="font-size: 24px; margin-bottom: 8px;">⭐⭐⭐⭐⭐</div>
                                <div style="font-weight: 600;">Más de 1000+ clientes</div>
                                <div style="font-size: 13px; opacity: 0.8;">Transferencias exitosas</div>
                            </div>
                        </div>

                        <!-- Información de precios -->
                        <div style="background: rgba(255,255,255,0.1); padding: 20px; border-radius: 12px;">
                            <div style="font-weight: 600; margin-bottom: 12px;">💰 Precio Incluye:</div>
                            <ul style="margin: 0; padding-left: 20px; font-size: 13px; opacity: 0.9;">
                                <li>Gestión completa</li>
                                <li>Documentación oficial</li>
                                <li>Asesoramiento legal</li>
                                <li>Seguimiento hasta finalización</li>
                            </ul>
                        </div>
                    </div>
                </aside>

                <!-- PANEL PRINCIPAL DERECHO -->
                <main class="tramitfy-main">
                    
                    <!-- MENÚ DE NAVEGACIÓN -->
                    <div class="nav-progress-bar">
                        <div class="navigation-menu">
                            <div class="nav-item active" data-page="datos">
                                <span class="nav-icon">📋</span>
                                <span class="nav-text">Datos</span>
                            </div>
                            <div class="nav-item" data-page="documentos">
                                <span class="nav-icon">📁</span>
                                <span class="nav-text">Documentos</span>
                            </div>
                            <div class="nav-item" data-page="firma">
                                <span class="nav-icon">✍️</span>
                                <span class="nav-text">Firma</span>
                            </div>
                            <div class="nav-item" data-page="pago">
                                <span class="nav-icon">💳</span>
                                <span class="nav-text">Pago</span>
                            </div>
                        </div>
                    </div>

                    <!-- PÁGINA 1: DATOS BÁSICOS -->
                    <div id="page-datos" class="form-page active">
                        
                        <div class="test-info">
                            <h3>🧪 V3 - Testing Estructura Visual</h3>
                            <p>Layout de dos columnas funcionando correctamente. Próximo paso: integrar TPV.</p>
                        </div>

                        <h2>Datos de la Transferencia</h2>
                        <p class="subtitle">Complete la información básica del vehículo y las partes involucradas.</p>

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

                        <!-- Datos del Vehículo -->
                        <h3 style="color: #016d86; margin: 32px 0 16px 0;">🚢 Datos de la Embarcación</h3>
                        <div class="form-compact-triple">
                            <div class="form-group">
                                <label for="matricula">Matrícula *</label>
                                <input type="text" id="matricula" name="matricula" required>
                            </div>
                            <div class="form-group">
                                <label for="marca">Marca *</label>
                                <input type="text" id="marca" name="marca" required>
                            </div>
                            <div class="form-group">
                                <label for="modelo">Modelo *</label>
                                <input type="text" id="modelo" name="modelo" required>
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

                    <!-- PÁGINA 2: DOCUMENTOS -->
                    <div id="page-documentos" class="form-page">
                        <h2>Documentos Requeridos</h2>
                        <p class="subtitle">Suba los documentos necesarios para procesar la transferencia.</p>

                        <div class="upload-grid">
                            <div class="upload-row">
                                <div class="upload-item">
                                    <div style="font-size: 32px; margin-bottom: 12px; color: #016d86;">📄</div>
                                    <div style="font-weight: 600; margin-bottom: 8px;">DNI Comprador</div>
                                    <input type="file" name="dni_comprador" accept=".pdf,.jpg,.png" style="width: 100%; padding: 8px;">
                                    <div style="font-size: 12px; color: #666; margin-top: 8px;">PDF, JPG o PNG</div>
                                </div>
                                <div class="upload-item">
                                    <div style="font-size: 32px; margin-bottom: 12px; color: #016d86;">📄</div>
                                    <div style="font-weight: 600; margin-bottom: 8px;">DNI Vendedor</div>
                                    <input type="file" name="dni_vendedor" accept=".pdf,.jpg,.png" style="width: 100%; padding: 8px;">
                                    <div style="font-size: 12px; color: #666; margin-top: 8px;">PDF, JPG o PNG</div>
                                </div>
                            </div>

                            <div class="upload-row">
                                <div class="upload-item">
                                    <div style="font-size: 32px; margin-bottom: 12px; color: #016d86;">⚓</div>
                                    <div style="font-weight: 600; margin-bottom: 8px;">Registro Marítimo</div>
                                    <input type="file" name="registro_maritimo" accept=".pdf,.jpg,.png" style="width: 100%; padding: 8px;">
                                    <div style="font-size: 12px; color: #666; margin-top: 8px;">Documentación del barco</div>
                                </div>
                                <div class="upload-item">
                                    <div style="font-size: 32px; margin-bottom: 12px; color: #016d86;">📋</div>
                                    <div style="font-weight: 600; margin-bottom: 8px;">Contrato Compraventa</div>
                                    <input type="file" name="contrato" accept=".pdf,.jpg,.png" style="width: 100%; padding: 8px;">
                                    <div style="font-size: 12px; color: #666; margin-top: 8px;">Si ya existe</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- PÁGINA 3: FIRMA -->
                    <div id="page-firma" class="form-page">
                        <h2>Firma Digital</h2>
                        <p class="subtitle">Firme digitalmente para autorizar la transferencia.</p>

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
                        <p class="subtitle">Revise los datos y complete el pago para procesar su transferencia.</p>

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

                </main>

            </div>
        </div>

    </form>

    <!-- JAVASCRIPT BÁSICO PARA NAVEGACIÓN -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log('🚀 TBV3 - Estructura visual cargada correctamente');
        
        // Variables globales
        let currentPage = 0;
        const pages = ['datos', 'documentos', 'firma', 'pago'];
        const navItems = document.querySelectorAll('.nav-item');
        const formPages = document.querySelectorAll('.form-page');
        const btnAnterior = document.getElementById('btn-anterior');
        const btnSiguiente = document.getElementById('btn-siguiente');
        
        // Navegación entre páginas
        function showPage(pageIndex) {
            // Ocultar todas las páginas
            formPages.forEach(page => {
                page.classList.remove('active');
            });
            
            // Mostrar página actual
            const currentPageElement = document.getElementById(`page-${pages[pageIndex]}`);
            if (currentPageElement) {
                currentPageElement.classList.add('active');
            }
            
            // Actualizar navegación
            navItems.forEach((item, index) => {
                item.classList.remove('active', 'completed');
                if (index === pageIndex) {
                    item.classList.add('active');
                } else if (index < pageIndex) {
                    item.classList.add('completed');
                }
            });
            
            // Actualizar botones
            btnAnterior.style.display = pageIndex === 0 ? 'none' : 'inline-flex';
            btnSiguiente.textContent = pageIndex === pages.length - 1 ? 'Finalizar' : 'Siguiente →';
            
            // Actualizar sidebar dinámico
            updateSidebarContent(pageIndex);
            
            currentPage = pageIndex;
        }
        
        // Actualizar contenido dinámico del sidebar
        function updateSidebarContent(pageIndex) {
            const sidebarContent = document.getElementById('sidebar-dynamic-content');
            const pageNames = ['Datos Básicos', 'Documentos', 'Firma Digital', 'Pago'];
            const pageDescs = [
                'Complete la información del vehículo',
                'Suba los documentos requeridos',
                'Firme digitalmente la autorización',
                'Revise y complete el pago'
            ];
            
            if (sidebarContent) {
                sidebarContent.innerHTML = `
                    <div style="background: rgba(255,255,255,0.1); padding: 16px; border-radius: 8px;">
                        <div style="font-weight: 600; margin-bottom: 8px;">✅ Paso Actual: ${pageNames[pageIndex]}</div>
                        <div style="font-size: 13px; opacity: 0.8;">${pageDescs[pageIndex]}</div>
                    </div>
                `;
            }
        }
        
        // Event listeners para navegación
        navItems.forEach((item, index) => {
            item.addEventListener('click', () => {
                if (index <= currentPage + 1) { // Solo permitir avanzar una página
                    showPage(index);
                }
            });
        });
        
        btnSiguiente.addEventListener('click', () => {
            if (currentPage < pages.length - 1) {
                showPage(currentPage + 1);
            } else {
                alert('🧪 V3 Testing: Aquí se integraría el TPV en la siguiente fase');
            }
        });
        
        btnAnterior.addEventListener('click', () => {
            if (currentPage > 0) {
                showPage(currentPage - 1);
            }
        });
        
        // Cálculo simple ITP
        const valorVehiculo = document.getElementById('valor_vehiculo');
        const comunidadAutonoma = document.getElementById('comunidad_autonoma');
        const itpDisplay = document.getElementById('itp-amount-display');
        const itpSummary = document.getElementById('itp-summary');
        const totalSummary = document.getElementById('total-summary');
        
        function calculateITP() {
            const valor = parseFloat(valorVehiculo.value) || 0;
            const comunidad = comunidadAutonoma.value;
            
            let porcentaje = 0.04; // 4% por defecto
            
            if (comunidad === 'ISLAS_CANARIAS') {
                porcentaje = 0; // Canarias exento
            } else if (comunidad === 'MADRID') {
                porcentaje = 0.04;
            } else if (comunidad === 'PAIS_VASCO') {
                porcentaje = 0.04;
            }
            
            const itp = valor * porcentaje;
            const total = 174.99 + itp;
            
            if (itpDisplay) itpDisplay.textContent = itp.toFixed(2) + ' €';
            if (itpSummary) itpSummary.textContent = itp.toFixed(2) + ' €';
            if (totalSummary) totalSummary.textContent = total.toFixed(2) + ' €';
        }
        
        if (valorVehiculo) valorVehiculo.addEventListener('input', calculateITP);
        if (comunidadAutonoma) comunidadAutonoma.addEventListener('change', calculateITP);
        
        // Firma digital básica
        const canvas = document.getElementById('signature-pad');
        const clearBtn = document.getElementById('clear-signature');
        
        if (canvas && clearBtn) {
            const ctx = canvas.getContext('2d');
            let isDrawing = false;
            
            canvas.addEventListener('mousedown', () => isDrawing = true);
            canvas.addEventListener('mouseup', () => isDrawing = false);
            canvas.addEventListener('mousemove', (e) => {
                if (!isDrawing) return;
                const rect = canvas.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;
                
                ctx.lineWidth = 2;
                ctx.lineCap = 'round';
                ctx.strokeStyle = '#016d86';
                
                if (e.type === 'mousedown') {
                    ctx.beginPath();
                    ctx.moveTo(x, y);
                } else {
                    ctx.lineTo(x, y);
                    ctx.stroke();
                }
            });
            
            clearBtn.addEventListener('click', () => {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
            });
        }
        
        // Inicializar
        showPage(0);
        
        console.log('📋 V3 navegación inicializada correctamente');
        console.log('⏳ Próximo paso: integrar TPV Redsys paso a paso');
    });
    </script>

    <?php
    return ob_get_clean();
}

// ✅ REGISTRO DEL SHORTCODE
function tbv3_register_shortcode() {
    add_shortcode('transferencia_barco_v3', 'transferencia_barco_v3_shortcode');
}
add_action('init', 'tbv3_register_shortcode');

// Log de carga exitosa
if (function_exists('error_log')) {
    error_log('TBV3: Formulario V3 cargado correctamente - Estructura visual idéntica');
}