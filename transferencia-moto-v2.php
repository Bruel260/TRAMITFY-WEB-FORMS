<?php
/**
 * TRAMITFY - TMV2 STANDALONE (NO para functions.php)
 * Solo se carga cuando se usa el shortcode específicamente
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Verificar si ya está cargado para evitar redefinición
if (function_exists('tmv2_render_form')) {
    return;
}

/**
 * Renderizar formulario TMV2 SOLO cuando se solicita
 */
function tmv2_render_form() {
    // Verificar que estemos en contexto correcto
    if (is_admin()) {
        return '<div>TMV2: Solo disponible en frontend</div>';
    }
    
    // Cargar archivo completo
    $full_file = get_template_directory() . '/transferencia-moto-v2-full.php';
    if (file_exists($full_file)) {
        ob_start();
        include_once $full_file;
        return ob_get_clean();
    }
    
    return '<div>TMV2: Sistema no disponible</div>';
}

/**
 * Auto-registrar shortcode SOLO en las páginas que lo necesiten
 */
function tmv2_auto_register() {
    global $post;
    
    // Solo registrar si la página contiene el shortcode
    if (is_object($post) && 
        (strpos($post->post_content, '[transferencia_moto_v2') !== false ||
         strpos($_SERVER['REQUEST_URI'], 'moto') !== false)) {
        
        add_shortcode('transferencia_moto_v2', 'tmv2_render_form');
        add_shortcode('transferencia_moto_v2_form', 'tmv2_render_form');
    }
}

// Solo registrar en wp si realmente se necesita
add_action('wp', 'tmv2_auto_register');