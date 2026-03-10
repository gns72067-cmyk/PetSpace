<?php
/**
 * Module: Address Badge
 * Shortcode: [store_address_badge] [store_city_badge]
 */

defined( 'ABSPATH' ) || exit;

/* ═══════════════════════════════════════════
   1. 파싱 헬퍼
═══════════════════════════════════════════ */

/**
 * store_address 에서 도로명만 파싱.
 * "서울 강남구 테헤란로 123" → "테헤란로 123"
 */
function psc_parse_road_name( string $address ): string {
    if ( ! $address ) return '';

    $parts         = preg_split( '/\s+/', trim( $address ) );
    $road_suffixes = [ '로', '길', '대로' ];

    foreach ( $parts as $i => $part ) {
        foreach ( $road_suffixes as $suffix ) {
            if ( str_ends_with( $part, $suffix ) ) {
                return implode( ' ', array_slice( $parts, $i ) );
            }
        }
    }

    // 못 찾으면 마지막 2개 반환
    return implode( ' ', array_slice( $parts, -2 ) );
}

/**
 * store_address 에서 시·구 파싱.
 * "서울 강남구 테헤란로 123" → "서울 강남구"
 * "경기도 성남시 분당구 …"  → "성남시 분당구"
 */
function psc_parse_city_district( string $address ): string {
    if ( ! $address ) return '';

    $parts = preg_split( '/\s+/', trim( $address ) );
    $metro = [ '서울', '부산', '인천', '대구', '대전', '광주', '울산', '세종' ];

    // 광역시·특별시
    if ( in_array( $parts[0], $metro, true ) ) {
        $district = isset( $parts[1] ) && str_ends_with( $parts[1], '구' ) ? $parts[1] : '';
        return $district ? "{$parts[0]} {$district}" : $parts[0];
    }

    // 도 단위
    if ( str_ends_with( $parts[0], '도' ) ) {
        $city     = $parts[1] ?? '';
        $district = isset( $parts[2] ) && str_ends_with( $parts[2], '구' ) ? $parts[2] : '';
        if ( $city && $district ) return "{$city} {$district}";
        if ( $city )              return $city;
    }

    return implode( ' ', array_slice( $parts, 0, 2 ) );
}

/**
 * 네이버 지도 검색 URL 생성.
 */
function psc_naver_map_url( string $address ): string {
    return 'https://map.naver.com/v5/search/' . rawurlencode( $address );
}

/* ═══════════════════════════════════════════
   2. CSS (최초 1회만 출력)
═══════════════════════════════════════════ */

