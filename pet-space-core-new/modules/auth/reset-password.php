<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ============================================================
   Rewrite Rules
   ============================================================ */
add_action( 'init', 'psc_reset_rewrites' );
function psc_reset_rewrites(): void {
    add_rewrite_rule( '^reset-password/?$',          'index.php?psc_auth=reset_request', 'top' );
    add_rewrite_rule( '^reset-password/confirm/?$',  'index.php?psc_auth=reset_confirm', 'top' );
    add_rewrite_rule( '^reset-password/complete/?$', 'index.php?psc_auth=reset_complete','top' );
}

/* ============================================================
   Router
   ============================================================ */
add_action( 'template_redirect', 'psc_reset_router' );
function psc_reset_router(): void {
    $page = get_query_var( 'psc_auth' );
    if ( ! $page ) return;
    if ( is_user_logged_in() ) { wp_redirect( home_url( '/' ) ); exit; }

    match ( $page ) {
        'reset_request'  => psc_reset_request_page(),
        'reset_confirm'  => psc_reset_confirm_page(),
        'reset_complete' => psc_reset_complete_page(),
        default          => null,
    };
}

/* ============================================================
   STEP 1 — 이메일 입력 & 메일 발송
   ============================================================ */
function psc_reset_request_page(): void {
    $error   = '';
    $success = false;

    if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['psc_reset_request'] ) ) {
        if ( ! wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'psc_reset_request_nonce' ) ) {
            $error = '보안 토큰이 유효하지 않습니다. 새로고침 후 다시 시도해주세요.';
        } else {
            $email = sanitize_email( $_POST['user_email'] ?? '' );
            if ( empty( $email ) ) {
                $error = '이메일 주소를 입력해주세요.';
            } else {
                $user = get_user_by( 'email', $email );
                if ( $user ) {
                    $token   = bin2hex( random_bytes( 32 ) );
                    $expires = time() + ( 60 * 60 );
                    update_user_meta( $user->ID, '_psc_reset_token',   $token   );
                    update_user_meta( $user->ID, '_psc_reset_expires', $expires );

                    $reset_url = add_query_arg(
                        [ 'uid' => $user->ID, 'token' => $token ],
                        home_url( '/reset-password/confirm/' )
                    );

                    $subject = get_option( 'psc_email_reset_subject', '[펫스페이스] 비밀번호 재설정 안내' );
                    $body    = get_option( 'psc_email_reset_body', psc_email_default_reset_body() );
                    $message = psc_email_parse( $body, [
                        'name'       => $user->display_name,
                        'email'      => $user->user_email,
                        'reset_link' => $reset_url,
                        'site_name'  => get_bloginfo('name'),
                        'site_url'   => home_url('/'),
                        'login_url'  => home_url('/login/'),
                    ]);
                    $headers = [ 'Content-Type: text/html; charset=UTF-8' ];
                    wp_mail( $email, $subject, $message, $headers );
                }
                $success = true;
            }
        }
    }

    $d = psc_register_get_design();
    psc_reset_render_header( $d );
    ?>
    <div class="psc-rp-wrap">
        <div class="psc-rp-card">

            <!-- 로고 -->
            <div class="psc-rp-logo">
                <?php if ( $d['logo_url'] ) : ?>
                    <img src="<?php echo esc_url( $d['logo_url'] ); ?>" alt="로고">
                <?php else : ?>
                    <span><?php echo esc_html( $d['logo_text'] ); ?></span>
                <?php endif; ?>
            </div>

            <!-- 스텝 인디케이터 -->
            <div class="psc-rp-steps">
                <div class="psc-rp-step active">
                    <div class="psc-rp-step__dot">1</div>
                    <div class="psc-rp-step__label">이메일 입력</div>
                </div>
                <div class="psc-rp-step__line"></div>
                <div class="psc-rp-step">
                    <div class="psc-rp-step__dot">2</div>
                    <div class="psc-rp-step__label">비밀번호 설정</div>
                </div>
                <div class="psc-rp-step__line"></div>
                <div class="psc-rp-step">
                    <div class="psc-rp-step__dot">3</div>
                    <div class="psc-rp-step__label">완료</div>
                </div>
            </div>

            <?php if ( $success ) : ?>

                <!-- 발송 완료 상태 -->
                <div class="psc-rp-result">
                    <div class="psc-rp-result__icon">📬</div>
                    <div class="psc-rp-result__title">메일을 발송했습니다</div>
                    <div class="psc-rp-result__desc">
                        입력하신 이메일로 재설정 링크를 보내드렸어요.<br>
                        메일이 오지 않으면 스팸함을 확인해주세요.
                    </div>
                </div>
                <a href="<?php echo esc_url( home_url( '/login/' ) ); ?>"
                   class="psc-rp-btn psc-rp-btn--outline">
                    로그인으로 돌아가기
                </a>

            <?php else : ?>

                <div class="psc-rp-header">
                    <div class="psc-rp-header__title">비밀번호를 잊으셨나요?</div>
                    <div class="psc-rp-header__desc">
                        가입 시 등록한 이메일을 입력하시면<br>재설정 링크를 보내드립니다.
                    </div>
                </div>

                <?php if ( $error ) : ?>
                    <div class="psc-rp-notice psc-rp-notice--error">
                        <?php echo esc_html( $error ); ?>
                    </div>
                <?php endif; ?>

                <form method="post" class="psc-rp-form">
                    <?php wp_nonce_field( 'psc_reset_request_nonce' ); ?>

                    <div class="psc-rp-field">
                        <label class="psc-rp-field__label" for="user_email">이메일 주소</label>
                        <input type="email"
                               id="user_email"
                               name="user_email"
                               class="psc-rp-input"
                               placeholder="example@email.com"
                               required autofocus>
                    </div>

                    <button type="submit" name="psc_reset_request" class="psc-rp-btn">
                        재설정 링크 발송
                    </button>
                </form>

                <div class="psc-rp-footer-link">
                    <a href="<?php echo esc_url( home_url( '/login/' ) ); ?>">
                        ← 로그인으로 돌아가기
                    </a>
                </div>

            <?php endif; ?>
        </div>
    </div>
    <?php
    psc_reset_render_footer();
}

