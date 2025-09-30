<?php
// Asegurarse de que el archivo no sea accedido directamente
defined('ABSPATH') || exit;

// Cargar Stripe library ANTES de las funciones (IGUAL QUE RECUPERAR DOCUMENTACIÓN)
require_once(get_template_directory() . '/vendor/autoload.php');

// Configuración de Stripe AL NIVEL GLOBAL (IGUAL QUE RECUPERAR DOCUMENTACIÓN)
define('HOJA_ASIENTO_STRIPE_MODE', 'test'); // 'test' o 'live'

define('HOJA_ASIENTO_STRIPE_TEST_PUBLIC_KEY', 'pk_test_YOUR_STRIPE_TEST_PUBLIC_KEY');
define('HOJA_ASIENTO_STRIPE_TEST_SECRET_KEY', 'sk_test_YOUR_STRIPE_TEST_SECRET_KEY');

define('HOJA_ASIENTO_STRIPE_LIVE_PUBLIC_KEY', 'pk_live_YOUR_STRIPE_LIVE_PUBLIC_KEY');
define('HOJA_ASIENTO_STRIPE_LIVE_SECRET_KEY', 'sk_live_YOUR_STRIPE_LIVE_SECRET_KEY');

define('HOJA_ASIENTO_PRECIO_BASE', 29.95);
define('HOJA_ASIENTO_API_URL', 'https://46-202-128-35.sslip.io/api/herramientas/hoja-asiento/webhook');

/**
 * Función principal para generar y mostrar el formulario en el frontend
 */
function hoja_asiento_form_shortcode() {
    // Encolar scripts y estilos
    wp_enqueue_style('hoja-asiento-form-style', get_template_directory_uri() . '/style.css', array(), filemtime(get_template_directory() . '/style.css'));
    wp_enqueue_script('stripe', 'https://js.stripe.com/v3/', array(), null, false);
    wp_enqueue_script('signature-pad', 'https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js', array(), null, false);
    wp_enqueue_script('font-awesome', 'https://kit.fontawesome.com/a076d05399.js', array(), null, false);

    // Generar ID de trámite
    $prefix = 'TMA-HOJA';
    $counter_option = (HOJA_ASIENTO_STRIPE_MODE === 'test') ? 'tma_hoja_counter_test' : 'tma_hoja_counter';
    $date_part = date('Ymd');
    $current_cnt = get_option($counter_option, 0);
    $current_cnt++;
    update_option($counter_option, $current_cnt);
    $secuencial = str_pad($current_cnt, 6, '0', STR_PAD_LEFT);
    $tramite_id = $prefix . '-' . $date_part . '-' . $secuencial;

    // Iniciar el buffering de salida
    ob_start();
    ?>
    <!-- Estilos personalizados para el formulario mejorado -->
    <style>
        /* Mejoras estéticas para las páginas de documentación y pasos */
        .requirements-screen {
            padding: 40px;
            max-width: 800px;
            margin: 0 auto;
            background-color: white;
            border-radius: var(--radius-lg);
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            border: 1px solid rgba(var(--primary), 0.08);
            background-image: linear-gradient(to bottom, rgba(var(--primary-bg), 0.4) 0%, rgba(255,255,255,1) 250px);
        }
        
        .requirements-heading {
            color: rgb(var(--primary));
            font-size: 2rem;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            text-shadow: 0 1px 2px rgba(0,0,0,0.05);
            position: relative;
        }
        
        .requirements-heading:after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 3px;
            background: linear-gradient(90deg, rgba(var(--primary), 0.2) 0%, rgba(var(--primary), 0.8) 50%, rgba(var(--primary), 0.2) 100%);
            border-radius: 3px;
        }
        
        .requirements-heading i {
            font-size: 1.8rem;
            color: rgb(var(--primary));
            background: rgba(var(--primary), 0.1);
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }
        
        .requirements-subheading {
            color: rgb(var(--neutral-700));
            font-size: 1.15rem;
            line-height: 1.5;
            text-align: center;
            max-width: 650px;
            margin: 0 auto 25px;
            font-weight: 500;
        }
        
        .requirements-container {
            background-color: rgba(var(--primary-bg), 0.6);
            border-radius: var(--radius-lg);
            padding: 30px;
            margin-bottom: 30px;
            border: 1px solid rgba(var(--primary), 0.1);
            box-shadow: 0 5px 15px rgba(0,0,0,0.03);
            position: relative;
            overflow: hidden;
        }
        
        .requirements-container:before {
            content: '';
            position: absolute;
            top: -10px;
            right: -10px;
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, rgba(var(--primary), 0.03), rgba(var(--primary), 0.08));
            border-radius: 0 0 0 100%;
            z-index: 0;
        }
        
        .requirements-list {
            list-style: none;
            padding: 0;
            margin: 0;
            position: relative;
            z-index: 1;
        }
        
        .requirements-list li {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            color: rgb(var(--neutral-800));
            font-size: 1.05rem;
            font-weight: 500;
            transition: transform 0.3s ease;
            position: relative;
        }
        
        .requirements-list li:hover {
            transform: translateX(5px);
        }
        
        .requirements-list li i {
            color: white;
            margin-right: 15px;
            font-size: 1rem;
            width: 30px;
            height: 30px;
            text-align: center;
            line-height: 30px;
            background-color: rgb(var(--primary));
            border-radius: 50%;
            box-shadow: 0 2px 10px rgba(var(--primary), 0.3);
        }
        
        .step-item {
            display: flex;
            align-items: center;
            background-color: rgba(var(--primary-bg), 0.7);
            border-radius: var(--radius-lg);
            padding: 20px;
            transition: all 0.3s ease;
            border-left: 3px solid rgb(var(--primary));
            box-shadow: 0 3px 10px rgba(0,0,0,0.03);
            position: relative;
            overflow: hidden;
        }
        
        .step-item:after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, rgba(var(--primary), 0.03) 0%, transparent 50%);
            pointer-events: none;
        }
        
        .step-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            border-left-width: 5px;
        }
        
        .step-item .step-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, rgb(var(--primary)), rgb(var(--primary-light)));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            margin-right: 20px;
            flex-shrink: 0;
            box-shadow: 0 3px 10px rgba(var(--primary), 0.3);
            border: 2px solid rgba(255,255,255,0.8);
            font-size: 1.1rem;
        }
        
        .step-item .step-name {
            font-weight: 700;
            font-size: 1.15rem;
            color: rgb(var(--primary));
            margin-bottom: 5px;
        }
        
        .step-item .step-desc {
            font-size: 0.95rem;
            color: rgb(var(--neutral-700));
            line-height: 1.4;
            font-weight: 500;
        }
        
        .start-button {
            display: block;
            margin: 30px auto 0;
            padding: 0 35px;
            height: 54px;
            font-size: 1.15rem;
            font-weight: 600;
            background: linear-gradient(135deg, rgb(var(--primary)), rgb(var(--primary-light)));
            border: none;
            box-shadow: 0 5px 20px rgba(var(--primary), 0.4);
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .start-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(var(--primary), 0.5);
        }
        
        .start-button:active {
            transform: translateY(-1px);
        }
        
        .start-button:after {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: rgba(255, 255, 255, 0.2);
            transform: rotate(45deg);
            animation: startButtonShine 3s infinite;
        }
        
        @keyframes startButtonShine {
            0% { transform: scale(0) rotate(45deg); opacity: 0; }
            80% { transform: scale(0) rotate(45deg); opacity: 0.5; }
            81% { transform: scale(4) rotate(45deg); opacity: 0.5; }
            100% { transform: scale(50) rotate(45deg); opacity: 0; }
        }
        
        /* Estilos mejorados para el menú de navegación circular */
        .process-navigation {
            position: sticky;
            top: 0;
            background: linear-gradient(170deg, white, rgba(var(--neutral-50), 0.97));
            padding: 20px 25px;
            border-bottom: 1px solid rgba(var(--primary), 0.1);
            z-index: 1000;
            box-shadow: 0 5px 25px rgba(0,0,0,0.06);
            display: block !important; /* Forzar visibilidad */
            margin-bottom: 30px; /* Añadir espacio debajo del menú */
            backdrop-filter: blur(10px);
            transition: all 0.4s ease;
            width: 100%;
            box-sizing: border-box;
        }
        
        /* Efecto al hacer scroll */
        .process-navigation.scrolled {
            padding: 15px 25px;
            background: rgba(255, 255, 255, 0.95);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        /* Ocultar elementos redundantes en el formulario principal */
        .interactive-form-container .progress-container {
            display: none !important;
        }
        
        /* Posicionamiento del menú de navegación dentro del formulario */
        .process-navigation {
            position: sticky;
            top: 0;
            z-index: 1000;
            background: white;
            border-bottom: 1px solid rgba(var(--neutral-200));
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        
        /* Estructura básica de los pasos - diseño mejorado */
        .process-steps {
            display: flex;
            max-width: 1000px;
            margin: 0 auto;
            justify-content: space-between;
            position: relative;
            padding: 0 15px;
            flex-wrap: nowrap;
            width: 100%;
        }
        
        /* Línea de base para la barra de progreso horizontal */
        .process-steps::before {
            content: '';
            position: absolute;
            top: 28px;
            left: 50px;
            right: 50px;
            height: 4px;
            background: linear-gradient(90deg, 
                rgba(var(--neutral-200), 0.5) 0%, 
                rgba(var(--neutral-300), 0.7) 50%, 
                rgba(var(--neutral-200), 0.5) 100%);
            z-index: 1;
            border-radius: 4px;
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);
        }
        
        /* Barra de progreso principal que atraviesa las circunferencias */
        .process-steps::after {
            content: '';
            position: absolute;
            top: 28px;
            left: 50px;
            height: 4px;
            width: 0%; /* Será actualizado dinámicamente con JS */
            background: linear-gradient(90deg, 
                rgb(var(--primary)) 0%, 
                rgb(var(--primary-light)) 70%, 
                rgba(var(--primary-light), 0.8) 100%);
            z-index: 2;
            border-radius: 4px;
            transition: width 0.8s cubic-bezier(0.34, 1.56, 0.64, 1);
            box-shadow: 0 0 10px rgba(var(--primary), 0.4);
        }
        
        .process-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 2;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            width: 25%;
            opacity: 0.85;
            padding: 0 10px;
        }
        
        .process-step .step-number {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: linear-gradient(135deg, rgb(var(--neutral-100)), rgb(var(--neutral-200)));
            color: rgb(var(--neutral-700));
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 12px;
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            border: 3px solid white;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            position: relative;
            z-index: 3;
            overflow: hidden;
        }
        
        /* Efecto de destello/resplandor alrededor del número */
        .process-step .step-number::before {
            content: '';
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            border-radius: 50%;
            background: radial-gradient(circle at center, 
                rgba(255,255,255,0.9) 0%, 
                rgba(255,255,255,0) 70%);
            opacity: 0;
            z-index: 2;
            transition: opacity 0.5s ease;
            pointer-events: none;
        }
        
        /* Icono dentro del círculo */
        .process-step .step-icon {
            font-size: 20px;
            position: absolute;
            opacity: 0;
            transform: scale(0.5);
            transition: all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
            z-index: 3;
        }
        
        /* Número dentro del círculo */
        .process-step .step-digit {
            font-weight: 600;
            font-size: 20px;
            transition: all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
            z-index: 3;
        }
        
        .process-step .step-info {
            text-align: center;
            width: 100%;
            transition: all 0.3s ease;
        }
        
        .process-step .step-name {
            font-size: 14px;
            font-weight: 600;
            color: rgb(var(--neutral-700));
            transition: all 0.3s ease;
            margin-bottom: 3px;
        }
        
        .process-step .step-description {
            font-size: 11px;
            color: rgb(var(--neutral-500));
            transition: all 0.3s ease;
            opacity: 0.9;
            max-width: 140px;
            margin: 0 auto;
        }
        
        /* Efecto hover mejorado */
        .process-step:hover .step-number::before {
            opacity: 0.6;
            animation: pulse-light 2s infinite;
        }
        
        @keyframes pulse-light {
            0% { opacity: 0.2; }
            50% { opacity: 0.6; }
            100% { opacity: 0.2; }
        }
        
        /* Estados de los pasos */
        .process-step.active {
            opacity: 1;
        }
        
        .process-step.active .step-number {
            background-color: rgb(var(--primary));
            color: white;
            transform: scale(1.1);
            box-shadow: 0 0 12px rgba(var(--primary), 0.5);
            animation: pulse 2s infinite;
            border-color: rgba(var(--primary), 0.1);
        }
        
        .process-step.active .step-name {
            color: rgb(var(--primary));
            font-weight: 600;
            text-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .process-step.completed .step-number {
            background-color: rgb(var(--success));
            color: white;
            border-color: rgba(var(--success), 0.2);
        }
        
        .process-step.completed .step-number::before {
            content: '✓';
            position: absolute;
            font-size: 22px;
            font-weight: bold;
        }
        
        .process-step:hover .step-number {
            transform: translateY(-3px);
            box-shadow: 0 5px 10px rgba(0,0,0,0.1);
        }
        
        .process-step.clickable:hover .step-name {
            color: rgb(var(--primary));
        }
        
        /* Animación de pulso para el paso activo */
        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(var(--primary), 0.4);
            }
            70% {
                box-shadow: 0 0 0 8px rgba(var(--primary), 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(var(--primary), 0);
            }
        }
        
        /* Estado no disponible para pasos futuros */
        .process-step.not-available .step-number {
            animation: shake 0.5s ease-in-out;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            50% { transform: translateX(5px); }
            75% { transform: translateX(-5px); }
        }
        
        /* Estilos mejorados para las barras de progreso */
        .process-step .step-progress {
            transition: width 0.6s ease-in-out;
            background: linear-gradient(90deg, rgb(var(--primary)) 0%, rgb(var(--primary-light)) 100%);
            height: 4px; /* Ligeramente más alto para mejor visibilidad */
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(var(--primary), 0.2);
        }
        
        /* Mantener menú igual en tablets */
        @media (max-width: 768px) {
            /* Mantenemos los mismos estilos que en desktop */
            .process-steps::before {
                left: 50px;
                right: 50px;
            }
            
            .process-step .step-number {
                width: 56px;
                height: 56px;
                font-size: 20px;
            }
            
            .process-step .step-name {
                font-size: 14px;
            }
            
            .process-step .step-description {
                font-size: 11px;
                max-width: 140px;
                display: block;
            }
        }
        
        @media (max-width: 576px) {
            .process-navigation {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                scroll-behavior: smooth;
                scrollbar-width: none; /* Para Firefox */
                padding: 20px 15px;
            }
            
            .process-navigation::-webkit-scrollbar {
                display: none; /* Para Chrome y Safari */
            }
            
            .process-steps {
                min-width: 800px; /* Asegurar que el menú tenga espacio suficiente */
                justify-content: space-between;
                padding: 0;
                overflow-x: visible;
            }
            
            .process-step {
                width: 25%;
                min-width: auto;
                margin-right: 0;
                flex-shrink: 0;
            }
            
            /* Mantener tamaños consistentes con versión desktop */
            .process-step .step-number {
                width: 56px;
                height: 56px;
                font-size: 20px;
            }
            
            .process-step .step-name {
                font-size: 14px;
            }
            
            .process-step .step-description {
                display: block; /* Mostrar descripciones también en móvil */
                font-size: 11px;
                max-width: 140px;
            }
        }

        /* Estados de los pasos */
        .process-step.active {
            opacity: 1;
        }

        .process-step.active .step-number {
            background-color: rgb(var(--primary));
            color: white;
            transform: scale(1.1);
            box-shadow: 0 0 0 4px rgba(var(--primary), 0.2);
        }

        .process-step.active .step-name {
            color: rgb(var(--primary));
            font-weight: 600;
        }

        .process-step.completed .step-number {
            background-color: rgb(var(--success));
            color: white;
        }

        .process-step.completed .step-progress {
            width: 100%;
        }

        .process-step:hover .step-number {
            transform: translateY(-3px);
            box-shadow: 0 5px 10px rgba(0,0,0,0.1);
        }

        .process-step.clickable:hover .step-name {
            color: rgb(var(--primary));
        }

        /* Mantener menú igual en tablets */
        @media (max-width: 768px) {
            /* Mantenemos los mismos estilos que en desktop */
            .process-steps::before {
                left: 50px;
                right: 50px;
            }
            
            .process-step .step-number {
                width: 56px;
                height: 56px;
                font-size: 20px;
            }
            
            .process-step .step-name {
                font-size: 14px;
            }
            
            .process-step .step-description {
                font-size: 11px;
                max-width: 140px;
                display: block;
            }
        }

        @media (max-width: 576px) {
            .process-navigation {
                padding: 12px 4px;
                overflow-x: auto;
                overflow-y: hidden;
                -webkit-overflow-scrolling: touch;
                scroll-behavior: smooth;
            }
            
            .process-steps {
                width: 100%;
                min-width: unset;
                justify-content: space-between;
                padding: 0;
                overflow: visible;
                display: flex;
                gap: 5px;
            }
            
            .process-step {
                width: 25%;
                min-width: auto;
                margin-right: 0;
                flex-shrink: 0;
                padding: 0 2px;
                touch-action: manipulation;
            }
            
            /* Reducir tamaños proporcionalmente para que quepan */
            .process-step .step-number {
                width: 32px; /* Ligeramente más grande para mejor interacción táctil */
                height: 32px;
                font-size: 14px;
                box-shadow: 0 2px 5px rgba(0,0,0,0.15); /* Sombra más visible */
            }
            
            .process-step .step-digit {
                font-size: 14px;
                font-weight: 700; /* Más negrita para mejor legibilidad */
            }
            
            .process-step .step-name {
                font-size: 10px; /* Ligeramente más grande */
                margin-top: 6px;
                white-space: normal;
                word-break: keep-all;
                line-height: 1.2;
                font-weight: 600; /* Más negrita para mejor legibilidad */
                max-width: 90%; /* Limitar ancho para evitar desbordamiento */
                text-align: center;
            }
            
            .process-step .step-description {
                display: none; /* Mantenemos ocultas las descripciones para ahorrar espacio */
            }
            
            /* Ajustar línea que une los círculos */
            .process-steps::before {
                left: 15px;
                right: 15px;
                top: 16px; /* Ajustado para centrar en los nuevos círculos */
                height: 3px;
            }
            
            /* Ajustar línea de progreso */
            .process-steps::after {
                top: 16px; /* Ajustado para centrar en los nuevos círculos */
                left: 15px;
                height: 3px;
            }
            
            /* Ajuste del icono en el paso activo */
            .process-step.active .step-number {
                transform: scale(1.15); /* Mayor escala para destacar más */
                box-shadow: 0 0 10px rgba(var(--primary), 0.4); /* Sombra más visible */
            }
            
            /* Mejorar visibilidad del paso activo */
            .process-step.active .step-name {
                color: rgb(var(--primary));
                font-weight: 700;
            }
            
            /* Mejorar visibilidad del paso completado */
            .process-step.completed .step-number {
                box-shadow: 0 0 8px rgba(var(--success), 0.3);
            }
}
        }

        /* Nuevo menú superior moderno */
.main-header {
    position: sticky;
    top: 0;
    left: 0;
    width: 100%;
    background-color: #ffffff;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    z-index: var(--z-header);
    padding: 12px 20px;
    transition: all 0.3s ease;
    display: none; /* Por defecto oculto, se mostrará solo en las páginas del formulario */
}

.main-header.visible {
    display: flex;
}

.header-container {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    max-width: 1200px;
    margin: 0 auto;
}

.header-logo {
    display: flex;
    align-items: center;
}

.header-logo img {
    height: 32px;
    margin-right: 10px;
}

.header-logo-text {
    font-weight: 600;
    font-size: 1.2rem;
    color: rgb(var(--primary));
}

.header-nav {
    display: flex;
    align-items: center;
}

.header-progress {
    display: flex;
    align-items: center;
    margin-right: 20px;
}

.header-progress-bar {
    width: 120px;
    height: 6px;
    background-color: rgba(var(--neutral-200));
    border-radius: 10px;
    overflow: hidden;
    margin-right: 10px;
}

.header-progress-fill {
    height: 100%;
    background: linear-gradient(90deg, rgb(var(--primary)) 0%, rgb(var(--primary-light)) 100%);
    width: 0%;
    transition: width 0.5s ease;
    border-radius: 10px;
}

.header-progress-text {
    font-size: 0.85rem;
    font-weight: 500;
    color: rgb(var(--neutral-700));
}

.header-actions {
    display: flex;
    align-items: center;
}

.header-help-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background-color: rgba(var(--primary-bg));
    color: rgb(var(--primary));
    cursor: pointer;
    transition: all 0.2s ease;
    margin-right: 12px;
    border: none;
}

.header-help-btn:hover {
    background-color: rgba(var(--primary), 0.15);
    transform: translateY(-2px);
}

.header-support-btn {
    padding: 8px 15px;
    border-radius: 20px;
    background-color: white;
    border: 1px solid rgb(var(--primary));
    color: rgb(var(--primary));
    font-size: 0.85rem;
    font-weight: 500;
    transition: all 0.2s ease;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 6px;
}

