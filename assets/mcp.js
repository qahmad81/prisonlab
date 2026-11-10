/* External actors own their harness context. Gate credentials remain in memory only. */
const MCPUI={
    selections:{},urls:{},handoffs:{},waiting:false,pauseRequested:false,
    startConfig(){return Object.fromEntries(Object.entries(this.selections).filter(([role,v])=>v && !(role==='prisoner' && App.interrogationMode==='reverse') && !(role==='interrogator' && App.interrogationMode==='human')));},
    configure(role=App.modelTarget){
        this.returnFocus=document.activeElement;
        App.modelTarget=role;App.closeModelModal();
        const v=this.selections[role] || {};
        document.getElementById('mcp-name').value=v.name || '';
        document.getElementById('mcp-effort').value=v.effort || 'unknown';
        document.getElementById('mcp-role-title').textContent=LabI18n.t("mcp.configure.1")+(role==='prisoner'?LabI18n.t("mcp.configure.2"):LabI18n.t("mcp.configure.3"));
        const modal=document.getElementById('mcp-config-modal');modal.classList.add('active');
        modal.onkeydown=e=>{if(e.key==='Escape'){e.preventDefault();this.close();}if(e.key==='Tab'){const items=[...modal.querySelectorAll('button,input,select')],first=items[0],last=items.at(-1);if(e.shiftKey && document.activeElement===first){e.preventDefault();last.focus();}else if(!e.shiftKey && document.activeElement===last){e.preventDefault();first.focus();}}};
        document.getElementById('mcp-name').focus();
    },
    close(){document.getElementById('mcp-config-modal').classList.remove('active');document.getElementById(App.modelTarget==='prisoner'?'selected-model-card':'selected-interrogator-card').focus();},
    save(event){
        event.preventDefault();const name=document.getElementById('mcp-name').value.trim(),effort=document.getElementById('mcp-effort').value;
        if(name.length<2 || name.length>100)return;
        this.selections[App.modelTarget]={name,effort};App.startRequestId=null;
        this.close();
        App.renderSelectedModelSummary();App.renderInterrogatorSummary();
    },
    renderSelection(role){
        const v=this.selections[role];if(!v)return false;
        const card=document.getElementById(role==='prisoner'?'selected-model-card':'selected-interrogator-card');
        card.classList.remove('connection-weak');
        card.innerHTML=`<span class="selected-model-icon mcp-mark" aria-hidden="true">MCP</span><span class="selected-model-info"><strong class="selected-model-name"></strong><span class="selected-model-meta">${LabI18n.html("mcp.renderSelection.1")}</span><span class="model-card-badge badge-userkey">${LabI18n.html("mcp.renderSelection.2")}</span></span>`;
        card.querySelector('strong').textContent=v.name;
        const b=document.getElementById(role+'-reasoning-btn');b.disabled=false;b.textContent=LabView.reasoningLabel({source:'mcp',effort:v.effort})+LabI18n.t("mcp.renderSelection.3");
        if(role==='prisoner')document.getElementById('model-status-tip').textContent=LabI18n.t("mcp.renderSelection.4");
        return true;
    },
    async started(s){
        this.urls={};this.handoffs={};this.waiting=false;this.pauseRequested=false;
        if(!Object.keys(s.mcp_actors || {}).length)return;
        this.renderSession();document.getElementById('mcp-session-panel').open=true;
        for(const role of Object.keys(s.mcp_actors))await this.gate(role);
    },
    renderSession(){
        const panel=document.getElementById('mcp-session-panel'),s=App.currentSession;
        const roles=Object.keys(s?.mcp_actors || {});panel.hidden=!roles.length || !!s?.read_only;
        if(panel.hidden)return;
        const identity=s.session_id+':'+roles.join(':');
        if(panel.dataset.session!==identity){panel.dataset.session=identity;document.getElementById('mcp-gates').replaceChildren();
            for(const role of roles){
                const row=document.createElement('div');row.className='mcp-gate-row';row.dataset.role=role;
                const label=document.createElement('strong');label.textContent=(role==='prisoner'?LabI18n.t("mcp.configure.2"):LabI18n.t("mcp.configure.3"))+' · '+s.mcp_actors[role].name;
                const input=document.createElement('input');input.type='password';input.readOnly=true;input.dir='ltr';input.autocomplete='off';input.setAttribute('aria-label',LabI18n.t("mcp.renderSession.1")+(role==='prisoner'?LabI18n.t("mcp.configure.2"):LabI18n.t("mcp.configure.3")));input.placeholder=LabI18n.t("mcp.renderSession.2");
                input.hidden=true;
                const details=document.createElement('details'),summary=document.createElement('summary'),prompt=document.createElement('textarea');summary.textContent=LabI18n.t("mcp.renderSession.3");prompt.readOnly=true;prompt.className='mcp-handoff-prompt';prompt.setAttribute('aria-label',LabI18n.t("mcp.renderSession.4")+(role==='prisoner'?LabI18n.t("mcp.configure.2"):LabI18n.t("mcp.configure.3")));details.append(summary,prompt);
                const copy=document.createElement('button');copy.type='button';copy.className='btn btn-secondary copy-handoff';copy.setAttribute('aria-label',LabI18n.t("mcp.renderSession.5"));copy.title=LabI18n.t("mcp.renderSession.6");copy.innerHTML=`<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><rect x="8" y="8" width="12" height="13" rx="2"/><path d="M15 8V3H3v13h5"/></svg> ${LabI18n.html("mcp.renderSession.7")}`;copy.onclick=async()=>{const handoff=this.handoffs[s.session_id+role];if(!handoff)return App.notify(LabI18n.t("mcp.renderSession.8"),true);try{await navigator.clipboard.writeText(handoff);App.notify(LabI18n.t("mcp.renderSession.9"));}catch{details.open=true;prompt.focus();prompt.select();App.notify(LabI18n.t("mcp.renderSession.10"));}};
                const renew=document.createElement('button');renew.type='button';renew.className='text-button';renew.textContent=LabI18n.t("mcp.renderSession.11");renew.onclick=()=>this.gate(role);
                const revoke=document.createElement('button');revoke.type='button';revoke.className='text-button';revoke.textContent=LabI18n.t("mcp.renderSession.12");revoke.onclick=()=>this.gate(role,true);
                row.append(label,input,copy,renew,revoke,details);document.getElementById('mcp-gates').appendChild(row);
            }
        }
        for(const role of roles){const row=document.querySelector('.mcp-gate-row[data-role="'+role+'"]'),url=this.urls[s.session_id+role] || '';row.querySelector('input').value=url;row.querySelector('textarea').value=this.handoffs[s.session_id+role] || LabI18n.t("mcp.renderSession.13");for(const b of row.querySelectorAll('button'))b.disabled=!!s.is_ended;
            let status=row.querySelector('.mcp-gate-status');if(!status){status=document.createElement('small');status.className='mcp-gate-status';row.appendChild(status);}
            const state=s.mcp_status?.[role],recent=state?.connected_at && Date.now()-Date.parse(state.connected_at)<35000;
            status.textContent=!state?LabI18n.t("mcp.renderSession.14"):!state.active?LabI18n.t("mcp.renderSession.15"):(recent?LabI18n.t("mcp.renderSession.16"):LabI18n.t("mcp.renderSession.17"))+' · '+({not_requested:LabI18n.t("mcp.renderSession.18"),processed:LabI18n.t("mcp.renderSession.19"),reply_received:LabI18n.t("mcp.renderSession.20"),waiting_agent:LabI18n.t("mcp.renderSession.21")})[state.task_state];
        }
        document.getElementById('mcp-wait-state').textContent=s.is_ended?LabI18n.t("mcp.renderSession.22"):this.waiting?LabI18n.t("mcp.renderSession.23"):LabI18n.t("mcp.renderSession.24");
        document.getElementById('mcp-pause-btn').hidden=!this.waiting;
    },
    async gate(role,revoke=false){
        const s=App.currentSession;if(!s || s.is_ended || s.read_only)return;
        try{const d=await App.api('mcp_gate',{session_id:s.session_id,role,revoke});
            if(App.currentSession?.session_id!==s.session_id)return;
            const key=s.session_id+role;
            if(d.url){
                this.urls[key]=new URL(d.url,location.origin).href;
                this.handoffs[key]=d.handoff;
            }else{
                delete this.urls[key];
                delete this.handoffs[key];
            }
            this.renderSession();App.notify(revoke?LabI18n.t("mcp.gate.1"):LabI18n.t("mcp.gate.2"));
        }catch(e){App.notify(e.message,true);}
    }
};
window.addEventListener('DOMContentLoaded',()=>{
    document.getElementById('mcp-config-form').addEventListener('submit',e=>MCPUI.save(e));
    const chat=document.getElementById('chat-messages');chat.before(document.getElementById('mcp-session-panel'));
});
