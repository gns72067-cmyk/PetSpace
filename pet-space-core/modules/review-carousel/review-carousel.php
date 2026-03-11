<?php
defined( 'ABSPATH' ) || exit;

function psc_shortcode_store_reviews_carousel( array $atts ): string {
    $atts = shortcode_atts(
        [
            'id'      => get_the_ID(),
            'limit'   => 6,
            'orderby' => 'latest',
        ],
        $atts,
        'store_reviews_carousel'
    );

    $store_id = (int) $atts['id'];
    $limit    = max( 1, (int) $atts['limit'] );
    $orderby  = $atts['orderby'] === 'likes' ? 'likes' : 'latest';

    if ( ! $store_id ) {
        return '';
    }

    $summary = psc_get_review_summary( $store_id );
    if ( empty( $summary->total ) ) {
        return '';
    }

    $reviews = psc_get_reviews( $store_id, $orderby, $limit );
    if ( empty( $reviews ) ) {
        return '';
    }

    $review_list_url = psc_review_list_url( $store_id );
    $tag_map         = array_column( psc_get_review_tags( $store_id ), null, 'id' );

    ob_start();
    ?>
    <div class="psc-rv-carousel" data-store-id="<?php echo esc_attr( (string) $store_id ); ?>">
        <div class="psc-rv-carousel__track">
            <?php foreach ( $reviews as $review ) :
                $images       = psc_get_review_images( (int) $review->id );
                $thumbnail    = ! empty( $images ) && ! empty( $images[0]->url ) ? (string) $images[0]->url : '';
                $rating       = number_format_i18n( (float) $review->rating, 1 );
                $masked_name  = psc_mask_name( $review->display_name ?? '익명' );
                $date         = ! empty( $review->created_at ) ? date_i18n( 'Y.m.d', strtotime( $review->created_at ) ) : '';
                $preview      = wp_html_excerpt( wp_strip_all_tags( (string) $review->content ), 60, '...' );
                $selected_tag = '';
                $tag_ids      = $review->tags ? json_decode( (string) $review->tags, true ) : [];

                if ( is_array( $tag_ids ) && ! empty( $tag_ids ) ) {
                    $first_tag_id = (string) reset( $tag_ids );
                    if ( isset( $tag_map[ $first_tag_id ]['label'] ) ) {
                        $selected_tag = (string) $tag_map[ $first_tag_id ]['label'];
                    }
                }
                ?>
                <div class="psc-rv-carousel__card">
                    <?php if ( $thumbnail !== '' ) : ?>
                        <div class="psc-rv-carousel__img">
                            <img src="<?php echo esc_url( $thumbnail ); ?>" alt="" loading="lazy">
                        </div>
                    <?php endif; ?>

                    <div class="psc-rv-carousel__meta">
                        <span class="psc-rv-carousel__rating" aria-label="<?php echo esc_attr( $masked_name . ' 리뷰 평점 ' . $rating ); ?>">
                            ★ <?php echo esc_html( $rating ); ?>
                        </span>
                        <?php if ( $selected_tag !== '' ) : ?>
                            <span class="psc-rv-carousel__tag"><?php echo esc_html( $selected_tag ); ?></span>
                        <?php endif; ?>
                    </div>

                    <p class="psc-rv-carousel__text"><?php echo esc_html( $preview ); ?></p>
                    <time class="psc-rv-carousel__date" datetime="<?php echo esc_attr( ! empty( $review->created_at ) ? gmdate( 'c', strtotime( $review->created_at ) ) : '' ); ?>">
                        <?php echo esc_html( $date ); ?>
                    </time>
                </div>
            <?php endforeach; ?>
        </div>

        <a href="<?php echo esc_url( $review_list_url ); ?>" class="psc-rv-carousel__more">리뷰 전체보기 →</a>
    </div>
    <?php
    return ob_get_clean();
}

add_shortcode( 'store_reviews_carousel', 'psc_shortcode_store_reviews_carousel' );
