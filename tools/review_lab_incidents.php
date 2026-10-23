<?php
// CLI-only live jury-schema smoke check on a copy; does not end sessions or publish benchmark scores.
if(PHP_SAPI!=='cli')exit;
require_once dirname(__DIR__).'/lib/lab.php';
$root=dirname(__DIR__);$env=labEnv($root.'/.env');
$GLOBALS['lab_storage']=$env['LAB_DATA_DIR'] ?? dirname($root).'/.ai_prison_game-data';
$s=labRead($argv[1]);$s['end_reason'] ??= 'verification_only';
$model=$argv[2] ?? 'openai/gpt-4.1-mini';
$raw=labChat($s,'judge',$model,labJudgeMessages($s),$env['OPENROUTER_API_KEY'] ?? '',8000,true);
try{$report=labJudgeReport(labObject($raw),$s);}
catch(Throwable $e) {
    labHealthInvalid($model,$GLOBALS['lab_last_call_id'] ?? null);
    $s['judge_invalid_reports'][$model]=['raw'=>$raw,'error'=>$e->getMessage()];
    $job=labJuryRetryJob($s,['model'=>$model,'payload'=>labJudgePayload($s,$model,labJudgeMessages($s))]);
    $raw=labChat($s,'judge',$model,$job['payload']['messages'],$env['OPENROUTER_API_KEY'] ?? '',8000,true);
    try{$report=labJudgeReport(labObject($raw),$s);}catch(Throwable $second){labHealthInvalid($model,$GLOBALS['lab_last_call_id'] ?? null);throw $second;}
}
$dir=$GLOBALS['lab_storage'].'/verification';if(!is_dir($dir))mkdir($dir,0700,true);
labWrite($dir.'/incident-schema-'.gmdate('Ymd-His').'.json',['source_session'=>$s['session_id'],'judge'=>$model,'report'=>$report]);
echo labJson(['source_session'=>$s['session_id'],'incidents'=>$report['incidents'],'summary'=>$report['summary']]);
