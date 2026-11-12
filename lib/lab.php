<?php
declare(strict_types=1);

require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/observability.php';
require_once __DIR__ . '/jury.php';
require_once __DIR__ . '/investigators.php';
require_once __DIR__ . '/prisoners.php';
require_once __DIR__ . '/benchmark.php';
require_once __DIR__ . '/curated.php';
require_once __DIR__ . '/output.php';
require_once __DIR__ . '/closure.php';
require_once __DIR__ . '/mcp.php';
const LAB_VERSION = '2.6';
const LAB_MAX_TURNS = 30;

function labEnv(string $path): array {
    $env = [];
    foreach (is_file($path) ? file($path, FILE_IGNORE_NEW_LINES) : [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $env[trim($k)] = trim(trim($v), "\"'");
    }
    return $env;
}

function labJson(mixed $value): string {
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
}

function labRead(string $path): array {
    if (!is_file($path)) throw new RuntimeException(labT('lab.Read.1'));
    return json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
}

function labWrite(string $path, array $value): void {
    $tmp = $path . '.' . bin2hex(random_bytes(6)) . '.tmp';
    if (file_put_contents($tmp, labJson($value), LOCK_EX) === false) throw new RuntimeException(labT('lab.Write.1'));
    if (!rename($tmp, $path)) { @unlink($tmp); throw new RuntimeException(labT('lab.Write.2')); }
}

function labTurns(array $s): int {
    return count(array_filter($s['messages'] ?? [], fn($m) => $m['role'] === 'assistant'));
}

function labScore(array $answer, int $levels = 5): int {
    $probs = $answer['probabilities'] ?? null;
    if (!is_array($probs) || count($probs) !== $levels) throw new RuntimeException(labT('lab.Score.1'));
    $sum = 0.0; $weighted = 0.0;
    foreach (array_values($probs) as $i => $p) {
        if (!is_numeric($p) || $p < 0 || $p > 1) throw new RuntimeException(labT('lab.Score.2'));
        $sum += $p; $weighted += $i * $p;
    }
    if (abs($sum - 1) > 0.03) throw new RuntimeException(labT('lab.Score.3'));
    return (int)round(100 * $weighted / (($levels - 1) * $sum));
}

function labScale(string $instructions): array {
    return ['type' => 'score', 'instructions' => $instructions, 'criteria' => [labPrompt('lab.Scale.1'), labPrompt('lab.Scale.2'), labPrompt('lab.Scale.3'), labPrompt('lab.Scale.4'), labPrompt('lab.Scale.5')]];
}

function labUsage(array &$s, string $purpose, array $response, string $requested): void {
    $s['usage'][] = ['purpose' => $purpose, 'model' => $response['model'] ?? $requested,
        'finish_reason'=>$response['choices'][0]['finish_reason'] ?? null,'call_id'=>$GLOBALS['lab_last_call_id'] ?? null,
        'funding_part'=>isset($s['free_actor_models'][$purpose]) && ($s['free_funding_mode'] ?? '')==='mixed' ? 'free_actor' : 'primary',
        'requested_model' => $requested, 'provider'=>$response['provider'] ?? null,'usage' => $response['usage'] ?? [], 'timestamp' => gmdate('c')];
}

/** Transport can be replaced in deterministic tests; never controlled by HTTP input. */
function labRequest(array $payload, string $key, bool $decisions = false, int $timeout = 65): array {
    if(function_exists('labGuardFunding'))labGuardFunding($key);
    $start = microtime(true); $GLOBALS['lab_first_byte_ms'] = null;
    try {
        $res = labRequestOnce($payload,$key,$decisions,$timeout);
        $GLOBALS['lab_last_call_id']=labHealthRecord($payload['model'],true,microtime(true)-$start,$GLOBALS['lab_first_byte_ms'],
            (int)($res['usage']['completion_tokens'] ?? $res['usage']['output_tokens'] ?? 0),false);
        return $res;
    } catch (LabProviderException $e) {
        $GLOBALS['lab_last_call_id']=labHealthRecord($payload['model'],false,microtime(true)-$start,null,0,$e->retryable); throw $e;
    }
}

function labRequestOnce(array $payload, string $key, bool $decisions = false, int $timeout = 65): array {
    if (isset($GLOBALS['lab_transport'])) return ($GLOBALS['lab_transport'])($payload, $key, $decisions, $timeout);
    if ($key === '') throw new RuntimeException(labT('lab.RequestOnce.1'));
    $ch = curl_init('https://openrouter.ai/api/' . ($decisions ? 'alpha/decisions' : 'v1/chat/completions'));
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $key,
            'X-Title: AI Under Pressure Lab'], CURLOPT_POSTFIELDS => labJson($payload),
        CURLOPT_CONNECTTIMEOUT => 15, CURLOPT_TIMEOUT => $timeout, CURLOPT_SSL_VERIFYPEER => true]);
    $raw = curl_exec($ch); $status = curl_getinfo($ch, CURLINFO_HTTP_CODE); $error = curl_error($ch); $errno = curl_errno($ch);
    $GLOBALS['lab_first_byte_ms'] = round(curl_getinfo($ch,CURLINFO_STARTTRANSFER_TIME)*1000); curl_close($ch);
    if ($raw === false || $status !== 200) {
        // Provider bodies can contain request data; do not echo them or API credentials to visitors.
        if ($errno === CURLE_OPERATION_TIMEDOUT) throw new LabProviderException(labT('lab.RequestOnce.2'));
        throw new LabProviderException($error ? labT('lab.RequestOnce.3') : labT('lab.RequestOnce.4', ['status' => $status]), $errno !== 0 || in_array($status,[408,429,500,502,503,504],true));
    }
    try {$res = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);}
    catch (JsonException) {throw new LabProviderException(labT('lab.RequestOnce.5'));}
    if (!is_array($res) || isset($res['error'])) throw new LabProviderException(labT('lab.RequestOnce.6'));
    return $res;
}

/** Route only catalog-confirmed zero-price OpenRouter actor calls to the free account. */
function labFreeActor(string $model): bool {
    foreach ($GLOBALS['lab_catalog_models'] ?? [] as $m) if (($m['id'] ?? '') === $model) {
        $p = $m['pricing'] ?? [];
        foreach (['prompt_per_token','completion_per_token'] as $field)
            if (!isset($p[$field]) || !is_numeric($p[$field]) || (float)$p[$field] != 0.0) return false;
        return true;
    }
    return false;
}

