/* Public links never confer access to an unpublished transcript. */
const LabSharing={
    url(params){const u=new URL('public.php',location.href);for(const [k,v] of Object.entries(params))if(v!=='' && v!=null)u.searchParams.set(k,v);return u.href;},
    model(id,role){return this.url({view:'leaderboard',model:id,role});},
    case(file){return this.url({case_file:file});},
    investigator(id){return this.url({investigator:id});},
    profile(id,tab='all'){return this.url({profile:id,tab});},
    link(text,url){const a=document.createElement('a');a.href=url;a.className='text-button public-context-link';a.textContent=text;a.target='_blank';a.rel='noopener';a.addEventListener('click',e=>e.stopPropagation());return a;},
    copy(url,label='نسخ رابط المشاركة'){
        const b=document.createElement('button');b.type='button';b.className='btn btn-secondary share-link-button icon-button';b.title=label;b.setAttribute('aria-label',label);
        b.innerHTML='<svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="8" y="8" width="12" height="13" rx="2"/><path d="M15 8V3H3v13h5"/></svg>';
        b.addEventListener('click',async e=>{e.stopPropagation();try{await navigator.clipboard.writeText(url);b.title='نُسخ الرابط';b.setAttribute('aria-label','نُسخ الرابط');b.classList.add('copied');}catch{const a=this.link('افتح رابط المشاركة وانسخه',url);b.replaceWith(a);}});return b;
    },
    linkedin(url){const a=document.createElement('a');a.className='btn linkedin-share page-linkedin-share';a.href='https://www.linkedin.com/sharing/share-offsite/?url='+encodeURIComponent(url);a.target='_blank';a.rel='noopener noreferrer';a.title='مشاركة هذه الصفحة على LinkedIn';a.setAttribute('aria-label',a.title);a.innerHTML='<b aria-hidden="true" dir="ltr">in</b><span>مشاركة على LinkedIn</span>';return a;},
    setupLinks(){if(typeof LabCards!=='undefined')LabCards.setupLinks();}
};
