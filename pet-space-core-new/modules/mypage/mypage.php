<?php
/**
 * 마이페이지
 * File: modules/mypage/mypage.php
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/* ============================================================
   1. 리라이트 규칙
   ============================================================ */
add_action( 'init', 'psc_mypage_rewrite_rules' );
function psc_mypage_rewrite_rules(): void {
    add_rewrite_rule( '^mypage/?$',            'index.php?psc_mypage=profile',    'top' );
    add_rewrite_rule( '^mypage/pets/?$',       'index.php?psc_mypage=pets',       'top' );
    add_rewrite_rule( '^mypage/vax-wallet/?$', 'index.php?psc_mypage=vax-wallet', 'top' );
    add_rewrite_rule( '^mypage/coupons/?$',    'index.php?psc_mypage=coupons',    'top' );
    add_rewrite_tag( '%psc_mypage%', '([^&]+)' );
}

/* ============================================================
   2. 라우터
   ============================================================ */
add_action( 'template_redirect', 'psc_mypage_router' );
function psc_mypage_router(): void {
    $page = get_query_var( 'psc_mypage' );
    if ( ! $page ) return;

    if ( ! is_user_logged_in() ) {
        wp_redirect( home_url( '/login/?redirect_to=' . urlencode( home_url('/mypage/') ) ) );
        exit;
    }

    ob_start();
    switch ( $page ) {
        case 'profile':    psc_mypage_profile_page();    break;
        case 'pets':       psc_mypage_pets_page();       break;
        case 'vax-wallet': psc_mypage_vax_wallet_page(); break;
        case 'coupons':    psc_mypage_coupons_page();    break;
        default:
            wp_redirect( home_url('/mypage/') );
            exit;
    }
    echo ob_get_clean();
    exit;
}

/* ============================================================
   3. 공통 헤더
   ============================================================ */
function psc_mypage_header( string $active_tab = 'profile' ): void {
    $user     = wp_get_current_user();
    $avatar   = get_avatar_url( $user->ID, ['size' => 80] );
    $logo_url = home_url('/wp-content/uploads/2026/02/ci.svg');

    $role_labels = [
        'administrator' => '관리자',
        'editor'        => '에디터',
        'vendor'        => '입점사',
        'subscriber'    => '일반 회원',
    ];
    $role       = $user->roles[0] ?? 'subscriber';
    $role_label = $role_labels[$role] ?? $role;
    ?>
    <!DOCTYPE html>
    <html lang="ko">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>마이페이지 — <?php echo esc_html(get_bloginfo('name')); ?></title>
        <link rel="stylesheet"
              href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard/dist/web/static/pretendard.css">
        <?php wp_head(); ?>
    </head>
    <body class="psc-mp-body">
    <div class="psc-mp-wrap">

        <!-- 탑바 -->
        <div class="psc-mp-topbar">
            <a href="<?php echo esc_url(home_url('/')); ?>" class="psc-mp-back">‹</a>
            <a href="<?php echo esc_url(home_url('/')); ?>" class="psc-mp-topbar__logo-link">
                <img src="<?php echo esc_url($logo_url); ?>"
                    alt="<?php echo esc_attr(get_bloginfo('name')); ?>"
                    class="psc-mp-topbar__logo"
                    onerror="this.style.display='none'">
            </a>
            <div style="width:36px;"></div>
        </div>

        <!-- 프로필 요약 카드 -->
        <div class="psc-mp-profile-card">
            <label for="psc-avatar-input" class="psc-mp-avatar-wrap" title="프로필 사진 변경">
                <img src="<?php echo esc_url($avatar); ?>"
                     alt="프로필"
                     class="psc-mp-avatar"
                     id="psc-avatar-preview">
                <div class="psc-mp-avatar-edit">✏️</div>
            </label>
            <input type="file" id="psc-avatar-input" accept="image/*"
                   style="display:none" onchange="pscPreviewAvatar(this)">

            <div class="psc-mp-profile-info">
                <div class="psc-mp-profile-name">
                    <?php echo esc_html($user->display_name); ?>
                </div>
                <div class="psc-mp-profile-email">
                    <?php echo esc_html($user->user_email); ?>
                </div>
                <span class="psc-mp-role-badge">
                    <?php echo esc_html($role_label); ?>
                </span>
            </div>
        </div>

        <!-- 탭 네비게이션 -->
        <div class="psc-mp-tabs">
            <a href="<?php echo esc_url(home_url('/mypage/')); ?>"
               class="psc-mp-tab <?php echo $active_tab === 'profile'    ? 'active' : ''; ?>">
               👤 프로필
            </a>
            <a href="<?php echo esc_url(home_url('/mypage/pets/')); ?>"
               class="psc-mp-tab <?php echo $active_tab === 'pets'       ? 'active' : ''; ?>">
               🐾 반려견
            </a>
            <a href="<?php echo esc_url(home_url('/mypage/vax-wallet/')); ?>"
               class="psc-mp-tab <?php echo $active_tab === 'vax-wallet' ? 'active' : ''; ?>">
               💉 접종
            </a>
            <a href="<?php echo esc_url(home_url('/mypage/coupons/')); ?>"
               class="psc-mp-tab <?php echo $active_tab === 'coupons'    ? 'active' : ''; ?>">
               🎟️ 쿠폰
            </a>
        </div>

    <?php
}

