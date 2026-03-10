<?php
/**
 * Module: Region
 * Shortcode: [store_region]
 *
 * 사용 예)
 *   [store_region]                      - 전체 리스트
 *   [store_region city="성남시"]         - 시 필터
 *   [store_region district="분당구"]     - 구 필터
 */

defined( 'ABSPATH' ) || exit;

/**
 * 지역 데이터 (경기도 성남시 분당구까지)
 * 구조: province → city → district[]
 */
function psc_region_data(): array {
    return [
        '서울특별시' => [
            '강남구'  => [],
            '강동구'  => [],
            '강북구'  => [],
            '강서구'  => [],
            '관악구'  => [],
            '광진구'  => [],
            '구로구'  => [],
            '금천구'  => [],
            '노원구'  => [],
            '도봉구'  => [],
            '동대문구' => [],
            '동작구'  => [],
            '마포구'  => [],
            '서대문구' => [],
            '서초구'  => [],
            '성동구'  => [],
            '성북구'  => [],
            '송파구'  => [],
            '양천구'  => [],
            '영등포구' => [],
            '용산구'  => [],
            '은평구'  => [],
            '종로구'  => [],
            '중구'   => [],
            '중랑구'  => [],
        ],
        '경기도' => [
            '성남시' => [
                '분당구',
                '수정구',
                '중원구',
            ],
            '수원시' => [
                '권선구',
                '영통구',
                '장안구',
                '팔달구',
            ],
            '용인시' => [
                '기흥구',
                '수지구',
                '처인구',
            ],
            '고양시' => [
                '덕양구',
                '일산동구',
                '일산서구',
            ],
            '안양시' => [
                '동안구',
                '만안구',
            ],
            '부천시'  => [],
            '평택시'  => [],
            '안산시' => [
                '단원구',
                '상록구',
            ],
            '시흥시'  => [],
            '화성시'  => [],
            '광명시'  => [],
            '광주시'  => [],
            '군포시'  => [],
            '김포시'  => [],
            '남양주시' => [],
            '의정부시' => [],
            '파주시'  => [],
            '하남시'  => [],
        ],
        '인천광역시' => [
            '계양구'  => [],
            '남동구'  => [],
            '동구'   => [],
            '미추홀구' => [],
            '부평구'  => [],
            '서구'   => [],
            '연수구'  => [],
            '중구'   => [],
        ],
    ];
}

/**
 * [store_region] 숏코드
 * store CPT의 store_address ACF 필드에서 지역 정보를 읽어 목록 표시.
 *
 * @param array $atts 숏코드 속성
 * @return string HTML
 */
function psc_shortcode_store_region( array $atts ): string {

    $atts = shortcode_atts(
        [
            'city'     => '',   // 예: 성남시
            'district' => '',   // 예: 분당구
            'limit'    => 100,
        ],
        $atts,
        'store_region'
    );

    /* ── 쿼리 ── */
    $args = [
        'post_type'      => 'store',
        'post_status'    => 'publish',
        'posts_per_page' => (int) $atts['limit'],
        'orderby'        => 'title',
        'order'          => 'ASC',
    ];

    /* city / district 필터 */
    $meta_query = [];
    if ( ! empty( $atts['city'] ) ) {
        $meta_query[] = [
            'key'     => 'store_address',
            'value'   => $atts['city'],
            'compare' => 'LIKE',
        ];
    }
    if ( ! empty( $atts['district'] ) ) {
        $meta_query[] = [
            'key'     => 'store_address',
            'value'   => $atts['district'],
            'compare' => 'LIKE',
        ];
    }
    if ( $meta_query ) {
        $args['meta_query'] = array_merge( [ 'relation' => 'AND' ], $meta_query );
    }

    $query  = new WP_Query( $args );
    $stores = $query->posts;

    /* ── 렌더 ── */
    ob_start(); ?>
    <div class="psc-region-wrap">

        <?php if ( empty( $stores ) ) : ?>
            <p class="psc-no-store">등록된 매장이 없습니다.</p>

        <?php else : ?>
            <ul class="psc-store-list">
            <?php foreach ( $stores as $store ) :
                $address = get_post_meta( $store->ID, 'store_address', true );
                $plan    = get_post_meta( $store->ID, 'store_plan',    true );
            ?>
                <li class="psc-store-item <?php echo esc_attr( 'plan-' . $plan ); ?>">
                    <span class="psc-store-name">
                        <?php echo esc_html( get_the_title( $store ) ); ?>
                    </span>
                    <?php if ( $address ) : ?>
                        <span class="psc-store-address">
                            <?php echo esc_html( $address ); ?>
                        </span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
            </ul>
        <?php endif; ?>

    </div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'store_region', 'psc_shortcode_store_region' );
