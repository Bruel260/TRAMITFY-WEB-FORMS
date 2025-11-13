<?php
/**
 * TBV2 AUTO PROCESSOR - SIMPLIFIED VERSION
 * 
 * Reads files directly from sessionStorage (no Bridge API needed for files)
 * 
 * URL: https://tramitfy.es/wp-content/themes/xtra/tbv2-auto-processor.php
 */

header('Content-Type: text/html; charset=UTF-8');

// Función de logging
function log_auto_processor($message) {
    $timestamp = date('Y-m-d H:i:s');
    $log = "[$timestamp] TBV2_AUTO_PROCESSOR_SIMPLE: $message\n";
    file_put_contents('/tmp/tbv2_auto_processor_simple.log', $log, FILE_APPEND | LOCK_EX);
    error_log("TBV2_AUTO_PROCESSOR_SIMPLE: $message");
}

$orderId = $_GET['order_id'] ?? '';
$success = $_GET['success'] ?? '';

log_auto_processor("=== AUTO PROCESSOR SIMPLE INICIADO ===");
log_auto_processor("Order ID: $orderId");
log_auto_processor("Success: $success");

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Procesando Archivos - TBV2</title>
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
        <div class="success-icon">🚀</div>
        <h1 style="text-align: center; margin-bottom: 30px;">Procesando Archivos TBV2</h1>
        
        <div id="processing-steps">
            <div class="step processing" id="step-init">
                <span class="status-icon">⏳</span>
                <strong>Inicializando auto processor simple...</strong>
            </div>
            <div class="step" id="step-files">
                <span class="status-icon">⏳</span>
                <strong>Recuperando archivos desde sessionStorage...</strong>
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
            <div>🚀 TBV2 Auto Processor Simple iniciado - Order: <?php echo htmlspecialchars($orderId); ?></div>
            <div>📋 Método: Direct sessionStorage (sin Bridge API para archivos)</div>
            <div>⏳ Iniciando proceso simplificado...</div>
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
        console.log('🚀 TBV2 Auto Processor Simple iniciado - Order: <?php echo $orderId; ?>');
        
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
        
        // ✅ NUEVA IMPLEMENTACIÓN: Leer archivos directamente desde el DOM
        function getFilesFromDOM() {
            return new Promise(async (resolve) => {
                try {
                    addLog('📂 Recuperando archivos directamente desde el DOM...');
                    updateStep('step-files', 'processing', '⏳', 'Accediendo a archivos del formulario...');
                    
                    const files = [];
                    
                    // Obtener la ventana del formulario padre (opener)
                    const parentWindow = window.opener;
                    if (!parentWindow) {
                        addLog('❌ No se puede acceder a la ventana del formulario', 'error');
                        updateStep('step-files', 'failed', '❌', 'Error accediendo al formulario');
                        resolve([]);
                        return;
                    }
                    
                    addLog('✅ Acceso a ventana del formulario conseguido');
                    
                    // Lista de elementos de archivos a verificar
                    const fileInputs = [
                        { id: 'upload-hoja-asiento', category: 'upload_hoja_asiento' },
                        { id: 'upload-dni-comprador', category: 'upload_dni_comprador' },
                        { id: 'upload-dni-vendedor', category: 'upload_dni_vendedor' },
                        { id: 'upload-contrato-compraventa', category: 'upload_contrato_compraventa' },
                        { id: 'upload-permiso-circulacion', category: 'upload_permiso_circulacion' }
                    ];
                    
                    // Función para convertir archivo a base64
                    const convertFileToBase64 = (file) => {
                        return new Promise((resolve, reject) => {
                            const reader = new FileReader();
                            reader.onload = () => {
                                const base64 = reader.result.split(',')[1];
                                resolve(base64);
                            };
                            reader.onerror = reject;
                            reader.readAsDataURL(file);
                        });
                    };
                    
                    // Procesar cada campo de archivos
                    for (const inputConfig of fileInputs) {
                        try {
                            const element = parentWindow.document.getElementById(inputConfig.id);
                            if (element && element.files && element.files.length > 0) {
                                addLog(`📎 Procesando ${inputConfig.category}: ${element.files.length} archivo(s)`);
                                
                                for (let i = 0; i < element.files.length; i++) {
                                    const file = element.files[i];
                                    addLog(`   📄 Convirtiendo: ${file.name} (${file.size} bytes)`);
                                    
                                    try {
                                        const base64Content = await convertFileToBase64(file);
                                        files.push({
                                            name: file.name,
                                            content: base64Content,
                                            type: file.type || 'application/octet-stream',
                                            field: inputConfig.category,
                                            size: file.size
                                        });
                                        addLog(`   ✅ Convertido: ${file.name}`, 'success');
                                    } catch (e) {
                                        addLog(`   ❌ Error convirtiendo ${file.name}: ${e.message}`, 'error');
                                    }
                                }
                            }
                        } catch (e) {
                            addLog(`❌ Error accediendo a ${inputConfig.id}: ${e.message}`, 'error');
                        }
                    }
                    
                    if (files.length === 0) {
                        addLog('⚠️ No se encontraron archivos en el formulario', 'error');
                        updateStep('step-files', 'completed', '⚠️', 'Sin archivos para procesar');
                    } else {
                        addLog(`✅ Total archivos recuperados: ${files.length}`, 'success');
                        updateStep('step-files', 'completed', '✅', `${files.length} archivos recuperados`);
                    }
                    
                    resolve(files);
                    
                } catch (error) {
                    addLog(`❌ Error crítico recuperando archivos: ${error.message}`, 'error');
                    updateStep('step-files', 'failed', '❌', 'Error recuperando archivos');
                    resolve([]);
                }
            });
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
                        'User-Agent': 'TBV2-Auto-Processor-Simple/1.0'
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
        
        // Función principal simplificada
        async function processFiles() {
            try {
                updateStep('step-init', 'completed', '✅', 'Auto processor simple inicializado');
                
                // ✅ NUEVA IMPLEMENTACIÓN: Leer archivos directamente del DOM
                const files = await getFilesFromDOM();
                
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