<?php
/**
 * Debug page for TBV2 logs - IMPROVED VERSION
 * Acceso: https://tramitfy.es/wp-content/themes/xtra/tbv2-debug-logs.php
 */

// Solo permitir acceso con clave
$allowed = false;
if (isset($_GET['key']) && $_GET['key'] === 'tramitfy2024debug') {
    $allowed = true;
}

if (!$allowed) {
    die('Acceso denegado. Añade ?key=tramitfy2024debug a la URL');
}

// Cargar WordPress para acceso a la DB
$wp_load_paths = [
    dirname(__FILE__) . '/../../../wp-load.php',
    dirname(__FILE__) . '/../../wp-load.php',
    dirname(__FILE__) . '/../wp-load.php',
    $_SERVER['DOCUMENT_ROOT'] . '/wp-load.php',
    '/home/u1005991160/domains/tramitfy.es/public_html/wp-load.php'
];

$wp_loaded = false;
foreach ($wp_load_paths as $path) {
    if (file_exists($path)) {
        require_once($path);
        $wp_loaded = true;
        break;
    }
}

// Forzar debug
if (!defined('WP_DEBUG')) {
    define('WP_DEBUG', true);
}
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Escribir un log de prueba
error_log('TBV2 DEBUG PAGE: Accessed at ' . date('Y-m-d H:i:s'));

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TBV2 Debug Logs</title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            background: #1a1a1a;
            color: #00ff00;
            padding: 20px;
            line-height: 1.6;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        h1 {
            color: #00ff00;
            border-bottom: 2px solid #00ff00;
            padding-bottom: 10px;
        }
        .section {
            background: #0a0a0a;
            border: 1px solid #00ff00;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
            overflow-x: auto;
        }
        .section h2 {
            color: #00ffff;
            margin-top: 0;
        }
        pre {
            white-space: pre-wrap;
            word-wrap: break-word;
            overflow-x: auto;
            background: #000;
            padding: 10px;
            border-radius: 3px;
        }
        .error {
            color: #ff3333;
        }
        .success {
            color: #33ff33;
        }
        .warning {
            color: #ffaa33;
        }
        .info {
            color: #33aaff;
        }
        .timestamp {
            color: #888;
        }
        .refresh {
            background: #00ff00;
            color: black;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            display: inline-block;
            margin: 10px 5px;
            font-weight: bold;
        }
        .log-entry {
            border-left: 3px solid #333;
            padding-left: 10px;
            margin: 10px 0;
            background: #111;
            padding: 10px;
            border-radius: 3px;
        }
        .log-entry.error {
            border-color: #ff3333;
            background: #1a0a0a;
        }
        .log-entry.success {
            border-color: #33ff33;
            background: #0a1a0a;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }
        th, td {
            padding: 8px;
            text-align: left;
            border: 1px solid #333;
        }
        th {
            background: #222;
            color: #00ffff;
        }
        td {
            background: #111;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 TBV2 Debug Logs</h1>
        <div style="margin: 20px 0;">
            <a href="?key=tramitfy2024debug&refresh=1" class="refresh">🔄 Actualizar</a>
            <a href="?key=tramitfy2024debug&test=transient" class="refresh">🧪 Test Transient</a>
            <a href="?key=tramitfy2024debug&test=log" class="refresh">📝 Test Log</a>
            <a href="?key=tramitfy2024debug&clear=transients" class="refresh">🗑️ Limpiar Transients</a>
        </div>
        
        <?php
        // Información del sistema
        echo '<div class="section">';
        echo '<h2>📊 Información del Sistema</h2>';
        echo '<table>';
        echo '<tr><td><strong>Fecha/Hora servidor:</strong></td><td>' . date('Y-m-d H:i:s') . '</td></tr>';
        echo '<tr><td><strong>PHP Version:</strong></td><td>' . phpversion() . '</td></tr>';
        echo '<tr><td><strong>WordPress Loaded:</strong></td><td>' . ($wp_loaded ? '<span class="success">✅ SÍ</span>' : '<span class="error">❌ NO</span>') . '</td></tr>';
        if ($wp_loaded && function_exists('get_bloginfo')) {
            echo '<tr><td><strong>WordPress Version:</strong></td><td>' . get_bloginfo('version') . '</td></tr>';
            echo '<tr><td><strong>Site URL:</strong></td><td>' . get_site_url() . '</td></tr>';
        }
        echo '<tr><td><strong>Memory Limit:</strong></td><td>' . ini_get('memory_limit') . '</td></tr>';
        echo '<tr><td><strong>Max Execution Time:</strong></td><td>' . ini_get('max_execution_time') . ' segundos</td></tr>';
        echo '<tr><td><strong>WP_DEBUG:</strong></td><td>' . (defined('WP_DEBUG') && WP_DEBUG ? '<span class="success">✅ Activado</span>' : '<span class="error">❌ Desactivado</span>') . '</td></tr>';
        echo '</table>';
        echo '</div>';
        
        // Buscar logs del sistema
        echo '<div class="section">';
        echo '<h2>📁 Logs del Sistema</h2>';
        
        $log_locations = [
            'Hostinger Error Log' => '/home/u1005991160/domains/tramitfy.es/logs/error.log',
            'WordPress Debug' => '/home/u1005991160/domains/tramitfy.es/public_html/wp-content/debug.log',
            'PHP Error Log' => ini_get('error_log'),
            'Apache Error Log' => '/var/log/apache2/error.log',
            'System Messages' => '/var/log/messages',
            'Temp TBV2 Log' => '/tmp/tbv2-debug.log'
        ];
        
        echo '<table>';
        echo '<tr><th>Log File</th><th>Status</th><th>Size</th><th>Last Modified</th></tr>';
        
        foreach ($log_locations as $name => $path) {
            echo '<tr>';
            echo '<td>' . $name . '</td>';
            if (file_exists($path) && is_readable($path)) {
                $size = filesize($path);
                $modified = date('Y-m-d H:i:s', filemtime($path));
                echo '<td class="success">✅ Encontrado</td>';
                echo '<td>' . ($size > 1024 ? round($size/1024, 2) . ' KB' : $size . ' bytes') . '</td>';
                echo '<td>' . $modified . '</td>';
            } else {
                echo '<td class="error">❌ No accesible</td>';
                echo '<td>-</td>';
                echo '<td>-</td>';
            }
            echo '</tr>';
        }
        echo '</table>';
        
        // Leer logs TBV2 específicos
        $found_logs = false;
        foreach ($log_locations as $name => $log_file) {
            if (file_exists($log_file) && is_readable($log_file)) {
                $lines = [];
                $handle = fopen($log_file, 'r');
                if ($handle) {
                    // Ir al final del archivo
                    fseek($handle, -10000, SEEK_END);
                    $content = fread($handle, 10000);
                    fclose($handle);
                    
                    // Buscar líneas TBV2
                    $all_lines = explode("\n", $content);
                    foreach ($all_lines as $line) {
                        if (stripos($line, 'TBV2') !== false || 
                            stripos($line, 'transferencia-barco-v2') !== false ||
                            stripos($line, 'tbv2_') !== false) {
                            $lines[] = $line;
                        }
                    }
                }
                
                if (!empty($lines)) {
                    echo '<h3>📋 Logs TBV2 en ' . $name . ':</h3>';
                    echo '<pre>';
                    foreach (array_slice($lines, -20) as $line) { // Últimas 20 líneas
                        if (stripos($line, 'ERROR') !== false) {
                            echo '<span class="error">' . htmlspecialchars($line) . '</span>' . "\n";
                        } elseif (stripos($line, 'SUCCESS') !== false || stripos($line, 'exitoso') !== false) {
                            echo '<span class="success">' . htmlspecialchars($line) . '</span>' . "\n";
                        } elseif (stripos($line, 'WARNING') !== false) {
                            echo '<span class="warning">' . htmlspecialchars($line) . '</span>' . "\n";
                        } else {
                            echo '<span class="info">' . htmlspecialchars($line) . '</span>' . "\n";
                        }
                    }
                    echo '</pre>';
                    $found_logs = true;
                }
            }
        }
        
        if (!$found_logs) {
            echo '<p class="warning">⚠️ No se encontraron logs TBV2 específicos</p>';
        }
        echo '</div>';
        
        // Verificar transients TBV2
        if ($wp_loaded && isset($GLOBALS['wpdb'])) {
            echo '<div class="section">';
            echo '<h2>💾 Transients TBV2 Activos</h2>';
            
            global $wpdb;
            $transients = $wpdb->get_results(
                "SELECT option_name, option_value, 
                        (SELECT option_value FROM {$wpdb->options} WHERE option_name = CONCAT('_transient_timeout_', SUBSTRING(o.option_name, 12))) as timeout
                 FROM {$wpdb->options} o
                 WHERE option_name LIKE '_transient_tbv2_transfer_%' 
                 ORDER BY option_name DESC 
                 LIMIT 20"
            );
            
            if ($transients) {
                echo '<table>';
                echo '<tr><th>Order ID</th><th>Cliente</th><th>Email</th><th>Importe</th><th>Timestamp</th><th>Expira</th></tr>';
                foreach ($transients as $transient) {
                    $order_id = str_replace('_transient_tbv2_transfer_', '', $transient->option_name);
                    $data = maybe_unserialize($transient->option_value);
                    $expires = $transient->timeout ? date('Y-m-d H:i:s', $transient->timeout) : 'Never';
                    $is_expired = $transient->timeout && $transient->timeout < time();
                    
                    echo '<tr class="' . ($is_expired ? 'error' : '') . '">';
                    echo '<td><strong>' . $order_id . '</strong></td>';
                    echo '<td>' . ($data['customerName'] ?? 'N/A') . '</td>';
                    echo '<td>' . ($data['customerEmail'] ?? 'N/A') . '</td>';
                    echo '<td>' . ($data['finalAmount'] ?? 'N/A') . '€</td>';
                    echo '<td>' . ($data['timestamp'] ?? 'N/A') . '</td>';
                    echo '<td>' . $expires . ($is_expired ? ' <span class="error">(EXPIRADO)</span>' : '') . '</td>';
                    echo '</tr>';
                }
                echo '</table>';
                
                // Mostrar detalles del último transient
                if (!empty($transients)) {
                    $last = $transients[0];
                    $data = maybe_unserialize($last->option_value);
                    echo '<h3>📋 Detalles del último transient:</h3>';
                    echo '<pre>' . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . '</pre>';
                }
            } else {
                echo '<p class="warning">⚠️ No hay transients TBV2 activos</p>';
            }
            
            // Contar todos los transients
            $all_transients = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient_%'");
            echo '<p>Total de transients en el sistema: <strong>' . $all_transients . '</strong></p>';
            echo '</div>';
            
            // Verificar últimas entradas en el log de errores
            echo '<div class="section">';
            echo '<h2>🔴 Últimos Errores PHP</h2>';
            
            $errors = $wpdb->get_results(
                "SELECT option_value 
                 FROM {$wpdb->options} 
                 WHERE option_name = 'php_error_log'
                 LIMIT 1"
            );
            
            if (function_exists('error_get_last')) {
                $last_error = error_get_last();
                if ($last_error) {
                    echo '<div class="log-entry error">';
                    echo '<strong>Último error PHP:</strong><br>';
                    echo 'Type: ' . $last_error['type'] . '<br>';
                    echo 'Message: ' . $last_error['message'] . '<br>';
                    echo 'File: ' . $last_error['file'] . '<br>';
                    echo 'Line: ' . $last_error['line'];
                    echo '</div>';
                }
            }
            echo '</div>';
        } else {
            echo '<div class="section error">';
            echo '<h2>❌ Base de Datos WordPress</h2>';
            echo '<p>No se pudo conectar a la base de datos WordPress.</p>';
            echo '<p>WordPress loaded: ' . ($wp_loaded ? 'YES' : 'NO') . '</p>';
            echo '<p>wpdb exists: ' . (isset($GLOBALS['wpdb']) ? 'YES' : 'NO') . '</p>';
            echo '</div>';
        }
        
        // Test actions
        if (isset($_GET['test'])) {
            echo '<div class="section">';
            echo '<h2>🧪 Test Ejecutado</h2>';
            
            if ($_GET['test'] === 'transient' && $wp_loaded) {
                $test_order_id = 'TEST' . time();
                $test_data = [
                    'customerName' => 'Test Debug',
                    'customerEmail' => 'test@debug.com',
                    'finalAmount' => 99.99,
                    'timestamp' => date('Y-m-d H:i:s'),
                    'test' => true
                ];
                set_transient('tbv2_transfer_' . $test_order_id, $test_data, 3600);
                echo '<p class="success">✅ Transient de prueba creado: ' . $test_order_id . '</p>';
                echo '<pre>' . json_encode($test_data, JSON_PRETTY_PRINT) . '</pre>';
            }
            
            if ($_GET['test'] === 'log') {
                error_log('TBV2 TEST: Log de prueba generado en ' . date('Y-m-d H:i:s'));
                error_log('TBV2: Order ID generado: TEST' . time());
                error_log('TBV2: Pago exitoso detectado. Order ID: TEST' . time());
                echo '<p class="success">✅ Logs de prueba escritos</p>';
            }
            
            echo '</div>';
        }
        
        if (isset($_GET['clear']) && $_GET['clear'] === 'transients' && $wp_loaded) {
            global $wpdb;
            $deleted = $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_tbv2_transfer_%' OR option_name LIKE '_transient_timeout_tbv2_transfer_%'");
            echo '<div class="section success">';
            echo '<h2>🗑️ Limpieza Completada</h2>';
            echo '<p>Se eliminaron ' . $deleted . ' registros de transients TBV2</p>';
            echo '</div>';
        }
        ?>
        
        <div class="section">
            <h2>📝 Instrucciones de Prueba</h2>
            <ol>
                <li><strong>Completa un formulario TBV2</strong> hasta el pago</li>
                <li><strong>Realiza el pago</strong> en Redsys</li>
                <li><strong>Vuelve aquí</strong> y actualiza la página</li>
                <li><strong>Deberías ver:</strong>
                    <ul>
                        <li>✅ Un nuevo transient con el Order ID</li>
                        <li>✅ Logs del procesamiento</li>
                        <li>✅ Estado del pago</li>
                    </ul>
                </li>
            </ol>
        </div>
        
        <div class="section info">
            <h2>ℹ️ Información de Debug</h2>
            <p><strong>URL de esta página:</strong> <?php echo 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']; ?></p>
            <p><strong>Server Software:</strong> <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></p>
            <p><strong>Document Root:</strong> <?php echo $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown'; ?></p>
            <p><strong>Script Path:</strong> <?php echo __FILE__; ?></p>
        </div>
    </div>
</body>
</html>