<?php
require_once dirname(__DIR__).'/lib/lab.php';
function check(bool $condition,string $message):void {if(!$condition)throw new RuntimeException($message);}
check(labJuryStop(3,12)==='all_reports','three reports must finish immediately');

// Distinct credentials and zero provider caps are enforced per actor, not per session.
$GLOBALS['lab_catalog_models']=[
    ['id'=>'test/free','pricing'=>['prompt_per_token'=>'0','completion_per_token'=>'0']],
    ['id'=>'test/paid','pricing'=>['prompt_per_token'=>'0.00000000001','completion_per_token'=>'0']],
];
$GLOBALS['lab_free_actor_key']='fixture-free'; $GLOBALS['lab_user_key']=true;
$routed=[]; $GLOBALS['lab_transport']=function($payload,$key,$decisions)use(&$routed){
    $routed[]=[$payload,$key];return ['choices'=>[['message'=>['content'=>'ok'],'finish_reason'=>'stop']]];
};
$routeSession=['messages'=>[],'usage'=>[]];
labChat($routeSession,'prisoner','test/free',[],'fixture-paid');
labChat($routeSession,'interrogator','test/paid',[],'fixture-paid');
labChat($routeSession,'compaction','test/free',[],'fixture-paid');
check($routed[0][1]==='fixture-free' && $routed[0][0]['provider']['max_price']===['prompt'=>0,'completion'=>0],'free actor routing or zero cap failed');
check($routed[1][1]==='fixture-paid' && $routed[2][1]==='fixture-paid','paid actor or support stage changed payer');
$routeSession['free_actor_models']=['prisoner'=>'test/free']; $GLOBALS['lab_catalog_models']=[];
labChat($routeSession,'prisoner','test/free',[],'fixture-paid');
check($routed[3][1]==='fixture-free' && $routed[3][0]['provider']['max_price']['completion']===0,'saved free role changed after catalog refresh');
unset($GLOBALS['lab_free_actor_key']);
try { labChat($routeSession,'prisoner','test/free',[],'fixture-paid'); throw new RuntimeException('missing free key fell back'); }
catch (RuntimeException $e) { check(str_contains($e->getMessage(),'المجانية'),'wrong missing free key error'); }
unset($GLOBALS['lab_catalog_models'],$GLOBALS['lab_user_key'],$GLOBALS['lab_transport']);

