/* Standalone archive/public UI. All untrusted strings are inserted as text or escaped. */
const LabCommunity = {
    async fetchJson(url,timeoutMs=30000) {
        const controller=new AbortController(),timer=setTimeout(()=>controller.abort(),timeoutMs);
        try{const response=await fetch(url,{signal:controller.signal});const data=await response.json();
            if(!data.ok)throw new Error(data.error || 'تعذر تحميل البيانات');return data;
        }catch(e){if(e.name==='AbortError')throw new Error('انتهت مهلة الاتصال. أعد المحاولة؛ سجلاتك محفوظة.');throw e;}
        finally{clearTimeout(timer);}
    },
    money(value) {return Number.isFinite(Number(value))?'$'+Number(value).toFixed(3):'غير متاح';},
    cost(s) {
        const c=s.cost;if(!c)return 'التكلفة غير متاحة';
        const source=({site:'على نفقة الموقع',personal:'بالمفتاح الشخصي',free:'تمويل النماذج المجانية',legacy:'تجربة سابقة'})[c.funding_source] || '';
        const part=p=>p?.recorded_calls ? (p.reported_calls?this.money(p.total_usd)+(p.reported_calls<p.recorded_calls?' *':''):'غير متاح') : 'لا استدعاءات';
        const extra=c.free_funding_mode==='mixed'?` · الدور المجاني: ${part(c.by_funding_part?.free_actor)} · التمويل الأصلي: ${part(c.by_funding_part?.primary)}`:'';
        return `التكلفة ${this.money(c.total_usd)} · ${source}${extra}${c.complete?'':' *'}${Object.keys(s.mcp_actors || {}).length?' · MCP: تكلفة خارجية غير مقاسة':''}`;
    },
    operator() {const label=document.createElement('span');label.className='operator-line';label.textContent='تجربة في النسخة المستقلة';return label;},
    result(s) {
        const box=document.createElement('section');box.className='publication-summary';
        box.appendChild(this.operator(s));const cost=document.createElement('p');cost.className='session-total-cost';cost.textContent=this.cost(s);box.appendChild(cost);
        if(s.can_delete){
            const p=document.createElement('p');p.className='privacy-assurance';p.textContent='هذه المحادثة محفوظة في أرشيف هذا المتصفح. النشر اختياري، ويمكنك حذفها نهائيًا من المختبر في أي وقت، حتى بعد النشر.';box.appendChild(p);
            const actions=document.createElement('div');actions.className='session-owner-actions';
            if(!s.is_published && s.evaluation_status==='complete'){
                const publish=document.createElement('button');publish.className='btn btn-primary';publish.textContent='نشر التجربة';publish.disabled=App.busy;
                publish.addEventListener('click',()=>App.publishSession());actions.appendChild(publish);
                const close=document.createElement('button');close.className='btn btn-secondary';close.textContent='إغلاق وحفظها خاصة';close.addEventListener('click',()=>{App.currentSession=null;document.getElementById('chat-messages').replaceChildren();document.getElementById('usage-summary').textContent='';document.getElementById('active-session-id').textContent='لم تبدأ الجلسة بعد';App.updateControls();App.notify('محفوظة في سجلاتك الخاصة؛ يمكنك فتحها أو حذفها لاحقًا.');});actions.appendChild(close);
            }
            const remove=document.createElement('button');remove.className='btn danger-outline';remove.textContent='حذف المحادثة نهائيًا';remove.disabled=App.busy || App.generating;
            remove.addEventListener('click',()=>App.deleteSession(s.session_id));actions.appendChild(remove);box.appendChild(actions);
        }
        return box;
    }
};