.header-support-btn:hover {
    background-color: rgba(var(--primary-bg));
    transform: translateY(-2px);
}
        /* Tipografía corporativa */
        @import url('https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap');

        /* Variables de color */
        :root {
            /* Colores principales */
            --primary: 1, 109, 134; /* #016d86 */
            --primary-dark: 0, 86, 106;
            --primary-light: 0, 125, 156;
            --primary-bg: 236, 247, 255;
            
            --secondary: 0, 123, 255; /* #007bff */
            --secondary-dark: 0, 105, 217;
            --secondary-light: 50, 145, 255;
            --secondary-bg: 235, 245, 253;
            
            --neutral: 70, 80, 95;
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
            
            /* Espaciado */
            --spacing-xs: 0.25rem;
            --spacing-sm: 0.5rem;
            --spacing-md: 1rem;
            --spacing-lg: 1.5rem;
            --spacing-xl: 2rem;
            
            /* Bordes */
            --radius-sm: 0.25rem;
            --radius-md: 0.375rem;
            --radius-lg: 0.5rem;
            --radius-xl: 1rem;
            
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
            --z-header: 1000;
        }

        /* Reset y configuración base */
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        /* Estilos generales para el formulario */
        #hoja-asiento-form {
            max-width: 1000px;
            margin: 40px auto;
            font-family: 'Roboto', 'Helvetica Neue', Helvetica, Arial, sans-serif;
            background-color: #ffffff;
            box-shadow: var(--shadow-md);
            border-radius: var(--radius-xl);
            overflow: hidden;
            position: relative;
        }

        #hoja-asiento-form .full-width {
            max-width: none;
            margin: 0;
            width: 100%;
        }

        /* Estilos para la portada y páginas de información (sin cambios) */
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
        
        /* Estilos completos para el elemento 3D en la portada */
        .form-3d-container {
            perspective: 1000px;
            width: 100%;
            max-width: 450px;
            margin: 0 auto;
        }

        .form-3d-element {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transform: rotateY(10deg) rotateX(5deg);
            transition: all 0.5s ease;
            overflow: hidden;
            /* Eliminada la animación continua para evitar vibraciones visuales */
        }

        .form-3d-header {
            background: linear-gradient(90deg, rgb(var(--primary)) 0%, rgb(var(--primary-light)) 100%);
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .form-3d-title {
            color: white;
            font-weight: 600;
        }

        .form-3d-steps {
            display: flex;
            gap: 5px;
        }

        .form-3d-step {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background-color: rgba(255,255,255,0.3);
        }

        .form-3d-step.active {
            background-color: white;
        }

        .form-3d-content {
            padding: 20px;
        }

        .form-3d-field {
            margin-bottom: 15px;
        }

        .form-3d-field label {
            display: block;
            font-size: 13px;
            color: rgb(var(--neutral-600));
            margin-bottom: 5px;
        }

        .form-3d-input {
            height: 16px;
            width: 100%;
            background-color: rgb(var(--neutral-100));
            border-radius: 4px;
        }

        .form-3d-input.active {
            background-color: rgba(var(--primary), 0.1);
        }

        .form-3d-button {
            background: rgb(var(--primary));
            color: white;
            text-align: center;
            padding: 10px;
            border-radius: 5px;
            margin-top: 10px;
            font-size: 14px;
            font-weight: 500;
        }

        @keyframes float {
            0% { transform: rotateY(10deg) rotateX(5deg) translateY(0px); }
            50% { transform: rotateY(8deg) rotateX(3deg) translateY(-10px); }
            100% { transform: rotateY(10deg) rotateX(5deg) translateY(0px); }
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
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(var(--primary), 0.3);
            transition: all 0.3s ease;
        }
        
        .marketing-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(var(--primary), 0.4);
        }
        
        .marketing-button:active {
            transform: translateY(-1px);
        }
        
        .marketing-button:after {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: rgba(255, 255, 255, 0.2);
            transform: rotate(45deg);
            animation: shine 3s infinite;
        }
        
        @keyframes shine {
            0% { transform: scale(0) rotate(45deg); opacity: 0; }
            80% { transform: scale(0) rotate(45deg); opacity: 0.5; }
            81% { transform: scale(4) rotate(45deg); opacity: 0.5; }
            100% { transform: scale(50) rotate(45deg); opacity: 0; }
        }
        
        .pulse-button {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(var(--primary), 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(var(--primary), 0); }
            100% { box-shadow: 0 0 0 0 rgba(var(--primary), 0); }
        }
        
        /* Estilos completados para las páginas de requisitos y pasos */
        .requirements-screen {
            padding: 40px;
            max-width: 800px;
            margin: 0 auto;
            background-color: white;
            border-radius: var(--radius-lg);
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            border: 1px solid rgba(var(--primary), 0.08);
            background-image: linear-gradient(to bottom, rgba(var(--primary-bg), 0.4) 0%, rgba(255,255,255,1) 250px);
        }

        .requirements-header {
            margin-bottom: 25px;
            text-align: center;
        }

        .requirements-heading {
            color: rgb(var(--primary));
            font-size: 2rem;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            text-shadow: 0 1px 2px rgba(0,0,0,0.05);
            position: relative;
        }
        
        .requirements-heading:after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 3px;
            background: linear-gradient(90deg, rgba(var(--primary), 0.2) 0%, rgba(var(--primary), 0.8) 50%, rgba(var(--primary), 0.2) 100%);
            border-radius: 3px;
        }
        
        .requirements-heading i {
            font-size: 1.8rem;
            color: rgb(var(--primary));
            background: rgba(var(--primary), 0.1);
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }

        .requirements-subheading {
            color: rgb(var(--neutral-700));
            font-size: 1.15rem;
            line-height: 1.5;
            text-align: center;
            max-width: 650px;
            margin: 0 auto 25px;
            font-weight: 500;
        }

        .requirements-container {
            background-color: rgba(var(--primary-bg), 0.6);
            border-radius: var(--radius-lg);
            padding: 30px;
            margin-bottom: 30px;
            border: 1px solid rgba(var(--primary), 0.1);
            box-shadow: 0 5px 15px rgba(0,0,0,0.03);
            position: relative;
            overflow: hidden;
        }
        
        .requirements-container:before {
            content: '';
            position: absolute;
            top: -10px;
            right: -10px;
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, rgba(var(--primary), 0.03), rgba(var(--primary), 0.08));
            border-radius: 0 0 0 100%;
            z-index: 0;
        }

        .requirements-list {
            list-style: none;
            padding: 0;
            margin: 0;
            position: relative;
            z-index: 1;
        }

        .requirements-list li {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            color: rgb(var(--neutral-800));
            font-size: 1.05rem;
            font-weight: 500;
            transition: transform 0.3s ease;
            position: relative;
        }
        
        .requirements-list li:hover {
            transform: translateX(5px);
        }

        .requirements-list li:last-child {
            margin-bottom: 0;
        }

        .requirements-list li i {
            color: white;
            margin-right: 15px;
            font-size: 1rem;
            width: 30px;
            height: 30px;
            text-align: center;
            line-height: 30px;
            background-color: rgb(var(--primary));
            border-radius: 50%;
            box-shadow: 0 2px 10px rgba(var(--primary), 0.3);
        }

        .start-button {
            display: block;
            margin: 30px auto 0;
            padding: 0 35px;
            height: 54px;
            font-size: 1.15rem;
            font-weight: 600;
            background: linear-gradient(135deg, rgb(var(--primary)), rgb(var(--primary-light)));
            border: none;
            box-shadow: 0 5px 20px rgba(var(--primary), 0.4);
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .start-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(var(--primary), 0.5);
        }
        
        .start-button:active {
            transform: translateY(-1px);
        }
        
        .start-button:after {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: rgba(255, 255, 255, 0.2);
            transform: rotate(45deg);
            animation: startButtonShine 3s infinite;
        }
        
        @keyframes startButtonShine {
            0% { transform: scale(0) rotate(45deg); opacity: 0; }
            80% { transform: scale(0) rotate(45deg); opacity: 0.5; }
            81% { transform: scale(4) rotate(45deg); opacity: 0.5; }
            100% { transform: scale(50) rotate(45deg); opacity: 0; }
        }

        .welcome-steps {
            margin-bottom: 30px;
        }

        .steps-container {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .step-item {
            display: flex;
            align-items: center;
            background-color: rgba(var(--primary-bg), 0.7);
            border-radius: var(--radius-lg);
            padding: 20px;
            transition: all 0.3s ease;
            border-left: 3px solid rgb(var(--primary));
            box-shadow: 0 3px 10px rgba(0,0,0,0.03);
            position: relative;
            overflow: hidden;
        }
        
        .step-item:after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, rgba(var(--primary), 0.03) 0%, transparent 50%);
            pointer-events: none;
        }

        .step-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            border-left-width: 5px;
        }

        .step-number {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background-color: rgb(var(--primary));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 15px;
            flex-shrink: 0;
        }

        .step-info {
            flex: 1;
        }

        .step-name {
            font-weight: 600;
            color: rgb(var(--neutral-800));
            margin-bottom: 5px;
        }

        .step-desc {
            color: rgb(var(--neutral-600));
            font-size: 0.9rem;
        }

        /* Estilos para el contenedor principal del formulario */
        /* Nuevo menú superior moderno */
        .main-header {
            position: sticky;
            top: 0;
            left: 0;
            width: 100%;
            background-color: #ffffff;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            z-index: var(--z-header);
            padding: 12px 20px;
            transition: all 0.3s ease;
            display: none; /* Por defecto oculto, se mostrará solo en las páginas del formulario */
        }
        
        .main-header.visible {
            display: flex;
        }
        
        .header-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header-logo {
            display: flex;
            align-items: center;
        }
        
        .header-logo img {
            height: 32px;
            margin-right: 10px;
        }
        
        .header-logo-text {
            font-weight: 600;
            font-size: 1.2rem;
            color: rgb(var(--primary));
        }
        
        .header-nav {
            display: flex;
            align-items: center;
        }
        
        .header-progress {
            display: flex;
            align-items: center;
            margin-right: 20px;
        }
        
        .header-progress-bar {
            width: 120px;
            height: 6px;
            background-color: rgba(var(--neutral-200));
            border-radius: 10px;
            overflow: hidden;
            margin-right: 10px;
        }
        
        .header-progress-fill {
            height: 100%;
            background: linear-gradient(90deg, rgb(var(--primary)) 0%, rgb(var(--primary-light)) 100%);
            width: 0%;
            transition: width 0.5s ease;
            border-radius: 10px;
        }
        
        .header-progress-text {
            font-size: 0.85rem;
            font-weight: 500;
            color: rgb(var(--neutral-700));
        }
        
        .header-actions {
            display: flex;
            align-items: center;
        }
        
        .header-help-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background-color: rgba(var(--primary-bg));
            color: rgb(var(--primary));
            cursor: pointer;
            transition: all 0.2s ease;
            margin-right: 12px;
            border: none;
        }
        
        .header-help-btn:hover {
            background-color: rgba(var(--primary), 0.15);
            transform: translateY(-2px);
        }
        
        .header-support-btn {
            padding: 8px 15px;
            border-radius: 20px;
            background-color: white;
            border: 1px solid rgb(var(--primary));
            color: rgb(var(--primary));
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.2s ease;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .header-support-btn:hover {
            background-color: rgba(var(--primary-bg));
            transform: translateY(-2px);
        }
        
        /* Estilos para versión móvil del menú */
        @media (max-width: 768px) {
            .header-progress {
                display: none;
            }
            
            .header-logo-text {
                font-size: 1rem;
            }
            
            .header-support-btn span {
                display: none;
            }
            
            .header-support-btn {
                padding: 8px;
            }
        }
        
        @media (max-width: 480px) {
            .main-header {
                padding: 10px;
            }
            
            .header-logo img {
                height: 28px;
            }
            
            .header-help-btn {
                width: 32px;
                height: 32px;
                margin-right: 8px;
            }
        }

        /* Estilos para tooltips de ayuda */
.help-tooltip {
    position: absolute;
    background-color: white;
    box-shadow: var(--shadow-md);
    border-radius: var(--radius-md);
    padding: 15px;
    max-width: 280px;
    z-index: var(--z-header);
    top: 100%;
    right: 0;
    margin-top: 10px;
    opacity: 0;
    visibility: hidden;
    transform: translateY(-10px);
    transition: all 0.3s ease;
}

.help-tooltip.visible {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.help-tooltip-title {
    font-weight: 600;
    font-size: 1rem;
    color: rgb(var(--primary));
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.help-tooltip-text {
    font-size: 0.9rem;
    color: rgb(var(--neutral-700));
    line-height: 1.5;
}

.help-tooltip-close {
    position: absolute;
    top: 10px;
    right: 10px;
    cursor: pointer;
    color: rgb(var(--neutral-500));
    transition: color 0.2s ease;
}

.help-tooltip-close:hover {
    color: rgb(var(--neutral-800));
}

/* Estilos para versión móvil del menú */
@media (max-width: 768px) {
    .header-progress {
        display: none;
    }
    
    .header-logo-text {
        font-size: 1rem;
    }
    
    .header-support-btn span {
        display: none;
    }
    
    .header-support-btn {
        padding: 8px;
    }
}

@media (max-width: 480px) {
    .main-header {
        padding: 10px;
    }
    
    .header-logo img {
        height: 28px;
    }
    
    .header-help-btn {
        width: 32px;
        height: 32px;
        margin-right: 8px;
    }
}

        .interactive-form-container {
            position: relative;
            width: 100%;
            min-height: auto; /* Eliminar altura mínima para adaptarse exactamente al contenido */
            height: auto; /* Permitir que se ajuste al contenido */
            overflow: visible; /* Mantener visible para evitar recortes */
            background-color: rgb(var(--neutral-50));
            padding-top: 10px; /* Espacio para el menú superior */
            padding-bottom: 0; /* Eliminar padding inferior */
        }

        /* Estilos generales para las etapas del formulario */
        .form-stage {
            position: absolute;
            width: 100%;
            height: auto; /* Adaptarse al contenido */
            min-height: auto; /* Eliminar altura mínima para adaptarse exactamente al contenido */
            padding: 30px;
            padding-bottom: 70px; /* Reducir padding inferior para los botones */
            transition: transform 0.6s ease, opacity 0.6s ease;
        }

        /* Estilos para etapas ocultas y visibles */
        .form-stage.active {
            transform: translateX(0);
            opacity: 1;
            z-index: 5;
        }

        .form-stage.before {
            transform: translateX(-100%);
            opacity: 0;
            z-index: 1;
        }

        .form-stage.after {
            transform: translateX(100%);
            opacity: 0;
            z-index: 1;
        }
        
        /* Asegurar mismo padding en todas las etapas, independientemente del dispositivo */
        @media (max-width: 768px) {
            .form-stage {
                padding: 30px; /* Mantener el mismo padding que en desktop */
            }
        }

        /* Botones */
        .btn {
            padding: 0 1.5rem;
            border-radius: var(--radius-md);
            font-weight: 500;
            font-size: 1rem;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: all var(--transition-fast);
            height: 2.5rem; /* Reducir altura de botones */
            text-decoration: none;
        }

        .btn-primary {
            background: rgb(var(--primary));
            color: white;
        }

        .btn-primary:hover {
            background: rgb(var(--primary-dark));
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .btn-secondary {
            background: rgb(var(--neutral-200));
            color: rgb(var(--neutral-800));
        }

        .btn-secondary:hover {
            background: rgb(var(--neutral-300));
            transform: translateY(-1px);
        }

        .btn-lg {
            height: 3.125rem;
            font-size: 1.1rem;
            padding: 0 2rem;
        }

        .btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none !important;
        }

        /* Indicador de progreso */
        /* Estilos removidos para eliminar barra de progreso redundante */

        /* Recuadros de entrada para datos personales */
        .data-section {
            background-color: white;
            border-radius: var(--radius-lg);
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: var(--shadow-sm);
            position: relative;
            min-height: auto; /* Permitir expansión */
            height: auto; /* Asegurar que se ajuste al contenido */
            overflow: visible; /* Permitir que el contenido sobresalga si es necesario */
        }

        .data-section-title {
            color: rgb(var(--primary));
            font-size: 1.1rem;
            font-weight: 500;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .data-section-title i {
            color: rgb(var(--primary));
        }

        .fields-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }
        
        /* Corrige alineación del campo de teléfono */
        .data-section:nth-child(1) .fields-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr); /* 2 columnas fijas en la sección de datos personales */
        }

        .field-group {
            position: relative;
            margin-bottom: 20px; /* Espacio estandarizado para todos los dispositivos */
            transition: all 0.3s ease;
        }

        .field-group.full-width {
            grid-column: span 2;
        }

        .field-label {
            position: absolute;
            top: 50%;
            left: 12px;
            transform: translateY(-50%);
            font-size: 14px;
            color: rgb(var(--neutral-500));
            pointer-events: none;
            transition: all 0.2s ease;
            padding: 0 5px;
            background-color: transparent;
        }
        
        /* Garantizar posicionamiento correcto para etiqueta de teléfono */
        label[for="customer_phone"] {
            position: absolute !important;
            top: 50% !important;
            left: 12px !important;
            transform: translateY(-50%) !important;
            font-size: 14px !important;
            color: rgb(var(--neutral-500)) !important;
            pointer-events: none !important;
            transition: all 0.2s ease !important;
            padding: 0 5px !important;
            background-color: transparent !important;
            line-height: 1 !important;
            z-index: 1 !important;
            /* Ajuste exacto para centrar verticalmente */
            margin-top: -1px !important;
            display: inline-block !important;
            height: auto !important;
        }
        
        /* Estilos para el campo de teléfono personalizado - exactamente igual a los estilos de DNI */
        .custom-phone-input:focus {
            outline: none;
            border-color: rgb(1, 109, 134) !important; /* rgb(var(--primary)) */
            box-shadow: 0 0 0 3px rgba(1, 109, 134, 0.1) !important; /* rgba(var(--primary), 0.1) */
        }
        
        .custom-phone-input:focus + .custom-phone-label,
        .custom-phone-input:not(:placeholder-shown) + .custom-phone-label {
            top: 0 !important;
            left: 10px !important;
            transform: translateY(-50%) !important;
            font-size: 12px !important;
            background-color: white !important;
            padding: 0 5px !important;
            color: rgb(1, 109, 134) !important; /* rgb(var(--primary)) */
            transition: all 0.2s ease !important; /* misma transición que otros campos */
        }
        
        /* Estilo de completado (checkmark) para el campo de teléfono */
        .field-group.completed .custom-phone-input {
            border-color: rgb(40, 167, 69) !important; /* rgb(var(--success)) */
        }
        
        

        .field-input {
            width: 100%;
            padding: 12px 15px;
            font-size: 15px;
            border: 1px solid rgb(var(--neutral-300));
            border-radius: var(--radius-md);
            color: rgb(var(--neutral-800));
            background-color: white;
            transition: all 0.2s ease;
        }
        
        /* Normalizar apariencia para campos tipo tel - exactamente igual a .field-input */
        input[type="tel"] {
            -webkit-appearance: none !important;
            -moz-appearance: none !important;
            appearance: none !important;
            margin: 0 !important;
            box-sizing: border-box !important;
            width: 100% !important;
            padding: 12px 15px !important;
            font-size: 15px !important;
            border: 1px solid rgb(173, 181, 189) !important;
            border-radius: 6px !important;
            color: rgb(52, 58, 64) !important;
            background-color: white !important;
            transition: all 0.2s ease !important;
            height: auto !important; /* Eliminar altura fija */
            min-height: 0 !important;
            line-height: normal !important;
        }
        
        /* Estilos específicos cuando el teléfono está enfocado */
        input[type="tel"]:focus {
            outline: none !important;
            border-color: rgb(var(--primary)) !important;
            box-shadow: 0 0 0 3px rgba(var(--primary), 0.1) !important;
        }
        
        

        .field-input:focus {
            outline: none;
            border-color: rgb(var(--primary));
            box-shadow: 0 0 0 3px rgba(var(--primary), 0.1);
        }

        /* Cuando el campo tiene contenido o está enfocado */
        .field-input:focus + .field-label,
        .field-input:not(:placeholder-shown) + .field-label,
        .custom-phone-input:focus + .custom-phone-label,
        .custom-phone-input:not(:placeholder-shown) + .custom-phone-label {
            top: 0;
            left: 10px;
            transform: translateY(-50%);
            font-size: 12px;
            background-color: white;
            padding: 0 5px;
            color: rgb(var(--primary));
        }

        /* Campo completado correctamente */
        .field-group.completed .field-input {
            border-color: rgb(var(--success));
        }

        .field-group.completed::after {
            content: '✓';
            position: absolute;
            top: 50%;
            right: 12px;
            transform: translateY(-50%);
            color: rgb(var(--success));
            font-weight: bold;
        }

        /* Estilo de errores */
        .field-error {
            font-size: 12px;
            color: rgb(var(--error));
            margin-top: 5px;
            min-height: 18px; /* Reservar espacio incluso cuando está oculto */
            display: none;
        }

        .field-group.error .field-input {
            border-color: rgb(var(--error));
        }

        .field-group.error .field-error {
            display: block;
        }

        .field-group.error .field-label {
            color: rgb(var(--error));
        }
        
        /* Error tooltips para validación mejorada */
        .error-tooltip {
            position: absolute;
            background-color: rgb(var(--error));
            color: white;
            padding: 6px 10px;
            border-radius: 4px;
            font-size: 12px;
            z-index: 100;
            box-shadow: 0 2px 8px rgba(var(--error), 0.3);
            max-width: 250px;
            right: 0;
            top: 100%;
            margin-top: 5px;
            animation: fadeIn 0.2s ease-in-out;
            pointer-events: none;
        }
        
        .error-tooltip::before {
            content: '';
            position: absolute;
            top: -4px;
            right: 15px;
            width: 8px;
            height: 8px;
            background-color: rgb(var(--error));
            transform: rotate(45deg);
        }
        
        /* Mensaje de error global */
        .error-message {
            display: flex;
            align-items: center;
            gap: 10px;
            background-color: rgba(var(--error), 0.1);
            border-left: 4px solid rgb(var(--error));
            border-radius: var(--radius-md);
            padding: 12px 15px;
            margin: 15px 0;
            color: rgb(var(--error));
            font-size: 14px;
            animation: slideIn 0.3s ease;
            opacity: 0;
            transform: translateY(-10px);
            transition: opacity 0.3s ease, transform 0.3s ease;
            box-shadow: 0 2px 10px rgba(var(--error), 0.1);
        }
        
        .error-message.visible {
            opacity: 1;
            transform: translateY(0);
        }
        
        .error-message.hiding {
            opacity: 0;
            transform: translateY(-10px);
        }
        
        .error-message .error-icon {
            font-size: 18px;
            color: rgb(var(--error));
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .error-message .error-text {
            flex: 1;
            padding-right: 5px;
        }
        
        .error-message .error-close {
            cursor: pointer;
            padding: 5px;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.2s ease;
        }
        
        .error-message .error-close:hover {
            background-color: rgba(255, 255, 255, 0.5);
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-5px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Estilos para feedback de validación */
        .validation-success {
            animation: successPulse 0.8s ease;
        }
        
        .validation-error {
            animation: errorShake 0.5s ease;
        }
        
        @keyframes successPulse {
            0% { box-shadow: 0 0 0 0 rgba(var(--success), 0); }
            50% { box-shadow: 0 0 0 10px rgba(var(--success), 0.2); }
            100% { box-shadow: 0 0 0 0 rgba(var(--success), 0); }
        }
        
        @keyframes errorShake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-2px); }
            20%, 40%, 60%, 80% { transform: translateX(2px); }
        }

        /* Sección de carga de documentos */
        .upload-container {
            background-color: white;
            border-radius: var(--radius-lg);
            padding: 25px;
            margin-top: 20px;
            box-shadow: var(--shadow-sm);
        }

        .upload-title {
            color: rgb(var(--primary));
            font-size: 1.1rem;
            font-weight: 500;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .upload-area {
            border: 2px dashed rgb(var(--neutral-300));
            border-radius: var(--radius-md);
            padding: 25px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .upload-area:hover {
            border-color: rgb(var(--primary));
            background-color: rgba(var(--primary-bg));
        }

        .upload-icon {
            font-size: 30px;
            color: rgb(var(--primary));
            margin-bottom: 10px;
        }

        .upload-text {
            color: rgb(var(--neutral-600));
            margin-bottom: 10px;
        }

        .upload-input {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }

        .upload-preview {
            display: none;
            margin-top: 15px;
            padding: 12px;
            background-color: rgba(var(--success), 0.1);
            border-radius: var(--radius-md);
            color: rgb(var(--success));
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }

        .upload-preview.active {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
        }

        .upload-preview-name {
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            flex: 1;
            min-width: 0;
            padding: 5px 0;
        }

        .upload-preview-name .file-name {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .upload-preview-name .file-size {
            font-weight: normal;
            font-size: 0.85em;
            color: rgb(var(--neutral-600));
            white-space: nowrap;
        }

        .upload-preview-name i {
            font-size: 1.2em;
            min-width: 20px;
        }

        .upload-preview-remove {
            color: rgb(var(--error));
            cursor: pointer;
            font-size: 18px;
            margin-left: 10px;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.2s ease;
        }

        .upload-preview-remove:hover {
            background-color: rgba(var(--error), 0.1);
        }

        .file-thumbnail {
            width: 100%;
            margin-bottom: 10px;
            border-radius: var(--radius-sm);
            overflow: hidden;
            border: 1px solid rgba(var(--neutral-300), 0.5);
        }

        .file-thumbnail img {
            width: 100%;
            height: auto;
            max-height: 120px;
            object-fit: contain;
            background: white;
            display: block;
        }

        /* Sección de firma optimizada para móviles */
        .signature-container {
            background-color: white;
            border-radius: var(--radius-lg);
            padding: 25px;
            margin-top: 20px;
            box-shadow: var(--shadow-sm);
        }

        .signature-title {
            color: rgb(var(--primary));
            font-size: 1.1rem;
            font-weight: 500;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .signature-instructions {
            font-size: 14px;
            color: rgb(var(--neutral-600));
            margin-bottom: 15px;
        }

        #authorization-text {
            background-color: rgba(var(--primary-bg));
            padding: 15px;
            border-radius: var(--radius-md);
            margin-bottom: 20px;
            font-size: 14px;
            color: rgb(var(--neutral-700));
        }

        /* Componentes de firma mejorados */
        .signature-pad-container {
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
            border: 2px solid rgb(var(--neutral-300));
            border-radius: 8px;
            overflow: hidden;
            transition: border-color .3s ease;
            background-color: #fff;
            position: relative;
            touch-action: none;
            -ms-touch-action: none;
        }

        .signature-pad-container:hover {
            border-color: rgb(var(--primary));
        }

        #signature-pad {
            width: 100%;
            height: 180px;
            touch-action: none;
            -ms-touch-action: none;
            display: block;
            box-sizing: border-box;
            cursor: crosshair;
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
            position: relative;
            z-index: 10;
            background-color: #fff;
        }

        .signature-status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            margin-left: 10px;
            background-color: rgba(var(--neutral-300), 0.3);
            color: rgb(var(--neutral-600));
        }

        .signature-status.signed {
            background-color: rgba(var(--success), 0.1);
            color: rgb(var(--success));
        }

        .device-instruction {
            margin: 10px 0;
            color: rgb(var(--neutral-600));
            font-size: 14px;
            text-align: center;
        }

        .device-instruction i {
            margin-right: 5px;
        }

        .signature-actions {
            margin-top: 10px;
            display: flex;
            justify-content: space-between;
            gap: 10px;
        }

        .signature-actions button {
            padding: 8px 15px;
            background-color: white;
            border: 1px solid rgb(var(--neutral-300));
            border-radius: 30px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s ease;
        }

        #clear-signature {
            color: rgb(var(--error));
        }

        #clear-signature:hover {
            background-color: rgba(var(--error), 0.1);
            border-color: rgb(var(--error));
        }

        #zoom-signature {
            color: rgb(var(--primary));
            flex: 1;
            justify-content: center;
        }

        #zoom-signature:hover {
            background-color: rgba(var(--primary), 0.1);
            border-color: rgb(var(--primary));
        }

        /* Modal de firma mejorado */
        .signature-modal-enhanced {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.9);
            z-index: 10000;
            display: none;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            animation: fadeIn 0.3s ease;
            -webkit-touch-callout: none;
            -webkit-user-select: none;
            user-select: none;
        }
                    
        /* Estilos mejorados para el modal de firma */
        .signature-modal-enhanced {
            position: fixed !important;
            overflow: hidden !important;
            -webkit-overflow-scrolling: auto !important;
        }

        /* Mejoras para iOS */
        @supports (-webkit-touch-callout: none) {
            .interactive-form-container {
                -webkit-overflow-scrolling: touch;
            }
            
            .signature-pad-container {
                touch-action: none;
                -webkit-touch-callout: none;
                -webkit-user-select: none;
                user-select: none;
            }
            
            /* Prevenir scroll mientras se firma */
            .signature-modal-enhanced {
                position: fixed !important;
                width: 100% !important;
                height: 100% !important;
                top: 0 !important;
                left: 0 !important;
                right: 0 !important;
                bottom: 0 !important;
            }
        }
        
        /* Modal content - mejorado para adaptación a diferentes dispositivos */
        .enhanced-modal-content {
            position: relative;
            width: 95%;
            height: 92%;
            max-width: 95%;
            max-height: 90vh;
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            animation: zoomIn 0.3s ease;
            -webkit-touch-callout: none;
            -webkit-user-select: none;
            -webkit-tap-highlight-color: transparent;
            user-select: none;
        }
                    
        /* Header */
        .enhanced-modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px 20px;
            background-color: rgb(var(--neutral-50));
            border-bottom: 1px solid rgb(var(--neutral-300));
        }
                    
        .enhanced-modal-header h3 {
            margin: 0;
            font-size: 20px;
            color: rgb(var(--primary));
        }
                    
        .orientation-indicator {
            display: none; /* Ocultar permanentemente */
        }
                    
        .enhanced-close-button {
            background: none;
            border: none;
            color: #6c757d;
            font-size: 24px;
            cursor: pointer;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.2s ease;
        }
                    
        .enhanced-close-button:hover {
            background-color: rgba(108, 117, 125, 0.1);
        }
                    
        /* Signature container */
        .enhanced-signature-container {
            position: relative;
            flex: 1;
            width: 100%;
            background-color: white;
            overflow: hidden;
            touch-action: none;
            -webkit-touch-callout: none;
            -webkit-user-select: none;
            user-select: none;
        }
                    
        #enhanced-signature-canvas {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            touch-action: none;
            -ms-touch-action: none;
            user-select: none;
            -webkit-touch-callout: none;
            -webkit-user-select: none;
        }
                    
        /* Signature guide - siempre en horizontal */
        .signature-guide {
            position: absolute;
            top: 50%;
            left: 10px;
            right: 10px;
            z-index: 1;
            pointer-events: none;
            -webkit-touch-callout: none;
            -webkit-user-select: none;
            user-select: none;
        }
                    
        .signature-line {
            height: 2px;
            background-color: rgb(var(--primary));
            opacity: 0.5;
            box-shadow: 0 0 5px rgba(1, 109, 134, 0.2);
        }
                    
        .signature-instruction {
            position: absolute;
            color: rgb(var(--primary));
            font-size: 20px;
            font-weight: bold;
            letter-spacing: 3px;
            opacity: 0.3;
            left: 50%;
            top: -15px;
            transform: translateX(-50%);
            white-space: nowrap;
            text-align: center;
        }
                    
        /* Footer */
        .enhanced-modal-footer {
            padding: 15px 20px;
            background-color: rgb(var(--neutral-50));
            border-top: 1px solid rgb(var(--neutral-300));
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
                    
        .enhanced-instructions {
            margin: 0;
            text-align: center;
            color: #6c757d;
            font-size: 14px;
        }
                    
        .enhanced-button-container {
            display: flex;
            justify-content: space-between;
            gap: 15px;
        }
                    
        .enhanced-button-container button {
            flex: 1;
            padding: 12px 15px;
            border: none;
            border-radius: 30px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }
                    
        .enhanced-button-container button i {
            margin-right: 8px;
        }
                    
        .enhanced-clear-button {
            background-color: #f8d7da;
            color: #721c24;
        }
                    
        .enhanced-clear-button:hover {
            background-color: #f1b0b7;
            transform: translateY(-2px);
            box-shadow: 0 3px 5px rgba(114, 28, 36, 0.2);
        }
                    
        .enhanced-accept-button {
            background-color: rgb(var(--primary));
            color: white;
        }
                    
        .enhanced-accept-button:hover {
            background-color: rgb(var(--primary-dark));
            transform: translateY(-2px);
            box-shadow: 0 3px 5px rgba(1, 109, 134, 0.3);
        }
                    
        .enhanced-accept-button:disabled {
            background-color: #ccc;
            color: #666;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        /* Sección de pago */
        .payment-container {
            background-color: white;
            border-radius: var(--radius-lg);
            padding: 25px;
            margin-top: 20px;
            box-shadow: var(--shadow-sm);
        }

        .payment-title {
            color: rgb(var(--primary));
            font-size: 1.1rem;
            font-weight: 500;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .price-details {
            background-color: rgba(var(--primary-bg));
            padding: 20px;
            border-radius: var(--radius-md);
            margin-bottom: 20px;
        }

        .price-details p {
            margin: 5px 0;
            display: flex;
            justify-content: space-between;
            font-size: 15px;
            color: rgb(var(--neutral-700));
        }

        .price-details .price-total {
            font-weight: 700;
            font-size: 17px;
            margin-top: 10px;
            color: rgb(var(--neutral-800));
            border-top: 1px solid rgba(var(--neutral-300));
            padding-top: 10px;
        }

        .coupon-container {
            margin-bottom: 20px;
        }

        .coupon-field {
            display: flex;
            gap: 10px;
        }

        .coupon-input {
            flex: 1;
            padding: 10px 12px;
            border: 1px solid rgb(var(--neutral-300));
            border-radius: var(--radius-md);
            font-size: 14px;
        }

        .coupon-btn {
            background-color: rgb(var(--neutral-200));
            color: rgb(var(--neutral-800));
            padding: 0 15px;
            border: none;
            border-radius: var(--radius-md);
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s ease;
        }

        .coupon-btn:hover {
            background-color: rgb(var(--neutral-300));
        }

        .coupon-message {
            margin-top: 5px;
            font-size: 13px;
        }

        .coupon-message.success {
            color: rgb(var(--success));
        }

        .coupon-message.error {
            color: rgb(var(--error));
        }

        #payment-element {
            margin: 20px 0;
        }

        /* Overlay de carga */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(255,255,255,0.9);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            backdrop-filter: blur(5px);
            visibility: hidden;
            opacity: 0;
            transition: all 0.3s ease;
        }

        .loading-overlay.active {
            visibility: visible;
            opacity: 1;
        }
        
        /* Cuando el loading está activo, ocultar el formulario */
        #loading-overlay:not([style*="display: none"]) ~ #hoja-asiento-form {
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }

        .loading-spinner {
            width: 60px;
            height: 60px;
            border: 5px solid rgba(var(--primary), 0.3);
            border-radius: 50%;
            border-top-color: rgb(var(--primary));
            animation: spin 1s linear infinite;
        }

        .loading-text {
            margin-top: 20px;
            font-size: 18px;
            color: rgb(var(--primary));
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Navegación entre etapas - Versión mejorada */
        .stage-navigation {
            position: absolute; /* Posición absoluta */
            bottom: 0; /* Posicionar en la parte inferior */
            left: 0;
            width: 100%;
            padding: 12px 30px; /* Reducir padding vertical */
            background-color: rgb(255, 255, 255);
            box-shadow: 0 -5px 10px rgba(0, 0, 0, 0.05);
            display: flex;
            justify-content: space-between;
            z-index: 40; /* Por debajo de elementos críticos */
            border-top: 1px solid rgb(var(--neutral-200));
            transition: transform 0.3s ease, opacity 0.3s ease;
            margin-top: 0; /* Eliminar margen superior */
        }
        /* Arreglo para evitar que los botones pisen el contenido en escritorio */
@media (min-width: 769px) {
    /* Aumentar significativamente el padding inferior de las etapas del formulario */
    .form-stage {
        padding-bottom: 190px !important; /* Incrementado más para dar amplio espacio a los botones */
    }
    
    /* Posicionar los botones correctamente sin pisar contenido */
    .stage-navigation {
        position: absolute;
        bottom: -30px !important; /* Modificado para posicionar considerablemente más abajo */
        left: 0;
        width: 100%;
        z-index: 40;
        margin-top: 30px;
    }
    
    /* Asegurar que el último elemento de cada etapa tenga margen suficiente */
    .form-stage > *:last-child:not(.stage-navigation) {
        margin-bottom: 140px !important; /* Incrementado más para evitar superposición */
    }
    
    /* Asegurar que los contenedores de términos y condiciones tengan margen extra */
    .terms-container, 
    .payment-container .terms-container {
        margin-bottom: 140px !important; /* Incrementado más para evitar superposición */
    }
}

        /* Anular el comportamiento de ocultamiento para la clase scrolling-down */
        .stage-navigation.scrolling-down {
            transform: translateY(0) !important; /* Anular la transformación que lo oculta */
            opacity: 1 !important; /* Mantener completamente visible */
        }
        
        /* Mejorar visibilidad y garantizar posición fija */
        .stage-navigation {
            position: absolute !important; /* Forzar que sea absolute para posicionamiento consistente */
            bottom: 0 !important; /* La posición específica para ordenadores se define en la media query */
            z-index: 999 !important; /* Valor alto para estar sobre otros elementos */
            box-shadow: 0 -5px 15px rgba(0, 0, 0, 0.1) !important; /* Sombra más visible */
            padding: 12px 30px !important; /* Reducir padding vertical */
            background-color: rgba(255, 255, 255, 0.98) !important; /* Ligeramente transparente */
            margin-top: 0 !important; /* Eliminar margen superior */
        }
        
        /* Regla específica para pantallas grandes - evitar superposición de contenido */
        @media (min-width: 1024px) {
            .stage-navigation {
                bottom: -50px !important; /* Posicionar mucho más abajo en pantallas grandes */
            }
            
            .form-stage {
                padding-bottom: 220px !important; /* Dar considerablemente más espacio en pantallas grandes */
            }
            
            .form-stage > *:last-child:not(.stage-navigation) {
                margin-bottom: 160px !important; /* Asegurar amplio margen en el último elemento */
            }
            
            .terms-container, 
            .payment-container .terms-container {
                margin-bottom: 160px !important; /* Aumentar significativamente margen en términos y condiciones */
            }
        }
        
        /* Asegurar que siempre se muestre al final de cada etapa */
        .form-stage {
            padding-bottom: 20px !important; /* Reducir espacio excesivo */
        }
        
        /* Garantizar que el espacio adicional siempre esté disponible */
        .interactive-form-container {
            padding-bottom: 15px !important; /* Reducir espacio excesivo */
        }

        .stage-navigation-wrapper {
            max-width: 800px;
            margin: 0 auto;
            width: 100%;
            display: flex;
            justify-content: space-between;
            gap: 15px;
        }

        .stage-navigation .btn {
            min-width: 140px;
            transition: all 0.3s ease;
        }

        .stage-navigation .btn-primary {
            position: relative;
            overflow: hidden;
        }

        .stage-navigation .progress-indicator {
            position: absolute;
            top: -3px;
            left: 0;
            width: 100%;
            height: 3px;
            background-color: rgba(var(--neutral-200));
            overflow: hidden;
        }

        .stage-navigation .progress-indicator-fill {
            height: 100%;
            background: linear-gradient(90deg, rgb(var(--primary)) 0%, rgb(var(--primary-light)) 100%);
            width: 0%;
            transition: width 0.5s ease;
        }

        /* Botón flotante para dispositivos móviles */
        .mobile-nav-fab {
            display: none;
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background-color: rgb(var(--primary));
            color: white;
            font-size: 24px;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 10px rgba(var(--primary), 0.3);
            z-index: 1000 !important; /* Aumentar z-index para estar sobre otros elementos */
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .mobile-nav-fab:active {
            transform: scale(0.95);
        }

        /* Hacer que las acciones tengan mayor z-index también */
        .mobile-nav-actions {
            display: none;
            position: fixed;
            bottom: 90px;
            z-index: 1000 !important;
            right: 25px;
            flex-direction: column;
            gap: 10px;
            z-index: 49;
        }

        .mobile-nav-actions.active {
            display: flex;
            animation: slideUp 0.3s ease forwards;
        }

        .mobile-nav-action-btn {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            position: relative;
        }

        .mobile-nav-action-btn.prev {
            background-color: rgb(var(--neutral-200));
            color: rgb(var(--neutral-800));
        }

        .mobile-nav-action-btn.next {
            background-color: rgb(var(--primary));
            color: white;
        }

        .action-label {
            position: absolute;
            right: 60px;
            background-color: rgba(var(--neutral-800), 0.8);
            color: white;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 12px;
            white-space: nowrap;
            opacity: 0;
            transform: translateX(10px);
            transition: all 0.2s ease;
            pointer-events: none;
        }

        .mobile-nav-action-btn:hover .action-label {
            opacity: 1;
            transform: translateX(0);
        }

        /* Eliminar márgenes específicos ya que usamos un padding estandarizado en form-stage */
        .terms-container, 
        .payment-container .terms-container,
        .field-group:last-of-type,
        .form-stage > *:last-child:not(.stage-navigation) {
            margin-bottom: 0 !important; /* Eliminar márgenes específicos */
        }

        /* En móviles, ajustar para el FAB */
        @media (max-width: 480px) {
            .stage-navigation {
                display: flex; /* Mostrar la barra de navegación normal */
            }
            
            .mobile-nav-fab {
                display: none; /* Ocultar el botón flotante */
            }
            
            .terms-container, 
            .payment-container .terms-container,
            .field-group:last-of-type,
            .form-stage > *:last-child:not(.stage-navigation) {
                margin-bottom: 30px !important; /* Mismo margen que en desktop */
                margin-right: 0 !important; /* Eliminar margen derecho adicional */
            }
            
            /* Mantener el mismo margen en contenedores de checkbox */
            .terms-container input[type="checkbox"] {
                margin-right: 12px !important; /* Mismo margen que en desktop */
            }
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Media Queries para dispositivos móviles */
        @media (max-width: 768px) {
            .stage-navigation {
                padding: 15px 30px; /* Mismo padding que en desktop */
            }
            
            .stage-navigation .btn {
                min-width: 140px; /* Mismo ancho mínimo que en desktop */
                flex: 0; /* Evitar que los botones se expandan */
                font-size: 1rem; /* Mismo tamaño de fuente que en desktop */
                padding: 0 1.5rem; /* Mismo padding que en desktop */
            }
        }
        
        /* Anular media query que oculta la barra de navegación en móviles */
        @media (max-width: 480px) {
            .stage-navigation {
                display: flex !important;
                padding: 15px 30px !important; /* Mismo padding que en desktop */
                margin-top: 30px !important; /* Mismo margen superior que en desktop */
            }
            
            /* Ajustar botones para que sean idénticos a desktop */
            .stage-navigation .btn {
                padding: 0 1.5rem !important; /* Mismo padding que en desktop */
                min-width: 140px !important; /* Mismo ancho mínimo que en desktop */
                font-size: 1rem !important; /* Mismo tamaño de fuente que en desktop */
                height: 2.75rem !important; /* Misma altura que en desktop */
            }
            
            /* Ocultar FAB ya que usaremos solo la barra estándar */
            .mobile-nav-fab {
                display: none !important;
            }
            
            /* Ajustar padding inferior para todos los formularios */
            .form-stage {
                padding-bottom: 70px !important; /* Reducir padding inferior */
                min-height: auto !important; /* Permitir que se adapte al contenido */
            }
            
            /* Asegurar que los márgenes entre elementos sean iguales a desktop */
            .field-group {
                margin-bottom: 20px !important; /* Mismo margen que en desktop */
            }
            
            .data-section {
                margin-bottom: 20px !important; /* Mismo margen que en desktop */
            }
        }

        /* Término y condiciones */
        .terms-container {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin: 25px 0;
            padding: 15px;
            background-color: rgba(var(--primary-bg));
            border-radius: var(--radius-md);
            position: relative;
        }
        
        .terms-checkbox {
            flex-shrink: 0;
            width: 20px;
            height: 20px;
            margin-top: 2px;
            accent-color: rgb(var(--primary));
            cursor: pointer;
        }
        
        .terms-text {
            font-size: 14px;
            color: rgb(var(--neutral-700));
            line-height: 1.4;
        }
        
        /* Mejora para dispositivos móviles */
        @media (max-width: 480px) {
            .terms-container {
                padding: 12px;
                margin: 15px 0;
            }
            
            .terms-checkbox {
                width: 22px;
                height: 22px;
                margin-top: 0;
            }
        }

        .terms-text a {
            color: rgb(var(--primary));
            text-decoration: none;
        }

        .terms-text a:hover {
            text-decoration: underline;
        }

        /* Mensajes de éxito */
        .success-message {
            background-color: rgba(var(--success), 0.1);
            border: 1px solid rgba(var(--success), 0.3);
            color: rgb(var(--success));
            padding: 15px;
            border-radius: var(--radius-md);
            margin: 15px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .success-icon {
            font-size: 20px;
            flex-shrink: 0;
        }

        /* Mensajes de error */
        .error-message {
            background-color: rgba(var(--error), 0.1);
            border: 1px solid rgba(var(--error), 0.3);
            color: rgb(var(--error));
            padding: 15px;
            border-radius: var(--radius-md);
            margin: 15px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .error-icon {
            font-size: 20px;
            flex-shrink: 0;
        }

        /* Animaciones y transiciones */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes zoomIn {
            from { transform: scale(0.95); }
            to { transform: scale(1); }
        }

        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .animate-fadeIn {
            animation: fadeIn 0.5s ease forwards;
        }

        .animate-slideUp {
            animation: slideUp 0.5s ease forwards;
        }

        /* Ajustes para dispositivos móviles */
        /* Tablets grandes */
        @media (max-width: 1024px) {
            .marketing-container {
                flex-direction: column;
            }
            
            .marketing-image {
                margin-top: 30px;
            }
            
            .data-section-title {
                font-size: 1rem;
            }
            
            .btn {
                padding: 0 1.2rem;
                height: 2.5rem;
                font-size: 0.9rem;
            }
        }
        
        /* Tablets */
        @media (max-width: 768px) {
            .fields-grid {
                grid-template-columns: 1fr;
            }
            
            .field-group.full-width {
                grid-column: span 1;
            }
            
            .interactive-form-container {
                min-height: 700px;
            }
            
            .stage-navigation {
                flex-direction: column;
                gap: 10px;
            }
            
            .stage-navigation .btn {
                width: 100%;
            }

            #signature-pad {
                height: 150px;
            }

            .signature-actions {
                flex-direction: column;
            }
            
            .progress-steps {
                font-size: 10px;
            }
        }
        
        /* Tablets pequeñas */
        @media (max-width: 600px) {
            .form-stage {
                padding: 25px 20px;
            }
            
            .marketing-title {
                font-size: 1.8rem;
            }
            
            .marketing-description {
                font-size: 1rem;
            }
            
            .feature-item {
                gap: var(--spacing-xs);
            }
            
            .progress-container {
                padding: 10px 20px 0;
            }
            
            .progress-steps {
                font-size: 9px;
            }
        }

        /* Móviles */
        @media (max-width: 480px) {
            .form-stage {
                padding: 20px 15px;
            }

            #signature-pad {
                height: 120px;
            }
            
            .signature-actions {
                gap: 8px;
            }

            .signature-actions button {
                font-size: 12px;
                padding: 6px 10px;
            }
            
            /* Responsive adjustments for signature modal */
            .enhanced-modal-header {
                padding: 10px 15px;
            }
            
            .enhanced-modal-footer {
                padding: 10px 15px;
            }
            
            .enhanced-instructions {
                display: none;
            }
            
            .enhanced-button-container button {
                padding: 8px 12px;
                font-size: 14px;
            }
            
            .progress-steps {
                font-size: 8px;
            }
        }
        
        /* Móviles pequeños */
        @media (max-width: 375px) {
            .marketing-title {
                font-size: 1.5rem;
            }
            
            .marketing-description {
                font-size: 0.9rem;
            }
            
            .feature-text {
                font-size: 0.8rem;
            }
            
            .feature-icon {
                width: 30px;
                height: 30px;
                font-size: 0.9rem;
            }
            
            .field-input {
                padding: 10px 12px;
                font-size: 14px;
            }
            
            .field-label {
                font-size: 13px;
            }
            
            .upload-text {
                font-size: 13px;
            }
            
            .terms-text {
                font-size: 12px;
            }
            
            /* Mejoras específicas para dispositivos muy pequeños */
            .requirements-heading {
                font-size: 1.2rem;
            }
            
            .requirements-subheading {
                font-size: 0.9rem;
            }
            
            .requirements-list li {
                font-size: 0.9rem;
                margin-bottom: 10px;
            }
            
            .step-name {
                font-size: 0.9rem;
            }
            
            .step-desc {
                font-size: 0.8rem;
            }
            
            .step-number {
                width: 30px;
                height: 30px;
                font-size: 0.9rem;
            }
            
            .error-message, .success-message {
                font-size: 0.9rem;
                padding: 10px;
            }
        }
        
        /* Estilos para el componente de Autorización */
        .form-row {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 15px;
        }

        .form-col {
            flex: 1;
            min-width: 250px;
        }

        .form-col label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: rgb(var(--neutral-700));
        }

        .form-col input {
            width: 100%;
            padding: 12px 15px;
            font-size: 15px;
            border: 1px solid rgb(var(--neutral-300));
            border-radius: var(--radius-md);
            color: rgb(var(--neutral-800));
            background-color: white;
            transition: all 0.2s ease;
        }

        .form-col input:focus {
            outline: none;
            border-color: rgb(var(--primary));
            box-shadow: 0 0 0 3px rgba(var(--primary), 0.1);
        }

        .required {
            color: rgb(var(--error));
            margin-left: 3px;
        }

        /* Mejoras para la experiencia de firma */
        .signature-pad-container:hover {
            border-color: rgb(var(--primary));
        }

        .signature-actions {
            margin-top: 15px;
            display: flex;
            justify-content: center;
            gap: 10px;
        }

        .signature-actions button {
            padding: 8px 15px;
            background-color: #e9ecef;
            border: none;
            border-radius: 4px;
            color: #495057;
            cursor: pointer;
            display: flex;
            align-items: center;
            transition: all .3s ease;
        }

        .signature-actions button:hover {
            background-color: #dde2e6;
            transform: translateY(-2px);
        }

        .signature-actions button i {
            margin-right: 5px;
        }

        .signature-status.signed {
            color: rgb(var(--success));
            background-color: rgba(var(--success), 0.1);
        }

        .signature-status.empty {
            color: rgb(var(--neutral-600));
        }

        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
            }
            
            .form-col {
                width: 100%;
            }
        }

        /* Orientación horizontal para todos los dispositivos */
        @media (orientation: landscape) {
            .enhanced-modal-content {
                width: 80%;
                max-width: 700px;
                max-height: 90vh;
            }
            
            .signature-guide {
                top: 50%;
            }
            
            /* Ajustes específicos para móviles en landscape */
            @media (max-width: 900px) {
                .interactive-form-container {
                    min-height: 450px;
                }
                
                .progress-steps {
                    flex-wrap: wrap;
                }
                
                .progress-step {
                    flex: 0 0 50%;
                    margin-bottom: 5px;
                }
                
                .marketing-container {
                    padding: var(--spacing-md);
                }
                
                .signature-pad-container {
                    max-width: 400px;
                }
            }
        }

        /* Estilos mejorados para la sección de pago */
        .payment-container {
            background-color: white;
            border-radius: var(--radius-lg, 12px);
            padding: 25px;
            margin-top: 20px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .payment-title {
            color: rgb(var(--primary, 1, 109, 134));
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .price-details {
            background-color: rgba(var(--primary-bg, 236, 246, 250), 1);
            padding: 20px;
            border-radius: var(--radius-md, 8px);
            margin-bottom: 25px;
            box-shadow: 0 1px 5px rgba(0,0,0,0.04);
        }
        
        .price-details p {
            margin: 8px 0;
            display: flex;
            justify-content: space-between;
            font-size: 15px;
            color: rgb(var(--neutral-700, 75, 85, 99));
            line-height: 1.5;
        }
        
        .price-details .price-total {
            font-weight: 700;
            font-size: 17px;
            margin-top: 12px;
            color: rgb(var(--neutral-800, 31, 41, 55));
            border-top: 1px solid rgba(var(--neutral-300, 209, 213, 219), 1);
            padding-top: 12px;
        }
        
        .price-details .price-discount {
            color: rgb(var(--success, 16, 185, 129));
            font-weight: 500;
        }
        
        .price-details .price-discount span {
            color: rgb(var(--success, 16, 185, 129));
            font-weight: 700;
        }
        
        .coupon-container {
            margin-bottom: 25px;
        }
        
        .coupon-field {
            display: flex;
            gap: 10px;
            align-items: stretch;
        }
        
        .coupon-input {
            flex: 1;
            padding: 12px 15px;
            border: 1px solid rgb(var(--neutral-300, 209, 213, 219));
            border-radius: var(--radius-md, 8px);
            font-size: 15px;
            color: rgb(var(--neutral-800, 31, 41, 55));
        }
        
        .coupon-input:focus {
            outline: none;
            border-color: rgb(var(--primary, 1, 109, 134));
            box-shadow: 0 0 0 2px rgba(var(--primary, 1, 109, 134), 0.2);
        }
        
        .coupon-btn {
            background-color: rgb(var(--primary, 1, 109, 134));
            color: white;
            padding: 0 20px;
            border: none;
            border-radius: var(--radius-md, 8px);
            cursor: pointer;
            font-size: 15px;
            font-weight: 600;
            transition: all 0.2s ease;
            min-width: 100px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .coupon-btn:hover {
            background-color: rgba(var(--primary, 1, 109, 134), 0.9);
            transform: translateY(-1px);
        }
        
        .coupon-message {
            margin-top: 8px;
            font-size: 14px;
        }
        
        .coupon-message.success {
            color: rgb(var(--success, 16, 185, 129));
        }
        
        .coupon-message.error {
            color: rgb(var(--error, 220, 38, 38));
        }
        
        /* Unificar distancia entre menú superior y contenido en todas las pestañas */
        /* Establecer primero el espaciado general del form-stage */
        .form-stage {
            padding-top: 30px !important; /* Padding superior consistente en todas las pestañas */
        }
        
        /* Forzar margin-top: 0 en TODOS los contenedores */
        .upload-container, 
        .signature-container, 
        .payment-container,
        .data-section {
            margin-top: 0 !important; /* Eliminar cualquier margen superior */
        }
        
        /* Específicamente para los contenedores dentro de un form-stage */
        .form-stage .data-section,
        .form-stage .upload-container, 
        .form-stage .signature-container, 
        .form-stage .payment-container {
            margin-top: 0 !important; /* Eliminar margen superior */
            padding-top: 0 !important; /* Eliminar padding superior adicional */
        }
        
        /* Dar un poco de espacio a los títulos para mantener la legibilidad */
        /* Asegurar espaciado consistente en todos los títulos de sección */
        .data-section-title,
        .upload-title,
        .signature-title,
        .payment-title {
            margin-top: 0 !important; /* Reset para consistencia */
            padding-top: 0 !important; /* Reset para consistencia */
        }
        
        /* Aplicar el mismo espaciado exacto que tiene el título en Datos Personales */
        .form-stage .data-section-title,
        .form-stage .upload-title,
        .form-stage .signature-title,
        .form-stage .payment-title {
            margin-top: 0 !important; /* Sin margen superior */
            padding-top: 0 !important; /* Sin padding adicional */
        }
        
        /* Asegurar estilos consistentes en todas las pestañas */
        .form-stage.active > div:first-child {
            margin-top: 0 !important;
            padding-top: 0 !important;
        }
        
        /* Enhanced Mobile Navigation for better consistency and visibility */
        @media (max-width: 480px) {
            .stage-navigation {
                display: flex !important;
                padding: 12px 15px !important;
                position: fixed !important;
                bottom: 0 !important;
                left: 0 !important;
                width: 100% !important;
                background-color: rgba(255, 255, 255, 0.98) !important;
                box-shadow: 0 -3px 10px rgba(0, 0, 0, 0.1) !important;
                z-index: 9999 !important; /* Aumentar z-index para asegurar visibilidad */
                transform: none !important; /* Evitar cualquier transformación */
                height: auto !important; /* Altura automática en lugar de fija */
                transition: none !important; /* Eliminar transiciones que puedan causar parpadeo */
                margin-top: 0 !important; /* Remove margin since it's fixed */
            }
            
            /* Optimized buttons for better touch targets */
            .stage-navigation .btn {
                padding: 8px 10px !important;
                min-width: 100px !important;
                font-size: 14px !important;
                height: auto !important;
                flex: 1 !important;
                border-radius: 6px !important;
            }
            
            /* Estilos responsivos para la sección de pago en móviles */
            .payment-container {
                padding: 15px !important;
                margin-bottom: 30px !important; /* Reducir espacio para acercar botón de pago */
            }
            
            .payment-title {
                font-size: 1rem !important;
                margin-bottom: 15px !important;
            }
            
            .price-details {
                padding: 15px !important;
                margin-bottom: 20px !important;
                border-radius: 8px !important;
            }
            
            .price-details p {
                font-size: 14px !important;
                margin: 6px 0 !important;
            }
            
            .price-details .price-total {
                font-size: 16px !important;
                margin-top: 10px !important;
                padding-top: 10px !important;
            }
            
            /* Hacer el campo de cupón más accesible en móviles */
            .coupon-field {
                flex-direction: column !important;
                gap: 8px !important;
            }
            
            .coupon-input {
                width: 100% !important;
                padding: 14px !important; /* Más alto para mejor interacción táctil */
                font-size: 16px !important; /* Más grande para evitar zoom en iOS */
                box-sizing: border-box !important;
            }
            
            .coupon-btn {
                width: 100% !important;
                padding: 14px !important;
                font-size: 16px !important;
                min-height: 50px !important; /* Altura mínima para interacción táctil más fácil */
            }
            
            /* We're standardizing on the fixed navigation bar, not the FAB */
            .mobile-nav-fab {
                display: none !important;
            }
            
            /* Increased padding to ensure content isn't hidden behind fixed navigation */
            .form-stage {
                padding: 20px 15px 70px !important; /* Reducir padding inferior para acercar botones */
            }
            
            /* Ensure progress indicator is properly visible */
            .stage-navigation .progress-container {
                display: flex !important;
                height: 4px !important;
                margin-top: 8px !important;
            }
            
            /* Reduce spacing for last elements to bring buttons closer in payment tab */
            .terms-container, 
            .field-group:last-of-type,
            .form-stage > *:last-child:not(.stage-navigation) {
                margin-bottom: 20px !important; /* Valor reducido para acercar botones */
            }
            
            /* Específicamente reducir el espacio en la pestaña de pago */
            #payment-stage .payment-container {
                margin-bottom: 20px !important; /* Reducir espaciado específicamente en pago */
            }
            
            /* Ocultar plugins de WhatsApp cuando el pago está activo en móvil */
            #payment-stage.active ~ .nta_wa_button,
            #payment-stage.active ~ .wa__btn_popup,
            #payment-stage.active ~ .wa__popup_chat_box,
            #payment-stage.active ~ [class*="whatsapp"],
            #payment-stage.active ~ [id*="whatsapp"],
            #payment-stage:target ~ .nta_wa_button,
            #payment-stage:target ~ .wa__btn_popup,
            #payment-stage:target ~ .wa__popup_chat_box,
            #payment-stage:target ~ [class*="whatsapp"],
            #payment-stage:target ~ [id*="whatsapp"] {
                display: none !important;
                visibility: hidden !important;
                opacity: 0 !important;
                pointer-events: none !important;
                z-index: -1 !important;
            }
            
            /* Ocultar completamente el FAB para evitar conflictos */
            .mobile-nav-fab, .mobile-nav-actions {
                display: none !important;
            }
            
            /* Mejoras de responsividad para elementos de contenido en móviles */
            .field-group {
                margin-bottom: 18px !important; /* Espacio entre grupos de campos */
            }
            
            /* Hacer cada campo más legible y accesible */
            .form-field {
                margin-bottom: 12px !important; /* Separación entre campos individuales */
            }
            
            /* Etiquetas más visibles */
            .field-label {
                font-size: 14px !important;
                font-weight: 600 !important;
                margin-bottom: 5px !important;
            }
            
            /* Inputs más grandes para mejor interacción táctil */
            .field-input {
                height: 42px !important; /* Inputs más altos para mejor interacción táctil */
                font-size: 15px !important; /* Texto más grande */
                padding: 8px 12px !important; /* Más padding interno */
            }
            
            /* Más espacio para campos en una misma fila en móvil */
            .field-inline-group,
            .form-row {
                display: flex;
                flex-direction: column !important; /* Columna en lugar de fila en móviles */
                gap: 12px !important;
                max-width: 100% !important;
                overflow: hidden !important;
            }
            
            .field-inline-group .form-field,
            .form-col {
                width: 100% !important; /* Ancho completo para cada campo */
                margin-right: 0 !important;
                flex: 1 0 100% !important;
                min-width: 0 !important;
                max-width: 100% !important;
                box-sizing: border-box !important;
            }
            
            #autorizacion_nombre,
            #autorizacion_dni {
                width: 100% !important;
                box-sizing: border-box !important;
                padding: 10px !important;
                font-size: 16px !important;
                border-radius: 6px !important;
                max-width: 100% !important;
            }
            
            /* Mejorar visualización de errores */
            .field-error {
                font-size: 12px !important;
                padding: 4px 8px !important;
                margin-top: 5px !important;
            }
            }
        }
        
        /* Animación mejorada entre páginas iniciales */
        .form-page {
            opacity: 0;
            transition: opacity 0.4s ease;
        }

        .form-page:not(.hidden) {
            opacity: 1;
        }

        /* Prevenir saltos de altura */
        #page-marketing, #page-requisitos, #page-pasos {
            min-height: 500px; /* Ajustar según diseño */
        }

        /* Mejorar visibilidad de transición */
        .marketing-container, .requirements-screen {
            transition: transform 0.3s ease, opacity 0.3s ease;
        }

        .form-page:not(.hidden) .marketing-container,
        .form-page:not(.hidden) .requirements-screen {
            transform: translateY(0);
            opacity: 1;
        }

        .form-page.hidden .marketing-container,
        .form-page.hidden .requirements-screen {
            transform: translateY(20px);
            opacity: 0;
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
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            padding: 30px;
            position: relative;
            transform: translateY(-20px);
            opacity: 0;
            transition: all 0.4s ease;
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
            min-height: 150px;
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
        
        /* Animaciones para validación del formulario */
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }
        
        @keyframes pulse {
            0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(var(--primary), 0.7); }
            50% { transform: scale(1.05); box-shadow: 0 0 0 10px rgba(var(--primary), 0); }
            100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(var(--primary), 0); }
        }
        
        .shake-animation {
            animation: shake 0.6s cubic-bezier(.36,.07,.19,.97) both;
        }
        
        .pulse-animation {
            animation: pulse 1s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }

        /* Estilos para el overlay de carga con pasos */
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

        /* Estilos para la sección de seguridad de pago */
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
        
        /* Responsive styles for payment modal */
        @media screen and (max-width: 768px) {
            .payment-modal-content {
                margin: 5% auto;
                width: 95%;
                padding: 20px;
            }
            
            #stripe-container {
                padding: 15px 0;
            }
            
            .security-badges {
                gap: 15px;
            }
            
            .confirm-payment-button {
                padding: 12px 20px;
                font-size: 15px;
            }
        }
        
        @media screen and (max-width: 480px) {
            .payment-modal-content {
                margin: 0;
                width: 100%;
                height: 100%;
                border-radius: 0;
                padding: 15px;
                display: flex;
                flex-direction: column;
            }
            
            .payment-modal-content h3 {
                font-size: 18px;
                margin-bottom: 15px;
                text-align: center;
                padding-right: 30px;
            }
            
            .close-modal {
                top: 10px;
                right: 10px;
                width: 30px;
                height: 30px;
                font-size: 22px;
            }
            
            #stripe-container {
                display: flex;
                flex-direction: column;
                padding: 10px 0;
            }
            
            .payment-element-container {
                margin-bottom: 10px;
            }
            
            .security-badges {
                flex-direction: column;
                gap: 10px;
            }
            
            .security-badge {
                justify-content: center;
            }
            
            .payment-security {
                margin: 10px 0;
                padding: 8px;
            }
            
            .terms-reminder {
                margin: 15px 0 !important;
                font-size: 12px !important;
            }
            
            .confirm-payment-button {
                margin-top: 10px;
                width: 100%;
                padding: 15px;
                font-size: 16px;
            }
            
            /* Ajustes para badges de seguridad en línea horizontal */
            #payment-modal .security-badges {
                flex-direction: row !important;
                flex-wrap: nowrap !important;
                justify-content: space-between !important;
                align-items: center !important;
                gap: 2px !important;
                width: 100% !important;
            }

            #payment-modal .security-badge {
                flex: 0 0 auto !important;
                font-size: 11px !important;
                white-space: nowrap !important;
                display: flex !important;
                align-items: center !important;
                margin: 0 !important;
                padding: 2px !important;
            }

            #payment-modal .security-badge i {
                margin-right: 3px !important;
            }

            /* Reducción de espacios verticales excesivos */
            #payment-modal #payment-element {
                margin: 12px 0 10px 0 !important;
                min-height: 150px !important;
            }

            #payment-modal .payment-security {
                margin: 5px 0 8px 0 !important;
                padding: 8px 5px !important;
            }

            #payment-modal .terms-reminder {
                margin: 8px 0 10px 0 !important;
                font-size: 12px !important;
            }

            #payment-modal .confirm-payment-button {
                margin-top: 10px !important;
                position: static !important;
            }

            /* Ajustes de espaciado vertical general */
            #payment-modal .payment-modal-content {
                display: flex !important;
                flex-direction: column !important;
                padding-top: 15px !important;
                padding-bottom: 15px !important;
                overflow-y: auto !important;
            }

            #payment-modal #stripe-container {
                padding: 0 !important;
                display: flex !important;
                flex-direction: column !important;
                flex: initial !important;
            }
            
            /* Ajustes adicionales para asegurar visualización correcta */
            #payment-modal {
                align-items: flex-start !important;
                padding-top: 10px !important;
            }

            #payment-modal .payment-element-container {
                min-height: 150px !important;
                height: auto !important;
                margin-bottom: 10px !important;
                flex: initial !important;
            }

            #payment-modal .security-badges::after {
                content: "";
                display: table;
                clear: both;
            }

            /* Mejora de visibilidad */
            #payment-modal .security-badge span {
                max-width: none !important;
                overflow: visible !important;
            }
        }

        @media (max-width: 576px) {
            .loading-container {
                width: 95%;
                padding: 20px;
            }
            
            .loading-title {
                font-size: 18px;
            }
            
            .loading-message {
                font-size: 14px;
                margin-bottom: 20px;
            }
            
            .loading-spinner {
                width: 50px;
                height: 50px;
                margin-bottom: 15px;
            }
            
            .loading-steps {
                flex-direction: column;
                gap: 20px;
                align-items: flex-start;
                padding-left: 30px;
                margin-top: 15px;
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
                width: 40px;
                height: 40px;
                font-size: 16px;
            }
            
            .loading-step-text {
                font-size: 13px;
            }
        }
        
        @media (max-width: 380px) {
            .loading-container {
                padding: 30px; /* Mismo padding que en desktop */
            }
            
            .loading-title {
                font-size: 22px; /* Mismo tamaño que en desktop */
                margin-bottom: 10px; /* Mismo margen que en desktop */
            }
            
            .loading-spinner {
                width: 60px; /* Mismo tamaño que en desktop */
                height: 60px; /* Mismo tamaño que en desktop */
                border-width: 6px; /* Mismo tamaño que en desktop */
            }
            
            .loading-step-icon {
                width: 50px; /* Mismo tamaño que en desktop */
                height: 50px; /* Mismo tamaño que en desktop */
                font-size: 20px; /* Mismo tamaño que en desktop */
            }
            
            .loading-step-text {
                font-size: 14px; /* Mismo tamaño que en desktop */
            }
        }
        
        /* Bloque final para asegurar márgenes idénticos en todos los dispositivos */
        @media (max-width: 576px) {
            /* Asegurar que todos los elementos tengan los mismos márgenes que en desktop */
            .form-stage {
                padding: 30px !important; /* Asegurar mismo padding lateral que en desktop */
                padding-bottom: 70px !important; /* Padding inferior reducido */
                min-height: auto !important; /* Permitir que se adapte al contenido */
            }
            
            .data-section {
                padding: 25px !important; /* Asegurar mismo padding que en desktop */
                margin-bottom: 20px !important; /* Asegurar mismo margen que en desktop */
            }
            
            .field-group {
                margin-bottom: 20px !important; /* Asegurar mismo margen que en desktop */
            }
            
            /* Eliminar márgenes específicos para elementos finales ya que usamos un padding estandarizado */
            .form-stage > *:last-child:not(.stage-navigation),
            .signature-container,
            .terms-container,
            .payment-details,
            .data-section:last-of-type {
                margin-bottom: 0 !important; /* Eliminar márgenes específicos */
            }
            
            .stage-navigation {
                padding: 12px 30px !important; /* Reducir padding vertical */
                margin-top: 0 !important; /* Eliminar margen superior */
                position: absolute !important; /* Forzar posición absoluta */
                bottom: 0 !important; /* Siempre en la parte inferior */
            }
            
            .stage-navigation .btn {
                padding: 0 1.5rem !important; /* Asegurar mismo padding que en desktop */
                height: 2.5rem !important; /* Reducir altura de botones */
                min-width: 140px !important; /* Asegurar mismo ancho mínimo que en desktop */
                font-size: 1rem !important; /* Asegurar mismo tamaño de fuente que en desktop */
            }
            
            /* Quitar regla duplicada ya que se ha mejorado arriba */
            
            /* Mantener espaciado interior de cada sección */
            .fields-grid {
                gap: 15px !important; /* Asegurar mismo espacio que en desktop */
            }
            
            /* Configurar contenedor principal */
            .interactive-form-container {
                padding-bottom: 0 !important; /* Eliminar padding inferior */
                min-height: auto !important; /* Permitir que se ajuste al contenido exactamente */
                overflow: visible !important; /* Asegurar que el contenido no se recorte */
            }
        }
        
        /* Estilos para la pantalla de carga post-pago */
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
    </style>

    <!-- Formulario principal -->
    <form id="hoja-asiento-form" action="" method="POST" enctype="multipart/form-data">
        <!-- Página Marketing (Portada) -->
        <div id="page-marketing" class="form-page">
            <div class="marketing-container full-width">
                <div class="marketing-content">
                    <div class="marketing-badge">Rápido y sencillo</div>
                    <h2 class="marketing-title">Copia Hoja de Asiento en minutos</h2>
                    <p class="marketing-description">Gestione la solicitud de copia de hoja de asiento de su embarcación sin complicaciones, en un proceso totalmente digital y seguro.</p>
                    
                    <div class="marketing-features">
                        <div class="feature-item">
                            <div class="feature-icon"><i class="fa-solid fa-clock"></i></div>
                            <div class="feature-text">Proceso rápido</div>
                        </div>
                        <div class="feature-item">
                            <div class="feature-icon"><i class="fa-solid fa-shield-alt"></i></div>
                            <div class="feature-text">100% seguro</div>
                        </div>
                        <div class="feature-item">
                            <div class="feature-icon"><i class="fa-solid fa-laptop"></i></div>
                            <div class="feature-text">Trámite online</div>
                        </div>
                    </div>
                    
                    <button type="button" class="btn btn-primary btn-lg marketing-button pulse-button" id="goto-requirements">
                        <i class="fa-solid fa-arrow-right"></i> Iniciar trámite
                    </button>
                </div>
                <div class="marketing-image">
                    <div class="form-3d-container">
                        <div class="form-3d-element">
                            <div class="form-3d-header">
                                <div class="form-3d-title">Solicitud Hoja de Asiento</div>
                                <div class="form-3d-steps">
                                    <div class="form-3d-step active"></div>
                                    <div class="form-3d-step"></div>
                                    <div class="form-3d-step"></div>
                                    <div class="form-3d-step"></div>
                                </div>
                            </div>
                            <div class="form-3d-content">
                                <div class="form-3d-field">
                                    <label>Nombre completo</label>
                                    <div class="form-3d-input"></div>
                                </div>
                                <div class="form-3d-field">
                                    <label>DNI</label>
                                    <div class="form-3d-input"></div>
                                </div>
                                <div class="form-3d-field">
                                    <label>Matrícula embarcación</label>
                                    <div class="form-3d-input active"></div>
                                </div>
                                <div class="form-3d-field">
                                    <label>Nombre del barco</label>
                                    <div class="form-3d-input"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Página Requisitos -->
        <div id="page-requisitos" class="form-page hidden">
            <div class="requirements-screen">
                <div class="requirements-header">
                    <h3 class="requirements-heading">
                        <i class="fa-solid fa-clipboard-check"></i> 
                        Documentación necesaria
                    </h3>
                    <p class="requirements-subheading">Para agilizar su trámite de solicitud, asegúrese de tener a mano:</p>
                </div>
                
                <div class="requirements-container">
                    <ul class="requirements-list">
                        <li><i class="fa-solid fa-id-card"></i> DNI del solicitante (usted)</li>
                        <li><i class="fa-solid fa-ship"></i> Información del barco (nombre, NIB, matrícula)</li>
                        <li><i class="fa-solid fa-signature"></i> Preparado para firmar digitalmente la solicitud</li>
                        <li><i class="fa-solid fa-credit-card"></i> Tarjeta de crédito/débito para el pago</li>
                    </ul>
                </div>
                
                <button type="button" class="btn btn-primary btn-lg start-button" id="goto-pasos">
                    <i class="fa-solid fa-arrow-right"></i> Siguiente
                </button>
            </div>
        </div>

        <!-- Página Pasos -->
        <div id="page-pasos" class="form-page hidden">
            <div class="requirements-screen">
                <div class="requirements-header">
                    <h3 class="requirements-heading">
                        <i class="fa-solid fa-list-ol"></i> 
                        Pasos del proceso
                    </h3>
                    <p class="requirements-subheading">Siga estos pasos para completar la solicitud:</p>
                </div>
                
                <div class="welcome-steps">
                    <div class="steps-container">
                        <div class="step-item">
                            <div class="step-number">1</div>
                            <div class="step-info">
                                <div class="step-name">Información personal</div>
                                <div class="step-desc">Ingrese sus datos personales y de la embarcación</div>
                            </div>
                        </div>
                        <div class="step-item">
                            <div class="step-number">2</div>
                            <div class="step-info">
                                <div class="step-name">Documentación</div>
                                <div class="step-desc">Suba el DNI y firme la autorización digital</div>
                            </div>
                        </div>
                        <div class="step-item">
                            <div class="step-number">3</div>
                            <div class="step-info">
                                <div class="step-name">Pago seguro</div>
                                <div class="step-desc">Realice el pago de forma segura a través de Stripe</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <button type="button" class="btn btn-primary btn-lg start-button" id="start-process">
                    <i class="fa-solid fa-play"></i> Comenzar el proceso
                </button>
            </div>
        </div>

        <!-- Menú superior -->
