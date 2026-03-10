<?php
/**
 * Module: Coupon
 * ══════════════════════════════════════════════════════════════
 * - CPT: store_coupon 등록
 * - 커스텀 테이블: wp_psc_coupon_issued 생성
 * - 메타박스: 쿠폰 정보 입력
 * - 플랜 제한: 프리미엄만 발행 가능
 * ══════════════════════════════════════════════════════════════
 */

defined( 'ABSPATH' ) || exit;

/* ============================================================
   0. 상수
   ============================================================ */

define( 'PSC_COUPON_CPT',        'store_coupon' );
define( 'PSC_COUPON_STORE_META', 'coupon_store_id' );
define( 'PSC_COUPON_TABLE',      'psc_coupon_issued' );

/* ============================================================
   1. 커스텀 테이블 생성
   ============================================================ */

register_activation_hook(
    WP_PLUGIN_DIR . '/pet-space-core/pet-space-core.php',
    'psc_coupon_create_table'
);

function psc_coupon_create_table(): void {
    global $wpdb;

    $table   = $wpdb->prefix . PSC_COUPON_TABLE;
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS {$table} (
        id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        coupon_id   BIGINT(20) UNSIGNED NOT NULL,
        user_id     BIGINT(20) UNSIGNED NOT NULL,
        token       VARCHAR(64)         NOT NULL,
        issued_at   DATETIME            NOT NULL,
        used_at     DATETIME            DEFAULT NULL,
        is_used     TINYINT(1)          NOT NULL DEFAULT 0,
        PRIMARY KEY (id),
        UNIQUE KEY token (token),
        KEY coupon_id (coupon_id),
        KEY user_id  (user_id)
    ) {$charset};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
}

/* 테이블이 없으면 자동 생성 (플러그인 업데이트 대응) */
add_action( 'init', function() {
    global $wpdb;
    $table = $wpdb->prefix . PSC_COUPON_TABLE;
    if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) {
        psc_coupon_create_table();
    }
});

/* ============================================================
   2. CPT 등록
   ============================================================ */

add_action( 'init', 'psc_register_coupon_cpt' );

function psc_register_coupon_cpt(): void {
    register_post_type( PSC_COUPON_CPT, [
        'labels' => [
            'name'          => '매장 쿠폰',
            'singular_name' => '쿠폰',
            'add_new_item'  => '쿠폰 추가',
            'edit_item'     => '쿠폰 수정',
        ],
        'public'            => false,
        'show_ui'           => true,
        'show_in_menu'      => 'psc-coupon-dashboard',
        'show_in_rest'      => true,
        'supports'          => [ 'title' ],
        'capability_type'   => 'post',
        'map_meta_cap'      => true,
        'rewrite'           => false,
    ]);
}

/* ============================================================
   3. 메타박스 (어드민)
   ============================================================ */

add_action( 'add_meta_boxes', 'psc_coupon_meta_boxes' );

function psc_coupon_meta_boxes(): void {
    add_meta_box(
        'psc_coupon_meta',
        '쿠폰 설정',
        'psc_coupon_meta_render',
        PSC_COUPON_CPT,
        'normal',
        'high'
    );
}

