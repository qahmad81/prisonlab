const PublicCommunity={
    setup(sessions) {
        const addOptions=(id,values)=>{const select=document.getElementById(id);for(const [value,label] of values){const o=document.createElement('option');o.value=value;o.textContent=label;select.appendChild(o);}};
        const models=[...new Set((this.catalog?.models || []).map(m=>m.id).concat(sessions.flatMap(s=>[s.interrogator_type!=='reverse'?s.model:null,s.interrogator_type!=='human'?s.interrogator_model:null]).filter(Boolean)))].sort();
        addOptions('board-model',models.map(x=>[x,x]));
        addOptions('board-case',(this.catalog?.cases || []).map(c=>[c.filename,c.title]));
        addOptions('board-rubric',[...new Set(sessions.map(s=>s.evaluation?.rubric_version || 'legacy'))].map(x=>[x,x]));
        document.getElementById('show-leaderboard').addEventListener('click',()=>{this.show(true);this.load();});
        document.getElementById('show-gallery').addEventListener('click',()=>this.show(false));
        for(const id of ['model','role','case','level','opponent-level','rubric'])document.getElementById('board-'+id).addEventListener('change',()=>{this.updateUrl();this.load();});
        const params=LabI18n.publicParams();if(params.get('view')==='leaderboard'){
            if(models.includes(params.get('model')))document.getElementById('board-model').value=params.get('model');
            if(['prisoner','interrogator'].includes(params.get('role')))document.getElementById('board-role').value=params.get('role');
            for(const [id,param] of [['case','case_file'],['level','level'],['opponent-level','opponent_level'],['rubric','rubric']]){
                const select=document.getElementById('board-'+id),value=params.get(param);if([...select.options].some(o=>o.value===value))select.value=value;
            }
            this.show(true);this.load();
        }
        this.context();this.shareControl();
    },
    show(board){const params=LabI18n.publicParams();if(board)params.set('view','leaderboard');else params.delete('view');LabI18n.replacePublic(params);this.shareControl();document.getElementById('community-leaderboard').hidden=!board;document.getElementById('public-grid').hidden=board;document.getElementById('public-search').parentElement.hidden=board;},
    profile(data){const box=document.getElementById('public-profile-heading');box.hidden=false;box.className='profile-heading';document.getElementById('all-profiles-link').hidden=false;
        if(!data.ok){box.textContent=LabI18n.t("public-community.profile.1");return;}
        if(/^https:\/\//.test(data.profile.avatar_url || '')){const img=document.createElement('img');img.src=data.profile.avatar_url;img.alt='';img.referrerPolicy='no-referrer';img.addEventListener('error',()=>img.remove());box.appendChild(img);}
        const name=document.createElement('h2');name.className='profile-name';name.dir='auto';name.textContent=data.profile.display_name;box.appendChild(name);
        const p=document.createElement('p');p.textContent=LabI18n.t("public-community.profile.2", {"length": data.logs.length});box.appendChild(p);
        const tabs=document.createElement('div');tabs.className='profile-tabs';tabs.setAttribute('role','tablist');
        const choices=[['all',LabI18n.t("public-community.profile.3"),null],['investigator',LabI18n.t("public-community.profile.4"),'human'],['accused',LabI18n.t("public-community.profile.5"),'reverse'],['simulations',LabI18n.t("public-community.profile.6"),'ai']];
        const params=LabI18n.publicParams(),selected=params.get('tab');this.profileTab=choices.some(([id])=>id===selected)?selected:'all';
        if(this.profileTab!=='all' && !data.logs.some(s=>s.interrogator_type===choices.find(([id])=>id===this.profileTab)[2])){this.profileTab='all';params.set('tab','all');LabI18n.replacePublic(params);}
        for(const [id,label,mode] of choices){const b=document.createElement('button');b.type='button';b.className='btn btn-secondary';b.setAttribute('role','tab');b.setAttribute('aria-selected',String(this.profileTab===id));b.textContent=label+' · '+data.logs.filter(s=>!mode || s.interrogator_type===mode).length;b.addEventListener('click',()=>{this.profileTab=id;for(const x of tabs.children)x.setAttribute('aria-selected',String(x===b));const params=LabI18n.publicParams();params.set('tab',id);LabI18n.replacePublic(params);this.show(false);renderPublicGrid(document.getElementById('public-search').value);});tabs.appendChild(b);}box.appendChild(tabs);this.shareControl();
    },
    filterSessions(items){
        const p=LabI18n.publicParams(), mode={investigator:'human',accused:'reverse',simulations:'ai'}[this.profileTab];
        return items.filter(s=>(!p.get('sponsor') || s.sponsor_id===p.get('sponsor')) && (!p.get('funding') || s.cost?.funding_source===p.get('funding')) && (!p.get('funder') || (s.cost?.funding_source==='personal'?s.operator_profile?.public_id:s.sponsor_profile?.public_id)===p.get('funder')) && (!p.get('profile') || !mode || s.interrogator_type===mode) && (!p.get('case_file') || s.case_file===p.get('case_file')) && (!p.get('investigator') || s.interrogator_character?.id===p.get('investigator')) && (!p.get('model') || (p.get('role')==='interrogator'?s.interrogator_model===p.get('model'):p.get('role')==='prisoner'?s.model===p.get('model'):[s.model,s.interrogator_model].includes(p.get('model')))));
    },
    updateUrl(){const params=LabI18n.publicParams();params.set('view','leaderboard');for(const [id,param] of [['model','model'],['role','role'],['case','case_file'],['level','level'],['opponent-level','opponent_level'],['rubric','rubric']]){const value=document.getElementById('board-'+id).value;if(value)params.set(param,value);else params.delete(param);}LabI18n.replacePublic(params);this.shareControl();},
    shareControl(){const box=document.getElementById('public-share-control');if(box)box.replaceChildren(LabSharing.copy(location.href,LabI18n.t("public-community.shareControl.1")),LabSharing.linkedin(location.href));},
    context(){
        const box=document.getElementById('public-context-heading'),p=LabI18n.publicParams(),data=this.catalog || {};box.replaceChildren();
        const file=data.cases?.find(c=>c.filename===p.get('case_file')),persona=data.investigators?.find(c=>c.id===p.get('investigator')),model=data.models?.find(m=>m.id===p.get('model'));
        if(p.get('sponsor') || p.get('funder') || p.get('funding')){box.hidden=false;const h=document.createElement('h2');const funded=publishedSessions.find(s=>s.sponsor_id===p.get('sponsor') || (s.cost?.funding_source==='personal'?s.operator_profile?.public_id:s.sponsor_profile?.public_id)===p.get('funder'));h.textContent=LabI18n.t("public-community.context.1")+(funded?LabCards.funding(funded).name:LabI18n.t("public-community.context.2"));box.appendChild(h);}
        if(file || persona || model){box.hidden=false;const h=document.createElement('h2');h.textContent=file?.title || persona?.name || model?.name;box.appendChild(h);
            const text=document.createElement('p');text.textContent=file?LabI18n.t("public-community.context.3")+file.difficulty.level+'/10':persona?persona.bio+LabI18n.t("public-community.context.4")+persona.level+'/10':LabI18n.t("public-community.context.5");box.appendChild(text);
            if(file){const details=document.createElement('details');details.innerHTML=`<summary>${LabI18n.html("public-community.context.6")}</summary>`;const pre=document.createElement('pre');pre.className='public-case-content';pre.textContent=file.content;details.appendChild(pre);box.appendChild(details);const start=document.createElement('a');start.href=LabI18n.pageUrl('home',{case_file:file.filename});start.textContent=LabI18n.t("public-community.context.7");start.className='text-button';box.appendChild(start);}
            if(persona){const img=document.createElement('div');img.innerHTML=LabView.investigatorAvatar(persona);box.prepend(img);}
        }
        const dir=document.getElementById('public-directory');dir.replaceChildren();
        for(const [title,items,value,label,to] of [[LabI18n.t("public-community.context.8"),data.cases || [],'filename','title',v=>LabSharing.case(v)],[LabI18n.t("public-community.context.9"),data.investigators || [],'id','name',v=>LabSharing.investigator(v)]]){const l=document.createElement('label');l.textContent=title;const select=document.createElement('select');const empty=document.createElement('option');empty.value='';empty.textContent=LabI18n.t("public-community.context.10");select.appendChild(empty);for(const item of items){const o=document.createElement('option');o.value=item[value];o.textContent=item[label];select.appendChild(o);}select.addEventListener('change',()=>{if(select.value)location.href=to(select.value);});l.appendChild(select);dir.appendChild(l);}
    },
    async load(){const serial=this.serial=(this.serial || 0)+1;const params=new URLSearchParams({action:'leaderboard'});
        for(const [id,param] of [['model','model'],['role','role'],['case','case_file'],['level','level'],['opponent-level','opponent_level'],['rubric','rubric']])params.set(param,document.getElementById('board-'+id).value);
        const scope=LabI18n.publicParams();params.set('profile_id',scope.get('profile') || '');params.set('investigator',scope.get('investigator') || '');
        for(const k of ['sponsor','funder','funding'])if(scope.get(k))params.set(k,scope.get(k));
        const status=document.getElementById('board-status');status.textContent=LabI18n.t("public-community.load.1");
        try{const data=await LabCommunity.fetchJson('api.php?'+params);if(this.serial!==serial)return;
            const body=document.getElementById('board-rows');body.replaceChildren();let previous=null,rank=0;
            const context=Object.fromEntries(LabI18n.publicParams());
            data.rows.forEach((r,i)=>{if(r.score!==previous)rank=i+1;previous=r.score;body.appendChild(LabCards.card(r,{open:openSessionReader,scope:context,rank}));
            });status.textContent=data.total?LabI18n.t("public-community.load.4", {"total": data.total, "value2": data.total>data.limit?LabI18n.t("public-community.load.2")+data.limit+LabI18n.t("public-community.load.3"):''}):LabI18n.t("public-community.load.5");
        }catch(e){if(this.serial===serial)status.textContent=LabI18n.t("public-community.load.6")+e.message;}
    }
};
