<?php
/**
 * Plugin Name: Pet Space Core
 * Description: 펫스페이스 핵심 기능 플러그인.
 * Version:     1.0.0
 * Author:      Pet Space Dev
 * Text Domain: pet-space-core
 * Requires PHP: 8.0
 * Requires at least: 6.0
 */

defined( 'ABSPATH' ) || exit;

define( 'PSC_VERSION',     '1.0.0' );
define( 'PSC_PLUGIN_FILE', __FILE__ );                    // ← 추가: register_activation_hook 에 필요
define( 'PSC_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'PSC_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );

add_action( 'wp_enqueue_scripts', function (): void {
    wp_enqueue_style(
        'psc-ui-scope',
        PSC_PLUGIN_URL . 'assets/css/psc-ui-scope.css',
        [],
        PSC_VERSION
    );
} );

/* ============================================================
   파일 존재 여부 확인 후 로드 (누락 파일로 인한 fatal 방지)
   ============================================================ */
function psc_require( string $relative_path ): void {
    $full = PSC_PLUGIN_DIR . $relative_path;
    if ( file_exists( $full ) ) {
        require_once $full;
    } else {
        // 개발 환경에서만 경고 출력
        if ( defined('WP_DEBUG') && WP_DEBUG ) {
            trigger_error(
                "[PetSpace] 파일을 찾을 수 없습니다: {$relative_path}",
                E_USER_WARNING
            );
        }
    }
}

/* ============================================================
   기본 유틸
   ============================================================ */
psc_require( 'modules/access-control.php' );
psc_require( 'modules/region.php' );
psc_require( 'modules/hours.php' );
psc_require( 'modules/address-badge.php' );
psc_require( 'modules/admin-premium-lock.php' );

/* ============================================================
   어드민
   ============================================================ */
psc_require( 'modules/admin/store-manager.php' );

/* ============================================================
   공지
   ============================================================ */
psc_require( 'modules/notice.php' );
psc_require( 'modules/notice-query.php' );

/* ============================================================
   쿠폰
   ============================================================ */
psc_require( 'modules/coupon.php' );
psc_require( 'modules/coupon-query.php' );
psc_require( 'modules/store-coupon-display.php' );

/* ============================================================
   벤더 대시보드
   ============================================================ */
psc_require( 'modules/vendor-dashboard.php' );
psc_require( 'modules/vendor-dashboard-store.php' );
psc_require( 'modules/vendor-dashboard-notices.php' );
psc_require( 'modules/vendor-dashboard-coupons.php' );

/* ============================================================
   회원 인증
   ============================================================ */
psc_require( 'modules/auth/assets.php' );
psc_require( 'modules/auth/register-admin.php' );
psc_require( 'modules/auth/register.php' );
psc_require( 'modules/auth/login.php' );
psc_require( 'modules/auth/reset-password.php' );
psc_require( 'modules/mypage/assets.php' );
psc_require( 'modules/mypage/mypage.php' );

/* ============================================================
   반려견 정보
   ============================================================ */
psc_require( 'modules/pets/assets.php' );
psc_require( 'modules/pets/pets.php' );
psc_require( 'modules/pets/vax-wallet.php' );

/* ============================================================
   리뷰
   ※ 반드시 review.php → ajax → render → admin 순서 유지
   ============================================================ */
psc_require( 'modules/review/assets.php' );
psc_require( 'modules/review/review.php' );
psc_require( 'modules/review/review-ajax.php' );
psc_require( 'modules/review/review-render.php' );
psc_require( 'modules/review/review-admin.php' );
psc_require( 'modules/review-carousel/assets.php' );
psc_require( 'modules/review-carousel/review-carousel.php' );

/* ============================================================
   플러그인 활성화 훅 (테이블 생성 등)
   ============================================================ */
register_activation_hook( PSC_PLUGIN_FILE, function(): void {
    // 리뷰 테이블 생성
    if ( function_exists( 'psc_review_create_tables' ) ) {
        psc_review_create_tables();
    }
    // 추후 추가될 모듈 활성화 훅도 여기서 호출
} );

/* ============================================================
   하단 메뉴바 활성화 처리
   ============================================================ */
add_action( 'wp_footer', 'psc_bottom_nav_active' );

function psc_bottom_nav_active(): void {
    ?>
    <script>
    (function(){
        var path = window.location.pathname;

        var menuPatterns = [
            { pattern: /^\/store(\/|$)/, href: 'store' },
        ];

        document.querySelectorAll(
            '.elementor-nav-menu a, .elementor-widget-nav-menu a'
        ).forEach(function(link) {
            menuPatterns.forEach(function(item) {
                if (
                    link.getAttribute('href') &&
                    link.getAttribute('href').indexOf(item.href) !== -1 &&
                    item.pattern.test(path)
                ) {
                    var li = link.closest('li');
                    if (li) {
                        li.classList.add('current-menu-item', 'current_page_item');
                    }
                    link.classLi
