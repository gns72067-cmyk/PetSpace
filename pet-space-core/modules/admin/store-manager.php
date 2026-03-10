<?php
/**
 * 매장 관리 관리자 페이지
 * File: modules/admin/store-manager.php
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/* ============================================================
   상수
   ============================================================ */
if ( ! defined( 'PSC_STORE_CPT' ) ) define( 'PSC_STORE_CPT', 'store' );

/* ============================================================
   1. 관리자 메뉴 등록
   ============================================================ */
add_action( 'admin_menu', 'psc_store_manager_menu' );
function psc_store_manager_menu(): void {
    add_menu_page(
        '매장 관리',
        '🏪 매장 관리',
        'manage_options',
        'psc-store-manager',
        'psc_store_manager_page',
        'dashicons-store',
        31
    );
}

/* ============================================================
   2. 등급 변경 처리 (POST)
   ============================================================ */
add_action( 'admin_init', 'psc_store_manager_handle_change' );
function psc_store_manager_handle_change(): void {
    if (
        ! isset( $_POST['psc_store_plan_change'] ) ||
        ! check_admin_referer( 'psc_store_plan_nonce' )
    ) return;

    $store_id = absint( $_POST['store_id']   ?? 0 );
    $plan     = sanitize_key( $_POST['store_plan'] ?? 'free' );

    if ( ! $store_id || ! in_array( $plan, [ 'free', 'premium' ], true ) ) return;
    if ( get_post_type( $store_id ) !== PSC_STORE_CPT ) return;

    // ACF update_field 또는 update_post_meta 둘 다 지원
    if ( function_exists( 'update_field' ) ) {
        update_field( 'store_plan', $plan, $store_id );
    } else {
        update_post_meta( $store_id, 'store_plan', $plan );
    }

    // 변경 후 원래 페이지로 리다이렉트 (성공 메시지 포함)
    $redirect = add_query_arg(
        [ 'page' => 'psc-store-manager', 'plan_updated' => 1 ],
        admin_url( 'admin.php' )
    );
    wp_redirect( $redirect );
    exit;
}

/* ============================================================
   3. 매장 관리 페이지 렌더
   ============================================================ */
