<?php
/**
 * PetSpace — Pet Module
 * File: modules/pets/pets.php
 *
 * Shortcode : [my_pets_dashboard]
 * AJAX      : psc_save_pet, psc_delete_pet, psc_upload_pet_photo
 * DB Table  : {prefix}psc_pets
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* ============================================================
   0. 상수 & 테이블 생성
   ============================================================ */
define( 'PSC_PETS_TABLE', 'psc_pets' );

add_action( 'init', function () {
    if ( get_option( 'psc_pets_table_version' ) !== '1.0' ) {
        psc_pets_create_table();
    }
} );

function psc_pets_create_table(): void {
    global $wpdb;
    $table   = $wpdb->prefix . PSC_PETS_TABLE;
    $charset = $wpdb->get_charset_collate();
    $sql     = "CREATE TABLE IF NOT EXISTS {$table} (
        id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id      BIGINT UNSIGNED NOT NULL,
        pet_name     VARCHAR(100)    NOT NULL,
        gender       VARCHAR(10)     NOT NULL DEFAULT 'unknown',
        birthday     DATE            NULL,
        is_neutered  TINYINT(1)      NOT NULL DEFAULT 0,
        photo_url    VARCHAR(500)    NULL,
        photo_att_id BIGINT UNSIGNED NULL,
        breed_mix    LONGTEXT        NULL,
        created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_user (user_id)
    ) {$charset};";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
    update_option( 'psc_pets_table_version', '1.0' );
}

/* ============================================================
   1. 견종 목록
   ============================================================ */
function psc_get_breed_list(): array {
    return [
        '말티즈', '포메라니안', '치와와', '요크셔테리어', '시추', '푸들 (토이)', '비숑 프리제',
        '파피용', '닥스훈트', '미니어처 핀셔', '이탈리안 그레이하운드', '러셀 테리어',
        '웨스트 하이랜드 화이트 테리어', '스코티시 테리어', '케언 테리어', '하바니즈',
        '볼로네제', '코통 드 툴레아', '라사압소', '페키니즈', '퍼그', '프렌치 불독',
        '보스턴 테리어', '미니어처 슈나우저', '실키 테리어', '맨체스터 테리어',
        '비글', '코커 스패니얼', '스프링거 스패니얼', '브리타니', '바셋 하운드',
        '불독', '샤페이', '차우차우', '기슈', '시바 이누', '북경견', '케리 블루 테리어',
        '소프트 코티드 휘튼 테리어', '풀리', '경주 스패니얼', '포르투갈 워터독',
        '아메리칸 코커 스패니얼', '래브라도 리트리버 (미니)', '오스트레일리안 캐틀독',
        '웰시 코기 펨브로크', '웰시 코기 카디건', '셰틀랜드 쉽독 (쉘티)',
        '래브라도 리트리버', '골든 리트리버', '저먼 셰퍼드', '시베리안 허스키',
        '알래스카 맬러뮤트', '사모예드', '보더 콜리', '오스트레일리안 셰퍼드',
        '도베르만', '로트와일러', '복서', '그레이트 데인', '세인트 버나드',
        '버니즈 마운틴 독', '뉴펀들랜드', '아이리시 세터', '달마시안',
        '아프간 하운드', '살루키', '그레이하운드', '위마라너', '리드백',
        '아키타 이누', '진돗개', '풍산개', '삽살개',
        '기타 (직접 입력)',
    ];
}

/* ============================================================
   2. 숏코드 등록
   ============================================================ */
add_shortcode( 'my_pets_dashboard', 'psc_shortcode_my_pets_dashboard' );

function psc_shortcode_my_pets_dashboard(): string {
    if ( ! is_user_logged_in() ) {
        return '<p style="padding:20px;text-align:center;">로그인이 필요합니다. <a href="' . esc_url( home_url( '/login/' ) ) . '">로그인</a></p>';
    }
    ob_start();
    psc_pets_render_dashboard();
    return ob_get_clean();
}

/* ============================================================
   3. 대시보드 렌더
   ============================================================ */
