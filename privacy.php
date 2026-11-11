<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/i18n.php';
labBeginPage('privacy');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
?>
<!doctype html>
<html <?= labLocaleAttributes() ?>>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="index,follow">
    <title><?= labE('privacy.html.1') ?></title>
    <link rel="icon" href="/favicon.ico?v=2" sizes="any">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg?v=2">
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/branding/prisonlab-32.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/branding/prisonlab-180.png">
    <link rel="stylesheet" href="/assets/style.css?v=<?= (int)filemtime(__DIR__ . '/assets/style.css') ?>">
    <style>
        body{display:block;min-height:100vh;height:auto;overflow:auto;background:#0b1120;color:#e2e8f0}
        .privacy-wrap{max-width:820px;margin:auto;padding:36px 24px 72px;font-family:Tahoma,Arial,sans-serif;line-height:1.95}
        .privacy-nav{display:flex;justify-content:space-between;gap:18px;align-items:center;border-bottom:1px solid #334155;padding-bottom:20px;margin-bottom:36px}
        .privacy-nav a,.privacy-wrap a{color:#7dd3fc}.privacy-brand{font-weight:800;font-size:24px;color:#f8fafc;direction:ltr}
        .privacy-wrap h1{font-size:32px;color:#f8fafc;line-height:1.4;margin:0 0 12px}.privacy-wrap h2{font-size:20px;margin:32px 0 10px;color:#f8fafc}
        .privacy-wrap p{margin:10px 0}.privacy-meta{color:#94a3b8;font-size:14px}.privacy-note{border-right:3px solid #fbbf24;padding:12px 18px;background:#172032;margin:24px 0}
        .privacy-wrap li{margin-bottom:8px}.privacy-wrap ul{padding-inline-start:24px}
        @media(max-width:560px){.privacy-wrap{padding:24px 18px 50px}.privacy-wrap h1{font-size:27px}.privacy-nav{flex-wrap:wrap}}
    </style>
</head>
<body>
<main class="privacy-wrap">
    <nav class="privacy-nav" aria-label="<?= labE('privacy.html.2') ?>">
        <div class="brand-title-row"><span class="privacy-brand"><img src="/assets/branding/prisonlab-128.png" width="44" height="44" alt="" style="vertical-align:middle;border-radius:10px;margin-inline-end:8px">PrisonLab</span><?php labLanguageMenu(); ?></div>
        <div><a href="<?= labPageHref('home') ?>"><?= labE('privacy.html.3') ?></a> · <a href="<?= labPageHref('public') ?>"><?= labE('privacy.html.4') ?></a> · <a class="repo-link" href="https://github.com/qahmad81/prisonlab" target="_blank" rel="noopener noreferrer" aria-label="<?= labE('repository.link') ?>" title="<?= labE('repository.link') ?>"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="m8 7-5 5 5 5m8-10 5 5-5 5m-3-14-2 18"/></svg><span dir="ltr">GitHub</span></a></div>
    </nav>
    <h1><?= labE('privacy.html.5') ?></h1>
    <p class="privacy-meta"><?= labE('privacy.html.6') ?></p>
    <p><?= labE('privacy.html.7') ?></p>
    <div class="privacy-note"><strong><?= labE('privacy.html.8') ?></strong> <?= labE('privacy.html.9') ?></div>

    <h2><?= labE('privacy.html.10') ?></h2>
    <p><?= labE('privacy.html.11') ?></p>
    <p><?= labE('privacy.html.12') ?></p>

    <h2><?= labE('privacy.html.13') ?></h2>
    <p><?= labE('privacy.html.14') ?></p>
    <p><?= labE('privacy.html.15') ?></p>

    <h2><?= labE('privacy.html.16') ?></h2>
    <p><?= labE('privacy.html.17') ?><span dir="ltr">openid profile</span><?= labE('privacy.html.18') ?></p>
    <p><?= labE('privacy.html.19') ?></p>

    <h2><?= labE('privacy.html.20') ?></h2>
    <p><?= labE('privacy.html.21') ?></p>

    <h2><?= labE('privacy.html.22') ?></h2>
    <p><?= labE('privacy.html.23') ?></p>
    <p><?= labE('privacy.html.24') ?></p>

    <h2><?= labE('privacy.html.25') ?></h2>
    <p><?= labE('privacy.html.26') ?></p>
    <p><?= labE('privacy.html.27') ?> <span dir="ltr">sessionStorage</span> <?= labE('privacy.html.28') ?></p>
    <p><a href="https://openrouter.ai/privacy" rel="noopener noreferrer"><?= labE('privacy.html.29') ?></a> · <a href="https://www.linkedin.com/legal/privacy-policy" rel="noopener noreferrer"><?= labE('privacy.html.30') ?></a></p>

    <h2><?= labE('privacy.html.31') ?></h2>
    <p><?= labE('privacy.html.32') ?></p>
    <p><?= labE('privacy.html.33') ?></p>
    <p><?= labE('privacy.html.34') ?></p>
    <p><?= labE('privacy.html.35') ?></p>

    <h2><?= labE('privacy.html.36') ?></h2>
    <p><?= labE('privacy.html.37') ?></p>

    <h2><?= labE('privacy.html.38') ?></h2>
    <p><?= labE('privacy.html.39') ?></p>
    <p><?= labE('privacy.html.40') ?></p>
    <p><?= labE('privacy.html.41') ?></p>

    <h2><?= labE('privacy.html.42') ?></h2>
    <p><?= labE('privacy.html.43') ?> <a href="https://odehit.com/#contact" rel="noopener noreferrer"><?= labE('privacy.html.44') ?></a><?= labE('privacy.html.45') ?></p>
    <p class="privacy-meta"><?= labE('privacy.html.46') ?></p>
</main>
</body>
</html>
