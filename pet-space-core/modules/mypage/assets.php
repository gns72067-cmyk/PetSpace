<?php
defined('ABSPATH') || exit;

add_action('wp_enqueue_scripts', function() {
    if (!get_query_var('psc_mypage')) return;

    $version = defined('PSC_VERSION') ? PSC_VERSION : '1.0.0';
    $css_url = plugin_dir_url(__FILE__) . 'assets/css/mypage.css';

    wp_enqueue_style('psc-mypage', $css_url, [], $version);

    $primary = '#224471';
    if (function_exists('psc_register_get_design')) {
        $d       = psc_register_get_design();
        $primary = $d['primary_color'] ?? '#224471';
    }
    wp_add_inline_style('psc-mypage', ':root { --psc-primary: ' . sanitize_hex_color($primary) . '; }');
});
