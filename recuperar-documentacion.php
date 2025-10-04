<?php
/**
 * Formulario de Recuperación de Documentación Extraviada
 * Para WordPress - Shortcode: [recuperar_documentacion_form]
 */

// Asegurarse de que el archivo no sea accedido directamente
defined('ABSPATH') || exit;

error_log("=== RDOC FILE START ===");

// Configuración de Stripe
define('STRIPE_MODE', 'live'); // test o live

define('STRIPE_TEST_PUBLIC_KEY', 'pk_test_51SBOq2GXJ2PkUN8kmrKUUjCLbvY3v8sAsgr6rNtg8zHyUZjB6pFrB7Vz3Gm0l2Wm7y5xVoMap2NY8utwgdJOogNQ000qBYIX5V');
define('STRIPE_TEST_SECRET_KEY', 'YOUR_STRIPE_TEST_KEY_HERE');

define('STRIPE_LIVE_PUBLIC_KEY', 'pk_live_51QHhtNGXGHYLV5CXu3P7PrAFezBnDuf0JsZzb2AxjSsV0okn4y19VOMIjW0NUOLpaFdI3CCRhiC4fvNBDDbPhiW100KkF6Uo2x');
define('STRIPE_LIVE_SECRET_KEY', 'YOUR_STRIPE_LIVE_KEY_HERE');

if (STRIPE_MODE === 'test') {
    $stripe_public_key = STRIPE_TEST_PUBLIC_KEY;
    $stripe_secret_key = STRIPE_TEST_SECRET_KEY;
} else {
    $stripe_public_key = STRIPE_LIVE_PUBLIC_KEY;
    $stripe_secret_key = STRIPE_LIVE_SECRET_KEY;
}

// Configuración del servicio
define('PRECIO_TOTAL', 94.95);
define('TASA_1', 19.03);
define('TASA_2', 7.62);
define('TRAMITFY_API_URL', 'https://46-202-128-35.sslip.io/api/herramientas/documentacion/webhook');

// Cargar Stripe library ANTES de las funciones
require_once(__DIR__ . '/vendor/autoload.php');

function rdoc_create_payment_intent() {
    global $stripe_secret_key;

    header('Content-Type: application/json');

    try {
        error_log('=== RECUPERAR DOCUMENTACION PAYMENT INTENT ===');
        error_log('STRIPE MODE: ' . STRIPE_MODE);
        error_log('Using Stripe key starting with: ' . substr($stripe_secret_key, 0, 25));

        \Stripe\Stripe::setApiKey($stripe_secret_key);

        $currentKey = \Stripe\Stripe::getApiKey();
        error_log('Stripe API Key confirmed: ' . substr($currentKey, 0, 25));

        $amount = PRECIO_TOTAL * 100;

        $paymentIntent = \Stripe\PaymentIntent::create([
            'amount' => $amount,
            'currency' => 'eur',
            'automatic_payment_methods' => [
                'enabled' => true,
            ],
            'description' => 'Recuperación de Documentación Extraviada',
            'metadata' => [
                'service' => 'Recuperar Documentación',
                'source' => 'tramitfy_web',
                'form' => 'recuperar_documentacion',
                'mode' => STRIPE_MODE
            ]
        ]);

        error_log('Payment Intent created: ' . $paymentIntent->id);

        echo json_encode([
            'clientSecret' => $paymentIntent->client_secret,
            'debug' => [
                'mode' => STRIPE_MODE,
                'keyUsed' => substr($stripe_secret_key, 0, 25) . '...',
                'keyConfirmed' => substr($currentKey, 0, 25) . '...',
                'paymentIntentId' => $paymentIntent->id
            ]
        ]);
    } catch (Exception $e) {
        error_log('Error creating payment intent: ' . $e->getMessage());
        echo json_encode(['error' => $e->getMessage()]);
    }

    wp_die();
}

