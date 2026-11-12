<?php require_once __DIR__ . '/lib/i18n.php'; labBeginPage('home'); ?>
<!DOCTYPE html>
<html <?= labLocaleAttributes() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= labE('index.html.1') ?></title>
    <link rel="icon" href="/favicon.ico?v=2" sizes="any">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg?v=2">
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/branding/prisonlab-32.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/branding/prisonlab-180.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/style.css?v=<?= (int)filemtime(__DIR__ . "/assets/style.css") ?>">
</head>
<body>
<div id="connection-toast" class="connection-toast" role="status" aria-live="polite" hidden></div>

<header class="top-bar">
    <div class="brand">
        <a class="brand-home" href="<?= labPageHref('home') ?>" aria-label="<?= labE('public.html.1') ?>" title="<?= labE('public.html.2') ?>"><img class="brand-logo" src="/assets/branding/prisonlab-128.png" alt="PrisonLab" width="48" height="48"></a>
        <div class="brand-copy">
            <div class="brand-title-row"><span class="brand-name" dir="ltr">PrisonLab</span><?php labLanguageMenu(); ?></div>
            <h1><?= labE('index.html.4') ?></h1>
        </div>
    </div>
    <div class="header-actions">
        <div id="account-controls" class="account-controls" hidden></div>
        <nav class="brand-links" aria-label="<?= labE('index.html.9') ?>"><a class="repo-link" href="https://github.com/qahmad81/prisonlab" target="_blank" rel="noopener noreferrer" aria-label="<?= labE('repository.link') ?>" title="<?= labE('repository.link') ?>"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="m8 7-5 5 5 5m8-10 5 5-5 5m-3-14-2 18"/></svg><span dir="ltr">GitHub</span></a></nav>
        <button type="button" id="open-key-modal-btn" class="user-key-btn" onclick="App.openKeyModal()">
            <?= labE('index.html.13') ?>
        </button>
    </div>
</header>

<main class="layout">
    <!-- العمود الأيمن: إعدادات الجلسة والقضية -->
    <section class="panel">
        <div class="panel-header">
            <span><?= labE('index.html.14') ?></span>
        </div>
        <section id="account-notice" class="account-notice" aria-live="polite"></section>
        <div class="panel-body">
            <div class="form-group session-mode-group">
                <label id="session-mode-label"><?= labE('index.html.15') ?></label>
                <div class="session-mode-switch" role="group" aria-labelledby="session-mode-label">
                    <button type="button" id="mode-btn-human" class="gender-btn active" aria-pressed="true" onclick="App.setInterrogationMode('human')"><?= labE('index.html.16') ?></button>
                    <button type="button" id="mode-btn-reverse" class="gender-btn" aria-pressed="false" onclick="App.setInterrogationMode('reverse')"><?= labE('index.html.17') ?></button>
                    <button type="button" id="mode-btn-ai" class="gender-btn mode-ai-duel" aria-pressed="false" onclick="App.setInterrogationMode('ai')">
                        <img class="duel-face duel-face-right" src="/assets/icons/ai-profile.svg" alt="" aria-hidden="true" width="38" height="38">
                        <span class="duel-caption"><span class="duel-swords" aria-hidden="true">⚔️</span><span dir="ltr"><?= labE('mode.ai_duel') ?></span></span>
                        <img class="duel-face duel-face-left" src="/assets/icons/ai-profile.svg" alt="" aria-hidden="true" width="38" height="38">
                    </button>
                </div>
            </div>

            <article class="role-card role-card-prisoner" aria-labelledby="prisoner-role-heading">
                <header class="role-card-header"><h2 id="prisoner-role-heading"><span>01</span> <?= labE('roles.prisoner') ?></h2><span id="prisoner-representation-label" class="role-kind"><?= labE('index.html.19') ?></span></header>
                <div class="role-character-section">
                    <div class="role-section-label"><span><?= labE('index.html.20') ?></span><button type="button" id="open-case-modal-btn" class="role-change" onclick="App.openCaseModal()"><?= labE('index.html.21') ?></button></div>
                    <button type="button" id="selected-case-summary" class="selected-case-card" onclick="App.openCaseModal()" title="<?= labE('index.html.22') ?>"></button>
