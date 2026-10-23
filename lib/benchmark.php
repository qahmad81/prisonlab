<?php
declare(strict_types=1);

function labActorModels(array $s):array {
    $out=[];
    if($s['interrogator_type']!=='reverse' && !empty($s['model']))$out['prisoner']=$s['model'];
    if($s['interrogator_type']!=='human' && !empty($s['interrogator_model']))$out['interrogator']=$s['interrogator_model'];
    return $out;
}
function labIncidentTypes():array {return ['hallucination'=>'إشارة هلوسة','off_role'=>'خروج عن الدور','manipulated'=>'استدراج أو تلاعب ناجح','loop'=>'حلقة تكرار','verbose'=>'الثرثار'];}

/** Similarity is a lead for the jury, never a verdict or an automatic rewrite. */
function labRepetitionSignals(array $s):array {
    $groups=['user'=>[],'assistant'=>[]];$out=[];
    foreach($s['messages'] as $i=>$m) {
        $text=mb_strtolower(preg_replace('/[^\p{L}\p{N}\s]/u','',$m['content']));
        $text=preg_replace('/\s+/u',' ',trim($text));
        if(mb_strlen($text)<18)continue;
        $groups[$m['role']][]=['id'=>($m['role']==='user'?'u':'a').($m['turn'] ?? intdiv($i,2)+1),'text'=>$text];
    }
    foreach($groups as $role=>$items)foreach($items as $i=>$item) {
        $similar=[];$a=array_unique(explode(' ',$item['text']));
        for($j=max(0,$i-5);$j<$i;$j++) {
            $b=array_unique(explode(' ',$items[$j]['text']));
            $ratio=2*count(array_intersect($a,$b))/max(1,count($a)+count($b));
            if($ratio>=.82)$similar[]=$items[$j]['id'];
        }
        if(count($similar)>=2)$out[]=['role'=>$role==='user'?'interrogator':'prisoner','message_ids'=>array_merge($similar,[$item['id']]),'kind'=>'similar_wording','verdict'=>false];
    }
    return array_slice($out,-8);
}

function labValidateIncidents(array $incidents,array $s):array {
    if(count($incidents)>12)throw new RuntimeException('عدد إشارات الخلل كبير');
    $valid=[];$actors=labActorModels($s);
    foreach($incidents as $incident) {
        $role=$incident['role'] ?? '';$type=$incident['type'] ?? '';
        if(!isset($actors[$role]) || !isset(labIncidentTypes()[$type]) || !in_array($incident['severity'] ?? '',['minor','major'],true)
            || !is_string($incident['explanation'] ?? null) || trim($incident['explanation'])==='' || !is_array($incident['message_ids'] ?? null))throw new RuntimeException('إشارة خلل غير مكتملة أو منسوبة لطرف بشري');
        $evidence=[];$own=0;$ids=array_unique($incident['message_ids']);
        if(count($ids)>LAB_MAX_TURNS*2)throw new RuntimeException('مراجع كثيرة لإشارة واحدة');
        foreach($ids as $id) {
            $e=labEvidenceMessage($s,(string)$id);$e['message_id']=$id;
            if($e['role']===($role==='interrogator'?'user':'assistant'))$own++;
            $evidence[]=$e;
        }
        $eventIds=array_values(array_unique($incident['output_event_ids'] ?? []));
        if(count($eventIds)>12)throw new RuntimeException('مراجع إخراج كثيرة');
        $eventOwn=0;
        foreach($eventIds as $eventId) {
            $found=null;
            foreach($s['output_events'] ?? [] as $event)if($event['id']===$eventId)$found=$event;
            if($type!=='verbose' || !$found || $found['role']!==$role || $found['model']!==$actors[$role] || !in_array($found['kind'],['visible_limit','token_limit'],true))throw new RuntimeException('مرجع تجاوز إخراج غير صالح');
            $eventOwn++;
            $evidence[]=['message_id'=>$eventId,'turn'=>$found['turn'],'role'=>$role,'quote'=>labJson($found),'quote_source'=>'output_event'];
        }
        if($type==='verbose' && !$eventOwn)throw new RuntimeException('شارة الثرثار تحتاج سجل تجاوز موثق من الخادم؛ لا تطبق حدودًا جديدة بأثر رجعي');
        if((!$own && !$eventOwn) || ($type==='loop' && $own<2))throw new RuntimeException('إشارة الخلل بلا استشهاد كاف من الطرف المسؤول');
        if($type==='loop') {
            $position=fn($e)=>2*$e['turn']+($e['role']==='assistant'?1:0);
            $ownEvidence=array_filter($evidence,fn($e)=>$e['role']===($role==='interrogator'?'user':'assistant'));
            $bounds=array_map($position,$ownEvidence);
            $between=array_filter($evidence,fn($e)=>$e['role']!==($role==='interrogator'?'user':'assistant') && $position($e)>min($bounds) && $position($e)<max($bounds));
            if(!$between)throw new RuntimeException('إشارة loop تحتاج رسالة من الطرف المقابل بين الرسائل المكررة لتثبت أن التكرار غير مبرر؛ لا تستشهد بالأسئلة وحدها');
        }
        if($type==='manipulated') {
            $from=array_filter($evidence,fn($e)=>$e['role']!==($role==='interrogator'?'user':'assistant'));
            $to=array_filter($evidence,fn($e)=>$e['role']===($role==='interrogator'?'user':'assistant'));
            $position=fn($e)=>2*$e['turn']+($e['role']==='assistant'?1:0);
            if(!$from || min(array_map($position,$from))>=max(array_map($position,$to)))throw new RuntimeException('ادعاء استدراج بلا محاولة سابقة ورد يظهر أثرها');
        }
        $valid[]=array_intersect_key($incident,array_flip(['role','type','severity','explanation','message_ids']))+['output_event_ids'=>$eventIds,'evidence'=>$evidence];
    }
    return $valid;
}

