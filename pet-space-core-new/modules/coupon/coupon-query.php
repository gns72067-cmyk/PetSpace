<?php
/**
 * Module: Coupon Query
 * ══════════════════════════════════════════════════════════════
 * - REST API: 쿠폰 조회 / 발급 / 사용 처리
 * - 프론트 쿠폰 카드 UI (복제 방지 워터마크 + 카운트다운)
 * - shortcode: [psc_coupon_list] 매장 쿠폰 목록
 * ══════════════════════════════════════════════════════════════
 */

defined( 'ABSPATH' ) || exit;

/* ============================================================
   1. REST API
   ============================================================ */

add_action( 'rest_api_init', 'psc_coupon_register_rest' );

function psc_coupon_register_rest(): void {

    /* 매장 쿠폰 목록 */
    register_rest_route( 'psc/v1', '/coupons/store/(?P<store_id>\d+)', [
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'psc_rest_get_store_coupons',
        'permission_callback' => '__return_true',
        'args'                => [
            'store_id' => [
                'validate_callback' => fn($v) => is_numeric($v),
                'sanitize_callback' => 'absint',
            ],
        ],
    ]);

    /* 쿠폰 발급 */
    register_rest_route( 'psc/v1', '/coupons/(?P<id>\d+)/issue', [
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'psc_rest_issue_coupon',
        'permission_callback' => fn() => is_user_logged_in(),
        'args'                => [
            'id' => [
                'validate_callback' => fn($v) => is_numeric($v),
                'sanitize_callback' => 'absint',
            ],
        ],
    ]);

    /* 내 쿠폰 목록 */
    register_rest_route( 'psc/v1', '/coupons/my', [
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'psc_rest_get_my_coupons',
        'permission_callback' => fn() => is_user_logged_in(),
    ]);

    /* 토큰으로 쿠폰 정보 조회 (사용 처리용) */
    register_rest_route( 'psc/v1', '/coupons/verify/(?P<token>[a-f0-9]+)', [
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'psc_rest_verify_coupon',
        'permission_callback' => fn() => is_user_logged_in(),
        'args'                => [
            'token' => [
                'validate_callback' => fn($v) => (bool) preg_match('/^[a-f0-9]{48}$/', $v),
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ],
    ]);

    /* 쿠폰 사용 처리 */
    register_rest_route( 'psc/v1', '/coupons/use', [
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'psc_rest_use_coupon',
        'permission_callback' => fn() => is_user_logged_in(),
    ]);
}

/* ── 매장 쿠폰 목록 ── */
function psc_rest_get_store_coupons( WP_REST_Request $req ): WP_REST_Response {
    $store_id = (int) $req->get_param('store_id');
    $user_id  = get_current_user_id();

    $posts = get_posts([
        'post_type'      => PSC_COUPON_CPT,
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_query'     => [[
            'key'   => PSC_COUPON_STORE_META,
            'value' => $store_id,
        ]],
    ]);

    $result = [];
    foreach ( $posts as $p ) {
        if ( psc_coupon_is_expired( $p->ID ) ) continue;
        if ( psc_coupon_is_full( $p->ID )    ) continue;

        $use_limit    = (int) psc_coupon_get_meta( $p->ID, 'use_limit' );
        $issued_count = $user_id
            ? psc_coupon_user_issue_count( $p->ID, $user_id ) : 0;
        $used_count   = $user_id
            ? psc_coupon_user_used_count( $p->ID, $user_id )  : 0;

        $result[] = [
            'id'           => $p->ID,
            'title'        => html_entity_decode( get_the_title($p->ID), ENT_QUOTES, 'UTF-8' ),
            'desc'         => psc_coupon_get_meta( $p->ID, 'desc' ),
            'type'         => psc_coupon_get_meta( $p->ID, 'type' ),
            'value'        => (int) psc_coupon_get_meta( $p->ID, 'value' ),
            'value_label'  => psc_coupon_format_value( $p->ID ),
            'expire_date'  => psc_coupon_get_meta( $p->ID, 'expire_date' ),
            'use_limit'    => $use_limit,
            'issued_count' => $issued_count,
            'used_count'   => $used_count,
            'can_issue'    => $user_id
                ? ( $issued_count < $use_limit )
                : true,
        ];
    }

    return new WP_REST_Response( $result, 200 );
}

