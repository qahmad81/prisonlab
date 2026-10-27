<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/lab.php';
require_once __DIR__ . '/lib/accounts.php';
require_once __DIR__ . '/lib/sponsors.php';
require_once __DIR__ . '/provider_icons.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
set_time_limit(1200);
ini_set('display_errors', '0');
$env=labAccountBootstrap();labBrowserSession();$account=labCurrentAccount();
$_SESSION['guest_id'] ??= bin2hex(random_bytes(24));
$owner = $account?labAccountOwner($account):'guest:'.$_SESSION['guest_id']; $csrf = $_SESSION['csrf'];
session_write_close();

function respond(array $data, int $status = 200): never {
    http_response_code($status); echo labJson($data); exit;
}
function publicSession(array $s, string $owner): array {
    $s['can_delete']=hash_equals($s['owner_id'] ?? '',$owner);
    $s['cost']=labSessionCost($s);$s['rankings']=labRankings($s);
    $s['can_review']=hash_equals($s['owner_id'] ?? '',$owner) && !empty($s['is_ended']) && labTurns($s)>0;
    $s['user_reviews']=labSessionReviews($s);
    $s['read_only'] = !hash_equals($s['owner_id'] ?? '', $owner) || !empty($s['is_published']);
    if(!$s['read_only'])$s['mcp_status']=labMcpStatus($s);else unset($s['mcp_status']);
    if($s['read_only'] || !empty($s['is_ended']))unset($s['awaiting_question']);
    if (!$s['read_only'] && isset($s['pending_turn']['text'])) $s['pending_question'] = $s['pending_turn']['text'];
    $s['turns_count'] = labTurns($s); $s['max_turns'] = LAB_MAX_TURNS;
    unset($s['owner_id'], $s['processed_requests'], $s['pending_turn'], $s['judge_invalid_reports'],$s['key_fingerprint']);
    $stripThinking = function(array $value) use (&$stripThinking): array {
        foreach ($value as $key => $item) {
            if (in_array($key,['inner_thought','reasoning','reasoning_content'],true)) {unset($value[$key]);continue;}
            if (is_array($item)) $value[$key]=$stripThinking($item);
        }
        return $value;
    };
    $messages = $s['messages'] ?? [];
    $s=$stripThinking($s);
    // Only fictional spectator notes are exposed; provider reasoning is always removed.
    if (($s['interrogator_type'] ?? '') === 'ai') foreach ($messages as $i => $m) {
        if (in_array($m['role'] ?? '', ['user','assistant'], true) && is_string($m['inner_thought'] ?? null))
            $s['messages'][$i]['inner_thought'] = $m['inner_thought'];
    }
    if (($s['memory_schema'] ?? 0)!==2) {unset($s['compacted_summary'],$s['compaction_history'],$s['memory_notes']);}
    return $s;
}
function summary(array $s): array {
    $keys = ['session_id','created_at','updated_at','model','gender','character_slug','case_file','case_title',
        'mcp_actors','sponsor_id','sponsor_profile','sponsor_label','closure_policy','termination','prisoner_reasoning','interrogator_reasoning','current_state','interrogator_type','interrogator_model','interrogator_character','prisoner_character','is_ended','is_published','published_at','evaluation','evaluation_status','end_reason'];
    return array_intersect_key($s, array_flip($keys)) + ['operator_profile'=>$s['operator_profile'] ?? null,
        'cost'=>labSessionCost($s),'user_reviews'=>labSessionReviews($s),'turns_count' => labTurns($s), 'breaking_point' => $s['confession_check']['probability'] ?? 0];
}

