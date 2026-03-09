<?php
/**
 * Module: Vendor Dashboard — Notices
 * 공지 관리 페이지
 */

defined( 'ABSPATH' ) || exit;

/* ════════════════════════════════════════════
   공지 목록
   ════════════════════════════════════════════ */
function psc_dash_page_notices( int $store_id, int $user_id ): void {

    $is_premium = function_exists( 'psc_store_is_premium' ) && psc_store_is_premium( $store_id );
    $cpt        = defined( 'PSC_NOTICE_CPT' ) ? PSC_NOTICE_CPT : 'store_notice';

    $notices = get_posts( [
        'post_type'      => $cpt,
        'post_status'    => [ 'publish', 'draft' ],
        'posts_per_page' => -1,
        'meta_key'       => defined( 'PSC_NOTICE_STORE_META' ) ? PSC_NOTICE_STORE_META : 'notice_store_id',
        'meta_value'     => $store_id,
        'orderby'        => 'date',
        'order'          => 'DESC',
    ] );

    $new_url  = home_url( '/vendor/notices/new/' );
    $edit_url = home_url( '/vendor/notices/edit/' );

    ?>
    <div class="psc-dash-section">
        <div class="psc-dash-page-header">
            <h1 class="psc-dash-page-title">📢 공지 관리</h1>
            <a href="<?php echo esc_url( $new_url ); ?>"
               class="psc-dash-btn psc-dash-btn--primary psc-dash-btn--sm">
                + 공지 작성
            </a>
        </div>

        <?php if ( ! $is_premium && count( $notices ) >= 1 ) : ?>
            <div class="psc-dash-alert psc-dash-alert--info">
                무료 플랜은 공지 1개까지 등록 가능합니다.
                프리미엄으로 업그레이드하면 무제한 + 상단 고정 기능을 사용할 수 있습니다.
<a href="<?php echo esc_url( home_url( '/upgrade/' ) ); ?>"
   class="psc-dash-btn psc-dash-btn--primary psc-dash-btn--sm"
   style="margin-top:8px;display:inline-flex">
   업그레이드 →
</a>
            </div>
        <?php endif; ?>

        <?php if ( empty( $notices ) ) : ?>
            <div class="psc-dash-empty">등록된 공지가 없습니다.</div>
        <?php else : ?>
            <div class="psc-notice-list">
                <?php foreach ( $notices as $n ) :
                    $is_pinned = (bool) get_post_meta( $n->ID, 'is_pinned', true );
                    $expire    = get_post_meta( $n->ID, 'notice_expire_date', true );
                    $is_draft  = $n->post_status === 'draft';
                ?>
                    <div class="psc-notice-list-item">
                        <div class="psc-notice-list-info">
                            <div class="psc-notice-list-title">
                                <?php if ( $is_pinned ) : ?><span class="psc-pin-badge">📌</span><?php endif; ?>
                                <?php if ( $is_draft ) : ?><span class="psc-status-badge draft">비공개</span><?php endif; ?>
                                <?php echo esc_html( $n->post_title ); ?>
                            </div>
                            <div class="psc-notice-list-meta">
                                <?php echo get_the_date( 'Y.m.d', $n ); ?>
                                <?php if ( $expire ) : ?>
                                    · 만료: <?php echo esc_html( $expire ); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="psc-notice-list-actions">
                            <a href="<?php echo esc_url( add_query_arg( 'id', $n->ID, $edit_url ) ); ?>"
                               class="psc-dash-btn psc-dash-btn--outline psc-dash-btn--xs">수정</a>
                            <button type="button"
                                    class="psc-dash-btn psc-dash-btn--danger psc-dash-btn--xs"
                                    onclick="pscDeleteNotice(<?php echo $n->ID; ?>, '<?php echo esc_js( $n->post_title ); ?>')">
                                삭제
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <style>
    .psc-notice-list { display:flex; flex-direction:column; gap:10px; }
    .psc-notice-list-item {
        display:flex; align-items:center; justify-content:space-between;
        gap:12px; padding:14px 16px; background:#fff;
        border:1px solid #e5e7eb; border-radius:12px; flex-wrap:wrap;
    }
    .psc-notice-list-title { font-weight:600; font-size:.95rem; margin-bottom:4px; }
    .psc-notice-list-meta  { font-size:.8rem; color:#9ca3af; }
    .psc-notice-list-actions { display:flex; gap:8px; flex-shrink:0; }
    .psc-pin-badge  { font-size:.85rem; margin-right:4px; }
    .psc-status-badge { font-size:.72rem; padding:2px 7px; border-radius:10px; font-weight:600; }
    .psc-status-badge.draft { background:#f3f4f6; color:#6b7280; }
    .psc-dash-btn--danger {
        background:#fee2e2; color:#dc2626; border:1px solid #fca5a5;
    }
    .psc-dash-btn--danger:hover { background:#fecaca; }
    .psc-dash-empty { color:#9ca3af; text-align:center; padding:40px 0; }
    </style>

    <script>
    function pscDeleteNotice(id, title){
        if(!confirm('"' + title + '" 공지를 삭제하시겠습니까?')) return;
        var fd = new FormData();
        fd.append('action',   'psc_delete_notice');
        fd.append('notice_id', id);
        fd.append('nonce',    '<?php echo wp_create_nonce("psc_delete_notice"); ?>');
        fetch('<?php echo admin_url("admin-ajax.php"); ?>', { method:'POST', body:fd })
            .then(r=>r.json())
            .then(d=>{ if(d.success) location.reload(); else alert(d.data||'삭제 실패'); });
    }
    </script>
    <?php
}

/* ════════════════════════════════════════════
   공지 작성 / 수정 공통 폼
   ════════════════════════════════════════════ */
function psc_dash_page_notices_new( int $store_id, int $user_id ): void {
    psc_dash_notice_form( $store_id, $user_id, null );
}

function psc_dash_page_notices_edit( int $store_id, int $user_id ): void {
    $notice_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
    if ( ! $notice_id ) {
        wp_safe_redirect( home_url( '/vendor/notices/' ) ); exit;
    }
    psc_dash_notice_form( $store_id, $user_id, $notice_id );
}

function psc_dash_notice_form( int $store_id, int $user_id, ?int $notice_id ): void {

    $is_premium = function_exists( 'psc_store_is_premium' ) && psc_store_is_premium( $store_id );
    $cpt        = defined( 'PSC_NOTICE_CPT' ) ? PSC_NOTICE_CPT : 'store_notice';
    $is_edit    = $notice_id !== null;
    $message    = '';
    $error      = '';

    /* ── 저장 처리 ── */
    if (
        $_SERVER['REQUEST_METHOD'] === 'POST'
        && isset( $_POST['psc_notice_nonce'] )
        && wp_verify_nonce(
            sanitize_text_field( wp_unslash( $_POST['psc_notice_nonce'] ) ),
            'psc_notice_save'
        )
    ) {
        $result = psc_dash_save_notice( $store_id, $user_id, $notice_id );
        if ( is_wp_error( $result ) ) {
            $error = $result->get_error_message();
        } else {
            wp_safe_redirect( home_url( '/vendor/notices/' ) ); exit;
        }
    }

    /* ── 기존 값 ── */
    $n_title   = '';
    $n_content = '';
    $n_expire  = '';
    $n_pinned  = false;
    $n_status  = 'publish';

    if ( $is_edit && $notice_id ) {
        $post      = get_post( $notice_id );
        $n_title   = $post ? $post->post_title   : '';
        $n_content = $post ? $post->post_content : '';
        $n_status  = $post ? $post->post_status  : 'publish';
        $n_expire  = get_post_meta( $notice_id, 'notice_expire_date', true );
        $n_pinned  = (bool) get_post_meta( $notice_id, 'is_pinned', true );
    }

    $back_url = home_url( '/vendor/notices/' );

    ?>
    <div class="psc-dash-section">
        <div class="psc-dash-page-header">
            <a href="<?php echo esc_url( $back_url ); ?>" class="psc-back-link">← 공지 목록</a>
            <h1 class="psc-dash-page-title"><?php echo $is_edit ? '공지 수정' : '공지 작성'; ?></h1>
        </div>

        <?php if ( $error ) : ?>
            <div class="psc-dash-alert psc-dash-alert--error"><?php echo esc_html( $error ); ?></div>
        <?php endif; ?>

        <form method="post" class="psc-dash-form">
            <?php wp_nonce_field( 'psc_notice_save', 'psc_notice_nonce' ); ?>

            <div class="psc-dash-card">

                <div class="psc-dash-form-group">
                    <label>제목 <span style="color:#ef4444">*</span></label>
                    <input type="text" name="psc_notice_title"
                           value="<?php echo esc_attr( $n_title ); ?>"
                           placeholder="공지 제목을 입력하세요" required>
                </div>

                <div class="psc-dash-form-group">
                    <label>내용 <span style="color:#ef4444">*</span></label>
                    <textarea name="psc_notice_content" rows="7" required
                              placeholder="공지 내용을 입력하세요"><?php echo esc_textarea( $n_content ); ?></textarea>
                </div>

                <div class="psc-dash-form-group">
                    <label>만료일</label>
                    <input type="date" name="psc_notice_expire"
                           value="<?php echo esc_attr( $n_expire ); ?>">
                    <span class="psc-dash-form-hint">비워두면 상시 노출됩니다.</span>
                </div>

                <!-- 상단 고정 (프리미엄) -->
                <div class="psc-dash-form-group">
                    <label class="psc-pin-field-label <?php echo ! $is_premium ? 'disabled' : ''; ?>">
                        <input type="checkbox"
                               name="psc_notice_pinned"
                               value="1"
                               <?php checked( $n_pinned ); ?>
                               <?php echo ! $is_premium ? 'disabled' : ''; ?>>
                        <span class="psc-pin-icon">📌</span>
                        상단 고정
                        <?php if ( ! $is_premium ) : ?>
                            <span class="psc-pin-premium-hint">프리미엄 전용</span>
                        <?php endif; ?>
                    </label>
                </div>

                <!-- 공개 상태 -->
                <div class="psc-dash-form-group">
                    <label>공개 상태</label>
                    <select name="psc_notice_status">
                        <option value="publish" <?php selected( $n_status, 'publish' ); ?>>공개</option>
                        <option value="draft"   <?php selected( $n_status, 'draft'   ); ?>>비공개</option>
                    </select>
                </div>

            </div>

            <button type="submit"
                    class="psc-dash-btn psc-dash-btn--primary psc-dash-btn--full">
                <?php echo $is_edit ? '수정 완료' : '등록하기'; ?>
            </button>
        </form>
    </div>

    <style>
    /* 뒤로가기 */
    .psc-back-link {
        font-size:.85rem; color:#6b7280; text-decoration:none; margin-right:12px;
    }
    .psc-back-link:hover { color:#111; }

    /* 상단 고정 필드 */
    .psc-pin-field-label {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
        font-size: .95rem;
        font-weight: 600;
        padding: 10px 14px;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        background: #f9fafb;
        user-select: none;
    }
    .psc-pin-field-label input[type=checkbox] {
        width: 18px; height: 18px; cursor: pointer; margin: 0;
        accent-color: #667eea;
    }
    .psc-pin-field-label.disabled {
        opacity: .5; cursor: not-allowed;
    }
    .psc-pin-field-label.disabled input { cursor: not-allowed; }
    .psc-pin-icon { font-size: 1.1rem; }
    .psc-pin-premium-hint {
        font-size: .72rem; color: #9ca3af; font-weight: 400;
        margin-left: 4px;
    }
    </style>
    <?php
}

/* ════════════════════════════════════════════
   공지 저장 함수
   ════════════════════════════════════════════ */
function psc_dash_save_notice( int $store_id, int $user_id, ?int $notice_id ): int|WP_Error {

    $is_premium = function_exists( 'psc_store_is_premium' ) && psc_store_is_premium( $store_id );
    $cpt        = defined( 'PSC_NOTICE_CPT' ) ? PSC_NOTICE_CPT : 'store_notice';
    $meta_key   = defined( 'PSC_NOTICE_STORE_META' ) ? PSC_NOTICE_STORE_META : 'notice_store_id';

    $title   = sanitize_text_field( wp_unslash( $_POST['psc_notice_title']   ?? '' ) );
    $content = wp_kses_post( wp_unslash( $_POST['psc_notice_content'] ?? '' ) );
    $expire  = sanitize_text_field( $_POST['psc_notice_expire'] ?? '' );
    $pinned  = ! empty( $_POST['psc_notice_pinned'] ) && $is_premium;
    $status  = in_array( $_POST['psc_notice_status'] ?? '', [ 'publish', 'draft' ], true )
               ? $_POST['psc_notice_status'] : 'publish';

    if ( ! $title )   return new WP_Error( 'empty_title',   '제목을 입력해주세요.' );
    if ( ! $content ) return new WP_Error( 'empty_content', '내용을 입력해주세요.' );

    /* 무료 플랜 공지 개수 제한 */
    if ( ! $is_premium && $status === 'publish' ) {
        $active = function_exists( 'psc_notice_count_active' )
            ? psc_notice_count_active( $store_id )
            : 0;
        $is_new = $notice_id === null;
        if ( $is_new && $active >= 1 ) {
            return new WP_Error( 'limit_reached', '무료 플랜은 공지를 1개까지 등록할 수 있습니다.' );
        }
    }

    $post_data = [
        'post_type'    => $cpt,
        'post_title'   => $title,
        'post_content' => $content,
        'post_status'  => $status,
        'post_author'  => $user_id,
    ];

    if ( $notice_id ) {
        $post_data['ID'] = $notice_id;
        $result = wp_update_post( $post_data, true );
    } else {
        $result = wp_insert_post( $post_data, true );
    }

    if ( is_wp_error( $result ) ) return $result;

    $saved_id = (int) $result;
    update_post_meta( $saved_id, $meta_key,            $store_id );
    update_post_meta( $saved_id, 'is_pinned',          $pinned ? '1' : '' );
    update_post_meta( $saved_id, 'notice_expire_date', $expire );

    return $saved_id;
}

/* ════════════════════════════════════════════
   공지 삭제 AJAX
   ════════════════════════════════════════════ */
add_action( 'wp_ajax_psc_delete_notice', 'psc_ajax_delete_notice' );
function psc_ajax_delete_notice(): void {
    check_ajax_referer( 'psc_delete_notice', 'nonce' );
    $notice_id = absint( $_POST['notice_id'] ?? 0 );
    if ( ! $notice_id ) wp_send_json_error( '잘못된 요청입니다.' );

    $post = get_post( $notice_id );
    $cpt  = defined( 'PSC_NOTICE_CPT' ) ? PSC_NOTICE_CPT : 'store_notice';
    if ( ! $post || $post->post_type !== $cpt ) wp_send_json_error( '공지를 찾을 수 없습니다.' );

    /* 작성자 또는 관리자만 삭제 가능 */
    if ( (int) $post->post_author !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( '권한이 없습니다.' );
    }

    wp_delete_post( $notice_id, true );
    wp_send_json_success();
}