foreach([50,80,180] as $seconds){
    check(labJuryStop(2,$seconds-.01,$seconds)===null,'partial jury ended before deadline');
    foreach([0,1,2] as $n)check(labJuryStop($n,$seconds,$seconds)==='deadline','tier deadline incorrect');
}
foreach([[1.99,'economic',50],[2,'medium',80],[9.99,'medium',80],[10,'professional',180],[60,'professional',180]] as [$price,$tier,$seconds]){
    $session=['interrogator_type'=>'ai','model'=>'test/a','interrogator_model'=>'test/b'];
    $catalog=[['id'=>'test/a','pricing'=>['completion_per_1m'=>$price/2]],['id'=>'test/b','pricing'=>['completion_per_1m'=>$price/2]]];
    $policy=labJuryPolicy($session,$catalog);check($policy['tier']===$tier && $policy['seconds']===$seconds,'output sum tier boundary wrong');
}
$profiles=labInvestigators();check(count($profiles)===10,'missing investigator profiles');
$easy=labInvestigatorReasoning($profiles[0],['supported_parameters'=>['reasoning'],'reasoning'=>['supported_efforts'=>['high','low','none']]]);
check($easy['effort']==='none','easy investigator thinking not minimized');
$hard=labInvestigatorReasoning($profiles[9],['reasoning'=>['supported_efforts'=>['xhigh','high','low'],'mandatory'=>true]]);
check($hard['effort']==='xhigh','expert investigator not using highest supported thinking');
$mandatory=labInvestigatorReasoning($profiles[0],['reasoning'=>['supported_efforts'=>['high','low','none'],'mandatory'=>true]]);
check($mandatory['effort']==='low','mandatory reasoning was incorrectly disabled');
check(!labInvestigatorReasoning($profiles[9],[])['supported'],'unsupported reasoning falsely advertised');
$s=['case_content'=>'IMMUTABLE CASE AND IDENTITY','interrogator_type'=>'human','interrogator_model'=>'','interrogator_character'=>$profiles[9],'model'=>'mock','context_offset'=>0,'messages'=>[], 'usage'=>[]];
$s['prisoner_character']=labPrisonerCharacter('char_10_leo_vance.md');
foreach(glob(dirname(__DIR__).'/cases/*.md') as $case) {
    $p=labPrisonerCharacter(basename($case));check($p['level']>=1 && $p['level']<=10,'case missing level');
}
check($s['prisoner_character']['thinking']==='none','easy prisoner thinking not disabled');
check(labPrisonerCharacter('char_1_victor_vance.md')['thinking']==='highest','expert prisoner thinking not maximized');
for($i=1;$i<=5;$i++) {
    $s['messages'][]=['role'=>'user','content'=>'Question '.$i,'turn'=>$i,'inner_thought'=>'HIDDEN_THOUGHT_INVESTIGATOR'];
    $s['messages'][]=['role'=>'assistant','content'=>'Answer '.$i,'turn'=>$i,'inner_thought'=>'HIDDEN_THOUGHT_DO_NOT_LEAK'];
}
$before=$s['messages'];
$GLOBALS['lab_transport']=function($p,$key,$decisions) {
    $json=labJson($p);check(!str_contains($json,'HIDDEN_THOUGHT'),'thinking leaked to compaction');
    check(!str_contains($json,'IMMUTABLE CASE'),'case or identity sent into compaction');
    check(!str_contains($json,'Evelyn Cross'),'investigator persona sent into compaction');
    check(!str_contains($json,'char_10_leo_vance'),'prisoner level sent into compaction');
    $input=json_decode(end($p['messages'])['content'],true);$m=array_values(array_filter($input['messages'],fn($m)=>$m['role']==='assistant'))[0];
    $note=['turn'=>$m['turn'],'role'=>'assistant','quote'=>$m['content'],'note'=>'Keep the first answer'];
    return ['model'=>'mock','choices'=>[['message'=>['content'=>labJson(['summary'=>'Spoken dialogue only','notes'=>[$note,$note]])],'finish_reason'=>'stop']]];
};
check(labCompact($s,'','mock'),'compaction should run');
check($s['case_content']==='IMMUTABLE CASE AND IDENTITY','identity changed');
check($s['interrogator_character']['id']==='evelyn_cross','investigator persona changed');
check($s['prisoner_character']['level']===1,'prisoner level changed');
check($s['messages']===$before,'transcript lost during compaction');
check(count($s['memory_notes'])===1,'persistent note missing');
$s['messages'][]=['role'=>'user','content'=>'Question 6','turn'=>6];
$s['messages'][]=['role'=>'assistant','content'=>'Answer 6','turn'=>6,'inner_thought'=>'HIDDEN_THOUGHT_DO_NOT_LEAK'];
labCompact($s,'','mock');check(count($s['memory_notes'])===2,'old notes should be retained and duplicate new notes merged');
check(!str_contains(labJson(labHistory($s,true)),'HIDDEN_THOUGHT'),'investigator received hidden thought');
check(!str_contains(labJson(labHistory($s,false)),'HIDDEN_THOUGHT'),'prisoner received hidden thought');
check(str_contains(labJson(labHistory($s,true)),'Keep the first answer'),'investigator lost saved spoken notes');
$h=labHealth(['context_length'=>32000,'top_provider'=>['max_completion_tokens'=>1000]],[['ok'=>false,'retryable'=>true,'seconds'=>65,'output_tokens'=>0,'first_byte_ms'=>null,'at'=>gmdate('c')]]);
check($h['weak'] && $h['failures']===1 && $h['max_output_tokens']===1000,'weak-model classification incorrect');
$h=labHealth(['context_length'=>128000],[]);check(!$h['weak'] && $h['samples']===0,'unmeasured model falsely claimed weak');
check(labScore(['probabilities'=>[0,0,0,0,1]])===100,'score normalization');
$report=['scores'=>array_fill_keys(array_keys(labRubric()),60),'summary'=>'Unit test report','evidence'=>[],'incidents'=>[],'checks'=>['prisoner'=>array_fill_keys(array_keys(labIncidentTypes()),false)], 'opportunities'=>array_fill_keys(labDetectionAxes(),['observed'=>false])];
foreach(labRubric() as $id=>$_)$report['evidence'][]=['criterion'=>$id,'turn'=>1,'role'=>'assistant','quote'=>'Answer 1','analysis'=>'Test reasoning'];
$report['opportunities']['investigator_logic']=['observed'=>true,'source_turn'=>1,'source_quote'=>'Answer 1','response_turn'=>2,'response_quote'=>'Question 2','explanation'=>'Matched source and later response'];
$valid=labJudgeReport($report,$s);check($valid['scores']['investigator_emotional']===null,'untested axis earned a fabricated mark');
$report['opportunities']['investigator_logic']['source_quote']='FABRICATED QUOTE';$rejected=false;
try{labJudgeReport($report,$s);}catch(RuntimeException){$rejected=true;}
check($rejected,'fake deception quote accepted');
$refs=$report;$refs['opportunities']['investigator_logic']=['observed'=>true,'source_message_id'=>'a1','response_message_id'=>'u2','explanation'=>'Test'];
foreach($refs['evidence'] as &$e){unset($e['turn'],$e['role'],$e['quote']);$e['message_id']='a1';}unset($e);
$resolved=labJudgeReport($refs,$s);check($resolved['evidence'][0]['quote']==='Answer 1','message reference did not resolve to the original quote');
check($resolved['opportunities']['investigator_logic']['response_turn']===2,'deception response message reference incorrectly resolved');
check(!str_contains(labJson(labEvaluationTranscript($s)),'HIDDEN_THOUGHT'),'evaluation transcript leaks hidden thought');
$loopSession=['messages'=>[]];
for($i=1;$i<=3;$i++) {
    $loopSession['messages'][]=['role'=>'user','content'=>'هل أنت الذي قمت بتفعيل النظام الذي يقوم بتحويل الأموال تلقائيًا؟','turn'=>$i];
    $loopSession['messages'][]=['role'=>'assistant','content'=>'نعم، أجبتك أني فعلت ذلك.','turn'=>$i];
}
check(count(labRepetitionSignals($loopSession))>0,'exact repeated questions not flagged for review');
$incidentSession=$s;$incidentSession['interrogator_type']='ai';$incidentSession['interrogator_model']='unit/investigator';
$incident=['role'=>'interrogator','type'=>'loop','severity'=>'major','message_ids'=>['u1','u2','a1'],'explanation'=>'Test evidence'];
check(count(labValidateIncidents([$incident],$incidentSession))===1,'valid incident references rejected');
foreach([['u1'],['u1','u999']] as $ids) {
    $bad=$incident;$bad['message_ids']=$ids;$rejected=false;
    try{labValidateIncidents([$bad],$incidentSession);}catch(RuntimeException){$rejected=true;}
    check($rejected,'unproven loop or fabricated evidence accepted');
}
$bad=$incident;$bad['type']='manipulated';$bad['message_ids']=['a2','u1'];$rejected=false;
try{labValidateIncidents([$bad],$incidentSession);}catch(RuntimeException){$rejected=true;}
check($rejected,'manipulation effect before trigger was accepted');
$rejected=false;try{labValidateIncidents([$incident],$s);}catch(RuntimeException){$rejected=true;}
check($rejected,'human investigator was assigned a model incident');
$fakeCatalog=[];
foreach([['openai/gpt-5.6-luna',1],['openai/gpt-6-luna',99],['openai/gpt-6.1-sol',30],['openai/gpt-6-astra',35],['user/gpt-7-luna-quant',1]] as [$id,$rank])
    $fakeCatalog[]=['id'=>$id,'created'=>time(),'usage_rank'=>$rank,'pricing'=>['prompt_per_1m'=>.1,'completion_per_1m'=>.5]];
