/* ============================================================
   PetSpace Review — AJAX 제출 & 좋아요 & 삭제
   ============================================================ */
   (function () {
    'use strict';

    /* ── 리뷰 등록 제출 ──────────────────── */
    const submitBtn = () => document.getElementById('rv-submit-btn');

    document.addEventListener('click', async function (e) {
        const btn = e.target.closest('#rv-submit-btn');
        if (!btn) return;

        const form = PSC_FORM;
        if (!form.isVerifyPassed()) { alert('방문 인증을 완료해 주세요.'); return; }
        if (!form.getRating())      { alert('별점을 선택해 주세요.'); return; }
        if (form.getContent().length < 10) { alert('리뷰는 최소 10자 이상 작성해 주세요.'); return; }

        btn.disabled    = true;
        btn.textContent = '등록 중...';

        const fd = new FormData();
        fd.append('action',           'psc_save_review');
        fd.append('nonce',            PSC.nonce);
        fd.append('store_id',         form.getStoreId());
        fd.append('rating',           form.getRating());
        fd.append('content',          form.getContent());
        fd.append('tags',             JSON.stringify(form.getTags()));
        fd.append('visited_at',       form.getVisitedAt());
        fd.append('verify_type',      form.getVerifyType());
        fd.append('receipt_verified', form.isVerifyPassed() ? 1 : 0);
        fd.append('image_ids',        JSON.stringify(form.getImageIds()));

        const gps = form.getGpsData();
        if (gps) {
            fd.append('gps_lat', gps.lat);
            fd.append('gps_lng', gps.lng);
        }

        try {
            const res  = await fetch(PSC.ajaxUrl, { method: 'POST', body: fd });
            const data = await res.json();

            if (data.success) {
                /* 시트 닫기 */
                document.getElementById('rv-overlay')?.classList.remove('rv-open');
                document.getElementById('rv-sheet')?.classList.remove('rv-open');
                document.body.style.overflow = '';

                pscToast('✅ 리뷰가 등록되었습니다!', true);
                form.resetForm();

                /* 목록 새로고침 */
                setTimeout(() => location.reload(), 1200);
            } else {
                alert(data.data?.message || '오류가 발생했습니다.');
                btn.disabled    = false;
                btn.textContent = '등록하기';
            }
        } catch {
            alert('네트워크 오류가 발생했습니다.');
            btn.disabled    = false;
            btn.textContent = '등록하기';
        }
    });

    /* ── 좋아요 토글 ─────────────────────── */
    document.addEventListener('click', async function (e) {
        const btn = e.target.closest('.rv-like-btn');
        if (!btn) return;

        if (btn.dataset.requireLogin) {
            location.href = PSC.loginUrl;
            return;
        }

        const reviewId = btn.dataset.reviewId;
        if (!reviewId) return;

        const fd = new FormData();
        fd.append('action',    'psc_toggle_like');
        fd.append('nonce',     PSC.nonce);
        fd.append('review_id', reviewId);

        try {
            const res  = await fetch(PSC.ajaxUrl, { method: 'POST', body: fd });
            const data = await res.json();

            if (data.success) {
                const icon  = btn.querySelector('.rv-like-btn__icon');
                const count = btn.querySelector('.rv-like-btn__count');
                btn.classList.toggle('rv-like-btn--active', data.data.liked);
                if (icon)  icon.textContent  = data.data.liked ? '❤️' : '🤍';
                if (count) count.textContent = data.data.count;
            }
        } catch { /* skip */ }
    });

    /* ── 리뷰 삭제 ───────────────────────── */
    document.addEventListener('click', async function (e) {
        const btn = e.target.closest('.rv-card__action-btn--del');
        if (!btn) return;

        if (!confirm('이 리뷰를 삭제할까요?')) return;

        const reviewId = btn.dataset.reviewId;
        const fd = new FormData();
        fd.append('action',    'psc_delete_review');
        fd.append('nonce',     PSC.nonce);
        fd.append('review_id', reviewId);

        try {
            const res  = await fetch(PSC.ajaxUrl, { method: 'POST', body: fd });
            const data = await res.json();

            if (data.success) {
                btn.closest('.rv-card')?.remove();
                pscToast('🗑 삭제되었습니다.', true);
            } else {
                alert(data.data?.message || '삭제 실패');
            }
        } catch { /* skip */ }
    });

    /* ── 토스트 헬퍼 ─────────────────────── */
    function pscToast(msg, ok) {
        let el = document.getElementById('psc-rv-toast');
        if (!el) {
            el = document.createElement('div');
            el.id = 'psc-rv-toast';
            el.style.cssText = 'position:fixed;bottom:90px;left:50%;transform:translateX(-50%);z-index:9999;padding:12px 20px;border-radius:10px;font-size:.88rem;font-weight:600;box-shadow:0 4px 16px rgba(0,0,0,.12);transition:opacity .4s;pointer-events:none;white-space:nowrap;font-family:Pretendard,sans-serif;';
            document.body.appendChild(el);
        }
        el.textContent      = msg;
        el.style.background = ok ? '#f0fdf4' : '#fef2f2';
        el.style.color      = ok ? '#16a34a' : '#dc2626';
        el.style.border     = ok ? '1px solid #bbf7d0' : '1px solid #fecaca';
        el.style.opacity    = '1';
        clearTimeout(el._t);
        el._t = setTimeout(() => { el.style.opacity = '0'; }, 3000);
    }

    window.pscToast = pscToast;
})();