function psc_store_manager_page(): void {

    // 검색 & 필터 & 페이지네이션
    $search      = sanitize_text_field( $_GET['s']      ?? '' );
    $plan_filter = sanitize_key( $_GET['plan']          ?? '' );
    $paged       = max( 1, absint( $_GET['paged']       ?? 1 ) );
    $per_page    = 20;

    // WP_Query 로 매장 목록 가져오기
    $meta_query = [];
    if ( $plan_filter === 'free' ) {
        $meta_query = [
            'relation' => 'OR',
            [ 'key' => 'store_plan', 'value' => 'free',    'compare' => '=' ],
            [ 'key' => 'store_plan', 'compare' => 'NOT EXISTS' ],
        ];
    } elseif ( $plan_filter === 'premium' ) {
        $meta_query = [
            [ 'key' => 'store_plan', 'value' => 'premium', 'compare' => '=' ],
        ];
    }

    $query_args = [
        'post_type'      => PSC_STORE_CPT,
        'post_status'    => 'publish',
        'posts_per_page' => $per_page,
        'paged'          => $paged,
        'orderby'        => 'date',
        'order'          => 'DESC',
    ];
    if ( $search )           $query_args['s']          = $search;
    if ( ! empty($meta_query) ) $query_args['meta_query'] = $meta_query;

    $query       = new WP_Query( $query_args );
    $stores      = $query->posts;
    $total       = $query->found_posts;
    $total_pages = $query->max_num_pages;

    // 등급별 카운트 (필터 버튼용)
    $count_all     = wp_count_posts( PSC_STORE_CPT )->publish ?? 0;
    $count_premium = ( new WP_Query([
        'post_type' => PSC_STORE_CPT, 'post_status' => 'publish',
        'posts_per_page' => -1, 'fields' => 'ids',
        'meta_query' => [[ 'key' => 'store_plan', 'value' => 'premium' ]],
    ]) )->found_posts;
    $count_free = $count_all - $count_premium;

    ?>
    <div class="wrap">
        <h1>🏪 매장 관리
            <span style="font-size:.85rem;font-weight:400;color:#6b7280;margin-left:8px;">
                총 <?php echo number_format( $total ); ?>개
            </span>
        </h1>

        <?php if ( ! empty( $_GET['plan_updated'] ) ) : ?>
            <div class="notice notice-success is-dismissible">
                <p>✅ 매장 등급이 변경되었습니다.</p>
            </div>
        <?php endif; ?>

        <!-- 등급 필터 탭 -->
        <ul class="subsubsub" style="margin-bottom:12px;">
            <?php
            $tabs = [
                ''        => [ '전체', $count_all ],
                'free'    => [ '무료', $count_free ],
                'premium' => [ '프리미엄 ⭐', $count_premium ],
            ];
            $tab_items = [];
            foreach ( $tabs as $key => [ $label, $count ] ) {
                $url     = add_query_arg( [ 'page' => 'psc-store-manager', 'plan' => $key, 's' => $search ], admin_url('admin.php') );
                $current = ( $plan_filter === $key ) ? ' class="current"' : '';
                $tab_items[] = '<li><a href="' . esc_url($url) . '"' . $current . '>' . $label . ' <span class="count">(' . $count . ')</span></a></li>';
            }
            echo implode( ' | ', $tab_items );
            ?>
        </ul>

        <!-- 검색 -->
        <form method="get" style="display:flex;gap:8px;margin-bottom:16px;align-items:center;">
            <input type="hidden" name="page" value="psc-store-manager">
            <input type="hidden" name="plan" value="<?php echo esc_attr( $plan_filter ); ?>">
            <input type="text" name="s" value="<?php echo esc_attr( $search ); ?>"
                placeholder="매장명 검색"
                style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:.88rem;min-width:240px;">
            <button type="submit"
                style="padding:8px 18px;background:#111;color:#fff;border:none;border-radius:6px;font-size:.88rem;font-weight:700;cursor:pointer;">
                🔍 검색
            </button>
            <?php if ( $search ) : ?>
                <a href="<?php echo esc_url( add_query_arg( [ 'page' => 'psc-store-manager', 'plan' => $plan_filter ], admin_url('admin.php') ) ); ?>"
                    style="padding:8px 14px;background:#f3f4f6;color:#6b7280;border-radius:6px;font-size:.88rem;text-decoration:none;">
                    ✕ 초기화
                </a>
            <?php endif; ?>
        </form>

        <!-- 매장 테이블 -->
        <?php if ( empty( $stores ) ) : ?>
            <div style="text-align:center;padding:60px;color:#9ca3af;background:#fff;border-radius:12px;border:1px solid #e5e7eb;">
                매장이 없습니다.
            </div>
        <?php else : ?>
            <table class="wp-list-table widefat fixed striped" style="border-radius:10px;overflow:hidden;">
                <thead>
                    <tr>
                        <th style="width:60px;">썸네일</th>
                        <th>매장명</th>
                        <th style="width:150px;">업체 (벤더)</th>
                        <th style="width:110px;text-align:center;">현재 등급</th>
                        <th style="width:220px;text-align:center;">등급 변경</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $stores as $store ) :
                        $store_id   = $store->ID;
                        $thumb_url  = get_the_post_thumbnail_url( $store_id, 'thumbnail' );
                        $store_url  = get_permalink( $store_id );
                        $edit_url   = get_edit_post_link( $store_id );

                        // 등급
                        $plan = function_exists('get_field')
                            ? get_field( 'store_plan', $store_id )
                            : get_post_meta( $store_id, 'store_plan', true );
                        $plan       = $plan ?: 'free';
                        $is_premium = ( $plan === 'premium' );

                        // 벤더 (작성자)
                        $author_id   = (int) $store->post_author;
                        $author_name = get_the_author_meta( 'display_name', $author_id );
                        $author_edit = get_edit_user_link( $author_id );
                    ?>
                        <tr>
                            <!-- 썸네일 -->
                            <td style="padding:10px 12px;">
                                <?php if ( $thumb_url ) : ?>
                                    <img src="<?php echo esc_url( $thumb_url ); ?>"
                                        width="48" height="48"
                                        style="object-fit:cover;border-radius:8px;">
                                <?php else : ?>
                                    <div style="width:48px;height:48px;background:#f3f4f6;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#d1d5db;font-size:1.4rem;">🏪</div>
                                <?php endif; ?>
                            </td>

                            <!-- 매장명 -->
                            <td style="padding:10px 12px;">
                                <div style="font-weight:600;color:#111;margin-bottom:2px;">
                                    <a href="<?php echo esc_url( $store_url ); ?>" target="_blank"
                                        style="color:#111;text-decoration:none;">
                                        <?php echo esc_html( $store->post_title ); ?> ↗
                                    </a>
                                </div>
                                <div style="font-size:.78rem;color:#9ca3af;">
                                    ID: <?php echo $store_id; ?>
                                    &nbsp;·&nbsp;
                                    <a href="<?php echo esc_url( $edit_url ); ?>" style="color:#6b7280;">ACF 편집</a>
                                </div>
                            </td>

                            <!-- 벤더 -->
                            <td style="padding:10px 12px;">
                                <a href="<?php echo esc_url( $author_edit ); ?>"
                                    style="color:#374151;font-size:.88rem;text-decoration:none;">
                                    <?php echo esc_html( $author_name ); ?>
                                </a>
                            </td>

                            <!-- 현재 등급 뱃지 -->
                            <td style="padding:10px 12px;text-align:center;">
                                <?php if ( $is_premium ) : ?>
                                    <span style="display:inline-block;padding:4px 12px;border-radius:20px;font-size:.78rem;font-weight:700;background:#fef3c7;color:#92400e;">
                                        ⭐ 프리미엄
                                    </span>
                                <?php else : ?>
                                    <span style="display:inline-block;padding:4px 12px;border-radius:20px;font-size:.78rem;font-weight:700;background:#f3f4f6;color:#6b7280;">
                                        무료
                                    </span>
                                <?php endif; ?>
                            </td>

                            <!-- 등급 변경 -->
                            <td style="padding:10px 12px;text-align:center;">
                                <form method="post" style="display:inline-flex;gap:6px;align-items:center;">
                                    <?php wp_nonce_field( 'psc_store_plan_nonce' ); ?>
                                    <input type="hidden" name="store_id" value="<?php echo $store_id; ?>">
                                    <select name="store_plan"
                                        style="padding:6px 8px;border:1px solid #d1d5db;border-radius:6px;font-size:.82rem;">
                                        <option value="free"    <?php selected( $plan, 'free' );    ?>>무료</option>
                                        <option value="premium" <?php selected( $plan, 'premium' ); ?>>⭐ 프리미엄</option>
                                    </select>
                                    <button type="submit" name="psc_store_plan_change"
                                        style="padding:6px 14px;background:#111;color:#fff;border:none;border-radius:6px;font-size:.82rem;font-weight:700;cursor:pointer;">
                                        변경
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- 페이지네이션 -->
            <?php if ( $total_pages > 1 ) : ?>
                <div style="display:flex;justify-content:center;gap:6px;margin-top:20px;flex-wrap:wrap;">
                    <?php if ( $paged > 1 ) :
                        $prev = add_query_arg( [ 'page' => 'psc-store-manager', 's' => $search, 'plan' => $plan_filter, 'paged' => $paged - 1 ], admin_url('admin.php') );
                    ?>
                        <a href="<?php echo esc_url( $prev ); ?>"
                            style="display:inline-flex;align-items:center;padding:6px 12px;border-radius:6px;background:#f3f4f6;color:#374151;font-size:.85rem;text-decoration:none;">
                            ← 이전
                        </a>
                    <?php endif; ?>

                    <?php
                    $start = max( 1, $paged - 2 );
                    $end   = min( $total_pages, $paged + 2 );
                    for ( $i = $start; $i <= $end; $i++ ) :
                        $url     = add_query_arg( [ 'page' => 'psc-store-manager', 's' => $search, 'plan' => $plan_filter, 'paged' => $i ], admin_url('admin.php') );
                        $current = ( $i === $paged );
                    ?>
                        <a href="<?php echo esc_url( $url ); ?>"
                            style="display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:6px;font-size:.85rem;font-weight:600;text-decoration:none;
                            <?php echo $current ? 'background:#111;color:#fff;' : 'background:#f3f4f6;color:#374151;'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ( $paged < $total_pages ) :
                        $next = add_query_arg( [ 'page' => 'psc-store-manager', 's' => $search, 'plan' => $plan_filter, 'paged' => $paged + 1 ], admin_url('admin.php') );
                    ?>
                        <a href="<?php echo esc_url( $next ); ?>"
                            style="display:inline-flex;align-items:center;padding:6px 12px;border-radius:6px;background:#f3f4f6;color:#374151;font-size:.85rem;text-decoration:none;">
                            다음 →
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php
}

