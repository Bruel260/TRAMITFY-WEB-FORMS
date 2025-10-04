<?php
/*
Plugin Name: Transferencia Embarcación
Description: Formulario de transferencia de barco con Stripe, lógica de cupones y opción para usar solo el precio de compra (sin tablas CSV) cuando el usuario no encuentra su modelo.
Version: 1.8
Author: GPT-4
*/

// Asegurarse de que el archivo no sea accedido directamente
defined('ABSPATH') || exit;

// ============================================
// SISTEMA DE LOGS TRAMITFY
// ============================================

// Función de logging mejorada
if (!function_exists('tramitfy_barco_log')) {
    function tramitfy_barco_log($message, $context = 'BARCO-FORM', $level = 'INFO') {
        $log_dir = get_template_directory() . '/logs';

        if (!is_dir($log_dir)) {
            @mkdir($log_dir, 0755, true);
        }

        $log_file = $log_dir . '/tramitfy-' . date('Y-m-d') . '.log';

        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';

        if (is_array($message) || is_object($message)) {
            $message = json_encode($message, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }

        $log_entry = sprintf(
            "[%s] [%s] [%s] [IP:%s] %s\n",
            $timestamp,
            $level,
            $context,
            $ip,
            $message
        );

        @file_put_contents($log_file, $log_entry, FILE_APPEND);

        if ($level === 'ERROR' || $level === 'CRITICAL') {
            error_log("TRAMITFY [$context] $level: $message");
        }
    }
}

if (!function_exists('tramitfy_barco_debug')) {
    function tramitfy_barco_debug($message, $data = null) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $full_msg = $message;
            if ($data !== null) {
                $full_msg .= ' | ' . json_encode($data);
            }
            tramitfy_barco_log($full_msg, 'DEBUG', 'DEBUG');
        }
    }
}

tramitfy_barco_log('========== INICIO CARGA FORMULARIO BARCO ==========', 'INIT', 'INFO');

// Configuración Stripe para Transferencia Barco - FORZADO A TEST MODE
// IMPORTANTE: Usar constantes con prefijo BARCO_ para evitar conflictos con otros templates
define('BARCO_STRIPE_MODE', 'test'); // 'test' o 'live'
// CLAVES STRIPE - CONFIGURAR EN PRODUCCIÓN
// Reemplazar con las claves reales en el servidor de producción
define('BARCO_STRIPE_TEST_PUBLIC_KEY', 'pk_test_51SBOq2GXJ2PkUN8kmrKUUjCLbvY3v8sAsgr6rNtg8zHyUZjB6pFrB7Vz3Gm0l2Wm7y5xVoMap2NY8utwgdJOogNQ000qBYIX5V');
define('BARCO_STRIPE_TEST_SECRET_KEY', 'YOUR_STRIPE_TEST_KEY_HERE');
define('BARCO_STRIPE_LIVE_PUBLIC_KEY', 'pk_live_51QHhtNGXGHYLV5CXu3P7PrAFezBnDuf0JsZzb2AxjSsV0okn4y19VOMIjW0NUOLpaFdI3CCRhiC4fvNBDDbPhiW100KkF6Uo2x');
define('BARCO_STRIPE_LIVE_SECRET_KEY', 'YOUR_STRIPE_LIVE_KEY_HERE');

// Asignar claves a variables globales (igual que hoja-asiento.php - evita cache)
if (BARCO_STRIPE_MODE === 'test') {
    $barco_stripe_public_key = BARCO_STRIPE_TEST_PUBLIC_KEY;
    $barco_stripe_secret_key = BARCO_STRIPE_TEST_SECRET_KEY;
} else {
    $barco_stripe_public_key = BARCO_STRIPE_LIVE_PUBLIC_KEY;
    $barco_stripe_secret_key = BARCO_STRIPE_LIVE_SECRET_KEY;
}

/**
 * Carga datos desde archivos CSV según el tipo de vehículo
 */
function tpb_cargar_datos_csv($tipo) {
    // Siempre usa BARCO.csv (el parámetro no se usa realmente)
    $ruta_csv = get_template_directory() . '/BARCO.csv';
    $data = [];

    if (($handle = fopen($ruta_csv, 'r')) !== false) {
        // NO saltar header - el CSV no tiene encabezados
        while (($row = fgetcsv($handle, 1000, ',')) !== false) {
            if (count($row) >= 3) {
                list($fabricante, $modelo, $precio) = $row;
                $data[$fabricante][] = [
                    'modelo' => $modelo,
                    'precio' => $precio
                ];
            }
        }
        fclose($handle);
    }
    return $data;
}

/**
 * GENERA EL FORMULARIO EN EL FRONTEND
 */
function transferencia_barco_shortcode() {
    global $barco_stripe_public_key, $barco_stripe_secret_key;

    // Cargar datos de fabricantes para 'Embarcación' inicialmente
    $datos_fabricantes = tpb_cargar_datos_csv('Embarcación');

    // Obtener la ruta y versión del archivo CSS para encolarlo
    $style_path    = get_template_directory() . '/style.css';
    $style_version = file_exists($style_path) ? filemtime($style_path) : '1.0';

    // Encolar los scripts necesarios
    wp_enqueue_script('stripe', 'https://js.stripe.com/v3/', array(), null, false);
    wp_enqueue_script('signature-pad', 'https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js', array(), null, false);

    // Iniciar el buffering de salida
    ob_start();
    ?>
    <!-- Incluir el archivo CSS del tema -->
    <link rel="stylesheet" href="<?php echo get_template_directory_uri() . '/style.css?v=' . $style_version; ?>" type="text/css"/>

    <!-- Estilos personalizados para el formulario -->
    <style>
        /* Tipografía corporativa */
        @import url('https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap');
        
        /* Variables de color - Esquema formal verdoso/azul-gris */
        :root {
            /* Colores principales - Ajustados para coincidir con el formulario */
            --primary: 1, 109, 134; /* Color #016d86 - Verde/azul corporativo principal */
            --primary-dark: 0, 86, 106;
            --primary-light: 0, 125, 156;
            --primary-bg: 236, 247, 255;
            
            --secondary: 0, 123, 255; /* Azul #007bff - Color secundario */
            --secondary-dark: 0, 105, 217;
            --secondary-light: 50, 145, 255;
            --secondary-bg: 235, 245, 253;
            
            --neutral: 70, 80, 95; /* Azul grisáceo */
            --neutral-dark: 44, 62, 80;
            --neutral-medium: 127, 140, 141;
            --neutral-light: 189, 195, 199;
            
            /* Neutrales */
            --neutral-50: 248, 249, 250;
            --neutral-100: 241, 243, 244;
            --neutral-200: 233, 236, 239;
            --neutral-300: 222, 226, 230;
            --neutral-400: 206, 212, 218;
            --neutral-500: 173, 181, 189;
            --neutral-600: 108, 117, 125;
            --neutral-700: 73, 80, 87;
            --neutral-800: 52, 58, 64;
            --neutral-900: 33, 37, 41;
            
            /* Estados */
            --success: 40, 167, 69;
            --warning: 243, 156, 18;
            --error: 231, 76, 60;
            --info: 0, 123, 255;
            
            /* Espaciado y dimensiones */
            --spacing-xs: 0.25rem;
            --spacing-sm: 0.5rem;
            --spacing-md: 1rem;
            --spacing-lg: 1.5rem;
            --spacing-xl: 2rem;
            --spacing-xxl: 2.5rem;
            
            /* Bordes redondeados */
            --radius-sm: 0.25rem;
            --radius-md: 0.375rem;
            --radius-lg: 0.5rem;
            --radius-xl: 0.75rem;
            
            /* Sombras */
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.1);
            --shadow-md: 0 4px 6px rgba(0,0,0,0.1), 0 1px 3px rgba(0,0,0,0.08);
            --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05);
            
            /* Transiciones */
            --transition-fast: 150ms ease-in-out;
            --transition-normal: 250ms ease-in-out;
            
            /* Z-índices */
            --z-10: 10;
            --z-20: 20;
            --z-30: 30;
            --z-40: 40;
            --z-50: 50;
        }
        
        /* Reset y configuración base */
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        /* LAYOUTS COMPACTOS PARA FORMULARIO */
        .form-compact-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 18px;
        }

        .form-compact-row .form-group {
            margin-bottom: 0;
        }

        .form-compact-triple {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 12px;
            margin-bottom: 18px;
        }

        .form-compact-triple .form-group {
            margin-bottom: 0;
        }

        @media (max-width: 768px) {
            .form-compact-triple {
                grid-template-columns: 1fr;
                gap: 15px;
            }
        }

        /* Estilos para la pantalla de marketing inicial */
        .marketing-container {
            display: flex;
            gap: var(--spacing-xl);
            padding: var(--spacing-xl) var(--spacing-lg);
        }
        
        .marketing-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .marketing-image {
            flex: 1;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            perspective: 1000px;
        }
        
        .marketing-badge {
            display: inline-block;
            background-color: rgba(var(--primary), 0.1);
            color: rgb(var(--primary));
            padding: var(--spacing-xs) var(--spacing-md);
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: var(--spacing-md);
        }
        
        .marketing-title {
            font-size: 2.2rem;
            font-weight: 700;
            color: rgb(var(--neutral-800));
            line-height: 1.2;
            margin-bottom: var(--spacing-md);
        }
        
        .marketing-description {
            font-size: 1.2rem;
            color: rgb(var(--neutral-600));
            margin-bottom: var(--spacing-xl);
            line-height: 1.5;
        }
        
        .marketing-features {
            display: flex;
            gap: var(--spacing-lg);
            margin-bottom: var(--spacing-xl);
        }
        
        .feature-item {
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
        }
        
        .feature-icon {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background-color: rgba(var(--primary), 0.1);
            color: rgb(var(--primary));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
        }
        
        .feature-text {
            font-weight: 500;
            color: rgb(var(--neutral-700));
        }
        
        .marketing-button {
            align-self: flex-start;
            /* Los estilos adicionales están definidos en la sección mejorada más abajo */
        }
        
        /* Estilos para la animación 3D del formulario */
        .form-3d-container {
            width: 250px;
            height: 300px;
            position: relative;
            transform-style: preserve-3d;
            animation: form-float 5s ease-in-out infinite alternate;
        }
        
        @keyframes form-float {
            0% {
                transform: rotateX(5deg) rotateY(-10deg) translateZ(0);
            }
            100% {
                transform: rotateX(-5deg) rotateY(10deg) translateZ(20px);
            }
        }
        
        .form-3d-element {
            width: 100%;
            height: 100%;
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2), 0 5px 15px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            transform-style: preserve-3d;
            transform: translateZ(20px);
        }
        
        .form-3d-header {
            padding: 8px 12px;
            background: rgb(var(--primary));
            color: white;
            transform: translateZ(10px);
        }
        
        .form-3d-title {
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: var(--spacing-xs);
        }
        
        .form-3d-steps {
            display: flex;
            gap: 6px;
        }
        
        .form-3d-step {
            width: 30px;
            height: 4px;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 3px;
        }
        
        .form-3d-step.active {
            background: white;
        }
        
        .form-3d-content {
            padding: var(--spacing-md);
            transform: translateZ(5px);
        }
        
        .form-3d-field {
            margin-bottom: var(--spacing-xs);
        }
        
        .form-3d-field label {
            display: block;
            font-size: 0.7rem;
            color: rgb(var(--neutral-600));
            margin-bottom: 2px;
        }
        
        .form-3d-input {
            height: 28px;
            width: 100%;
            background: rgb(var(--neutral-100));
            border-radius: var(--radius-sm);
            border: 1px solid rgb(var(--neutral-300));
            position: relative;
            overflow: hidden;
        }
        
        .form-3d-input.active::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            height: 100%;
            width: 70%;
            background: rgba(var(--secondary), 0.1);
            animation: typing 2s ease-in-out infinite;
        }
        
        @keyframes typing {
            0%, 100% { width: 20%; }
            50% { width: 70%; }
        }
        
        .form-3d-button {
            background: rgb(var(--primary));
            color: white;
            font-weight: 500;
            font-size: 0.8rem;
            text-align: center;
            padding: 6px 0;
            border-radius: var(--radius-sm);
            margin-top: var(--spacing-md);
            transform: translateZ(10px);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
            animation: pulse 2s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { 
                transform: translateZ(10px) scale(1);
                box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
            }
            50% { 
                transform: translateZ(10px) scale(1.02);
                box-shadow: 0 3px 8px rgba(0, 0, 0, 0.15);
            }
        }
        
        /* Estilos para la pantalla de requisitos */
        .requirements-screen {
            padding: var(--spacing-xl) var(--spacing-lg);
            text-align: center;
        }
        
        .requirements-header {
            margin-bottom: var(--spacing-xl);
        }
        
        .requirements-heading {
            color: rgb(var(--neutral-800));
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: var(--spacing-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: var(--spacing-sm);
        }
        
        /* Estilos para la cuadrícula de vehículo */
        .vehicle-grid {
            margin: 25px 0;
        }
        
        .vehicle-row {
            display: flex;
            gap: 20px;
            width: 100%;
            margin-bottom: 20px;
        }
        
        .vehicle-field {
            flex: 1;
        }
        
        .vehicle-field label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .vehicle-field select,
        .vehicle-field input {
            width: 100%;
        }
        
        /* Ajustes para "No encuentro mi modelo" */
        #no-encuentro-wrapper {
            margin-bottom: 25px;
        }
        
        /* Ajustes responsivos */
        @media (max-width: 768px) {
            .vehicle-row {
                flex-direction: column;
                gap: 15px;
                margin-bottom: 15px;
            }
        }
        
        .requirements-heading i {
            color: rgb(var(--primary));
        }
        
        .requirements-subheading {
            color: rgb(var(--neutral-600));
            font-size: 1.1rem;
        }
        
        .requirements-container {
            background-color: rgba(var(--primary), 0.05);
            border: 1px solid rgba(var(--primary), 0.2);
            border-radius: var(--radius-lg);
            padding: var(--spacing-lg);
            margin-bottom: var(--spacing-xl);
        }
        
        .requirements-list {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            flex-direction: column;
            gap: var(--spacing-md);
        }
        
        .requirements-list li {
            padding: var(--spacing-md);
            background-color: white;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
            font-weight: 500;
            color: rgb(var(--neutral-700));
            box-shadow: var(--shadow-sm);
        }
        
        .requirements-list li i {
            color: rgb(var(--secondary));
            font-size: 1.2rem;
            width: 24px;
            text-align: center;
        }
        
        .welcome-steps {
            margin-bottom: var(--spacing-xl);
        }
        
        .steps-title {
            color: rgb(var(--secondary-dark));
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: var(--spacing-md);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: var(--spacing-sm);
        }
        
        .steps-title i {
            color: rgb(var(--secondary));
        }
        
        .steps-container {
            display: flex;
            flex-direction: column;
            gap: var(--spacing-sm);
        }
        
        .step-item {
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
            background-color: white;
            border-radius: var(--radius-md);
            padding: var(--spacing-md);
            box-shadow: var(--shadow-sm);
            border: 1px solid rgb(var(--neutral-200));
        }
        
        .step-number {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background-color: rgb(var(--secondary));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 1rem;
            flex-shrink: 0;
        }
        
        .step-info {
            text-align: left;
        }
        
        .step-name {
            font-weight: 600;
            color: rgb(var(--neutral-700));
        }
        
        .step-desc {
            font-size: 0.9rem;
            color: rgb(var(--neutral-500));
        }
        
        .btn-lg {
            height: 60px;
            font-size: 1.25rem;
            padding: 0 var(--spacing-xl);
            font-weight: 600;
        }
        
        .start-button {
            margin-top: var(--spacing-lg);
            min-width: 240px;
        }
        
        /* Estilos mejorados para el botón de marketing */
        .marketing-button {
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 20px rgba(var(--primary), 0.3);
            transition: all 0.3s ease;
            transform-origin: center;
            letter-spacing: 0.5px;
            border-radius: var(--radius-lg);
            animation: pulse-attention 2s infinite;
        }
        
        .marketing-button:hover {
            transform: translateY(-3px) scale(1.03);
            box-shadow: 0 12px 25px rgba(var(--primary), 0.4);
            animation: none;
        }
        
        .marketing-button:active {
            transform: translateY(-1px) scale(0.98);
            box-shadow: 0 6px 15px rgba(var(--primary), 0.3);
        }
        
        /* Efecto de brillo al pasar el cursor */
        .marketing-button::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(to right, rgba(255,255,255,0) 0%, rgba(255,255,255,0.3) 50%, rgba(255,255,255,0) 100%);
            transform: rotate(45deg);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .marketing-button:hover::before {
            opacity: 1;
            animation: shine 1s forwards;
        }
        
        /* Animación de pulso para llamar la atención */
        @keyframes pulse-attention {
            0% {
                box-shadow: 0 0 0 0 rgba(var(--primary), 0.7);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(var(--primary), 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(var(--primary), 0);
            }
        }
        
        /* Animación de brillo */
        @keyframes shine {
            0% {
                left: -50%;
                opacity: 0;
            }
            50% {
                opacity: 0.5;
            }
            100% {
                left: 150%;
                opacity: 0;
            }
        }
        
        /* Estilos generales del formulario */
        #hoja-asiento-form {
            max-width: 750px;
            width: 100%;
            margin: var(--spacing-xl) auto;
            font-family: 'Roboto', sans-serif;
            background: white;
            border-radius: var(--radius-lg);
            border: 1px solid rgb(var(--neutral-300));
            box-shadow: var(--shadow-md);
            position: relative;
            color: rgb(var(--neutral-800));
            line-height: 1.5;
            font-size: 15px;
            overflow: hidden;
        }
        
        /* Contenedor principal */
        .form-container {
            padding: var(--spacing-xl);
            position: relative;
        }
        
        /* Tabs de contenido */
        .tab-content {
            display: none;
            margin-bottom: var(--spacing-xl);
            animation: fadeIn 0.5s ease forwards;
            background-color: white;
            border: 1px solid rgb(var(--neutral-300));
            border-radius: var(--radius-md);
            padding: var(--spacing-xl);
            min-height: 300px;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .tab-content.active {
            display: block;
        }
        
        /* Botones */
        .btn {
            padding: 0 var(--spacing-lg);
            border-radius: var(--radius-md);
            font-weight: 500;
            font-size: 0.95rem;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: var(--spacing-sm);
            transition: all var(--transition-fast);
            height: 42px;
        }
        
        .btn:hover {
            transform: translateY(-1px);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }
        
        .btn:disabled:hover {
            transform: none;
        }
        
        .btn-primary {
            background: rgb(var(--primary));
            color: white;
        }
        
        .btn-primary:hover {
            background: rgb(var(--primary-dark));
        }
        
        /* Estilos responsivos */
        @media (max-width: 768px) {
            .form-container {
                padding: var(--spacing-md);
            }
            
            .marketing-container {
                flex-direction: column;
                padding: var(--spacing-md);
            }
            
            .form-3d-container {
                width: 200px;
                height: 250px;
                margin-top: var(--spacing-md);
            }
            
            .marketing-features {
                flex-direction: column;
                gap: var(--spacing-sm);
            }
        }

        /* Estilos generales mejorados para el formulario */
        #transferencia-form {
            max-width: 100%;
            width: 100%;
            margin: 20px auto;
            padding: 0;
            border: none;
            border-radius: 16px;
            font-family: 'Roboto', 'Helvetica Neue', Helvetica, Arial, sans-serif;
            background-color: transparent;
            box-shadow: none;
            transition: none;
        }
        
        #transferencia-form h2 {
            margin-top: 0;
            margin-bottom: 20px;
            color: rgb(var(--primary));
            font-size: 26px;
            font-weight: 600;
            border-bottom: 2px solid rgba(var(--primary), 0.1);
            padding-bottom: 10px;
        }

        #transferencia-form h3 {
            color: rgb(var(--primary-dark));
            font-size: 18px;
            margin-top: 24px;
            margin-bottom: 12px;
            font-weight: 500;
        }

        #transferencia-form label {
            font-weight: 500;
            display: block;
            margin-top: 16px;
            margin-bottom: 6px;
            color: #444444;
            font-size: 14px;
        }
        
        /* Mejoras para los acordeones en página de documentos */
        .accordion-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.5s ease;
            background-color: white;
            padding: 0 20px;
        }
        
        .accordion-content.active {
            padding: 25px 20px;
        }
        
        .accordion-toggle {
            transition: transform 0.3s ease;
        }
        
        .accordion-header.active .accordion-toggle {
            transform: rotate(180deg);
        }
        
        /* Estilos para convertir price-cards en acordeones */
        .price-card {
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }
        
        .price-card .price-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background-color 0.3s ease;
            position: relative;
            z-index: 2;
        }
        
        .price-card .price-card-body {
            overflow: hidden;
            transition: max-height 0.5s ease;
            position: relative;
            z-index: 1;
        }
        
        .price-card .accordion-toggle {
            transition: transform 0.3s ease;
            margin-left: 10px;
        }
        
        /* Estilos para formularios acordeón */
        .invalid {
            border-color: #e74c3c !important;
            box-shadow: 0 0 0 3px rgba(231, 76, 60, 0.3) !important;
            animation: shake 0.3s ease-in-out;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            50% { transform: translateX(5px); }
            75% { transform: translateX(-5px); }
        }
        
        #signature-pad.invalid {
            border: 2px solid #e74c3c !important;
        }
        
        .upload-wrapper.invalid {
            background-color: rgba(231, 76, 60, 0.05);
            border-radius: 4px;
        }
        
        #transferencia-form select,
        #transferencia-form input[type="text"],
        #transferencia-form input[type="date"],
        #transferencia-form input[type="number"],
        #transferencia-form input[type="tel"],
        #transferencia-form input[type="email"] {
            width: 100%;
            padding: 12px;
            margin-top: 6px;
            border-radius: 6px;
            border: 1px solid #d0d0d0;
            font-size: 15px;
            background-color: #f9f9f9;
            transition: all 0.2s ease;
        }
        
        #transferencia-form select:focus,
        #transferencia-form input[type="text"]:focus,
        #transferencia-form input[type="date"]:focus,
        #transferencia-form input[type="number"]:focus,
        #transferencia-form input[type="tel"]:focus,
        #transferencia-form input[type="email"]:focus {
            border-color: rgb(var(--primary));
            box-shadow: 0 0 0 3px rgba(var(--primary), 0.1);
            outline: none;
            background-color: #ffffff;
        }
        
        #transferencia-form .button {
            background-color: rgb(var(--primary));
            color: #ffffff;
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            transition: all 0.3s ease;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        
        #transferencia-form .button:hover {
            background-color: rgb(var(--primary-dark));
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }
        
        #transferencia-form .button:active {
            transform: translateY(0);
            box-shadow: 0 2px 3px rgba(0, 0, 0, 0.1);
        }
        
        /* Navegación discreta y profesional */
        #form-navigation {
            position: sticky;
            top: 0;
            z-index: 100;
            background: rgba(8, 145, 178, 0.08);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(14, 116, 144, 0.15);
            margin: 0;
            padding: 0;
            border-radius: 0 16px 0 0;
        }

        .nav-progress-bar {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 2px;
            background-color: rgba(14, 116, 144, 0.3);
            z-index: 1;
        }

        .nav-progress-indicator {
            position: absolute;
            top: 0;
            left: 0;
            height: 100%;
            width: 0%;
            background: #0891b2;
            transition: width 0.4s ease;
        }

        .nav-items-container {
            display: flex;
            position: relative;
            z-index: 2;
            max-width: 900px;
            margin: 0 auto;
            padding: 0;
        }

        .nav-item {
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            color: #0891b2;
            font-weight: 500;
            font-size: 14px;
            position: relative;
            transition: all 0.2s ease;
            flex: 1;
            padding: 16px 20px;
            border-bottom: 3px solid transparent;
        }

        .nav-item-circle {
            display: none;
        }

        .nav-item-icon {
            display: none;
        }

        .nav-item-number {
            display: none;
        }

        .nav-item-text {
            font-size: 14px;
            font-weight: 500;
        }

        /* Estado activo (página actual) */
        .nav-item.active {
            color: #0e7490;
            font-weight: 600;
            border-bottom-color: #0e7490;
            background: rgba(8, 145, 178, 0.1);
        }

        /* Estado completado (páginas anteriores) */
        .nav-item.completed {
            color: #016d86;
        }

        /* Hover */
        .nav-item:hover {
            color: #0e7490;
            background: rgba(8, 145, 178, 0.08);
        }

        /* Bloqueado (no accesible aún) */
        .nav-item-blocked {
            animation: shake 0.3s;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        
        /* Estilos responsivos */
        @media (max-width: 768px) {
            .nav-progress-bar {
                left: 8%;
                right: 8%;
            }

            .nav-items-container {
                width: 100%;
                gap: 4px;
            }

            .nav-item {
                flex-direction: column;
                padding: 10px 8px;
                gap: 6px;
            }

            .nav-item-circle {
                width: 46px;
                height: 46px;
            }

            .nav-item-icon {
                font-size: 20px;
            }

            .nav-item-number {
                width: 20px;
                height: 20px;
                font-size: 11px;
            }

            .nav-item-text {
                font-size: 11px;
                text-align: center;
            }
        }

        @media (max-width: 480px) {
            .nav-item-text {
                display: none;
            }

            .nav-item {
                padding: 8px 4px;
            }
        }
        
        @media (max-width: 576px) {
            .nav-item-circle {
                width: 40px;
                height: 40px;
            }
            
            .nav-item-icon {
                font-size: 16px;
            }
            
            .nav-item-number {
                font-size: 14px;
            }
            
            .nav-item-text {
                display: none;
            }
        }
        
        /* Animaciones de transición entre páginas */
        @keyframes fadeInRight {
            from {
                opacity: 0;
                transform: translateX(30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        @keyframes fadeOutLeft {
            from {
                opacity: 1;
                transform: translateX(0);
            }
            to {
                opacity: 0;
                transform: translateX(-30px);
            }
        }
        
        @keyframes fadeInLeft {
            from {
                opacity: 0;
                transform: translateX(-30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        @keyframes fadeOutRight {
            from {
                opacity: 1;
                transform: translateX(0);
            }
            to {
                opacity: 0;
                transform: translateX(30px);
            }
        }
        
        .page-enter {
            animation: fadeInRight 0.5s forwards;
        }
        
        .page-exit {
            animation: fadeOutLeft 0.5s forwards;
        }
        
        .page-enter-back {
            animation: fadeInLeft 0.5s forwards;
        }
        
        .page-exit-back {
            animation: fadeOutRight 0.5s forwards;
        }

        /* Ocultar modal de firma en desktop - solo para móvil */
        #signature-modal-mobile {
            display: none !important;
            visibility: hidden !important;
            opacity: 0 !important;
            pointer-events: none !important;
        }

        .button-container {
            display: none;
            justify-content: space-between;
            align-items: center;
            margin-top: 36px;
            padding-top: 24px;
            border-top: 1px solid #e5e7eb;
            gap: 16px;
            width: 100%;
            position: relative; /* Asegurar posicionamiento en flujo normal */
            z-index: 10; /* Asegurar que esté por encima de otros elementos */
        }

        .button-container .button {
            padding: 14px 32px;
            font-size: 15px;
            font-weight: 600;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            border: none;
            min-width: 140px;
        }

        #prevButton {
            background: #f3f4f6;
            color: #374151;
        }

        #prevButton:hover {
            background: #e5e7eb;
        }

        #nextButton {
            background: #016d86;
            color: white;
        }

        #nextButton:hover {
            background: #015266;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(1, 109, 134, 0.2);
        }
        
        /* Estilo mejorado para la sección de precio */
        .price-details {
            margin-top: 20px;
            font-size: 15px;
            background-color: #fbfbfb;
            padding: 20px;
            border-radius: 10px;
            border: 1px solid #eaeaea;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
            transition: transform 0.2s ease;
        }
        
        .price-details:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .price-details p {
            font-size: 18px;
            font-weight: bold;
            margin: 0 0 15px 0;
            color: #333333;
        }
        
        .price-details ul {
            list-style-type: none;
            padding: 0;
            margin: 15px 0;
        }
        
        .price-details ul li {
            margin-bottom: 12px;
            color: #555555;
            display: flex;
            justify-content: space-between;
            padding-bottom: 8px;
            border-bottom: 1px dashed rgba(0,0,0,0.06);
        }
        
        .price-details ul li:last-child {
            border-bottom: none;
        }
        
        .price-calculation {
            font-size: 20px;
            font-weight: bold;
            margin-top: 25px;
            padding-top: 15px;
            color: #333333;
            border-top: 2px solid rgba(var(--primary), 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        #info-link {
            color: rgb(var(--primary));
            text-decoration: none;
            margin-left: 8px;
            font-size: 0.8em;
            padding: 3px 8px;
            border-radius: 12px;
            background: rgba(var(--primary), 0.1);
            transition: all 0.2s ease;
        }
        
        #info-link:hover {
            background: rgba(var(--primary), 0.2);
        }
        
        /* Estilo mejorado para el apartado de popup info */
        #info-popup {
            display: none;
            background-color: #ffffff;
            border: 1px solid rgb(var(--primary));
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
            margin-top: 25px;
            animation: fadeIn 0.5s;
        }
        
        #info-popup h2 {
            text-align: center;
            color: rgb(var(--primary));
            margin-bottom: 20px;
            font-size: 22px;
        }
        
        /* Estilo mejorado para las opciones de radio */
        .radio-group {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin: 25px 0;
            justify-content: center;
        }
        
        .radio-group label {
            flex: 1 1 200px;
            max-width: 250px;
            min-width: 180px;
            height: auto;
            padding: 20px 15px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border: 2px solid rgba(var(--primary), 0.3);
            border-radius: 12px;
            margin: 5px;
            position: relative;
            cursor: pointer;
            transition: all 0.3s ease;
            background-color: #ffffff;
            text-align: center;
            box-shadow: 0 3px 8px rgba(0,0,0,0.05);
        }
        
        .radio-group label:hover {
            border-color: rgb(var(--primary));
            transform: translateY(-3px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.1);
        }
        
        .radio-group input[type="radio"] {
            position: absolute;
            top: 15px;
            left: 15px;
            transform: scale(1.2);
        }
        
        .radio-group svg {
            margin-left: 0;
            margin-bottom: 15px;
            width: 60px;
            height: 60px;
            transition: transform 0.3s ease;
        }
        
        .radio-group label:hover svg {
            transform: scale(1.1);
        }
        
        .radio-group label.selected {
            background-color: rgba(var(--primary), 0.1);
            border-color: rgb(var(--primary));
            box-shadow: 0 0 0 3px rgba(var(--primary), 0.2);
        }
        
        /* Mejoras en opciones adicionales */
        .additional-options {
            margin-top: 30px;
            background-color: white;
            padding: 25px;
            border-radius: 12px;
            border: 1px solid rgba(var(--primary), 0.15);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.08);
            position: relative;
            overflow: hidden;
        }
        
        .additional-options:before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, rgb(var(--primary)), rgb(var(--primary-light)));
        }
        
        .additional-options-title {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(var(--neutral-300), 0.5);
            font-weight: 600;
            color: rgb(var(--primary));
            font-size: 18px;
        }
        
        .additional-options-title i {
            transition: transform 0.3s ease;
        }
        
        .additional-options-title.expanded i {
            transform: rotate(180deg);
        }
        
        .additional-options-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.5s ease;
        }
        
        .additional-options-content.expanded {
            max-height: 1000px;
        }
        
        .additional-options label {
            display: flex;
            align-items: center;
            margin: 15px 0;
            cursor: pointer;
            padding: 12px;
            border-radius: 8px;
            transition: all 0.2s ease;
            border: 1px solid rgba(var(--neutral-300), 0.5);
            background-color: rgba(var(--neutral-50), 0.5);
        }
        
        .additional-options label:hover {
            background-color: rgba(var(--primary), 0.05);
            border-color: rgba(var(--primary), 0.3);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
        }
        
        .additional-options label.selected {
            background-color: rgba(var(--primary), 0.1);
            border-color: rgba(var(--primary), 0.4);
        }
        
        .additional-options label input[type="checkbox"] {
            margin-right: 15px;
            transform: scale(1.3);
            accent-color: rgb(var(--primary));
        }
        
        .additional-options span {
            margin-left: auto;
            color: rgb(var(--neutral-800));
            font-weight: 600;
            background-color: rgba(var(--primary), 0.1);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 14px;
        }
        
        .additional-input {
            margin-left: 30px;
            margin-bottom: 15px;
            animation: fadeIn 0.3s;
        }
        
        /* No encuentro mi modelo - mejorado */
        #no-encuentro-wrapper {
            margin-top: 25px;
            background-color: rgba(var(--primary), 0.05);
            border: 1px solid rgba(var(--primary), 0.2);
            border-radius: 12px;
            padding: 20px;
            box-shadow: var(--shadow-sm);
            transition: all 0.3s ease;
        }
        
        #no-encuentro-wrapper:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        #no-encuentro-wrapper label {
            display: flex;
            align-items: center;
            cursor: pointer;
            margin-top: 0;
        }
        
        #no_encuentro_checkbox {
            margin-right: 12px;
            transform: scale(1.3);
        }
        
        #no-encuentro-wrapper p {
            margin: 12px 0 0 32px;
            font-style: italic;
            color: rgb(var(--primary-dark));
            line-height: 1.5;
        }
        
        #manual-fields {
            margin-top: 15px;
            padding: 15px;
            background: #fff;
            border: 1px solid rgba(var(--primary), 0.15);
            border-radius: 10px;
            animation: fadeIn 0.5s;
        }
                
        /* Mejoras para la sección de documentos */
        .upload-section {
            margin-top: 30px;
        }
        
        .upload-item {
            margin-bottom: 20px;
            padding: 15px;
            border: 1px solid #eaeaea;
            border-radius: 12px;
            background-color: #f9f9f9;
            transition: all 0.2s ease;
        }
        
        .upload-item:hover {
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
            border-color: #d0d0d0;
        }
        
        .upload-item label {
            margin-top: 0;
            color: #444;
            font-weight: 600;
        }
        
        .upload-item input[type="file"] {
            background-color: white;
            padding: 12px;
            border: 1px dashed #ccc;
            border-radius: 8px;
            margin: 10px 0;
            width: 100%;
            transition: all 0.2s ease;
        }
        
        .upload-item input[type="file"]:hover {
            border-color: rgb(var(--primary));
            background-color: rgba(var(--primary), 0.02);
        }
        
        .upload-item .view-example {
            display: inline-block;
            background-color: transparent;
            color: rgb(var(--primary));
            text-decoration: underline;
            cursor: pointer;
            padding: 5px 12px;
            border-radius: 20px;
            transition: all 0.2s ease;
        }
        
        .upload-item .view-example:hover {
            background-color: rgba(var(--primary), 0.1);
            text-decoration: none;
        }
        
        /* Mejora para autorización */
        #authorization-document {
            background-color: #ffffff;
            padding: 35px;
            border-radius: 8px;
            border: 2px solid #016d86;
            margin: 20px 0;
            box-shadow: 0 4px 12px rgba(1,109,134,0.1);
            transition: all 0.3s ease;
            position: relative;
        }

        #authorization-document::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #016d86 0%, #01546a 100%);
            border-radius: 8px 8px 0 0;
        }

        #authorization-document:hover {
            box-shadow: 0 6px 20px rgba(1,109,134,0.15);
        }

        .auth-doc-header {
            text-align: center;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }

        .auth-doc-header img {
            max-width: 180px;
            margin-bottom: 15px;
        }

        .auth-doc-title {
            font-size: 18px;
            font-weight: 700;
            color: #016d86;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 10px 0;
        }

        .auth-doc-date {
            font-size: 13px;
            color: #666;
            margin-top: 8px;
        }

        #authorization-document p {
            margin-bottom: 18px;
            line-height: 1.8;
            color: #333;
            font-size: 15px;
        }

        #authorization-document strong {
            color: #016d86;
            font-weight: 600;
        }

        .auth-doc-section {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 6px;
            margin: 20px 0;
            border-left: 4px solid #016d86;
        }

        .auth-doc-section-title {
            font-size: 16px;
            font-weight: 600;
            color: #016d86;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        #authorization-document ul {
            padding-left: 0;
            margin-bottom: 15px;
            list-style: none;
        }

        #authorization-document li {
            margin-bottom: 10px;
            padding-left: 28px;
            position: relative;
            line-height: 1.6;
        }

        #authorization-document li::before {
            content: '\2022';
            color: #016d86;
            font-weight: bold;
            font-size: 20px;
            position: absolute;
            left: 8px;
            top: -2px;
        }

        .auth-doc-footer {
            margin-top: 25px;
            padding-top: 20px;
            border-top: 2px solid #f0f0f0;
            text-align: center;
            font-size: 13px;
            color: #666;
        }
        
        /* Mejora para firma */
        #signature-container {
            margin-top: 30px;
            text-align: center;
            width: 100%;
            position: relative;
        }

        .signature-instructions {
            background-color: #e3f2fd;
            border-left: 4px solid #016d86;
            padding: 15px 20px;
            margin-bottom: 20px;
            border-radius: 4px;
            text-align: left;
        }

        .signature-instructions h4 {
            color: #016d86;
            font-size: 15px;
            font-weight: 600;
            margin: 0 0 8px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .signature-instructions p {
            margin: 0;
            font-size: 13px;
            color: #555;
            line-height: 1.5;
        }

        .signature-pad-wrapper {
            position: relative;
            display: inline-block;
            margin: 0 auto;
        }

        .signature-label {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: #ccc;
            font-size: 18px;
            pointer-events: none;
            font-weight: 300;
            letter-spacing: 1px;
            z-index: 0;
        }

        .signature-label.hidden {
            display: none;
        }
        
        /* Estilos para cuadrícula de inputs */
        .inputs-grid {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .inputs-row {
            display: flex;
            gap: 20px;
            width: 100%;
        }
        
        .inputs-row .input-group {
            flex: 1;
            margin-bottom: 0;
        }
        
        /* Ajustes responsivos para inputs */
        @media (max-width: 768px) {
            .inputs-row {
                flex-direction: column;
                gap: 15px;
            }
            
            .inputs-row .input-group {
                margin-bottom: 5px;
            }
        }
        
        /* Estilos para la cuadrícula de documentos - VERSION COMPACTA */
        .upload-grid {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin: 12px 0;
        }
        
        .upload-row {
            display: flex;
            gap: 12px;
            width: 100%;
        }
        
        .upload-row .upload-item {
            flex: 1 1 0;
            min-width: 0;
        }

        /* Botones upload responsivos - Desktop vs Mobile */
        .upload-button-responsive .desktop-text {
            display: inline;
        }
        
        .upload-button-responsive .mobile-text {
            display: none;
        }
        
        /* Estilos compactos para upload items */
        .upload-item {
            padding: 12px !important;
            min-height: auto !important;
            background: white !important;
            border: 1px solid #e5e7eb !important;
            border-radius: 8px !important;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1) !important;
        }

        /* Asegurar posicionamiento correcto de botones en página documentos - SOLO CUANDO NO ESTÁN EN CONTENEDOR ESPECÍFICO */
        #page-documentos + .button-container:not(#documentos-buttons-container .button-container),
        #page-documentos ~ .button-container:not(#documentos-buttons-container .button-container) {
            margin-top: 36px !important;
            padding-top: 24px !important;
            position: relative !important;
            z-index: 10 !important;
        }

        /* Botones CASI TOCANDO el checkbox en página documentos - PRIORITARIO */
        #page-documentos #documentos-buttons-container .button-container {
            margin-top: 2px !important;
            padding-top: 0px !important;
            border-top: none !important;
        }
        
        .upload-wrapper {
            margin-bottom: 8px;
        }
        
        .upload-button {
            padding: 8px 14px !important;
            font-size: 13px !important;
            min-height: 36px !important;
        }
        
        .file-count {
            font-size: 11px !important;
            margin-top: 6px !important;
            margin-bottom: 6px !important;
            color: #6b7280;
        }
        
        .upload-row .upload-item:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }
        
        /* Ajustes responsivos */
        @media (max-width: 768px) {
            .upload-row {
                flex-direction: column;
                gap: 15px;
            }
            
            .upload-row .upload-item {
                min-height: auto;
                margin-bottom: 10px;
            }
        }
        
        #signature-pad {
            border: 2px solid #016d86;
            width: 100%;
            max-width: 600px;
            height: 200px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(1,109,134,0.15);
            margin: 0 auto;
            background-color: #ffffff;
            cursor: crosshair;
            position: relative;
            z-index: 1;
            transition: all 0.3s ease;
        }

        #signature-pad:hover {
            border-color: #01546a;
            box-shadow: 0 6px 16px rgba(1,109,134,0.2);
        }

        #signature-pad.signed {
            border-color: #2e7d32;
        }

        #clear-signature {
            margin-top: 20px;
            background-color: #f5f5f5;
            color: #666;
            border: 2px solid #ddd;
            padding: 10px 24px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        #clear-signature:hover {
            background-color: #fff;
            border-color: #016d86;
            color: #016d86;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        #clear-signature::before {
            content: '\2715';
            font-size: 16px;
        }
        
        /* Mejora para cupones */
        #coupon_code {
            width: 70%;
            display: inline-block;
            margin-right: 10px;
        }
        
        #coupon-message {
            margin-top: 10px;
            padding: 8px 12px;
            border-radius: 8px;
            display: inline-block;
            animation: fadeIn 0.3s;
        }
        
        .coupon-valid {
            background-color: #d4edda !important;
            border-color: #c3e6cb !important;
            border: 1px solid;
        }
        
        .coupon-error {
            background-color: #f8d7da !important;
            border-color: #f5c6cb !important;
            border: 1px solid;
        }
        
        .coupon-loading {
            background-color: #fff3cd !important;
            border-color: #ffeeba !important;
            border: 1px solid;
        }
        
        /* Animación fadeIn mejorada */
        @keyframes fadeIn {
            from { 
                opacity: 0; 
                transform: translateY(10px); 
            }
            to { 
                opacity: 1; 
                transform: translateY(0); 
            }
        }
        
        /* Estilos mejorados para loading overlay con pasos */
        #loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255,255,255,0.97);
            display: none;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            backdrop-filter: blur(4px);
        }

        .loading-container {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            text-align: center;
            width: 90%;
            max-width: 500px;
        }

        .loading-spinner {
            border: 6px solid #f3f3f3;
            border-top: 6px solid rgb(var(--primary));
            border-radius: 50%;
            width: 60px;
            height: 60px;
            margin: 0 auto 20px;
            animation: spin 1.5s linear infinite;
        }

        .loading-title {
            font-size: 22px;
            font-weight: 600;
            color: rgb(var(--primary));
            margin-bottom: 10px;
        }

        .loading-message {
            color: rgb(var(--neutral-600));
            margin-bottom: 30px;
        }

        .loading-steps {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
            position: relative;
            padding: 0 20px;
        }

        .loading-steps:before {
            content: '';
            position: absolute;
            top: 25px;
            left: 40px;
            right: 40px;
            height: 3px;
            background-color: rgba(var(--neutral-300), 0.5);
            z-index: 1;
        }

        .loading-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 2;
            padding: 0 10px;
            width: 33.33%;
        }

        .loading-step-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: white;
            border: 2px solid rgba(var(--neutral-300), 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 10px;
            color: rgb(var(--neutral-500));
            font-size: 20px;
            transition: all 0.4s ease;
            position: relative;
        }

        .loading-step-text {
            font-size: 14px;
            color: rgb(var(--neutral-600));
            font-weight: 500;
            text-align: center;
            transition: all 0.3s ease;
        }

        /* Estilo para pasos activos */
        .loading-step.active .loading-step-icon {
            border-color: rgb(var(--primary));
            background-color: rgba(var(--primary), 0.1);
            color: rgb(var(--primary));
            transform: scale(1.1);
            box-shadow: 0 0 0 5px rgba(var(--primary), 0.1);
        }

        .loading-step.active .loading-step-text {
            color: rgb(var(--primary));
            font-weight: 600;
        }

        /* Estilo para pasos completados */
        .loading-step.completed .loading-step-icon {
            background-color: rgb(var(--success));
            border-color: rgb(var(--success));
            color: white;
        }

        .loading-step.completed .loading-step-text {
            color: rgb(var(--success));
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @media (max-width: 576px) {
            .loading-steps {
                flex-direction: column;
                gap: 20px;
                align-items: flex-start;
                padding-left: 30px;
            }
            
            .loading-steps:before {
                top: 0;
                bottom: 0;
                left: 25px;
                right: auto;
                width: 3px;
                height: auto;
            }
            
            .loading-step {
                width: 100%;
                flex-direction: row;
                justify-content: flex-start;
                gap: 15px;
            }
            
            .loading-step-icon {
                margin-bottom: 0;
            }
        }
        
        /* Popup para ejemplos de documentos - mejorado */
        #document-popup {
            display: none;
            position: fixed;
            z-index: 1001;
            left: 0; top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.7);
            backdrop-filter: blur(5px);
            animation: fadeIn 0.3s;
        }
        
        #document-popup .popup-content {
            background-color: #fff;
            margin: 5% auto;
            padding: 25px;
            width: 90%;
            max-width: 700px;
            border-radius: 12px;
            position: relative;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            animation: fadeIn 0.5s;
        }
        
        #document-popup .close-popup {
            color: #aaa;
            position: absolute;
            top: 10px;
            right: 20px;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.2s ease;
            width: 40px;
            height: 40px;
            text-align: center;
            line-height: 40px;
            border-radius: 50%;
        }
        
        #document-popup .close-popup:hover {
            color: black;
            background-color: #f0f0f0;
        }
        
        #document-popup h3 {
            margin-top: 0;
            color: rgb(var(--primary));
            font-size: 22px;
            margin-bottom: 20px;
        }
        
        #document-popup img {
            width: 100%;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        /* Mejora para el total */
        #final-amount, #final-summary-amount {
            font-size: 28px;
            color: rgb(var(--primary)) !important;
            font-weight: 700;
            padding: 5px 15px;
            background-color: rgba(var(--primary), 0.05);
            border-radius: 10px;
            display: inline-block;
        }
        
        /* Media queries mejorados */
        @media (max-width: 768px) {
            #transferencia-form {
                padding: 20px;
                margin: 20px auto;
            }
            
            #form-navigation {
                flex-direction: row !important;
                overflow-x: auto;
                padding: 10px;
            }
            
            #form-navigation a {
                padding: 8px 12px;
                font-size: 14px;
                white-space: nowrap;
            }
            
            .upload-item {
                padding: 10px;
            }
            
            .button-container {
                display: flex !important;
                flex-direction: row !important;
                position: relative !important;
                background: white !important;
                padding: 12px 0 !important;
                border-top: 2px solid #e5e7eb !important;
                gap: 10px !important;
                margin-top: 20px !important;
                margin-bottom: 0 !important;
            }

            .button-container .button {
                flex: 1 !important;
                margin-bottom: 0 !important;
                width: auto !important;
                min-height: 50px !important;
                padding: 14px 16px !important;
                font-size: 15px !important;
                font-weight: 700 !important;
            }
            
            .radio-group label {
                min-width: 140px;
                padding: 15px 10px;
            }
            
            .radio-group svg {
                width: 40px;
                height: 40px;
            }
        }
        
        /* Estilos para secciones de acordeón en documentos */
        .accordion-section {
            margin-bottom: 25px;
            border: 1px solid rgb(var(--neutral-300));
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .accordion-section:hover {
            box-shadow: var(--shadow-md);
        }
        
        .accordion-header {
            padding: 18px 20px;
            background-color: rgba(var(--primary), 0.05);
            cursor: pointer;
            display: flex;
            align-items: center;
            position: relative;
            transition: all 0.3s ease;
        }
        
        .accordion-header.active {
            background-color: rgba(var(--primary), 0.1);
        }
        
        .accordion-header.completed {
            background-color: rgba(var(--success), 0.1);
        }
        
        .accordion-number {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            background-color: rgb(var(--primary));
            color: white;
            border-radius: 50%;
            margin-right: 15px;
            font-weight: bold;
        }
        
        .accordion-header.completed .accordion-number {
            background-color: rgb(var(--success));
        }
        
        .accordion-header h3 {
            margin: 0;
            flex: 1;
            font-size: 18px;
        }
        
        .accordion-status {
            font-size: 14px;
            color: rgb(var(--neutral-600));
            background-color: rgba(var(--neutral-500), 0.1);
            padding: 4px 12px;
            border-radius: 20px;
            margin-right: 15px;
        }
        
        .accordion-header.completed .accordion-status {
            background-color: rgba(var(--success), 0.1);
            color: rgb(var(--success));
        }

        /* Estilos para secciones de acordeón en documentos */
        .accordion-section {
            margin-bottom: 25px;
            border: 1px solid rgb(var(--neutral-300));
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .accordion-section:hover {
            box-shadow: var(--shadow-md);
        }
        
        .accordion-header {
            padding: 18px 20px;
            background-color: rgba(var(--primary), 0.05);
            cursor: pointer;
            display: flex;
            align-items: center;
            position: relative;
            transition: all 0.3s ease;
        }
        
        .accordion-header.active {
            background-color: rgba(var(--primary), 0.1);
        }
        
        .accordion-header.completed {
            background-color: rgba(var(--success), 0.1);
        }
        
        .accordion-number {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            background-color: rgb(var(--primary));
            color: white;
            border-radius: 50%;
            margin-right: 15px;
            font-weight: bold;
        }
        
        .accordion-header.completed .accordion-number {
            background-color: rgb(var(--success));
        }
        
        .accordion-header h3 {
            margin: 0;
            flex: 1;
            font-size: 18px;
        }
        
        .accordion-status {
            font-size: 14px;
            color: rgb(var(--neutral-600));
            background-color: rgba(var(--neutral-500), 0.1);
            padding: 4px 12px;
            border-radius: 20px;
            margin-right: 15px;
        }
        
        .accordion-header.completed .accordion-status,
        .accordion-status.completed {
            background-color: rgba(var(--success), 0.8);
            color: white;
            font-weight: 500;
        }
        
        .accordion-toggle {
            transition: all 0.3s ease;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: rgba(var(--primary), 0.1);
            border-radius: 50%;
        }
        
        .accordion-header:hover .accordion-toggle {
            background-color: rgba(var(--primary), 0.2);
            transform: scale(1.1);
        }
        
        .accordion-header.active .accordion-toggle {
            transform: rotate(180deg);
            background-color: rgba(var(--primary), 0.25);
        }
        
        .accordion-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.5s ease;
            background-color: white;
            padding: 0 20px;
        }
        
        .accordion-content.active {
            padding: 25px 20px;
        }

        /* Mejoras para los campos de entrada */
        .input-group {
            margin-bottom: 20px;
            position: relative;
        }
        
        .input-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .input-hint {
            display: block;
            font-size: 12px;
            color: rgb(var(--neutral-600));
            margin-top: 5px;
            font-style: italic;
        }
        
        .section-next-btn {
            background-color: rgb(var(--primary));
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: var(--radius-md);
            cursor: pointer;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.3s ease;
            margin-top: 20px;
            align-self: flex-end;
        }
        
        .section-next-btn:hover {
            background-color: rgb(var(--primary-dark));
            transform: translateY(-2px);
        }
        
        .section-next-btn:active {
            transform: translateY(0);
        }
        
        .section-intro {
            margin-bottom: 20px;
            color: rgb(var(--neutral-700));
            font-size: 15px;
            line-height: 1.5;
        }

        /* Mejoras para la carga de archivos */
        .upload-wrapper {
            position: relative;
            margin: 10px 0;
        }
        
        .upload-wrapper input[type="file"] {
            position: absolute;
            top: 0;
            left: 0;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
            z-index: 2;
        }
        
        .upload-button {
            padding: 12px 15px;
            background-color: rgba(var(--primary), 0.1);
            border: 1px dashed rgb(var(--primary));
            border-radius: var(--radius-md);
            color: rgb(var(--primary));
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .upload-wrapper:hover .upload-button {
            background-color: rgba(var(--primary), 0.15);
        }
        
        .file-name {
            margin-top: 5px;
            font-size: 14px;
            color: rgb(var(--neutral-600));
        }

        .file-count {
            margin-top: 5px;
            font-size: 14px;
            color: rgb(var(--neutral-600));
        }

        .file-uploaded .file-count {
            color: rgb(var(--success));
            font-weight: 500;
        }

        .files-preview {
            margin-top: 10px;
            display: none;
            max-height: 180px;
            overflow-y: auto;
            padding-right: 4px;
        }

        .files-preview.active {
            display: block;
        }

        .files-preview::-webkit-scrollbar {
            width: 6px;
        }

        .files-preview::-webkit-scrollbar-track {
            background: rgba(var(--neutral-200), 0.5);
            border-radius: 3px;
        }

        .files-preview::-webkit-scrollbar-thumb {
            background: rgba(var(--primary), 0.3);
            border-radius: 3px;
        }

        .files-preview::-webkit-scrollbar-thumb:hover {
            background: rgba(var(--primary), 0.5);
        }

        .file-preview-item {
            background-color: rgba(var(--primary), 0.05);
            padding: 8px 12px;
            border-radius: 6px;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: rgb(var(--neutral-700));
            min-width: 0;
            width: 100%;
        }

        .file-preview-item i {
            color: rgb(var(--primary));
            font-size: 14px;
            flex-shrink: 0;
        }

        .file-preview-item .file-size {
            font-size: 12px;
            color: rgb(var(--neutral-500));
            flex-shrink: 0;
            white-space: nowrap;
        }

        .file-preview-item .file-name-text {
            flex: 1;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .file-remove-btn {
            margin-left: auto;
            background: none;
            border: none;
            color: rgb(var(--neutral-400));
            font-size: 18px;
            font-weight: 700;
            line-height: 1;
            cursor: pointer;
            padding: 0 4px;
            transition: all 0.2s ease;
            opacity: 0.6;
            flex-shrink: 0;
        }

        .file-remove-btn:hover {
            color: rgb(var(--danger));
            opacity: 1;
            transform: scale(1.15);
        }

        .label-hint {
            font-size: 12px;
            font-weight: 400;
            color: rgb(var(--neutral-500));
            font-style: italic;
        }

        .file-uploaded .file-name {
            color: rgb(var(--success));
            font-weight: 500;
        }
        
        /* Mejoras para la firma */
        .signature-wrapper {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-top: 20px;
        }
        
        /* Estilos mejorados para popups y modales */
        .modal-popup {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
            padding: 30px;
            position: relative;
            max-width: 90%;
            width: 550px;
            margin: 0 auto;
            animation: modalFadeIn 0.4s ease;
        }
        
        @keyframes modalFadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .modal-popup:before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, rgb(var(--primary)), rgb(var(--primary-light)));
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
        }
        
        .modal-popup h3 {
            color: rgb(var(--primary));
            margin-top: 10px;
            margin-bottom: 20px;
            font-size: 24px;
            font-weight: 600;
        }
        
        .modal-popup-close {
            position: absolute;
            top: 15px;
            right: 15px;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: rgba(var(--neutral-200), 0.5);
            color: rgb(var(--neutral-700));
            font-size: 20px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .modal-popup-close:hover {
            background: rgba(var(--neutral-300), 0.8);
            color: rgb(var(--neutral-900));
        }
        
        /* Estilos mejorados para el popup del ITP */
        .calculation-section {
            background-color: #f9fafb;
            border-radius: 8px;
            padding: 20px;
            border: 1px solid #e5e7eb;
            position: relative;
            margin-bottom: 16px;
        }

        .calculation-section h4 {
            margin: 0 0 16px 0;
            font-size: 16px;
            color: #016d86;
            padding-bottom: 12px;
            border-bottom: 2px solid #e6f7fa;
            font-weight: 600;
        }

        .calculation-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            color: #4b5563;
            font-size: 14px;
        }

        .calculation-item.highlight-item {
            background-color: #e6f7fa;
            margin: 12px -12px 0;
            padding: 14px 12px;
            border-radius: 6px;
            border-left: 4px solid #016d86;
        }

        .calculation-item.highlight-item span:last-child {
            background-color: white;
            font-weight: 700;
            color: #016d86;
        }

        .calculation-item span:first-child {
            font-weight: 500;
        }

        .calculation-item span:last-child {
            font-weight: 600;
            color: #1f2937;
            background-color: white;
            padding: 6px 12px;
            border-radius: 6px;
            min-width: 90px;
            text-align: right;
            border: 1px solid #e5e7eb;
        }
        
        .calculation-result {
            display: flex;
            justify-content: space-between;
            font-size: 18px;
            font-weight: 700;
            color: white;
            background: linear-gradient(135deg, #016d86 0%, #014d5e 100%);
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
            box-shadow: 0 4px 12px rgba(1, 109, 134, 0.3);
            align-items: center;
        }

        .calculation-result span:first-child {
            font-size: 16px;
        }

        .calculation-result span:last-child {
            font-size: 22px;
            background-color: white;
            color: #016d86;
            padding: 10px 18px;
            border-radius: 6px;
            font-weight: 700;
        }
        
        #selected-services-summary {
            margin-top: 20px;
            padding: 15px;
            background-color: rgba(var(--primary), 0.05);
            border-radius: var(--radius-md);
            text-align: center;
            color: rgb(var(--primary-dark));
            font-weight: 500;
            border-left: 4px solid rgba(var(--primary), 0.4);
            animation: fadeIn 0.4s ease;
        }
        
        #signature-pad {
            border: 2px dashed rgba(var(--primary), 0.3);
            border-radius: var(--radius-md);
            background-color: white;
            width: 100%;
            max-width: 600px;
            transition: all 0.3s ease;
        }
        
        #signature-pad:hover {
            border-color: rgba(var(--primary), 0.6);
        }
        
        .authorization-document {
            background-color: rgba(var(--neutral-100), 0.5);
            padding: 25px;
            border-radius: var(--radius-md);
            border: 1px solid rgb(var(--neutral-300));
            margin-bottom: 25px;
            box-shadow: var(--shadow-sm);
            line-height: 1.6;
        }
        
        /* Estilos para el resumen del trámite */
        .summary-panel {
            background-color: #f9f9f9;
            border-radius: var(--radius-lg);
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: var(--shadow-sm);
            border: 1px solid rgb(var(--neutral-300));
        }
        
        .summary-panel h3 {
            color: rgb(var(--primary));
            font-size: 20px;
            margin-top: 0;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 1px solid rgba(var(--primary), 0.1);
            padding-bottom: 10px;
        }
        
        .summary-sections {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .summary-section {
            flex: 1;
            min-width: 250px;
            background-color: white;
            border-radius: var(--radius-md);
            padding: 15px;
            box-shadow: var(--shadow-sm);
            border: 1px solid rgb(var(--neutral-200));
        }
        
        .summary-section h4 {
            color: rgb(var(--neutral-700));
            font-size: 16px;
            margin-top: 0;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .summary-content {
            font-size: 14px;
        }
        
        .summary-content p {
            margin: 8px 0;
            display: flex;
            justify-content: space-between;
        }
        
        .summary-content strong {
            color: rgb(var(--neutral-700));
        }
        
        .checkmark {
            color: rgb(var(--success));
            font-weight: bold;
        }
        
        #summary-coupon-container {
            background-color: rgba(var(--secondary), 0.1);
            border-radius: var(--radius-md);
            padding: 10px 15px;
            margin-top: 10px;
            border: 1px dashed rgba(var(--secondary), 0.3);
        }
        
        /* Estilos mejorados para la página de precios */
        #page-precio {
            font-family: 'Roboto', sans-serif;
        }
        
        /* Tarjeta principal mejorada - coherente con form sections */
        .price-summary-card {
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            margin-bottom: 30px;
            border: 1px solid #e0e0e0;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .price-summary-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            border-color: #016d86;
        }
        
        .price-summary-header {
            background: linear-gradient(135deg, rgb(var(--primary)) 0%, rgb(var(--primary-dark)) 100%);
            padding: 22px 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: white;
            position: relative;
        }
        
        .price-summary-header h3 {
            margin: 0;
            font-size: 20px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            color: white !important;
        }

        .price-summary-header h3 i {
            color: white !important;
        }
        
        .price-summary-badge {
            background-color: rgba(255, 255, 255, 0.2);
            font-size: 14px;
            font-weight: 500;
            padding: 6px 12px;
            border-radius: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        
        .price-summary-body {
            padding: 0;
        }
        
        /* Sección principal de gestión - más limpia y coherente */
        .price-summary-main {
            padding: 28px;
            border-bottom: 1px solid #e8e8e8;
            background-color: #fafafa;
        }

        .price-summary-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            padding-bottom: 16px;
            border-bottom: 2px solid #e0e0e0;
        }

        .price-summary-title span {
            font-size: 17px;
            font-weight: 600;
            color: #1f2937;
        }

        .price-summary-amount {
            font-size: 19px;
            font-weight: 700;
            color: #016d86;
            background-color: #e6f7fa;
            padding: 10px 18px;
            border-radius: 8px;
            border: 1px solid #b3e5ef;
        }
        
        .price-summary-details {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .price-summary-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 12px;
            color: #4b5563;
            background-color: white;
            border-radius: 6px;
            font-size: 14px;
        }

        .price-summary-row i {
            color: #10b981;
            margin-right: 8px;
        }

        .price-summary-row span:first-child {
            font-weight: 500;
        }

        .price-summary-row span:last-child {
            font-weight: 600;
            color: #1f2937;
        }

        /* Sección de impuestos - estilo coherente */
        .price-summary-tax {
            padding: 28px;
            border-bottom: 1px solid #e8e8e8;
            background-color: #f9fafb;
        }
        
        .price-summary-help {
            margin-top: 16px;
            text-align: center;
        }

        .info-button-sm {
            background-color: white;
            color: #016d86;
            border: 2px solid #016d86;
            padding: 10px 18px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .info-button-sm:hover {
            background-color: #016d86;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(1, 109, 134, 0.2);
        }
        
        /* ============================================ */
        /* ESTILOS PARA NUEVO LAYOUT DE FIRMA SPLIT */
        /* ============================================ */
        
        /* Desktop: Split layout (documento + firma) */
        @media (min-width: 1024px) {
            .signature-split-container {
                display: flex !important;
            }
            
            .mobile-signature-container {
                display: none !important;
            }
        }
        
        /* Tablet y móvil: mantener modal */
        @media (max-width: 1023px) {
            .signature-split-container {
                display: none !important;
            }
            
            /* Mostrar botón de firma en móvil */
            #open-signature-modal-mobile {
                display: block !important;
                width: 100% !important;
                padding: 20px !important;
                background: #016d86 !important;
                color: white !important;
                border: none !important;
                border-radius: 12px !important;
                font-size: 18px !important;
                font-weight: 700 !important;
                margin: 20px 0 !important;
                cursor: pointer !important;
            }
        }

        /* Acordeón para secciones opcionales - estilo mejorado */
        .price-summary-accordion {
            border-bottom: 1px solid #e8e8e8;
        }

        .accordion-toggle-header {
            padding: 22px 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            background-color: #f5f5f5;
            transition: all 0.2s ease;
            border-top: 1px solid #e8e8e8;
        }

        .accordion-toggle-header:hover {
            background-color: #e6f7fa;
        }

        .accordion-toggle-header span {
            font-weight: 600;
            color: #374151;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 15px;
        }

        .accordion-toggle-header span i {
            color: #016d86;
            font-size: 16px;
        }

        .accordion-icon {
            color: #016d86;
            transition: transform 0.3s ease;
            font-size: 18px;
        }

        .accordion-toggle-header.active {
            background-color: #e6f7fa;
        }

        .accordion-toggle-header.active .accordion-icon {
            transform: rotate(180deg);
        }

        .accordion-content-section {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
            background-color: white;
        }

        .accordion-content-section.active {
            max-height: 500px;
            padding: 24px 28px;
            border-top: 2px solid #016d86;
        }
        
        /* Servicios adicionales dentro del acordeón - mejorado */
        .additional-service-item {
            margin-bottom: 16px;
            padding: 14px;
            border-radius: 8px;
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            transition: all 0.2s ease;
        }

        .additional-service-item:last-child {
            margin-bottom: 0;
        }

        .additional-service-item:hover {
            border-color: #016d86;
            background-color: #f0f9fa;
        }

        .service-checkbox {
            display: flex;
            align-items: center;
            cursor: pointer;
            padding: 0;
        }

        .service-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex: 1;
            margin-left: 12px;
        }

        .service-name {
            font-weight: 500;
            color: #1f2937;
            font-size: 14px;
        }

        .service-price {
            font-weight: 700;
            color: #016d86;
            background-color: #e6f7fa;
            padding: 6px 14px;
            border-radius: 6px;
            font-size: 14px;
            border: 1px solid #b3e5ef;
        }

        .additional-input {
            margin: 12px 0 0 28px;
            animation: fadeIn 0.3s ease;
        }

        .additional-input input {
            width: 100%;
            padding: 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
            background-color: white;
            transition: all 0.2s ease;
        }

        .additional-input input:focus {
            border-color: #016d86;
            box-shadow: 0 0 0 3px rgba(1, 109, 134, 0.1);
            outline: none;
        }
        
        /* Cupón dentro del acordeón - Rediseño coherente */
        .coupon-container {
            display: flex;
            flex-direction: column;
            gap: 16px;
            background-color: #fafafa;
            padding: 20px;
            border-radius: 8px;
            border: 2px dashed #d1d5db;
            position: relative;
        }

        .coupon-title {
            color: #016d86;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 15px;
            padding-bottom: 0;
            border-bottom: none;
            margin-bottom: 0;
        }

        .coupon-input-wrapper {
            display: flex;
            gap: 10px;
            position: relative;
        }

        .coupon-input-wrapper input {
            flex: 1;
            padding: 12px 14px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
            background-color: white;
            transition: all 0.2s ease;
        }

        .coupon-input-wrapper input:focus {
            border-color: #016d86;
            box-shadow: 0 0 0 3px rgba(1, 109, 134, 0.1);
            outline: none;
            background-color: white;
        }
        
        /* Estado de validación del cupón */
        .coupon-loading {
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 50 50" width="24" height="24"><path fill="%23016d86" d="M25,5A20.14,20.14,0,0,1,45,22.88a2.51,2.51,0,0,0,2.49,2.26h0A2.52,2.52,0,0,0,50,22.33a25.14,25.14,0,0,0-50,0,2.52,2.52,0,0,0,2.5,2.81h0A2.51,2.51,0,0,0,5,22.88,20.14,20.14,0,0,1,25,5Z"><animateTransform attributeName="transform" type="rotate" from="0 25 25" to="360 25 25" dur="0.6s" repeatCount="indefinite"/></path></svg>');
            background-repeat: no-repeat;
            background-position: right 15px center;
            padding-right: 45px !important;
        }
        
        .coupon-valid {
            border-color: rgb(var(--success)) !important;
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="%2328a745" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>');
            background-repeat: no-repeat;
            background-position: right 15px center;
            padding-right: 45px !important;
        }
        
        .coupon-error {
            border-color: rgb(var(--error)) !important;
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="%23e74c3c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>');
            background-repeat: no-repeat;
            background-position: right 15px center;
            padding-right: 45px !important;
        }
        
        .coupon-button {
            background-color: #016d86;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 14px;
        }

        .coupon-button:hover {
            background-color: #014d5e;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(1, 109, 134, 0.3);
        }

        .coupon-button:active {
            transform: translateY(0);
            box-shadow: 0 2px 4px rgba(1, 109, 134, 0.2);
        }
        
        .coupon-message {
            padding: 10px 14px;
            border-radius: 6px;
            font-weight: 500;
            font-size: 13px;
            line-height: 1.4;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .coupon-message.hidden {
            display: none;
        }

        .coupon-message.success {
            background-color: #d1fae5;
            color: #065f46;
            border: 1px solid #6ee7b7;
        }

        .coupon-message.success:before {
            content: '✓';
            font-weight: bold;
            font-size: 16px;
        }

        .coupon-message.error-message {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }

        .coupon-message.error-message:before {
            content: '!';
            font-weight: bold;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background-color: #dc2626;
            color: white;
        }

        .coupon-message.loading {
            background-color: #f3f4f6;
            color: #6b7280;
            border: 1px solid #d1d5db;
        }
        
        /* Total a pagar - diseño prominente y profesional */
        .price-summary-total {
            padding: 32px 28px;
            background: linear-gradient(135deg, #016d86 0%, #014d5e 100%);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .price-summary-total-label {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .price-summary-total-label span {
            font-size: 20px;
            font-weight: 700;
            color: white;
        }

        .price-summary-guarantees {
            display: flex;
            gap: 18px;
            font-size: 13px;
            color: rgba(255, 255, 255, 0.9);
        }

        .price-summary-guarantees span {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            font-weight: 500;
        }

        .price-summary-guarantees i {
            color: #b3e5ef;
        }

        .price-summary-total-amount {
            font-size: 36px;
            font-weight: 700;
            color: #016d86;
            background-color: white;
            padding: 16px 28px;
            border-radius: 8px;
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
        }
        
        /* Beneficios del servicio */
        .service-benefits {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 20px;
            margin: 30px 0;
        }
        
        .benefit-item {
            flex: 1;
            min-width: 200px;
            display: flex;
            align-items: center;
            gap: 15px;
            background-color: white;
            padding: 20px;
            border-radius: var(--radius-lg);
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(var(--neutral-300), 0.5);
            transition: all 0.3s ease;
        }
        
        .benefit-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            border-color: rgba(var(--primary), 0.3);
        }
        
        .benefit-icon {
            width: 50px;
            height: 50px;
            min-width: 50px;
            background: linear-gradient(135deg, rgb(var(--primary)) 0%, rgb(var(--primary-dark)) 100%);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            box-shadow: 0 3px 8px rgba(var(--primary), 0.3);
        }
        
        .benefit-text h4 {
            margin: 0 0 5px 0;
            color: rgb(var(--neutral-800));
            font-size: 16px;
        }
        
        .benefit-text p {
            margin: 0;
            color: rgb(var(--neutral-600));
            font-size: 14px;
        }
        
        /* Estilos para pantallas pequeñas */
        @media (max-width: 768px) {
            .price-summary-header, 
            .price-summary-main, 
            .price-summary-tax, 
            .accordion-toggle-header, 
            .accordion-content-section.active, 
            .price-summary-total {
                padding: 20px;
            }
            
            .price-summary-title, 
            .price-summary-total {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .price-summary-total-amount {
                align-self: stretch;
                text-align: center;
            }
            
            .coupon-input-wrapper {
                flex-direction: column;
            }
            
            .service-benefits {
                flex-direction: column;
            }
            
            .benefit-item {
                min-width: auto;
            }
        }
        
        /* Clases para destacar el descuento */
        .discount-row {
            background-color: rgba(var(--success), 0.08);
            border-radius: var(--radius-md);
            padding: 8px 12px;
            border: 1px dashed rgba(var(--success), 0.4);
        }
        
        .discount-text {
            font-weight: 600;
            color: rgb(var(--success));
        }

        /* ============================================
           LAYOUT DE 2 COLUMNAS CON PANEL LATERAL
           ============================================ */

        .tramitfy-layout-wrapper {
            max-width: 1400px;
            width: 95%;
            margin: 40px auto;
            padding: 0;
        }

        .tramitfy-two-column {
            display: grid !important;
            grid-template-columns: auto 1fr !important; /* Sidebar flexible + formulario resto */
            grid-template-areas: "sidebar content" !important;
            gap: 0;
            align-items: start; /* Cambiado para mejor alineación */
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            min-height: 600px;
        }

        /* Panel Lateral Izquierdo */
        .tramitfy-sidebar {
            grid-area: sidebar;
            position: relative;
            background: #016d86; /* Color corporativo sólido */
            border-radius: 16px 0 0 16px;
            padding: 28px 20px;
            box-shadow: none;
            border: none;
            backdrop-filter: none;
            min-height: 100%;
            overflow-y: auto;
            overflow-x: hidden;
            color: #ffffff;
            display: flex;
            flex-direction: column;
            width: 320px; /* Ancho base normal */
            transition: width 0.3s ease, background 0.3s ease, box-shadow 0.3s ease;
        }

        .sidebar-content {
            display: none;
            animation: fadeInUp 0.4s ease-out;
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
        }

        .sidebar-content.active {
            display: flex;
            flex-direction: column;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .sidebar-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 12px;
            padding-bottom: 10px;
            border-bottom: 2px solid rgba(255, 255, 255, 0.15);
            flex-shrink: 0;
        }

        .sidebar-icon {
            width: 38px;
            height: 38px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 19px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .sidebar-title {
            flex: 1;
        }

        .sidebar-title h3 {
            margin: 0 0 3px 0;
            color: #ffffff;
            font-size: 16px;
            font-weight: 700;
            line-height: 1.2;
        }

        .sidebar-title p {
            margin: 0;
            color: rgba(255, 255, 255, 0.85);
            font-size: 11px;
            line-height: 1.2;
        }

        .sidebar-body {
            margin-bottom: 12px;
            flex: 1;
            min-height: 0;
        }

        .sidebar-info-box {
            background: rgba(255, 255, 255, 0.12);
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            border-left: 3px solid rgba(255, 255, 255, 0.4);
        }

        .sidebar-info-box p {
            margin: 0 0 4px 0;
            color: rgba(255, 255, 255, 0.95);
            font-size: 12px;
            line-height: 1.4;
        }

        .sidebar-info-box p:last-child {
            margin-bottom: 0;
        }

        .sidebar-info-box strong {
            color: #ffffff;
            font-weight: 600;
        }

        .sidebar-checklist {
            background: white;
            padding: 12px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        }

        .sidebar-checklist-item {
            display: flex;
            align-items: start;
            gap: 8px;
            padding: 6px 0;
            border-bottom: 1px dashed rgba(var(--neutral-300), 0.5);
        }

        .sidebar-checklist-item:last-child {
            border-bottom: none;
        }

        .sidebar-check-icon {
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: rgba(var(--success), 0.1);
            color: rgb(var(--success));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .sidebar-checklist-text {
            flex: 1;
            color: rgb(var(--neutral-700));
            font-size: 11px;
            line-height: 1.3;
        }

        .sidebar-tips {
            background: rgba(255, 193, 7, 0.15);
            padding: 10px;
            border-radius: 8px;
            border-left: 3px solid #FFC107;
        }

        .sidebar-tips h4 {
            margin: 0 0 6px 0;
            color: #FFC107;
            font-size: 14px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .sidebar-tips h4 i {
            font-size: 16px;
        }

        .sidebar-tips ul {
            margin: 0;
            padding-left: 20px;
        }

        .sidebar-tips li {
            color: rgba(255, 255, 255, 0.95);
            font-size: 13px;
            line-height: 1.6;
            margin-bottom: 8px;
        }

        .sidebar-tips li:last-child {
            margin-bottom: 0;
        }

        .sidebar-price-highlight {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 25px;
            border-radius: 12px;
            text-align: center;
            margin-bottom: 20px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
            border: 2px solid rgba(255, 255, 255, 0.3);
            backdrop-filter: blur(10px);
        }

        .sidebar-price-label {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 8px;
        }

        .sidebar-price-amount {
            font-size: 36px;
            font-weight: 800;
            margin-bottom: 15px;
        }

        .sidebar-price-includes {
            font-size: 12px;
            opacity: 0.85;
            line-height: 1.5;
        }

        .sidebar-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(255, 255, 255, 0.2);
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            margin-top: 12px;
        }

        /* Panel Derecho - Formulario - maximizado */
        .tramitfy-main-form {
            grid-area: content;
            background: white;
            border-radius: 0 16px 16px 0;
            padding: 0;
            box-shadow: none;
            max-width: none !important;
            width: auto !important;
            overflow-x: visible;
            min-height: fit-content; /* Ajustar al contenido */
            height: auto; /* Altura automática */
            overflow-y: auto;
            min-width: 0;
        }

        /* Páginas del formulario sin restricciones de ancho */
        .form-page {
            width: 100%;
            max-width: 100% !important;
            padding: 32px 40px;
        }

        /* Clase hidden para ocultar páginas */
        .form-page.hidden {
            display: none !important;
        }

        /* Transiciones para los pasos del precio */
        .precio-step {
            transition: opacity 0.3s ease-out, transform 0.3s ease-out;
            opacity: 1;
            transform: translateY(0);
        }

        .documentos-step {
            transition: opacity 0.3s ease-out, transform 0.3s ease-out;
            opacity: 1;
            transform: translateY(0);
        }

        /* Estilos mejorados para página de documentos */
        #page-documentos {
            width: 100%;
            max-width: 100% !important;
            padding: 32px 40px !important;
        }

        #page-documentos h2 {
            margin-bottom: 8px;
            color: #1f2937;
            font-size: 26px;
        }

        #page-documentos > p {
            margin-bottom: 30px;
            color: #6b7280;
            line-height: 1.6;
        }

        /* Estilos mejorados para página de pago */
        #page-pago {
            width: 100%;
            max-width: 100% !important;
            padding: 32px 40px !important;
        }

        #page-pago > * {
            max-width: 100% !important;
        }

        #page-pago h2 {
            color: #1f2937;
            font-size: 26px;
        }

        /* Panel de admin */
        .admin-autofill-panel {
            margin: 24px 40px 0 40px !important;
        }

        /* Responsive - Laptops pequeños */
        @media (max-width: 1200px) and (min-width: 769px) {
            .tramitfy-two-column {
                grid-template-columns: 280px 1fr !important; /* Sidebar más estrecho */
            }

            .tramitfy-sidebar {
                padding: 24px 16px;
            }

            .form-page {
                padding: 28px 32px;
            }
        }

        /* Responsive - Solo en tablets y móviles */
        @media (max-width: 768px) {
            .tramitfy-two-column {
                grid-template-columns: 1fr !important;
                grid-template-areas: "sidebar" "content" !important;
                gap: 0;
            }

            .tramitfy-sidebar {
                position: relative;
                top: auto;
                min-height: auto;
                height: auto;
            }

            .tramitfy-main-form {
                padding: 30px 25px;
            }
        }

        @media (max-width: 768px) {
            .tramitfy-layout-wrapper {
                padding: 0;
                margin: 15px;
            }

            .tramitfy-two-column {
                border-radius: 12px;
            }

            .tramitfy-sidebar {
                padding: 20px;
                min-height: auto;
            }

            .sidebar-header {
                flex-direction: column;
                text-align: center;
                gap: 10px;
            }

            .sidebar-price-amount {
                font-size: 28px;
            }
        }

        /* Estilos para el modal de pago */
        .payment-modal {
            display: none;
            position: fixed;
            z-index: 1050;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(5px);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .payment-modal.show {
            display: block;
            opacity: 1;
        }
        
        .payment-modal-content {
            background-color: #fff;
            margin: 5% auto;
            max-width: 600px;
            width: 90%;
            border-radius: 12px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            padding: 30px;
            position: relative;
            transform: translateY(-20px);
            opacity: 0;
            transition: all 0.4s ease;
        }

        @media (min-width: 1024px) {
            .payment-modal-content {
                margin-top: calc(5% + 75px);
            }
        }

        .payment-modal.show .payment-modal-content {
            transform: translateY(0);
            opacity: 1;
        }
        
        .close-modal {
            position: absolute;
            top: 20px;
            right: 20px;
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.2s ease;
        }
        
        .close-modal:hover {
            color: #333;
            background-color: #f0f0f0;
        }
        
        /* Estilos para Stripe Payment Element */
        #stripe-container {
            margin: 0 auto;
            width: 100%;
            padding: 20px 0;
        }
        
        #payment-element {
            margin-bottom: 24px;
            min-height: 150px; /* Altura mínima para asegurar que sea visible */
        }
        
        #stripe-loading {
            text-align: center;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .stripe-spinner {
            display: inline-block;
            width: 40px;
            height: 40px;
            border: 4px solid rgba(var(--primary), 0.3);
            border-radius: 50%;
            border-top-color: rgb(var(--primary));
            animation: spin 1s linear infinite;
            margin-bottom: 15px;
        }
        
        .confirm-payment-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-top: 20px;
            background-color: rgb(var(--success));
            color: white;
            border: none;
            border-radius: var(--radius-md);
            padding: 14px 28px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 4px 6px rgba(46, 139, 87, 0.2);
            width: 100%;
            gap: 10px;
        }
        
        #payment-message {
            color: rgb(var(--neutral-700));
            font-size: 15px;
            line-height: 1.5;
            padding: 12px;
            border-radius: var(--radius-md);
            margin-top: 15px;
            text-align: center;
        }
        
        #payment-message.hidden {
            display: none;
        }
        
        #payment-message.error {
            background-color: rgba(var(--error), 0.1);
            color: rgb(var(--error));
            border: 1px solid rgba(var(--error), 0.3);
        }
        
        #payment-message.success {
            background-color: rgba(var(--success), 0.1);
            color: rgb(var(--success));
            border: 1px solid rgba(var(--success), 0.3);
        }
        
        #payment-message.processing {
            background-color: rgba(var(--primary), 0.1);
            color: rgb(var(--primary));
            border: 1px solid rgba(var(--primary), 0.3);
        }

        /* Estilos para formulario en sidebar */
        .sidebar-form-section {
            margin-bottom: 15px;
        }

        .sidebar-form-group {
            margin-bottom: 10px;
        }

        .sidebar-form-group label {
            display: block;
            color: rgba(255, 255, 255, 0.9);
            font-size: 11px;
            font-weight: 600;
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .sidebar-input {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid rgba(255, 255, 255, 0.25);
            border-radius: 6px;
            background: rgba(255, 255, 255, 0.15);
            color: white;
            font-size: 12px;
            font-family: 'Roboto', sans-serif;
            transition: all 0.2s ease;
            box-sizing: border-box;
        }

        .sidebar-input::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }

        .sidebar-input:focus {
            outline: none;
            background: rgba(255, 255, 255, 0.25);
            border-color: rgba(255, 255, 255, 0.5);
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.1);
        }

        .sidebar-doc-preview {
            margin-top: 15px;
        }

        .sidebar-doc-card {
            background: white;
            border-radius: 8px;
            padding: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            margin-top: 8px;
        }

        /* Nuevos estilos simplificados para página de precio */
        .price-section-simple {
            background: white;
            border-radius: 12px;
            margin-bottom: 20px;
            border: 1px solid #e5e7eb;
            overflow: hidden;
        }

        .price-section-header {
            padding: 20px 24px;
            background: linear-gradient(135deg, #016d86 0%, #014d5f 100%);
            color: white;
        }

        .price-section-header h3 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
        }

        .price-section-header h3 i {
            color: white;
            font-size: 16px;
        }

        .price-section-collapsible {
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.2s;
        }

        .price-section-collapsible:hover {
            background: linear-gradient(135deg, #01829e 0%, #015770 100%);
        }

        .price-section-collapsible .accordion-icon {
            transition: transform 0.3s;
        }

        .price-section-content {
            padding: 24px;
        }

        .price-explanation {
            color: #6b7280;
            font-size: 14px;
            line-height: 1.6;
            margin: 0 0 20px 0;
            padding: 12px;
            background: #f9fafb;
            border-left: 3px solid #016d86;
            border-radius: 4px;
        }

        .price-item-main {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px;
            background: #f9fafb;
            border-radius: 8px;
            margin-bottom: 12px;
        }

        .price-item-left {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .price-item-name {
            font-size: 16px;
            font-weight: 600;
            color: #1f2937;
        }

        .price-item-desc {
            font-size: 13px;
            color: #6b7280;
        }

        .price-item-right {
            font-size: 20px;
            font-weight: 700;
            color: #016d86;
        }

        .price-item-secondary {
            display: flex;
            justify-content: space-between;
            padding: 8px 16px;
            font-size: 14px;
            color: #6b7280;
        }

        .price-section-highlight {
            border: 2px solid #016d86;
            box-shadow: 0 4px 12px rgba(1, 109, 134, 0.1);
        }

        .btn-link-simple {
            background: transparent;
            border: none;
            color: #016d86;
            font-size: 14px;
            cursor: pointer;
            padding: 8px 0;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-weight: 500;
            transition: all 0.2s;
        }

        .btn-link-simple:hover {
            color: #014d5f;
            gap: 8px;
        }

        .checkbox-option-simple {
            margin-top: 16px;
            padding: 12px;
            background: #fef3c7;
            border-radius: 6px;
            border: 1px solid #fcd34d;
        }

        .checkbox-option-simple label {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            margin: 0;
        }

        .checkbox-option-simple input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .checkbox-label {
            font-size: 14px;
            color: #92400e;
            font-weight: 500;
        }

        .service-option-simple {
            padding: 16px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            margin-bottom: 12px;
            transition: all 0.2s;
        }

        .service-option-simple:hover {
            border-color: #016d86;
            background: #f9fafb;
        }

        .service-option-simple label {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            margin: 0;
        }

        .service-option-simple input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .service-option-content {
            flex: 1;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .service-option-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .service-option-name {
            font-size: 15px;
            font-weight: 600;
            color: #1f2937;
        }

        .service-option-desc {
            font-size: 13px;
            color: #6b7280;
        }

        .service-option-price {
            font-size: 16px;
            font-weight: 700;
            color: #016d86;
        }

        .coupon-input-simple {
            display: flex;
            gap: 10px;
        }

        .coupon-input-simple input {
            flex: 1;
            padding: 12px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.2s;
        }

        .coupon-input-simple input:focus {
            outline: none;
            border-color: #016d86;
        }

        .btn-apply-coupon {
            padding: 12px 24px;
            background: #016d86;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-apply-coupon:hover {
            background: #014d5f;
        }

        .price-total-simple {
            background: linear-gradient(135deg, #016d86 0%, #014d5f 100%);
            color: white;
            padding: 24px;
            border-radius: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 8px 20px rgba(1, 109, 134, 0.3);
        }

        .price-total-label {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .price-total-label > span {
            font-size: 16px;
            font-weight: 600;
            opacity: 0.95;
        }

        .price-total-badges {
            display: flex;
            gap: 12px;
            font-size: 13px;
            opacity: 0.9;
        }

        .price-total-badges i {
            margin-right: 4px;
        }

        .price-total-amount {
            font-size: 32px;
            font-weight: 700;
            color: white;
        }

        @media (max-width: 768px) {
            .price-section-header h3 {
                font-size: 16px;
            }

            .price-item-name {
                font-size: 14px;
            }

            .price-item-right {
                font-size: 18px;
            }

            .price-total-simple {
                flex-direction: column;
                text-align: center;
                gap: 16px;
            }

            .price-total-amount {
                font-size: 28px;
            }

            .service-option-content {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
        }

        .payment-modal h3 {
            color: rgb(var(--primary));
            font-size: 24px;
            margin-top: 0;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 1px solid rgba(var(--primary), 0.1);
            padding-bottom: 15px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Estilos mejorados para la sección de seguridad de pago */
        .payment-security {
            margin: 15px 0;
            text-align: center;
            padding: 10px 15px;
            background-color: rgba(var(--neutral-100), 0.5);
            border-radius: var(--radius-md);
            border: 1px solid rgba(var(--neutral-200), 0.5);
        }

        .security-badges {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .security-badge {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            color: rgb(var(--neutral-600));
        }

        .security-badge i {
            color: rgb(var(--primary));
            font-size: 14px;
        }

        .security-badge span {
            white-space: nowrap;
        }

        @media (max-width: 576px) {
            .security-badges {
                gap: 10px;
            }
            
            .security-badge {
                font-size: 12px;
            }
            
            .security-badge i {
                font-size: 13px;
            }
        }
        
        /* Estilos mejorados para la sección de seguridad de pago */
        .payment-security {
            margin: 15px 0;
            text-align: center;
            padding: 10px 15px;
            background-color: rgba(var(--neutral-100), 0.5);
            border-radius: var(--radius-md);
            border: 1px solid rgba(var(--neutral-200), 0.5);
        }

        .security-badges {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .security-badge {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            color: rgb(var(--neutral-600));
        }

        .security-badge i {
            color: rgb(var(--primary));
            font-size: 14px;
        }

        .security-badge span {
            white-space: nowrap;
        }

        @media (max-width: 576px) {
            .security-badges {
                gap: 10px;
            }

            .security-badge {
                font-size: 12px;
            }

            .security-badge i {
                font-size: 13px;
            }
        }

        /* ================================================
           OPTIMIZACIONES COMPLETAS PARA MÓVIL
           ================================================ */

        @media (max-width: 768px) {
            /* Layout principal móvil */
            .tramitfy-layout-wrapper {
                padding: 0 !important;
                margin: 0 !important;
            }

            .tramitfy-two-column {
                border-radius: 0 !important;
                margin: 0 !important;
            }

            /* Formulario principal - MÁS ANCHO */
            .tramitfy-main-form {
                padding: 18px 12px !important;
                max-width: 100% !important;
            }

            /* Form pages más anchas */
            .form-page {
                padding: 0 !important;
                max-width: 100% !important;
            }

            /* Secciones del formulario */
            .form-section {
                padding: 18px 14px !important;
                margin-bottom: 18px !important;
                border-radius: 12px !important;
            }

            .form-section h3 {
                font-size: 18px !important;
                margin-bottom: 16px !important;
                line-height: 1.3 !important;
            }

            /* ========================================
               REDISTRIBUCIÓN DE CONTAINERS
               ======================================== */

            /* TODOS los form-compact-row a 1 columna */
            .form-compact-row {
                grid-template-columns: 1fr !important;
                gap: 20px !important;
                margin-bottom: 20px !important;
            }

            /* TODOS los form-compact-triple a 1 columna */
            .form-compact-triple {
                grid-template-columns: 1fr !important;
                gap: 20px !important;
                margin-bottom: 20px !important;
            }

            /* Form groups individuales */
            .form-group {
                margin-bottom: 20px !important;
                width: 100% !important;
            }

            .form-group label {
                display: block !important;
                font-size: 15px !important;
                font-weight: 600 !important;
                margin-bottom: 10px !important;
                color: #1f2937 !important;
            }

            .form-group input,
            .form-group select,
            .form-group textarea {
                width: 100% !important;
                padding: 14px 16px !important;
                font-size: 16px !important;
                border: 2px solid #e5e7eb !important;
                border-radius: 10px !important;
                box-sizing: border-box !important;
                -webkit-appearance: none !important;
                appearance: none !important;
            }

            .form-group select {
                background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23016d86' d='M6 9L1 4h10z'/%3E%3C/svg%3E") !important;
                background-repeat: no-repeat !important;
                background-position: right 16px center !important;
                padding-right: 40px !important;
            }

            .form-group input:focus,
            .form-group select:focus,
            .form-group textarea:focus {
                border-color: #016d86 !important;
                outline: none !important;
                box-shadow: 0 0 0 3px rgba(1, 109, 134, 0.1) !important;
            }

            /* Hints de inputs */
            .input-hint,
            .form-group .input-hint {
                font-size: 13px !important;
                color: #64748b !important;
                margin-top: 6px !important;
                display: block !important;
                line-height: 1.4 !important;
            }

            /* Secciones del formulario */
            .form-section,
            .form-section-compact {
                padding: 20px 15px !important;
                margin-bottom: 0 !important;
            }

            /* Form pages */
            .form-page {
                padding: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
            }

            /* Títulos de páginas */
            .form-page h2 {
                font-size: 24px !important;
                margin-bottom: 15px !important;
            }

            .form-page .page-subtitle {
                font-size: 14px !important;
            }

            /* Input groups - MEJORADOS */
            .input-group {
                margin-bottom: 20px !important;
            }

            .input-group label {
                font-size: 15px !important;
                margin-bottom: 10px !important;
                font-weight: 600 !important;
                display: block !important;
            }

            .input-group input,
            .input-group select,
            .input-group textarea {
                font-size: 16px !important; /* Evita zoom en iOS */
                padding: 14px 16px !important;
                border-radius: 10px !important;
                width: 100% !important;
                border: 2px solid #e5e7eb !important;
                transition: all 0.2s !important;
            }

            .input-group input:focus,
            .input-group select:focus,
            .input-group textarea:focus {
                border-color: #016d86 !important;
                box-shadow: 0 0 0 3px rgba(1, 109, 134, 0.1) !important;
            }

            /* Selects más grandes */
            .input-group select {
                height: 50px !important;
                appearance: none !important;
                background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23016d86' d='M6 9L1 4h10z'/%3E%3C/svg%3E") !important;
                background-repeat: no-repeat !important;
                background-position: right 16px center !important;
                padding-right: 40px !important;
            }

            /* Grid de 2 columnas a 1 columna en móvil */
            .two-column-grid {
                grid-template-columns: 1fr !important;
                gap: 15px !important;
            }

            /* Botones de navegación - eliminado, se usa el de abajo que los fija */

            /* Barra de progreso - DISCRETO COMO PC */
            #form-navigation {
                display: block !important;
                visibility: visible !important;
                opacity: 1 !important;
                margin-bottom: 16px !important;
            }

            .nav-progress-bar {
                display: flex !important;
                flex-wrap: wrap !important;
                gap: 6px !important;
                padding: 8px !important;
                background: transparent !important;
                border-radius: 0 !important;
                border: none !important;
                box-shadow: none !important;
                justify-content: center !important;
            }

            .nav-item {
                font-size: 11px !important;
                padding: 8px 10px !important;
                min-height: 36px !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                gap: 5px !important;
                flex: 0 1 auto !important;
                background: #f3f4f6 !important;
                border-radius: 6px !important;
                border: 1px solid #e5e7eb !important;
                transition: all 0.2s !important;
                white-space: nowrap !important;
            }

            .nav-item.active {
                background: #016d86 !important;
                border-color: #016d86 !important;
                color: white !important;
            }

            .nav-item.completed {
                background: #ecfdf5 !important;
                border-color: #10b981 !important;
            }

            .nav-item .nav-icon {
                font-size: 14px !important;
                display: inline-block !important;
            }

            .nav-item.active .nav-icon {
                color: white !important;
            }

            .nav-item.completed .nav-icon {
                color: #10b981 !important;
            }

            .nav-item .nav-text {
                display: inline !important;
                font-weight: 600 !important;
            }

            .nav-item.active .nav-text {
                color: white !important;
            }

            /* Asegurar que el menú sea visible */
            .nav-progress-bar,
            .navigation-menu {
                visibility: visible !important;
                opacity: 1 !important;
            }

            /* ========================================
               PÁGINA DE DOCUMENTOS - REDISEÑADA MÓVIL
               ======================================== */

            #page-documentos {
                padding: 0 !important;
            }

            #page-documentos h2 {
                font-size: 22px !important;
                margin-bottom: 12px !important;
                line-height: 1.3 !important;
                padding: 0 12px !important;
                font-weight: 700 !important;
            }

            #page-documentos > p {
                font-size: 15px !important;
                margin-bottom: 24px !important;
                line-height: 1.6 !important;
                padding: 16px !important;
                background: #eff6ff !important;
                border-radius: 10px !important;
                border-left: 4px solid #3b82f6 !important;
                margin: 0 12px 20px 12px !important;
                color: #1f2937 !important;
            }

            /* Cards de sección - ANCHO COMPLETO */
            .docs-section-card {
                padding: 0 !important;
                margin-bottom: 0 !important;
                border-radius: 0 !important;
                background: transparent !important;
                border: none !important;
            }

            /* Upload grid - REDISEÑO COMPLETO */
            .upload-grid {
                gap: 0 !important;
                padding: 0 !important;
            }

            /* IMPORTANTE: Todas las filas de upload a columnas - FORZAR */
            #page-documentos .upload-row,
            #page-documentos div[style*="grid"] {
                display: flex !important;
                flex-direction: column !important;
                gap: 16px !important;
                margin-bottom: 0 !important;
            }

            #page-documentos .upload-row[style] {
                display: flex !important;
                flex-direction: column !important;
                grid-template-columns: 1fr !important;
                gap: 16px !important;
                margin-bottom: 24px !important;
            }

            /* Gap entre las filas upload-row */
            .upload-grid {
                gap: 0 !important;
            }

            .upload-grid .upload-row {
                margin-bottom: 24px !important;
            }

            /* Upload items - DISEÑO SIMPLE Y LIMPIO */
            .upload-item {
                padding: 14px !important;
                margin-bottom: 0 !important;
                margin-left: 0 !important;
                margin-right: 0 !important;
                background: white !important;
                border: 2px solid #e5e7eb !important;
                border-radius: 12px !important;
                width: 100% !important;
                position: relative !important;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05) !important;
            }
            
            /* Botones upload responsivos en móvil */
            .upload-button-responsive .desktop-text {
                display: none !important;
            }
            
            .upload-button-responsive .mobile-text {
                display: inline !important;
                display: block !important;
                height: auto !important;
                min-height: auto !important;
                overflow: visible !important;
            }

            /* Asegurar que TODO el contenido sea visible */
            .upload-item *:not(input[type="file"]) {
                visibility: visible !important;
                opacity: 1 !important;
            }

            .upload-item strong {
                display: block !important;
                visibility: visible !important;
                opacity: 1 !important;
                width: 100% !important;
            }

            .upload-item small {
                display: block !important;
                visibility: visible !important;
                opacity: 1 !important;
                width: 100% !important;
            }

            .upload-item .upload-button {
                display: flex !important;
                visibility: visible !important;
                opacity: 1 !important;
                width: 100% !important;
            }

            .upload-item .file-count {
                display: block !important;
                visibility: visible !important;
                opacity: 1 !important;
                width: 100% !important;
            }

            .upload-item .view-example {
                display: inline-block !important;
                visibility: visible !important;
                opacity: 1 !important;
            }

            .upload-item .label-hint {
                display: inline !important;
            }

            /* NO mostrar contador de pasos */
            .upload-item::before {
                content: none !important;
                display: none !important;
            }

            /* Label - visible y claro */
            .upload-item label {
                display: block !important;
                margin-bottom: 14px !important;
                cursor: pointer !important;
            }

            .upload-item label strong {
                font-size: 17px !important;
                font-weight: 700 !important;
                color: #111827 !important;
                display: block !important;
                margin-bottom: 4px !important;
            }

            .upload-item label small {
                font-size: 13px !important;
                color: #6b7280 !important;
                display: block !important;
                margin-bottom: 8px !important;
                line-height: 1.4 !important;
            }

            .upload-item .label-hint {
                font-weight: 500 !important;
                color: #6b7280 !important;
                font-size: 13px !important;
            }

            /* Quitar pseudo-elementos */
            .upload-item label::after,
            .upload-item label::before,
            .upload-item::after,
            .upload-item::before {
                content: none !important;
                display: none !important;
            }

            /* Wrapper del upload */
            .upload-wrapper {
                margin-top: 0 !important;
            }

            /* Botón de upload - VISIBLE */
            .upload-button {
                padding: 16px !important;
                font-size: 15px !important;
                font-weight: 600 !important;
                background: #016d86 !important;
                color: white !important;
                border-radius: 8px !important;
                cursor: pointer !important;
                text-align: center !important;
                transition: all 0.2s !important;
                border: none !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                gap: 10px !important;
                width: 100% !important;
                visibility: visible !important;
                opacity: 1 !important;
            }

            .upload-button i {
                font-size: 18px !important;
                display: inline-block !important;
                visibility: visible !important;
            }

            .upload-button:active {
                transform: scale(0.98) !important;
                background: #014d5f !important;
            }

            /* File count - VISIBLE */
            .file-count {
                font-size: 13px !important;
                color: #6b7280 !important;
                margin-top: 10px !important;
                margin-bottom: 0 !important;
                padding: 10px 12px !important;
                background: #f9fafb !important;
                border: 1px solid #e5e7eb !important;
                border-radius: 6px !important;
                text-align: center !important;
                display: block !important;
                visibility: visible !important;
                opacity: 1 !important;
                width: 100% !important;
            }

            .files-preview {
                margin-top: 10px !important;
                margin-bottom: 0 !important;
                display: block !important;
                width: 100% !important;
            }

            .files-preview img {
                max-width: 100% !important;
                height: auto !important;
                border-radius: 8px !important;
                margin-bottom: 8px !important;
            }

            /* Ver ejemplo link - VISIBLE */
            .view-example {
                font-size: 13px !important;
                font-weight: 600 !important;
                margin-top: 14px !important;
                margin-bottom: 0 !important;
                padding: 12px 0 0 0 !important;
                display: block !important;
                text-align: center !important;
                color: #016d86 !important;
                text-decoration: none !important;
                visibility: visible !important;
                opacity: 1 !important;
                width: 100% !important;
                border-top: 1px solid #e5e7eb !important;
            }

            .view-example:hover {
                text-decoration: underline !important;
            }

            /* Input file - oculto pero funcional */
            .upload-wrapper input[type="file"] {
                position: absolute !important;
                opacity: 0 !important;
                width: 0 !important;
                height: 0 !important;
                pointer-events: none !important;
            }

            /* Confirmación de documentos - simple */
            .docs-confirmation-container {
                padding: 16px !important;
                margin-top: 20px !important;
                background: #eff6ff !important;
                border: 2px solid #3b82f6 !important;
                border-radius: 10px !important;
            }

            .docs-confirmation-container label {
                gap: 12px !important;
                display: flex !important;
                align-items: flex-start !important;
            }

            .docs-confirmation-container input[type="checkbox"] {
                width: 22px !important;
                height: 22px !important;
                min-width: 22px !important;
                margin-top: 2px !important;
                accent-color: #3b82f6 !important;
                cursor: pointer !important;
            }

            .docs-confirmation-container .checkbox-text {
                font-size: 14px !important;
                line-height: 1.5 !important;
                color: #1e40af !important;
            }

            /* Upload areas - MEJORADAS */
            .document-upload-area {
                padding: 0 !important;
                margin-bottom: 25px !important;
            }

            .document-upload-area h4 {
                font-size: 16px !important;
                font-weight: 700 !important;
                margin-bottom: 12px !important;
                color: #1f2937 !important;
                padding-left: 4px !important;
            }

            .upload-zone {
                padding: 24px 16px !important;
                border: 2px dashed #cbd5e1 !important;
                border-radius: 8px !important;
                background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%) !important;
                min-height: 120px !important;
                display: flex !important;
                flex-direction: column !important;
                align-items: center !important;
                justify-content: center !important;
                cursor: pointer !important;
                transition: all 0.3s ease !important;
                position: relative !important;
            }

            .upload-zone:active {
                background: linear-gradient(135deg, #e0f2fe 0%, #bae6fd 100%) !important;
                border-color: #016d86 !important;
                border-width: 3px !important;
                transform: scale(0.98) !important;
            }

            .upload-zone-icon {
                font-size: 32px !important;
                margin-bottom: 12px !important;
                color: #016d86 !important;
                filter: drop-shadow(0 2px 4px rgba(1, 109, 134, 0.15)) !important;
            }

            .upload-zone-text {
                font-size: 14px !important;
                font-weight: 600 !important;
                margin-bottom: 6px !important;
                text-align: center !important;
                color: #1f2937 !important;
                line-height: 1.3 !important;
            }

            .upload-zone-hint {
                font-size: 12px !important;
                color: #64748b !important;
                text-align: center !important;
                line-height: 1.3 !important;
                max-width: 200px !important;
            }

            /* File preview mejorado - más compacto */
            .files-preview {
                padding: 8px !important;
                min-height: auto !important;
            }
            
            .file-preview {
                padding: 8px !important;
                margin: 4px 0 !important;
                background: white !important;
                border-radius: 12px !important;
                border: 2px solid #10b981 !important;
                margin-top: 15px !important;
                box-shadow: 0 2px 8px rgba(16, 185, 129, 0.1) !important;
            }

            .file-preview img {
                max-width: 100% !important;
                height: auto !important;
                border-radius: 10px !important;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08) !important;
            }

            .file-preview-name {
                font-size: 14px !important;
                font-weight: 600 !important;
                color: #059669 !important;
                margin-top: 12px !important;
                display: flex !important;
                align-items: center !important;
                gap: 8px !important;
            }

            /* Botón eliminar archivo */
            .remove-file-btn {
                padding: 10px 18px !important;
                font-size: 14px !important;
                font-weight: 600 !important;
                margin-top: 12px !important;
                background: #ef4444 !important;
                color: white !important;
                border: none !important;
                border-radius: 8px !important;
                cursor: pointer !important;
                width: 100% !important;
                transition: all 0.2s !important;
            }

            .remove-file-btn:active {
                background: #dc2626 !important;
                transform: scale(0.97) !important;
            }

            /* Checkbox de confirmación documentos - MEJORADO */
            .document-checkbox-label {
                font-size: 16px !important;
                padding: 20px 18px !important;
                display: flex !important;
                align-items: center !important;
                gap: 14px !important;
                background: #f8fafc !important;
                border: 2px solid #e5e7eb !important;
                border-radius: 12px !important;
                margin-top: 25px !important;
                cursor: pointer !important;
                transition: all 0.2s !important;
            }

            .document-checkbox-label:active {
                background: #e0f2fe !important;
                border-color: #016d86 !important;
            }

            #documents-complete-check {
                width: 28px !important;
                height: 28px !important;
                min-width: 28px !important;
                margin: 0 !important;
                cursor: pointer !important;
                accent-color: #016d86 !important;
            }

            .document-checkbox-label span {
                flex: 1 !important;
                line-height: 1.5 !important;
                color: #1f2937 !important;
                font-weight: 500 !important;
            }

            /* ========================================
               PÁGINA DE FIRMA - OPTIMIZADA PARA MÓVIL
               ======================================== */

            #documentos-step-2 {
                padding: 0 !important;
            }

            /* Header de firma - compacto */
            #documentos-step-2 > div:first-child {
                padding: 16px 12px !important;
                margin-bottom: 16px !important;
                background: #f9fafb !important;
                border-bottom: 2px solid #e5e7eb !important;
            }

            #documentos-step-2 > div:first-child h3 {
                font-size: 18px !important;
                margin-bottom: 6px !important;
            }

            #documentos-step-2 > div:first-child p {
                font-size: 13px !important;
                line-height: 1.4 !important;
            }

            #documentos-step-2 > div:first-child > div {
                flex-direction: column !important;
                gap: 12px !important;
            }

            #volver-documentos-step1 {
                width: 100% !important;
                padding: 12px 16px !important;
                font-size: 14px !important;
            }

            /* Documento de autorización - compacto */
            #authorization-document-full {
                padding: 20px 16px !important;
                margin: 0 12px 16px 12px !important;
                border-radius: 10px !important;
            }

            #authorization-document-full > div:first-child {
                margin-bottom: 20px !important;
                padding-bottom: 16px !important;
            }

            #authorization-document-full > div:first-child h2 {
                font-size: 20px !important;
                letter-spacing: 1px !important;
            }

            #authorization-document-full > div:first-child p {
                font-size: 13px !important;
            }

            #document-body {
                font-size: 13px !important;
                line-height: 1.7 !important;
                margin-bottom: 20px !important;
            }

            #document-body p {
                margin-bottom: 12px !important;
                text-align: justify !important;
            }

            /* Sección de firma - destacada */
            #authorization-document-full > div:last-child {
                margin-top: 30px !important;
                padding: 24px 16px !important;
                background: #f8f9fa !important;
                border-radius: 10px !important;
            }

            #authorization-document-full > div:last-child > div:first-child {
                margin-bottom: 16px !important;
            }

            #authorization-document-full > div:last-child > div:first-child h4 {
                font-size: 16px !important;
            }

            /* Ocultar pad de firma en móvil - usar modal */
            #signature-container {
                display: none !important;
            }

            /* Sección de firma con botón */
            #authorization-document-full > div:last-child {
                text-align: center !important;
            }

            /* Botón para abrir modal de firma */
            #open-signature-modal-mobile {
                width: 100% !important;
                padding: 18px 20px !important;
                font-size: 17px !important;
                font-weight: 700 !important;
                background: #016d86 !important;
                color: white !important;
                border: none !important;
                border-radius: 10px !important;
                cursor: pointer !important;
                transition: all 0.2s !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                gap: 10px !important;
                min-height: 56px !important;
            }

            #open-signature-modal-mobile:active {
                background: #014d5f !important;
                transform: scale(0.98) !important;
            }

            /* Modal de firma para móvil - RESETEAR para permitir uso */
            #signature-modal-mobile {
                display: none !important;
                visibility: visible !important;
                opacity: 1 !important;
                pointer-events: auto !important;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.8);
                z-index: 9999;
                justify-content: center;
                align-items: center;
            }

            #signature-modal-mobile.active {
                display: flex !important;
            }

            .signature-modal-content {
                background: white;
                width: 95%;
                max-width: 500px;
                border-radius: 16px;
                padding: 20px;
                position: relative;
                max-height: 90vh;
                display: flex;
                flex-direction: column;
            }

            .signature-modal-header {
                text-align: center;
                margin-bottom: 16px;
                padding-bottom: 12px;
                border-bottom: 2px solid #e5e7eb;
            }

            .signature-modal-header h3 {
                font-size: 20px;
                color: #016d86;
                margin: 0;
                font-weight: 700;
            }

            .signature-modal-body {
                flex: 1;
                display: flex;
                flex-direction: column;
                min-height: 0;
            }

            #signature-modal-canvas-wrapper {
                position: relative;
                flex: 1;
                margin-bottom: 16px;
                min-height: 250px;
            }

            #signature-modal-canvas {
                width: 100% !important;
                height: 100% !important;
                border: 3px solid #016d86 !important;
                border-radius: 12px !important;
                background: white !important;
                touch-action: none !important;
                cursor: crosshair !important;
            }

            #signature-modal-label {
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                color: #d1d5db;
                font-size: 20px;
                pointer-events: none;
                user-select: none;
                z-index: 1;
            }

            .signature-modal-buttons {
                display: flex;
                flex-direction: column;
                gap: 10px;
            }

            #clear-signature-modal,
            #confirm-signature-modal {
                width: 100%;
                padding: 14px 20px;
                font-size: 16px;
                font-weight: 700;
                border-radius: 10px;
                border: none;
                min-height: 50px;
                cursor: pointer;
                transition: all 0.2s;
            }

            #clear-signature-modal {
                background: #6b7280;
                color: white;
            }

            #clear-signature-modal:active {
                background: #4b5563;
                transform: scale(0.98);
            }

            #confirm-signature-modal {
                background: #016d86;
                color: white;
            }

            #confirm-signature-modal:active {
                background: #014d5f;
                transform: scale(0.98);
            }

            /* Botones de navegación en móvil */
            .navigation-buttons {
                position: fixed !important;
                bottom: 0 !important;
                left: 0 !important;
                right: 0 !important;
                background: white !important;
                padding: 12px !important;
                border-top: 2px solid #e5e7eb !important;
                box-shadow: 0 -4px 12px rgba(0, 0, 0, 0.1) !important;
                z-index: 100 !important;
                display: flex !important;
                flex-direction: row !important;
                gap: 10px !important;
                margin: 0 !important;
            }

            .navigation-buttons button {
                flex: 1 !important;
                padding: 14px 16px !important;
                font-size: 15px !important;
                font-weight: 700 !important;
                min-height: 50px !important;
                width: auto !important;
            }

            /* Ya no necesitamos padding inferior porque los botones están en el flujo */

            /* ========================================
               PÁGINA DE PAGO - OPTIMIZADA
               ======================================== */

            #page-pago {
                padding: 20px 15px !important;
            }

            #page-pago h2 {
                font-size: 24px !important;
                margin-bottom: 12px !important;
            }

            #page-pago > p {
                font-size: 14px !important;
                margin-bottom: 25px !important;
            }

            /* Contenedor de Stripe */
            #stripe-container {
                padding: 0 !important;
                margin: 0 !important;
                max-width: 100% !important;
            }

            /* Loading de Stripe */
            #stripe-loading {
                padding: 30px 20px !important;
                text-align: center !important;
                font-size: 15px !important;
            }

            /* Payment element */
            #payment-element {
                padding: 0 !important;
                margin-bottom: 20px !important;
            }

            /* Resumen de pago */
            .payment-summary {
                background: #f8fafc !important;
                padding: 20px !important;
                border-radius: 12px !important;
                margin-bottom: 20px !important;
                border: 2px solid #e5e7eb !important;
            }

            .payment-summary h3 {
                font-size: 18px !important;
                margin-bottom: 16px !important;
                color: #1f2937 !important;
            }

            .payment-summary-row {
                display: flex !important;
                justify-content: space-between !important;
                padding: 12px 0 !important;
                font-size: 15px !important;
                border-bottom: 1px solid #e5e7eb !important;
            }

            .payment-summary-row:last-child {
                border-bottom: none !important;
                padding-top: 16px !important;
                font-size: 18px !important;
                font-weight: 700 !important;
                color: #016d86 !important;
            }

            /* Checkbox de términos de pago - MEJORADO */
            .payment-terms {
                padding: 24px 20px !important;
                margin: 30px 0 !important;
                background: linear-gradient(135deg, rgba(1, 109, 134, 0.08) 0%, rgba(1, 109, 134, 0.03) 100%) !important;
                border: 2px solid rgba(1, 109, 134, 0.3) !important;
                border-radius: 14px !important;
            }

            .payment-terms label {
                display: flex !important;
                align-items: center !important;
                justify-content: flex-start !important;
                gap: 16px !important;
                font-weight: 500 !important;
                cursor: pointer !important;
                text-align: left !important;
            }

            .payment-terms .custom-checkbox-container {
                position: relative !important;
                width: 28px !important;
                height: 28px !important;
                min-width: 28px !important;
                flex-shrink: 0 !important;
            }

            .payment-terms .checkmark-box {
                position: absolute !important;
                top: 0 !important;
                left: 0 !important;
                height: 28px !important;
                width: 28px !important;
                background-color: white !important;
                border: 2.5px solid #016d86 !important;
                border-radius: 6px !important;
                transition: all 0.2s ease !important;
            }

            .payment-terms .checkmark {
                position: absolute !important;
                top: 0 !important;
                left: 0 !important;
                height: 28px !important;
                width: 28px !important;
                display: none !important;
                z-index: 2 !important;
            }

            .payment-terms .checkmark i {
                position: absolute !important;
                top: 3px !important;
                left: 7px !important;
                color: white !important;
                font-size: 16px !important;
            }

            .payment-terms span {
                flex: 1 !important;
                font-size: 15px !important;
                line-height: 1.6 !important;
                color: #1f2937 !important;
            }

            .payment-terms span a {
                font-weight: 700 !important;
                text-decoration: underline !important;
                color: #016d86 !important;
            }

            /* Botón de pago */
            #submit-payment {
                width: 100% !important;
                padding: 18px !important;
                font-size: 18px !important;
                font-weight: 700 !important;
                background: #016d86 !important;
                color: white !important;
                border: none !important;
                border-radius: 12px !important;
                min-height: 60px !important;
                cursor: pointer !important;
                transition: all 0.2s !important;
                margin-top: 20px !important;
            }

            #submit-payment:active {
                background: #014d5f !important;
                transform: scale(0.98) !important;
            }

            #submit-payment:disabled {
                background: #9ca3af !important;
                cursor: not-allowed !important;
            }

            /* Mensaje de pago */
            #payment-message {
                padding: 16px !important;
                border-radius: 10px !important;
                margin-top: 16px !important;
                font-size: 14px !important;
                line-height: 1.5 !important;
            }

            /* Security badges en pago */
            .security-badges {
                display: flex !important;
                flex-wrap: wrap !important;
                gap: 12px !important;
                justify-content: center !important;
                margin-top: 20px !important;
                padding: 20px !important;
                background: rgba(1, 109, 134, 0.05) !important;
                border-radius: 12px !important;
            }

            .security-badge {
                font-size: 13px !important;
                padding: 8px 12px !important;
                display: flex !important;
                align-items: center !important;
                gap: 6px !important;
            }

            /* Acordeones y dropdowns */
            .accordion-header {
                padding: 15px !important;
                font-size: 15px !important;
            }

            .accordion-content {
                padding: 15px !important;
            }

            /* Checkboxes y radios - MEJORADOS */
            .checkbox-group,
            .radio-group {
                gap: 12px !important;
                display: flex !important;
                flex-direction: column !important;
            }

            .checkbox-group label,
            .radio-group label {
                font-size: 15px !important;
                padding: 16px 18px !important;
                min-height: 54px !important;
                display: flex !important;
                align-items: center !important;
                border: 2px solid #e5e7eb !important;
                border-radius: 10px !important;
                cursor: pointer !important;
                transition: all 0.2s !important;
            }

            .checkbox-group label:active,
            .radio-group label:active,
            .checkbox-group label:has(input:checked),
            .radio-group label:has(input:checked) {
                border-color: #016d86 !important;
                background: rgba(1, 109, 134, 0.05) !important;
            }

            /* Checkbox/radio inputs más grandes */
            .checkbox-group input[type="checkbox"],
            .radio-group input[type="radio"] {
                width: 22px !important;
                height: 22px !important;
                margin-right: 12px !important;
                flex-shrink: 0 !important;
            }

            /* Tooltips y hints */
            .hint-text,
            .help-text {
                font-size: 12px !important;
            }

            /* Popup de documentos */
            #document-popup .popup-content {
                width: 95% !important;
                max-width: 95% !important;
                padding: 20px !important;
                margin: 10px !important;
            }

            #document-popup img {
                max-height: 70vh !important;
            }

            /* ITP Calculator */
            .itp-calculator {
                padding: 15px !important;
            }

            .itp-result {
                font-size: 14px !important;
                padding: 15px !important;
            }

            /* Resumen de precio */
            .price-summary-card {
                padding: 15px !important;
            }

            .price-row {
                font-size: 14px !important;
            }

            .total-price {
                font-size: 20px !important;
            }

            /* File uploads mejorados */
            .file-preview {
                max-width: 100% !important;
            }

            .file-name {
                font-size: 13px !important;
                max-width: 200px !important;
            }

            /* Mensajes de error/éxito */
            .error-message,
            .success-message {
                font-size: 13px !important;
                padding: 12px !important;
            }

            /* Tabs si los hay */
            .tab-buttons {
                flex-direction: column !important;
                gap: 8px !important;
            }

            .tab-button {
                width: 100% !important;
                padding: 12px !important;
            }
        }

        /* Móviles pequeños (< 480px) */
        @media (max-width: 480px) {
            .tramitfy-main-form {
                padding: 15px 12px !important;
            }

            .form-page h2 {
                font-size: 22px !important;
            }

            .input-group input,
            .input-group select,
            .input-group textarea {
                padding: 12px 14px !important;
            }

            .navigation-buttons button {
                padding: 14px !important;
                font-size: 15px !important;
            }

            /* Ocultar barra de progreso en móvil */
            .nav-progress-bar {
                display: none !important;
            }

            /* Menú de navegación móvil - diseño simple y compacto */
            .nav-items-container {
                gap: 2px !important;
                justify-content: space-between !important;
                overflow: visible !important;
                padding: 8px 4px !important;
            }

            .nav-item {
                font-size: 11px !important;
                padding: 8px 2px !important;
                min-width: auto !important;
                flex: 1 !important;
                text-align: center !important;
                display: flex !important;
                flex-direction: column !important;
                align-items: center !important;
                justify-content: center !important;
                gap: 0 !important;
                border-radius: 8px !important;
                border-bottom: none !important;
                transition: all 0.2s !important;
            }

            .nav-item-text {
                display: block !important;
                visibility: visible !important;
                opacity: 1 !important;
                font-size: 10px !important;
                line-height: 1.3 !important;
                font-weight: 600 !important;
                color: #6b7280 !important;
                max-width: 100% !important;
            }

            /* Ocultar círculos e iconos en móvil */
            .nav-item-circle {
                display: none !important;
            }

            .nav-item-icon {
                display: none !important;
            }

            .nav-item-number {
                display: none !important;
            }

            /* Estado activo más visible */
            .nav-item.active .nav-item-text {
                background: #016d86 !important;
                color: white !important;
                padding: 6px 10px !important;
                border-radius: 16px !important;
                font-weight: 700 !important;
            }

            .nav-item.completed .nav-item-text {
                color: #10b981 !important;
                font-weight: 600 !important;
            }

            #signature-pad {
                height: 200px !important;
            }

            .sidebar-price-amount {
                font-size: 24px !important;
            }
        }

        /* Landscape móvil */
        @media (max-width: 768px) and (orientation: landscape) {
            #signature-pad {
                height: 180px !important;
            }

            .tramitfy-main-form {
                padding: 15px !important;
            }

            .navigation-buttons {
                flex-direction: row !important;
            }

            .navigation-buttons button {
                width: auto !important;
                flex: 1 !important;
            }
        }

        /* Touch optimizations */
        @media (hover: none) and (pointer: coarse) {
            /* Aumentar área de toque para botones */
            button,
            .btn,
            a.button {
                min-height: 44px !important;
                min-width: 44px !important;
            }

            /* Mejorar contraste de estados activos */
            input:focus,
            select:focus,
            textarea:focus {
                outline: 3px solid #016d86 !important;
                outline-offset: 2px !important;
            }

            /* Prevenir zoom en inputs en iOS */
            input[type="text"],
            input[type="email"],
            input[type="tel"],
            input[type="number"],
            input[type="date"],
            select,
            textarea {
                font-size: 16px !important;
            }

            /* Mejorar scrolling suave */
            * {
                -webkit-overflow-scrolling: touch !important;
            }
        }

        /* Prevenir zoom en landscape */
        @media screen and (max-width: 768px) and (orientation: landscape) {
            html {
                touch-action: manipulation !important;
            }
        }

        /* Fix para Safari iOS */
        @supports (-webkit-touch-callout: none) {
            .tramitfy-main-form {
                min-height: -webkit-fill-available !important;
            }

            input,
            select,
            textarea {
                -webkit-appearance: none !important;
                border-radius: 8px !important;
            }
        }
    </style>

    <!-- Formulario principal -->
    <form id="transferencia-form" action="" method="POST" enctype="multipart/form-data">

        <!-- Wrapper de Layout de 2 Columnas -->
        <div class="tramitfy-layout-wrapper">
            <div class="tramitfy-two-column">

                <!-- Panel Lateral Izquierdo -->
                <aside class="tramitfy-sidebar">

                    <!-- Contenido dinámico por página -->
                    <div id="sidebar-dynamic-content" style="margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid rgba(255,255,255,0.2);">
                        <!-- Se actualizará dinámicamente con JavaScript -->
                    </div>

                    <!-- Contenido universal del sidebar -->
                    <div class="sidebar-content active" data-step="all">
                        <div class="sidebar-body">

                            <!-- Widget de Trustpilot directo en sidebar -->
                            <script defer async src='https://cdn.trustindex.io/loader.js?f4fbfd341d12439e0c86fae7fc2'></script>

                        </div>
                    </div>

                </aside>

                <!-- Panel Derecho - Formulario -->
                <div class="tramitfy-main-form">

                    <!-- Navegación del formulario mejorada -->
        <div id="form-navigation">
            <div class="nav-progress-bar">
                <div class="nav-progress-indicator"></div>
            </div>
            <div class="nav-items-container">
                <a href="#" class="nav-item" data-page-id="page-vehiculo">
                    <div class="nav-item-circle">
                        <div class="nav-item-icon">
                            <i class="fa-solid fa-water"></i>
                        </div>
                        <div class="nav-item-number">1</div>
                    </div>
                    <span class="nav-item-text">Vehículo</span>
                </a>

                <a href="#" class="nav-item" data-page-id="page-datos">
                    <div class="nav-item-circle">
                        <div class="nav-item-icon">
                            <i class="fa-solid fa-user"></i>
                        </div>
                        <div class="nav-item-number">2</div>
                    </div>
                    <span class="nav-item-text">Datos</span>
                </a>

                <a href="#" class="nav-item" data-page-id="page-precio">
                    <div class="nav-item-circle">
                        <div class="nav-item-icon">
                            <i class="fa-solid fa-tag"></i>
                        </div>
                        <div class="nav-item-number">3</div>
                    </div>
                    <span class="nav-item-text">Precio</span>
                </a>

                <a href="#" class="nav-item" data-page-id="page-documentos">
                    <div class="nav-item-circle">
                        <div class="nav-item-icon">
                            <i class="fa-solid fa-file-signature"></i>
                        </div>
                        <div class="nav-item-number">4</div>
                    </div>
                    <span class="nav-item-text">Documentos</span>
                </a>

                <a href="#" class="nav-item" data-page-id="page-pago">
                    <div class="nav-item-circle">
                        <div class="nav-item-icon">
                            <i class="fa-solid fa-credit-card"></i>
                        </div>
                        <div class="nav-item-number">5</div>
                    </div>
                    <span class="nav-item-text">Pago</span>
                </a>
            </div>
        </div>

                    <?php if (current_user_can('administrator')): ?>
                    <!-- Panel de Auto-rellenado TEST COMPACTO (solo administradores) -->
        <div class="admin-autofill-panel" style="position: fixed; top: 10px; right: 10px; z-index: 9999; background: #f0fdf4; border: 2px solid #10b981; padding: 10px; border-radius: 6px; max-width: 300px; font-size: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
            <h5 style="color: #047857; margin: 0 0 8px 0; font-size: 13px;">🔧 Admin TEST</h5>
            <button type="button" id="admin-autofill-btn" onclick="tramitfyAdminAutofill()" style="padding: 6px 12px; background: #10b981; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; font-size: 11px; width: 100%;">
                ⚡ Auto-rellenar TEST
            </button>
            <p style="margin: 6px 0 0 0; font-size: 10px; color: #64748b;">
                Stripe TEST: 4242 4242 4242 4242
            </p>
        </div>
        <?php endif; ?>

        <!-- Overlay de carga -->
        <div id="loading-overlay">
            <div class="loading-container">
                <div class="loading-spinner"></div>
                <div class="loading-title">Procesando su pago</div>
                <div class="loading-message">Por favor, no cierre esta ventana mientras completamos su trámite.</div>
                
                <div class="loading-steps">
                    <div class="loading-step" data-step="payment">
                        <div class="loading-step-icon"><i class="fa-solid fa-credit-card"></i></div>
                        <div class="loading-step-text">Verificando pago</div>
                    </div>
                    <div class="loading-step" data-step="documents">
                        <div class="loading-step-icon"><i class="fa-solid fa-file-alt"></i></div>
                        <div class="loading-step-text">Procesando documentos</div>
                    </div>
                    <div class="loading-step" data-step="complete">
                        <div class="loading-step-icon"><i class="fa-solid fa-check"></i></div>
                        <div class="loading-step-text">Completando trámite</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- PILOTO DE AVISOS (banner superior) -->
        <div id="alert-message" style="display:none; background-color:#fffae6; border:1px solid #ffe58a; padding:15px; border-radius:5px; margin-bottom:20px;">
            <p id="alert-message-text" style="margin:0; color:#666;"></p>
        </div>


        <!-- Página Vehículo -->
        <div id="page-vehiculo" class="form-page form-section-compact">
            <!-- Título del formulario en H2 -->
            <h2 style="margin-bottom: 12px; color: #016d86; font-size: 22px; font-weight: 600;">Cambio Titularidad Embarcación</h2>
            <p style="margin-bottom: 20px; font-size: 15px; color: #666; line-height: 1.6;">Realiza el cambio de titularidad de tu embarcación online, sin desplazamientos ni esperas en Capitanía.</p>
            
            
            <!-- Tipo de vehículo fijo: Barco -->
            <input type="hidden" name="vehicle_type" value="Embarcación">

            <!-- Fabricante y Modelo en fila -->
            <div id="vehicle-csv-section">
                <div class="form-compact-row">
                    <div class="form-group">
                        <label for="manufacturer">Fabricante</label>
                        <select id="manufacturer" name="manufacturer">
                            <option value="">Seleccione fabricante</option>
                            <?php foreach (array_keys($datos_fabricantes) as $fabricante): ?>
                                <option value="<?php echo esc_attr($fabricante); ?>"><?php echo esc_html($fabricante); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="model">Modelo</label>
                        <select id="model" name="model">
                            <option value="">Seleccione modelo</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- "No encuentro mi modelo" - compacto -->
            <div id="no-encuentro-wrapper" style="margin: 12px 0;">
                <label style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer; font-size: 14px;">
                    <input type="checkbox" id="no_encuentro_checkbox" name="no_encuentro_checkbox">
                    <span>No encuentro mi modelo</span>
                </label>

                <!-- Campos de marca/modelo manual en 2 columnas -->
                <div id="manual-fields" style="display: none; margin-top: 10px;">
                    <div class="form-compact-row">
                        <div class="form-group">
                            <label for="manual_manufacturer">Marca (manual)</label>
                            <input type="text" id="manual_manufacturer" name="manual_manufacturer" placeholder="Escriba la marca" />
                        </div>

                        <div class="form-group">
                            <label for="manual_model">Modelo (manual)</label>
                            <input type="text" id="manual_model" name="manual_model" placeholder="Escriba el modelo" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Fecha, Precio y Comunidad Autónoma en fila de 3 -->
            <div class="form-compact-triple">
                <div class="form-group">
                    <label for="matriculation_date" id="matriculation_date_label">Fecha Matriculación</label>
                    <input type="date" id="matriculation_date" name="matriculation_date" max="<?php echo date('Y-m-d'); ?>" required>
                </div>

                <div class="form-group">
                    <label for="purchase_price">Precio de Compra (€)</label>
                    <input type="number" id="purchase_price" name="purchase_price" placeholder="Ej: 12000" required />
                </div>

                <div class="form-group">
                    <label for="region">Comunidad Autónoma</label>
                    <select id="region" name="region" required>
                        <option value="">Seleccione comunidad</option>
                        <option value="Andalucía">Andalucía</option>
                        <option value="Aragón">Aragón</option>
                        <option value="Asturias">Asturias</option>
                        <option value="Islas Baleares">Islas Baleares</option>
                        <option value="Canarias">Canarias</option>
                        <option value="Cantabria">Cantabria</option>
                        <option value="Castilla-La Mancha">Castilla-La Mancha</option>
                        <option value="Castilla y León">Castilla y León</option>
                        <option value="Cataluña">Cataluña</option>
                        <option value="Comunidad Valenciana">Comunidad Valenciana</option>
                        <option value="Galicia">Galicia</option>
                        <option value="Madrid">Madrid</option>
                        <option value="Murcia">Murcia</option>
                        <option value="Navarra">Navarra</option>
                        <option value="País Vasco">País Vasco</option>
                        <option value="La Rioja">La Rioja</option>
                        <option value="Ceuta">Ceuta</option>
                        <option value="Melilla">Melilla</option>
                    </select>
                </div>
            </div>

            <!-- Contenedor para botones de navegación -->
            <div id="vehiculo-buttons-container"></div>

        </div> <!-- Fin page-vehiculo -->

        <!-- Página Datos -->
        <div id="page-datos" class="form-page form-section-compact hidden">
            <h2>Tus Datos Personales</h2>
            <p class="section-intro">Introduce tus datos personales para la gestión del trámite. Estos datos aparecerán en el documento de autorización.</p>

            <div class="form-compact-row">
                <div class="form-group">
                    <label for="customer_name">Nombre y Apellidos</label>
                    <input type="text" id="customer_name" name="customer_name" required />
                    <span class="input-hint">Tal como aparece en su DNI</span>
                </div>

                <div class="form-group">
                    <label for="customer_dni">DNI</label>
                    <input type="text" id="customer_dni" name="customer_dni" required />
                    <span class="input-hint">Formato: 12345678X</span>
                </div>
            </div>

            <div class="form-compact-row">
                <div class="form-group">
                    <label for="customer_email">Correo Electrónico</label>
                    <input type="email" id="customer_email" name="customer_email" required />
                    <span class="input-hint">Recibirás notificaciones del trámite</span>
                </div>

                <div class="form-group">
                    <label for="customer_phone">Teléfono</label>
                    <input type="tel" id="customer_phone" name="customer_phone" required />
                    <span class="input-hint">Para contactarte si es necesario</span>
                </div>
            </div>

            <!-- Contenedor para botones de navegación -->
            <div id="datos-buttons-container"></div>

        </div> <!-- Fin page-datos -->

        <!-- Página Precio - NUEVO FLUJO LIMPIO -->
        <div id="page-precio" class="form-page form-section-compact hidden">

            <!-- PASO 1: Información inicial de precios -->
            <div id="precio-step-1" class="precio-step">
                <h2 id="precio-titulo">Precio del Trámite</h2>
                <p id="precio-subtitulo" style="color: #666; margin-bottom: 32px; font-size: 15px; line-height: 1.6;">Todo lo que necesitas para completar tu transferencia de forma legal y sin complicaciones.</p>

                <!-- ITP (Impuesto) - Ajustar colores cuadros izquierda -->
                <div id="itp-info-box" style="background: rgba(1, 109, 134, 0.08); border: 2px solid #016d86; border-radius: 12px; padding: 28px; margin-bottom: 20px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                        <div>
                            <div style="font-size: 20px; font-weight: 700; color: #016d86;">Impuesto (ITP)</div>
                            <div style="font-size: 14px; color: #016d86; margin-top: 6px;">Obligatorio ante Hacienda</div>
                        </div>
                        <div style="font-size: 32px; font-weight: 700; color: #016d86; background: white; padding: 8px 16px; border-radius: 8px; text-align: center; border: 2px solid #016d86;" id="transfer_tax_step1">0 €</div>
                    </div>
                    <div style="border-top: 1px solid #016d86; padding-top: 16px; margin-top: 16px;">
                        <button type="button" id="ver-calculo-itp" style="background: #016d86; color: white; border: none; padding: 10px 20px; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; width: 100%;">
                            <i class="fa-solid fa-calculator"></i> Ver cómo se calcula el ITP
                        </button>
                    </div>

                    <!-- Detalle del cálculo (inicialmente oculto) -->
                    <div id="calculo-itp-detail" style="display: none; margin-top: 20px; padding-top: 20px; border-top: 2px dashed #016d86;">
                        <div style="font-size: 15px; color: #014d5f; margin-bottom: 16px; line-height: 1.6;">
                            <strong>¿Cómo se calcula?</strong> El ITP se aplica sobre el mayor valor entre:
                        </div>
                        <div style="display: grid; gap: 12px; font-size: 14px;">
                            <div style="display: flex; justify-content: space-between; padding: 10px; background: rgba(255,255,255,0.7); border-radius: 6px;">
                                <span style="color: #014d5f;">Precio de compra:</span>
                                <strong id="precio-compra-calc">0 €</strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding: 10px; background: rgba(255,255,255,0.7); border-radius: 6px;">
                                <span style="color: #014d5f;">Valor fiscal:</span>
                                <strong id="valor-fiscal-calc">0 €</strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding: 12px; background: white; border-radius: 6px; border: 2px solid #016d86;">
                                <span style="color: #016d86; font-weight: 600;">Base imponible:</span>
                                <strong style="color: #016d86;" id="base-imponible-calc">0 €</strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding: 10px; background: rgba(255,255,255,0.7); border-radius: 6px;">
                                <span style="color: #014d5f;">Tipo impositivo (<span id="region-name-calc">-</span>):</span>
                                <strong id="tipo-impositivo-calc">4%</strong>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pregunta ITP Pagado -->
                <div id="itp-question-container" style="background: rgba(1, 109, 134, 0.08); border: 2px solid #016d86; border-radius: 12px; padding: 24px; text-align: center; transition: all 0.3s ease;">
                    <h3 style="margin: 0 0 8px 0; font-size: 18px; color: #1f2937;">¿Ya has pagado el ITP?</h3>
                    <p style="margin: 0 0 20px 0; font-size: 14px; color: #6b7280;">Selecciona tu situación</p>
                    <div style="display: flex; gap: 12px; justify-content: center;">
                        <button type="button" id="itp-si" class="itp-choice-btn" style="flex: 1; max-width: 200px; padding: 16px 24px; border: 2px solid #016d86; background: white; color: #016d86; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.2s;">
                            Sí, ya lo pagué
                        </button>
                        <button type="button" id="itp-no" class="itp-choice-btn" style="flex: 1; max-width: 200px; padding: 16px 24px; border: 2px solid #016d86; background: white; color: #016d86; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.2s;">
                            No, necesito pagarlo
                        </button>
                    </div>
                </div>

                <!-- Flujo: ITP Ya Pagado (oculto inicialmente) -->
                <div id="itp-ya-pagado-flow" style="display: none; margin-top: 20px; background: #f0fdf4; border: 2px solid #10b981; border-radius: 12px; padding: 24px; text-align: center;">
                    <div style="display: flex; align-items: center; justify-content: center; gap: 12px; margin-bottom: 20px;">
                        <h4 style="margin: 0; font-size: 18px; color: #065f46;">Perfecto, ya tienes el ITP pagado</h4>
                    </div>
                    <button type="button" id="btn-ver-desglose-si" style="background: #10b981; color: white; border: none; padding: 14px 32px; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.2s; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);">
                        <i class="fa-solid fa-arrow-right" style="margin-right: 8px;"></i>
                        Continuar al resumen
                    </button>
                    <p style="margin: 16px 0 0 0; font-size: 13px; color: #059669;">
                        📄 Recuerda: Necesitarás el Modelo 620 en el paso de documentos
                    </p>
                </div>

                <!-- Flujo: ITP No Pagado - Gestión (oculto inicialmente) -->
                <div id="itp-no-pagado-flow" style="display: none; margin-top: 20px;">
                    <!-- Resumen compacto ITP (oculto inicialmente, se mostrará al seleccionar) -->
                    <div id="itp-resumen-compacto" style="display: none; background: #f8fafc; border: 2px solid #e2e8f0; border-radius: 8px; padding: 16px; margin-bottom: 20px; cursor: pointer; transition: all 0.2s ease; opacity: 0; transform: translateY(-5px);" onclick="mostrarOpcionesITP()">
                        <div style="display: flex; align-items: center; justify-content: space-between;">
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <i class="fa-solid fa-hand-holding-dollar" style="color: #016d86; font-size: 18px;"></i>
                                <span id="resumen-itp-texto" style="font-size: 15px; color: #1f2937; font-weight: 500;">ITP: Lo pago yo mismo</span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span style="font-size: 13px; color: #6b7280;">Modificar</span>
                                <i class="fa-solid fa-edit" style="color: #6b7280; font-size: 14px;"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Opción de gestión -->
                    <div id="itp-opciones-container" style="background: #eff6ff; border: 2px solid #016d86; border-radius: 12px; padding: 24px; margin-bottom: 20px; transition: all 0.2s ease; opacity: 1; transform: translateY(0);">
                        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
                            <i class="fa-solid fa-hand-holding-dollar" style="color: #016d86; font-size: 24px;"></i>
                            <h4 style="margin: 0; font-size: 17px; color: #014d5f;">¿Quieres que lo gestionemos nosotros?</h4>
                        </div>
                        <p style="margin: 0 0 20px 0; font-size: 15px; color: #0369a1; line-height: 1.6;">
                            Podemos pagarlo y gestionarlo por ti. Tú decides cómo pagarlo:
                        </p>

                        <!-- Opciones de gestión -->
                        <div style="display: grid; gap: 12px;">
                            <!-- Opción: Lo pago yo -->
                            <label style="display: flex; align-items: flex-start; gap: 12px; padding: 16px; background: white; border: 2px solid #e5e7eb; border-radius: 8px; cursor: pointer; transition: all 0.2s;" class="itp-gestion-option" data-option="yo-pago">
                                <input type="radio" name="itp_gestion" value="yo-pago" style="margin-top: 4px; width: 18px; height: 18px;">
                                <div style="flex: 1;">
                                    <div style="font-size: 15px; font-weight: 600; color: #1f2937; margin-bottom: 4px;">Lo pago yo mismo</div>
                                    <div style="font-size: 13px; color: #6b7280; line-height: 1.4;">
                                        Pagas el ITP por tu cuenta y nos aportas el modelo 620. Nosotros gestionamos la transferencia cuando lo recibamos.
                                    </div>
                                </div>
                            </label>

                            <!-- Opción: Lo gestionan ustedes -->
                            <label style="display: flex; align-items: flex-start; gap: 12px; padding: 16px; background: white; border: 2px solid #e5e7eb; border-radius: 8px; cursor: pointer; transition: all 0.2s;" class="itp-gestion-option" data-option="gestionan-ustedes">
                                <input type="radio" name="itp_gestion" value="gestionan-ustedes" style="margin-top: 4px; width: 18px; height: 18px;">
                                <div style="flex: 1;">
                                    <div style="font-size: 15px; font-weight: 600; color: #1f2937; margin-bottom: 4px;">Lo gestionan ustedes (+40€)</div>
                                    <div style="font-size: 13px; color: #6b7280; line-height: 1.4; margin-bottom: 8px;">
                                        Nosotros pagamos y gestionamos el ITP. Tú abonas el importe del ITP ahora.
                                    </div>
                                    <!-- Submétodos de pago (ocultos hasta seleccionar esta opción) -->
                                    <div id="metodos-pago-itp" style="display: none; margin-top: 12px; padding-top: 12px; border-top: 1px solid #e5e7eb;">
                                        <p style="margin: 0 0 10px 0; font-size: 13px; font-weight: 600; color: #374151;">¿Cómo prefieres pagarlo?</p>
                                        <div style="display: grid; gap: 8px;">
                                            <!-- Tarjeta -->
                                            <label style="display: flex; align-items: center; gap: 8px; padding: 10px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; cursor: pointer; font-size: 13px;">
                                                <input type="radio" name="metodo_pago_itp" value="tarjeta" style="width: 16px; height: 16px;">
                                                <div style="flex: 1;">
                                                    <span style="font-weight: 600; color: #1f2937;">Tarjeta</span>
                                                    <span style="color: #016d86; margin-left: 6px;">(+1,5% comisión bancaria)</span>
                                                </div>
                                            </label>
                                            <!-- Transferencia -->
                                            <label style="display: flex; align-items: center; gap: 8px; padding: 10px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; cursor: pointer; font-size: 13px;">
                                                <input type="radio" name="metodo_pago_itp" value="transferencia" style="width: 16px; height: 16px;">
                                                <div style="flex: 1;">
                                                    <span style="font-weight: 600; color: #1f2937;">Transferencia bancaria</span>
                                                    <span style="color: #059669; margin-left: 6px;">(sin comisión)</span>
                                                </div>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Botón para ver desglose cuando elige "lo pago yo" (oculto inicialmente) -->
                    <div id="btn-container-yo-pago" style="display: none; margin-top: 20px; text-align: center;">
                        <button type="button" id="btn-ver-desglose-yo-pago" style="background: #6b7280; color: white; border: none; padding: 14px 32px; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.2s; box-shadow: 0 4px 12px rgba(107, 114, 128, 0.3);">
                            <i class="fa-solid fa-arrow-right" style="margin-right: 8px;"></i>
                            Continuar al resumen
                        </button>
                        <p style="margin: 16px 0 0 0; font-size: 13px; color: #6b7280;">
                            📄 Recuerda: Necesitarás el Modelo 620 en el paso de documentos
                        </p>
                    </div>

                    <!-- Resumen del ITP a pagar (aparece al seleccionar método de pago cuando gestionamos nosotros) -->
                    <div id="itp-pago-resumen" style="display: none; background: white; border: 2px solid #016d86; border-radius: 12px; padding: 20px; margin-top: 20px;">
                        <h4 style="margin: 0 0 16px 0; font-size: 16px; color: #014d5f;">💰 Resumen del pago ITP</h4>
                        <div style="display: grid; gap: 10px; font-size: 14px;">
                            <div style="display: flex; justify-content: space-between;">
                                <span style="color: #6b7280;">ITP base:</span>
                                <strong id="itp-base-display">0 €</strong>
                            </div>
                            <div id="comision-tarjeta-row" style="display: none;">
                                <div style="display: flex; justify-content: space-between; color: #016d86;">
                                    <span>Comisión tarjeta (1,5%):</span>
                                    <strong id="comision-tarjeta-display">0 €</strong>
                                </div>
                            </div>
                            <div style="border-top: 1px solid #e5e7eb; padding-top: 10px; display: flex; justify-content: space-between; font-size: 16px;">
                                <span style="font-weight: 600; color: #014d5f;">Total ITP a pagar:</span>
                                <strong style="color: #016d86;" id="itp-total-display">0 €</strong>
                            </div>
                        </div>
                        <button type="button" id="btn-ver-desglose-gestionamos" style="margin-top: 20px; width: 100%; background: #016d86; color: white; border: none; padding: 14px 32px; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.2s; box-shadow: 0 4px 12px rgba(1, 109, 134, 0.3);">
                            <i class="fa-solid fa-arrow-right" style="margin-right: 8px;"></i>
                            Continuar al resumen
                        </button>
                        <p id="metodo-pago-info" style="margin: 16px 0 0 0; font-size: 13px; color: #0369a1; text-align: center;">
                            <!-- Se actualizará dinámicamente con el método de pago seleccionado -->
                        </p>
                    </div>
                </div>
            </div>

            <!-- PASO 2: Resumen final con llamado a acción (oculto inicialmente) -->
            <div id="precio-step-2" class="precio-step" style="display: none;">

                <h2 style="margin-bottom: 8px; color: #1f2937; font-size: 26px;">Resumen del Trámite</h2>
                <p style="margin-bottom: 20px; color: #6b7280; font-size: 15px; line-height: 1.6;">Revisa los servicios incluidos. Si necesitas modificar algo, usa el botón al final de esta página.</p>

                <!-- Desglose de Precio -->
                <div style="background: white; border: 2px solid #e5e7eb; border-radius: 12px; padding: 28px; margin-bottom: 20px;">
                    <h3 style="margin: 0 0 20px 0; font-size: 18px; font-weight: 700; color: #1f2937;">Desglose de Servicios</h3>

                    <!-- Tramitación completa -->
                    <div style="display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #e5e7eb;">
                        <div>
                            <div style="font-size: 15px; font-weight: 600; color: #1f2937;">Tramitación Completa</div>
                            <div style="font-size: 13px; color: #6b7280; margin-top: 2px;">Gestión, tasas DGMM e IVA incluidos</div>
                        </div>
                        <div style="font-size: 16px; font-weight: 700; color: #1f2937;" id="desglose-tramitacion">134.99 €</div>
                    </div>

                    <!-- ITP - Caso 1: ITP ya pagado -->
                    <div id="incluye-itp-si" style="display: none;">
                        <div style="display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #e5e7eb;">
                            <div>
                                <div style="font-size: 15px; font-weight: 600; color: #10b981;">
                                    <i class="fa-solid fa-circle-check" style="margin-right: 6px;"></i>
                                    Impuesto (ITP)
                                </div>
                                <div style="font-size: 13px; color: #6b7280; margin-top: 2px;">Ya has pagado el ITP por tu cuenta</div>
                            </div>
                            <div style="font-size: 16px; font-weight: 700; color: #10b981;">Ya pagado</div>
                        </div>
                    </div>

                    <!-- ITP - Caso 2 y 3: ITP incluido en el precio -->
                    <div id="incluye-itp-no" style="display: none;">
                        <div style="display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #e5e7eb;">
                            <div>
                                <div style="font-size: 15px; font-weight: 600; color: #016d86;">Impuesto (ITP)</div>
                                <div style="font-size: 13px; color: #6b7280; margin-top: 2px;" id="itp-desglose-descripcion">Gestionamos el pago por ti</div>
                            </div>
                            <div style="font-size: 16px; font-weight: 700; color: #016d86;" id="desglose-itp">0 €</div>
                        </div>
                        <!-- Comisión tarjeta (solo si aplica) -->
                        <div id="desglose-comision-container" style="display: none;">
                            <div style="display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #e5e7eb;">
                                <div>
                                    <div style="font-size: 15px; font-weight: 600; color: #016d86;">Comisión Tarjeta (1,5%)</div>
                                    <div style="font-size: 13px; color: #6b7280; margin-top: 2px;">Sobre el importe del ITP</div>
                                </div>
                                <div style="font-size: 16px; font-weight: 700; color: #016d86;" id="desglose-comision">0 €</div>
                            </div>
                        </div>
                    </div>

                    <!-- Servicios extras (dinámico) -->
                    <div id="desglose-extras-container"></div>

                    <!-- Cupón de descuento (si aplica) -->
                    <div id="desglose-cupon-container" style="display: none;">
                        <div style="display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #e5e7eb;">
                            <div>
                                <div style="font-size: 15px; font-weight: 600; color: #059669;">Cupón de Descuento</div>
                                <div style="font-size: 13px; color: #6b7280; margin-top: 2px;" id="cupon-codigo-aplicado">-</div>
                            </div>
                            <div style="font-size: 16px; font-weight: 700; color: #059669;" id="desglose-cupon">-0 €</div>
                        </div>
                    </div>

                    <!-- Total -->
                    <div style="display: flex; justify-content: space-between; padding: 20px 0 0 0; margin-top: 12px; border-top: 2px solid #1f2937;">
                        <div style="font-size: 20px; font-weight: 700; color: #1f2937;">Total a Pagar</div>
                        <div style="font-size: 28px; font-weight: 700; color: #016d86;" id="total-final-precio">134.99 €</div>
                    </div>
                </div>

                <!-- Servicios Adicionales - Diseño Compacto -->
                <div style="background: white; border: 2px solid #e5e7eb; border-radius: 12px; margin-bottom: 20px; padding: 20px;">
                    <h3 style="margin: 0 0 16px 0; font-size: 18px; font-weight: 700; color: #1f2937; text-align: center;">Servicios Adicionales</h3>
                    
                    <!-- Cambio de Lista -->
                    <div style="border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 12px; padding: 14px; background: #f9fafb;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                            <span style="font-size: 15px; font-weight: 600; color: #1f2937;">Cambio de lista</span>
                            <span style="font-size: 14px; font-weight: 700; color: #016d86;">+64,95€</span>
                        </div>
                        <div style="display: flex; gap: 8px;">
                            <button type="button" id="cambio-lista-si" class="cambio-lista-btn" style="flex: 1; padding: 8px 16px; border: 1px solid #016d86; background: white; color: #016d86; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.2s;">
                                Sí
                            </button>
                            <button type="button" id="cambio-lista-no" class="cambio-lista-btn" style="flex: 1; padding: 8px 16px; border: 1px solid #d1d5db; background: white; color: #6b7280; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.2s;">
                                No
                            </button>
                        </div>
                    </div>

                    <!-- Cambio de Nombre -->
                    <div style="border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 12px; padding: 14px; background: #f9fafb;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                            <span style="font-size: 15px; font-weight: 600; color: #1f2937;">Cambio de nombre</span>
                            <span style="font-size: 14px; font-weight: 700; color: #016d86;">+40,00€</span>
                        </div>
                        <div style="display: flex; gap: 8px;">
                            <button type="button" id="cambio-nombre-si" class="cambio-nombre-btn" style="flex: 1; padding: 8px 16px; border: 1px solid #016d86; background: white; color: #016d86; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.2s;">
                                Sí
                            </button>
                            <button type="button" id="cambio-nombre-no" class="cambio-nombre-btn" style="flex: 1; padding: 8px 16px; border: 1px solid #d1d5db; background: white; color: #6b7280; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.2s;">
                                No
                            </button>
                        </div>
                    </div>

                    <!-- Cambio de Puerto -->
                    <div style="border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 0; padding: 14px; background: #f9fafb;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                            <span style="font-size: 15px; font-weight: 600; color: #1f2937;">Cambio de puerto base</span>
                            <span style="font-size: 14px; font-weight: 700; color: #016d86;">+40,00€</span>
                        </div>
                        <div style="display: flex; gap: 8px;">
                            <button type="button" id="cambio-puerto-si" class="cambio-puerto-btn" style="flex: 1; padding: 8px 16px; border: 1px solid #016d86; background: white; color: #016d86; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.2s;">
                                Sí
                            </button>
                            <button type="button" id="cambio-puerto-no" class="cambio-puerto-btn" style="flex: 1; padding: 8px 16px; border: 1px solid #d1d5db; background: white; color: #6b7280; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.2s;">
                                No
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Cupón -->
                <div style="background: white; border: 2px solid #e5e7eb; border-radius: 12px; overflow: hidden;">
                    <button type="button" id="toggle-cupon" style="width: 100%; padding: 20px; background: white; border: none; display: flex; justify-content: space-between; align-items: center; cursor: pointer; text-align: left;">
                        <div>
                            <div style="font-size: 18px; font-weight: 700; color: #1f2937;">¿Tienes un cupón?</div>
                            <div style="font-size: 14px; color: #6b7280; margin-top: 4px;">Aplica tu descuento aquí</div>
                        </div>
                        <i class="fa-solid fa-chevron-down" id="cupon-icon" style="color: #6b7280; font-size: 18px; transition: transform 0.3s;"></i>
                    </button>
                    <div id="cupon-content" style="display: none; padding: 20px; border-top: 1px solid #e5e7eb;">
                        <div style="display: flex; gap: 8px;">
                            <input type="text" id="coupon_code" name="coupon_code" placeholder="Escribe tu código" style="flex: 1; padding: 12px; border: 2px solid #e5e7eb; border-radius: 6px; font-size: 15px;">
                            <button type="button" id="apply-coupon" style="padding: 12px 24px; background: #016d86; color: white; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 15px;">
                                Aplicar
                            </button>
                        </div>
                        <p id="coupon-message" style="margin: 12px 0 0 0; font-size: 14px;"></p>
                    </div>
                </div>

                <!-- Botón volver al paso 1 -->
                <div style="background: #f9fafb; border: 2px dashed #d1d5db; border-radius: 8px; padding: 16px; text-align: center;">
                    <p style="margin: 0 0 12px 0; font-size: 14px; color: #6b7280;">¿Necesitas cambiar algo?</p>
                    <button type="button" id="volver-precio-step1" style="width: 100%; padding: 12px 20px; background: white; border: 2px solid #016d86; border-radius: 8px; color: #016d86; font-size: 15px; font-weight: 600; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: center; gap: 10px;">
                        <i class="fa-solid fa-arrow-left"></i>
                        Modificar opciones de pago
                    </button>
                </div>

            </div>

        </div> <!-- Fin page-precio -->
            <!-- Ya no necesitamos el modal, ahora es inline -->
            <!-- 
            <div id="info-popup" class="info-modal">
                <div class="info-modal-content">
                    <div class="info-modal-header">
                        <h3>Detalle del cálculo del ITP</h3>
                        <button class="close-modal">&times;</button>
                    </div>
                    <div class="info-modal-body">
                        <p class="info-description">El <strong>Impuesto sobre Transmisiones Patrimoniales (ITP)</strong> es un tributo que el comprador debe abonar a Hacienda en los cambios de titularidad de un vehículo entre particulares.</p>
                        
                        <div class="calculation-detail">
                            <div class="calculation-item">
                                <span>Valor fiscal base:</span>
                                <span id="base_value_display">0 €</span>
                            </div>
                            <div class="calculation-item">
                                <span>Antigüedad del vehículo:</span>
                                <span id="vehicle_age_display">0 años</span>
                            </div>
                            <div class="calculation-item">
                                <span>Porcentaje de depreciación:</span>
                                <span id="depreciation_percentage_display">0 %</span>
                            </div>
                            <div class="calculation-item">
                                <span>Valor fiscal con depreciación:</span>
                                <span id="fiscal_value_display">0 €</span>
                            </div>
                            <div class="calculation-item">
                                <span>Precio de compra declarado:</span>
                                <span id="purchase_price_display">0 €</span>
                            </div>
                            <div class="calculation-item">
                                <span>Base imponible (mayor valor):</span>
                                <span id="tax_base_display">0 €</span>
                            </div>
                            <div class="calculation-item">
                                <span>Tipo impositivo aplicado:</span>
                                <span id="tax_rate_display">0 %</span>
                            </div>
                            
                            <div class="calculation-result">
                                <span>ITP a pagar:</span>
                                <span id="calculated_itp_display">0 €</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            -->

        <!-- Página Documentos - FLUJO EN 2 PASOS -->
        <div id="page-documentos" class="form-page form-section-compact hidden">

            <!-- PASO 1: DOCUMENTOS -->
            <div id="documentos-step-1" class="documentos-step">
                <div class="upload-grid" style="display: flex; flex-direction: column; gap: 12px; margin-bottom: 16px;">
                    <!-- Fila 1 -->
                    <div class="upload-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <div class="upload-item">
                            <label id="label-hoja-asiento" for="upload-hoja-asiento">
                                <strong style="display: block; margin-bottom: 2px; font-size: 14px;">📄 Registro marítimo</strong>
                                <small style="display: block; color: #6b7280; margin-bottom: 6px; font-size: 12px;">Documento de la moto</small>
                            </label>
                            <div class="upload-wrapper">
                                <input type="file" id="upload-hoja-asiento" name="upload_hoja_asiento[]" multiple required accept="image/*,.pdf">
                                <div class="upload-button upload-button-responsive"><span class="desktop-text"><i class="fa-solid fa-upload"></i> Seleccionar archivo</span><span class="mobile-text"><i class="fa-solid fa-camera"></i> Hacer foto o seleccionar</span></div>
                                <div class="file-count" data-input="upload-hoja-asiento">Ningún archivo seleccionado</div>
                            </div>
                            <div class="files-preview" id="preview-upload-hoja-asiento"></div>
                            <a href="#" class="view-example" id="view-example-hoja-asiento" data-doc="hoja-asiento" style="font-size: 12px;">Ver ejemplo</a>
                        </div>
                        <div class="upload-item">
                            <label id="label-dni-comprador" for="upload-dni-comprador">
                                <strong style="display: block; margin-bottom: 2px; font-size: 14px;">🪪 DNI del comprador <span class="label-hint">(ambas caras)</span></strong>
                                <small style="display: block; color: #6b7280; margin-bottom: 6px; font-size: 12px;">Delante y detrás del DNI</small>
                            </label>
                            <div class="upload-wrapper">
                                <input type="file" id="upload-dni-comprador" name="upload_dni_comprador[]" multiple required accept="image/*,.pdf">
                                <div class="upload-button upload-button-responsive"><span class="desktop-text"><i class="fa-solid fa-upload"></i> Seleccionar archivo</span><span class="mobile-text"><i class="fa-solid fa-camera"></i> Hacer foto o seleccionar</span></div>
                                <div class="file-count" data-input="upload-dni-comprador">Ningún archivo seleccionado</div>
                            </div>
                            <div class="files-preview" id="preview-upload-dni-comprador"></div>
                            <a href="#" class="view-example" data-doc="dni-comprador" style="font-size: 12px;">Ver ejemplo</a>
                        </div>
                    </div>

                    <!-- Fila 2 -->
                    <div class="upload-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <div class="upload-item">
                            <label id="label-dni-vendedor" for="upload-dni-vendedor">
                                <strong style="display: block; margin-bottom: 2px; font-size: 14px;">🪪 DNI del vendedor <span class="label-hint">(ambas caras)</span></strong>
                                <small style="display: block; color: #6b7280; margin-bottom: 6px; font-size: 12px;">Delante y detrás del DNI</small>
                            </label>
                            <div class="upload-wrapper">
                                <input type="file" id="upload-dni-vendedor" name="upload_dni_vendedor[]" multiple required accept="image/*,.pdf">
                                <div class="upload-button upload-button-responsive"><span class="desktop-text"><i class="fa-solid fa-upload"></i> Seleccionar archivo</span><span class="mobile-text"><i class="fa-solid fa-camera"></i> Hacer foto o seleccionar</span></div>
                                <div class="file-count" data-input="upload-dni-vendedor">Ningún archivo seleccionado</div>
                            </div>
                            <div class="files-preview" id="preview-upload-dni-vendedor"></div>
                            <a href="#" class="view-example" data-doc="dni-vendedor" style="font-size: 12px;">Ver ejemplo</a>
                        </div>
                        <div class="upload-item">
                            <label id="label-contrato-compraventa" for="upload-contrato-compraventa">
                                <strong style="display: block; margin-bottom: 2px; font-size: 14px;">📝 Contrato de compraventa</strong>
                                <small style="display: block; color: #6b7280; margin-bottom: 6px; font-size: 12px;">Contrato firmado por ambas partes</small>
                            </label>
                            <div class="upload-wrapper">
                                <input type="file" id="upload-contrato-compraventa" name="upload_contrato_compraventa[]" multiple required accept="image/*,.pdf">
                                <div class="upload-button upload-button-responsive"><span class="desktop-text"><i class="fa-solid fa-upload"></i> Seleccionar archivo</span><span class="mobile-text"><i class="fa-solid fa-camera"></i> Hacer foto o seleccionar</span></div>
                                <div class="file-count" data-input="upload-contrato-compraventa">Ningún archivo seleccionado</div>
                            </div>
                            <div class="files-preview" id="preview-upload-contrato-compraventa"></div>
                            <a href="#" class="view-example" data-doc="contrato-compraventa" style="font-size: 12px;">Ver ejemplo</a>
                        </div>
                    </div>

                    <!-- Fila adicional para el MODELO 620 (solo cuando ITP ya pagado) -->
                    <div class="upload-row" id="itp-payment-proof-row" style="display: none; grid-template-columns: 1fr; gap: 12px;">
                        <div class="upload-item" style="background: #f0f9ff; border: 2px solid #3b82f6; border-radius: 8px; padding: 14px;">
                            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px;">
                                <i class="fa-solid fa-file-invoice" style="color: #3b82f6; font-size: 18px;"></i>
                                <div>
                                    <strong style="display: block; font-size: 14px; color: #1e40af; margin-bottom: 2px;">📄 Modelo 620 - Justificante ITP</strong>
                                    <small style="color: #6b7280; font-size: 12px;">Documento con justificante de pago a Hacienda</small>
                                </div>
                            </div>
                            <div class="upload-wrapper">
                                <input type="file" id="upload-itp-comprobante" name="upload_itp_comprobante[]" multiple accept="image/*,.pdf">
                                <div class="upload-button upload-button-responsive"><span class="desktop-text"><i class="fa-solid fa-upload"></i> Seleccionar archivo</span><span class="mobile-text"><i class="fa-solid fa-camera"></i> Hacer foto o seleccionar</span></div>
                                <div class="file-count" data-input="upload-itp-comprobante">Ningún archivo seleccionado</div>
                            </div>
                            <div class="files-preview" id="preview-upload-itp-comprobante"></div>
                            <div style="background: #eff6ff; border: 1px solid #dbeafe; border-radius: 6px; padding: 8px; margin-top: 8px;">
                                <p style="margin: 0; font-size: 11px; color: #1e40af; line-height: 1.3;">
                                    <strong>💡</strong> Justificante oficial con sello de Hacienda.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Confirmación de documentación completa - COMPACTO -->
                <div class="docs-confirmation-container" style="margin-top: 12px; margin-bottom: 0px; padding: 14px 16px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; border-left: 4px solid #3b82f6;">
                    <label class="custom-checkbox" style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                        <input type="checkbox" name="documents_complete" id="documents-complete-check" required style="width: 16px; height: 16px;">
                        <span class="checkbox-text" style="flex: 1; font-size: 13px; color: #374151; line-height: 1.4; font-weight: 500;">
                            ✅ Confirmo que he adjuntado toda la documentación necesaria
                        </span>
                    </label>
                </div>

                <!-- Sección de firma simple (oculta inicialmente) -->
                <div id="simple-signature-section" style="display: none; opacity: 0; transform: translateY(-10px); transition: all 0.3s ease; margin-top: 30px; padding: 30px; background: #f8f9fa; border: 2px solid #016d86; border-radius: 8px; text-align: center;">
                    <h2 style="margin: 0 0 8px 0; color: #016d86; font-size: 20px; font-weight: 600;">
                        ✍️ Firma del Documento
                    </h2>
                    <p style="margin: 0 0 25px 0; color: #666; font-size: 15px; line-height: 1.4;">
                        Firma en el área de abajo para autorizar el trámite.<br>
                        <small style="color: #888;">Revisa el documento de autorización en el panel lateral</small>
                    </p>
                    
                    <!-- Canvas de firma -->
                    <div style="position: relative; width: 100%; height: 200px; border: 2px solid #016d86; border-radius: 8px; background: white; margin-bottom: 15px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                        <span id="signature-label-simple" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: #9ca3af; font-size: 16px; pointer-events: none; user-select: none; z-index: 1; font-style: italic;">
                            Firme aquí
                        </span>
                        <canvas id="signature-pad-simple" style="border-radius: 6px; background: transparent; cursor: crosshair; display: block; width: 100%; height: 100%; touch-action: none;"></canvas>
                        <!-- Línea de firma -->
                        <div style="position: absolute; bottom: 40px; left: 50%; transform: translateX(-50%); width: 70%; height: 1px; background: linear-gradient(to right, transparent, #d1d5db, transparent); pointer-events: none; z-index: 0;"></div>
                    </div>
                    
                    <!-- Botones -->
                    <div style="display: flex; justify-content: center; gap: 15px; flex-wrap: wrap;">
                        <button type="button" id="volver-documentos" style="padding: 10px 20px; background: transparent; color: #6b7280; border: 2px solid #d1d5db; border-radius: 6px; font-size: 14px; cursor: pointer; transition: all 0.2s;">
                            ← Volver a Documentos
                        </button>
                        <button type="button" id="clear-signature-simple" style="padding: 10px 20px; background: #6b7280; color: white; border: none; border-radius: 6px; font-size: 14px; cursor: pointer; transition: all 0.2s;">
                            Limpiar Firma
                        </button>
                    </div>
                </div>
            </div>
            </div> <!-- Fin documentos-step-1 -->

            <!-- Contenedor para botones de navegación (se moverán aquí dinámicamente) -->
            <div id="documentos-buttons-container"></div>

        </div> <!-- Fin page-documentos -->



        <!-- Página Pago -->
        <div id="page-pago" class="form-page form-section-compact hidden">
            <h2 style="margin-bottom: 10px;">Método de Pago</h2>
            <p style="color: #666; margin-bottom: 25px; font-size: 15px; line-height: 1.6;">Pago seguro con Stripe. Procesamos tu trámite inmediatamente tras la confirmación del pago.</p>

            <!-- Elemento de pago de Stripe directamente en el formulario -->
            <div id="stripe-container" style="max-width: 100%; margin: 0 auto;">
                <!-- Spinner de carga mientras se inicializa -->
                <div id="stripe-loading" style="text-align: center; padding: 40px;">
                    <div class="stripe-spinner" style="margin: 0 auto 20px;"></div>
                    <p style="color: #666;">Cargando sistema de pago seguro...</p>
                </div>

                <!-- Contenedor donde se montará el elemento de pago -->
                <div id="payment-element" class="payment-element-container" style="margin-bottom: 30px;"></div>

                <!-- Términos y condiciones -->
                <div class="terms-container payment-terms" style="margin: 30px 0; text-align: center; padding: 20px; border: 2px solid rgba(var(--primary), 0.3); border-radius: var(--radius-md); background-color: rgba(var(--primary), 0.05);">
                    <label style="display: flex; align-items: center; justify-content: center; gap: 12px; font-weight: 500; cursor: pointer;">
                        <div class="custom-checkbox-container" style="position: relative; width: 18px; height: 18px;">
                            <input type="checkbox" id="terms_accept_pago" name="terms_accept_pago" required style="position: absolute; opacity: 0; cursor: pointer; height: 0; width: 0;">
                            <div class="checkmark-box" style="position: absolute; top: 0; left: 0; height: 18px; width: 18px; background-color: white; border: 1.5px solid rgb(var(--primary)); border-radius: 3px; transition: all 0.2s ease;"></div>
                            <div class="checkmark" style="position: absolute; top: 0; left: 0; height: 18px; width: 18px; display: none; z-index: 2;">
                                <i class="fa-solid fa-check" style="position: absolute; top: 1px; left: 3px; color: white; font-size: 12px;"></i>
                            </div>
                        </div>
                        <span>Acepto los <a href="https://tramitfy.es/terminos-y-condiciones-de-uso/" target="_blank" style="font-weight: 600; text-decoration: underline; color: rgb(var(--primary-dark));">términos y condiciones de pago</a></span>
                    </label>
                </div>

                <style>
                    /* Estilos mejorados para el checkbox personalizado */
                    .custom-checkbox-container input:checked ~ .checkmark-box {
                        background-color: rgb(var(--primary)) !important;
                    }

                    .custom-checkbox-container input:checked ~ .checkmark {
                        display: block !important;
                        z-index: 5 !important;
                    }

                    /* Usar !important para forzar la visualización */
                    .custom-checkbox-container input:checked + .checkmark,
                    .custom-checkbox-container input:checked ~ .checkmark {
                        display: block !important;
                    }

                    .custom-checkbox-container input:focus ~ .checkmark-box {
                        box-shadow: 0 0 0 2px rgba(var(--primary), 0.3);
                    }

                    .custom-checkbox-container .checkmark-box:hover {
                        border-color: rgb(var(--primary-dark));
                    }

                    /* Estilos para el spinner de carga de Stripe */
                    .stripe-spinner {
                        border: 4px solid #f3f3f3;
                        border-top: 4px solid #4f46e5;
                        border-radius: 50%;
                        width: 40px;
                        height: 40px;
                        animation: spin 1s linear infinite;
                    }

                    @keyframes spin {
                        0% { transform: rotate(0deg); }
                        100% { transform: rotate(360deg); }
                    }
                </style>

                <!-- Indicadores de seguridad -->
                <div class="payment-security" style="margin: 20px 0; text-align: center; padding: 15px; background-color: #f8f9fa; border-radius: 8px;">
                    <div class="security-badges" style="display: flex; justify-content: center; align-items: center; gap: 20px; flex-wrap: wrap;">
                        <div class="security-badge" style="display: flex; align-items: center; gap: 8px; font-size: 14px; color: #4b5563;">
                            <i class="fa-solid fa-lock" style="color: #10b981;"></i>
                            <span>Pago Seguro</span>
                        </div>
                        <div class="security-badge" style="display: flex; align-items: center; gap: 8px; font-size: 14px; color: #4b5563;">
                            <i class="fa-solid fa-shield-alt" style="color: #10b981;"></i>
                            <span>Datos Encriptados</span>
                        </div>
                        <div class="security-badge" style="display: flex; align-items: center; gap: 8px; font-size: 14px; color: #4b5563;">
                            <i class="fa-brands fa-stripe" style="color: #635bff;"></i>
                            <span>Powered by Stripe</span>
                        </div>
                    </div>
                </div>

                <!-- Mensajes de estado del pago -->
                <div id="payment-message" class="hidden" style="margin: 20px 0; padding: 15px; border-radius: 8px; text-align: center; font-weight: 500;"></div>

                <!-- Botón de pago -->
                <button type="button" id="submit-payment" class="btn-primary" style="width: 100%; padding: 16px; font-size: 18px; font-weight: 600; background: linear-gradient(135deg, #016d86 0%, #015266 100%); color: white; border: none; border-radius: 8px; cursor: pointer; margin-top: 20px; transition: all 0.3s ease; box-shadow: 0 4px 6px -1px rgba(1, 109, 134, 0.3), 0 2px 4px -1px rgba(1, 109, 134, 0.2);">
                    <i class="fa-solid fa-lock"></i> Pagar Ahora
                </button>

                <style>
                    #submit-payment:hover {
                        background: linear-gradient(135deg, #015266 0%, #013d4d 100%);
                        box-shadow: 0 6px 8px -1px rgba(1, 109, 134, 0.4), 0 4px 6px -1px rgba(1, 109, 134, 0.3);
                        transform: translateY(-1px);
                    }
                    #submit-payment:active {
                        transform: translateY(0);
                        box-shadow: 0 2px 4px -1px rgba(1, 109, 134, 0.3);
                    }
                    #submit-payment:disabled {
                        background: #9ca3af;
                        cursor: not-allowed;
                        box-shadow: none;
                    }
                </style>
            </div>

            <!-- Contenedor para botones de navegación (se moverán aquí dinámicamente) -->
            <div id="pago-buttons-container"></div>
        </div> <!-- Fin page-pago -->

        <!-- Campos ocultos para enviar TASAS, IVA y HONORARIOS exactos -->
        <input type="hidden" name="tasas_hidden" id="tasas_hidden" />
        <input type="hidden" name="iva_hidden" id="iva_hidden" />
        <input type="hidden" name="honorarios_hidden" id="honorarios_hidden" />
        
        <!-- Campos ocultos para ITP -->
        <input type="hidden" name="itp_management_option" id="itp_management_option" />
        <input type="hidden" name="itp_payment_method" id="itp_payment_method" />
        <input type="hidden" name="itp_amount" id="itp_amount" />
        <input type="hidden" name="itp_commission" id="itp_commission" />
        <input type="hidden" name="itp_total_amount" id="itp_total_amount" />

        <!-- Botones de navegación (posición original dentro del grid) -->
        <div class="button-container">
            <button type="button" class="button" id="prevButton">Anterior</button>
            <button type="button" class="button" id="nextButton">Siguiente</button>
        </div>

                </div> <!-- Fin .tramitfy-main-form -->
            </div> <!-- Fin .tramitfy-two-column -->
        </div> <!-- Fin .tramitfy-layout-wrapper -->

    </form>

    <!-- Popup para ejemplos de documentos -->
    <div id="document-popup">
        <div class="popup-content">
            <span class="close-popup">&times;</span>
            <h3>Ejemplo de documento</h3>
            <img id="document-example-image" src="" alt="Ejemplo de documento">
        </div>
    </div>
    
    <!-- Cargar las mejoras de la experiencia de pago -->
    <?php include_once(dirname(__FILE__) . '/load-payment-enhancements.html'); ?>

    <!-- JavaScript para la lógica del formulario -->
    <script>
    // ============================================
    // SISTEMA DE LOGGING AVANZADO PARA F12
    // ============================================
    const TRAMITFY_DEBUG = true; // Cambiar a false en producción

    const LOG_LEVELS = {
        DEBUG: { color: '#6b7280', emoji: '🔍', enabled: TRAMITFY_DEBUG },
        INFO: { color: '#3b82f6', emoji: 'ℹ️', enabled: true },
        SUCCESS: { color: '#10b981', emoji: '✅', enabled: true },
        WARNING: { color: '#f59e0b', emoji: '⚠️', enabled: true },
        ERROR: { color: '#ef4444', emoji: '❌', enabled: true },
        CRITICAL: { color: '#dc2626', emoji: '🔥', enabled: true }
    };

    // Función de logging principal
    function log(level, context, message, data = null) {
        const config = LOG_LEVELS[level];
        if (!config || !config.enabled) return;

        const timestamp = new Date().toLocaleTimeString('es-ES', { hour12: false, hour: '2-digit', minute: '2-digit', second: '2-digit', fractionalSecondDigits: 3 });
        const prefix = `${config.emoji} [${timestamp}] [${level}] [${context}]`;
        const styles = `color: ${config.color}; font-weight: bold; font-size: 11px;`;

        if (data !== null && typeof data === 'object') {
            console.groupCollapsed(`%c${prefix} ${message}`, styles);
            console.log('📦 Datos:', data);
            console.log('🕐 Timestamp:', new Date().toISOString());
            if (level === 'ERROR' || level === 'CRITICAL') {
                console.trace('📍 Stack trace');
            }
            console.groupEnd();
        } else if (data !== null) {
            console.log(`%c${prefix} ${message}`, styles, data);
        } else {
            console.log(`%c${prefix} ${message}`, styles);
        }

        // Guardar en array para debug posterior
        if (!window.tramitfyLogs) window.tramitfyLogs = [];
        window.tramitfyLogs.push({ timestamp, level, context, message, data });
    }

    // Atajos convenientes
    const logDebug = (ctx, msg, data) => log('DEBUG', ctx, msg, data);
    const logInfo = (ctx, msg, data) => log('INFO', ctx, msg, data);
    const logSuccess = (ctx, msg, data) => log('SUCCESS', ctx, msg, data);
    const logWarning = (ctx, msg, data) => log('WARNING', ctx, msg, data);
    const logError = (ctx, msg, data) => log('ERROR', ctx, msg, data);
    const logCritical = (ctx, msg, data) => log('CRITICAL', ctx, msg, data);

    // Monitor de performance
    const perfMarks = {};
    function perfStart(label) {
        perfMarks[label] = performance.now();
        logDebug('PERF', `⏱️ Inicio medición: ${label}`);
    }
    function perfEnd(label) {
        if (perfMarks[label]) {
            const duration = (performance.now() - perfMarks[label]).toFixed(2);
            const color = duration < 100 ? 'SUCCESS' : duration < 500 ? 'WARNING' : 'ERROR';
            log(color, 'PERF', `⏱️ ${label}: ${duration}ms`);
            delete perfMarks[label];
            return parseFloat(duration);
        }
    }

    // Helper para exportar logs
    window.exportTramitfyLogs = function() {
        const logs = window.tramitfyLogs || [];
        const blob = new Blob([JSON.stringify(logs, null, 2)], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `tramitfy-logs-${new Date().toISOString()}.json`;
        a.click();
        logSuccess('LOGS', `📥 Exportados ${logs.length} logs`);
    };

    // Inicialización del sistema
    logInfo('INIT', '========== TRAMITFY BARCO FORM v1.11 (Navigation Buttons Fix) ==========');
    logInfo('INIT', `🌐 User Agent: ${navigator.userAgent.substring(0, 100)}...`);
    logInfo('INIT', `📱 Viewport: ${window.innerWidth}x${window.innerHeight}`);
    logInfo('INIT', `🔗 URL: ${window.location.href}`);
    logInfo('INIT', `⏰ Timestamp: ${new Date().toISOString()}`);
    logDebug('INIT', '🚀 Sistema de logging inicializado correctamente');

    // Sistema de logging persistente
    window.tramitfyLogs = [];
    
    function persistentLog(message, type = 'info') {
        const timestamp = new Date().toISOString();
        const logEntry = {
            timestamp,
            message,
            type,
            url: window.location.href
        };
        
        // Agregar a array en memoria
        window.tramitfyLogs.push(logEntry);
        
        // Guardar en localStorage
        localStorage.setItem('tramitfy_debug_logs', JSON.stringify(window.tramitfyLogs));
        
        // También mostrar en console normal
        console.log(`[${timestamp}] ${message}`);
        
        // Enviar al servidor de forma asíncrona (sin bloquear)
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=tpb_log_debug&message=${encodeURIComponent(message)}&type=${type}&timestamp=${timestamp}`
        }).catch(() => {}); // Ignorar errores silenciosamente
    }
    
    // Función para mostrar logs guardados (para debug)
    window.showTramitfyLogs = function() {
        const logs = JSON.parse(localStorage.getItem('tramitfy_debug_logs') || '[]');
        console.log('📋 LOGS GUARDADOS DE TRAMITFY:', logs);
        return logs;
    };
    
    // Función para limpiar logs
    window.clearTramitfyLogs = function() {
        localStorage.removeItem('tramitfy_debug_logs');
        window.tramitfyLogs = [];
        console.log('🧹 Logs limpiados');
    };

    document.addEventListener('DOMContentLoaded', function() {
        persistentLog('🚀 DOMContentLoaded ejecutado - Sistema de logging iniciado');
        logDebug('DOM', '✅ DOMContentLoaded ejecutado');
        
        // CRÍTICO: Reset completo del layout al cargar la página
        const mainForm = document.querySelector('.tramitfy-main-form');
        const sidebar = document.querySelector('.tramitfy-sidebar');
        if (mainForm && sidebar) {
            mainForm.style.gridColumn = '';
            sidebar.style.display = 'flex';
            sidebar.style.width = '';
            sidebar.style.minWidth = '';
            sidebar.style.background = '';
            sidebar.style.boxShadow = '';
            console.log('🔧 [DOMContentLoaded] Layout grid completamente reseteado');
        }

        // IMPORTANTE: Limpiar documento de autorización al navegar a cualquier página
        document.addEventListener('click', function(e) {
            const navButton = e.target.closest('[data-page]');
            if (navButton) {
                // Se está navegando a otra página, limpiar documento de autorización
                setTimeout(() => {
                    limpiarDocumentoAutorizacion();
                }, 100);
            }
        });

        // Variables globales y configuración
        const itpRates = {
            "Andalucía": 0.04, "Aragón": 0.04, "Asturias": 0.04, "Islas Baleares": 0.04,
            "Canarias": 0.055, "Cantabria": 0.08, "Castilla-La Mancha": 0.06, "Castilla y León": 0.05,
            "Cataluña": 0.05, "Comunidad Valenciana": 0.08, "Galicia": 0.03,
            "Madrid": 0.04, "Murcia": 0.04, "Navarra": 0.04, "País Vasco": 0.04,
            "La Rioja": 0.04, "Ceuta": 0.02, "Melilla": 0.04
        };

        // Regiones con trámites especiales para embarcaciones
        const regionesEspeciales = {
            "Comunidad Valenciana": {
                tasaAdicional: 45.00, // Tasa adicional específica para Valencia
                requiereDocumentacion: ["certificado_navegabilidad", "seguro_embarcacion"]
            },
            "Andalucía": {
                descuentoFamiliarNumerosa: 0.5, // 50% descuento si familia numerosa
                requiereDocumentacion: ["certificado_andalucia"]
            },
            "Asturias": {
                bonificacionJoven: 0.25, // 25% descuento si menor de 30 años
                requiereDocumentacion: ["certificado_empadronamiento"]
            }
        };
        
        // Tabla oficial BOE 2024 - Columna "A motor y MN" (Motores y Motos Náuticas)
        const depreciationRates = [
            { years: 1, rate: 100 },  // Hasta 1 año
            { years: 2, rate: 85 },   // Más de 1, hasta 2
            { years: 3, rate: 72 },   // Más de 2, hasta 3
            { years: 4, rate: 61 },   // Más de 3, hasta 4
            { years: 5, rate: 52 },   // Más de 4, hasta 5
            { years: 6, rate: 44 },   // Más de 5, hasta 6
            { years: 7, rate: 37 },   // Más de 6, hasta 7
            { years: 8, rate: 32 },   // Más de 7, hasta 8
            { years: 9, rate: 27 },   // Más de 8, hasta 9
            { years: 10, rate: 23 },  // Más de 9, hasta 10
            { years: 11, rate: 19 },  // Más de 10, hasta 11
            { years: 12, rate: 16 },  // Más de 11, hasta 12
            { years: 13, rate: 14 },  // Más de 12, hasta 13
            { years: 14, rate: 12 },  // Más de 13, hasta 14
            { years: 15, rate: 10 }   // Más de 14 años
        ];
        
        const BASE_TRANSFER_PRICE_SIN_ITP = 134.99;
        const BASE_TRANSFER_PRICE_CON_ITP = 174.99;

        let basePrice = 0;
        let currentTransferTax = 0;
        let currentExtraFee = 0;
        let paymentCompleted = false;
        let currentPage = 0;
        let couponDiscountPercent = 0;
        let gestionamosITP = false; // true si seleccionan "Lo gestionan ustedes"
        let couponValue = "";
        let stripe;
        let elements;
        let finalAmount = BASE_TRANSFER_PRICE_SIN_ITP; // Inicializar con precio base mínimo
        let purchaseDetails = {};

        // Variables del flujo de precio (necesarias globalmente para actualizarSidebarPrecio)
        let itpPagado = null; // null, true (sí pagado), false (no pagado)
        let precioStep = 1; // 1 o 2
        let cambioListaSeleccionado = false; // Para el servicio de cambio de lista
        const PRECIO_CAMBIO_LISTA = 64.95;
        let cambioNombreSeleccionado = false; // Para el servicio de cambio de nombre
        const PRECIO_CAMBIO_NOMBRE = 40.00;
        let cambioPuertoSeleccionado = false; // Para el servicio de cambio de puerto
        const PRECIO_CAMBIO_PUERTO = 40.00;
        let itpGestionSeleccionada = null; // 'yo-pago' o 'gestionan-ustedes'
        let itpMetodoPago = null; // 'tarjeta' o 'transferencia'
        let itpBaseAmount = 0;
        let itpComisionTarjeta = 0;
        let itpTotalAmount = 0;

        // Referencias a elementos del DOM
        const formPages = document.querySelectorAll('.form-page');
        const navLinks = document.querySelectorAll('.nav-link');
        const prevButton = document.getElementById('prevButton');
        const nextButton = document.getElementById('nextButton');
        
        persistentLog('🔍 ELEMENTOS DEL FORMULARIO verificados');
        persistentLog(`prevButton encontrado: ${!!prevButton}`);
        persistentLog(`nextButton encontrado: ${!!nextButton}`);
        
        if (!nextButton) {
            persistentLog('🚨 ERROR CRÍTICO: nextButton no encontrado en el DOM!', 'error');
        }
        const purchasePriceInput = document.getElementById('purchase_price');
        const regionSelect = document.getElementById('region');
        const transferTaxDisplay = document.getElementById('transfer_tax_display');
        const extraOptions = document.querySelectorAll('.extra-option');
        const manufacturerSelect = document.getElementById('manufacturer');
        const modelSelect = document.getElementById('model');
        const vehicleCsvSection = document.getElementById('vehicle-csv-section');
        const noEncuentroCheckbox = document.getElementById('no_encuentro_checkbox');
        const manualFields = document.getElementById('manual-fields');

        // Log de verificación de elementos clave
        logDebug('INIT', 'Elementos del vehículo:', {
            manufacturerSelect: !!manufacturerSelect,
            modelSelect: !!modelSelect,
            vehicleCsvSection: !!vehicleCsvSection,
            noEncuentroCheckbox: !!noEncuentroCheckbox
        });

        const extraFeeIncludesDisplay = document.getElementById('extra_fee_includes_display');
        const cambioNombrePriceDisplay = document.getElementById('cambio_nombre_price');
        const infoPopup = document.getElementById('info-popup');
        const matriculationDateInput = document.getElementById('matriculation_date');
        const matriculationDateLabel = document.getElementById('matriculation_date_label');

        const baseValueDisplay = document.getElementById('base_value_display');
        const depreciationPercentageDisplay = document.getElementById('depreciation_percentage_display');
        const fiscalValueDisplay = document.getElementById('fiscal_value_display');
        const calculatedItpDisplay = document.getElementById('calculated_itp_display');
        const vehicleAgeDisplay = document.getElementById('vehicle_age_display');
        const purchasePriceDisplay = document.getElementById('purchase_price_display');
        const taxBaseDisplay = document.getElementById('tax_base_display');
        const taxRateDisplay = document.getElementById('tax_rate_display');
        const couponCodeInput = document.getElementById('coupon_code');
        const couponMessage = document.getElementById('coupon-message');
        const discountLi = document.getElementById('discount-li');
        const discountAmountEl = document.getElementById('discount-amount');
        const finalAmountEl = document.getElementById('final-amount');
        const finalSummaryAmountEl = document.getElementById('final-summary-amount');

        const customerNameInput = document.getElementById('customer_name');
        const customerDniInput = document.getElementById('customer_dni');
        const customerEmailInput = document.getElementById('customer_email');
        const customerPhoneInput = document.getElementById('customer_phone');

        let signaturePad; // Variable mantenida por compatibilidad
        const signatureCanvas = null; // Canvas viejo eliminado - usar signaturePadForm

        // Manejo de múltiples archivos en inputs
        function setupMultipleFileInputs() {
            const fileInputs = document.querySelectorAll('input[type="file"][multiple]');
            const filesMap = new Map();

            fileInputs.forEach(input => {
                filesMap.set(input.id, []);

                input.addEventListener('change', function() {
                    const newFiles = Array.from(this.files);
                    const inputId = this.id;

                    newFiles.forEach(file => {
                        if (!filesMap.get(inputId).some(f => f.name === file.name && f.size === file.size)) {
                            filesMap.get(inputId).push(file);
                        }
                    });

                    updateFileDisplay(inputId, input);
                });
            });

            function updateFileDisplay(inputId, inputElement) {
                const files = filesMap.get(inputId);
                const fileCountEl = inputElement.parentElement.querySelector('.file-count');
                const previewContainer = document.getElementById(`preview-${inputId}`);
                const uploadWrapper = inputElement.parentElement;

                if (files.length > 0) {
                    if (files.length === 1) {
                        fileCountEl.textContent = `${files[0].name}`;
                    } else {
                        fileCountEl.textContent = `${files.length} archivos seleccionados`;
                    }

                    uploadWrapper.classList.add('file-uploaded');

                    if (previewContainer) {
                        previewContainer.innerHTML = '';
                        previewContainer.classList.add('active');

                        files.forEach((file, index) => {
                            const fileItem = document.createElement('div');
                            fileItem.className = 'file-preview-item';

                            const fileIcon = file.type.startsWith('image/')
                                ? '<i class="fa-solid fa-image"></i>'
                                : '<i class="fa-solid fa-file-pdf"></i>';

                            const fileSize = (file.size / 1024).toFixed(1) + ' KB';

                            fileItem.innerHTML = `
                                ${fileIcon}
                                <span class="file-name-text">${file.name}</span>
                                <span class="file-size">${fileSize}</span>
                                <button type="button" class="file-remove-btn" data-index="${index}" title="Eliminar">×</button>
                            `;

                            const removeBtn = fileItem.querySelector('.file-remove-btn');
                            removeBtn.addEventListener('click', function() {
                                const idx = parseInt(this.getAttribute('data-index'));
                                filesMap.get(inputId).splice(idx, 1);
                                updateFileDisplay(inputId, inputElement);
                                updateInputFiles(inputId, inputElement);
                            });

                            previewContainer.appendChild(fileItem);
                        });
                    }

                    updateInputFiles(inputId, inputElement);
                    
                    // 🔄 ACTUALIZAR SIDEBAR después de añadir archivos
                    const currentPageElement = document.querySelector('.form-page:not(.hidden)');
                    if (currentPageElement && currentPageElement.id === 'page-documentos') {
                        setTimeout(() => {
                            actualizarSidebarDinamico('page-documentos');
                        }, 50);
                    }
                } else {
                    fileCountEl.textContent = 'Ningún archivo seleccionado';
                    uploadWrapper.classList.remove('file-uploaded');
                    if (previewContainer) {
                        previewContainer.innerHTML = '';
                        previewContainer.classList.remove('active');
                    }
                }
                
                // 🔄 ACTUALIZAR SIDEBAR EN TIEMPO REAL
                // Solo actualizar si estamos en la página de documentos
                const currentPageElement = document.querySelector('.form-page:not(.hidden)');
                if (currentPageElement && currentPageElement.id === 'page-documentos') {
                    // Pequeño delay para asegurar que los archivos estén procesados
                    setTimeout(() => {
                        actualizarSidebarDinamico('page-documentos');
                    }, 50);
                }
            }

            function updateInputFiles(inputId, inputElement) {
                const files = filesMap.get(inputId);
                const dataTransfer = new DataTransfer();

                files.forEach(file => {
                    dataTransfer.items.add(file);
                });

                inputElement.files = dataTransfer.files;
            }
        }

        // Inicializar el manejo de archivos múltiples
        setupMultipleFileInputs();

        // Referencias para los nuevos elementos de navegación
        const gotoRequirementsBtn = document.getElementById('goto-requirements');
        const gotoPasosBtn = document.getElementById('goto-pasos');
        const startProcessBtn = document.getElementById('start-process');

        // Función de navegación del formulario
        function updateForm() {
            const isPrePage = false; // Ya no hay pre-páginas
            
            // CRÍTICO: Asegurar que el layout grid esté correcto al inicio
            const mainForm = document.querySelector('.tramitfy-main-form');
            const sidebar = document.querySelector('.tramitfy-sidebar');
            const twoColumn = document.querySelector('.tramitfy-two-column');
            if (mainForm && sidebar && twoColumn) {
                // Restaurar grid normal por defecto
                mainForm.style.gridColumn = '';
                sidebar.style.display = 'flex';
                twoColumn.style.gridTemplateColumns = ''; // RESTAURAR grid original
                console.log('🔧 [updateForm] Layout grid restaurado por defecto');
            }

            // Scroll al inicio del formulario solo en móvil (no en desktop)
            const isMobile = window.innerWidth <= 768;
            if (isMobile && mainForm) {
                const formTop = mainForm.getBoundingClientRect().top + window.pageYOffset - 20;
                window.scrollTo({ top: formTop, behavior: 'smooth' });
            } else if (isMobile) {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }

            document.getElementById('form-navigation').style.display = 'flex';

            formPages.forEach((page, index) => {
                page.classList.toggle('hidden', index !== currentPage);
            });

            // Restaurar layout normal si NO estamos en la página de firma
            const currentPageId = formPages[currentPage]?.id;
            const step2 = document.getElementById('documentos-step-2');
            const step2Display = step2 ? step2.style.display : 'elemento no existe';
            const step2Visible = step2 && step2.style.display !== 'none';
            const enPasoFirma = (currentPageId === 'page-documentos') && step2Visible;

            console.log('📄 [updateForm] Página actual:', currentPageId);
            console.log('   documentos-step-2 display:', step2Display);
            console.log('   step2Visible:', step2Visible);
            console.log('   ¿En paso firma?:', enPasoFirma);

            // Solo mantener layout especial si estamos EN documentos Y EN paso firma
            if (!enPasoFirma) {
                console.log('🔧 Llamando a restaurarLayoutNormal()');
                if (typeof restaurarLayoutNormal === 'function') {
                    restaurarLayoutNormal();
                } else {
                    console.error('❌ restaurarLayoutNormal no está definida');
                }
            } else {
                console.log('⏭️ No se restaura el layout (estamos en paso firma)');
                // NO inicializar SignaturePad aquí - se manejará en el flujo condicional
                console.log('🖊️ Página de firma - esperando confirmación de documentos...');
            }

            // Actualizar sidebar dinámico según la página activa SIEMPRE
            if (formPages[currentPage]) {
                const pageId = formPages[currentPage].id;
                console.log('🔍 [DEBUG] formPages[currentPage].id:', pageId);
                console.log('🔍 [DEBUG] currentPage index:', currentPage);
                console.log('🔍 [DEBUG] formPages length:', formPages.length);
                console.log('🔍 [DEBUG] formPages[currentPage]:', formPages[currentPage]);
                actualizarSidebarDinamico(pageId);
            }

            // Manejar posicionamiento de botones para páginas específicas
            const buttonContainer = document.querySelector('.button-container');
            if (buttonContainer && formPages[currentPage]) {
                const pageId = formPages[currentPage].id;
                
                if (pageId === 'page-vehiculo') {
                    const vehiculoContainer = document.getElementById('vehiculo-buttons-container');
                    if (vehiculoContainer) {
                        vehiculoContainer.appendChild(buttonContainer);
                        console.log('✅ Botones movidos a página vehículo');
                    }
                } else if (pageId === 'page-datos') {
                    const datosContainer = document.getElementById('datos-buttons-container');
                    if (datosContainer) {
                        datosContainer.appendChild(buttonContainer);
                        console.log('✅ Botones movidos a página datos');
                    }
                } else if (pageId === 'page-documentos') {
                    console.log("Página documentos cargada - v1.10 sin acordeones");
                    const documentosContainer = document.getElementById('documentos-buttons-container');
                    if (documentosContainer) {
                        documentosContainer.appendChild(buttonContainer);
                        console.log('✅ Botones movidos a página documentos');
                    }
                } else if (pageId === 'page-pago') {
                    const pagoContainer = document.getElementById('pago-buttons-container');
                    if (pagoContainer) {
                        pagoContainer.appendChild(buttonContainer);
                        console.log('✅ Botones movidos a página pago');
                    }
                } else {
                    // Para otras páginas (precio), usar posición original
                    const mainFormContainer = document.querySelector('.tramitfy-main-form');
                    if (mainFormContainer && buttonContainer.parentNode !== mainFormContainer) {
                        mainFormContainer.appendChild(buttonContainer);
                        console.log('✅ Botones en posición original para', pageId);
                    }
                }
            }

            // Si estamos pasando a la página de firma, inicializar estado inicial
            if (formPages[currentPage] && formPages[currentPage].id === 'page-firma') {
                console.log("Página firma cargada - inicializando estado inicial");
                
                // Actualizar sidebar según estado inicial (confirmación)
                actualizarSidebarDinamico('page-firma');
                
                // NO inicializar SignaturePad hasta que se confirmen los documentos
                // initializeSignaturePadForm() se llamará desde el event listener del checkbox
            }

            // Si estamos pasando a la página de precio, inicializar los acordeones de precio
            if (formPages[currentPage] && formPages[currentPage].id === 'page-precio') {
                console.log("Actualizando a página precio, inicializando acordeones de precio");
                setTimeout(() => {
                    try {
                        if (typeof initAdditionalOptionsDropdown === 'function') {
                            initAdditionalOptionsDropdown();
                        }
                    } catch (error) {
                        console.error('Error inicializando Additional Options Dropdown:', error);
                    }

                    try {
                        if (typeof initCouponDropdown === 'function') {
                            initCouponDropdown();
                        }
                    } catch (error) {
                        console.error('Error inicializando Coupon Dropdown:', error);
                    }

                    // Forzar actualización del cálculo ITP
                    try {
                        if (typeof window.actualizarCalculoITPStep1 === 'function') {
                            window.actualizarCalculoITPStep1();
                        }
                    } catch (error) {
                        console.error('Error actualizando cálculo ITP:', error);
                    }
                }, 100);
            }

            if (!isPrePage) {
                // Actualizar navegación y barra de progreso
                const navItems = document.querySelectorAll('.nav-item');
                const progressIndicator = document.querySelector('.nav-progress-indicator');
                let activeIndex = -1;
                
                navItems.forEach((item, index) => {
                    const pageId = item.getAttribute('data-page-id');
                    const isActive = pageId === formPages[currentPage].id;
                    
                    // Marcar el ítem activo
                    item.classList.toggle('active', isActive);
                    
                    // Marcar completados los ítems anteriores
                    item.classList.toggle('completed', !isActive && index < getNavItemIndex(formPages[currentPage].id));
                    
                    if (isActive) activeIndex = index;
                });
                
                // Actualizar la barra de progreso
                if (activeIndex >= 0) {
                    const progressPercentage = (activeIndex / (navItems.length - 1)) * 100;
                    progressIndicator.style.width = `${progressPercentage}%`;
                }
                
                const currentPageId = formPages[currentPage].id;
                const isPaymentPage = currentPageId === 'page-pago';
                const buttonContainer = document.querySelector('.button-container');

                // SOLO ocultar botones en página de pago (última página)
                if (isPaymentPage) {
                    // Usar setProperty con !important para forzar la ocultación
                    buttonContainer.style.setProperty('display', 'none', 'important');
                    buttonContainer.style.setProperty('visibility', 'hidden', 'important');
                    buttonContainer.style.setProperty('opacity', '0', 'important');
                    buttonContainer.style.setProperty('height', '0', 'important');
                    buttonContainer.style.setProperty('overflow', 'hidden', 'important');
                    buttonContainer.style.setProperty('pointer-events', 'none', 'important');
                } else {
                    // Mostrar botones y resetear propiedades
                    buttonContainer.style.setProperty('display', 'flex', 'important');
                    buttonContainer.style.setProperty('visibility', 'visible', 'important');
                    buttonContainer.style.setProperty('opacity', '1', 'important');
                    buttonContainer.style.setProperty('height', 'auto', 'important');
                    buttonContainer.style.setProperty('overflow', 'visible', 'important');
                    buttonContainer.style.setProperty('pointer-events', 'auto', 'important');
                    // Asegurar posición correcta abajo del formulario
                    // NO aplicar margin-top/padding-top si están en contenedores específicos
                    if (buttonContainer.closest('#documentos-buttons-container')) {
                        // Para documentos: botones casi tocando el checkbox
                        buttonContainer.style.setProperty('margin-top', '2px', 'important');
                        buttonContainer.style.setProperty('padding-top', '0px', 'important');
                    } else if (buttonContainer.closest('#pago-buttons-container')) {
                        // Para pago: espacio normal dentro del contenedor
                        buttonContainer.style.setProperty('margin-top', '20px', 'important');
                        buttonContainer.style.setProperty('padding-top', '0px', 'important');
                    } else {
                        // Para otras páginas: espaciado por defecto
                        buttonContainer.style.setProperty('margin-top', '36px', 'important');
                        buttonContainer.style.setProperty('padding-top', '24px', 'important');
                    }
                    buttonContainer.style.setProperty('position', 'relative', 'important');

                    // En primera página: solo mostrar "Siguiente", ocultar "Anterior"
                    if (currentPage === 0) {
                        prevButton.style.setProperty('display', 'none', 'important');
                        prevButton.style.setProperty('visibility', 'hidden', 'important');
                        // Alinear "Siguiente" a la derecha cuando está solo
                        buttonContainer.style.setProperty('justify-content', 'flex-end', 'important');
                    } else {
                        prevButton.style.setProperty('display', 'inline-block', 'important');
                        prevButton.style.setProperty('visibility', 'visible', 'important');
                        // Restaurar alineación normal cuando hay ambos botones
                        buttonContainer.style.setProperty('justify-content', 'space-between', 'important');
                    }

                    // Cambiar texto del botón siguiente
                    if (currentPage === formPages.length - 1) {
                        nextButton.textContent = 'Pagar';
                    } else {
                        nextButton.textContent = 'Siguiente';
                    }
                }
            } else {
                const buttonContainer = document.querySelector('.button-container');
                buttonContainer.style.setProperty('display', 'none', 'important');
                buttonContainer.style.setProperty('visibility', 'hidden', 'important');
            }
            
            if (formPages[currentPage].id === 'page-pago') {
                console.log('🎯 Navegando a página de pago');
                console.log('💰 Final Amount:', finalAmount);
                
                // LOGS CRÍTICOS: Estado de la página al navegar
                const pagePagoNav = document.getElementById('page-pago');
                console.log("🔍 [DEBUG NAV] page-pago element al navegar:", pagePagoNav);
                console.log("🔍 [DEBUG NAV] page-pago classList:", pagePagoNav ? pagePagoNav.classList.toString() : 'NO EXISTE');
                console.log("🔍 [DEBUG NAV] page-pago hidden class:", pagePagoNav ? pagePagoNav.classList.contains('hidden') : 'NO EXISTE');
                console.log("🔍 [DEBUG NAV] page-pago display computed:", pagePagoNav ? getComputedStyle(pagePagoNav).display : 'NO EXISTE');
                
                updateTotal(); // Actualizar totales primero para incluir ITP
                // updatePaymentSummary(); // COMENTADO: Función no existe, causa error
                // Inicializar Stripe cuando se muestra la página de pago
                console.log('⏱️ Iniciando timeout para inicializar Stripe en 300ms...');
                setTimeout(() => {
                    try {
                        // Calcular monto para Stripe
                        let stripeAmount = finalAmount;

                        // Si gestionamos el ITP y eligieron transferencia, cobrar solo lo nuestro (174.99€ fijo)
                        if (gestionamosITP && itpMetodoPago === 'transferencia') {
                            stripeAmount = 174.99; // PRECIO FIJO: tasas DGMM + gestión
                            console.log('📌 ITP se pagará por transferencia. Monto Stripe FIJO:', stripeAmount, '€');
                            console.log('💰 Total trámite:', finalAmount, '€ (ITP:', currentTransferTax, '€ + Nuestro:', stripeAmount, '€)');
                        }

                        console.log('🚀 Llamando a initializeStripe con amount:', stripeAmount);
                        initializeStripe(stripeAmount);
                    } catch (error) {
                        console.error("❌ Error al inicializar Stripe:", error);
                    }
                }, 300);
            }
            
            updateTermsCheckbox();
        }

        function getPageIndexById(pageId) {
            for (let i = 0; i < formPages.length; i++) {
                if (formPages[i].id === pageId) return i;
            }
            return -1;
        }
        
        function getNavItemIndex(pageId) {
            const navItems = document.querySelectorAll('.nav-item');
            for (let i = 0; i < navItems.length; i++) {
                if (navItems[i].getAttribute('data-page-id') === pageId) return i;
            }
            return -1;
        }

        function updateTermsCheckbox() {
            console.log("Actualizando términos para página:", formPages[currentPage].id);
            console.log("Contenedores de términos encontrados:", document.querySelectorAll('.terms-container').length);
            console.log("Contenedores de términos de pago encontrados:", document.querySelectorAll('.terms-container.payment-terms').length);
            
            // Ocultar todos los contenedores de términos
            document.querySelectorAll('.terms-container').forEach(container => {
                container.style.display = 'none';
            });

            // Mostrar el contenedor específico según la página
            if (formPages[currentPage].id === 'page-vehiculo') {
                // Para la página de vehículo, verifica si existe el elemento
                const vehicleCheckbox = document.querySelector('input[name="terms_accept_vehicle"]');
                if (vehicleCheckbox) {
                    vehicleCheckbox.checked = false;
                    const vehicleTermsContainer = vehicleCheckbox.closest('.terms-container');
                    if (vehicleTermsContainer) {
                        vehicleTermsContainer.style.display = 'block';
                    }
                }
            } 
            else if (formPages[currentPage].id === 'page-pago') {
                // Para la página de pago, utiliza un selector más específico
                const paymentTermsContainers = document.querySelectorAll('.terms-container.payment-terms');
                
                // Mostrar SOLO el contenedor de términos que NO esté dentro del modal
                paymentTermsContainers.forEach(container => {
                    if (!container.closest('#payment-modal')) {
                        container.style.display = 'block';
                    }
                });
                
                // Desmarcar la casilla de verificación
                const paymentCheckbox = document.querySelector('input[name="terms_accept_pago"]');
                if (paymentCheckbox) {
                    paymentCheckbox.checked = false;
                }
            }
        }

        // Inicialización de Stripe
        async function initializeStripe(amount) {
            console.log("=".repeat(60));
            console.log("🔷 INICIANDO SISTEMA DE PAGO STRIPE");
            console.log("=".repeat(60));
            console.log("💰 Monto recibido:", amount);
            const amountCents = Math.round(amount * 100);
            console.log("💵 Monto en centavos:", amountCents);

            // LOGS DETALLADOS: Estado de page-pago
            const pagePago = document.getElementById('page-pago');
            console.log("🔍 [DEBUG] page-pago element:", pagePago);
            console.log("🔍 [DEBUG] page-pago classList:", pagePago ? pagePago.classList.toString() : 'NO EXISTE');
            console.log("🔍 [DEBUG] page-pago tiene clase hidden:", pagePago ? pagePago.classList.contains('hidden') : 'NO EXISTE');
            console.log("🔍 [DEBUG] page-pago style.display:", pagePago ? getComputedStyle(pagePago).display : 'NO EXISTE');
            console.log("🔍 [DEBUG] page-pago offsetWidth x offsetHeight:", pagePago ? `${pagePago.offsetWidth}x${pagePago.offsetHeight}` : 'NO EXISTE');

            // Mostrar el spinner de carga
            const loadingEl = document.getElementById('stripe-loading');
            const paymentEl = document.getElementById('payment-element');
            const messageEl = document.getElementById('payment-message');
            const stripeContainer = document.getElementById('stripe-container');

            console.log('📋 Elementos del DOM:');
            console.log('  - stripe-loading:', loadingEl ? '✅ Encontrado' : '❌ No encontrado');
            console.log('  - payment-element:', paymentEl ? '✅ Encontrado' : '❌ No encontrado');
            console.log('  - payment-message:', messageEl ? '✅ Encontrado' : '❌ No encontrado');
            console.log('  - stripe-container:', stripeContainer ? '✅ Encontrado' : '❌ No encontrado');

            // LOGS CRÍTICOS: Ubicación física de elementos
            if (stripeContainer) {
                console.log("🔍 [DEBUG] stripe-container parent:", stripeContainer.parentElement);
                console.log("🔍 [DEBUG] stripe-container parent ID:", stripeContainer.parentElement?.id);
                console.log("🔍 [DEBUG] stripe-container parent classList:", stripeContainer.parentElement?.classList.toString());
                console.log("🔍 [DEBUG] stripe-container getBoundingClientRect():", stripeContainer.getBoundingClientRect());
            }
            
            if (paymentEl) {
                console.log("🔍 [DEBUG] payment-element parent:", paymentEl.parentElement);
                console.log("🔍 [DEBUG] payment-element parent ID:", paymentEl.parentElement?.id);
                console.log("🔍 [DEBUG] payment-element getBoundingClientRect():", paymentEl.getBoundingClientRect());
            }

            // LOGS CRÍTICOS: Estado del grid y sidebar
            const sidebar = document.querySelector('.tramitfy-sidebar');
            const twoColumn = document.querySelector('.tramitfy-two-column');
            const mainForm = document.querySelector('.tramitfy-main-form');
            
            console.log("🔍 [DEBUG] sidebar getBoundingClientRect():", sidebar ? sidebar.getBoundingClientRect() : 'NO EXISTE');
            console.log("🔍 [DEBUG] mainForm getBoundingClientRect():", mainForm ? mainForm.getBoundingClientRect() : 'NO EXISTE');
            console.log("🔍 [DEBUG] twoColumn gridTemplateColumns:", twoColumn ? getComputedStyle(twoColumn).gridTemplateColumns : 'NO EXISTE');

            if (loadingEl) loadingEl.style.display = 'block';
            if (paymentEl) paymentEl.innerHTML = '';
            if (messageEl) messageEl.className = 'hidden';

            // Inicializar Stripe según configuración
            console.log('🔑 Clave pública de Stripe:', '<?php echo substr($barco_stripe_public_key, 0, 20); ?>...');
            console.log('⚙️ STRIPE MODE:', '<?php echo BARCO_STRIPE_MODE; ?>' === 'test' ? '🧪 TEST MODE' : '🔴 LIVE MODE');
            console.log('🔑 Tipo de clave:', '<?php echo $barco_stripe_public_key; ?>'.startsWith('pk_test') ? 'TEST KEY ✅' : 'LIVE KEY ⚠️');
            console.log('🔧 Inicializando objeto Stripe...');
            stripe = Stripe('<?php echo $barco_stripe_public_key; ?>');
            console.log('✅ Objeto Stripe inicializado:', stripe ? 'OK' : 'ERROR');

            try {
                // Crear el payment intent
                const ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
                console.log('🌐 URL de AJAX:', ajaxUrl);
                console.log('📤 Enviando petición para crear Payment Intent...');
                console.log('📦 Datos:', `action=barco_create_payment_intent&amount=${amountCents}`);

                const response = await fetch(ajaxUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=barco_create_payment_intent&amount=${amountCents}`
                });

                console.log('📥 Respuesta recibida');
                console.log('  - Status:', response.status);
                console.log('  - Status Text:', response.statusText);
                console.log('  - OK:', response.ok);

                // Procesar la respuesta del Payment Intent
                const result = await response.json();
                console.log('📄 JSON parseado:', result);

                // DEBUG: Imprimir respuesta completa del servidor
                console.log("=== RESPUESTA DEL SERVIDOR (PaymentIntent) ===");
                console.log(result);

                if (result && result.error) {
                    console.error("Error al crear Payment Intent:", result.error);
                    document.getElementById('payment-message').textContent = 'Error al crear la intención de pago: ' + result.error;
                    document.getElementById('payment-message').classList.remove('hidden');
                    document.getElementById('payment-message').className = 'error';
                    document.getElementById('stripe-loading').style.display = 'none';
                    return;
                }

                if (!result || !result.clientSecret) {
                    console.error("No se recibió clientSecret del servidor");
                    document.getElementById('payment-message').textContent = 'Error: No se pudo inicializar el sistema de pago. Por favor, recarga la página.';
                    document.getElementById('payment-message').classList.remove('hidden');
                    document.getElementById('payment-message').className = 'error';
                    document.getElementById('stripe-loading').style.display = 'none';
                    return;
                }

                // Configurar la apariencia de Stripe con los colores de la marca
                const appearance = {
                    theme: 'stripe',
                    variables: {
                        colorPrimary: '#016d86',
                        colorBackground: '#ffffff',
                        colorText: '#1f2937',
                        colorDanger: '#dc2626',
                        fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
                        fontSizeBase: '16px',
                        spacingUnit: '4px',
                        borderRadius: '8px',
                        colorTextSecondary: '#6b7280',
                        colorTextPlaceholder: '#9ca3af',
                    },
                    rules: {
                        '.Input': {
                            border: '1px solid #d1d5db',
                            boxShadow: '0 1px 2px 0 rgba(0, 0, 0, 0.05)',
                            padding: '12px',
                            fontSize: '16px',
                        },
                        '.Input:focus': {
                            border: '1px solid #016d86',
                            boxShadow: '0 0 0 3px rgba(1, 109, 134, 0.1)',
                            outline: 'none',
                        },
                        '.Label': {
                            fontSize: '14px',
                            fontWeight: '500',
                            color: '#374151',
                            marginBottom: '8px',
                        },
                        '.Tab': {
                            border: '1px solid #e5e7eb',
                            boxShadow: '0 1px 2px 0 rgba(0, 0, 0, 0.05)',
                        },
                        '.Tab:hover': {
                            backgroundColor: '#f9fafb',
                        },
                        '.Tab--selected': {
                            backgroundColor: '#016d86',
                            color: '#ffffff',
                            borderColor: '#016d86',
                        },
                        '.Tab--selected:hover': {
                            backgroundColor: '#015266',
                        },
                    }
                };
                
                // Limpiar elementos previos
                if (elements) {
                    elements = null;
                }

                // Guardar clientSecret para confirm
                window.stripeClientSecret = result.clientSecret;
                console.log('💾 ClientSecret guardado:', result.clientSecret.substring(0, 30) + '...');

                // Crear elementos de Stripe CON clientSecret (igual que hoja-asiento)
                elements = stripe.elements({
                    appearance,
                    clientSecret: result.clientSecret
                });
                console.log('✅ Stripe elements creado con clientSecret');

                // Usar Payment Element (igual que hoja-asiento)
                const paymentElement = elements.create('payment', {
                    layout: {
                        type: 'tabs',
                        defaultCollapsed: false
                    }
                });
                console.log('✅ Payment Element creado');

                // Limpiar cualquier contenido existente y montar el elemento
                document.getElementById('payment-element').innerHTML = '';

                // Montar el elemento de pago
                setTimeout(async () => {
                    console.log("🔧 [DEBUG] Intentando montar Stripe en #payment-element...");
                    
                    // Verificar de nuevo los elementos antes del mount
                    const targetElement = document.getElementById('payment-element');
                    console.log("🔍 [DEBUG] Target element antes del mount:", targetElement);
                    console.log("🔍 [DEBUG] Target element offsetWidth x offsetHeight:", targetElement ? `${targetElement.offsetWidth}x${targetElement.offsetHeight}` : 'NO EXISTE');
                    console.log("🔍 [DEBUG] Target element getBoundingClientRect():", targetElement ? targetElement.getBoundingClientRect() : 'NO EXISTE');
                    
                    if (targetElement) {
                        await paymentElement.mount('#payment-element');
                        document.getElementById('stripe-loading').style.display = 'none';
                        console.log("✅ Payment Element montado correctamente");
                        
                        // Verificar DESPUÉS del mount dónde está renderizado
                        console.log("🔍 [DEBUG] DESPUÉS DEL MOUNT:");
                        console.log("🔍 [DEBUG] payment-element parent:", targetElement.parentElement);
                        console.log("🔍 [DEBUG] payment-element parent ID:", targetElement.parentElement?.id);
                        console.log("🔍 [DEBUG] payment-element children count:", targetElement.children.length);
                        console.log("🔍 [DEBUG] payment-element innerHTML length:", targetElement.innerHTML.length);
                        console.log("🔍 [DEBUG] Target element final getBoundingClientRect():", targetElement.getBoundingClientRect());
                        
                        // Verificar si hay contenido de Stripe en el sidebar
                        const sidebarContent = document.getElementById('sidebar-dynamic-content');
                        console.log("🔍 [DEBUG] sidebar-dynamic-content innerHTML length:", sidebarContent ? sidebarContent.innerHTML.length : 'NO EXISTE');
                    } else {
                        console.error("❌ [DEBUG] No se encontró #payment-element para montar Stripe");
                    }
                }, 300);
                
            } catch (err) {
                console.error("Error al inicializar Stripe:", err);
                document.getElementById('payment-message').textContent = 'Error al inicializar el sistema de pago: ' + err.message;
                document.getElementById('payment-message').className = 'error';
                document.getElementById('stripe-loading').style.display = 'none';
            }
        }

        // Funciones de cálculo
        function calculateDepreciationPercentage(years) {
            for (let i = 0; i < depreciationRates.length; i++) {
                if (years <= depreciationRates[i].years) {
                    return depreciationRates[i].rate;
                }
            }
            return 10;
        }

        function calculateFiscalValue() {
            logDebug('FISCAL', '💰 Calculando valor fiscal');

            // Protección: verificar si existe fecha de matriculación
            if (!matriculationDateInput || !matriculationDateInput.value) {
                logDebug('FISCAL', '⚠️ No hay fecha de matriculación, retornando valores por defecto');
                return { fiscalValue: 0, depreciationPercentage: 0, yearsDifference: 0 };
            }

            const matriculationDate = new Date(matriculationDateInput.value);
            const today = new Date();

            // Validar que la fecha sea válida
            if (isNaN(matriculationDate.getTime())) {
                logDebug('FISCAL', '⚠️ Fecha inválida, retornando valores por defecto');
                return { fiscalValue: 0, depreciationPercentage: 0, yearsDifference: 0 };
            }

            let yearsDifference = today.getFullYear() - matriculationDate.getFullYear();
            const monthsDifference = today.getMonth() - matriculationDate.getMonth();

            logDebug('FISCAL', 'Fecha matriculación:', matriculationDateInput.value);
            logDebug('FISCAL', 'Años diferencia (inicial):', yearsDifference);

            if (monthsDifference < 0 || (monthsDifference === 0 && today.getDate() < matriculationDate.getDate())) {
                yearsDifference--;
                logDebug('FISCAL', 'Ajuste por meses/días, años:', yearsDifference);
            }

            yearsDifference = (yearsDifference < 0) ? 0 : yearsDifference;
            const depreciationPercentage = calculateDepreciationPercentage(yearsDifference);
            const fiscalValue = basePrice * (depreciationPercentage / 100);

            logDebug('FISCAL', 'Resultado:', {
                yearsDifference,
                depreciationPercentage,
                basePrice,
                fiscalValue
            });

            return { fiscalValue, depreciationPercentage, yearsDifference };
        }

        function calculateTransferTax() {
            logDebug('ITP', '📊 Calculando ITP');
            const purchasePrice = parseFloat(purchasePriceInput.value) || 0;
            const { fiscalValue, depreciationPercentage, yearsDifference } = calculateFiscalValue();
            const region = regionSelect.value;
            let rate = itpRates[region] || 0;
            const itpAlreadyPaidElement = document.getElementById('itp_already_paid');
            const isItpAlreadyPaid = itpAlreadyPaidElement ? itpAlreadyPaidElement.checked : false;

            // Aplicar modificaciones especiales por región
            let tasaAdicional = 0;
            let descuentoAplicado = 0;
            if (regionesEspeciales[region]) {
                const especial = regionesEspeciales[region];
                
                // Valencia: tasa adicional
                if (region === "Comunidad Valenciana") {
                    tasaAdicional = especial.tasaAdicional;
                }
                
                // Andalucía: descuento familia numerosa (simulado)
                if (region === "Andalucía" && especial.descuentoFamiliarNumerosa) {
                    // Por simplicidad, aplicamos descuento si precio > 15000€
                    if (purchasePrice > 15000) {
                        descuentoAplicado = especial.descuentoFamiliarNumerosa;
                        rate = rate * (1 - descuentoAplicado);
                    }
                }
                
                // Asturias: bonificación joven (simulado)
                if (region === "Asturias" && especial.bonificacionJoven) {
                    // Por simplicidad, aplicamos bonificación si precio < 25000€
                    if (purchasePrice < 25000) {
                        descuentoAplicado = especial.bonificacionJoven;
                        rate = rate * (1 - descuentoAplicado);
                    }
                }
            }

            logDebug('ITP', 'Datos entrada:', {
                purchasePrice,
                fiscalValue,
                region,
                rate,
                isItpAlreadyPaid,
                tasaAdicional,
                descuentoAplicado
            });

            const baseValue = Math.max(purchasePrice, fiscalValue);
            let itp = isItpAlreadyPaid ? 0 : baseValue * rate;
            
            // Añadir tasa adicional si aplica
            if (tasaAdicional > 0) {
                itp += tasaAdicional;
            }
            
            const extraFee = tasaAdicional; // La tasa adicional se considera como fee extra

            logDebug('ITP', 'Resultado cálculo:', { baseValue, itp, extraFee });

            // Actualizar elementos de la página de detalle ITP (solo si existen)
            if (baseValueDisplay) baseValueDisplay.textContent = basePrice.toFixed(2) + ' €';
            if (depreciationPercentageDisplay) depreciationPercentageDisplay.textContent = depreciationPercentage + ' %';
            if (fiscalValueDisplay) fiscalValueDisplay.textContent = fiscalValue.toFixed(2) + ' €';
            if (vehicleAgeDisplay) vehicleAgeDisplay.textContent = yearsDifference + ' años';
            if (purchasePriceDisplay) purchasePriceDisplay.textContent = purchasePrice.toFixed(2) + ' €';
            if (taxBaseDisplay) taxBaseDisplay.textContent = baseValue.toFixed(2) + ' €';
            if (taxRateDisplay) taxRateDisplay.textContent = (rate * 100).toFixed(2) + ' %';
            if (calculatedItpDisplay) calculatedItpDisplay.textContent = itp.toFixed(2) + ' €';

            // Actualizar elementos del sidebar "Desglose del trámite"
            const sidebarPurchasePrice = document.getElementById('sidebar-purchase-price');
            const sidebarFiscalValue = document.getElementById('sidebar-fiscal-value');
            const sidebarTaxBase = document.getElementById('sidebar-tax-base');
            const sidebarTaxRate = document.getElementById('sidebar-tax-rate');
            const sidebarItpAmount = document.getElementById('sidebar-itp-amount');
            const sidebarRegionName = document.getElementById('sidebar-region-name');

            if (sidebarPurchasePrice) sidebarPurchasePrice.textContent = purchasePrice.toFixed(2) + '€';
            if (sidebarFiscalValue) sidebarFiscalValue.textContent = fiscalValue > 0 ? fiscalValue.toFixed(2) + '€' : '-';
            if (sidebarTaxBase) sidebarTaxBase.textContent = baseValue.toFixed(2) + '€';
            if (sidebarTaxRate) sidebarTaxRate.textContent = (rate * 100).toFixed(0) + '%';
            if (sidebarItpAmount) sidebarItpAmount.textContent = itp.toFixed(2) + '€';
            if (sidebarRegionName) sidebarRegionName.textContent = region || '-';

            return { itp, extraFee };
        }

        function updateTransferTaxDisplay() {
            const { itp, extraFee } = calculateTransferTax();
            currentTransferTax = itp;
            currentExtraFee = extraFee;
            if (transferTaxDisplay) transferTaxDisplay.textContent = itp.toFixed(2) + ' €';
            if (extraFeeIncludesDisplay) extraFeeIncludesDisplay.textContent = extraFee.toFixed(2) + ' €';
        }

        // Actualizar total y aplicar descuentos
        function updateTotal() {
            // Calculamos la parte base "Gestión" + extras marcados
            // Usar el precio correcto según si gestionamos el ITP
            let transferFee = gestionamosITP ? BASE_TRANSFER_PRICE_CON_ITP : BASE_TRANSFER_PRICE_SIN_ITP;
            let additionalServicesTotal = 0;
            let selectedServiceLabels = [];
            
            // Calcular opciones adicionales y aplicar estilo a los elementos seleccionados
            extraOptions.forEach(option => {
                if (option.checked) {
                    const optionPrice = parseFloat(option.dataset.price) || 0;
                    additionalServicesTotal += optionPrice;
                    
                    // Agregar clase 'selected' al contenedor del checkbox
                    const label = option.closest('label');
                    if (label) {
                        label.classList.add('selected');
                        
                        // Mostrar campos de entrada adicionales si existen
                        if (option.value === 'Cambio de nombre') {
                            const inputField = document.getElementById('nombre-input');
                            if (inputField) {
                                inputField.style.display = 'block';
                                // Forzar reflow para asegurar que la transición funcione
                                inputField.getBoundingClientRect();
                                // Añadir clase para transición suave
                                inputField.classList.add('campo-activo');
                            }
                        } else if (option.value === 'Cambio de puerto base') {
                            const inputField = document.getElementById('puerto-input');
                            if (inputField) {
                                inputField.style.display = 'block';
                                // Forzar reflow para asegurar que la transición funcione
                                inputField.getBoundingClientRect();
                                // Añadir clase para transición suave
                                inputField.classList.add('campo-activo');
                            }
                        }
                        
                        // Mantener un registro de servicios seleccionados para el resumen
                        const serviceName = label.querySelector('.service-name')?.textContent || option.value;
                        selectedServiceLabels.push(serviceName);
                    }
                    
                    // Mostrar el acordeón de servicios adicionales si alguna opción está seleccionada
                    const servicesAccordion = document.getElementById('services-accordion');
                    if (servicesAccordion) {
                        const toggleHeader = servicesAccordion.querySelector('.accordion-toggle-header');
                        const contentSection = servicesAccordion.querySelector('.accordion-content-section');
                        
                        if (toggleHeader && contentSection && !toggleHeader.classList.contains('active')) {
                            toggleHeader.classList.add('active');
                            contentSection.classList.add('active');
                            contentSection.style.maxHeight = contentSection.scrollHeight + 'px';
                        }
                    }
                } else {
                    // Quitar clase 'selected' si no está marcado
                    const label = option.closest('label');
                    if (label) {
                        label.classList.remove('selected');
                        
                        // Ocultar campos adicionales
                        if (option.value === 'Cambio de nombre') {
                            const inputField = document.getElementById('nombre-input');
                            if (inputField) {
                                // Primero quitar la clase de transición
                                inputField.classList.remove('campo-activo');
                                // Después de un breve retraso, ocultar el elemento
                                setTimeout(() => {
                                    inputField.style.display = 'none';
                                }, 200);
                            }
                        } else if (option.value === 'Cambio de puerto base') {
                            const inputField = document.getElementById('puerto-input');
                            if (inputField) {
                                // Primero quitar la clase de transición
                                inputField.classList.remove('campo-activo');
                                // Después de un breve retraso, ocultar el elemento
                                setTimeout(() => {
                                    inputField.style.display = 'none';
                                }, 200);
                            }
                        }
                    }
                }
            });
            
            // Sumar precio base + servicios adicionales
            transferFee += additionalServicesTotal;

            // Obtenemos el ITP y su comisión bancaria
            const { itp, extraFee } = calculateTransferTax();
            currentTransferTax = itp;
            currentExtraFee = extraFee; // Actualizar variable global, no crear nueva local

            // Base para aplicar descuento (solo sobre transferFee)
            const discountBase = transferFee; 
            const discount = (couponDiscountPercent / 100) * discountBase;
            const discountedTransferFee = transferFee - discount;
            const finalExtraFee = currentExtraFee;

            // Desglose de tasas, honorarios e IVA
            const baseTasas = 19.05; // Las tasas siempre son 19.05€
            
            // Calcular honorarios basados en el precio base menos las tasas
            // Si gestionamos ITP: 174.99 - 19.05 = 155.94
            // Si NO gestionamos ITP: 134.99 - 19.05 = 115.94
            // Los servicios adicionales se suman aparte
            const baseHonorariosSinServicios = transferFee - baseTasas;
            const baseHonorarios = baseHonorariosSinServicios + additionalServicesTotal;
            
            const discountRatio = (couponDiscountPercent / 100);
            const discountedHonorarios = baseHonorarios * (1 - discountRatio);
            
            // 🔧 CORRECCIÓN: IVA = diferencia entre honorarios brutos y netos
            const honorariosSinIva = discountedHonorarios / 1.21;
            const newIva = discountedHonorarios - honorariosSinIva;

            // Guardar valores globalmente para poder acceder en el submit
            window.currentTasas = baseTasas;
            window.currentHonorarios = discountedHonorarios;
            window.currentIva = newIva;

            // Cálculo final
            const totalGestion = baseTasas + discountedHonorarios + newIva + finalExtraFee;
            const total = itp + totalGestion;

            finalAmount = total;

            // Actualizar la UI para el descuento
            if (discount > 0) {
                // Mostrar descuento en UI
                if (discountLi) {
                    discountLi.style.display = 'flex';
                    if (discountAmountEl) {
                        discountAmountEl.textContent = '-' + discount.toFixed(2) + ' €';
                    }
                }
                
                // Si hay descuento, mostrar el acordeón de cupón
                const couponAccordion = document.getElementById('coupon-accordion');
                if (couponAccordion) {
                    const toggleHeader = couponAccordion.querySelector('.accordion-toggle-header');
                    const contentSection = couponAccordion.querySelector('.accordion-content-section');
                    
                    if (toggleHeader && contentSection && !toggleHeader.classList.contains('active')) {
                        toggleHeader.classList.add('active');
                        contentSection.classList.add('active');
                        contentSection.style.maxHeight = contentSection.scrollHeight + 'px';
                    }
                }
                
                // Mostrar información de cupón en resumen
                const couponInfo = document.getElementById('summary-coupon-container');
                if (couponInfo) {
                    couponInfo.style.display = 'block';
                    const couponInfoText = document.getElementById('summary-coupon-info');
                    if (couponInfoText) {
                        couponInfoText.textContent = `Cupón aplicado: ${couponValue} (-${couponDiscountPercent}%)`;
                    }
                }
            } else {
                if (discountLi) {
                    discountLi.style.display = 'none';
                }
                
                // Ocultar información de cupón en resumen
                const couponInfo = document.getElementById('summary-coupon-container');
                if (couponInfo) {
                    couponInfo.style.display = 'none';
                }
            }

            // Actualizar importes en la UI
            if (finalAmountEl) finalAmountEl.textContent = total.toFixed(2) + ' €';
            if (finalSummaryAmountEl) finalSummaryAmountEl.textContent = total.toFixed(2) + ' €';

            // Actualizar otros elementos de UI
            const transferTaxDisplay = document.getElementById('transfer_tax_display');
            const tasasHonorariosDisplay = document.getElementById('tasas_honorarios_display');
            const ivaDisplay = document.getElementById('iva_display');
            const extraFeeIncludesDisplay = document.getElementById('extra_fee_includes_display');
            const cambioNombrePriceDisplay = document.getElementById('cambio_nombre_price');
            
            if (transferTaxDisplay) transferTaxDisplay.textContent = itp.toFixed(2) + ' €';
            
            const tasasMasHonorarios = baseTasas + discountedHonorarios;
            if (tasasHonorariosDisplay) tasasHonorariosDisplay.textContent = tasasMasHonorarios.toFixed(2) + ' €';
            if (ivaDisplay) ivaDisplay.textContent = newIva.toFixed(2) + ' €';
            if (extraFeeIncludesDisplay) extraFeeIncludesDisplay.textContent = finalExtraFee.toFixed(2) + ' €';
            if (cambioNombrePriceDisplay) cambioNombrePriceDisplay.textContent = totalGestion.toFixed(2) + ' €';

            // Guardar valores en campos ocultos
            const tasasHidden = document.getElementById('tasas_hidden');
            const ivaHidden = document.getElementById('iva_hidden');
            const honorariosHidden = document.getElementById('honorarios_hidden');
            
            if (tasasHidden) tasasHidden.value = baseTasas.toFixed(2);
            if (ivaHidden) ivaHidden.value = newIva.toFixed(2);
            if (honorariosHidden) honorariosHidden.value = discountedHonorarios.toFixed(2);
            
            // Actualizar también los valores en el detalle del ITP
            if (typeof calculateTransferTax === 'function') {
                calculateTransferTax();
            }
            
            // Actualizar contenido HTML para servicios adicionales seleccionados
            const servicesSummary = document.getElementById('selected-services-summary');
            if (servicesSummary && selectedServiceLabels.length > 0) {
                servicesSummary.innerHTML = '<strong>Servicios adicionales:</strong> ' + selectedServiceLabels.join(', ');
                servicesSummary.style.display = 'block';
            } else if (servicesSummary) {
                servicesSummary.style.display = 'none';
            }
            
            if (typeof updatePaymentSummary === 'function') {
                updatePaymentSummary();
            }
        }

        // Actualizar resumen para pago
        function updatePaymentSummary() {
            // Actualizar datos personales (sidebar y página resumen)
            const summaryNameElements = document.querySelectorAll('#summary-name');
            const summaryDniElements = document.querySelectorAll('#summary-dni');
            const summaryEmailElements = document.querySelectorAll('#summary-email');
            const summaryPhoneElements = document.querySelectorAll('#summary-phone');

            summaryNameElements.forEach(el => el.textContent = document.getElementById('customer_name')?.value || '-');
            summaryDniElements.forEach(el => el.textContent = document.getElementById('customer_dni')?.value || '-');
            summaryEmailElements.forEach(el => el.textContent = document.getElementById('customer_email')?.value || '-');
            summaryPhoneElements.forEach(el => el.textContent = document.getElementById('customer_phone')?.value || '-');

            const vehicleType = 'Embarcación';
            const summaryVehicleTypeElements = document.querySelectorAll('#summary-vehicle-type');
            summaryVehicleTypeElements.forEach(el => el.textContent = vehicleType);

            const noEncuentro = noEncuentroCheckbox.checked;
            const summaryManufacturerElements = document.querySelectorAll('#summary-manufacturer');
            const summaryModelElements = document.querySelectorAll('#summary-model');
            const summaryMatriculationElements = document.querySelectorAll('#summary-matriculation');

            if (noEncuentro) {
                summaryManufacturerElements.forEach(el => el.textContent = document.getElementById('manual_manufacturer').value || '-');
                summaryModelElements.forEach(el => el.textContent = document.getElementById('manual_model').value || '-');
                summaryMatriculationElements.forEach(el => el.textContent = 'No aplica');
            } else {
                summaryManufacturerElements.forEach(el => el.textContent = manufacturerSelect.value || '-');
                summaryModelElements.forEach(el => el.textContent = modelSelect.value || '-');
                summaryMatriculationElements.forEach(el => el.textContent = matriculationDateInput.value || '-');
            }

            const summaryPurchasePriceElements = document.querySelectorAll('#summary-purchase-price');
            const summaryRegionElements = document.querySelectorAll('#summary-region');
            summaryPurchasePriceElements.forEach(el => el.textContent = purchasePriceInput.value + ' €' || '-');
            summaryRegionElements.forEach(el => el.textContent = regionSelect.value || '-');

            // Obtener valores actuales (con validación para evitar null)
            const cambioNombrePrice = cambioNombrePriceDisplay ? cambioNombrePriceDisplay.textContent : '134.99 €';
            const tasasHonorariosEl = document.getElementById('tasas_honorarios_display');
            const ivaEl = document.getElementById('iva_display');
            const tasasHonorarios = tasasHonorariosEl ? tasasHonorariosEl.textContent : '114.87 €';
            const iva = ivaEl ? ivaEl.textContent : '20.12 €';

            // Usar variables globales para ITP y comisión (más fiables que DOM)
            const comisionBancaria = currentExtraFee ? currentExtraFee.toFixed(2) + ' €' : '0 €';
            const transferTax = currentTransferTax ? currentTransferTax.toFixed(2) + ' €' : '0 €';

            console.log('updatePaymentSummary - ITP:', currentTransferTax, 'Comisión:', currentExtraFee, 'Total:', finalAmount);

            const summaryBasePriceElements = document.querySelectorAll('#summary-base-price');
            const summaryTasasGestionElements = document.querySelectorAll('#summary-tasas-gestion');
            const summaryIvaElements = document.querySelectorAll('#summary-iva');
            const summaryComisionElements = document.querySelectorAll('#summary-comision');
            const summaryTransferTaxElements = document.querySelectorAll('#summary-transfer-tax, #summary-transfer-tax-detail');
            const summaryFinalAmountElements = document.querySelectorAll('#summary-final-amount');

            console.log('Elementos ITP encontrados:', summaryTransferTaxElements.length, 'Valor a asignar:', transferTax);

            summaryBasePriceElements.forEach(el => el.textContent = cambioNombrePrice);
            summaryTasasGestionElements.forEach(el => el.textContent = tasasHonorarios);
            summaryIvaElements.forEach(el => el.textContent = iva);
            summaryComisionElements.forEach(el => el.textContent = comisionBancaria);
            summaryTransferTaxElements.forEach(el => {
                console.log('Actualizando elemento ITP:', el.id, 'con valor:', transferTax);
                el.textContent = transferTax;
            });
            summaryFinalAmountElements.forEach(el => el.textContent = finalAmount.toFixed(2) + ' €');

            const summaryNameChange = document.getElementById('summary-name-change');
            const summaryPortChange = document.getElementById('summary-port-change');
            if (summaryNameChange) summaryNameChange.style.display = 'none';
            if (summaryPortChange) summaryPortChange.style.display = 'none';

            const extraOptions = document.querySelectorAll('.extra-option');
            let hasExtras = false;

            extraOptions.forEach(option => {
                if (option.checked) {
                    hasExtras = true;
                    if (option.value === 'Cambio de nombre' && summaryNameChange) {
                        summaryNameChange.style.display = 'block';
                    } else if (option.value === 'Cambio de puerto base' && summaryPortChange) {
                        summaryPortChange.style.display = 'block';
                    }
                }
            });

            const summaryExtrasDetail = document.getElementById('summary-extras-detail');
            if (summaryExtrasDetail) summaryExtrasDetail.style.display = hasExtras ? 'block' : 'none';

            const couponCode = couponCodeInput.value;
            const summaryCouponContainer = document.getElementById('summary-coupon-container');
            const summaryCoupon = document.getElementById('summary-coupon');
            const summaryDiscountDetail = document.getElementById('summary-discount-detail');
            const summaryDiscountAmount = document.getElementById('summary-discount-amount');

            if (couponCode && couponDiscountPercent > 0) {
                if (summaryCouponContainer) summaryCouponContainer.style.display = 'block';
                if (summaryCoupon) summaryCoupon.textContent = couponCode + ' (' + couponDiscountPercent + '% descuento)';

                if (summaryDiscountDetail) summaryDiscountDetail.style.display = 'block';
                const discountBase = gestionamosITP ? BASE_TRANSFER_PRICE_CON_ITP : BASE_TRANSFER_PRICE_SIN_ITP;
                const discountAmount = (couponDiscountPercent / 100) * discountBase;
                if (summaryDiscountAmount) summaryDiscountAmount.textContent = discountAmount.toFixed(2) + ' €';
            } else {
                if (summaryCouponContainer) summaryCouponContainer.style.display = 'none';
                if (summaryDiscountDetail) summaryDiscountDetail.style.display = 'none';
            }

            const finalSummaryAmount = document.getElementById('final-summary-amount');
            if (finalSummaryAmount) finalSummaryAmount.textContent = finalAmount.toFixed(2) + ' €';
        }

        // Función de compatibilidad - YA NO SE USA (usar initializeSignaturePadForm)
        function initializeSignaturePad(forceReinit = false) {
            console.log('⚠️ initializeSignaturePad() llamada - esta función está obsoleta');
            console.log('🔄 Use initializeSignaturePadForm() para el flujo condicional');
            return; // Deshabilitada
            if ((!signaturePad || forceReinit) && signatureCanvas) {
                try {
                    // Si ya existe, destruirlo primero
                    if (signaturePad && forceReinit) {
                        signaturePad.off();
                        signaturePad = null;
                    }

                    // Configurar el canvas para alta resolución (mejor precisión)
                    const ratio = Math.max(window.devicePixelRatio || 1, 1);
                    const canvas = signatureCanvas;

                    // Esperar a que el DOM esté completamente renderizado
                    setTimeout(() => {
                        // Obtener el tamaño CSS del canvas
                        const rect = canvas.getBoundingClientRect();

                        console.log('📐 [initializeSignaturePad] Dimensiones del canvas:', rect.width + 'x' + rect.height);

                        // Solo inicializar si el canvas tiene dimensiones válidas
                        if (rect.width > 0 && rect.height > 0) {
                            // Establecer el tamaño del canvas interno (mayor resolución)
                            canvas.width = rect.width * ratio;
                            canvas.height = rect.height * ratio;

                            // Escalar el contexto para que coincida
                            const ctx = canvas.getContext('2d');
                            ctx.scale(ratio, ratio);

                            // Crear SignaturePad
                            signaturePad = new SignaturePad(canvas, {
                                backgroundColor: 'rgb(255, 255, 255)',
                                penColor: 'rgb(0, 0, 0)',
                                minWidth: 1.5,
                                maxWidth: 3.5,
                                dotSize: 2,
                                velocityFilterWeight: 0.7
                            });

                            // Ocultar label cuando el usuario empiece a firmar
                            signaturePad.addEventListener('beginStroke', function() {
                                const label = document.getElementById('signature-label');
                                if (label) label.classList.add('hidden');
                                if (canvas) canvas.classList.add('signed');
                            });

                            console.log('✅ SignaturePad inicializado correctamente con alta resolución', rect.width + 'x' + rect.height);
                        } else {
                            console.warn('⚠️ Canvas tiene dimensiones 0, no se puede inicializar SignaturePad');
                        }
                    }, 100);

                } catch (error) {
                    console.error('❌ Error inicializando SignaturePad:', error);
                }
            }
        }

        // Función simple para inicializar SignaturePad
        function initializeSimpleSignaturePad() {
            // Esperar a que SignaturePad esté disponible
            if (typeof SignaturePad === 'undefined') {
                console.log('🔄 SignaturePad no disponible, reintentando...');
                setTimeout(initializeSimpleSignaturePad, 500);
                return;
            }

            const canvas = document.getElementById('signature-pad-simple');
            if (!canvas) {
                console.log('❌ Canvas signature-pad-simple no encontrado');
                return;
            }

            // Verificar que el canvas esté visible
            const rect = canvas.getBoundingClientRect();
            if (rect.width === 0 || rect.height === 0) {
                console.log('🔄 Canvas no visible, reintentando...');
                setTimeout(initializeSimpleSignaturePad, 300);
                return;
            }

            // Limpiar instance anterior si existe
            if (window.signaturePadSimple) {
                try {
                    window.signaturePadSimple.off();
                } catch (e) {}
                window.signaturePadSimple = null;
            }

            try {
                // Configurar dimensiones correctas
                const ratio = Math.max(window.devicePixelRatio || 1, 1);
                canvas.width = rect.width * ratio;
                canvas.height = rect.height * ratio;
                
                const ctx = canvas.getContext('2d');
                ctx.scale(ratio, ratio);
                ctx.fillStyle = 'white';
                ctx.fillRect(0, 0, canvas.width, canvas.height);

                // Crear SignaturePad
                window.signaturePadSimple = new SignaturePad(canvas, {
                    backgroundColor: 'rgb(255, 255, 255)',
                    penColor: 'rgb(0, 0, 0)',
                    minWidth: 1.5,
                    maxWidth: 3.5,
                    dotSize: 2.0,
                    velocityFilterWeight: 0.7,
                    throttle: 16
                });

                // Event listeners
                window.signaturePadSimple.addEventListener('beginStroke', function() {
                    const label = document.getElementById('signature-label-simple');
                    if (label) label.style.display = 'none';
                    
                    // Cambiar borde a verde cuando hay firma
                    setTimeout(() => {
                        canvas.style.borderColor = '#10b981';
                    }, 100);
                });

                // Configurar botón limpiar
                const clearBtn = document.getElementById('clear-signature-simple');
                if (clearBtn) {
                    clearBtn.onclick = function() {
                        window.signaturePadSimple.clear();
                        const label = document.getElementById('signature-label-simple');
                        if (label) label.style.display = 'block';
                        canvas.style.borderColor = '#016d86';
                    };
                }

                // Configurar botón volver
                const volverBtn = document.getElementById('volver-documentos');
                if (volverBtn) {
                    volverBtn.onclick = function() {
                        const checkbox = document.getElementById('documents-complete-check');
                        if (checkbox) {
                            checkbox.checked = false;
                            checkbox.dispatchEvent(new Event('change'));
                        }
                    };
                }

                console.log('✅ SignaturePad simple inicializado correctamente:', rect.width + 'x' + rect.height);
                
            } catch (error) {
                console.error('❌ Error inicializando SignaturePad simple:', error);
            }
        }

        // Validar que hay firma (versión simple)
        function validateSignature() {
            if (!window.signaturePadSimple) return false;
            return !window.signaturePadSimple.isEmpty();
        }

        // Obtener datos de firma (versión simple)
        function getSignatureData() {
            if (!window.signaturePadSimple || window.signaturePadSimple.isEmpty()) {
                return null;
            }
            return window.signaturePadSimple.toDataURL('image/png');
        }

        // Mostrar error de pago
        function showPaymentError(message) {
            const paymentMessage = document.getElementById('payment-message');
            if (paymentMessage) {
                paymentMessage.textContent = message;
                paymentMessage.className = 'error';
                paymentMessage.style.display = 'block';
                paymentMessage.style.backgroundColor = '#fee2e2';
                paymentMessage.style.color = '#991b1b';
                paymentMessage.style.border = '1px solid #fca5a5';
                paymentMessage.style.padding = '12px';
                paymentMessage.style.borderRadius = '6px';
                paymentMessage.style.marginBottom = '16px';
            }
        }

        // Limpiar documento de autorización del sidebar
        function limpiarDocumentoAutorizacion() {
            console.log('🧹 LIMPIANDO documento de autorización...');
            
            // Eliminar cualquier elemento de documento de autorización del DOM
            const authDoc = document.getElementById('authorization-document');
            if (authDoc) {
                authDoc.remove();
            }
            
            // Limpiar solo el contenido dinámico, NO destruir la estructura
            const sidebarDynamic = document.getElementById('sidebar-dynamic-content');
            if (sidebarDynamic) {
                sidebarDynamic.innerHTML = ''; // Solo limpiar contenido dinámico
                console.log('✅ Contenido dinámico limpiado');
            }
            
            // Restaurar estilos del sidebar sin destruir estructura
            const sidebar = document.querySelector('.tramitfy-sidebar');
            if (sidebar) {
                sidebar.style.width = '';
                sidebar.style.minWidth = '';
                sidebar.style.background = '';
                sidebar.style.boxShadow = '';
                console.log('✅ Estilos del sidebar restaurados');
            }
        }

        // Mostrar documento de autorización en sidebar (SOLO en vista de firma)
        function mostrarDocumentoAutorizacionSidebar() {
            // IMPORTANTE: Limpiar primero cualquier documento previo
            limpiarDocumentoAutorizacion();
            
            // Cambiar el sidebar para mostrar modo firma
            const sidebar = document.querySelector('.tramitfy-sidebar');
            if (sidebar) {
                // Ensanchar el sidebar para mostrar documento y mantener fondo azul
                sidebar.style.width = '400px';
                sidebar.style.minWidth = '400px';
                sidebar.style.background = 'linear-gradient(135deg, #016d86 0%, #014d61 100%)';
                sidebar.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
                
                // Actualizar contenido dinámico del sidebar para mostrar documento
                const sidebarDynamic = document.getElementById('sidebar-dynamic-content');
                if (sidebarDynamic) {
                    sidebarDynamic.innerHTML = `
                        <div style="padding: 20px;">
                            <h3 style="color: white; font-size: 16px; font-weight: 600; margin-bottom: 15px; text-align: center;">
                                📄 Documento de Autorización
                            </h3>
                            <div id="authorization-document" style="background: white; padding: 20px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.2); font-size: 12px; line-height: 1.4; max-height: 500px; overflow-y: auto; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                                <!-- El contenido se llenará con generateAuthorizationDocument() -->
                            </div>
                            <div style="text-align: center; margin-top: 15px;">
                                <small style="color: rgba(255,255,255,0.8); font-size: 11px;">
                                    Revisa el documento y firma en el formulario
                                </small>
                            </div>
                        </div>
                    `;
                    
                    // Generar el documento SOLO aquí
                    setTimeout(() => {
                        generateAuthorizationDocument();
                    }, 100);
                }
            }
        }

        function generateAuthorizationDocument() {
            const authorizationDiv = document.getElementById('authorization-document');

            // Validar que el elemento existe antes de intentar modificarlo
            if (!authorizationDiv) {
                console.log('[generateAuthorizationDocument] Elemento authorization-document no existe en el DOM actual');
                return;
            }

            const customerName = document.getElementById('customer_name')?.value?.trim() || '';
            const customerDNI = document.getElementById('customer_dni')?.value?.trim() || '';
            const customerEmail = document.getElementById('customer_email')?.value?.trim() || '';
            const vehicleType = 'Embarcación';
            const manufacturer = manufacturerSelect.value;
            const model = modelSelect.value;
            const manualManufacturer = document.getElementById('manual_manufacturer') ? document.getElementById('manual_manufacturer').value.trim() : '';
            const manualModel = document.getElementById('manual_model') ? document.getElementById('manual_model').value.trim() : '';
            const matriculationDate = matriculationDateInput ? matriculationDateInput.value : '';
            const nuevoNombre = document.getElementById('nuevo_nombre') ? document.getElementById('nuevo_nombre').value.trim() : '';
            const nuevoPuerto = document.getElementById('nuevo_puerto') ? document.getElementById('nuevo_puerto').value.trim() : '';
            const noEncuentro = noEncuentroCheckbox ? noEncuentroCheckbox.checked : false;

            const currentDate = new Date().toLocaleDateString('es-ES', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });

            let html = `
                <div style="text-align: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #016d86;">
                    <h3 style="color: #016d86; margin: 0; font-size: 16px;">Autorización de Transferencia</h3>
                    <small style="color: #666;">${currentDate}</small>
                </div>

                <p style="font-size: 13px; line-height: 1.4; margin-bottom: 15px;">
                    <strong>${customerName}</strong> (DNI: ${customerDNI}) autoriza a <strong>TRAMITFY S.L.</strong> para gestionar la transferencia de propiedad del vehículo:
                </p>

                <div style="background: #f8f9fa; padding: 12px; border-radius: 6px; margin-bottom: 15px;">
                    <strong style="color: #016d86;">🚤 ${vehicleType}</strong><br>
            `;

            if (!noEncuentro) {
                html += `
                    <span style="font-size: 12px; color: #555;">
                        ${manufacturer} ${model}<br>
                        <small>Fecha: ${matriculationDate}</small>
                    </span>`;
            } else {
                html += `
                    <span style="font-size: 12px; color: #555;">
                        ${manualManufacturer} ${manualModel}
                    </span>`;
            }

            html += `</div>`;

            if (nuevoNombre || nuevoPuerto) {
                html += `
                <div style="background: #e8f4f8; padding: 10px; border-radius: 6px; font-size: 12px; margin-bottom: 15px;">
                    <strong style="color: #016d86;">📝 Servicios adicionales:</strong><br>`;
                if (nuevoNombre) {
                    html += `• Cambio nombre: ${nuevoNombre}<br>`;
                }
                if (nuevoPuerto) {
                    html += `• Cambio puerto: ${nuevoPuerto}<br>`;
                }
                html += `</div>`;
            }

            html += `
                <div style="text-align: center; margin-top: 20px; padding: 15px; background: #f0f8ff; border: 1px solid #cce7ff; border-radius: 6px;">
                    <p style="margin: 0; font-size: 12px; color: #2c3e50; font-weight: 600;">
                        ✍️ Firma del Solicitante
                    </p>
                    <small style="color: #666; font-size: 11px;">
                        La firma digital tiene validez legal
                    </small>
                </div>
            `;
            authorizationDiv.innerHTML = html;
            
            // Marcar la primera sección como completada
            const firstHeader = document.querySelector('.accordion-section:first-child .accordion-header');
            if (firstHeader && customerName && customerDNI && customerEmail && document.getElementById('customer_phone')?.value) {
                firstHeader.classList.add('completed');
                firstHeader.querySelector('.accordion-status').textContent = 'Completado';
            }
        }

        function validateSection(sectionIndex) {
            console.log('Validando sección', sectionIndex);
            let isValid = true;
            const sections = document.querySelectorAll('.accordion-section');
            if (sectionIndex >= sections.length) {
                console.error('Índice de sección inválido:', sectionIndex);
                return false;
            }
            const section = sections[sectionIndex];
            
            if (sectionIndex === 0) { // Datos personales
                const requiredInputs = section.querySelectorAll('input[required]');
                requiredInputs.forEach(input => {
                    if (!input.value.trim()) {
                        isValid = false;
                        input.classList.add('invalid');
                        console.log('Campo inválido:', input.id || input.name);
                    } else {
                        input.classList.remove('invalid');
                    }
                });
                
                if (!isValid) {
                    alert('Por favor, completa todos los campos requeridos de datos personales');
                }
            } 
            else if (sectionIndex === 1) { // Documentos
                const fileInputs = section.querySelectorAll('input[type="file"]');
                let allFilesValid = true;
                
                fileInputs.forEach(input => {
                    if (input.required && (!input.files || input.files.length === 0)) {
                        allFilesValid = false;
                        input.parentElement.classList.add('invalid');
                        console.log('Archivo faltante:', input.id || input.name);
                    } else {
                        input.parentElement.classList.remove('invalid');
                    }
                });
                
                if (!allFilesValid) {
                    isValid = false;
                    alert('Por favor, sube todos los documentos requeridos');
                }
            }
            else if (sectionIndex === 2) { // Firma
                if (signaturePad && signaturePad.isEmpty()) {
                    isValid = false;
                    const signatureCanvas = document.getElementById('signature-pad');
                    if (signatureCanvas) {
                        signatureCanvas.classList.add('invalid');
                        signatureCanvas.parentElement.classList.add('invalid');
                    }
                    alert('Por favor, firme el documento antes de continuar.');
                } else if (signaturePad) {
                    const signatureCanvas = document.getElementById('signature-pad');
                    if (signatureCanvas) {
                        signatureCanvas.classList.remove('invalid');
                        signatureCanvas.parentElement.classList.remove('invalid');
                    }
                }
            }
            
            // Si es válido, actualizar el progreso de la navegación
            if (isValid) {
                updateDocumentProgress();
            }
            
            console.log('Resultado de validación:', isValid);
            return isValid;
            
            return isValid;
        }

        // Inicialización de acordeones
        // Función para actualizar el estado visual de las secciones del acordeón
        function updateAccordionStatus() {
            const sections = document.querySelectorAll('.accordion-section');
            sections.forEach((section, index) => {
                const statusSpan = section.querySelector('.accordion-status');
                if (statusSpan) {
                    // Si la sección tiene la clase completed, actualizar su estado
                    if (section.querySelector('.accordion-header').classList.contains('completed')) {
                        statusSpan.textContent = 'Completado';
                        statusSpan.classList.add('completed');
                    } else {
                        statusSpan.textContent = index === 0 ? 'En proceso' : 'Pendiente';
                        statusSpan.classList.remove('completed');
                    }
                }
            });
        }
            
        function initAccordionSections() {
            console.log("Inicializando acordeón");
            const accordionHeaders = document.querySelectorAll('.accordion-header');
            
            // Primero, eliminar todos los event listeners existentes para evitar duplicados
            accordionHeaders.forEach(header => {
                const newHeader = header.cloneNode(true);
                header.parentNode.replaceChild(newHeader, header);
            });
            
            // Volver a seleccionar los headers después de clonarlos
            const newAccordionHeaders = document.querySelectorAll('.accordion-header');
            
            // Inicializar los acordeones (por defecto mostrar el primero, ocultar el resto)
            document.querySelectorAll('.accordion-content').forEach((content, idx) => {
                if (idx === 0) {
                    content.classList.add('active');
                    newAccordionHeaders[0].classList.add('active');
                    content.style.maxHeight = content.scrollHeight + "px";
                } else {
                    content.classList.remove('active');
                    content.style.maxHeight = "0px";
                }
            });
            
            // Actualizar estados iniciales de las secciones
            updateAccordionStatus();
            
            // Event listener para headers de acordeón
            newAccordionHeaders.forEach((header, index) => {
                header.addEventListener('click', function(e) {
                    // Prevenir comportamiento por defecto y propagación
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const content = this.nextElementSibling;
                    const isActive = this.classList.contains('active');
                    
                    console.log('Clic en acordeón', index, 'isActive:', isActive);
                    
                    // Toggle del acordeón
                    if (!isActive) {
                        // Cerrar todos primero
                        newAccordionHeaders.forEach(h => {
                            h.classList.remove('active');
                            const c = h.nextElementSibling;
                            c.classList.remove('active');
                            c.style.maxHeight = "0px";
                        });

                        // Abrir este
                        this.classList.add('active');

                        // Pequeño retraso para la animación
                        setTimeout(() => {
                            content.classList.add('active');
                            content.style.maxHeight = content.scrollHeight + "px";

                            // Si es el acordeón de firma, ya no se usa (flujo condicional)
                            const sectionId = this.parentElement.id;
                            if (sectionId === 'section-firma') {
                                console.log('Acordeón de firma abierto - usando flujo condicional');
                                // initializeSignaturePad(); // Deshabilitado - usa flujo condicional
                            }
                        }, 50);
                    } else {
                        // Cerrar este
                        this.classList.remove('active');
                        content.classList.remove('active');
                        content.style.maxHeight = "0px";
                    }
                });
            });
            
            // Limpiar y volver a asignar event listeners para botones "Continuar"
            const nextButtons = document.querySelectorAll('.section-next-btn');
            nextButtons.forEach((button, index) => {
                // Clonar para eliminar event listeners anteriores
                const newButton = button.cloneNode(true);
                button.parentNode.replaceChild(newButton, button);
                
                // Agregar event listener al nuevo botón
                newButton.addEventListener('click', function(e) {
                    e.preventDefault();
                    console.log('Clic en botón continuar', index);
                    
                    // Validar la sección actual
                    if (validateSection(index)) {
                        // Marcar como completada
                        newAccordionHeaders[index].classList.add('completed');
                        const statusElement = newAccordionHeaders[index].querySelector('.accordion-status');
                        if (statusElement) {
                            statusElement.textContent = 'Completado';
                            statusElement.classList.add('completed');
                        }
                        
                        // Si hay una siguiente sección, activarla
                        if (index < newAccordionHeaders.length - 1) {
                            // Cerrar la actual
                            newAccordionHeaders[index].classList.remove('active');
                            const currentContent = newAccordionHeaders[index].nextElementSibling;
                            currentContent.classList.remove('active');
                            currentContent.style.maxHeight = "0px";
                            
                            // Abrir la siguiente con un ligero retraso para mejorar la animación
                            newAccordionHeaders[index + 1].classList.add('active');
                            const nextContent = newAccordionHeaders[index + 1].nextElementSibling;
                            
                            // Pequeño retraso para la animación
                            setTimeout(() => {
                                nextContent.classList.add('active');
                                nextContent.style.maxHeight = nextContent.scrollHeight + "px";
                            }, 100);
                        }
                        
                        // Actualizar el progreso
                        updateDocumentProgress();
                    }
                });
            });
            
            // Mejorar la experiencia de carga de archivos
            const fileInputs = document.querySelectorAll('input[type="file"]');
            fileInputs.forEach(input => {
                input.addEventListener('change', function() {
                    const fileNameElement = this.parentElement.querySelector('.file-name');
                    if (this.files.length > 0) {
                        fileNameElement.textContent = this.files[0].name;
                        this.parentElement.classList.add('file-uploaded');
                    } else {
                        fileNameElement.textContent = 'Ningún archivo seleccionado';
                        this.parentElement.classList.remove('file-uploaded');
                    }
                });
            });
        }

        // Manejo de cupones
        let debounceTimer;
        function debounceValidateCoupon() {
            if (debounceTimer) clearTimeout(debounceTimer);
            debounceTimer = setTimeout(validateCouponCode, 1000);
        }
        
        function resetCoupon() {
            // Restablecer variables globales
            couponDiscountPercent = 0;
            couponValue = "";
            
            // Restablecer elementos de UI
            if (couponCodeInput) {
                couponCodeInput.classList.remove('coupon-valid','coupon-error','coupon-loading');
                couponCodeInput.value = "";
            }
            
            if (couponMessage) {
                couponMessage.textContent = "";
                couponMessage.classList.add('hidden');
            }
            
            if (discountLi) {
                discountLi.style.display = 'none';
            }
            
            // Ocultar información de cupón en el resumen si existe
            const summaryContainer = document.getElementById('summary-coupon-container');
            if (summaryContainer) {
                summaryContainer.style.display = 'none';
            }
            
            // Actualizar precios
            updateTotal();
        }
        
        function validateCouponCode() {
            // Verificar que tenemos todas las referencias necesarias
            if (!couponCodeInput) {
                console.error('Elemento de entrada de cupón no encontrado');
                return;
            }
            
            const code = couponCodeInput.value.trim();
            if (!code) {
                resetCoupon();
                updateTotal();
                return;
            }
            
            // Mostrar el acordeón de cupón si está cerrado
            const couponAccordion = document.getElementById('coupon-accordion');
            if (couponAccordion) {
                const toggleHeader = couponAccordion.querySelector('.accordion-toggle-header');
                const contentSection = couponAccordion.querySelector('.accordion-content-section');
                
                if (toggleHeader && contentSection) {
                    if (!toggleHeader.classList.contains('active')) {
                        toggleHeader.classList.add('active');
                        contentSection.classList.add('active');
                        // Asegurar que el contenido sea visible ajustando maxHeight
                        contentSection.style.maxHeight = contentSection.scrollHeight + "px";
                    }
                }
            }
            
            // Actualizar la UI para mostrar que estamos verificando
            if (couponCodeInput) {
                couponCodeInput.classList.remove('coupon-valid','coupon-error');
                couponCodeInput.classList.add('coupon-loading');
            }
            
            if (couponMessage) {
                couponMessage.classList.remove('success','error-message','hidden');
                couponMessage.textContent = "Verificando cupón...";
            }

            /* SIMULACIÓN DE CUPONES - Comentado para usar AJAX real
            // Simulación de validación de cupón (para testing inmediato)
            // NOTA: Esta simulación asegura consistencia con transferencia-propiedad.php
            setTimeout(() => {
                // Códigos de cupón de prueba para testing - mismos que en transferencia-propiedad.php
                const validCoupons = {
                    'DESCUENTO10': 10,
                    'DESCUENTO20': 20,
                    'VERANO15': 15,
                    'BLACK50': 50,
                    'SINTOSOSIO': 80,
                };
                
                if (couponCodeInput) {
                    couponCodeInput.classList.remove('coupon-loading');
                }
                
                const upperCode = code.toUpperCase();
                if (validCoupons[upperCode]) {
                    const discount = validCoupons[upperCode];
                    couponDiscountPercent = discount;
                    couponValue = upperCode;
                    
                    if (couponCodeInput) {
                        couponCodeInput.classList.add('coupon-valid');
                    }
                    
                    if (couponMessage) {
                        couponMessage.classList.remove('error-message','hidden');
                        couponMessage.classList.add('success');
                        couponMessage.textContent = "Cupón aplicado correctamente: " + discount + "% de descuento";
                    }
                    
                    if (discountLi) {
                        discountLi.style.display = 'flex';
                    }
                    
                    // Actualizar el texto del contenedor del cupón en el resumen si existe
                    const summaryContainer = document.getElementById('summary-coupon-container');
                    const summaryText = document.getElementById('summary-coupon-info');
                    
                    if (summaryContainer && summaryText) {
                        summaryContainer.style.display = 'block';
                        summaryText.textContent = `Cupón aplicado: ${upperCode} (-${discount}%)`;
                    }
                } else {
                    couponDiscountPercent = 0;
                    couponValue = "";
                    
                    if (couponCodeInput) {
                        couponCodeInput.classList.add('coupon-error');
                    }
                    
                    if (couponMessage) {
                        couponMessage.classList.remove('success','hidden');
                        couponMessage.classList.add('error-message');
                        couponMessage.textContent = "Cupón inválido o expirado";
                    }
                    
                    if (discountLi) {
                        discountLi.style.display = 'none';
                    }
                    
                    // Ocultar el contenedor del cupón en el resumen si existe
                    const summaryContainer = document.getElementById('summary-coupon-container');
                    if (summaryContainer) {
                        summaryContainer.style.display = 'none';
                    }
                }
                
                // Actualizar los precios
                updateTotal();
            }, 800);
            */

            // VERSIÓN AJAX REAL - Backend activado
            const formData = new FormData();
            formData.append('action', 'tpb_validate_coupon');
            formData.append('coupon', code);

            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (couponCodeInput) {
                    couponCodeInput.classList.remove('coupon-loading');
                }
                
                if (data.success) {
                    couponDiscountPercent = data.data.discount_percent;
                    couponValue = code.toUpperCase();
                    
                    if (couponCodeInput) {
                        couponCodeInput.classList.add('coupon-valid');
                    }
                    
                    if (couponMessage) {
                        couponMessage.classList.remove('error-message','hidden');
                        couponMessage.classList.add('success');
                        couponMessage.textContent = "Cupón aplicado correctamente: " + data.data.discount_percent + "% de descuento";
                    }
                    
                    if (discountLi) {
                        discountLi.style.display = 'flex';
                    }
                    
                    // Actualizar el texto del contenedor del cupón en el resumen
                    const summaryContainer = document.getElementById('summary-coupon-container');
                    const summaryText = document.getElementById('summary-coupon-info');
                    
                    if (summaryContainer && summaryText) {
                        summaryContainer.style.display = 'block';
                        summaryText.textContent = `Cupón aplicado: ${couponValue} (-${couponDiscountPercent}%)`;
                    }
                } else {
                    couponDiscountPercent = 0;
                    couponValue = "";
                    
                    if (couponCodeInput) {
                        couponCodeInput.classList.add('coupon-error');
                    }
                    
                    if (couponMessage) {
                        couponMessage.classList.remove('success','hidden');
                        couponMessage.classList.add('error-message');
                        couponMessage.textContent = "Cupón inválido o expirado";
                    }
                    
                    if (discountLi) {
                        discountLi.style.display = 'none';
                    }
                    
                    // Ocultar el contenedor del cupón en el resumen
                    const summaryContainer = document.getElementById('summary-coupon-container');
                    if (summaryContainer) {
                        summaryContainer.style.display = 'none';
                    }
                }
                updateTotal();
            })
            .catch(err => {
                couponDiscountPercent = 0;
                couponValue = "";
                
                if (couponCodeInput) {
                    couponCodeInput.classList.remove('coupon-loading');
                    couponCodeInput.classList.add('coupon-error');
                }
                
                if (couponMessage) {
                    couponMessage.classList.remove('success','hidden');
                    couponMessage.classList.add('error-message');
                    couponMessage.textContent = "Error al validar el cupón";
                }
                
                if (discountLi) {
                    discountLi.style.display = 'none';
                }
                
                updateTotal();
            });
        }

        // =====================================================
        // FASE 2: UPLOAD DE DOCUMENTOS Y PDF
        // =====================================================
        async function uploadDocumentsAndPDF() {
            console.log('📎 Iniciando upload de documentos...');
            console.log('Tramite ID:', purchaseDetails.tramite_id);

            const formData = new FormData();
            formData.append('action', 'tpb_upload_documents');
            formData.append('tramite_id', purchaseDetails.tramite_id);

            // Documentos del comprador
            const dniBuyerFront = document.getElementById('upload_dni_buyer_front')?.files[0];
            const dniBuyerBack = document.getElementById('upload_dni_buyer_back')?.files[0];

            // Documentos del vendedor
            const dniSellerFront = document.getElementById('upload_dni_seller_front')?.files[0];
            const dniSellerBack = document.getElementById('upload_dni_seller_back')?.files[0];

            // Documentos del vehículo
            const vehicleCard = document.getElementById('upload_vehicle_card')?.files[0];
            const contract = document.getElementById('upload_contract')?.files[0];
            const itpReceipt = document.getElementById('upload_itp_receipt')?.files[0];

            console.log('Documentos encontrados:', {
                dniBuyerFront: dniBuyerFront?.name || 'NO',
                dniBuyerBack: dniBuyerBack?.name || 'NO',
                dniSellerFront: dniSellerFront?.name || 'NO',
                dniSellerBack: dniSellerBack?.name || 'NO',
                vehicleCard: vehicleCard?.name || 'NO',
                contract: contract?.name || 'NO',
                itpReceipt: itpReceipt?.name || 'NO'
            });

            // Añadir archivos al FormData
            if (dniBuyerFront) formData.append('dni_buyer_front', dniBuyerFront);
            if (dniBuyerBack) formData.append('dni_buyer_back', dniBuyerBack);
            if (dniSellerFront) formData.append('dni_seller_front', dniSellerFront);
            if (dniSellerBack) formData.append('dni_seller_back', dniSellerBack);
            if (vehicleCard) formData.append('vehicle_card', vehicleCard);
            if (contract) formData.append('contract', contract);
            if (itpReceipt) formData.append('itp_receipt', itpReceipt);

            // Enviar firma para generar PDF
            if (purchaseDetails.signature) {
                formData.append('signature_data', purchaseDetails.signature);
            }

            // Datos necesarios para generar PDF de autorización
            formData.append('customer_name', purchaseDetails.customer_name);
            formData.append('customer_dni', purchaseDetails.customer_dni);
            formData.append('seller_name', purchaseDetails.seller_name);
            formData.append('seller_dni', purchaseDetails.seller_dni);
            formData.append('vehicle_type', purchaseDetails.vehicle_type);
            formData.append('manufacturer', purchaseDetails.manufacturer);
            formData.append('model', purchaseDetails.model);
            formData.append('registration', purchaseDetails.registration);

            console.log('🔄 Enviando documentos a:', '<?php echo admin_url('admin-ajax.php'); ?>');

            try {
                const response = await fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    body: formData
                });

                console.log('📥 Upload response status:', response.status);
                const result = await response.json();
                console.log('📥 Upload result:', result);

                if (result.success) {
                    console.log('✅ Documentos subidos correctamente:', result.data);
                    console.log('   - Archivos:', Object.keys(result.data.files || {}));
                    console.log('   - PDF Autorización:', result.data.authorization_pdf_url || 'NO GENERADO');

                    // Guardar URLs de documentos en purchaseDetails
                    purchaseDetails.uploadedFiles = result.data.files || {};
                    purchaseDetails.authorizationPdfUrl = result.data.authorization_pdf_url || '';
                    return result.data;
                } else {
                    console.error('❌ Error subiendo documentos:', result.data);
                    return null;
                }
            } catch (error) {
                console.error('❌ Error en uploadDocumentsAndPDF:', error);
                return null;
            }
        }

        // =====================================================
        // FASE 3: ENVIAR DATOS A REACT APP via PostMessage
        // =====================================================
        async function sendToReactApp() {
            return new Promise((resolve) => {
                console.log('🚀 Enviando datos a React App...');

                // Preparar payload adaptado al formato del externalApiService
                const payload = {
                    buyer_name: purchaseDetails.customer_name,
                    buyer_dni: purchaseDetails.customer_dni,
                    buyer_email: purchaseDetails.customer_email,
                    buyer_phone: purchaseDetails.customer_phone,
                    buyer_address: purchaseDetails.customer_address || '',
                    buyer_postal_code: purchaseDetails.customer_postal_code || '',
                    buyer_city: purchaseDetails.customer_city || '',
                    buyer_province: purchaseDetails.customer_province || '',

                    seller_name: purchaseDetails.seller_name,
                    seller_dni: purchaseDetails.seller_dni,
                    seller_phone: purchaseDetails.seller_phone || '',
                    seller_email: purchaseDetails.seller_email || '',

                    vehicle_type: purchaseDetails.vehicle_type,
                    manufacturer: purchaseDetails.manufacturer || '',
                    model: purchaseDetails.model || '',
                    year: purchaseDetails.matriculation_date ? new Date(purchaseDetails.matriculation_date).getFullYear().toString() : '',
                    registration: purchaseDetails.registration,
                    hull_number: purchaseDetails.hull_number || '',
                    engine_brand: purchaseDetails.engine_brand || '',
                    engine_serial: purchaseDetails.engine_serial || '',
                    engine_power: purchaseDetails.engine_power || '',
                    purchase_price: purchaseDetails.purchase_price?.toString() || '0',
                    region: purchaseDetails.region || '',

                    itp_paid: purchaseDetails.itpPagado ? 'true' : 'false',
                    itp_management_option: purchaseDetails.itpGestionSeleccionada || '',
                    itp_payment_method: purchaseDetails.itpMetodoPago || '',
                    itp_amount: purchaseDetails.itpAmount?.toString() || '',
                    itp_commission: purchaseDetails.itpComision?.toString() || '',

                    cambio_lista: purchaseDetails.cambioLista ? 'true' : 'false',
                    coupon_used: purchaseDetails.couponCode || '',
                    coupon_discount: purchaseDetails.couponDiscount?.toString() || '',
                    final_amount: purchaseDetails.finalAmount?.toString() || '0',

                    signature: purchaseDetails.signatureData || '',

                    // Documentos individuales (URLs)
                    upload_dni_buyer_front: purchaseDetails.uploadedFiles?.dni_buyer_front?.url || '',
                    upload_dni_buyer_back: purchaseDetails.uploadedFiles?.dni_buyer_back?.url || '',
                    upload_dni_seller_front: purchaseDetails.uploadedFiles?.dni_seller_front?.url || '',
                    upload_dni_seller_back: purchaseDetails.uploadedFiles?.dni_seller_back?.url || '',
                    upload_vehicle_card: purchaseDetails.uploadedFiles?.vehicle_card?.url || '',
                    upload_contract: purchaseDetails.uploadedFiles?.contract?.url || '',
                    upload_itp_receipt: purchaseDetails.uploadedFiles?.itp_receipt?.url || '',

                    // PDF de autorización generado
                    authorization_pdf_url: purchaseDetails.authorizationPdfUrl || '',

                    // Payment info
                    payment_intent_id: purchaseDetails.paymentIntentId || '',
                    tramite_id: purchaseDetails.tramite_id || ''
                };

                console.log('📦 Datos preparados para Tramitfy:', payload);

                // Método 1: Intentar usar window.tramitfyApi si está disponible
                if (window.tramitfyApi && typeof window.tramitfyApi.receiveMotoTransferForm === 'function') {
                    console.log('✅ Usando window.tramitfyApi directamente');
                    window.tramitfyApi.receiveMotoTransferForm(payload)
                        .then(function(result) {
                            console.log('📥 Respuesta de Tramitfy API:', result);
                            if (result.success) {
                                resolve({
                                    success: true,
                                    trackingUrl: result.trackingUrl || '',
                                    trackingToken: result.trackingToken || '',
                                    procedureId: result.procedureId || ''
                                });
                            } else {
                                resolve({
                                    success: false,
                                    error: result.error || 'Error desconocido'
                                });
                            }
                        })
                        .catch(function(error) {
                            console.error('❌ Error en Tramitfy API:', error);
                            resolve({
                                success: false,
                                error: error.message || 'Error desconocido'
                            });
                        });
                } else {
                    // Método 2: Usar postMessage a la misma ventana
                    console.log('ℹ️ window.tramitfyApi no disponible, usando postMessage');

                    const messageHandler = (event) => {
                        if (event.data.type === 'BARCO_TRANSFER_RESPONSE') {
                            window.removeEventListener('message', messageHandler);
                            console.log('📥 Respuesta de Tramitfy postMessage:', event.data);

                            if (event.data.success) {
                                resolve({
                                    success: true,
                                    trackingUrl: event.data.data?.trackingUrl || '',
                                    trackingToken: event.data.data?.trackingToken || '',
                                    procedureId: event.data.data?.procedureId || ''
                                });
                            } else {
                                resolve({
                                    success: false,
                                    error: event.data.error || 'Error desconocido'
                                });
                            }
                        }
                    };

                    window.addEventListener('message', messageHandler);

                    // Enviar postMessage a la misma ventana
                    persistentLog('📡 Enviando postMessage al React App...', 'info');
                    window.postMessage({
                        type: 'BARCO_TRANSFER_FORM',
                        payload: payload
                    }, window.location.origin);
                    persistentLog('📡 PostMessage enviado, esperando respuesta...', 'info');

                    // Timeout de 5 segundos
                    setTimeout(() => {
                        window.removeEventListener('message', messageHandler);
                        persistentLog('⏱️ TIMEOUT: React App no respondió, continuando sin tracking', 'warning');
                        console.warn('⏱️ Timeout: React App no respondió, continuando sin tracking');
                        resolve({
                            success: false,
                            error: 'Timeout esperando respuesta'
                        });
                    }, 5000);
                }
            });
        }

        // Función alternativa: enviar a React App usando fetch (backup)
        async function sendToReactAppFetch() {
            console.log('🔄 Intentando enviar a React App via fetch...');

            const reactApiUrl = 'https://tramitfy.es/app/api/receive-form';

            const payload = {
                type: 'BARCO_TRANSFER',
                tramiteId: purchaseDetails.tramite_id,
                timestamp: purchaseDetails.timestamp,
                data: purchaseDetails
            };

            try {
                const response = await fetch(reactApiUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });

                const result = await response.json();

                if (result.success) {
                    console.log('✅ Datos enviados a React correctamente (fetch):', result);
                    purchaseDetails.trackingUrl = result.data?.trackingUrl || '';
                    purchaseDetails.procedureId = result.data?.procedureId || '';
                    return result;
                } else {
                    console.error('⚠️ React API respondió con error:', result);
                    // No bloquear el flujo si React falla
                    return null;
                }
            } catch (error) {
                console.error('❌ Error enviando a React (continuando flujo):', error);
                // No bloquear el flujo si React no está disponible
                return null;
            }
        }

        // =====================================================
        // FASE 1 & 4: ENVIAR EMAILS (ADMIN + CLIENTE)
        // =====================================================
        async function sendEmails() {
            persistentLog('🚨 === SENDEMAILS FUNCTION INICIADA ===');
            persistentLog('📧 Enviando emails...');
            persistentLog(`🔍 purchaseDetails disponible: ${!!purchaseDetails}`);
            persistentLog(`📋 purchaseDetails keys: ${purchaseDetails ? Object.keys(purchaseDetails).length + ' claves' : 'undefined'}`);

            // Convertir purchaseDetails a URLSearchParams para enviar por POST
            const params = new URLSearchParams();
            params.append('action', 'tpb_send_emails');

            // Añadir todos los datos de purchaseDetails
            Object.keys(purchaseDetails).forEach(key => {
                const value = purchaseDetails[key];
                // Convertir objetos a JSON string
                if (typeof value === 'object' && value !== null) {
                    params.append(key, JSON.stringify(value));
                } else if (value !== undefined && value !== null) {
                    params.append(key, value);
                }
            });

            // Añadir tracking URL si existe
            if (purchaseDetails.trackingUrl) {
                params.append('tracking_url', purchaseDetails.trackingUrl);
            }

            try {
                console.log('🌐 Haciendo fetch a:', '<?php echo admin_url('admin-ajax.php'); ?>');
                console.log('📦 Enviando params:', Array.from(params.entries()));
                
                const response = await fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: params
                });
                
                console.log('📡 Response status:', response.status);
                console.log('📡 Response ok:', response.ok);
                
                const data = await response.json();
                console.log('📄 Response data:', data);
                
                if (data.success) {
                    console.log('✅ Emails enviados exitosamente');
                    return { success: true };
                } else {
                    console.error('❌ Error al enviar los correos:', data.data);
                    return { success: false, error: data.data };
                }
            } catch (error) {
                console.error('🚨🚨🚨 ERROR CRÍTICO EN SENDEMAILS:', error);
                console.error('Stack trace sendEmails:', error.stack);
                console.error('Error message sendEmails:', error.message);
                console.error('purchaseDetails que causó error:', purchaseDetails);
                return { success: false, error: error.message };
            }
        }

        function handleFinalSubmission() {
            if (signaturePad && signaturePad.isEmpty()) {
                alert('Por favor, firme el documento...');
                return;
            }

            // IMPORTANTE: Actualizar el total para asegurar que las variables globales estén correctas
            updateTotal();

            // Mantener visible el overlay en lugar de mostrarlo de nuevo
            // Ya debería estar visible desde el proceso de pago
            // document.getElementById('loading-overlay').style.display = 'flex';

            const alertMessage = document.getElementById('alert-message');
            const alertMessageText = document.getElementById('alert-message-text');
            alertMessage.style.display = 'block';
            alertMessageText.textContent = 'Enviando el formulario...';

            const formData = new FormData(document.getElementById('transferencia-form'));
            formData.append('action', 'submit_barco_form_tpb');
            formData.append('final_amount', finalAmount.toFixed(2));
            formData.append('current_transfer_tax', currentTransferTax.toFixed(2));
            formData.append('current_extra_fee', currentExtraFee.toFixed(2));

            // Leer valores calculados de las variables globales
            // Estos valores se acaban de actualizar en updateTotal() arriba
            const tasasValue = window.currentTasas || 0;
            const ivaValue = window.currentIva || 0;
            const honorariosValue = window.currentHonorarios || 0;

            console.log('Valores económicos a enviar:', {
                tasas: tasasValue,
                iva: ivaValue,
                honorarios: honorariosValue,
                itp: currentTransferTax,
                total: finalAmount
            });

            formData.append('tasas_hidden', tasasValue.toFixed(2));
            formData.append('iva_hidden', ivaValue.toFixed(2));
            formData.append('honorarios_hidden', honorariosValue.toFixed(2));

            // Incluir datos de firma
            const signatureData = getSignatureData();
            if (signatureData) {
                formData.append('signature', signatureData);
            }

            formData.append('coupon_used', couponValue);
            formData.append('cambio_lista', cambioListaSeleccionado ? 'true' : 'false');

            // Añadir el payment_intent_id si existe
            if (purchaseDetails && purchaseDetails.paymentIntentId) {
                formData.append('payment_intent_id', purchaseDetails.paymentIntentId);
            }

            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                console.log('Respuesta del servidor:', data);
                console.log('data.success =', data.success);
                console.log('tracking_url =', data.data?.tracking_url);
                if (data.success) {
                    // Enviar datos a la app React de Tramitfy
                    try {
                        const motoTransferData = {
                            buyer_name: formData.get('buyer_name'),
                            buyer_dni: formData.get('buyer_dni'),
                            buyer_email: formData.get('buyer_email'),
                            buyer_phone: formData.get('buyer_phone'),
                            buyer_address: formData.get('buyer_address'),
                            buyer_postal_code: formData.get('buyer_postal_code'),
                            buyer_city: formData.get('buyer_city'),
                            buyer_province: formData.get('buyer_province'),
                            seller_name: formData.get('seller_name'),
                            seller_dni: formData.get('seller_dni'),
                            seller_phone: formData.get('seller_phone'),
                            seller_email: formData.get('seller_email'),
                            vehicle_type: formData.get('vehicle_type'),
                            manufacturer: formData.get('manufacturer'),
                            model: formData.get('model'),
                            year: formData.get('year'),
                            registration: formData.get('registration'),
                            hull_number: formData.get('hull_number'),
                            engine_brand: formData.get('engine_brand'),
                            engine_serial: formData.get('engine_serial'),
                            engine_power: formData.get('engine_power'),
                            purchase_price: formData.get('purchase_price'),
                            region: formData.get('region'),
                            itp_paid: formData.get('itp_paid'),
                            itp_management_option: formData.get('itp_management_option'),
                            itp_payment_method: formData.get('itp_payment_method'),
                            itp_amount: formData.get('current_transfer_tax'),
                            itp_commission: formData.get('current_extra_fee'),
                            cambio_lista: cambioListaSeleccionado,
                            coupon_used: (typeof cuponActual !== 'undefined' && cuponActual) ? cuponActual.codigo : null,
                            coupon_discount: (typeof cuponActual !== 'undefined' && cuponActual) ? cuponActual.descuento : null,
                            final_amount: formData.get('final_amount'),
                            signature: formData.get('signature'),
                            authorization_pdf_url: data.data?.authorization_pdf_url,
                            invoice_pdf_url: data.data?.invoice_pdf_url
                        };

                        // Enviar mensaje a la app React
                        if (window.parent !== window) {
                            window.parent.postMessage({
                                type: 'BARCO_TRANSFER_FORM',
                                payload: motoTransferData
                            }, 'https://tramitfy.es');
                        }

                        console.log('Datos enviados a React:', motoTransferData);
                    } catch (error) {
                        console.error('Error enviando datos a React:', error);
                    }

                    alertMessageText.textContent = '¡Formulario enviado con éxito! Redirigiendo...';

                    // Actualizar el mensaje en el overlay también
                    const messageEl = document.querySelector('.loading-message');
                    if (messageEl) {
                        messageEl.textContent = '¡Trámite completado con éxito! Redirigiendo...';
                    }
                    const titleEl = document.querySelector('.loading-title');
                    if (titleEl) {
                        titleEl.textContent = '¡Proceso Finalizado!';
                        titleEl.style.color = 'rgb(var(--success))';
                    }

                    // Redirigir después de un breve retraso para que se vea el estado final
                    setTimeout(() => {
                        // Usar la URL de tracking devuelta por el servidor
                        // Redirigir a página de pago completado en lugar del seguimiento
                        const pagoCompletadoUrl = 'https://tramitfy.es/pago-realizado-con-exito/';
                        console.log('Redirigiendo a página de pago completado:', pagoCompletadoUrl);
                        window.location.href = pagoCompletadoUrl;
                    }, 1500);
                } else {
                    // Si hay un error pero los emails ya se enviaron, continuar de todos modos
                    console.warn('⚠️ Error en respuesta del servidor pero continuando:', data);
                    
                    // Si tenemos el tramite ID, redirigir de todos modos
                    if (purchaseDetails && purchaseDetails.tramite_id) {
                        console.log('✅ Emails enviados, redirigiendo con tramite ID:', purchaseDetails.tramite_id);
                        alertMessageText.textContent = 'Procesando... Redirigiendo al seguimiento';
                        
                        setTimeout(() => {
                            const pagoCompletadoUrl = 'https://tramitfy.es/pago-realizado-con-exito/';
                            console.log('Redirigiendo a página de pago completado:', pagoCompletadoUrl);
                            window.location.href = pagoCompletadoUrl;
                        }, 1500);
                    } else {
                        // Solo mostrar error si realmente falló algo crítico
                        const errorMessage = data.data?.message || data.message || 'Error procesando el formulario';
                        alertMessageText.textContent = 'Error: ' + errorMessage;
                        document.getElementById('loading-overlay').style.display = 'none';
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alertMessageText.textContent = 'Hubo un error al enviar el formulario.';
                document.getElementById('loading-overlay').style.display = 'none';
            });
        }

        // Funcionalidad para el "No encuentro mi modelo"
        function updateNoEncuentroBehavior() {
            if (noEncuentroCheckbox.checked) {
                // Ocultar campos CSV
                vehicleCsvSection.style.display = 'none';
                basePrice = 0;

                // Mostrar campos manuales con animación
                manualFields.style.display = 'block';
                manualFields.style.opacity = '0';
                setTimeout(() => {
                    manualFields.style.transition = 'opacity 0.3s ease';
                    manualFields.style.opacity = '1';
                }, 50);

                // Ocultar fecha matriculación y su contenedor completo
                const matriculationContainer = matriculationDateInput?.closest('.form-group');
                if (matriculationContainer) {
                    matriculationContainer.style.display = 'none';
                }
                matriculationDateInput.removeAttribute('required');

                // Limpiar selecciones anteriores
                manufacturerSelect.value = '';
                modelSelect.value = '';
                modelSelect.innerHTML = '<option value="">Seleccione un modelo</option>';
            } else {
                // Mostrar campos CSV
                vehicleCsvSection.style.display = 'block';
                manualFields.style.display = 'none';

                // Mostrar fecha matriculación
                const matriculationContainer = matriculationDateInput?.closest('.form-group');
                if (matriculationContainer) {
                    matriculationContainer.style.display = 'block';
                }
                matriculationDateInput.setAttribute('required', 'required');

                // Limpiar campos manuales
                document.getElementById('manual_manufacturer').value = '';
                document.getElementById('manual_model').value = '';

                const selectedOption = modelSelect.options[modelSelect.selectedIndex];
                basePrice = selectedOption && selectedOption.dataset.price ? parseFloat(selectedOption.dataset.price) : 0;
            }
        }

        function updateVehicleSelection() {
            document.querySelectorAll('.radio-group label').forEach(label => label.classList.remove('selected'));
            // Buscar el radio button de "Moto de Agua" o "Barco" y seleccionar su label
            const vehicleRadios = document.querySelectorAll('input[name="vehicle_type"]');
            vehicleRadios.forEach(radio => {
                if (radio.checked && radio.parentElement) {
                    radio.parentElement.classList.add('selected');
                }
            });
        }

        function updateAdditionalInputs() {
            const cambioNombreCheckbox = document.querySelector('input[value="Cambio de nombre"]');
            const cambioPuertoCheckbox = document.querySelector('input[value="Cambio de puerto base"]');
            const nombreInputDiv = document.getElementById('nombre-input');
            const puertoInputDiv = document.getElementById('puerto-input');

            if (nombreInputDiv) {
                if (cambioNombreCheckbox && cambioNombreCheckbox.checked) {
                    nombreInputDiv.style.display = 'block';
                    // Forzar reflow para asegurar que la transición funcione
                    nombreInputDiv.getBoundingClientRect();
                    nombreInputDiv.classList.add('campo-activo');
                } else {
                    // Primero quitar la clase para la transición
                    nombreInputDiv.classList.remove('campo-activo');
                    // Después de un breve retraso, ocultar el elemento
                    setTimeout(() => {
                        nombreInputDiv.style.display = 'none';
                        // Reiniciar el valor si se desmarca
                        const nombreInput = document.getElementById('nuevo_nombre');
                        if (nombreInput) nombreInput.value = '';
                    }, 200);
                }
            }
            
            if (puertoInputDiv) {
                if (cambioPuertoCheckbox && cambioPuertoCheckbox.checked) {
                    puertoInputDiv.style.display = 'block';
                    // Forzar reflow para asegurar que la transición funcione
                    puertoInputDiv.getBoundingClientRect();
                    puertoInputDiv.classList.add('campo-activo');
                } else {
                    // Primero quitar la clase para la transición
                    puertoInputDiv.classList.remove('campo-activo');
                    // Después de un breve retraso, ocultar el elemento
                    setTimeout(() => {
                        puertoInputDiv.style.display = 'none';
                        // Reiniciar el valor si se desmarca
                        const puertoInput = document.getElementById('nuevo_puerto');
                        if (puertoInput) puertoInput.value = '';
                    }, 200);
                }
            }
            
            // Actualizar altura máxima del acordeón si está abierto
            setTimeout(() => {
                const accordionContent = document.querySelector('#services-accordion .accordion-content-section');
                if (accordionContent && accordionContent.classList.contains('active')) {
                    accordionContent.style.maxHeight = accordionContent.scrollHeight + 'px';
                }
            }, 50);
        }
        
        // Función para actualizar los pasos del overlay de carga
        function updateLoadingStep(stepId) {
            const steps = document.querySelectorAll('.loading-step');
            let foundActive = false;
            
            steps.forEach(step => {
                const currentStep = step.getAttribute('data-step');
                
                if (currentStep === stepId) {
                    // Este es el paso activo actual
                    step.classList.add('active');
                    step.classList.remove('completed');
                    foundActive = true;
                } else if (foundActive) {
                    // Pasos futuros (después del activo)
                    step.classList.remove('active', 'completed');
                } else {
                    // Pasos anteriores (ya completados)
                    step.classList.remove('active');
                    step.classList.add('completed');
                }
            });
        }

        function populateManufacturers() {
            logDebug('CSV', '📥 Iniciando carga de fabricantes desde CSV');
            const vehicleType = 'Embarcación';
            const csvFile = 'BARCO.csv';
            const csvUrl = '<?php echo get_template_directory_uri(); ?>/' + csvFile;
            logDebug('CSV', 'URL del CSV:', csvUrl);

            fetch(csvUrl)
                .then(response => {
                    logDebug('CSV', 'Respuesta recibida, status:', response.status);
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.text();
                })
                .then(data => {
                    logDebug('CSV', 'Datos CSV recibidos, longitud:', data.length);
                    const manufacturers = {};
                    const rows = data.split('\n').slice(1);
                    logDebug('CSV', 'Número de filas (sin header):', rows.length);

                    rows.forEach((row, index) => {
                        const [fabricante, modelo, precio] = row.split(',');
                        if (fabricante && fabricante.trim()) {
                            if (!manufacturers[fabricante]) {
                                manufacturers[fabricante] = [];
                                logDebug('CSV', `Nuevo fabricante encontrado: ${fabricante}`);
                            }
                            manufacturers[fabricante].push({ modelo, precio });
                        }
                    });

                    logDebug('CSV', 'Fabricantes procesados:', Object.keys(manufacturers).length);

                    manufacturerSelect.innerHTML = '<option value="">Seleccione un fabricante</option>';
                    Object.keys(manufacturers).forEach(fab => {
                        const option = document.createElement('option');
                        option.value = fab;
                        option.textContent = fab;
                        manufacturerSelect.appendChild(option);
                    });

                    logDebug('CSV', '✅ Fabricantes cargados en el select');
                })
                .catch(error => {
                    logError('CSV', '❌ Error al cargar fabricantes:', error);
                });
        }

        // Ajustar label de la hoja de asiento según vehículo
        function updateDocumentLabels() {
            const vehicleType = 'Embarcación'; // Fijo para transferencia de barcos
            const labelHojaAsiento = document.getElementById('label-hoja-asiento');
            const inputHojaAsiento = document.getElementById('upload-hoja-asiento');
            const viewExampleLink = document.getElementById('view-example-hoja-asiento');
            
            if (vehicleType === 'Embarcación') {
                labelHojaAsiento.textContent = 'Registro marítimo';
                inputHojaAsiento.name = 'upload_registro_maritimo';
                viewExampleLink.setAttribute('data-doc', 'registro-maritimo');
            } else {
                labelHojaAsiento.textContent = 'Copia del registro marítimo';
                inputHojaAsiento.name = 'upload_hoja_asiento';
                viewExampleLink.setAttribute('data-doc', 'hoja-asiento');
            }
        }

        function onInputChange() {
            if (purchasePriceInput.value && (noEncuentroCheckbox.checked || (matriculationDateInput.value && regionSelect.value))) {
                updateTransferTaxDisplay();
                updateTotal();
            }
        }

        function onDocumentFieldsInput() {
            // No generar automáticamente el documento aquí
            // Solo se generará cuando esté en la vista de firma
        }

        // Event listeners
        // Escuchar clics en los ítems de navegación mejorados
        document.querySelectorAll('.nav-item').forEach(item => {
            item.addEventListener('click', (e) => {
                e.preventDefault();
                const pageId = item.getAttribute('data-page-id');
                const pageIndex = getPageIndexById(pageId);
                
                // Solo permitir navegación a páginas anteriores o a la siguiente
                const formPageIndex = currentPage; // Sin ajuste ya que no hay pre-páginas
                const targetPageIndex = pageIndex;
                
                // Verificar si podemos navegar a esa página
                // Criterios: puede ser una página anterior o la siguiente, pero no más adelante
                if (pageIndex !== -1 && (targetPageIndex <= formPageIndex || targetPageIndex === formPageIndex + 1)) {
                    // Animar la transición
                    window.prevPage = currentPage;
                    currentPage = pageIndex;
                    updateForm();
                } else if (targetPageIndex > formPageIndex + 1) {
                    // Feedback visual para páginas futuras no disponibles aún
                    item.classList.add('nav-item-blocked');
                    setTimeout(() => {
                        item.classList.remove('nav-item-blocked');
                    }, 500);
                }
            });
        });

        prevButton.addEventListener('click', () => {
            // SINCRONIZACIÓN ESPECIAL: Página documentos (índice 3)
            if (currentPage === 3) { // page-documentos
                const documentsCheckbox = document.getElementById('documents-complete-check');
                const isSignatureMode = documentsCheckbox && documentsCheckbox.checked;
                
                if (isSignatureMode) {
                    // Estamos en modo firma, volver a modo documentos
                    persistentLog('📋 SINCRONIZACIÓN: Volviendo a documentos desde firma');
                    documentsCheckbox.checked = false;
                    documentsCheckbox.dispatchEvent(new Event('change'));
                    return; // No retroceder página, quedarse en documentos
                }
                // Si está en modo documentos normal, retroceder normal
            }
            
            if (currentPage > 0) currentPage--;
            updateForm();
        });

        persistentLog('🔧 DEFINIENDO EVENT LISTENER PARA NEXTBUTTON...');
        nextButton.addEventListener('click', async (e) => {
            persistentLog('🚨 NEXTBUTTON CLICKED - INICIO DEL LISTENER');
            e.preventDefault();
            const isLastPage = (currentPage === formPages.length - 1);
            persistentLog(`🔍 isLastPage: ${isLastPage}`);

            // SINCRONIZACIÓN ESPECIAL: Página documentos (índice 3)
            if (currentPage === 3) { // page-documentos
                const documentsCheckbox = document.getElementById('documents-complete-check');
                const isDocumentsMode = !documentsCheckbox || !documentsCheckbox.checked;
                
                if (isDocumentsMode) {
                    // Estamos en modo documentos normal, activar firma
                    persistentLog('📋 SINCRONIZACIÓN: Activando firma desde botón Siguiente');
                    if (documentsCheckbox) {
                        documentsCheckbox.checked = true;
                        documentsCheckbox.dispatchEvent(new Event('change'));
                    }
                    return; // No avanzar página, quedarse en firma
                }
                // Si ya está en modo firma, continuar normal (ir a pago)
            }

            if (!isLastPage) {
                persistentLog('📄 No es última página, navegando...');
                currentPage++;
                updateForm();
            } else {
                persistentLog('💳 ES ÚLTIMA PÁGINA - PROCESANDO PAGO...');
                if (!document.querySelector('input[name="terms_accept_pago"]').checked) {
                    // Mostrar mensaje en la UI en lugar de alert
                    const paymentMessage = document.getElementById('payment-message');
                    if (paymentMessage) {
                        paymentMessage.textContent = 'Debe aceptar los términos y condiciones de pago para continuar.';
                        paymentMessage.className = 'error';
                        paymentMessage.classList.remove('hidden');
                    }
                    return;
                }

                document.getElementById('loading-overlay').style.display = 'flex';
                nextButton.disabled = true;

                const paymentMessage = document.getElementById('payment-message');
                paymentMessage.classList.remove('success', 'error');
                paymentMessage.classList.add('hidden');

                try {
                    persistentLog('💳 INICIANDO CONFIRMACIÓN DE PAGO CON STRIPE...');
                    persistentLog(`🔑 stripe disponible: ${!!stripe}`);
                    persistentLog(`🎛️ elements disponible: ${!!elements}`);
                    persistentLog(`🔐 clientSecret disponible: ${!!window.stripeClientSecret}`);
                    
                    if (!stripe || !elements) {
                        console.error("Stripe no ha sido inicializado correctamente");
                        throw new Error("Error en la configuración del sistema de pago");
                    }
                    
                    // Usar confirmCardPayment para Card Element
                    const { error, paymentIntent } = await stripe.confirmCardPayment(
                        window.stripeClientSecret,
                        {
                            payment_method: {
                                card: elements.getElement('card'),
                                billing_details: {
                                    name: customerNameInput.value.trim(),
                                    email: customerEmailInput.value.trim(),
                                    phone: customerPhoneInput.value.trim(),
                                }
                            }
                        }
                    );
                    
                    persistentLog('📡 RESPUESTA DE STRIPE RECIBIDA');
                    persistentLog(`❌ error: ${error ? error.message : 'ninguno'}`);
                    persistentLog(`✅ paymentIntent: ${paymentIntent ? 'recibido' : 'null'}`);
                    
                    if (error) {
                        console.error("Error en el pago:", error);
                        paymentMessage.textContent = error.message;
                        paymentMessage.classList.add('error');
                        paymentMessage.classList.remove('hidden');
                        document.getElementById('loading-overlay').style.display = 'none';
                        nextButton.disabled = false;
                        return;
                    }

                    paymentMessage.textContent = 'Pago realizado con éxito.';
                    paymentMessage.classList.add('success');
                    paymentMessage.classList.remove('hidden');

                    paymentCompleted = true;

                    persistentLog('🚨🚨🚨 PAGO EXITOSO - INICIANDO PROCESO DE EMAILS 🚨🚨🚨');
                    persistentLog('🔍 FLUJO: nextButton + confirmCardPayment');
                    persistentLog('📧 Debe ejecutar sendEmails() ahora...');

                    // PASO 0: Generar ID de trámite PRIMERO (igual que el segundo flujo)
                    console.log('🔢 Generando ID de trámite...');
                    let tramiteId = '';
                    try {
                        const idResponse = await fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: 'action=tpb_generate_tramite_id'
                        });
                        const idResult = await idResponse.json();
                        if (idResult.success) {
                            tramiteId = idResult.data.tramite_id;
                            console.log('✅ Trámite ID generado:', tramiteId);
                        } else {
                            console.error('❌ Error generando tramite ID:', idResult);
                            tramiteId = 'TMA-TRANS-' + Date.now(); // Fallback
                        }
                    } catch (error) {
                        console.error('❌ Error en generación de ID:', error);
                        tramiteId = 'TMA-TRANS-' + Date.now(); // Fallback
                    }

                    // 🚨 AJUSTE CRÍTICO: Si es pago fraccionado, ajustar finalAmount para email/webhook
                    let finalAmountParaEmail = finalAmount;
                    let totalAmountParaEmail = finalAmount.toFixed(2);
                    
                    if (gestionamosITP && itpMetodoPago === 'transferencia') {
                        finalAmountParaEmail = 174.99; // SOLO LO NUESTRO para email/webhook
                        totalAmountParaEmail = '174.99';
                        console.log('💰 AJUSTE PAGO FRACCIONADO PARA EMAIL:');
                        console.log('   📊 Total original:', finalAmount, '€');
                        console.log('   💳 Email mostrará:', finalAmountParaEmail, '€');
                        console.log('   🏦 ITP por transferencia:', currentTransferTax, '€');
                    }

                    // DEBUG: Verificar variables ITP antes de construir purchaseDetails
                    console.log('🔍 DEBUG ITP ANTES DE PURCHASEDETAILS:');
                    console.log('itpPagado:', itpPagado);
                    console.log('itpGestionSeleccionada:', itpGestionSeleccionada);
                    console.log('itpMetodoPago:', itpMetodoPago);
                    console.log('currentTransferTax:', currentTransferTax);

                    purchaseDetails = {
                        // Identificación del trámite
                        tramite_id: tramiteId,
                        paymentIntentId: paymentIntent.id,
                        paymentStatus: paymentIntent.status,
                        timestamp: new Date().toISOString(),

                        // Cliente (Comprador)
                        customerName: customerNameInput.value.trim(),
                        customerEmail: customerEmailInput.value.trim(),
                        customerPhone: customerPhoneInput.value.trim(),
                        customerDNI: customerDniInput.value.trim(),

                        // Vehículo
                        vehicleType: 'barco',
                        manufacturer: selectedManufacturer || 'GENÉRICO',
                        model: selectedModel || 'GENÉRICO',
                        matriculationDate: document.getElementById('fecha_matriculacion').value || '',
                        purchasePrice: parseFloat(document.getElementById('precio_compra').value) || 0,
                        region: document.getElementById('region').value || '',

                        // Nuevo propietario
                        nuevoNombre: document.getElementById('nuevo_nombre').value.trim(),
                        nuevoPuerto: document.getElementById('nuevo_puerto').value.trim(),

                        // Financieros (AJUSTADOS para pago fraccionado si aplica)
                        finalAmount: finalAmountParaEmail, // AJUSTADO para pago fraccionado
                        totalAmount: totalAmountParaEmail, // AJUSTADO para pago fraccionado
                        current_transfer_tax: currentTransferTax,
                        current_extra_fee: currentExtraFee,
                        tasas_hidden: window.currentTasas || 0,
                        iva_hidden: window.currentIva || 0,
                        honorarios_hidden: window.currentHonorarios || 0,
                        honorariosNetos: (window.currentHonorarios || 0) / 1.21,
                        pagoFraccionado: (gestionamosITP && itpMetodoPago === 'transferencia'),

                        // Descuentos y extras
                        couponUsed: couponValue,
                        cambioLista: cambioListaSeleccionado,
                        cambioListaPrecio: 0,
                        
                        // ITP - Variables críticas para email
                        itp_paid: itpPagado ? 'true' : '',
                        itp_management_option: itpGestionSeleccionada,
                        itp_payment_method: itpMetodoPago,
                        itp_amount: currentTransferTax,
                        itp_commission: (itpMetodoPago === 'tarjeta' ? itpComisionTarjeta : 0),
                        itp_total_amount: itpTotalAmount
                    };

                    // 3. Enviar emails y esperar a que se completen
                    console.log('🚨 === PUNTO CRÍTICO: ANTES DE SENDEMAILS() ===');
                    console.log('📧 Iniciando envío de emails...');
                    console.log('🔍 Estado purchaseDetails:', JSON.stringify(purchaseDetails, null, 2));
                    const emailResult = await sendEmails();
                    console.log('🚨 === PUNTO CRÍTICO: DESPUÉS DE SENDEMAILS() ===');
                    console.log('📤 Resultado sendEmails:', emailResult);
                    
                    if (emailResult.success) {
                        console.log('✅ Emails completados, continuando con submission final');
                    } else {
                        console.error('⚠️ Error en emails, pero continuando:', emailResult.error);
                    }
                    
                    // 4. Procesar submission final
                    handleFinalSubmission();

                } catch (err) {
                    console.error("🚨🚨🚨 ERROR CRÍTICO EN EL FLUJO:", err);
                    console.error("Stack trace:", err.stack);
                    console.error("Error message:", err.message);
                    paymentMessage.textContent = 'Ocurrió un error al procesar el pago: ' + err.message;
                    paymentMessage.classList.add('error');
                    paymentMessage.classList.remove('hidden');
                    document.getElementById('loading-overlay').style.display = 'none';
                    nextButton.disabled = false;
                    
                    // IMPORTANTE: NO llama handleFinalSubmission() en caso de error
                    console.error("🚨 FLUJO INTERRUMPIDO - NO SE EJECUTARÁ handleFinalSubmission()");
                }
            }
        });


        const infoLink = document.getElementById('info-link');
        if (infoLink) {
            infoLink.addEventListener('click', function(e) {
                e.preventDefault();
                const itpDetailContainer = document.getElementById('itp-detail-container');
                const infoButtonText = document.getElementById('info-button-text');
                const isVisible = itpDetailContainer.style.display !== 'none';

                if (isVisible) {
                    itpDetailContainer.style.display = 'none';
                    infoButtonText.textContent = 'Ver detalle del cálculo del ITP';
                } else {
                    itpDetailContainer.style.display = 'block';
                    infoButtonText.textContent = 'Ocultar detalle del cálculo';
                }
            });
        }

        const docPopup = document.getElementById('document-popup');
        const exampleImage = document.getElementById('document-example-image');

        if (docPopup) {
            const closePopup = docPopup.querySelector('.close-popup');

            document.querySelectorAll('.view-example').forEach(link => {
                link.addEventListener('click', function(event) {
                    event.preventDefault();
                    const docType = this.getAttribute('data-doc');
                    if (exampleImage) exampleImage.src = '/wp-content/uploads/exampledocs/' + docType + '.jpg';
                    docPopup.style.display = 'block';
                });
            });

            if (closePopup) {
                closePopup.addEventListener('click', () => {
                    docPopup.style.display = 'none';
                });
            }

            window.addEventListener('click', function(event) {
                if (event.target === docPopup) {
                    docPopup.style.display = 'none';
                }
            });
        }

        if (purchasePriceInput) {
            purchasePriceInput.addEventListener('input', function() {
                this.value = this.value.replace(/[.,]/g, '');
                onInputChange();
            });
        }

        if (regionSelect) {
            regionSelect.addEventListener('change', onInputChange);
        }

        // Manejar el checkbox de ITP ya pagado
        const itpAlreadyPaidCheckbox = document.getElementById('itp_already_paid');
        const itpPaymentProofRow = document.getElementById('itp-payment-proof-row');
        const itpComprobante = document.getElementById('upload-itp-comprobante');

        if (itpAlreadyPaidCheckbox) {
            itpAlreadyPaidCheckbox.addEventListener('change', function() {
                // Mostrar/ocultar campo para subir comprobante
                if (itpPaymentProofRow) itpPaymentProofRow.style.display = this.checked ? 'flex' : 'none';

                // Cambiar si el campo es requerido o no
                if (itpComprobante) itpComprobante.required = this.checked;

                // Actualizar cálculos
                onInputChange();
            });
        }
        if (matriculationDateInput) {
            matriculationDateInput.addEventListener('change', onInputChange);
        }
        
        extraOptions.forEach(opt => opt.addEventListener('change', () => {
            updateAdditionalInputs();
            updateTotal();
        }));

        if (manufacturerSelect) {
            logInfo('MANUFACTURER', '✅ Event listener añadido a manufacturerSelect');
            manufacturerSelect.addEventListener('change', function() {
                perfStart('manufacturer-change');
                const selectedFabricante = this.value;
                logInfo('MANUFACTURER', `📦 Fabricante seleccionado: ${selectedFabricante}`);

                modelSelect.innerHTML = '<option value="">Seleccione un modelo</option>';
                basePrice = 0;
                onInputChange();

                if (selectedFabricante) {
                    logDebug('MANUFACTURER', 'Cargando modelos desde PHP...', { fabricante: selectedFabricante });
                // Cargar modelos desde PHP (ya tenemos los datos CSV en PHP)
                <?php
                // Cargar CSV y generar estructura JS
                // Primero intentar desde el directorio del tema, si no desde el directorio actual
                $csv_file = get_template_directory() . '/BARCO.csv';
                if (!file_exists($csv_file)) {
                    // Si no está en el tema, buscar en el directorio del formulario
                    $csv_file = dirname(__FILE__) . '/BARCO.csv';
                }

                $modelos_por_fabricante = array();

                if (file_exists($csv_file) && ($handle = fopen($csv_file, 'r')) !== false) {
                    // NO saltar encabezado porque no hay
                    while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                        if (count($row) >= 3) {
                            $fab = trim($row[0]);
                            $mod = trim($row[1]);
                            $precio = trim($row[2]);
                            if (!isset($modelos_por_fabricante[$fab])) {
                                $modelos_por_fabricante[$fab] = array();
                            }
                            $modelos_por_fabricante[$fab][] = array('modelo' => $mod, 'precio' => $precio);
                        }
                    }
                    fclose($handle);
                }
                ?>

                // Datos de modelos cargados desde PHP
                const modelosData = <?php echo json_encode($modelos_por_fabricante); ?>;

                logDebug('MANUFACTURER', `📊 Total fabricantes en CSV: ${Object.keys(modelosData).length}`);

                if (modelosData[selectedFabricante]) {
                    const modelos = modelosData[selectedFabricante];
                    logSuccess('MANUFACTURER', `✅ ${modelos.length} modelos encontrados para ${selectedFabricante}`);

                    modelos.forEach((item, index) => {
                        const option = document.createElement('option');
                        option.value = item.modelo;
                        option.textContent = item.modelo;
                        option.dataset.price = item.precio;
                        modelSelect.appendChild(option);

                        if (index < 3) {
                            logDebug('MANUFACTURER', `  → Modelo ${index + 1}: ${item.modelo} (${item.precio}€)`);
                        }
                    });

                    logInfo('MANUFACTURER', `Modelos cargados en el select`);
                } else {
                    logWarning('MANUFACTURER', `⚠️ No se encontraron modelos para: ${selectedFabricante}`);
                }

                perfEnd('manufacturer-change');
            }
            });
        } else {
            logError('MANUFACTURER', '❌ manufacturerSelect no encontrado');
        }

        if (modelSelect) {
            logInfo('MODEL', '✅ Event listener añadido a modelSelect');
            modelSelect.addEventListener('change', function() {
                if (!noEncuentroCheckbox.checked) {
                    const selectedOption = this.options[this.selectedIndex];
                    basePrice = selectedOption ? parseFloat(selectedOption.dataset.price) : 0;
                } else {
                    basePrice = 0;
                }
                onInputChange();
            });
        } else {
            logError('MODEL', '❌ modelSelect no encontrado');
        }

        noEncuentroCheckbox.addEventListener('change', () => {
            const checked = noEncuentroCheckbox.checked;
            logInfo('NO-ENCUENTRO', `🔄 Checkbox cambiado: ${checked ? 'ACTIVADO' : 'DESACTIVADO'}`);

            perfStart('no-encuentro-toggle');
            updateNoEncuentroBehavior();
            onInputChange();

            // Actualizar sidebar cuando cambia el checkbox
            const currentPageId = formPages[currentPage]?.id;
            if (currentPageId === 'page-vehiculo') {
                actualizarSidebarDinamico('page-vehiculo');
                logDebug('NO-ENCUENTRO', 'Sidebar actualizado');
            }

            perfEnd('no-encuentro-toggle');
            logSuccess('NO-ENCUENTRO', checked ? '✅ Modo manual activado' : '✅ Modo CSV activado');
        });

        if (couponCodeInput) {
            couponCodeInput.addEventListener('input', debounceValidateCoupon);
        }

        const applyCouponBtn = document.getElementById('apply-coupon');
        if (applyCouponBtn) {
            applyCouponBtn.addEventListener('click', function() {
                const couponInput = document.getElementById('coupon_code');
                if (couponInput && couponInput.value.trim()) {
                    debounceValidateCoupon();
                }
            });
        }

        customerNameInput.addEventListener('input', onDocumentFieldsInput);
        customerDniInput.addEventListener('input', onDocumentFieldsInput);
        customerEmailInput.addEventListener('input', onDocumentFieldsInput);
        customerPhoneInput.addEventListener('input', onDocumentFieldsInput);

        // Actualizar vista previa del documento en sidebar cuando cambian los datos
        customerNameInput.addEventListener('input', function() {
            const previewNameEl = document.getElementById('preview-name-sidebar');
            if (previewNameEl) {
                previewNameEl.textContent = this.value || '_____________';
            }
        });

        customerDniInput.addEventListener('input', function() {
            const previewDniEl = document.getElementById('preview-dni-sidebar');
            if (previewDniEl) {
                previewDniEl.textContent = this.value || '_____________';
            }
        });

        // Actualizar resumen de vehículo en sidebar de página Datos
        function updateVehicleSummary() {
            const sidebarFabricanteEl = document.getElementById('sidebar-datos-fabricante');
            const sidebarModeloEl = document.getElementById('sidebar-datos-modelo');
            const sidebarFechaEl = document.getElementById('sidebar-datos-fecha');
            const sidebarPrecioEl = document.getElementById('sidebar-datos-precio');

            if (sidebarFabricanteEl) {
                const fabricante = noEncuentroCheckbox.checked
                    ? document.getElementById('manual_manufacturer').value
                    : manufacturerSelect.value;
                sidebarFabricanteEl.textContent = fabricante || '-';
            }

            if (sidebarModeloEl) {
                const modelo = noEncuentroCheckbox.checked
                    ? document.getElementById('manual_model').value
                    : modelSelect.value;
                sidebarModeloEl.textContent = modelo || '-';
            }

            if (sidebarFechaEl) {
                const fecha = matriculationDateInput.value;
                sidebarFechaEl.textContent = fecha ? new Date(fecha).toLocaleDateString('es-ES') : '-';
            }

            if (sidebarPrecioEl) {
                const precio = purchasePriceInput.value;
                sidebarPrecioEl.textContent = precio ? precio + ' €' : '-';
            }
        }

        manufacturerSelect.addEventListener('change', updateVehicleSummary);
        modelSelect.addEventListener('change', updateVehicleSummary);
        matriculationDateInput.addEventListener('change', updateVehicleSummary);
        purchasePriceInput.addEventListener('input', updateVehicleSummary);

        const manualManufacturer = document.getElementById('manual_manufacturer');
        if (manualManufacturer) {
            manualManufacturer.addEventListener('input', updateVehicleSummary);
        }

        const manualModel = document.getElementById('manual_model');
        if (manualModel) {
            manualModel.addEventListener('input', updateVehicleSummary);
        }

        // Botón clear-signature obsoleto - DESHABILITADO
        const clearSignatureBtn = document.getElementById('clear-signature');
        if (clearSignatureBtn) {
            console.log('⚠️ clear-signature encontrado pero obsoleto - usar clear-signature-form');
            // Event listener deshabilitado - se usa clear-signature-form en flujo condicional
        }

        // Botón confirm-signature obsoleto - DESHABILITADO
        const confirmSignatureBtn = document.getElementById('confirm-signature');
        if (confirmSignatureBtn) {
            console.log('⚠️ confirm-signature encontrado pero obsoleto - usar validate-signature-form');
            // Event listener deshabilitado - se usa validate-signature-form en flujo condicional
        } else if (false) {
            console.log('DEBUG: confirmSignatureBtn encontrado', confirmSignatureBtn);
            confirmSignatureBtn.addEventListener('click', function() {
                console.log('DEBUG: confirmSignatureBtn clicked', { signaturePad, formPages });
                if (!signaturePad) {
                    alert('Error: El sistema de firma no está inicializado. Por favor, recarga la página.');
                    return;
                }
                if (signaturePad && !signaturePad.isEmpty()) {
                // Hay firma, navegar a la página de pago
                // Buscar índice de la página de pago (formPages es NodeList, no Array)
                let pagoIndex = -1;
                for (let i = 0; i < formPages.length; i++) {
                    if (formPages[i].id === 'page-pago') {
                        pagoIndex = i;
                        break;
                    }
                }

                if (pagoIndex !== -1) {
                    currentPage = pagoIndex;
                    updateForm();
                    // El scroll ya se hace en updateForm()
                }
            } else {
                // No hay firma, mostrar alerta
                alert('Por favor, firma el documento antes de continuar.');
            }
            });
        }

        // Event listeners para flujo condicional de firma
        const documentosConfirmadosFirma = document.getElementById('documentos-confirmados-firma');
        if (documentosConfirmadosFirma) {
            documentosConfirmadosFirma.addEventListener('change', function() {
                const firmaStepConfirmacion = document.getElementById('firma-step-confirmacion');
                const firmaStepSignature = document.getElementById('firma-step-signature');
                
                if (this.checked) {
                    console.log('✅ Documentos confirmados - mostrando paso firma');
                    
                    // Ocultar confirmación y mostrar firma
                    firmaStepConfirmacion.style.display = 'none';
                    firmaStepSignature.style.display = 'block';
                    
                    // Actualizar sidebar para mostrar documento y ensanchar (con delay para DOM update)
                    setTimeout(() => {
                        actualizarSidebarDinamico('page-firma');
                    }, 50);
                    
                    // Inicializar SignaturePad
                    setTimeout(() => {
                        initializeSignaturePadForm();
                    }, 200);
                    
                } else {
                    console.log('❌ Documentos no confirmados - ocultando paso firma');
                    
                    // Mostrar confirmación y ocultar firma
                    firmaStepConfirmacion.style.display = 'block';
                    firmaStepSignature.style.display = 'none';
                    
                    // Restaurar sidebar normal (con delay para DOM update)
                    setTimeout(() => {
                        actualizarSidebarDinamico('page-firma'); // Mostrar estado confirmación
                    }, 50);
                }
            });
        }

        // Event listener para volver a confirmación
        const volverConfirmacionBtn = document.getElementById('volver-confirmacion');
        if (volverConfirmacionBtn) {
            volverConfirmacionBtn.addEventListener('click', function() {
                console.log('🔄 Volviendo a confirmación de documentos');
                
                const documentosConfirmadosFirmaCheckbox = document.getElementById('documentos-confirmados-firma');
                if (documentosConfirmadosFirmaCheckbox) {
                    documentosConfirmadosFirmaCheckbox.checked = false;
                    // Disparar el evento change para activar la lógica
                    documentosConfirmadosFirmaCheckbox.dispatchEvent(new Event('change'));
                }
            });
        }

        // Event listeners para SignaturePad del formulario
        const clearSignatureFormBtn = document.getElementById('clear-signature-form');
        if (clearSignatureFormBtn) {
            clearSignatureFormBtn.addEventListener('click', function() {
                console.log('DEBUG: clear signature form clicked');
                if (window.signaturePadForm) {
                    window.signaturePadForm.clear();
                    const label = document.getElementById('signature-label-form');
                    if (label) label.style.display = 'block';
                    
                    // Ocultar mensaje de validación
                    const validationMsg = document.getElementById('signature-validation-message');
                    if (validationMsg) validationMsg.style.display = 'none';
                }
            });
        }

        const validateSignatureFormBtn = document.getElementById('validate-signature-form');
        if (validateSignatureFormBtn) {
            validateSignatureFormBtn.addEventListener('click', function() {
                console.log('DEBUG: validate signature form clicked');
                const validationMsg = document.getElementById('signature-validation-message');
                
                if (window.signaturePadForm && !window.signaturePadForm.isEmpty()) {
                    // Firma válida
                    if (validationMsg) {
                        validationMsg.textContent = '✅ Firma validada correctamente';
                        validationMsg.style.display = 'block';
                        validationMsg.style.backgroundColor = '#d1fae5';
                        validationMsg.style.color = '#065f46';
                        validationMsg.style.borderColor = '#10b981';
                    }
                    
                    // Marcar como firmado globalmente
                    window.firmaCompleta = true;
                    
                    console.log('✅ Firma validada en formulario');
                } else {
                    // Sin firma
                    if (validationMsg) {
                        validationMsg.textContent = '❌ Por favor, firme en el espacio indicado';
                        validationMsg.style.display = 'block';
                        validationMsg.style.backgroundColor = '#fee2e2';
                        validationMsg.style.color = '#991b1b';
                        validationMsg.style.borderColor = '#ef4444';
                    }
                }
            });
        }

        // Event listener para el botón de pago directo en la página
        const submitPaymentBtn = document.getElementById('submit-payment');
        if (submitPaymentBtn) {
            submitPaymentBtn.addEventListener('click', async (e) => {
                e.preventDefault();

                // Validar términos y condiciones
                if (!document.querySelector('input[name="terms_accept_pago"]').checked) {
                    showPaymentError('Debe aceptar los términos y condiciones de pago para continuar.');
                    return;
                }

                // Validar firma
                if (!validateSignature()) {
                    showPaymentError('Debe firmar en el campo de firma digital antes de continuar con el pago.');
                    // Scroll al canvas de firma solo en móvil
                    const canvas = document.getElementById('signature-pad-form');
                    if (canvas) {
                        const isMobile = window.innerWidth <= 768;
                        if (isMobile) {
                            canvas.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        }
                        canvas.style.borderColor = '#ef4444';
                        canvas.style.borderWidth = '3px';
                        setTimeout(() => {
                            canvas.style.borderColor = '#e5e7eb';
                            canvas.style.borderWidth = '1px';
                        }, 3000);
                    }
                    return;
                }

                document.getElementById('loading-overlay').style.display = 'flex';
                submitPaymentBtn.disabled = true;
                submitPaymentBtn.textContent = 'Procesando...';

                const paymentMessage = document.getElementById('payment-message');
                paymentMessage.classList.remove('success', 'error');
                paymentMessage.classList.add('hidden');
                paymentMessage.style.display = 'none';

                try {
                    if (!stripe || !elements) {
                        console.error("Stripe no ha sido inicializado correctamente");
                        throw new Error("Error en la configuración del sistema de pago");
                    }

                    // CRÍTICO: Recalcular monto de pago según selección ITP
                    let paymentAmount = finalAmount;
                    
                    console.log('🔍 VERIFICANDO CONDICIONES DE PAGO:');
                    console.log('  gestionamosITP:', gestionamosITP, '(tipo:', typeof gestionamosITP, ')');
                    console.log('  itpMetodoPago:', itpMetodoPago, '(tipo:', typeof itpMetodoPago, ')');
                    console.log('  currentTransferTax:', currentTransferTax);
                    console.log('  finalAmount original:', finalAmount);
                    console.log('🔍 EVALUACIÓN DE CONDICIÓN:');
                    console.log('  gestionamosITP === true?', gestionamosITP === true);
                    console.log('  itpMetodoPago === "transferencia"?', itpMetodoPago === 'transferencia');
                    console.log('  AMBAS verdaderas?', (gestionamosITP && itpMetodoPago === 'transferencia'));
                    
                    // Si gestionamos el ITP y eligieron transferencia, solo cobrar lo nuestro (174,99€ + servicios)
                    if (gestionamosITP && itpMetodoPago === 'transferencia') {
                        paymentAmount = finalAmount - currentTransferTax; // LO NUESTRO: 174.99€ base + servicios adicionales
                        console.log('💰 PAGO FRACCIONADO DETECTADO:');
                        console.log('   📊 Total trámite:', finalAmount, '€');
                        console.log('   💳 Stripe cobra (lo nuestro):', paymentAmount, '€');
                        console.log('   🏦 Cliente transfiere (ITP):', currentTransferTax, '€');
                        console.log('   🎯 Desglose:', paymentAmount.toFixed(2) + '€ (stripe) + ' + currentTransferTax + '€ (transferencia) = ' + finalAmount + '€');
                    } else if (gestionamosITP && itpMetodoPago === 'tarjeta') {
                        // Si gestionamos ITP y se paga con tarjeta, incluir TODO + comisión
                        const itpAmount = currentTransferTax || 0;
                        const comisionTarjeta = itpAmount * 0.015; // 1.5% comisión
                        paymentAmount = finalAmount + comisionTarjeta; // finalAmount ya incluye base + servicios + ITP
                        console.log('💳 PAGO COMPLETO CON ITP POR TARJETA:');
                        console.log('   🏛️ Base + servicios + ITP:', finalAmount, '€');
                        console.log('   💰 Comisión tarjeta (1.5%):', comisionTarjeta.toFixed(2), '€');
                        console.log('   🎯 TOTAL A COBRAR:', paymentAmount.toFixed(2), '€');
                    } else if (!gestionamosITP) {
                        // Si NO gestionamos ITP (cliente ya lo pagó o lo paga él), cobrar solo lo nuestro
                        paymentAmount = finalAmount; // Usar finalAmount que ya incluye 134.99€ base + servicios adicionales
                        console.log('💰 NO GESTIONAMOS ITP - PRECIO BASE:');
                        console.log('   🏛️ Base (sin ITP):', 134.99, '€');
                        console.log('   📋 Servicios adicionales incluidos en finalAmount');
                        console.log('   🎯 TOTAL A COBRAR:', paymentAmount.toFixed(2), '€');
                    } else {
                        console.log('💳 PAGO COMPLETO: Cobrar total:', paymentAmount, '€');
                    }

                    // Crear nuevo Payment Intent con el monto correcto
                    const amountCents = Math.round(paymentAmount * 100);
                    console.log('🔄 Creando Payment Intent con monto correcto:', amountCents, 'centavos');
                    
                    const response = await fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `action=barco_create_payment_intent&amount=${amountCents}`
                    });

                    const result = await response.json();
                    console.log('📄 Respuesta Payment Intent recreado:', result);
                    
                    // Verificar que tenemos clientSecret (puede venir como client_secret o clientSecret)
                    const clientSecret = result.client_secret || result.clientSecret;
                    if (!clientSecret) {
                        console.error('❌ Error en respuesta - falta clientSecret:', result);
                        throw new Error('Error al crear el Payment Intent: ' + (result.error || 'ClientSecret no encontrado'));
                    }
                    
                    console.log('✅ Payment Intent creado exitosamente con monto:', paymentAmount, '€');
                    
                    // Actualizar el clientSecret con el nuevo Payment Intent
                    window.stripeClientSecret = clientSecret;
                    console.log('✅ Payment Intent recreado con monto correcto:', paymentAmount, '€');

                    // Verificar que el clientSecret existe
                    console.log('🔑 Confirmando pago con clientSecret actualizado:', window.stripeClientSecret.substring(0, 30) + '...');

                    // Usar confirmPayment para Payment Element (igual que hoja-asiento)
                    const { error } = await stripe.confirmPayment({
                        elements,
                        confirmParams: {
                            return_url: window.location.href
                        },
                        redirect: 'if_required'
                    });

                    // Si hay error, mostrarlo
                    if (error) {
                        throw error;
                    }

                    // Obtener el Payment Intent después de la confirmación
                    const { paymentIntent } = await stripe.retrievePaymentIntent(window.stripeClientSecret);
                    console.log('✅ Payment Intent confirmado:', paymentIntent.id);

                    paymentMessage.textContent = '¡Pago realizado con éxito!';
                    paymentMessage.style.display = 'block';
                    paymentMessage.style.backgroundColor = '#d1fae5';
                    paymentMessage.style.color = '#065f46';
                    paymentMessage.style.border = '1px solid #6ee7b7';

                    paymentCompleted = true;

                    // PASO 0: Generar ID de trámite PRIMERO
                    console.log('🔢 Generando ID de trámite...');
                    tramiteId = ''; // Usar la variable ya declarada arriba
                    try {
                        const idResponse = await fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: 'action=tpb_generate_tramite_id'
                        });
                        const idResult = await idResponse.json();
                        if (idResult.success) {
                            tramiteId = idResult.data.tramite_id;
                            console.log('✅ Trámite ID generado:', tramiteId);
                        } else {
                            console.error('❌ Error generando tramite ID:', idResult);
                            tramiteId = 'TMA-TRANS-' + Date.now(); // Fallback
                        }
                    } catch (error) {
                        console.error('❌ Error en generación de ID:', error);
                        tramiteId = 'TMA-TRANS-' + Date.now(); // Fallback
                    }

                    // 🔍 DEBUG ITP CRÍTICO - Variables antes de SEGUNDA assignación purchaseDetails
                    console.log('🚨 DEBUG ITP ANTES DE SEGUNDA ASSIGNACIÓN PURCHASEDETAILS:');
                    console.log('  gestionamosITP:', gestionamosITP);
                    console.log('  itpGestionSeleccionada:', itpGestionSeleccionada);
                    console.log('  itpMetodoPago:', itpMetodoPago);
                    console.log('  currentTransferTax:', currentTransferTax);
                    console.log('  itpComisionTarjeta:', itpComisionTarjeta);
                    console.log('  itpTotalAmount:', itpTotalAmount);

                    // 🚨 AJUSTE CRÍTICO: Si es pago fraccionado, ajustar finalAmount para email/webhook
                    let finalAmountParaEmail = finalAmount;
                    let totalAmountParaEmail = finalAmount.toFixed(2);
                    
                    if (gestionamosITP && itpMetodoPago === 'transferencia') {
                        finalAmountParaEmail = 174.99; // SOLO LO NUESTRO para email/webhook
                        totalAmountParaEmail = '174.99';
                        console.log('💰 AJUSTE PAGO FRACCIONADO PARA EMAIL:');
                        console.log('   📊 Total original:', finalAmount, '€');
                        console.log('   💳 Email mostrará:', finalAmountParaEmail, '€');
                        console.log('   🏦 ITP por transferencia:', currentTransferTax, '€');
                    }

                    // Preparar datos completos del trámite
                    purchaseDetails = {
                        // Identificación del trámite
                        tramite_id: tramiteId,
                        paymentIntentId: paymentIntent.id,
                        paymentStatus: paymentIntent.status,
                        timestamp: new Date().toISOString(),

                        // Cliente (Comprador)
                        customer_name: document.getElementById('customer_name')?.value?.trim() || '',
                        customer_email: document.getElementById('customer_email')?.value?.trim() || '',
                        customer_phone: document.getElementById('customer_phone')?.value?.trim() || '',
                        customer_dni: document.getElementById('customer_dni')?.value?.trim() || '',
                        customer_address: document.getElementById('customer_address')?.value?.trim() || '',
                        customer_postal_code: document.getElementById('customer_postal_code')?.value?.trim() || '',
                        customer_city: document.getElementById('customer_city')?.value?.trim() || '',
                        customer_province: document.getElementById('customer_province')?.value?.trim() || '',

                        // Vendedor
                        seller_name: document.getElementById('seller_name')?.value?.trim() || '',
                        seller_dni: document.getElementById('seller_dni')?.value?.trim() || '',
                        seller_phone: document.getElementById('seller_phone')?.value?.trim() || '',
                        seller_email: document.getElementById('seller_email')?.value?.trim() || '',

                        // Vehículo
                        vehicle_type: document.getElementById('vehicle_type')?.value || '',
                        manufacturer: document.getElementById('manufacturer')?.value || '',
                        model: document.getElementById('model')?.value || '',
                        matriculation_date: document.getElementById('matriculation_date')?.value || '',
                        registration: document.getElementById('registration')?.value?.trim() || '',
                        hull_number: document.getElementById('hull_number')?.value?.trim() || '',
                        engine_brand: document.getElementById('engine_brand')?.value?.trim() || '',
                        engine_serial: document.getElementById('engine_serial')?.value?.trim() || '',
                        engine_power: document.getElementById('engine_power')?.value?.trim() || '',
                        purchase_price: parseFloat(document.getElementById('purchase_price')?.value) || 0,
                        region: document.getElementById('region')?.value || '',

                        // Precios y honorarios
                        basePrice: gestionamosITP ? BASE_TRANSFER_PRICE_CON_ITP : BASE_TRANSFER_PRICE_SIN_ITP,
                        finalAmount: finalAmountParaEmail, // AJUSTADO para pago fraccionado
                        totalAmount: totalAmountParaEmail, // AJUSTADO para pago fraccionado

                        // ITP - Detalles completos
                        itpPagado: itpPagado,
                        itpGestionSeleccionada: itpGestionSeleccionada,
                        itpMetodoPago: itpMetodoPago,
                        itpAmount: currentTransferTax,
                        itpComision: (itpMetodoPago === 'tarjeta' ? itpComisionTarjeta : 0),
                        itpTotalAmount: itpTotalAmount,
                        
                        // PAGO FRACCIONADO - Info para email
                        pagoFraccionado: (gestionamosITP && itpMetodoPago === 'transferencia'),
                        totalOriginalCompleto: finalAmount, // Total real del trámite
                        importeTransferencia: (gestionamosITP && itpMetodoPago === 'transferencia') ? currentTransferTax : 0,
                        transferTax: currentTransferTax.toFixed(2),

                        // Extras
                        cambioLista: cambioListaSeleccionado,
                        cambioListaPrecio: cambioListaSeleccionado ? PRECIO_CAMBIO_LISTA : 0,

                        // Cupón
                        couponCode: couponValue || '',
                        couponDiscount: couponDiscountPercent || 0,
                        couponUsed: couponValue,

                        // ITP - Información completa
                        itpPagado: itpPagado,
                        itpGestionSeleccionada: itpGestionSeleccionada,
                        itpMetodoPago: itpMetodoPago,
                        itpAmount: currentTransferTax,
                        itpComision: (itpMetodoPago === 'tarjeta' ? itpComisionTarjeta : 0),
                        itpTotalAmount: itpTotalAmount,

                        // Opciones adicionales (legacy)
                        options: Array.from(extraOptions).filter(opt => opt.checked).map(opt => opt.value),
                        nuevoNombre: document.getElementById('nuevo_nombre')?.value?.trim() || '',
                        nuevoPuerto: document.getElementById('nuevo_puerto')?.value?.trim() || '',

                        // Firma
                        signature: getSignatureData() || ''
                    };

                    console.log('📋 purchaseDetails preparados:', purchaseDetails);

                    // 1. Subir documentos primero
                    await uploadDocumentsAndPDF();

                    // 2. Generar tracking URL localmente (sin postMessage)
                    console.log('🔗 Generando tracking URL localmente...');
                    // tramiteId ya existe en el scope, usarla directamente
                    purchaseDetails.trackingUrl = `https://46-202-128-35.sslip.io/seguimiento/${purchaseDetails.tramite_id}`;
                    purchaseDetails.trackingToken = purchaseDetails.tramite_id;
                    purchaseDetails.procedureId = purchaseDetails.tramite_id;
                    console.log('✅ Tracking URL generado:', purchaseDetails.trackingUrl);

                    // 3. Enviar emails (cliente + admin) con tracking URL
                    persistentLog('📧 === PUNTO CRÍTICO: ANTES DE SENDEMAILS() ===', 'critical');
                    console.log('🚨 === SEGUNDO PUNTO CRÍTICO: ANTES DE SENDEMAILS() ===');
                    console.log('📧 Iniciando envío de emails con tracking URL...');
                    console.log('🔍 Estado purchaseDetails con tracking:', JSON.stringify(purchaseDetails, null, 2));
                    const emailResult = await sendEmails();
                    persistentLog('📤 === PUNTO CRÍTICO: DESPUÉS DE SENDEMAILS() ===', 'critical');
                    persistentLog('📤 Resultado sendEmails: ' + JSON.stringify(emailResult), 'critical');
                    console.log('🚨 === SEGUNDO PUNTO CRÍTICO: DESPUÉS DE SENDEMAILS() ===');
                    console.log('📤 Resultado sendEmails con tracking:', emailResult);
                    
                    if (emailResult.success) {
                        console.log('✅ Emails con tracking completados, continuando con submission final');
                    } else {
                        console.error('⚠️ Error en emails con tracking, pero continuando:', emailResult.error);
                    }

                    // 4. Procesar submission final
                    handleFinalSubmission();

                } catch (err) {
                    console.error("Error en el pago:", err);

                    // Mensaje más claro según el tipo de error
                    let errorMsg = err.message || 'Error desconocido';
                    if (err.code === 'resource_missing') {
                        errorMsg = 'El enlace de pago ha expirado. Por favor, recarga la página y vuelve a intentarlo.';
                        console.error('❌ Payment Intent no encontrado');
                    } else if (err.type === 'card_error' || err.type === 'validation_error') {
                        errorMsg = err.message;
                    }

                    paymentMessage.textContent = errorMsg;
                    paymentMessage.style.display = 'block';
                    paymentMessage.style.backgroundColor = '#fee2e2';
                    paymentMessage.style.color = '#991b1b';
                    paymentMessage.style.border = '1px solid #fca5a5';
                    document.getElementById('loading-overlay').style.display = 'none';
                    submitPaymentBtn.disabled = false;
                    submitPaymentBtn.innerHTML = '<i class="fa-solid fa-lock"></i> Pagar Ahora';
                }
            });
        }

        // Inicialización
        logDebug('INIT', '⚙️ Iniciando configuración del formulario');
        currentPage = 0; // Empezamos en la página de vehículo (primera página del formulario)
        document.querySelector('.button-container').style.display = 'flex'; // Mostrar botones navegación desde el inicio
        logDebug('INIT', 'Página actual:', currentPage);

        populateManufacturers();
        updateForm();
        updateVehicleSelection();
        updateAdditionalInputs();
        // NO inicializar signaturePad aquí - se hará cuando la página esté visible
        // initializeSignaturePad();
        updateNoEncuentroBehavior();
        logDebug('INIT', '✅ Funciones principales ejecutadas');
        
        // Inicializar dropdowns mejorados para opciones adicionales y cupones
        initAdditionalOptionsDropdown();
        initCouponDropdown();
        
        // Asegurar que los precios se actualicen correctamente después de la inicialización
        setTimeout(() => {
            updateTotal();
            updatePaymentSummary();
            logDebug('INIT', '💰 Precios actualizados');
        }, 300);

        // Configuración para inicializar acordeón cuando sea necesario
        // Ya estamos dentro de DOMContentLoaded, no es necesario otro listener
        // REMOVIDO en v1.10: page-documentos ya no usa acordeones
        // if (document.getElementById('page-documentos') && !document.getElementById('page-documentos').classList.contains('hidden')) {
        //     initAccordionSections();
        // }

        logDebug('INIT', '🎉 ¡Formulario Tramitfy completamente inicializado!');
        
        // Función para inicializar el dropdown de opciones adicionales
        function initAdditionalOptionsDropdown() {
            // Opciones para ambos estilos - ya sea usando .additional-options o .accordion-content-section
            // Primero, intentar con el estilo de la columna de servicios
            let additionalOptionsContainer = document.querySelector('.additional-options');
            let usingAccordion = false;
            
            // Si no existe, buscar el acordeón de servicios en page-precio
            if (!additionalOptionsContainer) {
                additionalOptionsContainer = document.querySelector('.price-summary-accordion#services-accordion .accordion-content-section');
                if (additionalOptionsContainer) {
                    usingAccordion = true;
                } else {
                    console.warn('No se encontró contenedor de opciones adicionales');
                    return;
                }
            }
            
            // Si está usando el estilo de opciones adicionales original
            if (!usingAccordion) {
                // Crear título con toggle si no existe
                if (!additionalOptionsContainer.querySelector('.additional-options-title')) {
                    const title = document.createElement('div');
                    title.className = 'additional-options-title';
                    title.innerHTML = 'Opciones Adicionales <i class="fas fa-chevron-down"></i>';
                    
                    // Wrap existing content
                    const content = document.createElement('div');
                    content.className = 'additional-options-content';
                    
                    // Move all children except the title into content
                    while (additionalOptionsContainer.children.length > 0) {
                        content.appendChild(additionalOptionsContainer.children[0]);
                    }
                    
                    additionalOptionsContainer.appendChild(title);
                    additionalOptionsContainer.appendChild(content);
                    
                    // Add click handler to toggle
                    title.addEventListener('click', function() {
                        this.classList.toggle('expanded');
                        content.classList.toggle('expanded');
                        
                        if (content.classList.contains('expanded')) {
                            content.style.maxHeight = content.scrollHeight + 'px';
                        } else {
                            content.style.maxHeight = '0';
                        }
                    });
                    
                    // Expand initially
                    setTimeout(() => {
                        title.click();
                    }, 100);
                }
            } else {
                // Si está usando el estilo de acordeón, asegurar que funcione correctamente
                const accordionHeader = document.querySelector('.price-summary-accordion#services-accordion .accordion-toggle-header');
                
                if (accordionHeader) {
                    // Eliminar cualquier event listener existente
                    const newHeader = accordionHeader.cloneNode(true);
                    accordionHeader.parentNode.replaceChild(newHeader, accordionHeader);
                    
                    // Añadir el event listener para expandir/contraer
                    newHeader.addEventListener('click', function() {
                        this.classList.toggle('active');
                        const content = this.nextElementSibling;
                        
                        if (content) {
                            content.classList.toggle('active');
                            
                            if (content.classList.contains('active')) {
                                content.style.maxHeight = content.scrollHeight + 'px';
                            } else {
                                content.style.maxHeight = '0';
                            }
                        }
                    });
                    
                    // Expandir inicialmente si hay alguna opción marcada
                    const hasSelectedOptions = Array.from(document.querySelectorAll('.extra-option')).some(opt => opt.checked);
                    if (hasSelectedOptions) {
                        setTimeout(() => {
                            newHeader.click();
                        }, 100);
                    }
                }
            }
            
            // Aplicar los efectos de selección a ambos estilos
            // Buscar checkboxes en ambos tipos de contenedores
            const checkboxes = document.querySelectorAll('.extra-option, input[type="checkbox"].extra-option');
            
            checkboxes.forEach(checkbox => {
                // Buscar el label contenedor (funciona para ambos estilos)
                const label = checkbox.closest('label');
                
                if (checkbox.checked && label) {
                    label.classList.add('selected');
                }
                
                checkbox.addEventListener('change', function() {
                    if (this.checked && label) {
                        label.classList.add('selected');
                    } else if (label) {
                        label.classList.remove('selected');
                    }
                    
                    // Gestionar los inputs adicionales
                    const additionalInputId = this.value === 'Cambio de nombre' ? 'nombre-input' : 
                                             this.value === 'Cambio de puerto base' ? 'puerto-input' : null;
                    
                    if (additionalInputId) {
                        const additionalInput = document.getElementById(additionalInputId);
                        if (additionalInput) {
                            additionalInput.style.display = this.checked ? 'block' : 'none';
                        }
                    }
                    
                    // Actualizar el total
                    updateTotal();
                });
            });
        }
        
        // Función mejorada para inicializar el dropdown del cupón
        function initCouponDropdown() {
            const couponAccordion = document.getElementById('coupon-accordion');
            if (!couponAccordion) return;
            
            const toggleHeader = couponAccordion.querySelector('.accordion-toggle-header');
            const contentSection = couponAccordion.querySelector('.accordion-content-section');
            
            if (toggleHeader && contentSection) {
                // Ensure proper event handler
                const newToggleHeader = toggleHeader.cloneNode(true);
                toggleHeader.parentNode.replaceChild(newToggleHeader, toggleHeader);
                
                // Add click event
                newToggleHeader.addEventListener('click', function() {
                    this.classList.toggle('active');
                    contentSection.classList.toggle('active');
                    
                    if (contentSection.classList.contains('active')) {
                        contentSection.style.maxHeight = contentSection.scrollHeight + 'px';
                    } else {
                        contentSection.style.maxHeight = '0';
                    }
                });
                
                // Ensure the toggle works correctly
                if (typeof couponValue !== 'undefined' && typeof couponDiscountPercent !== 'undefined' && couponValue && couponDiscountPercent > 0) {
                    // Si hay un cupón válido aplicado, mostrar la sección
                    setTimeout(() => {
                        if (!newToggleHeader.classList.contains('active')) {
                            newToggleHeader.click();
                        }
                    }, 200);
                }
            }
            
            // Mejorar la funcionalidad del botón de aplicar cupón
            const applyButton = document.getElementById('apply-coupon');
            const couponInput = document.getElementById('coupon_code');
            
            if (applyButton && couponInput) {
                // Eliminar event listeners existentes
                const newButton = applyButton.cloneNode(true);
                applyButton.parentNode.replaceChild(newButton, applyButton);
                
                // Añadir event listener
                newButton.addEventListener('click', function() {
                    if (couponInput.value.trim()) {
                        validateCouponCode();
                    }
                });
                
                // Añadir funcionalidad para aplicar el cupón al presionar Enter
                couponInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        if (this.value.trim()) {
                            validateCouponCode();
                        }
                    }
                });
            }
            
            // Añadir la funcionalidad de cargar el mensaje de descuento cuando hay un cupón aplicado
            const couponMessage = document.getElementById('coupon-message');
            if (couponMessage && couponValue && couponDiscountPercent > 0) {
                couponMessage.textContent = `Cupón aplicado: ${couponDiscountPercent}% de descuento`;
                couponMessage.classList.remove('error-message', 'hidden');
                couponMessage.classList.add('success');
                
                if (couponInput) {
                    couponInput.value = couponValue;
                    couponInput.classList.add('coupon-valid');
                }
            }
        }
        
        document.querySelectorAll('.nav-item').forEach(link => {
            link.addEventListener('click', function() {
                const pageId = this.getAttribute('data-page-id');
                if (pageId === 'page-documentos') {
                    console.log("Navegando a la página documentos - v1.10 (sin acordeones)");
                    // REMOVIDO en v1.10: page-documentos ya no usa acordeones
                    // setTimeout(initAccordionSections, 200);
                }
            });
        });
        
        // Función para actualizar la barra de progreso del formulario basado en secciones completadas
        function updateDocumentProgress() {
            const accordionSections = document.querySelectorAll('.accordion-section');
            let completedSections = 0;
            
            // Contar secciones completadas
            accordionSections.forEach(section => {
                const status = section.querySelector('.accordion-status');
                if (status.textContent === 'Completado' || status.classList.contains('completed')) {
                    completedSections++;
                }
            });
            
            // Actualizar la barra de progreso solo si estamos en la página de documentos
            if (formPages[currentPage].id === 'page-documentos') {
                const progressPercentage = (completedSections / accordionSections.length) * 100;
                const progressIndicator = document.querySelector('.nav-progress-indicator');
                if (progressIndicator) {
                    // Calcular el progreso relativo a la página actual (documentos = 2/4 páginas)
                    progressIndicator.style.width = `${Math.max(50, 50 + (progressPercentage/2))}%`;
                }
                
                // Actualizar estado de la navegación
                const navItems = document.querySelectorAll('.nav-item');
                navItems.forEach((item) => {
                    const pageId = item.getAttribute('data-page-id');
                    if (pageId === 'page-documentos') {
                        // Si todas las secciones están completadas, marcar este paso como completado
                        if (completedSections === accordionSections.length) {
                            item.classList.add('completed');
                        }
                    }
                });
            }
        }
        
        // Corregir el comportamiento del checkbox de términos en la página de pago
        // Ya estamos dentro de DOMContentLoaded
        const paymentCheckbox = document.getElementById('terms_accept_pago');
        if (paymentCheckbox) {
            paymentCheckbox.addEventListener('change', function() {
                const checkmark = this.closest('.custom-checkbox-container').querySelector('.checkmark');
                if (checkmark) {
                    if (this.checked) {
                        checkmark.style.display = 'block';
                    } else {
                        checkmark.style.display = 'none';
                    }
                }
            });
        }

        // También añadir para el checkbox del modal
        const modalCheckbox = document.getElementById('modal-terms-accept');
        if (modalCheckbox) {
            modalCheckbox.addEventListener('change', function() {
                const container = this.closest('label');
                if (container) {
                    container.classList.toggle('checked', this.checked);
                }
            });
        }

        // Inicializar funcionalidad de botones de upload
        document.querySelectorAll('.upload-button').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();

                // Buscar el input file correspondiente dentro del mismo upload-wrapper
                const wrapper = this.closest('.upload-wrapper');
                if (wrapper) {
                    const fileInput = wrapper.querySelector('input[type="file"]');
                    if (fileInput) {
                        fileInput.click();
                    }
                }
            });
        });

        // Actualizar contador de archivos y mostrar preview
        document.querySelectorAll('input[type="file"]').forEach(input => {
            input.addEventListener('change', function() {
                const fileCount = this.files.length;
                const wrapper = this.closest('.upload-wrapper');
                const uploadItem = this.closest('.upload-item');
                const viewExampleLink = uploadItem ? uploadItem.querySelector('.view-example') : null;

                // Actualizar contador
                if (wrapper) {
                    const countElement = wrapper.querySelector('.file-count');
                    if (countElement) {
                        if (fileCount === 0) {
                            countElement.textContent = 'Ningún archivo seleccionado';
                        } else if (fileCount === 1) {
                            countElement.textContent = '1 archivo seleccionado';
                        } else {
                            countElement.textContent = fileCount + ' archivos seleccionados';
                        }
                    }
                }

                // Ocultar/mostrar "Ver ejemplo" según si hay archivos (aplica para móvil y desktop)
                if (viewExampleLink) {
                    if (fileCount > 0) {
                        viewExampleLink.style.setProperty('display', 'none', 'important');
                        viewExampleLink.style.visibility = 'hidden';
                        viewExampleLink.style.opacity = '0';
                        viewExampleLink.style.height = '0';
                        viewExampleLink.style.overflow = 'hidden';
                    } else {
                        const displayValue = window.innerWidth <= 768 ? 'block' : 'inline-block';
                        viewExampleLink.style.setProperty('display', displayValue, 'important');
                        viewExampleLink.style.visibility = 'visible';
                        viewExampleLink.style.opacity = '1';
                        viewExampleLink.style.height = 'auto';
                        viewExampleLink.style.overflow = 'visible';
                    }
                }

                // Mostrar solo nombre del último archivo en móvil
                if (uploadItem && window.innerWidth <= 768) {
                    const previewContainer = uploadItem.querySelector('.files-preview');

                    if (previewContainer && fileCount > 0) {
                        previewContainer.innerHTML = '';

                        // Obtener el último archivo
                        const lastFile = this.files[fileCount - 1];

                        if (lastFile) {
                            // Mostrar solo el nombre del archivo (sin preview de imagen)
                            const previewDiv = document.createElement('div');
                            previewDiv.style.cssText = 'margin-top: 10px; padding: 12px; background: #f3f4f6; border: 2px solid #e5e7eb; border-radius: 8px; text-align: center;';

                            const fileName = document.createElement('div');
                            const icon = lastFile.type.startsWith('image/') ? '📷' : '📄';
                            fileName.textContent = icon + ' ' + lastFile.name;
                            fileName.style.cssText = 'font-size: 13px; color: #374151; margin-bottom: 8px; word-break: break-word;';

                            const removeBtn = document.createElement('button');
                            removeBtn.type = 'button';
                            removeBtn.innerHTML = '🗑️ Eliminar último';
                            removeBtn.style.cssText = 'width: 100%; padding: 8px; background: #ef4444; color: white; border: none; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer;';

                            removeBtn.addEventListener('click', function() {
                                const dt = new DataTransfer();
                                const files = Array.from(input.files);
                                for (let i = 0; i < files.length - 1; i++) {
                                    dt.items.add(files[i]);
                                }
                                input.files = dt.files;
                                input.dispatchEvent(new Event('change'));
                            });

                            previewDiv.appendChild(fileName);
                            previewDiv.appendChild(removeBtn);
                            previewContainer.appendChild(previewDiv);
                        }
                    } else if (previewContainer && fileCount === 0) {
                        previewContainer.innerHTML = '';
                    }
                }
                
                // 🔄 ACTUALIZAR SIDEBAR EN TIEMPO REAL
                // Solo actualizar si estamos en la página de documentos
                const currentPageElement = document.querySelector('.form-page:not(.hidden)');
                if (currentPageElement && currentPageElement.id === 'page-documentos') {
                    // Pequeño delay para asegurar que los archivos estén procesados
                    setTimeout(() => {
                        actualizarSidebarDinamico('page-documentos');
                    }, 100);
                }
            });
        });

        // ====================================
        // MODAL DE FIRMA PARA MÓVIL
        // ====================================
        if (window.innerWidth <= 768) {
            const openModalBtn = document.getElementById('open-signature-modal-mobile');
            const modal = document.getElementById('signature-modal-mobile');
            const modalCanvas = document.getElementById('signature-modal-canvas');
            const modalLabel = document.getElementById('signature-modal-label');
            const clearModalBtn = document.getElementById('clear-signature-modal');
            const confirmModalBtn = document.getElementById('confirm-signature-modal');
            let signaturePadModal;

            // Mostrar botón en móvil
            if (openModalBtn) {
                openModalBtn.style.display = 'flex';
            }

            // Abrir modal
            if (openModalBtn) {
                openModalBtn.addEventListener('click', function() {
                    modal.classList.add('active');
                    document.body.style.overflow = 'hidden';

                    // Inicializar SignaturePad para el modal
                    setTimeout(() => {
                        const wrapper = document.getElementById('signature-modal-canvas-wrapper');
                        const rect = wrapper.getBoundingClientRect();
                        const ratio = Math.max(window.devicePixelRatio || 1, 1);

                        modalCanvas.width = rect.width * ratio;
                        modalCanvas.height = rect.height * ratio;

                        const ctx = modalCanvas.getContext('2d');
                        ctx.scale(ratio, ratio);

                        signaturePadModal = new SignaturePad(modalCanvas, {
                            backgroundColor: 'rgb(255, 255, 255)',
                            penColor: 'rgb(0, 0, 0)',
                            minWidth: 1.5,
                            maxWidth: 3.5
                        });

                        // Ocultar label cuando empiece a firmar
                        signaturePadModal.addEventListener('beginStroke', function() {
                            if (modalLabel) modalLabel.style.display = 'none';
                        });
                    }, 100);
                });
            }

            // Limpiar firma en modal
            if (clearModalBtn) {
                clearModalBtn.addEventListener('click', function() {
                    if (signaturePadModal) {
                        signaturePadModal.clear();
                        if (modalLabel) modalLabel.style.display = 'block';
                    }
                });
            }

            // Confirmar firma y cerrar modal
            if (confirmModalBtn) {
                confirmModalBtn.addEventListener('click', function() {
                    if (signaturePadModal && !signaturePadModal.isEmpty()) {
                        // Transferir firma al canvas principal
                        const mainCanvas = document.getElementById('signature-pad');
                        const mainCtx = mainCanvas.getContext('2d');

                        // Limpiar canvas principal
                        mainCtx.clearRect(0, 0, mainCanvas.width, mainCanvas.height);
                        mainCtx.fillStyle = 'white';
                        mainCtx.fillRect(0, 0, mainCanvas.width, mainCanvas.height);

                        // Copiar firma del modal al canvas principal
                        const dataURL = signaturePadModal.toDataURL();
                        const img = new Image();
                        img.onload = function() {
                            mainCtx.drawImage(img, 0, 0, mainCanvas.width, mainCanvas.height);

                            // Actualizar signaturePad principal
                            if (window.signaturePad) {
                                window.signaturePad.fromDataURL(dataURL);
                            }

                            // Ocultar label del canvas principal
                            const mainLabel = document.getElementById('signature-label');
                            if (mainLabel) mainLabel.classList.add('hidden');
                            if (mainCanvas) mainCanvas.classList.add('signed');
                        };
                        img.src = dataURL;

                        // Cerrar modal
                        modal.classList.remove('active');
                        document.body.style.overflow = '';

                        // Limpiar modal para próximo uso
                        if (signaturePadModal) {
                            signaturePadModal.clear();
                        }
                        if (modalLabel) modalLabel.style.display = 'block';
                    } else {
                        alert('Por favor, firme antes de guardar');
                    }
                });
            }

            // Cerrar modal al hacer click fuera
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    modal.classList.remove('active');
                    document.body.style.overflow = '';
                    if (signaturePadModal) signaturePadModal.clear();
                    if (modalLabel) modalLabel.style.display = 'block';
                }
            });
        }

    // Funcion global para auto-rellenado de administradores (accesible globalmente via window)
    window.tramitfyAdminAutofill = function() {
        console.log('[ADMIN] Iniciando auto-rellenado');

                var vehicleTypeSelect = document.querySelector('[name="vehicle_type"]');
                console.log('[ADMIN] vehicleTypeSelect:', vehicleTypeSelect);
                if (vehicleTypeSelect) {
                    vehicleTypeSelect.value = 'barco';
                    vehicleTypeSelect.dispatchEvent(new Event('change', { bubbles: true }));
                    console.log('[ADMIN] Tipo vehiculo establecido: barco');
                }

                setTimeout(function() {
                    var manufacturerSelect = document.querySelector('[name="manufacturer"]');
                    console.log('[ADMIN] manufacturerSelect:', manufacturerSelect);
                    if (manufacturerSelect) {
                        manufacturerSelect.value = 'YAMAHA';
                        manufacturerSelect.dispatchEvent(new Event('change', { bubbles: true }));
                        console.log('[ADMIN] Fabricante establecido: YAMAHA');
                    }

                    setTimeout(function() {
                        var modelSelect = document.querySelector('[name="model"]');
                        console.log('[ADMIN] modelSelect:', modelSelect);
                        if (modelSelect) {
                            modelSelect.value = 'VX DELUXE';
                            modelSelect.dispatchEvent(new Event('change', { bubbles: true }));
                            console.log('[ADMIN] Modelo establecido: VX DELUXE');
                        }

                        var priceInput = document.querySelector('[name="purchase_price"]');
                        if (priceInput) {
                            priceInput.value = '15000';
                            console.log('[ADMIN] Precio establecido: 15000');
                        }

                        var regionInput = document.querySelector('[name="region"]');
                        if (regionInput) {
                            regionInput.value = 'Madrid';
                            console.log('[ADMIN] Region establecida: Madrid');
                        }

                        var dateInput = document.querySelector('[name="matriculation_date"]');
                        if (dateInput) {
                            dateInput.value = '2020-01-15';
                            console.log('[ADMIN] Fecha establecida: 2020-01-15');
                        }

                        setTimeout(function() {
                            document.getElementById('nextButton').click();

                            setTimeout(function() {
                                document.querySelector('[name="customer_name"]').value = 'Admin Test';
                                document.querySelector('[name="customer_dni"]').value = '12345678Z';
                                document.querySelector('[name="customer_email"]').value = 'joanpinyol@hotmail.es';
                                document.querySelector('[name="customer_phone"]').value = '666777888';

                                document.querySelector('[name="customer_name"]').dispatchEvent(new Event('input', { bubbles: true }));
                                document.querySelector('[name="customer_email"]').dispatchEvent(new Event('input', { bubbles: true }));

                                setTimeout(function() {
                                    document.getElementById('nextButton').click();

                                    setTimeout(function() {
                                        document.getElementById('nextButton').click();

                                        setTimeout(function() {
                                            document.getElementById('nextButton').click();

                                            setTimeout(function() {
                                                console.log('Paso de firma - Firme manualmente antes de continuar');
                                            }, 500);
                                        }, 500);
                                    }, 500);
                                }, 500);
                            }, 500);
                        }, 1000);
                    }, 1500);
                }, 500);
    };

    console.log('[ADMIN] tramitfyAdminAutofill function registered on window object');


    // ============================================
    // NUEVO FLUJO DE PÁGINA DE PRECIO
    // ============================================

    logDebug('PRECIO-INIT', '🎯 Inicializando flujo de página de precio');

    // Variables del flujo de precio ya definidas globalmente (líneas 5412-5413)
    // let itpPagado = null;
    // let precioStep = 1;

    // Elementos del nuevo flujo
    const precioStep1 = document.getElementById('precio-step-1');
    const precioStep2 = document.getElementById('precio-step-2');
    const verCalculoBtn = document.getElementById('ver-calculo-itp');
    const calculoDetail = document.getElementById('calculo-itp-detail');
    const itpSiBtn = document.getElementById('itp-si');
    const itpNoBtn = document.getElementById('itp-no');
    const toggleCuponBtn = document.getElementById('toggle-cupon');
    const cuponContent = document.getElementById('cupon-content');
    const cuponIcon = document.getElementById('cupon-icon');

    logDebug('PRECIO-INIT', 'Elementos encontrados:', {
        precioStep1: !!precioStep1,
        precioStep2: !!precioStep2,
        verCalculoBtn: !!verCalculoBtn,
        calculoDetail: !!calculoDetail,
        itpSiBtn: !!itpSiBtn,
        itpNoBtn: !!itpNoBtn,
        toggleCuponBtn: !!toggleCuponBtn,
        cuponContent: !!cuponContent
    });
    console.log('DEBUG: Estado completo de elementos:', {
        precioStep1, precioStep2, verCalculoBtn, calculoDetail, 
        itpSiBtn, itpNoBtn, toggleCuponBtn, cuponContent
    });

    // Botón ver cálculo ITP
    if (verCalculoBtn) {
        logDebug('PRECIO-INIT', '✅ Event listener añadido a botón ver cálculo');
        verCalculoBtn.addEventListener('click', function() {
            if (calculoDetail.style.display === 'none') {
                calculoDetail.style.display = 'block';
                verCalculoBtn.innerHTML = '<i class="fa-solid fa-eye-slash"></i> Ocultar cálculo';
                logDebug('PRECIO-FLOW', '👁️ Cálculo ITP mostrado');
            } else {
                calculoDetail.style.display = 'none';
                verCalculoBtn.innerHTML = '<i class="fa-solid fa-calculator"></i> Ver cómo se calcula el ITP';
                logDebug('PRECIO-FLOW', '🙈 Cálculo ITP ocultado');
            }
        });
    } else {
        logError('PRECIO-INIT', '❌ No se encontró botón ver cálculo');
    }

    // Actualizar valores del cálculo ITP en step 1
    function actualizarCalculoITPStep1() {
        logDebug('ITP-STEP1', '🔄 Actualizando cálculo ITP en paso 1');

        const purchasePrice = parseFloat(document.getElementById('purchase_price')?.value) || 0;
        const region = document.getElementById('region')?.value || '';

        logDebug('ITP-STEP1', 'Precio compra:', purchasePrice);
        logDebug('ITP-STEP1', 'Región:', region);

        try {
            // Calcular valor fiscal
            const { fiscalValue, depreciationPercentage, yearsDifference } = calculateFiscalValue();

            // Base imponible es el mayor entre precio compra y valor fiscal
            const baseImponible = Math.max(purchasePrice, fiscalValue);

            // Obtener tipo impositivo
            const taxRate = (itpRates[region] || 0.04);
            const taxRatePercent = taxRate * 100;

            // Calcular ITP
            const itp = baseImponible * taxRate;

            logDebug('ITP-STEP1', 'Resultado:', {
                fiscalValue,
                baseImponible,
                taxRate,
                itp
            });

            // ACTUALIZAR VARIABLES GLOBALES
            currentTransferTax = itp;
            itpBaseAmount = itp;
            logDebug('ITP-STEP1', '✅ Variables globales actualizadas:', { currentTransferTax, itpBaseAmount });

            // Actualizar display principal del ITP
            const transferTaxStep1 = document.getElementById('transfer_tax_step1');
            if (transferTaxStep1) {
                transferTaxStep1.textContent = itp.toFixed(2) + ' €';
                logDebug('ITP-STEP1', '✅ Display principal actualizado');
            } else {
                logError('ITP-STEP1', '❌ Elemento transfer_tax_step1 no encontrado');
            }

            // Actualizar displays en el detalle del cálculo
            const precioCompraCalc = document.getElementById('precio-compra-calc');
            const valorFiscalCalc = document.getElementById('valor-fiscal-calc');
            const baseImponibleCalc = document.getElementById('base-imponible-calc');
            const regionNameCalc = document.getElementById('region-name-calc');
            const tipoImpositivoCalc = document.getElementById('tipo-impositivo-calc');

            if (precioCompraCalc) precioCompraCalc.textContent = purchasePrice.toFixed(2) + ' €';
            if (valorFiscalCalc) valorFiscalCalc.textContent = fiscalValue.toFixed(2) + ' €';
            if (baseImponibleCalc) baseImponibleCalc.textContent = baseImponible.toFixed(2) + ' €';
            if (regionNameCalc) regionNameCalc.textContent = region || '-';
            if (tipoImpositivoCalc) tipoImpositivoCalc.textContent = taxRatePercent.toFixed(0) + '%';

            logDebug('ITP-STEP1', '✅ Todos los displays actualizados');
        } catch (error) {
            logError('ITP-STEP1', '❌ Error en cálculo:', error);
        }
    }
    // Exponer globalmente para que esté disponible en otros scopes
    window.actualizarCalculoITPStep1 = actualizarCalculoITPStep1;

    // Botones ITP pagado/no pagado (variables ya declaradas globalmente)
    if (itpSiBtn && itpNoBtn) {
        logDebug('PRECIO-FLOW', '✅ Botones ITP encontrados');
        console.log('DEBUG: Botones ITP encontrados', { itpSiBtn, itpNoBtn });

        itpSiBtn.addEventListener('click', function() {
            logDebug('PRECIO-FLOW', '✅ Usuario seleccionó: ITP YA PAGADO');
            itpPagado = true;
            gestionamosITP = false;
            basePrice = BASE_TRANSFER_PRICE_SIN_ITP; // 134.99€

            // Mostrar contenedor Modelo 620 en documentos
            const itpPaymentProofRow = document.getElementById('itp-payment-proof-row');
            const itpComprobante = document.getElementById('upload-itp-comprobante');
            if (itpPaymentProofRow) {
                itpPaymentProofRow.style.display = 'grid';
                logDebug('PRECIO-FLOW', '📄 Mostrando contenedor Modelo 620');
            }
            if (itpComprobante) {
                itpComprobante.required = true;
                logDebug('PRECIO-FLOW', '✅ Campo Modelo 620 ahora requerido');
            }

            // Estilos activo
            itpSiBtn.style.background = '#10b981';
            itpSiBtn.style.color = 'white';
            itpSiBtn.style.borderColor = '#10b981';

            itpNoBtn.style.background = 'white';
            itpNoBtn.style.color = '#6b7280';
            itpNoBtn.style.borderColor = '#e5e7eb';

            // Ocultar título, subtítulo y cajas superiores con animación
            const precioTitulo = document.getElementById('precio-titulo');
            const precioSubtitulo = document.getElementById('precio-subtitulo');
            const tramitacionBox = document.getElementById('tramitacion-completa-box');
            const itpInfoBox = document.getElementById('itp-info-box');

            // Animación de desaparición
            [precioTitulo, precioSubtitulo, tramitacionBox, itpInfoBox].forEach(elem => {
                if (elem) {
                    elem.style.transition = 'all 0.3s ease';
                    elem.style.opacity = '0';
                    elem.style.transform = 'translateY(-20px)';
                }
            });

            // Reducir tamaño del selector
            const questionContainer = document.getElementById('itp-question-container');
            questionContainer.style.padding = '12px 16px';
            questionContainer.querySelector('h3').style.fontSize = '14px';
            questionContainer.querySelector('h3').style.margin = '0';
            questionContainer.querySelector('p').style.display = 'none';
            questionContainer.querySelectorAll('.itp-choice-btn').forEach(btn => {
                btn.style.padding = '8px 16px';
                btn.style.fontSize = '13px';
                btn.style.maxWidth = '150px';
            });

            setTimeout(() => {
                // Ocultar elementos superiores
                [precioTitulo, precioSubtitulo, tramitacionBox, itpInfoBox].forEach(elem => {
                    if (elem) elem.style.display = 'none';
                });

                // Mostrar flujo "ya pagado"
                document.getElementById('itp-no-pagado-flow').style.display = 'none';
                const yaPagadoFlow = document.getElementById('itp-ya-pagado-flow');
                yaPagadoFlow.style.display = 'block';
                yaPagadoFlow.style.opacity = '0';
                yaPagadoFlow.style.transform = 'translateY(20px)';

                setTimeout(() => {
                    yaPagadoFlow.style.transition = 'all 0.3s ease';
                    yaPagadoFlow.style.opacity = '1';
                    yaPagadoFlow.style.transform = 'translateY(0)';
                }, 50);

                // Actualizar sidebar
                actualizarSidebarPrecio();
            }, 300);
        });

        itpNoBtn.addEventListener('click', function() {
            logDebug('PRECIO-FLOW', '❌ Usuario seleccionó: ITP NO PAGADO');
            itpPagado = false;

            // Ocultar contenedor Modelo 620 en documentos
            const itpPaymentProofRow = document.getElementById('itp-payment-proof-row');
            const itpComprobante = document.getElementById('upload-itp-comprobante');
            if (itpPaymentProofRow) {
                itpPaymentProofRow.style.display = 'none';
                logDebug('PRECIO-FLOW', '📄 Ocultando contenedor Modelo 620');
            }
            if (itpComprobante) {
                itpComprobante.required = false;
                logDebug('PRECIO-FLOW', '❌ Campo Modelo 620 ya no requerido');
            }

            // Estilos activo
            itpNoBtn.style.background = '#016d86';
            itpNoBtn.style.color = 'white';
            itpNoBtn.style.borderColor = '#016d86';

            itpSiBtn.style.background = 'white';
            itpSiBtn.style.color = '#6b7280';
            itpSiBtn.style.borderColor = '#e5e7eb';

            // Ocultar título, subtítulo y cajas superiores con animación (IGUAL QUE EL BOTÓN SÍ)
            const precioTitulo = document.getElementById('precio-titulo');
            const precioSubtitulo = document.getElementById('precio-subtitulo');
            const tramitacionBox = document.getElementById('tramitacion-completa-box');
            const itpInfoBox = document.getElementById('itp-info-box');

            // Animación de desaparición de elementos superiores
            [precioTitulo, precioSubtitulo, tramitacionBox, itpInfoBox].forEach(elem => {
                if (elem) {
                    elem.style.transition = 'all 0.3s ease';
                    elem.style.opacity = '0';
                    elem.style.transform = 'translateY(-20px)';
                }
            });

            // Reducir tamaño del selector (IGUAL QUE EL BOTÓN SÍ)
            const questionContainer = document.getElementById('itp-question-container');
            questionContainer.style.padding = '12px 16px';
            questionContainer.querySelector('h3').style.fontSize = '14px';
            questionContainer.querySelector('h3').style.margin = '0';
            questionContainer.querySelector('p').style.display = 'none';
            questionContainer.querySelectorAll('.itp-choice-btn').forEach(btn => {
                btn.style.padding = '8px 16px';
                btn.style.fontSize = '13px';
                btn.style.maxWidth = '150px';
            });

            // Después de la animación, ocultar completamente y mostrar flujo "no pagado"
            setTimeout(() => {
                // Ocultar elementos superiores
                [precioTitulo, precioSubtitulo, tramitacionBox, itpInfoBox].forEach(elem => {
                    if (elem) {
                        elem.style.display = 'none';
                    }
                });

                // Mostrar flujo "no pagado" con animación
                document.getElementById('itp-ya-pagado-flow').style.display = 'none';
                const noPagadoFlow = document.getElementById('itp-no-pagado-flow');
                noPagadoFlow.style.display = 'block';
                noPagadoFlow.style.opacity = '0';
                noPagadoFlow.style.transform = 'translateY(20px)';

                setTimeout(() => {
                    noPagadoFlow.style.transition = 'all 0.3s ease';
                    noPagadoFlow.style.opacity = '1';
                    noPagadoFlow.style.transform = 'translateY(0)';
                }, 50);

                // Actualizar sidebar
                actualizarSidebarPrecio();
            }, 300);

            // Calcular ITP base
            itpBaseAmount = currentTransferTax || 0;
            logDebug('PRECIO-FLOW', 'ITP base calculado:', itpBaseAmount);

            // Actualizar displays del resumen ITP inmediatamente
            const itpBaseDisplay = document.getElementById('itp-base-display');
            if (itpBaseDisplay) {
                itpBaseDisplay.textContent = itpBaseAmount.toFixed(2) + ' €';
                logDebug('PRECIO-FLOW', '✅ Display ITP base actualizado:', itpBaseAmount);
            }
        });
    } else {
        logError('PRECIO-FLOW', '❌ No se encontraron botones ITP');
        console.error('DEBUG: Botones ITP NO encontrados', { 
            itpSiBtn, 
            itpNoBtn,
            getElementById_itp_si: document.getElementById('itp-si'),
            getElementById_itp_no: document.getElementById('itp-no')
        });
    }

    // Gestión de opciones ITP (lo pago yo / lo gestionan ustedes)
    document.querySelectorAll('input[name="itp_gestion"]').forEach(radio => {
        radio.addEventListener('change', function() {
            itpGestionSeleccionada = this.value;
            console.log('🏛️ ITP GESTIÓN SELECCIONADA:', itpGestionSeleccionada);
            console.log('🔍 Radio value:', this.value);
            console.log('🔍 Radio checked:', this.checked);
            logDebug('PRECIO-FLOW', 'Gestión ITP seleccionada:', itpGestionSeleccionada);
            
            // Actualizar campo hidden
            const hiddenField = document.getElementById('itp_management_option');
            if (hiddenField) {
                hiddenField.value = itpGestionSeleccionada;
                console.log('✅ Campo hidden itp_management_option actualizado:', hiddenField.value);
            }

            // Actualizar estilos de las opciones
            document.querySelectorAll('.itp-gestion-option').forEach(opt => {
                if (opt.querySelector('input').checked) {
                    opt.style.borderColor = '#016d86';
                    opt.style.background = '#eff6ff';
                } else {
                    opt.style.borderColor = '#e5e7eb';
                    opt.style.background = 'white';
                }
            });

            if (itpGestionSeleccionada === 'gestionan-ustedes') {
                // Mostrar métodos de pago
                gestionamosITP = true;
                basePrice = BASE_TRANSFER_PRICE_CON_ITP; // 174.99€
                document.getElementById('metodos-pago-itp').style.display = 'block';
                document.getElementById('btn-container-yo-pago').style.display = 'none';
                document.getElementById('itp-pago-resumen').style.display = 'none';
                
                // NO ocultar automáticamente, esperar a que se seleccione método de pago
            } else if (itpGestionSeleccionada === 'yo-pago') {
                // Mostrar botón de ver desglose para "lo pago yo"
                gestionamosITP = false;
                basePrice = BASE_TRANSFER_PRICE_SIN_ITP; // 134.99€
                document.getElementById('metodos-pago-itp').style.display = 'none';
                document.getElementById('itp-pago-resumen').style.display = 'none';
                document.getElementById('btn-container-yo-pago').style.display = 'block';

                // Ocultar opciones y mostrar resumen compacto
                setTimeout(() => {
                    ocultarOpcionesYMostrarResumen('Lo pago yo mismo');
                }, 200);

                // Actualizar sidebar
                actualizarSidebarPrecio();
            }
        });
    });

    // Métodos de pago ITP
    document.querySelectorAll('input[name="metodo_pago_itp"]').forEach(radio => {
        radio.addEventListener('change', function() {
            itpMetodoPago = this.value;
            console.log('💳 ITP MÉTODO PAGO SELECCIONADO:', itpMetodoPago);
            console.log('🔍 Radio value:', this.value);
            console.log('🔍 Radio checked:', this.checked);
            logDebug('PRECIO-FLOW', 'Método pago ITP seleccionado:', itpMetodoPago);
            
            // Actualizar campo hidden
            const hiddenField = document.getElementById('itp_payment_method');
            if (hiddenField) {
                hiddenField.value = itpMetodoPago;
                console.log('✅ Campo hidden itp_payment_method actualizado:', hiddenField.value);
            }

            // Calcular totales
            if (itpMetodoPago === 'tarjeta') {
                itpComisionTarjeta = itpBaseAmount * 0.02;
                itpTotalAmount = itpBaseAmount + itpComisionTarjeta;
            } else {
                itpComisionTarjeta = 0;
                itpTotalAmount = itpBaseAmount;
            }
            
            // Actualizar campos hidden con valores calculados
            const itpAmountField = document.getElementById('itp_amount');
            const itpCommissionField = document.getElementById('itp_commission');
            const itpTotalField = document.getElementById('itp_total_amount');
            
            if (itpAmountField) {
                itpAmountField.value = itpBaseAmount.toFixed(2);
                console.log('✅ Campo hidden itp_amount actualizado:', itpAmountField.value);
            }
            if (itpCommissionField) {
                itpCommissionField.value = itpComisionTarjeta.toFixed(2);
                console.log('✅ Campo hidden itp_commission actualizado:', itpCommissionField.value);
            }
            if (itpTotalField) {
                itpTotalField.value = itpTotalAmount.toFixed(2);
                console.log('✅ Campo hidden itp_total_amount actualizado:', itpTotalField.value);
            }

            // Actualizar displays
            document.getElementById('itp-base-display').textContent = itpBaseAmount.toFixed(2) + ' €';
            document.getElementById('comision-tarjeta-display').textContent = itpComisionTarjeta.toFixed(2) + ' €';
            document.getElementById('itp-total-display').textContent = itpTotalAmount.toFixed(2) + ' €';

            // Mostrar/ocultar fila de comisión
            const comisionRow = document.getElementById('comision-tarjeta-row');
            comisionRow.style.display = (itpMetodoPago === 'tarjeta') ? 'block' : 'none';

            // Mostrar resumen
            document.getElementById('itp-pago-resumen').style.display = 'block';

            // Actualizar mensaje del método de pago
            const metodoPagoInfo = document.getElementById('metodo-pago-info');
            if (itpMetodoPago === 'tarjeta') {
                metodoPagoInfo.innerHTML = 'Pago con tarjeta <strong>(al momento)</strong> - incluye comisión del 1,5%';
            } else {
                metodoPagoInfo.innerHTML = '🏦 Pago por transferencia bancaria - sin comisión adicional';
            }

            // Ocultar opciones y mostrar resumen compacto después de un breve delay
            setTimeout(() => {
                const textoResumen = itpMetodoPago === 'tarjeta' ? 
                    'Lo gestionamos nosotros (con tarjeta)' : 
                    'Lo gestionamos nosotros (transferencia)';
                ocultarOpcionesYMostrarResumen(textoResumen);
            }, 300);

            logDebug('PRECIO-FLOW', 'Cálculo ITP:', {
                base: itpBaseAmount,
                comision: itpComisionTarjeta,
                total: itpTotalAmount
            });

            // Actualizar sidebar
            actualizarSidebarPrecio();
        });
    });

    // Event listeners para los botones de ver desglose
    const btnVerDesgloseSi = document.getElementById('btn-ver-desglose-si');
    if (btnVerDesgloseSi) {
        btnVerDesgloseSi.addEventListener('click', function() {
            logDebug('PRECIO-FLOW', '📋 Ver desglose - ITP ya pagado');
            mostrarPrecioStep2();
        });
    }

    const btnVerDesgloseYoPago = document.getElementById('btn-ver-desglose-yo-pago');
    if (btnVerDesgloseYoPago) {
        btnVerDesgloseYoPago.addEventListener('click', function() {
            logDebug('PRECIO-FLOW', '📋 Ver desglose - ITP lo pago yo');
            mostrarPrecioStep2();
        });
    }

    const btnVerDesgloseGestionamos = document.getElementById('btn-ver-desglose-gestionamos');
    if (btnVerDesgloseGestionamos) {
        btnVerDesgloseGestionamos.addEventListener('click', function() {
            logDebug('PRECIO-FLOW', '📋 Ver desglose - ITP gestionado por nosotros');
            mostrarPrecioStep2();
        });
    }

    // Función para mostrar step 2
    function mostrarPrecioStep2() {
        logDebug('PRECIO-FLOW', '🔄 Iniciando transición a paso 2');
        logDebug('PRECIO-FLOW', 'Estado ITP pagado:', itpPagado);

        if (precioStep1 && precioStep2) {
            logDebug('PRECIO-FLOW', '✅ Elementos step1 y step2 encontrados');

            // Fade out del step 1
            precioStep1.style.opacity = '0';
            precioStep1.style.transform = 'translateY(-20px)';
            logDebug('PRECIO-FLOW', '↗️ Fade out aplicado al paso 1');

            setTimeout(() => {
                // Ocultar step 1 completamente
                precioStep1.style.display = 'none';
                logDebug('PRECIO-FLOW', '❌ Paso 1 oculto');

                // Mostrar step 2 con fade in
                precioStep2.style.display = 'block';
                precioStep2.style.opacity = '0';
                precioStep2.style.transform = 'translateY(20px)';

                // Forzar reflow
                precioStep2.offsetHeight;

                // Aplicar animación fade in
                precioStep2.style.transition = 'opacity 0.4s ease-out, transform 0.4s ease-out';
                precioStep2.style.opacity = '1';
                precioStep2.style.transform = 'translateY(0)';
                logDebug('PRECIO-FLOW', '✅ Paso 2 mostrado con fade in');

                precioStep = 2;

                // Actualizar lo que incluye según ITP
                const incluyeItpSi = document.getElementById('incluye-itp-si');
                const incluyeItpNo = document.getElementById('incluye-itp-no');

                if (itpPagado === true) {
                    incluyeItpSi.style.display = 'block';
                    incluyeItpNo.style.display = 'none';
                    logDebug('PRECIO-FLOW', '✅ Mostrado: ITP ya pagado');
                } else {
                    if (itpGestionSeleccionada === 'yo-pago') {
                        incluyeItpSi.style.display = 'block';
                        incluyeItpNo.style.display = 'none';
                        logDebug('PRECIO-FLOW', '✅ Mostrado: Usuario paga ITP por su cuenta');
                    } else {
                        incluyeItpSi.style.display = 'none';
                        incluyeItpNo.style.display = 'block';
                        logDebug('PRECIO-FLOW', '✅ Mostrado: Pago de ITP incluido (gestionamos nosotros)');
                    }
                }

                // Actualizar precio total
                actualizarPrecioFinal();

                // Actualizar resumen en sidebar
                actualizarSidebarPrecio();

                logDebug('PRECIO-FLOW', '✅ Transición completada');
            }, 300);
        } else {
            logError('PRECIO-FLOW', '❌ No se encontraron elementos step1 o step2');
        }
    }

    // Toggle cupón
    if (toggleCuponBtn) {
        toggleCuponBtn.addEventListener('click', function() {
            if (cuponContent.style.display === 'none') {
                cuponContent.style.display = 'block';
                cuponIcon.style.transform = 'rotate(180deg)';
            } else {
                cuponContent.style.display = 'none';
                cuponIcon.style.transform = 'rotate(0deg)';
            }
        });
    }

    // Funciones para gestión ITP: Ocultar opciones y mostrar resumen compacto
    function ocultarOpcionesYMostrarResumen(textoResumen) {
        const opcionesContainer = document.getElementById('itp-opciones-container');
        const resumenCompacto = document.getElementById('itp-resumen-compacto');
        const resumenTexto = document.getElementById('resumen-itp-texto');
        
        if (opcionesContainer && resumenCompacto && resumenTexto) {
            // Actualizar texto del resumen
            resumenTexto.textContent = `ITP: ${textoResumen}`;
            
            // Animación de ocultación más rápida
            opcionesContainer.style.opacity = '0';
            opcionesContainer.style.transform = 'translateY(-5px)';
            
            setTimeout(() => {
                opcionesContainer.style.display = 'none';
                resumenCompacto.style.display = 'block';
                
                // Animación de aparición del resumen más rápida
                setTimeout(() => {
                    resumenCompacto.style.opacity = '1';
                    resumenCompacto.style.transform = 'translateY(0)';
                }, 20);
            }, 150);
            
            console.log('✅ ITP: Opciones ocultadas, resumen mostrado:', textoResumen);
        }
    }
    
    // Función global para botón Modificar ITP
    window.mostrarOpcionesITP = function() {
        const opcionesContainer = document.getElementById('itp-opciones-container');
        const resumenCompacto = document.getElementById('itp-resumen-compacto');
        
        if (opcionesContainer && resumenCompacto) {
            console.log('🔄 ITP: Iniciando restauración de opciones...');
            
            // Ocultar resumen compacto más rápido
            resumenCompacto.style.opacity = '0';
            resumenCompacto.style.transform = 'translateY(-5px)';
            
            setTimeout(() => {
                resumenCompacto.style.display = 'none';
                opcionesContainer.style.display = 'block';
                
                // También ocultar elementos que podrían estar visibles
                document.getElementById('btn-container-yo-pago').style.display = 'none';
                document.getElementById('itp-pago-resumen').style.display = 'none';
                
                // Resetear métodos de pago para que no aparezcan
                document.getElementById('metodos-pago-itp').style.display = 'none';
                
                // Resetear selecciones de radio buttons para permitir nueva selección
                document.querySelectorAll('input[name="itp_gestion"]').forEach(radio => {
                    radio.checked = false;
                });
                document.querySelectorAll('input[name="metodo_pago_itp"]').forEach(radio => {
                    radio.checked = false;
                });
                
                // Resetear variables globales
                itpGestionSeleccionada = '';
                itpMetodoPago = '';
                
                // Resetear estilos de las opciones
                document.querySelectorAll('.itp-gestion-option').forEach(opt => {
                    opt.style.borderColor = '#e5e7eb';
                    opt.style.background = 'white';
                });
                
                // Animación de aparición de las opciones
                setTimeout(() => {
                    opcionesContainer.style.opacity = '1';
                    opcionesContainer.style.transform = 'translateY(0)';
                }, 20);
            }, 100);
            
            console.log('✅ ITP: Opciones restauradas para modificación');
        }
    }

    // Actualizar resumen en sidebar
    function actualizarSidebarPrecio() {
        logDebug('SIDEBAR-PRECIO', '💰 Actualizando asistente de cálculo');

        const sidebarPrecioContent = document.getElementById('sidebar-precio-content');
        if (!sidebarPrecioContent) {
            logError('SIDEBAR-PRECIO', 'Contenedor no encontrado');
            return;
        }

        let contenido = '';

        // 1. Datos del vehículo (clickable para volver)
        const purchasePrice = parseFloat(document.getElementById('purchase_price')?.value) || 0;
        const region = document.getElementById('region')?.value || '';
        const regionName = region ? (region.charAt(0).toUpperCase() + region.slice(1).replace(/-/g, ' ')) : 'Región pendiente';

        contenido += `
            <div class="sidebar-price-section" data-section="vehiculo" style="cursor: pointer; padding: 10px; background: rgba(255,255,255,0.05); border-radius: 6px; margin-bottom: 10px; border-left: 3px solid ${purchasePrice && region ? '#10b981' : '#f59e0b'}; transition: all 0.2s;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                    <span style="font-size: 12px; color: rgba(255,255,255,0.7);">Vehículo</span>
                    ${purchasePrice && region ? '<i class="fa-solid fa-check-circle" style="color: #10b981; font-size: 14px;"></i>' : '<i class="fa-solid fa-exclamation-circle" style="color: #f59e0b; font-size: 14px;"></i>'}
                </div>
                <div style="font-size: 13px; color: white; font-weight: 600;">
                    ${purchasePrice ? purchasePrice.toLocaleString('es-ES', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' €' : 'Precio pendiente'}
                </div>
                <div style="font-size: 11px; color: rgba(255,255,255,0.6); margin-top: 4px;">
                    ${regionName}
                </div>
            </div>
        `;

        // 2. Cálculo del ITP - SIEMPRE MOSTRAR
        const transferTax = currentTransferTax || 0;
        const taxRate = region ? ((itpRates[region] || 0.04) * 100) : 0;

        contenido += `
            <div style="padding: 10px; background: rgba(251, 191, 36, 0.1); border-radius: 6px; margin-bottom: 10px; border-left: 3px solid #f59e0b;">
                <div style="font-size: 12px; color: rgba(255,255,255,0.7); margin-bottom: 6px;">Impuesto (ITP)</div>
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-size: 13px; color: white;">${taxRate.toFixed(0)}% · ${regionName}</span>
                    <strong style="font-size: 15px; color: #fbbf24;">${transferTax.toLocaleString('es-ES', {minimumFractionDigits: 2, maximumFractionDigits: 2})} €</strong>
                </div>
            </div>
        `;

        // 3. Estado del ITP - SOLO MOSTRAR SI YA SE SELECCIONÓ
        if (itpPagado !== null) {
            let estadoTexto = '';
            let estadoColor = '';
            let estadoIcon = '';

            if (itpPagado === true) {
                estadoTexto = 'Ya pagado';
                estadoColor = '#10b981';
                estadoIcon = 'fa-check-circle';
            } else {
                if (itpGestionSeleccionada === 'gestionan-ustedes') {
                    estadoTexto = 'Lo gestionamos (incluido)';
                    estadoColor = '#016d86';
                    estadoIcon = 'fa-building';
                } else if (itpGestionSeleccionada === 'yo-pago') {
                    estadoTexto = 'Lo pagas tú (no incluido)';
                    estadoColor = '#6b7280';
                    estadoIcon = 'fa-user';
                }
            }

            contenido += `
                <div class="sidebar-price-section" data-section="itp-decision" style="cursor: pointer; padding: 10px; background: rgba(255,255,255,0.05); border-radius: 6px; margin-bottom: 10px; border-left: 3px solid ${estadoColor}; transition: all 0.2s;">
                    <div style="font-size: 12px; color: rgba(255,255,255,0.7); margin-bottom: 6px;">3. Gestión del ITP</div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <i class="fa-solid ${estadoIcon}" style="color: ${estadoColor}; font-size: 14px;"></i>
                        <span style="font-size: 13px; color: white; font-weight: 600;">${estadoTexto}</span>
                    </div>
                </div>
            `;

            // Método de pago ITP (si aplica)
            if (itpPagado === false && itpGestionSeleccionada === 'gestionan-ustedes' && itpMetodoPago) {
                const metodoTexto = itpMetodoPago === 'tarjeta' ? 'Tarjeta (+1,5% comisión)' : 'Transferencia (sin comisión)';
                const comision = itpMetodoPago === 'tarjeta' ? itpComisionTarjeta : 0;

                contenido += `
                    <div style="padding: 10px; background: rgba(255,255,255,0.08); border-radius: 6px; margin-bottom: 10px; margin-left: 16px; border-left: 2px solid rgba(255,255,255,0.3);">
                        <div style="font-size: 11px; color: rgba(255,255,255,0.6); margin-bottom: 4px;">Método de pago ITP:</div>
                        <div style="font-size: 12px; color: white; font-weight: 600;">${metodoTexto}</div>
                        ${comision > 0 ? `<div style="font-size: 11px; color: #fca5a5; margin-top: 4px;">+${comision.toFixed(2)} € comisión</div>` : ''}
                    </div>
                `;
            }

            // 4. Honorarios/Tramitación - SOLO MOSTRAR SI YA SE SELECCIONÓ ITP
            contenido += `
                <div style="padding: 10px; background: rgba(255,255,255,0.05); border-radius: 6px; margin-bottom: 10px; border-left: 3px solid #8b5cf6;">
                    <div style="font-size: 12px; color: rgba(255,255,255,0.7); margin-bottom: 6px;">Honorarios</div>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-size: 13px; color: white;">Gestión DGMM</span>
                        <strong style="font-size: 15px; color: white;">${(gestionamosITP ? BASE_TRANSFER_PRICE_CON_ITP : BASE_TRANSFER_PRICE_SIN_ITP).toFixed(2)} €</strong>
                    </div>
                </div>
            `;

            // 5. Cambio de lista (si está seleccionado)
            if (cambioListaSeleccionado) {
                contenido += `
                    <div style="padding: 10px; background: rgba(255,255,255,0.05); border-radius: 6px; margin-bottom: 10px; border-left: 3px solid #06b6d4;">
                        <div style="font-size: 12px; color: rgba(255,255,255,0.7); margin-bottom: 6px;">Cambio de lista</div>
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div style="display: flex; align-items: center; gap: 6px;">
                                <i class="fa-solid fa-check-circle" style="color: #06b6d4; font-size: 14px;"></i>
                                <span style="font-size: 13px; color: white;">Incluido</span>
                            </div>
                            <strong style="font-size: 15px; color: white;">+${PRECIO_CAMBIO_LISTA.toFixed(2)} €</strong>
                        </div>
                    </div>
                `;
            }

            // 6. TOTAL - SOLO MOSTRAR SI YA SE SELECCIONÓ ITP
            let totalFinal = gestionamosITP ? BASE_TRANSFER_PRICE_CON_ITP : BASE_TRANSFER_PRICE_SIN_ITP;
            if (itpPagado === false && itpGestionSeleccionada === 'gestionan-ustedes') {
                totalFinal += itpTotalAmount;
            }
            if (cambioListaSeleccionado) {
                totalFinal += PRECIO_CAMBIO_LISTA;
            }
            if (cambioNombreSeleccionado) {
                totalFinal += PRECIO_CAMBIO_NOMBRE;
            }
            if (cambioPuertoSeleccionado) {
                totalFinal += PRECIO_CAMBIO_PUERTO;
            }
            if (couponDiscountPercent > 0) {
                totalFinal = totalFinal * (1 - couponDiscountPercent / 100);
            }

            contenido += `
                <div style="padding: 14px; background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(5, 150, 105, 0.2)); border-radius: 8px; border: 2px solid #10b981; margin-top: 16px;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-size: 14px; color: white; font-weight: 600;">TOTAL</span>
                        <strong style="font-size: 20px; color: #10b981;">${totalFinal.toFixed(2)} €</strong>
                    </div>
                    ${couponDiscountPercent > 0 ? `<div style="font-size: 11px; color: #6ee7b7; margin-top: 6px;">Cupón aplicado: -${couponDiscountPercent}%</div>` : ''}
                </div>
            `;
        }

        sidebarPrecioContent.innerHTML = contenido;

        // Event listeners para navegación
        setTimeout(() => {
            document.querySelectorAll('.sidebar-price-section').forEach(section => {
                section.addEventListener('click', function() {
                    const sectionType = this.dataset.section;
                    if (sectionType === 'vehiculo') {
                        // Navegar a página de vehículo
                        goToPage(0);
                    } else if (sectionType === 'itp-decision') {
                        // Enfocar el selector de ITP
                        const itpSelector = document.getElementById('itp-question-container');
                    }
                });
                // Hover effect
                section.addEventListener('mouseenter', function() {
                    this.style.background = 'rgba(255,255,255,0.12)';
                });
                section.addEventListener('mouseleave', function() {
                    this.style.background = 'rgba(255,255,255,0.05)';
                });
            });
        }, 100);

        logDebug('SIDEBAR-PRECIO', '✅ Asistente actualizado');
    }

    // Función para volver al paso 1 de precio
    function volverAPaso1Precio() {
        logDebug('PRECIO-FLOW', '🔙 Volviendo al paso 1 de precio');
        
        // Ocultar contenedor Modelo 620 cuando se vuelve al paso 1
        const itpPaymentProofRow = document.getElementById('itp-payment-proof-row');
        const itpComprobante = document.getElementById('upload-itp-comprobante');
        if (itpPaymentProofRow) {
            itpPaymentProofRow.style.display = 'none';
            logDebug('PRECIO-FLOW', '📄 Ocultando Modelo 620 al volver al paso 1');
        }
        if (itpComprobante) {
            itpComprobante.required = false;
        }

        // Fade out del paso 2
        if (precioStep2) {
            precioStep2.style.opacity = '0';
            precioStep2.style.transform = 'translateY(20px)';

            setTimeout(() => {
                precioStep2.style.display = 'none';

                // Mostrar paso 1
                precioStep1.style.display = 'block';
                precioStep1.style.opacity = '0';
                precioStep1.style.transform = 'translateY(-20px)';

                // Forzar reflow
                precioStep1.offsetHeight;

                // Fade in
                precioStep1.style.opacity = '1';
                precioStep1.style.transform = 'translateY(0)';

                precioStep = 1;

                // RESTAURAR ELEMENTOS OCULTOS
                const precioTitulo = document.getElementById('precio-titulo');
                const precioSubtitulo = document.getElementById('precio-subtitulo');
                const tramitacionBox = document.getElementById('tramitacion-completa-box');
                const itpInfoBox = document.getElementById('itp-info-box');

                if (precioTitulo) {
                    precioTitulo.style.display = 'block';
                    precioTitulo.style.opacity = '1';
                    precioTitulo.style.transform = 'translateY(0)';
                }
                if (precioSubtitulo) {
                    precioSubtitulo.style.display = 'block';
                    precioSubtitulo.style.opacity = '1';
                    precioSubtitulo.style.transform = 'translateY(0)';
                }
                if (tramitacionBox) {
                    tramitacionBox.style.display = 'block';
                    tramitacionBox.style.opacity = '1';
                    tramitacionBox.style.transform = 'translateY(0)';
                }
                if (itpInfoBox) {
                    itpInfoBox.style.display = 'block';
                    itpInfoBox.style.opacity = '1';
                    itpInfoBox.style.transform = 'translateY(0)';
                }

                // Restaurar tamaño del selector
                const questionContainer = document.getElementById('itp-question-container');
                questionContainer.style.padding = '24px';
                questionContainer.querySelector('h3').style.fontSize = '18px';
                questionContainer.querySelector('h3').style.margin = '0 0 8px 0';
                const subtitleP = questionContainer.querySelector('p');
                if (subtitleP) subtitleP.style.display = 'block';
                questionContainer.querySelectorAll('.itp-choice-btn').forEach(btn => {
                    btn.style.padding = '16px 24px';
                    btn.style.fontSize = '16px';
                    btn.style.maxWidth = '200px';
                });

                // Ocultar flujos
                document.getElementById('itp-ya-pagado-flow').style.display = 'none';
                document.getElementById('itp-no-pagado-flow').style.display = 'none';

                // ACTUALIZAR RESUMEN después de volver al paso 1
                if (typeof updatePaymentSummary === 'function') {
                    setTimeout(() => {
                        updatePaymentSummary();
                        logDebug('PRECIO-FLOW', '📋 Resumen actualizado después de volver al paso 1');
                    }, 100);
                }

                logDebug('PRECIO-FLOW', '✅ Vuelto al paso 1 - Elementos restaurados');
            }, 300);
        }
    }
    // Exponer globalmente para que esté disponible en otros scopes
    window.actualizarSidebarPrecio = actualizarSidebarPrecio;

    // Actualizar precio final en step 2
    function actualizarPrecioFinal() {
        logDebug('PRECIO-FINAL', '💰 Calculando precio final y desglose completo');

        const precioBase = gestionamosITP ? BASE_TRANSFER_PRICE_CON_ITP : BASE_TRANSFER_PRICE_SIN_ITP;
        let total = precioBase;
        let subtotal = precioBase;

        // 1. TRAMITACIÓN - siempre se muestra
        const desgloseTramitacion = document.getElementById('desglose-tramitacion');
        if (desgloseTramitacion) {
            desgloseTramitacion.textContent = precioBase.toFixed(2) + ' €';
        }

        // 2. ITP - según caso de trámite
        const desgloseItp = document.getElementById('desglose-itp');
        const desgloseComision = document.getElementById('desglose-comision');
        const desgloseComisionContainer = document.getElementById('desglose-comision-container');
        const itpDesgloseDescripcion = document.getElementById('itp-desglose-descripcion');

        if (itpPagado === false && itpGestionSeleccionada === 'gestionan-ustedes') {
            // CASO 3: Lo gestionamos nosotros - mostrar ITP en desglose
            const itpBase = itpBaseAmount || currentTransferTax || 0;

            if (desgloseItp) {
                desgloseItp.textContent = itpBase.toFixed(2) + ' €';
            }
            total += itpBase;

            // Mostrar comisión si paga con tarjeta
            if (itpMetodoPago === 'tarjeta') {
                const comision = itpBase * 0.015;
                if (desgloseComision) {
                    desgloseComision.textContent = comision.toFixed(2) + ' €';
                }
                if (desgloseComisionContainer) {
                    desgloseComisionContainer.style.display = 'block';
                }
                total += comision;
                if (itpDesgloseDescripcion) {
                    itpDesgloseDescripcion.textContent = 'Pagado con tarjeta (+1,5% comisión)';
                }
            } else {
                if (desgloseComisionContainer) {
                    desgloseComisionContainer.style.display = 'none';
                }
                if (itpDesgloseDescripcion) {
                    itpDesgloseDescripcion.textContent = 'Pagado por transferencia bancaria';
                }
            }
            logDebug('PRECIO-FINAL', 'ITP incluido (gestionamos):', itpBase);
        }

        // 3. SERVICIOS EXTRAS - Cambio de lista
        const desgloseExtrasContainer = document.getElementById('desglose-extras-container');
        if (desgloseExtrasContainer) {
            desgloseExtrasContainer.innerHTML = ''; // Limpiar

            // Añadir cambio de lista si está seleccionado
            if (cambioListaSeleccionado) {
                total += PRECIO_CAMBIO_LISTA;

                const extraLine = document.createElement('div');
                extraLine.style.cssText = 'display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #e5e7eb;';
                extraLine.innerHTML = `
                    <div>
                        <div style="font-size: 15px; font-weight: 600; color: #1f2937;">Cambio de lista</div>
                        <div style="font-size: 13px; color: #6b7280; margin-top: 2px;">Servicio adicional</div>
                    </div>
                    <div style="font-size: 16px; font-weight: 700; color: #1f2937;">${PRECIO_CAMBIO_LISTA.toFixed(2)} €</div>
                `;
                desgloseExtrasContainer.appendChild(extraLine);
            }

            // Añadir cambio de nombre si está seleccionado
            if (cambioNombreSeleccionado) {
                total += PRECIO_CAMBIO_NOMBRE;

                const extraLine = document.createElement('div');
                extraLine.style.cssText = 'display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #e5e7eb;';
                extraLine.innerHTML = `
                    <div>
                        <div style="font-size: 15px; font-weight: 600; color: #1f2937;">Cambio de nombre</div>
                        <div style="font-size: 13px; color: #6b7280; margin-top: 2px;">Servicio adicional</div>
                    </div>
                    <div style="font-size: 16px; font-weight: 700; color: #1f2937;">${PRECIO_CAMBIO_NOMBRE.toFixed(2)} €</div>
                `;
                desgloseExtrasContainer.appendChild(extraLine);
            }

            // Añadir cambio de puerto si está seleccionado
            if (cambioPuertoSeleccionado) {
                total += PRECIO_CAMBIO_PUERTO;

                const extraLine = document.createElement('div');
                extraLine.style.cssText = 'display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #e5e7eb;';
                extraLine.innerHTML = `
                    <div>
                        <div style="font-size: 15px; font-weight: 600; color: #1f2937;">Cambio de puerto</div>
                        <div style="font-size: 13px; color: #6b7280; margin-top: 2px;">Servicio adicional</div>
                    </div>
                    <div style="font-size: 16px; font-weight: 700; color: #1f2937;">${PRECIO_CAMBIO_PUERTO.toFixed(2)} €</div>
                `;
                desgloseExtrasContainer.appendChild(extraLine);
            }
        }

        // 4. CUPÓN DE DESCUENTO
        const desgloseCuponContainer = document.getElementById('desglose-cupon-container');
        const desgloseCupon = document.getElementById('desglose-cupon');
        const cuponCodigoAplicado = document.getElementById('cupon-codigo-aplicado');

        if (couponDiscountPercent > 0) {
            const descuento = total * (couponDiscountPercent / 100);
            total = total - descuento;

            if (desgloseCupon) {
                desgloseCupon.textContent = '-' + descuento.toFixed(2) + ' €';
            }
            if (cuponCodigoAplicado) {
                const cuponCode = document.getElementById('coupon_code').value || 'Descuento aplicado';
                cuponCodigoAplicado.textContent = cuponCode + ' (' + couponDiscountPercent + '%)';
            }
            if (desgloseCuponContainer) {
                desgloseCuponContainer.style.display = 'block';
            }
        } else {
            if (desgloseCuponContainer) {
                desgloseCuponContainer.style.display = 'none';
            }
        }

        // 5. TOTAL FINAL
        const totalFinalEl = document.getElementById('total-final-precio');
        if (totalFinalEl) {
            totalFinalEl.textContent = total.toFixed(2) + ' €';
        }

        // También actualizar el total general (para mantener compatibilidad)
        finalAmount = total;
        const finalAmountEl = document.getElementById('final-amount');
        if (finalAmountEl) {
            finalAmountEl.textContent = total.toFixed(2) + ' €';
        }

        logDebug('PRECIO-FINAL', '✅ Desglose completo actualizado. Total:', total);
    }

    // Event listeners para botones de cambio de lista (variable ya declarada globalmente)
    const cambioListaSi = document.getElementById('cambio-lista-si');
    const cambioListaNo = document.getElementById('cambio-lista-no');

    if (cambioListaSi && cambioListaNo) {
        cambioListaSi.addEventListener('click', function() {
            // Activar cambio de lista
            cambioListaSeleccionado = true;

            // Estilos activo
            cambioListaSi.style.background = '#016d86';
            cambioListaSi.style.color = 'white';
            cambioListaSi.style.borderColor = '#016d86';

            cambioListaNo.style.background = 'white';
            cambioListaNo.style.color = '#6b7280';
            cambioListaNo.style.borderColor = '#d1d5db';

            // Actualizar precio
            actualizarPrecioFinal();
            actualizarSidebarPrecio();

            logDebug('CAMBIO-LISTA', '✅ Cambio de lista seleccionado:', PRECIO_CAMBIO_LISTA);
        });

        cambioListaNo.addEventListener('click', function() {
            // Desactivar cambio de lista
            cambioListaSeleccionado = false;

            // Estilos activo
            cambioListaNo.style.background = '#10b981';
            cambioListaNo.style.color = 'white';
            cambioListaNo.style.borderColor = '#10b981';

            cambioListaSi.style.background = 'white';
            cambioListaSi.style.color = '#6b7280';
            cambioListaSi.style.borderColor = '#d1d5db';

            // Actualizar precio
            actualizarPrecioFinal();
            actualizarSidebarPrecio();

            logDebug('CAMBIO-LISTA', '❌ Cambio de lista NO seleccionado');
        });
    }

    // Event listeners para botones de cambio de nombre
    const cambioNombreSi = document.getElementById('cambio-nombre-si');
    const cambioNombreNo = document.getElementById('cambio-nombre-no');

    if (cambioNombreSi && cambioNombreNo) {
        cambioNombreSi.addEventListener('click', function() {
            // Activar cambio de nombre
            cambioNombreSeleccionado = true;

            // Estilos activo
            cambioNombreSi.style.background = '#016d86';
            cambioNombreSi.style.color = 'white';
            cambioNombreSi.style.borderColor = '#016d86';

            cambioNombreNo.style.background = 'white';
            cambioNombreNo.style.color = '#6b7280';
            cambioNombreNo.style.borderColor = '#d1d5db';

            // Actualizar precio
            actualizarPrecioFinal();
            actualizarSidebarPrecio();

            logDebug('CAMBIO-NOMBRE', '✅ Cambio de nombre seleccionado:', PRECIO_CAMBIO_NOMBRE);
        });

        cambioNombreNo.addEventListener('click', function() {
            // Desactivar cambio de nombre
            cambioNombreSeleccionado = false;

            // Estilos activo
            cambioNombreNo.style.background = '#10b981';
            cambioNombreNo.style.color = 'white';
            cambioNombreNo.style.borderColor = '#10b981';

            cambioNombreSi.style.background = 'white';
            cambioNombreSi.style.color = '#6b7280';
            cambioNombreSi.style.borderColor = '#d1d5db';

            // Actualizar precio
            actualizarPrecioFinal();
            actualizarSidebarPrecio();

            logDebug('CAMBIO-NOMBRE', '❌ Cambio de nombre NO seleccionado');
        });
    }

    // Event listeners para botones de cambio de puerto
    const cambioPuertoSi = document.getElementById('cambio-puerto-si');
    const cambioPuertoNo = document.getElementById('cambio-puerto-no');

    if (cambioPuertoSi && cambioPuertoNo) {
        cambioPuertoSi.addEventListener('click', function() {
            // Activar cambio de puerto
            cambioPuertoSeleccionado = true;

            // Estilos activo
            cambioPuertoSi.style.background = '#016d86';
            cambioPuertoSi.style.color = 'white';
            cambioPuertoSi.style.borderColor = '#016d86';

            cambioPuertoNo.style.background = 'white';
            cambioPuertoNo.style.color = '#6b7280';
            cambioPuertoNo.style.borderColor = '#d1d5db';

            // Actualizar precio
            actualizarPrecioFinal();
            actualizarSidebarPrecio();

            logDebug('CAMBIO-PUERTO', '✅ Cambio de puerto seleccionado:', PRECIO_CAMBIO_PUERTO);
        });

        cambioPuertoNo.addEventListener('click', function() {
            // Desactivar cambio de puerto
            cambioPuertoSeleccionado = false;

            // Estilos activo
            cambioPuertoNo.style.background = '#10b981';
            cambioPuertoNo.style.color = 'white';
            cambioPuertoNo.style.borderColor = '#10b981';

            cambioPuertoSi.style.background = 'white';
            cambioPuertoSi.style.color = '#6b7280';
            cambioPuertoSi.style.borderColor = '#d1d5db';

            // Actualizar precio
            actualizarPrecioFinal();
            actualizarSidebarPrecio();

            logDebug('CAMBIO-PUERTO', '❌ Cambio de puerto NO seleccionado');
        });
    }

    // Botón volver al paso 1 de precio
    const volverPrecioStep1Btn = document.getElementById('volver-precio-step1');
    if (volverPrecioStep1Btn) {
        volverPrecioStep1Btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            volverAPaso1Precio();
        });

        // Hover effects
        volverPrecioStep1Btn.addEventListener('mouseenter', function() {
            this.style.background = '#016d86';
            this.style.color = 'white';
        });
        volverPrecioStep1Btn.addEventListener('mouseleave', function() {
            this.style.background = 'white';
            this.style.color = '#016d86';
        });
    }

    // Botón volver al paso 1 de documentos
    const volverDocumentosStep1Btn = document.getElementById('volver-documentos-step1');
    if (volverDocumentosStep1Btn) {
        // DESHABILITADO: El flujo viejo ya no se usa
        console.log('⚠️ Botón volver-documentos-step1 encontrado pero deshabilitado');
        console.log('🔄 El flujo ahora usa page-firma con flujo condicional');
        // volverDocumentosStep1Btn.addEventListener('click', function(e) {
        //     logDebug('DOCS', '🔙 Volviendo al paso 1 de documentos');

            // COMENTADO: Este flujo viejo ya no se usa
            // const step2 = document.getElementById('documentos-step-2');
            // const step1 = document.getElementById('documentos-step-1');
            // ...resto del código comentado
        // });

        // Hover effects DESHABILITADOS
        // volverDocumentosStep1Btn.addEventListener('mouseenter', function() {
        //     this.style.background = '#016d86';
        //     this.style.color = 'white';
        // });
        // volverDocumentosStep1Btn.addEventListener('mouseleave', function() {
        //     this.style.background = 'white';
        //     this.style.color = '#016d86';
        // });
    }

    // Resetear flujo cuando se vuelve a página de precio
    function resetearFlujoPrecio() {
        if (precioStep1 && precioStep2) {
            precioStep1.style.display = 'block';
            precioStep2.style.display = 'none';
            precioStep = 1;
            itpPagado = null;

            // Resetear botones ITP
            if (itpSiBtn) {
                itpSiBtn.style.background = 'white';
                itpSiBtn.style.color = '#10b981';
                itpSiBtn.style.borderColor = '#10b981';
            }
            if (itpNoBtn) {
                itpNoBtn.style.background = 'white';
                itpNoBtn.style.color = '#6b7280';
                itpNoBtn.style.borderColor = '#e5e7eb';
            }
        }
    }

    // Detectar cuando se muestra la página de precio para resetear el flujo
    // Esto se manejará automáticamente cuando el usuario vuelva a la página

    // ============================================
    // SISTEMA DE SIDEBAR DINÁMICO POR PÁGINA
    // ============================================

    function actualizarSidebarDinamico(pageId) {
        console.log('🔍 [DEBUG] actualizarSidebarDinamico recibió pageId:', pageId);
        console.log('🔍 [DEBUG] typeof pageId:', typeof pageId);
        console.log('🔍 [DEBUG] pageId === "page-pago":', pageId === 'page-pago');
        logDebug('SIDEBAR-DYN', '🔄 Actualizando sidebar para página:', pageId);

        const sidebarDynamic = document.getElementById('sidebar-dynamic-content');
        if (!sidebarDynamic) {
            logError('SIDEBAR-DYN', 'Contenedor dinámico no encontrado');
            return;
        }

        let contenido = '';

        switch(pageId) {
            case 'page-vehiculo':
                contenido = `
                    <div style="background: rgba(255,255,255,0.1); padding: 18px; border-radius: 8px;">
                        <h3 style="color: white; font-size: 16px; margin: 0 0 16px 0; font-weight: 600; line-height: 1.3;">
                            Cambio Titularidad<br>Embarcación
                        </h3>
                        
                        
                        <!-- 3 puntos destacados -->
                        <div style="margin-bottom: 16px;">
                            <div style="display: flex; align-items: center; margin-bottom: 8px; padding: 8px; background: rgba(255,255,255,0.1); border-radius: 6px; border-left: 3px solid rgba(255,255,255,0.6);">
                                <span style="color: rgba(255,255,255,0.9); font-size: 12px;">Presentamos tu solicitud en menos de 24h desde que la recibimos</span>
                            </div>
                            <div style="display: flex; align-items: center; margin-bottom: 8px; padding: 8px; background: rgba(255,255,255,0.1); border-radius: 6px; border-left: 3px solid rgba(255,255,255,0.6);">
                                <span style="color: rgba(255,255,255,0.9); font-size: 12px;">Envío de provisional en menos de 24h</span>
                            </div>
                            <div style="display: flex; align-items: center; margin-bottom: 8px; padding: 8px; background: rgba(255,255,255,0.1); border-radius: 6px; border-left: 3px solid rgba(255,255,255,0.6);">
                                <span style="color: rgba(255,255,255,0.9); font-size: 12px;">Seguimiento estado del trámite en tiempo real</span>
                            </div>
                        </div>
                        
                        <!-- Cuadro de tiempo estimado (discreto) -->
                        <div style="background: rgba(255,255,255,0.03); padding: 8px; border-radius: 4px; text-align: center; margin-bottom: 12px; border: 1px solid rgba(255,255,255,0.05);">
                            <span style="color: rgba(255,255,255,0.6); font-size: 10px;">Proceso rápido • Solo 5 minutos</span>
                        </div>
                        
                        <!-- Cuadro de precio (compacto) -->
                        <div style="background: linear-gradient(135deg, rgba(255,255,255,0.15) 0%, rgba(255,255,255,0.08) 100%); border-radius: 6px; padding: 12px; text-align: center; border: 1px solid rgba(255,255,255,0.2);">
                            <div style="font-size: 10px; opacity: 0.7; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; color: rgba(255,255,255,0.7);">Desde</div>
                            <div style="font-size: 24px; font-weight: 700; margin: 2px 0; color: white;">134,99€</div>
                            <div style="font-size: 9px; opacity: 0.7; color: rgba(255,255,255,0.6);">IVA y tasas incluidos</div>
                        </div>
                    </div>
                `;
                break;

            case 'page-datos':
                contenido = `
                    <div style="background: rgba(255,255,255,0.1); padding: 18px; border-radius: 8px;">
                        <h3 style="color: white; font-size: 16px; margin: 0 0 16px 0; font-weight: 600; line-height: 1.3;">
                            Información Personal<br>y de Contacto
                        </h3>
                        
                        <div style="color: rgba(255,255,255,0.9); font-size: 13px; line-height: 1.6; margin-bottom: 16px;">
                            Necesitamos tus datos personales para generar la documentación oficial requerida por Capitanía Marítima.
                        </div>
                        
                        <div style="border-left: 3px solid rgba(255,255,255,0.3); padding-left: 12px; margin-bottom: 16px;">
                            <div style="color: rgba(255,255,255,0.8); font-size: 12px; line-height: 1.5;">
                                • Datos protegidos según RGPD<br>
                                • Uso exclusivo para el trámite<br>
                                • Comunicación vía email/teléfono<br>
                                • Documentos personalizados
                            </div>
                        </div>
                        
                        <div style="background: rgba(255,255,255,0.05); padding: 12px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.1); margin-bottom: 12px;">
                            <div style="color: rgba(255,255,255,0.7); font-size: 11px; text-align: center;">
                                Progreso: 2 de 5 pasos completados
                            </div>
                        </div>
                        
                        <div style="background: rgba(255,255,255,0.05); padding: 10px; border-radius: 6px;">
                            <div style="color: rgba(255,255,255,0.6); font-size: 11px; text-align: center;">
                                Siguiente: Configuración de precios y tasas
                            </div>
                        </div>
                    </div>
                `;
                break;

            case 'page-precio':
                contenido = `
                    <div style="background: transparent; padding: 14px;">
                        <h4 style="color: white; font-size: 14px; margin: 0 0 14px 0; font-weight: 600; border-bottom: 1px solid rgba(255,255,255,0.2); padding-bottom: 8px;">
                            Asistente de Cálculo
                        </h4>
                        <div id="sidebar-precio-content">
                            <!-- Se actualizará dinámicamente con actualizarSidebarPrecio() -->
                        </div>
                    </div>
                `;
                break;

            case 'page-documentos':
                
                // Verificar si estamos en paso 2 (firma) - detección mejorada
                const step2 = document.getElementById('documentos-step-2');
                const step1 = document.getElementById('documentos-step-1');
                const documentsCheckbox = document.getElementById('documents-complete-check');
                
                // Detección simplificada: checkbox marcado = modo firma
                const enPasoFirma = documentsCheckbox && documentsCheckbox.checked;
                
                console.log('DEBUG SIDEBAR DOCUMENTOS:', {
                    step2_exists: !!step2,
                    step2_display: step2?.style.display,
                    step2_hidden: step2?.classList.contains('hidden'),
                    enPasoFirma: enPasoFirma
                });

                if (enPasoFirma) {
                    // MODO FIRMA: Asegurar que SIEMPRE se muestre el documento
                    console.log('🎯 DETECTADO MODO FIRMA - Mostrando documento automáticamente');
                    
                    // Llamar función para mostrar documento (con delay para asegurar DOM ready)
                    setTimeout(() => {
                        mostrarDocumentoAutorizacionSidebar();
                    }, 100);
                    
                    // Contenido temporal mientras se carga el documento
                    contenido = `
                        <div style="background: rgba(255,255,255,0.1); padding: 16px; border-radius: 8px;">
                            <h4 style="color: white; font-size: 15px; margin: 0 0 12px 0; font-weight: 600;">
                                Vista de Firma
                            </h4>
                            <p style="color: rgba(255,255,255,0.8); font-size: 13px; margin: 0;">
                                Cargando documento de autorización...
                            </p>
                        </div>
                    `;
                } else {
                    // MODO NORMAL: Testigos de documentos subidos
                    // Restaurar sidebar normal
                    const sidebar = document.querySelector('.tramitfy-sidebar');
                    if (sidebar) {
                        sidebar.style.width = '';
                        sidebar.style.background = '';
                        sidebar.style.boxShadow = '';
                        sidebar.style.minWidth = '';
                    }

                    // Verificar estado de cada documento
                    const hojaAsientoFiles = document.getElementById('upload-hoja-asiento')?.files?.length || 0;
                    const dniCompradorFiles = document.getElementById('upload-dni-comprador')?.files?.length || 0;
                    const dniVendedorFiles = document.getElementById('upload-dni-vendedor')?.files?.length || 0;
                    const contratoFiles = document.getElementById('upload-contrato-compraventa')?.files?.length || 0;
                    const itpComprobanteFiles = document.getElementById('upload-itp-comprobante')?.files?.length || 0;
                    
                    // Verificar si el ITP ya fue pagado (necesita comprobante)
                    const necesitaComprobanteITP = (itpPagado === true);
                    
                    const documentsCheckbox = document.getElementById('documents-complete-check');
                    const todosConfirmados = documentsCheckbox && documentsCheckbox.checked;

                    contenido = `
                        <div style="background: rgba(255,255,255,0.1); padding: 16px; border-radius: 8px;">
                            <h4 style="color: white; font-size: 15px; margin: 0 0 12px 0; font-weight: 600;">
                                📋 Estado Documentos
                            </h4>`;

                    // Testigo 1: Hoja de asiento
                    const hojaEstado = hojaAsientoFiles > 0;
                    contenido += `
                            <div style="padding: 8px; background: rgba(255,255,255,0.05); border-radius: 6px; margin-bottom: 8px; border-left: 3px solid ${hojaEstado ? '#10b981' : '#ef4444'};">
                                <div style="display: flex; align-items: center; justify-content: space-between;">
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <i class="fa-solid ${hojaEstado ? 'fa-check-circle' : 'fa-circle-xmark'}" style="color: ${hojaEstado ? '#10b981' : '#ef4444'}; font-size: 12px;"></i>
                                        <span style="font-size: 12px; color: white;">Hoja de asiento</span>
                                    </div>
                                    <span style="font-size: 11px; color: ${hojaEstado ? '#10b981' : '#ef4444'}; font-weight: 600;">
                                        ${hojaEstado ? hojaAsientoFiles : '0'}
                                    </span>
                                </div>
                            </div>`;

                    // Testigo 2: DNI Comprador
                    const dniCompradorEstado = dniCompradorFiles > 0;
                    contenido += `
                            <div style="padding: 8px; background: rgba(255,255,255,0.05); border-radius: 6px; margin-bottom: 8px; border-left: 3px solid ${dniCompradorEstado ? '#10b981' : '#ef4444'};">
                                <div style="display: flex; align-items: center; justify-content: space-between;">
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <i class="fa-solid ${dniCompradorEstado ? 'fa-check-circle' : 'fa-circle-xmark'}" style="color: ${dniCompradorEstado ? '#10b981' : '#ef4444'}; font-size: 12px;"></i>
                                        <span style="font-size: 12px; color: white;">DNI Comprador</span>
                                    </div>
                                    <span style="font-size: 11px; color: ${dniCompradorEstado ? '#10b981' : '#ef4444'}; font-weight: 600;">
                                        ${dniCompradorEstado ? dniCompradorFiles : '0'}
                                    </span>
                                </div>
                            </div>`;

                    // Testigo 3: DNI Vendedor
                    const dniVendedorEstado = dniVendedorFiles > 0;
                    contenido += `
                            <div style="padding: 8px; background: rgba(255,255,255,0.05); border-radius: 6px; margin-bottom: 8px; border-left: 3px solid ${dniVendedorEstado ? '#10b981' : '#ef4444'};">
                                <div style="display: flex; align-items: center; justify-content: space-between;">
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <i class="fa-solid ${dniVendedorEstado ? 'fa-check-circle' : 'fa-circle-xmark'}" style="color: ${dniVendedorEstado ? '#10b981' : '#ef4444'}; font-size: 12px;"></i>
                                        <span style="font-size: 12px; color: white;">DNI Vendedor</span>
                                    </div>
                                    <span style="font-size: 11px; color: ${dniVendedorEstado ? '#10b981' : '#ef4444'}; font-weight: 600;">
                                        ${dniVendedorEstado ? dniVendedorFiles : '0'}
                                    </span>
                                </div>
                            </div>`;

                    // Testigo 4: Contrato compraventa
                    const contratoEstado = contratoFiles > 0;
                    contenido += `
                            <div style="padding: 8px; background: rgba(255,255,255,0.05); border-radius: 6px; margin-bottom: 8px; border-left: 3px solid ${contratoEstado ? '#10b981' : '#ef4444'};">
                                <div style="display: flex; align-items: center; justify-content: space-between;">
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <i class="fa-solid ${contratoEstado ? 'fa-check-circle' : 'fa-circle-xmark'}" style="color: ${contratoEstado ? '#10b981' : '#ef4444'}; font-size: 12px;"></i>
                                        <span style="font-size: 12px; color: white;">Contrato compraventa</span>
                                    </div>
                                    <span style="font-size: 11px; color: ${contratoEstado ? '#10b981' : '#ef4444'}; font-weight: 600;">
                                        ${contratoEstado ? contratoFiles : '0'}
                                    </span>
                                </div>
                            </div>`;

                    // Testigo 5: Comprobante ITP (solo si es necesario)
                    if (necesitaComprobanteITP) {
                        const itpEstado = itpComprobanteFiles > 0;
                        contenido += `
                            <div style="padding: 8px; background: rgba(255,255,255,0.05); border-radius: 6px; margin-bottom: 8px; border-left: 3px solid ${itpEstado ? '#10b981' : '#f59e0b'};">
                                <div style="display: flex; align-items: center; justify-content: space-between;">
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <i class="fa-solid ${itpEstado ? 'fa-check-circle' : 'fa-exclamation-triangle'}" style="color: ${itpEstado ? '#10b981' : '#f59e0b'}; font-size: 12px;"></i>
                                        <span style="font-size: 12px; color: white;">Comprobante ITP</span>
                                    </div>
                                    <span style="font-size: 11px; color: ${itpEstado ? '#10b981' : '#f59e0b'}; font-weight: 600;">
                                        ${itpEstado ? itpComprobanteFiles : '0'}
                                    </span>
                                </div>
                            </div>`;
                    }

                    // Estado general y confirmación
                    const documentosBasicosCompletos = hojaEstado && dniCompradorEstado && dniVendedorEstado && contratoEstado;
                    const todosDocumentosCompletos = documentosBasicosCompletos && (!necesitaComprobanteITP || itpComprobanteFiles > 0);
                    
                    contenido += `
                            <hr style="border: none; border-top: 1px solid rgba(255,255,255,0.2); margin: 12px 0;">
                            <div style="padding: 8px; background: rgba(255,255,255,0.05); border-radius: 6px; border-left: 3px solid ${todosConfirmados ? '#10b981' : '#6b7280'};">
                                <div style="display: flex; align-items: center; justify-content: space-between;">
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <i class="fa-solid ${todosConfirmados ? 'fa-check-circle' : 'fa-clock'}" style="color: ${todosConfirmados ? '#10b981' : '#6b7280'}; font-size: 12px;"></i>
                                        <span style="font-size: 12px; color: white; font-weight: 600;">Confirmación</span>
                                    </div>
                                    <span style="font-size: 11px; color: ${todosConfirmados ? '#10b981' : '#6b7280'}; font-weight: 600;">
                                        ${todosConfirmados ? '✓' : '○'}
                                    </span>
                                </div>
                            </div>
                            
                            <div style="font-size: 11px; color: rgba(255,255,255,0.6); margin-top: 12px; line-height: 1.5; text-align: center;">
                                ${!todosDocumentosCompletos ? '📤 Sube todos los documentos requeridos' : !todosConfirmados ? '✅ Confirma documentos para continuar' : '🎯 Documentación completa'}
                            </div>
                        </div>
                    `;
                }
                break;

            case 'page-firma':
                // Detectar si estamos en modo confirmación o firma
                const firmaStepSignature = document.getElementById('firma-step-signature');
                const firmaStepConfirmacion = document.getElementById('firma-step-confirmacion');
                const documentosConfirmadosCheck = document.getElementById('documentos-confirmados-firma');
                
                // Detección más robusta: priorizar estado del checkbox y visibility
                const enModoFirma = (documentosConfirmadosCheck && documentosConfirmadosCheck.checked) || 
                                   (firmaStepSignature && firmaStepSignature.style.display === 'block') ||
                                   (firmaStepSignature && getComputedStyle(firmaStepSignature).display !== 'none' && 
                                    firmaStepConfirmacion && getComputedStyle(firmaStepConfirmacion).display === 'none');
                
                console.log('🔍 Detección modo firma:', {
                    checkboxChecked: documentosConfirmadosCheck?.checked,
                    signatureDisplay: firmaStepSignature?.style.display,
                    enModoFirma: enModoFirma
                });
                
                const sidebar = document.querySelector('.tramitfy-sidebar');
                
                if (enModoFirma) {
                    // MODO FIRMA: Ensanchar sidebar y mostrar documento de autorización
                    if (sidebar) {
                        sidebar.style.width = '45%'; // Más ancho para la autorización
                        sidebar.style.minWidth = '500px'; // Mínimo para legibilidad
                        console.log('🔄 Sidebar ensanchado para modo firma');
                    }
                    // Continuar para mostrar documento de autorización
                } else {
                    // MODO CONFIRMACIÓN: Sidebar normal y mostrar estado
                    if (sidebar) {
                        sidebar.style.width = '';
                        sidebar.style.minWidth = '';
                        console.log('🔄 Sidebar normal para modo confirmación');
                    }
                    
                    // Mostrar estado de documentos confirmados
                    const documentosConfirmadosFirmaCheck = document.getElementById('documentos-confirmados-firma');
                    const documentosConfirmados = documentosConfirmadosFirmaCheck && documentosConfirmadosFirmaCheck.checked;
                    
                    contenido = `
                        <div style="background: rgba(255,255,255,0.1); padding: 16px; border-radius: 8px;">
                            <h4 style="color: white; font-size: 15px; margin: 0 0 12px 0; font-weight: 600;">
                                Confirmación de Documentos
                            </h4>
                            <div style="padding: 10px; background: rgba(255,255,255,0.05); border-radius: 6px; margin-bottom: 10px; border-left: 3px solid ${documentosConfirmados ? '#10b981' : '#f59e0b'};">
                                <div style="font-size: 12px; color: rgba(255,255,255,0.7); margin-bottom: 6px;">Estado</div>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <i class="fa-solid ${documentosConfirmados ? 'fa-check-circle' : 'fa-clock'}" style="color: ${documentosConfirmados ? '#10b981' : '#f59e0b'}; font-size: 14px;"></i>
                                    <span style="font-size: 13px; color: white; font-weight: 600;">${documentosConfirmados ? 'Documentos Confirmados' : 'Pendiente de Confirmar'}</span>
                                </div>
                            </div>
                            <div style="font-size: 11px; color: rgba(255,255,255,0.6); margin-top: 12px; line-height: 1.5;">
                                ${!documentosConfirmados ? 'Marca el checkbox para confirmar que has adjuntado toda la documentación necesaria.' : 'Puedes proceder a firmar el documento de autorización.'}
                            </div>
                        </div>
                    `;
                    break; // Solo break en modo confirmación
                }

                // Obtener datos del formulario para la solicitud
                const buyerNameFirma = document.getElementById('customer_name')?.value || '[Nombre del comprador]';
                const buyerDniFirma = document.getElementById('customer_dni')?.value || '[DNI del comprador]';
                const sellerNameFirma = document.getElementById('seller_name')?.value || '[Nombre del vendedor]';
                const sellerDniFirma = document.getElementById('seller_dni')?.value || '[DNI del vendedor]';
                const vehicleTypeFirma = document.getElementById('vehicle_type')?.value || 'embarcación';
                const registrationFirma = document.getElementById('registration')?.value || '[matrícula]';
                const manufacturerFirma = document.getElementById('manufacturer')?.value || document.getElementById('manual_manufacturer')?.value || '[fabricante]';
                const modelFirma = document.getElementById('model')?.value || document.getElementById('manual_model')?.value || '[modelo]';
                const todayFirma = new Date().toLocaleDateString('es-ES', { year: 'numeric', month: 'long', day: 'numeric' });

                contenido = `
                    <div style="background: rgba(255,255,255,0.15); margin: 0 -20px; padding: 25px 30px; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.1);">
                        <!-- Encabezado compacto -->
                        <div style="text-align: center; margin-bottom: 20px; border-bottom: 2px solid rgba(255,255,255,0.3); padding-bottom: 15px;">
                            <h3 style="margin: 0 0 5px 0; font-size: 16px; color: white; font-weight: 700; text-transform: uppercase; letter-spacing: 1px;">
                                Autorización de Tramitación
                            </h3>
                            <p style="margin: 0; font-size: 12px; color: rgba(255,255,255,0.8);">
                                Transferencia de Propiedad
                            </p>
                        </div>

                        <!-- Solicitud breve -->
                        <div style="font-size: 13px; color: white; line-height: 1.6; text-align: justify;">
                            <p style="margin: 0 0 12px 0;">
                                En <strong>${todayFirma}</strong>, yo, <strong>${buyerNameFirma}</strong> (DNI: <strong>${buyerDniFirma}</strong>),
                                autorizo a <strong style="color: #fbbf24;">TRAMITFY</strong> para gestionar ante Capitanía Marítima la transferencia
                                de mi ${vehicleTypeFirma} <strong>${manufacturerFirma} ${modelFirma}</strong> (${registrationFirma}).
                            </p>

                            <p style="margin: 12px 0;">
                                Esta autorización incluye presentar documentación, pagar tasas y retirar certificados
                                resultantes del trámite de transferencia desde <strong>${sellerNameFirma}</strong>.
                            </p>
                        </div>

                        <!-- Instrucciones firma -->
                        <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid rgba(255,255,255,0.2); text-align: center;">
                            <p style="margin: 0; font-size: 11px; color: rgba(255,255,255,0.7); line-height: 1.4;">
                                <i class="fa-solid fa-arrow-right" style="margin-right: 6px;"></i>
                                Firma en el panel de la derecha para autorizar el trámite
                            </p>
                        </div>
                    </div>
                `;
                break;

            case 'page-pago':
                console.log('🎯 [page-pago] FORZAR POSICIÓN CORRECTA');
                
                // FORZAR que page-pago esté en main-form donde debe estar
                const pagePagoEl = document.getElementById('page-pago');
                const mainFormEl = document.querySelector('.tramitfy-main-form');
                const navigationEl = document.getElementById('form-navigation');
                
                if (pagePagoEl && mainFormEl && navigationEl) {
                    console.log('🔧 [FIX] Forzando page-pago en main-form después de navegación');
                    // Asegurar que page-pago esté en main-form, después de la navegación
                    if (pagePagoEl.parentElement !== mainFormEl) {
                        mainFormEl.insertBefore(pagePagoEl, navigationEl.nextSibling);
                        console.log('✅ [FIX] page-pago movido a main-form');
                    } else {
                        console.log('✅ [OK] page-pago ya está en main-form');
                    }
                }
                
                const precioBase = gestionamosITP ? BASE_TRANSFER_PRICE_CON_ITP : BASE_TRANSFER_PRICE_SIN_ITP;
                const itpIncluido = (itpPagado === false && itpGestionSeleccionada === 'gestionan-ustedes') ? itpTotalAmount : 0;
                const totalEstimado = precioBase + itpIncluido;
                
                // Calcular pago inmediato vs transferencia bancaria
                const esPagoITPTransferencia = (gestionamosITP && itpMetodoPago === 'transferencia');
                
                // LÓGICA CORREGIDA:
                // - Si ITP por TRANSFERENCIA: pago inmediato = 174.99€ (solo tasas DGMM), ITP por transferencia después
                // - Si ITP por TARJETA: pago inmediato = totalEstimado (tasas DGMM + ITP), no hay transferencia
                // - Si NO ITP: pago inmediato = totalEstimado (solo tasas DGMM)
                const pagoInmediato = esPagoITPTransferencia ? 174.99 : totalEstimado;
                const pagoTransferencia = esPagoITPTransferencia ? itpIncluido : 0;

                // Construir información detallada según el caso
                let detallesPago = '';
                let informacionAdicional = '';
                
                if (esPagoITPTransferencia) {
                    // Caso: ITP por transferencia bancaria - pago fraccionado
                    detallesPago = `
                        <div style="background: rgba(255,255,255,0.08); padding: 10px; border-radius: 6px; margin-bottom: 8px;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                                <span style="color: rgba(255,255,255,0.9); font-size: 12px;">Pago inmediato:</span>
                                <strong style="color: white; font-size: 14px;">${pagoInmediato.toFixed(2)} €</strong>
                            </div>
                            <div style="font-size: 11px; color: rgba(255,255,255,0.7);">
                                (Tasas DGMM + gastos de gestión)
                            </div>
                        </div>
                        ${pagoTransferencia > 0 ? `
                        <div style="background: rgba(255,200,100,0.15); padding: 8px; border-radius: 6px; margin-bottom: 8px; border-left: 3px solid #ffc107;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                                <span style="color: rgba(255,255,255,0.9); font-size: 12px;">Transferencia ITP:</span>
                                <strong style="color: #ffc107; font-size: 14px;">${pagoTransferencia.toFixed(2)} €</strong>
                            </div>
                            <div style="font-size: 10px; color: rgba(255,255,255,0.7);">
                                Se informará por email
                            </div>
                        </div>` : ''}
                    `;
                    informacionAdicional = `
                        <div style="font-size: 11px; color: rgba(255,255,255,0.8); line-height: 1.4; margin-top: 8px;">
                            🏦 <strong>Transferencia bancaria ITP:</strong><br>
                            Recibirás datos bancarios por email<br>
                            📅 Plazo: 30 días hábiles
                        </div>
                    `;
                } else if (gestionamosITP && itpMetodoPago === 'tarjeta') {
                    // Caso: ITP con tarjeta - TODO EN UN PAGO
                    const baseAmount = 174.99;
                    const itpAmount = itpIncluido;
                    detallesPago = `
                        <div style="background: rgba(255,255,255,0.08); padding: 10px; border-radius: 6px; margin-bottom: 8px;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                                <span style="color: rgba(255,255,255,0.9); font-size: 12px;">Tasas DGMM + gestión:</span>
                                <span style="color: white; font-size: 13px;">${baseAmount.toFixed(2)} €</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                                <span style="color: rgba(255,255,255,0.9); font-size: 12px;">ITP + comisión:</span>
                                <span style="color: white; font-size: 13px;">${itpAmount.toFixed(2)} €</span>
                            </div>
                            <hr style="border: none; border-top: 1px solid rgba(255,255,255,0.2); margin: 8px 0;">
                            <div style="display: flex; justify-content: space-between;">
                                <span style="font-weight: 600; color: white; font-size: 14px;">TOTAL:</span>
                                <strong style="font-size: 16px; color: white;">${totalEstimado.toFixed(2)} €</strong>
                            </div>
                        </div>
                    `;
                    informacionAdicional = `
                        <div style="font-size: 11px; color: rgba(255,255,255,0.8); line-height: 1.4; margin-top: 8px;">
                            <strong>ITP con tarjeta:</strong><br>
                            Todo en un solo pago<br>
                            Tramitación completa
                        </div>
                    `;
                } else if (gestionamosITP) {
                    // Caso: ITP sin método especificado (fallback)
                    detallesPago = `
                        <div style="background: rgba(255,255,255,0.08); padding: 10px; border-radius: 6px; margin-bottom: 8px;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                                <span style="font-weight: 600; color: white; font-size: 13px;">TOTAL:</span>
                                <strong style="font-size: 16px; color: white;">${totalEstimado.toFixed(2)} €</strong>
                            </div>
                            <div style="font-size: 11px; color: rgba(255,255,255,0.7);">
                                Incluye ITP + tasas DGMM + gestión
                            </div>
                        </div>
                    `;
                    informacionAdicional = `
                        <div style="font-size: 11px; color: rgba(255,255,255,0.8); line-height: 1.4; margin-top: 8px;">
                            <strong>ITP incluido:</strong><br>
                            Nos encargamos de todo el proceso<br>
                            Tramitación completa
                        </div>
                    `;
                } else {
                    // Caso: Solo tasas DGMM (sin ITP)
                    detallesPago = `
                        <div style="background: rgba(255,255,255,0.08); padding: 10px; border-radius: 6px; margin-bottom: 8px;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                                <span style="font-weight: 600; color: white; font-size: 13px;">TOTAL:</span>
                                <strong style="font-size: 16px; color: white;">${totalEstimado.toFixed(2)} €</strong>
                            </div>
                            <div style="font-size: 11px; color: rgba(255,255,255,0.7);">
                                Tasas DGMM + gastos de gestión
                            </div>
                        </div>
                    `;
                    informacionAdicional = `
                        <div style="font-size: 11px; color: rgba(255,255,255,0.8); line-height: 1.4; margin-top: 8px;">
                            <strong>Sin ITP:</strong><br>
                            Ya abonado anteriormente<br>
                            Solo tramitación
                        </div>
                    `;
                }

                contenido = `
                    <div style="background: rgba(255,255,255,0.1); padding: 12px; border-radius: 8px;">
                        <h4 style="color: white; font-size: 14px; margin: 0 0 10px 0; font-weight: 600;">
                            Pago Seguro
                        </h4>
                        ${detallesPago}
                        <div style="font-size: 11px; color: rgba(255,255,255,0.8); line-height: 1.4;">
                            Pago 100% seguro con Stripe<br>
                            Confirmación por email
                        </div>
                        ${informacionAdicional}
                    </div>
                `;
                break;

            default:
                contenido = '';
        }

        sidebarDynamic.innerHTML = contenido;
        logDebug('SIDEBAR-DYN', '✅ Sidebar actualizado');

        // Añadir event listeners para navegación por click
        setTimeout(() => {
            document.querySelectorAll('.sidebar-field').forEach(field => {
                field.addEventListener('click', function() {
                    const fieldName = this.dataset.field;
                    const input = document.getElementById(fieldName);
                    if (input) {
                        input.focus();
                        // Resaltar temporalmente
                        input.style.transition = 'all 0.3s';
                        input.style.boxShadow = '0 0 0 3px rgba(16, 185, 129, 0.3)';
                        setTimeout(() => {
                            input.style.boxShadow = '';
                        }, 1500);
                    }
                });
                // Hover effect
                field.addEventListener('mouseenter', function() {
                    this.style.background = this.querySelector('[style*="font-weight: 600"]') ? 'rgba(16, 185, 129, 0.25)' : 'rgba(255,255,255,0.1)';
                });
                field.addEventListener('mouseleave', function() {
                    const hasValue = this.querySelector('[style*="font-weight: 600"]');
                    this.style.background = hasValue ? 'rgba(16, 185, 129, 0.15)' : 'rgba(255,255,255,0.05)';
                });
            });
        }, 100);

        // Si estamos en página de precio, actualizar contenido de precio
        if (pageId === 'page-precio') {
            actualizarSidebarPrecio();
        }
    }

    // ============================================
    // FIN SISTEMA DE SIDEBAR DINÁMICO
    // ============================================

    // Actualizar cálculos cuando cambien datos del vehículo
    // purchasePriceInput, matriculationDateInput, regionSelect ya están definidos arriba (líneas 5414-5416)

    logDebug('PRECIO-INIT', 'Inputs encontrados:', {
        purchasePriceInput: !!purchasePriceInput,
        matriculationDateInput: !!matriculationDateInput,
        regionSelect: !!regionSelect
    });

    if (purchasePriceInput) {
        purchasePriceInput.addEventListener('change', function() {
            logDebug('PRECIO-FLOW', '💰 Precio de compra cambiado:', this.value);
            actualizarCalculoITPStep1();
            actualizarSidebarDinamico('page-vehiculo');
            actualizarSidebarPrecio();
        });
        purchasePriceInput.addEventListener('input', function() {
            actualizarSidebarDinamico('page-vehiculo');
        });
        logDebug('PRECIO-INIT', '✅ Event listener añadido a precio compra');
    }
    if (matriculationDateInput) {
        matriculationDateInput.addEventListener('change', function() {
            logDebug('PRECIO-FLOW', '📅 Fecha matriculación cambiada:', this.value);
            actualizarCalculoITPStep1();
            actualizarSidebarDinamico('page-vehiculo');
            actualizarSidebarPrecio();
        });
        logDebug('PRECIO-INIT', '✅ Event listener añadido a fecha matriculación');
    }
    if (regionSelect) {
        regionSelect.addEventListener('change', function() {
            logDebug('PRECIO-FLOW', '🗺️ Región cambiada:', this.value);
            actualizarCalculoITPStep1();
            actualizarSidebarDinamico('page-vehiculo');
            actualizarSidebarPrecio();
        });
        logDebug('PRECIO-INIT', '✅ Event listener añadido a región');
    }

    // Event listeners para actualizar sidebar en tiempo real en todas las páginas
    ['vehicle_type', 'manufacturer', 'model', 'manual_manufacturer', 'manual_model', 'matriculation_date', 'purchase_price', 'customer_name', 'customer_dni', 'customer_email', 'customer_phone'].forEach(fieldId => {
        const input = document.getElementById(fieldId);
        if (input) {
            input.addEventListener('input', function() {
                const currentPageId = formPages[currentPage]?.id;
                if (currentPageId) {
                    actualizarSidebarDinamico(currentPageId);
                }
                // Si es precio o fecha, también actualizar cálculo ITP
                if (fieldId === 'purchase_price' || fieldId === 'matriculation_date') {
                    actualizarCalculoITPStep1();
                    actualizarSidebarPrecio();
                }
            });
            // También en change para selects y dates
            input.addEventListener('change', function() {
                const currentPageId = formPages[currentPage]?.id;
                if (currentPageId) {
                    actualizarSidebarDinamico(currentPageId);
                }
                // Si es precio o fecha, también actualizar cálculo ITP
                if (fieldId === 'purchase_price' || fieldId === 'matriculation_date') {
                    actualizarCalculoITPStep1();
                    actualizarSidebarPrecio();
                }
            });
        }
    });

    logDebug('PRECIO-INIT', '✅ Flujo de precio inicializado correctamente');

    // ============================================
    // FIN NUEVO FLUJO DE PÁGINA DE PRECIO
    // ============================================

    // ============================================
    // FUNCIÓN PARA LLENAR DOCUMENTO DE AUTORIZACIÓN
    // ============================================
    function llenarDocumentoAutorizacion() {
        const documentBody = document.getElementById('document-body');
        const documentBodySidebar = document.getElementById('document-body-sidebar');
        const signatureInfo = document.getElementById('signature-info');

        if (!documentBody && !documentBodySidebar) return;

        // Obtener datos del formulario
        const buyerName = document.getElementById('customer_name')?.value || '[Nombre del comprador]';
        const buyerDni = document.getElementById('customer_dni')?.value || '[DNI del comprador]';
        const sellerName = document.getElementById('seller_name')?.value || '[Nombre del vendedor]';
        const sellerDni = document.getElementById('seller_dni')?.value || '[DNI del vendedor]';
        const vehicleType = document.getElementById('vehicle_type')?.value || 'embarcación';
        const registration = document.getElementById('registration')?.value || '[matrícula]';
        const manufacturer = document.getElementById('manufacturer')?.value || document.getElementById('manual_manufacturer')?.value || '[fabricante]';
        const model = document.getElementById('model')?.value || document.getElementById('manual_model')?.value || '[modelo]';
        const today = new Date().toLocaleDateString('es-ES', { year: 'numeric', month: 'long', day: 'numeric' });

        // Contenido del documento
        const documentContent = `
            <p style="margin: 0 0 20px 0; text-indent: 30px;">
                En <strong>${today}</strong>, yo, <strong>${buyerName}</strong>, con DNI/NIE número <strong>${buyerDni}</strong>,
                en mi calidad de comprador, autorizo expresamente a <strong>TRAMITFY</strong> para que actúe en mi nombre y
                representación en todos los trámites necesarios ante Capitanía Marítima para la transferencia de titularidad
                de la ${vehicleType} con matrícula <strong>${registration}</strong>, marca <strong>${manufacturer}</strong>,
                modelo <strong>${model}</strong>.
            </p>

            <p style="margin: 20px 0; text-indent: 30px;">
                Esta autorización incluye expresamente la facultad para presentar toda la documentación requerida, realizar
                el pago de tasas administrativas en mi nombre, firmar los documentos oficiales necesarios, y retirar los
                certificados y documentación oficial resultante de la tramitación.
            </p>

            <p style="margin: 20px 0; text-indent: 30px;">
                Declaro bajo mi responsabilidad que todos los datos facilitados en el presente documento son veraces y
                completos, y que la documentación aportada es auténtica y válida para los fines del presente trámite de
                transferencia de titularidad.
            </p>

            <p style="margin: 20px 0; text-indent: 30px;">
                Asimismo, manifiesto mi conformidad con el tratamiento de mis datos personales por parte de TRAMITFY
                exclusivamente para la gestión del presente trámite administrativo, de acuerdo con la normativa vigente
                en materia de protección de datos.
            </p>
        `;

        // Llenar ambos contenedores (original y sidebar)
        if (documentBody) {
            documentBody.innerHTML = documentContent;
        }
        if (documentBodySidebar) {
            documentBodySidebar.innerHTML = documentContent;
        }

        // Ocultar el sidebar cuando estamos en modo firma
        const sidebar = document.querySelector('.tramitfy-sidebar');
        if (sidebar) {
            console.log('🔒 [llenarDocumentoAutorizacion] Ocultando sidebar (display: none)');
            sidebar.style.display = 'none';
        } else {
            console.log('❌ [llenarDocumentoAutorizacion] No se encontró sidebar');
        }

        // Hacer que el formulario principal ocupe todo el ancho
        const mainForm = document.querySelector('.tramitfy-main-form');
        if (mainForm) {
            console.log('📏 [llenarDocumentoAutorizacion] Expandiendo formulario a ancho completo');
            mainForm.style.gridColumn = '1 / -1';
        } else {
            console.log('❌ [llenarDocumentoAutorizacion] No se encontró mainForm');
        }
    }

    function restaurarLayoutNormal() {
        // Verificar estado antes de restaurar
        const step2 = document.getElementById('documentos-step-2');
        const step2Display = step2 ? step2.style.display : 'no encontrado';
        console.log('🔄 [restaurarLayoutNormal] Iniciando restauración...');
        console.log('   Estado documentos-step-2:', step2Display);

        // Mostrar y restaurar el sidebar
        const sidebar = document.querySelector('.tramitfy-sidebar');
        if (sidebar) {
            const beforeDisplay = sidebar.style.display;
            sidebar.style.display = 'flex';
            // Restaurar estilos normales del sidebar
            sidebar.style.width = '';
            sidebar.style.background = '';
            sidebar.style.boxShadow = '';
            sidebar.style.minWidth = '';
            console.log(`✅ Sidebar restaurado: ${beforeDisplay} → flex`);
        } else {
            console.log('❌ No se encontró el sidebar');
        }

        // Restaurar el grid del formulario
        const mainForm = document.querySelector('.tramitfy-main-form');
        if (mainForm) {
            const beforeGrid = mainForm.style.gridColumn;
            mainForm.style.gridColumn = '';
            console.log(`✅ Grid restaurado: "${beforeGrid}" → ""`);
        } else {
            console.log('❌ No se encontró el mainForm');
        }
    }

    // ============================================
    // FLUJO DE PÁGINA DE DOCUMENTOS (2 PASOS)
    // ============================================
    
    // Función para mostrar paso 2 con firma integrada elegante
    
    const documentsCheckbox = document.getElementById('documents-complete-check');

    if (documentsCheckbox) {
        documentsCheckbox.addEventListener('change', function() {
            const signatureSection = document.getElementById('simple-signature-section');
            const uploadsSection = document.querySelector('.upload-grid');
            const docsConfirmation = document.querySelector('.docs-confirmation-container');
            
            if (this.checked) {
                // Ocultar campos de adjuntar y checkbox con animación
                if (uploadsSection) {
                    uploadsSection.style.opacity = '0';
                    uploadsSection.style.transform = 'translateY(-10px)';
                    setTimeout(() => {
                        uploadsSection.style.display = 'none';
                    }, 300);
                }
                
                if (docsConfirmation) {
                    docsConfirmation.style.opacity = '0';
                    docsConfirmation.style.transform = 'translateY(-10px)';
                    setTimeout(() => {
                        docsConfirmation.style.display = 'none';
                    }, 300);
                }
                
                // Mostrar sección de firma con animación (después de ocultar lo anterior)
                setTimeout(() => {
                    if (signatureSection) {
                        signatureSection.style.display = 'block';
                        setTimeout(() => {
                            signatureSection.style.opacity = '1';
                            signatureSection.style.transform = 'translateY(0)';
                            
                            // Inicializar SignaturePad simple
                            initializeSimpleSignaturePad();
                            
                            // Actualizar sidebar para mostrar documento de autorización
                            mostrarDocumentoAutorizacionSidebar();
                        }, 10);
                    }
                }, 350);
                
            } else {
                // Ocultar sección de firma
                if (signatureSection) {
                    signatureSection.style.opacity = '0';
                    signatureSection.style.transform = 'translateY(-10px)';
                    setTimeout(() => {
                        signatureSection.style.display = 'none';
                    }, 300);
                }
                
                // Mostrar campos de adjuntar y checkbox con animación
                setTimeout(() => {
                    if (uploadsSection) {
                        uploadsSection.style.display = 'grid';
                        setTimeout(() => {
                            uploadsSection.style.opacity = '1';
                            uploadsSection.style.transform = 'translateY(0)';
                        }, 10);
                    }
                    
                    if (docsConfirmation) {
                        docsConfirmation.style.display = 'block';
                        setTimeout(() => {
                            docsConfirmation.style.opacity = '1';
                            docsConfirmation.style.transform = 'translateY(0)';
                        }, 10);
                    }
                    
                    // IMPORTANTE: Limpiar completamente el documento de autorización
                    limpiarDocumentoAutorizacion();
                    
                    // Restaurar sidebar normal usando el sistema dinámico
                    actualizarSidebarDinamico('page-documentos');
                }, 350);
            }
        });

        logDebug('DOCS', '✅ Event listener del checkbox de documentos configurado');
    }
    
    // Event listeners para botones de firma ya definidos arriba (líneas 10070-10100)
    // Eliminados para evitar duplicación
    
    // ============================================
    // FIN FLUJO DE PÁGINA DE DOCUMENTOS
    // ============================================

    }); // FIN document.addEventListener('DOMContentLoaded')
    </script>
    <?php
    return ob_get_clean();
}

/**
 * Registrar el shortcode [transferencia_propiedad_form]
 */
add_shortcode('transferencia_barco_form', 'transferencia_barco_shortcode');

/**
 * ENDPOINTS Y ACCIONES AJAX
 */

/**
 * 1. CREATE PAYMENT INTENT
 */
add_action('wp_ajax_barco_create_payment_intent', 'tpb_create_payment_intent');
add_action('wp_ajax_nopriv_barco_create_payment_intent', 'tpb_create_payment_intent');
function tpb_create_payment_intent() {
    // RE-EVALUAR las claves aquí para evitar cache (igual que hoja-asiento.php)
    if (BARCO_STRIPE_MODE === 'test') {
        $stripe_secret_key = BARCO_STRIPE_TEST_SECRET_KEY;
    } else {
        $stripe_secret_key = BARCO_STRIPE_LIVE_SECRET_KEY;
    }

    // Asegurarse de que la respuesta es JSON
    header('Content-Type: application/json');

    // Comprobar si existe la biblioteca de Stripe
    $stripe_path = get_template_directory() . '/vendor/autoload.php';

    if (!file_exists($stripe_path)) {
        echo json_encode([
            'error' => 'La biblioteca de Stripe no está instalada correctamente. Por favor, contacta con el administrador.'
        ]);
        wp_die();
    }

    try {
        error_log('=== TRANSFERENCIA BARCO PAYMENT INTENT ===');
        error_log('STRIPE MODE: ' . BARCO_STRIPE_MODE);
        error_log('TEST SECRET CONSTANT: ' . substr(BARCO_STRIPE_TEST_SECRET_KEY, 0, 25) . '...' . substr(BARCO_STRIPE_TEST_SECRET_KEY, -10));
        error_log('LIVE SECRET CONSTANT: ' . substr(BARCO_STRIPE_LIVE_SECRET_KEY, 0, 25) . '...' . substr(BARCO_STRIPE_LIVE_SECRET_KEY, -10));
        error_log('Selected key variable: ' . substr($stripe_secret_key, 0, 25) . '...' . substr($stripe_secret_key, -10));
        error_log('Key length: ' . strlen($stripe_secret_key));

        require_once $stripe_path;

        // Configurar Stripe con la clave
        \Stripe\Stripe::setApiKey($stripe_secret_key);

        $currentKey = \Stripe\Stripe::getApiKey();
        error_log('Stripe API Key confirmed: ' . substr($currentKey, 0, 25));

        $amount = isset($_POST['amount']) ? intval($_POST['amount']) : 0;
        
        if ($amount <= 0) {
            echo json_encode([
                'error' => 'El monto del pago es inválido'
            ]);
            wp_die();
        }
        
        $paymentIntent = \Stripe\PaymentIntent::create([
            'amount' => $amount,
            'currency' => 'eur',
            'automatic_payment_methods' => [
                'enabled' => true,
            ],
            'description' => 'Transferencia de Embarcación',
            'metadata' => [
                'source' => 'tramitfy_web',
                'form' => 'transferencia_moto',
                'mode' => BARCO_STRIPE_MODE
            ]
        ]);

        error_log('Payment Intent created: ' . $paymentIntent->id);

        echo json_encode([
            'clientSecret' => $paymentIntent->client_secret,
            'debug' => [
                'mode' => BARCO_STRIPE_MODE,
                'keyUsed' => substr($stripe_secret_key, 0, 25) . '...',
                'keyConfirmed' => substr($currentKey, 0, 25) . '...',
                'paymentIntentId' => $paymentIntent->id
            ]
        ]);
    } catch (\Exception $e) {
        error_log('STRIPE ERROR: ' . $e->getMessage());
        error_log('ERROR TRACE: ' . $e->getTraceAsString());
        echo json_encode([
            'error' => $e->getMessage(),
            'debug' => [
                'mode' => BARCO_STRIPE_MODE,
                'keyUsed' => substr($stripe_secret_key, 0, 25) . '...' . substr($stripe_secret_key, -10),
                'keyLength' => strlen($stripe_secret_key)
            ]
        ]);
    }
    wp_die();
}

/**
 * 2. VALIDAR CUPÓN DE DESCUENTO
 */
add_action('wp_ajax_tpb_validate_coupon', 'tpb_validate_coupon_code');

/**
 * Sistema de logging persistente para debug
 */
add_action('wp_ajax_tpb_log_debug', 'tpb_log_debug');
add_action('wp_ajax_nopriv_tpb_log_debug', 'tpb_log_debug');
function tpb_log_debug() {
    $message = sanitize_text_field($_POST['message'] ?? '');
    $type = sanitize_text_field($_POST['type'] ?? 'info');
    $timestamp = sanitize_text_field($_POST['timestamp'] ?? '');
    
    // Escribir al archivo de debug específico
    $log_file = '/tmp/tramitfy-form-debug.log';
    $log_entry = "[{$timestamp}] [{$type}] {$message}\n";
    $result = file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    
    // También al error_log por backup con resultado
    error_log("TRAMITFY DEBUG [{$timestamp}] [{$type}] {$message} | FILE_WRITE_RESULT: " . ($result !== false ? 'SUCCESS' : 'FAILED'));
    
    wp_send_json_success('Log guardado - Result: ' . ($result !== false ? 'SUCCESS' : 'FAILED'));
}

/**
 * Procesamiento manual de pago (para situaciones donde Stripe API falla)
 */
add_action('wp_ajax_process_payment_manual', 'tpb_process_payment_manual');
add_action('wp_ajax_nopriv_process_payment_manual', 'tpb_process_payment_manual');
function tpb_process_payment_manual() {
    // Verificar datos
    $purchase_details = isset($_POST['purchase_details']) ? json_decode(stripslashes($_POST['purchase_details']), true) : [];
    
    if (empty($purchase_details)) {
        wp_send_json_error('Datos de compra incorrectos o vacíos');
        wp_die();
    }
    
    // Guardar la solicitud en la base de datos o enviar notificaciones
    // Este es un enfoque alternativo cuando el API de Stripe no funciona
    // Email del administrador para recibir notificaciones
    $admin_email = 'ipmgroup24@gmail.com';
    $customer_email = sanitize_email($purchase_details['customerEmail']);
    
    // Crear el mensaje
    $subject = 'Nueva solicitud de transferencia de propiedad (Procesamiento manual)';
    $message = "Se ha recibido una nueva solicitud de transferencia de propiedad que requiere procesamiento manual:\n\n";
    $message .= "Nombre: " . sanitize_text_field($purchase_details['customerName']) . "\n";
    $message .= "Email: " . $customer_email . "\n";
    $message .= "Teléfono: " . sanitize_text_field($purchase_details['customerPhone']) . "\n";
    $message .= "DNI: " . sanitize_text_field($purchase_details['customerDNI']) . "\n\n";
    $message .= "Detalles del vehículo:\n";
    $message .= "Tipo: " . sanitize_text_field($purchase_details['vehicle']['type']) . "\n";
    $message .= "Fabricante: " . sanitize_text_field($purchase_details['vehicle']['manufacturer']) . "\n";
    $message .= "Modelo: " . sanitize_text_field($purchase_details['vehicle']['model']) . "\n";
    $message .= "Fecha Matriculación: " . sanitize_text_field($purchase_details['vehicle']['matriculationDate']) . "\n";
    $message .= "Precio Compra: " . sanitize_text_field($purchase_details['vehicle']['purchasePrice']) . "\n";
    $message .= "Comunidad Autónoma: " . sanitize_text_field($purchase_details['vehicle']['region']) . "\n\n";
    $message .= "Total a pagar: " . sanitize_text_field($purchase_details['totalAmount']) . " €\n";
    $message .= "ITP: " . sanitize_text_field($purchase_details['transferTax']) . " €\n\n";
    $message .= "ATENCIÓN: Esta solicitud fue procesada mediante el método alternativo debido a que el API de Stripe no estaba disponible.";
    
    // Enviar email al administrador
    wp_mail($admin_email, $subject, $message);
    
    // Enviar confirmación al cliente
    $subject_customer = 'Su solicitud de transferencia de propiedad está en proceso - TramitFy';
    $message_customer = "Estimado/a " . sanitize_text_field($purchase_details['customerName']) . ",\n\n";
    $message_customer .= "Hemos recibido su solicitud de transferencia de propiedad. Un miembro de nuestro equipo se pondrá en contacto con usted en breve para completar el proceso de pago y continuar con el trámite.\n\n";
    $message_customer .= "Detalle de su solicitud:\n";
    $message_customer .= "Vehículo: " . sanitize_text_field($purchase_details['vehicle']['manufacturer']) . " " . sanitize_text_field($purchase_details['vehicle']['model']) . "\n";
    $message_customer .= "Importe total: " . sanitize_text_field($purchase_details['totalAmount']) . " €\n\n";
    $message_customer .= "Gracias por confiar en TramitFy.\n\n";
    $message_customer .= "Atentamente,\nEquipo TramitFy";
    
    // EMAIL ELIMINADO - se envía solo con tracking
    // wp_mail($customer_email, $subject_customer, $message_customer);
    
    // Devolver éxito
    wp_send_json_success('Solicitud procesada correctamente');
    wp_die();
}
add_action('wp_ajax_nopriv_tpb_validate_coupon', 'tpb_validate_coupon_code');
function tpb_validate_coupon_code() {
    $raw_coupon = isset($_POST['coupon']) ? sanitize_text_field($_POST['coupon']) : '';
    $coupon_clean = strtoupper(preg_replace('/\s+/', '', $raw_coupon));

    // Ejemplo de cupones
    $valid_coupons = [
        'DESCUENTO10' => 10,
        'DESCUENTO20' => 20,
        'VERANO15' => 15,
        'BLACK50' => 50,
        'SINTOSOSIO' => 80,
    ];

    if (isset($valid_coupons[$coupon_clean])) {
        $discount_percent = $valid_coupons[$coupon_clean];
        wp_send_json_success(['discount_percent' => $discount_percent]);
    } else {
        wp_send_json_error('Cupón inválido o expirado');
    }
    wp_die();
}

/**
 * 3. ENVÍO DE CORREOS (DESHABILITADO - Ahora usa tpb_send_emails_v2)
 */
// add_action('wp_ajax_send_emails', 'tpb_send_emails');
// add_action('wp_ajax_nopriv_send_emails', 'tpb_send_emails');
/*
function tpb_send_emails() {
    // Datos que llegan por POST
    $customer_email = sanitize_email($_POST['customer_email']);
    $customer_name = sanitize_text_field($_POST['customer_name']);
    $customer_dni = sanitize_text_field($_POST['customer_dni']);
    $customer_phone = sanitize_text_field($_POST['customer_phone']);
    $payment_amount = sanitize_text_field($_POST['payment_amount']);
    $nuevo_nombre = sanitize_text_field($_POST['nuevo_nombre']);
    $nuevo_puerto = sanitize_text_field($_POST['nuevo_puerto']);
    $coupon_used = sanitize_text_field($_POST['coupon_used']);
    $opciones_extras = isset($_POST['service_details']) ? explode(', ', sanitize_text_field($_POST['service_details'])) : [];
    $tramite_id = isset($_POST['tramite_id']) ? sanitize_text_field($_POST['tramite_id']) : '';
    
    // Si no se pasó un ID de trámite, generamos uno nuevo (esto es una solución temporal)
    if (empty($tramite_id)) {
        $prefix = 'TMA-TRANS';
        $counter_option = 'tma_trans_counter';
        $current_cnt = get_option($counter_option, 0);
        $current_cnt++;
        update_option($counter_option, $current_cnt);
        $date_part = date('Ymd');
        $secuencial = str_pad($current_cnt, 6, '0', STR_PAD_LEFT);
        $tramite_id = $prefix . '-' . $date_part . '-' . $secuencial;
    }

    // Cabeceras del email
    $headers = [
        'Content-Type: text/html; charset=UTF-8',
        'From: Tramitfy <info@tramitfy.es>'
    ];

    // Asunto del correo al cliente
    $subject_customer = '¡Confirmación de su trámite de transferencia! - Tramitfy';

    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Confirmación de trámite - Tramitfy</title>
    </head>
    <body style="margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f5f7fa; -webkit-font-smoothing: antialiased; font-size: 16px; line-height: 1.6; color: #333;">
        <div style="max-width: 650px; margin: 0 auto; background-color: #ffffff; box-shadow: 0 4px 16px rgba(0,0,0,0.1); border-radius: 12px; overflow: hidden;">
            <!-- Cabecera con degradado -->
            <div style="background: linear-gradient(135deg, #016d86 0%, #01546a 100%); padding: 40px 30px 30px; text-align: center; color: white;">
                <img src="https://www.tramitfy.es/wp-content/uploads/LOGO.png" alt="Tramitfy Logo" style="max-width: 180px; height: auto; margin-bottom: 20px;">
                <h1 style="margin: 0; font-size: 26px; font-weight: 600; text-shadow: 0 1px 2px rgba(0,0,0,0.2);">¡Trámite Recibido con Éxito!</h1>
                <p style="margin: 10px 0 0; font-size: 18px; opacity: 0.9;">Su transferencia de propiedad está en proceso</p>
            </div>
            
            <!-- Destacado para el número de trámite -->
            <div style="background-color: #e8f4f8; padding: 15px 30px; border-bottom: 1px solid #d9edf7; text-align: center;">
                <p style="margin: 0; color: #016d86; font-size: 16px; font-weight: 500;">
                    <span style="display: block; font-weight: 600; font-size: 14px; color: #555; margin-bottom: 4px;">IDENTIFICADOR DE TRÁMITE</span>
                    <span style="display: inline-block; background-color: #016d86; color: white; padding: 6px 15px; border-radius: 4px; font-weight: 600; letter-spacing: 1px;"><?php echo esc_html($tramite_id); ?></span>
                </p>
                <p style="margin: 10px 0 0; font-size: 14px; color: #555;">Por favor, conserve este número para cualquier consulta</p>
            </div>

            <!-- Contenido principal -->
            <div style="padding: 30px; color: #333;">
                <p style="margin-bottom: 20px;">Estimado/a <strong><?php echo esc_html($customer_name); ?></strong>,</p>
                
                <p style="margin-bottom: 25px;">Nos complace confirmarle que hemos recibido correctamente toda la documentación e información necesaria para su trámite de transferencia de propiedad. Nuestro equipo de profesionales ya está trabajando en su caso para asegurar que todo el proceso se realice de manera eficiente y sin contratiempos.</p>
                
                <!-- Resumen del trámite en cuadro -->
                <div style="background-color: #f7f9fa; border: 1px solid #e0e5e9; border-radius: 10px; padding: 25px; margin-bottom: 30px;">
                    <h3 style="margin-top: 0; margin-bottom: 20px; color: #016d86; border-bottom: 1px solid #e0e5e9; padding-bottom: 10px; font-size: 18px;">
                        &#128196; Resumen de su Trámite
                    </h3>
                    
                    <table style="width: 100%; border-collapse: collapse; font-size: 15px;">
                        <tr>
                            <td style="padding: 8px 10px 8px 0; width: 45%; vertical-align: top; color: #555; font-weight: 500;">Nombre completo:</td>
                            <td style="padding: 8px 0; vertical-align: top; font-weight: 600;"><?php echo esc_html($customer_name); ?></td>
                        </tr>
                        <tr style="background-color: #f0f4f7;">
                            <td style="padding: 8px 10px 8px 0; width: 45%; vertical-align: top; color: #555; font-weight: 500;">DNI:</td>
                            <td style="padding: 8px 0; vertical-align: top; font-weight: 600;"><?php echo esc_html($customer_dni); ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 10px 8px 0; width: 45%; vertical-align: top; color: #555; font-weight: 500;">Teléfono:</td>
                            <td style="padding: 8px 0; vertical-align: top; font-weight: 600;"><?php echo esc_html($customer_phone); ?></td>
                        </tr>
                        <tr style="background-color: #f0f4f7;">
                            <td style="padding: 8px 10px 8px 0; width: 45%; vertical-align: top; color: #555; font-weight: 500;">Correo electrónico:</td>
                            <td style="padding: 8px 0; vertical-align: top; font-weight: 600;"><?php echo esc_html($customer_email); ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 10px 8px 0; width: 45%; vertical-align: top; color: #555; font-weight: 500;">Importe abonado:</td>
                            <td style="padding: 8px 0; vertical-align: top; font-weight: 600;"><?php echo esc_html($payment_amount); ?> €</td>
                        </tr>

                        <?php if (!empty($opciones_extras)): ?>
                        <tr style="background-color: #f0f4f7;">
                            <td style="padding: 8px 10px 8px 0; width: 45%; vertical-align: top; color: #555; font-weight: 500;">Opciones adicionales:</td>
                            <td style="padding: 8px 0; vertical-align: top; font-weight: 600;"><?php echo esc_html(implode(', ', $opciones_extras)); ?></td>
                        </tr>
                        <?php endif; ?>

                        <?php if (!empty($coupon_used)): ?>
                        <tr<?php echo empty($opciones_extras) ? ' style="background-color: #f0f4f7;"' : ''; ?>>
                            <td style="padding: 8px 10px 8px 0; width: 45%; vertical-align: top; color: #555; font-weight: 500;">Cupón aplicado:</td>
                            <td style="padding: 8px 0; vertical-align: top; font-weight: 600;"><?php echo esc_html($coupon_used); ?></td>
                        </tr>
                        <?php endif; ?>

                        <?php if (!empty($nuevo_nombre)): ?>
                        <tr<?php echo (empty($opciones_extras) && empty($coupon_used)) || (!empty($opciones_extras) && !empty($coupon_used)) ? ' style="background-color: #f0f4f7;"' : ''; ?>>
                            <td style="padding: 8px 10px 8px 0; width: 45%; vertical-align: top; color: #555; font-weight: 500;">Nuevo nombre embarcación:</td>
                            <td style="padding: 8px 0; vertical-align: top; font-weight: 600;"><?php echo esc_html($nuevo_nombre); ?></td>
                        </tr>
                        <?php endif; ?>

                        <?php if (!empty($nuevo_puerto)): ?>
                        <tr<?php 
                        $row_count = 0;
                        if (!empty($opciones_extras)) $row_count++;
                        if (!empty($coupon_used)) $row_count++;
                        if (!empty($nuevo_nombre)) $row_count++;
                        echo ($row_count % 2 == 0) ? ' style="background-color: #f0f4f7;"' : '';
                        ?>>
                            <td style="padding: 8px 10px 8px 0; width: 45%; vertical-align: top; color: #555; font-weight: 500;">Nuevo puerto base:</td>
                            <td style="padding: 8px 0; vertical-align: top; font-weight: 600;"><?php echo esc_html($nuevo_puerto); ?></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
                
                <!-- Información adicional -->
                <div style="margin-bottom: 30px; padding: 20px; border-left: 4px solid #016d86; background-color: #f2f7fa;">
                    <h4 style="margin-top: 0; color: #016d86; font-size: 16px; margin-bottom: 10px;">&#9989; ¿Qué sucede ahora?</h4>
                    <p style="margin: 0 0 10px; color: #444; font-size: 15px;">Nuestro equipo procesará su solicitud siguiendo estos pasos:</p>
                    <ol style="margin: 0; padding-left: 20px; color: #444; font-size: 15px;">
                        <li style="margin-bottom: 6px;">Verificación de toda la documentación recibida</li>
                        <li style="margin-bottom: 6px;">Preparación y presentación de los trámites ante las autoridades competentes</li>
                        <li style="margin-bottom: 6px;">Gestión del proceso administrativo completo</li>
                        <li style="margin-bottom: 0;">Notificación a usted una vez completado el trámite</li>
                    </ol>
                </div>

                <p>Si fuera necesaria alguna información adicional o se presentara cualquier imprevisto, nos pondremos en contacto con usted a la mayor brevedad para garantizar que el trámite se complete de manera eficaz.</p>
                
                <p>Le agradecemos sinceramente su confianza en <strong>Tramitfy</strong>. Estamos a su disposición para resolver cualquier duda o ampliar la información que precise.</p>
                
                <div style="margin-top: 30px; padding-top: 15px; border-top: 1px solid #eaeaea;">
                    <p style="margin: 0;">Atentamente,</p>
                    <p style="margin: 5px 0 0; font-weight: 600; color: #016d86;">El Equipo de Tramitfy</p>
                </div>
            </div>
            
            <!-- Pie de página -->
            <div style="background-color: #016d86; color: white; padding: 25px 30px; font-size: 14px; text-align: center;">
                <p style="margin: 0 0 15px; font-weight: 600; font-size: 16px;">Tramitfy S.L.</p>
                
                <table style="width: 100%; max-width: 400px; margin: 0 auto; text-align: center; color: white;">
                    <tr>
                        <td style="padding: 5px; width: 50%;">
                            <a href="mailto:info@tramitfy.es" style="color: white; text-decoration: none;">
                                <span style="display: block;">&#9993; Email</span>
                                <span style="display: block; font-weight: 600;">info@tramitfy.es</span>
                            </a>
                        </td>
                        <td style="padding: 5px; width: 50%;">
                            <a href="tel:+34689170273" style="color: white; text-decoration: none;">
                                <span style="display: block;">&#128222; Teléfono</span>
                                <span style="display: block; font-weight: 600;">+34 689 170 273</span>
                            </a>
                        </td>
                    </tr>
                </table>
                
                <p style="margin: 15px 0 0; opacity: 0.9; font-size: 13px;">Paseo Castellana 194 puerta B, Madrid, España</p>
                <p style="margin: 5px 0 0;">
                    <a href="https://www.tramitfy.es" style="color: white; text-decoration: underline;">www.tramitfy.es</a>
                </p>
            </div>
        </div>
    </body>
    </html>
    <?php
    $message_customer = ob_get_clean();

    // EMAIL ELIMINADO - se envía solo con tracking
    // wp_mail($customer_email, $subject_customer, $message_customer, $headers);

    wp_send_json_success('Correo al cliente enviado.');
    wp_die();
}
*/

/**
 * 3A. GENERAR ID DE TRÁMITE
 */
add_action('wp_ajax_tpb_generate_tramite_id', 'tpb_generate_tramite_id');
add_action('wp_ajax_nopriv_tpb_generate_tramite_id', 'tpb_generate_tramite_id');

function tpb_generate_tramite_id() {
    error_log('=== TPM GENERAR TRAMITE ID ===');

    $prefix = 'TMA-TRANS';
    $counter_option = 'tma_trans_counter';
    $current_cnt = get_option($counter_option, 0);
    $current_cnt++;
    update_option($counter_option, $current_cnt);
    $date_part = date('Ymd');
    $secuencial = str_pad($current_cnt, 6, '0', STR_PAD_LEFT);
    $tramite_id = $prefix . '-' . $date_part . '-' . $secuencial;

    error_log('Tramite ID generado: ' . $tramite_id);

    wp_send_json_success([
        'tramite_id' => $tramite_id
    ]);
}

/**
 * 3B. ENVÍO DE CORREOS MEJORADO (con email admin detallado)
 */
add_action('wp_ajax_tpb_send_emails', 'tpb_send_emails_v2');
add_action('wp_ajax_nopriv_tpb_send_emails', 'tpb_send_emails_v2');

function tpb_send_emails_v2() {
    error_log('=== TPM SEND EMAILS V2 INICIADO ===');

    // Recibir todos los datos de purchaseDetails
    $tramite_id = sanitize_text_field($_POST['tramite_id'] ?? '');
    $customer_name = sanitize_text_field($_POST['customer_name'] ?? '');
    $customer_email = sanitize_email($_POST['customer_email'] ?? '');
    $customer_dni = sanitize_text_field($_POST['customer_dni'] ?? '');
    $customer_phone = sanitize_text_field($_POST['customer_phone'] ?? '');

    error_log('Tramite: ' . $tramite_id);
    error_log('Cliente: ' . $customer_name . ' (' . $customer_email . ')');

    // Vendedor
    $seller_name = sanitize_text_field($_POST['seller_name'] ?? '');
    $seller_dni = sanitize_text_field($_POST['seller_dni'] ?? '');

    // Vehículo
    $vehicle_type = sanitize_text_field($_POST['vehicle_type'] ?? '');
    $manufacturer = sanitize_text_field($_POST['manufacturer'] ?? '');
    $model = sanitize_text_field($_POST['model'] ?? '');
    $registration = sanitize_text_field($_POST['registration'] ?? '');
    $purchase_price = floatval($_POST['purchase_price'] ?? 0);
    $region = sanitize_text_field($_POST['region'] ?? '');

    // Precios
    $base_price = floatval($_POST['basePrice'] ?? 0);
    $final_amount = floatval($_POST['finalAmount'] ?? 0);

    // ITP
    $itp_pagado = isset($_POST['itp_paid']) ? filter_var($_POST['itp_paid'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : null;
    $itp_gestion = sanitize_text_field($_POST['itp_management_option'] ?? '');
    $itp_metodo_pago = sanitize_text_field($_POST['itp_payment_method'] ?? '');
    $itp_amount = floatval($_POST['itp_amount'] ?? 0);
    $itp_comision = floatval($_POST['itp_commission'] ?? 0);
    $itp_total = floatval($_POST['itp_total_amount'] ?? 0);

    // Extras
    $cambio_lista = isset($_POST['cambioLista']) ? filter_var($_POST['cambioLista'], FILTER_VALIDATE_BOOLEAN) : false;
    $cambio_lista_precio = floatval($_POST['cambioListaPrecio'] ?? 0);

    // Cupón
    $coupon_code = sanitize_text_field($_POST['couponCode'] ?? '');
    $coupon_discount = floatval($_POST['couponDiscount'] ?? 0);

    // Payment
    $payment_intent_id = sanitize_text_field($_POST['paymentIntentId'] ?? '');

    // Tracking
    $tracking_url = isset($_POST['tracking_url']) ? esc_url($_POST['tracking_url']) : '';

    // Documentos
    $uploaded_files = isset($_POST['uploadedFiles']) ? json_decode(stripslashes($_POST['uploadedFiles']), true) : [];

    $headers = ['Content-Type: text/html; charset=UTF-8', 'From: Tramitfy <info@tramitfy.es>'];

    // ===================================
    // EMAIL AL ADMIN (ipmgroup24@gmail.com)
    // ===================================
    $admin_email = 'ipmgroup24@gmail.com';
    $subject_admin = "Nuevo Trámite Barco - {$tramite_id}";

    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f3f4f6; margin: 0; padding: 20px; }
            .container { max-width: 700px; margin: 0 auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
            .header { background: linear-gradient(135deg, #016d86 0%, #01546a 100%); padding: 30px; color: white; text-align: center; }
            .header h1 { margin: 0; font-size: 24px; }
            .tramite-id { background: rgba(255,255,255,0.2); display: inline-block; padding: 8px 16px; border-radius: 6px; margin-top: 12px; font-weight: 600; letter-spacing: 1px; }
            .content { padding: 30px; }
            .section { margin-bottom: 30px; }
            .section-title { font-size: 18px; font-weight: 700; color: #1f2937; margin-bottom: 16px; padding-bottom: 8px; border-bottom: 2px solid #016d86; }
            .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
            .info-item { background: #f9fafb; padding: 12px; border-radius: 6px; border-left: 3px solid #016d86; }
            .info-label { font-size: 12px; color: #6b7280; font-weight: 600; margin-bottom: 4px; }
            .info-value { font-size: 14px; color: #1f2937; font-weight: 600; }
            .price-breakdown { background: #eff6ff; border: 2px solid #016d86; border-radius: 8px; padding: 20px; }
            .price-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #e5e7eb; }
            .price-row:last-child { border-bottom: none; }
            .price-label { font-size: 14px; color: #374151; }
            .price-value { font-size: 14px; font-weight: 600; color: #1f2937; }
            .total-row { background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(5, 150, 105, 0.1)); margin-top: 12px; padding: 12px; border-radius: 6px; font-size: 18px; font-weight: 700; }
            .alert-box { background: #fef3c7; border-left: 4px solid #f59e0b; padding: 16px; border-radius: 6px; margin: 16px 0; }
            .alert-success { background: #d1fae5; border-color: #10b981; }
            .alert-info { background: #dbeafe; border-color: #016d86; }
            .docs-list { list-style: none; padding: 0; }
            .docs-list li { background: #f9fafb; padding: 10px; margin-bottom: 8px; border-radius: 6px; display: flex; align-items: center; gap: 8px; font-size: 14px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>🏍️ Nuevo Trámite: Transferencia Barco</h1>
                <div class="tramite-id"><?php echo esc_html($tramite_id); ?></div>
            </div>

            <div class="content">
                <!-- Datos del Cliente -->
                <div class="section">
                    <div class="section-title">👤 Datos del Cliente (Comprador)</div>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Nombre</div>
                            <div class="info-value"><?php echo esc_html($customer_name); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">DNI</div>
                            <div class="info-value"><?php echo esc_html($customer_dni); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Email</div>
                            <div class="info-value"><?php echo esc_html($customer_email); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Teléfono</div>
                            <div class="info-value"><?php echo esc_html($customer_phone); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Datos del Vehículo -->
                <div class="section">
                    <div class="section-title">🏍️ Datos del Vehículo</div>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Tipo</div>
                            <div class="info-value"><?php echo esc_html($vehicle_type); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Marca/Modelo</div>
                            <div class="info-value"><?php echo esc_html($manufacturer . ' ' . $model); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Matrícula</div>
                            <div class="info-value"><?php echo esc_html($registration); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Precio de Compra</div>
                            <div class="info-value"><?php echo number_format($purchase_price, 2, ',', '.'); ?> €</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Región</div>
                            <div class="info-value"><?php echo esc_html(ucfirst(str_replace('-', ' ', $region))); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Vendedor</div>
                            <div class="info-value"><?php echo esc_html($seller_name); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Desglose de Precios -->
                <div class="section">
                    <div class="section-title">💰 Desglose Económico</div>
                    <div class="price-breakdown">
                        <div class="price-row">
                            <div class="price-label">Honorarios Tramitfy</div>
                            <div class="price-value"><?php echo number_format($base_price, 2, ',', '.'); ?> €</div>
                        </div>

                        <?php if ($itp_pagado === false && $itp_gestion === 'gestionan-ustedes'): ?>
                        <div class="price-row">
                            <div class="price-label">
                                ITP (<?php echo $purchase_price > 0 ? number_format(($itp_amount / $purchase_price) * 100, 1) : '0'; ?>%)
                                <?php if ($itp_metodo_pago === 'transferencia'): ?>
                                    <span style="font-size: 12px; color: #10b981;">• Transferencia</span>
                                <?php else: ?>
                                    <span style="font-size: 12px; color: #f59e0b;">• Tarjeta</span>
                                <?php endif; ?>
                            </div>
                            <div class="price-value"><?php echo number_format($itp_amount, 2, ',', '.'); ?> €</div>
                        </div>

                        <?php if ($itp_comision > 0): ?>
                        <div class="price-row">
                            <div class="price-label">Comisión tarjeta ITP (1,5%)</div>
                            <div class="price-value"><?php echo number_format($itp_comision, 2, ',', '.'); ?> €</div>
                        </div>
                        <?php endif; ?>
                        <?php endif; ?>

                        <?php if ($cambio_lista): ?>
                        <div class="price-row">
                            <div class="price-label">Cambio de Lista</div>
                            <div class="price-value">+<?php echo number_format($cambio_lista_precio, 2, ',', '.'); ?> €</div>
                        </div>
                        <?php endif; ?>

                        <?php if ($coupon_discount > 0): ?>
                        <div class="price-row" style="color: #10b981;">
                            <div class="price-label">Cupón "<?php echo esc_html($coupon_code); ?>" (-<?php echo $coupon_discount; ?>%)</div>
                            <div class="price-value">-<?php echo number_format(($final_amount / (1 - $coupon_discount/100)) - $final_amount, 2, ',', '.'); ?> €</div>
                        </div>
                        <?php endif; ?>

                        <div class="total-row price-row">
                            <div class="price-label">TOTAL PAGADO</div>
                            <div class="price-value" style="color: #10b981; font-size: 20px;"><?php echo number_format($final_amount, 2, ',', '.'); ?> €</div>
                        </div>
                    </div>
                </div>

                <!-- Gestión del ITP -->
                <div class="section">
                    <div class="section-title">📋 Gestión del ITP</div>
                    <?php if ($itp_pagado === true): ?>
                        <div class="alert-box alert-success">
                            <strong>✅ ITP Ya Pagado</strong><br>
                            El cliente ya ha pagado el ITP previamente.
                        </div>
                    <?php elseif ($itp_gestion === 'gestionan-ustedes'): ?>
                        <div class="alert-box alert-info">
                            <strong>🏢 Lo Gestionamos Nosotros</strong><br>
                            Método de pago: <strong><?php echo $itp_metodo_pago === 'tarjeta' ? 'Tarjeta (cobrado)' : 'Transferencia (pendiente)'; ?></strong><br>
                            Importe ITP: <strong><?php echo number_format($itp_amount, 2, ',', '.'); ?> €</strong>
                            <?php if ($itp_metodo_pago === 'transferencia'): ?>
                            <br><span style="color: #f59e0b;">⚠️ Cliente debe transferir ITP aparte</span>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert-box">
                            <strong>👤 Lo Paga el Cliente</strong><br>
                            El cliente se encargará de pagar el ITP directamente.
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Documentos Adjuntos -->
                <?php if (!empty($uploaded_files)): ?>
                <div class="section">
                    <div class="section-title">📎 Documentos Adjuntos</div>
                    <ul class="docs-list">
                        <?php foreach ($uploaded_files as $doc_key => $doc_info): ?>
                            <li>
                                ✓ <strong><?php echo esc_html(ucfirst(str_replace('_', ' ', $doc_key))); ?></strong>
                                <?php if (is_array($doc_info) && isset($doc_info['filename'])): ?>
                                    - <?php echo esc_html($doc_info['filename']); ?>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <!-- Información de Pago -->
                <div class="section">
                    <div class="section-title">💳 Información de Pago</div>
                    <div class="info-item">
                        <div class="info-label">Payment Intent ID</div>
                        <div class="info-value" style="font-size: 11px; word-break: break-all;"><?php echo esc_html($payment_intent_id); ?></div>
                    </div>
                </div>

                <?php if ($tracking_url): ?>
                <div style="text-align: center; margin-top: 30px;">
                    <a href="<?php echo esc_url($tracking_url); ?>" style="display: inline-block; background: #016d86; color: white; padding: 14px 32px; text-decoration: none; border-radius: 8px; font-weight: 600;">
                        🔍 Ver Trámite en Sistema
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </body>
    </html>
    <?php
    $message_admin = ob_get_clean();

    error_log('Email admin ELIMINADO - se envía solo al final con adjuntos');
    // $admin_sent = wp_mail($admin_email, $subject_admin, $message_admin, $headers);
    $admin_sent = true; // Simular éxito

    // ===================================
    // EMAIL AL CLIENTE (mejorado)
    // ===================================
    $subject_customer = "Confirmación - Trámite {$tramite_id} - Tramitfy";

    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f3f4f6; margin: 0; padding: 20px; }
            .container { max-width: 650px; margin: 0 auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
            .header { background: linear-gradient(135deg, #016d86 0%, #01546a 100%); padding: 30px; color: white; text-align: center; }
            .header h1 { margin: 0; font-size: 24px; }
            .tramite-box { background: #eff6ff; border: 2px solid #016d86; padding: 16px; border-radius: 8px; text-align: center; margin: 20px 0; }
            .tramite-number { font-size: 24px; font-weight: 700; color: #016d86; letter-spacing: 1px; }
            .content { padding: 30px; }
            .info-box { background: #f7f9fa; border: 1px solid #e0e5e9; border-radius: 8px; padding: 20px; margin: 20px 0; }
            .alert-warning { background: #fef3c7; border-left: 4px solid #f59e0b; padding: 16px; border-radius: 6px; margin: 20px 0; }
            .btn { display: inline-block; background: #016d86; color: white; padding: 14px 32px; text-decoration: none; border-radius: 8px; font-weight: 600; margin: 20px 0; }
            .timeline { margin: 20px 0; }
            .timeline ol { padding-left: 20px; }
            .timeline li { margin-bottom: 10px; color: #374151; }
            .footer { background: #016d86; color: white; padding: 25px 30px; text-align: center; font-size: 14px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>✅ ¡Trámite Recibido!</h1>
                <p style="margin: 10px 0 0;">Tu transferencia está en proceso</p>
            </div>

            <div class="content">
                <p>Hola <strong><?php echo esc_html($customer_name); ?></strong>,</p>
                <p>Hemos recibido correctamente tu solicitud de transferencia. Tu número de trámite es:</p>

                <div class="tramite-box">
                    <div style="font-size: 12px; color: #6b7280; margin-bottom: 4px;">Número de Trámite</div>
                    <div class="tramite-number"><?php echo esc_html($tramite_id); ?></div>
                </div>

                <div class="info-box">
                    <h3 style="margin-top: 0;">📋 Resumen de tu trámite</h3>
                    <p><strong>Vehículo:</strong> <?php echo esc_html($manufacturer . ' ' . $model); ?></p>
                    <p><strong>Matrícula:</strong> <?php echo esc_html($registration); ?></p>
                    <p><strong>Total pagado:</strong> <?php echo number_format($final_amount, 2, ',', '.'); ?> €</p>
                </div>

                <?php if ($itp_pagado === false && $itp_gestion === 'gestionan-ustedes' && $itp_metodo_pago === 'transferencia'): ?>
                <?php error_log('CONDICION EMAIL SIMPLE: itp_pagado=' . ($itp_pagado === false ? 'false' : 'other') . ', gestion=' . $itp_gestion . ', metodo=' . $itp_metodo_pago); ?>
                <div class="alert-warning">
                    <strong>⚠️ Acción Requerida: Pago del ITP por Transferencia</strong><br>
                    Recuerda realizar la transferencia del ITP:<br>
                    <strong>Importe:</strong> <?php echo number_format($itp_amount, 2, ',', '.'); ?> €<br>
                    <strong>Concepto:</strong> ITP - <?php echo esc_html($tramite_id); ?><br>
                    <em>Recibirás un email con los datos bancarios en breve.</em>
                </div>
                <?php endif; ?>

                <?php if ($tracking_url): ?>
                <div style="text-align: center;">
                    <a href="<?php echo esc_url($tracking_url); ?>" class="btn">
                        🔍 Seguir mi trámite
                    </a>
                </div>
                <?php endif; ?>

                <div class="timeline">
                    <h3>⏱️ ¿Qué sigue ahora?</h3>
                    <ol>
                        <li>Revisaremos tu documentación en las próximas 24 horas</li>
                        <li>Te contactaremos si necesitamos algún documento adicional</li>
                        <li>Procesaremos tu trámite ante la DGMM</li>
                        <li>Recibirás una notificación cuando esté completado (aprox. 5-7 días hábiles)</li>
                    </ol>
                </div>

                <p>Gracias por confiar en <strong>Tramitfy</strong>.</p>
            </div>

            <div class="footer">
                <p style="margin: 0 0 10px 0; font-weight: 600;">Tramitfy S.L.</p>
                <p style="margin: 5px 0;">📧 info@tramitfy.es | 📞 +34 689 170 273</p>
                <p style="margin: 5px 0;">Paseo Castellana 194 puerta B, Madrid, España</p>
            </div>
        </div>
    </body>
    </html>
    <?php
    $message_customer = ob_get_clean();

    // EMAIL ACTIVADO - se envía al cliente
    error_log('=== DEBUG EMAIL CLIENTE ===');
    error_log('RAW POST itp_management_option: [' . ($_POST['itp_management_option'] ?? 'NOT_SET') . ']');
    error_log('RAW POST itp_payment_method: [' . ($_POST['itp_payment_method'] ?? 'NOT_SET') . ']');
    error_log('RAW POST itp_amount: [' . ($_POST['itp_amount'] ?? 'NOT_SET') . ']');
    error_log('PROCESSED itp_gestion: [' . $itp_gestion . ']');
    error_log('PROCESSED itp_metodo_pago: [' . $itp_metodo_pago . ']');
    error_log('PROCESSED itp_amount: [' . $itp_amount . ']');
    error_log('Condición transferencia: ' . (($itp_gestion === 'gestionan-ustedes' && $itp_metodo_pago === 'transferencia') ? 'TRUE' : 'FALSE'));
    
    
    error_log('Email cliente enviado: ' . ($customer_sent ? 'SI' : 'NO'));

    error_log('=== TPM SEND EMAILS V2 COMPLETADO ===');

    wp_send_json_success('Emails enviados correctamente');
}

/**
 * 4. UPLOAD DE DOCUMENTOS Y GENERACIÓN DE PDF
 */
add_action('wp_ajax_tpb_upload_documents', 'tpb_upload_documents');
add_action('wp_ajax_nopriv_tpb_upload_documents', 'tpb_upload_documents');

function tpb_upload_documents() {
    error_log('=== TPM UPLOAD DOCUMENTS INICIADO ===');

    $tramite_id = sanitize_text_field($_POST['tramite_id'] ?? '');
    error_log('Tramite ID: ' . $tramite_id);

    if (empty($tramite_id)) {
        error_log('ERROR: Tramite ID vacío');
        wp_send_json_error('Trámite ID requerido');
        return;
    }

    // Directorio de uploads
    $upload_dir = wp_upload_dir();
    $tramite_dir = $upload_dir['basedir'] . '/tramites/' . $tramite_id . '/';
    error_log('Directorio tramite: ' . $tramite_dir);

    // Crear directorio si no existe
    if (!file_exists($tramite_dir)) {
        wp_mkdir_p($tramite_dir);
        error_log('Directorio creado: ' . $tramite_dir);
    }

    $uploaded_files = [];
    error_log('Archivos recibidos: ' . print_r(array_keys($_FILES), true));

    // Lista de archivos esperados
    $file_fields = [
        'dni_buyer_front',
        'dni_buyer_back',
        'dni_seller_front',
        'dni_seller_back',
        'vehicle_card',
        'contract',
        'itp_receipt'
    ];

    foreach ($file_fields as $field) {
        if (isset($_FILES[$field]) && $_FILES[$field]['error'] === 0) {
            $file = $_FILES[$field];
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = $field . '_' . time() . '.' . $extension;
            $filepath = $tramite_dir . $filename;

            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                $uploaded_files[$field] = [
                    'filename' => $filename,
                    'url' => $upload_dir['baseurl'] . '/tramites/' . $tramite_id . '/' . $filename,
                    'path' => $filepath
                ];
                error_log('Archivo subido: ' . $field . ' -> ' . $filename);
            } else {
                error_log('ERROR subiendo archivo: ' . $field);
            }
        }
    }

    error_log('Total archivos subidos: ' . count($uploaded_files));

    // Generar PDF de autorización si hay firma
    $authorization_pdf_url = '';
    if (isset($_POST['signature_data']) && !empty($_POST['signature_data'])) {
        error_log('Generando PDF de autorización...');
        $pdf_data = [
            'tramite_id' => $tramite_id,
            'customer_name' => sanitize_text_field($_POST['customer_name'] ?? ''),
            'customer_dni' => sanitize_text_field($_POST['customer_dni'] ?? ''),
            'seller_name' => sanitize_text_field($_POST['seller_name'] ?? ''),
            'seller_dni' => sanitize_text_field($_POST['seller_dni'] ?? ''),
            'vehicle_type' => sanitize_text_field($_POST['vehicle_type'] ?? ''),
            'manufacturer' => sanitize_text_field($_POST['manufacturer'] ?? ''),
            'model' => sanitize_text_field($_POST['model'] ?? ''),
            'registration' => sanitize_text_field($_POST['registration'] ?? ''),
            'signature_data' => $_POST['signature_data'],
            'tramite_dir' => $tramite_dir,
            'upload_dir' => $upload_dir
        ];

        $authorization_pdf_url = tpb_generate_authorization_pdf($pdf_data);
        error_log('PDF generado: ' . $authorization_pdf_url);
    } else {
        error_log('No hay firma para generar PDF');
    }

    error_log('=== TPM UPLOAD DOCUMENTS COMPLETADO ===');

    wp_send_json_success([
        'message' => 'Documentos subidos correctamente',
        'files' => $uploaded_files,
        'authorization_pdf_url' => $authorization_pdf_url,
        'tramite_id' => $tramite_id
    ]);
}

/**
 * Generar PDF de autorización
 */
function tpb_generate_authorization_pdf($data) {
    error_log('=== GENERANDO PDF DE AUTORIZACION ===');
    error_log('Datos PDF: ' . print_r(array_keys($data), true));

    // Verificar si existe FPDF
    $fpdf_path = get_template_directory() . '/fpdf/fpdf.php';
    error_log('Buscando FPDF en: ' . $fpdf_path);

    if (!file_exists($fpdf_path)) {
        error_log('ERROR: FPDF no encontrado en: ' . $fpdf_path);
        return '';
    }

    error_log('FPDF encontrado, generando PDF...');
    require_once($fpdf_path);

    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 16);

    // Título
    $pdf->Cell(0, 10, 'AUTORIZACION DE TRANSFERENCIA', 0, 1, 'C');
    $pdf->Ln(10);

    // ID Trámite
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 10, 'Tramite: ' . $data['tramite_id'], 0, 1);
    $pdf->Ln(5);

    // Comprador
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 10, 'COMPRADOR:', 0, 1);
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 8, 'Nombre: ' . utf8_decode($data['customer_name']), 0, 1);
    $pdf->Cell(0, 8, 'DNI: ' . $data['customer_dni'], 0, 1);
    $pdf->Ln(5);

    // Vendedor
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 10, 'VENDEDOR:', 0, 1);
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 8, 'Nombre: ' . utf8_decode($data['seller_name']), 0, 1);
    $pdf->Cell(0, 8, 'DNI: ' . $data['seller_dni'], 0, 1);
    $pdf->Ln(5);

    // Vehículo
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 10, 'VEHICULO:', 0, 1);
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 8, 'Tipo: ' . utf8_decode($data['vehicle_type']), 0, 1);
    $pdf->Cell(0, 8, 'Marca/Modelo: ' . utf8_decode($data['manufacturer'] . ' ' . $data['model']), 0, 1);
    $pdf->Ln(10);

    // Firma (si existe)
    if (!empty($data['signature_data'])) {
        $signature_data = preg_replace('#^data:image/\w+;base64,#i', '', $data['signature_data']);
        $signature_data = base64_decode($signature_data);
        $signature_temp = $data['tramite_dir'] . 'signature_temp.png';
        file_put_contents($signature_temp, $signature_data);

        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 10, 'FIRMA DEL COMPRADOR:', 0, 1);
        $pdf->Image($signature_temp, 20, $pdf->GetY(), 60);

        // Eliminar temp
        @unlink($signature_temp);
    }

    // Guardar PDF
    $pdf_filename = 'autorizacion_' . $data['tramite_id'] . '_' . time() . '.pdf';
    $pdf_path = $data['tramite_dir'] . $pdf_filename;

    error_log('Guardando PDF en: ' . $pdf_path);
    $pdf->Output('F', $pdf_path);

    $pdf_url = $data['upload_dir']['baseurl'] . '/tramites/' . $data['tramite_id'] . '/' . $pdf_filename;
    error_log('PDF guardado correctamente. URL: ' . $pdf_url);
    error_log('=== PDF AUTORIZACION COMPLETADO ===');

    // Retornar URL
    return $pdf_url;
}

/**
 * Helper function to log debug messages to a file we can access
 */
function tpb_debug_log($message) {
    $debug_log = get_template_directory() . '/tramitfy-barco-debug.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($debug_log, "[$timestamp] $message\n", FILE_APPEND);
    error_log($message);
}

/**
 * 4. SUBMIT FINAL FORM (documentos + firma)
 */
add_action('wp_ajax_submit_barco_form_tpb', 'tpb_submit_form');
add_action('wp_ajax_nopriv_submit_barco_form_tpb', 'tpb_submit_form');
function tpb_submit_form() {
    tpb_debug_log('[TPM] INICIO tmp_submit_form');
    tramitfy_barco_log('========== INICIO SUBMIT FORMULARIO ==========', 'SUBMIT', 'INFO');
    tramitfy_barco_log('POST recibido: ' . count($_POST) . ' campos, FILES: ' . count($_FILES), 'SUBMIT', 'INFO');
    
    // 🔍 CARGAR SISTEMA DE DEBUG CENTRALIZADO
    $debug_file_path = get_template_directory() . '/debug-itp-variables.php';
    error_log('🔍 Intentando cargar debug desde: ' . $debug_file_path);
    if (file_exists($debug_file_path)) {
        require_once $debug_file_path;
        error_log('✅ Debug file cargado correctamente');
        debug_itp_variables('TPM_SUBMIT_FORM_START');
    } else {
        error_log('❌ Debug file NO encontrado en: ' . $debug_file_path);
    }

    try {
        tramitfy_barco_log('Procesando datos del cliente', 'SUBMIT', 'INFO');
        $customer_name = sanitize_text_field($_POST['customer_name']);
        tramitfy_barco_log('Cliente: ' . $customer_name, 'SUBMIT', 'INFO');
        $customer_dni = sanitize_text_field($_POST['customer_dni']);
        $customer_email = sanitize_email($_POST['customer_email']);
        $customer_phone = sanitize_text_field($_POST['customer_phone']);
        $vehicle_type = sanitize_text_field($_POST['vehicle_type']);
        $no_encuentro = isset($_POST['no_encuentro_checkbox']) && $_POST['no_encuentro_checkbox'] === 'on';
        $manufacturer = sanitize_text_field($_POST['manufacturer']);
        $model = sanitize_text_field($_POST['model']);
        $manual_manufacturer = sanitize_text_field($_POST['manual_manufacturer']);
        $manual_model = sanitize_text_field($_POST['manual_model']);
        $matriculation_date = sanitize_text_field($_POST['matriculation_date']);
        $purchase_price = floatval($_POST['purchase_price']);
        $region = sanitize_text_field($_POST['region']);
        $nuevo_nombre = isset($_POST['nuevo_nombre']) ? sanitize_text_field($_POST['nuevo_nombre']) : '';
        $nuevo_puerto = isset($_POST['nuevo_puerto']) ? sanitize_text_field($_POST['nuevo_puerto']) : '';
        $coupon_used = isset($_POST['coupon_used']) ? sanitize_text_field($_POST['coupon_used']) : '';
        $cambio_lista = isset($_POST['cambio_lista']) && $_POST['cambio_lista'] === 'true';
        $signature = $_POST['signature'];
    
        tpb_debug_log('[TPM] Datos básicos procesados');

        $final_amount = isset($_POST['final_amount']) ? floatval($_POST['final_amount']) : 0;
        $current_transfer_tax = isset($_POST['current_transfer_tax']) ? floatval($_POST['current_transfer_tax']) : 0;
        $current_extra_fee = isset($_POST['current_extra_fee']) ? floatval($_POST['current_extra_fee']) : 0;
        $tasas_hidden = isset($_POST['tasas_hidden']) ? floatval($_POST['tasas_hidden']) : 0;
        $iva_hidden = isset($_POST['iva_hidden']) ? floatval($_POST['iva_hidden']) : 0;
        $honorarios_hidden = isset($_POST['honorarios_hidden']) ? floatval($_POST['honorarios_hidden']) : 0;
        
        // ITP información (necesaria para email del cliente)
        $itp_pagado = isset($_POST['itp_paid']) ? filter_var($_POST['itp_paid'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : null;
        $itp_gestion = sanitize_text_field($_POST['itp_management_option'] ?? '');
        $itp_metodo_pago = sanitize_text_field($_POST['itp_payment_method'] ?? '');
        $itp_amount = floatval($_POST['itp_amount'] ?? 0);
        $itp_comision = floatval($_POST['itp_commission'] ?? 0);
        $itp_total = floatval($_POST['itp_total_amount'] ?? 0);

        tpb_debug_log('[TPM] Valores económicos recibidos: finalAmount=' . $final_amount . ', ITP=' . $current_transfer_tax . ', tasas=' . $tasas_hidden . ', iva=' . $iva_hidden . ', honorarios=' . $honorarios_hidden);
        tpb_debug_log('[TPM] ITP información: gestion=' . $itp_gestion . ', metodo=' . $itp_metodo_pago . ', amount=' . $itp_amount);
        
        // 🔍 DEBUG VARIABLES ITP PROCESADAS
        if (function_exists('debug_itp_variables')) {
            debug_itp_variables('TPM_AFTER_PROCESSING_POST');
        }
        
        // 🔍 DEBUG DIRECTO VARIABLES ITP
        error_log('=== DEBUG ITP DIRECTO ===');
        error_log('itp_paid (POST): [' . ($_POST['itp_paid'] ?? 'NOT_SET') . ']');
        error_log('itp_management_option (POST): [' . ($_POST['itp_management_option'] ?? 'NOT_SET') . ']');
        error_log('itp_payment_method (POST): [' . ($_POST['itp_payment_method'] ?? 'NOT_SET') . ']');
        error_log('itp_amount (POST): [' . ($_POST['itp_amount'] ?? 'NOT_SET') . ']');
        error_log('Variables PHP procesadas:');
        error_log('$itp_gestion: [' . $itp_gestion . ']');
        error_log('$itp_metodo_pago: [' . $itp_metodo_pago . ']');
        error_log('$itp_amount: [' . $itp_amount . ']');
        error_log('========================');
    
        // Generar TRÁMITE ID para Transferencia
        $prefix = 'TMA-TRANS';
        $counter_option = 'tma_trans_counter';
        $current_cnt = get_option($counter_option, 0);
        $current_cnt++;
        update_option($counter_option, $current_cnt);
    
        $date_part = date('Ymd');
        $secuencial = str_pad($current_cnt, 6, '0', STR_PAD_LEFT);
        $tramite_id = $prefix . '-' . $date_part . '-' . $secuencial;
    
        // Procesar la imagen de la firma en PNG
        tpb_debug_log('[TPM] Procesando firma');
        $signature_data = str_replace('data:image/png;base64,', '', $signature);
        $signature_data = str_replace(' ', '+', $signature_data);
        $signature_data = base64_decode($signature_data);
        $upload_dir = wp_upload_dir();
        $signature_image_name = 'signature_' . time() . '.png';
        $signature_image_path = $upload_dir['path'] . '/' . $signature_image_name;
        file_put_contents($signature_image_path, $signature_data);
        tpb_debug_log('[TPM] Firma guardada: ' . $signature_image_path);
    
        // Obtener base_price desde CSV si es necesario
        $base_price = 0;
        if (!$no_encuentro && $vehicle_type !== 'Embarcación') {
            $csv_file = ($vehicle_type === 'Embarcación') ? 'BARCO.csv' : 'BARCO.csv';
            $csv_path = get_template_directory() . '/' . $csv_file;
            if (($handle = fopen($csv_path, 'r')) !== false) {
                fgetcsv($handle, 1000, ','); // Saltar encabezado
                while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                    list($csv_manufacturer, $csv_model, $csv_price) = $row;
                    if ($csv_manufacturer === $manufacturer && $csv_model === $model) {
                        $base_price = floatval($csv_price);
                        break;
                    }
                }
                fclose($handle);
            }
        }
    
        // Crear PDF de autorización profesional
        tpb_debug_log('[TPM] Creando PDF autorización');
        require_once get_template_directory() . '/vendor/fpdf/fpdf.php';
        $pdf = new FPDF();
        $pdf->AddPage();
        $pdf->SetAutoPageBreak(true, 20);
    
        // Colores corporativos (RGB)
        $colorPrimario = array(1, 109, 134);  // #016d86
        $colorGris = array(85, 85, 85);       // #555
    
        // === ENCABEZADO ===
        // Logo (si existe en el servidor)
        $logo_path = get_template_directory() . '/assets/img/logo.png';
        if (file_exists($logo_path)) {
            $pdf->Image($logo_path, 15, 12, 40);
        }
    
        // Línea superior decorativa
        $pdf->SetFillColor($colorPrimario[0], $colorPrimario[1], $colorPrimario[2]);
        $pdf->Rect(0, 0, 210, 3, 'F');
    
        // Información del documento (lado derecho)
        $pdf->SetXY(130, 15);
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->SetTextColor($colorPrimario[0], $colorPrimario[1], $colorPrimario[2]);
        $pdf->Cell(65, 5, utf8_decode('DOCUMENTO OFICIAL'), 0, 1, 'R');
    
        $pdf->SetFont('Arial', '', 9);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->SetX(130);
        $pdf->Cell(65, 5, utf8_decode('Fecha: ') . date('d/m/Y'), 0, 1, 'R');
        $pdf->SetX(130);
        $pdf->Cell(65, 5, utf8_decode('ID: ') . $tramite_id, 0, 1, 'R');
    
        $pdf->Ln(15);
    
        // === TÍTULO PRINCIPAL ===
        $pdf->SetFont('Arial', 'B', 18);
        $pdf->SetTextColor($colorPrimario[0], $colorPrimario[1], $colorPrimario[2]);
        $pdf->Cell(0, 8, utf8_decode('AUTORIZACIÓN PARA TRANSFERENCIA'), 0, 1, 'C');
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(0, 8, utf8_decode('DE PROPIEDAD'), 0, 1, 'C');
    
        $pdf->Ln(10);
    
        // === TEXTO INTRODUCTORIO ===
        $pdf->SetFont('Arial', '', 11);
        $pdf->SetTextColor(0, 0, 0);
        $texto = "Yo, $customer_name, con DNI $customer_dni y correo electrónico $customer_email, autorizo expresamente a TRAMITFY S.L. (CIF B55388557) para que, actuando en mi nombre y representación, realice todas las gestiones necesarias para la transferencia de propiedad del siguiente vehículo:";
        $pdf->MultiCell(0, 6, utf8_decode($texto), 0, 'J');
        $pdf->Ln(8);
    
        // === SECCIÓN: DATOS DEL VEHÍCULO ===
        // Encabezado de sección
        $pdf->SetFillColor(240, 240, 240);
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->SetTextColor($colorPrimario[0], $colorPrimario[1], $colorPrimario[2]);
        $pdf->Cell(0, 8, utf8_decode('  DATOS DEL VEHÍCULO'), 0, 1, 'L', true);
        $pdf->Ln(2);
    
        // Contenido de la sección
        $pdf->SetFont('Arial', '', 11);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(50, 6, utf8_decode('Tipo de Vehículo:'), 0, 0);
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(0, 6, utf8_decode($vehicle_type), 0, 1);
    
        $pdf->SetFont('Arial', '', 11);
        if (!$no_encuentro) {
            $pdf->Cell(50, 6, utf8_decode('Fabricante:'), 0, 0);
            $pdf->SetFont('Arial', 'B', 11);
            $pdf->Cell(0, 6, utf8_decode($manufacturer), 0, 1);
    
            $pdf->SetFont('Arial', '', 11);
            $pdf->Cell(50, 6, utf8_decode('Modelo:'), 0, 0);
            $pdf->SetFont('Arial', 'B', 11);
            $pdf->Cell(0, 6, utf8_decode($model), 0, 1);
    
        } else {
            $pdf->Cell(50, 6, utf8_decode('Fabricante:'), 0, 0);
            $pdf->SetFont('Arial', 'B', 11);
            $pdf->Cell(0, 6, utf8_decode($manual_manufacturer), 0, 1);
    
            $pdf->SetFont('Arial', '', 11);
            $pdf->Cell(50, 6, utf8_decode('Modelo:'), 0, 0);
            $pdf->SetFont('Arial', 'B', 11);
            $pdf->Cell(0, 6, utf8_decode($manual_model), 0, 1);
    
        }
    
        // === SERVICIOS ADICIONALES (si los hay) ===
        if (!empty($nuevo_nombre) || !empty($nuevo_puerto)) {
            $pdf->Ln(8);
            $pdf->SetFillColor(240, 240, 240);
            $pdf->SetFont('Arial', 'B', 12);
            $pdf->SetTextColor($colorPrimario[0], $colorPrimario[1], $colorPrimario[2]);
            $pdf->Cell(0, 8, utf8_decode('  SERVICIOS ADICIONALES SOLICITADOS'), 0, 1, 'L', true);
            $pdf->Ln(2);
    
            $pdf->SetFont('Arial', '', 11);
            $pdf->SetTextColor(0, 0, 0);
            if (!empty($nuevo_nombre)) {
                $pdf->Cell(50, 6, utf8_decode('Cambio de Nombre:'), 0, 0);
                $pdf->SetFont('Arial', 'B', 11);
                $pdf->Cell(0, 6, utf8_decode($nuevo_nombre), 0, 1);
            }
            if (!empty($nuevo_puerto)) {
                $pdf->SetFont('Arial', '', 11);
                $pdf->Cell(50, 6, utf8_decode('Cambio de Puerto Base:'), 0, 0);
                $pdf->SetFont('Arial', 'B', 11);
                $pdf->Cell(0, 6, utf8_decode($nuevo_puerto), 0, 1);
            }
        }
    
        $pdf->Ln(8);
    
        // === DECLARACIÓN ===
        $pdf->SetFont('Arial', '', 10);
        $pdf->SetTextColor(0, 0, 0);
        $declaracion = "Esta autorización incluye la presentación de documentación, pago de tasas DGMM, y cualquier otra gestión requerida por la autoridad competente para completar la transferencia.";
        $pdf->MultiCell(0, 5, utf8_decode($declaracion), 0, 'J');
    
        $pdf->Ln(5);
        $pdf->SetFont('Arial', 'B', 10);
        $declaracion2 = "DECLARO QUE: Los datos proporcionados son veraces y me comprometo a facilitar cualquier documentación adicional que sea requerida para completar el trámite.";
        $pdf->MultiCell(0, 5, utf8_decode($declaracion2), 0, 'J');
    
        // === FIRMA ===
        $pdf->Ln(12);
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->SetTextColor($colorPrimario[0], $colorPrimario[1], $colorPrimario[2]);
        $pdf->Cell(0, 6, utf8_decode('FIRMA DEL SOLICITANTE'), 0, 1, 'C');
        $pdf->SetFont('Arial', 'I', 9);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->Cell(0, 5, utf8_decode('(La firma electrónica tiene la misma validez legal que una firma manuscrita)'), 0, 1, 'C');
        $pdf->Ln(5);
    
        // Insertar imagen de firma centrada
        $signatureWidth = 60;
        $signatureHeight = 30;
        $xPos = ($pdf->GetPageWidth() - $signatureWidth) / 2;
        $pdf->Image($signature_image_path, $xPos, $pdf->GetY(), $signatureWidth, $signatureHeight);
        $pdf->Ln($signatureHeight + 5);
    
        // Línea de firma
        $pdf->SetDrawColor($colorPrimario[0], $colorPrimario[1], $colorPrimario[2]);
        $pdf->Line(60, $pdf->GetY(), 150, $pdf->GetY());
        $pdf->Ln(2);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(0, 5, utf8_decode($customer_name), 0, 1, 'C');
        $pdf->SetFont('Arial', '', 9);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->Cell(0, 5, utf8_decode('DNI: ' . $customer_dni), 0, 1, 'C');
    
        // === PIE DE PÁGINA ===
        $pdf->SetY(-20);
        $pdf->SetFont('Arial', 'I', 8);
        $pdf->SetTextColor(120, 120, 120);
        $pdf->Cell(0, 4, utf8_decode('TRAMITFY S.L. - CIF: B55388557'), 0, 1, 'C');
        $pdf->Cell(0, 4, utf8_decode('Web: www.tramitfy.es - Email: info@tramitfy.es'), 0, 1, 'C');
    
        // Línea inferior decorativa
        $pdf->SetFillColor($colorPrimario[0], $colorPrimario[1], $colorPrimario[2]);
        $pdf->Rect(0, 294, 210, 3, 'F');
    
        // Guardar PDF
        $authorization_pdf_name = 'autorizacion_' . $tramite_id . '_' . time() . '.pdf';
        $authorization_pdf_path = $upload_dir['path'] . '/' . $authorization_pdf_name;
        $pdf->Output('F', $authorization_pdf_path);
        tpb_debug_log('[TPM] PDF guardado: ' . $authorization_pdf_path);
    
        // Borrar imagen temporal de la firma
        unlink($signature_image_path);
        tpb_debug_log('[TPM] Firma temporal eliminada');
    
        // Manejar archivos subidos (múltiples archivos por campo)
        tpb_debug_log('[TPM] Procesando archivos adjuntos');
        $attachments = [$authorization_pdf_path];
        $file_fields_with_paths = []; // Array asociativo para mantener el mapeo field_name => file_paths
        
        // Añadir el PDF de autorización
        $file_fields_with_paths['upload_autorizacion_pdf'] = [$authorization_pdf_path];
        
        $upload_fields = [
            'upload_hoja_asiento',
            'upload_registro_maritimo',
            'upload_dni_comprador',
            'upload_dni_vendedor',
            'upload_contrato_compraventa',
            'upload_itp_comprobante'
        ];
    
        foreach ($upload_fields as $field_name) {
            $file_fields_with_paths[$field_name] = []; // Inicializar array para este campo
            
            if (isset($_FILES[$field_name]) && is_array($_FILES[$field_name]['name'])) {
                // Múltiples archivos
                $file_count = count($_FILES[$field_name]['name']);
                for ($i = 0; $i < $file_count; $i++) {
                    if ($_FILES[$field_name]['error'][$i] === UPLOAD_ERR_OK) {
                        $file_array = array(
                            'name'     => $_FILES[$field_name]['name'][$i],
                            'type'     => $_FILES[$field_name]['type'][$i],
                            'tmp_name' => $_FILES[$field_name]['tmp_name'][$i],
                            'error'    => $_FILES[$field_name]['error'][$i],
                            'size'     => $_FILES[$field_name]['size'][$i]
                        );
                        $uploaded_file = wp_handle_upload($file_array, ['test_form' => false]);
                        if (isset($uploaded_file['file'])) {
                            $attachments[] = $uploaded_file['file'];
                            $file_fields_with_paths[$field_name][] = $uploaded_file['file'];
                        }
                    }
                }
            }
        }

        tpb_debug_log('[TPM] Total archivos procesados: ' . count($attachments));

        /*************************************************************/
        /*** RESPUESTA INMEDIATA AL CLIENTE - Sin esperas largas ***/
        /*************************************************************/
    
        // Guardar datos del trámite en archivo temporal para procesamiento async
        $async_data = array(
            'tramite_id' => $tramite_id,
            'customer_name' => $customer_name,
            'customer_dni' => $customer_dni,
            'customer_email' => $customer_email,
            'customer_phone' => $customer_phone,
            'vehicle_type' => $vehicle_type,
            'manufacturer' => $no_encuentro ? $manual_manufacturer : $manufacturer,
            'model' => $no_encuentro ? $manual_model : $model,
            'matriculation_date' => $no_encuentro ? '' : $matriculation_date,
            'purchase_price' => $purchase_price,
            'region' => $region,
            'nuevo_nombre' => $nuevo_nombre,
            'nuevo_puerto' => $nuevo_puerto,
            'coupon_used' => $coupon_used,
            'final_amount' => $final_amount,
            'current_transfer_tax' => $current_transfer_tax,
            'current_extra_fee' => $current_extra_fee,
            'tasas_hidden' => $tasas_hidden,
            'iva_hidden' => $iva_hidden,
            'honorarios_hidden' => $honorarios_hidden,
            'attachments' => $attachments,
            'authorization_pdf_path' => $authorization_pdf_path,
            'no_encuentro' => $no_encuentro
        );
    
        // Guardar en archivo temporal
        tpb_debug_log('[TPM] Guardando datos async');
        $temp_dir = get_temp_dir() . 'tramitfy-async/';
        if (!file_exists($temp_dir)) {
            mkdir($temp_dir, 0755, true);
        }
        $async_file = $temp_dir . 'barco-' . $tramite_id . '-' . time() . '.json';
        file_put_contents($async_file, json_encode($async_data));
        tpb_debug_log('[TPM] Archivo async guardado: ' . $async_file);

        // COMENTADO: El procesamiento async no funciona en shared hosting y no es necesario
        // $script_path = get_template_directory() . '/process-barco-async.php';
        // $log_file = get_template_directory() . '/logs/async-barco.log';
        // $cmd = sprintf('php %s %s >> %s 2>&1 &',
        //     escapeshellarg($script_path),
        //     escapeshellarg($async_file),
        //     escapeshellarg($log_file)
        // );
        // exec($cmd);
        tpb_debug_log('[TPM] Continuando con emails (sin procesamiento async)');

        // Enviar email rápido de confirmación al cliente (sin adjuntos pesados)
        $customer_email_quick = $customer_email;
        $subject_customer_quick = 'Pago Recibido - Transferencia de Embarcación';
        $message_customer_quick = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        </head>
        <body style='margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif; background-color: #f4f7fa;'>
            <table width='100%' cellpadding='0' cellspacing='0' style='background-color: #f4f7fa; padding: 40px 20px;'>
                <tr>
                    <td align='center'>
                        <table width='600' cellpadding='0' cellspacing='0' style='background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.08);'>

                            <!-- Header -->
                            <tr>
                                <td style='background: linear-gradient(135deg, #0066cc 0%, #004a99 100%); padding: 40px 40px 35px; text-align: center;'>
                                    <h1 style='margin: 0; color: #ffffff; font-size: 26px; font-weight: 600; letter-spacing: -0.5px;'>TRAMITFY</h1>
                                    <p style='margin: 8px 0 0; color: rgba(255,255,255,0.9); font-size: 14px; font-weight: 400;'>Gestión de Trámites Marítimos</p>
                                </td>
                            </tr>

                            <!-- Confirmación Pago -->
                            <tr>
                                <td style='padding: 40px 40px 30px;'>
                                    <div style='background-color: #e8f5e9; border-left: 4px solid #4caf50; padding: 16px 20px; border-radius: 4px; margin-bottom: 30px;'>
                                        <p style='margin: 0; color: #2e7d32; font-size: 15px; font-weight: 600;'>Pago recibido correctamente</p>
                                    </div>

                                    <p style='margin: 0 0 20px; color: #333; font-size: 15px; line-height: 1.6;'>
                                        Estimado/a <strong>$customer_name</strong>,
                                    </p>
                                    <p style='margin: 0 0 30px; color: #555; font-size: 15px; line-height: 1.7;'>
                                        Hemos recibido su pago y su solicitud de transferencia está siendo procesada. En breve recibirá un segundo email con todos los detalles y el enlace de seguimiento.
                                    </p>

                                    <!-- Número de Trámite -->
                                    <div style='background-color: #e3f2fd; border-radius: 8px; padding: 20px 24px; margin-bottom: 30px; text-align: center;'>
                                        <p style='margin: 0; color: #1565c0; font-size: 15px; font-weight: 600;'>
                                            Número de Trámite: <span style='color: #0d47a1;'>$tramite_id</span>
                                        </p>
                                    </div>

                                    <p style='margin: 0 0 10px; color: #555; font-size: 14px; line-height: 1.7;'>
                                        Nuestro equipo ha comenzado a procesar su solicitud. Le mantendremos informado en cada paso del proceso.
                                    </p>

                                </td>
                            </tr>

                            <!-- Footer -->
                            <tr>
                                <td style='background-color: #f8f9fa; padding: 30px 40px; text-align: center; border-top: 1px solid #e0e0e0;'>
                                    <p style='margin: 0 0 8px; color: #666; font-size: 13px;'>
                                        Gracias por confiar en nosotros
                                    </p>
                                    <p style='margin: 0; color: #0066cc; font-size: 15px; font-weight: 600;'>
                                        Equipo TRAMITFY
                                    </p>
                                    <p style='margin: 16px 0 0; color: #999; font-size: 12px;'>
                                        Este correo es informativo. Por favor, no responda a este mensaje.
                                    </p>
                                </td>
                            </tr>

                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        ";
        $headers_quick = [
            'Content-Type: text/html; charset=UTF-8',
            'From: info@tramitfy.es'
        ];
        // EMAIL ELIMINADO: Se envía solo al final con tracking
        // tpb_debug_log('[TPM] Enviando email rápido al cliente: ' . $customer_email_quick);
        // $mail_result = wp_mail($customer_email_quick, $subject_customer_quick, $message_customer_quick, $headers_quick);
        tpb_debug_log('[TPM] Email cliente rápido ELIMINADO - se envía solo con tracking');
    
        // Enviar email al ADMIN con detalles completos
        $admin_email = 'ipmgroup24@gmail.com';
        $subject_admin = "Nuevo Trámite - Transferencia Barco - $tramite_id";
        $honorarios_netos = round($honorarios_hidden / 1.21, 2);
        $message_admin = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        </head>
        <body style='margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif; background-color: #f4f7fa;'>
            <table width='100%' cellpadding='0' cellspacing='0' style='background-color: #f4f7fa; padding: 30px 20px;'>
                <tr>
                    <td align='center'>
                        <table width='700' cellpadding='0' cellspacing='0' style='background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.08);'>

                            <!-- Header Admin -->
                            <tr>
                                <td style='background: linear-gradient(135deg, #d32f2f 0%, #b71c1c 100%); padding: 30px 40px; text-align: center;'>
                                    <h1 style='margin: 0; color: #ffffff; font-size: 24px; font-weight: 600;'>🔔 NUEVO TRÁMITE</h1>
                                    <p style='margin: 8px 0 0; color: rgba(255,255,255,0.95); font-size: 15px; font-weight: 500;'>Transferencia de Embarcación</p>
                                </td>
                            </tr>

                            <!-- ID Trámite -->
                            <tr>
                                <td style='padding: 30px 40px 20px;'>
                                    <div style='background: linear-gradient(135deg, #1e88e5 0%, #1565c0 100%); border-radius: 8px; padding: 16px 24px; text-align: center; margin-bottom: 25px;'>
                                        <p style='margin: 0; color: #ffffff; font-size: 18px; font-weight: 700; letter-spacing: 0.5px;'>$tramite_id</p>
                                    </div>

                                    <!-- Datos del Cliente -->
                                    <table width='100%' cellpadding='0' cellspacing='0' style='background-color: #f8f9fa; border-radius: 8px; margin-bottom: 20px; overflow: hidden;'>
                                        <tr>
                                            <td style='padding: 20px 24px;'>
                                                <h3 style='margin: 0 0 14px; color: #d32f2f; font-size: 15px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;'>👤 CLIENTE</h3>
                                                <table width='100%' cellpadding='6' cellspacing='0'>
                                                    <tr>
                                                        <td style='color: #666; font-size: 13px; padding: 5px 0; width: 35%;'>Nombre:</td>
                                                        <td style='color: #222; font-size: 14px; padding: 5px 0; font-weight: 600;'>$customer_name</td>
                                                    </tr>
                                                    <tr>
                                                        <td style='color: #666; font-size: 13px; padding: 5px 0;'>DNI:</td>
                                                        <td style='color: #222; font-size: 14px; padding: 5px 0; font-weight: 600;'>$customer_dni</td>
                                                    </tr>
                                                    <tr>
                                                        <td style='color: #666; font-size: 13px; padding: 5px 0;'>Email:</td>
                                                        <td style='color: #1565c0; font-size: 13px; padding: 5px 0;'><a href='mailto:$customer_email' style='color: #1565c0; text-decoration: none;'>$customer_email</a></td>
                                                    </tr>
                                                    <tr>
                                                        <td style='color: #666; font-size: 13px; padding: 5px 0;'>Teléfono:</td>
                                                        <td style='color: #222; font-size: 14px; padding: 5px 0; font-weight: 600;'>$customer_phone</td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                    </table>

                                    <!-- Datos del Vehículo -->
                                    <table width='100%' cellpadding='0' cellspacing='0' style='background-color: #e3f2fd; border-radius: 8px; margin-bottom: 20px; overflow: hidden;'>
                                        <tr>
                                            <td style='padding: 20px 24px;'>
                                                <h3 style='margin: 0 0 14px; color: #0066cc; font-size: 15px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;'>🚤 VEHÍCULO</h3>
                                                <table width='100%' cellpadding='6' cellspacing='0'>
                                                    <tr>
                                                        <td style='color: #666; font-size: 13px; padding: 5px 0; width: 35%;'>Tipo:</td>
                                                        <td style='color: #222; font-size: 14px; padding: 5px 0; font-weight: 600;'>$vehicle_type</td>
                                                    </tr>
                                                    <tr>
                                                        <td style='color: #666; font-size: 13px; padding: 5px 0;'>Fabricante:</td>
                                                        <td style='color: #222; font-size: 14px; padding: 5px 0; font-weight: 600;'>" . ($no_encuentro ? $manual_manufacturer : $manufacturer) . "</td>
                                                    </tr>
                                                    <tr>
                                                        <td style='color: #666; font-size: 13px; padding: 5px 0;'>Modelo:</td>
                                                        <td style='color: #222; font-size: 14px; padding: 5px 0; font-weight: 600;'>" . ($no_encuentro ? $manual_model : $model) . "</td>
                                                    </tr>
                                                    <tr>
                                                        <td style='color: #666; font-size: 13px; padding: 5px 0;'>Precio Compra:</td>
                                                        <td style='color: #222; font-size: 14px; padding: 5px 0; font-weight: 600;'>" . number_format($purchase_price, 2) . " €</td>
                                                    </tr>
                                                    <tr>
                                                        <td style='color: #666; font-size: 13px; padding: 5px 0;'>Región:</td>
                                                        <td style='color: #222; font-size: 14px; padding: 5px 0; font-weight: 600;'>$region</td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                    </table>

                                    <!-- Desglose Económico -->
                                    <table width='100%' cellpadding='0' cellspacing='0' style='background: linear-gradient(135deg, #f5f5f5 0%, #e8e8e8 100%); border-radius: 8px; margin-bottom: 25px; overflow: hidden;'>
                                        <tr>
                                            <td style='padding: 20px 24px;'>
                                                <h3 style='margin: 0 0 14px; color: #2e7d32; font-size: 15px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;'>💰 DESGLOSE ECONÓMICO</h3>
                                                <table width='100%' cellpadding='6' cellspacing='0'>
                                                    <tr>
                                                        <td style='color: #666; font-size: 14px; padding: 6px 0; width: 50%;'>Precio Total:</td>
                                                        <td align='right' style='color: #1565c0; font-size: 17px; padding: 6px 0; font-weight: 700;'>" . number_format($final_amount, 2) . " €</td>
                                                    </tr>
                                                    <tr style='border-top: 1px solid #ddd;'>
                                                        <td style='color: #666; font-size: 13px; padding: 6px 0;'>ITP (Impuesto):</td>
                                                        <td align='right' style='color: #555; font-size: 14px; padding: 6px 0; font-weight: 600;'>" . number_format($current_transfer_tax, 2) . " €</td>
                                                    </tr>
                                                    <tr>
                                                        <td style='color: #666; font-size: 13px; padding: 6px 0;'>Tasas DGMM:</td>
                                                        <td align='right' style='color: #555; font-size: 14px; padding: 6px 0; font-weight: 600;'>" . number_format($tasas_hidden, 2) . " €</td>
                                                    </tr>
                                                    " . ($current_extra_fee > 0 ? "
                                                    <tr>
                                                        <td style='color: #666; font-size: 13px; padding: 6px 0;'>Servicios adicionales:</td>
                                                        <td align='right' style='color: #ff9800; font-size: 14px; padding: 6px 0; font-weight: 600;'>" . number_format($current_extra_fee, 2) . " €</td>
                                                    </tr>
                                                    " : "") . "
                                                    <tr style='border-top: 2px solid #4caf50; background-color: #f1f8e9;'>
                                                        <td style='color: #2e7d32; font-size: 14px; padding: 8px 0; font-weight: 700;'>Honorarios (con IVA):</td>
                                                        <td align='right' style='color: #2e7d32; font-size: 16px; padding: 8px 0; font-weight: 700;'>" . number_format($honorarios_hidden, 2) . " €</td>
                                                    </tr>
                                                    <tr style='background-color: #f1f8e9;'>
                                                        <td style='color: #558b2f; font-size: 13px; padding: 4px 0 8px 20px;'>Base imponible:</td>
                                                        <td align='right' style='color: #558b2f; font-size: 13px; padding: 4px 0 8px 0;'>" . number_format($honorarios_netos, 2) . " €</td>
                                                    </tr>
                                                    <tr style='background-color: #f1f8e9;'>
                                                        <td style='color: #558b2f; font-size: 13px; padding: 0 0 8px 20px;'>IVA (21%):</td>
                                                        <td align='right' style='color: #558b2f; font-size: 13px; padding: 0 0 8px 0;'>" . number_format($iva_hidden, 2) . " €</td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                    </table>

                                    " . (($itp_gestion === 'gestionan-ustedes') ? "
                                    <!-- Información del ITP -->
                                    <table width='100%' cellpadding='0' cellspacing='0' style='background-color: #e8f5e9; border-radius: 8px; margin-bottom: 20px; overflow: hidden; border: 2px solid #4caf50;'>
                                        <tr>
                                            <td style='padding: 20px 24px;'>
                                                <h3 style='margin: 0 0 14px; color: #2e7d32; font-size: 15px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;'>🏦 GESTIÓN DEL ITP</h3>
                                                <table width='100%' cellpadding='6' cellspacing='0'>
                                                    <tr>
                                                        <td style='color: #666; font-size: 14px; padding: 6px 0; width: 50%;'>Cliente ya pagó ITP:</td>
                                                        <td align='right' style='color: #333; font-size: 14px; padding: 6px 0; font-weight: 600;'>" . ($itp_pagado === true ? '✅ SÍ' : ($itp_pagado === false ? '❌ NO' : '❓ NO ESPECIFICADO')) . "</td>
                                                    </tr>
                                                    <tr>
                                                        <td style='color: #666; font-size: 14px; padding: 6px 0;'>Gestión del ITP:</td>
                                                        <td align='right' style='color: #2e7d32; font-size: 14px; padding: 6px 0; font-weight: 600;'>GESTIONAMOS NOSOTROS</td>
                                                    </tr>
                                                    <tr>
                                                        <td style='color: #666; font-size: 14px; padding: 6px 0;'>Método de pago ITP:</td>
                                                        <td align='right' style='color: " . ($itp_metodo_pago === 'transferencia' ? '#ff9800' : '#1565c0') . "; font-size: 14px; padding: 6px 0; font-weight: 600;'>" . ($itp_metodo_pago === 'transferencia' ? '🏦 TRANSFERENCIA BANCARIA' : '💳 TARJETA (INCLUIDO)') . "</td>
                                                    </tr>
                                                    <tr>
                                                        <td style='color: #666; font-size: 14px; padding: 6px 0;'>Importe ITP base:</td>
                                                        <td align='right' style='color: #333; font-size: 14px; padding: 6px 0; font-weight: 600;'>" . number_format($itp_amount, 2) . " €</td>
                                                    </tr>
                                                    " . ($itp_comision > 0 ? "
                                                    <tr>
                                                        <td style='color: #666; font-size: 14px; padding: 6px 0;'>Comisión tarjeta (1,5%):</td>
                                                        <td align='right' style='color: #333; font-size: 14px; padding: 6px 0; font-weight: 600;'>" . number_format($itp_comision, 2) . " €</td>
                                                    </tr>
                                                    " : "") . "
                                                    <tr style='border-top: 1px solid #4caf50;'>
                                                        <td style='color: #2e7d32; font-size: 14px; padding: 8px 0; font-weight: 700;'>Total ITP a gestionar:</td>
                                                        <td align='right' style='color: #2e7d32; font-size: 16px; padding: 8px 0; font-weight: 700;'>" . number_format($itp_total, 2) . " €</td>
                                                    </tr>
                                                </table>
                                                " . ($itp_metodo_pago === 'transferencia' ? "
                                                <div style='background-color: #fff3e0; border: 1px solid #ff9800; border-radius: 6px; padding: 14px; margin-top: 16px;'>
                                                    <p style='margin: 0; color: #e65100; font-size: 13px; font-weight: 600;'>⚠️ IMPORTANTE: Cliente debe realizar transferencia bancaria</p>
                                                    <p style='margin: 8px 0 0; color: #666; font-size: 12px;'>El cliente debe abonar " . number_format($itp_total, 2) . " € por transferencia bancaria. Se le han enviado las instrucciones por email.</p>
                                                </div>
                                                " : "") . "
                                            </td>
                                        </tr>
                                    </table>
                                    " : "") . "
                                    
                                    " . ($cambio_lista ? "
                                    <!-- Servicios Adicionales -->
                                    <table width='100%' cellpadding='0' cellspacing='0' style='background-color: #fff3e0; border-radius: 8px; margin-bottom: 20px; overflow: hidden; border: 2px solid #ff9800;'>
                                        <tr>
                                            <td style='padding: 20px 24px;'>
                                                <h3 style='margin: 0 0 14px; color: #ef6c00; font-size: 15px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;'>✨ SERVICIOS ADICIONALES</h3>
                                                <table width='100%' cellpadding='6' cellspacing='0'>
                                                    <tr>
                                                        <td style='color: #666; font-size: 14px; padding: 6px 0; width: 50%;'>Cambio de Lista:</td>
                                                        <td align='right' style='color: #ef6c00; font-size: 16px; padding: 6px 0; font-weight: 700;'>64,95 €</td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                    </table>
                                    " : "") . "

                                    <!-- Botón Dashboard -->
                                    <div style='text-align: center; margin-top: 25px;'>
                                        <a href='https://46-202-128-35.sslip.io/tramites' style='display: inline-block; background: linear-gradient(135deg, #1976d2 0%, #1565c0 100%); color: white; padding: 14px 32px; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 14px; box-shadow: 0 3px 8px rgba(25,118,210,0.3);'>
                                            📊 Ver en Dashboard
                                        </a>
                                    </div>

                                </td>
                            </tr>

                            <!-- Footer -->
                            <tr>
                                <td style='background-color: #f8f9fa; padding: 20px 40px; text-align: center; border-top: 1px solid #e0e0e0;'>
                                    <p style='margin: 0; color: #999; font-size: 12px;'>
                                        Email automático del sistema TRAMITFY
                                    </p>
                                </td>
                            </tr>

                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        ";
        tpb_debug_log('[TPM] Email admin ELIMINADO - se envía solo al final con adjuntos');
        // $admin_mail_result = wp_mail($admin_email, $subject_admin, $message_admin, $headers_quick);
        $admin_mail_result = true; // Simular éxito

        // Enviar a TRAMITFY API con archivos adjuntos
        tpb_debug_log('[TPM] Enviando webhook con archivos adjuntos');
        $tramitfy_api_url = 'https://46-202-128-35.sslip.io/api/herramientas/barcos/webhook';

        // Preparar archivos para enviar con CURLFile usando mapeo correcto por campo
        $file_fields = array();
        $total_files_to_send = 0;
        
        // Contar archivos totales
        foreach ($file_fields_with_paths as $field_name => $file_paths) {
            $total_files_to_send += count($file_paths);
        }
        tpb_debug_log('[TPM] Total archivos a enviar: ' . $total_files_to_send);
        
        // Enviar archivos con sus nombres de campo correctos
        foreach ($file_fields_with_paths as $field_name => $file_paths) {
            if (!empty($file_paths)) {
                foreach ($file_paths as $index => $file_path) {
                    if (file_exists($file_path)) {
                        $cfile = new CURLFile($file_path, mime_content_type($file_path), basename($file_path));
                        // Si hay múltiples archivos del mismo tipo, añadir sufijo
                        $final_field_name = count($file_paths) > 1 ? $field_name . '_' . $index : $field_name;
                        $file_fields[$final_field_name] = $cfile;
                        tpb_debug_log('[TPM] Adjuntando archivo como ' . $final_field_name . ': ' . basename($file_path));
                    } else {
                        tpb_debug_log('[TPM] Archivo NO existe: ' . $file_path);
                    }
                }
            }
        }

        // Añadir payment_intent_id
        $payment_intent_id = isset($_POST['payment_intent_id']) ? sanitize_text_field($_POST['payment_intent_id']) : '';

        // Calcular honorarios netos (sin IVA) - Base imponible
        // Los honorarios netos son la base, los brutos incluyen el IVA
        $honorarios_netos_calc = round(floatval($honorarios_hidden) / 1.21, 2);

        // Preparar datos completos (datos + archivos)
        // IMPORTANTE: Con multipart/form-data, todos los valores deben ser strings
        $form_data = array_merge(array(
            'tramiteId' => (string)$tramite_id,
            'tramiteType' => 'Transferencia Barco',
            'customerName' => (string)$customer_name,
            'customerDni' => (string)$customer_dni,
            'customerEmail' => (string)$customer_email,
            'customerPhone' => (string)$customer_phone,
            'vehicleType' => (string)$vehicle_type,
            'manufacturer' => (string)($no_encuentro ? $manual_manufacturer : $manufacturer),
            'model' => (string)($no_encuentro ? $manual_model : $model),
            'matriculationDate' => (string)($no_encuentro ? '' : $matriculation_date),
            'purchasePrice' => (string)floatval($purchase_price),
            'region' => (string)$region,
            'nuevoNombre' => (string)$nuevo_nombre,
            'nuevoPuerto' => (string)$nuevo_puerto,
            'couponUsed' => (string)$coupon_used,
            'finalAmount' => (string)floatval($final_amount),
            'current_transfer_tax' => (string)floatval($current_transfer_tax),
            'current_extra_fee' => (string)floatval($current_extra_fee),
            'tasas_hidden' => (string)floatval($tasas_hidden),
            'iva_hidden' => (string)floatval($iva_hidden),
            'honorarios_hidden' => (string)floatval($honorarios_hidden),
            'honorariosNetos' => (string)$honorarios_netos_calc,
            'paymentIntentId' => (string)$payment_intent_id,
            // Información ITP con nombres correctos para webhook
            'itp_paid' => $itp_pagado !== null ? ($itp_pagado ? 'true' : 'false') : '',
            'itp_management_option' => (string)$itp_gestion,
            'itp_payment_method' => (string)$itp_metodo_pago,
            'itp_amount' => (string)floatval($itp_amount),
            'itp_commission' => (string)floatval($itp_comision),
            'itp_total_amount' => (string)floatval($itp_total),
            // Extras
            'cambioLista' => $cambio_lista ? 'true' : 'false',
            'cambioListaPrecio' => (string)($cambio_lista ? 64.95 : 0),
            'cambioNombre' => isset($_POST['cambioNombre']) ? 'true' : 'false',
            'cambioNombrePrecio' => isset($_POST['cambioNombre']) ? '40.00' : '0',
            'cambioPuerto' => isset($_POST['cambioPuerto']) ? 'true' : 'false',
            'cambioPuertoPrecio' => isset($_POST['cambioPuerto']) ? '40.00' : '0',
            'vehicleType' => 'Embarcación',
            'status' => 'pending'
        ), $file_fields);

        tpb_debug_log('[TPM] Datos a enviar: tramiteId=' . $tramite_id . ', customerName=' . $customer_name . ', finalAmount=' . $final_amount);
        
        // 🔍 DEBUG DATOS WEBHOOK ANTES DE ENVIAR
        debug_webhook_data($form_data, 'BEFORE_WEBHOOK_SEND');

        // Enviar con cURL (multipart/form-data para archivos)
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $tramitfy_api_url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $form_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        tpb_debug_log('[TPM] Webhook HTTP ' . $http_code . ' - Response: ' . $response);
    
        // Parsear la respuesta para obtener el ID del tracking
        $response_data = json_decode($response, true);
        $tracking_id = isset($response_data['id']) ? $response_data['id'] : time();
        $tracking_url = 'https://46-202-128-35.sslip.io/seguimiento/' . $tracking_id;
        tpb_debug_log('[TPM] Tracking URL: ' . $tracking_url);
    
        // LÓGICA EMAIL: Evaluar condición en EL CONTEXTO CORRECTO
        $email_condition_correct = ($itp_gestion === 'gestionan-ustedes' && $itp_metodo_pago === 'transferencia');
        error_log('=== EMAIL CONDITION FINAL CONTEXT DEBUG ===');
        error_log('CONTEXTO CORRECTO - $itp_gestion: [' . $itp_gestion . ']');
        error_log('CONTEXTO CORRECTO - $itp_metodo_pago: [' . $itp_metodo_pago . ']');
        error_log('EMAIL CONDITION FINAL: ' . ($email_condition_correct ? 'TRUE - PAGO FRACCIONADO' : 'FALSE - PAGO NORMAL'));
        error_log('=== EMAIL CONDITION FINAL DEBUG END ===');
        
        // Enviar email al cliente con el link de tracking
        $subject_customer = 'Trámite Registrado - Siga su Transferencia [DEBUG: ' . ($email_condition_correct ? 'FRACCIONADO' : 'NORMAL') . ']';
        $display_manufacturer = $no_encuentro ? $manual_manufacturer : $manufacturer;
        $display_model = $no_encuentro ? $manual_model : $model;
        $message_customer = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        </head>
        <body style='margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif; background-color: #f4f7fa;'>
            <table width='100%' cellpadding='0' cellspacing='0' style='background-color: #f4f7fa; padding: 40px 20px;'>
                <tr>
                    <td align='center'>
                        <table width='600' cellpadding='0' cellspacing='0' style='background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.08);'>

                            <!-- Header -->
                            <tr>
                                <td style='background: linear-gradient(135deg, #0066cc 0%, #004a99 100%); padding: 40px 40px 35px; text-align: center;'>
                                    <h1 style='margin: 0; color: #ffffff; font-size: 26px; font-weight: 600; letter-spacing: -0.5px;'>TRAMITFY</h1>
                                    <p style='margin: 8px 0 0; color: rgba(255,255,255,0.9); font-size: 14px; font-weight: 400;'>Gestión de Trámites Marítimos</p>
                                </td>
                            </tr>

                            <!-- Confirmación -->
                            <tr>
                                <td style='padding: 40px 40px 30px;'>
                                    <div style='background-color: #e8f5e9; border-left: 4px solid #4caf50; padding: 16px 20px; border-radius: 4px; margin-bottom: 30px;'>
                                        <p style='margin: 0; color: #2e7d32; font-size: 15px; font-weight: 600;'>Trámite registrado correctamente</p>
                                    </div>

                                    <p style='margin: 0 0 20px; color: #333; font-size: 15px; line-height: 1.6;'>
                                        Estimado/a <strong>{$customer_name}</strong>,
                                    </p>
                                    <p style='margin: 0 0 30px; color: #555; font-size: 15px; line-height: 1.7;'>
                                        Su solicitud de transferencia ha sido registrada en nuestro sistema. Nuestro equipo comenzará a tramitar su solicitud en breve.
                                    </p>

                                    <!-- Resumen Vehículo -->
                                    <table width='100%' cellpadding='0' cellspacing='0' style='background-color: #f8f9fa; border-radius: 8px; margin-bottom: 25px; overflow: hidden;'>
                                        <tr>
                                            <td style='padding: 20px 24px;'>
                                                <h3 style='margin: 0 0 16px; color: #0066cc; font-size: 16px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;'>Vehículo</h3>
                                                <table width='100%' cellpadding='6' cellspacing='0'>
                                                    <tr>
                                                        <td style='color: #666; font-size: 14px; padding: 6px 0; width: 35%;'>Tipo:</td>
                                                        <td style='color: #333; font-size: 14px; padding: 6px 0; font-weight: 600;'>{$vehicle_type}</td>
                                                    </tr>
                                                    <tr>
                                                        <td style='color: #666; font-size: 14px; padding: 6px 0;'>Marca/Modelo:</td>
                                                        <td style='color: #333; font-size: 14px; padding: 6px 0; font-weight: 600;'>{$display_manufacturer} {$display_model}</td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                    </table>

                                    <!-- Seguimiento -->
                                    <div style='background-color: #e3f2fd; border-radius: 8px; padding: 24px; margin-bottom: 30px; text-align: center;'>
                                        <p style='margin: 0 0 12px; color: #1565c0; font-size: 15px; font-weight: 600;'>
                                            Número de Trámite
                                        </p>
                                        <p style='margin: 0 0 20px; color: #0d47a1; font-size: 20px; font-weight: 700; letter-spacing: 0.5px;'>
                                            {$tramite_id}
                                        </p>
                                        <p style='margin: 0 0 16px; color: #555; font-size: 14px;'>
                                            Puede consultar el estado de su trámite en cualquier momento:
                                        </p>
                                        <a href='{$tracking_url}' style='display: inline-block; background: linear-gradient(135deg, #1976d2 0%, #1565c0 100%); color: white; padding: 14px 32px; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 14px; box-shadow: 0 3px 8px rgba(25,118,210,0.3); margin-bottom: 16px;'>
                                            Ver Estado del Trámite
                                        </a>
                                        <p style='margin: 0; color: #777; font-size: 13px; line-height: 1.5;'>
                                            O copie este enlace:<br>
                                            <span style='color: #1565c0; font-size: 12px;'>{$tracking_url}</span>
                                        </p>
                                    </div>

                                    <!-- Próximos Pasos -->
                                    <table width='100%' cellpadding='0' cellspacing='0' style='margin-bottom: 25px;'>
                                        <tr>
                                            <td>
                                                <h3 style='margin: 0 0 16px; color: #333; font-size: 16px; font-weight: 600;'>Próximos pasos:</h3>
                                                <table width='100%' cellpadding='8' cellspacing='0'>
                                                    <tr>
                                                        <td style='padding: 10px 0; border-bottom: 1px solid #e0e0e0;'>
                                                            <span style='color: #1976d2; font-weight: 700; font-size: 14px; margin-right: 10px;'>1.</span>
                                                            <span style='color: #555; font-size: 14px;'>Revisaremos su documentación</span>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style='padding: 10px 0; border-bottom: 1px solid #e0e0e0;'>
                                                            <span style='color: #1976d2; font-weight: 700; font-size: 14px; margin-right: 10px;'>2.</span>
                                                            <span style='color: #555; font-size: 14px;'>Tramitaremos la transferencia ante los organismos competentes</span>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style='padding: 10px 0;'>
                                                            <span style='color: #1976d2; font-weight: 700; font-size: 14px; margin-right: 10px;'>3.</span>
                                                            <span style='color: #555; font-size: 14px;'>Le enviaremos la documentación final</span>
                                                        </td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                    </table>

                                    <!-- Información de Pago según método seleccionado -->
                                    " . ($email_condition_correct ? "
                                    <div style='background-color: #e8f5e9; border: 3px solid #4caf50; border-radius: 12px; padding: 25px; margin-bottom: 30px;'>
                                        <h3 style='margin: 0 0 20px; color: #2e7d32; font-size: 20px; font-weight: 700; text-align: center; border-bottom: 2px solid #4caf50; padding-bottom: 12px;'>
                                            💳🏦 PAGO FRACCIONADO - DESGLOSE COMPLETO
                                        </h3>
                                        
                                        <!-- Resumen de pagos MUY CLARO -->
                                        <div style='background-color: #ffffff; padding: 20px; border-radius: 10px; margin-bottom: 20px; border: 2px solid #c8e6c9; box-shadow: 0 2px 4px rgba(0,0,0,0.1);'>
                                            <h4 style='margin: 0 0 18px; color: #1b5e20; font-size: 16px; font-weight: 700; text-align: center; text-transform: uppercase; background-color: #f1f8e9; padding: 10px; border-radius: 6px;'>📊 RESUMEN DE PAGOS</h4>
                                            
                                            <!-- Lo que YA pagó -->
                                            <div style='background-color: #e8f5e9; padding: 18px; border-radius: 8px; margin-bottom: 15px; border: 2px solid #4caf50;'>
                                                <p style='margin: 0 0 6px; color: #2e7d32; font-size: 14px; font-weight: 700; text-transform: uppercase;'>✅ YA PAGADO CON TARJETA</p>
                                                <p style='margin: 0 0 10px; color: #1b5e20; font-size: 28px; font-weight: 700;'>174,99 €</p>
                                                <p style='margin: 0; color: #4caf50; font-size: 13px; font-weight: 600;'><strong>Incluye:</strong> Tasas DGMM (19,05€) + Honorarios gestión (155,94€)</p>
                                            </div>
                                            
                                            <!-- Lo que FALTA por pagar -->
                                            <div style='background-color: #fff8e1; padding: 18px; border-radius: 8px; margin-bottom: 15px; border: 2px solid #ff9800;'>
                                                <p style='margin: 0 0 6px; color: #ef6c00; font-size: 14px; font-weight: 700; text-transform: uppercase;'>⏳ PENDIENTE - DEBE TRANSFERIR</p>
                                                <p style='margin: 0 0 10px; color: #e65100; font-size: 28px; font-weight: 700;'>" . number_format($current_transfer_tax, 2, ',', '.') . " €</p>
                                                <p style='margin: 0; color: #ff9800; font-size: 13px; font-weight: 600;'><strong>Concepto:</strong> ITP (Impuesto de Transmisiones Patrimoniales)</p>
                                            </div>
                                            
                                            <!-- Total -->
                                            <div style='background-color: #e3f2fd; padding: 18px; border-radius: 8px; border: 2px solid #2196f3; text-align: center;'>
                                                <p style='margin: 0 0 6px; color: #1565c0; font-size: 14px; font-weight: 700; text-transform: uppercase;'>💰 TOTAL DEL TRÁMITE</p>
                                                <p style='margin: 0; color: #0d47a1; font-size: 24px; font-weight: 700;'>" . number_format(174.99 + $current_transfer_tax, 2, ',', '.') . " €</p>
                                            </div>
                                        </div>
                                        
                                        <!-- Instrucciones transferencia MUY CLARAS -->
                                        <div style='background-color: #fff3e0; padding: 20px; border-radius: 10px; margin-bottom: 20px; border: 2px solid #ff9800;'>
                                            <h4 style='margin: 0 0 15px; color: #ef6c00; font-size: 16px; font-weight: 700; text-align: center; text-transform: uppercase; background-color: #ffcc02; color: #000; padding: 10px; border-radius: 6px;'>🏦 INSTRUCCIONES DE TRANSFERENCIA</h4>
                                            
                                            <div style='background-color: #ffffff; padding: 16px; border-radius: 8px; margin-bottom: 15px; border: 1px solid #e0e0e0;'>
                                                <h5 style='margin: 0 0 10px; color: #333; font-size: 14px; font-weight: 700;'>DATOS BANCARIOS - CAIXABANK:</h5>
                                                <table style='width: 100%; border-collapse: collapse;'>
                                                    <tr style='border-bottom: 1px solid #f0f0f0;'>
                                                        <td style='padding: 6px 0; font-weight: 600; color: #555; width: 30%;'>Titular:</td>
                                                        <td style='padding: 6px 0; color: #333;'>TRAMITFY S.L.</td>
                                                    </tr>
                                                    <tr style='border-bottom: 1px solid #f0f0f0;'>
                                                        <td style='padding: 6px 0; font-weight: 600; color: #555;'>IBAN:</td>
                                                        <td style='padding: 6px 0; color: #333; font-family: monospace; font-size: 14px; font-weight: 700;'>ES32 2100 0497 8602 0069 7111</td>
                                                    </tr>
                                                    <tr style='border-bottom: 1px solid #f0f0f0;'>
                                                        <td style='padding: 6px 0; font-weight: 600; color: #555;'>SWIFT/BIC:</td>
                                                        <td style='padding: 6px 0; color: #333; font-family: monospace; font-weight: 700;'>CAIXESBBXXX</td>
                                                    </tr>
                                                    <tr>
                                                        <td style='padding: 6px 0; font-weight: 600; color: #555;'>Concepto:</td>
                                                        <td style='padding: 6px 0; color: #333; font-weight: 700;'>ITP Trámite {$tramite_id}</td>
                                                    </tr>
                                                </table>
                                            </div>
                                            
                                            <div style='background-color: #e65100; color: white; padding: 15px; border-radius: 8px; text-align: center;'>
                                                <p style='margin: 0 0 8px; font-size: 16px; font-weight: 700;'>⚠️ ACCIÓN REQUERIDA</p>
                                                <p style='margin: 0; font-size: 14px;'>Debe transferir <strong>" . number_format($current_transfer_tax, 2, ',', '.') . " €</strong> para completar el pago del ITP</p>
                                            </div>
                                        </div>
                                        
                                        <div style='background-color: #ffebee; padding: 15px; border-radius: 8px; border-left: 4px solid #d32f2f; margin-bottom: 20px;'>
                                            <p style='margin: 0; color: #d32f2f; font-size: 14px; line-height: 1.5; font-weight: 600;'>
                                                <strong>📌 IMPORTANTE:</strong> Sin la transferencia del ITP no podremos procesar completamente su trámite. Una vez realizada la transferencia, su trámite continuará automáticamente.
                                            </p>
                                        </div>
                                    </div>
                                    " : "
                                    <!-- Pago único con tarjeta -->
                                    <div style='background-color: #e3f2fd; border: 2px solid #2196f3; border-radius: 8px; padding: 20px; margin-bottom: 25px;'>
                                        <h3 style='margin: 0 0 16px; color: #1565c0; font-size: 16px; font-weight: 700; display: flex; align-items: center;'>
                                            💳 Pago Completado con Tarjeta
                                        </h3>
                                        <div style='background-color: #f1f8ff; padding: 16px; border-radius: 6px; margin-bottom: 16px;'>
                                            <p style='margin: 0 0 8px; color: #1565c0; font-size: 14px; font-weight: 600;'>✅ Importe total abonado:</p>
                                            <p style='margin: 0 0 12px; color: #333; font-size: 18px; font-weight: 700;'>" . number_format($final_amount, 2, ',', '.') . " €</p>
                                            <p style='margin: 0; color: #555; font-size: 13px;'>Incluye: Tasas DGMM, Honorarios, IVA e ITP (si aplica)</p>
                                        </div>
                                        <p style='margin: 0; color: #1565c0; font-size: 13px; line-height: 1.5; background-color: #e8f4fd; padding: 12px; border-radius: 4px; border-left: 3px solid #2196f3;'>
                                            <strong>✨ Perfecto:</strong> Su pago ha sido procesado correctamente. No necesita realizar ninguna acción adicional de pago.
                                        </p>
                                    </div>
                                    ") . "

                                    <p style='margin: 0; color: #666; font-size: 13px; line-height: 1.6; padding: 16px; background-color: #fff3cd; border-left: 3px solid #ffc107; border-radius: 4px;'>
                                        <strong>Importante:</strong> Le notificaremos por email cualquier actualización o si necesitamos información adicional.
                                    </p>

                                </td>
                            </tr>

                            <!-- Footer -->
                            <tr>
                                <td style='background-color: #f8f9fa; padding: 30px 40px; text-align: center; border-top: 1px solid #e0e0e0;'>
                                    <p style='margin: 0 0 8px; color: #666; font-size: 13px;'>
                                        Gracias por confiar en nosotros
                                    </p>
                                    <p style='margin: 0; color: #0066cc; font-size: 15px; font-weight: 600;'>
                                        Equipo TRAMITFY
                                    </p>
                                    <p style='margin: 16px 0 0; color: #999; font-size: 12px;'>
                                        Este correo es informativo. Por favor, no responda a este mensaje.
                                    </p>
                                </td>
                            </tr>

                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>";
    
        $headers_customer = array('Content-Type: text/html; charset=UTF-8', 'From: Tramitfy <info@tramitfy.es>');
        tpb_debug_log('[TPM] Enviando email con tracking al cliente: ' . $customer_email);
        $tracking_mail_result = wp_mail($customer_email, $subject_customer, $message_customer, $headers_customer);
        tpb_debug_log('[TPM] Email tracking enviado: ' . ($tracking_mail_result ? 'SI' : 'NO'));
        
        // TAMBIÉN enviar copia del email con tracking a ipmgroup24@gmail.com
        $admin_copy_email = 'ipmgroup24@gmail.com';
        $subject_admin_copy = '[COPIA] ' . $subject_customer . ' - Cliente: ' . $customer_name;
        $admin_copy_result = wp_mail($admin_copy_email, $subject_admin_copy, $message_customer, $headers_customer);
        tpb_debug_log('[TPM] Copia tracking enviada a admin: ' . ($admin_copy_result ? 'SI' : 'NO'));
    
        // RESPONDER AL CLIENTE CON LA URL DE TRACKING
        tpb_debug_log('[TPM] Enviando respuesta JSON al cliente');
        wp_send_json_success(array(
            'message' => 'Formulario procesado correctamente',
            'tramite_id' => $tramite_id,
            'tracking_id' => $tracking_id,
            'tracking_url' => $tracking_url
        ));
        tpb_debug_log('[TPM] FIN tpb_submit_form');

    } catch (Exception $e) {
        tpb_debug_log('[TPM] ERROR CRÍTICO: ' . $e->getMessage());
        tpb_debug_log('[TPM] Archivo: ' . $e->getFile() . ' Línea: ' . $e->getLine());
        tpb_debug_log('[TPM] Stack trace: ' . $e->getTraceAsString());
        wp_send_json_error([
            'message' => 'Error procesando formulario: ' . $e->getMessage(),
            'file' => basename($e->getFile()),
            'line' => $e->getLine(),
            'debug' => WP_DEBUG ? $e->getTraceAsString() : ''
        ]);
    }

    wp_die();
}

// NUEVO: Script de procesamiento asíncrono para barcos
function tpb_process_async($async_file) {
    if (!file_exists($async_file)) {
        error_log("Archivo async no encontrado: $async_file");
        return;
    }

    $data = json_decode(file_get_contents($async_file), true);
    if (!$data) {
        error_log("Error decodificando datos async");
        unlink($async_file);
        return;
    }

    error_log("=== PROCESAMIENTO ASYNC BARCO: {$data['tramite_id']} ===");

    // Extraer variables
    extract($data);
    // Email del administrador para recibir notificaciones
    $admin_email = 'ipmgroup24@gmail.com';
    $upload_dir = wp_upload_dir();

    $headers = [
        'Content-Type: text/html; charset=UTF-8',
        'From: info@tramitfy.es'
    ];
    $subject_admin = 'Nuevo formulario enviado';

    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head><meta charset="UTF-8"></head>
    <body style="font-family: Arial, sans-serif;">
    <div style="max-width:600px;margin:auto;padding:20px;background:#f9f9f9;border:1px solid #e0e0e0;border-radius:10px;">
        <div style="text-align:center;">
            <img src="https://www.tramitfy.es/wp-content/uploads/LOGO.png" alt="Tramitfy Logo" style="max-width:200px;">
            <h2 style="color:#016d86;">Nuevo Formulario Enviado</h2>
        </div>
        <div style="background:#fff;padding:20px;border-radius:8px;">
            <p>Se ha recibido un nuevo formulario con los siguientes detalles:</p>
            <h3>Datos del Cliente:</h3>
            <table style="width:100%;border-collapse:collapse;">
                <tr>
                    <th style="text-align:left;padding:8px;">Nombre:</th>
                    <td style="padding:8px;"><?php echo esc_html($customer_name); ?></td>
                </tr>
                <tr>
                    <th style="text-align:left;padding:8px;">DNI:</th>
                    <td style="padding:8px;"><?php echo esc_html($customer_dni); ?></td>
                </tr>
                <tr>
                    <th style="text-align:left;padding:8px;">Email:</th>
                    <td style="padding:8px;"><?php echo esc_html($customer_email); ?></td>
                </tr>
                <tr>
                    <th style="text-align:left;padding:8px;">Teléfono:</th>
                    <td style="padding:8px;"><?php echo esc_html($customer_phone); ?></td>
                </tr>
            </table>
            <h3>Datos del Vehículo:</h3>
            <table style="width:100%;border-collapse:collapse;">
                <tr>
                    <th style="text-align:left;padding:8px;">Tipo de Vehículo:</th>
                    <td style="padding:8px;"><?php echo esc_html($vehicle_type); ?></td>
                </tr>
                <?php if (!$no_encuentro) : ?>
                    <tr>
                        <th style="text-align:left;padding:8px;">Fabricante:</th>
                        <td style="padding:8px;"><?php echo esc_html($manufacturer); ?></td>
                    </tr>
                    <tr>
                        <th style="text-align:left;padding:8px;">Modelo:</th>
                        <td style="padding:8px;"><?php echo esc_html($model); ?></td>
                    </tr>
                    <tr>
                        <th style="text-align:left;padding:8px;">Fecha de Matriculación:</th>
                        <td style="padding:8px;"><?php echo esc_html($matriculation_date); ?></td>
                    </tr>
                <?php else: ?>
                    <tr>
                        <th style="text-align:left;padding:8px;">Fabricante (manual):</th>
                        <td style="padding:8px;"><?php echo esc_html($manual_manufacturer); ?></td>
                    </tr>
                    <tr>
                        <th style="text-align:left;padding:8px;">Modelo (manual):</th>
                        <td style="padding:8px;"><?php echo esc_html($manual_model); ?></td>
                    </tr>
                    <tr>
                        <th style="text-align:left;padding:8px;">Fecha de Matriculación:</th>
                        <td style="padding:8px;">(no requerida)</td>
                    </tr>
                <?php endif; ?>
                <tr>
                    <th style="text-align:left;padding:8px;">Precio de Compra:</th>
                    <td style="padding:8px;"><?php echo number_format($purchase_price, 2, ',', '.'); ?> €</td>
                </tr>
                <tr>
                    <th style="text-align:left;padding:8px;">Comunidad Autónoma:</th>
                    <td style="padding:8px;"><?php echo esc_html($region); ?></td>
                </tr>
                <?php if (!empty($coupon_used)): ?>
                    <tr>
                        <th style="text-align:left;padding:8px;">Cupón utilizado:</th>
                        <td style="padding:8px;"><?php echo esc_html($coupon_used); ?></td>
                    </tr>
                <?php endif; ?>
                <?php if (!empty($nuevo_nombre)): ?>
                    <tr>
                        <th style="text-align:left;padding:8px;">Nuevo Nombre:</th>
                        <td style="padding:8px;"><?php echo esc_html($nuevo_nombre); ?></td>
                    </tr>
                <?php endif; ?>
                <?php if (!empty($nuevo_puerto)): ?>
                    <tr>
                        <th style="text-align:left;padding:8px;">Nuevo Puerto:</th>
                        <td style="padding:8px;"><?php echo esc_html($nuevo_puerto); ?></td>
                    </tr>
                <?php endif; ?>
            </table>
            <p>Se adjuntan los documentos proporcionados por el cliente.</p>
        </div>
    </body>
    </html>
    <?php
    $message_admin = ob_get_clean();
    // Enviar email al admin (ipmgroup24@gmail.com)
    wp_mail($admin_email, $subject_admin, $message_admin, $headers, $attachments);
    
    // TAMBIÉN enviar email a ipmgroup24@gmail.com como copia de seguridad
    $backup_admin_email = 'ipmgroup24@gmail.com';
    if ($admin_email !== $backup_admin_email) {
        wp_mail($backup_admin_email, $subject_admin, $message_admin, $headers, $attachments);
        tpb_debug_log('[TPM] Email también enviado a copia: ' . $backup_admin_email);
    }

    /**************************************************/
    /*** [NUEVO] Generar TRÁMITE ID para Transferencia */
    /**************************************************/
    $prefix         = 'TMA-TRANS';
    $counter_option = 'tma_trans_counter';
    $current_cnt    = get_option($counter_option, 0);
    $current_cnt++;
    update_option($counter_option, $current_cnt);

    $date_part   = date('Ymd');
    $secuencial  = str_pad($current_cnt, 6, '0', STR_PAD_LEFT);
    $tramite_id  = $prefix . '-' . $date_part . '-' . $secuencial;

    /*******************************************************/
    /*** [AÑADIR] Subida de archivos a Google Drive (API) ***/
    /*******************************************************/
    require_once __DIR__ . '/vendor/autoload.php'; // Ajusta la ruta a tu autoload/credenciales

    $googleCredentialsPath = __DIR__ . '/credentials.json'; // Ajusta a tu archivo de credenciales
    $client = new Google_Client();
    $client->setAuthConfig($googleCredentialsPath);
    $client->addScope(Google_Service_Drive::DRIVE_FILE);

    $driveService = new Google_Service_Drive($client);

    // ID de la carpeta "padre" en Drive donde se crearán subcarpetas mensuales
    $parentFolderId = '1vxHdQImalnDVI7aTaE0cGIX7m-7pl7sr'; // Ajustar con la carpeta real

    // Crear o reutilizar la carpeta AAAA-MM
    $yearMonth = date('Y-m');
    try {
        $query = sprintf(
            "name = '%s' and '%s' in parents and mimeType = 'application/vnd.google-apps.folder' and trashed=false",
            $yearMonth,
            $parentFolderId
        );
        $response = $driveService->files->listFiles([
            'q' => $query,
            'spaces' => 'drive',
            'fields' => 'files(id, name)'
        ]);

        if (count($response->files) > 0) {
            // Ya existe la carpeta
            $folderId = $response->files[0]->id;
        } else {
            // Crearla
            $folderMetadata = new Google_Service_Drive_DriveFile([
                'name' => $yearMonth,
                'mimeType' => 'application/vnd.google-apps.folder',
                'parents' => [$parentFolderId]
            ]);
            $createdFolder = $driveService->files->create($folderMetadata, ['fields' => 'id']);
            $folderId = $createdFolder->id;
        }
    } catch (Exception $e) {
        $folderId = null;
    }

    // Subir los archivos a la carpeta mensual
    $uploadedDriveLinks = [];
    if ($folderId && !empty($attachments)) {
        foreach ($attachments as $filePath) {
            if (!file_exists($filePath)) {
                continue;
            }
            $fileName  = basename($filePath);
            $driveFile = new Google_Service_Drive_DriveFile([
                'name'    => $fileName,
                'parents' => [$folderId]
            ]);

            try {
                $fileContent = file_get_contents($filePath);
                $createdFile = $driveService->files->create($driveFile, [
                    'data' => $fileContent,
                    'mimeType' => mime_content_type($filePath),
                    'uploadType' => 'multipart',
                    'fields' => 'id, webViewLink'
                ]);

                // Dar permiso de lectura a "anyone"
                $permission = new Google_Service_Drive_Permission();
                $permission->setType('anyone');
                $permission->setRole('reader');
                $driveService->permissions->create($createdFile->id, $permission);

                $uploadedDriveLinks[] = $createdFile->webViewLink;
            } catch (Exception $e) {
                // Manejo de error en subida
            }
        }
    }

    /*****************************************/
    /*** [AÑADIR] Escritura en Google Sheets ***/
    /*****************************************/
    try {
        $sheetsClient = new Google_Client();
        $sheetsClient->setAuthConfig($googleCredentialsPath);
        $sheetsClient->addScope(Google_Service_Sheets::SPREADSHEETS);

        $sheetsService = new Google_Service_Sheets($sheetsClient);

        // ID de tu hoja de cálculo
        $spreadsheetId = '1APFnwJ3yBfxt1M4JJcfPLOQkdIF27OXAzubW1Bx9ZbA'; 

        // --- Opción 1: Se conserva la hoja "DATABASE" (opcional) ---
        $rangeDatabase = 'DATABASE!A1';
        $fecha      = date('d/m/Y');
        $driveLinks = implode("\n", $uploadedDriveLinks);
        $clientData = "Nombre: $customer_name\nDNI: $customer_dni\nEmail: $customer_email\nTlf: $customer_phone";
        $iva        = $iva_hidden;
        $tasas      = $tasas_hidden;
        $honorarios = $honorarios_hidden;
        if ($no_encuentro) {
            $fabricanteReal = "Fabricante (manual): $manual_manufacturer";
            $modeloReal     = "Modelo (manual): $manual_model";
            $fechaMatri     = "(no requerida)";
        } else {
            $fabricanteReal = "Fabricante: $manufacturer";
            $modeloReal     = "Modelo: $model";
            $fechaMatri     = "Fecha Matric.: $matriculation_date";
        }
        $boatData  = "Tipo: $vehicle_type\n";
        $boatData .= "$fabricanteReal\n$modeloReal\n";
        $boatData .= "$fechaMatri\n";
        $boatData .= "Precio Compra: $purchase_price\n";
        $boatData .= "Región: $region\n";
        if (!empty($nuevo_nombre))  $boatData .= "Nuevo Nombre: $nuevo_nombre\n";
        if (!empty($nuevo_puerto))  $boatData .= "Nuevo Puerto: $nuevo_puerto\n";
        $visitors  = "";
        $rowValuesDatabase = [
            $tramite_id,
            $clientData,
            $boatData,
            "IMPORTE TOTAL: $final_amount\nITP: $current_transfer_tax\nTASAS: $tasas\nIVA: $iva\nHONORARIOS: $honorarios\nCOMISION BANCARIA: $current_extra_fee\nCUPON USADO: $coupon_used",
            $visitors,
            $driveLinks
        ];
        $bodyDatabase = new Google_Service_Sheets_ValueRange(['values' => [$rowValuesDatabase]]);
        $paramsDatabase = ['valueInputOption' => 'USER_ENTERED'];
        $sheetsService->spreadsheets_values->append($spreadsheetId, $rangeDatabase, $bodyDatabase, $paramsDatabase);

        // --- Opción 2: Hoja "OrganizedData" con cada dato en su propia columna ---
        $newSheetTitle = 'OrganizedData';

        // Verificar si la hoja ya existe; si no, crearla.
        $spreadsheetObj = $sheetsService->spreadsheets->get($spreadsheetId);
        $sheetExists = false;
        foreach ($spreadsheetObj->getSheets() as $sheet) {
            if ($sheet->getProperties()->getTitle() == $newSheetTitle) {
                $sheetExists = true;
                break;
            }
        }
        if (!$sheetExists) {
            $addSheetRequest = new Google_Service_Sheets_Request([
                'addSheet' => [
                    'properties' => [
                        'title' => $newSheetTitle
                    ]
                ]
            ]);
            $batchUpdateRequest = new Google_Service_Sheets_BatchUpdateSpreadsheetRequest([
                'requests' => [$addSheetRequest]
            ]);
            $sheetsService->spreadsheets->batchUpdate($spreadsheetId, $batchUpdateRequest);
        }

        // Preparar la fila con datos individuales:
        $organizedRow = [
            $tramite_id,                                   // Número de trámite
            $customer_name,                                // Nombre
            $customer_dni,                                 // DNI
            $customer_email,                               // Email
            $customer_phone,                               // Teléfono
            $vehicle_type,                                 // Tipo de vehículo
            ($no_encuentro ? $manual_manufacturer : $manufacturer),  // Fabricante
            ($no_encuentro ? $manual_model : $model),                  // Modelo
            ($no_encuentro ? '' : $matriculation_date),                // Fecha de matriculación
            $purchase_price,                               // Precio de compra
            $region,                                       // Comunidad Autónoma
            $coupon_used,                                  // Cupón aplicado
            $nuevo_nombre,                                 // Nuevo nombre (si se indicó)
            $nuevo_puerto,                                 // Nuevo puerto (si se indicó)
            $final_amount,                                 // Importe total
            $current_transfer_tax,                         // ITP
            $tasas_hidden,                                 // Tasas
            $iva_hidden,                                   // IVA
            $honorarios_hidden                             // Honorarios
        ];
        // Ahora, agregamos en columnas separadas cada uno de los documentos subidos (incluido el PDF con la firma)
        // Se asignarán de forma genérica (Documento 1, Documento 2, etc.) según el orden en el array $uploadedDriveLinks
        foreach ($uploadedDriveLinks as $docLink) {
            $organizedRow[] = $docLink;
        }

        $rangeOrganized = $newSheetTitle . '!A1';
        $bodyOrganized = new Google_Service_Sheets_ValueRange(['values' => [$organizedRow]]);
        $paramsOrganized = ['valueInputOption' => 'USER_ENTERED'];
        $sheetsService->spreadsheets_values->append($spreadsheetId, $rangeOrganized, $bodyOrganized, $paramsOrganized);

    } catch (Exception $e) {
        // Manejo de error en Sheets (no detiene el flujo principal)
    }

    /*****************************************/
    /*** TRAMITFY WEBHOOK INTEGRATION ***/
    /*****************************************/
    $tramitfy_api_url = 'https://46-202-128-35.sslip.io/api/herramientas/barcos/webhook';

    // Preparar archivos para enviar
    $file_fields = array();
    if (!empty($attachments)) {
        // Mapear archivos a nombres específicos que espera el webhook
        $file_mapping = [
            0 => 'upload_autorizacion_pdf',  // Primer archivo: PDF de autorización generado
            1 => 'upload_dni_comprador',     // Segundo archivo: DNI comprador
            2 => 'upload_dni_vendedor',      // Tercer archivo: DNI vendedor  
            3 => 'upload_registro_maritimo',      // Cuarto archivo: Registro marítimo
            4 => 'upload_hoja_asiento',      // Quinto archivo: Hoja de asiento
            5 => 'upload_contrato_compraventa', // Sexto archivo: Contrato
            6 => 'upload_itp_comprobante',   // Séptimo archivo: Modelo 620 ITP
        ];
        
        foreach ($attachments as $index => $file_path) {
            if (file_exists($file_path)) {
                $cfile = new CURLFile($file_path, mime_content_type($file_path), basename($file_path));
                $field_name = isset($file_mapping[$index]) ? $file_mapping[$index] : "upload_otros_$index";
                $file_fields[$field_name] = $cfile;
            }
        }
    }

    // Obtener directorio de uploads para generar URLs correctas
    $upload_dir = wp_upload_dir();
    
    // Preparar datos del formulario
    $form_data = array_merge(array(
        'tramiteId' => $tramite_id,
        'tramiteType' => 'Transferencia Barco',
        'customerName' => $customer_name,
        'customerDni' => $customer_dni,
        'customerEmail' => $customer_email,
        'customerPhone' => $customer_phone,
        'vehicleType' => $vehicle_type,
        'manufacturer' => $no_encuentro ? $manual_manufacturer : $manufacturer,
        'model' => $no_encuentro ? $manual_model : $model,
        'matriculationDate' => $no_encuentro ? '' : $matriculation_date,
        'purchasePrice' => floatval($purchase_price),
        'region' => $region,
        'nuevoNombre' => $nuevo_nombre,
        'nuevoPuerto' => $nuevo_puerto,
        'couponUsed' => $coupon_used,
        'finalAmount' => floatval($final_amount),
        'transferTax' => floatval($tasas_hidden),
        'extraFee' => floatval($current_extra_fee),
        'tasas' => floatval($tasas_hidden),
        'iva' => floatval($iva_hidden),
        'honorarios' => floatval($honorarios_hidden),
        'authorizationPdfUrl' => !empty($attachments) && file_exists($attachments[0]) ? $upload_dir['url'] . '/' . basename($attachments[0]) : '',
        'driveLinks' => json_encode(isset($uploadedDriveLinks) ? $uploadedDriveLinks : array())
    ), $file_fields);

    // Enviar con cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $tramitfy_api_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $form_data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $tramitfy_response = curl_exec($ch);
    $tramitfy_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Log para debugging (opcional)
    error_log('TRAMITFY Response (' . $tramitfy_http_code . '): ' . $tramitfy_response);

    wp_send_json_success('Formulario procesado correctamente.');
    wp_die();
}

/**
 * Registrar el shortcode [transferencia_barco_form]
 */
add_shortcode('transferencia_barco_form', 'transferencia_barco_shortcode');

/**
 * Registrar AJAX handlers
 */
add_action('wp_ajax_submit_barco_form_tpb', 'tpb_submit_form');
add_action('wp_ajax_nopriv_submit_barco_form_tpb', 'tpb_submit_form');

add_action('wp_ajax_tpb_send_emails_v2', 'tpb_send_emails_v2');
add_action('wp_ajax_nopriv_tpb_send_emails_v2', 'tpb_send_emails_v2');

add_action('wp_ajax_tpb_log_debug', 'tpb_log_debug');
add_action('wp_ajax_nopriv_tpb_log_debug', 'tpb_log_debug');