<!-- Menú superior eliminado y reemplazado por el menú circular -->
        
        <!-- NUEVO FORMULARIO INTERACTIVO -->
        <div class="interactive-form-container" style="display: none;">
            <!-- Menú de navegación mejorado con iconos y animaciones -->
            <div class="process-navigation" style="display: block;">
                <div class="process-steps">
                    <div class="process-step" data-stage="0">
                        <div class="step-number">
                            <i class="step-icon fa-solid fa-user-edit"></i>
                            <span class="step-digit">1</span>
                        </div>
                        <div class="step-info">
                            <span class="step-name">Datos personales</span>
                            <div class="step-description">Información personal y del vehículo</div>
                        </div>
                    </div>
                    <div class="process-step" data-stage="1">
                        <div class="step-number">
                            <i class="step-icon fa-solid fa-file-upload"></i>
                            <span class="step-digit">2</span>
                        </div>
                        <div class="step-info">
                            <span class="step-name">Documentación</span>
                            <div class="step-description">Subida de documentos</div>
                        </div>
                    </div>
                    <div class="process-step" data-stage="2">
                        <div class="step-number">
                            <i class="step-icon fa-solid fa-signature"></i>
                            <span class="step-digit">3</span>
                        </div>
                        <div class="step-info">
                            <span class="step-name">Firma</span>
                            <div class="step-description">Firma digital</div>
                        </div>
                    </div>
                    <div class="process-step" data-stage="3">
                        <div class="step-number">
                            <i class="step-icon fa-solid fa-credit-card"></i>
                            <span class="step-digit">4</span>
                        </div>
                        <div class="step-info">
                            <span class="step-name">Pago</span>
                            <div class="step-description">Finaliza tu solicitud</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Etapa 1: Datos personales -->
            <div class="form-stage active" data-stage="0">
                <div class="data-section animate-fadeIn">
                    <div class="data-section-title">
                        <i class="fa-solid fa-user"></i> Completa tus datos personales
                    </div>
                    <div class="fields-grid">
                        <div class="field-group">
                            <input type="text" id="customer_name" name="customer_name" class="field-input" placeholder=" " required>
                            <label for="customer_name" class="field-label">Nombre completo</label>
                            <div class="field-error">Campo obligatorio</div>
                        </div>
                        <div class="field-group">
                            <input type="text" id="customer_dni" name="customer_dni" class="field-input" placeholder=" " required>
                            <label for="customer_dni" class="field-label">DNI</label>
                            <div class="field-error">Campo obligatorio</div>
                        </div>
                        <div class="field-group">
                            <input type="email" id="customer_email" name="customer_email" class="field-input" placeholder=" " required>
                            <label for="customer_email" class="field-label">Correo electrónico</label>
                            <div class="field-error">Campo obligatorio</div>
                        </div>
                        <div class="field-group">
                            <!-- Reemplazar completamente el campo de teléfono para asegurar consistencia -->
                            <div class="phone-field-wrapper" style="position:relative;">
                                <input type="text" id="customer_phone" name="customer_phone" class="custom-phone-input" placeholder=" " required 
                                    style="width:100%; padding:12px 15px; font-size:15px; border:1px solid rgb(222,226,230); 
                                    border-radius:6px; color:rgb(52,58,64); background-color:white; transition:all 0.2s ease;
                                    height:46px; box-sizing:border-box; line-height:normal; appearance:none;">
                                <label for="customer_phone" class="custom-phone-label"
                                    style="position:absolute; top:50%; left:12px; transform:translateY(-50%); 
                                    font-size:14px; color:rgb(173,181,189); pointer-events:none; transition:all 0.2s ease;
                                    line-height:1; padding:0; background-color:transparent;">Teléfono</label>
                            </div>
                            <div class="field-error">Campo obligatorio</div>
                        </div>
                    </div>
                </div>

                <div class="data-section animate-fadeIn" style="animation-delay: 0.2s;">
                    <div class="data-section-title">
                        <i class="fa-solid fa-ship"></i> Información de la embarcación
                    </div>
                    <div class="fields-grid">
                        <div class="field-group">
                            <input type="text" id="boat_name" name="boat_name" class="field-input" placeholder=" " required>
                            <label for="boat_name" class="field-label">Nombre del barco</label>
                            <div class="field-error">Campo obligatorio</div>
                        </div>
                        <div class="field-group">
                            <input type="text" id="boat_matricula" name="boat_matricula" class="field-input" placeholder=" " required>
                            <label for="boat_matricula" class="field-label">Matrícula</label>
                            <div class="field-error">Campo obligatorio</div>
                        </div>
                        <div class="field-group full-width">
                            <input type="text" id="boat_nib" name="boat_nib" class="field-input" placeholder=" " required>
                            <label for="boat_nib" class="field-label">Número de Identificación del Barco (NIB)</label>
                            <div class="field-error">Campo obligatorio</div>
                        </div>
                    </div>
                </div>

                <div class="stage-navigation">
                    <div class="progress-indicator">
                        <div class="progress-indicator-fill" style="width: 25%"></div>
                    </div>
                    <div class="stage-navigation-wrapper">
                        <button type="button" class="btn btn-secondary" id="prev-stage-0" style="visibility: hidden;">
                            <i class="fa-solid fa-arrow-left"></i> Anterior
                        </button>
                        <button type="button" class="btn btn-primary" id="next-stage-0">
                            Continuar <i class="fa-solid fa-arrow-right"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Etapa 2: Documentación -->
            <div class="form-stage after" data-stage="1">
                <div class="upload-container animate-fadeIn">
                    <div class="upload-title">
                        <i class="fa-solid fa-id-card"></i> Sube tu DNI
                    </div>
                    <div class="upload-area" id="dni-upload-area">
                        <div class="upload-icon">
                            <i class="fa-solid fa-cloud-upload-alt"></i>
                        </div>
                        <div class="upload-text">
                            Arrastra y suelta tu DNI aquí o haz clic para seleccionar
                        </div>
                        <div class="upload-hint">Formatos aceptados: JPG, PNG o PDF</div>
                        <input type="file" id="upload-dni-propietario" name="upload_dni_propietario" class="upload-input" accept=".jpg,.jpeg,.png,.pdf" required>
                    </div>
                    <div class="upload-preview" id="dni-preview">
                        <div class="upload-preview-name"></div>
                        <div class="upload-preview-remove">
                            <i class="fa-solid fa-times"></i>
                        </div>
                    </div>
                </div>


                <div class="stage-navigation">
                    <div class="progress-indicator">
                        <div class="progress-indicator-fill" style="width: 50%"></div>
                    </div>
                    <div class="stage-navigation-wrapper">
                        <button type="button" class="btn btn-secondary" id="prev-stage-1">
                            <i class="fa-solid fa-arrow-left"></i> Anterior
                        </button>
                        <button type="button" class="btn btn-primary" id="next-stage-1">
                            Continuar <i class="fa-solid fa-arrow-right"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Etapa 3: Firma -->
            <div class="form-stage after" data-stage="2">
                <div class="signature-container animate-fadeIn">
                    <div class="signature-title">
                        <i class="fa-solid fa-user-tie"></i> Autorización de Representación
                        <span id="signature-status" class="signature-status">Sin firmar</span>
                    </div>
                    
                    <div class="signature-instructions">
                        Por favor, lea la siguiente autorización y firme en el espacio proporcionado.
                    </div>
                    
                    <div id="authorization-text">
                        <p class="auth-main-text" style="margin-bottom: 15px;">Por la presente, autorizo a <strong>Tramitfy S.L.</strong> con CIF B55388557 a actuar como mi representante legal para la tramitación y gestión del procedimiento de solicitud de copia de hoja de asiento de embarcación ante las autoridades competentes.</p>
                        
                        <div class="form-row" style="display: flex; gap: 15px; margin-bottom: 15px; flex-wrap: wrap; max-width: 100%; overflow: hidden;">
                            <div class="form-col" style="flex: 1; min-width: 200px; max-width: 100%; box-sizing: border-box;">
                                <label for="autorizacion_nombre" style="display: block; margin-bottom: 5px; font-weight: 500; font-size: 14px;">Nombre y apellidos del autorizante: <span class="required">*</span></label>
                                <input type="text" id="autorizacion_nombre" name="autorizacion_nombre" class="field-input" style="width: 100%; box-sizing: border-box; font-size: 16px; padding: 8px 12px; border-radius: 6px;" required>
                            </div>
                            
                            <div class="form-col" style="flex: 1; min-width: 200px; max-width: 100%; box-sizing: border-box;">
                                <label for="autorizacion_dni" style="display: block; margin-bottom: 5px; font-weight: 500; font-size: 14px;">DNI/NIE: <span class="required">*</span></label>
                                <input type="text" id="autorizacion_dni" name="autorizacion_dni" class="field-input" style="width: 100%; box-sizing: border-box; font-size: 16px; padding: 8px 12px; border-radius: 6px;" required>
                            </div>
                        </div>
                        
                        <p class="auth-secondary-text" style="margin-top: 15px;">Doy conformidad para que Tramitfy S.L. pueda presentar y recoger cuanta documentación sea necesaria, subsanar defectos, pagar tasas y realizar cuantas actuaciones sean precisas para la correcta finalización del procedimiento.</p>
                        
                        <input type="hidden" name="tipo_representante" value="representante">
                        <input type="hidden" id="signature_data" name="signature_data" value="">
                    </div>
                    
                    <div id="device-specific-instructions">
                        <div class="device-instruction" id="desktop-instruction">
                            <i class="fa-solid fa-mouse-pointer"></i> Use el ratón manteniendo pulsado el botón izquierdo
                        </div>
                        <div class="device-instruction" id="tablet-instruction" style="display: none;">
                            <i class="fa-solid fa-hand-pointer"></i> Use su dedo o un stylus para firmar en la pantalla
                        </div>
                        <div class="device-instruction" id="mobile-instruction" style="display: none;">
                            <i class="fa-solid fa-hand-pointer"></i> Use su dedo para firmar en la pantalla
                        </div>
                    </div>
                    
                    <div class="signature-pad-container">
                        <canvas id="signature-pad" width="600" height="200"></canvas>
                    </div>
                    
                    <div class="signature-actions">
                        <button type="button" id="clear-signature" class="clear-btn">
                            <i class="fa-solid fa-eraser"></i> Limpiar firma
                        </button>
                        <button type="button" id="zoom-signature" class="zoom-btn" style="display: none;">
                            <i class="fa-solid fa-search-plus"></i> Ampliar
                        </button>
                    </div>
                </div>

                <div class="stage-navigation">
                    <div class="progress-indicator">
                        <div class="progress-indicator-fill" style="width: 75%"></div>
                    </div>
                    <div class="stage-navigation-wrapper">
                        <button type="button" class="btn btn-secondary" id="prev-stage-2">
                            <i class="fa-solid fa-arrow-left"></i> Anterior
                        </button>
                        <button type="button" class="btn btn-primary" id="next-stage-2">
                            Continuar <i class="fa-solid fa-arrow-right"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Etapa 4: Pago -->
            <div class="form-stage after" data-stage="3">
                <div class="payment-container animate-fadeIn">
                    <div class="payment-title">
                        <i class="fa-solid fa-credit-card"></i> Información de pago
                    </div>
                    <div class="price-details">
                        <p><strong>Solicitud de copia hoja de asiento:</strong> <span>29.99€</span></p>
                        <p>Tasas + Honorarios: <span>26.11 €</span></p>
                        <p>IVA (21%): <span>3.88 €</span></p>
                        <p class="price-discount" style="display: none;">Descuento: <span>0.00 €</span></p>
                        <p class="price-total">Total a pagar: <span id="final-amount">29.99 €</span></p>
                    </div>

                    <div class="coupon-container">
                        <div class="coupon-field">
                            <input type="text" id="coupon_code" name="coupon_code" class="coupon-input" placeholder="¿Tienes un cupón de descuento?">
                            <button type="button" id="apply-coupon" class="coupon-btn">Aplicar</button>
                        </div>
                        <div class="coupon-message" id="coupon-message"></div>
                    </div>

                    <div class="terms-container">
                        <input type="checkbox" id="payment-terms" name="terms_accept_pago" class="terms-checkbox" required>
                        <label for="payment-terms" class="terms-text">
                            Acepto la <a href="https://tramitfy.es/politica-de-privacidad/" target="_blank">política de uso y tratamiento de datos</a> y acepto los <a href="https://tramitfy.es/terminos-y-condiciones-de-uso/" target="_blank">términos y condiciones de pago</a>
                        </label>
                    </div>
                </div>

                <div class="stage-navigation">
                    <div class="progress-indicator">
                        <div class="progress-indicator-fill" style="width: 100%"></div>
                    </div>
                    <div class="stage-navigation-wrapper" style="text-align: center; display: flex; justify-content: center;">
                        <button type="button" class="btn btn-secondary" id="prev-stage-3" style="display: none;">
                            <i class="fa-solid fa-arrow-left"></i> Anterior
                        </button>
                        <button type="button" class="btn btn-primary" id="submit-payment" style="animation: pulse-scale 1.5s infinite alternate; transition: all 0.3s ease;">
                            Realizar pago <i class="fa-solid fa-lock"></i>
                        </button>
                    </div>
                    
                    <style>
                        @keyframes pulse-scale {
                            0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(0, 123, 255, 0.4); }
                            100% { transform: scale(1.05); box-shadow: 0 0 0 10px rgba(0, 123, 255, 0); }
                        }
                        #submit-payment:hover {
                            transform: scale(1.1) !important;
                            box-shadow: 0 0 15px rgba(0, 123, 255, 0.6) !important;
                        }
                    </style>
                </div>
            </div>
            
            <!-- Navegación flotante para móviles -->
            <div class="mobile-nav-fab" id="mobile-nav-toggle">
                <i class="fa-solid fa-ellipsis"></i>
            </div>

            <div class="mobile-nav-actions" id="mobile-nav-actions">
                <div class="mobile-nav-action-btn prev" id="mobile-prev-btn">
                    <i class="fa-solid fa-arrow-left"></i>
                    <span class="action-label">Anterior</span>
                </div>
                <div class="mobile-nav-action-btn next" id="mobile-next-btn">
                    <i class="fa-solid fa-arrow-right"></i>
                    <span class="action-label">Continuar</span>
                </div>
            </div>

         
        </div>

        <!-- Modal de firma mejorado optimizado para móviles -->
        <div id="signature-modal-advanced" class="signature-modal-enhanced">
            <div class="enhanced-modal-content">
                <div class="enhanced-modal-header">
                    <h3>Firma Digital</h3>
                    <div class="orientation-indicator">
                        <i class="fas fa-mobile-alt"></i>
                        <span>Para una mejor experiencia, gire su dispositivo a horizontal</span>
                    </div>
                    <button class="enhanced-close-button" aria-label="Cerrar">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <div class="enhanced-signature-container">
                    <div class="signature-guide">
                        <div class="signature-line"></div>
                        <div class="signature-instruction">FIRME AQUÍ</div>
                    </div>
                    <canvas id="enhanced-signature-canvas"></canvas>
                </div>
                
                <div class="enhanced-modal-footer">
                    <p class="enhanced-instructions">Use el dedo para firmar en el área indicada</p>
                    <div class="enhanced-button-container">
                        <button class="enhanced-clear-button">
                            <i class="fas fa-eraser"></i> Borrar
                        </button>
                        <button class="enhanced-accept-button" disabled>
                            <i class="fas fa-check"></i> Confirmar firma
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- INICIO CÓDIGO PANTALLA DE CARGA POST-PAGO -->
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

        <!-- ID del trámite -->
        <input type="hidden" name="tramite_id" value="<?php echo esc_attr($tramite_id); ?>">
    </form>

    <!-- JavaScript para el nuevo formulario interactivo -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Asegurarse de que el campo personalizado de teléfono también reciba la marca de completado
        const phoneField = document.getElementById('customer_phone');
        if (phoneField) {
            phoneField.addEventListener('blur', function() {
                if (this.value.trim() !== '') {
                    this.parentElement.parentElement.classList.add('completed');
                } else {
                    this.parentElement.parentElement.classList.remove('completed');
                }
            });
        }
        let stripe;
        let elements;
        let clientSecret;
        let signaturePad;
        let enhancedSignaturePad;
        let mainSignatureData = null;
        let isScrolling = false;
        
        // Objeto para almacenar referencias a listeners
        const eventHandlers = {};

        // Precios detallados
        let feesPrice = 7.61;
        let baseFee = 18.50;
        let baseIVA = 3.88;
        let totalPrice = parseFloat((feesPrice + baseFee + baseIVA).toFixed(2));
        let currentPrice = totalPrice;
        let discountApplied = 0;
        let discountAmount = 0;

        // Referencia a las etapas del formulario y botones de navegación
        const formPages = document.querySelectorAll('.form-page');
        const interactiveContainer = document.querySelector('.interactive-form-container');
        const formStages = document.querySelectorAll('.form-stage');
        const progressFill = document.querySelector('.progress-fill');
        const progressSteps = document.querySelectorAll('.progress-step');

        // Botones de la pre-página
        const gotoRequirementsBtn = document.getElementById('goto-requirements');
        const gotoPasosBtn = document.getElementById('goto-pasos');
        const startProcessBtn = document.getElementById('start-process');

        // Elementos de firma
        const signatureCanvas = document.getElementById('signature-pad');
        const enhancedCanvas = document.getElementById('enhanced-signature-canvas');
        const enhancedModal = document.getElementById('signature-modal-advanced');
        const zoomSignatureBtn = document.getElementById('zoom-signature');
        const clearSignatureBtn = document.getElementById('clear-signature');
        const signatureStatusElement = document.getElementById('signature-status');

        // Estado del formulario
        let currentPage = 0; // Páginas de marketing/info
        let currentStage = 0; // Etapas del formulario interactivo
        let formSubmitted = false;

        // Utilidad para debounce (limitar llamadas repetidas)
        function debounce(func, wait) {
            let timeout;
            return function() {
                const context = this, args = arguments;
                clearTimeout(timeout);
                timeout = setTimeout(() => func.apply(context, args), wait);
            };
        }

        // Función para actualizar el indicador de progreso
        function updateProgress() {
            // La barra de progreso ahora se maneja a través de updateHeaderProgress
            // Esta función se mantiene para compatibilidad
            updateHeaderProgress();
        }

        // Función para mostrar la etapa adecuada del formulario
        function showStage(stageIndex) {
            console.log(`Cambiando a etapa ${stageIndex}`);
            
            // Guardar posición de scroll actual en móviles para mantenerla
            const isMobile = window.innerWidth <= 480;
            const currentScrollPos = isMobile ? window.scrollY : 0;
            
            // Capturar el contenedor antes de cualquier modificación
            const interactiveContainer = document.querySelector('.interactive-form-container');
            const targetStage = formStages[stageIndex];
            
            // Resetear cualquier altura fija previamente establecida
            interactiveContainer.style.height = 'auto';
            
            // Preparar todas las etapas antes de la transición
            formStages.forEach((stage, index) => {
                // Primero quitar las clases de posición
                stage.classList.remove('active', 'before', 'after');
                
                // Asignar la nueva clase según corresponda
                if (index === stageIndex) {
                    // La etapa objetivo ahora será visible pero sin transición aún
                    stage.style.opacity = '0'; 
                    stage.style.transition = 'none';
                    stage.classList.add('active');
                } else if (index < stageIndex) {
                    stage.classList.add('before');
                } else {
                    stage.classList.add('after');
                }
            });
            
            // Forzar reflow para aplicar los cambios inmediatamente
            void interactiveContainer.offsetWidth;
            
            // Capturar la altura natural de la etapa actual con margen extra para móviles
            const extraPadding = isMobile ? 100 : 60;
            const targetHeight = targetStage.scrollHeight + extraPadding;
            console.log(`Altura objetivo calculada: ${targetHeight}px (móvil: ${isMobile})`);
            
            // Asegurar que el contenedor tenga suficiente altura, con mínimo más alto para móviles
            const minHeight = isMobile ? 650 : 580;
            interactiveContainer.style.minHeight = Math.max(targetHeight, minHeight) + 'px';
            
            // Restaurar las transiciones y hacer visible la etapa
            targetStage.style.transition = '';
            targetStage.style.opacity = '1';
            
            // En dispositivos móviles, restaurar la posición de scroll para evitar saltos
            if (isMobile) {
                setTimeout(() => {
                    window.scrollTo({
                        top: currentScrollPos,
                        behavior: 'auto' // Usar 'auto' en lugar de 'smooth' para evitar animación
                    });
                    console.log(`Manteniendo posición de scroll en ${currentScrollPos}px`);
                }, 10);
            }
            
            // Actualizar estado
            currentStage = stageIndex;
            
            // Actualizar inmediatamente el menú de navegación circular
            // para una experiencia de usuario más fluida
            const processSteps = document.querySelectorAll('.process-step');
            processSteps.forEach((step) => {
                const stepStage = parseInt(step.getAttribute('data-stage'));
                
                // Actualizar clases
                step.classList.remove('active', 'completed');
                
                if (stepStage === stageIndex) {
                    step.classList.add('active');
                } else if (stepStage < stageIndex) {
                    step.classList.add('completed', 'clickable');
                }
                
                // Actualizar barras de progreso
                const progressBar = step.querySelector('.step-progress');
                if (progressBar) {
                    if (stepStage < stageIndex) {
                        progressBar.style.width = '100%';
                    } else if (stepStage === stageIndex) {
                        progressBar.style.width = '30%'; // Inicio del progreso en etapa actual
                    } else {
                        progressBar.style.width = '0%';
                    }
                }
            });
            
            // Inicialización específica para la etapa de firma
            if (stageIndex === 2) {
                setTimeout(() => {
                    console.log('Inicializando componentes de firma');
                    generateAuthorizationText();
                    
                    // Restaurar firma si existe
                    if (mainSignatureData && signaturePad) {
                        setTimeout(() => {
                            restoreSignature(signatureCanvas, signaturePad);
                        }, 200);
                    }
                    
                    // Verificar dispositivo para mejorar experiencia de firma
                    detectDevice();
                }, 300);
            }
            
            // Asegurar que los botones de navegación estén visibles
            document.querySelectorAll('.stage-navigation').forEach(nav => {
                nav.classList.remove('scrolling-down');
                nav.style.transform = 'translateY(0)';
                nav.style.opacity = '1';
                nav.style.display = 'flex';
                nav.style.visibility = 'visible';
            });
            
            // Eliminar el desplazamiento automático para evitar saltos
            // Solo recalcular la altura
            recalculateContainerHeight();
            
            // Recalcular altura nuevamente después de un retraso para capturar cambios dinámicos
            setTimeout(recalculateContainerHeight, 500);
        }

        // Función para recalcular altura cuando cambia el contenido
        function recalculateContainerHeight() {
            const activeStage = document.querySelector('.form-stage.active');
            if (!activeStage) return;
            
            const container = document.querySelector('.interactive-form-container');
            if (!container) return;
            
            // Verificar si estamos en móvil
            const isMobile = window.innerWidth <= 480;
            
            // Usar altura fija más conservadora para evitar fluctuaciones
            const minHeight = isMobile ? 800 : 600;
            
            // Obtener altura real del contenido con margen fijo
            const contentHeight = activeStage.scrollHeight;
            const fixedMargin = isMobile ? 200 : 100;
            const newHeight = contentHeight + fixedMargin;
            
            // CAMBIO IMPORTANTE: Nunca reducir la altura una vez establecida
            const currentHeight = parseInt(container.style.minHeight) || 0;
            const finalHeight = Math.max(newHeight, minHeight, currentHeight);
            
            // Aplicar la altura calculada al contenedor de manera más estable
            container.style.minHeight = finalHeight + 'px';
            
            // Padding fijo en lugar de calculado
            activeStage.style.paddingBottom = isMobile ? '120px' : '100px';
            
            console.log(`Altura estable calculada: ${finalHeight}px (móvil: ${isMobile})`);
            
            // Verificación adicional para iOS - usar altura fija más conservadora
            const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
            if (isIOS && isMobile) {
                // Usar una altura fija más conservadora para iOS
                const minIOSHeight = 950; // Valor alto para evitar scroll innecesario
                const finalIOSHeight = Math.max(finalHeight, minIOSHeight);
                container.style.minHeight = finalIOSHeight + 'px';
                console.log(`Altura fija para iOS aplicada: ${finalIOSHeight}px`);
            }
            
            // IMPORTANTE: No forzar el scroll automático en dispositivos móviles
            if (isMobile) {
                // Evitar cualquier manipulación del scroll que pueda hacer que la página vuelva a una posición específica
                console.log('Desactivado reposicionamiento de scroll en móviles');
            }
        }
        
        // Función para actualizar la vista según la página actual
        function updateView() {
            console.log(`Actualizando vista a página ${currentPage}`);
            
            // Guardar posición de scroll actual
            const currentScrollPos = window.scrollY;
            
            // El manejo del menú de navegación circular se realiza 
            // completamente dentro de initProcessNavigation
            
            // No hacer scroll automático entre páginas iniciales
// No hacer scroll en ningún momento
console.log('Navegación sin desplazamiento automático');
            
            // Ocultar todas las páginas y mostrar la actual
            formPages.forEach((page, index) => {
                if (index === currentPage) {
                    page.classList.remove('hidden');
                    // Añadir animación de entrada
                    page.style.animation = 'fadeIn 0.5s ease forwards';
                } else {
                    page.classList.add('hidden');
                    page.style.animation = '';
                }
            });

            // Mostrar el contenedor interactivo cuando llegamos a la página del formulario
            if (currentPage === 3) {
                // Asegurarse de que el contenedor tenga una altura mínima antes de mostrarlo
                if (interactiveContainer.style.height === '' || interactiveContainer.style.height === 'auto') {
                    interactiveContainer.style.height = '580px';
                }
                
                // Añadir clase de transición para evitar parpadeos
                interactiveContainer.classList.add('transitioning');
                interactiveContainer.style.display = 'block';
                
                // Asegurarnos de que el menú de navegación sea visible
                const processNavigation = document.querySelector('.process-navigation');
                if (processNavigation) {
                    processNavigation.style.display = 'block';
                    console.log('Menú de navegación mostrado desde updateView');
                }
                
                // Dar tiempo para que el navegador aplique los cambios
                setTimeout(() => {
                    // Inicializar componentes específicos
                    detectDevice();
                    initSignaturePad();
                    
                    // Mostrar primera etapa solo después de que el contenedor esté visible
                    showStage(0);
                    
                    // Quitar clase de transición después de completar
                    setTimeout(() => {
                        interactiveContainer.classList.remove('transitioning');
                    }, 600);
                    
                    // No forzar desplazamiento para evitar problemas
                    setTimeout(() => {
                        recalculateContainerHeight();
                    }, 100);
                }, 50);
            } else {
                interactiveContainer.style.display = 'none';
                
                // No necesitamos gestionar la visibilidad del menú aquí,
                // ya que eso lo hace initProcessNavigation
                
                // Si estamos en la portada o páginas intermedias, animar elementos 3D
                if (currentPage <= 2) {
                    const form3dElement = document.querySelector('.form-3d-element');
                    if (form3dElement) {
                        form3dElement.style.animation = 'float 3s ease-in-out infinite';
                    }
                }
            }

            // Si estamos en la etapa de firma, generar el texto de autorización
            if (currentStage === 2 && currentPage === 3) {
                console.log('En etapa de firma, generando texto de autorización');
                generateAuthorizationText();
            }

            // Si estamos en la etapa de pago, inicializar Stripe
            if (currentStage === 3 && currentPage === 3 && !stripe) {
                console.log('En etapa de pago, inicializando Stripe');
                initializeStripe();
            }
            
            console.log(`Vista actualizada a página ${currentPage}`);
        }

        // Inicializar Stripe
        // Inicialización de Stripe mejorada
        async function initializeStripe(amount) {
            console.log("Inicializando Stripe con el monto: ", amount);
            const amountCents = Math.round(amount * 100);
            
            // Seleccionar el elemento dentro del modal
            const modalPaymentElement = document.querySelector('#payment-modal #payment-element');
            const modalLoadingElement = document.querySelector('#payment-modal #stripe-loading');
            const modalMessageElement = document.querySelector('#payment-modal #payment-message');
            
            if (!modalPaymentElement || !modalLoadingElement || !modalMessageElement) {
                console.error("No se encontraron los elementos del modal de pago");
                return;
            }
            
            // Mostrar el spinner de carga dentro del modal
            modalLoadingElement.style.display = 'block';
            modalPaymentElement.innerHTML = '';
            modalMessageElement.className = 'hidden';

            // Inicializar Stripe con la clave pública (IGUAL QUE RECUPERAR DOCUMENTACIÓN)
            console.log('💳 Inicializando Stripe con clave pública...');
            const stripePublicKey = '<?php echo (HOJA_ASIENTO_STRIPE_MODE === "test") ? HOJA_ASIENTO_STRIPE_TEST_PUBLIC_KEY : HOJA_ASIENTO_STRIPE_LIVE_PUBLIC_KEY; ?>';
            console.log('💳 Usando clave:', stripePublicKey.substring(0, 15) + '...');
            console.log('💳 Modo:', '<?php echo HOJA_ASIENTO_STRIPE_MODE; ?>');
            stripe = Stripe(stripePublicKey);
            console.log('✅ Stripe object creado:', stripe);

            try {
                // Crear el payment intent
                const response = await fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=create_payment_intent_hoja_asiento&amount=${amountCents}`
                });
                
                // Procesar la respuesta del Payment Intent
                const result = await response.json();
                
                if (result && result.error) {
                    console.error("Error al crear Payment Intent:", result.error);
                    modalMessageElement.textContent = 'Error al crear la intención de pago: ' + result.error;
                    modalMessageElement.className = 'error';
                    modalLoadingElement.style.display = 'none';
                    return;
                }
                
                // Verificar que el resultado contenga un clientSecret
                if (!result || !result.clientSecret) {
                    console.error("Error: No se recibió un clientSecret válido");
                    modalMessageElement.textContent = 'Error al crear la intención de pago: No se recibió un ID de cliente válido';
                    modalMessageElement.className = 'error';
                    modalLoadingElement.style.display = 'none';
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
                            name: document.getElementById('customer_name').value || '',
                            email: document.getElementById('customer_email').value || '',
                            phone: document.getElementById('customer_phone').value || '',
                        }
                    }
                });
                
                // Limpiar cualquier contenido existente y montar el elemento en el modal
                modalPaymentElement.innerHTML = '';
                
                // Montar el elemento de pago dentro del modal
                setTimeout(() => {
                    paymentElement.mount('#payment-element');
                    modalLoadingElement.style.display = 'none';
                    console.log("Elemento de pago montado correctamente en el modal");
                }, 300);
            } catch (err) {
                console.error("Error al inicializar Stripe:", err);
                document.getElementById('payment-message').textContent = 'Error al inicializar el sistema de pago: ' + err.message;
                document.getElementById('payment-message').className = 'error';
                document.getElementById('stripe-loading').style.display = 'none';
            }
        }
        
        // Procesamiento del pago con overlay visual de pasos
        document.getElementById('confirm-payment-button').addEventListener('click', async function() {
            // Obtener referencias a los elementos del modal
            const loadingOverlay = document.getElementById('loading-overlay');
            const paymentModal = document.getElementById('payment-modal');
            const paymentMessage = document.querySelector('#payment-modal #payment-message');
            
            // Mostrar overlay de carga y deshabilitar el botón
            loadingOverlay.style.display = 'flex';
            this.disabled = true;
            
            // Activar el primer paso (payment)
            updateLoadingStep('payment');
            
            // Limpiar mensajes anteriores
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
                
                // NO ocultar el modal de pago todavía (lo haremos después de confirmar el pago exitoso)
                
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
                                name: document.getElementById('customer_name').value.trim(),
                                email: document.getElementById('customer_email').value.trim(),
                                phone: document.getElementById('customer_phone').value.trim(),
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
                
                // Recopilar los datos para enviar al servidor
                let formData = new FormData(document.getElementById('hoja-asiento-form'));
                formData.append('action', 'send_emails_hoja_asiento');
                formData.append('payment_amount', currentPrice.toFixed(2));
                
                // Enviar emails mientras se muestra el segundo paso
                try {
                    const emailResponse = await fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                        method: 'POST', 
                        body: formData
                    });
                    
                    const emailResult = await emailResponse.json();
                    console.log('Resultado de envío de emails:', emailResult);
                } catch (emailError) {
                    console.error('Error al enviar emails:', emailError);
                }
                
                // Activar el tercer paso (complete)
                updateLoadingStep('complete');
                
                // Esperar un momento y luego enviar el form final
                setTimeout(() => {
                    try {
                        submitForm();
                    } catch (submitError) {
                        console.error("Error al enviar el formulario final:", submitError);
                        loadingOverlay.style.display = 'none';
                        this.disabled = false;
                        
                        // Mostrar error en el mensaje
                        const errorMsg = document.querySelector('.loading-message');
                        if (errorMsg) {
                            errorMsg.textContent = 'Error al procesar su solicitud: ' + submitError.message;
                            errorMsg.style.color = 'rgb(var(--error))';
                        }
                    }
                }, 1000);
                
            } catch (err) {
                console.error("Error inesperado:", err);
                paymentMessage.textContent = 'Ocurrió un error al procesar el pago: ' + err.message;
                paymentMessage.className = 'error';
                loadingOverlay.style.display = 'none';
                this.disabled = false;
            }
        });

        // Función para actualizar los pasos visuales en el overlay de carga
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
            
            // Asegurar visibilidad del overlay
            const loadingOverlay = document.getElementById('loading-overlay');
            loadingOverlay.style.display = 'flex';
            loadingOverlay.style.zIndex = '9999';
            document.body.style.overflow = 'hidden';
        }

        // Configurar canvas para firmas
        function setupCanvas(canvas) {
            if (!canvas) return;
            
            try {
                const ctx = canvas.getContext('2d');
                if (!ctx) return;
                
                ctx.fillStyle = "#ffffff";
                ctx.fillRect(0, 0, canvas.width, canvas.height);
                
                // Propiedades críticas para prevenir problemas táctiles
                canvas.style.touchAction = 'none';
                canvas.style.msTouchAction = 'none';
                canvas.style.userSelect = 'none';
                canvas.style.webkitUserSelect = 'none';
                canvas.style.mozUserSelect = 'none';
                canvas.style.msUserSelect = 'none';
                
                // Aceleración por hardware para mejor rendimiento
                canvas.style.transform = 'translateZ(0)';
                canvas.style.webkitTransform = 'translateZ(0)';
                canvas.style.willChange = 'transform';
                
                // Lista completa de eventos a prevenir
                const events = [
                    'touchstart', 'touchmove', 'touchend', 'touchcancel', 
                    'gesturestart', 'gesturechange', 'gestureend'
                ];
                
                function preventDefaultBehavior(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    return false;
                }
                
                events.forEach(event => {
                    canvas.addEventListener(event, preventDefaultBehavior, { passive: false });
                });
                
                canvas.addEventListener('mousedown', function(e) {
                    e.stopPropagation();
                });
                
                // Bloquear el desplazamiento durante la firma en móviles
                canvas.addEventListener('touchstart', function(event) {
                    const scrollPos = {
                        x: window.scrollX || window.pageXOffset,
                        y: window.scrollY || window.pageYOffset
                    };
                    
                    isScrolling = true;
                    
                    function maintainScrollPosition() {
                        window.scrollTo(scrollPos.x, scrollPos.y);
                    }
                    
                    const scrollInterval = setInterval(maintainScrollPosition, 5);
                    
                    function onTouchEnd() {
                        clearInterval(scrollInterval);
                        isScrolling = false;
                        canvas.removeEventListener('touchend', onTouchEnd);
                        canvas.removeEventListener('touchcancel', onTouchEnd);
                    }
                    
                    canvas.addEventListener('touchend', onTouchEnd, { once: true });
                    canvas.addEventListener('touchcancel', onTouchEnd, { once: true });
                }, { passive: false });
            } catch (err) {
                console.error('Error al configurar canvas:', err);
            }
        }

        // Redimensionar canvas
        function resizeCanvas(canvas, isModal = false) {
            if (!canvas) return;
            
            try {
                const ratio = window.devicePixelRatio || 1;
                const container = canvas.parentElement;
                
                if (!container) return;
                
                const containerWidth = container.clientWidth;
                
                let height;
                if (isModal) {
                    height = Math.min(window.innerHeight * 0.5, containerWidth * 0.7);
                    if (window.innerWidth <= 480) {
                        height = Math.min(window.innerHeight * 0.4, containerWidth * 0.8);
                    }
                } else {
                    if (window.innerWidth <= 480) {
                        height = 120;
                    } else if (window.innerWidth <= 768) {
                        height = 150;
                    } else {
                        height = 180;
                    }
                }
                
                // Establecer dimensiones físicas considerando DPI
                canvas.width = containerWidth * ratio;
                canvas.height = height * ratio;
                
                // Establecer dimensiones visuales via CSS
                canvas.style.width = containerWidth + 'px';
                canvas.style.height = height + 'px';
                
                // Escalar el contexto para DPI
                const context = canvas.getContext('2d');
                if (context) {
                    context.scale(ratio, ratio);
                    context.fillStyle = "#ffffff";
                    context.fillRect(0, 0, containerWidth, height);
                }
                
                return { width: containerWidth, height: height, ratio: ratio };
            } catch (err) {
                console.error('Error al redimensionar canvas:', err);
            }
        }

        // Inicializar pad de firma
        function initSignaturePad() {
            console.log('Inicializando pad de firma...');
            if (!signatureCanvas) {
                console.error('Canvas de firma no encontrado');
                return;
            }
            
            try {
                // Limpieza de instancias anteriores
                if (signaturePad) {
                    console.log('Limpiando instancia anterior de SignaturePad');
                    signaturePad.off();
                    signaturePad = null;
                }
                
                // Verificar que el canvas esté visible en el DOM
                const canvasRect = signatureCanvas.getBoundingClientRect();
                if (canvasRect.width === 0 || canvasRect.height === 0) {
                    console.warn('Canvas de firma tiene dimensiones cero, esperando redimensionamiento...');
                    // Intentar inicializar después de un retraso
                    setTimeout(initSignaturePad, 300);
                    return;
                }
                
                console.log(`Canvas dimensiones: ${canvasRect.width}x${canvasRect.height}`);
                
                // Configurar correctamente el canvas con dimensiones precisas
                const containerWidth = signatureCanvas.parentElement.clientWidth;
                const devicePixelRatio = window.devicePixelRatio || 1;
                
                // Determinar altura basada en el tamaño de pantalla
                const height = window.innerWidth <= 480 ? 120 : 
                               window.innerWidth <= 768 ? 150 : 180;
                
                console.log(`Configurando canvas con ancho=${containerWidth}, altura=${height}, ratio=${devicePixelRatio}`);
                
                // Configurar dimensiones físicas del canvas
                signatureCanvas.width = containerWidth * devicePixelRatio;
                signatureCanvas.height = height * devicePixelRatio;
                
                // Configurar dimensiones visuales
                signatureCanvas.style.width = containerWidth + 'px';
                signatureCanvas.style.height = height + 'px';
                
                // Escalar el contexto para alta resolución
                const ctx = signatureCanvas.getContext('2d');
                ctx.scale(devicePixelRatio, devicePixelRatio);
                ctx.fillStyle = '#ffffff';
                ctx.fillRect(0, 0, containerWidth, height);
                
                // Preparar eventos táctiles especiales
                setupCanvas(signatureCanvas);
                
                // Crear un campo de firma oculto si no existe
                if (!document.getElementById('signature_data')) {
                    console.log('Creando campo oculto para datos de firma');
                    const hiddenField = document.createElement('input');
                    hiddenField.type = 'hidden';
                    hiddenField.id = 'signature_data';
                    hiddenField.name = 'signature_data';
                    signatureCanvas.parentNode.appendChild(hiddenField);
                }
                
                // Opciones optimizadas para dispositivos touch
                const signatureOptions = {
                    minWidth: 1,
                    maxWidth: 2.5,
                    penColor: "rgb(0, 0, 0)",
                    backgroundColor: "rgb(255, 255, 255)",
                    throttle: 16,
                    velocityFilterWeight: 0.6,
                    dotSize: 2.5
                };
                
                // Crear la instancia de SignaturePad
                if (typeof SignaturePad !== 'function') {
                    console.error('La librería SignaturePad no está disponible');
                    return;
                }
                
                console.log('Creando nueva instancia de SignaturePad');
                signaturePad = new SignaturePad(signatureCanvas, signatureOptions);
                
                // Verificar iOS para tratamiento especial
                const isIOS = /iPad|iPhone|iPod/i.test(navigator.userAgent);
                if (isIOS) {
                    console.log('Dispositivo iOS detectado, aplicando optimizaciones simplificadas');
                    // No manipular propiedades de transform que pueden causar problemas
                    // Solo enfocarse en la configuración del canvas
                    
                    // Asegurarnos que el canvas tenga los ajustes correctos para iOS
                    if (signatureCanvas) {
                        signatureCanvas.style.touchAction = 'none';
                        signatureCanvas.style.msTouchAction = 'none';
                        signatureCanvas.style.webkitTapHighlightColor = 'rgba(0,0,0,0)';
                    }
                }
                
                // Restaurar firma si existe
                if (mainSignatureData) {
                    console.log('Restaurando firma existente');
                    setTimeout(() => {
                        restoreSignature(signatureCanvas, signaturePad);
                    }, 200);
                } else {
                    console.log('No hay firma para restaurar');
                }
                
                // Eventos para guardar firma
                console.log('Configurando eventos para guardar firma');
                const saveHandler = function() {
                    console.log('Guardando datos de firma');
                    saveSignatureData();
                };
                
                signatureCanvas.addEventListener('pointerup', saveHandler);
                signatureCanvas.addEventListener('mouseup', saveHandler);
                signatureCanvas.addEventListener('touchend', saveHandler);
                
                // Eventos para restaurar firma en scroll
                window.addEventListener('scroll', debounce(() => {
                    if (mainSignatureData && signaturePad) {
                        console.log('Restaurando firma después de scroll');
                        restoreSignature(signatureCanvas, signaturePad);
                    }
                }, 300), { passive: true });
                
                // Evento para limpiar firma
                if (clearSignatureBtn) {
                    clearSignatureBtn.addEventListener('click', function() {
                        console.log('Limpiando firma');
                        if (signaturePad) {
                            // Limpiar el pad usando el método clear
                            signaturePad.clear();
                            
                            // Además, limpiar también el canvas completamente
                            const canvas = document.getElementById('signature-pad');
                            if (canvas) {
                                const ctx = canvas.getContext('2d');
                                const containerWidth = canvas.parentElement.clientWidth;
                                const devicePixelRatio = window.devicePixelRatio || 1;
                                const height = window.innerWidth <= 480 ? 120 : 
                                               window.innerWidth <= 768 ? 150 : 180;
                                
                                // Limpiar todo el canvas con fondo blanco
                                ctx.setTransform(1, 0, 0, 1, 0, 0); // Reset transformación
                                ctx.fillStyle = '#ffffff';
                                ctx.fillRect(0, 0, canvas.width, canvas.height);
                                ctx.scale(devicePixelRatio, devicePixelRatio); // Reestablecer escala
                            }
                        }
                        mainSignatureData = null;
                        
                        const signatureDataField = document.getElementById('signature_data');
                        if (signatureDataField) {
                            signatureDataField.value = '';
                        }
                        
                        try {
                            localStorage.removeItem('matriculacion_signature');
                        } catch (e) {
                            console.warn('No se pudo limpiar firma del localStorage:', e);
                        }
                        
                        updateSignatureStatus();
                    });
                }
                
                // Restaurar firma desde localStorage
                try {
                    console.log('Intentando restaurar firma desde localStorage');
                    const savedSignature = localStorage.getItem('matriculacion_signature');
                    if (savedSignature && !mainSignatureData) {
                        console.log('Firma encontrada en localStorage');
                        mainSignatureData = savedSignature;
                        setTimeout(() => {
                            restoreSignature(signatureCanvas, signaturePad);
                        }, 200);
                    }
                } catch (err) {
                    console.warn('Error al acceder a localStorage:', err);
                }
                
                // Actualizar estado de la firma
                updateSignatureStatus();
                
                console.log('Inicialización de firma completada');
            } catch (e) {
                console.error('Error al inicializar SignaturePad:', e);
            }
            
            // Inicializar experiencia de firma mejorada para móviles
            initEnhancedSignatureExperience();
        }
        
        // Función para añadir listeners de manera segura
        function addSafeEventListener(element, event, handler, options) {
            if (!element) return;
            
            // Crear un ID único para el elemento si no tiene uno
            if (!element.id) {
                element.id = 'elem_' + Math.random().toString(36).substr(2, 9);
            }
            
            // Eliminar handler anterior si existe
            if (eventHandlers[element.id] && eventHandlers[element.id][event]) {
                element.removeEventListener(event, eventHandlers[element.id][event]);
            }
            
            // Inicializar si no existe
            if (!eventHandlers[element.id]) eventHandlers[element.id] = {};
            
            // Guardar referencia y añadir listener
            eventHandlers[element.id][event] = handler;
            element.addEventListener(event, handler, options);
            
            console.log(`Añadido event listener ${event} a elemento ${element.id}`);
        }
        
        // Función para detectar dispositivo y ajustar UI
        function detectDevice() {
            try {
                console.log('Detectando tipo de dispositivo...');
                
                const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
                const isTablet = /iPad|Android(?!.*Mobile)/i.test(navigator.userAgent) || (isMobile && window.innerWidth > 768);
                const isPhone = isMobile && !isTablet;
                const isIOS = /iPad|iPhone|iPod/i.test(navigator.userAgent) && !window.MSStream;
                
                // Para iOS, establecer altura fija más alta y evitar recálculos frecuentes
                if (isIOS) {
                    const container = document.querySelector('.interactive-form-container');
                    if (container) {
                        // Usar una altura fija más conservadora para iOS
                        const minIOSHeight = 950; // Valor alto para evitar scroll innecesario
                        container.style.minHeight = minIOSHeight + 'px';
                        
                        // Scroll controls para iOS
                        document.body.style.webkitOverflowScrolling = 'touch';
                        
                        // Ya no modificamos el scroll al enfocar campos
                        if (isPhone) {
                            console.log('Configuración para dispositivos móviles sin manipulación de scroll');
                        }
                    }
                }
                
                console.log(`Tipo de dispositivo: ${isPhone ? 'Móvil' : isTablet ? 'Tablet' : 'Desktop'}`);
                
                // Actualizar instrucciones específicas para el dispositivo
                const deviceInstructions = {
                    desktop: document.getElementById('desktop-instruction'),
                    tablet: document.getElementById('tablet-instruction'),
                    mobile: document.getElementById('mobile-instruction')
                };
                
                if (deviceInstructions.desktop) {
                    deviceInstructions.desktop.style.display = (!isMobile && !isTablet) ? 'block' : 'none';
                }
                
                if (deviceInstructions.tablet) {
                    deviceInstructions.tablet.style.display = isTablet ? 'block' : 'none';
                }
                
                if (deviceInstructions.mobile) {
                    deviceInstructions.mobile.style.display = (isMobile && !isTablet) ? 'block' : 'none';
                }
                
                // Optimizaciones para dispositivos móviles
                if (isMobile) {
                    console.log('Aplicando optimizaciones para dispositivos móviles');
                    
                    // Modificar instrucciones de firma - sin cambiar HTML directamente
                    const signatureInstructions = document.querySelector('.signature-instructions');
                    if (signatureInstructions) {
                        // Usar una clase en lugar de modificar el HTML directamente
                        signatureInstructions.classList.add('mobile-instructions');
                        
                        // Solo actualizar si no tiene el estilo ya aplicado
                        if (signatureInstructions.style.backgroundColor !== 'rgba(var(--warning), 0.1)') {
                            signatureInstructions.style.backgroundColor = 'rgba(243, 156, 18, 0.1)'; // Usar valor directo en lugar de variable CSS
                            signatureInstructions.style.padding = '10px';
                            signatureInstructions.style.borderRadius = '4px';
                            signatureInstructions.style.marginBottom = '15px';
                            
                            // Cambiamos el texto solo si no lo hemos hecho antes
                            if (!signatureInstructions.dataset.mobileTextSet) {
                                signatureInstructions.dataset.originalHtml = signatureInstructions.innerHTML;
                                signatureInstructions.innerHTML = '<strong>En dispositivos móviles:</strong> Por favor, use el botón para firmar en pantalla completa.';
                                signatureInstructions.dataset.mobileTextSet = 'true';
                            }
                        }
                    }
                    
                    // Mejorar el botón de firma a pantalla completa
                    if (zoomSignatureBtn && !zoomSignatureBtn.classList.contains('mobile-enhanced')) {
                        // Asegurar que el botón esté visible en móviles
                        zoomSignatureBtn.style.display = 'flex';
                        zoomSignatureBtn.classList.add('mobile-enhanced');
                        
                        // Guardar estilos originales para restauración
                        if (!zoomSignatureBtn.dataset.originalStyles) {
                            zoomSignatureBtn.dataset.originalStyles = JSON.stringify({
                                padding: zoomSignatureBtn.style.padding,
                                fontSize: zoomSignatureBtn.style.fontSize,
                                backgroundColor: zoomSignatureBtn.style.backgroundColor,
                                color: zoomSignatureBtn.style.color,
                                width: zoomSignatureBtn.style.width,
                                marginTop: zoomSignatureBtn.style.marginTop,
                                borderRadius: zoomSignatureBtn.style.borderRadius,
                                boxShadow: zoomSignatureBtn.style.boxShadow,
                                animation: zoomSignatureBtn.style.animation,
                                innerHTML: zoomSignatureBtn.innerHTML
                            });
                        }
                        
                        // Aplicar estilos mejorados
                        zoomSignatureBtn.style.padding = '15px 20px';
                        zoomSignatureBtn.style.fontSize = '16px';
                        zoomSignatureBtn.style.backgroundColor = '#016d86'; // Color primario
                        zoomSignatureBtn.style.color = 'white';
                        zoomSignatureBtn.style.width = '100%';
                        zoomSignatureBtn.style.marginTop = '15px';
                        zoomSignatureBtn.style.borderRadius = '6px';
                        zoomSignatureBtn.style.boxShadow = '0 4px 10px rgba(1, 109, 134, 0.3)';
                        
                        // Usar una clase en lugar de animación inline
                        zoomSignatureBtn.classList.add('pulse-animation');
                        zoomSignatureBtn.innerHTML = '<i class="fa-solid fa-pen"></i> Pulsa aquí para firmar';
                        
                        // Agregar estilo para la animación de pulso si no existe
                        if (!document.getElementById('pulse-animation-style')) {
                            const style = document.createElement('style');
                            style.id = 'pulse-animation-style';
                            style.textContent = `
                                .pulse-animation {
                                    animation: pulse 2s infinite;
                                }
                                @keyframes pulse {
                                    0% { transform: scale(1); }
                                    50% { transform: scale(1.05); }
                                    100% { transform: scale(1); }
                                }
                            `;
                            document.head.appendChild(style);
                        }
                    }
                    
                    // En móviles, mantener el canvas principal visible pero con pista visual
                    // de que deben usar la versión de pantalla completa
                    if (signatureCanvas) {
                        signatureCanvas.classList.add('mobile-signature-canvas');
                        signatureCanvas.style.opacity = '0.7';
                        
                        // Crear un mensaje de ayuda si no existe
                        if (!document.getElementById('mobile-signature-helper')) {
                            const helperDiv = document.createElement('div');
                            helperDiv.id = 'mobile-signature-helper';
                            helperDiv.style.position = 'absolute';
                            helperDiv.style.top = '50%';
                            helperDiv.style.left = '50%';
                            helperDiv.style.transform = 'translate(-50%, -50%)';
                            helperDiv.style.padding = '10px';
                            helperDiv.style.borderRadius = '5px';
                            helperDiv.style.backgroundColor = 'rgba(0,0,0,0.6)';
                            helperDiv.style.color = 'white';
                            helperDiv.style.fontSize = '14px';
                            helperDiv.style.pointerEvents = 'none';
                            helperDiv.style.zIndex = '5';
                            helperDiv.textContent = 'Use el botón inferior para firmar';
                            
                            const container = signatureCanvas.parentElement;
                            if (container) {
                                container.style.position = 'relative';
                                container.appendChild(helperDiv);
                            }
                        }
                    }
                } else {
                    // Restaurar valores originales para escritorio
                    if (signatureCanvas) {
                        signatureCanvas.classList.remove('mobile-signature-canvas');
                        signatureCanvas.style.opacity = '';
                        
                        const helper = document.getElementById('mobile-signature-helper');
                        if (helper && helper.parentNode) {
                            helper.parentNode.removeChild(helper);
                        }
                    }
                    
                    const signatureInstructions = document.querySelector('.signature-instructions');
                    if (signatureInstructions && signatureInstructions.dataset.originalHtml) {
                        signatureInstructions.innerHTML = signatureInstructions.dataset.originalHtml;
                        signatureInstructions.style.backgroundColor = '';
                        signatureInstructions.style.padding = '';
                        signatureInstructions.style.borderRadius = '';
                        signatureInstructions.style.marginBottom = '';
                        signatureInstructions.classList.remove('mobile-instructions');
                        delete signatureInstructions.dataset.mobileTextSet;
                    }
                    
                    // Ocultar botón de ampliar en PC
                    if (zoomSignatureBtn) {
                        zoomSignatureBtn.style.display = 'none';
                    }
                }
                
                console.log('Detección de dispositivo completada');
            } catch (err) {
                console.error('Error en detectDevice:', err);
            }
        }

        // Guardar datos de firma
        function saveSignatureData() {
            if (signaturePad && !signaturePad.isEmpty()) {
                try {
                    mainSignatureData = signaturePad.toDataURL();
                    const signatureDataField = document.getElementById('signature_data');
                    if (signatureDataField) signatureDataField.value = mainSignatureData;
                    
                    try {
                        localStorage.setItem('matriculacion_signature', mainSignatureData);
                        localStorage.setItem('matriculacion_signature_canvas_width', signatureCanvas.width);
                        localStorage.setItem('matriculacion_signature_canvas_height', signatureCanvas.height);
                    } catch (e) {
                        console.warn('Could not save signature to localStorage:', e);
                    }
                    
                    updateSignatureStatus();
                } catch (err) {
                    console.error('Error saving signature data:', err);
                }
            }
        }

        // Restaurar firma
        function restoreSignature(targetCanvas, targetPad) {
            if (!mainSignatureData || !targetCanvas || !targetPad) return false;
            
            try {
                targetPad.clear();
                
                const image = new Image();
                
                image.onload = function() {
                    try {
                        const context = targetCanvas.getContext('2d');
                        if (!context) return false;
                        
                        context.fillStyle = "#ffffff";
                        context.fillRect(0, 0, targetCanvas.width, targetCanvas.height);
                        
                        // Usar mejor cálculo de ratio para mantener siempre en vertical
                        const dpr = window.devicePixelRatio || 1;
                        const canvasWidth = targetCanvas.width / dpr;
                        const canvasHeight = targetCanvas.height / dpr;
                        
                        // Usar un factor de escala más grande para una firma más prominente
                        let ratio;
                        if (image.width > image.height) {
                            // Si la firma es más ancha que alta
                            ratio = (canvasWidth * 1.2) / image.width;
                        } else {
                            // Si la firma es más alta que ancha
                            ratio = Math.max(
                                (canvasWidth * 0.95) / image.width,
                                (canvasHeight * 0.7) / image.height
                            );
                        }
                        
                        const newWidth = image.width * ratio;
                        const newHeight = image.height * ratio;
                        
                        // Centrar y elevar ligeramente la firma
                        const x = (canvasWidth - newWidth) / 2;
                        const y = (canvasHeight - newHeight) / 2 - (canvasHeight * 0.05);
                        
                        // Dibujar con anti-aliasing para mejorar calidad
                        context.imageSmoothingEnabled = true;
                        context.imageSmoothingQuality = 'high';
                        context.drawImage(image, x, y, newWidth, newHeight);
                        
                        targetPad._isEmpty = false;
                        
                        // Mejoras específicas para iOS
                        if (/iPhone|iPad|iPod/i.test(navigator.userAgent)) {
                            // Forzar repintar para iOS
                            requestAnimationFrame(() => {
                                try {
                                    // Pequeña alteración del canvas para forzar repintar
                                    context.fillStyle = "rgba(255,255,255,0.01)";
                                    context.fillRect(0, 0, 1, 1);
                                } catch (e) {}
                            });
                        }
                        
                        updateSignatureStatus();
                        
                        return true;
                    } catch (err) {
                        console.error('Error al restaurar la firma:', err);
                        return false;
                    }
                };
                
                image.onerror = function() {
                    console.error('Error al cargar la imagen de firma');
                    return false;
                };
                
                image.src = mainSignatureData;
                return true;
            } catch (err) {
                console.error('Error general al restaurar firma:', err);
                return false;
            }
        }

        // Actualizar estado de la firma
        function updateSignatureStatus() {
            if (!signatureStatusElement) return;
            
            if (mainSignatureData && (signaturePad && !signaturePad._isEmpty)) {
                signatureStatusElement.textContent = 'Firmado';
                signatureStatusElement.classList.add('signed');
                signatureStatusElement.classList.remove('empty');
                
                // También actualizar el estilo del contenedor
                const signatureContainer = document.querySelector('.signature-pad-container');
                if (signatureContainer) {
                    signatureContainer.style.borderColor = 'rgb(var(--success))';
                }
                
                // Si hay botón de zoom, actualizar estado
                const zoomBtn = document.getElementById('zoom-signature');
                if (zoomBtn) {
                    zoomBtn.innerHTML = '<i class="fa-solid fa-search-plus"></i> Ver firma';
                }
                
                // Actualizar el control de errores
                const errorElem = document.querySelector('.signature-container .field-error');
                if (errorElem) {
                    errorElem.style.display = 'none';
                }
            } else {
                signatureStatusElement.textContent = 'Sin firmar';
                signatureStatusElement.classList.remove('signed');
                signatureStatusElement.classList.add('empty');
                
                // Restaurar estilo del contenedor
                const signatureContainer = document.querySelector('.signature-pad-container');
                if (signatureContainer) {
                    signatureContainer.style.borderColor = 'rgb(var(--neutral-300))';
                }
                
                // Restaurar texto del botón de zoom
                const zoomBtn = document.getElementById('zoom-signature');
                if (zoomBtn) {
                    zoomBtn.innerHTML = '<i class="fa-solid fa-search-plus"></i> Ampliar';
                }
            }
        }

        // Variables globales para las funciones de ocultamiento de widgets
        let hideWhatsAppWidgets;
        let restoreWhatsAppWidgets;

        // Ocultar widgets de WhatsApp y otros chats durante la firma y el pago
        function handleSignatureModalVisibility() {
            // Selectores comunes de widgets de WhatsApp
            const whatsappWidgets = [
                '.nta_wa_button', '.wa__btn_popup', '.wa__popup_chat_box', 
                '[class*="whatsapp"]', '[id*="whatsapp"]', 
                '.fb_dialog', '.crisp-client', '.intercom-frame',
                '[class*="chat"]', '[id*="chat"]', '[class*="wa-"]'
            ];
            
            // Definir las funciones y asignarlas a las variables globales
            hideWhatsAppWidgets = function() {
                whatsappWidgets.forEach(selector => {
                    try {
                        document.querySelectorAll(selector).forEach(el => {
                            el.style.display = 'none';
                            el.style.visibility = 'hidden';
                            el.style.opacity = '0';
                            el.style.pointerEvents = 'none';
                            el.classList.add('hidden-during-signature');
                        });
                    } catch (e) {}
                });
                
                // Inyectar regla CSS para forzar ocultamiento
                if (!document.getElementById('hide-chat-widgets')) {
                    document.body.insertAdjacentHTML('beforeend', 
                        `<style id="hide-chat-widgets">
                            .nta_wa_button, .wa__btn_popup, .wa__popup_chat_box,
                            [class*="whatsapp"], [id*="whatsapp"], 
                            .fb_dialog, .crisp-client, .intercom-frame,
                            [class*="chat"], [id*="chat"], [class*="wa-"] {
                                display: none !important;
                                visibility: hidden !important;
                                opacity: 0 !important;
                                pointer-events: none !important;
                                z-index: -1 !important;
                            }
                        </style>`
                    );
                }
            };
            
            restoreWhatsAppWidgets = function() {
                document.querySelectorAll('.hidden-during-signature').forEach(el => {
                    el.style.display = '';
                    el.style.visibility = '';
                    el.style.opacity = '';
                    el.style.pointerEvents = '';
                    el.classList.remove('hidden-during-signature');
                });
                
                const styleTag = document.getElementById('hide-chat-widgets');
                if (styleTag) styleTag.remove();
            };
            
            // Ocultar widgets cuando se abra el modal de firma
            if (enhancedModal) {
                const observer = new MutationObserver((mutations) => {
                    const isVisible = window.getComputedStyle(enhancedModal).display !== 'none';
                    if (isVisible) {
                        hideWhatsAppWidgets();
                    } else {
                        setTimeout(restoreWhatsAppWidgets, 300);
                    }
                });
                observer.observe(enhancedModal, { attributes: true, attributeFilter: ['style'] });
                
                // También al hacer clic en el botón de cerrar
                const closeButton = enhancedModal.querySelector('.enhanced-close-button');
                if (closeButton) {
                    closeButton.addEventListener('click', () => {
                        setTimeout(restoreWhatsAppWidgets, 300);
                    });
                }
            }
            
            // Ocultar widgets al abrir el modal de firma
            if (zoomSignatureBtn) {
                zoomSignatureBtn.addEventListener('click', hideWhatsAppWidgets);
            }
            
            // Ocultar widgets en el modal/sección de pago
            document.addEventListener('DOMContentLoaded', function() {
                // Ocultar WhatsApp cuando se muestra la pestaña de pago
                const stageLinks = document.querySelectorAll('.stage-nav-link');
                if (stageLinks && stageLinks.length) {
                    stageLinks.forEach(link => {
                        link.addEventListener('click', function(e) {
                            // Si el enlace es para la sección de pago
                            if (this.getAttribute('data-target') === '#payment-stage' || 
                                this.getAttribute('href') === '#payment-stage' ||
                                this.innerText.toLowerCase().includes('pago')) {
                                // Ocultamos WhatsApp
                                hideWhatsAppWidgets();
                            } else if (document.querySelector('#payment-stage.active')) {
                                // Si estábamos en la sección de pago y vamos a otra, restaurar
                                setTimeout(restoreWhatsAppWidgets, 300);
                            }
                        });
                    });
                }
                
                // También ocultar widgets cuando se muestra un modal de pago
                const paymentModals = document.querySelectorAll('[id*="payment"][id*="modal"], [class*="payment"][class*="modal"]');
                if (paymentModals.length) {
                    paymentModals.forEach(modal => {
                        // Observamos cambios en el estilo para detectar cuando se abre/cierra
                        const observer = new MutationObserver((mutations) => {
                            const isVisible = window.getComputedStyle(modal).display !== 'none';
                            if (isVisible) {
                                hideWhatsAppWidgets();
                            } else {
                                setTimeout(restoreWhatsAppWidgets, 300);
                            }
                        });
                        observer.observe(modal, { attributes: true, attributeFilter: ['style', 'class'] });
                    });
                }
                
                // Si hay botones específicos que abren modales de pago
                const paymentButtons = document.querySelectorAll('[id*="payment"][id*="button"], .payment-button, #payment-button');
                if (paymentButtons.length) {
                    paymentButtons.forEach(button => {
                        button.addEventListener('click', hideWhatsAppWidgets);
                    });
                }
            });
        }

        // Función específica para el manejo avanzado de widgets en el modal de pago
        function enhancePaymentModalWidgetHandling() {
            // 1. Manejo específico para el modal de pago principal
            const paymentModal = document.getElementById('payment-modal');
            const submitPaymentBtn = document.getElementById('submit-payment');
            const closeModalBtn = document.querySelector('.close-modal');
            
            if (paymentModal && submitPaymentBtn) {
                // Ocultar widgets cuando se abre el modal de pago
                submitPaymentBtn.addEventListener('click', function() {
                    // Verificar términos primero (manteniendo lógica existente)
                    const paymentTerms = document.getElementById('payment-terms');
                    if (paymentTerms && !paymentTerms.checked) {
                        showError('Por favor, acepte la política de uso y los términos de pago');
                        return;
                    }
                    
                    // Ocultar widgets de WhatsApp antes de mostrar el modal
                    if (typeof hideWhatsAppWidgets === 'function') {
                        hideWhatsAppWidgets();
                    }
                    
                    // El resto del código para mostrar el modal permanece igual
                });
                
                // Restaurar widgets cuando se cierra el modal
                if (closeModalBtn) {
                    closeModalBtn.addEventListener('click', function() {
                        if (typeof restoreWhatsAppWidgets === 'function') {
                            setTimeout(restoreWhatsAppWidgets, 300);
                        }
                    });
                }
                
                // También restaurar cuando se cierra haciendo clic fuera del modal
                paymentModal.addEventListener('click', function(event) {
                    if (event.target === this) {
                        if (typeof restoreWhatsAppWidgets === 'function') {
                            setTimeout(restoreWhatsAppWidgets, 300);
                        }
                    }
                });
                
                // Observar cambios en el modal para manejar cualquier otra forma de cierre
                const observer = new MutationObserver((mutations) => {
                    const isVisible = window.getComputedStyle(paymentModal).display !== 'none' && 
                                     paymentModal.classList.contains('show');
                    if (isVisible) {
                        if (typeof hideWhatsAppWidgets === 'function') {
                            hideWhatsAppWidgets();
                        }
                    } else {
                        if (typeof restoreWhatsAppWidgets === 'function') {
                            setTimeout(restoreWhatsAppWidgets, 300);
                        }
                    }
                });
                observer.observe(paymentModal, { attributes: true, attributeFilter: ['style', 'class'] });
            }
            
            // 2. Manejo específico para la etapa de pago en el formulario
            const paymentStage = document.querySelector('.form-stage[data-stage="3"]');
            if (paymentStage) {
                // Verificar estado inicial - si ya está activo, ocultar widgets inmediatamente
                if (paymentStage.classList.contains('active')) {
                    if (typeof hideWhatsAppWidgets === 'function') {
                        hideWhatsAppWidgets();
                    }
                }
                
                // Observar cambios en la clase para detectar cuando se activa/desactiva
                const stageObserver = new MutationObserver((mutations) => {
                    const isActive = paymentStage.classList.contains('active');
                    if (isActive) {
                        if (typeof hideWhatsAppWidgets === 'function') {
                            hideWhatsAppWidgets();
                        }
                        
                        // Añadir CSS específico para esta etapa si no existe ya
                        if (!document.getElementById('hide-chat-widgets-payment-stage')) {
                            document.body.insertAdjacentHTML('beforeend', 
                                `<style id="hide-chat-widgets-payment-stage">
                                    .form-stage[data-stage="3"].active ~ .nta_wa_button,
                                    .form-stage[data-stage="3"].active ~ .wa__btn_popup,
                                    .form-stage[data-stage="3"].active ~ .wa__popup_chat_box,
                                    .form-stage[data-stage="3"].active ~ [class*="whatsapp"],
                                    .form-stage[data-stage="3"].active ~ [id*="whatsapp"],
                                    #payment-stage.active ~ .nta_wa_button,
                                    #payment-stage.active ~ .wa__btn_popup,
                                    #payment-stage.active ~ .wa__popup_chat_box,
                                    #payment-stage.active ~ [class*="whatsapp"],
                                    #payment-stage.active ~ [id*="whatsapp"] {
                                        display: none !important;
                                        visibility: hidden !important;
                                        opacity: 0 !important;
                                        pointer-events: none !important;
                                        z-index: -1 !important;
                                    }
                                </style>`
                            );
                        }
                    } else {
                        if (typeof restoreWhatsAppWidgets === 'function') {
                            setTimeout(restoreWhatsAppWidgets, 300);
                        }
                        
                        // Eliminar CSS específico
                        const styleTag = document.getElementById('hide-chat-widgets-payment-stage');
                        if (styleTag) styleTag.remove();
                    }
                });
                stageObserver.observe(paymentStage, { attributes: true, attributeFilter: ['class'] });
            }
            
            // 3. Observar el botón de confirmación de pago dentro del modal
            const confirmPaymentButton = document.getElementById('confirm-payment-button');
            if (confirmPaymentButton) {
                confirmPaymentButton.addEventListener('click', function() {
                    if (typeof hideWhatsAppWidgets === 'function') {
                        hideWhatsAppWidgets();
                    }
                });
            }
            
            // 4. Observar el overlay de carga para mantener widgets ocultos durante el procesamiento
            const loadingOverlay = document.getElementById('loading-overlay');
            if (loadingOverlay) {
                // Verificar estado inicial
                if (window.getComputedStyle(loadingOverlay).display !== 'none') {
                    if (typeof hideWhatsAppWidgets === 'function') {
                        hideWhatsAppWidgets();
                    }
                }
                
                // Observar cambios
                const loadingObserver = new MutationObserver((mutations) => {
                    const isVisible = window.getComputedStyle(loadingOverlay).display !== 'none';
                    if (isVisible) {
                        if (typeof hideWhatsAppWidgets === 'function') {
                            hideWhatsAppWidgets();
                        }
                    }
                });
                loadingObserver.observe(loadingOverlay, { attributes: true, attributeFilter: ['style'] });
            }
            
            // 5. Añadir manejo en navegación de etapas
            // Interceptar la función showStage para ocultar widgets al cambiar a la etapa de pago
            const originalShowStage = window.showStage;
            if (typeof originalShowStage === 'function') {
                window.showStage = function(stageIndex) {
                    // Llamar a la función original
                    originalShowStage(stageIndex);
                    
                    // Si estamos cambiando a la etapa de pago, ocultar widgets
                    if (stageIndex === 3) {
                        if (typeof hideWhatsAppWidgets === 'function') {
                            hideWhatsAppWidgets();
                        }
                    } else {
                        // Si salimos de la etapa de pago, restaurar widgets
                        if (typeof restoreWhatsAppWidgets === 'function') {
                            setTimeout(restoreWhatsAppWidgets, 300);
                        }
                    }
                };
            }
        }

        // Generar texto de autorización
        function generateAuthorizationText() {
            try {
                console.log('Generando texto de autorización...');
                const customerName = document.getElementById('customer_name').value.trim() || '[Su nombre]';
                const customerDNI = document.getElementById('customer_dni').value.trim() || '[Su DNI]';
                const boatName = document.getElementById('boat_name').value.trim() || '[Nombre del barco]';
                const boatNIB = document.getElementById('boat_nib').value.trim() || '[NIB del barco]';
                const boatMatricula = document.getElementById('boat_matricula').value.trim() || '[Matrícula del barco]';
                const currentDate = new Date().toLocaleDateString('es-ES');

                console.log(`Datos recuperados: ${customerName}, ${customerDNI}, ${boatName}`);

                // Actualizar los campos de autorización con los datos del usuario
                const nombreField = document.getElementById('autorizacion_nombre');
                if (nombreField) {
                    // Solo actualizar si está vacío o es el valor predeterminado
                    if (!nombreField.value.trim() || nombreField.value === '[Su nombre]') {
                        console.log(`Actualizando campo nombre con: ${customerName}`);
                        nombreField.value = customerName;
                        // Disparar evento de cambio para activar validaciones
                        nombreField.dispatchEvent(new Event('input', { bubbles: true }));
                    }
                } else {
                    console.warn('Campo autorizacion_nombre no encontrado');
                }
                
                const dniField = document.getElementById('autorizacion_dni');
                if (dniField) {
                    // Solo actualizar si está vacío o es el valor predeterminado
                    if (!dniField.value.trim() || dniField.value === '[Su DNI]') {
                        console.log(`Actualizando campo DNI con: ${customerDNI}`);
                        dniField.value = customerDNI;
                        // Disparar evento de cambio para activar validaciones
                        dniField.dispatchEvent(new Event('input', { bubbles: true }));
                    }
                } else {
                    console.warn('Campo autorizacion_dni no encontrado');
                }

                // Actualizar texto de la autorización con datos del barco
                const authText = document.getElementById('authorization-text');
                if (authText) {
                    // Usar el selector con la clase específica en lugar del genérico
                    const textoPrincipal = authText.querySelector('.auth-main-text');
                    if (textoPrincipal) {
                        console.log('Actualizando texto principal de autorización');
                        const nuevoTexto = `Por la presente, autorizo a <strong>Tramitfy S.L.</strong> con CIF B55388557 a actuar como mi representante legal para la tramitación y gestión del procedimiento de solicitud de copia de hoja de asiento para el barco <strong>${boatName}</strong> con número de identificación <strong>${boatNIB}</strong> y matrícula <strong>${boatMatricula}</strong> ante las autoridades competentes.`;
                        textoPrincipal.innerHTML = nuevoTexto;
                    } else {
                        console.warn('Texto principal de autorización no encontrado');
                    }
                } else {
                    console.warn('Contenedor authorization-text no encontrado');
                }

                // Asegurarse de que el campo oculto de firma existe
                const signatureField = document.getElementById('signature_data');
                if (!signatureField) {
                    console.warn('Campo signature_data no encontrado, creando uno temporal');
                    // Si no existe, crear uno temporal
                    const tempField = document.createElement('input');
                    tempField.type = 'hidden';
                    tempField.id = 'signature_data';
                    tempField.name = 'signature_data';
                    if (authText) {
                        authText.appendChild(tempField);
                    }
                }
            } catch (error) {
                console.error('Error al generar texto de autorización:', error);
            }
        }

        // Implementación avanzada de firma para móviles
        function initEnhancedSignatureExperience() {
            // Solo para dispositivos móviles
            if (!/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)) {
                return;
            }
            
            if (!enhancedCanvas || !enhancedModal || !zoomSignatureBtn) {
                console.warn('No se encontraron elementos necesarios para la experiencia de firma');
                return;
            }
            
            setupCanvas(enhancedCanvas);
            
            // Configurar eventos de modal
            const closeBtn = enhancedModal.querySelector('.enhanced-close-button');
            const clearBtn = enhancedModal.querySelector('.enhanced-clear-button');
            const acceptBtn = enhancedModal.querySelector('.enhanced-accept-button');
            
            // Acción del botón de abrir el modal
            zoomSignatureBtn.addEventListener('click', function() {
                openEnhancedModal();
            });
            
            // Acción del botón cerrar
            closeBtn.addEventListener('click', function() {
                closeEnhancedModal();
            });
            
            // Acción del botón limpiar
            clearBtn.addEventListener('click', function() {
                if (enhancedSignaturePad) {
                    enhancedSignaturePad.clear();
                    acceptBtn.disabled = true;
                    
                    // Mostrar nuevamente la guía
                    const signatureGuide = enhancedModal.querySelector('.signature-guide');
                    if (signatureGuide) {
                        signatureGuide.style.opacity = '1';
                    }
                }
            });
            
            // Acción del botón aceptar
            acceptBtn.addEventListener('click', function() {
                if (!enhancedSignaturePad || enhancedSignaturePad.isEmpty()) return;
                
                try {
                    // Obtener datos de firma
                    mainSignatureData = enhancedSignaturePad.toDataURL();
                    
                    // Guardar en variable global
                    const signatureField = document.getElementById('signature_data');
                    if (signatureField) signatureField.value = mainSignatureData;
                    
                    // Guardar en localStorage para persistencia
                    try {
                        localStorage.setItem('matriculacion_signature', mainSignatureData);
                        localStorage.setItem('matriculacion_signature_canvas_width', enhancedCanvas.width);
                        localStorage.setItem('matriculacion_signature_canvas_height', enhancedCanvas.height);
                    } catch (e) {}
                    
                    // Actualizar el canvas principal
                    restoreSignature(signatureCanvas, signaturePad);
                    
                    // Actualizar estado
                    updateSignatureStatus();
                    
                    // Cerrar modal
                    closeEnhancedModal();
                    
                    // Asegurar que el formulario vuelva a su posición correcta
                    setTimeout(resetFormPosition, 350);
                    
                    // Feedback visual
                    const padContainer = document.querySelector('.signature-pad-container');
                    if (padContainer) {
                        padContainer.style.transition = 'all 0.3s ease';
                        padContainer.style.boxShadow = '0 0 0 3px rgba(40, 167, 69, 0.3)';
                        padContainer.style.borderColor = 'rgb(40, 167, 69)';
                        
                        setTimeout(() => {
                            padContainer.style.boxShadow = '';
                        }, 1500);
                    }
                } catch (err) {
                    console.error('Error al aceptar la firma:', err);
                }
            });
            
            // Cerrar modal si se hace clic fuera del contenido
            enhancedModal.addEventListener('click', function(e) {
                if (e.target === enhancedModal) {
                    closeEnhancedModal();
                }
            });
            
            // Eventos del canvas
            enhancedCanvas.addEventListener('touchstart', function() {
                const signatureGuide = enhancedModal.querySelector('.signature-guide');
                if (signatureGuide) signatureGuide.style.opacity = '0';
            });
            
            // Gestionar cambios de orientación
            window.addEventListener('orientationchange', function() {
                setTimeout(function() {
                    if (enhancedModal.style.display !== 'none') {
                        resizeEnhancedCanvas();
                        
                        // Restaurar firma si existe
                        if (mainSignatureData) {
                            restoreSignatureToEnhancedCanvas();
                        }
                    }
                }, 300);
            });
            
            // Gestionar cambios de tamaño de ventana
            window.addEventListener('resize', debounce(function() {
                if (enhancedModal.style.display !== 'none') {
                    resizeEnhancedCanvas();
                    
                    // Restaurar firma si existe
                    if (mainSignatureData) {
                        restoreSignatureToEnhancedCanvas();
                    }
                }
            }, 200));
            
            // Gestionar visibilidad de widgets de chat
            handleSignatureModalVisibility();
        }
        
        // Abrir el modal de firma mejorado
        // Variable global para almacenar la posición del scroll
        let scrollPosition = 0;
        
        function openEnhancedModal() {
            if (!enhancedModal) return;
            
            // Mostrar modal sin manejar posición fija en iOS
            enhancedModal.style.display = 'flex';
            
            // Bloquear scroll de forma más simple
            document.body.style.overflow = 'hidden';
            
            // Asegurar que el modal esté correctamente posicionado
            enhancedModal.classList.add('signature-modal-enhanced');
            
            // Inicializar el canvas y SignaturePad
            requestAnimationFrame(() => {
                resizeEnhancedCanvas();
                initializeEnhancedSignaturePad();
                
                // Restaurar firma existente si la hay
                if (mainSignatureData) {
                    setTimeout(() => {
                        restoreSignatureToEnhancedCanvas();
                    }, 200);
                }
            });
        }
        
        // Función para resetear la posición del formulario
        function resetFormPosition() {
            console.log('Configuración de formulario simplificada');
            // No intentar manipular la posición o el scroll
            // Solo recalcular la altura del contenedor si es necesario
            
            // Verificar si estamos en móvil
            const isMobile = window.innerWidth <= 480;
            if (!isMobile) {
                // Solo recalcular en escritorio, ya que en móvil causa problemas de scroll
                recalculateContainerHeight();
            } else {
                console.log('Evitando recálculo de altura en móvil para prevenir saltos de scroll');
            }
        }
        
        // Cerrar el modal mejorado
        function closeEnhancedModal() {
            if (!enhancedModal) return;
            
            // Ocultar modal con animación
            enhancedModal.style.opacity = '0';
            
            setTimeout(() => {
                enhancedModal.style.display = 'none';
                enhancedModal.style.opacity = '1';
                document.body.style.overflow = '';
                
                // Eliminar la clase del modal
                enhancedModal.classList.remove('signature-modal-enhanced');
                
                // Verificar si estamos en móvil antes de forzar recálculo
                const isMobile = window.innerWidth <= 480;
                if (!isMobile) {
                    // Solo forzar recálculo en escritorio
                    setTimeout(resetFormPosition, 100);
                } else {
                    console.log('Evitando reseteo de posición en móvil para prevenir saltos de scroll');
                }
            }, 300);
        }
        
        // Redimensionar el canvas del modal avanzado
        function resizeEnhancedCanvas() {
            if (!enhancedCanvas) return;
            
            try {
                const container = enhancedCanvas.parentElement;
                if (!container) return;
                
                const rect = container.getBoundingClientRect();
                const ratio = window.devicePixelRatio || 1;
                
                // Determinar si estamos en modo horizontal o vertical
                const isLandscape = window.innerWidth > window.innerHeight;
                
                let canvasWidth, canvasHeight;
                
                if (isLandscape) {
                    // En modo horizontal, limitar el ancho para mantener proporción vertical
                    canvasWidth = Math.min(rect.width, rect.height * 0.7);
                    canvasHeight = rect.height;
                } else {
                    // En modo vertical, usar todo el espacio disponible
                    canvasWidth = rect.width;
                    canvasHeight = rect.height;
                }
                
                // Establecer dimensiones físicas considerando DPI
                enhancedCanvas.width = canvasWidth * ratio;
                enhancedCanvas.height = canvasHeight * ratio;
                
                // Establecer dimensiones visuales
                enhancedCanvas.style.width = canvasWidth + 'px';
                enhancedCanvas.style.height = canvasHeight + 'px';
                
                // Centrar canvas horizontalmente si estamos en landscape
                if (isLandscape) {
                    enhancedCanvas.style.marginLeft = 'auto';
                    enhancedCanvas.style.marginRight = 'auto';
                    enhancedCanvas.style.display = 'block';
                } else {
                    enhancedCanvas.style.marginLeft = '';
                    enhancedCanvas.style.marginRight = '';
                }
                
                // Escalar contexto
                const context = enhancedCanvas.getContext('2d');
                if (context) {
                    context.scale(ratio, ratio);
                    context.fillStyle = "#ffffff";
                    context.fillRect(0, 0, canvasWidth, canvasHeight);
                }
            } catch (e) {
                console.error('Error al redimensionar canvas mejorado:', e);
            }
        }
        
        // Inicializar el pad de firma mejorado
        function initializeEnhancedSignaturePad() {
            if (!enhancedCanvas || typeof SignaturePad !== 'function') return;
            
            // Detección de iOS para ajustes específicos
            const isIOS = /iPad|iPhone|iPod/i.test(navigator.userAgent);
            if (isIOS) {
                console.log('Dispositivo iOS detectado en modal mejorado, aplicando ajustes');
                
                // En iOS, asegurar que el modal esté correctamente posicionado
                if (enhancedModal) {
                    enhancedModal.style.position = 'fixed';
                    enhancedModal.style.top = '0';
                    enhancedModal.style.left = '0';
                    enhancedModal.style.right = '0';
                    enhancedModal.style.bottom = '0';
                    enhancedModal.style.width = '100%';
                    enhancedModal.style.height = '100%';
                }
            }
            
            try {
                // Opciones mejoradas para el pad
                const options = {
                    minWidth: 1.5,
                    maxWidth: 3.5,
                    penColor: "rgb(0, 0, 0)",
                    backgroundColor: "rgb(255, 255, 255)",
                    throttle: 16,
                    velocityFilterWeight: 0.7,
                    dotSize: 3.0
                };
                
                // Crear nueva instancia
                enhancedSignaturePad = new SignaturePad(enhancedCanvas, options);
                
                // Evento para actualizar botón cuando se dibuja
                enhancedSignaturePad.addEventListener('endStroke', () => {
                    if (!enhancedSignaturePad.isEmpty()) {
                        const acceptBtn = document.querySelector('.enhanced-accept-button');
                        if (acceptBtn) acceptBtn.disabled = false;
                    }
                });
            } catch (e) {
                console.error('Error al inicializar SignaturePad mejorado:', e);
            }
        }
        
        // Restaurar la firma al canvas mejorado
        function restoreSignatureToEnhancedCanvas() {
            if (!mainSignatureData || !enhancedCanvas || !enhancedSignaturePad) return;
            
            try {
                // Limpiar canvas
                enhancedSignaturePad.clear();
                
                const image = new Image();
                image.onload = () => {
                    try {
                        const ctx = enhancedCanvas.getContext('2d');
                        if (!ctx) return;
                        
                        // Usar las dimensiones actuales del canvas
                        const dpr = window.devicePixelRatio || 1;
                        const canvasWidth = enhancedCanvas.width / dpr;
                        const canvasHeight = enhancedCanvas.height / dpr;
                        
                        ctx.fillStyle = "#ffffff";
                        ctx.fillRect(0, 0, canvasWidth, canvasHeight);
                        
                        // Siempre optimizar para mostrar mejor en vertical
                        // independientemente de la orientación actual del dispositivo
                        const ratio = Math.min(
                            (canvasWidth * 0.85) / image.width,
                            (canvasHeight * 0.65) / image.height
                        );
                        
                        const newWidth = image.width * ratio;
                        const newHeight = image.height * ratio;
                        
                        const x = (canvasWidth - newWidth) / 2;
                        const y = (canvasHeight - newHeight) / 2;
                        
                        // Mejorar calidad de imagen
                        ctx.imageSmoothingEnabled = true;
                        ctx.imageSmoothingQuality = 'high';
                        
                        ctx.drawImage(image, x, y, newWidth, newHeight);
                        
                        enhancedSignaturePad._isEmpty = false;
                        
                        // Habilitar botón de aceptar
                        const acceptBtn = document.querySelector('.enhanced-accept-button');
                        if (acceptBtn) acceptBtn.disabled = false;
                        
                        // Ocultar guía si hay firma
                        const signatureGuide = enhancedModal.querySelector('.signature-guide');
                        if (signatureGuide) {
                            signatureGuide.style.opacity = '0';
                        }
                        
                        // Para iOS, forzar repintado
                        if (/iPhone|iPad|iPod/i.test(navigator.userAgent)) {
                            // Pequeña alteración del canvas para forzar repintar
                            setTimeout(() => {
                                ctx.fillStyle = "rgba(255,255,255,0.01)";
                                ctx.fillRect(0, 0, 1, 1);
                            }, 50);
                        }
                    } catch (err) {
                        console.error('Error al restaurar firma:', err);
                    }
                };
                
                image.src = mainSignatureData;
            } catch (err) {
                console.error('Error general al restaurar firma:', err);
            }
        }
        
        // Validar etapa actual
        // Función para validar email con expresión regular
        function validarEmail(email) {
            const regex = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
            return regex.test(String(email).toLowerCase());
        }
        
        // Función para validar DNI/NIE español
        function validarDNI(dni) {
            const regex = /^[0-9XYZ][0-9]{7}[A-Z]$/i;
            return regex.test(String(dni).toUpperCase());
        }
        
        // Función para validar teléfono español
        function validarTelefono(tel) {
            const regex = /^[6-9][0-9]{8}$/;
            return regex.test(tel);
        }
        
        // Función para validar matrícula de barco española
        function validarMatricula(matricula) {
            // Formato típico: número + lista + número (ej. 7ª BA-2-111-06)
            const regex = /^[0-9]+[ª]?\s*[A-Z]{2}[-\s]*[0-9]+[-\s]*[0-9]+[-\s]*[0-9]+$/i;
            return regex.test(matricula);
        }

        function validateStage(stageIndex) {
            console.log(`Validando etapa ${stageIndex}...`);
            const stage = formStages[stageIndex];
            
            // Validaciones específicas por etapa - versión menos restrictiva
            if (stageIndex === 0) {
                // Sin validaciones para la etapa 0 (datos personales)
                console.log('Omitiendo validaciones para la etapa de datos personales');
                
                // Siempre retornamos true para permitir avanzar
                return true;
            } else if (stageIndex === 1) {
                // Etapa de documentación - sin validación estricta
                console.log('Permitiendo continuar sin validaciones estrictas');
                return true;
            } else if (stageIndex === 2) {
                // Validar solo que haya una firma
                console.log('Validando firma');
                if (!mainSignatureData) {
                    const signatureContainer = document.querySelector('.signature-pad-container');
                    if (signatureContainer) {
                        signatureContainer.style.borderColor = 'rgb(var(--error))';
                    }
                    showError('Por favor, firme el documento para continuar');
                    return false;
                } else {
                    const signatureContainer = document.querySelector('.signature-pad-container');
                    if (signatureContainer) {
                        signatureContainer.style.borderColor = 'rgb(var(--success))';
                    }
                    return true;
                }
            } else if (stageIndex === 3) {
                // Etapa de pago - validación mínima
                // Verificar el checkbox de términos de pago
                const paymentTerms = document.getElementById('payment-terms');
                if (paymentTerms && !paymentTerms.checked) {
                    const container = paymentTerms.closest('.terms-container');
                    if (container) container.style.borderColor = 'rgb(var(--error))';
                    paymentTerms.parentElement.classList.add('error');
                    showError('Debe aceptar los términos y condiciones de pago');
                    return false;
                } else if (paymentTerms) {
                    const container = paymentTerms.closest('.terms-container');
                    if (container) container.style.borderColor = '';
                    paymentTerms.parentElement.classList.remove('error');
                }
                return true;
            }
            
            // Feedback visual para el usuario
            const stageElement = formStages[stageIndex];
            stageElement.classList.add('validation-success');
            setTimeout(() => {
                stageElement.classList.remove('validation-success');
            }, 1000);

            // Por defecto, permitimos continuar
            return true;
        }

        // Mostrar mensaje de error con manejo consistente y mejor posicionamiento
        function showError(message, error = null, timeout = 5000) {
            // Registrar el error en la consola si está disponible
            if (error) {
                console.error('Error específico:', error);
            }
            
            console.log(`Mostrando mensaje de error: ${message}`);
            
            // Evitar mostrar mensajes duplicados
            const existingErrors = document.querySelectorAll('.error-message');
            for (let i = 0; i < existingErrors.length; i++) {
                const errorText = existingErrors[i].querySelector('.error-text');
                if (errorText && errorText.textContent === message) {
                    console.log('Mensaje de error duplicado, no se mostrará nuevamente');
                    return; // No mostrar mensaje duplicado
                }
            }
            
            // Crear el mensaje de error
            const errorDiv = document.createElement('div');
            errorDiv.className = 'error-message';
            // Agregar ID único para poder referenciar este error específico
            const errorId = 'error-' + Date.now() + '-' + Math.floor(Math.random() * 1000);
            errorDiv.id = errorId;
            errorDiv.setAttribute('role', 'alert');
            
            // Agregar botón de cierre
            errorDiv.innerHTML = `
                <div class="error-icon"><i class="fa-solid fa-exclamation-circle"></i></div>
                <div class="error-text">${message}</div>
                <div class="error-close" onclick="this.parentNode.remove()"><i class="fa-solid fa-times"></i></div>
            `;
            
            // Insertar después del título de la sección, no al inicio de la etapa
            const currentStageElement = formStages[currentStage];
            const sectionTitle = currentStageElement.querySelector('.data-section-title') || 
                               currentStageElement.querySelector('.upload-title') ||
                               currentStageElement.querySelector('.signature-title') ||
                               currentStageElement.querySelector('.payment-title');
                               
            if (sectionTitle && sectionTitle.parentNode) {
                // Insertar después del primer título que se encuentre
                sectionTitle.parentNode.insertBefore(errorDiv, sectionTitle.nextSibling);
            } else {
                // Si no se encuentra un título, insertar al principio de la etapa
                currentStageElement.insertBefore(errorDiv, currentStageElement.firstChild);
            }
            
            // Añadir clase para animar la entrada
            setTimeout(() => {
                errorDiv.classList.add('visible');
                // Recalcular altura después de mostrar el error
                recalculateContainerHeight();
            }, 10);
            
            // Eliminar el mensaje después del tiempo especificado
            if (timeout > 0) {
                setTimeout(() => {
                    if (errorDiv.parentNode) {
                        // Animación de salida
                        errorDiv.classList.add('hiding');
                        setTimeout(() => {
                            if (errorDiv.parentNode) {
                                errorDiv.parentNode.removeChild(errorDiv);
                                // Recalcular altura después de eliminar el error
                                recalculateContainerHeight();
                            }
                        }, 300); // Duración de la animación
                    }
                }, timeout);
            }
            
            // Ya no desplazamos automáticamente al error
            // El usuario debe manejar manualmente el desplazamiento
            console.log('Error mostrado sin desplazamiento automático:', errorDiv.id);
            
            return errorId; // Devolver el ID para poder referenciar este error
        }

        // Mostrar mensaje de éxito con mejor posicionamiento
        function showSuccess(message) {
            // Crear el mensaje de éxito
            const successDiv = document.createElement('div');
            successDiv.className = 'success-message';
            successDiv.innerHTML = `
                <div class="success-icon"><i class="fa-solid fa-check-circle"></i></div>
                <div class="success-text">${message}</div>
            `;
            
            // Insertar después del título de la sección, similar a showError
            const currentStageElement = formStages[currentStage];
            const sectionTitle = currentStageElement.querySelector('.data-section-title') || 
                               currentStageElement.querySelector('.upload-title') ||
                               currentStageElement.querySelector('.signature-title') ||
                               currentStageElement.querySelector('.payment-title');
                               
            if (sectionTitle && sectionTitle.parentNode) {
                sectionTitle.parentNode.insertBefore(successDiv, sectionTitle.nextSibling);
            } else {
                currentStageElement.insertBefore(successDiv, currentStageElement.firstChild);
            }
            
            // Asegurarse de que el contenedor se ajuste al nuevo contenido
            recalculateContainerHeight();
            
            // Eliminar el mensaje después de 5 segundos
            setTimeout(() => {
                if (successDiv.parentNode) {
                    successDiv.parentNode.removeChild(successDiv);
                    recalculateContainerHeight();
                }
            }, 5000);
            
            // Ya no desplazamos automáticamente al mensaje de éxito
            console.log('Mensaje de éxito mostrado sin desplazamiento automático');
        }

        // Mostrar spinner de carga
        function showLoading(show) {
            const loadingOverlay = document.getElementById('loading-overlay');
            if (show) {
                loadingOverlay.style.display = 'flex';
                // Ocultar el contenedor del formulario cuando se muestra el loading
                const formContainer = document.getElementById('hoja-asiento-form');
                if (formContainer) {
                    formContainer.style.opacity = '0';
                    formContainer.style.transition = 'opacity 0.3s ease';
                }
            } else {
                loadingOverlay.style.display = 'none';
                // Restaurar la visibilidad del formulario cuando se oculta el loading
                const formContainer = document.getElementById('hoja-asiento-form');
                if (formContainer) {
                    formContainer.style.opacity = '1';
                }
            }
        }

        // Procesar el pago final - versión menos restrictiva
        async function processPayment() {
            // Verificamos solo el checkbox de términos de pago
            const paymentTerms = document.getElementById('payment-terms');
            if (paymentTerms && !paymentTerms.checked) {
                showError('Por favor, acepte la política de uso y los términos de pago');
                return false;
            }

            showLoading(true);

            try {
                const { error } = await stripe.confirmPayment({
                    elements,
                    confirmParams: {
                        return_url: window.location.href,
                        payment_method_data: {
                            billing_details: {
                                name: document.getElementById('customer_name').value,
                                email: document.getElementById('customer_email').value,
                                phone: document.getElementById('customer_phone').value
                            }
                        }
                    },
                    redirect: 'if_required'
                });

                if (error) {
                    throw new Error(error.message);
                } else {
                    // Procesar el envío final del formulario
                    submitForm();
                }
            } catch (error) {
                showLoading(false);
                showError('Error al procesar el pago: ' + error.message);
            }
        }

        // Enviar formulario final
        // Enviar formulario final - versión mejorada
        async function submitForm() {
            try {
                const formData = new FormData(document.getElementById('hoja-asiento-form'));
                
                // Añadir datos de acción y firma
                formData.append('action', 'submit_form_hoja_asiento');
                
                // Usar el dato de firma correcto, sea mainSignatureData o signature_data
                const signatureData = mainSignatureData || document.getElementById('signature_data')?.value;
                formData.append('signature', signatureData);
                formData.append('coupon_used', document.getElementById('coupon_code').value.trim());
                formData.append('payment_amount', currentPrice.toFixed(2));
                
                // Añadir datos de autorización específicos
                const autorizacionNombre = document.getElementById('autorizacion_nombre');
                const autorizacionDNI = document.getElementById('autorizacion_dni');
                
                if (autorizacionNombre && autorizacionNombre.value) {
                    formData.append('autorizacion_nombre', autorizacionNombre.value.trim());
                }
                
                if (autorizacionDNI && autorizacionDNI.value) {
                    formData.append('autorizacion_dni', autorizacionDNI.value.trim());
                }
                
                // Añadir tipo de representante
                formData.append('tipo_representante', 'representante');

                const response = await fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                document.getElementById('loading-overlay').style.display = 'none';
                
                if (result.success) {
                    // Función de redirección inmediata
                    const redirectNow = function() {
                        window.location.href = '<?php echo site_url("/pago-realizado-con-exito"); ?>';
                    };
                    
                    // Ocultar absolutamente todo en la página
                    document.body.innerHTML = '';
                    document.body.style.backgroundColor = '#ffffff';
                    
                    // Mostrar un spinner minimalista mientras se redirige
                    const spinner = document.createElement('div');
                    spinner.style.position = 'fixed';
                    spinner.style.top = '50%';
                    spinner.style.left = '50%';
                    spinner.style.transform = 'translate(-50%, -50%)';
                    spinner.style.width = '50px';
                    spinner.style.height = '50px';
                    spinner.style.border = '5px solid #f3f3f3';
                    spinner.style.borderTop = '5px solid #016d86';
                    spinner.style.borderRadius = '50%';
                    spinner.style.animation = 'spin 1s linear infinite';
                    
                    // Añadir animación al head
                    const style = document.createElement('style');
                    style.textContent = '@keyframes spin { 0% { transform: translate(-50%, -50%) rotate(0deg); } 100% { transform: translate(-50%, -50%) rotate(360deg); } }';
                    document.head.appendChild(style);
                    
                    document.body.appendChild(spinner);
                    
                    // Redirigir inmediatamente
                    redirectNow();
                    
                    // Backup: si por alguna razón la redirección no ocurre, forzarla después de 100ms
                    setTimeout(redirectNow, 100);
                } else {
                    throw new Error(result.message || 'Error al procesar el formulario');
                }
            } catch (error) {
                document.getElementById('loading-overlay').style.display = 'none';
                showError('Error al enviar el formulario: ' + error.message, error);
            }
        }

        // Validar y aplicar cupón
        async function validateCoupon(couponCode) {
            const couponInput = document.getElementById('coupon_code');
            const couponMessage = document.getElementById('coupon-message');
            const applyButton = document.getElementById('apply-coupon');
            
            if (!couponCode.trim()) {
                return;
            }
            
            couponInput.disabled = true;
            applyButton.disabled = true;
            couponMessage.className = 'coupon-message';
            couponMessage.textContent = 'Verificando cupón...';

            try {
                const response = await fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=validate_coupon_code_hoja_asiento&coupon=${encodeURIComponent(couponCode)}`
                });
                
                const result = await response.json();
                
                couponInput.disabled = false;
                applyButton.disabled = false;
                
                if (result.success) {
                    // Aplicar descuento
                    discountApplied = result.data.discount_percent;
                    discountAmount = parseFloat((18.50 * discountApplied / 100).toFixed(2));
                    const newBaseFee = parseFloat((18.50 - discountAmount).toFixed(2));
                    const newIVA = parseFloat((newBaseFee * 0.21).toFixed(2));
                    currentPrice = parseFloat((7.61 + newBaseFee + newIVA).toFixed(2));
                    
                    // Actualizar UI
                    couponMessage.className = 'coupon-message success';
                    couponMessage.textContent = `Cupón aplicado: ${discountApplied}% de descuento`;
                    
                    // Mostrar línea de descuento
                    document.querySelector('.price-discount').style.display = 'flex';
                    document.querySelector('.price-discount span').textContent = `-${discountAmount.toFixed(2)} €`;
                    document.getElementById('final-amount').textContent = `${currentPrice.toFixed(2)} €`;
                    
                    // Reinicializar Stripe con el nuevo precio
                    initializeStripe(currentPrice);
                } else {
                    couponMessage.className = 'coupon-message error';
                    couponMessage.textContent = 'Cupón inválido o expirado';
                }
            } catch (error) {
                couponInput.disabled = false;
                applyButton.disabled = false;
                couponMessage.className = 'coupon-message error';
                couponMessage.textContent = 'Error al verificar el cupón';
            }
        }

        // Manejo del área de carga de documentos
        function setupFileUploadArea() {
            const uploadInput = document.getElementById('upload-dni-propietario');
            const preview = document.getElementById('dni-preview');
            const previewName = preview.querySelector('.upload-preview-name');
            const removeButton = preview.querySelector('.upload-preview-remove');

            uploadInput.addEventListener('change', function() {
                if (this.files.length > 0) {
                    const file = this.files[0];
                    const fileExt = file.name.split('.').pop().toLowerCase();
                    
                    // Añadir icono según tipo de archivo
                    let fileIcon = '';
                    if (['jpg', 'jpeg', 'png'].includes(fileExt)) {
                        fileIcon = '<i class="fa-solid fa-file-image"></i> ';
                    } else if (fileExt === 'pdf') {
                        fileIcon = '<i class="fa-solid fa-file-pdf"></i> ';
                    } else {
                        fileIcon = '<i class="fa-solid fa-file"></i> ';
                    }
                    
                    // Formatear tamaño de archivo
                    const fileSize = formatFileSize(file.size);
                    
                    // Mostrar nombre con icono y tamaño
                    previewName.innerHTML = `${fileIcon}<span class="file-name">${file.name}</span> <span class="file-size">(${fileSize})</span>`;
                    
                    preview.classList.add('active');
                    // Quitar estilo de error si estaba presente
                    document.getElementById('dni-upload-area').style.borderColor = 'rgb(var(--success))';
                    
                    // Si es imagen, crear miniatura
                    if (['jpg', 'jpeg', 'png'].includes(fileExt)) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            // Crear o actualizar la miniatura
                            let thumbnail = preview.querySelector('.file-thumbnail');
                            if (!thumbnail) {
                                thumbnail = document.createElement('div');
                                thumbnail.className = 'file-thumbnail';
                                preview.insertBefore(thumbnail, previewName);
                            }
                            thumbnail.innerHTML = `<img src="${e.target.result}" alt="Vista previa">`;
                        }
                        reader.readAsDataURL(file);
                    }
                } else {
                    preview.classList.remove('active');
                    // Eliminar miniatura si existe
                    const thumbnail = preview.querySelector('.file-thumbnail');
                    if (thumbnail) thumbnail.remove();
                }
            });

            removeButton.addEventListener('click', function() {
                uploadInput.value = '';
                preview.classList.remove('active');
                // Eliminar miniatura si existe
                const thumbnail = preview.querySelector('.file-thumbnail');
                if (thumbnail) thumbnail.remove();
                // Restaurar estilo del área de carga
                document.getElementById('dni-upload-area').style.borderColor = 'rgb(var(--neutral-300))';
            });
            
            // Función para formatear el tamaño del archivo
            function formatFileSize(bytes) {
                if (bytes < 1024) return bytes + ' bytes';
                else if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
                else return (bytes / 1048576).toFixed(1) + ' MB';
            }

            // Manejar arrastrar y soltar
            const dropArea = document.getElementById('dni-upload-area');
            
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                dropArea.addEventListener(eventName, preventDefaults, false);
            });
            
            function preventDefaults(e) {
                e.preventDefault();
                e.stopPropagation();
            }
            
            ['dragenter', 'dragover'].forEach(eventName => {
                dropArea.addEventListener(eventName, highlight, false);
            });
            
            ['dragleave', 'drop'].forEach(eventName => {
                dropArea.addEventListener(eventName, unhighlight, false);
            });
            
            function highlight() {
                dropArea.style.borderColor = 'rgb(var(--primary))';
                dropArea.style.backgroundColor = 'rgba(var(--primary-bg))';
            }
            
            function unhighlight() {
                dropArea.style.borderColor = 'rgb(var(--neutral-300))';
                dropArea.style.backgroundColor = '';
            }
            
            dropArea.addEventListener('drop', handleDrop, false);
            
            function handleDrop(e) {
                if (e.dataTransfer.files.length) {
                    uploadInput.files = e.dataTransfer.files;
                    uploadInput.dispatchEvent(new Event('change'));
                }
            }
        }

        // Escuchar cambios en campos para activar solo el refuerzo positivo
        function setupFieldValidation() {
            
            // Incluir tanto los campos normales como el campo de teléfono personalizado
            const inputFields = document.querySelectorAll('.field-input, .custom-phone-input');
            
            inputFields.forEach(input => {
                input.addEventListener('blur', function() {
                    // Para la primera etapa (datos personales), solo mostramos refuerzo positivo
                    if (this.closest('.form-stage[data-stage="0"]')) {
                        // Eliminar cualquier tooltip de error existente
                        const errorTooltip = this.parentElement.querySelector('.error-tooltip');
                        if (errorTooltip) {
                            errorTooltip.remove();
                        }
                        
                        // Eliminar clases de error
                        this.parentElement.classList.remove('error');
                        this.setAttribute('aria-invalid', 'false');
                        
                        // Solo aplicar clase 'completed' si el campo tiene contenido
                        if (this.value.trim()) {
                            this.parentElement.classList.add('completed');
                        } else {
                            this.parentElement.classList.remove('completed');
                        }
                    } else {
                        // Para el resto de etapas, mantener la validación original
                        const fieldName = this.name || this.id;
                        const fieldLabel = this.dataset.label || this.placeholder || fieldName;
                        const errorTooltip = this.parentElement.querySelector('.error-tooltip');
                        
                        // Eliminar tooltip existente si hay uno
                        if (errorTooltip) {
                            errorTooltip.remove();
                        }
                        
                        // Validación básica por tipo de campo
                        if (this.required && !this.value.trim()) {
                            // Campo requerido vacío
                            this.parentElement.classList.add('error');
                            this.parentElement.classList.remove('completed');
                            
                            // Crear tooltip de error
                            const tooltip = document.createElement('div');
                            tooltip.className = 'error-tooltip';
                            tooltip.textContent = `Por favor, complete este campo`;
                            this.parentElement.appendChild(tooltip);
                        } else {
                            // Validaciones específicas por tipo
                            let isValid = true;
                            let errorMsg = '';
                            
                            if (this.value.trim()) {
                                // Email
                                if (this.type === 'email' && !validarEmail(this.value)) {
                                    isValid = false;
                                    errorMsg = 'El correo electrónico no es válido';
                                }
                                // Teléfono
                                else if (this.id === 'customer_phone' && !validarTelefono(this.value)) {
                                    isValid = false;
                                    errorMsg = 'El teléfono debe tener 9 dígitos y empezar por 6, 7, 8 o 9';
                                }
                                // DNI
                                else if ((this.id === 'customer_dni' || this.id === 'autorizacion_dni') && !validarDNI(this.value)) {
                                    isValid = false;
                                    errorMsg = 'El DNI/NIE no tiene un formato válido';
                                }
                                // Matrícula
                                else if (this.id === 'boat_matricula' && !validarMatricula(this.value)) {
                                    isValid = false;
                                    errorMsg = 'La matrícula no tiene un formato válido';
                                }
                            }
                            
                            if (!isValid) {
                                this.parentElement.classList.add('error');
                                this.parentElement.classList.remove('completed');
                                this.setAttribute('aria-invalid', 'true');
                                
                                // Mostrar tooltip con el error específico
                                const tooltip = document.createElement('div');
                                tooltip.className = 'error-tooltip';
                                tooltip.textContent = errorMsg;
                                this.parentElement.appendChild(tooltip);
                            } else {
                                this.parentElement.classList.remove('error');
                                this.setAttribute('aria-invalid', 'false');
                                if (this.value.trim()) {
                                    this.parentElement.classList.add('completed');
                                }
                            }
                        }
                    }
                });

                // Mejorar la experiencia al ingresar datos
                input.addEventListener('input', function() {
                    // Para la primera etapa, solo aplicamos refuerzo positivo
                    if (this.closest('.form-stage[data-stage="0"]')) {
                        // Eliminar clases de error
                        this.parentElement.classList.remove('error');
                        
                        // Quitar tooltip de error si existe
                        const errorTooltip = this.parentElement.querySelector('.error-tooltip');
                        if (errorTooltip) {
                            errorTooltip.remove();
                        }
                        
                        // Aplicar clase 'completed' si tiene contenido
                        if (this.value.trim()) {
                            this.parentElement.classList.add('completed');
                        } else {
                            this.parentElement.classList.remove('completed');
                        }
                    } else {
                        // Para otras etapas, comportamiento original
                        // Eliminar marca de error mientras escribe
                        this.parentElement.classList.remove('error');
                        
                        // Quitar tooltip de error si existe
                        const errorTooltip = this.parentElement.querySelector('.error-tooltip');
                        if (errorTooltip) {
                            errorTooltip.style.display = 'none';
                        }
                    }
                });
                
                // No aplicar auto-continuación en la primera etapa
                input.addEventListener('change', debounce(function() {
                    // Excluimos la validación y auto-continuación en la etapa 0
                    if (currentStage === 0) {
                        // Solo aplicamos la clase completed si tiene contenido
                        if (this.value.trim()) {
                            this.parentElement.classList.add('completed');
                        } else {
                            this.parentElement.classList.remove('completed');
                        }
                    }
                }, 500));
            });
            
            // Agregar validación también a los campos de selección
            const selectFields = document.querySelectorAll('select.field-input');
            selectFields.forEach(select => {
                select.addEventListener('change', function() {
                    if (this.required && (!this.value || this.value === '')) {
                        this.parentElement.classList.add('error');
                        this.parentElement.classList.remove('completed');
                    } else {
                        this.parentElement.classList.remove('error');
                        this.parentElement.classList.add('completed');
                    }
                });
            });
        }

        // Inicializar todos los eventos de navegación
        function setupNavigation() {
            // Event listeners para la pre-página
            gotoRequirementsBtn.addEventListener('click', function(e) {
                e.preventDefault();
                currentPage = 1; // Requisitos
                updateView();
                // No desplazamos automáticamente
            });

            gotoPasosBtn.addEventListener('click', function(e) {
                e.preventDefault();
                currentPage = 2; // Pasos
                updateView();
                // No desplazamos automáticamente
            });

            startProcessBtn.addEventListener('click', function(e) {
                e.preventDefault();
                currentPage = 3; // Iniciar el formulario interactivo
                updateView();
                
                // Mostrar el menú superior solo en el formulario interactivo
                toggleHeaderVisibility(true);
                
                // Actualizar el progreso en el menú superior
                updateHeaderProgress();
            });

            // Event listeners para la navegación entre etapas del formulario - más permisivos
            document.getElementById('next-stage-0').addEventListener('click', function() {
                // Aplicamos solo refuerzo positivo a los campos con valor
                const fields = document.querySelectorAll('.form-stage[data-stage="0"] input');
                fields.forEach(field => {
                    if (field.value.trim()) {
                        field.parentElement.classList.add('completed');
                    } else {
                        field.parentElement.classList.remove('completed');
                    }
                    // Eliminar cualquier error o tooltip
                    field.parentElement.classList.remove('error');
                    const errorTooltip = field.parentElement.querySelector('.error-tooltip');
                    if (errorTooltip) {
                        errorTooltip.remove();
                    }
                });
                
                // Continuar sin validación
                showStage(1);
            });

            document.getElementById('next-stage-1').addEventListener('click', function() {
                // Permitimos continuar sin validación estricta
                showStage(2);
            });

            document.getElementById('next-stage-2').addEventListener('click', function() {
                // Solo validamos que haya una firma
                if (validateStage(2)) {
                    showStage(3);
                }
            });

            document.getElementById('prev-stage-1').addEventListener('click', function() {
                showStage(0);
            });

            document.getElementById('prev-stage-2').addEventListener('click', function() {
                showStage(1);
            });

            document.getElementById('prev-stage-3').addEventListener('click', function() {
                showStage(2);
            });

            // Botón de pago
            // Botón para mostrar el modal de pago
            document.getElementById('submit-payment').addEventListener('click', function() {
                // Verificar que los datos personales estén cumplimentados
                const camposPersonales = [
                    'customer_name',
                    'customer_dni',
                    'customer_email',
                    'customer_phone'
                ];
                
                let datosIncompletos = false;
                let campoVacio = '';
                
                for (const campo of camposPersonales) {
                    const elemento = document.getElementById(campo);
                    if (!elemento || !elemento.value || elemento.value.trim() === '') {
                        datosIncompletos = true;
                        campoVacio = campo;
                        break;
                    }
                }
                
                if (datosIncompletos) {
                    showError('Por favor, complete todos sus datos personales en la primera página antes de realizar el pago');
                    // Animación para resaltar que faltan datos
                    document.querySelector('.progress-step[data-step="0"]').classList.add('pulse-animation');
                    setTimeout(() => {
                        document.querySelector('.progress-step[data-step="0"]').classList.remove('pulse-animation');
                    }, 2000);
                    // Navegar a la primera etapa donde están los datos personales
                    showStage(0);
                    return;
                }
                
                // Verificar solo los términos de pago
                const paymentTerms = document.getElementById('payment-terms');
                
                if (paymentTerms && !paymentTerms.checked) {
                    showError('Por favor, acepte la política de uso y los términos de pago');
                    paymentTerms.parentElement.classList.add('shake-animation');
                    setTimeout(() => {
                        paymentTerms.parentElement.classList.remove('shake-animation');
                    }, 800);
                    return;
                }
                
                // Mostrar el modal
                document.getElementById('payment-modal').classList.add('show');
                
                // Inicializar Stripe después de un pequeño retraso para que la animación del modal termine
                setTimeout(() => {
                    try {
                        initializeStripe(currentPrice);
                    } catch (error) {
                        console.error("Error al inicializar Stripe:", error);
                        document.getElementById('payment-message').textContent = 'Error al inicializar el sistema de pago: ' + error.message;
                        document.getElementById('payment-message').className = 'error';
                        document.getElementById('stripe-loading').style.display = 'none';
                    }
                }, 500);
            });
            
            // Eventos para cerrar modal
            document.querySelector('.close-modal').addEventListener('click', function() {
                document.getElementById('payment-modal').classList.remove('show');
            });
            
            document.getElementById('payment-modal').addEventListener('click', function(event) {
                if (event.target === this) {
                    this.classList.remove('show');
                }
            });
            
            // Botón de cupón
            document.getElementById('apply-coupon').addEventListener('click', function() {
                const couponCode = document.getElementById('coupon_code').value.trim();
                validateCoupon(couponCode);
            });
        }
        
        // Función mejorada de redimensionamiento de canvas
        function resizeCanvas(canvas) {
            if (!canvas) return;
            
            try {
                const container = canvas.parentElement;
                const ratio = window.devicePixelRatio || 1;
                const width = container.clientWidth;
                
                // Altura adaptativa según el tamaño de pantalla
                const height = window.innerWidth <= 480 ? 120 : 
                              window.innerWidth <= 768 ? 150 : 180;
                
                // Ajustar dimensiones físicas del canvas
                canvas.width = width * ratio;
                canvas.height = height * ratio;
                
                // Ajustar dimensiones visuales del canvas
                canvas.style.width = width + 'px';
                canvas.style.height = height + 'px';
                
                // Escalar contexto para mantener calidad
                const ctx = canvas.getContext('2d');
                ctx.scale(ratio, ratio);
                
                // Mejoras específicas para iOS
                if (/iPhone|iPad|iPod/i.test(navigator.userAgent)) {
                    canvas.style.transform = 'translateZ(0)';
                    canvas.style.webkitTransform = 'translateZ(0)';
                    setTimeout(() => {
                        ctx.fillStyle = "rgba(255,255,255,0.01)";
                        ctx.fillRect(0, 0, 1, 1);
                    }, 50);
                }
            } catch (e) {
                console.error('Error en resizeCanvas:', e);
            }
        }
        
        // Inicialización principal
        // Configurar observador para ajuste automático de altura
        function setupFormResizeObserver() {
            if (typeof ResizeObserver !== 'function') return;
            
            const container = document.querySelector('.interactive-form-container');
            if (!container) return;
            
            // Variable para implementar un sistema de throttling
            let resizeTimeout = null;
            
            const resizeObserver = new ResizeObserver(entries => {
                const activeStage = document.querySelector('.form-stage.active');
                if (activeStage) {
                    // Implementar throttling para reducir frecuencia de recálculos
                    if (!resizeTimeout) {
                        resizeTimeout = setTimeout(() => {
                            recalculateContainerHeight();
                            resizeTimeout = null;
                        }, 200); // Esperar 200ms entre recálculos
                    }
                }
            });
            
            // Observar cada etapa del formulario
            document.querySelectorAll('.form-stage').forEach(stage => {
                resizeObserver.observe(stage);
            });
            
            console.log('Observador de tamaño configurado con throttling');
        }
        
        function initializeForm() {
            console.log('Inicializando formulario...');
            
            // Event listeners para la navegación entre etapas
            setupNavigation();
            
            // Inicializar área de carga de documentos
            setupFileUploadArea();
            
            // Configurar validación de campos
            setupFieldValidation();
            
            // Configurar observador para ajuste automático de altura
            setupFormResizeObserver();
            
            // Configurar manipulación de widgets para firmas
            handleSignatureModalVisibility();
            
            // Configurar manipulación de widgets para pagos
            enhancePaymentModalWidgetHandling();
            
            // Actualizar vista inicial
            updateView();
            
            // Añadir redimensionamiento de canvas en cambios de ventana
            window.addEventListener('resize', debounce(function() {
                console.log('Redimensionando canvas en respuesta a cambio de ventana');
                const signatureCanvas = document.getElementById('signature-pad');
                if (signatureCanvas) {
                    resizeCanvas(signatureCanvas);
                    
                    // Si ya existe una firma, restaurarla después del redimensionamiento
                    if (mainSignatureData && signaturePad) {
                        setTimeout(() => {
                            restoreSignature(signatureCanvas, signaturePad);
                        }, 200);
                    }
                }
                
                // También redimensionar el canvas mejorado si está visible
                if (enhancedCanvas && enhancedModal.style.display !== 'none') {
                    resizeEnhancedCanvas();
                    if (mainSignatureData && enhancedSignaturePad) {
                        restoreSignatureToEnhancedCanvas();
                    }
                }
            }, 300));
            
            // Asegurar que los cambios de etapa funcionan correctamente
            document.querySelectorAll('[id^="next-stage-"]').forEach(button => {
                button.addEventListener('click', function() {
                    const stageIndex = parseInt(this.id.replace('next-stage-', ''));
                    
                    // Permitimos avanzar de forma menos restrictiva
                    const canContinue = (stageIndex === 2) ? validateStage(stageIndex) : true;
                    
                    if (canContinue) {
                        console.log(`Avanzando a la siguiente etapa desde ${stageIndex}`);
                        showStage(stageIndex + 1);
                        
                        // Si la siguiente etapa es de firma, inicializar componentes de firma
                        if (stageIndex + 1 === 2) {
                            setTimeout(() => {
                                console.log('Inicializando componentes de firma');
                                generateAuthorizationText();
                                detectDevice();
                                
                                // Reinicializar canvas si es necesario
                                if (signatureCanvas) {
                                    resizeCanvas(signatureCanvas);
                                    if (!signaturePad) {
                                        initSignaturePad();
                                    } else if (mainSignatureData) {
                                        restoreSignature(signatureCanvas, signaturePad);
                                    }
                                }
                            }, 300);
                        }
                    } else if (stageIndex === 2) {
                        console.warn(`Se requiere firma para continuar`);
                    }
                });
            });
            
            // Inicializar el menú de navegación del proceso
            function initProcessNavigation() {
                const processSteps = document.querySelectorAll('.process-step');
                
                // Función para actualizar el estado de los pasos
                function updateProcessSteps() {
                    processSteps.forEach((step, index) => {
                        // Reiniciar clases
                        step.classList.remove('active', 'completed', 'clickable');
                        
                        // Aplicar clases según el estado actual
                        if (index < currentPage) {
                            step.classList.add('completed', 'clickable');
                        } else if (index === currentPage) {
                            step.classList.add('active');
                        }
                        
                        // Actualizar la barra de progreso interna
                        const progressBar = step.querySelector('.step-progress');
                        if (progressBar) {
                            if (index < currentPage) {
                                progressBar.style.width = '100%';
                            } else if (index === currentPage) {
                                // Si estamos en el formulario interactivo, mostrar progreso basado en etapa actual
                                if (currentPage === 3) {
                                    const formProgress = (currentStage / (formStages.length - 1)) * 100;
                                    progressBar.style.width = `${formProgress}%`;
                                } else {
                                    progressBar.style.width = '50%';
                                }
                            } else {
                                progressBar.style.width = '0%';
                            }
                        }
                    });
                }
                
                // Actualizar inicialmente
                updateProcessSteps();
                
                // Añadir evento clic para navegación
                processSteps.forEach((step) => {
                    step.addEventListener('click', function() {
                        const targetPage = parseInt(this.getAttribute('data-page'));
                        
                        // Solo permitir navegar a páginas completadas o la actual
                        if (targetPage <= currentPage) {
                            currentPage = targetPage;
                            updateView();
                            updateProcessSteps();
                            
                            // Ya no desplazamos automáticamente
                            console.log('Navegación entre páginas sin desplazamiento automático');
                        }
                    });
                });
                
                // Sobrescribir funciones para actualizar el menú
                const originalUpdateView = updateView;
                window.updateView = function() {
                    originalUpdateView();
                    updateProcessSteps();
                };
                
                const originalShowStage = showStage;
                window.showStage = function(stageIndex) {
                    originalShowStage(stageIndex);
                    updateProcessSteps();
                };
            }

            // Llamar a la función de inicialización
            initProcessNavigation();
            
            console.log('Inicialización de formulario completada');
        }
        
        // Añadir CSS para mejorar las transiciones
        function injectTransitionStyles() {
            // Crear un estilo para modificar algunas propiedades CSS problemáticas
            const style = document.createElement('style');
            style.id = 'form-transition-styles';
            style.textContent = `
                .interactive-form-container {
                    transition: height 0.6s ease;
                    min-height: 580px; /* Altura mínima base */
                    max-width: 100%;
                    width: 100%;
                    padding: 0;
                    box-sizing: border-box;
                    margin: 40px auto;
                }
                
                .form-stage {
                    height: auto;
                    min-height: unset;
                    transition: transform 0.6s ease, opacity 0.6s ease;
                    will-change: transform, opacity;
                }
                
                .form-stage.active {
                    position: relative;
                    transform: translateX(0);
                    opacity: 1;
                    z-index: 5;
                }
                
                /* Evitar pantallas en blanco durante transiciones */
                .interactive-form-container:before {
                    content: '';
                    position: absolute;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background-color: rgba(var(--neutral-50));
                    opacity: 0;
                    transition: opacity 0.3s ease;
                    z-index: 2;
                    pointer-events: none;
                }
                
                .interactive-form-container.transitioning:before {
                    opacity: 1;
                }
                
                /* Estilos responsivos para los campos del formulario */
                @media (max-width: 768px) {
                    .fields-grid {
                        grid-template-columns: 1fr 1fr;
                        gap: 15px;
                    }
                    
                    .data-section {
                        padding: 15px;
                    }
                    
                    .stage-navigation-wrapper {
                        flex-direction: row;
                        justify-content: space-between;
                    }
                    
                    /* Área de firma responsiva */
                    .signature-pad-container {
                        width: 100%;
                        max-width: 100%;
                    }
                    
                    .signature-actions {
                        flex-direction: row;
                        justify-content: space-between;
                    }
                    
                    /* Área de pago responsiva */
                    .price-details {
                        padding: 15px;
                    }
                    
                    /* Modal de firma mejorado */
                    .signature-modal-enhanced .enhanced-modal-content {
                        width: 90%;
                        max-width: 600px;
                    }
                }
                
                @media (max-width: 576px) {
                    .fields-grid {
                        grid-template-columns: 1fr;
                        gap: 15px;
                    }
                    
                    .field-group.full-width {
                        grid-column: 1;
                    }
                    
                    .data-section {
                        padding: 25px; /* Mismo padding que en versión desktop */
                    }
                    
                    .form-stage {
                        padding: 30px; /* Mismo padding que en versión desktop */
                    }
                    
                    .stage-navigation-wrapper {
                        flex-direction: row;
                        justify-content: space-between;
                        width: 100%;
                    }
                    
                    .stage-navigation button {
                        padding: 0 1.5rem; /* Mismo padding que en desktop */
                        font-size: 1rem; /* Mismo tamaño que en desktop */
                    }
                    
                    /* Área de firma en móvil - ajustado para mantener consistencia */
                    .signature-actions {
                        flex-direction: row; /* Misma dirección que en desktop */
                        gap: 10px;
                        justify-content: space-between; /* Mismo layout que en desktop */
                    }
                    
                    .signature-actions button {
                        width: auto; /* Mismo tamaño que en desktop */
                    }
                    
                    /* Área de pago en móvil */
                    .coupon-field {
                        flex-direction: row; /* Misma dirección que en desktop */
                    }
                    
                    .coupon-input {
                        width: auto; /* Mismo tamaño que en desktop */
                        margin-bottom: 0; /* Eliminar margen adicional */
                        margin-right: 10px; /* Agregar margen lateral */
                    }
                    
                    .coupon-btn {
                        width: auto; /* Mismo tamaño que en desktop */
                    }
                    
                    /* Modal responsivo */
                    .enhanced-modal-content {
                        width: 90%; /* Más similar a desktop */
                        max-width: 600px; /* Mismo valor que en desktop */
                        padding: 30px; /* Mismo padding que en desktop */
                    }
                    
                    .enhanced-button-container {
                        flex-direction: row; /* Misma dirección que en desktop */
                    }
                    
                    .enhanced-button-container button {
                        width: auto; /* Mismo tamaño que en desktop */
                        margin: 0 5px; /* Mismo margen que en desktop */
                    }
                }
            `;
            document.head.appendChild(style);
            console.log('Estilos de transición inyectados');
        }
        
        // Ejecutar inicialización cuando el DOM esté completamente cargado
        initializeForm();
        
        // Inyectar estilos para transiciones
        injectTransitionStyles();
        
        // Manejador para verificar estado inicial al cargar la página
        window.addEventListener('load', function() {
            // Verificar si estamos en la etapa de pago al cargar
            const paymentStage = document.querySelector('.form-stage[data-stage="3"]');
            if (paymentStage && paymentStage.classList.contains('active')) {
                // Ocultar widgets
                if (typeof hideWhatsAppWidgets === 'function') {
                    hideWhatsAppWidgets();
                }
            }
            
            // También verificar si el modal de pago está visible al cargar
            const paymentModal = document.getElementById('payment-modal');
            if (paymentModal && paymentModal.classList.contains('show')) {
                if (typeof hideWhatsAppWidgets === 'function') {
                    hideWhatsAppWidgets();
                }
            }
        });
        
        // Inicialización del menú de navegación circular para etapas del formulario
