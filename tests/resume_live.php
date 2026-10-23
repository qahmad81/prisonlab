<?php
require_once dirname(__DIR__) . '/lib/lab.php';
$root = dirname(__DIR__);
$env = labEnv($root . '/.env');
$GLOBALS['lab_storage'] = dirname($root) . '/.ai_prison_game-data';
$file = $root . '/test-results/live-smoke.json';
$s = labRead($file);
if (in_array('--replace-failed-deepseek-with-qwen', $argv, true)) {
    $old = 'deepseek/deepseek-v3.2'; $new = 'qwen/qwen3-235b-a22b-2507';
    if (isset($s['judge_reports'][$old])) throw new RuntimeException('لا يمكن استبدال تقرير مكتمل');
    $s['judge_models'] = array_map(fn($id) => $id === $old ? $new : $id, $s['judge_models']);
    $s['judge_changes'][] = ['from'=>$old,'to'=>$new,'reason'=>'فشلت تجربتان متتاليتان بسبب مهلة المزود؛ تغير اختيار اللجنة أثناء التطوير وأُبلغ المستخدم.'];
}
$save = function(array $s) use ($file) { labWrite($file, $s); };
labEvaluate($s, $env['OPENROUTER_API_KEY'] ?? '', labRead($root . '/models.json')['models'], $save);
echo labJson(['status'=>$s['evaluation_status'], 'error'=>$s['evaluation_error'] ?? null,
    'judges'=>array_keys($s['judge_reports']), 'evaluation'=>$s['evaluation'] ? array_intersect_key($s['evaluation'],array_flip(['prisoner_score','investigator_score','scores'])) : null]);
exit($s['evaluation_status'] === 'complete' ? 0 : 1);
