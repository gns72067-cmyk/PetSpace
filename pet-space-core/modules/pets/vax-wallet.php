<?php
/**
 * PetSpace — Vaccination Wallet Module
 * File: modules/pets/vax-wallet.php
 *
 * Shortcode : [pet_vax_wallet]
 * AJAX      : psc_save_vax, psc_delete_vax,
 *             psc_upload_vax_attach, psc_delete_vax_attach
 * DB Tables : {prefix}psc_vax_records
 *             {prefix}psc_vax_attachments
 * Admin     : WP어드민 → PetSpace → 접종 증빙 관리
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* ============================================================
   0. 상수 & 테이블 생성
   ============================================================ */
define( 'PSC_VAX_TABLE',     'psc_vax_records' );
define( 'PSC_VAX_ATT_TABLE', 'psc_vax_attachments' );

add_action( 'init', function () {
    if ( get_option('psc_vax_table_version') !== '1.1' ) {
        psc_vax_create_table();
    }
} );

function psc_vax_create_table(): void {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();

    $t1   = $wpdb->prefix . PSC_VAX_TABLE;
    $sql1 = "CREATE TABLE IF NOT EXISTS {$t1} (
        id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id    BIGINT UNSIGNED NOT NULL,
        pet_id     BIGINT UNSIGNED NOT NULL,
        vax_name   VARCHAR(200)   NOT NULL,
        vax_date   DATE           NOT NULL,
        next_date  DATE           NULL,
        hospital   VARCHAR(200)   NULL,
        memo       TEXT           NULL,
        created_at DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_user_pet  (user_id, pet_id),
        INDEX idx_next_date (next_date)
    ) {$charset};";

    $t2   = $wpdb->prefix . PSC_VAX_ATT_TABLE;
    $sql2 = "CREATE TABLE IF NOT EXISTS {$t2} (
        id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        vax_id     BIGINT UNSIGNED NOT NULL,
        user_id    BIGINT UNSIGNED NOT NULL,
        att_id     BIGINT UNSIGNED NOT NULL,
        file_url   VARCHAR(500)   NOT NULL,
        file_type  VARCHAR(10)    NOT NULL DEFAULT 'image',
        created_at DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_vax  (vax_id),
        INDEX idx_user (user_id)
    ) {$charset};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql1 );
    dbDelta( $sql2 );
    update_option( 'psc_vax_table_version', '1.1' );
}

/* ============================================================
   1. 접종 목록
   ============================================================ */
function psc_get_vax_list(): array {
    return [
        '종합백신 DHPPL'           => '💉',
        '코로나 장염'              => '💉',
        '켄넬코프'                 => '💉',
        '광견병'                   => '💉',
        '인플루엔자'               => '💉',
        '신종플루'                 => '💉',
        '라임병'                   => '💉',
        '렙토스피라'               => '💉',
        '심장사상충 예방'          => '🐛',
        '내부기생충 구충'          => '🐛',
        '외부기생충 (벼룩/진드기)' => '🐛',
        '구강 스케일링'            => '🦷',
        '건강검진'                 => '🏥',
        '직접 입력'                => '📝',
    ];
}

/* ============================================================
   2. D-day 헬퍼
   ============================================================ */
function psc_vax_dday( string $next_date ): array {
    $today = new DateTime( current_time('Y-m-d') );
    $next  = new DateTime( $next_date );
    $diff  = (int)$today->diff($next)->format('%r%a');
    $level = 'ok';
    $label = '';

    if ( $diff < 0 ) {
        $label = '⚠️ ' . abs($diff) . '일 지남'; $level = 'danger';
    } elseif ( $diff === 0 ) {
        $label = '🔔 오늘!'; $level = 'danger';
    } elseif ( $diff <= 7 ) {
        $label = '🔔 D-' . $diff; $level = 'danger';
    } elseif ( $diff <= 30 ) {
        $label = '⏰ D-' . $diff; $level = 'warn';
    } else {
        $label = 'D-' . $diff; $level = 'ok';
    }
    return [ 'diff' => $diff, 'label' => $label, 'level' => $level ];
}

/* ============================================================
   3. 숏코드
   ============================================================ */
add_shortcode( 'pet_vax_wallet', 'psc_shortcode_vax_wallet' );

function psc_shortcode_vax_wallet(): string {
    if ( ! is_user_logged_in() ) {
        return '<p style="padding:20px;text-align:center;">로그인이 필요합니다. <a href="'
               . esc_url( home_url('/login/') ) . '">로그인</a></p>';
    }
    ob_start();
    psc_vax_render_wallet();
    return ob_get_clean();
}

/* ============================================================
   4. 메인 렌더
   ============================================================ */
