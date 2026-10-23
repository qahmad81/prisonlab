const App = {
    models: [],
    reasoningSelections: {prisoner:{},interrogator:{}},
    states: [],
    cases: [],
    investigators: [],
    participantProfile: null,
    keyBalance: null,
    selectedInvestigatorPersona: 'ethan_cole',
    logs: [],
    env: {},
    currentGender: 'male',
    currentCharacterSlug: 'char_1',
    selectedCaseFilename: '',
    previewCaseFilename: '',
    selectedModelId: '',
    currentModelTab: 'focus_economic',
    modelSort: 'recommended',
    aiPaused: false,
    modelSearchQuery: '',
    caseSearchQuery: '',
    currentSession: null,
    userApiKey: '',
    csrfToken: '',
    publishedLogs: [],
    interrogationMode: 'human',
    selectedInterrogatorId: '',
    modelTarget: 'prisoner',
    busy: false,
    generation: 0,
    generating: false,
    stopRequested: false,

    async fetchData(url,options={},timeoutMs=30000) {
        const controller=new AbortController(),timer=setTimeout(()=>controller.abort(),timeoutMs);
        try{const response=await fetch(url,{...options,signal:controller.signal});const data=await response.json();
            if(!data.ok)throw new Error(data.error || 'تعذر تحميل البيانات؛ أعد المحاولة.');return data;
        }catch(e){if(e.name==='AbortError')throw new Error('انتهت مهلة الاتصال بالخادم. أعد تحميل الصفحة أو حاول مجددًا.');throw e;}
        finally{clearTimeout(timer);}
    },

    async init() {
        // Load user API key from sessionStorage
        this.userApiKey = sessionStorage.getItem('user_openrouter_api_key') || '';
        this.updateKeyButtonState();

        const headers = {};
        if (this.userApiKey) {
            headers['X-User-OpenRouter-Key'] = this.userApiKey;
        }

        const data = await this.fetchData('api.php?action=init_data', { headers });

        this.models = data.models || [];
        this.states = data.states || [];
        this.cases = data.cases || [];
        this.investigators = data.investigators || [];
        // The server owns the standalone browser identity and CSRF protection.
        this.participantProfile = data.participant_profile || null;

        this.logs = data.logs || [];
        this.env = data.env || {};
        this.csrfToken = data.csrf_token;
        this.publishedLogs = data.published_logs || [];
        this.discoveryMeta = data.discovery_meta || {};

        document.getElementById('sense-model-pill').textContent = this.env.sense_model || 'typesafe/jev-1.14';
        document.getElementById('compact-model-pill').textContent = this.env.compaction_model || '';
        document.getElementById('models-count-pill').textContent = this.models.length;

        // Default Case
        if (this.cases.length > 0) {
            this.selectedCaseFilename = this.cases[0].filename;
            this.previewCaseFilename = this.cases[0].filename;
            this.setCaseCharacter(this.cases[0]);
        }

        // Default Model
        const preferred = [
            'google/gemini-2.5-flash-lite',
            'mistralai/mistral-small-3.2-24b-instruct',
            'mistralai/mistral-small-24b-instruct-2501',
            'openai/gpt-4o-mini',
            'deepseek/deepseek-chat'
        ];
        const focusPreferred=this.models.filter(m=>m.curated_tier==='focus_economic' && !m.is_locked)
            .sort((a,b)=>(a.usage_rank || 99999)-(b.usage_rank || 99999) || Number(b.created || 0)-Number(a.created || 0))[0];
        let foundPreferred = focusPreferred || preferred.map(id => this.models.find(m => m.id === id && !m.is_locked)).find(Boolean);
        if (!foundPreferred) {
            foundPreferred = this.models.find(m => !m.is_locked) || this.models[0];
        }
        if (foundPreferred) {
            this.selectedModelId = foundPreferred.id;
        }

        this.selectedInterrogatorId = focusPreferred?.id || this.selectedModelId;
        this.renderSelectedCaseSummary();
        this.renderSelectedModelSummary();
        this.setInterrogationMode(this.interrogationMode);
        this.renderLogsList();
        this.bindEvents();
        this.updateControls();
        this.renderAccount();if(this.userApiKey)this.refreshKeyBalance();
        document.getElementById('archive-modal-search').addEventListener('input', () => this.renderArchive());
        document.getElementById('model-sort').addEventListener('change',e=>{this.modelSort=e.target.value;this.renderModalModels();});
        document.querySelectorAll('.modal-overlay').forEach(el => el.addEventListener('click', e => {if(e.target === el) el.classList.remove('active');}));
        document.addEventListener('keydown', e => {if(e.key === 'Escape') document.querySelectorAll('.modal-overlay.active').forEach(el=>el.classList.remove('active'));});
    },

    bindEvents() {
        document.getElementById('start-session-btn').addEventListener('click', () => this.startNewSession());
        document.getElementById('send-btn').addEventListener('click', () => this.sendMessage());
        document.getElementById('compact-btn').addEventListener('click', () => this.compactNow());

        // Case Modal Search
        document.getElementById('case-modal-search').addEventListener('input', (e) => {
            this.caseSearchQuery = e.target.value.trim().toLowerCase();
            this.renderCaseCards();
        });

        const modelDialog=document.getElementById('model-modal'),modelGrid=document.getElementById('modal-models-grid');
        modelGrid.tabIndex=0;modelGrid.setAttribute('aria-label','قائمة النماذج القابلة للتمرير');
        modelDialog.addEventListener('wheel',e=>{
            if(!modelDialog.classList.contains('active') || e.ctrlKey || !e.deltaY)return;
            let target=e.target.closest('.model-modal-toolbar,.modal-models-grid') || modelGrid;
            const unit=e.deltaMode===1?18:e.deltaMode===2?target.clientHeight:1;
            if(target.scrollHeight<=target.clientHeight)target=modelGrid;
            e.preventDefault();target.scrollTop+=e.deltaY*unit;
        },{passive:false});
        modelGrid.addEventListener('pointerdown',e=>{if(!e.target.closest('button,input,select,summary,a'))modelGrid.focus({preventScroll:true});});
        // Model Modal Search
        document.getElementById('model-modal-search').addEventListener('input', (e) => {
            this.modelSearchQuery = e.target.value.trim().toLowerCase();
            this.renderModalModels();
        });

        document.getElementById('user-input').addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
            }
        });

        document.querySelectorAll('.tactic-chip').forEach(chip => {
            chip.addEventListener('click', () => {
                const input = document.getElementById('user-input');
                input.value = chip.dataset.prompt;
                input.focus();
            });
        });
    },

    // ==========================================
    // Selected Case Management
    // ==========================================
    getSelectedCase() {
        return this.cases.find(c => c.filename === this.selectedCaseFilename) || this.cases[0];
    },

    setCaseCharacter(c) {
        if (!c) return;
        this.currentCharacterSlug = c.character_slug || c.gender || 'char_1';
        this.currentGender = c.gender || 'male';

        const st = this.currentSession ? this.currentSession.current_state : (c.initial_state || 'calm');
        this.updatePortrait(this.currentCharacterSlug, st, this.currentSession?.custom_state_text || '');
        this.renderStatesGallery(st);
    },

    renderSelectedCaseSummary() {
        const c = this.getSelectedCase();
        const container = document.getElementById('selected-case-summary');
        if (!c) {
            container.innerHTML = '<span style="color:var(--text-muted);font-size:0.8rem;">لم يتم اختيار قضية بعد.</span>';
            return;
        }

        const stateId = c.initial_state || 'calm';
        const stateAr = c.initial_state_ar || 'هادئ';
        const imgPath = `assets/images/${c.character_slug || c.gender}/${stateId}.jpg`;

        container.innerHTML = `
            <img src="${imgPath}" class="selected-case-avatar" alt="${this.escapeHtml(c.character_name)}" onerror="this.src='assets/images/char_1/calm.jpg'">
            <span class="selected-case-info">
                <span class="selected-case-name">${this.escapeHtml(c.character_name)}</span>
                <span class="selected-case-title">${this.escapeHtml(c.title)}</span>
                <span class="case-summary-tags">
                    <span class="case-card-state">الحالة الابتدائية: ${this.escapeHtml(stateAr)}</span>
                    ${c.difficulty?`<span class="case-card-state">المستوى ${c.difficulty.level}/10 · ${this.escapeHtml(c.difficulty.level_ar)}</span>`:''}
                    <span style="font-size:0.7rem; color:#38bdf8;">🔄 تغيير القضية</span>
                </span>
            </span>
        `;
    },

    // ==========================================
    // Case Selector Modal
    // ==========================================
    openCaseModal() {
        this.previewCaseFilename = this.selectedCaseFilename || (this.cases[0] ? this.cases[0].filename : '');
        this.caseSearchQuery = '';
        document.getElementById('case-modal-search').value = '';
        document.getElementById('case-modal').classList.add('active');
        this.renderCaseCards();
        this.renderCasePreviewDetail(this.previewCaseFilename);
    },

    closeCaseModal() {
        document.getElementById('case-modal').classList.remove('active');
    },

    selectModalCase(filename) {
        this.previewCaseFilename = filename;
        document.querySelectorAll('.case-card').forEach(el => {
            el.classList.toggle('active', el.dataset.filename === filename);
        });
        this.renderCasePreviewDetail(filename);
    },

    renderCaseCards() {
        const container = document.getElementById('case-cards-container');
        container.innerHTML = '';

        const q = this.caseSearchQuery;
        const filtered = this.cases.filter(c => {
            if (!q) return true;
            return (c.character_name && c.character_name.toLowerCase().includes(q)) ||
                   (c.title && c.title.toLowerCase().includes(q)) ||
                   (c.accusation && c.accusation.toLowerCase().includes(q)) ||
                   (c.content && c.content.toLowerCase().includes(q));
        });

        if (filtered.length === 0) {
            container.innerHTML = '<div style="color:var(--text-muted);padding:20px;text-align:center;grid-column:1/-1;">لا توجد قضايا مطابقة للبحث.</div>';
            return;
        }

        filtered.forEach(c => {
            const card = document.createElement('div');
            const isActive = (c.filename === this.previewCaseFilename);
            card.className = 'case-card' + (isActive ? ' active' : '');
            card.dataset.filename = c.filename;

            const stateId = c.initial_state || 'calm';
            const imgPath = `assets/images/${c.character_slug || c.gender}/${stateId}.jpg`;

            card.innerHTML = `
                <img src="${imgPath}" class="case-card-avatar" alt="${this.escapeHtml(c.character_name)}" onerror="this.src='assets/images/char_1/calm.jpg'">
                <div class="case-card-info">
                    <div class="case-card-name">${this.escapeHtml(c.character_name)}</div>
                    <div class="case-card-title">${this.escapeHtml(c.title)}</div>
                    <span class="case-card-state">تبدأ بـ: ${this.escapeHtml(c.initial_state_ar || stateId)}</span>
                </div>
            `;
            card.addEventListener('click', () => this.selectModalCase(c.filename));
            container.appendChild(card);
        });
    },

    renderCasePreviewDetail(filename) {
        const c = this.cases.find(item => item.filename === filename) || this.cases[0];
        if (!c) return;

        const stateId = c.initial_state || 'calm';
        const stateAr = c.initial_state_ar || 'هادئ';
        const imgPath = `assets/images/${c.character_slug || c.gender}/${stateId}.jpg`;

        const portraitImg = document.getElementById('modal-detail-portrait');
        portraitImg.src = imgPath;
        portraitImg.onerror = () => { portraitImg.src = 'assets/images/char_1/calm.jpg'; };

        document.getElementById('modal-detail-name').textContent = c.character_name || c.title;
        document.getElementById('modal-detail-sub').textContent = c.accusation ? `التهمة: ${c.accusation}` : c.title;
        document.getElementById('modal-detail-state-pill').textContent = `الحالة الابتدائية المحددة: ${stateAr} (${stateId})`;
        document.getElementById('modal-detail-content').textContent = c.content;
    },

    confirmSelectedCase() {
        this.selectedCaseFilename = this.previewCaseFilename;
        this.renderSelectedModelSummary();
        const c = this.getSelectedCase();
        if (c) {
            this.setCaseCharacter(c);
            this.renderSelectedCaseSummary();
        }
        this.closeCaseModal();
    },

    // ==========================================
    // Selected Model Management
    // ==========================================
    getSelectedModel() {
        return this.models.find(m => m.id === this.selectedModelId) || this.models[0];
    },

    renderSelectedModelSummary() {
        const m = this.getSelectedModel();
        const container = document.getElementById('selected-model-card');
        const tip = document.getElementById('model-status-tip');
        if (!m) return;

        container.classList.toggle('connection-weak',!!m.health?.weak);
        const pIn = m.pricing?.prompt_per_1m ?? 0;
        const pOut = m.pricing?.completion_per_1m ?? 0;
        const isLocked = !!m.is_locked;

        let statusBadge = '';
        if (isLocked) {
            if (this.userApiKey) {
                statusBadge = '<span class="model-card-badge badge-userkey">✓ مفعل بمفتاحك</span>';
                tip.innerHTML = '<span style="color:#10b981;">✓ موديول متقدم (مفعّل باستخدام مفتاحك الخاص).</span>';
            } else {
                statusBadge = '<span class="model-card-badge badge-locked">🔒 متقدم (أكثر من $1.00)</span>';
                tip.innerHTML = '<span style="color:#f87171;">🔒 موديول متقدم مقفل (يتطلب مفتاحك الخاص لتجربته على نفقتك).</span>';
            }
        } else {
            statusBadge = '<span class="model-card-badge badge-unlocked">⚡ خفيف / اقتصادي</span>';
            tip.innerHTML = this.userApiKey ? '<span style="color:#10b981;">✓ سيُستخدم مفتاحك الشخصي لهذا النموذج؛ لا يوجد حد يومي للتجارب.</span>' : '<span style="color:#94a3b8;">✓ موديول خفيف متاح على نفقة المشغّل.</span>';
        }

        this.renderReasoningControl('prisoner',m);
        container.innerHTML = `
            <span class="selected-model-icon">
                ${m.icon_svg || '🤖'}
            </span>
            <span class="selected-model-info">
                <span class="model-summary-heading">
                    <span class="selected-model-name">${this.escapeHtml(m.name)}</span>
                    ${statusBadge}
                </span>
                <span class="selected-model-meta">
                    <span>${this.escapeHtml(m.context_formatted || '128K')} سياق</span>
                    <span>•</span>
                    <span>إدخال: $${pIn} / إخراج: $${pOut} / مليون</span>
                    <span>•</span>
                    <span style="color:#38bdf8; font-weight:700;">تغيير 🔄</span>
                </span>
            </span>
        `;
    },

    // ==========================================
    // Model Selector Modal
    // ==========================================
    openModelModal(target = 'prisoner') {
        this.modelTarget = target;
        document.getElementById('connection-toast').hidden=true;
        document.getElementById('model-modal-description').textContent=target==='interrogator'?'اختبار الموديل في دور المحقق · درجات ومراجعات هذا الدور':'اختبار الموديل في دور المتهم · درجات ومراجعات هذا الدور';
        this.currentModelTab='focus_economic';
        document.querySelectorAll('.model-tab').forEach(b=>b.classList.toggle('active',b.dataset.tab==='focus_economic'));
        this.modelSearchQuery = '';
        document.getElementById('model-modal-search').value = '';
        document.getElementById('model-modal').classList.add('active');
        this.renderModalModels();
    },

    closeModelModal() {
        document.getElementById('model-modal').classList.remove('active');
    },

    filterModelTab(tab) {
        this.currentModelTab = tab;
        document.querySelectorAll('.model-tab').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.tab === tab);
        });
        this.renderModalModels();
    },

    renderModalModels() {
        const container = document.getElementById('modal-models-grid');
        container.innerHTML = '';

        // Counts
        const category = m => Number(m.pricing?.prompt_per_1m)===0 && Number(m.pricing?.completion_per_1m)===0 ? 'free' : m.is_locked ? 'professional' : 'economic';
        const inTab=(m,tab)=>tab==='all' || (tab.startsWith('focus_')?m.curated_tier===tab:category(m)===tab && (tab!=='economic' || !m.curated_tier));
        for(const tab of ['focus_economic','focus_medium','focus_high','economic','professional','all','free'])document.getElementById('tab-count-'+tab).textContent=this.models.filter(m=>inTab(m,tab)).length;
        const source=document.getElementById('ranking-source');source.replaceChildren();
        const asOf=this.discoveryMeta?.ranking_meta?.as_of;
        source.append(asOf ? `استخدام آخر 7 أيام · ${new Date(asOf).toLocaleDateString('ar')} · المصدر: ` : 'ترتيب الحداثة حسب تاريخ الإصدار؛ بيانات الاستخدام غير متاحة · ');
        const link=document.createElement('a');link.href='https://openrouter.ai/rankings';link.textContent='OpenRouter';link.target='_blank';link.rel='noopener';source.append(link);
        if(asOf)source.append(' · CC BY 4.0');

        const q = this.modelSearchQuery;
        const tab = this.currentModelTab;

        const filtered = this.models.filter(m => {
            // Tab filter
            if (!inTab(m,tab)) return false;

            // Search filter
            if (!q) return true;
            return m.id.toLowerCase().includes(q) ||
                   m.name.toLowerCase().includes(q) ||
                   (m.provider_slug && m.provider_slug.toLowerCase().includes(q)) ||
                   (m.description_ar && m.description_ar.toLowerCase().includes(q));
        }).sort((a,b)=>this.modelOrder(b)-this.modelOrder(a) || a.name.localeCompare(b.name));

        if (filtered.length === 0) {
            container.innerHTML = '<div style="color:var(--text-muted);padding:30px;text-align:center;grid-column:1/-1;">لا توجد نماذج مطابقة لمعايير البحث.</div>';
            return;
        }

        filtered.forEach(m => {
            const card = document.createElement('div');
            const isSelected = (m.id === (this.modelTarget === 'interrogator' ? this.selectedInterrogatorId : this.selectedModelId));
            const isLocked = !!m.is_locked;

            let cardClass = 'model-card';
            if (isSelected) cardClass += ' active';
            if (isLocked && !this.userApiKey) cardClass += ' locked';
            if (isLocked && this.userApiKey) cardClass += ' key-funded';
            const quality=m.performance?.[this.modelTarget];
            const bands=[m.health?.reliability_band,quality?.quality_band];
            const band=bands.includes('orange')?'orange':bands.includes('yellow')?'yellow':bands.includes('green')?'green':null;
            if(band)cardClass+=' reliability-'+band;
            card.className = cardClass;

            const pIn = m.pricing?.prompt_per_1m ?? 0;
            const pOut = m.pricing?.completion_per_1m ?? 0;

            let badgeHtml = '';
            if (isLocked) {
                if (this.userApiKey) {
                    badgeHtml = '<span class="model-card-badge badge-userkey">✓ مفتاحك مفعل</span>';
                } else {
                    badgeHtml = '<span class="model-card-badge badge-locked">🔒 متقدم (&gt; $1.00)</span>';
                }
            } else {
                badgeHtml = `<span class="model-card-badge badge-unlocked">${category(m)==='free'?'مجاني':'مدفوع اقتصادي'}</span>`;
            }

            const descText = m.description_ar || `${m.name} نموذج ذكاء اصطناعي عبر منصة OpenRouter.`;

            card.innerHTML = `
                <div class="model-card-icon">
                    ${m.icon_svg || '🤖'}
                </div>
                <div class="model-card-body">
                    <div class="model-card-header-row">
                        <span class="model-card-name" title="${this.escapeHtml(m.id)}">${this.escapeHtml(m.name)}</span>
                        ${badgeHtml}
                    </div>
                    <div class="model-card-desc" title="${this.escapeHtml(descText)}">
                        ${this.escapeHtml(descText)}
                    </div>
                    <div class="model-card-footer">
                        <span class="model-card-price">$${pIn} In / $${pOut} Out / مليون</span>
                        <span class="reasoning-badge">${LabView.escape(LabView.reasoningLabel(this.reasoningPreview(this.modelTarget,m)))}</span><span class="model-card-context">${m.context_formatted || '128K'}</span>
                    </div>
                    <div class="model-discovery-meta">${m.created ? new Date(m.created*1000).toLocaleDateString('ar') : 'تاريخ غير متوفر'}${m.usage_rank ? ` · الاستخدام #${m.usage_rank}` : ''}</div>
                    ${m.curated_family?`<div class="curated-family">${this.escapeHtml(m.curated_family)} · إصدار رسمي مختار</div>`:''}
                    ${this.modelPerformanceHTML(m)}
                    <div class="model-health">${this.modelHealthText(m)}</div>
                </div>
            `;

            card.addEventListener('click', e => {if(!e.target.closest('details'))this.selectModalModel(m.id);});
            container.appendChild(card);
        });
    },

    modelOrder(m) {
        const created=Number(m.created || 0), rank=Number(m.usage_rank || 0);
        if(this.modelSort==='reliable') {
            const h=m.health || {},n=(h.successes || 0)+(h.failures || 0);
            return n ? ((h.successes || 0)+2)/(n+4) : -1;
        }
        if(this.modelSort==='newest')return created;
        if(this.modelSort==='popular')return rank ? 10000-rank : 0;
        const days=created ? Math.max(0,(Date.now()/1000-created)/86400) : 10000;
        return Math.exp(-days/240)*.45 + (rank ? 1/Math.sqrt(rank) : 0)*.55;
    },
    modelPerformanceHTML(m) {
        const p=m.performance?.[this.modelTarget];
        if(!p)return '<div class="model-performance">لا توجد درجات لهذا الدور بعد · ابدأ اختبارًا</div>';
        return `<div class="model-performance"><strong>${this.modelTarget==='interrogator'?'أداء المحقق':'أداء المتهم'}: ${p.judge_average==null?'لم يُحكّم':p.judge_average+'/100'}</strong> · ${p.assessments} اختبار<br>المستخدمون: ${p.user_average==null?'لا مراجعات':p.user_average+'/5'} · ${p.user_reviews} مراجعة${p.limited_sample?'<br>عينة محدودة':''}${Object.entries(p.badges || {}).map(([type,n])=>`<span class="incident-badge">${LabView.incidentLabels[type] || this.escapeHtml(type)} ×${n}</span>`).join('')}${p.quality_band==='orange' || p.quality_band==='yellow'?'<small>لون تنبيه بسبب درجات الأداء؛ قياسات الاتصال منفصلة أدناه.</small>':''}${p.contexts?.length?`<details class="model-user-reviews"><summary>سياق الاختبارات</summary>${p.contexts.map(c=>`<p>مستوى ${c.level ?? 'غير محفوظ'} · معيار ${this.escapeHtml(c.rubric_version || 'قديم')} · ${c.count} اختبار · ${c.average}/100</p>`).join('')}</details>`:''}${p.recent_reviews?.length?`<details class="model-user-reviews"><summary>مراجعات المستخدمين</summary>${p.recent_reviews.map(r=>`<p><strong>${r.rating}/5</strong> ${this.escapeHtml(r.text)}</p>`).join('')}</details>`:''}</div>`;
    },
    modelHealthText(m) {
        const h=m.health || {}, parts=[];
        parts.push(`الاستدعاءات ${h.requests ?? 0} · نجح ${h.successes ?? 0} · فشل ${h.failures ?? 0}`);
        if(h.failure_rate!=null)parts.push(`نسبة الفشل ${h.failure_rate}%`);
        if(h.cancelled)parts.push(`أُلغي بعد اكتمال النصاب ${h.cancelled}`);
        if(h.invalid_responses)parts.push(`من الفشل: ${h.invalid_responses} رد غير صالح`);
        if(h.failure_rate!=null && h.sample_confidence==='limited')parts.push('عينة محدودة؛ ليست ضمانًا للاعتمادية');
        if(h.weak)parts.push('⚠ '+(h.reasons || []).map(x=>LabView.escape(x)).join(' · '));
        if(h.first_byte_ms!=null)parts.push(`أول بايت ${h.first_byte_ms}ms`);
        if(h.avg_seconds!=null)parts.push(`متوسط الطلب ${h.avg_seconds}ث`);
        if(h.tokens_per_second!=null)parts.push(`${h.tokens_per_second} توكن/ث إجمالي`);
        if(h.failure_rate==null)parts.push('لم يُقَس اتصال مكتمل بعد');
        return parts.join(' · ');
    },

    selectModalModel(modelId) {
        const m = this.models.find(item => item.id === modelId);
        if (!m) return;

        if (m.is_locked && !this.userApiKey) {
            alert('عذراً لا تتوفر لدينا ميزانية لفتح هذه الموديولات، أرجو أن تستخدم مفتاحك في زر (استخدام مفتاحي) في الأعلى لتختبر هذا الموديول على نفقتك الخاصة.. ملاحظة: المفاتيح الخاصة تحفظ أثناء الزيارة فقط على متصفحك ولا يتم تخزين أي مفاتيح للزوار هنا.');
            this.closeModelModal();
            this.openKeyModal();
            return;
        }

        if (this.modelTarget === 'interrogator') this.selectedInterrogatorId = modelId;
        else this.selectedModelId = modelId;
        this.renderSelectedModelSummary();
        this.renderInterrogatorSummary();
        this.closeModelModal();
    },

    // ==========================================
    // User OpenRouter API Key
    // ==========================================
    openKeyModal() {
        document.getElementById('custom-api-key-input').value = this.userApiKey;
        document.getElementById('key-modal').classList.add('active');
    },

    closeKeyModal() {
        document.getElementById('key-modal').classList.remove('active');
    },

    async saveUserKey() {
        const val = document.getElementById('custom-api-key-input').value.trim();
        if (val && !val.startsWith('sk-or-')) {
            alert('يرجى التأكد من إدخال مفتاح OpenRouter صالح (يبدأ عادة بـ sk-or-v1-).');
            return;
        }
        const old=this.userApiKey;this.userApiKey = val;
        if(val){try{this.keyBalance=(await this.api('key_balance')).balance;}catch(e){this.userApiKey=old;return this.notify('تعذر التحقق من المفتاح: '+e.message,true);}}
        if (val) {
            sessionStorage.setItem('user_openrouter_api_key', val);
            this.notify('تم التحقق من مفتاحك. التجارب الجديدة ستكون على نفقتك؛ لا يوجد حد يومي للتجارب.');
        } else {
            this.keyBalance=null;sessionStorage.removeItem('user_openrouter_api_key');
        }
        this.updateKeyButtonState();
        this.closeKeyModal();
        this.renderSelectedModelSummary();
        this.renderAccount();this.renderInterrogatorSummary();this.renderModalModels();
    },

    clearUserKey() {
        this.userApiKey = '';
        this.keyBalance=null;
        sessionStorage.removeItem('user_openrouter_api_key');
        document.getElementById('custom-api-key-input').value = '';
        this.updateKeyButtonState();
        this.closeKeyModal();
        this.renderSelectedModelSummary();
        this.renderAccount();this.renderInterrogatorSummary();this.renderModalModels();this.notify('أُزيل المفتاح. التجارب الجديدة تستخدم مفتاح المشغّل؛ التجارب الشخصية السابقة تحتاج مفتاحها نفسه لمتابعتها.');
    },

    updateKeyButtonState() {
        const btn = document.getElementById('open-key-modal-btn');
        if (this.userApiKey) {
            const amount=this.keyBalance?.available_to_spend;
            btn.textContent='🔑 مفتاحي مفعل ✓'+(amount!=null?' '+Number(amount).toFixed(2)+'$':' · الرصيد غير متاح');
            btn.classList.add('has-key');
        } else {
            btn.innerHTML = '🔑 استخدام مفتاحي';
            btn.classList.remove('has-key');
        }
    },

    // ==========================================
    // Visual Gallery & Portrait
    // ==========================================
    renderStatesGallery() { /* Atlas intentionally removed from the interface. */ },

    updatePortrait(slug, stateId, customStateText) {
        const stateObj = this.states.find(s => s.id === stateId) || this.states[0];
        const validId = stateObj ? stateObj.id : 'calm';
        const img = document.getElementById('prisoner-portrait');
        img.src = `assets/images/${slug}/${validId}.jpg`;
        img.onerror = () => { img.src = `assets/images/char_1/${validId}.jpg`; };

        const pill = document.getElementById('current-state-pill');
        if (customStateText) {
            pill.textContent = `${customStateText} (حالة مخصصة)`;
            pill.style.background = '#9333ea';
        } else if (stateObj) {
            pill.textContent = `${stateObj.name_ar} (${stateObj.name_en})`;
            pill.style.background = stateObj.color || '#3b82f6';
        }

        document.querySelectorAll('.state-thumb').forEach(el => el.classList.remove('active'));
        const idx = this.states.findIndex(s => s.id === validId);
        if (idx >= 0 && document.querySelectorAll('.state-thumb')[idx]) {
            document.querySelectorAll('.state-thumb')[idx].classList.add('active');
        }
    },

    updateSenseMeters(metrics, directive, targetState) {
        if (!metrics) return;
        const setBar = (id, val) => {
            const bar = document.getElementById(`bar-${id}`);
            const num = document.getElementById(`val-${id}`);
            if (bar) bar.style.width = `${val}%`;
            if (num) num.textContent = `${val}%`;
        };
        setBar('fear', metrics.fear || 0);
        setBar('anger', metrics.anger || 0);
        setBar('trust', metrics.trust || 0);
        setBar('collapse', metrics.collapse || 0);
        setBar('breaking', metrics.breaking_point || 0);

        const target=this.states.find(s=>s.id===targetState);
        document.getElementById('sense-directive-text').textContent=target ? `الحالة التي يفرضها JEV: ${target.name_ar}. هذه مؤشرات مستقلة وليست نسبًا يجب أن مجموعها 100%. ارتفاع الخوف أو الإنهاك لا يثبت وقوع اعتراف.` : 'تظهر قراءة JEV المستقلة مع كل رد. ارتفاع الانهيار لا يعني الاعتراف.';
    },

    // ==========================================
    // Interrogation Session Execution
    // ==========================================

    async api(action, payload = {}, retryContext = {}) {
        const canRetry=['send_message','ai_vs_ai_step','reverse_question','reverse_answer','retry_confession','evaluate_session','compact_now'].includes(action);
        if(action==='compact_now' && !payload.request_id)payload={...payload,request_id:this.requestId()};
        const body=JSON.stringify({action,...payload});
        const busyDeadline=Date.now()+1320000;
        const firstAttempt=retryContext.priorFailure?1:0;
        if(firstAttempt) {this.notify('تعذر فحص الإنهاء أو الاعتراف بعد حفظ الرد. المحاولة الثانية بعد 10 ثوانٍ.',true);await this.retryWait(10000);}
        for(let attempt=firstAttempt;attempt<3;attempt++) {
            try {
                let res;
                try {res=await fetch('api.php',{method:'POST',signal:AbortSignal.timeout(1320000),headers:{'Content-Type':'application/json','X-CSRF-Token':this.csrfToken,...(this.userApiKey?{'X-User-OpenRouter-Key':this.userApiKey}:{})},body});}
                catch(e) {throw Object.assign(new Error('تعذر الاتصال بالخادم.'),{retryable:true});}
                let data;
                try {data=await res.json();}catch {throw Object.assign(new Error('تعذر قراءة استجابة الخادم.'),{retryable:res.status>=500});}
                if(res.status===409 && data.busy && canRetry && payload.session_id) {
                    this.notify('الخادم ما زال يُكمل الطلب السابق؛ ننتظره دون تكرار الاستدعاء المدفوع.');
                    let progress;
                    do {
                        if(Date.now()>busyDeadline)throw new Error('ما زال الطلب قيد التنفيذ. المراحل محفوظة؛ أعد فتح الجلسة لاحقًا.');
                        await this.retryWait(3000);
                        progress=await this.fetchData('api.php?action=load_session&session_id='+encodeURIComponent(payload.session_id));
                    }while(progress.processing);
                    // Replay the identical ID only after release; server idempotence returns saved work.
                    attempt--;continue;
                }
                if(!data.ok)throw Object.assign(new Error(data.error || 'تعذر تنفيذ الطلب'),{retryable:!!data.retryable || [502,503,504].includes(res.status)});
                if(action==='evaluate_session' && data.session?.evaluation_status==='failed') {
                    this.currentSession=data.session;
                    if(data.session.evaluation_retryable)throw Object.assign(new Error(data.session.evaluation_error),{retryable:true});
                }
                if(this.userApiKey && data.session && ['send_message','ai_vs_ai_step','reverse_question','reverse_answer','evaluate_session','compact_now'].includes(action))void this.refreshKeyBalance();
                return data;
            } catch(e) {
                if(!canRetry || !e.retryable || attempt===2) {if(canRetry && e.retryable && attempt===2)e.message='فشلت محاولات الاتصال الثلاث. '+e.message;throw e;}
                const delay=(attempt+1)*10000;
                this.notify(`${e.message} إعادة المحاولة ${attempt+2} من 3 بعد ${delay/1000} ثانية؛ المراحل المكتملة محفوظة.`,true);
                await this.retryWait(delay);
            }
        }
    },
    reasoningSelection(role) {
        const id=role==='prisoner'?this.selectedModelId:this.selectedInterrogatorId;
        return this.reasoningSelections[role][id] || 'auto';
    },
    reasoningPreview(role,m) {
        const c=m?.reasoning_capabilities || {}, choice=this.reasoningSelections[role][m?.id] || 'auto';
        if(!c.efforts?.length)return {supported:false,has_reasoning:c.has_reasoning};
        if(choice!=='auto' && c.efforts.includes(choice))return {supported:true,effort:choice};
        const wanted=role==='prisoner'?this.getSelectedCase()?.difficulty?.thinking:this.investigators.find(p=>p.id===this.selectedInvestigatorPersona)?.thinking;
        const ranks=['none','minimal','low','medium','high','xhigh','max'];
        const effort=wanted==='highest'?c.efforts.at(-1):c.efforts.reduce((a,b)=>Math.abs(ranks.indexOf(b)-ranks.indexOf(wanted))<Math.abs(ranks.indexOf(a)-ranks.indexOf(wanted))?b:a,c.efforts[0]);
        return {supported:true,effort};
    },
    renderReasoningControl(role,m) {
        const button=document.getElementById(role+'-reasoning-btn');
        if(!button)return;
        const adjustable=!!m?.reasoning_capabilities?.efforts?.length;
        button.textContent=LabView.reasoningLabel(this.reasoningPreview(role,m))+(adjustable?' · تعديل ↗':'');
        button.disabled=!m || !adjustable;
        button.title='إعداد التجربة التالية؛ لا يغيّر الجلسة الحالية';
    },
    openReasoningModal(role) {
        const id=role==='prisoner'?this.selectedModelId:this.selectedInterrogatorId,m=this.models.find(m=>m.id===id);
        const efforts=m?.reasoning_capabilities?.efforts || [];if(!efforts.length)return;
        this.reasoningReturnFocus=document.activeElement;
        const box=document.getElementById('reasoning-options');box.replaceChildren();
        document.getElementById('reasoning-model-name').textContent=m.name;
        for(const choice of ['auto',...efforts]) {
            const b=document.createElement('button');b.type='button';b.className='reasoning-option';b.dataset.effort=choice;
            b.textContent=choice==='auto'?'تلقائي · حسب مستوى الشخصية':LabView.reasoningNames[choice];
            b.setAttribute('aria-pressed',String(this.reasoningSelection(role)===choice));
            b.addEventListener('click',()=>{this.reasoningSelections[role][id]=choice;this.startRequestId=null;this.renderSelectedModelSummary();this.renderInterrogatorSummary();this.closeReasoningModal();});box.appendChild(b);
        }
        document.getElementById('connection-toast').hidden=true;
        const modal=document.getElementById('reasoning-modal');
        modal.onkeydown=e=>{if(e.key==='Escape'){e.preventDefault();this.closeReasoningModal();}if(e.key==='Tab'){const items=[...modal.querySelectorAll('button')],first=items[0],last=items.at(-1);if(e.shiftKey && document.activeElement===first){e.preventDefault();last.focus();}else if(!e.shiftKey && document.activeElement===last){e.preventDefault();first.focus();}}};
        modal.classList.add('active');box.querySelector('button').focus();
    },
    closeReasoningModal() {document.getElementById('reasoning-modal').classList.remove('active');this.reasoningReturnFocus?.focus();},
    retryWait(ms) {return new Promise(resolve=>setTimeout(resolve,ms));},
    notify(text, error = false) {
        const el=document.getElementById('session-notice'); el.textContent=text; el.classList.toggle('error-text',error);
        const toast=document.getElementById('connection-toast');toast.textContent=text;toast.hidden=false;toast.classList.toggle('is-error',error);
        clearTimeout(this.noticeTimer);if(!error)this.noticeTimer=setTimeout(()=>{toast.hidden=true;},6000);
    },
    setInterrogationMode(mode) {
        this.interrogationMode=mode;
        for(const id of ['human','ai','reverse']) {
            const button=document.getElementById('mode-btn-'+id);
            button.classList.toggle('active',id===mode);button.setAttribute('aria-pressed',String(id===mode));
        }
        const humanPrisoner=mode==='reverse',humanInvestigator=mode==='human';
        document.getElementById('interrogator-model-group').hidden=humanInvestigator;
        document.getElementById('interrogator-persona-group').hidden=humanInvestigator;
        document.getElementById('interrogator-level-hint').hidden=humanInvestigator;
        document.getElementById('human-investigator-character').hidden=!humanInvestigator;
        document.getElementById('prisoner-model-group').hidden=humanPrisoner;
        document.getElementById('prisoner-human-card').hidden=!humanPrisoner;
        document.getElementById('interrogator-human-card').hidden=!humanInvestigator;
        document.getElementById('reverse-role-brief').hidden=!humanPrisoner;
        for(const [role,isHuman] of [['prisoner',humanPrisoner],['interrogator',humanInvestigator]]) {
            document.getElementById(role+'-representation-label').textContent=isHuman?'أنت · مشارك بشري':'ذكاء اصطناعي';
            document.getElementById(role+'-performer-type').textContent=isHuman?'المشارك البشري':'نموذج ذكاء اصطناعي';
            this.renderHumanPerformer(document.getElementById(role+'-human-card'));
        }
        this.renderSelectedModelSummary();
        this.renderInterrogatorSummary();
        document.getElementById('swap-roles-btn').title=mode==='ai'?'تبديل نموذجَي الذكاء الاصطناعي بين الدورين':'تبديل دورك ونقل نموذج الذكاء الاصطناعي إلى الدور المقابل';
    },
    swapRoles() {
        if(this.busy || this.generating)return;
        [this.reasoningSelections.prisoner,this.reasoningSelections.interrogator]=[this.reasoningSelections.interrogator,this.reasoningSelections.prisoner];
        [this.selectedModelId,this.selectedInterrogatorId]=[this.selectedInterrogatorId,this.selectedModelId];
        this.setInterrogationMode(this.interrogationMode==='ai'?'ai':this.interrogationMode==='human'?'reverse':'human');
        this.updateControls();
    },
    renderHumanPerformer(container) {
        container.innerHTML='<span class="participant-avatar participant-placeholder" aria-hidden="true">👤</span><span class="participant-info"><strong>أنت · المشارك البشري</strong><small>نسخة مستقلة · السجلات مرتبطة بهذا المتصفح</small></span>';
    },
    renderInterrogatorSummary() {
        const m=this.models.find(m=>m.id===this.selectedInterrogatorId);
        const card=document.getElementById('selected-interrogator-card');
        card.classList.toggle('connection-weak',!!m?.health?.weak);
        card.innerHTML=m ? `<span class="selected-model-icon">${m.icon_svg || '🤖'}</span><span class="selected-model-info"><span class="model-summary-heading"><strong class="selected-model-name">${this.escapeHtml(m.name)}</strong><span class="model-change-label">تغيير ↗</span></span><span class="selected-model-meta"><span>${this.escapeHtml(m.context_formatted || '')} سياق</span><span>إدخال $${m.pricing.prompt_per_1m} · إخراج $${m.pricing.completion_per_1m} / مليون</span></span><span class="model-card-badge ${m.is_locked && !this.userApiKey?'badge-locked':this.userApiKey?'badge-userkey':'badge-unlocked'}">${m.is_locked?'نموذج احترافي':'خفيف / اقتصادي'}</span></span>` : 'اختر نموذج المحقق';
        this.renderReasoningControl('interrogator',m);
        const p=this.investigators.find(p=>p.id===this.selectedInvestigatorPersona);
        document.getElementById('selected-investigator-persona').innerHTML=p?`${LabView.investigatorAvatar(p)}<span><strong dir="ltr">${this.escapeHtml(p.name)}</strong><small>المستوى ${p.level}/10 · ${this.escapeHtml(p.level_ar)}</small><small>${this.escapeHtml(p.specialty)}</small></span><span>تغيير ←</span>`:'اختر شخصية المحقق';
    },
    openInvestigatorModal() {
        document.getElementById('connection-toast').hidden=true;
        const box=document.getElementById('investigator-profile-grid');box.replaceChildren();
        for(const p of this.investigators) {
            const card=document.createElement('article');card.className='investigator-card';
            card.innerHTML=`${LabView.investigatorAvatar(p,'profile-portrait')}<div class="investigator-file"><span class="investigator-rank">${String(p.number).padStart(2,'0')} · المستوى ${p.level}/10 · ${this.escapeHtml(p.level_ar)}</span><h3 dir="ltr">${this.escapeHtml(p.name)}</h3><p>${p.age} عامًا · ${p.years_experience} سنة خبرة</p><strong>${this.escapeHtml(p.specialty)}</strong><p>${this.escapeHtml(p.bio)}</p><p>${this.escapeHtml(p.style)}</p><div class="investigator-skills">${p.skills.map(s=>`<span>${this.escapeHtml(s)}</span>`).join('')}</div><details><summary>حدود المستوى وتعليمات الشخصية</summary><p>${this.escapeHtml(p.limits)}</p><p>${this.escapeHtml(p.instructions)}</p><small>التفكير المطلوب: ${this.escapeHtml(({none:'معدوم إن أمكن',low:'قليل',medium:'متوسط',high:'مرتفع',highest:'أعلى مستوى متاح'})[p.thinking])} · حسب دعم النموذج.</small></details><button type="button" class="btn btn-secondary" data-investigator-id="${p.id}">${p.id===this.selectedInvestigatorPersona?'✓ المحدد حاليًا':'اختيار المحقق'}</button></div>`;
            const faces=document.createElement('details');faces.innerHTML='<summary>معاينة التعابير الستة</summary><div class="investigator-expressions">'+Object.entries(LabView.investigatorExpressions).map(([id,name])=>`<figure>${LabView.investigatorAvatar(p,'',id)}<figcaption>${name}</figcaption></figure>`).join('')+'</div>';card.querySelector('.investigator-file').insertBefore(faces,card.querySelector('button'));
            card.querySelector('button').addEventListener('click',()=>{this.selectedInvestigatorPersona=p.id;this.renderInterrogatorSummary();document.getElementById('investigator-profile-modal').classList.remove('active');});
            box.appendChild(card);
        }
        document.getElementById('investigator-profile-modal').classList.add('active');
    },
    async requestReverseQuestion() {
        if(this.busy || !this.currentSession || this.currentSession.interrogator_type!=='reverse' || this.currentSession.is_ended || this.currentSession.read_only)return;
        this.busy=true;this.updateControls();this.notify('المحقق يراجع ملف القضية ويجهز سؤاله…');
        try {this.currentSession=(await this.api('reverse_question',{session_id:this.currentSession.session_id})).session;this.renderActiveSession();this.notify('دورك: أجب بصفتك المتهم في القضية.');}
        catch(e){this.notify(e.message+' يمكنك إعادة طلب السؤال من الزر أسفل الحوار.',true);}
        finally{this.busy=false;this.renderActiveSession();}
        if(this.currentSession?.is_ended && this.currentSession.evaluation_status==='pending')await this.endAndEvaluateSession();
    },
    async startNewSession() {
        if (this.busy || this.generating) return;
        this.busy=true; this.updateControls();
        try {
            const c=this.getSelectedCase();
            this.startRequestId ||= this.requestId();
            const data=await this.api('start_session',{request_id:this.startRequestId,case_file:c.filename,model:this.selectedModelId,
                interrogator_type:this.interrogationMode,interrogator_model:this.selectedInterrogatorId,interrogator_character:this.selectedInvestigatorPersona,prisoner_effort:this.reasoningSelection('prisoner'),interrogator_effort:this.reasoningSelection('interrogator')});
            this.generation++; this.currentSession=data.session; this.stopRequested=false;this.aiPaused=false;
            this.startRequestId=null;this.renderAccount();
            this.renderActiveSession(); await this.reloadLogsOnly(); this.notify('بدأت الجلسة. حد الاستجواب 30 ردًا للسجين.');
        } catch(e) {this.notify(e.message,true);}
        finally {this.busy=false;this.updateControls();}
        if(this.currentSession?.interrogator_type==='ai' && !this.currentSession.is_ended && !this.generating) this.generateAiRounds();
        if(this.currentSession?.interrogator_type==='reverse')await this.requestReverseQuestion();
    },
    async reloadLogsOnly() {
        const d=await this.fetchData('api.php?action=init_data');
        if(d.ok) {this.logs=d.logs;this.publishedLogs=d.published_logs;this.csrfToken=d.csrf_token;this.models=d.models;this.participantProfile=d.participant_profile;this.renderAccount();this.renderLogsList();}
    },
    async loadSession(id) {
        if(this.busy || this.generating) return this.notify('انتظر انتهاء الجولة أو أنهِ التشغيل قبل فتح سجل آخر.',true);
        try {
            const d=await (await fetch('api.php?action=load_session&session_id='+encodeURIComponent(id))).json();
            if(!d.ok) throw Error(d.error);
            this.currentSession=d.session;this.generation++;this.currentCharacterSlug=d.session.character_slug;
            this.aiPaused=d.session.interrogator_type==='ai' && !d.session.is_ended;
            this.currentGender=d.session.gender;this.selectedModelId=d.session.model;
            this.renderStatesGallery(d.session.current_state);
            this.selectedInterrogatorId=d.session.interrogator_model;this.selectedCaseFilename=d.session.case_file;
            this.selectedInvestigatorPersona=d.session.interrogator_character?.id || 'ethan_cole';
            this.setInterrogationMode(d.session.interrogator_type);
            if (d.session.pending_question && d.session.interrogator_type === 'human') document.getElementById('user-input').value = d.session.pending_question;
            this.renderSelectedCaseSummary();this.renderSelectedModelSummary();this.renderActiveSession();this.closeArchiveModal();
            this.notify(d.session.read_only?'عرض جلسة منشورة للقراءة فقط.':'تم تحميل الجلسة.');
        } catch(e) {this.notify(e.message,true);}
    },
    updateControls() {
        const s=this.currentSession, ai=s?.interrogator_type==='ai', reverse=s?.interrogator_type==='reverse', ended=s?.is_ended, ro=s?.read_only;
        document.getElementById('start-session-btn').disabled=this.busy || this.generating;
        document.getElementById('swap-roles-btn').disabled=this.busy || this.generating;
        const logout=document.getElementById('logout-btn');if(logout)logout.disabled=this.busy || this.generating;
        document.getElementById('start-session-btn').textContent='بدء تجربة جديدة';
        document.getElementById('human-input-bar').style.display=ai?'none':'';
        document.getElementById('human-tactics-bar').style.display=ai || reverse?'none':'';
        document.getElementById('ai-vs-ai-controls').style.display=ai?'':'none';
        const waiting=reverse && !s?.awaiting_question?.sense;
        document.getElementById('send-btn').disabled=this.busy || !!ended || !!ro || !!waiting;
        document.getElementById('user-input').disabled=this.busy || !!ended || !!ro || !!waiting;
        document.getElementById('send-btn').textContent=reverse?'إرسال إجابتي ⬅':'إرسال وممارسة الضغط ⬅';
        document.getElementById('user-input').placeholder=reverse?'أجب عن سؤال المحقق بصفتك المتهم في القضية…':'اكتب كلامك للسجين هنا…';
        document.getElementById('reverse-resume-btn').hidden=!reverse || !!ended || !!ro || !!s?.awaiting_question?.sense;
        document.getElementById('reverse-resume-btn').disabled=this.busy;
        document.getElementById('compact-btn').disabled=!s || this.busy || this.generating || !!ended || !!ro;
        const end=document.getElementById('end-session-btn');
        end.disabled=!s || this.busy || !!ro || s.evaluation_status==='complete';
        const endTest=document.getElementById('end-test-btn');endTest.disabled=end.disabled;endTest.hidden=!!s?.evaluation;
        endTest.textContent=this.generating?'إيقاف التوليد بعد الجولة الحالية وطلب التحكيم ⏹':'إنهاء الاختبار وطلب التحكيم والعلامة ⏹';
        end.textContent=this.generating?'⏹ إيقاف بعد الجولة الحالية والتقييم':ended?'إعادة محاولة التقييم':'⏹ إنهاء الجلسة والتقييم';
        if(ai) {
            document.getElementById('resume-investigation-btn').hidden=!this.aiPaused || this.generating || this.busy || !!ended || !!ro;
            document.getElementById('ai-turn-counter').textContent=`الجولات المحفوظة ${s.turns_count} / 30${this.generating?' · جارٍ إنشاء الجولة التالية…':''}`;
        }
    },
    renderActiveSession() {
        const s=this.currentSession;if(!s)return;
        const chatBox=document.getElementById('chat-messages');
        const followBottom=chatBox.scrollHeight-chatBox.scrollTop-chatBox.clientHeight<100;
        const previousScroll=chatBox.scrollTop;
        const visible=s.messages;
        const last=visible.filter(m=>m.role==='assistant').at(-1);
        document.getElementById('active-session-id').textContent=`الجولات ${s.turns_count} / 30`;
        this.updatePortrait(s.character_slug,last?.visual_state || s.initial_state,'');
        document.getElementById('chat-messages').replaceChildren();
        const levels=document.createElement('div');levels.className='session-levels';levels.innerHTML=LabView.levels(s)+(s.closure_policy?'<span>الإنهاء متاح للطرفين · الهدف 5–15 جولة · يراجع JEV الطلب وتقيّم اللجنة توقيته.</span>':'');document.getElementById('chat-messages').appendChild(levels);
        if(!s.is_ended)document.getElementById('chat-messages').appendChild(LabCommunity.result(s));
        for(const m of visible)this.appendMessageDOM(m);
        if(s.interrogator_type==='reverse' && s.awaiting_question && !s.is_ended) {
            this.appendMessageDOM({role:'user',content:s.awaiting_question.text,interrogator_expression:s.awaiting_question.expression,turn:s.awaiting_question.turn,timestamp:s.awaiting_question.timestamp});
            const sense=s.awaiting_question.sense;
            if(sense) {
                const p=document.createElement('p');p.className='reverse-prompt';p.textContent='توجيه تمثيل الدور من JEV (لا يسمعه المحقق): '+sense.directive;
                document.getElementById('chat-messages').appendChild(p);
            }
        }
        if(!visible.length && !s.awaiting_question) {
            const p=document.createElement('p');p.className='session-empty';p.textContent=s.interrogator_type==='ai'?'المحقق يدرس القضية. سيظهر الحوار هنا تلقائياً عند اكتمال كل جولة.':'تم فتح غرفة التحقيق. ابدأ بسؤال محدد من القضية.';
            document.getElementById('chat-messages').appendChild(p);
        }
        if(last?.sense_metrics)this.updateSenseMeters(last.sense_metrics,last.sense_directive,last.jev_target_state);
        else {this.updateSenseMeters({fear:0,anger:0,trust:0,collapse:0,breaking_point:0},'تظهر قراءة JEV مع كل رد معروض.');}
        if(s.interrogator_type==='reverse')document.getElementById('sense-directive-text').textContent='توجيه JEV لتمثيل الشخصية في المحاكاة، وليس قياسًا لمشاعرك أو كشفًا للكذب.';
        if(s.is_ended) {
            const card=LabView.evaluation(s);
            if(!s.read_only && s.evaluation_status==='complete') {
                // Publication controls, provisional ranking and privacy confirmation are shown together above.
            }
            if(!s.read_only && s.evaluation_status!=='complete') {
                const b=document.createElement('button');b.className='btn btn-secondary';b.textContent=this.busy?'اللجنة تراجع الحوار…':'إكمال التقييم';b.disabled=this.busy;
                b.addEventListener('click',()=>this.endAndEvaluateSession());card.appendChild(b);
            }
            document.getElementById('chat-messages').appendChild(card);
            document.getElementById('chat-messages').appendChild(LabCommunity.result(s));
            document.getElementById('chat-messages').appendChild(LabView.benchmark(s));
        }
        document.getElementById('usage-summary').textContent=LabView.usage(s);
        const memory=document.getElementById('memory-notes'),visibleTurn=s.turns_count;
        const notes=Object.values(s.memory_notes || {}).filter(n=>n.audience==='spoken' && n.turn<=visibleTurn);
        memory.hidden=!notes.length;memory.innerHTML=`<summary>ملاحظات التحقيق المحفوظة (${notes.length})</summary>${notes.map(n=>`<p>الجولة ${n.turn}: ${LabView.text(n.note)}</p>`).join('')}`;
        const box=document.getElementById('chat-messages');box.scrollTop=followBottom?box.scrollHeight:previousScroll;this.updateControls();
    },
    appendMessageDOM(m) {document.getElementById('chat-messages').appendChild(LabView.message(m,this.currentSession));},
    requestId() {return crypto.randomUUID().replaceAll('-','');},
    async takeRound(message='') {
        const s=this.currentSession;
        const storageKey='pending_request_'+s.session_id;
        let pending=JSON.parse(sessionStorage.getItem(storageKey) || 'null');
        if(!pending || pending.turn!==s.turns_count)pending={id:this.requestId(),turn:s.turns_count};
        sessionStorage.setItem(storageKey,JSON.stringify(pending));
        const d=await this.api(s.interrogator_type==='reverse'?'reverse_answer':s.interrogator_type==='ai'?'ai_vs_ai_step':'send_message',{
            session_id:s.session_id,message,request_id:pending.id,expected_turns:pending.turn});
        this.currentSession=d.session;sessionStorage.removeItem(storageKey);
        if(d.session.interrogator_type!=='ai') {document.getElementById('user-input').value='';}
        this.renderActiveSession();
        if(d.session.confession_error)await this.checkConfession(true);
        return this.currentSession;
    },
    async checkConfession(priorFailure=false) {
        const d=await this.api('retry_confession',{session_id:this.currentSession.session_id},{priorFailure});
        this.currentSession=d.session;this.renderActiveSession();
    },
    async sendMessage() {
        if(this.busy || this.generating)return;
        const input=document.getElementById('user-input'),text=input.value.trim();if(!text)return;
        if(!this.currentSession)await this.startNewSession();
        if(!this.currentSession || this.currentSession.interrogator_type==='ai' || this.currentSession.is_ended || this.currentSession.read_only)return;
        this.busy=true;this.updateControls();this.notify('يحلل JEV الضغط النفسي ثم يرد السجين…');
        try {
            if(this.currentSession.confession_error)await this.checkConfession();
            if(!this.currentSession.is_ended)await this.takeRound(text);
            input.value='';
            this.notify('حُفظت الجولة.');this.renderActiveSession();await this.reloadLogsOnly();
        } catch(e) {this.notify(e.message,true);}
        finally {this.busy=false;this.updateControls();}
        if(this.currentSession.is_ended && this.currentSession.evaluation_status==='pending')await this.endAndEvaluateSession();
        else if(this.currentSession.interrogator_type==='reverse' && !this.currentSession.awaiting_question)await this.requestReverseQuestion();
    },
    async generateAiRounds() {
        if(this.generating || this.busy || !this.currentSession || this.currentSession.is_ended || this.currentSession.read_only)return;
        this.generating=true;this.stopRequested=false;this.aiPaused=false;this.updateControls();
        try {
            if(this.currentSession.confession_error)await this.checkConfession();
            while(!this.stopRequested && !this.currentSession.is_ended) {
                await this.takeRound();this.renderActiveSession();await this.reloadLogsOnly();
                this.notify('حُفظت الجولة وظهر الحوار تلقائياً. جارٍ إكمال المحاكاة…');
            }
        } catch(e) {this.aiPaused=true;this.notify('توقف التوليد: '+e.message+' اضغط «إكمال التحقيق» لإعادة المحاولة من المرحلة المحفوظة.',true);await this.reloadLogsOnly().catch(()=>{});}
        finally {this.generating=false;this.updateControls();}
        if(this.currentSession.is_ended || this.stopRequested) await this.endAndEvaluateSession();
    },
    resumeInvestigation() {
        if(this.busy || this.generating || !this.currentSession || this.currentSession.read_only)return;
        if(this.currentSession.is_ended)return this.endAndEvaluateSession();
        this.notify('جارٍ إكمال التحقيق من آخر مرحلة محفوظة…');this.generateAiRounds();
    },
    watchJury(sessionId) {
        const box=document.getElementById('jury-live-status');let done=false,inFlight=false,wait=null,progress={};
        box.hidden=false;
        const draw=()=>{
            if(done)return;
            if(!wait){box.textContent='جارٍ تجهيز اللجنة والتحقق من أسعارها…';return;}
            const remaining=Math.max(0,Math.ceil((wait.deadline_at*1000-Date.now())/1000));
            const received=Object.values(progress).filter(p=>p.status==='complete').length;
            box.replaceChildren();
            const title=document.createElement('strong');title.textContent=`تحكيم ${wait.tier==='economic'?'اقتصادي':wait.tier==='medium'?'متوسط':'احترافي'} · ${received}/3 تقارير`;
            const timer=document.createElement('bdi');timer.className='jury-countdown';timer.dataset.remaining=remaining;timer.textContent=remaining+' ث';
            const detail=document.createElement('span');detail.textContent=wait.phase==='finished' || remaining===0?'يحسم JEV التقارير الصالحة ويجهز النتيجة…':'عند انتهاء المهلة تُعتمد التقارير الصالحة ولو كان تقريرًا واحدًا؛ تكتمل اللجنة مبكرًا إذا وصلت الثلاثة.';
            box.append(title,timer,detail);
        };
        const poll=async()=>{
            if(done || inFlight)return;inFlight=true;
            try{const d=await this.fetchData('api.php?action=load_session&session_id='+encodeURIComponent(sessionId),{},5000);
                if(!done){wait=d.session.jury_wait || null;progress=d.session.judge_progress || {};draw();}}
            catch(e){if(!done && !wait)box.textContent='جارٍ التحكيم؛ تعذر تحديث العداد مؤقتًا. النتيجة ستظهر عند اكتمال الطلب.';}
            finally{inFlight=false;}
        };
        draw();poll();const ticker=setInterval(draw,250),poller=setInterval(poll,1500);
        return ()=>{done=true;clearInterval(ticker);clearInterval(poller);box.hidden=true;box.replaceChildren();};
    },
    async endAndEvaluateSession() {
        if(this.generating) {this.stopRequested=true;this.notify('سيتوقف التوليد بعد حفظ الجولة الحالية، ثم تبدأ اللجنة.');return;}
        if(this.busy || !this.currentSession || this.currentSession.read_only)return;
        if(!this.currentSession.turns_count)return this.notify('أكمل جولة واحدة على الأقل قبل التقييم.',true);
        this.busy=true;this.updateControls();this.notify('تجهيز لجنة من ثلاثة حكام حسب فئة النماذج، ثم يحسم JEV التقارير الصالحة.');
        const stopJury=this.watchJury(this.currentSession.session_id);
        try {
            const d=await this.api('evaluate_session',{session_id:this.currentSession.session_id});this.currentSession=d.session;
            this.notify(d.session.evaluation_status==='complete'?'اكتمل التقييم وحُفظ مع الجلسة.':d.session.evaluation_error || 'تعذر التقييم؛ يمكنك إعادة المحاولة.',d.session.evaluation_status!=='complete');
            await this.reloadLogsOnly();
        } catch(e){this.notify(e.message,true);}
        finally{stopJury();this.busy=false;this.renderActiveSession();}
    },
    async compactNow() {
        if(this.busy || this.generating || !this.currentSession)return;
        this.busy=true;this.updateControls();
        try{this.currentSession=(await this.api('compact_now',{session_id:this.currentSession.session_id})).session;this.notify('تم تحديث ذاكرة السياق مع الاحتفاظ بالحوار الكامل.');}
        catch(e){this.notify(e.message,true);}finally{this.busy=false;this.renderActiveSession();}
    },
    async submitModelReview(role,rating,text) {
        const data=await this.api('submit_review',{session_id:this.currentSession.session_id,role,rating,text});
        this.currentSession=data.session;await this.reloadLogsOnly();this.renderActiveSession();this.notify('حُفظت مراجعتك في سجل الموديل لهذا الدور.');
    },
    async publishSession() {
        if(this.busy || !this.currentSession)return;
        if(!confirm('نشر هذه المحادثة ونتائجها ومراجعاتك واسمك وصورتك للزوار؟ يمكنك حذفها من المختبر لاحقًا.'))return;
        this.busy=true;this.updateControls();
        try{this.currentSession=(await this.api('publish_session',{session_id:this.currentSession.session_id})).session;await this.reloadLogsOnly();this.notify('نُشرت الجلسة ويمكن مشاركتها من الصفحة العامة.');}
        catch(e){this.notify(e.message,true);}finally{this.busy=false;this.renderActiveSession();}
    },
    renderLogsList() {
        document.getElementById('archive-local-count').textContent=this.logs.length;
        document.getElementById('archive-public-count').textContent=this.publishedLogs.length;
        if(document.getElementById('archive-modal').classList.contains('active'))this.renderArchive();
    },
    openArchiveModal(mode) {
        this.archiveMode=mode;document.getElementById('archive-modal-title').textContent=mode==='public'?'سجلات التحقيق المنشورة':'سجلات التحقيق في الجلسة';
        document.getElementById('archive-modal-search').value='';document.getElementById('archive-modal').classList.add('active');this.renderArchive();
    },
    closeArchiveModal(){document.getElementById('archive-modal').classList.remove('active');},
    renderArchive() {
        const box=document.getElementById('archive-modal-grid');box.replaceChildren();
        const q=document.getElementById('archive-modal-search').value.trim().toLowerCase();
        const logs=(this.archiveMode==='public'?this.publishedLogs:this.logs).filter(s=>[s.case_title,s.model,s.interrogator_model].join(' ').toLowerCase().includes(q));
        if(!logs.length){box.textContent='لا توجد جلسات مطابقة.';return;}
        for(const s of logs){
            const card=document.createElement('div');card.className='archive-card';card.tabIndex=0;card.setAttribute('role','button');
            card.innerHTML=`<div class="archive-card-top"><img class="archive-card-avatar" src="${LabView.face(s.character_slug,s.current_state)}" alt=""><div class="archive-card-header-info"><div class="archive-card-name">${this.escapeHtml(s.case_title)}</div><div class="archive-card-roles">المحقق: ${this.escapeHtml(s.interrogator_type!=='human'?(s.interrogator_character?.name || '')+' · '+s.interrogator_model:'بشري')}</div><div class="archive-card-roles">السجين: ${this.escapeHtml(s.interrogator_type==='reverse'?'أنت · متهم بشري':s.model)}</div></div></div><div class="archive-card-body"><span>${s.turns_count} / 30 جولة · ${s.is_ended?'منتهية':'قيد التحقيق'}</span><progress max="30" value="${s.turns_count}"></progress>${s.evaluation?`<strong>السجين ${s.evaluation.prisoner_score ?? s.evaluation.overall_score} · المحقق ${s.evaluation.investigator_score ?? '—'}</strong>`:''}</div>`;
            card.insertAdjacentHTML('beforeend',LabView.participants(s));
            const cost=document.createElement('p');cost.className='archive-cost';cost.textContent=LabCommunity.cost(s);card.appendChild(cost);
            const open=()=>this.loadSession(s.session_id);card.addEventListener('click',open);card.addEventListener('keydown',e=>{if(e.target===card && (e.key==='Enter' || e.key===' ')){e.preventDefault();open();}});
            if(this.archiveMode!=='public') {const remove=document.createElement('button');remove.className='btn danger-outline archive-delete';remove.textContent='حذف نهائي';remove.addEventListener('click',e=>{e.stopPropagation();this.deleteSession(s.session_id);});card.appendChild(remove);}
            box.appendChild(card);
        }
    },
    renderAccount() {
        const controls=document.getElementById('account-controls'),notice=document.getElementById('account-notice');controls.replaceChildren();notice.replaceChildren();
        controls.textContent='النسخة المستقلة';
        const p=document.createElement('p');p.textContent='بلا تسجيل دخول وبلا حد يومي للتجارب. سجلاتك الخاصة مرتبطة بهذا المتصفح؛ النشر اختياري.';notice.appendChild(p);
        if(this.userApiKey){const balance=document.createElement('p');balance.id='personal-key-balance';
            const b=this.keyBalance;balance.textContent=b?(b.available_to_spend!=null?`المتاح للإنفاق بهذا المفتاح: ${Number(b.available_to_spend).toFixed(2)}$${b.key_limit!=null?' · سقف المفتاح '+Number(b.key_limit).toFixed(2)+'$':''}`:'الرصيد غير متاح لهذا المفتاح؛ تحقق من حدوده لدى OpenRouter.'):'جارٍ التحقق من رصيد مفتاحك…';notice.appendChild(balance);
            const refresh=document.createElement('button');refresh.className='text-button';refresh.textContent='تحديث الرصيد';refresh.addEventListener('click',()=>this.refreshKeyBalance());notice.appendChild(refresh);
        }
    },
    async refreshKeyBalance() {
        if(!this.userApiKey)return;
        const checkedKey=this.userApiKey;
        try{const balance=(await this.api('key_balance')).balance;if(this.userApiKey!==checkedKey)return;this.keyBalance=balance;this.updateKeyButtonState();this.renderAccount();}
        catch(e){const el=document.getElementById('personal-key-balance');if(el)el.textContent='تعذر تحديث الرصيد: '+e.message;}
    },
    async deleteSession(id) {
        if(this.busy || this.generating)return this.notify('انتظر حفظ الجولة قبل الحذف.',true);
        if(!confirm('حذف المحادثة نهائيًا من أرشيف هذا المتصفح والمعرض، مع تقييماتك المرتبطة بها؟ لا يمكن التراجع عن الحذف.'))return;
        this.busy=true;this.updateControls();
        try{await this.api('delete_session',{session_id:id,confirm:id});
            if(this.currentSession?.session_id===id){this.currentSession=null;this.generation++;document.getElementById('chat-messages').replaceChildren();document.getElementById('user-input').value='';document.getElementById('usage-summary').textContent='';}
            await this.reloadLogsOnly();this.notify('حُذفت المحادثة ومراجعاتها من المختبر نهائيًا.');
        }catch(e){this.notify(e.message,true);}finally{this.busy=false;this.updateControls();}
    },
    escapeHtml(str) {return LabView.text(str);}
};
window.addEventListener('DOMContentLoaded', () => App.init().catch(e=>App.notify('تعذر تحميل التطبيق: '+e.message,true)));
