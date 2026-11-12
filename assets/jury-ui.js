/* Owner-only committee controls; accepted reports are immutable. */
const JuryUI={
    draft:[null,null,null,null],context:'setup',slot:0,retryIds:{},
    slots(){return this.context==='session'?(App.currentSession?.jury_slots || [null,null,null,null]):this.draft;},
    cap(){return this.context==='session'?(App.currentSession?.jury_price_cap ?? Infinity):(typeof App.fundingCap==='function'?App.fundingCap():App.userApiKey?Infinity:1);},
    locked(m){return !m || !!m.catalog_unavailable || Math.max(Number(m.pricing?.prompt_per_1m ?? Infinity),Number(m.pricing?.completion_per_1m ?? Infinity))>this.cap();},
    eligible(m){const s=this.context==='session'?App.currentSession:null;
        const ids=s?[s.model,s.interrogator_model]:[App.interrogationMode==='reverse'?'':App.selectedModelId,App.interrogationMode==='human'?'':App.selectedInterrogatorId];
        return !ids.includes(m.id) && !ids.some(id=>m.canonical_slug && App.models.find(a=>a.id===id)?.canonical_slug===m.canonical_slug) && !this.slots().some((id,i)=>i!==this.slot && id && (id===m.id || (m.canonical_slug && App.models.find(a=>a.id===id)?.canonical_slug===m.canonical_slug)));
    },
    open(slot,context){this.slot=slot;this.context=context;App.openModelModal('judge');},
    async choose(id){if(App.busy || App.generating)return;const m=App.models.find(m=>m.id===id);if(id && (this.locked(m) || !this.eligible(m)))return App.notify(LabI18n.t('jury.budget'),true);
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
            if(s?.is_ended && canEdit && id && !report){const retry=document.createElement('button');retry.type='button';retry.className='text-button jury-seat-retry';retry.textContent=LabI18n.t('jury.retrySeat');retry.disabled=App.busy || App.generating;retry.onclick=()=>this.retry(i);seat.append(retry);}
            grid.append(seat);
        });
        const note=document.createElement('p');note.className='jury-controls-note';const cap=s?s.jury_price_cap:(typeof App.fundingCap==='function'?App.fundingCap():App.userApiKey?null:1);note.textContent=cap==null || !Number.isFinite(cap)?LabI18n.t('jury.unlimited'):LabI18n.t('jury.cap',{cap});box.replaceChildren(heading,grid,note);
        if(s?.is_ended && s.can_manage_jury){const retry=document.createElement('button');retry.type='button';retry.className='btn btn-secondary jury-retry';retry.textContent=LabI18n.t('jury.retry');retry.disabled=App.busy || App.generating || !s.turns_count || (!s.evaluation_refresh_pending && !slots.some(id=>id && !s.judge_reports?.[id]));retry.onclick=()=>this.retry();box.append(retry);}
    }},
    async retry(slot=null){const s=App.currentSession;if(!s?.can_manage_jury || App.busy || App.generating)return;App.busy=true;App.updateControls();const stop=App.watchJury(s.session_id);const rid=this.retryIds[s.session_id] ||= App.requestId();
        try{const d=await App.api('retry_jury',{session_id:s.session_id,request_id:rid,...(slot===null?{}:{slots:[slot]})});App.currentSession=d.session;if(d.session.evaluation_status==='complete' && !d.session.evaluation_refresh_pending)delete this.retryIds[s.session_id];App.notify(d.session.evaluation_status==='complete' && !d.session.evaluation_refresh_pending?LabI18n.t('jury.finished'):d.session.evaluation_error || LabI18n.t('jury.failed'),d.session.evaluation_status!=='complete' || !!d.session.evaluation_refresh_pending);await App.reloadLogsOnly();}
        catch(e){App.notify(e.message,true);}finally{stop();App.busy=false;App.renderActiveSession();}
    }
};
window.addEventListener('DOMContentLoaded',()=>JuryUI.render());
