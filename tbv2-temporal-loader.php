<?php
/**
 * TBV2 TEMPORAL SYSTEM LOADER
 * Carga e inicializa todo el sistema temporal
 * 
 * INCLUIR ESTE ARCHIVO AL FINAL DE transferencia-barco-v2.php
 */

if (!defined('ABSPATH')) {
    exit('Acceso directo no permitido');
}

// Solo cargar en páginas que contienen el formulario TBV2
global $post;
if (!$post || strpos($post->post_content, 'transferencia_barco_v2') === false) {
    return;
}

error_log("🔄 TBV2 TEMPORAL LOADER: Inicializando sistema temporal...");

try {
    // 1. Cargar sistema de storage temporal
    if (file_exists(__DIR__ . '/tbv2-temporal-storage.php')) {
        require_once(__DIR__ . '/tbv2-temporal-storage.php');
        error_log("✅ TBV2 TEMPORAL LOADER: Storage temporal cargado");
    } else {
        throw new Exception('Archivo tbv2-temporal-storage.php no encontrado');
    }
    
    // 2. Cargar sistema de callbacks de Redsys
    if (file_exists(__DIR__ . '/tbv2-redsys-callback.php')) {
        require_once(__DIR__ . '/tbv2-redsys-callback.php');
        error_log("✅ TBV2 TEMPORAL LOADER: Sistema de callbacks cargado");
    } else {
        throw new Exception('Archivo tbv2-redsys-callback.php no encontrado');
    }
    
    // 3. Cargar parche de integración
    if (file_exists(__DIR__ . '/tbv2-integration-patch.php')) {
        require_once(__DIR__ . '/tbv2-integration-patch.php');
        error_log("✅ TBV2 TEMPORAL LOADER: Parche de integración cargado");
    } else {
        throw new Exception('Archivo tbv2-integration-patch.php no encontrado');
    }
    
    // 4. Verificar que el directorio temporal existe y es escribible
    $temp_dir = wp_upload_dir()['basedir'] . '/temp-tbv2/';
    if (!is_dir($temp_dir)) {
        if (!wp_mkdir_p($temp_dir)) {
            throw new Exception('No se pudo crear directorio temporal: ' . $temp_dir);
        }
    }
    
    if (!is_writable($temp_dir)) {
        throw new Exception('Directorio temporal no es escribible: ' . $temp_dir);
    }
    
    // 5. Programar limpieza automática si no está programada
    if (!wp_next_scheduled('tbv2_cleanup_temp_data')) {
        wp_schedule_event(time(), 'twicedaily', 'tbv2_cleanup_temp_data');
        error_log("📅 TBV2 TEMPORAL LOADER: Limpieza automática programada");
    }
    
    // 6. Añadir hooks necesarios
    add_action('wp_enqueue_scripts', 'tbv2_enqueue_temporal_assets');
    add_action('wp_ajax_tbv2_temporal_status', 'tbv2_handle_temporal_status');
    add_action('wp_ajax_nopriv_tbv2_temporal_status', 'tbv2_handle_temporal_status');
    
    error_log("✅ TBV2 TEMPORAL LOADER: Sistema temporal completamente inicializado");
    
} catch (Exception $e) {
    error_log("❌ TBV2 TEMPORAL LOADER ERROR: " . $e->getMessage());
    
    // En caso de error, mostrar mensaje al admin
    if (current_user_can('administrator')) {
        add_action('admin_notices', function() use ($e) {
            echo '<div class="notice notice-error"><p>';
            echo '<strong>TBV2 Temporal System Error:</strong> ' . esc_html($e->getMessage());
            echo '</p></div>';
        });
    }
}

/**
 * Encolar assets necesarios para el sistema temporal
 */
