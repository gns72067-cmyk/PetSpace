<?php
/**
 * 리뷰 목록 페이지 라우팅 및 전체 출력
 * URL: /reviews/?store_id=123
 */
defined('ABSPATH') || exit;

/* ── 쿼리 변수 등록 ──────────────────────── */
add_filter('query_vars', function (array $vars): array {
    $vars[] = 'psc_store_reviews';
    return $vars;
});

/* ── 라우팅 ──────────────────────────────── */
add_action('template_redirect', function (): void {
    if (!is_page('reviews')) return;

    $store_id = (int)($_GET['store_id'] ?? 0);
    if (!$store_id) return;

    $store = get_post($store_id);
    if (!$store || $store->post_type !== 'store') return;

    psc_render_reviews_page($store_id);
    exit;
});

/* ── 전체 페이지 출력 ────────────────────── */
function psc_render_reviews_page(int $store_id): void {
    $store     = get_post($store_id);
    $store_name = $store ? get_the_title($store_id) : '';

    get_header();
    ?>
    <div class="rv-page">

        <!-- 스토어 타이틀 -->
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;">
            <a href="<?= esc_url(get_permalink($store_id)) ?>"
               style="font-size:14px;color:var(--rv-text3);text-decoration:none;">
                ← <?= esc_html($store_name) ?>
            </a>
        </div>

        <h1 style="font-size:22px;font-weight:800;color:var(--rv-text1);
                   font-family:var(--rv-font);margin-bottom:24px;">
            방문 후기
        </h1>

        <!-- 리뷰 목록 -->
        <?php psc_render_review_list($store_id); ?>

        <!-- 리뷰 작성 폼 (바텀시트) -->
        <?php psc_render_review_form_shell($store_id); ?>

    </div>
    <?php
    get_footer();
}