<div id="case-setup-tags" class="setup-character-tags"><a id="case-share-link" hidden></a></div>
                </div>
                <div class="role-representation">
                    <div class="role-section-label"><span><?= labE('index.html.33') ?></span><span id="prisoner-performer-type"><?= labE('index.html.24') ?></span></div>
                    <div id="prisoner-model-group">
                        <div class="setup-model-heading"><button type="button" id="selected-model-card" class="selected-model-card" onclick="App.openModelModal('prisoner')" title="<?= labE('index.html.25') ?>"></button><a id="prisoner-share-link" hidden></a></div><div class="setup-model-meta" id="prisoner-setup-meta"></div>
                        <button type="button" id="prisoner-reasoning-btn" class="reasoning-control" onclick="App.openReasoningModal('prisoner')" aria-haspopup="dialog"></button>
                        <div id="model-status-tip" class="role-model-tip"></div>
                    </div>
                    <div id="prisoner-human-card" class="human-performer" hidden></div>
                </div>
            </article>

            <div class="role-swap-row">
                <button type="button" id="swap-roles-btn" class="role-swap-button" onclick="App.swapRoles()" aria-label="<?= labE('index.html.27') ?>" title="<?= labE('index.html.27') ?>">
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 6h2c6 0 10 12 16 12h2M2 18h2C10 18 14 6 20 6h2M18 2l4 4-4 4M18 14l4 4-4 4"/></svg>
                </button>
            </div>

            <article class="role-card role-card-interrogator" aria-labelledby="interrogator-role-heading">
                <header class="role-card-header"><h2 id="interrogator-role-heading"><span>02</span> <?= labE('roles.interrogator') ?></h2><span id="interrogator-representation-label" class="role-kind"><?= labE('index.html.29') ?></span></header>
                <div class="role-character-section" id="interrogator-persona-group" hidden>
                    <div class="role-section-label"><span><?= labE('index.html.30') ?></span><span><?= labE('index.html.31') ?></span></div>
                    <button type="button" id="selected-investigator-persona" class="investigator-selected" onclick="App.openInvestigatorModal()"></button>
