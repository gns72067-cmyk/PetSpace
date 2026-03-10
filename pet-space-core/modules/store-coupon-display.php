<?php
/**
 * Store Single Page – Coupon Display & Issue
 * File: modules/store-coupon-display.php
 * 숏코드: [psc_store_coupons]
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/* ============================================================
   숏코드 등록
   ============================================================ */
add_shortcode( 'psc_store_coupons', 'psc_store_coupons_shortcode' );

function psc_store_coupons_shortcode( $atts ): string {

    $atts = shortcode_atts( [ 'store_id' => 0 ], $atts, 'psc_store_coupons' );

    $store_id = (int) $atts['store_id'] ?: get_the_ID();
    if ( ! $store_id ) return '';

    $coupons = get_posts( [
        'post_type'      => PSC_COUPON_CPT,
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_query'     => [ [ 'key' => PSC_COUPON_STORE_META, 'value' => $store_id ] ],
    ] );

    /* 만료·마감 필터 */
    $active = array_filter( $coupons, function( $c ) {
        $id        = $c->ID;
        $expire    = get_post_meta( $id, 'coupon_expire_date', true );
        $max_issue = (int) get_post_meta( $id, 'coupon_max_issue', true );
        if ( $expire && strtotime( $expire ) < time() ) return false;
        if ( $max_issue > 0 ) {
            global $wpdb;
            $table  = $wpdb->prefix . PSC_COUPON_TABLE;
            $issued = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM `{$table}` WHERE coupon_id = %d", $id
            ));
            if ( $issued >= $max_issue ) return false;
        }
        return true;
    });

    if ( empty( $active ) ) return '';

    $user_id   = get_current_user_id();
    $count     = count( $active );
    $first     = array_values( $active )[0];
    $first_id  = $first->ID;
    $first_val = get_post_meta( $first_id, 'coupon_value', true );
    $first_type= get_post_meta( $first_id, 'coupon_type',  true );
    $first_disc= $first_type === 'fixed'
                 ? number_format( (float) $first_val ) . '원'
                 : (float) $first_val . '%';

    ob_start();
    psc_store_coupon_inline_css();
    ?>

    <!-- ── 쿠폰 배너 ── -->
    <div class="psc-cp-banner" id="psc-cp-banner-<?php echo $store_id; ?>"
         onclick="pscOpenCouponSheet(<?php echo $store_id; ?>)">
        <div class="psc-cp-banner__left">
            <span class="psc-cp-banner__icon">🏷️</span>
            <div class="psc-cp-banner__info">
                <span class="psc-cp-banner__main">
                    최대 <strong><?php echo esc_html( $first_disc ); ?></strong> 할인
                </span>
                <?php if ( $count > 1 ) : ?>
                    <span class="psc-cp-banner__more">쿠폰 <?php echo $count; ?>장 보유</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="psc-cp-banner__right">
            <span class="psc-cp-banner__cta">쿠폰 받기</span>
            <span class="psc-cp-banner__arrow">›</span>
        </div>
    </div>

    <!-- ── 오버레이 ── -->
    <div class="psc-cp-overlay" id="psc-cp-overlay-<?php echo $store_id; ?>"
         onclick="pscCloseCouponSheet(<?php echo $store_id; ?>)"></div>

    <!-- ── 바텀시트 ── -->
    <div class="psc-cp-sheet" id="psc-cp-sheet-<?php echo $store_id; ?>">

        <!-- 핸들 -->
        <div class="psc-cp-sheet__handle"></div>

        <div class="psc-cp-sheet__header">
            <span class="psc-cp-sheet__title">할인 쿠폰 받기</span>
            <button class="psc-cp-sheet__close"
                    onclick="pscCloseCouponSheet(<?php echo $store_id; ?>)">✕</button>
        </div>
        <p class="psc-cp-sheet__sub">쿠폰을 받은 후 사용하기를 누르면 QR코드와 코드가 표시됩니다.</p>

        <div class="psc-cp-sheet__list">
        <?php foreach ( $active as $coupon ) :
            $cid    = $coupon->ID;
            $type   = get_post_meta( $cid, 'coupon_type',        true );
            $val    = get_post_meta( $cid, 'coupon_value',       true );
            $expire = get_post_meta( $cid, 'coupon_expire_date', true );
            $desc   = get_post_meta( $cid, 'coupon_desc',        true ) ?: $coupon->post_content;

            $disc_num  = (float) $val;
            $disc_unit = $type === 'fixed' ? '원' : '%';
            $disc_big  = $type === 'fixed'
                         ? number_format( $disc_num ) . '원'
                         : $disc_num . '%';

            /* 발급 여부 */
            $already = false;
            $token   = '';
            $is_used = 0;
            if ( $user_id ) {
                global $wpdb;
                $t   = $wpdb->prefix . PSC_COUPON_TABLE;
                $row = $wpdb->get_row( $wpdb->prepare(
                    "SELECT token, is_used FROM `{$t}`
                     WHERE coupon_id=%d AND user_id=%d
                     ORDER BY issued_at DESC LIMIT 1",
                    $cid, $user_id
                ));
                if ( $row ) {
                    $already = true;
                    $token   = $row->token;
                    $is_used = (int) $row->is_used;
                }
            }
        ?>

            <div class="psc-cp-card" id="psc-cp-card-<?php echo $cid; ?>">

                <!-- 쿠폰 카드 본체 (가로 분리형) -->
                <div class="psc-cp-card__body">

                    <!-- 왼쪽 메인 -->
                    <div class="psc-cp-card__main">
                        <div class="psc-cp-card__discount">
                            <span class="psc-cp-card__discount-num">
                                <?php echo $type === 'fixed'
                                    ? number_format( $disc_num )
                                    : $disc_num; ?>
                            </span>
                            <span class="psc-cp-card__discount-unit"><?php echo $disc_unit; ?></span>
                        </div>
                        <div class="psc-cp-card__name"><?php echo esc_html( $coupon->post_title ); ?></div>
                        <?php if ( $expire ) : ?>
                            <div class="psc-cp-card__period">
                                <?php echo esc_html( $expire ); ?> 까지
                            </div>
                        <?php else : ?>
                            <div class="psc-cp-card__period">상시 사용 가능</div>
                        <?php endif; ?>
                        <?php if ( $desc ) : ?>
                            <div class="psc-cp-card__desc"><?php echo esc_html( $desc ); ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- 오른쪽 사이드 -->
                    <div class="psc-cp-card__side">
                        <span class="psc-cp-card__brand">PetSpace</span>

                        <?php if ( ! $user_id ) : ?>
                            <a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>"
                               class="psc-cp-card__side-btn psc-cp-card__side-btn--login"
                               title="로그인 후 받기">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                     stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
                                    <polyline points="10 17 15 12 10 7"/>
                                    <line x1="15" y1="12" x2="3" y2="12"/>
                                </svg>
                            </a>

                        <?php elseif ( $already && $is_used ) : ?>
                            <button class="psc-cp-card__side-btn psc-cp-card__side-btn--used"
                                    disabled title="사용완료">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                     stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="20 6 9 17 4 12"/>
                                </svg>
                            </button>

                        <?php elseif ( $already ) : ?>
                            <button class="psc-cp-card__side-btn psc-cp-card__side-btn--use"
                                    onclick="pscShowToken(<?php echo $cid; ?>, '<?php echo esc_js( $token ); ?>')"
                                    title="사용하기">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                     stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="2" y="6" width="20" height="12" rx="2"/>
                                    <path d="M12 6v12M8 6v12"/>
                                </svg>
                            </button>

                        <?php else : ?>
                            <button class="psc-cp-card__side-btn psc-cp-card__side-btn--get"
                                    id="psc-btn-<?php echo $cid; ?>"
                                    onclick="pscIssueCoupon(this, <?php echo $cid; ?>, <?php echo $store_id; ?>)"
                                    title="쿠폰 받기">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                     stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="12" y1="5" x2="12" y2="19"/>
                                    <line x1="5" y1="12" x2="19" y2="12"/>
                                </svg>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 토큰 + QR 표시 영역 -->
                <div class="psc-cp-token-area" id="psc-token-area-<?php echo $cid; ?>"
                     style="display:none">
                    <div class="psc-cp-token-area__inner">
                        <div id="psc-qr-<?php echo $cid; ?>" class="psc-cp-qr"></div>
                        <p class="psc-cp-token-label">매장에 QR 또는 코드를 보여주세요</p>
                        <div class="psc-cp-token-wrap">
                            <span class="psc-cp-token-code"
                                  id="psc-token-<?php echo $cid; ?>"></span>
                            <button class="psc-cp-copy-btn"
                                    onclick="pscCopyToken(this, document.getElementById('psc-token-<?php echo $cid; ?>').textContent)">
                                복사
                            </button>
                        </div>
                    </div>
                </div>

                <!-- 에러 메시지 -->
                <p class="psc-cp-err" id="psc-err-<?php echo $cid; ?>" style="display:none"></p>

            </div>
        <?php endforeach; ?>
        </div>

        <!-- 전체 받기 버튼 -->
        <?php if ( $user_id && count( $active ) > 1 ) : ?>
            <button class="psc-cp-sheet__all-btn"
                    onclick="pscIssueAll(<?php echo $store_id; ?>)">
                쿠폰 전체 받기
            </button>
        <?php endif; ?>

    </div>

    <!-- QR 라이브러리 -->
    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
    <script>
    /* ── 바텀시트 열기/닫기 ── */
    function pscOpenCouponSheet(sid) {
        document.getElementById('psc-cp-overlay-' + sid).classList.add('active');
        document.getElementById('psc-cp-sheet-'   + sid).classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    function pscCloseCouponSheet(sid) {
        document.getElementById('psc-cp-overlay-' + sid).classList.remove('active');
        document.getElementById('psc-cp-sheet-'   + sid).classList.remove('active');
        document.body.style.overflow = '';
    }

    /* ── AJAX 설정 ── */
    var pscCouponAjax = {
        url  : '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>',
        nonce: '<?php echo esc_js( wp_create_nonce( 'psc_issue_coupon_nonce' ) ); ?>'
    };

    /* ── 쿠폰 발급 ── */
    function pscIssueCoupon(btn, couponId, storeId) {
        btn.disabled = true;
        btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px;animation:pscSpin .8s linear infinite"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>';

        fetch(pscCouponAjax.url, {
            method : 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded'},
            body   : new URLSearchParams({
                action   : 'psc_issue_coupon',
                nonce    : pscCouponAjax.nonce,
                coupon_id: couponId,
                store_id : storeId,
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                var token = data.data.token;
                /* 버튼 → 사용하기로 교체 */
                btn.disabled = false;
                btn.className = 'psc-cp-card__side-btn psc-cp-card__side-btn--use';
                btn.title = '사용하기';
                btn.setAttribute('onclick', "pscShowToken(" + couponId + ",'" + token + "')");
                btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="2"/><path d="M12 6v12M8 6v12"/></svg>';
            } else {
                btn.disabled = false;
                btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>';
                var err = document.getElementById('psc-err-' + couponId);
                if (err) {
                    err.textContent = data.data.message || '오류가 발생했습니다.';
                    err.style.display = 'block';
                    setTimeout(function(){ err.style.display = 'none'; }, 3000);
                }
            }
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>';
        });
    }

    /* ── 전체 받기 ── */
    function pscIssueAll(storeId) {
        document.querySelectorAll('.psc-cp-card__side-btn--get[id^="psc-btn-"]').forEach(function(btn) {
            var cid = parseInt(btn.id.replace('psc-btn-', ''));
            pscIssueCoupon(btn, cid, storeId);
        });
    }

    /* ── 토큰 + QR 표시 ── */
    function pscShowToken(couponId, token) {
        var area  = document.getElementById('psc-token-area-' + couponId);
        var qrDiv = document.getElementById('psc-qr-'         + couponId);
        var tokEl = document.getElementById('psc-token-'      + couponId);

        if (area.style.display !== 'none') {
            area.style.display = 'none';
            return;
        }

        tokEl.textContent = token;
        qrDiv.innerHTML   = '';

        if (typeof QRCode !== 'undefined') {
            QRCode.toCanvas
                ? QRCode.toCanvas(qrDiv, token, {width:160, margin:2}, function(){})
                : new QRCode(qrDiv, {text: token, width:160, height:160, correctLevel: QRCode.CorrectLevel.M});
        }

        area.style.display = 'block';
        area.scrollIntoView({behavior:'smooth', block:'nearest'});
    }

    /* ── 복사 ── */
    function pscCopyToken(btn, token) {
        navigator.clipboard.writeText(token.trim()).then(() => {
            var orig = btn.textContent;
            btn.textContent  = '✓ 복사됨';
            btn.style.background = '#16a34a';
            setTimeout(() => {
                btn.textContent  = orig;
                btn.style.background = '';
            }, 2000);
        });
    }
    </script>

    <?php
    return ob_get_clean();
}

/* ============================================================
   AJAX — 쿠폰 발급
   ============================================================ */
add_action( 'wp_ajax_psc_issue_coupon',        'psc_ajax_issue_coupon' );
add_action( 'wp_ajax_nopriv_psc_issue_coupon', 'psc_ajax_issue_coupon_nopriv' );

function psc_ajax_issue_coupon_nopriv(): void {
    wp_send_json_error( [ 'message' => '로그인이 필요합니다.' ] );
}

function psc_ajax_issue_coupon(): void {
    check_ajax_referer( 'psc_issue_coupon_nonce', 'nonce' );

    $coupon_id = (int) ( $_POST['coupon_id'] ?? 0 );
    $store_id  = (int) ( $_POST['store_id']  ?? 0 );
    $user_id   = get_current_user_id();

    if ( ! $coupon_id || ! $user_id )
        wp_send_json_error( [ 'message' => '잘못된 요청입니다.' ] );

    $owner_sid = (int) get_post_meta( $coupon_id, PSC_COUPON_STORE_META, true );
    if ( $owner_sid !== $store_id )
        wp_send_json_error( [ 'message' => '유효하지 않은 쿠폰입니다.' ] );

    $expire = get_post_meta( $coupon_id, 'coupon_expire_date', true );
    if ( $expire && strtotime( $expire ) < time() )
        wp_send_json_error( [ 'message' => '만료된 쿠폰입니다.' ] );

    global $wpdb;
    $table = $wpdb->prefix . PSC_COUPON_TABLE;

    $max_issue = (int) get_post_meta( $coupon_id, 'coupon_max_issue', true );
    if ( $max_issue > 0 ) {
        $issued = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM `{$table}` WHERE coupon_id = %d", $coupon_id
        ));
        if ( $issued >= $max_issue )
            wp_send_json_error( [ 'message' => '쿠폰이 모두 소진되었습니다.' ] );
    }

    $max_per_user = (int) get_post_meta( $coupon_id, 'coupon_max_per_user', true ) ?: 1;
    $user_count   = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM `{$table}` WHERE coupon_id = %d AND user_id = %d",
        $coupon_id, $user_id
    ));
    if ( $user_count >= $max_per_user )
        wp_send_json_error( [ 'message' => '이미 발급받은 쿠폰입니다.' ] );

    $result = psc_coupon_issue( $coupon_id, $user_id );

    if ( is_wp_error( $result ) )
        wp_send_json_error( [ 'message' => $result->get_error_message() ] );

    wp_send_json_success( [ 'token' => $result ] );
}

