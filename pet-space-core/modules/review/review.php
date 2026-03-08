<?php
/**
 * Module: Review
 * File: modules/review/review.php
 *
 * 테이블 생성, 상수 정의, 공통 헬퍼
 */

defined( 'ABSPATH' ) || exit;

/* ═══════════════════════════════════════════
   1. 상수
═══════════════════════════════════════════ */

define( 'PSC_REVIEW_TABLE',        $GLOBALS['wpdb']->prefix . 'psc_reviews' );
define( 'PSC_REVIEW_IMAGE_TABLE',  $GLOBALS['wpdb']->prefix . 'psc_review_images' );
define( 'PSC_REVIEW_LIKE_TABLE',   $GLOBALS['wpdb']->prefix . 'psc_review_likes' );
define( 'PSC_REVIEW_REPORT_TABLE', $GLOBALS['wpdb']->prefix . 'psc_review_reports' );

/* ═══════════════════════════════════════════
   2. 테이블 생성 (플러그인 활성화 시)
═══════════════════════════════════════════ */

function psc_review_create_tables(): void {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();

    $sql_reviews = "CREATE TABLE IF NOT EXISTS " . PSC_REVIEW_TABLE . " (
        id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        store_id          BIGINT UNSIGNED NOT NULL,
        user_id           BIGINT UNSIGNED NOT NULL,
        rating            TINYINT(1) NOT NULL,
        content           TEXT NOT NULL,
        tags              VARCHAR(500) DEFAULT NULL,
        status            ENUM('pending','published','hidden','deleted') NOT NULL DEFAULT 'pending',
        likes             INT UNSIGNED NOT NULL DEFAULT 0,
        verify_type       ENUM('receipt','gps') DEFAULT NULL,
        receipt_verified  TINYINT(1) NOT NULL DEFAULT 0,
        receipt_image_id  BIGINT UNSIGNED DEFAULT NULL,
        gps_lat           DECIMAL(10,7) DEFAULT NULL,
        gps_lng           DECIMAL(10,7) DEFAULT NULL,
        report_count      TINYINT UNSIGNED NOT NULL DEFAULT 0,
        report_status     ENUM('none','reported','reviewed') NOT NULL DEFAULT 'none',
        created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_store_id  (store_id),
        KEY idx_user_id   (user_id),
        KEY idx_status    (status),
        KEY idx_likes     (likes)
    ) $charset;";

    $sql_images = "CREATE TABLE IF NOT EXISTS " . PSC_REVIEW_IMAGE_TABLE . " (
        id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        review_id       BIGINT UNSIGNED NOT NULL,
        attachment_id   BIGINT UNSIGNED DEFAULT NULL,
        url             VARCHAR(500) NOT NULL,
        sort_order      TINYINT UNSIGNED NOT NULL DEFAULT 0,
        created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_review_id (review_id)
    ) $charset;";

    $sql_likes = "CREATE TABLE IF NOT EXISTS " . PSC_REVIEW_LIKE_TABLE . " (
        id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        review_id       BIGINT UNSIGNED NOT NULL,
        user_id         BIGINT UNSIGNED NOT NULL,
        created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_review_user (review_id, user_id)
    ) $charset;";

    $sql_reports = "CREATE TABLE IF NOT EXISTS " . PSC_REVIEW_REPORT_TABLE . " (
        id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        review_id       BIGINT UNSIGNED NOT NULL,
        reporter_id     BIGINT UNSIGNED NOT NULL,
        store_id        BIGINT UNSIGNED NOT NULL,
        reason          VARCHAR(500) NOT NULL,
        status          ENUM('pending','resolved') NOT NULL DEFAULT 'pending',
        created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_review_id (review_id),
        KEY idx_status    (status)
    ) $charset;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql_reviews );
    dbDelta( $sql_images );
    dbDelta( $sql_likes );
    dbDelta( $sql_reports );

    $columns = $wpdb->get_results( "SHOW COLUMNS FROM " . PSC_REVIEW_TABLE . " LIKE 'tags'" );
    if ( empty( $columns ) ) {
        $wpdb->query(
            "ALTER TABLE " . PSC_REVIEW_TABLE . " ADD COLUMN tags VARCHAR(500) DEFAULT NULL AFTER content"
        );
    }
}

/* ═══════════════════════════════════════════
   3. 공통 헬퍼
═══════════════════════════════════════════ */

