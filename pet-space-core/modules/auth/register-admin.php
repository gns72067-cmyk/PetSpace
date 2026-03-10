<?php
/**
 * 회원가입 관리자 설정
 * File: modules/auth/register-admin.php
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/* ============================================================
   1. 관리자 메뉴 등록
   ============================================================ */
add_action( 'admin_menu', 'psc_register_admin_menu' );
function psc_register_admin_menu(): void {
    add_menu_page(
        '회원가입 설정', '👤 회원가입 설정', 'manage_options',
        'psc-register-settings', 'psc_register_admin_page',
        'dashicons-welcome-learn-more', 25
    );
    add_submenu_page(
        'psc-register-settings', '입력 필드 관리', '📋 입력 필드',
        'manage_options', 'psc-register-settings', 'psc_register_admin_page'
    );
    add_submenu_page(
        'psc-register-settings', '약관 관리', '📜 약관 관리',
        'manage_options', 'psc-register-terms', 'psc_register_terms_page'
    );
    add_submenu_page(
        'psc-register-settings', '디자인 설정', '🎨 디자인 설정',
        'manage_options', 'psc-register-design', 'psc_register_design_page'
    );
    add_submenu_page( 
        'psc-register-settings', '회원 관리', '👥 회원 관리', 'manage_options', 'psc-register-members', 'psc_register_admin_page_members'
    );
  	add_submenu_page(
    'psc-register-settings', '이메일 설정', '📧 이메일 설정',
    'manage_options', 'psc-register-email', 'psc_register_admin_page_email'
);
}

/* ============================================================
   2. 관리자 전용 스타일/스크립트
   ============================================================ */
   add_action( 'admin_enqueue_scripts', function( $hook ) {
    if ( strpos( $hook, 'psc-register' ) === false ) return;
    wp_enqueue_script( 'jquery-ui-sortable' );
    wp_enqueue_style( 'wp-color-picker' );
    wp_enqueue_script( 'wp-color-picker' );
    wp_enqueue_media();
} );


/* ============================================================
   3. 기본 필드 정의
   ============================================================ */
function psc_register_default_fields(): array {
    return [
        [
            'id'          => 'user_login',
            'type'        => 'text',
            'label'       => '아이디',
            'placeholder' => '영문, 숫자, _, - 사용 가능',
            'required'    => true,
            'enabled'     => true,
            'system'      => true,   // 삭제 불가
            'duplicate_check' => true,
        ],
        [
            'id'          => 'user_pass',
            'type'        => 'password',
            'label'       => '비밀번호',
            'placeholder' => '8자 이상 입력',
            'required'    => true,
            'enabled'     => true,
            'system'      => true,
        ],
        [
            'id'          => 'user_pass2',
            'type'        => 'password',
            'label'       => '비밀번호 확인',
            'placeholder' => '비밀번호를 다시 입력하세요',
            'required'    => true,
            'enabled'     => true,
            'system'      => true,
        ],
        [
            'id'          => 'display_name',
            'type'        => 'text',
            'label'       => '이름',
            'placeholder' => '실명을 입력하세요',
            'required'    => true,
            'enabled'     => true,
            'system'      => true,
        ],
        [
            'id'          => 'user_email',
            'type'        => 'email',
            'label'       => '이메일',
            'placeholder' => 'example@email.com',
            'required'    => true,
            'enabled'     => true,
            'system'      => true,
        ],
        [
            'id'          => 'phone',
            'type'        => 'tel',
            'label'       => '휴대폰 번호',
            'placeholder' => '010-0000-0000',
            'required'    => false,
            'enabled'     => true,
            'system'      => false,
        ],
        [
            'id'          => 'birthday',
            'type'        => 'date',
            'label'       => '생년월일',
            'placeholder' => '',
            'required'    => false,
            'enabled'     => false,
            'system'      => false,
        ],
    ];
}

/* 저장된 필드 가져오기 (없으면 기본값) */
function psc_register_get_fields(): array {
    $saved = get_option( 'psc_register_fields', '' );
    if ( ! $saved ) return psc_register_default_fields();
    return json_decode( $saved, true ) ?: psc_register_default_fields();
}

/* ============================================================
   4. 입력 필드 관리 페이지
   ============================================================ */
