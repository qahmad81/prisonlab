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
    <h1>سياسة الخصوصية</h1>
    <p class="privacy-meta">آخر تحديث: 23 أكتوبر 2026</p>
    <p>PrisonLab مختبر مجتمعي لمقارنة أداء نماذج الذكاء الاصطناعي من خلال قضايا وشخصيات خيالية. تشرح هذه الصفحة البيانات المستخدمة في التجارب، وما يظهر للزوار، ودور الخدمات الخارجية.</p>
    <div class="privacy-note"><strong>حالة ربط الحسابات:</strong> تسجيل الدخول عبر LinkedIn والملفات الشخصية وحدود الاستخدام المرتبطة بالحساب قيد الإعداد حاليًا. لا تجمع النسخة الحالية بيانات حساب LinkedIn. سنحدّث هذه الصفحة عند تفعيل الربط وأدوات إدارة الحساب والحذف.</div>

    <h2>بيانات التجربة</h2>
    <p>يحفظ المختبر نص المحادثة، واختيارات النماذج والشخصيات، والانفعالات المحاكية، وتقارير التحكيم، والمراجعات التي تكتبها، ومعلومات الاستخدام والتكلفة التي يعيدها المزود. تستخدم هذه البيانات لاستكمال الجلسة وتقييم أداء النماذج. الانفعالات تخص الشخصية الخيالية ولا تمثل تشخيصًا نفسيًا للمستخدم.</p>
    <p>تعتمد ملكية السجلات الخاصة في النسخة الحالية على ملف جلسة آمن في المتصفح؛ فقدانه قد يمنع استعادة الوصول إليها. لا يوجد حاليًا نظام استرجاع حساب عبر LinkedIn.</p>

    <h2>المحادثات الخاصة والمنشورة</h2>
    <p>لا تظهر المحادثة في المعرض العام إلا عندما تختار نشرها بعد اكتمال تقييمها. عند النشر يصبح نصها ونتائجها والانفعالات ومراجعاتها وبيانات الاستخدام المتاحة قابلة للعرض عبر رابط عام. راجع المحتوى قبل النشر، وتجنب إدخال معلومات شخصية أو أسرار أو بيانات تخص أشخاصًا آخرين في التجارب.</p>
    <p>عند تفعيل الحسابات، سيعرض الملف العام المحادثات التي يختار صاحب الحساب نشرها فقط، مع اسمه وصورته بصفته مشاركًا أو مشغلًا للمحاكاة. لن ينشر المختبر شيئًا على حساب LinkedIn نفسه.</p>

    <h2>تسجيل الدخول عبر LinkedIn عند تفعيله</h2>
    <p>سيطلب الربط صلاحيات تسجيل الدخول والملف الأساسي فقط (<span dir="ltr">openid profile</span>): معرّف الحساب لدى التطبيق، والاسم، والصورة عند توفرها. لن نطلب كلمة مرور LinkedIn، أو البريد الإلكتروني، أو قائمة العلاقات، أو صلاحية النشر. تسجيل الدخول بحساب LinkedIn لا يعني التحقق من الهوية الحقيقية للشخص.</p>
    <p>الغرض من هذه البيانات هو ربط السجلات بالحساب، وتطبيق الحد اليومي للتجارب الممولة من الموقع، وإظهار صاحب المحادثات المنشورة. سيُتاح الاطلاع على بيانات الحساب وسياسة حذفها قبل إطلاق هذه الميزات.</p>

    <h2>خدمات الذكاء الاصطناعي والمفاتيح</h2>
    <p>ترسل تعليمات القضية والشخصيات ومحتوى المحادثة اللازم إلى OpenRouter ومزودي النماذج المختارة، بما في ذلك نماذج المشاعر والتحكيم وضغط السياق. تطبق هذه الجهات سياساتها الخاصة لمعالجة البيانات واحتفاظها بها. لا ترسل بيانات تسجيل الدخول إلى نماذج المحادثة.</p>
    <p>إذا أدخلت مفتاح OpenRouter الخاص بك، يحفظه المتصفح في <span dir="ltr">sessionStorage</span> ويرسله إلى خادم المختبر لتنفيذ الطلبات. لا يُحفظ المفتاح في ملفات المحادثات ولا يُعرض للزوار. مفتاح الموقع يبقى على الخادم. لا ترسل مفاتيحك في نص المحادثة أو في مستودع الكود أو في طلب دعم عام.</p>
    <p><a href="https://openrouter.ai/privacy" rel="noopener noreferrer">سياسة خصوصية OpenRouter</a> · <a href="https://www.linkedin.com/legal/privacy-policy" rel="noopener noreferrer">سياسة خصوصية LinkedIn</a></p>

    <h2>القياسات وملفات الارتباط</h2>
    <p>نسجل نجاح استدعاءات النماذج وفشلها ووقت الاستجابة ومعدل التوليد لتوضيح موثوقيتها. لا تعني هذه القياسات ضمان استجابة النموذج مستقبلًا. يستخدم الموقع ملف ارتباط للجلسة وحماية الطلبات، وتستخدم واجهته خطوط Google Fonts التي قد يتصل بها المتصفح. قد تحتفظ استضافة الموقع بسجلات تشغيل وأمان مثل وقت الطلب وعنوان الشبكة.</p>

    <h2>الحفظ والحذف</h2>
    <p>تُحفظ بيانات التطبيق في مساحة خاصة على الخادم، خارج مجلد الموقع العام ومستودع الكود. لا تتوفر في النسخة الحالية خدمة ذاتية لحذف الحساب أو المحادثة؛ أدوات الحذف الكامل للمحادثات الخاصة والمنشورة جزء من تحديث الحسابات الجاري. لا نعد بحذف نسخ تحتفظ بها خدمات خارجية أو زوار بعد نشر المحتوى علنًا.</p>
    <p>لا تُضمّن المحادثات أو مفاتيح API في مستودع المشروع المفتوح. ترخيص الكود لا يحوّل بيانات المستخدمين إلى بيانات مفتوحة المصدر.</p>

    <h2>الأسئلة والتحديثات</h2>
    <p>للاستفسارات وطلبات الخصوصية، تواصل مع مشغل الموقع عبر <a href="https://odehit.com/#contact" rel="noopener noreferrer">صفحة اتصل بنا لدى Odeh IT</a>. تُعلن التغييرات المؤثرة في جمع البيانات أو نشرها داخل الموقع قبل تفعيلها. لا ترسل كلمات مرور أو مفاتيح API ضمن طلب التواصل.</p>
    <p class="privacy-meta">PrisonLab مشروع مستقل، ولا يمثل LinkedIn أو شركات النماذج المستخدمة.</p>
</main>
</body>
</html>
