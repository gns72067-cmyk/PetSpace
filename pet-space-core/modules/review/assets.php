<?php
defined( 'ABSPATH' ) || exit;

add_action( 'wp_enqueue_scripts', function () {
    wp_enqueue_style(
        'psc-review',
        PSC_PLUGIN_URL . 'modules/review/assets/css/review.css',
        [],
        PSC_VERSION
    );
} );

add_action( 'admin_enqueue_scripts', function ( string $hook ) {
    if ( strpos( $hook, 'psc-reviews' ) === false ) return;
    wp_enqueue_style(
        'psc-review-admin',
        PSC_PLUGIN_URL . 'modules/review/assets/css/review-admin.css',
        [],
        PSC_VERSION
    );
} );
