<?php
/**
 * 리뷰 AJAX 핸들러
 */
defined('ABSPATH') || exit;

/* ── 공통 nonce 검증 ─────────────────────── */
function psc_verify_review_nonce(): void {
    if (!check_ajax_referer('psc_review_nonce', 'nonce', false)) {
        wp_send_json_error(['message' => '보안 검증 실패'], 403);
    }
}

/* ════════════════════════════════════════════
   리뷰 저장
   ════════════════════════════════════════════ */
add_action('wp_ajax_psc_save_review', function (): void {
    psc_verify_review_nonce();

    $uid      = get_current_user_id();
    $store_id = (int)($_POST['store_id'] ?? 0);
    $rating   = (int)($_POST['rating']   ?? 0);
    $content  = sanitize_textarea_field($_POST['content'] ?? '');
    $tags     = sanitize_text_field($_POST['tags']        ?? '[]');
    $visited  = sanitize_text_field($_POST['visited_at']  ?? '');
    $v_type   = sanitize_key($_POST['verify_type']        ?? '');
    $r_ver    = (int)($_POST['receipt_verified']          ?? 0);
    $gps_lat  = (float)($_POST['gps_lat']                 ?? 0);
    $gps_lng  = (float)($_POST['gps_lng']                 ?? 0);
    $img_ids  = json_decode(sanitize_text_field($_POST['image_ids'] ?? '[]'), true);

    /* 유효성 검사 */
    if (!$uid)                           wp_send_json_error(['message' => '로그인이 필요합니다.']);
    if (!$store_id)                      wp_send_json_error(['message' => '스토어 정보가 없습니다.']);
    if ($rating < 1 || $rating > 5)     wp_send_json_error(['message' => '별점을 선택해 주세요.']);
    if (mb_strlen($content) < 10)       wp_send_json_error(['message' => '리뷰는 최소 10자 이상 작성해 주세요.']);
    if (mb_strlen($content) > 1000)     wp_send_json_error(['message' => '리뷰는 1000자 이하로 작성해 주세요.']);

    $can = psc_can_write_review($store_id, $uid);
    if ($can === 'duplicate')            wp_send_json_error(['message' => '이미 리뷰를 작성하셨습니다.']);

    global $wpdb;

    $wpdb->insert(PSC_REVIEW_TABLE, [
        'store_id'         => $store_id,
        'user_id'          => $uid,
        'rating'           => $rating,
        'content'          => $content,
        'tags'             => $tags,
        'visited_at'       => $visited ?: null,
        'status'           => 'published',
        'verify_type'      => $v_type,
        'receipt_verified' => $r_ver,
        'gps_lat'          => $gps_lat ?: null,
        'gps_lng'          => $gps_lng ?: null,
        'created_at'       => current_time('mysql'),
        'updated_at'       => current_time('mysql'),
    ]);

    $review_id = (int)$wpdb->insert_id;
    if (!$review_id) wp_send_json_error(['message' => '저장 중 오류가 발생했습니다.']);

    /* 이미지 연결 */
    if (!empty($img_ids) && is_array($img_ids)) {
        foreach (array_slice($img_ids, 0, PSC_REVIEW_MAX_IMAGES) as $idx => $att_id) {
            $att_id = (int)$att_id;
            if (!$att_id) continue;
            $wpdb->insert(PSC_REVIEW_IMAGE_TABLE, [
                'review_id'     => $review_id,
                'attachment_id' => $att_id,
                'url'           => wp_get_attachment_url($att_id) ?: '',
                'sort_order'    => $idx,
                'created_at'    => current_time('mysql'),
            ]);
        }
    }

    /* 관리자 알림 */
    psc_notify_admin_new_review($review_id, $store_id, $uid);

    wp_send_json_success(['message' => '리뷰가 등록되었습니다.', 'review_id' => $review_id]);
});

/* ════════════════════════════════════════════
   리뷰 더 불러오기
   ════════════════════════════════════════════ */
add_action('wp_ajax_psc_load_reviews',        'psc_ajax_load_reviews');
add_action('wp_ajax_nopriv_psc_load_reviews', 'psc_ajax_load_reviews');

function psc_ajax_load_reviews(): void {
    psc_verify_review_nonce();

    $store_id = (int)($_POST['store_id'] ?? 0);
    $order    = sanitize_key($_POST['order'] ?? 'latest');
    $offset   = (int)($_POST['offset']        ?? 0);
    $limit    = min((int)($_POST['limit']      ?? 10), 20);

    $allowed_orders = ['latest', 'likes', 'rating_high', 'rating_low'];
    if (!in_array($order, $allowed_orders, true)) $order = 'latest';

    $reviews = psc_get_reviews($store_id, $order, $limit, $offset);
    $html    = array_map(fn($r) => ['html' => psc_render_review_card($r)], $reviews);

    wp_send_json_success(['reviews' => $html]);
}

/* ════════════════════════════════════════════
   좋아요 토글
   ════════════════════════════════════════════ */
