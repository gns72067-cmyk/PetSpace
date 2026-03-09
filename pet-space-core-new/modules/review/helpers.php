<?php
/**
 * PetSpace Review — 공통 헬퍼 함수
 */
defined( 'ABSPATH' ) || exit;

/* ════════════════════════════════════════
   이름 마스킹
════════════════════════════════════════ */
function psc_mask_name( string $name ): string {
    $chars = mb_str_split( $name );
    $len   = count( $chars );
    if ( $len <= 1 ) return $name;
    if ( $len === 2 ) return $chars[0] . '*';
    return $chars[0] . str_repeat( '*', $len - 2 ) . $chars[ $len - 1 ];
}

/* ════════════════════════════════════════
   별점 렌더링
════════════════════════════════════════ */
function psc_render_stars( float $rating, bool $interactive = false ): string {
    $html = '<span class="psc-rv-stars-display">';
    for ( $i = 1; $i <= 5; $i++ ) {
        $fill = $i <= $rating ? 'var(--psc-yellow)' : 'var(--psc-border)';
        $html .= sprintf(
            '<svg %s width="16" height="16" viewBox="0 0 24 24" fill="%s">
                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
            </svg>',
            $interactive ? 'data-star="' . $i . '"' : '',
            esc_attr( $fill )
        );
    }
    $html .= '</span>';
    return $html;
}

/* ════════════════════════════════════════
   리뷰 단건 조회
════════════════════════════════════════ */
function psc_get_review( int $review_id ): ?object {
    global $wpdb;
    return $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM " . PSC_REVIEW_TABLE . "
         WHERE id = %d AND status != %s",
        $review_id, PSC_REVIEW_STATUS_DELETED
    ) );
}

/* ════════════════════════════════════════
   리뷰 목록 조회
════════════════════════════════════════ */
function psc_get_reviews(
    int    $store_id,
    string $orderby = 'latest',
    int    $limit   = 10,
    int    $offset  = 0
): array {
    global $wpdb;

    $order_sql = match( $orderby ) {
        'likes'  => 'r.likes DESC, r.created_at DESC',
        'rating' => 'r.rating DESC, r.created_at DESC',
        default  => 'r.created_at DESC',
    };

    return $wpdb->get_results( $wpdb->prepare(
        "SELECT r.*,
                u.display_name,
                COUNT(DISTINCT ri.id) AS image_count
         FROM   " . PSC_REVIEW_TABLE . " r
         JOIN   {$wpdb->users} u ON u.ID = r.user_id
         LEFT JOIN " . PSC_REVIEW_IMAGE_TABLE . " ri ON ri.review_id = r.id
         WHERE  r.store_id = %d
           AND  r.status   = %s
         GROUP  BY r.id
         ORDER  BY {$order_sql}
         LIMIT  %d OFFSET %d",
        $store_id,
        PSC_REVIEW_STATUS_PUBLISHED,
        $limit,
        $offset
    ) );
}

/* ════════════════════════════════════════
   리뷰 요약 (별점 분포)
════════════════════════════════════════ */
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
         WHERE store_id = %d AND status = %s",
        $store_id,
        PSC_REVIEW_STATUS_PUBLISHED
    ) );

    if ( $row ) {
        $row->distribution = [
            5 => (int) $row->star5,
            4 => (int) $row->star4,
            3 => (int) $row->star3,
            2 => (int) $row->star2,
            1 => (int) $row->star1,
        ];
        return $row;
    }

    return (object) [
        'total'        => 0,
        'avg_rating'   => 0,
        'distribution' => [ 5=>0, 4=>0, 3=>0, 2=>0, 1=>0 ],
    ];
}

/* ════════════════════════════════════════
   리뷰 이미지 조회
════════════════════════════════════════ */
function psc_get_review_images( int $review_id ): array {
    global $wpdb;
    return $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM " . PSC_REVIEW_IMAGE_TABLE . "
         WHERE review_id = %d ORDER BY sort_order ASC",
        $review_id
    ) );
}

/* ════════════════════════════════════════
   좋아요 여부 확인
════════════════════════════════════════ */
function psc_user_liked_review( int $review_id, int $user_id ): bool {
    global $wpdb;
    return (bool) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM " . PSC_REVIEW_LIKE_TABLE . "
         WHERE review_id = %d AND user_id = %d",
        $review_id, $user_id
    ) );
}

