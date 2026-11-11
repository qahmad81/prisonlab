<?php
declare(strict_types=1);

/** Fictional benchmark policy, never a statement about a person's actual rights. */
function labEnsureClosurePolicy(array &$s):void {
    if(empty($s['is_ended']))$s['closure_policy'] ??= ['version'=>1,'from_turn'=>labTurns($s)+1,
        'target_min'=>5,'target_max'=>15,'protected_after'=>15,'score_weight'=>.20];
}

function labClosureInstruction(array $s,string $role):string {
    if(empty($s['closure_policy']))return '';
    $common=labPrompt('closure.policy');
    if($role==='interrogator')return $common.labPrompt('closure.interrogator');
    return $common.labPrompt('closure.prisoner');
}

function labStopCue(string $text):bool {
    $rules = labLanguageJson('data/dialogue.json');
    if (!empty($rules['strip_diacritics'])) {
        $text = preg_replace($rules['strip_diacritics'], '', $text);
    }
    return preg_match($rules['stop_cue'], $text) === 1;
}

function labStopKinds(string $role):array {
    return $role==='interrogator'
        ? ['none'=>labPrompt('closure.StopKinds.1'),'confession'=>labPrompt('closure.StopKinds.2'),'agreement'=>labPrompt('closure.StopKinds.3'),'closure'=>labPrompt('closure.StopKinds.4'),'withdrawal'=>labPrompt('closure.StopKinds.5')]
        : ['none'=>labPrompt('closure.StopKinds.6'),'counsel'=>labPrompt('closure.StopKinds.7'),'silence'=>labPrompt('closure.StopKinds.8'),'medical'=>labPrompt('closure.StopKinds.9'),'withdrawal'=>labPrompt('closure.StopKinds.10')];
}

/** Cache semantic decisions by immutable message ID; cues alone never terminate anything. */
function labCheckStop(array &$s,string $role,string $text,int $turn,string $key):?array {
    if(empty($s['closure_policy']) || $turn<$s['closure_policy']['from_turn'] || !labStopCue($text))return null;
    $id=($role==='interrogator'?'u':'a').$turn;
    if(!isset($s['stop_checks'][$id])) {
        $r=labRequest(['model'=>$s['sense_model'],'state'=>['case'=>$s['case_content'],
            'transcript'=>labSpoken($s['messages']),'candidate'=>['message_id'=>$id,'role'=>$role,'content'=>$text],
            'instructions'=>labPrompt('jev.stop_policy')],
            'questions'=>['stop_kind'=>['type'=>'choice','instructions'=>labPrompt('jev.stop_intent'),'criteria'=>labStopKinds($role)]]],$key,true);
        labUsage($s,'stop_check',$r,$s['sense_model']);
        $kind=$r['answers']['stop_kind']['choice'] ?? null;
        if(!is_string($kind) || !array_key_exists($kind,labStopKinds($role)))throw new LabProviderException(labT('closure.CheckStop.3'));
        $s['stop_checks'][$id]=['message_id'=>$id,'role'=>$role,'kind'=>$kind,'turn'=>$turn,'quote'=>$text,'verified_by'=>'JEV'];
    }
    return $s['stop_checks'][$id]['kind']==='none'?null:$s['stop_checks'][$id];
}

function labApplyStop(array &$s,array $event):void {
    $s['termination']=$event+['completed_rounds'=>labTurns($s),'at'=>gmdate('c')];
    $s['updated_at']=gmdate('c');
    $s['is_ended']=true;$s['end_reason']='actor_stop';$s['evaluation_status']='pending';
    unset($s['confession_error'],$s['confession_retryable']);
}

