<?php
/**
 * 로그인 모듈
 * File: modules/auth/login.php
 * 페이지 슬러그: /login/
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/* ============================================================
   1. Rewrite Rules
   ============================================================ */
add_action( 'init', 'psc_login_rewrites' );
function psc_login_rewrites(): void {
    add_rewrite_rule( '^login/?$', 'index.php?psc_auth=login', 'top' );
}

/* ============================================================
   2. 라우터
   ============================================================ */
add_action( 'template_redirect', 'psc_login_router', 9 );
function psc_login_router(): void {
    if ( get_query_var('psc_auth') !== 'login' ) return;

    if ( is_user_logged_in() ) {
        wp_redirect( home_url('/') );
        exit;
    }

    psc_login_handle_post();
    psc_login_page();
    exit;
}

/* ============================================================
   3. POST 처리
   ============================================================ */
function psc_login_handle_post(): void {
    if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) return;
    if ( empty( $_POST['psc_login_nonce'] ) ) return;
    if ( ! wp_verify_nonce( $_POST['psc_login_nonce'], 'psc_login_action' ) ) {
        psc_login_page( '보안 오류가 발생했습니다.' );
        exit;
    }

    $login    = sanitize_text_field( $_POST['user_login'] ?? '' );
    $password = $_POST['user_pass'] ?? '';
    $remember = ! empty( $_POST['remember'] );

    if ( ! $login || ! $password ) {
        psc_login_page( '아이디와 비밀번호를 입력해 주세요.' );
        exit;
    }

    $user = wp_signon([
        'user_login'    => $login,
        'user_password' => $password,
        'remember'      => $remember,
    ], false );

    if ( is_wp_error($user) ) {
        psc_login_page( '아이디 또는 비밀번호가 올바르지 않습니다.' );
        exit;
    }

    $redirect = isset($_POST['redirect_to']) && $_POST['redirect_to']
        ? esc_url_raw( $_POST['redirect_to'] )
        : home_url('/');

    wp_redirect( $redirect );
    exit;
}

/* ============================================================
   4. 페이지 렌더링
   ============================================================ */