function psc_pets_render_dashboard(): void {
    $user_id = get_current_user_id();
    global $wpdb;
    $table = $wpdb->prefix . PSC_PETS_TABLE;
    $pets  = $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM {$table} WHERE user_id = %d ORDER BY created_at ASC",
        $user_id
    ) );
    $breeds    = psc_get_breed_list();
    $nonce_add = wp_create_nonce( 'psc_pet_save' );
    $nonce_del = wp_create_nonce( 'psc_pet_delete' );
    $nonce_img = wp_create_nonce( 'psc_pet_photo' );

    $primary      = '#224471';
    ?>
    <div class="psc-pets-wrap" id="pscPetsWrap">

    <!-- ── 상단 헤더 ── -->
    <div class="psc-pets-header">
        <div class="psc-pets-title">
            🐾 내 반려견
            <span>(<?php echo count($pets); ?>마리)</span>
        </div>
        <button class="btn-add-pet" onclick="pscOpenPetForm()">
            ＋ 반려견 추가
        </button>
    </div>

    <!-- ── 추가/수정 폼 패널 ── -->
    <div class="psc-pet-form-panel" id="pscPetFormPanel">
        <div class="form-panel-title" id="pscFormTitle">🐶 새 반려견 등록</div>
        <form id="pscPetForm" onsubmit="return false;">
            <input type="hidden" id="petId"         name="pet_id"       value="0">
            <input type="hidden" id="petNonce"      name="nonce"        value="<?php echo esc_attr($nonce_add); ?>">
            <input type="hidden" id="petPhotoUrl"   name="photo_url"    value="">
            <input type="hidden" id="petPhotoAttId" name="photo_att_id" value="">

            <!-- 사진 업로드 -->
            <div class="pet-form-field full" style="align-items:center;">
                <div class="pet-photo-upload-wrap" id="petPhotoWrap"
                     onclick="document.getElementById('petPhotoFile').click()">
                    <div class="pet-photo-placeholder" id="petPhotoPlaceholder">🐾</div>
                    <img id="petPhotoPreview" src="" style="display:none;" alt="">
                    <input type="file" class="pet-form-field-input" id="petPhotoFile" accept="image/*"
                           onchange="pscPreviewPetPhoto(this)" style="display:none;">
                </div>
                <div class="pet-photo-hint">사진을 눌러 변경</div>
            </div>

            <div class="pet-form-grid">
                <!-- 이름 -->
                <div class="pet-form-field">
                    <label>이름 <span style="color:#dc2626;">*</span></label>
                    <input type="text" class="pet-form-field-input" id="petName" name="pet_name"
                           placeholder="예: 초코" maxlength="50">
                </div>
                <!-- 성별 -->
                <div class="pet-form-field">
                    <label>성별</label>
                    <select class="pet-form-field-select" id="petGender" name="gender">
                        <option value="male">수컷 ♂</option>
                        <option value="female">암컷 ♀</option>
                        <option value="unknown">모름</option>
                    </select>
                </div>
                <!-- 생년월일 -->
                <div class="pet-form-field">
                    <label>생년월일</label>
                    <input type="date" class="pet-form-field-input" id="petBirthday" name="birthday">
                </div>
                <!-- 중성화 -->
                <div class="pet-form-field">
                    <label>중성화 여부</label>
                    <select class="pet-form-field-select" id="petNeutered" name="is_neutered">
                        <option value="0">안함</option>
                        <option value="1">완료 ✂️</option>
                    </select>
                </div>

                <!-- 견종 선택 -->
                <div class="pet-form-field full">
                    <label>견종</label>

                    <!-- 타입 토글 -->
                    <div class="breed-type-toggle">
                        <div class="breed-type-btn active" id="breedTypePure"
                             onclick="pscSetBreedType('pure')">🐶 순종</div>
                        <div class="breed-type-btn" id="breedTypeMix"
                             onclick="pscSetBreedType('mix')">🧬 믹스견</div>
                        <div class="breed-type-btn" id="breedTypeUnknown"
                             onclick="pscSetBreedType('unknown')">❓ 모르겠어요</div>
                    </div>

                    <!-- 순종 -->
                    <div id="breedPureSection">
                        <div class="breed-search-wrap">
                            <input type="text" class="breed-search-input" id="breedPureSearch"
                                   placeholder="견종 검색... (예: 말티즈)"
                                   oninput="pscFilterBreeds(this.value)"
                                   onfocus="pscOpenBreedDD()"
                                   autocomplete="off">
                            <div class="breed-dropdown" id="breedPureDD">
                                <?php foreach ( $breeds as $b ) : ?>
                                <div class="breed-option" data-value="<?php echo esc_attr($b); ?>"
                                     onclick="pscSelectBreed('<?php echo esc_js($b); ?>')">
                                    <?php echo esc_html($b); ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <input type="hidden" id="breedPureValue" name="breed_pure" value="">
                        <input type="text" class="pet-form-field-input" id="breedPureCustom"
                               placeholder="견종 직접 입력"
                               style="margin-top:8px;display:none;width:100%;
                                      padding:11px 13px;border:1.5px solid #e5e8eb;
                                      border-radius:10px;font-size:.9rem;
                                      box-sizing:border-box;background:#f4f6f9;
                                      font-family:'Pretendard',sans-serif;">
                    </div>

                    <!-- 믹스견 -->
                    <div id="breedMixSection" style="display:none;">
                        <div class="dna-mixer-wrap">
                            <div style="font-size:.8rem;color:#8b95a1;margin-bottom:12px;">
                                🧬 견종을 선택하고 별점으로 비율을 설정하세요 (최대 3종)
                            </div>
                            <div id="dnaMixRows"></div>
                            <button type="button" class="btn-add-breed"
                                    id="btnAddBreed" onclick="pscAddMixRow()">
                                ＋ 견종 추가
                            </button>
                            <div class="dna-preview-bar" id="dnaPreviewWrap" style="display:none;">
                                <div class="dna-bar-label">🧬 DNA 구성 미리보기</div>
                                <div class="dna-bar" id="dnaPreviewBar"></div>
                                <div class="dna-legend" id="dnaPreviewLegend"></div>
                            </div>
                        </div>
                    </div>

                    <!-- 모르겠어요 -->
                    <div id="breedUnknownSection" style="display:none;">
                        <div class="breed-unknown-box">
                            <div class="breed-unknown-icon">🐾</div>
                            <div class="breed-unknown-text">
                                <strong>괜찮아요!</strong><br>
                                견종을 몰라도 소중한 반려견이에요.<br>
                                <span style="color:#8b95a1;font-size:.78rem;">
                                    나중에 알게 되면 언제든 수정할 수 있어요.
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="pet-form-actions">
                <button type="button" class="btn-pet-cancel" onclick="pscClosePetForm()">취소</button>
                <button type="button" class="btn-pet-save"   onclick="pscSavePet()">저장하기</button>
            </div>
        </form>
    </div>

    <!-- ── 반려견 카드 목록 ── -->
    <?php if ( empty($pets) ) : ?>
    <div class="psc-pets-empty" id="pscPetsEmpty">
        <div class="icon">🐾</div>
        <p>아직 등록된 반려견이 없어요.<br>첫 번째 반려견을 추가해보세요!</p>
        <button class="btn-add-pet" onclick="pscOpenPetForm()">＋ 반려견 추가</button>
    </div>
    <?php else : ?>
    <div class="psc-pets-grid" id="pscPetsGrid">
        <?php foreach ( $pets as $pet ) : ?>
            <?php echo psc_render_pet_card( $pet ); ?>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- 토스트 -->
    <div class="psc-toast" id="pscPetToast"></div>

    <script>
    (function () {
        const BREEDS     = <?php echo json_encode( psc_get_breed_list(), JSON_UNESCAPED_UNICODE ); ?>;
        const DNA_COLORS = ['<?php echo $primary; ?>','#f59e0b','#10b981','#ef4444','#8b5cf6'];
        const AJAX_URL   = '<?php echo esc_js( admin_url('admin-ajax.php') ); ?>';
        const NONCE_IMG  = '<?php echo esc_js( $nonce_img ); ?>';
        const NONCE_DEL  = '<?php echo esc_js( $nonce_del ); ?>';

        let mixRows          = [];
        let currentPhotoFile = null;

        /* ── 폼 열기/닫기 ── */
        window.pscOpenPetForm = function (petData) {
            const panel = document.getElementById('pscPetFormPanel');
            panel.classList.add('open');
            panel.scrollIntoView({ behavior:'smooth', block:'start' });

            if (!petData) {
                document.getElementById('pscFormTitle').textContent = '🐶 새 반려견 등록';
                document.getElementById('pscPetForm').reset();
                document.getElementById('petId').value        = '0';
                document.getElementById('petPhotoPreview').style.display     = 'none';
                document.getElementById('petPhotoPlaceholder').style.display = '';
                document.getElementById('petPhotoUrl').value   = '';
                document.getElementById('petPhotoAttId').value = '';
                currentPhotoFile = null;
                pscSetBreedType('pure');
                mixRows = [];
                pscRenderMixRows();
                pscAddMixRow();
            } else {
                document.getElementById('pscFormTitle').textContent  = '✏️ 반려견 정보 수정';
                document.getElementById('petId').value       = petData.id;
                document.getElementById('petName').value     = petData.pet_name;
                document.getElementById('petGender').value   = petData.gender;
                document.getElementById('petBirthday').value = petData.birthday || '';
                document.getElementById('petNeutered').value = petData.is_neutered;
                document.getElementById('petPhotoUrl').value = petData.photo_url || '';
                currentPhotoFile = null;

                if (petData.photo_url) {
                    document.getElementById('petPhotoPreview').src           = petData.photo_url;
                    document.getElementById('petPhotoPreview').style.display = 'block';
                    document.getElementById('petPhotoPlaceholder').style.display = 'none';
                } else {
                    document.getElementById('petPhotoPreview').style.display     = 'none';
                    document.getElementById('petPhotoPlaceholder').style.display = '';
                }

                let breedMix = [];
                try { breedMix = JSON.parse(petData.breed_mix || '[]'); } catch(e) {}

                if (breedMix.length === 1 && breedMix[0].breed === '알 수 없음') {
                    pscSetBreedType('unknown');
                } else if (breedMix.length === 1) {
                    pscSetBreedType('pure');
                    pscSelectBreed(breedMix[0].breed || '');
                } else if (breedMix.length > 1) {
                    pscSetBreedType('mix');
                    mixRows = breedMix.map(b => ({
                        breedVal : b.breed,
                        stars    : Math.max(1, Math.round(b.weight / 20))
                    }));
                    pscRenderMixRows();
                    pscUpdateDnaPreview();
                } else {
                    pscSetBreedType('pure');
                }
            }
        };

        window.pscClosePetForm = function () {
            document.getElementById('pscPetFormPanel').classList.remove('open');
        };

        /* ── 견종 타입 전환 ── */
        window.pscSetBreedType = function (type) {
            document.getElementById('breedTypePure').classList.toggle('active',    type === 'pure');
            document.getElementById('breedTypeMix').classList.toggle('active',     type === 'mix');
            document.getElementById('breedTypeUnknown').classList.toggle('active', type === 'unknown');

            document.getElementById('breedPureSection').style.display    = type === 'pure'    ? '' : 'none';
            document.getElementById('breedMixSection').style.display     = type === 'mix'     ? '' : 'none';
            document.getElementById('breedUnknownSection').style.display = type === 'unknown' ? '' : 'none';

            if (type === 'mix' && mixRows.length === 0) pscAddMixRow();
        };

        /* ── 순종 드롭다운 ── */
        window.pscFilterBreeds = function (val) {
            const dd       = document.getElementById('breedPureDD');
            const filtered = BREEDS.filter(b => b.includes(val));
            dd.innerHTML   = filtered.map(b =>
                `<div class="breed-option" data-value="${b}"
                      onclick="pscSelectBreed('${b.replace(/'/g,"\\'")}')">
                      ${b}</div>`
            ).join('');
            dd.classList.add('open');
        };

        window.pscOpenBreedDD = function () {
            pscFilterBreeds(document.getElementById('breedPureSearch').value);
        };

        window.pscSelectBreed = function (val) {
            document.getElementById('breedPureSearch').value = val;
            document.getElementById('breedPureValue').value  = val;
            document.getElementById('breedPureDD').classList.remove('open');
            const isCustom = (val === '기타 (직접 입력)');
            document.getElementById('breedPureCustom').style.display = isCustom ? '' : 'none';
        };

        document.addEventListener('click', function (e) {
            if (!e.target.closest('.breed-search-wrap')) {
                document.querySelectorAll('.breed-dropdown').forEach(d => d.classList.remove('open'));
            }
        });

        /* ── 믹스 견종 행 ── */
        window.pscAddMixRow = function () {
            if (mixRows.length >= 3) { pscToast('최대 3개 견종까지 추가할 수 있어요'); return; }
            mixRows.push({ breedVal:'', stars:3 });
            pscRenderMixRows();
            pscUpdateDnaPreview();
        };

        window.pscRemoveMixRow = function (idx) {
            mixRows.splice(idx, 1);
            pscRenderMixRows();
            pscUpdateDnaPreview();
        };

        window.pscSetMixStar = function (idx, star) {
            mixRows[idx].stars = star;
            pscRenderMixRows();
            pscUpdateDnaPreview();
        };

        window.pscSetMixBreed = function (idx, val) {
            mixRows[idx].breedVal = val;
            const inp = document.getElementById('mixBreedSearch_' + idx);
            if (inp) inp.value = val;
            const customInp = document.getElementById('mixBreedCustom_' + idx);
            if (customInp) customInp.style.display = (val === '기타 (직접 입력)') ? '' : 'none';
            const dd = document.getElementById('mixBreedDD_' + idx);
            if (dd) dd.classList.remove('open');
            pscUpdateDnaPreview();
        };

        function pscRenderMixRows() {
            const container = document.getElementById('dnaMixRows');
            const total     = mixRows.reduce((a, r) => a + r.stars, 0) || 1;
            container.innerHTML = mixRows.map((row, idx) => {
                const starHtml = [1,2,3,4,5].map(s =>
                    `<span class="dna-star ${row.stars >= s ? 'active' : ''}"
                           onclick="pscSetMixStar(${idx},${s})"
                           onmouseover="pscHoverStar(${idx},${s})"
                           onmouseout="pscUnhoverStar(${idx})">★</span>`
                ).join('');
                const pct          = Math.round(row.stars / total * 100);
                const breedOptions = BREEDS.map(b =>
                    `<div class="breed-option ${row.breedVal === b ? 'selected' : ''}"
                          onclick="pscSetMixBreed(${idx},'${b.replace(/'/g,"\\'")}')">
                          ${b}</div>`
                ).join('');
                return `
                <div class="dna-mix-item" id="mixRow_${idx}">
                    <div style="flex:1;min-width:180px;">
                        <div class="breed-search-wrap">
                            <input type="text" class="breed-search-input"
                                   id="mixBreedSearch_${idx}"
                                   value="${row.breedVal === '기타 (직접 입력)' ? '기타 (직접 입력)' : row.breedVal}"
                                   placeholder="견종 검색..."
                                   oninput="pscFilterMixBreeds(${idx},this.value)"
                                   onfocus="pscOpenMixDD(${idx})"
                                   autocomplete="off">
                            <div class="breed-dropdown" id="mixBreedDD_${idx}">${breedOptions}</div>
                        </div>
                        <input type="text" id="mixBreedCustom_${idx}"
                               placeholder="견종 직접 입력"
                               style="margin-top:6px;
                                      display:${row.breedVal === '기타 (직접 입력)' ? '' : 'none'};
                                      width:100%;padding:10px 12px;
                                      border:1.5px solid #e5e8eb;border-radius:10px;
                                      font-size:.88rem;box-sizing:border-box;
                                      background:#f4f6f9;font-family:'Pretendard',sans-serif;"
                               oninput="mixRows[${idx}].customVal=this.value">
                    </div>
                    <div class="dna-stars">${starHtml}</div>
                    <div class="dna-pct">${pct}%</div>
                    ${mixRows.length > 1
                        ? `<button class="btn-remove-breed" onclick="pscRemoveMixRow(${idx})" title="삭제">✕</button>`
                        : ''}
                </div>`;
            }).join('');
            document.getElementById('btnAddBreed').style.display = mixRows.length >= 3 ? 'none' : '';
        }

        window.pscFilterMixBreeds = function (idx, val) {
            const dd       = document.getElementById('mixBreedDD_' + idx);
            const filtered = BREEDS.filter(b => b.includes(val));
            dd.innerHTML   = filtered.map(b =>
                `<div class="breed-option ${mixRows[idx].breedVal === b ? 'selected' : ''}"
                      onclick="pscSetMixBreed(${idx},'${b.replace(/'/g,"\\'")}')">
                      ${b}</div>`
            ).join('');
            dd.classList.add('open');
        };

        window.pscOpenMixDD = function (idx) {
            pscFilterMixBreeds(idx, document.getElementById('mixBreedSearch_' + idx).value);
        };

        window.pscHoverStar = function (idx, star) {
            const row = document.getElementById('mixRow_' + idx);
            if (!row) return;
            row.querySelectorAll('.dna-star').forEach((s, i) => {
                s.style.color = i < star ? '#f59e0b' : '#e5e8eb';
            });
        };
        window.pscUnhoverStar = function (idx) {
            pscRenderMixRows();
            pscUpdateDnaPreview();
        };

        /* ── DNA 미리보기 ── */
        function pscUpdateDnaPreview() {
            const wrap   = document.getElementById('dnaPreviewWrap');
            const bar    = document.getElementById('dnaPreviewBar');
            const legend = document.getElementById('dnaPreviewLegend');
            const valid  = mixRows.filter(r =>
                (r.breedVal && r.breedVal !== '기타 (직접 입력)') || r.customVal
            );
            if (valid.length === 0) { wrap.style.display = 'none'; return; }
            wrap.style.display = '';
            const total = mixRows.reduce((a, r) => a + r.stars, 0) || 1;
            bar.innerHTML = mixRows.map((r, i) => {
                const label = r.breedVal === '기타 (직접 입력)' ? (r.customVal || '기타') : r.breedVal;
                if (!label) return '';
                const pct = Math.round(r.stars / total * 100);
                return `<div class="dna-segment"
                             style="width:${pct}%;background:${DNA_COLORS[i % DNA_COLORS.length]};"
                             title="${label} ${pct}%">
                             ${pct >= 15 ? pct + '%' : ''}</div>`;
            }).join('');
            legend.innerHTML = mixRows.map((r, i) => {
                const label = r.breedVal === '기타 (직접 입력)' ? (r.customVal || '기타') : r.breedVal;
                if (!label) return '';
                const pct = Math.round(r.stars / total * 100);
                return `<div class="dna-legend-item">
                    <div class="dna-dot" style="background:${DNA_COLORS[i % DNA_COLORS.length]};"></div>
                    <span>${label} <strong>${pct}%</strong></span>
                </div>`;
            }).join('');
        }

        /* ── 사진 미리보기 ── */
        window.pscPreviewPetPhoto = function (input) {
            if (!input.files || !input.files[0]) return;
            const file = input.files[0];
            if (file.size > 2 * 1024 * 1024) {
                pscToast('사진은 2MB 이하여야 해요'); input.value = ''; return;
            }
            currentPhotoFile = file;
            const reader = new FileReader();
            reader.onload = e => {
                document.getElementById('petPhotoPreview').src           = e.target.result;
                document.getElementById('petPhotoPreview').style.display = 'block';
                document.getElementById('petPhotoPlaceholder').style.display = 'none';
            };
            reader.readAsDataURL(file);
        };

        /* ── 저장 ── */
        window.pscSavePet = async function () {
            const name = document.getElementById('petName').value.trim();
            if (!name) { pscToast('이름을 입력해주세요'); return; }

            const saveBtn = document.querySelector('.btn-pet-save');
            saveBtn.disabled = true; saveBtn.textContent = '저장 중...';

            if (currentPhotoFile) {
                const fd  = new FormData();
                fd.append('action',    'psc_upload_pet_photo');
                fd.append('nonce',     NONCE_IMG);
                fd.append('pet_photo', currentPhotoFile);
                const res  = await fetch(AJAX_URL, { method:'POST', body:fd });
                const data = await res.json();
                if (data.success) {
                    document.getElementById('petPhotoUrl').value   = data.data.url;
                    document.getElementById('petPhotoAttId').value = data.data.att_id;
                } else {
                    pscToast('사진 업로드 실패: ' + (data.data || ''));
                    saveBtn.disabled = false; saveBtn.textContent = '저장하기';
                    return;
                }
            }

            const breedType =
                document.getElementById('breedTypePure').classList.contains('active')    ? 'pure'
              : document.getElementById('breedTypeMix').classList.contains('active')     ? 'mix'
              : 'unknown';

            let breedJson = '[]';

            if (breedType === 'pure') {
                let val = document.getElementById('breedPureValue').value;
                if (val === '기타 (직접 입력)')
                    val = document.getElementById('breedPureCustom').value.trim();
                if (val) breedJson = JSON.stringify([{ breed: val, weight: 100 }]);

            } else if (breedType === 'mix') {
                const total = mixRows.reduce((a, r) => a + r.stars, 0) || 1;
                const arr   = mixRows.map(r => {
                    const label = r.breedVal === '기타 (직접 입력)' ? (r.customVal || '기타') : r.breedVal;
                    return { breed: label, weight: Math.round(r.stars / total * 100) };
                }).filter(r => r.breed);
                if (arr.length > 0) {
                    const sum = arr.reduce((a, b) => a + b.weight, 0);
                    if (sum !== 100) arr[0].weight += (100 - sum);
                    breedJson = JSON.stringify(arr);
                }
            } else {
                breedJson = JSON.stringify([{ breed: '알 수 없음', weight: 100 }]);
            }

            const fd2 = new FormData();
            fd2.append('action',       'psc_save_pet');
            fd2.append('nonce',        document.getElementById('petNonce').value);
            fd2.append('pet_id',       document.getElementById('petId').value);
            fd2.append('pet_name',     name);
            fd2.append('gender',       document.getElementById('petGender').value);
            fd2.append('birthday',     document.getElementById('petBirthday').value);
            fd2.append('is_neutered',  document.getElementById('petNeutered').value);
            fd2.append('photo_url',    document.getElementById('petPhotoUrl').value);
            fd2.append('photo_att_id', document.getElementById('petPhotoAttId').value);
            fd2.append('breed_mix',    breedJson);

            const res2  = await fetch(AJAX_URL, { method:'POST', body:fd2 });
            const data2 = await res2.json();

            saveBtn.disabled = false; saveBtn.textContent = '저장하기';

            if (data2.success) {
                pscToast('저장되었어요 🐾');
                pscClosePetForm();
                pscRefreshGrid(data2.data);
            } else {
                pscToast('저장 실패: ' + (data2.data || '오류가 발생했어요'));
            }
        };

        /* ── 삭제 ── */
        window.pscDeletePet = function (petId) {
            if (!confirm('정말 삭제할까요?')) return;
            const fd = new FormData();
            fd.append('action', 'psc_delete_pet');
            fd.append('nonce',  NONCE_DEL);
            fd.append('pet_id', petId);
            fetch(AJAX_URL, { method:'POST', body:fd })
                .then(r => r.json())
                .then(d => {
                    if (d.success) {
                        document.getElementById('pet-card-' + petId)?.remove();
                        pscToast('삭제되었어요');
                        pscCheckEmpty();
                    } else {
                        pscToast('삭제 실패');
                    }
                });
        };

        /* ── 수정 ── */
        window.pscEditPet = function (petId) {
            const card = document.getElementById('pet-card-' + petId);
            if (!card) return;
            const petData = JSON.parse(card.dataset.pet || '{}');
            pscOpenPetForm(petData);
        };

        /* ── 그리드 갱신 ── */
        function pscRefreshGrid(petData) {
            let grid = document.getElementById('pscPetsGrid');
            if (!grid) {
                grid = document.createElement('div');
                grid.className = 'psc-pets-grid';
                grid.id        = 'pscPetsGrid';
                document.getElementById('pscPetsWrap').appendChild(grid);
            }
            document.getElementById('pscPetsEmpty')?.remove();

            const existing = document.getElementById('pet-card-' + petData.id);
            if (existing) {
                existing.outerHTML = pscBuildCardHtml(petData);
            } else {
                grid.insertAdjacentHTML('beforeend', pscBuildCardHtml(petData));
            }
            const titleEl = document.querySelector('.psc-pets-title span');
            if (titleEl) {
                titleEl.textContent = `(${document.querySelectorAll('.psc-pet-card').length}마리)`;
            }
        }

        function pscCheckEmpty() {
            const cards   = document.querySelectorAll('.psc-pet-card');
            const grid    = document.getElementById('pscPetsGrid');
            const titleEl = document.querySelector('.psc-pets-title span');
            if (cards.length === 0 && grid) {
                grid.remove();
                document.getElementById('pscPetsWrap').insertAdjacentHTML('beforeend',
                    `<div class="psc-pets-empty" id="pscPetsEmpty">
                        <div class="icon">🐾</div>
                        <p>아직 등록된 반려견이 없어요.<br>첫 번째 반려견을 추가해보세요!</p>
                        <button class="btn-add-pet" onclick="pscOpenPetForm()">＋ 반려견 추가</button>
                    </div>`);
            }
            if (titleEl) titleEl.textContent = `(${cards.length}마리)`;
        }

        /* ── 카드 HTML 빌더 (JS) ── */
        function pscBuildCardHtml(p) {
            const photoHtml = p.photo_url
                ? `<img class="pet-card-photo" src="${p.photo_url}" alt="${p.pet_name}">`
                : `<div class="pet-card-photo-placeholder">🐾</div>`;

            let breedMix = [];
            try { breedMix = JSON.parse(p.breed_mix || '[]'); } catch(e) {}

            let dnaHtml = '';
            if (breedMix.length === 1 && breedMix[0].breed === '알 수 없음') {
                dnaHtml = `<div style="font-size:.78rem;color:#8b95a1;margin-bottom:6px;">🐾 견종 미상</div>`;
            } else if (breedMix.length > 1) {
                const barSegs = breedMix.map((b, i) =>
                    `<div class="dna-segment"
                          style="width:${b.weight}%;background:${DNA_COLORS[i % DNA_COLORS.length]};"
                          title="${b.breed} ${b.weight}%">
                          ${b.weight >= 15 ? b.weight + '%' : ''}</div>`
                ).join('');
                const legends = breedMix.map((b, i) =>
                    `<div class="dna-legend-item">
                        <div class="dna-dot" style="background:${DNA_COLORS[i % DNA_COLORS.length]};"></div>
                        <span>${b.breed} <strong>${b.weight}%</strong></span>
                    </div>`
                ).join('');
                dnaHtml = `<div class="dna-bar-wrap">
                    <div class="dna-bar-label">🧬 DNA 구성</div>
                    <div class="dna-bar">${barSegs}</div>
                    <div class="dna-legend">${legends}</div>
                </div>`;
            } else if (breedMix.length === 1) {
                dnaHtml = `<div style="font-size:.78rem;color:#4e5968;margin-bottom:6px;">🐶 ${breedMix[0].breed}</div>`;
            }

            const genderLabel   = p.gender === 'male' ? '♂ 수컷' : p.gender === 'female' ? '♀ 암컷' : '성별 미상';
            const age           = p.birthday ? pscCalcAge(p.birthday) : '';
            const neuteredBadge = parseInt(p.is_neutered)
                ? '<span class="pet-meta-badge">중성화 ✂️</span>' : '';
            const petDataJson   = JSON.stringify(p).replace(/"/g, '&quot;');

            return `
            <div class="psc-pet-card" id="pet-card-${p.id}" data-pet="${petDataJson}">
                ${photoHtml}
                <div class="pet-card-body">
                    <div class="pet-card-name">${p.pet_name}</div>
                    <div class="pet-card-meta">
                        <span class="pet-meta-badge">${genderLabel}</span>
                        ${age ? `<span class="pet-meta-badge">🎂 ${age}</span>` : ''}
                        ${neuteredBadge}
                    </div>
                    ${dnaHtml}
                </div>
                <div class="pet-card-actions">
                    <button class="btn-pet-edit" onclick="pscEditPet(${p.id})">✏️ 수정</button>
                    <button class="btn-pet-del"  onclick="pscDeletePet(${p.id})">🗑️</button>
                </div>
            </div>`;
        }

        /* ── 나이 계산 ── */
        function pscCalcAge(birthday) {
            const birth  = new Date(birthday);
            const today  = new Date();
            let years    = today.getFullYear() - birth.getFullYear();
            let months   = today.getMonth()    - birth.getMonth();
            if (months < 0) { years--; months += 12; }
            if (years < 0) return '';
            if (years === 0) return months + '개월';
            return years + '살' + (months > 0 ? ' ' + months + '개월' : '');
        }

        /* ── 토스트 ── */
        window.pscToast = function (msg) {
            const t = document.getElementById('pscPetToast');
            t.textContent = msg;
            t.classList.add('show');
            setTimeout(() => t.classList.remove('show'), 2500);
        };

    })();
    </script>

    </div><!-- /.psc-pets-wrap -->
    <?php
}

