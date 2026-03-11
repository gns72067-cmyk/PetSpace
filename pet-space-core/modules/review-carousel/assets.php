<?php
defined( 'ABSPATH' ) || exit;

add_action( 'wp_enqueue_scripts', function (): void {
    if ( is_admin() ) {
        return;
    }

    wp_enqueue_style(
        'psc-review-carousel-style',
        PSC_PLUGIN_URL . 'modules/review-carousel/assets/css/review-carousel.css',
        [],
        PSC_VERSION
    );

    wp_enqueue_script(
        'psc-review-carousel-script',
        PSC_PLUGIN_URL . 'modules/review-carousel/assets/js/review-carousel.js',
        [],
        PSC_VERSION,
        true
    );
    wp_script_add_data( 'psc-review-carousel-script', 'defer', true );
} );
