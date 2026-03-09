<?php
/**
 * 리뷰 목록 렌더러
 * psc_render_review_list( int $store_id ) : void
 */
defined('ABSPATH') || exit;

function psc_render_review_list(int $store_id): void {
    $summary  = psc_get_review_summary($store_id);
    $reviews  = psc_get_reviews($store_id, 'latest', 10, 0);
    $total    = (int)($summary['total'] ?? 0);
    $avg      = round((float)($summary['avg_rating'] ?? 0), 1);
    $stars    = $summary['stars'] ?? [5=>0,4=>0,3=>0,2=>0,1=>0];
    $uid      = get_current_user_id();
    $can      = $uid ? psc_can_write_review($store_id, $uid) : false;
    ?>

    <!-- 요약 블록 -->
    <div class="rv-summary">
        <div class="rv-summary__score">
            <span class="rv-summary__num"><?= number_format($avg, 1) ?></span>
            <span class="rv-stars rv-stars--sm"><?= psc_render_stars($avg) ?></span>
            <span class="rv-summary__total">리뷰 <?= number_format($total) ?>개</span>
        </div>
        <div class="rv-summary__bars">
            <?php foreach ([5,4,3,2,1] as $s):
                $cnt   = (int)($stars[$s] ?? 0);
                $pct   = $total > 0 ? round($cnt / $total * 100) : 0;
            ?>
            <div class="rv-bar-row">
                <span class="rv-bar-row__label"><?= $s ?>★</span>
                <div class="rv-bar-row__track">
                    <div class="rv-bar-row__fill" style="width:<?= $pct ?>%"></div>
                </div>
                <span class="rv-bar-row__count"><?= $cnt ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- 액션 바 -->
    <div class="rv-action-bar">
        <?php if ($uid && $can === true): ?>
            <button
                class="rv-write-btn"
                data-rv-open="<?= $store_id ?>"
            >✏️ 리뷰 쓰기</button>
        <?php elseif ($uid && $can === 'duplicate'): ?>
            <button class="rv-write-btn" disabled style="background:var(--rv-border);color:var(--rv-text3);cursor:not-allowed;">
                이미 리뷰를 작성하셨어요
            </button>
        <?php else: ?>
            <a href="<?= esc_url(wp_login_url(get_permalink())) ?>" class="rv-write-btn" style="text-decoration:none;text-align:center;">
                로그인 후 리뷰 작성
            </a>
        <?php endif; ?>

        <select id="rv-sort-select" class="rv-sort-select" aria-label="정렬 기준">
            <option value="latest">최신순</option>
            <option value="likes">좋아요순</option>
            <option value="rating_high">별점 높은순</option>
            <option value="rating_low">별점 낮은순</option>
        </select>
    </div>

    <!-- 리뷰 카드 목록 -->
    <div id="rv-card-list" data-store-id="<?= $store_id ?>">
        <?php if (empty($reviews)): ?>
            <div class="rv-empty">
                <div class="rv-empty__icon">🐾</div>
                <div class="rv-empty__text">아직 리뷰가 없어요</div>
                <div class="rv-empty__sub">첫 번째 리뷰를 남겨보세요!</div>
            </div>
        <?php else: ?>
            <?php foreach ($reviews as $review):
                echo psc_render_review_card($review);
            endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- 더 불러오기 -->
    <?php if ($total > 10): ?>
    <div class="rv-load-more">
        <button id="rv-load-more-btn" class="rv-load-more-btn">
            더보기 (<?= number_format($total - 10) ?>개 남음)
        </button>
    </div>
    <?php endif; ?>

    <!-- 라이트박스 -->
    <div id="rv-lightbox" class="rv-lightbox" role="dialog" aria-label="이미지 확대">
        <img src="" alt="확대 이미지">
        <button class="rv-lightbox__close" aria-label="닫기">✕</button>
    </div>

    <?php
}
