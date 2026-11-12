<?php
declare(strict_types=1);

/** Funding is resolved server-side before any panel mutation or paid evaluation. */
function labJuryCap(array $s):?float {
    if(($s['funding_source'] ?? '')==='personal')return null;
    return (float)($GLOBALS['lab_actor_price_cap'] ?? $s['actor_price_cap_at_start'] ?? 1.);
}
function labJuryModel(array $s,string $id,array $catalog):array {
    if(str_starts_with($id,'mcp/'))throw new RuntimeException(labT('jury.selection.invalid'));
    $m=labModel($id,$catalog,true);$cap=labJuryCap($s);
    if(!empty($m['catalog_unavailable']) || (isset($m['architecture']['output_modalities']) && !in_array('text',$m['architecture']['output_modalities'],true)))throw new RuntimeException(labT('jury.selection.invalid'));
    foreach(['prompt_per_1m','completion_per_1m'] as $field){$price=$m['pricing'][$field] ?? null;
        if(!is_numeric($price) || $price<0 || ($cap!==null && $price>$cap))throw new RuntimeException(labT('jury.selection.budget'));
    }
    $byId=array_column($catalog,null,'id');
    foreach(labActorModels($s) as $actor)if($id===$actor || (!empty($m['canonical_slug']) && $m['canonical_slug']===($byId[$actor]['canonical_slug'] ?? null)))throw new RuntimeException(labT('jury.selection.actor'));
    return $m;
}
/** Preserve accepted reports, including historical three-seat panels. */
function labJurySlots(array $s):array {return array_pad(array_slice($s['jury_slots'] ?? $s['judge_models'] ?? [],0,4),4,null);}
function labSetJury(array &$s,mixed $input,array $catalog,bool $fill=false):void {
    if(!is_array($input) || !array_is_list($input) || count($input)!==4)throw new RuntimeException(labT('jury.selection.invalid'));
    $selected=[];$slugs=[];$old=labJurySlots($s);
    foreach($old as $i=>$id)if($id!==null && isset($s['judge_reports'][$id]) && $input[$i]!==$id)throw new RuntimeException(labT('jury.selection.complete'));
    foreach($input as $id){if($id===null)continue;if(!is_string($id))throw new RuntimeException(labT('jury.selection.invalid'));
        $m=isset($s['judge_reports'][$id])?(array_column($catalog,null,'id')[$id] ?? ['id'=>$id]):labJuryModel($s,$id,$catalog);$slug=$m['canonical_slug'] ?? $id;
        if(in_array($id,$selected,true) || in_array($slug,$slugs,true))throw new RuntimeException(labT('jury.selection.duplicate'));
        $selected[]=$id;$slugs[]=$slug;
    }
    if($fill){$defaults=in_array(null,$input,true)?labPanel($s+['jury_excluded_models'=>$selected],$catalog):[];foreach($input as &$id)if($id===null){foreach($defaults as $candidate)if(!in_array($candidate,$input,true)){$id=$candidate;break;}}unset($id);labSetJury($s,$input,$catalog,false);return;}
    if(!empty($s['evaluation_refresh_pending'])){
        if(count($s['judge_reports'])>($s['evaluation_refresh_report_count'] ?? 0))throw new RuntimeException(labT('jury.retry.finalize'));
        unset($s['evaluation_refresh_pending'],$s['evaluation_refresh_report_count'],$s['evaluation_retry_models']);
        foreach($s['jury_retry_requests'] ?? [] as $rid=>$state)$s['jury_retry_requests'][$rid]='complete';
    }
    $s['jury_slots']=$input;$s['judge_models']=array_values(array_filter($input,fn($id)=>$id!==null));
    foreach(['judge_progress','judge_failures','judge_invalid_reports','judge_price_caps'] as $field)foreach(array_keys($s[$field] ?? []) as $id)if(!in_array($id,$s['judge_models'],true))unset($s[$field][$id]);
    $s['jury_policy']=labJuryPolicy($s,$catalog);
}
function labPrepareJury(array &$s,array $catalog):void {
    if(!empty($s['judge_models'])){
        $s['jury_slots']=labJurySlots($s);
        $s['jury_policy']=labJuryPolicy($s,$catalog);
        return;
    }
    if(isset($s['jury_slots']))throw new RuntimeException(labT('jury.selection.invalid'));
    labSetJury($s,[null,null,null,null],$catalog,true);
}
/** Explicit recovery never discards a successful report or silently changes a historical grade. */
function labRetryJury(array &$s,string $requestId,array $catalog,mixed $slots=null):bool {
    if(!preg_match('/^[a-zA-Z0-9_-]{8,100}$/D',$requestId))throw new RuntimeException(labT('jury.retry.request'));
    $prior=$s['jury_retry_requests'][$requestId] ?? null;
    if($prior==='complete')return false;
    if($prior==='running')return true;
    if(!empty($s['evaluation_refresh_pending'])){$s['jury_retry_requests'][$requestId]='running';return true;}
    if(labTurns($s)<1 || empty($s['is_ended']))throw new RuntimeException(labT('jury.retry.ended'));
    if(!empty($s['evaluation']) && ($s['evaluation']['rubric_version'] ?? '')!==LAB_VERSION)throw new RuntimeException(labT('jury.retry.rubric'));
    labPrepareJury($s,$catalog);
    $missing=array_values(array_diff($s['judge_models'],array_keys($s['judge_reports'] ?? [])));
    if($slots!==null){
        if(!is_array($slots) || !array_is_list($slots) || !$slots || count($slots)>4)throw new RuntimeException(labT('jury.selection.invalid'));
        $targets=[];foreach($slots as $slot){if(!is_int($slot) || $slot<0 || $slot>3)throw new RuntimeException(labT('jury.selection.invalid'));$id=$s['jury_slots'][$slot];if(!$id || !in_array($id,$missing,true))throw new RuntimeException(labT('jury.selection.complete'));$targets[]=$id;}
        $missing=array_values(array_unique($targets));
    }
    if(!$missing)throw new RuntimeException(labT('jury.retry.full'));
    if(!empty($s['evaluation']))$s['evaluation_history'][]=['evaluation'=>$s['evaluation'],'quorum'=>$s['evaluation_quorum'] ?? null,'jury_wait'=>$s['jury_wait'] ?? null,'requested_at'=>gmdate('c'),'request_id'=>$requestId];
    $s['jury_retry_requests'][$requestId]='running';$s['evaluation_refresh_pending']=true;$s['evaluation_retry_models']=$missing;$s['evaluation_refresh_report_count']=count($s['judge_reports'] ?? []);unset($s['evaluation_quorum']);
    return true;
}

