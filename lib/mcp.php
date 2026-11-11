<?php
declare(strict_types=1);

function labMcpHandoff(array $actor, string $role, string $url): string
{
    $messages = labMessages('browser.json');
    $efforts = array_combine(['none','minimal','low','medium','high','xhigh','max'], range(23,29));
    $effortKey = 'session-view.ui.' . ($efforts[$actor['effort']] ?? '');
    return labPrompt('mcp.handoff', [
        'role' => $messages[$role === 'prisoner' ? 'mcp.configure.2' : 'mcp.configure.3'],
        'model' => $actor['name'],
        'effort' => $messages[$effortKey] ?? $messages['mcp.handoff.1'],
        'url' => $url,
        'fields' => $messages[$role === 'prisoner' ? 'mcp.handoff.2' : 'mcp.handoff.3'],
    ]);
}

function labMcpJoinHandoff(array $actor, string $role, string $code): string
{
    return labPrompt('mcp.join', [
        'role' => labT($role === 'prisoner' ? 'roles.prisoner' : 'roles.interrogator'),
        'model' => $actor['name'],
        'effort' => $actor['effort'],
        'code' => $code,
    ]);
}

/** A gate is a revocable, session/role-scoped capability, never a funding credential. */
class LabMcpWaiting extends RuntimeException {}

function labMcpConfig(mixed $input,string $type):array {
    if($input===null)return [];
    if(!is_array($input) || array_is_list($input) && $input!==[])throw new RuntimeException(labT('mcp.McpConfig.1'));
    $out=[];
    foreach($input as $role=>$v){
        if(!in_array($role,['prisoner','interrogator'],true) || ($role==='prisoner' && $type==='reverse') || ($role==='interrogator' && $type==='human'))throw new RuntimeException(labT('mcp.McpConfig.2'));
        if(!is_array($v) || !is_string($v['name'] ?? null) || !is_string($v['effort'] ?? null))throw new RuntimeException(labT('mcp.McpConfig.3'));
        $name=trim($v['name']);$effort=$v['effort'];
        if(mb_strlen($name)<2 || mb_strlen($name)>100 || preg_match('/[\x00-\x1f\x7f]/u',$name) || !in_array($effort,['none','minimal','low','medium','high','xhigh','max','unknown'],true))throw new RuntimeException(labT('mcp.McpConfig.4'));
        $out[$role]=['name'=>$name,'effort'=>$effort,'id'=>'mcp/'.substr(hash('sha256',mb_strtolower($name)),0,24),'source'=>'mcp','verified'=>false];
    }
    return $out;
}
function labMcpPolicy(array $actor):array {
    return ['supported'=>true,'has_reasoning'=>$actor['effort']!=='none','effort'=>$actor['effort'],'source'=>'mcp','self_reported'=>true,'config'=>[]];
}
function labMcpPath(string $sid,string $role):string {
    if(!preg_match('/^session_[a-zA-Z0-9_]+$/D',$sid) || !in_array($role,['prisoner','interrogator'],true))throw new RuntimeException(labT('mcp.McpPath.1'));
    $dir=$GLOBALS['lab_storage'].'/mcp';
    if(!is_dir($dir) && !mkdir($dir,0700,true) && !is_dir($dir))throw new RuntimeException(labT('mcp.McpPath.2'));
    return $dir.'/'.$sid.'.'.$role.'.json';
}
function labMcpLocked(string $sid,string $role,callable $fn):mixed {
    $path=labMcpPath($sid,$role);$lock=fopen($path.'.lock','c');
    if(!$lock || !flock($lock,LOCK_EX))throw new RuntimeException(labT('mcp.McpLocked.1'));
    @chmod($path.'.lock',0600);
    try{
        $g=is_file($path)?labRead($path):[];
        $auth=$GLOBALS['lab_mcp_authorization'] ?? null;
        $field=$auth['field'] ?? 'gate_hash';
        if($auth && $auth['sid']===$sid && $auth['role']===$role && (empty($g[$field]) || !hash_equals($g[$field],$auth['hash']) || ($g['expires_at'] ?? 0)<time()))throw new RuntimeException(labT('mcp.McpLocked.2'));
        $before=$g;$result=$fn($g);if($g!==$before || !is_file($path)){labWrite($path,$g);@chmod($path,0600);}return $result;
    }
    finally{flock($lock,LOCK_UN);fclose($lock);}
}
/** Registry locks are always acquired before mailbox locks when both are needed. */
function labMcpInvitations(callable $fn,bool $write=false):mixed {
    $path=$GLOBALS['lab_storage'].'/mcp-invitations.json';
    $lock=fopen($path.'.lock','c');
    if(!$lock || !flock($lock,$write?LOCK_EX:LOCK_SH))throw new RuntimeException(labT('mcp.invitation.unavailable'));
    @chmod($path.'.lock',0600);
    try {
        $entries=is_file($path)?labRead($path):[];
        $before=$entries;$result=$fn($entries);
        if($write && $entries!==$before){
            $mask=umask(0077);
            try {labWrite($path,$entries);chmod($path,0600);}
            finally {umask($mask);}
        }
        return $result;
    }finally{flock($lock,LOCK_UN);fclose($lock);}
}