function rdoc_send_to_tramitfy() {
    error_log("=== RDOC SEND TO TRAMITFY FUNCTION STARTED ===");
    error_log("🚀 RDOC: POST data: " . print_r($_POST, true));

    header('Content-Type: application/json');

    try {
        error_log("🚀 RDOC: Parseando formData...");
        $formData = json_decode(stripslashes($_POST['formData']), true);
        error_log("🚀 RDOC: formData parseado: " . print_r($formData, true));

        $uploadDir = wp_upload_dir();
        $baseUploadPath = $uploadDir['basedir'] . '/tramitfy-documentacion/';

        if (!file_exists($baseUploadPath)) {
            mkdir($baseUploadPath, 0755, true);
        }

        $timestamp = time();
        $uploadedFiles = [];

        // Guardar la firma como imagen
        $signatureFile = null;
        $signaturePath = null;
        if (isset($formData['signatureData']) && !empty($formData['signatureData'])) {
            $signatureData = $formData['signatureData'];
            $signatureData = str_replace('data:image/png;base64,', '', $signatureData);
            $signatureData = str_replace(' ', '+', $signatureData);
            $signatureDecoded = base64_decode($signatureData);

            $signatureFilename = $timestamp . '-signature.png';
            $signaturePath = $baseUploadPath . $signatureFilename;

            if (file_put_contents($signaturePath, $signatureDecoded)) {
                $signatureFile = [
                    'name' => 'firma.png',
                    'filename' => $signatureFilename,
                    'size' => filesize($signaturePath),
                    'path' => $signaturePath
                ];
                $uploadedFiles[] = $signatureFile;
            }
        }

        // Generar PDF de autorización con FPDF
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

        $pdf->Cell(40, 8, 'Nombre completo:', 0, 0);
        $pdf->Cell(0, 8, utf8_decode($formData['customerName']), 0, 1);

        $pdf->Cell(40, 8, 'DNI/NIE:', 0, 0);
        $pdf->Cell(0, 8, $formData['customerDNI'], 0, 1);
        $pdf->Ln(5);

        // Datos de la embarcación
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 10, utf8_decode('DATOS DE LA EMBARCACIÓN'), 0, 1, 'L');
        $pdf->SetFont('Arial', '', 11);

        $pdf->Cell(40, 8, 'Nombre:', 0, 0);
        $pdf->Cell(0, 8, utf8_decode($formData['vesselName'] ?? 'No especificado'), 0, 1);

        $pdf->Cell(40, 8, utf8_decode('Matrícula:'), 0, 0);
        $pdf->Cell(0, 8, $formData['vesselRegistration'] ?? 'No especificada', 0, 1);
        $pdf->Ln(5);

        // Texto de la autorización
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 10, utf8_decode('AUTORIZACIÓN'), 0, 1, 'L');
        $pdf->SetFont('Arial', '', 11);

        $customerName = $formData['customerName'];
        $customerDNI = $formData['customerDNI'];
        $vesselName = $formData['vesselName'] ?? 'la embarcación indicada';
        $vesselRegistration = $formData['vesselRegistration'] ?? 'matrícula indicada';

        $texto = "Por la presente, yo $customerName, con DNI/NIE $customerDNI, AUTORIZO a Tramitfy S.L. con CIF B55388557 a actuar como mi representante legal para la tramitación y gestión del procedimiento de recuperación de documentación extraviada para '$vesselName' con matrícula: $vesselRegistration ante las autoridades competentes.";
        $pdf->MultiCell(0, 6, utf8_decode($texto), 0, 'J');
        $pdf->Ln(3);

        $texto2 = "Doy conformidad para que Tramitfy S.L. pueda presentar y recoger cuanta documentación sea necesaria, subsanar defectos, pagar tasas y realizar cuantas actuaciones sean precisas para la correcta finalización del procedimiento.";
        $pdf->MultiCell(0, 6, utf8_decode($texto2), 0, 'J');
        $pdf->Ln(10);

        // Firma
        if ($signaturePath && file_exists($signaturePath)) {
            $pdf->Cell(0, 8, utf8_decode('Firma del autorizante:'), 0, 1);
            $pdf->Image($signaturePath, 30, $pdf->GetY(), 50, 30);
            $pdf->Ln(35);
        }

        // Pie de página legal
        $pdf->SetFont('Arial', 'I', 8);
        $pdf->MultiCell(0, 4, utf8_decode('En cumplimiento del Reglamento (UE) 2016/679 de Protección de Datos, le informamos que sus datos personales serán tratados por Tramitfy S.L. con la finalidad de gestionar su solicitud. Puede ejercer sus derechos de acceso, rectificación, supresión y portabilidad dirigiéndose a info@tramitfy.es'), 0, 'J');

        $authorizationPdfName = 'autorizacion_' . $timestamp . '.pdf';
        $authorizationPdfPath = $baseUploadPath . $authorizationPdfName;
        $pdf->Output('F', $authorizationPdfPath);

        error_log("✅ PDF de autorización generado: $authorizationPdfPath");

        // Procesar archivos adjuntos usando wp_handle_upload (igual que hoja-asiento)
        add_filter('upload_mimes', function($mimes) {
            $mimes['pdf'] = 'application/pdf';
            $mimes['jpg|jpeg'] = 'image/jpeg';
            $mimes['png'] = 'image/png';
            return $mimes;
        });

        error_log("=== RECUPERAR DOC: Procesando archivos ===");
        if (isset($_FILES['dniDocumento']) && !empty($_FILES['dniDocumento']['name'][0])) {
            $file_count = count($_FILES['dniDocumento']['name']);
            for ($i = 0; $i < $file_count; $i++) {
                if ($_FILES['dniDocumento']['error'][$i] === UPLOAD_ERR_OK) {
                    $file_array = array(
                        'name'     => $_FILES['dniDocumento']['name'][$i],
                        'type'     => $_FILES['dniDocumento']['type'][$i],
                        'tmp_name' => $_FILES['dniDocumento']['tmp_name'][$i],
                        'error'    => $_FILES['dniDocumento']['error'][$i],
                        'size'     => $_FILES['dniDocumento']['size'][$i]
                    );
                    $uploaded_file = wp_handle_upload($file_array, ['test_form' => false]);
                    error_log("Resultado wp_handle_upload: " . print_r($uploaded_file, true));

                    if (isset($uploaded_file['file'])) {
                        $uploadedFiles[] = [
                            'name' => $_FILES['dniDocumento']['name'][$i],
                            'filename' => basename($uploaded_file['file']),
                            'size' => $_FILES['dniDocumento']['size'][$i],
                            'path' => $uploaded_file['file']
                        ];
                        error_log("✅ Archivo agregado: {$_FILES['dniDocumento']['name'][$i]}");
                    } else {
                        error_log("❌ wp_handle_upload falló: " . (isset($uploaded_file['error']) ? $uploaded_file['error'] : 'sin error'));
                    }
                }
            }
        }

        $postData = [
            'customerName' => $formData['customerName'],
            'customerDNI' => $formData['customerDNI'],
            'customerEmail' => $formData['customerEmail'],
            'customerPhone' => $formData['customerPhone'],
            'vesselName' => $formData['vesselName'] ?? '',
            'vesselRegistration' => $formData['vesselRegistration'] ?? '',
            'totalPrice' => PRECIO_TOTAL,
            'tasa1' => TASA_1,
            'tasa2' => TASA_2,
            'consentTerms' => $formData['consentTerms'] ?? false,
            'hasSignature' => !empty($signatureFile),
            'paymentIntentId' => $formData['paymentIntentId'] ?? '',
            'timestamp' => date('c')
        ];

        // Preparar datos con CURLFile (multipart automático)
        $form_data = array();

        // Agregar campos como strings
        foreach ($postData as $key => $value) {
            $form_data[$key] = (string)$value;
        }

        // Agregar PDF de autorización
        if (file_exists($authorizationPdfPath)) {
            $form_data['autorizacion_pdf'] = new CURLFile($authorizationPdfPath, 'application/pdf', $authorizationPdfName);
            error_log("✅ PDF autorización agregado: $authorizationPdfName");
        } else {
            error_log("❌ PDF autorización NO existe: $authorizationPdfPath");
        }

        // Agregar archivos con CURLFile (mantener categorización)
        $fileIndex = 0;
        foreach ($uploadedFiles as $file) {
            if (file_exists($file['path'])) {
                // Usar nombre específico para firma, dni_documento para el resto
                if ($file['name'] === 'firma.png') {
                    $form_data['firma'] = new CURLFile($file['path'], 'image/png', $file['filename']);
                    error_log("✅ Firma agregada: {$file['filename']}");
                } else {
                    $form_data['dni_documento'] = new CURLFile($file['path'], mime_content_type($file['path']), $file['filename']);
                    error_log("✅ DNI documento agregado: {$file['filename']}");
                    $fileIndex++;
                }
            } else {
                error_log("❌ Archivo NO existe: {$file['path']}");
            }
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, TRAMITFY_API_URL);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $form_data); // Array directo con CURLFile
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        error_log("🔄 RDOC: Ejecutando curl al webhook...");
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        error_log("🔄 RDOC: Curl completado. HTTP Code: $httpCode");
        if ($curlError) {
            error_log("❌ RDOC: Curl error: $curlError");
        }
        error_log("🔄 RDOC: Response length: " . strlen($response));
        error_log("🔄 RDOC: Response: " . substr($response, 0, 500));

        $apiResponse = json_decode($response, true);
        $tramiteId = $apiResponse['tramiteId'] ?? null;

        error_log("=== RECUPERAR DOC: Datos enviados al API correctamente ===");
        error_log("TramiteId devuelto: $tramiteId");
        error_log("HTTP Code: $httpCode");

        echo json_encode([
            'success' => true,
            'message' => 'Datos enviados correctamente',
            'tramiteId' => $tramiteId,
            'apiResponse' => $apiResponse,
            'httpCode' => $httpCode
        ]);

    } catch (Exception $e) {
        error_log('Error in rdoc_send_to_tramitfy: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }

    exit;
}

function rdoc_send_confirmation_emails($formData, $uploadedFiles, $tramiteId = null, $tramiteReference = null) {
    error_log("📧 === FUNCIÓN EMAILS INICIADA ===");
    error_log("📧 CustomerEmail: " . ($formData['customerEmail'] ?? 'NO DEFINIDO'));
    error_log("📧 TramiteId: " . ($tramiteId ?? 'NULL'));

    $customerEmail = $formData['customerEmail'];
    $customerName = $formData['customerName'];
    $vesselName = $formData['vesselName'] ?? 'No especificado';
    $vesselRegistration = $formData['vesselRegistration'] ?? 'No especificada';

    // Headers con From de Tramitfy (WordPress SMTP se encarga del envío)
    $headers = [
        'Content-Type: text/html; charset=UTF-8',
        'From: Tramitfy <info@tramitfy.es>'
    ];

    $totalTasas = TASA_1 + TASA_2;
    $honorariosBrutos = PRECIO_TOTAL - $totalTasas;
    $honorariosNetos = round($honorariosBrutos / 1.21, 2);
    $iva = round($honorariosBrutos - $honorariosNetos, 2);

    $trackingUrl = $tramiteId ? 'https://46-202-128-35.sslip.io/seguimiento/' . $tramiteId : '#';
    $tramiteDisplayId = $tramiteReference ?? 'En proceso';

    // ============================================
    // EMAIL AL CLIENTE - Diseño mejorado y profesional
    // ============================================
    $customerSubject = '✅ Solicitud Confirmada - Recuperación de Documentación | ' . $tramiteDisplayId;
    $customerMessage = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    </head>
    <body style='margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif; background-color: #f0f4f8;'>
        <table width='100%' cellpadding='0' cellspacing='0' style='background-color: #f0f4f8; padding: 40px 20px;'>
            <tr>
                <td align='center'>
                    <table width='600' cellpadding='0' cellspacing='0' style='background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 8px 24px rgba(0,0,0,0.12);'>

                        <!-- Header Tramitfy -->
                        <tr>
                            <td style='background: linear-gradient(135deg, #016d86 0%, #014d61 100%); padding: 45px 40px; text-align: center;'>
                                <h1 style='margin: 0; color: #ffffff; font-size: 32px; font-weight: 700; letter-spacing: 1px;'>TRAMITFY</h1>
                                <p style='margin: 10px 0 0; color: rgba(255,255,255,0.95); font-size: 15px; font-weight: 400;'>Gestión Profesional de Trámites Marítimos</p>
                            </td>
                        </tr>

                        <!-- Confirmación Success -->
                        <tr>
                            <td style='padding: 45px 40px 35px;'>
                                <div style='background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%); border-left: 5px solid #4caf50; padding: 20px 24px; border-radius: 8px; margin-bottom: 35px; text-align: center;'>
                                    <p style='margin: 0; color: #1b5e20; font-size: 18px; font-weight: 700;'>✅ ¡Solicitud Recibida y Confirmada!</p>
                                    <p style='margin: 8px 0 0; color: #2e7d32; font-size: 14px; font-weight: 500;'>Su pago ha sido procesado correctamente</p>
                                </div>

                                <p style='margin: 0 0 12px; color: #333; font-size: 16px; line-height: 1.6;'>
                                    Hola <strong>{$customerName}</strong>,
                                </p>
                                <p style='margin: 0 0 24px; color: #555; font-size: 15px; line-height: 1.8;'>
                                    Hemos recibido su solicitud de <strong>recuperación de documentación extraviada</strong> y el pago se ha procesado exitosamente. Nuestro equipo ya está trabajando en su trámite.
                                </p>

                                <!-- Número de Trámite CTA Principal -->
                                <div style='background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); border-radius: 12px; padding: 28px 32px; margin-bottom: 32px; text-align: center; border: 2px solid #1976d2;'>
                                    <p style='margin: 0 0 8px; color: #0d47a1; font-size: 13px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;'>
                                        Su número de trámite
                                    </p>
                                    <p style='margin: 0 0 20px; color: #01579b; font-size: 26px; font-weight: 700; font-family: monospace;'>
                                        {$tramiteDisplayId}
                                    </p>
                                    <a href='{$trackingUrl}' style='display: inline-block; background: linear-gradient(135deg, #1976d2 0%, #1565c0 100%); color: white; padding: 16px 36px; text-decoration: none; border-radius: 8px; font-weight: 700; font-size: 15px; box-shadow: 0 4px 12px rgba(25,118,210,0.4); transition: all 0.3s;'>
                                        🔍 CONSULTAR ESTADO EN TIEMPO REAL
                                    </a>
                                    <p style='margin: 18px 0 0; color: #546e7a; font-size: 13px; line-height: 1.6;'>
                                        Le enviaremos notificaciones automáticas por email<br/>con cada actualización del trámite.
                                    </p>
                                </div>

                                <!-- Próximos Pasos Timeline -->
                                <div style='background-color: #fafafa; border-radius: 10px; padding: 28px 32px; margin-bottom: 28px; border: 1px solid #e0e0e0;'>
                                    <h3 style='margin: 0 0 20px; color: #016d86; font-size: 17px; font-weight: 700; text-align: center; text-transform: uppercase; letter-spacing: 0.5px;'>📅 Próximos Pasos</h3>

                                    <div style='position: relative; padding-left: 32px; border-left: 3px solid #016d86; margin-left: 12px;'>
                                        <div style='margin-bottom: 20px;'>
                                            <div style='position: absolute; left: -11px; width: 18px; height: 18px; background: #4caf50; border-radius: 50%; border: 3px solid white; box-shadow: 0 0 0 2px #4caf50;'></div>
                                            <p style='margin: 0 0 4px; color: #4caf50; font-size: 14px; font-weight: 700;'>✓ COMPLETADO</p>
                                            <p style='margin: 0; color: #666; font-size: 13px; line-height: 1.6;'>Solicitud recibida y pago confirmado</p>
                                        </div>

                                        <div style='margin-bottom: 20px;'>
                                            <div style='position: absolute; left: -11px; width: 18px; height: 18px; background: #ff9800; border-radius: 50%; border: 3px solid white; box-shadow: 0 0 0 2px #ff9800;'></div>
                                            <p style='margin: 0 0 4px; color: #e65100; font-size: 14px; font-weight: 700;'>⏳ PRÓXIMO PASO (24-48h)</p>
                                            <p style='margin: 0; color: #666; font-size: 13px; line-height: 1.6;'>Revisión de documentación y preparación de expediente</p>
                                        </div>

                                        <div style='margin-bottom: 20px;'>
                                            <div style='position: absolute; left: -11px; width: 18px; height: 18px; background: #90a4ae; border-radius: 50%; border: 3px solid white; box-shadow: 0 0 0 2px #90a4ae;'></div>
                                            <p style='margin: 0 0 4px; color: #455a64; font-size: 14px; font-weight: 700;'>⏹ PENDIENTE (5-7 días)</p>
                                            <p style='margin: 0; color: #666; font-size: 13px; line-height: 1.6;'>Tramitación ante Capitanía Marítima</p>
                                        </div>

                                        <div>
                                            <div style='position: absolute; left: -11px; width: 18px; height: 18px; background: #90a4ae; border-radius: 50%; border: 3px solid white; box-shadow: 0 0 0 2px #90a4ae;'></div>
                                            <p style='margin: 0 0 4px; color: #455a64; font-size: 14px; font-weight: 700;'>⏹ PENDIENTE (7-10 días)</p>
                                            <p style='margin: 0; color: #666; font-size: 13px; line-height: 1.6;'>Envío de documentación oficial completa</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Embarcación Info -->
                                <table width='100%' cellpadding='0' cellspacing='0' style='background: linear-gradient(135deg, #f5f5f5 0%, #eeeeee 100%); border-radius: 10px; margin-bottom: 28px; overflow: hidden; border: 1px solid #e0e0e0;'>
                                    <tr>
                                        <td style='padding: 24px 28px;'>
                                            <h3 style='margin: 0 0 18px; color: #016d86; font-size: 16px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;'>⛵ Embarcación</h3>
                                            <table width='100%' cellpadding='8' cellspacing='0'>
                                                <tr>
                                                    <td style='color: #666; font-size: 14px; padding: 8px 0; width: 35%; font-weight: 600;'>Nombre:</td>
                                                    <td style='color: #333; font-size: 15px; padding: 8px 0; font-weight: 700;'>{$vesselName}</td>
                                                </tr>
                                                <tr>
                                                    <td style='color: #666; font-size: 14px; padding: 8px 0; font-weight: 600;'>Matrícula:</td>
                                                    <td style='color: #333; font-size: 15px; padding: 8px 0; font-weight: 700;'>{$vesselRegistration}</td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                </table>

                                <!-- Documentación a Recibir -->
                                <div style='background-color: #fff3e0; border-radius: 10px; padding: 24px 28px; margin-bottom: 28px; border: 1px solid #ffb74d;'>
                                    <h3 style='margin: 0 0 18px; color: #e65100; font-size: 16px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;'>📄 Documentación a Recibir</h3>
                                    <table width='100%' cellpadding='0' cellspacing='0'>
                                        <tr>
                                            <td style='padding: 10px 0; border-bottom: 1px solid rgba(255,152,0,0.2);'>
                                                <span style='color: #4caf50; font-size: 18px; font-weight: bold; margin-right: 10px;'>✓</span>
                                                <span style='color: #333; font-size: 14px; font-weight: 600;'>Hoja de asiento registral completa</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style='padding: 10px 0; border-bottom: 1px solid rgba(255,152,0,0.2);'>
                                                <span style='color: #4caf50; font-size: 18px; font-weight: bold; margin-right: 10px;'>✓</span>
                                                <span style='color: #333; font-size: 14px; font-weight: 600;'>Certificado de registro marítimo renovado</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style='padding: 10px 0;'>
                                                <span style='color: #4caf50; font-size: 18px; font-weight: bold; margin-right: 10px;'>✓</span>
                                                <span style='color: #333; font-size: 14px; font-weight: 600;'>Permiso de navegación renovado y vigente</span>
                                            </td>
                                        </tr>
                                    </table>
                                </div>

                                <!-- Resumen Pago -->
                                <table width='100%' cellpadding='0' cellspacing='0' style='background: linear-gradient(135deg, #016d86 0%, #014d61 100%); border-radius: 10px; margin-bottom: 28px;'>
                                    <tr>
                                        <td style='padding: 24px 28px;'>
                                            <table width='100%' cellpadding='6' cellspacing='0'>
                                                <tr>
                                                    <td style='color: rgba(255,255,255,0.9); font-size: 15px; padding: 4px 0;'>Importe Total:</td>
                                                    <td align='right' style='color: #ffffff; font-size: 26px; font-weight: 700; padding: 4px 0;'>" . number_format(PRECIO_TOTAL, 2) . " €</td>
                                                </tr>
                                                <tr>
                                                    <td colspan='2' style='padding-top: 8px;'>
                                                        <div style='background: rgba(255,255,255,0.2); padding: 10px 14px; border-radius: 6px; margin-top: 6px;'>
                                                            <p style='margin: 0; color: rgba(255,255,255,0.95); font-size: 12px; line-height: 1.5;'>
                                                                ✓ Pago procesado correctamente<br/>
                                                                ✓ Todas las tasas oficiales incluidas<br/>
                                                                ✓ Gestión completa sin cargos ocultos
                                                            </p>
                                                        </div>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                </table>

                                <!-- Contacto y Soporte -->
                                <div style='background-color: #f5f5f5; border-radius: 10px; padding: 20px 24px; text-align: center; border: 1px solid #e0e0e0;'>
                                    <p style='margin: 0 0 12px; color: #333; font-size: 15px; font-weight: 700;'>¿Necesita ayuda?</p>
                                    <p style='margin: 0 0 16px; color: #666; font-size: 13px; line-height: 1.6;'>
                                        Nuestro equipo está disponible para resolver cualquier duda<br/>
                                        sobre su trámite.
                                    </p>
                                    <p style='margin: 0; color: #016d86; font-size: 14px; font-weight: 600;'>
                                        📧 info@tramitfy.es<br/>
                                        📱 WhatsApp: +34 XXX XXX XXX
                                    </p>
                                </div>

                            </td>
                        </tr>

                        <!-- Footer -->
                        <tr>
                            <td style='background-color: #263238; padding: 35px 40px; text-align: center;'>
                                <p style='margin: 0 0 10px; color: rgba(255,255,255,0.9); font-size: 16px; font-weight: 700;'>
                                    TRAMITFY
                                </p>
                                <p style='margin: 0 0 16px; color: rgba(255,255,255,0.7); font-size: 13px;'>
                                    Gestión Profesional de Trámites Marítimos
                                </p>
                                <p style='margin: 0; color: rgba(255,255,255,0.5); font-size: 11px; line-height: 1.6;'>
                                    Este es un email automático. Por favor, no responda directamente.<br/>
                                    Para consultas, utilice los canales de contacto indicados arriba.
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

    wp_mail($customerEmail, $customerSubject, $customerMessage, $headers);

    // ============================================
    // EMAIL AL ADMINISTRADOR
    // ============================================
    $adminEmail = 'info@tramitfy.es';
    $adminSubject = '🔔 Nueva Solicitud - ' . $tramiteDisplayId . ' - Recuperar Documentación';
    $adminMessage = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
    </head>
    <body style='margin: 0; padding: 20px; font-family: Arial, sans-serif; background-color: #f5f5f5;'>
        <div style='max-width: 700px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);'>

            <!-- Header Admin -->
            <div style='background: linear-gradient(135deg, #d32f2f 0%, #b71c1c 100%); padding: 25px 30px; color: white;'>
                <h2 style='margin: 0; font-size: 22px; font-weight: 600;'>🔔 NUEVA SOLICITUD</h2>
                <p style='margin: 6px 0 0; font-size: 14px; opacity: 0.95;'>Recuperación de Documentación Extraviada</p>
                <p style='margin: 10px 0 0; font-size: 16px; font-weight: 700; background: rgba(255,255,255,0.2); padding: 8px 12px; border-radius: 4px; display: inline-block;'>📋 {$tramiteDisplayId}</p>
            </div>

            <div style='padding: 30px;'>

                <!-- Link de Seguimiento -->
                <div style='margin-bottom: 25px; background-color: #e3f2fd; padding: 16px 20px; border-radius: 6px; text-align: center;'>
                    <a href='{$trackingUrl}' style='display: inline-block; background: linear-gradient(135deg, #1976d2 0%, #1565c0 100%); color: white; padding: 10px 24px; text-decoration: none; border-radius: 5px; font-weight: 600; font-size: 14px; box-shadow: 0 3px 8px rgba(25,118,210,0.3);'>
                        🔍 Ver Detalle Completo del Trámite
                    </a>
                </div>

                <!-- Datos del Cliente -->
                <div style='margin-bottom: 25px;'>
                    <h3 style='margin: 0 0 15px; color: #d32f2f; font-size: 16px; border-bottom: 2px solid #d32f2f; padding-bottom: 8px;'>👤 DATOS DEL CLIENTE</h3>
                    <table width='100%' cellpadding='6' cellspacing='0' style='font-size: 14px;'>
                        <tr>
                            <td style='color: #666; width: 35%;'>Nombre completo:</td>
                            <td style='color: #333; font-weight: 600;'>{$customerName}</td>
                        </tr>
                        <tr>
                            <td style='color: #666;'>DNI/NIE:</td>
                            <td style='color: #333; font-weight: 600;'>{$formData['customerDNI']}</td>
                        </tr>
                        <tr>
                            <td style='color: #666;'>Email:</td>
                            <td style='color: #0066cc; font-weight: 600;'>{$customerEmail}</td>
                        </tr>
                        <tr>
                            <td style='color: #666;'>Teléfono:</td>
                            <td style='color: #333; font-weight: 600;'>{$formData['customerPhone']}</td>
                        </tr>
                    </table>
                </div>

                <!-- Datos de la Embarcación -->
                <div style='margin-bottom: 25px; background-color: #e3f2fd; padding: 18px; border-radius: 6px;'>
                    <h3 style='margin: 0 0 15px; color: #1565c0; font-size: 16px;'>⚓ EMBARCACIÓN</h3>
                    <table width='100%' cellpadding='5' cellspacing='0' style='font-size: 14px;'>
                        <tr>
                            <td style='color: #555; width: 35%;'>Nombre:</td>
                            <td style='color: #333; font-weight: 600;'>{$vesselName}</td>
                        </tr>
                        <tr>
                            <td style='color: #555;'>Matrícula:</td>
                            <td style='color: #333; font-weight: 600;'>{$vesselRegistration}</td>
                        </tr>
                    </table>
                </div>

                <!-- Desglose Económico -->
                <div style='margin-bottom: 25px; background-color: #fff8e1; padding: 18px; border-radius: 6px; border-left: 4px solid #ffa000;'>
                    <h3 style='margin: 0 0 15px; color: #f57f17; font-size: 16px;'>💰 CONTABILIDAD</h3>
                    <table width='100%' cellpadding='6' cellspacing='0' style='font-size: 14px;'>
                        <tr>
                            <td style='color: #666;'>Precio total cobrado:</td>
                            <td align='right' style='color: #333; font-weight: 700; font-size: 16px;'>" . number_format(PRECIO_TOTAL, 2) . " €</td>
                        </tr>
                        <tr style='border-top: 1px solid #ffe082;'>
                            <td colspan='2' style='padding-top: 12px; padding-bottom: 6px; color: #888; font-size: 13px; font-weight: 600;'>DESGLOSE:</td>
                        </tr>
                        <tr>
                            <td style='color: #666; padding-left: 15px;'>Tasa 1:</td>
                            <td align='right' style='color: #666;'>" . number_format(TASA_1, 2) . " €</td>
                        </tr>
                        <tr>
                            <td style='color: #666; padding-left: 15px;'>Tasa 2:</td>
                            <td align='right' style='color: #666;'>" . number_format(TASA_2, 2) . " €</td>
                        </tr>
                        <tr>
                            <td style='color: #666; padding-left: 15px; border-bottom: 1px solid #ffe082; padding-bottom: 8px;'>Total tasas:</td>
                            <td align='right' style='color: #666; border-bottom: 1px solid #ffe082; padding-bottom: 8px;'>- " . number_format($totalTasas, 2) . " €</td>
                        </tr>
                        <tr>
                            <td style='color: #f57f17; font-weight: 700; padding-top: 8px;'>Honorarios brutos (con IVA):</td>
                            <td align='right' style='color: #f57f17; font-weight: 700; font-size: 16px; padding-top: 8px;'>" . number_format($honorariosBrutos, 2) . " €</td>
                        </tr>
                        <tr>
                            <td style='color: #666; padding-left: 15px; font-size: 13px;'>IVA (21%):</td>
                            <td align='right' style='color: #666; font-size: 13px;'>- " . number_format($iva, 2) . " €</td>
                        </tr>
                        <tr style='background-color: #fff3cd;'>
                            <td style='color: #d84315; font-weight: 700; padding: 8px; padding-left: 15px;'>Honorarios netos (sin IVA):</td>
                            <td align='right' style='color: #d84315; font-weight: 700; font-size: 17px; padding: 8px;'>" . number_format($honorariosNetos, 2) . " €</td>
                        </tr>
                    </table>
                </div>

                <!-- Información de Pago -->
                <div style='margin-bottom: 25px;'>
                    <h3 style='margin: 0 0 15px; color: #333; font-size: 16px;'>💳 PAGO STRIPE</h3>
                    <table width='100%' cellpadding='5' cellspacing='0' style='font-size: 13px; background-color: #f9f9f9; padding: 12px; border-radius: 4px;'>
                        <tr>
                            <td style='color: #666;'>Payment Intent ID:</td>
                            <td style='color: #333; font-family: monospace; font-size: 12px;'>{$formData['paymentIntentId']}</td>
                        </tr>
                        <tr>
                            <td style='color: #666;'>Modo Stripe:</td>
                            <td style='color: #333; font-weight: 600;'>" . STRIPE_MODE . "</td>
                        </tr>
                    </table>
                </div>

                <!-- Documentos Adjuntos -->
                <div style='margin-bottom: 25px;'>
                    <h3 style='margin: 0 0 15px; color: #333; font-size: 16px;'>📎 DOCUMENTOS ADJUNTOS (" . count($uploadedFiles) . ")</h3>
                    <ul style='margin: 0; padding: 0; list-style: none;'>";

    foreach ($uploadedFiles as $file) {
        $fileIcon = strpos($file['name'], 'signature') !== false ? '✍️' : '📄';
        $adminMessage .= "
                        <li style='padding: 8px 12px; margin-bottom: 6px; background-color: #f5f5f5; border-radius: 4px; font-size: 13px;'>
                            {$fileIcon} <strong>{$file['name']}</strong> <span style='color: #999;'>(" . round($file['size']/1024, 2) . " KB)</span>
                        </li>";
    }

    $adminMessage .= "
                    </ul>
                </div>

                <!-- Botón Dashboard -->
                <div style='text-align: center; margin-top: 30px;'>
                    <a href='https://46-202-128-35.sslip.io' style='display: inline-block; background: linear-gradient(135deg, #0066cc 0%, #004a99 100%); color: white; padding: 14px 32px; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 15px; box-shadow: 0 4px 10px rgba(0,102,204,0.3);'>
                        🖥 Ver en Dashboard TRAMITFY
                    </a>
                </div>

            </div>

            <!-- Footer Admin -->
            <div style='background-color: #f5f5f5; padding: 20px; text-align: center; border-top: 1px solid #e0e0e0;'>
                <p style='margin: 0; color: #999; font-size: 12px;'>
                    Email automático generado por TRAMITFY<br>
                    Fecha: " . date('d/m/Y H:i:s') . "
                </p>
            </div>

        </div>
    </body>
    </html>
    ";

    // Enviar email al administrador
    wp_mail($adminEmail, $adminSubject, $adminMessage, $headers);
}

if (isset($_POST['action'])) {
    if ($_POST['action'] === 'rdoc_create_payment_intent') {
        rdoc_create_payment_intent();
    } elseif ($_POST['action'] === 'rdoc_send_to_tramitfy') {
        rdoc_send_to_tramitfy();
    }
}

function recuperar_documentacion_form_shortcode() {
    global $stripe_public_key;

    $current_user = wp_get_current_user();
    $is_admin = in_array('administrator', $current_user->roles);

    ob_start();
    ?>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;600;700&display=swap');

        :root {
            --primary: 1, 109, 134;
            --primary-dark: 0, 86, 106;
            --primary-light: 0, 125, 156;
            --secondary: 0, 123, 255;
            --success: 40, 167, 69;
            --warning: 243, 156, 18;
            --error: 231, 76, 60;
            --neutral-50: 248, 249, 250;
            --neutral-100: 241, 243, 244;
            --neutral-200: 233, 236, 239;
            --neutral-300: 222, 226, 230;
            --neutral-500: 173, 181, 189;
            --neutral-600: 108, 117, 125;
            --neutral-700: 73, 80, 87;
            --neutral-800: 52, 58, 64;
            --neutral-900: 33, 37, 41;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Roboto', -apple-system, sans-serif;
            background: linear-gradient(135deg, rgb(var(--neutral-50)) 0%, rgb(var(--neutral-100)) 100%);
            color: rgb(var(--neutral-800));
            line-height: 1.5;
        }

        /* CONTENEDOR PRINCIPAL ÚNICO */
        .rdoc-container {
            max-width: 1300px;
            margin: 25px auto;
            background: white;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
            display: grid;
            grid-template-columns: 400px 1fr;
            min-height: 700px;
        }

        /* SIDEBAR INFORMATIVO */
        .rdoc-sidebar {
            background: linear-gradient(180deg, rgb(var(--primary)) 0%, rgb(var(--primary-dark)) 100%);
            color: white;
            padding: 30px 25px;
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .rdoc-logo {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 3px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .rdoc-logo i {
            font-size: 32px;
        }

        .rdoc-headline {
            font-size: 23px;
            font-weight: 700;
            line-height: 1.3;
            margin-bottom: 10px;
        }

        .rdoc-subheadline {
            font-size: 14px;
            opacity: 0.9;
            line-height: 1.5;
        }

        .rdoc-price-box {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            padding: 18px;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.25);
        }

        .rdoc-price-label {
            font-size: 13px;
            opacity: 0.8;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }

        .rdoc-price-amount {
            font-size: 40px;
            font-weight: 700;
            margin: 5px 0;
        }

        .rdoc-price-detail {
            font-size: 13px;
            opacity: 0.85;
        }

        .rdoc-benefits {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .rdoc-benefit {
            display: flex;
            align-items: start;
            gap: 10px;
            font-size: 13px;
        }

        .rdoc-benefit i {
            font-size: 16px;
            color: rgb(var(--success));
            background: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .rdoc-reviews {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-top: 20px;
        }

        .rdoc-review {
            background: rgba(255, 255, 255, 0.1);
            padding: 15px;
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .rdoc-stars {
            color: #ffd700;
            font-size: 14px;
            margin-bottom: 8px;
        }

        .rdoc-review-text {
            color: rgba(255, 255, 255, 0.95);
            font-size: 13px;
            line-height: 1.5;
            margin: 0 0 8px 0;
            font-style: italic;
        }

        .rdoc-review-author {
            color: rgba(255, 255, 255, 0.7);
            font-size: 12px;
            margin: 0;
            text-align: right;
        }

        /* FORMULARIO PRINCIPAL */
        .rdoc-form-area {
            padding: 20px 30px;
        }

        .rdoc-form-header {
            margin-bottom: 12px;
        }

        .rdoc-form-title {
            font-size: 22px;
            font-weight: 700;
            color: rgb(var(--neutral-900));
            margin-bottom: 3px;
        }

        .rdoc-form-subtitle {
            font-size: 14px;
            color: rgb(var(--neutral-600));
        }

        /* ADMIN PANEL */
        .rdoc-admin-panel {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
            color: white;
            padding: 12px 15px;
            border-radius: 10px;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .rdoc-admin-btn {
            background: white;
            color: #ff6b6b;
            border: none;
            padding: 10px 18px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s;
        }

        .rdoc-admin-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        /* FORMULARIO */
        .rdoc-form {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .rdoc-section {
            background: rgb(var(--neutral-50));
            padding: 14px;
            border-radius: 10px;
            border: 1px solid rgb(var(--neutral-200));
        }


        .rdoc-section-title {
            font-size: 15px;
            font-weight: 600;
            color: rgb(var(--neutral-900));
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .rdoc-section-title i {
            color: rgb(var(--primary));
            font-size: 18px;
        }

        .rdoc-form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .rdoc-two-column-section {
            display: grid;
            grid-template-columns: 1.3fr 1fr;
            gap: 12px;
        }

        .rdoc-column {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .rdoc-form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .rdoc-form-group.full {
            grid-column: span 2;
        }

        .rdoc-label {
            font-size: 14px;
            font-weight: 500;
            color: rgb(var(--neutral-700));
        }

        .rdoc-required {
            color: rgb(var(--error));
        }

        .rdoc-input {
            padding: 8px 10px;
            border: 2px solid rgb(var(--neutral-300));
            border-radius: 6px;
            font-size: 13px;
            font-family: inherit;
            background: white;
            transition: all 0.2s;
        }

        .rdoc-input:focus {
            outline: none;
            border-color: rgb(var(--primary));
            box-shadow: 0 0 0 3px rgba(var(--primary), 0.1);
        }

        .rdoc-input.error {
            border-color: rgb(var(--error));
            animation: shake 0.3s;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        /* UPLOAD COMPACTO */
        .rdoc-upload-area {
            border: 2px dashed rgb(var(--primary));
            border-radius: 8px;
            padding: 12px;
            text-align: center;
            background: rgba(var(--primary), 0.03);
            cursor: pointer;
            transition: all 0.2s;
        }

        .rdoc-upload-area:hover {
            background: rgba(var(--primary), 0.06);
            border-color: rgb(var(--primary-dark));
        }

        .rdoc-upload-area i {
            font-size: 24px;
            color: rgb(var(--primary));
            margin-bottom: 4px;
        }

        .rdoc-upload-text {
            font-size: 14px;
            color: rgb(var(--neutral-600));
        }

        .rdoc-file-input {
            display: none;
        }

        .rdoc-file-list {
            margin-top: 10px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            min-height: 20px;
        }

        .rdoc-file-list:empty::after {
            content: '';
            display: block;
            width: 100%;
            height: 2px;
        }

        .rdoc-file-item {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 10px;
            background: white;
            border: 1px solid rgb(var(--neutral-300));
            border-radius: 8px;
            font-size: 12px;
            max-width: 250px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .rdoc-file-icon {
            color: rgb(var(--primary));
            font-size: 14px;
            flex-shrink: 0;
        }

        .rdoc-file-name {
            flex: 1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-weight: 500;
        }

        .rdoc-file-size {
            color: rgb(var(--neutral-500));
            font-size: 11px;
            flex-shrink: 0;
        }

        .rdoc-file-remove {
            background: none;
            border: none;
            color: rgb(var(--neutral-400));
            cursor: pointer;
            padding: 2px;
            font-size: 14px;
            line-height: 1;
            transition: all 0.2s;
            flex-shrink: 0;
        }

        .rdoc-file-remove:hover {
            color: rgb(var(--error));
            transform: scale(1.15);
        }

        /* CHECKBOXES COMPACTOS */
        .rdoc-checkboxes {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .rdoc-checkbox-wrapper {
            display: flex;
            align-items: start;
            gap: 10px;
        }

        .rdoc-checkbox {
            width: 20px;
            height: 20px;
            cursor: pointer;
            margin-top: 2px;
            accent-color: rgb(var(--primary));
        }

        .rdoc-checkbox-label {
            font-size: 13px;
            line-height: 1.5;
            color: rgb(var(--neutral-700));
        }

        .rdoc-checkbox-label a {
            color: rgb(var(--primary));
            text-decoration: none;
            font-weight: 500;
        }

        .rdoc-checkbox-label a:hover {
            text-decoration: underline;
        }

        /* NAVEGACIÓN DE PÁGINAS */
        .rdoc-page {
            display: none;
        }

        .rdoc-page.active {
            display: block;
        }

        .rdoc-next-btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, rgb(var(--primary)) 0%, rgb(var(--primary-dark)) 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .rdoc-next-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(var(--primary), 0.3);
        }

        .rdoc-back-btn {
            background: none;
            border: 1px solid rgb(var(--neutral-300));
            color: rgb(var(--neutral-700));
            padding: 10px 18px;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 12px;
        }

        .rdoc-back-btn:hover {
            background: rgb(var(--neutral-100));
        }

        .rdoc-back-btn-minimal {
            background: none;
            border: none;
            color: rgb(var(--neutral-600));
            padding: 8px;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            margin-bottom: 10px;
            border-radius: 50%;
            width: 36px;
            height: 36px;
        }

        .rdoc-back-btn-minimal:hover {
            background: rgba(var(--neutral-200), 0.5);
            color: rgb(var(--primary));
            transform: translateX(-2px);
        }

        /* CANVAS FIRMA */
        .rdoc-signature-box {
            border: 2px solid rgb(var(--neutral-300));
            border-radius: 10px;
            padding: 10px;
            background: white;
        }

        .rdoc-signature-canvas {
            width: 100%;
            height: 120px;
            cursor: crosshair;
            border-radius: 6px;
            background: rgb(var(--neutral-50));
        }

        /* FIRMA ACTIONS */
        .rdoc-signature-clear {
            margin-top: 8px;
            background: none;
            border: 1px solid rgb(var(--neutral-300));
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 13px;
            cursor: pointer;
            color: rgb(var(--neutral-700));
            transition: all 0.2s;
        }

        .rdoc-signature-clear:hover {
            background: rgb(var(--neutral-100));
        }

        /* RESUMEN DE PRECIO */
        .rdoc-summary {
            background: rgba(var(--primary), 0.05);
            border: 1px solid rgba(var(--primary), 0.15);
            border-radius: 8px;
            padding: 0;
        }

        .rdoc-summary-row {
            display: flex;
            justify-content: space-between;
            padding: 14px 16px;
            font-size: 15px;
            border-bottom: 1px solid rgba(var(--primary), 0.1);
        }

        .rdoc-summary-row:last-child {
            border: none;
        }

        /* STRIPE */
        .rdoc-stripe-wrapper {
            padding: 18px;
            border: 2px solid rgba(var(--neutral-300), 1);
            border-radius: 10px;
            background: white;
            min-height: 60px;
        }

        .rdoc-stripe-wrapper:focus-within {
            border-color: rgb(var(--primary));
            box-shadow: 0 0 0 4px rgba(var(--primary), 0.12);
        }

        /* BOTÓN DE PAGO */
        .rdoc-submit-btn {
            width: 100%;
            padding: 20px;
            background: linear-gradient(135deg, rgb(var(--primary)) 0%, rgb(var(--primary-dark)) 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 18px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 6px 20px rgba(var(--primary), 0.35);
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-top: 32px;
        }

        .rdoc-submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(var(--primary), 0.4);
        }

        .rdoc-submit-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .rdoc-submit-btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }

        .rdoc-submit-btn:hover::before {
            width: 300px;
            height: 300px;
        }

        .rdoc-security-note {
            text-align: center;
            font-size: 12px;
            color: rgb(var(--neutral-500));
            margin-top: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        /* PAYMENT PAGE STYLES */
        .rdoc-summary-total {
            background: rgba(var(--primary), 0.1);
            border-radius: 0;
            padding: 14px 16px !important;
            margin-top: 0;
            font-size: 18px;
            font-weight: 700;
            color: rgb(var(--primary));
        }

        .rdoc-payment-wrapper {
            background: white;
            border: 2px solid rgba(var(--primary), 0.15);
            border-radius: 12px;
            padding: 28px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
        }

        .rdoc-payment-header {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 18px;
            font-weight: 700;
            color: rgb(var(--neutral-900));
            margin-bottom: 24px;
            padding-bottom: 18px;
            border-bottom: 2px solid rgba(var(--primary), 0.15);
        }

        .rdoc-payment-header i {
            font-size: 22px;
            color: rgb(var(--primary));
        }

        .rdoc-stripe-loading {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            padding: 32px;
            color: rgb(var(--primary));
            font-size: 14px;
            font-weight: 500;
            background: rgba(var(--primary), 0.05);
            border-radius: 10px;
            margin-bottom: 18px;
        }

        .rdoc-stripe-loading i {
            font-size: 22px;
        }

        .rdoc-card-errors {
            color: rgb(var(--error));
            font-size: 13px;
            margin-top: 10px;
            padding: 10px;
            background: rgba(var(--error), 0.05);
            border-radius: 6px;
            display: none;
        }

        .rdoc-card-errors:not(:empty) {
            display: block;
        }

        .rdoc-terms-wrapper {
            background: rgba(var(--primary), 0.04);
            border: 2px solid rgba(var(--primary), 0.12);
            border-radius: 12px;
            padding: 20px;
            margin-top: 28px;
            margin-bottom: 0;
        }

        .rdoc-terms-wrapper .rdoc-checkbox-wrapper {
            margin: 0;
        }

        .rdoc-terms-wrapper .rdoc-checkbox-label {
            font-size: 13px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding-left: 0;
        }

        .rdoc-terms-wrapper .rdoc-checkbox-label > i {
            color: rgb(var(--success));
            font-size: 16px;
            margin-top: 2px;
            flex-shrink: 0;
        }

        .rdoc-terms-wrapper .rdoc-checkbox-label a {
            color: rgb(var(--primary));
            text-decoration: underline;
            font-weight: 500;
        }

        .rdoc-security-badges {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 20px;
            margin-top: 28px;
            flex-wrap: wrap;
        }

        .rdoc-security-badge {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            color: rgb(var(--neutral-700));
            font-weight: 500;
            padding: 10px 20px;
            background: rgba(var(--success), 0.08);
            border-radius: 24px;
            border: 1px solid rgba(var(--success), 0.2);
        }

        .rdoc-security-badge i {
            color: rgb(var(--success));
            font-size: 16px;
        }

        .rdoc-security-badge i.fab {
            color: rgb(var(--primary));
        }

        @media (max-width: 768px) {
            .rdoc-container {
                margin: 10px 8px;
                border-radius: 12px;
            }

            .rdoc-form-area {
                padding: 20px 14px;
            }

            .rdoc-payment-wrapper {
                padding: 20px 16px;
                border-radius: 10px;
            }

            .rdoc-payment-header {
                font-size: 16px;
                padding-bottom: 14px;
                margin-bottom: 20px;
            }

            .rdoc-payment-header i {
                font-size: 20px;
            }

            .rdoc-terms-wrapper {
                padding: 16px;
                margin-top: 24px;
            }

            .rdoc-checkbox-label {
                font-size: 13px;
                line-height: 1.6;
            }

            .rdoc-submit-btn {
                padding: 18px;
                font-size: 17px;
                margin-top: 28px;
            }

            .rdoc-security-badges {
                flex-direction: column;
                gap: 10px;
                margin-top: 24px;
            }

            .rdoc-security-badge {
                justify-content: center;
                width: 100%;
            }
        }

        /* MENSAJE DE ÉXITO */
        .rdoc-success {
            text-align: center;
            padding: 60px 40px;
        }

        .rdoc-success-icon {
            font-size: 80px;
            color: rgb(var(--success));
            margin-bottom: 20px;
            animation: successPop 0.6s ease-out;
        }

        @keyframes successPop {
            0% { transform: scale(0); opacity: 0; }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); opacity: 1; }
        }

        .rdoc-success-title {
            font-size: 28px;
            font-weight: 700;
            color: rgb(var(--neutral-900));
            margin-bottom: 12px;
        }

        .rdoc-success-text {
            font-size: 16px;
            color: rgb(var(--neutral-600));
            line-height: 1.6;
        }

        /* RESPONSIVE */
        @media (max-width: 1024px) {
            .rdoc-container {
                grid-template-columns: 1fr;
                margin: 20px 10px;
            }

            .rdoc-sidebar {
                padding: 30px 25px;
            }

            .rdoc-form-area {
                padding: 30px 25px;
            }

            .rdoc-form-row {
                grid-template-columns: 1fr;
            }

            .rdoc-form-group.full {
                grid-column: span 1;
            }

            .rdoc-two-column-section {
                grid-template-columns: 1fr;
            }
        }

        /* CUSTOM NOTIFICATION SYSTEM */
        .rdoc-notification-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 999999;
            animation: rdocFadeIn 0.3s ease-out;
        }

        .rdoc-notification-overlay.active {
            display: flex;
        }

        .rdoc-notification {
            background: white;
            border-radius: 12px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            position: relative;
            animation: rdocSlideDown 0.4s ease-out;
        }

        .rdoc-notification-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 18px;
        }

        .rdoc-notification-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            flex-shrink: 0;
        }

        .rdoc-notification.error .rdoc-notification-icon {
            background: rgba(var(--error), 0.1);
            color: rgb(var(--error));
        }

        .rdoc-notification.success .rdoc-notification-icon {
            background: rgba(var(--success), 0.1);
            color: rgb(var(--success));
        }

        .rdoc-notification.warning .rdoc-notification-icon {
            background: rgba(var(--warning), 0.1);
            color: rgb(var(--warning));
        }

        .rdoc-notification.info .rdoc-notification-icon {
            background: rgba(var(--primary), 0.1);
            color: rgb(var(--primary));
        }

        .rdoc-notification-title {
            font-size: 20px;
            font-weight: 600;
            color: rgb(var(--neutral-800));
            margin: 0;
        }

        .rdoc-notification-message {
            font-size: 15px;
            line-height: 1.6;
            color: rgb(var(--neutral-700));
            margin: 0;
            white-space: pre-line;
        }

        .rdoc-notification-message ul {
            margin: 10px 0 0 0;
            padding-left: 20px;
        }

        .rdoc-notification-message li {
            margin: 5px 0;
        }

        .rdoc-notification-close {
            position: absolute;
            top: 15px;
            right: 15px;
            background: none;
            border: none;
            font-size: 24px;
            color: rgb(var(--neutral-500));
            cursor: pointer;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.2s ease;
        }

        .rdoc-notification-close:hover {
            background: rgba(var(--neutral-500), 0.1);
            color: rgb(var(--neutral-700));
        }

        .rdoc-notification-button {
            margin-top: 20px;
            width: 100%;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .rdoc-notification.error .rdoc-notification-button {
            background: rgb(var(--error));
            color: white;
        }

        .rdoc-notification.error .rdoc-notification-button:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(var(--error), 0.3);
        }

        .rdoc-notification.success .rdoc-notification-button {
            background: rgb(var(--success));
            color: white;
        }

        .rdoc-notification.success .rdoc-notification-button:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(var(--success), 0.3);
        }

        .rdoc-notification.warning .rdoc-notification-button {
            background: rgb(var(--warning));
            color: white;
        }

        .rdoc-notification.warning .rdoc-notification-button:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(var(--warning), 0.3);
        }

        .rdoc-notification.info .rdoc-notification-button {
            background: rgb(var(--primary));
            color: white;
        }

        .rdoc-notification.info .rdoc-notification-button:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(var(--primary), 0.3);
        }

        @keyframes rdocFadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        @keyframes rdocSlideDown {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        /* SIGNATURE PAGE STYLES */
        .rdoc-signature-page {
            padding: 0 !important;
        }

        .rdoc-signature-layout {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-bottom: 15px;
        }

        /* Document Card */
        .rdoc-document-card {
            background: linear-gradient(135deg, rgba(var(--primary), 0.03) 0%, rgba(var(--primary), 0.08) 100%);
            border-radius: 14px;
            overflow: hidden;
            border: 2px solid rgba(var(--primary), 0.15);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .rdoc-document-body {
            padding: 18px 20px;
        }

        .rdoc-auth-text {
            font-size: 14px;
            margin: 0;
            line-height: 1.8;
            color: rgb(var(--neutral-800));
        }

        .rdoc-data-highlight {
            color: rgb(var(--primary));
            background: rgba(var(--primary), 0.1);
            padding: 2px 8px;
            border-radius: 4px;
            font-weight: 600;
        }

        /* Signature Card */
        .rdoc-signature-card {
            background: white;
            border-radius: 14px;
            padding: 18px;
            border: 2px solid rgba(var(--neutral-200), 1);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            display: flex;
            flex-direction: column;
        }

        .rdoc-signature-canvas-wrapper {
            position: relative;
            background: rgba(var(--neutral-50), 1);
            border-radius: 10px;
            border: 2px dashed rgba(var(--primary), 0.3);
            overflow: hidden;
            margin-bottom: 15px;
            touch-action: none;
        }

        .rdoc-signature-canvas {
            display: block;
            width: 100%;
            height: 200px;
            cursor: crosshair;
            touch-action: none;
        }

        .rdoc-signature-placeholder {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            pointer-events: none;
            color: rgba(var(--neutral-400), 1);
            transition: opacity 0.3s ease;
        }

        .rdoc-signature-placeholder.hidden {
            opacity: 0;
        }

        .rdoc-signature-placeholder i {
            font-size: 40px;
            margin-bottom: 8px;
            opacity: 0.5;
        }

        .rdoc-signature-placeholder span {
            font-size: 14px;
            font-weight: 500;
        }

        .rdoc-signature-clear-new {
            background: rgba(var(--error), 0.1);
            color: rgb(var(--error));
            border: 1px solid rgba(var(--error), 0.3);
            padding: 12px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 15px;
        }

        .rdoc-signature-clear-new:hover {
            background: rgba(var(--error), 0.15);
            transform: translateY(-1px);
        }

        .rdoc-signature-clear-new i {
            font-size: 16px;
        }

        .rdoc-signature-info {
            background: rgba(var(--success), 0.08);
            padding: 12px;
            border-radius: 8px;
            font-size: 12px;
            color: rgb(var(--neutral-700));
            display: flex;
            align-items: center;
            gap: 8px;
            justify-content: center;
        }

        .rdoc-signature-info i {
            color: rgb(var(--success));
        }

        .rdoc-btn-large {
            padding: 16px 32px;
            font-size: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }

        .rdoc-btn-large i {
            font-size: 18px;
        }

        /* Mobile Responsive */
        @media (max-width: 1024px) {
            .rdoc-signature-layout {
                gap: 20px;
            }
        }

        @media (max-width: 768px) {
            .rdoc-document-body {
                padding: 20px;
            }

            .rdoc-auth-text {
                font-size: 13px;
            }

            .rdoc-signature-card {
                padding: 20px;
            }

            .rdoc-signature-canvas {
                height: 180px;
            }

            .rdoc-notification {
                padding: 20px;
                max-width: 95%;
            }

            .rdoc-notification-title {
                font-size: 18px;
            }

            .rdoc-notification-message {
                font-size: 14px;
            }
        }

        @media (max-width: 480px) {
            .rdoc-container {
                margin: 8px 6px;
                border-radius: 10px;
            }

            .rdoc-form-area {
                padding: 16px 12px;
            }

            .rdoc-document-body {
                padding: 15px;
            }

            .rdoc-signature-card {
                padding: 15px;
            }

            .rdoc-signature-canvas {
                height: 180px;
            }

            .rdoc-btn-large {
                padding: 14px 24px;
                font-size: 15px;
            }

            .rdoc-payment-wrapper {
                padding: 18px 12px;
            }

            .rdoc-payment-header {
                font-size: 15px;
            }

            .rdoc-stripe-wrapper {
                padding: 12px;
            }

            .rdoc-stripe-loading {
                padding: 20px;
                font-size: 13px;
            }

            .rdoc-terms-wrapper {
                padding: 14px 12px;
            }

            .rdoc-checkbox-label {
                font-size: 12px;
            }

            .rdoc-submit-btn {
                padding: 16px;
                font-size: 16px;
            }

            .rdoc-security-badge {
                padding: 8px 14px;
                font-size: 12px;
            }

            .rdoc-back-btn-minimal {
                font-size: 16px;
                width: 32px;
                height: 32px;
            }
        }
    </style>

    <div class="rdoc-container">
        
        <!-- SIDEBAR INFORMATIVO -->
        <div class="rdoc-sidebar">
            <div>
                <div class="rdoc-headline">Solicita la documentación de tu embarcación de recreo</div>
                <p class="rdoc-subheadline">Rellena el formulario paso a paso: datos personales, documentos, firma digital y pago. Nosotros nos encargamos del resto.</p>
            </div>

            <div style="background: rgba(255,255,255,0.1); padding: 18px; border-radius: 10px; margin-bottom: 20px; backdrop-filter: blur(10px);">
                <p style="font-size: 13.5px; line-height: 1.7; margin: 0; color: rgba(255,255,255,0.95);">
                    <strong style="display: block; margin-bottom: 8px; font-size: 14px;">¿Qué incluye?</strong>
                    Hoja de Asiento oficial, Registro Marítimo renovado y Permiso de Navegación actualizado. Todos los documentos necesarios para navegar legalmente.
                </p>
            </div>

            <div class="rdoc-price-box">
                <div class="rdoc-price-label">Precio Total</div>
                <div class="rdoc-price-amount"><?php echo PRECIO_TOTAL; ?>€</div>
                <div class="rdoc-price-detail">Incluye todas las tasas</div>
            </div>

            <div class="rdoc-benefits">
                <div class="rdoc-benefit">
                    <i class="fas fa-check"></i>
                    <span>Presentamos tu solicitud en menos de 24 h desde que la recibimos.</span>
                </div>
                <div class="rdoc-benefit">
                    <i class="fas fa-check"></i>
                    <span>Envío de provisional en menos de 24 h.</span>
                </div>
                <div class="rdoc-benefit">
                    <i class="fas fa-check"></i>
                    <span>Consulta el estado del trámite vía whatsapp.</span>
                </div>
            </div>
        </div>

        <!-- FORMULARIO PRINCIPAL -->
        <div class="rdoc-form-area">
            <?php if ($is_admin): ?>
            <div class="rdoc-admin-panel">
                <div>
                    <i class="fas fa-crown"></i>
                    <strong>Panel de Administrador</strong>
                </div>
                <button type="button" class="rdoc-admin-btn" onclick="rdocAutoFill()">
                    🚀 Auto-rellenar
                </button>
            </div>
            <?php endif; ?>

            <form id="rdoc-form" class="rdoc-form">

                <!-- PÁGINA 1: DATOS Y DOCUMENTOS -->
                <div class="rdoc-page active" id="rdoc-page-1">

                    <!-- SECCIÓN 1: DATOS (PERSONALES + EMBARCACIÓN) -->
                    <div class="rdoc-section">
                        <div class="rdoc-two-column-section">
                            <!-- DATOS PERSONALES -->
                            <div class="rdoc-column">
                                <div class="rdoc-section-title">
                                    <i class="fas fa-user"></i>
                                    Datos Personales
                                </div>

                                <div class="rdoc-form-row">
                                    <div class="rdoc-form-group">
                                        <label class="rdoc-label">
                                            Nombre Completo <span class="rdoc-required">*</span>
                                        </label>
                                        <input
                                            type="text"
                                            id="rdoc-name"
                                            class="rdoc-input"
                                            placeholder="Juan García López"
                                            required
                                        />
                                    </div>

                                    <div class="rdoc-form-group">
                                        <label class="rdoc-label">
                                            DNI / NIE <span class="rdoc-required">*</span>
                                        </label>
                                        <input
                                            type="text"
                                            id="rdoc-dni"
                                            class="rdoc-input"
                                            placeholder="12345678A"
                                            required
                                        />
                                    </div>
                                </div>

                                <div class="rdoc-form-row">
                                    <div class="rdoc-form-group">
                                        <label class="rdoc-label">
                                            Email <span class="rdoc-required">*</span>
                                        </label>
                                        <input
                                            type="email"
                                            id="rdoc-email"
                                            class="rdoc-input"
                                            placeholder="tu@email.com"
                                            required
                                        />
                                    </div>

                                    <div class="rdoc-form-group">
                                        <label class="rdoc-label">
                                            Teléfono <span class="rdoc-required">*</span>
                                        </label>
                                        <input
                                            type="tel"
                                            id="rdoc-phone"
                                            class="rdoc-input"
                                            placeholder="612 345 678"
                                            required
                                        />
                                    </div>
                                </div>
                            </div>

                            <!-- DATOS EMBARCACIÓN -->
                            <div class="rdoc-column">
                                <div class="rdoc-section-title">
                                    <i class="fas fa-ship"></i>
                                    Datos de la Embarcación
                                </div>

                                <div class="rdoc-form-group">
                                    <label class="rdoc-label">
                                        Nombre de la Embarcación <span class="rdoc-required">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        id="rdoc-vessel-name"
                                        class="rdoc-input"
                                        placeholder="Ej: Mar Azul"
                                        required
                                    />
                                </div>

                                <div class="rdoc-form-group">
                                    <label class="rdoc-label">
                                        Matrícula <span class="rdoc-required">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        id="rdoc-vessel-registration"
                                        class="rdoc-input"
                                        placeholder="Ej: 3-BA-1-234"
                                        required
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SECCIÓN 2: DOCUMENTO DNI -->
                    <div class="rdoc-section">
                        <div class="rdoc-section-title">
                            <i class="fas fa-id-card"></i>
                            Documento de Identidad
                        </div>

                        <div class="rdoc-upload-area" onclick="document.getElementById('rdoc-dni-input').click()">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <div class="rdoc-upload-text">
                                <strong>Haz clic para subir</strong> tu DNI/NIE (ambas caras)
                                <br>
                                <small>JPG, PNG o PDF • Máx. 10MB</small>
                            </div>
                        </div>
                        <input
                            type="file"
                            id="rdoc-dni-input"
                            class="rdoc-file-input"
                            accept="image/*,.pdf"
                            multiple
                        />
                        <div id="rdoc-file-list" class="rdoc-file-list"></div>
                    </div>

                    <!-- BOTÓN SIGUIENTE -->
                    <button type="button" id="rdoc-next-btn" class="rdoc-next-btn">
                        Siguiente <i class="fas fa-arrow-right"></i>
                    </button>

                </div>

                <!-- PÁGINA 2: FIRMA DIGITAL -->
                <div class="rdoc-page" id="rdoc-page-2">

                    <button type="button" class="rdoc-back-btn" onclick="rdocGoToPage(1)">
                        <i class="fas fa-arrow-left"></i> Volver
                    </button>

                    <!-- FIRMA CON DOCUMENTO -->
                    <div class="rdoc-section rdoc-signature-page">
                        <div class="rdoc-signature-layout">
                            <!-- Documento de Autorización -->
                            <div class="rdoc-document-card">
                                <div class="rdoc-document-body">
                                    <p class="rdoc-auth-text">
                                        <strong>Yo, <span id="rdoc-preview-name" class="rdoc-data-highlight">[Cargando...]</span></strong>,
                                        con DNI/NIE <strong><span id="rdoc-preview-dni" class="rdoc-data-highlight">[Cargando...]</span></strong>,
                                        autorizo a <strong>Tramitfy</strong> para gestionar ante la Capitanía Marítima la recuperación de documentación extraviada de mi embarcación
                                        <strong><span id="rdoc-preview-vessel" class="rdoc-data-highlight">[Cargando...]</span></strong>
                                        con matrícula <strong><span id="rdoc-preview-registration" class="rdoc-data-highlight">[Cargando...]</span></strong>.
                                    </p>
                                </div>
                            </div>

                            <!-- Canvas de Firma -->
                            <div class="rdoc-signature-card">

                                <div class="rdoc-signature-canvas-wrapper">
                                    <canvas
                                        id="rdoc-signature-canvas"
                                        class="rdoc-signature-canvas"
                                        width="700"
                                        height="200"
                                    ></canvas>
                                    <div class="rdoc-signature-placeholder" id="rdoc-signature-placeholder">
                                        <i class="fas fa-signature"></i>
                                        <span>Firma aquí</span>
                                    </div>
                                </div>

                                <button type="button" class="rdoc-signature-clear-new" onclick="rdocClearSignature()">
                                    <i class="fas fa-eraser"></i>
                                    <span>Limpiar y volver a firmar</span>
                                </button>

                                <div class="rdoc-signature-info">
                                    <i class="fas fa-lock"></i>
                                    Tu firma está protegida con encriptación SSL
                                </div>
                            </div>
                        </div>
                    </div>

                    <button type="button" id="rdoc-next-page2-btn" class="rdoc-next-btn rdoc-btn-large">
                        <span>Continuar al Pago</span>
                        <i class="fas fa-arrow-right"></i>
                    </button>

                </div>

                <!-- PÁGINA 3: PAGO -->
                <div class="rdoc-page" id="rdoc-page-3">

                    <button type="button" class="rdoc-back-btn-minimal" onclick="rdocGoToPage(2)" title="Volver">
                        <i class="fas fa-arrow-left"></i>
                    </button>

                    <!-- PAGO -->
                    <div class="rdoc-section">
                        <div class="rdoc-payment-wrapper">
                            <div class="rdoc-payment-header">
                                <i class="fas fa-lock"></i>
                                <span>Datos de Pago</span>
                            </div>
                            <div id="rdoc-stripe-loading" class="rdoc-stripe-loading" style="display: none;">
                                <i class="fas fa-spinner fa-spin"></i>
                                <span>Cargando sistema de pago seguro...</span>
                            </div>
                            <div id="rdoc-stripe-card" class="rdoc-stripe-wrapper"></div>
                            <div id="rdoc-card-errors" class="rdoc-card-errors"></div>
                        </div>

                        <div class="rdoc-terms-wrapper">
                            <div class="rdoc-checkbox-wrapper">
                                <input type="checkbox" id="rdoc-consent-terms" class="rdoc-checkbox" required />
                                <label for="rdoc-consent-terms" class="rdoc-checkbox-label">
                                    <i class="fas fa-check-circle"></i>
                                    He leído y acepto los <a href="https://tramitfy.es/terminos-y-condiciones-de-uso-2/" target="_blank">Términos y Condiciones</a> y la <a href="https://tramitfy.es/politica-de-privacidad/" target="_blank">Política de Privacidad</a>
                                </label>
                            </div>
                        </div>

                        <button type="button" id="rdoc-submit-payment" class="rdoc-submit-btn rdoc-btn-large">
                            <i class="fas fa-lock"></i>
                            <span>Confirmar y Pagar <?php echo PRECIO_TOTAL; ?>€</span>
                        </button>

                        <div class="rdoc-security-badges">
                            <div class="rdoc-security-badge">
                                <i class="fas fa-lock"></i>
                                <span>Pago 100% Seguro · Cifrado SSL · Stripe</span>
                            </div>
                        </div>
                    </div>

                </div>

            </form>

            <!-- MENSAJE DE ÉXITO (oculto inicialmente) -->
            <div id="rdoc-success" class="rdoc-success" style="display: none;">
                <div class="rdoc-success-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h2 class="rdoc-success-title">¡Solicitud Enviada con Éxito!</h2>
                <p class="rdoc-success-text">
                    Hemos recibido tu solicitud y el pago se ha procesado correctamente.<br><br>
                    <strong>Recibirás un email de confirmación con el enlace de seguimiento de tu trámite.</strong><br><br>
                    Podrás consultar el estado de tu documentación en tiempo real desde tu correo electrónico.
                </p>
            </div>

            <!-- CUSTOM NOTIFICATION OVERLAY -->
            <div id="rdoc-notification-overlay" class="rdoc-notification-overlay">
                <div id="rdoc-notification" class="rdoc-notification">
                    <button class="rdoc-notification-close" onclick="rdocCloseNotification()">×</button>
                    <div class="rdoc-notification-header">
                        <div class="rdoc-notification-icon">
                            <i id="rdoc-notification-icon-elem"></i>
                        </div>
                        <h3 class="rdoc-notification-title" id="rdoc-notification-title"></h3>
                    </div>
                    <p class="rdoc-notification-message" id="rdoc-notification-message"></p>
                    <button class="rdoc-notification-button" onclick="rdocCloseNotification()">Entendido</button>
                </div>
            </div>

        </div>
    </div>

    <!-- Widget de reseñas Trustindex -->
    <div style="max-width: 1400px; margin: 40px auto; padding: 0 20px;">
        [trustindex data-widget-id=528e73a37d5c907840566b0945b]
    </div>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Stripe JS -->
    <script src="https://js.stripe.com/v3/"></script>

    <script>
        let rdocStripe = null;
        let rdocElements, rdocCardElement;
        let rdocClientSecret = null;
        let rdocDniFiles = [];
        let rdocSignatureCanvas, rdocSignatureCtx;
        let rdocIsDrawing = false;
        let rdocHasSignature = false;

        let rdocCurrentPage = 1;

        // ====== CUSTOM NOTIFICATION SYSTEM ======
        function rdocShowNotification(message, type = 'error', title = null) {
            const overlay = document.getElementById('rdoc-notification-overlay');
            const notification = document.getElementById('rdoc-notification');
            const iconElem = document.getElementById('rdoc-notification-icon-elem');
            const titleElem = document.getElementById('rdoc-notification-title');
            const messageElem = document.getElementById('rdoc-notification-message');

            notification.className = 'rdoc-notification ' + type;

            const icons = {
                error: 'fa-circle-xmark',
                success: 'fa-circle-check',
                warning: 'fa-triangle-exclamation',
                info: 'fa-circle-info'
            };

            const titles = {
                error: title || 'Error',
                success: title || 'Éxito',
                warning: title || 'Atención',
                info: title || 'Información'
            };

            iconElem.className = 'fas ' + icons[type];
            titleElem.textContent = titles[type];
            messageElem.innerHTML = message;

            overlay.classList.add('active');
        }

        function rdocCloseNotification() {
            const overlay = document.getElementById('rdoc-notification-overlay');
            overlay.classList.remove('active');
        }

        document.addEventListener('click', function(e) {
            if (e.target.id === 'rdoc-notification-overlay') {
                rdocCloseNotification();
            }
        });

        // ====== INICIALIZAR STRIPE LIBRARY ======
        function rdocEnsureStripeLoaded() {
            return new Promise((resolve, reject) => {
                let attempts = 0;
                const maxAttempts = 20; // 2 segundos máximo (20 * 100ms)

                const checkStripe = () => {
                    attempts++;
                    console.log(`🔍 Verificando Stripe... (intento ${attempts}/${maxAttempts})`);

                    if (typeof Stripe !== 'undefined') {
                        try {
                            rdocStripe = Stripe('<?php echo $stripe_public_key; ?>');
                            console.log('✅ Stripe library cargada e inicializada correctamente');
                            console.log('✅ Stripe object:', rdocStripe);
                            resolve(rdocStripe);
                        } catch (error) {
                            console.error('❌ Error al inicializar Stripe:', error);
                            reject(error);
                        }
                    } else if (attempts >= maxAttempts) {
                        const error = new Error('Stripe no se pudo cargar después de ' + maxAttempts + ' intentos');
                        console.error('❌', error.message);
                        reject(error);
                    } else {
                        setTimeout(checkStripe, 100);
                    }
                };

                checkStripe();
            });
        }

        // ====== INICIALIZACIÓN ======
        document.addEventListener('DOMContentLoaded', async function() {
            console.log('🚀 DOMContentLoaded - Iniciando formulario recuperar documentación');

            // Esperar un momento para asegurar que todo esté cargado
            setTimeout(async function() {
                console.log('Inicializando componentes...');

                try {
                    await rdocEnsureStripeLoaded();
                } catch (error) {
                    console.error('❌ No se pudo cargar Stripe:', error);
                    rdocShowNotification(
                        'No se pudo cargar el sistema de pagos.<br><br>Por favor, <strong>recarga la página</strong> e inténtalo de nuevo.<br><br>Si el problema persiste, verifica tu conexión a internet.',
                        'error',
                        'Error de Carga'
                    );
                }

                rdocInitializeFileUpload();
                rdocSetupNavigation();
                rdocSetupPaymentButton();
                console.log('✅ Inicialización completa');
            }, 300);
        });

        // ====== NAVEGACIÓN DE PÁGINAS ======
        function rdocSetupNavigation() {
            const nextBtnPage1 = document.getElementById('rdoc-next-btn');
            if (!nextBtnPage1) {
                console.error('Botón página 1 no encontrado');
                return;
            }

            nextBtnPage1.addEventListener('click', function(e) {
                e.preventDefault();
                console.log('Botón página 1 clickeado');
                if (rdocValidatePage1()) {
                    rdocGoToPage(2);
                }
            });

            setTimeout(function() {
                const nextBtnPage2 = document.getElementById('rdoc-next-page2-btn');
                if (nextBtnPage2) {
                    nextBtnPage2.addEventListener('click', function(e) {
                        e.preventDefault();
                        console.log('Botón página 2 clickeado');
                        if (rdocValidatePage2()) {
                            rdocGoToPage(3);
                        }
                    });
                }
            }, 500);
        }

        function rdocGoToPage(pageNumber) {
            document.querySelectorAll('.rdoc-page').forEach(page => {
                page.classList.remove('active');
            });

            const targetPage = document.getElementById('rdoc-page-' + pageNumber);
            targetPage.classList.add('active');
            rdocCurrentPage = pageNumber;

            if (pageNumber === 2) {
                setTimeout(() => {
                    rdocPopulateAuthorizationData();
                    rdocInitializeSignature();
                }, 100);
            }

            if (pageNumber === 3) {
                setTimeout(async () => {
                    console.log('📄 Navegando a página 3 (Pago)');
                    console.log('💳 rdocClientSecret:', rdocClientSecret ? 'Existe' : 'No existe');
                    console.log('💳 rdocElements:', rdocElements ? 'Existe' : 'No existe');

                    if (!rdocClientSecret || !rdocElements) {
                        console.log('💳 Inicializando Stripe por primera vez...');
                        await rdocInitializeStripe();
                    } else {
                        console.log('✅ Stripe ya está inicializado');
                    }
                }, 100);
            }

            // Solo scroll en desktop
            if (window.innerWidth > 768) {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        }

        // ====== RELLENAR DATOS DE AUTORIZACIÓN ======
        function rdocPopulateAuthorizationData() {
            console.log('📝 Rellenando datos del documento de autorización...');

            const name = document.getElementById('rdoc-name').value;
            const dni = document.getElementById('rdoc-dni').value;
            const vesselName = document.getElementById('rdoc-vessel-name').value;
            const vesselReg = document.getElementById('rdoc-vessel-registration').value;

            document.getElementById('rdoc-preview-name').textContent = name;
            document.getElementById('rdoc-preview-dni').textContent = dni;
            document.getElementById('rdoc-preview-vessel').textContent = vesselName;
            document.getElementById('rdoc-preview-registration').textContent = vesselReg;

            console.log('✅ Datos rellenados:', { name, dni, vesselName, vesselReg });
        }

        function rdocValidatePage1() {
            console.log('=== VALIDANDO PÁGINA 1 ===');
            let isValid = true;
            const errors = [];

            const name = document.getElementById('rdoc-name');
            const dni = document.getElementById('rdoc-dni');
            const email = document.getElementById('rdoc-email');
            const phone = document.getElementById('rdoc-phone');
            const vesselName = document.getElementById('rdoc-vessel-name');
            const vesselReg = document.getElementById('rdoc-vessel-registration');

            if (!name.value.trim()) {
                name.classList.add('error');
                errors.push('Nombre completo');
                isValid = false;
            } else {
                name.classList.remove('error');
            }

            if (!dni.value.trim()) {
                dni.classList.add('error');
                errors.push('DNI/NIE');
                isValid = false;
            } else {
                dni.classList.remove('error');
            }

            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email.value)) {
                email.classList.add('error');
                errors.push('Email válido');
                isValid = false;
            } else {
                email.classList.remove('error');
            }

            if (!phone.value.trim() || phone.value.replace(/\s/g, '').length < 9) {
                phone.classList.add('error');
                errors.push('Teléfono (min 9 dígitos)');
                isValid = false;
            } else {
                phone.classList.remove('error');
            }

            if (!vesselName.value.trim()) {
                vesselName.classList.add('error');
                errors.push('Nombre de embarcación');
                isValid = false;
            } else {
                vesselName.classList.remove('error');
            }

            if (!vesselReg.value.trim()) {
                vesselReg.classList.add('error');
                errors.push('Matrícula');
                isValid = false;
            } else {
                vesselReg.classList.remove('error');
            }

            console.log('Archivos subidos:', rdocDniFiles.length);
            if (rdocDniFiles.length === 0) {
                errors.push('Documento DNI/NIE');
                isValid = false;
            }

            if (!isValid) {
                console.log('Validación fallida. Campos faltantes:', errors);
                const errorList = errors.map(e => '• ' + e).join('<br>');
                rdocShowNotification(
                    'Por favor, completa los siguientes campos:<br><br>' + errorList,
                    'error',
                    'Campos Incompletos'
                );
            } else {
                console.log('✅ Validación exitosa');
            }

            return isValid;
        }

        // ====== VALIDACIÓN PÁGINA 2 ======
        function rdocValidatePage2() {
            console.log('=== VALIDANDO PÁGINA 2 ===');

            if (!rdocHasSignature) {
                rdocShowNotification(
                    'Por favor, firma en el recuadro antes de continuar al pago.',
                    'warning',
                    'Firma Requerida'
                );
                return false;
            }

            console.log('✅ Validación página 2 exitosa');
            return true;
        }

        // ====== FIRMA DIGITAL ======
        function rdocInitializeSignature() {
            console.log('✍️ Inicializando canvas de firma...');

            rdocSignatureCanvas = document.getElementById('rdoc-signature-canvas');
            if (!rdocSignatureCanvas) {
                console.error('Canvas no encontrado');
                return;
            }

            const placeholder = document.getElementById('rdoc-signature-placeholder');

            rdocSignatureCtx = rdocSignatureCanvas.getContext('2d');
            rdocSignatureCtx.strokeStyle = '#000';
            rdocSignatureCtx.lineWidth = 2.5;
            rdocSignatureCtx.lineCap = 'round';
            rdocSignatureCtx.lineJoin = 'round';

            // Mouse events
            rdocSignatureCanvas.addEventListener('mousedown', rdocStartDrawing);
            rdocSignatureCanvas.addEventListener('mousemove', rdocDraw);
            rdocSignatureCanvas.addEventListener('mouseup', rdocStopDrawing);
            rdocSignatureCanvas.addEventListener('mouseleave', rdocStopDrawing);

            // Touch events con mejor manejo
            rdocSignatureCanvas.addEventListener('touchstart', (e) => {
                e.preventDefault();
                const touch = e.touches[0];
                rdocStartDrawing(touch);
            }, { passive: false });

            rdocSignatureCanvas.addEventListener('touchmove', (e) => {
                e.preventDefault();
                const touch = e.touches[0];
                rdocDraw(touch);
            }, { passive: false });

            rdocSignatureCanvas.addEventListener('touchend', (e) => {
                e.preventDefault();
                rdocStopDrawing();
            }, { passive: false });

            console.log('✅ Canvas de firma inicializado correctamente');
        }

        function rdocStartDrawing(e) {
            rdocIsDrawing = true;

            const placeholder = document.getElementById('rdoc-signature-placeholder');
            if (placeholder) {
                placeholder.classList.add('hidden');
            }

            const rect = rdocSignatureCanvas.getBoundingClientRect();
            const scaleX = rdocSignatureCanvas.width / rect.width;
            const scaleY = rdocSignatureCanvas.height / rect.height;
            const x = (e.clientX - rect.left) * scaleX;
            const y = (e.clientY - rect.top) * scaleY;

            rdocSignatureCtx.beginPath();
            rdocSignatureCtx.moveTo(x, y);
        }

        function rdocDraw(e) {
            if (!rdocIsDrawing) return;

            const rect = rdocSignatureCanvas.getBoundingClientRect();
            const scaleX = rdocSignatureCanvas.width / rect.width;
            const scaleY = rdocSignatureCanvas.height / rect.height;
            const x = (e.clientX - rect.left) * scaleX;
            const y = (e.clientY - rect.top) * scaleY;

            rdocSignatureCtx.lineTo(x, y);
            rdocSignatureCtx.stroke();

            if (!rdocHasSignature) {
                rdocHasSignature = true;
                console.log('✅ Primera firma detectada');
            }
        }

        function rdocStopDrawing() {
            rdocIsDrawing = false;
            rdocSignatureCtx.closePath();
        }

        function rdocClearSignature() {
            if (!rdocSignatureCanvas || !rdocSignatureCtx) return;

            rdocSignatureCtx.clearRect(0, 0, rdocSignatureCanvas.width, rdocSignatureCanvas.height);
            rdocHasSignature = false;

            const placeholder = document.getElementById('rdoc-signature-placeholder');
            if (placeholder) {
                placeholder.classList.remove('hidden');
            }

            console.log('🧹 Firma limpiada');
        }

        function rdocGetSignatureDataURL() {
            if (!rdocSignatureCanvas) return null;
            return rdocSignatureCanvas.toDataURL('image/png');
        }

        // ====== UPLOAD DE ARCHIVOS ======
        function rdocInitializeFileUpload() {
            const input = document.getElementById('rdoc-dni-input');
            const uploadArea = document.querySelector('.rdoc-upload-area');

            if (!input) {
                console.error('❌ Input de DNI no encontrado');
                return;
            }

            if (!uploadArea) {
                console.error('❌ Upload area no encontrada');
                return;
            }

            console.log('✅ Input de DNI encontrado:', input);
            console.log('✅ Upload area encontrada:', uploadArea);

            // Event listener para el cambio de archivos
            input.addEventListener('change', function(e) {
                console.log('📁 EVENT CHANGE TRIGGERED');
                console.log('📁 Archivos seleccionados:', e.target.files.length);

                if (e.target.files.length === 0) {
                    console.warn('⚠️ No hay archivos en el evento change');
                    return;
                }

                const files = Array.from(e.target.files);
                console.log('📁 Archivos en array:', files.length);

                files.forEach((file, index) => {
                    console.log(`📄 [${index}] Procesando:`, file.name, 'Tamaño:', file.size, 'bytes');

                    const isDuplicate = rdocDniFiles.some(
                        f => f.name === file.name && f.size === file.size
                    );

                    if (isDuplicate) {
                        console.warn(`⚠️ Archivo duplicado ignorado: ${file.name}`);
                        return;
                    }

                    if (file.size > 10 * 1024 * 1024) {
                        console.error(`❌ Archivo muy grande: ${file.name}`);
                        rdocShowNotification(
                            'El archivo <strong>' + file.name + '</strong> es demasiado grande.<br><br>El tamaño máximo permitido es <strong>10MB</strong>.',
                            'warning',
                            'Archivo Muy Grande'
                        );
                        return;
                    }

                    rdocDniFiles.push(file);
                    console.log(`✅ Archivo agregado [${rdocDniFiles.length}]:`, file.name);
                });

                console.log('📦 Total de archivos en array:', rdocDniFiles.length);
                console.log('📦 Array completo:', rdocDniFiles.map(f => f.name));

                rdocRenderFiles();

                // Reset input
                e.target.value = '';
                console.log('🔄 Input reseteado');
            });

            // Event listener adicional para debugging del click
            uploadArea.addEventListener('click', function(e) {
                console.log('🖱️ Click en upload area');
                console.log('🖱️ Input será activado');
            });

            console.log('✅ Event listeners configurados correctamente');
        }

        function rdocRenderFiles() {
            console.log('\n🎨 === RENDER FILES ===');
            const list = document.getElementById('rdoc-file-list');

            if (!list) {
                console.error('❌ rdoc-file-list element NOT FOUND!');
                console.error('❌ Buscando en DOM...');
                console.error('❌ Documento:', document.getElementById('rdoc-file-list'));
                return;
            }

            console.log('✅ List element encontrado:', list);
            console.log('📦 Archivos para renderizar:', rdocDniFiles.length);

            if (rdocDniFiles.length === 0) {
                console.log('ℹ️ No hay archivos, limpiando lista');
                list.innerHTML = '';
                list.style.display = 'none';
                return;
            }

            list.style.display = 'flex';

            const html = rdocDniFiles.map((file, index) => {
                const icon = file.type === 'application/pdf' ? 'fa-file-pdf' : 'fa-image';
                const truncatedName = file.name.length > 20 ? file.name.substring(0, 17) + '...' : file.name;
                const sizeKB = (file.size / 1024).toFixed(0);

                console.log(`  📄 [${index}] ${file.name} (${sizeKB}KB) - Icon: ${icon}`);

                return `
                    <div class="rdoc-file-item">
                        <i class="fas ${icon} rdoc-file-icon"></i>
                        <span class="rdoc-file-name" title="${file.name}">${truncatedName}</span>
                        <span class="rdoc-file-size">${sizeKB}KB</span>
                        <button type="button" class="rdoc-file-remove" onclick="rdocRemoveFile(${index})" title="Eliminar">×</button>
                    </div>
                `;
            }).join('');

            console.log('📝 HTML generado (primeros 200 chars):', html.substring(0, 200));
            list.innerHTML = html;
            console.log('✅ Archivos renderizados en DOM');
            console.log('✅ Total de pills visibles:', list.querySelectorAll('.rdoc-file-item').length);
            console.log('🎨 === FIN RENDER ===\n');
        }

        function rdocRemoveFile(index) {
            rdocDniFiles.splice(index, 1);
            rdocRenderFiles();
        }

        // ====== STRIPE PAYMENT ======
        async function rdocInitializeStripe() {
            console.log('💳 Inicializando Stripe...');

            const loadingIndicator = document.getElementById('rdoc-stripe-loading');
            const stripeContainer = document.getElementById('rdoc-stripe-card');

            // Mostrar loading
            if (loadingIndicator) loadingIndicator.style.display = 'flex';
            if (stripeContainer) stripeContainer.style.display = 'none';

            if (!rdocStripe) {
                console.error('❌ rdocStripe no está disponible');
                if (loadingIndicator) loadingIndicator.style.display = 'none';

                rdocShowNotification(
                    'Error al cargar el sistema de pagos. Por favor, <strong>recarga la página</strong> e inténtalo de nuevo.<br><br>Si el problema persiste, contacta con soporte.',
                    'error',
                    'Error de Pago'
                );
                return false;
            }

            try {
                console.log('💳 Creando Payment Intent...');
                const response = await fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ action: 'rdoc_create_payment_intent' })
                });

                if (!response.ok) {
                    throw new Error('Error en la conexión con el servidor');
                }

                const result = await response.json();
                console.log('💳 Respuesta del servidor:', result);

                if (result.error) throw new Error(result.error);
                if (!result.clientSecret) throw new Error('No se recibió el client secret del servidor');

                rdocClientSecret = result.clientSecret;
                console.log('💳 Client Secret recibido:', rdocClientSecret.substring(0, 20) + '...');

                if (!stripeContainer) {
                    throw new Error('Contenedor de Stripe no encontrado');
                }

                rdocElements = rdocStripe.elements({ clientSecret: rdocClientSecret });
                rdocCardElement = rdocElements.create('payment', {
                    layout: { type: 'tabs', defaultCollapsed: false }
                });

                console.log('💳 Montando Stripe Elements en DOM...');
                await rdocCardElement.mount('#rdoc-stripe-card');
                console.log('✅ Stripe Elements montado correctamente');

                rdocCardElement.on('change', function(event) {
                    const displayError = document.getElementById('rdoc-card-errors');
                    if (event.error) {
                        displayError.textContent = event.error.message;
                    } else {
                        displayError.textContent = '';
                    }
                });

                // Ocultar loading y mostrar Stripe
                if (loadingIndicator) loadingIndicator.style.display = 'none';
                if (stripeContainer) stripeContainer.style.display = 'block';

                console.log('✅ Stripe inicializado completamente');
                return true;

            } catch (error) {
                console.error('❌ Error inicializando Stripe:', error);
                console.error('❌ Error stack:', error.stack);

                // Ocultar loading
                if (loadingIndicator) loadingIndicator.style.display = 'none';

                rdocShowNotification(
                    'Error al inicializar el sistema de pagos:<br><br><strong>' + error.message + '</strong><br><br>Por favor, recarga la página e inténtalo de nuevo.',
                    'error',
                    'Error de Pago'
                );

                return false;
            }
        }

        // ====== VALIDACIÓN PÁGINA 3 (PAGO) ======
        function rdocValidatePage3() {
            console.log('=== VALIDANDO PÁGINA 3 (PAGO) ===');

            const consentTerms = document.getElementById('rdoc-consent-terms');
            if (!consentTerms.checked) {
                rdocShowNotification(
                    'Debes aceptar los Términos y Condiciones de Uso y la Política de Privacidad para continuar con el pago.',
                    'warning',
                    'Términos y Condiciones'
                );
                return false;
            }

            console.log('✅ Validación página 3 exitosa');
            return true;
        }

        // ====== SETUP PAYMENT BUTTON ======
        function rdocSetupPaymentButton() {
            const paymentBtn = document.getElementById('rdoc-submit-payment');
            if (!paymentBtn) {
                console.error('❌ Botón de pago no encontrado');
                return;
            }

            paymentBtn.addEventListener('click', async function(e) {
                e.preventDefault();
                console.log('💳 Botón de pago clickeado');

                if (!rdocValidatePage3()) return;

                const submitButton = this;
                const originalHTML = submitButton.innerHTML;
                submitButton.disabled = true;
                submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span>Procesando pago...</span>';

                try {
                    // Intentar cargar Stripe si no está disponible
                    if (!rdocStripe) {
                        console.log('⚠️ Stripe no disponible, intentando cargar...');
                        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span>Cargando sistema de pago...</span>';

                        try {
                            await rdocEnsureStripeLoaded();
                        } catch (stripeError) {
                            throw new Error('No se pudo cargar el sistema de pagos. Por favor, recarga la página e inténtalo de nuevo.');
                        }
                    }

                    if (!rdocElements) {
                        throw new Error('El formulario de pago no está listo. Por favor, espera unos segundos e inténtalo de nuevo.');
                    }

                    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span>Procesando pago...</span>';
                    console.log('💳 Confirmando pago con Stripe...');
                    const { error: submitError } = await rdocStripe.confirmPayment({
                        elements: rdocElements,
                        confirmParams: { return_url: window.location.href },
                        redirect: 'if_required'
                    });

                    if (submitError) {
                        throw new Error(submitError.message);
                    }

                    console.log('✅ Pago confirmado, enviando a Tramitfy...');
                    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span>Guardando datos...</span>';

                    const tramiteResult = await rdocSendToTramitfy();
                    console.log('✅ Datos guardados, tramiteId:', tramiteResult.tramiteId);

                    // Esperar 2 segundos antes de enviar emails para evitar conflictos
                    await new Promise(resolve => setTimeout(resolve, 2000));

                    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span>Enviando emails de confirmación...</span>';
                    await rdocSendEmails(tramiteResult.tramiteId);
                    console.log('✅ Emails enviados');

                    document.getElementById('rdoc-form').style.display = 'none';
                    document.getElementById('rdoc-success').style.display = 'block';

                    // Redirigir a la página de seguimiento después de 3 segundos
                    setTimeout(() => {
                        const trackingUrl = `https://46-202-128-35.sslip.io/seguimiento/${tramiteResult.tramiteId}`;
                        window.location.href = trackingUrl;
                    }, 3000);

                    // Solo scroll en desktop
                    if (window.innerWidth > 768) {
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    }

                } catch (error) {
                    console.error('❌ Error en el pago:', error);

                    const errorContainer = document.getElementById('rdoc-card-errors');
                    errorContainer.textContent = error.message;

                    rdocShowNotification(
                        'Error al procesar el pago:<br><br><strong>' + error.message + '</strong>',
                        'error',
                        'Error de Pago'
                    );

                    submitButton.disabled = false;
                    submitButton.innerHTML = originalHTML;
                }
            });

            console.log('✅ Event listener de pago configurado');
        }

        // ====== ENVIAR A TRAMITFY ======
        async function rdocSendToTramitfy() {
            const formData = new FormData();

            const data = {
                customerName: document.getElementById('rdoc-name').value,
                customerDNI: document.getElementById('rdoc-dni').value,
                customerEmail: document.getElementById('rdoc-email').value,
                customerPhone: document.getElementById('rdoc-phone').value,
                vesselName: document.getElementById('rdoc-vessel-name').value,
                vesselRegistration: document.getElementById('rdoc-vessel-registration').value,
                consentTerms: document.getElementById('rdoc-consent-terms').checked,
                signatureData: rdocGetSignatureDataURL(),
                paymentIntentId: rdocClientSecret
            };

            formData.append('action', 'rdoc_send_to_tramitfy');
            formData.append('formData', JSON.stringify(data));

            rdocDniFiles.forEach(file => {
                formData.append('dniDocumento[]', file);
            });

            // Enviar a WordPress para que genere el PDF y lo suba al API
            const response = await fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();
            if (!result.success) {
                throw new Error(result.error || 'Error al enviar los datos');
            }

            return result;
        }

        async function rdocSendEmails(tramiteId) {
            console.log('📧 Iniciando envío de emails para tramiteId:', tramiteId);

            const formData = new FormData();
            formData.append('action', 'rdoc_send_emails');
            formData.append('customerName', document.getElementById('rdoc-name').value);
            formData.append('customerEmail', document.getElementById('rdoc-email').value);
            formData.append('vesselName', document.getElementById('rdoc-vessel-name').value);
            formData.append('vesselRegistration', document.getElementById('rdoc-vessel-registration').value);
            formData.append('tramiteId', tramiteId);

            console.log('📧 Enviando request a admin-ajax.php...');
            const response = await fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                method: 'POST',
                body: formData
            });

            console.log('📧 Response status:', response.status);
            const result = await response.json();
            console.log('📧 Response data:', result);

            if (!result.success) {
                console.error('❌ Emails no enviados:', result);
                throw new Error(result.message || 'Error al enviar emails');
            }
            return result;
        }

        // ====== AUTO-RELLENADO (ADMIN) ======
        <?php if ($is_admin): ?>
        function rdocAutoFill() {
            document.getElementById('rdoc-name').value = 'Joan Pinyol';
            document.getElementById('rdoc-dni').value = '12345678A';
            document.getElementById('rdoc-email').value = 'joanpinyol@hotmail.es';
            document.getElementById('rdoc-phone').value = '682246937';
            document.getElementById('rdoc-vessel-name').value = 'Mar Azul';
            document.getElementById('rdoc-vessel-registration').value = '3-BA-1-234';

            rdocShowNotification(
                'Formulario auto-rellenado correctamente (Página 1).<br><br><strong>Importante:</strong> Recuerda subir tu DNI antes de continuar a la página de firma.',
                'success',
                'Auto-rellenado Completo'
            );
        }
        <?php endif; ?>
    </script>

    <?php
    return ob_get_clean();
}

