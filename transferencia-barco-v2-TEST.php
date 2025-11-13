<?php
if (!defined('ABSPATH')) {
    exit;
}

function tbv2_test_render() {
    return '<h1 style="color: red;">TBV2 TEST FUNCIONA</h1>';
}

function tbv2_test_register() {
    add_shortcode('tbv2_test', 'tbv2_test_render');
}
add_action('init', 'tbv2_test_register');