function initProcessNavigation() {
    const processSteps = document.querySelectorAll('.process-step');
    const processNavigation = document.querySelector('.process-navigation');
    
    // Función para actualizar la visibilidad del menú circular
    function updateProcessNavigationVisibility() {
        console.log('Actualizando visibilidad del menú de navegación, página actual:', currentPage);
        if (processNavigation) {
            // Solo mostrar el menú cuando estamos en la página del formulario interactivo
            if (currentPage === 3) {
                processNavigation.style.display = 'block';
                console.log('Menú de navegación mostrado');
            } else {
                processNavigation.style.display = 'none';
                console.log('Menú de navegación oculto');
            }
        } else {
            console.warn('Elemento de navegación no encontrado');
        }
    }
    
    // Función para actualizar el estado de los pasos del formulario
    function updateProcessStepsProgress() {
        // Siempre actualizar el progreso cuando estemos en el formulario
        if (!processNavigation) {
            console.warn('Elemento de navegación no encontrado en updateProcessStepsProgress');
            return;
        }
        
        // Calcular el progreso total basado en la etapa actual y posible progreso dentro de la etapa
        const totalSteps = 4; // Total de pasos (0-3)
        
        // Porcentaje base según la etapa (cada etapa completa = 25%)
        let progressPercentage = (currentStage / (totalSteps - 1)) * 100;
        
        // Para calcular el progreso adicional dentro de la etapa actual
        let intraStageProgress = 0;
        
        // Calcular progreso adicional dentro de la etapa actual
        if (currentStage === 0) {
            // Etapa de datos personales
            const totalFields = document.querySelectorAll('.form-stage[data-stage="0"] .field-input').length;
            const completedFields = document.querySelectorAll('.form-stage[data-stage="0"] .field-input.valid').length;
            intraStageProgress = totalFields > 0 ? (completedFields / totalFields) * 25 : 0;
        } else if (currentStage === 1) {
            // Etapa de documentación
            const isDocumentUploaded = document.getElementById('document_upload') && 
                                     document.getElementById('document_upload').files && 
                                     document.getElementById('document_upload').files.length > 0;
            intraStageProgress = isDocumentUploaded ? 20 : 0;
        } else if (currentStage === 2) {
            // Etapa de firma
            const signatureData = document.getElementById('signature_data');
            intraStageProgress = signatureData && signatureData.value ? 20 : 0;
        } else if (currentStage === 3) {
            // Etapa de pago
            intraStageProgress = 10; // Un poco de progreso por defecto
        }
        
        // Añadir progreso dentro de la etapa actual
        progressPercentage += intraStageProgress;
        
        // Actualizar la barra de progreso principal en el contenedor de pasos
        const processStepsContainer = document.querySelector('.process-steps');
        if (processStepsContainer) {
            // Actualizar anchura de la barra de progreso
            processStepsContainer.style.setProperty('--progress-width', `${progressPercentage}%`);
            
            // La pseudo-clase ::after no se puede manipular directamente con JS
            // Usamos una propiedad CSS personalizada como alternativa
            if (!processStepsContainer.style.cssText.includes('--progress-width')) {
                // Añadimos un estilo inline para la barra de progreso
                const existingStyle = processStepsContainer.getAttribute('style') || '';
                processStepsContainer.setAttribute('style', existingStyle + ` --progress-width: ${progressPercentage}%;`);
                
                // Y añadimos un estilo inline para mostrar la barra
                const afterStyleElem = document.createElement('style');
                afterStyleElem.textContent = '.process-steps::after { width: var(--progress-width, 0%); }';
                document.head.appendChild(afterStyleElem);
            } else {
                // Actualizamos solo el valor si ya existe el estilo
                processStepsContainer.style.setProperty('--progress-width', `${progressPercentage}%`);
            }
        }
        
        // Actualizar estado visual de cada paso
        processSteps.forEach((step) => {
            // Obtener el índice de etapa del formulario al que corresponde este paso
            const targetStage = parseInt(step.getAttribute('data-stage'));
            
            // Reiniciar clases
            step.classList.remove('active', 'completed', 'clickable');
            
            // Marcar el paso activo según la etapa actual del formulario
            if (targetStage === currentStage) {
                step.classList.add('active');
                
                // Si estamos en la etapa actual, añadir animación sutil de enfoque
                step.style.transform = 'scale(1.05)';
                step.style.transition = 'transform 0.3s ease';
            } else {
                step.style.transform = 'scale(1)';
            }
            
            // Marcar como completados los pasos anteriores y hacerlos clickables
            if (targetStage < currentStage) {
                step.classList.add('completed', 'clickable');
            }
            
            // Hacer todos los pasos clickables (navegación libre)
            step.classList.add('clickable');
        });
    }
    
    // Actualizar inicialmente
    updateProcessNavigationVisibility();
    updateProcessStepsProgress();
    
    // Añadir evento clic para navegación libre entre etapas del formulario
    processSteps.forEach((step) => {
        step.addEventListener('click', function() {
            // Solo activo cuando estamos en el formulario interactivo
            if (currentPage !== 3) return;
            
            const targetStage = parseInt(this.getAttribute('data-stage'));
            
            // Navegación libre - permite navegar a cualquier etapa
            
            // Agregar efecto visual de clic
            this.style.transform = 'scale(0.95)';
            setTimeout(() => {
                this.style.transform = 'scale(1.05)';
            }, 150);
            
            // Navegar a la etapa seleccionada sin restricciones
            showStage(targetStage);
            
            // Ya no desplazamos automáticamente
            console.log('Navegación entre etapas sin desplazamiento automático');
        });
        
        // Añadir efectos hover para mejor experiencia de usuario
        step.addEventListener('mouseenter', function() {
            // Todos los pasos son clickables (navegación libre)
            this.style.cursor = 'pointer';
            
            // Solo aplicar efecto de elevación si no es el paso activo
            if (parseInt(this.getAttribute('data-stage')) !== currentStage) {
                this.style.transform = 'translateY(-2px)';
            }
            
            // Efecto de brillo en el hover
            const stepNumber = this.querySelector('.step-number');
            if (stepNumber) {
                stepNumber.style.boxShadow = '0 0 8px rgba(var(--primary), 0.6)';
            }
        });
        
        step.addEventListener('mouseleave', function() {
            // Restaurar estado normal excepto para el paso activo
            if (parseInt(this.getAttribute('data-stage')) !== currentStage) {
                this.style.transform = 'translateY(0)';
            }
            
            // Quitar efecto de brillo
            const stepNumber = this.querySelector('.step-number');
            if (stepNumber) {
                stepNumber.style.boxShadow = '';
            }
        });
    });
    
    // Sobrescribir funciones para actualizar el menú
    const originalUpdateView = window.updateView;
    window.updateView = function() {
        // Primero actualizar la vista
        originalUpdateView();
        console.log('updateView sobrescrito ejecutado');
        
        // Luego actualizar el menú de navegación
        setTimeout(() => {
            updateProcessNavigationVisibility();
            updateProcessStepsProgress();
            console.log('Menú actualizado después de updateView');
        }, 100); // Pequeño retraso para asegurar que todo esté listo
    };
    
    const originalShowStage = window.showStage;
    window.showStage = function(stageIndex) {
        // Primero cambiar la etapa
        originalShowStage(stageIndex);
        console.log('showStage sobrescrito ejecutado para etapa', stageIndex);
        
        // Luego actualizar el menú de navegación
        setTimeout(() => {
            updateProcessStepsProgress();
            console.log('Menú actualizado después de showStage');
        }, 100); // Pequeño retraso para asegurar que todo esté listo
    };
}