/* ============================================================
   STEP 2 — 새 비밀번호 입력
   ============================================================ */
function psc_reset_confirm_page(): void {
    $uid   = absint( $_GET['uid']   ?? 0 );
    $token = sanitize_text_field( $_GET['token'] ?? '' );
    $error = '';

    if ( ! $uid || ! $token ) {
        psc_reset_invalid_link();
        return;
    }
    $saved_token   = get_user_meta( $uid, '_psc_reset_token',   true );
    $saved_expires = get_user_meta( $uid, '_psc_reset_expires', true );

    if ( ! $saved_token || $token !== $saved_token || time() > (int) $saved_expires ) {
        psc_reset_invalid_link();
        return;
    }

    if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['psc_reset_confirm'] ) ) {
        if ( ! wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'psc_reset_confirm_nonce' ) ) {
            $error = '보안 토큰이 유효하지 않습니다.';
        } else {
            $pass1 = $_POST['new_pass']  ?? '';
            $pass2 = $_POST['new_pass2'] ?? '';

            if ( strlen( $pass1 ) < 8 ) {
                $error = '비밀번호는 8자 이상이어야 합니다.';
            } elseif ( $pass1 !== $pass2 ) {
                $error = '비밀번호가 일치하지 않습니다.';
            } else {
                wp_set_password( $pass1, $uid );
                delete_user_meta( $uid, '_psc_reset_token'   );
                delete_user_meta( $uid, '_psc_reset_expires' );
                wp_redirect( home_url( '/reset-password/complete/' ) );
                exit;
            }
        }
    }

    $d = psc_register_get_design();
    psc_reset_render_header( $d );
    ?>
    <div class="psc-rp-wrap">
        <div class="psc-rp-card">

            <!-- 로고 -->
            <div class="psc-rp-logo">
                <?php if ( $d['logo_url'] ) : ?>
                    <img src="<?php echo esc_url( $d['logo_url'] ); ?>" alt="로고">
                <?php else : ?>
                    <span><?php echo esc_html( $d['logo_text'] ); ?></span>
                <?php endif; ?>
            </div>

            <!-- 스텝 인디케이터 -->
            <div class="psc-rp-steps">
                <div class="psc-rp-step done">
                    <div class="psc-rp-step__dot">✓</div>
                    <div class="psc-rp-step__label">이메일 입력</div>
                </div>
                <div class="psc-rp-step__line done"></div>
                <div class="psc-rp-step active">
                    <div class="psc-rp-step__dot">2</div>
                    <div class="psc-rp-step__label">비밀번호 설정</div>
                </div>
                <div class="psc-rp-step__line"></div>
                <div class="psc-rp-step">
                    <div class="psc-rp-step__dot">3</div>
                    <div class="psc-rp-step__label">완료</div>
                </div>
            </div>

            <div class="psc-rp-header">
                <div class="psc-rp-header__title">새 비밀번호 설정</div>
                <div class="psc-rp-header__desc">새로 사용할 비밀번호를 입력해주세요.</div>
            </div>

            <?php if ( $error ) : ?>
                <div class="psc-rp-notice psc-rp-notice--error">
                    <?php echo esc_html( $error ); ?>
                </div>
            <?php endif; ?>

            <form method="post" class="psc-rp-form" id="psc-reset-confirm-form">
                <?php wp_nonce_field( 'psc_reset_confirm_nonce' ); ?>
                <input type="hidden" name="uid"   value="<?php echo esc_attr( $uid );   ?>">
                <input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>">

                <div class="psc-rp-field">
                    <label class="psc-rp-field__label" for="new_pass">새 비밀번호</label>
                    <div class="psc-rp-input-wrap">
                        <input type="password"
                               id="new_pass"
                               name="new_pass"
                               class="psc-rp-input"
                               placeholder="8자 이상 입력"
                               required
                               autocomplete="new-password">
                        <button type="button" class="psc-rp-pwd-toggle"
                                onclick="pscRpToggle('new_pass', this)">👁️</button>
                    </div>
                    <!-- 강도 바 -->
                    <div class="psc-rp-strength" id="psc-rp-strength"></div>
                </div>

                <div class="psc-rp-field">
                    <label class="psc-rp-field__label" for="new_pass2">새 비밀번호 확인</label>
                    <div class="psc-rp-input-wrap">
                        <input type="password"
                               id="new_pass2"
                               name="new_pass2"
                               class="psc-rp-input"
                               placeholder="비밀번호를 한 번 더 입력"
                               required
                               autocomplete="new-password">
                        <button type="button" class="psc-rp-pwd-toggle"
                                onclick="pscRpToggle('new_pass2', this)">👁️</button>
                    </div>
                    <p class="psc-rp-hint" id="psc-rp-match"></p>
                </div>

                <button type="submit" name="psc_reset_confirm" class="psc-rp-btn">
                    비밀번호 변경
                </button>
            </form>
        </div>
    </div>

    <script>
    (function(){
        function pscRpToggle(id, btn) {
            var inp = document.getElementById(id);
            if (!inp) return;
            inp.type = inp.type === 'password' ? 'text' : 'password';
            btn.textContent = inp.type === 'password' ? '👁️' : '🙈';
        }
        window.pscRpToggle = pscRpToggle;

        // 비밀번호 강도
        document.getElementById('new_pass').addEventListener('input', function(){
            var v  = this.value;
            var el = document.getElementById('psc-rp-strength');
            if (!v) { el.innerHTML = ''; return; }
            var score = 0;
            if (v.length >= 8)           score++;
            if (/[A-Z]/.test(v))         score++;
            if (/[0-9]/.test(v))         score++;
            if (/[^A-Za-z0-9]/.test(v))  score++;
            var labels = ['', '약함', '보통', '강함', '매우 강함'];
            var colors = ['', '#ef4444', '#f59e0b', '#3b82f6', '#16a34a'];
            el.innerHTML =
                '<div style="height:4px;border-radius:2px;background:#f0f0f0;margin-bottom:4px">'
              + '<div style="height:4px;border-radius:2px;width:'+(score*25)+'%;background:'+colors[score]+';transition:width .3s"></div>'
              + '</div>'
              + '<span style="font-size:.75rem;color:'+colors[score]+'">'+labels[score]+'</span>';
            checkMatch();
        });

        // 비밀번호 일치 확인
        function checkMatch() {
            var p1  = document.getElementById('new_pass').value;
            var p2  = document.getElementById('new_pass2').value;
            var el  = document.getElementById('psc-rp-match');
            if (!p2) { el.textContent = ''; return; }
            if (p1 === p2) {
                el.textContent = '✅ 비밀번호가 일치합니다.';
                el.className   = 'psc-rp-hint success';
            } else {
                el.textContent = '❌ 비밀번호가 일치하지 않습니다.';
                el.className   = 'psc-rp-hint error';
            }
        }
        document.getElementById('new_pass').addEventListener('input',  checkMatch);
        document.getElementById('new_pass2').addEventListener('input', checkMatch);
    })();
    </script>
    <?php
    psc_reset_render_footer();
}