/* ── 쿠폰 발급 ── */
function psc_rest_issue_coupon( WP_REST_Request $req ): WP_REST_Response|WP_Error {
    $coupon_id = (int) $req->get_param('id');
    $user_id   = get_current_user_id();

    $token = psc_coupon_issue( $coupon_id, $user_id );
    if ( is_wp_error( $token ) ) {
        return new WP_REST_Response([
            'code'    => $token->get_error_code(),
            'message' => $token->get_error_message(),
        ], 400 );
    }

    $coupon_id_int = $coupon_id;
    return new WP_REST_Response([
        'token'       => $token,
        'title'       => html_entity_decode( get_the_title($coupon_id_int), ENT_QUOTES, 'UTF-8' ),
        'desc'        => psc_coupon_get_meta( $coupon_id_int, 'desc' ),
        'value_label' => psc_coupon_format_value( $coupon_id_int ),
        'expire_date' => psc_coupon_get_meta( $coupon_id_int, 'expire_date' ),
        'issued_at'   => current_time('mysql'),
        'user_name'   => wp_get_current_user()->display_name,
        'user_id'     => $user_id,
    ], 200 );
}

/* ── 내 쿠폰 목록 ── */
function psc_rest_get_my_coupons(): WP_REST_Response {
    global $wpdb;
    $user_id = get_current_user_id();
    $table   = $wpdb->prefix . PSC_COUPON_TABLE;

    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM {$table} WHERE user_id = %d ORDER BY issued_at DESC",
        $user_id
    ));

    $result = [];
    foreach ( $rows as $row ) {
        $cid  = (int) $row->coupon_id;
        $post = get_post( $cid );
        if ( ! $post || $post->post_status !== 'publish' ) continue;

        $result[] = [
            'token'       => $row->token,
            'coupon_id'   => $cid,
            'title'       => html_entity_decode( get_the_title($cid), ENT_QUOTES, 'UTF-8' ),
            'desc'        => psc_coupon_get_meta( $cid, 'desc' ),
            'value_label' => psc_coupon_format_value( $cid ),
            'expire_date' => psc_coupon_get_meta( $cid, 'expire_date' ),
            'issued_at'   => $row->issued_at,
            'is_used'     => (bool) $row->is_used,
            'used_at'     => $row->used_at,
            'is_expired'  => psc_coupon_is_expired( $cid ),
        ];
    }

    return new WP_REST_Response( $result, 200 );
}

/* ── 토큰 검증 ── */
function psc_rest_verify_coupon( WP_REST_Request $req ): WP_REST_Response {
    $token  = $req->get_param('token');
    $issued = psc_coupon_get_issued_by_token( $token );

    if ( ! $issued ) {
        return new WP_REST_Response([ 'valid' => false, 'message' => '존재하지 않는 쿠폰입니다.' ], 404 );
    }

    $cid  = (int) $issued->coupon_id;
    $post = get_post( $cid );

    return new WP_REST_Response([
        'valid'       => true,
        'is_used'     => (bool) $issued->is_used,
        'used_at'     => $issued->used_at,
        'is_expired'  => psc_coupon_is_expired( $cid ),
        'token'       => $token,
        'coupon_id'   => $cid,
        'title'       => $post ? html_entity_decode( get_the_title($cid), ENT_QUOTES, 'UTF-8' ) : '',
        'value_label' => psc_coupon_format_value( $cid ),
        'user_id'     => (int) $issued->user_id,
        'user_name'   => get_userdata( (int)$issued->user_id )->display_name ?? '',
        'issued_at'   => $issued->issued_at,
    ], 200 );
}

