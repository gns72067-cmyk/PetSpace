<?php
/**
 * Module: Hours
 * Shortcode: [store_hours]
 *
 * 사용 예)
 *   [store_hours id="42"]
 *   [store_hours id="42" show_all="true"]
 *
 * ACF 필드 형식
 * ─────────────────────────────────────────────────────────────
 * store_hours_weekly   : Repeater
 *   └ day              : select  (mon·tue·wed·thu·fri·sat·sun)
 *   └ open             : time    (09:00)
 *   └ close            : time    (21:00)
 *   └ day_off          : true_false
 *
 * store_temp_closures  : Repeater
 *   └ start_date       : date    (Ymd)
 *   └ end_date         : date    (Ymd)
 *   └ reason           : text
 * ─────────────────────────────────────────────────────────────
 */

defined( 'ABSPATH' ) || exit;

/* ── 상수 ── */
const PSC_DAY_MAP = [
    'mon' => '월',
    'tue' => '화',
    'wed' => '수',
    'thu' => '목',
    'fri' => '금',
    'sat' => '토',
    'sun' => '일',
];

/**
 * 오늘 날짜 기준으로 임시휴무 여부 확인.
 *
 * @param int    $post_id
 * @param string $today  Ymd
 * @return array{is_closed:bool, reason:string}
 */
function psc_check_temp_closure( int $post_id, string $today ): array {
    $closures = get_post_meta( $post_id, 'store_temp_closures', true );
    if ( ! is_array( $closures ) ) {
        return [ 'is_closed' => false, 'reason' => '' ];
    }
    foreach ( $closures as $row ) {
        $start  = $row['start_date'] ?? '';
        $end    = $row['end_date']   ?? $start;
        $reason = $row['reason']     ?? '';
        if ( $start && $today >= $start && $today <= $end ) {
            return [ 'is_closed' => true, 'reason' => $reason ];
        }
    }
    return [ 'is_closed' => false, 'reason' => '' ];
}

/**
 * 주간 영업 시간표에서 특정 요일(영문 약자) 행 반환.
 *
 * @param int    $post_id
 * @param string $day_key  'mon' … 'sun'
 * @return array|null
 */
function psc_get_day_row( int $post_id, string $day_key ): ?array {
    $weekly = get_post_meta( $post_id, 'store_hours_weekly', true );
    if ( ! is_array( $weekly ) ) {
        return null;
    }
    foreach ( $weekly as $row ) {
        if ( ( $row['day'] ?? '' ) === $day_key ) {
            return $row;
        }
    }
    return null;
}

/**
 * open/close 시간 문자열(H:i) → "오전/오후 N:MM" 형식으로 변환.
 *
 * @param string $time  "09:00"
 * @return string       "오전 9:00"
 */
function psc_format_time( string $time ): string {
    [ $h, $m ] = explode( ':', $time ) + [ 0, '00' ];
    $h    = (int) $h;
    $ampm = $h < 12 ? '오전' : '오후';
    $h12  = $h % 12 ?: 12;
    return "{$ampm} {$h12}:{$m}";
}

/**
 * 익일 마감 여부 판단: close 시각 < open 시각이면 익일 마감.
 *
 * @param string $open   "22:00"
 * @param string $close  "02:00"
 * @return bool
 */
function psc_is_next_day_close( string $open, string $close ): bool {
    $to_min = static fn( string $t ): int => (int) substr( $t, 0, 2 ) * 60 + (int) substr( $t, 3, 2 );
    return $to_min( $close ) < $to_min( $open );
}

/**
 * 오늘 영업 상태 텍스트와 CSS 클래스 반환.
 *
 * @param array  $row    weekly 한 행
 * @param string $now    H:i  현재 시각
 * @return array{label:string, class:string}
 */
function psc_today_status( array $row, string $now ): array {
    if ( ! empty( $row['day_off'] ) ) {
        return [ 'label' => '정기휴무', 'class' => 'psc-closed' ];
    }
    $open  = $row['open']  ?? '';
    $close = $row['close'] ?? '';
    if ( ! $open || ! $close ) {
        return [ 'label' => '정보 없음', 'class' => 'psc-unknown' ];
    }
    $to_min  = static fn( string $t ): int => (int) substr( $t, 0, 2 ) * 60 + (int) substr( $t, 3, 2 );
    $now_m   = $to_min( $now );
    $open_m  = $to_min( $open );
    $close_m = $to_min( $close );
    $next_day = psc_is_next_day_close( $open, $close );

    if ( $next_day ) {
        // 자정을 넘겨 마감 → 오늘 open_m 이후 or 내일 close_m 이전 = 영업 중
        $is_open = ( $now_m >= $open_m ) || ( $now_m < $close_m );
    } else {
        $is_open = $now_m >= $open_m && $now_m < $close_m;
    }

    $close_str = psc_format_time( $close ) . ( $next_day ? ' (익일)' : '' );

    return $is_open
        ? [ 'label' => "영업중 · {$close_str} 마감", 'class' => 'psc-open' ]
        : [ 'label' => "영업 종료 · {$open_m_str} 오픈 예정", 'class' => 'psc-closed' ];
    // ↑ $open_m_str 은 아래 shortcode 함수에서 처리, 여기선 단순 반환용
}

