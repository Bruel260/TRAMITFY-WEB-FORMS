<?php
defined('ABSPATH') || exit;
$is_test_mode = true;
$publishable_key_test = 'YOUR_STRIPE_TEST_PUBLIC_KEY_HERE';
$publishable_key_live = 'YOUR_STRIPE_LIVE_PUBLIC_KEY_HERE';
$secret_key_test = 'YOUR_STRIPE_TEST_SECRET_KEY_HERE';
$secret_key_live = 'YOUR_STRIPE_LIVE_SECRET_KEY_HERE';

function matriculacion_form_shortcode() {
    global $is_test_mode, $publishable_key_test, $publishable_key_live;
    wp_enqueue_style('matriculacion-form-style', get_template_directory_uri() . '/style.css', array(), filemtime(get_template_directory() . '/style.css'));
    wp_enqueue_script('stripe', 'https://js.stripe.com/v3/', array(), null, false);
    wp_enqueue_script('signature-pad', 'https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js', array(), null, false);

    $prefix = 'TMA-MAT';
    $counter_option = $is_test_mode ? 'tma_matriculacion_counter_test' : 'tma_matriculacion_counter';
    $date_part = date('Ymd');
    $current_cnt = get_option($counter_option, 0) + 1;
    update_option($counter_option, $current_cnt);
    $secuencial = str_pad($current_cnt, 6, '0', STR_PAD_LEFT);
    $tramite_id = $prefix . '-' . $date_part . '-' . $secuencial;

    ob_start();
    ?>
    <style>
    /* Estilos optimizados */
    .signature-pad-container{width:100%;max-width:600px;margin:0 auto;border:2px solid #e0e0e0;border-radius:8px;overflow:hidden;transition:border-color .3s ease;background-color:#fff;position:relative;touch-action:none;-ms-touch-action:none}
    .signature-pad-container:hover{border-color:#016d86}
    #signature-pad{width:100%;height:200px;touch-action:none;-ms-touch-action:none;display:block;box-sizing:border-box;cursor:crosshair;-webkit-user-select:none;-moz-user-select:none;-ms-user-select:none;user-select:none;position:relative;z-index:10;background-color:#fff}
    .modal-signature-container{width:100%;margin:20px 0;border:2px solid #e0e0e0;border-radius:8px;overflow:hidden;background-color:#fff;touch-action:none;-ms-touch-action:none}
    #modal-signature-pad{width:100%;height:400px;touch-action:none;-ms-touch-action:none;display:block;box-sizing:border-box;cursor:crosshair;-webkit-user-select:none;-moz-user-select:none;-ms-user-select:none;user-select:none;background-color:#fff}
    .signature-actions{margin-top:15px;display:flex;justify-content:center;gap:10px}
    .signature-actions button{padding:8px 15px;background-color:#e9ecef;border:none;border-radius:4px;color:#016d86;cursor:pointer;display:flex;align-items:center;transition:all .3s ease}
    .signature-actions button:hover{background-color:#dde2e6;transform:translateY(-2px)}
    @media (max-width:768px){#signature-pad{height:180px}.signature-actions{flex-wrap:wrap}.signature-actions button{flex:1 0 auto;justify-content:center;margin-bottom:5px}}
    @media (max-width:480px){#signature-pad{height:150px}.signature-pad-container{border-width:1px}}
    #signature-modal{position:fixed;z-index:2000;left:0;top:0;width:100%;height:100%;overflow:hidden;background-color:rgba(0,0,0,0.8);display:flex;align-items:center;justify-content:center}
    .signature-modal-content{background-color:#fff;padding:20px;border-radius:10px;width:90%;max-width:800px;position:relative;display:flex;flex-direction:column;align-items:center}
    .close-signature-modal{position:absolute;top:10px;right:15px;color:#6c757d;font-size:24px;font-weight:bold;cursor:pointer}
    .accept-btn{padding:10px 20px;background-color:#28a745;color:white;border:none;border-radius:4px;cursor:pointer;display:flex;align-items:center;margin-left:10px}
    .accept-btn i{margin-right:5px}
    .accept-btn:hover{background-color:#218838}
    .change-tramite-button{background-color:transparent;color:#016d86;border:1px solid #016d86;border-radius:20px;padding:4px 12px;font-size:13px;cursor:pointer;transition:all .3s ease;margin-left:10px;display:inline-flex;align-items:center;vertical-align:middle}
    .change-tramite-button:hover{background-color:#016d86;color:white;transform:translateY(-2px);box-shadow:0 2px 5px rgba(1,109,134,0.2)}
    .change-tramite-button i{margin-right:5px;font-size:11px}
    #matriculacion-form{max-width:1000px;margin:40px auto;padding:30px;border:1px solid #e0e0e0;border-radius:10px;font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;background-color:#fff;box-shadow:0 8px 16px rgba(0,0,0,0.1);transition:all .3s ease}
    #documento-identidad,#upload-dni,.upload-item:contains("representación"),.upload-item:contains("persona jurídica"),p:contains("firme a continuación"),#signature-container:not(.authorization-signature),#signature-pad:not(.authorization-signature),#clear-signature:not(.authorization-signature){display:none!important}
    #matriculacion-form label{font-weight:normal;display:block;margin-top:15px;margin-bottom:5px;color:#555}
    #matriculacion-form input[type="text"],#matriculacion-form input[type="tel"],#matriculacion-form input[type="email"],#matriculacion-form input[type="number"],#matriculacion-form input[type="date"],#matriculacion-form select,#matriculacion-form input[type="file"]{width:100%;padding:12px;margin-top:0;border-radius:8px;border:2px solid #e0e0e0;font-size:16px;background-color:#f9f9f9;transition:all .3s ease}
    #matriculacion-form input[type="text"]:focus,#matriculacion-form input[type="tel"]:focus,#matriculacion-form input[type="email"]:focus,#matriculacion-form input[type="number"]:focus,#matriculacion-form input[type="date"]:focus,#matriculacion-form select:focus{border-color:#016d86;box-shadow:0 0 0 3px rgba(1,109,134,0.15);outline:none}
    #matriculacion-form input[type="file"]{padding:10px;border:2px dashed #e0e0e0;background-color:#f8f9fa;cursor:pointer}
    #matriculacion-form input[type="file"]:hover{border-color:#016d86;background-color:rgba(1,109,134,0.05)}
    .input-with-icon{position:relative}
    .input-with-icon input{padding-left:40px!important}
    .input-with-icon i{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#6c757d;font-size:18px}
    .input-with-button{position:relative}
    .input-with-button input{padding-right:50px!important}
    .input-with-button button{position:absolute;right:2px;top:50%;transform:translateY(-50%);background:#e9ecef;border:none;padding:5px 10px;border-radius:5px;cursor:pointer;color:#495057;transition:all .3s ease}
    .input-with-button button:hover{background:#016d86;color:white}
    #matriculacion-form .radio-group{display:flex;flex-wrap:wrap;gap:10px;margin-top:5px}
    #matriculacion-form .radio-group label{margin:0;display:flex;align-items:center;cursor:pointer;padding:10px 16px;background-color:#fff;border:2px solid #e0e0e0;border-radius:50px;transition:all .3s ease;position:relative}
    #matriculacion-form .radio-group label:hover{border-color:#016d86;background-color:rgba(1,109,134,0.05)}
    #matriculacion-form .radio-group input[type="radio"]{position:absolute;opacity:0;width:0;height:0}
    #matriculacion-form .radio-group input[type="radio"]:checked+span{color:#016d86;font-weight:600}
    #matriculacion-form .radio-group input[type="radio"]:checked~span{color:#016d86;font-weight:600}
    #matriculacion-form .radio-group label.field-error{border-color:#dc3545;animation:shake .5s ease-in-out;background-color:rgba(220,53,69,0.05)}
    #matriculacion-form .radio-group input[type="radio"]:checked+span::before{content:"";position:absolute;left:0;top:0;width:100%;height:100%;border:2px solid #016d86;border-radius:50px;box-sizing:border-box;background-color:rgba(1,109,134,0.05);z-index:-1}
    #matriculacion-form .radio-group span{position:relative;z-index:1}
    #matriculacion-form .checkbox-group{margin-top:5px}
    #matriculacion-form .checkbox-group label{display:flex;align-items:center;cursor:pointer;margin-bottom:10px;transition:all .3s ease}
    #matriculacion-form .checkbox-group input[type="checkbox"]{position:relative;width:20px;height:20px;margin-right:10px;-webkit-appearance:none;-moz-appearance:none;appearance:none;border:2px solid #e0e0e0;border-radius:4px;outline:none;transition:all .3s ease;cursor:pointer}
    #matriculacion-form .checkbox-group input[type="checkbox"]:checked{border-color:#016d86;background-color:#016d86}
    #matriculacion-form .checkbox-group input[type="checkbox"]:checked::before{content:"✓";position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);color:white;font-size:14px}
    #matriculacion-form .checkbox-group label:hover input[type="checkbox"]{border-color:#016d86}
    #matriculacion-form .button{background-color:#28a745;color:#fff;padding:14px 24px;border:none;border-radius:50px;cursor:pointer;font-size:16px;font-weight:600;transition:all .3s ease;margin-top:20px;display:inline-flex;align-items:center;justify-content:center;box-shadow:0 2px 6px rgba(40,167,69,0.3);position:relative;overflow:hidden}
    #matriculacion-form .button:hover{background-color:#218838;box-shadow:0 4px 10px rgba(40,167,69,0.4);transform:translateY(-2px)}
    #matriculacion-form .button:active{transform:translateY(0);box-shadow:0 2px 3px rgba(40,167,69,0.2)}
    #matriculacion-form .button-secondary{background-color:#6c757d;box-shadow:0 2px 6px rgba(108,117,125,0.3)}
    #matriculacion-form .button-secondary:hover{background-color:#5a6268;box-shadow:0 4px 10px rgba(108,117,125,0.4)}
    #matriculacion-form .button-primary{background-color:#016d86;box-shadow:0 2px 6px rgba(1,109,134,0.3)}
    #matriculacion-form .button-primary:hover{background-color:#015a70;box-shadow:0 4px 10px rgba(1,109,134,0.4)}
    #matriculacion-form .button i{margin-right:8px}
    .button-floating{position:fixed;bottom:30px;right:30px;width:60px;height:60px;border-radius:50%;background-color:#016d86;color:white;display:flex;align-items:center;justify-content:center;font-size:24px;box-shadow:0 4px 12px rgba(0,0,0,0.2);cursor:pointer;z-index:1000;transition:all .3s ease}
    .button-floating:hover{background-color:#015a70;box-shadow:0 6px 14px rgba(0,0,0,0.25);transform:translateY(-2px)}
    #matriculacion-form .hidden{display:none}
    #form-navigation{display:flex;flex-wrap:wrap;justify-content:space-between;margin-bottom:30px;align-items:center;background-color:#f8f9fa;padding:0;border-radius:8px;box-shadow:0 2px 10px rgba(0,0,0,0.05);position:relative;z-index:10}
    .progress-bar-container{position:absolute;bottom:0;left:0;height:4px;width:100%;background-color:#e9ecef;border-radius:0 0 8px 8px;overflow:hidden}
    .progress-bar{height:100%;background-color:#016d86;transition:width .4s ease}
    #form-navigation a{color:#495057;text-decoration:none;font-weight:600;position:relative;padding:15px 20px;transition:all .3s ease;flex:1;text-align:center;border-bottom:3px solid transparent}
    #form-navigation a.active{color:#016d86;background-color:rgba(1,109,134,0.05);border-bottom:3px solid #016d86}
    #form-navigation a.completed{color:#28a745}
    #form-navigation a.available{color:#495057;cursor:pointer}
    #form-navigation a.locked{color:#adb5bd;cursor:not-allowed}
    #form-navigation a::before{content:attr(data-step);display:flex;align-items:center;justify-content:center;width:28px;height:28px;background-color:#e9ecef;color:#6c757d;border-radius:50%;font-size:14px;margin:0 auto 8px;transition:all .3s ease}
    #form-navigation a.active::before{background-color:#016d86;color:white}
    #form-navigation a.completed::before{content:"✓";background-color:#28a745;color:white}
    #form-navigation a:hover{color:#016d86;background-color:rgba(1,109,134,0.03)}
    .button-container{display:flex;flex-wrap:wrap;justify-content:space-between;margin-top:15px}
    .button-container .button{flex:1 1 auto;margin:5px}
    .upload-section{margin-top:20px}
    .upload-item{margin-bottom:10px;display:flex;align-items:center;flex-wrap:wrap}
    .upload-item label{flex:0 0 30%;font-weight:normal;color:#555;margin-bottom:5px}
    .upload-item input[type="file"]{flex:1;margin-bottom:5px}
    .upload-item .view-example{flex:0 0 auto;margin-left:10px;background-color:transparent;color:#007bff;text-decoration:underline;cursor:pointer;margin-bottom:5px}
    .upload-item .view-example:hover{color:#0056b3}
    #document-popup{display:none;position:fixed;z-index:1001;left:0;top:0;width:100%;height:100%;overflow:auto;background-color:rgba(0,0,0,0.5)}
    #document-popup .popup-content{background-color:#fff;margin:5% auto;padding:20px;width:90%;max-width:600px;border-radius:8px;position:relative}
    #document-popup .close-popup{color:#aaa;position:absolute;top:10px;right:25px;font-size:28px;font-weight:bold;cursor:pointer}
    #document-popup .close-popup:hover{color:black}
    #document-popup h3{margin-top:0;color:#333}
    #document-popup img{width:100%;border-radius:8px}
    #signature-container{margin-top:20px;text-align:center;width:100%}
    #signature-instructions{font-size:14px;color:#555;margin-bottom:10px;text-align:center}
    #payment-element{margin-top:15px;margin-bottom:15px;background-color:#f9f9f9;padding:20px;border-radius:8px;border:1px solid #e0e0e0}
    #submit{background-color:#016d86;color:#fff;padding:15px 25px;border:none;border-radius:5px;cursor:pointer;font-size:20px;transition:background-color .3s ease;margin-top:0;display:inline-flex;align-items:center;justify-content:center}
    #submit:hover{background-color:#014f63}
    #payment-message{margin-top:15px;font-size:16px;text-align:center}
    #payment-message.success{color:#28a745}
    #payment-message.error{color:#dc3545}
    #payment-message.info{color:#17a2b8}
    .StripeElement--invalid{border-color:#dc3545}
    .StripeElement{background-color:#fff;padding:12px;border:1px solid #ccc;border-radius:4px;margin-bottom:10px;width:100%}
    #loading-overlay{position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(255,255,255,0.9);display:none;flex-direction:column;align-items:center;justify-content:center;z-index:1000}
    #loading-overlay .spinner{border:8px solid #f3f3f3;border-top:8px solid #007bff;border-radius:50%;width:70px;height:70px;animation:spin 1.5s linear infinite}
    #loading-overlay p{margin-top:25px;font-size:20px;color:#007bff}
    @keyframes spin{0%{transform:rotate(0deg)}100%{transform:rotate(360deg)}}
    .button[disabled],.button:disabled{background-color:#ccc;cursor:not-allowed}
    .terms-container{margin-top:25px;text-align:left}
    .terms-container label{font-weight:normal;color:#555}
    .terms-container a{color:#007bff;text-decoration:none}
    .terms-container a:hover{text-decoration:underline}
    .price-details{margin-top:20px;padding:20px;border-radius:8px;border:1px solid #e0e0e0;background-color:#fafafa}
    .price-details p{font-size:18px;font-weight:bold;margin:0;color:#333}
    .price-details ul{list-style-type:none;padding:0;margin:15px 0}
    .price-details ul li{margin-bottom:8px;color:#555}
    .error-message{color:#dc3545;margin-bottom:20px;font-size:16px;font-weight:bold}
    .field-error{border-color:#dc3545!important;animation:shake .5s ease-in-out}
    @keyframes shake{0%,100%{transform:translateX(0)}20%,60%{transform:translateX(-5px)}40%,80%{transform:translateX(5px)}}
    .field-info{font-size:13px;color:#6c757d;margin-top:5px}
    .field-hint{display:none;position:absolute;background-color:#495057;color:white;padding:10px;border-radius:4px;font-size:14px;bottom:100%;left:0;width:220px;margin-bottom:10px;z-index:10;box-shadow:0 4px 6px rgba(0,0,0,0.1)}
    .field-hint::after{content:"";position:absolute;top:100%;left:20px;border-width:6px;border-style:solid;border-color:#495057 transparent transparent transparent}
    .hint-trigger{position:relative;display:inline-block;width:18px;height:18px;background-color:#e9ecef;color:#495057;border-radius:50%;text-align:center;font-size:12px;font-weight:bold;line-height:18px;margin-left:5px;cursor:help}
    .hint-trigger:hover .field-hint{display:block}
    .coupon-valid{background-color:#d4edda!important;border-color:#28a745!important}
    .coupon-error{background-color:#f8d7da!important;border-color:#dc3545!important}
    .coupon-loading{background-color:#fff3cd!important;border-color:#ffeeba!important}
    .form-section{margin-bottom:25px;padding:20px;border-radius:8px;background-color:#f8f9fa;box-shadow:0 2px 6px rgba(0,0,0,0.05);transition:all .3s ease}
    .form-section:hover{box-shadow:0 4px 8px rgba(0,0,0,0.1)}
    .form-section-header{display:flex;align-items:center;justify-content:space-between;cursor:pointer;padding-bottom:10px;margin-bottom:15px;border-bottom:1px solid #e0e0e0}
    .form-section-header h3{color:#016d86;margin:0;display:flex;align-items:center}
    .form-section-header h3 i{margin-right:10px;transition:transform .3s ease}
    .form-section-header .section-toggle{font-size:24px;color:#016d86;transition:transform .3s ease}
    .form-section.collapsed .section-toggle{transform:rotate(180deg)}
    .form-section-content{transition:max-height .4s ease,opacity .3s ease;max-height:2000px;opacity:1;overflow:hidden}
    .form-section.collapsed .form-section-content{max-height:0;opacity:0;margin-top:0}
    .form-row{display:flex;flex-wrap:wrap;margin:0 -10px}
    .form-col{flex:1 0 200px;padding:0 10px;margin-bottom:15px;transition:all .3s ease}
    .field-group{padding:15px;margin-bottom:20px;border-radius:8px;background-color:white;border:1px solid #e0e0e0;transition:all .3s ease}
    .field-group:hover{box-shadow:0 2px 6px rgba(0,0,0,0.1)}
    .field-group-title{font-weight:600;color:#495057;margin-bottom:10px;display:block}
    .tramite-selector{background-color:#e9f7fb;padding:15px;border-radius:8px;margin-bottom:20px;border-left:4px solid #016d86}
    .tramite-selector .radio-group{margin-top:10px}
    .tramite-title{font-weight:600;margin-bottom:10px;color:#016d86}
    @media (max-width:768px){#matriculacion-form{padding:20px 15px;margin:20px auto}#form-navigation{flex-direction:column;align-items:flex-start;padding:8px}#form-navigation a{margin-bottom:0;padding:10px;font-size:14px;flex:1 0 100%;display:flex;align-items:center;justify-content:flex-start;border-bottom:none;border-left:3px solid transparent}#form-navigation a::before{margin:0 10px 0 0;width:24px;height:24px;font-size:12px}#form-navigation a.active{border-bottom:none;border-left:3px solid #016d86}.button-container{flex-direction:column;align-items:stretch}.button-container .button{width:100%;margin:5px 0}.upload-item{flex-direction:column;align-items:flex-start}.upload-item label,.upload-item input[type="file"],.upload-item .view-example{flex:1 1 100%;margin-bottom:5px}.upload-item .view-example{margin-left:0}.form-col{flex:1 0 100%}.signature-box{margin-top:20px}.signature-header{flex-direction:column;align-items:flex-start}.signature-status{margin-top:5px}.tramite-cards-container{flex-direction:column;align-items:center}.tramite-card{min-width:100%;margin-bottom:15px}}
    @media (max-width:480px){#matriculacion-form{padding:15px 10px;box-shadow:0 4px 8px rgba(0,0,0,0.1)}#form-navigation{margin-bottom:15px}#form-navigation a{padding:8px 5px;font-size:12px}#form-navigation a::before{width:20px;height:20px;font-size:10px}.button{font-size:14px;padding:10px}.form-section{padding:15px 10px}.form-section-header h3{font-size:16px}.select-tramite-button{width:100%}#matriculacion-form input[type="text"],#matriculacion-form input[type="tel"],#matriculacion-form input[type="email"],#matriculacion-form input[type="number"],#matriculacion-form input[type="date"],#matriculacion-form select,#matriculacion-form input[type="file"]{padding:10px;font-size:14px}.input-with-icon input{padding-left:35px!important}.input-with-icon i{left:10px;font-size:16px}.signature-content{padding:10px}.signature-actions button{padding:6px 10px;font-size:12px}#matriculacion-form .radio-group label{padding:8px 12px;font-size:14px}}
    .payment-summary{background-color:#f8f9fa;border-radius:10px;padding:20px;margin-bottom:20px;border-left:4px solid #016d86}
    .payment-card{background:white;border-radius:10px;box-shadow:0 4px 12px rgba(0,0,0,0.1);padding:20px;margin-bottom:20px;border:1px solid rgba(0,0,0,0.08);transition:all .3s ease}
    .payment-card:hover{box-shadow:0 6px 16px rgba(0,0,0,0.15);transform:translateY(-2px)}
    .payment-title{color:#016d86;margin-top:0;margin-bottom:15px;font-size:1.2em;padding-bottom:10px;border-bottom:1px solid #e0e0e0}
    .payment-row{display:flex;justify-content:space-between;margin-bottom:8px}
    .payment-label{color:#555;font-weight:500}
    .payment-value{font-weight:600}
    .payment-total{margin-top:15px;padding-top:15px;border-top:2px solid #e0e0e0;font-size:1.2em}
    .payment-total .payment-value{color:#016d86;font-size:1.2em}
    .coupon-container{position:relative;margin-top:20px;margin-bottom:20px}
    .coupon-input-wrapper{display:flex;justify-content:space-between;align-items:center;margin-top:8px}
    .coupon-input-wrapper input{flex:1;margin-right:10px}
    .apply-coupon-btn{background-color:#6c757d;color:white;border:none;border-radius:4px;padding:10px 15px;cursor:pointer;transition:all .3s ease}
    .apply-coupon-btn:hover{background-color:#5a6268}
    .coupon-message{margin-top:8px;padding:8px;border-radius:4px;font-size:14px;display:flex;align-items:center}
    .coupon-message.success{background-color:#d4edda;color:#155724;border:1px solid #c3e6cb}
    .coupon-message.error{background-color:#f8d7da;color:#721c24;border:1px solid #f5c6cb}
    .coupon-message i{margin-right:8px}
    .payment-actions{margin-top:25px}
    .submit-button{background-color:#016d86;color:white;border:none;border-radius:30px;padding:15px 30px;font-size:18px;font-weight:600;cursor:pointer;width:100%;transition:all .3s ease;display:flex;justify-content:center;align-items:center;position:relative;overflow:hidden}
    .submit-button:hover{background-color:#015a70;transform:translateY(-2px);box-shadow:0 4px 10px rgba(1,109,134,0.3)}
    .submit-button:disabled{background-color:#ccc;cursor:not-allowed;transform:none;box-shadow:none}
    .submit-button i{margin-right:8px}
    .submit-button:active:not(:disabled){transform:scale(0.98)}
    .status-message{display:flex;align-items:center;justify-content:center;padding:15px;border-radius:8px;margin:15px 0;font-weight:500}
    .status-message i{font-size:20px;margin-right:10px}
    .status-message.success{background-color:#d4edda;color:#155724;border:1px solid #c3e6cb}
    .status-message.error{background-color:#f8d7da;color:#721c24;border:1px solid #f5c6cb}
    .status-message.info{background-color:#d1ecf1;color:#0c5460;border:1px solid #bee5eb}
    .status-message.warning{background-color:#fff3cd;color:#856404;border:1px solid #ffeeba}
    .signature-box{margin-top:20px;border-radius:8px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,0.1);background-color:white}
    .signature-header{background-color:#f8f9fa;padding:10px 15px;border-bottom:1px solid #e0e0e0;display:flex;justify-content:space-between;align-items:center}
    .signature-title{font-weight:600;color:#016d86;margin:0}
    .signature-content{padding:20px;display:flex;flex-direction:column;align-items:center}
    .signature-pad-container{width:100%;max-width:500px;margin:0 auto;border:2px solid #e0e0e0;border-radius:8px;overflow:hidden;transition:border-color .3s ease}
    .signature-pad-container:hover{border-color:#016d86}
    .signature-actions{margin-top:15px;display:flex;justify-content:center;gap:10px}
    .signature-actions button{padding:8px 15px;background-color:#e9ecef;border:none;border-radius:4px;color:#495057;cursor:pointer;display:flex;align-items:center;transition:all .3s ease}
    .signature-actions button:hover{background-color:#dde2e6}
    .signature-actions button i{margin-right:5px}
    .signature-actions .clear-btn{color:#dc3545}
    .signature-actions .clear-btn:hover{background-color:#f8d7da}
    .signature-instructions{margin-top:15px;color:#6c757d;font-size:.9em;text-align:center}
    .signature-status{margin-top:10px;font-size:.9em;color:#6c757d}
    .signature-status.signed{color:#28a745}
    .signature-status.empty{color:#dc3545}
    #tramite-selector-container{padding:40px;background-color:#f8f9fa;border-radius:15px;text-align:center;margin-bottom:30px;box-shadow:0 5px 15px rgba(0,0,0,0.08);max-width:900px;margin:0 auto 30px}
    .tramite-selection-title{font-size:26px;color:#016d86;margin-bottom:20px;font-weight:600;text-align:center}
    .tramite-selection-subtitle{font-size:16px;color:#6c757d;margin-bottom:30px;text-align:center;max-width:700px;margin-left:auto;margin-right:auto}
    .tramite-cards-container{display:flex;justify-content:center;flex-wrap:wrap;gap:30px;margin-top:30px}
    .tramite-card{flex:1;min-width:280px;max-width:380px;background-color:white;border-radius:12px;padding:25px;box-shadow:0 4px 12px rgba(0,0,0,0.06);transition:all .3s ease;cursor:pointer;position:relative;overflow:hidden;border:2px solid transparent}
    @media (max-width:768px){.tramite-cards-container{flex-direction:column;align-items:center;gap:20px}.tramite-card{width:100%;min-width:unset;max-width:100%;margin-bottom:15px}#tramite-selector-container{padding:20px 15px}.tramite-selection-title{font-size:22px}.tramite-selection-subtitle{font-size:14px}}
    @media (max-width:480px){.tramite-card{padding:15px}.tramite-card-header{margin-bottom:10px}.tramite-card-icon{width:40px;height:40px;font-size:18px}.tramite-card-title{font-size:16px}.tramite-card-description{font-size:13px;margin-bottom:10px}.tramite-card-bullets li{font-size:13px;margin-bottom:3px}.select-tramite-button{width:100%;padding:10px 20px}}
    .tramite-card:hover{transform:translateY(-5px);box-shadow:0 8px 24px rgba(0,0,0,0.1)}
    .tramite-card.selected{border-color:#016d86;box-shadow:0 0 0 2px rgba(1,109,134,0.2),0 8px 24px rgba(0,0,0,0.1)}
    .tramite-card.selected::after{content:"✓";position:absolute;top:15px;right:15px;width:24px;height:24px;background-color:#016d86;color:white;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:14px}
    .tramite-card-header{display:flex;align-items:center;margin-bottom:15px}
    .tramite-card-icon{width:50px;height:50px;background-color:rgba(1,109,134,0.1);border-radius:10px;display:flex;align-items:center;justify-content:center;margin-right:15px;color:#016d86;font-size:22px}
    .tramite-card-title{font-size:18px;font-weight:600;color:#333}
    .tramite-card-subtitle{color:#016d86;font-size:14px;margin-bottom:5px}
    .tramite-card-description{color:#6c757d;font-size:14px;line-height:1.5;margin-bottom:15px}
    .tramite-card-bullets{padding-left:20px;margin-bottom:15px}
    .tramite-card-bullets li{color:#555;font-size:14px;margin-bottom:5px;position:relative}
    .tramite-card-bullets li::before{content:"•";color:#016d86;position:absolute;left:-15px;font-size:18px;line-height:14px}
    .select-tramite-button{background-color:#016d86;color:white;border:none;border-radius:30px;padding:12px 30px;font-size:16px;font-weight:600;cursor:pointer;transition:all .3s ease;margin-top:20px;display:inline-flex;align-items:center;justify-content:center;opacity:.9}
    .select-tramite-button:hover{background-color:#015a70;opacity:1;transform:translateY(-2px);box-shadow:0 4px 10px rgba(1,109,134,0.3)}
    .select-tramite-button i{margin-left:8px}
    .select-tramite-button:disabled{background-color:#ccc;cursor:not-allowed;opacity:.7}
    #form-notification{z-index:9999;max-width:90%;width:300px;word-wrap:break-word}
    .visually-hidden{position:absolute!important;width:1px!important;height:1px!important;padding:0!important;margin:-1px!important;overflow:hidden!important;clip:rect(0,0,0,0)!important;white-space:nowrap!important;border:0!important}
    a:focus,button:focus,input:focus,select:focus,textarea:focus{outline:3px solid rgba(1,109,134,0.5);outline-offset:2px}
    @media (hover:none){a:focus,button:focus,input:focus,select:focus,textarea:focus{outline-width:2px}}
    .field-info,.signature-instructions,.hint-trigger,.tramite-card-description,.tramite-selection-subtitle{color:#555}
    </style>

    <!-- Formulario principal -->
    <form id="matriculacion-form" action="" method="POST" enctype="multipart/form-data">
        <div id="error-messages"></div>

        <!-- Selección de tipo de trámite -->
        <div id="tramite-selector-container">
            <h2 class="tramite-selection-title">Seleccione el tipo de trámite a realizar</h2>
            <p class="tramite-selection-subtitle">Elija la opción que mejor se adapte a sus necesidades. Cada tipo de trámite requiere documentación específica y tiene características diferentes.</p>
            
            <div class="tramite-cards-container">
                <div class="tramite-card" id="card-abanderamiento" data-tramite-type="abanderamiento">
                    <div class="tramite-card-header">
                        <div class="tramite-card-icon">
                            <i class="fas fa-flag"></i>
                        </div>
                        <div>
                            <div class="tramite-card-subtitle">Régimen general</div>
                            <h3 class="tramite-card-title">Abanderamiento / Matriculación</h3>
                        </div>
                    </div>
                    <p class="tramite-card-description">Trámite establecido en el artículo 9 del RD 1435/2010, aplicable a las embarcaciones de recreo que necesitan bandera española.</p>
                    <ul class="tramite-card-bullets">
                        <li>Procedimiento completo de matriculación</li>
                        <li>Aplicable a cualquier embarcación de recreo</li>
                        <li>Incluye asignación de bandera española</li>
                        <li>Obtención de indicativo de matrícula</li>
                    </ul>
                </div>
                
                <div class="tramite-card" id="card-inscripcion" data-tramite-type="inscripcion">
                    <div class="tramite-card-header">
                        <div class="tramite-card-icon">
                            <i class="fas fa-file-signature"></i>
                        </div>
                        <div>
                            <div class="tramite-card-subtitle">Régimen especial</div>
                            <h3 class="tramite-card-title">Inscripción</h3>
                        </div>
                    </div>
                    <p class="tramite-card-description">Régimen especial establecido en el artículo 8 del RD 1435/2010, aplicable a embarcaciones con características específicas.</p>
                    <ul class="tramite-card-bullets">
                        <li>Embarcaciones con marcado CE</li>
                        <li>Eslora igual o inferior a 12 metros</li>
                        <li>Proceso simplificado</li>
                        <li>Documentación reducida</li>
                    </ul>
                </div>
            </div>
            
            <button type="button" id="continue-with-tramite" class="select-tramite-button" disabled>
                Continuar con el trámite <i class="fas fa-arrow-right"></i>
            </button>
        </div>

        <!-- Campos ocultos -->
        <input type="hidden" name="tramite_id" value="<?php echo esc_attr($tramite_id); ?>">
        <input type="hidden" name="tipo_tramite" id="tipo_tramite_hidden" value="">
        <input type="hidden" name="tasas_hidden" id="tasas_hidden" value="88.50" />
        <input type="hidden" name="iva_hidden" id="iva_hidden" value="11.49" />
        <input type="hidden" name="honorarios_hidden" id="honorarios_hidden" value="49.00" />
        <input type="hidden" name="signature_data" id="signature_data" value="" />

        <!-- Navegación del formulario -->
        <div id="form-navigation" class="hidden">
            <a href="#" class="nav-link active" data-page-id="page-datos-embarcacion" data-step="1">Datos Embarcación</a>
            <a href="#" class="nav-link" data-page-id="page-datos-propietario" data-step="2">Datos Propietario</a>
            <a href="#" class="nav-link" data-page-id="page-documentacion" data-step="3">Documentación</a>
            <a href="#" class="nav-link" data-page-id="page-payment" data-step="4">Pago</a>
            <div class="progress-bar-container">
                <div class="progress-bar" id="form-progress-bar" style="width: 25%;"></div>
            </div>
        </div>
        
        <!-- Ayuda flotante -->
        <div class="button-floating" id="help-button">
            <i class="fas fa-question"></i>
        </div>
        
        <!-- Modal de ayuda -->
        <div id="help-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999;">
            <div style="position: relative; width: 90%; max-width: 600px; margin: 50px auto; background: white; border-radius: 8px; padding: 20px; box-shadow: 0 10px 25px rgba(0,0,0,0.2);">
                <span style="position: absolute; top: 10px; right: 15px; font-size: 24px; cursor: pointer; color: #6c757d;" id="close-help-modal">&times;</span>
                <h3 style="color: #016d86; margin-top: 0;">Ayuda para completar el formulario</h3>
                <div id="help-content">
                    <p><strong>Consejos generales:</strong></p>
                    <ul>
                        <li>Puedes navegar entre las secciones usando el menú superior, siempre que hayas completado las secciones anteriores.</li>
                        <li>Los campos marcados con * son obligatorios.</li>
                        <li>El formulario guardará automáticamente tu progreso cada 2 minutos.</li>
                    </ul>
                    <p><strong>Sección actual:</strong> <span id="current-section-help">Datos Embarcación</span></p>
                    <div id="section-specific-help"></div>
                </div>
            </div>
        </div>

        <!-- Overlay de carga -->
        <div id="loading-overlay">
            <div class="spinner"></div>
            <p>Procesando, por favor espera...</p>
        </div>

        <!-- Página de Datos de la Embarcación -->
        <div id="page-datos-embarcacion" class="form-page hidden">
            <h2 style="text-align: center; color: #016d86;">Datos de la Embarcación</h2>
            
            <!-- Banner informativo del tipo de trámite seleccionado -->
            <div class="tramite-selector" id="tramite-info-banner">
                <div class="tramite-title">
                    Tipo de Trámite: <span id="tramite-type-display">Abanderamiento / Matriculación</span>
                    <button type="button" id="change-tramite-button" class="change-tramite-button">
                        <i class="fas fa-exchange-alt"></i> Cambiar
                    </button>
                </div>
                <p class="info-text" id="info-abanderamiento" style="display: block; margin-top: 10px; font-size: 14px; color: #555;">
                    Régimen general, establecido en el artículo 9 del RD 1435/2010, aplicable a las embarcaciones de recreo.
                </p>
                <p class="info-text" id="info-inscripcion" style="display: none; margin-top: 10px; font-size: 14px; color: #555;">
                    Régimen especial, establecido en el artículo 8 del RD 1435/2010, aplicable a embarcaciones de recreo con marcado CE de eslora igual o inferior a 12 metros.
                </p>
            </div>
            
            <div class="form-section collapsed" id="seccion-datos-principales">
                <div class="form-section-header">
                    <h3><i class="fas fa-ship"></i> Datos Principales</h3>
                    <span class="section-toggle">▲</span>
                </div>
                
                <div class="form-section-content">
                    <div class="form-row">
                        <div class="form-col">
                            <label for="marca">Marca: <span class="required">*</span>
                                <span class="hint-trigger">?
                                    <span class="field-hint">Ingrese la marca del fabricante de la embarcación. Por ejemplo: Bayliner, Sea Ray, Yamaha, etc.</span>
                                </span>
                            </label>
                            <div class="input-with-icon">
                                <i class="fas fa-tag"></i>
                                <input type="text" id="marca" name="marca" required placeholder="Ej: Bayliner">
                            </div>
                        </div>
                        
                        <div class="form-col">
                            <label for="modelo">Modelo: <span class="required">*</span></label>
                            <div class="input-with-icon">
                                <i class="fas fa-info-circle"></i>
                                <input type="text" id="modelo" name="modelo" required placeholder="Ej: Element E16">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <label>Tipo: <span class="required">*</span></label>
                            <div class="radio-group">
                                <label>
                                    <input type="radio" name="tipo_embarcacion" value="vela" required> 
                                    <span>Vela</span>
                                </label>
                                <label>
                                    <input type="radio" name="tipo_embarcacion" value="motor" required> 
                                    <span>Motor</span>
                                </label>
                            </div>
                        </div>
                        
                        <div class="form-col">
                            <label for="categoria_diseno">Categoría de diseño: <span class="required">*</span></label>
                            <input type="text" id="categoria_diseno" name="categoria_diseno" required>
                        </div>
                    </div>
                
                    <div class="form-row">
                        <div class="form-col">
                            <label for="num_serie">Nº de serie / CIN: <span class="required">*</span></label>
                            <input type="text" id="num_serie" name="num_serie" required>
                        </div>
                        
                        <div class="form-col">
                            <label for="eslora">Eslora (m): <span class="required">*</span></label>
                            <input type="number" id="eslora" name="eslora" step="0.01" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <label for="manga">Manga (m): <span class="required">*</span></label>
                            <input type="number" id="manga" name="manga" step="0.01" required>
                        </div>
                        
                        <div class="form-col">
                            <label for="num_max_personas">Nº máximo personas: <span class="required">*</span></label>
                            <input type="number" id="num_max_personas" name="num_max_personas" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <label for="carga_maxima">Carga máxima (kg): <span class="required">*</span></label>
                            <input type="number" id="carga_maxima" name="carga_maxima" required>
                        </div>
                        
                        <div class="form-col">
                            <label for="material_casco">Material del casco: <span class="required">*</span></label>
                            <input type="text" id="material_casco" name="material_casco" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <label for="zona_navegacion">Zona de navegación: <span class="required">*</span></label>
                            <input type="text" id="zona_navegacion" name="zona_navegacion" required>
                        </div>
                        
                        <div class="form-col">
                            <label for="fecha_adquisicion">Fecha de adquisición: <span class="required">*</span></label>
                            <input type="date" id="fecha_adquisicion" name="fecha_adquisicion" required>
                        </div>
                    </div>
                    
                    <div id="matriculacion-info" style="display: none;">
                        <div class="form-row">
                            <div class="form-col">
                                <label for="nib">NIB:</label>
                                <input type="text" id="nib" name="nib">
                            </div>
                            
                            <div class="form-col">
                                <label for="matricula">Indicativo de Matrícula:</label>
                                <input type="text" id="matricula" name="matricula">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="form-section collapsed">
                <div class="form-section-header">
                    <h3><i class="fas fa-cogs"></i> Datos del Motor / Motores</h3>
                    <span class="section-toggle">▲</span>
                </div>
                
                <div class="form-section-content">
                    <div class="form-row">
                        <div class="form-col">
                            <label for="motor_marca">Marca: <span class="required">*</span></label>
                            <input type="text" id="motor_marca" name="motor_marca" required>
                        </div>
                        
                        <div class="form-col">
                            <label for="motor_modelo">Modelo: <span class="required">*</span></label>
                            <input type="text" id="motor_modelo" name="motor_modelo" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <label for="motor_potencia">Potencia (kW): <span class="required">*</span></label>
                            <input type="number" id="motor_potencia" name="motor_potencia" step="0.1" required>
                        </div>
                        
                        <div class="form-col">
                            <label for="motor_num_serie">Nº de serie: <span class="required">*</span></label>
                            <input type="text" id="motor_num_serie" name="motor_num_serie" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <label>Combustible: <span class="required">*</span></label>
                            <div class="radio-group">
                                <label><input type="radio" name="combustible" value="gasolina" required><span>Gasolina</span></label>
                                <label><input type="radio" name="combustible" value="diesel" required><span>Diesel</span></label>
                                <label><input type="radio" name="combustible" value="otros" required><span>Otros</span></label>
                            </div>
                        </div>
                        
                        <div class="form-col">
                            <label for="combustible_otros" id="label-combustible-otros" style="display:none">Especificar combustible:</label>
                            <input type="text" id="combustible_otros" name="combustible_otros" style="display:none">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <label for="num_motores">Nº motores: <span class="required">*</span></label>
                            <input type="number" id="num_motores" name="num_motores" min="1" value="1" required>
                        </div>
                        
                        <div class="form-col">
                            <label>Tipo: <span class="required">*</span></label>
                            <div class="radio-group">
                                <label><input type="radio" name="tipo_motor" value="fueraborda" required><span>Fueraborda</span></label>
                                <label><input type="radio" name="tipo_motor" value="intraborda" required><span>Intraborda</span></label>
                                <label><input type="radio" name="tipo_motor" value="mixto" required><span>Mixto</span></label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="form-section collapsed">
                <div class="form-section-header">
                    <h3><i class="fas fa-life-ring"></i> Datos de Embarcaciones Auxiliares (opcional)</h3>
                    <span class="section-toggle">▲</span>
                </div>
                
                <div class="form-section-content">
                    <div class="form-row">
                        <div class="form-col">
                            <label for="aux_marca">Marca:</label>
                            <input type="text" id="aux_marca" name="aux_marca">
                        </div>
                        
                        <div class="form-col">
                            <label for="aux_modelo">Modelo:</label>
                            <input type="text" id="aux_modelo" name="aux_modelo">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <label for="aux_categoria_diseno">Categoría de diseño:</label>
                            <input type="text" id="aux_categoria_diseno" name="aux_categoria_diseno">
                        </div>
                        
                        <div class="form-col">
                            <label for="aux_num_serie">Nº de serie / CIN:</label>
                            <input type="text" id="aux_num_serie" name="aux_num_serie">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <label for="aux_eslora">Eslora (m):</label>
                            <input type="number" id="aux_eslora" name="aux_eslora" step="0.01">
                        </div>
                        
                        <div class="form-col">
                            <label for="aux_num_max_personas">Nº máximo de personas:</label>
                            <input type="number" id="aux_num_max_personas" name="aux_num_max_personas">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <label for="aux_num_embarcaciones">Nº de embarcaciones auxiliares:</label>
                            <input type="number" id="aux_num_embarcaciones" name="aux_num_embarcaciones" min="0" value="0">
                        </div>
                        
                        <div class="form-col">
                            <label for="aux_fecha_adquisicion">Fecha de adquisición:</label>
                            <input type="date" id="aux_fecha_adquisicion" name="aux_fecha_adquisicion">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Página de Datos del Propietario -->
        <div id="page-datos-propietario" class="form-page hidden">
            <h2 style="text-align: center; color: #016d86;">Datos del Propietario</h2>
            
            <div class="form-section collapsed">
                <div class="form-section-header">
                    <h3><i class="fas fa-user"></i> Propietario o Titular Registral</h3>
                    <span class="section-toggle">▲</span>
                </div>
                
                <div class="form-section-content">
                    <div class="form-row">
                        <div class="form-col">
                            <label for="propietario_nombre">Apellidos y nombre o Razón social: <span class="required">*</span></label>
                            <input type="text" id="propietario_nombre" name="propietario_nombre" required>
                        </div>
                        
                        <div class="form-col">
                            <label for="propietario_nif">NIF/CIF: <span class="required">*</span></label>
                            <input type="text" id="propietario_nif" name="propietario_nif" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <label for="propietario_via">Vía Pública: <span class="required">*</span></label>
                            <input type="text" id="propietario_via" name="propietario_via" required>
                        </div>
                        
                        <div class="form-col">
                            <label for="propietario_numero">Número: <span class="required">*</span></label>
                            <input type="text" id="propietario_numero" name="propietario_numero" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <label for="propietario_escalera">Escalera:</label>
                            <input type="text" id="propietario_escalera" name="propietario_escalera">
                        </div>
                        
                        <div class="form-col">
                            <label for="propietario_piso">Piso:</label>
                            <input type="text" id="propietario_piso" name="propietario_piso">
                        </div>
                        
                        <div class="form-col">
                            <label for="propietario_puerta">Puerta:</label>
                            <input type="text" id="propietario_puerta" name="propietario_puerta">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <label for="propietario_cp">Código Postal: <span class="required">*</span></label>
                            <input type="text" id="propietario_cp" name="propietario_cp" required>
                        </div>
                        
                        <div class="form-col">
                            <label for="propietario_localidad">Localidad: <span class="required">*</span></label>
                            <input type="text" id="propietario_localidad" name="propietario_localidad" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <label for="propietario_provincia">Provincia: <span class="required">*</span></label>
                            <input type="text" id="propietario_provincia" name="propietario_provincia" required>
                        </div>
                        
                        <div class="form-col">
                            <label for="propietario_pais">País: <span class="required">*</span></label>
                            <input type="text" id="propietario_pais" name="propietario_pais" required value="España">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <label for="propietario_telefono">Teléfono: <span class="required">*</span></label>
                            <input type="tel" id="propietario_telefono" name="propietario_telefono" required>
                        </div>
                        
                        <div class="form-col">
                            <label for="propietario_movil">Teléfono móvil: <span class="required">*</span></label>
                            <input type="tel" id="propietario_movil" name="propietario_movil" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <label for="propietario_email">Email: <span class="required">*</span></label>
                            <input type="email" id="propietario_email" name="propietario_email" required>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="form-section collapsed">
                <div class="form-section-header">
                    <h3><i class="fas fa-user-tie"></i> Autorización de Representación</h3>
                    <span class="section-toggle">▲</span>
                </div>
                
                <div class="form-section-content">
                    <div class="form-row">
                        <div class="form-col">
                            <p style="margin-bottom: 15px;">Por la presente, autorizo a <strong>Tramitfy S.L.</strong> con CIF B12345678 y domicilio en Calle Principal 123, 28001 Madrid, a actuar como mi representante legal para la tramitación y gestión del procedimiento de <span id="tramite-type-display-auth">Abanderamiento/Matriculación</span> de embarcación ante las autoridades competentes.</p>
                            
                            <div class="form-row">
                                <div class="form-col">
                                    <label for="autorizacion_nombre">Nombre y apellidos del autorizante: <span class="required">*</span></label>
                                    <input type="text" id="autorizacion_nombre" name="autorizacion_nombre" required>
                                </div>
                                
                                <div class="form-col">
                                    <label for="autorizacion_dni">DNI/NIE: <span class="required">*</span></label>
                                    <input type="text" id="autorizacion_dni" name="autorizacion_dni" required>
                                </div>
                            </div>
                            
                            <p style="margin-top: 15px;">Doy conformidad para que Tramitfy S.L. pueda presentar y recoger cuanta documentación sea necesaria, subsanar defectos, pagar tasas y realizar cuantas actuaciones sean precisas para la correcta finalización del procedimiento.</p>
                            
                            <input type="hidden" name="tipo_representante" value="representante">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-col">
                        <div class="signature-box">
                            <div class="signature-header">
                                <h4 class="signature-title">Firma Digital</h4>
                                <span class="signature-status" id="signature-status">Sin firmar</span>
                            </div>
                            <div class="signature-content">
                                <p class="signature-instructions">Firme a continuación:</p>
                                <div id="device-specific-instructions">
                                    <div class="device-instruction" id="desktop-instruction">
                                        <i class="fas fa-mouse-pointer"></i> Use el ratón manteniendo pulsado el botón izquierdo
                                    </div>
                                    <div class="device-instruction" id="tablet-instruction" style="display: none;">
                                        <i class="fas fa-hand-pointer"></i> Use su dedo o un stylus para firmar en la pantalla
                                    </div>
                                    <div class="device-instruction" id="mobile-instruction" style="display: none;">
                                        <i class="fas fa-hand-pointer"></i> Use su dedo para firmar en la pantalla
                                    </div>
                                </div>
                                <div class="signature-pad-container">
                                    <canvas id="signature-pad" width="600" height="200"></canvas>
                                </div>
                                <div class="signature-actions">
                                    <button type="button" id="clear-signature" class="clear-btn"><i class="fas fa-eraser"></i> Limpiar firma</button>
                                    <button type="button" id="zoom-signature" class="zoom-btn"><i class="fas fa-search-plus"></i> Ampliar</button>
                                </div>
                            </div>
                        </div>
                        <div id="signature-modal" style="display: none;">
                            <div class="signature-modal-content">
                                <span class="close-signature-modal">&times;</span>
                                <h3>Firma Digital</h3>
                                <div class="modal-signature-container">
                                    <canvas id="modal-signature-pad" width="800" height="400"></canvas>
                                </div>
                                <button type="button" id="modal-clear-signature" class="clear-btn"><i class="fas fa-eraser"></i> Limpiar</button>
                                <button type="button" id="modal-accept-signature" class="accept-btn"><i class="fas fa-check"></i> Aceptar</button>
                            </div>
                        </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Página de Documentación -->
        <div id="page-documentacion" class="form-page hidden">
            <h2 style="text-align: center; color: #016d86;">Documentación</h2>
            
            <div id="documentacion-matriculacion" class="form-section collapsed">
                <div class="form-section-header">
                    <h3><i class="fas fa-clipboard-list"></i> Documentación para Matriculación/Abanderamiento</h3>
                    <span class="section-toggle">▲</span>
                </div>
                
                <div class="form-section-content">
                    <div class="upload-section">
                        <div class="upload-item">
                            <label for="upload-declaracion-conformidad-embarcacion">Declaración de conformidad de la embarcación:</label>
                            <input type="file" id="upload-declaracion-conformidad-embarcacion" name="upload_declaracion_conformidad_embarcacion" required>
                            <a href="#" class="view-example" data-doc="conformidad-embarcacion">Ver ejemplo</a>
                        </div>
                        
                        <div class="upload-item">
                            <label for="upload-declaracion-conformidad-motor">Declaración de conformidad del motor:</label>
                            <input type="file" id="upload-declaracion-conformidad-motor" name="upload_declaracion_conformidad_motor" required>
                            <a href="#" class="view-example" data-doc="conformidad-motor">Ver ejemplo</a>
                        </div>
                        
                        <div class="upload-item">
                            <label for="upload-certificado-homologacion">Certificado de homologación:</label>
                            <input type="file" id="upload-certificado-homologacion" name="upload_certificado_homologacion">
                            <a href="#" class="view-example" data-doc="homologacion">Ver ejemplo</a>
                        </div>
                        
                        <div class="upload-item">
                            <label for="upload-impuesto">Documentación acreditativa del pago del Impuesto:</label>
                            <input type="file" id="upload-impuesto" name="upload_impuesto" required>
                            <a href="#" class="view-example" data-doc="impuesto">Ver ejemplo</a>
                        </div>
                        
                        <div class="upload-item">
                            <label for="upload-titulo-propiedad">Título de adquisición de la propiedad:</label>
                            <input type="file" id="upload-titulo-propiedad" name="upload_titulo_propiedad" required>
                            <a href="#" class="view-example" data-doc="titulo-propiedad">Ver ejemplo</a>
                        </div>
                        
                        <div class="upload-item">
                            <label for="upload-manual-propietario">Sección del Manual del Propietario con datos principales:</label>
                            <input type="file" id="upload-manual-propietario" name="upload_manual_propietario" required>
                            <a href="#" class="view-example" data-doc="manual-propietario">Ver ejemplo</a>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-col">
                                <label for="lista_matricula">Lista de la matrícula donde solicita:</label>
                                <input type="text" id="lista_matricula" name="lista_matricula">
                            </div>
                            
                            <div class="form-col">
                                <label for="nombre_propuesto">Nombre propuesto para la embarcación:</label>
                                <input type="text" id="nombre_propuesto" name="nombre_propuesto">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div id="documentacion-inscripcion" class="form-section collapsed" style="display: none;">
                <div class="form-section-header">
                    <h3><i class="fas fa-clipboard-check"></i> Documentación para Inscripción</h3>
                    <span class="section-toggle">▲</span>
                </div>
                
                <div class="form-section-content">
                    <div class="upload-section">
                        <div class="upload-item">
                            <label for="upload-factura-embarcacion">Factura de compra o título de la embarcación:</label>
                            <input type="file" id="upload-factura-embarcacion" name="upload_factura_embarcacion">
                            <a href="#" class="view-example" data-doc="factura-embarcacion">Ver ejemplo</a>
                        </div>
                        
                        <div class="upload-item">
                            <label for="upload-factura-motor">Factura de compra del motor:</label>
                            <input type="file" id="upload-factura-motor" name="upload_factura_motor">
                            <a href="#" class="view-example" data-doc="factura-motor">Ver ejemplo</a>
                        </div>
                        
                        <div class="upload-item">
                            <label for="upload-impuesto-inscripcion">Documentación acreditativa del pago del Impuesto:</label>
                            <input type="file" id="upload-impuesto-inscripcion" name="upload_impuesto_inscripcion">
                            <a href="#" class="view-example" data-doc="impuesto">Ver ejemplo</a>
                        </div>
                        
                        <div class="upload-item">
                            <label for="upload-certificado-registro">Certificado de Registro Español/Permiso de Navegación a canjear (si procede):</label>
                            <input type="file" id="upload-certificado-registro" name="upload_certificado_registro">
                            <a href="#" class="view-example" data-doc="certificado-registro">Ver ejemplo</a>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-col">
                                <label for="lista_inscripcion">Lista de la matrícula donde solicita inscripción:</label>
                                <input type="text" id="lista_inscripcion" name="lista_inscripcion">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="terms-container">
                <label>
                    <input type="checkbox" name="terms_accept" required>
                    Declaro que los datos consignados son ciertos, que se ha cumplido con los trámites legales aduaneros y satisfechos los impuestos correspondientes en su caso, y que la embarcación cumple con los requisitos para ser inscrita. Autorizo a Tramitfy a realizar este trámite en mi nombre.
                </label>
            </div>
        </div>

        <!-- Página de Pago -->
        <div id="page-payment" class="form-page hidden">
            <h2 style="text-align: center; color: #016d86;">Información de Pago</h2>
            
            <div class="payment-card">
                <h3 class="payment-title">Resumen de su solicitud</h3>
                
                <div class="payment-row">
                    <span class="payment-label">Tipo de trámite:</span>
                    <span class="payment-value" id="payment-tramite-type">Matriculación/Abanderamiento</span>
                </div>
                
                <div class="payment-row">
                    <span class="payment-label">Embarcación:</span>
                    <span class="payment-value" id="payment-boat-info">-</span>
                </div>
                
                <div class="payment-row">
                    <span class="payment-label">Propietario:</span>
                    <span class="payment-value" id="payment-owner-info">-</span>
                </div>
                
                <div class="payment-row">
                    <span class="payment-label">Número de trámite:</span>
                    <span class="payment-value"><?php echo esc_attr($tramite_id); ?></span>
                </div>
            </div>
            
            <div class="payment-card">
                <h3 class="payment-title">Detalle del precio</h3>
                
                <div class="payment-row">
                    <span class="payment-label">Tasas y honorarios:</span>
                    <span class="payment-value">88.50 €</span>
                </div>
                
                <div class="payment-row">
                    <span class="payment-label">IVA (21%):</span>
                    <span class="payment-value">11.49 €</span>
                </div>
                
                <div class="payment-row" id="discount-line" style="display:none; color: #28a745;">
                    <span class="payment-label">Descuento aplicado:</span>
                    <span class="payment-value" id="discount-amount">-0.00 €</span>
                </div>
                
                <div class="payment-total">
                    <span class="payment-label">Total a pagar:</span>
                    <span class="payment-value" id="final-amount">99.99 €</span>
                </div>
            </div>

            <div class="payment-card">
                <h3 class="payment-title">¿Tienes un código de descuento?</h3>
                
                <div class="coupon-container">
                    <div class="coupon-input-wrapper">
                        <input type="text" id="coupon_code" name="coupon_code" placeholder="Introduce tu código aquí" />
                        <button type="button" class="apply-coupon-btn" id="apply-coupon">Aplicar</button>
                    </div>
                    <div id="coupon-message" class="coupon-message hidden"></div>
                </div>
            </div>

            <div class="payment-card">
                <h3 class="payment-title">Método de pago</h3>
                
                <div id="payment-element"></div>
                
                <div id="payment-message" class="status-message hidden"></div>
                
                <div class="terms-container">
                    <label>
                        <input type="checkbox" name="terms_accept_pago" required>
                        Acepto los 
                        <a href="https://tramitfy.es/terminos-y-condiciones-de-uso/" target="_blank">
                            términos y condiciones de pago
                        </a>
                    </label>
                </div>
                
                <div class="payment-actions">
                    <button id="submit" class="submit-button" disabled>
                        <i class="fas fa-lock"></i> Pagar ahora
                    </button>
                </div>
            </div>
            
            <div class="button-container" style="justify-content: space-between; margin-top: 15px;">
                <button type="button" class="button" id="prevButtonPayment">
                    <i class="fas fa-arrow-left"></i> Anterior
                </button>
            </div>
        </div>

        <!-- Botones de navegación principales -->
        <div class="button-container" id="main-button-container" style="display: none;">
            <button type="button" class="button" id="prevButtonMain" style="display: none;">
                <i class="fas fa-arrow-left"></i> Anterior
            </button>
            <button type="button" class="button" id="nextButtonMain">
                Siguiente <i class="fas fa-arrow-right"></i>
            </button>
        </div>
    </form>

    <!-- Popup para ejemplos de documentos -->
    <div id="document-popup">
        <div class="popup-content">
            <span class="close-popup">&times;</span>
            <h3>Ejemplo de documento</h3>
            <img id="document-example-image" src="" alt="Ejemplo de documento">
        </div>
    </div>

    <!-- Incluir FontAwesome para íconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <script>
        // Implementación avanzada de firma horizontal con soporte de orientación optimizado
(function() {
    // Configuración global
    const config = {
        colors: {
            primary: '#016d86',
            primaryDark: '#01556a',
            accent: '#28a745',
            light: '#f8f9fa',
            dark: '#343a40',
            error: '#dc3545',
            border: '#e0e0e0'
        },
        animations: {
            duration: 300 // ms
        },
        text: {
            signHere: 'FIRME AQUÍ',
            instructions: 'Use el dedo para firmar en el área indicada',
            rotateDevice: 'Para una mejor experiencia, gire su dispositivo a horizontal',
            clear: 'Borrar',
            accept: 'Confirmar firma'
        },
        modalId: 'signature-modal-advanced',
        orientation: {
            get isLandscape() {
                return window.innerWidth > window.innerHeight;
            }
        },
        device: {
            get isIOS() { return /iPhone|iPad|iPod/i.test(navigator.userAgent); },
            get isAndroid() { return /Android/i.test(navigator.userAgent); },
            get isMobile() { return this.isIOS || this.isAndroid || /Mobi/i.test(navigator.userAgent); }
        }
    };

    // Clase principal para gestionar la experiencia de firma
    // Clase principal para gestionar la experiencia de firma
class EnhancedSignatureExperience {
    constructor() {
        // Elementos DOM originales
        this.originalModal = document.getElementById('signature-modal');
        this.originalCanvas = document.getElementById('modal-signature-pad');
        this.originalZoomButton = document.getElementById('zoom-signature');
        this.originalAcceptButton = document.getElementById('modal-accept-signature');
        this.originalClearButton = document.getElementById('modal-clear-signature');
        this.originalMainCanvas = document.getElementById('signature-pad');
        
        // Estado
        this.isEnhancedModalOpen = false;
        this.lastOrientation = config.orientation.isLandscape ? 'landscape' : 'portrait';
        this.resizeTimeout = null;
        this.signatureData = null;
        this.orientationChangeHandled = false;
        
        // Elementos DOM mejorados (se crearán dinámicamente)
        this.enhancedModal = null;
        this.enhancedCanvas = null;
        this.enhancedSignaturePad = null;
    }
    
    /**
     * Inicializa la experiencia mejorada
     */
    initialize() {
        // Verificar que existan los elementos necesarios
        if (!this.originalZoomButton || !this.originalMainCanvas) {
            console.warn('No se encontraron elementos necesarios para la experiencia de firma');
            return false;
        }
        
        // Crear el nuevo modal de firma mejorado
        this.createEnhancedModal();
        
        // Mejorar el botón de zoom
        this.enhanceZoomButton();
        
        // Configurar eventos para cambios de orientación
        this.setupOrientationHandling();
        
        // Mejorar restauración de firma durante desplazamiento
        this.enhanceSignatureRestoration();
        
        // Inicialización completa
        return true;
    }
    
    /**
     * Crea el nuevo modal avanzado para firma
     */
    createEnhancedModal() {
        // Remover modal anterior si existe
        const existingModal = document.getElementById(config.modalId);
        if (existingModal) {
            document.body.removeChild(existingModal);
        }
        
        // Crear el nuevo modal
        this.enhancedModal = document.createElement('div');
        this.enhancedModal.id = config.modalId;
        this.enhancedModal.className = 'signature-modal-enhanced';
        
        // Crear la estructura interna del modal
        this.enhancedModal.innerHTML = `
            <div class="enhanced-modal-content">
                <div class="enhanced-modal-header">
                    <h3>Firma Digital</h3>
                    <div class="orientation-indicator">
                        <i class="fas fa-mobile-alt"></i>
                        <span>${config.text.rotateDevice}</span>
                    </div>
                    <button class="enhanced-close-button" aria-label="Cerrar">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <div class="enhanced-signature-container">
                    <div class="signature-guide">
                        <div class="signature-line"></div>
                        <div class="signature-instruction">${config.text.signHere}</div>
                    </div>
                    <canvas id="enhanced-signature-canvas"></canvas>
                </div>
                
                <div class="enhanced-modal-footer">
                    <p class="enhanced-instructions">${config.text.instructions}</p>
                    <div class="enhanced-button-container">
                        <button class="enhanced-clear-button">
                            <i class="fas fa-eraser"></i> ${config.text.clear}
                        </button>
                        <button class="enhanced-accept-button" disabled>
                            <i class="fas fa-check"></i> ${config.text.accept}
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        // Aplicar estilos
        this.applyEnhancedStyles();
        
        // Agregar a la página
        document.body.appendChild(this.enhancedModal);
        
        // Establecer referencias a los elementos creados
        this.enhancedCanvas = document.getElementById('enhanced-signature-canvas');
        this.closeButton = this.enhancedModal.querySelector('.enhanced-close-button');
        this.clearButton = this.enhancedModal.querySelector('.enhanced-clear-button');
        this.acceptButton = this.enhancedModal.querySelector('.enhanced-accept-button');
        this.orientationIndicator = this.enhancedModal.querySelector('.orientation-indicator');
        
        // Configurar eventos
        this.setupEnhancedModalEvents();
    }
    
    /**
     * Aplica estilos al modal mejorado
     */
    applyEnhancedStyles() {
        // Crear elemento de estilo
        const styleElement = document.createElement('style');
        styleElement.id = 'enhanced-signature-styles';
        
        // Definir los estilos
        styleElement.textContent = `
/* Modal container */
.signature-modal-enhanced {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.9);
    z-index: 10000;
    display: none;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    animation: fadeIn 0.3s ease;
    -webkit-touch-callout: none; /* Prevenir popup WhatsApp */
    -webkit-user-select: none;
    user-select: none;
}
            
/* Modal content - siempre en vertical */
.enhanced-modal-content {
    position: relative;
    width: 95%;
    height: 92%;
    max-width: 95%;
    max-height: 92%;
    background-color: white;
    border-radius: 12px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    animation: zoomIn 0.3s ease;
    -webkit-touch-callout: none;
    -webkit-user-select: none;
    -webkit-tap-highlight-color: transparent;
    user-select: none;
}
            
            /* Header */
            .enhanced-modal-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 15px 20px;
                background-color: ${config.colors.light};
                border-bottom: 1px solid ${config.colors.border};
            }
            
            .enhanced-modal-header h3 {
                margin: 0;
                font-size: 20px;
                color: ${config.colors.primary};
            }
            
            .orientation-indicator {
                display: none; /* Ocultar permanentemente */
            }
            
            .enhanced-close-button {
                background: none;
                border: none;
                color: #6c757d;
                font-size: 24px;
                cursor: pointer;
                width: 36px;
                height: 36px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: background-color 0.2s ease;
            }
            
            .enhanced-close-button:hover {
                background-color: rgba(108, 117, 125, 0.1);
            }
            
  /* Signature container */
.enhanced-signature-container {
    position: relative;
    flex: 1;
    width: 100%;
    background-color: white;
    overflow: hidden;
    touch-action: none;
    -webkit-touch-callout: none;
    -webkit-user-select: none;
    user-select: none;
}
            
#enhanced-signature-canvas {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    touch-action: none;
    -ms-touch-action: none;
    user-select: none;
    -webkit-touch-callout: none; /* Prevenir popup WhatsApp */
    -webkit-user-select: none;
}
            
/* Signature guide - siempre en horizontal */
.signature-guide {
    position: absolute;
    top: 50%;
    left: 10px;
    right: 10px;
    z-index: 1;
    pointer-events: none;
    -webkit-touch-callout: none;
    -webkit-user-select: none;
    user-select: none;
}
            
            .signature-line {
                height: 2px;
                background-color: ${config.colors.primary};
                opacity: 0.5;
                box-shadow: 0 0 5px rgba(1, 109, 134, 0.2);
            }
            
            .signature-instruction {
                position: absolute;
                color: ${config.colors.primary};
                font-size: 20px;
                font-weight: bold;
                letter-spacing: 3px;
                opacity: 0.3;
                left: 50%;
                top: -15px;
                transform: translateX(-50%);
                white-space: nowrap;
                text-align: center;
            }
            
            /* Footer */
            .enhanced-modal-footer {
                padding: 15px 20px;
                background-color: ${config.colors.light};
                border-top: 1px solid ${config.colors.border};
                display: flex;
                flex-direction: column;
                gap: 10px;
            }
            
            .enhanced-instructions {
                margin: 0;
                text-align: center;
                color: #6c757d;
                font-size: 14px;
            }
            
            .enhanced-button-container {
                display: flex;
                justify-content: space-between;
                gap: 15px;
            }
            
            .enhanced-button-container button {
                flex: 1;
                padding: 12px 15px;
                border: none;
                border-radius: 30px;
                font-size: 16px;
                font-weight: 500;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: all 0.2s ease;
            }
            
            .enhanced-button-container button i {
                margin-right: 8px;
            }
            
            .enhanced-clear-button {
                background-color: #f8d7da;
                color: #721c24;
            }
            
            .enhanced-clear-button:hover {
                background-color: #f1b0b7;
                transform: translateY(-2px);
                box-shadow: 0 3px 5px rgba(114, 28, 36, 0.2);
            }
            
            .enhanced-accept-button {
                background-color: ${config.colors.primary};
                color: white;
            }
            
            .enhanced-accept-button:hover {
                background-color: ${config.colors.primaryDark};
                transform: translateY(-2px);
                box-shadow: 0 3px 5px rgba(1, 109, 134, 0.3);
            }
            
            .enhanced-accept-button:disabled {
                background-color: #ccc;
                color: #666;
                cursor: not-allowed;
                transform: none;
                box-shadow: none;
            }
            
            /* Animations */
            @keyframes fadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }
            
            @keyframes zoomIn {
                from { transform: scale(0.95); }
                to { transform: scale(1); }
            }
            
            /* Responsive adjustments */
            @media (max-height: 500px) {
                .enhanced-modal-header {
                    padding: 10px 15px;
                }
                
                .enhanced-modal-footer {
                    padding: 10px 15px;
                }
                
                .enhanced-instructions {
                    display: none;
                }
                
                .enhanced-button-container button {
                    padding: 8px 12px;
                    font-size: 14px;
                }
            }
            
            /* Forzar modo vertical siempre, sin importar la orientación del dispositivo */
            @media (orientation: landscape) {
                .enhanced-modal-content {
                    /* Limitar el ancho en modo horizontal para mantener proporción vertical */
                    width: auto;
                    max-width: 60%;
                }
                
                .signature-guide {
                    top: 50%;
                }
            }
            
            /* Portrait specific styles */
            @media (orientation: portrait) {
                .signature-guide {
                    top: 40%;
                }
            }
        `;
        
        // Añadir a la página
        document.head.appendChild(styleElement);
    }
    
    /**
     * Configura eventos para el modal mejorado
     */
    setupEnhancedModalEvents() {
        if (!this.enhancedModal) return;
        
        // Cerrar modal
        this.closeButton.addEventListener('click', () => this.closeEnhancedModal());
        
        // Cerrar al hacer clic fuera
        this.enhancedModal.addEventListener('click', (e) => {
            if (e.target === this.enhancedModal) {
                this.closeEnhancedModal();
            }
        });
        
        // Limpiar firma
        this.clearButton.addEventListener('click', () => {
            if (this.enhancedSignaturePad) {
                this.enhancedSignaturePad.clear();
                this.acceptButton.disabled = true;
                
                // Mostrar nuevamente la guía
                const signatureGuide = this.enhancedModal.querySelector('.signature-guide');
                if (signatureGuide) {
                    signatureGuide.style.opacity = '1';
                }
            }
        });
        
        // Aceptar firma
        this.acceptButton.addEventListener('click', () => {
            if (!this.enhancedSignaturePad || this.enhancedSignaturePad.isEmpty()) return;
            
            try {
                // Obtener datos de firma
                this.signatureData = this.enhancedSignaturePad.toDataURL();
                
                // Guardar en variables globales para compatibilidad
                window.mainSignatureData = this.signatureData;
                
                // Guardar en localStorage para persistencia
                try {
                    localStorage.setItem('matriculacion_signature', this.signatureData);
                    localStorage.setItem('matriculacion_signature_canvas_width', this.enhancedCanvas.width);
                    localStorage.setItem('matriculacion_signature_canvas_height', this.enhancedCanvas.height);
                } catch (e) {}
                
                // Actualizar el campo oculto
                const signatureField = document.getElementById('signature_data');
                if (signatureField) signatureField.value = this.signatureData;
                
                // Actualizar canvas principal
                this.updateMainCanvas();
                
                // Actualizar estado
                if (typeof window.updateSignatureStatus === 'function') {
                    window.updateSignatureStatus();
                }
                
                // Cerrar modal
                this.closeEnhancedModal();
                
                // Feedback visual
                const padContainer = document.querySelector('.signature-pad-container');
                if (padContainer) {
                    padContainer.style.transition = 'all 0.3s ease';
                    padContainer.style.boxShadow = '0 0 0 3px rgba(40, 167, 69, 0.3)';
                    padContainer.style.borderColor = '#28a745';
                    
                    setTimeout(() => {
                        padContainer.style.boxShadow = '';
                    }, 1500);
                }
            } catch (err) {
                console.error('Error al aceptar la firma:', err);
            }
        });
        
        // Eventos del canvas
        if (this.enhancedCanvas) {
            // Prevenir comportamiento predeterminado
            ['touchstart', 'touchmove', 'touchend'].forEach(eventName => {
                this.enhancedCanvas.addEventListener(eventName, (e) => {
                    e.preventDefault();
                }, { passive: false });
            });
            
            // Gestionar dibujo para mostrar/ocultar la guía
            const signatureGuide = this.enhancedModal.querySelector('.signature-guide');
            
            this.enhancedCanvas.addEventListener('mousedown', () => {
                if (signatureGuide) signatureGuide.style.opacity = '0';
            });
            
            this.enhancedCanvas.addEventListener('touchstart', () => {
                if (signatureGuide) signatureGuide.style.opacity = '0';
            });
        }
    }
    
    /**
     * Inicializa el pad de firma mejorado
     */
initializeEnhancedSignaturePad() {
    if (!this.enhancedCanvas || typeof SignaturePad !== 'function') return;
    
    try {
        // Ocultar widgets de WhatsApp antes de inicializar la firma
        if (typeof handleSignatureModalVisibility === 'function') {
            const chatWidgets = [
                // WhatsApp
                '.nta_wa_button', '.wa__btn_popup', '.wa__popup_chat_box', 
                '[class*="whatsapp"]', '[id*="whatsapp"]', 
                // Otros chats
                '.fb_dialog', '.crisp-client', '.intercom-frame',
                '[class*="chat"]', '[id*="chat"]', '[class*="wa-"]'
            ];
            
            chatWidgets.forEach(selector => {
                try {
                    document.querySelectorAll(selector).forEach(el => {
                        el.style.display = 'none';
                        el.style.visibility = 'hidden';
                        el.style.opacity = '0';
                        el.style.pointerEvents = 'none';
                        el.classList.add('hidden-during-signature');
                    });
                } catch (err) {}
            });
            
            // Forzar ocultamiento con una intervención más agresiva
            document.body.insertAdjacentHTML('beforeend', 
                `<style id="hide-chat-widgets">
                    .nta_wa_button, .wa__btn_popup, .wa__popup_chat_box,
                    [class*="whatsapp"], [id*="whatsapp"], 
                    .fb_dialog, .crisp-client, .intercom-frame,
                    [class*="chat"], [id*="chat"], [class*="wa-"] {
                        display: none !important;
                        visibility: hidden !important;
                        opacity: 0 !important;
                        pointer-events: none !important;
                        z-index: -1 !important;
                    }
                </style>`
            );
        }
        
        // Opciones mejoradas para el pad
        const options = {
            minWidth: 1.5,
            maxWidth: 3.5,
            penColor: "rgb(0, 0, 0)",
            backgroundColor: "rgb(255, 255, 255)",
            throttle: 16,
            velocityFilterWeight: 0.7,
            dotSize: 3.0
        };
        
        // Crear nueva instancia
        this.enhancedSignaturePad = new SignaturePad(this.enhancedCanvas, options);
        
        // Evento para actualizar botón cuando se dibuja
        this.enhancedSignaturePad.addEventListener('endStroke', () => {
            if (!this.enhancedSignaturePad.isEmpty()) {
                this.acceptButton.disabled = false;
            }
        });
        
        // Garantizar que los widgets permanezcan ocultos
        let keepWidgetsHidden = setInterval(() => {
            document.querySelectorAll('.nta_wa_button, .wa__btn_popup, [class*="whatsapp"], [class*="chat"]').forEach(el => {
                if (el.style.display !== 'none') {
                    el.style.display = 'none';
                    el.style.visibility = 'hidden';
                    el.classList.add('hidden-during-signature');
                }
            });
        }, 500);
        
        // Limpiar el intervalo cuando se cierre el modal
        this.enhancedModal.addEventListener('DOMNodeRemoved', () => {
            clearInterval(keepWidgetsHidden);
            const styleTag = document.getElementById('hide-chat-widgets');
            if (styleTag) styleTag.remove();
        });
        
        // También limpiar en el evento de cerrar
        this.closeButton.addEventListener('click', () => {
            clearInterval(keepWidgetsHidden);
            const styleTag = document.getElementById('hide-chat-widgets');
            if (styleTag) styleTag.remove();
        });
        
    } catch (e) {
        console.error('Error al inicializar SignaturePad mejorado:', e);
    }
}
    
    /**
     * Abre el modal de firma mejorado - siempre en modo vertical
     */
    openEnhancedModal() {
        if (!this.enhancedModal) return;
        
        // Mostrar modal
        this.enhancedModal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        
        // Bloquear scroll en iOS
        if (config.device.isIOS) {
            document.body.style.position = 'fixed';
            document.body.style.width = '100%';
        }
        
        this.isEnhancedModalOpen = true;
        
        // Inicializar el canvas y SignaturePad
        requestAnimationFrame(() => {
            this.resizeEnhancedCanvas();
            this.initializeEnhancedSignaturePad();
            
            // Restaurar firma existente si la hay
            if (window.mainSignatureData) {
                // Primero intentar inmediatamente
                this.restoreSignatureToEnhancedCanvas();
                
                // Luego intentar después de un breve retraso para asegurar que el canvas está listo
                setTimeout(() => {
                    this.restoreSignatureToEnhancedCanvas();
                }, 200);
            }
            
            // En iOS, aplicar un enfoque más agresivo
            if (config.device.isIOS) {
                setTimeout(() => {
                    this.resizeEnhancedCanvas();
                    if (window.mainSignatureData) {
                        this.restoreSignatureToEnhancedCanvas();
                    }
                }, 300);
            }
        });
    }
    
    /**
     * Cierra el modal mejorado
     */
    closeEnhancedModal() {
        if (!this.enhancedModal) return;
        
        // Ocultar modal con animación
        this.enhancedModal.style.opacity = '0';
        
        setTimeout(() => {
            this.enhancedModal.style.display = 'none';
            this.enhancedModal.style.opacity = '1';
            document.body.style.overflow = '';
            
            // Desbloquear scroll en iOS
            if (config.device.isIOS) {
                document.body.style.position = '';
                document.body.style.width = '';
            }
            
            this.isEnhancedModalOpen = false;
        }, 300);
    }
    
    /**
     * Adapta el tamaño del canvas mejorado - forzando siempre orientación vertical
     */
    resizeEnhancedCanvas() {
        if (!this.enhancedCanvas) return;
        
        try {
            const container = this.enhancedCanvas.parentElement;
            if (!container) return;
            
            // Obtener dimensiones del contenedor
            const rect = container.getBoundingClientRect();
            const ratio = window.devicePixelRatio || 1;
            
            // Determinar si estamos en modo horizontal o vertical
            const isLandscape = window.innerWidth > window.innerHeight;
            
            let canvasWidth, canvasHeight;
            
            if (isLandscape) {
                // En modo horizontal, limitar el ancho para mantener proporción vertical
                canvasWidth = Math.min(rect.width, rect.height * 0.7);
                canvasHeight = rect.height;
            } else {
                // En modo vertical, usar todo el espacio disponible
                canvasWidth = rect.width;
                canvasHeight = rect.height;
            }
            
            // Establecer dimensiones físicas considerando DPI
            this.enhancedCanvas.width = canvasWidth * ratio;
            this.enhancedCanvas.height = canvasHeight * ratio;
            
            // Establecer dimensiones visuales
            this.enhancedCanvas.style.width = canvasWidth + 'px';
            this.enhancedCanvas.style.height = canvasHeight + 'px';
            
            // Centrar canvas horizontalmente si estamos en landscape
            if (isLandscape) {
                this.enhancedCanvas.style.marginLeft = 'auto';
                this.enhancedCanvas.style.marginRight = 'auto';
                this.enhancedCanvas.style.display = 'block';
            } else {
                this.enhancedCanvas.style.marginLeft = '';
                this.enhancedCanvas.style.marginRight = '';
            }
            
            // Escalar contexto
            const context = this.enhancedCanvas.getContext('2d');
            if (context) {
                context.scale(ratio, ratio);
                context.fillStyle = "#ffffff";
                context.fillRect(0, 0, canvasWidth, canvasHeight);
            }
            
            // Si hay una firma existente, restaurarla
            if (this.signatureData || window.mainSignatureData) {
                // Pequeño retraso para asegurar que el canvas está completamente redimensionado
                setTimeout(() => {
                    this.restoreSignatureToEnhancedCanvas();
                }, 100);
            }
        } catch (e) {
            console.error('Error al redimensionar canvas mejorado:', e);
        }
    }
    
    /**
     * Restaura la firma actual al canvas mejorado - optimizado para orientación vertical
     */
    restoreSignatureToEnhancedCanvas() {
        const signatureData = this.signatureData || window.mainSignatureData;
        if (!signatureData || !this.enhancedCanvas || !this.enhancedSignaturePad) return;
        
        try {
            // Limpiar canvas
            this.enhancedSignaturePad.clear();
            
            const image = new Image();
            image.onload = () => {
                try {
                    const ctx = this.enhancedCanvas.getContext('2d');
                    if (!ctx) return;
                    
                    // Usar las dimensiones actuales del canvas
                    const dpr = window.devicePixelRatio || 1;
                    const canvasWidth = this.enhancedCanvas.width / dpr;
                    const canvasHeight = this.enhancedCanvas.height / dpr;
                    
                    ctx.fillStyle = "#ffffff";
                    ctx.fillRect(0, 0, canvasWidth, canvasHeight);
                    
                    // Siempre optimizar para mostrar mejor en vertical
                    // independientemente de la orientación actual del dispositivo
                    const ratio = Math.min(
                        (canvasWidth * 0.85) / image.width,
                        (canvasHeight * 0.65) / image.height
                    );
                    
                    const newWidth = image.width * ratio;
                    const newHeight = image.height * ratio;
                    
                    const x = (canvasWidth - newWidth) / 2;
                    const y = (canvasHeight - newHeight) / 2;
                    
                    // Mejorar calidad de imagen
                    ctx.imageSmoothingEnabled = true;
                    ctx.imageSmoothingQuality = 'high';
                    
                    ctx.drawImage(image, x, y, newWidth, newHeight);
                    
                    this.enhancedSignaturePad._isEmpty = false;
                    this.acceptButton.disabled = false;
                    
                    // Ocultar guía si hay firma
                    const signatureGuide = this.enhancedModal.querySelector('.signature-guide');
                    if (signatureGuide) {
                        signatureGuide.style.opacity = '0';
                    }
                    
                    // Para iOS, forzar repintado
                    if (config.device.isIOS) {
                        // Pequeña alteración del canvas para forzar repintar
                        setTimeout(() => {
                            ctx.fillStyle = "rgba(255,255,255,0.01)";
                            ctx.fillRect(0, 0, 1, 1);
                        }, 50);
                    }
                } catch (err) {
                    console.error('Error al restaurar firma:', err);
                }
            };
            
            image.src = signatureData;
        } catch (err) {
            console.error('Error general al restaurar firma:', err);
        }
    }
    
    /**
     * Actualiza el canvas principal con la firma del modal mejorado
     */
    /**
 * Actualiza el canvas principal con la firma del modal mejorado - con firma más grande
 */
updateMainCanvas() {
    if (!this.signatureData || !this.originalMainCanvas) return;
    
    try {
        // Limpiar canvas principal
        if (window.signaturePad) {
            window.signaturePad.clear();
        }
        
        const image = new Image();
        image.onload = () => {
            try {
                const ctx = this.originalMainCanvas.getContext('2d');
                if (!ctx) return;
                
                const canvasWidth = this.originalMainCanvas.width / (window.devicePixelRatio || 1);
                const canvasHeight = this.originalMainCanvas.height / (window.devicePixelRatio || 1);
                
                ctx.fillStyle = "#ffffff";
                ctx.fillRect(0, 0, canvasWidth, canvasHeight);
                
                // Usar factor de escala más grande (1.5 en vez de 0.9) para agrandar la firma
                // y ajustar la relación de aspecto para favorecer el ancho
                let ratio;
                if (image.width > image.height) {
                    // Si la firma es más ancha que alta, priorizar el ancho
                    ratio = (canvasWidth * 1.2) / image.width;
                } else {
                    // Si la firma es más alta que ancha, asegurar que no sea demasiado pequeña
                    ratio = Math.max(
                        (canvasWidth * 0.95) / image.width,
                        (canvasHeight * 0.7) / image.height
                    );
                }
                
                const newWidth = image.width * ratio;
                const newHeight = image.height * ratio;
                
                // Centrar la firma, pero posicionarla ligeramente más alta para mejor apariencia
                const x = (canvasWidth - newWidth) / 2;
                const y = (canvasHeight - newHeight) / 2 - (canvasHeight * 0.05);
                
                // Mejorar calidad
                ctx.imageSmoothingEnabled = true;
                ctx.imageSmoothingQuality = 'high';
                ctx.drawImage(image, x, y, newWidth, newHeight);
                
                if (window.signaturePad) {
                    window.signaturePad._isEmpty = false;
                }
            } catch (err) {
                console.error('Error al actualizar canvas principal:', err);
            }
        };
        
        image.src = this.signatureData;
    } catch (err) {
        console.error('Error general al actualizar canvas principal:', err);
    }
}
    
    /**
     * Mejora el botón de zoom
     */
    enhanceZoomButton() {
        if (!this.originalZoomButton) return;
        
        // Aplicar estilos mejorados
        Object.assign(this.originalZoomButton.style, {
            padding: '15px 20px',
            fontSize: '16px',
            backgroundColor: config.colors.primary,
            color: 'white',
            width: '100%',
            borderRadius: '30px',
            border: 'none',
            boxShadow: '0 4px 8px rgba(0,0,0,0.2)',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            cursor: 'pointer',
            transition: 'all 0.2s ease',
            marginTop: '15px'
        });
        
        // Actualizar texto
        this.originalZoomButton.innerHTML = '<i class="fas fa-signature" style="margin-right: 8px;"></i> Firmar en pantalla completa';
        
        // Agregar efectos táctiles
        this.originalZoomButton.addEventListener('touchstart', function() {
            this.style.transform = 'scale(0.98)';
            this.style.boxShadow = '0 2px 4px rgba(0,0,0,0.2)';
        });
        
        this.originalZoomButton.addEventListener('touchend', function() {
            this.style.transform = 'scale(1)';
            this.style.boxShadow = '0 4px 8px rgba(0,0,0,0.2)';
        });
        
        // Reemplazar acción de clic
        const originalAction = this.originalZoomButton.onclick;
        this.originalZoomButton.onclick = (e) => {
            e.preventDefault();
            this.openEnhancedModal();
        };
    }
    
    /**
     * Configura el manejo de orientación - manteniendo siempre modo vertical
     */
    setupOrientationHandling() {
        // Manejar cambios de orientación
        window.addEventListener('orientationchange', () => {
            // Marcar que estamos manejando un cambio de orientación
            this.orientationChangeHandled = true;
            
            // Esperar a que se complete el cambio
            setTimeout(() => {
                // Actualizar si el modal está abierto
                if (this.isEnhancedModalOpen) {
                    this.resizeEnhancedCanvas();
                    
                    // Forzar redimensionamiento múltiple para asegurar que se aplica correctamente
                    setTimeout(() => {
                        this.resizeEnhancedCanvas();
                        
                        // Forzar restauración de firma si hay alguna
                        if (this.signatureData || window.mainSignatureData) {
                            this.restoreSignatureToEnhancedCanvas();
                        }
                    }, 300);
                }
                
                // Desmarcar manejo
                setTimeout(() => {
                    this.orientationChangeHandled = false;
                }, 500);
            }, 300);
        });
        
        // También manejar cambios de tamaño
        window.addEventListener('resize', () => {
            // No hacer nada si estamos manejando orientación
            if (this.orientationChangeHandled) return;
            
            // Usar debounce para evitar demasiadas actualizaciones
            clearTimeout(this.resizeTimeout);
            this.resizeTimeout = setTimeout(() => {
                // Comprobar si la orientación cambió
                const isLandscape = window.innerWidth > window.innerHeight;
                const orientationChanged = 
                    (isLandscape && this.lastOrientation === 'portrait') || 
                    (!isLandscape && this.lastOrientation === 'landscape');
                
                this.lastOrientation = isLandscape ? 'landscape' : 'portrait';
                
                // Actualizar si el modal está abierto
                if (this.isEnhancedModalOpen) {
                    this.resizeEnhancedCanvas();
                    
                    // Ejecutar una segunda vez después de un breve retraso para asegurar
                    // que se aplica correctamente después de la rotación
                    if (orientationChanged) {
                        setTimeout(() => {
                            this.resizeEnhancedCanvas();
                            
                            // Restaurar firma si existe
                            if (this.signatureData || window.mainSignatureData) {
                                this.restoreSignatureToEnhancedCanvas();
                            }
                        }, 200);
                    }
                }
            }, 100);
        });
    }
    
    /**
     * Actualiza el indicador de orientación (ya no se usa, siempre vertical)
     */
    updateOrientationIndicator() {
        // Ya no necesitamos mostrar el indicador de orientación
        // porque siempre mantenemos el modo vertical
        return;
    }
    
    /**
     * Mejora la restauración de firma durante desplazamiento
     */
    enhanceSignatureRestoration() {
        if (typeof window.restoreSignature !== 'function') return;
        
        // Para iOS - establecer un intervalo
        if (config.device.isIOS) {
            setInterval(() => {
                if (window.mainSignatureData && 
                    document.getElementById('signature-pad') && 
                    window.signaturePad) {
                    
                    const canvas = document.getElementById('signature-pad');
                    const rect = canvas.getBoundingClientRect();
                    const isVisible = rect.top < window.innerHeight && rect.bottom > 0;
                    
                    if (isVisible) {
                        try {
                            window.restoreSignature(canvas, window.signaturePad);
                        } catch (e) {}
                    }
                }
            }, 250);
        }
        
        // Mejorar manejo de desplazamiento
        let lastScrollTop = 0;
        
        const handleScroll = () => {
            const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            const scrollingUp = scrollTop < lastScrollTop;
            lastScrollTop = scrollTop;
            
            if (window.mainSignatureData && 
                document.getElementById('signature-pad') && 
                window.signaturePad) {
                
                const canvas = document.getElementById('signature-pad');
                const rect = canvas.getBoundingClientRect();
                const isVisible = rect.top < window.innerHeight && rect.bottom > 0;
                
                if (isVisible) {
                    try {
                        window.restoreSignature(canvas, window.signaturePad);
                        
                        // Para iOS, aplicar múltiples restauraciones
                        if (config.device.isIOS && scrollingUp) {
                            [50, 150, 300].forEach(delay => {
                                setTimeout(() => {
                                    if (document.getElementById('signature-pad')) {
                                        window.restoreSignature(canvas, window.signaturePad);
                                    }
                                }, delay);
                            });
                        }
                    } catch (e) {}
                }
            }
        };
        
        // Usar passive: true para mejorar rendimiento
        window.addEventListener('scroll', handleScroll, { passive: true });
    }
}
    
    // Inicializar cuando el DOM esté listo
    function initEnhancedSignature() {
        // Solo para dispositivos móviles
        if (!config.device.isMobile) return;
        
        // Crear e inicializar experiencia mejorada
        const enhancedSignature = new EnhancedSignatureExperience();
        enhancedSignature.initialize();
    }
    
    // Ejecutar cuando el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initEnhancedSignature);
    } else {
        initEnhancedSignature();
    }
    
    // También ejecutar después de carga completa
    window.addEventListener('load', initEnhancedSignature);
})();

    document.addEventListener('DOMContentLoaded', function() {
        let stripe, elements, clientSecret, autosaveTimer, signaturePad, modalSignaturePad;
        let mainSignatureData = null;
        let completedPages = [];
        let selectedTramiteType = '';
        let combinedFees = 88.50, baseIVA = 11.49, totalPrice = 99.99;
        let currentPrice = totalPrice, discountApplied = 0, discountAmount = 0, lastValidCoupon = '';
        
        const finalAmountSpan = document.getElementById('final-amount');
        if(finalAmountSpan) finalAmountSpan.textContent = totalPrice.toFixed(2) + ' €';
        
       function initializeSignature() {
    const signatureCanvas = document.getElementById('signature-pad');
    
    if (!signatureCanvas) {
        console.error('No se encontró el elemento canvas necesario para la firma');
        return;
    }
    
    mainSignatureData = null;
    
    setupCanvas(signatureCanvas);
    detectDevice();
    
    // Configuración para el pad de firma
    const signatureOptions = {
        minWidth: 1,
        maxWidth: 2.5,
        penColor: "rgb(0, 0, 0)",
        backgroundColor: "rgb(255, 255, 255)",
        throttle: 16,
        velocityFilterWeight: 0.6,
        dotSize: 2.5,
        immediateUpdate: true
    };
    
    try {
        signaturePad = new SignaturePad(signatureCanvas, signatureOptions);
    } catch (e) {
        console.error('Error initializing signature pad:', e);
    }
    
    function saveSignatureData(immediate = false) {
        if (signaturePad && !signaturePad.isEmpty()) {
            try {
                mainSignatureData = signaturePad.toDataURL();
                const signatureDataField = document.getElementById('signature_data');
                if (signatureDataField) signatureDataField.value = mainSignatureData;
                
                try {
                    localStorage.setItem('matriculacion_signature', mainSignatureData);
                    localStorage.setItem('matriculacion_signature_canvas_width', signatureCanvas.width);
                    localStorage.setItem('matriculacion_signature_canvas_height', signatureCanvas.height);
                } catch (e) {
                    console.warn('Could not save signature to localStorage:', e);
                }
                
                updateSignatureStatus();
                
                if (immediate && /Android|webOS|iPhone|iPad|iPod/i.test(navigator.userAgent)) {
                    setTimeout(() => restoreSignature(signatureCanvas, signaturePad), 0);
                }
            } catch (err) {
                console.error('Error saving signature data:', err);
            }
        }
    }
    
    // Guardar firma durante y al finalizar el trazo
    if (signatureCanvas) {
        ['pointerup', 'mouseup', 'touchend'].forEach(event => {
            signatureCanvas.addEventListener(event, () => saveSignatureData(true), { passive: true });
        });
        
        ['pointermove', 'mousemove', 'touchmove'].forEach(event => {
            signatureCanvas.addEventListener(event, debounce(() => saveSignatureData(false), 300), { passive: true });
        });
    }
    
    const clearButton = document.getElementById('clear-signature');
    if (clearButton) {
        clearButton.addEventListener('click', function(e) {
            e.preventDefault();
            if (signaturePad) {
                signaturePad.clear();
                mainSignatureData = null;
                
                const signatureDataField = document.getElementById('signature_data');
                if (signatureDataField) signatureDataField.value = '';
                
                try {
                    localStorage.removeItem('matriculacion_signature');
                } catch (e) {}
                
                updateSignatureStatus();
            }
        });
    }
    
    // El modal viejo ya no es necesario, solo usamos el mejorado a través de EnhancedSignatureExperience
    
    // Manejar cambios de visibilidad para restaurar la firma
    document.addEventListener('visibilitychange', function() {
        if (document.visibilityState === 'visible' && mainSignatureData) {
            setTimeout(() => {
                try {
                    restoreSignature(signatureCanvas, signaturePad);
                } catch (err) {}
            }, 200);
        }
    });

    // Manejo mejorado del scroll con detección de dirección
    let scrollTimeout;
    let lastScrollTop = 0;

    window.addEventListener('scroll', function() {
        const currentScrollTop = window.scrollY || document.documentElement.scrollTop;
        const isScrollingUp = currentScrollTop < lastScrollTop;
        lastScrollTop = currentScrollTop;
        
        // Para iPhone/iPad
        if (/iPhone|iPad|iPod/i.test(navigator.userAgent) && mainSignatureData) {
            // Forzar restauración múltiple cuando se desplaza hacia arriba
            if (isScrollingUp) {
                for (let i = 0; i < 3; i++) {
                    setTimeout(function() {
                        try {
                            if (document.getElementById('signature-pad') && signaturePad) {
                                restoreSignature(document.getElementById('signature-pad'), signaturePad);
                            }
                        } catch (err) {}
                    }, i * 50);
                }
            } else {
                // Restauración normal para desplazamiento hacia abajo
                try {
                    restoreSignature(signatureCanvas, signaturePad);
                } catch (err) {}
            }
        } 
        // Para otros dispositivos
        else if (/Android|webOS/i.test(navigator.userAgent) && mainSignatureData) {
            try {
                restoreSignature(signatureCanvas, signaturePad);
            } catch (err) {}
        }
        
        // Restauración al finalizar el scroll (para todos)
        clearTimeout(scrollTimeout);
        scrollTimeout = setTimeout(function() {
            if (mainSignatureData) {
                try {
                    restoreSignature(signatureCanvas, signaturePad);
                    // Restauración adicional para iOS
                    if (/iPhone|iPad|iPod/i.test(navigator.userAgent)) {
                        setTimeout(function() {
                            restoreSignature(signatureCanvas, signaturePad);
                        }, 100);
                    }
                } catch (err) {}
            }
        }, 50);
    }, {passive: true});
    
    // Manejar cambios de tamaño de ventana
    window.addEventListener('resize', function() {
        if (signaturePad && !signaturePad.isEmpty()) {
            try {
                mainSignatureData = signaturePad.toDataURL();
            } catch (err) {}
        }
        
        detectDevice();
        try {
            resizeCanvas(signatureCanvas);
        } catch (err) {}
        
        if (mainSignatureData) {
            setTimeout(() => {
                try {
                    restoreSignature(signatureCanvas, signaturePad);
                } catch (err) {}
            }, 300);
        }
    });
    
    // Manejar cambios de orientación en dispositivos móviles
    window.addEventListener('orientationchange', function() {
        if (signaturePad && !signaturePad.isEmpty()) {
            try {
                mainSignatureData = signaturePad.toDataURL();
            } catch (err) {}
        }
        
        setTimeout(function() {
            try {
                resizeCanvas(signatureCanvas);
                if (mainSignatureData) {
                    setTimeout(() => {
                        restoreSignature(signatureCanvas, signaturePad);
                    }, 300);
                }
            } catch (err) {}
        }, 500);
    });
    
    // Soporte específico para iOS
    if (/iPad|iPhone|iPod/.test(navigator.userAgent)) {
        document.addEventListener('focusin', function() {
            setTimeout(function() {
                if (mainSignatureData) {
                    try {
                        restoreSignature(signatureCanvas, signaturePad);
                    } catch (err) {}
                }
            }, 400);
        });
        
        document.addEventListener('focusout', function() {
            setTimeout(function() {
                if (mainSignatureData) {
                    try {
                        restoreSignature(signatureCanvas, signaturePad);
                    } catch (err) {}
                }
            }, 400);
        });
    }
    
    // Restaurar firma desde localStorage
    try {
        const savedSignature = localStorage.getItem('matriculacion_signature');
        if (savedSignature) {
            mainSignatureData = savedSignature;
            setTimeout(() => {
                restoreSignature(signatureCanvas, signaturePad);
            }, 200);
        }
    } catch (err) {}
    
    updateSignatureStatus();
}

        function setupCanvas(canvas, isModal = false) {
            if (!canvas) return;
            
            try {
                const ctx = canvas.getContext('2d');
                if (!ctx) return;
                
                ctx.fillStyle = "#ffffff";
                ctx.fillRect(0, 0, canvas.width, canvas.height);
                
                // Propiedades críticas para prevenir problemas táctiles
                canvas.style.touchAction = 'none';
                canvas.style.msTouchAction = 'none';
                canvas.style.userSelect = 'none';
                canvas.style.webkitUserSelect = 'none';
                canvas.style.mozUserSelect = 'none';
                canvas.style.msUserSelect = 'none';
                
                // Aceleración por hardware para mejor rendimiento
                canvas.style.transform = 'translateZ(0)';
                canvas.style.webkitTransform = 'translateZ(0)';
                canvas.style.willChange = 'transform';
                
                // Lista completa de eventos a prevenir
                const events = [
                    'touchstart', 'touchmove', 'touchend', 'touchcancel', 
                    'gesturestart', 'gesturechange', 'gestureend'
                ];
                
                function preventDefaultBehavior(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    return false;
                }
                
                events.forEach(event => {
                    canvas.addEventListener(event, preventDefaultBehavior, { passive: false });
                });
                
                canvas.addEventListener('mousedown', function(e) {
                    e.stopPropagation();
                });
                
                // Bloquear el desplazamiento durante la firma en móviles
                canvas.addEventListener('touchstart', function(event) {
                    const scrollPos = {
                        x: window.scrollX || window.pageXOffset,
                        y: window.scrollY || window.pageYOffset
                    };
                    
                    function maintainScrollPosition() {
                        window.scrollTo(scrollPos.x, scrollPos.y);
                    }
                    
                    const scrollInterval = setInterval(maintainScrollPosition, 5);
                    
                    function onTouchEnd() {
                        clearInterval(scrollInterval);
                        canvas.removeEventListener('touchend', onTouchEnd);
                        canvas.removeEventListener('touchcancel', onTouchEnd);
                    }
                    
                    canvas.addEventListener('touchend', onTouchEnd, { once: true });
                    canvas.addEventListener('touchcancel', onTouchEnd, { once: true });
                }, { passive: false });
            } catch (err) {}
        }

        function resizeCanvas(canvas, isModal = false) {
            if (!canvas) return;
            
            try {
                const ratio = window.devicePixelRatio || 1;
                const container = canvas.parentElement;
                
                if (!container) return;
                
                const containerWidth = container.clientWidth;
                
                let height;
                if (isModal) {
                    height = Math.min(window.innerHeight * 0.5, containerWidth * 0.7);
                    if (window.innerWidth <= 480) {
                        height = Math.min(window.innerHeight * 0.4, containerWidth * 0.8);
                    }
                } else {
                    if (window.innerWidth <= 480) {
                        height = 160;
                    } else if (window.innerWidth <= 768) {
                        height = 180;
                    } else {
                        height = 200;
                    }
                }
                
                // Establecer dimensiones físicas considerando DPI
                canvas.width = containerWidth * ratio;
                canvas.height = height * ratio;
                
                // Establecer dimensiones visuales via CSS
                canvas.style.width = containerWidth + 'px';
                canvas.style.height = height + 'px';
                
                // Escalar el contexto para DPI
                const context = canvas.getContext('2d');
                if (context) {
                    context.scale(ratio, ratio);
                    context.fillStyle = "#ffffff";
                    context.fillRect(0, 0, containerWidth, height);
                }
                
                return { width: containerWidth, height: height, ratio: ratio };
            } catch (err) {}
        }

        // Utilidad de debounce para evitar llamadas excesivas
        function debounce(func, wait) {
            let timeout;
            return function() {
                const context = this, args = arguments;
                clearTimeout(timeout);
                timeout = setTimeout(() => func.apply(context, args), wait);
            };
        }

       function restoreSignature(targetCanvas, targetPad) {
    if (!window.mainSignatureData || !targetCanvas || !targetPad) return false;
    
    try {
        targetPad.clear();
        
        const image = new Image();
        
        image.onload = function() {
            try {
                const context = targetCanvas.getContext('2d');
                if (!context) return false;
                
                context.fillStyle = "#ffffff";
                context.fillRect(0, 0, targetCanvas.width, targetCanvas.height);
                
                // Usar mejor cálculo de ratio para mantener siempre en vertical
                const dpr = window.devicePixelRatio || 1;
                const canvasWidth = targetCanvas.width / dpr;
                const canvasHeight = targetCanvas.height / dpr;
                
                // Usar un factor de escala más grande para una firma más prominente
                let ratio;
                if (image.width > image.height) {
                    // Si la firma es más ancha que alta
                    ratio = (canvasWidth * 1.2) / image.width;
                } else {
                    // Si la firma es más alta que ancha
                    ratio = Math.max(
                        (canvasWidth * 0.95) / image.width,
                        (canvasHeight * 0.7) / image.height
                    );
                }
                
                const newWidth = image.width * ratio;
                const newHeight = image.height * ratio;
                
                // Centrar y elevar ligeramente la firma
                const x = (canvasWidth - newWidth) / 2;
                const y = (canvasHeight - newHeight) / 2 - (canvasHeight * 0.05);
                
                // Dibujar con anti-aliasing para mejorar calidad
                context.imageSmoothingEnabled = true;
                context.imageSmoothingQuality = 'high';
                context.drawImage(image, x, y, newWidth, newHeight);
                
                targetPad._isEmpty = false;
                
                // Mejoras específicas para iOS
                if (/iPhone|iPad|iPod/i.test(navigator.userAgent)) {
                    // Forzar repintar para iOS
                    requestAnimationFrame(() => {
                        try {
                            // Pequeña alteración del canvas para forzar repintar
                            context.fillStyle = "rgba(255,255,255,0.01)";
                            context.fillRect(0, 0, 1, 1);
                        } catch (e) {}
                    });
                }
                
                window.updateSignatureStatus && window.updateSignatureStatus();
                
                return true;
            } catch (err) {
                console.error('Error al restaurar la firma:', err);
                return false;
            }
        };
        
        image.onerror = function() {
            console.error('Error al cargar la imagen de firma');
            return false;
        };
        
        image.src = window.mainSignatureData;
        return true;
    } catch (err) {
        console.error('Error general al restaurar firma:', err);
        return false;
    }
}

        function copySignatureToCanvas(signatureData, targetCanvas, targetPad) {
            if (!signatureData || !targetCanvas || !targetPad) return false;
            
            try {
                const image = new Image();
                image.onload = function() {
                    try {
                        const ctx = targetCanvas.getContext('2d');
                        if (!ctx) return false;
                        
                        const canvasWidth = targetCanvas.width / (window.devicePixelRatio || 1);
                        const canvasHeight = targetCanvas.height / (window.devicePixelRatio || 1);
                        
                        ctx.fillStyle = "#ffffff";
                        ctx.fillRect(0, 0, canvasWidth, canvasHeight);
                        
                        const ratio = Math.min(
                            canvasWidth / image.width,
                            canvasHeight / image.height
                        ) * 0.9;
                        
                        const newWidth = image.width * ratio;
                        const newHeight = image.height * ratio;
                        
                        const x = (canvasWidth - newWidth) / 2;
                        const y = (canvasHeight - newHeight) / 2;
                        
                        ctx.drawImage(image, x, y, newWidth, newHeight);
                        
                        targetPad._isEmpty = false;
                        
                        return true;
                    } catch (err) {
                        return false;
                    }
                };
                
                image.onerror = function() {
                    return false;
                };
                
                image.src = signatureData;
                return true;
            } catch (err) {
                return false;
            }
        }

        function closeSignatureModal() {
            try {
                const modal = document.getElementById('signature-modal');
                if (modal) {
                    modal.style.display = 'none';
                    document.body.style.overflow = '';
                }
            } catch (err) {}
        }

        function updateSignatureStatus() {
            try {
                const statusElement = document.getElementById('signature-status');
                const container = document.querySelector('.signature-pad-container');
                const signatureDataField = document.getElementById('signature_data');
                
                if (!statusElement || !container || !signatureDataField) return;
                
                let isSigned = false;
                
                if (mainSignatureData) {
                    isSigned = true;
                } else if (signaturePad && !signaturePad.isEmpty()) {
                    isSigned = true;
                    try {
                        mainSignatureData = signaturePad.toDataURL();
                        try {
                            localStorage.setItem('matriculacion_signature', mainSignatureData);
                        } catch (e) {}
                    } catch (err) {}
                }
                
                statusElement.textContent = isSigned ? "Firmado" : "Sin firmar";
                statusElement.className = "signature-status " + (isSigned ? "signed" : "empty");
                
                container.style.borderColor = isSigned ? "#28a745" : "#e0e0e0";
                container.style.boxShadow = isSigned ? "0 0 5px rgba(40, 167, 69, 0.3)" : "none";
                
                signatureDataField.value = isSigned ? mainSignatureData : "";
                
                if (isSigned && signaturePad && signaturePad.isEmpty()) {
                    const signatureCanvas = document.getElementById('signature-pad');
                    if (signatureCanvas) {
                        setTimeout(() => {
                            restoreSignature(signatureCanvas, signaturePad);
                        }, 50);
                    }
                }
            } catch (err) {}
        }
        
        function startAutosave() {
            if (autosaveTimer) clearInterval(autosaveTimer);
            autosaveTimer = setInterval(function() {
                saveProgress();
                showNotification('Progreso guardado automáticamente', 'info');
            }, 120000);
        }
        
        startAutosave();

      async function initializeStripe(customAmount = null) {
    const amountToCharge = (customAmount !== null) ? customAmount : currentPrice;
    const totalAmountCents = Math.round(amountToCharge * 100);
    // Recopilar metadatos
    const metadata = {
        tramite_id: '<?php echo esc_attr($tramite_id); ?>',
        tipo_tramite: selectedTramiteType,
        marca: document.getElementById('marca')?.value || '',
        modelo: document.getElementById('modelo')?.value || '',
        coupon: lastValidCoupon
    };
    stripe = Stripe('<?php echo ($is_test_mode ? $publishable_key_test : $publishable_key_live); ?>');

    try {
        // Crear la cadena de parámetros para la solicitud
        let params = `action=create_payment_intent_matriculacion&amount=${totalAmountCents}`;
        
        // Añadir metadatos a la solicitud
        if (metadata.tramite_id) params += `&tramite_id=${encodeURIComponent(metadata.tramite_id)}`;
        if (metadata.tipo_tramite) params += `&tipo_tramite=${encodeURIComponent(metadata.tipo_tramite)}`;
        if (metadata.marca) params += `&marca=${encodeURIComponent(metadata.marca)}`;
        if (metadata.modelo) params += `&modelo=${encodeURIComponent(metadata.modelo)}`;
        if (metadata.coupon) params += `&coupon=${encodeURIComponent(metadata.coupon)}`;
        
        const response = await fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: params
        });
        
        if (!response.ok) {
            throw new Error('Error en la conexión con el servidor');
        }
        
        const result = await response.json();

        if (result.error) throw new Error(result.error);

        clientSecret = result.clientSecret;

        const appearance = {
            theme: 'flat',
            variables: {
                colorPrimary: '#016d86',
                colorBackground: '#ffffff',
                colorText: '#333333',
                colorDanger: '#dc3545',
                fontFamily: 'Arial, sans-serif',
                spacingUnit: '4px',
                borderRadius: '4px',
            },
            rules: {
                '.Label': {
                    color: '#555555',
                    fontSize: '14px',
                    marginBottom: '4px',
                },
                '.Input': {
                    padding: '12px',
                    border: '1px solid #cccccc',
                    borderRadius: '4px',
                },
                '.Input:focus': {
                    borderColor: '#016d86',
                },
                '.Input--invalid': {
                    borderColor: '#dc3545',
                },
            }
        };

        if (elements) {
            elements.clear();
            elements = null;
            document.getElementById('payment-element').innerHTML = '';
        }
        
        // Create payment elements with explicit payment method configuration
        elements = stripe.elements({ 
            appearance, 
            clientSecret,
            loader: 'auto' 
        });
        
        // Create payment element with card explicitly set as the primary payment method
        const paymentElement = elements.create('payment', { 
            paymentMethodOrder: ['card'],
            defaultValues: {
                billingDetails: {
                    name: document.getElementById('propietario_nombre')?.value || '',
                    email: document.getElementById('propietario_email')?.value || '',
                    phone: document.getElementById('propietario_movil')?.value || '',
                    address: {
                        country: 'ES',
                    }
                }
            }
        });
        
        paymentElement.on('ready', () => {
            console.log('Payment element is ready');
            document.getElementById('loading-overlay').style.display = 'none';
        });
        
        paymentElement.on('change', (event) => {
            // Update button state based on completion status
            const submitButton = document.getElementById('submit');
            if (event.complete) {
                submitButton.disabled = !document.querySelector('input[name="terms_accept_pago"]').checked;
            } else {
                submitButton.disabled = true;
            }
        });
        
        paymentElement.mount('#payment-element');
        
        return { success: true };
    } catch (error) {
        console.error('Error initializing Stripe:', error);
        document.getElementById('payment-message').textContent = 'Error al inicializar el pago: ' + error.message;
        document.getElementById('payment-message').className = 'status-message error';
        document.getElementById('payment-message').style.display = 'block';
        document.getElementById('loading-overlay').style.display = 'none';
        return { success: false, error: error.message };
    }
}

        const formPages = document.querySelectorAll('.form-page');
        const navLinks = document.querySelectorAll('.nav-link');
        let currentPage = 0;

        function updateForm() {
            formPages.forEach((page, index) => {
                page.classList.toggle('hidden', index !== currentPage);
            });
            
            navLinks.forEach((link, index) => {
                link.classList.remove('active', 'completed', 'available', 'locked');
                
                if (index === currentPage) {
                    link.classList.add('active');
                } else if (completedPages.includes(index) || index < currentPage) {
                    link.classList.add('completed');
                }
                
                link.classList.add('available');
            });
            
            document.getElementById('form-progress-bar').style.width = ((currentPage + 1) / formPages.length) * 100 + '%';
            
            if (currentPage === 0) {
                document.getElementById('prevButtonMain').style.display = 'none';
            } else {
                document.getElementById('prevButtonMain').style.display = 'block';
            }

            if (currentPage === 3) {
                document.getElementById('main-button-container').style.display = 'none';
            } else {
                document.getElementById('main-button-container').style.display = 'flex';
            }

            if (formPages[currentPage].id === 'page-payment' && !stripe) {
                updatePaymentSummary();
                
                initializeStripe().catch(error => {
                    showNotification('Error al inicializar el pago: ' + error.message, 'error');
                });
                
                document.getElementById('apply-coupon').addEventListener('click', function() {
                    const couponCode = document.getElementById('coupon_code').value.trim();
                    if (couponCode) validateCouponCode(couponCode);
                });
                
                document.getElementById('coupon_code').addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        document.getElementById('apply-coupon').click();
                    }
                });
                
                document.getElementById('submit').addEventListener('click', processPayment);
                
                document.querySelector('input[name="terms_accept_pago"]').addEventListener('change', function() {
                    document.getElementById('submit').disabled = !this.checked;
                });
            }
            
            updateHelpContent();
            saveProgress();
            applyPageTransitionEffect();
        }
        
        function updatePaymentSummary() {
            document.getElementById('payment-tramite-type').textContent = 
                selectedTramiteType === 'abanderamiento' ? 'Matriculación/Abanderamiento' : 'Inscripción';
            
            const marca = document.getElementById('marca')?.value || '';
            const modelo = document.getElementById('modelo')?.value || '';
            if (marca || modelo) {
                document.getElementById('payment-boat-info').textContent = marca + ' ' + modelo;
            }
            
            const propietario = document.getElementById('propietario_nombre')?.value || '';
            const nif = document.getElementById('propietario_nif')?.value || '';
            if (propietario) {
                document.getElementById('payment-owner-info').textContent = propietario + (nif ? ' - ' + nif : '');
            }
        }
        
        function applyPageTransitionEffect() {
            const currentFormPage = formPages[currentPage];
            
            currentFormPage.style.opacity = '0';
            currentFormPage.style.transform = 'translateY(20px)';
            
            setTimeout(() => {
                currentFormPage.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                currentFormPage.style.opacity = '1';
                currentFormPage.style.transform = 'translateY(0)';
                
                setTimeout(() => {
                    currentFormPage.style.transition = '';
                }, 500);
            }, 50);
        }
        
        async function processPayment(e) {
            
    e.preventDefault();
    
    if (!signaturePad || signaturePad.isEmpty()) {
        showNotification('Por favor, firme antes de realizar el pago', 'error');
        
        currentPage = 1;
        updateForm();
        
        const signatureBox = document.querySelector('.signature-box');
        if (signatureBox) {
            setTimeout(() => {
                signatureBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
                signatureBox.style.boxShadow = '0 0 0 2px #dc3545, 0 0 15px rgba(220, 53, 69, 0.5)';
                
                setTimeout(() => {
                    signatureBox.style.boxShadow = '';
                }, 2000);
            }, 300);
        }
        
        return false;
    }
    
    if (!document.querySelector('input[name="terms_accept_pago"]').checked) {
        showNotification('Debe aceptar los términos de pago para continuar', 'error');
        return false;
    }
    
    const submitButton = document.getElementById('submit');
    submitButton.disabled = true;
    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
    document.getElementById('loading-overlay').style.display = 'flex';
    
    const paymentMessage = document.getElementById('payment-message');
    paymentMessage.classList.remove('success', 'error');
    paymentMessage.classList.add('hidden');

    try {
        // Verify that Stripe has been properly initialized
        if (!stripe || !elements) {
            throw new Error('El sistema de pago no se ha inicializado correctamente. Por favor, recargue la página e intente nuevamente.');
        }
        
        // Validate that billing details are complete
        const propietarioNombre = document.getElementById('propietario_nombre').value;
        const propietarioEmail = document.getElementById('propietario_email').value;
        
        if (!propietarioNombre || !propietarioEmail) {
            throw new Error('Faltan datos del propietario. Verifique que ha completado todos los campos obligatorios.');
        }
        
        // Show message during processing
        paymentMessage.textContent = 'Procesando el pago, por favor espere...';
        paymentMessage.className = 'status-message info';
        paymentMessage.style.display = 'block';

        // Complete the billing details for the payment
        const billingDetails = {
            name: propietarioNombre,
            email: propietarioEmail,
            phone: document.getElementById('propietario_movil').value || '',
            address: {
                city: document.getElementById('propietario_localidad').value || '',
                country: 'ES',
                line1: (document.getElementById('propietario_via').value || '') + ' ' + (document.getElementById('propietario_numero').value || ''),
                postal_code: document.getElementById('propietario_cp').value || '',
                state: document.getElementById('propietario_provincia').value || ''
            }
        };
        
        // Setup metadata for the payment
        const metadata = {
            tramite_id: '<?php echo esc_attr($tramite_id); ?>',
            tipo_tramite: selectedTramiteType,
            marca_embarcacion: document.getElementById('marca')?.value || '',
            modelo_embarcacion: document.getElementById('modelo')?.value || '',
            coupon_applied: lastValidCoupon
        };
        // Guardar datos del trámite para usar después de la redirección
const tramiteData = {
    tramite_id: '<?php echo esc_attr($tramite_id); ?>',
    email: billingDetails.email,
    nombre: billingDetails.name,
    tipo_tramite: selectedTramiteType,
    marca: document.getElementById('marca')?.value || '',
    modelo: document.getElementById('modelo')?.value || '',
    precio_final: currentPrice.toFixed(2)
};

// Guardar datos en el servidor para recuperarlos después
fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `action=guardar_datos_tramite_temp&tramite_id=${encodeURIComponent('<?php echo esc_attr($tramite_id); ?>')}&data=${encodeURIComponent(JSON.stringify(tramiteData))}`
}).catch(err => {
    console.error('Error al guardar datos temporales:', err);
});
        
// Confirm the payment with explicit payment method
const { error, paymentIntent } = await stripe.confirmPayment({
    elements,
    confirmParams: {
        return_url: window.location.href + '?payment_success=true&tramite_id=' + encodeURIComponent('<?php echo esc_attr($tramite_id); ?>'),
        receipt_email: billingDetails.email,
        payment_method_data: {
            billing_details: billingDetails
        }
        // Eliminado el parámetro metadata de aquí
    },
    redirect: 'if_required',
});

        if (error) {
            // Common error codes and more user-friendly messages
            const errorMessages = {
                'card_declined': 'Tarjeta rechazada. Por favor, intente con otra tarjeta.',
                'expired_card': 'La tarjeta ha expirado. Por favor, use una tarjeta válida.',
                'incorrect_cvc': 'El código de seguridad (CVC) es incorrecto.',
                'insufficient_funds': 'La tarjeta no tiene fondos suficientes para completar esta transacción.',
                'invalid_expiry_month': 'El mes de expiración es inválido.',
                'invalid_expiry_year': 'El año de expiración es inválido.',
                'invalid_number': 'El número de tarjeta no es válido.'
            };
            
            let errorMsg = errorMessages[error.code] || error.message || 'Error al procesar el pago. Por favor, intente nuevamente.';
            throw new Error(errorMsg);
        } else if (paymentIntent && paymentIntent.status === 'succeeded') {
            paymentMessage.textContent = 'Pago realizado con éxito. Estamos procesando su solicitud.';
            paymentMessage.classList.remove('hidden', 'error', 'info');
            paymentMessage.classList.add('success');
            
            return handleFinalSubmission();
        } else if (paymentIntent && paymentIntent.status === 'processing') {
            paymentMessage.textContent = 'Su pago está siendo procesado. Le notificaremos cuando se complete.';
            paymentMessage.classList.remove('hidden', 'error');
            paymentMessage.classList.add('info');
            
            setTimeout(function() {
                checkPaymentStatus(paymentIntent.id);
            }, 3000);
            return true;
        } else if (paymentIntent && paymentIntent.status === 'requires_action') {
            paymentMessage.textContent = 'Se requiere verificación adicional. Siga las instrucciones para completar el pago.';
            paymentMessage.classList.remove('hidden', 'error');
            paymentMessage.classList.add('info');
            return false;
        } else {
            // Additional manual verification
            return handlePaymentVerification();
        }
    } catch (error) {
        console.error('Error processing payment:', error);
        
        paymentMessage.textContent = 'Error al procesar el pago: ' + 
            (error.message || 'Por favor, verifique sus datos e intente nuevamente.');
        paymentMessage.classList.remove('hidden', 'success', 'info');
        paymentMessage.classList.add('error');
        
        // Show additional notification for better error visibility
        showNotification('Error de pago: ' + (error.message || 'Verifique sus datos'), 'error');
    } finally {
        submitButton.disabled = false;
        submitButton.innerHTML = '<i class="fas fa-lock"></i> Pagar ahora';
 // Agregar un retraso de 3 segundos antes de ocultar el overlay
    setTimeout(function() {
        document.getElementById('loading-overlay').style.display = 'none';
    }, 1000); // 3000 milisegundos = 3 segundos
}
    return false;
}

async function checkPaymentStatus(paymentIntentId) {
    try {
        const response = await fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=check_payment_intent_status&payment_intent_id=${paymentIntentId}`
        });
        
        if (!response.ok) {
            throw new Error('Error checking payment status');
        }
        
        const result = await response.json();
        
        if (result.success) {
            if (result.status === 'succeeded') {
                const paymentMessage = document.getElementById('payment-message');
                paymentMessage.textContent = 'Pago realizado con éxito. Estamos procesando su solicitud.';
                paymentMessage.classList.remove('hidden', 'error', 'info');
                paymentMessage.classList.add('success');
                
                return handleFinalSubmission();
            } else if (result.status === 'processing') {
                // Check again after delay
                setTimeout(function() {
                    checkPaymentStatus(paymentIntentId);
                }, 3000);
            } else {
                // Status is probably canceled, failed, or requires action
                const paymentMessage = document.getElementById('payment-message');
                paymentMessage.textContent = 'El estado del pago ha cambiado. Por favor, intente nuevamente.';
                paymentMessage.classList.remove('hidden', 'success', 'info');
                paymentMessage.classList.add('error');
            }
        } else {
            throw new Error(result.error || 'Error checking payment status');
        }
    } catch (error) {
        console.error('Error checking payment status:', error);
        const paymentMessage = document.getElementById('payment-message');
        paymentMessage.textContent = 'Error al verificar el estado del pago: ' + error.message;
        paymentMessage.classList.remove('hidden', 'success', 'info');
        paymentMessage.classList.add('error');
    }
}
        
        // Función adicional para verificar el estado del pago si no tenemos confirmación inmediata
        async function handlePaymentVerification() {
            // Implementar verificación adicional si es necesario
            const paymentMessage = document.getElementById('payment-message');
            
            try {
                paymentMessage.textContent = 'Verificando estado del pago...';
                paymentMessage.className = 'status-message info';
                
                // Podríamos implementar una verificación adicional si fuera necesario
                // Por ahora, asumimos que si no hubo error, el pago está en proceso o completado
                
                paymentMessage.textContent = 'Pago recibido. Estamos procesando su solicitud.';
                paymentMessage.classList.remove('info', 'error');
                paymentMessage.classList.add('success');
                
                return handleFinalSubmission();
            } catch (error) {
                console.error('Error verificando el pago:', error);
                paymentMessage.textContent = 'No pudimos verificar el estado de su pago. Por favor, contacte con soporte.';
                paymentMessage.classList.remove('success', 'info');
                paymentMessage.classList.add('error');
                return false;
            }
        }

        async function handleFinalSubmission() {
            if (!signaturePad || signaturePad.isEmpty()) {
                showNotification('Por favor, firme antes de enviar el formulario.', 'error');
                
                currentPage = 1;
                updateForm();
                
                const signatureBox = document.querySelector('.signature-box');
                if (signatureBox) {
                    setTimeout(() => {
                        signatureBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        signatureBox.style.boxShadow = '0 0 0 2px #dc3545, 0 0 15px rgba(220, 53, 69, 0.5)';
                        
                        setTimeout(() => {
                            signatureBox.style.boxShadow = '';
                        }, 2000);
                    }, 300);
                }
                
                document.getElementById('loading-overlay').style.display = 'none';
                return false;
            }

            let formData = new FormData(document.getElementById('matriculacion-form'));
            formData.append('action', 'submit_form_matriculacion');
            formData.append('signature', signaturePad.toDataURL());
            formData.append('coupon_used', lastValidCoupon);
            formData.append('precio_final', currentPrice.toFixed(2));
            
            if (discountApplied > 0) {
                formData.append('descuento_porcentaje', discountApplied);
                formData.append('descuento_monto', discountAmount.toFixed(2));
            }

            try {
                const response = await fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                    method: 'POST',
                    body: formData
                });
                
                if (!response.ok) {
                    throw new Error('Error en la respuesta del servidor');
                }
                
                const data = await response.json();
                
                if (data.success) {
                    document.getElementById('matriculacion-form').innerHTML = `
                        <div class="success-message" style="text-align: center; padding: 30px; background-color: #d4edda; border-radius: 10px; margin: 20px 0;">
                            <i class="fas fa-check-circle" style="font-size: 48px; color: #28a745; margin-bottom: 20px;"></i>
                            <h2 style="color: #155724; margin-bottom: 15px;">¡Trámite completado con éxito!</h2>
                            <p style="font-size: 18px; margin-bottom: 20px;">Su solicitud de matriculación ha sido recibida correctamente.</p>
                            <p>Número de trámite: <strong>${data.tramite_id || '<?php echo esc_attr($tramite_id); ?>'}</strong></p>
                            <p>Se ha enviado un email de confirmación a su dirección de correo electrónico.</p>
                            <p style="margin-top: 30px;">Un gestor se pondrá en contacto con usted en breve para informarle sobre el avance de su trámite.</p>
                            <a href="<?php echo esc_url(home_url()); ?>" class="button button-primary" style="margin-top: 30px; display: inline-block;">
                                <i class="fas fa-home"></i> Volver al inicio
                            </a>
                        </div>
                    `;
                    
                    localStorage.removeItem('matriculacion_form_data');
                    localStorage.removeItem('matriculacion_form_page');
                    localStorage.removeItem('matriculacion_completed_pages');
                    localStorage.removeItem('matriculacion_tramite_type');
                    
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                    
                    return true;
                } else {
                    throw new Error(data.message || 'Error al procesar el formulario');
                }
            } catch (error) {
                console.error('Error en el envío del formulario:', error);
                showNotification('Error al procesar el formulario: ' + error.message, 'error');
                document.getElementById('loading-overlay').style.display = 'none';
                return false;
            }
        }
        
        function showNotification(message, type = 'info') {
            let notification = document.getElementById('form-notification');
            if (!notification) {
                notification = document.createElement('div');
                notification.id = 'form-notification';
                notification.style.position = 'fixed';
                notification.style.top = '20px';
                notification.style.right = '20px';
                notification.style.padding = '15px 20px';
                notification.style.borderRadius = '8px';
                notification.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
                notification.style.zIndex = '9999';
                notification.style.maxWidth = '300px';
                notification.style.transition = 'all 0.3s ease';
                notification.style.opacity = '0';
                notification.style.transform = 'translateY(-20px)';
                document.body.appendChild(notification);
            }
            
            if (type === 'error') {
                notification.style.backgroundColor = '#f8d7da';
                notification.style.color = '#721c24';
                notification.style.borderLeft = '4px solid #dc3545';
            } else if (type === 'success') {
                notification.style.backgroundColor = '#d4edda';
                notification.style.color = '#155724';
                notification.style.borderLeft = '4px solid #28a745';
            } else {
                notification.style.backgroundColor = '#d1ecf1';
                notification.style.color = '#0c5460';
                notification.style.borderLeft = '4px solid #17a2b8';
            }
            
            notification.textContent = message;
            
            setTimeout(() => {
                notification.style.opacity = '1';
                notification.style.transform = 'translateY(0)';
            }, 10);
            
            setTimeout(() => {
                notification.style.opacity = '0';
                notification.style.transform = 'translateY(-20px)';
                
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.parentNode.removeChild(notification);
                    }
                }, 300);
            }, 4000);
        }
        
        function updateHelpContent() {
            const currentSectionHelp = document.getElementById('current-section-help');
            const sectionSpecificHelp = document.getElementById('section-specific-help');
            
            if (document.getElementById('tramite-selector-container').style.display !== 'none') {
                currentSectionHelp.textContent = "Selección de tipo de trámite";
                sectionSpecificHelp.innerHTML = `
                    <p>En esta sección debe:</p>
                    <ul>
                        <li>Seleccionar el tipo de trámite que desea realizar según sus necesidades.</li>
                        <li>Matriculación/Abanderamiento es el procedimiento completo para cualquier embarcación.</li>
                        <li>Inscripción es un procedimiento simplificado para embarcaciones con marcado CE y eslora ≤ 12 metros.</li>
                    </ul>
                    <p>Haga clic en la tarjeta que corresponda a su caso y pulse "Continuar" para seguir con el formulario.</p>
                `;
                return;
            }
            
            if (navLinks[currentPage]) {
                currentSectionHelp.textContent = navLinks[currentPage].textContent;
            }
            
            let helpContent = '';
            switch(formPages[currentPage].id) {
                case 'page-datos-embarcacion':
                    helpContent = `
                        <p>En esta sección debe:</p>
                        <ul>
                            <li>Completar todos los datos técnicos de su embarcación que coincidan con la documentación oficial.</li>
                            <li>Puede desplegar/contraer cada sección haciendo clic en los encabezados.</li>
                            <li>Para embarcaciones auxiliares, solo complete la información si corresponde.</li>
                        </ul>
                    `;
                    break;
                case 'page-datos-propietario':
                    helpContent = `
                        <p>Ingrese los datos del propietario y representante (si procede):</p>
                        <ul>
                            <li>Los datos deben coincidir con la documentación legal.</li>
                            <li>Si actúa como representante, adjunte el documento de autorización en la sección de documentación.</li>
                            <li>Asegúrese de firmar digitalmente en la sección correspondiente.</li>
                        </ul>
                    `;
                    break;
                case 'page-documentacion':
                    helpContent = `
                        <p>Adjunte todos los documentos requeridos:</p>
                        <ul>
                            <li>Los archivos deben estar en formato PDF, JPG o PNG.</li>
                            <li>El tamaño máximo por archivo es de 5MB.</li>
                            <li>Asegúrese de que las imágenes sean legibles y estén completas.</li>
                            <li>Puede ver ejemplos haciendo clic en "Ver ejemplo" junto a cada documento.</li>
                        </ul>
                    `;
                    break;
                case 'page-payment':
                    helpContent = `
                        <p>Revisión y pago:</p>
                        <ul>
                            <li>Verifique que todos los datos ingresados sean correctos.</li>
                            <li>Si tiene un cupón de descuento, ingréselo antes de proceder al pago.</li>
                            <li>La tarifa incluye todas las tasas oficiales y honorarios de gestión.</li>
                            <li>El pago se procesa de forma segura a través de Stripe.</li>
                        </ul>
                    `;
                    break;
            }
            
            sectionSpecificHelp.innerHTML = helpContent;
        }
        
        function saveProgress() {
            const formData = new FormData(document.getElementById('matriculacion-form'));
            const formDataObject = {};
            
            formData.forEach((value, key) => {
                formDataObject[key] = value;
            });
            
            if (mainSignatureData) {
                localStorage.setItem('matriculacion_signature', mainSignatureData);
            } else {
                localStorage.removeItem('matriculacion_signature');
            }
            
            localStorage.setItem('matriculacion_form_data', JSON.stringify(formDataObject));
            localStorage.setItem('matriculacion_form_page', currentPage.toString());
            localStorage.setItem('matriculacion_completed_pages', JSON.stringify(completedPages));
            localStorage.setItem('matriculacion_tramite_type', selectedTramiteType);
        }
        
        function loadProgress() {
            const savedData = localStorage.getItem('matriculacion_form_data');
            const savedPage = localStorage.getItem('matriculacion_form_page');
            const savedCompletedPages = localStorage.getItem('matriculacion_completed_pages');
            const savedTramiteType = localStorage.getItem('matriculacion_tramite_type');
            
            if (savedTramiteType) {
                selectedTramiteType = savedTramiteType;
                document.getElementById('tipo_tramite_hidden').value = selectedTramiteType;
                
                if (selectedTramiteType === 'abanderamiento' || selectedTramiteType === 'inscripcion') {
                    startMainForm(selectedTramiteType);
                }
            }
            
            if (savedData) {
                const formDataObject = JSON.parse(savedData);
                const form = document.getElementById('matriculacion-form');
                
                Object.keys(formDataObject).forEach(key => {
                    const field = form.elements[key];
                    if (field) {
                        if (field.type === 'checkbox' || field.type === 'radio') {
                            field.checked = (formDataObject[key] === 'on' || formDataObject[key] === true);
                        } else {
                            field.value = formDataObject[key];
                        }
                        
                        const event = new Event('change');
                        field.dispatchEvent(event);
                    }
                });
                
                if (savedPage) {
                    currentPage = parseInt(savedPage, 10);
                    if (currentPage >= formPages.length) {
                        currentPage = formPages.length - 1;
                    }
                }
                
                if (savedCompletedPages) {
                    completedPages = JSON.parse(savedCompletedPages);
                }
                
                showNotification('Se ha restaurado su progreso anterior', 'success');
            }
        }

        function handleTramiteTypeChange() {
            const tramiteTitle = selectedTramiteType === 'abanderamiento' ? 
                'Abanderamiento / Matriculación' : 'Inscripción';
            
            document.getElementById('tramite-type-display').textContent = tramiteTitle;
            document.getElementById('tramite-type-display-auth').textContent = tramiteTitle;
            document.getElementById('payment-tramite-type').textContent = tramiteTitle;
            
            document.getElementById('info-abanderamiento').style.display = 
                selectedTramiteType === 'abanderamiento' ? 'block' : 'none';
            document.getElementById('info-inscripcion').style.display = 
                selectedTramiteType === 'inscripcion' ? 'block' : 'none';
            
            document.getElementById('documentacion-matriculacion').style.display = 
                selectedTramiteType === 'abanderamiento' ? 'block' : 'none';
            document.getElementById('documentacion-inscripcion').style.display = 
                selectedTramiteType === 'inscripcion' ? 'block' : 'none';
            
            const camposMatriculacion = document.querySelectorAll('#documentacion-matriculacion input[required]');
            camposMatriculacion.forEach(campo => {
                campo.required = selectedTramiteType === 'abanderamiento';
            });
            
            const camposInscripcion = document.querySelectorAll('#documentacion-inscripcion input[type="file"]');
            camposInscripcion.forEach(campo => {
                campo.required = selectedTramiteType === 'inscripcion';
            });
        }

        async function validateCouponCode(code) {
            if (!code || code.trim() === '') {
                resetCoupon();
                return;
            }
            
            const couponInput = document.getElementById('coupon_code');
            const couponMessage = document.getElementById('coupon-message');
            const applyButton = document.getElementById('apply-coupon');
            
            couponInput.disabled = true;
            applyButton.disabled = true;
            applyButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            
            couponMessage.innerHTML = '<i class="fas fa-sync fa-spin"></i> Verificando cupón...';
            couponMessage.className = 'coupon-message info';
            couponMessage.style.display = 'block';
            
            try {
                const response = await fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=validate_coupon_code_matriculacion&coupon=${encodeURIComponent(code)}`
                });
                
                if (!response.ok) {
                    throw new Error('Error en la comunicación con el servidor');
                }
                
                const result = await response.json();
                
                if (result.success) {
                    discountApplied = result.data.discount_percent;
                    
                    discountAmount = parseFloat(((combinedFees * discountApplied) / 100).toFixed(2));
                    let newCombinedFees = parseFloat((combinedFees - discountAmount).toFixed(2));
                    let newIVA = parseFloat((newCombinedFees * 0.21).toFixed(2));
                    currentPrice = parseFloat((newCombinedFees + newIVA).toFixed(2));
                    
                    couponMessage.innerHTML = '<i class="fas fa-check-circle"></i> ¡Cupón aplicado correctamente!';
                    couponMessage.classList.remove('error-message','hidden');
                    couponMessage.classList.add('success');
                    
                    document.getElementById('discount-line').style.display = 'flex';
                    document.getElementById('discount-amount').textContent = '- ' + discountAmount.toFixed(2) + ' €';
                    document.getElementById('final-amount').textContent = currentPrice.toFixed(2) + ' €';
                    
                    couponInput.classList.add('coupon-valid');
                    couponInput.classList.remove('coupon-error');
                    
                    lastValidCoupon = code;
                    
                    if (stripe) {
                        stripe = null;
                        document.getElementById('payment-element').innerHTML = '';
                        initializeStripe(currentPrice);
                    }
                } else {
                    couponMessage.innerHTML = '<i class="fas fa-times-circle"></i> Cupón inválido o expirado';
                    couponMessage.classList.remove('success','hidden');
                    couponMessage.classList.add('error-message');
                    
                    couponInput.classList.add('coupon-error');
                    couponInput.classList.remove('coupon-valid');
                    
                    resetCouponDiscount();
                }
            } catch (error) {
                console.error('Error al validar el cupón:', error);
                
                couponMessage.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Error al validar el cupón';
                couponMessage.classList.remove('success','hidden');
                couponMessage.classList.add('error-message');
                
                couponInput.classList.add('coupon-error');
                couponInput.classList.remove('coupon-valid');
                
                resetCouponDiscount();
            } finally {
                couponInput.disabled = false;
                applyButton.disabled = false;
                applyButton.innerHTML = 'Aplicar';
            }
        }

        function resetCoupon() {
            const couponInput = document.getElementById('coupon_code');
            const couponMessage = document.getElementById('coupon-message');
            
            couponInput.classList.remove('coupon-error', 'coupon-valid');
            couponMessage.style.display = 'none';
            
            resetCouponDiscount();
        }

        function resetCouponDiscount() {
            document.getElementById('discount-line').style.display = 'none';
            document.getElementById('final-amount').textContent = totalPrice.toFixed(2) + ' €';
            
            discountApplied = 0;
            discountAmount = 0;
            currentPrice = totalPrice;
            lastValidCoupon = '';
            
            if (stripe) {
                stripe = null;
                document.getElementById('payment-element').innerHTML = '';
                initializeStripe(totalPrice);
            }
        }

        function validateCurrentPage() {
            let valid = true;
            const currentForm = formPages[currentPage];
            const requiredFields = currentForm.querySelectorAll('input[required], select[required]');
            const errorMessages = [];

            requiredFields.forEach(field => {
                const isHidden = isFieldHidden(field);
                
                if (!isHidden && (!field.value || (field.type === 'checkbox' && !field.checked))) {
                    valid = false;
                    field.classList.add('field-error');
                    
                    let labelText = field.name;
                    const fieldId = field.id;
                    
                    if (fieldId) {
                        const label = document.querySelector(`label[for="${fieldId}"]`);
                        if (label) {
                            labelText = label.textContent.replace(/[*?:]/g, '').trim();
                        }
                    } else if (field.previousElementSibling && field.previousElementSibling.tagName.toLowerCase() === 'label') {
                        labelText = field.previousElementSibling.textContent.replace(/[*?:]/g, '').trim();
                    }
                    
                    errorMessages.push(`El campo "${labelText}" es obligatorio.`);
                } else {
                    field.classList.remove('field-error');
                }
            });
            
            const radioGroups = new Set();
            currentForm.querySelectorAll('input[type="radio"][required]').forEach(radio => {
                if (!isFieldHidden(radio)) {
                    radioGroups.add(radio.name);
                }
            });
            
            radioGroups.forEach(groupName => {
                const checkedRadio = currentForm.querySelector(`input[name="${groupName}"]:checked`);
                if (!checkedRadio) {
                    valid = false;
                    
                    let groupLabel = groupName.replace(/_/g, ' ');
                    const firstRadio = currentForm.querySelector(`input[name="${groupName}"]`);
                    if (firstRadio) {
                        const radioContainer = firstRadio.closest('.radio-group');
                        if (radioContainer && radioContainer.previousElementSibling) {
                            groupLabel = radioContainer.previousElementSibling.textContent.replace(/[*?:]/g, '').trim();
                        }
                    }
                    
                    errorMessages.push(`Seleccione una opción para "${groupLabel}".`);
                    
                    currentForm.querySelectorAll(`input[name="${groupName}"]`).forEach(radio => {
                        const label = radio.parentElement;
                        if (label && label.tagName.toLowerCase() === 'label') {
                            label.classList.add('field-error');
                        }
                    });
                } else {
                    currentForm.querySelectorAll(`input[name="${groupName}"]`).forEach(radio => {
                        const label = radio.parentElement;
                        if (label && label.tagName.toLowerCase() === 'label') {
                            label.classList.remove('field-error');
                        }
                    });
                }
            });

            const errorDiv = document.getElementById('error-messages');
            errorDiv.innerHTML = '';

            if (!valid) {
                const errorContainer = document.createElement('div');
                errorContainer.className = 'error-container';
                errorContainer.style.padding = '15px';
                errorContainer.style.backgroundColor = '#f8d7da';
                errorContainer.style.color = '#721c24';
                errorContainer.style.borderRadius = '8px';
                errorContainer.style.marginBottom = '20px';
                errorContainer.style.borderLeft = '4px solid #dc3545';
                
                const errorTitle = document.createElement('h4');
                errorTitle.textContent = 'Por favor, corrija los siguientes errores:';
                errorTitle.style.marginTop = '0';
                errorTitle.style.marginBottom = '10px';
                errorContainer.appendChild(errorTitle);
                
                const errorList = document.createElement('ul');
                errorList.style.marginBottom = '0';
                errorMessages.forEach(msg => {
                    const li = document.createElement('li');
                    li.textContent = msg;
                    errorList.appendChild(li);
                });
                errorContainer.appendChild(errorList);
                
                errorDiv.appendChild(errorContainer);
                
                expandSectionsWithErrors();
                
                window.scrollTo({
                    top: errorDiv.offsetTop - 20,
                    behavior: 'smooth'
                });
                
                errorContainer.style.animation = 'shake 0.5s ease-in-out';
            }

            return valid;
        }
        
        function isFieldHidden(field) {
            if (field.style.display === 'none' || field.type === 'hidden') {
                return true;
            }
            
            let parent = field.parentElement;
            while (parent && parent !== document) {
                const computedStyle = window.getComputedStyle(parent);
                if (computedStyle.display === 'none' || computedStyle.visibility === 'hidden' || 
                    parent.classList.contains('collapsed') || 
                    (parent.classList.contains('form-section-content') && 
                     parent.closest('.form-section').classList.contains('collapsed'))) {
                    return true;
                }
                parent = parent.parentElement;
            }
            
            return false;
        }
        
        function expandSectionsWithErrors() {
            document.querySelectorAll('.field-error').forEach(errorField => {
                const section = errorField.closest('.form-section');
                if (section && section.classList.contains('collapsed')) {
                    section.classList.remove('collapsed');
                    
                    const toggle = section.querySelector('.section-toggle');
                    if (toggle) {
                        toggle.style.transform = 'rotate(0deg)';
                    }
                }
            });
        }

        const popup = document.getElementById('document-popup');
        const closePopup = document.querySelector('.close-popup');
        const exampleImage = document.getElementById('document-example-image');

        document.querySelectorAll('.view-example').forEach(link => {
            link.addEventListener('click', function(event) {
                event.preventDefault();
                const docType = this.getAttribute('data-doc');
                
                exampleImage.src = '';
                exampleImage.alt = 'Cargando...';
                const loadingOverlay = document.createElement('div');
                loadingOverlay.style.position = 'absolute';
                loadingOverlay.style.top = '0';
                loadingOverlay.style.left = '0';
                loadingOverlay.style.width = '100%';
                loadingOverlay.style.height = '100%';
                loadingOverlay.style.display = 'flex';
                loadingOverlay.style.alignItems = 'center';
                loadingOverlay.style.justifyContent = 'center';
                loadingOverlay.style.backgroundColor = 'rgba(255, 255, 255, 0.8)';
                loadingOverlay.innerHTML = '<div class="spinner" style="border: 6px solid #f3f3f3; border-top: 6px solid #016d86; border-radius: 50%; width: 50px; height: 50px; animation: spin 1.5s linear infinite;"></div>';
                popup.querySelector('.popup-content').appendChild(loadingOverlay);
                
                popup.style.display = 'block';
                
                const img = new Image();
                img.onload = function() {
                    if (loadingOverlay.parentNode) {
                        loadingOverlay.parentNode.removeChild(loadingOverlay);
                    }
                    exampleImage.src = img.src;
                    exampleImage.alt = 'Ejemplo de documento ' + docType;
                };
                img.onerror = function() {
                    if (loadingOverlay.parentNode) {
                        loadingOverlay.parentNode.removeChild(loadingOverlay);
                    }
                    exampleImage.src = '';
                    exampleImage.alt = 'Error al cargar la imagen';
                    popup.querySelector('.popup-content').innerHTML += '<p style="color: #dc3545; text-align: center;">No se pudo cargar la imagen de ejemplo</p>';
                };
                img.src = '/wp-content/uploads/exampledocs/' + docType + '.jpg';
            });
        });

        closePopup.addEventListener('click', () => {
            popup.style.opacity = '0';
            setTimeout(() => {
                popup.style.display = 'none';
                popup.style.opacity = '1';
            }, 300);
        });

        window.addEventListener('click', function(event) {
            if (event.target === popup) {
                closePopup.click();
            }
        });
        
        function setupCollapsibleSections() {
            document.querySelectorAll('.form-section-header').forEach(header => {
                header.addEventListener('click', function() {
                    const section = this.closest('.form-section');
                    section.classList.toggle('collapsed');
                    
                    const toggle = this.querySelector('.section-toggle');
                    if (section.classList.contains('collapsed')) {
                        toggle.style.transform = 'rotate(180deg)';
                    } else {
                        toggle.style.transform = 'rotate(0deg)';
                    }
                });
            });
        }
        
        function setupHelpModal() {
            const helpButton = document.getElementById('help-button');
            const helpModal = document.getElementById('help-modal');
            const closeHelpModal = document.getElementById('close-help-modal');
            
            helpButton.addEventListener('click', function() {
                helpModal.style.display = 'block';
                updateHelpContent();
                
                const modalContent = helpModal.querySelector('div');
                modalContent.style.opacity = '0';
                modalContent.style.transform = 'translateY(-20px)';
                
                setTimeout(() => {
                    modalContent.style.transition = 'all 0.3s ease';
                    modalContent.style.opacity = '1';
                    modalContent.style.transform = 'translateY(0)';
                }, 10);
            });
            
            closeHelpModal.addEventListener('click', function() {
                const modalContent = helpModal.querySelector('div');
                modalContent.style.opacity = '0';
                modalContent.style.transform = 'translateY(-20px)';
                
                setTimeout(() => {
                    helpModal.style.display = 'none';
                }, 300);
            });
            
            helpModal.addEventListener('click', function(e) {
                if (e.target === helpModal) {
                    closeHelpModal.click();
                }
            });
        }

        const nextButtonMain = document.getElementById('nextButtonMain');
        if (nextButtonMain) {
            nextButtonMain.addEventListener('click', () => {
                if (validateCurrentPage()) {
                    if (!completedPages.includes(currentPage)) {
                        completedPages.push(currentPage);
                    }
                    
                    currentPage++;
                    updateForm();
                }
            });
        }

        const prevButtonMain = document.getElementById('prevButtonMain');
        if (prevButtonMain) {
            prevButtonMain.addEventListener('click', () => {
                currentPage--;
                updateForm();
            });
        }

        const prevButtonPayment = document.getElementById('prevButtonPayment');
        if (prevButtonPayment) {
            prevButtonPayment.addEventListener('click', () => {
                currentPage--;
                updateForm();
            });
        }

        navLinks.forEach((link, index) => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                
                currentPage = index;
                updateForm();
                
                const activeLink = document.querySelector('.nav-link.active');
                if (activeLink) {
                    activeLink.style.transition = 'all 0.3s ease';
                    activeLink.style.transform = 'scale(0.95)';
                    setTimeout(() => {
                        activeLink.style.transform = '';
                    }, 300);
                }
            });
        });

        function setupTramiteSelection() {
            const tramiteCards = document.querySelectorAll('.tramite-card');
            const continueButton = document.getElementById('continue-with-tramite');
            
            tramiteCards.forEach(card => {
                card.addEventListener('click', function() {
                    tramiteCards.forEach(c => c.classList.remove('selected'));
                    
                    this.classList.add('selected');
                    
                    selectedTramiteType = this.getAttribute('data-tramite-type');
                    
                    document.getElementById('tipo_tramite_hidden').value = selectedTramiteType;
                    continueButton.disabled = false;
                    
                    this.style.transform = 'scale(1.02)';
                    setTimeout(() => {
                        this.style.transform = '';
                    }, 200);
                });
            });
            
            continueButton.addEventListener('click', function() {
                if (selectedTramiteType) {
                    startMainForm(selectedTramiteType);
                } else {
                    showNotification('Por favor, seleccione un tipo de trámite', 'error');
                }
            });
        }

        function setupChangeTramiteButton() {
            const changeTramiteButton = document.getElementById('change-tramite-button');
            if (changeTramiteButton) {
                changeTramiteButton.addEventListener('click', function() {
                    document.getElementById('form-navigation').classList.add('hidden');
                    document.getElementById('main-button-container').style.display = 'none';
                    
                    formPages.forEach(page => {
                        page.classList.add('hidden');
                    });
                    
                    document.getElementById('tramite-selector-container').style.display = 'block';
                    
                    document.querySelectorAll('.tramite-card').forEach(c => c.classList.remove('selected'));
                    document.getElementById('continue-with-tramite').disabled = true;
                    
                    showNotification('Ahora puede seleccionar un tipo de trámite diferente', 'info');
                    
                    window.scrollTo({
                        top: document.getElementById('tramite-selector-container').offsetTop - 20,
                        behavior: 'smooth'
                    });
                });
            }
        }

        function startMainForm(tramiteType) {
            document.getElementById('tramite-selector-container').style.display = 'none';
            document.getElementById('form-navigation').classList.remove('hidden');
            document.getElementById('main-button-container').style.display = 'flex';
            
            formPages.forEach((page, index) => {
                page.classList.toggle('hidden', index !== 0);
            });
            
            handleTramiteTypeChange();
            
            formPages[0].style.opacity = '0';
            setTimeout(() => {
                formPages[0].style.transition = 'opacity 0.5s ease';
                formPages[0].style.opacity = '1';
            }, 50);
            
            showNotification(`Ha seleccionado el trámite de ${tramiteType === 'abanderamiento' ? 'Abanderamiento/Matriculación' : 'Inscripción'}`, 'success');
        }

        document.querySelectorAll('input[name="combustible"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const showOtros = this.value === 'otros';
                document.getElementById('label-combustible-otros').style.display = showOtros ? 'block' : 'none';
                document.getElementById('combustible_otros').style.display = showOtros ? 'block' : 'none';
                document.getElementById('combustible_otros').required = showOtros;
            });
        });
        
        document.getElementById('propietario_nombre').addEventListener('change', function() {
            if (!document.getElementById('autorizacion_nombre').value) {
                document.getElementById('autorizacion_nombre').value = this.value;
            }
        });
        
        document.getElementById('propietario_nif').addEventListener('change', function() {
            if (!document.getElementById('autorizacion_dni').value) {
                document.getElementById('autorizacion_dni').value = this.value;
            }
        });
        
        function checkPaymentRedirection() {
    const urlParams = new URLSearchParams(window.location.search);
    const paymentSuccess = urlParams.get('payment_success');
    const tramiteId = urlParams.get('tramite_id');
    
    if (paymentSuccess === 'true' && tramiteId) {
        // Mostrar mensaje de éxito
        document.getElementById('matriculacion-form').innerHTML = `
            <div class="success-message" style="text-align: center; padding: 30px; background-color: #d4edda; border-radius: 10px; margin: 20px 0;">
                <i class="fas fa-check-circle" style="font-size: 48px; color: #28a745; margin-bottom: 20px;"></i>
                <h2 style="color: #155724; margin-bottom: 15px;">¡Trámite completado con éxito!</h2>
                <p style="font-size: 18px; margin-bottom: 20px;">Su solicitud de matriculación ha sido recibida correctamente.</p>
                <p>Número de trámite: <strong>${tramiteId}</strong></p>
                <p>Se ha enviado un email de confirmación a su dirección de correo electrónico.</p>
                <p style="margin-top: 30px;">Un gestor se pondrá en contacto con usted en breve para informarle sobre el avance de su trámite.</p>
                <div id="sending-email-indicator" style="margin-top: 20px; display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-spinner fa-spin" style="margin-right: 10px;"></i> Enviando correo de confirmación...
                </div>
                <a href="<?php echo esc_url(home_url()); ?>" class="button button-primary" style="margin-top: 30px; display: inline-block;">
                    <i class="fas fa-home"></i> Volver al inicio
                </a>
            </div>
        `;
        
        // Enviar petición para generar correo de confirmación
        fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=enviar_correo_confirmacion&tramite_id=${encodeURIComponent(tramiteId)}`
        })
        .then(response => response.json())
        .then(data => {
            document.getElementById('sending-email-indicator').innerHTML = 
                data.success ? 
                '<i class="fas fa-check" style="color: #28a745; margin-right: 10px;"></i> Correo de confirmación enviado.' :
                '<i class="fas fa-exclamation-triangle" style="color: #dc3545; margin-right: 10px;"></i> No pudimos enviar el correo de confirmación.';
        })
        .catch(error => {
            document.getElementById('sending-email-indicator').innerHTML = 
                '<i class="fas fa-exclamation-triangle" style="color: #dc3545; margin-right: 10px;"></i> Error al enviar el correo de confirmación.';
        });
        
        localStorage.removeItem('matriculacion_form_data');
        localStorage.removeItem('matriculacion_form_page');
        localStorage.removeItem('matriculacion_completed_pages');
        localStorage.removeItem('matriculacion_tramite_type');
        localStorage.removeItem('matriculacion_signature');
        
        return true;
    }
    
    return false;
}
        
      // Función para autorellenar datos de prueba (versión mejorada)

    // Seleccionar tipo de trámite si no está seleccionado
    if (!selectedTramiteType) {
        const abandonamientoCard = document.getElementById('card-abanderamiento');
        if (abandonamientoCard) {
            abandonamientoCard.click();
            document.getElementById('continue-with-tramite').click();
// Función para autorellenar datos de prueba (versión mejorada)
// Función para autorellenar datos de prueba (versión mejorada)
function fillTestData() {
    // Seleccionar tipo de trámite si no está seleccionado
    if (!selectedTramiteType) {
        const abandonamientoCard = document.getElementById('card-abanderamiento');
        if (abandonamientoCard) {
            abandonamientoCard.click();
            document.getElementById('continue-with-tramite').click();
        }
        
        // Dar tiempo para que se muestre el formulario completo
        setTimeout(() => completeFormWithTestData(), 500);
    } else {
        completeFormWithTestData();
    }
}

function completeFormWithTestData() {
    // Expandir todas las secciones colapsadas para acceder a todos los campos
    document.querySelectorAll('.form-section.collapsed').forEach(section => {
        const header = section.querySelector('.form-section-header');
        if (header) header.click();
    });
    
    // Datos de prueba completos para todos los campos
    const testData = {
        // Datos de embarcación
        'marca': 'Bayliner',
        'modelo': 'Element E16',
        'tipo_embarcacion': 'motor',
        'categoria_diseno': 'C',
        'num_serie': 'BY12345XYZ789',
        'eslora': '4.98',
        'manga': '2.10',
        'num_max_personas': '6',
        'carga_maxima': '450',
        'material_casco': 'Fibra de vidrio',
        'zona_navegacion': 'Aguas costeras',
        'fecha_adquisicion': new Date().toISOString().split('T')[0], // Fecha actual en formato YYYY-MM-DD
        'motor_marca': 'Mercury',
        'motor_modelo': 'F40',
        'motor_potencia': '40',
        'motor_num_serie': 'MERC12345Z',
        'combustible': 'gasolina',
        'num_motores': '1',
        'tipo_motor': 'fueraborda',
        
        // Datos auxiliares (opcionales)
        'aux_marca': 'Zodiac',
        'aux_modelo': 'Mini 250',
        'aux_categoria_diseno': 'D',
        'aux_num_serie': 'ZOD345678',
        'aux_eslora': '2.5',
        'aux_num_max_personas': '2',
        'aux_num_embarcaciones': '1',
        'aux_fecha_adquisicion': new Date().toISOString().split('T')[0],
        
        // Datos del propietario
        'propietario_nombre': 'Juan Antonio Pérez García',
        'propietario_nif': '48123456A',
        'propietario_via': 'Calle Principal',
        'propietario_numero': '25',
        'propietario_escalera': '1',
        'propietario_piso': '4',
        'propietario_puerta': 'B',
        'propietario_cp': '28001',
        'propietario_localidad': 'Madrid',
        'propietario_provincia': 'Madrid',
        'propietario_pais': 'España',
        'propietario_telefono': '910123456',
        'propietario_movil': '666123456',
        'propietario_email': 'joanpinyol@hotmail.es',
        
        // Datos de autorización
        'autorizacion_nombre': 'Juan Antonio Pérez García',
        'autorizacion_dni': '48123456A',
        
        // Datos de matriculación/listas
        'lista_matricula': '7ª',
        'nombre_propuesto': 'NEPTUNO',
        'lista_inscripcion': '7ª'
    };
    
    // Rellenar todos los campos de texto, número, email, tel, date, etc.
    const inputSelectors = 'input[type="text"], input[type="number"], input[type="email"], input[type="tel"], input[type="date"], textarea, select';
    document.querySelectorAll(inputSelectors).forEach(input => {
        if (testData[input.name]) {
            input.value = testData[input.name];
            // Disparar evento input y change para activar validaciones
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }
    });
    
    // Manejar radio buttons
    const radioGroups = ['tipo_embarcacion', 'combustible', 'tipo_motor'];
    radioGroups.forEach(group => {
        if (testData[group]) {
            const radio = document.querySelector(`input[name="${group}"][value="${testData[group]}"]`);
            if (radio) {
                radio.checked = true;
                radio.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }
    });
    
    // Manejar checkboxes (términos y condiciones)
    document.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
        checkbox.checked = true;
        checkbox.dispatchEvent(new Event('change', { bubbles: true }));
    });
    
    // Simular archivos adjuntos modificando los campos de file input
    document.querySelectorAll('input[type="file"]').forEach(fileInput => {
        if (fileInput && fileInput.parentElement) {
            // Crear elemento visual para indicar que hay un archivo "cargado"
            const fileStatus = document.createElement('div');
            fileStatus.className = 'file-upload-status';
            fileStatus.style.padding = '5px 10px';
            fileStatus.style.backgroundColor = '#d4edda';
            fileStatus.style.color = '#155724';
            fileStatus.style.borderRadius = '4px';
            fileStatus.style.marginTop = '5px';
            fileStatus.style.fontSize = '14px';
            fileStatus.innerHTML = '<i class="fas fa-check-circle"></i> Archivo de prueba simulado.pdf';
            
            // Eliminar estado anterior si existe
            const existingStatus = fileInput.parentElement.querySelector('.file-upload-status');
            if (existingStatus) {
                existingStatus.remove();
            }
            
            fileInput.parentElement.appendChild(fileStatus);
            
            // Marcar el campo como "completo" para validación
            Object.defineProperty(fileInput, 'files', {
                value: [{
                    name: 'archivo_simulado.pdf',
                    size: 512000,
                    type: 'application/pdf'
                }],
                writable: false
            });
            
            // Disparar eventos para notificar al formulario
            const event = new Event('change', { bubbles: true });
            fileInput.dispatchEvent(event);
        }
    });
    
    // Generar una firma simulada
    if (typeof signaturePad !== 'undefined' && signaturePad) {
        // Limpiar firma existente
        signaturePad.clear();
        
        // Obtener canvas y contexto
        const canvas = document.getElementById('signature-pad');
        if (canvas) {
            const ctx = canvas.getContext('2d');
            if (ctx) {
                // Obtener dimensiones
                const width = canvas.width / (window.devicePixelRatio || 1);
                const height = canvas.height / (window.devicePixelRatio || 1);
                
                // Dibujar firma simulada
                ctx.beginPath();
                ctx.moveTo(width * 0.2, height * 0.5);
                ctx.bezierCurveTo(
                    width * 0.3, height * 0.3,
                    width * 0.5, height * 0.8,
                    width * 0.8, height * 0.2
                );
                ctx.lineWidth = 2;
                ctx.strokeStyle = "black";
                ctx.stroke();
                
                // Adicional: Agregar el apellido para hacer la firma más realista
                ctx.font = '20px cursive';
                ctx.fillStyle = 'black';
                ctx.fillText('Pérez', width * 0.4, height * 0.7);
                
                // Guardar firma en variables globales
                signaturePad._isEmpty = false;
                window.mainSignatureData = signaturePad.toDataURL();
                
                // Actualizar campo oculto y estado visual
                const signatureDataField = document.getElementById('signature_data');
                if (signatureDataField) signatureDataField.value = window.mainSignatureData;
                
                // Actualizar estado de firma
                if (typeof updateSignatureStatus === 'function') {
                    updateSignatureStatus();
                }
            }
        }
    }
    
    // Notificar al usuario
    showNotification('Formulario rellenado con datos de prueba', 'success');
    
    // Subir automáticamente en la página para ver el resultado
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
    // Seleccionar tipo de trámite si no está seleccionado
    if (!selectedTramiteType) {
        const abandonamientoCard = document.getElementById('card-abanderamiento');
        if (abandonamientoCard) {
            abandonamientoCard.click();
            document.getElementById('continue-with-tramite').click();
        }
        
        // Dar tiempo para que se muestre el formulario completo
        setTimeout(() => completeFormWithTestData(), 500);
    } else {
        completeFormWithTestData();
    }
}

function completeFormWithTestData() {
    // Expandir todas las secciones colapsadas para acceder a todos los campos
    document.querySelectorAll('.form-section.collapsed').forEach(section => {
        const header = section.querySelector('.form-section-header');
        if (header) header.click();
    });
    
    // Datos de prueba completos para todos los campos
    const testData = {
        // Datos de embarcación
        'marca': 'Bayliner',
        'modelo': 'Element E16',
        'tipo_embarcacion': 'motor',
        'categoria_diseno': 'C',
        'num_serie': 'BY12345XYZ789',
        'eslora': '4.98',
        'manga': '2.10',
        'num_max_personas': '6',
        'carga_maxima': '450',
        'material_casco': 'Fibra de vidrio',
        'zona_navegacion': 'Aguas costeras',
        'fecha_adquisicion': new Date().toISOString().split('T')[0], // Fecha actual en formato YYYY-MM-DD
        'motor_marca': 'Mercury',
        'motor_modelo': 'F40',
        'motor_potencia': '40',
        'motor_num_serie': 'MERC12345Z',
        'combustible': 'gasolina',
        'num_motores': '1',
        'tipo_motor': 'fueraborda',
        
        // Datos auxiliares (opcionales)
        'aux_marca': 'Zodiac',
        'aux_modelo': 'Mini 250',
        'aux_categoria_diseno': 'D',
        'aux_num_serie': 'ZOD345678',
        'aux_eslora': '2.5',
        'aux_num_max_personas': '2',
        'aux_num_embarcaciones': '1',
        'aux_fecha_adquisicion': new Date().toISOString().split('T')[0],
        
        // Datos del propietario
        'propietario_nombre': 'Juan Antonio Pérez García',
        'propietario_nif': '48123456A',
        'propietario_via': 'Calle Principal',
        'propietario_numero': '25',
        'propietario_escalera': '1',
        'propietario_piso': '4',
        'propietario_puerta': 'B',
        'propietario_cp': '28001',
        'propietario_localidad': 'Madrid',
        'propietario_provincia': 'Madrid',
        'propietario_pais': 'España',
        'propietario_telefono': '910123456',
        'propietario_movil': '666123456',
        'propietario_email': 'joanpinyol@hotmail.es',
        
        // Datos de autorización
        'autorizacion_nombre': 'Juan Antonio Pérez García',
        'autorizacion_dni': '48123456A',
        
        // Datos de matriculación/listas
        'lista_matricula': '7ª',
        'nombre_propuesto': 'NEPTUNO',
        'lista_inscripcion': '7ª'
    };
    
    // Rellenar todos los campos de texto, número, email, tel, date, etc.
    const inputSelectors = 'input[type="text"], input[type="number"], input[type="email"], input[type="tel"], input[type="date"], textarea, select';
    document.querySelectorAll(inputSelectors).forEach(input => {
        if (testData[input.name]) {
            input.value = testData[input.name];
            // Disparar evento input y change para activar validaciones
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }
    });
    
    // Manejar radio buttons
    const radioGroups = ['tipo_embarcacion', 'combustible', 'tipo_motor'];
    radioGroups.forEach(group => {
        if (testData[group]) {
            const radio = document.querySelector(`input[name="${group}"][value="${testData[group]}"]`);
            if (radio) {
                radio.checked = true;
                radio.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }
    });
    
    // Manejar checkboxes (términos y condiciones)
    document.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
        checkbox.checked = true;
        checkbox.dispatchEvent(new Event('change', { bubbles: true }));
    });
    
    // Simular archivos adjuntos modificando los campos de file input
    document.querySelectorAll('input[type="file"]').forEach(fileInput => {
        if (fileInput && fileInput.parentElement) {
            // Crear elemento visual para indicar que hay un archivo "cargado"
            const fileStatus = document.createElement('div');
            fileStatus.className = 'file-upload-status';
            fileStatus.style.padding = '5px 10px';
            fileStatus.style.backgroundColor = '#d4edda';
            fileStatus.style.color = '#155724';
            fileStatus.style.borderRadius = '4px';
            fileStatus.style.marginTop = '5px';
            fileStatus.style.fontSize = '14px';
            fileStatus.innerHTML = '<i class="fas fa-check-circle"></i> Archivo de prueba simulado.pdf';
            
            // Eliminar estado anterior si existe
            const existingStatus = fileInput.parentElement.querySelector('.file-upload-status');
            if (existingStatus) {
                existingStatus.remove();
            }
            
            fileInput.parentElement.appendChild(fileStatus);
            
            // Marcar el campo como "completo" para validación
            Object.defineProperty(fileInput, 'files', {
                value: [{
                    name: 'archivo_simulado.pdf',
                    size: 512000,
                    type: 'application/pdf'
                }],
                writable: false
            });
            
            // Disparar eventos para notificar al formulario
            const event = new Event('change', { bubbles: true });
            fileInput.dispatchEvent(event);
        }
    });
    
    // Generar una firma simulada
    if (typeof signaturePad !== 'undefined' && signaturePad) {
        // Limpiar firma existente
        signaturePad.clear();
        
        // Obtener canvas y contexto
        const canvas = document.getElementById('signature-pad');
        if (canvas) {
            const ctx = canvas.getContext('2d');
            if (ctx) {
                // Obtener dimensiones
                const width = canvas.width / (window.devicePixelRatio || 1);
                const height = canvas.height / (window.devicePixelRatio || 1);
                
                // Dibujar firma simulada
                ctx.beginPath();
                ctx.moveTo(width * 0.2, height * 0.5);
                ctx.bezierCurveTo(
                    width * 0.3, height * 0.3,
                    width * 0.5, height * 0.8,
                    width * 0.8, height * 0.2
                );
                ctx.lineWidth = 2;
                ctx.strokeStyle = "black";
                ctx.stroke();
                
                // Adicional: Agregar el apellido para hacer la firma más realista
                ctx.font = '20px cursive';
                ctx.fillStyle = 'black';
                ctx.fillText('Pérez', width * 0.4, height * 0.7);
                
                // Guardar firma en variables globales
                signaturePad._isEmpty = false;
                window.mainSignatureData = signaturePad.toDataURL();
                
                // Actualizar campo oculto y estado visual
                const signatureDataField = document.getElementById('signature_data');
                if (signatureDataField) signatureDataField.value = window.mainSignatureData;
                
                // Actualizar estado de firma
                if (typeof updateSignatureStatus === 'function') {
                    updateSignatureStatus();
                }
            }
        }
    }
    
    // Notificar al usuario
    showNotification('Formulario rellenado con datos de prueba', 'success');
    
    // Subir automáticamente en la página para ver el resultado
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
}

function completeFormWithTestData() {
    // Expandir todas las secciones colapsadas para acceder a todos los campos
    document.querySelectorAll('.form-section.collapsed').forEach(section => {
        const header = section.querySelector('.form-section-header');
        if (header) header.click();
    });
    
    // Datos de prueba completos para todos los campos
    const testData = {
        // Datos de embarcación
        'marca': 'Bayliner',
        'modelo': 'Element E16',
        'tipo_embarcacion': 'motor',
        'categoria_diseno': 'C',
        'num_serie': 'BY12345XYZ789',
        'eslora': '4.98',
        'manga': '2.10',
        'num_max_personas': '6',
        'carga_maxima': '450',
        'material_casco': 'Fibra de vidrio',
        'zona_navegacion': 'Aguas costeras',
        'fecha_adquisicion': new Date().toISOString().split('T')[0], // Fecha actual en formato YYYY-MM-DD
        'motor_marca': 'Mercury',
        'motor_modelo': 'F40',
        'motor_potencia': '40',
        'motor_num_serie': 'MERC12345Z',
        'combustible': 'gasolina',
        'num_motores': '1',
        'tipo_motor': 'fueraborda',
        
        // Datos auxiliares (opcionales)
        'aux_marca': 'Zodiac',
        'aux_modelo': 'Mini 250',
        'aux_categoria_diseno': 'D',
        'aux_num_serie': 'ZOD345678',
        'aux_eslora': '2.5',
        'aux_num_max_personas': '2',
        'aux_num_embarcaciones': '1',
        'aux_fecha_adquisicion': new Date().toISOString().split('T')[0],
        
        // Datos del propietario
        'propietario_nombre': 'Juan Antonio Pérez García',
        'propietario_nif': '48123456A',
        'propietario_via': 'Calle Principal',
        'propietario_numero': '25',
        'propietario_escalera': '1',
        'propietario_piso': '4',
        'propietario_puerta': 'B',
        'propietario_cp': '28001',
        'propietario_localidad': 'Madrid',
        'propietario_provincia': 'Madrid',
        'propietario_pais': 'España',
        'propietario_telefono': '910123456',
        'propietario_movil': '666123456',
        'propietario_email': 'joanpinyol@hotmail.es',
        
        // Datos de autorización
        'autorizacion_nombre': 'Juan Antonio Pérez García',
        'autorizacion_dni': '48123456A',
        
        // Datos de matriculación/listas
        'lista_matricula': '7ª',
        'nombre_propuesto': 'NEPTUNO',
        'lista_inscripcion': '7ª'
    };
    
    // Rellenar todos los campos de texto, número, email, tel, date, etc.
    const inputSelectors = 'input[type="text"], input[type="number"], input[type="email"], input[type="tel"], input[type="date"], textarea, select';
    document.querySelectorAll(inputSelectors).forEach(input => {
        if (testData[input.name]) {
            input.value = testData[input.name];
            // Disparar evento input y change para activar validaciones
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }
    });
    
    // Manejar radio buttons
    const radioGroups = ['tipo_embarcacion', 'combustible', 'tipo_motor'];
    radioGroups.forEach(group => {
        if (testData[group]) {
            const radio = document.querySelector(`input[name="${group}"][value="${testData[group]}"]`);
            if (radio) {
                radio.checked = true;
                radio.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }
    });
    
    // Manejar checkboxes (términos y condiciones)
    document.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
        checkbox.checked = true;
        checkbox.dispatchEvent(new Event('change', { bubbles: true }));
    });
    
    // Simular archivos adjuntos modificando los campos de file input
    document.querySelectorAll('input[type="file"]').forEach(fileInput => {
        if (fileInput && fileInput.parentElement) {
            // Crear elemento visual para indicar que hay un archivo "cargado"
            const fileStatus = document.createElement('div');
            fileStatus.className = 'file-upload-status';
            fileStatus.style.padding = '5px 10px';
            fileStatus.style.backgroundColor = '#d4edda';
            fileStatus.style.color = '#155724';
            fileStatus.style.borderRadius = '4px';
            fileStatus.style.marginTop = '5px';
            fileStatus.style.fontSize = '14px';
            fileStatus.innerHTML = '<i class="fas fa-check-circle"></i> Archivo de prueba simulado.pdf';
            
            // Eliminar estado anterior si existe
            const existingStatus = fileInput.parentElement.querySelector('.file-upload-status');
            if (existingStatus) {
                existingStatus.remove();
            }
            
            fileInput.parentElement.appendChild(fileStatus);
            
            // Marcar el campo como "completo" para validación
            Object.defineProperty(fileInput, 'files', {
                value: [{
                    name: 'archivo_simulado.pdf',
                    size: 512000,
                    type: 'application/pdf'
                }],
                writable: false
            });
            
            // Disparar eventos para notificar al formulario
            const event = new Event('change', { bubbles: true });
            fileInput.dispatchEvent(event);
        }
    });
    
    // Generar una firma simulada
    if (typeof signaturePad !== 'undefined' && signaturePad) {
        // Limpiar firma existente
        signaturePad.clear();
        
        // Obtener canvas y contexto
        const canvas = document.getElementById('signature-pad');
        if (canvas) {
            const ctx = canvas.getContext('2d');
            if (ctx) {
                // Obtener dimensiones
                const width = canvas.width / (window.devicePixelRatio || 1);
                const height = canvas.height / (window.devicePixelRatio || 1);
                
                // Dibujar firma simulada
                ctx.beginPath();
                ctx.moveTo(width * 0.2, height * 0.5);
                ctx.bezierCurveTo(
                    width * 0.3, height * 0.3,
                    width * 0.5, height * 0.8,
                    width * 0.8, height * 0.2
                );
                ctx.lineWidth = 2;
                ctx.strokeStyle = "black";
                ctx.stroke();
                
                // Adicional: Agregar el apellido para hacer la firma más realista
                ctx.font = '20px cursive';
                ctx.fillStyle = 'black';
                ctx.fillText('Pérez', width * 0.4, height * 0.7);
                
                // Guardar firma en variables globales
                signaturePad._isEmpty = false;
                window.mainSignatureData = signaturePad.toDataURL();
                
                // Actualizar campo oculto y estado visual
                const signatureDataField = document.getElementById('signature_data');
                if (signatureDataField) signatureDataField.value = window.mainSignatureData;
                
                // Actualizar estado de firma
                if (typeof updateSignatureStatus === 'function') {
                    updateSignatureStatus();
                }
            }
        }
    }
    
    // Notificar al usuario
    showNotification('Formulario rellenado con datos de prueba', 'success');
    
    // Subir automáticamente en la página para ver el resultado
    window.scrollTo({ top: 0, behavior: 'smooth' });
}


    // Expandir todas las secciones colapsadas para acceder a todos los campos
    document.querySelectorAll('.form-section.collapsed').forEach(section => {
        const header = section.querySelector('.form-section-header');
        if (header) header.click();
    });
    
    // Datos de prueba completos para todos los campos
    const testData = {
        // Datos de embarcación
        'marca': 'Bayliner',
        'modelo': 'Element E16',
        'tipo_embarcacion': 'motor',
        'categoria_diseno': 'C',
        'num_serie': 'BY12345XYZ789',
        'eslora': '4.98',
        'manga': '2.10',
        'num_max_personas': '6',
        'carga_maxima': '450',
        'material_casco': 'Fibra de vidrio',
        'zona_navegacion': 'Aguas costeras',
        'fecha_adquisicion': new Date().toISOString().split('T')[0], // Fecha actual en formato YYYY-MM-DD
        'motor_marca': 'Mercury',
        'motor_modelo': 'F40',
        'motor_potencia': '40',
        'motor_num_serie': 'MERC12345Z',
        'combustible': 'gasolina',
        'num_motores': '1',
        'tipo_motor': 'fueraborda',
        
        // Datos auxiliares (opcionales)
        'aux_marca': 'Zodiac',
        'aux_modelo': 'Mini 250',
        'aux_categoria_diseno': 'D',
        'aux_num_serie': 'ZOD345678',
        'aux_eslora': '2.5',
        'aux_num_max_personas': '2',
        'aux_num_embarcaciones': '1',
        'aux_fecha_adquisicion': new Date().toISOString().split('T')[0],
        
        // Datos del propietario
        'propietario_nombre': 'Juan Antonio Pérez García',
        'propietario_nif': '48123456A',
        'propietario_via': 'Calle Principal',
        'propietario_numero': '25',
        'propietario_escalera': '1',
        'propietario_piso': '4',
        'propietario_puerta': 'B',
        'propietario_cp': '28001',
        'propietario_localidad': 'Madrid',
        'propietario_provincia': 'Madrid',
        'propietario_pais': 'España',
        'propietario_telefono': '910123456',
        'propietario_movil': '666123456',
        'propietario_email': 'joanpinyol@hotmail.es',
        
        // Datos de autorización
        'autorizacion_nombre': 'Juan Antonio Pérez García',
        'autorizacion_dni': '48123456A',
        
        // Datos de matriculación/listas
        'lista_matricula': '7ª',
        'nombre_propuesto': 'NEPTUNO',
        'lista_inscripcion': '7ª'
    };
    
    // Rellenar todos los campos de texto, número, email, tel, date, etc.
    const inputSelectors = 'input[type="text"], input[type="number"], input[type="email"], input[type="tel"], input[type="date"], textarea, select';
    document.querySelectorAll(inputSelectors).forEach(input => {
        if (testData[input.name]) {
            input.value = testData[input.name];
            // Disparar evento input y change para activar validaciones
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }
    });
    
    // Manejar radio buttons
    const radioGroups = ['tipo_embarcacion', 'combustible', 'tipo_motor'];
    radioGroups.forEach(group => {
        if (testData[group]) {
            const radio = document.querySelector(`input[name="${group}"][value="${testData[group]}"]`);
            if (radio) {
                radio.checked = true;
                radio.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }
    });
    
    // Manejar checkboxes (términos y condiciones)
    document.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
        checkbox.checked = true;
        checkbox.dispatchEvent(new Event('change', { bubbles: true }));
    });
    
    // Simular archivos adjuntos modificando los campos de file input
    document.querySelectorAll('input[type="file"]').forEach(fileInput => {
        if (fileInput && fileInput.parentElement) {
            // Crear elemento visual para indicar que hay un archivo "cargado"
            const fileStatus = document.createElement('div');
            fileStatus.className = 'file-upload-status';
            fileStatus.style.padding = '5px 10px';
            fileStatus.style.backgroundColor = '#d4edda';
            fileStatus.style.color = '#155724';
            fileStatus.style.borderRadius = '4px';
            fileStatus.style.marginTop = '5px';
            fileStatus.style.fontSize = '14px';
            fileStatus.innerHTML = '<i class="fas fa-check-circle"></i> Archivo de prueba simulado.pdf';
            
            // Eliminar estado anterior si existe
            const existingStatus = fileInput.parentElement.querySelector('.file-upload-status');
            if (existingStatus) {
                existingStatus.remove();
            }
            
            fileInput.parentElement.appendChild(fileStatus);
            
            // Marcar el campo como "completo" para validación
            Object.defineProperty(fileInput, 'files', {
                value: [{
                    name: 'archivo_simulado.pdf',
                    size: 512000,
                    type: 'application/pdf'
                }],
                writable: false
            });
        }
    });
    
    // Generar una firma simulada
    if (typeof signaturePad !== 'undefined' && signaturePad) {
        // Limpiar firma existente
        signaturePad.clear();
        
        // Obtener canvas y contexto
        const canvas = document.getElementById('signature-pad');
        if (canvas) {
            const ctx = canvas.getContext('2d');
            if (ctx) {
                // Obtener dimensiones
                const width = canvas.width / (window.devicePixelRatio || 1);
                const height = canvas.height / (window.devicePixelRatio || 1);
                
                // Dibujar firma simulada
                ctx.beginPath();
                ctx.moveTo(width * 0.2, height * 0.5);
                ctx.bezierCurveTo(
                    width * 0.3, height * 0.3,
                    width * 0.5, height * 0.8,
                    width * 0.8, height * 0.2
                );
                ctx.lineWidth = 2;
                ctx.strokeStyle = "black";
                ctx.stroke();
                
                // Adicional: Agregar el apellido para hacer la firma más realista
                ctx.font = '20px cursive';
                ctx.fillStyle = 'black';
                ctx.fillText('Pérez', width * 0.4, height * 0.7);
                
                // Guardar firma en variables globales
                signaturePad._isEmpty = false;
                window.mainSignatureData = signaturePad.toDataURL();
                
                // Actualizar campo oculto y estado visual
                const signatureDataField = document.getElementById('signature_data');
                if (signatureDataField) signatureDataField.value = window.mainSignatureData;
                
                // Actualizar estado de firma
                if (typeof updateSignatureStatus === 'function') {
                    updateSignatureStatus();
                }
            }
        }
    }
    
    // Notificar al usuario
    showNotification('Formulario rellenado con datos de prueba', 'success');
    
    // Subir automáticamente en la página para ver el resultado
    window.scrollTo({ top: 0, behavior: 'smooth' });
        
        function completeFormWithTestData() {
            // Expandir todas las secciones colapsadas
            document.querySelectorAll('.form-section.collapsed').forEach(section => {
                const header = section.querySelector('.form-section-header');
                if (header) header.click();
            });
            
            // Datos de prueba completos
            const testData = {
                // Datos de embarcación
                'marca': 'Bayliner',
                'modelo': 'Element E16',
                'tipo_embarcacion': 'motor',
                'categoria_diseno': 'C',
                'num_serie': 'BY12345XYZ789',
                'eslora': '4.98',
                'manga': '2.10',
                'num_max_personas': '6',
                'carga_maxima': '450',
                'material_casco': 'Fibra de vidrio',
                'zona_navegacion': 'Aguas costeras',
                'fecha_adquisicion': new Date().toISOString().split('T')[0], // Fecha actual en formato YYYY-MM-DD
                'motor_marca': 'Mercury',
                'motor_modelo': 'F40',
                'motor_potencia': '40',
                'motor_num_serie': 'MERC12345Z',
                'combustible': 'gasolina',
                'num_motores': '1',
                'tipo_motor': 'fueraborda',
                
                // Datos auxiliares (opcionales)
                'aux_marca': 'Zodiac',
                'aux_modelo': 'Mini 250',
                'aux_categoria_diseno': 'D',
                'aux_num_serie': 'ZOD345678',
                'aux_eslora': '2.5',
                'aux_num_max_personas': '2',
                'aux_num_embarcaciones': '1',
                'aux_fecha_adquisicion': new Date().toISOString().split('T')[0],
                
                // Datos del propietario
                'propietario_nombre': 'Juan Antonio Pérez García',
                'propietario_nif': '48123456A',
                'propietario_via': 'Calle Principal',
                'propietario_numero': '25',
                'propietario_escalera': '1',
                'propietario_piso': '4',
                'propietario_puerta': 'B',
                'propietario_cp': '28001',
                'propietario_localidad': 'Madrid',
                'propietario_provincia': 'Madrid',
                'propietario_pais': 'España',
                'propietario_telefono': '910123456',
                'propietario_movil': '666123456',
                'propietario_email': 'joanpinyol@hotmail.es',
                
                // Datos de autorización
                'autorizacion_nombre': 'Juan Antonio Pérez García',
                'autorizacion_dni': '48123456A',
                
                // Datos de matriculación/listas
                'lista_matricula': '7ª',
                'nombre_propuesto': 'NEPTUNO',
                'lista_inscripcion': '7ª'
            };
            
            // Rellenar todos los campos de texto, número, email, tel, date, etc.
            const inputSelectors = 'input[type="text"], input[type="number"], input[type="email"], input[type="tel"], input[type="date"], textarea, select';
            document.querySelectorAll(inputSelectors).forEach(input => {
                if (testData[input.name]) {
                    input.value = testData[input.name];
                    // Disparar evento input y change
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });
            
            // Manejar radio buttons
            const radioGroups = ['tipo_embarcacion', 'combustible', 'tipo_motor'];
            radioGroups.forEach(group => {
                if (testData[group]) {
                    const radio = document.querySelector(`input[name="${group}"][value="${testData[group]}"]`);
                    if (radio) {
                        radio.checked = true;
                        radio.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                }
            });
            
            // Manejar checkboxes
            document.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
                // Marcar todos los checkboxes (normalmente son términos y condiciones)
                checkbox.checked = true;
                checkbox.dispatchEvent(new Event('change', { bubbles: true }));
            });
            
            // Generar una firma de prueba
            if (signaturePad) {
                // Generar una firma simple
                const canvas = document.getElementById('signature-pad');
                if (canvas) {
                    const ctx = canvas.getContext('2d');
                    if (ctx) {
                        // Limpiar primero
                        signaturePad.clear();
                        
                        // Obtener dimensiones
                        const width = canvas.width / (window.devicePixelRatio || 1);
                        const height = canvas.height / (window.devicePixelRatio || 1);
                        
                        // Dibujar firma simulada
                        ctx.beginPath();
                        ctx.moveTo(width * 0.2, height * 0.5);
                        ctx.bezierCurveTo(
                            width * 0.3, height * 0.3,
                            width * 0.5, height * 0.8,
                            width * 0.8, height * 0.2
                        );
                        ctx.lineWidth = 2;
                        ctx.strokeStyle = "black";
                        ctx.stroke();
                        
                        // Guardar firma
                        signaturePad._isEmpty = false;
                        mainSignatureData = signaturePad.toDataURL();
                        const signatureDataField = document.getElementById('signature_data');
                        if (signatureDataField) signatureDataField.value = mainSignatureData;
                        updateSignatureStatus();
                    }
                }
            }
            
            // Subir automáticamente en la página para ver el resultado
            window.scrollTo({ top: 0, behavior: 'smooth' });
            
            // Mostrar mensaje de éxito
            showNotification('Formulario rellenado completamente con datos de prueba', 'success');
        }
        
        function createTestDataButton() {
            if (<?php echo $is_test_mode ? 'true' : 'false'; ?>) {
                // Eliminar el botón si ya existe
                const existingButton = document.getElementById('fill-test-data');
                if (existingButton) existingButton.remove();
                
                // Crear nuevo botón de prueba
                const testButton = document.createElement('button');
                testButton.type = 'button';
                testButton.id = 'fill-test-data';
                testButton.className = 'button button-primary';
                testButton.style.position = 'fixed';
                testButton.style.bottom = '100px';
                testButton.style.right = '30px';
                testButton.style.zIndex = '2000';
                testButton.style.fontSize = '14px';
                testButton.style.padding = '12px 20px';
                testButton.style.borderRadius = '30px';
                testButton.style.boxShadow = '0 4px 10px rgba(0,0,0,0.2)';
                testButton.innerHTML = '<i class="fas fa-magic"></i> Rellenar Formulario';
                
                testButton.addEventListener('click', fillTestData);
                
                document.body.appendChild(testButton);
            }
        }

        function detectDevice() {
    try {
        const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        const isTablet = /iPad|Android(?!.*Mobile)/i.test(navigator.userAgent) || (isMobile && window.innerWidth > 768);
        const isPhone = isMobile && !isTablet;
        
        const desktopInstruction = document.getElementById('desktop-instruction');
        const tabletInstruction = document.getElementById('tablet-instruction');
        const mobileInstruction = document.getElementById('mobile-instruction');
        
        if (desktopInstruction) {
            desktopInstruction.style.display = (!isMobile && !isTablet) ? 'block' : 'none';
        }
        
        if (tabletInstruction) {
            tabletInstruction.style.display = isTablet ? 'block' : 'none';
        }
        
        if (mobileInstruction) {
            mobileInstruction.style.display = (isMobile && !isTablet) ? 'block' : 'none';
        }
        
        // Forzar firma en pantalla completa para cualquier dispositivo móvil
        if (isMobile) {
            // Ocultar el canvas principal y sus controles
            const mainCanvas = document.getElementById('signature-pad');
            if (mainCanvas) {
                mainCanvas.parentElement.style.pointerEvents = 'none';
                mainCanvas.style.opacity = '0.4';
            }
            
            // Ocultar el botón de limpiar firma del canvas principal
            const clearBtn = document.getElementById('clear-signature');
            if (clearBtn) {
                clearBtn.style.display = 'none';
            }
            
            // Mostrar mensaje de información
            const signatureInstructions = document.getElementById('signature-instructions');
            if (signatureInstructions) {
                signatureInstructions.innerHTML = '<strong>En dispositivos móviles:</strong> Por favor, use el botón "Firmar en pantalla completa" a continuación.';
                signatureInstructions.style.backgroundColor = '#fff3cd';
                signatureInstructions.style.padding = '10px';
                signatureInstructions.style.borderRadius = '4px';
                signatureInstructions.style.marginBottom = '15px';
            }
            
            // Resaltar el botón de firma a pantalla completa
            const zoomButton = document.getElementById('zoom-signature');
            if (zoomButton) {
                zoomButton.style.padding = '15px 20px';
                zoomButton.style.fontSize = '16px';
                zoomButton.style.backgroundColor = '#016d86';
                zoomButton.style.color = 'white';
                zoomButton.style.width = '100%';
                zoomButton.style.marginTop = '15px';
                zoomButton.style.borderRadius = '6px';
                zoomButton.style.boxShadow = '0 4px 10px rgba(1, 109, 134, 0.3)';
                zoomButton.style.animation = 'pulse 2s infinite';
                zoomButton.innerHTML = '<i class="fas fa-pen"></i> Pulsa aquí para firmar';
                
                // Agregar estilo para la animación de pulso
                if (!document.getElementById('pulse-animation-style')) {
                    const style = document.createElement('style');
                    style.id = 'pulse-animation-style';
                    style.textContent = `
                        @keyframes pulse {
                            0% { transform: scale(1); }
                            50% { transform: scale(1.05); }
                            100% { transform: scale(1); }
                        }
                    `;
                    document.head.appendChild(style);
                }
            }
        }
    } catch (err) {
        console.error('Error en detectDevice:', err);
    }
}
        function init() {
            if (checkPaymentRedirection()) return;
            
            initializeSignature();
            setupCollapsibleSections();
            setupHelpModal();
            setupTramiteSelection();
            setupChangeTramiteButton();
            
            // Cargar datos guardados
            loadProgress();
            
            // Colapsar todas las secciones por defecto
            document.querySelectorAll('.form-section').forEach(section => {
                if (!section.classList.contains('collapsed')) {
                    section.classList.add('collapsed');
                    const toggle = section.querySelector('.section-toggle');
                    if (toggle) toggle.style.transform = 'rotate(180deg)';
                }
            });
            
            // Crear botón de autorellenado para testing (al inicio y en cada cambio de página)
            createTestDataButton();
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') {
                    createTestDataButton();
                }
            });
            
            // Recrear el botón después de cualquier cambio de página
            navLinks.forEach(link => {
                link.addEventListener('click', () => {
                    setTimeout(createTestDataButton, 300);
                });
            });
            
            // Notificación de bienvenida
            setTimeout(function() {
                showNotification('Bienvenido al formulario de matriculación. Seleccione el tipo de trámite para comenzar.', 'info');
            }, 1000);
            
// Inicializar intervalos que mantengan la firma visible en móviles
if (/Android|webOS|iPhone|iPad|iPod/i.test(navigator.userAgent)) {
    // Intervalo general para todos los dispositivos móviles
    setInterval(() => {
        if (mainSignatureData && signaturePad && 
            document.getElementById('signature-pad')) {
            // En iOS, restaurar sin importar si está vacío o no
            if (/iPhone|iPad|iPod/i.test(navigator.userAgent)) {
                restoreSignature(document.getElementById('signature-pad'), signaturePad);
            }
            // En otros dispositivos, solo restaurar si está vacío
            else if (signaturePad._isEmpty) {
                restoreSignature(document.getElementById('signature-pad'), signaturePad);
            }
        }
    }, 500); // Intervalo más frecuente
    
    // Intervalo adicional específico para iOS (más agresivo)
    if (/iPhone|iPad|iPod/i.test(navigator.userAgent)) {
        setInterval(() => {
            if (mainSignatureData && !isScrolling && document.getElementById('signature-pad') && signaturePad) {
                restoreSignature(document.getElementById('signature-pad'), signaturePad);
            }
        }, 200); // Intervalo muy frecuente para iOS
    }
}
            
            // Detectar cuando un campo recibe el focus y restaurar firma si está en móvil
            const allInputs = document.querySelectorAll('input, select, textarea');
            allInputs.forEach(input => {
                input.addEventListener('focus', () => {
                    if (/Android|webOS|iPhone|iPad|iPod/i.test(navigator.userAgent) && 
                        mainSignatureData && signaturePad && document.getElementById('signature-pad')) {
                        setTimeout(() => {
                            restoreSignature(document.getElementById('signature-pad'), signaturePad);
                        }, 100);
                    }
                });
            });
        }
        
 // Resto del código existente...
    
 init();
    
   // Function to hide WhatsApp widget when signature modal is open
function handleSignatureModalVisibility() {
    // Select the signature modals (both original and enhanced version)
    const originalModal = document.getElementById('signature-modal');
    const enhancedModal = document.getElementById('signature-modal-advanced');
    
    // Select common WhatsApp widget selectors (covering most implementations)
    const whatsappWidgets = [
        // WhatsApp widgets
        '.nta_wa_button', '.wa__btn_popup', '.wa__popup_chat_box', '.wa__popup_content',
        '.wa__popup_content_item', '.wa__popup_content_list', '.nta-wa-gdpr', '#nta-wa-gdpr',
        '.nta-whatsapp-popup', '.nta-whatsapp-button', '[data-nta-whatsapp-plugin]',
        '#wa-chat-btn-root', '.wa__popover', '.wa__btn_popup_icon', '.wa-chat-box',
        // Elementos específicos del plugin WhatsApp común
        '[class*="whatsapp"]', '[id*="whatsapp"]', '[class*="wa-"]', '[id*="wa-"]',
        // Otros chats
        '.fb_dialog', '.fb-customerchat', '.crisp-client', '.intercom-frame',
        '.drift-frame', '#hubspot-messages-iframe-container', '#chat-widget-container',
        '.tidio-chat-container', '#tidio-chat', '#WhatsHelp', '.wh-widget-button',
        '.chat-widget', '.widget-visible', '[class*="chat"]', '[id*="chat"]'
    ];
    
    // Almacenar los valores originales de display
    const originalDisplayValues = new Map();
    
    // Función para ocultar widgets - versión más agresiva
    function hideWidgets() {
        console.log("Ocultando widgets de chat...");
        
        // Ocultar primero por selectores específicos
        whatsappWidgets.forEach(selector => {
            try {
                const elements = document.querySelectorAll(selector);
                elements.forEach(el => {
                    // Guardar estilo display original
                    if (!originalDisplayValues.has(el) && el.style.display !== 'none') {
                        const computedStyle = window.getComputedStyle(el);
                        originalDisplayValues.set(el, computedStyle.display);
                    }
                    
                    // Ocultar con múltiples enfoques
                    el.style.display = 'none';
                    el.style.visibility = 'hidden';
                    el.style.opacity = '0';
                    el.style.pointerEvents = 'none';
                    el.classList.add('hidden-during-signature');
                });
            } catch (e) {
                console.error("Error ocultando selector: " + selector, e);
            }
        });
        
        // Método adicional: buscar elementos por palabras clave en el DOM
        ['whatsapp', 'wa-', 'chat', 'widget'].forEach(keyword => {
            document.querySelectorAll('*').forEach(el => {
                try {
                    // Si el elemento o sus clases contienen la palabra clave
                    if ((el.id && el.id.toLowerCase().includes(keyword)) || 
                        (el.className && typeof el.className === 'string' && 
                         el.className.toLowerCase().includes(keyword))) {
                        
                        // Guardar estilo original
                        if (!originalDisplayValues.has(el) && el.style.display !== 'none') {
                            const computedStyle = window.getComputedStyle(el);
                            originalDisplayValues.set(el, computedStyle.display);
                        }
                        
                        // Ocultar elemento
                        el.style.display = 'none';
                        el.style.visibility = 'hidden';
                        el.classList.add('hidden-during-signature');
                    }
                } catch (e) {}
            });
        });
    }
    
    // Función para restaurar widgets
    function restoreWidgets() {
        console.log("Restaurando widgets de chat...");
        document.querySelectorAll('.hidden-during-signature').forEach(el => {
            // Restaurar con el valor original si fue guardado
            if (originalDisplayValues.has(el)) {
                el.style.display = originalDisplayValues.get(el);
            } else {
                // Valor por defecto si no tenemos el original
                el.style.display = '';
            }
            el.style.visibility = '';
            el.style.opacity = '';
            el.style.pointerEvents = '';
            el.classList.remove('hidden-during-signature');
        });
    }
    
    // Aplicar de forma inmediata al abrir modales
    // 1. Para el modal original
    if (originalModal) {
        // Observar cambios en el estilo para mostrar/ocultar
        const observer1 = new MutationObserver((mutations) => {
            const isVisible = window.getComputedStyle(originalModal).display !== 'none';
            if (isVisible) {
                hideWidgets();
            } else {
                // Usar múltiples intentos con distintos tiempos para asegurar restauración
                setTimeout(restoreWidgets, 100);
                setTimeout(restoreWidgets, 500);
            }
        });
        observer1.observe(originalModal, { attributes: true, attributeFilter: ['style'] });
        
        // Capturar cierres por botón
        const closeButton = originalModal.querySelector('.close-signature-modal');
        if (closeButton) {
            closeButton.addEventListener('click', () => {
                setTimeout(restoreWidgets, 100);
                setTimeout(restoreWidgets, 500);
            });
        }
    }
    
    // 2. Para el modal mejorado
    if (enhancedModal) {
        const observer2 = new MutationObserver((mutations) => {
            const isVisible = window.getComputedStyle(enhancedModal).display !== 'none';
            if (isVisible) {
                hideWidgets();
            } else {
                setTimeout(restoreWidgets, 100);
                setTimeout(restoreWidgets, 500);
            }
        });
        observer2.observe(enhancedModal, { attributes: true, attributeFilter: ['style'] });
        
        // Capturar cierres por botón
        const closeButton = enhancedModal.querySelector('.enhanced-close-button');
        if (closeButton) {
            closeButton.addEventListener('click', () => {
                setTimeout(restoreWidgets, 100);
                setTimeout(restoreWidgets, 500);
            });
        }
    }
    
    // Capturar el botón que abre la firma a pantalla completa
    const zoomButton = document.getElementById('zoom-signature');
    if (zoomButton) {
        // Ocultar de forma proactiva al hacer clic en el botón
        zoomButton.addEventListener('click', () => {
            console.log("Botón de firma clickeado - ocultando widgets");
            // Ocultar inmediatamente y en intervalos para asegurar
            hideWidgets();
            setTimeout(hideWidgets, 100);
            setTimeout(hideWidgets, 300);
        });
    }
    
    // Capturar clics en el fondo para detectar cierres
    document.body.addEventListener('click', (e) => {
        if ((enhancedModal && e.target === enhancedModal) || 
            (originalModal && e.target === originalModal)) {
            setTimeout(restoreWidgets, 100);
            setTimeout(restoreWidgets, 500);
        }
    });
    
    // Capturar botón de aceptar firma
    const acceptButton = document.getElementById('modal-accept-signature');
    if (acceptButton) {
        acceptButton.addEventListener('click', () => {
            setTimeout(restoreWidgets, 100);
            setTimeout(restoreWidgets, 500);
        });
    }
    
    // Manejar cambios de orientación
    window.addEventListener('orientationchange', () => {
        setTimeout(() => {
            const originalModalVisible = originalModal && 
                window.getComputedStyle(originalModal).display !== 'none';
            const enhancedModalVisible = enhancedModal && 
                window.getComputedStyle(enhancedModal).display !== 'none';
                
            if (originalModalVisible || enhancedModalVisible) {
                hideWidgets();
            } else {
                restoreWidgets();
            }
        }, 500);
    });
    
    // Ocultar proactivamente al inicio si algún modal está abierto
    setTimeout(() => {
        const originalModalVisible = originalModal && 
            window.getComputedStyle(originalModal).display !== 'none';
        const enhancedModalVisible = enhancedModal && 
            window.getComputedStyle(enhancedModal).display !== 'none';
            
        if (originalModalVisible || enhancedModalVisible) {
            hideWidgets();
        }
    }, 500);
}
    
    // Initialize when signature functionality is loaded
    function initWhenSignatureReady() {
        // Check if signature modal exists
        if (document.getElementById('signature-modal') || 
            document.getElementById('signature-modal-advanced')) {
            handleSignatureModalVisibility();
        } else {
            // If not found yet, try again in a second (form might still be loading)
            setTimeout(initWhenSignatureReady, 1000);
        }
    }
    
    initWhenSignatureReady();
    
    });
    </script>
    <?php
    return ob_get_clean();
}

add_action('wp_ajax_create_payment_intent_matriculacion', 'create_payment_intent_matriculacion');
add_action('wp_ajax_nopriv_create_payment_intent_matriculacion', 'create_payment_intent_matriculacion');

function create_payment_intent_matriculacion() {
    global $is_test_mode, $secret_key_test, $secret_key_live;
    require_once __DIR__ . '/vendor/stripe/stripe-php/init.php';

    if ($is_test_mode) {
        \Stripe\Stripe::setApiKey($secret_key_test);
    } else {
        \Stripe\Stripe::setApiKey($secret_key_live);
    }

    $amount = isset($_POST['amount']) ? intval($_POST['amount']) : 0;
    
    // Obtener metadatos opcionales si están presentes
    $tramite_id = isset($_POST['tramite_id']) ? sanitize_text_field($_POST['tramite_id']) : '';
    $tipo_tramite = isset($_POST['tipo_tramite']) ? sanitize_text_field($_POST['tipo_tramite']) : '';
    $marca = isset($_POST['marca']) ? sanitize_text_field($_POST['marca']) : '';
    $modelo = isset($_POST['modelo']) ? sanitize_text_field($_POST['modelo']) : '';
    $coupon = isset($_POST['coupon']) ? sanitize_text_field($_POST['coupon']) : '';

    try {
        $metadata = [
            'source' => 'matriculacion_form',
            'timestamp' => time()
        ];
        
        // Añadir datos adicionales si están disponibles
        if (!empty($tramite_id)) $metadata['tramite_id'] = $tramite_id;
        if (!empty($tipo_tramite)) $metadata['tipo_tramite'] = $tipo_tramite;
        if (!empty($marca)) $metadata['marca_embarcacion'] = $marca;
        if (!empty($modelo)) $metadata['modelo_embarcacion'] = $modelo;
        if (!empty($coupon)) $metadata['coupon_applied'] = $coupon;

        $paymentIntent = \Stripe\PaymentIntent::create([
            'amount' => $amount,
            'currency' => 'eur',
            'payment_method_types' => ['card'],
            'description' => 'Trámite de matriculación',
            'metadata' => $metadata
        ]);

        echo json_encode([
            'clientSecret' => $paymentIntent->client_secret,
            'id' => $paymentIntent->id
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'error' => $e->getMessage(),
        ]);
    }

    wp_die();
}

function check_payment_intent_status() {
    global $is_test_mode, $secret_key_test, $secret_key_live;
    require_once __DIR__ . '/vendor/stripe/stripe-php/init.php';

    if ($is_test_mode) {
        \Stripe\Stripe::setApiKey($secret_key_test);
    } else {
        \Stripe\Stripe::setApiKey($secret_key_live);
    }

    $payment_intent_id = isset($_POST['payment_intent_id']) ? sanitize_text_field($_POST['payment_intent_id']) : '';
    
    if (empty($payment_intent_id)) {
        wp_send_json_error('No payment intent ID provided');
    }

    try {
        $paymentIntent = \Stripe\PaymentIntent::retrieve($payment_intent_id);
        
        wp_send_json_success([
            'status' => $paymentIntent->status,
            'id' => $paymentIntent->id
        ]);
    } catch (Exception $e) {
        wp_send_json_error($e->getMessage());
    }

    wp_die();
}

add_action('wp_ajax_guardar_datos_tramite_temp', 'guardar_datos_tramite_temp');
add_action('wp_ajax_nopriv_guardar_datos_tramite_temp', 'guardar_datos_tramite_temp');

function guardar_datos_tramite_temp() {
    if (isset($_POST['tramite_id']) && isset($_POST['data'])) {
        $tramite_id = sanitize_text_field($_POST['tramite_id']);
        $data = json_decode(stripslashes($_POST['data']), true);
        
        // Guardar datos temporalmente (24 horas)
        set_transient('tramite_temp_' . $tramite_id, $data, 24 * HOUR_IN_SECONDS);
        
        wp_send_json_success();
    } else {
        wp_send_json_error('Datos insuficientes');
    }
    wp_die();
}
add_action('wp_ajax_enviar_correo_confirmacion', 'enviar_correo_confirmacion');
add_action('wp_ajax_nopriv_enviar_correo_confirmacion', 'enviar_correo_confirmacion');

function enviar_correo_confirmacion() {
    if (!isset($_POST['tramite_id'])) {
        wp_send_json_error('No se proporcionó ID de trámite');
    }
    
    $tramite_id = sanitize_text_field($_POST['tramite_id']);
    $tramite_data = get_transient('tramite_temp_' . $tramite_id);
    
    if (!$tramite_data) {
        // Intentar obtener datos mínimos de Stripe si es posible
        global $is_test_mode, $secret_key_test, $secret_key_live;
        try {
            require_once __DIR__ . '/vendor/stripe/stripe-php/init.php';
            \Stripe\Stripe::setApiKey($is_test_mode ? $secret_key_test : $secret_key_live);
            
            // Buscar pagos recientes con este tramite_id
            $payments = \Stripe\PaymentIntent::all([
                'limit' => 1,
                'created' => ['gte' => time() - 86400], // Últimas 24 horas
            ]);
            
            foreach ($payments->data as $payment) {
                if (isset($payment->metadata->tramite_id) && $payment->metadata->tramite_id === $tramite_id) {
                    // Crear datos básicos del pago
                    $tramite_data = [
                        'email' => $payment->receipt_email,
                        'tipo_tramite' => $payment->metadata->tipo_tramite ?? 'matriculación',
                        'marca' => $payment->metadata->marca_embarcacion ?? '',
                        'modelo' => $payment->metadata->modelo_embarcacion ?? '',
                        'precio_final' => $payment->amount / 100
                    ];
                    break;
                }
            }
        } catch (Exception $e) {
            error_log('Error al recuperar datos de Stripe: ' . $e->getMessage());
        }
        
        if (!$tramite_data) {
            wp_send_json_error('No se encontraron datos del trámite');
            wp_die();
        }
    }
    
    // Enviar correo de confirmación
    $cliente_email = $tramite_data['email'];
    $tipo_tramite = $tramite_data['tipo_tramite'] ?? 'matriculación';
    $marca = $tramite_data['marca'] ?? '';
    $modelo = $tramite_data['modelo'] ?? '';
    $precio_final = $tramite_data['precio_final'] ?? '99.99';
    
    $headers = array('Content-Type: text/html; charset=UTF-8');
    
    $cliente_subject = 'Confirmación de su trámite de ' . 
        ($tipo_tramite == 'abanderamiento' ? 'Matriculación/Abanderamiento' : 'Inscripción') . 
        ' - Ref: ' . $tramite_id;
    
    // Usar la misma plantilla de correo que en submit_form_matriculacion
    $cliente_message = '
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmación de Trámite</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
        }
        .header {
            background-color: #016d86;
            color: white;
            padding: 20px;
            text-align: center;
        }
        .content {
            padding: 20px;
            border: 1px solid #e0e0e0;
            border-top: none;
        }
        .footer {
            background-color: #f8f9fa;
            padding: 15px;
            text-align: center;
            font-size: 12px;
            color: #666;
            border: 1px solid #e0e0e0;
            border-top: none;
        }
        .tramite-info {
            background-color: #f8f9fa;
            padding: 15px;
            margin: 20px 0;
            border-left: 4px solid #016d86;
        }
        .button {
            display: inline-block;
            background-color: #016d86;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 20px;
        }
        h2 {
            color: #016d86;
        }
        .divider {
            border-top: 1px solid #e0e0e0;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Tramitfy</h1>
        <p>Su gestor náutico de confianza</p>
    </div>
    
    <div class="content">
        <h2>¡Gracias por confiar en nosotros!</h2>
        
        <p>Estimado/a cliente,</p>
        
        <p>Hemos recibido correctamente su solicitud de <strong>' . ($tipo_tramite == 'abanderamiento' ? 'Matriculación/Abanderamiento' : 'Inscripción') . '</strong> para su embarcación. Su trámite ya está siendo procesado por nuestro equipo de gestores profesionales.</p>
        
        <div class="tramite-info">
            <h3>Detalles del trámite:</h3>
            <p><strong>Número de referencia:</strong> ' . $tramite_id . '</p>
            <p><strong>Fecha de solicitud:</strong> ' . date('d/m/Y') . '</p>
            <p><strong>Embarcación:</strong> ' . $marca . ' ' . $modelo . '</p>
            <p><strong>Tipo de trámite:</strong> ' . ($tipo_tramite == 'abanderamiento' ? 'Matriculación/Abanderamiento' : 'Inscripción') . '</p>
            <p><strong>Importe total:</strong> ' . number_format((float)$precio_final, 2) . ' €</p>
        </div>
        
        <h3>Próximos pasos:</h3>
        
        <ol>
            <li>Nuestro departamento de gestión revisará la documentación aportada en un plazo de 24-48 horas laborables.</li>
            <li>Si fuera necesaria alguna documentación adicional, nos pondremos en contacto con usted.</li>
            <li>Una vez validados los documentos, iniciaremos los trámites con la administración competente.</li>
            <li>Le mantendremos informado del progreso mediante correo electrónico.</li>
        </ol>
        
        <p>Si tiene cualquier duda o consulta sobre su trámite, puede responder directamente a este correo o contactarnos a través de los canales que encontrará a continuación.</p>
        
        <div class="divider"></div>
        
        <p>Recuerde guardar el número de referencia de su trámite para futuras consultas: <strong>' . $tramite_id . '</strong></p>
        
        <a href="https://tramitfy.es/mi-cuenta/" class="button">Acceder a mi cuenta</a>
        
        <p style="margin-top: 30px;">Atentamente,</p>
        <p><strong>El equipo de Tramitfy</strong><br>
        Su gestor náutico de confianza</p>
    </div>
    
    <div class="footer">
        <p>Tramitfy S.L. - CIF B12345678</p>
        <p>Calle Principal 123, 28001 Madrid</p>
        <p>Email: info@tramitfy.es | Teléfono: 910 123 456</p>
        <p>&copy; ' . date('Y') . ' Tramitfy. Todos los derechos reservados.</p>
    </div>
</body>
</html>
';
    
    $mail_sent = wp_mail($cliente_email, $cliente_subject, $cliente_message, $headers);
    
    if ($mail_sent) {
        // Limpiar datos temporales
        delete_transient('tramite_temp_' . $tramite_id);
        wp_send_json_success(['message' => 'Correo enviado correctamente']);
    } else {
        wp_send_json_error(['message' => 'Error al enviar el correo']);
    }
    
    wp_die();
}
add_action('wp_ajax_validate_coupon_code_matriculacion', 'validate_coupon_code_matriculacion');
add_action('wp_ajax_nopriv_validate_coupon_code_matriculacion', 'validate_coupon_code_matriculacion');

function validate_coupon_code_matriculacion() {
    $valid_coupons = array(
        'MATRICULA10' => 10,
        'MATRICULA20' => 20,
        'VERANO15'    => 15,
        'BLACK50'     => 50,
    );

    $coupon = isset($_POST['coupon']) ? sanitize_text_field($_POST['coupon']) : '';
    $coupon_upper = strtoupper($coupon);

    if (isset($valid_coupons[$coupon_upper])) {
        $discount_percent = $valid_coupons[$coupon_upper];
        wp_send_json_success(['discount_percent' => $discount_percent]);
    } else {
        wp_send_json_error('Cupón inválido o expirado');
    }
    wp_die();
}

add_action('wp_ajax_check_payment_intent_status', 'check_payment_intent_status');
add_action('wp_ajax_nopriv_check_payment_intent_status', 'check_payment_intent_status');

add_action('wp_ajax_submit_form_matriculacion', 'submit_form_matriculacion');
add_action('wp_ajax_nopriv_submit_form_matriculacion', 'submit_form_matriculacion');

function submit_form_matriculacion() {
    $tipo_tramite = sanitize_text_field($_POST['tipo_tramite']);
    $tramite_id = sanitize_text_field($_POST['tramite_id']);
    $marca = sanitize_text_field($_POST['marca']);
    $modelo = sanitize_text_field($_POST['modelo']);
    $tipo_embarcacion = sanitize_text_field($_POST['tipo_embarcacion']);
    $propietario_nombre = sanitize_text_field($_POST['propietario_nombre']);
    $propietario_nif = sanitize_text_field($_POST['propietario_nif']);
    $propietario_email = sanitize_email($_POST['propietario_email']);
    $coupon_used = isset($_POST['coupon_used']) ? sanitize_text_field($_POST['coupon_used']) : '';
    
    // Crear directorios para guardar archivos del cliente
    $upload_dir = wp_upload_dir();
    $client_data_dir = $upload_dir['basedir'] . '/client_data';
    if (!file_exists($client_data_dir)) {
        wp_mkdir_p($client_data_dir);
    }
    
    // Guardar firma como imagen
    $signature = isset($_POST['signature']) ? $_POST['signature'] : '';
    $signature_path = '';
    if (!empty($signature)) {
        $signature_data = str_replace('data:image/png;base64,', '', $signature);
        $signature_data = base64_decode($signature_data);
        
        $signature_image_name = 'firma_matriculacion_' . time() . '.png';
        $signature_image_path = $client_data_dir . '/' . $signature_image_name;
        file_put_contents($signature_image_path, $signature_data);
        $signature_path = $signature_image_path;
    }
    
    // Manejar archivos subidos
    $uploaded_files_paths = [];
    foreach ($_FILES as $fileKey => $file) {
        if ($file['error'] === UPLOAD_ERR_OK) {
            $filename = sanitize_file_name($file['name']);
            $target_path = $client_data_dir . '/' . $filename;
            if (move_uploaded_file($file['tmp_name'], $target_path)) {
                $uploaded_files_paths[$fileKey] = $target_path;
            }
        }
    }
    
    // Subir archivos a Google Drive
    try {
        require_once __DIR__ . '/vendor/autoload.php';
        
        // Configuración de Google Drive
        $googleCredentialsPath = __DIR__ . '/credentials.json';
        $client = new Google_Client();
        $client->setAuthConfig($googleCredentialsPath);
        $client->addScope(Google_Service_Drive::DRIVE_FILE);
        
        $driveService = new Google_Service_Drive($client);
        
        // ID de la carpeta padre en Drive
        $parentFolderId = '1vxHdQImalnDVI7aTaE0cGIX7m-7pl7sr'; // Ajustar con tu carpeta real
        
        // Crear o reutilizar carpeta del mes actual (AAAA-MM)
        $yearMonth = date('Y-m');
        $folderId = null;
        
        try {
            $query = sprintf(
                "name = '%s' and '%s' in parents and mimeType = 'application/vnd.google-apps.folder' and trashed=false",
                $yearMonth,
                $parentFolderId
            );
            $response = $driveService->files->listFiles([
                'q' => $query,
                'spaces' => 'drive',
                'fields' => 'files(id, name)'
            ]);
            
            if (count($response->files) > 0) {
                // Carpeta existente
                $folderId = $response->files[0]->id;
            } else {
                // Crear nueva carpeta
                $folderMetadata = new Google_Service_Drive_DriveFile([
                    'name' => $yearMonth,
                    'mimeType' => 'application/vnd.google-apps.folder',
                    'parents' => [$parentFolderId]
                ]);
                $createdFolder = $driveService->files->create($folderMetadata, ['fields' => 'id']);
                $folderId = $createdFolder->id;
            }
        } catch (Exception $e) {
            $folderId = $parentFolderId;
        }
        
        // Subir firma y documentos a Drive
        $driveFileLinks = [];
        
        // Subir firma
        if (!empty($signature_path)) {
            $driveFile = new Google_Service_Drive_DriveFile([
                'name' => 'Firma_' . $tramite_id . '.png',
                'parents' => [$folderId]
            ]);
            
            $fileContent = file_get_contents($signature_path);
            $createdFile = $driveService->files->create($driveFile, [
                'data' => $fileContent,
                'mimeType' => 'image/png',
                'uploadType' => 'multipart',
                'fields' => 'id, webViewLink'
            ]);
            
            // Dar permiso de lectura
            $permission = new Google_Service_Drive_Permission();
            $permission->setType('anyone');
            $permission->setRole('reader');
            $driveService->permissions->create($createdFile->id, $permission);
            
            $driveFileLinks['firma'] = $createdFile->webViewLink;
        }
        
        // Subir documentos
        foreach ($uploaded_files_paths as $fileKey => $filePath) {
            $fileName = basename($filePath);
            $driveFileName = $fileKey . '_' . $tramite_id . '_' . $fileName;
            
            $driveFile = new Google_Service_Drive_DriveFile([
                'name' => $driveFileName,
                'parents' => [$folderId]
            ]);
            
            $fileContent = file_get_contents($filePath);
            $mimeType = mime_content_type($filePath);
            
            $createdFile = $driveService->files->create($driveFile, [
                'data' => $fileContent,
                'mimeType' => $mimeType,
                'uploadType' => 'multipart',
                'fields' => 'id, webViewLink'
            ]);
            
            // Dar permiso de lectura
            $permission = new Google_Service_Drive_Permission();
            $permission->setType('anyone');
            $permission->setRole('reader');
            $driveService->permissions->create($createdFile->id, $permission);
            
            $driveFileLinks[$fileKey] = $createdFile->webViewLink;
        }
        
// Guardar en Google Sheets
// Guardar en Google Sheets
$sheetsClient = new Google_Client();
$sheetsClient->setAuthConfig($googleCredentialsPath);
$sheetsClient->addScope(Google_Service_Sheets::SPREADSHEETS);

$sheetsService = new Google_Service_Sheets($sheetsClient);

// ID de la hoja de cálculo
$spreadsheetId = '1APFnwJ3yBfxt1M4JJcfPLOQkdIF27OXAzubW1Bx9ZbA';

// Recopilar todos los datos relevantes
$propietario_telefono = isset($_POST['propietario_telefono']) ? sanitize_text_field($_POST['propietario_telefono']) : '';
$propietario_movil = isset($_POST['propietario_movil']) ? sanitize_text_field($_POST['propietario_movil']) : '';
$propietario_via = isset($_POST['propietario_via']) ? sanitize_text_field($_POST['propietario_via']) : '';
$propietario_numero = isset($_POST['propietario_numero']) ? sanitize_text_field($_POST['propietario_numero']) : '';
$propietario_cp = isset($_POST['propietario_cp']) ? sanitize_text_field($_POST['propietario_cp']) : '';
$propietario_localidad = isset($_POST['propietario_localidad']) ? sanitize_text_field($_POST['propietario_localidad']) : '';
$provincia = isset($_POST['propietario_provincia']) ? sanitize_text_field($_POST['propietario_provincia']) : '';
$propietario_pais = isset($_POST['propietario_pais']) ? sanitize_text_field($_POST['propietario_pais']) : '';
$categoria_diseno = isset($_POST['categoria_diseno']) ? sanitize_text_field($_POST['categoria_diseno']) : '';
$num_serie = isset($_POST['num_serie']) ? sanitize_text_field($_POST['num_serie']) : '';
$eslora = isset($_POST['eslora']) ? sanitize_text_field($_POST['eslora']) : '';
$manga = isset($_POST['manga']) ? sanitize_text_field($_POST['manga']) : '';
$num_max_personas = isset($_POST['num_max_personas']) ? sanitize_text_field($_POST['num_max_personas']) : '';
$fecha_adquisicion = isset($_POST['fecha_adquisicion']) ? sanitize_text_field($_POST['fecha_adquisicion']) : '';
$precio_compra = isset($_POST['precio_compra']) ? sanitize_text_field($_POST['precio_compra']) : '';
$motor_marca = isset($_POST['motor_marca']) ? sanitize_text_field($_POST['motor_marca']) : '';
$motor_modelo = isset($_POST['motor_modelo']) ? sanitize_text_field($_POST['motor_modelo']) : '';
$motor_potencia = isset($_POST['motor_potencia']) ? sanitize_text_field($_POST['motor_potencia']) : '';
$motor_num_serie = isset($_POST['motor_num_serie']) ? sanitize_text_field($_POST['motor_num_serie']) : '';
$nombre_propuesto = isset($_POST['nombre_propuesto']) ? sanitize_text_field($_POST['nombre_propuesto']) : '';
$nuevo_puerto = isset($_POST['lista_matricula']) ? sanitize_text_field($_POST['lista_matricula']) : '';
$precio_final = isset($_POST['precio_final']) ? floatval($_POST['precio_final']) : 0;
$tasas = isset($_POST['tasas_hidden']) ? floatval($_POST['tasas_hidden']) : 0;
$iva = isset($_POST['iva_hidden']) ? floatval($_POST['iva_hidden']) : 0;
$honorarios = isset($_POST['honorarios_hidden']) ? floatval($_POST['honorarios_hidden']) : 0;
$itp = isset($_POST['itp']) ? floatval($_POST['itp']) : 0;
$userIP = $_SERVER['REMOTE_ADDR'];
$userAgent = $_SERVER['HTTP_USER_AGENT'];
$fecha = date('d/m/Y');
$fechaHora = date('d/m/Y H:i:s');

// Preparar enlaces de documentos y firma
$documentLinks = '';
$firma_link = isset($driveFileLinks['firma']) ? $driveFileLinks['firma'] : '';
foreach ($driveFileLinks as $key => $link) {
    if ($key !== 'firma') {
        $documentLinks .= "{$key}: {$link}\n";
    }
}

// Datos para DATABASE - guardar todos los datos en una única hoja
$databaseData = [
    // Datos de Trámite ID
    $tramite_id,                                      // Trámite ID
    $fecha,                                           // Fecha 
    $tipo_tramite,                                    // Tipo Trámite
    'Pendiente',                                      // Estado
    
    // Datos de CLIENT DATA
    $propietario_nombre,                              // Nombre
    $propietario_nif,                                 // NIF/CIF
    $propietario_email,                               // Email
    $propietario_telefono,                            // Teléfono
    $propietario_movil,                               // Móvil
    $propietario_via . ' ' . $propietario_numero,     // Dirección
    $propietario_cp,                                  // Código Postal
    $propietario_localidad,                           // Localidad
    $provincia,                                       // Provincia
    $propietario_pais,                                // País
    
    // Datos de BOAT DATA
    $marca,                                           // Marca
    $modelo,                                          // Modelo
    $tipo_embarcacion,                                // Tipo
    $categoria_diseno,                                // Categoría Diseño
    $num_serie,                                       // Nº Serie
    $eslora,                                          // Eslora
    $manga,                                           // Manga
    $num_max_personas,                                // Nº Personas
    $motor_marca,                                     // Marca Motor
    $motor_modelo,                                    // Modelo Motor
    $motor_potencia,                                  // Potencia
    $motor_num_serie,                                 // Nº Serie Motor
    $nombre_propuesto,                                // Nombre Propuesto
    $nuevo_puerto,                                    // Puerto
    
    // Datos de CONTABLE DATA
    $precio_final,                                    // Precio Final
    $tasas,                                           // Tasas
    $honorarios,                                      // Honorarios
    $iva,                                             // IVA
    $coupon_used,                                     // Cupón
    'Stripe',                                         // Método Pago
    'Pagado',                                         // Estado Pago
    
    // Datos de VISITORS
    $userIP,                                          // IP
    $userAgent,                                       // User Agent
    
    // Datos de LINKED DOCUMENTS
    $firma_link,                                      // Firma Link
    $documentLinks,                                   // Document Links
    
    // Datos de EXTRACT DATA
    $fecha_adquisicion,                               // Fecha Adquisición
    $precio_compra,                                   // Precio Compra
    $itp                                              // ITP
];

// Guardar en DATABASE
$valueRange = new Google_Service_Sheets_ValueRange();
$valueRange->setValues([$databaseData]);

$sheetsService->spreadsheets_values->append(
    $spreadsheetId,
    'DATABASE!A:AO',
    $valueRange,
    ['valueInputOption' => 'USER_ENTERED']
);

// También guardar en OrganizedData
$organizedData = [
    $tramite_id,                            // ID Trámite
    $propietario_nombre,                    // Nombre
    $propietario_nif,                       // DNI
    $propietario_email,                     // Email
    $propietario_telefono ?: $propietario_movil, // Teléfono
    $tipo_embarcacion,                      // Tipo de Vehículo
    $marca,                                 // Fabricante
    $modelo,                                // Modelo
    $fecha_adquisicion,                     // Fecha de Matriculación
    $precio_compra,                         // Precio de Compra
    $provincia,                             // Comunidad Autónoma
    $coupon_used,                           // Cupón Aplicado
    $nombre_propuesto,                      // Nuevo Nombre
    $nuevo_puerto,                          // Nuevo Puerto
    $precio_final,                          // Importe Total
    $itp,                                   // ITP
    $tasas,                                 // Tasas
    $iva,                                   // IVA
    $honorarios,                            // Honorarios
    $firma_link                             // Enlace firma
];

// Agregar enlaces de documentos
foreach ($driveFileLinks as $key => $link) {
    if ($key !== 'firma') {
        $organizedData[] = $link;  // Agregar enlace del documento
    }
}

$valueRange = new Google_Service_Sheets_ValueRange();
$valueRange->setValues([$organizedData]);

$sheetsService->spreadsheets_values->append(
    $spreadsheetId,
    'OrganizedData!A:Z',
    $valueRange,
    ['valueInputOption' => 'USER_ENTERED']
);
        
    } catch (Exception $e) {
        // Continuar incluso si hay error en Google Drive/Sheets
        error_log('Error en Google APIs: ' . $e->getMessage());
    }
    
    // Enviar correo de confirmación
    $admin_email = get_option('admin_email');
    $cliente_email = $propietario_email;
    
    $headers = array('Content-Type: text/html; charset=UTF-8');
    
// Correo para el cliente
$cliente_subject = 'Confirmación de su trámite de ' . ($tipo_tramite == 'abanderamiento' ? 'Matriculación/Abanderamiento' : 'Inscripción') . ' - Ref: ' . $tramite_id;

// Crear un email más profesional con diseño HTML
$cliente_message = '
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmación de Trámite</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
        }
        .header {
            background-color: #016d86;
            color: white;
            padding: 20px;
            text-align: center;
        }
        .content {
            padding: 20px;
            border: 1px solid #e0e0e0;
            border-top: none;
        }
        .footer {
            background-color: #f8f9fa;
            padding: 15px;
            text-align: center;
            font-size: 12px;
            color: #666;
            border: 1px solid #e0e0e0;
            border-top: none;
        }
        .tramite-info {
            background-color: #f8f9fa;
            padding: 15px;
            margin: 20px 0;
            border-left: 4px solid #016d86;
        }
        .button {
            display: inline-block;
            background-color: #016d86;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 20px;
        }
        h2 {
            color: #016d86;
        }
        .divider {
            border-top: 1px solid #e0e0e0;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Tramitfy</h1>
        <p>Su gestor náutico de confianza</p>
    </div>
    
    <div class="content">
        <h2>¡Gracias por confiar en nosotros!</h2>
        
        <p>Estimado/a <strong>' . $propietario_nombre . '</strong>,</p>
        
        <p>Hemos recibido correctamente su solicitud de <strong>' . ($tipo_tramite == 'abanderamiento' ? 'Matriculación/Abanderamiento' : 'Inscripción') . '</strong> para su embarcación. Su trámite ya está siendo procesado por nuestro equipo de gestores profesionales.</p>
        
        <div class="tramite-info">
            <h3>Detalles del trámite:</h3>
            <p><strong>Número de referencia:</strong> ' . $tramite_id . '</p>
            <p><strong>Fecha de solicitud:</strong> ' . date('d/m/Y') . '</p>
            <p><strong>Embarcación:</strong> ' . $marca . ' ' . $modelo . '</p>
            <p><strong>Propietario:</strong> ' . $propietario_nombre . '</p>
            <p><strong>Tipo de trámite:</strong> ' . ($tipo_tramite == 'abanderamiento' ? 'Matriculación/Abanderamiento' : 'Inscripción') . '</p>
            <p><strong>Importe total:</strong> ' . number_format($precio_final, 2) . ' €</p>
        </div>
        
        <h3>Próximos pasos:</h3>
        
        <ol>
            <li>Nuestro departamento de gestión revisará la documentación aportada en un plazo de 24-48 horas laborables.</li>
            <li>Si fuera necesaria alguna documentación adicional, nos pondremos en contacto con usted.</li>
            <li>Una vez validados los documentos, iniciaremos los trámites con la administración competente.</li>
            <li>Le mantendremos informado del progreso mediante correo electrónico.</li>
        </ol>
        
        <p>Si tiene cualquier duda o consulta sobre su trámite, puede responder directamente a este correo o contactarnos a través de los canales que encontrará a continuación.</p>
        
        <div class="divider"></div>
        
        <p>Recuerde guardar el número de referencia de su trámite para futuras consultas: <strong>' . $tramite_id . '</strong></p>
        
        <a href="https://tramitfy.es/mi-cuenta/" class="button">Acceder a mi cuenta</a>
        
        <p style="margin-top: 30px;">Atentamente,</p>
        <p><strong>El equipo de Tramitfy</strong><br>
        Su gestor náutico de confianza</p>
    </div>
    
    <div class="footer">
        <p>Tramitfy S.L. - CIF B12345678</p>
        <p>Calle Principal 123, 28001 Madrid</p>
        <p>Email: info@tramitfy.es | Teléfono: 910 123 456</p>
        <p>&copy; ' . date('Y') . ' Tramitfy. Todos los derechos reservados.</p>
    </div>
</body>
</html>
';
    
    wp_mail($cliente_email, $cliente_subject, $cliente_message, $headers);
    
    // Correo para el administrador
    $admin_subject = 'Nuevo trámite: ' . $tramite_id;
    $admin_message = '<h2>Nuevo trámite registrado</h2>';
    $admin_message .= '<p><strong>Número de trámite:</strong> ' . $tramite_id . '</p>';
    $admin_message .= '<p><strong>Tipo:</strong> ' . ($tipo_tramite == 'abanderamiento' ? 'Matriculación/Abanderamiento' : 'Inscripción') . '</p>';
    $admin_message .= '<p><strong>Cliente:</strong> ' . $propietario_nombre . ' (' . $propietario_nif . ')</p>';
    $admin_message .= '<p><strong>Email:</strong> ' . $propietario_email . '</p>';
    $admin_message .= '<p><strong>Embarcación:</strong> ' . $marca . ' ' . $modelo . '</p>';
    $admin_message .= '<p><strong>Precio final:</strong> ' . $precio_final . ' €</p>';
    
    if (!empty($driveFileLinks)) {
        $admin_message .= '<h3>Documentos subidos:</h3><ul>';
        foreach ($driveFileLinks as $key => $link) {
            $admin_message .= '<li><a href="' . $link . '" target="_blank">' . $key . '</a></li>';
        }
        $admin_message .= '</ul>';
    }
    
    wp_mail($admin_email, $admin_subject, $admin_message, $headers);
    
    wp_send_json_success(array(
        'message' => 'Formulario procesado correctamente',
        'tramite_id' => $tramite_id
    ));
    
    wp_die();
}
add_shortcode('matriculacion_form', 'matriculacion_form_shortcode');