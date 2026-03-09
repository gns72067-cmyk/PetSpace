<?php
/**
 * Plugin Name: Pet Space Core
 * Description: 펫스페이스 핵심 기능 플러그인
 * Version:     2.0.0
 * Author:      Pet Space Dev
 * Text Domain: pet-space-core
 * Requires PHP: 8.0
 * Requires at least: 6.0
 */

defined('ABSPATH') || exit;

/* ============================================================
   1. 코어 상수 · 헬퍼 (가장 먼저 로드)
   ============================================================ */
require_once __DIR__ . '/core/constants.php';
require_once __DIR__ . '/core/helpers.php';

/* ============================================================
   2. 기본 유틸 모듈
   ============================================================ */
psc_require('modules/access-control.php');
psc_require('modules/region.php');
psc_require('modules/hours.php');
psc_require('modules/address-badge.php');
psc_require('modules/admin-premium-lock.php');

/* ============================================================
   3. 어드민
   ============================================================ */
psc_require('modules/admin/store-manager.php');

/* ============================================================
   4. 공지
   ============================================================ */
psc_require('modules/notice/notice.php');
psc_require('modules/notice/notice-query.php');

/* ============================================================
   5. 쿠폰
   ============================================================ */
psc_require('modules/coupon/coupon.php');
psc_require('modules/coupon/coupon-query.php');
psc_require('modules/coupon/store-coupon-display.php');

/* ============================================================
   6. 벤더 대시보드
   ============================================================ */
psc_require('modules/vendor/vendor-dashboard.php');
psc_require('modules/vendor/vendor-dashboard-store.php');
psc_require('modules/vendor/vendor-dashboard-notices.php');
psc_require('modules/vendor/vendor-dashboard-coupons.php');

/* ============================================================
   7. 회원 인증 · 마이페이지
   ============================================================ */
psc_require('modules/auth/register-admin.php');
psc_require('modules/auth/register.php');
psc_require('modules/auth/login.php');
psc_require('modules/auth/reset-password.php');
psc_require('modules/mypage/mypage.php');

/* ============================================================
   8. 반려견 정보
   ============================================================ */
psc_require('modules/pets/pets.php');
psc_require('modules/pets/vax-wallet.php');

/* ============================================================
   9. 리뷰 모듈
   로드 순서 중요:
   constants → db → helpers → ocr → gps
   → assets → form/* → list/* → shortcodes → ajax → admin
   ============================================================ */

/* 9-1. 기반 */
psc_require('modules/review/constants.php');
psc_require('modules/review/db.php');
psc_require('modules/review/helpers.php');
psc_require('modules/review/ocr.php');
psc_require('modules/review/gps.php');

/* 9-2. 에셋 로더 */
psc_require('modules/review/assets.php');

/* 9-3. 폼 스텝 (form-shell 이 각 스텝 함수를 호출하므로 스텝 먼저) */
psc_require('modules/review/form/step-verify.php');
psc_require('modules/review/form/step-date.php');
psc_require('modules/review/form/step-rating.php');
psc_require('modules/review/form/step-tags.php');
psc_require('modules/review/form/step-content.php');
psc_require('modules/review/form/step-photo.php');
psc_require('modules/review/form/form-shell.php');

/* 9-4. 리스트 (card → renderer → page 순서) */
psc_require('modules/review/list/card.php');
psc_require('modules/review/list/renderer.php');
psc_require('modules/review/list/page.php');

/* 9-5. 숏코드 · AJAX · 어드민 */
psc_require('modules/review/shortcodes.php');
psc_require('modules/review/ajax.php');
psc_require('modules/review/admin.php');

/* ============================================================
   10. 플러그인 활성화 훅
   ============================================================ */
register_activation_hook(__FILE__, function (): void {
    if (function_exists('psc_review_create_tables')) {
        psc_review_create_tables();
    }
});

/* ============================================================
   11. 하단 메뉴바 활성화 처리 (기존 유지)
   ============================================================ */
add_action('wp_footer', 'psc_bottom_nav_active');

function psc_bottom_nav_active(): void {
    ?>
    <script>
    (function () {
        var path = window.location.pathname;
        var menuPatterns = [
            { pattern: /^\/store(\/|$)/, href: 'store' },
        ];
        document.querySelectorAll(
            '.elementor-nav-menu a, .elementor-widget-nav-menu a'
        ).forEach(function (link) {
            menuPatterns.forEach(function (item) {
                if (
                    link.getAttribute('href') &&
                    link.getAttribute('href').indexOf(item.href) !== -1 &&
                    item.pattern.test(path)
                ) {
                    var li = link.closest('li');
                    if (li) li.classList.add('current-menu-item', 'current_page_item');
                    link.classList.add('elementor-item-active');
                }
            });
        });
    })();
    </script>
    <?php
}
