const SponsorUI={
    open(){document.getElementById('connection-toast').hidden=true;this.render();document.getElementById('sponsors-modal').classList.add('active');},
    choose(id){
        App.selectedSponsorId=id;sessionStorage.setItem('selected_sponsor_id',id);App.startRequestId=null;
        for(const role of ['prisoner','interrogator']){const field=role==='prisoner'?'selectedModelId':'selectedInterrogatorId';if(App.modelLocked(App.models.find(m=>m.id===App[field])))App[field]=App.models.find(m=>!App.modelLocked(m))?.id || '';}
        App.renderSelectedModelSummary();App.renderInterrogatorSummary();App.renderAccount();App.updateKeyButtonState();this.render();
        document.getElementById('sponsors-modal').classList.remove('active');App.notify(LabI18n.t("sponsors.choose.1"));
    },
    selection(){
        const label=document.getElementById('active-sponsor-label');if(!label)return;
        const r=App.sponsors.find(r=>r.id===App.selectedSponsorId) || App.sponsors.find(r=>r.is_site);
        label.textContent=App.personalFunding()?LabI18n.t("sponsors.selection.1")+(App.participantProfile?.display_name || LabI18n.t("sponsors.selection.2"))+LabI18n.t("sponsors.selection.3"):LabI18n.t("sponsors.selection.1")+(r?.label || LabI18n.t("sponsors.selection.4"));
    },
    render(){
        this.selection();const grid=document.getElementById('sponsor-list');if(!grid)return;grid.replaceChildren();
        for(const r of App.sponsors){const card=document.createElement('article');card.className='sponsor-card';
            const heading=document.createElement('h3');heading.textContent=r.label;card.appendChild(heading);
            card.appendChild(LabSharing.link(LabI18n.t("sponsors.render.1")+r.profile.display_name,LabSharing.profile(r.profile.public_id)));
            const p=document.createElement('p');p.textContent=LabI18n.t("sponsors.render.8", {"value1": r.is_site?LabI18n.t("sponsors.render.2"):'', "value2": r.active?LabI18n.t("sponsors.render.3"):LabI18n.t("sponsors.render.4"), "remaining": r.remaining, "daily_limit": r.daily_limit, "value5": r.price_cap===0?LabI18n.t("sponsors.render.5"):LabI18n.t("sponsors.render.6")+r.price_cap+LabI18n.t("sponsors.render.7")});card.appendChild(p);
            const choose=document.createElement('button');choose.type='button';choose.className='btn btn-primary';choose.textContent=App.selectedSponsorId===r.id?LabI18n.t("sponsors.render.9"):LabI18n.t("sponsors.render.10");choose.disabled=!r.active || r.remaining<1;choose.addEventListener('click',()=>this.choose(r.id));card.appendChild(choose);
            if(r.is_owner){const edit=document.createElement('button');edit.type='button';edit.className='text-button';edit.textContent=LabI18n.t("sponsors.render.11");edit.addEventListener('click',()=>this.edit(r));card.appendChild(edit);
                const del=document.createElement('button');del.type='button';del.className='text-button danger-outline';del.textContent=LabI18n.t("sponsors.render.12");del.addEventListener('click',async()=>{if(!confirm(LabI18n.t("sponsors.render.13")))return;try{const d=await App.api('delete_sponsor',{id:r.id,confirm:r.id});App.sponsors=d.sponsors;if(App.selectedSponsorId===r.id){App.selectedSponsorId='';sessionStorage.removeItem('selected_sponsor_id');}App.renderAccount();App.renderSelectedModelSummary();App.renderInterrogatorSummary();this.render();}catch(e){App.notify(e.message,true);}});card.appendChild(del);}
            grid.appendChild(card);
        }
        if(!App.sponsors.length)grid.textContent=LabI18n.t("sponsors.render.14");
        document.getElementById('sponsor-auth-notice').hidden=!!App.participantProfile;document.getElementById('sponsor-form').hidden=!App.participantProfile;
    },
    edit(r){const f=document.getElementById('sponsor-form');f.reset();f.elements.id.value=r.id;f.elements.revision.value=r.revision;f.elements.label.value=r.label;f.elements.daily_limit.value=r.daily_limit;f.elements.price_cap.value=r.price_cap;f.elements.budget_daily_usd.value=r.budget_daily_usd ?? '';f.elements.budget_total_usd.value=r.budget_total_usd ?? '';f.elements.active.checked=r.active;f.elements.key.required=false;f.querySelector('h3').textContent=LabI18n.t("sponsors.edit.1");f.scrollIntoView({block:'nearest'});},
    reset(){const f=document.getElementById('sponsor-form');f.reset();f.elements.id.value='';f.elements.revision.value='';f.elements.key.required=true;f.querySelector('h3').textContent=LabI18n.t("sponsors.reset.1");},
    async save(e){e.preventDefault();const f=e.target,b=f.querySelector('[type=submit]');b.disabled=true;
        try{const d=await App.api('save_sponsor',{id:f.elements.id.value,revision:Number(f.elements.revision.value),label:f.elements.label.value,key:f.elements.key.value,daily_limit:Number(f.elements.daily_limit.value),price_cap:Number(f.elements.price_cap.value),budget_daily_usd:f.elements.budget_daily_usd.value===''?null:Number(f.elements.budget_daily_usd.value),budget_total_usd:f.elements.budget_total_usd.value===''?null:Number(f.elements.budget_total_usd.value),active:f.elements.active.checked});
            f.elements.key.value='';App.sponsors=d.sponsors;this.reset();App.renderAccount();App.renderSelectedModelSummary();App.renderInterrogatorSummary();this.render();document.getElementById('sponsor-save-status').textContent=LabI18n.t("sponsors.save.1");
        }catch(err){document.getElementById('sponsor-save-status').textContent=err.message;}finally{b.disabled=false;}
    }
};
document.addEventListener('DOMContentLoaded',()=>{document.getElementById('sponsor-form').addEventListener('submit',e=>SponsorUI.save(e));});
