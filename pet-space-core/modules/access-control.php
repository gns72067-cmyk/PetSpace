<?php
/**
 * Module: Access Control v1
 * ══════════════════════════════════════════════════════════════
 * 모든 모듈(공지/쿠폰/찜/근처추천 등)이 공통으로 사용하는
 * 계정 룰(권한/소유권/플랜) 코어 고정 모듈.
 *
 * 제공 함수 목록:
 *   psc_is_vendor()
 *   psc_vendor_store_ids()
 *   psc_store_owner_id()
 *   psc_store_is_premium()
 *   psc_require_vendor_or_admin()
 *   psc_require_store_owner()
 *   psc_current_store_id()
 *   psc_get_current_vendor_store_id()
 *   psc_restrict_cpt_to_vendor_owned_store()
 *
 * ──────────────────────────────────────────────────────────────
 * 다른 모듈에서 사용 예시:
 *
 *   // 1) vendor 여부 확인
 *   if ( psc_is_vendor( get_current_user_id() ) ) { ... }
 *
 *   // 2) vendor 소유 매장 ID 배열
 *   $store_ids = psc_vendor_store_ids( get_current_user_id() );
 *
 *   // 3) 프리미엄 플랜 확인
 *   if ( psc_store_is_premium( $store_id ) ) { ... }
 *
 *   // 4) store 소유자 검증 (WP_Error 반환)
 *   $check = psc_require_store_owner( $store_id );
 *   if ( is_wp_error( $check ) ) return $check;
 *
 *   // 5) CPT 소유권 기반 접근 제한 등록
 *   psc_restrict_cpt_to_vendor_owned_store( 'store_notice', 'notice_store_id' );
 * ══════════════════════════════════════════════════════════════
 */

defined( 'ABSPATH' ) || exit;

/* ════════════════════════════════════════════════════════════
   0. 상수
════════════════════════════════════════════════════════════ */

if ( ! defined( 'PSC_VENDOR_ROLE' ) ) {
    define( 'PSC_VENDOR_ROLE', 'vendor' );
}
if ( ! defined( 'PSC_STORE_CPT' ) ) {
    define( 'PSC_STORE_CPT', 'store' );
}
if ( ! defined( 'PSC_STORE_PLAN_META' ) ) {
    define( 'PSC_STORE_PLAN_META', 'store_plan' );
}

/* ════════════════════════════════════════════════════════════
   1. 역할 판별
════════════════════════════════════════════════════════════ */

/**
 * 지정 유저가 vendor 역할인지 확인.
 *
 * @param int $user_id  확인할 유저 ID (0 이면 현재 유저)
 * @return bool
 */
function psc_is_vendor( int $user_id = 0 ): bool {
    $user_id = $user_id ?: get_current_user_id();
    if ( ! $user_id ) return false;

    $user = get_userdata( $user_id );
    if ( ! $user ) return false;

    return in_array( PSC_VENDOR_ROLE, (array) $user->roles, true );
}

/* ════════════════════════════════════════════════════════════
   2. 소유권 조회
════════════════════════════════════════════════════════════ */

/**
 * vendor가 소유한 store ID 배열 반환.
 * post_author 기준, publish|pending|draft 포함.
 *
 * @param int $vendor_id  vendor 유저 ID
 * @return int[]          store post ID 배열 (없으면 빈 배열)
 */
function psc_vendor_store_ids( int $vendor_id ): array {
    if ( ! $vendor_id ) return [];

    $ids = get_posts( [
        'post_type'      => PSC_STORE_CPT,
        'post_status'    => [ 'publish', 'pending', 'draft' ],
        'author'         => $vendor_id,
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
    ] );

    return array_map( 'intval', $ids );
}

/**
 * store의 post_author(소유자 유저 ID) 반환.
 *
 * @param int $store_id
 * @return int  소유자 유저 ID (없으면 0)
 */
function psc_store_owner_id( int $store_id ): int {
    $post = get_post( $store_id );
    if ( ! $post || $post->post_type !== PSC_STORE_CPT ) return 0;
    return (int) $post->post_author;
}

/* ════════════════════════════════════════════════════════════
   3. 플랜 판별
════════════════════════════════════════════════════════════ */

/**
 * store_plan == 'premium' 여부 반환.
 * ACF get_field() 우선, 없으면 get_post_meta() fallback.
 *
 * @param int $store_id
 * @return bool
 */
function psc_store_is_premium( int $store_id ): bool {
    if ( ! $store_id ) return false;

    // ACF 우선
    if ( function_exists( 'get_field' ) ) {
        $plan = get_field( PSC_STORE_PLAN_META, $store_id );
    } else {
        $plan = get_post_meta( $store_id, PSC_STORE_PLAN_META, true );
    }

    return ( (string) $plan === 'premium' );
}