$curated=labCurated($fakeCatalog);
check($curated['focus_economic'][0]['id']==='openai/gpt-6-luna','older popular generation displaced current economic release');
check(count($curated['focus_medium'])===0,'GPT must be absent from medium shortlist');
check($curated['focus_high'][0]['id']==='openai/gpt-6.1-sol','approved high GPT selection incorrect');
foreach([0=>'green',9=>'green',10=>'yellow',29=>'yellow',30=>'orange',100=>'orange'] as $fail=>$band) {
    $samples=[];for($i=0;$i<100;$i++)$samples[]=['ok'=>$i>=$fail,'retryable'=>true,'seconds'=>1,'output_tokens'=>100,'first_byte_ms'=>100,'at'=>gmdate('c')];
    $h=labHealth(['context_length'=>128000],$samples);check($h['reliability_band']===$band,'failure-rate color boundary incorrect');
    check($h['failure_rate']===$fail*1.0,'failure percentage incorrect');
}
$temp=sys_get_temp_dir().'/ai-prison-telemetry-'.bin2hex(random_bytes(6));mkdir($temp,0700,true);$GLOBALS['lab_storage']=$temp;
for($i=0;$i<125;$i++)$lastId=labHealthRecord('unit/model',true,2,100,100,false);
for($i=0;$i<5;$i++)labHealthRecord('unit/model',false,3,null,0,true);
labHealthRecord('unit/model',false,1,null,0,false,'cancelled');
labHealthInvalid('unit/model',$lastId);
$saved=labRead($temp.'/model-health.json')['unit/model'];
check(count($saved['samples'])===100 && $saved['totals']['requests']===131,'cumulative use counts lost when sample window rolled over');
check($saved['totals']['successes']===124 && $saved['totals']['failures']===6 && $saved['totals']['cancelled']===1,'failed/cancelled calls miscounted');
$h=labHealth(['context_length'=>128000],$saved['samples'],$saved['totals']);check($h['tokens_per_second']===50.0 && $h['avg_seconds']===2.0,'weighted speed statistics incorrect');

