<?php
declare(strict_types=1);

/** Private encrypted vault. Never return vault records or key fingerprints to HTTP clients. */
function labSponsorPath():string {return $GLOBALS['lab_storage'].'/sponsor-vault.json';}
function labSponsorLocked(callable $fn,bool $write=false):mixed {
    $lock=fopen(labSponsorPath().'.lock','c');if(!$lock || !flock($lock,$write?LOCK_EX:LOCK_SH))throw new RuntimeException('تعذر فتح سجل الرعاة');
    try{$v=is_file(labSponsorPath())?labRead(labSponsorPath()):['version'=>1,'sponsors'=>[]];return $fn($v);}
    finally{flock($lock,LOCK_UN);fclose($lock);}
}
function labSponsorWrite(array $v):void {
    $mask=umask(0077);try{labWrite(labSponsorPath(),$v);chmod(labSponsorPath(),0600);}finally{umask($mask);}
}
function labSponsorCipherKey(bool $create=false):string {
    $p=$GLOBALS['lab_storage'].'/sponsor-vault.key';
    if(!is_file($p) && $create){$mask=umask(0077);try{$h=fopen($p,'x');if(!$h)throw new RuntimeException('تعذر تجهيز حماية المفاتيح');fwrite($h,random_bytes(32));fclose($h);chmod($p,0600);}finally{umask($mask);}}
    $key=is_file($p)?file_get_contents($p):'';if(strlen($key)!==32)throw new RuntimeException('تعذر فتح حماية مفاتيح الرعاة');return $key;
}
function labSponsorEncrypt(string $key):array {
    $iv=random_bytes(12);$tag='';$data=openssl_encrypt($key,'aes-256-gcm',labSponsorCipherKey(true),OPENSSL_RAW_DATA,$iv,$tag,'PrisonLab sponsors v1');
    if($data===false)throw new RuntimeException('تعذر حماية المفتاح');
    return ['iv'=>base64_encode($iv),'tag'=>base64_encode($tag),'data'=>base64_encode($data)];
}
function labSponsorDecrypt(array $r):string {
    $c=$r['secret'];$key=openssl_decrypt(base64_decode($c['data'],true),'aes-256-gcm',labSponsorCipherKey(),OPENSSL_RAW_DATA,base64_decode($c['iv'],true),base64_decode($c['tag'],true),'PrisonLab sponsors v1');
    if(!is_string($key) || $key==='')throw new RuntimeException('تعذر فتح مفتاح الراعي');return $key;
}
function labSponsorDay():string {return (new DateTimeImmutable('now',new DateTimeZone('Asia/Jerusalem')))->format('Y-m-d');}
function labSponsorPublic(array $r,array $v,?array $a):array {
    $path=$a?$GLOBALS['lab_storage'].'/quota_'.$a['id'].'.json':'';
    $quota=$path!=='' && is_file($path)?labRead($path):[];
    $used=count($quota['sponsor_usage'][labSponsorDay()][$r['id']] ?? []);
    return array_intersect_key($r,array_flip(['id','label','profile','daily_limit','price_cap','active','is_site','revision']))+
        ['used'=>$used,'remaining'=>max(0,$r['daily_limit']-$used),'is_owner'=>$a && hash_equals($r['owner_id'],$a['id']),
        'quota_day'=>labSponsorDay(),'timezone'=>'Asia/Jerusalem'];
}
function labSponsors(?array $a):array {
    return labSponsorLocked(function($v)use($a){$out=[];foreach($v['sponsors'] as $r)if($r['active'] || ($a && hash_equals($r['owner_id'],$a['id'])))$out[]=labSponsorPublic($r,$v,$a);return $out;});
}
function labDefaultSponsor():string {return labSponsorLocked(function($v){foreach($v['sponsors'] as $r)if(!empty($r['is_site']))return $r['id'];return '';});}

function labSaveSponsor(array $a,array $body,bool $site=false):array {
    $id=(string)($body['id'] ?? '');$label=trim((string)($body['label'] ?? $a['display_name']));
    $limit=filter_var($body['daily_limit'] ?? null,FILTER_VALIDATE_INT);$price=$body['price_cap'] ?? null;
    if(mb_strlen($label)<2 || mb_strlen($label)>100 || $limit===false || $limit<1 || $limit>1000 || !is_numeric($price) || $price<0 || $price>1000 || !is_bool($body['active'] ?? null))throw new RuntimeException('أدخل اسم الراعي وحدًا يوميًا من 1 إلى 1000 وسعرًا من صفر إلى 1000');
    $key=trim((string)($body['key'] ?? ''));if($key!=='')labVisitorBalance($key);
    return labSponsorLocked(function($v)use($a,$body,$id,$label,$limit,$price,$key,$site){
        $r=$id!==''?($v['sponsors'][$id] ?? null):null;
        if($id!=='' && (!$r || !hash_equals($r['owner_id'],$a['id'])))throw new RuntimeException('لا تملك هذا المفتاح');
        if($r && (int)($body['revision'] ?? 0)!==$r['revision'])throw new RuntimeException('تغيرت إعدادات الراعي؛ حدّث القائمة قبل الحفظ');
        if(!$r && count(array_filter($v['sponsors'],fn($x)=>$x['owner_id']===$a['id']))>=5)throw new RuntimeException('يمكن للحساب إدارة خمسة مفاتيح راعية كحد أقصى');
        if(!$r && $key==='')throw new RuntimeException('أدخل مفتاح OpenRouter لتسجيل الرعاية');
        if($key!=='')foreach($v['sponsors'] as $other)if($other['id']!==$id && hash_equals($other['fingerprint'],hash('sha256',$key)))throw new RuntimeException('هذا المفتاح مسجل بالفعل؛ عدّل سجله الحالي');
        if(!$r)$r=['id'=>'sp_'.bin2hex(random_bytes(12)),'owner_id'=>$a['id'],'is_site'=>$site,'created_at'=>gmdate('c'),'revision'=>0];
        if($site && array_filter($v['sponsors'],fn($x)=>!empty($x['is_site'])))throw new RuntimeException('مفتاح الموقع مسجل بالفعل');
        if($key!==''){$r['secret']=labSponsorEncrypt($key);$r['fingerprint']=hash('sha256',$key);}
        $r['profile']=labAccountProfile($a);$r['label']=$label;$r['daily_limit']=$limit;$r['price_cap']=(float)$price;$r['active']=$body['active'];$r['revision']++;$r['updated_at']=gmdate('c');
        $v['sponsors'][$r['id']]=$r;if($site)$v['site_initialized']=true;labSponsorWrite($v);return labSponsorPublic($r,$v,$a);
    },true);
}
function labDeleteSponsor(array $a,string $id):void {
    labSponsorLocked(function($v)use($a,$id){$r=$v['sponsors'][$id] ?? null;if(!$r || !hash_equals($r['owner_id'],$a['id']))throw new RuntimeException('لا تملك هذا المفتاح');unset($v['sponsors'][$id]);labSponsorWrite($v);},true);
}

