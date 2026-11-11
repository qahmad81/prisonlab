<?php
require_once __DIR__.'/lib/lab.php';require_once __DIR__.'/lib/access.php';
labBeginPage('public');
header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
$metaTitle=labT('public.public.1');$metaDescription=labT('public.public.2');$metaUrl='';$metaImage='';
try{$publicEnv=labRuntimeBootstrap();$metaBase=labBaseUrl($publicEnv);$metaUrl=$metaBase.labPageUrl('public',$_GET);$metaImage=$metaBase.'/assets/branding/prisonlab-512.png';
    $metaSid=$_GET['session_id'] ?? '';
    if($metaSid!==''){
        if(!is_string($metaSid) || !preg_match('/^session_[a-zA-Z0-9_]+$/D',$metaSid))throw new RuntimeException('private');
        $metaPath=$GLOBALS['lab_storage'].'/'.$metaSid.'.json';$metaSession=is_file($metaPath)?labRead($metaPath):[];
        if(empty($metaSession['is_published']) || empty($metaSession['evaluation']) || ($metaSession['evaluation_status'] ?? '')!=='complete')throw new RuntimeException('private');
        $metaTitle='PrisonLab | '.mb_substr($metaSession['case_title'],0,120);
        $metaDescription=labT('public.public.3').($metaSession['operator_profile']['display_name'] ?? labT('public.public.4')).labT('public.public.5').($metaSession['evaluation']['prisoner_score'] ?? '—').labT('public.public.6').($metaSession['evaluation']['investigator_score'] ?? '—').labT('standalone.public.public.7').labSessionCost($metaSession)['total_usd'];
        $metaUrl=$metaBase.labPageUrl('public',['session_id'=>$metaSid]);
    }elseif(!empty($_GET['case_file'])){
        $case=labCase((string)$_GET['case_file']);$metaTitle='PrisonLab | '.$case['title'];
    }elseif(!empty($_GET['investigator'])){
        $persona=labInvestigator((string)$_GET['investigator']);$metaTitle='PrisonLab | '.$persona['name'];$metaDescription=$persona['bio'];
    }
}catch(Throwable $e){if(!empty($_GET['session_id'])){http_response_code(404);$metaTitle=labT('public.public.15');$metaDescription=labT('public.public.16');$metaUrl='';}}
$metaEscape=fn($v)=>htmlspecialchars($v,ENT_QUOTES,'UTF-8');
?>
<!DOCTYPE html>
<html <?= labLocaleAttributes() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $metaEscape($metaTitle) ?></title>
    <meta name="description" content="<?= $metaEscape($metaDescription) ?>">
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= $metaEscape($metaTitle) ?>">
    <meta property="og:description" content="<?= $metaEscape($metaDescription) ?>">
    <?php if($metaUrl!==''): ?><meta property="og:url" content="<?= $metaEscape($metaUrl) ?>"><link rel="canonical" href="<?= $metaEscape($metaUrl) ?>"><?php endif; ?>
    <?php if($metaImage!==''): ?><meta property="og:image" content="<?= $metaEscape($metaImage) ?>"><meta property="og:image:alt" content="PrisonLab"><?php endif; ?>
    <link rel="icon" href="/favicon.ico?v=2" sizes="any">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg?v=2">
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/branding/prisonlab-32.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/branding/prisonlab-180.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/style.css?v=<?= (int)filemtime(__DIR__ . "/assets/style.css") ?>">
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
        <a class="brand-home" href="<?= labPageHref('home') ?>" aria-label="<?= labE('public.html.1') ?>" title="<?= labE('public.html.2') ?>"><img class="brand-logo" src="/assets/branding/prisonlab-128.png" alt="PrisonLab" width="48" height="48"></a>
        <div class="brand-copy">
            <div class="brand-title-row"><span class="brand-name" dir="ltr">PrisonLab</span><?php labLanguageMenu(); ?></div>
            <h1><?= labE('public.html.3') ?></h1>
        </div>
    </div>
    <div class="header-actions">
        <nav class="brand-links" aria-label="<?= labE('index.html.9') ?>"><a class="repo-link" href="https://github.com/qahmad81/prisonlab" target="_blank" rel="noopener noreferrer" aria-label="<?= labE('repository.link') ?>" title="<?= labE('repository.link') ?>"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="m8 7-5 5 5 5m8-10 5 5-5 5m-3-14-2 18"/></svg><span dir="ltr">GitHub</span></a></nav>
        <a href="<?= labPageHref('home') ?>" class="btn btn-primary" style="text-decoration:none; display:inline-flex; align-items:center; gap:8px;">
            <?= labE('public.html.7') ?>
        </a>
    </div>