function psc_mask_name( string $name ): string {
    $chars = mb_str_split( $name );
    $len   = count( $chars );
    if ( $len <= 1 ) return $name;
    if ( $len === 2 ) return $chars[0] . '*';
    $middle = str_repeat( '*', $len - 2 );
    return $chars[0] . $middle . $chars[ $len - 1 ];
}

function psc_render_stars( float $rating, bool $interactive = false ): string {
    $html = '<span class="psc-rv-stars-display">';
    for ( $i = 1; $i <= 5; $i++ ) {
        $fill = $i <= $rating ? '#f59e0b' : '#e5e8eb';
        $html .= sprintf(
            '<svg %s width="16" height="16" viewBox="0 0 24 24" fill="%s" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
            </svg>',
            $interactive ? "data-value=\"{$i}\"" : '',
            esc_attr( $fill )
        );
    }
    $html .= '</span>';
    return $html;
}

function psc_get_review( int $review_id ): ?object {
    global $wpdb;
    return $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM " . PSC_REVIEW_TABLE . " WHERE id = %d AND status != 'deleted'",
        $review_id
    ) );
}

function psc_get_reviews(
    int $store_id,
    string $orderby = 'latest',
    int $limit = 10,
    int $offset = 0
): array {
    global $wpdb;
    $order_sql = $orderby === 'likes'
        ? 'r.likes DESC, r.created_at DESC'
        : 'r.created_at DESC';
    return $wpdb->get_results( $wpdb->prepare(
        "SELECT r.*,
                u.display_name,
                COUNT(DISTINCT ri.id) AS image_count
         FROM   " . PSC_REVIEW_TABLE . " r
         JOIN   {$wpdb->users} u ON u.ID = r.user_id
         LEFT JOIN " . PSC_REVIEW_IMAGE_TABLE . " ri ON ri.review_id = r.id
         WHERE  r.store_id = %d
           AND  r.status   = 'published'
         GROUP  BY r.id
         ORDER  BY {$order_sql}
         LIMIT  %d OFFSET %d",
        $store_id, $limit, $offset
    ) );
}

function psc_get_review_summary( int $store_id ): object {
    global $wpdb;
    $row = $wpdb->get_row( $wpdb->prepare(
        "SELECT
            COUNT(*)        AS total,
            AVG(rating)     AS avg_rating,
            SUM(rating = 5) AS star5,
            SUM(rating = 4) AS star4,
            SUM(rating = 3) AS star3,
            SUM(rating = 2) AS star2,
            SUM(rating = 1) AS star1
         FROM " . PSC_REVIEW_TABLE . "
         WHERE store_id = %d AND status = 'published'",
        $store_id
    ) );

    if ( $row ) {
        $row->distribution = [
            1 => (int) $row->star1,
            2 => (int) $row->star2,
            3 => (int) $row->star3,
            4 => (int) $row->star4,
            5 => (int) $row->star5,
        ];
    }

    return $row ?? (object)[
        'total'        => 0,
        'avg_rating'   => 0,
        'star5'        => 0,
        'star4'        => 0,
        'star3'        => 0,
        'star2'        => 0,
        'star1'        => 0,
        'distribution' => [ 1=>0, 2=>0, 3=>0, 4=>0, 5=>0 ],
    ];
}

function psc_get_review_images( int $review_id ): array {
    global $wpdb;
    return $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM " . PSC_REVIEW_IMAGE_TABLE . "
         WHERE review_id = %d ORDER BY sort_order ASC",
        $review_id
    ) );
}

function psc_user_liked_review( int $review_id, int $user_id ): bool {
    global $wpdb;
    return (bool) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM " . PSC_REVIEW_LIKE_TABLE . "
         WHERE review_id = %d AND user_id = %d",
        $review_id, $user_id
    ) );
}

function psc_can_write_review( int $store_id, int $user_id = 0 ): bool|string {
    if ( ! $user_id ) {
        $user_id = get_current_user_id();
    }
    if ( ! $user_id ) return false;

    global $wpdb;
    $exists = $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM " . PSC_REVIEW_TABLE . "
         WHERE store_id = %d AND user_id = %d AND status != 'deleted'",
        $store_id, $user_id
    ) );

    return $exists ? 'duplicate' : true;
}

function psc_review_write_url( int $store_id ): string {
    return home_url( '/review-write/?store_id=' . $store_id );
}

