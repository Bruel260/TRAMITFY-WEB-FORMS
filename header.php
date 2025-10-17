<?php
/**
 * El encabezado de tu tema WordPress
 */
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="profile" href="https://gmpg.org/xfn/11">
    
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=AW-16784400779"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
      gtag('config', 'AW-16784400779');
    </script>

    <?php
    // Verificar si estamos en la página de pago realizado con éxito
    if ($_SERVER['REQUEST_URI'] === "/pago-realizado-con-exito/") { ?>
    <!-- Google tag (gtag.js) event -->
    <script>
      console.log("Evento de conversión activado");
      gtag('event', 'ads_conversion_Compra_1', {
        // Sin parámetros adicionales
      });
    </script>
    <?php } ?>

    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
    <?php wp_body_open(); ?>
    <header id="masthead" class="site-header">
        <div class="site-branding">
            <?php the_custom_logo(); ?>
            <div class="site-title-description">
                <h1 class="site-title"><?php bloginfo( 'name' ); ?></h1>
                <p class="site-description"><?php bloginfo( 'description' ); ?></p>
            </div>
        </div>
    </header>



