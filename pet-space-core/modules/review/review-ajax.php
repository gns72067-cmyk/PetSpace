<?php
/**
 * Module: Review
 * File: modules/review/review-ajax.php
 */

defined( 'ABSPATH' ) || exit;

/* ═══════════════════════════════════════════
   1. 리뷰 저장
═══════════════════════════════════════════ */
function psc_ajax_save_review(): void {
    global $wpdb;

    if ( ! check_ajax_referer( 'psc_review_nonce', 'nonce', false ) ) {
        wp_send_json_error( [ 'message' => '보안 검증 실패' ], 403 );
    }

    $user_id = get_current_user_id();
    if ( ! $user_id ) {
        wp_send_json_error( [ 'message' => '로그인이 필요합니다' ], 401 );
    }

    $store_id    = (int)   ( $_POST['store_id']    ?? 0 );
    $rating      = (int)   ( $_POST['rating']      ?? 0 );
    $content     = sanitize_textarea_field( $_POST['content']     ?? '' );
    $verify_type = sanitize_key(            $_POST['verify_type'] ?? '' );
    $receipt_id  = (int)   ( $_POST['receipt_id']  ?? 0 );
    $gps_lat     = (float) ( $_POST['gps_lat']     ?? 0 );
    $gps_lng     = (float) ( $_POST['gps_lng']     ?? 0 );

    // 태그 파싱
    $tags_raw  = sanitize_text_field( $_POST['tags'] ?? '[]' );
    $tags      = json_decode( $tags_raw, true );
    $tags      = is_array( $tags ) ? array_map( 'sanitize_key', $tags ) : [];
    $tags_json = ! empty( $tags ) ? wp_json_encode( $tags ) : null;

    if ( ! $store_id || get_post_type( $store_id ) !== 'store' ) {
        wp_send_json_error( [ 'message' => '유효하지 않은 매장입니다' ] );
    }
    if ( $rating < 1 || $rating > 5 ) {
        wp_send_json_error( [ 'message' => '별점을 선택해주세요 (1~5)' ] );
    }
    if ( mb_strlen( $content ) < 10 ) {
        wp_send_json_error( [ 'message' => '리뷰는 최소 10자 이상 작성해주세요' ] );
    }
    if ( mb_strlen( $content ) > 1000 ) {
        wp_send_json_error( [ 'message' => '리뷰는 최대 1000자까지 작성 가능합니다' ] );
    }

    $verify_setting = psc_get_store_verify_type( $store_id );
    $allowed_types  = $verify_setting === 'both' ? [ 'receipt', 'gps' ] : [ $verify_setting ];
    if ( ! in_array( $verify_type, $allowed_types, true ) ) {
        wp_send_json_error( [ 'message' => '방문 인증이 필요합니다' ] );
    }

    $can = psc_can_write_review( $store_id, $user_id );
    if ( $can === 'duplicate' ) {
        wp_send_json_error( [ 'message' => '이미 리뷰를 작성하셨습니다' ] );
    }
    if ( $can !== true ) {
        wp_send_json_error( [ 'message' => '로그인이 필요합니다' ] );
    }

    $data   = [
        'store_id'         => $store_id,
        'user_id'          => $user_id,
        'rating'           => $rating,
        'content'          => $content,
        'tags'             => $tags_json,
        'status'           => 'pending',
        'verify_type'      => $verify_type,
        'receipt_verified' => $verify_type === 'receipt' ? 1 : 0,
    ];
    $format = [ '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%d' ];

    if ( $verify_type === 'receipt' && $receipt_id ) {
        $data['receipt_image_id'] = $receipt_id;
        $format[] = '%d';
    }
    if ( $verify_type === 'gps' && $gps_lat && $gps_lng ) {
        $data['gps_lat'] = $gps_lat;
        $data['gps_lng'] = $gps_lng;
        $format[] = '%f';
        $format[] = '%f';
    }

    $inserted = $wpdb->insert( PSC_REVIEW_TABLE, $data, $format );
    if ( ! $inserted ) {
        wp_send_json_error( [ 'message' => '리뷰 저장 중 오류가 발생했습니다' ] );
    }

    $review_id = (int) $wpdb->insert_id;

    $raw_ids   = $_POST['image_ids'] ?? [];
    $image_ids = array_filter( array_map( 'intval',
        is_array( $raw_ids ) ? $raw_ids : explode( ',', (string) $raw_ids )
    ) );
    foreach ( $image_ids as $order => $att_id ) {
        $url = wp_get_attachment_url( $att_id );
        if ( ! $url ) continue;
        $wpdb->insert(
            PSC_REVIEW_IMAGE_TABLE,
            [
                'review_id'     => $review_id,
                'attachment_id' => $att_id,
                'url'           => $url,
                'sort_order'    => $order,
            ],
            [ '%d', '%d', '%s', '%d' ]
        );
    }

    psc_notify_admin_new_review( $review_id, $store_id );

    wp_send_json_success( [
        'message'   => '리뷰가 등록되었습니다! 검토 후 게시될 예정이에요 🐾',
        'review_id' => $review_id,
        'redirect'  => psc_review_list_url( $store_id ),
    ] );
}
add_action( 'wp_ajax_psc_save_review', 'psc_ajax_save_review' );

