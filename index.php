<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PrisonLab | مختبر أداء نماذج الذكاء الاصطناعي</title>
    <link rel="icon" href="favicon.ico?v=2" sizes="any">
    <link rel="icon" type="image/svg+xml" href="favicon.svg?v=2">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/branding/prisonlab-32.png">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/branding/prisonlab-180.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css?v=<?= (int)filemtime(__DIR__ . "/assets/style.css") ?>">
</head>
<body>
<div id="connection-toast" class="connection-toast" role="status" aria-live="polite" hidden></div>

<header class="top-bar">
    <div class="brand">
        <a class="brand-home" href="index.php" aria-label="الرئيسية · PrisonLab" title="العودة للرئيسية"><img class="brand-logo" src="assets/branding/prisonlab-128.png" alt="PrisonLab" width="48" height="48"></a>
        <div class="brand-copy"><span class="brand-name" dir="ltr">PrisonLab</span><h1>مختبر أداء نماذج الذكاء الاصطناعي</h1></div>
    </div>
    <div class="header-actions">
        <div id="account-controls" class="account-controls">النسخة المستقلة</div>
        <nav class="brand-links" aria-label="معلومات الموقع"><a href="public.php">التجارب المنشورة</a><a href="privacy.php">الخصوصية</a><a href="https://odehit.com/#contact" target="_blank" rel="noopener noreferrer">تواصل معنا ↗</a></nav>
        <button type="button" id="open-key-modal-btn" class="user-key-btn" onclick="App.openKeyModal()">
            🔑 استخدام مفتاحي
        </button>
    </div>
</header>

<main class="layout">
    <!-- العمود الأيمن: إعدادات الجلسة والقضية -->
    <section class="panel">
        <div class="panel-header">
            <span>⚙️ إعداد جلسة استجواب جديدة</span>
        </div>
        <section id="account-notice" class="account-notice" aria-live="polite"></section>
        <div class="panel-body">
            <div class="form-group session-mode-group">
                <label id="session-mode-label">اختر دورك في التحقيق</label>
                <div class="session-mode-switch" role="group" aria-labelledby="session-mode-label">
                    <button type="button" id="mode-btn-human" class="gender-btn active" aria-pressed="true" onclick="App.setInterrogationMode('human')">أنا المحقق</button>
                    <button type="button" id="mode-btn-reverse" class="gender-btn" aria-pressed="false" onclick="App.setInterrogationMode('reverse')">أنا المتهم</button>
                    <button type="button" id="mode-btn-ai" class="gender-btn mode-ai-duel" aria-pressed="false" onclick="App.setInterrogationMode('ai')">
                        <img class="duel-face duel-face-right" src="assets/icons/ai-profile.svg" alt="" aria-hidden="true" width="38" height="38">
                        <span class="duel-caption"><span class="duel-swords" aria-hidden="true">⚔️</span><span dir="ltr">AI VS AI</span></span>
                        <img class="duel-face duel-face-left" src="assets/icons/ai-profile.svg" alt="" aria-hidden="true" width="38" height="38">
                    </button>
                </div>
            </div>

            <article class="role-card role-card-prisoner" aria-labelledby="prisoner-role-heading">
                <header class="role-card-header"><h2 id="prisoner-role-heading"><span>01</span> المتهم</h2><span id="prisoner-representation-label" class="role-kind">ذكاء اصطناعي</span></header>
                <div class="role-character-section">
                    <div class="role-section-label"><span>الشخصية والقضية</span><button type="button" id="open-case-modal-btn" class="role-change" onclick="App.openCaseModal()">استعراض القضايا ↗</button></div>
                    <button type="button" id="selected-case-summary" class="selected-case-card" onclick="App.openCaseModal()" title="تغيير شخصية المتهم والقضية"></button>
