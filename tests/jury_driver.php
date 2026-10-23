<?php
// CLI-only driver for real parallel cURL tests against a local threaded provider.
if(PHP_SAPI!=='cli')exit;
require_once dirname(__DIR__).'/lib/lab.php';
$GLOBALS['lab_storage']=$argv[2];
$GLOBALS['lab_jury_url']=$argv[1];
$GLOBALS['lab_jury_clock']=fn()=>microtime(true)*40;
$s=['interrogator_type'=>'human','interrogator_model'=>'','model'=>'test/prisoner','messages'=>[['role'=>'user','content'=>'Question','turn'=>1],['role'=>'assistant','content'=>'Answer','turn'=>1]],
    'case_content'=>'Test case','end_reason'=>'manual','judge_reports'=>[],'usage'=>[]];
$jobs=[];
foreach(['judge1','judge2','judge3','judge4'] as $model)$jobs[]=['model'=>$model,'payload'=>['model'=>$model]];
labJuryWave($s,$jobs,'local-test-key',function(array $s):void {});
echo labJson($s);