function labMcpIssueCapability(array $s,string $role,bool $revoke,bool $invitation):array {
    if(empty($s['mcp_actors'][$role]) || !empty($s['is_ended']) || !empty($s['is_published']))throw new RuntimeException(labT('mcp.McpGate.1'));
    $secret=$invitation?'PL-'.rtrim(strtr(base64_encode(random_bytes(18)),'+/','-_'),'='):bin2hex(random_bytes(32));
    $hash=hash('sha256',$secret);$expires=time()+86400;$sid=$s['session_id'];
    labMcpInvitations(function(&$entries)use($sid,$role,$hash,$expires,$revoke,$invitation){
        foreach($entries as $key=>$entry)if(($entry['session_id']===$sid && $entry['role']===$role) || $entry['expires_at']<time())unset($entries[$key]);
        labMcpLocked($sid,$role,function(&$g)use($hash,$expires,$revoke,$invitation){
            $g['gate_hash']=!$revoke && !$invitation?$hash:'';
            $g['join_hash']=!$revoke && $invitation?$hash:'';
            $g['expires_at']=$expires;$g['connected_at']=null;
        });
        if(!$revoke && $invitation)$entries[$hash]=['session_id'=>$sid,'role'=>$role,'expires_at'=>$expires];
    },true);
    return ['secret'=>$revoke?null:$secret,'expires_at'=>gmdate('c',$expires)];
}

function labMcpGate(array $s,string $role,bool $revoke=false):array {
    $capability=labMcpIssueCapability($s,$role,$revoke,false);
    return ['gate'=>$capability['secret']===null?null:$s['session_id'].'.'.$role.'.'.$capability['secret'],'expires_at'=>$capability['expires_at']];
}

function labMcpInvitation(array $s,string $role,bool $revoke=false):array {
    $capability=labMcpIssueCapability($s,$role,$revoke,true);
    return ['code'=>$capability['secret'],'expires_at'=>$capability['expires_at']];
}

function labMcpAuthorize(string $token):array {
    if(!preg_match('/^(session_[a-zA-Z0-9_]+)\.(prisoner|interrogator)\.([a-f0-9]{64})$/D',$token,$m))throw new RuntimeException(labT('mcp.McpAuthorize.1'));
    return labMcpAuthorizeCapability($m[1],$m[2],hash('sha256',$m[3]),'gate_hash');
}

function labMcpAuthorizeCode(string $code):array {
    if(!preg_match('/^PL-[a-zA-Z0-9_-]{24}$/D',$code))throw new RuntimeException(labT('mcp.invitation.invalid'));
    $hash=hash('sha256',$code);
    $entry=labMcpInvitations(fn($entries)=>$entries[$hash] ?? null);
    if(!$entry || $entry['expires_at']<time())throw new RuntimeException(labT('mcp.invitation.invalid'));
    return labMcpAuthorizeCapability($entry['session_id'],$entry['role'],$hash,'join_hash');
}