/**
 * [store_hours] 숏코드 메인 함수.
 *
 * @param array $atts
 * @return string HTML
 */
function psc_shortcode_store_hours( array $atts ): string {

    $atts = shortcode_atts(
        [
            'id'       => get_the_ID(),   // 포스트 ID
            'show_all' => 'false',         // 주간 전체 보기 기본값
        ],
        $atts,
        'store_hours'
    );

    $post_id  = (int) $atts['id'];
    $show_all = filter_var( $atts['show_all'], FILTER_VALIDATE_BOOLEAN );

    if ( ! $post_id || get_post_type( $post_id ) !== 'store' ) {
        return '<p class="psc-error">유효한 매장 ID가 필요합니다.</p>';
    }

    /* ── 날짜/시각 ── */
    $tz       = new DateTimeZone( wp_timezone_string() ?: 'Asia/Seoul' );
    $now_dt   = new DateTime( 'now', $tz );
    $today    = $now_dt->format( 'Ymd' );
    $now_time = $now_dt->format( 'H:i' );
    $day_key  = strtolower( $now_dt->format( 'D' ) );  // 'mon'…'sun'

    /* ── 임시휴무 확인 ── */
    $temp     = psc_check_temp_closure( $post_id, $today );

    /* ── 오늘 행 ── */
    $today_row = psc_get_day_row( $post_id, $day_key );

    /* ── 오늘 상태 계산 ── */
    if ( $temp['is_closed'] ) {
        $status_label = '임시휴무' . ( $temp['reason'] ? " · {$temp['reason']}" : '' );
        $status_class = 'psc-temp-closed';
    } elseif ( ! $today_row ) {
        $status_label = '정보 없음';
        $status_class = 'psc-unknown';
    } elseif ( ! empty( $today_row['day_off'] ) ) {
        $status_label = '정기휴무';
        $status_class = 'psc-closed';
    } else {
        $open  = $today_row['open']  ?? '';
        $close = $today_row['close'] ?? '';

        if ( $open && $close ) {
            $to_min   = static fn( string $t ): int => (int) substr( $t, 0, 2 ) * 60 + (int) substr( $t, 3, 2 );
            $now_m    = $to_min( $now_time );
            $open_m   = $to_min( $open );
            $close_m  = $to_min( $close );
            $next_day = psc_is_next_day_close( $open, $close );

            if ( $next_day ) {
                $is_open = ( $now_m >= $open_m ) || ( $now_m < $close_m );
            } else {
                $is_open = $now_m >= $open_m && $now_m < $close_m;
            }

            $close_str = psc_format_time( $close ) . ( $next_day ? ' (익일)' : '' );
            $open_str  = psc_format_time( $open );

            if ( $is_open ) {
                $status_label = "영업중 · {$close_str} 마감";
                $status_class = 'psc-open';
            } else {
                $status_label = "영업 종료 · {$open_str} 오픈 예정";
                $status_class = 'psc-closed';
            }
        } else {
            $status_label = '정보 없음';
            $status_class = 'psc-unknown';
        }
    }

    /* ── 주간 시간표 ── */
    $weekly  = get_post_meta( $post_id, 'store_hours_weekly', true );
    $weekly  = is_array( $weekly ) ? $weekly : [];
    // day 키 순서 정렬
    $day_order = array_keys( PSC_DAY_MAP );
    usort( $weekly, static function ( $a, $b ) use ( $day_order ): int {
        return array_search( $a['day'] ?? '', $day_order, true )
             - array_search( $b['day'] ?? '', $day_order, true );
    } );

    /* ── 임시휴무 목록 ── */
    $closures = get_post_meta( $post_id, 'store_temp_closures', true );
    $closures = is_array( $closures ) ? $closures : [];
    // 오늘 이후 항목만 필터
    $upcoming_closures = array_filter(
        $closures,
        static fn( $c ) => isset( $c['end_date'] ) && $c['end_date'] >= $today
    );

    /* ── HTML 렌더 ── */
    $uid = 'psc-hours-' . $post_id;   // 더보기 토글용 고유 ID
    ob_start();
    ?>
    <div class="psc-hours-wrap" id="<?php echo esc_attr( $uid ); ?>">

        <?php /* 오늘 상태 배지 */ ?>
        <div class="psc-today-status <?php echo esc_attr( $status_class ); ?>">
            <?php echo esc_html( $status_label ); ?>
        </div>

        <?php /* 더보기 버튼 (show_all="true"일 때는 바로 표시) */ ?>
        <?php if ( ! $show_all ) : ?>
            <button
                class="psc-toggle-btn"
                aria-expanded="false"
                aria-controls="<?php echo esc_attr( $uid . '-table' ); ?>"
            >영업시간 더보기</button>
        <?php endif; ?>

        <?php /* 주간 시간표 */ ?>
        <div
            class="psc-hours-table<?php echo $show_all ? ' psc-visible' : ''; ?>"
            id="<?php echo esc_attr( $uid . '-table' ); ?>"
            <?php if ( ! $show_all ) : ?>style="display:none;"<?php endif; ?>
        >
            <?php if ( $weekly ) : ?>
                <table>
                    <tbody>
                    <?php foreach ( $weekly as $row ) :
                        $dk       = $row['day']     ?? '';
                        $day_off  = ! empty( $row['day_off'] );
                        $is_today = ( $dk === $day_key );
                        $row_open  = $row['open']  ?? '';
                        $row_close = $row['close'] ?? '';
                        $nd        = ( $row_open && $row_close ) ? psc_is_next_day_close( $row_open, $row_close ) : false;
                    ?>
                        <tr class="<?php echo $is_today ? 'psc-today-row' : ''; ?>">
                            <th><?php echo esc_html( PSC_DAY_MAP[ $dk ] ?? $dk ); ?>
                                <?php if ( $is_today ) echo '<span class="psc-today-badge">오늘</span>'; ?>
                            </th>
                            <td>
                                <?php if ( $day_off ) : ?>
                                    <span class="psc-label-off">정기휴무</span>
                                <?php elseif ( $row_open && $row_close ) : ?>
                                    <?php echo esc_html( psc_format_time( $row_open ) ); ?>
                                    ~
                                    <?php echo esc_html( psc_format_time( $row_close ) ); ?>
                                    <?php if ( $nd ) : ?>
                                        <span class="psc-next-day">(익일)</span>
                                    <?php endif; ?>
                                <?php else : ?>
                                    —
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p class="psc-no-hours">영업시간 정보가 없습니다.</p>
            <?php endif; ?>
        </div>

        <?php /* 임시휴무 공지 */ ?>
        <?php if ( $upcoming_closures ) : ?>
            <div class="psc-closures">
                <strong>임시휴무 안내</strong>
                <ul>
                <?php foreach ( $upcoming_closures as $c ) :
                    $s = $c['start_date'] ?? '';
                    $e = $c['end_date']   ?? '';
                    $r = $c['reason']     ?? '';
                    $fmt = static fn( string $d ): string =>
                        $d ? substr( $d, 0, 4 ) . '.' . substr( $d, 4, 2 ) . '.' . substr( $d, 6, 2 ) : '';
                ?>
                    <li>
                        <?php echo esc_html( $fmt( $s ) ); ?>
                        <?php if ( $s !== $e ) echo ' ~ ' . esc_html( $fmt( $e ) ); ?>
                        <?php if ( $r ) echo ' · ' . esc_html( $r ); ?>
                    </li>
                <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

    </div>

    <?php /* 더보기 토글 인라인 스크립트 (show_all=false일 때만) */ ?>
    <?php if ( ! $show_all ) : ?>
    <script>
    (function () {
        var wrap = document.getElementById('<?php echo esc_js( $uid ); ?>');
        if (!wrap) return;
        var btn   = wrap.querySelector('.psc-toggle-btn');
        var table = wrap.querySelector('.psc-hours-table');
        if (!btn || !table) return;
        btn.addEventListener('click', function () {
            var open = btn.getAttribute('aria-expanded') === 'true';
            table.style.display = open ? 'none' : '';
            btn.setAttribute('aria-expanded', open ? 'false' : 'true');
            btn.textContent = open ? '영업시간 더보기' : '영업시간 접기';
        });
    })();
    </script>
    <?php endif; ?>
    <?php
    return ob_get_clean();
}
add_shortcode( 'store_hours', 'psc_shortcode_store_hours' );