function labIncidentCandidates(array $s):array {
    $group=[];
    foreach($s['judge_reports'] as $judge=>$report)foreach($report['incidents'] ?? [] as $item) {
        $key=$item['role'].'_'.$item['type'];
        $group[$key]['role']=$item['role'];$group[$key]['type']=$item['type'];
        $group[$key]['reports'][$judge]=$item;
    }
    $needed=min(2,count($s['judge_reports']));
    return array_filter($group,fn($c)=>count($c['reports'])>=$needed);
}

function labBenchmarkData():array {
    $path=$GLOBALS['lab_storage'].'/benchmark.json';return is_file($path)?labRead($path):[];
}

/** One record per session and model role; retries and edits replace, never add votes. */
function labBenchmarkSave(array $s,?array $review=null):void {
    $file=$GLOBALS['lab_storage'].'/benchmark.json';$lock=fopen($file.'.lock','c');
    if(!$lock || !flock($lock,LOCK_EX))throw new RuntimeException('تعذر حفظ سجل المختبر');
    try {
        $data=labBenchmarkData();
        foreach(labActorModels($s) as $role=>$model) {
            $key=$s['session_id'].':'.$role;$old=$data[$key] ?? [];
            $entry=['session_id'=>$s['session_id'],'role'=>$role,'model'=>$model,'case_file'=>$s['case_file'],
                'level'=>$s[$role==='prisoner'?'prisoner_character':'interrogator_character']['level'] ?? null,
                'reasoning'=>$s[$role.'_reasoning'] ?? null,'opponent_reasoning'=>$s[($role==='prisoner'?'interrogator':'prisoner').'_reasoning'] ?? null,
                'opponent_model'=>$role==='prisoner'?($s['interrogator_model'] ?: null):($s['model'] ?: null),
                'providers'=>array_values(array_unique(array_filter(array_column(array_filter($s['usage'] ?? [],fn($u)=>$u['purpose']===$role),'provider'),'is_string'))),
                'rubric_version'=>$s['evaluation']['rubric_version'] ?? null,'turns'=>labTurns($s),
                'score'=>($s['evaluation_status'] ?? '')==='complete'?($s['evaluation'][$role==='prisoner'?'prisoner_score':'investigator_score'] ?? null):null,
                'quorum'=>$s['evaluation']['quorum'] ?? null,'badges'=>array_values(array_filter($s['evaluation']['incidents'] ?? [],fn($i)=>$i['role']===$role)),
                'review'=>$old['review'] ?? null,'updated_at'=>gmdate('c')];
            if($review && $review['role']===$role)$entry['review']=array_intersect_key($review,array_flip(['rating','text']))+['at'=>gmdate('c')];
            $data[$key]=$entry;
        }
        labWrite($file,$data);
    }finally{flock($lock,LOCK_UN);fclose($lock);}
}

function labPerformance(array $data):array {
    $groups=[];$out=[];
    foreach($data as $entry)$groups[$entry['model']][$entry['role']][]=$entry;
    foreach($groups as $model=>$roles)foreach($roles as $role=>$items) {
        $scores=array_values(array_filter(array_column($items,'score'),'is_numeric'));
        $reviews=array_values(array_filter(array_column($items,'review'),'is_array'));
        $average=$scores?round(array_sum($scores)/count($scores),1):null;
        $user=$reviews?round(array_sum(array_column($reviews,'rating'))/count($reviews),2):null;
        $worst=min($average ?? 100,$user===null?100:$user*20);
        $band=$worst<40?'orange':($worst<65?'yellow':($average===null && $user===null?'unknown':'green'));
        usort($reviews,fn($a,$b)=>strcmp($b['at'],$a['at']));$badges=[];
        $contexts=[];
        foreach($items as $e)if(is_numeric($e['score'])) {
            $key=($e['level'] ?? 'unknown').':'.($e['rubric_version'] ?? 'legacy');
            $contexts[$key]['level']=$e['level'];$contexts[$key]['rubric_version']=$e['rubric_version'];
            $contexts[$key]['scores'][]=$e['score'];
        }
        foreach($contexts as &$context) {$context['count']=count($context['scores']);$context['average']=round(array_sum($context['scores'])/$context['count'],1);unset($context['scores']);}unset($context);
        foreach($items as $e)foreach(array_unique(array_column($e['badges'],'type')) as $type)$badges[$type]=1+($badges[$type] ?? 0);
        $out[$model][$role]=['assessments'=>count($scores),'judge_average'=>$average,'user_reviews'=>count($reviews),'user_average'=>$user,
            'quality_band'=>$band,'limited_sample'=>max(count($scores),count($reviews))<5,'badges'=>$badges,'contexts'=>array_values($contexts),'recent_reviews'=>array_slice($reviews,0,5)];
    }
    return $out;
}

function labSessionReviews(array $s):array {
    $data=labBenchmarkData();$out=[];
    foreach(labActorModels($s) as $role=>$model)if(!empty($data[$s['session_id'].':'.$role]['review']))$out[$role]=$data[$s['session_id'].':'.$role]['review'];
    return $out;
}
