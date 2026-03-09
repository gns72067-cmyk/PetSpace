<?php
/**
 * Mypage — Asset Loader
 * File: modules/mypage/assets.php
 */
defined( 'ABSPATH' ) || exit;

add_action( 'wp_enqueue_scripts', function (): void {
    if ( ! get_query_var( 'psc_mypage' ) ) return;

    wp_enqueue_style(
        'psc-mypage',
        PSC_MODULES_URL . 'mypage/assets/css/mypage.css',
        [],
        PSC_VERSION
    );

    /* primary color 동적 주입 (관리자 설정값 → CSS 변수 덮어쓰기) */
    $d       = function_exists( 'psc_register_get_design' ) ? psc_register_get_design() : [];
    $primary = sanitize_hex_color( $d['primary_color'] ?? '' ) ?: '#224471';
    wp_add_inline_style( 'psc-mypage', ":root { --psc-mp-primary: {$primary}; }" );
} );