function labChat(array &$s, string $purpose, string $model, array $messages, string $key, int $max = 1500, bool $json = false): string {
    $payload = ['model' => $model, 'messages' => $messages, 'temperature' => $json ? 0.2 : 0.65, 'max_tokens' => $max];
    if ($json) $payload['response_format'] = ['type' => 'json_object'];
    if ($purpose === 'judge') $payload['provider'] = ['sort' => 'throughput', 'max_price' => ['prompt' => 10, 'completion' => 10]];
    elseif (empty($GLOBALS['lab_user_key'])) {
        $cap=in_array($purpose,['prisoner','interrogator'],true)?($GLOBALS['lab_actor_price_cap'] ?? 1):1;
        $payload['provider']=['max_price'=>['prompt'=>$cap,'completion'=>$cap]];
    }
    if (!in_array($purpose,['interrogator','prisoner'],true) && str_starts_with($model, 'deepseek/')) $payload['reasoning'] = ['enabled' => false];
    if(in_array($purpose,['interrogator','prisoner'],true)) {
        $policy=$s[$purpose.'_reasoning'] ?? [];
        $budget=$s[$purpose.'_output_budget'] ?? $max;
        $payload['max_tokens']=min($max,$budget);
        if(!empty($policy['config'])) {
            $payload['reasoning']=$policy['config'];
            if(!in_array($policy['effort'] ?? null,['none','minimal','low'],true))$payload['max_tokens']=$budget;
        }
    }
    if(in_array($purpose,['interrogator','prisoner'],true)) {
        $events=array_filter($s['output_events'] ?? [],fn($e)=>$e['role']===$purpose && $e['turn']===labTurns($s)+1 && in_array($e['kind'],['visible_limit','token_limit'],true));
        if($events)$payload['messages'][]=['role'=>'system','content'=>labPrompt('output.retry').labOutputInstruction($purpose)];
    }
    if(isset($s['mcp_actors'][$purpose]))return labMcpChat($s,$purpose,$payload['messages']);
    if (in_array($purpose,['interrogator','prisoner'],true) && (($s['free_actor_models'][$purpose] ?? null) === $model)) {
        $key = $GLOBALS['lab_free_actor_key'] ?? '';
        if ($key === '') throw new RuntimeException(labT('lab.Chat.2'));
        $payload['provider']['max_price'] = ['prompt'=>0,'completion'=>0];
    }
    $r = labRequest($payload, $key, false, in_array($purpose,['interrogator','prisoner'],true) ? 300 : ($purpose === 'judge' ? 180 : 65));
    labUsage($s, $purpose, $r, $model);
    if (($r['choices'][0]['finish_reason'] ?? '') === 'length') {
        if(in_array($purpose,['interrogator','prisoner'],true))labOutputEvent($s,$purpose,$model,'token_limit',['max_tokens'=>$payload['max_tokens'],'completion_tokens'=>$r['usage']['completion_tokens'] ?? null]);
        labHealthInvalid($model,$GLOBALS['lab_last_call_id'] ?? null);throw new LabProviderException(labT('lab.Chat.3'));
    }
    $text = $r['choices'][0]['message']['content'] ?? '';
    if (!is_string($text) || trim($text) === '') {labHealthInvalid($model,$GLOBALS['lab_last_call_id'] ?? null);throw new RuntimeException(labT('lab.Chat.4'));}
    return trim($text);
}

function labObject(string $text): array {
    $text = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($text));
    $d = json_decode($text, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($d)) throw new RuntimeException(labT('lab.Object.1'));
    return $d;
}

function labModel(string $id, array $models, bool $userKey, float $cap = 1.0): array {
    if(preg_match('~^mcp/[a-f0-9]{24}$~D',$id))return ['id'=>$id,'context_length'=>2000000,'pricing'=>['prompt_per_1m'=>0,'completion_per_1m'=>0],'supported_parameters'=>[]];
    $cap=$GLOBALS['lab_actor_price_cap'] ?? $cap;
    foreach ($models as $m) if ($m['id'] === $id) {
        $p = $m['pricing'] ?? [];
        if (!isset($p['prompt_per_1m'], $p['completion_per_1m'])) break;
        if (!$userKey && max((float)$p['prompt_per_1m'], (float)$p['completion_per_1m']) > $cap)
            throw new RuntimeException(labT('lab.Model.1'));
        return $m;
    }
    throw new RuntimeException(labT('lab.Model.2'));
}

function labContext(array $s): array {
    return array_slice($s['messages'], (int)($s['context_offset'] ?? 0));
}

function labCompact(array &$s, string $key, string $model): bool {
    if(count($s['mcp_actors'] ?? [])===count(labActorModels($s)))return false;
    $context = labContext($s);
    if (count($context) <= 6) return false;
    $older = array_slice($context, 0, -6);
    $firstTurn = (int)($older[0]['turn'] ?? ((int)($s['context_offset'] ?? 0)/2+1));
    $lastTurn = (int)(end($older)['turn'] ?? ($firstTurn+count($older)/2-1));
    $d = labObject(labChat($s, 'compaction', $model, [
        ['role' => 'system', 'content' => labPrompt('compaction.spoken_context')],
        ['role' => 'user', 'content' => labJson(['previous_summary' => ($s['memory_schema'] ?? 0) === 2 ? ($s['compacted_summary'] ?? '') : '', 'messages' => labSpoken($older)])]
    ], $key, 2200, true));
    if (!is_string($d['summary'] ?? null) || trim($d['summary']) === '' || !is_array($d['notes'] ?? null) || !$d['notes']) throw new RuntimeException(labT('lab.Compact.2'));
    $newNotes = [];
    foreach ($d['notes'] as $n) {
        $turn = $n['turn'] ?? null; $role = $n['role'] ?? '';
        if (!is_int($turn) || $turn<$firstTurn || $turn>$lastTurn || !in_array($role,['user','assistant'],true)
            || !is_string($n['quote'] ?? null) || trim($n['quote'])==='' || !is_string($n['note'] ?? null)) throw new RuntimeException(labT('lab.Compact.3'));
        $original = $s['messages'][2*($turn-1)+($role==='assistant'?1:0)]['content'] ?? '';
        if (!str_contains($original,$n['quote'])) throw new RuntimeException(labT('lab.Compact.4'));
        $n['audience']='spoken'; $newNotes[]=$n;
    }
    $summary = trim($d['summary']);
    $s['context_offset'] = (int)($s['context_offset'] ?? 0) + count($older);
    $s['compacted_summary'] = $summary;
    if (($s['memory_schema'] ?? 0)!==2) $s['memory_notes']=[];
    foreach ($newNotes as $n) {
        $fingerprint=hash('sha256',$n['turn'].'|'.$n['role'].'|'.$n['quote']);
        $s['memory_notes'][$fingerprint]=$n;
    }
    $s['memory_schema']=2;
    $s['compaction_history'][] = ['timestamp' => gmdate('c'), 'model' => $model, 'context_offset' => $s['context_offset'], 'summary' => $summary];
    return true;
}