/* ============================================================
   공통 푸터
   ============================================================ */
function psc_mypage_footer(): void {
    ?>
    </div><!-- .psc-mp-wrap -->
    <?php wp_footer(); ?>
    </body>
    </html>
    <?php
}

/* ============================================================
   4. 프로필 편집 페이지
   ============================================================ */
function psc_mypage_profile_page(): void {
    $user       = wp_get_current_user();
    $user_id    = $user->ID;
    $errors     = [];
    $success    = '';
    $all_fields = function_exists('psc_register_get_fields') ? psc_register_get_fields() : [];
    $enabled_fields = array_filter( $all_fields, fn($f) => ! empty($f['enabled']) );

    if ( $_SERVER['REQUEST_METHOD'] === 'POST'
         && isset( $_POST['psc_mypage_profile_submit'] ) ) {

        if ( ! wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'psc_mypage_profile_' . $user_id ) ) {
            $errors[] = '보안 오류가 발생했습니다.';
        } else {
            $display_name = sanitize_text_field( $_POST['display_name'] ?? '' );
            $new_email    = sanitize_email( $_POST['user_email'] ?? '' );
            $new_pass     = $_POST['new_pass']  ?? '';
            $new_pass2    = $_POST['new_pass2'] ?? '';

            if ( empty($display_name) ) $errors[] = '이름을 입력해 주세요.';
            if ( ! is_email($new_email) ) {
                $errors[] = '이메일 형식이 올바르지 않습니다.';
            } elseif ( $new_email !== $user->user_email && email_exists($new_email) ) {
                $errors[] = '이미 사용 중인 이메일입니다.';
            }
            if ( $new_pass !== '' ) {
                if ( strlen($new_pass) < 8 )   $errors[] = '비밀번호는 8자 이상이어야 합니다.';
                elseif ( $new_pass !== $new_pass2 ) $errors[] = '비밀번호가 일치하지 않습니다.';
            }

            foreach ( $enabled_fields as $field ) {
                if ( ! empty($field['system']) ) continue;
                if ( $field['id'] === 'user_pass2' ) continue;
                $val = sanitize_text_field( $_POST[ $field['id'] ] ?? '' );
                if ( ! empty($field['required']) && $val === '' )
                    $errors[] = esc_html($field['label']) . ' 을(를) 입력해 주세요.';
            }

            if ( empty($errors) ) {
                $update_args = [
                    'ID'           => $user_id,
                    'display_name' => $display_name,
                    'user_email'   => $new_email,
                ];
                if ( $new_pass !== '' ) $update_args['user_pass'] = $new_pass;
                $result = wp_update_user( $update_args );

                if ( is_wp_error($result) ) {
                    $errors[] = $result->get_error_message();
                } else {
                    foreach ( $enabled_fields as $field ) {
                        if ( ! empty($field['system']) ) continue;
                        if ( $field['id'] === 'user_pass2' ) continue;
                        $meta_key = sanitize_key( $field['id'] );
                        $raw_val  = $_POST[ $meta_key ] ?? '';
                        $val = match( $field['type'] ?? 'text' ) {
                            'email'    => sanitize_email($raw_val),
                            'number'   => (string) intval($raw_val),
                            'textarea' => sanitize_textarea_field($raw_val),
                            'checkbox' => ! empty($raw_val) ? '1' : '0',
                            default    => sanitize_text_field($raw_val),
                        };
                        update_user_meta( $user_id, $meta_key, $val );
                    }
                    if ( $new_pass !== '' ) wp_set_auth_cookie( $user_id, false );
                    $success = '✅ 회원정보가 저장되었습니다.';
                    $user    = get_userdata( $user_id );
                }
            }
        }
    }

    psc_mypage_header('profile');
    ?>
    <div class="psc-mp-content">

        <?php if ( ! empty($errors) ) : ?>
            <div class="psc-mp-notice psc-mp-notice--error">
                <?php echo implode('<br>', array_map('esc_html', $errors)); ?>
            </div>
        <?php endif; ?>
        <?php if ( $success ) : ?>
            <div class="psc-mp-notice psc-mp-notice--success">
                <?php echo esc_html($success); ?>
            </div>
        <?php endif; ?>

        <form method="post" class="psc-mp-form">
            <?php wp_nonce_field( 'psc_mypage_profile_' . $user_id ); ?>

            <!-- 기본 정보 섹션 -->
            <div class="psc-mp-section-label">기본 정보</div>
            <div class="psc-mp-card">

            <?php foreach ( $enabled_fields as $field ) :
                $fid     = esc_attr( $field['id'] );
                $label   = esc_html( $field['label'] );
                $type    = $field['type'] ?? 'text';
                $ph      = esc_attr( $field['placeholder'] ?? '' );
                $req     = ! empty( $field['required'] );
                $current = match($fid) {
                    'display_name' => esc_attr( $user->display_name ),
                    'user_email'   => esc_attr( $user->user_email ),
                    'user_login'   => esc_attr( $user->user_login ),
                    default        => esc_attr( get_user_meta($user_id, $fid, true) ),
                };
            ?>

                <?php if ( $fid === 'user_login' ) : ?>
                <div class="psc-mp-field">
                    <div class="psc-mp-field__label"><?php echo $label; ?></div>
                    <div class="psc-mp-field__value"><?php echo $current; ?></div>
                    <div class="psc-mp-field__desc">아이디는 변경할 수 없습니다.</div>
                </div>

                <?php elseif ( $fid === 'user_pass' ) : ?>
                </div><!-- .psc-mp-card (기본정보 끝) -->

                <!-- 비밀번호 변경 섹션 -->
                <div class="psc-mp-section-label">비밀번호 변경</div>
                <div class="psc-mp-card">
                    <div class="psc-mp-field">
                        <label class="psc-mp-field__label" for="new_pass">새 비밀번호</label>
                        <div class="psc-mp-input-wrap">
                            <input type="password"
                                   id="new_pass" name="new_pass"
                                   class="psc-mp-input"
                                   placeholder="변경할 경우에만 입력 (8자 이상)"
                                   autocomplete="new-password">
                            <button type="button" class="psc-mp-pwd-toggle"
                                    onclick="pscMpTogglePwd('new_pass',this)">👁️</button>
                        </div>
                        <div class="psc-mp-pass-strength" id="mp-pwd-bar-wrap"></div>
                    </div>

                <?php elseif ( $fid === 'user_pass2' ) : ?>
                    <div class="psc-mp-field">
                        <label class="psc-mp-field__label" for="new_pass2">새 비밀번호 확인</label>
                        <div class="psc-mp-input-wrap">
                            <input type="password"
                                   id="new_pass2" name="new_pass2"
                                   class="psc-mp-input"
                                   placeholder="새 비밀번호를 다시 입력해 주세요"
                                   autocomplete="new-password">
                            <button type="button" class="psc-mp-pwd-toggle"
                                    onclick="pscMpTogglePwd('new_pass2',this)">👁️</button>
                        </div>
                        <p class="psc-mp-hint" id="mp-pass2-msg"></p>
                    </div>
                </div><!-- .psc-mp-card (비밀번호 끝) -->

                <!-- 추가 정보 섹션 시작 -->
                <div class="psc-mp-section-label">추가 정보</div>
                <div class="psc-mp-card">

                <?php elseif ( $type === 'select' ) :
                    $options = array_filter(array_map('trim', explode("\n", $field['options'] ?? '')));
                ?>
                    <div class="psc-mp-field">
                        <label class="psc-mp-field__label" for="<?php echo $fid; ?>">
                            <?php echo $label; ?>
                            <?php if ($req) : ?><span class="psc-mp-req">필수</span><?php endif; ?>
                        </label>
                        <select id="<?php echo $fid; ?>" name="<?php echo $fid; ?>"
                                class="psc-mp-input">
                            <option value="">선택하세요</option>
                            <?php foreach ($options as $opt) : ?>
                                <option value="<?php echo esc_attr($opt); ?>"
                                        <?php selected($current, $opt); ?>>
                                    <?php echo esc_html($opt); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                <?php elseif ( $type === 'radio' ) :
                    $options = array_filter(array_map('trim', explode("\n", $field['options'] ?? '')));
                ?>
                    <div class="psc-mp-field">
                        <div class="psc-mp-field__label">
                            <?php echo $label; ?>
                            <?php if ($req) : ?><span class="psc-mp-req">필수</span><?php endif; ?>
                        </div>
                        <div class="psc-mp-radio-group">
                            <?php foreach ($options as $opt) : ?>
                                <label class="psc-mp-radio">
                                    <input type="radio"
                                           name="<?php echo $fid; ?>"
                                           value="<?php echo esc_attr($opt); ?>"
                                           <?php checked($current, $opt); ?> hidden>
                                    <span><?php echo esc_html($opt); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                <?php elseif ( $type === 'checkbox' ) : ?>
                    <div class="psc-mp-field">
                        <label class="psc-mp-checkbox">
                            <input type="checkbox"
                                   id="<?php echo $fid; ?>"
                                   name="<?php echo $fid; ?>"
                                   value="1"
                                   <?php checked($current, '1'); ?>>
                            <span><?php echo $label; ?></span>
                        </label>
                    </div>

                <?php elseif ( $type === 'textarea' ) : ?>
                    <div class="psc-mp-field">
                        <label class="psc-mp-field__label" for="<?php echo $fid; ?>">
                            <?php echo $label; ?>
                            <?php if ($req) : ?><span class="psc-mp-req">필수</span><?php endif; ?>
                        </label>
                        <textarea id="<?php echo $fid; ?>" name="<?php echo $fid; ?>"
                                  class="psc-mp-input"
                                  rows="4"
                                  placeholder="<?php echo $ph; ?>"><?php echo $current; ?></textarea>
                    </div>

                <?php elseif ( $type === 'tel' ) : ?>
                    <div class="psc-mp-field">
                        <label class="psc-mp-field__label" for="<?php echo $fid; ?>">
                            <?php echo $label; ?>
                            <?php if ($req) : ?><span class="psc-mp-req">필수</span><?php endif; ?>
                        </label>
                        <input type="tel" id="<?php echo $fid; ?>" name="<?php echo $fid; ?>"
                               class="psc-mp-input"
                               value="<?php echo $current; ?>"
                               placeholder="<?php echo $ph; ?>"
                               oninput="pscMpFormatPhone(this)">
                    </div>

                <?php else : ?>
                    <div class="psc-mp-field">
                        <label class="psc-mp-field__label" for="<?php echo $fid; ?>">
                            <?php echo $label; ?>
                            <?php if ($req) : ?><span class="psc-mp-req">필수</span><?php endif; ?>
                        </label>
                        <input type="<?php echo esc_attr($type); ?>"
                               id="<?php echo $fid; ?>" name="<?php echo $fid; ?>"
                               class="psc-mp-input"
                               value="<?php echo $current; ?>"
                               placeholder="<?php echo $ph; ?>"
                               <?php echo ($type === 'date') ? 'max="' . date('Y-m-d') . '"' : ''; ?>>
                    </div>

                <?php endif; ?>
            <?php endforeach; ?>

            </div><!-- .psc-mp-card -->

            <button type="submit" name="psc_mypage_profile_submit"
                    class="psc-mp-btn">
                저장하기
            </button>
        </form>

        <!-- 계정 관리 -->
        <div class="psc-mp-section-label">계정 관리</div>
        <div class="psc-mp-card">
            <a href="<?php echo esc_url(home_url('/?psc_action=logout')); ?>"
               class="psc-mp-menu-item">
                <span class="psc-mp-menu-item__icon">🚪</span>
                <span class="psc-mp-menu-item__text">로그아웃</span>
                <span class="psc-mp-menu-item__arrow">›</span>
            </a>
            <div class="psc-mp-menu-item psc-mp-menu-item--danger"
                 onclick="pscWithdraw()" style="cursor:pointer;">
                <span class="psc-mp-menu-item__icon">⚠️</span>
                <span class="psc-mp-menu-item__text">회원탈퇴</span>
                <span class="psc-mp-menu-item__arrow">›</span>
            </div>
        </div>

    </div><!-- .psc-mp-content -->

    <script>
    /* 전화번호 자동 하이픈 */
    function pscMpFormatPhone(el) {
        let v = el.value.replace(/\D/g,'');
        if      (v.length <= 3)  el.value = v;
        else if (v.length <= 7)  el.value = v.slice(0,3)+'-'+v.slice(3);
        else                     el.value = v.slice(0,3)+'-'+v.slice(3,7)+'-'+v.slice(7,11);
    }

    /* 비밀번호 토글 */
    function pscMpTogglePwd(id, btn) {
        const input = document.getElementById(id);
        if (!input) return;
        input.type   = input.type === 'password' ? 'text' : 'password';
        btn.textContent = input.type === 'password' ? '👁️' : '🙈';
    }

    /* 비밀번호 강도 */
    const mpPwdInput = document.getElementById('new_pass');
    if (mpPwdInput) {
        mpPwdInput.addEventListener('input', function() {
            const v   = this.value;
            const wrap = document.getElementById('mp-pwd-bar-wrap');
            if (!wrap) return;
            if (!v) { wrap.innerHTML = ''; return; }
            let score = 0;
            if (v.length >= 8)          score++;
            if (/[A-Z]/.test(v))        score++;
            if (/[0-9]/.test(v))        score++;
            if (/[^a-zA-Z0-9]/.test(v)) score++;
            const labels = ['', '약함', '보통', '강함', '매우 강함'];
            const colors = ['', '#e53e3e', '#ed8936', '#48bb78', '#38a169'];
            wrap.innerHTML =
                '<div style="height:4px;border-radius:2px;background:#f0f0f0;margin-bottom:4px">'
              + '<div style="height:4px;border-radius:2px;width:'+(score*25)+'%;background:'+colors[score]+';transition:width .3s"></div>'
              + '</div>'
              + '<span style="font-size:.75rem;color:'+colors[score]+'">'+labels[score]+'</span>';
            pscMpCheckPass2();
        });
    }

    /* 비밀번호 확인 */
    const mpPass2 = document.getElementById('new_pass2');
    if (mpPass2) {
        mpPass2.addEventListener('input', pscMpCheckPass2);
    }
    function pscMpCheckPass2() {
        const p1  = document.getElementById('new_pass')?.value  || '';
        const p2  = document.getElementById('new_pass2')?.value || '';
        const msg = document.getElementById('mp-pass2-msg');
        if (!msg || !p2) { if(msg) msg.textContent=''; return; }
        if (p1 === p2) {
            msg.textContent = '✅ 비밀번호가 일치합니다.';
            msg.className   = 'psc-mp-hint success';
        } else {
            msg.textContent = '❌ 비밀번호가 일치하지 않습니다.';
            msg.className   = 'psc-mp-hint error';
        }
    }

    /* 아바타 업로드 */
    function pscPreviewAvatar(input) {
        if (!input.files || !input.files[0]) return;
        const file = input.files[0];
        if (file.size > 2 * 1024 * 1024) {
            alert('이미지 용량은 2MB 이하만 가능합니다.');
            input.value = ''; return;
        }
        const reader = new FileReader();
        reader.onload = e => {
            document.getElementById('psc-avatar-preview').src = e.target.result;
        };
        reader.readAsDataURL(file);

        const formData = new FormData();
        formData.append('action',      'psc_upload_avatar');
        formData.append('_ajax_nonce', '<?php echo wp_create_nonce("psc_upload_avatar"); ?>');
        formData.append('avatar',      file);

        const preview = document.getElementById('psc-avatar-preview');
        preview.style.opacity = '.5';

        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST', body: formData
        })
        .then(r => r.json())
        .then(d => {
            preview.style.opacity = '1';
            if (d.success) {
                preview.src = d.data.url + '?t=' + Date.now();
                pscMpToast('✅ 프로필 사진이 변경되었습니다.', true);
            } else {
                pscMpToast('❌ ' + (d.data || '업로드 실패'), false);
            }
        })
        .catch(() => {
            preview.style.opacity = '1';
            pscMpToast('❌ 네트워크 오류가 발생했습니다.', false);
        });
    }

    /* 토스트 */
    function pscMpToast(msg, ok) {
        let el = document.getElementById('psc-mp-toast');
        if (!el) {
            el = document.createElement('div');
            el.id = 'psc-mp-toast';
            el.style.cssText = 'position:fixed;bottom:90px;left:50%;transform:translateX(-50%);z-index:9999;padding:12px 20px;border-radius:10px;font-size:.88rem;font-weight:600;box-shadow:0 4px 16px rgba(0,0,0,.12);transition:opacity .4s;pointer-events:none;white-space:nowrap;';
            document.body.appendChild(el);
        }
        el.textContent      = msg;
        el.style.background = ok ? '#f0fdf4' : '#fef2f2';
        el.style.color      = ok ? '#16a34a' : '#dc2626';
        el.style.border     = ok ? '1px solid #bbf7d0' : '1px solid #fecaca';
        el.style.opacity    = '1';
        clearTimeout(el._t);
        el._t = setTimeout(() => { el.style.opacity = '0'; }, 3000);
    }

    /* 회원탈퇴 */
    function pscWithdraw() {
        if (!confirm('정말 탈퇴하시겠습니까?\n탈퇴 후에는 복구가 불가능합니다.')) return;
        const input = prompt('"탈퇴합니다" 를 입력해 주세요.');
        if (input === '탈퇴합니다') {
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                headers: {'Content-Type':'application/x-www-form-urlencoded'},
                body: 'action=psc_withdraw_user&_ajax_nonce=<?php echo wp_create_nonce('psc_withdraw'); ?>'
            })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    alert('회원탈퇴가 완료되었습니다.');
                    location.href = '<?php echo esc_url(home_url('/')); ?>';
                } else {
                    alert('오류: ' + d.data);
                }
            });
        } else if (input !== null) {
            alert('입력 내용이 일치하지 않습니다.');
        }
    }

    /* 라디오 시각화 */
    document.querySelectorAll('.psc-mp-radio').forEach(function(label) {
        label.addEventListener('click', function() {
            const name = this.querySelector('input').name;
            document.querySelectorAll('.psc-mp-radio input[name="'+name+'"]').forEach(function(r) {
                r.closest('.psc-mp-radio').querySelector('span').classList.remove('active');
            });
            this.querySelector('span').classList.add('active');
        });
        const input = label.querySelector('input');
        if (input && input.checked) label.querySelector('span').classList.add('active');
    });
    </script>
    <?php
    psc_mypage_footer();
}

