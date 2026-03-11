<?php
/**
 * Module: Vendor Dashboard
 * ══════════════════════════════════════════════════════════════
 * - 라우팅: /vendor/* 페이지 처리
 * - 공통 레이아웃 (헤더/네비/푸터)
 * - 대시보드 홈 (매장 요약, 최근 공지, 플랜 배너)
 * ══════════════════════════════════════════════════════════════
 */

defined( 'ABSPATH' ) || exit;

/* ============================================================
   0. 상수
   ============================================================ */

define( 'PSC_DASH_BASE',    'vendor' );
define( 'PSC_DASH_STORE',   'vendor/store' );
define( 'PSC_DASH_NOTICES', 'vendor/notices' );
define( 'PSC_DASH_COUPONS', 'vendor/coupons' );

/* ============================================================
   1. Rewrite Rules
   ============================================================ */

add_action( 'init', 'psc_dash_register_rewrites' );

function psc_dash_register_rewrites(): void {
    add_rewrite_rule( '^vendor/?$',                 'index.php?psc_dash=home',          'top' );
    add_rewrite_rule( '^vendor/store/?$',           'index.php?psc_dash=store',         'top' );
    add_rewrite_rule( '^vendor/notices/?$',         'index.php?psc_dash=notices',       'top' );
    add_rewrite_rule( '^vendor/notices/new/?$',     'index.php?psc_dash=notices_new',   'top' );
    add_rewrite_rule( '^vendor/notices/edit/?$',    'index.php?psc_dash=notices_edit',  'top' );
    add_rewrite_rule( '^vendor/coupons/?$',         'index.php?psc_dash=coupons',       'top' );
    add_rewrite_rule( '^vendor/coupons/new/?$',     'index.php?psc_dash=coupons_new',   'top' );
    add_rewrite_rule( '^vendor/coupons/edit/?$',    'index.php?psc_dash=coupons_edit',  'top' );
    add_rewrite_rule( '^vendor/coupons/use/?$',     'index.php?psc_dash=coupons_use',   'top' );
    add_rewrite_rule( '^vendor/coupons/stats/?$',   'index.php?psc_dash=coupons_stats', 'top' );
}

add_filter( 'query_vars', 'psc_dash_query_vars' );

function psc_dash_query_vars( array $vars ): array {
    $vars[] = 'psc_dash';
    return $vars;
}

/* 플러그인 활성화 시 퍼머링크 자동 갱신 */
register_activation_hook(
    WP_PLUGIN_DIR . '/pet-space-core/pet-space-core.php',
    function() {
        psc_dash_register_rewrites();
        flush_rewrite_rules();
    }
);

/* ============================================================
   2. 대시보드 라우터
   ============================================================ */

add_action( 'template_redirect', 'psc_dash_router' );

function psc_dash_router(): void {

    $page = get_query_var( 'psc_dash' );
    if ( ! $page ) return;

    if ( ! is_user_logged_in() ) {
        wp_redirect( wp_login_url( home_url( '/vendor/' ) ) );
        exit;
    }

    $user_id  = get_current_user_id();
    $is_admin = current_user_can( 'manage_options' );

    if ( ! psc_is_vendor( $user_id ) && ! $is_admin ) {
        wp_die( '접근 권한이 없습니다.', '권한 없음', [ 'response' => 403 ] );
    }

    $store_ids = psc_vendor_store_ids( $user_id );

    /* 매장이 없는 경우 */
    if ( empty( $store_ids ) ) {
        psc_dash_render( $page, 0, $user_id );
        exit;
    }

    /* 매장이 1개면 바로 사용 */
    if ( count( $store_ids ) === 1 ) {
        psc_dash_render( $page, (int) $store_ids[0], $user_id );
        exit;
    }

    /* ── 매장 전환 버튼 클릭 시 쿠키 초기화 ── */
    if ( isset( $_GET['clear_store'] ) ) {
        setcookie(
            'psc_vendor_store_' . $user_id,
            '',
            time() - HOUR_IN_SECONDS,
            COOKIEPATH,
            COOKIE_DOMAIN
        );
        wp_safe_redirect( home_url( '/vendor/' ) );
        exit;
    }

    /* 매장이 여러 개 → GET 파라미터 or 쿠키로 선택 */
    $selected_store_id = 0;

    if ( isset( $_GET['store_id'] ) ) {
        $sid = absint( $_GET['store_id'] );
        if ( in_array( $sid, array_map( 'intval', $store_ids ), true ) ) {
            $selected_store_id = $sid;
            setcookie(
                'psc_vendor_store_' . $user_id,
                $sid,
                time() + 30 * DAY_IN_SECONDS,
                COOKIEPATH,
                COOKIE_DOMAIN
            );
        }
    } elseif ( isset( $_COOKIE[ 'psc_vendor_store_' . $user_id ] ) ) {
        $sid = absint( $_COOKIE[ 'psc_vendor_store_' . $user_id ] );
        if ( in_array( $sid, array_map( 'intval', $store_ids ), true ) ) {
            $selected_store_id = $sid;
        }
    }

    /* 선택된 매장이 없으면 선택 화면 */
    if ( ! $selected_store_id ) {
        psc_dash_render_store_select( $store_ids, $page, $user_id );
        exit;
    }

    psc_dash_render( $page, $selected_store_id, $user_id );
    exit;
}

