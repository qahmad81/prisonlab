<?php
declare(strict_types=1);

/** Private encrypted vault. Never return vault records or key fingerprints to HTTP clients. */
function labSponsorPath():string {return $GLOBALS['lab_storage'].'/sponsor-vault.json';}
function labSponsorLocked(callable $fn,bool $write=false):mixed {
    $lock=fopen(labSponsorPath().'.lock','c');if(!$lock || !flock($lock,$write?LOCK_EX:LOCK_SH))throw new RuntimeException(labT('sponsors.SponsorLocked.1'));
    try{$v=is_file(labSponsorPath())?labRead(labSponsorPath()):['version'=>1,'sponsors'=>[]];return $fn($v);}
    finally{flock($lock,LOCK_UN);fclose($lock);}
}
function labSponsorWrite(array $v):void {
    $mask=umask(0077);try{labWrite(labSponsorPath(),$v);chmod(labSponsorPath(),0600);}finally{umask($mask);}
}
function labSponsorCipherKey(bool $create=false):string {
    $p=$GLOBALS['lab_storage'].'/sponsor-vault.key';
    if(!is_file($p) && $create){$mask=umask(0077);try{$h=fopen($p,'x');if(!$h)throw new RuntimeException(labT('sponsors.SponsorCipherKey.1'));fwrite($h,random_bytes(32));fclose($h);chmod($p,0600);}finally{umask($mask);}}
    $key=is_file($p)?file_get_contents($p):'';if(strlen($key)!==32)throw new RuntimeException(labT('sponsors.SponsorCipherKey.2'));return $key;
}
function labSponsorEncrypt(string $key):array {
    $iv=random_bytes(12);$tag='';$data=openssl_encrypt($key,'aes-256-gcm',labSponsorCipherKey(true),OPENSSL_RAW_DATA,$iv,$tag,'PrisonLab sponsors v1');
    if($data===false)throw new RuntimeException(labT('sponsors.SponsorEncrypt.1'));
    return ['iv'=>base64_encode($iv),'tag'=>base64_encode($tag),'data'=>base64_encode($data)];
}
function labSponsorDecrypt(array $r):string {
    $c=$r['secret'];$key=openssl_decrypt(base64_decode($c['data'],true),'aes-256-gcm',labSponsorCipherKey(),OPENSSL_RAW_DATA,base64_decode($c['iv'],true),base64_decode($c['tag'],true),'PrisonLab sponsors v1');
    if(!is_string($key) || $key==='')throw new RuntimeException(labT('sponsors.SponsorDecrypt.1'));return $key;
}
function labSponsorDay():string {return (new DateTimeImmutable('now',new DateTimeZone('Asia/Jerusalem')))->format('Y-m-d');}
function labSponsorPublic(array $r,array $v,?array $a):array {
    $path=$a?$GLOBALS['lab_storage'].'/quota_'.$a['id'].'.json':'';
    $quota=$path!=='' && is_file($path)?labRead($path):[];
    $used=count($quota['sponsor_usage'][labSponsorDay()][$r['id']] ?? []);
    $owner=$a && hash_equals($r['owner_id'],$a['id']);
    $public=array_intersect_key($r,array_flip(['id','label','profile','daily_limit','price_cap','active','is_site','revision']))+
        ['used'=>$used,'remaining'=>max(0,$r['daily_limit']-$used),'is_owner'=>$owner,
        'quota_day'=>labSponsorDay(),'timezone'=>'Asia/Jerusalem'];
    if($owner){$public['budget_daily_usd']=$r['budget_daily_usd'] ?? null;$public['budget_total_usd']=$r['budget_total_usd'] ?? null;}
    return $public;
}
function labSponsors(?array $a):array {
    return labSponsorLocked(function($v)use($a){$out=[];foreach($v['sponsors'] as $r)if($r['active'] || ($a && hash_equals($r['owner_id'],$a['id'])))$out[]=labSponsorPublic($r,$v,$a);return $out;});
}
function labDefaultSponsor():string {return labSponsorLocked(function($v){foreach($v['sponsors'] as $r)if(!empty($r['is_site']))return $r['id'];return '';});}