function psc_vax_render_wallet(): void {
    global $wpdb;
    $user_id    = get_current_user_id();
    $pets_table = $wpdb->prefix . PSC_PETS_TABLE;
    $vax_table  = $wpdb->prefix . PSC_VAX_TABLE;
    $att_table  = $wpdb->prefix . PSC_VAX_ATT_TABLE;

    $pets = $wpdb->get_results( $wpdb->prepare(
        "SELECT id, pet_name, photo_url FROM {$pets_table}
         WHERE user_id = %d ORDER BY created_at ASC",
        $user_id
    ) );

    $active_pet_id = (int)( $_GET['pet'] ?? ( $pets[0]->id ?? 0 ) );

    $records = [];
    if ( $active_pet_id ) {
        $records = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$vax_table}
             WHERE user_id = %d AND pet_id = %d
             ORDER BY
                CASE WHEN next_date IS NULL THEN 1 ELSE 0 END,
                next_date ASC, vax_date DESC",
            $user_id, $active_pet_id
        ) );
        foreach ( $records as $rec ) {
            $rec->attachments = $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM {$att_table} WHERE vax_id = %d ORDER BY created_at ASC",
                $rec->id
            ) );
        }
    }

    $vax_list   = psc_get_vax_list();
    $nonce_save = wp_create_nonce('psc_vax_save');
    $nonce_del  = wp_create_nonce('psc_vax_delete');
    $nonce_att  = wp_create_nonce('psc_vax_attach');

    $urgent_count = 0;
    foreach ( $records as $r ) {
        if ( $r->next_date ) {
            $d = psc_vax_dday($r->next_date);
            if ( $d['diff'] <= 30 ) $urgent_count++;
        }
    }

    $primary      = '#224471';
    $primary_dark = '#1a3459';
    ?>
    <div class="psc-vax-wrap" id="pscVaxWrap">

    <style>
    @import url('https://cdn.jsdelivr.net/gh/orioncactus/pretendard/dist/web/static/pretendard.css');

    .psc-vax-wrap {
        font-family: "Pretendard", -apple-system, "Apple SD Gothic Neo", sans-serif;
        color: #191f28;
        -webkit-font-smoothing: antialiased;
    }

    /* ── 반려견 탭 ── */
    .psc-vax-pet-tabs {
        display: flex;
        gap: 8px;
        overflow-x: auto;
        padding-bottom: 4px;
        margin-bottom: 20px;
        scrollbar-width: none;
    }
    .psc-vax-pet-tabs::-webkit-scrollbar { display: none; }
    .psc-vax-pet-tab {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 16px;
        border-radius: 30px;
        border: 1.5px solid #e5e8eb;
        background: #fff;
        font-size: .84rem;
        font-weight: 600;
        color: #8b95a1;
        cursor: pointer;
        white-space: nowrap;
        text-decoration: none;
        transition: all .15s;
        flex-shrink: 0;
        font-family: "Pretendard", sans-serif;
    }
    .psc-vax-pet-tab:hover {
        border-color: <?php echo $primary; ?>;
        color: <?php echo $primary; ?>;
    }
    .psc-vax-pet-tab.active {
        border-color: <?php echo $primary; ?>;
        background: <?php echo $primary; ?>;
        color: #fff;
    }
    .psc-vax-pet-tab img {
        width: 22px; height: 22px;
        border-radius: 50%;
        object-fit: cover;
    }
    .tab-urgent-dot {
        width: 7px; height: 7px;
        border-radius: 50%;
        background: #ef4444;
        flex-shrink: 0;
    }

    /* ── 헤더 ── */
    .psc-vax-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 16px;
    }
    .psc-vax-title {
        font-size: 1rem;
        font-weight: 800;
        color: #191f28;
        letter-spacing: -.02em;
    }
    .psc-vax-title span {
        font-size: .82rem;
        font-weight: 400;
        color: #8b95a1;
        margin-left: 4px;
    }
    .btn-add-vax {
        display: flex;
        align-items: center;
        gap: 6px;
        padding: 9px 18px;
        background: <?php echo $primary; ?>;
        color: #fff;
        border: none;
        border-radius: 10px;
        font-size: .86rem;
        font-weight: 700;
        cursor: pointer;
        transition: background .2s;
        font-family: "Pretendard", sans-serif;
    }
    .btn-add-vax:hover { background: <?php echo $primary_dark; ?>; }

    /* ── 임박 배너 ── */
    .psc-vax-urgent-banner {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 16px;
        background: #fffbeb;
        border: 1.5px solid #fcd34d;
        border-radius: 12px;
        margin-bottom: 16px;
        font-size: .86rem;
        color: #92400e;
        font-weight: 600;
    }

    /* ── 폼 패널 ── */
    .psc-vax-form-panel {
        background: #fff;
        border-radius: 16px;
        border: 1px solid #e5e8eb;
        box-shadow: 0 2px 12px rgba(0,0,0,.06);
        padding: 22px;
        margin-bottom: 20px;
        display: none;
    }
    .psc-vax-form-panel.open { display: block; }
    .vax-form-title {
        font-size: 1rem;
        font-weight: 800;
        color: #191f28;
        margin-bottom: 18px;
        padding-bottom: 12px;
        border-bottom: 1.5px solid #f2f4f6;
        letter-spacing: -.02em;
    }
    .vax-form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }
    @media (max-width: 560px) {
        .vax-form-grid { grid-template-columns: 1fr; }
    }
    .vax-form-field { display: flex; flex-direction: column; gap: 5px; }
    .vax-form-field.full { grid-column: 1 / -1; }
    .vax-form-field label {
        font-size: .8rem;
        font-weight: 700;
        color: #4e5968;
    }
    .vax-form-field input,
    .vax-form-field select,
    .vax-form-field textarea {
        padding: 11px 13px;
        border: 1.5px solid #e5e8eb;
        border-radius: 10px;
        font-size: .88rem;
        color: #191f28;
        outline: none;
        transition: border-color .2s, background .2s;
        width: 100%;
        box-sizing: border-box;
        background: #f4f6f9;
        font-family: "Pretendard", sans-serif;
        -webkit-appearance: none;
        appearance: none;
    }
    .vax-form-field input:focus,
    .vax-form-field select:focus,
    .vax-form-field textarea:focus {
        border-color: <?php echo $primary; ?>;
        background: #fff;
        box-shadow: 0 0 0 3px rgba(34,68,113,.08);
    }
    .vax-form-field input::placeholder,
    .vax-form-field textarea::placeholder { color: #b0b8c1; }
    .vax-form-field textarea { resize: vertical; min-height: 64px; }

    #vaxCustomNameWrap { margin-top: 8px; display: none; }
    #vaxCustomNameWrap input {
        padding: 11px 13px;
        border: 1.5px solid #e5e8eb;
        border-radius: 10px;
        font-size: .88rem;
        outline: none;
        width: 100%;
        box-sizing: border-box;
        background: #f4f6f9;
        font-family: "Pretendard", sans-serif;
        transition: border-color .2s, background .2s;
    }
    #vaxCustomNameWrap input:focus {
        border-color: <?php echo $primary; ?>;
        background: #fff;
    }

    /* ── 증빙 업로드 ── */
    .vax-attach-section { margin-top: 4px; }
    .vax-attach-label {
        font-size: .8rem;
        font-weight: 700;
        color: #4e5968;
        margin-bottom: 5px;
    }
    .vax-attach-hint {
        font-size: .74rem;
        color: #8b95a1;
        margin-bottom: 8px;
    }
    .vax-attach-previews {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 10px;
        min-height: 10px;
    }
    .vax-attach-thumb {
        position: relative;
        width: 76px; height: 76px;
        border-radius: 10px;
        overflow: hidden;
        border: 1.5px solid #e5e8eb;
        background: #f4f6f9;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
    }
    .vax-attach-thumb img {
        width: 100%; height: 100%;
        object-fit: cover;
    }
    .vax-attach-thumb .pdf-icon { font-size: 1.8rem; line-height: 1; }
    .vax-attach-thumb .btn-remove-attach {
        position: absolute;
        top: 3px; right: 3px;
        width: 20px; height: 20px;
        border-radius: 50%;
        background: rgba(25,31,40,.7);
        color: #fff;
        border: none;
        font-size: .65rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        line-height: 1;
    }
    .vax-attach-thumb.uploading { opacity: .6; }
    .vax-attach-upload-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 76px; height: 76px;
        border-radius: 10px;
        border: 2px dashed #e5e8eb;
        background: #f4f6f9;
        cursor: pointer;
        font-size: 1.5rem;
        color: #8b95a1;
        transition: all .15s;
        flex-shrink: 0;
    }
    .vax-attach-upload-btn:hover {
        border-color: <?php echo $primary; ?>;
        background: #eef2ff;
        color: <?php echo $primary; ?>;
    }
    .vax-attach-upload-input { display: none; }

    /* ── 폼 액션 ── */
    .vax-form-actions {
        display: flex;
        gap: 10px;
        margin-top: 16px;
        justify-content: flex-end;
    }
    .btn-vax-save {
        padding: 11px 28px;
        background: <?php echo $primary; ?>;
        color: #fff;
        border: none;
        border-radius: 10px;
        font-size: .9rem;
        font-weight: 700;
        cursor: pointer;
        transition: background .2s;
        font-family: "Pretendard", sans-serif;
    }
    .btn-vax-save:hover { background: <?php echo $primary_dark; ?>; }
    .btn-vax-cancel {
        padding: 11px 20px;
        background: #f4f6f9;
        color: #4e5968;
        border: none;
        border-radius: 10px;
        font-size: .9rem;
        font-weight: 600;
        cursor: pointer;
        transition: background .15s;
        font-family: "Pretendard", sans-serif;
    }
    .btn-vax-cancel:hover { background: #e5e8eb; }

    /* ── 접종 카드 ── */
    .psc-vax-list { display: flex; flex-direction: column; gap: 12px; }
    .psc-vax-card {
        background: #fff;
        border-radius: 14px;
        border: 1px solid #e5e8eb;
        box-shadow: 0 2px 10px rgba(0,0,0,.05);
        overflow: hidden;
        transition: box-shadow .15s;
        border-left: 4px solid #e5e8eb;
    }
    .psc-vax-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,.09); }
    .psc-vax-card.level-danger { border-left-color: #ef4444; }
    .psc-vax-card.level-warn   { border-left-color: #f59e0b; }
    .psc-vax-card.level-ok     { border-left-color: #10b981; }

    .vax-card-inner    { padding: 14px 16px; }
    .vax-card-top {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 10px;
        margin-bottom: 8px;
    }
    .vax-card-name {
        font-size: .94rem;
        font-weight: 800;
        color: #191f28;
        display: flex;
        align-items: center;
        gap: 6px;
        letter-spacing: -.01em;
    }
    .vax-dday-badge {
        font-size: .72rem;
        font-weight: 700;
        padding: 3px 9px;
        border-radius: 20px;
        white-space: nowrap;
        flex-shrink: 0;
    }
    .badge-danger { background: #fee2e2; color: #dc2626; }
    .badge-warn   { background: #fffbeb; color: #92400e; }
    .badge-ok     { background: #d1fae5; color: #065f46; }

    .vax-card-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        font-size: .78rem;
        color: #8b95a1;
    }
    .vax-meta-item { display: flex; align-items: center; gap: 4px; }
    .vax-card-memo {
        margin-top: 8px;
        font-size: .78rem;
        color: #8b95a1;
        border-top: 1px solid #f2f4f6;
        padding-top: 8px;
        line-height: 1.5;
    }

    /* ── 카드 증빙 썸네일 ── */
    .vax-card-attachments {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
        padding: 10px 16px;
        border-top: 1px solid #f2f4f6;
    }
    .vax-card-att-thumb {
        width: 52px; height: 52px;
        border-radius: 8px;
        overflow: hidden;
        border: 1.5px solid #e5e8eb;
        cursor: pointer;
        transition: all .15s;
        position: relative;
        background: #f4f6f9;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .vax-card-att-thumb:hover {
        border-color: <?php echo $primary; ?>;
        transform: scale(1.06);
    }
    .vax-card-att-thumb img {
        width: 100%; height: 100%;
        object-fit: cover;
    }
    .vax-card-att-thumb .pdf-badge { font-size: 1.3rem; line-height: 1; }
    .vax-att-count {
        font-size: .74rem;
        color: #8b95a1;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    /* ── 카드 액션 ── */
    .vax-card-actions {
        display: flex;
        gap: 6px;
        border-top: 1px solid #f2f4f6;
        padding: 8px 16px;
    }
    .btn-vax-edit {
        flex: 1;
        padding: 7px;
        background: #f4f6f9;
        border: none;
        border-radius: 8px;
        font-size: .8rem;
        font-weight: 600;
        color: #4e5968;
        cursor: pointer;
        transition: background .15s;
        font-family: "Pretendard", sans-serif;
    }
    .btn-vax-edit:hover { background: #e5e8eb; }
    .btn-vax-del {
        padding: 7px 14px;
        background: #fff0f0;
        border: none;
        border-radius: 8px;
        font-size: .8rem;
        font-weight: 600;
        color: #dc2626;
        cursor: pointer;
        transition: background .15s;
        font-family: "Pretendard", sans-serif;
    }
    .btn-vax-del:hover { background: #fecaca; }

    /* ── 라이트박스 ── */
    .psc-vax-lightbox {
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,.85);
        z-index: 99999;
        display: none;
        align-items: center;
        justify-content: center;
    }
    .psc-vax-lightbox.open { display: flex; }
    .lightbox-inner {
        position: relative;
        max-width: 90vw;
        max-height: 90vh;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .lightbox-inner img {
        max-width: 88vw;
        max-height: 85vh;
        border-radius: 12px;
        box-shadow: 0 8px 40px rgba(0,0,0,.5);
    }
    .lightbox-close {
        position: absolute;
        top: -14px; right: -14px;
        width: 34px; height: 34px;
        border-radius: 50%;
        background: #fff;
        border: none;
        font-size: 1rem;
        cursor: pointer;
        box-shadow: 0 2px 8px rgba(0,0,0,.3);
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .lightbox-pdf-wrap {
        background: #fff;
        border-radius: 16px;
        padding: 32px 40px;
        text-align: center;
    }
    .lightbox-pdf-wrap .pdf-big  { font-size: 4rem; margin-bottom: 12px; }
    .lightbox-pdf-wrap p {
        font-size: .88rem;
        color: #8b95a1;
        margin-bottom: 20px;
        line-height: 1.6;
    }
    .btn-pdf-download {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 11px 24px;
        background: <?php echo $primary; ?>;
        color: #fff;
        border: none;
        border-radius: 10px;
        font-size: .9rem;
        font-weight: 700;
        cursor: pointer;
        text-decoration: none;
        font-family: "Pretendard", sans-serif;
    }

    /* ── 빈 상태 ── */
    .psc-vax-empty {
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 60px 24px;
        text-align: center;
    }
    .psc-vax-empty .icon { font-size: 3.5rem; margin-bottom: 12px; }
    .psc-vax-empty p {
        font-size: .9rem;
        color: #8b95a1;
        line-height: 1.7;
    }

    /* ── 반려견 없음 ── */
    .psc-vax-no-pet {
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 48px 20px;
        background: #f4f6f9;
        border-radius: 16px;
        border: 1.5px dashed #e5e8eb;
        text-align: center;
    }
    .psc-vax-no-pet .icon { font-size: 3rem; margin-bottom: 10px; }
    .psc-vax-no-pet p {
        font-size: .88rem;
        color: #8b95a1;
        margin-bottom: 16px;
        line-height: 1.6;
    }
    .btn-go-pets {
        display: inline-block;
        padding: 10px 22px;
        background: <?php echo $primary; ?>;
        color: #fff;
        border-radius: 10px;
        font-size: .86rem;
        font-weight: 700;
        text-decoration: none;
        transition: background .2s;
        font-family: "Pretendard", sans-serif;
    }
    .btn-go-pets:hover { background: <?php echo $primary_dark; ?>; }

    /* ── 토스트 ── */
    .psc-vax-toast {
        position: fixed;
        bottom: 32px;
        left: 50%;
        transform: translateX(-50%);
        background: #191f28;
        color: #fff;
        padding: 11px 22px;
        border-radius: 30px;
        font-size: .86rem;
        font-weight: 600;
        z-index: 9999;
        opacity: 0;
        transition: opacity .3s;
        pointer-events: none;
        white-space: nowrap;
        font-family: "Pretendard", sans-serif;
    }
    .psc-vax-toast.show { opacity: 1; }
    </style>

    <?php if ( empty($pets) ) : ?>
    <div class="psc-vax-no-pet">
        <div class="icon">🐾</div>
        <p>먼저 반려견을 등록해야<br>접종 기록을 추가할 수 있어요.</p>
        <a class="btn-go-pets" href="<?php echo esc_url( home_url('/mypage/pets/') ); ?>">
            🐶 반려견 등록하러 가기
        </a>
    </div>

    <?php else : ?>

    <!-- 반려견 탭 -->
    <div class="psc-vax-pet-tabs">
        <?php foreach ( $pets as $pet ) :
            $is_active  = ( (int)$pet->id === $active_pet_id );
            $has_urgent = (bool)$wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$vax_table}
                 WHERE user_id=%d AND pet_id=%d
                 AND next_date IS NOT NULL AND next_date <= %s",
                $user_id, $pet->id,
                date('Y-m-d', strtotime('+30 days'))
            ) );
            $tab_url = add_query_arg('pet', $pet->id, home_url('/mypage/vax-wallet/'));
        ?>
        <a href="<?php echo esc_url($tab_url); ?>"
           class="psc-vax-pet-tab <?php echo $is_active ? 'active' : ''; ?>">
            <?php if ( $pet->photo_url ) : ?>
                <img src="<?php echo esc_url($pet->photo_url); ?>"
                     alt="<?php echo esc_attr($pet->pet_name); ?>">
            <?php else : ?>🐾<?php endif; ?>
            <?php echo esc_html($pet->pet_name); ?>
            <?php if ( $has_urgent && ! $is_active ) : ?>
                <span class="tab-urgent-dot"></span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- 헤더 -->
    <div class="psc-vax-header">
        <div class="psc-vax-title">
            💉 접종 지갑
            <?php foreach ( $pets as $p ) {
                if ( (int)$p->id === $active_pet_id ) {
                    echo '<span>— ' . esc_html($p->pet_name) . '</span>';
                    break;
                }
            } ?>
        </div>
        <button class="btn-add-vax" onclick="pscOpenVaxForm()">＋ 접종 추가</button>
    </div>

    <!-- 임박 배너 -->
    <?php if ( $urgent_count > 0 ) : ?>
    <div class="psc-vax-urgent-banner">
        🔔 곧 접종이 필요한 항목이 <strong><?php echo $urgent_count; ?>건</strong> 있어요!
    </div>
    <?php endif; ?>

    <!-- 폼 패널 -->
    <div class="psc-vax-form-panel" id="pscVaxFormPanel">
        <div class="vax-form-title" id="pscVaxFormTitle">💉 접종 기록 추가</div>
        <form id="pscVaxForm" onsubmit="return false;">
            <input type="hidden" id="vaxId"    name="vax_id" value="0">
            <input type="hidden" id="vaxPetId" name="pet_id" value="<?php echo esc_attr($active_pet_id); ?>">
            <input type="hidden" id="vaxNonce" name="nonce"  value="<?php echo esc_attr($nonce_save); ?>">

            <div class="vax-form-grid">

                <!-- 접종 선택 -->
                <div class="vax-form-field full">
                    <label>접종 항목 <span style="color:#dc2626;">*</span></label>
                    <select id="vaxNameSelect" onchange="pscVaxSelectChange(this)">
                        <option value="">-- 선택하세요 --</option>
                        <?php foreach ( $vax_list as $name => $icon ) : ?>
                        <option value="<?php echo esc_attr($name); ?>">
                            <?php echo esc_html($icon . ' ' . $name); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <div id="vaxCustomNameWrap">
                        <input type="text" id="vaxCustomName"
                               placeholder="접종명 직접 입력" maxlength="100">
                    </div>
                </div>

                <!-- 접종일 -->
                <div class="vax-form-field">
                    <label>접종일 <span style="color:#dc2626;">*</span></label>
                    <input type="date" id="vaxDate" name="vax_date">
                </div>

                <!-- 다음 접종일 -->
                <div class="vax-form-field">
                    <label>다음 접종 예정일</label>
                    <input type="date" id="vaxNextDate" name="next_date">
                </div>

                <!-- 병원명 -->
                <div class="vax-form-field full">
                    <label>병원명</label>
                    <input type="text" id="vaxHospital" name="hospital"
                           placeholder="예: 행복동물병원" maxlength="100">
                </div>

                <!-- 메모 -->
                <div class="vax-form-field full">
                    <label>메모</label>
                    <textarea id="vaxMemo" name="memo"
                              placeholder="이상반응, 특이사항 등" maxlength="500"></textarea>
                </div>

                <!-- 증빙자료 업로드 -->
                <div class="vax-form-field full">
                    <div class="vax-attach-section">
                        <div class="vax-attach-label">📎 증빙자료</div>
                        <div class="vax-attach-hint">
                            이미지(JPG·PNG·WEBP) 또는 PDF · 최대 3개 · 각 10MB 이하
                        </div>
                        <div class="vax-attach-previews" id="vaxAttachPreviews"></div>
                        <div class="vax-attach-upload-btn" id="vaxAttachUploadBtn"
                             onclick="document.getElementById('vaxAttachInput').click()">＋</div>
                        <input type="file" id="vaxAttachInput"
                               class="vax-attach-upload-input"
                               accept="image/jpeg,image/png,image/webp,application/pdf"
                               multiple onchange="pscHandleAttachFiles(this)">
                    </div>
                </div>

            </div>

            <div class="vax-form-actions">
                <button type="button" class="btn-vax-cancel" onclick="pscCloseVaxForm()">취소</button>
                <button type="button" class="btn-vax-save"   onclick="pscSaveVax()">저장하기</button>
            </div>
        </form>
    </div>

    <!-- 접종 카드 목록 -->
    <?php if ( empty($records) ) : ?>
    <div class="psc-vax-empty" id="pscVaxEmpty">
        <div class="icon">💉</div>
        <p>아직 접종 기록이 없어요.<br>첫 번째 접종을 기록해보세요!</p>
    </div>
    <?php else : ?>
    <div class="psc-vax-list" id="pscVaxList">
        <?php foreach ( $records as $rec ) :
            echo psc_render_vax_card($rec);
        endforeach; ?>
    </div>
    <?php endif; ?>

    <?php endif; ?>

    <!-- 라이트박스 -->
    <div class="psc-vax-lightbox" id="pscVaxLightbox"
         onclick="pscCloseLightbox(event)">
        <div class="lightbox-inner" id="pscLightboxInner"></div>
    </div>

    <!-- 토스트 -->
    <div class="psc-vax-toast" id="pscVaxToast"></div>

    <script>
    (function () {
        const AJAX_URL  = '<?php echo esc_js( admin_url('admin-ajax.php') ); ?>';
        const NONCE_DEL = '<?php echo esc_js( $nonce_del ); ?>';
        const NONCE_ATT = '<?php echo esc_js( $nonce_att ); ?>';
        const VAX_LIST  = <?php echo json_encode( array_keys($vax_list), JSON_UNESCAPED_UNICODE ); ?>;
        const MAX_FILES = 3;
        const MAX_SIZE  = 10 * 1024 * 1024;

        let pendingFiles    = [];
        let existingAttachs = [];

        /* ── 접종 선택 변경 ── */
        window.pscVaxSelectChange = function (sel) {
            const wrap = document.getElementById('vaxCustomNameWrap');
            wrap.style.display = sel.value === '직접 입력' ? '' : 'none';
            if (sel.value !== '직접 입력')
                document.getElementById('vaxCustomName').value = '';
        };

        /* ── 폼 열기 ── */
        window.pscOpenVaxForm = function (recData) {
            const panel = document.getElementById('pscVaxFormPanel');
            panel.classList.add('open');
            panel.scrollIntoView({ behavior:'smooth', block:'start' });

            pendingFiles    = [];
            existingAttachs = [];

            if (!recData) {
                document.getElementById('pscVaxFormTitle').textContent = '💉 접종 기록 추가';
                document.getElementById('pscVaxForm').reset();
                document.getElementById('vaxId').value = '0';
                document.getElementById('vaxCustomNameWrap').style.display = 'none';
                document.getElementById('vaxDate').value = pscTodayStr();
            } else {
                document.getElementById('pscVaxFormTitle').textContent = '✏️ 접종 기록 수정';
                document.getElementById('vaxId').value       = recData.id;
                document.getElementById('vaxDate').value     = recData.vax_date  || '';
                document.getElementById('vaxNextDate').value = recData.next_date || '';
                document.getElementById('vaxHospital').value = recData.hospital  || '';
                document.getElementById('vaxMemo').value     = recData.memo      || '';

                const sel = document.getElementById('vaxNameSelect');
                if (VAX_LIST.includes(recData.vax_name) && recData.vax_name !== '직접 입력') {
                    sel.value = recData.vax_name;
                    document.getElementById('vaxCustomNameWrap').style.display = 'none';
                } else {
                    sel.value = '직접 입력';
                    document.getElementById('vaxCustomNameWrap').style.display = '';
                    document.getElementById('vaxCustomName').value = recData.vax_name;
                }

                if (recData.attachments && recData.attachments.length) {
                    existingAttachs = recData.attachments.map(a => ({
                        id       : a.id,
                        file_url : a.file_url,
                        file_type: a.file_type,
                        att_id   : a.att_id,
                    }));
                }
            }
            pscRenderAttachPreviews();
        };

        window.pscCloseVaxForm = function () {
            document.getElementById('pscVaxFormPanel').classList.remove('open');
        };

        /* ── 첨부 파일 선택 ── */
        window.pscHandleAttachFiles = function (input) {
            const total = pendingFiles.length + existingAttachs.length;
            const files = Array.from(input.files);
            let added   = 0;

            for (const file of files) {
                if (total + added >= MAX_FILES) {
                    pscVaxToast(`최대 ${MAX_FILES}개까지 첨부할 수 있어요`); break;
                }
                if (file.size > MAX_SIZE) {
                    pscVaxToast(`${file.name} 파일이 10MB를 초과해요`); continue;
                }
                const allowed = ['image/jpeg','image/png','image/webp','application/pdf'];
                if (!allowed.includes(file.type)) {
                    pscVaxToast('이미지 또는 PDF 파일만 첨부할 수 있어요'); continue;
                }
                const isPdf = file.type === 'application/pdf';
                pendingFiles.push({
                    file,
                    previewUrl: isPdf ? null : URL.createObjectURL(file),
                    type: isPdf ? 'pdf' : 'image',
                });
                added++;
            }
            input.value = '';
            pscRenderAttachPreviews();
        };

        /* ── 첨부 미리보기 렌더 ── */
        function pscRenderAttachPreviews() {
            const wrap  = document.getElementById('vaxAttachPreviews');
            const btn   = document.getElementById('vaxAttachUploadBtn');
            const total = existingAttachs.length + pendingFiles.length;
            wrap.innerHTML = '';

            existingAttachs.forEach((a, i) => {
                const div = document.createElement('div');
                div.className = 'vax-attach-thumb';
                if (a.file_type === 'pdf') {
                    div.innerHTML = `<span class="pdf-icon">📄</span>`;
                } else {
                    div.innerHTML = `<img src="${a.file_url}" alt="증빙">`;
                }
                div.innerHTML += `<button class="btn-remove-attach"
                    onclick="pscRemoveExistingAttach(${i})">✕</button>`;
                div.querySelector('img, span.pdf-icon')?.addEventListener('click', () => {
                    pscOpenLightbox(a.file_url, a.file_type);
                });
                wrap.appendChild(div);
            });

            pendingFiles.forEach((pf, i) => {
                const div = document.createElement('div');
                div.className = 'vax-attach-thumb';
                if (pf.type === 'pdf') {
                    div.innerHTML = `<span class="pdf-icon">📄</span>`;
                } else {
                    div.innerHTML = `<img src="${pf.previewUrl}" alt="증빙">`;
                }
                div.innerHTML += `<button class="btn-remove-attach"
                    onclick="pscRemovePendingAttach(${i})">✕</button>`;
                wrap.appendChild(div);
            });

            btn.style.display = total >= MAX_FILES ? 'none' : '';
        }

        window.pscRemoveExistingAttach = function (idx) {
            existingAttachs.splice(idx, 1);
            pscRenderAttachPreviews();
        };
        window.pscRemovePendingAttach = function (idx) {
            pendingFiles.splice(idx, 1);
            pscRenderAttachPreviews();
        };

        /* ── 저장 ── */
        window.pscSaveVax = async function () {
            let vaxName = document.getElementById('vaxNameSelect').value;
            if (vaxName === '직접 입력')
                vaxName = document.getElementById('vaxCustomName').value.trim();
            if (!vaxName || vaxName === '-- 선택하세요 --') {
                pscVaxToast('접종 항목을 선택하거나 입력해주세요'); return;
            }
            const vaxDate  = document.getElementById('vaxDate').value;
            const nextDate = document.getElementById('vaxNextDate').value;
            if (!vaxDate) { pscVaxToast('접종일을 입력해주세요'); return; }
            if (nextDate && nextDate < vaxDate) {
                pscVaxToast('다음 접종일은 접종일 이후여야 해요'); return;
            }

            const saveBtn = document.querySelector('.btn-vax-save');
            saveBtn.disabled = true; saveBtn.textContent = '저장 중...';

            const fd = new FormData();
            fd.append('action',           'psc_save_vax');
            fd.append('nonce',            document.getElementById('vaxNonce').value);
            fd.append('vax_id',           document.getElementById('vaxId').value);
            fd.append('pet_id',           document.getElementById('vaxPetId').value);
            fd.append('vax_name',         vaxName);
            fd.append('vax_date',         vaxDate);
            fd.append('next_date',        nextDate);
            fd.append('hospital',         document.getElementById('vaxHospital').value.trim());
            fd.append('memo',             document.getElementById('vaxMemo').value.trim());
            fd.append('keep_attach_ids',  JSON.stringify(existingAttachs.map(a => a.id)));

            const res  = await fetch(AJAX_URL, { method:'POST', body:fd });
            const data = await res.json();

            if (!data.success) {
                pscVaxToast('저장 실패: ' + (data.data || '오류'));
                saveBtn.disabled = false; saveBtn.textContent = '저장하기';
                return;
            }

            const vaxId = data.data.id;

            for (const pf of pendingFiles) {
                const afd = new FormData();
                afd.append('action',  'psc_upload_vax_attach');
                afd.append('nonce',   NONCE_ATT);
                afd.append('vax_id',  vaxId);
                afd.append('vax_att', pf.file);
                await fetch(AJAX_URL, { method:'POST', body:afd });
            }

            const fd2 = new FormData();
            fd2.append('action', 'psc_get_vax_record');
            fd2.append('nonce',  NONCE_ATT);
            fd2.append('vax_id', vaxId);
            const res2  = await fetch(AJAX_URL, { method:'POST', body:fd2 });
            const data2 = await res2.json();

            saveBtn.disabled = false; saveBtn.textContent = '저장하기';
            pscVaxToast('저장되었어요 💉');
            pscCloseVaxForm();

            if (data2.success) pscRefreshVaxCard(data2.data);
            else               pscRefreshVaxCard(data.data);
        };

        /* ── 삭제 ── */
        window.pscDeleteVax = function (vaxId) {
            if (!confirm('접종 기록을 삭제할까요?\n(증빙자료도 함께 삭제돼요)')) return;
            const fd = new FormData();
            fd.append('action', 'psc_delete_vax');
            fd.append('nonce',  NONCE_DEL);
            fd.append('vax_id', vaxId);
            fetch(AJAX_URL, { method:'POST', body:fd })
                .then(r => r.json())
                .then(d => {
                    if (d.success) {
                        document.getElementById('vax-card-' + vaxId)?.remove();
                        pscVaxToast('삭제되었어요');
                        pscCheckVaxEmpty();
                    } else {
                        pscVaxToast('삭제 실패');
                    }
                });
        };

        /* ── 수정 ── */
        window.pscEditVax = function (vaxId) {
            const card = document.getElementById('vax-card-' + vaxId);
            if (!card) return;
            const recData = JSON.parse(card.dataset.rec || '{}');
            pscOpenVaxForm(recData);
        };

        /* ── 라이트박스 ── */
        window.pscOpenLightbox = function (url, type) {
            const lb    = document.getElementById('pscVaxLightbox');
            const inner = document.getElementById('pscLightboxInner');
            if (type === 'pdf') {
                inner.innerHTML = `
                <div class="lightbox-pdf-wrap">
                    <div class="pdf-big">📄</div>
                    <p>PDF 파일은 브라우저에서 직접 열거나<br>다운로드하세요.</p>
                    <a class="btn-pdf-download" href="${url}" target="_blank" download>
                        ⬇️ 다운로드
                    </a>
                </div>
                <button class="lightbox-close" onclick="pscCloseLightbox()">✕</button>`;
            } else {
                inner.innerHTML = `
                <img src="${url}" alt="증빙자료">
                <button class="lightbox-close" onclick="pscCloseLightbox()">✕</button>`;
            }
            lb.classList.add('open');
        };

        window.pscCloseLightbox = function (e) {
            if (!e || e.target === document.getElementById('pscVaxLightbox')) {
                document.getElementById('pscVaxLightbox').classList.remove('open');
            }
        };

        /* ── 카드 갱신 ── */
        function pscRefreshVaxCard(rec) {
            let list = document.getElementById('pscVaxList');
            if (!list) {
                document.getElementById('pscVaxEmpty')?.remove();
                list = document.createElement('div');
                list.className = 'psc-vax-list';
                list.id        = 'pscVaxList';
                document.getElementById('pscVaxWrap').appendChild(list);
            }
            const existing = document.getElementById('vax-card-' + rec.id);
            const html     = pscBuildVaxCardHtml(rec);
            if (existing) existing.outerHTML = html;
            else          list.insertAdjacentHTML('afterbegin', html);
            pscRefreshUrgentBanner();
        }

        function pscCheckVaxEmpty() {
            const cards = document.querySelectorAll('.psc-vax-card');
            const list  = document.getElementById('pscVaxList');
            if (cards.length === 0 && list) {
                list.remove();
                document.getElementById('pscVaxWrap').insertAdjacentHTML('beforeend',
                    `<div class="psc-vax-empty" id="pscVaxEmpty">
                        <div class="icon">💉</div>
                        <p>아직 접종 기록이 없어요.</p>
                    </div>`);
            }
            pscRefreshUrgentBanner();
        }

        function pscRefreshUrgentBanner() {
            const cards    = document.querySelectorAll('.psc-vax-card.level-danger, .psc-vax-card.level-warn');
            const existing = document.querySelector('.psc-vax-urgent-banner');
            if (cards.length > 0) {
                const msg = `🔔 곧 접종이 필요한 항목이 <strong>${cards.length}건</strong> 있어요!`;
                if (existing) existing.innerHTML = msg;
                else {
                    const header = document.querySelector('.psc-vax-header');
                    if (header) header.insertAdjacentHTML('afterend',
                        `<div class="psc-vax-urgent-banner">${msg}</div>`);
                }
            } else {
                existing?.remove();
            }
        }

        /* ── 카드 HTML 빌더 ── */
        function pscBuildVaxCardHtml(r) {
            const icons = {
                '종합백신 DHPPL':'💉','코로나 장염':'💉','켄넬코프':'💉',
                '광견병':'💉','인플루엔자':'💉','신종플루':'💉',
                '라임병':'💉','렙토스피라':'💉',
                '심장사상충 예방':'🐛','내부기생충 구충':'🐛',
                '외부기생충 (벼룩/진드기)':'🐛',
                '구강 스케일링':'🦷','건강검진':'🏥',
            };
            const icon = icons[r.vax_name] || '💉';

            let ddayHtml = '', levelClass = 'level-none';
            if (r.next_date) {
                const diff = Math.ceil((new Date(r.next_date) - new Date()) / 86400000);
                let label = '', badgeClass = '';
                if      (diff < 0)   { label=`⚠️ ${Math.abs(diff)}일 지남`; badgeClass='badge-danger'; levelClass='level-danger'; }
                else if (diff===0)   { label='🔔 오늘!';                     badgeClass='badge-danger'; levelClass='level-danger'; }
                else if (diff<=7)    { label=`🔔 D-${diff}`;                 badgeClass='badge-danger'; levelClass='level-danger'; }
                else if (diff<=30)   { label=`⏰ D-${diff}`;                 badgeClass='badge-warn';   levelClass='level-warn';   }
                else                 { label=`D-${diff}`;                    badgeClass='badge-ok';     levelClass='level-ok';     }
                ddayHtml = `<span class="vax-dday-badge ${badgeClass}">${label}</span>`;
            }

            const metaItems = [];
            if (r.vax_date)  metaItems.push(`<span class="vax-meta-item">📅 접종일 ${r.vax_date}</span>`);
            if (r.next_date) metaItems.push(`<span class="vax-meta-item">🔔 다음 ${r.next_date}</span>`);
            if (r.hospital)  metaItems.push(`<span class="vax-meta-item">🏥 ${r.hospital}</span>`);

            const memoHtml = r.memo
                ? `<div class="vax-card-memo">📝 ${r.memo}</div>` : '';

            let attachHtml = '';
            if (r.attachments && r.attachments.length) {
                const thumbs = r.attachments.map(a => {
                    if (a.file_type === 'pdf') {
                        return `<div class="vax-card-att-thumb"
                                     onclick="pscOpenLightbox('${a.file_url}','pdf')">
                                    <span class="pdf-badge">📄</span>
                                </div>`;
                    }
                    return `<div class="vax-card-att-thumb"
                                 onclick="pscOpenLightbox('${a.file_url}','image')">
                                <img src="${a.file_url}" alt="증빙">
                            </div>`;
                }).join('');
                attachHtml = `
                <div class="vax-card-attachments">
                    <span class="vax-att-count">📎 증빙 ${r.attachments.length}개</span>
                    ${thumbs}
                </div>`;
            }

            const recJson = JSON.stringify(r).replace(/"/g,'&quot;');
            return `
            <div class="psc-vax-card ${levelClass}" id="vax-card-${r.id}" data-rec="${recJson}">
                <div class="vax-card-inner">
                    <div class="vax-card-top">
                        <div class="vax-card-name">${icon} ${r.vax_name}</div>
                        ${ddayHtml}
                    </div>
                    <div class="vax-card-meta">${metaItems.join('')}</div>
                    ${memoHtml}
                </div>
                ${attachHtml}
                <div class="vax-card-actions">
                    <button class="btn-vax-edit" onclick="pscEditVax(${r.id})">✏️ 수정</button>
                    <button class="btn-vax-del"  onclick="pscDeleteVax(${r.id})">🗑️</button>
                </div>
            </div>`;
        }

        function pscTodayStr() {
            const d = new Date();
            return d.getFullYear() + '-'
                 + String(d.getMonth()+1).padStart(2,'0') + '-'
                 + String(d.getDate()).padStart(2,'0');
        }

        window.pscVaxToast = function (msg) {
            const t = document.getElementById('pscVaxToast');
            t.textContent = msg;
            t.classList.add('show');
            setTimeout(() => t.classList.remove('show'), 2500);
        };

    })();
    </script>

    </div><!-- /.psc-vax-wrap -->
    <?php
}

/* ============================================================
   5. 카드 렌더 헬퍼 (PHP)
   ============================================================ */
function psc_render_vax_card( object $rec ): string {
    global $wpdb;
    $att_table = $wpdb->prefix . PSC_VAX_ATT_TABLE;
    $vax_list  = psc_get_vax_list();
    $icon      = $vax_list[$rec->vax_name] ?? '💉';
    $primary   = '#224471';

    $dday_html   = '';
    $level_class = 'level-none';
    if ( $rec->next_date ) {
        $d = psc_vax_dday($rec->next_date);
        $level_class = 'level-' . $d['level'];
        $badge_class = match($d['level']) {
            'danger' => 'badge-danger',
            'warn'   => 'badge-warn',
            'ok'     => 'badge-ok',
            default  => '',
        };
        if ( $badge_class ) {
            $dday_html = sprintf(
                '<span class="vax-dday-badge %s">%s</span>',
                $badge_class, esc_html($d['label'])
            );
        }
    }

    $meta = [];
    if ( $rec->vax_date )  $meta[] = '<span class="vax-meta-item">📅 접종일 ' . esc_html($rec->vax_date) . '</span>';
    if ( $rec->next_date ) $meta[] = '<span class="vax-meta-item">🔔 다음 '  . esc_html($rec->next_date) . '</span>';
    if ( $rec->hospital )  $meta[] = '<span class="vax-meta-item">🏥 '       . esc_html($rec->hospital) . '</span>';

    $memo_html = $rec->memo
        ? '<div class="vax-card-memo">📝 ' . esc_html($rec->memo) . '</div>' : '';

    $attachments = isset($rec->attachments)
        ? $rec->attachments
        : $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$att_table} WHERE vax_id = %d ORDER BY created_at ASC",
            $rec->id
          ) );

    $attach_html = '';
    if ( ! empty($attachments) ) {
        $thumbs = '';
        foreach ( $attachments as $a ) {
            if ( $a->file_type === 'pdf' ) {
                $thumbs .= sprintf(
                    '<div class="vax-card-att-thumb"
                          onclick="pscOpenLightbox(\'%s\',\'pdf\')">
                        <span class="pdf-badge">📄</span>
                    </div>',
                    esc_js($a->file_url)
                );
            } else {
                $thumbs .= sprintf(
                    '<div class="vax-card-att-thumb"
                          onclick="pscOpenLightbox(\'%s\',\'image\')">
                        <img src="%s" alt="증빙">
                    </div>',
                    esc_js($a->file_url), esc_url($a->file_url)
                );
            }
        }
        $attach_html = '<div class="vax-card-attachments">'
            . '<span class="vax-att-count">📎 증빙 ' . count($attachments) . '개</span>'
            . $thumbs . '</div>';
    }

    $rec_arr               = (array)$rec;
    $rec_arr['attachments'] = array_map(fn($a) => (array)$a, $attachments);
    $rec_json              = esc_attr( wp_json_encode($rec_arr) );

    return "
    <div class=\"psc-vax-card {$level_class}\" id=\"vax-card-{$rec->id}\" data-rec=\"{$rec_json}\">
        <div class=\"vax-card-inner\">
            <div class=\"vax-card-top\">
                <div class=\"vax-card-name\">{$icon} " . esc_html($rec->vax_name) . "</div>
                {$dday_html}
            </div>
            <div class=\"vax-card-meta\">" . implode('', $meta) . "</div>
            {$memo_html}
        </div>
        {$attach_html}
        <div class=\"vax-card-actions\">
            <button class=\"btn-vax-edit\" onclick=\"pscEditVax({$rec->id})\">✏️ 수정</button>
            <button class=\"btn-vax-del\"  onclick=\"pscDeleteVax({$rec->id})\">🗑️</button>
        </div>
    </div>";
}

/* ============================================================
   6. AJAX — 접종 기록 저장
   ============================================================ */
add_action( 'wp_ajax_psc_save_vax', 'psc_ajax_save_vax' );
function psc_ajax_save_vax(): void {
    check_ajax_referer('psc_vax_save', 'nonce');
    $user_id = get_current_user_id();
    if ( ! $user_id ) wp_send_json_error('로그인 필요');

    global $wpdb;
    $table      = $wpdb->prefix . PSC_VAX_TABLE;
    $att_table  = $wpdb->prefix . PSC_VAX_ATT_TABLE;
    $pets_table = $wpdb->prefix . PSC_PETS_TABLE;

    $vax_id    = (int)( $_POST['vax_id']   ?? 0 );
    $pet_id    = (int)( $_POST['pet_id']   ?? 0 );
    $vax_name  = sanitize_text_field( $_POST['vax_name']  ?? '' );
    $vax_date  = sanitize_text_field( $_POST['vax_date']  ?? '' );
    $next_date = sanitize_text_field( $_POST['next_date'] ?? '' );
    $hospital  = sanitize_text_field( $_POST['hospital']  ?? '' );
    $memo      = sanitize_textarea_field( $_POST['memo']  ?? '' );
    $keep_ids  = json_decode( sanitize_text_field($_POST['keep_attach_ids'] ?? '[]'), true );
    if ( ! is_array($keep_ids) ) $keep_ids = [];

    if ( ! $vax_name ) wp_send_json_error('접종 항목을 입력해주세요');
    if ( ! $vax_date || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $vax_date) )
        wp_send_json_error('접종일을 확인해주세요');
    if ( $next_date && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $next_date) ) $next_date = '';
    if ( $next_date && $next_date < $vax_date )
        wp_send_json_error('다음 접종일은 접종일 이후여야 해요');

    $owner = $wpdb->get_var( $wpdb->prepare(
        "SELECT user_id FROM {$pets_table} WHERE id = %d", $pet_id
    ) );
    if ( (int)$owner !== $user_id ) wp_send_json_error('권한 없음');

    $data = [
        'user_id'    => $user_id,
        'pet_id'     => $pet_id,
        'vax_name'   => $vax_name,
        'vax_date'   => $vax_date,
        'next_date'  => $next_date ?: null,
        'hospital'   => $hospital  ?: null,
        'memo'       => $memo      ?: null,
        'updated_at' => current_time('mysql'),
    ];

    if ( $vax_id > 0 ) {
        $rec_owner = $wpdb->get_var( $wpdb->prepare(
            "SELECT user_id FROM {$table} WHERE id = %d", $vax_id
        ) );
        if ( (int)$rec_owner !== $user_id ) wp_send_json_error('권한 없음');
        $wpdb->update( $table, $data, ['id' => $vax_id] );

        $all_att = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, att_id FROM {$att_table} WHERE vax_id = %d AND user_id = %d",
            $vax_id, $user_id
        ) );
        foreach ( $all_att as $a ) {
            if ( ! in_array((int)$a->id, array_map('intval', $keep_ids), true) ) {
                wp_delete_attachment( (int)$a->att_id, true );
                $wpdb->delete( $att_table, ['id' => $a->id] );
            }
        }
    } else {
        $data['created_at'] = current_time('mysql');
        $wpdb->insert( $table, $data );
        $vax_id = $wpdb->insert_id;
    }

    $saved = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$table} WHERE id = %d", $vax_id
    ) );
    wp_send_json_success( $saved );
}