function psc_register_admin_page(): void {

    /* 저장 처리 */
    if ( isset( $_POST['psc_save_fields'] ) &&
         check_admin_referer( 'psc_fields_save' ) ) {

        $raw    = $_POST['fields'] ?? [];
        $system = psc_register_default_fields();
        $sys_ids = array_column(
            array_filter( $system, fn($f) => $f['system'] ),
            'id'
        );

        $fields = [];
        foreach ( $raw as $f ) {
            $id = sanitize_key( $f['id'] ?? '' );
            if ( ! $id ) continue;

            $fields[] = [
                'id'          => $id,
                'type'        => sanitize_key( $f['type']        ?? 'text' ),
                'label'       => sanitize_text_field( $f['label']       ?? '' ),
                'placeholder' => sanitize_text_field( $f['placeholder'] ?? '' ),
                'required'    => ! empty( $f['required'] ),
                'enabled'     => ! empty( $f['enabled'] ),
                'system'      => in_array( $id, $sys_ids, true ),
                'duplicate_check' => ! empty( $f['duplicate_check'] ),
                'options'     => sanitize_textarea_field( $f['options'] ?? '' ),
            ];
        }

        update_option( 'psc_register_fields', json_encode( $fields ) );
        echo '<div class="notice notice-success is-dismissible"><p>✅ 저장되었습니다.</p></div>';
    }

    $fields     = psc_register_get_fields();
    $field_types = [
        'text'     => '텍스트',
        'email'    => '이메일',
        'password' => '비밀번호',
        'tel'      => '전화번호',
        'date'     => '날짜',
        'select'   => '셀렉트박스',
        'radio'    => '라디오버튼',
        'checkbox' => '체크박스',
        'textarea' => '텍스트영역',
        'number'   => '숫자',
    ];
    ?>
    <div class="wrap">
    <h1>📋 회원가입 입력 필드 관리</h1>
    <p style="color:#666;margin-bottom:20px">드래그로 순서를 변경하고, 각 필드의 설정을 수정하세요.</p>

    <form method="post" id="psc-fields-form">
        <?php wp_nonce_field('psc_fields_save'); ?>

        <div style="margin-bottom:16px;display:flex;gap:10px;align-items:center">
            <button type="button" class="button button-secondary" onclick="pscAddField()">
                + 새 필드 추가
            </button>
            <input type="submit" name="psc_save_fields"
                   class="button button-primary" value="💾 저장">
        </div>

        <table class="wp-list-table widefat fixed striped" id="psc-fields-table">
            <thead>
                <tr>
                    <th style="width:30px"></th>
                    <th style="width:50px">활성</th>
                    <th>라벨</th>
                    <th style="width:120px">타입</th>
                    <th>플레이스홀더</th>
                    <th style="width:60px">필수</th>
                    <th style="width:70px">중복확인</th>
                    <th style="width:60px">삭제</th>
                </tr>
            </thead>
            <tbody id="psc-fields-tbody">
            <?php foreach ( $fields as $i => $field ) :
                $is_system = ! empty( $field['system'] );
            ?>
                <tr class="psc-field-row" data-system="<?php echo $is_system ? '1' : '0'; ?>">
                    <td>
                        <span class="dashicons dashicons-menu" style="cursor:move;color:#aaa"></span>
                        <input type="hidden" name="fields[<?php echo $i; ?>][id]"
                               value="<?php echo esc_attr($field['id']); ?>">
                        <input type="hidden" name="fields[<?php echo $i; ?>][type]"
                               class="psc-field-type-val"
                               value="<?php echo esc_attr($field['type']); ?>">
                        <input type="hidden" name="fields[<?php echo $i; ?>][system]"
                               value="<?php echo $is_system ? '1' : '0'; ?>">
                    </td>
                    <td>
                        <input type="checkbox" name="fields[<?php echo $i; ?>][enabled]"
                               value="1" <?php checked( $field['enabled'] ); ?>>
                    </td>
                    <td>
                        <input type="text" name="fields[<?php echo $i; ?>][label]"
                               value="<?php echo esc_attr($field['label']); ?>"
                               class="regular-text" style="width:100%">
                    </td>
                    <td>
                        <select name="fields[<?php echo $i; ?>][type]"
                                class="psc-type-select"
                                onchange="pscTypeChange(this)">
                            <?php foreach ( $field_types as $v => $l ) : ?>
                                <option value="<?php echo $v; ?>"
                                        <?php selected( $field['type'], $v ); ?>>
                                    <?php echo esc_html($l); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td>
                        <input type="text" name="fields[<?php echo $i; ?>][placeholder]"
                               value="<?php echo esc_attr($field['placeholder'] ?? ''); ?>"
                               class="regular-text" style="width:100%"
                               placeholder="플레이스홀더">
                        <?php if ( in_array($field['type'], ['select','radio'], true) ) : ?>
                        <div class="psc-options-wrap" style="margin-top:6px">
                            <textarea name="fields[<?php echo $i; ?>][options]"
                                      rows="3" style="width:100%;font-size:.8rem"
                                      placeholder="옵션 (줄바꿈으로 구분)&#10;예: 서울&#10;부산&#10;제주"><?php
                                echo esc_textarea($field['options'] ?? '');
                            ?></textarea>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:center">
                        <input type="checkbox" name="fields[<?php echo $i; ?>][required]"
                               value="1" <?php checked( $field['required'] ); ?>
                               <?php echo $is_system && $field['required'] ? 'disabled' : ''; ?>>
                    </td>
                    <td style="text-align:center">
                        <?php if ( ! empty($field['duplicate_check']) || $field['id'] === 'user_login' || $field['id'] === 'user_email' ) : ?>
                        <input type="checkbox" name="fields[<?php echo $i; ?>][duplicate_check]"
                               value="1" <?php checked( $field['duplicate_check'] ?? false ); ?>>
                        <?php else : ?>—<?php endif; ?>
                    </td>
                    <td style="text-align:center">
                        <?php if ( ! $is_system ) : ?>
                            <button type="button" class="button-link"
                                    style="color:#c00"
                                    onclick="this.closest('tr').remove();pscReindex()">삭제</button>
                        <?php else : ?>
                            <span style="color:#aaa;font-size:.8rem">시스템</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <p style="margin-top:16px">
            <input type="submit" name="psc_save_fields"
                   class="button button-primary" value="💾 저장">
        </p>
    </form>
    </div>

    <script>
    jQuery(function($) {
        /* 드래그 정렬 */
        $('#psc-fields-tbody').sortable({
            handle: '.dashicons-menu',
            update: function() { pscReindex(); }
        });
    });

    /* 새 필드 추가 */
    var newFieldCount = 1000;
    function pscAddField() {
        var idx = newFieldCount++;
        var row = '<tr class="psc-field-row" data-system="0">'
            + '<td><span class="dashicons dashicons-menu" style="cursor:move;color:#aaa"></span>'
            + '<input type="hidden" name="fields['+idx+'][id]" value="custom_'+idx+'">'
            + '<input type="hidden" name="fields['+idx+'][type]" class="psc-field-type-val" value="text">'
            + '<input type="hidden" name="fields['+idx+'][system]" value="0"></td>'
            + '<td><input type="checkbox" name="fields['+idx+'][enabled]" value="1" checked></td>'
            + '<td><input type="text" name="fields['+idx+'][label]" class="regular-text" style="width:100%" placeholder="필드 라벨"></td>'
            + '<td><select name="fields['+idx+'][type]" class="psc-type-select" onchange="pscTypeChange(this)">'
            + '<?php foreach($field_types as $v=>$l): ?><option value="<?php echo $v ?>"><?php echo $l ?></option><?php endforeach; ?>'
            + '</select></td>'
            + '<td><input type="text" name="fields['+idx+'][placeholder]" class="regular-text" style="width:100%" placeholder="플레이스홀더"></td>'
            + '<td style="text-align:center"><input type="checkbox" name="fields['+idx+'][required]" value="1"></td>'
            + '<td style="text-align:center">—</td>'
            + '<td style="text-align:center"><button type="button" class="button-link" style="color:#c00" onclick="this.closest(\'tr\').remove();pscReindex()">삭제</button></td>'
            + '</tr>';
        jQuery('#psc-fields-tbody').append(row);
    }

    /* 인덱스 재정렬 */
    function pscReindex() {
        jQuery('#psc-fields-tbody tr').each(function(i) {
            jQuery(this).find('[name]').each(function() {
                this.name = this.name.replace(/fields\[\d+\]/, 'fields[' + i + ']');
            });
        });
    }

    /* 타입 변경 시 옵션 textarea 표시 */
    function pscTypeChange(sel) {
        var row  = sel.closest('tr');
        var wrap = row.querySelector('.psc-options-wrap');
        var idx  = sel.name.match(/\d+/)[0];
        if (['select','radio'].includes(sel.value)) {
            if (!wrap) {
                var ph = row.querySelector('[name*="placeholder"]').parentNode;
                var div = document.createElement('div');
                div.className = 'psc-options-wrap';
                div.style.marginTop = '6px';
                div.innerHTML = '<textarea name="fields['+idx+'][options]" rows="3" style="width:100%;font-size:.8rem" placeholder="옵션 (줄바꿈으로 구분)"></textarea>';
                ph.appendChild(div);
            }
        } else {
            if (wrap) wrap.remove();
        }
    }
    </script>
    <?php
}

/* ============================================================
   5. 약관 관리 페이지
   ============================================================ */
