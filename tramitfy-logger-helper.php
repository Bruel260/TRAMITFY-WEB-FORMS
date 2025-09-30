<?php
// Helper function para enviar logs a la API de Tramitfy
function tramitfy_log($message, $data = null) {
    $url = 'https://46-202-128-35.sslip.io/api/debug-log';
    $payload = json_encode(array(
        'message' => $message,
        'data' => $data
    ));
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 2);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    curl_exec($ch);
    curl_close($ch);
}