function labJuryResult(array &$s,array $job,?array $response,string $error,float $elapsed,?float $firstByte,?string $existingCall,callable $save):void {
    $id=$job['model'];$tokens=(int)($response['usage']['completion_tokens'] ?? 0);
    $attempt=$s['judge_progress'][$id]['attempt'] ?? 1;
    $call=$existingCall ?? labHealthRecord($id,$error==='', $elapsed,$firstByte,$tokens,true);
    try {
        if($error!=='')throw new LabProviderException($error);
        labUsage($s,'judge',$response,$id);
        if(($response['choices'][0]['finish_reason'] ?? '')==='length')throw new RuntimeException(labT('jury.JuryResult.1'));
        $raw=$response['choices'][0]['message']['content'] ?? '';
        if(!is_string($raw) || trim($raw)==='')throw new RuntimeException(labT('jury.JuryResult.2'));
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
function labJuryStop(int $reports,float $elapsed,int $seconds=100,int $invited=4):?string {
    if($reports>=$invited && $elapsed>=30)return 'all_reports';
    return $elapsed>=$seconds?'deadline':null;
}

function labJuryRetryJob(array $s,array $job):array {
    $invalid=$s['judge_invalid_reports'][$job['model']] ?? null;
    if($invalid) {
        $job['payload']['messages']=labJudgeMessages($s);
        $job['payload']['messages'][]=['role'=>'assistant','content'=>$invalid['raw']];
        $job['payload']['messages'][]=['role'=>'user','content'=>labPrompt('jury.retry', ['validation_error' => $invalid['error']])];
    }
    return $job;
}

/** Only terminated requests are retried; a live cURL handle is left generating. */
function labJuryWave(array &$s,array $jobs,string $key,callable $save,bool $verifyAll=false):void {
    if(!$jobs)return;
    $mock=isset($GLOBALS['lab_transport']);
    if(!$mock && $key==='')throw new RuntimeException(labT('jury.JuryWave.1'));
    $multi=$mock?null:curl_multi_init();$active=[];$attempts=[];$byModel=[];
    $expected=count($s['judge_reports'])+count($jobs);
    $started=labJuryNow();$reason=null;$nextCheck=30.;$seconds=(int)($s['jury_policy']['seconds'] ?? 100);
    $s['jury_wait']=['total_seconds'=>$seconds,'phase'=>'reviewing','started_at'=>gmdate('c'),'deadline_at'=>microtime(true)+$seconds,'tier'=>$s['jury_policy']['tier'] ?? 'economic','check_interval_seconds'=>30];
    $launch=function(array $job)use(&$s,&$active,&$attempts,$multi,$mock,$key,$started,$seconds):void {
        $id=$job['model'];
        $attempts[$id]=1+($attempts[$id] ?? 0);
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
            if($code!==CURLM_OK)throw new RuntimeException(labT('jury.JuryWave.2'));
            while($info=curl_multi_info_read($multi)) {
                $ch=$info['handle'];$hid=spl_object_id($ch);$item=$active[$hid];$status=curl_getinfo($ch,CURLINFO_HTTP_CODE);
                $raw=curl_multi_getcontent($ch);$r=is_string($raw)?json_decode($raw,true):null;
                if($status!==200 && isset($r['error']['message'])) {
                    $detail=str_replace($key,'[redacted]',(string)$r['error']['message']);
                    @file_put_contents($GLOBALS['lab_storage'].'/provider-errors.jsonl',json_encode(['model'=>$item['job']['model'],'status'=>$status,'message'=>mb_substr($detail,0,1000),'at'=>gmdate('c')],JSON_UNESCAPED_UNICODE)."\n",FILE_APPEND|LOCK_EX);
                }
                $error=$info['result']!==CURLE_OK?labT('jury.JuryWave.3').curl_strerror($info['result']):($status!==200?labT('jury.JuryWave.4', ['status' => $status]):(!is_array($r)||isset($r['error'])?labT('jury.JuryWave.5'):''));
                labJuryResult($s,$item['job'],$r,$error,labJuryNow()-$item['start'],round(curl_getinfo($ch,CURLINFO_STARTTRANSFER_TIME)*1000),null,$save);
                curl_multi_remove_handle($multi,$ch);curl_close($ch);unset($active[$hid]);
            }
            }
            $elapsed=labJuryNow()-$started;
            if($elapsed>=$nextCheck || $elapsed>=$seconds){$reason=labJuryStop(count($s['judge_reports']),$elapsed,$seconds,$expected);$nextCheck=(floor($elapsed/30)+1)*30;}
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
