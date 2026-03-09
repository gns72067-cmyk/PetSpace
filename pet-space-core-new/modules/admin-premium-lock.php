<?php
/**
 * Module: Admin Premium Lock
 *
 * store 편집 화면에서 store_plan 라디오 값을 감시해
 * .premium-only-group 을 숨김/표시합니다.
 *
 * store_plan field key : field_69a2ca4fac26e
 *
 * 동작 방식:
 *  - ACF 라디오 렌더가 JS 이후에 끝날 수 있으므로 setInterval 로 폴링 후
 *    DOM 감지 시 MutationObserver 로 전환합니다.
 */

defined( 'ABSPATH' ) || exit;

/**
 * store 편집 화면에만 JS 에셋 인라인 등록.
 */
function psc_admin_enqueue_premium_lock(): void {

    $screen = get_current_screen();
    if (
        ! $screen ||
        $screen->base    !== 'post' ||
        $screen->post_type !== 'store'
    ) {
        return;
    }

    wp_register_script(
        'psc-admin-premium-lock',
        false,  // src 없음 → 인라인 전용
        [],
        PSC_VERSION,
        true    // footer
    );
    wp_enqueue_script( 'psc-admin-premium-lock' );
    wp_add_inline_script( 'psc-admin-premium-lock', psc_premium_lock_js() );
}
add_action( 'admin_enqueue_scripts', 'psc_admin_enqueue_premium_lock' );

/**
 * 인라인 JS 문자열 반환.
 * field_key 는 PHP에서 wp_json_encode() 로 안전하게 주입합니다.
 *
 * @return string
 */
function psc_premium_lock_js(): string {
    $field_key = wp_json_encode( 'field_69a2ca4fac26e' );
    // premium 플랜으로 간주할 값 목록 (필요에 따라 수정)
    $premium_values = wp_json_encode( [ 'premium', 'pro', 'enterprise' ] );

    return <<<JS
(function () {
    'use strict';

    var FIELD_KEY      = {$field_key};
    var PREMIUM_VALUES = {$premium_values};

    /* .premium-only-group 전체 숨김/표시 */
    function applyVisibility(isPremium) {
        document.querySelectorAll('.premium-only-group').forEach(function (el) {
            el.style.display = isPremium ? '' : 'none';
        });
    }

    /* ACF 라디오/셀렉트 래퍼에서 현재 선택값 읽기 */
    function getFieldValue(fieldWrap) {
        /* 라디오 */
        var checked = fieldWrap.querySelector('input[type="radio"]:checked');
        if (checked) return checked.value;
        /* 셀렉트 */
        var sel = fieldWrap.querySelector('select');
        if (sel) return sel.value;
        /* 체크박스 단일 */
        var cb = fieldWrap.querySelector('input[type="checkbox"]:checked');
        if (cb) return cb.value;
        return '';
    }

    /* 변경 이벤트 핸들러 */
    function onChange(fieldWrap) {
        var val       = getFieldValue(fieldWrap);
        var isPremium = PREMIUM_VALUES.indexOf(val) !== -1;
        applyVisibility(isPremium);
    }

    /* MutationObserver 로 라디오 변경 감지 */
    function observeField(fieldWrap) {
        /* 초기 상태 즉시 적용 */
        onChange(fieldWrap);

        /* 라디오/셀렉트 change 이벤트 */
        fieldWrap.addEventListener('change', function () {
            onChange(fieldWrap);
        });

        /* ACF가 동적으로 checked 속성을 클래스 등으로 반영하는 경우 대비 */
        var observer = new MutationObserver(function () {
            onChange(fieldWrap);
        });
        observer.observe(fieldWrap, {
            subtree:   true,
            attributes: true,
            attributeFilter: ['checked', 'selected', 'class', 'value']
        });
    }

    /* ACF 라디오 래퍼 탐색:
       data-field_key 속성 또는 data-key 속성으로 식별 */
    function findFieldWrap() {
        return (
            document.querySelector('[data-field_key="' + FIELD_KEY + '"]') ||
            document.querySelector('[data-key="' + FIELD_KEY + '"]') ||
            /* ACF 5.x 구형 구조 */
            document.querySelector('.acf-field[data-key="' + FIELD_KEY + '"]')
        );
    }

    /* ─── 메인 진입 ───
       ACF 필드 렌더가 DOMContentLoaded 이후 완료되는 경우가 있으므로
       setInterval 로 최대 10초 폴링 후 발견 시 MutationObserver 로 전환 */
    var MAX_TRIES  = 100;   // 100 × 100ms = 10초
    var tryCount   = 0;
    var pollTimer  = null;

    function poll() {
        tryCount++;
        var fieldWrap = findFieldWrap();

        if (fieldWrap) {
            clearInterval(pollTimer);
            observeField(fieldWrap);
            return;
        }

        if (tryCount >= MAX_TRIES) {
            clearInterval(pollTimer);
            console.warn('[psc] store_plan 필드를 찾지 못했습니다. field_key:', FIELD_KEY);
        }
    }

    /* 즉시 1회 시도 */
    poll();

    /* DOM 준비 후 간격 폴링 */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            if (!findFieldWrap()) {
                pollTimer = setInterval(poll, 100);
            }
        });
    } else {
        if (!findFieldWrap()) {
            pollTimer = setInterval(poll, 100);
        }
    }

    /* ACF acf/setup_fields 이벤트 훅 (ACF JS API) */
    if (typeof acf !== 'undefined' && typeof acf.addAction === 'function') {
        acf.addAction('ready',       function () { poll(); });
        acf.addAction('append',      function () { poll(); });
        acf.addAction('load',        function () { poll(); });
        acf.addAction('new_field/type=radio', function () { poll(); });
    }

}());
JS;
}