<div id="investigator-setup-tags" class="setup-character-tags"><a id="investigator-share-link" hidden></a></div>
                </div>
                <div id="human-investigator-character" class="human-character-note"><?= labE('index.html.32') ?></div>
                <div class="role-representation">
                    <div class="role-section-label"><span><?= labE('index.html.33') ?></span><span id="interrogator-performer-type"><?= labE('index.html.34') ?></span></div>
                    <div id="interrogator-model-group" hidden>
                        <div class="setup-model-heading"><button type="button" id="selected-interrogator-card" class="selected-model-card" onclick="App.openModelModal('interrogator')" title="<?= labE('index.html.35') ?>"></button><a id="interrogator-share-link" hidden></a></div><div class="setup-model-meta" id="interrogator-setup-meta"></div>
                        <button type="button" id="interrogator-reasoning-btn" class="reasoning-control" onclick="App.openReasoningModal('interrogator')" aria-haspopup="dialog"></button>
                    </div>
                    <div id="interrogator-human-card" class="human-performer"></div>
                </div>
            </article>
            <div id="reverse-role-brief" class="reverse-role-brief" hidden><?= labE('index.html.36') ?></div>

            <section id="jury-setup-panel" class="jury-controls"></section>
            <button type="button" id="start-session-btn" class="btn btn-primary" style="margin-top:6px;"><?= labE('index.html.37') ?></button>

            <hr style="border-color: var(--border); margin: 16px 0;">

            <!-- زري الأرشيف المطورين -->
            <div class="form-group">
                <label><?= labE('index.html.38') ?></label>
                <div class="archive-buttons-container">
                    <button type="button" class="btn-archive-action btn-archive-local" onclick="App.openArchiveModal('local')">
                        <?= labE('index.html.39') ?><span id="archive-local-count">0</span>)
                    </button>
                    <button type="button" class="btn-archive-action btn-archive-public" onclick="App.openArchiveModal('public')">
                        <?= labE('index.html.40') ?><span id="archive-public-count">0</span>)
                    </button>
                </div>
            </div>
        </div>
    </section>

    <!-- العمود الأوسط: غرفة الحوار والضغط -->
    <section class="panel">
        <div class="panel-header">
            <span><?= labE('index.html.41') ?></span>
            <div style="display:flex; gap:8px; align-items:center;">
                <span id="active-session-id" style="font-size:0.76rem; color:var(--text-muted);"><?= labE('index.html.42') ?></span>
                <button type="button" id="compact-btn" class="btn btn-secondary" style="padding:5px 10px; font-size:0.76rem;"><?= labE('index.html.43') ?></button>
                <button type="button" id="end-session-btn" class="btn btn-danger" style="padding:5px 10px; font-size:0.76rem; background:#dc2626; border-color:#ef4444; color:#fff;" onclick="App.endAndEvaluateSession()"><?= labE('index.html.44') ?></button>
            </div>
        </div>

        <div id="chat-messages" class="chat-messages">
            <div style="text-align:center; color:var(--text-muted); margin-top:60px;">
                <?= labE('index.html.45') ?> <strong><?= labE('index.html.46') ?></strong> <?= labE('index.html.47') ?>
            </div>
        </div>
        <button type="button" id="reverse-resume-btn" class="btn btn-secondary" onclick="App.requestReverseQuestion()" hidden><?= labE('index.html.48') ?></button>

        <!-- شريط تحكم AI ضد AI -->
        <div id="ai-vs-ai-controls" class="ai-vs-ai-bar" style="display:none;">
            <div style="display:flex; justify-content:space-between; align-items:center; width:100%;">
                <div style="display:flex; align-items:center; gap:8px;">
                    <span class="ai-pulse-dot"></span>
                    <span style="font-size:0.86rem; font-weight:700; color:#38bdf8;"><?= labE('index.html.49') ?></span>
                    <span id="ai-turn-counter" style="font-size:0.8rem; color:#cbd5e1;"><?= labE('index.html.50') ?></span>
                </div>
                <button type="button" id="resume-investigation-btn" class="btn btn-resume" onclick="App.resumeInvestigation()" hidden><?= labE('index.html.51') ?></button>
            </div>
        </div>

        <div class="quick-tactics" id="human-tactics-bar">
            <span class="tactic-chip" data-prompt="<?= htmlspecialchars(labPrompt('tactic.1'), ENT_QUOTES, 'UTF-8') ?>"><?= labE('index.html.52') ?></span>
            <span class="tactic-chip" data-prompt="<?= htmlspecialchars(labPrompt('tactic.2'), ENT_QUOTES, 'UTF-8') ?>"><?= labE('index.html.53') ?></span>
            <span class="tactic-chip" data-prompt="<?= htmlspecialchars(labPrompt('tactic.3'), ENT_QUOTES, 'UTF-8') ?>"><?= labE('index.html.54') ?></span>
            <span class="tactic-chip" data-prompt="<?= htmlspecialchars(labPrompt('tactic.4'), ENT_QUOTES, 'UTF-8') ?>"><?= labE('index.html.55') ?></span>
            <span class="tactic-chip" data-prompt="<?= htmlspecialchars(labPrompt('tactic.5'), ENT_QUOTES, 'UTF-8') ?>"><?= labE('index.html.56') ?></span>
        </div>

        <button type="button" id="end-test-btn" class="end-test-button" onclick="App.endAndEvaluateSession()" disabled><?= labE('index.html.57') ?></button>
        <div id="session-notice" class="session-notice" role="status" aria-live="polite"></div>
        <section id="jury-session-panel" class="jury-controls" hidden></section>
        <div id="jury-live-status" class="jury-live-status" hidden></div>
        <details id="mcp-session-panel" class="memory-panel mcp-session-panel" hidden><summary><?= labE('index.html.58') ?></summary><p><?= labE('index.html.59') ?></p><div id="mcp-gates"></div><p id="mcp-wait-state" role="status"></p><button id="mcp-pause-btn" type="button" class="btn btn-secondary" onclick="MCPUI.pauseRequested=true" hidden><?= labE('index.html.60') ?></button></details>
        <details id="memory-notes" class="memory-panel" hidden></details>
        <div id="session-funding-label" class="usage-summary" role="status" hidden></div>
        <div id="usage-summary" class="usage-summary"></div>
        <div class="chat-input-bar" id="human-input-bar">
            <textarea id="user-input" placeholder="<?= labE('index.html.61') ?>"></textarea>
            <button type="button" id="send-btn" class="btn btn-primary" style="width:auto; min-width:170px; height:54px;"><?= labE('index.html.62') ?></button>
        </div>
    </section>

    <!-- العمود الأيسر: الكاميرا الحية ومؤشرات المشاعر والحالات الـ 20 -->
    <section class="panel">
        <div class="panel-header">
            <span><?= labE('index.html.63') ?></span>
        </div>
        <div class="panel-body">
            <div class="portrait-stage">
                <img id="prisoner-portrait" src="/assets/images/char_1/calm.jpg" alt="<?= labE('index.html.64') ?>">
                <div class="state-badge-overlay">
                    <span style="font-size:0.8rem; color:#cbd5e1;"><?= labE('index.html.65') ?></span>
                    <span id="current-state-pill" class="state-badge-pill" style="background:#3b82f6;"><?= labE('index.html.66') ?></span>
                </div>
            </div>

            <div class="sense-box">
                <div style="font-size:0.82rem; font-weight:700; margin-bottom:8px; color:#38bdf8;">
                    <?= labE('index.html.67') ?>
                </div>

                <div class="meter-row">
                    <div class="meter-label"><span><?= labE('index.html.68') ?></span><span id="val-fear">0%</span></div>
                    <div class="meter-bar"><div id="bar-fear" class="meter-fill" style="width:0%; background:#f97316;"></div></div>
                </div>

                <div class="meter-row">
                    <div class="meter-label"><span><?= labE('index.html.69') ?></span><span id="val-anger">0%</span></div>
                    <div class="meter-bar"><div id="bar-anger" class="meter-fill" style="width:0%; background:#ef4444;"></div></div>
                </div>

                <div class="meter-row">
                    <div class="meter-label"><span><?= labE('index.html.70') ?></span><span id="val-trust">0%</span></div>
                    <div class="meter-bar"><div id="bar-trust" class="meter-fill" style="width:0%; background:#10b981;"></div></div>
                </div>

                <div class="meter-row">
                    <div class="meter-label"><span><?= labE('index.html.71') ?></span><span id="val-collapse">0%</span></div>
                    <div class="meter-bar"><div id="bar-collapse" class="meter-fill" style="width:0%; background:#64748b;"></div></div>
                </div>

                <div class="meter-row">
                    <div class="meter-label"><span><?= labE('index.html.72') ?></span><span id="val-breaking">0%</span></div>
                    <div class="meter-bar"><div id="bar-breaking" class="meter-fill" style="width:0%; background:#eab308;"></div></div>
                </div>

                <div id="sense-directive-text" style="font-size:0.75rem; color:#94a3b8; margin-top:8px; line-height:1.45;">
                    <?= labE('index.html.73') ?>
                </div>
            </div>

            <div id="states-gallery" hidden></div>
        </div>
    </section>