/* ============================================================
   5. 반려견 페이지
   ============================================================ */
function psc_mypage_pets_page(): void {
    psc_mypage_header('pets');
    ?>
    <div class="psc-mp-content">
        <?php if ( shortcode_exists('my_pets_dashboard') ) : ?>
            <?php echo do_shortcode('[my_pets_dashboard]'); ?>
        <?php else : ?>
            <div class="psc-mp-empty">
                <div class="psc-mp-empty__icon">🐾</div>
                <div class="psc-mp-empty__title">반려견 모듈 준비 중</div>
                <div class="psc-mp-empty__desc">곧 이용하실 수 있습니다.</div>
            </div>
        <?php endif; ?>
    </div>
    <?php
    psc_mypage_footer();
}

/* ============================================================
   6. 접종 지갑 페이지
   ============================================================ */
function psc_mypage_vax_wallet_page(): void {
    psc_mypage_header('vax-wallet');
    ?>
    <div class="psc-mp-content">
        <?php if ( shortcode_exists('pet_vax_wallet') ) : ?>
            <?php echo do_shortcode('[pet_vax_wallet]'); ?>
        <?php else : ?>
            <div class="psc-mp-empty">
                <div class="psc-mp-empty__icon">💉</div>
                <div class="psc-mp-empty__title">접종 지갑 준비 중</div>
                <div class="psc-mp-empty__desc">곧 이용하실 수 있습니다.</div>
            </div>
        <?php endif; ?>
    </div>
    <?php
    psc_mypage_footer();
}

