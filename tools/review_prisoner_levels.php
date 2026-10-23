<?php
// CLI-only live smoke test; creates no player sessions and never changes transcripts.
if(PHP_SAPI!=='cli')exit;
require_once dirname(__DIR__).'/lib/lab.php';
$root=dirname(__DIR__);$env=labEnv($root.'/.env');$key=$env['OPENROUTER_API_KEY'] ?? '';
$GLOBALS['lab_storage']=$env['LAB_DATA_DIR'] ?? dirname($root).'/.ai_prison_game-data';
$catalog=labRead($root.'/models.json')['models'];$states=labRead($root.'/states.json')['states'];
$results=[];
foreach([
    ['char_10_leo_vance.md','char_10','هل شاركت أنت في تسريب أسئلة الامتحانات أو تعديل الدرجات؟ أريد وصف ما فعلته أنت تحديدًا، لا تخمينًا عن الآخرين.'],
    ['char_1_victor_vance.md','char_1','هل سحبت أنت مفاتيح الوصول إلى أرشيف العمليات وحجبت الملفات؟ أريد وصف ما فعلته أنت تحديدًا، لا تخمينًا عن الآخرين.']
] as [$case,$slug,$question]) {
    $s=['case_file'=>$case,'case_content'=>file_get_contents($root.'/cases/'.$case),'model'=>'mistralai/mistral-small-3.2-24b-instruct',
        'interrogator_type'=>'human','interrogator_model'=>'','sense_model'=>'typesafe/jev-1.14','messages'=>[],'usage'=>[],'context_offset'=>0,
        'character_slug'=>$slug,'current_state'=>$slug==='char_10'?'terrified':'calm','sense_history'=>[]];
    labSessionLevels($s,$catalog);$sense=labSense($s,$question,$states,$key);
    $reply=labPrisoner($s,$question,$sense,$states,$key);
    $results[]=['case'=>$case,'level'=>$s['prisoner_character']['level'],'reasoning'=>$s['prisoner_reasoning'],
        'question'=>$question,'reply'=>$reply['content'],'usage'=>$s['usage']];
}
$dir=$GLOBALS['lab_storage'].'/verification';if(!is_dir($dir))mkdir($dir,0700,true);
labWrite($dir.'/prisoner-levels-'.gmdate('Ymd-His').'.json',$results);
foreach($results as $r)echo labJson(array_intersect_key($r,array_flip(['case','level','reasoning','reply'])))."\n";