// Funciones de compatibilidad (para evitar errores con código existente)
function updateHeaderProgress() {
    // Esta función se mantiene para compatibilidad con el código existente
    // Ahora la actualización del progreso se hace en el menú circular
}

function toggleHeaderVisibility(isFormVisible) {
    // Esta función se mantiene para compatibilidad con el código existente
    // Ahora la visibilidad se controla con el menú circular
}
        
        // Inicializar el menú de navegación circular
        initProcessNavigation();
        
        // Para dispositivos móviles, inicializar intervalos para mantener firma visible
        if (/Android|webOS|iPhone|iPad|iPod/i.test(navigator.userAgent)) {
            // REEMPLAZAMOS intervalos por eventos controlados para evitar vibraciones
            const isIOS = /iPhone|iPad|iPod/i.test(navigator.userAgent);
            
            // Evento para restaurar firma después de orientationchange
            window.addEventListener('orientationchange', function() {
                setTimeout(() => {
                    if (mainSignatureData && signaturePad && 
                        document.getElementById('signature-pad')) {
                        console.log('Restaurando firma después de cambio de orientación');
                        restoreSignature(document.getElementById('signature-pad'), signaturePad);
                    }
                }, 500);
            });
            
            // Evento de scroll con debounce en lugar de intervalos
            window.addEventListener('scroll', debounce(() => {
                if (mainSignatureData && signaturePad && 
                    document.getElementById('signature-pad') && 
                    document.querySelector('.form-stage[data-stage="2"].active')) {
                    console.log('Restaurando firma después de scroll');
                    restoreSignature(document.getElementById('signature-pad'), signaturePad);
                }
            }, 500), { passive: true });
            
            // Enfoque simplificado para iOS
            if (/iPhone|iPad|iPod/i.test(navigator.userAgent)) {
                // Configurar el canvas sin transformaciones que puedan causar problemas
                const signatureCanvas = document.getElementById('signature-pad');
                if (signatureCanvas) {
                    signatureCanvas.style.touchAction = 'none';
                    signatureCanvas.style.msTouchAction = 'none';
                    signatureCanvas.style.webkitTapHighlightColor = 'rgba(0,0,0,0)';
                    
                    // Aplicar estilos adicionales para mejorar el rendimiento
                    signatureCanvas.style.transform = 'translateZ(0)';
                    signatureCanvas.style.backfaceVisibility = 'hidden';
                }
                
                // Restaurar firma solo en eventos específicos y focus/blur
                document.addEventListener('visibilitychange', () => {
                    if (document.visibilityState === 'visible' && 
                        mainSignatureData && 
                        document.getElementById('signature-pad') && 
                        signaturePad && 
                        document.querySelector('.form-stage[data-stage="2"].active')) {
                        try {
                            console.log('Restaurando firma después de volver a la pestaña');
                            restoreSignature(document.getElementById('signature-pad'), signaturePad);
                        } catch (e) {
                            console.warn('Error al restaurar firma:', e);
                        }
                    }
                });
            }
        }
        
        // Funciones para implementar la navegación flotante mejorada
        function setupEnhancedNavigation() {
            console.log('Inicializando navegación mejorada...');
            
            // Variables para el control de scroll
            let lastScrollTop = 0;
            let isScrolling = false;
            let scrollTimer = null;
            
            // Actualizar el indicador de progreso
            function updateNavigationProgress() {
                const progressFills = document.querySelectorAll('.progress-indicator-fill');
                const progressPercentage = (currentStage / (formStages.length - 1)) * 100;
                
                progressFills.forEach(fill => {
                    fill.style.width = `${progressPercentage}%`;
                });
            }
            
            // Función para asegurar que la navegación esté siempre visible
            function enforceNavigationVisibility(immediate = false) {
                const navElements = document.querySelectorAll('.stage-navigation');
                
                // Limitar llamadas a esta función en móviles
                const isMobile = window.innerWidth <= 480;
                
                // Verificar si estamos en las pestañas de documentación (1) o pago (3)
                // para evitar interferir con el scroll en estas pestañas específicas
                let isDocumentacionOrPagoStage = false;
                
                if (isMobile) {
                    // Verificar si estamos en las etapas de documentación o pago
                    const activeStage = document.querySelector('.form-stage.active');
                    if (activeStage) {
                        const stageIndex = activeStage.getAttribute('data-stage');
                        isDocumentacionOrPagoStage = (stageIndex === '1' || stageIndex === '3');
                        
                        if (isDocumentacionOrPagoStage) {
                            console.log('En etapa de documentación o pago: permitiendo scroll libre');
                            // En estas etapas, solo hacer lo mínimo necesario para mantener la navegación visible
                            // sin interferir con el scroll
                            navElements.forEach(nav => {
                                if (!nav.dataset.styleFixed) {
                                    nav.style.display = 'flex';
                                    nav.style.position = 'fixed';
                                    nav.style.bottom = '0';
                                    nav.style.left = '0';
                                    nav.style.width = '100%';
                                    nav.style.zIndex = '9999';
                                    nav.dataset.styleFixed = 'true'; // Marcar como fijado
                                }
                            });
                            return; // Salir temprano para no ejecutar más código que manipule el scroll
                        }
                    }
                    
                    // Código original para otras etapas en móviles
                    navElements.forEach(nav => {
                        if (!nav.dataset.styleFixed) {
                            nav.style.display = 'flex';
                            nav.style.position = 'fixed';
                            nav.style.bottom = '0';
                            nav.style.left = '0';
                            nav.style.width = '100%';
                            nav.style.opacity = '1';
                            nav.style.visibility = 'visible';
                            nav.style.transform = 'none';
                            nav.style.zIndex = '9999';
                            nav.dataset.styleFixed = 'true'; // Marcar como fijado
                        }
                    });
                } else {
                    // En desktop, comportamiento normal
                    navElements.forEach(nav => {
                        nav.classList.remove('scrolling-down');
                        nav.style.transform = 'translateY(0)';
                        nav.style.opacity = '1';
                        nav.style.display = 'flex';
                        nav.style.visibility = 'visible';
                        nav.style.zIndex = '1000';
                    });
                }
                
                // Cuando se necesita aplicación inmediata, usar !important
                // Solo si NO estamos en las etapas de documentación o pago en móvil
                if (immediate && !(isMobile && isDocumentacionOrPagoStage)) {
                    navElements.forEach(nav => {
                        nav.setAttribute('style', nav.getAttribute('style') + ' display: flex !important; visibility: visible !important; opacity: 1 !important;');
                    });
                }
            }
            
            // Control de visibilidad en scroll - PERMITIR SCROLL LIBRE EN DOCUMENTACIÓN Y PAGO
            window.addEventListener('scroll', function() {
                // Verificar si estamos en las etapas de documentación o pago
                const activeStage = document.querySelector('.form-stage.active');
                if (activeStage) {
                    const stageIndex = activeStage.getAttribute('data-stage');
                    const isDocumentacionOrPagoStage = (stageIndex === '1' || stageIndex === '3');
                    const isMobile = window.innerWidth <= 480;
                    
                    // Si estamos en documentación o pago en móvil, permitir scroll libre
                    // No manipular la posición o visibilidad de elementos durante el scroll
                    if (isMobile && isDocumentacionOrPagoStage) {
                        console.log('Scroll libre permitido en etapa ' + stageIndex);
                        return; // No ejecutar nada más
                    }
                }
                
                // Para otras etapas, mantener el comportamiento original
                enforceNavigationVisibility();
                
                // Programar comprobaciones posteriores solo para etapas que no son documentación ni pago
                clearTimeout(scrollTimer);
                scrollTimer = setTimeout(() => {
                    enforceNavigationVisibility(true);
                }, 300);
            }, { passive: true });
            
            // Asegurar que los términos y condiciones siempre estén visibles
            function ensureCheckboxesVisibility() {
                console.log('Verificando visibilidad de checkboxes de términos y condiciones...');
                
                // Obtener todos los contenedores de términos y condiciones
                const termsContainers = document.querySelectorAll('.terms-container');
                
                // Detectar si estamos en iOS
                const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
                const isMobile = window.innerWidth <= 480;
                const extraMarginForIOS = isIOS && isMobile ? 150 : 0;
                
                termsContainers.forEach(container => {
                    // Verificar si el contenedor está en la etapa actual
                    const parentStage = container.closest('.form-stage');
                    if (!parentStage || !parentStage.classList.contains('active')) return;
                    
                    // Calcular la posición del checkbox para verificar superposición
                    const checkbox = container.querySelector('input[type="checkbox"]');
                    if (!checkbox) return;
                    
                    const checkboxRect = checkbox.getBoundingClientRect();
                    const viewportHeight = window.innerHeight;
                    
                    // Si el checkbox está cerca del borde inferior, agregar más margen al contenedor
                    // Margen mayor para iOS y móviles
                    const safetyMargin = isMobile ? 120 : 100;
                    if (checkboxRect.bottom > viewportHeight - safetyMargin - extraMarginForIOS) {
                        console.log('Checkbox potencialmente oculto detectado, ajustando margen...');
                        container.style.marginBottom = (100 + extraMarginForIOS) + 'px';
                        
                        // Forzar reflow para aplicar margen
                        void container.offsetHeight;
                        
                        // Eliminado scrollIntoView forzado para evitar problemas en móviles
                        // No forzamos el scroll para permitir que el usuario controle libremente
                        console.log('Checkbox ajustado sin forzar scroll');
                    }
                });
            }
            
            // Si queremos mantener el botón flotante como alternativa:
            const mobileNavToggle = document.getElementById('mobile-nav-toggle');
            const mobileNavActions = document.getElementById('mobile-nav-actions');
            const mobilePrevBtn = document.getElementById('mobile-prev-btn');
            const mobileNextBtn = document.getElementById('mobile-next-btn');
            
            if (mobileNavToggle && mobileNavActions) {
                // Alternar menú de navegación móvil
                mobileNavToggle.addEventListener('click', function() {
                    mobileNavActions.classList.toggle('active');
                    this.innerHTML = mobileNavActions.classList.contains('active') 
                        ? '<i class="fa-solid fa-times"></i>' 
                        : '<i class="fa-solid fa-ellipsis"></i>';
                });
                
                // Cerrar al hacer clic en cualquier parte
                document.addEventListener('click', function(e) {
                    if (!mobileNavToggle.contains(e.target) && 
                        !mobileNavActions.contains(e.target) &&
                        mobileNavActions.classList.contains('active')) {
                        mobileNavActions.classList.remove('active');
                        mobileNavToggle.innerHTML = '<i class="fa-solid fa-ellipsis"></i>';
                    }
                });
                
                // Actualizar visibilidad y etiquetas según la etapa
                function updateMobileNavigation() {
                    // Ocultar botón anterior en primera etapa
                    mobilePrevBtn.style.display = currentStage === 0 ? 'none' : 'flex';
                    
                    // Cambiar etiqueta del botón siguiente en la última etapa
                    const nextLabel = mobileNextBtn.querySelector('.action-label');
                    if (nextLabel) {
                        nextLabel.textContent = currentStage === formStages.length - 1 ? 'Finalizar' : 'Continuar';
                    }
                }
                
                // Asignar funcionalidad a los botones
                mobilePrevBtn.addEventListener('click', function() {
                    // Verificar que no estemos en la primera etapa
                    if (currentStage > 0) {
                        showStage(currentStage - 1);
                        updateMobileNavigation();
                        mobileNavActions.classList.remove('active');
                        mobileNavToggle.innerHTML = '<i class="fa-solid fa-ellipsis"></i>';
                    }
                });
                
                mobileNextBtn.addEventListener('click', function() {
                    // Si estamos en la última etapa, procesar pago
                    if (currentStage === formStages.length - 1) {
                        processPayment();
                    } 
                    // En la etapa de firma, validamos solo la firma
                    else if (currentStage === 2) {
                        if (validateStage(2)) { // Solo verificamos la firma
                            showStage(currentStage + 1);
                            updateMobileNavigation();
                        }
                    }
                    // En las demás etapas, permitimos avanzar sin validación estricta
                    else {
                        showStage(currentStage + 1);
                        updateMobileNavigation();
                    }
                    
                    mobileNavActions.classList.remove('active');
                    mobileNavToggle.innerHTML = '<i class="fa-solid fa-ellipsis"></i>';
                });
                
                // Inicializar estado
                updateMobileNavigation();
            }
            
            // Modificar la función showStage original para actualizar la navegación
            const originalShowStage = window.showStage;
            window.showStage = function(stageIndex) {
                originalShowStage(stageIndex);
                updateNavigationProgress();
                
                // Garantizar que los botones estén visibles al cambiar de etapa
                enforceNavigationVisibility(true);
                
                // Verificar visibilidad de checkboxes después de cambiar etapas
                setTimeout(ensureCheckboxesVisibility, 300);
                
                // Recalcular altura del contenedor para asegurar que todo sea visible
                // Múltiples intentos con intervalos diferentes para capturar cambios dinámicos
                setTimeout(recalculateContainerHeight, 100);
                setTimeout(recalculateContainerHeight, 500);
                
                // Una verificación adicional después de más tiempo para contenido dinámico
                setTimeout(() => {
                    enforceNavigationVisibility(true);
                    ensureCheckboxesVisibility();
                    recalculateContainerHeight();
                }, 1000);
            };
            
            // Verificar checkboxes al redimensionar la ventana
            window.addEventListener('resize', debounce(function() {
                ensureCheckboxesVisibility();
                recalculateContainerHeight();
                enforceNavigationVisibility(true);
            }, 200));
            
            // Verificar checkboxes después de que se cargue la página
            window.addEventListener('load', function() {
                // Inicialización inicial
                enforceNavigationVisibility(true);
                setTimeout(ensureCheckboxesVisibility, 500);
                
                // Forzar verificaciones después de la carga completa
                setTimeout(() => {
                    enforceNavigationVisibility(true);
                    ensureCheckboxesVisibility();
                    recalculateContainerHeight();
                }, 1000);
                
                // Verificación final para estar totalmente seguros
                setTimeout(() => {
                    enforceNavigationVisibility(true);
                    ensureCheckboxesVisibility();
                    
                    // En caso de iOS, hacer una verificación adicional
                    const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
                    if (isIOS) {
                        setTimeout(recalculateContainerHeight, 500);
                    }
                }, 2000);
                
                // ELIMINAMOS el intervalo periódico que causaba vibraciones visuales
                // En su lugar, solo aplicamos este estilo al inicializar y en eventos específicos
                enforceNavigationVisibility(true);
                
                // Solo añadimos event listeners específicos si son necesarios
                window.addEventListener('orientationchange', () => {
                    setTimeout(() => enforceNavigationVisibility(true), 500);
                });
            });
            
            console.log('Navegación mejorada inicializada');
        }

        // Añadir a la lista de funciones de inicialización
        const oldInit = window.onload || function() {};
        window.onload = function() {
            if (typeof oldInit === 'function') oldInit();
            setupEnhancedNavigation();
        };
    });
    </script>
    
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
                
                <!-- Modo de prueba indicador -->
                <?php if ($is_test_mode): ?>
                <div style="background: #fef3c7; border: 1px solid #f59e0b; padding: 10px; margin-bottom: 15px; border-radius: 6px; font-size: 14px; text-align: center;">
                    ⚠️ <strong>MODO PRUEBA</strong> - Usa tarjeta: 4242 4242 4242 4242
                </div>
                <?php endif; ?>

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
                <span>Política de uso y términos de pago aceptados</span>
            </div>
            
            <button type="button" id="confirm-payment-button" class="confirm-payment-button">
                <i class="fa-solid fa-check-circle"></i> Confirmar Pago
            </button>
        </div>
    </div>
    
    <!-- Overlay de carga con pasos visuales -->
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
    <?php
    return ob_get_clean();
}