/* ============================================================
   4. 회원 관리 탭 — 벤더 매장 인라인 펼침 (AJAX)
   ============================================================ */
add_action( 'wp_ajax_psc_get_vendor_stores', 'psc_ajax_get_vendor_stores' );
function psc_ajax_get_vendor_stores(): void {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( '권한 없음' );
    check_ajax_referer( 'psc_vendor_stores_nonce', 'nonce' );

    $vendor_id = absint( $_POST['vendor_id'] ?? 0 );
    if ( ! $vendor_id ) wp_send_json_error();

    $stores = get_posts([
        'post_type'      => PSC_STORE_CPT,
        'post_status'    => 'publish',
        'author'         => $vendor_id,
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ]);

    if ( empty( $stores ) ) {
        wp_send_json_success( '<div style="padding:12px 16px;color:#9ca3af;font-size:.85rem;">등록된 매장이 없습니다.</div>' );
        return;
    }

    ob_start();
    ?>
    <div style="background:#f9fafb;border-top:1px solid #e5e7eb;padding:12px 16px;">
        <table style="width:100%;border-collapse:collapse;font-size:.85rem;">
            <thead>
                <tr style="border-bottom:1px solid #e5e7eb;">
                    <th style="padding:6px 10px;text-align:left;color:#6b7280;font-weight:600;">매장명</th>
                    <th style="padding:6px 10px;text-align:center;color:#6b7280;font-weight:600;width:100px;">현재 등급</th>
                    <th style="padding:6px 10px;text-align:center;color:#6b7280;font-weight:600;width:200px;">등급 변경</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $stores as $store ) :
                    $sid   = $store->ID;
                    $plan  = function_exists('get_field')
                        ? ( get_field('store_plan', $sid) ?: 'free' )
                        : ( get_post_meta( $sid, 'store_plan', true ) ?: 'free' );
                    $is_p  = ( $plan === 'premium' );
                    $s_url = get_permalink( $sid );
                ?>
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:8px 10px;">
                            <a href="<?php echo esc_url( $s_url ); ?>" target="_blank"
                                style="color:#111;font-weight:600;text-decoration:none;">
                                <?php echo esc_html( $store->post_title ); ?> ↗
                            </a>
                        </td>
                        <td style="padding:8px 10px;text-align:center;">
                            <?php if ( $is_p ) : ?>
                                <span style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:.75rem;font-weight:700;background:#fef3c7;color:#92400e;">⭐ 프리미엄</span>
                            <?php else : ?>
                                <span style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:.75rem;font-weight:700;background:#f3f4f6;color:#6b7280;">무료</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding:8px 10px;text-align:center;">
                            <form method="post" style="display:inline-flex;gap:6px;align-items:center;">
                                <?php wp_nonce_field( 'psc_store_plan_nonce' ); ?>
                                <input type="hidden" name="store_id" value="<?php echo $sid; ?>">
                                <select name="store_plan"
                                    style="padding:5px 8px;border:1px solid #d1d5db;border-radius:6px;font-size:.8rem;">
                                    <option value="free"    <?php selected( $plan, 'free' );    ?>>무료</option>
                                    <option value="premium" <?php selected( $plan, 'premium' ); ?>>⭐ 프리미엄</option>
                                </select>
                                <button type="submit" name="psc_store_plan_change"
                                    style="padding:5px 12px;background:#111;color:#fff;border:none;border-radius:6px;font-size:.8rem;font-weight:700;cursor:pointer;">
                                    변경
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
    wp_send_json_success( ob_get_clean() );
}