/* ============================================================
   4. 카드 렌더 헬퍼 (PHP — 초기 로드용)
   ============================================================ */
function psc_render_pet_card( object $pet ): string {
    $primary    = '#224471';
    $dna_colors = [$primary, '#f59e0b', '#10b981', '#ef4444', '#8b5cf6'];
    $breedMix   = [];
    if ( $pet->breed_mix ) {
        $decoded = json_decode( $pet->breed_mix, true );
        if ( is_array($decoded) ) $breedMix = $decoded;
    }

    $photo_html = $pet->photo_url
        ? '<img class="pet-card-photo" src="' . esc_url($pet->photo_url) . '" alt="' . esc_attr($pet->pet_name) . '">'
        : '<div class="pet-card-photo-placeholder">🐾</div>';

    $dna_html = '';
    if ( count($breedMix) === 1 && $breedMix[0]['breed'] === '알 수 없음' ) {
        $dna_html = '<div style="font-size:.78rem;color:#8b95a1;margin-bottom:6px;">🐾 견종 미상</div>';

    } elseif ( count($breedMix) > 1 ) {
        $bar_segs = '';
        $legends  = '';
        foreach ( $breedMix as $i => $b ) {
            $color     = $dna_colors[$i % count($dna_colors)];
            $bar_segs .= sprintf(
                '<div class="dna-segment" style="width:%d%%;background:%s;" title="%s %d%%">%s</div>',
                $b['weight'], $color, esc_attr($b['breed']), $b['weight'],
                $b['weight'] >= 15 ? $b['weight'].'%' : ''
            );
            $legends .= sprintf(
                '<div class="dna-legend-item">
                    <div class="dna-dot" style="background:%s;"></div>
                    <span>%s <strong>%d%%</strong></span>
                </div>',
                $color, esc_html($b['breed']), $b['weight']
            );
        }
        $dna_html = '<div class="dna-bar-wrap">'
                  . '<div class="dna-bar-label">🧬 DNA 구성</div>'
                  . '<div class="dna-bar">' . $bar_segs . '</div>'
                  . '<div class="dna-legend">' . $legends . '</div>'
                  . '</div>';

    } elseif ( count($breedMix) === 1 ) {
        $dna_html = '<div style="font-size:.78rem;color:#4e5968;margin-bottom:6px;">🐶 '
                  . esc_html($breedMix[0]['breed']) . '</div>';
    }

    $age_str = '';
    if ( $pet->birthday ) {
        $birth = new DateTime( $pet->birthday );
        $today = new DateTime();
        $diff  = $today->diff($birth);
        if ( $diff->y > 0 ) {
            $age_str = $diff->y . '살' . ( $diff->m > 0 ? ' ' . $diff->m . '개월' : '' );
        } elseif ( $diff->m > 0 ) {
            $age_str = $diff->m . '개월';
        }
    }

    $gender_label   = $pet->gender === 'male' ? '♂ 수컷' : ( $pet->gender === 'female' ? '♀ 암컷' : '성별 미상' );
    $neutered_badge = $pet->is_neutered ? '<span class="pet-meta-badge">중성화 ✂️</span>' : '';
    $age_badge      = $age_str ? '<span class="pet-meta-badge">🎂 ' . esc_html($age_str) . '</span>' : '';
    $pet_json       = esc_attr( wp_json_encode( (array)$pet ) );

    return "
    <div class=\"psc-pet-card\" id=\"pet-card-{$pet->id}\" data-pet=\"{$pet_json}\">
        {$photo_html}
        <div class=\"pet-card-body\">
            <div class=\"pet-card-name\">" . esc_html($pet->pet_name) . "</div>
            <div class=\"pet-card-meta\">
                <span class=\"pet-meta-badge\">{$gender_label}</span>
                {$age_badge}
                {$neutered_badge}
            </div>
            {$dna_html}
        </div>
        <div class=\"pet-card-actions\">
            <button class=\"btn-pet-edit\" onclick=\"pscEditPet({$pet->id})\">✏️ 수정</button>
            <button class=\"btn-pet-del\"  onclick=\"pscDeletePet({$pet->id})\">🗑️</button>
        </div>
    </div>";
}