/**
 * Endpoint para crear el Payment Intent (Stripe)
 */
add_action('wp_ajax_create_payment_intent_hoja_asiento', 'create_payment_intent_hoja_asiento');
add_action('wp_ajax_nopriv_create_payment_intent_hoja_asiento', 'create_payment_intent_hoja_asiento');
function create_payment_intent_hoja_asiento() {
    // Usar constantes (IGUAL QUE RECUPERAR DOCUMENTACIÓN)
    if (HOJA_ASIENTO_STRIPE_MODE === 'test') {
        $stripe_secret_key = HOJA_ASIENTO_STRIPE_TEST_SECRET_KEY;
    } else {
        $stripe_secret_key = HOJA_ASIENTO_STRIPE_LIVE_SECRET_KEY;
    }

    header('Content-Type: application/json');
    require_once get_template_directory() . '/vendor/autoload.php';

    try {
        error_log('=== HOJA ASIENTO PAYMENT INTENT ===');
        error_log('STRIPE MODE: ' . HOJA_ASIENTO_STRIPE_MODE);
        error_log('Using Stripe key starting with: ' . substr($stripe_secret_key, 0, 25));

        \Stripe\Stripe::setApiKey($stripe_secret_key);

        $currentKey = \Stripe\Stripe::getApiKey();
        error_log('Stripe API Key confirmed: ' . substr($currentKey, 0, 25));

        $amount = isset($_POST['amount']) ? intval($_POST['amount']) : 0;

        $paymentIntent = \Stripe\PaymentIntent::create([
            'amount' => $amount,
            'currency' => 'eur',
            'automatic_payment_methods' => ['enabled' => true],
            'description' => 'Hoja de Asiento - Trámite Marítimo',
            'metadata' => [
                'service' => 'Hoja de Asiento',
                'source' => 'tramitfy_web',
                'form' => 'hoja_asiento',
                'mode' => HOJA_ASIENTO_STRIPE_MODE
            ]
        ]);

        echo json_encode([
            'clientSecret' => $paymentIntent->client_secret,
            'debug' => [
                'mode' => HOJA_ASIENTO_STRIPE_MODE,
                'paymentIntentId' => $paymentIntent->id
            ]
        ]);
    } catch (Exception $e) {
        error_log('Error creating payment intent: ' . $e->getMessage());
        echo json_encode(['error' => $e->getMessage()]);
    }

    wp_die();
}