</main>
<footer class="lab-footer">        <div class="env-pills">
            <div class="env-pill"><?= labE('index.html.74') ?> <strong id="sense-model-pill">Jev</strong></div>
            <div class="env-pill"><?= labE('index.html.75') ?> <strong id="compact-model-pill">...</strong></div>
            <div class="env-pill"><?= labE('index.html.76') ?> <strong id="models-count-pill">0</strong></div>
        </div>
<a href="https://odehit.com/" target="_blank" rel="noopener"><?= labE('index.html.77') ?> <b dir="ltr">OdehIt</b></a></footer>

<!-- Modal: Case Selector -->
<div id="case-modal" class="modal-overlay">
    <div class="modal-card modal-card-xl">
        <div class="modal-card-header">
            <div style="display:flex; align-items:center; gap:10px;">
                <span style="font-size:1.3rem;">📂</span>
                <div>
                    <h3 style="margin:0;"><?= labE('index.html.78') ?></h3>
                    <div style="font-size:0.78rem; color:var(--text-muted);"><?= labE('index.html.79') ?></div>
                </div>
            </div>
            <button type="button" class="modal-close-btn" onclick="App.closeCaseModal()">✕</button>
        </div>
        <div class="case-modal-body">
            <!-- القائمة اليمينية: بطاقات الشخصيات الـ 16 -->
            <div class="case-list-column">
                <div class="case-filter-bar">
                    <input type="text" id="case-modal-search" placeholder="<?= labE('index.html.80') ?>">
                </div>
                <div id="case-cards-container" class="case-cards-grid">
                    <!-- يتم بناؤه بالجافاسكريبت -->
                </div>
            </div>

            <!-- العمود الجانبي: المعاينة المكبرة وتفاصيل القضية مع سلايدر السحب -->
            <div class="case-detail-column">
                <div class="case-detail-header">
                    <img id="modal-detail-portrait" src="/assets/images/char_1/calm.jpg" alt="<?= labE('index.html.81') ?>">
                    <div class="case-detail-titles">
                        <h4 id="modal-detail-name"><?= labE('index.html.82') ?></h4>
                        <div id="modal-detail-sub" class="case-detail-sub"><?= labE('index.html.83') ?></div>
                        <div id="modal-detail-state-pill" class="state-badge-pill" style="margin-top:4px;"><?= labE('index.html.84') ?></div>
                    </div>
                </div>
                <div class="case-detail-section-title">
                    <span><?= labE('index.html.85') ?></span>
                </div>
                <div id="modal-detail-content" class="case-detail-content-scroll">
                    <!-- نص ملف الماركداون كاملاً مع سلايدر عمودي -->
                </div>
                <button type="button" id="confirm-case-btn" class="btn btn-primary" onclick="App.confirmSelectedCase()" style="margin-top:12px;">
                    <?= labE('index.html.86') ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Model Selector -->
