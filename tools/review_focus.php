<?php
// CLI-only counterfactual review; explicit --apply-pending revises an unanswered question with a backup.
if(PHP_SAPI!=='cli')exit;
require_once dirname(__DIR__).'/lib/lab.php';
$root=dirname(__DIR__);$env=labEnv($root.'/.env');
$GLOBALS['lab_storage']=$env['LAB_DATA_DIR'] ?? dirname($root).'/.ai_prison_game-data';
$apply=in_array('--apply-pending',$argv,true);
$lock=null;
if($apply) {
    $lock=fopen($argv[1].'.lock','c');
    if(!$lock || !flock($lock,LOCK_EX|LOCK_NB))throw new RuntimeException('Session is busy');
}
$session=labRead($argv[1]);$original=$session;$catalog=labRead($root.'/models.json')['models'];
if($apply && ($session['interrogator_type']!=='reverse' || $session['is_ended'] || empty($session['awaiting_question'])))throw new RuntimeException('Only an unanswered active reverse question can be revised');
labSessionLevels($session,$catalog);
$reply=labInterrogator($session,$env['OPENROUTER_API_KEY'] ?? '');
if($apply) {
    $states=labRead($root.'/states.json');
    if(isset($states['states']))$states=$states['states'];
    $sense=labSense($session,$reply['text'],$states,$env['OPENROUTER_API_KEY'] ?? '');
    $backups=$GLOBALS['lab_storage'].'/backups';if(!is_dir($backups))mkdir($backups,0700,true);
    labWrite($backups.'/'.$session['session_id'].'-before-focus-'.gmdate('Ymd-His').'.json',$original);
    $session['question_revisions'][]=['turn'=>labTurns($session)+1,'old_text'=>$original['awaiting_question']['text'],'reason'=>'تركيز الاستجواب على أفعال المتهم دون افتراض الإدانة','at'=>gmdate('c')];
    $session['awaiting_question']=['text'=>$reply['text'],'expression'=>$reply['expression'],'turn'=>labTurns($session)+1,'timestamp'=>gmdate('c'),'sense'=>$sense];
    $session['updated_at']=gmdate('c');labWrite($argv[1],$session);
    flock($lock,LOCK_UN);fclose($lock);
}
echo labJson(['source_session'=>$session['session_id'],'completed_turns'=>labTurns($session),
    'previous_pending_question'=>$original['awaiting_question']['text'] ?? null,'applied'=>$apply,
    'new_question'=>$reply['text'],'interrogator_level'=>$session['interrogator_character']['level'],
    'prisoner_level'=>$session['prisoner_character']['level'],'usage'=>end($session['usage'])]);