function labMcpAuthorizeCapability(string $sid,string $role,string $hash,string $field):array {
    labMcpPath($sid,$role);
    $path=$GLOBALS['lab_storage'].'/'.$sid.'.json';
    if(!is_file($path))throw new RuntimeException(labT('mcp.McpAuthorize.2'));
    $s=labRead($path);
    if(empty($s['mcp_actors'][$role]) || !empty($s['is_published']))throw new RuntimeException(labT('mcp.McpAuthorize.3'));
    labMcpLocked($sid,$role,function(&$g)use($hash,$field){
        if(empty($g[$field]) || !hash_equals($g[$field],$hash) || ($g['expires_at'] ?? 0)<time())throw new RuntimeException(labT('mcp.McpAuthorize.4'));
        if(time()-strtotime($g['connected_at'] ?? '1970-01-01')>=10)$g['connected_at']=gmdate('c');
    });
    $GLOBALS['lab_mcp_authorization']=['sid'=>$sid,'role'=>$role,'hash'=>$hash,'field'=>$field];
    return [$s,$role];
}
function labMcpChat(array &$s,string $role,array $messages):string {
    $result=labMcpLocked($s['session_id'],$role,function(&$g)use($s,$role,$messages){
        $turn=labTurns($s)+1;$task=$g['task'] ?? null;
        if($task && $task['turn']===$turn && empty($task['consumed'])){
            if(isset($task['response'])){$g['task']['consumed']=true;return ['raw'=>$task['response']];}
            return null;
        }
        // Never forward history, spectator notes or the other actor's directives on incremental tasks.
        $bootstrap=empty($g['delivered_tasks']);
        if($bootstrap)$context=$messages;
        else {
            $context=[];
            foreach(array_slice($messages,1) as $message)if($message['role']==='system')$context[]=$message;
            if($role==='prisoner'){
                $question=$s['pending_turn']['text'] ?? '';
                $context[]=['role'=>'user','content'=>$question];
            }else{
                $last=end($s['messages']);$context[]=['role'=>'user','content'=>$last['content'] ?? labPrompt('mcp.McpChat.1')];
                $context[]=['role'=>'system','content'=>labClosureInstruction($s,'interrogator').labPrompt('mcp.McpChat.2')];
            }
        }
        $context[]=['role'=>'system','content'=>labPrompt('mcp.declared_reasoning', ['effort' => $s['mcp_actors'][$role]['effort']])];
        $g['task']=['id'=>bin2hex(random_bytes(16)),'turn'=>$turn,'role'=>$role,'bootstrap'=>$bootstrap,'messages'=>$context,'created_at'=>gmdate('c'),'consumed'=>false];
        return null;
    });
    if($result!==null){
        $s['usage'][]=['purpose'=>$role,'model'=>$s['mcp_actors'][$role]['id'],'provider'=>labT('mcp.McpChat.5'),'usage'=>[],'timestamp'=>gmdate('c'),'cost_unavailable'=>true];
        return $result['raw'];
    }
    throw new LabMcpWaiting(labT('mcp.McpChat.6').($role==='prisoner'?labT('mcp.McpChat.7'):labT('mcp.McpChat.8')));
}
function labMcpTask(array $s,string $role):array {
    if(!empty($s['is_ended']))return ['status'=>'ended','instruction'=>labPrompt('mcp.McpTask.1')];
    return labMcpLocked($s['session_id'],$role,function(&$g){
        $task=$g['task'] ?? null;
        if(!$task || !empty($task['consumed']) || isset($task['response']))return ['status'=>'waiting','retry_after_seconds'=>15,'instruction'=>labPrompt('mcp.McpTask.2')];
        if(empty($task['delivered'])){$g['task']['delivered']=true;$g['delivered_tasks']=1+($g['delivered_tasks'] ?? 0);}
        return ['status'=>'task','task'=>array_intersect_key($task,array_flip(['id','turn','role','bootstrap','messages','created_at'])),
            'instruction'=>labPrompt('mcp.McpTask.3')];
    });
}
function labMcpReply(array $s,string $role,array $args):array {
    if(!empty($s['is_ended']))throw new RuntimeException(labT('mcp.McpReply.1'));
    $id=$args['task_id'] ?? '';$output=$args['output'] ?? null;
    if(!is_string($id) || !is_array($output) || array_is_list($output))throw new RuntimeException(labT('mcp.McpReply.2'));
    $field=$role==='prisoner'?'reply':'question';
    if(!is_string($output[$field] ?? null) || trim($output[$field])==='' || mb_strlen($output[$field])>12000)throw new RuntimeException(labT('mcp.McpReply.3'));
    // Allow only spoken output, expression and optional fictional spectator note, never raw reasoning.
    $clean=array_intersect_key($output,array_flip([$field,'state_id','expression','inner_thought']));
    foreach($clean as $v)if(!is_string($v) || strlen($v)>48000)throw new RuntimeException(labT('mcp.McpReply.4'));
    $raw=labJson($clean);
    return labMcpLocked($s['session_id'],$role,function(&$g)use($id,$raw){
        if(($g['task']['id'] ?? '')!==$id)throw new RuntimeException(labT('mcp.McpReply.5'));
        if(isset($g['task']['response'])){
            if(!hash_equals($g['task']['response'],$raw))throw new RuntimeException(labT('mcp.McpReply.6'));
            return ['status'=>'accepted','duplicate'=>true];
        }
        if(!empty($g['task']['consumed']))throw new RuntimeException(labT('mcp.McpReply.7'));
        $g['task']['response']=$raw;$g['task']['responded_at']=gmdate('c');
        return ['status'=>'accepted','instruction'=>labPrompt('mcp.McpReply.8')];
    });
}
function labMcpDelete(array $s):void {
    if(empty($s['mcp_actors']))return;
    labMcpInvitations(function(&$entries)use($s){
        foreach($entries as $hash=>$entry)if($entry['session_id']===$s['session_id'])unset($entries[$hash]);
        foreach(array_keys($s['mcp_actors'] ?? []) as $role)labMcpLocked($s['session_id'],$role,function(&$g){$g=[];});
        foreach(array_keys($s['mcp_actors'] ?? []) as $role){$p=labMcpPath($s['session_id'],$role);if(is_file($p))unlink($p);}
    },true);
}
function labMcpStatus(array $s):array {
    $out=[];
    foreach(array_keys($s['mcp_actors'] ?? []) as $role){
        $p=labMcpPath($s['session_id'],$role);$g=is_file($p)?labRead($p):[];
        $out[$role]=['active'=>(!empty($g['gate_hash']) || !empty($g['join_hash'])) && ($g['expires_at'] ?? 0)>time(),'invitation'=>!empty($g['join_hash']),'expires_at'=>$g['expires_at'] ?? null,
            'connected_at'=>$g['connected_at'] ?? null,'task_state'=>empty($g['task'])?'not_requested':(!empty($g['task']['consumed'])?'processed':(isset($g['task']['response'])?'reply_received':'waiting_agent'))];
    }
    return $out;
}
