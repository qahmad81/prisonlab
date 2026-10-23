<?php
/** Explicit operator-only migration; never copies credentials into source or backups. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__).'/lib/lab.php';
require_once dirname(__DIR__).'/lib/accounts.php';
require_once dirname(__DIR__).'/lib/sponsors.php';
try{
    $profile=$argv[1] ?? '';if(!preg_match('/^[a-f0-9]{32}$/D',$profile))throw new RuntimeException('Pass the verified owner public profile ID');
    $env=labAccountBootstrap();$owner=null;
    foreach(glob($GLOBALS['lab_storage'].'/accounts/*.json') as $p){$a=labRead($p);if($a['public_id']===$profile)$owner=$a;}
    if(!$owner)throw new RuntimeException('No verified LinkedIn owner matches the supplied public profile');
    $existing=labDefaultSponsor();
    if($existing!=='' && labSponsorLocked(fn($v)=>!empty($v['site_migration_complete']))){echo "Site sponsor already exists; no secret changed.\n";exit;}
    $key=$env['OPENROUTER_API_KEY'] ?? '';
    if($existing==='') {
        if($key==='')throw new RuntimeException('No legacy site key to migrate');
        // Fail closed until existing daily reservations have been carried over.
        $r=labSaveSponsor($owner,['label'=>$owner['display_name'],'key'=>$key,'daily_limit'=>3,'price_cap'=>1,'active'=>false],true);
    }else {
        $r=labSponsorLocked(function($v)use($existing,$owner){$r=$v['sponsors'][$existing];if($r['owner_id']!==$owner['id'])throw new RuntimeException('Wrong migration owner');return labSponsorPublic($r,$v,$owner);});
    }
    $saved=labSponsorLocked(fn($v)=>labSponsorDecrypt($v['sponsors'][$r['id']]));
    if($key!=='' && !hash_equals($key,$saved))throw new RuntimeException('Migration verification failed');
    foreach(glob($GLOBALS['lab_storage'].'/quota_*.json') as $path) {
        $lock=fopen($path.'.lock','c');if(!$lock || !flock($lock,LOCK_EX))throw new RuntimeException('Quota migration lock failed');
        try{$q=labRead($path);$day=labSponsorDay();if(($q['day'] ?? '')!==$day)continue;
            $prior=$q['sponsor_usage'][$day][$r['id']] ?? []; $missing=max(0,(int)($q['used'] ?? 0)-count($prior));
            for($i=0;$i<$missing;$i++)$q['sponsor_usage'][$day][$r['id']]['legacy_migrated_'.bin2hex(random_bytes(8))]='legacy';
            if($missing)labWrite($path,$q);
        }finally{flock($lock,LOCK_UN);fclose($lock);}
    }
    $r=labSaveSponsor($owner,['id'=>$r['id'],'revision'=>$r['revision'],'label'=>$r['label'],'daily_limit'=>$r['daily_limit'],'price_cap'=>$r['price_cap'],'active'=>true]);
    labSponsorLocked(function($v){$v['site_migration_complete']=true;labSponsorWrite($v);},true);
    $funding=labFunding($r['id'],'',[]);if(!hash_equals($saved,$funding['key']))throw new RuntimeException('Migration verification failed');
    echo "Site key encrypted and verified under the requested LinkedIn owner.\n";
}catch(Throwable $e){fwrite(STDERR,"Site sponsor migration did not complete; legacy configuration is unchanged.\n");exit(1);}