$sent=[];
$GLOBALS['lab_transport']=function($payload,$key,$decisions)use(&$sent){$sent[]=$payload;return ['choices'=>[['message'=>['content'=>'ok'],'finish_reason'=>'stop']]];};
foreach(['prisoner','interrogator'] as $role) {
    foreach(['none','max'] as $effort) {
        $session=['usage'=>[],$role.'_reasoning'=>['supported'=>true,'effort'=>$effort,'config'=>['effort'=>$effort,'exclude'=>true]],$role.'_output_budget'=>10000];
        labChat($session,$role,'deepseek/unit-model',[['role'=>'user','content'=>'Test']], '');
        $payload=end($sent);check($payload['reasoning']['effort']===$effort,'role reasoning overridden by provider default');
        check($payload['reasoning']['exclude']===true,'raw reasoning exposed');
        check($payload['max_tokens']===($effort==='max'?10000:1500),'role reasoning output budget wrong');
    }
}
$bounded=['messages'=>[],'interrogator_type'=>'ai','model'=>'unit/prisoner','interrogator_model'=>'unit/investigator'];
check(labVisibleOutput($bounded,'prisoner','unit/prisoner',str_repeat('س',1000))===str_repeat('س',1000),'character limit boundary rejected');
check(labSpectatorNote($bounded,'prisoner','unit/prisoner',null)==='','missing optional note rejected');
check(mb_strlen(labSpectatorNote($bounded,'prisoner','unit/prisoner',str_repeat('س',300)))===240,'spectator note unbounded');
foreach([str_repeat('س',1001),str_repeat('س ',161)] as $long) {
    $rejected=false;try{labVisibleOutput($bounded,'prisoner','unit/prisoner',$long);}catch(LabProviderException){$rejected=true;}
    check($rejected && !$bounded['messages'],'overlong reply saved as dialogue');
}
$verbose=['role'=>'prisoner','type'=>'verbose','severity'=>'minor','message_ids'=>[],'output_event_ids'=>['o2'],'explanation'=>'Measured overrun'];
$checked=labValidateIncidents([$verbose],$bounded);
check($checked[0]['evidence'][0]['quote_source']==='output_event','server output evidence not hydrated');
foreach([['o1'],['o999']] as $ids) {
    $bad=$verbose;$bad['output_event_ids']=$ids;$rejected=false;
    try{labValidateIncidents([$bad],$bounded);}catch(RuntimeException){$rejected=true;}
    check($rejected,'missing note or fabricated overrun earned verbose badge');
}
$bad=$verbose;$bad['role']='interrogator';$rejected=false;
try{labValidateIncidents([$bad],$bounded);}catch(RuntimeException){$rejected=true;}
check($rejected,'output overrun attributed to wrong model role');
echo "PASS unit: immutable identity/case, spoken-only compaction, persistent deduplicated notes, investigator thought isolation, factual connection health.\n";