function psc_review_list_url( int $store_id ): string {
    $slug = get_post_field( 'post_name', $store_id );
    return home_url( "/store/{$slug}/reviews/" );
}

/* ═══════════════════════════════════════════
   4. 매장 인증 방식 헬퍼
═══════════════════════════════════════════ */

function psc_get_store_verify_type( int $store_id ): string {
    $type = get_post_meta( $store_id, 'review_verify_type', true );
    return in_array( $type, [ 'receipt', 'gps', 'both' ], true ) ? $type : 'receipt';
}

function psc_get_store_gps_radius( int $store_id ): int {
    $radius = (int) get_post_meta( $store_id, 'review_gps_radius', true );
    return $radius > 0 ? $radius : 300;
}

function psc_get_store_gps( int $store_id ): array {
    return [
        'lat' => (float) get_post_meta( $store_id, 'store_lat', true ),
        'lng' => (float) get_post_meta( $store_id, 'store_lng', true ),
    ];
}

/* ═══════════════════════════════════════════
   5. OCR 헬퍼
═══════════════════════════════════════════ */

function psc_ocr_extract( string $image_url ): array {
    $provider = defined( 'PSC_OCR_PROVIDER' ) ? PSC_OCR_PROVIDER : 'google';
    return $provider === 'naver'
        ? psc_ocr_naver( $image_url )
        : psc_ocr_google( $image_url );
}

function psc_ocr_google( string $image_url ): array {
    $api_key = defined( 'PSC_GOOGLE_VISION_API_KEY' ) ? PSC_GOOGLE_VISION_API_KEY : '';
    if ( ! $api_key ) {
        return [ 'success' => false, 'message' => 'API 키가 설정되지 않았습니다' ];
    }

    $endpoint = "https://vision.googleapis.com/v1/images:annotate?key={$api_key}";
    $body     = json_encode( [
        'requests' => [ [
            'image'    => [ 'source' => [ 'imageUri' => $image_url ] ],
            'features' => [ [ 'type' => 'DOCUMENT_TEXT_DETECTION' ] ],
        ] ]
    ] );

    $response = wp_remote_post( $endpoint, [
        'headers' => [ 'Content-Type' => 'application/json' ],
        'body'    => $body,
        'timeout' => 15,
    ] );

    if ( is_wp_error( $response ) ) {
        return [ 'success' => false, 'message' => $response->get_error_message() ];
    }

    $data = json_decode( wp_remote_retrieve_body( $response ), true );
    $text = $data['responses'][0]['fullTextAnnotation']['text'] ?? '';

    if ( ! $text ) {
        return [ 'success' => false, 'message' => '텍스트를 인식할 수 없습니다' ];
    }

    return [
        'success'  => true,
        'raw_text' => $text,
        'parsed'   => psc_ocr_parse( $text ),
    ];
}

function psc_ocr_naver( string $image_url ): array {
    return [ 'success' => false, 'message' => 'Naver OCR 미구현' ];
}