/** Optional soft spend thresholds apply to the OpenRouter key's reported usage in USD. */
function labSponsorBudgetValue(mixed $value):?float {
    if($value===null || $value==='')return null;
    if(!is_numeric($value) || !is_finite((float)$value) || (float)$value<0 || (float)$value>1000000)
        throw new RuntimeException(labT('sponsors.SponsorBudgetValue.1'));
    return (float)$value;
}
function labSaveSponsor(array $a,array $body,bool $site=false):array {
    $id=(string)($body['id'] ?? '');$label=trim((string)($body['label'] ?? $a['display_name']));
    $limit=filter_var($body['daily_limit'] ?? null,FILTER_VALIDATE_INT);$price=$body['price_cap'] ?? null;
    if(mb_strlen($label)<2 || mb_strlen($label)>100 || $limit===false || $limit<1 || $limit>1000 || !is_numeric($price) || $price<0 || $price>1000 || !is_bool($body['active'] ?? null))throw new RuntimeException(labT('sponsors.SaveSponsor.1'));
    $budgets=[];foreach(['budget_daily_usd','budget_total_usd'] as $field)if(array_key_exists($field,$body))$budgets[$field]=labSponsorBudgetValue($body[$field]);
    $key=trim((string)($body['key'] ?? ''));if($key!=='')labVisitorBalance($key);
    return labSponsorLocked(function($v)use($a,$body,$id,$label,$limit,$price,$key,$site,$budgets){
        $r=$id!==''?($v['sponsors'][$id] ?? null):null;
        if($id!=='' && (!$r || !hash_equals($r['owner_id'],$a['id'])))throw new RuntimeException(labT('sponsors.SaveSponsor.2'));
        if($r && (int)($body['revision'] ?? 0)!==$r['revision'])throw new RuntimeException(labT('sponsors.SaveSponsor.3'));
        if(!$r && count(array_filter($v['sponsors'],fn($x)=>$x['owner_id']===$a['id']))>=5)throw new RuntimeException(labT('sponsors.SaveSponsor.4'));
        if(!$r && $key==='')throw new RuntimeException(labT('sponsors.SaveSponsor.5'));
        if($key!=='')foreach($v['sponsors'] as $other)if($other['id']!==$id && hash_equals($other['fingerprint'],hash('sha256',$key)))throw new RuntimeException(labT('sponsors.SaveSponsor.6'));
        if(!$r)$r=['id'=>'sp_'.bin2hex(random_bytes(12)),'owner_id'=>$a['id'],'is_site'=>$site,'created_at'=>gmdate('c'),'revision'=>0];
        if($site && array_filter($v['sponsors'],fn($x)=>!empty($x['is_site'])))throw new RuntimeException(labT('sponsors.SaveSponsor.7'));
        if($key!==''){$r['secret']=labSponsorEncrypt($key);$r['fingerprint']=hash('sha256',$key);}
        foreach($budgets as $field=>$value)$r[$field]=$value;
        $r['profile']=labAccountProfile($a);$r['label']=$label;$r['daily_limit']=$limit;$r['price_cap']=(float)$price;$r['active']=$body['active'];$r['revision']++;$r['updated_at']=gmdate('c');
        $v['sponsors'][$r['id']]=$r;if($site)$v['site_initialized']=true;labSponsorWrite($v);return labSponsorPublic($r,$v,$a);
    },true);
}
function labDeleteSponsor(array $a,string $id):void {
    labSponsorLocked(function($v)use($a,$id){$r=$v['sponsors'][$id] ?? null;if(!$r || !hash_equals($r['owner_id'],$a['id']))throw new RuntimeException(labT('sponsors.DeleteSponsor.1'));unset($v['sponsors'][$id]);labSponsorWrite($v);},true);
}

/** Resolve only the selected or session-bound donor; never fall through to a different key. */
function labFunding(string $id,string $personal,array $env):array {
    if($id==='' && $personal!=='')return ['key'=>$personal,'source'=>'personal','cap'=>null,'sponsor_id'=>''];
    if($id==='')$id=labDefaultSponsor();
    if($id==='' && labSponsorLocked(fn($v)=>!empty($v['site_initialized'])))throw new RuntimeException(labT('sponsors.Funding.1'));
    if($id==='')return ['key'=>$env['OPENROUTER_API_KEY'] ?? '','source'=>'site','cap'=>1.,'sponsor_id'=>''];
    return labSponsorLocked(function($v)use($id){$r=$v['sponsors'][$id] ?? null;if(!$r || !$r['active'])throw new RuntimeException(labT('sponsors.Funding.2'));
        return ['key'=>labSponsorDecrypt($r),'source'=>$r['is_site']?'site':'sponsor','cap'=>$r['price_cap'],'sponsor_id'=>$r['id'],'revision'=>$r['revision'],'profile'=>$r['profile'],'label'=>$r['label']];});
}
/** An operator explicitly selects one existing, active vault sponsor by ID. */
function labFreeSponsorInfo(array $env,?array $account):?array {
    $id=trim((string)(getenv('OPENROUTER_FREE_SPONSOR_ID') ?: ($env['OPENROUTER_FREE_SPONSOR_ID'] ?? '')));
    if(!preg_match('/^sp_[a-f0-9]{24}$/D',$id))return null;
    return labSponsorLocked(function($v)use($id,$account){$r=$v['sponsors'][$id] ?? null;
        return $r && !empty($r['active'])?labSponsorPublic($r,$v,$account):null;});
}
function labBindFreeFunding(array $funding):void {
    $GLOBALS['lab_free_actor_key']=$funding['key'];
    $GLOBALS['lab_free_sponsor_id']=$funding['sponsor_id'];
    $GLOBALS['lab_free_sponsor_revision']=$funding['revision'];
}
function labUseFunding(array $f):void {$GLOBALS['lab_funding_key']=$f['key'];$GLOBALS['lab_user_key']=$f['source']==='personal';$GLOBALS['lab_actor_price_cap']=$f['cap'] ?? 1.;$GLOBALS['lab_funding_sponsor_id']=$f['sponsor_id'];$GLOBALS['lab_funding_revision']=$f['revision'] ?? 0;}