/* ============================================================
   인라인 CSS
   ============================================================ */
function psc_store_coupon_inline_css(): void {
    $primary      = '#224471';
    $primary_dark = '#1a3459';
    ?>
<style>
@keyframes pscSpin {
    to { transform: rotate(360deg); }
}

/* ── 배너 ── */
.psc-cp-banner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #fff;
    border: 1.5px solid #e5e8eb;
    border-radius: 14px;
    padding: 14px 18px;
    cursor: pointer;
    margin: 16px 0 4px;
    transition: box-shadow .15s, border-color .15s;
    box-shadow: 0 2px 8px rgba(0,0,0,.05);
}
.psc-cp-banner:hover {
    border-color: <?php echo $primary; ?>;
    box-shadow: 0 4px 16px rgba(34,68,113,.12);
}
.psc-cp-banner__left {
    display: flex;
    align-items: center;
    gap: 10px;
}
.psc-cp-banner__icon { font-size: 1.4rem; }
.psc-cp-banner__info {
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.psc-cp-banner__main {
    font-size: .92rem;
    font-weight: 600;
    color: #191f28;
}
.psc-cp-banner__main strong {
    color: <?php echo $primary; ?>;
    font-weight: 800;
    font-size: 1rem;
}
.psc-cp-banner__more {
    font-size: .76rem;
    color: #8b95a1;
    font-weight: 500;
}
.psc-cp-banner__right {
    display: flex;
    align-items: center;
    gap: 4px;
}
.psc-cp-banner__cta {
    font-size: .82rem;
    font-weight: 700;
    color: <?php echo $primary; ?>;
}
.psc-cp-banner__arrow {
    font-size: 1.1rem;
    color: <?php echo $primary; ?>;
    font-weight: 700;
}

/* ── 오버레이 ── */
.psc-cp-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.45);
    z-index: 9998;
}
.psc-cp-overlay.active { display: block; }

