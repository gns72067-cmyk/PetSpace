<?php
/**
 * 회원가입 모듈
 * File: modules/auth/register.php
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/* ============================================================
   1. Rewrite Rules
   ============================================================ */
add_action( 'init', 'psc_register_rewrites' );
function psc_register_rewrites(): void {
    add_rewrite_rule( '^register/?$',          'index.php?psc_auth=register',      'top' );
    add_rewrite_rule( '^register/complete/?$', 'index.php?psc_auth=register_done', 'top' );
}

add_filter( 'query_vars', function( $vars ) {
    $vars[] = 'psc_auth';
    return $vars;
});

/* ============================================================
   2. 라우터
   ============================================================ */
add_action( 'template_redirect', 'psc_auth_router' );
function psc_auth_router(): void {
    $page = get_query_var( 'psc_auth' );
    if ( ! $page ) return;

    if ( is_user_logged_in() ) {
        wp_redirect( home_url( '/' ) );
        exit;
    }

    switch ( $page ) {
        case 'register':      psc_register_page();      exit;
        case 'register_done': psc_register_done_page(); exit;
    }
}

/* ============================================================
   3. 세션 시작 헬퍼
   ============================================================ */
function psc_session_start(): void {
    if ( ! session_id() && ! headers_sent() ) {
        session_start();
    }
}

/* ============================================================
   4. 회원가입 페이지 컨트롤러
   ============================================================ */
function psc_register_page(): void {
    psc_session_start();
    $step  = (int) ( $_GET['step'] ?? 1 );
    $error = '';

    if ( $step === 1 && $_SERVER['REQUEST_METHOD'] === 'POST' ) {
        if ( ! wp_verify_nonce( $_POST['psc_terms_nonce'] ?? '', 'psc_terms_agree' ) ) {
            $error = '보안 오류가 발생했습니다.';
        } else {
            $terms_list   = psc_register_get_terms();
            $required_ids = array_column(
                array_filter( $terms_list, fn($t) => $t['required'] && $t['enabled'] ),
                'id'
            );
            $missing = false;
            foreach ( $required_ids as $tid ) {
                if ( empty( $_POST['agree'][$tid] ) ) { $missing = true; break; }
            }
            if ( $missing ) {
                $error = '필수 약관에 모두 동의해 주세요.';
            } else {
                $_SESSION['psc_agreed'] = [];
                foreach ( $terms_list as $t ) {
                    if ( ! $t['enabled'] ) continue;
                    $_SESSION['psc_agreed'][ $t['id'] ] = ! empty( $_POST['agree'][$t['id']] ) ? 1 : 0;
                }
                wp_redirect( home_url( '/register/?step=2' ) );
                exit;
            }
        }
    }

    if ( $step === 2 && $_SERVER['REQUEST_METHOD'] === 'POST' ) {
        if ( empty( $_SESSION['psc_agreed'] ) ) {
            wp_redirect( home_url( '/register/?step=1' ) );
            exit;
        }
        $result = psc_register_save_user();
        if ( is_wp_error( $result ) ) {
            $error = $result->get_error_message();
        } else {
            wp_set_current_user( $result );
            wp_set_auth_cookie( $result );
            unset( $_SESSION['psc_agreed'] );
            wp_redirect( home_url( '/register/complete/' ) );
            exit;
        }
    }

    psc_register_render( $step, $error );
}

/* ============================================================
   5. 회원 저장
   ============================================================ */
