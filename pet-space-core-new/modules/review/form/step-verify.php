<?php
/**
 * STEP 1: 방문 인증 (영수증 OCR / GPS)
 */
defined('ABSPATH') || exit;

function psc_render_step_verify(int $store_id): void {
    $verify_type = psc_get_store_verify_type($store_id); // 'both' | 'receipt' | 'gps'
    ?>
    <div class="rv-step rv-step--active" data-step="1">
        <p class="rv-step__label">STEP 1 / 6</p>
        <h2 class="rv-step__title">방문을 인증해 주세요</h2>

        <!-- 인증 방식 탭 -->
        <div class="rv-verify-tabs">
            <?php if (in_array($verify_type, ['both', 'receipt'])): ?>
            <div class="rv-verify-tab rv-verify-tab--active" data-type="receipt">
                <div class="rv-verify-tab__icon">🧾</div>
                <div class="rv-verify-tab__name">영수증 인증</div>
                <div class="rv-verify-tab__desc">영수증을 촬영하거나<br>업로드해 주세요</div>
            </div>
            <?php endif; ?>

            <?php if (in_array($verify_type, ['both', 'gps'])): ?>
            <div class="rv-verify-tab <?= $verify_type === 'gps' ? 'rv-verify-tab--active' : '' ?>" data-type="gps">
                <div class="rv-verify-tab__icon">📍</div>
                <div class="rv-verify-tab__name">GPS 인증</div>
                <div class="rv-verify-tab__desc">매장 근처에서<br>위치를 인증해 주세요</div>
            </div>
            <?php endif; ?>
        </div>

        <!-- 영수증 패널 -->
        <?php if (in_array($verify_type, ['both', 'receipt'])): ?>
        <div class="rv-verify-panel rv-verify-panel--active" data-type="receipt">
            <input type="file" id="rv-receipt-file" accept="image/*" capture="environment" style="display:none;">
            <div id="rv-receipt-upload" class="rv-upload-box">
                <div class="rv-upload-box__icon">📷</div>
                <div class="rv-upload-box__text">클릭하거나 사진을 드래그하세요</div>
                <div class="rv-upload-box__hint">JPEG, PNG, WEBP, HEIC · 최대 10MB</div>
            </div>
            <div id="rv-ocr-result" class="rv-ocr-result" style="display:none;"></div>
        </div>
        <?php endif; ?>

        <!-- GPS 패널 -->
        <?php if (in_array($verify_type, ['both', 'gps'])): ?>
        <div class="rv-verify-panel <?= $verify_type === 'gps' ? 'rv-verify-panel--active' : '' ?>" data-type="gps">
            <div class="rv-gps-box">
                <div class="rv-gps-box__icon">📍</div>
                <div class="rv-gps-box__text">
                    현재 위치를 확인하여 매장 방문 여부를 인증합니다.<br>
                    위치 권한을 허용해 주세요.
                </div>
                <button id="rv-gps-btn" class="rv-btn rv-btn--next" style="max-width:200px;margin:0 auto;">
                    현재 위치 인증하기
                </button>
            </div>
            <div id="rv-gps-result" class="rv-gps-result" style="display:none;"></div>
        </div>
        <?php endif; ?>

        <!-- 숨김 필드 -->
        <input type="hidden" id="rv-store-id" value="<?= esc_attr($store_id) ?>">
    </div>
    <?php
}
