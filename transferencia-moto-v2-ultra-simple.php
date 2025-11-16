<?php
/**
 * TRAMITFY - TMV2 ULTRA SIMPLE para functions.php
 * Solo lo mínimo indispensable
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Función única para registrar TMV2
function tmv2_setup() {
    // Solo registrar en páginas específicas para evitar overhead
    if (!is_admin() && isset($_SERVER['REQUEST_URI'])) {
        $uri = $_SERVER['REQUEST_URI'];
        if (strpos($uri, 'moto') === false && strpos($uri, 'testingfy') === false) {
            return; // No cargar en otras páginas
        }
    }
    
    // Shortcode handler
    add_shortcode('transferencia_moto_v2', function($atts) {
        return '<div id="tmv2-placeholder">TMV2 placeholder</div>';
    });
    
    add_shortcode('transferencia_moto_v2_form', function($atts) {
        return '<div id="tmv2-form-placeholder">TMV2 form placeholder</div>';
    });
}

// Hook único en wp_loaded para evitar conflictos
add_action('wp_loaded', 'tmv2_setup');