function tbv2_enqueue_temporal_assets() {
    // CSS adicional para el sistema temporal
    wp_add_inline_style('tbv2-styles', '
        .tbv2-temporal-status {
            position: fixed;
            bottom: 20px;
            left: 20px;
            background: #f0f9ff;
            border: 1px solid #0ea5e9;
            border-radius: 6px;
            padding: 12px;
            font-size: 12px;
            color: #0c4a6e;
            z-index: 9999;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            max-width: 200px;
        }
        
        .tbv2-temporal-status.hidden {
            display: none;
        }
        
        .tbv2-system-alert {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            line-height: 1.4;
        }
        
        .tbv2-system-alert h4 {
            margin: 0 0 8px 0;
            font-size: 16px;
            font-weight: 600;
        }
        
        .tbv2-system-alert p {
            margin: 0 0 8px 0;
            font-size: 14px;
        }
        
        .tbv2-system-alert ul {
            margin: 8px 0;
            padding-left: 20px;
        }
        
        .tbv2-system-alert li {
            margin-bottom: 4px;
        }
    ');
    
    // JavaScript adicional
    wp_add_inline_script('jquery', '
        jQuery(document).ready(function($) {
            // Mostrar indicador de estado temporal para admins
            if (typeof tbv2Ajax !== "undefined" && tbv2Ajax.is_admin) {
                tbv2_show_temporal_status();
            }
            
            // Función para mostrar estado del sistema temporal
            function tbv2_show_temporal_status() {
                $.post(tbv2Ajax.url, {
                    action: "tbv2_temporal_status",
                    nonce: tbv2Ajax.nonce
                })
                .done(function(response) {
                    if (response.success) {
                        const stats = response.data;
                        const statusHtml = `
                            <div class="tbv2-temporal-status" id="tbv2-status">
                                <strong>📊 TBV2 Temporal</strong><br>
                                Activos: ${stats.active_temporals}<br>
                                Pendientes: ${stats.pending_retries}<br>
                                Fallos: ${stats.failed_payments}
                            </div>
                        `;
                        $("body").append(statusHtml);
                        
                        // Auto-ocultar tras 10 segundos
                        setTimeout(() => {
                            $("#tbv2-status").fadeOut();
                        }, 10000);
                    }
                });
            }
        });
    ');
}

/**
 * AJAX handler para obtener estado del sistema temporal
 */
function tbv2_handle_temporal_status() {
    if (!current_user_can('administrator')) {
        wp_send_json_error('Sin permisos');
    }
    
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'tbv2_nonce')) {
        wp_send_json_error('Nonce inválido');
    }
    
    try {
        $stats = TBV2_Redsys_Callback::get_temp_stats();
        wp_send_json_success($stats);
    } catch (Exception $e) {
        wp_send_json_error($e->getMessage());
    }
}

/**
 * Panel de administración para el sistema temporal (solo para admins)
 */
if (current_user_can('administrator') && is_admin()) {
    add_action('admin_menu', 'tbv2_add_admin_menu');
}

function tbv2_add_admin_menu() {
    add_submenu_page(
        'tools.php',
        'TBV2 Temporal System',
        'TBV2 Temporal',
        'manage_options',
        'tbv2-temporal',
        'tbv2_render_admin_page'
    );
}

