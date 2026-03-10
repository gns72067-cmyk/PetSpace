# Pet Space Core

펫스페이스 핵심 기능 WordPress 플러그인.  
`modules/` 폴더에 `.php` 파일을 추가하는 것만으로 기능을 확장할 수 있습니다.

---

## 설치

1. `/wp-content/plugins/pet-space-core/` 폴더를 통째로 업로드합니다.
2. WordPress 관리자 → **플러그인** → **Pet Space Core** 활성화.
3. ACF Pro가 설치·활성화되어 있어야 합니다.

---

## 요구 사항

| 항목 | 버전 |
|------|------|
| PHP  | 8.0 이상 |
| WordPress | 6.0 이상 |
| Advanced Custom Fields Pro | 5.x / 6.x |

---

## 숏코드 사용법

### `[store_region]` — 매장 지역 목록

| 속성 | 기본값 | 설명 |
|------|--------|------|
| `city` | (없음) | 시 이름으로 필터 (예: `성남시`) |
| `district` | (없음) | 구 이름으로 필터 (예: `분당구`) |
| `limit` | `100` | 최대 출력 개수 |