/* ── 쿠폰 사용 처리 ── */
function psc_rest_use_coupon( WP_REST_Request $req ): WP_REST_Response {
    $token    = sanitize_text_field( $req->get_param('token') ?? '' );
    $store_id = absint( $req->get_param('store_id') ?? 0 );

    if ( ! $token ) {
        return new WP_REST_Response([ 'success' => false, 'message' => '토큰이 없습니다.' ], 400 );
    }

    if ( $store_id && ! current_user_can('manage_options') ) {
        $my_stores = function_exists('psc_vendor_store_ids')
            ? psc_vendor_store_ids( get_current_user_id() ) : [];
        if ( ! in_array( $store_id, array_map('intval', $my_stores), true ) ) {
            return new WP_REST_Response([ 'success' => false, 'message' => '권한이 없습니다.' ], 403 );
        }
    }

    $result = psc_coupon_use( $token );
    if ( is_wp_error( $result ) ) {
        return new WP_REST_Response([
            'success' => false,
            'message' => $result->get_error_message(),
        ], 400 );
    }

    return new WP_REST_Response([ 'success' => true, 'message' => '쿠폰이 사용 처리되었습니다.' ], 200 );
}

/* ============================================================
   2. 프론트 에셋 (쿠폰 UI + 복제 방지)
   ============================================================ */

add_action( 'wp_enqueue_scripts', 'psc_coupon_enqueue_assets' );