/* ============================================================
   5. AJAX — 반려견 저장
   ============================================================ */
add_action( 'wp_ajax_psc_save_pet', 'psc_ajax_save_pet' );
function psc_ajax_save_pet(): void {
    check_ajax_referer( 'psc_pet_save', 'nonce' );
    $user_id = get_current_user_id();
    if ( ! $user_id ) wp_send_json_error( '로그인 필요' );

    global $wpdb;
    $table = $wpdb->prefix . PSC_PETS_TABLE;

    $pet_id       = (int) ( $_POST['pet_id']      ?? 0 );
    $pet_name     = sanitize_text_field( $_POST['pet_name']     ?? '' );
    $gender       = sanitize_key(        $_POST['gender']        ?? 'unknown' );
    $birthday     = sanitize_text_field( $_POST['birthday']     ?? '' );
    $is_neutered  = (int)               ( $_POST['is_neutered']  ?? 0 );
    $photo_url    = esc_url_raw(         $_POST['photo_url']    ?? '' );
    $photo_att_id = (int)               ( $_POST['photo_att_id'] ?? 0 );
    $breed_mix    = wp_kses_post(        $_POST['breed_mix']    ?? '[]' );

    if ( ! $pet_name ) wp_send_json_error( '이름을 입력해주세요' );
    if ( ! in_array( $gender, ['male','female','unknown'], true ) ) $gender = 'unknown';
    if ( $birthday && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthday) ) $birthday = '';

    $data = [
        'user_id'      => $user_id,
        'pet_name'     => $pet_name,
        'gender'       => $gender,
        'birthday'     => $birthday ?: null,
        'is_neutered'  => $is_neutered,
        'photo_url'    => $photo_url  ?: null,
        'photo_att_id' => $photo_att_id ?: null,
        'breed_mix'    => $breed_mix,
        'updated_at'   => current_time('mysql'),
    ];

    if ( $pet_id > 0 ) {
        $owner = $wpdb->get_var( $wpdb->prepare(
            "SELECT user_id FROM {$table} WHERE id = %d", $pet_id
        ) );
        if ( (int)$owner !== $user_id ) wp_send_json_error( '권한 없음' );
        $wpdb->update( $table, $data, ['id' => $pet_id] );
    } else {
        $data['created_at'] = current_time('mysql');
        $wpdb->insert( $table, $data );
        $pet_id = $wpdb->insert_id;
    }

    $saved = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$table} WHERE id = %d", $pet_id
    ) );
    wp_send_json_success( $saved );
}