</header>

<main class="public-container">
    <section class="public-hero">
        <div>
            <h2><?= labE('public.html.8') ?></h2>
            <p><?= labE('standalone.public.html.9') ?></p>
        </div>
        <div class="public-stats">
            <div class="public-stat-box">
                <div class="num" id="stat-published-count">0</div>
                <div class="lbl"><?= labE('public.html.10') ?></div>
            </div>
            <div class="public-stat-box">
                <div class="num" id="stat-avg-score">0%</div>
                <div class="lbl"><?= labE('public.html.11') ?></div>
            </div>
        </div>
    </section>

    <div class="case-filter-bar" style="margin-bottom:18px;">
        <input type="text" id="public-search" placeholder="<?= labE('public.html.31') ?>">
    </div>

    <section id="public-context-heading" hidden></section>
    <div id="public-grid" class="archive-cards-grid">
        <!-- Rendered by JS -->
    </div>
</main>

<!-- Modal: Reader for Published Session -->
<div id="session-reader-modal" class="modal-overlay">
    <div class="modal-card modal-card-xl" style="height:90vh;">
        <div class="modal-card-header">
            <div style="display:flex; align-items:center; gap:12px;">
                <img id="reader-portrait" src="/assets/images/char_1/calm.jpg" style="width:45px; height:45px; border-radius:8px; object-fit:cover; border:1px solid #3b82f6;" alt="<?= labE('public.html.32') ?>">
                <div>
                    <h3 id="reader-title" style="margin:0;"><?= labE('public.html.33') ?></h3>
                    <div id="reader-meta" style="font-size:0.77rem; color:var(--text-muted);"><?= labE('public.html.34') ?></div>
                </div>
            </div>
            <button type="button" class="modal-close-btn" onclick="closeReaderModal()">✕</button>
        </div>
        <div id="reader-chat-messages" class="chat-messages" style="flex:1; margin-top:14px;">
            <!-- Messages rendered here -->
        </div>
    </div>
</div>

<?php labLocaleScript(); ?>
<script src="/assets/i18n.js?v=<?= (int)filemtime(__DIR__.'/assets/i18n.js') ?>"></script>
<script src="/assets/links.js?v=<?= (int)filemtime(__DIR__.'/assets/links.js') ?>"></script>
<script src="/assets/session-view.js?v=<?= (int)filemtime(__DIR__ . "/assets/session-view.js") ?>"></script>
<script src="/assets/lab-cards.js?v=<?= (int)filemtime(__DIR__.'/assets/lab-cards.js') ?>"></script>
<script src="/assets/community.js?v=<?= (int)filemtime(__DIR__ . '/assets/community.js') ?>"></script>
<script>
let publishedSessions = [];
let publicCatalog = {};

