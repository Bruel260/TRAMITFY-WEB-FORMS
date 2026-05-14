<?php
/**
 * Formulario de Recuperación de Documentación Extraviada
 * Para WordPress - Shortcode: [recuperar_documentacion_form]
 */

// Asegurarse de que el archivo no sea accedido directamente
defined('ABSPATH') || exit;

error_log("=== RDOC FILE START ===");

// =====================================================
// CONFIGURACIÓN REDSYS - RECUPERAR DOCUMENTACIÓN
// =====================================================
if (!defined('RDOC_REDSYS_MODE')) define('RDOC_REDSYS_MODE', 'live');
if (!defined('RDOC_REDSYS_MERCHANT_CODE')) define('RDOC_REDSYS_MERCHANT_CODE', '363391103');
if (!defined('RDOC_REDSYS_TERMINAL')) define('RDOC_REDSYS_TERMINAL', '1');
if (!defined('RDOC_REDSYS_CURRENCY')) define('RDOC_REDSYS_CURRENCY', '978');

if (!defined('RDOC_REDSYS_SECRET_KEY')) {
    if (RDOC_REDSYS_MODE === 'test') {
        define('RDOC_REDSYS_SECRET_KEY', 'sq7HjrUOBfKmC576ILgskD5srU870gJ7');
    } else {
        define('RDOC_REDSYS_SECRET_KEY', 'ERDGGMADKbhFIngyRLnW6KrxEuKnjq9p');
    }
}

if (!defined('RDOC_REDSYS_SIGNATURE_VERSION')) define('RDOC_REDSYS_SIGNATURE_VERSION', 'HMAC_SHA256_V1');
if (!defined('RDOC_REDSYS_URL_TEST')) define('RDOC_REDSYS_URL_TEST', 'https://sis-t.redsys.es:25443/sis/realizarPago');
if (!defined('RDOC_REDSYS_URL_LIVE')) define('RDOC_REDSYS_URL_LIVE', 'https://sis.redsys.es/sis/realizarPago');
if (!defined('RDOC_REDSYS_URL_OK')) define('RDOC_REDSYS_URL_OK', 'https://tramitfy.es/pago-completado-documentacion/');
if (!defined('RDOC_REDSYS_URL_KO')) define('RDOC_REDSYS_URL_KO', 'https://tramitfy.es/recuperar-documentacion/');
if (!defined('RDOC_REDSYS_URL_NOTIFICATION')) define('RDOC_REDSYS_URL_NOTIFICATION', 'https://tramitfy.org/api/temporal/confirm');

// ✅ CONFIGURACIÓN DEL SERVICIO CON PREFIJO ÚNICO
define('RDOC_PRECIO_TOTAL', 94.95);
define('RDOC_TASA_1', 19.03);
define('RDOC_TASA_2', 7.62);
define('RDOC_TRAMITFY_API_URL', 'https://tramitfy.org/api/herramientas/documentacion/webhook');

// =====================================================
// FUNCIONES CORE REDSYS RDOC
// =====================================================
function rdoc_redsys_generate_signature($data) {
    $password_decoded = base64_decode(RDOC_REDSYS_SECRET_KEY);
    $order_id = $data['Ds_Order'] ?? $data['Ds_Merchant_Order'] ?? '';
    $l = ceil(strlen($order_id) / 8) * 8;
    $padded_order_id = $order_id . str_repeat("\0", $l - strlen($order_id));
    $encryption_key = substr(
        openssl_encrypt($padded_order_id, 'des-ede3-cbc', $password_decoded, OPENSSL_RAW_DATA, "\0\0\0\0\0\0\0\0"),
        0, $l
    );
    $string_to_sign = base64_encode(json_encode($data));
    $signature = hash_hmac('sha256', $string_to_sign, $encryption_key, true);
    return base64_encode($signature);
}

function rdoc_redsys_create_payment_form($order_data) {
    $redsys_url = (RDOC_REDSYS_MODE === 'test') ? RDOC_REDSYS_URL_TEST : RDOC_REDSYS_URL_LIVE;
    $params = [
        'Ds_Merchant_MerchantCode' => RDOC_REDSYS_MERCHANT_CODE,
        'Ds_Merchant_Terminal' => RDOC_REDSYS_TERMINAL,
        'Ds_Merchant_Order' => $order_data['order_id'],
        'Ds_Merchant_Amount' => $order_data['amount_cents'],
        'Ds_Merchant_Currency' => RDOC_REDSYS_CURRENCY,
        'Ds_Merchant_TransactionType' => '0',
        'Ds_Merchant_MerchantURL' => RDOC_REDSYS_URL_NOTIFICATION,
        'Ds_Merchant_UrlOK' => RDOC_REDSYS_URL_OK,
        'Ds_Merchant_UrlKO' => RDOC_REDSYS_URL_KO,
        'Ds_Merchant_MerchantName' => 'Tramitfy',
        'Ds_Merchant_ProductDescription' => 'Recuperar Documentacion Extraviada',
        'Ds_Merchant_ConsumerLanguage' => '001'
    ];
    $signature = rdoc_redsys_generate_signature($params);
    $merchant_parameters = base64_encode(json_encode($params));
    return [
        'url' => $redsys_url,
        'Ds_MerchantParameters' => $merchant_parameters,
        'Ds_SignatureVersion' => RDOC_REDSYS_SIGNATURE_VERSION,
        'Ds_Signature' => $signature
    ];
}

