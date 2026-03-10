<?php
/**
 * Module: Review
 * File: modules/review/review-render.php
 */
defined( 'ABSPATH' ) || exit;

/* ══════════════════════════════════════════════════════
   0-A. 태그 정의
══════════════════════════════════════════════════════ */
function psc_get_review_tags( int $store_id ): array {
    $common = [
        [ 'id' => 'kind',       'emoji' => '😊', 'label' => '친절해요' ],
        [ 'id' => 'clean',      'emoji' => '✨', 'label' => '매장이 깔끔해요' ],
        [ 'id' => 'pet',        'emoji' => '🐾', 'label' => '반려동물 친화적이에요' ],
        [ 'id' => 'price',      'emoji' => '💰', 'label' => '가격이 합리적이에요' ],
        [ 'id' => 'revisit',    'emoji' => '🔄', 'label' => '재방문 의사 있어요' ],
        [ 'id' => 'parking',    'emoji' => '🅿️', 'label' => '주차하기 편해요' ],
    ];

    $category = get_post_meta( $store_id, 'store_category', true ) ?: '';

    $category_tags = [
        'hospital' => [
            [ 'id' => 'detail',   'emoji' => '💉', 'label' => '설명이 자세해요' ],
            [ 'id' => 'careful',  'emoji' => '🔬', 'label' => '꼼꼼하게 봐줘요' ],
            [ 'id' => 'comfort',  'emoji' => '😌', 'label' => '아이가 편안해해요' ],
        ],
        'grooming' => [
            [ 'id' => 'skill',    'emoji' => '✂️', 'label' => '솜씨가 좋아요' ],
            [ 'id' => 'custom',   'emoji' => '🎨', 'label' => '원하는 대로 해줘요' ],
            [ 'id' => 'petlove',  'emoji' => '🛁', 'label' => '아이가 좋아해요' ],
        ],
        'cafe' => [
            [ 'id' => 'mood',     'emoji' => '☕', 'label' => '분위기가 좋아요' ],
            [ 'id' => 'snack',    'emoji' => '🍖', 'label' => '간식이 맛있어요' ],
            [ 'id' => 'space',    'emoji' => '🏠', 'label' => '공간이 넓어요' ],
        ],
        'shop' => [
            [ 'id' => 'variety',  'emoji' => '📦', 'label' => '제품이 다양해요' ],
            [ 'id' => 'guide',    'emoji' => '🏷️', 'label' => '설명이 친절해요' ],
            [ 'id' => 'gift',     'emoji' => '🎁', 'label' => '포장이 예뻐요' ],
        ],
    ];

    $extra = $category_tags[ $category ] ?? [];
    return array_merge( $common, $extra );
}