function tbv2_render_admin_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Sin permisos');
    }
    
    // Procesar acciones del admin
    if (isset($_POST['action'])) {
        $action = sanitize_text_field($_POST['action']);
        
        switch($action) {
            case 'cleanup_expired':
                $cleaned = TBV2_Temporal_Storage::cleanup_expired_data();
                echo '<div class="notice notice-success"><p>Limpieza completada. ' . $cleaned . ' elementos eliminados.</p></div>';
                break;
                
            case 'retry_transfer':
                $temp_id = sanitize_text_field($_POST['temp_id'] ?? '');
                if (!empty($temp_id)) {
                    $result = TBV2_Redsys_Callback::retry_failed_transfer($temp_id);
                    if ($result['success']) {
                        echo '<div class="notice notice-success"><p>Transferencia reintentada exitosamente.</p></div>';
                    } else {
                        echo '<div class="notice notice-error"><p>Error: ' . esc_html($result['error']) . '</p></div>';
                    }
                }
                break;
        }
    }
    
    // Obtener estadísticas
    $stats = TBV2_Redsys_Callback::get_temp_stats();
    
    ?>
    <div class="wrap">
        <h1>TBV2 Sistema Temporal</h1>
        
        <div class="card">
            <h2>📊 Estadísticas del Sistema</h2>
            <table class="widefat">
                <tr>
                    <td><strong>Datos temporales activos:</strong></td>
                    <td><?php echo $stats['active_temporals']; ?></td>
                </tr>
                <tr>
                    <td><strong>Transferencias pendientes de retry:</strong></td>
                    <td><?php echo $stats['pending_retries']; ?></td>
                </tr>
                <tr>
                    <td><strong>Pagos fallidos:</strong></td>
                    <td><?php echo $stats['failed_payments']; ?></td>
                </tr>
                <tr>
                    <td><strong>Tamaño directorio temporal:</strong></td>
                    <td><?php echo size_format($stats['temp_directory_size']); ?></td>
                </tr>
                <tr>
                    <td><strong>Próxima limpieza automática:</strong></td>
                    <td><?php echo $stats['last_cleanup'] ? date('Y-m-d H:i:s', $stats['last_cleanup']) : 'No programada'; ?></td>
                </tr>
            </table>
        </div>
        
        <div class="card">
            <h2>🛠️ Herramientas de Administración</h2>
            
            <form method="post" style="margin-bottom: 20px;">
                <input type="hidden" name="action" value="cleanup_expired">
                <?php wp_nonce_field('tbv2_admin', 'nonce'); ?>
                <button type="submit" class="button button-secondary">
                    🧹 Limpiar datos expirados
                </button>
            </form>
            
            <?php if ($stats['pending_retries'] > 0): ?>
                <h3>Transferencias pendientes de retry:</h3>
                <?php
                global $wpdb;
                $retries = $wpdb->get_results(
                    "SELECT option_name, option_value FROM {$wpdb->options} 
                     WHERE option_name LIKE '_transient_tbv2_retry_%'"
                );
                
                foreach ($retries as $retry) {
                    $temp_id = str_replace('_transient_tbv2_retry_', '', $retry->option_name);
                    $retry_data = maybe_unserialize($retry->option_value);
                    
                    echo '<div style="border: 1px solid #ccc; padding: 10px; margin: 10px 0;">';
                    echo '<strong>Temp ID:</strong> ' . esc_html($temp_id) . '<br>';
                    echo '<strong>Error:</strong> ' . esc_html($retry_data['error'] ?? 'N/A') . '<br>';
                    echo '<strong>Intentos:</strong> ' . intval($retry_data['retry_count'] ?? 0) . '<br>';
                    
                    echo '<form method="post" style="margin-top: 10px;">';
                    echo '<input type="hidden" name="action" value="retry_transfer">';
                    echo '<input type="hidden" name="temp_id" value="' . esc_attr($temp_id) . '">';
                    wp_nonce_field('tbv2_admin', 'nonce');
                    echo '<button type="submit" class="button button-primary">🔄 Reintentar transferencia</button>';
                    echo '</form>';
                    
                    echo '</div>';
                }
                ?>
            <?php endif; ?>
        </div>
        
        <div class="card">
            <h2>📋 Información del Sistema</h2>
            <p><strong>Versión:</strong> TBV2 Temporal System v1.0</p>
            <p><strong>Estado:</strong> <?php echo defined('TBV2_TEMP_DIR') ? '✅ Activo' : '❌ Inactivo'; ?></p>
            <p><strong>Directorio temporal:</strong> <?php echo TBV2_TEMP_DIR; ?></p>
            <p><strong>TTL de datos temporales:</strong> <?php echo TBV2_TEMP_TTL / 3600; ?> horas</p>
            
            <h3>🔧 Configuración</h3>
            <ul>
                <li>Storage temporal: WordPress transients</li>
                <li>Archivos temporales: Sistema de archivos local</li>
                <li>Limpieza automática: Cada 12 horas</li>
                <li>Webhook definitivo: <?php echo defined('TBV2_WEBHOOK_URL') ? TBV2_WEBHOOK_URL : 'No configurado'; ?></li>
            </ul>
        </div>
    </div>
    <?php
}

/**
 * Activar el sistema añadiendo las líneas necesarias al final del archivo
 */
function tbv2_show_activation_instructions() {
    if (!current_user_can('administrator')) {
        return;
    }
    
    ?>
    <div class="notice notice-info">
        <p><strong>TBV2 Sistema Temporal:</strong> Para activar completamente, añada esta línea al final del archivo <code>transferencia-barco-v2.php</code>:</p>
        <code>require_once(__DIR__ . '/tbv2-temporal-loader.php');</code>
    </div>
    <?php
}

if (is_admin()) {
    add_action('admin_notices', 'tbv2_show_activation_instructions');
}

error_log("✅ TBV2 TEMPORAL LOADER: Archivo de carga completamente configurado");