/* ============================================================
   3. 공통 레이아웃
   ============================================================ */

function psc_dash_render( string $page, int $store_id, int $user_id ): void {

    $nav_items = [
        'home'    => [ 'label' => '🏠 홈',      'url' => home_url( '/vendor/' ) ],
        'store'   => [ 'label' => '🏪 매장관리', 'url' => home_url( '/vendor/store/' ) ],
        'notices' => [ 'label' => '📢 공지관리', 'url' => home_url( '/vendor/notices/' ) ],
        'coupons' => [ 'label' => '🎟️ 쿠폰관리', 'url' => home_url( '/vendor/coupons/' ) ],
    ];

    /* 현재 활성 네비 탭 결정 */
    $current_base = match(true) {
    in_array( $page, [ 'notices', 'notices_new', 'notices_edit' ], true )
        => 'notices',
    in_array( $page, [ 'coupons', 'coupons_new', 'coupons_edit', 'coupons_use', 'coupons_stats' ], true )
        => 'coupons',
    default => $page,
    };

    $all_stores = psc_vendor_store_ids( $user_id );

    ?><!DOCTYPE html>
    <html <?php language_attributes(); ?>>
    <head>
        <meta charset="<?php bloginfo( 'charset' ); ?>">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>입점사 대시보드 — <?php bloginfo( 'name' ); ?></title>
        <?php wp_head(); ?>
        <style><?php echo psc_dash_global_css(); ?></style>
    </head>
    <body class="psc-dash-body">

    <header class="psc-dash-header">
        <div class="psc-dash-header__inner">

            <div class="psc-dash-header__left">
                <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="psc-dash-logo">
                    <?php bloginfo( 'name' ); ?>
                </a>
                <?php if ( count( $all_stores ) > 1 ) : ?>
                    <a href="<?php echo esc_url( add_query_arg( 'clear_store', '1', home_url( '/vendor/' ) ) ); ?>"
                       class="psc-dash-store-switch">
                        🔄 매장 전환
                    </a>
                <?php endif; ?>
            </div>

            <div class="psc-dash-header__right">
                <span class="psc-dash-user">
                    <?php echo esc_html( wp_get_current_user()->display_name ); ?>
                </span>
                <a href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>"
                   class="psc-dash-logout">로그아웃</a>
            </div>

        </div>
    </header>

    <nav class="psc-dash-nav">
        <div class="psc-dash-nav__inner">
            <?php foreach ( $nav_items as $key => $item ) :
                $active = $current_base === $key ? ' is-active' : '';
            ?>
                <a href="<?php echo esc_url( $item['url'] ); ?>"
                   class="psc-dash-nav__item<?php echo esc_attr( $active ); ?>">
                    <?php echo esc_html( $item['label'] ); ?>
                </a>
            <?php endforeach; ?>
        </div>
    </nav>

    <main class="psc-dash-main">
        <?php
        switch ( $page ) {
            case 'home':          psc_dash_page_home( $store_id, $user_id );          break;
            case 'store':         psc_dash_page_store( $store_id, $user_id );         break;
            case 'notices':       psc_dash_page_notices( $store_id, $user_id );       break;
            case 'notices_new':   psc_dash_page_notices_new( $store_id, $user_id );   break;
            case 'notices_edit':  psc_dash_page_notices_edit( $store_id, $user_id );  break;
            case 'coupons':       psc_dash_page_coupons( $store_id, $user_id );       break;
            case 'coupons_new':   psc_dash_page_coupon_new( $store_id, $user_id );    break;
            case 'coupons_edit':  psc_dash_page_coupon_edit( $store_id, $user_id );   break;
            case 'coupons_use':   psc_dash_page_coupon_use( $store_id, $user_id );    break;
            case 'coupons_stats': psc_dash_page_coupon_stats( $store_id, $user_id ); break;
            default: echo '<p>페이지를 찾을 수 없습니다.</p>';
        }
        ?>
    </main>

    <nav class="psc-dash-bottom-nav">
        <?php foreach ( $nav_items as $key => $item ) :
            $active = $current_base === $key ? ' is-active' : '';
        ?>
            <a href="<?php echo esc_url( $item['url'] ); ?>"
               class="psc-dash-bottom-nav__item<?php echo esc_attr( $active ); ?>">
                <?php echo esc_html( $item['label'] ); ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <?php wp_footer(); ?>
    </body>
    </html><?php
}