function psc_register_terms_page(): void {

    if ( isset($_POST['psc_save_terms']) &&
         check_admin_referer('psc_terms_save') ) {

        /* 약관 항목 저장 */
        $terms_list = [];
        foreach ( ($_POST['terms'] ?? []) as $t ) {
            $terms_list[] = [
                'id'       => sanitize_key( $t['id']    ?? '' ),
                'title'    => sanitize_text_field( $t['title']   ?? '' ),
                'required' => ! empty( $t['required'] ),
                'enabled'  => ! empty( $t['enabled']  ),
                'content'  => wp_kses_post( $t['content'] ?? '' ),
            ];
        }
        update_option( 'psc_register_terms_list', json_encode($terms_list) );
        echo '<div class="notice notice-success is-dismissible"><p>✅ 저장되었습니다.</p></div>';
    }

    $terms_list = psc_register_get_terms();
    ?>
    <div class="wrap">
    <h1>📜 약관 관리</h1>
    <p style="color:#666;margin-bottom:20px">드래그로 순서를 변경하고, 약관 내용을 편집하세요.</p>

    <form method="post">
        <?php wp_nonce_field('psc_terms_save'); ?>

        <div style="margin-bottom:16px;display:flex;gap:10px">
            <button type="button" class="button button-secondary" onclick="pscAddTerm()">
                + 약관 추가
            </button>
            <input type="submit" name="psc_save_terms"
                   class="button button-primary" value="💾 저장">
        </div>

        <div id="psc-terms-list">
        <?php foreach ( $terms_list as $i => $term ) : ?>
            <div class="psc-term-item postbox" style="margin-bottom:12px">
                <div class="postbox-header" style="cursor:move;display:flex;align-items:center;gap:10px;padding:12px 16px">
                    <span class="dashicons dashicons-menu" style="color:#aaa"></span>
                    <input type="text" name="terms[<?php echo $i; ?>][title]"
                           value="<?php echo esc_attr($term['title']); ?>"
                           style="flex:1;font-size:1rem;font-weight:700;border:none;outline:none;background:transparent"
                           placeholder="약관 제목">
                    <label style="display:flex;align-items:center;gap:4px;font-size:.85rem">
                        <input type="checkbox" name="terms[<?php echo $i; ?>][required]"
                               value="1" <?php checked($term['required']); ?>> 필수
                    </label>
                    <label style="display:flex;align-items:center;gap:4px;font-size:.85rem">
                        <input type="checkbox" name="terms[<?php echo $i; ?>][enabled]"
                               value="1" <?php checked($term['enabled']); ?>> 활성
                    </label>
                    <?php if ( empty($term['system']) ) : ?>
                    <button type="button" class="button-link" style="color:#c00"
                            onclick="this.closest('.psc-term-item').remove()">삭제</button>
                    <?php endif; ?>
                    <input type="hidden" name="terms[<?php echo $i; ?>][id]"
                           value="<?php echo esc_attr($term['id']); ?>">
                </div>
                <div class="inside" style="padding:0 16px 16px">
                    <?php
                    wp_editor(
                        $term['content'],
                        'term_content_' . $i,
                        [
                            'textarea_name' => "terms[{$i}][content]",
                            'media_buttons' => false,
                            'teeny'         => true,
                            'textarea_rows' => 8,
                        ]
                    );
                    ?>
                </div>
            </div>
        <?php endforeach; ?>
        </div>

        <input type="submit" name="psc_save_terms"
               class="button button-primary" value="💾 저장">
    </form>

    <script>
    jQuery(function($) {
        $('#psc-terms-list').sortable({ handle: '.dashicons-menu' });
    });

    var termCount = <?php echo count($terms_list); ?>;
    function pscAddTerm() {
        var i   = termCount++;
        var div = document.createElement('div');
        div.className = 'psc-term-item postbox';
        div.style.marginBottom = '12px';
        div.innerHTML =
            '<div class="postbox-header" style="cursor:move;display:flex;align-items:center;gap:10px;padding:12px 16px">'
            + '<span class="dashicons dashicons-menu" style="color:#aaa"></span>'
            + '<input type="text" name="terms['+i+'][title]" style="flex:1;font-size:1rem;font-weight:700;border:none;outline:none;background:transparent" placeholder="약관 제목">'
            + '<label style="display:flex;align-items:center;gap:4px;font-size:.85rem"><input type="checkbox" name="terms['+i+'][required]" value="1"> 필수</label>'
            + '<label style="display:flex;align-items:center;gap:4px;font-size:.85rem"><input type="checkbox" name="terms['+i+'][enabled]" value="1" checked> 활성</label>'
            + '<button type="button" class="button-link" style="color:#c00" onclick="this.closest(\'.psc-term-item\').remove()">삭제</button>'
            + '<input type="hidden" name="terms['+i+'][id]" value="custom_term_'+i+'">'
            + '</div>'
            + '<div class="inside" style="padding:0 16px 16px">'
            + '<textarea name="terms['+i+'][content]" rows="8" style="width:100%" placeholder="약관 내용을 입력하세요"></textarea>'
            + '</div>';
        document.getElementById('psc-terms-list').appendChild(div);
        jQuery('#psc-terms-list').sortable('refresh');
    }
    </script>
    </div>
    <?php
}

/* ============================================================
   6. 디자인 설정 페이지
   ============================================================ */
function psc_register_design_page(): void {

    if ( isset($_POST['psc_save_design']) &&
         check_admin_referer('psc_design_save') ) {
        update_option( 'psc_reg_design', [
            'primary_color'  => sanitize_hex_color( $_POST['primary_color']  ?? '#111111' ),
            'accent_color'   => sanitize_hex_color( $_POST['accent_color']   ?? '#667eea' ),
            'bg_color'       => sanitize_hex_color( $_POST['bg_color']       ?? '#f5f7fa' ),
            'card_radius'    => (int) ( $_POST['card_radius'] ?? 16 ),
            'btn_radius'     => (int) ( $_POST['btn_radius']  ?? 10 ),
            'font_size_base' => (int) ( $_POST['font_size_base'] ?? 15 ),
            'logo_url'       => esc_url_raw( $_POST['logo_url'] ?? '' ),
            'logo_text'      => sanitize_text_field( $_POST['logo_text'] ?? '' ),
        ]);
        echo '<div class="notice notice-success is-dismissible"><p>✅ 저장되었습니다.</p></div>';
    }

    $d = psc_register_get_design();
    ?>
    <div class="wrap">
    <h1>🎨 디자인 설정</h1>

    <form method="post">
        <?php wp_nonce_field('psc_design_save'); ?>
        <table class="form-table">
            <tr>
                <th>메인 컬러</th>
                <td>
                    <input type="text" name="primary_color"
                           value="<?php echo esc_attr($d['primary_color']); ?>"
                           class="psc-color-picker">
                    <p class="description">버튼, 활성 상태 등 주요 색상</p>
                </td>
            </tr>
            <tr>
                <th>포인트 컬러</th>
                <td>
                    <input type="text" name="accent_color"
                           value="<?php echo esc_attr($d['accent_color']); ?>"
                           class="psc-color-picker">
                    <p class="description">링크, 포커스, 뱃지 등 강조 색상</p>
                </td>
            </tr>
            <tr>
                <th>배경 컬러</th>
                <td>
                    <input type="text" name="bg_color"
                           value="<?php echo esc_attr($d['bg_color']); ?>"
                           class="psc-color-picker">
                </td>
            </tr>
            <tr>
                <th>카드 모서리 반경</th>
                <td>
                    <input type="range" name="card_radius" min="0" max="32"
                           value="<?php echo (int)$d['card_radius']; ?>"
                           oninput="document.getElementById('card_radius_val').textContent=this.value">
                    <span id="card_radius_val"><?php echo (int)$d['card_radius']; ?></span>px
                </td>
            </tr>
            <tr>
                <th>버튼 모서리 반경</th>
                <td>
                    <input type="range" name="btn_radius" min="0" max="32"
                           value="<?php echo (int)$d['btn_radius']; ?>"
                           oninput="document.getElementById('btn_radius_val').textContent=this.value">
                    <span id="btn_radius_val"><?php echo (int)$d['btn_radius']; ?></span>px
                </td>
            </tr>
            <tr>
                <th>기본 폰트 크기</th>
                <td>
                    <input type="range" name="font_size_base" min="12" max="20"
                           value="<?php echo (int)$d['font_size_base']; ?>"
                           oninput="document.getElementById('font_size_val').textContent=this.value">
                    <span id="font_size_val"><?php echo (int)$d['font_size_base']; ?></span>px
                </td>
            </tr>
            <tr>
                <th>로고 이미지 URL</th>
                <td>
                    <input type="text" name="logo_url"
                           value="<?php echo esc_attr($d['logo_url']); ?>"
                           class="large-text">
                    <button type="button" class="button" onclick="pscMediaUpload()">
                        미디어에서 선택
                    </button>
                    <?php if ( $d['logo_url'] ) : ?>
                        <br><img src="<?php echo esc_url($d['logo_url']); ?>"
                                 style="max-height:60px;margin-top:8px;border-radius:6px">
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th>로고 텍스트 <small>(이미지 없을 때)</small></th>
                <td>
                    <input type="text" name="logo_text"
                           value="<?php echo esc_attr($d['logo_text']); ?>"
                           class="regular-text">
                </td>
            </tr>
        </table>

        <p class="submit">
            <input type="submit" name="psc_save_design"
                   class="button button-primary" value="💾 저장">
        </p>
    </form>

    <script>
    jQuery(function($) {
        $('.psc-color-picker').wpColorPicker();
    });

    /* 미디어 업로더 */
    function pscMediaUpload() {
        var frame = wp.media({ title:'로고 이미지 선택', multiple:false });
        frame.on('select', function() {
            var att = frame.state().get('selection').first().toJSON();
            jQuery('[name="logo_url"]').val(att.url);
        });
        frame.open();
    }
    </script>
    </div>
    <?php
}