function psc_coupon_meta_render( WP_Post $post ): void {
    wp_nonce_field( 'psc_coupon_save_' . $post->ID, 'psc_coupon_nonce' );

    $store_id   = get_post_meta( $post->ID, 'coupon_store_id',    true );
    $type       = get_post_meta( $post->ID, 'coupon_type',        true ) ?: 'percent';
    $value      = get_post_meta( $post->ID, 'coupon_value',       true );
    $desc       = get_post_meta( $post->ID, 'coupon_desc',        true );
    $expire     = get_post_meta( $post->ID, 'coupon_expire_date', true );
    $max_use    = get_post_meta( $post->ID, 'coupon_max_use',     true );
    $use_limit  = get_post_meta( $post->ID, 'coupon_use_limit',   true ) ?: 1;

    /* 전체 매장 목록 */
    $stores = get_posts([
        'post_type'      => defined('PSC_STORE_CPT') ? PSC_STORE_CPT : 'store',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ]);

    ?>
    <table class="form-table">
        <tr>
            <th>연결 매장</th>
            <td>
                <select name="coupon_store_id" style="min-width:200px">
                    <option value="">— 선택 —</option>
                    <?php foreach ( $stores as $s ) : ?>
                        <option value="<?php echo $s->ID; ?>"
                            <?php selected( $store_id, $s->ID ); ?>>
                            <?php echo esc_html( $s->post_title ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr>
            <th>할인 타입</th>
            <td>
                <label style="margin-right:16px">
                    <input type="radio" name="coupon_type" value="percent"
                           <?php checked( $type, 'percent' ); ?>>
                    할인율 (%)
                </label>
                <label>
                    <input type="radio" name="coupon_type" value="fixed"
                           <?php checked( $type, 'fixed' ); ?>>
                    정액 할인 (원)
                </label>
            </td>
        </tr>
        <tr>
            <th>할인값</th>
            <td>
                <input type="number" name="coupon_value"
                       value="<?php echo esc_attr( $value ); ?>"
                       min="1" style="width:120px">
                <span id="psc-coupon-unit">
                    <?php echo $type === 'fixed' ? '원' : '%'; ?>
                </span>
                <script>
                document.querySelectorAll('input[name="coupon_type"]').forEach(function(r){
                    r.addEventListener('change', function(){
                        document.getElementById('psc-coupon-unit').textContent =
                            this.value === 'fixed' ? '원' : '%';
                    });
                });
                </script>
            </td>
        </tr>
        <tr>
            <th>쿠폰 설명</th>
            <td>
                <input type="text" name="coupon_desc"
                       value="<?php echo esc_attr( $desc ); ?>"
                       placeholder="예) 첫 방문 고객 10% 할인"
                       style="width:100%">
            </td>
        </tr>
        <tr>
            <th>만료일</th>
            <td>
                <input type="date" name="coupon_expire_date"
                       value="<?php echo esc_attr( $expire ); ?>">
                <p class="description">비워두면 상시 노출됩니다.</p>
            </td>
        </tr>
        <tr>
            <th>총 발행 수량</th>
            <td>
                <input type="number" name="coupon_max_use"
                       value="<?php echo esc_attr( $max_use ); ?>"
                       min="0" placeholder="0 = 무제한"
                       style="width:120px">
                <p class="description">0 입력 시 무제한 발행됩니다.</p>
            </td>
        </tr>
        <tr>
            <th>1인당 사용 횟수</th>
            <td>
                <input type="number" name="coupon_use_limit"
                       value="<?php echo esc_attr( $use_limit ); ?>"
                       min="1" style="width:120px">
                <p class="description">1인이 해당 쿠폰을 최대 몇 번 사용할 수 있는지 설정합니다.</p>
            </td>
        </tr>
    </table>
    <?php
}

/* ============================================================
   4. 메타 저장
   ============================================================ */

add_action( 'save_post_' . PSC_COUPON_CPT, 'psc_coupon_save_meta' );

function psc_coupon_save_meta( int $post_id ): void {
    if (
        ! isset( $_POST['psc_coupon_nonce'] )
        || ! wp_verify_nonce(
            sanitize_text_field( wp_unslash( $_POST['psc_coupon_nonce'] ) ),
            'psc_coupon_save_' . $post_id
        )
    ) return;

    if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    $store_id  = absint( $_POST['coupon_store_id']    ?? 0 );
    $type      = in_array( $_POST['coupon_type'] ?? '', ['percent','fixed'], true )
                 ? $_POST['coupon_type'] : 'percent';
    $value     = absint( $_POST['coupon_value']       ?? 0 );
    $desc      = sanitize_text_field( $_POST['coupon_desc']        ?? '' );
    $expire    = sanitize_text_field( $_POST['coupon_expire_date'] ?? '' );
    $max_use   = absint( $_POST['coupon_max_use']     ?? 0 );
    $use_limit = max( 1, absint( $_POST['coupon_use_limit'] ?? 1 ) );

    /* 프리미엄 플랜 확인 */
    if ( $store_id && function_exists('psc_store_is_premium') && ! psc_store_is_premium( $store_id ) ) {
        /* 무료 플랜이면 강제 draft */
        wp_update_post( [ 'ID' => $post_id, 'post_status' => 'draft' ] );
        set_transient( 'psc_coupon_msg_' . $post_id, 'premium_only', 30 );
        return;
    }

    update_post_meta( $post_id, 'coupon_store_id',    $store_id );
    update_post_meta( $post_id, 'coupon_type',        $type );
    update_post_meta( $post_id, 'coupon_value',       $value );
    update_post_meta( $post_id, 'coupon_desc',        $desc );
    update_post_meta( $post_id, 'coupon_expire_date', $expire );
    update_post_meta( $post_id, 'coupon_max_use',     $max_use );
    update_post_meta( $post_id, 'coupon_use_limit',   $use_limit );
}

/* ============================================================
   5. 헬퍼 함수
   ============================================================ */

/**
 * 쿠폰 메타 읽기
 */
function psc_coupon_get_meta( int $coupon_id, string $key ): mixed {
    return get_post_meta( $coupon_id, 'coupon_' . $key, true );
}

/**
 * 쿠폰 만료 여부
 */
function psc_coupon_is_expired( int $coupon_id ): bool {
    $expire = psc_coupon_get_meta( $coupon_id, 'expire_date' );
    if ( ! $expire ) return false;
    return $expire < gmdate( 'Y-m-d' );
}

/**
 * 쿠폰 총 발행 수량 초과 여부
 */
function psc_coupon_is_full( int $coupon_id ): bool {
    global $wpdb;
    $max = (int) psc_coupon_get_meta( $coupon_id, 'max_use' );
    if ( $max === 0 ) return false; // 무제한
    $table = $wpdb->prefix . PSC_COUPON_TABLE;
    $count = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$table} WHERE coupon_id = %d",
        $coupon_id
    ));
    return $count >= $max;
}

/**
 * 특정 유저의 해당 쿠폰 발급 횟수
 */
function psc_coupon_user_issue_count( int $coupon_id, int $user_id ): int {
    global $wpdb;
    $table = $wpdb->prefix . PSC_COUPON_TABLE;
    return (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$table} WHERE coupon_id = %d AND user_id = %d",
        $coupon_id, $user_id
    ));
}

/**
 * 특정 유저의 해당 쿠폰 사용 횟수
 */
function psc_coupon_user_used_count( int $coupon_id, int $user_id ): int {
    global $wpdb;
    $table = $wpdb->prefix . PSC_COUPON_TABLE;
    return (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$table} WHERE coupon_id = %d AND user_id = %d AND is_used = 1",
        $coupon_id, $user_id
    ));
}

/**
 * 쿠폰 발급 (토큰 생성 + DB 저장)
 */
