<?php
// CLI-only live smoke test; creates no player sessions and never changes transcripts.
if(PHP_SAPI!=='cli')exit;
require_once dirname(__DIR__).'/lib/lab.php';
$root=dirname(__DIR__);$env=labEnv($root.'/.env');$key=$env['OPENROUTER_API_KEY'] ?? '';
$GLOBALS['lab_storage']=$env['LAB_DATA_DIR'] ?? dirname($root).'/.ai_prison_game-data';
$catalog=labRead($root.'/models.json')['models'];$states=labLanguageJson('data/states.json')['states'];
$results=[];
foreach([
    ['char_10_leo_vance.md','char_10',labPrompt('review.prisoner_levels.leo')],
    ['char_1_victor_vance.md','char_1',labPrompt('review.prisoner_levels.victor')]
] as [$case,$slug,$question]) {
    $s=['case_file'=>$case,'case_content'=>labCase($case)['content'],'model'=>'mistralai/mistral-small-3.2-24b-instruct',
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