<div id="case-setup-tags" class="setup-character-tags"><a id="case-share-link" hidden></a></div>
                </div>
                <div class="role-representation">
                    <div class="role-section-label"><span>من يؤدي الدور؟</span><span id="prisoner-performer-type">نموذج ذكاء اصطناعي</span></div>
                    <div id="prisoner-model-group">
                        <div class="setup-model-heading"><button type="button" id="selected-model-card" class="selected-model-card" onclick="App.openModelModal('prisoner')" title="تغيير نموذج المتهم"></button><a id="prisoner-share-link" hidden></a></div><div class="setup-model-meta" id="prisoner-setup-meta"></div>
                        <button type="button" id="prisoner-reasoning-btn" class="reasoning-control" onclick="App.openReasoningModal('prisoner')" aria-haspopup="dialog"></button>
                        <div id="model-status-tip" class="role-model-tip"></div>
                    </div>
                    <div id="prisoner-human-card" class="human-performer" hidden></div>
                </div>
            </article>

            <div class="role-swap-row">
                <button type="button" id="swap-roles-btn" class="role-swap-button" onclick="App.swapRoles()" aria-label="تبديل المتهم والمحقق" title="تبديل المتهم والمحقق">
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 6h2c6 0 10 12 16 12h2M2 18h2C10 18 14 6 20 6h2M18 2l4 4-4 4M18 14l4 4-4 4"/></svg>
                </button>
            </div>

            <article class="role-card role-card-interrogator" aria-labelledby="interrogator-role-heading">
                <header class="role-card-header"><h2 id="interrogator-role-heading"><span>02</span> المحقق</h2><span id="interrogator-representation-label" class="role-kind">أنت</span></header>
                <div class="role-character-section" id="interrogator-persona-group" hidden>
                    <div class="role-section-label"><span>شخصية المحقق ومستواه</span><span>ملف المحقق</span></div>
                    <button type="button" id="selected-investigator-persona" class="investigator-selected" onclick="App.openInvestigatorModal()"></button>
