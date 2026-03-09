<?php
defined( 'ABSPATH' ) || exit;

/**
 * 파일 안전 로드
 */
function psc_require( string $relative_path ): void {
    $full = PSC_PLUGIN_DIR . $relative_path;
    if ( file_exists( $full ) ) {
        require_once $full;
    } else {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            trigger_error(
                "[PetSpace] 파일을 찾을 수 없습니다: {$relative_path}",
                E_USER_WARNING
            );
        }
    }
}

/**
 * 모듈 URL 반환
 */
function psc_module_url( string $module ): string {
    return PSC_MODULES_URL . $module . '/';
}

/**
 * 모듈 DIR 반환
 */
function psc_module_dir( string $module ): string {
    return PSC_MODULES_DIR . $module . '/';
}
