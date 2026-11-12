const App = {
    modelLocked(m) {return this.modelTarget==='judge' && document.getElementById('model-modal').classList.contains('active')?JuryUI.locked(m):!m || !!m.is_locked && !this.userApiKey;},
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
        try{const response=await fetch(LabI18n.api(url),{...options,signal:controller.signal});const data=await response.json();
            if(!data.ok)throw new Error(data.error || LabI18n.t("app.fetchData.1"));return data;
        }catch(e){if(e.name==='AbortError')throw new Error(LabI18n.t("standalone.app.fetchData.2"));throw e;}
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

        document.getElementById('sense-model-pill').textContent = 'Jev';
        document.getElementById('compact-model-pill').textContent = LabCards.shortName(this.env.compaction_model || '');
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
        const linkedCase=new URL(location.href).searchParams.get('case_file');
        if(linkedCase && this.cases.some(c=>c.filename===linkedCase)){
            this.selectedCaseFilename=linkedCase;this.renderSelectedCaseSummary();
        }
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
        modelGrid.tabIndex=0;modelGrid.setAttribute('aria-label',LabI18n.t("app.bindEvents.1"));
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

    renderSelectedCaseSummary() {LabCards.setupCase();},

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
            container.innerHTML = `<div style="color:var(--text-muted);padding:20px;text-align:center;grid-column:1/-1;">${LabI18n.html("app.renderCaseCards.1")}</div>`;
            return;
        }

        filtered.forEach(c => {
            const card = document.createElement('div');
            const isActive = (c.filename === this.previewCaseFilename);
            card.className = 'case-card' + (isActive ? ' active' : '');
            card.dataset.filename = c.filename;

            const stateId = c.initial_state || 'calm';
            const imgPath = `/assets/images/${c.character_slug || c.gender}/${stateId}.jpg`;

            card.innerHTML = `
                <img src="${imgPath}" class="case-card-avatar" alt="${this.escapeHtml(c.character_name)}" onerror="this.src='/assets/images/char_1/calm.jpg'">
                <div class="case-card-info">
                    <div class="case-card-name">${this.escapeHtml(c.character_name)}</div>
                    <div class="case-card-title">${this.escapeHtml(c.title)}</div>
                    <span class="case-card-state">${LabI18n.html("app.renderCaseCards.2")} ${this.escapeHtml(c.initial_state_ar || stateId)}</span>
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
        const stateAr = c.initial_state_ar || LabI18n.t("session-view.emotionDescription.2");
        const imgPath = `/assets/images/${c.character_slug || c.gender}/${stateId}.jpg`;

        const portraitImg = document.getElementById('modal-detail-portrait');
        portraitImg.src = imgPath;
        portraitImg.onerror = () => { portraitImg.src = '/assets/images/char_1/calm.jpg'; };

        document.getElementById('modal-detail-name').textContent = c.character_name || c.title;
        document.getElementById('modal-detail-sub').textContent = c.accusation ? LabI18n.t("app.renderCasePreviewDetail.2", {"accusation": c.accusation}) : c.title;
        document.getElementById('modal-detail-state-pill').textContent = LabI18n.t("app.renderCasePreviewDetail.3", {"stateAr": stateAr, "stateId": stateId});
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

    renderSelectedModelSummary() {this.renderReasoningControl('prisoner',this.getSelectedModel());LabCards.setupModel('prisoner');},

    // ==========================================
    // Model Selector Modal
    // ==========================================
    openModelModal(target = 'prisoner') {
        this.modelTarget = target;
        document.querySelector('.mcp-connect-option').hidden=target==='judge';
        document.getElementById('connection-toast').hidden=true;
        document.getElementById('model-modal-description').textContent=target==='judge'?LabI18n.t('jury.choose'):target==='interrogator'?LabI18n.t("app.openModelModal.1"):LabI18n.t("app.openModelModal.2");
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
        source.append(asOf ? LabI18n.t("app.renderModalModels.1", {"value1": new Date(asOf).toLocaleDateString(LabI18n.locale)}) : LabI18n.t("app.renderModalModels.2"));
        const link=document.createElement('a');link.href='https://openrouter.ai/rankings';link.textContent='OpenRouter';link.target='_blank';link.rel='noopener';source.append(link);
        if(asOf)source.append(' · CC BY 4.0');

        const q = this.modelSearchQuery;
        const tab = this.currentModelTab;

        const filtered = this.models.filter(m => {
            // Tab filter
            if (!inTab(m,tab) || (this.modelTarget==='judge' && !JuryUI.eligible(m))) return false;

            // Search filter
            if (!q) return true;
            return m.id.toLowerCase().includes(q) ||
                   m.name.toLowerCase().includes(q) ||
                   (m.provider_slug && m.provider_slug.toLowerCase().includes(q)) ||
                   (m.description_ar && m.description_ar.toLowerCase().includes(q));
        }).sort((a,b)=>this.modelOrder(b)-this.modelOrder(a) || a.name.localeCompare(b.name));

        if (filtered.length === 0) {
            container.innerHTML = `<div style="color:var(--text-muted);padding:30px;text-align:center;grid-column:1/-1;">${LabI18n.html("app.renderModalModels.3")}</div>`;
            return;
        }

        filtered.forEach(m => {
            const card = document.createElement('div');
            const isSelected = this.modelTarget==='judge'?JuryUI.slots()[JuryUI.slot]===m.id:(m.id === (this.modelTarget === 'interrogator' ? this.selectedInterrogatorId : this.selectedModelId));
            const isLocked = !!m.is_locked;

            let cardClass = 'model-card';
            if (isSelected) cardClass += ' active';
            if (this.modelLocked(m)) cardClass += ' locked';
            if (isLocked && !this.modelLocked(m)) cardClass += ' key-funded';
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
                    badgeHtml = `<span class="model-card-badge badge-userkey">${LabI18n.html("app.renderModalModels.4")}</span>`;
                } else {
                    badgeHtml = `<span class="model-card-badge badge-locked">${LabI18n.html("app.renderModalModels.5")}</span>`;
                }
            } else {
                badgeHtml = `<span class="model-card-badge badge-unlocked">${category(m)==='free'?LabI18n.t("app.renderModalModels.6"):LabI18n.t("app.renderModalModels.7")}</span>`;
            }

            if (category(m)==='free' && !this.env.free_models_available) badgeHtml=`<span class="model-card-badge badge-locked">${LabI18n.html("app.renderModalModels.10")}</span>`;
            const descText = m.description_ar || LabI18n.t("app.renderModalModels.11", {"name": m.name});

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
                        <span class="model-card-price">$${pIn} In / $${pOut} ${LabI18n.html("app.renderModalModels.17")}</span>
                        <span class="reasoning-badge">${this.modelTarget==='judge'?LabI18n.html('jury.providerReasoning'):LabView.escape(LabView.reasoningLabel(this.reasoningPreview(this.modelTarget,m)))}</span><span class="model-card-context">${m.context_formatted || '128K'}</span>
                    </div>
                    <div class="model-discovery-meta">${m.created ? new Date(m.created*1000).toLocaleDateString(LabI18n.locale) : LabI18n.t("app.renderModalModels.14")}${m.usage_rank ? LabI18n.t("app.renderModalModels.15", {"usage_rank": m.usage_rank}) : ''}</div>
                    ${m.curated_family?`<div class="curated-family">${this.escapeHtml(m.curated_family)} ${LabI18n.html("app.renderModalModels.16")}</div>`:''}
                    ${this.modelPerformanceHTML(m)}
                    <div class="model-health">${this.modelHealthText(m)}</div>
                </div>
            `;

            const name=card.querySelector('.model-card-name'),link=document.createElement('a');
            link.className='model-card-name model-card-name-link';link.textContent=m.name;link.title=m.id;link.dir='auto';
            link.href='https://openrouter.ai/'+m.id;link.target='_blank';link.rel='noopener';
            name.replaceWith(link);
            card.addEventListener('click', e => {if(!e.target.closest('details,a,button'))this.selectModalModel(m.id);});
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
        if(!p)return `<div class="model-performance">${LabI18n.html("app.modelPerformanceHTML.1")}</div>`;
        return `<div class="model-performance"><strong>${this.modelTarget==='interrogator'?LabI18n.t("session-view.evaluation.13"):LabI18n.t("app.modelPerformanceHTML.3")}: ${p.judge_average==null?LabI18n.t("app.modelPerformanceHTML.4"):p.judge_average+'/100'}</strong> · ${p.assessments} ${LabI18n.html("app.modelPerformanceHTML.15")}<br>${LabI18n.html("app.modelPerformanceHTML.16")} ${p.user_average==null?LabI18n.t("app.modelPerformanceHTML.5"):p.user_average+'/5'} · ${p.user_reviews} ${LabI18n.html("app.modelPerformanceHTML.17")}${p.limited_sample?`<br>${LabI18n.html("app.modelPerformanceHTML.6")}`:''}${Object.entries(p.badges || {}).map(([type,n])=>`<span class="incident-badge">${LabView.incidentLabels[type] || this.escapeHtml(type)} ×${n}</span>`).join('')}${p.quality_band==='orange' || p.quality_band==='yellow'?`<small>${LabI18n.html("app.modelPerformanceHTML.7")}</small>`:''}${p.contexts?.length?`<details class="model-user-reviews"><summary>${LabI18n.html("app.modelPerformanceHTML.13")}</summary>${p.contexts.map(c=>`<p>${LabI18n.html("app.modelPerformanceHTML.10")} ${c.level ?? LabI18n.t("app.modelPerformanceHTML.8")} ${LabI18n.html("session-view.evaluation.10")} ${this.escapeHtml(c.rubric_version || LabI18n.t("session-view.evaluation.5"))} · ${c.count} ${LabI18n.html("app.modelPerformanceHTML.12")} ${c.average}/100</p>`).join('')}</details>`:''}${p.recent_reviews?.length?`<details class="model-user-reviews"><summary>${LabI18n.html("app.modelPerformanceHTML.14")}</summary>${p.recent_reviews.map(r=>`<p><strong>${r.rating}/5</strong> ${this.escapeHtml(r.text)}</p>`).join('')}</details>`:''}</div>`;
    },
    modelHealthText(m) {
        const h=m.health || {}, parts=[];
        parts.push(LabI18n.t("app.modelHealthText.1", {"value1": h.requests ?? 0, "value2": h.successes ?? 0, "value3": h.failures ?? 0}));
        if(h.failure_rate!=null)parts.push(LabI18n.t("app.modelHealthText.2", {"failure_rate": h.failure_rate}));
        if(h.cancelled)parts.push(LabI18n.t("app.modelHealthText.3", {"cancelled": h.cancelled}));
        if(h.invalid_responses)parts.push(LabI18n.t("app.modelHealthText.4", {"invalid_responses": h.invalid_responses}));
        if(h.failure_rate!=null && h.sample_confidence==='limited')parts.push(LabI18n.t("app.modelHealthText.5"));
        if(h.weak)parts.push('⚠ '+(h.reasons || []).map(x=>LabView.escape(x)).join(' · '));
        if(h.first_byte_ms!=null)parts.push(LabI18n.t("app.modelHealthText.6", {"first_byte_ms": h.first_byte_ms}));
        if(h.avg_seconds!=null)parts.push(LabI18n.t("app.modelHealthText.7", {"avg_seconds": h.avg_seconds}));
        if(h.tokens_per_second!=null)parts.push(LabI18n.t("app.modelHealthText.8", {"tokens_per_second": h.tokens_per_second}));
        if(h.failure_rate==null)parts.push(LabI18n.t("app.modelHealthText.9"));
        return parts.join(' · ');
    },

    selectModalModel(modelId) {
        const m = this.models.find(item => item.id === modelId);
        if (!m) return;
        if(this.modelTarget==='judge')return JuryUI.choose(modelId);
        if (Number(m.pricing?.prompt_per_1m)===0 && Number(m.pricing?.completion_per_1m)===0 && !this.env.free_models_available) {alert(LabI18n.t("standalone.app.selectModalModel.1"));return;}

        if (m.is_locked && !this.userApiKey) {
            alert(LabI18n.t("standalone.app.selectModalModel.2"));
            this.closeModelModal();
            this.openKeyModal();
            return;
        }

        if (this.modelTarget === 'interrogator') this.selectedInterrogatorId = modelId;
        else this.selectedModelId = modelId;
        if(typeof MCPUI!=='undefined')delete MCPUI.selections[this.modelTarget];
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
            alert(LabI18n.t("app.saveUserKey.1"));
            return;
        }
        const old=this.userApiKey;this.userApiKey = val;
        if(val){try{this.keyBalance=(await this.api('key_balance')).balance;}catch(e){this.userApiKey=old;return this.notify(LabI18n.t("app.saveUserKey.3")+e.message,true);}}
        if (val) {
            sessionStorage.setItem('user_openrouter_api_key', val);
            this.notify(LabI18n.t("standalone.app.saveUserKey.3"));
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
        this.renderAccount();this.renderInterrogatorSummary();this.renderModalModels();this.notify(LabI18n.t("standalone.app.clearUserKey.1"));
    },

    updateKeyButtonState() {
        const btn = document.getElementById('open-key-modal-btn');
        if (this.userApiKey) {
            const amount=this.keyBalance?.available_to_spend;
            btn.textContent=LabI18n.t("app.updateKeyButtonState.2")+(amount!=null?' '+Number(amount).toFixed(2)+'$':LabI18n.t("app.updateKeyButtonState.3"));
            btn.classList.add('has-key');
        } else {
            btn.innerHTML = LabI18n.t("app.updateKeyButtonState.4");
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
        img.src = `/assets/images/${slug}/${validId}.jpg`;
        img.onerror = () => { img.src = `/assets/images/char_1/${validId}.jpg`; };

        const pill = document.getElementById('current-state-pill');
        if (customStateText) {
            pill.textContent = LabI18n.t("app.updatePortrait.1", {"customStateText": customStateText});
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
        document.getElementById('sense-directive-text').textContent=target ? LabI18n.t("app.updateSenseMeters.1", {"name_ar": target.name_ar}) : LabI18n.t("app.updateSenseMeters.2");
    },

    // ==========================================
    // Interrogation Session Execution
    // ==========================================

    async api(action, payload = {}, retryContext = {}) {
        const canRetry=['send_message','ai_vs_ai_step','reverse_question','reverse_answer','retry_confession','evaluate_session','retry_jury','compact_now'].includes(action);
        if(action==='compact_now' && !payload.request_id)payload={...payload,request_id:this.requestId()};
        const body=JSON.stringify({action,...payload});
        const busyDeadline=Date.now()+1320000;
        const firstAttempt=retryContext.priorFailure?1:0;
        if(firstAttempt) {this.notify(LabI18n.t("app.api.1"),true);await this.retryWait(10000);}
        for(let attempt=firstAttempt;attempt<3;attempt++) {
            try {
                let res;
                try {res=await fetch(LabI18n.api(),{method:'POST',signal:AbortSignal.timeout(1320000),headers:{'Content-Type':'application/json','X-CSRF-Token':this.csrfToken,...(this.userApiKey?{'X-User-OpenRouter-Key':this.userApiKey}:{})},body});}
                catch(e) {throw Object.assign(new Error(LabI18n.t("app.api.2")),{retryable:true});}
                let data;
                try {data=await res.json();}catch {throw Object.assign(new Error(LabI18n.t("app.api.3")),{retryable:res.status>=500});}
                if(res.status===409 && data.busy && canRetry && payload.session_id) {
                    this.notify(LabI18n.t("app.api.4"));
                    let progress;
                    do {
                        if(Date.now()>busyDeadline)throw new Error(LabI18n.t("app.api.5"));
                        await this.retryWait(3000);
                        progress=await this.fetchData('api.php?action=load_session&session_id='+encodeURIComponent(payload.session_id));
                    }while(progress.processing);
                    // Replay the identical ID only after release; server idempotence returns saved work.
                    attempt--;continue;
                }
                if(!data.ok)throw Object.assign(new Error(data.error || LabI18n.t("app.api.6")),{retryable:!!data.retryable || [502,503,504].includes(res.status)});
                if(data.mcp_wait && typeof MCPUI!=='undefined'){
                    MCPUI.waiting=true;this.currentSession=data.session;this.renderActiveSession();
                    this.notify(LabI18n.t("app.api.7"));
                    await this.retryWait(2000);
                    if(MCPUI.pauseRequested){MCPUI.waiting=false;MCPUI.pauseRequested=false;throw new Error(LabI18n.t("app.api.8"));}
                    attempt--;continue;
                }
                if(typeof MCPUI!=='undefined')MCPUI.waiting=false;
                if(['evaluate_session','retry_jury'].includes(action) && data.session?.evaluation_status==='failed') {
                    this.currentSession=data.session;
                    if(data.session.evaluation_retryable)throw Object.assign(new Error(data.session.evaluation_error),{retryable:true});
                }
                if(this.userApiKey && data.session && ['send_message','ai_vs_ai_step','reverse_question','reverse_answer','evaluate_session','compact_now'].includes(action))void this.refreshKeyBalance();
                return data;
            } catch(e) {
                if(!canRetry || !e.retryable || attempt===2) {if(canRetry && e.retryable && attempt===2)e.message=LabI18n.t("app.api.9")+e.message;throw e;}
                const delay=(attempt+1)*10000;
                this.notify(LabI18n.t("app.api.10", {"message": e.message, "value2": attempt+2, "value3": delay/1000}),true);
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
        button.textContent=LabView.reasoningLabel(this.reasoningPreview(role,m))+(adjustable?LabI18n.t("mcp.renderSelection.3"):'');
        button.disabled=!m || !adjustable;
        button.title=LabI18n.t("app.renderReasoningControl.2");
    },
    openReasoningModal(role) {
        if(typeof MCPUI!=='undefined' && MCPUI.selections[role])return MCPUI.configure(role);
        const id=role==='prisoner'?this.selectedModelId:this.selectedInterrogatorId,m=this.models.find(m=>m.id===id);
        const efforts=m?.reasoning_capabilities?.efforts || [];if(!efforts.length)return;
        this.reasoningReturnFocus=document.activeElement;
        const box=document.getElementById('reasoning-options');box.replaceChildren();
        document.getElementById('reasoning-model-name').textContent=m.name;
        for(const choice of ['auto',...efforts]) {
            const b=document.createElement('button');b.type='button';b.className='reasoning-option';b.dataset.effort=choice;
            b.textContent=choice==='auto'?LabI18n.t("app.openReasoningModal.1"):LabView.reasoningNames[choice];
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
        document.getElementById('human-investigator-character').hidden=!humanInvestigator;
        document.getElementById('prisoner-model-group').hidden=humanPrisoner;
        document.getElementById('prisoner-human-card').hidden=!humanPrisoner;
        document.getElementById('interrogator-human-card').hidden=!humanInvestigator;
        document.getElementById('reverse-role-brief').hidden=!humanPrisoner;
        for(const [role,isHuman] of [['prisoner',humanPrisoner],['interrogator',humanInvestigator]]) {
            document.getElementById(role+'-representation-label').textContent=isHuman?LabI18n.t("app.setInterrogationMode.1"):LabI18n.t("app.setInterrogationMode.2");
            document.getElementById(role+'-performer-type').textContent=isHuman?LabI18n.t("app.setInterrogationMode.3"):LabI18n.t("app.setInterrogationMode.4");
            this.renderHumanPerformer(document.getElementById(role+'-human-card'));
        }
        this.renderSelectedModelSummary();
        this.renderInterrogatorSummary();
        document.getElementById('swap-roles-btn').title=mode==='ai'?LabI18n.t("app.setInterrogationMode.5"):LabI18n.t("app.setInterrogationMode.6");
    },
    swapRoles() {
        if(this.busy || this.generating)return;
        if(typeof MCPUI!=='undefined')[MCPUI.selections.prisoner,MCPUI.selections.interrogator]=[MCPUI.selections.interrogator,MCPUI.selections.prisoner];
        [this.reasoningSelections.prisoner,this.reasoningSelections.interrogator]=[this.reasoningSelections.interrogator,this.reasoningSelections.prisoner];
        [this.selectedModelId,this.selectedInterrogatorId]=[this.selectedInterrogatorId,this.selectedModelId];
        this.setInterrogationMode(this.interrogationMode==='ai'?'ai':this.interrogationMode==='human'?'reverse':'human');
        this.updateControls();
    },
    renderHumanPerformer(container) {
        container.innerHTML=`<span class="participant-avatar participant-placeholder" aria-hidden="true">👤</span><span class="participant-info"><strong>${LabI18n.html("app.renderHumanPerformer.1")}</strong><small>${LabI18n.html("standalone.app.renderHumanPerformer.2")}</small></span>`;
    },
    renderInterrogatorSummary() {
        const m=this.models.find(m=>m.id===this.selectedInterrogatorId);
        this.renderReasoningControl('interrogator',m);LabCards.setupModel('interrogator');
        LabCards.setupInvestigator(this.investigators.find(p=>p.id===this.selectedInvestigatorPersona));
    },
    openInvestigatorModal() {
        document.getElementById('connection-toast').hidden=true;
        const box=document.getElementById('investigator-profile-grid');box.replaceChildren();
        for(const p of this.investigators) {
            const card=document.createElement('article');card.className='investigator-card';
            card.innerHTML=`${LabView.investigatorAvatar(p,'profile-portrait')}<div class="investigator-file"><span class="investigator-rank">${String(p.number).padStart(2,'0')} ${LabI18n.html("app.openInvestigatorModal.8")} ${p.level}/10 · ${this.escapeHtml(p.level_ar)}</span><h3 dir="ltr">${this.escapeHtml(p.name)}</h3><p>${p.age} ${LabI18n.html("app.openInvestigatorModal.9")} ${p.years_experience} ${LabI18n.html("app.openInvestigatorModal.10")}</p><strong>${this.escapeHtml(p.specialty)}</strong><p>${this.escapeHtml(p.bio)}</p><p>${this.escapeHtml(p.style)}</p><div class="investigator-skills">${p.skills.map(s=>`<span>${this.escapeHtml(s)}</span>`).join('')}</div><details><summary>${LabI18n.html("app.openInvestigatorModal.11")}</summary><p>${this.escapeHtml(p.limits)}</p><p>${this.escapeHtml(p.instructions)}</p><small>${LabI18n.html("app.openInvestigatorModal.12")} ${this.escapeHtml(({none:LabI18n.t("app.openInvestigatorModal.1"),low:LabI18n.t("session-view.ui.25"),medium:LabI18n.t("session-view.ui.26"),high:LabI18n.t("session-view.ui.27"),highest:LabI18n.t("app.openInvestigatorModal.5")})[p.thinking])} ${LabI18n.html("app.openInvestigatorModal.13")}</small></details><button type="button" class="btn btn-secondary" data-investigator-id="${p.id}">${p.id===this.selectedInvestigatorPersona?LabI18n.t("app.openInvestigatorModal.6"):LabI18n.t("app.openInvestigatorModal.7")}</button></div>`;
            const faces=document.createElement('details');faces.innerHTML=`<summary>${LabI18n.html("app.openInvestigatorModal.14")}</summary><div class="investigator-expressions">`+Object.entries(LabView.investigatorExpressions).map(([id,name])=>`<figure>${LabView.investigatorAvatar(p,'',id)}<figcaption>${name}</figcaption></figure>`).join('')+'</div>';card.querySelector('.investigator-file').insertBefore(faces,card.querySelector('button'));
            card.querySelector('button').addEventListener('click',()=>{this.selectedInvestigatorPersona=p.id;this.renderInterrogatorSummary();document.getElementById('investigator-profile-modal').classList.remove('active');});
            box.appendChild(card);
        }
        document.getElementById('investigator-profile-modal').classList.add('active');
    },
    async requestReverseQuestion() {
        if(this.busy || !this.currentSession || this.currentSession.interrogator_type!=='reverse' || this.currentSession.is_ended || this.currentSession.read_only)return;
        this.busy=true;this.updateControls();this.notify(LabI18n.t("app.requestReverseQuestion.1"));
        try {this.currentSession=(await this.api('reverse_question',{session_id:this.currentSession.session_id})).session;this.renderActiveSession();this.notify(LabI18n.t("app.requestReverseQuestion.2"));}
        catch(e){this.notify(e.message+LabI18n.t("app.requestReverseQuestion.3"),true);}
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
                jury_slots:typeof JuryUI!=='undefined'?JuryUI.draft:undefined,
                mcp_actors:typeof MCPUI!=='undefined'?MCPUI.startConfig():{},
                interrogator_type:this.interrogationMode,interrogator_model:this.selectedInterrogatorId,interrogator_character:this.selectedInvestigatorPersona,prisoner_effort:this.reasoningSelection('prisoner'),interrogator_effort:this.reasoningSelection('interrogator')});
            this.generation++; this.currentSession=data.session; this.stopRequested=false;this.aiPaused=false;
            if(typeof MCPUI!=='undefined')await MCPUI.started(data.session);
            this.startRequestId=null;this.renderAccount();
            this.renderActiveSession(); await this.reloadLogsOnly(); this.notify(LabI18n.t("app.startNewSession.1"));
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
        if(this.busy || this.generating) return this.notify(LabI18n.t("app.loadSession.1"),true);
        try {
            const d=await (await fetch(LabI18n.api('api.php?action=load_session&session_id='+encodeURIComponent(id)))).json();
            if(!d.ok) throw Error(d.error);
            this.currentSession=d.session;this.generation++;this.currentCharacterSlug=d.session.character_slug;
            if(typeof MCPUI!=='undefined')MCPUI.selections=structuredClone(d.session.mcp_actors || {});
            this.aiPaused=d.session.interrogator_type==='ai' && !d.session.is_ended;
            this.currentGender=d.session.gender;this.selectedModelId=d.session.model;
            this.renderStatesGallery(d.session.current_state);
            this.selectedInterrogatorId=d.session.interrogator_model;this.selectedCaseFilename=d.session.case_file;
            this.selectedInvestigatorPersona=d.session.interrogator_character?.id || 'ethan_cole';
            this.setInterrogationMode(d.session.interrogator_type);
            if (d.session.pending_question && d.session.interrogator_type === 'human') document.getElementById('user-input').value = d.session.pending_question;
            this.renderSelectedCaseSummary();this.renderSelectedModelSummary();this.renderActiveSession();this.closeArchiveModal();
            this.notify(d.session.read_only?LabI18n.t("app.loadSession.2"):LabI18n.t("app.loadSession.3"));
        } catch(e) {this.notify(e.message,true);}
    },
    updateControls() {
        const s=this.currentSession, ai=s?.interrogator_type==='ai', reverse=s?.interrogator_type==='reverse', ended=s?.is_ended, ro=s?.read_only;
        document.getElementById('start-session-btn').disabled=this.busy || this.generating;
        document.getElementById('swap-roles-btn').disabled=this.busy || this.generating;
        const logout=document.getElementById('logout-btn');if(logout)logout.disabled=this.busy || this.generating;
        document.getElementById('start-session-btn').textContent=LabI18n.t("app.updateControls.1");
        document.getElementById('human-input-bar').style.display=ai?'none':'';
        document.getElementById('human-tactics-bar').style.display=ai || reverse?'none':'';
        document.getElementById('ai-vs-ai-controls').style.display=ai?'':'none';
        const waiting=reverse && !s?.awaiting_question?.sense;
        document.getElementById('send-btn').disabled=this.busy || !!ended || !!ro || !!waiting;
        document.getElementById('user-input').disabled=this.busy || !!ended || !!ro || !!waiting;
        document.getElementById('send-btn').textContent=reverse?LabI18n.t("app.updateControls.3"):LabI18n.t("app.updateControls.4");
        document.getElementById('user-input').placeholder=reverse?LabI18n.t("app.updateControls.5"):LabI18n.t("app.updateControls.6");
        document.getElementById('reverse-resume-btn').hidden=!reverse || !!ended || !!ro || !!s?.awaiting_question?.sense;
        document.getElementById('reverse-resume-btn').disabled=this.busy;
        document.getElementById('compact-btn').disabled=!s || this.busy || this.generating || !!ended || !!ro;
        if(s && Object.keys(s.mcp_actors || {}).length===(s.interrogator_type==='ai'?2:1))document.getElementById('compact-btn').disabled=true;
        if(typeof MCPUI!=='undefined')MCPUI.renderSession();
        if(typeof JuryUI!=='undefined')JuryUI.render();
        const end=document.getElementById('end-session-btn');
        end.disabled=!s || this.busy || !!ro || s.evaluation_status==='complete';
        const endTest=document.getElementById('end-test-btn');endTest.disabled=end.disabled;endTest.hidden=!!s?.evaluation;
        endTest.textContent=this.generating?LabI18n.t("app.updateControls.7"):LabI18n.t("app.updateControls.8");
        end.textContent=this.generating?LabI18n.t("app.updateControls.9"):ended?LabI18n.t("app.updateControls.10"):LabI18n.t("app.updateControls.11");
        if(ai) {
            document.getElementById('resume-investigation-btn').hidden=!this.aiPaused || this.generating || this.busy || !!ended || !!ro;
            document.getElementById('ai-turn-counter').textContent=LabI18n.t("app.updateControls.13", {"turns_count": s.turns_count, "value2": this.generating?LabI18n.t("app.updateControls.12"):''});
        }
    },
    renderActiveSession() {
        const s=this.currentSession;if(!s)return;
        const fundingLabel=document.getElementById('session-funding-label');
        if(fundingLabel){fundingLabel.hidden=false;fundingLabel.textContent=LabI18n.t("app.renderActiveSession.1")+LabCards.funding(s).name+(s.free_funding_mode==='mixed'?LabI18n.t("standalone.app.renderActiveSession.2"):'');}
        const chatBox=document.getElementById('chat-messages');
        const followBottom=chatBox.scrollHeight-chatBox.scrollTop-chatBox.clientHeight<100;
        const previousScroll=chatBox.scrollTop;
        const visible=s.messages;
        const last=visible.filter(m=>m.role==='assistant').at(-1);
        document.getElementById('active-session-id').textContent=LabI18n.t("app.renderActiveSession.3", {"turns_count": s.turns_count});
        this.updatePortrait(s.character_slug,last?.visual_state || s.initial_state,'');
        document.getElementById('chat-messages').replaceChildren();
        const levels=document.createElement('div');levels.className='session-levels';levels.innerHTML=LabView.levels(s)+(s.closure_policy?`<span>${LabI18n.html("app.renderActiveSession.4")}</span>`:'');document.getElementById('chat-messages').appendChild(levels);
        if(!s.is_ended)document.getElementById('chat-messages').appendChild(LabCommunity.result(s));
        for(const m of visible)this.appendMessageDOM(m);
        if(s.interrogator_type==='reverse' && s.awaiting_question && !s.is_ended) {
            this.appendMessageDOM({role:'user',content:s.awaiting_question.text,interrogator_expression:s.awaiting_question.expression,turn:s.awaiting_question.turn,timestamp:s.awaiting_question.timestamp});
            const sense=s.awaiting_question.sense;
            if(sense) {
                const p=document.createElement('p');p.className='reverse-prompt';p.textContent=LabI18n.t("app.renderActiveSession.5")+sense.directive;
                document.getElementById('chat-messages').appendChild(p);
            }
        }
        if(!visible.length && !s.awaiting_question) {
            const p=document.createElement('p');p.className='session-empty';p.textContent=s.interrogator_type==='ai'?LabI18n.t("app.renderActiveSession.6"):LabI18n.t("app.renderActiveSession.7");
            document.getElementById('chat-messages').appendChild(p);
        }
        if(last?.sense_metrics)this.updateSenseMeters(last.sense_metrics,last.sense_directive,last.jev_target_state);
        else {this.updateSenseMeters({fear:0,anger:0,trust:0,collapse:0,breaking_point:0},LabI18n.t("app.renderActiveSession.8"));}
        if(s.interrogator_type==='reverse')document.getElementById('sense-directive-text').textContent=LabI18n.t("app.renderActiveSession.9");
        if(s.is_ended) {
            const card=LabView.evaluation(s);
            if(!s.read_only && s.evaluation_status==='complete') {
                // Publication controls, provisional ranking and privacy confirmation are shown together above.
            }
            if(!s.read_only && s.evaluation_status!=='complete') {
                const b=document.createElement('button');b.className='btn btn-secondary';b.textContent=this.busy?LabI18n.t("app.renderActiveSession.10"):LabI18n.t("app.renderActiveSession.11");b.disabled=this.busy;
                b.addEventListener('click',()=>this.endAndEvaluateSession());card.appendChild(b);
            }
            document.getElementById('chat-messages').appendChild(card);
            document.getElementById('chat-messages').appendChild(LabCommunity.result(s));
            document.getElementById('chat-messages').appendChild(LabView.benchmark(s));
        }
        document.getElementById('usage-summary').textContent=LabView.usage(s);
        const memory=document.getElementById('memory-notes'),visibleTurn=s.turns_count;
        const notes=Object.values(s.memory_notes || {}).filter(n=>n.audience==='spoken' && n.turn<=visibleTurn);
        memory.hidden=!notes.length;memory.innerHTML=`<summary>${LabI18n.html("app.renderActiveSession.13")}${notes.length})</summary>${notes.map(n=>`<p>${LabI18n.html("session-view.message.1")} ${n.turn}: ${LabView.text(n.note)}</p>`).join('')}`;
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
        this.busy=true;this.updateControls();this.notify(LabI18n.t("app.sendMessage.1"));
        try {
            if(this.currentSession.confession_error)await this.checkConfession();
            if(!this.currentSession.is_ended)await this.takeRound(text);
            input.value='';
            this.notify(LabI18n.t("app.sendMessage.2"));this.renderActiveSession();await this.reloadLogsOnly();
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
                this.notify(LabI18n.t("app.generateAiRounds.1"));
            }
        } catch(e) {this.aiPaused=true;this.notify(LabI18n.t("app.generateAiRounds.2")+e.message+LabI18n.t("app.generateAiRounds.3"),true);await this.reloadLogsOnly().catch(()=>{});}
        finally {this.generating=false;this.updateControls();}
        if(this.currentSession.is_ended || this.stopRequested) await this.endAndEvaluateSession();
    },
    resumeInvestigation() {
        if(this.busy || this.generating || !this.currentSession || this.currentSession.read_only)return;
        if(this.currentSession.is_ended)return this.endAndEvaluateSession();
        this.notify(LabI18n.t("app.resumeInvestigation.1"));this.generateAiRounds();
    },
    watchJury(sessionId) {
        const box=document.getElementById('jury-live-status');let done=false,inFlight=false,wait=null,progress={};
        box.hidden=false;
        const draw=()=>{
            if(done)return;
            if(!wait){box.textContent=LabI18n.t("app.watchJury.1");return;}
            const remaining=Math.max(0,Math.ceil((wait.deadline_at*1000-Date.now())/1000));
            const received=Object.values(progress).filter(p=>p.status==='complete').length;
            box.replaceChildren();
            const title=document.createElement('strong');title.textContent=LabI18n.t("app.watchJury.4", {"value1": wait.tier==='economic'?LabI18n.t("app.watchJury.2"):wait.tier==='medium'?LabI18n.t("session-view.ui.26"):LabI18n.t("lab-cards.setupModel.3"), "received": received});
            const timer=document.createElement('bdi');timer.className='jury-countdown';timer.dataset.remaining=remaining;timer.textContent=remaining+LabI18n.t("app.watchJury.5");
            const detail=document.createElement('span');detail.textContent=wait.phase==='finished' || remaining===0?LabI18n.t("app.watchJury.6"):LabI18n.t("app.watchJury.7");
            box.append(title,timer,detail);
        };
        const poll=async()=>{
            if(done || inFlight)return;inFlight=true;
            try{const d=await this.fetchData('api.php?action=load_session&session_id='+encodeURIComponent(sessionId),{},5000);
                if(!done){wait=d.session.jury_wait || null;progress=d.session.judge_progress || {};if(this.currentSession?.session_id===sessionId){for(const field of ['jury_slots','judge_models','judge_reports','judge_progress','jury_wait'])if(d.session[field]!==undefined)this.currentSession[field]=d.session[field];if(typeof JuryUI!=='undefined')JuryUI.render();}draw();}}
            catch(e){if(!done && !wait)box.textContent=LabI18n.t("app.watchJury.8");}
            finally{inFlight=false;}
        };
        draw();poll();const ticker=setInterval(draw,250),poller=setInterval(poll,1500);
        return ()=>{done=true;clearInterval(ticker);clearInterval(poller);box.hidden=true;box.replaceChildren();};
    },
    async endAndEvaluateSession() {
        if(this.generating) {this.stopRequested=true;this.notify(LabI18n.t("app.endAndEvaluateSession.1"));return;}
        if(this.busy || !this.currentSession || this.currentSession.read_only)return;
        if(!this.currentSession.turns_count)return this.notify(LabI18n.t("app.endAndEvaluateSession.2"),true);
        this.busy=true;this.updateControls();this.notify(LabI18n.t("app.endAndEvaluateSession.3"));
        const stopJury=this.watchJury(this.currentSession.session_id);
        try {
            const d=await this.api('evaluate_session',{session_id:this.currentSession.session_id});this.currentSession=d.session;
            this.notify(d.session.evaluation_status==='complete'?LabI18n.t("app.endAndEvaluateSession.4"):d.session.evaluation_error || LabI18n.t("app.endAndEvaluateSession.5"),d.session.evaluation_status!=='complete');
            await this.reloadLogsOnly();
        } catch(e){this.notify(e.message,true);}
        finally{stopJury();this.busy=false;this.renderActiveSession();}
    },
    async compactNow() {
        if(this.busy || this.generating || !this.currentSession)return;
        this.busy=true;this.updateControls();
        try{this.currentSession=(await this.api('compact_now',{session_id:this.currentSession.session_id})).session;this.notify(LabI18n.t("app.compactNow.1"));}
        catch(e){this.notify(e.message,true);}finally{this.busy=false;this.renderActiveSession();}
    },
    async submitModelReview(role,rating,text) {
        const data=await this.api('submit_review',{session_id:this.currentSession.session_id,role,rating,text});
        this.currentSession=data.session;await this.reloadLogsOnly();this.renderActiveSession();this.notify(LabI18n.t("app.submitModelReview.1"));
    },
    async publishSession() {
        if(this.busy || !this.currentSession)return;
        if(!confirm(LabI18n.t("app.publishSession.1")))return;
        this.busy=true;this.updateControls();
        try{this.currentSession=(await this.api('publish_session',{session_id:this.currentSession.session_id})).session;await this.reloadLogsOnly();this.notify(LabI18n.t("app.publishSession.2"));}
        catch(e){this.notify(e.message,true);}finally{this.busy=false;this.renderActiveSession();}
    },
    renderLogsList() {
        document.getElementById('archive-local-count').textContent=this.logs.length;
        document.getElementById('archive-public-count').textContent=this.publishedLogs.length;
        if(document.getElementById('archive-modal').classList.contains('active'))this.renderArchive();
    },
    openArchiveModal(mode) {
        this.archiveMode=mode;document.getElementById('archive-modal-title').textContent=mode==='public'?LabI18n.t("app.openArchiveModal.1"):LabI18n.t("app.openArchiveModal.2");
        document.getElementById('archive-modal-search').value='';document.getElementById('archive-modal').classList.add('active');this.renderArchive();
    },
    closeArchiveModal(){document.getElementById('archive-modal').classList.remove('active');},
    renderArchive() {
        const box=document.getElementById('archive-modal-grid');box.replaceChildren();
        const q=document.getElementById('archive-modal-search').value.trim().toLowerCase();
        const logs=(this.archiveMode==='public'?this.publishedLogs:this.logs).filter(s=>[s.case_title,s.model,s.interrogator_model].join(' ').toLowerCase().includes(q));
        if(!logs.length){box.textContent=LabI18n.t("app.renderArchive.1");return;}
        for(const s of logs){
            const compact=LabCards.card(s,{open:id=>this.loadSession(id)});box.appendChild(compact);

        }
    },
    renderAccount() {
        const controls=document.getElementById('account-controls'),notice=document.getElementById('account-notice');controls.replaceChildren();notice.replaceChildren();
        controls.textContent=LabI18n.t("standalone.app.renderAccount.1");
        const p=document.createElement('p');p.textContent=LabI18n.t("standalone.app.renderAccount.2");notice.appendChild(p);
        if(this.userApiKey){const balance=document.createElement('p');balance.id='personal-key-balance';
            const b=this.keyBalance;balance.textContent=b?(b.available_to_spend!=null?LabI18n.t("app.renderAccount.9", {"value1": Number(b.available_to_spend).toFixed(2), "value2": b.key_limit!=null?LabI18n.t("app.renderAccount.8")+Number(b.key_limit).toFixed(2)+'$':''}):LabI18n.t("app.renderAccount.10")):LabI18n.t("app.renderAccount.11");notice.appendChild(balance);
            const refresh=document.createElement('button');refresh.className='text-button';refresh.textContent=LabI18n.t("app.renderAccount.12");refresh.addEventListener('click',()=>this.refreshKeyBalance());notice.appendChild(refresh);
        }
    },
    async refreshKeyBalance() {
        if(!this.userApiKey)return;
        const checkedKey=this.userApiKey;
        try{const balance=(await this.api('key_balance')).balance;if(this.userApiKey!==checkedKey)return;this.keyBalance=balance;this.updateKeyButtonState();this.renderAccount();}
        catch(e){const el=document.getElementById('personal-key-balance');if(el)el.textContent=LabI18n.t("app.refreshKeyBalance.1")+e.message;}
    },
    async deleteSession(id) {
        if(this.busy || this.generating)return this.notify(LabI18n.t("app.deleteSession.1"),true);
        if(!confirm(LabI18n.t("standalone.app.deleteSession.2")))return;
        this.busy=true;this.updateControls();
        try{await this.api('delete_session',{session_id:id,confirm:id});
            if(this.currentSession?.session_id===id){this.currentSession=null;this.generation++;document.getElementById('chat-messages').replaceChildren();document.getElementById('user-input').value='';document.getElementById('usage-summary').textContent='';document.getElementById('session-funding-label').hidden=true;}
            await this.reloadLogsOnly();this.notify(LabI18n.t("standalone.app.deleteSession.3"));
        }catch(e){this.notify(e.message,true);}finally{this.busy=false;this.updateControls();}
    },
    escapeHtml(str) {return LabView.text(str);}
};
window.addEventListener('DOMContentLoaded', () => App.init().catch(e=>App.notify(LabI18n.t("app.ui.1")+e.message,true)));