function psc_coupon_enqueue_assets(): void {
    $store_cpt = defined('PSC_STORE_CPT') ? PSC_STORE_CPT : 'store';
    if ( ! is_singular( $store_cpt ) ) return;

    $store_id = get_the_ID();
    if ( ! $store_id ) return;

    if ( ! function_exists('psc_store_is_premium') || ! psc_store_is_premium( $store_id ) ) return;

    $user_id   = get_current_user_id();
    $login_url = wp_login_url( get_permalink( $store_id ) );

    wp_register_style( 'psc-coupon', false );
    wp_enqueue_style( 'psc-coupon' );

    /* ── CSS ── */
    $css = '
/* 쿠폰 목록 */
.psc-coupon-list { display:flex; flex-direction:column; gap:14px; margin:16px 0; }

/* 쿠폰 카드 */
.psc-coupon-card {
    position: relative;
    background: #fff;
    border-radius: 16px;
    padding: 20px;
    box-shadow: 0 2px 16px rgba(0,0,0,.08);
    overflow: hidden;
    cursor: pointer;
    transition: transform .15s, box-shadow .15s;
}
.psc-coupon-card:hover { transform:translateY(-2px); box-shadow:0 4px 24px rgba(0,0,0,.12); }
.psc-coupon-card__badge {
    display: inline-block;
    background: linear-gradient(135deg,#667eea,#764ba2);
    color: #fff; font-size:.78rem; font-weight:700;
    padding: 3px 10px; border-radius:20px; margin-bottom:8px;
}
.psc-coupon-card__value {
    font-size: 1.6rem; font-weight: 800; color: #111; margin-bottom:4px;
}
.psc-coupon-card__title { font-size:.95rem; font-weight:600; color:#333; margin-bottom:4px; }
.psc-coupon-card__desc  { font-size:.82rem; color:#888; margin-bottom:12px; }
.psc-coupon-card__footer {
    display: flex; align-items:center; justify-content:space-between;
    font-size:.78rem; color:#aaa; border-top:1px dashed #eee; padding-top:10px;
}
.psc-coupon-card__btn {
    background: #111; color:#fff; border:none;
    padding: 8px 18px; border-radius:8px;
    font-size:.85rem; font-weight:700; cursor:pointer;
    transition: opacity .15s;
}
.psc-coupon-card__btn:hover { opacity:.8; }
.psc-coupon-card__btn:disabled { background:#ccc; cursor:not-allowed; }
.psc-coupon-card--used .psc-coupon-card__value { color:#ccc; }
.psc-coupon-card--used .psc-coupon-card__btn   { display:none; }

/* 복제 방지 워터마크 */
.psc-coupon-watermark {
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    pointer-events: none;
    overflow: hidden;
    border-radius: 16px;
    opacity: 0.06;
    z-index: 1;
}
.psc-coupon-watermark-inner {
    position: absolute;
    white-space: nowrap;
    font-size: 11px;
    font-weight: 700;
    color: #000;
    letter-spacing: 2px;
    animation: pscWatermarkMove 12s linear infinite;
    top: -20px;
    left: -100%;
    width: 400%;
    line-height: 22px;
}
@keyframes pscWatermarkMove {
    0%   { transform: translateX(0)   translateY(0); }
    100% { transform: translateX(25%) translateY(100px); }
}

/* 발급된 쿠폰 모달 */
.psc-coupon-modal-overlay {
    display: none; position:fixed; inset:0;
    background: rgba(0,0,0,.6); z-index:99998;
    backdrop-filter: blur(4px);
}
.psc-coupon-modal-overlay.is-open { display:flex; align-items:center; justify-content:center; padding:20px; }
.psc-issued-coupon {
    position: relative;
    background: #fff;
    border-radius: 20px;
    width: 100%; max-width: 360px;
    padding: 28px 24px 24px;
    overflow: hidden;
    box-shadow: 0 8px 48px rgba(0,0,0,.25);
}
.psc-issued-coupon__store  { font-size:.78rem; color:#888; margin-bottom:4px; }
.psc-issued-coupon__value  { font-size:2.2rem; font-weight:800; color:#111; margin-bottom:6px; }
.psc-issued-coupon__title  { font-size:1rem; font-weight:600; color:#333; margin-bottom:4px; }
.psc-issued-coupon__desc   { font-size:.82rem; color:#888; margin-bottom:16px; }
.psc-issued-coupon__token-wrap {
    background: #f5f5f5; border-radius:10px;
    padding: 10px 14px; margin-bottom:14px;
    display: flex; align-items:center; justify-content:space-between; gap:8px;
}
.psc-issued-coupon__token {
    font-family: monospace; font-size:.78rem;
    color: #555; word-break:break-all;
    flex: 1;
}
.psc-issued-coupon__copy-btn {
    background: #111; color:#fff; border:none;
    padding: 4px 10px; border-radius:6px;
    font-size:.72rem; cursor:pointer; flex-shrink:0;
}
.psc-issued-coupon__qr {
    display: flex; justify-content:center; margin-bottom:14px;
}
.psc-issued-coupon__qr canvas { border-radius:8px; }
.psc-issued-coupon__countdown {
    text-align:center; font-size:.82rem; color:#ef4444;
    font-weight:600; margin-bottom:14px;
}
.psc-issued-coupon__info {
    font-size:.75rem; color:#aaa;
    border-top:1px dashed #eee; padding-top:12px;
    display:flex; justify-content:space-between;
}
.psc-issued-watermark {
    position: absolute;
    top:0; left:0; right:0; bottom:0;
    pointer-events: none;
    overflow: hidden;
    border-radius: 20px;
    z-index: 0;
    opacity: 0.045;
}
.psc-issued-watermark-inner {
    position: absolute;
    white-space: nowrap;
    font-size: 10px;
    font-weight: 800;
    color: #000;
    letter-spacing: 1px;
    top: -10px; left: -100%;
    width: 400%;
    line-height: 20px;
    animation: pscIssuedWatermark 8s linear infinite;
}
@keyframes pscIssuedWatermark {
    0%   { transform: translateX(0)   translateY(0)   rotate(-15deg); }
    100% { transform: translateX(20%) translateY(80px) rotate(-15deg); }
}
.psc-issued-coupon__close {
    position:absolute; top:14px; right:14px;
    background:none; border:none; font-size:1.4rem;
    color:#aaa; cursor:pointer; z-index:10;
    line-height:1; padding:0;
}
.psc-issued-coupon > * { position:relative; z-index:2; }
.psc-issued-coupon .psc-issued-watermark { z-index:1; }
.psc-coupon-loading {
    text-align:center; padding:20px; color:#888; font-size:.9rem;
}
';
    wp_add_inline_style( 'psc-coupon', $css );

    /* QRCode.js CDN */
    wp_enqueue_script(
        'qrcodejs',
        'https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js',
        [], '1.0.0', true
    );

    wp_register_script( 'psc-coupon', false, ['qrcodejs'], null, true );
    wp_enqueue_script( 'psc-coupon' );

    /* ── 변수를 wp_localize_script로 안전하게 주입 ── */
    wp_localize_script( 'psc-coupon', 'PSC_COUPON', [
        'rest_base' => rest_url('psc/v1/'),
        'nonce'     => wp_create_nonce('wp_rest'),
        'store_id'  => $store_id,
        'user_id'   => $user_id,
        'login_url' => $login_url,
    ]);

    /* ── JS ── */
    $js = '
(function(){
"use strict";

var REST_BASE  = PSC_COUPON.rest_base;
var NONCE      = PSC_COUPON.nonce;
var STORE_ID   = parseInt(PSC_COUPON.store_id, 10);
var USER_ID    = parseInt(PSC_COUPON.user_id, 10);
var LOGIN_URL  = PSC_COUPON.login_url;
var storeTitle = document.title;

/* 유틸 */
function apiFetch(path, opts){
    opts = opts || {};
    opts.headers = Object.assign({ "X-WP-Nonce": NONCE }, opts.headers || {});
    return fetch(REST_BASE + path, opts).then(function(r){ return r.json(); });
}

/* 워터마크 텍스트 생성 */
function buildWatermarkText(userName, token){
    var line = (userName || "USER") + " · " + (token ? token.substr(0,12) : "") + " · PETSPACE · ";
    var repeated = "";
    for(var i=0; i<8; i++) repeated += line;
    var rows = "";
    for(var j=0; j<20; j++) rows += repeated + "\n";
    return rows;
}

/* 쿠폰 목록 렌더 */
function renderCouponList(coupons, container){
    container.innerHTML = "";
    if(!coupons.length){
        container.innerHTML = "<p style=\"color:#aaa;text-align:center;padding:20px\">등록된 쿠폰이 없습니다.</p>";
        return;
    }
    coupons.forEach(function(c){
        var canIssue = USER_ID ? c.can_issue : true;
        var btnText  = !USER_ID ? "로그인 후 받기"
                     : !canIssue ? "발급 완료"
                     : "쿠폰 받기";

        var card = document.createElement("div");
        card.className = "psc-coupon-card" + (!canIssue ? " psc-coupon-card--used" : "");
        card.innerHTML =
            "<div class=\"psc-coupon-watermark\"><div class=\"psc-coupon-watermark-inner\">"
            + buildWatermarkText("", "") + "</div></div>"
            + "<div class=\"psc-coupon-card__badge\">🎟️ 쿠폰</div>"
            + "<div class=\"psc-coupon-card__value\">" + escHtml(c.value_label) + "</div>"
            + "<div class=\"psc-coupon-card__title\">" + escHtml(c.title) + "</div>"
            + (c.desc ? "<div class=\"psc-coupon-card__desc\">" + escHtml(c.desc) + "</div>" : "")
            + "<div class=\"psc-coupon-card__footer\">"
            +   "<span>" + (c.expire_date ? "~" + c.expire_date : "상시 사용") + "</span>"
            +   "<button class=\"psc-coupon-card__btn\" data-id=\"" + c.id + "\""
            +       ((!USER_ID || canIssue) ? "" : " disabled") + ">"
            +       btnText + "</button>"
            + "</div>";

        var btn = card.querySelector(".psc-coupon-card__btn");
        btn.addEventListener("click", function(e){
            e.stopPropagation();
            if(!USER_ID){ window.location.href = LOGIN_URL; return; }
            issueCoupon(c.id, btn);
        });

        container.appendChild(card);
    });
}

/* 쿠폰 발급 */
function issueCoupon(couponId, btn){
    btn.disabled = true;
    btn.textContent = "발급 중...";

    apiFetch("coupons/" + couponId + "/issue", { method:"POST" })
    .then(function(data){
        if(data.code){
            alert(data.message);
            btn.disabled = false;
            btn.textContent = "쿠폰 받기";
            return;
        }
        btn.textContent = "발급 완료";
        openIssuedModal(data);
    })
    .catch(function(){
        alert("오류가 발생했습니다. 다시 시도해주세요.");
        btn.disabled = false;
        btn.textContent = "쿠폰 받기";
    });
}

/* 발급된 쿠폰 모달 */
var modalOverlay   = null;
var qrInstance     = null;
var countdownTimer = null;

function buildModal(){
    modalOverlay = document.createElement("div");
    modalOverlay.className = "psc-coupon-modal-overlay";
    modalOverlay.innerHTML =
        "<div class=\"psc-issued-coupon\" id=\"psc-issued-coupon-inner\">"
        + "<div class=\"psc-issued-watermark\"><div class=\"psc-issued-watermark-inner\" id=\"psc-issued-wm\"></div></div>"
        + "<button class=\"psc-issued-coupon__close\" id=\"psc-coupon-close\">×</button>"
        + "<div class=\"psc-issued-coupon__store\" id=\"psc-ic-store\"></div>"
        + "<div class=\"psc-issued-coupon__value\" id=\"psc-ic-value\"></div>"
        + "<div class=\"psc-issued-coupon__title\" id=\"psc-ic-title\"></div>"
        + "<div class=\"psc-issued-coupon__desc\"  id=\"psc-ic-desc\"></div>"
        + "<div class=\"psc-issued-coupon__qr\"    id=\"psc-ic-qr\"></div>"
        + "<div class=\"psc-issued-coupon__token-wrap\">"
        +   "<span class=\"psc-issued-coupon__token\" id=\"psc-ic-token\"></span>"
        +   "<button class=\"psc-issued-coupon__copy-btn\" id=\"psc-ic-copy\">복사</button>"
        + "</div>"
        + "<div class=\"psc-issued-coupon__countdown\" id=\"psc-ic-countdown\"></div>"
        + "<div class=\"psc-issued-coupon__info\">"
        +   "<span id=\"psc-ic-user\"></span>"
        +   "<span id=\"psc-ic-expire\"></span>"
        + "</div>"
        + "</div>";

    document.body.appendChild(modalOverlay);

    document.getElementById("psc-coupon-close").addEventListener("click", closeModal);
    modalOverlay.addEventListener("click", function(e){
        if(e.target === modalOverlay) closeModal();
    });
    document.addEventListener("keydown", function(e){
        if(e.key === "Escape") closeModal();
    });

    document.getElementById("psc-ic-copy").addEventListener("click", function(){
        var token = document.getElementById("psc-ic-token").textContent;
        navigator.clipboard.writeText(token).then(function(){
            var btn = document.getElementById("psc-ic-copy");
            btn.textContent = "✓ 복사됨";
            setTimeout(function(){ btn.textContent = "복사"; }, 2000);
        });
    });
}

function openIssuedModal(data){
    if(!modalOverlay) buildModal();

    document.getElementById("psc-issued-wm").textContent =
        buildWatermarkText(data.user_name, data.token);

    document.getElementById("psc-ic-store").textContent  = storeTitle;
    document.getElementById("psc-ic-value").textContent  = data.value_label;
    document.getElementById("psc-ic-title").textContent  = data.title;
    document.getElementById("psc-ic-desc").textContent   = data.desc || "";
    document.getElementById("psc-ic-token").textContent  = data.token;
    document.getElementById("psc-ic-user").textContent   = "👤 " + (data.user_name || "");
    document.getElementById("psc-ic-expire").textContent =
        data.expire_date ? "만료: " + data.expire_date : "상시 사용";

    var qrWrap = document.getElementById("psc-ic-qr");
    qrWrap.innerHTML = "";
    if(typeof QRCode !== "undefined"){
        new QRCode(qrWrap, {
            text:       data.token,
            width:      140,
            height:     140,
            colorDark:  "#111111",
            colorLight: "#ffffff",
        });
    }

    if(countdownTimer) clearInterval(countdownTimer);
    var issuedAt = new Date(data.issued_at.replace(" ","T"));
    var expireAt = new Date(issuedAt.getTime() + 24 * 60 * 60 * 1000);

    function updateCountdown(){
        var now  = new Date();
        var diff = expireAt - now;
        if(diff <= 0){
            document.getElementById("psc-ic-countdown").textContent = "⚠️ 쿠폰이 만료되었습니다.";
            clearInterval(countdownTimer);
            return;
        }
        var h = Math.floor(diff / 3600000);
        var m = Math.floor((diff % 3600000) / 60000);
        var s = Math.floor((diff % 60000)   / 1000);
        document.getElementById("psc-ic-countdown").textContent =
            "⏱ " + pad(h) + ":" + pad(m) + ":" + pad(s) + " 후 만료";
    }
    updateCountdown();
    countdownTimer = setInterval(updateCountdown, 1000);

    modalOverlay.classList.add("is-open");
    document.body.style.overflow = "hidden";
}

function closeModal(){
    if(!modalOverlay) return;
    modalOverlay.classList.remove("is-open");
    document.body.style.overflow = "";
    if(countdownTimer){ clearInterval(countdownTimer); countdownTimer = null; }
}

/* 초기 로드 */
function initCouponList(){
    var containers = document.querySelectorAll("[data-psc-coupons]");
    if(!containers.length) return;

    containers.forEach(function(container){
        container.innerHTML = "<div class=\"psc-coupon-loading\">쿠폰 불러오는 중...</div>";
        apiFetch("coupons/store/" + STORE_ID)
        .then(function(data){
            renderCouponList(Array.isArray(data) ? data : [], container);
        })
        .catch(function(){
            container.innerHTML = "<p style=\"color:#aaa;text-align:center\">쿠폰을 불러오지 못했습니다.</p>";
        });
    });
}

/* 유틸 */
function pad(n){ return String(n).padStart(2,"0"); }
function escHtml(s){
    return String(s)
        .replace(/&/g,"&amp;").replace(/</g,"&lt;")
        .replace(/>/g,"&gt;").replace(/"/g,"&quot;");
}

document.addEventListener("DOMContentLoaded", initCouponList);

})();
';
    wp_add_inline_script( 'psc-coupon', $js );
}

/* ============================================================
   3. 숏코드 [psc_coupon_list]
   ============================================================ */

add_shortcode( 'psc_coupon_list', 'psc_shortcode_coupon_list' );

function psc_shortcode_coupon_list( array $atts ): string {
    $atts = shortcode_atts([ 'store_id' => 0 ], $atts );
    $store_id = $atts['store_id']
        ? (int) $atts['store_id']
        : ( function_exists('psc_current_store_id') ? psc_current_store_id() : get_the_ID() );

    if ( ! $store_id ) return '';

    if ( ! function_exists('psc_store_is_premium') || ! psc_store_is_premium( $store_id ) ) return '';

    return '<div class="psc-coupon-list" data-psc-coupons="1"></div>';
}
