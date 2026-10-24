<?php
declare(strict_types=1);
require_once __DIR__.'/lib/lab.php';
require_once __DIR__.(is_file(__DIR__.'/lib/access.php')?'/lib/access.php':'/lib/accounts.php');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');header('Referrer-Policy: no-referrer');header('X-Content-Type-Options: nosniff');
ini_set('display_errors','0');set_time_limit(25);
function mcpSend(array $value,int $status=200):never {http_response_code($status);echo labJson($value);exit;}
$id=null;
try{
    $env=function_exists('labRuntimeBootstrap')?labRuntimeBootstrap():labAccountBootstrap();
    $origin=$_SERVER['HTTP_ORIGIN'] ?? '';$base=$env['APP_BASE_URL'] ?? '';
    if($origin!=='' && rtrim($origin,'/')!==rtrim($base,'/'))mcpSend(['error'=>'Origin rejected'],403);
    $token=trim($_GET['gate'] ?? '');
    if(preg_match('/^Bearer (.+)$/D',$_SERVER['HTTP_AUTHORIZATION'] ?? '',$bearer))$token=$bearer[1];
    [$s,$role]=labMcpAuthorize($token);
    if($_SERVER['REQUEST_METHOD']!=='POST'){header('Allow: POST');mcpSend(['error'=>'Use MCP Streamable HTTP POST'],405);}
    if(!str_contains($_SERVER['CONTENT_TYPE'] ?? '','application/json'))mcpSend(['error'=>'Use application/json'],415);
    $raw=file_get_contents('php://input',false,null,0,65537);if(strlen($raw)>65536)mcpSend(['error'=>'Request too large'],413);
    $r=json_decode($raw,true,64,JSON_THROW_ON_ERROR);
    if(!is_array($r) || ($r['jsonrpc'] ?? '')!=='2.0' || !is_string($r['method'] ?? null))throw new InvalidArgumentException('Invalid JSON-RPC request');
    $id=$r['id'] ?? null;$method=$r['method'];$params=$r['params'] ?? [];
    if($id!==null && !is_string($id) && !is_int($id))throw new InvalidArgumentException('Invalid request id');
    if(!is_array($params))throw new InvalidArgumentException('Invalid params');
    if(!array_key_exists('id',$r)){if(!in_array($method,['notifications/initialized','notifications/cancelled'],true))throw new InvalidArgumentException('Unknown notification');http_response_code(202);exit;}
    if($method==='initialize'){
        $version=$params['protocolVersion'] ?? '2025-11-25';
        if(!in_array($version,['2025-03-26','2025-06-18','2025-11-25'],true))$version='2025-11-25';
        $result=['protocolVersion'=>$version,'capabilities'=>['tools'=>new stdClass()],'serverInfo'=>['name'=>'PrisonLab','version'=>'1.0.0'],
            'instructions'=>'مختبر محاكاة عربي. أنت '.($role==='prisoner'?'المتهم':'المحقق').'. استدعِ prisonlab_wait حتى تصل مهمة، ثم prisonlab_reply. حافظ على سياقك محليًا؛ لا ترسل تفكيرك الخام. البوابة لا تخولك أدوات أخرى أو الوصول إلى حساب صاحبها. الاسم والتفكير معلنان وغير موثقين.'];
    }elseif($method==='ping')$result=new stdClass();
    elseif($method==='tools/list')$result=['tools'=>[
        ['name'=>'prisonlab_wait','description'=>'انتظر مهمتك التالية حتى 15 ثانية؛ عند waiting كرر الانتظار. المهمة الأولى تحوي الشخصية والقضية؛ اللاحقة تحوي آخر كلام الطرف الآخر وتوجيهات دورك فقط.','inputSchema'=>['type'=>'object','properties'=>['wait_seconds'=>['type'=>'integer','minimum'=>0,'maximum'=>15]],'additionalProperties'=>false]],
        ['name'=>'prisonlab_reply','description'=>'أرسل ردًا عربيًا للمهمة. output: للمحقق question وexpression؛ للمتهم reply وstate_id. inner_thought ملاحظة تمثيلية اختيارية للمشاهد فقط وليست استدلالًا خامًا.','inputSchema'=>['type'=>'object','properties'=>['task_id'=>['type'=>'string'],'output'=>['type'=>'object','properties'=>['question'=>['type'=>'string'],'reply'=>['type'=>'string'],'expression'=>['type'=>'string'],'state_id'=>['type'=>'string'],'inner_thought'=>['type'=>'string']],'additionalProperties'=>false]],'required'=>['task_id','output'],'additionalProperties'=>false]]
    ]];
    elseif($method==='tools/call'){
        $args=$params['arguments'] ?? [];if(!is_array($args))throw new InvalidArgumentException('Invalid arguments');
        try{
            if(($params['name'] ?? '')==='prisonlab_wait'){
                $wait=$args['wait_seconds'] ?? 15;if(!is_int($wait) || $wait<0 || $wait>15)throw new RuntimeException('الانتظار بين صفر و15 ثانية');
                $deadline=microtime(true)+$wait;
                do{[$s,$role]=labMcpAuthorize($token);$data=labMcpTask($s,$role);if($data['status']!=='waiting' || microtime(true)>=$deadline)break;usleep(250000);}while(true);
            }elseif(($params['name'] ?? '')==='prisonlab_reply')$data=labMcpReply($s,$role,$args);
            else throw new RuntimeException('أداة غير معروفة');
            $result=['content'=>[['type'=>'text','text'=>labJson($data)]],'isError'=>false];
        }catch(RuntimeException $e){$result=['content'=>[['type'=>'text','text'=>$e->getMessage()]],'isError'=>true];}
    }else mcpSend(['jsonrpc'=>'2.0','id'=>$id,'error'=>['code'=>-32601,'message'=>'Method not found']]);
    mcpSend(['jsonrpc'=>'2.0','id'=>$id,'result'=>$result]);
}catch(InvalidArgumentException|JsonException $e){mcpSend(['jsonrpc'=>'2.0','id'=>$id,'error'=>['code'=>-32600,'message'=>'Invalid request']],400);}
catch(Throwable $e){mcpSend(['jsonrpc'=>'2.0','id'=>$id,'error'=>['code'=>-32001,'message'=>'البوابة غير صالحة أو منتهية؛ اطلب رابطًا جديدًا من صاحب الجلسة.']],401);}
