/**
 * TBV2 TEMPORAL INTEGRATION
 * Integración del sistema de storage temporal con el formulario
 */

window.TBV2TemporalSystem = {
    
    /**
     * Procesa el formulario usando el sistema temporal
     */
    async processWithTemporal() {
        try {
            console.log('🚀 TBV2 TEMPORAL: Iniciando procesamiento temporal...');
            
            // 1. Recopilar todos los datos del formulario
            const formData = this.collectAllFormData();
            
            // 2. Validar datos antes de enviar
            if (!this.validateFormData(formData)) {
                throw new Error('Datos del formulario inválidos');
            }
            
            // 3. Crear FormData para envío temporal
            const temporalFormData = this.createTemporalFormData(formData);
            
            // 4. Enviar al endpoint temporal
            const tempResult = await this.sendToTemporal(temporalFormData);
            
            if (tempResult.success) {
                console.log('✅ TBV2 TEMPORAL: Datos guardados temporalmente:', tempResult.data.temp_id);
                
                // 5. Proceder al pago con temp_id
                this.proceedToPayment(tempResult.data);
            } else {
                throw new Error(tempResult.error || 'Error guardando datos temporales');
            }
            
        } catch (error) {
            console.error('❌ TBV2 TEMPORAL ERROR:', error);
            this.showError('Error procesando formulario: ' + error.message);
        }
    },
    
    /**
     * Recopila todos los datos del formulario (mejorado)
     */
    collectAllFormData() {
        // Datos del cliente
        const customerData = {
            name: document.getElementById('buyer-name')?.value || '',
            email: document.getElementById('buyer-email')?.value || '',
            dni: document.getElementById('buyer-dni')?.value || '',
            phone: document.getElementById('buyer-phone')?.value || '',
            company: document.getElementById('company-name')?.value || ''
        };
        
        // Datos del vehículo
        const vehicleData = {
            tipo_embarcacion: document.getElementById('tipo-embarcacion')?.value || '',
            eslora: document.getElementById('eslora')?.value || '',
            motor: document.getElementById('motor')?.value || '',
            manufacturer: document.getElementById('manufacturer')?.value || '',
            model: document.getElementById('model')?.value || '',
            matricula: document.getElementById('matricula')?.value || '',
            matriculation_date: document.getElementById('matriculation-date')?.value || '',
            purchase_price: parseFloat(document.getElementById('purchase-price')?.value || 0),
            region: document.getElementById('region')?.value || ''
        };
        
        // Calcular precios (usar lógica existente del formulario)
        const itpAmount = this.calculateCurrentITP ? this.calculateCurrentITP() : 0;
        const shouldIncludeITP = this.itpPagado === false;
        const basePrice = 134.99;
        const finalAmount = shouldIncludeITP ? basePrice + itpAmount : basePrice;
        
        const pricingData = {
            final_amount: finalAmount,
            transfer_tax: shouldIncludeITP ? itpAmount : 0,
            extra_fee: 0,
            base_price: basePrice,
            itp_included: shouldIncludeITP,
            payment_method: 'redsys'
        };
        
        // Datos de archivos
        const filesData = this.collectFilesData();
        
        // Firma digital
        const signatureData = this.collectSignatureData();
        
        console.log('📊 TBV2 TEMPORAL: Datos recopilados:', {
            customer: customerData,
            vehicle: vehicleData,
            pricing: pricingData,
            files_count: filesData.totalFiles,
            has_signature: !!signatureData
        });
        
        return {
            customer: customerData,
            vehicle: vehicleData,
            pricing: pricingData,
            files: filesData,
            signature: signatureData
        };
    },
    
    /**
     * Recopila información de archivos
     */
    collectFilesData() {
        const fileInputs = [
            { id: 'upload-hoja-asiento', name: 'upload_hoja_asiento' },
            { id: 'upload-dni-comprador', name: 'upload_dni_comprador' },
            { id: 'upload-dni-vendedor', name: 'upload_dni_vendedor' },
            { id: 'upload-contrato-compraventa', name: 'upload_contrato_compraventa' },
            { id: 'upload-modelo-620', name: 'upload_modelo_620' }
        ];
        
        const filesInfo = {};
        let totalFiles = 0;
        
        fileInputs.forEach(inputInfo => {
            const input = document.getElementById(inputInfo.id);
            if (input && input.files && input.files.length > 0) {
                filesInfo[inputInfo.name] = {
                    count: input.files.length,
                    files: Array.from(input.files).map(file => ({
                        name: file.name,
                        size: file.size,
                        type: file.type
                    }))
                };
                totalFiles += input.files.length;
            }
        });
        
        return {
            info: filesInfo,
            totalFiles: totalFiles
        };
    },
    
    /**
     * Recopila firma digital
     */
    collectSignatureData() {
        try {
            if (!window.signaturePad) {
                return null;
            }
            
            if (window.signaturePad.isEmpty()) {
                return null;
            }
            
            const signatureDataURL = window.signaturePad.toDataURL('image/png');
            console.log('✍️ TBV2 TEMPORAL: Firma capturada');
            return signatureDataURL;
            
        } catch (error) {
            console.error('❌ Error capturando firma:', error);
            return null;
        }
    },
    
    /**
     * Valida datos del formulario
     */
    validateFormData(formData) {
        const errors = [];
        
        // Validar datos obligatorios del cliente
        if (!formData.customer.name.trim()) {
            errors.push('Nombre del cliente es obligatorio');
        }
        
        if (!formData.customer.email.trim()) {
            errors.push('Email del cliente es obligatorio');
        }
        
        if (!formData.customer.dni.trim()) {
            errors.push('DNI del cliente es obligatorio');
        }
        
        // Validar datos del vehículo
        if (!formData.vehicle.matricula.trim()) {
            errors.push('Matrícula del vehículo es obligatoria');
        }
        
        // Validar precio
        if (formData.pricing.final_amount <= 0) {
            errors.push('Precio final inválido');
        }
        
        // Validar archivos mínimos
        if (formData.files.totalFiles === 0) {
            errors.push('Debe subir al menos un archivo');
        }
        
        if (errors.length > 0) {
            console.error('❌ TBV2 TEMPORAL: Errores de validación:', errors);
            this.showValidationErrors(errors);
            return false;
        }
        
        return true;
    },
    
    /**
     * Crea FormData para envío temporal
     */
    createTemporalFormData(formData) {
        const temporalFormData = new FormData();
        
        // Datos del cliente
        Object.entries(formData.customer).forEach(([key, value]) => {
            temporalFormData.append(`customer_${key}`, value);
        });
        
        // Datos del vehículo
        Object.entries(formData.vehicle).forEach(([key, value]) => {
            temporalFormData.append(key, value);
        });
        
        // Datos de precios
        Object.entries(formData.pricing).forEach(([key, value]) => {
            temporalFormData.append(key, value);
        });
        
        // Archivos
        const fileInputs = [
            'upload-hoja-asiento',
            'upload-dni-comprador', 
            'upload-dni-vendedor',
            'upload-contrato-compraventa',
            'upload-modelo-620'
        ];
        
        fileInputs.forEach(inputId => {
            const input = document.getElementById(inputId);
            if (input && input.files) {
                for (let i = 0; i < input.files.length; i++) {
                    temporalFormData.append(`${inputId.replace('-', '_')}[]`, input.files[i]);
                }
            }
        });
        
        // Firma digital
        if (formData.signature) {
            temporalFormData.append('signature_data', formData.signature);
        }
        
        // Metadatos
        temporalFormData.append('action', 'tbv2_prepare_data');
        temporalFormData.append('nonce', tbv2Ajax.nonce);
        
        return temporalFormData;
    },
    
    /**
     * Envía datos al sistema temporal
     */
    async sendToTemporal(temporalFormData) {
        try {
            console.log('📤 TBV2 TEMPORAL: Enviando datos temporales...');
            
            const response = await fetch(tbv2Ajax.url, {
                method: 'POST',
                body: temporalFormData
            });
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const result = await response.json();
            
            if (result.success) {
                console.log('✅ TBV2 TEMPORAL: Datos temporales guardados:', result.data);
                return {
                    success: true,
                    data: result.data
                };
            } else {
                throw new Error(result.data || 'Error desconocido');
            }
            
        } catch (error) {
            console.error('❌ TBV2 TEMPORAL: Error enviando datos temporales:', error);
            return {
                success: false,
                error: error.message
            };
        }
    },
    
    /**
     * Procede al pago con el temp_id
     */
    proceedToPayment(tempData) {
        console.log('💳 TBV2 TEMPORAL: Procediendo al pago con temp_id:', tempData.temp_id);
        
        // Crear orden de pago modificada para incluir temp_id
        const orderData = {
            temp_id: tempData.temp_id,
            order_id: tempData.temp_id.replace('TBV2-TEMP-', 'TBV2-'),
            amount: tempData.final_amount || 134.99,
            description: 'Transferencia Embarcación TBV2',
            customer_name: document.getElementById('buyer-name')?.value || 'Cliente',
            expires_at: tempData.expires_at
        };
        
        // Usar la función existente del formulario pero con datos temporales
        this.createRedsysPaymentWithTemporal(orderData);
    },
    
    /**
     * Crea formulario de pago Redsys modificado para datos temporales
     */
    createRedsysPaymentWithTemporal(orderData) {
        console.log('💳 TBV2 TEMPORAL: Creando pago Redsys temporal:', orderData);
        
        // Crear FormData para Redsys
        const redsysFormData = new FormData();
        redsysFormData.append('action', 'tbv2_create_redsys_payment_temporal');
        redsysFormData.append('nonce', tbv2Ajax.nonce);
        redsysFormData.append('temp_id', orderData.temp_id);
        redsysFormData.append('order_id', orderData.order_id);
        redsysFormData.append('amount', orderData.amount);
        redsysFormData.append('description', orderData.description);
        redsysFormData.append('customer_name', orderData.customer_name);
        
        // Enviar a WordPress para generar parámetros Redsys
        fetch(tbv2Ajax.url, {
            method: 'POST',
            body: redsysFormData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('💳 TBV2 TEMPORAL: Parámetros Redsys generados');
                this.submitRedsysForm(data.data);
            } else {
                throw new Error(data.data || 'Error generando pago');
            }
        })
        .catch(error => {
            console.error('❌ TBV2 TEMPORAL: Error creando pago Redsys:', error);
            this.showError('Error procesando pago: ' + error.message);
        });
    },
    
    /**
     * Envía el formulario de Redsys
     */
    submitRedsysForm(redsysData) {
        console.log('💳 TBV2 TEMPORAL: Enviando a Redsys...');
        
        // Crear formulario oculto
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = redsysData.redsys_url;
        form.style.display = 'none';
        
        // Añadir parámetros Redsys
        Object.entries(redsysData.params).forEach(([key, value]) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = key;
            input.value = value;
            form.appendChild(input);
        });
        
        // Añadir al DOM y enviar
        document.body.appendChild(form);
        
        // Mostrar mensaje de redirección
        this.showPaymentRedirection();
        
        // Enviar tras un pequeño delay para que se muestre el mensaje
        setTimeout(() => {
            form.submit();
        }, 1000);
    },
    
    /**
     * Muestra errores de validación
     */
    showValidationErrors(errors) {
        const errorHtml = errors.map(error => `<li>${error}</li>`).join('');
        
        this.showAlert(`
            <div class="tbv2-error-alert">
                <h4>⚠️ Errores en el formulario:</h4>
                <ul>${errorHtml}</ul>
                <p>Por favor, corrija estos errores antes de continuar.</p>
            </div>
        `, 'error');
    },
    
    /**
     * Muestra mensaje de redirección al pago
     */
    showPaymentRedirection() {
        this.showAlert(`
            <div class="tbv2-payment-redirect">
                <h4>💳 Redirigiendo al pago...</h4>
                <p>Sus datos han sido guardados de forma segura.</p>
                <p>Será redirigido al sistema de pago en unos segundos...</p>
                <div class="tbv2-spinner"></div>
            </div>
        `, 'info');
    },
    
    /**
     * Muestra mensaje de error
     */
    showError(message) {
        this.showAlert(`
            <div class="tbv2-error-alert">
                <h4>❌ Error</h4>
                <p>${message}</p>
            </div>
        `, 'error');
    },
    
    /**
     * Sistema de alertas mejorado
     */
    showAlert(content, type = 'info') {
        // Remover alertas existentes
        const existingAlerts = document.querySelectorAll('.tbv2-system-alert');
        existingAlerts.forEach(alert => alert.remove());
        
        // Crear nueva alerta
        const alertDiv = document.createElement('div');
        alertDiv.className = `tbv2-system-alert tbv2-alert-${type}`;
        alertDiv.innerHTML = content;
        
        // Estilos inline para asegurar visibilidad
        alertDiv.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: ${type === 'error' ? '#fee2e2' : type === 'success' ? '#d1fae5' : '#dbeafe'};
            border: 2px solid ${type === 'error' ? '#dc2626' : type === 'success' ? '#059669' : '#2563eb'};
            border-radius: 8px;
            padding: 16px;
            max-width: 400px;
            z-index: 10000;
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
            font-family: -apple-system, BlinkMacSystemFont, sans-serif;
        `;
        
        // Añadir al DOM
        document.body.appendChild(alertDiv);
        
        // Auto-remover tras 5 segundos (excepto errores)
        if (type !== 'error') {
            setTimeout(() => {
                if (alertDiv && alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 5000);
        }
        
        // Permitir cerrar manualmente
        alertDiv.addEventListener('click', () => {
            alertDiv.remove();
        });
    }
};

// CSS para el spinner de carga
const spinnerCSS = `
    .tbv2-spinner {
        width: 20px;
        height: 20px;
        border: 2px solid #e5e7eb;
        border-top: 2px solid #2563eb;
        border-radius: 50%;
        animation: tbv2-spin 1s linear infinite;
        margin: 10px auto;
    }
    
    @keyframes tbv2-spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
`;

// Inyectar CSS
const styleSheet = document.createElement('style');
styleSheet.textContent = spinnerCSS;
document.head.appendChild(styleSheet);

console.log('✅ TBV2 TEMPORAL: Sistema temporal cargado y listo');