function psc_address_badge_style(): void {
    static $printed = false;
    if ( $printed ) return;
    $printed = true;
    ?>
    <style id="psc-address-badge-style">
    .psc-addr-wrap {
        display: inline-flex;
        flex-direction: column;
        gap: 6px;
        font-size: 14px;
        line-height: 1.5;
    }

    .psc-addr-top {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        flex-wrap: wrap;
    }

    .psc-addr-icon {
        font-size: 14px;
        flex-shrink: 0;
    }

    .psc-addr-label {
        font-family: Pretendard, sans-serif;
        font-weight: 500;
        color: #333;
    }

    /* ── 버튼 공통 ── */
    .psc-addr-btn {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 10px;
        border-radius: 20px;
        border: 1px solid #ddd;
        background: #fff;
        color: #444;
        font-size: 12px;
        cursor: pointer;
        transition: background .15s, border-color .15s;
        text-decoration: none;
        white-space: nowrap;
    }
    .psc-addr-btn:hover,
    .psc-addr-btn:focus {
        background: transparent;
        border-color: transparent;
        color: #333;
    }

    button.psc-addr-btn.psc-addr-btn-flip {
        border: 0;
        margin: 0;
        padding: 0 4px;
    }

    /* 지도 버튼 */
    .psc-addr-btn-map {
        color: #03c;
        border-color: #c0d0ff;
        background: #f0f4ff;
    }
    .psc-addr-btn-map:hover {
        background: #dce6ff !important;
        border-color: #c0d0ff !important;
        color: #03c !important;
    }

    /* 플립 화살표 */
    .psc-addr-btn-flip .psc-flip-arrow {
        display: inline-block;
        transition: transform .2s;
    }
    .psc-addr-btn-flip[aria-expanded="true"] .psc-flip-arrow {
        transform: rotate(180deg);
    }

    /* ── 상세주소 행 ── */
    .psc-addr-detail {
        display: none;
        align-items: center;
        gap: 8px;
        padding: 8px 12px;
        background: #f8f8f8;
        border-radius: 8px;
        border: 1px solid #eee;
        flex-wrap: wrap;
    }
    .psc-addr-detail.psc-visible {
        display: flex;
    }

    .psc-addr-full {
        color: #333;
        font-size: 13px;
        flex: 1;
        min-width: 0;
        word-break: keep-all;
    }

    .psc-addr-btn-copy {
        flex-shrink: 0;
        color: #555;
    }
    .psc-addr-btn-copy.psc-copied {
        color: #00a86b;
        border-color: #00a86b;
        background: #eaffef;
    }

    /* ── 시·구 전용 뱃지 ── */
    .psc-city-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 12px;
        font-family: Pretendard, sans-serif;
        font-weight: 400;
        color: #9e9e9e !important;
    }
    </style>
    <?php
}

/* ═══════════════════════════════════════════
   3. JS (최초 1회만 출력)
═══════════════════════════════════════════ */

function psc_address_badge_script(): void {
    static $printed = false;
    if ( $printed ) return;
    $printed = true;
    ?>
    <script id="psc-address-badge-script">
    (function () {
        'use strict';

        /* 플립 버튼 토글 */
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.psc-addr-btn-flip');
            if (!btn) return;
            var wrap   = btn.closest('.psc-addr-wrap');
            var detail = wrap && wrap.querySelector('.psc-addr-detail');
            if (!detail) return;

            var expanded = btn.getAttribute('aria-expanded') === 'true';
            btn.setAttribute('aria-expanded', expanded ? 'false' : 'true');
            detail.classList.toggle('psc-visible', !expanded);
        });

        /* 복사 버튼 */
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.psc-addr-btn-copy');
            if (!btn) return;
            var text = btn.dataset.copy || '';
            if (!text) return;

            var copyText = btn.querySelector('.psc-copy-text');

            navigator.clipboard.writeText(text).then(function () {
                btn.classList.add('psc-copied');
                copyText.textContent = '복사됨';
                setTimeout(function () {
                    btn.classList.remove('psc-copied');
                    copyText.textContent = '복사';
                }, 2000);
            }).catch(function () {
                var ta = document.createElement('textarea');
                ta.value = text;
                ta.style.cssText = 'position:fixed;opacity:0';
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
                copyText.textContent = '복사됨';
                setTimeout(function () {
                    copyText.textContent = '복사';
                }, 2000);
            });
        });
    }());
    </script>
    <?php
}

/* ═══════════════════════════════════════════
   4. [store_address_badge] – 도로명 + 화살표 + 지도
═══════════════════════════════════════════ */