function psc_ocr_parse( string $text ): array {
    $parsed = [
        'store_name' => '',
        'biz_number' => '',
        'address'    => '',
        'date'       => '',
        'amount'     => '',
    ];

    $lines = array_values( array_filter(
        array_map( 'trim', preg_split( '/\r\n|\r|\n/', $text ) )
    ) );

    // ── 사업자번호 ───────────────────────────────────────────
    if ( preg_match( '/(\d{3}-\d{2}-\d{5})/', $text, $m ) ) {
        $parsed['biz_number'] = $m[1];
    }

    // ── 날짜 ────────────────────────────────────────────────
    if ( preg_match(
        '/(\d{4}[-\.\/년]\s*\d{1,2}[-\.\/월]\s*\d{1,2})/',
        $text, $m
    ) ) {
        $raw_date = preg_replace( '/[년월]/', '-', $m[1] );
        $raw_date = preg_replace( '/[\/]/', '-', $raw_date );
        $raw_date = preg_replace( '/\s+/', '', $raw_date );
        $parsed['date'] = rtrim( $raw_date, '-' );
    }

    // ── 결제금액 ─────────────────────────────────────────────
    if ( preg_match(
        '/(?:합\s*계|받을\s*금액|받은\s*금액|결제\s*금액|총\s*액|합계금액|승인금액|total|amount)[^\d]*(\d[\d,]+)/iu',
        $text, $m
    ) ) {
        $parsed['amount'] = $m[1] . '원';
    }
    if ( empty( $parsed['amount'] ) ) {
        $max_val = 0;
        $max_str = '';
        foreach ( $lines as $line ) {
            if ( preg_match( '/^[\s\*]*(\d{1,3}(?:,\d{3})+)\s*$/', $line, $lm ) ) {
                $val = (int) str_replace( ',', '', $lm[1] );
                if ( $val > $max_val ) {
                    $max_val = $val;
                    $max_str = $lm[1];
                }
            }
        }
        if ( $max_str ) {
            $parsed['amount'] = $max_str . '원';
        }
    }

    // ── 주소 ────────────────────────────────────────────────
    foreach ( $lines as $line ) {
        if (
            preg_match( '/(?:서울|부산|대구|인천|광주|대전|울산|세종|경기|강원|충북|충남|전북|전남|경북|경남|제주)/', $line ) &&
            preg_match( '/(?:로|길|동|번지|구|시|군)/', $line )
        ) {
            $parsed['address'] = $line;
            break;
        }
    }

    // ── 매장명 ──────────────────────────────────────────────
    if ( preg_match( '/(?:상호|업체명|매장명|점포명|가맹점명)\s*[:\：]?\s*(.+)/u', $text, $m ) ) {
        $parsed['store_name'] = trim( $m[1] );
    }

    if ( empty( $parsed['store_name'] ) && ! empty( $parsed['biz_number'] ) ) {
        foreach ( $lines as $i => $line ) {
            if ( str_contains( $line, $parsed['biz_number'] ) ) {
                $before = trim( preg_replace(
                    '/' . preg_quote( $parsed['biz_number'], '/' ) . '.*$/u',
                    '',
                    $line
                ) );
                $before = preg_replace( '/\s*(TEL|tel|전화|FAX|fax)[^\s]*/iu', '', $before );
                $before = trim( $before );

                if ( mb_strlen( $before ) >= 2 ) {
                    $parsed['store_name'] = $before;
                } elseif ( $i > 0 ) {
                    $parsed['store_name'] = $lines[ $i - 1 ];
                }
                break;
            }
        }
    }

    if ( empty( $parsed['store_name'] ) ) {
        foreach ( $lines as $line ) {
            if ( preg_match( '/^\((?:주|유|사|합)\)/', $line ) ) {
                $parsed['store_name'] = $line;
                break;
            }
        }
    }

    if ( empty( $parsed['store_name'] ) ) {
        foreach ( $lines as $line ) {
            if ( mb_strlen( $line ) >= 2 && ! preg_match( '/^\d+$/', $line ) ) {
                $parsed['store_name'] = $line;
                break;
            }
        }
    }

    // ── 매장명 후처리 ─────────────────────────────────────────
    if ( ! empty( $parsed['store_name'] ) ) {
        $parsed['store_name'] = preg_replace(
            '/\s*(TEL|tel|전화|FAX|fax|☎|\d{2,4}-\d{3,4}-\d{4}).*$/iu',
            '',
            $parsed['store_name']
        );
        $parsed['store_name'] = trim( $parsed['store_name'] );

        $invalid_prefixes = [ '사업자', '대표', '주소', '전화', 'TEL', 'FAX', '등록번호', '영수증' ];
        foreach ( $invalid_prefixes as $prefix ) {
            if ( mb_strpos( $parsed['store_name'], $prefix ) === 0 ) {
                $parsed['store_name'] = '';
                break;
            }
        }
    }

    if ( empty( $parsed['store_name'] ) ) {
        foreach ( $lines as $line ) {
            if ( preg_match( '/(?:^\((?:주|유|사|합)\)|주식회사|유한회사)/u', $line ) ) {
                $parsed['store_name'] = preg_replace(
                    '/\s*(TEL|tel|전화|사업자|대표).*$/iu', '', $line
                );
                $parsed['store_name'] = trim( $parsed['store_name'] );
                break;
            }
        }
    }

    return $parsed;
}

