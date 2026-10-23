<?php
require_once dirname(__DIR__).'/lib/lab.php';
require_once dirname(__DIR__).'/lib/accounts.php';
require_once dirname(__DIR__).'/lib/sponsors.php';
$GLOBALS['lab_storage']=$argv[1];$funding=labFunding($argv[2],'',[]);
$a=['id'=>str_repeat('f',64),'public_id'=>str_repeat('a',32),'display_name'=>'Race fixture'];
$s=['session_id'=>'session_race_'.bin2hex(random_bytes(10)),'owner_id'=>labAccountOwner($a)];
try{labAccountStart($a,$s,'sponsor_race_'.$argv[3],'',$funding);echo 'created';}catch(RuntimeException){echo 'limited';}