/* ============================================================
   4. 홈 페이지
   ============================================================ */

function psc_dash_page_home( int $store_id, int $user_id ): void {

    $store      = $store_id ? get_post( $store_id ) : null;
    $is_premium = $store_id ? psc_store_is_premium( $store_id ) : false;
    $plan_label = $is_premium ? '★ 프리미엄' : '무료';
    $plan_class = $is_premium ? 'premium' : 'free';

    $notices = $store_id ? get_posts( [
        'post_type'      => PSC_NOTICE_CPT,
        'post_status'    => 'publish',
        'posts_per_page' => 3,
        'meta_key'       => PSC_NOTICE_STORE_META,
        'meta_value'     => $store_id,
        'orderby'        => 'date',
        'order'          => 'DESC',
    ] ) : [];

    ?>
    <div class="psc-dash-section">

    <?php if ( ! $store ) : ?>
        <div class="psc-dash-alert psc-dash-alert--warning">
            등록된 매장이 없습니다. 관리자에게 문의하세요.
        </div>
    <?php else : ?>

        <!-- 매장 요약 카드 -->
        <div class="psc-dash-store-card">
            <?php $thumb = get_the_post_thumbnail_url( $store_id, 'medium' ); ?>
            <?php if ( $thumb ) : ?>
                <img src="<?php echo esc_url( $thumb ); ?>"
                     alt="매장 이미지"
                     class="psc-dash-store-card__thumb">
            <?php endif; ?>
            <div class="psc-dash-store-card__info">
                <div class="psc-dash-store-card__top">
                    <h2 class="psc-dash-store-card__name">
                        <?php echo esc_html( get_the_title( $store_id ) ); ?>
                    </h2>
                    <span class="psc-dash-plan-badge psc-dash-plan-badge--<?php echo esc_attr( $plan_class ); ?>">
                        <?php echo esc_html( $plan_label ); ?>
                    </span>
                </div>
                <?php $address = get_post_meta( $store_id, 'store_address', true ); ?>
                <?php if ( $address ) : ?>
                    <p class="psc-dash-store-card__meta">📍 <?php echo esc_html( $address ); ?></p>
                <?php endif; ?>
                <?php $phone = get_post_meta( $store_id, 'store_phone', true ); ?>
                <?php if ( $phone ) : ?>
                    <p class="psc-dash-store-card__meta">📞 <?php echo esc_html( $phone ); ?></p>
                <?php endif; ?>
                <a href="<?php echo esc_url( home_url( '/vendor/store/' ) ); ?>"
                   class="psc-dash-btn psc-dash-btn--outline psc-dash-btn--sm"
                   style="margin-top:12px;display:inline-block">
                    매장 정보 수정
                </a>
            </div>
        </div>

        <?php if ( ! $is_premium ) : ?>
        <!-- 프리미엄 유도 배너 -->
        <div class="psc-dash-upgrade-banner">
            <div class="psc-dash-upgrade-banner__text">
                <strong>프리미엄으로 업그레이드</strong>
                공지 무제한 등록, 쿠폰 발행, 상단 고정 등 더 많은 기능을 사용할 수 있습니다.
            </div>
            <a href="<?php echo esc_url( home_url( '/upgrade/' ) ); ?>"
               class="psc-dash-btn psc-dash-btn--premium">업그레이드 →</a>
        </div>
        <?php endif; ?>

        <!-- 빠른 메뉴 -->
        <div class="psc-dash-quick-menu">
            <a href="<?php echo esc_url( home_url( '/vendor/store/' ) ); ?>"
               class="psc-dash-quick-item">
                <span class="psc-dash-quick-item__icon">🏪</span>
                <span>매장 수정</span>
            </a>
            <a href="<?php echo esc_url( home_url( '/vendor/notices/new/' ) ); ?>"
               class="psc-dash-quick-item">
                <span class="psc-dash-quick-item__icon">✏️</span>
                <span>공지 작성</span>
            </a>
            <a href="<?php echo esc_url( home_url( '/vendor/coupons/' ) ); ?>"
               class="psc-dash-quick-item">
                <span class="psc-dash-quick-item__icon">🎟️</span>
                <span>쿠폰 관리</span>
            </a>
            <a href="<?php echo esc_url( get_permalink( $store_id ) ?: home_url( '/' ) ); ?>"
               class="psc-dash-quick-item" target="_blank">
                <span class="psc-dash-quick-item__icon">👁️</span>
                <span>매장 보기</span>
            </a>
        </div>

        <!-- 최근 공지 -->
        <div class="psc-dash-card">
            <div class="psc-dash-card__header">
                <h3 class="psc-dash-card__title">최근 공지</h3>
                <a href="<?php echo esc_url( home_url( '/vendor/notices/' ) ); ?>"
                   class="psc-dash-card__more">전체보기 ›</a>
            </div>
            <?php if ( empty( $notices ) ) : ?>
                <p class="psc-dash-empty">등록된 공지가 없습니다.</p>
            <?php else : ?>
                <ul class="psc-dash-notice-list">
                <?php foreach ( $notices as $n ) :
                    $pinned = get_post_meta( $n->ID, 'is_pinned', true );
                ?>
                    <li class="psc-dash-notice-list__item">
                        <div class="psc-dash-notice-list__left">
                            <?php if ( $pinned ) : ?>
                                <span class="psc-pin-badge">📌</span>
                            <?php endif; ?>
                            <span class="psc-dash-notice-list__title">
                                <?php echo esc_html( $n->post_title ); ?>
                            </span>
                        </div>
                        <span class="psc-dash-notice-list__date">
                            <?php echo esc_html( get_the_date( 'Y.m.d', $n->ID ) ); ?>
                        </span>
                    </li>
                <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

    <?php endif; ?>
    </div>
    <?php
}

