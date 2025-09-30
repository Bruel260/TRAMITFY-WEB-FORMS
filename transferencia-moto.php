<?php
/*
Plugin Name: Transferencia Moto de Agua
Description: Formulario de transferencia de barco con Stripe, lógica de cupones y opción para usar solo el precio de compra (sin tablas CSV) cuando el usuario no encuentra su modelo.
Version: 1.1
Author: GPT-4
*/

// Asegurarse de que el archivo no sea accedido directamente
defined('ABSPATH') || exit;

// Configuración Stripe para Transferencia Moto - cambiar 'test' a 'live' para producción
define('MOTO_STRIPE_MODE', 'test'); // 'test' o 'live'
define('STRIPE_PUBLIC_KEY', MOTO_STRIPE_MODE === 'test'
    ? 'pk_test_YOUR_STRIPE_TEST_PUBLIC_KEY'
    : 'pk_live_YOUR_STRIPE_LIVE_PUBLIC_KEY');
define('STRIPE_SECRET_KEY', MOTO_STRIPE_MODE === 'test'
    ? 'sk_test_YOUR_STRIPE_TEST_SECRET_KEY'
    : 'sk_live_YOUR_STRIPE_LIVE_SECRET_KEY');

/**
 * Carga datos desde archivos CSV según el tipo de vehículo
 */
function tpm_cargar_datos_csv($tipo) {
    $archivo_csv = ($tipo === 'Moto de Agua') ? 'MOTO.csv' : 'MOTO.csv';
    $ruta_csv    = get_template_directory() . '/' . $archivo_csv;
    $data        = [];

    if (($handle = fopen($ruta_csv, 'r')) !== false) {
        fgetcsv($handle, 1000, ','); // Se asume que la primera fila es encabezado
        while (($row = fgetcsv($handle, 1000, ',')) !== false) {
            list($fabricante, $modelo, $precio) = $row;
            $data[$fabricante][] = [
                'modelo' => $modelo,
                'precio' => $precio
            ];
        }
        fclose($handle);
    }
    return $data;
}

/**
 * GENERA EL FORMULARIO EN EL FRONTEND
 */
