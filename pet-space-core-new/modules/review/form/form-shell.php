<?php
/**
 * 리뷰 폼 바텀시트 HTML 뼈대
 * psc_render_review_form_shell( int $store_id ) 호출
 */
defined('ABSPATH') || exit;

function psc_render_review_form_shell(int $store_id): void {
    if (!is_user_logged_in()) return;

    $can = psc_can_write_review($store_id, get_current_user_id());
    if ($can === 'duplicate') {
        // 이미 작성한 경우 버튼 비활성화 처리는 shortcode에서
        return;
    }
    ?>
    <!-- 오버레이 -->
    <div id="rv-overlay" class="rv-overlay" aria-hidden="true"></div>

    <!-- 바텀시트 -->
    <div id="rv-sheet" class="rv-sheet" role="dialog" aria-modal="true" aria-label="리뷰 작성">

        <!-- 드래그 핸들 -->
        <div class="rv-sheet__handle"></div>

        <!-- 헤더 -->
        <div class="rv-sheet__header">
            <span class="rv-sheet__title">리뷰 작성</span>
            <button id="rv-close-btn" class="rv-sheet__close" aria-label="닫기">✕</button>
        </div>

        <!-- 진행 표시줄 (6 스텝) -->
        <div class="rv-progress" role="progressbar" aria-valuemin="1" aria-valuemax="6">
            <?php for ($i = 1; $i <= 6; $i++): ?>
                <div class="rv-progress__bar <?= $i === 1 ? 'rv-progress__bar--active' : '' ?>"></div>
            <?php endfor; ?>
        </div>

        <!-- 스텝 컨테이너 -->
        <div class="rv-steps">
            <?php
            psc_render_step_verify($store_id);
            psc_render_step_date();
            psc_render_step_rating();
            psc_render_step_tags($store_id);
            psc_render_step_content();
            psc_render_step_photo();
            ?>
        </div>

        <!-- 하단 액션 바 -->
        <div class="rv-sheet__footer">
            <button id="rv-prev-btn" class="rv-btn rv-btn--prev" aria-label="이전" style="visibility:hidden;">←</button>
            <button id="rv-next-btn" class="rv-btn rv-btn--next" disabled>다음</button>
            <button id="rv-submit-btn" class="rv-btn rv-btn--submit" style="display:none;">등록하기</button>
        </div>

    </div><!-- /.rv-sheet -->
    <?php
}