/* ============================================================
   6. AJAX — 반려견 삭제
   ============================================================ */
add_action( 'wp_ajax_psc_delete_pet', 'psc_ajax_delete_pet' );
function psc_ajax_delete_pet(): void {
    check_ajax_referer( 'psc_pet_delete', 'nonce' );
    $user_id = get_current_user_id();
    if ( ! $user_id ) wp_send_json_error( '로그인 필요' );

    global $wpdb;
    $table  = $wpdb->prefix . PSC_PETS_TABLE;
    $pet_id = (int)( $_POST['pet_id'] ?? 0 );
    if ( ! $pet_id ) wp_send_json_error( '잘못된 요청' );

    $owner = $wpdb->get_var( $wpdb->prepare(
        "SELECT user_id FROM {$table} WHERE id = %d", $pet_id
    ) );
    if ( (int)$owner !== $user_id ) wp_send_json_error( '권한 없음' );

    $att_id = (int)$wpdb->get_var( $wpdb->prepare(
        "SELECT photo_att_id FROM {$table} WHERE id = %d", $pet_id
    ) );
    if ( $att_id ) wp_delete_attachment( $att_id, true );

    $wpdb->delete( $table, ['id' => $pet_id, 'user_id' => $user_id] );
    wp_send_json_success();
}