/* ════════════════════════════════════════════════════════════
   4. 접근 요구 (Guard 함수)
════════════════════════════════════════════════════════════ */

/**
 * 현재 유저가 vendor 또는 admin이어야 통과.
 * 실패 시 WP_Error 반환.
 *
 * @return true|WP_Error
 */
function psc_require_vendor_or_admin(): true|WP_Error {
    $user_id = get_current_user_id();

    if ( ! $user_id ) {
        return new WP_Error(
            'psc_not_logged_in',
            '로그인이 필요합니다.',
            [ 'status' => 401 ]
        );
    }

    if ( user_can( $user_id, 'manage_options' ) || psc_is_vendor( $user_id ) ) {
        return true;
    }

    return new WP_Error(
        'psc_forbidden',
        '접근 권한이 없습니다.',
        [ 'status' => 403 ]
    );
}

/**
 * 현재 유저가 해당 store의 admin이거나 owner여야 통과.
 * 실패 시 WP_Error 반환.
 *
 * @param int $store_id
 * @return true|WP_Error
 */
function psc_require_store_owner( int $store_id ): true|WP_Error {
    $user_id = get_current_user_id();

    if ( ! $user_id ) {
        return new WP_Error(
            'psc_not_logged_in',
            '로그인이 필요합니다.',
            [ 'status' => 401 ]
        );
    }

    // admin 통과
    if ( user_can( $user_id, 'manage_options' ) ) {
        return true;
    }

    // store 소유자 확인
    if ( psc_store_owner_id( $store_id ) === $user_id ) {
        return true;
    }

    return new WP_Error(
        'psc_not_store_owner',
        '해당 매장의 소유자가 아닙니다.',
        [ 'status' => 403 ]
    );
}

/* ════════════════════════════════════════════════════════════
   5. 현재 페이지 store ID
════════════════════════════════════════════════════════════ */

/**
 * singular('store') 페이지에서 현재 store ID 반환.
 * 아니면 0 반환.
 *
 * @return int
 */
function psc_current_store_id(): int {
    if ( is_singular( PSC_STORE_CPT ) ) {
        return (int) get_queried_object_id();
    }
    return 0;
}

/* ════════════════════════════════════════════════════════════
   6. 다매장 대응: vendor 현재 store ID 반환
════════════════════════════════════════════════════════════ */

/**
 * vendor의 현재 활성 store ID 반환.
 *
 * 로직:
 *   1) vendor 소유 store가 1개  → 그 ID
 *   2) 2개 이상 + GET[store_id]가 vendor 소유 → GET 값
 *   3) 그 외 → 0
 *
 * 향후 vendor 대시보드 store 선택에 사용.
 *
 * @param int $vendor_id  0 이면 현재 유저
 * @return int
 */
function psc_get_current_vendor_store_id( int $vendor_id = 0 ): int {
    $vendor_id = $vendor_id ?: get_current_user_id();
    if ( ! $vendor_id ) return 0;

    $store_ids = psc_vendor_store_ids( $vendor_id );

    if ( empty( $store_ids ) ) return 0;

    // 1개면 바로 반환
    if ( count( $store_ids ) === 1 ) {
        return $store_ids[0];
    }

    // 2개 이상: GET 파라미터 확인
    if ( isset( $_GET['store_id'] ) ) {
        $requested = absint( $_GET['store_id'] );
        if ( in_array( $requested, $store_ids, true ) ) {
            return $requested;
        }
    }

    return 0;
}

/* ════════════════════════════════════════════════════════════
   7. CPT 소유권 기반 접근 제한 (재사용 훅 등록)
════════════════════════════════════════════════════════════ */

/**
 * 특정 CPT에 대해 vendor가 자기 store에 연결된 글만
 * 보고/편집/삭제할 수 있도록 제한한다.
 *
 * 사용 예:
 *   psc_restrict_cpt_to_vendor_owned_store( 'store_notice', 'notice_store_id' );
 *
 * @param string $post_type       제한할 CPT slug
 * @param string $store_id_meta_key  CPT 글에서 store ID를 담은 메타 키
 */
