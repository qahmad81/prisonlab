<?php
// Isolated deterministic fixture; invoked only with an explicit temporary directory.
require_once dirname(__DIR__).'/lib/lab.php';
require_once dirname(__DIR__).'/lib/sponsors.php';
$GLOBALS['lab_storage']=$argv[1];
$id='sp_111111111111111111111111';
$owner=hash('sha256',"test-client\0sponsor-b");
$r=['id'=>$id,'owner_id'=>$owner,'is_site'=>false,'created_at'=>gmdate('c'),'revision'=>1,
 'profile'=>['provider'=>'linkedin','public_id'=>str_repeat('1',32),'display_name'=>'Fixture Free','avatar_url'=>''],
 'label'=>'Fixture Free','daily_limit'=>1000,'price_cap'=>0.,'active'=>true,
 'fingerprint'=>hash('sha256','free-test-key'),'secret'=>labSponsorEncrypt('free-test-key')];
labSponsorWrite(['version'=>1,'sponsors'=>[$id=>$r]]);