/* ═══════════════════════════════════════════
   2. 영수증 OCR 검증
═══════════════════════════════════════════ */
function psc_ajax_verify_receipt(): void {

    if ( ! check_ajax_referer( 'psc_review_nonce', 'nonce', false ) ) {
        wp_send_json_error( [ 'message' => '보안 검증 실패' ], 403 );
    }

    $user_id  = get_current_user_id();
    $store_id = (int) ( $_POST['store_id']   ?? 0 );
    $att_id   = (int) ( $_POST['receipt_id'] ?? 0 );

    if ( ! $user_id ) {
        wp_send_json_error( [ 'message' => '로그인이 필요합니다' ], 401 );
    }
    if ( ! $store_id ) {
        wp_send_json_error( [ 'message' => '매장 정보가 없습니다' ] );
    }
    if ( ! $att_id ) {
        wp_send_json_error( [ 'message' => '영수증 이미지가 없습니다' ] );
    }

    $image_url = wp_get_attachment_url( $att_id );
    if ( ! $image_url ) {
        wp_send_json_error( [ 'message' => '이미지를 찾을 수 없습니다' ] );
    }

    error_log( '[PSC OCR] image_url: ' . $image_url );

    $ocr_result = psc_ocr_extract( $image_url );

    error_log( '[PSC OCR] result: ' . json_encode( $ocr_result, JSON_UNESCAPED_UNICODE ) );

    if ( ! $ocr_result['success'] ) {
        wp_send_json_error( [
            'message' => '영수증을 인식할 수 없습니다. 다시 촬영해주세요.',
            'fields'  => [],
        ] );
    }

    $verify = psc_ocr_verify_store( $ocr_result['parsed'], $store_id );

    if ( $verify['verified'] ) {
        wp_send_json_success( [
            'verified' => true,
            'score'    => $verify['score'],
            'fields'   => $verify['fields'],
            'message'  => '✅ 영수증 인증이 완료되었습니다!',
        ] );
    } else {
        wp_send_json_error( [
            'verified' => false,
            'score'    => $verify['score'],
            'fields'   => $verify['fields'],
            'message'  => '매장 정보가 일치하지 않아요. 다른 영수증을 사용해주세요.',
        ] );
    }
}
add_action( 'wp_ajax_psc_verify_receipt', 'psc_ajax_verify_receipt' );

/* ═══════════════════════════════════════════
   3. GPS 인증 검증
═══════════════════════════════════════════ */
function psc_ajax_verify_gps(): void {

    if ( ! check_ajax_referer( 'psc_review_nonce', 'nonce', false ) ) {
        wp_send_json_error( [ 'message' => '보안 검증 실패' ], 403 );
    }

    $user_id  = get_current_user_id();
    $store_id = (int)   ( $_POST['store_id'] ?? 0 );
    $lat      = (float) ( $_POST['lat']      ?? 0 );
    $lng      = (float) ( $_POST['lng']      ?? 0 );

    if ( ! $user_id ) {
        wp_send_json_error( [ 'message' => '로그인이 필요합니다' ], 401 );
    }
    if ( ! $store_id || ! $lat || ! $lng ) {
        wp_send_json_error( [ 'message' => '위치 정보가 없습니다' ] );
    }

    $result = psc_gps_verify_store( $lat, $lng, $store_id );

    if ( $result['verified'] ) {
        wp_send_json_success( [
            'verified' => true,
            'distance' => $result['distance'],
            'radius'   => $result['radius'],
            'message'  => $result['message'],
        ] );
    } else {
        wp_send_json_error( [
            'verified' => false,
            'distance' => $result['distance'] ?? 0,
            'radius'   => $result['radius']   ?? 0,
            'message'  => $result['message'],
        ] );
    }
}
add_action( 'wp_ajax_psc_verify_gps', 'psc_ajax_verify_gps' );
add_action( 'wp_ajax_nopriv_psc_verify_gps', 'psc_ajax_verify_gps' );

