/* Public links never confer access to an unpublished transcript. */
const LabSharing={
    url(params){const u=new URL('public.php',location.href);for(const [k,v] of Object.entries(params))if(v!=='' && v!=null)u.searchParams.set(k,v);return u.href;},
    model(id,role){return this.url({view:'leaderboard',model:id,role});},
    case(file){return this.url({case_file:file});},
    investigator(id){return this.url({investigator:id});},
    profile(id,tab='investigator'){return this.url({profile:id,tab});},
    link(text,url){const a=document.createElement('a');a.href=url;a.className='text-button public-context-link';a.textContent=text;a.target='_blank';a.rel='noopener';a.addEventListener('click',e=>e.stopPropagation());return a;},
    copy(url,label='نسخ رابط المشاركة'){
        const b=document.createElement('button');b.type='button';b.className='btn btn-secondary share-link-button';b.textContent=label;
        b.addEventListener('click',async e=>{e.stopPropagation();try{await navigator.clipboard.writeText(url);b.textContent='✓ نُسخ الرابط';}catch{const a=this.link('افتح رابط المشاركة وانسخه',url);b.replaceWith(a);}});return b;
    },
    setupLinks(){
        for(const [id,url,label] of [['case-share-link',this.case(App.selectedCaseFilename),'رابط القضية والشخصية ↗'],['prisoner-share-link',this.model(App.selectedModelId,'prisoner'),'نتائج نموذج المتهم ↗'],['interrogator-share-link',this.model(App.selectedInterrogatorId,'interrogator'),'نتائج نموذج المحقق ↗'],['investigator-share-link',this.investigator(App.selectedInvestigatorPersona),'رابط شخصية المحقق ↗']]){const a=document.getElementById(id);if(a){a.href=url;a.textContent=label;}}
    }
};
