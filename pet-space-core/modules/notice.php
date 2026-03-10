<?php
/**
 * Module: Store Notice
 * ══════════════════════════════════════════════════════════════
 * - CPT: store_notice (public=false, show_ui=true)
 * - 메타: notice_store_id / is_pinned / notice_expire_date
 * - 권한: access-control.php 의 헬퍼/훅 재사용
 * - 플랜 제한: free → 활성 1개 / premium → 무제한 + pinned
 * - 숏코드: [psc_notice_body] / [store_notices_fallback]
 * ══════════════════════════════════════════════════════════════
 */

defined( 'ABSPATH' ) || exit;

/* ────────────────────────────────────────────────────────────
   0. 상수
──────────────────────────────────────────────────────────── */

if ( ! defined( 'PSC_NOTICE_CPT' ) ) {
    define( 'PSC_NOTICE_CPT', 'store_notice' );
}
if ( ! defined( 'PSC_NOTICE_STORE_META' ) ) {
    define( 'PSC_NOTICE_STORE_META', 'notice_store_id' );
}

/* ────────────────────────────────────────────────────────────
   1. CPT 등록
──────────────────────────────────────────────────────────── */

add_action( 'init', 'psc_register_notice_cpt' );

function psc_register_notice_cpt(): void {

    register_post_type( PSC_NOTICE_CPT, [
    'label'               => '매장 공지',
    'labels'              => [
        'name'               => '매장 공지',
        'singular_name'      => '공지',
        'add_new'            => '공지 작성',
        'add_new_item'       => '새 공지 작성',
        'edit_item'          => '공지 편집',
        'menu_name'          => '매장 공지',
    ],

    /* ★ 핵심 변경 포인트 */
    'public'              => true,              // ← false → true
    'publicly_queryable'  => true,              // ← true 유지
    'exclude_from_search' => true,              // ← 검색 결과엔 노출 안 함
    'has_archive'         => false,             // ← 아카이브 페이지 없음

    'show_ui'             => true,
    'show_in_menu'        => true,
    'show_in_rest'        => true,              // ← Elementor 필수
    'supports'            => [ 'title', 'editor', 'author' ],
    'menu_icon'           => 'dashicons-megaphone',
    'capability_type'     => 'post',
    'map_meta_cap'        => true,
    'rewrite'             => [ 'slug' => 'store-notice', 'with_front' => false ],
] );


}

/* ────────────────────────────────────────────────────────────
   2. vendor 권한 부여 + CPT 접근 제한
      ★ access-control.php 재사용 핵심 부분
──────────────────────────────────────────────────────────── */

add_action( 'init', 'psc_notice_setup_access', 30 );

function psc_notice_setup_access(): void {

    /* vendor 역할에 post 기본 cap 부여 (store_notice 편집용) */
    $vendor = get_role( PSC_VENDOR_ROLE );
    if ( $vendor ) {
        $caps = [
            'edit_posts',
            'edit_published_posts',
            'publish_posts',
            'delete_posts',
            'delete_published_posts',
            'read',
        ];
        foreach ( $caps as $cap ) {
            $vendor->add_cap( $cap );
        }
    }

    /*
     * ★ access-control.php 의 psc_restrict_cpt_to_vendor_owned_store() 재사용
     * vendor는 자기 소유 store에 연결된 공지만 목록/편집/삭제 가능
     */
    psc_restrict_cpt_to_vendor_owned_store(
        PSC_NOTICE_CPT,
        PSC_NOTICE_STORE_META
    );
}

/* ────────────────────────────────────────────────────────────
   3. 메타박스: notice_store_id / is_pinned / notice_expire_date
──────────────────────────────────────────────────────────── */

add_action( 'add_meta_boxes', 'psc_notice_add_meta_boxes' );

function psc_notice_add_meta_boxes(): void {
    add_meta_box(
        'psc_notice_settings',
        '공지 설정',
        'psc_notice_meta_box_render',
        PSC_NOTICE_CPT,
        'side',
        'high'
    );
}