add_action('wp_ajax_psc_toggle_like', function (): void {
    psc_verify_review_nonce();

    $uid       = get_current_user_id();
    $review_id = (int)($_POST['review_id'] ?? 0);
    if (!$uid)       wp_send_json_error(['message' => '로그인이 필요합니다.']);
    if (!$review_id) wp_send_json_error(['message' => '잘못된 요청입니다.']);

    global $wpdb;
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM " . PSC_REVIEW_LIKE_TABLE . " WHERE review_id=%d AND user_id=%d",
        $review_id, $uid
    ));

    if ($exists) {
        $wpdb->delete(PSC_REVIEW_LIKE_TABLE, ['review_id' => $review_id, 'user_id' => $uid]);
        $wpdb->query($wpdb->prepare("UPDATE " . PSC_REVIEW_TABLE . " SET likes=GREATEST(0,likes-1) WHERE id=%d", $review_id));
        $liked = false;
    } else {
        $wpdb->insert(PSC_REVIEW_LIKE_TABLE, ['review_id' => $review_id, 'user_id' => $uid, 'created_at' => current_time('mysql')]);
        $wpdb->query($wpdb->prepare("UPDATE " . PSC_REVIEW_TABLE . " SET likes=likes+1 WHERE id=%d", $review_id));
        $liked = true;
    }

    $count = (int)$wpdb->get_var($wpdb->prepare("SELECT likes FROM " . PSC_REVIEW_TABLE . " WHERE id=%d", $review_id));
    wp_send_json_success(['liked' => $liked, 'count' => $count]);
});

/* ════════════════════════════════════════════
   리뷰 삭제
   ════════════════════════════════════════════ */
add_action('wp_ajax_psc_delete_review', function (): void {
    psc_verify_review_nonce();

    $uid       = get_current_user_id();
    $review_id = (int)($_POST['review_id'] ?? 0);
    if (!$uid || !$review_id) wp_send_json_error(['message' => '잘못된 요청입니다.']);

    global $wpdb;
    $review = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . PSC_REVIEW_TABLE . " WHERE id=%d", $review_id));
    if (!$review) wp_send_json_error(['message' => '리뷰를 찾을 수 없습니다.']);

    if ((int)$review->user_id !== $uid && !current_user_can('manage_options')) {
        wp_send_json_error(['message' => '권한이 없습니다.']);
    }

    $wpdb->update(PSC_REVIEW_TABLE, ['status' => 'deleted', 'updated_at' => current_time('mysql')], ['id' => $review_id]);
    wp_send_json_success(['message' => '삭제되었습니다.']);
});

/* ════════════════════════════════════════════
   영수증 인증
   ════════════════════════════════════════════ */
add_action('wp_ajax_psc_verify_receipt', function (): void {
    psc_verify_review_nonce();

    if (!is_user_logged_in()) wp_send_json_error(['message' => '로그인이 필요합니다.']);

    $store_id = (int)($_POST['store_id'] ?? 0);
    if (!$store_id) wp_send_json_error(['message' => '스토어 정보가 없습니다.']);
    if (empty($_FILES['receipt'])) wp_send_json_error(['message' => '파일이 없습니다.']);

    $result = psc_ocr_extract($_FILES['receipt']['tmp_name']);
    if (!$result) wp_send_json_error(['message' => 'OCR 처리에 실패했습니다.']);

    $verify = psc_ocr_verify_store($result, $store_id);
    if ($verify['score'] < 30) {
        wp_send_json_error(['message' => '영수증이 해당 매장과 일치하지 않습니다. (점수: ' . $verify['score'] . ')']);
    }

    wp_send_json_success(array_merge($result, ['score' => $verify['score']]));
});

/* ════════════════════════════════════════════
   GPS 인증
   ════════════════════════════════════════════ */
add_action('wp_ajax_psc_verify_gps',        'psc_ajax_verify_gps_handler');
add_action('wp_ajax_nopriv_psc_verify_gps', 'psc_ajax_verify_gps_handler');

function psc_ajax_verify_gps_handler(): void {
    psc_verify_review_nonce();

    $store_id = (int)($_POST['store_id'] ?? 0);
    $lat      = (float)($_POST['lat']    ?? 0);
    $lng      = (float)($_POST['lng']    ?? 0);

    if (!$store_id || !$lat || !$lng) wp_send_json_error(['message' => '위치 정보가 없습니다.']);

    $result = psc_gps_verify_store($store_id, $lat, $lng);
    if ($result['verified']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result);
    }
}

/* ════════════════════════════════════════════
   사진 업로드
   ════════════════════════════════════════════ */
add_action('wp_ajax_psc_upload_review_image', function (): void {
    psc_verify_review_nonce();
    if (!is_user_logged_in()) wp_send_json_error(['message' => '로그인이 필요합니다.']);
    if (empty($_FILES['image']))  wp_send_json_error(['message' => '파일이 없습니다.']);

    $file    = $_FILES['image'];
    $allowed = ['image/jpeg','image/png','image/webp','image/gif','image/heic'];
    if (!in_array($file['type'], $allowed, true)) wp_send_json_error(['message' => '지원하지 않는 파일 형식입니다.']);
    if ($file['size'] > 10 * 1024 * 1024)         wp_send_json_error(['message' => '10MB 이하 파일만 업로드 가능합니다.']);

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';

    $att_id = media_handle_upload('image', 0);
    if (is_wp_error($att_id)) wp_send_json_error(['message' => $att_id->get_error_message()]);

    wp_send_json_success([
        'attachment_id' => $att_id,
        'url'           => wp_get_attachment_url($att_id),
        'thumbnail'     => wp_get_attachment_image_url($att_id, 'thumbnail'),
    ]);
});

/* ── 관리자 알림 헬퍼 ─────────────────────── */
function psc_notify_admin_new_review(int $review_id, int $store_id, int $user_id): void {
    $admin_email = get_option('admin_email');
    $store_name  = get_the_title($store_id);
    $user        = get_userdata($user_id);
    $user_name   = $user ? $user->display_name : '알 수 없음';
    $admin_url   = admin_url('admin.php?page=psc-reviews&review_id=' . $review_id);

    wp_mail(
        $admin_email,
        "[펫스페이스] 새 리뷰 등록: {$store_name}",
        "새 리뷰가 등록되었습니다.\n\n매장: {$store_name}\n작성자: {$user_name}\n관리: {$admin_url}"
    );
}
