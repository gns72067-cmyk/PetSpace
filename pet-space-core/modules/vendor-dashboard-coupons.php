<?php
/**
 * Vendor Dashboard – Coupon Management
 * File: modules/vendor-dashboard-coupons.php
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/* ============================================================
   0. 대시보드 전용 헬퍼 (coupon.php 함수명 불일치 보완)
   ============================================================ */

/**
 * 쿠폰 총 발행 수
 */
if ( ! function_exists( 'psc_coupon_issued_count' ) ) {
    function psc_coupon_issued_count( int $coupon_id ): int {
        global $wpdb;
        $table = $wpdb->prefix . PSC_COUPON_TABLE;
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM `{$table}` WHERE coupon_id = %d",
            $coupon_id
        ));
    }
}

/**
 * 쿠폰 총 사용 수
 */
if ( ! function_exists( 'psc_coupon_used_count' ) ) {
    function psc_coupon_used_count( int $coupon_id ): int {
        global $wpdb;
        $table = $wpdb->prefix . PSC_COUPON_TABLE;
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM `{$table}` WHERE coupon_id = %d AND is_used = 1",
            $coupon_id
        ));
    }
}

/**
 * 할인값 포맷 — coupon.php 는 ($coupon_id) 방식이므로
 * 대시보드에서는 ($type, $value) 방식 별도 함수로 사용
 */
if ( ! function_exists( 'psc_coupon_format_discount' ) ) {
    function psc_coupon_format_discount( string $type, $value ): string {
        if ( $type === 'fixed' ) {
            return number_format( (float) $value ) . '원 할인';
        }
        return (float) $value . '% 할인';
    }
}

/**
 * 토큰으로 발급 레코드 조회 — coupon.php 실제 함수명 alias
 */
if ( ! function_exists( 'psc_coupon_get_by_token' ) ) {
    function psc_coupon_get_by_token( string $token ): ?object {
        return psc_coupon_get_issued_by_token( $token );
    }
}

/**
 * 유저가 해당 매장 소유자인지 확인
 */
if ( ! function_exists( 'psc_user_owns_store' ) ) {
    function psc_user_owns_store( int $user_id, int $store_id ): bool {
        if ( ! $store_id || ! $user_id ) return false;
        $post = get_post( $store_id );
        if ( ! $post ) return false;
        $cpt = defined( 'PSC_STORE_CPT' ) ? PSC_STORE_CPT : 'store';
        if ( $post->post_type !== $cpt ) return false;
        return (int) $post->post_author === $user_id;
    }
}

/* ============================================================
   1. 쿠폰 목록 페이지
   ============================================================ */
function psc_dash_page_coupons( int $store_id, int $user_id ): void {

    /* 플랜 확인 */
    $plan       = get_post_meta( $store_id, 'store_plan', true );
    $is_premium = ( $plan === 'premium' );

    /* URL 파라미터 메시지 */
    if ( ! empty( $_GET['saved'] ) ) {
        echo '<div class="psc-dash-alert psc-dash-alert--success">✅ 쿠폰이 저장되었습니다.</div>';
    }
    if ( ! empty( $_GET['deleted'] ) ) {
        echo '<div class="psc-dash-alert psc-dash-alert--success">🗑️ 쿠폰이 삭제되었습니다.</div>';
    }
    if ( ! empty( $_GET['error'] ) ) {
        $err_map = [
            'save_failed'   => '저장 중 오류가 발생했습니다.',
            'free_plan'     => '무료 플랜은 쿠폰을 발행할 수 없습니다.',
            'invalid_nonce' => '보안 토큰이 유효하지 않습니다.',
            'no_title'      => '쿠폰 제목을 입력해 주세요.',
            'no_value'      => '할인 값을 입력해 주세요.',
        ];
        $msg = $err_map[ sanitize_key( $_GET['error'] ) ] ?? '오류가 발생했습니다.';
        echo '<div class="psc-dash-alert psc-dash-alert--error">❌ ' . esc_html( $msg ) . '</div>';
    }

    /* 무료 플랜 유도 화면 */
    if ( ! $is_premium ) {
        echo psc_coupon_free_plan_screen();
        psc_coupon_inline_css();
        return;
    }

    /* 쿠폰 목록 조회 */
    $coupons = get_posts( [
        'post_type'      => PSC_COUPON_CPT,
        'post_status'    => [ 'publish', 'draft' ],
        'posts_per_page' => -1,
        'meta_query'     => [ [
            'key'   => PSC_COUPON_STORE_META,
            'value' => $store_id,
        ] ],
    ] );
    ?>

    <div class="psc-dash-page-header">
    <h2 class="psc-dash-page-title">🎟️ 쿠폰 관리</h2>
    <div style="display:flex;gap:8px;align-items:center">
        <a href="<?php echo esc_url( home_url( '/vendor/coupons/stats/' ) ); ?>"
           class="psc-dash-btn psc-dash-btn--outline psc-dash-btn--sm">📊 현황</a>
        <a href="<?php echo esc_url( home_url( '/vendor/coupons/new/' ) ); ?>"
           class="psc-dash-btn psc-dash-btn--primary psc-dash-btn--sm">+ 쿠폰 만들기</a>
    </div>
</div>

    <?php if ( empty( $coupons ) ) : ?>
        <div class="psc-coupon-empty">
            <p>🎟️ 아직 발행된 쿠폰이 없습니다.</p>
            <a href="<?php echo esc_url( home_url( '/vendor/coupons/new/' ) ); ?>"
               class="psc-dash-btn psc-dash-btn--primary">첫 쿠폰 만들기</a>
        </div>
    <?php else : ?>
        <div class="psc-coupon-list-wrap">
        <?php foreach ( $coupons as $coupon ) :
            $coupon_id    = $coupon->ID;
            $type         = get_post_meta( $coupon_id, 'coupon_type',        true );
            $value        = get_post_meta( $coupon_id, 'coupon_value',       true );
            $expire       = get_post_meta( $coupon_id, 'coupon_expire_date', true );
            $max_issue    = (int) get_post_meta( $coupon_id, 'coupon_max_issue', true );
            $issued_count = psc_coupon_issued_count( $coupon_id );
            $used_count   = psc_coupon_used_count( $coupon_id );
            $is_expired   = $expire && strtotime( $expire ) < time();
            $is_full      = $max_issue > 0 && $issued_count >= $max_issue;

            if ( $is_expired ) {
                $status_label = '<span class="psc-badge psc-badge--danger">만료</span>';
            } elseif ( $is_full ) {
                $status_label = '<span class="psc-badge psc-badge--warning">마감</span>';
            } elseif ( $coupon->post_status === 'publish' ) {
                $status_label = '<span class="psc-badge psc-badge--success">발행중</span>';
            } else {
                $status_label = '<span class="psc-badge psc-badge--gray">임시저장</span>';
            }

            $disc_label = psc_coupon_format_discount( $type, $value );
        ?>
            <div class="psc-coupon-card">
                <div class="psc-coupon-card__top">
                    <div class="psc-coupon-card__title">
                        <?php echo esc_html( $coupon->post_title ); ?>
                        <?php echo $status_label; ?>
                    </div>
                    <div class="psc-coupon-card__value"><?php echo esc_html( $disc_label ); ?></div>
                </div>
                <div class="psc-coupon-card__meta">
                    <span>📤 발행
                        <?php echo $issued_count;
                        echo $max_issue > 0 ? ' / ' . $max_issue : ''; ?>장
                    </span>
                    <span>✅ 사용 <?php echo $used_count; ?>장</span>
                    <?php if ( $expire ) : ?>
                        <span>📅 <?php echo esc_html( $expire ); ?> 까지</span>
                    <?php endif; ?>
                </div>
                <div class="psc-coupon-card__actions">
    <a href="<?php echo esc_url( home_url( '/vendor/coupons/use/' ) ); ?>"
       class="psc-dash-btn psc-dash-btn--sm psc-dash-btn--success">🔍 사용 처리</a>
    <a href="<?php echo esc_url( add_query_arg(
            'coupon_id', $coupon_id,
            home_url( '/vendor/coupons/edit/' ) ) ); ?>"
       class="psc-dash-btn psc-dash-btn--sm">✏️ 수정</a>
    <a href="<?php echo esc_url( wp_nonce_url(
            add_query_arg(
                [ 'action' => 'delete_coupon', 'coupon_id' => $coupon_id ],
                home_url( '/vendor/coupons/' )
            ),
            'psc_delete_coupon_' . $coupon_id
        ) ); ?>"
       class="psc-dash-btn psc-dash-btn--sm psc-dash-btn--danger"
       onclick="return confirm('쿠폰을 삭제하시겠습니까?')">🗑️ 삭제</a>
