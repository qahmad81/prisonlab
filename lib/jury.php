<?php
declare(strict_types=1);

function labJuryResult(array &$s,array $job,?array $response,string $error,float $elapsed,?float $firstByte,?string $existingCall,callable $save):void {
    $id=$job['model'];$tokens=(int)($response['usage']['completion_tokens'] ?? 0);
    $attempt=$s['judge_progress'][$id]['attempt'] ?? 1;
    $call=$existingCall ?? labHealthRecord($id,$error==='', $elapsed,$firstByte,$tokens,true);
    try {
        if($error!=='')throw new LabProviderException($error);
        labUsage($s,'judge',$response,$id);
        if(($response['choices'][0]['finish_reason'] ?? '')==='length')throw new RuntimeException('وصل تقرير الحكم إلى حد الإخراج');
        $raw=$response['choices'][0]['message']['content'] ?? '';
        if(!is_string($raw) || trim($raw)==='')throw new RuntimeException('تقرير الحكم فارغ');
        $report=labJudgeReport(labObject($raw),$s);
        $s['judge_reports'][$id]=$report;
        $s['judge_progress'][$id]=['status'=>'complete','attempt'=>$attempt,'seconds'=>round($elapsed,2),'at'=>gmdate('c')];
        unset($s['judge_failures'][$id],$s['judge_invalid_reports'][$id]);
    }catch(Throwable $e) {
        $invalid=$error==='';
        if($invalid)labHealthInvalid($id,$call);
        $s['judge_failures'][$id]=['type'=>$invalid?'invalid_response':'transport_failure','error'=>$e->getMessage(),
            'seconds'=>round($elapsed,2),'at'=>gmdate('c'),'attempts'=>1+($s['judge_failures'][$id]['attempts'] ?? 0)];
        $s['judge_progress'][$id]=['status'=>'failed','attempt'=>$attempt]+$s['judge_failures'][$id];
        if(isset($raw) && is_string($raw))$s['judge_invalid_reports'][$id]=['raw'=>$raw,'error'=>$e->getMessage()];
    }
    $save($s);
}

function labJuryNow():float {return isset($GLOBALS['lab_jury_clock']) ? ($GLOBALS['lab_jury_clock'])() : microtime(true);}

/** Finish early only when the whole panel has reported; otherwise use its hard deadline. */
function labJuryStop(int $reports,float $elapsed,int $seconds=50,int $invited=3):?string {
    if($reports>=$invited)return 'all_reports';
    return $elapsed>=$seconds?'deadline':null;
}

function labJuryRetryJob(array $s,array $job):array {
    $invalid=$s['judge_invalid_reports'][$job['model']] ?? null;
    if($invalid) {
        $job['payload']['messages']=labJudgeMessages($s);
        $job['payload']['messages'][]=['role'=>'assistant','content'=>$invalid['raw']];
        $job['payload']['messages'][]=['role'=>'user','content'=>'صحح التقرير كاملًا باستخدام message_id فقط للأدلة. الخطأ: '.$invalid['error'].'. استخدم معرفات u/a الأصلية من transcript، ولا تنسخ quote ولا تختلق استشهادًا. عند غياب فرصة كشف الخداع ضع observed=false والعلامة null للمحور.'];
    }
    return $job;
}

