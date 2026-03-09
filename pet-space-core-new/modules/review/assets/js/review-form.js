/**
 * PetSpace Review Form JS
 * 바텀시트 스텝 제어, 별점, 태그, 글자수
 */
(function () {
    'use strict';
  
    /* ── 상수 ────────────────────────────────── */
    const TOTAL_STEPS  = 6;
    const MAX_TAGS     = 3;
    const MIN_CONTENT  = 10;
    const MAX_CONTENT  = 1000;
  
    /* ── 상태 ────────────────────────────────── */
    const state = {
      currentStep  : 1,
      verifyType   : null,   // 'receipt' | 'gps'
      verified     : false,
      visitedAt    : '',
      rating       : 0,
      tags         : [],
      content      : '',
      photos       : [],     // { file, attachmentId, url }
      storeId      : 0,
    };
  
    /* ── DOM 캐싱 ────────────────────────────── */
    let sheet, overlay, steps, bars, prevBtn, nextBtn, submitBtn;
  
    /* ── 초기화 ──────────────────────────────── */
    function init() {
      sheet     = document.getElementById('rv-sheet');
      overlay   = document.getElementById('rv-overlay');
      if (!sheet || !overlay) return;
  
      steps     = Array.from(sheet.querySelectorAll('.rv-step'));
      bars      = Array.from(sheet.querySelectorAll('.rv-progress__bar'));
      prevBtn   = sheet.querySelector('#rv-prev-btn');
      nextBtn   = sheet.querySelector('#rv-next-btn');
      submitBtn = sheet.querySelector('#rv-submit-btn');
  
      /* 트리거 버튼 */
      document.querySelectorAll('[data-rv-open]').forEach(btn => {
        btn.addEventListener('click', () => {
          state.storeId = parseInt(btn.dataset.rvOpen, 10) || 0;
          openSheet();
        });
      });
  
      /* 닫기 */
      overlay.addEventListener('click', closeSheet);
      sheet.querySelector('#rv-close-btn')?.addEventListener('click', closeSheet);
  
      /* 이전/다음 */
      prevBtn?.addEventListener('click',   goPrev);
      nextBtn?.addEventListener('click',   goNext);
      submitBtn?.addEventListener('click', handleSubmit);
  
      /* 인증 탭 */
      sheet.querySelectorAll('.rv-verify-tab').forEach(tab => {
        tab.addEventListener('click', () => selectVerifyTab(tab.dataset.type));
      });
  
      /* 별점 */
      sheet.querySelectorAll('.rv-rating-star').forEach(star => {
        star.addEventListener('click',     () => selectRating(parseInt(star.dataset.value, 10)));
        star.addEventListener('mouseover', () => hoverRating(parseInt(star.dataset.value, 10)));
        star.addEventListener('mouseout',  () => hoverRating(state.rating));
      });
  
      /* 키워드 태그 */
      sheet.querySelectorAll('.rv-tag-chip').forEach(chip => {
        chip.addEventListener('click', () => toggleTag(chip.dataset.tag, chip));
      });
  
      /* 텍스트에어리어 */
      const ta = sheet.querySelector('#rv-content');
      if (ta) {
        ta.addEventListener('input', () => {
          state.content = ta.value;
          updateCharCount(ta.value.length);
        });
      }
  
      /* 날짜 */
      const dateInput = sheet.querySelector('#rv-visited-at');
      if (dateInput) {
        /* 오늘 날짜를 기본값으로, 최대값도 오늘 */
        const today = new Date().toISOString().slice(0, 16);
        dateInput.max   = today;
        dateInput.value = today;
        state.visitedAt = today;
        dateInput.addEventListener('change', () => { state.visitedAt = dateInput.value; });
      }
    }
  
    /* ── 시트 열기/닫기 ──────────────────────── */
    function openSheet() {
      resetState();
      showStep(1);
      document.body.style.overflow = 'hidden';
      overlay.classList.add('rv-overlay--visible');
      sheet.classList.add('rv-sheet--open');
    }
  
    function closeSheet() {
      document.body.style.overflow = '';
      overlay.classList.remove('rv-overlay--visible');
      sheet.classList.remove('rv-sheet--open');
    }
  
    function resetState() {
      Object.assign(state, {
        currentStep : 1,
        verifyType  : null,
        verified    : false,
        visitedAt   : '',
        rating      : 0,
        tags        : [],
        content     : '',
        photos      : [],
      });
  
      /* UI 초기화 */
      sheet.querySelectorAll('.rv-tag-chip').forEach(c => c.classList.remove('rv-tag-chip--active'));
      sheet.querySelectorAll('.rv-rating-star').forEach(s => s.classList.remove('rv-rating-star--on'));
      sheet.querySelector('#rv-content') && (sheet.querySelector('#rv-content').value = '');
      updateCharCount(0);
      sheet.querySelector('#rv-rating-label') && (sheet.querySelector('#rv-rating-label').textContent = '');
      clearPhotoPreviews();
    }
  
    /* ── 스텝 표시 ───────────────────────────── */
    function showStep(n) {
      state.currentStep = n;
  
      steps.forEach((el, i) => {
        el.classList.toggle('rv-step--active', i + 1 === n);
      });
  
      bars.forEach((bar, i) => {
        bar.classList.toggle('rv-progress__bar--done',   i + 1 < n);
        bar.classList.toggle('rv-progress__bar--active', i + 1 === n);
      });
  
      /* 버튼 상태 */
      if (prevBtn)   prevBtn.style.visibility  = n > 1 ? 'visible' : 'hidden';
      if (nextBtn)   nextBtn.style.display     = n < TOTAL_STEPS ? 'block' : 'none';
      if (submitBtn) submitBtn.style.display   = n === TOTAL_STEPS ? 'block' : 'none';
  
      updateNextBtn();
    }
  
    /* ── 이전/다음 ───────────────────────────── */
    function goPrev() {
      if (state.currentStep > 1) showStep(state.currentStep - 1);
    }
  
    function goNext() {
      if (!canProceed()) { shakeNextBtn(); return; }
      if (state.currentStep < TOTAL_STEPS) showStep(state.currentStep + 1);
    }
  
    /* ── 스텝별 통과 조건 ────────────────────── */
    function canProceed() {
      switch (state.currentStep) {
        case 1: return state.verified;
        case 2: return !!state.visitedAt;
        case 3: return state.rating > 0;
        case 4: return true;   // 태그는 선택 사항
        case 5: return state.content.length >= MIN_CONTENT;
        case 6: return true;   // 사진은 선택 사항
        default: return true;
      }
    }
  
    function updateNextBtn() {
      if (!nextBtn) return;
      nextBtn.disabled = !canProceed();
    }
  
    function shakeNextBtn() {
      nextBtn?.classList.remove('rv-anim-shake');
      void nextBtn?.offsetWidth;   // reflow
      nextBtn?.classList.add('rv-anim-shake');
      nextBtn?.addEventListener('animationend', () => nextBtn.classList.remove('rv-anim-shake'), { once: true });
    }
  
    /* ── 인증 탭 ─────────────────────────────── */
    function selectVerifyTab(type) {
      state.verifyType = type;
      state.verified   = false;
  
      sheet.querySelectorAll('.rv-verify-tab').forEach(t => {
        t.classList.toggle('rv-verify-tab--active', t.dataset.type === type);
      });
      sheet.querySelectorAll('.rv-verify-panel').forEach(p => {
        p.classList.toggle('rv-verify-panel--active', p.dataset.type === type);
      });
  
      updateNextBtn();
    }
  
    /* ── 인증 완료 콜백 (ajax.js 에서 호출) ──── */
    window.PSCReviewForm = window.PSCReviewForm || {};
    window.PSCReviewForm.setVerified = function (ok) {
      state.verified = ok;
      updateNextBtn();
    };
  
    window.PSCReviewForm.getState = function () { return state; };
  
    /* ── 별점 ────────────────────────────────── */
    const RATING_LABELS = ['', '별로예요', '그저 그래요', '보통이에요', '좋아요', '최고예요!'];
  
    function selectRating(val) {
      state.rating = val;
      hoverRating(val);
      const label = sheet.querySelector('#rv-rating-label');
      if (label) label.textContent = RATING_LABELS[val] || '';
      updateNextBtn();
    }
  
    function hoverRating(val) {
      sheet.querySelectorAll('.rv-rating-star').forEach(s => {
        s.classList.toggle('rv-rating-star--on', parseInt(s.dataset.value, 10) <= val);
      });
    }
  
    /* ── 태그 ────────────────────────────────── */
    function toggleTag(tag, chip) {
      const idx = state.tags.indexOf(tag);
      if (idx === -1) {
        if (state.tags.length >= MAX_TAGS) {
          chip.classList.add('rv-anim-shake');
          chip.addEventListener('animationend', () => chip.classList.remove('rv-anim-shake'), { once: true });
          showTagsHint(true);
          return;
        }
        state.tags.push(tag);
        chip.classList.add('rv-tag-chip--active');
      } else {
        state.tags.splice(idx, 1);
        chip.classList.remove('rv-tag-chip--active');
      }
      showTagsHint(false);
    }
  
    function showTagsHint(warn) {
      const hint = sheet.querySelector('.rv-tags-hint');
      if (!hint) return;
      hint.textContent  = warn ? `태그는 최대 ${MAX_TAGS}개까지 선택할 수 있어요` : `최대 ${MAX_TAGS}개 선택 가능`;
      hint.style.color  = warn ? '#e03131' : '';
    }
  
    /* ── 글자수 ──────────────────────────────── */
    function updateCharCount(len) {
      const el = sheet.querySelector('#rv-char-count');
      if (!el) return;
      el.textContent = `${len} / ${MAX_CONTENT}`;
      el.classList.toggle('rv-char-count--warn', len > MAX_CONTENT * 0.9);
    }
  
    /* ── 사진 미리보기 추가 (ajax.js 에서 호출) */
    window.PSCReviewForm.addPhotoPreview = function ({ file, attachmentId, url }) {
      state.photos.push({ file, attachmentId, url });
      renderPhotoPreview({ attachmentId, url });
    };
  
    function renderPhotoPreview({ attachmentId, url }) {
      const grid = sheet.querySelector('#rv-photos-grid');
      if (!grid) return;
  
      const item = document.createElement('div');
      item.className     = 'rv-photo-item';
      item.dataset.attId = attachmentId;
      item.innerHTML = `
        <img src="${url}" alt="첨부사진">
        <button class="rv-photo-item__del" aria-label="삭제">✕</button>`;
  
      item.querySelector('.rv-photo-item__del').addEventListener('click', () => {
        state.photos = state.photos.filter(p => p.attachmentId !== attachmentId);
        item.remove();
      });
  
      /* 추가 버튼 앞에 삽입 */
      const addBtn = grid.querySelector('.rv-photo-add');
      grid.insertBefore(item, addBtn);
    }
  
    function clearPhotoPreviews() {
      const grid = sheet.querySelector('#rv-photos-grid');
      if (!grid) return;
      grid.querySelectorAll('.rv-photo-item').forEach(el => el.remove());
    }
  
    /* ── 폼 제출 ─────────────────────────────── */
    function handleSubmit() {
      if (typeof window.PSCReviewAjax?.submit === 'function') {
        window.PSCReviewAjax.submit(state);
      }
    }
  
    /* ── DOMContentLoaded 이후 초기화 ───────── */
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', init);
    } else {
      init();
    }
  })();
  