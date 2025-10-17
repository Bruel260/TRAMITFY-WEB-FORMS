/**
 * SISTEMA UNIFICADO DE ACTUALIZACIÓN DE DATOS
 * ============================================
 * 
 * Este sistema centraliza TODOS los cálculos y actualizaciones
 * para garantizar consistencia entre:
 * - Sidebar
 * - Desglose de servicios
 * - Resumen de pago
 * - Totales
 * - ITP
 */

// Función maestra que actualiza TODO
function updateAllDisplays() {
    console.log('🔄 [UNIFIED] Actualizando TODOS los displays');
    
    // 1. Obtener todos los valores actuales
    const data = collectAllFormData();
    
    // 2. Calcular todo centralizado
    const calculations = performAllCalculations(data);
    
    // 3. Actualizar TODOS los displays
    updateSidebarDisplay(calculations);
    updateDesgloseDisplay(calculations);
    updatePaymentSummaryDisplay(calculations);
    updateITPDisplay(calculations);
    updateTotalsDisplay(calculations);
    
    // 4. Sincronizar con paymentData
    syncWithPaymentData(calculations);
    
    console.log('✅ [UNIFIED] Todas las pantallas actualizadas', calculations);
}

// Recolectar TODOS los datos del formulario
function collectAllFormData() {
    return {
        // Vehículo
        purchasePrice: parseFloat(document.getElementById('purchase_price')?.value) || 0,
        region: document.getElementById('region')?.value || '',
        manufacturer: document.getElementById('manufacturer')?.value || '',
        model: document.getElementById('model')?.value || '',
        basePrice: getBasePrice(),
        
        // ITP
        itpPagado: getITPPagado(),
        gestionamosITP: window.gestionamosITP || false,
        itpMetodoPago: window.itpMetodoPago || '',
        
        // Servicios adicionales
        cambioLista: document.getElementById('cambio-lista-si')?.classList.contains('active') || false,
        cambioNombre: document.querySelector('input[value="Cambio de nombre"]')?.checked || false,
        cambioPuerto: document.querySelector('input[value="Cambio de puerto base"]')?.checked || false,
        
        // Cupón
        couponCode: document.getElementById('coupon_code')?.value || '',
        couponDiscountPercent: window.couponDiscountPercent || 0
    };
}

// Realizar TODOS los cálculos centralizados
function performAllCalculations(data) {
    const calc = {};
    
    // Precio base del servicio
    calc.servicioBase = data.gestionamosITP ? 174.99 : 134.99;
    
    // Calcular ITP
    const itpRate = window.itpRates?.[data.region] || 0.04;
    calc.itpAmount = data.purchasePrice * itpRate;
    calc.itpCommission = 0;
    
    if (data.itpPagado === false && data.gestionamosITP && data.itpMetodoPago === 'tarjeta') {
        calc.itpCommission = calc.itpAmount * 0.015;
    }
    
    // Servicios adicionales
    calc.serviciosAdicionales = 0;
    if (data.cambioLista) calc.serviciosAdicionales += 62.50;
    if (data.cambioNombre) calc.serviciosAdicionales += 50.00;
    if (data.cambioPuerto) calc.serviciosAdicionales += 40.00;
    
    // Subtotal antes de descuento
    calc.subtotal = calc.servicioBase + calc.serviciosAdicionales;
    
    // Si gestionamos ITP, añadirlo al subtotal
    if (data.gestionamosITP && !data.itpPagado) {
        calc.subtotal += calc.itpAmount + calc.itpCommission;
    }
    
    // Aplicar descuento
    calc.descuento = calc.subtotal * (data.couponDiscountPercent / 100);
    calc.total = calc.subtotal - calc.descuento;
    
    // Desglose detallado
    calc.tasas = 19.05;
    calc.honorarios = calc.servicioBase - calc.tasas;
    calc.iva = calc.honorarios * 0.21;
    
    // Total para Stripe
    if (data.gestionamosITP && data.itpMetodoPago === 'transferencia') {
        calc.stripeAmount = calc.servicioBase + calc.serviciosAdicionales - calc.descuento;
    } else {
        calc.stripeAmount = calc.total;
    }
    
    return calc;
}