/* ============================================================
   7. AJAX — 증빙 업로드
   ============================================================ */
add_action( 'wp_ajax_psc_upload_vax_attach', 'psc_ajax_upload_vax_attach' );
function psc_ajax_upload_vax_attach(): void {
    check_ajax_referer('psc_vax_attach', 'nonce');
    $user_id = get_current_user_id();
    if ( ! $user_id ) wp_send_json_error('로그인 필요');
    if ( empty($_FILES['vax_att']) ) wp_send_json_error('파일 없음');

    global $wpdb;
    $att_table = $wpdb->prefix . PSC_VAX_ATT_TABLE;
    $vax_table = $wpdb->prefix . PSC_VAX_TABLE;
    $vax_id    = (int)( $_POST['vax_id'] ?? 0 );

    $owner = $wpdb->get_var( $wpdb->prepare(
        "SELECT user_id FROM {$vax_table} WHERE id = %d", $vax_id
    ) );
    if ( (int)$owner !== $user_id ) wp_send_json_error('권한 없음');

    $count = (int)$wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$att_table} WHERE vax_id = %d", $vax_id
    ) );
    if ( $count >= 3 ) wp_send_json_error('최대 3개까지 첨부할 수 있어요');

    $file    = $_FILES['vax_att'];
    $allowed = ['image/jpeg','image/png','image/webp','application/pdf'];
    $finfo   = finfo_open(FILEINFO_MIME_TYPE);
    $mime    = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if ( ! in_array($mime, $allowed, true) ) wp_send_json_error('허용되지 않는 파일 형식');
    if ( $file['size'] > 10 * 1024 * 1024 ) wp_send_json_error('파일 크기는 10MB 이하여야 해요');

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg';
    $_FILES['vax_att']['name'] = 'vax-' . $user_id . '-' . $vax_id . '-' . time() . '.' . $ext;

    $att_id = media_handle_upload('vax_att', 0);
    if ( is_wp_error($att_id) ) wp_send_json_error( $att_id->get_error_message() );

    $file_type = $mime === 'application/pdf' ? 'pdf' : 'image';
    $wpdb->insert( $att_table, [
        'vax_id'     => $vax_id,
        'user_id'    => $user_id,
        'att_id'     => $att_id,
        'file_url'   => wp_get_attachment_url($att_id),
        'file_type'  => $file_type,
        'created_at' => current_time('mysql'),
    ] );

    wp_send_json_success([
        'id'        => $wpdb->insert_id,
        'file_url'  => wp_get_attachment_url($att_id),
        'file_type' => $file_type,
        'att_id'    => $att_id,
    ]);
}

