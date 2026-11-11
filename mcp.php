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
    if($origin!=='' && rtrim($origin,'/')!==rtrim($base,'/'))mcpSend(['error'=>labT('mcp.mcpSend.1')],403);
    $token=trim($_GET['gate'] ?? '');
    if(preg_match('/^Bearer (.+)$/D',$_SERVER['HTTP_AUTHORIZATION'] ?? '',$bearer))$token=$bearer[1];
    $legacy=$token!=='';
    if($legacy){[$s,$role]=labMcpAuthorize($token);labUseSessionLocale($s);}
    if($_SERVER['REQUEST_METHOD']!=='POST'){header('Allow: POST');mcpSend(['error'=>labT('mcp.mcpSend.2')],405);}
    if(!str_contains($_SERVER['CONTENT_TYPE'] ?? '','application/json'))mcpSend(['error'=>labT('mcp.mcpSend.3')],415);
    $raw=file_get_contents('php://input',false,null,0,65537);if(strlen($raw)>65536)mcpSend(['error'=>labT('mcp.mcpSend.4')],413);
    $r=json_decode($raw,true,64,JSON_THROW_ON_ERROR);
    if(!is_array($r) || ($r['jsonrpc'] ?? '')!=='2.0' || !is_string($r['method'] ?? null))throw new InvalidArgumentException(labT('mcp.mcpSend.5'));
    $id=$r['id'] ?? null;$method=$r['method'];$params=$r['params'] ?? [];
    if($id!==null && !is_string($id) && !is_int($id))throw new InvalidArgumentException(labT('mcp.mcpSend.6'));
    if(!is_array($params))throw new InvalidArgumentException(labT('mcp.mcpSend.7'));
    if(!array_key_exists('id',$r)){if(!in_array($method,['notifications/initialized','notifications/cancelled'],true))throw new InvalidArgumentException(labT('mcp.mcpSend.8'));http_response_code(202);exit;}
    if($method==='initialize'){
        $version=$params['protocolVersion'] ?? '2025-11-25';
        if(!in_array($version,['2025-03-26','2025-06-18','2025-11-25'],true))$version='2025-11-25';
        $result=['protocolVersion'=>$version,'capabilities'=>['tools'=>new stdClass()],'serverInfo'=>['name'=>'PrisonLab','version'=>'1.1.0'],
            'instructions'=>$legacy?labPrompt('mcp.initialize', ['role' => $role === 'prisoner' ? labT('roles.prisoner') : labT('roles.interrogator')]):labPrompt('mcp.shared_initialize')];
    }elseif($method==='ping')$result=new stdClass();
    elseif($method==='tools/list'){
        $code=$legacy?[]:['code'=>['type'=>'string','minLength'=>27,'maxLength'=>27,'description'=>labT('mcp.invitation.argument')]];
        $tools=[
            ['name'=>'prisonlab_wait','description'=>labPrompt($legacy?'mcp.mcpSend.13':'mcp.shared_wait'),'inputSchema'=>['type'=>'object','properties'=>$code+['wait_seconds'=>['type'=>'integer','minimum'=>0,'maximum'=>15]],'required'=>array_keys($code),'additionalProperties'=>false]],
            ['name'=>'prisonlab_reply','description'=>labPrompt($legacy?'mcp.mcpSend.14':'mcp.shared_reply'),'inputSchema'=>['type'=>'object','properties'=>$code+['task_id'=>['type'=>'string'],'output'=>['type'=>'object','properties'=>['question'=>['type'=>'string'],'reply'=>['type'=>'string'],'expression'=>['type'=>'string'],'state_id'=>['type'=>'string'],'inner_thought'=>['type'=>'string']],'additionalProperties'=>false]],'required'=>array_merge(array_keys($code),['task_id','output']),'additionalProperties'=>false]]
        ];
        if(!$legacy)array_unshift($tools,['name'=>'prisonlab_join','description'=>labPrompt('mcp.shared_join'),'inputSchema'=>['type'=>'object','properties'=>$code,'required'=>['code'],'additionalProperties'=>false]]);
        $result=['tools'=>$tools];
    }
    elseif($method==='tools/call'){
        $args=$params['arguments'] ?? [];if(!is_array($args))throw new InvalidArgumentException(labT('mcp.mcpSend.15'));
        try{
            if(!$legacy){
                $joinCode=$args['code'] ?? null;
                if(!is_string($joinCode))throw new RuntimeException(labT('mcp.invitation.invalid'));
                [$s,$role]=labMcpAuthorizeCode($joinCode);labUseSessionLocale($s);
            }
            if(($params['name'] ?? '')==='prisonlab_join' && !$legacy){
                $data=['status'=>'joined','session_id'=>$s['session_id'],'role'=>$role,'locale'=>labLocale(),'instruction'=>labPrompt('mcp.joined')];
            }elseif(($params['name'] ?? '')==='prisonlab_wait'){
                $wait=$args['wait_seconds'] ?? 15;if(!is_int($wait) || $wait<0 || $wait>15)throw new RuntimeException(labT('mcp.mcpSend.16'));
                $deadline=microtime(true)+$wait;
                do{[$s,$role]=$legacy?labMcpAuthorize($token):labMcpAuthorizeCode($joinCode);$data=labMcpTask($s,$role);if($data['status']!=='waiting' || microtime(true)>=$deadline)break;usleep(250000);}while(true);
            }elseif(($params['name'] ?? '')==='prisonlab_reply')$data=labMcpReply($s,$role,$args);
            else throw new RuntimeException(labT('mcp.mcpSend.17'));
            $result=['content'=>[['type'=>'text','text'=>labJson($data)]],'isError'=>false];
        }catch(RuntimeException $e){$result=['content'=>[['type'=>'text','text'=>$e->getMessage()]],'isError'=>true];}
    }else mcpSend(['jsonrpc'=>'2.0','id'=>$id,'error'=>['code'=>-32601,'message'=>labT('mcp.mcpSend.18')]]);
    mcpSend(['jsonrpc'=>'2.0','id'=>$id,'result'=>$result]);
}catch(InvalidArgumentException|JsonException $e){mcpSend(['jsonrpc'=>'2.0','id'=>$id,'error'=>['code'=>-32600,'message'=>labT('mcp.mcpSend.19')]],400);}
catch(Throwable $e){mcpSend(['jsonrpc'=>'2.0','id'=>$id,'error'=>['code'=>-32001,'message'=>labT('mcp.mcpSend.20')]],401);}