</div>

            </div>
        <?php endforeach; ?>
        </div>
    <?php endif;

    psc_coupon_inline_css();
}

/* ============================================================
   삭제 처리
   ============================================================ */
add_action( 'template_redirect', function () {
    if ( get_query_var( 'psc_dash' ) !== 'coupons' ) return;
    if ( empty( $_GET['action'] ) || $_GET['action'] !== 'delete_coupon' ) return;
    if ( ! is_user_logged_in() ) return;

    $coupon_id = (int) ( $_GET['coupon_id'] ?? 0 );
    if ( ! $coupon_id ) return;
    if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'psc_delete_coupon_' . $coupon_id ) ) return;

    $store_id = (int) get_post_meta( $coupon_id, PSC_COUPON_STORE_META, true );
    if ( ! psc_user_owns_store( get_current_user_id(), $store_id ) ) return;

    wp_delete_post( $coupon_id, true );
    wp_redirect( home_url( '/vendor/coupons/?deleted=1' ) );
    exit;
} );

/* ============================================================
   쿠폰 저장 조기 처리 (헤더 출력 전 redirect)
   ============================================================ */
add_action( 'template_redirect', function () {

    $page = get_query_var( 'psc_dash' );
    if ( ! in_array( $page, [ 'coupons_new', 'coupons_edit' ], true ) ) return;
    if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) return;
    if ( empty( $_POST['psc_coupon_nonce'] ) ) return;
    if ( ! is_user_logged_in() ) return;

    /* store_id 가져오기 */
    $user_id   = get_current_user_id();
    $store_ids = psc_vendor_store_ids( $user_id );
    if ( empty( $store_ids ) ) return;

    /* 선택된 매장 확인 */
    $store_id = 0;
    if ( count( $store_ids ) === 1 ) {
        $store_id = (int) $store_ids[0];
    } elseif ( isset( $_COOKIE[ 'psc_vendor_store_' . $user_id ] ) ) {
        $sid = absint( $_COOKIE[ 'psc_vendor_store_' . $user_id ] );
        if ( in_array( $sid, array_map( 'intval', $store_ids ), true ) ) {
            $store_id = $sid;
        }
    }
    if ( ! $store_id ) return;

    /* 편집 시 coupon_id */
    $coupon_id = (int) ( $_POST['coupon_id'] ?? $_GET['coupon_id'] ?? 0 );

    /* 저장 실행 */
    $result = psc_dash_save_coupon( $store_id, $coupon_id );

    if ( is_wp_error( $result ) ) {
        $code = $result->get_error_code();
        if ( $coupon_id ) {
            wp_redirect( add_query_arg(
                [ 'coupon_id' => $coupon_id, 'error' => $code ],
                home_url( '/vendor/coupons/edit/' )
            ));
        } else {
            wp_redirect( home_url( '/vendor/coupons/new/?error=' . $code ) );
        }
        exit;
    }

    // 성공은 psc_dash_save_coupon 내부에서 redirect+exit 처리
}, 5 ); // priority 5 — 메인 라우터(10)보다 먼저 실행


