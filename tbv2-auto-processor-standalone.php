<?php
/**
 * TBV2 AUTO PROCESSOR - STANDALONE VERSION
 * 
 * Completely independent from WordPress to avoid plugin conflicts
 * No WordPress scripts, no plugin interference
 * 
 * URL: https://tramitfy.es/wp-content/themes/xtra/tbv2-auto-processor.php
 */

// Prevent WordPress loading
define('SHORTINIT', true);

header('Content-Type: text/html; charset=UTF-8');

// Función de logging
function log_auto_processor($message) {
    $timestamp = date('Y-m-d H:i:s');
    $log = "[$timestamp] TBV2_AUTO_PROCESSOR_STANDALONE: $message\n";
    file_put_contents('/tmp/tbv2_auto_processor_standalone.log', $log, FILE_APPEND | LOCK_EX);
    error_log("TBV2_AUTO_PROCESSOR_STANDALONE: $message");
}

$orderId = $_GET['order_id'] ?? '';
$success = $_GET['success'] ?? '';

log_auto_processor("=== AUTO PROCESSOR STANDALONE INICIADO ===");
log_auto_processor("Order ID: $orderId");
log_auto_processor("Success: $success");

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Procesando Archivos - TBV2 Standalone</title>
    <!-- NO WordPress scripts - completamente independiente -->
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            line-height: 1.6;
        }
        .container {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 30px;
            backdrop-filter: blur(10px);
            box-shadow: 0 8px 32px rgba(31, 38, 135, 0.37);
        }
        .success-icon {
            text-align: center;
            font-size: 4em;
            margin-bottom: 20px;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        .step {
            margin: 15px 0;
            padding: 15px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            border-left: 4px solid #4CAF50;
            transition: all 0.3s ease;
        }
        .step.processing {
            border-left-color: #FF9800;
            background: rgba(255, 152, 0, 0.1);
        }
        .step.completed {
            border-left-color: #4CAF50;
            background: rgba(76, 175, 80, 0.1);
        }
        .step.failed {
            border-left-color: #F44336;
            background: rgba(244, 67, 54, 0.1);
        }
        .status-icon {
            display: inline-block;
            width: 25px;
            margin-right: 10px;
            font-size: 1.2em;
        }
        .log-output {
            background: rgba(0, 0, 0, 0.4);
            padding: 20px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
            margin: 20px 0;
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        .log-output div {
            margin: 5px 0;
        }
        .redirect-info {
            background: rgba(76, 175, 80, 0.2);
            padding: 20px;
            border-radius: 8px;
            border: 2px solid rgba(76, 175, 80, 0.5);
            margin-top: 20px;
        }
        .btn {
            background: linear-gradient(45deg, #4CAF50, #45a049);
            color: white;
            padding: 15px 30px;
            text-decoration: none;
            border-radius: 25px;
            display: inline-block;
            margin: 10px 5px;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-weight: bold;
        }
        .btn:hover {
            background: linear-gradient(45deg, #45a049, #4CAF50);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        .btn.secondary {
            background: linear-gradient(45deg, #2196F3, #1976D2);
        }
        .btn.secondary:hover {
            background: linear-gradient(45deg, #1976D2, #2196F3);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="success-icon">⚡</div>
        <h1 style="text-align: center; margin-bottom: 30px;">Procesando Archivos TBV2 - Standalone</h1>
        
        <div id="processing-steps">
            <div class="step processing" id="step-init">
                <span class="status-icon">⏳</span>
                <strong>Inicializando processor standalone (sin WordPress)...</strong>
            </div>
            <div class="step" id="step-files">
                <span class="status-icon">⏳</span>
                <strong>Recuperando archivos via bridge API...</strong>
            </div>
            <div class="step" id="step-upload">
                <span class="status-icon">⏳</span>
                <strong>Enviando archivos al webhook...</strong>
            </div>
            <div class="step" id="step-complete">
                <span class="status-icon">⏳</span>
                <strong>Finalizando proceso...</strong>
            </div>
        </div>

        <div class="log-output" id="log-output">
            <div style="color: #81C784;">⚡ TBV2 Auto Processor Standalone iniciado - Order: <?php echo htmlspecialchars($orderId); ?></div>
            <div style="color: #64B5F6;">📋 Método: Standalone (sin WordPress plugins, sin conflictos)</div>
            <div style="color: #FFB74D;">⏳ Iniciando proceso ultra-simple...</div>
        </div>

        <div id="final-result" style="display: none;">
            <div class="redirect-info">
                <h3>✅ ¡Proceso Completado!</h3>
                <p id="result-message">Su trámite ha sido procesado correctamente.</p>
                <div id="file-stats"></div>
                <p><strong>Trámite ID:</strong> TBV2-<?php echo htmlspecialchars($orderId); ?></p>
            </div>
            
            <div style="text-align: center; margin-top: 30px;">
                <a href="https://tramitfy.org/tramites" class="btn">Ver Estado del Trámite</a>
                <a href="https://tramitfy.es" class="btn secondary">Nuevo Trámite</a>
            </div>
        </div>
    </div>

    <!-- VANILLA JAVASCRIPT - No jQuery, no WordPress dependencies -->
    <script>
        // Completamente standalone - sin dependencias
        console.log('⚡ TBV2 Auto Processor Standalone iniciado - Order: <?php echo $orderId; ?>');
        
        const orderId = '<?php echo $orderId; ?>';
        const tramiteId = 'TBV2-' + orderId;
        
        function updateStep(stepId, status, icon, message) {
            const step = document.getElementById(stepId);
            if (step) {
                step.className = 'step ' + status;
                const iconElement = step.querySelector('.status-icon');
                if (iconElement) iconElement.textContent = icon;
                if (message) {
                    const strongElement = step.querySelector('strong');
                    if (strongElement) strongElement.textContent = message;
                }
            }
        }
        
        function addLog(message, color = '#FFFFFF') {
            const logOutput = document.getElementById('log-output');
            if (logOutput) {
                const div = document.createElement('div');
                div.textContent = message;
                div.style.color = color;
                logOutput.appendChild(div);
                logOutput.scrollTop = logOutput.scrollHeight;
            }
            console.log(message);
        }
        
        // ✅ ULTRA-SIMPLE: Direct fetch sin WordPress complications
        async function getFilesFromBridge() {
            try {
                addLog('📂 Conectando con bridge API...', '#81C784');
                updateStep('step-files', 'processing', '⏳', 'Conectando con bridge API...');
                
                const bridgeUrl = 'https://tramitfy.es/wp-content/themes/xtra/tbv2-file-bridge.php?order_id=' + orderId;
                
                const response = await fetch(bridgeUrl, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'User-Agent': 'TBV2-Standalone/1.0'
                    }
                });
                
                if (!response.ok) {
                    addLog('❌ Error en bridge API: HTTP ' + response.status, '#F44336');
                    updateStep('step-files', 'failed', '❌', 'Error en bridge API');
                    return [];
                }
                
                const data = await response.json();
                
                if (data.error) {
                    addLog('❌ Error en bridge: ' + data.error, '#F44336');
                    updateStep('step-files', 'failed', '❌', 'Error en bridge API');
                    return [];
                }
                
                const files = data.files || [];
                
                if (files.length === 0) {
                    addLog('⚠️ No se encontraron archivos', '#FFB74D');
                    updateStep('step-files', 'completed', '⚠️', 'Sin archivos para procesar');
                } else {
                    addLog('✅ Archivos recuperados: ' + files.length, '#81C784');
                    updateStep('step-files', 'completed', '✅', files.length + ' archivos recuperados');
                    
                    files.forEach(function(file, index) {
                        addLog('   📎 ' + (index + 1) + '. ' + file.name + ' (' + file.size + ' bytes)', '#64B5F6');
                    });
                }
                
                return files;
                
            } catch (error) {
                addLog('❌ Error crítico: ' + error.message, '#F44336');
                updateStep('step-files', 'failed', '❌', 'Error crítico en bridge API');
                return [];
            }
        }
        
        async function uploadToWebhook(tramiteId, files) {
            try {
                addLog('🚀 Iniciando upload al webhook...', '#64B5F6');
                updateStep('step-upload', 'processing', '⏳', 'Enviando al webhook...');
                
                const webhookUrl = 'https://tramitfy.org/api/herramientas/barcos/webhook';
                
                const payload = {
                    files_update: 'true',
                    tramiteId: tramiteId,
                    attachments: files
                };
                
                addLog('📋 tramiteId: ' + tramiteId, '#64B5F6');
                addLog('📎 Archivos: ' + files.length, '#64B5F6');
                
                const response = await fetch(webhookUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'User-Agent': 'TBV2-Standalone/1.0'
                    },
                    body: JSON.stringify(payload)
                });
                
                if (response.ok) {
                    addLog('✅ Upload exitoso', '#81C784');
                    updateStep('step-upload', 'completed', '✅', files.length + ' archivos enviados');
                    return true;
                } else {
                    addLog('❌ Error HTTP: ' + response.status, '#F44336');
                    updateStep('step-upload', 'failed', '❌', 'Error en upload');
                    return false;
                }
                
            } catch (error) {
                addLog('❌ Error upload: ' + error.message, '#F44336');
                updateStep('step-upload', 'failed', '❌', 'Error crítico en upload');
                return false;
            }
        }
        
        async function processFiles() {
            try {
                updateStep('step-init', 'completed', '✅', 'Processor standalone inicializado');
                
                const files = await getFilesFromBridge();
                
                if (files.length === 0) {
                    addLog('📋 Proceso sin archivos completado', '#FFB74D');
                    updateStep('step-upload', 'completed', 'ℹ️', 'Sin archivos para enviar');
                    updateStep('step-complete', 'completed', '✅', 'Completado sin archivos');
                    
                    document.getElementById('result-message').textContent = 'Su trámite ha sido procesado correctamente. No se encontraron archivos adjuntos.';
                    document.getElementById('file-stats').innerHTML = '<strong>Archivos:</strong> 0<br><strong>Estado:</strong> ✅ Completado sin archivos';
                } else {
                    const success = await uploadToWebhook(tramiteId, files);
                    
                    if (success) {
                        updateStep('step-complete', 'completed', '✅', 'Todos los archivos procesados');
                        document.getElementById('result-message').textContent = 'Su trámite ha sido procesado correctamente con todos los archivos adjuntos.';
                        document.getElementById('file-stats').innerHTML = '<strong>Archivos:</strong> ' + files.length + '<br><strong>Estado:</strong> ✅ Todos subidos correctamente';
                    } else {
                        updateStep('step-complete', 'failed', '❌', 'Error procesando archivos');
                        document.getElementById('result-message').textContent = 'El trámite fue creado pero hubo problemas con los archivos.';
                        document.getElementById('file-stats').innerHTML = '<strong>Archivos:</strong> ' + files.length + '<br><strong>Estado:</strong> ❌ Error en upload';
                    }
                }
                
                // Mostrar resultado final con delay
                setTimeout(function() {
                    document.getElementById('final-result').style.display = 'block';
                }, 1500);
                
            } catch (error) {
                addLog('❌ Error crítico: ' + error.message, '#F44336');
                updateStep('step-complete', 'failed', '❌', 'Error crítico');
                
                document.getElementById('result-message').textContent = 'Hubo un error técnico procesando el trámite.';
                document.getElementById('file-stats').innerHTML = '<strong>Estado:</strong> ❌ Error técnico';
                document.getElementById('final-result').style.display = 'block';
            }
        }
        
        // Iniciar automáticamente después de carga completa
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                setTimeout(processFiles, 1000);
            });
        } else {
            setTimeout(processFiles, 1000);
        }
        
    </script>
</body>
</html>