/**
 * Endpoint para validar el cupón de descuento
 */
add_action('wp_ajax_validate_coupon_code_hoja_asiento', 'validate_coupon_code_hoja_asiento');
add_action('wp_ajax_nopriv_validate_coupon_code_hoja_asiento', 'validate_coupon_code_hoja_asiento');
function validate_coupon_code_hoja_asiento() {
    // Definir cupones válidos
    $valid_coupons = array(
        'DESCUENTO10' => 10,
        'DESCUENTO20' => 20,
        'VERANO15'    => 15,
        'BLACK50'     => 50,
    );

    $coupon = isset($_POST['coupon']) ? sanitize_text_field($_POST['coupon']) : '';
    $coupon_upper = strtoupper($coupon);

    if (isset($valid_coupons[$coupon_upper])) {
        $discount_percent = $valid_coupons[$coupon_upper];
        wp_send_json_success(['discount_percent' => $discount_percent]);
    } else {
        wp_send_json_error('Cupón inválido o expirado');
    }
    wp_die();
}

/**
 * Función para enviar emails mejorados
 */
add_action('wp_ajax_send_emails_hoja_asiento', 'send_emails_hoja_asiento');
add_action('wp_ajax_nopriv_send_emails_hoja_asiento', 'send_emails_hoja_asiento');
function send_emails_hoja_asiento() {
    // Datos que llegan por POST
    $customer_email = sanitize_email($_POST['customer_email']);
    $customer_name = sanitize_text_field($_POST['customer_name']);
    $customer_dni = sanitize_text_field($_POST['customer_dni']);
    $customer_phone = sanitize_text_field($_POST['customer_phone']);
    $payment_amount = isset($_POST['payment_amount']) ? sanitize_text_field($_POST['payment_amount']) : '29.99';
    $boat_name = sanitize_text_field($_POST['boat_name']);
    $boat_nib = sanitize_text_field($_POST['boat_nib']);
    $boat_matricula = sanitize_text_field($_POST['boat_matricula']);
    $tramite_id = isset($_POST['tramite_id']) ? sanitize_text_field($_POST['tramite_id']) : '';
    
    // Si no se pasó un ID de trámite, generamos uno nuevo
    if (empty($tramite_id)) {
        $prefix = 'TMA-HOJA';
        $counter_option = 'tma_hoja_counter';
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
    $subject_customer = '¡Confirmación de su solicitud de Hoja de Asiento! - Tramitfy';

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
                <p style="margin: 10px 0 0; font-size: 18px; opacity: 0.9;">Su solicitud de Hoja de Asiento está en proceso</p>
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
                
                <p style="margin-bottom: 25px;">Nos complace confirmarle que hemos recibido correctamente toda la documentación e información necesaria para su trámite de solicitud de copia de Hoja de Asiento. Nuestro equipo de profesionales ya está trabajando en su caso para asegurar que todo el proceso se realice de manera eficiente y sin contratiempos.</p>
                
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
                            <td style="padding: 8px 10px 8px 0; width: 45%; vertical-align: top; color: #555; font-weight: 500;">Nombre de la embarcación:</td>
                            <td style="padding: 8px 0; vertical-align: top; font-weight: 600;"><?php echo esc_html($boat_name); ?></td>
                        </tr>
                        <tr style="background-color: #f0f4f7;">
                            <td style="padding: 8px 10px 8px 0; width: 45%; vertical-align: top; color: #555; font-weight: 500;">Matrícula:</td>
                            <td style="padding: 8px 0; vertical-align: top; font-weight: 600;"><?php echo esc_html($boat_matricula); ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 10px 8px 0; width: 45%; vertical-align: top; color: #555; font-weight: 500;">NIB:</td>
                            <td style="padding: 8px 0; vertical-align: top; font-weight: 600;"><?php echo esc_html($boat_nib); ?></td>
                        </tr>
                        <tr style="background-color: #f0f4f7;">
                            <td style="padding: 8px 10px 8px 0; width: 45%; vertical-align: top; color: #555; font-weight: 500;">Importe abonado:</td>
                            <td style="padding: 8px 0; vertical-align: top; font-weight: 600;"><?php echo esc_html($payment_amount); ?> €</td>
                        </tr>
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

    // También enviar correo al administrador
    $admin_email = get_option('admin_email');
    $subject_admin = 'Nueva solicitud de Hoja de Asiento - ' . $tramite_id;
    
    // Email simplificado para el administrador
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head><meta charset="UTF-8"></head>
    <body style="font-family: Arial, sans-serif;">
        <div style="max-width:600px;margin:auto;padding:20px;background:#f9f9f9;border:1px solid #e0e0e0;border-radius:10px;">
            <div style="text-align:center;">
                <img src="https://www.tramitfy.es/wp-content/uploads/LOGO.png" alt="Tramitfy Logo" style="max-width:200px;">
                <h2 style="color:#016d86;">Nueva Solicitud de Hoja de Asiento</h2>
                <p><strong>ID Trámite:</strong> <?php echo esc_html($tramite_id); ?></p>
            </div>
            <div style="background:#fff;padding:20px;border-radius:8px;">
                <h3 style="color:#016d86;">Datos del Cliente</h3>
                <p><strong>Nombre:</strong> <?php echo esc_html($customer_name); ?></p>
                <p><strong>DNI:</strong> <?php echo esc_html($customer_dni); ?></p>
                <p><strong>Email:</strong> <?php echo esc_html($customer_email); ?></p>
                <p><strong>Teléfono:</strong> <?php echo esc_html($customer_phone); ?></p>
                
                <h3 style="color:#016d86;">Datos de la Embarcación</h3>
                <p><strong>Nombre:</strong> <?php echo esc_html($boat_name); ?></p>
                <p><strong>Matrícula:</strong> <?php echo esc_html($boat_matricula); ?></p>
                <p><strong>NIB:</strong> <?php echo esc_html($boat_nib); ?></p>
                
                <h3 style="color:#016d86;">Detalles del Pago</h3>
                <p><strong>Importe:</strong> <?php echo esc_html($payment_amount); ?> €</p>
            </div>
        </div>
    </body>
    </html>
    <?php
    $message_admin = ob_get_clean();
    
    wp_mail($admin_email, $subject_admin, $message_admin, $headers);

    wp_send_json_success('Correos enviados con éxito');
    wp_die();
}

/**
 * Función para manejar el envío final del formulario con integración a Google Drive y Sheets
 */
add_action('wp_ajax_submit_form_hoja_asiento', 'submit_form_hoja_asiento');
add_action('wp_ajax_nopriv_submit_form_hoja_asiento', 'submit_form_hoja_asiento');
function submit_form_hoja_asiento() {
    // Recuperar datos del formulario
    $customer_name  = sanitize_text_field($_POST['customer_name']);
    $customer_dni   = sanitize_text_field($_POST['customer_dni']);
    $customer_email = sanitize_email($_POST['customer_email']);
    $customer_phone = sanitize_text_field($_POST['customer_phone']);
    $boat_name      = sanitize_text_field($_POST['boat_name']);
    $boat_nib       = sanitize_text_field($_POST['boat_nib']);
    $boat_matricula = sanitize_text_field($_POST['boat_matricula']);
    $coupon_used    = isset($_POST['coupon_used']) ? sanitize_text_field($_POST['coupon_used']) : '';
    $tramite_id     = isset($_POST['tramite_id']) ? sanitize_text_field($_POST['tramite_id']) : '';
    $payment_amount = isset($_POST['payment_amount']) ? floatval($_POST['payment_amount']) : 29.99;
    
    // Datos de autorización
    $autorizacion_nombre = isset($_POST['autorizacion_nombre']) ? sanitize_text_field($_POST['autorizacion_nombre']) : $customer_name;
    $autorizacion_dni = isset($_POST['autorizacion_dni']) ? sanitize_text_field($_POST['autorizacion_dni']) : $customer_dni;
    $tipo_representante = isset($_POST['tipo_representante']) ? sanitize_text_field($_POST['tipo_representante']) : 'representante';

    // Carpeta para archivos locales
    $upload_dir = wp_upload_dir();
    $client_data_dir = $upload_dir['basedir'] . '/client_data';
    if (!file_exists($client_data_dir)) {
        wp_mkdir_p($client_data_dir);
    }

    // Capturar la firma
    $signature = $_POST['signature'];
    $signature_data = str_replace('data:image/png;base64,', '', $signature);
    $signature_data = str_replace(' ', '+', $signature_data);
    $signature_data = base64_decode($signature_data);

    // Guardar la firma temporal
    $signature_image_name = 'signature_' . time() . '.png';
    $signature_image_path = $client_data_dir . '/' . $signature_image_name;
    file_put_contents($signature_image_path, $signature_data);

    // Generar el PDF
    require_once get_template_directory() . '/vendor/fpdf/fpdf.php';
    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 14);

    // Título y fecha
    $pdf->Cell(0, 10, utf8_decode('AUTORIZACIÓN DE REPRESENTACIÓN'), 0, 1, 'C');
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 6, 'Fecha: ' . date('d/m/Y'), 0, 1, 'R');
    $pdf->Ln(6);

    // Información de la autorización
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 10, utf8_decode('DATOS DEL AUTORIZANTE'), 0, 1, 'L');
    $pdf->SetFont('Arial', '', 11);
    
    // Datos del autorizante en formato tabla
    $pdf->Cell(40, 8, 'Nombre completo:', 0, 0);
    $pdf->Cell(0, 8, utf8_decode($autorizacion_nombre), 0, 1);
    
    $pdf->Cell(40, 8, 'DNI/NIE:', 0, 0);
    $pdf->Cell(0, 8, $autorizacion_dni, 0, 1);
    $pdf->Ln(5);
    
    // Datos de la embarcación
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 10, utf8_decode('DATOS DE LA EMBARCACIÓN'), 0, 1, 'L');
    $pdf->SetFont('Arial', '', 11);
    
    $pdf->Cell(40, 8, 'Nombre:', 0, 0);
    $pdf->Cell(0, 8, utf8_decode($boat_name), 0, 1);
    
    $pdf->Cell(40, 8, utf8_decode('Matrícula:'), 0, 0);
    $pdf->Cell(0, 8, $boat_matricula, 0, 1);
    
    $pdf->Cell(40, 8, 'NIB:', 0, 0);
    $pdf->Cell(0, 8, $boat_nib, 0, 1);
    $pdf->Ln(5);
    
    // Texto de la autorización
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 10, utf8_decode('AUTORIZACIÓN'), 0, 1, 'L');
    $pdf->SetFont('Arial', '', 11);
    
    $texto = "Por la presente, yo $autorizacion_nombre, con DNI/NIE $autorizacion_dni, AUTORIZO a Tramitfy S.L. con CIF B55388557 a actuar como mi representante legal para la tramitación y gestión del procedimiento de solicitud de copia de hoja de asiento para el barco '$boat_name' con NIB: $boat_nib y matrícula: $boat_matricula ante las autoridades competentes.";
    $pdf->MultiCell(0, 6, utf8_decode($texto), 0, 'J');
    $pdf->Ln(3);
    
    $texto2 = "Doy conformidad para que Tramitfy S.L. pueda presentar y recoger cuanta documentación sea necesaria, subsanar defectos, pagar tasas y realizar cuantas actuaciones sean precisas para la correcta finalización del procedimiento.";
    $pdf->MultiCell(0, 6, utf8_decode($texto2), 0, 'J');
    $pdf->Ln(10);
    
    // Firma
    $pdf->Cell(0, 8, utf8_decode('Firma del autorizante:'), 0, 1);
    $pdf->Image($signature_image_path, 30, $pdf->GetY(), 50, 30);
    $pdf->Ln(35);
    
    // Pie de página legal
    $pdf->SetFont('Arial', 'I', 8);
    $pdf->MultiCell(0, 4, utf8_decode('En cumplimiento del Reglamento (UE) 2016/679 de Protección de Datos, le informamos que sus datos personales serán tratados por Tramitfy S.L. con la finalidad de gestionar su solicitud. Puede ejercer sus derechos de acceso, rectificación, supresión y portabilidad dirigiéndose a info@tramitfy.es'), 0, 'J');

    $authorization_pdf_name = 'autorizacion_' . time() . '.pdf';
    $authorization_pdf_path = $client_data_dir . '/' . $authorization_pdf_name;
    $pdf->Output('F', $authorization_pdf_path);

    $authorization_pdf_url = $upload_dir['baseurl'] . '/client_data/' . $authorization_pdf_name;
    $uploaded_files_urls = [];
    $uploaded_files_urls[] = $authorization_pdf_url;

    // Manejar archivos subidos
    foreach ($_FILES as $fileKey => $file) {
        if ($file['error'] === UPLOAD_ERR_OK) {
            $filename = sanitize_file_name($file['name']);
            $target_path = $client_data_dir . '/' . $filename;
            if (move_uploaded_file($file['tmp_name'], $target_path)) {
                $file_url = $upload_dir['baseurl'] . '/client_data/' . $filename;
                $uploaded_files_urls[] = $file_url;
            }
        }
    }

    // Integración con Google Drive y Google Sheets
    try {
        require_once __DIR__ . '/vendor/autoload.php';

        $googleCredentialsPath = __DIR__ . '/credentials.json';
        $client = new Google_Client();
        $client->setAuthConfig($googleCredentialsPath);
        $client->addScope(Google_Service_Drive::DRIVE_FILE);

        $driveService = new Google_Service_Drive($client);

        // ID de la carpeta "padre" en Drive donde se crearán subcarpetas mensuales
        $parentFolderId = '1vxHdQImalnDVI7aTaE0cGIX7m-7pl7sr';

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
        if ($folderId && !empty($uploaded_files_urls)) {
            foreach ($uploaded_files_urls as $fileUrl) {
                $filePath = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $fileUrl);
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

        // Escritura en Google Sheets
        $sheetsClient = new Google_Client();
        $sheetsClient->setAuthConfig($googleCredentialsPath);
        $sheetsClient->addScope(Google_Service_Sheets::SPREADSHEETS);
        $sheetsService = new Google_Service_Sheets($sheetsClient);

        // ID de tu hoja de cálculo
        $spreadsheetId = '1APFnwJ3yBfxt1M4JJcfPLOQkdIF27OXAzubW1Bx9ZbA'; 

        // Guardar datos en formato simple en la hoja principal (DATABASE)
        $rangeDatabase = 'DATABASE!A1';
        $fecha      = date('d/m/Y');
        $driveLinks = implode("\n", $uploadedDriveLinks);
        $clientData = "Nombre: $customer_name\nDNI: $customer_dni\nEmail: $customer_email\nTlf: $customer_phone";
        $boatData  = "Embarcación: $boat_name\nMatrícula: $boat_matricula\nNIB: $boat_nib";

        $rowValuesDatabase = [
            $tramite_id,
            $clientData,
            $boatData,
            "IMPORTE TOTAL: $payment_amount €\nCUPON USADO: $coupon_used",
            $fecha,
            $driveLinks
        ];

        $bodyDatabase = new Google_Service_Sheets_ValueRange(['values' => [$rowValuesDatabase]]);
        $paramsDatabase = ['valueInputOption' => 'USER_ENTERED'];
        $sheetsService->spreadsheets_values->append($spreadsheetId, $rangeDatabase, $bodyDatabase, $paramsDatabase);

        // Guardar datos en formato estructurado en la hoja OrganizedData
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

        // Calcular desglose de precios con posible descuento
        $tasas = 7.61; // Valor fijo de tasas
        $honorariosBase = 18.50; // Valor base de honorarios
        $porcentajeIVA = 0.21; // 21% de IVA
        
        // Si hay un cupón de descuento, aplicarlo solo a honorarios
        $descuento = 0;
        if (!empty($coupon_used)) {
            // Asumimos que el cupón tiene un formato como "DESCUENTO10" o similar
            // Extraemos el número del cupón o aplicamos un descuento predeterminado
            preg_match('/\d+/', $coupon_used, $matches);
            $descuentoPorcentaje = (isset($matches[0])) ? (int)$matches[0] / 100 : 0.1; // 10% por defecto si no se específica
            $descuento = $honorariosBase * $descuentoPorcentaje;
            $honorarios = $honorariosBase - $descuento;
        } else {
            $honorarios = $honorariosBase;
        }
        
        $ivaHonorarios = $honorarios * $porcentajeIVA;
        $totalPrecio = $tasas + $honorarios + $ivaHonorarios;
        
        // Redondear a dos decimales
        $tasas = round($tasas, 2);
        $honorarios = round($honorarios, 2);
        $ivaHonorarios = round($ivaHonorarios, 2);
        $totalPrecio = round($totalPrecio, 2);
        
        // Datos para la hoja OrganizedData según las columnas específicas
        $organizedRow = [
            $tramite_id,                       // ID Trámite
            $customer_name,                    // Nombre
            $customer_dni,                     // DNI
            $customer_email,                   // Email
            $customer_phone,                   // Teléfono
            'Embarcación',                     // Tipo de Vehículo
            '',                                // Fabricante
            $boat_name,                        // Modelo (usando el nombre de la embarcación)
            '',                                // Fecha de Matriculación
            '',                                // Precio de Compra
            '',                                // Comunidad Autónoma
            $coupon_used,                      // Cupón Aplicado
            '',                                // Nuevo Nombre
            '',                                // Nuevo Puerto
            $totalPrecio,                      // Importe Total
            '',                                // ITP
            $tasas,                            // Tasas
            $ivaHonorarios,                    // IVA
            $honorarios,                        // Honorarios
        ];
        
        // Añadir enlaces a documentos en las columnas restantes
        $docIndex = 0;
        foreach ($uploadedDriveLinks as $docLink) {
            if ($docIndex < 5) { // Solo añadir hasta 5 documentos (Documento 1-5)
                $organizedRow[] = $docLink;
                $docIndex++;
            } else {
                break;
            }
        }
        
        // Rellenar con valores vacíos si hay menos de 5 documentos
        while ($docIndex < 5) {
            $organizedRow[] = '';
            $docIndex++;
        }

        $rangeOrganized = $newSheetTitle . '!A1';
        $bodyOrganized = new Google_Service_Sheets_ValueRange(['values' => [$organizedRow]]);
        $paramsOrganized = ['valueInputOption' => 'USER_ENTERED'];
        $sheetsService->spreadsheets_values->append($spreadsheetId, $rangeOrganized, $bodyOrganized, $paramsOrganized);

    } catch (Exception $e) {
        // Log error pero no detener el proceso
        error_log('Error en Google Drive/Sheets: ' . $e->getMessage());
    }

    // Limpieza
    @unlink($signature_image_path);

    // Enviar datos a la API de Tramitfy - Webhook para sincronizar con React Dashboard
    $tramitfy_api_url = 'https://46-202-128-35.sslip.io/api/herramientas/forms/hoja-asiento';

    $tramitfy_data = array(
        'customer_name' => $customer_name,
        'customer_dni' => $customer_dni,
        'customer_email' => $customer_email,
        'customer_phone' => $customer_phone,
        'boat_name' => $boat_name,
        'boat_matricula' => $boat_matricula,
        'boat_nib' => $boat_nib,
        'coupon_used' => $coupon_used,
        'payment_intent_id' => $tramite_id,
        'payment_completed' => 'true'
    );

    $tramitfy_response = wp_remote_post($tramitfy_api_url, array(
        'method' => 'POST',
        'timeout' => 30,
        'headers' => array(
            'Content-Type' => 'application/x-www-form-urlencoded'
        ),
        'body' => $tramitfy_data
    ));

    if (is_wp_error($tramitfy_response)) {
        error_log('Error enviando a API Tramitfy: ' . $tramitfy_response->get_error_message());
    } else {
        $response_body = wp_remote_retrieve_body($tramitfy_response);
        error_log('Respuesta de API Tramitfy: ' . $response_body);
    }

    // Respuesta para AJAX
    wp_send_json_success('Formulario procesado correctamente.');
    wp_die();
}

/**
 * Función auxiliar para descargar un archivo
 */
function downloadFileTemporally($fileUrl) {
    if (strpos($fileUrl, 'http') !== 0) {
        return false;
    }
    $tempDir = sys_get_temp_dir();
    $tempFile = tempnam($tempDir, 'gdrive_');
    $fileContent = @file_get_contents($fileUrl);

    if ($fileContent === false) {
        return false;
    }
    file_put_contents($tempFile, $fileContent);
    return $tempFile;
}

// Registrar el shortcode
add_shortcode('hoja_asiento_form', 'hoja_asiento_form_shortcode');
?>