/* ============================================================
   2. 쿠폰 생성 페이지
   ============================================================ */
function psc_dash_page_coupon_new( int $store_id, int $user_id ): void {
    $plan       = get_post_meta( $store_id, 'store_plan', true );
    $is_premium = ( $plan === 'premium' );

    if ( ! $is_premium ) {
        echo psc_coupon_free_plan_screen();
        psc_coupon_inline_css();
        return;
    }

    $error = '';
    if ( $_SERVER['REQUEST_METHOD'] === 'POST' && ! empty( $_POST['psc_coupon_nonce'] ) ) {
        $result = psc_dash_save_coupon( $store_id );
        if ( is_wp_error( $result ) ) {
            $error = $result->get_error_code();
        }
    }

    if ( $error ) {
        echo '<div class="psc-dash-alert psc-dash-alert--error">❌ '
           . esc_html( psc_coupon_error_msg( $error ) ) . '</div>';
    }

    echo '<div class="psc-dash-page-header">'
       . '<a href="' . esc_url( home_url( '/vendor/coupons/' ) ) . '" class="psc-back-link">← 목록으로</a>'
       . '<h2 class="psc-dash-page-title">쿠폰 만들기</h2></div>';

    psc_coupon_form( $store_id, null );
    psc_coupon_inline_css();
}

/* ============================================================
   3. 쿠폰 편집 페이지
   ============================================================ */
function psc_dash_page_coupon_edit( int $store_id, int $user_id ): void {
    $coupon_id = (int) ( $_GET['coupon_id'] ?? 0 );
    if ( ! $coupon_id ) { wp_redirect( home_url( '/vendor/coupons/' ) ); exit; }

    $coupon = get_post( $coupon_id );
    if ( ! $coupon || $coupon->post_type !== PSC_COUPON_CPT ) {
        wp_redirect( home_url( '/vendor/coupons/' ) ); exit;
    }

    $owner_store = (int) get_post_meta( $coupon_id, PSC_COUPON_STORE_META, true );
    if ( $owner_store !== $store_id ) {
        wp_redirect( home_url( '/vendor/coupons/' ) ); exit;
    }

    $error = '';
    if ( $_SERVER['REQUEST_METHOD'] === 'POST' && ! empty( $_POST['psc_coupon_nonce'] ) ) {
        $result = psc_dash_save_coupon( $store_id, $coupon_id );
        if ( is_wp_error( $result ) ) {
            $error = $result->get_error_code();
        }
    }
    if ( $error ) {
        echo '<div class="psc-dash-alert psc-dash-alert--error">❌ '
           . esc_html( psc_coupon_error_msg( $error ) ) . '</div>';
    }

    echo '<div class="psc-dash-page-header">'
       . '<a href="' . esc_url( home_url( '/vendor/coupons/' ) ) . '" class="psc-back-link">← 목록으로</a>'
       . '<h2 class="psc-dash-page-title">쿠폰 수정</h2></div>';

    psc_coupon_form( $store_id, $coupon_id );
    psc_coupon_inline_css();
}

/* ============================================================
   4. 쿠폰 저장
   ============================================================ */
function psc_dash_save_coupon( int $store_id, int $coupon_id = 0 ): mixed {

    if ( ! wp_verify_nonce( $_POST['psc_coupon_nonce'] ?? '', 'psc_coupon_nonce_action' ) ) {
        return new WP_Error( 'invalid_nonce', '보안 토큰 오류' );
    }

    $plan = get_post_meta( $store_id, 'store_plan', true );
    if ( $plan !== 'premium' ) {
        return new WP_Error( 'free_plan', '무료 플랜은 쿠폰 발행 불가' );
    }

    $title        = sanitize_text_field( $_POST['coupon_title']        ?? '' );
    $desc         = sanitize_textarea_field( $_POST['coupon_desc']     ?? '' );
    $type         = in_array( $_POST['coupon_type'] ?? '', [ 'percent', 'fixed' ], true )
                    ? $_POST['coupon_type'] : 'percent';
    $value        = (float) ( $_POST['coupon_value']        ?? 0 );
    $expire       = sanitize_text_field( $_POST['coupon_expire_date']  ?? '' );
    $max_issue    = (int)   ( $_POST['coupon_max_issue']    ?? 0 );
    $max_per_user = (int)   ( $_POST['coupon_max_per_user'] ?? 1 );
    $status       = ( ( $_POST['coupon_status'] ?? 'draft' ) === 'publish' ) ? 'publish' : 'draft';

    if ( $title === '' ) return new WP_Error( 'no_title', '제목 필요' );
    if ( $value <= 0  ) return new WP_Error( 'no_value', '할인값 필요' );
    if ( $type === 'percent' && $value > 100 ) $value = 100;

    $post_data = [
        'post_type'    => PSC_COUPON_CPT,
        'post_title'   => $title,
        'post_status'  => $status,
        'post_content' => $desc,
    ];

    if ( $coupon_id > 0 ) {
        $post_data['ID'] = $coupon_id;
        $result = wp_update_post( $post_data, true );
    } else {
        $result = wp_insert_post( $post_data, true );
    }

    if ( is_wp_error( $result ) ) return new WP_Error( 'save_failed', '저장 실패' );

    $saved_id = (int) $result;
    update_post_meta( $saved_id, PSC_COUPON_STORE_META,   $store_id     );
    update_post_meta( $saved_id, 'coupon_type',            $type         );
    update_post_meta( $saved_id, 'coupon_value',           $value        );
    update_post_meta( $saved_id, 'coupon_desc',            $desc         );
    update_post_meta( $saved_id, 'coupon_expire_date',     $expire       );
    update_post_meta( $saved_id, 'coupon_max_issue',       $max_issue    );
    update_post_meta( $saved_id, 'coupon_max_per_user',    $max_per_user );

    wp_redirect( home_url( '/vendor/coupons/?saved=1' ) );
    exit;
}

