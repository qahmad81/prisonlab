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
        const params=new URLSearchParams(location.search);if(params.get('view')==='leaderboard'){
            if(models.includes(params.get('model')))document.getElementById('board-model').value=params.get('model');
            if(['prisoner','interrogator'].includes(params.get('role')))document.getElementById('board-role').value=params.get('role');
            for(const [id,param] of [['case','case_file'],['level','level'],['opponent-level','opponent_level'],['rubric','rubric']]){
                const select=document.getElementById('board-'+id),value=params.get(param);if([...select.options].some(o=>o.value===value))select.value=value;
            }
            this.show(true);this.load();
        }
        this.context();this.shareControl();
    },
    show(board){const u=new URL(location.href);if(board)u.searchParams.set('view','leaderboard');else u.searchParams.delete('view');history.replaceState(null,'',u);this.shareControl();document.getElementById('community-leaderboard').hidden=!board;document.getElementById('public-grid').hidden=board;document.getElementById('public-search').parentElement.hidden=board;},
    profile(data){const box=document.getElementById('public-profile-heading');box.hidden=false;box.className='profile-heading';document.getElementById('all-profiles-link').hidden=false;
        if(!data.ok){box.textContent='لا توجد تجارب منشورة لهذا الملف.';return;}
        if(/^https:\/\//.test(data.profile.avatar_url || '')){const img=document.createElement('img');img.src=data.profile.avatar_url;img.alt='';img.referrerPolicy='no-referrer';img.addEventListener('error',()=>img.remove());box.appendChild(img);}
        const name=document.createElement('h2');name.className='profile-name';name.dir='auto';name.textContent=data.profile.display_name;box.appendChild(name);
        const p=document.createElement('p');p.textContent=`${data.logs.length} تجارب منشورة. السجلات الخاصة غير معروضة.`;box.appendChild(p);
        box.appendChild(LabSharing.copy(LabSharing.profile(data.profile.public_id),'نسخ رابط الملف'));
        const tabs=document.createElement('div');tabs.className='profile-tabs';tabs.setAttribute('role','tablist');
        const choices=[['investigator','محقق','human'],['accused','متهم','reverse'],['simulations','محاكاة بين النماذج','ai']];
        const selected=new URL(location.href).searchParams.get('tab');this.profileTab=choices.some(([id])=>id===selected)?selected:'investigator';
        for(const [id,label,mode] of choices){const b=document.createElement('button');b.type='button';b.className='btn btn-secondary';b.setAttribute('role','tab');b.setAttribute('aria-selected',String(this.profileTab===id));b.textContent=label+' · '+data.logs.filter(s=>s.interrogator_type===mode).length;b.addEventListener('click',()=>{this.profileTab=id;for(const x of tabs.children)x.setAttribute('aria-selected',String(x===b));const u=new URL(location.href);u.searchParams.set('tab',id);u.searchParams.delete('view');history.replaceState(null,'',u);this.show(false);renderPublicGrid(document.getElementById('public-search').value);});tabs.appendChild(b);}box.appendChild(tabs);
    },
    filterSessions(items){
        const p=new URL(location.href).searchParams, mode={investigator:'human',accused:'reverse',simulations:'ai'}[this.profileTab];
        return items.filter(s=>(!p.get('profile') || !mode || s.interrogator_type===mode) && (!p.get('case_file') || s.case_file===p.get('case_file')) && (!p.get('investigator') || s.interrogator_character?.id===p.get('investigator')) && (!p.get('model') || (p.get('role')==='interrogator'?s.interrogator_model===p.get('model'):p.get('role')==='prisoner'?s.model===p.get('model'):[s.model,s.interrogator_model].includes(p.get('model')))));
    },
    updateUrl(){const u=new URL(location.href);u.searchParams.set('view','leaderboard');for(const [id,param] of [['model','model'],['role','role'],['case','case_file'],['level','level'],['opponent-level','opponent_level'],['rubric','rubric']]){const value=document.getElementById('board-'+id).value;if(value)u.searchParams.set(param,value);else u.searchParams.delete(param);}history.replaceState(null,'',u);this.shareControl();},
    shareControl(){const box=document.getElementById('public-share-control');if(box)box.replaceChildren(LabSharing.copy(location.href,'نسخ رابط هذه الصفحة والمرشحات'));},
    context(){
        const box=document.getElementById('public-context-heading'),p=new URL(location.href).searchParams,data=this.catalog || {};box.replaceChildren();
        const file=data.cases?.find(c=>c.filename===p.get('case_file')),persona=data.investigators?.find(c=>c.id===p.get('investigator')),model=data.models?.find(m=>m.id===p.get('model'));
        if(file || persona || model){box.hidden=false;const h=document.createElement('h2');h.textContent=file?.title || persona?.name || model?.name;box.appendChild(h);
            const text=document.createElement('p');text.textContent=file?'القضية وشخصية المتهم · المستوى '+file.difficulty.level+'/10':persona?persona.bio+' · المستوى '+persona.level+'/10':'نتائج النموذج المنشورة حسب الدور والمرشحات؛ لا تظهر المحادثات الخاصة.';box.appendChild(text);
            if(file){const details=document.createElement('details');details.innerHTML='<summary>ملف القضية والشخصية</summary>';const pre=document.createElement('pre');pre.className='public-case-content';pre.textContent=file.content;details.appendChild(pre);box.appendChild(details);const start=document.createElement('a');start.href='index.php?case_file='+encodeURIComponent(file.filename);start.textContent='اختبر هذه القضية في المختبر ↗';start.className='text-button';box.appendChild(start);}
            if(persona){const img=document.createElement('div');img.innerHTML=LabView.investigatorAvatar(persona);box.prepend(img);}
            box.appendChild(LabSharing.copy(location.href));
        }
        const dir=document.getElementById('public-directory');dir.replaceChildren();
        for(const [title,items,value,label,to] of [['استعرض القضايا والشخصيات',data.cases || [],'filename','title',v=>LabSharing.case(v)],['استعرض شخصيات المحققين',data.investigators || [],'id','name',v=>LabSharing.investigator(v)]]){const l=document.createElement('label');l.textContent=title;const select=document.createElement('select');const empty=document.createElement('option');empty.value='';empty.textContent='اختر';select.appendChild(empty);for(const item of items){const o=document.createElement('option');o.value=item[value];o.textContent=item[label];select.appendChild(o);}select.addEventListener('change',()=>{if(select.value)location.href=to(select.value);});l.appendChild(select);dir.appendChild(l);}
    },
    async load(){const serial=this.serial=(this.serial || 0)+1;const params=new URLSearchParams({action:'leaderboard'});
        for(const [id,param] of [['model','model'],['role','role'],['case','case_file'],['level','level'],['opponent-level','opponent_level'],['rubric','rubric']])params.set(param,document.getElementById('board-'+id).value);
        const scope=new URL(location.href).searchParams;params.set('profile_id',scope.get('profile') || '');params.set('investigator',scope.get('investigator') || '');
        const status=document.getElementById('board-status');status.textContent='جارٍ تحميل النتائج…';
        try{const data=await LabCommunity.fetchJson('api.php?'+params);if(this.serial!==serial)return;
            const body=document.getElementById('board-rows');body.replaceChildren();let previous=null,rank=0;
            data.rows.forEach((r,i)=>{if(r.score!==previous)rank=i+1;previous=r.score;const tr=document.createElement('tr');
                for(let n=0;n<5;n++)tr.appendChild(document.createElement('td'));
                tr.children[0].textContent=rank;tr.children[1].appendChild(LabSharing.link(LabView.actorName(r,r.role),LabSharing.model(r.model_id,r.role)));tr.children[1].append(' · '+r.score+'/100');
                tr.children[2].appendChild(LabCommunity.operator(r));
                const a=document.createElement('a');a.href='public.php?session_id='+encodeURIComponent(r.session_id);a.textContent=r.case_title;tr.children[3].appendChild(a);
                const p=document.createElement('p');p.textContent=`${r.role==='prisoner'?'المتهم':'المحقق'} · مستوى ${r.level ?? '—'}/10 · الطرف المقابل ${r.opponent_level==null?'محقق بشري':r.opponent_level+'/10'} · معيار ${r.rubric}`;tr.children[3].appendChild(p);
                tr.children[3].insertAdjacentHTML('beforeend',LabView.participants(r));
                tr.children[4].textContent=LabCommunity.cost(r);body.appendChild(tr);
            });status.textContent=data.total?`${data.total} نتائج منشورة · راعِ اختلاف مستويات التفكير المعروضة${data.total>data.limit?'؛ تُعرض أعلى '+data.limit+' نتيجة. استخدم المرشحات.':''}`:'لا توجد نتائج منشورة ضمن هذه المرشحات.';
        }catch(e){if(this.serial===serial)status.textContent='تعذر تحميل الترتيب: '+e.message;}
    }
};