/* ============================================================
   7. 공통 헬퍼
   ============================================================ */
function psc_register_get_terms(): array {
    $saved = get_option( 'psc_register_terms_list', '' );
    if ( ! $saved ) {
        return [
            [
                'id'       => 'terms_service',
                'title'    => '이용약관',
                'required' => true,
                'enabled'  => true,
                'system'   => true,
                'content'  => get_option('psc_terms_service', '이용약관 내용을 입력해 주세요.'),
            ],
            [
                'id'       => 'terms_privacy',
                'title'    => '개인정보처리방침',
                'required' => true,
                'enabled'  => true,
                'system'   => true,
                'content'  => get_option('psc_terms_privacy', '개인정보처리방침 내용을 입력해 주세요.'),
            ],
            [
                'id'       => 'terms_marketing',
                'title'    => '마케팅 정보 수신 동의',
                'required' => false,
                'enabled'  => true,
                'system'   => false,
                'content'  => get_option('psc_terms_marketing', '마케팅 수신 동의 내용을 입력해 주세요.'),
            ],
            [
                'id'       => 'terms_sms',
                'title'    => 'SMS 수신 동의',
                'required' => false,
                'enabled'  => true,
                'system'   => false,
                'content'  => '',
            ],
        ];
    }
    return json_decode( $saved, true ) ?: [];
}

function psc_register_get_design(): array {
    $default = [
        'primary_color'  => '#111111',
        'accent_color'   => '#667eea',
        'bg_color'       => '#f5f7fa',
        'card_radius'    => 16,
        'btn_radius'     => 10,
        'font_size_base' => 15,
        'logo_url'       => '',
        'logo_text'      => '',
    ];
    $saved = get_option( 'psc_reg_design', [] );
    return wp_parse_args( $saved, $default );
}