/* ============================================================
   5. 쿠폰 폼 렌더
   ============================================================ */
function psc_coupon_form( int $store_id, ?int $coupon_id ): void {
    $title        = '';
    $desc         = '';
    $type         = 'percent';
    $value        = '';
    $expire       = '';
    $max_issue    = '';
    $max_per_user = 1;
    $status       = 'publish';

    if ( $coupon_id ) {
        $p            = get_post( $coupon_id );
        $title        = $p->post_title;
        $desc         = $p->post_content;
        $status       = $p->post_status;
        $type         = get_post_meta( $coupon_id, 'coupon_type',        true ) ?: 'percent';
        $value        = get_post_meta( $coupon_id, 'coupon_value',       true );
        $expire       = get_post_meta( $coupon_id, 'coupon_expire_date', true );
        $max_issue    = get_post_meta( $coupon_id, 'coupon_max_issue',   true );
        $max_per_user = (int) get_post_meta( $coupon_id, 'coupon_max_per_user', true ) ?: 1;
    }
    ?>
    <form method="post" class="psc-coupon-form">
        <?php wp_nonce_field( 'psc_coupon_nonce_action', 'psc_coupon_nonce' ); ?>
        <?php if ( $coupon_id ) : ?>
            <input type="hidden" name="coupon_id" value="<?php echo $coupon_id; ?>">
        <?php endif; ?>

        <div class="psc-dash-card">
            <div class="psc-dash-card__body">

                <div class="psc-form-group">
                    <label class="psc-form-label">쿠폰 제목 <span class="req">*</span></label>
                    <input type="text" name="coupon_title" class="psc-form-input"
                           value="<?php echo esc_attr( $title ); ?>"
                           placeholder="예: 신규 방문 10% 할인" required>
                </div>

                <div class="psc-form-group">
                    <label class="psc-form-label">쿠폰 설명</label>
                    <textarea name="coupon_desc" class="psc-form-input" rows="3"
                              placeholder="고객에게 보여질 쿠폰 설명을 입력하세요"><?php echo esc_textarea( $desc ); ?></textarea>
                </div>

                <div class="psc-form-row">
                    <div class="psc-form-group">
                        <label class="psc-form-label">할인 타입 <span class="req">*</span></label>
                        <select name="coupon_type" class="psc-form-input" id="coupon_type_select">
                            <option value="percent" <?php selected( $type, 'percent' ); ?>>할인율 (%)</option>
                            <option value="fixed"   <?php selected( $type, 'fixed'   ); ?>>정액 (원)</option>
                        </select>
                    </div>
                    <div class="psc-form-group">
                        <label class="psc-form-label">할인 값 <span class="req">*</span></label>
                        <div class="psc-input-with-unit">
                            <input type="number" name="coupon_value" class="psc-form-input"
                                   value="<?php echo esc_attr( $value ); ?>" min="1" step="0.1" required>
                            <span class="psc-input-unit" id="coupon_unit">
                                <?php echo $type === 'fixed' ? '원' : '%'; ?>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="psc-form-row">
                    <div class="psc-form-group">
                        <label class="psc-form-label">만료일</label>
                        <input type="date" name="coupon_expire_date" class="psc-form-input"
                               value="<?php echo esc_attr( $expire ); ?>">
                    </div>
                    <div class="psc-form-group">
                        <label class="psc-form-label">최대 발행 수 <small>(0=무제한)</small></label>
                        <input type="number" name="coupon_max_issue" class="psc-form-input"
                               value="<?php echo esc_attr( $max_issue ); ?>" min="0">
                    </div>
                </div>

                <div class="psc-form-group">
                    <label class="psc-form-label">1인 최대 사용 횟수</label>
                    <input type="number" name="coupon_max_per_user" class="psc-form-input"
                           value="<?php echo esc_attr( $max_per_user ); ?>" min="1">
                </div>

                <div class="psc-form-group">
                    <label class="psc-form-label">상태</label>
                    <select name="coupon_status" class="psc-form-input">
                        <option value="publish" <?php selected( $status, 'publish' ); ?>>발행</option>
                        <option value="draft"   <?php selected( $status, 'draft'   ); ?>>임시저장</option>
                    </select>
                </div>

            </div>
        </div>

        <button type="submit" class="psc-dash-btn psc-dash-btn--primary psc-dash-btn--full">
            💾 저장하기
        </button>
    </form>

    <script>
    document.getElementById('coupon_type_select').addEventListener('change', function () {
        document.getElementById('coupon_unit').textContent = this.value === 'fixed' ? '원' : '%';
    });
    </script>
    <?php
}

/* ============================================================
   6. 쿠폰 사용 처리 페이지
   ============================================================ */
