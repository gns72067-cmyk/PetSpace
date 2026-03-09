<?php
/**
 * 리뷰 숏코드 등록
 *
 * [psc_review_list store_id="123"]
 * [psc_review_form store_id="123"]
 * [psc_review_summary store_id="123"]
 */
defined('ABSPATH') || exit;

/* ── 리뷰 목록 ───────────────────────────── */
add_shortcode('psc_review_list', function (array $atts): string {
    $atts = shortcode_atts(['store_id' => 0], $atts, 'psc_review_list');
    $store_id = (int)$atts['store_id'];
    if (!$store_id) return '<p style="color:red;">store_id가 필요합니다.</p>';

    ob_start();
    psc_render_review_list($store_id);
    return ob_get_clean();
});

/* ── 리뷰 작성 폼 (바텀시트 트리거 포함) ── */
add_shortcode('psc_review_form', function (array $atts): string {
    $atts = shortcode_atts(['store_id' => 0], $atts, 'psc_review_form');
    $store_id = (int)$atts['store_id'];
    if (!$store_id) return '';

    ob_start();
    psc_render_review_form_shell($store_id);
    return ob_get_clean();
});

/* ── 리뷰 요약 (별점 + 개수만) ──────────── */
add_shortcode('psc_review_summary', function (array $atts): string {
    $atts = shortcode_atts(['store_id' => 0], $atts, 'psc_review_summary');
    $store_id = (int)$atts['store_id'];
    if (!$store_id) return '';

    $summary = psc_get_review_summary($store_id);
    $avg     = round((float)($summary['avg_rating'] ?? 0), 1);
    $total   = (int)($summary['total'] ?? 0);

    ob_start();
    ?>
    <div class="rv-summary-inline" style="display:inline-flex;align-items:center;gap:6px;">
        <span class="rv-stars rv-stars--sm"><?= psc_render_stars($avg) ?></span>
        <strong style="font-size:14px;color:var(--rv-text1);"><?= number_format($avg, 1) ?></strong>
        <span style="font-size:13px;color:var(--rv-text3);">(<?= number_format($total) ?>)</span>
    </div>
    <?php
    return ob_get_clean();
});
