---
# PetSpace — Claude 작업 가이드

## 레포지토리 구조
\```
PetSpace/
└── pet-space-core/          # WordPress 플러그인 루트
    ├── pet-space-core.php   # 메인 부트스트랩 파일
    └── modules/
        ├── auth/            # 로그인 / 회원가입 / 비밀번호 재설정
        ├── mypage/          # 마이페이지
        ├── pets/            # 반려견 / 예방접종 수첩
        └── review/          # 리뷰 시스템
\```

## 모듈 파일 구조 규칙
각 모듈은 반드시 아래 구조를 따른다:
\```
modules/{name}/
├── {name}.php          # 메인 로직
├── assets.php          # wp_enqueue_style 등록
└── assets/
    └── css/
        └── {name}.css  # 외부 CSS 파일
\```

## PHP 작업 규칙
- `psc_require()` 헬퍼로 파일을 로드한다 (직접 `require` 금지).
- 새 모듈 추가 시 `pet-space-core.php`에 `assets.php`를 먼저 require한 뒤 메인 파일을 require한다.
- 작업 후 반드시 `php -l`로 문법 검사한다.
- 작업 완료 시 수정/생성된 파일 목록을 반드시 출력한다.

## CSS 작업 규칙

### 규칙 1 — 인라인 CSS 절대 금지
PHP 파일 안에 `<style>` 태그로 CSS를 작성하지 않는다.
반드시 `assets/css/` 폴더의 외부 파일로 분리한다.

### 규칙 2 — 셀렉터 specificity (가장 중요)
태그 셀렉터는 반드시 클래스와 결합하여 전역 충돌을 방지한다.

\```css
/* ❌ 금지 — 전역 충돌 위험 */
.wrap input { }
.wrap select { }
input[type="text"] { }

/* ✅ 올바른 방식 — 태그명.클래스명 */
input.psc-mp-input { }
select.psc-mp-input { }
textarea.psc-rv-textarea { }
button.psc-rv-submit-btn { }
\```

### 규칙 3 — 테마 CSS 충돌 방지
WordPress 테마가 `button`, `input`, `select`, `a` 태그에
전역 스타일을 적용하는 경우가 많다.
특히 `button` 태그는 테마가 border-color, background, color,
outline, box-shadow를 덮어씌울 수 있으므로
핵심 스타일에는 `!important`를 사용한다.

\```css
/* ✅ 테마 충돌 방지 패턴 */
button.psc-rv-verify-tab {
    border: 2px solid var(--rv-border) !important;
    background: var(--rv-card) !important;
    color: var(--rv-text1) !important;
    outline: none !important;
    box-shadow: none !important;
}
button.psc-rv-submit-btn {
    background: var(--rv-primary) !important;
    color: #fff !important;
    border: none !important;
    outline: none !important;
    box-shadow: none !important;
}
\```

### 규칙 4 — assets.php CSS 로드 조건
- **전용 커스텀 페이지 모듈** (auth, mypage, pets):
  쿼리 변수(`psc_auth`, `psc_mypage`)로 분기해서 해당 페이지에서만 로드한다.
- **숏코드 기반 모듈** (review 등):
  숏코드는 어느 페이지에든 삽입될 수 있으므로
  조건 없이 프론트엔드 전체에서 로드한다.

\```php
/* ✅ 전용 페이지 모듈 — 조건부 로드 */
add_action( 'wp_enqueue_scripts', function () {
    $page = get_query_var('psc_auth');
    if ( $page === 'login' ) {
        wp_enqueue_style( 'psc-login', ... );
    }
} );

/* ✅ 숏코드 모듈 — 조건 없이 전체 로드 */
add_action( 'wp_enqueue_scripts', function () {
    wp_enqueue_style( 'psc-review', ... );
} );
\```

### 규칙 5 — 디자인 토큰
CSS 변수로 정의하고, PHP에서 하드코딩이 필요한 경우 아래 값을 사용한다.

| 용도 | CSS 변수 | 하드코딩 값 |
|------|----------|------------|
| Primary | `--rv-primary` | `#224471` |
| Primary Dark | `--rv-primary-dk` | `#1a3459` |
| Primary Light | `--rv-primary-lt` | `#eef3fa` |
| Accent / Yellow | `--rv-yellow` | `#f59e0b` |
| Text 1 | `--rv-text1` | `#191f28` |
| Text 2 | `--rv-text2` | `#4e5968` |
| Text 3 | `--rv-text3` | `#8b95a1` |
| Border | `--rv-border` | `#e5e8eb` |
| Background | `--rv-bg` | `#f4f6f9` |
| Green | `--rv-green` | `#10b981` |
| Red | `--rv-red` | `#ef4444` |

### 규칙 6 — 폰트
\```css
font-family: "Pretendard", -apple-system, "Apple SD Gothic Neo", sans-serif;
\```

## 작업 완료 기준
모든 작업은 아래 조건을 만족해야 완료로 간주한다:
1. `php -l` 통과
2. 수정/생성된 파일 목록 출력
3. CSS는 외부 파일로 분리되어 있을 것
4. 셀렉터가 `태그명.클래스명` 형식일 것
5. `button` 태그 핵심 스타일에 `!important` 적용
6. 숏코드 기반 CSS는 조건 없이 전체 로드
---