/* ══════════════════════════════════════════════════════
   0-C. JavaScript (1회 출력)
══════════════════════════════════════════════════════ */
function psc_review_script(): string {
    static $printed = false;
    if ( $printed ) return '';
    $printed = true;
    ob_start(); ?>
    <script id="psc-review-script">
    (function(){
    'use strict';

    const AJAX = '<?php echo esc_js( admin_url('admin-ajax.php') ); ?>';

    function $(s,c){ return (c||document).querySelector(s); }
    function $$(s,c){ return [...(c||document).querySelectorAll(s)]; }

    /* ── 라이트박스 ── */
    let _lb_images = [];
    let _lb_idx    = 0;

    function lbOpen(images, idx){
        _lb_images = images;
        _lb_idx    = idx;
        lbRender();
        const lb = document.getElementById('psc-rv-lightbox');
        if(lb) lb.classList.add('is-open');
    }
    function lbRender(){
        const lb = document.getElementById('psc-rv-lightbox');
        if(!lb) return;
        lb.querySelector('img').src = _lb_images[_lb_idx] || '';
        const counter = lb.querySelector('.psc-rv-lightbox__counter');
        if(counter){
            counter.textContent = _lb_images.length > 1 ? `${_lb_idx+1} / ${_lb_images.length}` : '';
        }
        const prev = lb.querySelector('.psc-rv-lightbox__prev');
        const next = lb.querySelector('.psc-rv-lightbox__next');
        if(prev) prev.style.display = _lb_images.length > 1 ? '' : 'none';
        if(next) next.style.display = _lb_images.length > 1 ? '' : 'none';
    }
    document.addEventListener('click', function(e){
        const t = e.target.closest('.psc-rv-thumb');
        if(!t) return;
        e.preventDefault();
        e.stopPropagation();
        const gallery = t.closest('.psc-rv-images');
        const images  = gallery?.dataset.gallery ? JSON.parse(gallery.dataset.gallery) : [t.dataset.full || t.src];
        const idx     = parseInt(t.dataset.idx || '0');
        lbOpen(images, idx);
    });
    document.addEventListener('click', function(e){
        if(e.target.classList.contains('psc-rv-lightbox') ||
           e.target.classList.contains('psc-rv-lightbox__close')){

            $$('.psc-rv-lightbox').forEach(lb => lb.classList.remove('is-open'));
        }
        if(e.target.classList.contains('psc-rv-lightbox__prev')){
            _lb_idx = (_lb_idx - 1 + _lb_images.length) % _lb_images.length;
            lbRender();
        }
        if(e.target.classList.contains('psc-rv-lightbox__next')){
            _lb_idx = (_lb_idx + 1) % _lb_images.length;
            lbRender();
        }
    });
    document.addEventListener('keydown', function(e){
        const lb = document.getElementById('psc-rv-lightbox');
        if(!lb?.classList.contains('is-open')) return;
        if(e.key === 'ArrowLeft'){  _lb_idx = (_lb_idx - 1 + _lb_images.length) % _lb_images.length; lbRender(); }
        if(e.key === 'ArrowRight'){ _lb_idx = (_lb_idx + 1) % _lb_images.length; lbRender(); }
        if(e.key === 'Escape'){ lb.classList.remove('is-open'); }
    });

    /* ── 더보기 ── */
    document.addEventListener('click', function(e){
        const btn = e.target.closest('.psc-rv-more-btn');
        if(!btn) return;
        const body = btn.previousElementSibling;
        if(!body) return;
        body.classList.toggle('is-clamped');
        btn.textContent = body.classList.contains('is-clamped') ? '더보기' : '접기';
    });

    /* ── 좋아요 ── */
    document.addEventListener('click', function(e){
        const btn = e.target.closest('.psc-rv-like-btn');
        if(!btn) return;
        const wasLiked = btn.classList.contains('is-liked');
        const icon = btn.querySelector('.psc-rv-like-icon');
        const c = btn.querySelector('.psc-rv-like-cnt');
        const prevCount = parseInt(c?.textContent || '0', 10);

        btn.disabled = true;
        btn.classList.toggle('is-liked', !wasLiked);
        if(icon) icon.textContent = !wasLiked ? '♥' : '♡';
        if(c) c.textContent = String(Math.max(0, prevCount + (!wasLiked ? 1 : -1)));

        const fd = new FormData();
        fd.append('action','psc_toggle_like');
        fd.append('review_id', btn.dataset.rid);
        fd.append('nonce', btn.dataset.nonce);
        fetch(AJAX,{method:'POST',body:fd})
            .then(r=>r.json())
            .then(res=>{
                if(res.success){
                    btn.classList.toggle('is-liked', res.data.liked);
                    if(c) c.textContent = res.data.count;
                    if(icon) icon.textContent = res.data.liked ? '♥' : '♡';
                } else {
                    btn.classList.toggle('is-liked', wasLiked);
                    if(c) c.textContent = String(prevCount);
                    if(icon) icon.textContent = wasLiked ? '♥' : '♡';
                }
                btn.disabled = false;
            })
            .catch(()=>{
                btn.classList.toggle('is-liked', wasLiked);
                if(c) c.textContent = String(prevCount);
                if(icon) icon.textContent = wasLiked ? '♥' : '♡';
                btn.disabled = false;
            });
    });

    /* ── 삭제 ── */
    document.addEventListener('click', function(e){
        const btn = e.target.closest('.psc-rv-delete-btn');
        if(!btn) return;
        if(!confirm('리뷰를 삭제할까요?')) return;
        const fd = new FormData();
        fd.append('action','psc_delete_review');
        fd.append('review_id', btn.dataset.rid);
        fd.append('nonce', btn.dataset.nonce);
        fetch(AJAX,{method:'POST',body:fd})
            .then(r=>r.json())
            .then(res=>{ if(res.success) btn.closest('.psc-rv-card')?.remove(); });
    });

    /* ════════════════════════════════════════
       리뷰 작성 폼 초기화
    ════════════════════════════════════════ */
    document.addEventListener('DOMContentLoaded', function(){

        $$('.psc-rv-write-wrap').forEach(initWriteForm);
    });

    function initWriteForm(wrap){
        const storeId    = wrap.dataset.storeId;
        const verifyType = wrap.dataset.verifyType;
        const nonce      = wrap.dataset.nonce;

        const state = {
            verifyMethod  : verifyType === 'both' ? null : verifyType,
            verified      : false,
            verifyType    : null,
            receiptId     : null,
            retryCount    : 0,
            maxRetry      : 3,
            uploadedImages: [],
            rating        : 0,
            selectedTags  : [],
            maxTags       : 3,
        };

        /* ── BOTH 탭 ── */
        if(verifyType === 'both'){

            $$('.psc-rv-verify-tab', wrap).forEach(tab => {
                tab.addEventListener('click', function(){

                    $$('.psc-rv-verify-tab', wrap).forEach(t => t.classList.remove('is-active'));

                    $$('.psc-rv-verify-panel', wrap).forEach(p => p.classList.remove('is-active'));
                    this.classList.add('is-active');
                    state.verifyMethod = this.dataset.method;
                    const panel = wrap.querySelector(`.psc-rv-verify-panel[data-method="${state.verifyMethod}"]`);
                    if(panel) panel.classList.add('is-active');
                    resetVerifyStatus(wrap);
                });
            });
            const firstTab = $('.psc-rv-verify-tab', wrap);
            if(firstTab) firstTab.click();
        } else {
            const panel = wrap.querySelector(`.psc-rv-verify-panel[data-method="${verifyType}"]`);
            if(panel) panel.classList.add('is-active');
        }

        /* ── 영수증 업로드 ── */
        const receiptZone   = $('.psc-rv-receipt-zone', wrap);
        const receiptInput  = wrap.querySelector('input.psc-rv-receipt-input');
        const receiptPrev   = $('.psc-rv-receipt-preview', wrap);
        const receiptImg    = receiptPrev?.querySelector('img');
        const receiptRemove = receiptPrev?.querySelector('.psc-rv-receipt-preview__remove');
        const ocrBtn        = $('.psc-rv-ocr-analyze-btn', wrap);

        if(receiptZone && receiptInput){
            receiptZone.addEventListener('click', () => receiptInput.click());
            receiptZone.addEventListener('dragover', e => { e.preventDefault(); receiptZone.classList.add('is-dragover'); });
            receiptZone.addEventListener('dragleave', () => receiptZone.classList.remove('is-dragover'));
            receiptZone.addEventListener('drop', e => {
                e.preventDefault();
                receiptZone.classList.remove('is-dragover');
                if(e.dataTransfer.files[0]) handleReceiptFile(e.dataTransfer.files[0]);
            });
            receiptInput.addEventListener('change', function(){
                if(this.files[0]) handleReceiptFile(this.files[0]);
            });
        }
        if(receiptRemove){
            receiptRemove.addEventListener('click', () => {
                if(receiptPrev) receiptPrev.style.display = 'none';
                if(receiptZone) receiptZone.style.display = '';
                state.receiptId = null;
                if(ocrBtn) ocrBtn.disabled = true;
                hideOcrResult(wrap);
                resetVerifyStatus(wrap);
            });
        }

        function handleReceiptFile(file){
            const allowed = ['image/jpeg','image/png','image/webp','image/heic'];
            if(!allowed.includes(file.type)){ alert('JPG, PNG, WEBP, HEIC 파일만 가능해요.'); return; }
            if(file.size > 10*1024*1024){ alert('10MB 이하 파일만 업로드 가능해요.'); return; }
            const reader = new FileReader();
            reader.onload = ev => {
                if(receiptImg) receiptImg.src = ev.target.result;
                if(receiptPrev) receiptPrev.style.display = 'block';
                if(receiptZone) receiptZone.style.display = 'none';
            };
            reader.readAsDataURL(file);
            uploadReceiptFile(file);
        }

        function uploadReceiptFile(file){
            if(ocrBtn) ocrBtn.disabled = true;
            const fd = new FormData();
            fd.append('action','psc_upload_review_image');
            fd.append('nonce', nonce);
            fd.append('image', file);
            fetch(AJAX,{method:'POST',body:fd})
                .then(r=>r.json())
                .then(res=>{
                    if(res.success){
                        state.receiptId = res.data.attachment_id;
                        if(ocrBtn) ocrBtn.disabled = false;
                    } else {
                        alert(res.data?.message || '업로드 실패');
                    }
                })
                .catch(() => alert('업로드 중 오류가 발생했어요.'));
        }

        if(ocrBtn){
            ocrBtn.addEventListener('click', function(){
                if(!state.receiptId){ alert('영수증을 먼저 업로드해주세요.'); return; }
                if(state.retryCount >= state.maxRetry){
                    showVerifyStatus(wrap,'err','❌ 3회 시도 초과. 관리자에게 문의해주세요.'); return;
                }
                runOcrVerify(wrap, storeId, state, nonce, ocrBtn);
            });
        }

        /* ── GPS ── */
        const gpsStartBtn = $('.psc-rv-gps-start-btn', wrap);
        const gpsModal    = wrap.querySelector('.psc-rv-gps-modal-overlay');
        const gpsLoading  = $('.psc-rv-gps-loading', wrap);

        if(gpsStartBtn && gpsModal){
            gpsStartBtn.addEventListener('click', () => gpsModal.classList.add('is-open'));
        }
        gpsModal?.querySelector('.psc-rv-gps-modal__cancel')
            ?.addEventListener('click', () => gpsModal.classList.remove('is-open'));
        gpsModal?.querySelector('.psc-rv-gps-modal__confirm')
            ?.addEventListener('click', () => {
                gpsModal.classList.remove('is-open');
                runGpsVerify(wrap, storeId, state, nonce, gpsLoading);
            });
        gpsModal?.addEventListener('click', e => { if(e.target===gpsModal) gpsModal.classList.remove('is-open'); });

        /* ── 키워드 태그 ── */

        $$('.psc-rv-tag-btn', wrap).forEach(btn => {
            btn.addEventListener('click', function(){
                const tagId = this.dataset.tagId;
                if(this.classList.contains('is-selected')){
                    this.classList.remove('is-selected');
                    state.selectedTags = state.selectedTags.filter(t => t !== tagId);
                } else {
                    if(state.selectedTags.length >= state.maxTags){
                        this.style.animation = 'psc-shake .3s ease';
                        setTimeout(() => this.style.animation = '', 300);
                        return;
                    }
                    this.classList.add('is-selected');
                    state.selectedTags.push(tagId);
                }

                $$('.psc-rv-tag-btn', wrap).forEach(b => {
                    if(!b.classList.contains('is-selected')){
                        b.classList.toggle('is-disabled', state.selectedTags.length >= state.maxTags);
                    }
                });
                const limitEl = wrap.querySelector('.psc-rv-tag-limit');
                if(limitEl) limitEl.textContent = `${state.selectedTags.length} / ${state.maxTags} 선택`;
            });
        });

        /* ── 별점 ── */
        const starsEl = $('.psc-rv-stars', wrap);
        if(starsEl){
            starsEl.addEventListener('mouseover', e => {
                const svg = e.target.closest('svg[data-star]');
                if(svg) highlightStars(starsEl, parseInt(svg.dataset.star));
            });
            starsEl.addEventListener('mouseleave', () => highlightStars(starsEl, state.rating));
            starsEl.addEventListener('click', e => {
                const svg = e.target.closest('svg[data-star]');
                if(svg){ state.rating = parseInt(svg.dataset.star); highlightStars(starsEl, state.rating); }
            });
        }

        /* ── 이미지 업로드 ── */
        const imgArea  = $('.psc-rv-img-area', wrap);
        const imgInput = wrap.querySelector('input.psc-rv-img-input');
        const imgAdd   = $('.psc-rv-img-add', wrap);
        if(imgAdd && imgInput){
            imgAdd.addEventListener('click', () => imgInput.click());
            imgInput.addEventListener('change', function(){
                [...this.files].forEach(f => uploadReviewImage(f, imgArea, state, nonce));
                this.value = '';
            });
        }

        /* ── 글자수 ── */
        const textarea  = wrap.querySelector('textarea.psc-rv-textarea');
        const charCount = $('.psc-rv-char-count', wrap);
        if(textarea && charCount){
            textarea.addEventListener('input', function(){
                const len = this.value.length;
                charCount.textContent = `${len} / 400`;
                charCount.classList.toggle('is-warn', len > 360);
            });
        }

        /* ── 제출 ── */
        const submitBtn = wrap.querySelector('button.psc-rv-submit-btn');
        if(submitBtn){
            submitBtn.addEventListener('click', function(){
                if(!state.verified){ alert('인증을 먼저 완료해주세요.'); return; }
                if(!state.rating){ alert('별점을 선택해주세요.'); return; }
                const content = textarea?.value.trim() || '';
                if(content.length < 10){ alert('리뷰 내용을 10자 이상 작성해주세요.'); return; }
                submitBtn.disabled = true;
                submitBtn.textContent = '저장 중...';
                const fd = new FormData();
                fd.append('action','psc_save_review');
                fd.append('nonce', nonce);
                fd.append('store_id', storeId);
                fd.append('rating', state.rating);
                fd.append('content', content);
                fd.append('tags', JSON.stringify(state.selectedTags));
                fd.append('verify_type', state.verifyType);
                fd.append('receipt_id', state.receiptId || '');
                state.uploadedImages.forEach(id => fd.append('image_ids[]', id));
                fetch(AJAX,{method:'POST',body:fd})
                    .then(r=>r.json())
                    .then(res=>{
                        if(res.success){
                            alert('리뷰가 등록됐어요! 관리자 검토 후 게시됩니다. 🐾');
                            if(res.data?.redirect) location.href = res.data.redirect;
                        } else {
                            alert(res.data?.message || '오류가 발생했어요.');
                            submitBtn.disabled = false;
                            submitBtn.textContent = '리뷰 등록하기';
                        }
                    })
                    .catch(() => {
                        alert('서버 오류가 발생했어요.');
                        submitBtn.disabled = false;
                        submitBtn.textContent = '리뷰 등록하기';
                    });
            });
        }
    } // end initWriteForm

    /* ════════════════════════════════════════
       OCR 검증
    ════════════════════════════════════════ */
    function runOcrVerify(wrap, storeId, state, nonce, btn){
        btn.disabled = true;
        btn.textContent = '분석 중...';
        hideOcrResult(wrap);
        resetVerifyStatus(wrap);
        const fd = new FormData();
        fd.append('action','psc_verify_receipt');
        fd.append('nonce', nonce);
        fd.append('store_id', storeId);
        fd.append('receipt_id', state.receiptId);
        fetch(AJAX,{method:'POST',body:fd})
            .then(r=>r.json())
            .then(res=>{
                state.retryCount++;
                updateRetryInfo(wrap, state);
                btn.textContent = '🔍 영수증 분석하기';
                if(res.success && res.data.verified){
                    renderOcrResult(wrap, res.data.fields);
                    showVerifyStatus(wrap,'ok','✅ 영수증 인증이 완료됐어요!');
                    state.verified   = true;
                    state.verifyType = 'receipt';
                    moveToStep2(wrap, state);
                } else {
                    renderOcrResult(wrap, res.data?.fields || []);
                    showVerifyStatus(wrap,'err', `❌ ${res.data?.message || '매장 정보가 일치하지 않아요.'}`);
                    if(state.retryCount < state.maxRetry) btn.disabled = false;
                    else showVerifyStatus(wrap,'err','❌ 3회 시도 초과. 관리자에게 문의해주세요.');
                }
            })
            .catch(() => {
                btn.disabled = false;
                btn.textContent = '🔍 영수증 분석하기';
                showVerifyStatus(wrap,'err','❌ 서버 오류. 다시 시도해주세요.');
            });
    }

    function renderOcrResult(wrap, fields){
        const box = wrap.querySelector('.psc-rv-ocr-result');
        if(!box) return;
        const body = box.querySelector('.psc-rv-ocr-result__body');
        if(!body) return;
        body.innerHTML = '';
        fields.forEach(f => {
            const cls  = f.status==='match'?'is-match':f.status==='ref'?'is-ref':f.status==='miss'?'is-miss':'';
            const icon = f.status==='match'?'🟢':f.status==='ref'?'🔵':f.status==='miss'?'🔴':'⬜';
            const row  = document.createElement('div');
            row.className = `psc-rv-ocr-row ${cls}`;
            row.innerHTML = `<span class="psc-rv-ocr-row__label">${escHtml(f.label)}</span>
                             <span class="psc-rv-ocr-row__val">${escHtml(f.value||'-')}</span>
                             <span class="psc-rv-ocr-row__status">${icon}</span>`;
            body.appendChild(row);
        });
        box.classList.add('is-visible');
    }
    function hideOcrResult(wrap){
        wrap.querySelector('.psc-rv-ocr-result')?.classList.remove('is-visible');
    }

    /* ════════════════════════════════════════
       GPS 검증
    ════════════════════════════════════════ */
    function runGpsVerify(wrap, storeId, state, nonce, loadingEl){
        if(loadingEl) loadingEl.classList.add('is-visible');
        resetVerifyStatus(wrap);
        const gpsBtn = wrap.querySelector('.psc-rv-gps-start-btn');
        if(gpsBtn) gpsBtn.disabled = true;

        if(!navigator.geolocation){
            loadingEl?.classList.remove('is-visible');
            showVerifyStatus(wrap,'err','❌ 이 브라우저는 위치 서비스를 지원하지 않아요.');
            if(gpsBtn) gpsBtn.disabled = false;
            return;
        }

        navigator.geolocation.getCurrentPosition(
            pos => {
                const fd = new FormData();
                fd.append('action','psc_verify_gps');
                fd.append('nonce', nonce);
                fd.append('store_id', storeId);
                fd.append('lat', pos.coords.latitude);
                fd.append('lng', pos.coords.longitude);
                fetch(AJAX,{method:'POST',body:fd})
                    .then(r=>r.json())
                    .then(res=>{
                        loadingEl?.classList.remove('is-visible');
                        if(res.success && res.data.verified){
                            showVerifyStatus(wrap,'ok',`📍 방문 인증 완료! (매장까지 ${res.data.distance}m)`);
                            state.verified   = true;
                            state.verifyType = 'gps';
                            moveToStep2(wrap, state);
                        } else {
                            /* ── GPS 인증 실패 — 친절한 안내 메시지 ── */
                            const distance = res.data?.distance;
                            const radius   = res.data?.radius || 300;
                            let msg = '❌ 매장 반경 밖에 계세요.<br>';
                            if(distance){
                                msg += `현재 매장까지 <strong>${distance}m</strong> 떨어져 있어요.<br>`;
                                msg += `(허용 반경: ${radius}m)<br>`;
                            }
                            msg += '매장 근처에 계실 때 다시 시도해주세요.';
                            showVerifyStatus(wrap,'err', msg);
                            if(gpsBtn) gpsBtn.disabled = false;
                        }
                    })
                    .catch(() => {
                        loadingEl?.classList.remove('is-visible');
                        showVerifyStatus(wrap,'err','❌ 서버 오류가 발생했어요. 잠시 후 다시 시도해주세요.');
                        if(gpsBtn) gpsBtn.disabled = false;
                    });
            },
            err => {
                loadingEl?.classList.remove('is-visible');
                if(gpsBtn) gpsBtn.disabled = false;
                if(err.code===1) showVerifyStatus(wrap,'warn','⚠️ 위치 권한이 거부됐어요.<br>브라우저 설정에서 위치를 허용한 후 다시 시도해주세요.');
                else if(err.code===2) showVerifyStatus(wrap,'warn','⚠️ 위치 정보를 가져올 수 없어요.<br>GPS가 켜져 있는지 확인해주세요.');
                else if(err.code===3) showVerifyStatus(wrap,'warn','⚠️ 위치 확인 시간이 초과됐어요.<br>잠시 후 다시 시도해주세요.');
                else showVerifyStatus(wrap,'err','❌ 위치 확인 중 오류가 발생했어요.');
            },
            { timeout:10000, enableHighAccuracy:true }
        );
    }

    /* ════════════════════════════════════════
       STEP 2로 이동
    ════════════════════════════════════════ */
    function moveToStep2(wrap, state){
        setTimeout(() => {
            wrap.querySelector('.psc-rv-verify-section').style.display = 'none';
            const form = wrap.querySelector('.psc-rv-form-panel');
            if(form) form.classList.add('is-active');

            const steps = wrap.querySelectorAll('.psc-rv-progress__step');
            const lines = wrap.querySelectorAll('.psc-rv-progress__line');
            steps[0]?.classList.replace('is-active','is-done');
            lines[0]?.classList.add('is-done');
            steps[1]?.classList.add('is-active');

            const banner = wrap.querySelector('.psc-rv-verified-banner');
            if(banner){
                banner.innerHTML = state.verifyType === 'receipt'
                    ? '✅ 영수증 인증 완료 — 리뷰를 작성해주세요!'
                    : '📍 방문 인증 완료 — 리뷰를 작성해주세요!';
            }
        }, 800);
    }

    /* ════════════════════════════════════════
       리뷰 이미지 업로드
    ════════════════════════════════════════ */
    function uploadReviewImage(file, imgArea, state, nonce){
        if(state.uploadedImages.length >= 5){ alert('이미지는 최대 5장까지 첨부 가능해요.'); return; }
        const allowed = ['image/jpeg','image/png','image/webp','image/gif'];
        if(!allowed.includes(file.type)){ alert('JPG, PNG, WEBP, GIF 파일만 가능해요.'); return; }
        if(file.size > 10*1024*1024){ alert('10MB 이하 파일만 가능해요.'); return; }
        const fd = new FormData();
        fd.append('action','psc_upload_review_image');
        fd.append('nonce', nonce);
        fd.append('image', file);
        fetch(AJAX,{method:'POST',body:fd})
            .then(r=>r.json())
            .then(res=>{
                if(res.success){
                    state.uploadedImages.push(res.data.attachment_id);
                    const item = document.createElement('div');
                    item.className = 'psc-rv-img-item';
                    item.dataset.id = res.data.attachment_id;
                    item.innerHTML = `<img src="${res.data.thumbnail}" alt="">
                        <button class="psc-rv-img-item__del" type="button">×</button>`;
                    item.querySelector('.psc-rv-img-item__del').addEventListener('click', () => {
                        state.uploadedImages = state.uploadedImages.filter(id => id != res.data.attachment_id);
                        item.remove();
                    });
                    imgArea.insertBefore(item, imgArea.querySelector('.psc-rv-img-add'));
                } else {
                    alert(res.data?.message || '이미지 업로드 실패');
                }
            })
            .catch(() => alert('이미지 업로드 중 오류가 발생했어요.'));
    }

    /* ── 유틸 ── */
    function highlightStars(container, count){
        container.querySelectorAll('svg[data-star]').forEach(svg => {
            svg.querySelector('path')?.setAttribute('fill', parseInt(svg.dataset.star) <= count ? '#f59e0b' : '#e5e8eb');
        });
    }

    function showVerifyStatus(wrap, type, msg){
        /* 현재 활성화된 패널 안의 status 박스를 찾기 */
        const activePanel = wrap.querySelector('.psc-rv-verify-panel.is-active');
        const box = (activePanel || wrap).querySelector('.psc-rv-verify-status');
        if(!box) return;
        box.className = `psc-rv-verify-status is-visible psc-rv-verify-status--${type}`;
        box.innerHTML = `<span>${msg}</span>`;
        box.scrollIntoView({ behavior:'smooth', block:'nearest' });
    }

    function resetVerifyStatus(wrap){
        /* 모든 패널의 status 박스 초기화 */
        wrap.querySelectorAll('.psc-rv-verify-status').forEach(box => {
            box.className = 'psc-rv-verify-status';
            box.innerHTML = '';
        });
    }

    function updateRetryInfo(wrap, state){
        const el = wrap.querySelector('.psc-rv-retry-info');
        if(el) el.textContent = `시도 횟수: ${state.retryCount} / ${state.maxRetry}`;
    }
    function escHtml(str){
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    })();
    </script>
    <?php
    return ob_get_clean();
}

/* ══════════════════════════════════════════════════════
   2. [store_review_summary]
══════════════════════════════════════════════════════ */
function psc_shortcode_review_summary( array $atts ): string {
    $atts    = shortcode_atts( [ 'id' => get_the_ID() ], $atts, 'store_review_summary' );
    $post_id = (int) $atts['id'];
    if ( ! $post_id ) return '';

    $summary   = psc_get_review_summary( $post_id );
    $avg       = $summary->total > 0 ? round( (float) $summary->avg_rating, 1 ) : 0;
    $total     = (int) $summary->total;
    $dist      = $summary->distribution ?? [];
    $can_write = psc_can_write_review( $post_id );
    $write_url = psc_review_write_url( $post_id );
    $list_url  = psc_review_list_url( $post_id );

    ob_start();
    ?>
    <div class="psc-rv-wrap psc-rv-summary">
        <div class="psc-rv-summary__top">
            <div class="psc-rv-summary__score">
                <div class="psc-rv-summary__big"><?php echo esc_html( $avg ?: '-' ); ?></div>
                <div class="psc-rv-summary__stars"><?php echo psc_render_stars( $avg ); ?></div>
                <div class="psc-rv-summary__cnt"><?php echo number_format( $total ); ?>개의 리뷰</div>
            </div>
            <div class="psc-rv-summary__bars">
                <?php for ( $i = 5; $i >= 1; $i-- ) :
                    $cnt = (int) ( $dist[$i] ?? 0 );
                    $pct = $total > 0 ? round( $cnt / $total * 100 ) : 0;
                ?>
                <div class="psc-rv-bar-row">
                    <span class="psc-rv-bar-row__label"><?php echo $i; ?></span>
                    <div class="psc-rv-bar-track">
                        <div class="psc-rv-bar-fill" style="width:<?php echo $pct; ?>%"></div>
                    </div>
                    <span class="psc-rv-bar-row__pct"><?php echo $pct; ?>%</span>
                </div>
                <?php endfor; ?>
            </div>
        </div>
        <div class="psc-rv-summary__actions">
            <?php if ( $can_write === true ) : ?>
                <a href="<?php echo esc_url( $write_url ); ?>" class="psc-rv-btn psc-rv-btn--primary">✏️ 리뷰 작성</a>
            <?php elseif ( $can_write === 'duplicate' ) : ?>
                <span class="psc-rv-btn psc-rv-btn--secondary" style="cursor:default;">이미 리뷰를 작성했어요</span>
            <?php else : ?>
                <a href="<?php echo esc_url( wp_login_url( $write_url ) ); ?>" class="psc-rv-btn psc-rv-btn--primary">로그인 후 리뷰 작성</a>
            <?php endif; ?>
            <a href="<?php echo esc_url( $list_url ); ?>" class="psc-rv-btn psc-rv-btn--secondary">리뷰 전체보기</a>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'store_review_summary', 'psc_shortcode_review_summary' );

/* ══════════════════════════════════════════════════════
   3. [store_reviews]
══════════════════════════════════════════════════════ */
function psc_shortcode_store_reviews( array $atts ): string {
    $atts    = shortcode_atts( [ 'id' => get_the_ID(), 'limit' => 5, 'orderby' => 'likes' ], $atts, 'store_reviews' );
    $post_id = (int) $atts['id'];
    $limit   = (int) $atts['limit'];
    if ( ! $post_id ) return '';

    $reviews    = psc_get_reviews( $post_id, $atts['orderby'], $limit );
    $total      = (int) psc_get_review_summary( $post_id )->total;
    $uid        = get_current_user_id();
    $like_nonce = wp_create_nonce( 'psc_toggle_like' );
    $del_nonce  = wp_create_nonce( 'psc_delete_review' );

    ob_start();
    echo psc_review_script();
    ?>
    <div class="psc-rv-wrap psc-rv-list">
        <?php if ( empty( $reviews ) ) : ?>
            <p style="color:var(--rv-text3);font-size:.9rem;text-align:center;padding:32px 0;">
                아직 리뷰가 없어요. 첫 번째 리뷰를 남겨보세요! 🐾
            </p>
        <?php else : ?>
            <?php foreach ( $reviews as $rv ) :
                $images  = psc_get_review_images( $rv->id );
                $liked   = $uid ? psc_user_liked_review( $rv->id, $uid ) : false;
                $is_mine = $uid && ( (int) $rv->user_id === $uid || current_user_can('manage_options') );
                $avatar  = mb_substr( psc_mask_name( $rv->display_name ?? '익명' ), 0, 1 );
                $tags    = $rv->tags ? json_decode( $rv->tags, true ) : [];

                $badge = '';
                if ( $rv->receipt_verified && $rv->verify_type === 'receipt' )
                    $badge = '<span class="psc-rv-badge psc-rv-badge--receipt">✅ 영수증 인증</span>';
                elseif ( $rv->verify_type === 'gps' )
                    $badge = '<span class="psc-rv-badge psc-rv-badge--gps">📍 방문 인증</span>';
            ?>
            <div class="psc-rv-card" data-review-id="<?php echo (int) $rv->id; ?>">
                <div class="psc-rv-card__head">
                    <div class="psc-rv-avatar"><?php echo esc_html( $avatar ); ?></div>
                    <div class="psc-rv-card__meta">
                        <div class="psc-rv-card__name"><?php echo esc_html( psc_mask_name( $rv->display_name ?? '익명' ) ); ?></div>
                        <div class="psc-rv-card__sub">
                            <?php echo psc_render_stars( $rv->rating ); ?>
                            <span class="psc-rv-card__date"><?php echo esc_html( date_i18n( 'Y.m.d', strtotime( $rv->created_at ) ) ); ?></span>
                            <?php echo $badge; ?>
                        </div>
                    </div>
                </div>

                <?php if ( ! empty( $tags ) ) : ?>
                <div class="psc-rv-card__tags">
                    <?php
                    $all_tags = array_column( psc_get_review_tags( $post_id ), null, 'id' );
                    foreach ( $tags as $tag_id ) :
                        $tag = $all_tags[ $tag_id ] ?? null;
                        if ( $tag ) : ?>
                        <span class="psc-rv-card__tag"><?php echo esc_html( $tag['emoji'] . ' ' . $tag['label'] ); ?></span>
                    <?php endif; endforeach; ?>
                </div>
                <?php endif; ?>

                <div class="psc-rv-card__body is-clamped"><?php echo nl2br( esc_html( $rv->content ) ); ?></div>
                <?php if ( mb_strlen( $rv->content ) > 35 ) : ?>
                    <button class="psc-rv-more-btn" type="button">더보기</button>
                <?php endif; ?>

                <?php if ( ! empty( $images ) ) : ?>
                <?php
                $img_urls = array_map( fn($img) => esc_url( $img->url ), $images );
                $img_json = esc_attr( json_encode( $img_urls ) );
                ?>
                <div class="psc-rv-images" data-gallery="<?php echo $img_json; ?>">
                    <?php foreach ( $images as $idx => $img ) : ?>
                    <img class="psc-rv-thumb" src="<?php echo esc_url( $img->url ); ?>" data-full="<?php echo esc_url( $img->url ); ?>" data-idx="<?php echo $idx; ?>" alt="리뷰 이미지">
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <div class="psc-rv-card__foot">
                    <button class="psc-rv-like-btn <?php echo $liked ? 'is-liked' : ''; ?>"
                            data-rid="<?php echo (int) $rv->id; ?>"
                            data-nonce="<?php echo esc_attr( $like_nonce ); ?>"
                            type="button">
                        <span class="psc-rv-like-icon"><?php echo $liked ? '♥' : '♡'; ?></span>
                        <span class="psc-rv-like-cnt"><?php echo (int) $rv->likes; ?></span>
                    </button>
                    <?php if ( $is_mine ) : ?>
                    <button class="psc-rv-delete-btn"
                            data-rid="<?php echo (int) $rv->id; ?>"
                            data-nonce="<?php echo esc_attr( $del_nonce ); ?>"
                            type="button">삭제</button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if ( $total > $limit ) : ?>
            <a href="<?php echo esc_url( psc_review_list_url( $post_id ) ); ?>" class="psc-rv-load-more">
                리뷰 더보기 (<?php echo number_format( $total ); ?>개) →
            </a>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <div id="psc-rv-lightbox" class="psc-rv-lightbox" role="dialog">
        <button class="psc-rv-lightbox__close" aria-label="닫기">×</button>
        <button class="psc-rv-lightbox__prev" aria-label="이전">‹</button>
        <img src="" alt="리뷰 이미지 확대">
        <button class="psc-rv-lightbox__next" aria-label="다음">›</button>
        <div class="psc-rv-lightbox__counter"></div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'store_reviews', 'psc_shortcode_store_reviews' );

/* ══════════════════════════════════════════════════════
   4. [store_review_write]
══════════════════════════════════════════════════════ */
function psc_shortcode_review_write( array $atts ): string {
    $atts    = shortcode_atts( [ 'id' => get_the_ID() ], $atts, 'store_review_write' );
    $post_id = (int) $atts['id'];
    if ( ! $post_id && isset( $_GET['store_id'] ) ) $post_id = (int) $_GET['store_id'];
    if ( ! $post_id ) return '';

    if ( ! is_user_logged_in() ) {
        $current_url = home_url( '/review-write/?store_id=' . $post_id );
        return '<p style="text-align:center;padding:32px;font-family:Pretendard,sans-serif;">
            <a href="' . esc_url( wp_login_url( $current_url ) ) . '" style="color:#224471;font-weight:700;">로그인</a> 후 리뷰를 작성할 수 있어요.
        </p>';
    }

    $can = psc_can_write_review( $post_id );
    if ( $can === 'duplicate' ) {
        return '<p style="text-align:center;padding:32px;font-family:Pretendard,sans-serif;color:#4e5968;">이미 이 매장에 리뷰를 작성하셨어요.</p>';
    }

    $verify_type = psc_get_store_verify_type( $post_id );
    $gps_radius  = psc_get_store_gps_radius( $post_id );
    $store_title = get_the_title( $post_id );
    $nonce       = wp_create_nonce( 'psc_review_nonce' );
    $tags        = psc_get_review_tags( $post_id );

    ob_start();
    echo psc_review_script();
    ?>
    <div class="psc-rv-wrap psc-rv-write-wrap"
         data-store-id="<?php echo $post_id; ?>"
         data-verify-type="<?php echo esc_attr( $verify_type ); ?>"
         data-gps-radius="<?php echo $gps_radius; ?>"
         data-nonce="<?php echo esc_attr( $nonce ); ?>">

        <!-- 프로그레스 -->
        <div class="psc-rv-progress">
            <div class="psc-rv-progress__step is-active">
                <div class="psc-rv-progress__dot">1</div>
                <span>방문 인증</span>
            </div>
            <div class="psc-rv-progress__line"></div>
            <div class="psc-rv-progress__step">
                <div class="psc-rv-progress__dot">2</div>
                <span>리뷰 작성</span>
            </div>
        </div>

        <!-- ════ STEP 1: 인증 ════ -->
        <div class="psc-rv-verify-section">

            <?php if ( $verify_type === 'both' ) : ?>
            <div class="psc-rv-verify-tabs">
                <button class="psc-rv-verify-tab" data-method="receipt" type="button">
                    <div class="psc-rv-verify-tab__icon">🧾</div>
                    <div class="psc-rv-verify-tab__label">영수증 인증</div>
                    <div class="psc-rv-verify-tab__desc">영수증을 촬영해주세요</div>
                </button>
                <button class="psc-rv-verify-tab" data-method="gps" type="button">
                    <div class="psc-rv-verify-tab__icon">📍</div>
                    <div class="psc-rv-verify-tab__label">GPS 인증</div>
                    <div class="psc-rv-verify-tab__desc">현재 위치를 확인해요</div>
                </button>
            </div>
            <?php endif; ?>

            <!-- 영수증 패널 -->
            <div class="psc-rv-verify-panel" data-method="receipt">
                <p style="font-size:.85rem;color:var(--rv-text2);margin-bottom:16px;line-height:1.65;">
                    방문하신 매장의 영수증을 촬영하거나 업로드해주세요.<br>
                    <strong style="color:var(--rv-primary);">매장명, 사업자번호, 주소</strong>가 잘 보이도록 찍어주세요.
                </p>
                <div class="psc-rv-receipt-zone">
                    <div class="psc-rv-receipt-zone__icon">🧾</div>
                    <div class="psc-rv-receipt-zone__title">영수증 사진 업로드</div>
                    <div class="psc-rv-receipt-zone__desc">클릭하거나 드래그 · JPG, PNG, WEBP, HEIC · 최대 10MB</div>
                </div>
                <input type="file" class="psc-rv-receipt-input" accept="image/jpeg,image/png,image/webp,image/heic" style="display:none;">
                <div class="psc-rv-receipt-preview">
                    <img src="" alt="영수증 미리보기">
                    <button class="psc-rv-receipt-preview__remove" type="button">×</button>
                </div>
                <div class="psc-rv-ocr-result">
                    <div class="psc-rv-ocr-result__title">📋 인식된 영수증 정보</div>
                    <div class="psc-rv-ocr-result__body"></div>
                </div>
                <div class="psc-rv-verify-status"></div>
                <div class="psc-rv-retry-info"></div>
                <button class="psc-rv-btn psc-rv-btn--primary psc-rv-ocr-analyze-btn" type="button" disabled style="width:100%;margin-top:4px;">
                    🔍 영수증 분석하기
                </button>
            </div>

            <!-- GPS 패널 -->
            <div class="psc-rv-verify-panel" data-method="gps">
                <p style="font-size:.85rem;color:var(--rv-text2);margin-bottom:20px;line-height:1.65;">
                    현재 위치를 확인하여 방문을 인증합니다.<br>
                    매장 반경 <strong style="color:var(--rv-primary);"><?php echo $gps_radius; ?>m</strong> 이내에 계셔야 해요.
                </p>
                <div class="psc-rv-gps-loading">
                    <div class="psc-rv-spinner"></div>
                    <div class="psc-rv-gps-loading__text">위치를 확인하는 중이에요...</div>
                </div>
                <div class="psc-rv-verify-status"></div>
                <button class="psc-rv-btn psc-rv-btn--primary psc-rv-gps-start-btn" type="button" style="width:100%;">
                    📍 위치 확인하기
                </button>
                <div class="psc-rv-gps-modal-overlay">
                    <div class="psc-rv-gps-modal">
                        <div class="psc-rv-gps-modal__icon">📍</div>
                        <div class="psc-rv-gps-modal__title">위치 확인이 필요해요</div>
                        <div class="psc-rv-gps-modal__desc">
                            매장 방문 인증을 위해 현재 위치를 확인합니다.<br><br>
                            <strong><?php echo esc_html( $store_title ); ?></strong> 매장 반경<br>
                            <strong><?php echo $gps_radius; ?>m</strong> 이내에서만 인증이 가능해요.
                        </div>
                        <div class="psc-rv-gps-modal__actions">
                            <button class="psc-rv-gps-modal__cancel" type="button">취소</button>
                            <button class="psc-rv-gps-modal__confirm" type="button">📍 위치 허용하기</button>
                        </div>
                    </div>
                </div>
            </div>

        </div><!-- /verify-section -->

        <!-- ════ STEP 2: 리뷰 작성 ════ -->
        <div class="psc-rv-form-panel">

            <div class="psc-rv-verified-banner">✅ 인증 완료 — 리뷰를 작성해주세요!</div>
            <div class="psc-rv-store-title">📝 <?php echo esc_html( $store_title ); ?> 리뷰</div>

            <!-- 별점 -->
            <div class="psc-rv-field-label">별점 <span style="color:#ef4444;">*</span></div>
            <div class="psc-rv-stars">
                <?php for ( $i = 1; $i <= 5; $i++ ) : ?>
                <svg data-star="<?php echo $i; ?>" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" fill="#e5e8eb"/>
                </svg>
                <?php endfor; ?>
            </div>

            <!-- 키워드 태그 -->
            <div class="psc-rv-field-label">
                어떤 점이 좋았나요?
                <span style="color:var(--rv-text3);font-weight:400;">(최대 3개)</span>
            </div>
            <div class="psc-rv-tags-wrap">
                <?php foreach ( $tags as $tag ) : ?>
                <button class="psc-rv-tag-btn" type="button"
                        data-tag-id="<?php echo esc_attr( $tag['id'] ); ?>">
                    <?php echo esc_html( $tag['emoji'] . ' ' . $tag['label'] ); ?>
                </button>
                <?php endforeach; ?>
            </div>
            <div class="psc-rv-tag-limit">0 / 3 선택</div>

            <!-- 내용 -->
            <div class="psc-rv-field-label">리뷰 내용 <span style="color:#ef4444;">*</span></div>
            <textarea class="psc-rv-textarea"
                      placeholder="이 매장에서의 경험을 자유롭게 작성해주세요. (10자 이상)"
                      maxlength="400"></textarea>
            <div class="psc-rv-char-count">0 / 400</div>

            <!-- 사진 -->
            <div class="psc-rv-field-label">
                사진 첨부
                <span style="color:var(--rv-text3);font-weight:400;">(선택 · 최대 5장)</span>
            </div>
            <div class="psc-rv-img-area">
                <div class="psc-rv-img-add" role="button" tabindex="0">
                    <span class="psc-rv-img-add__icon">📷</span>
                    <span>사진 추가</span>
                </div>
            </div>
            <input type="file" class="psc-rv-img-input"
                   accept="image/jpeg,image/png,image/webp,image/gif"
                   multiple style="display:none;">

            <!-- 제출 -->
            <button class="psc-rv-submit-btn" type="button">리뷰 등록하기</button>
            <p style="font-size:.75rem;color:var(--rv-text3);text-align:center;margin-top:12px;line-height:1.6;">
                등록된 리뷰는 관리자 검토 후 게시됩니다.
            </p>
        </div>

    </div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'store_review_write', 'psc_shortcode_review_write' );

/* ══════════════════════════════════════════════════════
   5. [store_rating_inline]
══════════════════════════════════════════════════════ */
function psc_shortcode_rating_inline( array $atts ): string {
    $atts    = shortcode_atts( [ 'id' => get_the_ID() ], $atts, 'store_rating_inline' );
    $post_id = (int) $atts['id'];
    if ( ! $post_id ) return '';

    $summary  = psc_get_review_summary( $post_id );
    $avg      = $summary->total > 0 ? number_format( (float) $summary->avg_rating, 1 ) : 0;
    $total    = (int) $summary->total;
    $list_url = psc_review_list_url( $post_id );

    if ( ! $total ) {
        return '<span class="psc-rating-inline psc-rating-inline--empty">☆ 리뷰 없음</span>';
    }

    ob_start();
    ?>
    <span class="psc-rating-inline">
        <span>⭐</span>
        <span class="psc-rating-inline__avg"><?php echo esc_html( $avg ); ?></span>
        <span class="psc-rating-inline__sep">/</span>
        <a class="psc-rating-inline__cnt" href="<?php echo esc_url( $list_url ); ?>">
            <?php echo number_format( $total ); ?>개의 리뷰
        </a>
    </span>
    <?php
    return ob_get_clean();
}
add_shortcode( 'store_rating_inline', 'psc_shortcode_rating_inline' );
