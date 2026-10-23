<?php
declare(strict_types=1);

/** Standalone identity is an unguessable browser capability, not an external account. */
function labRuntimeBootstrap():array {
    $env=array_replace(labEnv(dirname(__DIR__).'/.env'),$GLOBALS['lab_test_env'] ?? []);
    $storage=getenv('LAB_DATA_DIR') ?: ($env['LAB_DATA_DIR'] ?? dirname(__DIR__,2).'/.ai_prison_game-data');
    if(!is_dir($storage) && !mkdir($storage,0700,true))throw new RuntimeException('تعذر فتح مساحة البيانات الخاصة');
    $resolved=str_replace('\\','/',realpath($storage));$root=str_replace('\\','/',realpath(dirname(__DIR__)));
    if(str_starts_with(strtolower($resolved.'/'),strtolower($root.'/')))throw new RuntimeException('مساحة البيانات يجب أن تكون خارج الموقع');
    $GLOBALS['lab_storage']=$storage;return $env;
}
function labBrowserSession():void {
    if(session_status()===PHP_SESSION_ACTIVE)return;
    ini_set('session.use_strict_mode','1');ini_set('session.use_only_cookies','1');
    $cookie=['httponly'=>true,'samesite'=>'Lax','path'=>'/','secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off'];
    session_set_cookie_params($cookie);session_start();$_SESSION['csrf'] ??= bin2hex(random_bytes(24));
    $owner=$_COOKIE['prisonlab_local'] ?? '';
    if(!is_string($owner) || !preg_match('/^[a-f0-9]{64}$/D',$owner)){
        $owner=bin2hex(random_bytes(32));setcookie('prisonlab_local',$owner,$cookie+['expires'=>time()+31536000]);
    }
    $_SESSION['browser_owner']=$owner;
}
function labBrowserIdentity():array {return ['id'=>$_SESSION['browser_owner'],'display_name'=>'أنت'];}
function labBrowserOwner(?array $a):string {return $a?'browser_'.$a['id']:'';}
function labBrowserProfile(array $a):array {return ['provider'=>'local','display_name'=>'أنت · المشارك البشري'];}
function labKeyHttp(string $url,array $headers=[]):array {
    if(isset($GLOBALS['lab_key_http']))return ($GLOBALS['lab_key_http'])($url,$headers);
    if(!in_array($url,['https://openrouter.ai/api/v1/key','https://openrouter.ai/api/v1/credits'],true))throw new RuntimeException('وجهة اتصال غير مسموحة');
    $ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>$headers,CURLOPT_CONNECTTIMEOUT=>10,
        CURLOPT_TIMEOUT=>25,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_FOLLOWLOCATION=>false]);
    $raw=curl_exec($ch);$status=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
    if(!is_string($raw) || $status!==200 || strlen($raw)>262144)throw new RuntimeException('تعذر التحقق من الخدمة الخارجية؛ حاول لاحقًا');
    $data=json_decode($raw,true);if(!is_array($data) || isset($data['error']))throw new RuntimeException('تعذر التحقق من الخدمة الخارجية');return $data;
}
/** Serialize duplicate starts, with no daily test quota. */
function labBrowserStart(array $a,array $s,string $requestId,string $userKey):array {
    if(!preg_match('/^[a-zA-Z0-9_-]{8,100}$/D',$requestId))throw new RuntimeException('معرّف إنشاء الجلسة مفقود');
    $file=$GLOBALS['lab_storage'].'/starts_'.$a['id'].'.json';$lock=fopen($file.'.lock','c');
    if(!$lock || !flock($lock,LOCK_EX))throw new RuntimeException('تعذر حفظ التجربة');
    try{
        $starts=is_file($file)?labRead($file):[];
        if(isset($starts[$requestId])){$path=$GLOBALS['lab_storage'].'/'.$starts[$requestId].'.json';
            if(!is_file($path))throw new RuntimeException('هذه الجلسة حُذفت؛ ابدأ طلبًا جديدًا');return labRead($path);}
        if($userKey!=='')labVisitorBalance($userKey);
        $s['funding_source']=$userKey!==''?'personal':'site';
        if($userKey!=='')$s['key_fingerprint']=hash('sha256',$userKey);
        $starts[$requestId]=$s['session_id'];labWrite($file,$starts);labWrite($GLOBALS['lab_storage'].'/'.$s['session_id'].'.json',$s);return $s;
    }finally{flock($lock,LOCK_UN);fclose($lock);}
}

function labBaseUrl(array $env):string {
    $url=rtrim($env['APP_BASE_URL'] ?? '','/');$parts=parse_url($url);
    if(!$parts || empty($parts['host']) || isset($parts['query']) || isset($parts['fragment']) || isset($parts['user']) || isset($parts['pass'])
        || (($parts['scheme'] ?? '')!=='https' && !(($parts['scheme'] ?? '')==='http' && in_array($parts['host'],['127.0.0.1','localhost'],true))))
        throw new RuntimeException('عنوان الموقع غير مضبوط لدى المشغل');
    return $url;
}