/* ═══════════════════════════════════════════
   4. 좋아요 토글
═══════════════════════════════════════════ */
function psc_ajax_toggle_like(): void {
    global $wpdb;

    if ( ! check_ajax_referer( 'psc_toggle_like', 'nonce', false ) ) {
        wp_send_json_error( [ 'message' => '보안 검증 실패' ], 403 );
    }

    $user_id   = get_current_user_id();
    $review_id = (int) ( $_POST['review_id'] ?? 0 );

    if ( ! $user_id ) {
        wp_send_json_error( [ 'message' => '로그인이 필요합니다', 'login_required' => true ], 401 );
    }
    if ( ! $review_id ) {
        wp_send_json_error( [ 'message' => '유효하지 않은 리뷰입니다' ] );
    }

    $already = psc_user_liked_review( $review_id, $user_id );

    if ( $already ) {
        $wpdb->delete(
            PSC_REVIEW_LIKE_TABLE,
            [ 'review_id' => $review_id, 'user_id' => $user_id ],
            [ '%d', '%d' ]
        );
        $wpdb->query( $wpdb->prepare(
            "UPDATE " . PSC_REVIEW_TABLE . " SET likes = GREATEST(likes - 1, 0) WHERE id = %d",
            $review_id
        ) );
        $liked = false;
    } else {
        $wpdb->insert(
            PSC_REVIEW_LIKE_TABLE,
            [ 'review_id' => $review_id, 'user_id' => $user_id ],
            [ '%d', '%d' ]
        );
        $wpdb->query( $wpdb->prepare(
            "UPDATE " . PSC_REVIEW_TABLE . " SET likes = likes + 1 WHERE id = %d",
            $review_id
        ) );
        $liked = true;
    }

    $new_count = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT likes FROM " . PSC_REVIEW_TABLE . " WHERE id = %d",
        $review_id
    ) );

    wp_send_json_success( [ 'liked' => $liked, 'count' => $new_count ] );
}
add_action( 'wp_ajax_psc_toggle_like', 'psc_ajax_toggle_like' );

/* ═══════════════════════════════════════════
   5. 이미지 업로드
═══════════════════════════════════════════ */
function psc_ajax_upload_review_image(): void {

    if ( ! check_ajax_referer( 'psc_review_nonce', 'nonce', false ) ) {
        wp_send_json_error( [ 'message' => '보안 검증 실패' ], 403 );
    }

    $user_id = get_current_user_id();
    if ( ! $user_id ) {
        wp_send_json_error( [ 'message' => '로그인이 필요합니다' ], 401 );
    }

    error_log( '[PSC UPLOAD] FILES: ' . json_encode( $_FILES, JSON_UNESCAPED_UNICODE ) );

    $file_key = isset( $_FILES['image'] ) ? 'image' : ( isset( $_FILES['file'] ) ? 'file' : '' );
    if ( ! $file_key ) {
        wp_send_json_error( [ 'message' => '파일이 없습니다' ] );
    }

    $file     = $_FILES[ $file_key ];
    $allowed  = [ 'image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/heic' ];
    $max_size = 10 * 1024 * 1024;

    error_log( '[PSC UPLOAD] file_key: ' . $file_key . ' type: ' . $file['type'] . ' size: ' . $file['size'] );

    if ( ! in_array( $file['type'], $allowed, true ) ) {
        wp_send_json_error( [ 'message' => 'JPG, PNG, WEBP, GIF, HEIC만 업로드 가능합니다' ] );
    }
    if ( $file['size'] > $max_size ) {
        wp_send_json_error( [ 'message' => '파일 크기는 10MB 이하여야 합니다' ] );
    }

    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';

    if ( $file_key !== 'file' ) {
        $_FILES['file'] = $_FILES[ $file_key ];
    }

    $att_id = media_handle_upload( 'file', 0 );

    error_log( '[PSC UPLOAD] att_id: ' . ( is_wp_error( $att_id ) ? $att_id->get_error_message() : $att_id ) );

    if ( is_wp_error( $att_id ) ) {
        wp_send_json_error( [ 'message' => '업로드 실패: ' . $att_id->get_error_message() ] );
    }

    wp_send_json_success( [
        'attachment_id' => $att_id,
        'url'           => wp_get_attachment_url( $att_id ),
        'thumbnail'     => wp_get_attachment_image_url( $att_id, 'thumbnail' ),
    ] );
}
add_action( 'wp_ajax_psc_upload_review_image', 'psc_ajax_upload_review_image' );