/** Resolve only the selected or session-bound donor; never fall through to a different key. */
function labFunding(string $id,string $personal,array $env):array {
    if($id==='' && $personal!=='')return ['key'=>$personal,'source'=>'personal','cap'=>null,'sponsor_id'=>''];
    if($id==='')$id=labDefaultSponsor();
    if($id==='' && labSponsorLocked(fn($v)=>!empty($v['site_initialized'])))throw new RuntimeException('مفتاح الموقع متوقف أو محذوف؛ اختر راعيا آخر أو أضف مفتاحك الشخصي');
    if($id==='')return ['key'=>$env['OPENROUTER_API_KEY'] ?? '','source'=>'site','cap'=>1.,'sponsor_id'=>''];
    return labSponsorLocked(function($v)use($id){$r=$v['sponsors'][$id] ?? null;if(!$r || !$r['active'])throw new RuntimeException('هذا الراعي غير متاح أو أوقف مفتاحه؛ اختر تمويلًا آخر لتجربة جديدة');
        return ['key'=>labSponsorDecrypt($r),'source'=>$r['is_site']?'site':'sponsor','cap'=>$r['price_cap'],'sponsor_id'=>$r['id'],'revision'=>$r['revision'],'profile'=>$r['profile'],'label'=>$r['label']];});
}
function labUseFunding(array $f):void {$GLOBALS['lab_user_key']=$f['source']==='personal';$GLOBALS['lab_actor_price_cap']=$f['cap'] ?? 1.;$GLOBALS['lab_funding_sponsor_id']=$f['sponsor_id'];$GLOBALS['lab_funding_revision']=$f['revision'] ?? 0;}

/** Called under the account-start lock. Reservations survive deletion, rotation and limit edits. */
function labReserveSponsor(array $a,array &$s,string $requestId,array $funding,array &$quota):void {
    labSponsorLocked(function($v)use($a,&$s,$requestId,$funding,&$quota){
        $r=$v['sponsors'][$funding['sponsor_id']] ?? null;
        if(!$r || !$r['active'] || $r['revision']!==$funding['revision'])throw new RuntimeException('تغير مفتاح الراعي أو قيوده؛ حدّث القائمة وأعد المحاولة');
        $day=labSponsorDay();$prior=$quota['sponsor_usage'][$day][$r['id']] ?? [];
        if(isset($prior[$requestId]))throw new RuntimeException('هذا الطلب محجوز بالفعل؛ افتح سجلك أو ابدأ طلبًا جديدًا');
        if(count($prior)>=$r['daily_limit'])throw new RuntimeException('شكرًا لمساهمتك! اكتملت حصتك لدى هذا الراعي اليوم. يمكنك اختيار راعٍ آخر أو استخدام مفتاحك أو العودة غدًا.');
        $s['funding_source']=$funding['source'];$s['sponsor_id']=$r['id'];$s['sponsor_profile']=$r['profile'];$s['sponsor_label']=$r['label'];$s['sponsor_revision_at_start']=$r['revision'];$s['actor_price_cap_at_start']=$r['price_cap'];
        $quota['sponsor_usage'][$day][$r['id']][$requestId]=$s['session_id'];
    });
}

function labGuardFunding():void {
    $id=$GLOBALS['lab_funding_sponsor_id'] ?? '';if($id==='')return;
    labSponsorLocked(function($v)use($id){$r=$v['sponsors'][$id] ?? null;if(!$r || !$r['active'] || $r['revision']!==($GLOBALS['lab_funding_revision'] ?? 0))throw new RuntimeException('تغير مفتاح الراعي أو توقف؛ المراحل المكتملة محفوظة. حدّث الجلسة قبل المتابعة.');});
}

function labSponsorSiteQuota(?array $a):?array {
    return labSponsorLocked(function($v)use($a){foreach($v['sponsors'] as $r)if($r['is_site'])return labSponsorPublic($r,$v,$a);return null;});
}