function psc_register_save_user(): int|WP_Error {
    if ( ! wp_verify_nonce( $_POST['psc_reg_nonce'] ?? '', 'psc_register_action' ) ) {
        return new WP_Error( 'nonce', '보안 오류가 발생했습니다.' );
    }

    $fields  = psc_register_get_fields();
    $enabled = array_filter( $fields, fn($f) => $f['enabled'] );

    $data       = [];
    $user_login = '';
    $user_email = '';
    $user_pass  = '';

    foreach ( $enabled as $field ) {
        $id  = $field['id'];
        $val = sanitize_text_field( $_POST[$id] ?? '' );

        switch ( $field['type'] ) {
            case 'email':    $val = sanitize_email( $_POST[$id] ?? '' );          break;
            case 'password': $val = $_POST[$id] ?? '';                            break;
            case 'textarea': $val = sanitize_textarea_field( $_POST[$id] ?? '' ); break;
        }

        if ( $field['required'] && $id !== 'user_pass2' && trim($val) === '' ) {
            return new WP_Error( 'required', esc_html($field['label']) . '을(를) 입력해 주세요.' );
        }

        switch ( $id ) {
            case 'user_login':
                if ( ! validate_username($val) )
                    return new WP_Error( 'bad_login', '아이디는 영문, 숫자, _, -만 사용 가능합니다.' );
                if ( username_exists($val) )
                    return new WP_Error( 'dup_login', '이미 사용 중인 아이디입니다.' );
                $user_login = $val;
                break;
            case 'user_email':
                if ( ! is_email($val) )
                    return new WP_Error( 'bad_email', '올바른 이메일 형식이 아닙니다.' );
                if ( email_exists($val) )
                    return new WP_Error( 'dup_email', '이미 사용 중인 이메일입니다.' );
                $user_email = $val;
                break;
            case 'user_pass':
                if ( strlen($val) < 8 )
                    return new WP_Error( 'short_pass', '비밀번호는 8자 이상이어야 합니다.' );
                $user_pass = $val;
                break;
            case 'user_pass2':
                if ( $val !== $user_pass )
                    return new WP_Error( 'pass_match', '비밀번호가 일치하지 않습니다.' );
                break;
            default:
                $data[$id] = $val;
        }
    }

    $user_id = wp_insert_user([
        'user_login'   => $user_login,
        'user_email'   => $user_email,
        'user_pass'    => $user_pass,
        'display_name' => $data['display_name'] ?? $user_login,
        'first_name'   => $data['display_name'] ?? '',
        'role'         => 'subscriber',
    ]);

    if ( is_wp_error($user_id) ) return $user_id;

    foreach ( $data as $key => $val ) {
        if ( $key === 'display_name' ) continue;
        update_user_meta( $user_id, $key, $val );
    }

    psc_session_start();
    if ( ! empty( $_SESSION['psc_agreed'] ) ) {
        update_user_meta( $user_id, 'psc_agreed_terms', $_SESSION['psc_agreed'] );
    }

    return $user_id;
}

/* ============================================================
   6. AJAX — 아이디 중복 확인
   ============================================================ */
add_action( 'wp_ajax_nopriv_psc_check_login', 'psc_ajax_check_login' );
add_action( 'wp_ajax_psc_check_login',        'psc_ajax_check_login' );
function psc_ajax_check_login(): void {
    $login = sanitize_user( $_POST['login'] ?? '' );
    if ( ! $login )
        wp_send_json_error([ 'message' => '아이디를 입력해 주세요.' ]);
    if ( ! validate_username($login) )
        wp_send_json_error([ 'message' => '영문, 숫자, _, -만 사용 가능합니다.' ]);
    if ( username_exists($login) )
        wp_send_json_error([ 'message' => '이미 사용 중인 아이디입니다.' ]);
    wp_send_json_success([ 'message' => '✅ 사용 가능한 아이디입니다.' ]);
}

/* ============================================================
   7. AJAX — 이메일 중복 확인
   ============================================================ */
add_action( 'wp_ajax_nopriv_psc_check_email', 'psc_ajax_check_email' );
add_action( 'wp_ajax_psc_check_email',        'psc_ajax_check_email' );
function psc_ajax_check_email(): void {
    $email = sanitize_email( $_POST['email'] ?? '' );
    if ( ! $email || ! is_email($email) )
        wp_send_json_error([ 'message' => '올바른 이메일을 입력해 주세요.' ]);
    if ( email_exists($email) )
        wp_send_json_error([ 'message' => '이미 사용 중인 이메일입니다.' ]);
    wp_send_json_success([ 'message' => '✅ 사용 가능한 이메일입니다.' ]);
}