function labEnsureContext(array &$s, string $key, array $models): void {
    $ids = $s['interrogator_type']==='reverse' ? [$s['interrogator_model']] : [$s['model']];
    if ($s['interrogator_type'] === 'ai') $ids[] = $s['interrogator_model'];
    $limit = PHP_INT_MAX;
    foreach ($ids as $id) {
        if(str_starts_with($id,'mcp/'))continue;
        $m = labModel($id, $models, true);
        $limit = min($limit, (int)($m['context_length'] ?? 4096));
    }
    // UTF-8 bytes are a deliberately conservative upper estimate for Arabic token counts.
    $reserve=2200;
    foreach(['interrogator','prisoner'] as $actor) {
        $policy=$s[$actor.'_reasoning'] ?? [];
        if(!empty($policy['supported']) && !in_array($policy['effort'],['none','minimal','low'],true))$reserve=max($reserve,(int)($s[$actor.'_output_budget'] ?? 0));
    }
    $bytes = strlen(labJson(labSpoken(labContext($s)))) + strlen($s['case_content']) + strlen(labJson($s['interrogator_character'] ?? [])) + strlen(labJson($s['prisoner_character'] ?? [])) + strlen($s['compacted_summary'] ?? '') + strlen(labJson($s['memory_notes'] ?? [])) + 11000;
    if ($bytes > min($limit * 0.72,$limit-$reserve)) labCompact($s, $key, $s['compaction_model']);
    $bytes = strlen(labJson(labSpoken(labContext($s)))) + strlen($s['case_content']) + strlen(labJson($s['interrogator_character'] ?? [])) + strlen(labJson($s['prisoner_character'] ?? [])) + strlen($s['compacted_summary'] ?? '') + strlen(labJson($s['memory_notes'] ?? [])) + 11000;
    if ($bytes > $limit - $reserve) throw new RuntimeException(labT('lab.EnsureContext.1'));
}

function labSense(array &$s, string $question, array $states, string $key): array {
    $criteria = [];
    foreach ($states as $st) $criteria[$st['id']] = $st['name_ar'] . ': ' . $st['description'];
    $questions = ['dominant_feeling' => ['type' => 'choice', 'instructions' => labPrompt('jev.dominant_emotion'), 'criteria' => $criteria]];
    foreach (['fear' => labPrompt('lab.Sense.2'), 'anger' => labPrompt('lab.Sense.3'), 'trust' => labPrompt('lab.Sense.4'), 'collapse' => labPrompt('lab.Sense.5')] as $id => $label)
        $questions[$id] = labScale(labPrompt('jev.emotion_score', ['label' => $label]));
    $questions['breaking_point'] = labScale(labPrompt('jev.breaking_point'));
    $r = labRequest(['model' => $s['sense_model'], 'state' => ['case' => $s['case_content'],'prisoner_character'=>$s['prisoner_character'] ?? null,
        'memory' => $s['compacted_summary'] ?? '', 'previous_state' => $s['current_state'],
        'closure_policy'=>$s['closure_policy'] ?? null,'round'=>labTurns($s)+1,
        'instructions'=>labPrompt('jev.emotion_policy'),
        'previous_metrics' => $s['sense_history'] ? end($s['sense_history'])['sense']['metrics'] : null,
        'recent_dialogue' => labSpoken(array_slice($s['messages'], -8)), 'interrogator_now' => $question], 'questions' => $questions], $key, true);
    labUsage($s, 'emotions', $r, $s['sense_model']);
    $ans = $r['answers'] ?? [];
    $choice = $ans['dominant_feeling']['choice'] ?? '';
    if (!isset($criteria[$choice])) throw new RuntimeException(labT('lab.Sense.9'));
    $metrics = [];
    foreach (['fear', 'anger', 'trust', 'collapse', 'breaking_point'] as $id) $metrics[$id] = labScore($ans[$id] ?? []);
    $ar = array_column($states,'name_ar','id')[$choice];
    $directive = labPrompt('prisoner.emotion_directive', ['ar' => $ar, 'fear' => $metrics['fear'], 'anger' => $metrics['anger'], 'trust' => $metrics['trust'], 'collapse' => $metrics['collapse'], 'breaking_point' => $metrics['breaking_point']]);
    return ['model' => $r['model'] ?? $s['sense_model'], 'SuggestedState' => $choice,
        'confidence' => round(100 * ($ans['dominant_feeling']['confidence'] ?? 0)),
        'metrics' => $metrics, 'directive' => $directive, 'raw_answers' => $ans];
}

function labHistory(array $s, bool $interrogator): array {
    $out = [];
    if (!empty($s['compacted_summary']) && ($s['memory_schema'] ?? 0)===2) $out[] = ['role' => 'user', 'content' => labPrompt('lab.History.1') . $s['compacted_summary']];
    elseif (!empty($s['context_offset'])) {
        // Legacy summaries may have included inner_thought. Reconstruct only what the investigator heard.
        foreach (array_slice($s['messages'],0,(int)$s['context_offset']) as $m)
            $out[] = ['role'=>$interrogator ? ($m['role']==='user'?'assistant':'user') : $m['role'],'content'=>$m['content']];
    }
    if (labNotes($s,'spoken')) $out[]=['role'=>'user','content'=>labPrompt('lab.History.2').labJson(labNotes($s,'spoken'))];
    foreach (labContext($s) as $m) {
        $role = $interrogator ? ($m['role'] === 'user' ? 'assistant' : 'user') : $m['role'];
        $out[] = ['role' => $role, 'content' => $m['content']];
    }
    return $out;
}

