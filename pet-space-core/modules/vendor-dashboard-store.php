<?php
/**
 * Module: Vendor Dashboard — Store Edit
 * 매장 기본정보 수정 페이지
 */

defined( 'ABSPATH' ) || exit;

function psc_dash_page_store( int $store_id, int $user_id ): void {

    if ( ! $store_id ) {
        echo '<div class="psc-dash-alert psc-dash-alert--warning">등록된 매장이 없습니다.</div>';
        return;
    }

    $message = '';
    $error   = '';

    /* ── 저장 처리 ── */
    if (
        $_SERVER['REQUEST_METHOD'] === 'POST'
        && isset( $_POST['psc_store_nonce'] )
        && wp_verify_nonce(
            sanitize_text_field( wp_unslash( $_POST['psc_store_nonce'] ) ),
            'psc_store_save_' . $store_id
        )
    ) {
        $result = psc_dash_save_store( $store_id );
        if ( is_wp_error( $result ) ) {
            $error = $result->get_error_message();
        } else {
            $message = '매장 정보가 저장되었습니다.';
        }
    }

    /* ── 현재 값 읽기 ── */
    $address        = get_field( 'store_address',        $store_id ) ?: '';
    $address_detail = get_field( 'store_address_detail', $store_id ) ?: '';
    $phone          = get_field( 'store_phone',          $store_id ) ?: '';
    $lat            = get_field( 'store_lat',            $store_id ) ?: '';
    $lng            = get_field( 'store_lng',            $store_id ) ?: '';
    $store_title    = get_the_title( $store_id );
    $thumb_id       = get_post_thumbnail_id( $store_id );
    $thumb_url      = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'medium' ) : '';

    /* ── 플랜 및 갤러리 ── */
    $is_premium  = function_exists( 'psc_store_is_premium' ) && psc_store_is_premium( $store_id );
    $max_images  = $is_premium ? 20 : 5;
    $raw_gallery = get_field( 'store_gallery_basic', $store_id );
    $gallery_items = is_array( $raw_gallery ) ? $raw_gallery : [];
    $current_count = count( $gallery_items );

    /* ── 운영시간 ── */
    $days_map = [
        'mon' => '월', 'tue' => '화', 'wed' => '수', 'thu' => '목',
        'fri' => '금', 'sat' => '토', 'sun' => '일',
    ];
    $hours_weekly = get_field( 'store_hours_weekly', $store_id );
    if ( ! is_array( $hours_weekly ) ) $hours_weekly = [];
    foreach ( array_keys( $days_map ) as $d ) {
        if ( ! isset( $hours_weekly[ $d ] ) ) {
            $hours_weekly[ $d ] = [
                'closed'      => false,
                'open'        => '09:00',
                'close'       => '18:00',
                'break_start' => '',
                'break_end'   => '',
                'note'        => '',
            ];
        }
    }

    /* ── 리뷰 인증 설정 ── */
    $cur_verify_type = get_post_meta( $store_id, 'review_verify_type', true );
    if ( ! in_array( $cur_verify_type, [ 'receipt', 'gps', 'both' ], true ) ) {
        $cur_verify_type = 'receipt';
    }

    $cur_gps_radius = (int) get_post_meta( $store_id, 'review_gps_radius', true );
    if ( $cur_gps_radius <= 0 ) {
        $cur_gps_radius = 300;
    }

    ?>
    <div class="psc-dash-section">

        <div class="psc-dash-page-header">
            <h1 class="psc-dash-page-title">🏪 매장 정보 수정</h1>
            <a href="<?php echo esc_url( get_permalink( $store_id ) ); ?>"
               target="_blank"
               class="psc-dash-btn psc-dash-btn--outline psc-dash-btn--sm">
               매장 보기 ↗
            </a>
        </div>

        <?php if ( $message ) : ?>
            <div class="psc-dash-alert psc-dash-alert--success"><?php echo esc_html( $message ); ?></div>
        <?php endif; ?>
        <?php if ( $error ) : ?>
            <div class="psc-dash-alert psc-dash-alert--error"><?php echo esc_html( $error ); ?></div>
        <?php endif; ?>

        <form method="post" class="psc-dash-form" enctype="multipart/form-data">
            <?php wp_nonce_field( 'psc_store_save_' . $store_id, 'psc_store_nonce' ); ?>

            <!-- ════ 대표 이미지 ════ -->
            <div class="psc-dash-card">
                <div class="psc-dash-card__header">
                    <h3 class="psc-dash-card__title">대표 이미지</h3>
                </div>
                <div class="psc-dash-thumb-wrap">
                    <?php if ( $thumb_url ) : ?>
                        <img src="<?php echo esc_url( $thumb_url ); ?>"
                             id="psc-thumb-preview"
                             class="psc-dash-thumb-preview" alt="대표 이미지">
                    <?php else : ?>
                        <div class="psc-dash-thumb-empty" id="psc-thumb-preview">이미지 없음</div>
                    <?php endif; ?>
                    <div class="psc-dash-thumb-actions">
                        <label for="psc_store_thumb"
                               class="psc-dash-btn psc-dash-btn--outline psc-dash-btn--sm"
                               style="cursor:pointer">이미지 변경</label>
                        <input type="file" id="psc_store_thumb" name="psc_store_thumb"
                               accept="image/*" style="display:none"
                               onchange="pscPreviewThumb(this)">
                        <?php if ( $thumb_id ) : ?>
                            <label style="display:flex;align-items:center;gap:6px;font-size:.85rem;cursor:pointer">
                                <input type="checkbox" name="psc_remove_thumb" value="1">
                                이미지 삭제
                            </label>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- ════ 갤러리 이미지 ════ -->
            <div class="psc-dash-card">
                <div class="psc-dash-card__header">
                    <h3 class="psc-dash-card__title">갤러리 이미지</h3>
                    <span class="psc-gallery-plan-badge <?php echo $is_premium ? 'premium' : 'free'; ?>">
                        <?php echo $is_premium ? '⭐ 프리미엄' : '무료'; ?> 플랜
                    </span>
                </div>

                <div class="psc-gallery-counter">
                    <span id="psc-gallery-count"><?php echo $current_count; ?></span>
                    / <?php echo $max_images; ?>장
                    <?php if ( ! $is_premium ) : ?>
                        <span class="psc-gallery-upgrade-hint">
                            &nbsp;· 프리미엄 업그레이드 시 최대 20장 등록 가능
                        </span>
                    <?php endif; ?>
                </div>

                <div class="psc-gallery-grid" id="psc-gallery-grid">
                    <?php foreach ( $gallery_items as $img ) :
                        $img_id  = is_array( $img ) ? (int) $img['ID'] : (int) $img;
                        $img_url = is_array( $img ) ? ( $img['sizes']['thumbnail'] ?? $img['url'] ?? '' )
                                                    : wp_get_attachment_image_url( $img_id, 'thumbnail' );
                        if ( ! $img_url ) continue;
                    ?>
                        <div class="psc-gallery-item" id="psc-gitem-<?php echo $img_id; ?>" data-id="<?php echo $img_id; ?>">
                            <img src="<?php echo esc_url( $img_url ); ?>" alt="">
                            <button type="button" class="psc-gallery-del"
                                    onclick="pscRemoveGalleryItem(this, <?php echo $img_id; ?>)"
                                    title="삭제">✕</button>
                            <input type="hidden" name="psc_gallery_keep[]" value="<?php echo $img_id; ?>">
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="psc-gallery-grid" id="psc-gallery-new-preview"></div>
                <div id="psc-gallery-remove-inputs"></div>

                <div style="margin-top:12px">
                    <label for="psc_gallery_upload"
                           class="psc-dash-btn psc-dash-btn--outline psc-dash-btn--sm"
                           id="psc-gallery-upload-label"
                           style="cursor:pointer;display:inline-flex;align-items:center;gap:6px">
                        📷 이미지 추가
                        <span style="font-size:.78rem;color:#6b7280;font-weight:400">(최대 <?php echo $max_images; ?>장)</span>
                    </label>
                    <input type="file" id="psc_gallery_upload" name="psc_gallery_new[]"
                           accept="image/*" multiple style="display:none"
                           onchange="pscAddGalleryImages(this)">
                </div>

                <div id="psc-gallery-limit-warn"
                     style="display:none;color:#ef4444;font-size:.83rem;margin-top:6px">
                    ⚠️ 최대 <?php echo $max_images; ?>장까지만 등록할 수 있습니다.
                    <?php if ( ! $is_premium ) : ?>
                        <a href="<?php echo esc_url( home_url( '/upgrade/' ) ); ?>"
                           style="color:#667eea">프리미엄으로 업그레이드</a>하면 20장까지 가능합니다.
                    <?php endif; ?>
                </div>
            </div>

            <!-- ════ 기본 정보 ════ -->
            <div class="psc-dash-card">
                <div class="psc-dash-card__header">
                    <h3 class="psc-dash-card__title">기본 정보</h3>
                </div>
                <div class="psc-dash-form" style="gap:14px">

                    <div class="psc-dash-form-group">
                        <label>매장명</label>
                        <input type="text" name="psc_store_title"
                               value="<?php echo esc_attr( $store_title ); ?>" required>
                    </div>

                    <div class="psc-dash-form-group">
                        <label>전화번호</label>
                        <input type="tel" name="psc_store_phone"
                               value="<?php echo esc_attr( $phone ); ?>"
                               placeholder="010-0000-0000">
                    </div>

                    <div class="psc-dash-form-group">
                        <label>주소</label>
                        <div style="display:flex;gap:8px">
                            <input type="text" name="psc_store_address" id="psc_store_address"
                                   value="<?php echo esc_attr( $address ); ?>"
                                   placeholder="도로명 주소" readonly style="flex:1">
                            <button type="button"
                                    class="psc-dash-btn psc-dash-btn--outline psc-dash-btn--sm"
                                    onclick="pscSearchAddress()">주소 검색</button>
                        </div>
                        <input type="hidden" name="psc_store_lat" id="psc_store_lat" value="<?php echo esc_attr( $lat ); ?>">
                        <input type="hidden" name="psc_store_lng" id="psc_store_lng" value="<?php echo esc_attr( $lng ); ?>">
                    </div>

                    <div class="psc-dash-form-group">
                        <label>상세주소</label>
                        <input type="text" name="psc_store_address_detail"
                               value="<?php echo esc_attr( $address_detail ); ?>"
                               placeholder="예) 2층, 101호, OO빌딩">
                    </div>

                </div>
            </div>

            <!-- ════ 리뷰 인증 설정 ════ -->
            <div class="psc-dash-card" id="section-review-verify" style="display:block">
                <div class="psc-dash-card__header">
                    <h3 class="psc-dash-card__title">📝 리뷰 인증 설정</h3>
                </div>

                <div class="psc-dash-form-group">
                    <label class="psc-dash-label">인증 방식</label>
                    <div class="psc-verify-options">
                        <label class="psc-verify-option <?php echo $cur_verify_type === 'receipt' ? 'active' : ''; ?>">
                            <input type="radio" name="review_verify_type" value="receipt"
                                <?php checked( $cur_verify_type, 'receipt' ); ?>>
                            <span class="psc-verify-icon">🧾</span>
                            <span class="psc-verify-label">영수증 인증만</span>
                            <span class="psc-verify-desc">방문 후 영수증 사진으로 인증</span>
                        </label>
                        <label class="psc-verify-option <?php echo $cur_verify_type === 'gps' ? 'active' : ''; ?>">
                            <input type="radio" name="review_verify_type" value="gps"
                                <?php checked( $cur_verify_type, 'gps' ); ?>>
                            <span class="psc-verify-icon">📍</span>
                            <span class="psc-verify-label">GPS 인증만</span>
                            <span class="psc-verify-desc">매장 반경 내 위치로 인증</span>
                        </label>
                        <label class="psc-verify-option <?php echo $cur_verify_type === 'both' ? 'active' : ''; ?>">
                            <input type="radio" name="review_verify_type" value="both"
                                <?php checked( $cur_verify_type, 'both' ); ?>>
                            <span class="psc-verify-icon">✅</span>
                            <span class="psc-verify-label">둘 다 허용</span>
                            <span class="psc-verify-desc">고객이 원하는 방식으로 인증</span>
                        </label>
                    </div>
                </div>

                <div class="psc-dash-form-group psc-gps-radius-wrap"
                     style="<?php echo $cur_verify_type === 'receipt' ? 'display:none' : ''; ?>">
                    <label class="psc-dash-label" for="review_gps_radius">GPS 인증 반경</label>
                    <div class="psc-radius-input-wrap">
                        <input type="range" id="review_gps_radius" name="review_gps_radius"
                               min="50" max="2000" step="50"
                               value="<?php echo esc_attr( $cur_gps_radius ); ?>"
                               oninput="document.getElementById('psc_radius_display').textContent = this.value">
                        <span class="psc-radius-display">
                            <span id="psc_radius_display"><?php echo esc_html( $cur_gps_radius ); ?></span>m
                        </span>
                    </div>
                    <p class="psc-dash-hint">매장 위치에서 이 반경 안에 있어야 GPS 인증이 통과됩니다. (50m ~ 2000m)</p>
                </div>
            </div>

            <!-- ════ 운영시간 ════ -->
            <div class="psc-dash-card">
                <div class="psc-dash-card__header">
                    <h3 class="psc-dash-card__title">운영시간</h3>
                    <span style="font-size:.8rem;color:#6b7280;margin-left:auto">저장 즉시 매장 페이지에 반영됩니다</span>
                </div>

                <div class="psc-hours-table">
                    <?php foreach ( $days_map as $key => $label ) :
                        $d      = $hours_weekly[ $key ];
                        $closed = ! empty( $d['closed'] );
                        $open   = esc_attr( $d['open']        ?? '09:00' );
                        $close  = esc_attr( $d['close']       ?? '18:00' );
                        $bstart = esc_attr( $d['break_start'] ?? '' );
                        $bend   = esc_attr( $d['break_end']   ?? '' );
                        $note   = esc_attr( $d['note']        ?? '' );
                    ?>
                    <div class="psc-hours-row" id="psc-hours-row-<?php echo $key; ?>">

                        <div class="psc-hours-day"><?php echo $label; ?></div>

                        <div class="psc-hours-closed-wrap">
                            <label class="psc-toggle-label">
                                <input type="checkbox"
                                       name="psc_hours[<?php echo $key; ?>][closed]"
                                       value="1"
                                       class="psc-hours-closed-chk"
                                       data-day="<?php echo $key; ?>"
                                       <?php checked( $closed ); ?>
                                       onchange="pscToggleHoursRow('<?php echo $key; ?>', this.checked)">
                                <span class="psc-toggle-slider"></span>
                                <span class="psc-toggle-text"><?php echo $closed ? '휴무' : '영업'; ?></span>
                            </label>
                        </div>

                        <div class="psc-hours-times" id="psc-times-<?php echo $key; ?>"
                             style="<?php echo $closed ? 'display:none' : ''; ?>">

                            <div class="psc-hours-time-row">
                                <span class="psc-hours-time-label">영업</span>
                                <input type="time" name="psc_hours[<?php echo $key; ?>][open]"
                                       value="<?php echo $open; ?>">
                                <span class="psc-hours-sep">~</span>
                                <input type="time" name="psc_hours[<?php echo $key; ?>][close]"
                                       value="<?php echo $close; ?>">
                            </div>

                            <div class="psc-hours-time-row psc-hours-break-row"
                                 style="<?php echo ( $bstart || $bend ) ? '' : 'opacity:.5'; ?>">
                                <span class="psc-hours-time-label">브레이크</span>
                                <input type="time" name="psc_hours[<?php echo $key; ?>][break_start]"
                                       value="<?php echo $bstart; ?>" placeholder="선택">
                                <span class="psc-hours-sep">~</span>
                                <input type="time" name="psc_hours[<?php echo $key; ?>][break_end]"
                                       value="<?php echo $bend; ?>" placeholder="선택">
                            </div>

                            <div class="psc-hours-time-row">
                                <span class="psc-hours-time-label">비고</span>
                                <input type="text" name="psc_hours[<?php echo $key; ?>][note]"
                                       value="<?php echo $note; ?>"
                                       placeholder="예) 라스트오더 21:30"
                                       style="flex:1">
                            </div>

                        </div>

                        <div class="psc-hours-closed-label" id="psc-closed-label-<?php echo $key; ?>"
                             style="<?php echo $closed ? '' : 'display:none'; ?>">
                            휴무
                        </div>

                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <button type="submit" class="psc-dash-btn psc-dash-btn--primary psc-dash-btn--full">
                저장하기
            </button>
        </form>
    </div>

    <!-- ════ CSS ════ -->
    <style>
    /* 헤더 */
    .psc-dash-page-header {
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 20px;
    }
    .psc-dash-page-title { margin: 0 !important; font-size: 1.1rem; font-weight: 700; }
    .psc-dash-card__header { display: flex; align-items: center; gap: 10px; margin-bottom: 16px; }
    .psc-dash-card__title  { margin: 0; font-size: 1rem; font-weight: 700; }

    /* 대표 이미지 */
    .psc-dash-thumb-wrap    { display:flex; align-items:center; gap:16px; flex-wrap:wrap; }
    .psc-dash-thumb-preview { width:100px; height:100px; object-fit:cover; border-radius:10px; }
    .psc-dash-thumb-empty   {
        width:100px; height:100px; background:#f0f0f0; border-radius:10px;
        display:flex; align-items:center; justify-content:center;
        font-size:.78rem; color:#aaa;
    }
    .psc-dash-thumb-actions { display:flex; flex-direction:column; gap:8px; }

    /* 갤러리 */
    .psc-gallery-plan-badge {
        font-size:.75rem; font-weight:600; padding:2px 10px;
        border-radius:20px; margin-left:auto; white-space:nowrap;
    }
    .psc-gallery-plan-badge.premium { background:#fef3c7; color:#92400e; }
    .psc-gallery-plan-badge.free    { background:#f3f4f6; color:#6b7280; }
    .psc-gallery-counter {
        font-size:.85rem; color:#6b7280; margin-bottom:12px;
        display:flex; align-items:center; flex-wrap:wrap;
    }
    .psc-gallery-counter #psc-gallery-count { font-weight:700; color:#111827; font-size:1rem; }
    .psc-gallery-upgrade-hint { color:#667eea; font-size:.78rem; }
    .psc-gallery-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(90px, 1fr));
        gap: 10px;
        margin-bottom: 4px;
    }
    .psc-gallery-item {
        position: relative; border-radius: 8px;
        overflow: hidden; aspect-ratio: 1/1;
        background: #f3f4f6; cursor: grab;
    }
    .psc-gallery-item.sortable-ghost { opacity: .4; }
    .psc-gallery-item img { width:100%; height:100%; object-fit:cover; display:block; }
    .psc-gallery-del {
        position:absolute; top:4px; right:4px; width:22px; height:22px;
        border-radius:50%; background:rgba(0,0,0,.55); color:#fff;
        border:none; cursor:pointer; font-size:.7rem;
        display:flex; align-items:center; justify-content:center;
        line-height:1; padding:0;
    }
    .psc-gallery-del:hover { background:rgba(239,68,68,.85); }

    /* 리뷰 인증 설정 */
    .psc-verify-options {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 12px;
        margin-top: 8px;
    }
    #section-review-verify { display:block !important; }
    .psc-verify-option {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 4px;
        padding: 16px 12px;
        border: 2px solid #e5e7eb;
        border-radius: 12px;
        cursor: pointer;
        transition: all .2s;
        text-align: center;
    }
    .psc-verify-option input[type="radio"] { display: none; }
    .psc-verify-option.active,
    .psc-verify-option:has(input:checked) {
        border-color: #6366f1;
        background: #eef2ff;
    }
    .psc-verify-icon  { font-size: 28px; }
    .psc-verify-label { font-weight: 700; font-size: 14px; color: #111; }
    .psc-verify-desc  { font-size: 12px; color: #6b7280; }
    .psc-radius-input-wrap {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-top: 8px;
    }
    .psc-radius-input-wrap input[type="range"] { flex: 1; accent-color: #6366f1; }
    .psc-radius-display {
        font-weight: 700;
        color: #6366f1;
        min-width: 70px;
        font-size: 16px;
    }
    .psc-dash-hint { font-size: .78rem; color: #9ca3af; margin-top: 6px; }

    /* 운영시간 */
    .psc-hours-table { display:flex; flex-direction:column; gap:0; }
    .psc-hours-row {
        display: grid;
        grid-template-columns: 2rem 5rem 1fr;
        align-items: start;
        gap: 12px;
        padding: 14px 0;
        border-bottom: 1px solid #f3f4f6;
    }
    .psc-hours-row:last-child { border-bottom: none; }
    .psc-hours-day { font-weight: 700; font-size: .95rem; color: #111; padding-top: 6px; }
    .psc-hours-closed-wrap { padding-top: 4px; }
    .psc-toggle-label { display: inline-flex; align-items: center; gap: 8px; cursor: pointer; }
    .psc-toggle-label input[type=checkbox] { display: none; }
    .psc-toggle-slider {
        position: relative; width: 40px; height: 22px;
        background: #d1d5db; border-radius: 11px;
        transition: background .2s; flex-shrink: 0;
    }
    .psc-toggle-slider::after {
        content: ''; position: absolute; top: 3px; left: 3px;
        width: 16px; height: 16px; border-radius: 50%;
        background: #fff; transition: transform .2s;
    }
    .psc-toggle-label input:checked + .psc-toggle-slider { background: #6b7280; }
    .psc-toggle-label input:checked + .psc-toggle-slider::after { transform: translateX(18px); }
    .psc-toggle-label input:not(:checked) + .psc-toggle-slider { background: #10b981; }
    .psc-toggle-text { font-size: .82rem; color: #6b7280; white-space: nowrap; }
    .psc-hours-times { display: flex; flex-direction: column; gap: 8px; }
    .psc-hours-time-row { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
    .psc-hours-time-label { font-size: .75rem; color: #9ca3af; width: 46px; flex-shrink: 0; }
    .psc-hours-time-row input[type=time],
    .psc-hours-time-row input[type=text] {
        border: 1px solid #e5e7eb; border-radius: 6px;
        padding: 5px 8px; font-size: .88rem;
    }
    .psc-hours-time-row input[type=time] { width: 100px; }
    .psc-hours-sep { color: #9ca3af; font-size: .85rem; }
    .psc-hours-closed-label { font-size: .88rem; color: #9ca3af; padding-top: 6px; font-style: italic; }

    @media (max-width: 480px) {
        .psc-verify-options { grid-template-columns: 1fr; }
        .psc-hours-row { grid-template-columns: 2rem 4.5rem 1fr; gap: 8px; }
        .psc-hours-time-row input[type=time] { width: 88px; }
    }
    </style>

    <!-- ════ JS ════ -->
    <?php
    wp_enqueue_script(
        'sortablejs',
        'https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js',
        [], '1.15.2', true
    );
    ?>

    <script>
    /* ── 대표이미지 미리보기 ── */
    function pscPreviewThumb(input){
        if(!input.files||!input.files[0])return;
        var r=new FileReader();
        r.onload=function(e){
            var el=document.getElementById('psc-thumb-preview');
            if(el.tagName==='IMG'){el.src=e.target.result;}
            else{
                var img=document.createElement('img');
                img.src=e.target.result;img.id='psc-thumb-preview';
                img.className='psc-dash-thumb-preview';
                el.parentNode.replaceChild(img,el);
            }
        };
        r.readAsDataURL(input.files[0]);
    }

    /* ── 다음 주소 검색 + 카카오 지오코딩 ── */
    function pscSearchAddress(){
        new daum.Postcode({
            oncomplete: function(data){
                var addr = data.roadAddress || data.jibunAddress;
                document.getElementById('psc_store_address').value = addr;

                // 카카오 지오코딩으로 좌표 변환
                if(typeof kakao !== 'undefined' && kakao.maps && kakao.maps.services){
                    var geocoder = new kakao.maps.services.Geocoder();
                    geocoder.addressSearch(addr, function(result, status){
                        if(status === kakao.maps.services.Status.OK){
                            document.getElementById('psc_store_lat').value = result[0].y;
                            document.getElementById('psc_store_lng').value = result[0].x;
                        } else {
                            alert('좌표 탐색 실패: ' + status + '\n' + addr);
                        }
                    });
                } else {
                    alert('카카오 지도 SDK가 로드되지 않았습니다.');
                }
            }
        }).open();
    }

    /* ── 갤러리 ── */
    var PSC_MAX_IMAGES=<?php echo (int)$max_images; ?>;

    function pscGetTotalCount(){
        return document.querySelectorAll('#psc-gallery-grid .psc-gallery-item').length
             + document.querySelectorAll('#psc-gallery-new-preview .psc-gallery-item').length;
    }

    function pscUpdateCounter(){
        var t=pscGetTotalCount();
        document.getElementById('psc-gallery-count').textContent=t;
        var warn=document.getElementById('psc-gallery-limit-warn');
        var label=document.getElementById('psc-gallery-upload-label');
        if(t>=PSC_MAX_IMAGES){
            warn.style.display='block';
            label.style.opacity='.4';
            label.style.pointerEvents='none';
        }else{
            warn.style.display='none';
            label.style.opacity='1';
            label.style.pointerEvents='auto';
        }
    }

    function pscRemoveGalleryItem(btn,imgId){
        var item=document.getElementById('psc-gitem-'+imgId);
        if(item)item.remove();
        var wrap=document.getElementById('psc-gallery-remove-inputs');
        var inp=document.createElement('input');
        inp.type='hidden';inp.name='psc_gallery_remove[]';inp.value=imgId;
        wrap.appendChild(inp);
        pscUpdateCounter();
    }

    function pscAddGalleryImages(input){
        if(!input.files||!input.files.length)return;
        var files=Array.from(input.files);
        var allowed=PSC_MAX_IMAGES-pscGetTotalCount();
        if(allowed<=0){pscUpdateCounter();input.value='';return;}
        var toAdd=files.slice(0,allowed);
        if(files.length>allowed){
            document.getElementById('psc-gallery-limit-warn').style.display='block';
        }
        var preview=document.getElementById('psc-gallery-new-preview');
        toAdd.forEach(function(file,idx){
            var r=new FileReader();
            r.onload=function(e){
                var item=document.createElement('div');
                item.className='psc-gallery-item';
                item.dataset.fileIndex=idx;
                var img=document.createElement('img');img.src=e.target.result;
                var del=document.createElement('button');
                del.type='button';del.className='psc-gallery-del';del.textContent='✕';
                del.onclick=function(){item.remove();pscUpdateCounter();pscRebuildFileInput();};
                item.appendChild(img);item.appendChild(del);
                preview.appendChild(item);
                pscUpdateCounter();
            };
            r.readAsDataURL(file);
        });
        if(typeof DataTransfer!=='undefined'){
            var dt=new DataTransfer();
            toAdd.forEach(function(f){dt.items.add(f);});
            input.files=dt.files;
        }
    }

    function pscRebuildFileInput(){
        var input=document.getElementById('psc_gallery_upload');
        var items=document.querySelectorAll('#psc-gallery-new-preview .psc-gallery-item');
        var indices=Array.from(items).map(function(el){return parseInt(el.dataset.fileIndex);});
        if(typeof DataTransfer==='undefined')return;
        var dt=new DataTransfer();
        Array.from(input.files).forEach(function(f,i){
            if(indices.indexOf(i)!==-1)dt.items.add(f);
        });
        input.files=dt.files;
        pscUpdateCounter();
    }

    /* ── 운영시간 토글 ── */
    function pscToggleHoursRow(day,isClosed){
        var times=document.getElementById('psc-times-'+day);
        var label=document.getElementById('psc-closed-label-'+day);
        var toggleText=document.querySelector('#psc-hours-row-'+day+' .psc-toggle-text');
        if(isClosed){
            times.style.display='none';
            label.style.display='block';
            if(toggleText)toggleText.textContent='휴무';
        }else{
            times.style.display='';
            label.style.display='none';
            if(toggleText)toggleText.textContent='영업';
        }
    }

    /* ── 리뷰 인증 방식 토글 ── */
    document.querySelectorAll('input[name="review_verify_type"]').forEach(function(radio){
        radio.addEventListener('change', function(){
            var wrap=document.querySelector('.psc-gps-radius-wrap');
            if(!wrap)return;
            wrap.style.display=(this.value==='receipt')?'none':'';
            document.querySelectorAll('.psc-verify-option').forEach(function(opt){
                opt.classList.remove('active');
            });
            this.closest('.psc-verify-option').classList.add('active');
        });
    });

    /* ── SortableJS 초기화 ── */
    function pscInitSortable(){
        var grid=document.getElementById('psc-gallery-grid');
        if(!grid)return;
        if(typeof Sortable==='undefined'){
            setTimeout(pscInitSortable,100);
            return;
        }
        Sortable.create(grid,{
            animation:150,
            ghostClass:'sortable-ghost',
            onEnd:function(){
                var items=grid.querySelectorAll('.psc-gallery-item');
                items.forEach(function(item){
                    var old=item.querySelector('input[name="psc_gallery_keep[]"]');
                    if(old)old.remove();
                    var inp=document.createElement('input');
                    inp.type='hidden';
                    inp.name='psc_gallery_keep[]';
                    inp.value=item.dataset.id;
                    item.appendChild(inp);
                });
            }
        });
    }

    document.addEventListener('DOMContentLoaded',function(){
        pscUpdateCounter();
        pscInitSortable();
    });
    </script>

    <!-- 다음 주소 API -->
    <script src="https://t1.daumcdn.net/mapjsapi/bundle/postcode/prod/postcode.v2.js"></script>
    <!-- 다음 주소 API -->
<script src="https://t1.daumcdn.net/mapjsapi/bundle/postcode/prod/postcode.v2.js"></script>
<!-- 카카오 지도 SDK (지오코딩용) -->
<script src="https://dapi.kakao.com/v2/maps/sdk.js?appkey=5f9ea3dc453f9ccc71d68cf3547d9981&libraries=services"></script>


    <?php
}

/* ════════════════════════════════════════════
   저장 처리 함수
   ════════════════════════════════════════════ */
function psc_dash_save_store( int $store_id ): true|WP_Error {

    /* 매장명 */
    $title = isset( $_POST['psc_store_title'] )
        ? sanitize_text_field( wp_unslash( $_POST['psc_store_title'] ) ) : '';
    if ( ! $title ) return new WP_Error( 'empty_title', '매장명을 입력해주세요.' );

    wp_update_post( [ 'ID' => $store_id, 'post_title' => $title ] );

    /* 기본 메타 */
    $simple_fields = [
        'psc_store_phone'          => 'store_phone',
        'psc_store_address'        => 'store_address',
        'psc_store_address_detail' => 'store_address_detail',
        'psc_store_lat'            => 'store_lat',
        'psc_store_lng'            => 'store_lng',
    ];
    foreach ( $simple_fields as $post_key => $acf_key ) {
        $val = isset( $_POST[ $post_key ] )
            ? sanitize_text_field( wp_unslash( $_POST[ $post_key ] ) ) : '';
        update_field( $acf_key, $val, $store_id );
    }

    /* ── 리뷰 인증 설정 저장 ── */
    $verify_type = sanitize_text_field( $_POST['review_verify_type'] ?? 'receipt' );
    $gps_radius  = absint( $_POST['review_gps_radius'] ?? 300 );

    if ( ! in_array( $verify_type, [ 'receipt', 'gps', 'both' ], true ) ) {
        $verify_type = 'receipt';
    }
    $gps_radius = max( 50, min( 2000, $gps_radius ) );

    update_post_meta( $store_id, 'review_verify_type', $verify_type );
    update_post_meta( $store_id, 'review_gps_radius',  $gps_radius );

    /* ── 운영시간 저장 ── */
    $days     = [ 'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun' ];
    $raw_hours = isset( $_POST['psc_hours'] ) ? (array) $_POST['psc_hours'] : [];
    $hours_data = [];
    foreach ( $days as $d ) {
        $day_raw = $raw_hours[ $d ] ?? [];
        $hours_data[ $d ] = [
            'closed'      => ! empty( $day_raw['closed'] ),
            'open'        => sanitize_text_field( $day_raw['open']        ?? '09:00' ),
            'close'       => sanitize_text_field( $day_raw['close']       ?? '18:00' ),
            'break_start' => sanitize_text_field( $day_raw['break_start'] ?? '' ),
            'break_end'   => sanitize_text_field( $day_raw['break_end']   ?? '' ),
            'note'        => sanitize_text_field( $day_raw['note']        ?? '' ),
        ];
    }
    update_field( 'store_hours_weekly', $hours_data, $store_id );

    /* 대표 이미지 삭제 */
    if ( ! empty( $_POST['psc_remove_thumb'] ) ) delete_post_thumbnail( $store_id );

    /* 대표 이미지 업로드 */
    if ( ! empty( $_FILES['psc_store_thumb']['name'] ) ) {
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        $aid = media_handle_upload( 'psc_store_thumb', $store_id );
        if ( ! is_wp_error( $aid ) ) set_post_thumbnail( $store_id, $aid );
    }

    /* ── 갤러리 저장 ── */
    $is_premium = function_exists( 'psc_store_is_premium' ) && psc_store_is_premium( $store_id );
    $max_images = $is_premium ? 20 : 5;

    $keep_ids = isset( $_POST['psc_gallery_keep'] )
        ? array_map( 'absint', (array) $_POST['psc_gallery_keep'] ) : [];

    $new_ids = [];
    if ( ! empty( $_FILES['psc_gallery_new']['name'][0] ) ) {
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        $count     = count( $_FILES['psc_gallery_new']['name'] );
        $remaining = $max_images - count( $keep_ids );
        for ( $i = 0; $i < $count && count( $new_ids ) < $remaining; $i++ ) {
            if ( empty( $_FILES['psc_gallery_new']['name'][ $i ] ) ) continue;
            $_FILES['psc_gallery_new_single'] = [
                'name'     => $_FILES['psc_gallery_new']['name'][ $i ],
                'type'     => $_FILES['psc_gallery_new']['type'][ $i ],
                'tmp_name' => $_FILES['psc_gallery_new']['tmp_name'][ $i ],
                'error'    => $_FILES['psc_gallery_new']['error'][ $i ],
                'size'     => $_FILES['psc_gallery_new']['size'][ $i ],
            ];
            $aid = media_handle_upload( 'psc_gallery_new_single', $store_id );
            if ( ! is_wp_error( $aid ) ) $new_ids[] = $aid;
        }
    }

    $final_ids = array_slice( array_merge( $keep_ids, $new_ids ), 0, $max_images );
    update_field( 'store_gallery_basic', $final_ids, $store_id );

    return true;
}