/* ═══════════════════════════════════════════
   6. 리뷰 삭제
═══════════════════════════════════════════ */
function psc_ajax_delete_review(): void {
    global $wpdb;

    if ( ! check_ajax_referer( 'psc_delete_review', 'nonce', false ) ) {
        wp_send_json_error( [ 'message' => '보안 검증 실패' ], 403 );
    }

    $user_id   = get_current_user_id();
    $review_id = (int) ( $_POST['review_id'] ?? 0 );

    if ( ! $user_id || ! $review_id ) {
        wp_send_json_error( [ 'message' => '잘못된 요청입니다' ] );
    }

    $review = psc_get_review( $review_id );
    if ( ! $review ) {
        wp_send_json_error( [ 'message' => '리뷰를 찾을 수 없습니다' ] );
    }

    $is_mine  = (int) $review->user_id === $user_id;
    $is_admin = current_user_can( 'manage_options' );

    if ( ! $is_mine && ! $is_admin ) {
        wp_send_json_error( [ 'message' => '삭제 권한이 없습니다' ], 403 );
    }

    $wpdb->update(
        PSC_REVIEW_TABLE,
        [ 'status' => 'deleted' ],
        [ 'id'     => $review_id ],
        [ '%s' ],
        [ '%d' ]
    );

    wp_send_json_success( [ 'message' => '리뷰가 삭제되었습니다' ] );
}
add_action( 'wp_ajax_psc_delete_review', 'psc_ajax_delete_review' );

/* ═══════════════════════════════════════════
   7. 리뷰 신고
═══════════════════════════════════════════ */
function psc_ajax_report_review(): void {

    if ( ! check_ajax_referer( 'psc_review_nonce', 'nonce', false ) ) {
        wp_send_json_error( [ 'message' => '보안 검증 실패' ], 403 );
    }

    $user_id   = get_current_user_id();
    $review_id = (int) ( $_POST['review_id'] ?? 0 );
    $store_id  = (int) ( $_POST['store_id']  ?? 0 );
    $reason    = sanitize_textarea_field( $_POST['reason'] ?? '' );

    if ( ! $user_id ) {
        wp_send_json_error( [ 'message' => '로그인이 필요합니다' ], 401 );
    }
    if ( ! $review_id || ! $store_id ) {
        wp_send_json_error( [ 'message' => '잘못된 요청입니다' ] );
    }
    if ( mb_strlen( $reason ) < 5 ) {
        wp_send_json_error( [ 'message' => '신고 사유를 5자 이상 입력해주세요' ] );
    }

    $store_owner = (int) get_post_field( 'post_author', $store_id );
    $is_vendor   = ( $user_id === $store_owner ) || current_user_can( 'manage_options' );
    if ( ! $is_vendor ) {
        wp_send_json_error( [ 'message' => '신고 권한이 없습니다' ], 403 );
    }

    $saved = psc_save_report( $review_id, $user_id, $store_id, $reason );
    if ( ! $saved ) {
        wp_send_json_error( [ 'message' => '이미 신고한 리뷰입니다' ] );
    }

    psc_notify_admin_report( $review_id, $store_id, $reason );

    wp_send_json_success( [ 'message' => '신고가 접수되었습니다. 관리자가 검토 후 처리할 예정입니다.' ] );
}
add_action( 'wp_ajax_psc_report_review', 'psc_ajax_report_review' );

/* ═══════════════════════════════════════════
   8. 관리자 알림 헬퍼
═══════════════════════════════════════════ */
function psc_notify_admin_new_review( int $review_id, int $store_id ): void {
    $admin_email = get_option( 'admin_email' );
    $store_name  = get_the_title( $store_id );
    $admin_url   = admin_url( 'admin.php?page=psc-reviews' );
    wp_mail(
        $admin_email,
        "[펫스페이스] 새 리뷰 승인 요청 — {$store_name}",
        "새 리뷰가 등록되어 승인 대기 중입니다.\n\n" .
        "매장: {$store_name}\n리뷰 ID: #{$review_id}\n\n" .
        "관리자 페이지:\n{$admin_url}"
    );
}

function psc_notify_admin_report( int $review_id, int $store_id, string $reason ): void {
    $admin_email = get_option( 'admin_email' );
    $store_name  = get_the_title( $store_id );
    $admin_url   = admin_url( 'admin.php?page=psc-reviews&tab=reports' );
    wp_mail(
        $admin_email,
        "[펫스페이스] 리뷰 신고 접수 — {$store_name}",
        "리뷰 신고가 접수되었습니다.\n\n" .
        "매장: {$store_name}\n리뷰 ID: #{$review_id}\n신고 사유: {$reason}\n\n" .
        "관리자 페이지:\n{$admin_url}"
    );
}
