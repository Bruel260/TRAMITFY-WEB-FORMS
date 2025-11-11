<?php
/**
 * TRAMITFY - TRANSFERENCIA EMBARCACIONES V2
 * WordPress Compatible - Sistema Firma Digital
 * 
 * @version 2.3.0 - WORDPRESS EDITION
 * @author Claude Code
 * @created 2025-11-09
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Función principal del formulario - WordPress compatible
 */
function tbv2_render_main_content() {
    ob_start();
    ?>
    
    <!-- Font Awesome para iconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* Variables de color */
        :root {
            --primary: 1, 109, 134;
            --primary-dark: 0, 86, 106;
            --primary-light: 0, 125, 156;
            
            --neutral-50: 248, 249, 250;
            --neutral-100: 241, 243, 244;
            --neutral-200: 233, 236, 239;
            --neutral-300: 222, 226, 230;
            --neutral-400: 206, 212, 218;
            --neutral-500: 173, 181, 189;
            --neutral-600: 108, 117, 125;
            --neutral-700: 73, 80, 87;
            --neutral-800: 52, 58, 64;
            --neutral-900: 33, 37, 41;
            
            --success: 40, 167, 69;
            --warning: 243, 156, 18;
            --error: 231, 76, 60;
            --info: 0, 123, 255;
        }

        /* Reset y estilos globales */
        * {
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: rgb(var(--neutral-800));
        }

        /* Container principal - Grid de 2 columnas */
        .tbv2-container {
            max-width: 1400px;
            margin: 25px auto 40px auto;
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
            display: grid;
            grid-template-columns: 380px 1fr;
            min-height: fit-content;
            align-items: stretch;
        }

        /* SIDEBAR IZQUIERDO */
        .tbv2-sidebar {
            background: linear-gradient(180deg, rgb(var(--primary)) 0%, rgb(var(--primary-dark)) 100%);
            color: white;
            padding: 30px 25px 40px 25px;
            display: flex;
            flex-direction: column;
            gap: 25px;
            min-height: 100%;
            position: relative;
        }

        /* Asegurar que todos los títulos en sidebar tengan texto blanco sin fondo */
        .tbv2-sidebar h1, .tbv2-sidebar h2, .tbv2-sidebar h3, .tbv2-sidebar h4, .tbv2-sidebar h5, .tbv2-sidebar h6 {
            color: white !important;
            background: none !important;
            background-color: transparent !important;
        }

        .tbv2-logo {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .tbv2-logo i {
            font-size: 28px;
        }

        .tbv2-headline {
            font-size: 18px;
            font-weight: 600;
            line-height: 1.3;
            margin-bottom: 8px;
            background: transparent;
            color: white;
            padding: 12px 16px;
            border-radius: 8px;
            margin: -5px -5px 15px -5px;
        }

        .tbv2-subheadline {
            font-size: 13px;
            opacity: 0.92;
            line-height: 1.4;
            margin-bottom: 10px;
        }

        /* Caja de precio destacada */
        .tbv2-price-box {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            padding: 15px;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.25);
            margin: 15px 0 20px 0;
        }

        .tbv2-price-label {
            font-size: 11px;
            opacity: 0.85;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 6px;
        }

        .tbv2-price-amount {
            font-size: 38px;
            font-weight: 700;
            margin: 4px 0;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .tbv2-price-detail {
            font-size: 12px;
            opacity: 0.88;
        }

        /* Lista de beneficios */
        .tbv2-benefits {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin: 15px 0 20px 0;
        }

        .tbv2-reviews-widget {
            margin-top: 25px;
            padding: 25px 15px 20px 15px;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(5px);
        }

        .tbv2-benefit {
            display: flex;
            align-items: start;
            gap: 8px;
            font-size: 12px;
            line-height: 1.4;
        }

        .tbv2-benefit i {
            font-size: 14px;
            color: rgb(var(--success));
            background: white;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            margin-top: 1px;
        }

        /* Trust badges */
        .tbv2-trust-badges {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: auto;
            padding-top: 30px;
            border-top: 1px solid rgba(255, 255, 255, 0.15);
        }

        .tbv2-badge {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            padding: 8px 12px;
            border-radius: 20px;
            font-size: 10px;
            display: flex;
            align-items: center;
            gap: 6px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .tbv2-badge i {
            font-size: 11px;
        }
        
        .tbv2-badge:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        /* ÁREA PRINCIPAL DEL FORMULARIO */
        .tbv2-form-area {
            padding: 30px 40px 50px 40px;
            background: linear-gradient(135deg, #fafbfc 0%, #f8f9fa 100%);
            overflow-y: auto;
        }

        /* Responsivo */
        @media (max-width: 1024px) {
            .tbv2-container {
                grid-template-columns: 1fr;
                max-width: 800px;
                margin: 20px auto;
            }
            
            .tbv2-sidebar {
                padding: 25px 20px;
            }
            
            .tbv2-form-area {
                padding: 25px 20px 40px 20px;
            }
        }
    </style>

    <div class="tbv2-container">
        
        <!-- SIDEBAR IZQUIERDO -->
        <div class="tbv2-sidebar">
            <div class="tbv2-logo">
                <i class="fa-solid fa-anchor"></i>
                Tramitfy
            </div>
            
            <div class="tbv2-headline">
                Transferencia de Embarcación
            </div>
            
            <div class="tbv2-subheadline">
                Gestión completa del cambio de titularidad con firma digital y trámites ante la DGMM
            </div>
            
            <!-- Caja de precio -->
            <div class="tbv2-price-box">
                <div class="tbv2-price-label">Precio Final</div>
                <div class="tbv2-price-amount">Variable</div>
                <div class="tbv2-price-detail">Según características de la embarcación</div>
            </div>
            
            <!-- Lista de beneficios -->
            <div class="tbv2-benefits">
                <div class="tbv2-benefit">
                    <i class="fa-solid fa-check"></i>
                    <span>Gestión completa ante DGMM</span>
                </div>
                <div class="tbv2-benefit">
                    <i class="fa-solid fa-check"></i>
                    <span>Firma digital de documentos</span>
                </div>
                <div class="tbv2-benefit">
                    <i class="fa-solid fa-check"></i>
                    <span>Tramitación de tasas incluida</span>
                </div>
                <div class="tbv2-benefit">
                    <i class="fa-solid fa-check"></i>
                    <span>Seguimiento en tiempo real</span>
                </div>
                <div class="tbv2-benefit">
                    <i class="fa-solid fa-check"></i>
                    <span>Documentación certificada</span>
                </div>
            </div>
            
            <!-- Trust badges -->
            <div class="tbv2-trust-badges">
                <div class="tbv2-badge">
                    <i class="fa-solid fa-shield-check"></i>
                    Seguro
                </div>
                <div class="tbv2-badge">
                    <i class="fa-solid fa-clock"></i>
                    24-48h
                </div>
                <div class="tbv2-badge">
                    <i class="fa-solid fa-certificate"></i>
                    Oficial
                </div>
            </div>
        </div>
        
        <!-- ÁREA PRINCIPAL DEL FORMULARIO -->
        <div class="tbv2-form-area">
            <form id="tbv2-main-form">
                
                <!-- Datos del Comprador -->
                <div style="margin-bottom: 25px;">
                    <h3 style="color: #374151; margin-bottom: 15px; border-bottom: 2px solid #3b82f6; padding-bottom: 10px;">Datos del Comprador</h3>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                    <div>
                        <label style="display: block; margin-bottom: 5px; color: #374151; font-weight: 500;">Nombre completo *</label>
                        <input type="text" name="buyer_name" required style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 5px; color: #374151; font-weight: 500;">DNI/NIE *</label>
                        <input type="text" name="buyer_dni" required style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 15px; margin-bottom: 15px;">
                    <div>
                        <label style="display: block; margin-bottom: 5px; color: #374151; font-weight: 500;">Email *</label>
                        <input type="email" name="buyer_email" required style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 5px; color: #374151; font-weight: 500;">Teléfono *</label>
                        <input type="tel" name="buyer_phone" required style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
                    </div>
                </div>
            </div>
            
            <!-- Datos de la Embarcación -->
            <div style="margin-bottom: 25px;">
                <h3 style="color: #374151; margin-bottom: 15px; border-bottom: 2px solid #3b82f6; padding-bottom: 10px;">Datos de la Embarcación</h3>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                    <div>
                        <label style="display: block; margin-bottom: 5px; color: #374151; font-weight: 500;">Nombre/Matrícula *</label>
                        <input type="text" name="boat_name" required style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 5px; color: #374151; font-weight: 500;">Eslora (metros) *</label>
                        <input type="number" name="boat_length" step="0.01" required style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div>
                        <label style="display: block; margin-bottom: 5px; color: #374151; font-weight: 500;">Fabricante</label>
                        <input type="text" name="boat_manufacturer" style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 5px; color: #374151; font-weight: 500;">Año de fabricación</label>
                        <input type="number" name="boat_year" min="1900" max="2025" style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
                    </div>
                </div>
            </div>
            
            <!-- Sistema de Firma Digital -->
            <div style="margin: 30px 0; text-align: center; padding: 25px; background: #f9fafb; border-radius: 8px; border: 2px dashed #d1d5db;">
                <h3 style="color: #374151; margin-bottom: 15px;">Firma Digital de Documentos</h3>
                <p style="color: #6b7280; margin-bottom: 20px; font-size: 14px;">Debe firmar digitalmente los documentos de autorización para procesar la transferencia</p>
                
                <button type="button" id="signature-field" style="padding: 15px 30px; background: #3b82f6; color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 16px; font-weight: 500; transition: background 0.2s;">
                    <i class="fa-solid fa-signature"></i> Firmar Documentos
                </button>
                
                <div id="signature-status" style="margin-top: 15px; font-size: 14px; color: #6b7280;"></div>
            </div>
            
            <!-- Botón de Envío -->
            <div style="text-align: center; margin-top: 30px;">
                <button type="submit" style="padding: 15px 40px; background: #10b981; color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 16px; font-weight: 500; transition: background 0.2s;" onmouseover="this.style.background='#059669'" onmouseout="this.style.background='#10b981'">
                    <i class="fa-solid fa-paper-plane"></i> Enviar Solicitud
                </button>
            </div>
            
            </form>
        </div>
        
    </div>
    
    <!-- Modales del Sistema de Firma -->
    <div id="doc-modal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.8); z-index:999999;">
        <div style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); background:white; padding:30px; border-radius:12px; max-width:600px; width:90%; box-shadow: 0 20px 40px rgba(0,0,0,0.3);">
            
            <div style="text-align: center; margin-bottom: 25px;">
                <h3 style="margin:0 0 15px 0; color:#1f2937; font-size: 20px;">📄 Documento de Autorización</h3>
                <p style="color: #6b7280; font-size: 14px; margin: 0;">Lea cuidadosamente antes de proceder a firmar</p>
            </div>
            
            <div style="max-height: 300px; overflow-y: auto; margin: 20px 0; padding: 20px; background: #f9fafb; border-radius: 8px; line-height: 1.6;">
                <h4 style="color:#1f2937; margin-bottom: 15px; text-align: center;">AUTORIZACIÓN PARA TRAMITACIÓN ANTE DGMM</h4>
                
                <p style="margin-bottom: 12px; color: #374151;">Por la presente, <strong>AUTORIZO expresamente</strong> a Tramitfy S.L. para que, en mi nombre y representación, realice todos los trámites necesarios ante la Dirección General de la Marina Mercante (DGMM) para gestionar el cambio de titularidad de la embarcación especificada en este formulario.</p>
                
                <p style="margin-bottom: 12px; color: #374151;">Esta autorización incluye expresamente las siguientes facultades:</p>
                
                <ul style="margin-bottom: 15px; padding-left: 20px; color: #374151;">
                    <li style="margin-bottom: 8px;">Presentar toda la documentación requerida por la DGMM</li>
                    <li style="margin-bottom: 8px;">Realizar el pago de tasas administrativas en mi nombre</li>
                    <li style="margin-bottom: 8px;">Firmar los documentos oficiales necesarios para la tramitación</li>
                    <li style="margin-bottom: 8px;">Retirar los certificados y documentación oficial resultante</li>
                    <li style="margin-bottom: 8px;">Gestionar cualquier incidencia que pueda surgir durante el proceso</li>
                </ul>
                
                <div style="background: #fef3c7; border: 2px solid #f59e0b; border-radius: 8px; padding: 15px; margin: 15px 0; text-align: center;">
                    <p style="margin: 0; font-weight: 600; color: #92400e; font-size: 14px;">
                        ⚠️ <strong>IMPORTANTE:</strong> Al firmar este documento, acepta expresamente todos los términos y condiciones de esta autorización.
                    </p>
                </div>
            </div>
            
            <div style="text-align:center; margin-top:25px; display: flex; gap: 15px; justify-content: center;">
                <button onclick="closeDocModal()" style="padding:12px 24px; background:#6b7280; color:white; border:none; border-radius:8px; cursor:pointer; font-weight: 500; transition: background 0.2s;" onmouseover="this.style.background='#4b5563'" onmouseout="this.style.background='#6b7280'">
                    <i class="fa-solid fa-times"></i> Cancelar
                </button>
                <button onclick="showSigModal()" style="padding:12px 24px; background:#3b82f6; color:white; border:none; border-radius:8px; cursor:pointer; font-weight: 500; transition: background 0.2s;" onmouseover="this.style.background='#2563eb'" onmouseout="this.style.background='#3b82f6'">
                    <i class="fa-solid fa-pen-fancy"></i> Proceder a Firmar
                </button>
            </div>
            
        </div>
    </div>
    
    <!-- Modal Firma Digital -->
    <div id="sig-modal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.85); z-index:999999;">
        <div style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); background:white; padding:30px; border-radius:12px; max-width:500px; width:90%; text-align:center; box-shadow: 0 20px 40px rgba(0,0,0,0.3);">
            
            <div style="margin-bottom: 25px;">
                <h3 style="margin:0 0 10px 0; color:#1f2937; font-size: 20px;">✍️ Firma Digital</h3>
                <p style="color: #6b7280; font-size: 14px; margin: 0;">Dibuje su firma en el área inferior</p>
            </div>
            
            <div style="margin: 20px auto; display: inline-block; position: relative;">
                <canvas id="sig-canvas" width="400" height="200" style="border:2px solid #d1d5db; border-radius:8px; background:white; cursor:crosshair; display: block;"></canvas>
                <div id="canvas-placeholder" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: #9ca3af; font-size: 14px; pointer-events: none; font-weight: 500;">
                    Firme aquí con el ratón o dedo
                </div>
            </div>
            
            <p style="color:#6b7280; font-size:14px; margin:15px 0;">
                <span style="display: none;" class="desktop-instruction">Use el ratón para dibujar su firma</span>
                <span style="display: none;" class="mobile-instruction">Use el dedo para dibujar su firma</span>
            </p>
            
            <div style="margin-top:25px; display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;">
                <button onclick="clearSig()" style="padding:10px 18px; background:#f3f4f6; color:#374151; border:1px solid #d1d5db; border-radius:8px; cursor:pointer; font-weight: 500; transition: background 0.2s;" onmouseover="this.style.background='#e5e7eb'" onmouseout="this.style.background='#f3f4f6'">
                    <i class="fa-solid fa-eraser"></i> Limpiar
                </button>
                <button onclick="closeSigModal()" style="padding:10px 18px; background:#6b7280; color:white; border:none; border-radius:8px; cursor:pointer; font-weight: 500; transition: background 0.2s;" onmouseover="this.style.background='#4b5563'" onmouseout="this.style.background='#6b7280'">
                    <i class="fa-solid fa-times"></i> Cancelar
                </button>
                <button onclick="acceptSig()" style="padding:10px 18px; background:#10b981; color:white; border:none; border-radius:8px; cursor:pointer; font-weight: 500; transition: background 0.2s;" onmouseover="this.style.background='#059669'" onmouseout="this.style.background='#10b981'">
                    <i class="fa-solid fa-check"></i> Completar Firma
                </button>
            </div>
            
        </div>
    </div>
    
    <!-- Sistema JavaScript Encapsulado -->
    <script>
    (function() {
        'use strict';
        
        var canvas, ctx, isDrawing = false, hasDrawn = false;
        
        // Función principal de inicialización
        function initSystem() {
            setupSignatureField();
            setupResponsiveText();
        }
        
        // Configurar botón principal
        function setupSignatureField() {
            var btn = document.getElementById('signature-field');
            if (btn) {
                btn.addEventListener('click', showDocModal);
            }
        }
        
        // Configurar textos responsivos
        function setupResponsiveText() {
            var desktop = document.querySelectorAll('.desktop-instruction');
            var mobile = document.querySelectorAll('.mobile-instruction');
            
            if (window.innerWidth >= 768) {
                desktop.forEach(function(el) { el.style.display = 'inline'; });
                mobile.forEach(function(el) { el.style.display = 'none'; });
            } else {
                desktop.forEach(function(el) { el.style.display = 'none'; });
                mobile.forEach(function(el) { el.style.display = 'inline'; });
            }
        }
        
        // Funciones de modal
        window.showDocModal = function() {
            document.getElementById('doc-modal').style.display = 'block';
        };
        
        window.closeDocModal = function() {
            document.getElementById('doc-modal').style.display = 'none';
        };
        
        window.showSigModal = function() {
            closeDocModal();
            setTimeout(function() {
                document.getElementById('sig-modal').style.display = 'block';
                initCanvas();
            }, 200);
        };
        
        window.closeSigModal = function() {
            document.getElementById('sig-modal').style.display = 'none';
        };
        
        // Inicialización del canvas
        function initCanvas() {
            canvas = document.getElementById('sig-canvas');
            if (!canvas) return;
            
            ctx = canvas.getContext('2d');
            ctx.strokeStyle = '#000000';
            ctx.lineWidth = 2;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
            
            setupCanvasEvents();
        }
        
        // Eventos del canvas
        function setupCanvasEvents() {
            // Eventos de ratón
            canvas.addEventListener('mousedown', function(e) {
                isDrawing = true;
                hasDrawn = true;
                hidePlaceholder();
                
                var rect = canvas.getBoundingClientRect();
                ctx.beginPath();
                ctx.moveTo(e.clientX - rect.left, e.clientY - rect.top);
            });
            
            canvas.addEventListener('mousemove', function(e) {
                if (!isDrawing) return;
                
                var rect = canvas.getBoundingClientRect();
                ctx.lineTo(e.clientX - rect.left, e.clientY - rect.top);
                ctx.stroke();
            });
            
            canvas.addEventListener('mouseup', function() {
                isDrawing = false;
            });
            
            // Eventos táctiles
            canvas.addEventListener('touchstart', function(e) {
                e.preventDefault();
                isDrawing = true;
                hasDrawn = true;
                hidePlaceholder();
                
                var rect = canvas.getBoundingClientRect();
                var touch = e.touches[0];
                ctx.beginPath();
                ctx.moveTo(touch.clientX - rect.left, touch.clientY - rect.top);
            });
            
            canvas.addEventListener('touchmove', function(e) {
                e.preventDefault();
                if (!isDrawing) return;
                
                var rect = canvas.getBoundingClientRect();
                var touch = e.touches[0];
                ctx.lineTo(touch.clientX - rect.left, touch.clientY - rect.top);
                ctx.stroke();
            });
            
            canvas.addEventListener('touchend', function(e) {
                e.preventDefault();
                isDrawing = false;
            });
        }
        
        // Utilidades del canvas
        function hidePlaceholder() {
            var placeholder = document.getElementById('canvas-placeholder');
            if (placeholder) {
                placeholder.style.opacity = '0';
            }
        }
        
        function showPlaceholder() {
            var placeholder = document.getElementById('canvas-placeholder');
            if (placeholder) {
                placeholder.style.opacity = '1';
            }
        }
        
        window.clearSig = function() {
            if (ctx && canvas) {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                hasDrawn = false;
                showPlaceholder();
            }
        };
        
        window.acceptSig = function() {
            if (!hasDrawn) {
                alert('Por favor, dibuje su firma antes de continuar.');
                return;
            }
            
            var field = document.getElementById('signature-field');
            var status = document.getElementById('signature-status');
            
            if (field) {
                field.innerHTML = '<i class="fa-solid fa-check-circle"></i> Documentos Firmados';
                field.style.background = '#10b981';
                field.style.cursor = 'default';
                field.onclick = null;
            }
            
            if (status) {
                status.innerHTML = '<i class="fa-solid fa-shield-check"></i> Firma digital completada correctamente';
                status.style.color = '#10b981';
                status.style.fontWeight = '500';
            }
            
            closeSigModal();
        };
        
        // Inicialización automática
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initSystem);
        } else {
            initSystem();
        }
        
        // Responsive on resize
        window.addEventListener('resize', setupResponsiveText);
        
    })();
    </script>
    
    <!-- CSS Responsivo adicional -->
    <style>
    @media (max-width: 768px) {
        #tbv2-main-form div[style*="grid-template-columns"] {
            grid-template-columns: 1fr !important;
        }
        
        #sig-modal div[style*="flex"] {
            flex-direction: column !important;
        }
        
        #sig-canvas {
            width: 100% !important;
            max-width: 320px !important;
            height: 160px !important;
        }
        
        .desktop-instruction {
            display: none !important;
        }
        
        .mobile-instruction {
            display: inline !important;
        }
    }
    
    @media (min-width: 769px) {
        .desktop-instruction {
            display: inline !important;
        }
        
        .mobile-instruction {
            display: none !important;
        }
    }
    
    /* Mejoras de accesibilidad */
    button:focus {
        outline: 2px solid #3b82f6 !important;
        outline-offset: 2px !important;
    }
    
    input:focus {
        outline: 2px solid #3b82f6 !important;
        outline-offset: 2px !important;
        border-color: #3b82f6 !important;
    }
    </style>
    
    <?php
    return ob_get_clean();
}

// Registrar shortcode
add_shortcode('transferencia_barco_v2_form', 'tbv2_render_main_content');

// Función de renderizado alternativa para compatibilidad
function tbv2_render_form() {
    return tbv2_render_main_content();
}

?>
