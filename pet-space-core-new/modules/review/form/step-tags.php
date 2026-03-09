<?php
/**
 * STEP 4: 키워드 태그 선택 (최대 3개)
 */
defined('ABSPATH') || exit;

function psc_render_step_tags(int $store_id): void {
    $tags = psc_get_review_tags($store_id);
    ?>
    <div class="rv-step" data-step="4">
        <p class="rv-step__label">STEP 4 / 6</p>
        <h2 class="rv-step__title">어떤 점이 좋았나요?</h2>

        <div class="rv-tags-grid">
            <?php foreach ($tags as $key => $label): ?>
                <span
                    class="rv-tag-chip"
                    data-tag="<?= esc_attr($key) ?>"
                    role="checkbox"
                    aria-checked="false"
                    tabindex="0"
                ><?= esc_html($label) ?></span>
            <?php endforeach; ?>
        </div>
        <p class="rv-tags-hint">최대 3개 선택 가능 (선택 사항)</p>
    </div>
    <?php
}
