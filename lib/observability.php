<?php
declare(strict_types=1);

class LabProviderException extends RuntimeException {
    public function __construct(string $message, public readonly bool $retryable = true) { parent::__construct($message); }
}

function labHealthRecord(string $model, bool $success, float $elapsed, ?float $firstByte, int $tokens, bool $retryable, string $outcome = '', ?string $requestId = null): string {
    $requestId ??= bin2hex(random_bytes(12));
    if (empty($GLOBALS['lab_storage'])) return $requestId;
    $file = $GLOBALS['lab_storage'] . '/model-health.json';
    $lock = fopen($file . '.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX)) return $requestId;
    try {
        $data = is_file($file) ? labRead($file) : [];
        $samples = $data[$model]['samples'] ?? [];
        $outcome=$outcome ?: ($success?'success':'transport_failure');
        $sample=['request_id'=>$requestId,'ok'=>$success,'outcome'=>$outcome,'seconds'=>round($elapsed,3),'first_byte_ms'=>$firstByte,
            'output_tokens'=>$tokens,'retryable'=>$retryable,'at'=>gmdate('c')];
        $entry=$data[$model] ?? [];
        $entry['totals'] ??= labHealthTotals($samples);
        $samples[]=$sample; $entry['totals']=labHealthAdd($entry['totals'],$sample,1);
        $entry['samples']=array_slice($samples,-100); $entry['tracking_since'] ??= gmdate('c');
        $data[$model]=$entry; labWrite($file,$data);
        @file_put_contents($GLOBALS['lab_storage'].'/model-requests.jsonl',json_encode(['model'=>$model]+$sample,JSON_UNESCAPED_UNICODE)."\n",FILE_APPEND|LOCK_EX);
    } catch (Throwable) { /* Observability cannot fail an otherwise successful model call. */ }
    finally {flock($lock,LOCK_UN);fclose($lock);}
    return $requestId;
}

function labHealthAdd(array $t,array $s,int $sign):array {
    foreach(['requests','successes','failures','transport_failures','invalid_responses','cancelled','seconds','tokens','first_byte_sum','first_byte_count','token_seconds'] as $k)$t[$k] ??= 0;
    $o=$s['outcome'] ?? ($s['ok']?'success':'transport_failure');$t['requests']+=$sign;
    if($o==='cancelled'){$t['cancelled']+=$sign;return $t;}
    if($o==='success') {
        $t['successes']+=$sign;$t['seconds']+=$sign*$s['seconds'];
        if(($s['output_tokens'] ?? 0)>0){$t['tokens']+=$sign*$s['output_tokens'];$t['token_seconds']+=$sign*$s['seconds'];}
        if(isset($s['first_byte_ms'])){$t['first_byte_sum']+=$sign*$s['first_byte_ms'];$t['first_byte_count']+=$sign;}
    }else{$t['failures']+=$sign;$t[$o==='invalid_response'?'invalid_responses':'transport_failures']+=$sign;}
    return $t;
}
function labHealthTotals(array $samples):array {$t=[];foreach($samples as $s)$t=labHealthAdd($t,$s,1);return $t;}

function labHealthInvalid(string $model,?string $id):void {
    if(!$id || empty($GLOBALS['lab_storage']))return;
    $file=$GLOBALS['lab_storage'].'/model-health.json';$lock=fopen($file.'.lock','c');if(!$lock || !flock($lock,LOCK_EX))return;
    try {
        $data=labRead($file);$entry=$data[$model] ?? null;if(!$entry)return;
        foreach($entry['samples'] as &$sample)if(($sample['request_id'] ?? '')===$id && ($sample['outcome'] ?? '')==='success') {
            $entry['totals']=labHealthAdd($entry['totals'],$sample,-1);
            $sample['ok']=false;$sample['outcome']='invalid_response';$entry['totals']=labHealthAdd($entry['totals'],$sample,1);
            @file_put_contents($GLOBALS['lab_storage'].'/model-requests.jsonl',json_encode(['event'=>'classification','model'=>$model]+$sample,JSON_UNESCAPED_UNICODE)."\n",FILE_APPEND|LOCK_EX);
            break;
        }
        unset($sample);$data[$model]=$entry;labWrite($file,$data);
    }catch(Throwable){}finally{flock($lock,LOCK_UN);fclose($lock);}
}

function labHealth(array $model, array $samples, ?array $totals = null): array {
    $samples = array_values(array_filter($samples, fn($s)=>strtotime($s['at']) >= time()-7*86400));
    $ok = array_values(array_filter($samples,fn($s)=>$s['ok']));
    $totals ??= labHealthTotals($samples);
    $failed = $totals['failures'] ?? 0;
    $seconds = count($ok) ? array_sum(array_column($ok,'seconds'))/count($ok) : null;
    $first = array_values(array_filter(array_column($ok,'first_byte_ms'),'is_numeric'));
    $tokenSamples = array_values(array_filter($ok,fn($s)=>$s['output_tokens']>=30 && $s['seconds']>0));
    $tps = $tokenSamples ? array_sum(array_column($tokenSamples,'output_tokens'))/array_sum(array_column($tokenSamples,'seconds')) : null;
    $reasons = [];
    if ($failed) $reasons[] = "تعثر {$failed} طلب من ".(($totals['successes'] ?? 0)+$failed).' طلب مكتمل مسجل';
    if ($seconds !== null && $seconds > 45) $reasons[]='زمن استجابة مرتفع';
    if ($tps !== null && $tps < 10) $reasons[]='معدل إخراج منخفض';
    $limit = $model['top_provider']['max_completion_tokens'] ?? null;
    if (is_numeric($limit) && $limit < 1800) $reasons[]='حد إخراج قصير: ' . $limit . ' توكن';
    if (($model['context_length'] ?? 0) < 16384) $reasons[]='سياق صغير لهذه التجربة';
    $expiry = !empty($model['expiration_date']) ? strtotime($model['expiration_date']) : false;
    if ($expiry && $expiry < time()+7*86400) $reasons[]='موعد إيقاف الخدمة قريب';
    $completed=($totals['successes'] ?? 0)+$failed;
    $rate=$completed ? 100*$failed/$completed : null;
    return ['weak'=>$rate!==null && $rate>=30,'reasons'=>$reasons,'samples'=>count($samples),'failures'=>$failed,
        'requests'=>$totals['requests'] ?? 0,'successes'=>$totals['successes'] ?? 0,'cancelled'=>$totals['cancelled'] ?? 0,
        'transport_failures'=>$totals['transport_failures'] ?? 0,'invalid_responses'=>$totals['invalid_responses'] ?? 0,
        'failure_rate'=>$rate===null?null:round($rate,1),'reliability_band'=>$rate===null?'unknown':($rate>=30?'orange':($rate>=10?'yellow':'green')),
        'sample_confidence'=>$completed>=10?'established':'limited',
        'avg_seconds'=>empty($totals['successes'])?null:round($totals['seconds']/$totals['successes'],2),
        'first_byte_ms'=>empty($totals['first_byte_count'])?null:(int)round($totals['first_byte_sum']/$totals['first_byte_count']),
        'tokens_per_second'=>empty($totals['token_seconds'])?null:round($totals['tokens']/$totals['token_seconds'],1),'max_output_tokens'=>$limit];
}

/** No identity, case file or secret thinking is admitted to either conversation memory. */
function labSpoken(array $messages): array {
    return array_map(fn($m)=>array_intersect_key($m,array_flip(['role','content','turn'])), $messages);
}

function labNotes(array $s, string $audience): array {
    $out = [];
    foreach ($s['memory_notes'] ?? [] as $note) {
        if (($note['audience'] ?? '') === $audience) $out[] = $note;
    }
    return $out;
}

function labDetectionAxes(): array {return ['investigator_logic','investigator_emotional','investigator_moral'];}

function labEvaluationTranscript(array $s): array {
    $messages=$s['messages'];
    foreach($messages as $i=>&$m){unset($m['inner_thought'],$m['reasoning'],$m['reasoning_content']);$m['message_id']=($m['role']==='user'?'u':'a').($m['turn'] ?? (int)($i/2)+1);}
    return $messages;
}

function labEvidenceMessage(array $s,string $id):array {
    if(!preg_match('/^([ua])([1-9][0-9]*)$/D',$id,$m))throw new RuntimeException('معرف دليل غير صالح: '.$id);
    $turn=(int)$m[2];$role=$m[1]==='u'?'user':'assistant';
    $source=$s['messages'][2*($turn-1)+($role==='assistant'?1:0)] ?? null;
    if(!$source || $source['role']!==$role)throw new RuntimeException('مرجع دليل غير موجود: '.$id);
    return ['turn'=>$turn,'role'=>$role,'quote'=>$source['content'],'quote_source'=>'transcript_reference'];
}
