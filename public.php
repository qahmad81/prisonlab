<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>معرض استجوابات التحقيق المنشورة | مختبر الذكاء الاصطناعي</title>
    <link rel="icon" href="favicon.ico?v=2" sizes="any">
    <link rel="icon" type="image/svg+xml" href="favicon.svg?v=2">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/branding/prisonlab-32.png">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/branding/prisonlab-180.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css?v=<?= (int)filemtime(__DIR__ . "/assets/style.css") ?>">
    <style>
        .public-container {
            max-width: 1300px;
            margin: 0 auto;
            padding: 24px 20px;
            width: 100%;
        }
        .public-hero {
            background: linear-gradient(135deg, rgba(30, 41, 59, 0.7) 0%, rgba(15, 23, 42, 0.9) 100%);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 24px 28px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }
        .public-hero h2 {
            font-size: 1.4rem;
            color: #f8fafc;
            margin-bottom: 6px;
        }
        .public-hero p {
            color: var(--text-muted);
            font-size: 0.88rem;
            max-width: 650px;
            line-height: 1.5;
        }
        .public-stats {
            display: flex;
            gap: 14px;
        }
        .public-stat-box {
            background: #0b1120;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 10px 18px;
            text-align: center;
        }
        .public-stat-box .num {
            font-size: 1.3rem;
            font-weight: 800;
            color: #38bdf8;
        }
        .public-stat-box .lbl {
            font-size: 0.75rem;
            color: var(--text-muted);
        }
    </style>
</head>
<body>

<header class="top-bar">
    <div class="brand">
        <img class="brand-logo" src="assets/branding/prisonlab-128.png" alt="" width="48" height="48">
        <div class="brand-copy"><span class="brand-name" dir="ltr">PrisonLab</span><h1>التجارب المنشورة · نتائج يشاركها المجتمع</h1></div>
    </div>
    <div class="header-actions">
        <nav class="brand-links" aria-label="معلومات الموقع"><a href="privacy.php">الخصوصية</a><a href="https://odehit.com/#contact" target="_blank" rel="noopener noreferrer">تواصل معنا ↗</a></nav>
        <a href="index.php" class="btn btn-primary" style="text-decoration:none; display:inline-flex; align-items:center; gap:8px;">
            ⬅ العودة لمختبر الاستجواب الرئيسي
        </a>
    </div>
</header>

<main class="public-container">
    <section class="public-hero">
        <div>
            <h2>🌐 الأرشيف العلني لجلسات الاستجواب والاختبار تحت الضغط</h2>
            <p>تصفح جلسات التحقيق الحقيقية التي أجراها المستخدمون أو الوكلاء الأذكياء (AI vs AI) ضد نماذج الذكاء الاصطناعي، مع قياسات المشاعر الحية وتقييمات SENSE_MODEL (jev) النهائية.</p>
        </div>
        <div class="public-stats">
            <div class="public-stat-box">
                <div class="num" id="stat-published-count">0</div>
                <div class="lbl">جلسات منشورة</div>
            </div>
            <div class="public-stat-box">
                <div class="num" id="stat-avg-score">0%</div>
                <div class="lbl">متوسط أداء السجين</div>
            </div>
        </div>
    </section>

    <div class="case-filter-bar" style="margin-bottom:18px;">
        <input type="text" id="public-search" placeholder="ابحث باسم المتهم، أو المحقق، أو القضية المنشورة...">
    </div>

    <div id="public-grid" class="archive-cards-grid">
        <!-- Rendered by JS -->
    </div>
</main>