function psc_notice_meta_box_render( WP_Post $post ): void {

    wp_nonce_field( 'psc_notice_save', 'psc_notice_nonce' );

    $user_id    = get_current_user_id();
    $is_admin   = current_user_can( 'manage_options' );

    /* 메타값 읽기: ACF 우선, fallback get_post_meta */
    $store_id  = (int) psc_notice_get_meta( $post->ID, PSC_NOTICE_STORE_META );
    $is_pinned = (bool) psc_notice_get_meta( $post->ID, 'is_pinned' );
    $expire    = (string) psc_notice_get_meta( $post->ID, 'notice_expire_date' );

    /*
     * ★ access-control.php 의 psc_vendor_store_ids() 재사용
     * vendor는 자기 소유 store만 선택 가능
     */
    if ( $is_admin ) {
        $store_ids = get_posts( [
            'post_type'      => PSC_STORE_CPT,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ] );
    } else {
        $store_ids = psc_vendor_store_ids( $user_id );
    }

    ?>
    <p>
        <label for="psc_notice_store_id">
            <strong>연결 매장 <span style="color:red">*</span></strong>
        </label><br>
        <select name="psc_notice_store_id"
                id="psc_notice_store_id"
                style="width:100%;margin-top:4px">
            <option value="">— 매장 선택 —</option>
            <?php foreach ( (array) $store_ids as $sid ) :
                $sid = (int) $sid;
                /* ★ psc_store_is_premium() 재사용: 플랜 표시 */
                $plan_label = psc_store_is_premium( $sid ) ? ' ★유료' : ' (무료)';
            ?>
                <option value="<?php echo esc_attr( $sid ); ?>"
                    <?php selected( $store_id, $sid ); ?>>
                    <?php echo esc_html( get_the_title( $sid ) . $plan_label ); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </p>

    <p>
        <label for="psc_notice_expire">
            <strong>만료일</strong>
            <span style="font-size:11px;color:#888">(선택, 비우면 상시)</span>
        </label><br>
        <input type="date"
               name="psc_notice_expire_date"
               id="psc_notice_expire"
               value="<?php echo esc_attr( $expire ); ?>"
               style="width:100%;margin-top:4px">
    </p>

    <p>
        <label style="display:flex;align-items:center;gap:6px;cursor:pointer">
            <input type="checkbox"
                   name="psc_is_pinned"
                   value="1"
                   <?php checked( $is_pinned ); ?>>
            <strong>상단 고정 (유료 플랜 전용)</strong>
        </label>
        <span style="font-size:11px;color:#aaa;display:block;margin-top:2px">
            무료 매장은 저장 시 자동으로 해제됩니다.
        </span>
    </p>
    <?php
}

/* ────────────────────────────────────────────────────────────
   4. 메타 저장 + 플랜 제한 로직
──────────────────────────────────────────────────────────── */

add_action( 'save_post_' . PSC_NOTICE_CPT, 'psc_notice_save_meta', 20, 2 );