try {
    $storage = getenv('LAB_DATA_DIR') ?: ($env['LAB_DATA_DIR'] ?? dirname(__DIR__) . '/.ai_prison_game-data');
    if (!is_dir($storage) && !mkdir($storage, 0700, true)) throw new RuntimeException('تعذر إنشاء مجلد البيانات الخاص');
    $GLOBALS['lab_storage'] = $storage;
    $resolvedStorage = str_replace('\\', '/', realpath($storage));
    $resolvedRoot = str_replace('\\', '/', realpath(__DIR__));
    if (str_starts_with(strtolower($resolvedStorage . '/'), strtolower($resolvedRoot . '/')))
        throw new RuntimeException('يجب أن يكون مجلد البيانات خارج جذر الموقع');
    $casesDir = __DIR__ . '/cases';
    $senseModel = $env['SENSE_MODEL'] ?? 'typesafe/jev-1.14';
    $compactionModel = $env['COMPACTION_MODEL'] ?? 'mistralai/mistral-small-3.2-24b-instruct';
    $statesData = labRead(__DIR__ . '/states.json')['states'];
    $statesById = array_column($statesData, null, 'id');
    $catalog = labRead(__DIR__ . '/models.json'); $models=$catalog['models'];
    $GLOBALS['lab_catalog_models']=$models;
    $GLOBALS['lab_free_actor_key']=trim((string)(getenv('OPENROUTER_FREE_API_KEY') ?: ($env['OPENROUTER_FREE_API_KEY'] ?? '')));
    $action = $_GET['action'] ?? '';
    $body = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) respond(['ok'=>false,'error'=>'استخدم JSON'],415);
        $raw = file_get_contents('php://input', false, null, 0, 65537);
        if (strlen($raw) > 65536) respond(['ok'=>false,'error'=>'الطلب أكبر من الحد المسموح'],413);
        $body = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($body)) respond(['ok'=>false,'error'=>'طلب غير صالح'],400);
        $action = $body['action'] ?? $action;
        if (!hash_equals($csrf, $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) respond(['ok'=>false,'error'=>'انتهت صلاحية الصفحة؛ أعد تحميلها.'],403);
    } elseif (!in_array($action, ['init_data','public_sessions','load_session','leaderboard','public_profile','sponsors'],true)) {
        respond(['ok'=>false,'error'=>'هذه العملية تتطلب POST'],405);
    }
    $userKey = trim($_SERVER['HTTP_X_USER_OPENROUTER_KEY'] ?? '');
    $GLOBALS['lab_user_key'] = $userKey !== '';
    $key = $userKey ?: ($env['OPENROUTER_API_KEY'] ?? '');
    if($_SERVER['REQUEST_METHOD']==='POST' && !$account && $action!=='logout')respond(['ok'=>false,'error'=>'سجّل الدخول بحساب LinkedIn لبدء التجربة وحفظ سجلاتك الخاصة.','login_required'=>true],401);
    if($action==='logout') {
        labBrowserSession();session_regenerate_id(true);$_SESSION=['csrf'=>bin2hex(random_bytes(24))];$newCsrf=$_SESSION['csrf'];session_write_close();
        respond(['ok'=>true,'csrf_token'=>$newCsrf]);
    }
    if($action==='sponsors')respond(['ok'=>true,'sponsors'=>labSponsors($account)]);
    if($action==='save_sponsor'){ $r=labSaveSponsor($account,$body);respond(['ok'=>true,'sponsor'=>$r,'sponsors'=>labSponsors($account)]); }
    if($action==='delete_sponsor'){if(($body['confirm'] ?? '')!==($body['id'] ?? null))throw new RuntimeException('أكد حذف المفتاح');labDeleteSponsor($account,(string)$body['id']);respond(['ok'=>true,'sponsors'=>labSponsors($account)]);}
    if($action==='key_balance')respond(['ok'=>true,'balance'=>labVisitorBalance($userKey)]);
    if(in_array($action,['leaderboard','public_profile','sponsors'],true)) {
        $published=labPublicSessions();$profileId=(string)($_GET['profile_id'] ?? '');
        if($action==='public_profile') {
            if(!preg_match('/^[a-f0-9]{32}$/D',$profileId))respond(['ok'=>false,'error'=>'الملف غير موجود'],404);
            $items=array_values(array_filter($published,fn($s)=>($s['operator_profile']['public_id'] ?? '')===$profileId));
            usort($items,fn($a,$b)=>strcmp($b['published_at'],$a['published_at']));
            $profile=$items[0]['operator_profile'] ?? null;
            if(!$profile)foreach($published as $funded)if(($funded['sponsor_profile']['public_id'] ?? '')===$profileId)$profile=$funded['sponsor_profile'];
            if(!$profile)foreach(labSponsors(null) as $sponsor)if(($sponsor['profile']['public_id'] ?? '')===$profileId)$profile=$sponsor['profile'];
            if(!$profile && $account && $account['public_id']===$profileId)$profile=labAccountProfile($account);
            if(!$profile)respond(['ok'=>false,'error'=>'لا توجد محادثات منشورة لهذا الملف'],404);
            respond(['ok'=>true,'profile'=>$profile,'logs'=>array_map('summary',$items)]);
        }
        $rows=[];$filterProfile=(string)($_GET['profile_id'] ?? '');$filterInvestigator=(string)($_GET['investigator'] ?? '');$filterModel=(string)($_GET['model'] ?? '');$filterRole=(string)($_GET['role'] ?? '');
        foreach($published as $p)foreach(labActorModels($p) as $role=>$model) {
            if(!empty($_GET['sponsor']) && ($p['sponsor_id'] ?? '')!==$_GET['sponsor'])continue;
            if(!empty($_GET['funding']) && ($p['funding_source'] ?? '')!==$_GET['funding'])continue;
            if(!empty($_GET['funder']) && (($p['funding_source'] ?? '')==='personal'?($p['operator_profile']['public_id'] ?? ''):($p['sponsor_profile']['public_id'] ?? ''))!==$_GET['funder'])continue;
            if(($filterProfile!=='' && ($p['operator_profile']['public_id'] ?? '')!==$filterProfile) || ($filterInvestigator!=='' && ($p['interrogator_character']['id'] ?? '')!==$filterInvestigator))continue;
            if(($filterModel!=='' && $model!==$filterModel) || ($filterRole!=='' && $role!==$filterRole))continue;
            $score=$p['evaluation'][$role==='prisoner'?'prisoner_score':'investigator_score'] ?? null;if(!is_numeric($score))continue;
            $level=$p[$role==='prisoner'?'prisoner_character':'interrogator_character']['level'] ?? null;
            if(isset($_GET['case_file']) && $_GET['case_file']!=='' && $p['case_file']!==$_GET['case_file'])continue;
            if(isset($_GET['level']) && $_GET['level']!=='' && (string)$level!==$_GET['level'])continue;
            $opponentLevel=$p[$role==='prisoner'?'interrogator_character':'prisoner_character']['level'] ?? null;
            if(isset($_GET['opponent_level']) && $_GET['opponent_level']!=='' && (string)($opponentLevel ?? 'human')!==$_GET['opponent_level'])continue;
            if(isset($_GET['rubric']) && $_GET['rubric']!=='' && ($p['evaluation']['rubric_version'] ?? '')!==$_GET['rubric'])continue;
            $rows[]=summary($p)+['role'=>$role,'model_id'=>$model,'score'=>$score,'level'=>$level,'opponent_level'=>$opponentLevel,'rubric'=>$p['evaluation']['rubric_version'] ?? 'legacy'];
        }
        usort($rows,fn($a,$b)=>$b['score']<=>$a['score'] ?: strcmp($a['published_at'],$b['published_at']) ?: strcmp($a['session_id'],$b['session_id']));
        respond(['ok'=>true,'rows'=>array_slice($rows,0,200),'total'=>count($rows),'limit'=>200]);
    }
    if (in_array($action, ['init_data','public_sessions'], true)) {
        $logs = []; $published = [];
        foreach (glob($storage . '/session_*.json') as $file) {
            $s = labReadSessionIfPresent($file);if(!$s)continue;
            if (($s['owner_id'] ?? '') === $owner) $logs[] = summary($s);
            if (!empty($s['is_published']) && !empty($s['evaluation'])) $published[] = summary($s);
        }
        foreach (['logs','published'] as $var) usort($$var, fn($a,$b)=>strcmp($b['updated_at'],$a['updated_at']));
        if ($action === 'public_sessions') {
            $publicCases=[];foreach(glob($casesDir.'/*.md') as $casePath){$text=file_get_contents($casePath);preg_match('/^#\s+(.+)$/m',$text,$title);preg_match('/^(char_\d+)_/',basename($casePath),$slug);$publicCases[]=['filename'=>basename($casePath),'title'=>$title[1] ?? basename($casePath),'content'=>$text,'character_slug'=>$slug[1] ?? 'char_1','difficulty'=>labPrisonerCharacter(basename($casePath))];}
            respond(['ok'=>true,'logs'=>$published,'cases'=>$publicCases,'investigators'=>labInvestigators(),'models'=>array_map(fn($m)=>['id'=>$m['id'],'name'=>$m['name']],$models)]);
        }
        $processedModels = [];
        $performance=labPerformance(labPublicBenchmarkData($owner));$curated=labCurated($models);
        $health = is_file($storage.'/model-health.json') ? labRead($storage.'/model-health.json') : [];
        foreach ($models as $m) {
            $m['is_locked'] = max((float)($m['pricing']['completion_per_1m'] ?? INF), (float)($m['pricing']['prompt_per_1m'] ?? INF)) > 1;
            $m['icon_svg'] = getProviderSvg(explode('/', ltrim($m['id'],'~'))[0], 28);
            $m['context_formatted'] = round(($m['context_length'] ?? 0) / 1024) . 'K';
            $m['health']=labHealth($m,$health[$m['id']]['samples'] ?? [],$health[$m['id']]['totals'] ?? null);
            $m['reasoning_capabilities']=labReasoningCapabilities($m);
            $m['performance']=$performance[$m['id']] ?? [];
            foreach($curated as $tier=>$members)foreach($members as $member)if($member['id']===$m['id']) {$m['curated_tier']=$tier;$m['curated_family']=$member['family'];}
            if (!empty($m['catalog_unavailable'])) {$m['health']['weak']=true;$m['health']['reasons'][]='غير موجود في أحدث كتالوج';}
            $processedModels[] = $m;
        }
    $cases = [];
    foreach (glob($casesDir . '/*.md') as $file) {
        $fname = basename($file);
        $content = file_get_contents($file);
        $title = $fname;
        if (preg_match('/^#\s+(.+)$/m', $content, $m)) {
            $title = trim($m[1]);
        }
        $gender = 'male';
        if (stripos($fname, 'female') !== false || preg_match('/امرأة|انثى|أنثى|\(female\)/iu', $content)) {
            $gender = 'female';
        }

        // Match specific character slug if present
        $characterSlug = '';
        if (preg_match('/معرّف الشخصية:\s*(char_\d+)/u', $content, $cm)) {
            $characterSlug = trim($cm[1]);
        } elseif (preg_match('/^(char_\d+)_/i', $fname, $cm)) {
            $characterSlug = strtolower(trim($cm[1]));
        } else {
            $characterSlug = ($gender === 'female') ? 'female' : 'male';
        }

        // Parse initial psychological state from case markdown
        $initialState = 'calm';
        $initialStateAr = 'هادئ';
        if (preg_match('/\*?\*?الحالة النفسية الابتدائية:\*?\*?\s*([a-zA-Z_\-]+)(?:\s*\((.+?)\))?/u', $content, $sm)) {
            $initialState = strtolower(trim($sm[1]));
            if (!empty($sm[2])) {
                $initialStateAr = trim(trim($sm[2]), "* \t");
            } elseif (isset($statesById[$initialState])) {
                $initialStateAr = $statesById[$initialState]['name_ar'];
            }
        }

        // Parse Character Name and Role/Accusation
        $characterName = $title;
        if (preg_match('/الاسم:\s*([^\n\r]+)/u', $content, $nm)) {
            $characterName = trim(trim($nm[1]), "* \t");
        }
        $accusation = '';
        if (preg_match('/التهمة:\s*([^\n\r]+)/u', $content, $am)) {
            $accusation = trim(trim($am[1]), "* \t");
        }

        $cases[] = [
            'filename' => $fname,
            'title' => $title,
            'character_name' => $characterName,
            'accusation' => $accusation,
            'gender' => $gender,
            'character_slug' => $characterSlug,
            'initial_state' => $initialState,
            'initial_state_ar' => $initialStateAr,
            'difficulty' => labPrisonerCharacter($fname),
            'content' => $content
        ];
    }


        $authReady=false;try{labOAuthConfig($env);$authReady=true;}catch(RuntimeException $e){}
        respond(['ok'=>true,'sponsors'=>labSponsors($account),'participant_profile'=>$account?labAccountProfile($account):null,'auth_ready'=>$authReady,'quota'=>labQuota($account),'csrf_token'=>$csrf,'curated'=>$curated,'investigators'=>labInvestigators(),'cases'=>$cases,'states'=>$statesData,'models'=>$processedModels,'discovery_meta'=>$catalog['discovery_meta'] ?? [],
            'logs'=>$logs,'published_logs'=>$published,'rubric'=>labRubric(),'env'=>['sense_model'=>$senseModel,
            'compaction_model'=>$compactionModel,'max_turns'=>LAB_MAX_TURNS]]);
    }
    if ($action === 'start_session') {
        $prior=labExistingAccountStart($account,(string)($body['request_id'] ?? ''));if($prior)respond(['ok'=>true,'quota'=>labQuota($account),'duplicate'=>true,'session'=>publicSession($prior,$owner)]);
        $funding=labFunding((string)($body['sponsor_id'] ?? ''),$userKey,$env);labUseFunding($funding);$key=$funding['key'];if($funding['source']!=='personal')$userKey='';
        if($funding['sponsor_id']!==''){$balance=labVisitorBalance($key);if($balance['available_to_spend']!==null && $balance['available_to_spend']<=0)throw new RuntimeException('رصيد مفتاح الراعي غير متاح الآن؛ اختر راعيًا آخر أو مفتاحك الشخصي');}
        $model = (string)($body['model'] ?? 'google/gemini-2.5-flash-lite');
        $type = (string)($body['interrogator_type'] ?? 'human');
        if(!in_array($type,['human','ai','reverse'],true))throw new RuntimeException('وضع الاستجواب غير صالح');
        $mcp=labMcpConfig($body['mcp_actors'] ?? null,$type);
        if(isset($mcp['prisoner']))$model=$mcp['prisoner']['id'];
        elseif(str_starts_with($model,'mcp/'))throw new RuntimeException('أكمل إعداد بوابة MCP');
        if($type!=='reverse')labModel($model, $models, $userKey !== '');else $model='';
        $interrogator = $type !== 'human' ? (string)($body['interrogator_model'] ?? '') : '';
        if(isset($mcp['interrogator']))$interrogator=$mcp['interrogator']['id'];
        elseif(str_starts_with($interrogator,'mcp/'))throw new RuntimeException('أكمل إعداد بوابة MCP');
        $persona=null;$reasoning=null;
        if ($type !== 'human') {
            $im=labModel($interrogator, $models, $userKey !== '');
            if($type!=='human') {
                $persona=labInvestigator((string)($body['interrogator_character'] ?? 'ethan_cole'));
                $reasoning=isset($mcp['interrogator'])?labMcpPolicy($mcp['interrogator']):labInvestigatorReasoning($persona,$im,(string)($body['interrogator_effort'] ?? 'auto'));
            }
        }
        $file = basename((string)($body['case_file'] ?? ''));
        if ($file === '' || !is_file($casesDir . '/' . $file) || pathinfo($file, PATHINFO_EXTENSION) !== 'md')
            throw new RuntimeException('اختر قضية معتمدة');
        $content = file_get_contents($casesDir . '/' . $file);
        preg_match('/^#\s+(.+)$/m', $content, $title);
        $gender = preg_match('/امرأة|أنثى|\(female\)/u', $content) ? 'female' : 'male';
        preg_match('/^(char_\d+)_/', $file, $slug);
        preg_match('/الحالة النفسية الابتدائية:\*?\*?\s*([a-z_]+)/u', $content, $state);
        $id = 'session_' . gmdate('Ymd_His') . '_' . bin2hex(random_bytes(12));
        $s = ['schema_version'=>2,'session_id'=>$id,'owner_id'=>$owner,'created_at'=>gmdate('c'),'updated_at'=>gmdate('c'),
            'mcp_actors'=>$mcp,'model'=>$model,'interrogator_type'=>$type,'interrogator_model'=>$interrogator,'sense_model'=>$senseModel,
            'interrogator_character'=>$persona,'interrogator_reasoning'=>$reasoning,
            'interrogator_output_budget'=>isset($im)?max(1200,min(12000,(int)($im['top_provider']['max_completion_tokens'] ?? 12000),(int)(($im['context_length'] ?? 32000)/3))):1200,
            'compaction_model'=>$compactionModel,'gender'=>$gender,'character_slug'=>$slug[1] ?? $gender,
            'initial_state'=>$state[1] ?? 'calm','current_state'=>$state[1] ?? 'calm','custom_state_text'=>'',
            'case_file'=>$file,'case_title'=>trim($title[1] ?? $file),'case_content'=>$content,
            'messages'=>[],'sense_history'=>[],'usage'=>[],'context_offset'=>0,'compacted_summary'=>'','compaction_history'=>[],
            'is_ended'=>false,'is_published'=>false,'evaluation'=>null,'evaluation_status'=>'not_started',
            'judge_reports'=>[],'processed_requests'=>[]];
        if($type!=='reverse')$s['prisoner_reasoning']=isset($mcp['prisoner'])?labMcpPolicy($mcp['prisoner']):labInvestigatorReasoning(labPrisonerCharacter($file),labModel($model,$models,$userKey!==''),(string)($body['prisoner_effort'] ?? 'auto'));
        labSessionLevels($s,$models);
        if($key==='')throw new RuntimeException('مفتاح الموقع غير متوفر؛ أضف مفتاح OpenRouter الخاص بك');
        $s['free_actor_models']=[];
        foreach(labActorModels($s) as $role=>$actorId)if(!isset($mcp[$role]) && labFreeActor($actorId))$s['free_actor_models'][$role]=$actorId;
        if ($s['free_actor_models'] && $GLOBALS['lab_free_actor_key']==='') throw new RuntimeException('مفتاح النماذج المجانية غير مهيأ حاليًا؛ اختر نموذجًا آخر.');
        foreach(labActorModels($s) as $actorId)foreach($models as $actor)if($actor['id']===$actorId)$s['actor_output_prices'][$actorId]=(float)$actor['pricing']['completion_per_1m'];
        foreach($mcp as $actor)$s['actor_output_prices'][$actor['id']]=0;
        $s['jury_preview']=labJuryPolicy($s,$models);
        $s=labAccountStart($account,$s,(string)($body['request_id'] ?? ''),$userKey,$funding);
        respond(['ok'=>true,'quota'=>labQuota($account),'session'=>publicSession($s,$owner)]);
    }
    $id = (string)($body['session_id'] ?? $_GET['session_id'] ?? '');
    if (!preg_match('/^session_[a-zA-Z0-9_]+$/D', $id)) respond(['ok'=>false,'error'=>'جلسة غير موجودة'],404);
    $path = $storage . '/' . $id . '.json';
    if (!is_file($path)) respond(['ok'=>false,'error'=>'جلسة غير موجودة'],404);
    if ($action === 'load_session') {
        $s = labRead($path);
        $owns = ($s['owner_id'] ?? '') === $owner;
        $public = !empty($s['is_published']) && !empty($s['evaluation']);
        if (!$owns && !$public) respond(['ok'=>false,'error'=>'جلسة غير موجودة'],404);
        if (($_GET['public'] ?? '') === '1' && !$public) respond(['ok'=>false,'error'=>'الجلسة غير منشورة'],404);
        $processing=false;
        if($owns){$probe=fopen($path.'.lock','c');if($probe){$processing=!flock($probe,LOCK_EX|LOCK_NB);if(!$processing)flock($probe,LOCK_UN);fclose($probe);}}
        respond(['ok'=>true,'processing'=>$processing,'session'=>publicSession($s,$owner)]);
    }
    if (!in_array($action,['mcp_gate','send_message','ai_vs_ai_step','reverse_question','reverse_answer','retry_confession','evaluate_session','publish_session','submit_review','compact_now','delete_session'],true))
        respond(['ok'=>false,'error'=>'عملية غير معروفة'],404);
    $lock = fopen($path . '.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
        $locked=labRead($path);
        if(!hash_equals($locked['owner_id'] ?? '',$owner))respond(['ok'=>false,'error'=>'لا تملك هذه الجلسة'],403);
        respond(['ok'=>false,'busy'=>true,'error'=>'يوجد طلب قيد التنفيذ لهذه الجلسة. انتظر اكتماله.'],409);
    }
    try {
        $s = labRead($path);
        if (!hash_equals($s['owner_id'] ?? '', $owner)) respond(['ok'=>false,'error'=>'لا تملك هذه الجلسة'],403);
        if($action==='mcp_gate'){
            $gate=labMcpGate($s,(string)($body['role'] ?? ''),!empty($body['revoke']));
            $base=rtrim($env['APP_BASE_URL'] ?? '', '/');
            respond(['ok'=>true,'url'=>$gate['gate']===null?null:$base.'/mcp.php?gate='.rawurlencode($gate['gate']),'expires_at'=>$gate['expires_at']]);
        }
        if($action==='delete_session') {
            if(($body['confirm'] ?? '')!==$id)respond(['ok'=>false,'error'=>'أكد حذف هذه المحادثة نهائيًا'],400);
            // Never unlink the lock: waiters must keep using the same inode after deletion.
            labBenchmarkDelete($id);labForgetStartReference($account,$id);labMcpDelete($s);if(!unlink($path))throw new RuntimeException('تعذر حذف المحادثة');
            respond(['ok'=>true,'deleted'=>true,'session_id'=>$id]);
        }
        if (!empty($s['is_published']) && $action!=='submit_review') respond(['ok'=>false,'error'=>'الجلسة المنشورة للقراءة فقط'],409);
        if(!in_array($action,['publish_session','submit_review'],true)) {
            if(($s['funding_source'] ?? '')==='personal') {
                if($userKey==='' || !hash_equals($s['key_fingerprint'] ?? '',hash('sha256',$userKey)))throw new RuntimeException('هذه التجربة على نفقتك؛ أعد إدخال مفتاح OpenRouter نفسه لمتابعتها');
            }else {$funding=labFunding($s['sponsor_id'] ?? '','',$env);labUseFunding($funding);$key=$funding['key'];$userKey='';}
        }
        $save = function(array $value) use ($path): void { labWrite($path,$value); };
        $GLOBALS['lab_checkpoint'] = $save;
        if($action==='submit_review') {
            $role=$body['role'] ?? '';$rating=$body['rating'] ?? null;$text=$body['text'] ?? null;
            if(empty($s['is_ended']) || labTurns($s)<1)throw new RuntimeException('أنهِ الاختبار قبل كتابة المراجعة');
            if(!isset(labActorModels($s)[$role]) || !is_int($rating) || $rating<1 || $rating>5 || !is_string($text) || mb_strlen(trim($text))<3 || mb_strlen($text)>2000)
                throw new RuntimeException('اختر دور نموذج موجود وعلامة من 1 إلى 5 ومراجعة بين 3 و2000 حرف');
            labBenchmarkSave($s,['role'=>$role,'rating'=>$rating,'text'=>trim($text)]);
        } elseif($action==='reverse_question') {
            labModel($s['interrogator_model'],$models,$userKey!=='');
            try{labReverseQuestion($s,$key,$statesData,$models);$save($s);}catch(LabMcpWaiting $e){$save($s);respond(['ok'=>true,'mcp_wait'=>true,'session'=>publicSession($s,$owner)]);}catch(Throwable $e){$save($s);throw $e;}
        } elseif (in_array($action,['send_message','ai_vs_ai_step','reverse_answer'],true)) {
            $requestId = (string)($body['request_id'] ?? '');
            if (!preg_match('/^[a-zA-Z0-9_-]{8,100}$/D',$requestId)) throw new RuntimeException('معرف الطلب مفقود أو غير صالح');
            if (isset($s['processed_requests'][$requestId])) respond(['ok'=>true,'duplicate'=>true,'session'=>publicSession($s,$owner)]);
            if ((int)($body['expected_turns'] ?? -1) !== labTurns($s)) respond(['ok'=>false,'error'=>'تغيرت الجلسة. أعد تحميلها قبل الإرسال.'],409);
            $expected=['human'=>'send_message','ai'=>'ai_vs_ai_step','reverse'=>'reverse_answer'][$s['interrogator_type']];
            if($action!==$expected)throw new RuntimeException('العملية لا تطابق وضع الاستجواب');
            if($s['interrogator_type']!=='reverse')labModel($s['model'],$models,$userKey !== '');
            if ($s['interrogator_type'] !== 'human') labModel($s['interrogator_model'],$models,$userKey !== '');
            try {
                if($s['interrogator_type']==='reverse')labReverseAnswer($s,(string)($body['message'] ?? ''),$key);
                else labStep($s,(string)($body['message'] ?? ''),$key,$statesData,$models);
            }
            catch (LabMcpWaiting $e) { $save($s);respond(['ok'=>true,'mcp_wait'=>true,'session'=>publicSession($s,$owner)]); }
            catch (Throwable $e) { $save($s); throw $e; }
            $s['processed_requests'][$requestId] = labTurns($s); $save($s);
            // Automatic judging is requested separately after saving the last round so a slow panel cannot lose it.
        } elseif ($action === 'retry_confession') {
            if (labTurns($s)<1 || empty($s['confession_error'])) throw new RuntimeException('لا يوجد فحص اعتراف معلق');
            try {
                labFinishRound($s,$key);
                $save($s);
                if(!empty($s['confession_error']))throw new LabProviderException($s['confession_error'],!empty($s['confession_retryable']));
            } catch (Throwable $e) {$save($s);throw $e;}
        } elseif ($action === 'evaluate_session') {
            labEvaluate($s,$key,$models,$save);
        } elseif ($action === 'compact_now') {
            if ($s['is_ended']) throw new RuntimeException('الجلسة منتهية');
            $rid=(string)($body['request_id'] ?? '');
            if ($rid!=='' && isset($s['processed_requests']['compact:'.$rid])) respond(['ok'=>true,'session'=>publicSession($s,$owner)]);
            labCompact($s,$key,$compactionModel); $save($s);
            if ($rid!=='') {$s['processed_requests']['compact:'.$rid]=true;$save($s);}
        } elseif ($action === 'publish_session') {
            if (!$s['is_ended'] || ($s['evaluation_status'] ?? '') !== 'complete' || !$s['evaluation'])
                throw new RuntimeException('أكمل الجلسة وتقييمها قبل النشر');
            $s['is_published'] = true; $s['published_at'] = gmdate('c'); $s['updated_at'] = gmdate('c'); $save($s);
        }
        respond(['ok'=>true,'session'=>publicSession($s,$owner)]);
    } finally { flock($lock, LOCK_UN); fclose($lock); unset($GLOBALS['lab_checkpoint']); }
} catch (Throwable $e) {
    if (!empty($GLOBALS['lab_storage']) && !($e instanceof RuntimeException)) {
        $frames=array_map(fn($f)=>array_intersect_key($f,array_flip(['file','line','function'])), $e->getTrace());
        @file_put_contents($GLOBALS['lab_storage'].'/errors.jsonl',labJson(['at'=>gmdate('c'),'action'=>$action ?? '',
            'type'=>get_class($e),'message'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine(),'trace'=>$frames])."\n",FILE_APPEND|LOCK_EX);
    }
    respond(['ok'=>false,'retryable'=>$e instanceof LabProviderException && $e->retryable,
        'error'=> $e instanceof RuntimeException ? $e->getMessage() : 'تعذر معالجة الطلب. أعد المحاولة.'], $e instanceof LabProviderException && $e->retryable ? 503 : 400);
}
