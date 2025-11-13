<?php
/**
 * TRAMITFY - SISTEMA DE PRUEBA SEGURO
 * 
 * ✅ METODOLOGÍA: "Retención hasta Confirmación TPV"
 * ✅ Captura temporal → Pago TPV → Guardado definitivo solo en SUCCESS
 * ✅ Sin pérdida de datos, sin datos huérfanos
 * 
 * @version 1.0.0 - SISTEMA SEGURO DE PRUEBA
 * @author Claude Code  
 * @created 2025-11-13
 */

// Acceso solo via WordPress
if (!defined('ABSPATH')) {
    exit('Acceso directo no permitido.');
}

// =====================================================
// CONFIGURACIÓN DEL SISTEMA SEGURO
// =====================================================

define('PRUEBA_SEGURA_VPS_URL', 'https://46-202-128-35.sslip.io/api/herramientas/barcos/webhook');
define('PRUEBA_SEGURA_TTL', 7200); // 2 horas para expiración temporal

// Configuración Redsys (misma que V2/V3)
define('PRUEBA_REDSYS_MERCHANT', '363391103');
define('PRUEBA_REDSYS_TERMINAL', '1');
define('PRUEBA_REDSYS_SECRET', 'sq7HjrUOBfKmC576ILgskD5srU870gJ7');
define('PRUEBA_REDSYS_URL', 'https://sis-t.redsys.es:25443/sis/realizarPago');

/**
 * ✅ LOGGING SEGURO
 */
function prueba_log($message, $context = 'PRUEBA-SEGURA', $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] [$level] [$context] $message\n";
    file_put_contents('/tmp/tramitfy-prueba-segura.log', $log_entry, FILE_APPEND);
}

/**
 * ✅ GENERACIÓN DE ID ÚNICO SEGURO
 */
function generar_id_seguro() {
    $microtime = microtime(true);
    return 'PS_' . substr(str_replace('.', '', $microtime), -8);
}

/**
 * ✅ GUARDAR DATOS EN ALMACÉN TEMPORAL
 */
function guardar_temporal($id_unico, $datos_completos) {
    prueba_log("Guardando datos temporales - ID: $id_unico", 'TEMPORAL-SAVE', 'INFO');
    
    // Guardar en WordPress transients con TTL
    $guardado = set_transient('prueba_temp_' . $id_unico, $datos_completos, PRUEBA_SEGURA_TTL);
    
    if ($guardado) {
        prueba_log("Datos temporales guardados exitosamente - ID: $id_unico", 'TEMPORAL-OK', 'SUCCESS');
        return true;
    } else {
        prueba_log("Error guardando datos temporales - ID: $id_unico", 'TEMPORAL-ERROR', 'ERROR');
        return false;
    }
}

/**
 * ✅ RECUPERAR DATOS DEL ALMACÉN TEMPORAL
 */
function recuperar_temporal($id_unico) {
    prueba_log("Recuperando datos temporales - ID: $id_unico", 'TEMPORAL-GET', 'INFO');
    
    $datos = get_transient('prueba_temp_' . $id_unico);
    
    if ($datos) {
        prueba_log("Datos temporales recuperados exitosamente - ID: $id_unico", 'TEMPORAL-FOUND', 'SUCCESS');
        return $datos;
    } else {
        prueba_log("No se encontraron datos temporales - ID: $id_unico", 'TEMPORAL-NOT-FOUND', 'WARNING');
        return false;
    }
}

/**
 * ✅ LIMPIAR DATOS TEMPORALES
 */
function limpiar_temporal($id_unico) {
    prueba_log("Limpiando datos temporales - ID: $id_unico", 'TEMPORAL-CLEAN', 'INFO');
    
    $limpiado = delete_transient('prueba_temp_' . $id_unico);
    
    if ($limpiado) {
        prueba_log("Datos temporales limpiados - ID: $id_unico", 'TEMPORAL-CLEANED', 'SUCCESS');
    }
    
    return $limpiado;
}

/**
 * ✅ GUARDAR DEFINITIVO EN VPS (Solo después de pago confirmado)
 */