async function loadPublicData() {
    const data = await LabCommunity.fetchJson('api.php?action=public_sessions');

    publicCatalog=data;
    const scope=LabI18n.publicParams();
    publishedSessions=(data.logs || []).filter(s=>s.is_published &&
        (!scope.get('case_file') || s.case_file===scope.get('case_file')) &&
        (!scope.get('investigator') || s.interrogator_character?.id===scope.get('investigator')) &&
        (!scope.get('model') || (scope.get('role')==='prisoner'?s.model===scope.get('model'):scope.get('role')==='interrogator'?s.interrogator_model===scope.get('model'):[s.model,s.interrogator_model].includes(scope.get('model')))));
    const file=data.cases?.find(c=>c.filename===scope.get('case_file'));
    const persona=data.investigators?.find(p=>p.id===scope.get('investigator'));
    const model=data.models?.find(m=>m.id===scope.get('model'));
    if(file || persona || model){
        const box=document.getElementById('public-context-heading');box.hidden=false;
        const title=document.createElement('h2');title.textContent=file?.short_title || persona?.name || model?.name;box.appendChild(title);
        if(file || persona){
            const text=document.createElement('p');text.textContent=file?.character_name || persona.bio;box.appendChild(text);
        }
        if(file){
            const details=document.createElement('details'),summary=document.createElement('summary'),content=document.createElement('pre');
            summary.textContent=LabI18n.t('public-community.context.6');content.className='public-case-content';content.textContent=file.content;
            details.append(summary,content);box.appendChild(details);
            const start=document.createElement('a');start.href=LabI18n.pageUrl('home',{case_file:file.filename});
            start.textContent=LabI18n.t('public-community.context.7');box.appendChild(start);
        }
    }
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
    const params = LabI18n.publicParams();
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
        container.innerHTML = `<div style="color:var(--text-muted);text-align:center;padding:40px;grid-column:1/-1;">${LabI18n.html("public.renderPublicGrid.1")}</div>`;
        return;
    }

    filtered.forEach(s=>container.appendChild(LabCards.card(s,{open:openSessionReader})));
}

async function openSessionReader(sessionId) {
    let data;try{data=await LabCommunity.fetchJson(`api.php?action=load_session&public=1&session_id=${encodeURIComponent(sessionId)}`);}catch(e){return alert(LabI18n.t("public.openSessionReader.1")+e.message);}

    const s = data.session;
    const imgSlug = s.character_slug || s.gender || 'char_1';
    document.getElementById('reader-portrait').src = `/assets/images/${imgSlug}/${s.current_state || 'calm'}.jpg`;
    document.getElementById('reader-title').textContent = s.case_title || LabI18n.t("public.openSessionReader.2");
    document.getElementById('reader-meta').textContent = LabI18n.t("public.openSessionReader.4", {"value1": s.interrogator_type === 'ai' ? s.interrogator_model : LabI18n.t("public.openSessionReader.3"), "model": s.model, "current_state": s.current_state, "turns_count": s.turns_count});

    document.getElementById('reader-meta').insertAdjacentHTML('beforeend',LabView.participants(s));
    const chatBox = document.getElementById('reader-chat-messages');
    chatBox.replaceChildren();
    for (const m of s.messages || []) chatBox.appendChild(LabView.message(m,s));
    if(s.evaluation) chatBox.appendChild(LabView.evaluation(s));
    chatBox.appendChild(LabCommunity.result({...s,can_delete:false}));
    if(s.evaluation)chatBox.appendChild(LabView.benchmark({...s,can_review:false}));
    const link=document.createElement('a');link.href=LabI18n.pageUrl('public',{session_id:s.session_id});link.textContent=LabI18n.t("public.openSessionReader.5");link.className='btn btn-secondary';chatBox.appendChild(link);
    const params=LabI18n.publicParams();params.set('session_id',s.session_id);LabI18n.replacePublic(params);
    document.getElementById('session-reader-modal').classList.add('active');
}

function closeReaderModal() {
    const params=LabI18n.publicParams();params.delete('session_id');LabI18n.replacePublic(params);
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

window.addEventListener('DOMContentLoaded',()=>loadPublicData().catch(e=>{document.getElementById('public-grid').textContent=LabI18n.t("public.ui.1")+e.message;}));
</script>
</body>
</html>