function psc_login_page( string $error = '' ): void {
    $redirect    = esc_url_raw( $_GET['redirect_to'] ?? '' );
    $promo_title = get_option( 'psc_login_promo_title', '반갑습니다!' );
    $promo_sub   = get_option( 'psc_login_promo_sub',   '반려동물과 함께하는 모든 공간' );
    $promo_img   = get_option( 'psc_login_promo_img',   '' );
    $promo_badge = get_option( 'psc_login_promo_badge', '' );
    ?>
    <!DOCTYPE html>
    <html <?php language_attributes(); ?>>
    <head>
        <meta charset="<?php bloginfo('charset'); ?>">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>로그인 — <?php bloginfo('name'); ?></title>
        <!-- Pretendard -->
        <link rel="stylesheet"
              href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard/dist/web/static/pretendard.css">
        <?php wp_head(); ?>
        <style><?php echo psc_login_css(); ?></style>
    </head>
    <body class="psc-login-body">
    <div class="psc-login-wrap">

        <!-- 탑바 -->
        <div class="psc-login-topbar">
            <a href="<?php echo esc_url( home_url('/') ); ?>"
               class="psc-login-back">‹</a>
        </div>

        <!-- 히어로 -->
        <div class="psc-login-hero">
            <img src="<?php echo esc_url( home_url('/wp-content/uploads/2026/02/ci.svg') ); ?>"
                 alt="<?php bloginfo('name'); ?>"
                 class="psc-login-hero__logo"
                 onerror="this.style.display='none'">

            <?php if ( $promo_img ) : ?>
                <div class="psc-login-hero__img-wrap">
                    <img src="<?php echo esc_url($promo_img); ?>"
                         alt="" class="psc-login-hero__img">
                    <?php if ( $promo_badge ) : ?>
                        <div class="psc-login-hero__badge">
                            <?php echo wp_kses_post($promo_badge); ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <h1 class="psc-login-hero__title">
                <?php echo esc_html($promo_title); ?><br>
                <span><?php bloginfo('name'); ?></span>에 로그인하세요
            </h1>
            <p class="psc-login-hero__sub">
                <?php echo esc_html($promo_sub); ?>
            </p>
        </div>

        <!-- 소셜 로그인 -->
        <div class="psc-login-social">
            <a href="<?php echo esc_url( home_url('/auth/kakao/') ); ?>"
               class="psc-login-social-btn psc-login-social-btn--kakao">
                <!-- 카카오 공식 SVG -->
                <svg class="psc-social-icon" viewBox="0 0 36 33.77"
                     xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd"
                          d="M18,0C8.58,0,0,6.26,0,13.97c0,4.8,3.12,9.03,7.86,11.55l-2,7.33c-.18.65.56,1.17,1.13.79l8.75-5.81c.74.07,1.49.11,2.25.11,9.94,0,18-6.26,18-13.97S27.94,0,18,0"
                          fill="#3A1D1D"/>
                </svg>
                카카오로 계속하기
            </a>

            <a href="<?php echo esc_url( home_url('/auth/naver/') ); ?>"
               class="psc-login-social-btn psc-login-social-btn--naver">
                <!-- 네이버 공식 SVG -->
                <svg class="psc-social-icon"
                     xmlns="http://www.w3.org/2000/svg"
                     xmlns:xlink="http://www.w3.org/1999/xlink"
                     width="18px" height="19px">
                    <image x="0px" y="0px" width="18px" height="19px"
                           xlink:href="data:img/png;base64,iVBORw0KGgoAAAANSUhEUgAAABIAAAATCAQAAAA3m5V5AAAABGdBTUEAALGPC/xhBQAAACBjSFJNAAB6JgAAgIQAAPoAAACA6AAAdTAAAOpgAAA6mAAAF3CculE8AAAAAmJLR0QA/4ePzL8AAAAHdElNRQfqAwgBJywQwHTKAAAAuUlEQVQoz4WQMQrCQBBF3yQiqKWNB9A7eAbvsOgtPIBgZytYCILHsLYQm5RiZWWdRkWIjkVIsmuSza/+zH54s1/0SKYbU0myQXeM8he1taDYR8XaDSU6rgoF2ArZa4+Sgr95yKoccnGpJn5cqq32/TiAAZt63MPypq6CSNe5j/VeF+rqteIj7uHyxPBp7ElOLP09RQDa0rMXByAJhldjT3Jh3oADUNGDFwcgyozYnju5/8rbIrQJM/8DAbYNCkou6sAAAAAASUVORK5CYII="/>
                </svg>
                네이버로 계속하기
            </a>
        </div>

        <!-- 구분선 -->
        <div class="psc-login-divider">이메일로 로그인</div>

        <!-- 이메일 폼 -->
        <div class="psc-login-form-section">

            <?php if ( $error ) : ?>
                <div class="psc-login-error">
                    <span>⚠️</span>
                    <span><?php echo esc_html($error); ?></span>
                </div>
            <?php endif; ?>

            <form method="post" id="psc-login-form">
                <?php wp_nonce_field('psc_login_action', 'psc_login_nonce'); ?>
                <?php if ( $redirect ) : ?>
                    <input type="hidden" name="redirect_to"
                           value="<?php echo esc_attr($redirect); ?>">
                <?php endif; ?>

                <div class="psc-login-input-group">
                    <label for="user_login">아이디 또는 이메일</label>
                    <div class="psc-login-input-wrap">
                        <input type="text"
                               id="user_login"
                               name="user_login"
                               placeholder="아이디 또는 이메일 입력"
                               value="<?php echo esc_attr($_POST['user_login'] ?? ''); ?>"
                               autocomplete="username">
                    </div>
                </div>

                <div class="psc-login-input-group">
                    <label for="user_pass">비밀번호</label>
                    <div class="psc-login-input-wrap">
                        <input type="password"
                               id="user_pass"
                               name="user_pass"
                               placeholder="비밀번호 입력"
                               autocomplete="current-password">
                        <button type="button"
                                class="psc-login-pwd-toggle"
                                onclick="pscTogglePass()"
                                id="psc-pwd-toggle">👁️</button>
                    </div>
                </div>

                <div class="psc-login-options">
                    <label class="psc-login-remember">
                        <input type="checkbox" name="remember" value="1">
                        로그인 상태 유지
                    </label>
                    <a href="<?php echo esc_url( home_url('/reset-password/') ); ?>"
                       class="psc-login-forgot">비밀번호 찾기</a>
                </div>

                <button type="submit" class="psc-login-btn" id="psc-login-btn">
                    로그인
                </button>

            </form>
        </div>

        <!-- 회원가입 유도 -->
        <div class="psc-login-signup-area">
            <p class="psc-login-signup-area__text">아직 계정이 없으신가요?</p>
            <a href="<?php echo esc_url( home_url('/register/') ); ?>"
               class="psc-login-signup-btn">
                🐾 이메일로 회원가입
            </a>
        </div>

        <!-- 나중에 -->
        <div class="psc-login-later">
            <a href="<?php echo esc_url( home_url('/') ); ?>">
                나중에 할게요
            </a>
        </div>

    </div><!-- /.psc-login-wrap -->

    <?php wp_footer(); ?>

    <script>
    /* 비밀번호 토글 */
    function pscTogglePass() {
        const input  = document.getElementById('user_pass');
        const toggle = document.getElementById('psc-pwd-toggle');
        input.type         = input.type === 'password' ? 'text' : 'password';
        toggle.textContent = input.type === 'password' ? '👁️' : '🙈';
    }

    /* 엔터 제출 */
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            document.getElementById('psc-login-form').submit();
        }
    });

    /* 제출 시 버튼 로딩 상태 */
    document.getElementById('psc-login-form').addEventListener('submit', function() {
        const btn       = document.getElementById('psc-login-btn');
        btn.disabled    = true;
        btn.textContent = '로그인 중…';
    });
    </script>

    </body>
    </html>
    <?php
}

