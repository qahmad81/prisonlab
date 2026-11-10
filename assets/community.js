/* Shared account/public UI. All untrusted strings are inserted as text or escaped. */
const LabCommunity = {
    async fetchJson(url,timeoutMs=30000) {
        const controller=new AbortController(),timer=setTimeout(()=>controller.abort(),timeoutMs);
        try{const response=await fetch(LabI18n.api(url),{signal:controller.signal});const data=await response.json();
            if(!data.ok)throw new Error(data.error || LabI18n.t("community.fetchJson.1"));return data;
        }catch(e){if(e.name==='AbortError')throw new Error(LabI18n.t("community.fetchJson.2"));throw e;}
        finally{clearTimeout(timer);}
    },
    money(value) {return Number.isFinite(Number(value))?'$'+Number(value).toFixed(3):LabI18n.t("community.money.1");},
    cost(s) {
        const c=s.cost;if(!c)return LabI18n.t("community.cost.1");
        const source=c.funding_source==='sponsor'?LabI18n.t("community.cost.2")+(c.sponsor_label || c.sponsor_profile?.display_name || ''):({site:LabI18n.t("community.cost.3"),personal:LabI18n.t("community.cost.4"),legacy:LabI18n.t("community.cost.5")})[c.funding_source] || '';
        const part=p=>p?.recorded_calls ? (p.reported_calls?this.money(p.total_usd)+(p.reported_calls<p.recorded_calls?' *':''):LabI18n.t("community.money.1")) : LabI18n.t("community.cost.6");
        const extra=c.free_sponsor && c.by_funding_part ? LabI18n.t("community.cost.7", {"label": c.free_sponsor.label, "value2": part(c.by_funding_part.free_actor), "value3": part(c.by_funding_part.primary)}) : '';
        return LabI18n.t("community.cost.9", {"value1": this.money(c.total_usd), "source": source, "extra": extra, "value4": c.complete?'':' *', "value5": Object.keys(s.mcp_actors || {}).length?LabI18n.t("community.cost.8"):''});
    },
    publicUrl(s) {return LabSharing.url({session_id:s.session_id});},
    operator(s) {
        const box=document.createElement('div');box.className='operator-line';const p=s.operator_profile;
        if(!p?.public_id){box.textContent=LabI18n.t("community.operator.1");return box;}
        const a=document.createElement('a');a.href=LabSharing.profile(p.public_id);
        a.dir='auto';
        if(p.avatar_url && /^https:\/\//.test(p.avatar_url)){const img=document.createElement('img');img.src=p.avatar_url;img.alt='';img.loading='lazy';img.referrerPolicy='no-referrer';img.addEventListener('error',()=>img.remove());a.appendChild(img);}
        a.appendChild(document.createTextNode(p.display_name || LabI18n.t("community.operator.2")));box.append(LabI18n.t("community.operator.3"),a);return box;
    },
    share(s) {
        const box=document.createElement('section');box.className='share-session';
        if(!s.is_published)return box;
        const a=document.createElement('a');a.className='btn linkedin-share';a.textContent=LabI18n.t("community.share.1");
        a.href='https://www.linkedin.com/sharing/share-offsite/?url='+encodeURIComponent(this.publicUrl(s));a.target='_blank';a.rel='noopener noreferrer';
        const p=document.createElement('p');p.textContent=LabI18n.t("community.share.2");
        box.append(a,p);return box;
    },
    result(s) {
        const box=document.createElement('section');box.className='publication-summary';
        box.appendChild(this.operator(s));const cost=document.createElement('p');cost.className='session-total-cost';cost.textContent=this.cost(s);box.appendChild(cost);if(s.cost?.sponsor_profile?.public_id)box.appendChild(LabSharing.link(LabI18n.t("community.result.1")+(s.cost.sponsor_label || s.cost.sponsor_profile.display_name),LabSharing.profile(s.cost.sponsor_profile.public_id)));if(s.is_published)box.appendChild(LabSharing.copy(this.publicUrl(s),LabI18n.t("community.result.2")));
        for(const [role,r] of Object.entries(s.rankings || {})){
            const p=document.createElement('p');p.textContent=LabI18n.t("community.result.6", {"value1": role==='prisoner'?LabI18n.t("community.result.3"):LabI18n.t("community.result.4"), "model": r.model, "rank": r.rank, "total": r.total, "value5": r.provisional?LabI18n.t("community.result.5"):''});box.appendChild(p);
            const compare=document.createElement('a');const params=new URLSearchParams({view:'leaderboard',model:r.model,role,
                case_file:s.case_file,level:String(r.level ?? ''),opponent_level:String(s[role==='prisoner'?'interrogator_character':'prisoner_character']?.level ?? 'human'),rubric:s.evaluation.rubric_version || 'legacy'});
            compare.href=LabI18n.pageUrl('public',params);compare.textContent=LabI18n.t("community.result.7");compare.className='text-button';box.appendChild(compare);
        }
        if(Object.keys(s.rankings || {}).length){const p=document.createElement('small');p.textContent=LabI18n.t("community.result.8");box.appendChild(p);}
        if(s.can_delete){
            const p=document.createElement('p');p.className='privacy-assurance';p.textContent=LabI18n.t("community.result.9");box.appendChild(p);
            const actions=document.createElement('div');actions.className='session-owner-actions';
            if(!s.is_published && s.evaluation_status==='complete'){
                const publish=document.createElement('button');publish.className='btn btn-primary';publish.textContent=LabI18n.t("community.result.10");publish.disabled=App.busy;
                publish.addEventListener('click',()=>App.publishSession());actions.appendChild(publish);
                const close=document.createElement('button');close.className='btn btn-secondary';close.textContent=LabI18n.t("community.result.11");close.addEventListener('click',()=>{App.currentSession=null;document.getElementById('chat-messages').replaceChildren();document.getElementById('usage-summary').textContent='';document.getElementById('active-session-id').textContent=LabI18n.t("community.result.12");App.updateControls();App.notify(LabI18n.t("community.result.13"));});actions.appendChild(close);
            }
            const remove=document.createElement('button');remove.className='btn danger-outline';remove.textContent=LabI18n.t("community.result.14");remove.disabled=App.busy || App.generating;
            remove.addEventListener('click',()=>App.deleteSession(s.session_id));actions.appendChild(remove);box.appendChild(actions);
        }
        if(s.is_published)box.appendChild(this.share(s));return box;
    }
};