/* ============================================================
   7. AJAX — 사진 업로드
   ============================================================ */
add_action( 'wp_ajax_psc_upload_pet_photo', 'psc_ajax_upload_pet_photo' );
function psc_ajax_upload_pet_photo(): void {
    check_ajax_referer( 'psc_pet_photo', 'nonce' );
    $user_id = get_current_user_id();
    if ( ! $user_id ) wp_send_json_error( '로그인 필요' );
    if ( empty($_FILES['pet_photo']) ) wp_send_json_error( '파일 없음' );

    $file    = $_FILES['pet_photo'];
    $allowed = ['image/jpeg','image/png','image/gif','image/webp'];
    $finfo   = finfo_open(FILEINFO_MIME_TYPE);
    $mime    = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if ( ! in_array($mime, $allowed, true) ) wp_send_json_error( '허용되지 않는 파일 형식' );
    if ( $file['size'] > 2 * 1024 * 1024 )  wp_send_json_error( '파일 크기는 2MB 이하여야 해요' );

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg';
    $_FILES['pet_photo']['name'] = 'pet-' . $user_id . '-' . time() . '.' . $ext;

    $att_id = media_handle_upload('pet_photo', 0);
    if ( is_wp_error($att_id) ) wp_send_json_error( $att_id->get_error_message() );

    wp_send_json_success([
        'url'    => wp_get_attachment_url($att_id),
        'att_id' => $att_id,
    ]);
}
