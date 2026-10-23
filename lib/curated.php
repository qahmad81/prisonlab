<?php
declare(strict_types=1);

/** Shortlists use publisher namespaces and catalog dates, never guessed product names. */
function labCurated(array $models):array {
    $families=['openai'=>'GPT','anthropic'=>'Claude','google'=>'Gemini','deepseek'=>'DeepSeek','z-ai'=>'GLM',
        'qwen'=>'Qwen','mistralai'=>'Mistral','x-ai'=>'Grok','xiaomi'=>'MiMo','minimax'=>'MiniMax'];
    $groups=[];
    foreach($models as $m) {
        [$publisher,$slug]=array_pad(explode('/',$m['id'],2),2,'');
        if(!isset($families[$publisher]) || !empty($m['catalog_unavailable']) || str_contains($slug,':')
            || preg_match('/(?:quant|\bgguf\b|\bfp[48]\b|\bint[48]\b|abliterat|finetun|distill|embed|audio|realtime|image|search|research|coder|codex|computer-use|guard|moderation|safety|ultraspeed|build-)/i',$slug))continue;
        if(($publisher==='openai' && !str_starts_with($slug,'gpt-')) || ($publisher==='google' && !str_starts_with($slug,'gemini-'))
            || ($publisher==='anthropic' && !str_starts_with($slug,'claude-')))continue;
        if($publisher==='openai' && preg_match('/-(?:luna|sol|astra|terra)-pro$/',$slug))continue;
        $price=max((float)($m['pricing']['prompt_per_1m'] ?? INF),(float)($m['pricing']['completion_per_1m'] ?? INF));
        if($price<=0 || !is_finite($price))continue;
        // Product tiers within each family: price caps remain separate access controls.
        $tier=match($publisher) {
            'openai'=>preg_match('/luna|mini|nano/',$slug)?'focus_economic':(preg_match('/astra|pro(?:-|$)/',$slug)?'focus_high':'focus_medium'),
            'anthropic'=>str_contains($slug,'haiku')?'focus_economic':(str_contains($slug,'opus')?'focus_high':'focus_medium'),
            'google'=>str_contains($slug,'lite')?'focus_economic':(str_contains($slug,'flash')?'focus_medium':'focus_high'),
            'mistralai'=>preg_match('/small|ministral/',$slug)?'focus_economic':(str_contains($slug,'large')?'focus_high':'focus_medium'),
            'deepseek'=>str_contains($slug,'flash')?'focus_economic':(preg_match('/max|prime|pro/',$slug)?'focus_high':'focus_medium'),
            'z-ai'=>str_contains($slug,'flash')?'focus_economic':(preg_match('/prime|max/',$slug)?'focus_high':'focus_medium'),
            'qwen'=>preg_match('/flash|turbo/',$slug)?'focus_economic':(str_contains($slug,'max')?'focus_high':'focus_medium'),
            'x-ai'=>preg_match('/fast|mini/',$slug)?'focus_economic':(preg_match('/heavy|expert/',$slug)?'focus_high':'focus_medium'),
            'xiaomi','minimax'=>str_contains($slug,'flash')?'focus_economic':(preg_match('/max|ultra|omni/',$slug)?'focus_high':'focus_medium'),
            default=>'focus_medium'
        };
        $groups[$tier][$publisher][]=$m;
    }
    $out=['focus_economic'=>[],'focus_medium'=>[],'focus_high'=>[]];
    foreach($groups as $tier=>$publishers)foreach($publishers as $publisher=>$items) {
        // Avoid an older generation winning merely because it accumulated more usage.
        if(!in_array($publisher,['mistralai','x-ai'],true)) {
            $version=function($m){preg_match('/\d+(?:\.\d+)?/',explode('/',$m['id'],2)[1],$match);return $match[0] ?? '0';};
            $latest='0';foreach($items as $m)if(version_compare($version($m),$latest,'>'))$latest=$version($m);
            $items=array_values(array_filter($items,fn($m)=>version_compare($version($m),$latest,'=')));
        }
        // Prefer the latest release window (14 days), then measured popularity, then date.
        $newest=max(array_column($items,'created'));
        $items=array_values(array_filter($items,fn($m)=>(int)($m['created'] ?? 0)>=$newest-14*86400));
        usort($items,function($a,$b){
            $preview=fn($m)=>(bool)preg_match('/preview|experimental|beta/i',$m['id']);
            return ($preview($a)<=>$preview($b)) ?: (($a['usage_rank'] ?? PHP_INT_MAX)<=>($b['usage_rank'] ?? PHP_INT_MAX))
                ?: (($b['created'] ?? 0)<=>($a['created'] ?? 0)) ?: strcmp($a['id'],$b['id']);
        });
        $m=$items[0];$out[$tier][]=['id'=>$m['id'],'family'=>$families[$publisher],'publisher'=>$publisher,'created'=>$m['created'] ?? null];
    }
    return $out;
}
