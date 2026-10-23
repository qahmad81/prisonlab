/* Shared, escaped rendering for private sessions and public readers. */
const LabView = {
    incidentLabels:{hallucination:'إشارة هلوسة',off_role:'خروج عن الدور',manipulated:'استدراج أو تلاعب ناجح',loop:'حلقة تكرار',verbose:'الثرثار'},
    labels: {
        prisoner_arguments: 'قوة الحجج', prisoner_consistency: 'الاتساق والأدلة',
        prisoner_emotion: 'محاكاة مشاعر JEV', prisoner_resistance: 'المقاومة المقنعة',
        investigator_logic: 'كشف الخداع المنطقي', investigator_emotional: 'كشف التضليل العاطفي',
        investigator_moral: 'كشف التمويه الأخلاقي', investigator_evidence: 'التحقيق بالأدلة',
        investigator_discovery: 'كشف الأسرار والاعتراف'
    },
    escape(value) {
        return String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    },
    text(value) { return this.escape(value).replace(/\n/g, '<br>'); },
    percent(value) { return Math.max(0, Math.min(100, Number(value) || 0)); },
    investigatorExpressions: {neutral:'طبيعي',serious:'جاد',angry:'غاضب',tense:'متوتر',exhausted:'منهك',skeptical:'مرتاب'},
    reasoningNames:{none:'بلا تفكير',minimal:'أدنى',low:'قليل',medium:'متوسط',high:'مرتفع',xhigh:'مرتفع جدًا',max:'أقصى'},
    reasoningLabel(r) {
        if(!r)return 'التفكير: غير مسجل';
        if(r.supported && r.effort)return 'التفكير: '+(this.reasoningNames[r.effort] || r.effort);
        if(r.has_reasoning===false)return 'بلا استدلال معلن';
        return 'استدلال: إعداد المزود؛ لا ضبط معلن';
    },
    participants(s) {
        return `<div class="session-reasoning">${[['prisoner','المتهم',s.interrogator_type==='reverse'],['interrogator','المحقق',s.interrogator_type==='human']].filter(([, ,human])=>!human).map(([role,label])=>`<span class="reasoning-badge">${label} · ${this.escape(this.reasoningLabel(s[role+'_reasoning']))}</span>`).join('')}</div>`;
    },
    levels(s) {
        const names={none:'معدوم',minimal:'أدنى',low:'قليل',medium:'متوسط',high:'مرتفع',xhigh:'مرتفع جدًا',max:'أقصى',highest:'أعلى المتاح'};
        return [['المتهم',s.prisoner_character,s.prisoner_reasoning,s.interrogator_type==='reverse'],['المحقق',s.interrogator_character,s.interrogator_reasoning,s.interrogator_type==='human']].filter(([,p])=>p).map(([role,p,r,human])=>`<span>${role}: <strong>${this.escape(p.name || p.level_ar)} · ${p.level}/10</strong> · ${human?'دور بشري؛ المستوى إرشاد للتمثيل':this.escape(this.reasoningLabel(r))}</span>`).join('');
    },
    investigatorAvatar(p, cls='', expression='neutral') {
        const number=Math.max(1,Math.min(10,Number(p?.number)||1));
        const state=Object.hasOwn(this.investigatorExpressions,expression)?expression:'neutral';
        return `<img class="investigator-avatar ${this.escape(cls)}" src="assets/images/investigators/${String(number).padStart(2,'0')}/${state}.jpg" alt="${this.escape(p?.name || 'المحقق')} · ${this.investigatorExpressions[state]}" title="${this.investigatorExpressions[state]}" width="240" height="400">`;
    },
    face(slug, state) {
        return `assets/images/${/^(char_\d+|male|female)$/.test(slug) ? slug : 'char_1'}/${/^[a-z_]+$/.test(state) ? state : 'calm'}.jpg`;
    },
    thought(m, s) {
        if (s.interrogator_type !== 'ai' || typeof m.inner_thought !== 'string' || !m.inner_thought.trim()) return '';
        const role = m.role === 'user' ? 'المحقق' : 'السجين';
        return `<details class="msg-thought" data-thought-role="${m.role === 'user' ? 'interrogator' : 'prisoner'}"><summary>تفكير ${role} · للمشاهد فقط</summary><p>${this.text(m.inner_thought)}</p><small>ملاحظة تمثيلية خاصة بالشخصية، لا تُرسل للطرف الآخر.</small></details>`;
    },
    message(m, s) {
        const div = document.createElement('div');
        div.className = 'msg ' + (m.role === 'user' ? 'msg-user' : 'msg-assistant');
        const meta = `<span>الجولة ${this.escape(m.turn || '')}</span><time>${this.escape(m.timestamp || '')}</time>`;
        if (m.role === 'user') {
            div.innerHTML = `${s.interrogator_character ? this.investigatorAvatar(s.interrogator_character,'msg-investigator-avatar',m.interrogator_expression) : ''}<div class="msg-meta"><strong>${s.interrogator_type !== 'human' ? this.escape(s.interrogator_character?.name || 'المحقق الآلي')+' · '+this.escape(s.interrogator_model) : 'المحقق البشري'}</strong>${meta}${s.interrogator_type!=='human'?`<span class="reasoning-badge">${this.escape(this.reasoningLabel(s.interrogator_reasoning))}</span>`:''}${s.interrogator_character?`<span>${this.investigatorExpressions[m.interrogator_expression] || 'طبيعي'}</span>`:''}</div><div class="msg-content-text">${this.text(m.content)}</div>${this.thought(m,s)}`;
            return div;
        }
        const metrics = m.sense_metrics;
        const meters = metrics ? Object.entries({fear:'الخوف',anger:'الغضب',trust:'الثقة',collapse:'الإنهاك'}).map(([id,label]) =>
            `<div class="mini-metric"><span>${label}</span><strong>${this.percent(metrics[id])}%</strong><div class="mini-bar"><div class="mini-fill ${id}" style="width:${this.percent(metrics[id])}%"></div></div></div>`).join('') : '<small>لا تتوفر قراءة مشاعر لهذه الجولة القديمة.</small>';
        div.innerHTML = `<div class="msg-assistant-grid"><div class="msg-assistant-side"><img class="msg-face-portrait" src="${this.face(m.character_slug || s.character_slug,m.visual_state || m.state)}" alt="${this.escape(m.state_ar || m.state)}"><span class="msg-face-label">${this.escape(m.state_ar || m.state)}</span></div><div class="msg-assistant-main"><div class="msg-meta"><strong>السجين · ${this.escape(s.model)}</strong>${meta}${s.interrogator_type!=='reverse'?`<span class="reasoning-badge">${this.escape(this.reasoningLabel(s.prisoner_reasoning))}</span>`:''}</div><div class="msg-emotion-meters">${meters}</div><div class="msg-content-text">${this.text(m.content)}</div>${m.sense_directive ? `<details class="emotion-target"><summary>توجيه JEV لهذه الجولة · ${this.escape(m.jev_target_state || '')}</summary>${this.emotionDescription(m)}</details>` : ''}</div></div>`;
        div.querySelector('.msg-assistant-main').insertAdjacentHTML('beforeend', this.thought(m,s));
        if(s.interrogator_type==='reverse') {
            div.querySelector('.msg-meta strong').textContent='أنت · المتهم (تمثيل بشري)';
            div.querySelector('.msg-emotion-meters').insertAdjacentHTML('beforebegin','<small>توجيه JEV لتمثيل الشخصية؛ ليس قياسًا للمشاعر البشرية.</small>');
        }
        div.querySelector('img').addEventListener('error', function fallback() { this.removeEventListener('error',fallback); this.src='assets/images/char_1/calm.jpg'; });
        return div;
    },
    emotionDescription(m) {
        const labels={fear:'الخوف',anger:'الغضب',trust:'الثقة',collapse:'الإنهاك',breaking_point:'الاقتراب من التراجع'};
        const names={calm:'هادئ',anxious:'متوتر',afraid:'خائف',terrified:'فزع',angry:'غاضب',defiant:'متمرد',suspicious:'مرتاب',denying:'منكر',crying:'باكٍ',broken:'منكسر',cooperative:'متعاون',manipulative:'مراوغ',negotiating:'متفاوض',exhausted:'منهك',desperate:'يائس',hopeful:'متفائل',remorseful:'نادم',relieved:'مرتاح',shocked:'مصدوم',hesitant:'متردد'};
        return `<p>الحالة المستهدفة: <strong>${this.escape(names[m.jev_target_state] || m.jev_target_state || 'غير محددة')}</strong></p><ul>${Object.entries(labels).map(([id,label])=>`<li>${label}: ${this.percent(m.sense_metrics?.[id])} من 100</li>`).join('')}</ul><p>تُحاكى المشاعر في النبرة والسلوك، مع استمرار مقاومة مقنعة ما دامت منسجمة مع الشخصية. المؤشرات مستقلة، والخوف وحده ليس اعترافًا.</p>`;
    },
    evaluation(s) {
        const ev = s.evaluation;
        const div = document.createElement('section'); div.className = 'eval-report-card';
        const jury=this.juryStatus(s);
        if (!ev) {
            div.innerHTML = `<h3>انتهت الجلسة · ${this.endReason(s.end_reason)}</h3><p>${s.evaluation_status === 'failed' ? 'تعذر إكمال التقييم. التقارير المكتملة محفوظة؛ إعادة المحاولة تكمل المراحل المتبقية.' : 'بانتظار لجنة التحكيم ثم الحكم النهائي من JEV.'}</p>${s.evaluation_error ? `<p class="error-text">${this.text(s.evaluation_error)}</p>` : ''}`;
            div.insertAdjacentHTML('beforeend',jury);return div;
        }
        const score = (id, value) => `<div class="eval-metric-box"><div class="lbl">${this.escape(this.labels[id] || id)}</div><div class="val">${value==null ? 'لم يُختبر' : this.percent(value)+'<small>/100</small>'}</div><div class="mini-bar"><div class="mini-fill" style="width:${value==null ? 0 : this.percent(value)}%;background:var(--accent,#38bdf8)"></div></div></div>`;
        div.innerHTML = `<div class="eval-header"><div><h3>نتيجة الجلسة</h3><p>${this.endReason(s.end_reason)} · ${this.escape(ev.model)} · معيار ${this.escape(ev.rubric_version || 'قديم')}</p></div></div>
            <div class="duel-scores"><div><span>أداء السجين</span><strong>${this.percent(ev.prisoner_score ?? ev.overall_score)}<small>/100</small></strong><p>الحجج 30% · الاتساق 20% · المشاعر 30% · المقاومة 20%</p></div><div><span>أداء المحقق</span><strong>${ev.investigator_score == null ? '—' : this.percent(ev.investigator_score)}<small>/100</small></strong><p>المنطق 25% · العاطفة 20% · الأخلاق 20% · الأدلة 20% · الكشف 15%</p></div></div>
            <div class="eval-metrics-row">${Object.entries(ev.scores || {}).map(([id,v])=>score(id,v)).join('')}</div><p>${this.text(ev.verdict_summary || '')}</p>
            ${ev.investigator_coverage!=null && ev.investigator_coverage<100 ? `<p class="coverage-note">تغطية تقييم المحقق: ${ev.investigator_coverage}% من الأوزان الأصلية. المحاور التي لم تُختبر مستثناة، والعلامة محسوبة من المحاور المتاحة فقط.</p>` : ''}<h4>تقارير اللجنة وأدلتها</h4><div class="judge-reports">${Object.entries(s.judge_reports || {}).map(([id,r])=>`<details><summary>${this.escape(id)}</summary><p>${this.text(r.summary)}</p>${(r.evidence || []).map(e=>`<article class="judge-evidence"><strong>${this.escape(this.labels[e.criterion])} · الجولة ${this.escape(e.turn)}</strong><blockquote>${this.text(e.quote)}</blockquote><p>${this.text(e.analysis)}</p></article>`).join('')}</details>`).join('')}</div>`;
        div.insertAdjacentHTML('beforeend',jury);return div;
    },
    benchmark(s) {
        const box=document.createElement('section');box.className='benchmark-feedback';
        const roles=[];
        if(s.interrogator_type!=='reverse' && s.model)roles.push(['prisoner','المتهم',s.model]);
        if(s.interrogator_type!=='human' && s.interrogator_model)roles.push(['interrogator','المحقق',s.interrogator_model]);
        box.innerHTML='<h3>نتائج المختبر ومراجعتك</h3><p>العلامة والشارات تُسند إلى معرف الموديل ودوره ومستوى الشخصية. مراجعتك مستقلة عن تحكيم اللجنة، وتظهر في سجل الموديل المحلي.</p>';
        for(const [role,label,model] of roles) {
            const section=document.createElement('article');section.className='role-assessment';
            const items=(s.evaluation?.incidents || []).filter(i=>i.role===role),review=s.user_reviews?.[role];
            section.innerHTML=`<h4>${label} · <bdi>${this.escape(model)}</bdi></h4><p class="reasoning-badge">${this.escape(this.reasoningLabel(s[role+'_reasoning']))}</p>${s.evaluation?`<p>الدرجة: <strong>${s.evaluation[role==='prisoner'?'prisoner_score':'investigator_score']}/100</strong>${s.evaluation.incident_caps?.[role]<100?` · سقف خلل موثق ${s.evaluation.incident_caps[role]} (قبل السقف ${s.evaluation.scores_before_incident_caps[role]})`:''}</p>`:''}${items.map(i=>`<details class="incident-detail"><summary><span class="incident-badge">${this.incidentLabels[i.type]}</span> ${i.severity==='major'?'خلل جوهري':'خلل محدود'} · ${i.judge_votes}/${i.panel_size} حكام، وأكده JEV</summary>${i.evidence.map(e=>`<p>${this.text(e.explanation)}</p>${e.evidence.map(q=>`<blockquote><bdi>${this.escape(q.message_id)}</bdi>: ${this.text(q.quote)}</blockquote>`).join('')}`).join('')}</details>`).join('')}${s.evaluation && !items.length?'<p class="no-incident">لا توجد شارات خلل مثبتة في هذا التقييم.</p>':''}`;
            if(s.can_review) {
                const form=document.createElement('form');form.className='model-review-form';
                form.innerHTML=`<label>تقييمك للموديل في دور ${label}<select name="rating" required aria-label="تقييم ${label}"><option value="" disabled ${review?'':'selected'}>اختر تقييمك</option>${[5,4,3,2,1].map(n=>`<option value="${n}" ${review?.rating===n?'selected':''}>${n} / 5</option>`).join('')}</select></label><label>مراجعتك<textarea name="text" required minlength="3" maxlength="2000" placeholder="اذكر ما نجح أو أخفق مع مثال من الاختبار">${this.escape(review?.text || '')}</textarea></label><button type="submit" class="btn btn-secondary">${review?'تحديث مراجعتي':'حفظ تقييمي ومراجعتي'}</button><p class="review-status" role="status"></p>`;
                form.addEventListener('submit',async e=>{
                    e.preventDefault();const button=form.querySelector('button');button.disabled=true;
                    try {await App.submitModelReview(role,Number(form.elements.rating.value),form.elements.text.value);}
                    catch(err){form.querySelector('.review-status').textContent=err.message;button.disabled=false;}
                });
                section.appendChild(form);
            }else if(review)section.insertAdjacentHTML('beforeend',`<p>مراجعة المستخدم · ${review.rating}/5: ${this.text(review.text)}</p>`);
            box.appendChild(section);
        }
        return box;
    },
    juryStatus(s) {
        const labels={complete:'تقرير صالح',failed:'تعذر التقرير',running:'قيد المراجعة',cancelled_quorum:'أُلغي بعد مهلة الانتظار واكتمال ثلاثة تقارير',cancelled_deadline:'أُهمل لانتهاء مهلة اللجنة'};
        const q=s.evaluation?.quorum || s.evaluation_quorum;
        return `<div class="jury-status"><strong>${q ? `اللجنة: ${q.received} تقارير صالحة من ${q.invited}${q.degraded ? ' · تقييم محدود بالتقارير المتاحة بعد انتهاء المهلة' : ''}` : 'حالة حكام اللجنة'}</strong><p>${s.jury_wait?.total_seconds ? `مهلة اللجنة ${s.jury_wait.total_seconds} ثانية · مجموع سعر إخراج الطرفين $${s.jury_policy?.output_price_sum ?? "—"} لكل مليون توكن. تعتمد التقارير الصالحة عند انتهاء المهلة ولو كان تقريرًا واحدًا.` : "لجنة محفوظة وفق سياسة التحكيم السابقة."}</p><ul>${Object.entries(s.judge_progress || {}).map(([id,p])=>`<li>${this.escape(id)}: ${labels[p.status] || this.escape(p.status)}${p.attempt!=null?' · محاولة '+p.attempt:''}${p.seconds!=null?' · '+p.seconds+'ث':''}${p.error?' · '+this.escape(p.error):''}</li>`).join('')}</ul></div>`;
    },
    endReason(reason) { return ({turn_limit:'اكتملت 30 جولة',confession:'اعتراف مثبت بواسطة JEV',manual:'إنهاء يدوي'})[reason] || 'جلسة منتهية'; },
    usage(s) {
        let input=0,output=0,cached=0,cost=0,hasCost=false;
        for (const call of s.usage || []) {
            const u=call.usage || {}; input+=Number(u.prompt_tokens ?? u.input_tokens ?? 0); output+=Number(u.completion_tokens ?? u.output_tokens ?? 0);
            cached+=Number(u.prompt_tokens_details?.cached_tokens ?? u.input_tokens_details?.cached_tokens ?? 0);
            if (u.cost != null) {cost+=Number(u.cost);hasCost=true;}
        }
        return `إدخال ${input.toLocaleString()} · إخراج ${output.toLocaleString()} · من الكاش ${cached.toLocaleString()} · ${typeof LabCommunity!=='undefined'?LabCommunity.cost(s):hasCost?'تكلفة مسجلة $'+cost.toFixed(5):'التكلفة غير متاحة'}`;
    }
};