/* ════════════════════════════════════════
   리뷰 작성 가능 여부
════════════════════════════════════════ */
function psc_can_write_review( int $store_id, int $user_id = 0 ): bool|string {
    if ( ! $user_id ) $user_id = get_current_user_id();
    if ( ! $user_id ) return false;

    global $wpdb;
    $exists = $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM " . PSC_REVIEW_TABLE . "
         WHERE store_id = %d AND user_id = %d AND status != %s",
        $store_id, $user_id, PSC_REVIEW_STATUS_DELETED
    ) );

    return $exists ? 'duplicate' : true;
}

/* ════════════════════════════════════════
   URL 헬퍼
════════════════════════════════════════ */
function psc_review_write_url( int $store_id ): string {
    return home_url( '/review-write/?store_id=' . $store_id );
}

function psc_review_list_url( int $store_id ): string {
    return home_url( '/reviews/?store_id=' . $store_id );
}

/* ════════════════════════════════════════
   매장 인증 설정 헬퍼
════════════════════════════════════════ */
function psc_get_store_verify_type( int $store_id ): string {
    $type = get_post_meta( $store_id, 'review_verify_type', true );
    return in_array( $type, [ PSC_VERIFY_RECEIPT, PSC_VERIFY_GPS, PSC_VERIFY_BOTH ], true )
        ? $type
        : PSC_VERIFY_RECEIPT;
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

/* ════════════════════════════════════════
   신고 저장
════════════════════════════════════════ */
function psc_save_report(
    int    $review_id,
    int    $reporter_id,
    int    $store_id,
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

/* ════════════════════════════════════════
   태그 정의
════════════════════════════════════════ */
function psc_get_review_tags( int $store_id ): array {
    $common = [
        [ 'id' => 'kind',     'emoji' => '😊', 'label' => '친절해요' ],
        [ 'id' => 'clean',    'emoji' => '✨', 'label' => '매장이 깔끔해요' ],
        [ 'id' => 'pet',      'emoji' => '🐾', 'label' => '반려동물 친화적이에요' ],
        [ 'id' => 'price',    'emoji' => '💰', 'label' => '가격이 합리적이에요' ],
        [ 'id' => 'revisit',  'emoji' => '🔄', 'label' => '재방문 의사 있어요' ],
        [ 'id' => 'parking',  'emoji' => '🅿️', 'label' => '주차하기 편해요' ],
    ];

    $category_tags = [
        'hospital' => [
            [ 'id' => 'detail',  'emoji' => '💉', 'label' => '설명이 자세해요' ],
            [ 'id' => 'careful', 'emoji' => '🔬', 'label' => '꼼꼼하게 봐줘요' ],
            [ 'id' => 'comfort', 'emoji' => '😌', 'label' => '아이가 편안해해요' ],
        ],
        'grooming' => [
            [ 'id' => 'skill',   'emoji' => '✂️', 'label' => '솜씨가 좋아요' ],
            [ 'id' => 'custom',  'emoji' => '🎨', 'label' => '원하는 대로 해줘요' ],
            [ 'id' => 'petlove', 'emoji' => '🛁', 'label' => '아이가 좋아해요' ],
        ],
        'cafe' => [
            [ 'id' => 'mood',    'emoji' => '☕', 'label' => '분위기가 좋아요' ],
            [ 'id' => 'snack',   'emoji' => '🍖', 'label' => '간식이 맛있어요' ],
            [ 'id' => 'space',   'emoji' => '🏠', 'label' => '공간이 넓어요' ],
        ],
        'shop' => [
            [ 'id' => 'variety', 'emoji' => '📦', 'label' => '제품이 다양해요' ],
            [ 'id' => 'guide',   'emoji' => '🏷️', 'label' => '설명이 친절해요' ],
            [ 'id' => 'gift',    'emoji' => '🎁', 'label' => '포장이 예뻐요' ],
        ],
    ];

    $category = get_post_meta( $store_id, 'store_category', true ) ?: '';
    return array_merge( $common, $category_tags[ $category ] ?? [] );
}