// Nueva función simple para enviar emails
function rdoc_send_emails() {
    error_log("=== RDOC_SEND_EMAILS FUNCTION STARTED ===");

    $customer_name = sanitize_text_field($_POST['customerName']);
    $customer_email = sanitize_email($_POST['customerEmail']);
    $vessel_name = sanitize_text_field($_POST['vesselName']);
    $vessel_registration = sanitize_text_field($_POST['vesselRegistration']);
    $tramite_id = sanitize_text_field($_POST['tramiteId']);

    error_log("Enviando emails para: $customer_email, tramiteId: $tramite_id");

    $tracking_url = 'https://46-202-128-35.sslip.io/seguimiento/' . $tramite_id;

    // Email al cliente
    $subject_customer = "✅ Confirmación de Solicitud - Recuperación de Documentación";
    $message_customer = "
    <!DOCTYPE html>
    <html lang='es'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { 
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
                line-height: 1.6; 
                color: #2c3e50; 
                background-color: #f8f9fa;
            }
            .container { 
                max-width: 600px; 
                margin: 0 auto; 
                background: #ffffff; 
                border-radius: 12px; 
                overflow: hidden;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            }
            .header { 
                background: linear-gradient(135deg, #016d86 0%, #014d61 100%); 
                color: white; 
                padding: 30px 40px;
                text-align: center;
            }
            .header h1 { 
                font-size: 24px; 
                font-weight: 600; 
                margin-bottom: 8px;
            }
            .header p { 
                opacity: 0.9; 
                font-size: 16px;
            }
            .content { 
                padding: 40px;
            }
            .greeting { 
                font-size: 18px; 
                color: #2c3e50; 
                margin-bottom: 25px;
            }
            .info-card { 
                background: #f8f9fa; 
                border-left: 4px solid #016d86; 
                padding: 20px; 
                margin: 25px 0; 
                border-radius: 0 8px 8px 0;
            }
            .info-row { 
                display: flex; 
                justify-content: space-between; 
                margin-bottom: 12px; 
                border-bottom: 1px solid #e9ecef; 
                padding-bottom: 8px;
            }
            .info-row:last-child { 
                border-bottom: none; 
                margin-bottom: 0; 
                padding-bottom: 0;
            }
            .info-label { 
                font-weight: 600; 
                color: #495057;
                min-width: 120px;
            }
            .info-value { 
                color: #016d86; 
                font-weight: 500;
            }
            .tracking-section { 
                background: #e8f4f8; 
                padding: 25px; 
                text-align: center; 
                border-radius: 8px; 
                margin: 25px 0;
            }
            .tracking-btn { 
                display: inline-block; 
                background: #016d86; 
                color: white; 
                padding: 12px 30px; 
                text-decoration: none; 
                border-radius: 6px; 
                font-weight: 600;
                margin-top: 15px;
                transition: background 0.3s;
            }
            .tracking-btn:hover { 
                background: #014d61; 
            }
            .footer { 
                background: #f8f9fa; 
                padding: 30px 40px; 
                text-align: center; 
                border-top: 1px solid #e9ecef;
            }
            .signature { 
                color: #6c757d; 
                font-style: italic;
            }
            .company-name { 
                color: #016d86; 
                font-weight: 700; 
                font-size: 18px;
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Solicitud Confirmada</h1>
                <p>Recuperación de Documentación Marítima</p>
            </div>
            
            <div class='content'>
                <div class='greeting'>
                    Estimado/a <strong>$customer_name</strong>,
                </div>
                
                <p>Hemos recibido correctamente su solicitud de recuperación de documentación. Nuestro equipo especializado se encargará de gestionar su trámite con la máxima eficiencia.</p>
                
                <div class='info-card'>
                    <div class='info-row'>
                        <span class='info-label'>Embarcación:</span>
                        <span class='info-value'>$vessel_name</span>
                    </div>
                    <div class='info-row'>
                        <span class='info-label'>Matrícula:</span>
                        <span class='info-value'>$vessel_registration</span>
                    </div>
                    <div class='info-row'>
                        <span class='info-label'>ID Trámite:</span>
                        <span class='info-value'>$tramite_id</span>
                    </div>
                    <div class='info-row'>
                        <span class='info-label'>Estado:</span>
                        <span class='info-value'>En revisión</span>
                    </div>
                </div>
                
                <div class='tracking-section'>
                    <h3 style='color: #016d86; margin-bottom: 10px;'>📋 Seguimiento en Tiempo Real</h3>
                    <p>Consulte el estado de su trámite cuando lo desee</p>
                    <a href='$tracking_url' class='tracking-btn'>Ver Estado del Trámite</a>
                </div>
                
                <p style='margin-top: 25px; color: #6c757d;'>
                    Le mantendremos informado sobre el progreso de su solicitud. Recibirá notificaciones por email en cada etapa del proceso.
                </p>
            </div>
            
            <div class='footer'>
                <div class='signature'>
                    Atentamente,<br>
                    <span class='company-name'>Equipo Tramitfy</span><br>
                    <small style='color: #adb5bd;'>Especialistas en Trámites Marítimos</small>
                </div>
            </div>
        </div>
    </body>
    </html>
    ";

    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: Tramitfy <info@tramitfy.es>'
    );

    $mail_sent_customer = wp_mail($customer_email, $subject_customer, $message_customer, $headers);
    error_log("Email cliente enviado a $customer_email: " . ($mail_sent_customer ? 'SI' : 'NO'));

    // Email al admin
    $admin_email = 'ipmgroup24@gmail.com';
    error_log("Preparando email admin para: $admin_email");
    $subject_admin = "🔔 Nueva Solicitud - Recuperación de Documentación [$tramite_id]";
    $message_admin = "
    <!DOCTYPE html>
    <html lang='es'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { 
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
                line-height: 1.6; 
                color: #2c3e50; 
                background-color: #f8f9fa;
            }
            .container { 
                max-width: 650px; 
                margin: 0 auto; 
                background: #ffffff; 
                border-radius: 12px; 
                overflow: hidden;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            }
            .header { 
                background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); 
                color: white; 
                padding: 25px 35px;
                text-align: center;
            }
            .header h1 { 
                font-size: 22px; 
                font-weight: 600; 
                margin-bottom: 5px;
            }
            .header .badge { 
                background: rgba(255,255,255,0.2); 
                padding: 4px 12px; 
                border-radius: 15px; 
                font-size: 13px; 
                font-weight: 500;
            }
            .content { 
                padding: 35px;
            }
            .alert-box { 
                background: #fff3cd; 
                border: 1px solid #ffeaa7; 
                border-left: 4px solid #f39c12; 
                padding: 20px; 
                border-radius: 8px; 
                margin-bottom: 25px;
            }
            .client-info { 
                background: #f8f9fa; 
                border-radius: 8px; 
                padding: 25px; 
                margin: 20px 0;
            }
            .info-grid { 
                display: grid; 
                grid-template-columns: 1fr 1fr; 
                gap: 15px; 
                margin-bottom: 15px;
            }
            .info-item { 
                background: white; 
                padding: 15px; 
                border-radius: 6px; 
                border: 1px solid #e9ecef;
            }
            .info-label { 
                font-size: 12px; 
                text-transform: uppercase; 
                color: #6c757d; 
                font-weight: 600; 
                margin-bottom: 5px;
            }
            .info-value { 
                font-size: 16px; 
                color: #2c3e50; 
                font-weight: 600;
            }
            .vessel-info { 
                grid-column: 1 / -1; 
                background: #e8f4f8; 
                border: 1px solid #b8daff;
            }
            .actions { 
                background: #f8f9fa; 
                padding: 25px; 
                text-align: center; 
                border-top: 1px solid #e9ecef;
            }
            .btn-primary { 
                display: inline-block; 
                background: #007bff; 
                color: white; 
                padding: 12px 25px; 
                text-decoration: none; 
                border-radius: 6px; 
                font-weight: 600; 
                margin: 0 10px;
                transition: background 0.3s;
            }
            .btn-secondary { 
                display: inline-block; 
                background: #6c757d; 
                color: white; 
                padding: 12px 25px; 
                text-decoration: none; 
                border-radius: 6px; 
                font-weight: 600; 
                margin: 0 10px;
                transition: background 0.3s;
            }
            .timestamp { 
                text-align: center; 
                color: #6c757d; 
                font-size: 14px; 
                margin-top: 20px; 
                padding-top: 20px; 
                border-top: 1px solid #e9ecef;
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Nueva Solicitud Recibida</h1>
                <div class='badge'>Recuperación de Documentación</div>
            </div>
            
            <div class='content'>
                <div class='alert-box'>
                    <strong>⚡ Acción requerida:</strong> Nueva solicitud de recuperación de documentación pendiente de revisión.
                </div>
                
                <div class='client-info'>
                    <h3 style='margin-bottom: 20px; color: #2c3e50;'>📋 Información del Cliente</h3>
                    
                    <div class='info-grid'>
                        <div class='info-item'>
                            <div class='info-label'>Cliente</div>
                            <div class='info-value'>$customer_name</div>
                        </div>
                        <div class='info-item'>
                            <div class='info-label'>Email</div>
                            <div class='info-value'>$customer_email</div>
                        </div>
                        <div class='info-item vessel-info'>
                            <div class='info-label'>Embarcación</div>
                            <div class='info-value'>$vessel_name</div>
                            <div style='margin-top: 8px; color: #6c757d;'>
                                <strong>Matrícula:</strong> $vessel_registration
                            </div>
                        </div>
                        <div class='info-item'>
                            <div class='info-label'>ID Trámite</div>
                            <div class='info-value' style='color: #dc3545;'>$tramite_id</div>
                        </div>
                        <div class='info-item'>
                            <div class='info-label'>Estado</div>
                            <div class='info-value' style='color: #f39c12;'>Pendiente Revisión</div>
                        </div>
                    </div>
                </div>
                
                <div style='background: #d1ecf1; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                    <h4 style='color: #0c5460; margin-bottom: 10px;'>💰 Información de Pago</h4>
                    <p style='color: #0c5460; margin: 0;'>
                        <strong>Importe:</strong> 94,95€ (servicios + tasas)<br>
                        <strong>Estado:</strong> Pagado correctamente
                    </p>
                </div>
            </div>
            
            <div class='actions'>
                <a href='https://46-202-128-35.sslip.io/tramites/$tramite_id' class='btn-primary'>
                    📊 Ver en Dashboard
                </a>
                <a href='https://46-202-128-35.sslip.io/seguimiento/$tramite_id' class='btn-secondary'>
                    👁️ Vista Cliente
                </a>
            </div>
            
            <div class='timestamp'>
                Solicitud recibida: " . date('d/m/Y H:i:s') . "
            </div>
        </div>
    </body>
    </html>
    ";

    $mail_sent_admin = wp_mail($admin_email, $subject_admin, $message_admin, $headers);
    error_log("Email admin enviado a $admin_email: " . ($mail_sent_admin ? 'SI' : 'NO'));
    error_log("Resultado final - Cliente: $mail_sent_customer, Admin: $mail_sent_admin");

    if ($mail_sent_customer && $mail_sent_admin) {
        error_log("=== AMBOS EMAILS ENVIADOS CORRECTAMENTE ===");
        wp_send_json_success(['message' => 'Emails enviados correctamente']);
    } else {
        error_log("=== ERROR: Cliente=$mail_sent_customer, Admin=$mail_sent_admin ===");
        wp_send_json_error(['message' => 'Error al enviar emails']);
    }

    wp_die();
}

add_shortcode('recuperar_documentacion_form', 'recuperar_documentacion_form_shortcode');

add_action('wp_ajax_rdoc_create_payment_intent', 'rdoc_create_payment_intent');
add_action('wp_ajax_nopriv_rdoc_create_payment_intent', 'rdoc_create_payment_intent');

add_action('wp_ajax_rdoc_send_emails', 'rdoc_send_emails');
add_action('wp_ajax_nopriv_rdoc_send_emails', 'rdoc_send_emails');
?>