function transferencia_moto_shortcode() {
    // Cargar datos de fabricantes para 'Moto de Agua' inicialmente
    $datos_fabricantes = tpm_cargar_datos_csv('Moto de Agua');

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
            max-width: 1200px;
            margin: 40px auto;
            padding: 35px;
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            font-family: 'Roboto', 'Helvetica Neue', Helvetica, Arial, sans-serif;
            background-color: #ffffff;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.05);
            transition: box-shadow 0.3s ease;
        }
        
        #transferencia-form:hover {
            box-shadow: 0 12px 28px rgba(0, 0, 0, 0.08);
        }
        
        #transferencia-form h2 {
            margin-top: 0;
            margin-bottom: 24px;
            color: rgb(var(--primary));
            font-size: 28px;
            font-weight: 600;
            border-bottom: 2px solid rgba(var(--primary), 0.1);
            padding-bottom: 12px;
        }
        
        #transferencia-form h3 {
            color: rgb(var(--primary-dark));
            font-size: 20px;
            margin-top: 30px;
            margin-bottom: 15px;
            font-weight: 500;
        }
        
        #transferencia-form label {
            font-weight: 500;
            display: block;
            margin-top: 18px;
            margin-bottom: 6px;
            color: #444444;
            font-size: 15px;
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
            padding: 14px;
            margin-top: 6px;
            border-radius: 8px;
            border: 1px solid #d0d0d0;
            font-size: 16px;
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
            padding: 14px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 18px;
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
        
        /* Estilos para el menú de navegación mejorado */
        #form-navigation {
            position: relative;
            padding: 0;
            margin-bottom: 40px;
            border-radius: 0;
            background: none;
            box-shadow: none;
            border: none;
        }
        
        .nav-progress-bar {
            position: absolute;
            top: 30px;
            left: 12%;
            right: 12%;
            height: 4px;
            background-color: rgba(var(--neutral-300), 0.5);
            border-radius: 4px;
            z-index: 1;
        }
        
        .nav-progress-indicator {
            position: absolute;
            top: 0;
            left: 0;
            height: 100%;
            width: 0%; /* Se actualiza con JS */
            background: linear-gradient(90deg, rgb(var(--primary)) 0%, rgb(var(--primary-light)) 100%);
            border-radius: 4px;
            transition: width 0.6s cubic-bezier(0.65, 0, 0.35, 1);
            box-shadow: 0 0 10px rgba(var(--primary), 0.3);
        }
        
        .nav-items-container {
            display: flex;
            justify-content: space-between;
            position: relative;
            z-index: 2;
            width: 90%;
            max-width: 800px;
            margin: 0 auto;
        }
        
        .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            color: rgb(var(--neutral-600));
            font-weight: 500;
            position: relative;
            transition: all 0.3s ease;
            min-width: 80px;
        }
        
        .nav-item-circle {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background-color: white;
            border: 2px solid rgba(var(--neutral-300), 0.6);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 10px;
            position: relative;
            transition: all 0.4s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .nav-item-icon {
            position: absolute;
            font-size: 22px;
            color: rgb(var(--neutral-500));
            transition: all 0.3s ease;
            transform: translateY(0);
            opacity: 1;
        }
        
        .nav-item-number {
            position: absolute;
            font-size: 18px;
            font-weight: 600;
            color: rgb(var(--neutral-500));
            transition: all 0.3s ease;
            transform: translateY(30px);
            opacity: 0;
        }
        
        .nav-item-text {
            font-size: 14px;
            transition: all 0.3s ease;
            text-align: center;
        }
        
        /* Estilos para el ítem activo */
        .nav-item.active {
            color: rgb(var(--primary));
        }
        
        .nav-item.active .nav-item-circle {
            border-color: rgb(var(--primary));
            background-color: rgba(var(--primary), 0.05);
            box-shadow: 0 0 0 4px rgba(var(--primary), 0.1);
            transform: scale(1.1);
        }
        
        .nav-item.active .nav-item-icon {
            color: rgb(var(--primary));
            transform: translateY(-30px);
            opacity: 0;
        }
        
        .nav-item.active .nav-item-number {
            color: rgb(var(--primary));
            transform: translateY(0);
            opacity: 1;
        }
        
        .nav-item.active .nav-item-text {
            font-weight: 600;
        }
        
        /* Estilos para ítems completados */
        .nav-item.completed .nav-item-circle {
            background-color: rgb(var(--primary));
            border-color: rgb(var(--primary));
        }
        
        .nav-item.completed .nav-item-icon, 
        .nav-item.completed .nav-item-number {
            color: white;
        }
        
        /* Efecto hover */
        .nav-item:hover .nav-item-circle {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .nav-item:not(.active):hover .nav-item-icon {
            transform: scale(1.1);
        }
        
        /* Estilos responsivos */
        @media (max-width: 768px) {
            .nav-progress-bar {
                left: 8%;
                right: 8%;
            }
            
            .nav-items-container {
                width: 100%;
            }
            
            .nav-item-circle {
                width: 50px;
                height: 50px;
            }
            
            .nav-item-text {
                font-size: 12px;
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
        
        .button-container {
            display: none;
            flex-wrap: wrap;
            justify-content: space-between;
            margin-top: 40px;
            gap: 15px;
        }
        
        .button-container .button {
            flex: 1 1 auto;
            text-align: center;
            min-width: 120px;
        }
        
        /* Estilo mejorado para la sección de precio */
        .price-details {
            margin-top: 25px;
            font-size: 16px;
            background-color: #fbfbfb;
            padding: 25px;
            border-radius: 12px;
            border: 1px solid #eaeaea;
            box-shadow: 0 3px 10px rgba(0,0,0,0.03);
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
        
        /* Estilos para la cuadrícula de documentos */
        .upload-grid {
            display: flex;
            flex-direction: column;
            gap: 20px;
            margin: 20px 0;
        }
        
        .upload-row {
            display: flex;
            gap: 20px;
            width: 100%;
        }
        
        .upload-row .upload-item {
            flex: 1 1 0;
            min-width: 0;
            margin-bottom: 0;
            min-height: 175px;
            max-height: 350px;
            background-color: #f9f9f9;
            border-radius: var(--radius-md);
            padding: 15px;
            box-shadow: var(--shadow-sm);
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            overflow: hidden;
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
                flex-direction: column;
            }
            
            .button-container .button {
                margin-bottom: 10px;
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
            background-color: rgba(var(--neutral-50), 0.7);
            border-radius: var(--radius-md);
            padding: 18px 20px;
            border: 1px solid rgba(var(--neutral-300), 0.3);
            position: relative;
            margin-bottom: 15px;
        }
        
        .calculation-section h4 {
            margin: 0 0 15px 0;
            font-size: 17px;
            color: rgb(var(--primary));
            padding-bottom: 10px;
            border-bottom: 1px solid rgba(var(--primary), 0.15);
            font-weight: 600;
        }
        
        .calculation-item.highlight-item {
            background-color: rgba(var(--primary), 0.03);
            margin: 15px -10px 0;
            padding: 12px 10px;
            border-radius: var(--radius-md);
            border-left: 3px solid rgba(var(--primary), 0.4);
        }
        
        .calculation-item.highlight-item span:last-child {
            background-color: rgba(var(--primary), 0.1);
            font-weight: 700;
        }
        
        .calculation-item span:last-child {
            font-weight: 600;
            color: rgb(var(--primary-dark));
            background-color: rgba(var(--primary), 0.05);
            padding: 6px 14px;
            border-radius: 6px;
            min-width: 90px;
            text-align: right;
            transition: all 0.2s ease;
        }
        
        .calculation-item:hover span:last-child {
            background-color: rgba(var(--primary), 0.1);
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        .calculation-result {
            display: flex;
            justify-content: space-between;
            font-size: 20px;
            font-weight: 700;
            color: rgb(var(--primary));
            background-color: rgba(var(--primary), 0.1);
            padding: 18px 24px;
            border-radius: var(--radius-md);
            margin-top: 20px;
            box-shadow: 0 4px 8px rgba(var(--primary-dark), 0.1);
            align-items: center;
            position: relative;
            overflow: hidden;
        }
        
        .calculation-result:before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            width: 6px;
            height: 100%;
            background: linear-gradient(to bottom, rgb(var(--primary)), rgb(var(--primary-light)));
        }
        
        .calculation-result:after {
            content: '';
            position: absolute;
            right: -25px;
            top: -25px;
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            z-index: 1;
        }
        
        .calculation-result span:first-child {
            font-size: 18px;
            position: relative;
            z-index: 2;
        }
        
        .calculation-result span:last-child {
            font-size: 24px;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
            position: relative;
            z-index: 2;
            background: none;
            padding: 0;
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
        
        /* Tarjeta principal mejorada */
        .price-summary-card {
            background-color: #ffffff;
            border-radius: var(--radius-lg);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 30px;
            border: 1px solid rgba(var(--neutral-300), 0.5);
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .price-summary-card:hover {
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
            transform: translateY(-3px);
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
        
        /* Sección principal de gestión */
        .price-summary-main {
            padding: 25px 28px;
            border-bottom: 1px solid rgba(var(--neutral-300), 0.3);
        }
        
        .price-summary-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px dashed rgba(var(--neutral-400), 0.3);
        }
        
        .price-summary-title span {
            font-size: 18px;
            font-weight: 600;
            color: rgb(var(--primary-dark));
        }
        
        .price-summary-amount {
            font-size: 20px;
            font-weight: 700;
            color: rgb(var(--primary));
            background-color: rgba(var(--primary), 0.08);
            padding: 8px 16px;
            border-radius: 30px;
        }
        
        .price-summary-details {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .price-summary-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            color: rgb(var(--neutral-700));
        }
        
        .price-summary-row i {
            color: rgb(var(--success));
            margin-right: 8px;
        }
        
        .price-summary-row:not(:last-child) {
            border-bottom: 1px dotted rgba(var(--neutral-400), 0.2);
        }
        
        /* Sección de impuestos */
        .price-summary-tax {
            padding: 25px 28px;
            border-bottom: 1px solid rgba(var(--neutral-300), 0.3);
            background-color: rgba(var(--primary), 0.02);
        }
        
        .price-summary-help {
            margin-top: 15px;
            text-align: center;
        }
        
        .info-button-sm {
            background-color: transparent;
            color: rgb(var(--primary));
            border: 1px solid rgb(var(--primary));
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .info-button-sm:hover {
            background-color: rgba(var(--primary), 0.08);
            transform: translateY(-1px);
        }
        
        /* Acordeón para secciones opcionales */
        .price-summary-accordion {
            border-bottom: 1px solid rgba(var(--neutral-300), 0.3);
        }
        
        .accordion-toggle-header {
            padding: 20px 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            background-color: rgba(var(--neutral-100), 0.5);
            transition: all 0.2s ease;
        }
        
        .accordion-toggle-header:hover {
            background-color: rgba(var(--primary), 0.05);
        }
        
        .accordion-toggle-header span {
            font-weight: 600;
            color: rgb(var(--neutral-700));
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .accordion-toggle-header span i {
            color: rgb(var(--primary));
        }
        
        .accordion-icon {
            color: rgb(var(--primary));
            transition: transform 0.3s ease;
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
            padding: 20px 28px;
        }
        
        /* Servicios adicionales dentro del acordeón */
        .additional-service-item {
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px dashed rgba(var(--neutral-300), 0.4);
        }
        
        .additional-service-item:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }
        
        .service-checkbox {
            display: flex;
            align-items: center;
            cursor: pointer;
            padding: 8px;
            border-radius: var(--radius-md);
            transition: background-color 0.2s ease;
        }
        
        .service-checkbox:hover {
            background-color: rgba(var(--primary), 0.05);
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
            color: rgb(var(--neutral-800));
        }
        
        .service-price {
            font-weight: 600;
            color: rgb(var(--primary));
            background-color: rgba(var(--primary), 0.08);
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 14px;
        }
        
        .additional-input {
            margin: 15px 0 5px 35px;
            animation: fadeIn 0.3s ease;
        }
        
        /* Cupón dentro del acordeón - Rediseño */
        .coupon-container {
            display: flex;
            flex-direction: column;
            gap: 15px;
            background-color: white;
            padding: 20px;
            border-radius: var(--radius-lg);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(var(--primary), 0.12);
            position: relative;
            overflow: hidden;
        }
        
        .coupon-container:before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 5px;
            height: 100%;
            background: linear-gradient(to bottom, rgb(var(--primary)), rgb(var(--primary-light)));
        }
        
        .coupon-title {
            color: rgb(var(--primary));
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 16px;
            padding-bottom: 10px;
            border-bottom: 1px solid rgba(var(--neutral-300), 0.4);
            margin-bottom: 5px;
        }
        
        .coupon-input-wrapper {
            display: flex;
            gap: 12px;
            position: relative;
        }
        
        .coupon-input-wrapper input {
            flex: 1;
            padding: 14px 16px;
            border: 2px solid rgba(var(--neutral-300), 0.8);
            border-radius: var(--radius-md);
            font-size: 15px;
            background-color: white;
            transition: all 0.25s ease;
            box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.05);
        }
        
        .coupon-input-wrapper input:focus {
            border-color: rgb(var(--primary));
            box-shadow: 0 0 0 3px rgba(var(--primary), 0.15);
            outline: none;
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
            background-color: rgb(var(--primary));
            color: white;
            border: none;
            padding: 0 24px;
            border-radius: var(--radius-md);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 3px 6px rgba(var(--primary-dark), 0.3);
        }
        
        .coupon-button:hover {
            background-color: rgb(var(--primary-dark));
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(var(--primary-dark), 0.4);
        }
        
        .coupon-button:active {
            transform: translateY(0);
            box-shadow: 0 2px 4px rgba(var(--primary-dark), 0.3);
        }
        
        .coupon-message {
            padding: 12px 15px;
            border-radius: var(--radius-md);
            font-weight: 500;
            font-size: 14px;
            line-height: 1.4;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .coupon-message.hidden {
            display: none;
        }
        
        .coupon-message.success {
            background-color: rgba(var(--success), 0.1);
            color: rgb(var(--success));
            border: 1px solid rgba(var(--success), 0.3);
        }
        
        .coupon-message.success:before {
            content: '✓';
            font-weight: bold;
            font-size: 16px;
        }
        
        .coupon-message.error-message {
            background-color: rgba(var(--error), 0.1);
            color: rgb(var(--error));
            border: 1px solid rgba(var(--error), 0.3);
        }
        
        .coupon-message.error-message:before {
            content: '!';
            font-weight: bold;
            font-size: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background-color: rgb(var(--error));
            color: white;
        }
            border: 1px solid rgba(var(--error), 0.3);
        }
        
        .coupon-message.loading {
            background-color: rgba(var(--neutral-500), 0.1);
            color: rgb(var(--neutral-600));
            border: 1px solid rgba(var(--neutral-500), 0.3);
        }
        
        /* Total a pagar mejorado */
        .price-summary-total {
            padding: 28px;
            background: linear-gradient(to right, rgba(var(--primary), 0.02), rgba(var(--primary), 0.08));
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .price-summary-total-label {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .price-summary-total-label span {
            font-size: 22px;
            font-weight: 700;
            color: rgb(var(--neutral-800));
        }
        
        .price-summary-guarantees {
            display: flex;
            gap: 15px;
            font-size: 13px;
            color: rgb(var(--neutral-600));
        }
        
        .price-summary-guarantees span {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 13px;
            font-weight: normal;
        }
        
        .price-summary-guarantees i {
            color: rgb(var(--primary));
        }
        
        .price-summary-total-amount {
            font-size: 32px;
            font-weight: 700;
            color: rgb(var(--primary));
            background-color: white;
            padding: 12px 24px;
            border-radius: var(--radius-md);
            box-shadow: 0 4px 15px rgba(var(--primary), 0.15);
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
            margin: 0 auto;
            padding: 0;
        }

        .tramitfy-two-column {
            display: grid;
            grid-template-columns: 400px 1fr;
            gap: 30px;
            align-items: start;
        }

        /* Panel Lateral Izquierdo */
        .tramitfy-sidebar {
            position: sticky;
            top: 20px;
            background: linear-gradient(135deg, #016d86 0%, #014d5f 100%);
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 8px 32px rgba(1, 109, 134, 0.25);
            border: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            min-height: 500px;
            color: #ffffff;
        }

        .sidebar-content {
            display: none;
            animation: fadeInUp 0.4s ease-out;
        }

        .sidebar-content.active {
            display: block;
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
            gap: 15px;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 2px solid rgba(255, 255, 255, 0.2);
        }

        .sidebar-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, rgb(var(--primary)) 0%, rgb(var(--primary-dark)) 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 22px;
            box-shadow: 0 4px 12px rgba(var(--primary), 0.3);
        }

        .sidebar-title {
            flex: 1;
        }

        .sidebar-title h3 {
            margin: 0 0 5px 0;
            color: #ffffff;
            font-size: 20px;
            font-weight: 700;
        }

        .sidebar-title p {
            margin: 0;
            color: rgba(255, 255, 255, 0.8);
            font-size: 13px;
        }

        .sidebar-body {
            margin-bottom: 25px;
        }

        .sidebar-info-box {
            background: rgba(255, 255, 255, 0.15);
            padding: 18px;
            border-radius: 10px;
            margin-bottom: 15px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            border-left: 3px solid rgba(255, 255, 255, 0.5);
            backdrop-filter: blur(10px);
        }

        .sidebar-info-box p {
            margin: 0 0 8px 0;
            color: rgba(255, 255, 255, 0.95);
            font-size: 14px;
            line-height: 1.6;
        }

        .sidebar-info-box p:last-child {
            margin-bottom: 0;
        }

        .sidebar-info-box strong {
            color: #ffffff;
            font-weight: 700;
        }

        .sidebar-checklist {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        }

        .sidebar-checklist-item {
            display: flex;
            align-items: start;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px dashed rgba(var(--neutral-300), 0.5);
        }

        .sidebar-checklist-item:last-child {
            border-bottom: none;
        }

        .sidebar-check-icon {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: rgba(var(--success), 0.1);
            color: rgb(var(--success));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .sidebar-checklist-text {
            flex: 1;
            color: rgb(var(--neutral-700));
            font-size: 14px;
            line-height: 1.5;
        }

        .sidebar-tips {
            background: rgba(255, 193, 7, 0.2);
            padding: 18px;
            border-radius: 10px;
            border-left: 3px solid #FFC107;
            backdrop-filter: blur(10px);
        }

        .sidebar-tips h4 {
            margin: 0 0 12px 0;
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

        /* Panel Derecho - Formulario */
        .tramitfy-main-form {
            background: white;
            border-radius: 16px;
            padding: 0;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .tramitfy-two-column {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .tramitfy-sidebar {
                position: relative;
                top: auto;
                order: -1;
            }
        }

        @media (max-width: 768px) {
            .tramitfy-layout-wrapper {
                padding: 0;
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
    </style>

    <!-- Formulario principal -->
    <form id="transferencia-form" action="" method="POST" enctype="multipart/form-data">

        <!-- Wrapper de Layout de 2 Columnas -->
        <div class="tramitfy-layout-wrapper">
            <div class="tramitfy-two-column">

                <!-- Panel Lateral Izquierdo -->
                <aside class="tramitfy-sidebar">

                    <!-- Contenido: PASO 1 - Vehículo -->
                    <div class="sidebar-content" data-step="page-vehiculo">
                        <div class="sidebar-header">
                            <div class="sidebar-icon">
                                <i class="fa-solid fa-clock"></i>
                            </div>
                            <div class="sidebar-title">
                                <h3>En 24h: Documentos provisionales y presentación capitanía</h3>
                            </div>
                        </div>
                        <div class="sidebar-body">
                            <div class="sidebar-info-box">
                                <p><strong>Comienza ahora</strong> y mañana tendrás toda la documentación provisional lista para navegar.</p>
                                <p>Nos encargamos de presentar en <strong>Capitanía Marítima</strong> y gestionar todo el papeleo.</p>
                            </div>
                        </div>
                        <div class="sidebar-tips">
                            <h4><i class="fa-solid fa-circle-check"></i> Qué necesitas tener a mano</h4>
                            <ul>
                                <li>Matrícula y modelo de tu embarcación</li>
                                <li>DNI del vendedor y comprador</li>
                                <li>Contrato de compraventa (lo subimos después)</li>
                            </ul>
                        </div>
                    </div>

                    <!-- Contenido: Desglose de precios - Se muestra en vehiculo y documentos -->
                    <div class="sidebar-content active" data-step="page-vehiculo">
                        <div class="sidebar-header">
                            <div class="sidebar-icon">
                                <i class="fa-solid fa-calculator"></i>
                            </div>
                            <div class="sidebar-title">
                                <h3>Nuestros servicios</h3>
                            </div>
                        </div>
                        <div class="sidebar-body">
                            <!-- Desglose Nuestros Servicios -->
                            <div class="sidebar-info-box" style="margin-bottom: 20px;">
                                <h4 style="margin: 0 0 15px 0; font-size: 15px; font-weight: 700; color: #ffffff;">Tu tramitación</h4>
                                <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 13px;">
                                    <span>Tasas Capitanía + Gestión + Pago ITP:</span>
                                    <span id="sidebar-tasas-gestion">114,87€</span>
                                </div>
                                <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 13px;">
                                    <span>Honorarios profesionales:</span>
                                    <span id="sidebar-honorarios">0€</span>
                                </div>
                                <div style="display: flex; justify-content: space-between; margin-bottom: 12px; font-size: 13px;">
                                    <span>IVA incluido:</span>
                                    <span id="sidebar-iva">20,12€</span>
                                </div>
                                <div style="display: flex; justify-content: space-between; padding-top: 12px; border-top: 2px solid rgba(255,255,255,0.3); font-weight: 700; font-size: 18px;">
                                    <span>Total a pagar:</span>
                                    <span id="sidebar-total-amount">134,95€</span>
                                </div>
                            </div>

                            <!-- Desglose ITP Detallado -->
                            <div class="sidebar-info-box">
                                <h4 style="margin: 0 0 15px 0; font-size: 15px; font-weight: 700; color: #ffffff;">Cálculo del ITP</h4>

                                <!-- Datos del vehículo -->
                                <div style="margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid rgba(255,255,255,0.2);">
                                    <p style="margin: 0 0 10px 0; font-size: 12px; font-weight: 600; opacity: 0.8; text-transform: uppercase;">Datos del vehículo</p>
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 13px;">
                                        <span>Valor fiscal base:</span>
                                        <span id="sidebar-base-value">0€</span>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 13px;">
                                        <span>Antigüedad:</span>
                                        <span id="sidebar-vehicle-age">0 años</span>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 13px;">
                                        <span>Depreciación aplicada:</span>
                                        <span id="sidebar-depreciation">0%</span>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; font-size: 13px; font-weight: 600;">
                                        <span>Valor fiscal con depreciación:</span>
                                        <span id="sidebar-fiscal-value">0€</span>
                                    </div>
                                </div>

                                <!-- Cálculo -->
                                <div style="margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid rgba(255,255,255,0.2);">
                                    <p style="margin: 0 0 10px 0; font-size: 12px; font-weight: 600; opacity: 0.8; text-transform: uppercase;">Base imponible</p>
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 13px;">
                                        <span>Precio de compra:</span>
                                        <span id="sidebar-purchase-price">0€</span>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; font-size: 13px; font-weight: 600; background: rgba(255,255,255,0.1); padding: 8px; border-radius: 6px; margin-top: 6px;">
                                        <span>Mayor valor (base imponible):</span>
                                        <span id="sidebar-taxable-base">0€</span>
                                    </div>
                                </div>

                                <!-- Resultado -->
                                <div>
                                    <p style="margin: 0 0 10px 0; font-size: 12px; font-weight: 600; opacity: 0.8; text-transform: uppercase;">Impuesto a pagar</p>
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 13px;">
                                        <span>Tipo aplicado:</span>
                                        <span id="sidebar-tax-rate">4%</span>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; padding-top: 12px; border-top: 2px solid rgba(255,255,255,0.3); font-weight: 700; font-size: 16px;">
                                        <span>ITP a pagar a Hacienda:</span>
                                        <span id="sidebar-itp-amount">0€</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Mismo contenido para page-documentos -->
                    <div class="sidebar-content" data-step="page-documentos">
                        <div class="sidebar-header">
                            <div class="sidebar-icon">
                                <i class="fa-solid fa-calculator"></i>
                            </div>
                            <div class="sidebar-title">
                                <h3>Nuestros servicios</h3>
                            </div>
                        </div>
                        <div class="sidebar-body">
                            <!-- Desglose Nuestros Servicios -->
                            <div class="sidebar-info-box" style="margin-bottom: 20px;">
                                <h4 style="margin: 0 0 15px 0; font-size: 15px; font-weight: 700; color: #ffffff;">Tu tramitación</h4>
                                <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 13px;">
                                    <span>Tasas Capitanía + Gestión + Pago ITP:</span>
                                    <span id="sidebar-tasas-gestion">114,87€</span>
                                </div>
                                <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 13px;">
                                    <span>Honorarios profesionales:</span>
                                    <span id="sidebar-honorarios">0€</span>
                                </div>
                                <div style="display: flex; justify-content: space-between; margin-bottom: 12px; font-size: 13px;">
                                    <span>IVA incluido:</span>
                                    <span id="sidebar-iva">20,12€</span>
                                </div>
                                <div style="display: flex; justify-content: space-between; padding-top: 12px; border-top: 2px solid rgba(255,255,255,0.3); font-weight: 700; font-size: 18px;">
                                    <span>Total a pagar:</span>
                                    <span id="sidebar-total-amount">134,95€</span>
                                </div>
                            </div>

                            <!-- Desglose ITP Detallado -->
                            <div class="sidebar-info-box">
                                <h4 style="margin: 0 0 15px 0; font-size: 15px; font-weight: 700; color: #ffffff;">Cálculo del ITP</h4>

                                <!-- Datos del vehículo -->
                                <div style="margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid rgba(255,255,255,0.2);">
                                    <p style="margin: 0 0 10px 0; font-size: 12px; font-weight: 600; opacity: 0.8; text-transform: uppercase;">Datos del vehículo</p>
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 13px;">
                                        <span>Valor fiscal base:</span>
                                        <span id="sidebar-base-value">0€</span>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 13px;">
                                        <span>Antigüedad:</span>
                                        <span id="sidebar-vehicle-age">0 años</span>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 13px;">
                                        <span>Depreciación aplicada:</span>
                                        <span id="sidebar-depreciation">0%</span>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; font-size: 13px; font-weight: 600;">
                                        <span>Valor fiscal con depreciación:</span>
                                        <span id="sidebar-fiscal-value">0€</span>
                                    </div>
                                </div>

                                <!-- Cálculo -->
                                <div style="margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid rgba(255,255,255,0.2);">
                                    <p style="margin: 0 0 10px 0; font-size: 12px; font-weight: 600; opacity: 0.8; text-transform: uppercase;">Base imponible</p>
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 13px;">
                                        <span>Precio de compra:</span>
                                        <span id="sidebar-purchase-price">0€</span>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; font-size: 13px; font-weight: 600; background: rgba(255,255,255,0.1); padding: 8px; border-radius: 6px; margin-top: 6px;">
                                        <span>Mayor valor (base imponible):</span>
                                        <span id="sidebar-taxable-base">0€</span>
                                    </div>
                                </div>

                                <!-- Resultado -->
                                <div>
                                    <p style="margin: 0 0 10px 0; font-size: 12px; font-weight: 600; opacity: 0.8; text-transform: uppercase;">Impuesto a pagar</p>
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 13px;">
                                        <span>Tipo aplicado:</span>
                                        <span id="sidebar-tax-rate">4%</span>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; padding-top: 12px; border-top: 2px solid rgba(255,255,255,0.3); font-weight: 700; font-size: 16px;">
                                        <span>ITP a pagar a Hacienda:</span>
                                        <span id="sidebar-itp-amount">0€</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>


                    <!-- Contenido: PASO 4 - Datos Personales -->
                    <div class="sidebar-content" data-step="page-datos">
                        <div class="sidebar-header">
                            <div class="sidebar-icon">
                                <i class="fa-solid fa-user"></i>
                            </div>
                            <div class="sidebar-title">
                                <h3>Tus datos de contacto</h3>
                                <p>Paso 4 de 6</p>
                            </div>
                        </div>
                        <div class="sidebar-body">
                            <div class="sidebar-info-box">
                                <p><strong>Necesitamos tus datos</strong> para la tramitación oficial y para mantenerte informado del progreso.</p>
                                <p>Toda tu información es <strong>100% confidencial</strong> y segura.</p>
                            </div>
                        </div>
                        <div class="sidebar-tips">
                            <h4><i class="fa-solid fa-lightbulb"></i> Consejos útiles</h4>
                            <ul>
                                <li>Usaremos tu <strong>email</strong> para enviarte actualizaciones</li>
                                <li>El DNI debe coincidir con el del <strong>documento subido</strong></li>
                                <li>Revisa bien el nuevo nombre de tu moto de agua</li>
                            </ul>
                        </div>
                    </div>

                    <!-- Contenido: PASO 5 - Firma -->
                    <div class="sidebar-content" data-step="page-firma">
                        <div class="sidebar-header">
                            <div class="sidebar-icon">
                                <i class="fa-solid fa-pen-nib"></i>
                            </div>
                            <div class="sidebar-title">
                                <h3>Autorización final</h3>
                                <p>Paso 5 de 6</p>
                            </div>
                        </div>
                        <div class="sidebar-body">
                            <div class="sidebar-info-box">
                                <p><strong>Necesitamos tu firma electrónica</strong> para la autorización de transferencia.</p>
                                <p>Este documento tiene <strong>validez legal</strong> y será incluido en tu tramitación.</p>
                            </div>
                        </div>
                        <div class="sidebar-tips">
                            <h4><i class="fa-solid fa-lightbulb"></i> Consejos útiles</h4>
                            <ul>
                                <li>Firma con el <strong>ratón</strong> o con el <strong>dedo</strong> en móvil</li>
                                <li>Si no te gusta, puedes <strong>borrar y firmar de nuevo</strong></li>
                                <li>Asegúrate de que la firma sea clara y legible</li>
                            </ul>
                        </div>
                    </div>

                    <!-- Contenido: PASO 6 - Resumen y Pago -->
                    <div class="sidebar-content" data-step="page-resumen">
                        <div class="sidebar-header">
                            <div class="sidebar-icon">
                                <i class="fa-solid fa-credit-card"></i>
                            </div>
                            <div class="sidebar-title">
                                <h3>Confirma y paga</h3>
                                <p>Paso 6 de 6</p>
                            </div>
                        </div>
                        <div class="sidebar-body">
                            <div class="sidebar-price-highlight">
                                <div class="sidebar-price-label">Total a pagar ahora</div>
                                <div class="sidebar-price-amount" id="sidebar-final-amount">134,95€</div>
                                <div class="sidebar-price-includes">
                                    ✓ Gestión completa<br>
                                    ✓ Tasas + Honorarios + IVA<br>
                                    ✗ ITP (se paga a Hacienda)
                                </div>
                                <div class="sidebar-badge">
                                    <i class="fa-solid fa-shield-halved"></i> Conexión cifrada SSL
                                </div>
                            </div>
                            <div class="sidebar-info-box">
                                <p><strong>Revisa todos los datos</strong> antes de proceder al pago.</p>
                                <p>Recibirás confirmación por <strong>email inmediatamente</strong>.</p>
                            </div>
                        </div>
                    </div>

                    <!-- TrustIndex Reviews Widget -->
                    <div style="margin-top: 25px; padding: 20px; background: rgba(255, 255, 255, 0.1); border-radius: 12px; backdrop-filter: blur(10px);">
                        <script defer async src='https://cdn.trustindex.io/loader.js?f4fbfd341d12439e0c86fae7fc2'></script>
                    </div>

                </aside>

                <!-- Panel Derecho - Formulario -->
                <div class="tramitfy-main-form">

        <?php if (current_user_can('administrator')): ?>
        <!-- Panel de Auto-rellenado para Administradores -->
        <div class="admin-autofill-panel" style="background: #f0f9ff; border: 2px solid #0ea5e9; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <h4 style="color: #0369a1; margin: 0 0 10px 0;">🔧 Modo Administrador</h4>
            <p style="margin: 0 0 10px 0; font-size: 14px;">
                <strong>Stripe:</strong> <span style="color: #dc2626; font-weight: bold;">
                    🔴 MODO PRODUCCIÓN
                </span>
            </p>
            <button type="button" id="admin-autofill-btn" class="btn-primary" style="padding: 10px 20px; background: #0ea5e9; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: bold;">
                ⚡ Auto-rellenar Formulario (Solo Datos)
            </button>
            <p style="margin: 8px 0 0 0; font-size: 12px; color: #64748b;">
                Rellena automáticamente todos los campos y llega hasta el resumen. Stripe se maneja independientemente.
            </p>
        </div>
        <?php endif; ?>

        <!-- Navegación del formulario mejorada -->
        <div id="form-navigation" style="display: none;">
            <div class="nav-progress-bar">
                <div class="nav-progress-indicator"></div>
            </div>
            <div class="nav-items-container">
                <a href="#" class="nav-item" data-page-id="page-vehiculo">
                    <div class="nav-item-circle">
                        <div class="nav-item-icon">
                            <i class="fa-solid fa-car-side"></i>
                        </div>
                        <div class="nav-item-number">1</div>
                    </div>
                    <span class="nav-item-text">Vehículo</span>
                </a>
                
                <a href="#" class="nav-item" data-page-id="page-documentos">
                    <div class="nav-item-circle">
                        <div class="nav-item-icon">
                            <i class="fa-solid fa-file-alt"></i>
                        </div>
                        <div class="nav-item-number">2</div>
                    </div>
                    <span class="nav-item-text">Documentos</span>
                </a>

                <a href="#" class="nav-item" data-page-id="page-pago">
                    <div class="nav-item-circle">
                        <div class="nav-item-icon">
                            <i class="fa-solid fa-credit-card"></i>
                        </div>
                        <div class="nav-item-number">3</div>
                    </div>
                    <span class="nav-item-text">Pago</span>
                </a>
            </div>
        </div>

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
            <h2 style="margin-bottom: 10px;">Transferencia de Propiedad</h2>
            <h3 style="margin-bottom: 25px; font-size: 18px; color: #666;">Información de la Moto</h3>
            <!-- Tipo de vehículo fijo: Barco -->
            <input type="hidden" name="vehicle_type" value="Barco">

            <!-- Fabricante y Modelo en fila compacta -->
            <div id="vehicle-csv-section">
                <div class="form-compact-row">
                    <div class="form-group">
                        <label for="manufacturer">Fabricante</label>
                        <select id="manufacturer" name="manufacturer">
                            <option value="">Seleccione un fabricante</option>
                            <?php foreach (array_keys($datos_fabricantes) as $fabricante): ?>
                                <option value="<?php echo esc_attr($fabricante); ?>"><?php echo esc_html($fabricante); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="model">Modelo</label>
                        <select id="model" name="model">
                            <option value="">Seleccione un modelo</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- "No encuentro mi modelo" -->
            <div id="no-encuentro-wrapper">
                <label>
                    <input type="checkbox" id="no_encuentro_checkbox" name="no_encuentro_checkbox">
                    No encuentro mi modelo
                </label>
                <p style="font-size: 13px; color: #666; margin: 8px 0 15px 0;">
                    Marque esta casilla si su moto de agua no aparece en la lista anterior.
                    El cálculo del ITP se basará únicamente en el <strong>precio de compra</strong>.
                </p>
                <!-- Campos de marca/modelo manual en 2 columnas -->
                <div id="manual-fields" style="display: none;">
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

            <!-- Precio y Fecha en fila compacta -->
            <div class="form-compact-row">
                <div class="form-group">
                    <label for="purchase_price">Precio de Compra (€)</label>
                    <input type="number" id="purchase_price" name="purchase_price" placeholder="Precio de compra" required />
                </div>

                <div class="form-group">
                    <label for="matriculation_date" id="matriculation_date_label">Fecha de Matriculación</label>
                    <input type="date" id="matriculation_date" name="matriculation_date" max="<?php echo date('Y-m-d'); ?>" required>
                </div>
            </div>

            <!-- Comunidad Autónoma (ancho completo) -->
            <div class="form-group">
                <label for="region">Comunidad Autónoma del comprador</label>
                <select id="region" name="region" required>
                    <option value="">Seleccione una comunidad autónoma</option>
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

        </div> <!-- Fin page-vehiculo -->

        <!-- Página Documentos -->
        <div id="page-documentos" class="form-page form-section-compact hidden">
            <h2>Documentos</h2>

            <!-- Opción para ITP ya pagado -->
            <div class="itp-paid-option" style="margin-bottom: 20px; padding: 15px; background-color: #f8f9fa; border-radius: 8px;">
                <label class="service-checkbox">
                    <input type="checkbox" id="itp_already_paid" name="itp_already_paid">
                    <span class="checkbox-custom"></span>
                    <div class="service-info">
                        <span class="service-name">Ya tengo pagado el ITP (Impuesto de Transmisiones Patrimoniales)</span>
                    </div>
                </label>
            </div>

            <!-- Servicios adicionales - acordeón -->
            <div class="price-summary-accordion" id="services-accordion" style="margin-bottom: 20px;">
                <div class="accordion-toggle-header">
                    <span><i class="fa-solid fa-plus-circle"></i> Servicios Adicionales Opcionales</span>
                    <i class="fa-solid fa-chevron-down accordion-icon"></i>
                </div>
                <div class="accordion-content-section">
                    <div class="additional-service-item">
                        <label class="service-checkbox">
                            <input type="checkbox" class="extra-option" data-price="40" value="Cambio de nombre">
                            <span class="checkbox-custom"></span>
                            <div class="service-info">
                                <span class="service-name">Cambiar el nombre de la moto de agua</span>
                                <span class="service-price">40 €</span>
                            </div>
                        </label>
                        <div class="additional-input" id="nombre-input" style="display: none;">
                            <input type="text" id="nuevo_nombre" name="nuevo_nombre" placeholder="Ingrese el nuevo nombre de la moto de agua" />
                        </div>
                    </div>

                    <div class="additional-service-item">
                        <label class="service-checkbox">
                            <input type="checkbox" class="extra-option" data-price="40" value="Cambio de puerto base">
                            <span class="checkbox-custom"></span>
                            <div class="service-info">
                                <span class="service-name">Cambio de puerto base</span>
                                <span class="service-price">40 €</span>
                            </div>
                        </label>
                        <div class="additional-input" id="puerto-input" style="display: none;">
                            <input type="text" id="nuevo_puerto" name="nuevo_puerto" placeholder="Ingrese el nuevo puerto" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sección acordeón para datos personales -->
            <div class="accordion-section" id="section-datos">
                <div class="accordion-header active">
                    <span class="accordion-number">1</span>
                    <h3>Introduce tus datos</h3>
                    <span class="accordion-status">Pendiente</span>
                    <span class="accordion-toggle"><i class="fa-solid fa-chevron-down"></i></span>
                </div>
                <div class="accordion-content active">
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
                        </div>

                        <div class="form-group">
                            <label for="customer_phone">Teléfono</label>
                            <input type="tel" id="customer_phone" name="customer_phone" required />
                        </div>
                    </div>

                    <button type="button" class="section-next-btn">Continuar</button>
                </div>
            </div>

            <!-- Sección acordeón para documentación -->
            <div class="accordion-section" id="section-documentos">
                <div class="accordion-header">
                    <span class="accordion-number">2</span>
                    <h3>Adjunta tu documentación</h3>
                    <span class="accordion-status">Pendiente</span>
                    <span class="accordion-toggle"><i class="fa-solid fa-chevron-down"></i></span>
                </div>
                <div class="accordion-content">
                    <p class="section-intro">Por favor, sube los siguientes documentos. Puedes ver un ejemplo haciendo clic en "Ver ejemplo" junto a cada uno.</p>
                    
                    <div class="upload-grid">
                        <div class="upload-row">
                            <div class="upload-item">
                                <label id="label-hoja-asiento" for="upload-hoja-asiento">Copia hoja de tarjeta de la moto</label>
                                <div class="upload-wrapper">
                                    <input type="file" id="upload-hoja-asiento" name="upload_hoja_asiento[]" multiple required accept="image/*,.pdf">
                                    <div class="upload-button"><i class="fa-solid fa-upload"></i> Seleccionar archivos</div>
                                    <div class="file-count" data-input="upload-hoja-asiento">Ningún archivo seleccionado</div>
                                </div>
                                <div class="files-preview" id="preview-upload-hoja-asiento"></div>
                                <a href="#" class="view-example" id="view-example-hoja-asiento" data-doc="hoja-asiento">Ver ejemplo</a>
                            </div>
                            <div class="upload-item">
                                <label for="upload-dni-comprador">DNI del comprador <span class="label-hint">(por ambas caras)</span></label>
                                <div class="upload-wrapper">
                                    <input type="file" id="upload-dni-comprador" name="upload_dni_comprador[]" multiple required accept="image/*,.pdf">
                                    <div class="upload-button"><i class="fa-solid fa-upload"></i> Seleccionar archivos</div>
                                    <div class="file-count" data-input="upload-dni-comprador">Ningún archivo seleccionado</div>
                                </div>
                                <div class="files-preview" id="preview-upload-dni-comprador"></div>
                                <a href="#" class="view-example" data-doc="dni-comprador">Ver ejemplo</a>
                            </div>
                        </div>
                        <div class="upload-row">
                            <div class="upload-item">
                                <label for="upload-dni-vendedor">DNI del vendedor <span class="label-hint">(por ambas caras)</span></label>
                                <div class="upload-wrapper">
                                    <input type="file" id="upload-dni-vendedor" name="upload_dni_vendedor[]" multiple required accept="image/*,.pdf">
                                    <div class="upload-button"><i class="fa-solid fa-upload"></i> Seleccionar archivos</div>
                                    <div class="file-count" data-input="upload-dni-vendedor">Ningún archivo seleccionado</div>
                                </div>
                                <div class="files-preview" id="preview-upload-dni-vendedor"></div>
                                <a href="#" class="view-example" data-doc="dni-vendedor">Ver ejemplo</a>
                            </div>
                            <div class="upload-item">
                                <label for="upload-contrato-compraventa">Copia del contrato de compraventa</label>
                                <div class="upload-wrapper">
                                    <input type="file" id="upload-contrato-compraventa" name="upload_contrato_compraventa[]" multiple required accept="image/*,.pdf">
                                    <div class="upload-button"><i class="fa-solid fa-upload"></i> Seleccionar archivos</div>
                                    <div class="file-count" data-input="upload-contrato-compraventa">Ningún archivo seleccionado</div>
                                </div>
                                <div class="files-preview" id="preview-upload-contrato-compraventa"></div>
                                <a href="#" class="view-example" data-doc="contrato-compraventa">Ver ejemplo</a>
                            </div>
                        </div>

                        <!-- Fila adicional para el comprobante de pago del ITP (oculto por defecto) -->
                        <div class="upload-row" id="itp-payment-proof-row" style="display: none;">
                            <div class="upload-item">
                                <label for="upload-itp-comprobante">Comprobante de pago del ITP</label>
                                <div class="upload-wrapper">
                                    <input type="file" id="upload-itp-comprobante" name="upload_itp_comprobante">
                                    <div class="upload-button"><i class="fa-solid fa-upload"></i> Seleccionar archivo</div>
                                    <div class="file-name">Ningún archivo seleccionado</div>
                                </div>
                                <span class="input-hint">Justificante de pago del Impuesto de Transmisiones Patrimoniales</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Confirmación de documentación completa -->
                    <div class="docs-confirmation-container">
                        <label class="custom-checkbox">
                            <input type="checkbox" name="documents_complete" id="documents-complete-check" required>
                            <span class="checkbox-custom-mark"></span>
                            <span class="checkbox-text">Confirmo que he adjuntado toda la documentación necesaria para el trámite de transferencia de propiedad y que dicha documentación cumple con los requisitos legales establecidos.</span>
                        </label>
                    </div>
                    
                    <button type="button" class="section-next-btn">Continuar</button>
                </div>
            </div>

            <!-- Sección acordeón para firma -->
            <div class="accordion-section" id="section-firma">
                <div class="accordion-header">
                    <span class="accordion-number">3</span>
                    <h3>Firma</h3>
                    <span class="accordion-status">Pendiente</span>
                    <span class="accordion-toggle"><i class="fa-solid fa-chevron-down"></i></span>
                </div>
                <div class="accordion-content">
                    <p class="section-intro">Por favor, lee detenidamente el documento de autorización y firma en el espacio proporcionado.</p>

                    <div id="authorization-document" class="authorization-document">
                        <!-- Se generará dinámicamente cuando los text fields estén rellenados -->
                    </div>

                    <div class="signature-wrapper">
                        <div class="signature-instructions">
                            <h4><i class="fa-solid fa-info-circle"></i> Instrucciones para firmar</h4>
                            <p>Utilice el ratón o el dedo (en pantallas táctiles) para firmar en el recuadro de abajo. Su firma debe ser legible y similar a la de su DNI. Si necesita corregir su firma, pulse el botón "Limpiar Firma".</p>
                        </div>
                        <div id="signature-container">
                            <div class="signature-pad-wrapper">
                                <span class="signature-label" id="signature-label">Firme aquí</span>
                                <canvas id="signature-pad" width="500" height="200"></canvas>
                            </div>
                        </div>
                        <button type="button" class="button" id="clear-signature">Limpiar Firma</button>
                    </div>
                </div>
            </div>
        </div> <!-- Fin page-documentos -->

        <!-- Página Pago -->
        <div id="page-pago" class="form-page form-section-compact hidden">
            <h2 style="margin-bottom: 10px;">Resumen y Pago</h2>

            <!-- Cupón de descuento - acordeón -->
            <div class="price-summary-accordion" id="coupon-accordion" style="margin-bottom: 20px;">
                <div class="accordion-toggle-header">
                    <span><i class="fa-solid fa-tag"></i> ¿Tienes un cupón de descuento?</span>
                    <i class="fa-solid fa-chevron-down accordion-icon"></i>
                </div>
                <div class="accordion-content-section">
                    <div class="coupon-container">
                        <div class="coupon-input-wrapper">
                            <input type="text" id="coupon_code" name="coupon_code" placeholder="Introduce tu código de descuento" />
                            <button type="button" id="apply-coupon" class="coupon-button">Aplicar</button>
                        </div>
                        <p id="coupon-message" class="coupon-message"></p>
                    </div>
                </div>
            </div>

            <!-- Panel de resumen completo del trámite -->
            <div class="summary-panel">
                <h3 style="margin-bottom: 20px;"><i class="fa-solid fa-clipboard-list"></i> Resumen de su trámite</h3>

                <div class="summary-grid">
                    <!-- Columna 1: Datos Personales -->
                    <div class="summary-section">
                        <h4><i class="fa-solid fa-user"></i> Datos Personales</h4>
                        <div class="summary-content">
                            <p><strong>Nombre:</strong> <span id="summary-name">-</span></p>
                            <p><strong>DNI:</strong> <span id="summary-dni">-</span></p>
                            <p><strong>Email:</strong> <span id="summary-email">-</span></p>
                            <p><strong>Teléfono:</strong> <span id="summary-phone">-</span></p>
                        </div>
                    </div>
                    
                    <!-- Columna 2: Datos del Vehículo -->
                    <div class="summary-section">
                        <h4><i class="fa-solid fa-water"></i> Vehículo</h4>
                        <div class="summary-content">
                            <p><strong>Tipo:</strong> <span id="summary-vehicle-type">-</span></p>
                            <p><strong>Fabricante:</strong> <span id="summary-manufacturer">-</span></p>
                            <p><strong>Modelo:</strong> <span id="summary-model">-</span></p>
                            <p><strong>Fecha Matric.:</strong> <span id="summary-matriculation">-</span></p>
                            <p><strong>Precio Compra:</strong> <span id="summary-purchase-price">-</span></p>
                            <p><strong>Com. Autónoma:</strong> <span id="summary-region">-</span></p>
                        </div>
                    </div>
                    
                    <!-- Columna 3: Resumen de Pago -->
                    <div class="summary-section">
                        <h4><i class="fa-solid fa-receipt"></i> Resumen de Pago</h4>
                        <div class="summary-content">
                            <p><strong>Cambio de titularidad:</strong> <span id="summary-base-price">134.99 €</span></p>
                            <p style="margin-top: 10px; font-size: 0.9em; color: #666;"><strong>Incluye:</strong></p>
                            <div style="margin-left: 10px; font-size: 0.9em;">
                                <p>• Tasas + Gestión: <span id="summary-tasas-gestion">114.87 €</span></p>
                                <p>• IVA: <span id="summary-iva">20.12 €</span></p>
                            </div>
                            <p style="margin-top: 10px;"><strong>Impuesto transmisiones:</strong> <span id="summary-transfer-tax-detail">0 €</span></p>
                            <p id="summary-discount-detail" style="display: none; color: #2e8b57;"><strong>Descuento aplicado:</strong> <span id="summary-discount-amount">0 €</span></p>
                            
                            <div style="margin-top: 10px; padding-top: 10px; border-top: 1px dashed #ddd;">
                                <p id="summary-extras-detail" style="display: none;"><strong>Servicios adicionales:</strong></p>
                                <p id="summary-name-change" style="display: none;">• Cambio de Nombre: <span>40 €</span></p>
                                <p id="summary-port-change" style="display: none;">• Cambio de Puerto: <span>40 €</span></p>
                                <div id="summary-extras"></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Cupón aplicado (si existe) -->
                <div id="summary-coupon-container" style="display: none;">
                    <p><i class="fa-solid fa-tag"></i> <strong>Cupón aplicado:</strong> <span id="summary-coupon">-</span></p>
                </div>
            </div>
            
            <div class="total-price" style="margin-top: 30px; padding: 15px 20px; background-color: rgba(var(--primary), 0.05); border-radius: var(--radius-md); display: flex; justify-content: space-between; align-items: center;">
                <span class="total-label" style="font-size: 20px; font-weight: 600; color: rgb(var(--neutral-800));">TOTAL:</span>
                <span class="total-value" id="final-summary-amount" style="font-size: 24px; font-weight: 700; color: rgb(var(--primary)); background-color: rgba(var(--primary), 0.1); padding: 8px 15px; border-radius: var(--radius-md);">0 €</span>
            </div>
            
            <div class="terms-container payment-terms" style="margin-top: 30px; text-align: center; padding: 20px; border: 2px solid rgba(var(--primary), 0.3); border-radius: var(--radius-md); background-color: rgba(var(--primary), 0.05); box-shadow: 0 4px 8px rgba(0,0,0,0.05);">
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
                
                /* Estilo adicional para el checkbox dentro del modal */
                #modal-terms-accept:checked + label,
                label.checked {
                    color: rgb(var(--primary));
                    font-weight: 600;
                }
                
                /* Estilos mejorados para el botón de pago */
                .payment-button {
                    transition: all 0.3s ease;
                    transform: translateY(0);
                    box-shadow: 0 4px 12px rgba(var(--primary-dark), 0.2);
                }
                .payment-button:hover {
                    transform: translateY(-3px);
                    box-shadow: 0 8px 16px rgba(var(--primary-dark), 0.3);
                }
                .payment-button:active {
                    transform: translateY(1px);
                    box-shadow: 0 2px 8px rgba(var(--primary-dark), 0.2);
                }
            </style>

            <!-- Nuevo botón de pago -->
            <div class="payment-button-container" style="margin-top: 40px;">
                <button type="button" id="show-payment-modal" class="payment-button" style="font-size: 18px; font-weight: 600; padding: 14px 28px; border-radius: 8px; background: linear-gradient(135deg, rgb(var(--primary)) 0%, rgb(var(--primary-dark)) 100%); color: white; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 12px; width: 100%; max-width: 400px; margin: 0 auto;">
                    <i class="fa-solid fa-credit-card"></i> Realizar Pago Seguro
                </button>
            </div>
        </div>

        <!-- Modal de pago -->
        <div id="payment-modal" class="payment-modal">
            <div class="payment-modal-content">
                <span class="close-modal">&times;</span>
                <h3><i class="fa-solid fa-lock"></i> Realizar Pago Seguro</h3>
                
                <div id="stripe-container">
                    <!-- Spinner de carga mientras se inicializa -->
                    <div id="stripe-loading">
                        <div class="stripe-spinner"></div>
                        <p>Cargando sistema de pago...</p>
                    </div>
                    
                    <!-- Contenedor donde se montará el elemento de pago -->
                    <div id="payment-element" class="payment-element-container"></div>
                    
                    <!-- Indicadores de seguridad -->
                    <div class="payment-security">
                        <div class="security-badges">
                            <div class="security-badge">
                                <i class="fa-solid fa-lock"></i>
                                <span>Pago Seguro</span>
                            </div>
                            <div class="security-badge">
                                <i class="fa-solid fa-shield-alt"></i>
                                <span>Datos Encriptados</span>
                            </div>
                            <div class="security-badge">
                                <i class="fa-brands fa-stripe"></i>
                                <span>Stripe</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Mensajes de estado del pago -->
                    <div id="payment-message" class="hidden"></div>
                </div>
                
                <!-- Nota de términos ya aceptados (reemplaza el checkbox) -->
                <div class="terms-reminder" style="margin: 20px 0; text-align: center; font-size: 14px; color: rgb(var(--neutral-600));">
                    <i class="fa-solid fa-check-circle" style="color: rgb(var(--success)); margin-right: 5px;"></i>
                    <span>Términos y condiciones aceptados</span>
                </div>
                
                <button type="button" id="confirm-payment-button" class="confirm-payment-button">
                    <i class="fa-solid fa-check-circle"></i> Confirmar Pago
                </button>
            </div>
        </div>

        <!-- Botones de navegación -->
        <div class="button-container">
            <button type="button" class="button" id="prevButton">Anterior</button>
            <button type="button" class="button" id="nextButton">Siguiente</button>
        </div>
        
        <!-- Campos ocultos para enviar TASAS, IVA y HONORARIOS exactos -->
        <input type="hidden" name="tasas_hidden" id="tasas_hidden" />
        <input type="hidden" name="iva_hidden" id="iva_hidden" />
        <input type="hidden" name="honorarios_hidden" id="honorarios_hidden" />

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
    document.addEventListener('DOMContentLoaded', function() {
        // Variables globales y configuración
        const itpRates = {
            "Andalucía": 0.04, "Aragón": 0.04, "Asturias": 0.04, "Islas Baleares": 0.04,
            "Canarias": 0.055, "Cantabria": 0.08, "Castilla-La Mancha": 0.06, "Castilla y León": 0.05,
            "Cataluña": 0.05, "Comunidad Valenciana": 0.08, "Galicia": 0.03,
            "Madrid": 0.04, "Murcia": 0.04, "Navarra": 0.04, "País Vasco": 0.04,
            "La Rioja": 0.04, "Ceuta": 0.02, "Melilla": 0.04
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
        
        const BASE_TRANSFER_PRICE = 134.99;

        let basePrice = 0;
        let currentTransferTax = 0;
        let currentExtraFee = 0;
        let paymentCompleted = false;
        let currentPage = 0;
        let couponDiscountPercent = 0;
        let couponValue = "";
        let stripe;
        let elements;
        let finalAmount = BASE_TRANSFER_PRICE;
        let purchaseDetails = {};

        // Referencias a elementos del DOM
        const formPages = document.querySelectorAll('.form-page');
        const navLinks = document.querySelectorAll('.nav-link');
        const prevButton = document.getElementById('prevButton');
        const nextButton = document.getElementById('nextButton');
        const purchasePriceInput = document.getElementById('purchase_price');
        const regionSelect = document.getElementById('region');
        const transferTaxDisplay = document.getElementById('transfer_tax_display');
        const extraOptions = document.querySelectorAll('.extra-option');
        const manufacturerSelect = document.getElementById('manufacturer');
        const modelSelect = document.getElementById('model');
        const vehicleCsvSection = document.getElementById('vehicle-csv-section');
        const noEncuentroCheckbox = document.getElementById('no_encuentro_checkbox');
        const manualFields = document.getElementById('manual-fields');

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

        let signaturePad;
        const signatureCanvas = document.getElementById('signature-pad');

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
                } else {
                    fileCountEl.textContent = 'Ningún archivo seleccionado';
                    uploadWrapper.classList.remove('file-uploaded');
                    if (previewContainer) {
                        previewContainer.innerHTML = '';
                        previewContainer.classList.remove('active');
                    }
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

            document.getElementById('form-navigation').style.display = 'flex';

            formPages.forEach((page, index) => {
                page.classList.toggle('hidden', index !== currentPage);
            });

            // ===== ACTUALIZAR SIDEBAR SEGÚN PASO ACTUAL =====
            const currentPageElement = formPages[currentPage];
            if (currentPageElement) {
                const currentPageId = currentPageElement.id;

                // Ocultar todos los contenidos del sidebar
                document.querySelectorAll('.sidebar-content').forEach(content => {
                    content.classList.remove('active');
                });

                // Mostrar el contenido correspondiente al paso actual
                // Buscar sidebar que tenga el data-step exacto o que contenga el currentPageId
                document.querySelectorAll('.sidebar-content').forEach(content => {
                    const dataStep = content.getAttribute('data-step');
                    if (dataStep && (dataStep === currentPageId || dataStep.includes(currentPageId))) {
                        content.classList.add('active');
                    }
                });
            }
            // ================================================

            // Si estamos pasando a la página de documentos, inicializar el acordeón
            if (formPages[currentPage] && formPages[currentPage].id === 'page-documentos') {
                console.log("Actualizando a página documentos, inicializando acordeón");
                setTimeout(initAccordionSections, 100);
            }

            // Inicializar acordeones cuando llegamos a documentos (donde están ahora)
            if (formPages[currentPage] && formPages[currentPage].id === 'page-documentos') {
                console.log("Actualizando a página documentos, inicializando acordeones");
                setTimeout(() => {
                    initAdditionalOptionsDropdown();
                    initCouponDropdown();
                }, 100);
            }

            // Inicializar acordeón de cupón en page-pago
            if (formPages[currentPage] && formPages[currentPage].id === 'page-pago') {
                console.log("Actualizando a página pago, inicializando acordeón de cupón");
                setTimeout(() => {
                    initCouponDropdown();
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
                
                const isPaymentPage = formPages[currentPage].id === 'page-pago';
                document.querySelector('.button-container').style.display = isPaymentPage ? 'none' : 'flex';
                
                if (!isPaymentPage) {
                    prevButton.style.display = currentPage === 0 ? 'none' : 'inline-block';
                    
                    if (currentPage === formPages.length - 1) {
                        nextButton.textContent = 'Pagar';
                    } else {
                        nextButton.textContent = 'Siguiente';
                    }
                }
            } else {
                document.querySelector('.button-container').style.display = 'none';
            }
            
            if (formPages[currentPage].id === 'page-pago') {
                updatePaymentSummary();
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
            console.log("Inicializando Stripe con el monto: ", amount);
            const amountCents = Math.round(amount * 100);
            
            // Mostrar el spinner de carga
            document.getElementById('stripe-loading').style.display = 'block';
            document.getElementById('payment-element').innerHTML = '';
            document.getElementById('payment-message').className = 'hidden';
            
            // Inicializar Stripe según configuración
            stripe = Stripe('<?php echo STRIPE_PUBLIC_KEY; ?>');

            try {
                // Crear el payment intent
                const response = await fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=create_payment_intent&amount=${amountCents}`
                });
                
                // Procesar la respuesta del Payment Intent
                const result = await response.json();

                // DEBUG: Imprimir respuesta completa del servidor
                console.log("=== RESPUESTA DEL SERVIDOR (PaymentIntent) ===");
                console.log(result);

                if (result && result.error) {
                    console.error("Error al crear Payment Intent:", result.error);
                    document.getElementById('payment-message').textContent = 'Error al crear la intención de pago: ' + result.error;
                    document.getElementById('payment-message').className = 'error';
                    document.getElementById('stripe-loading').style.display = 'none';
                    return;
                }
                
                if (result && result.error) {
                    console.error("Error al crear Payment Intent:", result.error);
                    document.getElementById('payment-message').textContent = 'Error al crear la intención de pago: ' + result.error;
                    document.getElementById('payment-message').className = 'error';
                    document.getElementById('stripe-loading').style.display = 'none';
                    return;
                }

                // Configurar la apariencia de Stripe
                const appearance = {
                    theme: 'stripe',
                    variables: {
                        colorPrimary: '#016d86',
                        colorBackground: '#ffffff',
                        colorText: '#333333',
                        fontFamily: 'Roboto, sans-serif',
                        borderRadius: '4px',
                        colorDanger: '#e74c3c',
                    }
                };
                
                // Limpiar elementos si ya existen
                if (elements) {
                    elements = null;
                }
                
                // Crear elementos de Stripe con el secreto del cliente
                elements = stripe.elements({
                    appearance,
                    clientSecret: result.clientSecret
                });
                
                // Crear el elemento de pago con opciones mejoradas
                const paymentElement = elements.create('payment', {
                    layout: 'tabs',
                    defaultValues: {
                        billingDetails: {
                            name: customerNameInput.value || '',
                            email: customerEmailInput.value || '',
                            phone: customerPhoneInput.value || '',
                        }
                    }
                });
                
                // Limpiar cualquier contenido existente y montar el elemento
                document.getElementById('payment-element').innerHTML = '';
                
                // Montar el elemento de pago
                setTimeout(() => {
                    paymentElement.mount('#payment-element');
                    document.getElementById('stripe-loading').style.display = 'none';
                    console.log("Elemento de pago montado correctamente");
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
            const matriculationDate = new Date(matriculationDateInput.value);
            const today = new Date();
            let yearsDifference = today.getFullYear() - matriculationDate.getFullYear();
            const monthsDifference = today.getMonth() - matriculationDate.getMonth();
            
            if (monthsDifference < 0 || (monthsDifference === 0 && today.getDate() < matriculationDate.getDate())) {
                yearsDifference--;
            }
            
            yearsDifference = (yearsDifference < 0) ? 0 : yearsDifference;
            const depreciationPercentage = calculateDepreciationPercentage(yearsDifference);
            const fiscalValue = basePrice * (depreciationPercentage / 100);
            
            return { fiscalValue, depreciationPercentage, yearsDifference };
        }

        function calculateTransferTax() {
            const purchasePrice = parseFloat(purchasePriceInput.value) || 0;
            const { fiscalValue, depreciationPercentage, yearsDifference } = calculateFiscalValue();
            const region = regionSelect.value;
            const rate = itpRates[region] || 0;
            const isItpAlreadyPaid = document.getElementById('itp_already_paid').checked;

            const baseValue = Math.max(purchasePrice, fiscalValue);
            const itp = isItpAlreadyPaid ? 0 : baseValue * rate;
            const extraFee = 0; // Comisión bancaria eliminada

            baseValueDisplay.textContent = basePrice.toFixed(2) + ' €';
            depreciationPercentageDisplay.textContent = depreciationPercentage + ' %';
            fiscalValueDisplay.textContent = fiscalValue.toFixed(2) + ' €';
            vehicleAgeDisplay.textContent = yearsDifference + ' años';
            purchasePriceDisplay.textContent = purchasePrice.toFixed(2) + ' €';
            taxBaseDisplay.textContent = baseValue.toFixed(2) + ' €';
            taxRateDisplay.textContent = (rate * 100).toFixed(2) + ' %';
            calculatedItpDisplay.textContent = itp.toFixed(2) + ' €';

            return { itp, extraFee };
        }

        // Actualizar valores ITP en el sidebar
        function updateSidebarITP() {
            const purchasePrice = parseFloat(purchasePriceInput.value) || 0;
            const baseValue = selectedModelPrice || 0;
            const matriculationDate = matriculationDateInput.value;
            let vehicleAge = 0;
            let depreciationPercentage = 0;

            if (matriculationDate) {
                const matriculationYear = new Date(matriculationDate).getFullYear();
                const currentYear = new Date().getFullYear();
                vehicleAge = currentYear - matriculationYear;
                depreciationPercentage = Math.min(vehicleAge * 5, 50);
            }

            const depreciationFactor = 1 - (depreciationPercentage / 100);
            const fiscalValue = baseValue * depreciationFactor;
            const taxableBase = Math.max(purchasePrice, fiscalValue);
            const taxRate = parseFloat(document.querySelector('#region option:checked')?.dataset.rate || 4);
            const itp = currentTransferTax || 0;

            // IDs del sidebar
            const sidebarBaseValue = document.getElementById('sidebar-base-value');
            const sidebarVehicleAge = document.getElementById('sidebar-vehicle-age');
            const sidebarDepreciation = document.getElementById('sidebar-depreciation');
            const sidebarFiscalValue = document.getElementById('sidebar-fiscal-value');
            const sidebarPurchasePrice = document.getElementById('sidebar-purchase-price');
            const sidebarTaxableBase = document.getElementById('sidebar-taxable-base');
            const sidebarTaxRate = document.getElementById('sidebar-tax-rate');
            const sidebarItpAmount = document.getElementById('sidebar-itp-amount');

            if (sidebarBaseValue) sidebarBaseValue.textContent = baseValue > 0 ? baseValue.toFixed(2) + '€' : '0€';
            if (sidebarVehicleAge) sidebarVehicleAge.textContent = vehicleAge + ' años';
            if (sidebarDepreciation) sidebarDepreciation.textContent = depreciationPercentage + '%';
            if (sidebarFiscalValue) sidebarFiscalValue.textContent = fiscalValue > 0 ? fiscalValue.toFixed(2) + '€' : '0€';
            if (sidebarPurchasePrice) sidebarPurchasePrice.textContent = purchasePrice.toFixed(2) + '€';
            if (sidebarTaxableBase) sidebarTaxableBase.textContent = taxableBase.toFixed(2) + '€';
            if (sidebarTaxRate) sidebarTaxRate.textContent = taxRate + '%';
            if (sidebarItpAmount) sidebarItpAmount.textContent = itp.toFixed(2) + '€';
        }

        // Actualizar desglose de tramitación en sidebar
        function updateSidebarTramitacion(tasasGestion, honorarios, iva, total) {
            const sidebarTasasGestion = document.getElementById('sidebar-tasas-gestion');
            const sidebarHonorarios = document.getElementById('sidebar-honorarios');
            const sidebarIva = document.getElementById('sidebar-iva');
            const sidebarTotalAmount = document.getElementById('sidebar-total-amount');

            if (sidebarTasasGestion) sidebarTasasGestion.textContent = tasasGestion.toFixed(2) + '€';
            if (sidebarHonorarios) sidebarHonorarios.textContent = honorarios.toFixed(2) + '€';
            if (sidebarIva) sidebarIva.textContent = iva.toFixed(2) + '€';
            if (sidebarTotalAmount) sidebarTotalAmount.textContent = total.toFixed(2) + '€';
        }

        function updateTransferTaxDisplay() {
            const { itp, extraFee } = calculateTransferTax();
            currentTransferTax = itp;
            currentExtraFee = extraFee;
            transferTaxDisplay.textContent = itp.toFixed(2) + ' €';
            extraFeeIncludesDisplay.textContent = extraFee.toFixed(2) + ' €';

            // Actualizar sidebar ITP
            updateSidebarITP();
        }

        // Actualizar total y aplicar descuentos
        function updateTotal() {
            // Calculamos la parte base "Gestión" + extras marcados
            let transferFee = BASE_TRANSFER_PRICE;
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
            let currentExtraFee = extraFee;

            // Base para aplicar descuento (solo sobre transferFee)
            const discountBase = transferFee; 
            const discount = (couponDiscountPercent / 100) * discountBase;
            const discountedTransferFee = transferFee - discount;
            const finalExtraFee = currentExtraFee;

            // Desglose de tasas, honorarios e IVA
            const baseTasas = 19.05;
            const baseHonorarios = 95.82 + additionalServicesTotal; // Incluir servicios adicionales en honorarios
            const discountRatio = (couponDiscountPercent / 100);
            const discountedHonorarios = baseHonorarios * (1 - discountRatio);
            const newIva = discountedHonorarios * 0.21;

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
            const cambioNombrePriceDisplay = document.getElementById('cambio_nombre_price');

            if (transferTaxDisplay) transferTaxDisplay.textContent = itp.toFixed(2) + ' €';

            const tasasMasHonorarios = baseTasas + discountedHonorarios;
            if (tasasHonorariosDisplay) tasasHonorariosDisplay.textContent = tasasMasHonorarios.toFixed(2) + ' €';
            if (ivaDisplay) ivaDisplay.textContent = newIva.toFixed(2) + ' €';
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

            // Actualizar sidebar con desglose de tramitación
            const tasasMasHonorarios = baseTasas + discountedHonorarios;
            updateSidebarTramitacion(tasasMasHonorarios, discountedHonorarios, newIva, totalGestion);
            
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
            document.getElementById('summary-name').textContent = customerNameInput.value || '-';
            document.getElementById('summary-dni').textContent = customerDniInput.value || '-';
            document.getElementById('summary-email').textContent = customerEmailInput.value || '-';
            document.getElementById('summary-phone').textContent = customerPhoneInput.value || '-';
            
            const vehicleType = 'Moto de Agua'; // Fijo para transferencia de barcos
            document.getElementById('summary-vehicle-type').textContent = vehicleType;
            
            const noEncuentro = noEncuentroCheckbox.checked;
            if (noEncuentro) {
                document.getElementById('summary-manufacturer').textContent = document.getElementById('manual_manufacturer').value || '-';
                document.getElementById('summary-model').textContent = document.getElementById('manual_model').value || '-';
                document.getElementById('summary-matriculation').textContent = 'No aplica';
            } else {
                document.getElementById('summary-manufacturer').textContent = manufacturerSelect.value || '-';
                document.getElementById('summary-model').textContent = modelSelect.value || '-';
                document.getElementById('summary-matriculation').textContent = matriculationDateInput.value || '-';
            }
            
            document.getElementById('summary-purchase-price').textContent = purchasePriceInput.value + ' €' || '-';
            document.getElementById('summary-region').textContent = regionSelect.value || '-';
            
            const cambioNombrePrice = cambioNombrePriceDisplay.textContent;
            const tasasHonorarios = document.getElementById('tasas_honorarios_display').textContent;
            const iva = document.getElementById('iva_display').textContent;
            const transferTax = transferTaxDisplay.textContent;

            document.getElementById('summary-base-price').textContent = cambioNombrePrice;
            document.getElementById('summary-tasas-gestion').textContent = tasasHonorarios;
            document.getElementById('summary-iva').textContent = iva;
            document.getElementById('summary-transfer-tax-detail').textContent = transferTax;
            
            document.getElementById('summary-name-change').style.display = 'none';
            document.getElementById('summary-port-change').style.display = 'none';
            
            const extraOptions = document.querySelectorAll('.extra-option');
            let hasExtras = false;
            
            extraOptions.forEach(option => {
                if (option.checked) {
                    hasExtras = true;
                    if (option.value === 'Cambio de nombre') {
                        document.getElementById('summary-name-change').style.display = 'block';
                    } else if (option.value === 'Cambio de puerto base') {
                        document.getElementById('summary-port-change').style.display = 'block';
                    }
                }
            });
            
            document.getElementById('summary-extras-detail').style.display = hasExtras ? 'block' : 'none';
            
            const couponCode = couponCodeInput.value;
            if (couponCode && couponDiscountPercent > 0) {
                document.getElementById('summary-coupon-container').style.display = 'block';
                document.getElementById('summary-coupon').textContent = couponCode + ' (' + couponDiscountPercent + '% descuento)';
                
                document.getElementById('summary-discount-detail').style.display = 'block';
                const discountBase = BASE_TRANSFER_PRICE;
                const discountAmount = (couponDiscountPercent / 100) * discountBase;
                document.getElementById('summary-discount-amount').textContent = discountAmount.toFixed(2) + ' €';
            } else {
                document.getElementById('summary-coupon-container').style.display = 'none';
                document.getElementById('summary-discount-detail').style.display = 'none';
            }
            
            document.getElementById('final-summary-amount').textContent = finalAmount.toFixed(2) + ' €';
        }

        // Validación y firma
        function initializeSignaturePad() {
            if (!signaturePad && signatureCanvas) {
                try {
                    signaturePad = new SignaturePad(signatureCanvas, {
                        backgroundColor: 'rgb(255, 255, 255)',
                        penColor: 'rgb(0, 0, 0)',
                        minWidth: 1,
                        maxWidth: 2.5
                    });

                    // Ocultar label cuando el usuario empiece a firmar
                    signaturePad.addEventListener('beginStroke', function() {
                        const label = document.getElementById('signature-label');
                        if (label) label.classList.add('hidden');
                        if (signatureCanvas) signatureCanvas.classList.add('signed');
                    });

                    console.log('SignaturePad inicializado correctamente');
                } catch (error) {
                    console.error('Error inicializando SignaturePad:', error);
                }
            }
        }

        function generateAuthorizationDocument() {
            const authorizationDiv = document.getElementById('authorization-document');
            const customerName = customerNameInput.value.trim();
            const customerDNI = customerDniInput.value.trim();
            const customerEmail = customerEmailInput.value.trim();
            const vehicleType = 'Moto de Agua';
            const manufacturer = manufacturerSelect.value;
            const model = modelSelect.value;
            const manualManufacturer = document.getElementById('manual_manufacturer').value.trim();
            const manualModel = document.getElementById('manual_model').value.trim();
            const matriculationDate = matriculationDateInput.value;
            const nuevoNombre = document.getElementById('nuevo_nombre').value.trim();
            const nuevoPuerto = document.getElementById('nuevo_puerto').value.trim();
            const noEncuentro = noEncuentroCheckbox.checked;

            const currentDate = new Date().toLocaleDateString('es-ES', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });

            let html = `
                <div class="auth-doc-header">
                    <img src="https://www.tramitfy.es/wp-content/uploads/LOGO.png" alt="Tramitfy Logo">
                    <div class="auth-doc-title">Autorización para Transferencia de Propiedad</div>
                    <div class="auth-doc-date">Fecha: ${currentDate}</div>
                </div>

                <p style="text-align: justify;">Yo, <strong>${customerName}</strong>, con DNI <strong>${customerDNI}</strong> y correo electrónico <strong>${customerEmail}</strong>, autorizo expresamente a <strong>TRAMITFY S.L.</strong> (CIF <strong>B55388557</strong>) para que, actuando en mi nombre y representación, realice todas las gestiones necesarias para la <strong>transferencia de propiedad</strong> del siguiente vehículo:</p>

                <div class="auth-doc-section">
                    <div class="auth-doc-section-title">
                        <i class="fa-solid fa-water"></i>
                        Datos del Vehículo
                    </div>
                    <ul>
                        <li><strong>Tipo de Vehículo:</strong> ${vehicleType}</li>
            `;

            if (!noEncuentro) {
                html += `
                        <li><strong>Fabricante:</strong> ${manufacturer}</li>
                        <li><strong>Modelo:</strong> ${model}</li>
                        <li><strong>Fecha de Matriculación:</strong> ${matriculationDate}</li>
                `;
            } else {
                html += `
                        <li><strong>Fabricante:</strong> ${manualManufacturer}</li>
                        <li><strong>Modelo:</strong> ${manualModel}</li>
                        <li><em>Fecha de matriculación no disponible</em></li>
                `;
            }

            if (nuevoNombre || nuevoPuerto) {
                html += `</ul>
                    <div class="auth-doc-section-title" style="margin-top: 15px;">
                        <i class="fa-solid fa-pen-to-square"></i>
                        Servicios Adicionales Solicitados
                    </div>
                    <ul>`;
                if (nuevoNombre) {
                    html += `<li><strong>Cambio de Nombre:</strong> ${nuevoNombre}</li>`;
                }
                if (nuevoPuerto) {
                    html += `<li><strong>Cambio de Puerto Base:</strong> ${nuevoPuerto}</li>`;
                }
            }

            html += `
                    </ul>
                </div>

                <p style="text-align: justify;">Esta autorización incluye la presentación de documentación, pago de tasas administrativas, y cualquier otra gestión requerida por la autoridad competente para completar la transferencia.</p>

                <p style="text-align: justify; margin-top: 20px;"><strong>Declaro que:</strong> Los datos proporcionados son veraces y me comprometo a facilitar cualquier documentación adicional que sea requerida para completar el trámite.</p>

                <div class="auth-doc-footer">
                    <p style="margin: 5px 0; font-weight: 600;">Firma del Solicitante</p>
                    <p style="margin: 5px 0; font-style: italic;">(La firma electrónica a continuación tiene la misma validez legal que una firma manuscrita)</p>
                </div>
            `;
            authorizationDiv.innerHTML = html;
            
            // Marcar la primera sección como completada
            const firstHeader = document.querySelector('.accordion-section:first-child .accordion-header');
            if (firstHeader && customerName && customerDNI && customerEmailInput.value && customerPhoneInput.value) {
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
                } else if (typeof generateAuthorizationDocument === 'function') {
                    generateAuthorizationDocument();
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

                            // Si es el acordeón de firma, inicializar SignaturePad
                            const sectionId = this.parentElement.id;
                            if (sectionId === 'section-firma') {
                                console.log('Acordeón de firma abierto, inicializando SignaturePad');
                                initializeSignaturePad();
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
            formData.append('action', 'validate_coupon_code_XXX');
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

        // Enviar emails y procesar formulario
        function sendEmails() {
            const formData = new FormData();
            formData.append('action', 'send_emails');
            formData.append('customer_email', purchaseDetails.customerEmail);
            formData.append('customer_name', purchaseDetails.customerName);
            formData.append('customer_dni', purchaseDetails.customerDNI);
            formData.append('customer_phone', purchaseDetails.customerPhone);
            formData.append('service_details', purchaseDetails.options.join(', '));
            formData.append('payment_amount', purchaseDetails.totalAmount);
            formData.append('nuevo_nombre', purchaseDetails.nuevoNombre);
            formData.append('nuevo_puerto', purchaseDetails.nuevoPuerto);
            formData.append('coupon_used', purchaseDetails.couponUsed);
            // Enviar el ID de trámite si existe
            if (purchaseDetails.tramite_id) {
                formData.append('tramite_id', purchaseDetails.tramite_id);
            }

            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    console.log('Correos enviados exitosamente.');
                } else {
                    console.log('Error al enviar los correos.');
                }
            })
            .catch(error => console.error('Error:', error));
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
            formData.append('action', 'submit_moto_form_tpm');
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

            if (signaturePad) {
                formData.append('signature', signaturePad.toDataURL());
            }

            formData.append('coupon_used', couponValue);

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
                        const trackingUrl = data.data && data.data.tracking_url
                            ? data.data.tracking_url
                            : 'https://46-202-128-35.sslip.io/seguimiento/' + (data.data.tracking_id || Date.now());
                        console.log('Redirigiendo a página de seguimiento:', trackingUrl);
                        window.location.href = trackingUrl;
                    }, 1500);
                } else {
                    alertMessageText.textContent = 'Error al enviar el formulario: ' + data.message;
                    document.getElementById('loading-overlay').style.display = 'none';
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
                vehicleCsvSection.style.display = 'none';
                basePrice = 0;
                manualFields.style.display = 'block';
                matriculationDateLabel.style.display = 'none';
                matriculationDateInput.style.display = 'none';
                matriculationDateInput.removeAttribute('required');
            } else {
                vehicleCsvSection.style.display = 'block';
                manualFields.style.display = 'none';
                matriculationDateLabel.style.display = 'block';
                matriculationDateInput.style.display = 'block';
                matriculationDateInput.setAttribute('required', 'required');

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
            const vehicleType = 'Moto de Agua'; // Fijo para transferencia de barcos
            const csvFile = 'MOTO.csv'; // Fijo para barcos
            fetch('<?php echo get_template_directory_uri(); ?>/' + csvFile)
                .then(response => response.text())
                .then(data => {
                    const manufacturers = {};
                    const rows = data.split('\n').slice(1);
                    rows.forEach(row => {
                        const [fabricante, modelo, precio] = row.split(',');
                        if (!manufacturers[fabricante]) {
                            manufacturers[fabricante] = [];
                        }
                        manufacturers[fabricante].push({ modelo, precio });
                    });
                    manufacturerSelect.innerHTML = '<option value="">Seleccione un fabricante</option>';
                    Object.keys(manufacturers).forEach(fab => {
                        const option = document.createElement('option');
                        option.value = fab;
                        option.textContent = fab;
                        manufacturerSelect.appendChild(option);
                    });
                });
        }

        // Ajustar label de la hoja de asiento según vehículo
        function updateDocumentLabels() {
            const vehicleType = 'Moto de Agua'; // Fijo para transferencia de barcos
            const labelHojaAsiento = document.getElementById('label-hoja-asiento');
            const inputHojaAsiento = document.getElementById('upload-hoja-asiento');
            const viewExampleLink = document.getElementById('view-example-hoja-asiento');
            
            if (vehicleType === 'Moto de Agua') {
                labelHojaAsiento.textContent = 'Tarjeta de la moto';
                inputHojaAsiento.name = 'upload_tarjeta_moto';
                viewExampleLink.setAttribute('data-doc', 'tarjeta-moto');
            } else {
                labelHojaAsiento.textContent = 'Copia del tarjeta de la moto';
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
            const cName = customerNameInput.value.trim();
            const cDni = customerDniInput.value.trim();
            const cEmail = customerEmailInput.value.trim();
            const cPhone = customerPhoneInput.value.trim();
            
            if (cName && cDni && cEmail && cPhone) {
                generateAuthorizationDocument();
            }
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
            if (currentPage > 0) currentPage--;
            updateForm();
        });

        nextButton.addEventListener('click', async (e) => {
            e.preventDefault();
            const isLastPage = (currentPage === formPages.length - 1);

            if (!isLastPage) {
                currentPage++;
                updateForm();
            } else {
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
                    if (!stripe || !elements) {
                        console.error("Stripe no ha sido inicializado correctamente");
                        throw new Error("Error en la configuración del sistema de pago");
                    }
                    
                    const { error, paymentIntent } = await stripe.confirmPayment({
                        elements,
                        confirmParams: {
                            return_url: window.location.href,
                            payment_method_data: {
                                billing_details: {
                                    name: customerNameInput.value.trim(),
                                    email: customerEmailInput.value.trim(),
                                    phone: customerPhoneInput.value.trim(),
                                },
                            },
                        },
                        redirect: 'if_required'
                    });
                    
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

                    purchaseDetails = {
                        totalAmount: finalAmount.toFixed(2),
                        options: Array.from(extraOptions).filter(opt => opt.checked).map(opt => opt.value),
                        transferTax: currentTransferTax.toFixed(2),
                        customerName: customerNameInput.value.trim(),
                        customerEmail: customerEmailInput.value.trim(),
                        customerPhone: customerPhoneInput.value.trim(),
                        customerDNI: customerDniInput.value.trim(),
                        nuevoNombre: document.getElementById('nuevo_nombre').value.trim(),
                        nuevoPuerto: document.getElementById('nuevo_puerto').value.trim(),
                        couponUsed: couponValue,
                        tramite_id: '<?php echo $tramite_id; ?>', // Añadimos el ID de trámite
                        paymentIntentId: paymentIntent.id // Guardar el payment intent ID
                    };

                    sendEmails();
                    handleFinalSubmission();

                } catch (err) {
                    console.error("Error inesperado:", err);
                    paymentMessage.textContent = 'Ocurrió un error al procesar el pago: ' + err.message;
                    paymentMessage.classList.add('error');
                    paymentMessage.classList.remove('hidden');
                    document.getElementById('loading-overlay').style.display = 'none';
                    nextButton.disabled = false;
                }
            }
        });


        document.getElementById('info-link').addEventListener('click', function(e) {
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

        const docPopup = document.getElementById('document-popup');
        const closePopup = docPopup.querySelector('.close-popup');
        const exampleImage = document.getElementById('document-example-image');
        
        document.querySelectorAll('.view-example').forEach(link => {
            link.addEventListener('click', function(event) {
                event.preventDefault();
                const docType = this.getAttribute('data-doc');
                exampleImage.src = '/wp-content/uploads/exampledocs/' + docType + '.jpg';
                docPopup.style.display = 'block';
            });
        });
        
        closePopup.addEventListener('click', () => {
            docPopup.style.display = 'none';
        });
        
        window.addEventListener('click', function(event) {
            if (event.target === docPopup) {
                docPopup.style.display = 'none';
            }
        });

        purchasePriceInput.addEventListener('input', function() {
            this.value = this.value.replace(/[.,]/g, '');
            onInputChange();
        });
        
        regionSelect.addEventListener('change', onInputChange);

        // Manejar el checkbox de ITP ya pagado
        const itpAlreadyPaidCheckbox = document.getElementById('itp_already_paid');
        const itpPaymentProofRow = document.getElementById('itp-payment-proof-row');
        const itpComprobante = document.getElementById('upload-itp-comprobante');

        itpAlreadyPaidCheckbox.addEventListener('change', function() {
            // Mostrar/ocultar campo para subir comprobante
            itpPaymentProofRow.style.display = this.checked ? 'flex' : 'none';

            // Cambiar si el campo es requerido o no
            itpComprobante.required = this.checked;

            // Actualizar cálculos
            onInputChange();
        });
        matriculationDateInput.addEventListener('change', onInputChange);
        
        extraOptions.forEach(opt => opt.addEventListener('change', () => {
            updateAdditionalInputs();
            updateTotal();
        }));

        // Tipo de vehículo fijo, no necesario detectar cambios
        // document.querySelectorAll('input[name="vehicle_type"]').forEach(input => {
        //     input.addEventListener('change', () => {
        //         populateManufacturers();
        //         manufacturerSelect.innerHTML = '<option value="">Seleccione un fabricante</option>';
        //         modelSelect.innerHTML = '<option value="">Seleccione un modelo</option>';
        //         basePrice = 0;
        //         onInputChange();
        //         updateVehicleSelection();
        //         updateDocumentLabels();
        //     });
        // });

        manufacturerSelect.addEventListener('change', function() {
            const selectedFabricante = this.value;
            modelSelect.innerHTML = '<option value="">Seleccione un modelo</option>';
            basePrice = 0;
            onInputChange();
            
            if (selectedFabricante) {
                const csvFile = 'MOTO.csv'; // Fijo para barcos
                fetch('<?php echo get_template_directory_uri(); ?>/' + csvFile)
                    .then(response => response.text())
                    .then(data => {
                        const rows = data.split('\n').slice(1);
                        rows.forEach(row => {
                            const [fab, mod, precio] = row.split(',');
                            if (fab === selectedFabricante) {
                                const option = document.createElement('option');
                                option.value = mod;
                                option.textContent = mod;
                                option.dataset.price = precio;
                                modelSelect.appendChild(option);
                            }
                        });
                    });
            }
        });

        modelSelect.addEventListener('change', function() {
            if (!noEncuentroCheckbox.checked) {
                const selectedOption = this.options[this.selectedIndex];
                basePrice = selectedOption ? parseFloat(selectedOption.dataset.price) : 0;
            } else {
                basePrice = 0;
            }
            onInputChange();
        });

        noEncuentroCheckbox.addEventListener('change', () => {
            updateNoEncuentroBehavior();
            onInputChange();
        });

        couponCodeInput.addEventListener('input', debounceValidateCoupon);

        document.getElementById('apply-coupon').addEventListener('click', function() {
            const couponInput = document.getElementById('coupon_code');
            if (couponInput && couponInput.value.trim()) {
                debounceValidateCoupon();
            }
        });

        customerNameInput.addEventListener('input', onDocumentFieldsInput);
        customerDniInput.addEventListener('input', onDocumentFieldsInput);
        customerEmailInput.addEventListener('input', onDocumentFieldsInput);
        customerPhoneInput.addEventListener('input', onDocumentFieldsInput);

        document.getElementById('clear-signature').addEventListener('click', function() {
            if (signaturePad) {
                signaturePad.clear();
                const label = document.getElementById('signature-label');
                if (label) label.classList.remove('hidden');
                const canvas = document.getElementById('signature-pad');
                if (canvas) canvas.classList.remove('signed');
            }
        });

        document.getElementById('show-payment-modal').addEventListener('click', function() {
            const termsCheckbox = document.querySelector('input[name="terms_accept_pago"]');
            const customerEmail = document.getElementById('customer_email').value.trim();
            
            // Verificar términos y condiciones
            if (!termsCheckbox || !termsCheckbox.checked) {
                alert('Debe aceptar los términos y condiciones de pago para continuar.');
                return;
            }
            
            // Verificar que se haya ingresado el email
            if (!customerEmail) {
                alert('Debe ingresar su correo electrónico en la sección de datos personales para continuar.');
                return;
            }
            
            // Verificar que se haya firmado el documento
            if (signaturePad && signaturePad.isEmpty()) {
                alert('Debe firmar el documento de autorización antes de continuar con el pago.');
                return;
            }
            
            // Mostrar el modal
            document.getElementById('payment-modal').classList.add('show');
            
            // Inicializar Stripe después de un pequeño retraso para que la animación del modal termine
            setTimeout(() => {
                try {
                    initializeStripe(finalAmount);
                } catch (error) {
                    console.error("Error al inicializar Stripe:", error);
                    document.getElementById('payment-message').textContent = 'Error al inicializar el sistema de pago: ' + error.message;
                    document.getElementById('payment-message').className = 'error';
                    document.getElementById('stripe-loading').style.display = 'none';
                }
            }, 500);
            
            // La validación del botón de confirmación de pago está en un listener separado
            // abajo para evitar duplicados
        });
        
        document.querySelector('.close-modal').addEventListener('click', function() {
            document.getElementById('payment-modal').classList.remove('show');
        });
        
        document.getElementById('payment-modal').addEventListener('click', function(event) {
            if (event.target === this) {
                this.classList.remove('show');
            }
        });
        
        document.getElementById('confirm-payment-button').addEventListener('click', async function() {
            // Mostrar overlay de carga y deshabilitar el botón
            const loadingOverlay = document.getElementById('loading-overlay');
            loadingOverlay.style.display = 'flex';
            this.disabled = true;
            
            // Activar el primer paso (payment)
            updateLoadingStep('payment');
            
            // Limpiar mensajes anteriores
            const paymentMessage = document.getElementById('payment-message');
            paymentMessage.className = 'hidden';
            paymentMessage.textContent = '';
            
            try {
                // Verificar que Stripe esté inicializado correctamente
                if (!stripe || !elements) {
                    console.error("Error: Stripe no está inicializado correctamente");
                    paymentMessage.textContent = 'Error: El sistema de pago no está inicializado correctamente. Por favor, recargue la página e intente nuevamente.';
                    paymentMessage.className = 'error';
                    loadingOverlay.style.display = 'none';
                    this.disabled = false;
                    return;
                }
                
                // Mostrar mensaje de procesamiento
                paymentMessage.textContent = 'Procesando su pago...';
                paymentMessage.className = 'processing';
                
                // Confirmar el pago
                const { error, paymentIntent } = await stripe.confirmPayment({
                    elements,
                    confirmParams: {
                        // URL de retorno en caso de autenticación 3D Secure
                        return_url: window.location.href,
                        payment_method_data: {
                            billing_details: {
                                name: customerNameInput.value.trim(),
                                email: customerEmailInput.value.trim(),
                                phone: customerPhoneInput.value.trim(),
                            },
                        },
                    },
                    redirect: 'if_required'
                });
                
                // Manejar errores de pago
                if (error) {
                    console.error("Error en el pago:", error);
                    
                    // Mensaje de error en español
                    let errorMessage;
                    switch (error.type) {
                        case 'card_error':
                            errorMessage = 'Error con la tarjeta: ' + error.message;
                            break;
                        case 'validation_error':
                            errorMessage = 'Por favor, revise los datos de su tarjeta: ' + error.message;
                            break;
                        default:
                            errorMessage = 'Ha ocurrido un error en el procesamiento del pago: ' + error.message;
                    }
                    
                    paymentMessage.textContent = errorMessage;
                    paymentMessage.className = 'error';
                    loadingOverlay.style.display = 'none';
                    this.disabled = false;
                    return;
                }
                
                // Pago exitoso
                paymentMessage.textContent = 'Pago realizado con éxito. Procesando su solicitud...';
                paymentMessage.className = 'success';
                
                // Activar el segundo paso (documents)
                updateLoadingStep('documents');
                
                // Esperar un momento antes de continuar (para mostrar visualmente el progreso)
                await new Promise(resolve => setTimeout(resolve, 1500));
                
                paymentCompleted = true;
                
                purchaseDetails = {
                    totalAmount: finalAmount.toFixed(2),
                    options: Array.from(extraOptions).filter(opt => opt.checked).map(opt => opt.value),
                    transferTax: currentTransferTax.toFixed(2),
                    customerName: customerNameInput.value.trim(),
                    customerEmail: customerEmailInput.value.trim(),
                    customerPhone: customerPhoneInput.value.trim(),
                    customerDNI: customerDniInput.value.trim(),
                    nuevoNombre: document.getElementById('nuevo_nombre').value.trim(),
                    nuevoPuerto: document.getElementById('nuevo_puerto').value.trim(),
                    couponUsed: couponValue,
                    tramite_id: '<?php echo $tramite_id; ?>' // Añadimos el ID de trámite
                };

                // Activar el tercer paso (complete) después de un momento
                setTimeout(() => {
                    updateLoadingStep('complete');
                    // Esperar un momento y luego enviar el form final
                    setTimeout(() => {
                        handleFinalSubmission();
                    }, 1000);
                }, 2000);
                
            } catch (err) {
                console.error("Error inesperado:", err);
                paymentMessage.textContent = 'Ocurrió un error al procesar el pago: ' + err.message;
                paymentMessage.classList.add('error');
                loadingOverlay.style.display = 'none';
                this.disabled = false;
            }
        });

        // Inicialización
        currentPage = 0; // Empezamos en la página de vehículo (primera página del formulario)
        document.querySelector('.button-container').style.display = 'flex'; // Mostrar botones navegación desde el inicio
        
        populateManufacturers();
        updateForm();
        updateVehicleSelection();
        updateAdditionalInputs();
        initializeSignaturePad();
        updateNoEncuentroBehavior();
        
        // Inicializar dropdowns mejorados para opciones adicionales y cupones
        initAdditionalOptionsDropdown();
        initCouponDropdown();
        
        // Asegurar que los precios se actualicen correctamente después de la inicialización
        setTimeout(() => {
            updateTotal();
            updatePaymentSummary();
        }, 300);
        
        // Configuración para inicializar acordeón cuando sea necesario
        document.addEventListener('DOMContentLoaded', function() {
            if (document.getElementById('page-documentos') && !document.getElementById('page-documentos').classList.contains('hidden')) {
                initAccordionSections();
            }
        });
        
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
                    console.log("Navegando a la página documentos, inicializando acordeón");
                    // Esperar a que la página se muestre y luego inicializar el acordeón
                    setTimeout(initAccordionSections, 200);
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
        document.addEventListener('DOMContentLoaded', function() {
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
        });
    });

    <?php if (current_user_can('administrator')): ?>
    // Auto-rellenado para administradores
    document.addEventListener('DOMContentLoaded', function() {
        const adminAutofillBtn = document.getElementById('admin-autofill-btn');
        if (adminAutofillBtn) {
            adminAutofillBtn.addEventListener('click', async function() {
                alert('Iniciando auto-rellenado del formulario...');

                // PASO 1: VEHÍCULO
                const manufacturerSelect = document.querySelector('[name="manufacturer"]');
                manufacturerSelect.value = 'YAMAHA'; // Para barco, usar fabricante de MOTO.csv
                manufacturerSelect.dispatchEvent(new Event('change', { bubbles: true }));

                // Esperar a que carguen los modelos
                setTimeout(() => {
                    const modelSelect = document.querySelector('[name="model"]');
                    modelSelect.value = 'AR240'; // Ajustar según el CSV
                    modelSelect.dispatchEvent(new Event('change', { bubbles: true }));

                    document.querySelector('[name="purchase_price"]').value = '25000';
                    document.querySelector('[name="region"]').value = 'Madrid';
                    document.querySelector('[name="matriculation_date"]').value = '2020-01-15';

                    // Campos específicos para barcos
                    const nuevoNombre = document.querySelector('[name="nuevo_nombre"]');
                    if (nuevoNombre) nuevoNombre.value = 'BARCO TEST';

                    const nuevoPuerto = document.querySelector('[name="nuevo_puerto"]');
                    if (nuevoPuerto) nuevoPuerto.value = 'Puerto Test';

                    // Avanzar al siguiente paso
                    setTimeout(() => {
                        document.getElementById('nextButton').click();

                        // PASO 2: CLIENTE
                        setTimeout(() => {
                            document.querySelector('[name="customer_name"]').value = 'Admin Test';
                            document.querySelector('[name="customer_dni"]').value = '12345678Z';
                            document.querySelector('[name="customer_email"]').value = 'joanpinyol@hotmail.es';
                            document.querySelector('[name="customer_phone"]').value = '666777888';
                            // Nota: No hay campo domicilio en este formulario

                            // Avanzar a documentos
                            setTimeout(() => {
                                document.getElementById('nextButton').click();

                                // PASO 3: DOCUMENTOS (saltamos)
                                setTimeout(() => {
                                    document.getElementById('nextButton').click();

                                    // PASO 4: CUPÓN (saltamos)
                                    setTimeout(() => {
                                        document.getElementById('nextButton').click();

                                        // PASO 5: FIRMA
                                        setTimeout(() => {
                                            // Inicializar SignaturePad si no existe
                                            if (!window.signaturePad) {
                                                const canvas = document.querySelector('#signature-pad');
                                                if (canvas && typeof SignaturePad !== 'undefined') {
                                                    window.signaturePad = new SignaturePad(canvas);
                                                }
                                            }

                                            // Dejar que el admin firme manualmente
                                            alert('Formulario rellenado. Por favor, firme manualmente en el paso de firma.');

                                            // Avanzar al resumen
                                            setTimeout(() => {
                                                document.getElementById('nextButton').click();

                                                // DETENERSE AQUÍ - No avanzar al pago
                                                setTimeout(() => {
                                                    alert('✅ Formulario completado!\n\nAhora puedes:\n1. Revisar el resumen\n2. Avanzar manualmente al pago\n3. Completar con Stripe (MODO PRODUCCIÓN)');
                                                }, 500);
                                            }, 500);
                                        }, 500);
                                    }, 500);
                                }, 500);
                            }, 500);
                        }, 500);
                    }, 1000); // Más tiempo para cargar modelos
                }, 1500); // Aún más tiempo para la carga inicial
            });
        }
    });
    <?php endif; ?>

    </script>
    <?php
    return ob_get_clean();
}

/**
 * Registrar el shortcode [transferencia_propiedad_form]
 */
add_shortcode('transferencia_moto_form', 'transferencia_moto_shortcode');

/**
 * ENDPOINTS Y ACCIONES AJAX
 */

/**
 * 1. CREATE PAYMENT INTENT
 */
add_action('wp_ajax_create_payment_intent', 'tpm_create_payment_intent');
add_action('wp_ajax_nopriv_create_payment_intent', 'tpm_create_payment_intent');
function tpm_create_payment_intent() {
    // Asegurarse de que la respuesta es JSON
    header('Content-Type: application/json');

    // Comprobar si existe la biblioteca de Stripe
    $stripe_path = __DIR__ . '/vendor/stripe/stripe-php/init.php';
    
    if (!file_exists($stripe_path)) {
        echo json_encode([
            'error' => 'La biblioteca de Stripe no está instalada correctamente. Por favor, contacta con el administrador.'
        ]);
        wp_die();
    }

    try {
        require_once $stripe_path;

        // Configurar Stripe con la clave de producción
        \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

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
            'payment_method_types' => ['card'],
            'metadata' => [
                'source' => 'tramitfy_web',
                'form' => 'transferencia_barco'
            ]
        ]);

        echo json_encode([
            'clientSecret' => $paymentIntent->client_secret
        ]);
    } catch (\Exception $e) {
        echo json_encode([
            'error' => $e->getMessage()
        ]);
    }
    wp_die();
}

/**
 * 2. VALIDAR CUPÓN DE DESCUENTO
 */
add_action('wp_ajax_validate_coupon_code_XXX', 'tpm_validate_coupon_code');

/**
 * Procesamiento manual de pago (para situaciones donde Stripe API falla)
 */
add_action('wp_ajax_process_payment_manual', 'tpm_process_payment_manual');
add_action('wp_ajax_nopriv_process_payment_manual', 'tpm_process_payment_manual');
function tpm_process_payment_manual() {
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
    
    wp_mail($customer_email, $subject_customer, $message_customer);
    
    // Devolver éxito
    wp_send_json_success('Solicitud procesada correctamente');
    wp_die();
}
add_action('wp_ajax_nopriv_validate_coupon_code_XXX', 'tpm_validate_coupon_code');
function tpm_validate_coupon_code() {
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
 * 3. ENVÍO DE CORREOS
 */
add_action('wp_ajax_send_emails', 'tpm_send_emails');
add_action('wp_ajax_nopriv_send_emails', 'tpm_send_emails');
function tpm_send_emails() {
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
                            <td style="padding: 8px 10px 8px 0; width: 45%; vertical-align: top; color: #555; font-weight: 500;">Nuevo nombre moto de agua:</td>
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

    wp_mail($customer_email, $subject_customer, $message_customer, $headers);

    wp_send_json_success('Correo al cliente enviado.');
    wp_die();
}

/**
 * Helper function to log debug messages to a file we can access
 */
function tpm_debug_log($message) {
    $debug_log = get_template_directory() . '/tramitfy-moto-debug.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($debug_log, "[$timestamp] $message\n", FILE_APPEND);
    error_log($message);
}

/**
 * 4. SUBMIT FINAL FORM (documentos + firma)
 */
add_action('wp_ajax_submit_moto_form_tpm', 'tpm_submit_form');
add_action('wp_ajax_nopriv_submit_moto_form_tpm', 'tpm_submit_form');
function tpm_submit_form() {
    tpm_debug_log('[TPM] INICIO tpm_submit_form');

    try {
        $customer_name = sanitize_text_field($_POST['customer_name']);
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
        $signature = $_POST['signature'];
    
        tpm_debug_log('[TPM] Datos básicos procesados');

        $final_amount = isset($_POST['final_amount']) ? floatval($_POST['final_amount']) : 0;
        $current_transfer_tax = isset($_POST['current_transfer_tax']) ? floatval($_POST['current_transfer_tax']) : 0;
        $current_extra_fee = isset($_POST['current_extra_fee']) ? floatval($_POST['current_extra_fee']) : 0;
        $tasas_hidden = isset($_POST['tasas_hidden']) ? floatval($_POST['tasas_hidden']) : 0;
        $iva_hidden = isset($_POST['iva_hidden']) ? floatval($_POST['iva_hidden']) : 0;
        $honorarios_hidden = isset($_POST['honorarios_hidden']) ? floatval($_POST['honorarios_hidden']) : 0;

        tpm_debug_log('[TPM] Valores económicos recibidos: finalAmount=' . $final_amount . ', ITP=' . $current_transfer_tax . ', tasas=' . $tasas_hidden . ', iva=' . $iva_hidden . ', honorarios=' . $honorarios_hidden);
    
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
        tpm_debug_log('[TPM] Procesando firma');
        $signature_data = str_replace('data:image/png;base64,', '', $signature);
        $signature_data = str_replace(' ', '+', $signature_data);
        $signature_data = base64_decode($signature_data);
        $upload_dir = wp_upload_dir();
        $signature_image_name = 'signature_' . time() . '.png';
        $signature_image_path = $upload_dir['path'] . '/' . $signature_image_name;
        file_put_contents($signature_image_path, $signature_data);
        tpm_debug_log('[TPM] Firma guardada: ' . $signature_image_path);
    
        // Obtener base_price desde CSV si es necesario
        $base_price = 0;
        if (!$no_encuentro && $vehicle_type !== 'Moto de Agua') {
            $csv_file = ($vehicle_type === 'Moto de Agua') ? 'MOTO.csv' : 'MOTO.csv';
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
        tpm_debug_log('[TPM] Creando PDF autorización');
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
    
            $pdf->SetFont('Arial', '', 11);
            $pdf->Cell(50, 6, utf8_decode('Fecha Matriculación:'), 0, 0);
            $pdf->SetFont('Arial', 'B', 11);
            $pdf->Cell(0, 6, utf8_decode($matriculation_date), 0, 1);
        } else {
            $pdf->Cell(50, 6, utf8_decode('Fabricante:'), 0, 0);
            $pdf->SetFont('Arial', 'B', 11);
            $pdf->Cell(0, 6, utf8_decode($manual_manufacturer), 0, 1);
    
            $pdf->SetFont('Arial', '', 11);
            $pdf->Cell(50, 6, utf8_decode('Modelo:'), 0, 0);
            $pdf->SetFont('Arial', 'B', 11);
            $pdf->Cell(0, 6, utf8_decode($manual_model), 0, 1);
    
            $pdf->SetFont('Arial', 'I', 10);
            $pdf->SetTextColor(100, 100, 100);
            $pdf->Cell(0, 6, utf8_decode('(Fecha de matriculación no disponible)'), 0, 1);
            $pdf->SetTextColor(0, 0, 0);
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
        $declaracion = "Esta autorización incluye la presentación de documentación, pago de tasas administrativas, y cualquier otra gestión requerida por la autoridad competente para completar la transferencia.";
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
        tpm_debug_log('[TPM] PDF guardado: ' . $authorization_pdf_path);
    
        // Borrar imagen temporal de la firma
        unlink($signature_image_path);
        tpm_debug_log('[TPM] Firma temporal eliminada');
    
        // Manejar archivos subidos (múltiples archivos por campo)
        tpm_debug_log('[TPM] Procesando archivos adjuntos');
        $attachments = [$authorization_pdf_path];
        $upload_fields = [
            'upload_hoja_asiento',
            'upload_tarjeta_moto',
            'upload_dni_comprador',
            'upload_dni_vendedor',
            'upload_contrato_compraventa'
        ];
    
        foreach ($upload_fields as $field_name) {
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
                        }
                    }
                }
            }
        }

        tpm_debug_log('[TPM] Total archivos procesados: ' . count($attachments));

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
        tpm_debug_log('[TPM] Guardando datos async');
        $temp_dir = get_temp_dir() . 'tramitfy-async/';
        if (!file_exists($temp_dir)) {
            mkdir($temp_dir, 0755, true);
        }
        $async_file = $temp_dir . 'barco-' . $tramite_id . '-' . time() . '.json';
        file_put_contents($async_file, json_encode($async_data));
        tpm_debug_log('[TPM] Archivo async guardado: ' . $async_file);

        // COMENTADO: El procesamiento async no funciona en shared hosting y no es necesario
        // $script_path = get_template_directory() . '/process-barco-async.php';
        // $log_file = get_template_directory() . '/logs/async-barco.log';
        // $cmd = sprintf('php %s %s >> %s 2>&1 &',
        //     escapeshellarg($script_path),
        //     escapeshellarg($async_file),
        //     escapeshellarg($log_file)
        // );
        // exec($cmd);
        tpm_debug_log('[TPM] Continuando con emails (sin procesamiento async)');

        // Enviar email rápido de confirmación al cliente (sin adjuntos pesados)
        $customer_email_quick = $customer_email;
        $subject_customer_quick = '✓ Pago Recibido - Transferencia de Moto de Agua';
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
                                        <p style='margin: 0; color: #2e7d32; font-size: 15px; font-weight: 600;'>✓ Pago recibido correctamente</p>
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
                                            📋 Número de Trámite: <span style='color: #0d47a1;'>$tramite_id</span>
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
        tpm_debug_log('[TPM] Enviando email rápido al cliente: ' . $customer_email_quick);
        $mail_result = wp_mail($customer_email_quick, $subject_customer_quick, $message_customer_quick, $headers_quick);
        tpm_debug_log('[TPM] Email cliente resultado: ' . ($mail_result ? 'SUCCESS' : 'FAILED'));
    
        // Enviar email al ADMIN con detalles completos
        $admin_email = 'ipmgroup24@gmail.com';
        $subject_admin = "🔔 Nuevo Trámite - Transferencia Moto - $tramite_id";
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
                                    <p style='margin: 8px 0 0; color: rgba(255,255,255,0.95); font-size: 15px; font-weight: 500;'>Transferencia de Moto de Agua</p>
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
                                                        <td style='color: #666; font-size: 13px; padding: 6px 0;'>Tasas:</td>
                                                        <td align='right' style='color: #555; font-size: 14px; padding: 6px 0; font-weight: 600;'>" . number_format($tasas_hidden, 2) . " €</td>
                                                    </tr>
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
        tpm_debug_log('[TPM] Enviando email al admin: ' . $admin_email);
        $admin_mail_result = wp_mail($admin_email, $subject_admin, $message_admin, $headers_quick);
        tpm_debug_log('[TPM] Email admin resultado: ' . ($admin_mail_result ? 'SUCCESS' : 'FAILED'));

        // Enviar a TRAMITFY API con archivos adjuntos
        tpm_debug_log('[TPM] Enviando webhook con archivos adjuntos');
        $tramitfy_api_url = 'https://46-202-128-35.sslip.io/api/herramientas/motos/webhook';

        // Preparar archivos para enviar con CURLFile
        $file_fields = array();
        tpm_debug_log('[TPM] Total archivos a enviar: ' . count($attachments));
        foreach ($attachments as $index => $file_path) {
            if (file_exists($file_path)) {
                $cfile = new CURLFile($file_path, mime_content_type($file_path), basename($file_path));
                $file_fields["files[$index]"] = $cfile;
                tpm_debug_log('[TPM] Adjuntando archivo ' . $index . ': ' . basename($file_path));
            } else {
                tpm_debug_log('[TPM] Archivo NO existe: ' . $file_path);
            }
        }

        // Añadir payment_intent_id
        $payment_intent_id = isset($_POST['payment_intent_id']) ? sanitize_text_field($_POST['payment_intent_id']) : '';

        // Calcular honorarios netos (sin IVA)
        $honorarios_netos_calc = round(floatval($honorarios_hidden) / 1.21, 2);

        // Preparar datos completos (datos + archivos)
        // IMPORTANTE: Con multipart/form-data, todos los valores deben ser strings
        $form_data = array_merge(array(
            'tramiteId' => (string)$tramite_id,
            'tramiteType' => 'Transferencia Moto',
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
            'transferTax' => (string)floatval($current_transfer_tax),
            'extraFee' => (string)floatval($current_extra_fee),
            'tasas' => (string)floatval($tasas_hidden),
            'iva' => (string)floatval($iva_hidden),
            'honorarios' => (string)floatval($honorarios_hidden),
            'honorariosNetos' => (string)$honorarios_netos_calc,
            'paymentIntentId' => (string)$payment_intent_id,
            'status' => 'pending'
        ), $file_fields);

        tpm_debug_log('[TPM] Datos a enviar: tramiteId=' . $tramite_id . ', customerName=' . $customer_name . ', finalAmount=' . $final_amount);

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
        tpm_debug_log('[TPM] Webhook HTTP ' . $http_code . ' - Response: ' . $response);
    
        // Parsear la respuesta para obtener el ID del tracking
        $response_data = json_decode($response, true);
        $tracking_id = isset($response_data['id']) ? $response_data['id'] : time();
        $tracking_url = 'https://46-202-128-35.sslip.io/seguimiento/' . $tracking_id;
        tpm_debug_log('[TPM] Tracking URL: ' . $tracking_url);
    
        // Enviar email al cliente con el link de tracking
        $subject_customer = '✓ Trámite Registrado - Siga su Transferencia';
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
                                        <p style='margin: 0; color: #2e7d32; font-size: 15px; font-weight: 600;'>✓ Trámite registrado correctamente</p>
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
                                            📋 Número de Trámite
                                        </p>
                                        <p style='margin: 0 0 20px; color: #0d47a1; font-size: 20px; font-weight: 700; letter-spacing: 0.5px;'>
                                            {$tramite_id}
                                        </p>
                                        <p style='margin: 0 0 16px; color: #555; font-size: 14px;'>
                                            Puede consultar el estado de su trámite en cualquier momento:
                                        </p>
                                        <a href='{$tracking_url}' style='display: inline-block; background: linear-gradient(135deg, #1976d2 0%, #1565c0 100%); color: white; padding: 14px 32px; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 14px; box-shadow: 0 3px 8px rgba(25,118,210,0.3); margin-bottom: 16px;'>
                                            🔍 Ver Estado del Trámite
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
                                                            <span style='color: #1976d2; font-size: 18px; margin-right: 10px;'>1️⃣</span>
                                                            <span style='color: #555; font-size: 14px;'>Revisaremos su documentación</span>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style='padding: 10px 0; border-bottom: 1px solid #e0e0e0;'>
                                                            <span style='color: #1976d2; font-size: 18px; margin-right: 10px;'>2️⃣</span>
                                                            <span style='color: #555; font-size: 14px;'>Tramitaremos la transferencia ante los organismos competentes</span>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style='padding: 10px 0;'>
                                                            <span style='color: #1976d2; font-size: 18px; margin-right: 10px;'>3️⃣</span>
                                                            <span style='color: #555; font-size: 14px;'>Le enviaremos la documentación final</span>
                                                        </td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                    </table>

                                    <p style='margin: 0; color: #666; font-size: 13px; line-height: 1.6; padding: 16px; background-color: #fff3cd; border-left: 3px solid #ffc107; border-radius: 4px;'>
                                        💡 <strong>Importante:</strong> Le notificaremos por email cualquier actualización o si necesitamos información adicional.
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
        tpm_debug_log('[TPM] Enviando email tracking al cliente: ' . $customer_email);
        $tracking_mail_result = wp_mail($customer_email, $subject_customer, $message_customer, $headers_customer);
        tpm_debug_log('[TPM] Email tracking resultado: ' . ($tracking_mail_result ? 'SUCCESS' : 'FAILED'));
    
        // RESPONDER AL CLIENTE CON LA URL DE TRACKING
        tpm_debug_log('[TPM] Enviando respuesta JSON al cliente');
        wp_send_json_success(array(
            'message' => 'Formulario procesado correctamente',
            'tramite_id' => $tramite_id,
            'tracking_id' => $tracking_id,
            'tracking_url' => $tracking_url
        ));
        tpm_debug_log('[TPM] FIN tpm_submit_form');

    } catch (Exception $e) {
        tpm_debug_log('[TPM] ERROR CRÍTICO: ' . $e->getMessage());
        tpm_debug_log('[TPM] Archivo: ' . $e->getFile() . ' Línea: ' . $e->getLine());
        tpm_debug_log('[TPM] Stack trace: ' . $e->getTraceAsString());
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
function tpm_process_async($async_file) {
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
    wp_mail($admin_email, $subject_admin, $message_admin, $headers, $attachments);

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
    $tramitfy_api_url = 'https://46-202-128-35.sslip.io/api/herramientas/motos/webhook';

    // Preparar archivos para enviar
    $file_fields = array();
    if (!empty($attachments)) {
        foreach ($attachments as $index => $file_path) {
            if (file_exists($file_path)) {
                $cfile = new CURLFile($file_path, mime_content_type($file_path), basename($file_path));
                $file_fields["files[$index]"] = $cfile;
            }
        }
    }

    // Preparar datos del formulario
    $form_data = array_merge(array(
        'tramiteId' => $tramite_id,
        'tramiteType' => 'Transferencia Moto',
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
        'transferTax' => floatval($current_transfer_tax),
        'extraFee' => floatval($current_extra_fee),
        'tasas' => floatval($tasas_hidden),
        'iva' => floatval($iva_hidden),
        'honorarios' => floatval($honorarios_hidden),
        'authorizationPdfUrl' => isset($authorization_pdf_path) ? $upload_dir['url'] . '/' . basename($authorization_pdf_path) : '',
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

