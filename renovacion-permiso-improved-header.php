<?php
// Asegurarse de que el archivo no sea accedido directamente
defined('ABSPATH') || exit;

// Configuración de Stripe
define('STRIPE_MODE', 'live'); // 'test' o 'live'

define('STRIPE_TEST_PUBLIC_KEY', 'pk_test_REPLACE_WITH_YOUR_TEST_PUBLIC_KEY');
define('STRIPE_TEST_SECRET_KEY', 'sk_test_REPLACE_WITH_YOUR_TEST_SECRET_KEY');

define('STRIPE_LIVE_PUBLIC_KEY', 'pk_live_REPLACE_WITH_YOUR_LIVE_PUBLIC_KEY');
define('STRIPE_LIVE_SECRET_KEY', 'sk_live_REPLACE_WITH_YOUR_LIVE_SECRET_KEY');

// Seleccionar las claves según el modo
$stripe_public_key = (STRIPE_MODE === 'live') ? STRIPE_LIVE_PUBLIC_KEY : STRIPE_TEST_PUBLIC_KEY;
$stripe_secret_key = (STRIPE_MODE === 'live') ? STRIPE_LIVE_SECRET_KEY : STRIPE_TEST_SECRET_KEY;

// Precio del servicio (en euros)
define('SERVICE_PRICE', 65.00);

/**
 * Shortcode para el formulario de renovación de permiso de navegación
 */
function navigation_permit_renewal_form_shortcode() {
    global $stripe_public_key;
    
    // Encolar los scripts y estilos necesarios
    wp_enqueue_style('navigation-permit-renewal-form-style', get_template_directory_uri() . '/style.css', array(), filemtime(get_template_directory() . '/style.css'));
    wp_enqueue_script('stripe', 'https://js.stripe.com/v3/', array(), null, false);
    wp_enqueue_script('signature-pad', 'https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js', array(), null, false);

    // Iniciar el buffering de salida
    ob_start();
    ?>
