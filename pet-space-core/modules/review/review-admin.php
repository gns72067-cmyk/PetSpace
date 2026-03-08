<?php
/**
 * Module: Review
 * File: modules/review/review-admin.php
 *
 * 관리자 페이지: 리뷰 목록 조회, 상태 변경, 삭제
 */

defined( 'ABSPATH' ) || exit;

/* ═══════════════════════════════════════════
   1. 관리자 메뉴 등록
═══════════════════════════════════════════ */

function psc_review_admin_menu(): void {
    add_menu_page(
        '리뷰 관리',
        '🐾 리뷰 관리',
        'manage_options',
        'psc-reviews',
        'psc_review_admin_page',
        '',
        26
    );
}
add_action( 'admin_menu', 'psc_review_admin_menu' );

/* ═══════════════════════════════════════════
   2. 관리자 CSS
═══════════════════════════════════════════ */

function psc_review_admin_style(): void {
    ?>
    <style>
    /* ── 공통 ── */
    .psc-adm-wrap { max-width: 1200px; }
    .psc-adm-wrap h1 {
        font-size: 1.4rem;
        font-weight: 700;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    /* ── 필터 바 ── */
    .psc-adm-filter {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        margin-bottom: 16px;
        background: #fff;
        padding: 14px 18px;
        border-radius: 10px;
        border: 1px solid #e5e8eb;
    }
    .psc-adm-filter select,
    .psc-adm-filter input[type="text"] {
        padding: 7px 12px;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-size: .88rem;
        color: #333;
    }
    .psc-adm-filter input[type="text"] { min-width: 200px; }
    .psc-adm-filter .button { height: auto; }

    /* ── 통계 카드 ── */
    .psc-adm-stats {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        margin-bottom: 20px;
    }
    .psc-adm-stat-card {
        background: #fff;
        border: 1px solid #e5e8eb;
        border-radius: 10px;
        padding: 14px 20px;
        min-width: 140px;
        flex: 1;
    }
    .psc-adm-stat-card__label {
        font-size: .78rem;
        color: #8b95a1;
        margin-bottom: 6px;
    }
    .psc-adm-stat-card__value {
        font-size: 1.5rem;
        font-weight: 800;
        color: #191f28;
        letter-spacing: -.03em;
    }
    .psc-adm-stat-card__value.primary { color: #224471; }
    .psc-adm-stat-card__value.warning { color: #f59e0b; }
    .psc-adm-stat-card__value.danger  { color: #e53e3e; }

    /* ── 테이블 ── */
    .psc-adm-table-wrap {
        background: #fff;
        border-radius: 10px;
        border: 1px solid #e5e8eb;
        overflow: hidden;
    }
    table.psc-adm-table {
        width: 100%;
        border-collapse: collapse;
        font-size: .88rem;
    }
    table.psc-adm-table th {
        background: #f8f9fa;
        padding: 12px 14px;
        text-align: left;
        font-weight: 700;
        color: #4e5968;
        border-bottom: 1px solid #e5e8eb;
        white-space: nowrap;
    }
    table.psc-adm-table td {
        padding: 12px 14px;
        border-bottom: 1px solid #f2f4f6;
        vertical-align: top;
        color: #191f28;
    }
    table.psc-adm-table tr:last-child td { border-bottom: none; }
    table.psc-adm-table tr:hover td { background: #fafbfc; }

    /* 별점 */
    .psc-adm-stars { color: #f59e0b; letter-spacing: 1px; }

    /* 상태 뱃지 */
    .psc-adm-badge {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 20px;
        font-size: .76rem;
        font-weight: 700;
    }
    .psc-adm-badge.published { background: #dcfce7; color: #16a34a; }
    .psc-adm-badge.hidden    { background: #fff8e6; color: #d97706; }
    .psc-adm-badge.deleted   { background: #fee2e2; color: #e53e3e; }

    /* 내용 미리보기 */
    .psc-adm-content-preview {
        max-width: 300px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        color: #4e5968;
    }

    /* 이미지 썸네일 */
    .psc-adm-thumbs { display: flex; gap: 4px; flex-wrap: wrap; margin-top: 4px; }
    .psc-adm-thumbs img {
        width: 44px;
        height: 44px;
        border-radius: 5px;
        object-fit: cover;
        border: 1px solid #e5e8eb;
    }

    /* 액션 버튼 */
    .psc-adm-actions { display: flex; gap: 6px; flex-wrap: wrap; }
    .psc-adm-btn {
        display: inline-block;
        padding: 5px 12px;
        border-radius: 6px;
        font-size: .78rem;
        font-weight: 600;
        cursor: pointer;
        border: 1px solid transparent;
        text-decoration: none;
        transition: opacity .15s;
    }
    .psc-adm-btn:hover { opacity: .8; }
    .psc-adm-btn.publish { background: #dcfce7; color: #16a34a; border-color: #bbf7d0; }
    .psc-adm-btn.hide    { background: #fff8e6; color: #d97706; border-color: #fde68a; }
    .psc-adm-btn.delete  { background: #fee2e2; color: #e53e3e; border-color: #fecaca; }

    /* 페이지네이션 */
    .psc-adm-pagination {
        display: flex;
        align-items: center;
        gap: 6px;
        padding: 16px 18px;
        border-top: 1px solid #e5e8eb;
        flex-wrap: wrap;
    }
    .psc-adm-pagination a,
    .psc-adm-pagination span {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        border-radius: 6px;
        font-size: .82rem;
        font-weight: 600;
        text-decoration: none;
        border: 1px solid #e5e8eb;
        color: #4e5968;
    }
    .psc-adm-pagination span.current {
        background: #224471;
        color: #fff;
        border-color: #224471;
    }
    .psc-adm-pagination a:hover { background: #f4f6f9; }

    /* 빈 상태 */
    .psc-adm-empty {
        text-align: center;
        padding: 40px;
        color: #8b95a1;
        font-size: .9rem;
    }

    /* 인라인 알림 */
    .psc-adm-notice {
        padding: 10px 16px;
        border-radius: 8px;
        margin-bottom: 14px;
        font-size: .88rem;
        font-weight: 600;
    }
    .psc-adm-notice.success { background: #dcfce7; color: #16a34a; }
    .psc-adm-notice.error   { background: #fee2e2; color: #e53e3e; }
    </style>
    <?php
}

/* ═══════════════════════════════════════════
   3. 상태 변경 처리 (GET 액션)
═══════════════════════════════════════════ */

function psc_review_admin_handle_action(): void {
    if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'psc-reviews' ) return;
    if ( ! current_user_can( 'manage_options' ) ) return;

    $action    = sanitize_key( $_GET['psc_action'] ?? '' );
    $review_id = (int) ( $_GET['review_id'] ?? 0 );

    if ( ! $action || ! $review_id ) return;
    if ( ! check_admin_referer( 'psc_review_action_' . $review_id ) ) return;

    global $wpdb;

    $status_map = [
        'publish' => 'published',
        'hide'    => 'hidden',
        'delete'  => 'deleted',
    ];

    if ( ! isset( $status_map[ $action ] ) ) return;

    $wpdb->update(
        PSC_REVIEW_TABLE,
        [ 'status' => $status_map[ $action ] ],
        [ 'id'     => $review_id ],
        [ '%s' ],
        [ '%d' ]
    );

    $redirect = add_query_arg( [
        'page'    => 'psc-reviews',
        'updated' => $action,
    ], admin_url( 'admin.php' ) );

    wp_redirect( $redirect );
    exit;
}
add_action( 'admin_init', 'psc_review_admin_handle_action' );

/* ═══════════════════════════════════════════
   4. 관리자 페이지 렌더링
═══════════════════════════════════════════ */

function psc_review_admin_page(): void {
    if ( ! current_user_can( 'manage_options' ) ) return;

    global $wpdb;

    psc_review_admin_style();

    /* ── 필터 파라미터 ── */
    $status   = sanitize_key( $_GET['status']   ?? 'all' );
    $store_id = (int)         ( $_GET['store_id'] ?? 0 );
    $rating   = (int)         ( $_GET['rating']   ?? 0 );
    $search   = sanitize_text_field( $_GET['s'] ?? '' );
    $paged    = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
    $per_page = 20;
    $offset   = ( $paged - 1 ) * $per_page;

    /* ── WHERE 조건 조합 ── */
    $where  = "WHERE 1=1";
    $params = [];

    if ( $status !== 'all' ) {
        $where   .= " AND r.status = %s";
        $params[] = $status;
    } else {
        $where .= " AND r.status != 'deleted'";
    }
    if ( $store_id ) {
        $where   .= " AND r.store_id = %d";
        $params[] = $store_id;
    }
    if ( $rating ) {
        $where   .= " AND r.rating = %d";
        $params[] = $rating;
    }
    if ( $search ) {
        $where   .= " AND (r.content LIKE %s OR u.display_name LIKE %s)";
        $like     = '%' . $wpdb->esc_like( $search ) . '%';
        $params[] = $like;
        $params[] = $like;
    }

    /* ── 통계 ── */
    $stats = $wpdb->get_row(
        "SELECT
            COUNT(*) AS total,
            SUM(status = 'published') AS published,
            SUM(status = 'hidden')    AS hidden,
            AVG(CASE WHEN status = 'published' THEN rating END) AS avg_rating
         FROM " . PSC_REVIEW_TABLE
    );

    /* ── 리뷰 목록 조회 ── */
    $base_sql = "FROM " . PSC_REVIEW_TABLE . " r
                 JOIN {$wpdb->users} u ON u.ID = r.user_id
                 LEFT JOIN {$wpdb->posts} p ON p.ID = r.store_id
                 {$where}";

    $total_sql  = "SELECT COUNT(*) {$base_sql}";
    $total_rows = $params
        ? (int) $wpdb->get_var( $wpdb->prepare( $total_sql, ...$params ) )
        : (int) $wpdb->get_var( $total_sql );

    $list_sql = "SELECT r.*, u.display_name, p.post_title AS store_name {$base_sql}
                 ORDER BY r.created_at DESC
                 LIMIT %d OFFSET %d";

    $list_params   = array_merge( $params, [ $per_page, $offset ] );
    $reviews       = $wpdb->get_results( $wpdb->prepare( $list_sql, ...$list_params ) );
    $total_pages   = ceil( $total_rows / $per_page );

    /* ── 매장 목록 (필터용) ── */
    $stores = get_posts( [
        'post_type'      => 'store',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ] );

    /* ── 알림 메시지 ── */
    $updated     = sanitize_key( $_GET['updated'] ?? '' );
    $notice_map  = [
        'publish' => [ '✅ 리뷰를 공개했습니다', 'success' ],
        'hide'    => [ '🔒 리뷰를 숨겼습니다',   'success' ],
        'delete'  => [ '🗑️ 리뷰를 삭제했습니다', 'success' ],
    ];
    ?>

    <div class="wrap psc-adm-wrap">
        <h1>🐾 리뷰 관리</h1>

        <?php if ( isset( $notice_map[ $updated ] ) ) : ?>
            <div class="psc-adm-notice <?php echo esc_attr( $notice_map[$updated][1] ); ?>">
                <?php echo esc_html( $notice_map[$updated][0] ); ?>
            </div>
        <?php endif; ?>

        <!-- 통계 카드 -->
        <div class="psc-adm-stats">
            <div class="psc-adm-stat-card">
                <div class="psc-adm-stat-card__label">전체 리뷰</div>
                <div class="psc-adm-stat-card__value primary">
                    <?php echo number_format( (int) $stats->total ); ?>
                </div>
            </div>
            <div class="psc-adm-stat-card">
                <div class="psc-adm-stat-card__label">공개 중</div>
                <div class="psc-adm-stat-card__value">
                    <?php echo number_format( (int) $stats->published ); ?>
                </div>
            </div>
            <div class="psc-adm-stat-card">
                <div class="psc-adm-stat-card__label">숨김</div>
                <div class="psc-adm-stat-card__value warning">
                    <?php echo number_format( (int) $stats->hidden ); ?>
                </div>
            </div>
            <div class="psc-adm-stat-card">
                <div class="psc-adm-stat-card__label">평균 별점</div>
                <div class="psc-adm-stat-card__value">
                    <?php echo $stats->avg_rating
                        ? '★ ' . number_format( (float) $stats->avg_rating, 1 )
                        : '–'; ?>
                </div>
            </div>
        </div>

        <!-- 필터 바 -->
        <form method="get" action="">
            <input type="hidden" name="page" value="psc-reviews">
            <div class="psc-adm-filter">

                <!-- 상태 -->
                <select name="status">
                    <option value="all"       <?php selected( $status, 'all' );       ?>>전체</option>
                    <option value="published" <?php selected( $status, 'published' ); ?>>공개</option>
                    <option value="hidden"    <?php selected( $status, 'hidden' );    ?>>숨김</option>
                    <option value="deleted"   <?php selected( $status, 'deleted' );   ?>>삭제됨</option>
                </select>

                <!-- 매장 -->
                <select name="store_id">
                    <option value="">전체 매장</option>
                    <?php foreach ( $stores as $st ) : ?>
                        <option value="<?php echo (int) $st->ID; ?>"
                            <?php selected( $store_id, $st->ID ); ?>>
                            <?php echo esc_html( $st->post_title ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <!-- 별점 -->
                <select name="rating">
                    <option value="">전체 별점</option>
                    <?php for ( $i = 5; $i >= 1; $i-- ) : ?>
                        <option value="<?php echo $i; ?>" <?php selected( $rating, $i ); ?>>
                            ★ <?php echo $i; ?>점
                        </option>
                    <?php endfor; ?>
                </select>

                <!-- 검색 -->
                <input type="text" name="s"
                       value="<?php echo esc_attr( $search ); ?>"
                       placeholder="작성자 또는 내용 검색…">

                <button type="submit" class="button button-primary">필터 적용</button>
                <a href="<?php echo esc_url( admin_url('admin.php?page=psc-reviews') ); ?>"
                   class="button">초기화</a>
            </div>
        </form>

        <!-- 리뷰 테이블 -->
        <div class="psc-adm-table-wrap">
            <?php if ( empty( $reviews ) ) : ?>
                <div class="psc-adm-empty">🐾 표시할 리뷰가 없습니다</div>
            <?php else : ?>
            <table class="psc-adm-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>매장</th>
                        <th>작성자</th>
                        <th>별점</th>
                        <th>내용 / 이미지</th>
                        <th>좋아요</th>
                        <th>상태</th>
                        <th>작성일</th>
                        <th>관리</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $reviews as $rv ) :
                        $images = psc_get_review_images( (int) $rv->id );
                        $nonce  = wp_create_nonce( 'psc_review_action_' . $rv->id );

                        $action_url = function( string $act ) use ( $rv, $nonce ): string {
                            return esc_url( add_query_arg( [
                                'page'       => 'psc-reviews',
                                'psc_action' => $act,
                                'review_id'  => $rv->id,
                                '_wpnonce'   => $nonce,
                            ], admin_url( 'admin.php' ) ) );
                        };
                    ?>
                    <tr>
                        <!-- ID -->
                        <td style="color:#8b95a1;">#<?php echo (int) $rv->id; ?></td>

                        <!-- 매장 -->
                        <td>
                            <a href="<?php echo esc_url( get_permalink( (int) $rv->store_id ) ); ?>"
                               target="_blank" style="color:#224471;text-decoration:none;">
                                <?php echo esc_html( $rv->store_name ?: '(삭제됨)' ); ?>
                            </a>
                        </td>

                        <!-- 작성자 -->
                        <td>
                            <div><?php echo esc_html( psc_mask_name( $rv->display_name ) ); ?></div>
                            <div style="font-size:.74rem;color:#8b95a1;">
                                UID: <?php echo (int) $rv->user_id; ?>
                            </div>
                        </td>

                        <!-- 별점 -->
                        <td>
                            <span class="psc-adm-stars">
                                <?php echo str_repeat( '★', (int) $rv->rating )
                                         . str_repeat( '☆', 5 - (int) $rv->rating ); ?>
                            </span>
                            <div style="font-size:.76rem;color:#8b95a1;">
                                <?php echo (int) $rv->rating; ?>점
                            </div>
                        </td>

                        <!-- 내용 / 이미지 -->
                        <td>
                            <div class="psc-adm-content-preview">
                                <?php echo esc_html( $rv->content ); ?>
                            </div>
                            <?php if ( ! empty( $images ) ) : ?>
                            <div class="psc-adm-thumbs">
                                <?php foreach ( $images as $img ) : ?>
                                    <img src="<?php echo esc_url( $img->url ); ?>"
                                         alt="리뷰이미지"
                                         title="<?php echo esc_attr( $img->url ); ?>">
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </td>

                        <!-- 좋아요 -->
                        <td style="text-align:center;">
                            👍 <?php echo (int) $rv->likes; ?>
                        </td>

                        <!-- 상태 -->
                        <td>
                            <span class="psc-adm-badge <?php echo esc_attr( $rv->status ); ?>">
                                <?php
                                $status_label = [
                                    'published' => '공개',
                                    'hidden'    => '숨김',
                                    'deleted'   => '삭제됨',
                                ];
                                echo esc_html( $status_label[ $rv->status ] ?? $rv->status );
                                ?>
                            </span>
                        </td>

                        <!-- 작성일 -->
                        <td style="white-space:nowrap;color:#8b95a1;font-size:.82rem;">
                            <?php echo esc_html(
                                date_i18n( 'Y.m.d', strtotime( $rv->created_at ) )
                            ); ?>
                        </td>

                        <!-- 관리 버튼 -->
                        <td>
                            <div class="psc-adm-actions">
                                <?php if ( $rv->status !== 'published' ) : ?>
                                    <a href="<?php echo $action_url('publish'); ?>"
                                       class="psc-adm-btn publish">공개</a>
                                <?php endif; ?>
                                <?php if ( $rv->status !== 'hidden' ) : ?>
                                    <a href="<?php echo $action_url('hide'); ?>"
                                       class="psc-adm-btn hide">숨김</a>
                                <?php endif; ?>
                                <?php if ( $rv->status !== 'deleted' ) : ?>
                                    <a href="<?php echo $action_url('delete'); ?>"
                                       class="psc-adm-btn delete"
                                       onclick="return confirm('정말 삭제하시겠습니까?')">삭제</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- 페이지네이션 -->
            <?php if ( $total_pages > 1 ) : ?>
            <div class="psc-adm-pagination">
                <?php
                $base_args = array_filter( [
                    'page'     => 'psc-reviews',
                    'status'   => $status !== 'all' ? $status : '',
                    'store_id' => $store_id ?: '',
                    'rating'   => $rating   ?: '',
                    's'        => $search,
                ] );

                for ( $p = 1; $p <= $total_pages; $p++ ) :
                    $url = esc_url( add_query_arg(
                        array_merge( $base_args, [ 'paged' => $p ] ),
                        admin_url( 'admin.php' )
                    ) );
                    if ( $p === $paged ) :
                        echo "<span class='current'>{$p}</span>";
                    else :
                        echo "<a href='{$url}'>{$p}</a>";
                    endif;
                endfor;
                ?>
            </div>
            <?php endif; ?>

            <?php endif; ?>
        </div><!-- .psc-adm-table-wrap -->

    </div><!-- .wrap -->
    <?php
}
