<?php
/**
 * STEP 5: 본문 작성
 */
defined('ABSPATH') || exit;

function psc_render_step_content(): void {
    $min = 10;
    $max = 1000;
    ?>
    <div class="rv-step" data-step="5">
        <p class="rv-step__label">STEP 5 / 6</p>
        <h2 class="rv-step__title">방문 후기를 남겨주세요</h2>

        <div class="rv-textarea-wrap">
            <textarea
                id="rv-content"
                class="rv-textarea"
                placeholder="다른 방문객에게 도움이 되는 솔직한 후기를 남겨주세요. (최소 <?= $min ?>자)"
                maxlength="<?= $max ?>"
                aria-label="리뷰 내용"
            ></textarea>
        </div>
        <p id="rv-char-count" class="rv-char-count">0 / <?= $max ?></p>

        <ul style="margin-top:14px;padding-left:18px;font-size:12px;color:var(--rv-text3);font-family:var(--rv-font);line-height:1.8;">
            <li>최소 <?= $min ?>자 이상 작성해 주세요</li>
            <li>욕설·비방·광고성 내용은 삭제될 수 있어요</li>
            <li>개인정보(전화번호, 주소 등)는 포함하지 마세요</li>
        </ul>
    </div>
    <?php
}
