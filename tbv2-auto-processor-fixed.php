<?php
/**
 * TBV2 AUTO PROCESSOR - CONFLICT-FREE VERSION
 * 
 * SOLUTION: Uses PHP session-based file bridge instead of JavaScript DOM access
 * - No localStorage (eliminates QuotaExceededError)
 * - No window.opener (eliminates JavaScript freezing)
 * - Server-side file temporary storage
 * 
 * URL: https://tramitfy.es/wp-content/themes/xtra/tbv2-auto-processor.php
 */

header('Content-Type: text/html; charset=UTF-8');

// Función de logging
function log_auto_processor($message) {
    $timestamp = date('Y-m-d H:i:s');
    $log = "[$timestamp] TBV2_AUTO_PROCESSOR_FIXED: $message\n";
    file_put_contents('/tmp/tbv2_auto_processor_fixed.log', $log, FILE_APPEND | LOCK_EX);
    error_log("TBV2_AUTO_PROCESSOR_FIXED: $message");
}

$orderId = $_GET['order_id'] ?? '';
$success = $_GET['success'] ?? '';

log_auto_processor("=== AUTO PROCESSOR CONFLICT-FREE INICIADO ===");
log_auto_processor("Order ID: $orderId");
log_auto_processor("Success: $success");

// ✅ NUEVA ESTRATEGIA: Leer archivos desde directorio temporal PHP
$files_directory = "/tmp/tbv2_files_$orderId";
$files_list = [];