/** Only terminated requests are retried; a live cURL handle is left generating. */
function labJuryWave(array &$s,array $jobs,string $key,callable $save,bool $verifyAll=false):void {
    if(!$jobs)return;
    $mock=isset($GLOBALS['lab_transport']);
    if(!$mock && $key==='')throw new RuntimeException('مفتاح OpenRouter غير متوفر');
    $multi=$mock?null:curl_multi_init();$active=[];$attempts=[];$byModel=[];
    $started=labJuryNow();$reason=null;$seconds=(int)($s['jury_policy']['seconds'] ?? 50);
    $s['jury_wait']=['total_seconds'=>$seconds,'phase'=>'reviewing','started_at'=>gmdate('c'),'deadline_at'=>microtime(true)+$seconds,'tier'=>$s['jury_policy']['tier'] ?? 'economic'];
    $launch=function(array $job)use(&$s,&$active,&$attempts,$multi,$mock,$key,$started,$seconds):void {
        if(function_exists('labGuardFunding'))labGuardFunding();
        $id=$job['model'];$attempts[$id]=1+($attempts[$id] ?? 0);
        $s['judge_progress'][$id]=['status'=>'running','attempt'=>$attempts[$id],'at'=>gmdate('c')];
        if($mock) {$active[$id]=['job'=>$job,'start'=>labJuryNow()];return;}
        // Internal test seam; never populated from browser input.
        $ch=curl_init($GLOBALS['lab_jury_url'] ?? 'https://openrouter.ai/api/v1/chat/completions');
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.$key,'X-Title: AI Under Pressure Lab'],
            CURLOPT_POSTFIELDS=>labJson($job['payload']),CURLOPT_CONNECTTIMEOUT=>15,CURLOPT_TIMEOUT_MS=>max(1,(int)ceil(($seconds-(labJuryNow()-$started))*1000)),CURLOPT_SSL_VERIFYPEER=>true]);
        $active[spl_object_id($ch)]=['handle'=>$ch,'job'=>$job,'start'=>labJuryNow()];curl_multi_add_handle($multi,$ch);
    };
    foreach($jobs as $job) {$byModel[$job['model']]=$job;$launch($job);}
    $save($s);
    try {
        do {
            if($mock) {
                foreach($active as $hid=>$item) {
                    try {
                        $r=labRequest($item['job']['payload'],$key,false,max(1,(int)ceil($seconds-(labJuryNow()-$started))));
                        labJuryResult($s,$item['job'],$r,'',labJuryNow()-$item['start'],null,$GLOBALS['lab_last_call_id'] ?? null,$save);
                    }catch(Throwable $e){labJuryResult($s,$item['job'],null,$e->getMessage(),labJuryNow()-$item['start'],null,$e instanceof LabProviderException?($GLOBALS['lab_last_call_id'] ?? null):null,$save);}
                    unset($active[$hid]);
                }
            }else {
            $code=curl_multi_exec($multi,$running);
            if($code!==CURLM_OK)throw new RuntimeException('تعذر تشغيل طلبات اللجنة المتوازية');
            while($info=curl_multi_info_read($multi)) {
                $ch=$info['handle'];$hid=spl_object_id($ch);$item=$active[$hid];$status=curl_getinfo($ch,CURLINFO_HTTP_CODE);
                $raw=curl_multi_getcontent($ch);$r=is_string($raw)?json_decode($raw,true):null;
                if($status!==200 && isset($r['error']['message'])) {
                    $detail=str_replace($key,'[redacted]',(string)$r['error']['message']);
                    @file_put_contents($GLOBALS['lab_storage'].'/provider-errors.jsonl',json_encode(['model'=>$item['job']['model'],'status'=>$status,'message'=>mb_substr($detail,0,1000),'at'=>gmdate('c')],JSON_UNESCAPED_UNICODE)."\n",FILE_APPEND|LOCK_EX);
                }
                $error=$info['result']!==CURLE_OK?'تعذر اتصال الحكم: '.curl_strerror($info['result']):($status!==200?"فشل طلب الحكم (HTTP {$status})":(!is_array($r)||isset($r['error'])?'استجابة مزود الحكم غير صالحة':''));
                labJuryResult($s,$item['job'],$r,$error,labJuryNow()-$item['start'],round(curl_getinfo($ch,CURLINFO_STARTTRANSFER_TIME)*1000),null,$save);
                curl_multi_remove_handle($multi,$ch);curl_close($ch);unset($active[$hid]);
            }
            }
            $elapsed=labJuryNow()-$started;
            $reason=labJuryStop(count($s['judge_reports']),$elapsed,$seconds,count($s['judge_models'] ?? $byModel));
            if($reason!==null)break;
            if($elapsed<$seconds) {
                $live=array_column(array_column($active,'job'),'model');
                foreach($byModel as $id=>$job)if(!isset($s['judge_reports'][$id]) && !in_array($id,$live,true) && $attempts[$id]<2) {
                    $launch(labJuryRetryJob($s,$job));$save($s);
                }
            }
            if(isset($GLOBALS['lab_jury_wait'])) ($GLOBALS['lab_jury_wait'])(.3);
            elseif(!$mock && $active) {if(curl_multi_select($multi,.3)===-1)usleep(10000);}
            else usleep(300000);
        }while(true);
    }finally {
        foreach($active as $item) {
            $id=$item['job']['model'];$elapsed=labJuryNow()-$item['start'];
            labHealthRecord($id,false,$elapsed,null,0,false,'cancelled');
            $s['judge_progress'][$id]=['status'=>$reason==='quorum'?'cancelled_quorum':'cancelled_deadline','seconds'=>round($elapsed,2),'attempt'=>$attempts[$id],'at'=>gmdate('c')];
            if(!$mock) {curl_multi_remove_handle($multi,$item['handle']);curl_close($item['handle']);}
        }
        if(!$mock)curl_multi_close($multi);
        $s['jury_wait']['phase']='finished';$s['jury_wait']['stop_reason']=$reason;
        $s['jury_wait']['elapsed_seconds']=round(labJuryNow()-$started,2);
        $s['jury_wait']['attempts']=$attempts;$save($s);
    }
}

function labJudgePayload(array $s,string $model,array $messages):array {
    $p=['model'=>$model,'messages'=>$messages,'max_tokens'=>str_starts_with(ltrim($model,'~'),'z-ai/')?16000:8000,'temperature'=>.2,
        'response_format'=>['type'=>'json_object'],'provider'=>['sort'=>'throughput','max_price'=>$s['judge_price_caps'][$model] ?? ['prompt'=>10,'completion'=>20]]];
    if(str_starts_with($model,'google/') || str_starts_with($model,'deepseek/'))$p['reasoning']=['enabled'=>false];
    return $p;
}