function psc_dash_page_coupon_use( int $store_id, int $user_id ): void {

    $verify_result = null;
    $use_result    = null;

    /* 토큰 검증 */
    if ( ! empty( $_POST['verify_token'] ) && ! empty( $_POST['psc_use_nonce'] ) ) {
        if ( ! wp_verify_nonce( $_POST['psc_use_nonce'], 'psc_use_coupon_nonce' ) ) {
            $verify_result = [ 'error' => '보안 토큰 오류' ];
        } else {
            $token = sanitize_text_field( $_POST['verify_token'] );
            $row   = psc_coupon_get_by_token( $token );
            if ( ! $row ) {
                $verify_result = [ 'error' => '유효하지 않은 쿠폰입니다.' ];
            } else {
                $coupon    = get_post( $row->coupon_id );
                $owner_sid = (int) get_post_meta( $row->coupon_id, PSC_COUPON_STORE_META, true );
                if ( $owner_sid !== $store_id ) {
                    $verify_result = [ 'error' => '이 매장의 쿠폰이 아닙니다.' ];
                } elseif ( (int) $row->is_used === 1 ) {
                    $verify_result = [ 'error' => '이미 사용된 쿠폰입니다. (사용일시: ' . $row->used_at . ')' ];
                } else {
                    $expire = get_post_meta( $row->coupon_id, 'coupon_expire_date', true );
                    if ( $expire && strtotime( $expire ) < time() ) {
                        $verify_result = [ 'error' => '만료된 쿠폰입니다.' ];
                    } else {
                        $type  = get_post_meta( $row->coupon_id, 'coupon_type',  true );
                        $val   = get_post_meta( $row->coupon_id, 'coupon_value', true );
                        $verify_result = [
                            'ok'       => true,
                            'token'    => $token,
                            'title'    => $coupon ? $coupon->post_title : '',
                            'discount' => psc_coupon_format_discount( $type, $val ),
                        ];
                    }
                }
            }
        }
    }

    /* 사용 확정 */
    if ( ! empty( $_POST['confirm_use'] ) && ! empty( $_POST['use_token'] ) && ! empty( $_POST['psc_confirm_nonce'] ) ) {
        if ( wp_verify_nonce( $_POST['psc_confirm_nonce'], 'psc_confirm_use_nonce' ) ) {
            $token      = sanitize_text_field( $_POST['use_token'] );
            $ok         = psc_coupon_use( $token );          // true | WP_Error
            $use_result = ( $ok === true ) ? 'success' : 'fail';
        }
    }
    ?>

    <div class="psc-dash-page-header">
        <h2 class="psc-dash-page-title">🔍 쿠폰 사용 처리</h2>
    </div>

    <?php if ( $use_result === 'success' ) : ?>
        <div class="psc-dash-alert psc-dash-alert--success">✅ 쿠폰이 정상적으로 사용 처리되었습니다.</div>
    <?php elseif ( $use_result === 'fail' ) : ?>
        <div class="psc-dash-alert psc-dash-alert--error">❌ 사용 처리 중 오류가 발생했습니다.</div>
    <?php endif; ?>

    <div class="psc-use-tabs">
        <button class="psc-use-tab active" data-tab="input">코드 직접 입력</button>
        <button class="psc-use-tab"        data-tab="qr">QR 스캔</button>
    </div>

    <div class="psc-use-tab-panel active" id="tab-input">
        <div class="psc-dash-card">
            <div class="psc-dash-card__body">
                <form method="post">
                    <?php wp_nonce_field( 'psc_use_coupon_nonce', 'psc_use_nonce' ); ?>
                    <div class="psc-form-group">
                        <label class="psc-form-label">쿠폰 토큰</label>
                        <input type="text" name="verify_token" class="psc-form-input psc-token-input"
                               placeholder="쿠폰 코드를 입력하세요"
                               value="<?php echo esc_attr( $_POST['verify_token'] ?? '' ); ?>">
                    </div>
                    <button type="submit" class="psc-dash-btn psc-dash-btn--primary psc-dash-btn--full">확인</button>
                </form>

                <?php if ( $verify_result ) : ?>
                    <?php if ( ! empty( $verify_result['error'] ) ) : ?>
                        <div class="psc-dash-alert psc-dash-alert--error" style="margin-top:12px">
                            ❌ <?php echo esc_html( $verify_result['error'] ); ?>
                        </div>
                    <?php elseif ( ! empty( $verify_result['ok'] ) ) : ?>
                        <div class="psc-coupon-verify-ok">
                            <p>🎟️ <strong><?php echo esc_html( $verify_result['title'] ); ?></strong></p>
                            <p>할인: <strong><?php echo esc_html( $verify_result['discount'] ); ?></strong></p>
                            <form method="post">
                                <?php wp_nonce_field( 'psc_confirm_use_nonce', 'psc_confirm_nonce' ); ?>
                                <input type="hidden" name="use_token"    value="<?php echo esc_attr( $verify_result['token'] ); ?>">
                                <input type="hidden" name="confirm_use"  value="1">
                                <button type="submit" class="psc-dash-btn psc-dash-btn--success psc-dash-btn--full">
                                    ✅ 사용 확정
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="psc-use-tab-panel" id="tab-qr">
        <div class="psc-dash-card">
            <div class="psc-dash-card__body">
                <p style="text-align:center;color:#666;margin-bottom:12px">카메라로 고객의 QR 코드를 스캔하세요</p>
                <div id="psc-qr-reader" style="width:100%;max-width:400px;margin:0 auto"></div>
                <div id="psc-qr-result" style="margin-top:12px"></div>
            </div>
        </div>
    </div>

    <?php psc_coupon_inline_css(); ?>
    <script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <script>
    document.querySelectorAll('.psc-use-tab').forEach(function(btn){
        btn.addEventListener('click', function(){
            document.querySelectorAll('.psc-use-tab').forEach(function(b){ b.classList.remove('active'); });
            document.querySelectorAll('.psc-use-tab-panel').forEach(function(p){ p.classList.remove('active'); });
            btn.classList.add('active');
            document.getElementById('tab-' + btn.dataset.tab).classList.add('active');
            if ( btn.dataset.tab === 'qr' ) pscStartQR();
        });
    });

    var qrScanner = null;
    function pscStartQR() {
        if ( qrScanner ) return;
        qrScanner = new Html5Qrcode('psc-qr-reader');
        qrScanner.start(
            { facingMode: 'environment' },
            { fps: 10, qrbox: 250 },
            function(decodedText) {
                qrScanner.stop();
                qrScanner = null;
                document.getElementById('psc-qr-result').innerHTML =
                    '<p>스캔된 코드: <strong>' + decodedText + '</strong></p>'
                    + '<form method="post">'
                    + '<input type="hidden" name="psc_use_nonce" value="<?php echo wp_create_nonce( 'psc_use_coupon_nonce' ); ?>">'
                    + '<input type="hidden" name="verify_token" value="' + decodedText + '">'
                    + '<button type="submit" class="psc-dash-btn psc-dash-btn--primary psc-dash-btn--full">토큰 확인</button>'
                    + '</form>';
            },
            function() {}
        );
    }
    </script>
    <?php
}