if (is_dir($files_directory)) {
    $files_list = scandir($files_directory);
    $files_list = array_filter($files_list, function($file) {
        return !in_array($file, ['.', '..']);
    });
    log_auto_processor("Archivos encontrados en $files_directory: " . count($files_list));
} else {
    log_auto_processor("Directorio de archivos no encontrado: $files_directory");
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Procesando Archivos - TBV2 Fixed</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
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
        }
        .step {
            margin: 15px 0;
            padding: 10px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            border-left: 4px solid #4CAF50;
        }
        .step.processing {
            border-left-color: #FF9800;
        }
        .step.completed {
            border-left-color: #4CAF50;
        }
        .step.failed {
            border-left-color: #F44336;
        }
        .status-icon {
            display: inline-block;
            width: 20px;
            margin-right: 10px;
        }
        .log-output {
            background: rgba(0, 0, 0, 0.3);
            padding: 15px;
            border-radius: 8px;
            font-family: monospace;
            font-size: 0.9em;
            margin: 20px 0;
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .redirect-info {
            background: rgba(76, 175, 80, 0.2);
            padding: 15px;
            border-radius: 8px;
            border: 1px solid rgba(76, 175, 80, 0.5);
            margin-top: 20px;
        }
        .btn {
            background: #4CAF50;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 25px;
            display: inline-block;
            margin: 10px 5px;
            transition: background 0.3s;
        }
        .btn:hover {
            background: #45a049;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="success-icon">🔧</div>
        <h1 style="text-align: center; margin-bottom: 30px;">Procesando Archivos TBV2 - Conflict-Free</h1>
        
        <div id="processing-steps">
            <div class="step processing" id="step-init">
                <span class="status-icon">⏳</span>
                <strong>Inicializando auto processor sin conflictos...</strong>
            </div>
            <div class="step" id="step-files">
                <span class="status-icon">⏳</span>
                <strong>Recuperando archivos desde servidor...</strong>
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
            <div>🔧 TBV2 Auto Processor Conflict-Free iniciado - Order: <?php echo htmlspecialchars($orderId); ?></div>
            <div>📋 Método: Server-side file bridge (sin DOM access, sin localStorage)</div>
            <div>⏳ Iniciando proceso sin conflictos...</div>
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
                <a href="https://tramitfy.es" class="btn" style="background: #2196F3;">Nuevo Trámite</a>
            </div>
        </div>
    </div>

    <script>
        console.log('🔧 TBV2 Auto Processor Conflict-Free iniciado - Order: <?php echo $orderId; ?>');
        
        const orderId = '<?php echo $orderId; ?>';
        const tramiteId = 'TBV2-' + orderId;
        
        function updateStep(stepId, status, icon, message) {
            const step = document.getElementById(stepId);
            step.className = 'step ' + status;
            step.querySelector('.status-icon').textContent = icon;
            if (message) {
                step.querySelector('strong').textContent = message;
            }
        }
        
        function addLog(message, type = 'info') {
            const logOutput = document.getElementById('log-output');
            const div = document.createElement('div');
            div.textContent = message;
            if (type === 'error') {
                div.style.color = '#ffcdd2';
            } else if (type === 'success') {
                div.style.color = '#c8e6c9';
            }
            logOutput.appendChild(div);
            logOutput.scrollTop = logOutput.scrollHeight;
            
            console.log(message);
        }
        
        // ✅ NUEVA IMPLEMENTACIÓN: Bridge API para recuperar archivos del servidor
        async function getFilesFromServer() {
            try {
                addLog('📂 Recuperando archivos desde bridge API del servidor...');
                updateStep('step-files', 'processing', '⏳', 'Conectando con bridge API...');
                
                // Llamar al bridge API para obtener archivos
                const bridgeUrl = `https://tramitfy.es/wp-content/themes/xtra/tbv2-file-bridge.php?order_id=${orderId}`;
                
                const response = await fetch(bridgeUrl, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'User-Agent': 'TBV2-Auto-Processor-Fixed/1.0'
                    }
                });
                
                if (!response.ok) {
                    addLog(`❌ Error en bridge API: HTTP ${response.status}`, 'error');
                    updateStep('step-files', 'failed', '❌', 'Error conectando con bridge API');
                    return [];
                }
                
                const bridgeData = await response.json();
                
                if (bridgeData.error) {
                    addLog(`❌ Error en bridge: ${bridgeData.error}`, 'error');
                    updateStep('step-files', 'failed', '❌', 'Error en bridge API');
                    return [];
                }
                
                const files = bridgeData.files || [];
                
                if (files.length === 0) {
                    addLog('⚠️ No se encontraron archivos en el servidor', 'error');
                    updateStep('step-files', 'completed', '⚠️', 'Sin archivos para procesar');
                } else {
                    addLog(`✅ Total archivos recuperados desde servidor: ${files.length}`, 'success');
                    updateStep('step-files', 'completed', '✅', `${files.length} archivos recuperados`);
                    
                    // Log details
                    files.forEach((file, index) => {
                        addLog(`   📎 ${index + 1}. ${file.name} (${file.size} bytes)`);
                    });
                }
                
                return files;
                
            } catch (error) {
                addLog(`❌ Error crítico en bridge API: ${error.message}`, 'error');
                updateStep('step-files', 'failed', '❌', 'Error crítico en bridge API');
                return [];
            }
        }
        
        // Función para enviar archivos al webhook (sin cambios)
        async function uploadFilesToWebhook(tramiteId, files) {
            try {
                addLog(`🚀 Iniciando upload de ${files.length} archivos al webhook para trámite: ${tramiteId}`);
                updateStep('step-upload', 'processing', '⏳', 'Enviando archivos al webhook...');
                
                const webhookUrl = 'https://tramitfy.org/api/herramientas/barcos/webhook';
                
                const payload = {
                    files_update: 'true',
                    tramiteId: tramiteId,
                    attachments: files
                };
                
                addLog(`📋 tramiteId enviado: ${tramiteId}`);
                addLog(`📎 Archivos a enviar: ${files.length}`);
                
                const response = await fetch(webhookUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'User-Agent': 'TBV2-Auto-Processor-Fixed/1.0'
                    },
                    body: JSON.stringify(payload)
                });
                
                const responseText = await response.text();
                
                if (response.ok) {
                    addLog('✅ Upload de archivos exitoso', 'success');
                    updateStep('step-upload', 'completed', '✅', `${files.length} archivos enviados correctamente`);
                    return true;
                } else {
                    addLog(`❌ Error en upload: HTTP ${response.status}`, 'error');
                    addLog(`❌ Response: ${responseText}`, 'error');
                    updateStep('step-upload', 'failed', '❌', 'Error enviando archivos al webhook');
                    return false;
                }
                
            } catch (error) {
                addLog(`❌ Exception en upload: ${error.message}`, 'error');
                updateStep('step-upload', 'failed', '❌', 'Error crítico en upload de archivos');
                return false;
            }
        }
        
        // Función principal sin conflictos
        async function processFiles() {
            try {
                updateStep('step-init', 'completed', '✅', 'Auto processor sin conflictos inicializado');
                
                // ✅ NUEVA IMPLEMENTACIÓN: Bridge API del servidor (sin DOM access)
                const files = await getFilesFromServer();
                
                if (files.length === 0) {
                    addLog('📋 No hay archivos para procesar');
                    updateStep('step-upload', 'completed', 'ℹ️', 'Sin archivos para enviar');
                    updateStep('step-complete', 'completed', '✅', 'Proceso completado (sin archivos)');
                    
                    document.getElementById('result-message').textContent = 'Su trámite ha sido procesado correctamente. No se encontraron archivos adjuntos.';
                    document.getElementById('file-stats').innerHTML = '<strong>Archivos procesados:</strong> 0<br><strong>Estado:</strong> ✅ Proceso completado sin archivos';
                } else {
                    const uploadSuccess = await uploadFilesToWebhook(tramiteId, files);
                    
                    if (uploadSuccess) {
                        updateStep('step-complete', 'completed', '✅', 'Todos los archivos procesados correctamente');
                        document.getElementById('result-message').textContent = 'Su trámite ha sido procesado correctamente con todos los archivos adjuntos.';
                        document.getElementById('file-stats').innerHTML = `<strong>Archivos procesados:</strong> ${files.length}<br><strong>Estado:</strong> ✅ Todos los archivos subidos correctamente`;
                    } else {
                        updateStep('step-complete', 'failed', '❌', 'Error procesando algunos archivos');
                        document.getElementById('result-message').textContent = 'El trámite fue creado pero hubo un error procesando los archivos adjuntos.';
                        document.getElementById('file-stats').innerHTML = `<strong>Archivos encontrados:</strong> ${files.length}<br><strong>Estado:</strong> ❌ Error en upload de archivos`;
                    }
                }
                
                // Mostrar resultado final
                setTimeout(() => {
                    document.getElementById('final-result').style.display = 'block';
                }, 1000);
                
            } catch (error) {
                addLog(`❌ Error crítico en processFiles: ${error.message}`, 'error');
                updateStep('step-complete', 'failed', '❌', 'Error crítico en procesamiento');
                
                document.getElementById('result-message').textContent = 'Hubo un error técnico procesando el trámite.';
                document.getElementById('file-stats').innerHTML = '<strong>Estado:</strong> ❌ Error técnico';
                document.getElementById('final-result').style.display = 'block';
            }
        }
        
        // Iniciar procesamiento automáticamente
        setTimeout(processFiles, 1000);
        
    </script>
</body>
</html>