/* ============================================================
   8. 회원 관리 페이지
   ============================================================ */
   function psc_register_admin_page_members(): void {

    // 롤 변경 처리
    if ( isset( $_POST['psc_change_role'] ) && check_admin_referer( 'psc_change_role_nonce' ) ) {
        $target_uid  = absint( $_POST['target_uid']  ?? 0 );
        $target_role = sanitize_key( $_POST['target_role'] ?? '' );
        $allowed_roles = [ 'subscriber', 'vendor', 'editor', 'administrator' ];

        if ( $target_uid && in_array( $target_role, $allowed_roles, true ) ) {
            if ( $target_uid === get_current_user_id() ) {
                echo '<div class="notice notice-error is-dismissible"><p>⚠️ 본인의 롤은 변경할 수 없습니다.</p></div>';
            } else {
                $user = new WP_User( $target_uid );
                $user->set_role( $target_role );
                echo '<div class="notice notice-success is-dismissible"><p>✅ 롤이 변경되었습니다.</p></div>';
            }
        }
    }

    $search      = sanitize_text_field( $_GET['s']    ?? '' );
    $role_filter = sanitize_key( $_GET['role']        ?? '' );
    $paged       = max( 1, absint( $_GET['paged']     ?? 1 ) );
    $per_page    = 20;

    $args = [
        'number'  => $per_page,
        'offset'  => ( $paged - 1 ) * $per_page,
        'orderby' => 'registered',
        'order'   => 'DESC',
    ];
    if ( $search )      $args['search'] = '*' . $search . '*';
    if ( $role_filter ) $args['role']   = $role_filter;

    $user_query  = new WP_User_Query( $args );
    $users       = $user_query->get_results();
    $total_users = $user_query->get_total();
    $total_pages = (int) ceil( $total_users / $per_page );

    $all_roles = [
        'subscriber'    => '일반 회원',
        'vendor'        => '벤더 (업체)',
        'editor'        => '에디터',
        'administrator' => '관리자',
    ];

    $role_colors = [
        'subscriber'    => '#6b7280',
        'vendor'        => '#0284c7',
        'editor'        => '#7c3aed',
        'administrator' => '#dc2626',
    ];
    ?>
    <div class="wrap">
        <h1>👥 회원 관리
            <span style="font-size:.85rem;font-weight:400;color:#6b7280;margin-left:8px;">
                총 <?php echo number_format( $total_users ); ?>명
            </span>
        </h1>

        <!-- 검색 & 필터 -->
        <form method="get" style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap;align-items:center;">
            <input type="hidden" name="page" value="psc-register-members">
            <input type="text" name="s" value="<?php echo esc_attr( $search ); ?>"
                placeholder="이름, 이메일, 아이디 검색"
                style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:.88rem;min-width:220px;">
            <select name="role"
                style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:.88rem;">
                <option value="">전체 롤</option>
                <?php foreach ( $all_roles as $rk => $rn ) : ?>
                    <option value="<?php echo $rk; ?>" <?php selected( $role_filter, $rk ); ?>>
                        <?php echo esc_html( $rn ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit"
                style="padding:8px 18px;background:#111;color:#fff;border:none;border-radius:6px;font-size:.88rem;font-weight:700;cursor:pointer;">
                🔍 검색
            </button>
            <?php if ( $search || $role_filter ) : ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=psc-register-members' ) ); ?>"
                    style="padding:8px 14px;background:#f3f4f6;color:#6b7280;border-radius:6px;font-size:.88rem;text-decoration:none;">
                    ✕ 초기화
                </a>
            <?php endif; ?>
        </form>

        <!-- 회원 테이블 -->
        <?php if ( empty( $users ) ) : ?>
            <div style="text-align:center;padding:60px;color:#9ca3af;background:#fff;border-radius:12px;border:1px solid #e5e7eb;">
                검색 결과가 없습니다.
            </div>
        <?php else : ?>
            <table class="wp-list-table widefat fixed striped" style="border-radius:10px;overflow:hidden;">
                <thead>
                    <tr>
                        <th style="width:280px;">회원 정보</th>
                        <th style="width:120px;">아이디</th>
                        <th style="width:100px;">가입일</th>
                        <th style="width:110px;text-align:center;">현재 롤</th>
                        <th style="text-align:center;">롤 변경</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $users as $user ) :
                        $roles        = $user->roles;
                        $current_role = $roles[0] ?? 'subscriber';
                        $role_label   = $all_roles[ $current_role ] ?? $current_role;
                        $role_color   = $role_colors[ $current_role ] ?? '#6b7280';
                        $avatar       = get_avatar_url( $user->ID, [ 'size' => 36 ] );
                        $phone        = get_user_meta( $user->ID, 'phone', true );
                        $is_me        = ( $user->ID === get_current_user_id() );
                    ?>
                        <tr>
                            <!-- 회원 정보 -->
                            <td style="padding:12px 16px;">
                                <div style="display:flex;align-items:center;gap:10px;">
                                    <img src="<?php echo esc_url( $avatar ); ?>"
                                        width="36" height="36"
                                        style="border-radius:50%;object-fit:cover;flex-shrink:0;">
                                    <div>
                                        <div style="font-weight:600;color:#111;">
                                            <?php echo esc_html( $user->display_name ); ?>
                                            <?php if ( $is_me ) : ?>
                                                <span style="font-size:.72rem;background:#fef3c7;color:#92400e;padding:2px 6px;border-radius:4px;margin-left:4px;">나</span>
                                            <?php endif; ?>
                                        </div>
                                        <div style="color:#6b7280;font-size:.8rem;">
                                            <?php echo esc_html( $user->user_email ); ?>
                                        </div>
                                        <?php if ( $phone ) : ?>
                                            <div style="color:#9ca3af;font-size:.75rem;">
                                                <?php echo esc_html( $phone ); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <!-- 아이디 -->
                            <td style="padding:12px 16px;color:#374151;font-size:.88rem;">
                                <?php echo esc_html( $user->user_login ); ?>
                            </td>
                            <!-- 가입일 -->
                            <td style="padding:12px 16px;color:#6b7280;font-size:.85rem;">
                                <?php echo esc_html( date_i18n( 'Y.m.d', strtotime( $user->user_registered ) ) ); ?>
                            </td>
                            <!-- 현재 롤 뱃지 -->
                            <td style="padding:12px 16px;text-align:center;">
                                <span style="display:inline-block;padding:4px 10px;border-radius:20px;font-size:.75rem;font-weight:700;color:#fff;background:<?php echo esc_attr( $role_color ); ?>;">
                                    <?php echo esc_html( $role_label ); ?>
                                </span>
                            </td>
                            <!-- 롤 변경 -->
                            <td style="padding:12px 16px;text-align:center;">
                                <?php if ( ! $is_me ) : ?>
                                    <form method="post" style="display:inline-flex;gap:6px;align-items:center;">
                                        <?php wp_nonce_field( 'psc_change_role_nonce' ); ?>
                                        <input type="hidden" name="target_uid" value="<?php echo $user->ID; ?>">
                                        <select name="target_role"
                                            style="padding:6px 8px;border:1px solid #d1d5db;border-radius:6px;font-size:.82rem;">
                                            <?php foreach ( $all_roles as $rk => $rn ) : ?>
                                                <option value="<?php echo $rk; ?>"
                                                    <?php selected( $current_role, $rk ); ?>>
                                                    <?php echo esc_html( $rn ); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" name="psc_change_role"
                                            style="padding:6px 14px;background:#111;color:#fff;border:none;border-radius:6px;font-size:.82rem;font-weight:700;cursor:pointer;">
                                            변경
                                        </button>
                                    </form>
                                <?php else : ?>
                                    <span style="font-size:.78rem;color:#9ca3af;">변경 불가</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- 페이지네이션 -->
            <?php if ( $total_pages > 1 ) : ?>
                <div style="display:flex;justify-content:center;gap:6px;margin-top:20px;flex-wrap:wrap;">
                    <?php if ( $paged > 1 ) :
                        $prev_url = add_query_arg( [ 'page' => 'psc-register-members', 's' => $search, 'role' => $role_filter, 'paged' => $paged - 1 ], admin_url('admin.php') );
                    ?>
                        <a href="<?php echo esc_url( $prev_url ); ?>"
                            style="display:inline-flex;align-items:center;padding:6px 12px;border-radius:6px;background:#f3f4f6;color:#374151;font-size:.85rem;text-decoration:none;">
                            ← 이전
                        </a>
                    <?php endif; ?>

                    <?php
                    $start = max( 1, $paged - 2 );
                    $end   = min( $total_pages, $paged + 2 );
                    for ( $i = $start; $i <= $end; $i++ ) :
                        $url        = add_query_arg( [ 'page' => 'psc-register-members', 's' => $search, 'role' => $role_filter, 'paged' => $i ], admin_url('admin.php') );
                        $is_current = ( $i === $paged );
                    ?>
                        <a href="<?php echo esc_url( $url ); ?>"
                            style="display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:6px;font-size:.85rem;font-weight:600;text-decoration:none;
                            <?php echo $is_current ? 'background:#111;color:#fff;' : 'background:#f3f4f6;color:#374151;'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ( $paged < $total_pages ) :
                        $next_url = add_query_arg( [ 'page' => 'psc-register-members', 's' => $search, 'role' => $role_filter, 'paged' => $paged + 1 ], admin_url('admin.php') );
                    ?>
                        <a href="<?php echo esc_url( $next_url ); ?>"
                            style="display:inline-flex;align-items:center;padding:6px 12px;border-radius:6px;background:#f3f4f6;color:#374151;font-size:.85rem;text-decoration:none;">
                            다음 →
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php
}

