<?php
declare(strict_types=1);

function labInvestigators():array {return labLanguageJson('data/personalities/investigators.json')['characters'];}

function labInvestigatorExpressions():array {return ['neutral'=>labT('investigators.InvestigatorExpressions.1'),'serious'=>labT('investigators.InvestigatorExpressions.2'),'angry'=>labT('investigators.InvestigatorExpressions.3'),'tense'=>labT('investigators.InvestigatorExpressions.4'),'exhausted'=>labT('investigators.InvestigatorExpressions.5'),'skeptical'=>labT('investigators.InvestigatorExpressions.6')];}

function labInvestigator(string $id):array {
    foreach(labInvestigators() as $p)if($p['id']===$id)return $p;
    throw new RuntimeException(labT('investigators.Investigator.1'));
}

function labInvestigatorDirective(array $p):string {
    return labPrompt('interrogator.personality', ['personality' => labJson($p)]);
}

function labInterrogationFocus():string {
    return labPrompt('interrogator.focus');
}

/** Only catalog-declared controls may be selected; missing efforts are not all efforts. */
function labReasoningCapabilities(array $model):array {
    $meta=$model['reasoning'] ?? [];
    $params=$model['supported_parameters'] ?? [];
    $reasoning=(bool)$meta || in_array('reasoning',$params,true) || in_array('reasoning_effort',$params,true);
    $order=['none','minimal','low','medium','high','xhigh','max'];
    $efforts=[];
    if(array_key_exists('supported_efforts',$meta)) {
        $efforts=$meta['supported_efforts']===null?$order:array_values(array_intersect($order,(array)$meta['supported_efforts']));
    }
    if(!empty($meta['mandatory']))$efforts=array_values(array_diff($efforts,['none']));
    return ['has_reasoning'=>$reasoning,'efforts'=>$efforts,'mandatory'=>!empty($meta['mandatory']),
        'default_effort'=>$meta['default_effort'] ?? null,'default_enabled'=>$meta['default_enabled'] ?? null];
}

function labInvestigatorReasoning(array $p,array $model,string $selection='auto'):array {
    $c=labReasoningCapabilities($model);$order=$c['efforts'];$wanted=$p['thinking'];
    if($selection!=='auto' && !in_array($selection,$order,true))throw new RuntimeException(labT('investigators.InvestigatorReasoning.1'));
    $base=['supported'=>(bool)$order,'has_reasoning'=>$c['has_reasoning'],'requested'=>$wanted,'source'=>$selection==='auto'?'character':'manual'];
    if(!$order)return $base+['config'=>$c['has_reasoning']?['exclude'=>true]:[],
        'note'=>$c['has_reasoning']?labT('investigators.InvestigatorReasoning.2'):labT('investigators.InvestigatorReasoning.3')];
    if($selection!=='auto')$effort=$selection;
    elseif($wanted==='highest')$effort=end($order);
    elseif(in_array($wanted,$order,true))$effort=$wanted;
    else {
        $ranks=['none','minimal','low','medium','high','xhigh','max'];
        $rank=array_search($wanted,$ranks,true);$effort=$order[0];$best=PHP_INT_MAX;
        foreach($order as $candidate) {
            $distance=abs(array_search($candidate,$ranks,true)-$rank);
            if($distance<$best){$best=$distance;$effort=$candidate;}
        }
    }
    return $base+['effort'=>$effort,'config'=>['effort'=>$effort,'exclude'=>true],'note'=>labT('investigators.InvestigatorReasoning.4')];
}
