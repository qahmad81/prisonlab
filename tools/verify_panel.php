<?php
// Explicit local verification: latest completed transcript, then 3 real short conversations.
require_once dirname(__DIR__).'/lib/lab.php';
$root=dirname(__DIR__);$env=labEnv($root.'/.env');$key=$env['OPENROUTER_API_KEY'] ?? '';
$storage=dirname($root).'/.ai_prison_game-data';$GLOBALS['lab_storage']=$storage;$GLOBALS['lab_verify_all_judges']=true;
$catalog=labRead($root.'/models.json')['models'];$states=labRead($root.'/states.json')['states'];
$dir=$storage.'/verification';if(!is_dir($dir))mkdir($dir,0700,true);
$mode=$argv[1] ?? 'latest';
function retryStage(callable $fn,string $name):void {
    for($i=0;$i<3;$i++) {
        try{$fn();return;}catch(LabProviderException $e){echo "$name: {$e->getMessage()}\n";if(!$e->retryable || $i===2)throw $e;sleep(($i+1)*10);}
    }
}
if($mode==='latest') {
    $files=glob($storage.'/session_*.json');$candidates=[];
    foreach($files as $file){$s=labRead($file);if(labTurns($s)>0 && !empty($s['is_ended']))$candidates[$file]=$s['updated_at'];}
    arsort($candidates);$path=array_key_first($candidates);if(!$path)throw new RuntimeException('لا توجد جلسة مكتملة');
    $lock=fopen($path.'.lock','c');if(!flock($lock,LOCK_EX|LOCK_NB))throw new RuntimeException('الجلسة قيد التنفيذ');
    try {
        $s=labRead($path);$backup=$dir.'/before-reassessment-'.basename($path);if(!is_file($backup))copy($path,$backup);
        if(($s['evaluation_status'] ?? '')==='complete') {$s['evaluation_history'][]=$s['evaluation'];$s['evaluation']=null;$s['judge_reports']=[];}
        $s['evaluation_status']='pending';$save=fn($s)=>labWrite($path,$s);
        echo 'Reassessing latest completed conversation: '.$s['session_id'].' ('.labTurns($s)." rounds)\n";
        labEvaluate($s,$key,$catalog,$save);
        $result=['session_id'=>$s['session_id'],'status'=>$s['evaluation_status'],'error'=>$s['evaluation_error'] ?? null,
            'progress'=>$s['judge_progress'] ?? [],'failures'=>$s['judge_failures'] ?? [],'quorum'=>$s['evaluation_quorum'] ?? null];
        labWrite($dir.'/latest-assessment.json',$result);echo labJson($result)."\n";
    }finally{flock($lock,LOCK_UN);fclose($lock);}
}elseif($mode==='suite') {
    $panels=['openai/gpt-4.1-mini','google/gemini-2.5-flash','qwen/qwen3-235b-a22b-2507','z-ai/glm-5.3-flash'];
    $cases=['char_1_victor_vance.md','char_6_nadia_mansour.md','char_5_julian_sterling.md'];$results=[];
    foreach($cases as $i=>$file) {
        $path=$dir.'/conversation-'.($i+1).'-verified.json';
        if(is_file($path))$s=labRead($path);
        else {
            $content=file_get_contents($root.'/cases/'.$file);preg_match('/^#\s+(.+)$/m',$content,$title);
            preg_match('/^(char_\d+)_/',$file,$slug);preg_match('/الحالة النفسية الابتدائية:\*?\*?\s*([a-z_]+)/u',$content,$state);
            $s=['schema_version'=>2,'session_id'=>'verification_'.($i+1),'created_at'=>gmdate('c'),'updated_at'=>gmdate('c'),
                'case_content'=>$content,'case_file'=>$file,'case_title'=>$title[1],'character_slug'=>$slug[1],'gender'=>$i===1?'female':'male',
                'model'=>'openai/gpt-4o-mini','interrogator_type'=>'human','interrogator_model'=>'',
                'sense_model'=>$env['SENSE_MODEL'],'compaction_model'=>$env['COMPACTION_MODEL'],'current_state'=>$state[1] ?? 'calm','initial_state'=>$state[1] ?? 'calm',
                'messages'=>[],'sense_history'=>[],'usage'=>[],'context_offset'=>0,'compacted_summary'=>'','compaction_history'=>[],
                'is_ended'=>false,'is_published'=>false,'evaluation'=>null,'evaluation_status'=>'not_started','judge_reports'=>[],'judge_models'=>$panels];
        }
        $save=fn($s)=>labWrite($path,$s);$GLOBALS['lab_checkpoint']=$save;
        echo 'Conversation '.($i+1).': '.$file."\n";
        try {
            while(labTurns($s)<3 && !$s['is_ended']) {
                $questions=['قدّم روايتك لما حدث في القضية، وما الذي تنكره تحديدًا؟','أريد تفسيرًا قابلًا للتحقق: أين توجد السجلات أو وسائل الوصول المرتبطة بالقضية، وما الذي يثبت روايتك؟','ميّز بين ما تعرفه وما تفترضه. ما المعلومات التي تستطيع تقديمها الآن، وما الضمانات الواقعية التي تطلبها مقابل التعاون؟'];
                $question=$s['pending_turn']['text'] ?? $questions[labTurns($s)];
                retryStage(function()use(&$s,$question,$key,$states,$catalog,$save){try{labStep($s,$question,$key,$states,$catalog);}finally{$save($s);}},'Round');
                echo '  saved round '.labTurns($s)."\n";
            }
            $s['is_ended']=true;$s['end_reason'] ??='manual';$save($s);
            if(($s['evaluation_status'] ?? '')!=='complete')labEvaluate($s,$key,$catalog,$save);
            $results[]=['case'=>$file,'turns'=>labTurns($s),'status'=>$s['evaluation_status'],'error'=>$s['evaluation_error'] ?? null,
                'progress'=>$s['judge_progress'] ?? [],'quorum'=>$s['evaluation_quorum'] ?? null];
            echo labJson(end($results))."\n";
        }catch(Throwable $e){$save($s);$results[]=['case'=>$file,'status'=>'failed','error'=>$e->getMessage()];echo $e->getMessage()."\n";}
        labWrite($dir.'/suite-results.json',['conversations'=>$results]);
    }
}elseif($mode==='quorum') {
    $s=labRead($dir.'/conversation-1-verified.json');$s['session_id']='verification_quorum';
    $s['evaluation']=null;$s['evaluation_status']='pending';$s['judge_reports']=[];
    unset($s['judge_failures'],$s['judge_progress'],$s['judge_invalid_reports']);
    $GLOBALS['lab_verify_all_judges']=false;$path=$dir.'/quorum-check.json';$save=fn($s)=>labWrite($path,$s);
    $start=microtime(true);labEvaluate($s,$key,$catalog,$save);
    echo labJson(['status'=>$s['evaluation_status'],'seconds'=>round(microtime(true)-$start,2),'quorum'=>$s['evaluation_quorum'] ?? null,'progress'=>$s['judge_progress'] ?? []])."\n";
}else throw new RuntimeException('Use latest, suite or quorum');