function psc_notice_save_meta( int $post_id, WP_Post $post ): void {

    /* 기본 검증 */
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( wp_is_post_revision( $post_id ) )                return;
    if ( ! isset( $_POST['psc_notice_nonce'] ) )          return;
    if ( ! wp_verify_nonce(
        sanitize_text_field( wp_unslash( $_POST['psc_notice_nonce'] ) ),
        'psc_notice_save'
    ) ) return;

    $user_id  = get_current_user_id();
    $is_admin = current_user_can( 'manage_options' );

    /* ── store_id 파싱 ── */
    $new_store_id = isset( $_POST['psc_notice_store_id'] )
        ? absint( $_POST['psc_notice_store_id'] )
        : 0;

    /* ── store 미선택 ── */
    if ( ! $new_store_id ) {
        psc_notice_set_msg( 'error', '연결할 매장을 선택해야 합니다.' );
        psc_notice_force_draft( $post_id );
        return;
    }

    /*
     * ★ access-control.php 의 psc_require_store_owner() 재사용
     * 타인 매장 선택 시 저장 거부
     */
    if ( ! $is_admin ) {
        $ownership = psc_require_store_owner( $new_store_id );
        if ( is_wp_error( $ownership ) ) {
            psc_notice_set_msg( 'error', $ownership->get_error_message() );
            psc_notice_force_draft( $post_id );
            return;
        }
    }

    /*
     * ★ access-control.php 의 psc_store_is_premium() 재사용
     */
    $is_premium = psc_store_is_premium( $new_store_id );

    /* ── is_pinned: 무료면 강제 false ── */
    $is_pinned = ( ! empty( $_POST['psc_is_pinned'] ) && $is_premium ) ? 1 : 0;

    /* ── 만료일 파싱 ── */
    $expire_raw = isset( $_POST['psc_notice_expire_date'] )
        ? sanitize_text_field( wp_unslash( $_POST['psc_notice_expire_date'] ) )
        : '';
    $expire = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $expire_raw )
        ? $expire_raw : '';

    /* ── 무료 플랜: 활성 공지 1개 제한 ── */
    if ( ! $is_premium && $post->post_status === 'publish' ) {
        $active = psc_notice_count_active( $new_store_id, $post_id );
        if ( $active >= 1 ) {
            psc_notice_set_msg(
                'warning',
                sprintf(
                    '"%s" 매장은 무료 플랜으로 활성 공지를 1개만 유지할 수 있습니다. 임시저장으로 전환되었습니다.',
                    esc_html( get_the_title( $new_store_id ) )
                )
            );
            psc_notice_force_draft( $post_id );
            /* 메타는 계속 저장 (draft 상태로 보존) */
        }
    }

    /* ── 메타 저장 ── */
    update_post_meta( $post_id, PSC_NOTICE_STORE_META, $new_store_id );
    update_post_meta( $post_id, 'is_pinned',           $is_pinned );
    update_post_meta( $post_id, 'notice_expire_date',  $expire );
}

/* ────────────────────────────────────────────────────────────
   5. 헬퍼 함수
──────────────────────────────────────────────────────────── */

/**
 * 메타값 읽기: ACF get_field() 우선, fallback get_post_meta.
 */
function psc_notice_get_meta( int $post_id, string $key ): mixed {
    if ( function_exists( 'get_field' ) ) {
        $val = get_field( $key, $post_id );
        if ( $val !== null && $val !== false && $val !== '' ) {
            return $val;
        }
    }
    return get_post_meta( $post_id, $key, true );
}

/**
 * 활성 공지 수 반환 (자신 제외).
 * 활성 기준: publish + (만료일 없거나 오늘 이후)
 */
function psc_notice_count_active( int $store_id, int $exclude_id = 0 ): int {
    $today = gmdate( 'Y-m-d' );
    $args  = [
        'post_type'      => PSC_NOTICE_CPT,
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'no_found_rows'  => false,
        'meta_query'     => [
            'relation' => 'AND',
            [
                'key'   => PSC_NOTICE_STORE_META,
                'value' => $store_id,
                'type'  => 'NUMERIC',
            ],
            [
                'relation' => 'OR',
                [ 'key' => 'notice_expire_date', 'compare' => 'NOT EXISTS' ],
                [ 'key' => 'notice_expire_date', 'value'   => '', 'compare' => '=' ],
                [
                    'key'     => 'notice_expire_date',
                    'value'   => $today,
                    'compare' => '>=',
                    'type'    => 'DATE',
                ],
            ],
        ],
    ];
    if ( $exclude_id ) {
        $args['post__not_in'] = [ $exclude_id ];
    }
    $q = new WP_Query( $args );
    return (int) $q->found_posts;
}

/**
 * 강제 draft 전환 (무한루프 방지).
 */
