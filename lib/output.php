<?php
declare(strict_types=1);

/** Visible dialogue limits are independent of provider reasoning budgets. */
function labOutputLimits(string $role):array {
    return $role==='prisoner'?['characters'=>1000,'words'=>160]:['characters'=>500,'words'=>80];
}
function labOutputInstruction(string $role):string {
    $l=labOutputLimits($role);
    return "\nحد الإخراج المنطوق: {$l['words']} كلمة و{$l['characters']} محرف كحد أقصى، أيهما أسبق. استهدف نصف هذا الحد، وأغلق JSON كاملاً. الملاحظة التمثيلية اختيارية عند ضيق المساحة، جملة واحدة لا تتجاوز 240 محرفاً. لا تسرد استدلالك الخاص. أجب عن النقطة الجديدة دون إعادة مقدمة أو دفاع سبق قوله.";
}
function labOutputEvent(array &$s,string $role,string $model,string $kind,array $details=[]):void {
    $s['output_events'][]=['id'=>'o'.(count($s['output_events'] ?? [])+1),'role'=>$role,'model'=>$model,
        'turn'=>labTurns($s)+1,'kind'=>$kind,'at'=>gmdate('c')]+$details;
}
function labVisibleOutput(array &$s,string $role,string $model,string $text):string {
    $text=trim($text);$l=labOutputLimits($role);
    $characters=mb_strlen($text);$words=count(preg_split('/\s+/u',$text,-1,PREG_SPLIT_NO_EMPTY));
    if($characters>$l['characters'] || $words>$l['words']) {
        labOutputEvent($s,$role,$model,'visible_limit',['characters'=>$characters,'words'=>$words,'limits'=>$l]);
        labHealthInvalid($model,$GLOBALS['lab_last_call_id'] ?? null);
        throw new LabProviderException('تجاوز النموذج حد الرد المنطوق؛ سيعاد طلب جواب موجز مع حفظ المراحل المكتملة.');
    }
    return $text;
}
function labSpectatorNote(array &$s,string $role,string $model,mixed $note):string {
    if(!is_string($note) || trim($note)==='') {
        labOutputEvent($s,$role,$model,'missing_note');
        return ''; // A missing optional spectator note must not discard a valid spoken answer.
    }
    return mb_substr(trim($note),0,240);
}
