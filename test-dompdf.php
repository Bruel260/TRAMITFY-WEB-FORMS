<?php
// Iniciar sesión de errores para verificar cualquier problema en el archivo de log
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/vendor/dompdf/src/Autoloader.php';
Dompdf\Autoloader::register();


use Dompdf\Dompdf;

// Crear una instancia de DOMPDF
$dompdf = new Dompdf();

// Configurar el contenido HTML
$html = '<h1>Hola, prueba de DOMPDF</h1><p>Si ves esto, DOMPDF está funcionando correctamente.</p>';

// Cargar el HTML en DOMPDF
$dompdf->loadHtml($html);

// (Opcional) Configurar el tamaño y la orientación del papel
$dompdf->setPaper('A4', 'portrait');

// Renderizar el PDF
$dompdf->render();

// Mostrar el PDF en el navegador
$dompdf->stream("prueba_dompdf.pdf", array("Attachment" => false));
?>