<div id="investigator-setup-tags" class="setup-character-tags"><a id="investigator-share-link" hidden></a></div>
                </div>
                <div id="human-investigator-character" class="human-character-note">أسلوبك، أسئلتك، وطريقتك في كشف الحقيقة.</div>
                <div class="role-representation">
                    <div class="role-section-label"><span>من يؤدي الدور؟</span><span id="interrogator-performer-type">المشارك البشري</span></div>
                    <div id="interrogator-model-group" hidden>
                        <div class="setup-model-heading"><button type="button" id="selected-interrogator-card" class="selected-model-card" onclick="App.openModelModal('interrogator')" title="تغيير نموذج المحقق"></button><a id="interrogator-share-link" hidden></a></div><div class="setup-model-meta" id="interrogator-setup-meta"></div>
                        <button type="button" id="interrogator-reasoning-btn" class="reasoning-control" onclick="App.openReasoningModal('interrogator')" aria-haspopup="dialog"></button>
                    </div>
                    <div id="interrogator-human-card" class="human-performer"></div>
                </div>
            </article>
            <div id="reverse-role-brief" class="reverse-role-brief" hidden>أنت تؤدي دور المتهم من القضية المختارة. اقرأ خلفيته وأسراره في ملف القضية، ثم أجب بكلماتك. المحقق يبدأ، وله 30 جولة كحد أقصى. توجيهات JEV مقترحات لتمثيل الدور وليست قياسًا لمشاعرك.</div>

            <button type="button" id="start-session-btn" class="btn btn-primary" style="margin-top:6px;">🚨 بدء جلسة استجواب جديدة</button>

            <hr style="border-color: var(--border); margin: 16px 0;">

            <!-- زري الأرشيف المطورين -->
            <div class="form-group">
                <label>📂 أرشيف وسجلات التحقيق:</label>
                <div class="archive-buttons-container">
                    <button type="button" class="btn-archive-action btn-archive-local" onclick="App.openArchiveModal('local')">
                        📋 سجلات التحقيق في الجلسة (<span id="archive-local-count">0</span>)
                    </button>
                    <button type="button" class="btn-archive-action btn-archive-public" onclick="App.openArchiveModal('public')">
                        🌐 سجلات التحقيق المنشورة (<span id="archive-public-count">0</span>)
                    </button>
                </div>
            </div>
        </div>
    </section>

    <!-- العمود الأوسط: غرفة الحوار والضغط -->
    <section class="panel">
        <div class="panel-header">
            <span>🎙️ غرفة التحقيق والتفاوض المباشر</span>
            <div style="display:flex; gap:8px; align-items:center;">
                <span id="active-session-id" style="font-size:0.76rem; color:var(--text-muted);">لم تبدأ الجلسة بعد</span>
                <button type="button" id="compact-btn" class="btn btn-secondary" style="padding:5px 10px; font-size:0.76rem;">ضغط السياق (Compaction)</button>
                <button type="button" id="end-session-btn" class="btn btn-danger" style="padding:5px 10px; font-size:0.76rem; background:#dc2626; border-color:#ef4444; color:#fff;" onclick="App.endAndEvaluateSession()">⏹️ إنهاء الجلسة والتقييم</button>
            </div>
        </div>

        <div id="chat-messages" class="chat-messages">
            <div style="text-align:center; color:var(--text-muted); margin-top:60px;">
                اختر الشخصية وملف الهوية من القائمة اليمنى ثم اضغط <strong>"بدء جلسة استجواب جديدة"</strong> أو اكتب رسالتك مباشرة للبدء.
            </div>
        </div>
        <button type="button" id="reverse-resume-btn" class="btn btn-secondary" onclick="App.requestReverseQuestion()" hidden>طلب سؤال المحقق / إعادة المحاولة ↻</button>

        <!-- شريط تحكم AI ضد AI -->
        <div id="ai-vs-ai-controls" class="ai-vs-ai-bar" style="display:none;">
            <div style="display:flex; justify-content:space-between; align-items:center; width:100%;">
                <div style="display:flex; align-items:center; gap:8px;">
                    <span class="ai-pulse-dot"></span>
                    <span style="font-size:0.86rem; font-weight:700; color:#38bdf8;">نظام AI ضد AI نشط:</span>
                    <span id="ai-turn-counter" style="font-size:0.8rem; color:#cbd5e1;">الجولة: 0 / 30</span>
                </div>
                <button type="button" id="resume-investigation-btn" class="btn btn-resume" onclick="App.resumeInvestigation()" hidden>إكمال التحقيق ↻</button>
            </div>
        </div>

        <div class="quick-tactics" id="human-tactics-bar">
            <span class="tactic-chip" data-prompt="لدينا تسجيلات صوتية وبصمات رقمية تثبت تورطك الكامل، الإنكار لن يفيدك الآن!">🔥 مواجهة بالأدلة</span>
            <span class="tactic-chip" data-prompt="إذا تعاونت معنا الآن وكشفت الحقيقة كاملة، أضمن لك تخفيف العقوبة وحماية عائلتك رسمياً.">🤝 عرض صفقة مشروطة</span>
            <span class="tactic-chip" data-prompt="شريكك في الخارج ألقى باللوم كله عليك، أنت هنا تدفع الثمن وحدك بينما هو حر طليق!">⚡ زرع الشك والضغط</span>
            <span class="tactic-chip" data-prompt="أمامك 5 دقائق فقط قبل تحويل ملفك للمحكمة العليا بتهمة القصوى وإغلاق باب التفاوض نهائياً.">⏱️ ضغط الوقت</span>
            <span class="tactic-chip" data-prompt="أنا أعلم أنك لم تكن تقصد إيذاء أحد وأن الظروف أجبرتك.. ساعدني لأساعدك على الخروج من هنا.">💬 استمالة إنسانية</span>
        </div>

        <button type="button" id="end-test-btn" class="end-test-button" onclick="App.endAndEvaluateSession()" disabled>إنهاء الاختبار وطلب التحكيم والعلامة ⏹</button>
        <div id="session-notice" class="session-notice" role="status" aria-live="polite"></div>
        <div id="jury-live-status" class="jury-live-status" hidden></div>
        <details id="mcp-session-panel" class="memory-panel mcp-session-panel" hidden><summary>بوابات MCP الخاصة بهذه الجلسة</summary><p>أضف رابط الدور إلى وكيلك كخادم MCP عبر HTTP. البوابة سرية وتسمح بتأدية هذا الدور فقط؛ لا تشاركها مع الجمهور. أبقِ هذه الصفحة مفتوحة لتشغيل JEV والتحكيم. لا يُرسل السياق كاملًا بعد المهمة الأولى، ولا يُضغط سياق طرف MCP؛ يحتفظ به الهارنس.</p><div id="mcp-gates"></div><p id="mcp-wait-state" role="status"></p><button id="mcp-pause-btn" type="button" class="btn btn-secondary" onclick="MCPUI.pauseRequested=true" hidden>إيقاف انتظار الوكيل</button></details>
        <details id="memory-notes" class="memory-panel" hidden></details>
        <div id="session-funding-label" class="usage-summary" role="status" hidden></div>
        <div id="usage-summary" class="usage-summary"></div>
        <div class="chat-input-bar" id="human-input-bar">
            <textarea id="user-input" placeholder="اكتب كلامك للسجين هنا (فاوضه، اضغط عليه، أو واجهه بالأدلة)..."></textarea>
            <button type="button" id="send-btn" class="btn btn-primary" style="width:auto; min-width:170px; height:54px;">إرسال وممارسة الضغط ⬅</button>
        </div>
    </section>

    <!-- العمود الأيسر: الكاميرا الحية ومؤشرات المشاعر والحالات الـ 20 -->
    <section class="panel">
        <div class="panel-header">
            <span>📹 المراقبة البصرية والقياسات النفسية</span>
        </div>
        <div class="panel-body">
            <div class="portrait-stage">
                <img id="prisoner-portrait" src="assets/images/char_1/calm.jpg" alt="حالة السجين">
                <div class="state-badge-overlay">
                    <span style="font-size:0.8rem; color:#cbd5e1;">الحالة الانفعالية:</span>
                    <span id="current-state-pill" class="state-badge-pill" style="background:#3b82f6;">هادئ (Calm)</span>
                </div>
            </div>

            <div class="sense-box">
                <div style="font-size:0.82rem; font-weight:700; margin-bottom:8px; color:#38bdf8;">
                    🧠 قراءة المشاعر الحية (JEV)
                </div>

                <div class="meter-row">
                    <div class="meter-label"><span>الخوف والرهبة</span><span id="val-fear">0%</span></div>
                    <div class="meter-bar"><div id="bar-fear" class="meter-fill" style="width:0%; background:#f97316;"></div></div>
                </div>

                <div class="meter-row">
                    <div class="meter-label"><span>الغضب والتمرد</span><span id="val-anger">0%</span></div>
                    <div class="meter-bar"><div id="bar-anger" class="meter-fill" style="width:0%; background:#ef4444;"></div></div>
                </div>

                <div class="meter-row">
                    <div class="meter-label"><span>الثقة والقابلية للتفاوض</span><span id="val-trust">0%</span></div>
                    <div class="meter-bar"><div id="bar-trust" class="meter-fill" style="width:0%; background:#10b981;"></div></div>
                </div>

                <div class="meter-row">
                    <div class="meter-label"><span>الإنهاك والانكسار النفسي</span><span id="val-collapse">0%</span></div>
                    <div class="meter-bar"><div id="bar-collapse" class="meter-fill" style="width:0%; background:#64748b;"></div></div>
                </div>

                <div class="meter-row">
                    <div class="meter-label"><span>مؤشر الاقتراب من نقطة الاعتراف</span><span id="val-breaking">0%</span></div>
                    <div class="meter-bar"><div id="bar-breaking" class="meter-fill" style="width:0%; background:#eab308;"></div></div>
                </div>

                <div id="sense-directive-text" style="font-size:0.75rem; color:#94a3b8; margin-top:8px; line-height:1.45;">
                    يحلل Jev نبرة الاستجواب مع كل رسالة ويوجّه تمثيل مشاعر الشخصية.
                </div>
            </div>

            <div id="states-gallery" hidden></div>
        </div>
    </section>
