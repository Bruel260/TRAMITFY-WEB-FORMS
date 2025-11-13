<?php
/**
 * TBV2 AUTO PROCESSOR - SIMPLE WORKING VERSION
 * 
 * Versión simple que funcionaba antes de los conflictos de plugins
 * Sin modificaciones complejas, solo la funcionalidad básica
 */

header('Content-Type: text/html; charset=UTF-8');

$orderId = $_GET['order_id'] ?? '';
$success = $_GET['success'] ?? '';

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
                <strong>Inicializando auto processor...</strong>
            </div>
            <div class="step" id="step-files">
                <span class="status-icon">⏳</span>
                <strong>Recuperando archivos...</strong>
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
            <div>🚀 TBV2 Auto Processor iniciado - Order: <?php echo htmlspecialchars($orderId); ?></div>
            <div>📋 Método: Bridge API simple</div>
            <div>⏳ Iniciando proceso...</div>
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
        console.log('🚀 TBV2 Auto Processor Simple - Order: <?php echo $orderId; ?>');
        
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
        
        function addLog(message, type) {
            const logOutput = document.getElementById('log-output');
            if (logOutput) {
                const div = document.createElement('div');
                div.textContent = message;
                if (type === 'error') {
                    div.style.color = '#ffcdd2';
                } else if (type === 'success') {
                    div.style.color = '#c8e6c9';
                }
                logOutput.appendChild(div);
                logOutput.scrollTop = logOutput.scrollHeight;
            }
            console.log(message);
        }
        
        // Simple fetch to bridge API
        function getFilesFromBridge() {
            return new Promise(function(resolve, reject) {
                addLog('📂 Conectando con bridge API...');
                updateStep('step-files', 'processing', '⏳', 'Conectando con bridge API...');
                
                const bridgeUrl = 'https://tramitfy.es/wp-content/themes/xtra/tbv2-file-bridge.php?order_id=' + orderId;
                
                fetch(bridgeUrl, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json'
                    }
                })
                .then(function(response) {
                    if (!response.ok) {
                        throw new Error('HTTP ' + response.status);
                    }
                    return response.json();
                })
                .then(function(data) {
                    if (data.error) {
                        throw new Error(data.error);
                    }
                    
                    const files = data.files || [];
                    
                    if (files.length === 0) {
                        addLog('⚠️ No se encontraron archivos');
                        updateStep('step-files', 'completed', '⚠️', 'Sin archivos para procesar');
                    } else {
                        addLog('✅ Archivos recuperados: ' + files.length, 'success');
                        updateStep('step-files', 'completed', '✅', files.length + ' archivos recuperados');
                    }
                    
                    resolve(files);
                })
                .catch(function(error) {
                    addLog('❌ Error: ' + error.message, 'error');
                    updateStep('step-files', 'failed', '❌', 'Error en bridge API');
                    resolve([]);
                });
            });
        }
        
        function uploadToWebhook(tramiteId, files) {
            return new Promise(function(resolve, reject) {
                addLog('🚀 Enviando al webhook...');
                updateStep('step-upload', 'processing', '⏳', 'Enviando al webhook...');
                
                const webhookUrl = 'https://tramitfy.org/api/herramientas/barcos/webhook';
                
                const payload = {
                    files_update: 'true',
                    tramiteId: tramiteId,
                    attachments: files
                };
                
                fetch(webhookUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(payload)
                })
                .then(function(response) {
                    if (response.ok) {
                        addLog('✅ Upload exitoso', 'success');
                        updateStep('step-upload', 'completed', '✅', 'Archivos enviados');
                        resolve(true);
                    } else {
                        addLog('❌ Error HTTP: ' + response.status, 'error');
                        updateStep('step-upload', 'failed', '❌', 'Error en upload');
                        resolve(false);
                    }
                })
                .catch(function(error) {
                    addLog('❌ Error: ' + error.message, 'error');
                    updateStep('step-upload', 'failed', '❌', 'Error crítico');
                    resolve(false);
                });
            });
        }
        
        function processFiles() {
            updateStep('step-init', 'completed', '✅', 'Inicializado');
            
            getFilesFromBridge()
                .then(function(files) {
                    if (files.length === 0) {
                        updateStep('step-upload', 'completed', 'ℹ️', 'Sin archivos para enviar');
                        updateStep('step-complete', 'completed', '✅', 'Completado sin archivos');
                        
                        document.getElementById('result-message').textContent = 'Su trámite ha sido procesado correctamente. No se encontraron archivos adjuntos.';
                        document.getElementById('file-stats').innerHTML = '<strong>Archivos:</strong> 0<br><strong>Estado:</strong> ✅ Completado sin archivos';
                    } else {
                        return uploadToWebhook(tramiteId, files)
                            .then(function(success) {
                                if (success) {
                                    updateStep('step-complete', 'completed', '✅', 'Completado correctamente');
                                    document.getElementById('result-message').textContent = 'Su trámite ha sido procesado correctamente con todos los archivos adjuntos.';
                                    document.getElementById('file-stats').innerHTML = '<strong>Archivos:</strong> ' + files.length + '<br><strong>Estado:</strong> ✅ Todos subidos';
                                } else {
                                    updateStep('step-complete', 'failed', '❌', 'Error procesando archivos');
                                    document.getElementById('result-message').textContent = 'El trámite fue creado pero hubo problemas con los archivos.';
                                    document.getElementById('file-stats').innerHTML = '<strong>Archivos:</strong> ' + files.length + '<br><strong>Estado:</strong> ❌ Error en upload';
                                }
                            });
                    }
                })
                .then(function() {
                    setTimeout(function() {
                        document.getElementById('final-result').style.display = 'block';
                    }, 1000);
                })
                .catch(function(error) {
                    addLog('❌ Error crítico: ' + error.message, 'error');
                    updateStep('step-complete', 'failed', '❌', 'Error crítico');
                    
                    document.getElementById('result-message').textContent = 'Hubo un error técnico procesando el trámite.';
                    document.getElementById('file-stats').innerHTML = '<strong>Estado:</strong> ❌ Error técnico';
                    document.getElementById('final-result').style.display = 'block';
                });
        }
        
        // Iniciar después de carga
        setTimeout(processFiles, 1000);
        
    </script>
</body>
</html>