function psc_notice_force_draft( int $post_id ): void {
    remove_action( 'save_post_' . PSC_NOTICE_CPT, 'psc_notice_save_meta', 20 );
    wp_update_post( [ 'ID' => $post_id, 'post_status' => 'draft' ] );
    add_action( 'save_post_' . PSC_NOTICE_CPT, 'psc_notice_save_meta', 20, 2 );
}

/**
 * 관리자 화면 알림 transient 저장.
 */
function psc_notice_set_msg( string $type, string $msg ): void {
    set_transient(
        'psc_notice_msg_' . get_current_user_id(),
        [ 'type' => $type, 'msg' => $msg ],
        60
    );
}

add_action( 'admin_notices', 'psc_notice_print_msg' );

function psc_notice_print_msg(): void {
    $uid  = get_current_user_id();
    $data = get_transient( 'psc_notice_msg_' . $uid );
    if ( ! $data ) return;
    delete_transient( 'psc_notice_msg_' . $uid );

    $type = in_array( $data['type'], [ 'error', 'warning', 'success', 'info' ], true )
        ? $data['type'] : 'info';

    printf(
        '<div class="notice notice-%s is-dismissible"><p><strong>[Pet Space]</strong> %s</p></div>',
        esc_attr( $type ),
        esc_html( $data['msg'] )
    );
}

/* ────────────────────────────────────────────────────────────
   6. 관리자 목록 컬럼
──────────────────────────────────────────────────────────── */

add_filter( 'manage_' . PSC_NOTICE_CPT . '_posts_columns',       'psc_notice_columns' );
add_action( 'manage_' . PSC_NOTICE_CPT . '_posts_custom_column', 'psc_notice_column_cb', 10, 2 );

function psc_notice_columns( array $cols ): array {
    $new = [];
    foreach ( $cols as $k => $v ) {
        $new[ $k ] = $v;
        if ( $k === 'title' ) {
            $new['col_store']  = '연결 매장';
            $new['col_plan']   = '플랜';
            $new['col_pinned'] = '고정';
            $new['col_expire'] = '만료일';
        }
    }
    return $new;
}

function psc_notice_column_cb( string $col, int $post_id ): void {
    $sid = (int) get_post_meta( $post_id, PSC_NOTICE_STORE_META, true );

    switch ( $col ) {
        case 'col_store':
            echo $sid
                ? esc_html( get_the_title( $sid ) )
                : '<span style="color:#bbb">—</span>';
            break;

        case 'col_plan':
            if ( ! $sid ) { echo '—'; break; }
            /* ★ psc_store_is_premium() 재사용 */
            echo psc_store_is_premium( $sid )
                ? '<span style="color:#e6a817;font-weight:700">★ 유료</span>'
                : '<span style="color:#aaa">무료</span>';
            break;

        case 'col_pinned':
            echo get_post_meta( $post_id, 'is_pinned', true )
                ? '📌'
                : '—';
            break;

        case 'col_expire':
            $exp = get_post_meta( $post_id, 'notice_expire_date', true );
            if ( ! $exp ) { echo '<span style="color:#aaa">상시</span>'; break; }
            $color = ( $exp < gmdate( 'Y-m-d' ) ) ? 'red' : '#333';
            printf(
                '<span style="color:%s">%s</span>',
                esc_attr( $color ),
                esc_html( $exp )
            );
            break;
    }
}

/* ────────────────────────────────────────────────────────────
   7. 숏코드: [psc_notice_body]
   Elementor Popup / Off-Canvas 템플릿 내부용
──────────────────────────────────────────────────────────── */

add_shortcode( 'psc_notice_body', 'psc_sc_notice_body' );