/* ============================================================
   STEP 3 — 완료
   ============================================================ */
function psc_reset_complete_page(): void {
    $d = psc_register_get_design();
    psc_reset_render_header( $d );
    ?>
    <div class="psc-rp-wrap">
        <div class="psc-rp-card">

            <!-- 로고 -->
            <div class="psc-rp-logo">
                <?php if ( $d['logo_url'] ) : ?>
                    <img src="<?php echo esc_url( $d['logo_url'] ); ?>" alt="로고">
                <?php else : ?>
                    <span><?php echo esc_html( $d['logo_text'] ); ?></span>
                <?php endif; ?>
            </div>

            <!-- 스텝 인디케이터 -->
            <div class="psc-rp-steps">
                <div class="psc-rp-step done">
                    <div class="psc-rp-step__dot">✓</div>
                    <div class="psc-rp-step__label">이메일 입력</div>
                </div>
                <div class="psc-rp-step__line done"></div>
                <div class="psc-rp-step done">
                    <div class="psc-rp-step__dot">✓</div>
                    <div class="psc-rp-step__label">비밀번호 설정</div>
                </div>
                <div class="psc-rp-step__line done"></div>
                <div class="psc-rp-step active">
                    <div class="psc-rp-step__dot">3</div>
                    <div class="psc-rp-step__label">완료</div>
                </div>
            </div>

            <!-- 완료 상태 -->
            <div class="psc-rp-result">
                <div class="psc-rp-result__icon">🎉</div>
                <div class="psc-rp-result__title">비밀번호 변경 완료!</div>
                <div class="psc-rp-result__desc">
                    새 비밀번호로 로그인해주세요.
                </div>
            </div>

            <a href="<?php echo esc_url( home_url( '/login/' ) ); ?>"
               class="psc-rp-btn">
                로그인하기
            </a>
        </div>
    </div>
    <?php
    psc_reset_render_footer();
}