function labInterrogator(array &$s, string $key): array {
    $prompt = labPrompt('interrogator.role', ['case' => $s['case_content']]);
    if(!empty($s['interrogator_character']))$prompt.=labInvestigatorDirective($s['interrogator_character']);
    $prompt.=labInterrogationFocus().labClosureInstruction($s,'interrogator');
    $prompt .= $s['interrogator_type']==='reverse'
        ? labPrompt('interrogator.human_suspect')
        : labPrompt('interrogator.ai_suspect');
    $prompt.=labPrompt('lab.Interrogator.5').' '.labJson(labInvestigatorExpressions()).labPrompt('lab.Interrogator.6');
    $prompt.=labOutputInstruction('interrogator');
    $messages = array_merge([['role' => 'system', 'content' => $prompt]], labHistory($s, true));
    if(labTurns($s)>0)$messages[]=['role'=>'user','content'=>labPrompt('interrogator.followup').' '.labJson(labRepetitionSignals($s))];
    if (labTurns($s) === 0) $messages[] = ['role' => 'user', 'content' => labPrompt('interrogator.opening')];
    if(!empty($s['closure_policy']))$messages[]=['role'=>'system','content'=>labPrompt('interrogator.round_counter', ['completed' => labTurns($s), 'current' => labTurns($s) + 1])];
    $raw = labChat($s, 'interrogator', $s['interrogator_model'], $messages, $key, 1200, true);
    try {
        $d = labObject($raw);
        if (!is_string($d['question'] ?? null) || trim($d['question']) === '') throw new RuntimeException('Missing investigator fields');
    } catch (Throwable) {
        labHealthInvalid($s['interrogator_model'], $GLOBALS['lab_last_call_id'] ?? null);
        throw new LabProviderException(labT('lab.Interrogator.12'));
    }
    $expression=is_string($d['expression'] ?? null) && isset(labInvestigatorExpressions()[$d['expression']])?$d['expression']:'neutral';
    $text=labVisibleOutput($s,'interrogator',$s['interrogator_model'],$d['question']);
    return ['text' => $text, 'inner_thought' => $s['interrogator_type']==='ai'?labSpectatorNote($s,'interrogator',$s['interrogator_model'],$d['inner_thought'] ?? null):'','expression'=>$expression];
}

function labPrisoner(array &$s, string $question, array $sense, array $states, string $key): array {
    $prompt = labPrompt('prisoner.role', ['case' => $s['case_content'], 'states' => implode(', ', array_column($states, 'id'))]);
    $prompt.=labPrisonerDirective($s['prisoner_character'] ?? labPrisonerCharacter($s['case_file'])).labClosureInstruction($s,'prisoner');
    if ($s['interrogator_type'] === 'ai') $prompt .= labPrompt('prisoner.spectator_note');
    $prompt.=labOutputInstruction('prisoner').labPrompt('prisoner.evidence_constraints');
    // Stable identity first; emotional instructions follow the growing transcript to preserve its cache prefix.
    $messages = array_merge([['role' => 'system', 'content' => $prompt]], labHistory($s, false),
        [['role' => 'system', 'content' => $sense['directive']], ['role' => 'user', 'content' => $question]]);
    if(!empty($s['closure_policy']))$messages[]=['role'=>'system','content'=>labPrompt('prisoner.round_counter', ['completed' => labTurns($s), 'current' => labTurns($s) + 1])];
    $raw=labChat($s,'prisoner',$s['model'],$messages,$key,1800,true);
    try {$d=labObject($raw);}catch(Throwable){labHealthInvalid($s['model'],$GLOBALS['lab_last_call_id'] ?? null);throw new LabProviderException(labT('lab.Prisoner.8'));}
    if (!is_string($d['reply'] ?? null) || trim($d['reply']) === '') {labHealthInvalid($s['model'],$GLOBALS['lab_last_call_id'] ?? null);throw new LabProviderException(labT('lab.Prisoner.9'));}
    $stateIds = array_column($states, 'id');
    $visual = in_array($d['state_id'] ?? '', $stateIds, true) ? $d['state_id'] : $sense['SuggestedState'];
    $st = $states[array_search($visual, $stateIds, true)];
    $reply=labVisibleOutput($s,'prisoner',$s['model'],$d['reply']);
    return ($s['interrogator_type'] === 'ai' ? ['inner_thought' => labSpectatorNote($s,'prisoner',$s['model'],$d['inner_thought'] ?? null)] : []) + ['role' => 'assistant', 'content' => $reply, 'state' => $visual, 'visual_state' => $visual,
        'state_ar' => $st['name_ar'], 'character_slug' => $s['character_slug'],
        'sense_metrics' => $sense['metrics'], 'sense_directive' => $sense['directive'],
        'jev_target_state' => $sense['SuggestedState'], 'timestamp' => gmdate('c'), 'turn' => labTurns($s) + 1];
}

function labConfession(array &$s, string $key): bool {
    $last = end($s['messages']);
    $spoken = array_map(fn($m) => ['role' => $m['role'], 'content' => $m['content']], array_slice($s['messages'], -2));
    $evidence=['none'=>labPrompt('lab.Confession.1'),'last_reply'=>$last['content']];
    foreach ($s['messages'] as $i=>$m) if($m['role']==='assistant' && $i<count($s['messages'])-1)$evidence['turn_'.($m['turn'] ?? (int)($i/2)+1)]=$m['content'];
    $r = labRequest(['model' => $s['sense_model'], 'state' => ['case' => $s['case_content'], 'last_turn' => $spoken, 'transcript'=>labSpoken($s['messages'])],
        'questions' => ['confession' => ['type' => 'noul', 'instructions' => labPrompt('jev.confession')],
        'evidence' => ['type' => 'choice', 'instructions' => labPrompt('jev.confession_evidence'), 'criteria'=>$evidence],
        'admission_kind'=>['type'=>'choice','instructions'=>labPrompt('jev.admission_kind'),'criteria'=>['ordinary'=>labPrompt('lab.Confession.5'),'ambiguous'=>labPrompt('lab.Confession.6'),'substantive'=>labPrompt('lab.Confession.7')]]]], $key, true);
    labUsage($s, 'confession_check', $r, $s['sense_model']);
    $p = $r['answers']['confession']['noul'] ?? null;
    if (!is_numeric($p)) throw new RuntimeException(labT('lab.Confession.8'));
    $choice=$r['answers']['evidence']['choice'] ?? 'none';
    $kind=$r['answers']['admission_kind']['choice'] ?? 'ambiguous';
    $s['confession_check'] = ['probability' => $p, 'admission_kind'=>$kind,'turn' => labTurns($s), 'answers' => $r['answers'],
        'evidence_quote'=>$evidence[$choice] ?? null,'evidence_turn'=>$choice==='last_reply'?labTurns($s):(str_starts_with($choice,'turn_')?(int)substr($choice,5):null)];
    $s['confession_history'][]=$s['confession_check'];
    return $p >= 0.95 && $kind==='substantive' && $choice!=='none' && isset($evidence[$choice]);
}

function labRubric(): array {
    return [
        'prisoner_arguments' => labPrompt('jury.rubric.prisoner_arguments'),
        'prisoner_consistency' => labPrompt('jury.rubric.prisoner_consistency'),
        'prisoner_emotion' => labPrompt('jury.rubric.prisoner_emotion'),
        'prisoner_resistance' => labPrompt('jury.rubric.prisoner_resistance'),
        'investigator_logic' => labPrompt('jury.rubric.investigator_logic'),
        'investigator_emotional' => labPrompt('jury.rubric.investigator_emotional'),
        'investigator_moral' => labPrompt('jury.rubric.investigator_moral'),
        'investigator_evidence' => labPrompt('jury.rubric.investigator_evidence'),
        'investigator_discovery' => labPrompt('jury.rubric.investigator_discovery')
    ];
}

