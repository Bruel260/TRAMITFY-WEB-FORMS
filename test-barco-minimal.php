<?php
/**
 * Test mínimo para detectar error crítico
 */

// Asegurarse de que el archivo no sea accedido directamente
defined('ABSPATH') || exit;

// Log para verificar que se carga
error_log("[TEST] test-barco-minimal.php LOADED");

/**
 * Shortcode simple de prueba
 */
function test_barco_minimal_shortcode() {
    return '<div>TEST BARCO MINIMAL - Si ves esto, no hay error crítico aquí</div>';
}
add_shortcode('test_barco_minimal', 'test_barco_minimal_shortcode');

// Nada más, archivo mínimo