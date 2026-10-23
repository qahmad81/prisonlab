<?php
declare(strict_types=1);
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="index,follow">
    <title>سياسة الخصوصية | PrisonLab</title>
    <link rel="icon" href="favicon.ico?v=2" sizes="any">
    <link rel="icon" type="image/svg+xml" href="favicon.svg?v=2">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/branding/prisonlab-32.png">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/branding/prisonlab-180.png">
    <link rel="stylesheet" href="assets/style.css?v=<?= (int)filemtime(__DIR__ . '/assets/style.css') ?>">
    <style>
        body{display:block;min-height:100vh;height:auto;overflow:auto;background:#0b1120;color:#e2e8f0}
        .privacy-wrap{max-width:820px;margin:auto;padding:36px 24px 72px;font-family:Tahoma,Arial,sans-serif;line-height:1.95}
        .privacy-nav{display:flex;justify-content:space-between;gap:18px;align-items:center;border-bottom:1px solid #334155;padding-bottom:20px;margin-bottom:36px}
        .privacy-nav a,.privacy-wrap a{color:#7dd3fc}.privacy-brand{font-weight:800;font-size:24px;color:#f8fafc;direction:ltr}
        .privacy-wrap h1{font-size:32px;color:#f8fafc;line-height:1.4;margin:0 0 12px}.privacy-wrap h2{font-size:20px;margin:32px 0 10px;color:#f8fafc}
        .privacy-wrap p{margin:10px 0}.privacy-meta{color:#94a3b8;font-size:14px}.privacy-note{border-right:3px solid #fbbf24;padding:12px 18px;background:#172032;margin:24px 0}
        .privacy-wrap li{margin-bottom:8px}.privacy-wrap ul{padding-right:24px}
        @media(max-width:560px){.privacy-wrap{padding:24px 18px 50px}.privacy-wrap h1{font-size:27px}.privacy-nav{flex-wrap:wrap}}
    </style>
</head>
<body>
<main class="privacy-wrap">
    <nav class="privacy-nav" aria-label="روابط الموقع"><span class="privacy-brand"><img src="assets/branding/prisonlab-128.png" width="44" height="44" alt="" style="vertical-align:middle;border-radius:10px;margin-inline-end:8px">PrisonLab</span><div><a href="index.php">المختبر</a> · <a href="public.php">المحادثات المنشورة</a></div></nav>
    <h1>الخصوصية في النسخة المستقلة</h1>
    <p class="privacy-meta">آخر تحديث: 23 أكتوبر 2026 · فرع main</p>
    <div class="privacy-note">لا تتطلب هذه النسخة تسجيل الدخول. محادثاتك خاصة افتراضيًا، ولا تظهر للزوار إلا إذا نشرتها بنفسك.</div>
    <h2>السجل وملفات المتصفح</h2>
    <p>يحفظ المشغّل المحادثات وتوجيهات الشخصيات والانفعالات وتقارير التحكيم والتكاليف والمراجعات في مساحة خاصة على الخادم. ترتبط سجلاتك بمعرّف عشوائي في ملف تعريف ارتباط HttpOnly، مع جلسة مستقلة لحماية الطلبات من التزوير. لا تنشر هذا المعرّف أو تشاركه. حذف ملفات المتصفح قد يفقدك الوصول إلى الأرشيف الخاص؛ لا يوجد حساب لاستعادته من متصفح آخر.</p>
    <h2>مفتاحك والتكلفة</h2>
    <p>يمكنك استخدام مفتاح OpenRouter الخاص بك، ويُحفظ في Session Storage لهذا المتصفح ويُرسل إلى خادم المختبر ثم OpenRouter لتنفيذ طلباتك. لا يُحفظ المفتاح الخام في سجل المحادثة؛ تحفظ بصمة لربط التجربة بمصدر تمويلها. لا يوجد حد يومي للتجارب، لكن رصيد المفتاح وحدود مزوده تبقى نافذة. تعرض التكاليف التي أبلغ عنها المزود؛ قد تكون غير مكتملة إذا لم يرسل تكلفة بعض الطلبات.</p>
    <h2>ما يصل إلى النماذج</h2>
    <p>تُرسل معلومات القضية والدور والسياق المنطوق إلى النماذج عبر OpenRouter. لا يرى أي طرف ملاحظات التفكير التمثيلية الخاصة بالطرف الآخر. لا تدخل أسرارًا أو بيانات شخصية حساسة في المحاكاة. راجع <a href="https://openrouter.ai/privacy" rel="noopener noreferrer">سياسة خصوصية OpenRouter</a> وسياسات مزودي النماذج.</p>
    <h2>النشر والحذف</h2>
    <p>يمكنك نشر محادثة مكتملة التقييم في المعرض العام. عندها يراها الزوار مع نتائجها ومراجعاتها وتكلفتها، وتصبح للقراءة فقط. يمكنك حذف محادثاتك الخاصة أو المنشورة نهائيًا من المختبر مع مراجعاتها وسجلها. لا يشمل الحذف نسخًا حفظها زوار أو مزودو النماذج وفق سياساتهم. لا تُنسخ المحادثات ضمن نسخ نشر الكود الاحتياطية.</p>
    <h2>التواصل</h2>
    <p>تواصل مع مشغّل نسختك لطلبات الخصوصية. للاستفسار عن المشروع الأصلي: <a href="https://odehit.com/#contact" rel="noopener noreferrer">اتصل بـ Odeh IT</a>. لا ترسل كلمات مرور أو مفاتيح API ضمن الرسائل.</p>
</main>
</body>
</html>