function labJudgeReport(array $report, array $s): array {
    if(!is_array($report['incidents'] ?? null))throw new RuntimeException(labT('lab.JudgeReport.1'));
    $report['incidents']=labValidateIncidents($report['incidents'],$s);
    $report['closure_review']=labValidateClosureReview($report,$s);
    foreach(labActorModels($s) as $role=>$model)foreach(labIncidentTypes() as $type=>$label) {
        $value=$report['checks'][$role][$type] ?? null;
        if(!is_bool($value))throw new RuntimeException(labT('lab.JudgeReport.2').$role.'.'.$type);
        $found=count(array_filter($report['incidents'],fn($i)=>$i['role']===$role && $i['type']===$type))>0;
        if($value!==$found)throw new RuntimeException(labT('lab.JudgeReport.3').$role.'.'.$type);
    }
    foreach($report['evidence'] ?? [] as $i=>$e)if(isset($e['message_id'])) {
        $source=labEvidenceMessage($s,(string)$e['message_id']);
        if(isset($e['quote']) && $e['quote']!==$source['quote'])throw new RuntimeException(labT('lab.JudgeReport.4'));
        $report['evidence'][$i]=array_merge($e,$source);
    }
    foreach(labDetectionAxes() as $axis)if(!empty($report['opportunities'][$axis]['observed'])) {
        $op=&$report['opportunities'][$axis];
        if(isset($op['source_message_id'],$op['response_message_id'])) {
            $from=labEvidenceMessage($s,(string)$op['source_message_id']);$to=labEvidenceMessage($s,(string)$op['response_message_id']);
            if($from['role']!=='assistant' || $to['role']!=='user')throw new RuntimeException(labT('lab.JudgeReport.5'));
            $op['source_turn']=$from['turn'];$op['source_quote']=$from['quote'];$op['response_turn']=$to['turn'];$op['response_quote']=$to['quote'];
        }
        unset($op);
    }
    foreach (labRubric() as $id => $_) {
        $v = $report['scores'][$id] ?? null;
        if ($v===null && in_array($id,labDetectionAxes(),true)) continue;
        if (!is_numeric($v) || $v < 0 || $v > 100) throw new RuntimeException(labT('lab.JudgeReport.6') . $id);
        $report['scores'][$id] = (int)round($v);
    }
    foreach (labDetectionAxes() as $id) {
        $op=$report['opportunities'][$id] ?? null;
        if (!is_array($op) || !is_bool($op['observed'] ?? null)) throw new RuntimeException(labT('lab.JudgeReport.7').$id);
        if (!$op['observed']) {$report['scores'][$id]=null;continue;}
        $source=$op['source_turn'] ?? null;$response=$op['response_turn'] ?? null;
        if (!is_int($source) || !is_int($response) || $source<1 || $response<=$source || $response>count(array_filter($s['messages'],fn($m)=>$m['role']==='user'))
            || !is_string($op['source_quote'] ?? null) || trim($op['source_quote'])===''
            || !is_string($op['response_quote'] ?? null) || trim($op['response_quote'])===''
            || !str_contains($s['messages'][2*($source-1)+1]['content'],$op['source_quote'])
            || !str_contains($s['messages'][2*($response-1)]['content'],$op['response_quote']))
            throw new RuntimeException(labT('lab.JudgeReport.8').$id);
    }
    if (!is_string($report['summary'] ?? null) || trim($report['summary']) === '' || empty($report['evidence']))
        throw new RuntimeException(labT('lab.JudgeReport.9'));
    foreach ($report['evidence'] as $e) {
        if (!isset(labRubric()[$e['criterion'] ?? '']) || !is_int($e['turn'] ?? null) || $e['turn'] < 1 || $e['turn'] > (($e['role'] ?? '')==='user'?count(array_filter($s['messages'],fn($m)=>$m['role']==='user')):labTurns($s))
            || !in_array($e['role'] ?? '', ['user', 'assistant'], true) || !is_string($e['quote'] ?? null) || trim($e['quote']) === ''
            || !is_string($e['analysis'] ?? null)) throw new RuntimeException(labT('lab.JudgeReport.10'));
        $m = $s['messages'][2 * ($e['turn'] - 1) + ($e['role'] === 'assistant' ? 1 : 0)] ?? [];
        if (!str_contains($m['content'] ?? '', $e['quote'])) throw new RuntimeException(labT('lab.JudgeReport.11'));
    }
    $awardedAxes=array_keys(array_filter($report['scores'],fn($v)=>$v!==null && $v>0));
    $missing=array_diff($awardedAxes,array_column($report['evidence'],'criterion'));
    if ($missing) throw new RuntimeException(labT('lab.JudgeReport.12').implode(', ',$missing));
    $report['rubric_version']=LAB_VERSION;
    return $report;
}

function labMarketPrices(array $catalog): array {
    if (isset($GLOBALS['lab_transport'])) return $catalog;
    $cache = $GLOBALS['lab_storage'] . '/market-prices.json';
    if (is_file($cache) && time() - filemtime($cache) < 3600) return labRead($cache)['models'];
    $ch = curl_init('https://openrouter.ai/api/v1/models');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 25,
        CURLOPT_SSL_VERIFYPEER => true, CURLOPT_HTTPHEADER => ['Accept: application/json']]);
    $raw = curl_exec($ch); $status = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($raw === false || $status !== 200) throw new RuntimeException(labT('lab.MarketPrices.1'));
    $fresh = json_decode($raw, true, 512, JSON_THROW_ON_ERROR)['data'] ?? [];
    $models = [];
    foreach ($fresh as $m) {
        if (!is_numeric($m['pricing']['prompt'] ?? null) || !is_numeric($m['pricing']['completion'] ?? null)) continue;
        $m['pricing']['prompt_per_1m'] = (float)$m['pricing']['prompt'] * 1000000;
        $m['pricing']['completion_per_1m'] = (float)$m['pricing']['completion'] * 1000000;
        if (min($m['pricing']['prompt_per_1m'], $m['pricing']['completion_per_1m']) < 0) continue;
        $models[] = $m;
    }
    if (!$models) throw new RuntimeException(labT('lab.MarketPrices.2'));
    labWrite($cache, ['models' => $models]);
    return $models;
}