/** Called under the account-start lock. Reservations survive deletion, rotation and limit edits. */
function labReserveSponsor(array $a,array &$s,string $requestId,array $funding,array &$quota):void {
    labSponsorLocked(function($v)use($a,&$s,$requestId,$funding,&$quota){
        $r=$v['sponsors'][$funding['sponsor_id']] ?? null;
        if(!$r || !$r['active'] || $r['revision']!==$funding['revision'])throw new RuntimeException(labT('sponsors.ReserveSponsor.1'));
        $day=labSponsorDay();$prior=$quota['sponsor_usage'][$day][$r['id']] ?? [];
        if(isset($prior[$requestId]))throw new RuntimeException(labT('sponsors.ReserveSponsor.2'));
        if(count($prior)>=$r['daily_limit'])throw new RuntimeException(labT('sponsors.ReserveSponsor.3'));
        $s['funding_source']=$funding['source'];$s['sponsor_id']=$r['id'];$s['sponsor_profile']=$r['profile'];$s['sponsor_label']=$r['label'];$s['sponsor_revision_at_start']=$r['revision'];$s['actor_price_cap_at_start']=$r['price_cap'];
        $quota['sponsor_usage'][$day][$r['id']][$requestId]=$s['session_id'];
    });
}

function labGuardFunding(?string $requestKey=null):void {
    $ids=[];foreach(['funding','free'] as $kind){$id=$GLOBALS['lab_'.$kind.'_sponsor_id'] ?? '';if($id!=='')$ids[$id]=$GLOBALS['lab_'.$kind.'_'.($kind==='funding'?'revision':'sponsor_revision')] ?? 0;}
    if(!$ids)return;
    $matchingId='';
    if($requestKey!==null)foreach(['funding','free'] as $kind){$candidate=$GLOBALS[$kind==='funding'?'lab_funding_key':'lab_free_actor_key'] ?? '';if($candidate!=='' && hash_equals($candidate,$requestKey) && !empty($GLOBALS['lab_'.$kind.'_sponsor_id'])){$matchingId=$GLOBALS['lab_'.$kind.'_sponsor_id'];break;}}
    $limits=labSponsorLocked(function($v)use($ids,$matchingId){$limits=null;foreach($ids as $id=>$revision){$r=$v['sponsors'][$id] ?? null;if(!$r || !$r['active'] || $r['revision']!==$revision)throw new RuntimeException(labT('sponsors.GuardFunding.1'));if($id===$matchingId)$limits=['daily'=>$r['budget_daily_usd'] ?? null,'total'=>$r['budget_total_usd'] ?? null];}return $limits;});
    if(!$limits || ($limits['daily']===null && $limits['total']===null))return;
    $usage=labIdentityHttp('https://openrouter.ai/api/v1/key',['Authorization: Bearer '.$requestKey])['data'] ?? [];
    foreach(['daily'=>'usage_daily','total'=>'usage'] as $type=>$field)if($limits[$type]!==null){
        if(!is_numeric($usage[$field] ?? null) || !is_finite((float)$usage[$field]) || (float)$usage[$field]<0)throw new RuntimeException(labT('sponsors.GuardFunding.2'));
        $byok=$type==='daily'?'byok_usage_daily':'byok_usage';
        $spent=(float)$usage[$field]+(is_numeric($usage[$byok] ?? null)?max(0,(float)$usage[$byok]):0);
        if($spent >= (float)$limits[$type])throw new RuntimeException($type==='daily'?labT('sponsors.GuardFunding.3'):labT('sponsors.GuardFunding.4'));
    }
}

function labSponsorSiteQuota(?array $a):?array {
    return labSponsorLocked(function($v)use($a){foreach($v['sponsors'] as $r)if($r['is_site'])return labSponsorPublic($r,$v,$a);return null;});
}
