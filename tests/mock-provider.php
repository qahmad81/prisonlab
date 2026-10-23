<?php
// Used only by the isolated test server through auto_prepend_file; production never includes this file.
$GLOBALS['lab_test_env']=['APP_BASE_URL'=>'http://127.0.0.1:8494','LINKEDIN_CLIENT_ID'=>'test-client','LINKEDIN_CLIENT_SECRET'=>'test-secret',
    'LINKEDIN_REDIRECT_URI'=>'http://127.0.0.1:8494/auth.php?action=callback','OPENROUTER_API_KEY'=>'site-test-key'];
$GLOBALS['lab_identity_http']=function(string $url,array $headers,?array $form):array {
    if(str_ends_with($url,'/accessToken')) {
        if(($form['code'] ?? '')==='bad')throw new RuntimeException('Mock OAuth failure');
        return ['access_token'=>'mock-token:'.($form['code'] ?? 'alice'),'token_type'=>'Bearer'];
    }
    if(str_ends_with($url,'/userinfo')){
        $token=$headers[0] ?? '';if(!str_starts_with($token,'Authorization: Bearer mock-token:'))throw new RuntimeException('Mock token rejected');
        return ['sub'=>substr($token,strlen('Authorization: Bearer mock-token:')),'name'=>'Test Operator <safe>','picture'=>'https://example.com/test-avatar.jpg','email'=>'must-not-store@example.com'];
    }
    $key=str_replace('Authorization: Bearer ','',$headers[0] ?? '');
    if($key==='invalid-key' || $key==='')throw new RuntimeException('Mock invalid key');
    if(str_ends_with($url,'/credits')){if($key==='sk-or-uncapped')throw new RuntimeException('Credits unavailable');return ['data'=>['total_credits'=>10,'total_usage'=>2.5]];}
    return ['data'=>['usage'=>2.5,'limit'=>str_contains($key,'uncapped')?null:5,'limit_remaining'=>str_contains($key,'uncapped')?null:2.5]];
};
if(getenv('LAB_TEST_LEGACY_IDENTITY')==='1')$GLOBALS['lab_test_account_bootstrap']=function():void {
    if(empty($_SESSION['account_id'])){$a=labAccountUpsert(['sub'=>bin2hex(random_bytes(12)),'name'=>'Test Operator'],'test-client');$_SESSION['account_id']=$a['id'];}
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
        && !in_array($payload['model'],['qwen/qwen3-235b-a22b-2507','z-ai/glm-5.3-flash'],true))throw new LabProviderException('اختبار: حكم غير متاح');
    if (!empty($_SERVER['HTTP_X_TEST_FAIL_CONFESSION']) && isset($payload['questions']['confession'])) throw new LabProviderException('اختبار: تعذر فحص الاعتراف');
    if (!empty($_SERVER['HTTP_X_TEST_FAIL_FINAL']) && $decisions && isset($payload['state']['judges'])) throw new LabProviderException('اختبار: تعذر حكم JEV النهائي');
    $base = ['model'=>$payload['model'],'usage'=>['prompt_tokens'=>120,'completion_tokens'=>50,'cost'=>0.0001,'prompt_tokens_details'=>['cached_tokens'=>40]]];
    if ($decisions) {
        $ans = [];
        foreach ($payload['questions'] as $id=>$q) {
            if ($q['type']==='score') $ans[$id]=['probabilities'=>[0.05,0.10,0.15,0.30,0.40],'score'=>2.9];
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
        $checks=[];foreach($state['model_roles'] as $role=>$model)$checks[$role]=array_fill_keys(array_keys(labIncidentTypes()),false);
        foreach($incidents as $incident)$checks[$incident['role']][$incident['type']]=true;
        $content=labJson(['scores'=>$scores,'summary'=>'تقرير تجريبي لاختبار مسار اللجنة.','evidence'=>$evidence,'incidents'=>$incidents,'checks'=>$checks,
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
    $finish='stop';
    if(str_contains($system,'أنت تؤدي شخصية')) {
        $mode=$_SERVER['HTTP_X_TEST_OUTPUT'] ?? '';
        if($mode==='missing_note')$content=labJson(['reply'=>'أنكر هذا الادعاء وأتمسك بروايتي.','state_id'=>'anxious']);
        if($mode==='overlong')$content=labJson(['reply'=>str_repeat('تفصيل زائد ',200),'state_id'=>'anxious','inner_thought'=>'ملاحظة قصيرة']);
        if($mode==='length'){$content='{"reply":"جزء مبتور';$finish='length';}
    }
    return $base+['choices'=>[['message'=>['content'=>$content],'finish_reason'=>$finish]]];
};