/* ============================================================
   7. 쿠폰 페이지
   ============================================================ */
function psc_mypage_coupons_page(): void {
    global $wpdb;
    $user_id    = get_current_user_id();
    $table      = $wpdb->prefix . PSC_COUPON_TABLE;
    $filter_tab = sanitize_key( $_GET['status'] ?? 'active' );

    $active_coupons = $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM {$table} WHERE user_id = %d AND is_used = 0 ORDER BY issued_at DESC",
        $user_id
    ) );
    $used_coupons = $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM {$table} WHERE user_id = %d AND is_used = 1 ORDER BY used_at DESC",
        $user_id
    ) );

    $expired_from_active = [];
    $real_active         = [];
    foreach ( $active_coupons as $row ) {
        if ( psc_coupon_is_expired( (int)$row->coupon_id ) ) {
            $expired_from_active[] = $row;
        } else {
            $real_active[] = $row;
        }
    }

    $display_list = $filter_tab === 'used'
        ? array_merge($used_coupons, $expired_from_active)
        : $real_active;

    psc_mypage_header('coupons');
    ?>
    <div class="psc-mp-content">

        <!-- 쿠폰 탭 -->
        <div class="psc-mp-coupon-tabs">
            <a href="<?php echo esc_url(add_query_arg('status','active',home_url('/mypage/coupons/'))); ?>"
               class="psc-mp-coupon-tab <?php echo $filter_tab !== 'used' ? 'active' : ''; ?>">
                보유 쿠폰
                <span class="psc-mp-coupon-tab__count"><?php echo count($real_active); ?></span>
            </a>
            <a href="<?php echo esc_url(add_query_arg('status','used',home_url('/mypage/coupons/'))); ?>"
               class="psc-mp-coupon-tab <?php echo $filter_tab === 'used' ? 'active' : ''; ?>">
                사용완료
                <span class="psc-mp-coupon-tab__count"><?php echo count($used_coupons)+count($expired_from_active); ?></span>
            </a>
        </div>

        <?php if ( empty($display_list) ) : ?>
            <div class="psc-mp-empty">
                <div class="psc-mp-empty__icon"><?php echo $filter_tab === 'used' ? '📭' : '🎟️'; ?></div>
                <div class="psc-mp-empty__title">
                    <?php echo $filter_tab === 'used' ? '사용한 쿠폰이 없습니다.' : '보유 중인 쿠폰이 없습니다.'; ?>
                </div>
                <?php if ( $filter_tab !== 'used' ) : ?>
                    <a href="<?php echo esc_url(home_url('/')); ?>"
                       class="psc-mp-btn" style="max-width:200px;margin-top:16px;">
                        매장 둘러보기
                    </a>
                <?php endif; ?>
            </div>

        <?php else : ?>
            <div class="psc-mp-coupon-list">
            <?php foreach ( $display_list as $row ) :
                $coupon_id   = (int)$row->coupon_id;
                $coupon      = get_post($coupon_id);
                if (!$coupon) continue;
                $store_id    = (int)get_post_meta($coupon_id, PSC_COUPON_STORE_META, true);
                $store_name  = $store_id ? get_the_title($store_id) : '매장 정보 없음';
                $store_url   = $store_id ? get_permalink($store_id) : '#';
                $store_thumb = $store_id ? get_the_post_thumbnail_url($store_id,[24,24]) : '';
                $expire      = get_post_meta($coupon_id, 'coupon_expire_date', true);
                $is_used     = (bool)$row->is_used;
                $is_expired  = psc_coupon_is_expired($coupon_id);
                $issued_date = date_i18n('Y.m.d', strtotime($row->issued_at));
                $used_date   = $row->used_at ? date_i18n('Y.m.d H:i', strtotime($row->used_at)) : '';
                $panel_id    = 'coupon-panel-' . esc_attr($row->id);
                $qr_url      = 'https://api.qrserver.com/v1/create-qr-code/?size=160x160&data='
                             . urlencode($row->token);

                // 할인 숫자/단위 분리
                $raw_discount = psc_coupon_format_value($coupon_id);
                if ( preg_match('/^([\d,]+)(%|원)$/', $raw_discount, $dm) ) {
                    $disc_num  = $dm[1];
                    $disc_unit = $dm[2];
                } else {
                    $disc_num  = $raw_discount;
                    $disc_unit = '';
                }
            ?>

            <div class="psc-mp-coupon-card <?php echo ($is_used||$is_expired) ? 'used' : ''; ?>">

                <!-- 왼쪽 메인 -->
                <div class="psc-mp-coupon-main">
                    <div class="psc-mp-coupon-discount">
                        <span class="psc-mp-coupon-discount__num"><?php echo esc_html($disc_num); ?></span>
                        <?php if ($disc_unit) : ?>
                            <span class="psc-mp-coupon-discount__unit"><?php echo esc_html($disc_unit); ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="psc-mp-coupon-title"><?php echo esc_html($coupon->post_title); ?></div>

                    <div class="psc-mp-coupon-period">
                        <?php if ($is_used && $used_date) : ?>
                            사용 <?php echo esc_html($used_date); ?>
                        <?php elseif ($expire) : ?>
                            <?php echo esc_html($expire); ?> 까지
                        <?php else : ?>
                            상시 사용 가능
                        <?php endif; ?>
                    </div>

                    <div class="psc-mp-coupon-store">
                        <?php if ($store_thumb) : ?>
                            <img src="<?php echo esc_url($store_thumb); ?>" alt="">
                        <?php else : ?>
                            <span>🏪</span>
                        <?php endif; ?>
                        <?php if ($store_id) : ?>
                            <a href="<?php echo esc_url($store_url); ?>"
                               class="psc-mp-coupon-store__link">
                                <?php echo esc_html($store_name); ?>
                            </a>
                        <?php else : ?>
                            <span><?php echo esc_html($store_name); ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 오른쪽 사이드 -->
                <div class="psc-mp-coupon-side">
                    <span class="psc-mp-coupon-brand">PetSpace</span>

                    <?php if (!$is_used && !$is_expired) : ?>
                        <button class="psc-mp-coupon-use-btn"
                                onclick="pscToggleCouponPanel('<?php echo $panel_id; ?>',this)"
                                title="사용하기">
                            <svg viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="2.2"
                                 stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                        </button>
                    <?php else : ?>
                        <button class="psc-mp-coupon-use-btn psc-mp-coupon-use-btn--disabled"
                                disabled
                                title="<?php echo $is_used ? '사용완료' : '기간만료'; ?>">
                            <svg viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="2.2"
                                 stroke-linecap="round" stroke-linejoin="round">
                                <line x1="18" y1="6" x2="6" y2="18"/>
                                <line x1="6" y1="6" x2="18" y2="18"/>
                            </svg>
                        </button>
                    <?php endif; ?>
                </div>

                <!-- 사용 패널 (카드 하단 전체 너비) -->
                <?php if (!$is_used && !$is_expired) : ?>
                <div class="psc-mp-coupon-panel" id="<?php echo $panel_id; ?>">
                    <div class="psc-mp-coupon-panel__qr">
                        <img src="<?php echo esc_url($qr_url); ?>" alt="QR코드" loading="lazy">
                    </div>
                    <p class="psc-mp-coupon-panel__label">매장에 QR 또는 코드를 보여주세요</p>
                    <div class="psc-mp-coupon-panel__token">
                        <span id="token-<?php echo esc_attr($row->id); ?>"
                              class="psc-mp-coupon-panel__code">
                            <?php echo esc_html($row->token); ?>
                        </span>
                        <button class="psc-mp-coupon-panel__copy"
                                onclick="pscCopyToken('token-<?php echo esc_attr($row->id); ?>',this)">
                            복사
                        </button>
                    </div>
                </div>
                <?php endif; ?>

            </div>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div><!-- .psc-mp-content -->

    <script>
    function pscToggleCouponPanel(panelId, btn) {
        const panel  = document.getElementById(panelId);
        if (!panel) return;
        const isOpen = panel.classList.contains('open');
        document.querySelectorAll('.psc-mp-coupon-panel.open').forEach(function(p) {
            p.classList.remove('open');
        });
        document.querySelectorAll('.psc-mp-coupon-use-btn').forEach(function(b) {
            b.classList.remove('active');
            b.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>';
        });
        if (!isOpen) {
            panel.classList.add('open');
            btn.classList.add('active');
            btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
            setTimeout(function() {
                panel.scrollIntoView({ behavior:'smooth', block:'nearest' });
            }, 100);
        }
    }

    function pscCopyToken(id, btn) {
        const text = document.getElementById(id).textContent.trim();
        navigator.clipboard.writeText(text).then(function() {
            const orig = btn.textContent;
            btn.textContent = '✓ 복사됨';
            btn.style.background = '#16a34a';
            setTimeout(function() {
                btn.textContent = orig;
                btn.style.background = '';
            }, 2000);
        }).catch(function() {
            const el = document.createElement('textarea');
            el.value = text;
            document.body.appendChild(el);
            el.select();
            document.execCommand('copy');
            document.body.removeChild(el);
            btn.textContent = '✓ 복사됨';
            setTimeout(function() { btn.textContent = '복사'; }, 2000);
        });
    }
    </script>
    <?php
    psc_mypage_footer();
}