function guardar_definitivo($datos_completos) {
    prueba_log("Iniciando guardado definitivo en VPS", 'DEFINITIVO-START', 'INFO');
    
    // Preparar datos para VPS
    $payload = [
        'tramite_tipo' => 'transferencia_barco_prueba',
        'timestamp_definitivo' => time(),
        'datos_formulario' => $datos_completos['form_data'],
        'archivos' => $datos_completos['files_data'],
        'firma_digital' => $datos_completos['signature_data'],
        'pago_confirmado' => $datos_completos['pago_data'],
        'estado' => 'confirmed_and_paid'
    ];
    
    // Enviar a VPS
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => PRUEBA_SEGURA_VPS_URL,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-Source: Prueba-Segura-TBV3'
        ]
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($curl_error) {
        prueba_log("Error cURL enviando a VPS: $curl_error", 'DEFINITIVO-ERROR', 'ERROR');
        return false;
    }
    
    if ($http_code >= 200 && $http_code < 300) {
        prueba_log("Datos guardados definitivamente en VPS - HTTP $http_code", 'DEFINITIVO-OK', 'SUCCESS');
        return true;
    } else {
        prueba_log("Error HTTP enviando a VPS: $http_code - $response", 'DEFINITIVO-FAIL', 'ERROR');
        return false;
    }
}

/**
 * ✅ PROCESAMIENTO DEL FORMULARIO DE PRUEBA
 */
function procesar_formulario_prueba() {
    if (!isset($_POST['proceso_prueba_segura'])) {
        return;
    }
    
    prueba_log("=== INICIO PROCESAMIENTO PRUEBA SEGURA ===", 'PROCESO-START', 'INFO');
    
    // 1. Generar ID único
    $id_unico = generar_id_seguro();
    prueba_log("ID único generado: $id_unico", 'ID-GEN', 'SUCCESS');
    
    // 2. Capturar datos del formulario
    $form_data = [
        'nombre' => sanitize_text_field($_POST['nombre'] ?? ''),
        'email' => sanitize_email($_POST['email'] ?? ''),
        'telefono' => sanitize_text_field($_POST['telefono'] ?? ''),
        'mensaje' => sanitize_textarea_field($_POST['mensaje'] ?? '')
    ];
    
    // 3. Procesar archivos (simulado por ahora)
    $files_data = [];
    if (!empty($_FILES['documentos'])) {
        // En implementación real: procesar uploads
        $files_data = ['archivo_simulado.pdf' => 'ruta_temporal'];
    }
    
    // 4. Procesar firma
    $signature_data = null;
    if (!empty($_POST['signature_data'])) {
        $signature_data = $_POST['signature_data']; // base64
    }
    
    // 5. Preparar datos completos
    $datos_completos = [
        'id_unico' => $id_unico,
        'timestamp_captura' => time(),
        'form_data' => $form_data,
        'files_data' => $files_data,
        'signature_data' => $signature_data,
        'estado' => 'captured_temporal'
    ];
    
    // 6. Guardar en almacén temporal
    $guardado_temporal = guardar_temporal($id_unico, $datos_completos);
    
    if (!$guardado_temporal) {
        wp_die('Error guardando datos temporales');
    }
    
    // 7. Simular redirección a TPV
    prueba_log("Redirigiendo a simulación TPV - ID: $id_unico", 'TPV-REDIRECT', 'INFO');
    
    // En lugar de TPV real, simular proceso
    mostrar_simulacion_tpv($id_unico, $form_data);
    exit;
}

/**
 * ✅ SIMULACIÓN TPV PARA PRUEBA
 */