function psc_ocr_verify_store( array $parsed, int $store_id ): array {
    $store_name    = get_the_title( $store_id );
    $store_biz     = get_post_meta( $store_id, 'store_biz_number', true );
    $store_address = get_post_meta( $store_id, 'store_address', true );

    $matched   = [];
    $unmatched = [];
    $score     = 0;

    if ( $parsed['biz_number'] && $store_biz ) {
        $biz_p = preg_replace( '/[^0-9]/', '', $parsed['biz_number'] );
        $biz_s = preg_replace( '/[^0-9]/', '', $store_biz );
        if ( $biz_p === $biz_s ) {
            $matched[] = 'biz_number';
            $score    += 50;
        } else {
            $unmatched[] = 'biz_number';
        }
    }

    if ( $parsed['store_name'] && $store_name ) {
        similar_text(
            mb_strtolower( $parsed['store_name'] ),
            mb_strtolower( $store_name ),
            $pct
        );
        if ( $pct >= 80 ) {
            $matched[] = 'store_name';
            $score    += 30;
        } else {
            $unmatched[] = 'store_name';
        }
    }

    if ( $parsed['address'] && $store_address ) {
        $addr_words   = preg_split( '/\s+/', $store_address );
        $addr_matched = false;
        foreach ( $addr_words as $word ) {
            if ( mb_strlen( $word ) > 1 && str_contains( $parsed['address'], $word ) ) {
                $addr_matched = true;
                break;
            }
        }
        if ( $addr_matched ) {
            $matched[] = 'address';
            $score    += 20;
        } else {
            $unmatched[] = 'address';
        }
    }

    $fields    = [];
    $label_map = [
        'store_name' => '매장명',
        'biz_number' => '사업자번호',
        'address'    => '주소',
        'date'       => '날짜',
        'amount'     => '결제금액',
    ];
    foreach ( $label_map as $key => $label ) {
        if ( in_array( $key, $matched, true ) ) {
            $status = 'match';
        } elseif ( in_array( $key, $unmatched, true ) ) {
            $status = 'miss';
        } elseif ( ! empty( $parsed[ $key ] ) ) {
            $status = 'ref';
        } else {
            $status = '';
        }
        $fields[] = [
            'label'  => $label,
            'value'  => $parsed[ $key ] ?? '',
            'status' => $status,
        ];
    }

    return [
        'verified'  => $score >= 50,
        'score'     => $score,
        'matched'   => $matched,
        'unmatched' => $unmatched,
        'fields'    => $fields,
        'parsed'    => $parsed,
    ];
}

/* ═══════════════════════════════════════════
   6. GPS 검증 헬퍼
═══════════════════════════════════════════ */

function psc_gps_distance( float $lat1, float $lng1, float $lat2, float $lng2 ): float {
    $earth_radius = 6371000;
    $d_lat = deg2rad( $lat2 - $lat1 );
    $d_lng = deg2rad( $lng2 - $lng1 );
    $a = sin( $d_lat / 2 ) * sin( $d_lat / 2 ) +
         cos( deg2rad( $lat1 ) ) * cos( deg2rad( $lat2 ) ) *
         sin( $d_lng / 2 ) * sin( $d_lng / 2 );
    $c = 2 * atan2( sqrt( $a ), sqrt( 1 - $a ) );
    return $earth_radius * $c;
}

function psc_gps_verify_store( float $user_lat, float $user_lng, int $store_id ): array {
    $store_gps = psc_get_store_gps( $store_id );
    $radius    = psc_get_store_gps_radius( $store_id );

    if ( ! $store_gps['lat'] || ! $store_gps['lng'] ) {
        return [
            'verified' => false,
            'distance' => 0,
            'radius'   => $radius,
            'message'  => '매장 위치 정보가 설정되지 않았습니다',
        ];
    }

    $distance = psc_gps_distance(
        $user_lat, $user_lng,
        $store_gps['lat'], $store_gps['lng']
    );

    return [
        'verified' => $distance <= $radius,
        'distance' => round( $distance ),
        'radius'   => $radius,
        'message'  => $distance <= $radius
            ? "매장 반경 {$radius}m 이내 확인 완료"
            : "매장에서 " . round( $distance ) . "m 떨어져 있습니다 (허용 반경: {$radius}m)",
    ];
}

/* ═══════════════════════════════════════════
   7. 신고 헬퍼
═══════════════════════════════════════════ */

function psc_save_report(
    int $review_id,
    int $reporter_id,
    int $store_id,
    string $reason
): bool {
    global $wpdb;

    $exists = $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM " . PSC_REVIEW_REPORT_TABLE . "
         WHERE review_id = %d AND reporter_id = %d",
        $review_id, $reporter_id
    ) );
    if ( $exists ) return false;

    $wpdb->insert(
        PSC_REVIEW_REPORT_TABLE,
        [
            'review_id'   => $review_id,
            'reporter_id' => $reporter_id,
            'store_id'    => $store_id,
            'reason'      => $reason,
        ],
        [ '%d', '%d', '%d', '%s' ]
    );

    $wpdb->query( $wpdb->prepare(
        "UPDATE " . PSC_REVIEW_TABLE . "
         SET report_count  = report_count + 1,
             report_status = 'reported'
         WHERE id = %d",
        $review_id
    ) );

    return true;
}

