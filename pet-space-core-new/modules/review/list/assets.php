<?php
/**
 * Review 모듈 에셋 로더
 * CSS · JS enqueue
 */
defined('ABSPATH') || exit;

add_action('wp_enqueue_scripts', function (): void {

    /* 스토어 단일 페이지 또는 /reviews/ 페이지에서만 로드 */
    if (!is_singular('store') && !is_page('reviews')) return;

    $base = PSC_MODULES_URL . 'review/assets/';
    $ver  = PSC_VERSION;

    /* ── CSS ─────────────────────────────── */
    wp_enqueue_style(
        'psc-review-common',
        $base . 'css/review-common.css',
        [],
        $ver
    );
    wp_enqueue_style(
        'psc-review-form',
        $base . 'css/review-form.css',
        ['psc-review-common'],
        $ver
    );
    wp_enqueue_style(
        'psc-review-list',
        $base . 'css/review-list.css',
        ['psc-review-common'],
        $ver
    );

    /* ── JS ──────────────────────────────── */
    wp_enqueue_script(
        'psc-review-form',
        $base . 'js/review-form.js',
        [],
        $ver,
        true
    );
    wp_enqueue_script(
        'psc-review-ajax',
        $base . 'js/review-ajax.js',
        ['psc-review-form'],
        $ver,
        true
    );
    wp_enqueue_script(
        'psc-review-list',
        $base . 'js/review-list.js',
        ['psc-review-ajax'],
        $ver,
        true
    );

    /* ── JS 변수 주입 ─────────────────────── */
    wp_localize_script('psc-review-ajax', 'PSC', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('psc_review_nonce'),
        'isLogin' => is_user_logged_in() ? 1 : 0,
        'loginUrl'=> wp_login_url(get_permalink()),
    ]);
});
