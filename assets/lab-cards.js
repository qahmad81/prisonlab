/* Compact shared presentation. Navigation never grants access to private tests. */
const LabCards={
    esc(v){return LabView.escape(v);},
    data(){return typeof publicCatalog!=='undefined'?publicCatalog:typeof App!=='undefined'?{cases:App.cases,models:App.models}:{};},
    shortName(value){return String(value || '').replace(/^[^/]+\//,'').replace(/^[^:]+:\s*/,'');},
    modelName(s,role){const id=s[role==='prisoner'?'model':'interrogator_model'];return s.mcp_actors?.[role]?.name || this.shortName(this.data()?.models?.find(m=>m.id===id)?.name || id);},
    caseInfo(s) {
        const c = this.data()?.cases?.find(item => item.filename === (s.case_file || s.filename));
        const savedTitle = s.case_title === c?.title ? undefined : s.case_title;
        return {
            title: s.case_short_title || s.short_title || savedTitle || c?.short_title || s.title || LabI18n.t('lab-cards.caseInfo.fallback_title'),
            name: s.character_name || c?.character_name || LabI18n.t('lab-cards.caseInfo.fallback_name')
        };
    },
    stats(url,label,cls=''){if(!url)return '';return `<a class="stat-link ${cls}" href="${this.esc(url)}" title="${this.esc(label)}" aria-label="${this.esc(label)}"><img src="/assets/icons/statistics.svg" alt="" width="18" height="18"></a>`;},
    caseUrl(file){return typeof LabLinks!=='undefined'?LabLinks.case(file):null;},
    actorUrl(id,role){return typeof LabLinks!=='undefined'?LabLinks.model(id,role):null;},
    personaUrl(id){return id && typeof LabLinks!=='undefined'?LabLinks.investigator(id):null;},
    reasoning(r){return LabView.reasoningLabel(r).replace(/^MCP · /,'');},
    actor(s,role,scope={}){
        const prisoner=role==='prisoner',human=s.interrogator_type===(prisoner?'reverse':'human'),persona=prisoner?this.caseInfo(s).name:s.interrogator_character?.name || s.operator_profile?.display_name || LabI18n.t("lab-cards.actor.1");
        const hide=prisoner?!!scope.case_file && scope.case_file===s.case_file:!!scope.investigator && scope.investigator===s.interrogator_character?.id;
        const id=s[prisoner?'model':'interrogator_model'],personUrl=prisoner?this.caseUrl(s.case_file):this.personaUrl(s.interrogator_character?.id);
        return `<section class="test-actor"><img class="actor-role-icon" src="/assets/icons/${prisoner?'accused':'investigator'}.svg" alt="${prisoner?LabI18n.t("session-view.participants.1"):LabI18n.t("session-view.participants.2")}" width="26" height="26"><div>${!hide?`<div class="test-persona"><span>${this.esc(persona)}</span>${this.stats(personUrl,prisoner?LabI18n.t("lab-cards.actor.4"):LabI18n.t("lab-cards.actor.5"))}</div>`:''}<div class="test-model"><bdi>${this.esc(human?LabI18n.t("lab-cards.actor.6"):this.modelName(s,role))}</bdi>${human?'':this.stats(this.actorUrl(id,role),prisoner?LabI18n.t("lab-cards.actor.7"):LabI18n.t("lab-cards.actor.8"))}</div>${!human?`<small>${this.esc(this.reasoning(s[role+'_reasoning']))}${s.mcp_actors?.[role]?LabI18n.t("lab-cards.actor.9"):''}</small>`:''}</div></section>`;
    },
    funding(s){
        const source=s.cost?.funding_source || s.funding_source;
        const name=source==='personal'?LabI18n.t('lab-cards.funding.1'):source==='site'?LabI18n.t('standalone.funding.site'):source==='free'?LabI18n.t('standalone.lab-cards.funding.3'):LabI18n.t('lab-cards.funding.3');
        return {profile:null,name,url:null,source};
    },
    person(label,p,name,url){const avatar=/^https:\/\//.test(p?.avatar_url || '')?`<img src="${this.esc(p.avatar_url)}" alt="" width="25" height="25" referrerpolicy="no-referrer" loading="lazy">`:'<span class="person-placeholder" aria-hidden="true">●</span>';
        return `<div class="test-credit"><small>${label}</small><div>${avatar}<span dir="auto">${this.esc(name || LabI18n.t("lab-cards.funding.3"))}</span>${this.stats(url,label===LabI18n.t("lab-cards.funding.1")?LabI18n.t("lab-cards.person.1"):LabI18n.t("lab-cards.person.2"))}</div></div>`;},
    card(s,{open,scope={},rank=null}={}){
        const info=this.caseInfo(s),f=this.funding(s),op=s.operator_profile;
        const el=document.createElement('article');el.className='archive-card test-card';
        const ev=s.evaluation || {},p=ev.prisoner_score ?? ev.overall_score,i=ev.investigator_score;
        el.innerHTML=`<header class="test-heading">${rank?`<span class="test-rank">#${this.esc(rank)}</span>`:''}<h3>${this.esc(info.title)}</h3><div class="test-character-caption">${this.esc(info.name)}${this.stats(this.caseUrl(s.case_file),LabI18n.t("lab-cards.actor.4"))}</div></header><div class="test-cast"><div class="test-portraits"><img class="test-portrait" src="${LabView.face(s.character_slug,s.current_state)}" alt="${this.esc(info.name)}" width="64" height="84">${s.interrogator_type!=='human' && s.interrogator_character?LabView.investigatorAvatar(s.interrogator_character,'test-investigator-portrait'):''}</div><div class="test-actors">${this.actor(s,'prisoner',scope)}${this.actor(s,'interrogator',scope)}</div></div><div class="test-scores"><span>${LabI18n.html("lab-cards.card.3")} ${p==null?'—':this.esc(p)+'%'}</span><span>${LabI18n.html("lab-cards.card.4")} ${i==null?'—':this.esc(i)+'%'}</span><span>${this.esc(s.turns_count || 0)} ${LabI18n.html("lab-cards.card.5")}</span></div><div class="test-credits">${this.person(LabI18n.t("lab-cards.funding.1"),op,op?.display_name || LabI18n.t("standalone.lab-cards.card.1"),null)}${this.person(LabI18n.t("lab-cards.card.1"),f.profile,f.name,f.url)}</div><div class="test-cost"></div><div class="test-actions"><span class="test-date"></span><button type="button" class="btn btn-secondary test-open">${LabI18n.html("lab-cards.card.6")}</button></div>`;
        el.querySelector('.test-cost').textContent=LabCommunity.cost(s);
        if(!s.cost?.complete)el.querySelector('.test-cost').title=LabI18n.t("lab-cards.card.7");
        const date=s.published_at || s.updated_at || s.created_at;el.querySelector('.test-date').textContent=date?new Date(date).toLocaleDateString(LabI18n.locale):'';
        el.querySelector('.test-open').addEventListener('click',e=>{e.stopPropagation();open?.(s.session_id);});
        if(Object.keys(s.user_reviews || {}).length){const details=document.createElement('details');details.className='test-reviews';details.innerHTML=`<summary>${LabI18n.html("lab-cards.card.8")}</summary>`;for(const [role,review] of Object.entries(s.user_reviews)){const p=document.createElement('p');p.textContent=(role==='prisoner'?LabI18n.t("session-view.thought.1"):LabI18n.t("session-view.participants.2"))+' · '+review.rating+'/5: '+review.text;details.appendChild(p);}el.querySelector('.test-actions').before(details);}
        el.addEventListener('click',e=>{if(!e.target.closest('a,button,details'))open?.(s.session_id);});
        for(const img of el.querySelectorAll('.test-credit img'))img.addEventListener('error',()=>img.remove());
        return el;
    },
    setupCase(){const c=App.getSelectedCase(),button=document.getElementById('selected-case-summary');if(!c)return;
        const info=this.caseInfo(c);button.innerHTML=`<img class="selected-case-avatar" src="${LabView.face(c.character_slug,c.initial_state)}" alt="${this.esc(info.name)}"><span class="selected-case-info"><strong class="selected-case-name">${this.esc(info.name)}</strong><span class="selected-case-title">${this.esc(info.title)}</span></span>`;
        const tags=document.getElementById('case-setup-tags'),link=document.getElementById('case-share-link');tags.innerHTML=`<span class="case-card-state">${LabI18n.html("lab-cards.setupCase.2")} ${this.esc(c.initial_state_ar || LabI18n.t("session-view.emotionDescription.2"))}</span><span class="case-card-state">${LabI18n.html("lab-cards.setupCase.3")} ${this.esc(c.difficulty?.level_ar || '')} · ${c.difficulty?.level || ''}/10</span>`;
        if(link)tags.appendChild(link);this.setupLinks();
    },
    setupModel(role){const m=App.models.find(m=>m.id===App[role==='prisoner'?'selectedModelId':'selectedInterrogatorId']),external=typeof MCPUI!=='undefined'?MCPUI.selections[role]:null;
        const button=document.getElementById(role==='prisoner'?'selected-model-card':'selected-interrogator-card'),meta=document.getElementById(role+'-setup-meta');if(!m && !external)return;
        button.classList.toggle('connection-weak',!external && !!m?.health?.weak);
        const locked=!external && (typeof App.modelLocked==='function'?App.modelLocked(m):!!m.is_locked && !App.userApiKey);
        const badge=external?LabI18n.t("lab-cards.setupModel.1"):locked?LabI18n.t("lab-cards.setupModel.2"):m.is_locked?LabI18n.t("lab-cards.setupModel.3"):LabI18n.t("lab-cards.setupModel.4");
        button.innerHTML=`<span class="selected-model-icon">${external?'MCP':m.icon_svg || '🤖'}</span><span class="selected-model-info"><strong class="selected-model-name" dir="auto">${this.esc(external?external.name:this.shortName(m.name || m.id))}</strong><span class="model-card-badge ${locked?'badge-locked':'badge-unlocked'}">${this.esc(badge)}</span></span>`;
        meta.innerHTML=external?`<span>${LabI18n.html("lab-cards.setupModel.5")}</span>`:`<span>${this.esc(m.context_formatted || '')} ${LabI18n.html("lab-cards.setupModel.6")}</span><span>${LabI18n.html("lab-cards.setupModel.7")} <bdi>$${m.pricing.prompt_per_1m}</bdi></span><span>${LabI18n.html("lab-cards.setupModel.8")} <bdi>$${m.pricing.completion_per_1m}</bdi></span>`;meta.title=LabI18n.t("lab-cards.setupModel.9");
        const reasoning=external?{source:'mcp',effort:external.effort}:App.reasoningPreview(role,m);const rb=document.getElementById(role+'-reasoning-btn');if(external)rb.disabled=false;rb.textContent=this.reasoning(reasoning)+(rb.disabled?'':LabI18n.t("lab-cards.setupModel.10"));
        this.setupLinks();
    },
    setupInvestigator(p){if(!p)return;document.getElementById('selected-investigator-persona').innerHTML=`${LabView.investigatorAvatar(p)}<span class="selected-case-info"><strong class="selected-case-name" dir="ltr">${this.esc(p.name)}</strong><span class="selected-case-title">${this.esc(p.specialty)}</span></span>`;
        const tags=document.getElementById('investigator-setup-tags'),link=document.getElementById('investigator-share-link');tags.innerHTML=`<span class="case-card-state">${LabI18n.html("lab-cards.setupInvestigator.1")}</span><span class="case-card-state">${LabI18n.html("lab-cards.setupCase.3")} ${this.esc(p.level_ar)} · ${p.level}/10</span>`;if(link)tags.appendChild(link);this.setupLinks();},
    setupLinks(){if(typeof LabLinks==='undefined')return;
        const links=[['case-share-link',this.caseUrl(App.selectedCaseFilename),LabI18n.t("lab-cards.setupLinks.1")],['investigator-share-link',this.personaUrl(App.selectedInvestigatorPersona),LabI18n.t("lab-cards.actor.5")]];
        for(const role of ['prisoner','interrogator']){const v=typeof MCPUI!=='undefined'?MCPUI.selections[role]:null;links.push([role+'-share-link',v?.id?this.actorUrl(v.id,role):v?null:this.actorUrl(App[role==='prisoner'?'selectedModelId':'selectedInterrogatorId'],role),LabI18n.t("lab-cards.setupLinks.2")+(role==='prisoner'?LabI18n.t("session-view.participants.1"):LabI18n.t("session-view.participants.2"))]);}
        for(const [id,url,label] of links){const a=document.getElementById(id);if(!a)continue;a.hidden=!url;if(url)a.href=url;a.removeAttribute('target');a.className='stat-link';a.title=label;a.setAttribute('aria-label',label);a.innerHTML='<img src="/assets/icons/statistics.svg" alt="" width="18" height="18">';}
    }
};
