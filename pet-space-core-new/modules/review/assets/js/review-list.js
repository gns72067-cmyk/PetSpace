/**
 * PetSpace Review List JS
 * 좋아요, 삭제, 더보기, 라이트박스, 더 불러오기
 */
(function () {
    'use strict';
  
    const cfg     = window.PSC || {};
    const ajaxUrl = cfg.ajaxUrl || '';
    const nonce   = cfg.nonce   || '';
    let   offset  = 0;
    const limit   = 10;
  
    /* ── 공통 fetch ─────────────────────────── */
    function post(action, data) {
      const body = new FormData();
      body.append('action', action);
      body.append('nonce',  nonce);
      Object.entries(data).forEach(([k, v]) => body.append(k, v));
      return fetch(ajaxUrl, { method: 'POST', body }).then(r => r.json());
    }
  
    /* ── 좋아요 ──────────────────────────────── */
    function initLikes() {
      document.addEventListener('click', e => {
        const btn = e.target.closest('.rv-like-btn[data-review-id]');
        if (!btn) return;
  
        const reviewId = parseInt(btn.dataset.reviewId, 10);
        btn.disabled   = true;
  
        post('psc_toggle_like', { review_id: reviewId })
          .then(res => {
            if (res.success) {
              btn.classList.toggle('rv-like-btn--active', res.data.liked);
              const countEl = btn.querySelector('.rv-like-btn__count');
              if (countEl) countEl.textContent = res.data.count;
            } else {
              alert(res.data?.message || '로그인이 필요합니다.');
            }
          })
          .catch(() => alert('오류가 발생했습니다.'))
          .finally(() => { btn.disabled = false; });
      });
    }
  
    /* ── 삭제 ────────────────────────────────── */
    function initDelete() {
      document.addEventListener('click', e => {
        const btn = e.target.closest('.rv-card__action-btn--del[data-review-id]');
        if (!btn) return;
        if (!confirm('리뷰를 삭제할까요?')) return;
  
        const reviewId = parseInt(btn.dataset.reviewId, 10);
        btn.disabled   = true;
  
        post('psc_delete_review', { review_id: reviewId })
          .then(res => {
            if (res.success) {
              const card = btn.closest('.rv-card');
              card?.classList.add('rv-anim-fade');
              card?.style && (card.style.opacity = '0');
              setTimeout(() => card?.remove(), 250);
            } else {
              alert(res.data?.message || '삭제 실패');
              btn.disabled = false;
            }
          })
          .catch(() => { alert('오류 발생'); btn.disabled = false; });
      });
    }
  
    /* ── 더보기 텍스트 ───────────────────────── */
    function initReadMore() {
      document.addEventListener('click', e => {
        const btn = e.target.closest('.rv-card__more');
        if (!btn) return;
        const content = btn.previousElementSibling;
        if (!content) return;
        content.style.webkitLineClamp = '';
        content.style.overflow        = 'visible';
        content.style.display         = 'block';
        btn.remove();
      });
    }
  
    /* ── 라이트박스 ──────────────────────────── */
    function initLightbox() {
      const lb     = document.getElementById('rv-lightbox');
      const lbImg  = lb?.querySelector('img');
      const lbClose = lb?.querySelector('.rv-lightbox__close');
      if (!lb) return;
  
      document.addEventListener('click', e => {
        const img = e.target.closest('.rv-card__img');
        if (!img) return;
        lbImg.src = img.src;
        lb.classList.add('rv-lightbox--open');
      });
  
      lbClose?.addEventListener('click', closeLightbox);
      lb.addEventListener('click', e => { if (e.target === lb) closeLightbox(); });
      document.addEventListener('keydown', e => { if (e.key === 'Escape') closeLightbox(); });
  
      function closeLightbox() {
        lb.classList.remove('rv-lightbox--open');
      }
    }
  
    /* ── 정렬 ────────────────────────────────── */
    function initSort() {
      const sel = document.getElementById('rv-sort-select');
      if (!sel) return;
      sel.addEventListener('change', () => {
        offset = 0;
        const storeId = getStoreId();
        loadReviews(storeId, sel.value, true);
      });
    }
  
    /* ── 더 불러오기 ─────────────────────────── */
    function initLoadMore() {
      const btn = document.getElementById('rv-load-more-btn');
      if (!btn) return;
      btn.addEventListener('click', () => {
        const storeId = getStoreId();
        const order   = document.getElementById('rv-sort-select')?.value || 'latest';
        loadReviews(storeId, order, false);
      });
    }
  
    function loadReviews(storeId, order, reset) {
      if (reset) offset = 0;
      const btn = document.getElementById('rv-load-more-btn');
      if (btn) { btn.disabled = true; btn.textContent = '불러오는 중...'; }
  
      post('psc_load_reviews', { store_id: storeId, order, offset, limit })
        .then(res => {
          if (!res.success) return;
          const list    = document.getElementById('rv-card-list');
          const reviews = res.data.reviews || [];
  
          if (reset && list) list.innerHTML = '';
          reviews.forEach(card => {
            const el = document.createElement('div');
            el.innerHTML = card.html;
            list?.appendChild(el.firstElementChild);
          });
  
          offset += reviews.length;
  
          if (btn) {
            const hasMore = reviews.length >= limit;
            btn.style.display = hasMore ? 'inline-block' : 'none';
            btn.disabled      = false;
            btn.textContent   = '더보기';
          }
        })
        .catch(() => {
          if (btn) { btn.disabled = false; btn.textContent = '더보기'; }
        });
    }
  
    /* ── 외부에서 리로드 가능하게 ────────────── */
    window.PSCReviewList = window.PSCReviewList || {};
    window.PSCReviewList.reload = function () {
      const storeId = getStoreId();
      const order   = document.getElementById('rv-sort-select')?.value || 'latest';
      loadReviews(storeId, order, true);
    };
  
    /* ── 유틸 ────────────────────────────────── */
    function getStoreId() {
      return parseInt(
        document.getElementById('rv-card-list')?.dataset.storeId
        || new URLSearchParams(location.search).get('store_id')
        || '0',
        10
      );
    }
  
    /* ── 초기화 ──────────────────────────────── */
    function init() {
      initLikes();
      initDelete();
      initReadMore();
      initLightbox();
      initSort();
      initLoadMore();
    }
  
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', init);
    } else {
      init();
    }
  })();
  