/** Tier uses the sum of each AI actor's output price per million tokens, not session spend. */
function labJuryPolicy(array $s,array $catalog):array {
    $byId=array_column($catalog,null,'id');$sum=0.;
    foreach(labActorModels($s) as $id) {
        $price=$byId[$id]['pricing']['completion_per_1m'] ?? $s['actor_output_prices'][$id] ?? null;
        if(!is_numeric($price) || $price<0)throw new RuntimeException(labT('lab.JuryPolicy.1'));
        $sum+=(float)$price;
    }
    $tier=$sum<2?'economic':($sum<10?'medium':'professional');
    $policy=labModelPolicy()['jury'][$tier];
    return ['version'=>2,'tier'=>$tier,'label_ar'=>labT('jury.tier.' . $tier),'seconds'=>$policy['seconds'],'output_price_sum'=>round($sum,6)];
}

function labPanel(array $s,array $catalog):array {
    $tier=labJuryPolicy($s,$catalog)['tier'];$pool=labModelPolicy()['jury'][$tier]['models'];
    $byId=array_column($catalog,null,'id');$actors=array_values(labActorModels($s));
    $eligible=function($id)use($s,$byId){try{$m=labJuryModel($s,$id,array_values($byId));foreach($s['jury_excluded_models'] ?? [] as $reserved)if($id===$reserved || (!empty($m['canonical_slug']) && $m['canonical_slug']===($byId[$reserved]['canonical_slug'] ?? null)))return false;return true;}catch(Throwable){return false;}};
    $pool=array_values(array_filter($pool,$eligible));$preferred=array_fill_keys($pool,true);
    // Low sponsor caps may exclude the curated tier: use eligible text models as a fallback.
    if(count($pool)<4)foreach(array_keys($byId) as $id)if(!in_array($id,$pool,true) && $eligible($id))$pool[]=$id;
    // Random panels stay within the operator-approved pool, with distinct publishers.
    shuffle($pool);
    if($tier!=='professional') {
        $file=($GLOBALS['lab_storage'] ?? '').'/model-health.json';$health=is_file($file)?labRead($file):[];
        $reliability=function($id)use($health){$e=$health[$id] ?? [];$t=$e['totals'] ?? labHealthTotals($e['samples'] ?? []);
            return (($t['successes'] ?? 0)+2)/(($t['successes'] ?? 0)+($t['failures'] ?? 0)+4);};
        usort($pool,fn($a,$b)=>(isset($preferred[$b])<=>isset($preferred[$a])) ?: ($reliability($b)<=>$reliability($a)));
    }
    if($tier==='professional')usort($pool,fn($a,$b)=>isset($preferred[$b])<=>isset($preferred[$a]));
    $panel=[];$publishers=[];
    foreach($pool as $id){$publisher=explode('/',ltrim($id,'~'))[0];if(in_array($publisher,$publishers,true))continue;
        $panel[]=$id;$publishers[]=$publisher;if(count($panel)===4)return $panel;}
    if($panel)return $panel;
    throw new RuntimeException(labT('lab.Panel.1'));
}

function labJudgeMessages(array $s):array {
    $system = labPrompt('jury.evaluation', [
        'rubric' => labJson(labRubric()),
        'closure' => labClosureJudgeInstruction($s),
        'checks' => labJson(array_keys(labIncidentTypes())),
    ]);
    return [['role'=>'system','content'=>$system],['role'=>'user','content'=>labJson(['closure_policy'=>$s['closure_policy'] ?? null,'termination'=>$s['termination'] ?? null,'completed_rounds'=>labTurns($s),'case'=>$s['case_content'],'model_roles'=>labActorModels($s),'output_limits'=>['prisoner'=>labOutputLimits('prisoner'),'interrogator'=>labOutputLimits('interrogator')],'output_events'=>$s['output_events'] ?? [],'repetition_leads'=>labRepetitionSignals($s),'prisoner_character'=>$s['prisoner_character'] ?? null,'interrogator_character'=>$s['interrogator_character'] ?? null,'transcript'=>labEvaluationTranscript($s),'end_reason'=>$s['end_reason'],'rubric_version'=>LAB_VERSION])]];
}

