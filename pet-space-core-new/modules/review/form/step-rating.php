<?php
/**
 * STEP 3: 별점 선택
 */
defined('ABSPATH') || exit;

function psc_render_step_rating(): void {
    ?>
    <div class="rv-step" data-step="3">
        <p class="rv-step__label">STEP 3 / 6</p>
        <h2 class="rv-step__title">전반적으로 어떠셨나요?</h2>

        <div class="rv-rating-wrap">
            <div class="rv-rating-stars" role="radiogroup" aria-label="별점 선택">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <span
                        class="rv-rating-star"
                        data-value="<?= $i ?>"
                        role="radio"
                        aria-label="<?= $i ?>점"
                        aria-checked="false"
                        tabindex="0"
                    >★</span>
                <?php endfor; ?>
            </div>
            <div id="rv-rating-label" class="rv-rating-label"></div>
        </div>
    </div>
    <?php
}