/* ============================================================
   7. 무료 플랜 유도 화면
   ============================================================ */
function psc_coupon_free_plan_screen(): string {
    ob_start(); ?>
    <div class="psc-coupon-upgrade-wrap">
        <div class="psc-coupon-upgrade-blur">
            <div class="psc-coupon-card">
                <div class="psc-coupon-card__top">
                    <div class="psc-coupon-card__title">신규 방문 10% 할인 쿠폰</div>
                    <div class="psc-coupon-card__value">10% 할인</div>
                </div>
                <div class="psc-coupon-card__meta">
                    <span>📤 발행 32장</span>
                    <span>✅ 사용 18장</span>
                </div>
            </div>
        </div>
        <div class="psc-coupon-upgrade-overlay">
            <div class="psc-coupon-upgrade-box">
                <p class="psc-coupon-upgrade-icon">🎟️</p>
                <h3>쿠폰 기능은 프리미엄 전용입니다</h3>
                <ul>
                    <li>✅ 할인율/정액 쿠폰 무제한 발행</li>
                    <li>✅ 복제 방지 워터마크 자동 적용</li>
                    <li>✅ QR 코드 + 코드 입력 사용 처리</li>
                </ul>
                <a href="<?php echo esc_url( home_url( '/upgrade/' ) ); ?>"
                   class="psc-dash-btn psc-dash-btn--premium">프리미엄으로 업그레이드 →</a>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/* ============================================================
   8. 인라인 CSS
   ============================================================ */
function psc_coupon_inline_css(): void { ?>
<style>
/* ── 폼 공통 ── */
.psc-form-group{display:flex;flex-direction:column;gap:6px;margin-bottom:16px}
.psc-form-group:last-child{margin-bottom:0}
.psc-form-label{font-size:.88rem;font-weight:600;color:#333}
.psc-form-label .req{color:#e53e3e}
.psc-form-input{
    width:100%;padding:10px 14px;
    border:1px solid #e5e7eb;border-radius:8px;
    font-size:.92rem;color:#111;background:#fff;
    outline:none;transition:border-color .15s;font-family:inherit;
}
input.psc-form-input{
    width:100%;padding:10px 14px;
    border:1px solid #e5e7eb;border-radius:8px;
    font-size:.92rem;color:#111;background:#fff;
    outline:none;transition:border-color .15s;font-family:inherit;
}
.psc-form-input:focus{border-color:#667eea;box-shadow:0 0 0 3px rgba(102,126,234,.12)}
.psc-form-input textarea{resize:vertical;min-height:90px}
select.psc-form-input{appearance:auto}

/* ── 카드 ── */
.psc-dash-card{
    background:#fff;border-radius:14px;padding:20px;margin-bottom:16px;
    box-shadow:0 1px 4px rgba(0,0,0,.06)
}
/* block으로 변경 — flex:column이 내부 row를 덮어쓰는 문제 해결 */
.psc-dash-card__body{display:block}

/* ── 저장 버튼 ── */
.psc-dash-btn--full{width:100%;display:flex;justify-content:center}

/* ── 폼 2열 행 ── */
.psc-coupon-form .psc-form-row{
    display:flex !important;
    flex-direction:row !important;
    gap:12px;
    margin-bottom:16px;
}
.psc-coupon-form .psc-form-row .psc-form-group{
    flex:1 1 0;
    min-width:0;
    margin-bottom:0;
}
.psc-input-with-unit{display:flex;align-items:center;gap:6px}
.psc-input-with-unit .psc-form-input{flex:1;min-width:0}
.psc-input-unit{font-size:.9rem;font-weight:700;color:#555;white-space:nowrap}

@media(max-width:480px){
    .psc-coupon-form .psc-form-row{
        flex-direction:column !important;
        margin-bottom:0
    }
    .psc-coupon-form .psc-form-row .psc-form-group{margin-bottom:16px}
}

/* ── 쿠폰 카드 ── */
.psc-coupon-list-wrap{display:flex;flex-direction:column;gap:12px;margin-top:12px}
.psc-coupon-card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:16px;
    box-shadow:0 1px 3px rgba(0,0,0,.06)}
.psc-coupon-card__top{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px}
.psc-coupon-card__title{font-weight:700;font-size:.95rem;display:flex;align-items:center;gap:6px;flex-wrap:wrap}
.psc-coupon-card__value{font-size:1.1rem;font-weight:800;color:#667eea;white-space:nowrap}
.psc-coupon-card__meta{font-size:.8rem;color:#888;display:flex;gap:12px;flex-wrap:wrap;margin-bottom:10px}
.psc-coupon-card__actions{display:flex;gap:8px}

/* ── 뱃지 ── */
.psc-badge{display:inline-block;padding:2px 7px;border-radius:20px;font-size:.72rem;font-weight:700}
.psc-badge--success{background:#d1fae5;color:#065f46}
.psc-badge--warning{background:#fef3c7;color:#92400e}
.psc-badge--danger{background:#fee2e2;color:#991b1b}
.psc-badge--gray{background:#f3f4f6;color:#6b7280}

/* ── 빈 상태 ── */
.psc-coupon-empty{text-align:center;padding:40px 20px;color:#888}
.psc-coupon-empty p{margin-bottom:16px;font-size:1rem}

/* ── 사용처리 탭 ── */
.psc-use-tabs{display:flex;margin-bottom:16px;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden}
.psc-use-tab{flex:1;padding:10px;background:#f9fafb;border:none;cursor:pointer;
    font-size:.88rem;font-weight:600;color:#555;transition:background .2s}
.psc-use-tab.active{background:#667eea;color:#fff}
.psc-use-tab-panel{display:none}
.psc-use-tab-panel.active{display:block}
.psc-token-input{font-family:monospace;letter-spacing:.05em;font-size:1rem}
.psc-coupon-verify-ok{background:#f0fdf4;border:1px solid #86efac;border-radius:10px;
    padding:14px;margin-top:12px}
.psc-coupon-verify-ok p{margin:0 0 8px}

/* ── 업그레이드 유도 ── */
.psc-coupon-upgrade-wrap{position:relative;min-height:240px}
.psc-coupon-upgrade-blur{filter:blur(4px);pointer-events:none;opacity:.5}
.psc-coupon-upgrade-overlay{position:absolute;inset:0;display:flex;align-items:center;
    justify-content:center;z-index:2}
.psc-coupon-upgrade-box{background:#fff;border-radius:16px;padding:28px 24px;text-align:center;
    box-shadow:0 4px 24px rgba(0,0,0,.12);max-width:340px;width:100%}
.psc-coupon-upgrade-icon{font-size:2.5rem;margin:0 0 8px}
.psc-coupon-upgrade-box h3{margin:0 0 12px;font-size:1.05rem}
.psc-coupon-upgrade-box ul{list-style:none;padding:0;margin:0 0 18px;
    text-align:left;font-size:.88rem;line-height:2}
.psc-dash-btn--premium{
    background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;border:none;
    padding:12px 24px;border-radius:8px;font-weight:700;font-size:.95rem;
    display:inline-block;text-decoration:none}

/* ── 헤더 ── */
.psc-dash-page-header{
    display:flex!important;align-items:center!important;
    justify-content:space-between!important;flex-wrap:wrap;gap:10px;margin-bottom:20px}
.psc-dash-page-title{margin:0!important;font-size:1.1rem;font-weight:700}
.psc-back-link{font-size:.85rem;color:#667eea;text-decoration:none}
.psc-back-link:hover{text-decoration:underline}

/* ── 버튼 성공 색상 ── */
.psc-dash-btn--success{background:#d1fae5;color:#065f46;border:1px solid #6ee7b7}
.psc-dash-btn--success:hover{background:#a7f3d0}
</style>
<?php }

/* ============================================================
   9. 에러 메시지 헬퍼
   ============================================================ */
function psc_coupon_error_msg( string $code ): string {
    $map = [
        'invalid_nonce' => '보안 토큰이 유효하지 않습니다.',
        'free_plan'     => '무료 플랜은 쿠폰을 발행할 수 없습니다.',
        'no_title'      => '쿠폰 제목을 입력해 주세요.',
        'no_value'      => '할인 값을 입력해 주세요.',
        'save_failed'   => '저장 중 오류가 발생했습니다.',
    ];
    return $map[ $code ] ?? '오류가 발생했습니다.';
}

/* ============================================================
   쿠폰 현황 페이지
   ============================================================ */
function psc_dash_page_coupon_stats( int $store_id, int $user_id ): void {

    $plan       = get_post_meta( $store_id, 'store_plan', true );
    $is_premium = ( $plan === 'premium' );
    if ( ! $is_premium ) {
        echo psc_coupon_free_plan_screen();
        psc_coupon_inline_css();
        return;
    }

    /* 이 매장의 모든 쿠폰 */
    $coupons = get_posts( [
        'post_type'      => PSC_COUPON_CPT,
        'post_status'    => [ 'publish', 'draft' ],
        'posts_per_page' => -1,
        'meta_query'     => [ [
            'key'   => PSC_COUPON_STORE_META,
            'value' => $store_id,
        ] ],
    ] );

    global $wpdb;
    $table = $wpdb->prefix . PSC_COUPON_TABLE;

    /* 전체 집계 */
    $total_issued = 0;
    $total_used   = 0;
    $stats        = [];

    foreach ( $coupons as $c ) {
        $cid     = $c->ID;
        $issued  = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM `{$table}` WHERE coupon_id = %d", $cid
        ));
        $used    = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM `{$table}` WHERE coupon_id = %d AND is_used = 1", $cid
        ));
        $total_issued += $issued;
        $total_used   += $used;
        $stats[$cid]   = [ 'issued' => $issued, 'used' => $used ];
    }

    $use_rate = $total_issued > 0
        ? round( $total_used / $total_issued * 100 )
        : 0;

    /* 최근 발급 내역 (최대 10건) */
    $coupon_ids = array_column( $coupons, 'ID' );
    $recent     = [];
    if ( ! empty( $coupon_ids ) ) {
        $ids_in = implode( ',', array_map( 'intval', $coupon_ids ) );
        $recent = $wpdb->get_results(
            "SELECT i.*, u.display_name
             FROM `{$table}` i
             LEFT JOIN {$wpdb->users} u ON u.ID = i.user_id
             WHERE i.coupon_id IN ({$ids_in})
             ORDER BY i.issued_at DESC
             LIMIT 10"
        );
    }
    ?>

    <div class="psc-dash-page-header">
        <h2 class="psc-dash-page-title">📊 쿠폰 현황</h2>
        <a href="<?php echo esc_url( home_url( '/vendor/coupons/' ) ); ?>"
           class="psc-back-link">← 쿠폰 관리</a>
    </div>

    <!-- 요약 카드 -->
    <div class="psc-stats-summary">
        <div class="psc-stats-card">
            <div class="psc-stats-card__num"><?php echo number_format( count( $coupons ) ); ?></div>
            <div class="psc-stats-card__label">총 쿠폰 수</div>
        </div>
        <div class="psc-stats-card">
            <div class="psc-stats-card__num"><?php echo number_format( $total_issued ); ?></div>
            <div class="psc-stats-card__label">총 발급 수</div>
        </div>
        <div class="psc-stats-card">
            <div class="psc-stats-card__num"><?php echo number_format( $total_used ); ?></div>
            <div class="psc-stats-card__label">총 사용 수</div>
        </div>
        <div class="psc-stats-card">
            <div class="psc-stats-card__num"><?php echo $use_rate; ?>%</div>
            <div class="psc-stats-card__label">사용률</div>
        </div>
    </div>

    <!-- 쿠폰별 현황 -->
    <div class="psc-dash-card" style="margin-top:16px">
        <h3 style="margin:0 0 14px;font-size:.95rem;font-weight:700">쿠폰별 현황</h3>
        <?php if ( empty( $coupons ) ) : ?>
            <p style="color:#aaa;text-align:center;padding:20px 0">발행된 쿠폰이 없습니다.</p>
        <?php else : ?>
        <div style="overflow-x:auto">
        <table class="psc-stats-table">
            <thead>
                <tr>
                    <th>쿠폰명</th>
                    <th>상태</th>
                    <th>발급</th>
                    <th>사용</th>
                    <th>사용률</th>
                    <th>만료일</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $coupons as $c ) :
                $cid      = $c->ID;
                $type     = get_post_meta( $cid, 'coupon_type',        true );
                $val      = get_post_meta( $cid, 'coupon_value',       true );
                $expire   = get_post_meta( $cid, 'coupon_expire_date', true );
                $max      = (int) get_post_meta( $cid, 'coupon_max_issue', true );
                $issued   = $stats[$cid]['issued'];
                $used     = $stats[$cid]['used'];
                $rate     = $issued > 0 ? round( $used / $issued * 100 ) : 0;
                $is_exp   = $expire && strtotime( $expire ) < time();
                $is_full  = $max > 0 && $issued >= $max;

                if ( $is_exp )                          $badge = '<span class="psc-badge psc-badge--danger">만료</span>';
                elseif ( $is_full )                     $badge = '<span class="psc-badge psc-badge--warning">마감</span>';
                elseif ( $c->post_status === 'publish' ) $badge = '<span class="psc-badge psc-badge--success">발행중</span>';
                else                                    $badge = '<span class="psc-badge psc-badge--gray">임시저장</span>';

                $disc = $type === 'fixed'
                    ? number_format( (float)$val ) . '원'
                    : (float)$val . '%';
            ?>
                <tr>
                    <td>
                        <strong><?php echo esc_html( $c->post_title ); ?></strong>
                        <small style="color:#888;display:block"><?php echo esc_html( $disc ); ?> 할인</small>
                    </td>
                    <td><?php echo $badge; ?></td>
                    <td>
                        <?php echo $issued; ?>
                        <?php if ( $max > 0 ) echo '<small style="color:#aaa"> / ' . $max . '</small>'; ?>
                    </td>
                    <td><?php echo $used; ?></td>
                    <td>
                        <div class="psc-rate-bar">
                            <div class="psc-rate-bar__fill" style="width:<?php echo $rate; ?>%"></div>
                        </div>
                        <small><?php echo $rate; ?>%</small>
                    </td>
                    <td style="font-size:.82rem;color:<?php echo $is_exp ? '#e53e3e' : '#555'; ?>">
                        <?php echo $expire ? esc_html( $expire ) : '—'; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- 최근 발급 내역 -->
    <div class="psc-dash-card" style="margin-top:16px">
        <h3 style="margin:0 0 14px;font-size:.95rem;font-weight:700">최근 발급 내역</h3>
        <?php if ( empty( $recent ) ) : ?>
            <p style="color:#aaa;text-align:center;padding:20px 0">발급 내역이 없습니다.</p>
        <?php else : ?>
        <div style="overflow-x:auto">
        <table class="psc-stats-table">
            <thead>
                <tr>
                    <th>쿠폰명</th>
                    <th>사용자</th>
                    <th>발급일시</th>
                    <th>상태</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $recent as $row ) :
                $c_title = get_the_title( $row->coupon_id );
            ?>
                <tr>
                    <td><?php echo esc_html( $c_title ?: '삭제된 쿠폰' ); ?></td>
                    <td><?php echo esc_html( $row->display_name ?: '탈퇴 회원' ); ?></td>
                    <td style="font-size:.8rem;color:#888"><?php echo esc_html( $row->issued_at ); ?></td>
                    <td>
                        <?php if ( (int)$row->is_used ) : ?>
                            <span class="psc-badge psc-badge--success">✅ 사용됨</span>
                        <?php else : ?>
                            <span class="psc-badge psc-badge--gray">미사용</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>

    <?php
    psc_coupon_inline_css();
    psc_coupon_stats_css();
}

/* 현황 페이지 전용 CSS */
function psc_coupon_stats_css(): void { ?>
<style>
/* 요약 카드 */
.psc-stats-summary{
    display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-top:4px
}
.psc-stats-card{
    background:#fff;border-radius:12px;padding:16px 12px;
    text-align:center;box-shadow:0 1px 4px rgba(0,0,0,.06)
}
.psc-stats-card__num{font-size:1.5rem;font-weight:900;color:#667eea}
.psc-stats-card__label{font-size:.75rem;color:#888;margin-top:4px}

/* 테이블 */
.psc-stats-table{width:100%;border-collapse:collapse;font-size:.85rem}
.psc-stats-table th{
    background:#f9fafb;padding:10px 12px;text-align:left;
    font-weight:700;color:#555;border-bottom:1px solid #e5e7eb;
    white-space:nowrap
}
.psc-stats-table td{
    padding:10px 12px;border-bottom:1px solid #f3f4f6;
    vertical-align:middle
}
.psc-stats-table tr:last-child td{border-bottom:none}
.psc-stats-table tr:hover td{background:#fafafa}

/* 사용률 바 */
.psc-rate-bar{
    height:6px;background:#f3f4f6;border-radius:4px;
    overflow:hidden;margin-bottom:3px;min-width:60px
}
.psc-rate-bar__fill{
    height:100%;background:linear-gradient(90deg,#667eea,#764ba2);
    border-radius:4px;transition:width .4s
}

@media(max-width:480px){
    .psc-stats-summary{grid-template-columns:repeat(2,1fr)}
}
</style>
<?php }
