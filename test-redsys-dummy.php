<?php
/**
 * TEST REDSYS DUMMY - ALGORITMO OFICIAL
 * 
 * Archivo de test para probar TPV Redsys con algoritmo oficial
 * Basado en código exacto proporcionado por soporte técnico
 * 
 * @version 1.0 - ALGORITMO OFICIAL REDSYS
 * @created 2025-11-09
 */

// =====================================================
// CONFIGURACIÓN REDSYS - DATOS OFICIALES
// =====================================================

define('REDSYS_MERCHANT_CODE', '363391103');
define('REDSYS_TERMINAL', '1');
define('REDSYS_SECRET_KEY', 'sq7HjrUOBfKmC576ILgskD5srU870gJ7');
define('REDSYS_CURRENCY', '978'); // EUR
define('REDSYS_URL_TEST', 'https://sis-t.redsys.es:25443/sis/realizarPago');

// URLs de retorno
define('REDSYS_URL_OK', 'https://tramitfy.es/wp-content/themes/xtra/test-redsys-dummy.php?result=ok');
define('REDSYS_URL_KO', 'https://tramitfy.es/wp-content/themes/xtra/test-redsys-dummy.php?result=ko');
define('REDSYS_URL_NOTIFICATION', 'https://tramitfy.es/wp-content/themes/xtra/test-redsys-dummy.php?notification=1');

// =====================================================
// FUNCIÓN DE FIRMA OFICIAL REDSYS
// =====================================================

function generateRedsysSignature($params, $orderId) {
    // Decodificacion en Base64 de la contraseña del comercio
    $password_decoded = base64_decode(REDSYS_SECRET_KEY);
    
    // Deteccion de la version de PHP del servidor
    $php_version = substr(phpversion(), 0, 1);
    
    // Generacion de la clave de cifrado según versión PHP - CÓDIGO OFICIAL
    switch ($php_version) {
        case "5":
            // Codigo para PHP5
            $bytes = array(0,0,0,0,0,0,0,0); //byte [] IV = {0, 0, 0, 0, 0, 0, 0, 0}
            $iv = implode(array_map("chr", $bytes));
            $clave_cifrado = mcrypt_encrypt(MCRYPT_3DES, $password_decoded, $orderId, MCRYPT_MODE_CBC, $iv);
            break;
            
        case "7":
        case "8":
        default:
            // Codigo para PHP7+ - ALGORITMO OFICIAL
            $l = ceil(strlen($orderId) / 8) * 8;
            $clave_cifrado = substr(
                openssl_encrypt(
                    $orderId . str_repeat("\0", $l - strlen($orderId)), 
                    'des-ede3-cbc', 
                    $password_decoded, 
                    OPENSSL_RAW_DATA, 
                    "\0\0\0\0\0\0\0\0"
                ), 
                0, 
                $l
            );
            break;
    }
    
    // Cadena a firmar (MerchantParameters en base64)
    $cadena_a_firmar = base64_encode(json_encode($params));
    
    // Generación de la firma SHA-256 - CÓDIGO OFICIAL
    $firma = hash_hmac('sha256', $cadena_a_firmar, $clave_cifrado, true);
    
    // Codificacion en Base64 de la firma
    $firma_codificada = base64_encode($firma);
    
    return $firma_codificada;
}

// =====================================================
// LÓGICA PRINCIPAL
// =====================================================

// Mostrar resultado si viene de TPV
if (isset($_GET['result'])) {
    echo "<h2>Resultado del TPV:</h2>";
    if ($_GET['result'] == 'ok') {
        echo "<p style='color: green;'>✅ PAGO EXITOSO</p>";
    } else {
        echo "<p style='color: red;'>❌ PAGO CANCELADO O ERROR</p>";
    }
    echo "<p><a href='?'>← Volver a probar</a></p>";
    exit;
}

// Procesar notificación
if (isset($_GET['notification'])) {
    echo "OK"; // Respuesta para Redsys
    exit;
}

// Generar test de pago - MÚLTIPLES ESTRATEGIAS PARA EVITAR SIS0075
// SIS0075 = Order ID duplicado o inválido

// ESTRATEGIA 1: Microtime único
$microtime = microtime(true);
$orderId1 = substr(str_replace('.', '', $microtime), -8);

