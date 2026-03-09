<?php
/**
 * Auth 모듈 에셋 로더
 */
defined('ABSPATH') || exit;

add_action('wp_enqueue_scripts', function (): void {
    $base = PSC_MODULES_URL . 'auth/assets/css/';
    $ver  = PSC_VERSION;

    /* 로그인 페이지 */
    if (get_query_var('psc_auth') === 'login') {
        wp_enqueue_style('psc-login', $base . 'login.css', [], $ver);
    }

    /* 회원가입 페이지 */
    if (in_array(get_query_var('psc_auth'), ['register', 'register_done'], true)) {
        wp_enqueue_style('psc-register', $base . 'register.css', [], $ver);
    }

    /* 비밀번호 재설정 */
    if (in_array(get_query_var('psc_auth'), ['reset_request', 'reset_confirm', 'reset_complete'], true)) {
        wp_enqueue_style('psc-reset-password', $base . 'reset-password.css', [], $ver);
    }
});
