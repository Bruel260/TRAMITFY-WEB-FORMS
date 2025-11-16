<?php
/**
 * TRAMITFY - TMV2 ABSOLUTAMENTE MÍNIMO
 * Solo lo necesario para functions.php
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit('Acceso directo no permitido.');
}

// SOLO definir funciones, NO ejecutar nada
function tmv2_render_form() {
    return '<div>TMV2 Formulario Placeholder</div>';
}

// SOLO registrar shortcode si WordPress lo permite
if (function_exists('add_shortcode')) {
    add_shortcode('transferencia_moto_v2', 'tmv2_render_form');
}