/* ============================================================
   9. 이메일 설정 페이지
   ============================================================ */
   function psc_register_admin_page_email(): void {

    // SMTP 저장
    if ( isset( $_POST['psc_save_smtp'] ) && check_admin_referer( 'psc_smtp_nonce' ) ) {
        update_option( 'psc_smtp_settings', [
            'host'      => sanitize_text_field( $_POST['smtp_host']      ?? '' ),
            'port'      => absint( $_POST['smtp_port']                   ?? 587 ),
            'username'  => sanitize_text_field( $_POST['smtp_username']  ?? '' ),
            'password'  => sanitize_text_field( $_POST['smtp_password']  ?? '' ),
            'secure'    => sanitize_key( $_POST['smtp_secure']           ?? 'tls' ),
            'from'      => sanitize_email( $_POST['smtp_from']           ?? '' ),
            'from_name' => sanitize_text_field( $_POST['smtp_from_name'] ?? '' ),
        ] );
        echo '<div class="notice notice-success is-dismissible"><p>✅ SMTP 설정이 저장되었습니다.</p></div>';
    }

    // 이메일 템플릿 저장
    if ( isset( $_POST['psc_save_email_tpl'] ) && check_admin_referer( 'psc_email_tpl_nonce' ) ) {
        update_option( 'psc_email_reset_subject',   sanitize_text_field( $_POST['reset_subject']   ?? '' ) );
        update_option( 'psc_email_reset_body',      wp_kses_post( $_POST['reset_body']             ?? '' ) );
        update_option( 'psc_email_welcome_subject', sanitize_text_field( $_POST['welcome_subject'] ?? '' ) );
        update_option( 'psc_email_welcome_body',    wp_kses_post( $_POST['welcome_body']           ?? '' ) );
        echo '<div class="notice notice-success is-dismissible"><p>✅ 이메일 템플릿이 저장되었습니다.</p></div>';
    }

    // 테스트 메일 발송
    $test_result = '';
    if ( isset( $_POST['psc_send_test'] ) && check_admin_referer( 'psc_test_mail_nonce' ) ) {
        $test_email = sanitize_email( $_POST['test_email'] ?? '' );
        if ( $test_email ) {
            $sent = wp_mail(
                $test_email,
                '[펫스페이스] 테스트 메일',
                '<p>SMTP 설정이 정상적으로 작동합니다! 🎉</p>',
                [ 'Content-Type: text/html; charset=UTF-8' ]
            );
            $test_result = $sent
                ? '<div class="notice notice-success is-dismissible"><p>✅ 테스트 메일이 발송되었습니다.</p></div>'
                : '<div class="notice notice-error is-dismissible"><p>❌ 메일 발송에 실패했습니다. SMTP 설정을 확인해주세요.</p></div>';
        }
    }

    $smtp            = get_option( 'psc_smtp_settings', [] );
    $reset_subject   = get_option( 'psc_email_reset_subject',   '[펫스페이스] 비밀번호 재설정 안내' );
    $reset_body      = get_option( 'psc_email_reset_body',      psc_email_default_reset_body() );
    $welcome_subject = get_option( 'psc_email_welcome_subject', '[펫스페이스] 회원가입을 환영합니다!' );
    $welcome_body    = get_option( 'psc_email_welcome_body',    psc_email_default_welcome_body() );
    ?>
    <div class="wrap">
        <h1>👤 회원가입 설정</h1>
        <?php psc_register_admin_tabs( 'email' ); ?>
        <?php echo $test_result; ?>

        <!-- SMTP 설정 -->
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:24px;margin-bottom:20px;">
            <h3 style="margin:0 0 8px;font-size:1rem;">📡 SMTP 설정</h3>
            <p style="color:#6b7280;font-size:.83rem;margin:0 0 16px;line-height:1.6;">
                Gmail: 호스트 <code>smtp.gmail.com</code> / 포트 <code>587</code> / 보안 <code>TLS</code><br>
                네이버: 호스트 <code>smtp.naver.com</code> / 포트 <code>465</code> / 보안 <code>SSL</code>
            </p>
            <form method="post">
                <?php wp_nonce_field( 'psc_smtp_nonce' ); ?>
                <table class="form-table" style="margin:0;">
                    <tr>
                        <th style="width:160px;">SMTP 호스트</th>
                        <td>
                            <input type="text" name="smtp_host"
                                value="<?php echo esc_attr( $smtp['host'] ?? '' ); ?>"
                                style="width:100%;max-width:400px;padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;"
                                placeholder="smtp.gmail.com">
                        </td>
                    </tr>
                    <tr>
                        <th>포트</th>
                        <td>
                            <input type="number" name="smtp_port"
                                value="<?php echo esc_attr( $smtp['port'] ?? 587 ); ?>"
                                style="width:100px;padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;">
                        </td>
                    </tr>
                    <tr>
                        <th>보안 방식</th>
                        <td>
                            <select name="smtp_secure"
                                style="padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;">
                                <option value="tls" <?php selected( $smtp['secure'] ?? 'tls', 'tls' ); ?>>TLS</option>
                                <option value="ssl" <?php selected( $smtp['secure'] ?? 'tls', 'ssl' ); ?>>SSL</option>
                                <option value=""    <?php selected( $smtp['secure'] ?? 'tls', '' );    ?>>없음</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th>SMTP 아이디</th>
                        <td>
                            <input type="text" name="smtp_username"
                                value="<?php echo esc_attr( $smtp['username'] ?? '' ); ?>"
                                style="width:100%;max-width:400px;padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;"
                                placeholder="your@gmail.com">
                        </td>
                    </tr>
                    <tr>
                        <th>SMTP 비밀번호</th>
                        <td>
                            <input type="password" name="smtp_password"
                                value="<?php echo esc_attr( $smtp['password'] ?? '' ); ?>"
                                style="width:100%;max-width:400px;padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;"
                                placeholder="앱 비밀번호 입력">
                            <p style="color:#6b7280;font-size:.78rem;margin:4px 0 0;">
                                Gmail은 <a href="https://myaccount.google.com/apppasswords" target="_blank">앱 비밀번호</a>를 사용하세요.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th>발신자 이메일</th>
                        <td>
                            <input type="email" name="smtp_from"
                                value="<?php echo esc_attr( $smtp['from'] ?? '' ); ?>"
                                style="width:100%;max-width:400px;padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;"
                                placeholder="noreply@pet.artmixer.co.kr">
                        </td>
                    </tr>
                    <tr>
                        <th>발신자 이름</th>
                        <td>
                            <input type="text" name="smtp_from_name"
                                value="<?php echo esc_attr( $smtp['from_name'] ?? '' ); ?>"
                                style="width:100%;max-width:400px;padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;"
                                placeholder="펫스페이스">
                        </td>
                    </tr>
                </table>
                <button type="submit" name="psc_save_smtp"
                    style="margin-top:16px;padding:10px 24px;background:#111;color:#fff;border:none;border-radius:8px;font-size:.9rem;font-weight:700;cursor:pointer;">
                    💾 SMTP 저장
                </button>
            </form>

            <!-- 테스트 메일 -->
            <hr style="margin:20px 0;border-color:#f3f4f6;">
            <h4 style="margin:0 0 10px;font-size:.9rem;">🧪 테스트 메일 발송</h4>
            <form method="post" style="display:flex;gap:8px;align-items:center;">
                <?php wp_nonce_field( 'psc_test_mail_nonce' ); ?>
                <input type="email" name="test_email"
                    placeholder="테스트 받을 이메일 주소"
                    style="padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:.88rem;min-width:260px;">
                <button type="submit" name="psc_send_test"
                    style="padding:8px 18px;background:#0284c7;color:#fff;border:none;border-radius:8px;font-size:.88rem;font-weight:700;cursor:pointer;">
                    📨 테스트 발송
                </button>
            </form>
        </div>

        <!-- 이메일 템플릿 -->
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:24px;">
            <h3 style="margin:0 0 8px;font-size:1rem;">✉️ 이메일 템플릿 편집</h3>

            <!-- 숏코드 안내 -->
            <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:12px 16px;margin-bottom:20px;font-size:.83rem;line-height:1.8;">
                <strong>사용 가능한 숏코드</strong><br>
                <code>{name}</code> 회원 이름 &nbsp;|&nbsp;
                <code>{email}</code> 회원 이메일 &nbsp;|&nbsp;
                <code>{reset_link}</code> 비밀번호 재설정 링크 &nbsp;|&nbsp;
                <code>{site_name}</code> 사이트명 &nbsp;|&nbsp;
                <code>{site_url}</code> 사이트 주소 &nbsp;|&nbsp;
                <code>{login_url}</code> 로그인 주소
            </div>

            <form method="post">
                <?php wp_nonce_field( 'psc_email_tpl_nonce' ); ?>

                <!-- 비밀번호 재설정 메일 -->
                <div style="margin-bottom:28px;">
                    <h4 style="margin:0 0 12px;font-size:.95rem;color:#374151;padding-bottom:8px;border-bottom:1px solid #f3f4f6;">
                        🔐 비밀번호 재설정 이메일
                    </h4>
                    <div style="margin-bottom:12px;">
                        <label style="display:block;font-size:.85rem;font-weight:600;color:#374151;margin-bottom:6px;">제목</label>
                        <input type="text" name="reset_subject"
                            value="<?php echo esc_attr( $reset_subject ); ?>"
                            style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:.9rem;">
                    </div>
                    <div>
                        <label style="display:block;font-size:.85rem;font-weight:600;color:#374151;margin-bottom:6px;">본문 (HTML 가능)</label>
                        <?php wp_editor( $reset_body, 'reset_body', [
                            'textarea_name' => 'reset_body',
                            'media_buttons' => false,
                            'teeny'         => false,
                            'textarea_rows' => 12,
                        ] ); ?>
                    </div>
                </div>

                <hr style="margin:0 0 24px;border-color:#f3f4f6;">

                <!-- 회원가입 환영 메일 -->
                <div style="margin-bottom:20px;">
                    <h4 style="margin:0 0 12px;font-size:.95rem;color:#374151;padding-bottom:8px;border-bottom:1px solid #f3f4f6;">
                        🎉 회원가입 환영 이메일
                    </h4>
                    <div style="margin-bottom:12px;">
                        <label style="display:block;font-size:.85rem;font-weight:600;color:#374151;margin-bottom:6px;">제목</label>
                        <input type="text" name="welcome_subject"
                            value="<?php echo esc_attr( $welcome_subject ); ?>"
                            style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:.9rem;">
                    </div>
                    <div>
                        <label style="display:block;font-size:.85rem;font-weight:600;color:#374151;margin-bottom:6px;">본문 (HTML 가능)</label>
                        <?php wp_editor( $welcome_body, 'welcome_body', [
                            'textarea_name' => 'welcome_body',
                            'media_buttons' => false,
                            'teeny'         => false,
                            'textarea_rows' => 12,
                        ] ); ?>
                    </div>
                </div>

                <button type="submit" name="psc_save_email_tpl"
                    style="padding:10px 24px;background:#111;color:#fff;border:none;border-radius:8px;font-size:.9rem;font-weight:700;cursor:pointer;">
                    💾 템플릿 저장
                </button>
            </form>
        </div>
    </div>
    <?php
}

