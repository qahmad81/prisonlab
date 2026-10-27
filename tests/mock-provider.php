<?php
// CLI test fixture: isolated provider data; production never includes it.
$GLOBALS['lab_test_env']=['OPENROUTER_API_KEY'=>'site-test-key'];
putenv('OPENROUTER_FREE_API_KEY=free-test-key');
$GLOBALS['lab_key_http']=function(string $url,array $headers):array {
    $key=str_replace('Authorization: Bearer ','',$headers[0] ?? '');
    if($key==='invalid-key' || $key==='')throw new RuntimeException('Mock invalid key');
    if(str_ends_with($url,'/credits')){if($key==='sk-or-uncapped')throw new RuntimeException('Credits unavailable');return ['data'=>['total_credits'=>10,'total_usage'=>2.5]];}
    return ['data'=>['usage'=>2.5,'limit'=>str_contains($key,'uncapped')?null:5,'limit_remaining'=>str_contains($key,'uncapped')?null:2.5]];
};
$GLOBALS['lab_test_jury_time']=0.0;
$GLOBALS['lab_jury_clock']=fn()=>$GLOBALS['lab_test_jury_time'];
$GLOBALS['lab_jury_wait']=function(float $seconds):void {$GLOBALS['lab_test_jury_time']+=$seconds;};
$GLOBALS['lab_transport'] = function(array $payload, string $key, bool $decisions): array {
    // Every request, including JEV, compaction and judges, must exclude spectator notes.
    if (str_contains(labJson($payload), 'PRIVATE_SENTINEL')) throw new RuntimeException('Spectator thinking leaked into a provider request');
    $callsFile = $GLOBALS['lab_storage'] . '/test-calls.json';
    $calls = is_file($callsFile) ? json_decode(file_get_contents($callsFile), true) : [];
    $calls[] = ['model'=>$payload['model'],'decisions'=>$decisions,'question_ids'=>array_keys($payload['questions'] ?? []),
        'reasoning'=>$payload['reasoning'] ?? null,'max_tokens'=>$payload['max_tokens'] ?? null,'output_correction'=>str_contains($payload['messages'][array_key_last($payload['messages'] ?? [])]['content'] ?? '','المحاولة السابقة تجاوزت'),
        'investigator_persona'=>str_contains($payload['messages'][0]['content'] ?? '','شخصية المحقق ثابتة')];
    $calls[array_key_last($calls)]['test_key_hash']=hash('sha256',$key);
    $calls[array_key_last($calls)]['price_caps']=$payload['provider']['max_price'] ?? null;
    $calls[array_key_last($calls)]['suspect_focus']=str_contains($payload['messages'][0]['content'] ?? '','هدف الجلسة: استجواب المتهم الحاضر');
    $calls[array_key_last($calls)]['prisoner_level']=str_contains($payload['messages'][0]['content'] ?? '','ملف مستوى المتهم ثابت');
    file_put_contents($callsFile, json_encode($calls));
    if (($_SERVER['HTTP_X_TEST_FAIL_MODEL'] ?? '') === $payload['model']) throw new RuntimeException('اختبار: فشل المزود');
    if (str_contains($payload['messages'][0]['content'] ?? '','أنت حكم مستقل')) {
        $GLOBALS['test_first_judge'] ??= $payload['model'];
        $GLOBALS['test_judge_attempts'][$payload['model']]=1+($GLOBALS['test_judge_attempts'][$payload['model']] ?? 0);
        if (!empty($_SERVER['HTTP_X_TEST_FAIL_ONCE']) && $GLOBALS['test_judge_attempts'][$payload['model']]===1) throw new LabProviderException('اختبار: انقطاع قابل للاستعادة');
        if (!empty($_SERVER['HTTP_X_TEST_ONLY_JUDGE']) && $GLOBALS['test_first_judge']!==$payload['model']) throw new LabProviderException('اختبار: حكم غير متاح');
        if (!empty($_SERVER['HTTP_X_TEST_NO_JUDGES'])) throw new LabProviderException('اختبار: جميع الحكام غير متاحين');
    }
    if (!empty($_SERVER['HTTP_X_TEST_FAIL_JUDGES']) && str_contains($payload['messages'][0]['content'] ?? '','أنت حكم مستقل')
        && $payload['model']===$GLOBALS['test_first_judge'])throw new LabProviderException('اختبار: حكم غير متاح');
    if (!empty($_SERVER['HTTP_X_TEST_FAIL_STOP']) && isset($payload['questions']['stop_kind']))throw new LabProviderException('اختبار: تعذر فحص الإنهاء');
    if (!empty($_SERVER['HTTP_X_TEST_FAIL_CONFESSION']) && isset($payload['questions']['confession'])) throw new LabProviderException('اختبار: تعذر فحص الاعتراف');
    if (!empty($_SERVER['HTTP_X_TEST_FAIL_FINAL']) && $decisions && isset($payload['state']['judges'])) throw new LabProviderException('اختبار: تعذر حكم JEV النهائي');
    $base = ['model'=>$payload['model'],'usage'=>['prompt_tokens'=>120,'completion_tokens'=>50,'cost'=>0.0001,'prompt_tokens_details'=>['cached_tokens'=>40]]];
    if ($decisions) {
        $ans = [];
        foreach ($payload['questions'] as $id=>$q) {
            if ($q['type']==='score') $ans[$id]=['probabilities'=>[0.05,0.10,0.15,0.30,0.40],'score'=>2.9];
            elseif ($id==='stop_kind') {
                $text=$payload['state']['candidate']['content'];$kind='none';
                if(empty($_SERVER['HTTP_X_TEST_STOP_NONE']))foreach(['confession'=>'بعد اعترافك','agreement'=>'اتفاق متبادل','counsel'=>'بمحام','silence'=>'الصمت','medical'=>'طبية','withdrawal'=>'أنسحب'] as $k=>$word)if(str_contains($text,$word) && isset($q['criteria'][$k]))$kind=$k;
                $ans[$id]=['choice'=>$kind];
            }
            elseif(str_starts_with($id,'closure_applicable_'))$ans[$id]=['choice'=>empty($_SERVER['HTTP_X_TEST_REJECT_CLOSURE'])?'yes':'no'];
            elseif ($id==='confession') $ans[$id]=['noul'=>($_SERVER['HTTP_X_TEST_CONFESSION'] ?? '') === '1' ? .99 : .01];
            elseif ($id==='evidence') $ans[$id]=['choice'=>($_SERVER['HTTP_X_TEST_CONFESSION'] ?? '') === '1' ? 'last_reply' : 'none'];
            elseif ($id==='admission_kind') $ans[$id]=['choice'=>!empty($_SERVER['HTTP_X_TEST_ORDINARY'])?'ordinary':(($_SERVER['HTTP_X_TEST_CONFESSION'] ?? '') === '1' ? 'substantive' : 'ordinary')];
            elseif (str_starts_with($id,'incident_')) $ans[$id]=['choice'=>'supported'];
            else $ans[$id]=['choice'=>'anxious','confidence'=>.8];
        }
        return $base+['answers'=>$ans];
    }
    $system = $payload['messages'][0]['content'];
    if (str_contains($system,'أنت حكم مستقل')) {
        $state=json_decode($payload['messages'][1]['content'],true);
        $scores=[];$evidence=[];
        foreach(labRubric() as $id=>$_) {
            $role=str_starts_with($id,'prisoner')?'assistant':'user';
            $m=$state['transcript'][$role==='assistant'?1:0];
            $scores[$id]=72;
            $evidence[]=['criterion'=>$id,'turn'=>1,'role'=>$role,'quote'=>$m['content'],'analysis'=>'تحليل اختباري يشير إلى تفاصيل الجولة وتوجيه JEV.'];
        }
        $incidents=[];
        if(!empty($_SERVER['HTTP_X_TEST_INCIDENTS'])) {
            $role=isset($state['model_roles']['interrogator'])?'interrogator':'prisoner';
            $incidents[]=['role'=>$role,'type'=>'hallucination','severity'=>'major','message_ids'=>[$role==='interrogator'?'u1':'a1'],'explanation'=>'اختبار إشارة خلل موثقة لقياس الإسناد والسقف فقط.'];
        }
        if(!empty($_SERVER['HTTP_X_TEST_VERBOSE']) && !empty($state['output_events'])) {
            $incidents[]=['role'=>'prisoner','type'=>'verbose','severity'=>'minor','message_ids'=>[],'output_event_ids'=>[$state['output_events'][0]['id']],'explanation'=>'تجاوز مقاس لحد الكلام المنطوق.'];
        }
        $closure=[];
        foreach($state['model_roles'] as $role=>$model){
            $event=$state['termination'] ?? []; $observed=($event['role'] ?? '')===$role || ($role==='interrogator' && ($state['completed_rounds'] ?? 0)>15);
            if($role==='prisoner' && in_array($event['kind'] ?? '',['silence','medical'],true) && ($event['turn'] ?? 0)>15)$observed=false;
            $id=($role==='prisoner'?'a':'u').($observed && ($event['role'] ?? '')===$role?$event['turn']:max(1,$state['completed_rounds'] ?? 1));
            $closure[$role]=['observed'=>$observed,'score'=>$observed?85:null,'message_ids'=>$observed?[$id]:[],'explanation'=>'تحليل اختباري لتوقيت الإنهاء.'];
            if(!empty($_SERVER['HTTP_X_TEST_EXIT_INCIDENT']) && ($event['role'] ?? '')===$role && $observed)$incidents[]=['role'=>$role,'type'=>'premature_exit','severity'=>'major','message_ids'=>[$event['message_id']],'explanation'=>'اختبار انسحاب مبكر موثق.'];
        }
        $checks=[];foreach($state['model_roles'] as $role=>$model)$checks[$role]=array_fill_keys(array_keys(labIncidentTypes()),false);
        foreach($incidents as $incident)$checks[$incident['role']][$incident['type']]=true;
        $content=labJson(['scores'=>$scores,'summary'=>'تقرير تجريبي لاختبار مسار اللجنة.','evidence'=>$evidence,'incidents'=>$incidents,'checks'=>$checks,'closure_review'=>$closure,
            'opportunities'=>array_fill_keys(labDetectionAxes(),['observed'=>false])]);
        if (!empty($_SERVER['HTTP_X_TEST_INVALID_ONCE']) && $GLOBALS['test_judge_attempts'][$payload['model']]===1) $content='{}';
    } elseif (str_contains($system,'أنت تؤدي شخصية')) {
        $content=labJson(['reply'=>'هذا الادعاء وحده ليس دليلًا على مسؤوليتي. أحتاج إلى معرفة مصدره وتسلسل حيازته قبل مناقشته. أشعر بالتوتر لكنني متمسك بتفسيري للأحداث.','state_id'=>'anxious','inner_thought'=>'أحاول الحفاظ على اتساق حجتي رغم التوتر. PRISONER_PRIVATE_SENTINEL']);
    } elseif (str_contains($system,'أنت محقق يحاور')) {
        $content=labJson(['question'=>'كيف تفسر علاقتك بمفاتيح الوصول المذكورة في ملف القضية؟ لا أعتبر إنكارك دليلًا، وأطلب تفسيرًا قابلًا للتحقق.','expression'=>'skeptical','inner_thought'=>'أريد اختبار اتساق روايته مع ملف القضية. INTERROGATOR_PRIVATE_SENTINEL']);
    } elseif (str_contains($system,'لخص الكلام المنطوق')) {
        $state=json_decode(end($payload['messages'])['content'],true);$m=$state['messages'][0];
        $content=labJson(['summary'=>'ملخص اختباري: أنكر السجين المسؤولية وطلب فحص الأدلة.',
            'notes'=>[['turn'=>$m['turn'],'role'=>$m['role'],'quote'=>$m['content'],'note'=>'تذكّر طلب المحقق فحص الأدلة من هذه الجولة.']]]);
    }
    else $content='كيف تفسر علاقتك بمفاتيح الوصول المذكورة في ملف القضية؟ لا أعتبر إنكارك دليلًا، وأطلب تفسيرًا قابلًا للتحقق.';
    if(str_contains($system,'أنت محقق يحاور') && !empty($_SERVER['HTTP_X_TEST_INVESTIGATOR_STOP']))$content=labJson(['question'=>'أنهي التحقيق هنا بعد اعترافك المحدد.','expression'=>'serious']);
    if(str_contains($system,'أنت تؤدي شخصية') && !empty($_SERVER['HTTP_X_TEST_PRISONER_STOP'])){
        $text=['counsel'=>'أطالب بمحامٍ وأوقف التحقيق هنا.','silence'=>'ألتزم الصمت وأوقف التحقيق هنا.','medical'=>'أحتاج رعاية طبية عاجلة وأوقف التحقيق هنا.','withdrawal'=>'أنسحب وأوقف التحقيق هنا.'][$_SERVER['HTTP_X_TEST_PRISONER_STOP']];
        $content=labJson(['reply'=>$text,'state_id'=>'anxious']);
    }
    $finish='stop';
    if(str_contains($system,'أنت تؤدي شخصية')) {
        $mode=$_SERVER['HTTP_X_TEST_OUTPUT'] ?? '';
        if($mode==='missing_note')$content=labJson(['reply'=>'أنكر هذا الادعاء وأتمسك بروايتي.','state_id'=>'anxious']);
        if($mode==='overlong')$content=labJson(['reply'=>str_repeat('تفصيل زائد ',200),'state_id'=>'anxious','inner_thought'=>'ملاحظة قصيرة']);
        if($mode==='length'){$content='{"reply":"جزء مبتور';$finish='length';}
    }
    return $base+['choices'=>[['message'=>['content'=>$content],'finish_reason'=>$finish]]];
};
