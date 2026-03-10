<?php
defined('ABSPATH') || exit;

add_action('wp_enqueue_scripts', function() {
    $page = get_query_var('psc_mypage');
    if (!in_array($page, ['pets', 'vax-wallet'], true)) return;

    $version  = defined('PSC_VERSION') ? PSC_VERSION : '1.0.0';
    $base_url = plugin_dir_url(__FILE__) . 'assets/css/';

    if ($page === 'pets') {
        wp_enqueue_style('psc-pets', $base_url . 'pets.css', [], $version);
    }
    if ($page === 'vax-wallet') {
        wp_enqueue_style('psc-vax-wallet', $base_url . 'vax-wallet.css', [], $version);
    }

    $primary = '#224471';
    if (function_exists('psc_register_get_design')) {
        $d       = psc_register_get_design();
        $primary = $d['primary_color'] ?? '#224471';
    }
    $handle = $page === 'pets' ? 'psc-pets' : 'psc-vax-wallet';
    wp_add_inline_style($handle, ':root { --psc-primary: ' . sanitize_hex_color($primary) . '; }');
});
