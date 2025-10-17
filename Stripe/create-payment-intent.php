<?php
// Asegúrate de que este archivo solo pueda ser ejecutado desde WordPress
defined('ABSPATH') || exit;

// Carga la librería de Stripe
require_once 'path/to/stripe-php/init.php'; // Ajusta la ruta según donde instales la librería stripe-php

// Configura tu clave secreta de Stripe (úsala desde el modo seguro y nunca en el frontend)
\Stripe\Stripe::setApiKey('YOUR_STRIPE_TEST_SECRET_KEY_HERE'); // Reemplaza con tu clave secreta

// Configura los headers para recibir JSON
header('Content-Type: application/json');

// Crea el PaymentIntent
try {
    $paymentIntent = \Stripe\PaymentIntent::create([
        'amount' => 12995, // Este es el monto en céntimos, ajusta según el total de tu formulario
        'currency' => 'eur',
        'automatic_payment_methods' => ['enabled' => true],
    ]);

    $response = [
        'clientSecret' => $paymentIntent->client_secret,
    ];
    echo json_encode($response);
} catch (Error $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