/* ============================================================
   유효하지 않은 링크
   ============================================================ */
function psc_reset_invalid_link(): void {
    $d = psc_register_get_design();
    psc_reset_render_header( $d );
    ?>
    <div class="psc-rp-wrap">
        <div class="psc-rp-card">

            <div class="psc-rp-logo">
                <?php if ( $d['logo_url'] ) : ?>
                    <img src="<?php echo esc_url( $d['logo_url'] ); ?>" alt="로고">
                <?php else : ?>
                    <span><?php echo esc_html( $d['logo_text'] ); ?></span>
                <?php endif; ?>
            </div>

            <div class="psc-rp-result">
                <div class="psc-rp-result__icon">⚠️</div>
                <div class="psc-rp-result__title">링크가 만료되었습니다</div>
                <div class="psc-rp-result__desc">
                    재설정 링크가 만료되었거나 유효하지 않습니다.<br>
                    다시 요청해주세요.
                </div>
            </div>

            <a href="<?php echo esc_url( home_url( '/reset-password/' ) ); ?>"
               class="psc-rp-btn">
                다시 요청하기
            </a>
        </div>
    </div>
    <?php
    psc_reset_render_footer();
}

/* ============================================================
   이메일 템플릿
   ============================================================ */
function psc_reset_email_template( string $name, string $reset_url ): string {
    $logo_text = psc_register_get_design()['logo_text'] ?? '🐾 펫스페이스';
    $primary   = '#224471';
    return '
    <!DOCTYPE html>
    <html lang="ko">
    <head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
    <body style="margin:0;padding:0;background:#f4f6f9;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',sans-serif;">
        <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f9;padding:40px 0;">
            <tr><td align="center">
                <table width="520" cellpadding="0" cellspacing="0"
                       style="background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.08);">
                    <tr><td style="background:linear-gradient(135deg,#2d5a9e,#224471);padding:28px 32px;text-align:center;">
                        <span style="color:#fff;font-size:1.3rem;font-weight:800;">' . esc_html( $logo_text ) . '</span>
                    </td></tr>
                    <tr><td style="padding:36px 32px;">
                        <p style="font-size:1rem;color:#191f28;margin:0 0 8px;">
                            안녕하세요, <strong>' . esc_html( $name ) . '</strong>님!
                        </p>
                        <p style="font-size:.93rem;color:#4e5968;margin:0 0 28px;line-height:1.6;">
                            비밀번호 재설정 요청이 접수되었습니다.<br>
                            아래 버튼을 클릭해 새 비밀번호를 설정해주세요.<br>
                            <small>링크는 <strong>1시간</strong> 동안만 유효합니다.</small>
                        </p>
                        <div style="text-align:center;margin:0 0 28px;">
                            <a href="' . esc_url( $reset_url ) . '"
                               style="display:inline-block;background:#224471;color:#fff;text-decoration:none;padding:14px 36px;border-radius:10px;font-size:1rem;font-weight:700;">
                                비밀번호 재설정하기
                            </a>
                        </div>
                        <p style="font-size:.8rem;color:#8b95a1;margin:0;line-height:1.6;">
                            버튼이 작동하지 않으면 아래 주소를 복사해 브라우저에 붙여넣기 하세요.<br>
                            <a href="' . esc_url( $reset_url ) . '"
                               style="color:#224471;word-break:break-all;">' . esc_url( $reset_url ) . '</a>
                        </p>
                    </td></tr>
                    <tr><td style="background:#f4f6f9;padding:20px 32px;text-align:center;">
                        <p style="font-size:.78rem;color:#8b95a1;margin:0;">
                            본인이 요청하지 않은 경우 이 메일을 무시하셔도 됩니다.
                        </p>
                    </td></tr>
                </table>
            </td></tr>
        </table>
    </body>
    </html>';
}

/* ============================================================
   공통 레이아웃 — 헤더
   ============================================================ */
function psc_reset_render_header( array $d ): void {
    $primary      = $d['primary_color'] ?? '#224471';
    $primary_dark = '#1a3459';
    ?>
    <!DOCTYPE html>
    <html lang="ko">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>비밀번호 찾기 — <?php bloginfo('name'); ?></title>
        <link rel="stylesheet"
              href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard/dist/web/static/pretendard.css">
        <?php wp_head(); ?>
           </head>
    <body>
    <?php
}

/* ============================================================
   공통 레이아웃 — 푸터
   ============================================================ */
function psc_reset_render_footer(): void {
    ?>
    <?php wp_footer(); ?>
    </body>
    </html>
    <?php
    exit;
}
