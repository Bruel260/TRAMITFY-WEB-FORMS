<?php
/**
 * TMV2 LOADER INDEPENDIENTE
 * Para usar sin functions.php
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Cargar TMV2 solo cuando se detecta que es necesario
 */
function load_tmv2_if_needed() {
    global $post;
    
    // Detectar si se necesita TMV2
    $needs_tmv2 = false;
    
    // Verificar en contenido del post
    if (is_object($post) && !empty($post->post_content)) {
        if (strpos($post->post_content, '[transferencia_moto_v2') !== false) {
            $needs_tmv2 = true;
        }
    }
    
    // Verificar en URL
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($uri, 'moto') !== false || strpos($uri, 'transferencia-propiedad-moto') !== false) {
        $needs_tmv2 = true;
    }
    
    // Solo cargar si se necesita
    if ($needs_tmv2) {
        include_once get_template_directory() . '/transferencia-moto-v2.php';
    }
}

// Hook tardío para tener $post disponible
add_action('wp', 'load_tmv2_if_needed', 20);