/* ============================================================
   10. 이메일 기본 템플릿
   ============================================================ */
function psc_email_default_reset_body(): string {
    return '
<div style="max-width:520px;margin:0 auto;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',sans-serif;">
    <div style="background:#00bfa5;padding:28px 32px;border-radius:12px 12px 0 0;text-align:center;">
        <span style="color:#fff;font-size:1.3rem;font-weight:800;">{site_name}</span>
    </div>
    <div style="background:#fff;padding:36px 32px;border:1px solid #e5e7eb;border-top:none;">
        <p style="font-size:1rem;color:#111;margin:0 0 8px;">안녕하세요, <strong>{name}</strong>님!</p>
        <p style="font-size:.93rem;color:#6b7280;line-height:1.6;margin:0 0 28px;">
            비밀번호 재설정 요청이 접수되었습니다.<br>
            아래 버튼을 클릭해 새 비밀번호를 설정해주세요.<br>
            <small>링크는 <strong>1시간</strong> 동안만 유효합니다.</small>
        </p>
        <div style="text-align:center;margin:0 0 28px;">
            <a href="{reset_link}" style="display:inline-block;background:#00bfa5;color:#fff;text-decoration:none;padding:14px 36px;border-radius:10px;font-size:1rem;font-weight:700;">
                비밀번호 재설정하기
            </a>
        </div>
        <p style="font-size:.8rem;color:#9ca3af;line-height:1.6;">
            버튼이 작동하지 않으면 아래 주소를 브라우저에 붙여넣기 하세요.<br>
            <a href="{reset_link}" style="color:#00bfa5;word-break:break-all;">{reset_link}</a>
        </p>
    </div>
    <div style="background:#f8fafc;padding:20px 32px;border-radius:0 0 12px 12px;text-align:center;border:1px solid #e5e7eb;border-top:none;">
        <p style="font-size:.78rem;color:#9ca3af;margin:0;">본인이 요청하지 않은 경우 이 메일을 무시하셔도 됩니다.</p>
    </div>
</div>';
}

function psc_email_default_welcome_body(): string {
    return '
<div style="max-width:520px;margin:0 auto;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',sans-serif;">
    <div style="background:#00bfa5;padding:28px 32px;border-radius:12px 12px 0 0;text-align:center;">
        <span style="color:#fff;font-size:1.3rem;font-weight:800;">{site_name}</span>
    </div>
    <div style="background:#fff;padding:36px 32px;border:1px solid #e5e7eb;border-top:none;text-align:center;">
        <div style="font-size:2.5rem;margin-bottom:12px;">🐾</div>
        <h2 style="font-size:1.2rem;color:#111;margin:0 0 12px;">환영합니다, {name}님!</h2>
        <p style="font-size:.93rem;color:#6b7280;line-height:1.6;margin:0 0 28px;">
            펫스페이스 회원이 되신 것을 환영합니다.<br>
            반려동물과 함께하는 모든 공간을 찾아보세요!
        </p>
        <a href="{site_url}" style="display:inline-block;background:#00bfa5;color:#fff;text-decoration:none;padding:14px 36px;border-radius:10px;font-size:1rem;font-weight:700;">
            펫스페이스 둘러보기
        </a>
    </div>
    <div style="background:#f8fafc;padding:20px 32px;border-radius:0 0 12px 12px;text-align:center;border:1px solid #e5e7eb;border-top:none;">
        <p style="font-size:.78rem;color:#9ca3af;margin:0;">{site_name} · <a href="{site_url}" style="color:#9ca3af;">{site_url}</a></p>
    </div>
</div>';
}

/* ============================================================
   11. 숏코드 치환 헬퍼
   ============================================================ */
function psc_email_parse( string $template, array $vars ): string {
    foreach ( $vars as $key => $value ) {
        $template = str_replace( '{' . $key . '}', $value, $template );
    }
    return $template;
}

/* ============================================================
   12. SMTP 훅 (phpmailer_init)
   ============================================================ */
add_action( 'phpmailer_init', 'psc_smtp_setup' );
function psc_smtp_setup( PHPMailer\PHPMailer\PHPMailer $phpmailer ): void {
    $smtp = get_option( 'psc_smtp_settings', [] );
    if ( empty( $smtp['host'] ) ) return;

    $phpmailer->isSMTP();
    $phpmailer->Host       = $smtp['host']      ?? '';
    $phpmailer->SMTPAuth   = true;
    $phpmailer->Port       = (int) ( $smtp['port'] ?? 587 );
    $phpmailer->Username   = $smtp['username']  ?? '';
    $phpmailer->Password   = $smtp['password']  ?? '';
    $phpmailer->SMTPSecure = $smtp['secure']    ?? 'tls';
    $phpmailer->From       = $smtp['from']      ?? get_option('admin_email');
    $phpmailer->FromName   = $smtp['from_name'] ?? get_bloginfo('name');
}