function psc_coupon_issue( int $coupon_id, int $user_id ): string|WP_Error {
    global $wpdb;

    $post = get_post( $coupon_id );
    if ( ! $post || $post->post_type !== PSC_COUPON_CPT ) {
        return new WP_Error( 'invalid_coupon', '유효하지 않은 쿠폰입니다.' );
    }
    if ( $post->post_status !== 'publish' ) {
        return new WP_Error( 'inactive_coupon', '비활성 쿠폰입니다.' );
    }
    if ( psc_coupon_is_expired( $coupon_id ) ) {
        return new WP_Error( 'expired', '만료된 쿠폰입니다.' );
    }
    if ( psc_coupon_is_full( $coupon_id ) ) {
        return new WP_Error( 'full', '발행 수량이 모두 소진되었습니다.' );
    }

    $use_limit = (int) psc_coupon_get_meta( $coupon_id, 'use_limit' );
    if ( psc_coupon_user_issue_count( $coupon_id, $user_id ) >= $use_limit ) {
        return new WP_Error( 'limit_reached', '이미 최대 횟수만큼 발급받은 쿠폰입니다.' );
    }

    /* 고유 토큰 생성 */
    $token = bin2hex( random_bytes( 24 ) ); // 48자리 hex

    $table = $wpdb->prefix . PSC_COUPON_TABLE;
    $result = $wpdb->insert( $table, [
        'coupon_id' => $coupon_id,
        'user_id'   => $user_id,
        'token'     => $token,
        'issued_at' => current_time( 'mysql' ),
        'is_used'   => 0,
    ], [ '%d', '%d', '%s', '%s', '%d' ] );

    if ( $result === false ) {
        return new WP_Error( 'db_error', '쿠폰 발급 중 오류가 발생했습니다.' );
    }

    return $token;
}

/**
 * 토큰으로 발급 정보 조회
 */
function psc_coupon_get_issued_by_token( string $token ): ?object {
    global $wpdb;
    $table = $wpdb->prefix . PSC_COUPON_TABLE;
    return $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$table} WHERE token = %s",
        $token
    ));
}

/**
 * 토큰으로 쿠폰 사용 처리
 */
function psc_coupon_use( string $token ): true|WP_Error {
    global $wpdb;

    $issued = psc_coupon_get_issued_by_token( $token );
    if ( ! $issued ) {
        return new WP_Error( 'not_found', '존재하지 않는 쿠폰입니다.' );
    }
    if ( $issued->is_used ) {
        return new WP_Error( 'already_used', '이미 사용된 쿠폰입니다. (사용일시: ' . $issued->used_at . ')' );
    }
    if ( psc_coupon_is_expired( (int) $issued->coupon_id ) ) {
        return new WP_Error( 'expired', '만료된 쿠폰입니다.' );
    }

    $table = $wpdb->prefix . PSC_COUPON_TABLE;
    $wpdb->update(
        $table,
        [ 'is_used' => 1, 'used_at' => current_time('mysql') ],
        [ 'token'   => $token ],
        [ '%d', '%s' ],
        [ '%s' ]
    );

    return true;
}

/**
 * 할인값 포맷 출력
 */
function psc_coupon_format_value( int $coupon_id ): string {
    $type  = psc_coupon_get_meta( $coupon_id, 'type' );
    $value = (int) psc_coupon_get_meta( $coupon_id, 'value' );
    return $type === 'fixed'
        ? number_format( $value ) . '원 할인'
        : $value . '% 할인';
}

/* ============================================================
   6. 어드민 목록 컬럼
   ============================================================ */

add_filter( 'manage_' . PSC_COUPON_CPT . '_posts_columns', 'psc_coupon_columns' );
function psc_coupon_columns( array $cols ): array {
    return [
        'cb'          => $cols['cb'],
        'title'       => '쿠폰명',
        'store'       => '매장',
        'type'        => '타입',
        'value'       => '할인값',
        'issued'      => '발행수',
        'used'        => '사용수',
        'expire'      => '만료일',
        'date'        => '등록일',
    ];
}

add_action( 'manage_' . PSC_COUPON_CPT . '_posts_custom_column', 'psc_coupon_column_data', 10, 2 );
function psc_coupon_column_data( string $col, int $post_id ): void {
    global $wpdb;
    $table = $wpdb->prefix . PSC_COUPON_TABLE;

    switch ( $col ) {
        case 'store':
            $sid = (int) psc_coupon_get_meta( $post_id, 'store_id' );
            echo $sid ? esc_html( get_the_title( $sid ) ) : '—';
            break;
        case 'type':
            $type = psc_coupon_get_meta( $post_id, 'type' );
            echo $type === 'fixed' ? '정액' : '할인율';
            break;
        case 'value':
            echo esc_html( psc_coupon_format_value( $post_id ) );
            break;
        case 'issued':
            echo (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE coupon_id = %d", $post_id
            ));
            break;
        case 'used':
            echo (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE coupon_id = %d AND is_used = 1", $post_id
            ));
            break;
        case 'expire':
            $exp = psc_coupon_get_meta( $post_id, 'expire_date' );
            if ( ! $exp ) { echo '상시'; break; }
            $past = $exp < gmdate('Y-m-d');
            echo '<span style="color:' . ( $past ? '#c00' : '#111' ) . '">'
               . esc_html( $exp ) . ( $past ? ' (만료)' : '' ) . '</span>';
            break;
    }
}

/* ============================================================
   7. 대시보드 호환 래퍼
   ============================================================ */

/**
 * 쿠폰 총 발행 수 (대시보드용)
 */
function psc_coupon_issued_count( int $coupon_id ): int {
    global $wpdb;
    $table = $wpdb->prefix . PSC_COUPON_TABLE;
    return (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$table} WHERE coupon_id = %d",
        $coupon_id
    ));
}

/**
 * 쿠폰 총 사용 수 (대시보드용)
 */
function psc_coupon_used_count( int $coupon_id ): int {
    global $wpdb;
    $table = $wpdb->prefix . PSC_COUPON_TABLE;
    return (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$table} WHERE coupon_id = %d AND is_used = 1",
        $coupon_id
    ));
}

/**
 * 토큰으로 발급 정보 조회 (대시보드용 alias)
 */
function psc_coupon_get_by_token( string $token ): ?object {
    return psc_coupon_get_issued_by_token( $token );
}

