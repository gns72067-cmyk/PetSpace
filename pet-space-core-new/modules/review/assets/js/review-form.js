/* ============================================================
   PetSpace Review — 바텀시트 폼 컨트롤러
   ============================================================ */
   (function () {
    'use strict';

    const TOTAL_STEPS = 6;
    let currentStep  = 1;
    let verifyPassed = false;
    let selectedRating = 0;
    let selectedTags   = [];
    let uploadedImages = [];   // [{attachmentId, url, thumbUrl}]
    let gpsData        = null; // {lat, lng, verified}

    /* ── DOM 참조 ────────────────────────── */
    const overlay   = () => document.getElementById('rv-overlay');
    const sheet     = () => document.getElementById('rv-sheet');
    const closeBtn  = () => document.getElementById('rv-close-btn');
    const prevBtn   = () => document.getElementById('rv-prev-btn');
    const nextBtn   = () => document.getElementById('rv-next-btn');
    const submitBtn = () => document.getElementById('rv-submit-btn');

    /* ── 시트 열기/닫기 ──────────────────── */
    function openSheet() {
        overlay()?.classList.add('rv-open');
        sheet()?.classList.add('rv-open');
        document.body.style.overflow = 'hidden';
    }
    function closeSheet() {
        overlay()?.classList.remove('rv-open');
        sheet()?.classList.remove('rv-open');
        document.body.style.overflow = '';
    }

    /* ── 진행 바 업데이트 ────────────────── */
    function updateProgress(step) {
        document.querySelectorAll('.rv-progress__bar').forEach((bar, idx) => {
            bar.classList.remove('rv-progress__bar--active', 'rv-progress__bar--done');
            if (idx + 1 < step)  bar.classList.add('rv-progress__bar--done');
            if (idx + 1 === step) bar.classList.add('rv-progress__bar--active');
        });
    }

    /* ── 스텝 이동 ───────────────────────── */
    function goToStep(step) {
        document.querySelectorAll('.rv-step').forEach(el => {
            el.classList.remove('rv-step--active');
        });
        const target = document.querySelector(`.rv-step[data-step="${step}"]`);
        if (target) target.classList.add('rv-step--active');

        currentStep = step;
        updateProgress(step);
        updateButtons();
        validateStep();
    }

    function updateButtons() {
        const prev   = prevBtn();
        const next   = nextBtn();
        const submit = submitBtn();
        if (!prev || !next || !submit) return;

        prev.style.visibility = currentStep === 1 ? 'hidden' : 'visible';

        if (currentStep === TOTAL_STEPS) {
            next.style.display   = 'none';
            submit.style.display = '';
        } else {
            next.style.display   = '';
            submit.style.display = 'none';
        }
    }

    /* ── 스텝별 유효성 검사 ──────────────── */
    function validateStep() {
        const next = nextBtn();
        if (!next) return;
        let valid = false;

        switch (currentStep) {
            case 1: valid = verifyPassed;           break;
            case 2: valid = !!document.getElementById('rv-visited-at')?.value; break;
            case 3: valid = selectedRating > 0;     break;
            case 4: valid = true;                   break; // 선택사항
            case 5: {
                const len = (document.getElementById('rv-content')?.value || '').length;
                valid = len >= 10;
                break;
            }
            case 6: valid = true; break; // 선택사항
        }

        next.disabled = !valid;
        const submit = submitBtn();
        if (submit) submit.disabled = !valid;
    }

    /* ── STEP 1: 인증 탭 전환 ────────────── */
    function initVerifyTabs() {
        document.querySelectorAll('.rv-verify-tab').forEach(tab => {
            tab.addEventListener('click', () => {
                document.querySelectorAll('.rv-verify-tab').forEach(t => t.classList.remove('rv-verify-tab--active'));
                document.querySelectorAll('.rv-verify-panel').forEach(p => p.classList.remove('rv-verify-panel--active'));
                tab.classList.add('rv-verify-tab--active');
                const type = tab.dataset.type;
                document.querySelector(`.rv-verify-panel[data-type="${type}"]`)?.classList.add('rv-verify-panel--active');
                verifyPassed = false;
                validateStep();
            });
        });

        /* 영수증 업로드 박스 클릭 */
        const uploadBox = document.getElementById('rv-receipt-upload');
        const fileInput = document.getElementById('rv-receipt-file');
        if (uploadBox && fileInput) {
            uploadBox.addEventListener('click', () => fileInput.click());
            fileInput.addEventListener('change', handleReceiptUpload);
        }

        /* GPS 인증 버튼 */
        document.getElementById('rv-gps-btn')?.addEventListener('click', handleGpsVerify);
    }

    /* 영수증 업로드 처리 */
    async function handleReceiptUpload(e) {
        const file = e.target.files?.[0];
        if (!file) return;

        const resultBox = document.getElementById('rv-ocr-result');
        if (resultBox) {
            resultBox.style.display = 'block';
            resultBox.textContent   = '🔍 영수증 분석 중...';
        }

        const storeId = document.getElementById('rv-store-id')?.value;
        const fd = new FormData();
        fd.append('action',   'psc_verify_receipt');
        fd.append('nonce',    PSC.nonce);
        fd.append('store_id', storeId);
        fd.append('receipt',  file);

        try {
            const res  = await fetch(PSC.ajaxUrl, { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                verifyPassed = true;
                if (resultBox) {
                    resultBox.textContent = `✅ 인증 완료! (신뢰도 ${data.data.score}점) ${data.data.date ? '날짜: ' + data.data.date : ''}`;
                }
                validateStep();
            } else {
                verifyPassed = false;
                if (resultBox) resultBox.textContent = '❌ ' + (data.data?.message || '인증 실패');
            }
        } catch {
            if (resultBox) resultBox.textContent = '❌ 네트워크 오류가 발생했습니다.';
        }
    }

    /* GPS 인증 처리 */
    function handleGpsVerify() {
        if (!navigator.geolocation) {
            alert('이 브라우저는 위치 정보를 지원하지 않습니다.');
            return;
        }
        const btn       = document.getElementById('rv-gps-btn');
        const resultBox = document.getElementById('rv-gps-result');
        if (btn) { btn.disabled = true; btn.textContent = '📍 위치 확인 중...'; }

        navigator.geolocation.getCurrentPosition(async pos => {
            const lat = pos.coords.latitude;
            const lng = pos.coords.longitude;
            const storeId = document.getElementById('rv-store-id')?.value;

            const fd = new FormData();
            fd.append('action',   'psc_verify_gps');
            fd.append('nonce',    PSC.nonce);
            fd.append('store_id', storeId);
            fd.append('lat',      lat);
            fd.append('lng',      lng);

            try {
                const res  = await fetch(PSC.ajaxUrl, { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    verifyPassed = true;
                    gpsData = { lat, lng, verified: true };
                    if (resultBox) {
                        resultBox.style.display = 'block';
                        resultBox.textContent   = `✅ 위치 인증 완료! (거리: ${data.data.distance}m)`;
                    }
                    validateStep();
                } else {
                    if (resultBox) {
                        resultBox.style.display = 'block';
                        resultBox.textContent   = `❌ ${data.data?.message || '매장 근처가 아닙니다.'}`;
                    }
                }
            } catch {
                alert('네트워크 오류가 발생했습니다.');
            } finally {
                if (btn) { btn.disabled = false; btn.textContent = '현재 위치 인증하기'; }
            }
        }, () => {
            alert('위치 권한을 허용해 주세요.');
            if (btn) { btn.disabled = false; btn.textContent = '현재 위치 인증하기'; }
        });
    }

    /* ── STEP 2: 날짜 기본값 세팅 ───────── */
    function initDateStep() {
        const input = document.getElementById('rv-visited-at');
        if (!input) return;

        /* 오늘 날짜·시간 기본값 */
        const now = new Date();
        now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
        input.value = now.toISOString().slice(0, 16);
        input.max   = now.toISOString().slice(0, 16);

        input.addEventListener('input', validateStep);
    }

    /* ── STEP 3: 별점 선택 ───────────────── */
    function initRatingStep() {
        const labels = ['', '별로였어요', '그저 그랬어요', '보통이었어요', '좋았어요', '최고였어요!'];
        const stars  = document.querySelectorAll('.rv-rating-star');
        const label  = document.getElementById('rv-rating-label');

        stars.forEach(star => {
            star.addEventListener('click', () => {
                selectedRating = parseInt(star.dataset.value, 10);
                stars.forEach(s => {
                    const v = parseInt(s.dataset.value, 10);
                    s.classList.toggle('selected', v <= selectedRating);
                    s.classList.remove('hovered');
                });
                if (label) label.textContent = labels[selectedRating] || '';
                validateStep();
            });
            star.addEventListener('mouseenter', () => {
                const hv = parseInt(star.dataset.value, 10);
                stars.forEach(s => {
                    s.classList.toggle('hovered', parseInt(s.dataset.value, 10) <= hv);
                });
            });
            star.addEventListener('mouseleave', () => {
                stars.forEach(s => s.classList.remove('hovered'));
            });
        });
    }

    /* ── STEP 4: 태그 선택 ───────────────── */
    function initTagStep() {
        document.querySelectorAll('.rv-tag-chip').forEach(chip => {
            chip.addEventListener('click', () => {
                const tag = chip.dataset.tag;
                if (chip.classList.contains('selected')) {
                    chip.classList.remove('selected');
                    selectedTags = selectedTags.filter(t => t !== tag);
                } else {
                    if (selectedTags.length >= 3) return;
                    chip.classList.add('selected');
                    selectedTags.push(tag);
                }
            });
        });
    }

    /* ── STEP 5: 본문 글자 수 ────────────── */
    function initContentStep() {
        const ta    = document.getElementById('rv-content');
        const count = document.getElementById('rv-char-count');
        if (!ta) return;
        ta.addEventListener('input', () => {
            const len = ta.value.length;
            if (count) count.textContent = `${len} / 1000`;
            validateStep();
        });
    }

    /* ── STEP 6: 사진 추가 ───────────────── */
    function initPhotoStep() {
        const addBtn   = document.getElementById('rv-photo-add-btn');
        const fileInput = document.getElementById('rv-photo-file');
        if (!addBtn || !fileInput) return;

        addBtn.addEventListener('click',   () => fileInput.click());
        addBtn.addEventListener('keydown', e => { if (e.key === 'Enter') fileInput.click(); });
        fileInput.addEventListener('change', handlePhotoUpload);
    }

    async function handlePhotoUpload(e) {
        const files = Array.from(e.target.files || []);
        const grid  = document.getElementById('rv-photos-grid');
        const addBtn = document.getElementById('rv-photo-add-btn');
        if (!grid) return;

        for (const file of files) {
            if (uploadedImages.length >= 5) break;
            if (file.size > 10 * 1024 * 1024) { alert('10MB 이하 파일만 가능합니다.'); continue; }

            const fd = new FormData();
            fd.append('action', 'psc_upload_review_image');
            fd.append('nonce',  PSC.nonce);
            fd.append('image',  file);

            try {
                const res  = await fetch(PSC.ajaxUrl, { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    uploadedImages.push({
                        attachmentId: data.data.attachment_id,
                        url:          data.data.url,
                        thumbUrl:     data.data.thumbnail,
                    });
                    renderPhotoThumb(data.data.attachment_id, data.data.thumbnail, grid, addBtn);
                }
            } catch { /* skip */ }
        }
        e.target.value = '';
    }

    function renderPhotoThumb(attachmentId, thumbUrl, grid, addBtn) {
        const div = document.createElement('div');
        div.className = 'rv-photo-thumb';
        div.innerHTML = `<img src="${thumbUrl}" alt="첨부 이미지">
            <button class="rv-photo-thumb__del" data-att="${attachmentId}" aria-label="삭제">✕</button>`;
        div.querySelector('button').addEventListener('click', () => {
            uploadedImages = uploadedImages.filter(i => i.attachmentId !== attachmentId);
            div.remove();
            if (uploadedImages.length < 5 && !grid.contains(addBtn)) grid.appendChild(addBtn);
        });
        grid.insertBefore(div, addBtn);
        if (uploadedImages.length >= 5) addBtn.remove();
    }

    /* ── 초기화 ──────────────────────────── */
    function init() {
        /* 리뷰쓰기 버튼 이벤트 */
        document.addEventListener('click', e => {
            const btn = e.target.closest('[data-rv-open]');
            if (btn) { openSheet(); e.preventDefault(); }
        });

        closeBtn()?.addEventListener('click', closeSheet);
        overlay()?.addEventListener('click',  closeSheet);

        prevBtn()?.addEventListener('click', () => { if (currentStep > 1) goToStep(currentStep - 1); });
        nextBtn()?.addEventListener('click', () => { if (currentStep < TOTAL_STEPS) goToStep(currentStep + 1); });

        initVerifyTabs();
        initDateStep();
        initRatingStep();
        initTagStep();
        initContentStep();
        initPhotoStep();

        updateButtons();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    /* 외부에서 gpsData / selectedRating 등을 review-ajax.js에서 읽을 수 있도록 노출 */
    window.PSC_FORM = {
        getStoreId:      () => document.getElementById('rv-store-id')?.value || 0,
        getRating:       () => selectedRating,
        getTags:         () => selectedTags,
        getImageIds:     () => uploadedImages.map(i => i.attachmentId),
        getVisitedAt:    () => document.getElementById('rv-visited-at')?.value || '',
        getContent:      () => document.getElementById('rv-content')?.value   || '',
        getGpsData:      () => gpsData,
        isVerifyPassed:  () => verifyPassed,
        getVerifyType:   () => {
            const activeTab = document.querySelector('.rv-verify-tab.rv-verify-tab--active');
            return activeTab?.dataset.type || 'receipt';
        },
        resetForm: function () {
            verifyPassed   = false;
            selectedRating = 0;
            selectedTags   = [];
            uploadedImages = [];
            gpsData        = null;
        }
    };
})();
