<?php
/**
 * 리뷰 관리자 페이지
 */
defined('ABSPATH') || exit;

/* ── 메뉴 등록 ───────────────────────────── */
add_action('admin_menu', function (): void {
    add_submenu_page(
        'edit.php?post_type=store',
        '리뷰 관리',
        '리뷰 관리',
        'manage_options',
        'psc-reviews',
        'psc_render_review_admin_page'
    );
});

/* ── 관리자 페이지 렌더 ──────────────────── */
function psc_render_review_admin_page(): void {
    global $wpdb;

    /* 탭 */
    $tab = sanitize_key($_GET['tab'] ?? 'all');

    /* 필터 */
    $status   = $tab === 'reported' ? 'published' : ($tab === 'deleted' ? 'deleted' : 'published');
    $per_page = 20;
    $paged    = max(1, (int)($_GET['paged'] ?? 1));
    $offset   = ($paged - 1) * $per_page;

    /* 리뷰 목록 조회 */
    $where = "WHERE r.status = %s";
    $args  = [$status];

    if ($tab === 'reported') {
        $where .= " AND r.report_count > 0";
    }

    $total = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM " . PSC_REVIEW_TABLE . " r $where", ...$args
    ));

    $reviews = $wpdb->get_results($wpdb->prepare(
        "SELECT r.*, p.post_title as store_name
         FROM " . PSC_REVIEW_TABLE . " r
         LEFT JOIN {$wpdb->posts} p ON p.ID = r.store_id
         $where
         ORDER BY r.created_at DESC
         LIMIT %d OFFSET %d",
        ...array_merge($args, [$per_page, $offset])
    ));

    $total_pages = ceil($total / $per_page);
    ?>
    <div class="wrap">
        <h1>리뷰 관리</h1>

        <!-- 탭 -->
        <nav class="nav-tab-wrapper" style="margin-bottom:16px;">
            <a href="?post_type=store&page=psc-reviews&tab=all"
               class="nav-tab <?= $tab === 'all' ? 'nav-tab-active' : '' ?>">전체</a>
            <a href="?post_type=store&page=psc-reviews&tab=reported"
               class="nav-tab <?= $tab === 'reported' ? 'nav-tab-active' : '' ?>">신고된 리뷰</a>
            <a href="?post_type=store&page=psc-reviews&tab=deleted"
               class="nav-tab <?= $tab === 'deleted' ? 'nav-tab-active' : '' ?>">삭제된 리뷰</a>
        </nav>

        <p style="color:#666;margin-bottom:12px;">총 <?= number_format($total) ?>개</p>

        <!-- 테이블 -->
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th width="40">ID</th>
                    <th width="140">매장</th>
                    <th width="100">작성자</th>
                    <th width="60">별점</th>
                    <th>내용</th>
                    <th width="80">인증</th>
                    <th width="60">좋아요</th>
                    <th width="60">신고</th>
                    <th width="120">작성일</th>
                    <th width="100">관리</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($reviews)): ?>
                    <tr><td colspan="10" style="text-align:center;padding:24px;">리뷰가 없습니다.</td></tr>
                <?php else: ?>
                    <?php foreach ($reviews as $r):
                        $user = get_userdata($r->user_id);
                        $uname = $user ? esc_html($user->display_name) : '탈퇴회원';
                    ?>
                    <tr>
                        <td><?= (int)$r->id ?></td>
                        <td>
                            <a href="<?= esc_url(get_permalink($r->store_id)) ?>" target="_blank">
                                <?= esc_html($r->store_name ?? '-') ?>
                            </a>
                        </td>
                        <td><?= $uname ?></td>
                        <td><?= str_repeat('★', (int)$r->rating) ?></td>
                        <td style="max-width:300px;">
                            <span title="<?= esc_attr($r->content) ?>">
                                <?= esc_html(mb_substr($r->content, 0, 60)) . (mb_strlen($r->content) > 60 ? '…' : '') ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($r->verify_type === 'receipt'): ?>
                                🧾
                            <?php elseif ($r->verify_type === 'gps'): ?>
                                📍
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td><?= (int)$r->likes ?></td>
                        <td><?= (int)$r->report_count ?></td>
                        <td><?= esc_html(date_i18n('Y.m.d', strtotime($r->created_at))) ?></td>
                        <td>
                            <?php if ($r->status !== 'deleted'): ?>
                                <a href="<?= esc_url(wp_nonce_url(
                                    add_query_arg(['action' => 'psc_delete_review_admin', 'review_id' => $r->id]),
                                    'psc_admin_review_' . $r->id
                                )) ?>"
                                   onclick="return confirm('삭제할까요?')"
                                   style="color:#c00;">삭제</a>
                            <?php else: ?>
                                <span style="color:#999;">삭제됨</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- 페이지네이션 -->
        <?php if ($total_pages > 1): ?>
        <div style="margin-top:16px;">
            <?php echo paginate_links([
                'base'      => add_query_arg('paged', '%#%'),
                'format'    => '',
                'current'   => $paged,
                'total'     => $total_pages,
            ]); ?>
        </div>
        <?php endif; ?>
    </div>
    <?php
}

/* ── 관리자 삭제 액션 ────────────────────── */
add_action('admin_init', function (): void {
    if (($_GET['action'] ?? '') !== 'psc_delete_review_admin') return;
    if (!current_user_can('manage_options')) return;

    $review_id = (int)($_GET['review_id'] ?? 0);
    if (!$review_id) return;
    if (!check_admin_referer('psc_admin_review_' . $review_id)) return;

    global $wpdb;
    $wpdb->update(
        PSC_REVIEW_TABLE,
        ['status' => 'deleted', 'updated_at' => current_time('mysql')],
        ['id' => $review_id]
    );

    wp_redirect(remove_query_arg(['action', 'review_id', '_wpnonce']));
    exit;
});