/**
 * 유저가 매장 소유자인지 확인 (대시보드용)
 */
function psc_user_owns_store( int $user_id, int $store_id ): bool {
    if ( ! $store_id || ! $user_id ) return false;
    $post = get_post( $store_id );
    if ( ! $post ) return false;
    $cpt = defined( 'PSC_STORE_CPT' ) ? PSC_STORE_CPT : 'store';
    if ( $post->post_type !== $cpt ) return false;
    return (int) $post->post_author === $user_id;
}

/* ============================================================
   어드민 관리 기능 (A~E)
   ============================================================ */

add_action( 'admin_menu', 'psc_coupon_admin_menu' );

function psc_coupon_admin_menu(): void {
    add_menu_page(
        '쿠폰 관리',
        '🎟️ 쿠폰 관리',
        'manage_options',
        'psc-coupon-dashboard',
        'psc_coupon_admin_dashboard',
        'dashicons-tickets-alt',
        26
    );
    add_submenu_page(
        'psc-coupon-dashboard',
        '통계 대시보드',
        '📊 통계 대시보드',
        'manage_options',
        'psc-coupon-dashboard',
        'psc_coupon_admin_dashboard'
    );
    add_submenu_page(
        'psc-coupon-dashboard',
        '발급 내역',
        '📋 발급 내역',
        'manage_options',
        'psc-coupon-issued',
        'psc_coupon_admin_issued'
    );
    add_submenu_page(
        'psc-coupon-dashboard',
        '쿠폰 사용 처리',
        '✅ 사용 처리',
        'manage_options',
        'psc-coupon-use',
        'psc_coupon_admin_use_page'
    );
    add_submenu_page(
        'psc-coupon-dashboard',
        '매장별 현황',
        '🏪 매장별 현황',
        'manage_options',
        'psc-coupon-stores',
        'psc_coupon_admin_stores'
    );
}

