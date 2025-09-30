<?php
// Asegurarse de que el archivo no sea accedido directamente
defined('ABSPATH') || exit;

/**
 * Shortcode simple para transferencia barco
 */
function transferencia_barco_shortcode() {
    ob_start();
    ?>
    <div class="transferencia-barco-form">
        <h2>Formulario de Transferencia de Barco</h2>
        <p>Formulario temporalmente simplificado para debugging</p>

        <form id="transferencia-form" method="post">
            <label>Nombre:</label>
            <input type="text" name="customer_name" required>

            <label>Email:</label>
            <input type="email" name="customer_email" required>

            <button type="submit">Enviar</button>
        </form>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('transferencia_barco_form', 'transferencia_barco_shortcode');