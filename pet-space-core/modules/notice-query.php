<?php
/**
 * Module: Notice Query
 * ══════════════════════════════════════════════════════════════
 * - Elementor Loop Grid 커스텀 쿼리 (Query ID: psc_store_notices)
 * - REST API 엔드포인트: GET /wp-json/psc/v1/notice/{id}
 * - 하단 슬라이드업 시트 모달 (모바일) / 중앙 팝업 (PC)
 * ══════════════════════════════════════════════════════════════
 */

defined( 'ABSPATH' ) || exit;

/* ============================================================
   1. Elementor Loop Grid 커스텀 쿼리
   ============================================================ */

add_action( 'elementor/query/psc_store_notices', 'psc_elementor_notice_query' );

function psc_elementor_notice_query( WP_Query $query ): void {

    $store_id = psc_notice_resolve_store_id();

    if ( ! $store_id ) {
        $query->set( 'post__in', [ 0 ] );
        return;
    }

    $is_premium = psc_store_is_premium( $store_id );
    $today      = gmdate( 'Y-m-d' );

    $query->set( 'post_type',   PSC_NOTICE_CPT );
    $query->set( 'post_status', 'publish' );

    $query->set( 'meta_query', [
        'relation'      => 'AND',
        'store_clause'  => [
            'key'     => PSC_NOTICE_STORE_META,
            'value'   => (string) $store_id,
            'compare' => '=',
        ],
        'expire_clause' => [
            'relation' => 'OR',
            [ 'key' => 'notice_expire_date', 'compare' => 'NOT EXISTS' ],
            [ 'key' => 'notice_expire_date', 'value'   => '', 'compare' => '=' ],
            [
                'key'     => 'notice_expire_date',
                'value'   => $today,
                'compare' => '>=',
                'type'    => 'DATE',
            ],
        ],
    ] );

    if ( $is_premium ) {
        $query->set( 'meta_key', 'is_pinned' );
        $query->set( 'orderby', [
            'meta_value_num' => 'DESC',
            'date'           => 'DESC',
        ] );
    } else {
        $query->set( 'meta_key', '' );
        $query->set( 'orderby', 'date' );
        $query->set( 'order',   'DESC' );
    }
}

/* ============================================================
   2. Store ID 감지 헬퍼
   ============================================================ */

function psc_notice_resolve_store_id(): int {

    $id = psc_current_store_id();
    if ( $id ) return $id;

    if ( isset( $_GET['store_id'] ) ) {
        return absint( $_GET['store_id'] );
    }

    global $post;
    if ( $post instanceof WP_Post && $post->post_type === PSC_STORE_CPT ) {
        return (int) $post->ID;
    }

    return 0;
}

/* ============================================================
   3. [psc_notice_card] 숏코드  (Loop Item 템플릿용)
   ============================================================ */

add_shortcode( 'psc_notice_card', 'psc_shortcode_notice_card' );

function psc_shortcode_notice_card(): string {

    $id = get_the_ID();
    if ( ! $id || get_post_type( $id ) !== PSC_NOTICE_CPT ) return '';

    $title     = esc_html( get_the_title( $id ) );
    $date      = esc_html( get_the_date( 'Y.m.d', $id ) );
    $is_pinned = (bool) psc_notice_get_meta( $id, 'is_pinned' );
    $pin_badge = $is_pinned
        ? '<span class="psc-pin-badge">📌 고정</span>'
        : '';

    return sprintf(
        '<div class="psc-notice-card" data-notice-id="%d" role="button" tabindex="0" aria-label="%s 공지 열기">
            <div class="psc-card-inner">
                <p class="psc-card-title">%s%s</p>
                <p class="psc-card-date">%s</p>
            </div>
            <span class="psc-card-arrow">›</span>
        </div>',
        $id,
        esc_attr( $title ),
        $pin_badge,
        $title,
        $date
    );
}

/* ============================================================
   3-1. [psc_notice_id] 숏코드  (Attributes 방식용)
   ============================================================ */

add_shortcode( 'psc_notice_id', function(): string {
    $id = get_the_ID();
    if ( ! $id || get_post_type( $id ) !== PSC_NOTICE_CPT ) return '0';
    return (string) $id;
} );

/* ============================================================
   4. REST API  GET /wp-json/psc/v1/notice/{id}
   ============================================================ */

add_action( 'rest_api_init', 'psc_register_notice_rest' );

function psc_register_notice_rest(): void {
    register_rest_route( 'psc/v1', '/notice/(?P<id>\d+)', [
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'psc_rest_get_notice',
        'permission_callback' => '__return_true',
        'args'                => [
            'id' => [
                'validate_callback' => fn( $v ) => is_numeric( $v ),
                'sanitize_callback' => 'absint',
            ],
        ],
    ] );
}

