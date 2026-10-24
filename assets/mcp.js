/* External actors own their harness context. Gate credentials remain in memory only. */
const MCPUI={
    selections:{},urls:{},waiting:false,pauseRequested:false,
    startConfig(){return Object.fromEntries(Object.entries(this.selections).filter(([role,v])=>v && !(role==='prisoner' && App.interrogationMode==='reverse') && !(role==='interrogator' && App.interrogationMode==='human')));},
    configure(role=App.modelTarget){
        this.returnFocus=document.activeElement;
        App.modelTarget=role;App.closeModelModal();
        const v=this.selections[role] || {};
        document.getElementById('mcp-name').value=v.name || '';
        document.getElementById('mcp-effort').value=v.effort || 'unknown';
        document.getElementById('mcp-role-title').textContent='ربط وكيلك بدور '+(role==='prisoner'?'المتهم':'المحقق');
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
        card.innerHTML='<span class="selected-model-icon mcp-mark" aria-hidden="true">MCP</span><span class="selected-model-info"><strong class="selected-model-name"></strong><span class="selected-model-meta">وكيل خارجي · مواردك وهارنِسك · تغيير ↗</span><span class="model-card-badge badge-userkey">الهوية والتفكير معلنان من المشغّل</span></span>';
        card.querySelector('strong').textContent=v.name;
        const b=document.getElementById(role+'-reasoning-btn');b.disabled=false;b.textContent=LabView.reasoningLabel({source:'mcp',effort:v.effort})+' · تعديل ↗';
        if(role==='prisoner')document.getElementById('model-status-tip').textContent='تكلفة الوكيل الخارجي غير مقاسة؛ تبقى مراحل JEV والتحكيم على جهة التمويل المختارة.';
        return true;
    },
    async started(s){
        this.urls={};this.waiting=false;this.pauseRequested=false;
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
                const label=document.createElement('strong');label.textContent=(role==='prisoner'?'المتهم':'المحقق')+' · '+s.mcp_actors[role].name;
                const input=document.createElement('input');input.type='password';input.readOnly=true;input.dir='ltr';input.autocomplete='off';input.setAttribute('aria-label','رابط بوابة '+(role==='prisoner'?'المتهم':'المحقق'));input.placeholder='أنشئ رابط اتصال خاصًا بهذا الدور';
                const copy=document.createElement('button');copy.type='button';copy.className='btn btn-secondary';copy.textContent='نسخ رابط MCP';copy.onclick=async()=>{const url=this.urls[s.session_id+role];if(!url)return App.notify('أنشئ الرابط أولًا.',true);try{await navigator.clipboard.writeText(url);App.notify('نُسخ رابط البوابة الخاص. أضفه كخادم MCP عبر HTTP في هارنس الوكيل.');}catch{input.type='text';input.select();App.notify('انسخ الرابط المحدد ثم احفظه في وكيلك.');}};
                const renew=document.createElement('button');renew.type='button';renew.className='text-button';renew.textContent='إنشاء / تجديد الرابط';renew.onclick=()=>this.gate(role);
                const revoke=document.createElement('button');revoke.type='button';revoke.className='text-button';revoke.textContent='إبطال البوابة';revoke.onclick=()=>this.gate(role,true);
                row.append(label,input,copy,renew,revoke);document.getElementById('mcp-gates').appendChild(row);
            }
        }
        for(const role of roles){const row=document.querySelector('.mcp-gate-row[data-role="'+role+'"]');row.querySelector('input').value=this.urls[s.session_id+role] || '';for(const b of row.querySelectorAll('button'))b.disabled=!!s.is_ended;}
        document.getElementById('mcp-wait-state').textContent=s.is_ended?'أُغلقت أدوار التحقيق.':this.waiting?'بانتظار الوكيل الخارجي؛ يمكنك نسخ الرابط أثناء الانتظار.':'جاهز · يبدأ الوكيل باستدعاء prisonlab_wait ثم يرسل prisonlab_reply.';
        document.getElementById('mcp-pause-btn').hidden=!this.waiting;
    },
    async gate(role,revoke=false){
        const s=App.currentSession;if(!s || s.is_ended || s.read_only)return;
        try{const d=await App.api('mcp_gate',{session_id:s.session_id,role,revoke});
            if(App.currentSession?.session_id!==s.session_id)return;
            if(d.url)this.urls[s.session_id+role]=new URL(d.url,location.href).href;else delete this.urls[s.session_id+role];
            this.renderSession();App.notify(revoke?'أُبطلت البوابة. يمكنك إنشاء رابط جديد عند الحاجة.':'رابط الدور جاهز لمدة 24 ساعة. تجديده يُبطل الرابط السابق ويحفظ المهمة الحالية.');
        }catch(e){App.notify(e.message,true);}
    }
};
window.addEventListener('DOMContentLoaded',()=>{
    document.getElementById('mcp-config-form').addEventListener('submit',e=>MCPUI.save(e));
    const chat=document.getElementById('chat-messages');chat.before(document.getElementById('mcp-session-panel'));
});
