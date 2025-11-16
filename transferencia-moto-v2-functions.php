<?php
/**
 * TRAMITFY - TMV2 FUNCTIONS ONLY
 * Solo las funciones esenciales para functions.php
 * Sin output HTML, sin protecciones complejas
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Función simplificada de autorización para TMV2
 */
function tmv2_is_authorized_request() {
    // En functions.php siempre permitir
    if (!isset($_SERVER['REQUEST_URI'])) {
        return true; 
    }
    
    // AJAX: solo permitir acciones TMV2
    if (defined('DOING_AJAX') && DOING_AJAX) {
        $action = $_POST['action'] ?? $_GET['action'] ?? '';
        $tmv2_actions = [
            'tmv2_create_redsys_payment',
            'tmv2_store_files',
            'tmv2_send_confirmation_emails'
        ];
        return in_array($action, $tmv2_actions);
    }
    
    // Verificar URL contiene moto
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    return strpos($uri, 'moto') !== false || strpos($uri, 'testingfy') !== false;
}

/**
 * Registrar shortcode TMV2
 */
function tmv2_register_shortcode() {
    add_shortcode('transferencia_moto_v2', 'tmv2_render_basic');
    add_shortcode('transferencia_moto_v2_form', 'tmv2_render_basic');
}

/**
 * Render básico del shortcode
 */
function tmv2_render_basic($atts) {
    if (!tmv2_is_authorized_request()) {
        return '<!-- TMV2: No autorizado -->';
    }
    
    // Solo cargar el archivo completo cuando sea necesario
    if (file_exists(get_template_directory() . '/transferencia-moto-v2-full.php')) {
        ob_start();
        include get_template_directory() . '/transferencia-moto-v2-full.php';
        return ob_get_clean();
    }
    
    return '<div id="tmv2-placeholder">Cargando formulario TMV2...</div>';
}

/**
 * AJAX Handler para crear pago Redsys
 */
function tmv2_create_redsys_payment_handler() {
    if (!tmv2_is_authorized_request()) {
        wp_send_json_error('No autorizado');
        return;
    }
    
    // Lógica básica del handler
    wp_send_json_success(['message' => 'TMV2 payment handler ready']);
}

/**
 * AJAX Handler para almacenar archivos
 */
function tmv2_store_files_handler() {
    if (!tmv2_is_authorized_request()) {
        wp_send_json_error('No autorizado');
        return;
    }
    
    wp_send_json_success(['message' => 'TMV2 files handler ready']);
}

// Registrar funciones solo si es necesario
if (tmv2_is_authorized_request()) {
    // Shortcodes
    add_action('init', 'tmv2_register_shortcode');
    
    // AJAX Handlers
    add_action('wp_ajax_tmv2_create_redsys_payment', 'tmv2_create_redsys_payment_handler');
    add_action('wp_ajax_nopriv_tmv2_create_redsys_payment', 'tmv2_create_redsys_payment_handler');
    
    add_action('wp_ajax_tmv2_store_files', 'tmv2_store_files_handler');
    add_action('wp_ajax_nopriv_tmv2_store_files', 'tmv2_store_files_handler');
}