function psc_rest_get_notice( WP_REST_Request $request ): WP_REST_Response|WP_Error {

    $id   = (int) $request->get_param( 'id' );
    $post = get_post( $id );

    if ( ! $post || $post->post_type !== PSC_NOTICE_CPT || $post->post_status !== 'publish' ) {
        return new WP_Error( 'not_found', '공지를 찾을 수 없습니다.', [ 'status' => 404 ] );
    }

    $expire = psc_notice_get_meta( $id, 'notice_expire_date' );
    if ( $expire && $expire < gmdate( 'Y-m-d' ) ) {
        return new WP_Error( 'expired', '만료된 공지입니다.', [ 'status' => 410 ] );
    }

    $is_pinned = (bool) psc_notice_get_meta( $id, 'is_pinned' );

    return new WP_REST_Response( [
        'id'      => $id,
        'title'   => html_entity_decode( get_the_title( $id ), ENT_QUOTES, 'UTF-8' ),
        'date'    => get_the_date( 'Y년 m월 d일', $id ),
        'pinned'  => $is_pinned,
        'excerpt' => wp_strip_all_tags( get_the_excerpt( $post ) ),
        'content' => apply_filters( 'the_content', $post->post_content ),
    ], 200 );
}

/* ============================================================
   5. 프론트엔드 에셋
   ============================================================ */

add_action( 'wp_enqueue_scripts', 'psc_notice_enqueue_assets' );

