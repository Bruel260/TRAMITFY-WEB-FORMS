#!/bin/bash
# Sanitizar claves Stripe para GitHub

# Hacer backup primero
for file in hoja-asiento.php baja.php transferencia-barco.php transferencia-moto.php recuperar-documentacion.php renovacion-permiso.php; do
    cp $file ${file}.backup-with-keys
done

# Reemplazar claves reales con placeholders
sed -i "s/pk_test_51[A-Za-z0-9]*/pk_test_YOUR_STRIPE_TEST_PUBLIC_KEY/g" *.php
sed -i "s/sk_test_51[A-Za-z0-9]*/sk_test_YOUR_STRIPE_TEST_SECRET_KEY/g" *.php
sed -i "s/pk_live_51[A-Za-z0-9]*/pk_live_YOUR_STRIPE_LIVE_PUBLIC_KEY/g" *.php
sed -i "s/sk_live_51[A-Za-z0-9]*/sk_live_YOUR_STRIPE_LIVE_SECRET_KEY/g" *.php

echo "✅ Claves sanitizadas para GitHub"
