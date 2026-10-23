const PublicCommunity={
    setup(sessions) {
        const addOptions=(id,values)=>{const select=document.getElementById(id);for(const [value,label] of values){const o=document.createElement('option');o.value=value;o.textContent=label;select.appendChild(o);}};
        const models=[...new Set(sessions.flatMap(s=>[s.interrogator_type!=='reverse'?s.model:null,s.interrogator_type!=='human'?s.interrogator_model:null]).filter(Boolean))].sort();
        addOptions('board-model',models.map(x=>[x,x]));
        addOptions('board-case',[...new Map(sessions.map(s=>[s.case_file,s.case_title])).entries()]);
        addOptions('board-rubric',[...new Set(sessions.map(s=>s.evaluation?.rubric_version || 'legacy'))].map(x=>[x,x]));
        document.getElementById('show-leaderboard').addEventListener('click',()=>{this.show(true);this.load();});
        document.getElementById('show-gallery').addEventListener('click',()=>this.show(false));
        for(const id of ['model','role','case','level','opponent-level','rubric'])document.getElementById('board-'+id).addEventListener('change',()=>this.load());
        const params=new URLSearchParams(location.search);if(params.get('view')==='leaderboard'){
            if(models.includes(params.get('model')))document.getElementById('board-model').value=params.get('model');
            if(['prisoner','interrogator'].includes(params.get('role')))document.getElementById('board-role').value=params.get('role');
            for(const [id,param] of [['case','case_file'],['level','level'],['opponent-level','opponent_level'],['rubric','rubric']]){
                const select=document.getElementById('board-'+id),value=params.get(param);if([...select.options].some(o=>o.value===value))select.value=value;
            }
            this.show(true);this.load();
        }
    },
    show(board){document.getElementById('community-leaderboard').hidden=!board;document.getElementById('public-grid').hidden=board;document.getElementById('public-search').parentElement.hidden=board;},
    profile(data){const box=document.getElementById('public-profile-heading');box.hidden=false;box.className='profile-heading';document.getElementById('all-profiles-link').hidden=false;
        if(!data.ok){box.textContent='لا توجد تجارب منشورة لهذا الملف.';return;}
        if(/^https:\/\//.test(data.profile.avatar_url || '')){const img=document.createElement('img');img.src=data.profile.avatar_url;img.alt='';img.referrerPolicy='no-referrer';img.addEventListener('error',()=>img.remove());box.appendChild(img);}
        const name=document.createElement('h2');name.className='profile-name';name.dir='auto';name.textContent=data.profile.display_name;box.appendChild(name);
        const p=document.createElement('p');p.textContent=`${data.logs.length} تجارب منشورة، بما فيها المحاكاة التي شغّلها صاحب الحساب. السجلات الخاصة غير معروضة.`;box.appendChild(p);
    },
    async load(){const serial=this.serial=(this.serial || 0)+1;const params=new URLSearchParams({action:'leaderboard'});
        for(const [id,param] of [['model','model'],['role','role'],['case','case_file'],['level','level'],['opponent-level','opponent_level'],['rubric','rubric']])params.set(param,document.getElementById('board-'+id).value);
        const status=document.getElementById('board-status');status.textContent='جارٍ تحميل النتائج…';
        try{const data=await LabCommunity.fetchJson('api.php?'+params);if(this.serial!==serial)return;
            const body=document.getElementById('board-rows');body.replaceChildren();let previous=null,rank=0;
            data.rows.forEach((r,i)=>{if(r.score!==previous)rank=i+1;previous=r.score;const tr=document.createElement('tr');
                for(let n=0;n<5;n++)tr.appendChild(document.createElement('td'));
                tr.children[0].textContent=rank;tr.children[1].textContent=`${r.model_id} · ${r.score}/100`;
                tr.children[2].appendChild(LabCommunity.operator(r));
                const a=document.createElement('a');a.href='public.php?session_id='+encodeURIComponent(r.session_id);a.textContent=r.case_title;tr.children[3].appendChild(a);
                const p=document.createElement('p');p.textContent=`${r.role==='prisoner'?'المتهم':'المحقق'} · مستوى ${r.level ?? '—'}/10 · الطرف المقابل ${r.opponent_level==null?'محقق بشري':r.opponent_level+'/10'} · معيار ${r.rubric}`;tr.children[3].appendChild(p);
                tr.children[3].insertAdjacentHTML('beforeend',LabView.participants(r));
                tr.children[4].textContent=LabCommunity.cost(r);body.appendChild(tr);
            });status.textContent=data.total?`${data.total} نتائج منشورة · راعِ اختلاف مستويات التفكير المعروضة${data.total>data.limit?'؛ تُعرض أعلى '+data.limit+' نتيجة. استخدم المرشحات.':''}`:'لا توجد نتائج منشورة ضمن هذه المرشحات.';
        }catch(e){if(this.serial===serial)status.textContent='تعذر تحميل الترتيب: '+e.message;}
    }
};
