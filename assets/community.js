/* Standalone archive/public UI. All untrusted strings are inserted as text or escaped. */
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
        const c=s.cost;if(!c)return LabI18n.t("session-view.usage.2");
        const source=({site:LabI18n.t("community.cost.3"),personal:LabI18n.t("community.cost.4"),free:LabI18n.t("standalone.community.cost.4"),legacy:LabI18n.t("community.cost.5")})[c.funding_source] || '';
        const part=p=>p?.recorded_calls ? (p.reported_calls?this.money(p.total_usd)+(p.reported_calls<p.recorded_calls?' *':''):LabI18n.t("community.money.1")) : LabI18n.t("community.cost.6");
        const extra=c.free_funding_mode==='mixed'?LabI18n.t("standalone.community.cost.7", {"value1": part(c.by_funding_part?.free_actor), "value2": part(c.by_funding_part?.primary)}):'';
        return LabI18n.t("community.cost.9", {"value1": this.money(c.total_usd), "source": source, "extra": extra, "value4": c.complete?'':' *', "value5": Object.keys(s.mcp_actors || {}).length?LabI18n.t("standalone.community.cost.8"):''});
    },
    operator() {const label=document.createElement('span');label.className='operator-line';label.textContent=LabI18n.t("standalone.community.operator.1");return label;},
    result(s) {
        const box=document.createElement('section');box.className='publication-summary';
        box.appendChild(this.operator(s));const cost=document.createElement('p');cost.className='session-total-cost';cost.textContent=this.cost(s);box.appendChild(cost);
        if(s.can_delete){
            const p=document.createElement('p');p.className='privacy-assurance';p.textContent=LabI18n.t("standalone.community.result.1");box.appendChild(p);
            const actions=document.createElement('div');actions.className='session-owner-actions';
            if(!s.is_published && s.evaluation_status==='complete'){
                const publish=document.createElement('button');publish.className='btn btn-primary';publish.textContent=LabI18n.t("community.result.10");publish.disabled=App.busy;
                publish.addEventListener('click',()=>App.publishSession());actions.appendChild(publish);
                const close=document.createElement('button');close.className='btn btn-secondary';close.textContent=LabI18n.t("community.result.11");close.addEventListener('click',()=>{App.currentSession=null;document.getElementById('chat-messages').replaceChildren();document.getElementById('usage-summary').textContent='';document.getElementById('active-session-id').textContent=LabI18n.t("community.result.12");App.updateControls();App.notify(LabI18n.t("community.result.13"));});actions.appendChild(close);
            }
            const remove=document.createElement('button');remove.className='btn danger-outline';remove.textContent=LabI18n.t("community.result.14");remove.disabled=App.busy || App.generating;
            remove.addEventListener('click',()=>App.deleteSession(s.session_id));actions.appendChild(remove);box.appendChild(actions);
        }
        return box;
    }
};