/* 어드민 공통 CSS */
add_action( 'admin_head', 'psc_coupon_admin_css' );
function psc_coupon_admin_css(): void {
    $screen = get_current_screen();
    if ( ! $screen || strpos( $screen->id, 'psc-coupon' ) === false ) return;
    ?>
    <style>
    .psc-admin-wrap h1{margin-bottom:20px}
    .psc-admin-stats{display:flex;gap:16px;flex-wrap:wrap;margin-bottom:24px}
    .psc-admin-stat-card{
        background:#fff;border:1px solid #e5e7eb;border-radius:10px;
        padding:20px 24px;min-width:160px;text-align:center;flex:1
    }
    .psc-admin-stat-card__value{font-size:2rem;font-weight:800;color:#111;line-height:1}
    .psc-admin-stat-card__label{font-size:.85rem;color:#888;margin-top:6px}
    .psc-admin-stat-card--blue .psc-admin-stat-card__value{color:#2563eb}
    .psc-admin-stat-card--green .psc-admin-stat-card__value{color:#059669}
    .psc-admin-stat-card--purple .psc-admin-stat-card__value{color:#7c3aed}
    .psc-admin-stat-card--orange .psc-admin-stat-card__value{color:#ea580c}

    .psc-admin-table{width:100%;border-collapse:collapse;background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.06)}
    .psc-admin-table th{background:#f9fafb;padding:10px 14px;text-align:left;font-size:.82rem;color:#555;border-bottom:1px solid #e5e7eb}
    .psc-admin-table td{padding:10px 14px;font-size:.88rem;border-bottom:1px solid #f3f4f6;vertical-align:middle}
    .psc-admin-table tr:last-child td{border-bottom:none}
    .psc-admin-table tr:hover td{background:#fafafa}

    .psc-admin-badge{display:inline-block;padding:2px 8px;border-radius:20px;font-size:.75rem;font-weight:600}
    .psc-admin-badge--used{background:#d1fae5;color:#065f46}
    .psc-admin-badge--unused{background:#f3f4f6;color:#6b7280}
    .psc-admin-badge--expired{background:#fee2e2;color:#991b1b}
    .psc-admin-badge--premium{background:#fef3c7;color:#92400e}
    .psc-admin-badge--free{background:#f0f0f0;color:#888}

    .psc-admin-section{background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:20px;margin-bottom:20px}
    .psc-admin-section h2{font-size:1rem;margin-bottom:16px;padding-bottom:10px;border-bottom:1px solid #f3f4f6}

    .psc-admin-filter{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px;align-items:center}
    .psc-admin-filter input,.psc-admin-filter select{
        padding:7px 12px;border:1px solid #ddd;border-radius:6px;font-size:.88rem
    }
    .psc-admin-filter .button{height:auto;padding:7px 16px}

    .psc-plan-toggle{display:flex;align-items:center;gap:8px}
    .psc-plan-toggle select{padding:5px 10px;border-radius:6px;border:1px solid #ddd;font-size:.85rem}
    </style>
    <?php
}
/* ────────────────────────────────────────────
   C. 통계 대시보드
   ──────────────────────────────────────────── */
function psc_coupon_admin_dashboard(): void {
    global $wpdb;
    $table = $wpdb->prefix . PSC_COUPON_TABLE;

    $total_issued   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
    $total_used     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE is_used = 1" );
    $count          = wp_count_posts( PSC_COUPON_CPT );
    $active_coupons = (int) ( $count->publish ?? 0 );
    $draft_coupons  = (int) ( $count->draft   ?? 0 );
    $use_rate       = $total_issued > 0 ? round( $total_used / $total_issued * 100 ) : 0;

    /* 최근 7일 발급 */
    $daily = $wpdb->get_results(
        "SELECT DATE(issued_at) as day, COUNT(*) as cnt
         FROM {$table}
         WHERE issued_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
         GROUP BY DATE(issued_at) ORDER BY day ASC"
    );

    /* 쿠폰별 TOP 5 */
    $top5 = $wpdb->get_results(
        "SELECT coupon_id, COUNT(*) as issued, SUM(is_used) as used
         FROM {$table}
         GROUP BY coupon_id ORDER BY issued DESC LIMIT 5"
    );

    /* 만료 임박 (7일 이내) */
    $expiring = get_posts([
        'post_type'      => PSC_COUPON_CPT,
        'post_status'    => 'publish',
        'posts_per_page' => 5,
        'meta_query'     => [[
            'key'     => 'coupon_expire_date',
            'value'   => [ gmdate('Y-m-d'), gmdate('Y-m-d', strtotime('+7 days')) ],
            'compare' => 'BETWEEN',
            'type'    => 'DATE',
        ]],
    ]);
    ?>
    <div class="wrap psc-admin-wrap">
        <h1>🎟️ 쿠폰 통계 대시보드</h1>

        <div class="psc-admin-stats">
            <div class="psc-admin-stat-card psc-admin-stat-card--blue">
                <div class="psc-admin-stat-card__value"><?php echo number_format($active_coupons); ?></div>
                <div class="psc-admin-stat-card__label">발행 중인 쿠폰</div>
            </div>
            <div class="psc-admin-stat-card psc-admin-stat-card--green">
                <div class="psc-admin-stat-card__value"><?php echo number_format($total_issued); ?></div>
                <div class="psc-admin-stat-card__label">총 발급 수</div>
            </div>
            <div class="psc-admin-stat-card psc-admin-stat-card--purple">
                <div class="psc-admin-stat-card__value"><?php echo number_format($total_used); ?></div>
                <div class="psc-admin-stat-card__label">총 사용 수</div>
            </div>
            <div class="psc-admin-stat-card psc-admin-stat-card--orange">
                <div class="psc-admin-stat-card__value"><?php echo $use_rate; ?>%</div>
                <div class="psc-admin-stat-card__label">사용률</div>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px">

            <!-- 최근 7일 발급 추이 -->
            <div class="psc-admin-section">
                <h2>📈 최근 7일 발급 추이</h2>
                <?php if ( empty($daily) ) : ?>
                    <p style="color:#aaa;text-align:center;padding:20px 0">발급 내역 없음</p>
                <?php else : ?>
                    <table class="psc-admin-table">
                        <thead><tr><th>날짜</th><th>발급 수</th></tr></thead>
                        <tbody>
                        <?php foreach ($daily as $row) : ?>
                            <tr>
                                <td><?php echo esc_html($row->day); ?></td>
                                <td><strong><?php echo (int)$row->cnt; ?></strong>건</td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- 만료 임박 쿠폰 -->
            <div class="psc-admin-section">
                <h2>⚠️ 만료 임박 쿠폰 (7일 이내)</h2>
                <?php if ( empty($expiring) ) : ?>
                    <p style="color:#aaa;text-align:center;padding:20px 0">해당 없음</p>
                <?php else : ?>
                    <table class="psc-admin-table">
                        <thead><tr><th>쿠폰명</th><th>만료일</th></tr></thead>
                        <tbody>
                        <?php foreach ($expiring as $c) :
                            $exp = get_post_meta($c->ID, 'coupon_expire_date', true);
                        ?>
                            <tr>
                                <td>
                                    <a href="<?php echo get_edit_post_link($c->ID); ?>">
                                        <?php echo esc_html($c->post_title); ?>
                                    </a>
                                </td>
                                <td style="color:#c00"><?php echo esc_html($exp); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- TOP 5 쿠폰 -->
        <div class="psc-admin-section">
            <h2>🏆 발급 수 TOP 5 쿠폰</h2>
            <?php if ( empty($top5) ) : ?>
                <p style="color:#aaa;text-align:center;padding:20px 0">데이터 없음</p>
            <?php else : ?>
                <table class="psc-admin-table">
                    <thead>
                        <tr>
                            <th>쿠폰명</th><th>매장</th><th>할인</th>
                            <th>발급</th><th>사용</th><th>사용률</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($top5 as $row) :
                        $c      = get_post($row->coupon_id);
                        if (!$c) continue;
                        $sid    = (int) get_post_meta($row->coupon_id, PSC_COUPON_STORE_META, true);
                        $rate   = $row->issued > 0 ? round($row->used / $row->issued * 100) : 0;
                    ?>
                        <tr>
                            <td><a href="<?php echo get_edit_post_link($row->coupon_id); ?>"><?php echo esc_html($c->post_title); ?></a></td>
                            <td><?php echo $sid ? esc_html(get_the_title($sid)) : '—'; ?></td>
                            <td><?php echo esc_html(psc_coupon_format_value($row->coupon_id)); ?></td>
                            <td><?php echo (int)$row->issued; ?>장</td>
                            <td><?php echo (int)$row->used; ?>장</td>
                            <td>
                                <div style="background:#f3f4f6;border-radius:4px;height:8px;width:80px;overflow:hidden">
                                    <div style="background:#667eea;height:100%;width:<?php echo $rate; ?>%"></div>
                                </div>
                                <small><?php echo $rate; ?>%</small>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

/* ────────────────────────────────────────────
   A. 발급 내역 조회
   ──────────────────────────────────────────── */
function psc_coupon_admin_issued(): void {
    global $wpdb;
    $table = $wpdb->prefix . PSC_COUPON_TABLE;

    /* 필터 */
    $filter_coupon = (int) ( $_GET['coupon_id'] ?? 0 );
    $filter_store  = (int) ( $_GET['store_id']  ?? 0 );
    $filter_used   = $_GET['is_used'] ?? '';
    $filter_token  = sanitize_text_field( $_GET['token'] ?? '' );
    $paged         = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
    $per_page      = 20;
    $offset        = ( $paged - 1 ) * $per_page;

    /* WHERE 조건 */
    $where  = 'WHERE 1=1';
    $params = [];

    if ( $filter_token ) {
        $where   .= ' AND i.token LIKE %s';
        $params[] = '%' . $wpdb->esc_like( $filter_token ) . '%';
    }
    if ( $filter_coupon ) {
        $where   .= ' AND i.coupon_id = %d';
        $params[] = $filter_coupon;
    }
    if ( $filter_store ) {
        $store_coupon_ids = get_posts( [
            'post_type'      => PSC_COUPON_CPT,
            'post_status'    => [ 'publish', 'draft' ],
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [ [
                'key'   => PSC_COUPON_STORE_META,
                'value' => $filter_store,
            ] ],
        ] );
        if ( ! empty( $store_coupon_ids ) ) {
            $ids_in  = implode( ',', array_map( 'intval', $store_coupon_ids ) );
            $where  .= " AND i.coupon_id IN ({$ids_in})";
        } else {
            $where  .= ' AND 1=0';
        }
    }
    if ( $filter_used !== '' ) {
        $where   .= ' AND i.is_used = %d';
        $params[] = (int) $filter_used;
    }

    /* 전체 카운트 */
    $count_sql = "SELECT COUNT(*) FROM {$table} i {$where}";
    $total     = empty( $params )
        ? (int) $wpdb->get_var( $count_sql )
        : (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) );

    /* 목록 조회 */
    $list_params   = $params;
    $list_params[] = $per_page;
    $list_params[] = $offset;
    $list_sql      = "SELECT i.* FROM {$table} i {$where} ORDER BY i.issued_at DESC LIMIT %d OFFSET %d";
    $rows          = empty( $params )
        ? $wpdb->get_results( $wpdb->prepare( $list_sql, $per_page, $offset ) )
        : $wpdb->get_results( $wpdb->prepare( $list_sql, $list_params ) );

    $pages = ceil( $total / $per_page );

    /* 필터용 목록 */
    $all_coupons = get_posts( [
        'post_type'      => PSC_COUPON_CPT,
        'post_status'    => [ 'publish', 'draft' ],
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ] );
    $all_stores = get_posts( [
        'post_type'      => defined( 'PSC_STORE_CPT' ) ? PSC_STORE_CPT : 'store',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ] );
    ?>
    <div class="wrap psc-admin-wrap">
        <h1>📋 쿠폰 발급 내역</h1>

        <!-- 필터 -->
        <form method="get" class="psc-admin-filter">
            <input type="hidden" name="page" value="psc-coupon-issued">
            <input type="text" name="token" placeholder="토큰 검색"
                   value="<?php echo esc_attr( $filter_token ); ?>">
            <select name="store_id">
                <option value="">— 전체 매장 —</option>
                <?php foreach ( $all_stores as $s ) : ?>
                    <option value="<?php echo $s->ID; ?>" <?php selected( $filter_store, $s->ID ); ?>>
                        <?php echo esc_html( $s->post_title ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="coupon_id">
                <option value="">— 전체 쿠폰 —</option>
                <?php foreach ( $all_coupons as $c ) : ?>
                    <option value="<?php echo $c->ID; ?>" <?php selected( $filter_coupon, $c->ID ); ?>>
                        <?php echo esc_html( $c->post_title ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="is_used">
                <option value="">— 전체 상태 —</option>
                <option value="0" <?php selected( $filter_used, '0' ); ?>>미사용</option>
                <option value="1" <?php selected( $filter_used, '1' ); ?>>사용됨</option>
            </select>
            <button type="submit" class="button button-primary">검색</button>
            <a href="?page=psc-coupon-issued" class="button">초기화</a>
        </form>

        <p style="color:#888;margin-bottom:12px">총 <strong><?php echo number_format( $total ); ?></strong>건</p>

        <table class="psc-admin-table">
            <thead>
                <tr>
                    <th>토큰</th><th>쿠폰명</th><th>매장</th>
                    <th>사용자</th><th>발급일시</th><th>사용일시</th><th>상태</th>
                </tr>
            </thead>
            <tbody>
            <?php if ( empty( $rows ) ) : ?>
                <tr><td colspan="7" style="text-align:center;color:#aaa;padding:30px">내역 없음</td></tr>
            <?php else : ?>
                <?php foreach ( $rows as $row ) :
                    $coupon = get_post( $row->coupon_id );
                    $user   = get_userdata( $row->user_id );
                    $sid    = $coupon ? (int) get_post_meta( $row->coupon_id, PSC_COUPON_STORE_META, true ) : 0;
                    $is_exp = $coupon ? psc_coupon_is_expired( (int) $row->coupon_id ) : false;
                ?>
                    <tr>
                        <td><code style="font-size:.78rem"><?php echo esc_html( substr( $row->token, 0, 12 ) ) . '...'; ?></code></td>
                        <td>
                            <?php if ( $coupon ) : ?>
                                <a href="<?php echo get_edit_post_link( $row->coupon_id ); ?>">
                                    <?php echo esc_html( $coupon->post_title ); ?>
                                </a>
                            <?php else : ?>
                                <span style="color:#aaa">삭제된 쿠폰</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $sid ? esc_html( get_the_title( $sid ) ) : '—'; ?></td>
                        <td>
                            <?php if ( $user ) : ?>
                                <a href="<?php echo get_edit_user_link( $row->user_id ); ?>">
                                    <?php echo esc_html( $user->display_name ); ?>
                                </a>
                                <small style="color:#aaa;display:block"><?php echo esc_html( $user->user_login ); ?></small>
                            <?php else : ?>
                                <span style="color:#aaa">탈퇴 회원</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:.82rem"><?php echo esc_html( $row->issued_at ); ?></td>
                        <td style="font-size:.82rem"><?php echo $row->used_at ? esc_html( $row->used_at ) : '—'; ?></td>
                        <td>
                            <?php if ( $row->is_used ) : ?>
                                <span class="psc-admin-badge psc-admin-badge--used">✅ 사용됨</span>
                            <?php elseif ( $is_exp ) : ?>
                                <span class="psc-admin-badge psc-admin-badge--expired">만료</span>
                            <?php else : ?>
                                <span class="psc-admin-badge psc-admin-badge--unused">미사용</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>

        <!-- 페이지네이션 -->
        <?php if ( $pages > 1 ) : ?>
            <div style="margin-top:16px">
                <?php echo paginate_links( [
                    'base'    => add_query_arg( 'paged', '%#%' ),
                    'format'  => '',
                    'current' => $paged,
                    'total'   => $pages,
                ] ); ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

/* ────────────────────────────────────────────
   B. 쿠폰 강제 사용 처리
   ──────────────────────────────────────────── */
function psc_coupon_admin_use_page(): void {
    global $wpdb;
    $table = $wpdb->prefix . PSC_COUPON_TABLE;

    $result_msg   = '';
    $result_type  = '';
    $found_row    = null;

    /* 사용 확정 처리 */
    if ( isset($_POST['confirm_use_token']) && check_admin_referer('psc_admin_use_coupon') ) {
        $token = sanitize_text_field($_POST['confirm_use_token']);
        $ok    = psc_coupon_use($token);
        if ($ok === true) {
            $result_msg  = '✅ 쿠폰이 정상적으로 사용 처리되었습니다.';
            $result_type = 'success';
        } else {
            $result_msg  = '❌ ' . ( is_wp_error($ok) ? $ok->get_error_message() : '처리 실패' );
            $result_type = 'error';
        }
    }

    /* 토큰 검색 */
    if ( isset($_POST['search_token']) && check_admin_referer('psc_admin_search_token') ) {
        $token     = sanitize_text_field($_POST['search_token']);
        $found_row = psc_coupon_get_issued_by_token($token);
        if (!$found_row) {
            $result_msg  = '❌ 해당 토큰을 찾을 수 없습니다.';
            $result_type = 'error';
        }
    }
    ?>
    <div class="wrap psc-admin-wrap">
        <h1>✅ 쿠폰 사용 처리</h1>

        <?php if ($result_msg) : ?>
            <div class="notice notice-<?php echo $result_type === 'success' ? 'success' : 'error'; ?> is-dismissible">
                <p><?php echo esc_html($result_msg); ?></p>
            </div>
        <?php endif; ?>

        <!-- 토큰 검색 폼 -->
        <div class="psc-admin-section">
            <h2>🔍 토큰으로 쿠폰 검색</h2>
            <form method="post">
                <?php wp_nonce_field('psc_admin_search_token'); ?>
                <div style="display:flex;gap:10px;align-items:flex-end">
                    <div>
                        <label style="display:block;font-size:.85rem;margin-bottom:4px;font-weight:600">쿠폰 토큰</label>
                        <input type="text" name="search_token"
                               value="<?php echo esc_attr($_POST['search_token'] ?? ''); ?>"
                               placeholder="토큰 전체 입력"
                               style="width:400px;padding:8px 12px;border:1px solid #ddd;border-radius:6px;font-family:monospace">
                    </div>
                    <button type="submit" class="button button-primary">검색</button>
                </div>
            </form>
        </div>

        <!-- 검색 결과 -->
        <?php if ($found_row) :
            $coupon  = get_post($found_row->coupon_id);
            $user    = get_userdata($found_row->user_id);
            $sid     = $coupon ? (int)get_post_meta($found_row->coupon_id, PSC_COUPON_STORE_META, true) : 0;
            $is_exp  = $coupon ? psc_coupon_is_expired((int)$found_row->coupon_id) : false;
        ?>
            <div class="psc-admin-section">
                <h2>쿠폰 정보</h2>
                <table class="form-table" style="max-width:600px">
                    <tr><th>쿠폰명</th><td><?php echo $coupon ? esc_html($coupon->post_title) : '삭제된 쿠폰'; ?></td></tr>
                    <tr><th>매장</th><td><?php echo $sid ? esc_html(get_the_title($sid)) : '—'; ?></td></tr>
                    <tr><th>할인</th><td><?php echo $coupon ? esc_html(psc_coupon_format_value((int)$found_row->coupon_id)) : '—'; ?></td></tr>
                    <tr><th>발급 사용자</th><td>
                        <?php echo $user ? esc_html($user->display_name . ' (' . $user->user_login . ')') : '탈퇴 회원'; ?>
                    </td></tr>
                    <tr><th>발급일시</th><td><?php echo esc_html($found_row->issued_at); ?></td></tr>
                    <tr><th>상태</th><td>
                        <?php if ($found_row->is_used) : ?>
                            <span class="psc-admin-badge psc-admin-badge--used">✅ 사용됨 (<?php echo esc_html($found_row->used_at); ?>)</span>
                        <?php elseif ($is_exp) : ?>
                            <span class="psc-admin-badge psc-admin-badge--expired">만료됨</span>
                        <?php else : ?>
                            <span class="psc-admin-badge psc-admin-badge--unused">미사용</span>
                        <?php endif; ?>
                    </td></tr>
                    <tr><th>토큰</th><td><code><?php echo esc_html($found_row->token); ?></code></td></tr>
                </table>

                <?php if (!$found_row->is_used && !$is_exp) : ?>
                    <form method="post" style="margin-top:16px">
                        <?php wp_nonce_field('psc_admin_use_coupon'); ?>
                        <input type="hidden" name="confirm_use_token" value="<?php echo esc_attr($found_row->token); ?>">
                        <button type="submit" class="button button-primary"
                                onclick="return confirm('이 쿠폰을 사용 처리하시겠습니까?')">
                            ✅ 사용 확정
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
}
/* ────────────────────────────────────────────
   D. 매장별 쿠폰 현황
   ──────────────────────────────────────────── */
function psc_coupon_admin_stores(): void {
    global $wpdb;
    $table = $wpdb->prefix . PSC_COUPON_TABLE;

    $stores = get_posts([
        'post_type'      => defined('PSC_STORE_CPT') ? PSC_STORE_CPT : 'store',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ]);
    ?>
    <div class="wrap psc-admin-wrap">
        <h1>🏪 매장별 쿠폰 현황</h1>

        <table class="psc-admin-table">
            <thead>
                <tr>
                    <th>매장명</th>
                    <th>플랜</th>
                    <th>발행 쿠폰 수</th>
                    <th>총 발급</th>
                    <th>총 사용</th>
                    <th>사용률</th>
                    <th>관리</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($stores as $store) :
                $sid        = $store->ID;
                $is_premium = psc_store_is_premium($sid);

                /* 이 매장의 쿠폰들 */
                $coupons = get_posts([
                    'post_type'      => PSC_COUPON_CPT,
                    'post_status'    => ['publish','draft'],
                    'posts_per_page' => -1,
                    'meta_query'     => [[
                        'key'   => PSC_COUPON_STORE_META,
                        'value' => $sid,
                    ]],
                ]);

                $coupon_ids = array_column($coupons, 'ID');
                $active_cnt = count(array_filter($coupons, fn($c) => $c->post_status === 'publish'));

                $total_issued = 0;
                $total_used   = 0;
                if (!empty($coupon_ids)) {
                    $ids_in = implode(',', array_map('intval', $coupon_ids));
                    $total_issued = (int)$wpdb->get_var(
                        "SELECT COUNT(*) FROM {$table} WHERE coupon_id IN ({$ids_in})"
                    );
                    $total_used = (int)$wpdb->get_var(
                        "SELECT COUNT(*) FROM {$table} WHERE coupon_id IN ({$ids_in}) AND is_used=1"
                    );
                }
                $rate = $total_issued > 0 ? round($total_used / $total_issued * 100) : 0;
            ?>
                <tr>
                    <td>
                        <a href="<?php echo get_edit_post_link($sid); ?>">
                            <strong><?php echo esc_html($store->post_title); ?></strong>
                        </a>
                    </td>
                    <td>
                        <!-- E. 플랜 관리 연동 인라인 폼 -->
                        <form method="post" style="display:inline">
                            <?php wp_nonce_field('psc_change_plan_' . $sid, 'psc_plan_nonce'); ?>
                            <input type="hidden" name="psc_change_plan_store_id" value="<?php echo $sid; ?>">
                            <select name="psc_new_plan"
                                    onchange="this.form.submit()"
                                    style="padding:4px 8px;border-radius:6px;border:1px solid #ddd;font-size:.82rem">
                                <option value="free"    <?php selected(!$is_premium); ?>>무료</option>
                                <option value="premium" <?php selected($is_premium);  ?>>★ 프리미엄</option>
                            </select>
                        </form>
                    </td>
                    <td>
                        <?php echo $active_cnt; ?>개 발행중
                        <?php if (count($coupons) > $active_cnt) : ?>
                            <small style="color:#aaa">/ <?php echo count($coupons) - $active_cnt; ?>개 임시저장</small>
                        <?php endif; ?>
                    </td>
                    <td><?php echo number_format($total_issued); ?>장</td>
                    <td><?php echo number_format($total_used); ?>장</td>
                    <td>
                        <div style="display:flex;align-items:center;gap:6px">
                            <div style="background:#f3f4f6;border-radius:4px;height:8px;width:60px;overflow:hidden">
                                <div style="background:#667eea;height:100%;width:<?php echo $rate; ?>%"></div>
                            </div>
                            <small><?php echo $rate; ?>%</small>
                        </div>
                    </td>
                    <td>
                        <a href="<?php echo admin_url('admin.php?page=psc-coupon-issued&store_id=' . $sid); ?>"
                           class="button button-small">발급 내역</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

/* ────────────────────────────────────────────
   E. 플랜 변경 저장 처리
   ──────────────────────────────────────────── */
add_action( 'admin_init', 'psc_coupon_admin_handle_plan_change' );

function psc_coupon_admin_handle_plan_change(): void {
    if ( empty($_POST['psc_change_plan_store_id']) ) return;
    if ( ! current_user_can('manage_options') ) return;

    $store_id = (int) $_POST['psc_change_plan_store_id'];
    if ( ! wp_verify_nonce( $_POST['psc_plan_nonce'] ?? '', 'psc_change_plan_' . $store_id ) ) return;

    $new_plan = $_POST['psc_new_plan'] === 'premium' ? 'premium' : 'free';

    /* ACF 필드로 저장 */
    if ( function_exists('update_field') ) {
        update_field( 'store_plan', $new_plan, $store_id );
    } else {
        update_post_meta( $store_id, 'store_plan', $new_plan );
    }

    /* 무료로 다운그레이드 시 쿠폰 전부 draft 처리 */
    if ( $new_plan === 'free' ) {
        $coupons = get_posts([
            'post_type'      => PSC_COUPON_CPT,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => [[
                'key'   => PSC_COUPON_STORE_META,
                'value' => $store_id,
            ]],
        ]);
        foreach ($coupons as $c) {
            wp_update_post(['ID' => $c->ID, 'post_status' => 'draft']);
        }
    }

    wp_redirect( add_query_arg(
        ['page' => 'psc-coupon-stores', 'updated' => 1],
        admin_url('admin.php')
    ));
    exit;
}