function rdoc_create_redsys_payment() {
    try {
        $order_id = !empty($_POST['orderId']) ? $_POST['orderId'] : str_pad(time(), 12, '0', STR_PAD_LEFT);
        if (strlen($order_id) > 12) { $order_id = substr($order_id, -12); }
        $amount = floatval($_POST['amount'] ?? RDOC_PRECIO_TOTAL);
        // Aplicar descuento cupón desde temporal (fuente de verdad)
        $jsAppliedDiscount = floatval($_POST['couponDiscount'] ?? 0);
        $rawAmount = $amount + $jsAppliedDiscount;
        $apiCouponUrl = 'https://tramitfy.org/api/temporal/coupon-for-order?orderId=' . urlencode($order_id);
        $apiResponse = @file_get_contents($apiCouponUrl);
        if ($apiResponse) {
            $couponData = json_decode($apiResponse, true);
            $serverDiscount = floatval($couponData['couponDiscount'] ?? 0);
            if ($serverDiscount > 0) {
                $amount = max(0, $rawAmount - $serverDiscount);
                error_log("RDOC REDSYS - Cupón: " . $couponData['couponCode'] . " raw=" . $rawAmount . " -" . $serverDiscount . "€ → final=" . $amount . "€");
            } else {
                $amount = $rawAmount;
            }
        }
        $amount_cents = strval(intval($amount * 100));

        $payment_data = rdoc_redsys_create_payment_form([
            'order_id' => $order_id,
            'amount_cents' => $amount_cents
        ]);

        wp_send_json_success([
            'orderId' => $order_id,
            'paymentData' => $payment_data
        ]);

    } catch (Exception $e) {
        wp_send_json_error(['message' => $e->getMessage()]);
    }
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

        error_log("=== RECUPERAR DOC: Procesando archivos indexados ===");
        error_log("🔍 DEBUG FILES: " . print_r($_FILES, true));
        error_log("🔍 DEBUG Claves FILES: " . implode(', ', array_keys($_FILES)));
        
        // Debug específico de archivos indexados
        for ($i = 0; $i < 10; $i++) {
            $key = "upload_dni_documento_$i";
            if (isset($_FILES[$key])) {
                error_log("✅ ENCONTRADO: \$_FILES['$key'] = " . $_FILES[$key]['name']);
            } else {
                error_log("❌ NO EXISTE: \$_FILES['$key']");
                if ($i > 2) break; // Solo verificar hasta que no haya más
            }
        }
        
        // Procesar archivos indexados como upload_dni_documento_0, upload_dni_documento_1, etc.
        $fileIndex = 0;
        while (isset($_FILES["upload_dni_documento_$fileIndex"])) {
            $fileData = $_FILES["upload_dni_documento_$fileIndex"];
            
            if ($fileData['error'] === UPLOAD_ERR_OK) {
                $uploaded_file = wp_handle_upload($fileData, ['test_form' => false]);
                error_log("Resultado wp_handle_upload archivo $fileIndex: " . print_r($uploaded_file, true));
                
                if (isset($uploaded_file['file'])) {
                    $uploadedFiles[] = [
                        'name' => $fileData['name'],
                        'filename' => basename($uploaded_file['file']),
                        'size' => $fileData['size'],
                        'path' => $uploaded_file['file']
                    ];
                    error_log("✅ Archivo indexado agregado [$fileIndex]: {$fileData['name']}");
                } else {
                    error_log("❌ wp_handle_upload falló para archivo $fileIndex: " . (isset($uploaded_file['error']) ? $uploaded_file['error'] : 'sin error'));
                }
            }
            
            $fileIndex++;
        }
        
        error_log("📊 Total archivos procesados: $fileIndex");

        $postData = [
            'customerName' => $formData['customerName'],
            'customerDNI' => $formData['customerDNI'],
            'customerEmail' => $formData['customerEmail'],
            'customerPhone' => $formData['customerPhone'],
            'vesselName' => $formData['vesselName'] ?? '',
            'vesselRegistration' => $formData['vesselRegistration'] ?? '',
            'totalPrice' => RDOC_PRECIO_TOTAL,
            'tasa1' => RDOC_TASA_1,
            'tasa2' => RDOC_TASA_2,
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

        // Agregar archivos con CURLFile (INDEXADOS CORRECTAMENTE)
        $dniFileIndex = 0;
        foreach ($uploadedFiles as $file) {
            if (file_exists($file['path'])) {
                // Usar nombre específico para firma, campos indexados para documentos
                if ($file['name'] === 'firma.png') {
                    $form_data['firma'] = new CURLFile($file['path'], 'image/png', $file['filename']);
                    error_log("✅ Firma agregada: {$file['filename']}");
                } else {
                    // ✅ CAMPOS INDEXADOS PARA MÚLTIPLES ARCHIVOS
                    $form_data["upload_dni_documento_$dniFileIndex"] = new CURLFile($file['path'], mime_content_type($file['path']), $file['filename']);
                    error_log("✅ DNI documento agregado [$dniFileIndex]: {$file['filename']}");
                    $dniFileIndex++;
                }
            } else {
                error_log("❌ Archivo NO existe: {$file['path']}");
            }
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, RDOC_TRAMITFY_API_URL);
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

    $totalTasas = RDOC_TASA_1 + RDOC_TASA_2;
    $honorariosBrutos = RDOC_PRECIO_TOTAL - $totalTasas;
    $honorariosNetos = round($honorariosBrutos / 1.21, 2);
    $iva = round($honorariosBrutos - $honorariosNetos, 2);

    $trackingUrl = $tramiteId ? 'https://tramitfy.org/seguimiento/' . $tramiteId : '#';
    $tramiteDisplayId = $tramiteReference ?? 'En proceso';

    // ============================================
    // EMAIL AL CLIENTE - Diseño mejorado y profesional
    // ============================================
    $customerSubject = 'Solicitud de Recuperación de Documentación - ' . $tramiteDisplayId;
    $customerMessage = "
    <!DOCTYPE html>
    <html>
    <head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <style>
        @media only screen and (max-width: 600px) {
            .container { width: 100% !important; }
            .mobile-padding { padding: 20px !important; }
        }
    </style>
    </head>
    <body style='margin: 0; padding: 0; font-family: \"Helvetica Neue\", Helvetica, Arial, sans-serif; background-color: #f7f9fc; color: #333333;'>
        
    <table width='100%' cellpadding='0' cellspacing='0' border='0' style='background-color: #f7f9fc; padding: 20px 0;'>
        <tr>
            <td align='center'>
                
                <!-- Main Container -->
                <table class='container' width='600' cellpadding='0' cellspacing='0' border='0' style='background-color: #ffffff; max-width: 600px; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.06);'>
                    
                    <!-- Header -->
                    <tr>
                        <td style='background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%); padding: 40px 30px; text-align: center;'>
                            <h1 style='margin: 0; color: #ffffff; font-size: 24px; font-weight: 600; letter-spacing: 0.5px;'>
                                TRAMITFY
                            </h1>
                            <p style='margin: 8px 0 0; color: rgba(255,255,255,0.9); font-size: 14px; font-weight: 400;'>
                                Servicios de tramitación marítima
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Content -->
                    <tr>
                        <td class='mobile-padding' style='padding: 40px 30px;'>
                            
                            <!-- Reference Number -->
                            <table width='100%' cellpadding='0' cellspacing='0' style='margin-bottom: 30px; background-color: #f8fafc; border-radius: 6px; border-left: 4px solid #1e40af;'>
                                <tr>
                                    <td style='padding: 20px 25px;'>
                                        <p style='margin: 0 0 5px; color: #64748b; font-size: 13px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;'>
                                            NÚMERO DE REFERENCIA
                                        </p>
                                        <p style='margin: 0; color: #1e40af; font-size: 18px; font-weight: 700; letter-spacing: 0.5px;'>
                                            {$tramiteDisplayId}
                                        </p>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Main Content -->
                            <p style='margin: 0 0 20px; color: #1f2937; font-size: 16px; line-height: 1.6;'>
                                Estimado/a <strong>{$customerName}</strong>,
                            </p>
                            
                            <p style='margin: 0 0 25px; color: #4b5563; font-size: 15px; line-height: 1.7;'>
                                Hemos recibido su solicitud de <strong>recuperación de documentación</strong>. Gracias por confiar en nosotros.
                                Revisaremos la documentación y procederemos con la tramitación.
                            </p>
                            
                            <!-- Details Box -->
                            <table width='100%' cellpadding='0' cellspacing='0' style='margin: 30px 0; background-color: #fefefe; border: 1px solid #e5e7eb; border-radius: 6px;'>
                                <tr>
                                    <td style='padding: 20px 25px;'>
                                        <table width='100%' cellpadding='8' cellspacing='0'>
                                            <tr>
                                                <td style='color: #6b7280; font-size: 14px; font-weight: 600; width: 35%;'>Estado:</td>
                                                <td style='color: #dc2626; font-size: 14px; font-weight: 600;'>Pendiente</td>
                                            </tr>
                                            <tr>
                                                <td style='color: #6b7280; font-size: 14px; font-weight: 600;'>Fecha:</td>
                                                <td style='color: #374151; font-size: 14px; font-weight: 600;'>" . date('d/m/Y H:i') . "</td>
                                            </tr>
                                            <tr>
                                                <td style='color: #6b7280; font-size: 14px; font-weight: 600;'>Importe:</td>
                                                <td style='color: #374151; font-size: 14px; font-weight: 600;'>" . number_format(RDOC_PRECIO_TOTAL, 2) . "€</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                            
                            <p style='margin: 30px 0 0; color: #4b5563; font-size: 15px; line-height: 1.7;'>
                                Le notificaremos por email del estado de su trámite en cada etapa del proceso. Si requiriéramos documentación adicional, nos pondremos en contacto con usted.
                            </p>
                            
                            <p style='margin: 25px 0 0; color: #1f2937; font-size: 15px;'>
                                Atentamente,<br>
                                <strong style='color: #1e40af;'>Equipo Tramitfy</strong><br>
                                <span style='color: #6b7280; font-size: 13px;'>Servicios de tramitación marítima</span>
                            </p>
                            
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style='background-color: #f8fafc; padding: 25px 30px; border-top: 1px solid #e5e7eb;'>
                            <p style='margin: 0; color: #6b7280; font-size: 13px; text-align: center; line-height: 1.5;'>
                                <strong style='color: #374151;'>Tramitfy</strong><br>
                                info@tramitfy.es | +34 689 170 273<br>
                                <span style='color: #9ca3af; font-size: 12px;'>Paseo Castellana 194 puerta B, Madrid, España</span>
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
    $adminSubject = 'Nueva Solicitud - ' . $tramiteDisplayId . ' - Recuperar Documentación';
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
                            <td align='right' style='color: #333; font-weight: 700; font-size: 16px;'>" . number_format(RDOC_PRECIO_TOTAL, 2) . " €</td>
                        </tr>
                        <tr style='border-top: 1px solid #ffe082;'>
                            <td colspan='2' style='padding-top: 12px; padding-bottom: 6px; color: #888; font-size: 13px; font-weight: 600;'>DESGLOSE:</td>
                        </tr>
                        <tr>
                            <td style='color: #666; padding-left: 15px;'>Tasa 1:</td>
                            <td align='right' style='color: #666;'>" . number_format(RDOC_TASA_1, 2) . " €</td>
                        </tr>
                        <tr>
                            <td style='color: #666; padding-left: 15px;'>Tasa 2:</td>
                            <td align='right' style='color: #666;'>" . number_format(RDOC_TASA_2, 2) . " €</td>
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
                    <h3 style='margin: 0 0 15px; color: #333; font-size: 16px;'>💳 PAGO REDSYS</h3>
                    <table width='100%' cellpadding='5' cellspacing='0' style='font-size: 13px; background-color: #f9f9f9; padding: 12px; border-radius: 4px;'>
                        <tr>
                            <td style='color: #666;'>Redsys Order ID:</td>
                            <td style='color: #333; font-family: monospace; font-size: 12px;'>{$formData['paymentIntentId']}</td>
                        </tr>
                        <tr>
                            <td style='color: #666;'>Modo Pago:</td>
                            <td style='color: #333; font-weight: 600;'>Redsys TPV</td>
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
                    <a href='https://tramitfy.org' style='display: inline-block; background: linear-gradient(135deg, #0066cc 0%, #004a99 100%); color: white; padding: 14px 32px; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 15px; box-shadow: 0 4px 10px rgba(0,102,204,0.3);'>
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

// Registrar handlers de WordPress AJAX (CRÍTICO)
add_action('wp_ajax_rdoc_create_redsys_payment', 'rdoc_create_redsys_payment');
add_action('wp_ajax_nopriv_rdoc_create_redsys_payment', 'rdoc_create_redsys_payment');

add_action('wp_ajax_rdoc_send_to_tramitfy', 'rdoc_send_to_tramitfy');
add_action('wp_ajax_nopriv_rdoc_send_to_tramitfy', 'rdoc_send_to_tramitfy');

// Fallback directo (mantener compatibilidad)
if (isset($_POST['action'])) {
    if ($_POST['action'] === 'rdoc_send_to_tramitfy') {
        rdoc_send_to_tramitfy();
    }
}

function recuperar_documentacion_form_shortcode() {

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
                <div class="rdoc-price-amount"><?php echo RDOC_PRECIO_TOTAL; ?>€</div>
                <div class="rdoc-price-detail">Incluye todas las tasas</div>
            </div>

            <div class="rdoc-benefits">
                <div class="rdoc-benefit">
                    <i class="fas fa-check"></i>
                    <span>Presentamos tu solicitud en menos de 24 h desde que la recibimos.</span>
                </div>
                <div class="rdoc-benefit">
                    <i class="fas fa-check"></i>
                    <span>Se presenta a Capitanía Marítima en un plazo máximo de 24 h</span>
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
                        <div class="rdoc-terms-wrapper">
                            <div class="rdoc-checkbox-wrapper">
                                <input type="checkbox" id="rdoc-consent-terms" class="rdoc-checkbox" required />
                                <label for="rdoc-consent-terms" class="rdoc-checkbox-label">
                                    <i class="fas fa-check-circle"></i>
                                    He leído y acepto los <a href="https://tramitfy.es/terminos-y-condiciones-de-uso-2/" target="_blank">Términos y Condiciones</a> y la <a href="https://tramitfy.es/politica-de-privacidad/" target="_blank">Política de Privacidad</a>
                                </label>
                            </div>
                        </div>

                        <div style="margin:16px 0;padding:14px;border:1px solid #e5e7eb;border-radius:8px;background:#f9fafb;">
                            <label style="display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:8px;">Código de descuento (opcional)</label>
                            <div style="display:flex;gap:8px;align-items:center;">
                                <input type="text" id="rdoc-coupon-input" placeholder="Introduce tu código" maxlength="30"
                                       style="flex:1;padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;text-transform:uppercase;">
                                <button type="button" id="rdoc-coupon-btn"
                                        style="padding:8px 16px;background:#016d86;color:white;border:none;border-radius:6px;font-size:14px;cursor:pointer;white-space:nowrap;">
                                    Aplicar
                                </button>
                            </div>
                            <div id="rdoc-coupon-msg" style="display:none;font-size:13px;margin-top:6px;"></div>
                        </div>

                        <button type="button" id="rdoc-submit-payment" class="rdoc-submit-btn rdoc-btn-large">
                            <i class="fas fa-lock"></i>
                            <span>Confirmar y Pagar <?php echo RDOC_PRECIO_TOTAL; ?>€</span>
                        </button>

                        <div id="rdoc-payment-message" style="display:none; padding:10px; margin:10px 0; border-radius:8px; text-align:center;"></div>

                        <div class="rdoc-security-badges">
                            <div class="rdoc-security-badge">
                                <i class="fas fa-lock"></i>
                                <span>Pago 100% Seguro · Cifrado SSL · CaixaBank</span>
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
    
    <!-- Cupón hidden inputs -->
    <input type="hidden" id="rdoc-coupon-code" name="coupon_code" value="">
    <input type="hidden" id="rdoc-coupon-discount" name="coupon_discount" value="0">

    <!-- Formulario oculto para Redsys -->
    <form id="rdoc-redsys-form" action="" method="POST" style="display:none;">
        <input type="hidden" name="Ds_SignatureVersion" id="rdoc-Ds_SignatureVersion">
        <input type="hidden" name="Ds_MerchantParameters" id="rdoc-Ds_MerchantParameters">
        <input type="hidden" name="Ds_Signature" id="rdoc-Ds_Signature">
    </form>

    <script>
        let rdocIsSubmitting = false;
        // Sistema de almacenamiento de archivos unificado (como hoja-asiento)
        const fileStorage = {
            'upload-dni-documento': []
        };
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

        // ====== INICIALIZACIÓN ======
        document.addEventListener('DOMContentLoaded', async function() {
            console.log('🚀 DOMContentLoaded - Iniciando formulario recuperar documentación (Redsys)');

            setTimeout(function() {
                console.log('Inicializando componentes...');
                rdocInitializeFileUpload();
                rdocSetupNavigation();
                rdocSetupPaymentButton();
                console.log('✅ Inicialización completa (Redsys mode)');
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
                console.log('📄 Navegando a página 3 (Pago Redsys)');
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

            console.log('Archivos subidos:', fileStorage['upload-dni-documento'].length);
            if (fileStorage['upload-dni-documento'].length === 0) {
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

        // ====== FIRMA DIGITAL MEJORADA ======
        let rdocLastPoint = null;
        let rdocCurrentPath = [];
        
        function rdocInitializeSignature() {
            console.log('✍️ Inicializando canvas de firma mejorada...');

            rdocSignatureCanvas = document.getElementById('rdoc-signature-canvas');
            if (!rdocSignatureCanvas) {
                console.error('Canvas no encontrado');
                return;
            }

            const placeholder = document.getElementById('rdoc-signature-placeholder');

            rdocSignatureCtx = rdocSignatureCanvas.getContext('2d');
            rdocSignatureCtx.strokeStyle = '#1a1a1a';
            rdocSignatureCtx.lineWidth = 2.8;
            rdocSignatureCtx.lineCap = 'round';
            rdocSignatureCtx.lineJoin = 'round';
            rdocSignatureCtx.globalCompositeOperation = 'source-over';
            rdocSignatureCtx.imageSmoothingEnabled = true;

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

            console.log('✅ Canvas de firma mejorada inicializado correctamente');
        }

        function rdocGetCanvasPoint(e) {
            const rect = rdocSignatureCanvas.getBoundingClientRect();
            const scaleX = rdocSignatureCanvas.width / rect.width;
            const scaleY = rdocSignatureCanvas.height / rect.height;
            return {
                x: (e.clientX - rect.left) * scaleX,
                y: (e.clientY - rect.top) * scaleY
            };
        }

        function rdocStartDrawing(e) {
            rdocIsDrawing = true;
            rdocCurrentPath = [];

            const placeholder = document.getElementById('rdoc-signature-placeholder');
            if (placeholder) {
                placeholder.classList.add('hidden');
            }

            const point = rdocGetCanvasPoint(e);
            rdocLastPoint = point;
            rdocCurrentPath.push(point);

            rdocSignatureCtx.beginPath();
            rdocSignatureCtx.moveTo(point.x, point.y);
        }

        function rdocDraw(e) {
            if (!rdocIsDrawing) return;

            const currentPoint = rdocGetCanvasPoint(e);
            rdocCurrentPath.push(currentPoint);

            // Solo dibujar curva suave si tenemos suficientes puntos
            if (rdocCurrentPath.length >= 3) {
                const len = rdocCurrentPath.length;
                const p0 = rdocCurrentPath[len - 3];
                const p1 = rdocCurrentPath[len - 2]; 
                const p2 = rdocCurrentPath[len - 1];

                // Calcular punto de control para curva cuadrática suave
                const cp = {
                    x: p1.x,
                    y: p1.y
                };

                // Punto medio entre p1 y p2
                const midPoint = {
                    x: (p1.x + p2.x) / 2,
                    y: (p1.y + p2.y) / 2
                };

                // Dibujar curva suave usando quadraticCurveTo
                rdocSignatureCtx.lineWidth = rdocCalculateLineWidth(p0, p2);
                rdocSignatureCtx.beginPath();
                rdocSignatureCtx.moveTo(rdocLastPoint.x, rdocLastPoint.y);
                rdocSignatureCtx.quadraticCurveTo(cp.x, cp.y, midPoint.x, midPoint.y);
                rdocSignatureCtx.stroke();

                rdocLastPoint = midPoint;
            } else if (rdocCurrentPath.length === 2) {
                // Para los primeros puntos, usar línea simple
                rdocSignatureCtx.beginPath();
                rdocSignatureCtx.moveTo(rdocLastPoint.x, rdocLastPoint.y);
                rdocSignatureCtx.lineTo(currentPoint.x, currentPoint.y);
                rdocSignatureCtx.stroke();
                rdocLastPoint = currentPoint;
            }

            if (!rdocHasSignature) {
                rdocHasSignature = true;
                console.log('✅ Primera firma detectada');
            }
        }

        function rdocCalculateLineWidth(p1, p2) {
            // Calcular grosor dinámico basado en velocidad del trazo
            const distance = Math.sqrt(Math.pow(p2.x - p1.x, 2) + Math.pow(p2.y - p1.y, 2));
            const minWidth = 1.8;
            const maxWidth = 3.5;
            
            // Líneas más gruesas para movimientos más lentos (más realista)
            const speed = Math.min(distance / 5, 10);
            const width = Math.max(minWidth, maxWidth - (speed * 0.2));
            
            return width;
        }

        function rdocStopDrawing() {
            if (!rdocIsDrawing) return;
            
            rdocIsDrawing = false;
            rdocCurrentPath = [];
            rdocLastPoint = null;
            rdocSignatureCtx.closePath();
        }

        function rdocClearSignature() {
            if (!rdocSignatureCanvas || !rdocSignatureCtx) return;

            rdocSignatureCtx.clearRect(0, 0, rdocSignatureCanvas.width, rdocSignatureCanvas.height);
            rdocHasSignature = false;
            rdocCurrentPath = [];
            rdocLastPoint = null;

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

                    const isDuplicate = fileStorage['upload-dni-documento'].some(
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

                    fileStorage['upload-dni-documento'].push(file);
                    console.log(`✅ Archivo agregado [${fileStorage['upload-dni-documento'].length}]:`, file.name);
                });

                console.log('📦 Total de archivos en array:', fileStorage['upload-dni-documento'].length);
                console.log('📦 Array completo:', fileStorage['upload-dni-documento'].map(f => f.name));

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
            console.log('📦 Archivos para renderizar:', fileStorage['upload-dni-documento'].length);

            if (fileStorage['upload-dni-documento'].length === 0) {
                console.log('ℹ️ No hay archivos, limpiando lista');
                list.innerHTML = '';
                list.style.display = 'none';
                return;
            }

            list.style.display = 'flex';

            const html = fileStorage['upload-dni-documento'].map((file, index) => {
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
            fileStorage['upload-dni-documento'].splice(index, 1);
            rdocRenderFiles();
        }

        // ====== OPTIMIZACIONES MÓVILES (copiado de hoja-asiento) ======
        function isMobile() {
            return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent) || window.innerWidth < 768;
        }

        async function compressImageForMobile(file, maxSizeMB = 2) {
            return new Promise((resolve) => {
                if (!file.type.startsWith('image/')) {
                    resolve(file);
                    return;
                }

                const needsCompression = isMobile() || file.size > (3 * 1024 * 1024);
                if (!needsCompression) {
                    resolve(file);
                    return;
                }

                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d');
                const img = new Image();
                
                img.onload = function() {
                    let { width, height } = img;
                    const maxDimension = isMobile() ? 1920 : 2048;
                    
                    if (width > height && width > maxDimension) {
                        height = (height * maxDimension) / width;
                        width = maxDimension;
                    } else if (height > maxDimension) {
                        width = (width * maxDimension) / height;
                        height = maxDimension;
                    }
                    
                    canvas.width = width;
                    canvas.height = height;
                    
                    ctx.drawImage(img, 0, 0, width, height);
                    
                    let quality = 0.8;
                    let blob = null;
                    
                    const compress = () => {
                        canvas.toBlob((newBlob) => {
                            if (newBlob && newBlob.size <= maxSizeMB * 1024 * 1024) {
                                const newFile = new File([newBlob], file.name, {
                                    type: 'image/jpeg',
                                    lastModified: Date.now()
                                });
                                resolve(newFile);
                            } else if (quality > 0.1) {
                                quality -= 0.1;
                                compress();
                            } else {
                                resolve(file);
                            }
                        }, 'image/jpeg', quality);
                    };
                    
                    compress();
                };
                
                img.onerror = () => resolve(file);
                img.src = URL.createObjectURL(file);
            });
        }

        function validateFileSize(file) {
            const maxSize = isMobile() ? 10 * 1024 * 1024 : 20 * 1024 * 1024;
            
            if (file.size > maxSize) {
                const maxSizeMB = Math.round(maxSize / (1024 * 1024));
                const currentSizeMB = (file.size / (1024 * 1024)).toFixed(1);
                alert(`⚠️ Archivo "${file.name}" demasiado grande\\n\\n📱 Máximo: ${maxSizeMB}MB\\n📊 Actual: ${currentSizeMB}MB`);
                return false;
            }
            return true;
        }

        // GESTIÓN PROFESIONAL DE POPUPS (copiado de hoja-asiento)
        let hiddenPopups = [];
        
        function hideAllPopups() {
            console.log('🚫 Ocultando todos los popups...');
            
            // Popups comunes de WordPress/Elementor
            const popupSelectors = [
                '.elementor-popup-modal',
                '.eael-modal',
                '.pp-modal',
                '.elementor-lightbox',
                '[id*="popup"]',
                '[class*="popup"]',
                '[class*="modal"]',
                '[id*="modal"]'
            ];
            
            popupSelectors.forEach(selector => {
                document.querySelectorAll(selector).forEach(popup => {
                    if (popup.style.display !== 'none') {
                        hiddenPopups.push({
                            element: popup,
                            originalDisplay: popup.style.display,
                            originalVisibility: popup.style.visibility,
                            originalZIndex: popup.style.zIndex
                        });
                        
                        popup.style.display = 'none';
                        popup.style.visibility = 'hidden';
                        popup.style.zIndex = '-1';
                    }
                });
            });
            
            console.log(`🚫 ${hiddenPopups.length} popups ocultados`);
        }
        
        function restoreAllPopups() {
            console.log('🔄 Restaurando popups...');
            
            hiddenPopups.forEach(popupInfo => {
                const popup = popupInfo.element;
                popup.style.display = popupInfo.originalDisplay;
                popup.style.visibility = popupInfo.originalVisibility;
                popup.style.zIndex = popupInfo.originalZIndex;
            });
            
            hiddenPopups = [];
            console.log('✅ Popups restaurados');
        }

        // ====== STRIPE PAYMENT ======
        // Helper: Convertir File a base64
        function rdocFileToBase64(file) {
            return new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.onload = () => resolve(reader.result);
                reader.onerror = reject;
                reader.readAsDataURL(file);
            });
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

        // ====== SETUP PAYMENT BUTTON (REDSYS) ======
        function rdocSetupPaymentButton() {
            const paymentBtn = document.getElementById('rdoc-submit-payment');
            if (!paymentBtn) {
                console.error('❌ Botón de pago no encontrado');
                return;
            }

            paymentBtn.addEventListener('click', async function(e) {
                e.preventDefault();
                console.log('💳 RDOC: Botón de pago clickeado - Flujo Redsys');

                if (!rdocValidatePage3()) return;

                if (rdocIsSubmitting) {
                    console.warn('⚠️ Envío ya en proceso');
                    return;
                }
                rdocIsSubmitting = true;

                const submitButton = this;
                const originalHTML = submitButton.innerHTML;
                submitButton.disabled = true;
                submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span>Conectando con pasarela de pago...</span>';

                try {
                    // 1. Generar OrderID
                    const timestamp = Math.floor(Date.now() / 10000) * 10;
                    const generatedOrderId = '00' + timestamp.toString().padStart(10, '0');
                    console.log('RDOC: OrderID generado:', generatedOrderId);

                    // 2. Recopilar archivos en base64
                    const filesArray = [];
                    const fileCategories = Object.keys(fileStorage);
                    for (const category of fileCategories) {
                        const files = fileStorage[category] || [];
                        for (const file of files) {
                            const base64 = await rdocFileToBase64(file);
                            filesArray.push({
                                fieldName: category,
                                fileName: file.name,
                                mimeType: file.type,
                                data: base64
                            });
                        }
                    }

                    // Añadir firma
                    const signatureData = rdocGetSignatureDataURL ? rdocGetSignatureDataURL() : null;
                    if (signatureData) {
                        filesArray.push({
                            fieldName: 'firma',
                            fileName: 'firma.png',
                            mimeType: 'image/png',
                            data: signatureData
                        });
                    }

                    console.log('RDOC: Archivos preparados:', filesArray.length);

                    // 3. Enviar datos al API temporal
                    const captureData = {
                        orderId: generatedOrderId,
                        tramiteType: 'recuperar-documentacion',
                        files: filesArray,
                        customerData: {
                            name: document.getElementById('rdoc-name').value.trim(),
                            dni: document.getElementById('rdoc-dni').value.trim(),
                            email: document.getElementById('rdoc-email').value.trim(),
                            phone: document.getElementById('rdoc-phone').value.trim()
                        },
                        serviceData: {
                            vesselName: document.getElementById('rdoc-vessel-name')?.value?.trim() || '',
                            vesselRegistration: document.getElementById('rdoc-vessel-registration')?.value?.trim() || ''
                        },
                        pricing: {
                            amount: <?php echo RDOC_PRECIO_TOTAL; ?>,
                            basePrice: <?php echo RDOC_PRECIO_TOTAL; ?>,
                            tasa1: <?php echo RDOC_TASA_1; ?>,
                            tasa2: <?php echo RDOC_TASA_2; ?>
                        },
                        metadata: {
                            timestamp: Date.now(),
                            formId: 'rdoc'
                        },
                        ga_client_id: (function() { var m = document.cookie.match(/_ga=GA\d+\.\d+\.(.+)/); return m ? m[1] : ''; })(),
                        gclid: new URLSearchParams(window.location.search).get('gclid') || sessionStorage.getItem('gclid') || '',
                        ga_session_id: (function() { var c = document.cookie.match(/_ga_[A-Z0-9]+=GS\d+\.\d+\.(.+?)(?:\.|$)/); return c ? c[1] : ''; })(),
                        couponCode: document.getElementById('rdoc-coupon-code')?.value || '',
                        couponDiscount: parseFloat(document.getElementById('rdoc-coupon-discount')?.value || 0)
                    };

                    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span>Almacenando datos...</span>';

                    const captureResponse = await fetch('https://tramitfy.org/api/temporal/capture', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(captureData)
                    });

                    const captureResult = await captureResponse.json();
                    console.log('RDOC: Respuesta temporal:', captureResult);

                    if (!captureResult.success) {
                        throw new Error(captureResult.error || 'Error capturando datos temporales');
                    }

                    // 4. Crear pago Redsys
                    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span>Redirigiendo a pasarela segura...</span>';

                    const rdocCouponDiscount = parseFloat(document.getElementById('rdoc-coupon-discount')?.value || 0);
                    const rdocFinalAmount = Math.max(0, <?php echo RDOC_PRECIO_TOTAL; ?> - rdocCouponDiscount);
                    const paymentData = new FormData();
                    paymentData.append('action', 'rdoc_create_redsys_payment');
                    paymentData.append('amount', rdocFinalAmount);
                    paymentData.append('orderId', generatedOrderId);
                    paymentData.append('couponDiscount', document.getElementById('rdoc-coupon-discount')?.value || '0');

                    const ajaxUrl = '<?php echo admin_url("admin-ajax.php"); ?>';
                    const response = await fetch(ajaxUrl, {
                        method: 'POST',
                        body: paymentData
                    });

                    const result = await response.json();

                    if (result.success) {
                        console.log('RDOC: Pago Redsys creado, redirigiendo a pasarela...');

                        const form = document.getElementById('rdoc-redsys-form');
                        form.action = result.data.paymentData.url;
                        document.getElementById('rdoc-Ds_SignatureVersion').value = result.data.paymentData.Ds_SignatureVersion;
                        document.getElementById('rdoc-Ds_MerchantParameters').value = result.data.paymentData.Ds_MerchantParameters;
                        document.getElementById('rdoc-Ds_Signature').value = result.data.paymentData.Ds_Signature;

                        setTimeout(() => {
                            form.submit();
                        }, 1500);
                    } else {
                        throw new Error(result.data?.message || 'Error al crear el pago');
                    }

                } catch (error) {
                    console.error('RDOC Error:', error);

                    rdocShowNotification(
                        'Error al procesar el pago:<br><br><strong>' + error.message + '</strong>',
                        'error',
                        'Error de Pago'
                    );

                    submitButton.disabled = false;
                    submitButton.innerHTML = originalHTML;
                    rdocIsSubmitting = false;
                }
            });

            console.log('✅ Event listener de pago Redsys configurado');
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
                paymentIntentId: 'redsys_pending'
            };

            formData.append('action', 'rdoc_send_to_tramitfy');
            formData.append('formData', JSON.stringify(data));

            // Procesar archivos con optimizaciones móviles (PATRÓN CORRECTO de renovacion-permiso)
            const documentFiles = fileStorage['upload-dni-documento'] || [];
            console.log(`📦 Total archivos a procesar: ${documentFiles.length}`);
            
            let processedFiles = 0;
            for (let index = 0; index < documentFiles.length; index++) {
                const file = documentFiles[index];
                console.log(`🔍 Validando archivo ${index}:`, file.name, `${Math.round(file.size/1024)}KB`);
                
                if (!validateFileSize(file)) {
                    console.warn(`⚠️ Archivo ${index} descartado por tamaño:`, file.name);
                    continue;
                }
                
                console.log(`📎 Procesando archivo ${index}:`, file.name, `${Math.round(file.size/1024)}KB`);
                const processedFile = await compressImageForMobile(file);
                console.log(`✅ Archivo ${index} procesado:`, processedFile.name, `${Math.round(processedFile.size/1024)}KB`);
                formData.append(`upload_dni_documento_${index}`, processedFile);
                processedFiles++;
            }
            
            console.log(`📊 Archivos finalmente enviados: ${processedFiles}/${documentFiles.length}`);

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

    // Cupón RDOC
    function updatePriceWithCoupon_rdoc(discount) {
        const basePrice = <?php echo RDOC_PRECIO_TOTAL; ?>;
        const newPrice = Math.max(0, basePrice - discount);
        const el = document.querySelector('.rdoc-price-amount');
        if (el) el.textContent = newPrice.toFixed(2).replace('.', ',') + '€';
    }

    (function() {
        document.addEventListener('DOMContentLoaded', function() {
            const btn = document.getElementById('rdoc-coupon-btn');
            const input = document.getElementById('rdoc-coupon-input');
            const msg = document.getElementById('rdoc-coupon-msg');
            const hiddenCode = document.getElementById('rdoc-coupon-code');
            const hiddenDiscount = document.getElementById('rdoc-coupon-discount');
            if (!btn) return;
            btn.addEventListener('click', async function() {
                const code = (input?.value || '').trim().toUpperCase();
                if (!code) return;
                btn.disabled = true; btn.textContent = '...';
                msg.style.display = 'none';
                try {
                    const r = await fetch('https://tramitfy.org/api/coupons/validate', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ code })
                    });
                    const data = await r.json();
                    if (data.valid) {
                        hiddenCode.value = data.code;
                        hiddenDiscount.value = data.clientDiscount;
                        updatePriceWithCoupon_rdoc(data.clientDiscount);
                        msg.style.cssText = 'display:block;color:#16a34a;font-weight:600;';
                        msg.textContent = '✓ ' + data.message;
                        btn.textContent = '✓'; btn.style.background = '#16a34a';
                        input.disabled = true; btn.disabled = true;
                    } else {
                        msg.style.cssText = 'display:block;color:#dc2626;';
                        msg.textContent = '✗ ' + (data.error || 'Cupón no válido');
                        btn.disabled = false; btn.textContent = 'Aplicar';
                    }
                } catch(e) {
                    msg.style.cssText = 'display:block;color:#dc2626;';
                    msg.textContent = 'Error de conexión.';
                    btn.disabled = false; btn.textContent = 'Aplicar';
                }
            });
        });
    })();
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

    $tracking_url = 'https://tramitfy.org/seguimiento/' . $tramite_id;

    // Email al cliente
    $subject_customer = "Confirmación de Solicitud - Recuperación de Documentación";
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
                
                <p>Hemos recibido correctamente su solicitud de recuperación de documentación marítima. Nuestro equipo se encarga de gestionar la tramitación y le mantendremos informado del progreso.</p>
                
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
                
                <p style='margin-top: 25px; color: #6c757d;'>
                    Le notificaremos por email del estado de su trámite en cada etapa del proceso. Si requiriéramos documentación adicional, nos pondremos en contacto con usted.
                </p>
            </div>
            
            <div class='footer'>
                <div class='signature'>
                    Atentamente,<br>
                    <span class='company-name'>Equipo Tramitfy</span><br>
                    <small style='color: #888;'>Servicios de tramitación marítima</small>
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
                <a href='https://tramitfy.org/tramites/$tramite_id' class='btn-primary'>
                    📊 Ver en Dashboard
                </a>
                <a href='https://tramitfy.org/seguimiento/$tramite_id' class='btn-secondary'>
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

add_action('wp_ajax_rdoc_create_redsys_payment', 'rdoc_create_redsys_payment');
add_action('wp_ajax_nopriv_rdoc_create_redsys_payment', 'rdoc_create_redsys_payment');

add_action('wp_ajax_rdoc_send_emails', 'rdoc_send_emails');
add_action('wp_ajax_nopriv_rdoc_send_emails', 'rdoc_send_emails');