function psc_restrict_cpt_to_vendor_owned_store(
    string $post_type,
    string $store_id_meta_key
): void {

    /* ── 7-1. admin 목록 쿼리 제한 ── */
    add_action(
        'pre_get_posts',
        function ( WP_Query $query ) use ( $post_type, $store_id_meta_key ): void {

            if ( ! is_admin() )                              return;
            if ( ! $query->is_main_query() )                return;
            if ( $query->get( 'post_type' ) !== $post_type ) return;
            if ( current_user_can( 'manage_options' ) )     return; // admin 제외

            $user_id   = get_current_user_id();
            $store_ids = psc_vendor_store_ids( $user_id );

            if ( empty( $store_ids ) ) {
                // 소유 매장 없으면 결과 없음
                $query->set( 'post__in', [ 0 ] );
                return;
            }

            // vendor 소유 store에 연결된 글만 표시
            $query->set( 'meta_query', [
                [
                    'key'     => $store_id_meta_key,
                    'value'   => $store_ids,
                    'compare' => 'IN',
                    'type'    => 'NUMERIC',
                ],
            ] );
        }
    );

    /* ── 7-2. edit_post / delete_post / read_post 권한 제한 ── */
    add_filter(
        'user_has_cap',
        function (
            array  $all_caps,
            array  $caps,
            array  $args,
            WP_User $user
        ) use ( $post_type, $store_id_meta_key ): array {

            // admin 제외
            if ( ! empty( $all_caps['manage_options'] ) ) return $all_caps;

            // 처리할 cap 목록
            $guarded_primitives = [ 'edit_post', 'delete_post', 'read_post' ];

            // $args[0] = 요청 cap, $args[2] = post_id
            $requested_cap = $args[0] ?? '';
            $post_id       = isset( $args[2] ) ? (int) $args[2] : 0;

            if ( ! in_array( $requested_cap, $guarded_primitives, true ) ) {
                return $all_caps;
            }
            if ( ! $post_id ) return $all_caps;

            $post = get_post( $post_id );
            if ( ! $post || $post->post_type !== $post_type ) return $all_caps;

            // 연결된 store_id 확인
            $linked_store_id = (int) get_post_meta( $post_id, $store_id_meta_key, true );
            if ( ! $linked_store_id ) {
                // store 미연결 글은 본인 작성자인지로 판단
                if ( (int) $post->post_author !== $user->ID ) {
                    foreach ( $caps as $cap ) {
                        $all_caps[ $cap ] = false;
                    }
                }
                return $all_caps;
            }

            // store 소유 여부 확인
            $owned = psc_vendor_store_ids( $user->ID );
            if ( ! in_array( $linked_store_id, $owned, true ) ) {
                foreach ( $caps as $cap ) {
                    $all_caps[ $cap ] = false;
                }
            }

            return $all_caps;
        },
        10, 4
    );
}
/* ════════════════════════════════════════════════════════════
   플러그인 활성화: 역할 정리 + 신규 역할 생성
════════════════════════════════════════════════════════════ */

register_activation_hook(
    WP_PLUGIN_DIR . '/pet-space-core/pet-space-core.php',
    'psc_setup_roles'
);

function psc_setup_roles(): void {
    psc_remove_default_roles();
    psc_create_vendor_role();
    psc_create_customer_role();
}

/**
 * 기본 역할 제거 (administrator 제외).
 */
function psc_remove_default_roles(): void {
    $remove = [
        'subscriber',   // 구독자
        'contributor',  // 기여자
        'author',       // 작성자
        'editor',       // 편집자
    ];

    foreach ( $remove as $role ) {
        if ( get_role( $role ) ) {
            remove_role( $role );
        }
    }
}

/**
 * 입점사(vendor) 역할 생성.
 */
function psc_create_vendor_role(): void {
    if ( get_role( 'vendor' ) ) return;

    add_role(
        'vendor',
        '입점사',
        [
            'read'                   => true,  // 관리자 대시보드 접근
            'upload_files'           => true,  // 미디어 업로드
            'edit_posts'             => false,
            'delete_posts'           => false,
            'publish_posts'          => false,
        ]
    );
}

/**
 * 일반회원(customer) 역할 생성.
 */
function psc_create_customer_role(): void {
    if ( get_role( 'customer' ) ) return;

    add_role(
        'customer',
        '일반회원',
        [
            'read' => true,  // 기본 읽기만
        ]
    );
}

/* ════════════════════════════════════════════════════════════
   플러그인 비활성화: 역할 제거
   (선택사항 — 비활성화 시 역할 정리가 필요하면 활성화)
════════════════════════════════════════════════════════════ */

register_deactivation_hook(
    WP_PLUGIN_DIR . '/pet-space-core/pet-space-core.php',
    'psc_cleanup_roles'
);

function psc_cleanup_roles(): void {
    remove_role( 'vendor' );
    remove_role( 'customer' );
}