/* ============================================================
   8. 렌더링
   ============================================================ */
function psc_register_render( int $step, string $error ): void {
    $design         = psc_register_get_design();
    $terms_list     = psc_register_get_terms();
    $fields         = psc_register_get_fields();
    $enabled_fields = array_values( array_filter( $fields, fn($f) => $f['enabled'] ) );
    $logo_url       = $design['logo_url'] ?? '';
    ?>
    <!DOCTYPE html>
    <html <?php language_attributes(); ?>>
    <head>
        <meta charset="<?php bloginfo('charset'); ?>">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>회원가입 — <?php bloginfo('name'); ?></title>
        <link rel="stylesheet"
              href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard/dist/web/static/pretendard.css">
        <?php wp_head(); ?>
    </head>
    <body class="psc-reg-body">
    <div class="psc-reg-wrap">

        <!-- 탑바 -->
        <div class="psc-reg-topbar">
            <?php if ( $step === 2 ) : ?>
                <a href="<?php echo esc_url( home_url('/register/?step=1') ); ?>"
                   class="psc-reg-back">‹</a>
            <?php else : ?>
                <a href="<?php echo esc_url( home_url('/') ); ?>"
                   class="psc-reg-back">‹</a>
            <?php endif; ?>
            <span class="psc-reg-topbar__title">회원가입</span>
        </div>

        <!-- 프로그레스 바 -->
        <div class="psc-reg-progress">
            <div class="psc-reg-progress__track">
                <div class="psc-reg-progress__fill"
                     style="width:<?php echo $step === 1 ? '50' : '100'; ?>%"></div>
            </div>
            <div class="psc-reg-progress__labels">
                <span class="<?php echo $step === 1 ? 'active' : 'done'; ?>">약관 동의</span>
                <span class="<?php echo $step === 2 ? 'active' : ''; ?>">정보 입력</span>
            </div>
        </div>

        <!-- 히어로 -->
        <div class="psc-reg-hero">
            <?php
            $logo_src = $logo_url ?: home_url('/wp-content/uploads/2026/02/ci.svg');
            ?>
            <img src="<?php echo esc_url($logo_src); ?>"
                 alt="<?php bloginfo('name'); ?>"
                 class="psc-reg-hero__logo"
                 onerror="this.style.display='none'">
            <h1 class="psc-reg-hero__title">
                <?php echo $step === 1 ? '서비스 이용 약관' : '회원정보를 입력해주세요'; ?>
            </h1>
            <p class="psc-reg-hero__sub">
                <?php echo $step === 1
                    ? '반려동물과 함께하는 공간, ' . get_bloginfo('name')
                    : '가입 후 모든 서비스를 이용할 수 있어요'; ?>
            </p>
        </div>

        <!-- 에러 -->
        <?php if ( $error ) : ?>
            <div class="psc-reg-error">
                <span>⚠️</span>
                <span><?php echo esc_html($error); ?></span>
            </div>
        <?php endif; ?>

        <?php if ( $step === 1 ) : ?>
        <!-- ════ STEP 1 : 약관 동의 ════ -->
        <form method="post" class="psc-reg-form">
            <?php wp_nonce_field('psc_terms_agree', 'psc_terms_nonce'); ?>

            <!-- 전체 동의 -->
            <div class="psc-terms-all">
                <div class="psc-terms-all__label" id="agree_all_box">
                    <div class="psc-terms-all__checkbox">
                        <svg width="14" height="14" viewBox="0 0 16 16" fill="none">
                            <path d="M3 8l3.5 3.5L13 4.5"
                                  stroke="#fff" stroke-width="2.2"
                                  stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <div>
                        <div class="psc-terms-all__text">전체 동의</div>
                        <div class="psc-terms-all__desc">선택 항목 포함 모든 약관에 동의합니다</div>
                    </div>
                </div>
            </div>

            <!-- 약관 목록 -->
            <div class="psc-terms-list">
            <?php foreach ( $terms_list as $term ) :
                if ( ! $term['enabled'] ) continue; ?>
                <div class="psc-terms-item">
                    <div class="psc-terms-item__row">
                        <div class="psc-terms-item__left">
                            <span class="psc-terms-badge <?php echo $term['required']
                                ? 'psc-terms-badge--req' : 'psc-terms-badge--opt'; ?>">
                                <?php echo $term['required'] ? '필수' : '선택'; ?>
                            </span>
                            <span class="psc-terms-item__title">
                                <?php echo esc_html($term['title']); ?>
                            </span>
                        </div>
                        <div class="psc-terms-item__right">
                            <?php if ( $term['content'] ) : ?>
                            <button type="button" class="psc-terms-view"
                                    onclick="pscToggleTerms('term_<?php echo esc_attr($term['id']); ?>')">
                                보기
                            </button>
                            <?php endif; ?>
                            <div class="psc-terms-agree-wrap">
                                <label class="psc-terms-opt">
                                    <input type="radio"
                                           name="agree[<?php echo esc_attr($term['id']); ?>]"
                                           value=""
                                           class="psc-terms-radio"
                                           data-termid="<?php echo esc_attr($term['id']); ?>"
                                           hidden>
                                    <span class="psc-terms-opt__btn" data-type="no">거부</span>
                                </label>
                                <label class="psc-terms-opt">
                                    <input type="radio"
                                           name="agree[<?php echo esc_attr($term['id']); ?>]"
                                           value="1"
                                           class="psc-terms-radio"
                                           data-termid="<?php echo esc_attr($term['id']); ?>"
                                           <?php echo $term['required'] ? 'required' : ''; ?>
                                           hidden>
                                    <span class="psc-terms-opt__btn" data-type="yes">동의</span>
                                </label>
                            </div>
                        </div>
                    </div>
                    <?php if ( $term['content'] ) : ?>
                    <div class="psc-terms-box" id="term_<?php echo esc_attr($term['id']); ?>">
                        <?php echo wp_kses_post($term['content']); ?>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            </div>

            <!-- 소셜 구분선 -->
            <div class="psc-reg-divider"><span>또는 소셜 계정으로 가입</span></div>

            <!-- 소셜 버튼 -->
            <div class="psc-reg-social">
                <a href="<?php echo esc_url(home_url('/auth/kakao/')); ?>"
                   class="psc-reg-social-btn psc-reg-social-btn--kakao">
                    <svg class="psc-reg-social-icon" viewBox="0 0 36 33.77"
                         xmlns="http://www.w3.org/2000/svg">
                        <path fill-rule="evenodd" clip-rule="evenodd"
                              d="M18,0C8.58,0,0,6.26,0,13.97c0,4.8,3.12,9.03,7.86,11.55l-2,7.33c-.18.65.56,1.17,1.13.79l8.75-5.81c.74.07,1.49.11,2.25.11,9.94,0,18-6.26,18-13.97S27.94,0,18,0"
                              fill="#3A1D1D"/>
                    </svg>
                    카카오로 시작하기
                </a>
                <a href="<?php echo esc_url(home_url('/auth/naver/')); ?>"
                   class="psc-reg-social-btn psc-reg-social-btn--naver">
                    <svg class="psc-reg-social-icon"
                         xmlns="http://www.w3.org/2000/svg"
                         xmlns:xlink="http://www.w3.org/1999/xlink"
                         width="18" height="19">
                        <image x="0" y="0" width="18" height="19"
                               xlink:href="data:img/png;base64,iVBORw0KGgoAAAANSUhEUgAAABIAAAATCAQAAAA3m5V5AAAABGdBTUEAALGPC/xhBQAAACBjSFJNAAB6JgAAgIQAAPoAAACA6AAAdTAAAOpgAAA6mAAAF3CculE8AAAAAmJLR0QA/4ePzL8AAAAHdElNRQfqAwgBJywQwHTKAAAAuUlEQVQoz4WQMQrCQBBF3yQiqKWNB9A7eAbvsOgtPIBgZytYCILHsLYQm5RiZWWdRkWIjkVIsmuSza/+zH54s1/0SKYbU0myQXeM8he1taDYR8XaDSU6rgoF2ArZa4+Sgr95yKoccnGpJn5cqq32/TiAAZt63MPypq6CSNe5j/VeF+rqteIj7uHyxPBp7ElOLP09RQDa0rMXByAJhldjT3Jh3oADUNGDFwcgyozYnju5/8rbIrQJM/8DAbYNCkou6sAAAAAASUVORK5CYII="/>
                    </svg>
                    네이버로 시작하기
                </a>
            </div>

            <button type="submit" class="psc-reg-btn">
                다음 단계
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                    <path d="M9 18l6-6-6-6" stroke="#fff" stroke-width="2.5"
                          stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
        </form>

        <div class="psc-reg-footer-link">
            이미 계정이 있으신가요?
            <a href="<?php echo esc_url(home_url('/login/')); ?>">로그인</a>
        </div>

        <?php elseif ( $step === 2 ) : ?>
        <!-- ════ STEP 2 : 회원정보 입력 ════ -->
        <form method="post" id="psc-reg-form" class="psc-reg-form">
            <?php wp_nonce_field('psc_register_action', 'psc_reg_nonce'); ?>

            <?php foreach ( $enabled_fields as $field ) :
                $id          = $field['id'];
                $label       = $field['label'];
                $placeholder = $field['placeholder'] ?? '';
                $required    = $field['required'];
                $type        = $field['type'];
                $old_val     = esc_attr( $_POST[$id] ?? '' );
            ?>
            <div class="psc-reg-field">
                <label class="psc-reg-field__label" for="<?php echo esc_attr($id); ?>">
                    <?php echo esc_html($label); ?>
                    <?php if ($required) : ?>
                        <span class="psc-reg-field__req">필수</span>
                    <?php endif; ?>
                </label>

                <?php if ( $id === 'user_login' ) : ?>
                    <div class="psc-reg-field__row">
                        <input type="text"
                               id="user_login"
                               name="user_login"
                               class="psc-reg-input"
                               placeholder="<?php echo esc_attr($placeholder); ?>"
                               value="<?php echo $old_val; ?>"
                               autocomplete="off">
                        <button type="button" class="psc-reg-check-btn"
                                onclick="pscCheckLogin()">중복확인</button>
                    </div>
                    <p class="psc-reg-hint" id="login_hint"></p>

                <?php elseif ( $id === 'user_email' ) : ?>
                    <div class="psc-reg-field__row">
                        <input type="email"
                               id="user_email"
                               name="user_email"
                               class="psc-reg-input"
                               placeholder="<?php echo esc_attr($placeholder); ?>"
                               value="<?php echo $old_val; ?>">
                        <button type="button" class="psc-reg-check-btn"
                                onclick="pscCheckEmail()">중복확인</button>
                    </div>
                    <p class="psc-reg-hint" id="email_hint"></p>

                <?php elseif ( $id === 'user_pass' ) : ?>
                    <div class="psc-reg-input-wrap">
                        <input type="password"
                               id="user_pass"
                               name="user_pass"
                               class="psc-reg-input"
                               placeholder="<?php echo esc_attr($placeholder ?: '8자 이상 입력해주세요'); ?>">
                        <button type="button" class="psc-reg-pwd-toggle"
                                onclick="pscTogglePwd('user_pass')">👁️</button>
                    </div>
                    <div class="psc-pass-strength-wrap" id="pass_strength"></div>

                <?php elseif ( $id === 'user_pass2' ) : ?>
                    <div class="psc-reg-input-wrap">
                        <input type="password"
                               id="user_pass2"
                               name="user_pass2"
                               class="psc-reg-input"
                               placeholder="<?php echo esc_attr($placeholder ?: '비밀번호를 다시 입력해주세요'); ?>">
                        <button type="button" class="psc-reg-pwd-toggle"
                                onclick="pscTogglePwd('user_pass2')">👁️</button>
                    </div>
                    <p class="psc-reg-hint" id="pass_match_hint"></p>

                <?php elseif ( $type === 'select' ) : ?>
                    <select name="<?php echo esc_attr($id); ?>"
                            id="<?php echo esc_attr($id); ?>"
                            class="psc-reg-input">
                        <option value="">— 선택하세요 —</option>
                        <?php foreach ( array_filter(explode("\n", $field['options'] ?? '')) as $opt ) :
                            $opt = trim($opt); ?>
                            <option value="<?php echo esc_attr($opt); ?>"
                                    <?php selected($old_val, $opt); ?>>
                                <?php echo esc_html($opt); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                <?php elseif ( $type === 'radio' ) : ?>
                    <div class="psc-reg-radio-group">
                    <?php foreach ( array_filter(explode("\n", $field['options'] ?? '')) as $opt ) :
                        $opt = trim($opt); ?>
                        <label class="psc-reg-radio">
                            <input type="radio"
                                   name="<?php echo esc_attr($id); ?>"
                                   value="<?php echo esc_attr($opt); ?>"
                                   <?php checked($old_val, $opt); ?> hidden>
                            <span><?php echo esc_html($opt); ?></span>
                        </label>
                    <?php endforeach; ?>
                    </div>

                <?php elseif ( $type === 'checkbox' ) : ?>
                    <label class="psc-reg-checkbox">
                        <input type="checkbox"
                               name="<?php echo esc_attr($id); ?>"
                               value="1"
                               <?php checked($old_val, '1'); ?>>
                        <span><?php echo esc_html($placeholder ?: $label); ?></span>
                    </label>

                <?php elseif ( $type === 'textarea' ) : ?>
                    <textarea name="<?php echo esc_attr($id); ?>"
                              id="<?php echo esc_attr($id); ?>"
                              class="psc-reg-input"
                              rows="4"
                              placeholder="<?php echo esc_attr($placeholder); ?>"><?php
                        echo esc_textarea($_POST[$id] ?? '');
                    ?></textarea>

                <?php else : ?>
                    <input type="<?php echo esc_attr($type); ?>"
                           id="<?php echo esc_attr($id); ?>"
                           name="<?php echo esc_attr($id); ?>"
                           class="psc-reg-input"
                           placeholder="<?php echo esc_attr($placeholder); ?>"
                           value="<?php echo $old_val; ?>"
                           <?php echo $required ? 'required' : ''; ?>>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>

            <button type="submit" class="psc-reg-btn"
                    onclick="return pscValidateStep2()">
                가입 완료 🎉
            </button>
        </form>

        <?php endif; ?>

    </div><!-- /.psc-reg-wrap -->
    <?php wp_footer(); ?>

    <script>
    var pscAjax      = { url: '<?php echo esc_js(admin_url('admin-ajax.php')); ?>' };
    var loginChecked = false;
    var emailChecked = false;

    /* ── 약관 내용 토글 ── */
    function pscToggleTerms(id) {
        var box = document.getElementById(id);
        if (box) box.classList.toggle('open');
    }

    /* ── 약관 버튼 시각화 ── */
    function pscSyncTermsBtn(radio) {
        var wrap = radio.closest('.psc-terms-agree-wrap');
        if (!wrap) return;
        wrap.querySelectorAll('.psc-terms-opt__btn').forEach(function(b) {
            b.classList.remove('active-yes', 'active-no');
        });
        var btn = radio.closest('.psc-terms-opt').querySelector('.psc-terms-opt__btn');
        if (btn) btn.classList.add(radio.value === '1' ? 'active-yes' : 'active-no');
        pscUpdateAllCheck();
    }

    /* ── 전체 동의 상태 업데이트 ── */
    function pscUpdateAllCheck() {
        var allRadios  = document.querySelectorAll('.psc-terms-radio[value="1"]');
        var allChecked = allRadios.length > 0 &&
                         Array.from(allRadios).every(function(r) { return r.checked; });
        var box = document.getElementById('agree_all_box');
        if (box) {
            var chk = box.querySelector('.psc-terms-all__checkbox');
            if (chk) chk.classList.toggle('checked', allChecked);
        }
    }

    /* ── 전체 동의 클릭 ── */
    (function() {
        var box = document.getElementById('agree_all_box');
        if (!box) return;
        box.addEventListener('click', function() {
            var chk       = box.querySelector('.psc-terms-all__checkbox');
            var isChecked = chk && chk.classList.contains('checked');
            var newState  = !isChecked;
            if (chk) chk.classList.toggle('checked', newState);

            document.querySelectorAll('.psc-terms-radio').forEach(function(r) {
                r.checked = (r.value === (newState ? '1' : ''));
                var wrap  = r.closest('.psc-terms-agree-wrap');
                if (!wrap) return;
                wrap.querySelectorAll('.psc-terms-opt__btn').forEach(function(b) {
                    b.classList.remove('active-yes', 'active-no');
                });
                var btn = r.closest('.psc-terms-opt').querySelector('.psc-terms-opt__btn');
                if (btn) btn.classList.add(r.value === '1' ? 'active-yes' : 'active-no');
            });
        });
    })();

    /* ── 개별 약관 버튼 클릭 ── */
    document.querySelectorAll('.psc-terms-opt__btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var radio = this.closest('.psc-terms-opt').querySelector('input[type="radio"]');
            if (radio) { radio.checked = true; pscSyncTermsBtn(radio); }
        });
    });

    /* ── 비밀번호 토글 ── */
    function pscTogglePwd(inputId) {
        var input = document.getElementById(inputId);
        var btn   = document.querySelector('[onclick*="' + inputId + '"]');
        if (!input) return;
        input.type = input.type === 'password' ? 'text' : 'password';
        if (btn) btn.textContent = input.type === 'password' ? '👁️' : '🙈';
    }

    /* ── 아이디 중복확인 ── */
    function pscCheckLogin() {
        var loginEl = document.getElementById('user_login');
        var hint    = document.getElementById('login_hint');
        if (!loginEl || !hint) return;
        var login = loginEl.value.trim();
        if (!login) {
            hint.textContent = '아이디를 입력해 주세요.';
            hint.className   = 'psc-reg-hint error';
            return;
        }
        hint.textContent = '확인 중…';
        hint.className   = 'psc-reg-hint';
        fetch(pscAjax.url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'psc_check_login', login: login })
        })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            hint.textContent = d.data.message;
            hint.className   = 'psc-reg-hint ' + (d.success ? 'success' : 'error');
            loginChecked     = d.success;
        });
    }

    /* ── 이메일 중복확인 ── */
    function pscCheckEmail() {
        var emailEl = document.getElementById('user_email');
        var hint    = document.getElementById('email_hint');
        if (!emailEl || !hint) return;
        var email = emailEl.value.trim();
        hint.textContent = '확인 중…';
        hint.className   = 'psc-reg-hint';
        fetch(pscAjax.url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'psc_check_email', email: email })
        })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            hint.textContent = d.data.message;
            hint.className   = 'psc-reg-hint ' + (d.success ? 'success' : 'error');
            emailChecked     = d.success;
        });
    }

    /* ── 비밀번호 강도 ── */
    var passInput = document.getElementById('user_pass');
    if (passInput) {
        passInput.addEventListener('input', function() {
            var v = this.value;
            var s = document.getElementById('pass_strength');
            if (!s) return;
            if (!v) { s.innerHTML = ''; return; }
            var score = 0;
            if (v.length >= 8)          score++;
            if (/[A-Z]/.test(v))        score++;
            if (/[0-9]/.test(v))        score++;
            if (/[^A-Za-z0-9]/.test(v)) score++;
            var labels = ['', '약함', '보통', '강함', '매우 강함'];
            var colors = ['', '#e53e3e', '#ed8936', '#48bb78', '#38a169'];
            s.innerHTML =
                '<div style="height:4px;border-radius:2px;background:#f0f0f0;margin-bottom:4px">'
              + '<div style="height:4px;border-radius:2px;width:' + (score*25) + '%;background:'
              + colors[score] + ';transition:width .3s"></div></div>'
              + '<span style="font-size:.75rem;color:' + colors[score] + '">' + labels[score] + '</span>';
        });
    }

    /* ── 비밀번호 확인 ── */
    var pass2Input = document.getElementById('user_pass2');
    if (pass2Input) {
        pass2Input.addEventListener('input', function() {
            var pass  = document.getElementById('user_pass').value;
            var hint  = document.getElementById('pass_match_hint');
            var match = this.value === pass;
            hint.textContent = this.value
                ? (match ? '✅ 비밀번호가 일치합니다.' : '❌ 비밀번호가 일치하지 않습니다.')
                : '';
            hint.className = 'psc-reg-hint ' + (match ? 'success' : 'error');
        });
    }

    /* ── STEP 2 최종 검증 ── */
    function pscValidateStep2() {
        var loginEl = document.getElementById('user_login');
        var emailEl = document.getElementById('user_email');
        if (loginEl && !loginChecked) {
            alert('아이디 중복확인을 해주세요.');
            loginEl.focus();
            return false;
        }
        if (emailEl && !emailChecked) {
            alert('이메일 중복확인을 해주세요.');
            emailEl.focus();
            return false;
        }
        var p  = document.getElementById('user_pass');
        var p2 = document.getElementById('user_pass2');
        if (p && p2 && p.value !== p2.value) {
            alert('비밀번호가 일치하지 않습니다.');
            p2.focus();
            return false;
        }
        if (p && p.value.length < 8) {
            alert('비밀번호는 8자 이상이어야 합니다.');
            p.focus();
            return false;
        }
        return true;
    }
    </script>
    </body>
    </html>
    <?php
}

