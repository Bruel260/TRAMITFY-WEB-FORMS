<?php
/**
 * TRAMITFY - TMV2 MINIMAL para functions.php
 * Solo funciones, CERO ejecución inmediata
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shortcode básico TMV2
 */
function tmv2_shortcode_handler($atts) {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    
    // Solo activar en páginas de moto
    if (strpos($uri, 'moto') === false && strpos($uri, 'testingfy') === false) {
        return '<!-- TMV2: Página no autorizada -->';
    }
    
    // Cargar archivo completo si existe
    $full_file = get_template_directory() . '/transferencia-moto-v2-full.php';
    if (file_exists($full_file)) {
        ob_start();
        include_once $full_file;
        $content = ob_get_clean();
        return $content ?: '<div>Error cargando TMV2</div>';
    }
    
    return '<div>TMV2: Archivo completo no encontrado</div>';
}

/**
 * AJAX básico TMV2
 */
function tmv2_ajax_basic() {
    wp_send_json_success(['message' => 'TMV2 minimal ready']);
}

/**
 * Función de inicialización - SE EJECUTA EN init, NO inmediatamente
 */
function tmv2_init() {
    // Solo registrar si no existe ya
    if (!shortcode_exists('transferencia_moto_v2')) {
        add_shortcode('transferencia_moto_v2', 'tmv2_shortcode_handler');
    }
    
    if (!shortcode_exists('transferencia_moto_v2_form')) {
        add_shortcode('transferencia_moto_v2_form', 'tmv2_shortcode_handler');
    }
}

/**
 * AJAX init - SE EJECUTA EN wp_loaded, NO inmediatamente  
 */
function tmv2_ajax_init() {
    add_action('wp_ajax_tmv2_basic', 'tmv2_ajax_basic');
    add_action('wp_ajax_nopriv_tmv2_basic', 'tmv2_ajax_basic');
}

// REGISTRAR HOOKS - No ejecutar código inmediatamente
add_action('init', 'tmv2_init');
add_action('wp_loaded', 'tmv2_ajax_init');