/* ── 바텀시트 ── */
.psc-cp-sheet {
    position: fixed;
    bottom: 0; left: 0; right: 0;
    background: #fff;
    border-radius: 20px 20px 0 0;
    z-index: 9999;
    padding: 0 0 40px;
    max-height: 82vh;
    overflow-y: auto;
    transform: translateY(100%);
    transition: transform .3s cubic-bezier(.4,0,.2,1);
    font-family: "Pretendard", -apple-system, sans-serif;
}
.psc-cp-sheet.active { transform: translateY(0); }

/* 핸들 */
.psc-cp-sheet__handle {
    width: 36px;
    height: 4px;
    background: #e5e8eb;
    border-radius: 2px;
    margin: 12px auto 0;
}

.psc-cp-sheet__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 20px 0;
    position: sticky;
    top: 0;
    background: #fff;
    z-index: 1;
}
.psc-cp-sheet__title {
    font-size: 1rem;
    font-weight: 800;
    color: #191f28;
    letter-spacing: -.02em;
}
.psc-cp-sheet__close {
    background: #224471;
    border: none;
    font-size: 1.05rem;
    cursor: pointer;
    color: #fff;
    padding: 0;
    line-height: 1;
    width: 32px;
    height: 32px;
    border-radius: 50%;
}
.psc-cp-sheet__close:hover { background: #1a3459; }
.psc-cp-sheet__sub {
    font-size: .78rem;
    color: #8b95a1;
    padding: 6px 20px 0;
    margin: 0 0 14px;
    line-height: 1.5;
}
.psc-cp-sheet__list {
    padding: 0 16px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.psc-cp-sheet__all-btn {
    display: block;
    width: calc(100% - 32px);
    margin: 16px 16px 0;
    background: #f4f6f9;
    color: #4e5968;
    border: 1.5px solid #e5e8eb;
    border-radius: 12px;
    padding: 14px;
    font-size: .9rem;
    font-weight: 700;
    cursor: pointer;
    transition: background .15s;
    font-family: "Pretendard", sans-serif;
}
.psc-cp-sheet__all-btn:hover { background: #e5e8eb; }

/* ── 쿠폰 카드 (가로 분리형) ── */
.psc-cp-card {
    border-radius: 16px;
    border: 1px solid #e5e8eb;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,.05);
    background: #fff;
}

.psc-cp-card__body {
    display: flex;
    flex-direction: row;
}

/* ── 왼쪽 메인 ── */
.psc-cp-card__main {
    flex: 1;
    padding: 18px 18px 16px 20px;
    display: flex;
    flex-direction: column;
    gap: 4px;
    position: relative;
    min-width: 0;
}
.psc-cp-card__main::after {
    content: "";
    position: absolute;
    right: 0; top: 10px; bottom: 10px;
    border-right: 2px dashed #e5e8eb;
}

/* ── 할인 숫자 ── */
.psc-cp-card__discount {
    display: flex;
    align-items: baseline;
    gap: 2px;
    line-height: 1;
    margin-bottom: 2px;
}
.psc-cp-card__discount-num {
    font-size: 2.2rem;
    font-weight: 800;
    color: <?php echo $primary; ?>;
    letter-spacing: -2px;
}
.psc-cp-card__discount-unit {
    font-size: 1.1rem;
    font-weight: 700;
    color: <?php echo $primary; ?>;
}

/* ── 쿠폰 이름 ── */
.psc-cp-card__name {
    font-size: .86rem;
    font-weight: 700;
    color: #191f28;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* ── 기간 ── */
.psc-cp-card__period {
    font-size: .74rem;
    color: #8b95a1;
}

/* ── 설명 ── */
.psc-cp-card__desc {
    font-size: .75rem;
    color: #4e5968;
    line-height: 1.5;
    margin-top: 2px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

/* ── 오른쪽 사이드 ── */
.psc-cp-card__side {
    width: 68px;
    background: #224471;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 12px;
    padding: 18px 0;
    flex-shrink: 0;
}

/* ── 브랜드 세로 텍스트 ── */
.psc-cp-card__brand {
    font-size: .68rem;
    font-weight: 700;
    color: rgba(255,255,255,.8);
    writing-mode: vertical-rl;
    text-orientation: mixed;
    transform: rotate(180deg);
    letter-spacing: .05em;
}

/* ── 사이드 버튼 (원형) ── */
.psc-cp-card__side-btn {
    width: 40px; height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: background .2s, transform .1s;
    padding: 0;
    flex-shrink: 0;
    border: 2px solid rgba(255,255,255,.4);
    background: rgba(255,255,255,.15);
    text-decoration: none;
}
.psc-cp-card__side-btn:hover {
    background: rgba(255,255,255,.3);
}
.psc-cp-card__side-btn:active {
    transform: scale(.92);
}
.psc-cp-card__side-btn svg {
    width: 17px; height: 17px;
    color: #fff;
    pointer-events: none;
}

/* 사용완료 상태 */
.psc-cp-card__side-btn--used {
    opacity: .45;
    cursor: not-allowed;
}

/* ── 토큰 + QR 영역 ── */
.psc-cp-token-area {
    background: #f0fdf4;
    border-top: 1.5px solid #bbf7d0;
    padding: 20px 18px 18px;
}
.psc-cp-token-area__inner {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
}
.psc-cp-qr {
    background: #fff;
    padding: 8px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,.08);
}
.psc-cp-qr canvas,
.psc-cp-qr img { display: block; }
.psc-cp-token-label {
    font-size: .78rem;
    font-weight: 600;
    color: #4e5968;
    text-align: center;
}
.psc-cp-token-wrap {
    width: 100%;
    background: #fff;
    border: 1.5px dashed #6ee7b7;
    border-radius: 10px;
    padding: 10px 14px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}
.psc-cp-token-code {
    font-family: monospace;
    font-size: .8rem;
    color: #191f28;
    word-break: break-all;
    line-height: 1.5;
}
button.psc-cp-copy-btn {
    flex-shrink: 0;
    padding: 6px 14px;
    background: #224471;
    color: #fff;
    border: none;
    border-radius: 8px;
    font-size: .76rem;
    font-weight: 700;
    cursor: pointer;
    transition: background .15s;
    font-family: "Pretendard", sans-serif;
}
button.psc-cp-copy-btn:hover { background: #1a3459; }

/* ── 에러 ── */
.psc-cp-err {
    font-size: .78rem;
    color: #dc2626;
    padding: 8px 16px;
    background: #fff0f0;
    border-top: 1px solid #ffd6d6;
    text-align: center;
    margin: 0;
}
</style>
    <?php
}

/* ============================================================
   Elementor 빈 쿠폰 위젯 자동 숨김
   ============================================================ */
add_action( 'wp_footer', function () {
    $cpt = defined( 'PSC_STORE_CPT' ) ? PSC_STORE_CPT : 'store';
    if ( ! is_singular( $cpt ) ) return;
    ?>
    <script>
    (function () {
        if ( document.querySelector('.psc-store-coupon-section') ) return;
        document.querySelectorAll('.elementor-widget-shortcode').forEach(function (widget) {
            var inner = widget.querySelector('.elementor-shortcode');
            if ( ! inner ) return;
            if ( inner.innerHTML.trim() !== '' ) return;
            widget.style.display = 'none';
            var col = widget.closest('.elementor-column, .e-con-inner, .e-con');
            if ( col ) {
                var visibleWidgets = Array.from(
                    col.querySelectorAll('.elementor-widget')
                ).filter(function (w) {
                    return w.style.display !== 'none';
                });
                if ( visibleWidgets.length === 0 ) col.style.display = 'none';
            }
            var sec = widget.closest('.elementor-section, .e-con');
            if ( sec ) {
                var visibleCols = Array.from(
                    sec.querySelectorAll('.elementor-column, .e-con-inner, .e-con')
                ).filter(function (c) {
                    return c.style.display !== 'none';
                });
                if ( visibleCols.length === 0 ) sec.style.display = 'none';
            }
        });
    })();
    </script>
    <?php
} );
