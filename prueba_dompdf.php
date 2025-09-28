<?php
ini_set('display_errors', 1); // Asegúrate de que los errores se muestran
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "Iniciando prueba de DomPDF<br>";

// Cargar el autoloader de Composer solo para DomPDF
require_once __DIR__ . '/vendor/vendor_dompdf/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

echo "Autoloader de Composer para DomPDF cargado correctamente<br>";

// Crear instancia de opciones para DomPDF
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);

echo "Opciones configuradas<br>";

// Crear una instancia de Dompdf con opciones
$dompdf = new Dompdf($options);
echo "Instancia de DomPDF creada con opciones<br>";

// Definir el HTML que se usará en el PDF
$html = "<h1>Prueba de DomPDF</h1><p>Si estás viendo este PDF, DomPDF está funcionando correctamente.</p>";
echo "HTML definido para el PDF<br>";

// Cargar el HTML en Dompdf
$dompdf->loadHtml($html);
echo "HTML cargado en DomPDF<br>";

// Configurar el tamaño de papel y la orientación
$dompdf->setPaper('A4', 'portrait');
echo "Configuración de papel establecida<br>";

// Renderizar el PDF
try {
    $dompdf->render();
    echo "PDF renderizado correctamente<br>";
} catch (Exception $e) {
    echo "Error al renderizar PDF: " . $e->getMessage();
    exit;
}

// Enviar el PDF al navegador
try {
    $dompdf->stream("prueba_dompdf.pdf", ["Attachment" => false]);
    echo "PDF enviado al navegador<br>";
} catch (Exception $e) {
    echo "Error al enviar PDF: " . $e->getMessage();
    exit;
}