<div id="model-modal" class="modal-overlay">
    <div class="modal-card modal-card-xl">
        <div class="modal-card-header">
            <div style="display:flex; align-items:center; gap:10px;">
                <span style="font-size:1.3rem;">🤖</span>
                <div>
                    <h3 style="margin:0;"><?= labE('index.html.87') ?></h3>
                    <div id="model-modal-description" style="font-size:0.78rem; color:var(--text-muted);"><?= labE('index.html.88') ?></div>
                </div>
            </div>
            <button type="button" class="modal-close-btn" onclick="App.closeModelModal()">✕</button>
        </div>
        <div class="model-modal-toolbar">
            <button type="button" class="mcp-connect-option" onclick="MCPUI.configure()"><strong dir="ltr">MCP ↗</strong><span><?= labE('index.html.89') ?></span><small><?= labE('index.html.90') ?></small></button>
            <input type="text" id="model-modal-search" placeholder="<?= labE('index.html.91') ?>">
            <div class="model-tabs">
                <button type="button" class="model-tab active" data-tab="focus_economic" onclick="App.filterModelTab('focus_economic')"><?= labE('index.html.92') ?><span id="tab-count-focus_economic">0</span>)</button>
                <button type="button" class="model-tab" data-tab="focus_medium" onclick="App.filterModelTab('focus_medium')"><?= labE('index.html.93') ?><span id="tab-count-focus_medium">0</span>)</button>
                <button type="button" class="model-tab" data-tab="focus_high" onclick="App.filterModelTab('focus_high')"><?= labE('index.html.94') ?><span id="tab-count-focus_high">0</span>)</button>
                <button type="button" class="model-tab" data-tab="economic" onclick="App.filterModelTab('economic')"><?= labE('index.html.95') ?><span id="tab-count-economic">0</span>)</button>
                <button type="button" class="model-tab" data-tab="professional" onclick="App.filterModelTab('professional')"><?= labE('index.html.96') ?><span id="tab-count-professional">0</span>)</button>
                <button type="button" class="model-tab" data-tab="all" onclick="App.filterModelTab('all')"><?= labE('index.html.97') ?><span id="tab-count-all">0</span>)</button>
                <button type="button" class="model-tab" data-tab="free" onclick="App.filterModelTab('free')"><?= labE('index.html.98') ?><span id="tab-count-free">0</span>)</button>
            </div>
            <div class="discovery-toolbar"><select id="model-sort" aria-label="<?= labE('index.html.99') ?>"><option value="recommended"><?= labE('index.html.100') ?></option><option value="newest"><?= labE('index.html.101') ?></option><option value="popular"><?= labE('index.html.102') ?></option><option value="reliable"><?= labE('index.html.103') ?></option></select></div>
            <details class="curated-note"><summary><?= labE('index.html.104') ?></summary>
            <p><?= labE('index.html.105') ?></p>
            <p><?= labE('index.html.106') ?></p>
            <small id="ranking-source" class="ranking-source"></small>
            <small class="ranking-source"><?= labE('index.html.107') ?></small>
            </details>
        </div>
        <div id="modal-models-grid" class="modal-models-grid">
            <!-- يتم بناؤه بالجافاسكريبت -->
        </div>
    </div>
