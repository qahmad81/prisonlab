<?php
declare(strict_types=1);

/** Account data and OAuth endpoints are server controlled, never supplied by HTTP clients. */
function labAccountBootstrap(): array {
    $env=array_replace(labEnv(dirname(__DIR__).'/.env'),$GLOBALS['lab_test_env'] ?? []);
    $storage=getenv('LAB_DATA_DIR') ?: ($env['LAB_DATA_DIR'] ?? dirname(__DIR__,2).'/.ai_prison_game-data');
    if(!is_dir($storage) && !mkdir($storage,0700,true))throw new RuntimeException('تعذر فتح مساحة البيانات الخاصة');
    $resolved=str_replace('\\','/',realpath($storage));$root=str_replace('\\','/',realpath(dirname(__DIR__)));
    if(str_starts_with(strtolower($resolved.'/'),strtolower($root.'/')))throw new RuntimeException('مساحة البيانات يجب أن تكون خارج الموقع');
    $GLOBALS['lab_storage']=$storage;
    if(!is_dir($storage.'/accounts'))mkdir($storage.'/accounts',0700,true);
    return $env;
}
function labBrowserSession():void {
    if(session_status()===PHP_SESSION_ACTIVE)return;
    ini_set('session.use_strict_mode','1');ini_set('session.use_only_cookies','1');
    session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax','path'=>'/',
        'secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off']);
    session_start();$_SESSION['csrf'] ??= bin2hex(random_bytes(24));
    if(isset($GLOBALS['lab_test_account_bootstrap']))($GLOBALS['lab_test_account_bootstrap'])();
}
function labCurrentAccount():?array {
    $id=$_SESSION['account_id'] ?? '';
    if(!is_string($id) || !preg_match('/^[a-f0-9]{64}$/D',$id))return null;
    $file=$GLOBALS['lab_storage'].'/accounts/'.$id.'.json';
    return is_file($file)?labRead($file):null;
}
function labAccountOwner(?array $account):string {return $account?'account_'.$account['id']:'';}
function labAccountProfile(array $a):array {
    return ['provider'=>'linkedin','public_id'=>$a['public_id'],'display_name'=>$a['display_name'],'avatar_url'=>$a['avatar_url'] ?? ''];
}
function labAccountUpsert(array $info,string $clientId):array {
    if(!is_string($info['sub'] ?? null) || $info['sub']==='' || strlen($info['sub'])>255
        || !is_string($info['name'] ?? null) || trim($info['name'])==='')throw new RuntimeException('لم يرسل LinkedIn بيانات الحساب الأساسية');
    $id=hash('sha256',$clientId."\0".$info['sub']);$path=$GLOBALS['lab_storage'].'/accounts/'.$id.'.json';
    $lock=fopen($path.'.lock','c');if(!$lock || !flock($lock,LOCK_EX))throw new RuntimeException('تعذر حفظ الحساب');
    try {
        $a=is_file($path)?labRead($path):['id'=>$id,'public_id'=>bin2hex(random_bytes(16)),'created_at'=>gmdate('c')];
        $a['display_name']=mb_substr(trim($info['name']),0,160);
        $avatar=$info['picture'] ?? '';
        $a['avatar_url']=is_string($avatar) && strlen($avatar)<2048 && parse_url($avatar,PHP_URL_SCHEME)==='https'?$avatar:'';
        $a['updated_at']=gmdate('c');labWrite($path,$a);return $a;
    }finally{flock($lock,LOCK_UN);fclose($lock);}
}
function labBaseUrl(array $env):string {
    $url=rtrim($env['APP_BASE_URL'] ?? '','/');$parts=parse_url($url);
    if(!$parts || empty($parts['host']) || isset($parts['query']) || isset($parts['fragment']) || isset($parts['user']) || isset($parts['pass'])
        || (($parts['scheme'] ?? '')!=='https' && !(($parts['scheme'] ?? '')==='http' && in_array($parts['host'],['127.0.0.1','localhost'],true))))
        throw new RuntimeException('عنوان الموقع غير مضبوط لدى المشغل');
    return $url;
}
function labOAuthConfig(array $env):array {
    $id=$env['LINKEDIN_CLIENT_ID'] ?? '';$secret=$env['LINKEDIN_CLIENT_SECRET'] ?? '';
    if($id==='' || $secret==='')throw new RuntimeException('ربط LinkedIn غير جاهز بعد؛ يرجى المحاولة لاحقًا');
    $base=labBaseUrl($env);$redirect=$env['LINKEDIN_REDIRECT_URI'] ?? $base.'/auth.php?action=callback';
    if($redirect!==$base.'/auth.php?action=callback')throw new RuntimeException('إعداد عنوان العودة غير صالح');
    return ['id'=>$id,'secret'=>$secret,'redirect'=>$redirect];
}
/** Fixed destinations and TLS verification. Bodies, tokens and transport errors never reach visitors/logs. */
function labIdentityHttp(string $url,array $headers=[],?array $form=null):array {
    if(isset($GLOBALS['lab_identity_http']))return ($GLOBALS['lab_identity_http'])($url,$headers,$form);
    if(!in_array($url,['https://www.linkedin.com/oauth/v2/accessToken','https://api.linkedin.com/v2/userinfo',
        'https://openrouter.ai/api/v1/key','https://openrouter.ai/api/v1/credits'],true))throw new RuntimeException('وجهة اتصال غير مسموحة');
    $ch=curl_init($url);$opts=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>$headers,CURLOPT_CONNECTTIMEOUT=>10,
        CURLOPT_TIMEOUT=>25,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_FOLLOWLOCATION=>false];
    if($form!==null){$opts[CURLOPT_POST]=true;$opts[CURLOPT_POSTFIELDS]=http_build_query($form);}
    curl_setopt_array($ch,$opts);$raw=curl_exec($ch);$status=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
    if(!is_string($raw) || $status!==200 || strlen($raw)>262144)throw new RuntimeException('تعذر التحقق من الخدمة الخارجية؛ حاول لاحقًا');
    $data=json_decode($raw,true);if(!is_array($data) || isset($data['error']))throw new RuntimeException('تعذر التحقق من الخدمة الخارجية');return $data;
}
function labOAuthBegin(array $config):string {
    // Reissue the cookie as Lax even for returning visitors with the old Strict cookie.
    // Merely changing session_set_cookie_params does not replace an existing browser cookie.
    session_regenerate_id(true);
    $state=bin2hex(random_bytes(32));
    $_SESSION['linkedin_oauth']=['state_hash'=>hash('sha256',$state),'expires'=>time()+600];
    return 'https://www.linkedin.com/oauth/v2/authorization?'.http_build_query(['response_type'=>'code','client_id'=>$config['id'],
        'redirect_uri'=>$config['redirect'],'state'=>$state,'scope'=>'openid profile']);
}
function labOAuthFinish(array $query,array $config):array {
    $saved=$_SESSION['linkedin_oauth'] ?? [];unset($_SESSION['linkedin_oauth']);
    if(!is_string($query['state'] ?? null) || !isset($saved['state_hash'],$saved['expires']) || $saved['expires']<time()
        || !hash_equals($saved['state_hash'],hash('sha256',$query['state'])))throw new RuntimeException('انتهت صلاحية محاولة الدخول أو لم تبدأ من هذا المتصفح؛ حاول مجددًا');
    if(isset($query['error']) || !is_string($query['code'] ?? null) || $query['code']==='' || strlen($query['code'])>4096)
        throw new RuntimeException('لم يكتمل تفويض LinkedIn؛ يمكنك إعادة المحاولة');
    session_write_close(); // Do not block other tabs behind slow provider HTTP calls.
    $token=labIdentityHttp('https://www.linkedin.com/oauth/v2/accessToken',['Content-Type: application/x-www-form-urlencoded'],
        ['grant_type'=>'authorization_code','code'=>$query['code'],'redirect_uri'=>$config['redirect'],'client_id'=>$config['id'],'client_secret'=>$config['secret']]);
    if(!is_string($token['access_token'] ?? null) || $token['access_token']==='')throw new RuntimeException('تعذر تأكيد تسجيل الدخول');
    // Authentication comes only from LinkedIn's TLS-authenticated userinfo endpoint.
    // We neither decode nor trust unsigned ID-token claims; the access token is discarded.
    $info=labIdentityHttp('https://api.linkedin.com/v2/userinfo',['Authorization: Bearer '.$token['access_token']]);unset($token);
    $account=labAccountUpsert($info,$config['id']);labBrowserSession();session_regenerate_id(true);
    $_SESSION=['account_id'=>$account['id'],'csrf'=>bin2hex(random_bytes(24))];return $account;
}
function labQuota(?array $a):array {
    $now=new DateTimeImmutable('now',new DateTimeZone('Asia/Jerusalem'));$day=$now->format('Y-m-d');$used=0;
    if($a){$path=$GLOBALS['lab_storage'].'/quota_'.$a['id'].'.json';if(is_file($path)){$q=labRead($path);if(($q['day'] ?? '')===$day)$used=(int)($q['used'] ?? count($q['session_ids'] ?? []));}}
    return ['day'=>$day,'timezone'=>'Asia/Jerusalem','limit'=>3,'used'=>$used,'remaining'=>max(0,3-$used),
        'resets_at'=>$now->modify('tomorrow')->setTime(0,0)->format(DateTimeInterface::ATOM)];
}
/** Serialize starts for one account. Keep reservations after deletion to prevent quota evasion. */
function labAccountStart(array $account,array $s,string $requestId,string $userKey):array {
    if(!preg_match('/^[a-zA-Z0-9_-]{8,100}$/D',$requestId))throw new RuntimeException('معرّف إنشاء الجلسة مفقود');
    $file=$GLOBALS['lab_storage'].'/quota_'.$account['id'].'.json';$lock=fopen($file.'.lock','c');
    if(!$lock || !flock($lock,LOCK_EX))throw new RuntimeException('تعذر حجز التجربة');
    try {
        $q=is_file($file)?labRead($file):[];$quota=labQuota($account);
        if(($q['day'] ?? '')!==$quota['day'])$q=['day'=>$quota['day'],'used'=>0,'requests'=>[]];
        if(isset($q['requests'][$requestId])) {
            $path=$GLOBALS['lab_storage'].'/'.$q['requests'][$requestId].'.json';
            if(!is_file($path))throw new RuntimeException('هذه الجلسة حُذفت؛ ابدأ طلبًا جديدًا');return labRead($path);
        }
        if($userKey==='' && (int)($q['used'] ?? 0)>=3)throw new RuntimeException('شكرًا لمساهمتك في المختبر! ميزانية الموقع محدودة، وقد استخدمت تجاربك الثلاث اليوم. عد غدًا لتجارب جديدة، أو أضف مفتاح OpenRouter الخاص بك لاستخدام غير محدود على نفقتك.');
        if($userKey!=='')labVisitorBalance($userKey);
        $s['funding_source']=$userKey!==''?'personal':'site';
        if($userKey!=='')$s['key_fingerprint']=hash('sha256',$userKey);
        $s['operator_profile']=labAccountProfile($account);
        $q['requests'][$requestId]=$s['session_id'];if($userKey==='')$q['used']=1+(int)($q['used'] ?? 0);
        // Reserve before creating the session: a crash may consume a slot, never create a free extra session.
        labWrite($file,$q);labWrite($GLOBALS['lab_storage'].'/'.$s['session_id'].'.json',$s);return $s;
    }finally{flock($lock,LOCK_UN);fclose($lock);}
}
function labVisitorBalance(string $key):array {
    if($key==='' || strlen($key)>512 || preg_match('/[\r\n]/',$key))throw new RuntimeException('أدخل مفتاح OpenRouter صالحًا');
    $raw=labIdentityHttp('https://openrouter.ai/api/v1/key',['Authorization: Bearer '.$key]);$data=$raw['data'] ?? [];
    if(!is_array($data) || !array_key_exists('usage',$data))throw new RuntimeException('تعذر التحقق من المفتاح');
    $out=['usage'=>is_numeric($data['usage'])?(float)$data['usage']:null,
        'key_limit'=>isset($data['limit']) && is_numeric($data['limit'])?(float)$data['limit']:null,
        'key_remaining'=>isset($data['limit_remaining']) && is_numeric($data['limit_remaining'])?(float)$data['limit_remaining']:null,
        'balance'=>null,'balance_available'=>false];
    try {$credits=labIdentityHttp('https://openrouter.ai/api/v1/credits',['Authorization: Bearer '.$key])['data'] ?? [];
        if(is_numeric($credits['total_credits'] ?? null) && is_numeric($credits['total_usage'] ?? null)) {
            $out['balance']=max(0,(float)$credits['total_credits']-(float)$credits['total_usage']);$out['balance_available']=true;
        }
    }catch(RuntimeException $e){/* Some keys cannot read account credit totals. Never invent a balance. */}
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
/** Leaderboards compare the same model, role, case, character levels and rubric. */
function labRankings(array $s,?array $published=null):array {
    if(empty($s['evaluation']) || ($s['evaluation_status'] ?? '')!=='complete')return [];
    $out=[];$published ??= labPublicSessions();
    foreach(labActorModels($s) as $role=>$model){
        $score=$s['evaluation'][$role==='prisoner'?'prisoner_score':'investigator_score'] ?? null;if(!is_numeric($score))continue;
        $level=$s[$role==='prisoner'?'prisoner_character':'interrogator_character']['level'] ?? null;$above=0;$total=0;
        foreach($published as $p){if($p['session_id']===$s['session_id'])continue;
            if((labActorModels($p)[$role] ?? '')!==$model || ($p['case_file'] ?? '')!==($s['case_file'] ?? '')
                || ($p['evaluation']['rubric_version'] ?? '')!==($s['evaluation']['rubric_version'] ?? '')
                || ($p['prisoner_character']['level'] ?? null)!==($s['prisoner_character']['level'] ?? null)
                || ($p['interrogator_character']['level'] ?? null)!==($s['interrogator_character']['level'] ?? null))continue;
            $other=$p['evaluation'][$role==='prisoner'?'prisoner_score':'investigator_score'] ?? null;
            if(is_numeric($other)){$total++;if($other>$score)$above++;}
        }
        $out[$role]=['model'=>$model,'score'=>$score,'rank'=>$above+1,'total'=>$total+1,'level'=>$level,
            'provisional'=>empty($s['is_published']),'ties'=>'shared','basis'=>'same_model_role_case_both_levels_rubric'];
    }return $out;
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
function labForgetStartReference(array $account,string $id):void {
    $file=$GLOBALS['lab_storage'].'/quota_'.$account['id'].'.json';$lock=fopen($file.'.lock','c');
    if(!$lock || !flock($lock,LOCK_EX))throw new RuntimeException('تعذر تنظيف مرجع الجلسة');
    try{if(is_file($file)){$q=labRead($file);$q['requests']=array_filter($q['requests'] ?? [],fn($sid)=>$sid!==$id);labWrite($file,$q);}}
    finally{flock($lock,LOCK_UN);fclose($lock);}
}
