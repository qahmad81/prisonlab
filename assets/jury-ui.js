/* Owner-only committee controls; accepted reports are immutable. */
const JuryUI={
    draft:[null,null,null,null],context:'setup',slot:0,retryIds:{},waitTimes:{},
    defaultWait(s){return ({economic:100,medium:180,professional:300})[s.jury_policy?.tier || s.jury_preview?.tier] || s.jury_policy?.seconds || s.jury_preview?.seconds || 100;},
    waitKey(s,slot){return s.session_id+':'+(slot ?? 'all');},
    retryControl(s,slot,button){const key=this.waitKey(s,slot);this.waitTimes[key] ??= String(this.defaultWait(s));
        const row=document.createElement('div');row.className='jury-retry-controls';const label=document.createElement('label');label.textContent=LabI18n.t('jury.wait');
        const input=document.createElement('input');input.type='number';input.min='1';input.max='900';input.step='1';input.value=this.waitTimes[key];input.className='jury-wait-seconds';input.dataset.slot=slot ?? 'all';input.disabled=button.disabled;input.setAttribute('aria-label',LabI18n.t('jury.wait'));input.title=LabI18n.t('jury.waitHint');input.oninput=()=>{this.waitTimes[key]=input.value;};label.append(input,document.createTextNode(LabI18n.t('jury.seconds')));row.append(label,button);return row;
    },
    slots(){return this.context==='session'?(App.currentSession?.jury_slots || [null,null,null,null]):this.draft;},
    fundingCap(context){const cap=context==='session'?(App.currentSession?.jury_price_cap ?? Infinity):(typeof App.fundingCap==='function'?App.fundingCap():App.userApiKey?Infinity:1);return cap===0?1:cap;},
    cap(){return this.fundingCap(this.context);},
    locked(m){return !m || !!m.catalog_unavailable || Math.max(Number(m.pricing?.prompt_per_1m ?? Infinity),Number(m.pricing?.completion_per_1m ?? Infinity))>this.cap();},
    eligible(m){const s=this.context==='session'?App.currentSession:null;
        const ids=s?[s.model,s.interrogator_model]:[App.interrogationMode==='reverse'?'':App.selectedModelId,App.interrogationMode==='human'?'':App.selectedInterrogatorId];
        return !ids.includes(m.id) && !ids.some(id=>m.canonical_slug && App.models.find(a=>a.id===id)?.canonical_slug===m.canonical_slug) && !this.slots().some((id,i)=>i!==this.slot && id && (id===m.id || (m.canonical_slug && App.models.find(a=>a.id===id)?.canonical_slug===m.canonical_slug)));
    },
    open(slot,context){this.slot=slot;this.context=context;App.openModelModal('judge');},
    async choose(id){if(App.busy || App.generating)return;const m=App.models.find(m=>m.id===id);if(id && (!m || m.catalog_unavailable))return App.notify(LabI18n.t('jury.unavailable'),true);if(id && this.locked(m))return App.notify(LabI18n.t('jury.priceError',{cap:this.cap()}),true);if(id && !this.eligible(m))return App.notify(LabI18n.t('jury.distinctError'),true);
        const slots=[...this.slots()];slots[this.slot]=id;
        if(this.context==='setup'){this.draft=slots;App.startRequestId=null;App.closeModelModal();this.render();return;}
        const sid=App.currentSession?.session_id;App.busy=true;App.updateControls();
        try{const d=await App.api('set_jury',{session_id:sid,jury_slots:slots});if(App.currentSession?.session_id===sid)App.currentSession=d.session;delete this.retryIds[sid];App.closeModelModal();App.renderActiveSession();}
        catch(e){App.notify(e.message,true);}finally{App.busy=false;App.updateControls();}
    },
    render(){for(const context of ['setup','session']){const box=document.getElementById('jury-'+context+'-panel');if(!box)continue;const s=context==='session'?App.currentSession:null;box.hidden=context==='session' && !s;if(box.hidden)continue;
        const slots=s?.jury_slots || (s?Array.from({length:4},(_,i)=>s.judge_models?.[i] || null):this.draft),canEdit=!s || s.can_manage_jury;
        const heading=document.createElement('strong');heading.textContent=LabI18n.t('jury.title');const grid=document.createElement('div');grid.className='jury-seats';
        slots.forEach((id,i)=>{const report=s?.judge_reports?.[id],progress=s?.judge_progress?.[id],seat=document.createElement('article');seat.className='jury-seat'+(report?' complete':'');seat.dataset.slot=i;
            const name=document.createElement('b');name.textContent=LabI18n.t('jury.seat',{number:i+1});const button=document.createElement('button');button.type='button';button.className='text-button jury-model-button';button.textContent=App.models.find(m=>m.id===id)?.name || id || LabI18n.t(s?'jury.empty':'jury.auto');button.title=id || button.textContent;button.disabled=!canEdit || !!report || App.busy || App.generating;button.onclick=()=>this.open(i,context);
            const state=document.createElement('small');state.textContent=report?LabI18n.t('jury.accepted'):progress?.status==='running'?LabI18n.t('jury.running'):progress?LabI18n.t('jury.failed'):LabI18n.t('jury.pending');seat.append(name,button,state);
            if(id && canEdit && !report){const clear=document.createElement('button');clear.type='button';clear.className='text-button';clear.textContent='×';clear.title=LabI18n.t('jury.clear');clear.setAttribute('aria-label',clear.title);clear.disabled=App.busy || App.generating;clear.onclick=()=>{this.slot=i;this.context=context;this.choose(null);};seat.append(clear);}
            if(s?.is_ended && canEdit && id && !report){const retry=document.createElement('button');retry.type='button';retry.className='text-button jury-seat-retry';retry.textContent=LabI18n.t('jury.retrySeat');retry.disabled=App.busy || App.generating;retry.onclick=()=>this.retry(i);seat.append(this.retryControl(s,i,retry));}
            grid.append(seat);
        });
        const note=document.createElement('p');note.className='jury-controls-note';const cap=this.fundingCap(context);note.textContent=cap==null || !Number.isFinite(cap)?LabI18n.t('jury.unlimited'):LabI18n.t('jury.cap',{cap});if(s){const live=document.getElementById('jury-live-status'),notice=document.getElementById('session-notice'),funding=document.getElementById('session-funding-label');box.replaceChildren(heading,live,notice,grid,note,funding);}else box.replaceChildren(heading,grid,note);
        if(s?.is_ended && s.can_manage_jury){const retry=document.createElement('button');retry.type='button';retry.className='btn btn-secondary jury-retry';retry.textContent=LabI18n.t('jury.retry');retry.disabled=App.busy || App.generating || !s.turns_count || (!s.evaluation_refresh_pending && !slots.some(id=>id && !s.judge_reports?.[id]));retry.onclick=()=>this.retry();box.append(this.retryControl(s,null,retry));}
    }},
    async retry(slot=null){const s=App.currentSession;if(!s?.can_manage_jury || App.busy || App.generating)return;const seconds=Number(this.waitTimes[this.waitKey(s,slot)] ?? this.defaultWait(s));if(!Number.isInteger(seconds) || seconds<1 || seconds>900)return App.notify(LabI18n.t('jury.invalidWait'),true);App.busy=true;App.updateControls();document.getElementById('jury-session-panel').scrollTop=0;const stop=App.watchJury(s.session_id);const rid=this.retryIds[s.session_id] ||= App.requestId();
        try{const d=await App.api('retry_jury',{session_id:s.session_id,request_id:rid,timeout_seconds:seconds,...(slot===null?{}:{slots:[slot]})});App.currentSession=d.session;if(!d.session.evaluation_refresh_pending)delete this.retryIds[s.session_id];App.notify(d.session.evaluation_status==='complete' && !d.session.evaluation_refresh_pending && !d.session.evaluation_error?LabI18n.t('jury.finished'):d.session.evaluation_error || LabI18n.t('jury.failed'),d.session.evaluation_status!=='complete' || !!d.session.evaluation_refresh_pending || !!d.session.evaluation_error);await App.reloadLogsOnly();}
        catch(e){App.notify(e.message,true);}finally{stop();App.busy=false;App.renderActiveSession();}
    }
};
window.addEventListener('DOMContentLoaded',()=>JuryUI.render());
