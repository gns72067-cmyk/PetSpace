/* ============================================================
   PetSpace Review — 목록 인터랙션 (더보기, 정렬, 이미지 확대)
   ============================================================ */
   (function () {
    'use strict';

    /* ── 더보기 버튼 ─────────────────────── */
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.rv-card__more');
        if (!btn) return;

        const content = btn.previousElementSibling;
        if (!content) return;

        content.style.webkitLineClamp = '';
        content.style.display         = '';
        content.style.webkitBoxOrient = '';
        content.style.overflow        = '';
        btn.remove();
    });

    /* ── 정렬 셀렉트 ─────────────────────── */
    const sortSelect = document.getElementById('rv-sort-select');
    if (sortSelect) {
        sortSelect.addEventListener('change', async function () {
            const order    = this.value;
            const list     = document.getElementById('rv-card-list');
            const storeId  = list?.dataset.storeId;
            if (!storeId) return;

            const fd = new FormData();
            fd.append('action',   'psc_load_reviews');
            fd.append('nonce',    PSC.nonce);
            fd.append('store_id', storeId);
            fd.append('order',    order);
            fd.append('offset',   0);
            fd.append('limit',    10);

            try {
                const res  = await fetch(PSC.ajaxUrl, { method: 'POST', body: fd });
                const data = await res.json();

                if (data.success && list) {
                    list.innerHTML = data.data.reviews.map(r => r.html).join('');
                }
            } catch { /* skip */ }
        });
    }

    /* ── 더 불러오기 ─────────────────────── */
    const loadMoreBtn = document.getElementById('rv-load-more-btn');
    if (loadMoreBtn) {
        let offset = 10;
        loadMoreBtn.addEventListener('click', async function () {
            const list    = document.getElementById('rv-card-list');
            const storeId = list?.dataset.storeId;
            const order   = sortSelect?.value || 'latest';
            if (!storeId) return;

            loadMoreBtn.disabled    = true;
            loadMoreBtn.textContent = '불러오는 중...';

            const fd = new FormData();
            fd.append('action',   'psc_load_reviews');
            fd.append('nonce',    PSC.nonce);
            fd.append('store_id', storeId);
            fd.append('order',    order);
            fd.append('offset',   offset);
            fd.append('limit',    10);

            try {
                const res  = await fetch(PSC.ajaxUrl, { method: 'POST', body: fd });
                const data = await res.json();

                if (data.success) {
                    const html = data.data.reviews.map(r => r.html).join('');
                    if (list) list.insertAdjacentHTML('beforeend', html);
                    offset += data.data.reviews.length;

                    if (data.data.reviews.length < 10) {
                        loadMoreBtn.remove();
                    } else {
                        loadMoreBtn.disabled    = false;
                        loadMoreBtn.textContent = '더보기';
                    }
                }
            } catch {
                loadMoreBtn.disabled    = false;
                loadMoreBtn.textContent = '더보기';
            }
        });
    }

    /* ── 이미지 라이트박스 ───────────────── */
    const lightbox = document.getElementById('rv-lightbox');
    if (lightbox) {
        const img   = lightbox.querySelector('img');
        const close = lightbox.querySelector('.rv-lightbox__close');

        document.addEventListener('click', function (e) {
            const thumb = e.target.closest('.rv-card__img');
            if (!thumb) return;
            if (img) img.src = thumb.src.replace('-150x150', '').replace('-300x300', '');
            lightbox.classList.add('rv-open');
        });

        close?.addEventListener('click',  () => lightbox.classList.remove('rv-open'));
        lightbox.addEventListener('click', e => {
            if (e.target === lightbox) lightbox.classList.remove('rv-open');
        });
    }
})();
