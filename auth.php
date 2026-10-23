<?php
declare(strict_types=1);
require_once __DIR__.'/lib/lab.php';
require_once __DIR__.'/lib/accounts.php';
header('Cache-Control: no-store');header('Referrer-Policy: no-referrer');header('X-Content-Type-Options: nosniff');
ini_set('display_errors','0');
set_time_limit(65);
try {
    $env=labAccountBootstrap();labBrowserSession();$action=$_GET['action'] ?? 'login';
    $config=labOAuthConfig($env);
    if($action==='login'){$url=labOAuthBegin($config);session_write_close();header('Location: '.$url, true,302);exit;}
    if($action==='callback'){labOAuthFinish($_GET,$config);session_write_close();header('Location: '.labBaseUrl($env).'/?signed_in=1',true,303);exit;}
    http_response_code(404);$error='صفحة غير موجودة';
}catch(Throwable $e){http_response_code(400);$error=$e instanceof RuntimeException?$e->getMessage():'تعذر إكمال تسجيل الدخول؛ حاول مجددًا';}
if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
?><!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>تسجيل الدخول | PrisonLab</title><link rel="stylesheet" href="assets/style.css"></head><body><main style="max-width:640px;margin:80px auto;padding:24px"><h1>تسجيل الدخول إلى PrisonLab</h1><p><?= htmlspecialchars($error,ENT_QUOTES,'UTF-8') ?></p><p><a href="auth.php?action=login">إعادة المحاولة عبر LinkedIn</a> · <a href="index.php">العودة إلى المختبر</a> · <a href="privacy.php">الخصوصية</a></p></main></body></html>
