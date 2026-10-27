const SponsorUI={
    open(){document.getElementById('connection-toast').hidden=true;this.render();document.getElementById('sponsors-modal').classList.add('active');},
    choose(id){
        App.selectedSponsorId=id;sessionStorage.setItem('selected_sponsor_id',id);App.startRequestId=null;
        for(const role of ['prisoner','interrogator']){const field=role==='prisoner'?'selectedModelId':'selectedInterrogatorId';if(App.modelLocked(App.models.find(m=>m.id===App[field])))App[field]=App.models.find(m=>!App.modelLocked(m))?.id || '';}
        App.renderSelectedModelSummary();App.renderInterrogatorSummary();App.renderAccount();App.updateKeyButtonState();this.render();
        document.getElementById('sponsors-modal').classList.remove('active');App.notify('تغير تمويل التجربة التالية فقط؛ المحادثات السابقة تحتفظ بجهة تمويلها.');
    },
    selection(){
        const label=document.getElementById('active-sponsor-label');if(!label)return;
        const r=App.sponsors.find(r=>r.id===App.selectedSponsorId) || App.sponsors.find(r=>r.is_site);
        label.textContent=App.personalFunding()?'أنت برعاية '+(App.participantProfile?.display_name || 'نفسك')+' · مفتاحك':'أنت برعاية '+(r?.label || 'الموقع');
    },
    render(){
        this.selection();const grid=document.getElementById('sponsor-list');if(!grid)return;grid.replaceChildren();
        for(const r of App.sponsors){const card=document.createElement('article');card.className='sponsor-card';
            const heading=document.createElement('h3');heading.textContent=r.label;card.appendChild(heading);
            card.appendChild(LabSharing.link('صاحب الرعاية: '+r.profile.display_name,LabSharing.profile(r.profile.public_id)));
            const p=document.createElement('p');p.textContent=`${r.is_site?'مفتاح الموقع · ':''}${r.active?'متاح':'متوقف'} · ${r.remaining} من ${r.daily_limit} تجربة يوميًا لكل حساب LinkedIn · ${r.price_cap===0?'نماذج حوار مجانية فقط':'أقصى سعر $'+r.price_cap+' لكل مليون توكن'}`;card.appendChild(p);
            const choose=document.createElement('button');choose.type='button';choose.className='btn btn-primary';choose.textContent=App.selectedSponsorId===r.id?'✓ الراعي المختار':'استخدام تمويل هذا الراعي';choose.disabled=!r.active || r.remaining<1;choose.addEventListener('click',()=>this.choose(r.id));card.appendChild(choose);
            if(r.is_owner){const edit=document.createElement('button');edit.type='button';edit.className='text-button';edit.textContent='تعديل أو تبديل المفتاح';edit.addEventListener('click',()=>this.edit(r));card.appendChild(edit);
                const del=document.createElement('button');del.type='button';del.className='text-button danger-outline';del.textContent='حذف المفتاح';del.addEventListener('click',async()=>{if(!confirm('حذف مفتاح الرعاية من الخادم؟ لن يمكن متابعة التجارب المرتبطة به. الطلب الجاري لدى المزود لا يمكن إلغاؤه، وتبقى نسبة التمويل في السجلات السابقة.'))return;try{const d=await App.api('delete_sponsor',{id:r.id,confirm:r.id});App.sponsors=d.sponsors;if(App.selectedSponsorId===r.id){App.selectedSponsorId='';sessionStorage.removeItem('selected_sponsor_id');}App.renderAccount();App.renderSelectedModelSummary();App.renderInterrogatorSummary();this.render();}catch(e){App.notify(e.message,true);}});card.appendChild(del);}
            grid.appendChild(card);
        }
        if(!App.sponsors.length)grid.textContent='لم تُضف مفاتيح راعية بعد. يبقى مفتاح الموقع أو مفتاحك الشخصي متاحًا.';
        document.getElementById('sponsor-auth-notice').hidden=!!App.participantProfile;document.getElementById('sponsor-form').hidden=!App.participantProfile;
        document.getElementById('sponsor-personal').textContent=App.userApiKey?'استخدام مفتاحي الشخصي':'إضافة مفتاحي الشخصي';
    },
    edit(r){const f=document.getElementById('sponsor-form');f.reset();f.elements.id.value=r.id;f.elements.revision.value=r.revision;f.elements.label.value=r.label;f.elements.daily_limit.value=r.daily_limit;f.elements.price_cap.value=r.price_cap;f.elements.active.checked=r.active;f.elements.key.required=false;f.querySelector('h3').textContent='تعديل الرعاية · اترك المفتاح فارغًا للاحتفاظ به';f.scrollIntoView({block:'nearest'});},
    reset(){const f=document.getElementById('sponsor-form');f.reset();f.elements.id.value='';f.elements.revision.value='';f.elements.key.required=true;f.querySelector('h3').textContent='أضف مفتاحًا راعيًا';},
    async save(e){e.preventDefault();const f=e.target,b=f.querySelector('[type=submit]');b.disabled=true;
        try{const d=await App.api('save_sponsor',{id:f.elements.id.value,revision:Number(f.elements.revision.value),label:f.elements.label.value,key:f.elements.key.value,daily_limit:Number(f.elements.daily_limit.value),price_cap:Number(f.elements.price_cap.value),active:f.elements.active.checked});
            f.elements.key.value='';App.sponsors=d.sponsors;this.reset();App.renderAccount();App.renderSelectedModelSummary();App.renderInterrogatorSummary();this.render();document.getElementById('sponsor-save-status').textContent='حُفظ المفتاح مشفرًا في المساحة الخاصة؛ لا يُعرض للزوار.';
        }catch(err){document.getElementById('sponsor-save-status').textContent=err.message;}finally{b.disabled=false;}
    }
};
document.addEventListener('DOMContentLoaded',()=>{document.getElementById('sponsor-form').addEventListener('submit',e=>SponsorUI.save(e));});
