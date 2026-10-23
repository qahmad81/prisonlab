<?php
require_once dirname(__DIR__).'/lib/lab.php';require_once dirname(__DIR__).'/lib/accounts.php';
$GLOBALS['lab_storage']=$argv[1];$account=['id'=>str_repeat('a',64),'public_id'=>str_repeat('b',32),'display_name'=>'Race test'];
$s=['session_id'=>'session_race_'.$argv[2],'owner_id'=>labAccountOwner($account)];
try{labAccountStart($account,$s,'race_request_'.$argv[2],'');echo 'created';}
catch(RuntimeException $e){echo str_contains($e->getMessage(),'الثلاث')?'limited':'unexpected';}
