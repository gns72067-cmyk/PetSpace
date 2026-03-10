<?php
defined('ABSPATH') || exit;

add_action('wp_enqueue_scripts', function() {
    $base    = plugin_dir_url(__FILE__) . 'assets/css/';
    $version = defined('PSC_VERSION') ? PSC_VERSION : '1.0.0';
    $page    = get_query_var('psc_auth');

    if ($page === 'login') {
        wp_enqueue_style('psc-login', $base . 'login.css', [], $version);
    }
    if (in_array($page, ['register', 'register_done'], true)) {
        wp_enqueue_style('psc-register', $base . 'register.css', [], $version);
    }
    if (in_array($page, ['reset_request', 'reset_confirm', 'reset_complete'], true)) {
        wp_enqueue_style('psc-reset-password', $base . 'reset-password.css', [], $version);
    }
});