/* ============================================================
   8. AJAX — 접종 기록 단건 조회
   ============================================================ */
add_action( 'wp_ajax_psc_get_vax_record', 'psc_ajax_get_vax_record' );
function psc_ajax_get_vax_record(): void {
    check_ajax_referer('psc_vax_attach', 'nonce');
    $user_id = get_current_user_id();
    if ( ! $user_id ) wp_send_json_error('로그인 필요');

    global $wpdb;
    $table     = $wpdb->prefix . PSC_VAX_TABLE;
    $att_table = $wpdb->prefix . PSC_VAX_ATT_TABLE;
    $vax_id    = (int)( $_POST['vax_id'] ?? 0 );

    $rec = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$table} WHERE id = %d AND user_id = %d",
        $vax_id, $user_id
    ) );
    if ( ! $rec ) wp_send_json_error('기록 없음');

    $rec->attachments = $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM {$att_table} WHERE vax_id = %d ORDER BY created_at ASC",
        $vax_id
    ) );
    wp_send_json_success( $rec );
}

/* ============================================================
   9. AJAX — 접종 기록 삭제
   ============================================================ */
add_action( 'wp_ajax_psc_delete_vax', 'psc_ajax_delete_vax' );
function psc_ajax_delete_vax(): void {
    check_ajax_referer('psc_vax_delete', 'nonce');
    $user_id = get_current_user_id();
    if ( ! $user_id ) wp_send_json_error('로그인 필요');

    global $wpdb;
    $table     = $wpdb->prefix . PSC_VAX_TABLE;
    $att_table = $wpdb->prefix . PSC_VAX_ATT_TABLE;
    $vax_id    = (int)( $_POST['vax_id'] ?? 0 );
    if ( ! $vax_id ) wp_send_json_error('잘못된 요청');

    $owner = $wpdb->get_var( $wpdb->prepare(
        "SELECT user_id FROM {$table} WHERE id = %d", $vax_id
    ) );
    if ( (int)$owner !== $user_id ) wp_send_json_error('권한 없음');

    $atts = $wpdb->get_results( $wpdb->prepare(
        "SELECT att_id FROM {$att_table} WHERE vax_id = %d", $vax_id
    ) );
    foreach ( $atts as $a ) wp_delete_attachment( (int)$a->att_id, true );
    $wpdb->delete( $att_table, ['vax_id' => $vax_id] );
    $wpdb->delete( $table, ['id' => $vax_id, 'user_id' => $user_id] );
    wp_send_json_success();
}