function psc_shortcode_address_badge( array $atts ): string {

    $atts = shortcode_atts(
        [ 'id' => get_the_ID() ],
        $atts,
        'store_address_badge'
    );

    $post_id = (int) $atts['id'];
    if ( ! $post_id || get_post_type( $post_id ) !== 'store' ) return '';

    $address     = (string) get_post_meta( $post_id, 'store_address',        true );
    $address_sub = (string) get_post_meta( $post_id, 'store_address_detail', true );
    $full_addr   = trim( $address . ( $address_sub ? ' ' . $address_sub : '' ) );
    $label       = psc_parse_road_name( $address );
    $map_url     = psc_naver_map_url( $full_addr );
    $uid         = 'psc-addr-' . $post_id;

    if ( ! $label ) return '';

    ob_start();
    psc_address_badge_style();
    ?>

    <div class="psc-addr-wrap" id="<?php echo esc_attr( $uid ); ?>">

        <div class="psc-addr-top">
            <span class="psc-addr-icon">📍</span>
            <span class="psc-addr-label"><?php echo esc_html( $label ); ?></span>

            <?php /* 플립 버튼 */ ?>
            <button
                class="psc-addr-btn psc-addr-btn-flip"
                aria-expanded="false"
                aria-controls="<?php echo esc_attr( $uid . '-detail' ); ?>"
                type="button"
            >
                <span class="psc-flip-arrow">↓</span>
            </button>

            <?php /* 지도 버튼 */ ?>
            <a
                class="psc-addr-btn psc-addr-btn-map"
                href="<?php echo esc_url( $map_url ); ?>"
                target="_blank"
                rel="noopener noreferrer"
            >
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M2.52694 3.23451C2.79227 3.07053 3.1236 3.05562 3.40259 3.19511L9.0001 5.99387L14.5976 3.19511C14.851 3.06843 15.1492 3.06843 15.4026 3.19511L21.4026 6.19511C21.7075 6.34757 21.9001 6.6592 21.9001 7.0001V20.0001C21.9001 20.312 21.7386 20.6017 21.4733 20.7657C21.2079 20.9297 20.8766 20.9446 20.5976 20.8051L15.0001 18.0063L9.40259 20.8051C9.14922 20.9318 8.85098 20.9318 8.59761 20.8051L2.59761 17.8051C2.2927 17.6526 2.1001 17.341 2.1001 17.0001V4.0001C2.1001 3.68818 2.2616 3.3985 2.52694 3.23451ZM15.9001 16.4439L20.1001 18.5439V7.55633L15.9001 5.45633V16.4439ZM14.1001 5.45633V16.4439L9.9001 18.5439V7.55633L14.1001 5.45633ZM8.1001 7.55633L3.9001 5.45633V16.4439L8.1001 18.5439V7.55633Z" fill="currentColor"/>
                </svg>
                지도
            </a>
        </div>

        <?php /* ── 상세주소 행 (접힘) ── */ ?>
        <div
            class="psc-addr-detail"
            id="<?php echo esc_attr( $uid . '-detail' ); ?>"
        >
            <span class="psc-addr-full"><?php echo esc_html( $full_addr ); ?></span>
            <button
                class="psc-addr-btn psc-addr-btn-copy"
                data-copy="<?php echo esc_attr( $full_addr ); ?>"
                type="button"
            >
                📋 <span class="psc-copy-text">복사</span>
            </button>
        </div>

    </div>

    <?php
    psc_address_badge_script();
    return ob_get_clean();
}
add_shortcode( 'store_address_badge', 'psc_shortcode_address_badge' );

/* ═══════════════════════════════════════════
   5. [store_city_badge] – 시·구만 표출
═══════════════════════════════════════════ */

function psc_shortcode_city_badge( array $atts ): string {

    $atts = shortcode_atts(
        [ 'id' => get_the_ID() ],
        $atts,
        'store_city_badge'
    );

    $post_id = (int) $atts['id'];
    if ( ! $post_id || get_post_type( $post_id ) !== 'store' ) return '';

    $address = (string) get_post_meta( $post_id, 'store_address', true );
    $label   = psc_parse_city_district( $address );

    if ( ! $label ) return '';

    ob_start();
    psc_address_badge_style();
    ?>

    <span class="psc-city-badge">
        <span class="psc-addr-label"><?php echo esc_html( $label ); ?></span>
    </span>

    <?php
    return ob_get_clean();
}
add_shortcode( 'store_city_badge', 'psc_shortcode_city_badge' );