/* ============================================================
   9. 가입 완료 페이지
   ============================================================ */
function psc_register_done_page(): void {
    if ( ! is_user_logged_in() ) {
        wp_redirect( home_url('/register/') );
        exit;
    }
    $user     = wp_get_current_user();
    $design   = psc_register_get_design();
    $logo_url = $design['logo_url'] ?? '';
    $logo_src = $logo_url ?: home_url('/wp-content/uploads/2026/02/ci.svg');
    ?>
    <!DOCTYPE html>
    <html <?php language_attributes(); ?>>
    <head>
        <meta charset="<?php bloginfo('charset'); ?>">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>가입 완료 — <?php bloginfo('name'); ?></title>
        <link rel="stylesheet"
              href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard/dist/web/static/pretendard.css">
        <?php wp_head(); ?>
    </head>
    <body class="psc-reg-body">
    <div class="psc-reg-wrap">
        <div class="psc-reg-done">
            <div class="psc-reg-done__circle">
                <svg width="40" height="40" viewBox="0 0 40 40" fill="none">
                    <path d="M8 20l8 8 16-16" stroke="#fff" stroke-width="3.5"
                          stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <h1 class="psc-reg-done__title">가입을 환영합니다! 🎉</h1>
            <p class="psc-reg-done__name">
                <strong><?php echo esc_html($user->display_name); ?></strong>님,<br>
                <?php bloginfo('name'); ?> 회원이 되셨습니다.
            </p>
            <p class="psc-reg-done__sub">
                이제 반려동물과 함께하는<br>모든 공간을 이용할 수 있어요 🐾
            </p>
            <a href="<?php echo esc_url(home_url('/')); ?>" class="psc-reg-btn">
                홈으로 가기
            </a>
            <a href="<?php echo esc_url(home_url('/mypage/')); ?>"
               class="psc-reg-btn psc-reg-btn--outline">
                마이페이지 보기
            </a>
        </div>
    </div>
    <?php wp_footer(); ?>
    </body>
    </html>
    <?php
}