</div>

<!-- Modal: Archive Modal (Session / Public Logs in Product Grid Style) -->
<div id="archive-modal" class="modal-overlay">
    <div class="modal-card modal-card-xl">
        <div class="modal-card-header">
            <div style="display:flex; align-items:center; gap:10px;">
                <span id="archive-modal-icon" style="font-size:1.3rem;">📋</span>
                <div>
                    <h3 id="archive-modal-title" style="margin:0;"><?= labE('index.html.108') ?></h3>
                    <div id="archive-modal-sub" style="font-size:0.78rem; color:var(--text-muted);"><?= labE('index.html.109') ?></div>
                </div>
            </div>
            <div style="display:flex; align-items:center; gap:10px;">
                <a href="<?= labPageHref('public') ?>" target="_blank" class="btn btn-secondary" style="padding:4px 10px; font-size:0.78rem; text-decoration:none;">
                    <?= labE('index.html.110') ?>
                </a>
                <button type="button" class="modal-close-btn" onclick="App.closeArchiveModal()">✕</button>
            </div>
        </div>
        <div class="case-filter-bar" style="margin-top:12px; margin-bottom:10px;">
            <input type="text" id="archive-modal-search" placeholder="<?= labE('index.html.111') ?>">
        </div>
        <div id="archive-modal-grid" class="archive-cards-grid">
            <!-- يتم بناؤه بالجافاسكريبت كعرض منتجات شبكي -->
        </div>
    </div>
</div>

<!-- Modal: User OpenRouter Key -->
<div id="key-modal" class="modal-overlay">
    <div class="modal-card">
        <h3><?= labE('index.html.112') ?></h3>
        <div class="modal-note">
            ⚠️ <strong><?= labE('index.html.113') ?></strong> <?= labE('index.html.114') ?> <strong><?= labE('index.html.115') ?></strong> <?= labE('index.html.116') ?> <code>sk-or-v1-...</code>).<br>
            <?= labE('index.html.117') ?>
        </div>
        <div class="form-group">
            <label for="custom-api-key-input"><?= labE('index.html.118') ?></label>
            <input type="password" id="custom-api-key-input" placeholder="sk-or-v1-...">
        </div>
        <div class="modal-buttons">
            <button type="button" class="btn btn-secondary" onclick="App.clearUserKey()"><?= labE('index.html.119') ?></button>
            <button type="button" class="btn btn-secondary" onclick="App.closeKeyModal()"><?= labE('index.html.120') ?></button>
            <button type="button" class="btn btn-primary" style="width:auto;" onclick="App.saveUserKey()"><?= labE('index.html.121') ?></button>
        </div>
    </div>