/* ═══════════════════════════════════════════
   8. 리뷰 목록 페이지 라우팅
   URL: /store/{slug}/reviews/
═══════════════════════════════════════════ */

add_action( 'init', function () {
    add_rewrite_rule(
        '^store/([^/]+)/reviews/?$',
        'index.php?psc_store_reviews=$matches[1]',
        'top'
    );
} );

add_filter( 'query_vars', function ( array $vars ): array {
    $vars[] = 'psc_store_reviews';
    return $vars;
} );

add_action( 'template_redirect', function () {
    $slug = get_query_var( 'psc_store_reviews' );
    if ( ! $slug ) return;

    $store = get_page_by_path( $slug, OBJECT, 'store' );
    if ( ! $store ) {
        wp_redirect( home_url( '/' ) );
        exit;
    }

    psc_render_reviews_page( $store->ID );
    exit;
} );

/* ───────────────────────────────────────────
   8-A. 리뷰 목록 페이지 렌더러
─────────────────────────────────────────── */

function psc_render_reviews_page( int $store_id ): void {
    $store_title   = get_the_title( $store_id );
    $store_url     = get_permalink( $store_id );
    $write_url     = psc_review_write_url( $store_id );
    $current_user  = wp_get_current_user();
    $is_logged_in  = is_user_logged_in();
    $can_write     = $is_logged_in ? psc_can_write_review( $store_id, $current_user->ID ) : false;
    $summary       = psc_get_review_summary( $store_id );
    $reviews       = psc_get_reviews( $store_id, 'latest', 20 );
    $avg           = $summary->total > 0 ? round( (float) $summary->avg_rating, 1 ) : 0;
    $all_tags      = function_exists( 'psc_get_review_tags' ) ? psc_get_review_tags( $store_id ) : [];

    if ( function_exists( 'psc_review_style' ) ) psc_review_style();

    // ── 헤더 출력 ──
    get_header();
    ?>
    <div class="psc-reviews-page" style="max-width:680px;margin:0 auto;padding:20px 16px 80px;">

        <?php /* 상단 네비 */ ?>
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:24px;">
            <a href="<?php echo esc_url( $store_url ); ?>"
               style="display:flex;align-items:center;gap:6px;color:#224471;font-size:14px;text-decoration:none;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
                <?php echo esc_html( $store_title ); ?>
            </a>
            <span style="color:#e5e8eb;">/</span>
            <span style="color:#6b7684;font-size:14px;">리뷰</span>
        </div>

        <?php /* 요약 블록 */ ?>
        <div style="background:#f8f9fa;border-radius:16px;padding:24px;margin-bottom:24px;">
            <div style="display:flex;align-items:center;gap:24px;flex-wrap:wrap;">
                <div style="text-align:center;min-width:80px;">
                    <div style="font-size:48px;font-weight:700;color:#1a1a1a;line-height:1;">
                        <?php echo esc_html( number_format( $avg, 1 ) ); ?>
                    </div>
                    <div style="margin:6px 0 4px;">
                        <?php echo psc_render_stars( $avg ); ?>
                    </div>
                    <div style="font-size:13px;color:#6b7684;">
                        <?php echo esc_html( number_format( $summary->total ) ); ?>개 리뷰
                    </div>
                </div>
                <div style="flex:1;min-width:160px;">
                    <?php for ( $s = 5; $s >= 1; $s-- ) : ?>
                        <?php
                        $cnt = (int) ( $summary->distribution[ $s ] ?? 0 );
                        $pct = $summary->total > 0 ? round( $cnt / $summary->total * 100 ) : 0;
                        ?>
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
                            <span style="font-size:12px;color:#6b7684;width:14px;text-align:right;">
                                <?php echo $s; ?>
                            </span>
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="#f59e0b">
                                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                            </svg>
                            <div style="flex:1;height:6px;background:#e5e8eb;border-radius:3px;overflow:hidden;">
                                <div style="height:100%;width:<?php echo $pct; ?>%;background:#f59e0b;border-radius:3px;"></div>
                            </div>
                            <span style="font-size:12px;color:#6b7684;width:28px;"><?php echo $cnt; ?></span>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>

            <?php /* 리뷰 작성 / 매장 정보 버튼 */ ?>
            <div style="display:flex;gap:10px;margin-top:20px;">
                <?php if ( ! $is_logged_in ) : ?>
                    <a href="<?php echo esc_url( home_url( '/login/?redirect_to=' . urlencode( psc_review_write_url( $store_id ) ) ) ); ?>"
                       style="flex:1;display:flex;align-items:center;justify-content:center;gap:6px;
                              background:#224471;color:#fff;border-radius:12px;padding:12px;
                              font-size:14px;font-weight:600;text-decoration:none;">
                        ✏️ 리뷰 작성
                    </a>
                <?php elseif ( $can_write === 'duplicate' ) : ?>
                    <span style="flex:1;display:flex;align-items:center;justify-content:center;
                                 background:#e5e8eb;color:#6b7684;border-radius:12px;padding:12px;
                                 font-size:14px;">
                        ✅ 이미 작성한 리뷰가 있어요
                    </span>
                <?php else : ?>
                    <a href="<?php echo esc_url( $write_url ); ?>"
                       style="flex:1;display:flex;align-items:center;justify-content:center;gap:6px;
                              background:#224471;color:#fff;border-radius:12px;padding:12px;
                              font-size:14px;font-weight:600;text-decoration:none;">
                        ✏️ 리뷰 작성
                    </a>
                <?php endif; ?>
                <a href="<?php echo esc_url( $store_url ); ?>"
                   style="display:flex;align-items:center;justify-content:center;gap:6px;
                          border:1.5px solid #224471;color:#224471;border-radius:12px;padding:12px 16px;
                          font-size:14px;font-weight:600;text-decoration:none;">
                    매장 정보
                </a>
            </div>
        </div>

        <?php /* 리뷰 목록 */ ?>
        <?php if ( empty( $reviews ) ) : ?>
            <div style="text-align:center;padding:60px 0;color:#6b7684;">
                <div style="font-size:40px;margin-bottom:12px;">🐾</div>
                <p style="font-size:15px;">아직 작성된 리뷰가 없어요.<br>첫 번째 리뷰를 작성해보세요!</p>
            </div>
        <?php else : ?>
            <div class="psc-review-list" style="display:flex;flex-direction:column;gap:16px;">
                <?php foreach ( $reviews as $rv ) :
                    $rv_images   = psc_get_review_images( (int) $rv->id );
                    $liked       = $is_logged_in && psc_user_liked_review( (int) $rv->id, $current_user->ID );
                    $is_mine     = $is_logged_in && (int) $rv->user_id === $current_user->ID;
                    $is_admin    = current_user_can( 'manage_options' );
                    $verify_badge = '';
                    if ( $rv->verify_type === 'receipt' && $rv->receipt_verified ) {
                        $verify_badge = '<span style="display:inline-flex;align-items:center;gap:4px;
                            background:#e8f5e9;color:#2e7d32;border-radius:20px;padding:3px 10px;
                            font-size:11px;font-weight:600;">✅ 영수증 인증</span>';
                    } elseif ( $rv->verify_type === 'gps' ) {
                        $verify_badge = '<span style="display:inline-flex;align-items:center;gap:4px;
                            background:#e3f2fd;color:#1565c0;border-radius:20px;padding:3px 10px;
                            font-size:11px;font-weight:600;">📍 GPS 인증</span>';
                    }
                    $tags_html = '';
                    if ( ! empty( $rv->tags ) ) {
                        $tag_list = array_filter( array_map( 'trim', explode( ',', $rv->tags ) ) );
                        foreach ( $tag_list as $tag ) {
                            $tags_html .= '<span style="background:#f1f3f5;color:#495057;border-radius:20px;
                                padding:3px 10px;font-size:12px;">' . esc_html( $tag ) . '</span>';
                        }
                    }
                ?>
                <div class="psc-rv-card" style="background:#fff;border:1px solid #e5e8eb;border-radius:16px;padding:20px;">

                    <?php /* 작성자 정보 */ ?>
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div style="width:36px;height:36px;border-radius:50%;background:#e5e8eb;
                                        display:flex;align-items:center;justify-content:center;
                                        font-size:16px;overflow:hidden;">
                                <?php
                                $avatar = get_avatar_url( $rv->user_id, [ 'size' => 36 ] );
                                if ( $avatar ) {
                                    echo '<img src="' . esc_url( $avatar ) . '" width="36" height="36" style="border-radius:50%;object-fit:cover;">';
                                } else {
                                    echo '🐾';
                                }
                                ?>
                            </div>
                            <div>
                                <div style="font-size:14px;font-weight:600;color:#1a1a1a;">
                                    <?php echo esc_html( psc_mask_name( $rv->display_name ) ); ?>
                                </div>
                                <div style="font-size:12px;color:#6b7684;">
                                    <?php echo esc_html( date_i18n( 'Y.m.d', strtotime( $rv->created_at ) ) ); ?>
                                </div>
                            </div>
                        </div>
                        <div style="display:flex;align-items:center;gap:6px;">
                            <?php echo psc_render_stars( (float) $rv->rating ); ?>
                            <span style="font-size:13px;font-weight:700;color:#1a1a1a;"><?php echo (int) $rv->rating; ?></span>
                        </div>
                    </div>

                    <?php /* 인증 배지 */ ?>
                    <?php if ( $verify_badge ) : ?>
                        <div style="margin-bottom:8px;"><?php echo $verify_badge; ?></div>
                    <?php endif; ?>

                    <?php /* 태그 */ ?>
                    <?php if ( $tags_html ) : ?>
                        <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px;"><?php echo $tags_html; ?></div>
                    <?php endif; ?>

                    <?php /* 본문 */ ?>
                    <div class="psc-rv-content"
                         style="font-size:14px;color:#333;line-height:1.6;
                                display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;">
                        <?php echo nl2br( esc_html( $rv->content ) ); ?>
                    </div>
                    <?php if ( mb_strlen( $rv->content ) > 100 ) : ?>
                        <button onclick="
                            var c = this.previousElementSibling;
                            var isClamp = c.style.webkitLineClamp !== 'unset';
                            c.style.webkitLineClamp = isClamp ? 'unset' : '3';
                            this.textContent = isClamp ? '접기' : '더 보기';
                        " style="background:none;border:none;color:#224471;font-size:13px;
                                 cursor:pointer;padding:4px 0;margin-top:4px;">더 보기</button>
                    <?php endif; ?>

                    <?php /* 이미지 */ ?>
                    <?php if ( ! empty( $rv_images ) ) : ?>
                        <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:12px;">
                            <?php foreach ( $rv_images as $img ) : ?>
                                <a href="<?php echo esc_url( $img->url ); ?>"
                                   data-lightbox="rv-<?php echo (int) $rv->id; ?>"
                                   style="display:block;width:72px;height:72px;border-radius:8px;overflow:hidden;">
                                    <img src="<?php echo esc_url( $img->url ); ?>"
                                         style="width:100%;height:100%;object-fit:cover;" loading="lazy">
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php /* 액션 바 (좋아요 / 삭제) */ ?>
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-top:14px;padding-top:12px;border-top:1px solid #f1f3f5;">
                        <button class="psc-like-btn"
                                data-review-id="<?php echo (int) $rv->id; ?>"
                                data-nonce="<?php echo wp_create_nonce( 'psc_toggle_like_' . $rv->id ); ?>"
                                style="display:flex;align-items:center;gap:6px;background:none;border:none;
                                       cursor:pointer;color:<?php echo $liked ? '#e53e3e' : '#6b7684'; ?>;
                                       font-size:13px;padding:0;">
                            <svg width="16" height="16" viewBox="0 0 24 24"
                                 fill="<?php echo $liked ? '#e53e3e' : 'none'; ?>"
                                 stroke="<?php echo $liked ? '#e53e3e' : '#6b7684'; ?>" stroke-width="2">
                                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                            </svg>
                            <span class="psc-like-count"><?php echo (int) $rv->likes; ?></span>
                        </button>

                        <?php if ( $is_mine || $is_admin ) : ?>
                            <button class="psc-delete-btn"
                                    data-review-id="<?php echo (int) $rv->id; ?>"
                                    data-nonce="<?php echo wp_create_nonce( 'psc_delete_review_' . $rv->id ); ?>"
                                    style="background:none;border:none;color:#adb5bd;font-size:12px;cursor:pointer;">
                                삭제
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>

    <?php
    if ( function_exists( 'psc_review_script' ) ) psc_review_script();
    get_footer();
}