function psc_sc_notice_body( array $atts ): string {
    $atts    = shortcode_atts( [ 'id' => get_the_ID() ], $atts, 'psc_notice_body' );
    $post_id = (int) $atts['id'];
    if ( ! $post_id ) return '';

    $post = get_post( $post_id );
    if ( ! $post || $post->post_type !== PSC_NOTICE_CPT ) return '';

    /* 만료 체크 */
    $expire = get_post_meta( $post_id, 'notice_expire_date', true );
    if ( $expire && $expire < gmdate( 'Y-m-d' ) ) return '';

    $title   = get_the_title( $post );
    $date    = get_the_date( 'Y년 m월 d일', $post );
    $pinned  = (bool) get_post_meta( $post_id, 'is_pinned', true );
    $content = apply_filters( 'the_content', $post->post_content );

    ob_start();
    ?>
    <div class="psc-notice-body" data-notice-id="<?php echo esc_attr( $post_id ); ?>">
        <div class="psc-notice-body__header">
            <?php if ( $pinned ) : ?>
                <span class="psc-notice-pin-badge">📌 고정</span>
            <?php endif; ?>
            <h2 class="psc-notice-body__title">
                <?php echo esc_html( $title ); ?>
            </h2>
            <p class="psc-notice-body__date">
                <?php echo esc_html( $date ); ?>
            </p>
        </div>
        <div class="psc-notice-body__content">
            <?php echo wp_kses_post( $content ); ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/* ────────────────────────────────────────────────────────────
   8. 숏코드: [store_notices_fallback]
   Loop Grid 미사용 시 간단 리스트
──────────────────────────────────────────────────────────── */

add_shortcode( 'store_notices_fallback', 'psc_sc_notices_fallback' );

function psc_sc_notices_fallback( array $atts ): string {
    $atts = shortcode_atts(
        [
            'store_id' => psc_current_store_id(), // ★ access-control 재사용
            'limit'    => 5,
        ],
        $atts,
        'store_notices_fallback'
    );

    $store_id = (int) $atts['store_id'];
    if ( ! $store_id ) return '';

    /* ★ psc_store_is_premium() 재사용 */
    $is_premium = psc_store_is_premium( $store_id );
    $today      = gmdate( 'Y-m-d' );

    $args = [
        'post_type'      => PSC_NOTICE_CPT,
        'post_status'    => 'publish',
        'posts_per_page' => $is_premium ? (int) $atts['limit'] : 1,
        'meta_key'       => 'is_pinned',
        'orderby'        => $is_premium
            ? [ 'meta_value_num' => 'DESC', 'date' => 'DESC' ]
            : [ 'date' => 'DESC' ],
        'meta_query'     => [
            'relation' => 'AND',
            [
                'key'   => PSC_NOTICE_STORE_META,
                'value' => $store_id,
                'type'  => 'NUMERIC',
            ],
            [
                'relation' => 'OR',
                [ 'key' => 'notice_expire_date', 'compare' => 'NOT EXISTS' ],
                [ 'key' => 'notice_expire_date', 'value'   => '', 'compare' => '=' ],
                [
                    'key'     => 'notice_expire_date',
                    'value'   => $today,
                    'compare' => '>=',
                    'type'    => 'DATE',
                ],
            ],
        ],
    ];

    $q = new WP_Query( $args );
    if ( ! $q->have_posts() ) {
        return '<p class="psc-no-notices">등록된 공지가 없습니다.</p>';
    }

    ob_start();
    ?>
    <ul class="psc-notices-fallback">
    <?php while ( $q->have_posts() ) : $q->the_post();
        $nid    = get_the_ID();
        $pinned = (bool) get_post_meta( $nid, 'is_pinned', true );
    ?>
        <li class="psc-notices-fallback__item<?php echo $pinned ? ' is-pinned' : ''; ?>"
            data-notice-id="<?php echo esc_attr( $nid ); ?>">
            <span class="psc-notices-fallback__icon">📢</span>
            <?php if ( $pinned ) : ?>
                <span class="psc-notice-pin-badge">📌</span>
            <?php endif; ?>
            <span class="psc-notices-fallback__title">
                <?php the_title(); ?>
            </span>
            <span class="psc-notices-fallback__date">
                <?php echo esc_html( get_the_date( 'Y.m.d' ) ); ?>
            </span>
        </li>
    <?php endwhile; wp_reset_postdata(); ?>
    </ul>
    <?php
    return ob_get_clean();
}