// ESTRATEGIA 2: Random con prefijo
$orderId2 = 'T' . str_pad(rand(1000000, 9999999), 7, '0', STR_PAD_LEFT);

// ESTRATEGIA 3: Timestamp + Random
$orderId3 = date('His') . rand(100, 999);

// USAR ESTRATEGIA 1 PRIMERO
$orderId = $orderId1;
$amount = '1000'; // 10.00€

// Parámetros exactos según documentación oficial
$params = [
    'Ds_Merchant_MerchantCode' => REDSYS_MERCHANT_CODE,
    'Ds_Merchant_Terminal' => REDSYS_TERMINAL,
    'Ds_Merchant_Order' => $orderId,
    'Ds_Merchant_Amount' => $amount,
    'Ds_Merchant_Currency' => REDSYS_CURRENCY,
    'Ds_Merchant_TransactionType' => '0',
    'Ds_Merchant_MerchantURL' => REDSYS_URL_NOTIFICATION,
    'Ds_Merchant_UrlOK' => REDSYS_URL_OK,
    'Ds_Merchant_UrlKO' => REDSYS_URL_KO,
    'Ds_Merchant_MerchantName' => 'Tramitfy Test',
    'Ds_Merchant_ProductDescription' => 'Test TPV',
    'Ds_Merchant_ConsumerLanguage' => '001'
];

// Generar firma con algoritmo oficial
$signature = generateRedsysSignature($params, $orderId);
$merchantParameters = base64_encode(json_encode($params));

// Debug completo
echo "<h1>🧪 TEST REDSYS - ALGORITMO OFICIAL</h1>";
echo "<h2>📋 Configuración:</h2>";
echo "<p><strong>Comercio:</strong> " . REDSYS_MERCHANT_CODE . "</p>";
echo "<p><strong>Terminal:</strong> " . REDSYS_TERMINAL . "</p>";
echo "<p><strong>Order ID:</strong> " . $orderId . "</p>";
echo "<p><strong>Amount:</strong> " . $amount . " (10,00€)</p>";
echo "<p><strong>PHP Version:</strong> " . phpversion() . "</p>";

echo "<h2>🔍 Debug Técnico:</h2>";
echo "<p><strong>Merchant Parameters:</strong> " . substr($merchantParameters, 0, 50) . "...</p>";
echo "<p><strong>Signature:</strong> " . $signature . "</p>";

echo "<h2>🔧 Estrategias Order ID:</h2>";
echo "<p><strong>Estrategia 1 (Actual):</strong> " . $orderId1 . " (microtime)</p>";
echo "<p><strong>Estrategia 2:</strong> " . $orderId2 . " (prefijo + random)</p>";
echo "<p><strong>Estrategia 3:</strong> " . $orderId3 . " (timestamp + random)</p>";
echo "<p><em>Si persiste SIS0075, cambiar manualmente en el código</em></p>";

// Mostrar parámetros decodificados
echo "<h2>📄 Parámetros Decodificados:</h2>";
echo "<pre>" . print_r($params, true) . "</pre>";

?>

<h2>💳 Formulario de Pago Redsys</h2>
<form method="POST" action="<?php echo REDSYS_URL_TEST; ?>">
    <input type="hidden" name="Ds_SignatureVersion" value="HMAC_SHA256_V1">
    <input type="hidden" name="Ds_MerchantParameters" value="<?php echo $merchantParameters; ?>">
    <input type="hidden" name="Ds_Signature" value="<?php echo $signature; ?>">
    
    <button type="submit" style="background: #007cba; color: white; padding: 15px 30px; border: none; border-radius: 5px; font-size: 16px; cursor: pointer;">
        🚀 IR AL TPV REDSYS (Test)
    </button>
</form>

<h2>⚠️ Expectativas:</h2>
<ul>
    <li>✅ <strong>Order ID debe aparecer:</strong> <?php echo $orderId; ?></li>
    <li>✅ <strong>Amount debe ser:</strong> 10,00€ (no 0,00€)</li>
    <li>✅ <strong>Error SIS0042 resuelto</strong></li>
    <li>✅ <strong>Pantalla de pago funcional</strong></li>
</ul>

<p><small>🔗 <strong>Test URL:</strong> https://tramitfy.es/wp-content/themes/xtra/test-redsys-dummy.php</small></p>