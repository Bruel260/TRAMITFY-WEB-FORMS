#!/bin/bash

echo "🔒 Sanitizando TODAS las claves sensibles en archivos PHP..."

# Función para sanitizar un archivo
sanitize_file() {
    local file="$1"
    echo "  🔧 Procesando: $file"
    
    # Reemplazar claves Stripe TEST
    sed -i 's/sk_test_[a-zA-Z0-9_]\{99\}/YOUR_STRIPE_TEST_SECRET_KEY_HERE/g' "$file"
    sed -i 's/pk_test_[a-zA-Z0-9_]\{99\}/YOUR_STRIPE_TEST_PUBLIC_KEY_HERE/g' "$file"
    
    # Reemplazar claves Stripe LIVE
    sed -i 's/sk_live_[a-zA-Z0-9_]\{99\}/YOUR_STRIPE_LIVE_SECRET_KEY_HERE/g' "$file"
    sed -i 's/pk_live_[a-zA-Z0-9_]\{99\}/YOUR_STRIPE_LIVE_PUBLIC_KEY_HERE/g' "$file"
    
    # Reemplazar password de email si existe
    sed -i "s/'xnkz dbvh xwlj uyij'/'YOUR_EMAIL_APP_PASSWORD_HERE'/g" "$file"
    sed -i 's/xnkz dbvh xwlj uyij/YOUR_EMAIL_APP_PASSWORD_HERE/g' "$file"
}

# Buscar y sanitizar todos los archivos PHP
find . -type f -name "*.php" | while read -r file; do
    sanitize_file "$file"
done

echo "✅ Sanitización completada"
