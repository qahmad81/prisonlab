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
    if($action==='callback'){
        $result=labOAuthFinish($_GET,$config);
        session_write_close();labSetLocaleCookie($result['locale']);
        header('Location: '.labBaseUrl($env).labPageUrl('home',['signed_in'=>1],$result['locale']),true,303);exit;
    }
    http_response_code(404);$error=labT('auth.auth.1');
}catch(Throwable $e){http_response_code(400);$error=$e instanceof RuntimeException?$e->getMessage():labT('auth.auth.2');}
if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
?><!doctype html><html <?= labLocaleAttributes() ?>><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= labE('auth.html.1') ?></title><link rel="stylesheet" href="/assets/style.css"></head><body><main style="max-width:640px;margin:80px auto;padding:24px"><h1><?= labE('auth.html.2') ?></h1><p><?= htmlspecialchars($error,ENT_QUOTES,'UTF-8') ?></p><p><a href="<?= labLoginHref() ?>"><?= labE('auth.html.3') ?></a> · <a href="<?= labPageHref('home') ?>"><?= labE('auth.html.4') ?></a> · <a href="<?= labPageHref('privacy') ?>"><?= labE('auth.html.5') ?></a></p></main></body></html>