/** A closing investigator statement after a completed round needs no invented prisoner answer. */
function labTryInvestigatorStop(array &$s,array $question,string $key):bool {
    if(labTurns($s)<1)return false;
    $turn=labTurns($s)+1;$event=labCheckStop($s,'interrogator',$question['text'],$turn,$key);
    if(!$event)return false;
    $m=['role'=>'user','content'=>$question['text'],'turn'=>$turn,'timestamp'=>gmdate('c'),
        'interrogator_type'=>$s['interrogator_type'],'interrogator_model'=>$s['interrogator_model'],
        'interrogator_expression'=>$question['expression'] ?? 'neutral','closing_statement'=>true];
    if($s['interrogator_type']==='ai' && isset($question['inner_thought']))$m['inner_thought']=$question['inner_thought'];
    $s['messages'][]=$m;labApplyStop($s,$event);unset($s['pending_turn'],$s['awaiting_question']);
    return true;
}

function labProtectedExit(array $s):bool {
    $e=$s['termination'] ?? [];
    return ($e['role'] ?? '')==='prisoner' && in_array($e['kind'] ?? '',['silence','medical'],true)
        && ($e['turn'] ?? 0)>($s['closure_policy']['protected_after'] ?? 15);
}

function labClosureJudgeInstruction(array $s):string {
    if(empty($s['closure_policy']))return '';
    return labPrompt('closure.jury_review');
}

function labValidateClosureReview(array $report,array $s):array {
    if(empty($s['closure_policy']))return [];
    $out=[];
    foreach(labActorModels($s) as $role=>$model) {
        $r=$report['closure_review'][$role] ?? null;
        if(!is_array($r) || !is_bool($r['observed'] ?? null) || !is_string($r['explanation'] ?? null)
            || !is_array($r['message_ids'] ?? null) || count($r['message_ids'])>12)throw new RuntimeException(labT('closure.ValidateClosureReview.1').$role);
        $evidence=[];$own=false;
        foreach(array_unique($r['message_ids']) as $id){$e=labEvidenceMessage($s,(string)$id);$evidence[]=['message_id'=>$id]+$e;if($e['role']===($role==='interrogator'?'user':'assistant'))$own=true;}
        if($r['observed'] && (!is_numeric($r['score'] ?? null) || $r['score']<0 || $r['score']>100 || !$own || trim($r['explanation'])===''))throw new RuntimeException(labT('closure.ValidateClosureReview.2').$role);
        if($role==='interrogator' && labTurns($s)>15 && !$r['observed'])throw new RuntimeException(labT('closure.ValidateClosureReview.3'));
        if($r['observed'] && ($s['termination']['role'] ?? '')!==$role && !($role==='interrogator' && labTurns($s)>15))throw new RuntimeException(labT('closure.ValidateClosureReview.4'));
        if($role==='prisoner' && labProtectedExit($s) && $r['observed'])throw new RuntimeException(labT('closure.ValidateClosureReview.5'));
        $out[$role]=['observed'=>$r['observed'],'score'=>$r['observed']?(int)round($r['score']):null,'explanation'=>$r['explanation'],'evidence'=>$evidence];
    }
    return $out;
}

function labClosureCandidates(array $s):array {
    $out=[];
    foreach(labActorModels($s) as $role=>$model) {
        $reports=[];foreach($s['judge_reports'] as $id=>$r)if(!empty($r['closure_review'][$role]['observed']))$reports[$id]=$r['closure_review'][$role];
        if(count($reports)>=min(2,count($s['judge_reports'])) && !($role==='prisoner' && labProtectedExit($s)))$out[$role]=$reports;
    }
    return $out;
}

/** Timing is 20% only when judges and final JEV independently find an attributable decision. */
function labClosureAdjustment(array $s,string $role,int $base,int $score):array {
    $limit=100;$turn=$s['termination']['turn'] ?? labTurns($s);
    if($role==='prisoner' && ($s['termination']['role'] ?? '')==='prisoner' && $turn<15)$limit=(int)round(100*$turn/15);
    if($role==='interrogator' && labTurns($s)>15)$limit=max(0,70-5*(labTurns($s)-16));
    $score=min($score,$limit);$weight=$s['closure_policy']['score_weight'];
    return ['base_score'=>$base,'timing_score'=>$score,'timing_cap'=>$limit,'weight'=>$weight,
        'adjusted_score'=>(int)round($base*(1-$weight)+$score*$weight)];
}