<!-- Modal: Reader for Published Session -->
<div id="session-reader-modal" class="modal-overlay">
    <div class="modal-card modal-card-xl" style="height:90vh;">
        <div class="modal-card-header">
            <div style="display:flex; align-items:center; gap:12px;">
                <img id="reader-portrait" src="assets/images/char_1/calm.jpg" style="width:45px; height:45px; border-radius:8px; object-fit:cover; border:1px solid #3b82f6;" alt="صورة السجين">
                <div>
                    <h3 id="reader-title" style="margin:0;">ملف الاستجواب الكامل</h3>
                    <div id="reader-meta" style="font-size:0.77rem; color:var(--text-muted);">تحميل البيانات...</div>
                </div>
            </div>
            <button type="button" class="modal-close-btn" onclick="closeReaderModal()">✕</button>
        </div>
        <div id="reader-chat-messages" class="chat-messages" style="flex:1; margin-top:14px;">
            <!-- Messages rendered here -->
        </div>
    </div>
</div>

<script src="assets/session-view.js?v=<?= (int)filemtime(__DIR__ . "/assets/session-view.js") ?>"></script>
<script>
let publishedSessions = [];

async function loadPublicData() {
    const res = await fetch('api.php?action=public_sessions');
    const data = await res.json();
    if (!data.ok) return;

    publishedSessions = (data.logs || []).filter(l => l.is_published);
    document.getElementById('stat-published-count').textContent = publishedSessions.length;

    let totalScore = 0;
    let scoredCount = 0;
    publishedSessions.forEach(s => {
        if (s.evaluation && Number.isFinite(s.evaluation.prisoner_score ?? s.evaluation.overall_score)) {
            totalScore += s.evaluation.prisoner_score ?? s.evaluation.overall_score;
            scoredCount++;
        }
    });
    const avg = scoredCount > 0 ? Math.round(totalScore / scoredCount) : 0;
    document.getElementById('stat-avg-score').textContent = avg + '%';

    renderPublicGrid('');

    // Check URL params for direct session load
    const params = new URLSearchParams(window.location.search);
    const sid = params.get('session_id');
    if (sid) {
        openSessionReader(sid);
    }
}

function renderPublicGrid(q) {
    const container = document.getElementById('public-grid');
    container.innerHTML = '';

    const query = q.trim().toLowerCase();
    const filtered = publishedSessions.filter(s => {
        if (!query) return true;
        return (s.case_title && s.case_title.toLowerCase().includes(query)) ||
               (s.model && s.model.toLowerCase().includes(query)) ||
               (s.interrogator_model && s.interrogator_model.toLowerCase().includes(query)) ||
               (s.session_id && s.session_id.toLowerCase().includes(query));
    });

    if (filtered.length === 0) {
        container.innerHTML = '<div style="color:var(--text-muted);text-align:center;padding:40px;grid-column:1/-1;">لا توجد جلسات منشورة مطابقة للبحث حتى الآن.</div>';
        return;
    }

    filtered.forEach(s => {
        const card = document.createElement('div');
        card.className = 'archive-card';

        const imgSlug = s.character_slug || s.gender || 'char_1';
        const st = s.current_state || 'calm';
        const imgPath = `assets/images/${imgSlug}/${st}.jpg`;

        const isAI = (s.interrogator_type !== 'human');
        const interrogatorLabel = isAI
            ? `🤖 ${escapeHtml(s.interrogator_character?.name || 'المحقق الذكي')}: <strong>${escapeHtml(s.interrogator_model || 'AI')}</strong>`
            : `🕵️‍♂️ المحقق: <strong>مستخدم بشري</strong>`;

        const evalScore = s.evaluation ? s.evaluation.overall_score : s.breaking_point;
        const evalBadge = s.evaluation
            ? `<span class="state-badge-pill" style="background:#059669;">السجين: ${s.evaluation.prisoner_score ?? s.evaluation.overall_score}% · المحقق: ${s.evaluation.investigator_score ?? '—'}% (${escapeHtml(s.evaluation.grade_ar || '')})</span>`
            : `<span class="state-badge-pill" style="background:#2563eb;">نقطة الانكسار: ${s.breaking_point || 0}%</span>`;

        card.innerHTML = `
            <div class="archive-card-top">
                <img src="${imgPath}" class="archive-card-avatar" alt="${escapeHtml(s.case_title)}" onerror="this.src='assets/images/char_1/calm.jpg'">
                <div class="archive-card-header-info">
                    <div class="archive-card-name">${escapeHtml(s.case_title)}</div>
                    <div class="archive-card-roles">${interrogatorLabel}</div>
                    <div class="archive-card-roles">🔒 المتهم: <strong>${s.interrogator_type==='reverse'?'مستخدم بشري':escapeHtml(s.model)}</strong></div>
                </div>
            </div>
            <div class="archive-card-body">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                    ${evalBadge}
                    <span style="font-size:0.75rem; color:#94a3b8;">${s.turns_count} ردود</span>
                </div>
                <div class="meter-bar" style="height:6px; margin-bottom:8px;">
                    <div class="meter-fill" style="width:${evalScore}%; background:#38bdf8;"></div>
                </div>
                <div style="display:flex; justify-content:space-between; align-items:center; font-size:0.72rem; color:#64748b;">
                    <span>نُشرت: ${s.published_at || s.updated_at}</span>
                    <button type="button" class="btn btn-secondary" style="padding:4px 10px; font-size:0.75rem;">تصفح الحوار 🔍</button>
                </div>
            </div>
        `;

        card.addEventListener('click', () => openSessionReader(s.session_id));
        container.appendChild(card);
    });
}