</div>

<div id="investigator-profile-modal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="investigator-modal-title">
    <div class="modal-content investigator-modal">
        <div class="modal-header"><div><h2 id="investigator-modal-title"><?= labE('index.html.122') ?></h2><p><?= labE('index.html.123') ?></p></div><button type="button" class="modal-close-btn" onclick="document.getElementById('investigator-profile-modal').classList.remove('active')" aria-label="<?= labE('index.html.126') ?>">×</button></div>
        <div id="investigator-profile-grid" class="investigator-grid"></div>
    </div>
</div>
<div id="reasoning-modal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="reasoning-title">
    <section class="reasoning-dialog"><header><h3 id="reasoning-title"><?= labE('index.html.125') ?></h3><button type="button" class="text-button" onclick="App.closeReasoningModal()" aria-label="<?= labE('index.html.126') ?>">×</button></header><p id="reasoning-model-name" dir="ltr"></p><div id="reasoning-options"></div><p class="reasoning-help"><?= labE('index.html.127') ?></p></section>
</div>
<div id="mcp-config-modal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="mcp-role-title"><section class="reasoning-dialog mcp-config-dialog"><header><h2 id="mcp-role-title"><?= labE('index.html.148') ?></h2><button type="button" class="text-button" aria-label="<?= labE('index.html.149') ?>" onclick="document.getElementById('mcp-config-modal').classList.remove('active')">×</button></header><p><?= labE('index.html.150') ?></p><form id="mcp-config-form"><label for="mcp-name"><?= labE('index.html.151') ?></label><input id="mcp-name" required minlength="2" maxlength="100" placeholder="<?= labE('index.html.152') ?>" dir="auto"><label for="mcp-effort"><?= labE('index.html.153') ?></label><select id="mcp-effort"><option value="unknown"><?= labE('index.html.154') ?></option><option value="none"><?= labE('index.html.155') ?></option><option value="minimal"><?= labE('index.html.156') ?></option><option value="low"><?= labE('index.html.157') ?></option><option value="medium"><?= labE('index.html.158') ?></option><option value="high"><?= labE('index.html.159') ?></option><option value="xhigh"><?= labE('index.html.160') ?></option><option value="max"><?= labE('index.html.161') ?></option></select><p><?= labE('index.html.162') ?></p><button type="submit" class="btn btn-primary"><?= labE('index.html.163') ?></button></form></section></div>
<?php labLocaleScript(); ?>
<script src="/assets/i18n.js?v=<?= (int)filemtime(__DIR__.'/assets/i18n.js') ?>"></script>
<script src="/assets/links.js?v=<?= (int)filemtime(__DIR__.'/assets/links.js') ?>"></script>
<script src="/assets/session-view.js?v=<?= (int)filemtime(__DIR__ . "/assets/session-view.js") ?>"></script>
<script src="/assets/community.js?v=<?= (int)filemtime(__DIR__ . '/assets/community.js') ?>"></script>
<script src="/assets/lab-cards.js?v=<?= (int)filemtime(__DIR__.'/assets/lab-cards.js') ?>"></script>
<script src="/assets/jury-ui.js?v=<?= (int)filemtime(__DIR__ . "/assets/jury-ui.js") ?>"></script>
<script src="/assets/app.js?v=<?= (int)filemtime(__DIR__ . "/assets/app.js") ?>"></script>
<script src="/assets/mcp.js?v=<?= (int)filemtime(__DIR__.'/assets/mcp.js') ?>"></script>
</body>
</html>