</main>
<footer class="lab-footer">        <div class="env-pills">
            <div class="env-pill">محرك المشاعر: <strong id="sense-model-pill">Jev</strong></div>
            <div class="env-pill">موديل الضغط: <strong id="compact-model-pill">...</strong></div>
            <div class="env-pill">النماذج: <strong id="models-count-pill">0</strong></div>
        </div>
<a href="https://odehit.com/" target="_blank" rel="noopener">تم تطوير المختبر بواسطة <b dir="ltr">OdehIt</b></a></footer>

<!-- Modal: Case Selector -->
<div id="case-modal" class="modal-overlay">
    <div class="modal-card modal-card-xl">
        <div class="modal-card-header">
            <div style="display:flex; align-items:center; gap:10px;">
                <span style="font-size:1.3rem;">📂</span>
                <div>
                    <h3 style="margin:0;">اختيار قضية وشخصية الاستجواب</h3>
                    <div style="font-size:0.78rem; color:var(--text-muted);">انقر على أي شخصية لمعاينة تفاصيل ملفها الكامل وسجل اتهامها على الجانب الأيسر</div>
                </div>
            </div>
            <button type="button" class="modal-close-btn" onclick="App.closeCaseModal()">✕</button>
        </div>
        <div class="case-modal-body">
            <!-- القائمة اليمينية: بطاقات الشخصيات الـ 16 -->
            <div class="case-list-column">
                <div class="case-filter-bar">
                    <input type="text" id="case-modal-search" placeholder="ابحث باسم المتهم، أو التهمة، أو الكلمة المفتاحية...">
                </div>
                <div id="case-cards-container" class="case-cards-grid">
                    <!-- يتم بناؤه بالجافاسكريبت -->
                </div>
            </div>

            <!-- العمود الجانبي: المعاينة المكبرة وتفاصيل القضية مع سلايدر السحب -->
            <div class="case-detail-column">
                <div class="case-detail-header">
                    <img id="modal-detail-portrait" src="assets/images/char_1/calm.jpg" alt="صورة المتهم">
                    <div class="case-detail-titles">
                        <h4 id="modal-detail-name">فيكتور فانس</h4>
                        <div id="modal-detail-sub" class="case-detail-sub">مدير العمليات السوداء السابق</div>
                        <div id="modal-detail-state-pill" class="state-badge-pill" style="margin-top:4px;">الحالة الابتدائية: هادئ</div>
                    </div>
                </div>
                <div class="case-detail-section-title">
                    <span>📄 ملف التحقيق وهوية المتهم بالكامل:</span>
                </div>
                <div id="modal-detail-content" class="case-detail-content-scroll">
                    <!-- نص ملف الماركداون كاملاً مع سلايدر عمودي -->
                </div>
                <button type="button" id="confirm-case-btn" class="btn btn-primary" onclick="App.confirmSelectedCase()" style="margin-top:12px;">
                    ✓ تأكيد واختيار هذه القضية لبدء الاستجواب
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
                    <h3 style="margin:0;">اختيار نموذج الذكاء الاصطناعي (OpenRouter)</h3>
                    <div id="model-modal-description" style="font-size:0.78rem; color:var(--text-muted);">اختر الموديل واختبر أداءه في دور المتهم</div>
                </div>
            </div>
            <button type="button" class="modal-close-btn" onclick="App.closeModelModal()">✕</button>
        </div>
        <div class="model-modal-toolbar">
            <button type="button" class="mcp-connect-option" onclick="MCPUI.configure()"><strong dir="ltr">MCP ↗</strong><span>استخدم وكيلك أو نموذجك المحلي</span><small>Claude Code · Codex · OpenCode · أي هارنس متوافق</small></button>
            <input type="text" id="model-modal-search" placeholder="ابحث باسم النموذج أو الشركة (DeepSeek, GPT, Claude, Gemini, Qwen, Mistral, Llama)...">
            <div class="model-tabs">
                <button type="button" class="model-tab active" data-tab="focus_economic" onclick="App.filterModelTab('focus_economic')">مختارة · اقتصادية (<span id="tab-count-focus_economic">0</span>)</button>
                <button type="button" class="model-tab" data-tab="focus_medium" onclick="App.filterModelTab('focus_medium')">مختارة · متوسطة (<span id="tab-count-focus_medium">0</span>)</button>
                <button type="button" class="model-tab" data-tab="focus_high" onclick="App.filterModelTab('focus_high')">مختارة · عليا (<span id="tab-count-focus_high">0</span>)</button>
                <button type="button" class="model-tab" data-tab="economic" onclick="App.filterModelTab('economic')">اقتصادية أخرى (<span id="tab-count-economic">0</span>)</button>
                <button type="button" class="model-tab" data-tab="professional" onclick="App.filterModelTab('professional')">احترافية (<span id="tab-count-professional">0</span>)</button>
                <button type="button" class="model-tab" data-tab="all" onclick="App.filterModelTab('all')">الكل (<span id="tab-count-all">0</span>)</button>
                <button type="button" class="model-tab" data-tab="free" onclick="App.filterModelTab('free')">مجانية (<span id="tab-count-free">0</span>)</button>
            </div>
            <div class="discovery-toolbar"><select id="model-sort" aria-label="ترتيب النماذج"><option value="recommended">الحداثة والشهرة والاستخدام</option><option value="newest">الأحدث أولًا</option><option value="popular">الأكثر استخدامًا أولًا</option><option value="reliable">الأكثر ثباتًا في قياسات المختبر</option></select></div>
            <details class="curated-note"><summary>عن الاختيارات والدرجات والألوان</summary>
            <p>موديل رسمي واحد لكل عائلة وفئة؛ الفئة موقع المنتج داخل العائلة وليست حدًا سعريًا. راجع السعر الفعلي والقفل قبل الاختيار. عينات الأداء محلية، وتحكيم اللجنة منفصل عن مراجعات المستخدمين.</p>
            <p>الأداء: أقل من 40 برتقالي، ومن 40 إلى أقل من 65 أصفر. الاتصال: فشل أقل من 10% أخضر، 10–أقل من 30% أصفر، و30% فأكثر برتقالي. يُعرض لون التنبيه الأشد مع بيان سببه؛ غير المقاس بلا لون.</p>
            <small id="ranking-source" class="ranking-source"></small>
            <small class="ranking-source">إحصاءات الاستدعاء محلية؛ نسبة الفشل من الطلبات المكتملة، وإلغاء الطلب بعد بلوغ النصاب لا يُحسب فشلًا. العينات القليلة ليست ضمانًا.</small>
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
                    <h3 id="archive-modal-title" style="margin:0;">سجلات التحقيق في الجلسة</h3>
                    <div id="archive-modal-sub" style="font-size:0.78rem; color:var(--text-muted);">قائمة المستجوبين ومن استجوبهم مع علامات التقدم والنتائج</div>
                </div>
            </div>
            <div style="display:flex; align-items:center; gap:10px;">
                <a href="public.php" target="_blank" class="btn btn-secondary" style="padding:4px 10px; font-size:0.78rem; text-decoration:none;">
                    🌐 فتح الصفحة العامة
                </a>
                <button type="button" class="modal-close-btn" onclick="App.closeArchiveModal()">✕</button>
            </div>
        </div>
        <div class="case-filter-bar" style="margin-top:12px; margin-bottom:10px;">
            <input type="text" id="archive-modal-search" placeholder="ابحث باسم المتهم، أو المحقق، أو القضية...">
        </div>
        <div id="archive-modal-grid" class="archive-cards-grid">
            <!-- يتم بناؤه بالجافاسكريبت كعرض منتجات شبكي -->
        </div>
    </div>
