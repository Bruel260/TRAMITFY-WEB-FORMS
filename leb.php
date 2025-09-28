<?php 
// Asegurarse de que el archivo no sea accedido directamente
defined('ABSPATH') || exit;

/**
 * Solicitud LEB
 * Formulario para gestionar solicitudes LEBs
 */

/**
 * [MODO TEST/PRODUCCIÓN]
 * Cambia a false en producción.
 */
$is_test_mode = false; // true = usa claves de prueba, false = usa claves en vivo.
$publishable_key_test = 'YOUR_STRIPE_TEST_PUBLIC_KEY_HERE';
$publishable_key_live = 'YOUR_STRIPE_LIVE_PUBLIC_KEY_HERE';
$secret_key_test = 'YOUR_STRIPE_TEST_SECRET_KEY_HERE';
$secret_key_live = 'YOUR_STRIPE_LIVE_SECRET_KEY_HERE';

/**
 * Función principal para generar y mostrar el formulario en el frontend
 */
function solicitud_leb_form_shortcode() {
    // Variables globales
    global $is_test_mode, $publishable_key_test, $publishable_key_live;

    // Encolar scripts y estilos
    wp_enqueue_style('solicitud-leb-form-style', get_template_directory_uri() . '/style.css', array(), filemtime(get_template_directory() . '/style.css'));
    wp_enqueue_script('stripe', 'https://js.stripe.com/v3/', array(), null, false);
    wp_enqueue_script('signature-pad', 'https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js', array(), null, false);
    wp_enqueue_script('font-awesome', 'https://kit.fontawesome.com/a076d05399.js', array(), null, false);

    // Generar ID de trámite
    $prefix = 'TMA-LEB';
    $counter_option = $is_test_mode ? 'tma_leb_counter_test' : 'tma_leb_counter';
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
    </style>

    <!-- Formulario principal -->
    <form id="solicitud-leb-form" action="" method="POST" enctype="multipart/form-data">
        <!-- Contenido del formulario -->
        <div class="page-container">
            <div class="marketing-container">
                <h2>Solicitud LEB en minutos</h2>
                <p>Gestione la solicitud LEB de su embarcación sin complicaciones.</p>
            </div>
            
            <!-- Sección de datos personales -->
            <div class="form-section">
                <h3>Datos Personales</h3>
                <!-- Campos del formulario de datos personales -->
                <div class="form-field">
                    <label for="nombre">Nombre completo</label>
                    <input type="text" id="nombre" name="nombre" required>
                </div>
                <!-- Otros campos de datos personales -->
            </div>
            
            <!-- Sección de información de la embarcación -->
            <div class="form-section">
                <h3>Información de la Embarcación</h3>
                <div class="form-field">
                    <label for="boat_mmsi">Número MMSI</label>
                    <input type="text" id="boat_mmsi" name="boat_mmsi" required>
                </div>
            </div>
            
            <!-- Sección de documentación -->
            <div class="form-section">
                <h3>Documentación</h3>
                <!-- Campo para DNI -->
                <div class="upload-container">
                    <h4>DNI del propietario</h4>
                    <input type="file" id="dni_file" name="dni_file" accept=".jpg,.jpeg,.png,.pdf" required>
                </div>
                
                <!-- Campo para Hoja de Registro Español -->
                <div class="upload-container">
                    <h4>Hoja de Registro Español</h4>
                    <input type="file" id="registro_file" name="registro_file" accept=".jpg,.jpeg,.png,.pdf" required>
                </div>
            </div>
            
            <!-- Sección de pago -->
            <div class="form-section">
                <h3>Pago</h3>
                <div class="price-details">
                    <p><strong>Solicitud LEB:</strong> <span>110.00€</span></p>
                    <p>Tasas + Honorarios: <span>90.00 €</span></p>
                    <p>IVA (21%): <span>20.00 €</span></p>
                    <p class="price-total">Total a pagar: <span id="final-amount">110.00 €</span></p>
                </div>
                
                <!-- Botón de pago -->
                <button type="submit" class="submit-button">Realizar Pago</button>
            </div>
        </div>
    </form>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // JavaScript para manejar el formulario
        const form = document.getElementById('solicitud-leb-form');
        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                // Aquí iría el código para procesar el formulario
                console.log('Formulario enviado');
                // Mostrar mensaje de éxito
                alert('Formulario enviado correctamente');
            });
        }
    });
    </script>
    <?php
    
    // Obtener la salida del buffer y devolverla
    return ob_get_clean();
}

// Registrar el shortcode
add_shortcode('solicitud_leb_form', 'solicitud_leb_form_shortcode');
?>