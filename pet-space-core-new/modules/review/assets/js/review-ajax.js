/**
 * PetSpace Review AJAX JS
 * 영수증 OCR, GPS 인증, 사진 업로드, 폼 제출
 */
(function () {
    'use strict';
  
    const cfg = window.PSC || {};
    const ajaxUrl = cfg.ajaxUrl || '';
    const nonce   = cfg.nonce   || '';
  
    /* ── 공통 fetch 래퍼 ─────────────────────── */
    function post(action, data) {
      const body = new FormData();
      body.append('action', action);
      body.append('nonce',  nonce);
      Object.entries(data).forEach(([k, v]) => {
        if (v !== undefined && v !== null) body.append(k, v);
      });
      return fetch(ajaxUrl, { method: 'POST', body })
        .then(r => r.json());
    }
  
    /* ════════════════════════════════════════════
       STEP 1-A : 영수증 OCR
       ════════════════════════════════════════════ */
    function initReceiptUpload() {
      const uploadBox = document.querySelector('#rv-receipt-upload');
      const fileInput = document.querySelector('#rv-receipt-file');
      const resultEl  = document.querySelector('#rv-ocr-result');
      if (!uploadBox || !fileInput) return;
  
      uploadBox.addEventListener('click', () => fileInput.click());
      uploadBox.addEventListener('dragover', e => { e.preventDefault(); uploadBox.classList.add('rv-upload-box--hover'); });
      uploadBox.addEventListener('dragleave', () => uploadBox.classList.remove('rv-upload-box--hover'));
      uploadBox.addEventListener('drop', e => {
        e.preventDefault();
        uploadBox.classList.remove('rv-upload-box--hover');
        if (e.dataTransfer.files[0]) handleReceiptFile(e.dataTransfer.files[0]);
      });
  
      fileInput.addEventListener('change', () => {
        if (fileInput.files[0]) handleReceiptFile(fileInput.files[0]);
      });
  
      function handleReceiptFile(file) {
        /* 타입·용량 체크 */
        const allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/gif'];
        if (!allowed.includes(file.type)) { alert('지원하지 않는 파일 형식입니다.'); return; }
        if (file.size > 10 * 1024 * 1024)  { alert('10MB 이하 파일만 업로드 가능합니다.'); return; }
  
        /* 미리보기 */
        uploadBox.querySelector('.rv-upload-box__icon').textContent = '⏳';
        uploadBox.querySelector('.rv-upload-box__text').textContent = 'OCR 분석 중...';
  
        const storeId = window.PSCReviewForm?.getState().storeId || 0;
        const formData = new FormData();
        formData.append('action',   'psc_verify_receipt');
        formData.append('nonce',    nonce);
        formData.append('store_id', storeId);
        formData.append('receipt',  file);
  
        fetch(ajaxUrl, { method: 'POST', body: formData })
          .then(r => r.json())
          .then(res => {
            uploadBox.querySelector('.rv-upload-box__icon').textContent = res.success ? '✅' : '❌';
            uploadBox.querySelector('.rv-upload-box__text').textContent = res.success
              ? '영수증 인증 완료'
              : (res.data?.message || '인증 실패. 다시 시도해 주세요.');
            uploadBox.classList.toggle('rv-upload-box--done', !!res.success);
  
            if (res.success && resultEl) {
              renderOcrResult(resultEl, res.data);
            }
            window.PSCReviewForm?.setVerified(!!res.success);
          })
          .catch(() => {
            uploadBox.querySelector('.rv-upload-box__text').textContent = '오류가 발생했습니다.';
            window.PSCReviewForm?.setVerified(false);
          });
      }
  
      function renderOcrResult(el, data) {
        el.innerHTML = `
          <div class="rv-ocr-result__row">
            <span class="rv-ocr-result__label">매장명</span>
            <span>${data.store_name || '-'}</span>
          </div>
          <div class="rv-ocr-result__row">
            <span class="rv-ocr-result__label">날짜</span>
            <span>${data.date || '-'}</span>
          </div>
          <div class="rv-ocr-result__row">
            <span class="rv-ocr-result__label">금액</span>
            <span>${data.amount || '-'}</span>
          </div>
          <div class="rv-ocr-result__row">
            <span class="rv-ocr-result__label">일치도</span>
            <span>${data.score || 0}점</span>
          </div>`;
        el.style.display = 'block';
      }
    }
  
    /* ════════════════════════════════════════════
       STEP 1-B : GPS 인증
       ════════════════════════════════════════════ */
    function initGpsVerify() {
      const gpsBtn    = document.querySelector('#rv-gps-btn');
      const gpsResult = document.querySelector('#rv-gps-result');
      if (!gpsBtn) return;
  
      gpsBtn.addEventListener('click', () => {
        if (!navigator.geolocation) {
          showGpsResult(false, '이 기기는 위치 정보를 지원하지 않습니다.');
          return;
        }
        gpsBtn.disabled     = true;
        gpsBtn.textContent  = '위치 확인 중...';
  
        navigator.geolocation.getCurrentPosition(
          pos => {
            const { latitude, longitude } = pos.coords;
            const storeId = window.PSCReviewForm?.getState().storeId || 0;
  
            post('psc_verify_gps', { store_id: storeId, lat: latitude, lng: longitude })
              .then(res => {
                const ok  = !!res.success;
                const msg = res.data?.message || (ok ? '방문 인증 완료!' : '매장 반경 외 위치입니다.');
                showGpsResult(ok, msg, res.data);
                window.PSCReviewForm?.setVerified(ok);
                gpsBtn.textContent = ok ? '✅ 인증 완료' : '다시 시도';
                gpsBtn.disabled    = ok;
              })
              .catch(() => {
                showGpsResult(false, '서버 오류가 발생했습니다.');
                gpsBtn.textContent = '다시 시도';
                gpsBtn.disabled    = false;
              });
          },
          err => {
            const msg = err.code === 1 ? '위치 권한을 허용해 주세요.' : '위치를 가져올 수 없습니다.';
            showGpsResult(false, msg);
            gpsBtn.textContent = '다시 시도';
            gpsBtn.disabled    = false;
          },
          { timeout: 10000, maximumAge: 0 }
        );
      });
  
      function showGpsResult(ok, msg, data) {
        if (!gpsResult) return;
        gpsResult.className = `rv-gps-result rv-gps-result--${ok ? 'ok' : 'fail'}`;
        gpsResult.innerHTML = `
          <strong>${ok ? '✅' : '❌'} ${msg}</strong>
          ${data?.distance ? `<br><small>거리: ${Math.round(data.distance)}m / 허용 반경: ${data.radius}m</small>` : ''}`;
        gpsResult.style.display = 'block';
      }
    }
  
    /* ════════════════════════════════════════════
       STEP 6 : 사진 업로드
       ════════════════════════════════════════════ */
    function initPhotoUpload() {
      const addBtn    = document.querySelector('#rv-photo-add-btn');
      const fileInput = document.querySelector('#rv-photo-file');
      if (!addBtn || !fileInput) return;
  
      const MAX_PHOTOS = 5;
  
      addBtn.addEventListener('click', () => {
        const current = window.PSCReviewForm?.getState().photos?.length || 0;
        if (current >= MAX_PHOTOS) { alert(`사진은 최대 ${MAX_PHOTOS}장까지 첨부할 수 있습니다.`); return; }
        fileInput.click();
      });
  
      fileInput.addEventListener('change', () => {
        Array.from(fileInput.files).forEach(file => uploadPhoto(file));
        fileInput.value = '';
      });
  
      function uploadPhoto(file) {
        const current = window.PSCReviewForm?.getState().photos?.length || 0;
        if (current >= MAX_PHOTOS) return;
  
        const allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/gif'];
        if (!allowed.includes(file.type)) { alert('지원하지 않는 파일 형식입니다.'); return; }
        if (file.size > 10 * 1024 * 1024)  { alert('10MB 이하 파일만 첨부 가능합니다.'); return; }
  
        const formData = new FormData();
        formData.append('action', 'psc_upload_review_image');
        formData.append('nonce',  nonce);
        formData.append('image',  file);
  
        fetch(ajaxUrl, { method: 'POST', body: formData })
          .then(r => r.json())
          .then(res => {
            if (res.success) {
              window.PSCReviewForm?.addPhotoPreview({
                file,
                attachmentId : res.data.attachment_id,
                url          : res.data.url,
              });
            } else {
              alert(res.data?.message || '업로드 실패');
            }
          })
          .catch(() => alert('업로드 중 오류가 발생했습니다.'));
      }
    }
  
    /* ════════════════════════════════════════════
       최종 제출
       ════════════════════════════════════════════ */
    window.PSCReviewAjax = window.PSCReviewAjax || {};
    window.PSCReviewAjax.submit = function (state) {
      const submitBtn = document.querySelector('#rv-submit-btn');
      if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = '등록 중...'; }
  
      const imageIds = (state.photos || []).map(p => p.attachmentId).filter(Boolean);
  
      post('psc_save_review', {
        store_id         : state.storeId,
        rating           : state.rating,
        content          : state.content,
        tags             : JSON.stringify(state.tags),
        visited_at       : state.visitedAt,
        verify_type      : state.verifyType,
        receipt_verified : state.verifyType === 'receipt' && state.verified ? 1 : 0,
        gps_lat          : state.gpsLat  || '',
        gps_lng          : state.gpsLng  || '',
        image_ids        : JSON.stringify(imageIds),
      })
        .then(res => {
          if (res.success) {
            showSuccessMessage();
            setTimeout(() => {
              /* 바텀시트 닫고 목록 새로고침 */
              document.querySelector('#rv-close-btn')?.click();
              window.PSCReviewList?.reload?.();
            }, 1800);
          } else {
            alert(res.data?.message || '저장에 실패했습니다. 다시 시도해 주세요.');
            if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = '등록하기'; }
          }
        })
        .catch(() => {
          alert('오류가 발생했습니다.');
          if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = '등록하기'; }
        });
    };
  
    function showSuccessMessage() {
      const sheet = document.getElementById('rv-sheet');
      if (!sheet) return;
      const steps = sheet.querySelector('.rv-steps');
      if (steps) {
        steps.innerHTML = `
          <div style="text-align:center;padding:60px 20px;">
            <div style="font-size:56px;margin-bottom:16px;">🐾</div>
            <div style="font-size:20px;font-weight:700;color:#191f28;margin-bottom:8px;">리뷰가 등록되었어요!</div>
            <div style="font-size:14px;color:#8b95a1;">소중한 후기 감사합니다 :)</div>
          </div>`;
      }
    }
  
    /* ── DOM Ready ───────────────────────────── */
    function init() {
      initReceiptUpload();
      initGpsVerify();
      initPhotoUpload();
    }
  
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', init);
    } else {
      init();
    }
  })();
  