function mostrar_simulacion_tpv($id_unico, $form_data) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Simulación TPV - Prueba Segura</title>
        <style>
            body { font-family: Arial, sans-serif; padding: 40px; background: #f0f0f0; }
            .tpv-container { background: white; padding: 40px; border-radius: 8px; max-width: 500px; margin: 0 auto; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
            .btn { padding: 15px 30px; margin: 10px; border: none; border-radius: 6px; cursor: pointer; font-size: 16px; }
            .btn-success { background: #28a745; color: white; }
            .btn-danger { background: #dc3545; color: white; }
            .info { background: #e3f2fd; padding: 15px; border-radius: 6px; margin: 20px 0; }
        </style>
    </head>
    <body>
        <div class="tpv-container">
            <h2>🏦 Simulación TPV - Sistema Seguro</h2>
            
            <div class="info">
                <strong>ID Único:</strong> <?php echo esc_html($id_unico); ?><br>
                <strong>Cliente:</strong> <?php echo esc_html($form_data['nombre']); ?><br>
                <strong>Email:</strong> <?php echo esc_html($form_data['email']); ?><br>
                <strong>Datos guardados:</strong> ✅ Temporalmente<br>
                <strong>Estado:</strong> Esperando confirmación pago
            </div>
            
            <p><strong>Simular resultado del pago:</strong></p>
            
            <form method="POST" style="text-align: center;">
                <input type="hidden" name="id_unico" value="<?php echo esc_attr($id_unico); ?>">
                
                <button type="submit" name="simular_pago_exitoso" class="btn btn-success">
                    ✅ Pago EXITOSO<br><small>Guardar definitivamente</small>
                </button>
                
                <button type="submit" name="simular_pago_fallido" class="btn btn-danger">
                    ❌ Pago FALLIDO<br><small>Limpiar temporal</small>
                </button>
            </form>
            
            <div style="margin-top: 30px; padding: 15px; background: #fff3cd; border-radius: 6px;">
                <h4>🔒 Sistema Seguro Activo:</h4>
                <ul style="text-align: left; margin: 0;">
                    <li>✅ Datos capturados temporalmente</li>
                    <li>⏳ Guardado definitivo solo si pago exitoso</li>
                    <li>🧹 Auto-limpieza si pago falla</li>
                    <li>📊 Trazabilidad completa</li>
                </ul>
            </div>
        </div>
    </body>
    </html>
    <?php
}

/**
 * ✅ PROCESAMIENTO CALLBACK SIMULADO
 */
function procesar_callback_simulado() {
    if (isset($_POST['simular_pago_exitoso']) || isset($_POST['simular_pago_fallido'])) {
        $id_unico = sanitize_text_field($_POST['id_unico']);
        
        if (isset($_POST['simular_pago_exitoso'])) {
            // PAGO EXITOSO - Guardar definitivamente
            prueba_log("=== SIMULACIÓN PAGO EXITOSO - ID: $id_unico ===", 'CALLBACK-SUCCESS', 'SUCCESS');
            
            // Recuperar datos temporales
            $datos_temporales = recuperar_temporal($id_unico);
            
            if ($datos_temporales) {
                // Añadir datos del pago
                $datos_temporales['pago_data'] = [
                    'estado' => 'exitoso',
                    'simulado' => true,
                    'timestamp_pago' => time(),
                    'id_unico' => $id_unico
                ];
                
                // Guardar definitivamente
                $guardado_definitivo = guardar_definitivo($datos_temporales);
                
                if ($guardado_definitivo) {
                    // Limpiar temporal
                    limpiar_temporal($id_unico);
                    
                    mostrar_resultado_exitoso($id_unico, $datos_temporales);
                } else {
                    mostrar_error("Error enviando a VPS. Datos temporales mantenidos para reintento.");
                }
            } else {
                mostrar_error("No se encontraron datos temporales para ID: $id_unico");
            }
            
        } else {
            // PAGO FALLIDO - Limpiar temporal
            prueba_log("=== SIMULACIÓN PAGO FALLIDO - ID: $id_unico ===", 'CALLBACK-FAIL', 'WARNING');
            
            limpiar_temporal($id_unico);
            mostrar_resultado_fallido($id_unico);
        }
        exit;
    }
}

/**
 * ✅ MOSTRAR RESULTADO EXITOSO
 */
function mostrar_resultado_exitoso($id_unico, $datos) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Éxito - Sistema Seguro</title>
        <style>
            body { font-family: Arial, sans-serif; padding: 40px; background: #d4edda; }
            .success-container { background: white; padding: 40px; border-radius: 8px; max-width: 600px; margin: 0 auto; border: 3px solid #28a745; }
            .success { color: #155724; }
        </style>
    </head>
    <body>
        <div class="success-container">
            <h2 class="success">🎉 ¡ÉXITO! Sistema Seguro Funcionando</h2>
            
            <div style="background: #d1ecf1; padding: 20px; border-radius: 6px; margin: 20px 0;">
                <h3>✅ Proceso Completado:</h3>
                <ol>
                    <li><strong>Captura temporal:</strong> ✅ Completada</li>
                    <li><strong>Pago simulado:</strong> ✅ Exitoso</li>
                    <li><strong>Guardado definitivo:</strong> ✅ En VPS</li>
                    <li><strong>Limpieza temporal:</strong> ✅ Realizada</li>
                </ol>
            </div>
            
            <div style="background: #e2e3e5; padding: 15px; border-radius: 6px;">
                <strong>ID Transacción:</strong> <?php echo esc_html($id_unico); ?><br>
                <strong>Cliente:</strong> <?php echo esc_html($datos['form_data']['nombre']); ?><br>
                <strong>Email:</strong> <?php echo esc_html($datos['form_data']['email']); ?><br>
                <strong>Timestamp:</strong> <?php echo date('Y-m-d H:i:s', $datos['timestamp_captura']); ?>
            </div>
            
            <p style="margin-top: 30px; text-align: center;">
                <a href="<?php echo home_url(); ?>" style="background: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px;">
                    Volver al inicio
                </a>
            </p>
        </div>
    </body>
    </html>
    <?php
}

/**
 * ✅ MOSTRAR RESULTADO FALLIDO
 */
function mostrar_resultado_fallido($id_unico) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Pago Fallido - Sistema Seguro</title>
        <style>
            body { font-family: Arial, sans-serif; padding: 40px; background: #f8d7da; }
            .fail-container { background: white; padding: 40px; border-radius: 8px; max-width: 600px; margin: 0 auto; border: 3px solid #dc3545; }
            .fail { color: #721c24; }
        </style>
    </head>
    <body>
        <div class="fail-container">
            <h2 class="fail">❌ Pago No Completado</h2>
            
            <div style="background: #d1ecf1; padding: 20px; border-radius: 6px; margin: 20px 0;">
                <h3>🔒 Sistema Seguro Activo:</h3>
                <ul>
                    <li><strong>Pago:</strong> ❌ No completado</li>
                    <li><strong>Datos temporales:</strong> 🧹 Limpiados automáticamente</li>
                    <li><strong>VPS:</strong> ✅ Sin datos huérfanos</li>
                    <li><strong>Estado:</strong> ✅ Sistema limpio</li>
                </ul>
            </div>
            
            <div style="background: #e2e3e5; padding: 15px; border-radius: 6px;">
                <strong>ID Transacción:</strong> <?php echo esc_html($id_unico); ?><br>
                <strong>Estado:</strong> Cancelado - Sin guardar<br>
                <strong>Acción:</strong> Datos temporales eliminados
            </div>
            
            <p style="margin-top: 30px; text-align: center;">
                <a href="<?php echo home_url(); ?>" style="background: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px;">
                    Reintentar proceso
                </a>
            </p>
        </div>
    </body>
    </html>
    <?php
}

/**
 * ✅ MOSTRAR ERROR
 */
function mostrar_error($mensaje) {
    wp_die('Error: ' . $mensaje);
}

/**
 * ✅ FUNCIÓN PRINCIPAL DEL SHORTCODE
 */
function sistema_prueba_segura_shortcode() {
    // Procesar callbacks primero
    procesar_callback_simulado();
    
    // Procesar formulario si es POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        procesar_formulario_prueba();
        return;
    }
    
    // Mostrar formulario
    ob_start();
    ?>
    <div id="sistema-prueba-segura">
        <style>
            #sistema-prueba-segura { max-width: 800px; margin: 20px auto; font-family: Arial, sans-serif; }
            .form-container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
            .form-group { margin-bottom: 20px; }
            .form-group label { display: block; font-weight: bold; margin-bottom: 8px; color: #333; }
            .form-group input, .form-group textarea { width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 6px; font-size: 14px; }
            .form-group input:focus, .form-group textarea:focus { border-color: #007bff; outline: none; }
            .signature-container { border: 2px dashed #ddd; border-radius: 6px; padding: 20px; text-align: center; margin: 20px 0; }
            #signature-pad { border: 2px solid #007bff; border-radius: 6px; }
            .btn-primary { background: #007bff; color: white; padding: 15px 30px; border: none; border-radius: 6px; font-size: 16px; cursor: pointer; }
            .btn-secondary { background: #6c757d; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; margin-top: 10px; }
            .info-box { background: #e3f2fd; padding: 20px; border-radius: 6px; margin-bottom: 20px; border-left: 4px solid #007bff; }
        </style>
        
        <div class="form-container">
            <h2>🔐 Sistema de Prueba Seguro</h2>
            
            <div class="info-box">
                <h3>🎯 Objetivo del Test:</h3>
                <ul>
                    <li>✅ <strong>Capturar:</strong> Datos + Archivos + Firma</li>
                    <li>⏳ <strong>Retener:</strong> Temporalmente hasta confirmación TPV</li>
                    <li>💳 <strong>Pagar:</strong> Simulación TPV</li>
                    <li>💾 <strong>Guardar:</strong> Definitivamente solo si pago exitoso</li>
                </ul>
            </div>
            
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="nombre">Nombre Completo *</label>
                    <input type="text" id="nombre" name="nombre" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email *</label>
                    <input type="email" id="email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="telefono">Teléfono *</label>
                    <input type="tel" id="telefono" name="telefono" required>
                </div>
                
                <div class="form-group">
                    <label for="documentos">Documentos de Prueba</label>
                    <input type="file" id="documentos" name="documentos[]" multiple accept=".pdf,.jpg,.png">
                    <small>Archivos de prueba (PDF, JPG, PNG)</small>
                </div>
                
                <div class="form-group">
                    <label for="mensaje">Mensaje</label>
                    <textarea id="mensaje" name="mensaje" rows="4" placeholder="Describe tu solicitud..."></textarea>
                </div>
                
                <div class="signature-container">
                    <h3>✍️ Firma Digital de Autorización</h3>
                    <p>Firma en el recuadro para autorizar el proceso:</p>
                    <canvas id="signature-pad" width="600" height="200"></canvas>
                    <br>
                    <button type="button" id="clear-signature" class="btn-secondary">Limpiar Firma</button>
                    <input type="hidden" id="signature_data" name="signature_data">
                </div>
                
                <div style="text-align: center; margin-top: 30px;">
                    <button type="submit" name="proceso_prueba_segura" class="btn-primary">
                        🔒 Procesar con Sistema Seguro
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>
    <script>
        // Configurar firma digital
        const canvas = document.getElementById('signature-pad');
        const clearBtn = document.getElementById('clear-signature');
        const signatureInput = document.getElementById('signature_data');
        
        const signaturePad = new SignaturePad(canvas, {
            backgroundColor: 'rgb(255, 255, 255)',
            penColor: 'rgb(0, 0, 0)'
        });
        
        signaturePad.addEventListener('endStroke', () => {
            if (!signaturePad.isEmpty()) {
                signatureInput.value = signaturePad.toDataURL();
                console.log('✍️ Firma capturada para prueba');
            }
        });
        
        clearBtn.addEventListener('click', () => {
            signaturePad.clear();
            signatureInput.value = '';
            console.log('🧹 Firma limpiada');
        });
        
        // Validación antes de envío
        document.querySelector('form').addEventListener('submit', function(e) {
            if (signaturePad.isEmpty()) {
                alert('⚠️ Por favor, firma para autorizar el proceso de prueba');
                e.preventDefault();
                return;
            }
            
            console.log('🔒 Enviando al sistema seguro de prueba...');
        });
        
        console.log('🚀 Sistema de prueba seguro inicializado');
    </script>
    <?php
    
    return ob_get_clean();
}

/**
 * ✅ REGISTRAR SHORTCODE
 */
function registrar_sistema_prueba_segura() {
    add_shortcode('sistema_prueba_segura', 'sistema_prueba_segura_shortcode');
}

add_action('init', 'registrar_sistema_prueba_segura');

/**
 * ✅ HOOKS DE WORDPRESS
 */
add_action('init', function() {
    if (isset($_POST['proceso_prueba_segura']) || isset($_POST['simular_pago_exitoso']) || isset($_POST['simular_pago_fallido'])) {
        // Procesar en el hook init para evitar problemas de headers
    }
});

?>