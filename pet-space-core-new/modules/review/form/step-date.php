<?php
/**
 * STEP 2: 방문 날짜·시간 선택
 */
defined('ABSPATH') || exit;

function psc_render_step_date(): void {
    ?>
    <div class="rv-step" data-step="2">
        <p class="rv-step__label">STEP 2 / 6</p>
        <h2 class="rv-step__title">언제 방문하셨나요?</h2>

        <div class="rv-date-picker">
            <label for="rv-visited-at">방문 날짜 및 시간</label>
            <input
                type="datetime-local"
                id="rv-visited-at"
                name="visited_at"
                class="rv-date-input"
                required
            >
            <p style="font-size:12px;color:var(--rv-text3);font-family:var(--rv-font);margin-top:4px;">
                * 영수증 날짜와 일치하면 더 정확한 인증이 돼요
            </p>
        </div>
    </div>
    <?php
}
