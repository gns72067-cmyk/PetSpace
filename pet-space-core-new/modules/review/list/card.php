<?php
/**
 * 리뷰 카드 단일 렌더러
 * psc_render_review_card( object $review ) : string
 */
defined('ABSPATH') || exit;

function psc_render_review_card(object $review): string {
    $user        = get_userdata($review->user_id);
    $name        = $user ? psc_mask_name($user->display_name) : '익명';
    $avatar      = get_avatar_url($review->user_id, ['size' => 38]);
    $stars       = psc_render_stars((int)$review->rating);
    $date        = date_i18n('Y.m.d', strtotime($review->created_at));
    $visited     = !empty($review->visited_at)
                   ? date_i18n('Y.m.d H:i', strtotime($review->visited_at))
                   : '';
    $tags        = !empty($review->tags) ? json_decode($review->tags, true) : [];
    $tag_labels  = psc_get_review_tags((int)$review->store_id);
    $images      = psc_get_review_images((int)$review->id);
    $current_uid = get_current_user_id();
    $liked       = $current_uid ? psc_user_liked_review((int)$review->id, $current_uid) : false;
    $can_delete  = $current_uid && ($current_uid === (int)$review->user_id || current_user_can('manage_options'));

    /* 본문 – 150자 초과 시 더보기 */
    $content_esc = esc_html($review->content);
    $show_more   = mb_strlen($review->content) > 150;
    $short_text  = $show_more ? esc_html(mb_substr($review->content, 0, 150)) . '…' : $content_esc;

    ob_start();
    ?>
    <div class="rv-card rv-anim-fade" data-review-id="<?= (int)$review->id ?>">

        <!-- 카드 헤더 -->
        <div class="rv-card__head">
            <div class="rv-card__user">
                <div class="rv-card__avatar">
                    <?php if ($avatar): ?>
                        <img src="<?= esc_url($avatar) ?>" alt="<?= esc_attr($name) ?>" width="38" height="38" style="border-radius:50%;object-fit:cover;">
                    <?php else: ?>
                        🐾
                    <?php endif; ?>
                </div>
                <div>
                    <div class="rv-card__name"><?= esc_html($name) ?></div>
                    <div class="rv-card__meta">
                        <span class="rv-stars rv-stars--sm"><?= $stars ?></span>
                        <span class="rv-card__date"><?= esc_html($date) ?></span>
                        <?php if ($review->verify_type === 'receipt' && $review->receipt_verified): ?>
                            <span class="rv-badge rv-badge--receipt">🧾 영수증 인증</span>
                        <?php elseif ($review->verify_type === 'gps'): ?>
                            <span class="rv-badge rv-badge--gps">📍 GPS 인증</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- 액션 버튼 -->
            <div class="rv-card__actions">
                <?php if ($can_delete): ?>
                    <button
                        class="rv-card__action-btn rv-card__action-btn--del"
                        data-review-id="<?= (int)$review->id ?>"
                        aria-label="리뷰 삭제"
                    >삭제</button>
                <?php endif; ?>
            </div>
        </div>

        <!-- 태그 -->
        <?php if (!empty($tags)): ?>
        <div class="rv-card__tags">
            <?php foreach ($tags as $tag):
                $label = $tag_labels[$tag] ?? $tag; ?>
                <span class="rv-card__tag"><?= esc_html($label) ?></span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- 본문 -->
        <div class="rv-card__content"
             style="<?= $show_more ? '-webkit-line-clamp:4;display:-webkit-box;-webkit-box-orient:vertical;overflow:hidden;' : '' ?>"
        ><?= $show_more ? $short_text : $content_esc ?></div>
        <?php if ($show_more): ?>
            <button class="rv-card__more">더보기</button>
        <?php endif; ?>

        <!-- 방문일 -->
        <?php if ($visited): ?>
            <p class="rv-card__visited">🗓 방문일: <?= esc_html($visited) ?></p>
        <?php endif; ?>

        <!-- 이미지 -->
        <?php if (!empty($images)): ?>
        <div class="rv-card__images">
            <?php foreach ($images as $img): ?>
                <img
                    class="rv-card__img"
                    src="<?= esc_url($img->url) ?>"
                    alt="리뷰 이미지"
                    loading="lazy"
                >
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- 카드 푸터 (좋아요) -->
        <div class="rv-card__footer">
            <button
                class="rv-like-btn <?= $liked ? 'rv-like-btn--active' : '' ?>"
                data-review-id="<?= (int)$review->id ?>"
                aria-label="좋아요"
                <?= !$current_uid ? 'data-require-login="1"' : '' ?>
            >
                <span class="rv-like-btn__icon"><?= $liked ? '❤️' : '🤍' ?></span>
                <span class="rv-like-btn__count"><?= (int)$review->likes ?></span>
            </button>
        </div>

    </div>
    <?php
    return ob_get_clean();
}