function labVisitorBalance(string $key):array {
    if($key==='' || strlen($key)>512 || preg_match('/[\r\n]/',$key))throw new RuntimeException('أدخل مفتاح OpenRouter صالحًا');
    $raw=labKeyHttp('https://openrouter.ai/api/v1/key',['Authorization: Bearer '.$key]);$data=$raw['data'] ?? [];
    if(!is_array($data) || !array_key_exists('usage',$data))throw new RuntimeException('تعذر التحقق من المفتاح');
    $out=['usage'=>is_numeric($data['usage'])?(float)$data['usage']:null,
        'key_limit'=>isset($data['limit']) && is_numeric($data['limit'])?(float)$data['limit']:null,
        'key_remaining'=>isset($data['limit_remaining']) && is_numeric($data['limit_remaining'])?(float)$data['limit_remaining']:null,
        'balance'=>null,'balance_available'=>false];
    try {$credits=labKeyHttp('https://openrouter.ai/api/v1/credits',['Authorization: Bearer '.$key])['data'] ?? [];
        if(is_numeric($credits['total_credits'] ?? null) && is_numeric($credits['total_usage'] ?? null)) {
            $out['balance']=max(0,(float)$credits['total_credits']-(float)$credits['total_usage']);$out['balance_available']=true;
        }
    }catch(RuntimeException $e){/* Some keys cannot read account credit totals. Never invent a balance. */}
    // A key cap (including effective guardrail budgets) takes precedence over account credits.
    $limits=[];
    if($out['key_remaining']!==null)$limits[]=$out['key_remaining'];
    elseif($out['key_limit']!==null && $out['usage']!==null)$limits[]=max(0,$out['key_limit']-$out['usage']);
    if(is_numeric($data['effective_budget']['remaining_usd'] ?? null))$limits[]=(float)$data['effective_budget']['remaining_usd'];
    foreach($data['budgets'] ?? [] as $budget)if(is_numeric($budget['remaining_usd'] ?? null))$limits[]=(float)$budget['remaining_usd'];
    if($out['balance_available'])$limits[]=$out['balance'];
    $out['available_to_spend']=$limits?max(0,min($limits)):null;
    return $out;
}

function labSessionCost(array $s):array {
    $total=0.;$reported=0;$calls=count($s['usage'] ?? []);
    foreach($s['usage'] ?? [] as $u)if(is_numeric($u['usage']['cost'] ?? null)){$total+=(float)$u['usage']['cost'];$reported++;}
    return ['total_usd'=>round($total,8),'reported_calls'=>$reported,'recorded_calls'=>$calls,'complete'=>$reported===$calls,
        'funding_source'=>$s['funding_source'] ?? 'legacy','scope'=>'provider_reported'];
}

function labPublicSessions():array {
    $out=[];foreach(glob($GLOBALS['lab_storage'].'/session_*.json') as $file){$s=labReadSessionIfPresent($file);if(!$s)continue;
        if(!empty($s['is_published']) && !empty($s['evaluation']) && ($s['evaluation_status'] ?? '')==='complete')$out[]=$s;}
    return $out;
}

function labReadSessionIfPresent(string $file):?array {
    try{return labRead($file);}catch(Throwable $e){if(!is_file($file))return null;throw $e;}
}

function labPublicBenchmarkData(string $owner=''):array {
    $ids=array_fill_keys(array_column(labPublicSessions(),'session_id'),true);
    if($owner!=='')foreach(glob($GLOBALS['lab_storage'].'/session_*.json') as $file){$s=labReadSessionIfPresent($file);if($s && ($s['owner_id'] ?? '')===$owner)$ids[$s['session_id']]=true;}
    return array_filter(labBenchmarkData(),fn($e)=>isset($ids[$e['session_id']]));
}

function labBenchmarkDelete(string $id):void {
    $file=$GLOBALS['lab_storage'].'/benchmark.json';$lock=fopen($file.'.lock','c');
    if(!$lock || !flock($lock,LOCK_EX))throw new RuntimeException('تعذر حذف مراجعات الجلسة');
    try{$data=labBenchmarkData();$data=array_filter($data,fn($e)=>($e['session_id'] ?? '')!==$id);labWrite($file,$data);}
    finally{flock($lock,LOCK_UN);fclose($lock);}
}

function labForgetStartReference(array $a,string $id):void {
    $file=$GLOBALS['lab_storage'].'/starts_'.$a['id'].'.json';$lock=fopen($file.'.lock','c');
    if(!$lock || !flock($lock,LOCK_EX))throw new RuntimeException('تعذر تنظيف مرجع الجلسة');
    try{if(is_file($file)){ $starts=labRead($file);$starts=array_filter($starts,fn($sid)=>$sid!==$id);labWrite($file,$starts);}}
    finally{flock($lock,LOCK_UN);fclose($lock);}
}