/* ============================================================
   5. 회원 관리 페이지에 매장 펼침 버튼 JS 주입
   ============================================================ */
add_action( 'admin_footer', 'psc_store_manager_inject_js' );
function psc_store_manager_inject_js(): void {
    $screen = get_current_screen();
    if ( ! $screen || $screen->id !== 'wu-weon_page_psc-register-members' ) {
        // hook 이름 확인용 — strpos로 넓게 처리
        if ( ! $screen || strpos( $screen->id, 'psc-register-members' ) === false ) return;
    }
    ?>
    <script>
    jQuery(function($){
        // 벤더 행에 매장 보기 버튼 추가
        $('table.wp-list-table tbody tr').each(function(){
            var roleBadge = $(this).find('td:nth-child(4) span');
            if ( roleBadge.text().trim().indexOf('벤더') === -1 ) return;

            var actionCell = $(this).find('td:last-child');
            var vendorId   = $(this).find('input[name="target_uid"]').val();
            if ( ! vendorId ) return;

            var btn = $('<button type="button" style="padding:5px 12px;background:#0284c7;color:#fff;border:none;border-radius:6px;font-size:.8rem;font-weight:700;cursor:pointer;margin-left:6px;">🏪 매장 보기</button>');
            btn.on('click', function(){
                var existing = $(this).closest('tr').next('.psc-vendor-stores-row');
                if ( existing.length ) { existing.remove(); return; }

                $.post(ajaxurl, {
                    action    : 'psc_get_vendor_stores',
                    vendor_id : vendorId,
                    nonce     : '<?php echo wp_create_nonce("psc_vendor_stores_nonce"); ?>',
                }, function(res){
                    if ( res.success ) {
                        var newRow = $('<tr class="psc-vendor-stores-row"><td colspan="5" style="padding:0;"></td></tr>');
                        newRow.find('td').html( res.data );
                        btn.closest('tr').after( newRow );
                    }
                });
            });
            actionCell.find('form').after( btn );
        });
    });
    </script>
    <?php
}