/* ============================================================
   10. 관리자 메뉴 — 접종 증빙 관리
   ============================================================ */
add_action( 'admin_menu', 'psc_vax_admin_menu' );
function psc_vax_admin_menu(): void {
    add_submenu_page(
        'psc-settings',
        '접종 증빙 관리',
        '접종 증빙 관리',
        'manage_options',
        'psc-vax-records',
        'psc_vax_admin_page'
    );
}

function psc_vax_admin_page(): void {
    global $wpdb;
    $vax_table  = $wpdb->prefix . PSC_VAX_TABLE;
    $att_table  = $wpdb->prefix . PSC_VAX_ATT_TABLE;
    $pets_table = $wpdb->prefix . PSC_PETS_TABLE;

    $search   = sanitize_text_field( $_GET['s']      ?? '' );
    $per_page = 20;
    $paged    = max(1, (int)( $_GET['paged'] ?? 1 ));
    $offset   = ($paged - 1) * $per_page;

    $where = "WHERE 1=1";
    $args  = [];
    if ( $search ) {
        $where .= " AND (u.display_name LIKE %s OR p.pet_name LIKE %s OR v.vax_name LIKE %s OR v.hospital LIKE %s)";
        $like   = '%' . $wpdb->esc_like($search) . '%';
        $args   = array_merge($args, [$like, $like, $like, $like]);
    }
    $where .= " AND EXISTS (SELECT 1 FROM {$att_table} a WHERE a.vax_id = v.id)";

    $count_sql = "SELECT COUNT(*) FROM {$vax_table} v
                  JOIN {$pets_table} p ON p.id = v.pet_id
                  JOIN {$wpdb->users} u ON u.ID = v.user_id
                  {$where}";
    $total = $args
        ? (int)$wpdb->get_var( $wpdb->prepare($count_sql, ...$args) )
        : (int)$wpdb->get_var($count_sql);

    $data_sql = "SELECT v.*, p.pet_name, u.display_name, u.user_email
                 FROM {$vax_table} v
                 JOIN {$pets_table} p ON p.id = v.pet_id
                 JOIN {$wpdb->users} u ON u.ID = v.user_id
                 {$where}
                 ORDER BY v.created_at DESC
                 LIMIT %d OFFSET %d";
    $rows = $args
        ? $wpdb->get_results( $wpdb->prepare($data_sql, ...array_merge($args, [$per_page, $offset])) )
        : $wpdb->get_results( $wpdb->prepare($data_sql, $per_page, $offset) );

    $total_pages = ceil($total / $per_page);
    ?>
    <div class="wrap">
    <h1>💉 접종 증빙 관리</h1>

    <style>
    .psc-admin-vax-wrap { margin-top: 16px; }
    .psc-admin-search   { display: flex; gap: 8px; margin-bottom: 16px; align-items: center; }
    .psc-admin-search input {
        padding: 6px 12px;
        border: 1px solid #8c8f94;
        border-radius: 4px;
        font-size: .9rem;
        width: 260px;
    }
    .psc-admin-search button {
        padding: 6px 14px;
        background: #2271b1;
        color: #fff;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: .9rem;
    }
    .psc-vax-admin-table { width: 100%; border-collapse: collapse; background: #fff; }
    .psc-vax-admin-table th {
        background: #f0f0f1;
        padding: 10px 12px;
        text-align: left;
        font-size: .85rem;
        border-bottom: 2px solid #c3c4c7;
    }
    .psc-vax-admin-table td {
        padding: 10px 12px;
        border-bottom: 1px solid #f0f0f1;
        font-size: .85rem;
        vertical-align: middle;
    }
    .psc-vax-admin-table tr:hover td { background: #f9f9f9; }
    .att-thumbs { display: flex; gap: 6px; flex-wrap: wrap; }
    .att-thumb-admin {
        width: 52px; height: 52px;
        border-radius: 6px;
        overflow: hidden;
        border: 1.5px solid #e5e8eb;
        cursor: pointer;
        transition: all .15s;
        background: #f4f6f9;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .att-thumb-admin:hover { border-color: #2271b1; transform: scale(1.08); }
    .att-thumb-admin img   { width: 100%; height: 100%; object-fit: cover; }
    .att-thumb-admin .pdf  { font-size: 1.5rem; }
    .badge-danger-admin {
        background: #fee2e2; color: #dc2626;
        padding: 2px 7px; border-radius: 4px;
        font-size: .74rem; font-weight: 700;
    }
    .badge-warn-admin {
        background: #fffbeb; color: #92400e;
        padding: 2px 7px; border-radius: 4px;
        font-size: .74rem; font-weight: 700;
    }
    .psc-admin-lb {
        position: fixed; inset: 0;
        background: rgba(0,0,0,.85);
        z-index: 999999;
        display: none;
        align-items: center;
        justify-content: center;
    }
    .psc-admin-lb.open { display: flex; }
    .psc-admin-lb img  {
        max-width: 88vw; max-height: 85vh;
        border-radius: 12px;
        box-shadow: 0 8px 40px rgba(0,0,0,.5);
    }
    .psc-admin-lb-close {
        position: absolute;
        top: 20px; right: 24px;
        background: #fff;
        border: none;
        border-radius: 50%;
        width: 38px; height: 38px;
        font-size: 1.2rem;
        cursor: pointer;
        box-shadow: 0 2px 8px rgba(0,0,0,.3);
    }
    </style>

    <div class="psc-admin-vax-wrap">
        <form method="get" class="psc-admin-search">
            <input type="hidden" name="page" value="psc-vax-records">
            <input type="text" name="s" value="<?php echo esc_attr($search); ?>"
                   placeholder="회원명 / 반려견명 / 접종항목 / 병원명 검색">
            <button type="submit">🔍 검색</button>
            <?php if ($search) : ?>
            <a href="?page=psc-vax-records"
               style="padding:6px 12px;color:#646970;text-decoration:none;">✕ 초기화</a>
            <?php endif; ?>
        </form>

        <p style="color:#646970;margin-bottom:10px;">
            총 <strong><?php echo $total; ?>건</strong>
            <?php if ($search) echo '(검색 결과)'; ?>
        </p>

        <?php if ( empty($rows) ) : ?>
        <p style="padding:30px;text-align:center;color:#9ca3af;">
            <?php echo $search ? '검색 결과가 없어요.' : '아직 증빙자료가 없어요.'; ?>
        </p>
        <?php else : ?>
        <table class="psc-vax-admin-table">
            <thead>
                <tr>
                    <th>회원</th>
                    <th>반려견</th>
                    <th>접종 항목</th>
                    <th>접종일</th>
                    <th>다음 접종일</th>
                    <th>병원</th>
                    <th>증빙자료</th>
                    <th>등록일</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $rows as $row ) :
                $atts = $wpdb->get_results( $wpdb->prepare(
                    "SELECT * FROM {$att_table} WHERE vax_id = %d ORDER BY created_at ASC",
                    $row->id
                ) );
                $dday_html = '';
                if ( $row->next_date ) {
                    $d = psc_vax_dday($row->next_date);
                    if ( $d['level'] === 'danger' )
                        $dday_html = '<br><span class="badge-danger-admin">' . esc_html($d['label']) . '</span>';
                    elseif ( $d['level'] === 'warn' )
                        $dday_html = '<br><span class="badge-warn-admin">' . esc_html($d['label']) . '</span>';
                }
            ?>
            <tr>
                <td>
                    <?php echo esc_html($row->display_name); ?><br>
                    <span style="font-size:.74rem;color:#8b95a1;">
                        <?php echo esc_html($row->user_email); ?>
                    </span>
                </td>
                <td><?php echo esc_html($row->pet_name); ?></td>
                <td><?php echo esc_html($row->vax_name); ?></td>
                <td><?php echo esc_html($row->vax_date); ?></td>
                <td>
                    <?php echo $row->next_date
                        ? esc_html($row->next_date)
                        : '<span style="color:#d1d5db;">-</span>'; ?>
                    <?php echo $dday_html; ?>
                </td>
                <td><?php echo $row->hospital
                    ? esc_html($row->hospital)
                    : '<span style="color:#d1d5db;">-</span>'; ?></td>
                <td>
                    <div class="att-thumbs">
                    <?php foreach ( $atts as $a ) : ?>
                        <?php if ( $a->file_type === 'pdf' ) : ?>
                        <a href="<?php echo esc_url($a->file_url); ?>"
                           target="_blank" class="att-thumb-admin">
                            <span class="pdf">📄</span>
                        </a>
                        <?php else : ?>
                        <div class="att-thumb-admin"
                             onclick="pscAdminLb('<?php echo esc_js($a->file_url); ?>')">
                            <img src="<?php echo esc_url($a->file_url); ?>" alt="증빙">
                        </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    </div>
                </td>
                <td style="white-space:nowrap;">
                    <?php echo esc_html( date_i18n('Y.m.d', strtotime($row->created_at)) ); ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ( $total_pages > 1 ) : ?>
        <div style="margin-top:16px;display:flex;gap:6px;align-items:center;">
            <?php for ( $i = 1; $i <= $total_pages; $i++ ) :
                $url    = add_query_arg(['page'=>'psc-vax-records','paged'=>$i,'s'=>$search]);
                $active = $i === $paged;
            ?>
            <a href="<?php echo esc_url($url); ?>"
               style="padding:5px 12px;border-radius:4px;
                      border:1px solid #c3c4c7;
                      background:<?php echo $active ? '#2271b1' : '#fff'; ?>;
                      color:<?php echo $active ? '#fff' : '#374151'; ?>;
                      font-weight:<?php echo $active ? '700' : '400'; ?>;
                      text-decoration:none;font-size:.85rem;">
                <?php echo $i; ?>
            </a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- 관리자 라이트박스 -->
    <div class="psc-admin-lb" id="pscAdminLb"
         onclick="if(event.target===this) this.classList.remove('open')">
        <button class="psc-admin-lb-close"
                onclick="document.getElementById('pscAdminLb').classList.remove('open')">✕</button>
        <img id="pscAdminLbImg" src="" alt="증빙">
    </div>

    <script>
    function pscAdminLb(url) {
        document.getElementById('pscAdminLbImg').src = url;
        document.getElementById('pscAdminLb').classList.add('open');
    }
    </script>
    </div><!-- /.wrap -->
    <?php
}