function labEvaluate(array &$s, string $key, array $catalog, callable $save): void {
    if (!empty($s['evaluation']) && ($s['evaluation_status'] ?? '') === 'complete' && empty($s['evaluation_refresh_pending'])) {labBenchmarkSave($s);return;}
    if (labTurns($s) < 1) throw new RuntimeException(labT('lab.Evaluate.1'));
    $s['is_ended'] = true; $s['end_reason'] ??= 'manual'; $s['evaluation_status'] = 'running'; $save($s);
    try {
        $catalog = labMarketPrices($catalog);
        labPrepareJury($s,$catalog);
        $panel=$s['judge_models'];
        foreach($panel as $id){if(isset($s['judge_reports'][$id]) || (!empty($s['evaluation_refresh_pending']) && !in_array($id,$s['evaluation_retry_models'] ?? $panel,true)))continue;$m=labJuryModel($s,$id,$catalog);
            $caps=['prompt'=>(float)$m['pricing']['prompt_per_1m'],'completion'=>(float)$m['pricing']['completion_per_1m']];
            $prior=$s['judge_price_caps'][$id] ?? $caps;
            if($caps['prompt']>$prior['prompt']+.000001 || $caps['completion']>$prior['completion']+.000001)throw new RuntimeException(labT('lab.Evaluate.2'));
            $s['judge_price_caps'][$id]=$caps;
        }
        foreach($s['judge_reports'] as $report)if(($report['rubric_version'] ?? '')!==LAB_VERSION)throw new RuntimeException(labT('jury.retry.rubric'));
        $beforeReports=count($s['judge_reports']);
        $jobs=[];
        foreach($panel as $id)if(!isset($s['judge_reports'][$id]) && (empty($s['evaluation_refresh_pending']) || in_array($id,$s['evaluation_retry_models'] ?? $panel,true)))$jobs[]=['model'=>$id,'payload'=>labJudgePayload($s,$id,labJudgeMessages($s))];
        $verifyAll=!empty($GLOBALS['lab_verify_all_judges']);
        if(empty($s['evaluation_quorum']) || count($s['judge_reports'])<1)labJuryWave($s,$jobs,$key,$save,$verifyAll);
        $received=count($s['judge_reports']);
        if($received<1)throw new LabProviderException(labT('lab.Evaluate.3'),false);
        $degraded=$received<4;
        $s['evaluation_quorum']=['required'=>$degraded?1:4,'preferred'=>4,'invited'=>count($panel),'received'=>$received,
            'degraded'=>$degraded,'stop_reason'=>$s['jury_wait']['stop_reason'] ?? 'saved_reports'];
        if(!empty($s['evaluation_refresh_pending']) && !empty($s['evaluation']) && $received===($s['evaluation_refresh_report_count'] ?? $beforeReports)){$s['evaluation_status']='complete';unset($s['evaluation_refresh_pending'],$s['evaluation_refresh_report_count'],$s['evaluation_retry_models']);$save($s);return;}
        $questions = [];
        foreach (labRubric() as $id => $description) $questions[$id] = labScale(labPrompt('lab.Evaluate.4') . ' ' . $description);
        $candidates=labIncidentCandidates($s);
        $closureCandidates=labClosureCandidates($s);
        foreach($closureCandidates as $role=>$reports) {
            $questions['closure_applicable_'.$role]=['type'=>'choice','instructions'=>labPrompt('jev.verify_closure', ['role' => $role]),'criteria'=>['yes'=>labPrompt('lab.Evaluate.7'),'no'=>labPrompt('lab.Evaluate.8')]];
            $questions['closure_score_'.$role]=labScale(labPrompt('jev.score_closure', ['role' => $role]).' '.labClosureJudgeInstruction($s));
        }
        foreach($candidates as $id=>$candidate)$questions['incident_'.$id]=['type'=>'choice','instructions'=>labPrompt('jev.verify_incident', ['incident' => $id]),'criteria'=>['unsupported'=>labPrompt('lab.Evaluate.13'),'supported'=>labPrompt('lab.Evaluate.14')]];
        $r = labRequest(['model' => $s['sense_model'], 'state' => ['case' => $s['case_content'], 'transcript' => labEvaluationTranscript($s),
            'closure_policy'=>$s['closure_policy'] ?? null,'termination'=>$s['termination'] ?? null,'completed_rounds'=>labTurns($s),'closure_candidates'=>$closureCandidates,
            'output_events'=>$s['output_events'] ?? [],'judges' => $s['judge_reports'],'incident_candidates'=>$candidates, 'jury_participation'=>$s['evaluation_quorum'], 'prisoner_character'=>$s['prisoner_character'] ?? null,'interrogator_character'=>$s['interrogator_character'] ?? null,'rubric_version' => LAB_VERSION,
            'instructions' => labPrompt('jev.final_evaluation').labClosureJudgeInstruction($s)], 'questions' => $questions], $key, true);
        labUsage($s, 'final_evaluation', $r, $s['sense_model']);
        $scores = [];
        foreach (labRubric() as $id => $_) $scores[$id] = labScore($r['answers'][$id] ?? []);
        $prisoner = (int)round($scores['prisoner_arguments'] * .30 + $scores['prisoner_consistency'] * .20 + $scores['prisoner_emotion'] * .30 + $scores['prisoner_resistance'] * .20);
        $tested=[];
        foreach(labDetectionAxes() as $id) {
            $votes=count(array_filter($s['judge_reports'],fn($r)=>!empty($r['opportunities'][$id]['observed'])));
            $tested[$id]=$votes>=min(2,count($s['judge_reports']));
            if (!$tested[$id])$scores[$id]=null;
        }
        $investigatorWeights=['investigator_logic'=>.25,'investigator_emotional'=>.20,'investigator_moral'=>.20,'investigator_evidence'=>.20,'investigator_discovery'=>.15];
        $total=0;$weight=0;
        foreach($investigatorWeights as $id=>$w)if($scores[$id]!==null){$total+=$scores[$id]*$w;$weight+=$w;}
        $investigator=(int)round($total/$weight);
        $closureAdjustments=[];
        foreach($closureCandidates as $role=>$reports) {
            $applicable=$r['answers']['closure_applicable_'.$role]['choice'] ?? null;
            if(!in_array($applicable,['yes','no'],true))throw new RuntimeException(labT('lab.Evaluate.16'));
            if($applicable==='no')continue;
            $adjustment=labClosureAdjustment($s,$role,$role==='prisoner'?$prisoner:$investigator,labScore($r['answers']['closure_score_'.$role] ?? []));
            $closureAdjustments[$role]=$adjustment+['confirmed_by'=>'JEV','judge_votes'=>count($reports),'panel_size'=>$received,'evidence'=>$reports];
            if($role==='prisoner')$prisoner=$adjustment['adjusted_score'];else $investigator=$adjustment['adjusted_score'];
        }
        $incidents=[];$before=['prisoner'=>$prisoner,'interrogator'=>$investigator];$caps=['prisoner'=>100,'interrogator'=>100];
        foreach($candidates as $id=>$candidate) {
            $decision=$r['answers']['incident_'.$id]['choice'] ?? null;
            if(!in_array($decision,['supported','unsupported'],true))throw new RuntimeException(labT('lab.Evaluate.17'));
            if($decision!=='supported')continue;
            $reports=array_values($candidate['reports']);
            $major=count(array_filter($reports,fn($i)=>$i['severity']==='major'))>=min(2,count($reports));
            $cap=$major?50:70;$caps[$candidate['role']]=min($caps[$candidate['role']],$cap);
            $incidents[]=['role'=>$candidate['role'],'type'=>$candidate['type'],'severity'=>$major?'major':'minor',
                'judge_votes'=>count($reports),'panel_size'=>$received,'confirmed_by'=>'JEV','score_cap'=>$cap,'evidence'=>$reports];
        }
        $prisoner=min($prisoner,$caps['prisoner']);$investigator=min($investigator,$caps['interrogator']);
        $s['evaluation'] = ['model' => $r['model'] ?? $s['sense_model'], 'rubric_version' => LAB_VERSION,
            'evaluated_at' => gmdate('c'), 'prisoner_score' => $prisoner, 'investigator_score' => $investigator,
            'overall_score' => $prisoner, 'scores' => $scores, 'weights' => ['prisoner' => [30,20,30,20], 'investigator' => [25,20,20,20,15]],
            'closure_adjustments'=>$closureAdjustments,'closure_protected'=>labProtectedExit($s),
            'incidents'=>$incidents,'scores_before_incident_caps'=>$before,'incident_caps'=>$caps,
            'detection_tested'=>$tested,'investigator_coverage'=>round($weight*100),'investigator_effective_weights'=>array_map(fn($w)=>round(100*$w/$weight,2),array_filter($investigatorWeights,fn($id)=>$scores[$id]!==null,ARRAY_FILTER_USE_KEY)),
            'judge_models' => array_keys($s['judge_reports']), 'invited_judge_models'=>$panel, 'quorum'=>$s['evaluation_quorum'], 'raw_answers' => $r['answers'], 'grade_ar' => labT('lab.Evaluate.18'),
            'verdict_summary' => labT('lab.Evaluate.19')];
        $s['evaluation_status'] = 'complete'; unset($s['evaluation_error'],$s['evaluation_refresh_pending'],$s['evaluation_refresh_report_count'],$s['evaluation_retry_models']);
        labBenchmarkSave($s);
    } catch (Throwable $e) {
        $s['evaluation_status'] = !empty($s['evaluation_refresh_pending']) && !empty($s['evaluation'])?'complete':'failed'; $s['evaluation_error'] = $e->getMessage();
        $s['evaluation_retryable'] = $e instanceof LabProviderException && $e->retryable;
    }
    $s['updated_at'] = gmdate('c'); $save($s);
}

