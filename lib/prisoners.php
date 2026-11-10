<?php
declare(strict_types=1);

/** Difficulty belongs to the case persona, never to a claim made in chat. */
function labPrisonerCharacter(string $case):array {
    $levels=labLanguageJson('data/personalities/prisoners.json', LAB_DEFAULT_LOCALE)['levels'];
    if(!isset($levels[$case]))throw new RuntimeException(labT('prisoners.PrisonerCharacter.1'));
    $n=$levels[$case];
    if($n<=3) {
        $label=labT('prisoners.PrisonerCharacter.2');$thinking=$n===1?'none':'low';
        $resistance=labPrompt('prisoners.PrisonerCharacter.3');
    }elseif($n<=5) {
        $label=labT('prisoners.PrisonerCharacter.4');$thinking=$n<=3?'low':'medium';
        $resistance=labPrompt('prisoners.PrisonerCharacter.5');
    }elseif($n<=7) {
        $label=labT('prisoners.PrisonerCharacter.6');$thinking='high';
        $resistance=labPrompt('prisoners.PrisonerCharacter.7');
    }else {
        $label=labT('prisoners.PrisonerCharacter.8');$thinking='highest';
        $resistance=labPrompt('prisoners.PrisonerCharacter.9');
    }
    return ['id'=>pathinfo($case,PATHINFO_FILENAME),'level'=>$n,'level_ar'=>$label,'thinking'=>$thinking,'resistance'=>$resistance,'version'=>1];
}

function labPrisonerDirective(array $p):string {
    return labPrompt('prisoner.personality', ['personality' => labJson($p)]);
}

function labActorOutputBudget(array $model):int {
    return max(256,min(12000,(int)($model['top_provider']['max_completion_tokens'] ?? 12000),(int)(($model['context_length'] ?? 32000)/3)));
}

/** Upgrade unfinished legacy sessions without rewriting past turns or their evaluation. */
function labSessionLevels(array &$s,array $models):void {
    $s['prisoner_character'] ??= labPrisonerCharacter($s['case_file']);
    if($s['interrogator_type']!=='reverse') {
        $m=labModel($s['model'],$models,true);
        $s['prisoner_reasoning'] ??= labInvestigatorReasoning($s['prisoner_character'],$m);
        $s['prisoner_output_budget']=labActorOutputBudget($m);
    }
    if($s['interrogator_type']!=='human') {
        $s['interrogator_character'] ??= labInvestigator('ethan_cole');
        $m=labModel($s['interrogator_model'],$models,true);
        $s['interrogator_reasoning'] ??= labInvestigatorReasoning($s['interrogator_character'],$m);
        $s['interrogator_output_budget']=labActorOutputBudget($m);
    }
    $s['level_policy_version']=1;
    labEnsureClosurePolicy($s);
}