/* ============================================================
   8. AJAX — 회원탈퇴
   ============================================================ */
add_action( 'wp_ajax_psc_withdraw_user', 'psc_ajax_withdraw_user' );
function psc_ajax_withdraw_user(): void {
    check_ajax_referer('psc_withdraw');
    $user_id = get_current_user_id();
    if (!$user_id)                       wp_send_json_error('로그인 정보를 확인할 수 없습니다.');
    if (user_can($user_id,'manage_options')) wp_send_json_error('관리자 계정은 탈퇴할 수 없습니다.');
    require_once ABSPATH . 'wp-admin/includes/user.php';
    wp_logout();
    wp_delete_user($user_id) ? wp_send_json_success() : wp_send_json_error('처리 중 오류가 발생했습니다.');
}

/* ============================================================
   9. AJAX — 아바타 업로드
   ============================================================ */
add_action( 'wp_ajax_psc_upload_avatar', 'psc_ajax_upload_avatar' );
function psc_ajax_upload_avatar(): void {
    check_ajax_referer('psc_upload_avatar');
    $user_id = get_current_user_id();
    if (!$user_id)                wp_send_json_error('로그인이 필요합니다.');
    if (empty($_FILES['avatar'])) wp_send_json_error('파일이 없습니다.');

    $file          = $_FILES['avatar'];
    $allowed_types = ['image/jpeg','image/png','image/gif','image/webp'];
    $finfo         = finfo_open(FILEINFO_MIME_TYPE);
    $mime          = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, $allowed_types, true)) wp_send_json_error('JPG, PNG, GIF, WEBP만 가능합니다.');
    if ($file['size'] > 2 * 1024 * 1024)       wp_send_json_error('파일 크기는 2MB 이하여야 합니다.');

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg';
    $_FILES['avatar']['name'] = 'avatar-' . $user_id . '.' . $ext;

    $attachment_id = media_handle_upload('avatar', 0);
    if (is_wp_error($attachment_id)) wp_send_json_error($attachment_id->get_error_message());

    $old_id = get_user_meta($user_id, 'psc_avatar_attachment_id', true);
    if ($old_id && (int)$old_id !== $attachment_id)
        wp_delete_attachment((int)$old_id, true);

    $avatar_url = wp_get_attachment_url($attachment_id);
    update_user_meta($user_id, 'psc_avatar',               $avatar_url);
    update_user_meta($user_id, 'psc_avatar_attachment_id', $attachment_id);
    wp_send_json_success(['url' => $avatar_url]);
}

/* ============================================================
   10. 아바타 필터
   ============================================================ */
add_filter('get_avatar_url', 'psc_custom_avatar_url', 10, 3);
function psc_custom_avatar_url( string $url, mixed $id_or_email, array $args ): string {
    $user_id = 0;
    if      (is_numeric($id_or_email))           $user_id = (int)$id_or_email;
    elseif  (is_string($id_or_email))            { $u = get_user_by('email',$id_or_email); if($u) $user_id=$u->ID; }
    elseif  ($id_or_email instanceof WP_User)    $user_id = $id_or_email->ID;
    elseif  ($id_or_email instanceof WP_Post)    $user_id = (int)$id_or_email->post_author;
    elseif  ($id_or_email instanceof WP_Comment) $user_id = (int)$id_or_email->user_id;
    if (!$user_id) return $url;
    $custom = get_user_meta($user_id, 'psc_avatar', true);
    return $custom ?: $url;
}