/* ============================================================
   13. 탭 네비게이션 (이메일 탭 포함)
   ============================================================ */
function psc_register_admin_tabs( string $current = 'fields' ): void {
    $tabs = [
        'fields'  => [ 'label' => '📋 입력 필드',   'page' => 'psc-register-settings' ],
        'terms'   => [ 'label' => '📜 약관 관리',   'page' => 'psc-register-terms'    ],
        'design'  => [ 'label' => '🎨 디자인 설정', 'page' => 'psc-register-design'   ],
        'members' => [ 'label' => '👥 회원 관리',   'page' => 'psc-register-members'  ],
        'email'   => [ 'label' => '📧 이메일 설정', 'page' => 'psc-register-email'    ],
    ];
    echo '<nav style="display:flex;gap:0;margin-bottom:24px;border-bottom:2px solid #e5e7eb;">';
    foreach ( $tabs as $key => $tab ) {
        $url     = admin_url( 'admin.php?page=' . $tab['page'] );
        $active  = $key === $current
            ? 'color:#111;border-bottom:3px solid #111;margin-bottom:-2px;'
            : 'color:#6b7280;border-bottom:3px solid transparent;margin-bottom:-2px;';
        echo '<a href="' . esc_url( $url ) . '" style="padding:10px 20px;font-size:.88rem;font-weight:600;text-decoration:none;' . $active . '">' . $tab['label'] . '</a>';
    }
    echo '</nav>';
}

/* ============================================================
   14. wp-admin 사용자 편집 화면 — 커스텀 필드 연동
   ============================================================ */

/* ── 필드 표시 ── */
add_action( 'show_user_profile',  'psc_show_extra_user_fields' );
add_action( 'edit_user_profile',  'psc_show_extra_user_fields' );

function psc_show_extra_user_fields( WP_User $user ): void {
    // 시스템 필드 + 비밀번호 계열은 wp-admin 기본 UI에 이미 있으므로 제외
    $skip = [ 'user_login', 'user_pass', 'user_pass2', 'display_name', 'user_email' ];

    $fields = function_exists('psc_register_get_fields')
        ? psc_register_get_fields()
        : [];

    $custom_fields = array_filter(
        $fields,
        fn($f) => ! empty($f['enabled']) && ! in_array($f['id'], $skip, true)
    );

    if ( empty($custom_fields) ) return;
    ?>
    <hr>
    <h2>🐾 펫스페이스 추가 정보</h2>
    <table class="form-table">
        <?php foreach ( $custom_fields as $field ) :
            $fid     = esc_attr( $field['id'] );
            $label   = esc_html( $field['label'] );
            $type    = $field['type'] ?? 'text';
            $ph      = esc_attr( $field['placeholder'] ?? '' );
            $req     = ! empty( $field['required'] );
            $current = esc_attr( get_user_meta( $user->ID, $field['id'], true ) );
        ?>
        <tr>
            <th>
                <label for="psc_<?php echo $fid; ?>">
                    <?php echo $label; ?>
                    <?php if ($req) echo '<span style="color:#dc2626"> *</span>'; ?>
                </label>
            </th>
            <td>
                <?php if ( $type === 'select' ) :
                    $options = array_filter(
                        array_map('trim', explode("\n", $field['options'] ?? ''))
                    );
                ?>
                    <select id="psc_<?php echo $fid; ?>"
                            name="psc_<?php echo $fid; ?>"
                            style="min-width:200px;">
                        <option value="">선택하세요</option>
                        <?php foreach ($options as $opt) : ?>
                            <option value="<?php echo esc_attr($opt); ?>"
                                    <?php selected($current, $opt); ?>>
                                <?php echo esc_html($opt); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                <?php elseif ( $type === 'radio' ) :
                    $options = array_filter(
                        array_map('trim', explode("\n", $field['options'] ?? ''))
                    );
                ?>
                    <fieldset>
                        <?php foreach ($options as $opt) : ?>
                            <label style="margin-right:16px;">
                                <input type="radio"
                                       name="psc_<?php echo $fid; ?>"
                                       value="<?php echo esc_attr($opt); ?>"
                                       <?php checked($current, $opt); ?>>
                                <?php echo esc_html($opt); ?>
                            </label>
                        <?php endforeach; ?>
                    </fieldset>

                <?php elseif ( $type === 'checkbox' ) : ?>
                    <label>
                        <input type="checkbox"
                               id="psc_<?php echo $fid; ?>"
                               name="psc_<?php echo $fid; ?>"
                               value="1"
                               <?php checked($current, '1'); ?>>
                        <?php echo $label; ?>
                    </label>

                <?php elseif ( $type === 'textarea' ) : ?>
                    <textarea id="psc_<?php echo $fid; ?>"
                              name="psc_<?php echo $fid; ?>"
                              rows="4"
                              placeholder="<?php echo $ph; ?>"
                              class="large-text"
                              ><?php echo esc_textarea(
                                  get_user_meta($user->ID, $field['id'], true)
                              ); ?></textarea>

                <?php else : ?>
                    <input type="<?php echo esc_attr($type); ?>"
                           id="psc_<?php echo $fid; ?>"
                           name="psc_<?php echo $fid; ?>"
                           value="<?php echo $current; ?>"
                           placeholder="<?php echo $ph; ?>"
                           class="regular-text"
                           <?php echo ($type === 'date') ? 'max="' . date('Y-m-d') . '"' : ''; ?>>
                <?php endif; ?>

                <?php if ($req) : ?>
                    <p class="description" style="color:#dc2626;font-size:.78rem;">
                        필수 입력 항목입니다.
                    </p>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php
    // nonce
    wp_nonce_field( 'psc_extra_user_fields_save', 'psc_extra_fields_nonce' );
}

/* ── 저장 처리 ── */
add_action( 'personal_options_update',  'psc_save_extra_user_fields' );
add_action( 'edit_user_profile_update', 'psc_save_extra_user_fields' );

function psc_save_extra_user_fields( int $user_id ): void {
    // 권한 체크
    if ( ! current_user_can( 'edit_user', $user_id ) ) return;

    // nonce 체크
    if ( ! isset( $_POST['psc_extra_fields_nonce'] ) ||
         ! wp_verify_nonce( $_POST['psc_extra_fields_nonce'], 'psc_extra_user_fields_save' )
    ) return;

    $skip = [ 'user_login', 'user_pass', 'user_pass2', 'display_name', 'user_email' ];

    $fields = function_exists('psc_register_get_fields')
        ? psc_register_get_fields()
        : [];

    foreach ( $fields as $field ) {
        if ( empty($field['enabled']) ) continue;
        if ( in_array($field['id'], $skip, true) ) continue;

        $meta_key = sanitize_key( $field['id'] );
        $raw_val  = $_POST[ 'psc_' . $meta_key ] ?? '';

        // 타입별 sanitize
        $val = match( $field['type'] ?? 'text' ) {
            'email'    => sanitize_email( $raw_val ),
            'number'   => (string) intval( $raw_val ),
            'textarea' => sanitize_textarea_field( $raw_val ),
            'checkbox' => ! empty($raw_val) ? '1' : '0',
            'url'      => esc_url_raw( $raw_val ),
            default    => sanitize_text_field( $raw_val ),
        };

        update_user_meta( $user_id, $meta_key, $val );
    }
}