async function openSessionReader(sessionId) {
    const res = await fetch(`api.php?action=load_session&public=1&session_id=${encodeURIComponent(sessionId)}`);
    const data = await res.json();
    if (!data.ok) return alert('تعذر تحميل الجلسة');

    const s = data.session;
    const imgSlug = s.character_slug || s.gender || 'char_1';
    document.getElementById('reader-portrait').src = `assets/images/${imgSlug}/${s.current_state || 'calm'}.jpg`;
    document.getElementById('reader-title').textContent = s.case_title || 'جلسة استجواب';
    document.getElementById('reader-meta').textContent = `
        المحقق: ${s.interrogator_type === 'ai' ? s.interrogator_model : 'مستخدم بشري'} •
        السجين: ${s.model} •
        الحالة النهائية: ${s.current_state} •
        الردود: ${s.turns_count}
    `;

    const chatBox = document.getElementById('reader-chat-messages');
    chatBox.replaceChildren();
    for (const m of s.messages || []) chatBox.appendChild(LabView.message(m,s));
    if(s.evaluation) chatBox.appendChild(LabView.evaluation(s));
    if(s.evaluation)chatBox.appendChild(LabView.benchmark({...s,can_review:false}));
    const link=document.createElement('a');link.href='public.php?session_id='+encodeURIComponent(s.session_id);link.textContent='رابط مشاركة هذه الجلسة';link.className='btn btn-secondary';chatBox.appendChild(link);
    document.getElementById('session-reader-modal').classList.add('active');
}

function closeReaderModal() {
    document.getElementById('session-reader-modal').classList.remove('active');
}

function extractCleanReply(text) {
    if (!text) return '';
    let s = String(text).trim();
    if (!s.includes('"reply"')) return s;
    s = s.replace(/^```(?:json)?\s*/i, '').replace(/\s*```$/i, '').trim();
    const m = s.match(/"reply"\s*:\s*"([\s\S]*?)"\s*,?\s*\r?\n\s*"state_id"/);
    if (m) return m[1].replace(/\\"/g, '"').replace(/\\n/g, '\n');
    const m2 = s.match(/"reply"\s*:\s*"([\s\S]*)$/);
    if (m2) return m2[1].replace(/"\s*,?\s*\}\s*$/, '').replace(/\\"/g, '"').replace(/\\n/g, '\n');
    return s;
}

function escapeHtml(str) { return LabView.escape(str); }

document.getElementById('public-search').addEventListener('input', (e) => {
    renderPublicGrid(e.target.value);
});

window.addEventListener('DOMContentLoaded', loadPublicData);
</script>
</body>
</html>