/* ============================================================
   5. 전역 CSS
   ============================================================ */

function psc_dash_global_css(): string {
    return '
*{box-sizing:border-box;margin:0;padding:0;}
body.psc-dash-body{
    font-family:-apple-system,BlinkMacSystemFont,"Pretendard","Apple SD Gothic Neo",sans-serif;
    background:#f5f5f5;color:#111;min-height:100vh;padding-bottom:70px;
}

/* 헤더 */
.psc-dash-header{background:#fff;border-bottom:1px solid #eee;position:sticky;top:0;z-index:100;}
.psc-dash-header__inner{
    max-width:720px;margin:0 auto;padding:14px 20px;
    display:flex;align-items:center;justify-content:space-between;gap:12px;
}
.psc-dash-header__left{display:flex;align-items:center;gap:12px;}
.psc-dash-header__right{display:flex;align-items:center;gap:12px;}
.psc-dash-logo{font-size:1.1rem;font-weight:800;color:#111;text-decoration:none;}
.psc-dash-user{font-size:.85rem;color:#555;}
.psc-dash-logout{font-size:.82rem;color:#888;text-decoration:none;}
.psc-dash-logout:hover{color:#c00;}
.psc-dash-store-switch{font-size:.82rem;color:#667eea;text-decoration:none;font-weight:600;}
.psc-dash-store-switch:hover{text-decoration:underline;}

/* 상단 네비 (PC) */
.psc-dash-nav{background:#fff;border-bottom:1px solid #eee;display:none;}
@media(min-width:769px){.psc-dash-nav{display:block;}}
.psc-dash-nav__inner{max-width:720px;margin:0 auto;padding:0 20px;display:flex;gap:4px;}
.psc-dash-nav__item{
    padding:12px 16px;font-size:.9rem;color:#555;text-decoration:none;
    border-bottom:2px solid transparent;transition:color .15s,border-color .15s;
}
.psc-dash-nav__item.is-active,.psc-dash-nav__item:hover{color:#111;border-bottom-color:#111;}

/* 하단 네비 (모바일) */
.psc-dash-bottom-nav{
    position:fixed;left:0;right:0;bottom:0;
    background:#fff;border-top:1px solid #eee;display:flex;z-index:100;
}
@media(min-width:769px){
    .psc-dash-bottom-nav{display:none;}
    body.psc-dash-body{padding-bottom:0;}
}
.psc-dash-bottom-nav__item{
    flex:1;text-align:center;padding:10px 0 8px;
    font-size:.72rem;color:#888;text-decoration:none;transition:color .15s;
}
.psc-dash-bottom-nav__item.is-active{color:#111;font-weight:700;}

/* 메인 */
.psc-dash-main{max-width:720px;margin:0 auto;padding:20px 16px;}
.psc-dash-section{display:flex;flex-direction:column;gap:16px;}

/* 카드 */
.psc-dash-card{background:#fff;border-radius:14px;padding:20px;}
.psc-dash-card__header{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;}
.psc-dash-card__title{font-size:1rem;font-weight:700;}
.psc-dash-card__more{font-size:.82rem;color:#888;text-decoration:none;}
.psc-dash-card__more:hover{color:#111;}

/* 매장 요약 카드 */
.psc-dash-store-card{background:#fff;border-radius:14px;overflow:hidden;}
.psc-dash-store-card__thumb{width:100%;height:160px;object-fit:cover;}
.psc-dash-store-card__info{padding:16px 20px 20px;}
.psc-dash-store-card__top{display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;}
.psc-dash-store-card__name{font-size:1.1rem;font-weight:700;}
.psc-dash-store-card__meta{font-size:.85rem;color:#555;margin-top:4px;}

/* 플랜 배지 */
.psc-dash-plan-badge{font-size:.72rem;font-weight:700;padding:3px 10px;border-radius:20px;}
.psc-dash-plan-badge--free{background:#f0f0f0;color:#888;}
.psc-dash-plan-badge--premium{background:#fff3cd;color:#856404;}

/* 업그레이드 배너 */
.psc-dash-upgrade-banner{
    background:linear-gradient(135deg,#667eea,#764ba2);
    border-radius:14px;padding:18px 20px;
    display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;
}
.psc-dash-upgrade-banner__text{font-size:.88rem;color:#fff;line-height:1.5;flex:1;}
.psc-dash-upgrade-banner__text strong{display:block;margin-bottom:4px;font-size:.95rem;}

/* 빠른 메뉴 — 4개로 변경 */
.psc-dash-quick-menu{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;}
@media(max-width:480px){.psc-dash-quick-menu{grid-template-columns:repeat(2,1fr);}}
.psc-dash-quick-item{
    background:#fff;border-radius:12px;padding:16px 8px;
    text-align:center;text-decoration:none;color:#333;font-size:.78rem;
    display:flex;flex-direction:column;align-items:center;gap:8px;
    transition:box-shadow .15s;
}
.psc-dash-quick-item:hover{box-shadow:0 2px 12px rgba(0,0,0,.08);}
.psc-dash-quick-item__icon{font-size:1.6rem;}

/* 공지 리스트 */
.psc-dash-notice-list{list-style:none;display:flex;flex-direction:column;}
.psc-dash-notice-list__item{
    display:flex;align-items:center;justify-content:space-between;
    padding:10px 0;border-bottom:1px solid #f5f5f5;gap:8px;
}
.psc-dash-notice-list__item:last-child{border-bottom:none;}
.psc-dash-notice-list__left{display:flex;align-items:center;gap:6px;flex:1;min-width:0;}
.psc-dash-notice-list__title{font-size:.9rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.psc-dash-notice-list__date{font-size:.78rem;color:#aaa;flex-shrink:0;}

/* 버튼 */
.psc-dash-btn{
    display:inline-flex;align-items:center;justify-content:center;
    padding:10px 20px;border-radius:8px;font-size:.9rem;font-weight:600;
    cursor:pointer;text-decoration:none;border:none;transition:background .15s, opacity .15s;font-family:inherit;
}
.psc-dash-btn:hover{opacity:.85;}
.psc-dash-btn--primary{background:#224471;color:#fff;}
.psc-dash-btn--primary:hover{background:#1a3459;opacity:1;}
.psc-dash-btn--outline{background:#fff;color:#111;border:1px solid #ddd;}
.psc-dash-btn--danger{background:#fee2e2;color:#c00;border:1px solid #fca5a5;}
.psc-dash-btn--danger:hover{background:#fecaca;}
.psc-dash-btn--premium{background:#fff;color:#764ba2;font-weight:700;padding:8px 16px;font-size:.85rem;border-radius:20px;}
.psc-dash-btn--sm{padding:7px 14px;font-size:.82rem;}
.psc-dash-btn--xs{padding:5px 10px;font-size:.78rem;}
.psc-dash-btn--full{width:100%;}

/* 폼 */
.psc-dash-form{display:flex;flex-direction:column;gap:16px;}
.psc-dash-form-group{display:flex;flex-direction:column;gap:6px;}
.psc-dash-form-group label{font-size:.88rem;font-weight:600;color:#333;}
.psc-dash-form-group input,
.psc-dash-form-group textarea,
.psc-dash-form-group select{
    width:100%;padding:10px 14px;border:1px solid #ddd;border-radius:8px;
    font-size:.92rem;color:#111;background:#fff;outline:none;
    transition:border-color .15s;font-family:inherit;
}
.psc-dash-form-group input:focus,
.psc-dash-form-group textarea:focus,
.psc-dash-form-group select:focus{border-color:#224471;}
.psc-dash-form-group textarea{resize:vertical;min-height:100px;}
.psc-dash-form-hint{font-size:.78rem;color:#aaa;margin-top:2px;}

/* 알림 */
.psc-dash-alert{padding:14px 16px;border-radius:10px;font-size:.88rem;line-height:1.5;}
.psc-dash-alert--success{background:#d1fae5;color:#065f46;}
.psc-dash-alert--error{background:#fee2e2;color:#991b1b;}
.psc-dash-alert--warning{background:#fef9c3;color:#854d0e;}
.psc-dash-alert--info{background:#eff6ff;color:#1e40af;}

/* 페이지 타이틀 */
.psc-dash-page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px;}
.psc-dash-page-title{font-size:1.2rem;font-weight:800;margin:0;}
.psc-dash-back{font-size:1.6rem;color:#333;text-decoration:none;line-height:1;}
.psc-back-link{font-size:.85rem;color:#6b7280;text-decoration:none;margin-right:12px;}
.psc-back-link:hover{color:#111;}

/* 빈 상태 */
.psc-dash-empty{text-align:center;color:#aaa;padding:32px 0;font-size:.9rem;}

/* 썸네일 */
.psc-dash-thumb-wrap{display:flex;align-items:center;gap:16px;flex-wrap:wrap;}
.psc-dash-thumb-preview{width:100px;height:100px;object-fit:cover;border-radius:10px;}
.psc-dash-thumb-empty{
    width:100px;height:100px;background:#f0f0f0;border-radius:10px;
    display:flex;align-items:center;justify-content:center;font-size:.78rem;color:#aaa;
}
.psc-dash-thumb-actions{display:flex;flex-direction:column;gap:8px;}

/* 배지 */
.psc-dash-badge{font-size:.7rem;font-weight:600;padding:2px 7px;border-radius:20px;}
.psc-dash-badge--draft{background:#f0f0f0;color:#888;}
.psc-dash-badge--pin{background:#fff3cd;color:#856404;}
.psc-dash-badge--expired{background:#fee2e2;color:#c00;}
';
}

/* ============================================================
   6. 매장 선택 화면
   ============================================================ */

function psc_dash_render_store_select( array $store_ids, string $page, int $user_id ): void {
    ?><!DOCTYPE html>
    <html <?php language_attributes(); ?>>
    <head>
        <meta charset="<?php bloginfo('charset'); ?>">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>매장 선택 — <?php bloginfo('name'); ?></title>
        <?php wp_head(); ?>
        <style><?php echo psc_dash_global_css(); ?></style>
    </head>
    <body class="psc-dash-body">
    <header class="psc-dash-header">
        <div class="psc-dash-header__inner">
            <div class="psc-dash-header__left">
                <a href="<?php echo esc_url( home_url('/') ); ?>" class="psc-dash-logo">
                    <?php bloginfo('name'); ?>
                </a>
            </div>
            <div class="psc-dash-header__right">
                <span class="psc-dash-user">
                    <?php echo esc_html( wp_get_current_user()->display_name ); ?>
                </span>
                <a href="<?php echo esc_url( wp_logout_url( home_url('/') ) ); ?>"
                   class="psc-dash-logout">로그아웃</a>
            </div>
        </div>
    </header>
    <main class="psc-dash-main">
        <div class="psc-dash-section">
            <div class="psc-dash-page-header">
                <h1 class="psc-dash-page-title">관리할 매장을 선택하세요</h1>
            </div>
            <div style="display:flex;flex-direction:column;gap:12px;">
            <?php foreach ( $store_ids as $sid ) :
                $sid        = (int) $sid;
                $name       = get_the_title( $sid );
                $address    = get_post_meta( $sid, 'store_address', true );
                $is_premium = psc_store_is_premium( $sid );
                $thumb      = get_the_post_thumbnail_url( $sid, 'thumbnail' );
                $select_url = add_query_arg( 'store_id', $sid, home_url( '/vendor/' ) );
            ?>
                <a href="<?php echo esc_url( $select_url ); ?>" style="text-decoration:none">
                    <div class="psc-dash-store-select-card">
                        <?php if ( $thumb ) : ?>
                            <img src="<?php echo esc_url( $thumb ); ?>"
                                 alt="" class="psc-dash-store-select-thumb">
                        <?php else : ?>
                            <div class="psc-dash-store-select-thumb psc-dash-thumb-empty">🏪</div>
                        <?php endif; ?>
                        <div class="psc-dash-store-select-info">
                            <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
                                <strong><?php echo esc_html( $name ); ?></strong>
                                <span class="psc-dash-plan-badge psc-dash-plan-badge--<?php echo $is_premium ? 'premium' : 'free'; ?>">
                                    <?php echo $is_premium ? '★ 프리미엄' : '무료'; ?>
                                </span>
                            </div>
                            <?php if ( $address ) : ?>
                                <p style="font-size:.83rem;color:#888;">
                                    📍 <?php echo esc_html( $address ); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        <span style="font-size:1.4rem;color:#ccc;flex-shrink:0;">›</span>
                    </div>
                </a>
            <?php endforeach; ?>
            </div>
        </div>
    </main>
    <?php wp_footer(); ?>
    </body>
    </html>
    <style>
    .psc-dash-store-select-card{
        background:#fff;border-radius:14px;padding:14px 16px;
        display:flex;align-items:center;gap:14px;transition:box-shadow .15s;
    }
    .psc-dash-store-select-card:hover{box-shadow:0 2px 16px rgba(0,0,0,.1);}
    .psc-dash-store-select-thumb{
        width:56px;height:56px;border-radius:10px;object-fit:cover;flex-shrink:0;
        display:flex;align-items:center;justify-content:center;font-size:1.5rem;
    }
    .psc-dash-store-select-info{flex:1;min-width:0;}
    </style>
    <?php
    exit;
}
