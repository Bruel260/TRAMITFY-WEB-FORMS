<?php
/**
 * TMV2 - ONLY AJAX FUNCTIONS (Minimal version to avoid conflicts)
 */

if (!defined('ABSPATH')) {
    exit('Acceso directo no permitido.');
}

// Solo cargar si es AJAX TMV2
if (!(defined('DOING_AJAX') && DOING_AJAX)) {
    return;
}

// Solo cargar definiciones mínimas para evitar redefinición de funciones
if (!defined('TMV2_REDSYS_MODE')) define('TMV2_REDSYS_MODE', 'test');
if (!defined('TMV2_REDSYS_URL_OK')) define('TMV2_REDSYS_URL_OK', 'https://tramitfy.es/moto-pago-realizado-con-exito/');

// Salir sin definir funciones para evitar conflictos
// Las funciones ya están definidas en el archivo principal
return;