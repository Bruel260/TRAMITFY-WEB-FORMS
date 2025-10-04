<?php
/**
 * Visor de Logs de WordPress
 * URL: https://tramitfy.es/wp-content/themes/xtra/ver-logs.php
 */

// Seguridad básica
$password = 'tramitfy2024';
$input_password = $_GET['pass'] ?? '';

if ($input_password !== $password) {
    die('Acceso denegado. Usa: ?pass=tramitfy2024');
}

$lines = $_GET['lines'] ?? 200;
$filter = $_GET['filter'] ?? '';

?>
<!DOCTYPE html>
<html>
<head>
    <title>Logs WordPress - Tramitfy</title>
    <style>
        body {
            font-family: monospace;
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
            margin: 0;
        }
        .header {
            background: #252526;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .controls {
            margin-bottom: 15px;
        }
        .controls a {
            background: #0e639c;
            color: white;
            padding: 8px 15px;
            text-decoration: none;
            border-radius: 3px;
            margin-right: 10px;
            display: inline-block;
        }
        .controls a:hover {
            background: #1177bb;
        }
        .log-container {
            background: #252526;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
        }
        .log-line {
            padding: 3px 0;
            border-bottom: 1px solid #333;
        }
        .log-line:hover {
            background: #2a2d2e;
        }
        .timestamp {
            color: #4ec9b0;
        }
        .error {
            color: #f48771;
        }
        .success {
            color: #4fc1ff;
        }
        .email {
            color: #dcdcaa;
        }
        .rdoc {
            color: #ce9178;
        }
        h1 {
            margin: 0 0 10px 0;
            color: #4ec9b0;
        }
        .stat {
            display: inline-block;
            margin-right: 20px;
            color: #858585;
        }
        .refresh-info {
            color: #858585;
            font-size: 12px;
            margin-top: 10px;
        }
    </style>
    <meta http-equiv="refresh" content="5">
</head>
<body>
    <div class="header">
        <h1>📋 Logs WordPress - Tramitfy</h1>
        <div class="stat">Mostrando últimas <strong><?php echo $lines; ?></strong> líneas</div>
        <div class="stat">Filtro: <strong><?php echo $filter ?: 'Ninguno'; ?></strong></div>
        <div class="stat">Auto-refresh: <strong>5 segundos</strong></div>
        <div class="refresh-info">Última actualización: <?php echo date('H:i:s'); ?></div>
    </div>

    <div class="controls">
        <a href="?pass=<?php echo $password; ?>&lines=50">50 líneas</a>
        <a href="?pass=<?php echo $password; ?>&lines=100">100 líneas</a>
        <a href="?pass=<?php echo $password; ?>&lines=200">200 líneas</a>
        <a href="?pass=<?php echo $password; ?>&lines=500">500 líneas</a>
        <a href="?pass=<?php echo $password; ?>&lines=200&filter=RDOC">Solo RDOC</a>
        <a href="?pass=<?php echo $password; ?>&lines=200&filter=Email">Solo Emails</a>
        <a href="?pass=<?php echo $password; ?>&lines=200&filter=ERROR">Solo Errores</a>
    </div>

    <div class="log-container">
        <?php
        // Leer logs vía FTP
        $ftp_server = '194.164.74.173';
        $ftp_user = 'u547005054';
        $ftp_pass = 'First-260spirit';

        $command = "lftp -c \"
set ftp:ssl-allow no
open -u $ftp_user,$ftp_pass $ftp_server
cd /domains/tramitfy.es/public_html/wp-content
cat tramitfy-debug.log
bye
\" 2>/dev/null | tail -$lines";

        if ($filter) {
            $command .= " | grep -i '$filter'";
        }

        $logs = shell_exec($command);

        if (!$logs) {
            echo "<div class='error'>⚠️ No hay logs disponibles o error al leer.</div>";
        } else {
            $lines_array = explode("\n", $logs);
            $total = count($lines_array);

            echo "<div style='color: #858585; margin-bottom: 10px;'>Total de líneas: $total</div>";

            foreach ($lines_array as $line) {
                if (empty(trim($line))) continue;

                $class = '';
                if (stripos($line, 'error') !== false || stripos($line, '❌') !== false) {
                    $class = 'error';
                } elseif (stripos($line, 'email') !== false || stripos($line, '📧') !== false) {
                    $class = 'email';
                } elseif (stripos($line, 'rdoc') !== false || stripos($line, 'RDOC') !== false) {
                    $class = 'rdoc';
                } elseif (stripos($line, 'success') !== false || stripos($line, '✅') !== false) {
                    $class = 'success';
                }

                // Highlight timestamp
                $line = preg_replace('/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/', '<span class="timestamp">$1</span>', $line);

                echo "<div class='log-line $class'>" . htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . "</div>";
            }
        }
        ?>
    </div>

    <div style="margin-top: 20px; color: #858585; font-size: 12px;">
        <p>💡 <strong>URLs útiles:</strong></p>
        <p>• Ver logs: <code>https://tramitfy.es/wp-content/themes/xtra/ver-logs.php?pass=tramitfy2024</code></p>
        <p>• Filtrar RDOC: <code>...?pass=tramitfy2024&filter=RDOC</code></p>
        <p>• Filtrar Emails: <code>...?pass=tramitfy2024&filter=Email</code></p>
        <p>• Más líneas: <code>...?pass=tramitfy2024&lines=500</code></p>
    </div>
</body>
</html>
