<?php
/**
 * STEP 6: 사진 첨부 (선택, 최대 5장)
 */
defined('ABSPATH') || exit;

function psc_render_step_photo(): void {
    $max = 5;
    ?>
    <div class="rv-step" data-step="6">
        <p class="rv-step__label">STEP 6 / 6</p>
        <h2 class="rv-step__title">사진을 첨부해 주세요</h2>
        <p style="font-size:14px;color:var(--rv-text3);font-family:var(--rv-font);margin-bottom:20px;">
            선택 사항이에요. 최대 <?= $max ?>장까지 첨부할 수 있어요.
        </p>

        <!-- 숨김 파일 입력 -->
        <input
            type="file"
            id="rv-photo-file"
            accept="image/*"
            multiple
            style="display:none;"
        >

        <!-- 사진 그리드 -->
        <div id="rv-photos-grid" class="rv-photos-grid">
            <div id="rv-photo-add-btn" class="rv-photo-add" role="button" tabindex="0" aria-label="사진 추가">
                <span>＋</span>
                <span class="rv-photo-add__label">사진 추가</span>
            </div>
        </div>

        <p class="rv-photos-hint">JPEG, PNG, WEBP, HEIC · 장당 최대 10MB</p>
    </div>
    <?php
}