/* ============================================================
   5. CSS
   ============================================================ */
function psc_login_css(): string { return '
/* ── 리셋 & 기본 ── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body.psc-login-body {
    font-family: "Pretendard", -apple-system, "Apple SD Gothic Neo",
                 "Noto Sans KR", sans-serif;
    background: #fff;
    color: #191f28;
    min-height: 100vh;
    -webkit-font-smoothing: antialiased;
}

.psc-login-wrap {
    max-width: 480px;
    margin: 0 auto;
    background: #fff;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
}

/* ── 폼 내부 간격 ── */
#psc-login-form {
    display: flex;
    flex-direction: column;
    gap: 20px;
    width: 100%;
}

/* ── 탑바 ── */
.psc-login-topbar {
    padding: 16px 20px;
    display: flex;
    align-items: center;
}
.psc-login-back {
    width: 36px; height: 36px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.4rem; font-weight: 300;
    color: #191f28; text-decoration: none;
    border: none; background: none; cursor: pointer;
}

/* ── 히어로 ── */
.psc-login-hero {
    padding: 8px 24px 20px; /* 32px → 20px */
}
.psc-login-hero__logo {
    height: 36px;
    display: block;
    margin-bottom: 16px; /* 20px → 16px */
    object-fit: contain;
}
.psc-login-hero__img-wrap {
    position: relative;
    display: inline-block;
    margin-bottom: 20px;
}
.psc-login-hero__img {
    max-height: 160px;
    object-fit: contain;
    border-radius: 16px;
}
.psc-login-hero__badge {
    position: absolute; bottom: -10px; left: 50%;
    transform: translateX(-50%);
    background: linear-gradient(135deg, #f97316, #fb923c);
    color: #fff; padding: 5px 16px; border-radius: 20px;
    font-size: .78rem; font-weight: 700; white-space: nowrap;
    box-shadow: 0 4px 12px rgba(249,115,22,.3);
}
.psc-login-hero__title {
    font-size: 1.5rem;
    font-weight: 800;
    color: #191f28;
    line-height: 1.35;
    margin-bottom: 6px; /* 8px → 6px */
}
.psc-login-hero__title span { color: #224471; }
.psc-login-hero__sub {
    font-size: .88rem;
    color: #8b95a1;
    line-height: 1.5;
    margin-bottom: 0; /* 추가 */
}

/* ── 소셜 버튼 ── */
.psc-login-social {
    padding: 0 24px;
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-top: 20px; /* 추가 */
}
.psc-login-social-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    height: 52px;
    padding: 0 20px;
    border-radius: 12px;
    font-size: .94rem;
    font-weight: 700;
    text-decoration: none;
    border: none; cursor: pointer;
    transition: filter .15s, transform .1s;
    font-family: "Pretendard", sans-serif;
}
.psc-login-social-btn:active { transform: scale(.98); }

/* 카카오 공식 */
.psc-login-social-btn--kakao {
    background: #FEE500;
    color: #000000;
}
.psc-login-social-btn--kakao:hover { filter: brightness(.96); }

/* 네이버 공식 */
.psc-login-social-btn--naver {
    background: #03C75A;
    color: #ffffff;
}
.psc-login-social-btn--naver:hover { filter: brightness(.96); }

.psc-social-icon {
    flex-shrink: 0;
    width: 20px; height: 20px;
}

/* 구분선 */
.psc-login-divider {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 20px 24px; /* 14px → 20px */
    color: #b0b8c1;
    font-size: .8rem;
    font-weight: 500;
}
.psc-login-divider::before,
.psc-login-divider::after {
    content: "";
    flex: 1;
    height: 1px;
    background: #e5e8eb;
}

/* ── 폼 섹션 ── */
.psc-login-form-section {
    padding: 0 24px;
    display: flex;
    flex-direction: column;
    gap: 20px; /* 12px → 20px */
}

/* 에러 */
.psc-login-error {
    background: #fff0f0;
    border: 1.5px solid #ffd6d6;
    border-radius: 10px;
    padding: 12px 14px;
    font-size: .84rem;
    color: #ff4d4f;
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 500;
}

/* 인풋 그룹 */
.psc-login-input-group {
    display: flex;
    flex-direction: column;
    gap: 10px; /* 7px → 10px */
}
.psc-login-input-group label {
    font-size: .86rem; /* .82rem → .86rem */
    font-weight: 700;
    color: #4e5968;
}
.psc-login-input-wrap input {
    width: 100%;
    padding: 17px 16px; /* 15px → 17px */
    background: #f4f6f9;
    border: 1.5px solid transparent;
    border-radius: 12px;
    font-size: .94rem;
    color: #191f28;
    outline: none;
    transition: border-color .2s, background .2s, box-shadow .2s;
    font-family: "Pretendard", sans-serif;
}
.psc-login-input-wrap input:focus {
    border-color: #224471;
    background: #fff;
    box-shadow: 0 0 0 3px rgba(34,68,113,.08);
}
.psc-login-input-wrap input::placeholder { color: #b0b8c1; }

/* 비밀번호 토글 */
.psc-login-pwd-toggle {
    position: absolute;
    right: 14px; top: 50%;
    transform: translateY(-50%);
    background: none; border: none;
    cursor: pointer; color: #b0b8c1;
    font-size: .9rem; padding: 4px;
}
.psc-login-input-wrap input[type="password"],
.psc-login-input-wrap input[type="text"] {
    padding-right: 48px;
}

/* 옵션 행 */
.psc-login-options {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 4px 2px; /* 0 → 4px */
    margin-top: 0; /* -2px → 0 */
}
.psc-login-remember {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: .82rem;
    color: #4e5968;
    font-weight: 500;
    cursor: pointer;
}
.psc-login-remember input[type="checkbox"] {
    width: 16px; height: 16px;
    accent-color: #224471;
    cursor: pointer;
}
.psc-login-forgot {
    font-size: .82rem;
    color: #8b95a1;
    text-decoration: none;
    font-weight: 500;
    transition: color .2s;
}
.psc-login-forgot:hover { color: #224471; }

/* 로그인 버튼 */
.psc-login-btn {
    width: 100%;
    padding: 18px; /* 16px → 18px */
    background: #224471;
    color: #fff;
    border: none;
    border-radius: 12px;
    font-size: 1rem;
    font-weight: 700;
    cursor: pointer;
    transition: background .2s, transform .1s;
    font-family: "Pretendard", sans-serif;
    margin-top: 4px; /* 2px → 4px */
    letter-spacing: -.01em;
}
.psc-login-btn:hover  { background: #1a3459; }
.psc-login-btn:active { transform: scale(.98); }
.psc-login-btn:disabled {
    background: #e5e8eb;
    color: #b0b8c1;
    cursor: not-allowed;
    transform: none;
}

/* 회원가입 유도 */
.psc-login-signup-area {
    padding: 28px 24px 0; /* 20px → 28px */
    display: flex;
    flex-direction: column;
    gap: 12px; /* 10px → 12px */
}
.psc-login-signup-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    padding: 17px; /* 15px → 17px */
    background: #e8eef6;
    color: #224471;
    border: none;
    border-radius: 12px;
    font-size: .94rem;
    font-weight: 700;
    cursor: pointer;
    text-decoration: none;
    transition: background .15s;
    font-family: "Pretendard", sans-serif;
}
.psc-login-signup-btn:hover { background: #d4e0f0; }

/* ── 나중에 ── */
.psc-login-later {
    margin-top: auto;
    padding: 24px;
    text-align: center;
}
.psc-login-later a {
    font-size: .84rem;
    color: #b0b8c1;
    text-decoration: none;
    transition: color .2s;
}
.psc-login-later a:hover { color: #8b95a1; }
';}

/* ============================================================
   6. 로그인 프로모션 관리자 설정
   ============================================================ */
add_action( 'admin_menu', 'psc_login_admin_menu' );
function psc_login_admin_menu(): void {
    add_submenu_page(
        'psc-register-settings',
        '로그인 페이지 설정',
        '🔑 로그인 설정',
        'manage_options',
        'psc-login-settings',
        'psc_login_admin_page'
    );
}

function psc_login_admin_page(): void {
    if ( isset($_POST['psc_save_login']) &&
         check_admin_referer('psc_login_save') ) {
        update_option( 'psc_login_promo_title', sanitize_text_field($_POST['promo_title'] ?? '') );
        update_option( 'psc_login_promo_sub',   sanitize_text_field($_POST['promo_sub']   ?? '') );
        update_option( 'psc_login_promo_img',   esc_url_raw($_POST['promo_img']           ?? '') );
        update_option( 'psc_login_promo_badge', wp_kses_post($_POST['promo_badge']        ?? '') );
        echo '<div class="notice notice-success is-dismissible"><p>✅ 저장되었습니다.</p></div>';
    }
    ?>
    <div class="wrap">
    <h1>🔑 로그인 페이지 설정</h1>
    <form method="post">
        <?php wp_nonce_field('psc_login_save'); ?>
        <table class="form-table">
            <tr>
                <th>프로모션 제목</th>
                <td>
                    <input type="text" name="promo_title" class="large-text"
                           value="<?php echo esc_attr(get_option('psc_login_promo_title','반갑습니다!')); ?>">
                    <p class="description">로그인 페이지 상단 환영 문구</p>
                </td>
            </tr>
            <tr>
                <th>프로모션 부제</th>
                <td>
                    <input type="text" name="promo_sub" class="large-text"
                           value="<?php echo esc_attr(get_option('psc_login_promo_sub','반려동물과 함께하는 모든 공간')); ?>">
                </td>
            </tr>
            <tr>
                <th>프로모션 이미지</th>
                <td>
                    <input type="text" name="promo_img" class="large-text"
                           id="psc_promo_img"
                           value="<?php echo esc_attr(get_option('psc_login_promo_img','')); ?>">
                    <button type="button" class="button" onclick="pscLoginMedia()">
                        미디어에서 선택
                    </button>
                    <?php $img = get_option('psc_login_promo_img',''); ?>
                    <?php if ($img) : ?>
                        <br><img src="<?php echo esc_url($img); ?>"
                                 style="max-height:80px;margin-top:8px;border-radius:8px">
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th>뱃지 텍스트</th>
                <td>
                    <input type="text" name="promo_badge" class="regular-text"
                           value="<?php echo esc_attr(get_option('psc_login_promo_badge','')); ?>">
                    <p class="description">예: 가입 즉시 <strong>5,000원</strong> 적립</p>
                </td>
            </tr>
        </table>
        <p class="submit">
            <input type="submit" name="psc_save_login"
                   class="button button-primary" value="💾 저장">
        </p>
    </form>
    <script>
    function pscLoginMedia() {
        var frame = wp.media({ title:'이미지 선택', multiple:false });
        frame.on('select', function() {
            var att = frame.state().get('selection').first().toJSON();
            document.getElementById('psc_promo_img').value = att.url;
        });
        frame.open();
    }
    </script>
    </div>
    <?php
}

/* ============================================================
   7. 로그아웃 처리
   ============================================================ */
add_action( 'init', 'psc_logout_handler' );
function psc_logout_handler(): void {
    if ( isset( $_GET['psc_action'] ) && $_GET['psc_action'] === 'logout' ) {
        if ( is_user_logged_in() ) wp_logout();
        wp_redirect( home_url('/login/') );
        exit;
    }
}

/* ============================================================
   8. wp-login.php 리다이렉트
   ============================================================ */
   add_action( 'init', 'psc_redirect_login_page' );
   function psc_redirect_login_page(): void {
       $page   = $_SERVER['REQUEST_URI'] ?? '';
       $action = $_GET['action'] ?? '';
       if (
           strpos( $page, 'wp-login.php' ) !== false &&
           ! in_array( $action, ['logout','lostpassword','rp','resetpass','register','postpass'], true ) &&
           ! isset( $_GET['key'] )
       ) {
           if ( is_user_logged_in() ) {
               wp_redirect( home_url('/') );
           } else {
               // redirect_to 파라미터 유지해서 넘기기
               $redirect_to = isset( $_GET['redirect_to'] ) ? $_GET['redirect_to'] : '';
               $login_url   = home_url('/login/');
               if ( $redirect_to ) {
                   $login_url = add_query_arg( 'redirect_to', urlencode( $redirect_to ), $login_url );
               }
               wp_redirect( $login_url );
           }
           exit;
       }
   }
   
   add_filter( 'login_url', 'psc_custom_login_url', 10, 3 );
   function psc_custom_login_url( string $login_url, string $redirect, bool $force_reauth ): string {
       $custom = home_url('/login/');
       if ( $redirect ) {
           $custom = add_query_arg('redirect_to', urlencode($redirect), $custom);
       }
       return $custom;
   }
   
   add_filter( 'logout_redirect', 'psc_logout_redirect', 10, 3 );
   function psc_logout_redirect( string $redirect_to, string $requested_redirect_to, WP_User $user ): string {
       return home_url('/login/');
   }
