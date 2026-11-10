/* Shared, escaped rendering for private sessions and public readers. */
const LabView = {
    incidentLabels:{hallucination:LabI18n.t("session-view.ui.1"),off_role:LabI18n.t("session-view.ui.2"),manipulated:LabI18n.t("session-view.ui.3"),loop:LabI18n.t("session-view.ui.4"),verbose:LabI18n.t("session-view.ui.5"),premature_exit:LabI18n.t("session-view.ui.6"),prolonged_interrogation:LabI18n.t("session-view.ui.7")},
    labels: {
        prisoner_arguments: LabI18n.t("session-view.ui.8"), prisoner_consistency: LabI18n.t("session-view.ui.9"),
        prisoner_emotion: LabI18n.t("session-view.ui.10"), prisoner_resistance: LabI18n.t("session-view.ui.11"),
        investigator_logic: LabI18n.t("session-view.ui.12"), investigator_emotional: LabI18n.t("session-view.ui.13"),
        investigator_moral: LabI18n.t("session-view.ui.14"), investigator_evidence: LabI18n.t("session-view.ui.15"),
        investigator_discovery: LabI18n.t("session-view.ui.16")
    },
    escape(value) {
        return String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    },
    text(value) { return this.escape(value).replace(/\n/g, '<br>'); },
    percent(value) { return Math.max(0, Math.min(100, Number(value) || 0)); },
    investigatorExpressions: {neutral:LabI18n.t("session-view.ui.17"),serious:LabI18n.t("session-view.ui.18"),angry:LabI18n.t("session-view.ui.19"),tense:LabI18n.t("session-view.ui.20"),exhausted:LabI18n.t("session-view.ui.21"),skeptical:LabI18n.t("session-view.ui.22")},
    reasoningNames:{none:LabI18n.t("session-view.ui.23"),minimal:LabI18n.t("session-view.ui.24"),low:LabI18n.t("session-view.ui.25"),medium:LabI18n.t("session-view.ui.26"),high:LabI18n.t("session-view.ui.27"),xhigh:LabI18n.t("session-view.ui.28"),max:LabI18n.t("session-view.ui.29")},
    reasoningLabel(r) {
        if(!r)return LabI18n.t("session-view.reasoningLabel.1");
        if(r.source==='mcp')return LabI18n.t("session-view.reasoningLabel.2")+(this.reasoningNames[r.effort] || LabI18n.t("session-view.reasoningLabel.3"))+LabI18n.t("session-view.reasoningLabel.4");
        if(r.supported && r.effort)return LabI18n.t("session-view.reasoningLabel.5")+(this.reasoningNames[r.effort] || r.effort);
        if(r.has_reasoning===false)return LabI18n.t("session-view.reasoningLabel.6");
        return LabI18n.t("session-view.reasoningLabel.7");
    },
    participants(s) {
        return `<div class="session-reasoning">${[['prisoner',LabI18n.t("session-view.participants.1"),s.interrogator_type==='reverse'],['interrogator',LabI18n.t("session-view.participants.2"),s.interrogator_type==='human']].filter(([, ,human])=>!human).map(([role,label])=>`<span class="reasoning-badge">${label} · ${this.escape(this.reasoningLabel(s[role+'_reasoning']))}</span>`).join('')}</div>`;
    },
    actorName(s,role){return s.mcp_actors?.[role]?s.mcp_actors[role].name+LabI18n.t("session-view.actorName.1"):s[role==='prisoner'?'model':'interrogator_model'];},
    levels(s) {
        const names={none:LabI18n.t("session-view.levels.1"),minimal:LabI18n.t("session-view.ui.24"),low:LabI18n.t("session-view.ui.25"),medium:LabI18n.t("session-view.ui.26"),high:LabI18n.t("session-view.ui.27"),xhigh:LabI18n.t("session-view.ui.28"),max:LabI18n.t("session-view.ui.29"),highest:LabI18n.t("session-view.levels.2")};
        return [[LabI18n.t("session-view.participants.1"),s.prisoner_character,s.prisoner_reasoning,s.interrogator_type==='reverse'],[LabI18n.t("session-view.participants.2"),s.interrogator_character,s.interrogator_reasoning,s.interrogator_type==='human']].filter(([,p])=>p).map(([role,p,r,human])=>`<span>${role}: <strong>${this.escape(p.name || p.level_ar)} · ${p.level}/10</strong> · ${human?LabI18n.t("session-view.levels.3"):this.escape(this.reasoningLabel(r))}</span>`).join('');
    },
    investigatorAvatar(p, cls='', expression='neutral') {
        const number=Math.max(1,Math.min(10,Number(p?.number)||1));
        const state=Object.hasOwn(this.investigatorExpressions,expression)?expression:'neutral';
        return `<img class="investigator-avatar ${this.escape(cls)}" src="/assets/images/investigators/${String(number).padStart(2,'0')}/${state}.jpg" alt="${this.escape(p?.name || LabI18n.t("session-view.participants.2"))} · ${this.investigatorExpressions[state]}" title="${this.investigatorExpressions[state]}" width="240" height="400">`;
    },
    face(slug, state) {
        return `/assets/images/${/^(char_\d+|male|female)$/.test(slug) ? slug : 'char_1'}/${/^[a-z_]+$/.test(state) ? state : 'calm'}.jpg`;
    },
    thought(m, s) {
        if (s.interrogator_type !== 'ai' || typeof m.inner_thought !== 'string' || !m.inner_thought.trim()) return '';
        const role = m.role === 'user' ? LabI18n.t("session-view.participants.2") : LabI18n.t("session-view.thought.1");
        return `<details class="msg-thought" data-thought-role="${m.role === 'user' ? 'interrogator' : 'prisoner'}"><summary>${LabI18n.html("session-view.thought.2")} ${role} ${LabI18n.html("session-view.thought.3")}</summary><p>${this.text(m.inner_thought)}</p><small>${LabI18n.html("session-view.thought.4")}</small></details>`;
    },
    message(m, s) {
        const div = document.createElement('div');
        div.className = 'msg ' + (m.role === 'user' ? 'msg-user' : 'msg-assistant');
        const meta = `<span>${LabI18n.html("session-view.message.1")} ${this.escape(m.turn || '')}</span><time>${this.escape(m.timestamp || '')}</time>`;
        if (m.role === 'user') {
            div.innerHTML = `${s.interrogator_character ? this.investigatorAvatar(s.interrogator_character,'msg-investigator-avatar',m.interrogator_expression) : ''}<div class="msg-meta"><strong>${s.interrogator_type !== 'human' ? this.escape(s.interrogator_character?.name || LabI18n.t("session-view.message.2"))+' · '+this.escape(this.actorName(s,'interrogator')) : LabI18n.t("session-view.message.3")}</strong>${meta}${s.interrogator_type!=='human'?`<span class="reasoning-badge">${this.escape(this.reasoningLabel(s.interrogator_reasoning))}</span>`:''}${s.interrogator_character?`<span>${this.investigatorExpressions[m.interrogator_expression] || LabI18n.t("session-view.ui.17")}</span>`:''}</div><div class="msg-content-text" dir="auto">${this.text(m.content)}</div>${this.thought(m,s)}`;
            return div;
        }
        const metrics = m.sense_metrics;
        const meters = metrics ? Object.entries({fear:LabI18n.t("session-view.message.4"),anger:LabI18n.t("session-view.message.5"),trust:LabI18n.t("session-view.message.6"),collapse:LabI18n.t("session-view.message.7")}).map(([id,label]) =>
            `<div class="mini-metric"><span>${label}</span><strong>${this.percent(metrics[id])}%</strong><div class="mini-bar"><div class="mini-fill ${id}" style="width:${this.percent(metrics[id])}%"></div></div></div>`).join('') : `<small>${LabI18n.html("session-view.message.8")}</small>`;
        div.innerHTML = `<div class="msg-assistant-grid"><div class="msg-assistant-side"><img class="msg-face-portrait" src="${this.face(m.character_slug || s.character_slug,m.visual_state || m.state)}" alt="${this.escape(m.state_ar || m.state)}"><span class="msg-face-label">${this.escape(m.state_ar || m.state)}</span></div><div class="msg-assistant-main"><div class="msg-meta"><strong>${LabI18n.html("session-view.message.10")} ${this.escape(this.actorName(s,'prisoner'))}</strong>${meta}${s.interrogator_type!=='reverse'?`<span class="reasoning-badge">${this.escape(this.reasoningLabel(s.prisoner_reasoning))}</span>`:''}</div><div class="msg-emotion-meters">${meters}</div><div class="msg-content-text" dir="auto">${this.text(m.content)}</div>${m.sense_directive ? `<details class="emotion-target"><summary>${LabI18n.html("session-view.message.9")} ${this.escape(m.jev_target_state || '')}</summary>${this.emotionDescription(m)}</details>` : ''}</div></div>`;
        div.querySelector('.msg-assistant-main').insertAdjacentHTML('beforeend', this.thought(m,s));
        if(s.interrogator_type==='reverse') {
            div.querySelector('.msg-meta strong').textContent=LabI18n.t("session-view.message.11");
            div.querySelector('.msg-emotion-meters').insertAdjacentHTML('beforebegin',`<small>${LabI18n.html("session-view.message.12")}</small>`);
        }
        div.querySelector('img').addEventListener('error', function fallback() { this.removeEventListener('error',fallback); this.src='/assets/images/char_1/calm.jpg'; });
        return div;
    },
    emotionDescription(m) {
        const labels={fear:LabI18n.t("session-view.message.4"),anger:LabI18n.t("session-view.message.5"),trust:LabI18n.t("session-view.message.6"),collapse:LabI18n.t("session-view.message.7"),breaking_point:LabI18n.t("session-view.emotionDescription.1")};
        const names={calm:LabI18n.t("session-view.emotionDescription.2"),anxious:LabI18n.t("session-view.ui.20"),afraid:LabI18n.t("session-view.emotionDescription.3"),terrified:LabI18n.t("session-view.emotionDescription.4"),angry:LabI18n.t("session-view.ui.19"),defiant:LabI18n.t("session-view.emotionDescription.5"),suspicious:LabI18n.t("session-view.ui.22"),denying:LabI18n.t("session-view.emotionDescription.6"),crying:LabI18n.t("session-view.emotionDescription.7"),broken:LabI18n.t("session-view.emotionDescription.8"),cooperative:LabI18n.t("session-view.emotionDescription.9"),manipulative:LabI18n.t("session-view.emotionDescription.10"),negotiating:LabI18n.t("session-view.emotionDescription.11"),exhausted:LabI18n.t("session-view.ui.21"),desperate:LabI18n.t("session-view.emotionDescription.12"),hopeful:LabI18n.t("session-view.emotionDescription.13"),remorseful:LabI18n.t("session-view.emotionDescription.14"),relieved:LabI18n.t("session-view.emotionDescription.15"),shocked:LabI18n.t("session-view.emotionDescription.16"),hesitant:LabI18n.t("session-view.emotionDescription.17")};
        return `<p>${LabI18n.html("session-view.emotionDescription.20")} <strong>${this.escape(names[m.jev_target_state] || m.jev_target_state || LabI18n.t("session-view.emotionDescription.18"))}</strong></p><ul>${Object.entries(labels).map(([id,label])=>`<li>${label}: ${this.percent(m.sense_metrics?.[id])} ${LabI18n.html("session-view.emotionDescription.19")}</li>`).join('')}</ul><p>${LabI18n.html("session-view.emotionDescription.21")}</p>`;
    },
    evaluation(s) {
        const ev = s.evaluation;
        const div = document.createElement('section'); div.className = 'eval-report-card';
        const jury=this.juryStatus(s)+this.closureReport(s);
        if (!ev) {
            div.innerHTML = `<h3>${LabI18n.html("session-view.evaluation.3")} ${this.endReason(s.end_reason)}</h3><p>${s.evaluation_status === 'failed' ? LabI18n.t("session-view.evaluation.1") : LabI18n.t("session-view.evaluation.2")}</p>${s.evaluation_error ? `<p class="error-text">${this.text(s.evaluation_error)}</p>` : ''}`;
            div.insertAdjacentHTML('beforeend',jury);return div;
        }
        const score = (id, value) => `<div class="eval-metric-box"><div class="lbl">${this.escape(this.labels[id] || id)}</div><div class="val">${value==null ? LabI18n.t("session-view.evaluation.4") : this.percent(value)+'<small>/100</small>'}</div><div class="mini-bar"><div class="mini-fill" style="width:${value==null ? 0 : this.percent(value)}%;background:var(--accent,#38bdf8)"></div></div></div>`;
        div.innerHTML = `<div class="eval-header"><div><h3>${LabI18n.html("session-view.evaluation.9")}</h3><p>${this.endReason(s.end_reason)} · ${this.escape(ev.model)} ${LabI18n.html("session-view.evaluation.10")} ${this.escape(ev.rubric_version || LabI18n.t("session-view.evaluation.5"))}</p></div></div>
            <div class="duel-scores"><div><span>${LabI18n.html("session-view.evaluation.11")}</span><strong>${this.percent(ev.prisoner_score ?? ev.overall_score)}<small>/100</small></strong><p>${LabI18n.html("session-view.evaluation.12")}</p></div><div><span>${LabI18n.html("session-view.evaluation.13")}</span><strong>${ev.investigator_score == null ? '—' : this.percent(ev.investigator_score)}<small>/100</small></strong><p>${LabI18n.html("session-view.evaluation.14")}</p></div></div>
            <div class="eval-metrics-row">${Object.entries(ev.scores || {}).map(([id,v])=>score(id,v)).join('')}</div><p>${this.text(ev.verdict_summary || '')}</p>
            ${ev.investigator_coverage!=null && ev.investigator_coverage<100 ? `<p class="coverage-note">${LabI18n.html("session-view.evaluation.6")} ${ev.investigator_coverage}${LabI18n.html("session-view.evaluation.7")}</p>` : ''}<h4>${LabI18n.html("session-view.evaluation.15")}</h4><div class="judge-reports">${Object.entries(s.judge_reports || {}).map(([id,r])=>`<details><summary>${this.escape(id)}</summary><p>${this.text(r.summary)}</p>${(r.evidence || []).map(e=>`<article class="judge-evidence"><strong>${this.escape(this.labels[e.criterion])} ${LabI18n.html("session-view.evaluation.8")} ${this.escape(e.turn)}</strong><blockquote>${this.text(e.quote)}</blockquote><p>${this.text(e.analysis)}</p></article>`).join('')}</details>`).join('')}</div>`;
        div.insertAdjacentHTML('beforeend',jury);return div;
    },
    benchmark(s) {
        const box=document.createElement('section');box.className='benchmark-feedback';
        const roles=[];
        if(s.interrogator_type!=='reverse' && s.model)roles.push(['prisoner',LabI18n.t("session-view.participants.1"),s.model]);
        if(s.interrogator_type!=='human' && s.interrogator_model)roles.push(['interrogator',LabI18n.t("session-view.participants.2"),s.interrogator_model]);
        box.innerHTML=`<h3>${LabI18n.html("session-view.benchmark.1")}</h3><p>${LabI18n.html("session-view.benchmark.2")}</p>`;
        for(const [role,label,model] of roles) {
            const section=document.createElement('article');section.className='role-assessment';
            const items=(s.evaluation?.incidents || []).filter(i=>i.role===role),review=s.user_reviews?.[role];
            section.innerHTML=`<h4>${label} · <bdi>${this.escape(model)}</bdi></h4><p class="reasoning-badge">${this.escape(this.reasoningLabel(s[role+'_reasoning']))}</p>${s.evaluation?`<p>${LabI18n.html("session-view.benchmark.4")} <strong>${s.evaluation[role==='prisoner'?'prisoner_score':'investigator_score']}/100</strong>${s.evaluation.incident_caps?.[role]<100?LabI18n.t("session-view.benchmark.3", {"value1": s.evaluation.incident_caps[role], "value2": s.evaluation.scores_before_incident_caps[role]}):''}</p>`:''}${items.map(i=>`<details class="incident-detail"><summary><span class="incident-badge">${this.incidentLabels[i.type]}</span> ${i.severity==='major'?LabI18n.t("session-view.benchmark.5"):LabI18n.t("session-view.benchmark.6")} · ${i.judge_votes}/${i.panel_size} ${LabI18n.html("session-view.benchmark.7")}</summary>${i.evidence.map(e=>`<p>${this.text(e.explanation)}</p>${e.evidence.map(q=>`<blockquote><bdi>${this.escape(q.message_id)}</bdi>: ${this.text(q.quote)}</blockquote>`).join('')}`).join('')}</details>`).join('')}${s.evaluation && !items.length?`<p class="no-incident">${LabI18n.html("session-view.benchmark.8")}</p>`:''}`;
            if(s.can_review) {
                const form=document.createElement('form');form.className='model-review-form';
                form.innerHTML=`<label>${LabI18n.html("session-view.benchmark.11")} ${label}<select name="rating" required aria-label="${LabI18n.html("session-view.benchmark.12")} ${label}"><option value="" disabled ${review?'':'selected'}>${LabI18n.html("session-view.benchmark.13")}</option>${[5,4,3,2,1].map(n=>`<option value="${n}" ${review?.rating===n?'selected':''}>${n} / 5</option>`).join('')}</select></label><label>${LabI18n.html("session-view.benchmark.14")}<textarea name="text" required minlength="3" maxlength="2000" placeholder="${LabI18n.html("session-view.benchmark.15")}">${this.escape(review?.text || '')}</textarea></label><button type="submit" class="btn btn-secondary">${review?LabI18n.t("session-view.benchmark.9"):LabI18n.t("session-view.benchmark.10")}</button><p class="review-status" role="status"></p>`;
                form.addEventListener('submit',async e=>{
                    e.preventDefault();const button=form.querySelector('button');button.disabled=true;
                    try {await App.submitModelReview(role,Number(form.elements.rating.value),form.elements.text.value);}
                    catch(err){form.querySelector('.review-status').textContent=err.message;button.disabled=false;}
                });
                section.appendChild(form);
            }else if(review)section.insertAdjacentHTML('beforeend',`<p>${LabI18n.html("session-view.benchmark.16")} ${review.rating}/5: ${this.text(review.text)}</p>`);
            box.appendChild(section);
        }
        return box;
    },
    closureReport(s) {
        if(!s.closure_policy)return '';
        const e=s.termination, kinds={confession:LabI18n.t("session-view.closureReport.1"),agreement:LabI18n.t("session-view.closureReport.2"),closure:LabI18n.t("session-view.closureReport.3"),withdrawal:LabI18n.t("session-view.closureReport.4"),counsel:LabI18n.t("session-view.closureReport.5"),silence:LabI18n.t("session-view.closureReport.6"),medical:LabI18n.t("session-view.closureReport.7")};
        const event=e?`<p>${e.role==='prisoner'?LabI18n.t("session-view.participants.1"):LabI18n.t("session-view.participants.2")} · ${this.escape(kinds[e.kind] || e.kind)} ${LabI18n.html("session-view.evaluation.8")} ${this.escape(e.turn)} · ${this.escape(e.message_id)}</p><blockquote>${this.text(e.quote)}</blockquote><small>${LabI18n.html("session-view.closureReport.8")}</small>`:`<p>${LabI18n.html("session-view.closureReport.9")}</p>`;
        const adjustments=Object.entries(s.evaluation?.closure_adjustments || {}).map(([role,a])=>`<article><strong>${role==='prisoner'?LabI18n.t("session-view.participants.1"):LabI18n.t("session-view.participants.2")} ${LabI18n.html("session-view.closureReport.11")} ${a.timing_score}/100</strong><p>${LabI18n.html("session-view.closureReport.12")} ${Math.round(a.weight*100)}${LabI18n.html("session-view.closureReport.13")} ${a.base_score} ← ${a.adjusted_score} ${LabI18n.html("session-view.closureReport.14")}</p>${Object.values(a.evidence || {}).map(r=>`<details><summary>${LabI18n.html("session-view.closureReport.10")}</summary><p>${this.text(r.explanation)}</p>${(r.evidence || []).map(e=>`<blockquote>${this.escape(e.message_id)}: ${this.text(e.quote)}</blockquote>`).join('')}</details>`).join('')}</article>`).join('');
        return `<section class="jury-status closure-report"><h4>${LabI18n.html("session-view.closureReport.16")}</h4>${event}${s.evaluation?.closure_protected?`<p>${LabI18n.html("session-view.closureReport.15")}</p>`:''}${adjustments}<p>${LabI18n.html("session-view.closureReport.17")}</p></section>`;
    },
    juryStatus(s) {
        const labels={complete:LabI18n.t("session-view.juryStatus.1"),failed:LabI18n.t("session-view.juryStatus.2"),running:LabI18n.t("session-view.juryStatus.3"),cancelled_quorum:LabI18n.t("session-view.juryStatus.4"),cancelled_deadline:LabI18n.t("session-view.juryStatus.5")};
        const q=s.evaluation?.quorum || s.evaluation_quorum;
        return `<div class="jury-status"><strong>${q ? LabI18n.t("session-view.juryStatus.7", {"received": q.received, "invited": q.invited, "value3": q.degraded ? LabI18n.t("session-view.juryStatus.6") : ''}) : LabI18n.t("session-view.juryStatus.8")}</strong><p>${s.jury_wait?.total_seconds ? LabI18n.t("session-view.juryStatus.9", {"total_seconds": s.jury_wait.total_seconds, "value2": s.jury_policy?.output_price_sum ?? "—"}) : LabI18n.t("session-view.juryStatus.10")}</p><ul>${Object.entries(s.judge_progress || {}).map(([id,p])=>`<li>${this.escape(id)}: ${labels[p.status] || this.escape(p.status)}${p.attempt!=null?LabI18n.t("session-view.juryStatus.11")+p.attempt:''}${p.seconds!=null?' · '+p.seconds+LabI18n.t("session-view.juryStatus.12"):''}${p.error?' · '+this.escape(p.error):''}</li>`).join('')}</ul></div>`;
    },
    endReason(reason) { return ({turn_limit:LabI18n.t("session-view.endReason.1"),confession:LabI18n.t("session-view.endReason.2"),manual:LabI18n.t("session-view.endReason.3"),actor_stop:LabI18n.t("session-view.endReason.4")})[reason] || LabI18n.t("session-view.endReason.5"); },
    usage(s) {
        let input=0,output=0,cached=0,cost=0,hasCost=false;
        for (const call of s.usage || []) {
            const u=call.usage || {}; input+=Number(u.prompt_tokens ?? u.input_tokens ?? 0); output+=Number(u.completion_tokens ?? u.output_tokens ?? 0);
            cached+=Number(u.prompt_tokens_details?.cached_tokens ?? u.input_tokens_details?.cached_tokens ?? 0);
            if (u.cost != null) {cost+=Number(u.cost);hasCost=true;}
        }
        return LabI18n.t("session-view.usage.3", {"value1": input.toLocaleString(), "value2": output.toLocaleString(), "value3": cached.toLocaleString(), "value4": typeof LabCommunity!=='undefined'?LabCommunity.cost(s):hasCost?LabI18n.t("session-view.usage.1")+cost.toFixed(5):LabI18n.t("session-view.usage.2")});
    }
};