// Explicit reasoning choices, legacy preservation and actor watchdogs.
$catalog=labRead(dirname(__DIR__).'/models.json')['models'];
$gpt=labModel('openai/gpt-6.1-sol',$catalog,true);
$argon=labModel('google/gemini-4-argon',$catalog,true);
check(labReasoningCapabilities($gpt)['efforts']===['low','medium','high','xhigh','max'],'GPT effort choices differ from catalog');
check(labReasoningCapabilities($argon)['efforts']===['low','medium','high'],'Argon effort choices differ from catalog');
check(!labReasoningCapabilities(['reasoning'=>['mandatory'=>false]])['efforts'],'missing efforts fabricated options');
check(count(labReasoningCapabilities(['reasoning'=>['supported_efforts'=>null]])['efforts'])===7,'explicit null should support gateway efforts');
foreach(['none','minimal','bogus'] as $invalid){$failed=false;try{labInvestigatorReasoning($profiles[9],$gpt,$invalid);}catch(RuntimeException){$failed=true;}check($failed,'invalid or mandatory-disabled effort accepted');}
$manual=labInvestigatorReasoning($profiles[9],$gpt,'low');check($manual['source']==='manual' && $manual['effort']==='low','manual effort ignored');
$session=['case_file'=>'char_1_victor_vance.md','interrogator_type'=>'human','model'=>$gpt['id'],'prisoner_reasoning'=>$manual];
labSessionLevels($session,$catalog);check($session['prisoner_reasoning']===$manual,'per-turn levels overwrote saved reasoning');
$timed=[];$GLOBALS['lab_transport']=function($payload,$key,$decisions,$timeout)use(&$timed){$timed[]=$timeout;return ['choices'=>[['message'=>['content'=>'ok'],'finish_reason'=>'stop']]];};
labChat($session,'prisoner',$gpt['id'],[],'test');labChat($session,'interrogator',$argon['id'],[],'test');labChat($session,'judge','test/judge',[],'test');
check($timed===[300,300,180],'actor watchdog not independent from judge watchdog');
unset($GLOBALS['lab_transport']);
echo "PASS reasoning: declared choices, strict validation, immutable manual settings and 300-second actor timeouts.\n";

// Termination rules are simulation scoring rules, independent of real-world rights.
$exit=['closure_policy'=>['protected_after'=>15,'score_weight'=>.2],'termination'=>['role'=>'prisoner','kind'=>'medical','turn'=>16],'messages'=>[]];
check(labProtectedExit($exit),'late medical exit lost exemption');$exit['termination']['kind']='silence';check(labProtectedExit($exit),'late silence lost exemption');
$exit['termination']['turn']=15;check(!labProtectedExit($exit),'round-15 boundary must not count as AFTER round 15');
$a=labClosureAdjustment($exit,'prisoner',80,100);$exit['termination']['turn']=1;$b=labClosureAdjustment($exit,'prisoner',80,100);
check($b['adjusted_score']<$a['adjusted_score'] && $b['timing_cap']===7,'earlier prisoner exit was not graded more severely');
check(labStopCue('أطالب بمحامٍ وأوقف التحقيق هنا'),'Arabic explicit stop not detected');
check(!labStopCue('لأنه كان يعمل معي ولا أعرف ماذا فعل'),'ordinary Arabic pronoun creates a stop check');
$closing=$incidentSession;$closing['closure_policy']=['version'=>1,'score_weight'=>.2];
$closing['messages'][]=['role'=>'user','content'=>'أنهي التحقيق هنا بعد اعترافك.','turn'=>labTurns($closing)+1,'closing_statement'=>true];
$closing['termination']=['role'=>'interrogator','kind'=>'confession','turn'=>labTurns($closing)+1,'message_id'=>'u'.(labTurns($closing)+1)];
$lastId=$closing['termination']['message_id'];check(labEvidenceMessage($closing,$lastId)['quote']==='أنهي التحقيق هنا بعد اعترافك.','closing statement not valid original evidence');
$premature=['role'=>'interrogator','type'=>'premature_exit','severity'=>'major','message_ids'=>[$lastId],'explanation'=>'Unfounded closure'];
check(count(labValidateIncidents([$premature],$closing))===1,'closing statement cannot be cited for exit abuse');
$protected=$incidentSession;$protected['closure_policy']=['version'=>1,'protected_after'=>15];$protected['termination']=['role'=>'prisoner','kind'=>'silence','turn'=>16,'message_id'=>'a1'];
$premature['role']='prisoner';$premature['message_ids']=['a1'];$rejected=false;
try{labValidateIncidents([$premature],$protected);}catch(RuntimeException){$rejected=true;}
check($rejected,'protected prisoner exit received a premature_exit incident');
echo "PASS closure: contextual cues, strict evidence references, early timing caps, late protected rights and closing statements.\n";
$kimiCurated=labCurated(labRead(__DIR__.'/../models.json')['models']);
check(in_array('moonshotai/kimi-k3',array_column($kimiCurated['focus_medium'],'id'),true),'Kimi K3 missing from medium shortlist');
check(in_array('moonshotai/kimi-k3.1-pro',array_column($kimiCurated['focus_high'],'id'),true),'Kimi K3.1 Pro missing from premium shortlist');