function psc_notice_enqueue_assets(): void {

    if ( ! is_singular( PSC_STORE_CPT ) ) return;

    $rest_base = esc_js( rest_url( 'psc/v1/notice/' ) );
    $nonce     = wp_create_nonce( 'wp_rest' );

    /* ── CSS ── */
    $css = '
/* 오버레이 */
.psc-modal-overlay{
    display:none!important;
    position:fixed!important;
    top:0!important;left:0!important;right:0!important;bottom:0!important;
    width:100%!important;height:100%!important;
    background:rgba(0,0,0,.5)!important;
    z-index:99998!important;
}
.psc-modal-overlay.is-open{display:block!important;}

/* 모바일: 하단 시트 */
.psc-notice-sheet{
    position:fixed!important;
    left:0!important;right:0!important;bottom:0!important;top:auto!important;
    width:100%!important;
    max-height:60vh!important;
    background:#fff!important;
    border-radius:20px 20px 0 0!important;
    box-shadow:0 -4px 24px rgba(0,0,0,.15)!important;
    z-index:99999!important;
    display:flex!important;
    flex-direction:column!important;
    transform:translateY(100%)!important;
    transition:transform .35s cubic-bezier(.32,1,.23,1)!important;
    overflow:hidden!important;
    margin:0!important;
    padding:0!important;
    box-sizing:border-box!important;
}
.psc-notice-sheet.is-open{
    transform:translateY(0)!important;
}

/* PC: 중앙 팝업 */
@media(min-width:769px){
    .psc-notice-sheet{
        left:50%!important;
        right:auto!important;
        bottom:50%!important;
        top:auto!important;
        width:520px!important;
        max-height:70vh!important;
        border-radius:16px!important;
        transform:translate(-50%, 50%) scale(.96)!important;
        opacity:0!important;
        transition:transform .3s cubic-bezier(.32,1,.23,1), opacity .3s ease!important;
        box-shadow:0 8px 48px rgba(0,0,0,.22)!important;
    }
    .psc-notice-sheet.is-open{
        transform:translate(-50%, 50%) scale(1)!important;
        opacity:1!important;
    }
}

/* 핸들 (모바일만) */
.psc-sheet-handle{
    flex-shrink:0;
    padding:12px 0 4px;
    display:flex;
    justify-content:center;
    cursor:grab;
    background:#fff;
}
.psc-sheet-handle::before{
    content:"";
    display:block;
    width:40px;height:4px;
    background:#d1d5db;
    border-radius:99px;
}
@media(min-width:769px){
    .psc-sheet-handle{display:none!important;}
}

/* 헤더 */
.psc-sheet-header{
    flex-shrink:0;
    display:flex;
    align-items:flex-start;
    gap:12px;
    padding:20px 20px 14px;
    border-bottom:1px solid #f0f0f0;
    background:#fff;
}
.psc-sheet-header-text{flex:1;min-width:0;}
.psc-sheet-title{
    margin:0;
    font-size:1.05rem;
    font-weight:700;
    line-height:1.4;
    word-break:break-word;
    color:#111;
}
.psc-sheet-date{
    margin-top:4px;
    font-size:.8rem;
    color:#888;
}
.psc-sheet-close{
    flex-shrink:0;
    width:32px;height:32px;
    border:none;
    background:none;
    cursor:pointer;
    font-size:1.3rem;
    color:#555;
    line-height:1;
    padding:0;
}
.psc-sheet-close:hover{color:#111; background:none;}

button#psc-sheet-close, button#psc-sheet-close:hover button#psc-sheet-close:focus {background: none; color: #333}

/* 본문 */
.psc-sheet-body{
    flex:1;
    overflow-y:auto;
    padding:18px 20px 32px;
    font-size:.93rem;
    line-height:1.7;
    color:#333;
    word-break:break-word;
    overscroll-behavior:contain;
    -webkit-overflow-scrolling:touch;
    background:#fff;
}
.psc-sheet-body img{max-width:100%;height:auto;border-radius:8px;}

/* 로딩 */
.psc-sheet-loading{
    display:flex;
    align-items:center;
    justify-content:center;
    padding:48px 0;
    color:#aaa;
    font-size:.9rem;
}

/* 핀 배지 */
.psc-pin-badge{
    display:inline-block;
    font-size:.7rem;
    background:#fff3cd;
    color:#856404;
    border-radius:4px;
    padding:1px 6px;
    margin-right:6px;
    vertical-align:middle;
    font-weight:600;
}

/* 카드 */
.psc-notice-card{
    display:flex!important;
    align-items:center!important;
    justify-content:space-between!important;
    padding:14px 16px!important;
    cursor:pointer!important;
    border-radius:10px!important;
    transition:background .15s!important;
    -webkit-tap-highlight-color:transparent!important;
}
.psc-notice-card:hover{background:#f9f9f9!important;}
.psc-card-inner{flex:1;min-width:0;}
.psc-card-title{
    margin:0 0 4px!important;
    font-size:.95rem!important;
    font-weight:600!important;
    white-space:nowrap!important;
    overflow:hidden!important;
    text-overflow:ellipsis!important;
    color:#111!important;
}
.psc-card-date{
    margin:0!important;
    font-size:.8rem!important;
    color:#888!important;
}
.psc-card-arrow{
    flex-shrink:0;
    font-size:1.2rem;
    color:#ccc;
    margin-left:8px;
}
';

    wp_register_style( 'psc-notice-sheet', false );
    wp_enqueue_style( 'psc-notice-sheet' );
    wp_add_inline_style( 'psc-notice-sheet', $css );

    /* ── JS ── */
    $js = <<<JS
(function(){
    'use strict';

    var REST_BASE = '{$rest_base}';
    var NONCE     = '{$nonce}';

    function buildDOM(){
        if(document.getElementById('psc-modal-overlay')) return;

        var overlay       = document.createElement('div');
        overlay.id        = 'psc-modal-overlay';
        overlay.className = 'psc-modal-overlay';

        var sheet         = document.createElement('div');
        sheet.id          = 'psc-notice-sheet';
        sheet.className   = 'psc-notice-sheet';
        sheet.setAttribute('role','dialog');
        sheet.setAttribute('aria-modal','true');
        sheet.setAttribute('aria-labelledby','psc-sheet-title');
        sheet.innerHTML   =
            '<div class="psc-sheet-handle" id="psc-sheet-handle"></div>' +
            '<div class="psc-sheet-header">' +
                '<div class="psc-sheet-header-text">' +
                    '<p class="psc-sheet-title" id="psc-sheet-title"></p>' +
                    '<p class="psc-sheet-date"  id="psc-sheet-date"></p>' +
                '</div>' +
                '<button class="psc-sheet-close" id="psc-sheet-close" aria-label="닫기">\u00d7</button>' +
            '</div>' +
            '<div class="psc-sheet-body" id="psc-sheet-body"></div>';

        document.body.appendChild(overlay);
        document.body.appendChild(sheet);
    }

    function openNotice(noticeId){
        buildDOM();

        var overlay = document.getElementById('psc-modal-overlay');
        var sheet   = document.getElementById('psc-notice-sheet');
        var body    = document.getElementById('psc-sheet-body');
        var title   = document.getElementById('psc-sheet-title');
        var date    = document.getElementById('psc-sheet-date');

        title.textContent = '';
        date.textContent  = '';
        body.innerHTML    = '<div class="psc-sheet-loading">\ubd88\ub7ec\uc624\ub294 \uc911\u2026</div>';

        overlay.classList.add('is-open');
        sheet.classList.add('is-open');
        document.body.style.overflow = 'hidden';

        setTimeout(function(){
            var btn = document.getElementById('psc-sheet-close');
            if(btn) btn.focus();
        }, 350);

        fetch(REST_BASE + noticeId, {
            headers: { 'X-WP-Nonce': NONCE }
        })
        .then(function(res){
            if(!res.ok) throw new Error('HTTP ' + res.status);
            return res.json();
        })
        .then(function(data){
            title.textContent = data.title || '';
            date.textContent  = data.date  || '';
            body.innerHTML    = data.content || data.excerpt || '<p>\ub0b4\uc6a9\uc774 \uc5c6\uc2b5\ub2c8\ub2e4.</p>';
        })
        .catch(function(e){
            body.innerHTML = '<p style="color:#c00;padding:16px 0;">\uacf5\uc9c0\ub97c \ubd88\ub7ec\uc624\uc9c0 \ubabb\ud588\uc2b5\ub2c8\ub2e4.</p>';
            console.error('[PSC Notice]', e);
        });
    }

    function closeNotice(){
        var overlay = document.getElementById('psc-modal-overlay');
        var sheet   = document.getElementById('psc-notice-sheet');
        if(!sheet) return;
        sheet.classList.remove('is-open');
        if(overlay) overlay.classList.remove('is-open');
        document.body.style.overflow = '';
    }

    document.addEventListener('click', function(e){
        var card = e.target.closest('[data-notice-id]');
        if(card){
            e.preventDefault();
            e.stopPropagation();
            openNotice(card.getAttribute('data-notice-id'));
            return;
        }
        if(e.target.id === 'psc-modal-overlay'){ closeNotice(); return; }
        if(e.target.id === 'psc-sheet-close'){   closeNotice(); return; }
    });

    document.addEventListener('keydown', function(e){
        if(e.key === 'Escape'){ closeNotice(); return; }
        if(e.key === 'Enter' || e.key === ' '){
            var card = document.activeElement && document.activeElement.closest('[data-notice-id]');
            if(card){ e.preventDefault(); openNotice(card.getAttribute('data-notice-id')); }
        }
    });

    var touchStartY = 0;
    document.addEventListener('touchstart', function(e){
        var sheet = document.getElementById('psc-notice-sheet');
        if(sheet && sheet.contains(e.target)) touchStartY = e.touches[0].clientY;
    },{passive:true});
    document.addEventListener('touchend', function(e){
        var sheet = document.getElementById('psc-notice-sheet');
        if(!sheet || !sheet.contains(e.target)) return;
        if(e.changedTouches[0].clientY - touchStartY > 80) closeNotice();
    },{passive:true});

})();
JS;

    wp_add_inline_script( 'elementor-frontend', $js );
}
/* ============================================================
   6. [store_notices_list] 숏코드  (전체보기 페이지용)
   ============================================================ */

add_shortcode( 'store_notices_list', 'psc_sc_notices_list' );

function psc_sc_notices_list( array $atts ): string {

    /* URL 파라미터 store_id 우선, 없으면 singular store 페이지 */
    $store_id = isset( $_GET['store_id'] )
        ? absint( $_GET['store_id'] )
        : psc_notice_resolve_store_id();

    if ( ! $store_id ) {
        return '<p class="psc-no-notices">매장 정보를 찾을 수 없습니다.</p>';
    }

    $store     = get_post( $store_id );
    $store_name = $store ? get_the_title( $store_id ) : '';
    $is_premium = psc_store_is_premium( $store_id );
    $today      = gmdate( 'Y-m-d' );

    $args = [
        'post_type'      => PSC_NOTICE_CPT,
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_query'     => [
            'relation'      => 'AND',
            'store_clause'  => [
                'key'     => PSC_NOTICE_STORE_META,
                'value'   => (string) $store_id,
                'compare' => '=',
            ],
            'expire_clause' => [
                'relation' => 'OR',
                [ 'key' => 'notice_expire_date', 'compare' => 'NOT EXISTS' ],
                [ 'key' => 'notice_expire_date', 'value'   => '', 'compare' => '=' ],
                [
                    'key'     => 'notice_expire_date',
                    'value'   => $today,
                    'compare' => '>=',
                    'type'    => 'DATE',
                ],
            ],
        ],
    ];

    if ( $is_premium ) {
        $args['meta_key'] = 'is_pinned';
        $args['orderby']  = [
            'meta_value_num' => 'DESC',
            'date'           => 'DESC',
        ];
    } else {
        $args['orderby'] = 'date';
        $args['order']   = 'DESC';
    }

    $q = new WP_Query( $args );

    ob_start();
    ?>
    <div class="psc-notices-list-wrap">

        <?php if ( $store_name ) : ?>
        <div class="psc-notices-list-header">
            <button class="psc-notices-back" onclick="history.back()">‹</button>
            <h2 class="psc-notices-list-title">공지사항</h2>
        </div>
        <?php endif; ?>

        <?php if ( ! $q->have_posts() ) : ?>
            <p class="psc-no-notices">등록된 공지가 없습니다.</p>
        <?php else : ?>
        <ul class="psc-notices-list">
            <?php while ( $q->have_posts() ) : $q->the_post();
                $nid      = get_the_ID();
                $pinned   = (bool) get_post_meta( $nid, 'is_pinned', true );
                $expire   = get_post_meta( $nid, 'notice_expire_date', true );
                $title    = get_the_title();
                $content  = get_the_content();
                $date_str = get_the_date( 'Y.m.d' );
            ?>
            <li class="psc-notices-list__item<?php echo $pinned ? ' is-pinned' : ''; ?>"
                data-notice-id="<?php echo esc_attr( $nid ); ?>">
                <div class="psc-notices-list__top">
                    <span class="psc-notices-list__store">
                       <?php echo esc_html( $store_name ); ?>
                    </span>
                    <?php if ( $pinned ) : ?>
                        <span class="psc-notices-list__badge psc-badge--pinned">중요</span>
                    <?php endif; ?>
                </div>
                <p class="psc-notices-list__title">
                    <?php echo esc_html( $title ); ?>
                </p>
                <p class="psc-notices-list__excerpt">
                    <?php echo esc_html( wp_trim_words( wp_strip_all_tags( $content ), 30, '…' ) ); ?>
                </p>
                <span class="psc-notices-list__date">
                    <?php echo esc_html( $date_str ); ?>
                </span>
            </li>
            <?php endwhile; wp_reset_postdata(); ?>
        </ul>
        <?php endif; ?>

    </div>

    <style>
    .psc-notices-list-wrap{
        max-width:600px;
        margin:0 auto;
        padding:0 0 40px;
        background:#f5f5f5;
        min-height:100vh;
    }
    .psc-notices-list-header{
        display:flex;
        align-items:center;
        gap:8px;
        padding:16px 20px;
        background:#fff;
        border-bottom:1px solid #eee;
        position:sticky;
        top:0;
        z-index:10;
    }
    .psc-notices-back{
        background:none;
        border:none;
        font-size:1.6rem;
        cursor:pointer;
        color:#333;
        padding:0 8px 0 0;
        line-height:1;
    }
    .psc-notices-list-title{
        margin:0;
        font-size:1.1rem;
        font-weight:700;
        color:#111;
    }
    .psc-notices-list{
        list-style:none;
        margin:12px 16px 0;
        padding:0;
        display:flex;
        flex-direction:column;
        gap:10px;
    }
    .psc-notices-list__item{
        background:#fff;
        border-radius:14px;
        padding:16px;
        cursor:pointer;
        transition:box-shadow .15s;
    }
    .psc-notices-list__item:hover{
        box-shadow:0 2px 12px rgba(0,0,0,.08);
    }
    .psc-notices-list__top{
        display:flex;
        align-items:center;
        justify-content:space-between;
        margin-bottom:8px;
    }
    .psc-notices-list__store{
        font-size:.8rem;
        color:#888;
    }
    .psc-notices-list__badge{
        font-size:.72rem;
        font-weight:700;
        padding:2px 8px;
        border-radius:20px;
    }
    .psc-badge--pinned{
        background:#fff0f0;
        color:#e53e3e;
    }
    .psc-notices-list__title{
        margin:0 0 6px;
        font-size:.97rem;
        font-weight:700;
        color:#111;
        line-height:1.4;
    }
    .psc-notices-list__excerpt{
        margin:0 0 10px;
        font-size:.85rem;
        color:#555;
        line-height:1.6;
    }
    .psc-notices-list__date{
        font-size:.78rem;
        color:#aaa;
    }
    .psc-no-notices{
        text-align:center;
        color:#aaa;
        padding:48px 0;
        font-size:.9rem;
    }
    </style>
    <?php

    return ob_get_clean();
}

add_shortcode( 'psc_notices_link', function(): string {
    $store_id = psc_notice_resolve_store_id();
    if ( ! $store_id ) return '';
    $url = add_query_arg( 'store_id', $store_id, home_url( '/notices/' ) );
    return sprintf(
        '<a href="%s" class="psc-notices-link">전체보기</a>',
        esc_url( $url )
    );
});

