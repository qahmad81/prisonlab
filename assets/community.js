/* Shared account/public UI. All untrusted strings are inserted as text or escaped. */
const LabCommunity = {
    money(value) {return Number.isFinite(Number(value))?'$'+Number(value).toFixed(5):'غير متاح';},
    cost(s) {
        const c=s.cost;if(!c)return 'التكلفة غير متاحة';
        const source=({site:'على نفقة الموقع',personal:'بالمفتاح الشخصي',legacy:'تجربة سابقة'})[c.funding_source] || '';
        return `التكلفة المبلغ عنها ${this.money(c.total_usd)} · ${source}${c.complete?'':' · المبلغ المتاح فقط؛ بعض الاستدعاءات بلا تكلفة مبلغ عنها'}`;
    },
    publicUrl(s) {return new URL('public.php?session_id='+encodeURIComponent(s.session_id),location.href).href;},
    operator(s) {
        const box=document.createElement('div');box.className='operator-line';const p=s.operator_profile;
        if(!p?.public_id){box.textContent='تجربة سابقة دون حساب مرتبط';return box;}
        const a=document.createElement('a');a.href='public.php?profile='+encodeURIComponent(p.public_id);
        a.dir='auto';
        if(p.avatar_url && /^https:\/\//.test(p.avatar_url)){const img=document.createElement('img');img.src=p.avatar_url;img.alt='';img.loading='lazy';img.referrerPolicy='no-referrer';img.addEventListener('error',()=>img.remove());a.appendChild(img);}
        a.appendChild(document.createTextNode(p.display_name || 'مشارك'));box.append('المشارك / مشغل المحاكاة: ',a);return box;
    },
    share(s) {
        const box=document.createElement('section');box.className='share-session';
        if(!s.is_published)return box;
        const a=document.createElement('a');a.className='btn linkedin-share';a.textContent='مشاركة التجربة على LinkedIn ↗';
        a.href='https://www.linkedin.com/sharing/share-offsite/?url='+encodeURIComponent(this.publicUrl(s));a.target='_blank';a.rel='noopener noreferrer';
        const p=document.createElement('p');p.textContent='يفتح محرر LinkedIn لمراجعة منشورك وإرساله بنفسك. حذف التجربة من المختبر لا يحذف منشور LinkedIn أو نسخه الخارجية.';
        box.append(a,p);return box;
    },
    result(s) {
        const box=document.createElement('section');box.className='publication-summary';
        box.appendChild(this.operator(s));const cost=document.createElement('p');cost.className='session-total-cost';cost.textContent=this.cost(s);box.appendChild(cost);
        for(const [role,r] of Object.entries(s.rankings || {})){
            const p=document.createElement('p');p.textContent=`${role==='prisoner'?'المتهم':'المحقق'} · ${r.model}: المرتبة ${r.rank} من ${r.total}${r.provisional?' إذا نشرت الآن (ترتيب تقديري يتغير مع التجارب الجديدة)':''}.`;box.appendChild(p);
            const compare=document.createElement('a');const params=new URLSearchParams({view:'leaderboard',model:r.model,role,
                case_file:s.case_file,level:String(r.level ?? ''),opponent_level:String(s[role==='prisoner'?'interrogator_character':'prisoner_character']?.level ?? 'human'),rubric:s.evaluation.rubric_version || 'legacy'});
            compare.href='public.php?'+params;compare.textContent='عرض نتائج المقارنة المنشورة';compare.className='text-button';box.appendChild(compare);
        }
        if(Object.keys(s.rankings || {}).length){const p=document.createElement('small');p.textContent='المرتبة تقارن نفس الموديل والدور والقضية ومستويَي الشخصيتين وإصدار معيار التحكيم. التعادلات تشترك في المرتبة.';box.appendChild(p);}
        if(s.can_delete){
            const p=document.createElement('p');p.className='privacy-assurance';p.textContent='هذه المحادثة محفوظة في ملفك. النشر اختياري، ويمكنك حذفها نهائيًا من المختبر في أي وقت، حتى بعد النشر.';box.appendChild(p);
            const actions=document.createElement('div');actions.className='session-owner-actions';
            if(!s.is_published && s.evaluation_status==='complete'){
                const publish=document.createElement('button');publish.className='btn btn-primary';publish.textContent='نشر التجربة';publish.disabled=App.busy;
                publish.addEventListener('click',()=>App.publishSession());actions.appendChild(publish);
                const close=document.createElement('button');close.className='btn btn-secondary';close.textContent='إغلاق وحفظها خاصة';close.addEventListener('click',()=>{App.currentSession=null;document.getElementById('chat-messages').replaceChildren();document.getElementById('usage-summary').textContent='';document.getElementById('active-session-id').textContent='لم تبدأ الجلسة بعد';App.updateControls();App.notify('محفوظة في سجلاتك الخاصة؛ يمكنك فتحها أو حذفها لاحقًا.');});actions.appendChild(close);
            }
            const remove=document.createElement('button');remove.className='btn danger-outline';remove.textContent='حذف المحادثة نهائيًا';remove.disabled=App.busy || App.generating;
            remove.addEventListener('click',()=>App.deleteSession(s.session_id));actions.appendChild(remove);box.appendChild(actions);
        }
        if(s.is_published)box.appendChild(this.share(s));return box;
    }
};