</div>

<!-- Modal: User OpenRouter Key -->
<div id="key-modal" class="modal-overlay">
    <div class="modal-card">
        <h3>🔑 استخدام مفتاح OpenRouter الخاص بك</h3>
        <div class="modal-note">
            ⚠️ <strong>تنبيه هام:</strong> هذا الإعداد مخصص لمفاتيح <strong>OpenRouter API فقط</strong> (التي تبدأ عادة بـ <code>sk-or-v1-...</code>).<br>
            يُحفظ المفتاح داخل جلسة متصفحك الحالية فقط (Session Storage)، ولا يتم تخزينه نهائياً على خوادمنا لحماية خصوصيتك ورصيدك.
        </div>
        <div class="form-group">
            <label for="custom-api-key-input">أدخل مفتاح OpenRouter API:</label>
            <input type="password" id="custom-api-key-input" placeholder="sk-or-v1-...">
        </div>
        <div class="modal-buttons">
            <button type="button" class="btn btn-secondary" onclick="App.clearUserKey()">إزالة المفتاح</button>
            <button type="button" class="btn btn-secondary" onclick="App.closeKeyModal()">إلغاء</button>
            <button type="button" class="btn btn-primary" style="width:auto;" onclick="App.saveUserKey()">حفظ وتفعيل المفتاح</button>
        </div>
    </div>