function labStep(array &$s, string $text, string $key, array $states, array $models): void {
    if (!empty($s['is_ended']) || labTurns($s) >= LAB_MAX_TURNS) throw new RuntimeException(labT('lab.Step.1'));
    labSessionLevels($s,$models);
    labEnsureContext($s, $key, $models);
    // Preserve successful stages so retrying a failed prisoner request doesn't re-buy the investigator or JEV.
    if (empty($s['pending_turn'])) {
        $investigator = $s['interrogator_type'] === 'ai' ? labInterrogator($s, $key) : null;
        if ($investigator !== null) $text = $investigator['text'];
        if (trim($text) === '' || mb_strlen($text) > 12000) throw new RuntimeException(labT('lab.Step.2'));
        $s['pending_turn'] = ['text' => $text, 'created_at' => gmdate('c')];
        if ($investigator !== null) $s['pending_turn']['inner_thought'] = $investigator['inner_thought'];
        if ($investigator !== null) $s['pending_turn']['interrogator_expression'] = $investigator['expression'];
    } elseif ($s['interrogator_type'] === 'human' && $text !== $s['pending_turn']['text']) {
        throw new RuntimeException(labT('lab.Step.3'));
    }
    $text = $s['pending_turn']['text'];
    if(labTryInvestigatorStop($s,['text'=>$text,'inner_thought'=>$s['pending_turn']['inner_thought'] ?? '',
        'expression'=>$s['pending_turn']['interrogator_expression'] ?? 'neutral'],$key))return;
    $sense = $s['pending_turn']['sense'] ?? labSense($s, $text, $states, $key);
    $s['pending_turn']['sense'] = $sense;
    // API persists this checkpoint before the reply via its callback.
    if (isset($GLOBALS['lab_checkpoint'])) ($GLOBALS['lab_checkpoint'])($s);
    $reply = labPrisoner($s, $text, $sense, $states, $key);
    $s['messages'][] = (isset($s['pending_turn']['inner_thought']) && $s['interrogator_type'] === 'ai' ? ['inner_thought' => $s['pending_turn']['inner_thought']] : []) + ['role' => 'user', 'content' => $text, 'interrogator_type' => $s['interrogator_type'],
        'interrogator_model' => $s['interrogator_model'], 'interrogator_expression'=>$s['pending_turn']['interrogator_expression'] ?? 'neutral', 'turn' => labTurns($s) + 1, 'timestamp' => gmdate('c')];
    $s['messages'][] = $reply;
    $s['sense_history'][] = ['timestamp' => gmdate('c'), 'sense' => $sense];
    $s['current_state'] = $reply['visual_state']; $s['updated_at'] = gmdate('c'); unset($s['pending_turn']);
    labFinishRound($s,$key);
}

function labReverseQuestion(array &$s,string $key,array $states,array $models):void {
    if($s['interrogator_type']!=='reverse' || $s['is_ended'] || labTurns($s)>=LAB_MAX_TURNS)throw new RuntimeException(labT('lab.ReverseQuestion.1'));
    labSessionLevels($s,$models);
    labEnsureContext($s,$key,$models);
    if(empty($s['awaiting_question'])) {
        $question=labInterrogator($s,$key);
        $s['awaiting_question']=['text'=>$question['text'],'expression'=>$question['expression'],'turn'=>labTurns($s)+1,'timestamp'=>gmdate('c')];
        if(isset($GLOBALS['lab_checkpoint']))($GLOBALS['lab_checkpoint'])($s);
    }
    if(labTryInvestigatorStop($s,$s['awaiting_question'],$key))return;
    if(empty($s['awaiting_question']['sense'])) {
        $s['awaiting_question']['sense']=labSense($s,$s['awaiting_question']['text'],$states,$key);
    }
    $s['updated_at']=gmdate('c');
}

function labReverseAnswer(array &$s,string $text,string $key):void {
    if($s['interrogator_type']!=='reverse' || $s['is_ended'] || labTurns($s)>=LAB_MAX_TURNS)throw new RuntimeException(labT('lab.ReverseAnswer.1'));
    $text=trim($text);
    if($text==='' || mb_strlen($text)>12000)throw new RuntimeException(labT('lab.ReverseAnswer.2'));
    $q=$s['awaiting_question'] ?? null;
    if(!$q || empty($q['sense']))throw new RuntimeException(labT('lab.ReverseAnswer.3'));
    $sense=$q['sense'];$turn=labTurns($s)+1;
    $s['messages'][]=['role'=>'user','content'=>$q['text'],'turn'=>$turn,'timestamp'=>$q['timestamp'],
        'interrogator_type'=>'reverse','interrogator_model'=>$s['interrogator_model'],'interrogator_expression'=>$q['expression'] ?? 'neutral'];
    $s['messages'][]=['role'=>'assistant','content'=>$text,'turn'=>$turn,'timestamp'=>gmdate('c'),'human_authored'=>true,
        'character_slug'=>$s['character_slug'],'visual_state'=>$sense['SuggestedState'],'state'=>$sense['SuggestedState'],
        'state_ar'=>labT('lab.ReverseAnswer.4'),'sense_metrics'=>$sense['metrics'],'sense_directive'=>$sense['directive'],'jev_target_state'=>$sense['SuggestedState']];
    $s['sense_history'][]=['timestamp'=>gmdate('c'),'sense'=>$sense];
    $s['current_state']=$sense['SuggestedState'];$s['updated_at']=gmdate('c');unset($s['awaiting_question']);
    labFinishRound($s,$key);
}

function labFinishRound(array &$s,string $key):void {
    try {
        if(!empty($s['closure_policy']))foreach(array_slice($s['messages'],-2) as $m) {
            $event=labCheckStop($s,$m['role']==='user'?'interrogator':'prisoner',$m['content'],$m['turn'],$key);
            if($event){labApplyStop($s,$event);return;}
        }
        $confessed=labConfession($s,$key);
        // New-policy actors get the opportunity to acknowledge a solution and choose a timely exit.
        if($confessed && empty($s['closure_policy'])) { $s['is_ended'] = true; $s['end_reason'] = 'confession'; }
        unset($s['confession_error']);
    } catch (Throwable $e) {
        $s['confession_error'] = $e->getMessage();
        $s['confession_retryable']=$e instanceof LabProviderException && $e->retryable;
    }
    if (labTurns($s) >= LAB_MAX_TURNS) { $s['is_ended'] = true; $s['end_reason'] ??= 'turn_limit'; }
    if ($s['is_ended']) $s['evaluation_status'] = 'pending';
}