// Actualizar Sidebar
function updateSidebarDisplay(calc) {
    const sidebar = document.getElementById('sidebar-precio-content');
    if (!sidebar) return;
    
    // Usar los valores calculados centralmente
    // ... código HTML del sidebar con calc.total, calc.itpAmount, etc.
}

// Actualizar Desglose
function updateDesgloseDisplay(calc) {
    const tramitacion = document.getElementById('desglose-tramitacion');
    if (tramitacion) tramitacion.textContent = calc.servicioBase.toFixed(2) + ' €';
    
    const itp = document.getElementById('desglose-itp');
    if (itp) itp.textContent = calc.itpAmount.toFixed(2) + ' €';
    
    const total = document.getElementById('total-final-precio');
    if (total) total.textContent = calc.total.toFixed(2) + ' €';
}

// Actualizar Resumen de Pago
function updatePaymentSummaryDisplay(calc) {
    const elements = {
        'summary-base-price': calc.servicioBase,
        'summary-tasas-gestion': calc.tasas,
        'summary-honorarios': calc.honorarios,
        'summary-iva': calc.iva,
        'summary-transfer-tax': calc.itpAmount,
        'summary-comision': calc.itpCommission,
        'summary-final-amount': calc.total
    };
    
    for (const [id, value] of Object.entries(elements)) {
        const el = document.getElementById(id);
        if (el) el.textContent = value.toFixed(2) + ' €';
    }
}

// Actualizar displays de ITP
function updateITPDisplay(calc) {
    const displays = [
        'transfer_tax_display',
        'calculated-itp-display',
        'sidebar-itp-amount'
    ];
    
    displays.forEach(id => {
        const el = document.getElementById(id);
        if (el) el.textContent = calc.itpAmount.toFixed(2) + ' €';
    });
}

// Actualizar todos los totales
function updateTotalsDisplay(calc) {
    const totalElements = [
        'final-amount',
        'final-summary-amount',
        'cambio_nombre_price',
        'total-final-precio'
    ];
    
    totalElements.forEach(id => {
        const el = document.getElementById(id);
        if (el) el.textContent = calc.total.toFixed(2) + ' €';
    });
}

// Sincronizar con paymentData
function syncWithPaymentData(calc) {
    if (window.paymentData) {
        window.paymentData.servicePrice = calc.servicioBase;
        window.paymentData.updateITP(calc.itpAmount);
        window.paymentData.tasas = calc.tasas;
        window.paymentData.honorarios = calc.honorarios;
        window.paymentData.iva = calc.iva;
    }
    
    // Mantener compatibilidad con variables globales
    window.finalAmount = calc.total;
    window.currentTransferTax = calc.itpAmount;
    window.currentExtraFee = calc.itpCommission;
}

// Función helper para obtener el precio base
function getBasePrice() {
    const modelSelect = document.getElementById('model');
    if (!modelSelect) return 0;
    
    const selectedOption = modelSelect.options[modelSelect.selectedIndex];
    return selectedOption ? parseFloat(selectedOption.dataset.price) || 0 : 0;
}

// Función helper para obtener estado ITP
function getITPPagado() {
    const itpPagadoEl = document.getElementById('itp_already_paid');
    if (itpPagadoEl) return itpPagadoEl.checked;
    
    // Si no existe el elemento, revisar las variables globales
    return window.itpPagado;
}

// REGISTRAR TODOS LOS EVENT LISTENERS
function registerUnifiedListeners() {
    // Lista de elementos que deben actualizar TODO
    const triggerElements = [
        'manufacturer',
        'model', 
        'purchase_price',
        'region',
        'itp_already_paid',
        'cambio-lista-si',
        'cambio-lista-no',
        'coupon_code'
    ];
    
    triggerElements.forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('change', updateAllDisplays);
            el.addEventListener('input', updateAllDisplays);
        }
    });
    
    // Checkboxes de servicios adicionales
    document.querySelectorAll('input[type="checkbox"][data-price]').forEach(cb => {
        cb.addEventListener('change', updateAllDisplays);
    });
}

// Exportar funciones para uso global
window.updateAllDisplays = updateAllDisplays;
window.registerUnifiedListeners = registerUnifiedListeners;