</div>

<div id="investigator-profile-modal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="investigator-modal-title">
    <div class="modal-content investigator-modal">
        <div class="modal-header"><div><h2 id="investigator-modal-title">اختر من يحقق معك</h2><p>10 شخصيات · من المبتدئ إلى الخبير · اختر الشخصية والنموذج معًا</p></div><button type="button" class="modal-close-btn" onclick="document.getElementById('investigator-profile-modal').classList.remove('active')" aria-label="إغلاق">×</button></div>
        <div id="investigator-profile-grid" class="investigator-grid"></div>
    </div>
</div>
<div id="reasoning-modal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="reasoning-title">
    <section class="reasoning-dialog"><header><h3 id="reasoning-title">مستوى التفكير</h3><button type="button" class="text-button" onclick="App.closeReasoningModal()" aria-label="إغلاق">×</button></header><p id="reasoning-model-name" dir="ltr"></p><div id="reasoning-options"></div><p class="reasoning-help">يطبق على التجربة التالية فقط. مستوى الشخصية وتعليماتها مستقلان عن ميزانية التفكير.</p></section>
</div>
<div id="mcp-config-modal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="mcp-role-title"><section class="reasoning-dialog mcp-config-dialog"><header><h2 id="mcp-role-title">ربط وكيل MCP</h2><button type="button" class="text-button" aria-label="إغلاق إعداد MCP" onclick="document.getElementById('mcp-config-modal').classList.remove('active')">×</button></header><p>الهارنس يتولى تشغيل النموذج وذاكرته. المختبر يرسل المهمة ويقيّم الرد؛ لا يتحكم في إعداد التفكير الفعلي خارج الموقع.</p><form id="mcp-config-form"><label for="mcp-name">اسم النموذج / الوكيل</label><input id="mcp-name" required minlength="2" maxlength="100" placeholder="اسم النموذج وإصداره" dir="auto"><label for="mcp-effort">مستوى التفكير الفعلي في الهارنس</label><select id="mcp-effort"><option value="unknown">غير معلن</option><option value="none">بلا تفكير</option><option value="minimal">أدنى</option><option value="low">قليل</option><option value="medium">متوسط</option><option value="high">مرتفع</option><option value="xhigh">مرتفع جدًا</option><option value="max">أقصى</option></select><p>تحصل على رابط الدور بعد بدء التجربة. الاسم والتفكير غير موثقين، وتظهر النتائج تحت فئة MCP مستقلة. تكلفة النموذج الخارجي غير معروفة؛ تبقى تكلفة JEV والتحكيم على جهة التمويل المحددة.</p><button type="submit" class="btn btn-primary">اعتماد وكيل MCP لهذا الدور</button></form></section></div>
<script src="assets/session-view.js?v=<?= (int)filemtime(__DIR__ . "/assets/session-view.js") ?>"></script>
<script src="assets/community.js?v=<?= (int)filemtime(__DIR__ . '/assets/community.js') ?>"></script>
<script src="assets/lab-cards.js?v=<?= (int)filemtime(__DIR__.'/assets/lab-cards.js') ?>"></script>
<script src="assets/app.js?v=<?= (int)filemtime(__DIR__ . "/assets/app.js") ?>"></script>
<script src="assets/mcp.js?v=<?= (int)filemtime(__DIR__.'/assets/mcp.js') ?>"></script>
</body>
</html>
